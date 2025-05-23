<?php
/**
 * Plugin Name: WooCommerce IremboPay Gateway
 * Description: Accept payments through IremboPay in your WooCommerce store.
 * Version: 1.0.2
 * Author: William Ishimwe
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('WC_IREMBOPAY_VERSION', '1.0.2');
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
        }
    });
});