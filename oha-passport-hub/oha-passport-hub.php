<?php
/**
 * Plugin Name: OHA Passport Hub
 * Description: Actor Passport, QR Badge Trading, Vendor Check-in, Scavenger Hunt, and Event Gamification for Ohio Haunters Association events. Works standalone or alongside the OHA Convention PWA.
 * Version: 2.1.3
 * Author: OHA Technical Team
 * Requires PHP: 8.0
 * Text Domain: oha-passport-hub
 *
 * Changelog:
 * 2.0.0 - MAJOR: Badge/achievement system — wp_oha_passport_badge_defs + wp_oha_user_badges tables.
 *         NEW: class-passport-badges.php — engine, AJAX, 8 default badge seeds, admin CRUD.
 *         NEW: [oha_my_badges], [oha_passport_progress], [oha_connections] shortcodes.
 *         NEW: Admin > Badges tab — FA icon picker, color picker, WP media upload, unlock conditions.
 *         FIX: All AJAX nonce failures now return wp_send_json_error (was wp_die — caused "Network
 *              error loading profile" toast when PWA served stale-nonce cached page).
 *         NEW: is_logged_in localized to JS — loadPassportCard() skips AJAX for guests.
 *         NEW: oha_passport_stamp_awarded action hook — fires on every stamp event for badge eval.
 * 1.3.0 - NEW: [oha_convention_passport] shortcode (PWA Core) now triggers Passport Hub asset load.
 *         NEW: oha_award_demo_stamp AJAX endpoint — awards pre-event "OHA Beginner" stamp,
 *              bypasses event-active check so it works before convention day.
 * 1.2.0 - New QR scan modes: Vendor Check-in, Scavenger Hunt, and General URL scanner.
 *         NEW: [oha_vendor_scanner]  — attendee scans vendor booth QR, sees profile + earns stamp.
 *         NEW: [oha_scavenger_hunt]  — progress display + embedded location QR scanner.
 *         NEW: [oha_url_scanner]     — opens any URL found in a QR code.
 *         NEW: [oha_smart_scanner]   — unified scanner that routes to the right mode automatically.
 *         NEW: Admin > Vendors       — manage booth QRs and download printable QR images.
 *         NEW: Admin > Scavenger Hunt— manage hidden location QRs, view completion leaderboard.
 *         DB:  Added oha_vendor_qrs and oha_scavenger_locations tables.
 *              DB version upgrade runs automatically on plugin load.
 * 1.1.0 - Hardening pass following plugin split integrity review.
 *         FIXED: stamp_exists_today() now scopes duplicate-trade check by event_slug,
 *           preventing cross-event false-positives when Passport Hub runs multiple events.
 *         FIXED: check_daily_scan_limit() now scopes the 50/day cap by event_slug,
 *           preventing one event's scan count from blocking another same-day event.
 *         FIXED: get_simple_leaderboard() replaced fragile "actor_one_id IS NULL OR
 *           event_slug = X" ON-clause filter with a clean "event_slug = X" — logic was
 *           equivalent but confusing and would have caused wrong results if actor_one_id
 *           ever allowed NULLs in future schema changes.
 *         HARDENED: enqueue_assets() is now conditional — JS/CSS only load on pages
 *           that contain a Passport Hub shortcode or [oha_convention_schedule].
 *           jsQR is still registered globally (wp_register_script) for other plugins.
 * 1.0.0 - Initial release. Extracted from OHA Convention PWA Core v3.4.1.
 *         Multi-event support, event registry admin, standalone shortcodes.
 *
 * Usage:
 *   [oha_actor_passport]          - Full passport profile card + QR code
 *   [oha_qr_scanner]              - Camera-based QR scanner for badge trading
 *   [oha_vendor_scanner]          - Scan vendor booth QRs for check-in stamps
 *   [oha_scavenger_hunt]          - Scavenger hunt progress + location scanner
 *   [oha_url_scanner]             - General purpose QR-to-URL opener
 *   [oha_smart_scanner]           - Unified scanner (routes automatically by QR type)
 *   [oha_my_stamps]               - User's badge collection and stats
 *   [oha_stamps_leaderboard]      - Participation leaderboard
 *   [oha_event_summary event="oha-convention-2026"] - Event results summary
 *
 * Compatible with: OHA Convention PWA Core 3.4.1+, OHA Hub PWA (upcoming)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────
define( 'OHA_PH_VERSION', '2.1.3' );
define( 'OHA_PH_DB_VERSION', '2.0.0' );
define( 'OHA_PH_DIR',     plugin_dir_path( __FILE__ ) );
define( 'OHA_PH_URL',     plugin_dir_url( __FILE__ ) );
define( 'OHA_PH_SLUG',    'oha-passport-hub' );
define( 'OHA_PH_NONCE',   'oha_passport_hub_nonce' );

// ─────────────────────────────────────────────────────────────────────────────
// Includes — load order matters: Events first (others depend on it)
// ─────────────────────────────────────────────────────────────────────────────
require_once OHA_PH_DIR . 'includes/class-passport-events.php';
require_once OHA_PH_DIR . 'includes/class-passport-profiles.php';
require_once OHA_PH_DIR . 'includes/class-passport-qr-manager.php';
require_once OHA_PH_DIR . 'includes/class-passport-stamps.php';
require_once OHA_PH_DIR . 'includes/class-passport-vendors.php';
require_once OHA_PH_DIR . 'includes/class-passport-scavenger.php';
require_once OHA_PH_DIR . 'includes/class-passport-badges.php';
require_once OHA_PH_DIR . 'includes/class-passport-shortcodes.php';
require_once OHA_PH_DIR . 'includes/class-passport-admin.php';

// ─────────────────────────────────────────────────────────────────────────────
// Main Plugin Class
// ─────────────────────────────────────────────────────────────────────────────
class OHA_Passport_Hub {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init',               [ $this, 'init_passport' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function init_passport() {
        OHA_Passport_Events::get_instance();
        OHA_Passport_Profiles::get_instance();
        OHA_Passport_QR_Manager::get_instance();
        OHA_Passport_Stamps::get_instance();
        OHA_Passport_Vendors::get_instance();
        OHA_Passport_Scavenger::get_instance();
        OHA_Passport_Badges::get_instance();
        OHA_Passport_Shortcodes::get_instance();
        OHA_Passport_Admin::get_instance();
    }

    public function enqueue_assets() {
        // Only enqueue on pages that actually use a Passport Hub shortcode,
        // OR that use [oha_convention_schedule] which delegates to passport tabs.
        // This prevents loading camera/QR/scanner code sitewide.
        if ( ! $this->page_needs_passport_assets() ) {
            // Still register jsQR globally so other plugins can depend on it if needed.
            wp_register_script(
                'oha-jsqr',
                OHA_PH_URL . 'assets/js/jsqr.min.js',
                [],
                '1.4.0',
                true
            );
            return;
        }

        // jsQR — registered globally so shortcodes and PWA plugins can both depend on it
        wp_register_script(
            'oha-jsqr',
            OHA_PH_URL . 'assets/js/jsqr.min.js',
            [],
            '1.4.0',
            true
        );

        wp_enqueue_script(
            'oha-passport-hub',
            OHA_PH_URL . 'assets/js/passport-hub.js',
            [ 'jquery', 'oha-jsqr' ],
            OHA_PH_VERSION,
            true
        );

        // Localize — use both ohaPassportHub (new) AND ohaActorPassport (legacy compat)
        // so the Convention PWA shortcode integration works during transition
        $localize_data = [
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( OHA_PH_NONCE ),
            'legacy_nonce'    => wp_create_nonce( 'oha_actor_passport_nonce' ),
            'version'         => OHA_PH_VERSION,
            'active_event'    => OHA_Passport_Events::get_instance()->get_active_event(),
            'is_event_active' => OHA_Passport_Events::get_instance()->is_any_event_active(),
            'is_logged_in'      => is_user_logged_in(),
            'jsqr_url'          => OHA_PH_URL . 'assets/js/jsqr.min.js',
            'passport_page_url' => get_option( 'oha_pwa_passport_url', rtrim( get_option( 'oha_convention_base_url', '/convention' ), '/' ) . '/passport/' ),
        ];
        wp_localize_script( 'oha-passport-hub', 'ohaPassportHub', $localize_data );
        wp_localize_script( 'oha-passport-hub', 'ohaActorPassport', $localize_data ); // legacy compat

        wp_enqueue_style(
            'oha-passport-hub',
            OHA_PH_URL . 'assets/css/passport-hub.css',
            [],
            OHA_PH_VERSION
        );

        // Font Awesome 6 Free — for badge icons
        wp_enqueue_style(
            'font-awesome-6',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
            [],
            '6.5.0'
        );
    }

    /**
     * Returns true if the current page contains a Passport Hub shortcode
     * OR the Convention PWA schedule shortcode that delegates passport tabs.
     * Also returns true for Passport Hub admin pages.
     */
    private function page_needs_passport_assets() {
        // Always load on Passport Hub admin pages
        if ( is_admin() ) {
            $screen = get_current_screen();
            if ( $screen && strpos( $screen->id, 'oha-passport' ) !== false ) {
                return true;
            }
            return false; // Other admin pages don't need passport assets
        }

        global $post;
        if ( ! $post || ! isset( $post->post_content ) ) {
            return false;
        }

        $passport_shortcodes = [
            'oha_actor_passport',
            'oha_qr_scanner',
            'oha_vendor_scanner',
            'oha_scavenger_hunt',
            'oha_url_scanner',
            'oha_smart_scanner',
            'oha_my_stamps',
            'oha_stamps_leaderboard',
            'oha_event_summary',
            'oha_convention_schedule', // Convention PWA delegates passport tabs to us
            'oha_convention_passport', // Convention PWA passport page (PWA Core shortcode)
            'oha_my_badges',
            'oha_passport_progress',
            'oha_connections',
        ];

        foreach ( $passport_shortcodes as $shortcode ) {
            if ( has_shortcode( $post->post_content, $shortcode ) ) {
                return true;
            }
        }

        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Bootstrap
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function() {
    OHA_Passport_Hub::get_instance();

    // Run DB upgrade if needed (safe on every load — dbDelta is idempotent)
    if ( get_option( 'oha_ph_db_version' ) !== OHA_PH_DB_VERSION ) {
        oha_passport_hub_run_db_upgrade();
    }
} );

