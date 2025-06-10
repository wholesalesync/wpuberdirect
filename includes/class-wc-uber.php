<?php

/**
 * Uber Shipping Method.
 *
 * @version 2.6.0
 * @package WooCommerce/Classes/Shipping
 */

use WC_Uber\Uber as Uber;

class WC_Uber_Shipping_Method extends WC_Shipping_Method {
    /**
     * @var float|int
     */
    private $add_fees = Uber::FEE;
    private bool $free_shipping;
    /**
     * @var int|mixed
     */
    private $free_shipping_cart_total;
    /**
     * @var false|mixed|null
     */
    private $customer_id;
    private string $icon;

    /**
     * Constructor. The instance ID is passed to this.
     */
    public function __construct($instance_id = 0) {
        parent::__construct();

        $this->id = 'uber';
        $this->icon = plugins_url('assets/img/uber.png', dirname(__FILE__));
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Uber Direct', 'wc-uber');
        $this->method_description = __('Uber is transforming the way goods move around cities by enabling anyone to have anything delivered on-demand.', 'wc-uber');
        $this->supports = [
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal'
        ];
        
        // Store location details
        $this->store_address = WC()->countries->get_base_address();
        $this->store_address_2 = WC()->countries->get_base_address_2();
        $this->store_city = WC()->countries->get_base_city();
        $this->store_postcode = WC()->countries->get_base_postcode();
        $this->store_state = WC()->countries->get_base_state();
        $this->store_country = WC()->countries->get_base_country();

        // Add hooks
        add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
        add_filter('woocommerce_cart_shipping_packages', [$this, 'pickup_datetime'], 10, 1);
        add_action('woocommerce_before_order_itemmeta', [$this, 'uber_logo'], 10, 2);
        add_action('woocommerce_after_order_itemmeta', [$this, 'delivery_request_block'], 10, 2);
        add_filter('woocommerce_cart_totals_coupon_html', [$this, 'change_coupon_html'], 10, 3);

        $this->init();
    }

    /**
     * Changes the coupon HTML display
     */
    public function change_coupon_html($coupon_html, $coupon, $discount_amount_html) {
        if ($coupon->get_meta('uber_free_shipping')) {
            $amount = WC()->cart->get_coupon_discount_amount(
                $coupon->get_code(), 
                WC()->cart->display_cart_ex_tax
            );
            if (empty($amount)) {
                $coupon_html = sprintf(
                    'Uber free shipping <a href="%s" class="woocommerce-remove-coupon" data-coupon="%s">%s</a>',
                    esc_url(add_query_arg(
                        'remove_coupon',
                        rawurlencode($coupon->get_code()),
                        defined('WOOCOMMERCE_CHECKOUT') ? wc_get_checkout_url() : wc_get_cart_url()
                    )),
                    esc_attr($coupon->get_code()),
                    __('[Remove]', 'woocommerce')
                );
            }
        }
        return $coupon_html;
    }

    /**
     * Displays the delivery request block
     */
    public function delivery_request_block($term_id, $item) {
        if (is_a($item, 'WC_Order_Item_Shipping') && $item->get_method_id() === 'uber') {
            include_once dirname(__FILE__, 2) . '/templates/html-delivery-request.php';
        }
    }

    /**
     * Displays the Uber logo
     */
    public function uber_logo($term_id, $item) {
        if (is_a($item, 'WC_Order_Item_Shipping') && $item->get_method_id() === 'uber') {
            include_once dirname(__FILE__, 2) . '/templates/html-uber-logo.php';
        }
    }

    /**
     * Schedule pickup datetime
     */
    public function pickup_datetime($packages) {
        foreach ($packages as $key => $package) {
            $packages[$key]['destination']['pickup_datetime'] = date('Y-m-d\TH:i:sP', time() + 3600);
        }
        return $packages;
    }

    /**
     * Init user set variables.
     */
    public function init() {
        $this->instance_form_fields = include 'settings.php';
        $this->title = $this->get_option('title');
        $this->add_fees += (int)$this->get_option('add_fees') * 100;
        $this->free_shipping = 'yes' === $this->get_option('free_shipping');
        $this->free_shipping_cart_total = $this->get_option('free_shipping_cart_total') ?? 0;
        $this->customer_id = get_option('uber_customer_id');
    }

    /**
     * Helper function to create structured address
     */
    private function create_structured_address($address_data) {
        return array_filter([
            'street_address' => array_filter([
                $address_data['address'] ?? '',
                $address_data['address_2'] ?? ''
            ]),
            'city' => $address_data['city'] ?? '',
            'state' => $address_data['state'] ?? '',
            'zip_code' => $address_data['postcode'] ?? '',
            'country' => $address_data['country'] ?? 'US'
        ], function($value) {
            if (is_array($value)) {
                return !empty(array_filter($value));
            }
            return !empty($value);
        });
    }

