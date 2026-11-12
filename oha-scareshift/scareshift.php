<?php
/**
 * Plugin Name: ScareShift - Haunter RPG System
 * Description: Create haunt characters, play daily scenarios, and master the art of fear through cooperative gameplay.
 * Version: 1.4.3-20250112
 * Author: ScareShift Team
 * Text Domain: scareshift
 */

if (!defined('ABSPATH')) exit;

define('SCARESHIFT_VERSION', '1.4.3-20250112');

// Auto-update check via GitHub releases
add_filter( 'pre_set_site_transient_update_plugins', 'scareshift_check_for_update' );
function scareshift_check_for_update( $transient ) {
    if ( empty( $transient->checked ) ) {
        return $transient;
    }
    $response = wp_remote_get(
        'https://raw.githubusercontent.com/thedrapercode/oha-plugins/main/updates/scareshift.json',
        [ 'timeout' => 10, 'sslverify' => true ]
    );
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return $transient;
    }
    $data = json_decode( wp_remote_retrieve_body( $response ) );
    if ( ! $data || empty( $data->version ) ) {
        return $transient;
    }
    if ( version_compare( $data->version, SCARESHIFT_VERSION, '>' ) ) {
        $transient->response[ plugin_basename( __FILE__ ) ] = (object) [
            'slug'        => 'oha-scareshift',
            'plugin'      => plugin_basename( __FILE__ ),
            'new_version' => $data->version,
            'package'     => $data->download_url,
            'url'         => '',
        ];
    }
    return $transient;
}
define('SCARESHIFT_URL', plugin_dir_url(__FILE__));
define('SCARESHIFT_DIR', plugin_dir_path(__FILE__));

// Load includes
require_once SCARESHIFT_DIR . 'includes/class-character-creator.php';
require_once SCARESHIFT_DIR . 'includes/class-daily-challenge.php';
require_once SCARESHIFT_DIR . 'includes/class-shift-mode.php';
require_once SCARESHIFT_DIR . 'includes/class-tutorial.php';
require_once SCARESHIFT_DIR . 'includes/class-admin.php';

class ScareShift {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_defaults();
        $this->init_components();
    }
    
    private function init_defaults() {
        $defaults = [
            'enable_charter' => 1,
            'enable_tutorial' => 1,
            'enable_daily_challenge' => 1,
            'xp_per_scenario' => 10,
            'scenarios_per_day' => 1
        ];
        
        $settings = get_option('scareshift_settings');
        if (!is_array($settings)) {
            update_option('scareshift_settings', $defaults);
        } else {
            update_option('scareshift_settings', array_merge($defaults, $settings));
        }
    }
    
    private function init_components() {
        new ScareShift_Character_Creator();
        new ScareShift_Daily_Challenge();
        new ScareShift_Shift_Mode();
        new ScareShift_Tutorial();
        new ScareShift_Admin();
    }
    
    public static function get_setting($key, $default = null) {
        $settings = get_option('scareshift_settings', []);
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
}

// Initialize plugin
add_action('plugins_loaded', function() {
    ScareShift::get_instance();
});

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create default scenarios on activation
    $scenarios = get_option('scareshift_scenarios');
    if (!is_array($scenarios) || empty($scenarios)) {
        $default_scenarios = include SCARESHIFT_DIR . 'config/default-scenarios.php';
        update_option('scareshift_scenarios', $default_scenarios);
    }
});
