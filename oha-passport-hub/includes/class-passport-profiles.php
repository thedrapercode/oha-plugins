<?php
/**
 * OHA Passport Hub — Actor Profiles
 *
 * Manages character profile creation, editing, and QR hash generation.
 * Profiles persist across events — a user keeps their character year to year.
 * Extracted from OHA Convention PWA Core v3.4.1 class-pwa-actor-passport.php.
 *
 * @package OHA_Passport_Hub
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class OHA_Passport_Profiles {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action( 'wp_ajax_oha_create_actor_profile',    [ $this, 'ajax_create_profile' ] );
        add_action( 'wp_ajax_oha_update_actor_profile',    [ $this, 'ajax_update_profile' ] );
        add_action( 'wp_ajax_oha_get_actor_profile',       [ $this, 'ajax_get_profile' ] );
        add_action( 'wp_ajax_oha_generate_qr_code',        [ $this, 'ajax_generate_qr' ] );
        add_action( 'wp_ajax_oha_get_profile_stats',       [ $this, 'ajax_get_stats' ] );
        add_action( 'wp_ajax_nopriv_oha_get_public_profile', [ $this, 'ajax_get_public_profile' ] );
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX handlers
    // ─────────────────────────────────────────────────────────────────────

    public function ajax_create_profile() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', OHA_PH_NONCE ) &&
             ! wp_verify_nonce( $_POST['nonce'] ?? '', 'oha_actor_passport_nonce' ) ) {
            wp_send_json_error( 'Invalid request' );
        }
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'You must be logged in to create a profile' );
        }

        $user_id = get_current_user_id();

        if ( $this->get_profile_by_user_id( $user_id ) ) {
            wp_send_json_error( 'You already have an actor profile. Use the edit function to update it.' );
        }

        $character_name = sanitize_text_field( $_POST['character_name'] );
        $character_type = sanitize_text_field( $_POST['character_type'] ?? '' );
        $haunt_name     = sanitize_text_field( $_POST['haunt_name']     ?? '' );
        $haunt_location = sanitize_text_field( $_POST['haunt_location'] ?? '' );
        $character_bio  = sanitize_textarea_field( $_POST['character_bio'] ?? '' );

        if ( empty( $character_name ) )        wp_send_json_error( 'Character name is required' );
        if ( strlen( $character_name ) > 100 ) wp_send_json_error( 'Character name must be 100 characters or less' );
        if ( strlen( $character_bio )  > 500 ) wp_send_json_error( 'Character bio must be 500 characters or less' );

        $qr_hash = $this->generate_qr_hash( $user_id );

        global $wpdb;
        $table  = $wpdb->prefix . 'oha_actor_profiles';
        $result = $wpdb->insert( $table, [
            'user_id'        => $user_id,
            'character_name' => $character_name,
            'character_type' => $character_type,
            'haunt_name'     => $haunt_name,
            'haunt_location' => $haunt_location,
            'character_bio'  => $character_bio,
            'qr_code_hash'   => $qr_hash,
            'qr_expires_at'  => date( 'Y-m-d H:i:s', strtotime( '+24 hours' ) ),
            'is_active'      => 1,
            'stamps_earned'  => 0,
        ], [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' ] );

        if ( $result === false ) {
            wp_send_json_error( 'Failed to create profile: ' . $wpdb->last_error );
        }

        $profile_id = $wpdb->insert_id;
        do_action( 'oha_passport_stamp_awarded', $user_id );
        wp_send_json_success( [
            'message'    => 'Actor profile created successfully!',
            'profile_id' => $profile_id,
            'qr_hash'    => $qr_hash,
            'profile'    => $this->get_profile_by_id( $profile_id ),
        ] );
    }

    public function ajax_update_profile() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', OHA_PH_NONCE ) &&
             ! wp_verify_nonce( $_POST['nonce'] ?? '', 'oha_actor_passport_nonce' ) ) {
            wp_send_json_error( 'Invalid request' );
        }
        if ( ! is_user_logged_in() ) wp_send_json_error( 'You must be logged in' );

        $user_id = get_current_user_id();
        $profile = $this->get_profile_by_user_id( $user_id );
        if ( ! $profile ) wp_send_json_error( 'No profile found to update' );

        $character_name = sanitize_text_field( $_POST['character_name'] );
        $character_type = sanitize_text_field( $_POST['character_type'] ?? '' );
        $haunt_name     = sanitize_text_field( $_POST['haunt_name']     ?? '' );
        $haunt_location = sanitize_text_field( $_POST['haunt_location'] ?? '' );
        $character_bio  = sanitize_textarea_field( $_POST['character_bio'] ?? '' );

        if ( empty( $character_name ) || strlen( $character_name ) > 100 )
            wp_send_json_error( 'Character name is required and must be 100 characters or less' );
        if ( strlen( $character_bio ) > 500 )
            wp_send_json_error( 'Character bio must be 500 characters or less' );

        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'oha_actor_profiles',
            [
                'character_name' => $character_name,
                'character_type' => $character_type,
                'haunt_name'     => $haunt_name,
                'haunt_location' => $haunt_location,
                'character_bio'  => $character_bio,
                'updated_at'     => current_time( 'mysql' ),
            ],
            [ 'user_id' => $user_id ],
            [ '%s', '%s', '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );

        if ( $result === false ) wp_send_json_error( 'Failed to update profile' );

        wp_send_json_success( [
            'message' => 'Profile updated successfully!',
            'profile' => $this->get_profile_by_user_id( $user_id ),
        ] );
    }

    public function ajax_get_profile() {
        if ( ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', OHA_PH_NONCE ) &&
             ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', 'oha_actor_passport_nonce' ) ) {
            wp_send_json_error( 'Invalid request' );
        }
        if ( ! is_user_logged_in() ) wp_send_json_error( 'You must be logged in' );

        $user_id = get_current_user_id();
        $profile = $this->get_profile_by_user_id( $user_id );

        if ( ! $profile ) {
            wp_send_json_success( [ 'has_profile' => false, 'message' => 'No profile found' ] );
        } else {
            wp_send_json_success( [ 'has_profile' => true, 'profile' => $profile ] );
        }
    }

    public function ajax_generate_qr() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', OHA_PH_NONCE ) &&
             ! wp_verify_nonce( $_POST['nonce'] ?? '', 'oha_actor_passport_nonce' ) ) {
            wp_send_json_error( 'Invalid request' );
        }
        if ( ! is_user_logged_in() ) wp_send_json_error( 'You must be logged in' );

        $user_id = get_current_user_id();
        $profile = $this->get_profile_by_user_id( $user_id );
        if ( ! $profile ) wp_send_json_error( 'No profile found' );

        $new_qr_hash = $this->generate_qr_hash( $user_id );

        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'oha_actor_profiles',
            [ 'qr_code_hash' => $new_qr_hash, 'qr_expires_at' => date( 'Y-m-d H:i:s', strtotime( '+24 hours' ) ) ],
            [ 'user_id' => $user_id ],
            [ '%s', '%s' ], [ '%d' ]
        );

        if ( $result === false ) wp_send_json_error( 'Failed to generate new QR code' );
        wp_send_json_success( [ 'message' => 'New QR code generated!', 'qr_hash' => $new_qr_hash ] );
    }

    public function ajax_get_stats() {
        if ( ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', OHA_PH_NONCE ) &&
             ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', 'oha_actor_passport_nonce' ) ) {
            wp_send_json_error( 'Invalid request' );
        }
        if ( ! is_user_logged_in() ) wp_send_json_error( 'You must be logged in' );

        $user_id = get_current_user_id();
        $profile = $this->get_profile_by_user_id( $user_id );
        if ( ! $profile ) wp_send_json_error( 'No profile found' );

        wp_send_json_success( $this->get_actor_stats( $profile['id'] ) );
    }

    public function ajax_get_public_profile() {
        $qr_hash = sanitize_text_field( $_REQUEST['qr_hash'] ?? '' );
        $profile = $this->get_profile_by_qr_hash( $qr_hash );

        if ( ! $profile ) {
            wp_send_json_error( 'Profile not found' );
        }
        wp_send_json_success( [
            'character_name' => $profile['character_name'],
            'character_type' => $profile['character_type'],
            'haunt_name'     => $profile['haunt_name'],
        ] );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Public data accessors — used by QR Manager, Stamps, and Shortcodes
    // ─────────────────────────────────────────────────────────────────────

    public function get_profile_by_user_id( $user_id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}oha_actor_profiles WHERE user_id = %d AND is_active = 1",
                $user_id
            ),
            ARRAY_A
        );
    }

    public function get_profile_by_id( $profile_id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}oha_actor_profiles WHERE id = %d AND is_active = 1",
                $profile_id
            ),
            ARRAY_A
        );
    }

    public function get_profile_by_qr_hash( $qr_hash ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}oha_actor_profiles
                 WHERE qr_code_hash = %s AND is_active = 1 AND qr_expires_at > NOW()",
                $qr_hash
            ),
            ARRAY_A
        );
    }

    public function get_actor_stats( $profile_id ) {
        global $wpdb;
        $stamps_table = $wpdb->prefix . 'oha_event_stamps';

        $total_stamps = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$stamps_table} WHERE actor_one_id = %d OR actor_two_id = %d",
            $profile_id, $profile_id
        ) );

        $unique_partners = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT
                CASE WHEN actor_one_id = %d THEN actor_two_id ELSE actor_one_id END
             ) FROM {$stamps_table} WHERE actor_one_id = %d OR actor_two_id = %d",
            $profile_id, $profile_id, $profile_id
        ) );

        $events_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT event_slug) FROM {$stamps_table}
             WHERE actor_one_id = %d OR actor_two_id = %d",
            $profile_id, $profile_id
        ) );

        $recent_trades = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.*,
                ap1.character_name as partner_name,
                ap1.haunt_name     as partner_haunt
             FROM {$stamps_table} s
             JOIN {$wpdb->prefix}oha_actor_profiles ap1 ON (
                 CASE
                     WHEN s.actor_one_id = %d THEN s.actor_two_id = ap1.id
                     ELSE s.actor_one_id = ap1.id
                 END
             )
             WHERE s.actor_one_id = %d OR s.actor_two_id = %d
             ORDER BY s.created_at DESC
             LIMIT 10",
            $profile_id, $profile_id, $profile_id
        ) );

        return [
            'total_stamps'         => intval( $total_stamps ),
            'unique_partners'      => intval( $unique_partners ),
            'events_participated'  => intval( $events_count ),
            'recent_trades'        => $recent_trades,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function generate_qr_hash( $user_id ) {
        return hash( 'sha256', $user_id . time() . wp_generate_password( 8, false ) );
    }

    public static function get_character_types() {
        return [
            'stalker'  => 'Stalker',    'trickster' => 'Trickster',  'creeper'  => 'Creeper',
            'screamer' => 'Screamer',   'clown'     => 'Creepy Clown','zombie'   => 'Zombie',
            'ghost'    => 'Ghost/Spirit','demon'    => 'Demon',       'vampire'  => 'Vampire',
            'werewolf' => 'Werewolf',   'witch'     => 'Witch/Warlock','doctor'  => 'Mad Doctor',
            'nurse'    => 'Killer Nurse','butcher'  => 'Butcher',     'chainsaw' => 'Chainsaw Maniac',
            'doll'     => 'Living Doll','puppet'    => 'Puppet Master','asylum'  => 'Asylum Patient',
            'carnival' => 'Carnival Freak','cult'   => 'Cult Member', 'other'    => 'Other',
        ];
    }
}
