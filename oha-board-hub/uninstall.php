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

// Remove user meta written specifically by this plugin.
// Explicit list only — wildcard deletes risk destroying meta written by WooCommerce
// or other plugins that share the billing_/shipping_/stripe_ key prefixes.
$plugin_user_meta_keys = [
    'stripe_customer_id',
    'stripe_subscription_id',
    'billing_address_display',
    'billing_address_line1',
    'billing_address_line2',
    'billing_city',
    'billing_state',
    'billing_postal_code',
    'billing_country',
    'oha_membership_paid',
    'oha_membership_paid_date',
    'oha_paid_member_since',
    'oha_card_sent',
    'oha_card_sent_date',
    'oha_stripe_member_from_invoice',
    'oha_stripe_invoice_id',
];
foreach ($plugin_user_meta_keys as $meta_key) {
    $wpdb->delete($wpdb->usermeta, ['meta_key' => $meta_key], ['%s']);
}

// Remove scheduled events
wp_clear_scheduled_hook('oha_bhs_downgrade_event');

// Clear any cached data
wp_cache_flush();

// Log uninstall (if WP_DEBUG is enabled)
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[OHA Board Hub Suite] Plugin uninstalled and data removed');
}