/**
 * Public helper — lets other plugins check if Passport Hub is active
 * Usage: if ( function_exists( 'oha_passport_hub_active' ) ) { ... }
 */
function oha_passport_hub_active() {
    return true;
}

/**
 * Public helper — get active event info from outside the plugin
 */
function oha_passport_get_active_event() {
    return OHA_Passport_Events::get_instance()->get_active_event();
}

/**
 * Public helper — check if passport trading is currently enabled
 */
function oha_passport_is_trading_active() {
    return OHA_Passport_Events::get_instance()->is_any_event_active();
}

// ─────────────────────────────────────────────────────────────────────────────
// DB table creation / upgrade (shared by activation hook + version upgrade)
// ─────────────────────────────────────────────────────────────────────────────
function oha_passport_hub_run_db_upgrade() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // ── oha_vendor_qrs ────────────────────────────────────────────────────
    $table_vendors = $wpdb->prefix . 'oha_vendor_qrs';
    dbDelta( "CREATE TABLE {$table_vendors} (
        id           bigint(20)   NOT NULL AUTO_INCREMENT,
        event_slug   varchar(100) NOT NULL,
        vendor_name  varchar(200) NOT NULL,
        description  text         DEFAULT NULL,
        website_url  varchar(500) DEFAULT NULL,
        booth_number varchar(50)  DEFAULT NULL,
        is_active    tinyint(1)   NOT NULL DEFAULT 1,
        created_at   timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_event_slug (event_slug)
    ) {$charset_collate};" );

    // ── oha_scavenger_locations ───────────────────────────────────────────
    $table_locations = $wpdb->prefix . 'oha_scavenger_locations';
    dbDelta( "CREATE TABLE {$table_locations} (
        id                   bigint(20)   NOT NULL AUTO_INCREMENT,
        event_slug           varchar(100) NOT NULL,
        location_name        varchar(200) NOT NULL,
        location_hint        varchar(300) DEFAULT NULL,
        location_description text         DEFAULT NULL,
        qr_hash              varchar(64)  NOT NULL,
        sort_order           int          NOT NULL DEFAULT 0,
        is_active            tinyint(1)   NOT NULL DEFAULT 1,
        created_at           timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_qr_hash  (qr_hash),
        KEY idx_event_slug (event_slug, sort_order)
    ) {$charset_collate};" );

    // ── oha_passport_badge_defs ───────────────────────────────────────────
    $table_badge_defs = $wpdb->prefix . 'oha_passport_badge_defs';
    dbDelta( "CREATE TABLE {$table_badge_defs} (
        id               bigint(20)   NOT NULL AUTO_INCREMENT,
        badge_slug       varchar(100) NOT NULL,
        badge_name       varchar(150) NOT NULL,
        badge_desc       varchar(300) DEFAULT NULL,
        icon_type        enum('fa','upload') NOT NULL DEFAULT 'fa',
        icon_value       varchar(300) NOT NULL DEFAULT 'fa-solid fa-star',
        icon_color       varchar(7)   NOT NULL DEFAULT '#8e44ad',
        unlock_type      enum('profile_created','stamp_count','stamp_type_count','event_stamp','manual') NOT NULL DEFAULT 'stamp_count',
        unlock_threshold int          NOT NULL DEFAULT 0,
        unlock_param     varchar(100) DEFAULT NULL,
        sort_order       int          NOT NULL DEFAULT 0,
        is_active        tinyint(1)   NOT NULL DEFAULT 1,
        created_at       timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_badge_slug (badge_slug)
    ) {$charset_collate};" );

    // ── oha_user_badges ───────────────────────────────────────────────────
    $table_user_badges = $wpdb->prefix . 'oha_user_badges';
    dbDelta( "CREATE TABLE {$table_user_badges} (
        id            bigint(20)   NOT NULL AUTO_INCREMENT,
        user_id       bigint(20)   NOT NULL,
        badge_id      bigint(20)   NOT NULL,
        unlocked_at   timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        event_context varchar(100) DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_user_badge (user_id, badge_id),
        KEY idx_user_id  (user_id),
        KEY idx_badge_id (badge_id)
    ) {$charset_collate};" );

    // Seed default badges on first install or after table creation
    if ( class_exists( 'OHA_Passport_Badges' ) ) {
        OHA_Passport_Badges::get_instance()->seed_default_badges();
    }

    update_option( 'oha_ph_db_version', OHA_PH_DB_VERSION );
}

