(function($) {
    'use strict';

    const IremboPayCheckout = {
        init: function() {
            this.form = $('form.checkout');
            this.form.on('checkout_place_order_irembopay', this.onSubmit.bind(this));
            this.form.on('change', 'input[name="payment_method"]', this.onPaymentMethodChange.bind(this));
        },

        onSubmit: function() {
            // Optionally, trigger the IremboPay widget here if using card payments
            return true;
        },

        onPaymentMethodChange: function() {
            const selectedMethod = $('input[name="payment_method"]:checked').val();
            if (selectedMethod === 'irembopay') {
                this.initIremboPay();
            }
        },

        initIremboPay: function() {
            if (!document.getElementById('irembopay-widget')) {
                const script = document.createElement('script');
                script.id = 'irembopay-widget';
                script.src = 'https://dashboard.sandbox.irembopay.com/assets/payment/inline.js';
                script.async = true;
                document.head.appendChild(script);
            }
        }
    };

    $(document).ready(function() {
        IremboPayCheckout.init();
    });
})(jQuery);