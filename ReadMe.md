# WooCommerce IremboPay Gateway

A secure WordPress plugin that integrates IremboPay payment gateway with WooCommerce, enabling your customers to pay using Mobile Money, Credit/Debit Cards, and Bank transfers in Rwanda.

## Features

- 🚀 **Multiple Payment Methods**: Mobile Money, Cards, and Bank transfers
- 🔒 **Secure Payments**: End-to-end encryption and webhook verification
- 🧪 **Test & Production Modes**: Full sandbox environment for testing
- 🔄 **Automatic Status Updates**: Real-time payment status via webhooks
- 📊 **Admin Dashboard**: Payment details and invoice management
- ⏰ **Payment Expiry**: 24-hour payment link expiration with countdown

## Requirements

- WordPress 5.0+
- WooCommerce 3.0+
- PHP 7.4+
- IremboPay merchant account

## Installation

### Via WordPress Admin

1. Download the plugin ZIP file
2. Go to **WordPress Admin** → **Plugins** → **Add New**
3. Click **Upload Plugin** and select the ZIP file
4. Click **Install Now** and then **Activate**

### Manual Installation

1. Extract the plugin files to `/wp-content/plugins/wc-irembopay/`
2. Go to **WordPress Admin** → **Plugins**
3. Find "WooCommerce IremboPay Gateway" and click **Activate**

## Configuration

### 1. Get IremboPay API Credentials

