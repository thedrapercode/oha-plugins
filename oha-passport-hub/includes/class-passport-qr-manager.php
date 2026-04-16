<?php
/**
 * OHA Passport Hub — QR Manager
 *
 * Handles QR code scanning, badge trading, and trading rules.
 * Extracted from OHA Convention PWA Core v3.4.1 class-pwa-qr-manager.php.
 * Now uses OHA_Passport_Events for active-event checks instead of hardcoded dates.
 *
 * @package OHA_Passport_Hub
 * @version 1.1.0
 */

defined( 'ABSPATH' ) || exit;

class OHA_Passport_QR_Manager {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action( 'wp_ajax_oha_process_qr_scan',   [ $this, 'ajax_process_qr_scan' ] );
        add_action( 'wp_ajax_oha_validate_qr_code',  [ $this, 'ajax_validate_qr_code' ] );
        add_action( 'wp_ajax_oha_check_event_active', [ $this, 'ajax_check_event_active' ] );
        add_action( 'wp_ajax_oha_get_qr_data',       [ $this, 'ajax_get_qr_data' ] );
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX handlers
    // ─────────────────────────────────────────────────────────────────────

    public function ajax_process_qr_scan() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', OHA_PH_NONCE ) &&
             ! wp_verify_nonce( $_POST['nonce'] ?? '', 'oha_actor_passport_nonce' ) ) {
            wp_send_json_error( 'Invalid request' );
        }
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in to scan QR codes' );
        }

        $scanner_user_id  = get_current_user_id();
        $scanner_profile  = OHA_Passport_Profiles::get_instance()->get_profile_by_user_id( $scanner_user_id );

        if ( ! $scanner_profile ) {
            wp_send_json_error( 'You need to create an actor profile before scanning QR codes' );
        }

        $qr_hash = sanitize_text_field( $_POST['qr_hash'] ?? '' );
        if ( empty( $qr_hash ) ) wp_send_json_error( 'Invalid QR code data' );

        $target_profile = OHA_Passport_Profiles::get_instance()->get_profile_by_qr_hash( $qr_hash );
        if ( ! $target_profile ) wp_send_json_error( 'QR code not found or expired' );

        if ( $target_profile['user_id'] == $scanner_user_id ) {
            wp_send_json_error( 'You cannot scan your own QR code!' );
        }

        if ( ! $this->is_event_active() ) {
            wp_send_json_error( 'Badge trading is only available during active OHA events' );
        }

        if ( $this->stamp_exists_today( $scanner_profile['id'], $target_profile['id'] ) ) {
            wp_send_json_error( 'You have already traded badges with ' . esc_html( $target_profile['character_name'] ) . ' today' );
        }

        if ( $this->check_daily_scan_limit( $scanner_profile['id'] ) ) {
            wp_send_json_error( 'Daily scan limit reached (50 per day). Try again tomorrow!' );
        }

        $stamp_result = $this->create_stamp( $scanner_profile['id'], $target_profile['id'] );
        if ( $stamp_result === false ) wp_send_json_error( 'Failed to create badge stamp' );

        $this->update_stamp_counter( $scanner_profile['id'] );
        $this->update_stamp_counter( $target_profile['id'] );

        do_action( 'oha_passport_stamp_awarded', $scanner_user_id );
        do_action( 'oha_passport_stamp_awarded', intval( $target_profile['user_id'] ) );

        wp_send_json_success( [
            'message'          => 'Badge trade successful!',
            'target_character' => $target_profile['character_name'],
            'target_haunt'     => $target_profile['haunt_name'],
            'stamp_id'         => $stamp_result,
            'new_total'        => $this->get_stamp_count( $scanner_profile['id'] ),
        ] );
    }

    public function ajax_validate_qr_code() {
        if ( ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', OHA_PH_NONCE ) &&
             ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', 'oha_actor_passport_nonce' ) ) {
            wp_send_json_error( 'Invalid request' );
        }

        $qr_hash = sanitize_text_field( $_REQUEST['qr_hash'] ?? '' );
        if ( empty( $qr_hash ) ) wp_send_json_error( 'Invalid QR code' );

        $profile = OHA_Passport_Profiles::get_instance()->get_profile_by_qr_hash( $qr_hash );
        if ( ! $profile ) {
            wp_send_json_success( [ 'valid' => false, 'message' => 'QR code not found or expired' ] );
        } else {
            wp_send_json_success( [
                'valid'          => true,
                'character_name' => $profile['character_name'],
                'haunt_name'     => $profile['haunt_name'],
                'character_type' => $profile['character_type'],
            ] );
        }
    }

    public function ajax_check_event_active() {
        if ( ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', OHA_PH_NONCE ) &&
             ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', 'oha_actor_passport_nonce' ) ) {
            wp_send_json_error( 'Invalid request' );
        }

        $is_active     = $this->is_event_active();
        $current_event = $this->get_current_event();

        wp_send_json_success( [
            'is_active' => $is_active,
            'event'     => $current_event,
            'message'   => $is_active
                ? 'Event is active — badge trading enabled!'
                : 'No active event — badge trading disabled',
        ] );
    }

    public function ajax_get_qr_data() {
        if ( ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', OHA_PH_NONCE ) &&
             ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', 'oha_actor_passport_nonce' ) ) {
            wp_send_json_error( 'Invalid request' );
        }
        if ( ! is_user_logged_in() ) wp_send_json_error( 'You must be logged in' );

        $user_id = get_current_user_id();
        $profile = OHA_Passport_Profiles::get_instance()->get_profile_by_user_id( $user_id );
        if ( ! $profile ) wp_send_json_error( 'No profile found' );

        $qr_expired = strtotime( $profile['qr_expires_at'] ) < time();
        if ( $qr_expired ) {
            $new_hash = $this->generate_qr_hash( $user_id );
            $this->update_qr_hash( $profile['id'], $new_hash );
            $profile['qr_code_hash'] = $new_hash;
        }

        wp_send_json_success( [
            'qr_hash'        => $profile['qr_code_hash'],
            'qr_url'         => $this->generate_qr_code_url( $profile['qr_code_hash'] ),
            'expires_at'     => $profile['qr_expires_at'],
            'character_name' => $profile['character_name'],
            'was_refreshed'  => $qr_expired,
        ] );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Public event-status methods (called by Stamps class and Admin)
    // ─────────────────────────────────────────────────────────────────────

    public function is_event_active() {
        return OHA_Passport_Events::get_instance()->is_any_event_active();
    }

    public function get_current_event() {
        $event = OHA_Passport_Events::get_instance()->get_active_event();
        if ( $event ) {
            return [
                'slug'     => $event['slug'],
                'name'     => $event['event_name'],
                'dates'    => $event['start_date'] . ' – ' . $event['end_date'],
                'location' => $event['location'] ?? '',
            ];
        }
        return [ 'slug' => 'none', 'name' => 'No Active Event', 'dates' => '', 'location' => '' ];
    }

    public function get_event_statistics( $event_slug = '' ) {
        global $wpdb;
        $stamps_table   = $wpdb->prefix . 'oha_event_stamps';
        $profiles_table = $wpdb->prefix . 'oha_actor_profiles';

        // Default to the current active event if no slug given
        if ( empty( $event_slug ) ) {
            $current = $this->get_current_event();
            $event_slug = $current['slug'] !== 'none' ? $current['slug'] : 'oha-convention-2026';
        }

        $total_trades = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$stamps_table} WHERE event_slug = %s", $event_slug
        ) );

        $unique_participants = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT participant) FROM (
                SELECT actor_one_id as participant FROM {$stamps_table} WHERE event_slug = %s
                UNION
                SELECT actor_two_id as participant FROM {$stamps_table} WHERE event_slug = %s
             ) as p",
            $event_slug, $event_slug
        ) );

        $haunts_represented = $wpdb->get_results( $wpdb->prepare(
            "SELECT haunt_name, COUNT(*) as actor_count
             FROM {$profiles_table} p
             WHERE p.id IN (
                 SELECT DISTINCT participant FROM (
                     SELECT actor_one_id as participant FROM {$stamps_table} WHERE event_slug = %s
                     UNION
                     SELECT actor_two_id as participant FROM {$stamps_table} WHERE event_slug = %s
                 ) as participants
             ) AND haunt_name IS NOT NULL AND haunt_name != ''
             GROUP BY haunt_name
             ORDER BY actor_count DESC",
            $event_slug, $event_slug
        ) );

        return [
            'total_trades'        => intval( $total_trades ),
            'unique_participants' => intval( $unique_participants ),
            'haunts_count'        => count( $haunts_represented ),
            'haunts_represented'  => $haunts_represented,
            'event_slug'          => $event_slug,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────

    private function stamp_exists_today( $actor_one_id, $actor_two_id ) {
        global $wpdb;
        $today      = current_time( 'Y-m-d' );
        $event      = $this->get_current_event();
        $event_slug = $event['slug'] ?? 'none';

        return intval( $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}oha_event_stamps
             WHERE ((actor_one_id = %d AND actor_two_id = %d)
                 OR (actor_one_id = %d AND actor_two_id = %d))
               AND event_date  = %s
               AND event_slug  = %s",
            $actor_one_id, $actor_two_id, $actor_two_id, $actor_one_id, $today, $event_slug
        ) ) ) > 0;
    }

    private function create_stamp( $actor_one_id, $actor_two_id ) {
        global $wpdb;
        $event = $this->get_current_event();
        $result = $wpdb->insert(
            $wpdb->prefix . 'oha_event_stamps',
            [
                'actor_one_id' => $actor_one_id,
                'actor_two_id' => $actor_two_id,
                'event_slug'   => $event['slug'],
                'event_date'   => current_time( 'Y-m-d' ),
                'stamp_type'   => 'trade',
            ],
            [ '%d', '%d', '%s', '%s', '%s' ]
        );
        return $result ? $wpdb->insert_id : false;
    }

    private function update_stamp_counter( $profile_id ) {
        global $wpdb;
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

    private function get_stamp_count( $profile_id ) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}oha_event_stamps
             WHERE actor_one_id = %d OR actor_two_id = %d",
            $profile_id, $profile_id
        ) );
    }

    private function check_daily_scan_limit( $profile_id ) {
        global $wpdb;
        $today      = current_time( 'Y-m-d' );
        $event      = $this->get_current_event();
        $event_slug = $event['slug'] ?? 'none';

        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}oha_event_stamps
             WHERE actor_one_id = %d
               AND event_date   = %s
               AND event_slug   = %s",
            $profile_id, $today, $event_slug
        ) );
        return intval( $count ) >= 50;
    }

    private function generate_qr_hash( $user_id ) {
        return hash( 'sha256', $user_id . time() . wp_generate_password( 12, false ) );
    }

    private function update_qr_hash( $profile_id, $new_hash ) {
        global $wpdb;
        return $wpdb->update(
            $wpdb->prefix . 'oha_actor_profiles',
            [ 'qr_code_hash' => $new_hash, 'qr_expires_at' => date( 'Y-m-d H:i:s', strtotime( '+24 hours' ) ) ],
            [ 'id' => $profile_id ],
            [ '%s', '%s' ], [ '%d' ]
        );
    }

    private function generate_qr_code_url( $qr_hash ) {
        return 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query( [
            'size'   => '300x300',
            'data'   => $qr_hash,
            'format' => 'png',
            'margin' => '10',
        ] );
    }

    public static function enable_testing_mode( $enable = true ) {
        update_option( 'oha_passport_testing_mode', $enable );
    }
}
