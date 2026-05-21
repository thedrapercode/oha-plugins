<?php
/**
 * OHA Virtual Events - Zoom API Client
 *
 * Singleton. Handles Zoom Server-to-Server OAuth access token management
 * and meeting creation for Phase 2 automation.
 *
 * USAGE:
 *   $api    = OHA_VE_Zoom_API::get_instance();
 *   $token  = $api->get_access_token();       // string or WP_Error
 *   $result = $api->create_meeting( $post_id ); // array or WP_Error
 *
 * TOKEN CACHE:
 *   Access tokens are cached in WP transient 'oha_zoom_access_token' for 55
 *   minutes (Zoom tokens expire after 60 minutes). Pass true to get_access_token()
 *   to force a fresh fetch (used by the settings page connection test).
 *
 * AUTO-CREATE HOOK:
 *   Fires on 'publish_gd_event'. Creates a Zoom meeting if:
 *     - oha_ve_enabled = 1
 *     - oha_ve_platform is 'zoom' or 'both'
 *     - oha_ve_zoom_id is empty (meeting not yet created)
 *     - Auto-create setting (oha_zoom_auto_create) is enabled
 *     - Zoom credentials are configured
 *   The zoom_id empty check prevents duplicate meetings on subsequent saves.
 *
 * META WRITTEN ON SUCCESS:
 *   oha_ve_zoom_id            - Meeting ID (numeric string)
 *   oha_ve_zoom_join_url      - Attendee join URL
 *   oha_ve_zoom_meeting_uuid  - Meeting UUID (used for recording lookup via webhooks)
 *   oha_ve_zoom_start_url     - Host start URL (contains short-lived ZAK token;
 *                               frontend falls back to zoom.us/s/{ID} if expired)
 *
 * ERROR REPORTING:
 *   API errors during auto-create are stored in a short-lived transient and
 *   shown as an admin notice on the next admin page load (admins only).
 *
 * DEPENDENCIES:
 *   - OHA_VE_Settings (must be loaded first)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OHA_VE_Zoom_API {

    /** @var OHA_VE_Zoom_API|null Singleton instance */
    private static $instance = null;

    /**
     * Get or create the singleton instance.
     *
     * @return OHA_VE_Zoom_API
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Private constructor - use get_instance(). */
    private function __construct() {
        add_action( 'publish_gd_event',                     [ $this, 'maybe_create_meeting_on_publish' ], 20, 2 );
        add_action( 'geodir_event_handle_past_event_after', [ $this, 'mark_ended_on_expire' ],            10, 3 );
        add_action( 'admin_notices',                        [ $this, 'show_api_error_notice' ] );
        add_filter( 'cron_schedules',                       [ $this, 'add_cron_interval' ] );
        add_action( 'oha_ve_auto_flip_statuses',            [ $this, 'auto_flip_statuses' ] );
        if ( ! wp_next_scheduled( 'oha_ve_auto_flip_statuses' ) ) {
            wp_schedule_event( time(), 'oha_ve_5min', 'oha_ve_auto_flip_statuses' );
        }
    }

    // =========================================================
    // AUTO-CREATE ON PUBLISH
    // =========================================================

    /**
     * Fires when a gd_event transitions to published status.
     * Auto-creates a Zoom meeting when all conditions are met.
     *
     * @param int     $post_id
     * @param WP_Post $post
     */
    public function maybe_create_meeting_on_publish( $post_id, $post ) {

        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Only act on OHA virtual events
        if ( '1' !== get_post_meta( $post_id, 'oha_ve_enabled', true ) ) {
            return;
        }

        // Only Zoom-based events (zoom or both)
        $platform = get_post_meta( $post_id, 'oha_ve_platform', true );
        if ( ! in_array( $platform, [ 'zoom', 'both' ], true ) ) {
            return;
        }

        // Skip if a meeting was already created (prevents duplicates on re-save)
        if ( ! empty( get_post_meta( $post_id, 'oha_ve_zoom_id', true ) ) ) {
            return;
        }

        // Check that auto-create is enabled in settings
        if ( '1' !== get_option( OHA_VE_Settings::OPT_AUTO_CREATE, '0' ) ) {
            return;
        }

        // Check that credentials are configured
        if ( ! OHA_VE_Settings::is_api_configured() ) {
            return;
        }

        $result = $this->create_meeting( $post_id );

        if ( is_wp_error( $result ) ) {
            $this->store_error(
                'Auto-create Zoom meeting failed for event "' . get_the_title( $post_id ) . '" (ID ' . $post_id . '): '
                . $result->get_error_message()
            );
        }
    }

    // =========================================================
    // MARK ENDED ON GD EXPIRY
    // =========================================================

    /**
     * When GeoDirectory marks a gd_event as past, flip oha_ve_event_status to
     * 'ended' so the event page stops showing join/launch buttons.
     *
     * @param int    $post_id
     * @param string $status  Status GD applied (pending, draft, trash, delete).
     * @param mixed  $result
     */
    public function mark_ended_on_expire( $post_id, $status, $result ) {
        if ( '1' !== get_post_meta( $post_id, 'oha_ve_enabled', true ) ) {
            return;
        }
        update_post_meta( $post_id, 'oha_ve_event_status', 'ended' );
    }

    // =========================================================
    // AUTO-FLIP EVENT STATUS VIA CRON
    // =========================================================

    /**
     * Register a 5-minute cron interval.
     */
    public function add_cron_interval( $schedules ) {
        $schedules['oha_ve_5min'] = [
            'interval' => 300,
            'display'  => 'Every 5 Minutes (OHA Virtual Events)',
        ];
        return $schedules;
    }

    /**
     * Cron job: automatically flip oha_ve_event_status for enabled virtual events.
     *
     * upcoming -> live  when now is between start_ts and end_ts
     * live     -> ended when now is past end_ts
     *
     * Only acts on events with stored status of 'upcoming' or 'live'.
     * cancelled, replay, closed, and ended are never overridden here.
     * GD's geodir_event_handle_past_event_after hook also sets ended on expiry.
     */
    public function auto_flip_statuses() {
        global $wpdb;

        $table = defined( 'GEODIR_EVENT_SCHEDULES_TABLE' )
            ? GEODIR_EVENT_SCHEDULES_TABLE
            : $wpdb->prefix . 'geodir_event_schedule';

        $event_ids = get_posts( [
            'post_type'      => 'gd_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [ 'key' => 'oha_ve_enabled',      'value' => '1' ],
                [ 'key' => 'oha_ve_event_status',  'value' => [ 'upcoming', 'live' ], 'compare' => 'IN' ],
            ],
        ] );

        if ( empty( $event_ids ) ) {
            return;
        }

        $now    = time();
        $utc_tz = new DateTimeZone( 'UTC' );

        foreach ( $event_ids as $event_id ) {
            $schedule = $wpdb->get_row( $wpdb->prepare(
                "SELECT start_date, start_time, end_date, end_time FROM {$table} WHERE event_id = %d ORDER BY start_date ASC LIMIT 1",
                $event_id
            ) );

            if ( ! $schedule || ! $schedule->start_date ) {
                continue;
            }

            $dt_start = new DateTime( $schedule->start_date . ' ' . ( $schedule->start_time ?: '00:00:00' ), $utc_tz );
            $start_ts = $dt_start->getTimestamp();

            $end_ts = null;
            if ( ! empty( $schedule->end_date ) && ! empty( $schedule->end_time ) ) {
                $dt_end = new DateTime( $schedule->end_date . ' ' . $schedule->end_time, $utc_tz );
                $end_ts = $dt_end->getTimestamp();
            }

            $stored = get_post_meta( $event_id, 'oha_ve_event_status', true );

            if ( $start_ts <= $now && ( null === $end_ts || $now < $end_ts ) ) {
                if ( 'live' !== $stored ) {
                    update_post_meta( $event_id, 'oha_ve_event_status', 'live' );
                }
            } elseif ( null !== $end_ts && $now >= $end_ts ) {
                update_post_meta( $event_id, 'oha_ve_event_status', 'ended' );
            }
        }
    }

    // =========================================================
    // TOKEN MANAGEMENT
    // =========================================================

    /**
     * Get a valid Zoom access token.
     *
     * Uses a WP transient cache (55 min TTL). Pass $force_fresh = true
     * to bypass the cache and fetch a new token (e.g. for connection tests).
     *
     * @param bool $force_fresh  Skip transient cache and fetch fresh.
     * @return string|WP_Error   Token string on success, WP_Error on failure.
     */
    public function get_access_token( $force_fresh = false ) {

        if ( ! $force_fresh ) {
            $cached = get_transient( 'oha_zoom_access_token' );
            if ( $cached ) {
                return $cached;
            }
        }

        $account_id    = get_option( OHA_VE_Settings::OPT_ACCOUNT_ID,    '' );
        $client_id     = get_option( OHA_VE_Settings::OPT_CLIENT_ID,     '' );
        $client_secret = get_option( OHA_VE_Settings::OPT_CLIENT_SECRET, '' );

        if ( ! $account_id || ! $client_id || ! $client_secret ) {
            return new WP_Error(
                'oha_ve_missing_credentials',
                'Zoom API credentials are not configured. Go to Settings > OHA Virtual Events to add them.'
            );
        }

        $response = wp_remote_post(
            add_query_arg(
                [ 'grant_type' => 'account_credentials', 'account_id' => $account_id ],
                'https://zoom.us/oauth/token'
            ),
            [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ],
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'oha_ve_http_error',
                'HTTP request to Zoom failed: ' . $response->get_error_message()
            );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== (int) $status || empty( $body['access_token'] ) ) {
            $msg = $body['reason'] ?? ( $body['message'] ?? ( 'HTTP ' . $status ) );
            return new WP_Error( 'oha_ve_token_error', 'Zoom token request failed: ' . $msg );
        }

        $token = sanitize_text_field( $body['access_token'] );
        set_transient( 'oha_zoom_access_token', $token, 55 * MINUTE_IN_SECONDS );

        return $token;
    }

    // =========================================================
    // CREATE MEETING
    // =========================================================

    /**
     * Create a Zoom meeting for the given gd_event post and store the result
     * in post meta.
     *
     * @param int   $event_id  gd_event post ID.
     * @param array $args      Optional overrides: start_time (Y-m-dTH:i:s local), duration (int minutes), topic (string).
     * @return array|WP_Error  Zoom API response array on success, WP_Error on failure.
     */
    public function create_meeting( $event_id, $args = [] ) {

        $token = $this->get_access_token();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $title    = ! empty( $args['topic'] ) ? $args['topic'] : get_the_title( $event_id );
        $defaults = OHA_VE_Settings::get_meeting_defaults();
        $duration = ! empty( $args['duration'] ) ? (int) $args['duration'] : $defaults['duration'];

        // Resolve start_time: use passed arg, else read from GD event schedule table.
        if ( ! empty( $args['start_time'] ) ) {
            $start_time = $args['start_time']; // caller passes Y-m-dTH:i:s local time
        } else {
            global $wpdb;
            $schedule_table = defined( 'GEODIR_EVENT_SCHEDULES_TABLE' )
                ? GEODIR_EVENT_SCHEDULES_TABLE
                : $wpdb->prefix . 'geodir_event_schedule';
            $schedule = $wpdb->get_row( $wpdb->prepare(
                "SELECT start_date, start_time FROM {$schedule_table} WHERE event_id = %d ORDER BY start_date ASC LIMIT 1",
                $event_id
            ) );
            if ( $schedule && $schedule->start_date ) {
                $t           = $schedule->start_time ?: '14:00:00';
                $start_time  = $schedule->start_date . 'T' . $t;
            } else {
                $start_time = gmdate( 'Y-m-d' ) . 'T14:00:00';
            }
        }

        $wp_timezone    = get_option( 'timezone_string', 'America/New_York' ) ?: 'America/New_York';
        $auto_recording = ( '1' === $defaults['auto_record'] ) ? 'cloud' : 'none';
        $join_before    = ( '1' === $defaults['join_before_host'] );

        $meeting_body = [
            'type'       => 2,
            'topic'      => $title,
            'duration'   => $duration,
            'start_time' => $start_time,
            'timezone'   => $wp_timezone,
            'settings'   => [
                'auto_recording'    => $auto_recording,
                'join_before_host'  => $join_before,
                'waiting_room'      => $join_before ? false : ( '1' === $defaults['waiting_room'] ),
                'host_video'        => ( '1' === $defaults['host_video'] ),
                'participant_video' => false,
                'mute_upon_entry'   => true,
            ],
        ];

        $response = wp_remote_post(
            'https://api.zoom.us/v2/users/me/meetings',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode( $meeting_body ),
                'timeout' => 20,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'oha_ve_http_error',
                'HTTP request to Zoom API failed: ' . $response->get_error_message()
            );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $data   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 201 !== (int) $status ) {
            $msg = $data['message'] ?? ( 'HTTP ' . $status );
            return new WP_Error( 'oha_ve_create_meeting_error', 'Zoom meeting creation failed: ' . $msg );
        }

        // Store results in post meta
        if ( ! empty( $data['id'] ) ) {
            update_post_meta( $event_id, 'oha_ve_zoom_id', sanitize_text_field( (string) $data['id'] ) );
        }
        if ( ! empty( $data['join_url'] ) ) {
            update_post_meta( $event_id, 'oha_ve_zoom_join_url', esc_url_raw( $data['join_url'] ) );
        }
        if ( ! empty( $data['uuid'] ) ) {
            update_post_meta( $event_id, 'oha_ve_zoom_meeting_uuid', sanitize_text_field( $data['uuid'] ) );
        }
        if ( ! empty( $data['start_url'] ) ) {
            // Start URL contains a ZAK token (~90 day expiry).
            // Frontend falls back to zoom.us/s/{ID} if this has expired.
            update_post_meta( $event_id, 'oha_ve_zoom_start_url', esc_url_raw( $data['start_url'] ) );
        }

        return $data;
    }

    // =========================================================
    // ERROR NOTICE
    // =========================================================

    /**
     * Store an error message for display as an admin notice on next page load.
     * Stored in a short-lived transient to survive the redirect after publish.
     *
     * @param string $message
     */
    private function store_error( $message ) {
        set_transient( 'oha_ve_api_error', sanitize_text_field( $message ), 5 * MINUTE_IN_SECONDS );
    }

    /**
     * Display stored API error as an admin notice.
     * Only shown to site administrators.
     */
    public function show_api_error_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $error = get_transient( 'oha_ve_api_error' );
        if ( $error ) {
            delete_transient( 'oha_ve_api_error' );
            echo '<div class="notice notice-error is-dismissible"><p>';
            echo '<strong>OHA Virtual Events - Zoom API Error:</strong> ' . esc_html( $error );
            echo '</p></div>';
        }
    }
}
