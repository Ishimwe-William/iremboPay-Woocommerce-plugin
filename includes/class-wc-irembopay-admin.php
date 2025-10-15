<?php
defined('ABSPATH') || exit;

class WC_IremboPay_Admin {
    public static function get_form_fields() {
        return [
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
                'default' => __('Pay securely with IremboPay using Mobile Money (MTN, Airtel), Credit/Debit Cards, or Bank Transfer.', 'wc-irembopay')
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
                'description' => __('e.g., PI-5b2b36xxxx (from merchant portal).', 'wc-irembopay')
            ],
            'generic_product_code' => [
                'title' => __('Generic Product Code', 'wc-irembopay'),
                'type' => 'text',
                'description' => __('The product code registered in IremboPay dashboard for all WooCommerce orders, e.g., PC-40c131xxxx.', 'wc-irembopay'),
                'default' => 'PC-40c131xxxx'
            ]
        ];
    }
}