// ─────────────────────────────────────────────────────────────────────────────
// Activation — create all database tables
// ─────────────────────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'oha_passport_hub_activate' );

function oha_passport_hub_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // ── oha_actor_profiles ────────────────────────────────────────────────
    $table_profiles = $wpdb->prefix . 'oha_actor_profiles';
    $sql_profiles   = "CREATE TABLE {$table_profiles} (
        id                  bigint(20)    NOT NULL AUTO_INCREMENT,
        user_id             bigint(20)    NOT NULL,
        character_name      varchar(100)  NOT NULL,
        character_type      varchar(50)   DEFAULT NULL,
        haunt_name          varchar(150)  DEFAULT NULL,
        haunt_location      varchar(100)  DEFAULT NULL,
        character_photo_url varchar(500)  DEFAULT NULL,
        character_bio       text          DEFAULT NULL,
        skill_tags          text          DEFAULT NULL,
        qr_code_hash        varchar(64)   DEFAULT NULL,
        qr_expires_at       datetime      DEFAULT NULL,
        is_active           tinyint(1)    NOT NULL DEFAULT 1,
        stamps_earned       int           NOT NULL DEFAULT 0,
        created_at          timestamp     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at          timestamp     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_user_id      (user_id),
        UNIQUE KEY uq_qr_code_hash (qr_code_hash),
        KEY idx_is_active  (is_active)
    ) {$charset_collate};";

    // ── oha_event_stamps ──────────────────────────────────────────────────
    $table_stamps = $wpdb->prefix . 'oha_event_stamps';
    $sql_stamps   = "CREATE TABLE {$table_stamps} (
        id           bigint(20)   NOT NULL AUTO_INCREMENT,
        actor_one_id bigint(20)   NOT NULL,
        actor_two_id bigint(20)   NOT NULL,
        event_slug   varchar(100) NOT NULL DEFAULT 'oha-convention-2026',
        event_date   date         NOT NULL,
        stamp_type   varchar(50)  NOT NULL DEFAULT 'trade',
        location     varchar(100) DEFAULT NULL,
        created_at   timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_daily_trade (actor_one_id, actor_two_id, event_slug, event_date),
        KEY idx_event     (event_slug, event_date),
        KEY idx_actor_one (actor_one_id),
        KEY idx_actor_two (actor_two_id)
    ) {$charset_collate};";

    // ── oha_passport_events ───────────────────────────────────────────────
    // The event registry — every event that badge trading can be active for.
    // Replacing the old hardcoded convention_start / convention_end options.
    $table_events = $wpdb->prefix . 'oha_passport_events';
    $sql_events   = "CREATE TABLE {$table_events} (
        id           int(11)      NOT NULL AUTO_INCREMENT,
        slug         varchar(100) NOT NULL,
        event_name   varchar(200) NOT NULL,
        start_date   date         NOT NULL,
        end_date     date         NOT NULL,
        location     varchar(300) DEFAULT NULL,
        description  text         DEFAULT NULL,
        is_active    tinyint(1)   NOT NULL DEFAULT 1,
        created_at   timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_slug (slug),
        KEY idx_dates      (start_date, end_date)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql_profiles );
    dbDelta( $sql_stamps );
    dbDelta( $sql_events );

    // Also create new 1.2.0 tables
    oha_passport_hub_run_db_upgrade();

    // Seed the 2026 convention as the first event
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_events}'" ) === $table_events ) {
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_events} WHERE slug = %s", 'oha-convention-2026'
        ));
        if ( ! $existing ) {
            $wpdb->insert( $table_events, [
                'slug'       => 'oha-convention-2026',
                'event_name' => 'OHA Convention 2026',
                'start_date' => '2026-05-09',
                'end_date'   => '2026-05-10',
                'location'   => 'Ohio State Reformatory, Mansfield, OH',
                'is_active'  => 1,
            ], [ '%s', '%s', '%s', '%s', '%s', '%d' ] );
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Deactivation — leave data intact (admin can delete tables manually if needed)
// ─────────────────────────────────────────────────────────────────────────────
register_deactivation_hook( __FILE__, function() {
    // Intentionally empty — preserve all profile and stamp data on deactivation
} );
