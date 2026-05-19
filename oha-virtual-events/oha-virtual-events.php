<?php
/**
 * Plugin Name: OHA Virtual Events
 * Plugin URI:  https://ohaunters.org
 * Description: Extends GeoDirectory Events with Zoom Pro and YouTube virtual
 *              meeting support for OHA board meetings, education sessions,
 *              actor meetups, member town halls, and replay libraries. Also
 *              displays upcoming events on haunt venue listing pages (replaces
 *              the gd_show_events_on_place_page code snippet - remove that
 *              snippet after activating this plugin).
 * Version:     1.4.2
 * Author:      OHA Development
 * License:     GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: oha-virtual-events
 *
 * ============================================================
 * PLUGIN DEPENDENCIES - everything this plugin connects to
 * ============================================================
 *
 * REQUIRED (plugin warns and stops loading without these):
 *
 *   1. GeoDirectory Free (core)
 *      Slug:     geodirectory
 *      Provides: gd_place post type, geodir_get_post_info() helper,
 *                wp_geodir_gd_place_detail custom DB table,
 *                GeoDirectory custom-field framework and hooks.
 *      Used for: Reading venue/haunt listing data, hooking into GD content
 *                pipeline for place and event detail pages.
 *
 *   2. Events for GeoDirectory (free add-on)
 *      Slug:     events-for-geodirectory
 *      Provides: gd_event post type, event scheduling/recurrence,
 *                GEODIR_EVENT_VERSION constant,
 *                wp_geodir_event_schedule database table,
 *                calendar widgets.
 *      Used for: The gd_event post type that virtual events are attached to.
 *                Event schedule table is queried for date/time display in
 *                shortcode output.
 *
 * STRONGLY RECOMMENDED (access control degrades without these):
 *
 *   3. UsersWP
 *      Slug:        userswp
 *      Provides:    User registration, My Account pages, account type routing.
 *      Account types used: board-member (UsersWP account type - used for
 *                  My Account display routing only, NOT for WP role checks).
 *      Used for:    The Education & Events tab in My Account where shortcodes
 *                  from this plugin are placed.
 *
 *   4. OHA Custom Restriction Plugin (internal)
 *      Provides:   Site-wide member-area access restriction, login redirects.
 *      Used for:   Complements per-event access control on virtual event pages.
 *
 * ============================================================
 * WORDPRESS ROLE REFERENCE (used for access control checks)
 * ============================================================
 *
 *   administrator    - site admin, full access everywhere
 *   oha_board        - board member WP role (stored in wp_capabilities meta).
 *                      NOTE: "board-member" is the UsersWP account type used
 *                      for My Account display; "oha_board" is the WP role slug
 *                      used for capability/access checks in this plugin.
 *   oha_instructor   - instructor role, can be assigned to events
 *   subscriber       - paid OHA member (primary member audience)
 *   listing_owner    - free listing owner (occasional free session access)
 *
 * ============================================================
 * EXTERNAL SERVICES
 * ============================================================
 *
 *   Zoom Pro account
 *      Phase 1: Meeting links pasted manually into event meta box.
 *               Separate join URL (for attendees) and start/host URL
 *               (for assigned instructor) stored per event.
 *      Phase 2: Zoom Server-to-Server OAuth API - auto-create meetings
 *               on publish, auto-attach recordings via webhooks.
 *
 *   YouTube (unlisted videos - optional)
 *      Phase 1: Video IDs pasted manually for large broadcasts / replays.
 *      Phase 3: YouTube Data API v3 integration.
 *
 * ============================================================
 * SHORTCODES (for My Account > Education & Events tab)
 * ============================================================
 *
 *   [oha_education_events]   - master hub (all sections stacked)
 *   [oha_education_live]     - live now section only
 *   [oha_education_upcoming] - upcoming events section only
 *   [oha_education_teaching] - events where logged-in user is instructor
 *   [oha_education_ondemand] - archived replay library
 *
 * ============================================================
 * SNIPPET REPLACEMENT NOTE
 * ============================================================
 * This plugin includes the logic from the site snippet:
 *   add_filter('the_content', 'gd_show_events_on_place_page')
 * Once this plugin is active and working, REMOVE that snippet.
 * ============================================================
 *
 * PHASE ROADMAP:
 *   Phase 1: Manual Zoom links, instructor assignment, shortcodes,
 *            access control, replay management. (complete)
 *   Phase 2 (this version): Zoom Server-to-Server OAuth, auto-create meetings
 *            on publish, webhook receivers for meeting.started / meeting.ended /
 *            recording.completed. Admin settings page with shortcode reference.
 *   Phase 3: YouTube Data API v3, Live Now banners, archive organisation.
 *   Phase 4: Per-user unique links, attendance tracking, certificates.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'OHA_VE_VERSION',    '1.4.2' );
define( 'OHA_VE_PLUGIN_FILE', __FILE__ );
define( 'OHA_VE_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'OHA_VE_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

/**
 * Boot after all plugins have loaded.
 * Checks required dependencies before initialising.
 */
add_action( 'plugins_loaded', 'oha_ve_init' );

function oha_ve_init() {

    $missing = [];

    if ( ! function_exists( 'geodir_get_post_info' ) ) {
        $missing[] = 'GeoDirectory (core)';
    }

    if ( ! defined( 'GEODIR_EVENT_VERSION' ) ) {
        $missing[] = 'Events for GeoDirectory';
    }

    if ( ! empty( $missing ) ) {
        add_action( 'admin_notices', function () use ( $missing ) {
            $list = implode( ', ', array_map( 'esc_html', $missing ) );
            echo '<div class="notice notice-error"><p>';
            echo '<strong>OHA Virtual Events</strong> requires: ' . $list . '. ';
            echo 'Please activate those plugins, then re-activate OHA Virtual Events.';
            echo '</p></div>';
        } );
        return;
    }

    // Warn if old place-events snippet is still running (would cause duplicate output).
    if ( function_exists( 'gd_show_events_on_place_page' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>OHA Virtual Events:</strong> The <code>gd_show_events_on_place_page</code> snippet is still active. ';
            echo 'This will cause duplicate event lists on haunt venue pages. ';
            echo 'Please remove or disable that snippet.';
            echo '</p></div>';
        } );
    }

    // Phase 2 classes must load before Phase 1 admin class (admin references Settings).
    require_once OHA_VE_PLUGIN_DIR . 'includes/class-oha-ve-settings.php';
    require_once OHA_VE_PLUGIN_DIR . 'includes/class-oha-ve-zoom-api.php';
    require_once OHA_VE_PLUGIN_DIR . 'includes/class-oha-ve-zoom-webhooks.php';

    require_once OHA_VE_PLUGIN_DIR . 'includes/class-oha-ve-admin.php';
    require_once OHA_VE_PLUGIN_DIR . 'includes/class-oha-ve-frontend.php';
    require_once OHA_VE_PLUGIN_DIR . 'includes/class-oha-ve-shortcodes.php';
    require_once OHA_VE_PLUGIN_DIR . 'includes/class-oha-ve-create.php';

    new OHA_VE_Settings();
    OHA_VE_Zoom_API::get_instance(); // Registers publish hook and error notice
    new OHA_VE_Zoom_Webhooks();
    new OHA_VE_Admin();
    new OHA_VE_Frontend();
    new OHA_VE_Shortcodes();
    new OHA_VE_Create();
}
