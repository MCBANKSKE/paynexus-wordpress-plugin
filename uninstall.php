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
$table = $wpdb->prefix . 'paynexus_payments';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
