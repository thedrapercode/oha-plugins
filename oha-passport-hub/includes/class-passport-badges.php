<?php
/**
 * OHA Passport Hub — Badge Engine
 *
 * Badge/achievement system for the OHA Haunt Passport.
 * Badges are defined in wp_oha_passport_badge_defs and awarded to WP users
 * in wp_oha_user_badges when unlock conditions are met.
 *
 * Unlock types:
 *   profile_created   — awarded when the user creates their first actor profile
 *   stamp_count       — awarded when total stamps >= unlock_threshold
 *   stamp_type_count  — awarded when stamps of unlock_param type >= unlock_threshold
 *   event_stamp       — awarded when user has any stamp for unlock_param event_slug
 *   manual            — admin-awarded only, never auto-evaluated
 *
 * @package OHA_Passport_Hub
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;

class OHA_Passport_Badges {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Badge evaluation hook — fired by stamps, profiles, QR manager
        add_action( 'oha_passport_stamp_awarded', [ $this, 'on_stamp_awarded' ] );

        // Frontend AJAX
        add_action( 'wp_ajax_oha_get_user_badges',  [ $this, 'ajax_get_user_badges' ] );
        add_action( 'wp_ajax_oha_evaluate_badges',  [ $this, 'ajax_evaluate_badges' ] );

        // Admin AJAX
        add_action( 'wp_ajax_oha_admin_award_badge',  [ $this, 'ajax_admin_award_badge' ] );
        add_action( 'wp_ajax_oha_save_badge_def',     [ $this, 'ajax_save_badge_def' ] );
        add_action( 'wp_ajax_oha_delete_badge_def',   [ $this, 'ajax_delete_badge_def' ] );
        add_action( 'wp_ajax_oha_get_badge_defs',     [ $this, 'ajax_get_badge_defs' ] );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Action hook handler
    // ─────────────────────────────────────────────────────────────────────

    public function on_stamp_awarded( $user_id ) {
        if ( ! $user_id ) return;
        $this->evaluate_badges( intval( $user_id ) );
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX handlers
    // ─────────────────────────────────────────────────────────────────────

    public function ajax_get_user_badges() {
        if ( ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', OHA_PH_NONCE ) &&
             ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', 'oha_actor_passport_nonce' ) ) {
            wp_send_json_error( 'Invalid request' );
        }
        if ( ! is_user_logged_in() ) wp_send_json_error( 'You must be logged in' );

        $user_id = get_current_user_id();
        wp_send_json_success( $this->get_user_badges( $user_id ) );
    }

    public function ajax_evaluate_badges() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', OHA_PH_NONCE ) &&
             ! wp_verify_nonce( $_POST['nonce'] ?? '', 'oha_actor_passport_nonce' ) ) {
            wp_send_json_error( 'Invalid request' );
        }
        if ( ! is_user_logged_in() ) wp_send_json_error( 'You must be logged in' );

        $user_id       = get_current_user_id();
        $newly_awarded = $this->evaluate_badges( $user_id );

        wp_send_json_success( [
            'newly_unlocked' => $newly_awarded,
            'count'          => count( $newly_awarded ),
        ] );
    }

    public function ajax_admin_award_badge() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', OHA_PH_NONCE ) ) {
            wp_send_json_error( 'Invalid request' );
        }
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        $target_user_id = intval( $_POST['target_user_id'] ?? 0 );
        $badge_id       = intval( $_POST['badge_id']       ?? 0 );
        $context        = sanitize_text_field( $_POST['event_context'] ?? '' );

        if ( ! $target_user_id || ! $badge_id ) {
            wp_send_json_error( 'User ID and Badge ID are required' );
        }

        $result = $this->award_badge( $target_user_id, $badge_id, $context );
        if ( $result ) {
            wp_send_json_success( [ 'message' => 'Badge awarded successfully' ] );
        } else {
            wp_send_json_error( 'Badge may already be awarded, or an error occurred' );
        }
    }

    public function ajax_save_badge_def() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', OHA_PH_NONCE ) ) {
            wp_send_json_error( 'Invalid request' );
        }
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        $id               = intval( $_POST['id'] ?? 0 );
        $badge_slug       = sanitize_title( $_POST['badge_slug']  ?? '' );
        $badge_name       = sanitize_text_field( $_POST['badge_name'] ?? '' );
        $badge_desc       = sanitize_text_field( $_POST['badge_desc'] ?? '' );
        $icon_type        = in_array( $_POST['icon_type'] ?? '', [ 'fa', 'upload' ] ) ? $_POST['icon_type'] : 'fa';
        $icon_value       = sanitize_text_field( $_POST['icon_value'] ?? 'fa-solid fa-star' );
        $icon_color       = sanitize_hex_color( $_POST['icon_color'] ?? '#8e44ad' ) ?: '#8e44ad';
        $unlock_types     = [ 'profile_created', 'stamp_count', 'stamp_type_count', 'event_stamp', 'manual' ];
        $unlock_type      = in_array( $_POST['unlock_type'] ?? '', $unlock_types ) ? $_POST['unlock_type'] : 'stamp_count';
        $unlock_threshold = intval( $_POST['unlock_threshold'] ?? 0 );
        $unlock_param     = sanitize_text_field( $_POST['unlock_param'] ?? '' );
        $sort_order       = intval( $_POST['sort_order'] ?? 0 );
        $is_active        = intval( $_POST['is_active'] ?? 1 );

        if ( empty( $badge_name ) ) wp_send_json_error( 'Badge name is required' );
        if ( empty( $badge_slug ) ) $badge_slug = sanitize_title( $badge_name );

        global $wpdb;
        $table = $wpdb->prefix . 'oha_passport_badge_defs';

        $data = [
            'badge_slug'       => $badge_slug,
            'badge_name'       => $badge_name,
            'badge_desc'       => $badge_desc,
            'icon_type'        => $icon_type,
            'icon_value'       => $icon_value,
            'icon_color'       => $icon_color,
            'unlock_type'      => $unlock_type,
            'unlock_threshold' => $unlock_threshold,
            'unlock_param'     => $unlock_param ?: null,
            'sort_order'       => $sort_order,
            'is_active'        => $is_active,
        ];
        $formats = [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d' ];

        if ( $id ) {
            $result = $wpdb->update( $table, $data, [ 'id' => $id ], $formats, [ '%d' ] );
        } else {
            $result = $wpdb->insert( $table, $data, $formats );
            $id     = $wpdb->insert_id;
        }

        if ( $result === false ) wp_send_json_error( 'Failed to save badge: ' . $wpdb->last_error );

        wp_send_json_success( [ 'id' => $id, 'message' => 'Badge saved successfully' ] );
    }

    public function ajax_delete_badge_def() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', OHA_PH_NONCE ) ) {
            wp_send_json_error( 'Invalid request' );
        }
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) wp_send_json_error( 'Badge ID is required' );

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'oha_passport_badge_defs',
            [ 'is_active' => 0 ],
            [ 'id' => $id ],
            [ '%d' ], [ '%d' ]
        );
        wp_send_json_success();
    }

    public function ajax_get_badge_defs() {
        if ( ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', OHA_PH_NONCE ) ) {
            wp_send_json_error( 'Invalid request' );
        }
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        wp_send_json_success( $this->get_all_badge_defs( false ) );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Core engine
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Evaluates all active badge definitions against the given user.
     * Awards any newly-met conditions and returns an array of badge rows
     * that were newly awarded in this call.
     */
    public function evaluate_badges( $user_id ) {
        $user_id = intval( $user_id );
        if ( ! $user_id ) return [];

        $profile       = OHA_Passport_Profiles::get_instance()->get_profile_by_user_id( $user_id );
        $badge_defs    = $this->get_all_badge_defs( true );
        $already_earned = $this->get_earned_badge_ids( $user_id );
        $newly_awarded  = [];

        foreach ( $badge_defs as $def ) {
            $def_id = intval( $def['id'] );
            if ( in_array( $def_id, $already_earned ) ) continue;

            if ( $this->check_unlock_condition( $def, $user_id, $profile ) ) {
                $awarded = $this->award_badge( $user_id, $def_id, '' );
                if ( $awarded ) {
                    $newly_awarded[] = $def;
                }
            }
        }

        return $newly_awarded;
    }

    /**
     * Checks whether a badge's unlock condition is currently met for a given user.
     */
    private function check_unlock_condition( $def, $user_id, $profile ) {
        global $wpdb;
        $st = $wpdb->prefix . 'oha_event_stamps';

        switch ( $def['unlock_type'] ) {
            case 'profile_created':
                return ! empty( $profile );

            case 'stamp_count':
                if ( ! $profile ) return false;
                $profile_id = intval( $profile['id'] );
                $count = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$st} WHERE actor_one_id = %d OR actor_two_id = %d",
                    $profile_id, $profile_id
                ) );
                return $count >= intval( $def['unlock_threshold'] );

            case 'stamp_type_count':
                if ( ! $profile || empty( $def['unlock_param'] ) ) return false;
                $profile_id = intval( $profile['id'] );
                $count = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$st}
                     WHERE (actor_one_id = %d OR actor_two_id = %d) AND stamp_type = %s",
                    $profile_id, $profile_id, $def['unlock_param']
                ) );
                return $count >= intval( $def['unlock_threshold'] );

            case 'event_stamp':
                if ( ! $profile || empty( $def['unlock_param'] ) ) return false;
                $profile_id = intval( $profile['id'] );
                $count = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$st}
                     WHERE (actor_one_id = %d OR actor_two_id = %d) AND event_slug = %s",
                    $profile_id, $profile_id, $def['unlock_param']
                ) );
                return $count > 0;

            case 'manual':
            default:
                return false;
        }
    }

    /**
     * Awards a badge to a WP user. Uses INSERT IGNORE via duplicate key handling.
     * Returns true if the badge was newly inserted, false if already existed or failed.
     */
    public function award_badge( $user_id, $badge_id, $event_context = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_user_badges';

        $result = $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO {$table} (user_id, badge_id, event_context) VALUES (%d, %d, %s)",
            intval( $user_id ), intval( $badge_id ), $event_context
        ) );

        return $result === 1; // 1 row inserted = newly awarded; 0 = duplicate
    }

    // ─────────────────────────────────────────────────────────────────────
    // Data accessors
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Returns all active badge definitions with an `is_unlocked` boolean for the given user.
     */
    public function get_user_badges( $user_id ) {
        $defs         = $this->get_all_badge_defs( true );
        $earned_ids   = $this->get_earned_badge_ids( intval( $user_id ) );

        foreach ( $defs as &$def ) {
            $def['is_unlocked'] = in_array( intval( $def['id'] ), $earned_ids );
        }
        unset( $def );

        return $defs;
    }

    /**
     * Returns all badge definitions. Pass $active_only = true to skip is_active=0 rows.
     */
    public function get_all_badge_defs( $active_only = true ) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_passport_badge_defs';
        $where = $active_only ? 'WHERE is_active = 1' : '';
        return $wpdb->get_results(
            "SELECT * FROM {$table} {$where} ORDER BY sort_order ASC, id ASC",
            ARRAY_A
        );
    }

    /**
     * Returns an array of badge_def IDs already earned by this WP user.
     */
    public function get_earned_badge_ids( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_user_badges';
        $rows  = $wpdb->get_col( $wpdb->prepare(
            "SELECT badge_id FROM {$table} WHERE user_id = %d",
            intval( $user_id )
        ) );
        return array_map( 'intval', $rows );
    }

    /**
     * Returns earned badge count and total active badge count for a user.
     */
    public function get_badge_progress( $user_id ) {
        $total   = (int) $this->count_active_badges();
        $earned  = count( $this->get_earned_badge_ids( intval( $user_id ) ) );
        return [
            'earned'  => $earned,
            'total'   => $total,
            'percent' => $total > 0 ? round( ( $earned / $total ) * 100 ) : 0,
        ];
    }

    private function count_active_badges() {
        global $wpdb;
        return $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}oha_passport_badge_defs WHERE is_active = 1" );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Default badge seeds
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Seeds the 8 default badges if the table is empty.
     * Called from oha_passport_hub_run_db_upgrade().
     * Safe to call repeatedly — checks for existing slugs before inserting.
     */
    public function seed_default_badges() {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_passport_badge_defs';

        // Only seed if table has no rows at all
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        if ( $count > 0 ) return;

        $defaults = [
            [
                'badge_slug'       => 'oha-member',
                'badge_name'       => 'OHA Member',
                'badge_desc'       => 'Created your Actor Passport and joined the OHA haunt community.',
                'icon_type'        => 'fa',
                'icon_value'       => 'fa-solid fa-id-card',
                'icon_color'       => '#c0392b',
                'unlock_type'      => 'profile_created',
                'unlock_threshold' => 0,
                'unlock_param'     => null,
                'sort_order'       => 1,
            ],
            [
                'badge_slug'       => 'first-contact',
                'badge_name'       => 'First Contact',
                'badge_desc'       => 'Traded your first badge stamp with another actor.',
                'icon_type'        => 'fa',
                'icon_value'       => 'fa-solid fa-handshake',
                'icon_color'       => '#e67e22',
                'unlock_type'      => 'stamp_count',
                'unlock_threshold' => 1,
                'unlock_param'     => null,
                'sort_order'       => 2,
            ],
            [
                'badge_slug'       => 'networker',
                'badge_name'       => 'Networker',
                'badge_desc'       => 'Collected 5 badge stamps from fellow actors.',
                'icon_type'        => 'fa',
                'icon_value'       => 'fa-solid fa-users',
                'icon_color'       => '#2980b9',
                'unlock_type'      => 'stamp_count',
                'unlock_threshold' => 5,
                'unlock_param'     => null,
                'sort_order'       => 3,
            ],
            [
                'badge_slug'       => 'social-butterfly',
                'badge_name'       => 'Social Butterfly',
                'badge_desc'       => 'Collected 10 badge stamps. You know everyone in the haunt.',
                'icon_type'        => 'fa',
                'icon_value'       => 'fa-solid fa-star',
                'icon_color'       => '#8e44ad',
                'unlock_type'      => 'stamp_count',
                'unlock_threshold' => 10,
                'unlock_param'     => null,
                'sort_order'       => 4,
            ],
            [
                'badge_slug'       => 'vendor-explorer',
                'badge_name'       => 'Vendor Explorer',
                'badge_desc'       => 'Checked in at your first vendor booth.',
                'icon_type'        => 'fa',
                'icon_value'       => 'fa-solid fa-store',
                'icon_color'       => '#27ae60',
                'unlock_type'      => 'stamp_type_count',
                'unlock_threshold' => 1,
                'unlock_param'     => 'vendor',
                'sort_order'       => 5,
            ],
            [
                'badge_slug'       => 'vendor-collector',
                'badge_name'       => 'Vendor Collector',
                'badge_desc'       => 'Checked in at 3 or more vendor booths.',
                'icon_type'        => 'fa',
                'icon_value'       => 'fa-solid fa-bag-shopping',
                'icon_color'       => '#16a085',
                'unlock_type'      => 'stamp_type_count',
                'unlock_threshold' => 3,
                'unlock_param'     => 'vendor',
                'sort_order'       => 6,
            ],
            [
                'badge_slug'       => 'on-the-hunt',
                'badge_name'       => 'On the Hunt',
                'badge_desc'       => 'Found your first scavenger hunt location.',
                'icon_type'        => 'fa',
                'icon_value'       => 'fa-solid fa-map-location-dot',
                'icon_color'       => '#d35400',
                'unlock_type'      => 'stamp_type_count',
                'unlock_threshold' => 1,
                'unlock_param'     => 'location',
                'sort_order'       => 7,
            ],
            [
                'badge_slug'       => 'convention-ready',
                'badge_name'       => 'Convention Ready',
                'badge_desc'       => 'Claimed your pre-event Passport demo stamp.',
                'icon_type'        => 'fa',
                'icon_value'       => 'fa-solid fa-certificate',
                'icon_color'       => '#f39c12',
                'unlock_type'      => 'stamp_type_count',
                'unlock_threshold' => 1,
                'unlock_param'     => 'demo',
                'sort_order'       => 8,
            ],
        ];

        foreach ( $defaults as $badge ) {
            $badge['is_active'] = 1;
            $formats = [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d' ];
            $wpdb->insert( $table, $badge, $formats );
        }
    }
}
