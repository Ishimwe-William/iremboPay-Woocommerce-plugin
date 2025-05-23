<?php
defined('ABSPATH') || exit;

class WC_IremboPay_API {
    private $secret_key;
    private $test_mode;
    private $api_base_url;

    public function __construct($secret_key, $test_mode = false) {
        $this->secret_key = $secret_key;
        $this->test_mode = $test_mode;
        $this->api_base_url = $test_mode
            ? 'https://api.sandbox.irembopay.com/'
            : 'https://api.irembopay.com/';
    }

    public function create_invoice($order, $payment_account, $generic_product_code) {
        $body = [
            'transactionId' => $order->get_order_number(),
            'paymentAccountIdentifier' => $payment_account,
            'paymentItems' => [
                [
                    'code' => $generic_product_code,
                    'quantity' => 1,
                    'unitAmount' => intval($order->get_total())
                ]
            ],
            'description' => sprintf('Order %s on %s', $order->get_order_number(), get_bloginfo('name')),
            'expiryAt' => gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 day')),
            'language' => 'EN',
            'customer' => [
                'email' => $order->get_billing_email(),
                'phoneNumber' => $order->get_billing_phone(),
                'name' => $order->get_formatted_billing_full_name()
            ]
        ];

        return $this->make_request('payments/invoices', 'POST', $body);
    }

    private function make_request($endpoint, $method = 'POST', $data = []) {
        $url = $this->api_base_url . ltrim($endpoint, '/');
        $headers = [
            'irembopay-secretkey' => $this->secret_key,
            'X-API-Version' => '2',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'FaranuxApp'
        ];

        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => true
        ];

        if (!empty($data)) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $this->log('API Error: ' . $response->get_error_message(), 'error');
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code >= 400) {
            $error_message = isset($body['message']) ? $body['message'] : 'Unknown error occurred';
            $this->log('API Error: ' . $error_message, 'error');
            return ['success' => false, 'message' => $error_message];
        }

        return $body;
    }

    private function log($message, $level = 'info') {
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->log($level, $message, ['source' => 'irembopay']);
        }
    }
}