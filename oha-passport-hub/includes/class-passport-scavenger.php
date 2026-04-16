<?php
/**
 * OHA Passport Hub — Scavenger Hunt
 *
 * Manages scavenger hunt location QR codes and attendee progress.
 * Admin creates location records and prints static QR codes to hide around the venue.
 * Attendees scan hidden QRs → earn 'location' stamps → see completion progress.
 *
 * QR data format:  LOC:{location_slug}
 * Table:           {prefix}oha_scavenger_locations
 * Stamp type:      'location'  in oha_event_stamps
 *
 * Completion: When all active locations for an event are scanned,
 *             a bonus 'scavenger_complete' stamp is automatically recorded.
 *
 * @package OHA_Passport_Hub
 * @version 1.2.0
 */

defined( 'ABSPATH' ) || exit;

class OHA_Passport_Scavenger {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action( 'wp_ajax_oha_scan_location_qr',          [ $this, 'ajax_scan_location_qr' ] );
        add_action( 'wp_ajax_oha_get_scavenger_progress',    [ $this, 'ajax_get_scavenger_progress' ] );
        // Admin CRUD
        add_action( 'wp_ajax_oha_admin_save_location',       [ $this, 'ajax_admin_save_location' ] );
        add_action( 'wp_ajax_oha_admin_delete_location',     [ $this, 'ajax_admin_delete_location' ] );
        add_action( 'wp_ajax_oha_admin_reorder_locations',   [ $this, 'ajax_admin_reorder_locations' ] );
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX: Scan location QR
    // ─────────────────────────────────────────────────────────────────────

