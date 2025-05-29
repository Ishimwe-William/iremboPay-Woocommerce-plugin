# WooCommerce IremboPay Gateway

A secure WordPress plugin that integrates IremboPay payment gateway with WooCommerce, enabling your customers to pay using Mobile Money, Credit/Debit Cards, and Bank transfers in Rwanda.

## Features

- 🚀 **Multiple Payment Methods**: Mobile Money, Cards, and Bank transfers
- 🔒 **Secure Payments**: End-to-end encryption and webhook verification
- 🧪 **Test Mode**: Sandbox environment for testing
- 🔄 **Automatic Status Updates**: Real-time payment status via webhooks
- 📊 **Admin Dashboard**: Payment details and invoice management
- ⏰ **Payment Expiry**: 24-hour payment link expiration with countdown
- 🌍 **Localization Ready**: Translation support
- 📱 **Responsive**: Mobile-friendly payment interface

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

1. Log in to your [IremboPay Merchant Portal](https://merchant.irembopay.com/)
2. Navigate to **API Settings**
3. Copy your:
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
| **Test Mode** | Enable for testing (uses sandbox) | ✅ |
| **Secret Key** | Your IremboPay secret key | ✅ |
| **Public Key** | Your IremboPay public key | ✅ |
| **Payment Account** | Your payment account identifier | ✅ |
| **Generic Product Code** | Product code for WooCommerce orders | ✅ |

### 3. Webhook Configuration

1. In your IremboPay merchant portal, set the webhook URL to:
   ```
   https://yoursite.com/wc-api/wc_irembopay_gateway
   ```
2. Enable webhook notifications for payment status updates

## Usage

### For Customers

1. Add products to cart and proceed to checkout
2. Select **IremboPay** as payment method
3. Click **Place Order**
4. Complete payment using:
    - Mobile Money (MTN, Airtel)
    - Credit/Debit Cards
    - Bank transfers

### For Store Owners

#### Order Management

- View payment details in **WooCommerce** → **Orders**
- Each IremboPay order shows:
    - Invoice number
    - Payment method used
    - Transaction reference
    - Payment status
    - Expiry information

#### Payment Links

- Copy payment links directly from order details
- Share links with customers for pending payments
- Monitor expiry countdown in real-time

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

## Testing

### Test Mode Setup

1. Enable **Test Mode** in plugin settings
2. Use sandbox API credentials from IremboPay
3. Test payments use sandbox environment:
    - **Dashboard**: `https://dashboard.sandbox.irembopay.com/`
    - **API**: `https://api.sandbox.irembopay.com/`

### Test Payment Methods

- Use test mobile money numbers provided by IremboPay
- Use test card numbers for card payments
- All test payments are simulated

## Troubleshooting

### Common Issues

#### Plugin Not Appearing

- **Solution**: Ensure WooCommerce is installed and activated
- Check PHP version is 7.4 or higher

#### Payment Failures

- **Solution**: Verify API credentials are correct
- Check if webhook URL is properly configured
- Review logs in **WooCommerce** → **Status** → **Logs**

#### Webhook Not Working

- **Solution**: Ensure webhook URL is accessible
- Check server firewall settings
- Verify SSL certificate is valid

### Debug Logging

Enable WooCommerce logging to troubleshoot issues:

1. Go to **WooCommerce** → **Settings** → **Advanced** → **Logs**
2. Look for logs with source: `irembopay`

## Security

- All API communications use HTTPS
- Webhook signatures are verified using HMAC-SHA256
- Sensitive data is encrypted in database
- No payment data is stored locally

## Performance

- Optimized API calls with caching
- Lightweight frontend JavaScript
- Efficient webhook processing
- Minimal database queries

## Support

### Documentation
- [IremboPay API Documentation](https://docs.irembopay.com/)
- [WooCommerce Payment Gateway Documentation](https://woocommerce.com/document/payment-gateway-api/)

### Getting Help

1. **Plugin Issues**: Contact plugin developer
2. **Payment Issues**: Contact IremboPay support
3. **WooCommerce Issues**: Check WooCommerce documentation

## Contributing

### Development Setup

1. Clone the repository
2. Install dependencies: `composer install`
3. Set up local WordPress/WooCommerce environment
4. Configure test IremboPay account

### Coding Standards

- Follow WordPress Coding Standards
- Use PSR-4 autoloading
- Write PHPDoc comments
- Include unit tests

## Changelog

### v1.0.9 (Current)
- Added payment expiry functionality
- Improved admin interface with countdown timers
- Enhanced webhook security
- Better error handling and logging
- Added payment link copying feature

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

**Disclaimer**: This plugin is not officially affiliated with IremboPay. Please test thoroughly before using in production.
