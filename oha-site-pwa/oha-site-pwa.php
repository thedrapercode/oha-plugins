<?php
/**
 * Plugin Name: OHA Site PWA
 * Description: Site-wide Progressive Web App for Ohio Haunters Association — push notifications, offline support, and app install experience.
 * Version: 1.6.1
 * Author: OHA Dev
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OHA_SITE_PWA_VERSION', '1.6.1' );

// Auto-update check via GitHub releases
add_filter( 'pre_set_site_transient_update_plugins', 'oha_spwa_check_for_update' );
function oha_spwa_check_for_update( $transient ) {
    if ( empty( $transient->checked ) ) {
        return $transient;
    }
    $response = wp_remote_get(
        'https://raw.githubusercontent.com/thedrapercode/oha-plugins/main/updates/site-pwa.json',
        [ 'timeout' => 10, 'sslverify' => true ]
    );
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return $transient;
    }
    $data = json_decode( wp_remote_retrieve_body( $response ) );
    if ( ! $data || empty( $data->version ) ) {
        return $transient;
    }
    if ( version_compare( $data->version, OHA_SITE_PWA_VERSION, '>' ) ) {
        $transient->response[ plugin_basename( __FILE__ ) ] = (object) [
            'slug'        => 'oha-site-pwa',
            'plugin'      => plugin_basename( __FILE__ ),
            'new_version' => $data->version,
            'package'     => $data->download_url,
            'url'         => '',
        ];
    }
    return $transient;
}
define( 'OHA_SITE_PWA_PATH', plugin_dir_path( __FILE__ ) );
define( 'OHA_SITE_PWA_URL', plugin_dir_url( __FILE__ ) );
define( 'OHA_SITE_PWA_FILE', __FILE__ );

require_once OHA_SITE_PWA_PATH . 'includes/class-oha-site-pwa-service-worker.php';
require_once OHA_SITE_PWA_PATH . 'includes/class-oha-site-pwa-settings.php';
require_once OHA_SITE_PWA_PATH . 'includes/class-oha-site-pwa-onesignal.php';
require_once OHA_SITE_PWA_PATH . 'includes/class-oha-site-pwa-rest.php';
require_once OHA_SITE_PWA_PATH . 'includes/class-oha-site-pwa-login.php';
require_once OHA_SITE_PWA_PATH . 'includes/class-oha-site-pwa-shortcodes.php';

register_activation_hook( __FILE__, 'oha_site_pwa_activate' );
function oha_site_pwa_activate() {
    OHA_Site_PWA_Service_Worker::write_physical_files();
    update_option( 'oha_spwa_written_version', OHA_SITE_PWA_VERSION );
}

add_action( 'plugins_loaded', 'oha_site_pwa_boot', 5 );
function oha_site_pwa_boot() {
    if ( get_option( 'oha_spwa_written_version' ) !== OHA_SITE_PWA_VERSION ) {
        OHA_Site_PWA_Service_Worker::write_physical_files();
        update_option( 'oha_spwa_written_version', OHA_SITE_PWA_VERSION );
    }
    OHA_Site_PWA_Service_Worker::instance()->init();
    OHA_Site_PWA_Settings::instance()->init();
    OHA_Site_PWA_REST::instance()->init();
    OHA_Site_PWA_Login::init();
    OHA_Site_PWA_Shortcodes::instance()->init();
}
