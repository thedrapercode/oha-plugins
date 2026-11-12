<?php
/**
 * Plugin Name: OHA Site PWA
 * Description: Site-wide Progressive Web App for Ohio Haunters Association — push notifications, offline support, and app install experience.
 * Version: 1.6.0
 * Author: OHA Dev
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OHA_SITE_PWA_VERSION', '1.6.0' );
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
