<?php
/**
 * Runs when PayNexus is uninstalled (deleted) from WordPress.
 *
 * Removes the plugin's options and database table.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'paynexus_settings' );

// Drop payments table.
global $wpdb;
$paynexus_table = $wpdb->prefix . 'paynexus_payments';
$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS `%s`", $paynexus_table ) );
