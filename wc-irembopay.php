<?php
/**
 * Plugin Name: WooCommerce IremboPay Gateway
 * Description: Accept payments through IremboPay in your WooCommerce store.
 * Version: 1.0.7
 * Author: William Ishimwe
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('WC_IREMBOPAY_VERSION', '1.0.7');
define('WC_IREMBOPAY_PATH', plugin_dir_path(__FILE__));
define('WC_IREMBOPAY_URL', plugin_dir_url(__FILE__));

add_action('plugins_loaded', function() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>WooCommerce IremboPay Gateway requires WooCommerce to be installed and active.</p></div>';
        });
        return;
    }
    require_once WC_IREMBOPAY_PATH . 'includes/class-wc-irembopay-gateway.php';
    require_once WC_IREMBOPAY_PATH . 'includes/class-wc-irembopay-api.php';
    add_filter('woocommerce_payment_gateways', function($gateways) {
        $gateways[] = 'WC_IremboPay_Gateway';
        return $gateways;
    });
    add_action('wp_enqueue_scripts', function() {
        if (is_checkout()) {
            wp_enqueue_script(
                'irembopay-checkout',
                WC_IREMBOPAY_URL . 'assets/js/irembopay-checkout.js',
                ['jquery'],
                WC_IREMBOPAY_VERSION,
                true
            );
            
            // Get the IremboPay gateway settings
            $gateways = WC()->payment_gateways->get_available_payment_gateways();
            $irembopay_gateway = isset($gateways['irembopay']) ? $gateways['irembopay'] : null;
            
            if ($irembopay_gateway) {
                wp_localize_script('irembopay-checkout', 'wc_irembopay_params', array(
                    'public_key' => $irembopay_gateway->public_key,
                    'is_testmode' => $irembopay_gateway->testmode,
                    'my_account_orders_url' => wc_get_account_endpoint_url('orders'),
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('irembopay_checkout')
                ));
            }
        }
    });
});

add_action('add_meta_boxes', 'add_irembopay_meta_box');

function add_irembopay_meta_box() {
    add_meta_box(
        'irembopay_order_meta',         // Meta box ID
        'IremboPay Details',            // Title
        'display_irembopay_meta_box',   // Callback function
        'shop_order',                   // Post type (WooCommerce orders)
        'side',                         // Position (side column)
        'default'                       // Priority
    );
}

function display_irembopay_meta_box($post) {
    $order = wc_get_order($post->ID);
    
    // Retrieve stored metadata
    $payment_method = $order->get_meta('_irembopay_payment_method');
    $payment_reference = $order->get_meta('_irembopay_payment_reference');
    $paid_at = $order->get_meta('_irembopay_paid_at');
    $currency = $order->get_meta('_irembopay_currency');
    
    // Display the metadata
    echo '<p><strong>Payment Method:</strong> ' . esc_html($payment_method) . '</p>';
    echo '<p><strong>Payment Reference:</strong> ' . esc_html($payment_reference) . '</p>';
    echo '<p><strong>Paid At:</strong> ' . esc_html($paid_at) . '</p>';
    echo '<p><strong>Currency:</strong> ' . esc_html($currency) . '</p>';
}