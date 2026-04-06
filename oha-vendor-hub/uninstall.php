<?php
/**
 * Uninstall Script
 *
 * Fired when the plugin is uninstalled.
 *
 * @package OHA_Board_Hub
 * @since 1.5.0
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Remove plugin data on uninstall
 *
 * IMPORTANT: This will delete ALL plugin data including settings,
 * member card data, and event logs. This cannot be undone!
 */

global $wpdb;

// Remove options
delete_option('oha_bh_suite_settings');
delete_option('oha_processed_webhook_events');

// Legacy options (if they still exist)
delete_option('oha_sm_settings');
delete_option('oha_sa_settings');
delete_option('oha_vbh_front_cap');
delete_option('oha_vbh_convention_2026_page_id');
delete_option('oha_bh_card_sent_map');
delete_option('oha_bh_card_events');

// Remove transients
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_oha_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_oha_%'");

// Remove custom tables (OPTIONAL - uncomment if you want to remove tables on uninstall)
/*
$table_status = $wpdb->prefix . 'oha_card_status';
$table_events = $wpdb->prefix . 'oha_card_events';

$wpdb->query("DROP TABLE IF EXISTS {$table_status}");
$wpdb->query("DROP TABLE IF EXISTS {$table_events}");
*/

// Remove user meta created by this plugin
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'stripe_%'");
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'billing_%'");
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'shipping_%'");

// Remove scheduled events
wp_clear_scheduled_hook('oha_bhs_downgrade_event');

// Clear any cached data
wp_cache_flush();

// Log uninstall (if WP_DEBUG is enabled)
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[OHA Board Hub Suite] Plugin uninstalled and data removed');
}
