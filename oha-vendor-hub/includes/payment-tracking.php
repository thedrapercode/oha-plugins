<?php
/**
 * Database-based payment tracking
 * 
 * Stores pending payments in database temporarily
 * More reliable than sessions/cookies which fail when headers are sent
 */

if (!defined('ABSPATH')) exit;

/**
 * Create tracking table on activation
 */
function oha_vhcpt_create_tracking_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'oha_payment_tracking';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        token varchar(64) NOT NULL,
        app_id bigint(20) NOT NULL,
        email varchar(255) NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY token (token),
        KEY app_id (app_id),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Create table on plugin activation
register_activation_hook(OHA_VHCPT_PATH . 'oha-vendor-hub.php', 'oha_vhcpt_create_tracking_table');

/**
 * Store app_id with unique token before redirecting to Stripe
 */
function oha_vhcpt_store_pending_payment($app_id, $email) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'oha_payment_tracking';
    
    // Generate unique token
    $token = wp_generate_password(32, false);
    
    // Store in database
    $wpdb->insert(
        $table_name,
        [
            'token' => $token,
            'app_id' => $app_id,
            'email' => $email,
            'created_at' => current_time('mysql')
        ],
        ['%s', '%d', '%s', '%s']
    );
    
    // Clean up old entries (over 2 hours old)
    $wpdb->query($wpdb->prepare(
        "DELETE FROM $table_name WHERE created_at < %s",
        date('Y-m-d H:i:s', strtotime('-2 hours'))
    ));
    
    return $token;
}

/**
 * Retrieve app_id from token
 */
function oha_vhcpt_get_app_from_token($token) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'oha_payment_tracking';
    
    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT app_id, email FROM $table_name WHERE token = %s AND created_at > %s",
        $token,
        date('Y-m-d H:i:s', strtotime('-2 hours'))
    ));
    
    if ($result) {
        // Delete the token (one-time use)
        $wpdb->delete($table_name, ['token' => $token], ['%s']);
        
        return [
            'app_id' => $result->app_id,
            'email' => $result->email
        ];
    }
    
    return false;
}

/**
 * Try to find app_id by email (fallback if token missing)
 */
function oha_vhcpt_find_recent_app_by_email($email) {
    // 1) Most recent application for this email in last 24 hours (typical flow)
    $apps = get_posts([
        'post_type'      => 'vendor_application',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'date_query'     => [
            [
                'after' => '24 hours ago',
            ]
        ],
        'meta_query'     => [
            [
                'key'     => 'email',
                'value'   => $email,
                'compare' => '=',
            ]
        ],
    ]);

    if (!empty($apps)) {
        return $apps[0]->ID;
    }

    // 2) Fallback: any most-recent application for this email (no time restriction)
    $apps = get_posts([
        'post_type'      => 'vendor_application',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [
            [
                'key'     => 'email',
                'value'   => $email,
                'compare' => '=',
            ]
        ],
    ]);

    if (!empty($apps)) {
        return $apps[0]->ID;
    }

    return false;
}
