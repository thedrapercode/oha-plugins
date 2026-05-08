<?php
/**
 * Plugin Name: OHA Modern Map
 * Plugin URI: https://ohiohaunters.com
 * Description: Premium haunt map plugin for Ohio haunted attractions. MapLibre GL JS WebGL rendering, GeoDirectory + Events integration, region fills, convention mode indoor floor plan, mobile-first design. Requires GeoDirectory.
 * Version: 2.12.3
 * Author: Ohio Haunters Association
 * Author URI: https://ohiohaunters.com
 * Text Domain: oha-modern-map
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: geodirectory
 */

if (!defined('ABSPATH')) exit;

// Plugin Constants
define('OHA_MODERN_MAP_VERSION', '2.12.3');
define('OHA_MODERN_MAP_FILE', __FILE__);
define('OHA_MODERN_MAP_PATH', plugin_dir_path(__FILE__));
define('OHA_MODERN_MAP_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class
 */
class OHA_Modern_Map {
    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        require_once OHA_MODERN_MAP_PATH . 'includes/class-oha-map-regions.php';
        require_once OHA_MODERN_MAP_PATH . 'includes/class-oha-map-data.php';
        require_once OHA_MODERN_MAP_PATH . 'includes/class-oha-map-shortcode.php';
        require_once OHA_MODERN_MAP_PATH . 'includes/class-oha-map-admin.php';
        require_once OHA_MODERN_MAP_PATH . 'includes/class-oha-convention-data.php';
        require_once OHA_MODERN_MAP_PATH . 'includes/class-oha-convention-shortcode.php';
    }

    private function init_hooks() {
        add_action('init', [$this, 'init']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Convention AJAX — public (front end map)
        add_action('wp_ajax_oha_get_convention_booths',        ['OHA_Convention_Data', 'ajax_get_booths']);
        add_action('wp_ajax_nopriv_oha_get_convention_booths', ['OHA_Convention_Data', 'ajax_get_booths']);

        // Convention AJAX — admin only (placement tool)
        add_action('wp_ajax_oha_get_vendor_hub_list',    ['OHA_Convention_Data', 'ajax_get_vendor_hub_list']);
        add_action('wp_ajax_oha_save_booth_placement',   ['OHA_Convention_Data', 'ajax_save_booth_placement']);
        add_action('wp_ajax_oha_delete_booth_placement', ['OHA_Convention_Data', 'ajax_delete_booth_placement']);
        add_action('wp_ajax_oha_clone_year_layout',      ['OHA_Convention_Data', 'ajax_clone_year_layout']);

        // Haunt map AJAX
        add_action('wp_ajax_oha_get_map_events',        ['OHA_Map_Data', 'ajax_get_events']);
        add_action('wp_ajax_nopriv_oha_get_map_events', ['OHA_Map_Data', 'ajax_get_events']);
    }

    public function init() {
        OHA_Map_Shortcode::init();
        OHA_Convention_Shortcode::init();
        OHA_Convention_Data::register();
    }

    public function add_admin_menu() {
        OHA_Map_Admin::register_menu();
    }

    public function enqueue_frontend_assets() {
        if (!is_admin()) {
            // MapLibre GL JS v4.7.1 — bundled locally; no CDN dependency at venue
            wp_enqueue_style(
                'maplibre-gl',
                OHA_MODERN_MAP_URL . 'assets/vendor/maplibre/maplibre-gl.css',
                [],
                '4.7.1'
            );
            wp_enqueue_script(
                'maplibre-gl',
                OHA_MODERN_MAP_URL . 'assets/vendor/maplibre/maplibre-gl.js',
                [],
                '4.7.1',
                true
            );

            // Plugin CSS — haunt map
            wp_enqueue_style(
                'oha-modern-map',
                OHA_MODERN_MAP_URL . 'assets/css/oha-modern-map.css',
                ['maplibre-gl'],
                OHA_MODERN_MAP_VERSION
            );

            // Plugin CSS — convention mode
            wp_enqueue_style(
                'oha-convention-map',
                OHA_MODERN_MAP_URL . 'assets/css/oha-convention.css',
                ['oha-modern-map'],
                OHA_MODERN_MAP_VERSION
            );

            // Plugin JS — haunt map
            wp_enqueue_script(
                'oha-modern-map',
                OHA_MODERN_MAP_URL . 'assets/js/oha-modern-map.js',
                ['jquery', 'maplibre-gl'],
                OHA_MODERN_MAP_VERSION,
                true
            );

            // Plugin JS — convention map
            wp_enqueue_script(
                'oha-convention-map',
                OHA_MODERN_MAP_URL . 'assets/js/oha-convention-map.js',
                ['jquery', 'maplibre-gl'],
                OHA_MODERN_MAP_VERSION,
                true
            );

            // Tile style URL — MapTiler if key set, fallback to OpenFreeMap
            $maptiler_key = OHA_Map_Admin::get_maptiler_key();
            $tile_style   = OHA_Map_Admin::get_tile_style_url();

            // Localize all settings for the haunt map JS
            wp_localize_script('oha-modern-map', 'ohaMapSettings', [
                'ajaxUrl'            => admin_url('admin-ajax.php'),
                'nonce'              => wp_create_nonce('oha_map_nonce'),
                'pluginUrl'          => OHA_MODERN_MAP_URL,
                'regions'            => OHA_Map_Regions::get_regions(),
                'regionsGeoJSON'     => OHA_Map_Regions::regions_to_geojson(),
                'icons'              => OHA_Map_Admin::get_icon_settings(),
                'theme'              => OHA_Map_Admin::get_theme_settings(),
                'clustering'         => OHA_Map_Admin::get_clustering_settings(),
                'categoryMappings'   => OHA_Map_Admin::get_category_mappings(),
                'tileStyle'          => $tile_style,
                'maptilerKey'        => $maptiler_key,
                'cardFields'         => OHA_Map_Admin::get_card_fields(),
                'showEventsByDefault'=> OHA_Map_Admin::get_events_settings()['show_by_default'],
                'eventsEnabled'      => OHA_Map_Admin::get_events_settings()['enabled'],
                'regionInfo'         => OHA_Map_Admin::get_region_info(),
                'ohioOutline'        => OHA_Map_Regions::get_ohio_outline_geojson(),
            ]);
        }
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'oha-modern-map') !== false || strpos($hook, 'oha-map') !== false || strpos($hook, 'oha-convention') !== false) {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');

            // Media uploader for icons and convention floorplan upload
            if (strpos($hook, 'oha-map-icons') !== false || strpos($hook, 'oha-convention') !== false) {
                wp_enqueue_media();
            }

            // Booth Placement page — dedicated CSS + JS
            if (strpos($hook, 'oha-booth-placement') !== false) {
                wp_enqueue_media();
                wp_enqueue_style(
                    'oha-placement',
                    OHA_MODERN_MAP_URL . 'assets/css/admin-placement.css',
                    [],
                    OHA_MODERN_MAP_VERSION
                );
                wp_enqueue_script(
                    'oha-placement',
                    OHA_MODERN_MAP_URL . 'assets/js/admin-placement.js',
                    ['jquery', 'wp-color-picker'],
                    OHA_MODERN_MAP_VERSION,
                    true
                );
            }

            wp_enqueue_style(
                'oha-modern-map-admin',
                OHA_MODERN_MAP_URL . 'assets/css/admin.css',
                [],
                OHA_MODERN_MAP_VERSION
            );

            wp_enqueue_script(
                'oha-modern-map-admin',
                OHA_MODERN_MAP_URL . 'assets/js/admin.js',
                ['jquery', 'jquery-ui-sortable', 'wp-color-picker'],
                OHA_MODERN_MAP_VERSION,
                true
            );
        }
    }
}

