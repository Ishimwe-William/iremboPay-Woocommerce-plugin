<?php
/**
 * Plugin Name: WooCommerce IremboPay Gateway
 * Description: Accept payments through IremboPay in your WooCommerce store.
 * Version: 1.1.0
 * Author: William Ishimwe
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('WC_IREMBOPAY_VERSION', '1.1.0');
define('WC_IREMBOPAY_PATH', plugin_dir_path(__FILE__));
define('WC_IREMBOPAY_URL', plugin_dir_url(__FILE__));

// Check PHP version immediately - before any hooks
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    add_action('admin_notices', 'wc_irembopay_php_version_notice');
    add_action('admin_init', 'wc_irembopay_deactivate_plugin');
    return;
}

/**
 * Display PHP version error notice
 */
function wc_irembopay_php_version_notice() {
    $class = 'notice notice-error';
    $message = sprintf(
        __('WooCommerce IremboPay Gateway requires PHP version 7.4 or higher. Your current PHP version is %s. Please upgrade PHP to use this plugin.', 'woocommerce-irembopay'),
        PHP_VERSION
    );
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
}

/**
 * Display WooCommerce dependency error notice
 */
function wc_irembopay_woocommerce_missing_notice() {
    $class = 'notice notice-error';
    $message = __('WooCommerce IremboPay Gateway requires WooCommerce to be installed and active. Please install and activate WooCommerce first.', 'woocommerce-irembopay');
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
}

/**
 * Deactivate plugin if requirements not met
 */
function wc_irembopay_deactivate_plugin() {
    deactivate_plugins(plugin_basename(__FILE__));
    if (isset($_GET['activate'])) {
        unset($_GET['activate']);
    }
}

/**
 * Check if WooCommerce is active and meets minimum version
 */
function wc_irembopay_is_woocommerce_active() {
    if (!class_exists('WooCommerce')) {
        return false;
    }
    
    // Check WooCommerce version if needed
    if (defined('WC_VERSION') && version_compare(WC_VERSION, '3.0', '<')) {
        return false;
    }
    
    return true;
}

/**
 * Initialize the plugin
 */
add_action('plugins_loaded', 'wc_irembopay_init', 11);
function wc_irembopay_init() {
    // Check WooCommerce dependency
    if (!wc_irembopay_is_woocommerce_active()) {
        add_action('admin_notices', 'wc_irembopay_woocommerce_missing_notice');
        return;
    }

    // Load plugin files
    require_once WC_IREMBOPAY_PATH . 'includes/class-wc-irembopay-api.php';
    require_once WC_IREMBOPAY_PATH . 'includes/class-wc-irembopay-gateway.php';
    require_once WC_IREMBOPAY_PATH . 'includes/class-wc-irembopay-webhook.php';
    require_once WC_IREMBOPAY_PATH . 'includes/class-wc-irembopay-admin.php';
    require_once WC_IREMBOPAY_PATH . 'includes/class-wc-irembopay-metabox.php';

    // Register payment gateway
    add_filter('woocommerce_payment_gateways', 'wc_irembopay_add_gateway');
    
    // Initialize other hooks
    wc_irembopay_init_hooks();
}

/**
 * Add gateway to WooCommerce
 */
function wc_irembopay_add_gateway($gateways) {
    $gateways[] = 'WC_IremboPay_Gateway';
    return $gateways;
}

/**
 * Initialize plugin hooks
 */
function wc_irembopay_init_hooks() {
    // Enqueue scripts for checkout
    add_action('wp_enqueue_scripts', 'wc_irembopay_enqueue_checkout_scripts');
    
    // Initialize metabox functionality
    new WC_IremboPay_Metabox();
}

/**
 * Enqueue checkout scripts
 */
function wc_irembopay_enqueue_checkout_scripts() {
    if (!is_checkout()) {
        return;
    }
    
    wp_enqueue_script(
        'irembopay-checkout',
        WC_IREMBOPAY_URL . 'assets/js/irembopay-checkout.js',
        ['jquery'],
        WC_IREMBOPAY_VERSION,
        true
    );
    
    $gateways = WC()->payment_gateways->get_available_payment_gateways();
    $irembopay_gateway = isset($gateways['irembopay']) ? $gateways['irembopay'] : null;
    
    if ($irembopay_gateway) {
        wp_localize_script('irembopay-checkout', 'wc_irembopay_params', [
            'public_key' => $irembopay_gateway->public_key,
            'is_testmode' => $irembopay_gateway->testmode,
            'my_account_orders_url' => wc_get_account_endpoint_url('orders'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('irembopay_checkout')
        ]);
    }
}