    /**
     * Process shipping rate response
     */
    private function process_shipping_response($response, $package) {
        if ($response['response']['code'] !== 200) {
            WC()->session->__unset('uber_available');
            if (WC()->session->get('uber_datetime')) {
                WC()->session->__unset('uber_datetime');
                WC()->session->__unset('uber_human_date');
            }
            $rate = [
                'id' => $this->get_rate_id(),
                'label' => $this->title,
                'cost' => 0,
                'package' => $package
            ];
            return $this->add_rate($rate);
        }

        WC()->session->set('uber_available', 'yes');
        $body = json_decode($response['body']);
        
        // Get timezone information
        $merchant_timezone = get_option('uber_merchant_timezone');
        $timezones = [
            'America/New_York',
            'America/Chicago',
            'America/Denver',
            'America/Phoenix',
            'America/Los_Angeles',
            'America/Anchorage',
            'America/Adak',
            'Pacific/Honolulu'
        ];
        $tz = new DateTimeZone($timezones[$merchant_timezone]);
        $now = new DateTime('now', $tz);
        $time_offset = $tz->getOffset($now) / -1;
        $timezone = 'GMT-' . $time_offset / 3600;

        // Process metadata
        $meta_data = $this->prepare_meta_data($body, $time_offset, $timezone);
        
        // Calculate cost
        $cost = $this->calculate_shipping_cost($body);
        
        // Prepare rate
        $rate = [
            'id' => $this->get_rate_id(),
            'label' => $this->get_rate_label(),
            'cost' => $cost,
            'package' => $package,
            'meta_data' => $meta_data
        ];

        $this->add_rate($rate);
    }

    /**
     * Prepare metadata for shipping rate
     */
    private function prepare_meta_data($body, $time_offset, $timezone) {
        $meta_data = [];
        foreach ((array)$body as $key => $value) {
            if (in_array($key, ['currency', 'currency_type'])) {
                continue;
            }

            if (in_array($key, ['created', 'expires', 'dropoff_eta'])) {
                $time = strtotime($value) - $time_offset;
                $value = date("F j, Y, g:i a", $time) . ' (' . $timezone . ')';
            } elseif (in_array($key, ['duration', 'pickup_duration'])) {
                $value .= ' min';
            }

            $meta_data['Quote ' . $key] = $value;
        }

        if (isset($body->fee)) {
            $meta_data['Quote fee'] = '$' . ($body->fee + $this->add_fees) / 100;
        }

        if ($human_date = WC()->session->get('uber_human_date')) {
            $meta_data['Quote delivery time'] = date('F j, Y, g:i a', strtotime($human_date)) . ' (' . $timezone . ')';
        }

        if ($this->free_shipping && WC()->cart->get_subtotal() >= $this->free_shipping_cart_total) {
            $meta_data['Free shipping'] = 'Yes';
        }

        return $meta_data;
    }

    /**
     * Calculate shipping cost
     */
    private function calculate_shipping_cost($body) {
        if ($this->free_shipping && WC()->cart->get_subtotal() >= $this->free_shipping_cart_total) {
            return 0;
        }

        $cost = ($body->fee + $this->add_fees) / 100;

        // Check for free shipping coupons
        $coupons = WC()->cart->get_applied_coupons();
        foreach ($coupons as $coupon_code) {
            $coupon = new WC_Coupon($coupon_code);
            if ($coupon->get_meta('uber_free_shipping')) {
                return 0;
            }
        }

        return $cost;
    }

    /**
     * Get rate label
     */
    private function get_rate_label() {
        $label = $this->title;
        
        $coupons = WC()->cart->get_applied_coupons();
        foreach ($coupons as $coupon_code) {
            $coupon = new WC_Coupon($coupon_code);
            if ($coupon->get_meta('uber_free_shipping')) {
                $label .= ' (Free Shipping)';
                break;
            }
        }

        return $label;
    }

    /**
     * Calculate shipping method
     *
     * @param array $package (default: array())
     */
    public function calculate_shipping($package = []): void {
        $post_data = [];
        parse_str($_POST['post_data'] ?? '', $post_data);

        // Create structured addresses
        $dropoff_address = $this->create_structured_address($package['destination']);
        $pickup_address = $this->create_structured_address([
            'address' => $this->store_address,
            'address_2' => $this->store_address_2,
            'city' => $this->store_city,
            'state' => $this->store_state,
            'postcode' => $this->store_postcode,
            'country' => $this->store_country
        ]);

        // Validate addresses
        if (empty($dropoff_address['street_address']) || empty($dropoff_address['zip_code'])) {
            Uber::log('Invalid dropoff address - missing required fields', 'error', 'quote');
            $this->add_rate([]);
            return;
        }

        if (empty($pickup_address['street_address']) || empty($pickup_address['zip_code'])) {
            Uber::log('Invalid pickup address - missing required fields', 'error', 'quote');
            $this->add_rate([]);
            return;
        }

        // Prepare API request
        $url = 'https://api.uber.com/v1/customers/' . $this->customer_id . '/delivery_quotes';
        $args_body = [
            'dropoff_address' => $dropoff_address,
            'pickup_address' => $pickup_address
        ];

        // Add optional parameters
        if (isset($phone)) {
            $args_body['dropoff_phone_number'] = $phone;
        }

        if (!empty($post_data['order_comments'])) {
            $args_body['dropoff_notes'] = $post_data['order_comments'];
        }

        if ($datetime = WC()->session->get('uber_datetime')) {
            $time = strtotime($datetime);
            $args_body['dropoff_deadline_dt'] = $datetime;
            $args_body['pickup_deadline_dt'] = date('c', $time);
            $args_body['pickup_ready_dt'] = date('c', $time - 20 * 60);
        }

        $args = [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . Uber::getToken(),
                'Content-type' => 'application/json'
            ],
            'body' => wp_json_encode($args_body)
        ];

        // Log and make request
        Uber::log($args_body, 'request', 'quote');
        $response = wp_remote_post($url, $args);
        Uber::log($response['body'] ?? '', 'response', 'quote');

        if (is_wp_error($response)) {
            $this->add_rate([]);
            return;
        }

        // Process response
        $this->process_shipping_response($response, $package);
    }
}