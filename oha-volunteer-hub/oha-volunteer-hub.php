<?php
/**
 * Plugin Name: OHA Volunteer Hub
 * Description: Complete volunteer management for OHA Convention. Tabbed board hub, shift management, area management, CSV export, and year-scoped volunteer tracking.
 * Version: 4.15.1
 * Author: OHA
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: oha-volunteer-hub
 */

if (!defined('ABSPATH')) { exit; }

define('OHA_VH_VERSION', '4.15.1');

// Auto-update check via GitHub releases
add_filter( 'pre_set_site_transient_update_plugins', 'oha_vh_check_for_update' );
function oha_vh_check_for_update( $transient ) {
    if ( empty( $transient->checked ) ) {
        return $transient;
    }
    $response = wp_remote_get(
        'https://raw.githubusercontent.com/thedrapercode/oha-plugins/main/updates/volunteer-hub.json',
        [ 'timeout' => 10, 'sslverify' => true ]
    );
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return $transient;
    }
    $data = json_decode( wp_remote_retrieve_body( $response ) );
    if ( ! $data || empty( $data->version ) ) {
        return $transient;
    }
    if ( version_compare( $data->version, OHA_VH_VERSION, '>' ) ) {
        $transient->response[ plugin_basename( __FILE__ ) ] = (object) [
            'slug'        => 'oha-volunteer-hub',
            'plugin'      => plugin_basename( __FILE__ ),
            'new_version' => $data->version,
            'package'     => $data->download_url,
            'url'         => '',
        ];
    }
    return $transient;
}
define('OHA_VH_PATH', plugin_dir_path(__FILE__));
define('OHA_VH_URL', plugin_dir_url(__FILE__));

// Load core classes
require_once OHA_VH_PATH . 'includes/class-oha-vh-core.php';
require_once OHA_VH_PATH . 'includes/class-oha-vh-database.php';
require_once OHA_VH_PATH . 'includes/class-oha-vh-settings.php';
require_once OHA_VH_PATH . 'includes/class-oha-vh-admin.php';
require_once OHA_VH_PATH . 'includes/class-oha-vh-shortcodes.php';
require_once OHA_VH_PATH . 'includes/class-oha-vh-ajax.php';
require_once OHA_VH_PATH . 'includes/class-oha-vh-emails.php';
require_once OHA_VH_PATH . 'includes/class-oha-vh-signup-form.php';
require_once OHA_VH_PATH . 'includes/class-oha-vh-assignments.php';
require_once OHA_VH_PATH . 'includes/class-oha-vh-portal.php';
require_once OHA_VH_PATH . 'includes/class-oha-vh-area-manager.php';
require_once OHA_VH_PATH . 'includes/class-oha-vh-board.php'; // Phase 4

/**
 * Activation hook - Create database tables and setup
 */
register_activation_hook(__FILE__, function(){
    // Create/update database tables
    \OHA\VH\Database::install();
    
    // No longer using task CPT system - Phase 3 uses dedicated tables
    \OHA\VH\Core::register_caps();
    
    flush_rewrite_rules();
});

/**
 * Deactivation hook - Cleanup
 */
register_deactivation_hook(__FILE__, function(){
    flush_rewrite_rules();
});

/**
 * Initialize after plugins loaded
 */
add_action('plugins_loaded', function () {
    // Check for database upgrades
    \OHA\VH\Database::install();

    // v4.8.1: Auto-reset bad confirmation email templates in the database:
    // - Old "Welcome aboard!" template that incorrectly confirmed volunteers
    // - Templates containing raw HTML tags (data-start/data-end attributes from pasted content)
    $stored_body = \OHA\VH\Settings::get('confirmation_body');
    if (
        empty($stored_body) ||
        strpos($stored_body, "You're officially part of the") !== false ||
        strpos($stored_body, 'data-start=') !== false ||
        strpos($stored_body, '<p ') !== false ||
        strpos($stored_body, '<strong') !== false
    ) {
        \OHA\VH\Settings::update('confirmation_body', \OHA\VH\Settings::get_default_confirmation_email());
        \OHA\VH\Settings::update('confirmation_subject', 'Volunteer Application Received - {convention_name}');
    }

    // Core capabilities and assets
    \OHA\VH\Core::register_caps();
    \OHA\VH\Core::init_assets();
    
    // Initialize all components
    \OHA\VH\Settings::init();
    \OHA\VH\Admin::init();
    \OHA\VH\Shortcodes::init();
    \OHA\VH\Ajax::init();
    \OHA\VH\Emails::init();
    \OHA\VH\SignupForm::init();
    \OHA\VH\Assignments::init();
    \OHA\VH\Portal::init();
    \OHA\VH\AreaManager::init();
    \OHA\VH\Board::init(); // Phase 4 - Board hub actions
});

/**
 * Add settings link on plugins page
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=oha-volunteer-settings') . '">Settings</a>';
    $dashboard_link = '<a href="' . admin_url('admin.php?page=oha-volunteer-hub') . '"><strong>Dashboard</strong></a>';
    $assign_link = '<a href="' . admin_url('admin.php?page=oha-volunteer-assign') . '" style="color: #28a745; font-weight: 600;">Assign Volunteers</a>';
    array_unshift($links, $settings_link, $dashboard_link, $assign_link);
    return $links;
});
