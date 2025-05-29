(function($) {
    'use strict';

    const IremboPayCheckout = {
        init: function() {
            this.form = $('form.checkout');
            this.addCheckoutStyling();
        },

        addCheckoutStyling: function() {
            const paymentMethod = $('#payment_method_irembopay');
            if (paymentMethod.length) {
                paymentMethod.closest('.wc_payment_method').addClass('irembopay-payment-option');
            }
        },

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
        IremboPayCheckout.init();
        if (typeof wc_irembopay_params !== 'undefined') {
            console.log('IremboPay payment gateway loaded');
        }
    });

})(jQuery);