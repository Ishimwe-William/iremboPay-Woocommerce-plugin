<?php
/**
 * IremboPay Metabox functionality with expiry information
 * 
 * Handles the display of IremboPay payment details in WooCommerce order admin pages
 *
 * @package WC_IremboPay
 * @since 1.0.9
 */

defined('ABSPATH') || exit;

/**
 * WC_IremboPay_Metabox class
 */
class WC_IremboPay_Metabox {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('admin_footer', array($this, 'add_expiry_script'));
    }

    /**
     * Add IremboPay meta box to order edit page
     */
    public function add_meta_box() {
        global $post;
        
        if (!$this->should_display_metabox($post)) {
            return;
        }
        
        add_meta_box(
            'irembopay_order_meta',
            __('IremboPay Payment Details', 'woocommerce-irembopay'),
            array($this, 'display_meta_box'),
            'shop_order',
            'side',
            'default'
        );
    }

    /**
     * Check if metabox should be displayed
     * 
     * @param WP_Post|null $post The post object
     * @return bool
     */
    private function should_display_metabox($post) {
        if (!$post || 'shop_order' !== $post->post_type) {
            return false;
        }
        
        $order = wc_get_order($post->ID);
        if (!$order || 'irembopay' !== $order->get_payment_method()) {
            return false;
        }
        
        return true;
    }

    /**
     * Display IremboPay meta box content
     * 
     * @param WP_Post $post The post object
     */
    public function display_meta_box($post) {
        $order = wc_get_order($post->ID);
        
        if (!$order) {
            $this->display_error_message(__('Unable to load order data.', 'woocommerce-irembopay'));
            return;
        }

        $payment_data = $this->get_payment_data($order);
        
        if (empty(array_filter($payment_data))) {
            $this->display_no_data_message();
            return;
        }

        $this->display_payment_data($payment_data, $order);
    }

    /**
     * Get payment data from order meta
     * 
     * @param WC_Order $order The order object
     * @return array
     */
    private function get_payment_data($order) {
        return array(
            'invoice_number' => $order->get_meta('_irembopay_invoice_number'),
            'payment_method' => $order->get_meta('_irembopay_payment_method'),
            'payment_reference' => $order->get_meta('_irembopay_payment_reference'),
            'transaction_id' => $order->get_meta('_irembopay_transaction_id'),
            'paid_at' => $order->get_meta('_irembopay_paid_at'),
            'currency' => $order->get_meta('_irembopay_currency'),
            'amount' => $order->get_meta('_irembopay_amount'),
            'status' => $order->get_meta('_irembopay_status'),
            'gateway_response' => $order->get_meta('_irembopay_gateway_response'),
            'expiry_at' => $order->get_meta('_irembopay_expiry_at')
        );
    }

    /**
     * Display payment data in a table format
     * 
     * @param array $payment_data
     * @param WC_Order $order
     */
    private function display_payment_data($payment_data, $order) {
        echo '<div class="irembopay-payment-details">';
        
        // Display payment link if invoice number exists
        if (!empty($payment_data['invoice_number'])) {
            $this->display_payment_link($payment_data['invoice_number'], $payment_data, $order);
        }
        
        echo '<table class="form-table">';
        
        $fields = array(
            'invoice_number' => __('Invoice Number', 'woocommerce-irembopay'),
            'payment_method' => __('Payment Method', 'woocommerce-irembopay'),
            'payment_reference' => __('Payment Reference', 'woocommerce-irembopay'),
            'transaction_id' => __('Transaction ID', 'woocommerce-irembopay'),
            'amount' => __('Amount Paid', 'woocommerce-irembopay'),
            'currency' => __('Currency', 'woocommerce-irembopay'),
            'status' => __('Payment Status', 'woocommerce-irembopay'),
            'paid_at' => __('Paid At', 'woocommerce-irembopay'),
            'expiry_at' => __('Expires At', 'woocommerce-irembopay'),
        );

        foreach ($fields as $key => $label) {
            if (!empty($payment_data[$key])) {
                $value = $this->format_field_value($key, $payment_data[$key]);
                echo '<tr>';
                echo '<th style="width: 40%; font-weight: 600;">' . esc_html($label) . ':</th>';
                echo '<td>' . wp_kses_post($value) . '</td>';
                echo '</tr>';
            }
        }
        
        // Display gateway response if available (for debugging)
        if (!empty($payment_data['gateway_response']) && current_user_can('manage_woocommerce')) {
            echo '<tr>';
            echo '<th style="width: 40%; font-weight: 600;">' . __('Gateway Response', 'woocommerce-irembopay') . ':</th>';
            echo '<td><details><summary>' . __('View Response', 'woocommerce-irembopay') . '</summary>';
            echo '<pre style="background: #f5f5f5; padding: 10px; margin-top: 10px; font-size: 11px; overflow-x: auto;">';
            echo esc_html(is_array($payment_data['gateway_response']) ? json_encode($payment_data['gateway_response'], JSON_PRETTY_PRINT) : $payment_data['gateway_response']);
            echo '</pre></details></td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</div>';
    }

    /**
     * Format field value for display
     * 
     * @param string $field_key
     * @param mixed $value
     * @return string
     */
    private function format_field_value($field_key, $value) {
        switch ($field_key) {
            case 'paid_at':
            case 'expiry_at':
                return $this->format_datetime($value);
            
            case 'amount':
                return $this->format_amount($value);
            
            case 'status':
                return $this->format_status($value);
            
            case 'invoice_number':
                return '<code>' . esc_html($value) . '</code>';
            
            case 'payment_reference':
            case 'transaction_id':
                return '<code>' . esc_html($value) . '</code>';
            
            default:
                return esc_html($value);
        }
    }

    /**
     * Format datetime for display
     * 
     * @param string $datetime
     * @return string
     */
    private function format_datetime($datetime) {
        if (empty($datetime)) {
            return '';
        }
        
        $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
        if (!$timestamp) {
            return esc_html($datetime);
        }
        
        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }

    /**
     * Format amount for display
     * 
     * @param mixed $amount
     * @return string
     */
    private function format_amount($amount) {
        if (empty($amount)) {
            return '';
        }
        
        return '<strong>' . wc_price($amount) . '</strong>';
    }

    /**
     * Format status for display
     * 
     * @param string $status
     * @return string
     */
    private function format_status($status) {
        if (empty($status)) {
            return '';
        }
        
        $status_colors = array(
            'completed' => '#46b450',
            'success' => '#46b450',
            'paid' => '#46b450',
            'pending' => '#ffb900',
            'processing' => '#00a0d2',
            'failed' => '#dc3232',
            'cancelled' => '#a00',
            'refunded' => '#666'
        );
        
        $color = isset($status_colors[strtolower($status)]) ? $status_colors[strtolower($status)] : '#666';
        
        return '<span style="color: ' . esc_attr($color) . '; font-weight: 600;">' . esc_html(ucfirst($status)) . '</span>';
    }

    /**
     * Display error message
     * 
     * @param string $message
     */
    private function display_error_message($message) {
        echo '<div class="notice notice-error inline">';
        echo '<p>' . esc_html($message) . '</p>';
        echo '</div>';
    }

    /**
     * Display payment link with expiry information
     * 
     * @param string $invoice_number
     * @param array $payment_data
     * @param WC_Order $order
     */
    private function display_payment_link($invoice_number, $payment_data, $order) {
        $payment_url = $this->get_payment_link_url($invoice_number);
        
        if (!$payment_url) {
            return;
        }
        
        // Use server time for consistency
        $server_time = current_time('timestamp');
        $expiry_time = $this->get_expiry_time($payment_data, $order);
        $is_expired = $expiry_time && $server_time > $expiry_time;
        $is_paid = !empty($payment_data['paid_at']) || $order->is_paid();
        
        echo '<div class="irembopay-payment-link" style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border: 1px solid #e1e5e9; border-radius: 4px;">';
        echo '<h4 style="margin: 0 0 8px 0; font-size: 13px; color: #23282d;">' . __('Payment Link', 'woocommerce-irembopay') . '</h4>';
        
        // Show expiry status
        if ($is_paid) {
            echo '<div style="color: #46b450; font-size: 12px; margin-bottom: 8px;">✓ ' . __('Payment Completed', 'woocommerce-irembopay') . '</div>';
        } elseif ($is_expired) {
            echo '<div style="color: #dc3232; font-size: 12px; margin-bottom: 8px;">⚠️ ' . __('Payment Link Expired', 'woocommerce-irembopay') . '</div>';
        } elseif ($expiry_time) {
            echo '<div style="color: #856404; font-size: 12px; margin-bottom: 8px;">';
            echo '⏰ ' . __('Expires:', 'woocommerce-irembopay') . ' ';
            echo '<span class="expiry-countdown" data-expiry="' . esc_attr($expiry_time) . '" data-server-time="' . esc_attr($server_time) . '">';
            echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $expiry_time);
            echo '</span>';
            echo '</div>';
        }
        
        echo '<div style="display: flex; align-items: center; gap: 10px;">';
        echo '<input type="text" value="' . esc_attr($payment_url) . '" readonly style="flex: 1; padding: 6px 8px; font-size: 12px; font-family: monospace; background: #fff; border: 1px solid #ddd; border-radius: 3px;" />';
        echo '<button type="button" class="button button-small" onclick="copyPaymentLink(this)" title="' . __('Copy Link', 'woocommerce-irembopay') . '">';
        echo '<span class="dashicons dashicons-admin-page" style="font-size: 16px; line-height: 1;"></span>';
        echo '</button>';
        
        if (!$is_expired || $is_paid) {
            echo '<a href="' . esc_url($payment_url) . '" target="_blank" class="button button-small" title="' . __('Open Payment Page', 'woocommerce-irembopay') . '">';
            echo '<span class="dashicons dashicons-external" style="font-size: 16px; line-height: 1;"></span>';
            echo '</a>';
        }
        
        echo '</div>';
        echo '</div>';
        
        // Add JavaScript for copy functionality
        $this->add_copy_script();
    }

    /**
     * Get expiry time from payment data or order
     * 
     * @param array $payment_data
     * @param WC_Order $order
     * @return int|null
     */
    private function get_expiry_time($payment_data, $order) {
         // Try to get expiry from payment data
        if (!empty($payment_data['expiry_at'])) {
            return is_numeric($payment_data['expiry_at']) ? $payment_data['expiry_at'] : strtotime($payment_data['expiry_at']);
        }
        
        // Fallback: calculate from order date (24 hours) using server time
        $order_date = $order->get_date_created();
        if ($order_date) {
            return $order_date->getTimestamp() + (24 * 60 * 60);
        }
        
        return null;
    }

    /**
     * Get payment link URL
     * 
     * @param string $invoice_number
     * @return string|null
     */
    private function get_payment_link_url($invoice_number) {
        if (empty($invoice_number)) {
            return null;
        }
        
        $is_testmode = $this->is_testmode();
        
        if ($is_testmode) {
            return 'https://checkout.sandbox.irembopay.com/' . $invoice_number;
        } else {
            return 'https://checkout.irembopay.com/' . $invoice_number;
        }
    }

    /**
     * Check if gateway is in test mode
     * 
     * @return bool
     */
    private function is_testmode() {
        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        $irembopay_gateway = isset($gateways['irembopay']) ? $gateways['irembopay'] : null;
        
        if ($irembopay_gateway && property_exists($irembopay_gateway, 'testmode')) {
            return $irembopay_gateway->testmode;
        }
        
        // Fallback: check gateway settings directly
        $gateway_settings = get_option('woocommerce_irembopay_settings', array());
        return isset($gateway_settings['testmode']) && 'yes' === $gateway_settings['testmode'];
    }

    /**
     * Add JavaScript for copy functionality and countdown
     */
    private function add_copy_script() {
        static $script_added = false;
        
        if ($script_added) {
            return;
        }
        
        echo '<script type="text/javascript">
        function copyPaymentLink(button) {
            var input = button.previousElementSibling;
            input.select();
            input.setSelectionRange(0, 99999);
            
            try {
                document.execCommand("copy");
                var originalTitle = button.title;
                button.title = "' . esc_js(__('Copied!', 'woocommerce-irembopay')) . '";
                button.style.color = "#46b450";
                
                setTimeout(function() {
                    button.title = originalTitle;
                    button.style.color = "";
                }, 2000);
            } catch (err) {
                console.log("Copy failed:", err);
            }
        }
        </script>';
        
        $script_added = true;
    }

    /**
     * Add expiry countdown script
     */
    public function add_expiry_script() {
        global $post;
        
        if (!$post || 'shop_order' !== $post->post_type) {
            return;
        }
        
        $order = wc_get_order($post->ID);
        if (!$order || 'irembopay' !== $order->get_payment_method()) {
            return;
        }
        
        $server_time = current_time('timestamp');
        ?>
        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            var countdownElements = document.querySelectorAll('.expiry-countdown');
            var serverTime = <?php echo $server_time; ?> * 1000; // Convert to milliseconds
            var clientTime = new Date().getTime();
            var timeDiff = serverTime - clientTime; // Server-client time difference
            
            function updateCountdowns() {
                countdownElements.forEach(function(element) {
                    var expiryTime = parseInt(element.getAttribute('data-expiry')) * 1000;
                    var now = new Date().getTime() + timeDiff; // Adjust client time to server time
                    var timeLeft = expiryTime - now;
                    
                    if (timeLeft <= 0) {
                        element.innerHTML = '<span style="color: #dc3232;">⚠️ Expired</span>';
                        element.parentElement.style.color = '#dc3232';
                        return;
                    }
                    
                    var hours = Math.floor(timeLeft / (1000 * 60 * 60));
                    var minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));  
                    var seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
                    
                    if (hours > 0) {
                        element.innerHTML = hours + 'h ' + minutes + 'm remaining';
                    } else if (minutes > 0) {
                        element.innerHTML = minutes + 'm ' + seconds + 's remaining';
                    } else {
                        element.innerHTML = seconds + 's remaining';
                    }
                });
            }
            
            if (countdownElements.length > 0) {
                updateCountdowns();
                setInterval(updateCountdowns, 1000);
            }
        });
        </script>
        <?php
    }

    /**
     * Display no data message
     */
    private function display_no_data_message() {
        echo '<div class="notice notice-info inline">';
        echo '<p>' . __('No IremboPay payment details found for this order.', 'woocommerce-irembopay') . '</p>';
        echo '</div>';
    }
}