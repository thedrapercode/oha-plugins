<?php
/**
 * OHA Virtual Events - Frontend Display
 *
 * Handles two display areas:
 *
 * 1. VIRTUAL EVENT SECTION - appended to gd_event single page content.
 *    Shows the instructor "Start Session" button or member "Join Meeting"
 *    button, YouTube embeds, replay, and resources - gated by access level.
 *
 * 2. UPCOMING EVENTS LIST - appended to gd_place single page content.
 *    Lists events linked to a venue by the 'venue' post meta field.
 *    Replaces the gd_show_events_on_place_page site snippet.
 *
 * ACCESS LEVELS (oha_ve_access_level meta):
 *   subscriber_only  - subscriber role (paid OHA members)
 *   all_members      - subscriber + listing_owner roles
 *   board_only       - oha_board role only
 *   public           - everyone
 *
 * ROLE NOTE:
 *   oha_board is the WordPress role slug (in wp_capabilities).
 *   "board-member" is the UsersWP account type used for My Account
 *   display routing only - NOT used for WP access control checks here.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OHA_VE_Frontend {

    public function __construct() {
        add_filter( 'the_content', [ $this, 'display_virtual_event' ],    20 );
        add_action( 'oha_place_page_events', [ $this, 'render_place_events' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
        add_action( 'rest_api_init',      [ $this, 'register_rest_routes' ] );
    }

    // =========================================================
    // PROTECTED ZOOM JOIN REDIRECT
    // Attendees are sent to /wp-json/oha-ve/v1/zoom-join/{id}?_token=...
    // First request renders a meeting rules acknowledgment page.
    // After agreeing (?agreed=1), the actual Zoom redirect fires.
    // The Zoom URL is never exposed in page source.
    // =========================================================

    public function register_rest_routes() {
        register_rest_route( 'oha-ve/v1', '/zoom-join/(?P<event_id>\d+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'zoom_join_redirect' ],
            'permission_callback' => '__return_true', // Auth handled inside callback
            'args'                => [
                'event_id' => [
                    'required'          => true,
                    'validate_callback' => function ( $v ) { return is_numeric( $v ) && (int) $v > 0; },
                    'sanitize_callback' => 'absint',
                ],
                'agreed' => [
                    'required'          => false,
                    'sanitize_callback' => function ( $v ) { return '1' === (string) $v; },
                ],
            ],
        ] );
    }

    public function zoom_join_redirect( \WP_REST_Request $request ) {
        $event_id = absint( $request->get_param( 'event_id' ) );
        $nonce    = sanitize_text_field( (string) $request->get_param( '_token' ) );
        $agreed   = (bool) $request->get_param( 'agreed' );

        // Verify HMAC token.
        // wp_verify_nonce() is user-session-dependent. In a REST API GET request (window.open),
        // WordPress does not authenticate the user from cookies without X-WP-Nonce, so
        // get_current_user_id() returns 0 and nonces created for the logged-in user fail.
        // Using an HMAC token keyed to WP secret salts avoids that entirely.
        if ( ! self::verify_zoom_token( $nonce, $event_id ) ) {
            wp_die(
                'This join link has expired or is invalid. Please reload the event page and click the button again.',
                'Link Expired',
                [ 'response' => 403 ]
            );
        }

        // WP REST API does not set up cookie-based user auth without X-WP-Nonce.
        // Explicitly validate the logged_in auth cookie so is_user_logged_in() works.
        if ( ! is_user_logged_in() ) {
            foreach ( $_COOKIE as $cookie_name => $cookie_value ) {
                if ( 0 === strpos( $cookie_name, 'wordpress_logged_in_' ) ) {
                    $uid = wp_validate_auth_cookie( $cookie_value, 'logged_in' );
                    if ( $uid ) {
                        wp_set_current_user( $uid );
                        break;
                    }
                }
            }
        }

        // Must be logged in.
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url( get_permalink( $event_id ) ) );
            exit;
        }

        // Check access: board/admin and assigned instructor always pass.
        $access_level   = get_post_meta( $event_id, 'oha_ve_access_level', true );
        $instructor_id  = (int) get_post_meta( $event_id, 'oha_ve_instructor_user_id', true );
        $user           = wp_get_current_user();
        $is_board_admin = current_user_can( 'manage_options' )
                       || in_array( 'oha_board', (array) $user->roles, true );
        $is_instructor  = $instructor_id && (int) get_current_user_id() === $instructor_id;

        if ( ! $is_board_admin && ! $is_instructor && ! self::user_has_access( $access_level ) ) {
            wp_die(
                'You do not have permission to join this event.',
                'Access Denied',
                [ 'response' => 403 ]
            );
        }

        // Fetch the Zoom join URL.
        $zoom_join = get_post_meta( $event_id, 'oha_ve_zoom_join_url', true );

        if ( empty( $zoom_join ) ) {
            wp_die(
                'No Zoom link has been configured for this event yet.',
                'Link Not Available',
                [ 'response' => 404 ]
            );
        }

        // Security: enforce HTTPS scheme and zoom.us domain before redirecting.
        $parsed_scheme = wp_parse_url( $zoom_join, PHP_URL_SCHEME );
        $parsed_host   = wp_parse_url( $zoom_join, PHP_URL_HOST );
        if ( 'https' !== strtolower( (string) $parsed_scheme )
            || ! preg_match( '/(?:^|\.)zoom\.us$/i', (string) $parsed_host )
        ) {
            wp_die(
                'This join link has an invalid destination. Please contact an administrator.',
                'Invalid Link',
                [ 'response' => 400 ]
            );
        }

        // Board/admin and instructors go straight to Zoom -- no acknowledgment step.
        // Members see the acknowledgment page on first visit.
        if ( ! $agreed && ! $is_board_admin && ! $is_instructor ) {
            $agree_url = add_query_arg(
                [ '_token' => $nonce, 'agreed' => '1' ],
                rest_url( 'oha-ve/v1/zoom-join/' . $event_id )
            );
            $is_board = ( 'board_only' === $access_level );
            $this->render_acknowledgment_page( get_the_title( $event_id ), $agree_url, $is_board );
            exit;
        }

        wp_redirect( esc_url_raw( $zoom_join ) );
        exit;
    }

    /**
     * Render the meeting rules acknowledgment page.
     * Outputs a full HTML document and exits - bypasses normal REST JSON response.
     *
     * @param string $event_title  Title of the event being joined.
     * @param string $agree_url    URL to redirect to after agreement (includes agreed=1).
     * @param bool   $is_board     True for board_only events - shows board-specific rules.
     */
    private function render_acknowledgment_page( $event_title, $agree_url, $is_board ) {

        $rules_member = [
            'Treat all participants with respect. Personal attacks, harassment, or bullying of any kind will result in removal from the session.',
            'Degrading or disrespectful comments about race, religion, culture, sexual orientation, gender, or identity are not tolerated.',
            'Keep discussion relevant and constructive. Public criticism of specific haunts or individuals is not appropriate in this setting.',
            'Do not share, record, screenshot, or redistribute session content, participant names, or private information without consent.',
            'Violations may result in immediate removal. Ongoing concerns should be directed to OHA leadership through official channels.',
        ];

        $rules_board = [
            'This is an official OHA Board meeting. Attendance is limited to board members and invited guests.',
            'Conduct yourself professionally at all times. Personal disputes, outside grievances, or disruptive behavior will not be permitted.',
            'Meeting content and discussions are confidential. Do not independently record or redistribute any portion of this session without board authorization.',
            'Violations may result in removal from the session.',
        ];

        $recording_member = 'This session is recorded for internal security purposes only. Recordings are not posted publicly or shared outside of OHA leadership. By joining you acknowledge and consent to this recording.';
        $recording_board  = 'This meeting is recorded for internal record-keeping purposes only. Recordings remain confidential and are accessible only to authorized OHA board members. They are not posted publicly or distributed outside the board. By joining you acknowledge and consent to this recording.';

        $rules     = $is_board ? $rules_board : $rules_member;
        $recording = $is_board ? $recording_board : $recording_member;
        $heading   = $is_board ? 'OHA Board Meeting' : 'OHA Virtual Event';

        $rules_html = '';
        foreach ( $rules as $rule ) {
            $rules_html .= '<li>' . esc_html( $rule ) . '</li>' . "\n";
        }

        $logo_url  = get_site_icon_url( 80 );
        $logo_html = $logo_url
            ? '<img src="' . esc_url( $logo_url ) . '" alt="OHA" style="max-height:56px;width:auto;margin-bottom:20px;">'
            : '';

        nocache_headers();
        header( 'Content-Type: text/html; charset=UTF-8' );

        echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Before You Join - OHA Virtual Event</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#680400;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.wrap{background:#000000;border:1px solid rgba(173,49,25,0.45);border-radius:8px;max-width:680px;width:100%;padding:40px}
.logo{text-align:center;margin-bottom:4px}
.heading{font-size:13px;font-weight:700;color:#e76653;text-align:center;letter-spacing:1px;text-transform:uppercase;margin-bottom:8px}
.event-title{font-size:20px;font-weight:700;color:#ffffff;text-align:center;margin-bottom:28px;line-height:1.3}
.intro{font-size:14px;color:#f7fafc;margin-bottom:20px;line-height:1.6}
.rules{list-style:none;margin-bottom:20px;border-top:1px solid rgba(173,49,25,0.2)}
.rules li{color:#f7fafc;font-size:14px;line-height:1.6;padding:11px 0 11px 18px;border-bottom:1px solid rgba(173,49,25,0.2);position:relative}
.rules li::before{content:"";position:absolute;left:0;top:18px;width:6px;height:6px;background:#ad3119;border-radius:50%}
.recording{background:rgba(173,49,25,0.12);border:1px solid rgba(173,49,25,0.35);border-radius:6px;padding:12px 16px;font-size:13px;color:#cbd2d9;margin-bottom:28px;line-height:1.6}
.btn{display:block;width:100%;background:#ad3119;color:#ffffff;font-size:16px;font-weight:700;text-align:center;padding:15px 24px;border-radius:6px;text-decoration:none;cursor:pointer}
.btn:hover{background:#ff0000;color:#ffffff}
.note{text-align:center;font-size:12px;color:#cbd2d9;margin-top:14px}
</style>
</head>
<body>
<div class="wrap">
  <div class="logo">' . $logo_html . '</div>
  <div class="heading">' . esc_html( $heading ) . '</div>
  <div class="event-title">' . esc_html( $event_title ) . '</div>
  <p class="intro">Please review the following before entering this session. By clicking the button below you agree to these terms.</p>
  <ul class="rules">' . $rules_html . '</ul>
  <div class="recording">' . esc_html( $recording ) . '</div>
  <a href="' . esc_url( $agree_url ) . '" class="btn">I Agree - Join Meeting</a>
  <p class="note">Clicking the button will open Zoom in this tab.</p>
</div>
</body>
</html>';
    }

    /**
     * Build a nonce-protected URL that redirects to the Zoom join link.
     * Use this anywhere a Zoom join href is rendered - the actual URL never appears in page source.
     *
     * @param int $event_id
     * @return string  Full URL to the protected redirect endpoint.
     */
    public static function get_protected_join_url( $event_id ) {
        $expires = time() + 3600; // 1-hour window
        return add_query_arg(
            '_token',
            self::make_zoom_token( absint( $event_id ), $expires ),
            rest_url( 'oha-ve/v1/zoom-join/' . absint( $event_id ) )
        );
    }

    /**
     * Build a time-limited HMAC token for a Zoom redirect URL.
     * Format: {expires}|{16-char hex signature}
     */
    private static function make_zoom_token( $event_id, $expires ) {
        $data = absint( $event_id ) . '.' . $expires;
        $sig  = substr( wp_hash( $data . '.oha_ve_zoom_join' ), 0, 16 );
        return $expires . '.' . $sig;
    }

    /**
     * Verify a token produced by make_zoom_token().
     * Returns true only if the token is well-formed, unexpired, and HMAC-valid.
     * Separator is dot (.) -- safe in URL query strings, no esc_url encoding issues.
     */
    private static function verify_zoom_token( $token, $event_id ) {
        $parts = explode( '.', (string) $token, 2 );
        if ( count( $parts ) !== 2 ) {
            return false;
        }
        $expires_ts   = (int) $parts[0];
        $provided_sig = $parts[1];
        if ( time() > $expires_ts ) {
            return false; // expired
        }
        $data         = absint( $event_id ) . '.' . $expires_ts;
        $expected_sig = substr( wp_hash( $data . '.oha_ve_zoom_join' ), 0, 16 );
        return hash_equals( $expected_sig, $provided_sig );
    }

    // =========================================================
    // 1. VIRTUAL EVENT SECTION - gd_event single pages
    // =========================================================

    public function display_virtual_event( $content ) {

        if ( ! is_singular( 'gd_event' ) ) {
            return $content;
        }

        if ( ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }

        global $post;
        $event_id = $post->ID;

        // Prevent duplicate output when the_content filter fires more than once per page.
        // GeoDirectory and some themes call apply_filters('the_content', ...) directly
        // for schema markup or secondary rendering, bypassing the in_the_loop() guard above.
        // Tracking by post ID is safe for pages that legitimately show multiple events.
        static $rendered_ids = [];
        if ( in_array( $event_id, $rendered_ids, true ) ) {
            return $content;
        }
        $rendered_ids[] = $event_id;

        // Only act on events flagged as OHA virtual events
        if ( '1' !== get_post_meta( $event_id, 'oha_ve_enabled', true ) ) {
            return $content;
        }

        $access_level     = get_post_meta( $event_id, 'oha_ve_access_level',       true );
        $event_type       = get_post_meta( $event_id, 'oha_ve_event_type',         true );
        $event_status     = get_post_meta( $event_id, 'oha_ve_event_status',       true );
        $platform         = get_post_meta( $event_id, 'oha_ve_platform',           true );
        $is_prerecorded   = ( 'prerecorded' === $platform );
        $instructor_id    = (int) get_post_meta( $event_id, 'oha_ve_instructor_user_id', true );
        $zoom_join        = get_post_meta( $event_id, 'oha_ve_zoom_join_url',      true );
        $zoom_id          = get_post_meta( $event_id, 'oha_ve_zoom_id',            true );

        // Build the start URL from meeting ID — never expires, no token needed.
        $zoom_start = '';
        if ( ! empty( $zoom_id ) ) {
            $clean_id   = preg_replace( '/\D/', '', $zoom_id );
            $zoom_start = 'https://zoom.us/s/' . rawurlencode( $clean_id );
        }
        $zoom_pass        = get_post_meta( $event_id, 'oha_ve_zoom_passcode',      true );
        $zoom_btn_label   = get_post_meta( $event_id, 'oha_ve_zoom_btn_label',     true );
        $join_btn_text    = ! empty( $zoom_btn_label ) ? $zoom_btn_label : 'Join Meeting';
        $yt_live_id       = get_post_meta( $event_id, 'oha_ve_yt_live_id',         true );
        $yt_replay_id     = get_post_meta( $event_id, 'oha_ve_yt_replay_id',       true );
        $archive_enabled  = get_post_meta( $event_id, 'oha_ve_archive_enabled',    true );
        $replay_available = get_post_meta( $event_id, 'oha_ve_replay_available',   true );
        $res_url          = get_post_meta( $event_id, 'oha_ve_resources_url',      true );

        // Determine who the current user is relative to this event
        $is_board_or_admin = $this->is_board_or_admin();
        $is_instructor     = $this->is_instructor( $instructor_id );
        $show_host_view    = $is_instructor || $is_board_or_admin;

        // Access check - board/admin bypass, instructor always in
        if ( ! $show_host_view && ! self::user_has_access( $access_level ) ) {
            return $content . $this->render_access_denied( $access_level );
        }

        // Resolve schedule and auto-compute effective status before any rendering.
        // This allows the LIVE NOW badge and all display logic to use time-based status
        // without requiring the board to manually flip oha_ve_event_status.
        // Resolve schedule and auto-compute effective status before any rendering.
        // The cron (auto_flip_statuses) keeps stored status current, but we also
        // compute it here as a real-time fallback for the page view itself.
        $ve_schedule      = $this->get_frontend_schedule( $event_id );
        $event_time_label = '';
        $effective_status = $event_status;
        if ( $ve_schedule ) {
            $utc_tz   = new DateTimeZone( 'UTC' );
            $dt       = new DateTime( $ve_schedule->start_date . ' ' . ( $ve_schedule->start_time ?: '00:00:00' ), $utc_tz );
            $start_ts = $dt->getTimestamp();
            $event_time_label = date_i18n( 'F j, Y \a\t g:i A', $start_ts );
            if ( in_array( $event_status, [ 'upcoming', 'live' ], true ) ) {
                $end_ts = null;
                if ( ! empty( $ve_schedule->end_date ) && ! empty( $ve_schedule->end_time ) ) {
                    $dt_end = new DateTime( $ve_schedule->end_date . ' ' . $ve_schedule->end_time, $utc_tz );
                    $end_ts = $dt_end->getTimestamp();
                }
                $now = time();
                if ( $start_ts <= $now && ( null === $end_ts || $now < $end_ts ) ) {
                    $effective_status = 'live';
                } elseif ( null !== $end_ts && $now >= $end_ts ) {
                    $effective_status = 'ended';
                }
            }
        }

        $html = '<div class="oha-virtual-event-section">';

        // Event type badge
        if ( ! empty( $event_type ) ) {
            $html .= '<div class="oha-ve-type-badge">'
                   . esc_html( self::event_type_label( $event_type ) )
                   . '</div>';
        }

        // Status badge (Live Now is prominent)
        if ( 'live' === $effective_status ) {
            $html .= '<div class="oha-ve-live-badge">&#128994; LIVE NOW</div>';
        }

        // Instructor info strip (visible to board/admin)
        if ( $is_board_or_admin && $instructor_id ) {
            $instructor = get_userdata( $instructor_id );
            if ( $instructor ) {
                $html .= '<div class="oha-ve-instructor-strip">'
                       . 'Instructor: <strong>' . esc_html( $instructor->display_name ) . '</strong>'
                       . '</div>';
            }
        }

        // ── ZOOM CARD ──
        // Never show Zoom section for pre-recorded videos - there is no live session.
        $has_zoom = ! $is_prerecorded && ( ! empty( $zoom_join ) || ! empty( $zoom_start ) );

        if ( $has_zoom ) {
            $host_key      = OHA_VE_Settings::get_host_key();
            $protected_url = self::get_protected_join_url( $event_id );

            $html .= '<div class="oha-ve-card oha-ve-zoom-card">';

            if ( $show_host_view ) {
                $html .= '<h3 class="oha-ve-card-title">You are hosting this session</h3>';
                if ( ! empty( $zoom_join ) ) {
                    $html .= '<p>Click the button below. Your host key will be copied to your clipboard and Zoom will open in a new tab.</p>';
                    $html .= '<a href="' . esc_url( $protected_url ) . '" target="_blank"'
                           . ' class="oha-ve-btn oha-ve-btn-host oha-ve-host-launch-btn"'
                           . ' data-host-key="' . esc_attr( $host_key ) . '">'
                           . 'Open Meeting + Copy Host Key</a>';
                    $html .= '<ol class="oha-ve-host-steps">'
                           . '<li>Zoom opens in a new tab - join as a participant</li>'
                           . '<li>In Zoom, click <strong>Participants</strong></li>'
                           . '<li>Click <strong>Claim Host</strong> at the bottom of the participant list</li>'
                           . '<li>Paste the host key when prompted (it was copied to your clipboard)</li>'
                           . '</ol>';
                    if ( $host_key ) {
                        $html .= '<p class="oha-ve-meta">Backup host key: <code>' . esc_html( $host_key ) . '</code></p>';
                    }
                    if ( $event_time_label ) {
                        $html .= '<p class="oha-ve-meta">Session time: <strong>' . esc_html( $event_time_label ) . '</strong></p>';
                    }
                } else {
                    $html .= '<p class="oha-ve-timing-notice">No Zoom link has been set for this event yet. Edit the event to add one.</p>';
                }
            } else {
                if ( ! empty( $zoom_join ) ) {
                    $html .= '<h3 class="oha-ve-card-title">'
                           . ( 'live' === $effective_status ? 'Session is Live' : 'Join Now' ) . '</h3>';
                    $html .= '<p>Click below to join this session in Zoom.</p>';
                    $html .= '<a href="' . esc_url( $protected_url ) . '" target="_blank" rel="noopener noreferrer"'
                           . ' class="oha-ve-btn oha-ve-btn-zoom">' . esc_html( $join_btn_text ) . '</a>';
                    if ( $event_time_label ) {
                        $html .= '<p class="oha-ve-meta">Session time: <strong>' . esc_html( $event_time_label ) . '</strong></p>';
                    }
                }
            }

            if ( ! empty( $zoom_id ) ) {
                $html .= '<p class="oha-ve-meta"><strong>Meeting ID:</strong> ' . esc_html( $zoom_id ) . '</p>';
            }
            if ( ! empty( $zoom_pass ) ) {
                $html .= '<p class="oha-ve-meta"><strong>Passcode:</strong> ' . esc_html( $zoom_pass ) . '</p>';
            }

            $html .= '</div>';

            // Output host launch JS once per page.
            static $host_js_done = false;
            if ( $show_host_view && ! $host_js_done ) {
                $host_js_done = true;
                $html .= $this->get_host_launch_js();
            }
        }

        // ── YOUTUBE LIVE EMBED ──
        if ( ! empty( $yt_live_id ) && in_array( $effective_status, [ 'live', 'upcoming' ], true ) ) {
            $html .= '<div class="oha-ve-card oha-ve-youtube-card">';
            $html .= '<h3 class="oha-ve-card-title">&#9654; Live Stream</h3>';
            $html .= '<div class="oha-ve-video-wrapper">';
            $html .= '<iframe src="https://www.youtube-nocookie.com/embed/'
                   . esc_attr( $yt_live_id )
                   . '?autoplay=0&rel=0" '
                   . 'title="OHA Virtual Event Live Stream" frameborder="0" '
                   . 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" '
                   . 'allowfullscreen></iframe>';
            $html .= '</div>';
            $html .= '</div>';
        }

        // ── YOUTUBE REPLAY EMBED ──
        // Show replay if:
        //   - replay is marked available, OR
        //   - user is board/admin (can preview before marking available)
        $show_replay = ! empty( $yt_replay_id )
            && ( '1' === $replay_available || $is_board_or_admin );

        if ( $show_replay ) {
            $html .= '<div class="oha-ve-card oha-ve-replay-card">';

            if ( $is_prerecorded ) {
                $html .= '<h3 class="oha-ve-card-title">&#9654; Watch Session</h3>';
            } else {
                $html .= '<h3 class="oha-ve-card-title">&#9654; Event Replay</h3>';
            }

            if ( $is_board_or_admin && '1' !== $replay_available ) {
                $html .= '<p class="oha-ve-admin-note">&#9888; This video is NOT yet marked as available to members. Check "Replay is Ready" in the event editor when ready to publish.</p>';
            } elseif ( $is_prerecorded ) {
                $html .= '<p>Watch this pre-recorded session below.</p>';
            } else {
                $html .= '<p>This event has ended. Watch the full recording below.</p>';
            }

            $html .= '<div class="oha-ve-video-wrapper">';
            $html .= '<iframe src="https://www.youtube-nocookie.com/embed/'
                   . esc_attr( $yt_replay_id )
                   . '?rel=0" '
                   . 'title="OHA Virtual Event Replay" frameborder="0" '
                   . 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" '
                   . 'allowfullscreen></iframe>';
            $html .= '</div>';
            $html .= '</div>';
        }

        // ── RESOURCES ──
        if ( ! empty( $res_url ) ) {
            $html .= '<div class="oha-ve-card">';
            $html .= '<h3 class="oha-ve-card-title">&#128196; Event Resources</h3>';
            $html .= '<p>Download the slides, handouts, or other materials for this event.</p>';
            $html .= '<a href="' . esc_url( $res_url ) . '" target="_blank" rel="noopener noreferrer"'
                   . ' class="oha-ve-btn oha-ve-btn-secondary">Download Resources</a>';
            $html .= '</div>';
        }

        $html .= '</div>'; // .oha-virtual-event-section

        return $content . $html;
    }

    /**
     * Render access-denied block.
     */
    private function render_access_denied( $access_level ) {
        $label  = self::access_level_label( $access_level );
        $block  = '<div class="oha-ve-access-denied">';
        $block .= '<p>&#128274; This is a <strong>' . esc_html( $label ) . '</strong> virtual event.</p>';

        if ( ! is_user_logged_in() ) {
            $block .= '<p><a href="' . esc_url( wp_login_url( get_permalink() ) )
                    . '" class="oha-ve-btn oha-ve-btn-secondary">Log In to Access</a></p>';
        } else {
            $block .= '<p>Your account does not have permission to view this event. '
                    . 'Contact an OHA administrator if you believe this is an error.</p>';
        }

        $block .= '</div>';
        return $block;
    }

    // =========================================================
    // 2. PLACE EVENTS LIST - gd_place single pages
    //    Called via do_action('oha_place_page_events', $place_id)
    //    from the haunt listing display template (main-content.php).
    //    Using a direct action avoids the_content double-fire issue
    //    caused by GeoDirectory's nested template override.
    // =========================================================

    public function render_place_events( $place_id ) {

        if ( function_exists( 'gd_show_events_on_place_page' ) ) {
            return;
        }

        $events = get_posts( [
            'post_type'      => 'gd_event',
            'posts_per_page' => -1,
            'meta_key'       => 'venue',
            'meta_value'     => $place_id,
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
        ] );

        if ( empty( $events ) ) {
            return;
        }

        echo '<h3>Upcoming Events</h3><ul>';

        foreach ( $events as $event ) {
            $start_raw = get_post_meta( $event->ID, 'date_time', true );

            if ( is_numeric( $start_raw ) ) {
                $timestamp = intval( $start_raw );
            } else {
                $timestamp = strtotime( $start_raw );
            }

            if ( $timestamp && $timestamp > 0 ) {
                $formatted_date = date_i18n( 'F j, Y', $timestamp );
                $formatted_time = date_i18n( 'g:i A', $timestamp );
                $event_datetime = "$formatted_date at $formatted_time";
            } else {
                $event_datetime = 'Date/Time TBD';
            }

            printf(
                '<li><a href="%s">%s</a> &ndash; %s</li>',
                esc_url( get_permalink( $event->ID ) ),
                esc_html( $event->post_title ),
                esc_html( $event_datetime )
            );
        }

        echo '</ul>';
    }

    // =========================================================
    // ACCESS CONTROL - static so shortcodes class can call them
    // =========================================================

    /**
     * Check if current user meets the required access level.
     *
     * Access level → required WordPress role(s):
     *   subscriber_only  → subscriber (paid OHA member)
     *   all_members      → subscriber OR listing_owner
     *   board_only       → oha_board
     *   public           → everyone
     *
     * Board members and admins implicitly pass all checks.
     *
     * @param string $required_level  subscriber_only | all_members | board_only | public
     * @return bool
     */
    public static function user_has_access( $required_level ) {

        if ( empty( $required_level ) || 'public' === $required_level ) {
            return true;
        }

        if ( ! is_user_logged_in() ) {
            return false;
        }

        // Administrators always have access.
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        $user  = wp_get_current_user();
        $roles = (array) $user->roles;

        // Board members can access everything.
        if ( in_array( 'oha_board', $roles, true ) ) {
            return true;
        }

        switch ( $required_level ) {
            case 'subscriber_only':
                return in_array( 'subscriber', $roles, true );

            case 'all_members':
                return ! empty( array_intersect( $roles, [ 'subscriber', 'listing_owner' ] ) );

            case 'board_only':
                // oha_board check already returned true above; anyone else: false.
                return false;

            default:
                // Unknown level - treat as subscriber_only.
                return in_array( 'subscriber', $roles, true );
        }
    }

    /**
     * Returns human-readable label for an access level.
     * @param string $level
     * @return string
     */
    public static function access_level_label( $level ) {
        $labels = [
            'subscriber_only' => 'Paid Members Only',
            'all_members'     => 'Members & Free Listing Owners',
            'board_only'      => 'Board Members Only',
            'public'          => 'Public',
        ];
        return $labels[ $level ] ?? 'Members Only';
    }

    /**
     * Returns human-readable label for an event type.
     * @param string $type
     * @return string
     */
    public static function event_type_label( $type ) {
        $labels = [
            'board_meeting'    => 'Board Meeting',
            'member_education' => 'Member Education Session',
            'pre_recorded'     => 'Pre-Recorded Session',
            'actor_meetup'     => 'Actor Meetup',
            'member_townhall'  => 'Member Town Hall',
            'other_virtual'    => 'Virtual Event',
        ];
        return $labels[ $type ] ?? 'Virtual Event';
    }

    // =========================================================
    // ROLE HELPERS
    // =========================================================

    /**
     * Is the current logged-in user the assigned instructor for this event?
     * @param int $instructor_user_id  stored in oha_ve_instructor_user_id meta
     * @return bool
     */
    private function is_instructor( $instructor_user_id ) {
        if ( ! is_user_logged_in() || ! $instructor_user_id ) {
            return false;
        }
        return (int) get_current_user_id() === (int) $instructor_user_id;
    }

    /**
     * Is the current user an oha_board member or site administrator?
     * @return bool
     */
    private function is_board_or_admin() {
        if ( ! is_user_logged_in() ) {
            return false;
        }
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        $user  = wp_get_current_user();
        $roles = (array) $user->roles;
        return in_array( 'oha_board', $roles, true );
    }

    // =========================================================
    // HELPERS
    // =========================================================

    /**
     * Fetch the first upcoming schedule row for an event.
     * Same query used in OHA_VE_Shortcodes — duplicated here to keep
     * frontend self-contained without a cross-class dependency.
     *
     * @param int $event_id
     * @return object|null  stdClass with start_date, start_time, or null.
     */
    private function get_frontend_schedule( $event_id ) {
        global $wpdb;
        $table = defined( 'GEODIR_EVENT_SCHEDULES_TABLE' )
            ? GEODIR_EVENT_SCHEDULES_TABLE
            : $wpdb->prefix . 'geodir_event_schedule';
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT start_date, start_time, end_date, end_time FROM {$table} WHERE event_id = %d ORDER BY start_date ASC LIMIT 1",
            $event_id
        ) );
    }

    /**
     * Inline JS for the host launch button.
     * Opens the Zoom join URL in a new tab and copies the host key to clipboard.
     *
     * @return string  <script> block.
     */
    private function get_host_launch_js() {
        // The host button is an <a href target="_blank"> so the browser handles
        // tab opening natively. This script only handles clipboard copy on click.
        return '<script>
(function() {
    var btns = document.querySelectorAll(".oha-ve-host-launch-btn");
    for (var i = 0; i < btns.length; i++) {
        btns[i].addEventListener("click", function() {
            var btn = this;
            var hostKey = btn.getAttribute("data-host-key");
            if (hostKey && navigator.clipboard) {
                navigator.clipboard.writeText(hostKey).then(function() {
                    btn.textContent = "Zoom Opened + Host Key Copied";
                    btn.style.background = "#155724";
                }).catch(function() {
                    btn.textContent = "Zoom Opened - Use Backup Key Below";
                });
            } else {
                btn.textContent = "Zoom Opened - Use Backup Key Below";
            }
        });
    }
}());
</script>';
    }

    // =========================================================
    // ASSETS
    // =========================================================

    public function enqueue_styles() {
        if ( is_singular( [ 'gd_event', 'gd_place' ] ) ) {
            wp_enqueue_style(
                'oha-virtual-events',
                OHA_VE_PLUGIN_URL . 'assets/css/oha-virtual-events.css',
                [],
                OHA_VE_VERSION
            );
        }
    }
}
