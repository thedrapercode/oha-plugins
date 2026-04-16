<?php
/**
 * OHA Passport Hub — Stamps & Stats
 *
 * Manages stamp collections, leaderboards, and CSV export.
 * Extracted from OHA Convention PWA Core v3.4.1 class-pwa-event-stamps.php.
 *
 * @package OHA_Passport_Hub
 * @version 1.1.0
 */

defined( 'ABSPATH' ) || exit;

class OHA_Passport_Stamps {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action( 'wp_ajax_oha_get_my_stamps',        [ $this, 'ajax_get_my_stamps' ] );
        add_action( 'wp_ajax_oha_get_stamp_leaderboard', [ $this, 'ajax_get_leaderboard' ] );
        add_action( 'wp_ajax_oha_get_event_stats',       [ $this, 'ajax_get_event_stats' ] );
        add_action( 'wp_ajax_oha_export_stamps_csv',     [ $this, 'ajax_export_stamps_csv' ] );
        add_action( 'wp_ajax_oha_award_demo_stamp',      [ $this, 'ajax_award_demo_stamp' ] );
        add_action( 'wp_ajax_oha_get_connections',        [ $this, 'ajax_get_connections' ] );
    }

    public function ajax_get_my_stamps() {
        if ( ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', OHA_PH_NONCE ) &&
             ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', 'oha_actor_passport_nonce' ) ) {
            wp_send_json_error( 'Invalid request' );
        }
        if ( ! is_user_logged_in() ) wp_send_json_error( 'You must be logged in' );

        $user_id = get_current_user_id();
        $profile = OHA_Passport_Profiles::get_instance()->get_profile_by_user_id( $user_id );
        if ( ! $profile ) wp_send_json_error( 'No profile found' );

        wp_send_json_success( [
            'stamps'  => $this->get_user_stamps( $profile['id'] ),
            'stats'   => $this->get_user_stamp_stats( $profile['id'] ),
            'profile' => [
                'character_name' => $profile['character_name'],
                'haunt_name'     => $profile['haunt_name'],
            ],
        ] );
    }

    public function ajax_get_leaderboard() {
        if ( ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', OHA_PH_NONCE ) &&
             ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', 'oha_actor_passport_nonce' ) ) {
            wp_send_json_error( 'Invalid request' );
        }
        wp_send_json_success( $this->get_simple_leaderboard() );
    }

    public function ajax_get_event_stats() {
        if ( ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', OHA_PH_NONCE ) &&
             ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', 'oha_actor_passport_nonce' ) ) {
            wp_send_json_error( 'Invalid request' );
        }
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        $event_slug = sanitize_text_field( $_REQUEST['event_slug'] ?? '' );
        wp_send_json_success( OHA_Passport_QR_Manager::get_instance()->get_event_statistics( $event_slug ) );
    }

    /**
     * Award the one-time pre-event "OHA Beginner" demo stamp.
     * Intentionally bypasses is_event_active() — works before convention day.
     * One demo stamp per user (stamp_type='demo', event_slug='pre-event').
     */
    public function ajax_award_demo_stamp() {
        if ( ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', OHA_PH_NONCE ) &&
             ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', 'oha_actor_passport_nonce' ) ) {
            wp_send_json_error( 'Invalid request' );
        }
        if ( ! is_user_logged_in() ) wp_send_json_error( 'You must be logged in' );

        $user_id = get_current_user_id();
        $profile = OHA_Passport_Profiles::get_instance()->get_profile_by_user_id( $user_id );
        if ( ! $profile ) wp_send_json_error( 'Create your Passport profile first' );

        global $wpdb;
        $st = $wpdb->prefix . 'oha_event_stamps';

        $already = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$st} WHERE actor_one_id = %d AND stamp_type = 'demo' AND event_slug = 'pre-event'",
            $profile['id']
        ) );
        if ( $already > 0 ) {
            wp_send_json_error( 'demo_already_claimed' );
        }

        $result = $wpdb->insert( $st, [
            'actor_one_id' => $profile['id'],
            'actor_two_id' => 0,
            'event_slug'   => 'pre-event',
            'event_date'   => current_time( 'Y-m-d' ),
            'stamp_type'   => 'demo',
        ], [ '%d', '%d', '%s', '%s', '%s' ] );

        if ( $result === false ) wp_send_json_error( 'Failed to award demo stamp' );

        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$st} WHERE actor_one_id = %d OR actor_two_id = %d",
            $profile['id'], $profile['id']
        ) );
        $wpdb->update(
            $wpdb->prefix . 'oha_actor_profiles',
            [ 'stamps_earned' => $total ],
            [ 'id' => $profile['id'] ],
            [ '%d' ],
            [ '%d' ]
        );

        do_action( 'oha_passport_stamp_awarded', get_current_user_id() );
        wp_send_json_success( [
            'message'  => 'OHA Beginner stamp awarded!',
            'stamp_id' => $wpdb->insert_id,
            'new_total'=> $total,
        ] );
    }

    public function ajax_export_stamps_csv() {
        if ( ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', OHA_PH_NONCE ) &&
             ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', 'oha_actor_passport_nonce' ) ) {
            wp_send_json_error( 'Invalid request' );
        }
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        $event_slug = sanitize_text_field( $_REQUEST['event_slug'] ?? '' );
        wp_send_json_success( [
            'csv_data' => $this->generate_stamps_csv( $event_slug ),
            'filename' => ( $event_slug ?: 'all-events' ) . '-stamps-' . date( 'Y-m-d' ) . '.csv',
        ] );
    }

    public function ajax_get_connections() {
        if ( ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', OHA_PH_NONCE ) &&
             ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', 'oha_actor_passport_nonce' ) ) {
            wp_send_json_error( 'Invalid request' );
        }
        if ( ! is_user_logged_in() ) wp_send_json_error( 'You must be logged in' );

        $limit   = max( 1, min( 50, intval( $_REQUEST['limit'] ?? 10 ) ) );
        $user_id = get_current_user_id();
        $profile = OHA_Passport_Profiles::get_instance()->get_profile_by_user_id( $user_id );
        if ( ! $profile ) wp_send_json_error( 'No profile found' );

        global $wpdb;
        $st = $wpdb->prefix . 'oha_event_stamps';
        $pt = $wpdb->prefix . 'oha_actor_profiles';
        $pid = intval( $profile['id'] );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT
                CASE WHEN s.actor_one_id = %d THEN ap2.character_name ELSE ap1.character_name END AS character_name,
                CASE WHEN s.actor_one_id = %d THEN ap2.haunt_name     ELSE ap1.haunt_name     END AS haunt_name,
                MAX(s.created_at) AS last_seen
             FROM {$st} s
             LEFT JOIN {$pt} ap1 ON s.actor_one_id = ap1.id
             LEFT JOIN {$pt} ap2 ON s.actor_two_id = ap2.id
             WHERE (s.actor_one_id = %d OR s.actor_two_id = %d) AND s.stamp_type = 'trade'
             GROUP BY
                CASE WHEN s.actor_one_id = %d THEN s.actor_two_id ELSE s.actor_one_id END
             ORDER BY last_seen DESC
             LIMIT %d",
            $pid, $pid, $pid, $pid, $pid, $limit
        ) );

        wp_send_json_success( $rows );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Data methods
    // ─────────────────────────────────────────────────────────────────────

    public function get_user_stamps( $profile_id ) {
        global $wpdb;
        $st = $wpdb->prefix . 'oha_event_stamps';
        $pt = $wpdb->prefix . 'oha_actor_profiles';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT s.*,
                CASE WHEN s.actor_one_id = %d THEN ap2.character_name ELSE ap1.character_name END as partner_name,
                CASE WHEN s.actor_one_id = %d THEN ap2.haunt_name     ELSE ap1.haunt_name     END as partner_haunt,
                CASE WHEN s.actor_one_id = %d THEN ap2.character_type ELSE ap1.character_type END as partner_type
             FROM {$st} s
             LEFT JOIN {$pt} ap1 ON s.actor_one_id = ap1.id
             LEFT JOIN {$pt} ap2 ON s.actor_two_id = ap2.id
             WHERE s.actor_one_id = %d OR s.actor_two_id = %d
             ORDER BY s.created_at DESC",
            $profile_id, $profile_id, $profile_id, $profile_id, $profile_id
        ) );
    }

    public function get_user_stamp_stats( $profile_id ) {
        global $wpdb;
        $st = $wpdb->prefix . 'oha_event_stamps';
        $pt = $wpdb->prefix . 'oha_actor_profiles';

        $total = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$st} WHERE actor_one_id = %d OR actor_two_id = %d",
            $profile_id, $profile_id
        ) );
        $unique_partners = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT CASE WHEN actor_one_id = %d THEN actor_two_id ELSE actor_one_id END)
             FROM {$st} WHERE actor_one_id = %d OR actor_two_id = %d",
            $profile_id, $profile_id, $profile_id
        ) );
        $different_haunts = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT
                CASE WHEN s.actor_one_id = %d THEN ap2.haunt_name ELSE ap1.haunt_name END)
             FROM {$st} s
             LEFT JOIN {$pt} ap1 ON s.actor_one_id = ap1.id
             LEFT JOIN {$pt} ap2 ON s.actor_two_id = ap2.id
             WHERE (s.actor_one_id = %d OR s.actor_two_id = %d)
               AND CASE WHEN s.actor_one_id = %d THEN ap2.haunt_name ELSE ap1.haunt_name END IS NOT NULL",
            $profile_id, $profile_id, $profile_id, $profile_id
        ) );
        $stamps_by_day = $wpdb->get_results( $wpdb->prepare(
            "SELECT event_date, COUNT(*) as count FROM {$st}
             WHERE actor_one_id = %d OR actor_two_id = %d
             GROUP BY event_date ORDER BY event_date DESC",
            $profile_id, $profile_id
        ) );
        $stamps_by_event = $wpdb->get_results( $wpdb->prepare(
            "SELECT event_slug, COUNT(*) as count FROM {$st}
             WHERE actor_one_id = %d OR actor_two_id = %d
             GROUP BY event_slug ORDER BY count DESC",
            $profile_id, $profile_id
        ) );

        return [
            'total_stamps'    => intval( $total ),
            'unique_partners' => intval( $unique_partners ),
            'different_haunts'=> intval( $different_haunts ),
            'stamps_by_day'   => $stamps_by_day,
            'stamps_by_event' => $stamps_by_event,
        ];
    }

    public function get_simple_leaderboard( $limit = 20, $event_slug = '' ) {
        global $wpdb;
        $st = $wpdb->prefix . 'oha_event_stamps';
        $pt = $wpdb->prefix . 'oha_actor_profiles';

        $where_event = $event_slug
            ? $wpdb->prepare( 'AND s.event_slug = %s', $event_slug )
            : '';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT ap.character_name, ap.haunt_name, ap.character_type, ap.stamps_earned,
                COUNT(DISTINCT
                    CASE WHEN s.actor_one_id = ap.id THEN s.actor_two_id ELSE s.actor_one_id END
                ) as unique_partners
             FROM {$pt} ap
             LEFT JOIN {$st} s ON (s.actor_one_id = ap.id OR s.actor_two_id = ap.id) {$where_event}
             WHERE ap.is_active = 1 AND ap.stamps_earned > 0
             GROUP BY ap.id
             ORDER BY ap.stamps_earned DESC, unique_partners DESC
             LIMIT %d",
            $limit
        ) );
    }

    public function generate_stamps_csv( $event_slug = '' ) {
        global $wpdb;
        $st = $wpdb->prefix . 'oha_event_stamps';
        $pt = $wpdb->prefix . 'oha_actor_profiles';

        $where = $event_slug
            ? $wpdb->prepare( "WHERE s.event_slug = %s", $event_slug )
            : '';

        $data = $wpdb->get_results(
            "SELECT s.id, s.event_slug, s.event_date, s.stamp_type, s.created_at,
                ap1.character_name as actor_one_name, ap1.haunt_name as actor_one_haunt,
                ap2.character_name as actor_two_name, ap2.haunt_name as actor_two_haunt
             FROM {$st} s
             LEFT JOIN {$pt} ap1 ON s.actor_one_id = ap1.id
             LEFT JOIN {$pt} ap2 ON s.actor_two_id = ap2.id
             {$where}
             ORDER BY s.created_at DESC"
        );

        $csv = "Stamp ID,Event,Event Date,Stamp Type,Created At,Actor One,Actor One Haunt,Actor Two,Actor Two Haunt\n";
        foreach ( $data as $row ) {
            $csv .= sprintf(
                "%d,%s,%s,%s,%s,\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $row->id,
                $row->event_slug,
                $row->event_date,
                $row->stamp_type,
                $row->created_at,
                str_replace( '"', '""', $row->actor_one_name ?? '' ),
                str_replace( '"', '""', $row->actor_one_haunt ?? '' ),
                str_replace( '"', '""', $row->actor_two_name ?? '' ),
                str_replace( '"', '""', $row->actor_two_haunt ?? '' )
            );
        }
        return $csv;
    }
}
