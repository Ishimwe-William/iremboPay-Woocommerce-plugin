(function($) {
    'use strict';

    const IremboPayCheckout = {
        init: function() {
            this.form = $('form.checkout');
            // No automatic widget initiation during checkout
            // The payment flow will be handled by WooCommerce's standard process
            // Widget will only appear on the receipt page after order creation
            
            // Optional: Add any checkout-specific styling or validation here
            this.addCheckoutStyling();
        },

        addCheckoutStyling: function() {
            // Optional: Add any custom styling for IremboPay payment method
            // This runs when the checkout page loads
            const paymentMethod = $('#payment_method_irembopay');
            if (paymentMethod.length) {
                // Add custom CSS class or styling if needed
                paymentMethod.closest('.wc_payment_method').addClass('irembopay-payment-option');
            }
        },

        // Utility method to load IremboPay script (not used during checkout)
        loadIremboPayScript: function() {
            if (!document.getElementById('irembopay-widget')) {
                const script = document.createElement('script');
                script.id = 'irembopay-widget';
                script.src = wc_irembopay_params.is_testmode ? 
                    'https://dashboard.sandbox.irembopay.com/assets/payment/inline.js' :
                    'https://dashboard.irembopay.com/assets/payment/inline.js';
                script.async = true;
                script.onload = function() {
                    console.log('IremboPay script loaded');
                };
                script.onerror = function() {
                    console.error('Failed to load IremboPay script');
                };
                document.head.appendChild(script);
            }
        }
    };

    $(document).ready(function() {
        // Initialize checkout handling
        IremboPayCheckout.init();
        
        // Optional: Log when IremboPay is available as payment method
        if (typeof wc_irembopay_params !== 'undefined') {
            console.log('IremboPay payment gateway loaded');
        }
    });

})(jQuery);