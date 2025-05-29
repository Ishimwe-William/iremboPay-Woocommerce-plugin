<?php
// defined('WP_UNINSTALL_PLUGIN') || exit;

// // Clean up plugin options
// delete_option('woocommerce_irembopay_settings');
// delete_option('wc_irembopay_version');

// // Clean up order meta
// global $wpdb;
// $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_irembopay_%'");