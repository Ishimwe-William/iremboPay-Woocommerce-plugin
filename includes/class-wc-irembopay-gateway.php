<?php
defined('ABSPATH') || exit;

class WC_IremboPay_Gateway extends WC_Payment_Gateway {
    private $api;

    public function __construct() {
        $this->id = 'irembopay';
        $this->icon = WC_IREMBOPAY_URL . 'assets\images\IremboPay_logo.png';
        $this->has_fields = false;
        $this->method_title = __('IremboPay', 'wc-irembopay');
        $this->method_description = __('Pay securely via IremboPay (Mobile Money, Cards, Bank).', 'wc-irembopay');

        $this->supports = ['products'];

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = 'yes' === $this->get_option('testmode');
        $this->secret_key = $this->get_option('secret_key');
        $this->public_key = $this->get_option('public_key');
        $this->payment_account = $this->get_option('payment_account');
        $this->generic_product_code = $this->get_option('generic_product_code', 'WOOCOMMERCE_ORDER');

        $this->api = new WC_IremboPay_API(
            $this->secret_key,
            $this->testmode
        );

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_api_wc_irembopay_gateway', [$this, 'handle_webhook']);
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'wc-irembopay'),
                'type' => 'checkbox',
                'label' => __('Enable IremboPay Payment', 'wc-irembopay'),
                'default' => 'no'
            ],
            'title' => [
                'title' => __('Title', 'wc-irembopay'),
                'type' => 'text',
                'default' => __('IremboPay', 'wc-irembopay')
            ],
            'description' => [
                'title' => __('Description', 'wc-irembopay'),
                'type' => 'textarea',
                'default' => __('Pay securely using IremboPay.', 'wc-irembopay')
            ],
            'testmode' => [
                'title' => __('Test mode', 'wc-irembopay'),
                'type' => 'checkbox',
                'label' => __('Enable Test Mode', 'wc-irembopay'),
                'default' => 'yes'
            ],
            'secret_key' => [
                'title' => __('Secret Key', 'wc-irembopay'),
                'type' => 'password',
                'description' => __('Your IremboPay secret key (from merchant portal).', 'wc-irembopay'),
                'custom_attributes' => ['autocomplete' => 'off']
            ],
            'public_key' => [
                'title' => __('Public Key', 'wc-irembopay'),
                'type' => 'password',
                'description' => __('Your IremboPay public key (for widget/card payments).', 'wc-irembopay'),
                'custom_attributes' => ['autocomplete' => 'off']
            ],
            'payment_account' => [
                'title' => __('Payment Account Identifier', 'wc-irembopay'),
                'type' => 'text',
                'description' => __('e.g., TST-RWF (from merchant portal).', 'wc-irembopay')
            ],
            'generic_product_code' => [
                'title' => __('Generic Product Code', 'wc-irembopay'),
                'type' => 'text',
                'description' => __('The product code registered in IremboPay dashboard for all WooCommerce orders, e.g., WOOCOMMERCE_ORDER.', 'wc-irembopay'),
                'default' => 'WOOCOMMERCE_ORDER'
            ]
        ];
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        // Use the generic product code for all invoices
        $invoice = $this->api->create_invoice($order, $this->payment_account, $this->generic_product_code);

        if (!$invoice || empty($invoice['data']['invoiceNumber'])) {
            wc_add_notice(__('Could not create IremboPay invoice: ', 'wc-irembopay') . ($invoice['message'] ?? 'Unknown error'), 'error');
            return ['result' => 'failure'];
        }

        $invoice_number = $invoice['data']['invoiceNumber'];
        $payment_link = $invoice['data']['paymentLinkUrl'] ?? '';

        if ($payment_link) {
            $order->update_meta_data('_irembopay_invoice_number', $invoice_number);
            $order->save();

            return [
                'result' => 'success',
                'redirect' => $payment_link
            ];
        }

        wc_add_notice(__('Could not get IremboPay payment link.', 'wc-irembopay'), 'error');
        return ['result' => 'failure'];
    }

    public function handle_webhook() {
        $payload = file_get_contents('php://input');
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $signature_header = $headers['irembopay-signature'] ?? '';
        $secret_key = $this->secret_key;

        // Signature verification (see IremboPay docs)
        if ($signature_header && $secret_key) {
            $parts = [];
            foreach (explode(',', $signature_header) as $part) {
                [$k, $v] = explode('=', $part, 2);
                $parts[trim($k)] = trim($v);
            }
            $t = $parts['t'] ?? '';
            $s = $parts['s'] ?? '';
            $signed_payload = $t . '#' . $payload;
            $expected_signature = hash_hmac('sha256', $signed_payload, $secret_key);

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
        }

        status_header(200);
        exit('OK');
    }
}
