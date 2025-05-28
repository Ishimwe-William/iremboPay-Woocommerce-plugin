<?php
defined('ABSPATH') || exit;

class WC_IremboPay_Gateway extends WC_Payment_Gateway {
    private $api;

    public function __construct() {
        $this->id = 'irembopay';
        $this->icon = WC_IREMBOPAY_URL . 'assets/images/IremboPay_logo.png';
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

        $this->api = new WC_IremboPay_API($this->secret_key, $this->testmode);

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_api_wc_irembopay_gateway', [$this, 'handle_webhook']);
        add_action('woocommerce_receipt_' . $this->id, [$this, 'receipt_page']);
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
                'redirect' => $order->get_checkout_payment_url(true)
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
            
            // Save additional metadata from IremboPay
            $order->update_meta_data('_irembopay_payment_method', $data['data']['paymentMethod']);
            $order->update_meta_data('_irembopay_payment_reference', $data['data']['paymentReference']);
            $order->update_meta_data('_irembopay_paid_at', $data['data']['paidAt']);
            $order->update_meta_data('_irembopay_currency', $data['data']['currency']);
            
            $order->save(); // Save the changes
        }

        status_header(200);
        exit('OK');
    }

    public function receipt_page($order_id) {
        $order = wc_get_order($order_id);
        $invoice_number = $order->get_meta('_irembopay_invoice_number');
        $public_key = $this->public_key;
        $widget_url = $this->testmode
            ? 'https://dashboard.sandbox.irembopay.com/assets/payment/inline.js'
            : 'https://dashboard.irembopay.com/assets/payment/inline.js';

        if (!$invoice_number || !$public_key) {
            wc_print_notice(__('Payment session error. Please try again.', 'wc-irembopay'), 'error');
            return;
        }

        $thanks_url = $order->get_checkout_order_received_url();
        $my_account_orders_url = wc_get_account_endpoint_url('orders');
        ?>
        <style>
        /* Scope all styles to our specific container */
        #wc-irembopay-payment-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        #wc-irembopay-payment-container .buttons-container {
            margin: 20px 0;
            text-align: center;
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        #wc-irembopay-payment-container .btn {
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 150px;
        }

        #wc-irembopay-payment-container .btn-primary {
            background-color: #0073aa;
            color: white !important;
        }

        #wc-irembopay-payment-container .btn-primary:hover {
            background-color: #005177;
            text-decoration: none;
        }

        #wc-irembopay-payment-container .btn-secondary {
            background-color: #f8f9fa;
            color: #6c757d !important;
            border: 1px solid #6c757d;
        }

        #wc-irembopay-payment-container .btn-secondary:hover {
            background-color: #e2e6ea;
            text-decoration: none;
        }

        #wc-irembopay-payment-container .message {
            text-align: center;
            margin-bottom: 20px;
            color: #666;
        }
        </style>

        <div id="wc-irembopay-payment-container">
            <p class="message">
                <?php esc_html_e('If the payment window does not open automatically, please click the button below to initiate payment.', 'wc-irembopay'); ?>
            </p>

            <div class="buttons-container">
                <button id="wc-irembopay-pay-btn" class="btn btn-primary" onclick="manualPayment()">
                    <?php esc_html_e('Pay Now', 'wc-irembopay'); ?>
                </button>
                <a href="<?php echo esc_url($my_account_orders_url); ?>" class="btn btn-secondary">
                    <?php esc_html_e('← Back to My Orders', 'wc-irembopay'); ?>
                </a>
            </div>
        </div>

        <!-- Payment script -->
        <script src="<?php echo esc_url($widget_url); ?>"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            function initiatePayment() {
                IremboPay.initiate({
                    publicKey: "<?php echo esc_js($public_key); ?>",
                    invoiceNumber: "<?php echo esc_js($invoice_number); ?>",
                    locale: IremboPay.locale.EN,
                    callback: function(err, resp) {
                        if (resp && (resp.status === 'PAID' || resp.paymentStatus === 'PAID')) {
                            // Payment successful - redirect to orders page
                            window.location.href = "<?php echo esc_url($my_account_orders_url); ?>";
                        } else if (err || resp.status === 'FAILED') {
                            // Payment failed or cancelled - redirect to orders
                            console.log('Payment error or cancelled:', err || resp);
                            window.location.href = "<?php echo esc_url($my_account_orders_url); ?>";
                        }
                        // If neither condition is met, stay on current page
                    }
                });
            }

            // Auto-initiate payment when page loads
            initiatePayment();
            
            // Add manual trigger button
            window.manualPayment = initiatePayment;
        });
        </script>
        <?php
    }
}
