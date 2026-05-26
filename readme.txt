=== PayNexus Payment Gateway ===
Contributors: paynexus, mcbankske
Tags: payment, mpesa, mobile money, woocommerce, kenya, payment gateway
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Accept M-Pesa payments through PayNexus in any WordPress or WooCommerce site.

== Description ==

PayNexus Payment Gateway lets you accept M-Pesa mobile money payments on your WordPress website. Money flows directly from the customer to your own M-Pesa payment account — PayNexus never holds or delays your funds.

**Features:**

* M-Pesa STK Push — Customers receive a payment prompt on their phone
* WooCommerce integration — Full checkout gateway for WooCommerce stores
* Shortcode support — Embed payment forms on any page with `[paynexus_payment_form]`
* Real-time polling — Automatic status updates without page refresh
* Webhook support — Receive instant payment notifications via REST API
* Admin dashboard — View and filter all payments from WordPress admin
* Secure — HMAC-SHA256 webhook signature verification with replay protection
* Local records — Every payment is stored locally for your records

**How It Works:**

1. Customer enters their phone number and amount
2. PayNexus sends an M-Pesa STK Push to the customer's phone
3. Customer enters their M-Pesa PIN to confirm payment
4. Payment status is updated automatically via webhook
5. WooCommerce orders are marked as paid automatically

== Installation ==

1. Download the plugin ZIP file
2. Go to **Plugins → Add New → Upload Plugin** in WordPress admin
3. Upload the ZIP file and click **Install Now**
4. Activate the plugin
5. Go to **PayNexus → Settings** to enter your API keys
6. For WooCommerce: enable the gateway at **WooCommerce → Settings → Payments → M-Pesa (PayNexus)**

**Getting API Keys:**

1. Sign up at [paynexus.co.ke](https://paynexus.co.ke)
2. Go to Merchant Dashboard → API Keys
3. Copy your Secret Key (sk_...) and optionally the Public Key (pk_...)

== Frequently Asked Questions ==

= Does PayNexus hold my funds? =

No. PayNexus is a payment orchestrator. Money flows directly from the customer to your own M-Pesa payment account.

= Do I need WooCommerce? =

No. You can use the `[paynexus_payment_form]` shortcode on any page without WooCommerce. WooCommerce integration is optional.

= What phone number format should customers use? =

Kenyan phone numbers in the format 254712345678 (with country code, no +).

= How do I receive webhook notifications? =

Set your webhook URL to `https://yoursite.com/wp-json/paynexus/v1/webhook` in the PayNexus dashboard.

== Changelog ==

= 1.0.0 =
* Initial release
* M-Pesa STK Push payment initiation
* WooCommerce payment gateway
* Payment form shortcode
* Payment status shortcode
* Admin settings page
* Admin payments list
* Webhook handler with HMAC verification
* Real-time JavaScript status polling
