# IremboPay WooCommerce Integration Readme

**API Docs:** [IremboPay API v2.0 Documentation](https://docs.irembopay.com/v2.0/)

---

## Overview

This document guides you through integrating the IremboPay payment gateway (API v2.0) with WooCommerce. It covers merchant portal setup, plugin installation, error handling, widget integration, plugin file structure, webhook testing, logging, and developer tools.

---

## Plugin File Structure

```
wp-content/
└── plugins/
    └── wc-irembopay/
        ├── wc-irembopay.php
        ├── includes/
        │   ├── class-wc-irembopay-gateway.php
        │   └── class-wc-irembopay-api.php
        ├── assets/
        │   ├── js/
        │   │   └── irembopay-checkout.js
        │   └── images/
        │       └── IremboPay_logo.png
```

---

## Prerequisites

- **WooCommerce Store:** WooCommerce installed and active (min. v5.0).
- **IremboPay Merchant Account:** [Sign up here](https://irembo.com/irembopay/).
- **Development Tools:** Postman, code editor, and access to your server.
- **Technical Knowledge:** PHP, JavaScript, WordPress plugin basics.

---

## Key Integration Points & Summary

- **Generic Product:** Uses a single product (e.g., `WOOCOMMERCE_ORDER`) for all WooCommerce orders.
- **API v2.0:** All API requests use `irembopay-secretkey` and `X-API-Version: 2` headers.
- **Payment Flow:** Creates an invoice, then redirects to the IremboPay payment link or loads the widget for card payments.
- **Widget:** Loaded inline on the checkout page (not in a new tab).
- **Callback URL:** `https://yourdomain.com/wc-api/wc_irembopay_gateway` for payment notifications.
- **Error Handling:** All errors are logged to WooCommerce logs (`WooCommerce > Status > Logs > irembopay-*.log`).
- **Testing:** Use sandbox credentials and endpoints for development.

---

## Step-by-Step Integration

### 1. Merchant Portal Setup

1. **Create a Payment Account:**  
   - Add a bank account in the portal.
   - Note the `paymentAccountIdentifier` (e.g., `TST-RWF`).

2. **Register a Generic Product:**  
   - Add a product (e.g., code: `WOOCOMMERCE_ORDER`).

3. **Configure Callback URL:**  
   - Set to `https://yourdomain.com/wc-api/wc_irembopay_gateway` (HTTPS, public).

4. **Obtain API Keys:**  
   - Get your **secret key** (API requests) and **public key** (widget) from the portal.

---

### 2. Plugin Installation & Configuration

1. **Install the Plugin:**  
   - Copy files to `wp-content/plugins/wc-irembopay/`.
   - Activate in WordPress admin.

2. **Configure Plugin Settings:**  
   - Go to WooCommerce > Settings > Payments > IremboPay.
   - Enter:
     - **Secret Key:** From IremboPay portal.
     - **Public Key:** From IremboPay portal (for widget).
     - **Payment Account Identifier:** e.g., `TST-RWF`.
     - **Generic Product Code:** e.g., `WOOCOMMERCE_ORDER`.
     - **Test Mode:** Enable for sandbox.

---

### 3. Payment Flow

1. **Invoice Creation:**  
   - The plugin creates an invoice using the generic product code, quantity 1, and the order total as `unitAmount`.

2. **Redirect to Payment:**  
   - Customer is redirected to the `paymentLinkUrl` or the widget is loaded inline.

3. **Webhook Handling:**  
   - IremboPay sends payment notifications to the callback URL.
   - The plugin verifies the webhook signature and updates the WooCommerce order status.

---

### 4. Error Handling & Logging

- **API Errors:**  
  - All API/network errors are logged to WooCommerce logs:  
    `WooCommerce > Status > Logs > irembopay-*.log`
- **Webhook Security:**  
  - The plugin verifies the `irembopay-signature` header using your secret key.
- **Customer Feedback:**  
  - Clear error messages are shown at checkout if payment fails.

---

### 5. Testing

- **Sandbox Environment:**  
  - Use sandbox endpoints and test credentials.
- **Test Data:**  
  - Use provided test phone numbers and card details from IremboPay docs.
- **Postman:**  
  - Use the sample collection below to test API endpoints and webhook payloads.

---

### 6. Production Launch

- **Switch to Production Keys and URLs:**  
  - Update plugin settings with live credentials and endpoints.
- **Verify Callback URL:**  
  - Ensure the callback URL is set and accessible in production.
- **Monitor Logs:**  
  - Regularly check WooCommerce logs for errors or issues.

---

## Sample Webhook Payload

```json
{
  "success": true,
  "message": "Payment completed",
  "data": {
    "invoiceNumber": "880419623157",
    "transactionId": "TST-10020",
    "paymentStatus": "PAID",
    "paymentReference": "IREMBO-REF-123456",
    "amount": 25000,
    "currency": "RWF"
  }
}
```

**Headers Example:**
```
irembopay-signature: t=1622471123,s=abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890
```

---

## Sample Postman Collection

**How to use:**
1. Import this JSON into Postman.
2. Set environment variables: `secret_key`, `payment_account`, `generic_product_code`, and (after invoice creation) `invoice_number`.
3. Test both endpoints and webhook.

```json
{
  "info": {
    "name": "IremboPay v2.0 Sandbox",
    "_postman_id": "faranux-irembopay-v2",
    "description": "Test IremboPay v2.0 endpoints for WooCommerce integration.",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Create Invoice",
      "request": {
        "method": "POST",
        "header": [
          { "key": "irembopay-secretkey", "value": "{{secret_key}}", "type": "text" },
          { "key": "X-API-Version", "value": "2", "type": "text" },
          { "key": "Content-Type", "value": "application/json", "type": "text" },
          { "key": "Accept", "value": "application/json", "type": "text" },
          { "key": "User-Agent", "value": "FaranuxApp", "type": "text" }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"transactionId\": \"TST-10020\",\n  \"paymentAccountIdentifier\": \"{{payment_account}}\",\n  \"paymentItems\": [\n    {\n      \"code\": \"{{generic_product_code}}\",\n      \"quantity\": 1,\n      \"unitAmount\": 25000\n    }\n  ],\n  \"description\": \"WooCommerce Order\",\n  \"expiryAt\": \"2025-06-01T00:00:00+02:00\",\n  \"language\": \"EN\",\n  \"customer\": {\n    \"email\": \"customer@faranux.com\",\n    \"phoneNumber\": \"0781234567\",\n    \"name\": \"John Doe\"\n  }\n}"
        },
        "url": {
          "raw": "https://api.sandbox.irembopay.com/payments/invoices",
          "protocol": "https",
          "host": ["api", "sandbox", "irembopay", "com"],
          "path": ["payments", "invoices"]
        }
      },
      "response": []
    },
    {
      "name": "Initiate Payment (Mobile Money)",
      "request": {
        "method": "POST",
        "header": [
          { "key": "irembopay-secretkey", "value": "{{secret_key}}", "type": "text" },
          { "key": "X-API-Version", "value": "2", "type": "text" },
          { "key": "Content-Type", "value": "application/json", "type": "text" },
          { "key": "Accept", "value": "application/json", "type": "text" },
          { "key": "User-Agent", "value": "FaranuxApp", "type": "text" }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"accountIdentifier\": \"0781234567\",\n  \"paymentProvider\": \"MTN\",\n  \"invoiceNumber\": \"{{invoice_number}}\",\n  \"transactionReference\": \"MTN_001\"\n}"
        },
        "url": {
          "raw": "https://api.sandbox.irembopay.com/payments/transactions/initiate",
          "protocol": "https",
          "host": ["api", "sandbox", "irembopay", "com"],
          "path": ["payments", "transactions", "initiate"]
        }
      },
      "response": []
    },
    {
      "name": "Test Webhook (Callback)",
      "request": {
        "method": "POST",
        "header": [
          { "key": "Content-Type", "value": "application/json", "type": "text" },
          { "key": "irembopay-signature", "value": "t=1622471123,s=abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890", "type": "text" }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"success\": true,\n  \"message\": \"Payment completed\",\n  \"data\": {\n    \"invoiceNumber\": \"{{invoice_number}}\",\n    \"transactionId\": \"TST-10020\",\n    \"paymentStatus\": \"PAID\",\n    \"paymentReference\": \"IREMBO-REF-123456\",\n    \"amount\": 25000,\n    \"currency\": \"RWF\"\n  }\n}"
        },
        "url": {
          "raw": "https://yourdomain.com/wc-api/wc_irembopay_gateway",
          "protocol": "https",
          "host": ["yourdomain", "com"],
          "path": ["wc-api", "wc_irembopay_gateway"]
        }
      },
      "response": []
    }
  ]
}
```

---

## Frequently Asked Questions

**Q: Can I use one product for all WooCommerce orders?**  
A: Yes. Register a single generic product (e.g., `WOOCOMMERCE_ORDER`) in the IremboPay dashboard and use it for all invoices.

**Q: Can the widget be opened in a new tab?**  
A: No. The IremboPay widget must be loaded inline on your checkout page. If you want a new tab, use the `paymentLinkUrl` for a full-page payment experience.

**Q: What is the callback URL?**  
A: `https://yourdomain.com/wc-api/wc_irembopay_gateway` (replace with your actual domain). Set this in the IremboPay portal for payment notifications.

**Q: Does the plugin handle errors?**  
A: Yes. All API and webhook errors are handled and logged. Customers see clear error messages if payment fails. Logs are in WooCommerce > Status > Logs > irembopay-*.log.

**Q: Is the widget part of the plugin?**  
A: No. The widget is provided by IremboPay and loaded via their JavaScript. The plugin only integrates it.

---

## Common Errors & Resolutions

See the [IremboPay API documentation](https://docs.irembopay.com/v2.0/) for a full list.  
Some common errors:

- **401 AUTHENTICATION_FAILED:** Invalid secret key. Check your plugin settings.
- **400 PRODUCT_NOT_FOUND:** The generic product code is not registered in the IremboPay dashboard.
- **400 PAYMENT_ACCOUNT_NOT_FOUND:** The payment account identifier is not registered.
- **Webhook 401:** Invalid signature. Check your secret key and signature verification logic.

---

## Support

For technical support, contact IremboPay at [irembopay@irembo.com](mailto:irembopay@irembo.com) or visit [https://irembo.com/irembopay/](https://irembo.com/irembopay/).

---

_Last updated: May 23, 2025_