<?php
/**
 * OHA Passport Hub — Event Registry
 *
 * Manages the list of events where badge trading is active.
 * Replaces the hardcoded convention_start/end options from the PWA plugin.
 * Each event has a slug, name, date range, and location.
 * Multiple events can be active simultaneously (e.g. multi-day weekend + testing).
 *
 * @package OHA_Passport_Hub
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class OHA_Passport_Events {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Nothing needed on construct — called from main plugin init
    }

    // ─────────────────────────────────────────────────────────────────────
    // Core event queries
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Check if any registered event is currently active (by date).
     * Also returns true if testing mode override is enabled.
     */
    public function is_any_event_active() {
        if ( get_option( 'oha_passport_testing_mode', false ) ) {
            return true;
        }
        return ! empty( $this->get_active_event() );
    }

    /**
     * Get the currently active event (today falls within start_date–end_date).
     * Returns the first match, or null if no event is active.
     */
    public function get_active_event() {
        // Testing mode: return a placeholder so QR trading works
        if ( get_option( 'oha_passport_testing_mode', false ) ) {
            return [
                'slug'       => 'testing-mode',
                'event_name' => 'Testing Mode',
                'start_date' => current_time( 'Y-m-d' ),
                'end_date'   => current_time( 'Y-m-d' ),
                'location'   => 'Test Environment',
            ];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oha_passport_events';
        $today = current_time( 'Y-m-d' );

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE is_active = 1
                   AND start_date <= %s
                   AND end_date   >= %s
                 ORDER BY start_date ASC
                 LIMIT 1",
                $today, $today
            ),
            ARRAY_A
        );
    }

    /**
     * Get event by slug.
     */
    public function get_event_by_slug( $slug ) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_passport_events';

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ),
            ARRAY_A
        );
    }

    /**
     * Get all registered events (active and past).
     */
    public function get_all_events() {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_passport_events';

        return $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY start_date DESC",
            ARRAY_A
        );
    }

    /**
     * Get upcoming events (start_date in the future).
     */
    public function get_upcoming_events() {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_passport_events';
        $today = current_time( 'Y-m-d' );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE is_active = 1 AND start_date > %s
                 ORDER BY start_date ASC",
                $today
            ),
            ARRAY_A
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // CRUD — called by admin page
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Register a new event.
     * Returns insert ID on success, false on failure.
     */
    public function register_event( $slug, $name, $start_date, $end_date, $location = '', $description = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_passport_events';

        $result = $wpdb->insert( $table, [
            'slug'       => sanitize_text_field( $slug ),
            'event_name' => sanitize_text_field( $name ),
            'start_date' => sanitize_text_field( $start_date ),
            'end_date'   => sanitize_text_field( $end_date ),
            'location'   => sanitize_text_field( $location ),
            'description'=> sanitize_textarea_field( $description ),
            'is_active'  => 1,
        ], [ '%s', '%s', '%s', '%s', '%s', '%s', '%d' ] );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update an existing event.
     */
    public function update_event( $id, $args ) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_passport_events';

        $allowed = [ 'event_name', 'start_date', 'end_date', 'location', 'description', 'is_active' ];
        $data    = array_intersect_key( $args, array_flip( $allowed ) );

        return $wpdb->update( $table, $data, [ 'id' => intval( $id ) ] );
    }

    /**
     * Delete an event (only if it has no stamp data).
     */
    public function delete_event( $id ) {
        global $wpdb;
        $event = $wpdb->get_row( $wpdb->prepare(
            "SELECT slug FROM {$wpdb->prefix}oha_passport_events WHERE id = %d", $id
        ) );
        if ( ! $event ) return false;

        // Protect events that have real stamp data
        $stamps_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}oha_event_stamps WHERE event_slug = %s",
            $event->slug
        ) );
        if ( intval( $stamps_count ) > 0 ) {
            return new WP_Error( 'has_stamps', 'Cannot delete an event that has stamp data. Deactivate it instead.' );
        }

        return $wpdb->delete( "{$wpdb->prefix}oha_passport_events", [ 'id' => intval( $id ) ] );
    }

    /**
     * Get a human-readable description of the current event status.
     */
    public function get_status_label() {
        if ( get_option( 'oha_passport_testing_mode', false ) ) {
            return 'Testing Mode Active';
        }
        $event = $this->get_active_event();
        if ( $event ) {
            return esc_html( $event['event_name'] ) . ' — Badge Trading ACTIVE';
        }
        $upcoming = $this->get_upcoming_events();
        if ( ! empty( $upcoming ) ) {
            $next = $upcoming[0];
            return 'Next: ' . esc_html( $next['event_name'] ) . ' (' . $next['start_date'] . ')';
        }
        return 'No Active Event';
    }
}
