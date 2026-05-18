<?php
/**
 * OHA Virtual Events - Zoom Webhook Receiver
 *
 * Registers a WordPress REST API endpoint to receive Zoom webhook events.
 * Endpoint: POST /wp-json/oha-ve/v1/zoom-webhook
 *
 * ZOOM APP SETUP:
 *   Add the endpoint URL to your Zoom app webhook subscription at marketplace.zoom.us.
 *   Enable event subscriptions: meeting.started, meeting.ended, recording.completed.
 *   Copy the Secret Token and save it in Settings > OHA Virtual Events.
 *
 * SIGNATURE VERIFICATION:
 *   Every incoming request (except the initial URL validation challenge) is verified
 *   using HMAC-SHA256 before any action is taken.
 *   Message format:  "v0:{x-zm-request-timestamp}:{raw_body}"
 *   Signature:       "v0=" + HMAC-SHA256(message, webhook_secret_token)
 *   Requests older than 5 minutes are rejected to prevent replay attacks.
 *
 * URL VALIDATION (Zoom setup challenge):
 *   When you first add the webhook URL, Zoom sends:
 *     {"event":"endpoint.url_validation","payload":{"plainToken":"..."}}
 *   The plugin responds with:
 *     {"plainToken":"...","encryptedToken":HMAC-SHA256(plainToken, secret)}
 *   This does NOT require the timestamp signature - only the webhook secret.
 *
 * EVENTS HANDLED:
 *   meeting.started      - Sets oha_ve_event_status = 'live' on matching event
 *   meeting.ended        - Sets oha_ve_event_status = 'closed' on matching event
 *   recording.completed  - Sets status to 'replay', marks archive_enabled, appends
 *                          Zoom share URL to Admin Notes for admin retrieval
 *
 * EVENT MATCHING:
 *   Incoming Zoom meeting IDs are matched against the oha_ve_zoom_id post meta key
 *   on all published gd_event posts.
 *
 * NOTE on recording.completed:
 *   The plugin sets the status to 'replay' and marks archive_enabled, but does NOT
 *   automatically set replay_available. The admin must still upload the recording
 *   to YouTube, paste the video ID into the event, and check "Replay is Ready".
 *   The Zoom share URL is appended to the event's Admin Notes for easy retrieval.
 *   Phase 3 will automate the YouTube upload step.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OHA_VE_Zoom_Webhooks {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    // =========================================================
    // ROUTE REGISTRATION
    // =========================================================

    public function register_routes() {
        register_rest_route(
            'oha-ve/v1',
            '/zoom-webhook',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_webhook' ],
                'permission_callback' => '__return_true', // Auth handled inside callback via signature
            ]
        );
    }

    // =========================================================
    // MAIN WEBHOOK HANDLER
    // =========================================================

    /**
     * Entry point for all incoming Zoom webhook requests.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_webhook( WP_REST_Request $request ) {

        $raw_body  = $request->get_body();
        $timestamp = $request->get_header( 'x-zm-request-timestamp' );
        $signature = $request->get_header( 'x-zm-signature' );

        $payload = json_decode( $raw_body, true );

        if ( ! is_array( $payload ) ) {
            return new WP_REST_Response( [ 'status' => 'invalid_payload' ], 400 );
        }

        // Handle Zoom URL validation challenge first (no request signature at this stage)
        if ( isset( $payload['event'] ) && 'endpoint.url_validation' === $payload['event'] ) {
            return $this->handle_url_validation( $payload );
        }

        // All other events require signature verification
        if ( ! $this->verify_signature( $raw_body, $timestamp, $signature ) ) {
            return new WP_REST_Response( [ 'status' => 'unauthorized' ], 401 );
        }

        $event_type = $payload['event'] ?? '';

        switch ( $event_type ) {
            case 'meeting.started':
                $this->handle_meeting_started( $payload );
                break;

            case 'meeting.ended':
                $this->handle_meeting_ended( $payload );
                break;

            case 'recording.completed':
                $this->handle_recording_completed( $payload );
                break;

            default:
                // Unknown event type - acknowledge and ignore
                break;
        }

        return new WP_REST_Response( [ 'status' => 'ok' ], 200 );
    }

    // =========================================================
    // URL VALIDATION (Zoom app setup challenge)
    // =========================================================

    /**
     * Respond to Zoom's endpoint validation challenge.
     * Zoom sends this when you first add the webhook URL in marketplace.zoom.us.
     *
     * @param array $payload  Decoded JSON payload containing event and plainToken.
     * @return WP_REST_Response
     */
    private function handle_url_validation( $payload ) {

        $secret      = get_option( OHA_VE_Settings::OPT_WEBHOOK_SECRET, '' );
        $plain_token = $payload['payload']['plainToken'] ?? '';

        if ( empty( $secret ) ) {
            return new WP_REST_Response(
                [ 'status' => 'webhook_secret_not_configured' ],
                400
            );
        }

        if ( empty( $plain_token ) ) {
            return new WP_REST_Response( [ 'status' => 'missing_plain_token' ], 400 );
        }

        $encrypted = hash_hmac( 'sha256', $plain_token, $secret );

        return new WP_REST_Response(
            [
                'plainToken'     => $plain_token,
                'encryptedToken' => $encrypted,
            ],
            200
        );
    }

    // =========================================================
    // SIGNATURE VERIFICATION
    // =========================================================

    /**
     * Verify the Zoom webhook request signature.
     *
     * Zoom signature scheme:
     *   message   = "v0:{timestamp}:{raw_body}"
     *   signature = "v0=" + HMAC-SHA256(message, webhook_secret)
     *
     * Requests more than 5 minutes old are rejected (prevents replay attacks).
     *
     * @param string $raw_body   Raw request body (not decoded).
     * @param string $timestamp  Value of x-zm-request-timestamp header (milliseconds).
     * @param string $signature  Value of x-zm-signature header.
     * @return bool
     */
    private function verify_signature( $raw_body, $timestamp, $signature ) {

        if ( empty( $timestamp ) || empty( $signature ) ) {
            return false;
        }

        $secret = get_option( OHA_VE_Settings::OPT_WEBHOOK_SECRET, '' );
        if ( empty( $secret ) ) {
            // No secret stored - cannot verify. Fail closed for security.
            return false;
        }

        // Reject requests older than 5 minutes
        $ts_seconds = (int) $timestamp / 1000;
        if ( abs( time() - $ts_seconds ) > 300 ) {
            return false;
        }

        $message  = 'v0:' . $timestamp . ':' . $raw_body;
        $expected = 'v0=' . hash_hmac( 'sha256', $message, $secret );

        // Constant-time comparison to prevent timing attacks
        return hash_equals( $expected, $signature );
    }

    // =========================================================
    // EVENT HANDLERS
    // =========================================================

    /**
     * meeting.started - set the matching event's status to 'live'.
     *
     * @param array $payload  Full Zoom webhook payload.
     */
    private function handle_meeting_started( $payload ) {

        $meeting_id = $payload['payload']['object']['id'] ?? '';
        if ( empty( $meeting_id ) ) {
            return;
        }

        $event_id = $this->find_event_by_zoom_id( (string) $meeting_id );
        if ( ! $event_id ) {
            return;
        }

        update_post_meta( $event_id, 'oha_ve_event_status', 'live' );
    }

    /**
     * meeting.ended - set the matching event's status to 'closed'.
     * Status will be updated to 'replay' by recording.completed when the
     * recording has finished processing.
     *
     * @param array $payload  Full Zoom webhook payload.
     */
    private function handle_meeting_ended( $payload ) {

        $meeting_id = $payload['payload']['object']['id'] ?? '';
        if ( empty( $meeting_id ) ) {
            return;
        }

        $event_id = $this->find_event_by_zoom_id( (string) $meeting_id );
        if ( ! $event_id ) {
            return;
        }

        update_post_meta( $event_id, 'oha_ve_event_status', 'closed' );
    }

    /**
     * recording.completed - mark the event as ready for replay processing.
     *
     * What this does:
     *   1. Sets status to 'replay' (if currently 'closed' or 'live')
     *   2. Marks oha_ve_archive_enabled = 1 (recording exists)
     *   3. Appends the Zoom share URL to Admin Notes so admin can retrieve it
     *
     * What the admin must still do manually (until Phase 3):
     *   - Download recording from Zoom share URL in Admin Notes
     *   - Upload to YouTube as unlisted
     *   - Paste the YouTube video ID into the event
     *   - Check "Replay is Ready" in the event editor
     *
     * @param array $payload  Full Zoom webhook payload.
     */
    private function handle_recording_completed( $payload ) {

        $object     = $payload['payload']['object'] ?? [];
        $meeting_id = $object['id'] ?? '';

        if ( empty( $meeting_id ) ) {
            return;
        }

        $event_id = $this->find_event_by_zoom_id( (string) $meeting_id );
        if ( ! $event_id ) {
            return;
        }

        // Advance status to replay (from closed or live)
        $current_status = get_post_meta( $event_id, 'oha_ve_event_status', true );
        if ( in_array( $current_status, [ 'live', 'closed', 'upcoming' ], true ) ) {
            update_post_meta( $event_id, 'oha_ve_event_status', 'replay' );
        }

        // Mark archive as enabled - a recording exists
        update_post_meta( $event_id, 'oha_ve_archive_enabled', '1' );

        // Append Zoom share URL to Admin Notes for admin retrieval
        $share_url = esc_url_raw( $object['share_url'] ?? '' );
        if ( $share_url ) {
            $existing_notes  = get_post_meta( $event_id, 'oha_ve_notes', true );
            $recording_note  = "\n--- Zoom Recording Ready (" . gmdate( 'Y-m-d H:i' ) . " UTC) ---";
            $recording_note .= "\nShare URL: " . $share_url;
            $recording_note .= "\nNext steps: Download, upload to YouTube (unlisted), paste video ID below, check Replay is Ready.";
            update_post_meta( $event_id, 'oha_ve_notes', sanitize_textarea_field( $existing_notes . $recording_note ) );
        }

        // NOTE: oha_ve_replay_available is intentionally left unchanged here.
        // The admin must confirm the YouTube upload is complete and check
        // "Replay is Ready" in the event editor before members can see the replay.
    }

    // =========================================================
    // HELPER: Match Zoom meeting ID to a gd_event post
    // =========================================================

    /**
     * Find the gd_event post ID that has the given Zoom meeting ID
     * stored in oha_ve_zoom_id post meta.
     *
     * Zoom meeting IDs are numeric strings. They may have been stored with spaces
     * stripped (the admin meta box strips spaces on save). We strip spaces from
     * the incoming ID before querying.
     *
     * @param string $zoom_meeting_id  Zoom meeting ID from webhook payload.
     * @return int|false  Post ID on success, false if not found.
     */
    private function find_event_by_zoom_id( $zoom_meeting_id ) {

        // Strip any whitespace - meeting IDs are stored without spaces
        $clean_id = preg_replace( '/\D/', '', $zoom_meeting_id );

        if ( empty( $clean_id ) ) {
            return false;
        }

        $posts = get_posts( [
            'post_type'      => 'gd_event',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => 'oha_ve_zoom_id',
                    'value' => $clean_id,
                ],
            ],
        ] );

        return ! empty( $posts ) ? (int) $posts[0] : false;
    }
}
