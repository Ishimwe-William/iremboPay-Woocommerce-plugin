<?php
defined('ABSPATH') || exit;

class WC_IremboPay_Gateway extends WC_Payment_Gateway {
    private $api;
    private $webhook_handler;

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
        $this->generic_product_code = $this->get_option('generic_product_code');

        $this->api = new WC_IremboPay_API($this->secret_key, $this->testmode);
        $this->webhook_handler = new WC_IremboPay_Webhook($this->secret_key);

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_api_wc_irembopay_gateway', [$this, 'handle_webhook']);
        add_action('woocommerce_receipt_' . $this->id, [$this, 'receipt_page']);
    }

    public function init_form_fields() {
        $this->form_fields = WC_IremboPay_Admin::get_form_fields();
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        // Check for existing valid invoice first
        $existing_invoice = $this->get_existing_valid_invoice($order);

        if ($existing_invoice) {
            // Reuse existing invoice
            $invoice_number = $existing_invoice;
            $this->log("Reusing existing invoice: {$invoice_number} for order {$order_id}");
        } else {
            // Create new invoice
            $invoice = $this->api->create_invoice($order, $this->payment_account, $this->generic_product_code);

            if (!$invoice || empty($invoice['data']['invoiceNumber'])) {
                wc_add_notice(__('Could not create IremboPay invoice: ', 'wc-irembopay') . ($invoice['message'] ?? 'Unknown error'), 'error');
                return ['result' => 'failure'];
            }

            $invoice_number = $invoice['data']['invoiceNumber'];
            $order->update_meta_data('_irembopay_invoice_number', $invoice_number);
            
            // Store expiry time when creating invoice
            $expiry_time = $this->calculate_payment_expiry($order);
            $order->update_meta_data('_irembopay_expiry_at', $expiry_time);
            
            $order->save();
            
            $this->log("Created new invoice: {$invoice_number} for order {$order_id}");
        }

        return [
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        ];
    }

    /**
     * Check if order has existing valid invoice
     *
     * @param WC_Order $order
     * @return string|false Invoice number if valid, false otherwise
     */
    private function get_existing_valid_invoice($order) {
        $invoice_number = $order->get_meta('_irembopay_invoice_number');
        
        if (!$invoice_number) {
            return false;
        }

        // Check if payment already expired using stored expiry time
        if ($this->is_payment_expired($order)) {
            $this->log("Invoice {$invoice_number} has expired (local check)");
            return false;
        }

        // Check if invoice is still valid via API
        $invoice_data = $this->api->get_invoice($invoice_number);
        
        if (!$invoice_data || !$invoice_data['success']) {
            return false;
        }

        $invoice_info = $invoice_data['data'];
        
        // Check if invoice is not yet paid and not expired
        if ($invoice_info['paymentStatus'] !== 'NEW') {
            $this->log("Invoice {$invoice_number} already processed: {$invoice_info['paymentStatus']}");
            return false;
        }

        // Verify invoice amount matches current order total
        if (intval($invoice_info['amount']) !== intval($order->get_total())) {
            $this->log("Invoice {$invoice_number} amount mismatch: {$invoice_info['amount']} vs {$order->get_total()}");
            return false;
        }

        return $invoice_number;
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

        // Check if payment has expired
        if ($this->is_payment_expired($order)) {
            wc_print_notice(__('Payment link has expired. Please create a new order.', 'wc-irembopay'), 'error');
            return;
        }

        // Double-check invoice validity on receipt page
        $invoice_data = $this->api->get_invoice($invoice_number);
        if (!$invoice_data || !$invoice_data['success'] || $invoice_data['data']['paymentStatus'] !== 'NEW') {
            // If invoice is no longer valid, create a new one
            $new_invoice = $this->api->create_invoice($order, $this->payment_account, $this->generic_product_code);
            if ($new_invoice && !empty($new_invoice['data']['invoiceNumber'])) {
                $invoice_number = $new_invoice['data']['invoiceNumber'];
                $order->update_meta_data('_irembopay_invoice_number', $invoice_number);
                
                // Update expiry time for new invoice
                $expiry_time = $this->calculate_payment_expiry($order);
                $order->update_meta_data('_irembopay_expiry_at', $expiry_time);
                
                $order->save();
                $this->log("Created replacement invoice: {$invoice_number} for order {$order_id}");
            } else {
                wc_print_notice(__('Unable to process payment. Please try again.', 'wc-irembopay'), 'error');
                return;
            }
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

    /**
     * Calculate payment expiry time based on order creation
     * 
     * @param WC_Order $order
     * @param int $hours_to_expire Default 24 hours
     * @return int timestamp
     */
    private function calculate_payment_expiry($order, $hours_to_expire = 24) {
        $order_date = $order->get_date_created();
        if (!$order_date) {
            return current_time('timestamp') + ($hours_to_expire * 60 * 60);
        }
        
        return $order_date->getTimestamp() + ($hours_to_expire * 60 * 60);
    }
    
    /**
     * Check if payment has expired using stored expiry time
     * 
     * @param WC_Order $order
     * @return bool
     */
    private function is_payment_expired($order) {
        $expiry_time = $order->get_meta('_irembopay_expiry_at');
        
        if (empty($expiry_time)) {
            // No stored expiry, calculate and store it
            $expiry_time = $this->calculate_payment_expiry($order);
            $order->update_meta_data('_irembopay_expiry_at', $expiry_time);
            $order->save();
        }
        
        $expiry_timestamp = is_numeric($expiry_time) ? $expiry_time : strtotime($expiry_time);
        return current_time('timestamp') > $expiry_timestamp;
    }

    public function handle_webhook() {
        $this->webhook_handler->process();
    }

    /**
     * Log messages
     */
    private function log($message, $level = 'info') {
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->log($level, $message, ['source' => 'irembopay']);
        }
    }
}