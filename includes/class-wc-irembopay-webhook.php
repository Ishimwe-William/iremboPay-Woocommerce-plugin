<?php
defined('ABSPATH') || exit;

class WC_IremboPay_Webhook {
    private $secret_key;

    public function __construct($secret_key) {
        $this->secret_key = $secret_key;
    }

    public function process() {
        $payload = file_get_contents('php://input');
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $signature_header = $headers['irembopay-signature'] ?? '';

        if ($signature_header && $this->secret_key) {
            $parts = [];
            foreach (explode(',', $signature_header) as $part) {
                [$k, $v] = explode('=', $part, 2);
                $parts[trim($k)] = trim($v);
            }
            $t = $parts['t'] ?? '';
            $s = $parts['s'] ?? '';
            $signed_payload = $t . '#' . $payload;
            $expected_signature = hash_hmac('sha256', $signed_payload, $this->secret_key);

            if (!hash_equals($expected_signature, $s)) {
                status_header(401);
                exit('Invalid signature');
            }
        }

        $data = json_decode($payload, true);
        if (!$data || empty($data['data']['invoiceNumber'])) {
            status_header(400);
            exit('Invalid payload');
        }

        $invoice_number = $data['data']['invoiceNumber'];
        $order_id = wc_get_orders([
            'meta_key' => '_irembopay_invoice_number',
            'meta_value' => $invoice_number,
            'return' => 'ids',
            'limit' => 1
        ]);
        $order = $order_id ? wc_get_order($order_id[0]) : false;

        if ($order && $data['success'] && $data['data']['paymentStatus'] === 'PAID') {
            $order->payment_complete();
            $order->add_order_note('IremboPay payment completed. Reference: ' . $data['data']['paymentReference']);
            $order->update_meta_data('_irembopay_payment_method', $data['data']['paymentMethod']);
            $order->update_meta_data('_irembopay_payment_reference', $data['data']['paymentReference']);
            $order->update_meta_data('_irembopay_paid_at', $data['data']['paidAt']);
            $order->update_meta_data('_irembopay_currency', $data['data']['currency']);
            $order->save();
        }

        status_header(200);
        exit('OK');
    }
}