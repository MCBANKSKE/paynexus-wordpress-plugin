<div align="center">

# PayNexus WordPress Plugin

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759B?style=flat-square&logo=wordpress)](https://wordpress.org)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-6.0%2B-96588A?style=flat-square&logo=woocommerce)](https://woocommerce.com)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat-square&logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-blue?style=flat-square)](LICENSE)

**Accept M-Pesa payments through PayNexus in any WordPress or WooCommerce site**

A powerful plugin that connects your WordPress site to the PayNexus payment platform, handling M-Pesa STK Push, real-time payment status tracking, webhook processing, and local payment records.

[Installation](#installation) • [Quick Start](#quick-start) • [WooCommerce](#woocommerce-integration) • [Shortcodes](#shortcodes) • [API Reference](#api-reference) • [Webhooks](#webhook-integration)

</div>

---

## Features

- **M-Pesa STK Push** — Seamless mobile payment initiation
- **WooCommerce Gateway** — Full checkout integration for WooCommerce stores
- **Shortcode Support** — Embed payment forms on any page or post
- **Real-time Polling** — Automatic JavaScript-based status updates
- **Webhook Handler** — REST API endpoint with HMAC-SHA256 verification
- **Admin Dashboard** — Settings page + payments list in WordPress admin
- **Local Records** — Every payment stored in a dedicated database table
- **Phone Validation** — Built-in Kenyan phone number normalisation
- **HPOS Compatible** — Works with WooCommerce High-Performance Order Storage

---

## Plugin vs Platform

> **This plugin is for WordPress websites** that want to accept payments through the PayNexus platform.

| Component | Description |
|-----------|-------------|
| **PayNexus Platform** | The payment gateway at [paynexus.co.ke](https://paynexus.co.ke) that processes payments |
| **This Plugin** | A WordPress plugin your site installs to connect to the PayNexus platform |

---

## How It Works

```
┌──────────────────┐     ┌──────────────────┐     ┌──────────────────────┐
│  Your WordPress  │────▶│   PayNexus API   │────▶│      M-Pesa          │
│     Site         │     │  (paynexus.co.ke)│     │   (Daraja API)       │
└──────────────────┘     └──────┬───────────┘     └──────────┬───────────┘
                                │                            │
                                │  ◀── Callback ────────────▶│
                                │                            │
                                ▼                            ▼
                         Payment status              Customer pays via
                         updated & webhook           STK Push on phone
                         sent to your site
```

**Payment Flow:**

1. **Initiate** — Customer submits the payment form (or checks out via WooCommerce). The plugin calls the PayNexus API.
2. **STK Push** — PayNexus sends an M-Pesa STK Push to the customer's phone.
3. **Customer pays** — The customer enters their M-Pesa PIN.
4. **Callback** — M-Pesa confirms the payment to PayNexus.
5. **Webhook** — PayNexus sends a webhook to your site's REST API endpoint.
6. **Updated** — The plugin updates the local payment record, fires WordPress hooks, and (for WooCommerce) marks the order as paid.

---

## Requirements

- WordPress 5.8+
- PHP 7.4+
- A PayNexus merchant account ([sign up here](https://paynexus.co.ke))
- WooCommerce 6.0+ (optional, only for WooCommerce gateway)

---

## Installation

### Method 1: Upload ZIP (Recommended)

1. Download the plugin as a ZIP file from [GitHub Releases](https://github.com/MCBANKSKE/paynexus/releases)
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP file and click **Install Now**
4. Click **Activate Plugin**

### Method 2: Manual Upload

1. Download or clone this repository
2. Copy the `paynexus-wordpress-plugin` folder into `wp-content/plugins/`
3. Rename the folder to `paynexus` (optional but cleaner)
4. Go to **Plugins** in WordPress admin and activate **PayNexus Payment Gateway**

### Method 3: From Source (Development)

```bash
cd wp-content/plugins/
git clone https://github.com/MCBANKSKE/paynexus.git
# The plugin is in the paynexus-wordpress-plugin/ subdirectory
# Create a symlink or copy:
ln -s paynexus/paynexus-wordpress-plugin paynexus
```

### Post-Installation

The plugin automatically:
- Creates the `wp_paynexus_payments` database table on activation
- Sets default configuration values
- Registers the webhook REST API endpoint

---

## Configuration

### Step 1: Get Your API Keys

1. Sign up or log in at [paynexus.co.ke](https://paynexus.co.ke)
2. Go to **Merchant Dashboard → API Keys** ([direct link](https://paynexus.co.ke/merchant/merchant-api-keys))
3. Copy your **Secret Key** (`sk_...`) — required for all operations
4. Optionally copy your **Public Key** (`pk_...`) — used for read-only operations

### Step 2: Configure the Plugin

1. In WordPress admin, go to **PayNexus → Settings**
2. Enter your **Secret Key**
3. Optionally enter your **Public Key**
4. The **Base URL** defaults to `https://paynexus.co.ke` — only change for staging/self-hosted
5. Click **Save Changes**

The **Connection Test** section at the bottom confirms your API key is valid and shows your merchant information.

### Step 3: Set Up Webhooks

1. Copy the webhook URL displayed on the settings page:
   ```
   https://yoursite.com/wp-json/paynexus/v1/webhook
   ```
2. In the [PayNexus dashboard](https://paynexus.co.ke), register this URL as a webhook endpoint
3. Copy the **Webhook Secret** from PayNexus and paste it into the plugin settings
4. Save — webhooks are now secured with HMAC-SHA256 signature verification

### Settings Reference

| Setting | Description | Default |
|---------|-------------|---------|
| **Secret Key** | Your PayNexus secret API key (`sk_...`) | — |
| **Public Key** | Your PayNexus public API key (`pk_...`) | — |
| **Base URL** | PayNexus API base URL | `https://paynexus.co.ke` |
| **Webhook Secret** | HMAC secret for webhook verification | — |
| **Currency** | Default currency code | `KES` |
| **Poll Interval** | Seconds between status polls | `3` |
| **Poll Timeout** | Max seconds to poll before timeout | `120` |
| **HTTP Timeout** | API request timeout in seconds | `30` |
| **HTTP Retries** | Number of retry attempts for failed requests | `2` |

---

## Quick Start

### Standalone Payment Form (No WooCommerce)

Add this shortcode to any page or post:

```
[paynexus_payment_form amount="1000" description="Donation" reference="DONATE"]
```

That's it! Visitors see a form with phone number input, click "Pay with M-Pesa", and receive an STK Push on their phone.

### WooCommerce Checkout

1. Go to **WooCommerce → Settings → Payments**
2. Enable **M-Pesa (PayNexus)**
3. Click **Manage** to customise the title, description, and instructions
4. Customers can now select M-Pesa at checkout

---

## Shortcodes

### `[paynexus_payment_form]`

Renders a payment form with phone number input and amount field.

**Attributes:**

| Attribute | Description | Default |
|-----------|-------------|---------|
| `amount` | Fixed payment amount. If empty, user enters the amount | — |
| `description` | Payment description sent to M-Pesa | `Payment via PayNexus` |
| `reference` | Account reference (max 12 chars) | `PAYNEXUS` |
| `button_text` | Submit button label | `Pay with M-Pesa` |
| `class` | Additional CSS class for the wrapper | — |
| `show_amount_field` | Show amount field even with fixed amount (`yes`/`no`) | `no` |

**Examples:**

```html
<!-- Fixed amount donation -->
[paynexus_payment_form amount="500" description="Donation to Charity" reference="CHARITY"]

<!-- User-entered amount -->
[paynexus_payment_form description="Custom payment" button_text="Send Payment"]

<!-- Fixed amount but show the field (read-only) -->
[paynexus_payment_form amount="2500" show_amount_field="yes" reference="MEMBERSHIP"]
```

### `[paynexus_payment_status]`

Renders a payment status checker where users can enter a reference to look up their payment.

```html
[paynexus_payment_status]
```

---

## WooCommerce Integration

### Gateway Settings

Go to **WooCommerce → Settings → Payments → M-Pesa (PayNexus)** to configure:

| Setting | Description | Default |
|---------|-------------|---------|
| **Enable/Disable** | Toggle the gateway on/off | Disabled |
| **Title** | Name shown at checkout | `M-Pesa (PayNexus)` |
| **Description** | Description shown at checkout | `Pay securely with M-Pesa...` |
| **Instructions** | Thank-you page text | `An M-Pesa payment prompt...` |
| **Payment Account ID** | Override auto-resolved account | Auto |

### Checkout Flow

1. Customer selects "M-Pesa (PayNexus)" at checkout
2. Customer enters their M-Pesa phone number (254712345678)
3. Customer clicks "Place Order"
4. An STK Push is sent to their phone immediately
5. The thank-you page shows a spinner while polling for confirmation
6. When M-Pesa confirms, the order is automatically marked as **Processing** (paid)

### Phone Number Validation

The plugin validates phone numbers at checkout:
- Accepts formats: `254712345678`, `+254712345678`, `0712345678`
- Automatically normalises to `254...` format before sending to PayNexus

### Order Meta

The plugin stores the following meta on WooCommerce orders:

| Meta Key | Description |
|----------|-------------|
| `_paynexus_checkout_request_id` | M-Pesa checkout request ID |
| `_paynexus_reference` | PayNexus payment reference |
| `_paynexus_phone` | Customer's phone number |

### HPOS Compatibility

The plugin declares compatibility with WooCommerce High-Performance Order Storage (HPOS / Custom Order Tables).

---

## Webhook Integration

### Endpoint

```
POST https://yoursite.com/wp-json/paynexus/v1/webhook
```

### Security

Webhooks are verified using HMAC-SHA256:

1. PayNexus signs the JSON body with your webhook secret
2. The signature is sent in the `X-PayNexus-Signature` header
3. The plugin computes the expected signature and compares securely
4. Replay protection: the `X-PayNexus-Timestamp` header is checked against a 5-minute window

> **Development convenience:** If no webhook secret is configured, signature verification is skipped. Always set a webhook secret in production.

### Webhook Payload

PayNexus sends the following payload:

```json
{
  "event": "payment.completed",
  "timestamp": "2026-05-22T10:00:00.000000Z",
  "data": {
    "payment_id": 42,
    "merchant_id": 1,
    "reference": "PNX-abc123",
    "amount": "1000.00",
    "currency": "KES",
    "phone": "254712345678",
    "status": "completed",
    "account_reference": "ORDER-123",
    "checkout_request_id": "ws_CO_123456",
    "transaction_id": "SH1234ABCDE",
    "provider_transaction_id": "SH1234ABCDE",
    "provider_reference": "...",
    "payer_name": "JOHN DOE",
    "created_at": "...",
    "updated_at": "..."
  }
}
```

### Supported Events

| Event | Description |
|-------|-------------|
| `payment.completed` | Payment was successful |
| `payment.failed` | Payment failed |

---

## WordPress Hooks

The plugin fires WordPress actions at key points. Use these in your theme's `functions.php` or a custom plugin.

### `paynexus_payment_initiated`

Fired after a payment is successfully initiated.

```php
add_action( 'paynexus_payment_initiated', function( $payment_data, $request_data ) {
    // $payment_data — response from PayNexus API
    // $request_data — original request parameters
    error_log( 'Payment initiated: ' . $payment_data['reference'] );
}, 10, 2 );
```

### `paynexus_payment_completed`

Fired when a webhook confirms a payment is completed.

```php
add_action( 'paynexus_payment_completed', function( $payment, $webhook_data ) {
    // $payment     — local payment record (object)
    // $webhook_data — raw webhook data array
    
    // Send a custom email
    wp_mail(
        'admin@example.com',
        'Payment Received!',
        sprintf( 'Payment %s for %s %s completed.', $payment->reference, $payment->currency, $payment->amount )
    );
}, 10, 2 );
```

### `paynexus_payment_failed`

Fired when a webhook confirms a payment has failed.

```php
add_action( 'paynexus_payment_failed', function( $payment, $webhook_data, $reason ) {
    error_log( sprintf( 'Payment %s failed: %s', $payment->reference, $reason ) );
}, 10, 3 );
```

---

## API Reference (PHP)

You can call PayNexus API methods directly from your theme or plugin code.

### Getting the Client

```php
// The client is available after the 'init' hook.
$client = paynexus()->client;
```

### Merchant

```php
// Get merchant info
$merchant = paynexus()->client->get_merchant();

// Get businesses
$businesses = paynexus()->client->get_businesses();

// Get payment accounts
$accounts = paynexus()->client->get_payment_accounts();
```

### Initiate Payment

```php
$result = paynexus()->client->initiate_payment([
    'amount'            => 1500,
    'phone'             => '254712345678',
    'account_reference' => 'ORDER-123',
    'description'       => 'Payment for Order #123',
]);

if ( $result['success'] ) {
    $checkout_request_id = $result['data']['checkout_request_id'];
    $reference           = $result['data']['reference'];
}
```

### Initiate M-Pesa Payment (alternate endpoint)

```php
$result = paynexus()->client->initiate_mpesa_payment([
    'amount' => 1000,
    'phone'  => '254712345678',
    'remark' => 'Monthly subscription',
]);
```

### Check Payment Status

```php
// By reference
$status = paynexus()->client->get_payment_by_reference( 'PNX-abc123' );

// By payment ID
$status = paynexus()->client->get_payment_by_id( 42 );

// By checkout request ID
$status = paynexus()->client->get_payment_by_checkout_id( 'ws_CO_123456' );

// Real-time M-Pesa status
$status = paynexus()->client->check_mpesa_status( 'ws_CO_123456' );
```

### Poll for Completion

```php
// Blocks until completed, failed, or timeout
$result = paynexus()->client->poll_status( 'ws_CO_123456' );
// Custom interval/timeout
$result = paynexus()->client->poll_status( 'ws_CO_123456', 5, 60 );
```

### List Payments

```php
$payments = paynexus()->client->list_payments([
    'status'   => 'completed',
    'per_page' => 10,
    'page'     => 1,
]);
```

### Phone Validation

```php
$result = paynexus()->client->validate_phone( '0712345678' );
// Returns normalised phone number and validation status
```

### Webhook Management

```php
// Register a webhook
paynexus()->client->register_webhook(
    'My Site Webhook',
    'https://mysite.com/wp-json/paynexus/v1/webhook',
    ['payment.completed', 'payment.failed']
);

// List webhooks
$webhooks = paynexus()->client->list_webhooks();

// Update a webhook
paynexus()->client->update_webhook( 1, ['name' => 'Updated Name'] );

// Delete a webhook
paynexus()->client->delete_webhook( 1 );
```

---

## Local Payment Records

The plugin creates a `wp_paynexus_payments` table to store all payment records locally.

### Database Schema

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Auto-increment primary key |
| `paynexus_payment_id` | bigint | Payment ID from PayNexus platform |
| `reference` | varchar | PayNexus reference (PNX-...) |
| `checkout_request_id` | varchar | M-Pesa checkout request ID |
| `merchant_request_id` | varchar | M-Pesa merchant request ID |
| `transaction_id` | varchar | M-Pesa transaction ID |
| `amount` | decimal(14,2) | Payment amount |
| `currency` | varchar(10) | Currency code (default: KES) |
| `phone` | varchar(20) | Customer phone number |
| `description` | varchar | Payment description |
| `account_reference` | varchar(50) | Account reference |
| `status` | varchar(20) | pending / completed / failed / timeout |
| `provider_reference` | varchar | Provider reference string |
| `failure_reason` | text | Reason for failure (if failed) |
| `payer_name` | varchar | Name from M-Pesa callback |
| `order_id` | bigint | Linked WooCommerce order ID |
| `metadata` | longtext (JSON) | Extra metadata |
| `idempotency_key` | varchar (unique) | Idempotency key |
| `created_at` | datetime | Record creation time |
| `updated_at` | datetime | Last update time |

### Querying Payments

```php
// Find by reference
$payment = PayNexus_Payment::find_by( 'reference', 'PNX-abc123' );

// Find by order ID
$payments = PayNexus_Payment::find_by_order( $order_id );

// List with filters
$result = PayNexus_Payment::list_payments([
    'status'   => 'completed',
    'per_page' => 10,
    'page'     => 1,
]);
// $result['items'] — array of payment objects
// $result['total'] — total matching count
```

---

## Admin Dashboard

### Settings Page

**PayNexus → Settings** provides:
- API key configuration
- Webhook URL display and secret configuration
- General settings (currency, polling, HTTP)
- Connection test that verifies your API key and shows merchant info

### Payments Page

**PayNexus → Payments** shows:
- All local payment records in a familiar WordPress table
- Filter by status (Pending, Completed, Failed, Timeout)
- Pagination for large datasets
- Key details: reference, amount, phone, status, transaction ID, payer name, date

---

## Customisation

### Styling

The plugin includes minimal CSS that can be overridden in your theme:

```css
/* Custom button colour */
.paynexus-btn {
    background: #FF6B00;
}
.paynexus-btn:hover {
    background: #E55F00;
}

/* Custom form width */
.paynexus-payment-form-wrap {
    max-width: 500px;
}
```

### Custom Payment Form (Template Override)

For full control, build your own form and use the AJAX endpoints:

```html
<form id="my-payment-form">
    <input type="number" name="amount" required />
    <input type="tel" name="phone" placeholder="254712345678" required />
    <button type="submit">Pay Now</button>
</form>

<script>
jQuery('#my-payment-form').on('submit', function(e) {
    e.preventDefault();
    jQuery.post(paynexus_params.ajax_url, {
        action: 'paynexus_initiate_payment',
        nonce:  paynexus_params.nonce,
        amount: jQuery('[name="amount"]').val(),
        phone:  jQuery('[name="phone"]').val(),
    }, function(res) {
        if (res.success) {
            // Start polling for status...
            console.log('Checkout ID:', res.data.checkout_request_id);
        }
    });
});
</script>
```

### AJAX Endpoints

| Endpoint | Action | Parameters |
|----------|--------|------------|
| Initiate Payment | `paynexus_initiate_payment` | `nonce`, `phone`, `amount`, `account_reference`, `description` |
| Check Status | `paynexus_check_status` | `nonce`, `checkout_request_id` or `reference` |

Both endpoints are available for logged-in and guest users.

---

## Error Handling

The plugin handles errors gracefully:

- **Network errors** — Retried automatically (configurable retry count)
- **Auth errors (401)** — Logged and returned with a clear message
- **API errors** — Returned with the error message from PayNexus
- **Invalid webhooks** — Rejected with 403 status
- **Expired webhooks** — Rejected if timestamp exceeds 5-minute window

All errors are logged to the WordPress debug log when `WP_DEBUG` is enabled:

```php
// In wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

Check `wp-content/debug.log` for `[PayNexus]` entries.

---

## Troubleshooting

### "Unable to reach PayNexus API"

- Check that the **Base URL** is correct (`https://paynexus.co.ke`)
- Verify your server can make outbound HTTPS requests
- Check if a firewall or security plugin is blocking the connection

### "Authentication failed"

- Verify your **Secret Key** is correct and starts with `sk_`
- Check that the key has not been revoked in the PayNexus dashboard
- Ensure there are no extra spaces in the key

### Webhook not received

- Confirm the webhook URL is registered in the PayNexus dashboard
- Check that your site's REST API is accessible: visit `https://yoursite.com/wp-json/paynexus/v1/webhook` — it should return a method-not-allowed error (GET not supported)
- Ensure no security plugin is blocking REST API access
- Check `wp-content/debug.log` for webhook-related errors

### WooCommerce gateway not showing

- Ensure WooCommerce is installed and active
- Go to **WooCommerce → Settings → Payments** and enable "M-Pesa (PayNexus)"
- Clear any caching plugins

### Payment stuck on "pending"

- Check the PayNexus dashboard for the actual payment status
- Verify webhooks are being received (check debug log)
- Try checking the status manually via the `[paynexus_payment_status]` shortcode

---

## Security

- **API keys** are stored in the WordPress options table (encrypted at rest if your database is encrypted)
- **Webhook signatures** use HMAC-SHA256 with constant-time comparison (`hash_equals`)
- **Replay protection** rejects webhooks older than 5 minutes
- **CSRF protection** via WordPress nonces on all AJAX endpoints
- **Input sanitisation** on all user inputs using WordPress sanitisation functions
- **SQL injection prevention** using `$wpdb->prepare()` for all database queries

---

## Uninstallation

When the plugin is **deleted** (not just deactivated):
- The `paynexus_settings` option is removed
- The `wp_paynexus_payments` table is dropped

Deactivating the plugin does NOT remove any data.

---

## Support

- **Documentation:** This README
- **Issues:** [GitHub Issues](https://github.com/MCBANKSKE/paynexus/issues)
- **Email:** support@paynexus.co.ke
- **Website:** [paynexus.co.ke](https://paynexus.co.ke)

---

## License

[MIT License](LICENSE)