// Initialize Plugin
function oha_modern_map() {
    return OHA_Modern_Map::instance();
}

// Start the plugin
add_action('plugins_loaded', 'oha_modern_map');

// Activation Hook
register_activation_hook(__FILE__, function() {
    $defaults = [
        'oha_map_theme' => [
            'style'            => 'dark',
            'accent_color'     => '#ff6b35',
            'featured_color'   => '#ffd700',
            'home_haunt_color' => '#9d4edd',
            'background_color' => '#1a1a1a',
        ],
        'oha_map_icons' => [
            'featured_size'   => 32,
            'standard_size'   => 24,
            'home_haunt_size' => 20,
            'featured_glow'   => true,
        ],
        'oha_map_clustering' => [
            'enabled'         => true,
            'max_zoom'        => 14,
            'radius'          => 50,
            'disable_at_zoom' => 16,
        ],
        'oha_map_maptiler' => [
            'api_key'    => '',
            'style'      => 'basic-v2-dark',
            'custom_url' => '',
        ],
        'oha_map_region_colors' => [
            'northwest' => ['fill' => '#ff6b35', 'outline' => '#ff8c5a'],
            'northeast' => ['fill' => '#9d4edd', 'outline' => '#b46ef0'],
            'central'   => ['fill' => '#c0392b', 'outline' => '#e74c3c'],
            'east'      => ['fill' => '#27ae60', 'outline' => '#2ecc71'],
            'west'      => ['fill' => '#e67e22', 'outline' => '#f39c12'],
            'southwest' => ['fill' => '#8e44ad', 'outline' => '#9b59b6'],
            'southeast' => ['fill' => '#d35400', 'outline' => '#e67e22'],
        ],
        'oha_map_card_fields' => [
            'thumbnail'       => true,
            'type_badge'      => true,
            'status_badge'    => true,
            'address'         => true,
            'phone'           => true,
            'website'         => true,
            'email'           => false,
            'view_details'    => true,
            'upcoming_events' => true,
            'description'     => false,
        ],
        'oha_map_events' => [
            'enabled'         => true,
            'show_by_default' => true,
            'layer_label'     => 'Events',
        ],
        'oha_convention_settings' => [
            'enabled'     => false,
            'event_name'  => '',
            'event_dates' => '',
            'venue_name'  => '',
            'floors'      => [
                ['id' => 1, 'label' => 'Main Hall', 'image_id' => 0, 'image_url' => '', 'bg_color' => '#1a1a2e'],
            ],
        ],
    ];

    foreach ($defaults as $key => $value) {
        if (!get_option($key)) {
            add_option($key, $value);
        }
    }
});
