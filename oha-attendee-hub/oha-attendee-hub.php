<?php
/**
 * Plugin Name: OHA Attendee Hub
 * Description: Convention attendee CSV import, WP account creation, year-over-year tracking, and welcome email system.
 * Version: 1.3.0
 * Author: OHA
 * License: GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OHA_ATTENDEE_HUB_VERSION', '1.3.0' );

// Auto-update check via GitHub releases
add_filter( 'pre_set_site_transient_update_plugins', 'oha_attendee_check_for_update' );
function oha_attendee_check_for_update( $transient ) {
    if ( empty( $transient->checked ) ) {
        return $transient;
    }
    $response = wp_remote_get(
        'https://raw.githubusercontent.com/thedrapercode/oha-plugins/main/updates/attendee-hub.json',
        [ 'timeout' => 10, 'sslverify' => true ]
    );
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return $transient;
    }
    $data = json_decode( wp_remote_retrieve_body( $response ) );
    if ( ! $data || empty( $data->version ) ) {
        return $transient;
    }
    if ( version_compare( $data->version, OHA_ATTENDEE_HUB_VERSION, '>' ) ) {
        $transient->response[ plugin_basename( __FILE__ ) ] = (object) [
            'slug'        => 'oha-attendee-hub',
            'plugin'      => plugin_basename( __FILE__ ),
            'new_version' => $data->version,
            'package'     => $data->download_url,
            'url'         => '',
        ];
    }
    return $transient;
}
define( 'OHA_ATTENDEE_HUB_DIR', plugin_dir_path( __FILE__ ) );
define( 'OHA_ATTENDEE_HUB_URL', plugin_dir_url( __FILE__ ) );

require_once OHA_ATTENDEE_HUB_DIR . 'includes/class-attendee-db.php';
require_once OHA_ATTENDEE_HUB_DIR . 'includes/class-attendee-importer.php';
require_once OHA_ATTENDEE_HUB_DIR . 'includes/class-attendee-mailer.php';
require_once OHA_ATTENDEE_HUB_DIR . 'includes/class-attendee-admin.php';

register_activation_hook( __FILE__, [ 'OHA_Attendee_DB', 'create_table' ] );
register_activation_hook( __FILE__, [ 'OHA_Attendee_Hub', 'register_role' ] );

class OHA_Attendee_Hub {

	public static function init() {
		new self();
	}

	public function __construct() {
		add_action( 'admin_menu',            [ $this, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_init',            [ 'OHA_Attendee_DB', 'maybe_upgrade' ] );

		// CSV upload handler (form POST)
		add_action( 'admin_post_oha_attendee_upload', [ 'OHA_Attendee_Importer', 'handle_upload' ] );

		// AJAX: import flow
		add_action( 'wp_ajax_oha_attendee_preview',       [ 'OHA_Attendee_Importer', 'ajax_preview' ] );
		add_action( 'wp_ajax_oha_attendee_import',        [ 'OHA_Attendee_Importer', 'ajax_import' ] );

		// AJAX: welcome email
		add_action( 'wp_ajax_oha_attendee_save_template', [ 'OHA_Attendee_Mailer', 'ajax_save_template' ] );
		add_action( 'wp_ajax_oha_attendee_preview_email', [ 'OHA_Attendee_Mailer', 'ajax_preview' ] );
		add_action( 'wp_ajax_oha_attendee_send_welcome',  [ 'OHA_Attendee_Mailer', 'ajax_send_batch' ] );
		add_action( 'wp_ajax_oha_attendee_unsent_count',  [ 'OHA_Attendee_Mailer', 'ajax_unsent_count' ] );
		add_action( 'wp_ajax_oha_attendee_save_gate_sales', [ 'OHA_Attendee_Admin', 'ajax_save_gate_sales' ] );
	}

	public static function register_role() {
		if ( ! get_role( 'oha_convention_attendee' ) ) {
			add_role( 'oha_convention_attendee', 'Convention Attendee', [ 'read' => true ] );
		}
	}

	public function add_menu() {
		add_menu_page(
			'OHA Attendees',
			'Attendees',
			'manage_options',
			'oha-attendee-hub',
			[ 'OHA_Attendee_Admin', 'page_list' ],
			'dashicons-id',
			25
		);
		add_submenu_page( 'oha-attendee-hub', 'All Attendees', 'All Attendees',  'manage_options', 'oha-attendee-hub',      [ 'OHA_Attendee_Admin', 'page_list' ] );
		add_submenu_page( 'oha-attendee-hub', 'Import CSV',    'Import CSV',     'manage_options', 'oha-attendee-import',   [ 'OHA_Attendee_Admin', 'page_import' ] );
		add_submenu_page( 'oha-attendee-hub', 'Welcome Email', 'Welcome Email',  'manage_options', 'oha-attendee-welcome',  [ 'OHA_Attendee_Admin', 'page_welcome' ] );
		add_submenu_page( 'oha-attendee-hub', 'Stats',         'Stats',          'manage_options', 'oha-attendee-stats',    [ 'OHA_Attendee_Admin', 'page_stats' ] );
		add_submenu_page( 'oha-attendee-hub', 'Roster',        'Roster',         'manage_options', 'oha-attendee-roster',   [ 'OHA_Attendee_Admin', 'page_roster' ] );
		add_submenu_page( 'oha-attendee-hub', 'Settings',      'Settings',       'manage_options', 'oha-attendee-settings', [ 'OHA_Attendee_Admin', 'page_settings' ] );
	}

	public function enqueue_assets( string $hook ) {
		if ( strpos( $hook, 'attendee' ) === false ) return;
		wp_enqueue_style(  'oha-attendee-admin', OHA_ATTENDEE_HUB_URL . 'assets/css/admin-style.css', [], OHA_ATTENDEE_HUB_VERSION );
		wp_enqueue_script( 'oha-attendee-admin', OHA_ATTENDEE_HUB_URL . 'assets/js/admin-script.js',  [ 'jquery' ], OHA_ATTENDEE_HUB_VERSION, true );
		wp_localize_script( 'oha-attendee-admin', 'ohaAttendee', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'oha_attendee_nonce' ),
		] );
	}
}

add_action( 'plugins_loaded', [ 'OHA_Attendee_Hub', 'init' ] );
