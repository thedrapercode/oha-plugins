<?php
/**
 * OHA Passport Hub — Vendor QR Check-in
 *
 * Manages vendor booth QR codes and attendee check-ins.
 * Admin creates a vendor record and downloads/prints the static QR code to post at the booth.
 * Attendees scan the booth QR → see vendor profile → a 'vendor' stamp is recorded.
 *
 * QR data format:  VND:{vendor_db_id}
 * Table:           {prefix}oha_vendor_qrs
 * Stamp type:      'vendor'  in oha_event_stamps
 *
 * @package OHA_Passport_Hub
 * @version 1.2.0
 */

defined( 'ABSPATH' ) || exit;

class OHA_Passport_Vendors {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action( 'wp_ajax_oha_scan_vendor_qr',         [ $this, 'ajax_scan_vendor_qr' ] );
        add_action( 'wp_ajax_nopriv_oha_scan_vendor_qr',  [ $this, 'ajax_scan_vendor_qr' ] );
        add_action( 'wp_ajax_oha_get_vendor_info',        [ $this, 'ajax_get_vendor_info' ] );
        add_action( 'wp_ajax_nopriv_oha_get_vendor_info', [ $this, 'ajax_get_vendor_info' ] );
        // Admin CRUD
        add_action( 'wp_ajax_oha_admin_save_vendor',      [ $this, 'ajax_admin_save_vendor' ] );
        add_action( 'wp_ajax_oha_admin_delete_vendor',    [ $this, 'ajax_admin_delete_vendor' ] );
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX: Scan vendor QR — shows info + optionally records a stamp
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Called when a user scans a vendor QR code.
     * QR data is "VND:{vendor_id}", so vendor_id is passed directly.
     * If the user has a passport profile, records a 'vendor' stamp.
     */
    public function ajax_scan_vendor_qr() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', OHA_PH_NONCE ) &&
             ! wp_verify_nonce( $_POST['nonce'] ?? '', 'oha_actor_passport_nonce' ) ) {
            wp_send_json_error( 'Invalid request' );
        }

        $vendor_id = intval( $_POST['vendor_id'] ?? 0 );
        if ( ! $vendor_id ) {
            wp_send_json_error( 'Invalid vendor QR code' );
        }

        $vendor = $this->get_vendor_by_id( $vendor_id );
        if ( ! $vendor ) {
            wp_send_json_error( 'Vendor not found — this QR code may be outdated' );
        }

        $response = [
            'vendor'     => [
                'id'          => intval( $vendor['id'] ),
                'vendor_name' => $vendor['vendor_name'],
                'description' => $vendor['description'],
                'website_url' => $vendor['website_url'],
                'booth_number'=> $vendor['booth_number'],
            ],
            'stamp_recorded' => false,
            'message'        => '',
        ];

        // Only stamp if the user is logged in and has a profile
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $profile = OHA_Passport_Profiles::get_instance()->get_profile_by_user_id( $user_id );

            if ( $profile ) {
                $stamp_result = $this->maybe_record_vendor_stamp( $profile['id'], $vendor );
                $response['stamp_recorded'] = $stamp_result['recorded'];
                $response['message']        = $stamp_result['message'];
            } else {
                $response['message'] = 'Create an Actor Passport to earn a check-in stamp here!';
            }
        } else {
            $response['message'] = 'Log in and create an Actor Passport to earn stamps!';
        }

        wp_send_json_success( $response );
    }

    /**
     * Public endpoint — returns vendor info without stamping (for preview cards, etc.)
     */
    public function ajax_get_vendor_info() {
        $vendor_id = intval( $_REQUEST['vendor_id'] ?? 0 );
        if ( ! $vendor_id ) wp_send_json_error( 'Invalid vendor ID' );

        $vendor = $this->get_vendor_by_id( $vendor_id );
        if ( ! $vendor ) wp_send_json_error( 'Vendor not found' );

        wp_send_json_success( [
            'id'          => intval( $vendor['id'] ),
            'vendor_name' => $vendor['vendor_name'],
            'description' => $vendor['description'],
            'website_url' => $vendor['website_url'],
            'booth_number'=> $vendor['booth_number'],
            'qr_url'      => $this->get_vendor_qr_url( $vendor['id'] ),
        ] );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Admin AJAX
    // ─────────────────────────────────────────────────────────────────────

    public function ajax_admin_save_vendor() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', OHA_PH_NONCE ) ) wp_send_json_error( 'Invalid request' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        $vendor_name  = sanitize_text_field( $_POST['vendor_name']  ?? '' );
        $description  = sanitize_textarea_field( $_POST['description'] ?? '' );
        $website_url  = esc_url_raw( $_POST['website_url'] ?? '' );
        $booth_number = sanitize_text_field( $_POST['booth_number'] ?? '' );
        $event_slug   = sanitize_text_field( $_POST['event_slug']   ?? '' );

        if ( empty( $vendor_name ) ) wp_send_json_error( 'Vendor name is required' );
        if ( empty( $event_slug ) )  wp_send_json_error( 'Event is required' );

        $result = $this->create_vendor( $vendor_name, $description, $website_url, $booth_number, $event_slug );
        if ( ! $result ) wp_send_json_error( 'Failed to save vendor' );

        wp_send_json_success( [
            'id'      => $result,
            'qr_url'  => $this->get_vendor_qr_url( $result ),
            'qr_data' => 'VND:' . $result,
        ] );
    }

    public function ajax_admin_delete_vendor() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', OHA_PH_NONCE ) ) wp_send_json_error( 'Invalid request' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        $id = intval( $_POST['id'] ?? 0 );
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'oha_vendor_qrs',
            [ 'is_active' => 0 ],
            [ 'id' => $id ],
            [ '%d' ], [ '%d' ]
        );
        wp_send_json_success();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Stamp logic
    // ─────────────────────────────────────────────────────────────────────

    private function maybe_record_vendor_stamp( $profile_id, $vendor ) {
        global $wpdb;

        $event  = OHA_Passport_QR_Manager::get_instance()->get_current_event();
        $today  = current_time( 'Y-m-d' );

        // Check if already checked in today at this vendor
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}oha_event_stamps
             WHERE actor_one_id = %d
               AND actor_two_id = %d
               AND stamp_type   = 'vendor'
               AND event_slug   = %s
               AND event_date   = %s",
            $profile_id, intval( $vendor['id'] ), $event['slug'], $today
        ) );

        if ( intval( $exists ) > 0 ) {
            return [
                'recorded' => false,
                'message'  => 'Already checked in at ' . esc_html( $vendor['vendor_name'] ) . ' today! ✅',
            ];
        }

        $label = $vendor['vendor_name'] . ( $vendor['booth_number'] ? ' (Booth ' . $vendor['booth_number'] . ')' : '' );

        $result = $wpdb->insert(
            $wpdb->prefix . 'oha_event_stamps',
            [
                'actor_one_id' => $profile_id,
                'actor_two_id' => intval( $vendor['id'] ),
                'event_slug'   => $event['slug'],
                'event_date'   => $today,
                'stamp_type'   => 'vendor',
                'location'     => substr( $label, 0, 100 ),
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s' ]
        );

        if ( $result === false ) {
            return [ 'recorded' => false, 'message' => 'Check-in failed — please try again' ];
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
            'recorded' => true,
            'message'  => '🏪 Check-in stamp earned at ' . esc_html( $vendor['vendor_name'] ) . '!',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Data accessors (public for admin + shortcodes)
    // ─────────────────────────────────────────────────────────────────────

    public function get_vendor_by_id( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}oha_vendor_qrs WHERE id = %d AND is_active = 1",
                $id
            ),
            ARRAY_A
        );
    }

    public function get_all_vendors( $event_slug = '' ) {
        global $wpdb;
        if ( $event_slug ) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}oha_vendor_qrs WHERE event_slug = %s AND is_active = 1 ORDER BY vendor_name ASC",
                    $event_slug
                ),
                ARRAY_A
            );
        }
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}oha_vendor_qrs WHERE is_active = 1 ORDER BY event_slug ASC, vendor_name ASC",
            ARRAY_A
        );
    }

    public function create_vendor( $vendor_name, $description, $website_url, $booth_number, $event_slug ) {
        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'oha_vendor_qrs',
            [
                'vendor_name'  => $vendor_name,
                'description'  => $description,
                'website_url'  => $website_url,
                'booth_number' => $booth_number,
                'event_slug'   => $event_slug,
                'is_active'    => 1,
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%d' ]
        );
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Returns the QR code image URL for a vendor.
     * The QR data embedded is "VND:{vendor_id}" so the scanner can identify it.
     */
    public function get_vendor_qr_url( $vendor_id ) {
        return 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query( [
            'size'   => '300x300',
            'data'   => 'VND:' . intval( $vendor_id ),
            'format' => 'png',
            'margin' => '10',
        ] );
    }

    /**
     * Returns how many distinct vendors a profile has checked into for a given event.
     */
    public function get_vendor_checkin_count( $profile_id, $event_slug ) {
        global $wpdb;
        return intval( $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}oha_event_stamps
             WHERE actor_one_id = %d AND stamp_type = 'vendor' AND event_slug = %s",
            $profile_id, $event_slug
        ) ) );
    }
}
