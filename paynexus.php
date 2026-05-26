<?php
/**
 * Plugin Name:       PayNexus Payment Gateway
 * Plugin URI:        https://github.com/MCBANKSKE/paynexus
 * Description:       Accept M-Pesa payments through PayNexus in any WordPress or WooCommerce site. Supports STK Push, real-time status tracking, webhooks, and local payment records.
 * Version:           1.0.0
 * Author:            PayNexus
 * Author URI:        https://paynexus.co.ke
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       paynexus
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 * WC tested up to:   9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PAYNEXUS_VERSION', '1.0.0' );
define( 'PAYNEXUS_PLUGIN_FILE', __FILE__ );
define( 'PAYNEXUS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PAYNEXUS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PAYNEXUS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once PAYNEXUS_PLUGIN_DIR . 'includes/class-paynexus.php';

/**
 * Return the main plugin instance.
 */
function paynexus() {
    return PayNexus::instance();
}

register_activation_hook( __FILE__, array( 'PayNexus', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PayNexus', 'deactivate' ) );

paynexus();