    public function ajax_scan_location_qr() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', OHA_PH_NONCE ) &&
             ! wp_verify_nonce( $_POST['nonce'] ?? '', 'oha_actor_passport_nonce' ) ) {
            wp_send_json_error( 'Invalid request' );
        }
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in to participate in the scavenger hunt' );
        }

        $qr_hash = sanitize_text_field( $_POST['qr_hash'] ?? '' );
        if ( empty( $qr_hash ) ) wp_send_json_error( 'Invalid QR code' );

        $location = $this->get_location_by_hash( $qr_hash );
        if ( ! $location ) {
            wp_send_json_error( 'Location QR code not found — make sure you scanned one of the hidden scavenger hunt markers' );
        }

        $user_id = get_current_user_id();
        $profile = OHA_Passport_Profiles::get_instance()->get_profile_by_user_id( $user_id );
        if ( ! $profile ) {
            wp_send_json_error( 'You need to create an Actor Passport to participate in the scavenger hunt! Visit the Passport page first.' );
        }

        $stamp_result = $this->maybe_record_location_stamp( $profile['id'], $location );

        // Check for hunt completion after stamping
        $progress = $this->get_user_progress( $profile['id'], $location['event_slug'] );

        $response = [
            'location'       => [
                'name'        => $location['location_name'],
                'description' => $location['location_description'],
            ],
            'stamp_recorded' => $stamp_result['recorded'],
            'message'        => $stamp_result['message'],
            'progress'       => $progress,
        ];

        // Award completion bonus if just completed
        if ( $stamp_result['recorded'] && $progress['is_complete'] && ! $stamp_result['was_already_complete'] ) {
            $this->record_completion_stamp( $profile['id'], $location['event_slug'] );
            $response['completed']         = true;
            $response['completion_message'] = '🏆 Amazing! You found all ' . $progress['total'] . ' locations — Hunt Complete!';
        }

        wp_send_json_success( $response );
    }

    /**
     * Returns progress data for the scavenger hunt for the current user.
     */
    public function ajax_get_scavenger_progress() {
        if ( ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', OHA_PH_NONCE ) &&
             ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', 'oha_actor_passport_nonce' ) ) {
            wp_send_json_error( 'Invalid request' );
        }
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in' );
        }

        $event_slug = sanitize_text_field( $_REQUEST['event_slug'] ?? '' );
        if ( empty( $event_slug ) ) {
            $event = OHA_Passport_QR_Manager::get_instance()->get_current_event();
            $event_slug = $event['slug'];
        }

        $user_id = get_current_user_id();
        $profile = OHA_Passport_Profiles::get_instance()->get_profile_by_user_id( $user_id );
        if ( ! $profile ) {
            wp_send_json_error( 'No actor profile found' );
        }

        $progress  = $this->get_user_progress( $profile['id'], $event_slug );
        $locations = $this->get_locations_for_event( $event_slug );
        $scanned   = $this->get_scanned_location_ids( $profile['id'], $event_slug );

        // Attach found/not-found status to each location
        $locations_with_status = [];
        foreach ( $locations as $loc ) {
            $locations_with_status[] = [
                'id'          => intval( $loc['id'] ),
                'name'        => $loc['location_name'],
                'hint'        => $loc['location_hint'],
                'found'       => in_array( intval( $loc['id'] ), $scanned ),
                'sort_order'  => intval( $loc['sort_order'] ),
            ];
        }

        wp_send_json_success( [
            'progress'  => $progress,
            'locations' => $locations_with_status,
            'event_slug'=> $event_slug,
        ] );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Admin AJAX
    // ─────────────────────────────────────────────────────────────────────

    public function ajax_admin_save_location() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', OHA_PH_NONCE ) ) wp_send_json_error( 'Invalid request' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        $location_name = sanitize_text_field( $_POST['location_name'] ?? '' );
        $location_hint = sanitize_text_field( $_POST['location_hint'] ?? '' );
        $description   = sanitize_textarea_field( $_POST['location_description'] ?? '' );
        $event_slug    = sanitize_text_field( $_POST['event_slug']    ?? '' );
        $sort_order    = intval( $_POST['sort_order'] ?? 0 );

        if ( empty( $location_name ) ) wp_send_json_error( 'Location name is required' );
        if ( empty( $event_slug ) )    wp_send_json_error( 'Event is required' );

        $result = $this->create_location( $location_name, $location_hint, $description, $event_slug, $sort_order );
        if ( ! $result ) wp_send_json_error( 'Failed to save location' );

        $loc = $this->get_location_by_id( $result );
        wp_send_json_success( [
            'id'       => $result,
            'qr_url'   => $this->get_location_qr_url( $loc['qr_hash'] ),
            'qr_data'  => 'LOC:' . $loc['qr_hash'],
        ] );
    }

    public function ajax_admin_delete_location() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', OHA_PH_NONCE ) ) wp_send_json_error( 'Invalid request' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        $id = intval( $_POST['id'] ?? 0 );
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'oha_scavenger_locations',
            [ 'is_active' => 0 ],
            [ 'id' => $id ],
            [ '%d' ], [ '%d' ]
        );
        wp_send_json_success();
    }

    public function ajax_admin_reorder_locations() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', OHA_PH_NONCE ) ) wp_send_json_error( 'Invalid request' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        $order = $_POST['order'] ?? [];
        if ( ! is_array( $order ) ) wp_send_json_error( 'Invalid order data' );

        global $wpdb;
        foreach ( $order as $position => $loc_id ) {
            $wpdb->update(
                $wpdb->prefix . 'oha_scavenger_locations',
                [ 'sort_order' => intval( $position ) ],
                [ 'id' => intval( $loc_id ) ],
                [ '%d' ], [ '%d' ]
            );
        }
        wp_send_json_success();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Stamp logic
    // ─────────────────────────────────────────────────────────────────────

    private function maybe_record_location_stamp( $profile_id, $location ) {
        global $wpdb;

        $event_slug = $location['event_slug'];
        $today      = current_time( 'Y-m-d' );

        // Check if this location was already found (any day, same event — locations are one-time)
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}oha_event_stamps
             WHERE actor_one_id = %d
               AND actor_two_id = %d
               AND stamp_type   = 'location'
               AND event_slug   = %s",
            $profile_id, intval( $location['id'] ), $event_slug
        ) );

        if ( intval( $exists ) > 0 ) {
            return [
                'recorded'           => false,
                'was_already_complete' => $this->get_user_progress( $profile_id, $event_slug )['is_complete'],
                'message'            => 'You already found ' . esc_html( $location['location_name'] ) . '! 📍',
            ];
        }

        $was_complete_before = $this->get_user_progress( $profile_id, $event_slug )['is_complete'];

        $result = $wpdb->insert(
            $wpdb->prefix . 'oha_event_stamps',
            [
                'actor_one_id' => $profile_id,
                'actor_two_id' => intval( $location['id'] ),
                'event_slug'   => $event_slug,
                'event_date'   => $today,
                'stamp_type'   => 'location',
                'location'     => substr( $location['location_name'], 0, 100 ),
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s' ]
        );

        if ( $result === false ) {
            return [ 'recorded' => false, 'was_already_complete' => $was_complete_before, 'message' => 'Stamp failed — please try again' ];
        }

        // Update profile stamp counter
        $total = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}oha_event_stamps
             WHERE actor_one_id = %d OR actor_two_id = %d",
            $profile_id, $profile_id
        ) );
        $wpdb->update(
            $wpdb->prefix . 'oha_actor_profiles',
            [ 'stamps_earned' => intval( $total ) ],
            [ 'id' => $profile_id ],
            [ '%d' ], [ '%d' ]
        );

        return [
            'recorded'           => true,
            'was_already_complete' => $was_complete_before,
            'message'            => '📍 Location found: ' . esc_html( $location['location_name'] ) . '!',
        ];
    }

    private function record_completion_stamp( $profile_id, $event_slug ) {
        global $wpdb;

        // Only record once per event
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}oha_event_stamps
             WHERE actor_one_id = %d AND stamp_type = 'scavenger_complete' AND event_slug = %s",
            $profile_id, $event_slug
        ) );
        if ( intval( $exists ) > 0 ) return;

        $wpdb->insert(
            $wpdb->prefix . 'oha_event_stamps',
            [
                'actor_one_id' => $profile_id,
                'actor_two_id' => 0,
                'event_slug'   => $event_slug,
                'event_date'   => current_time( 'Y-m-d' ),
                'stamp_type'   => 'scavenger_complete',
                'location'     => 'Hunt Complete',
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s' ]
        );

        // Bump stamp counter again
        $total = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}oha_event_stamps
             WHERE actor_one_id = %d OR actor_two_id = %d",
            $profile_id, $profile_id
        ) );
        $wpdb->update(
            $wpdb->prefix . 'oha_actor_profiles',
            [ 'stamps_earned' => intval( $total ) ],
            [ 'id' => $profile_id ],
            [ '%d' ], [ '%d' ]
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Data accessors (public for shortcodes + admin)
    // ─────────────────────────────────────────────────────────────────────

    public function get_locations_for_event( $event_slug ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}oha_scavenger_locations
                 WHERE event_slug = %s AND is_active = 1
                 ORDER BY sort_order ASC, id ASC",
                $event_slug
            ),
            ARRAY_A
        );
    }

    public function get_location_by_id( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}oha_scavenger_locations WHERE id = %d",
                $id
            ),
            ARRAY_A
        );
    }

    public function get_location_by_hash( $qr_hash ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}oha_scavenger_locations
                 WHERE qr_hash = %s AND is_active = 1",
                $qr_hash
            ),
            ARRAY_A
        );
    }

    /**
     * Returns found/total counts and completion status for a profile+event pair.
     */
    public function get_user_progress( $profile_id, $event_slug ) {
        global $wpdb;

        $total = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}oha_scavenger_locations
             WHERE event_slug = %s AND is_active = 1",
            $event_slug
        ) );

        $found = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}oha_event_stamps
             WHERE actor_one_id = %d AND stamp_type = 'location' AND event_slug = %s",
            $profile_id, $event_slug
        ) );

        $total = intval( $total );
        $found = intval( $found );

        return [
            'found'       => $found,
            'total'       => $total,
            'remaining'   => max( 0, $total - $found ),
            'percent'     => $total > 0 ? round( ( $found / $total ) * 100 ) : 0,
            'is_complete' => ( $total > 0 && $found >= $total ),
        ];
    }

    /**
     * Returns array of scavenger_location IDs that a profile has already stamped.
     */
    public function get_scanned_location_ids( $profile_id, $event_slug ) {
        global $wpdb;
        $rows = $wpdb->get_col( $wpdb->prepare(
            "SELECT actor_two_id FROM {$wpdb->prefix}oha_event_stamps
             WHERE actor_one_id = %d AND stamp_type = 'location' AND event_slug = %s",
            $profile_id, $event_slug
        ) );
        return array_map( 'intval', $rows );
    }

    public function create_location( $name, $hint, $description, $event_slug, $sort_order = 0 ) {
        global $wpdb;

        // Generate a stable, short hash for this location
        $qr_hash = substr( hash( 'sha256', $event_slug . $name . $sort_order . microtime() ), 0, 32 );

        $result = $wpdb->insert(
            $wpdb->prefix . 'oha_scavenger_locations',
            [
                'event_slug'           => $event_slug,
                'location_name'        => $name,
                'location_hint'        => $hint,
                'location_description' => $description,
                'qr_hash'              => $qr_hash,
                'sort_order'           => $sort_order,
                'is_active'            => 1,
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%d', '%d' ]
        );
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Returns the QR image URL for printing/posting.
     * QR data:  LOC:{qr_hash}
     */
    public function get_location_qr_url( $qr_hash ) {
        return 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query( [
            'size'   => '400x400',
            'data'   => 'LOC:' . $qr_hash,
            'format' => 'png',
            'margin' => '10',
        ] );
    }

    /**
     * Leaderboard — top hunters by number of locations found for a given event.
     */
    public function get_hunt_leaderboard( $event_slug, $limit = 20 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT ap.character_name, ap.haunt_name,
                    COUNT(*) as locations_found
             FROM {$wpdb->prefix}oha_event_stamps s
             JOIN {$wpdb->prefix}oha_actor_profiles ap ON s.actor_one_id = ap.id
             WHERE s.stamp_type = 'location' AND s.event_slug = %s
             GROUP BY s.actor_one_id
             ORDER BY locations_found DESC, s.created_at ASC
             LIMIT %d",
            $event_slug, $limit
        ) );
    }
}