#### For Testing (Sandbox)
1. Log in to your [IremboPay Sandbox Portal](https://dashboard.sandbox.irembopay.com/)
2. Navigate to **API Settings**
3. Copy your sandbox credentials:
   - Secret Key
   - Public Key
   - Payment Account Identifier
   - Generic Product Code

#### For Production (Live)
1. Log in to your [IremboPay Merchant Portal](https://dashboard.irembopay.com/)
2. Navigate to **API Settings**
3. Copy your live credentials:
   - Secret Key
   - Public Key
   - Payment Account Identifier
   - Generic Product Code

### 2. Configure the Plugin

1. Go to **WooCommerce** → **Settings** → **Payments**
2. Find **IremboPay** and click **Manage**
3. Configure the following settings:

| Setting | Description | Required |
|---------|-------------|----------|
| **Enable/Disable** | Enable IremboPay payments | ✅ |
| **Title** | Payment method title shown to customers | ✅ |
| **Description** | Payment method description | ✅ |
| **Test Mode** | Toggle between sandbox and live environment | ✅ |
| **Secret Key** | Your IremboPay secret key (sandbox or live) | ✅ |
| **Public Key** | Your IremboPay public key (sandbox or live) | ✅ |
| **Payment Account** | Your payment account identifier | ✅ |
| **Generic Product Code** | Product code for WooCommerce orders | ✅ |

**Important:** Make sure to use matching credentials for your selected mode:
- **Test Mode ON**: Use sandbox credentials from `dashboard.sandbox.irembopay.com`
- **Test Mode OFF**: Use live credentials from `dashboard.irembopay.com`

### 3. Webhook Configuration

#### For Test Mode (Sandbox)
1. In your IremboPay sandbox portal, set the webhook URL to:
   ```
   https://yoursite.com/wc-api/wc_irembopay_gateway
   ```
2. Enable webhook notifications for payment status updates

#### For Production (Live)
1. In your IremboPay live merchant portal, set the same webhook URL:
   ```
   https://yoursite.com/wc-api/wc_irembopay_gateway
   ```
2. Enable webhook notifications for payment status updates

**Note:** The webhook URL remains the same for both modes. The plugin automatically handles requests based on your configured mode.

## Environment Overview

### Test Mode (Sandbox)
- **Dashboard**: `https://dashboard.sandbox.irembopay.com/`
- **API Endpoint**: `https://api.sandbox.irembopay.com/`
- **Payment Page**: `https://checkout.sandbox.irembopay.com/`
- **Widget Script**: `https://dashboard.sandbox.irembopay.com/assets/payment/inline.js`

### Production Mode (Live)
- **Dashboard**: `https://dashboard.irembopay.com/`
- **API Endpoint**: `https://api.irembopay.com/`
- **Payment Page**: `https://checkout.irembopay.com/`
- **Widget Script**: `https://dashboard.irembopay.com/assets/payment/inline.js`

## Usage

### For Customers

1. Add products to cart and proceed to checkout
2. Select **IremboPay** as payment method
3. Click **Place Order**
4. Complete payment using:
   - Mobile Money (MTN, Airtel)
   - Credit/Debit Cards
   - Bank transfers

### Payment Flow
- In **Test Mode**: Payments are processed through sandbox environment
- In **Production Mode**: Real payments are processed through live environment

### For Store Owners

#### Order Management

- View payment details in **WooCommerce** → **Orders**
- Each IremboPay order shows:
  - Invoice number
  - Payment method used
  - Transaction reference
  - Payment status
  - Expiry information with countdown

#### Payment Links

- Copy payment links directly from order details
- Share links with customers for pending payments
- Monitor expiry countdown in real-time
- Links automatically point to correct environment (sandbox/live)

## Testing

### Test Mode Setup

1. **Enable Test Mode** in plugin settings
2. Use **sandbox credentials** from `dashboard.sandbox.irembopay.com`
3. All payments will be processed in sandbox environment
4. Test payments are simulated and no real money is charged

### Test Payment Methods

Use the following test credentials provided by IremboPay:

#### Test Mobile Money Numbers
- MTN: Use test numbers provided in sandbox documentation
- Airtel: Use test numbers provided in sandbox documentation

#### Test Card Numbers
- Use test card numbers provided by IremboPay sandbox
- All test transactions are simulated

### Production Deployment

1. **Disable Test Mode** in plugin settings
2. Replace all credentials with **live credentials** from `dashboard.irembopay.com`
3. Update webhook URL in live merchant portal
4. Test with small amounts before going fully live

## API Reference

### Creating Invoices

```php
$api = new WC_IremboPay_API($secret_key, $test_mode);
$invoice = $api->create_invoice($order, $payment_account, $product_code);
```

### Retrieving Invoice Status

```php
$invoice_data = $api->get_invoice($invoice_number);
```

## Webhook Events

The plugin handles the following webhook events:

- `payment.completed` - Payment successfully processed
- `payment.failed` - Payment failed or declined
- `payment.expired` - Payment link expired

## File Structure

```
wc-irembopay/
├── assets/
│   ├── images/
│   │   └── IremboPay_logo.png
│   └── js/
│       └── irembopay-checkout.js
├── includes/
│   ├── class-wc-irembopay-admin.php
│   ├── class-wc-irembopay-api.php
│   ├── class-wc-irembopay-gateway.php
│   ├── class-wc-irembopay-metabox.php
│   └── class-wc-irembopay-webhook.php
├── templates/
│   └── checkout/
│       └── irembopay-receipt.php
├── .gitignore
├── uninstall.php
├── wc-irembopay.php
└── README.md
```

## Troubleshooting

### Common Issues

#### Plugin Not Appearing
- **Solution**: Ensure WooCommerce is installed and activated
- Check PHP version is 7.4 or higher

#### Payment Failures
- **Solution**: Verify API credentials match your selected mode
- Check if webhook URL is properly configured in correct portal
- Review logs in **WooCommerce** → **Status** → **Logs**

#### Wrong Environment
- **Problem**: Payments not working after switching modes
- **Solution**: Ensure credentials match the selected mode:
  - Test Mode ON = Sandbox credentials
  - Test Mode OFF = Live credentials

#### Webhook Not Working
- **Solution**: Ensure webhook URL is accessible and configured in correct portal
- Check server firewall settings
- Verify SSL certificate is valid
- Confirm webhook is set in matching environment (sandbox/live)

### Debug Logging

Enable WooCommerce logging to troubleshoot issues:

1. Go to **WooCommerce** → **Settings** → **Advanced** → **Logs**
2. Look for logs with source: `irembopay`

### Environment Verification

To verify you're in the correct environment:

1. Check payment URLs in order admin:
   - Sandbox: `checkout.sandbox.irembopay.com`
   - Live: `checkout.irembopay.com`

2. Check API responses in logs for correct endpoints

## Security

- All API communications use HTTPS
- Webhook signatures are verified using HMAC-SHA256
- Sensitive data is encrypted in database
- No payment data is stored locally
- Separate credentials for test and production environments

## Performance

- Optimized API calls with caching
- Lightweight frontend JavaScript
- Efficient webhook processing
- Minimal database queries
- Environment-specific resource loading

## Going Live Checklist

Before switching to production:

- [ ] Test thoroughly in sandbox mode
- [ ] Obtain live credentials from IremboPay
- [ ] Configure webhook in live merchant portal
- [ ] Disable test mode in plugin settings
- [ ] Update all credentials to live versions
- [ ] Test with small amount in production
- [ ] Monitor initial transactions closely

## Support

### Documentation
- [IremboPay API Documentation](https://docs.irembopay.com/)
- [WooCommerce Payment Gateway Documentation](https://woocommerce.com/document/payment-gateway-api/)

### Getting Help

1. **Plugin Issues**: Contact plugin developer
2. **Payment Issues**: 
   - Test mode: Contact IremboPay sandbox support
   - Live mode: Contact IremboPay production support
3. **WooCommerce Issues**: Check WooCommerce documentation

## Contributing

### Development Setup

1. Clone the repository
2. Install dependencies: `composer install`
3. Set up local WordPress/WooCommerce environment
4. Configure test IremboPay account in sandbox

### Coding Standards

- Follow WordPress Coding Standards
- Use PSR-4 autoloading
- Write PHPDoc comments
- Include unit tests

## Changelog

### v1.0.9 (Current)
- Added payment expiry functionality with countdown timers
- Improved admin interface with environment-aware payment links
- Enhanced webhook security and processing
- Better error handling and logging
- Added payment link copying feature
- Improved test/production mode handling

### v1.0.8
- Improved API error handling
- Added invoice reuse functionality
- Enhanced webhook processing
- Bug fixes and performance improvements

### v1.0.0
- Initial release
- Basic payment processing
- Webhook integration
- Admin interface

## License

This plugin is licensed under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

## Credits

**Author**: William Ishimwe  
**Version**: 1.0.9  
**Requires**: WordPress 5.0+, WooCommerce 3.0+, PHP 7.4+

---

**Disclaimer**: This plugin is not officially affiliated with IremboPay. Please test thoroughly in sandbox mode before using in production.
