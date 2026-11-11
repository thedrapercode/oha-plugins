<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OHA_Site_PWA_Service_Worker {

    private static $_instance = null;

    public static function instance() {
        if ( null === self::$_instance ) self::$_instance = new self();
        return self::$_instance;
    }

    public function init() {
        add_action( 'wp_enqueue_scripts',   array( $this, 'enqueue' ) );
        add_action( 'wp_head',              array( $this, 'head_tags' ), 1 );
        add_action( 'wp_footer',            array( $this, 'notify_banner_html' ) );
        add_action( 'admin_post_oha_spwa_save_settings', array( $this, 'on_settings_saved' ) );
        add_filter( 'body_class',           array( $this, 'body_classes' ) );
    }

    // ------------------------------------------------------------------
    // Physical file writing
    // ------------------------------------------------------------------

    public static function write_physical_files() {
        self::write_sw();
        self::write_manifest();
    }

    public static function write_sw() {
        $version     = OHA_SITE_PWA_VERSION;
        $offline_url = get_option( 'oha_spwa_offline_url', '/offline/' );

        // Pull MapTiler key from oha-modern-map settings (if that plugin is active).
        $mt_settings  = get_option( 'oha_map_maptiler', array() );
        $maptiler_key = ! empty( $mt_settings['api_key'] ) ? $mt_settings['api_key'] : '';
        $ohio_tiles   = $maptiler_key ? self::build_ohio_tile_urls( $maptiler_key ) : array();

        $template = file_get_contents( OHA_SITE_PWA_PATH . 'assets/js/oha-site-sw.js' );
        $content  = str_replace(
            array( '{{VERSION}}', '{{OFFLINE_URL}}', '{{OHIO_TILES_JSON}}' ),
            array( $version, esc_url_raw( $offline_url ), wp_json_encode( $ohio_tiles ) ),
            $template
        );
        file_put_contents( ABSPATH . 'oha-site-sw.js', $content );
    }

    private static function build_ohio_tile_urls( $key ) {
        $urls = array();
        $key  = rawurlencode( $key );
        // Ohio bounding box: ~38.4N-42.0N, 84.8W-80.5W
        // Tile ranges per zoom (Mercator, standard XYZ)
        $ranges = array(
            6 => array( 'x' => array( 16, 17 ), 'y' => array( 23, 24 ) ),
            7 => array( 'x' => array( 33, 35 ), 'y' => array( 47, 49 ) ),
            8 => array( 'x' => array( 67, 70 ), 'y' => array( 95, 98 ) ),
        );
        foreach ( $ranges as $z => $r ) {
            for ( $x = $r['x'][0]; $x <= $r['x'][1]; $x++ ) {
                for ( $y = $r['y'][0]; $y <= $r['y'][1]; $y++ ) {
                    $urls[] = "https://api.maptiler.com/tiles/v3/{$z}/{$x}/{$y}.pbf?key={$key}";
                }
            }
        }
        return $urls;
    }

    public static function write_manifest() {
        $name       = get_option( 'oha_spwa_app_name', 'Ohio Haunters Association' );
        $short      = get_option( 'oha_spwa_short_name', 'OHA' );
        $start_url  = get_option( 'oha_spwa_start_url', '/' );
        $theme      = get_option( 'oha_spwa_theme_color', '#ad3119' );
        $bg         = get_option( 'oha_spwa_bg_color', '#680400' );
        $icon_url   = get_option( 'oha_spwa_icon_url', '' );
        $mask_url   = get_option( 'oha_spwa_mask_icon_url', '' );

        $icons = array();
        if ( $icon_url ) {
            $icons[] = array( 'src' => $icon_url, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' );
        }
        if ( $mask_url ) {
            $icons[] = array( 'src' => $mask_url, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' );
        }

        $manifest = array(
            'name'             => $name,
            'short_name'       => $short,
            'start_url'        => $start_url,
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'theme_color'      => $theme,
            'background_color' => $bg,
            'icons'            => $icons,
            'shortcuts'        => self::build_shortcuts(),
        );

        file_put_contents(
            ABSPATH . 'oha-site-manifest.json',
            wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
        );
    }

    private static function build_shortcuts() {
        $shortcuts = array();
        $tabs = array(
            array( 'oha_spwa_nav_listings_url', 'Haunt Listings' ),
            array( 'oha_spwa_nav_myoha_url',    'My OHA' ),
            array( 'oha_spwa_nav_events_url',   'Events' ),
        );
        foreach ( $tabs as $tab ) {
            $url = get_option( $tab[0], '' );
            if ( $url ) {
                $shortcuts[] = array( 'name' => $tab[1], 'url' => $url );
            }
        }
        return $shortcuts;
    }

    public function on_settings_saved() {
        self::write_manifest();
        self::write_sw();
    }

    // ------------------------------------------------------------------
    // Front-end output
    // ------------------------------------------------------------------

    public function enqueue() {
        if ( is_admin() ) return;

        $app_id = get_option( 'oha_spwa_onesignal_app_id', '' );

        wp_enqueue_script(
            'onesignal-sdk',
            'https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js',
            array(),
            null,
            false
        );

        wp_enqueue_script(
            'oha-site-pwa-init',
            OHA_SITE_PWA_URL . 'assets/js/oha-pwa-init.js',
            array( 'onesignal-sdk' ),
            OHA_SITE_PWA_VERSION,
            true
        );

        wp_localize_script( 'oha-site-pwa-init', 'ohaSpwaConfig', array(
            'appId'      => $app_id,
            'swPath'     => home_url( '/oha-site-sw.js' ),
            'swScope'    => home_url( '/' ),
            'pushEnabled' => get_option( 'oha_spwa_push_enabled', '1' ),
        ) );

        wp_enqueue_script(
            'oha-site-pwa-install',
            OHA_SITE_PWA_URL . 'assets/js/oha-pwa-install.js',
            array(),
            OHA_SITE_PWA_VERSION,
            true
        );

        // Notification prompt JS — only when push is configured
        $push_app_id = get_option( 'oha_spwa_onesignal_app_id', '' );
        if ( get_option( 'oha_spwa_push_enabled', '1' ) === '1' && $push_app_id ) {
            wp_enqueue_script(
                'oha-site-pwa-notify',
                OHA_SITE_PWA_URL . 'assets/js/oha-pwa-notify.js',
                array( 'oha-site-pwa-init' ),
                OHA_SITE_PWA_VERSION,
                true
            );
        }

        wp_enqueue_style(
            'oha-site-pwa',
            OHA_SITE_PWA_URL . 'assets/css/oha-pwa.css',
            array(),
            OHA_SITE_PWA_VERSION
        );
    }

    public function notify_banner_html() {
        if ( is_admin() ) return;
        if ( get_option( 'oha_spwa_push_enabled', '1' ) !== '1' ) return;
        if ( ! get_option( 'oha_spwa_onesignal_app_id', '' ) ) return;
        ?>
        <div id="oha-notify-banner" aria-hidden="true">
            <div id="oha-notify-header">
                <strong>Stay informed</strong>
                <button id="oha-notify-close" aria-label="Dismiss">&#x2715;</button>
            </div>
            <p id="oha-notify-body">Get alerts for haunt updates and OHA announcements.</p>
            <div id="oha-notify-actions">
                <button id="oha-notify-accept">Enable Notifications</button>
                <button id="oha-notify-defer">Not Now</button>
            </div>
        </div>
        <?php
    }

    public function head_tags() {
        if ( is_admin() ) return;

        $name       = esc_attr( get_option( 'oha_spwa_app_name', 'Ohio Haunters Association' ) );
        $short      = esc_attr( get_option( 'oha_spwa_short_name', 'OHA' ) );
        $theme      = esc_attr( get_option( 'oha_spwa_theme_color', '#ad3119' ) );
        $icon_url   = esc_url( get_option( 'oha_spwa_icon_url', '' ) );
        $manifest   = esc_url( home_url( '/oha-site-manifest.json' ) );

        // Inline script runs synchronously before CSS paints — prevents flash of
        // wrong state when pwa-hide/pwa-show utility classes are used on sections.
        echo '<script>!function(){var e=document.documentElement;if(window.matchMedia("(display-mode:standalone)").matches||window.navigator.standalone===true)e.classList.add("pwa-mode");if(!navigator.onLine)e.classList.add("pwa-offline");}();</script>' . "\n";

        echo '<link rel="manifest" href="' . $manifest . '">' . "\n";
        echo '<meta name="theme-color" content="' . $theme . '">' . "\n";
        echo '<meta name="application-name" content="' . $name . '">' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
        echo '<meta name="apple-mobile-web-app-title" content="' . $short . '">' . "\n";
        if ( $icon_url ) {
            echo '<link rel="apple-touch-icon" href="' . $icon_url . '">' . "\n";
        }
    }

    public function body_classes( $classes ) {
        if ( ! is_user_logged_in() ) return $classes;
        $user_id = get_current_user_id();
        if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' ) ) {
            $classes[] = 'is-board';
        }
        if ( get_user_meta( $user_id, 'oha_member_id', true ) ) {
            $classes[] = 'is-oha-member';
        }
        return $classes;
    }
}
