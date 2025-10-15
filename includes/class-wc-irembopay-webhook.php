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
                $split = explode('=', $part, 2);
                if (count($split) !== 2) continue;
                [$k, $v] = $split;
                $parts[trim($k)] = trim($v);
            }

            $t = $parts['t'] ?? '';
            $s = $parts['s'] ?? '';

            // Validate timestamp to prevent replay attacks
            $timestamp = intval($t);
            if ($timestamp > 0 && abs(time() - $timestamp) > 300) { // 5 minutes tolerance
                $this->log('Webhook rejected: Timestamp too old or invalid', 'warning');
                status_header(401);
                exit('Timestamp too old or invalid');
            }

            $signed_payload = $t . '#' . $payload;
            $expected_signature = hash_hmac('sha256', $signed_payload, $this->secret_key);

            if (!hash_equals($expected_signature, $s)) {
                $this->log('Webhook rejected: Invalid signature', 'warning');
                status_header(401);
                exit('Invalid signature');
            }
        }

        $data = json_decode($payload, true);
        if (!is_array($data) || !isset($data['data']['invoiceNumber']) || empty($data['data']['invoiceNumber'])) {
            $this->log('Webhook rejected: Invalid payload structure', 'error');
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

        if ($order && isset($data['success']) && $data['success'] &&
            isset($data['data']['paymentStatus']) && $data['data']['paymentStatus'] === 'PAID') {

            $order->payment_complete();

            $payment_ref = isset($data['data']['paymentReference']) ? sanitize_text_field($data['data']['paymentReference']) : 'N/A';
            $order->add_order_note(sprintf('IremboPay payment completed. Reference: %s', $payment_ref));

            // Store payment metadata
            if (isset($data['data']['paymentMethod'])) {
                $order->update_meta_data('_irembopay_payment_method', sanitize_text_field($data['data']['paymentMethod']));
            }
            if (isset($data['data']['paymentReference'])) {
                $order->update_meta_data('_irembopay_payment_reference', sanitize_text_field($data['data']['paymentReference']));
            }
            if (isset($data['data']['paidAt'])) {
                $order->update_meta_data('_irembopay_paid_at', sanitize_text_field($data['data']['paidAt']));
            }
            if (isset($data['data']['currency'])) {
                $order->update_meta_data('_irembopay_currency', sanitize_text_field($data['data']['currency']));
            }
            if (isset($data['data']['amount'])) {
                $order->update_meta_data('_irembopay_amount', sanitize_text_field($data['data']['amount']));
            }

            $order->update_meta_data('_irembopay_status', 'PAID');
            $order->save();

            $this->log(sprintf('Payment completed for order #%s, invoice: %s', $order->get_order_number(), $invoice_number), 'info');
        } elseif ($order) {
            // Log other payment statuses
            $payment_status = isset($data['data']['paymentStatus']) ? $data['data']['paymentStatus'] : 'unknown';
            $this->log(sprintf('Webhook received for order #%s with status: %s', $order->get_order_number(), $payment_status), 'info');
        }

        status_header(200);
        exit('OK');
    }

    /**
     * Log messages
     */
    private function log($message, $level = 'info') {
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->log($level, $message, ['source' => 'irembopay-webhook']);
        }
    }
}