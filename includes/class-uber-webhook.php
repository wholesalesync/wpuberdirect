<?php

use WC_Uber\Uber;

class Uber_Webhook
{
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'uber_webhook_rest_api']);
    }

    public function uber_webhook_rest_api()
    {
        register_rest_route('uber/v2', '/webhook/', array(
            'methods' => 'POST',
            'callback' => [$this, 'recurring_payment_handler'],
            'permission_callback' => [$this, 'credentials_permission_callback']
        ));
        register_rest_route('uber/v2', '/get-deliveries/(?P<status>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => [$this, 'credentials_callback'],
            'permission_callback' => [$this, 'credentials_permission_callback']
        ));
    }

    public function recurring_payment_handler($data)
    {
        $post_data = $data->get_json_params();
        if (!isset($post_data['delivery_id']) || !is_string($post_data['delivery_id'])) {
            wp_send_json_error(['message' => 'Invalid delivery_id.']);
        }
        if (!isset($post_data['status']) || !is_string($post_data['status'])) {
            wp_send_json_error(['message' => 'Invalid status.']);
        }
        Uber::log($post_data, 'webhook ', 'webhook');
        assert(isset($post_data['delivery_id']));
        assert(isset($post_data['status']));
        if (isset($post_data['status']) && $post_data['status'] === 'delivered') {
            $args = [
                'post_type' => 'shop_order',
                'post_status' => 'any',
                'posts_per_page' => 1,
                'meta_query' => [
                    [
                        'key' => 'uber_delivery_id',
                        'value' => $post_data['delivery_id']
                    ]
                ]
            ];
            $query = new WP_Query($args);
            if ($query->have_posts()) {
                $order_id = $query->posts[0]->ID;
                $order = wc_get_order($order_id);
                $order->update_status('completed', 'Successful delivery by Uber.');

                $shipping_items = $order->get_items('shipping');
                foreach ($shipping_items as $item_id => $item) {
                    if ($item->get_meta('Delivery id') === $post_data['delivery_id']) {
                        $item->update_meta_data('Delivery status', $post_data['status']);
                        $item->update_meta_data('Delivery complete', 'Yes');
                        $item->save();
                        $order->save();
                        break;
                    }
                }
            }
        }
        die;
    }

    public function credentials_permission_callback()
    {
        return true;
    }

    public function credentials_callback($data)
    {
        $status = $data->get_param('status');
        $available_statuses = ['pending', 'pickup', 'pickup_complete', 'dropoff', 'delivered', 'canceled', 'returned', 'ongoing'];
        if (!in_array($status, $available_statuses)) {
            wp_send_json_error(['message' => $status . ' unknown status.']);
        }
        $customer_id = get_option('uber_customer_id', false);
        if (!$customer_id) {
            wp_send_json_error(['message' => 'Problems with credentials in merchant website.']);
        }
        $query_args = [
            'filter' => $status,
            'limit' => 100
        ];

        $url = 'https://api.uber.com/v1/customers/' . $customer_id . '/deliveries?' . http_build_query($query_args);
        $args = [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . Uber::getToken(),
                'Content-type' => 'application/json'
            ]
        ];

        $response = wp_remote_get($url, $args);
        $body = json_decode($response['body']);
        wp_send_json_success($body->data);
    }
}
new Uber_Webhook();
