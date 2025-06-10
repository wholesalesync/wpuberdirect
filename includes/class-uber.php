<?php
namespace WC_Uber;

use Winscode_Debug_Logger;

class Uber {
    const API_BASE = 'https://api.uber.com/v1/deliveries';

    private static function get_headers() {
        $access_token = get_option('uber_access_token');
        $client_id = get_option('uber_client_id');

        return [
            'Authorization' => 'Bearer ' . $access_token,
            'Uber-Client-Id' => $client_id,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
    }

    public static function create_delivery($body) {
        $response = wp_remote_post(self::API_BASE, [
            'headers' => self::get_headers(),
            'body'    => json_encode($body),
            'timeout' => 30
        ]);

        self::log_response('create_delivery', $response);
        return self::handle_response($response);
    }

    public static function get_delivery($delivery_id) {
        $url = self::API_BASE . '/' . $delivery_id;
        $response = wp_remote_get($url, [
            'headers' => self::get_headers(),
            'timeout' => 30
        ]);

        self::log_response('get_delivery', $response);
        return self::handle_response($response);
    }

    private static function handle_response($response) {
        if (is_wp_error($response)) {
            Winscode_Debug_Logger::log_error('Uber API error: ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 200 && $code < 300) {
            return $body;
        } else {
            Winscode_Debug_Logger::log_error("Uber API HTTP $code: " . json_encode($body));
            return false;
        }
    }

    private static function log_response($action, $response) {
        $log_data = [
            'action' => $action,
            'response' => is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response)
        ];
        Winscode_Debug_Logger::log_info(json_encode($log_data));
    }
}
?>
