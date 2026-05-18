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
        add_filter( 'the_content', [ $this, 'display_events_on_place_page' ], 30 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
        add_action( 'rest_api_init',      [ $this, 'register_rest_routes' ] );
    }

    // =========================================================
    // PROTECTED ZOOM JOIN REDIRECT
    // Attendees are sent to /wp-json/oha-ve/v1/zoom-join/{id}?_wpnonce=...
    // The actual Zoom URL is never exposed in page source.
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
            ],
        ] );
    }

    public function zoom_join_redirect( \WP_REST_Request $request ) {
        $event_id = absint( $request->get_param( 'event_id' ) );
        $nonce    = sanitize_text_field( (string) $request->get_param( '_wpnonce' ) );

        // Verify time-limited nonce.
        if ( ! wp_verify_nonce( $nonce, 'oha_ve_zoom_join_' . $event_id ) ) {
            wp_die(
                'This join link has expired or is invalid. Please reload the event page and click the button again.',
                'Link Expired',
                [ 'response' => 403 ]
            );
        }

        // Must be logged in.
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url( get_permalink( $event_id ) ) );
            exit;
        }

        // Check access: board/admin and assigned instructor always pass.
        $access_level  = get_post_meta( $event_id, 'oha_ve_access_level', true );
        $instructor_id = (int) get_post_meta( $event_id, 'oha_ve_instructor_user_id', true );
        $user          = wp_get_current_user();
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

        // Fetch and redirect to the Zoom join URL.
        $zoom_join = get_post_meta( $event_id, 'oha_ve_zoom_join_url', true );

        if ( empty( $zoom_join ) ) {
            wp_die(
                'No Zoom link has been configured for this event yet.',
                'Link Not Available',
                [ 'response' => 404 ]
            );
        }

        // Security: enforce HTTPS scheme and zoom.us domain before redirecting.
        // Prevents an open redirect to arbitrary external sites if a stored URL
        // were ever malformed or tampered with at the database level.
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

        wp_redirect( esc_url_raw( $zoom_join ) );
        exit;
    }

    /**
     * Build a nonce-protected URL that redirects to the Zoom join link.
     * Use this anywhere a Zoom join href is rendered - the actual URL never appears in page source.
     *
     * @param int $event_id
     * @return string  Full URL to the protected redirect endpoint.
     */
    public static function get_protected_join_url( $event_id ) {
        return add_query_arg(
            '_wpnonce',
            wp_create_nonce( 'oha_ve_zoom_join_' . absint( $event_id ) ),
            rest_url( 'oha-ve/v1/zoom-join/' . absint( $event_id ) )
        );
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

        // Build the start URL from meeting ID so it never expires.
        // Phase 2: Zoom API will populate oha_ve_zoom_start_url with a fresh token - use that if set.
        $zoom_start_stored = get_post_meta( $event_id, 'oha_ve_zoom_start_url', true );
        $zoom_start = $zoom_start_stored;
        if ( empty( $zoom_start ) && ! empty( $zoom_id ) ) {
            $clean_id   = preg_replace( '/\D/', '', $zoom_id ); // strip spaces from ID
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

        $html = '<div class="oha-virtual-event-section">';

        // Event type badge
        if ( ! empty( $event_type ) ) {
            $html .= '<div class="oha-ve-type-badge">'
                   . esc_html( self::event_type_label( $event_type ) )
                   . '</div>';
        }

        // Status badge (Live Now is prominent)
        if ( 'live' === $event_status ) {
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
            // Resolve timing windows against the GD event schedule.
            $ve_schedule      = $this->get_frontend_schedule( $event_id );
            $always_show      = ( 'live' === $event_status || null === $ve_schedule );
            $seconds_until    = 0;
            $event_time_label = '';
            if ( $ve_schedule ) {
                $start_ts         = strtotime( $ve_schedule->start_date . ' ' . ( $ve_schedule->start_time ?: '00:00:00' ) );
                $seconds_until    = $start_ts - current_time( 'timestamp' );
                $event_time_label = date_i18n( 'F j, Y \a\t g:i A', $start_ts );
            }
            $instructor_secs      = OHA_VE_Settings::get_instructor_window() * 60;
            $member_secs          = OHA_VE_Settings::get_member_window()     * 60;
            $in_instructor_window = $always_show || $seconds_until <= $instructor_secs;
            $in_member_window     = $always_show || $seconds_until <= $member_secs;
            $host_key             = OHA_VE_Settings::get_host_key();
            $protected_url        = self::get_protected_join_url( $event_id );

            $html .= '<div class="oha-ve-card oha-ve-zoom-card">';

            if ( $show_host_view ) {
                if ( $in_instructor_window ) {
                    $html .= '<h3 class="oha-ve-card-title">You are hosting this session</h3>';
                    $html .= '<p>Click the button below. Your host key will be copied to your clipboard and Zoom will open in a new tab.</p>';
                    if ( ! empty( $zoom_join ) ) {
                        $html .= '<button class="oha-ve-btn oha-ve-btn-host oha-ve-host-launch-btn"'
                               . ' data-join-url="' . esc_attr( esc_url( $protected_url ) ) . '"'
                               . ' data-host-key="' . esc_attr( $host_key ) . '"'
                               . ' onclick="ohaVeHostLaunch(this)">Open Meeting + Copy Host Key</button>';
                        $html .= '<ol class="oha-ve-host-steps">'
                               . '<li>Zoom opens in a new tab - join as a participant</li>'
                               . '<li>In Zoom, click <strong>Participants</strong></li>'
                               . '<li>Click <strong>Claim Host</strong> at the bottom of the participant list</li>'
                               . '<li>Paste the host key when prompted (it was copied to your clipboard)</li>'
                               . '</ol>';
                        if ( $host_key ) {
                            $html .= '<p class="oha-ve-meta">Backup host key: <code>' . esc_html( $host_key ) . '</code></p>';
                        }
                    }
                } else {
                    $window_min = OHA_VE_Settings::get_instructor_window();
                    $html .= '<h3 class="oha-ve-card-title">You are hosting this session</h3>';
                    $html .= '<p class="oha-ve-timing-notice">This session begins <strong>'
                           . esc_html( $event_time_label ) . '</strong>. '
                           . 'The host launch button will appear <strong>' . esc_html( $window_min ) . ' minutes</strong> before the event.</p>';
                }
            } else {
                if ( $in_member_window && ! empty( $zoom_join ) ) {
                    $html .= '<h3 class="oha-ve-card-title">'
                           . ( 'live' === $event_status ? 'Session is Live' : 'Join Now' ) . '</h3>';
                    $html .= '<p>Click below to join this session in Zoom.</p>';
                    $html .= '<a href="' . esc_url( $protected_url ) . '" target="_blank" rel="noopener noreferrer"'
                           . ' class="oha-ve-btn oha-ve-btn-zoom">' . esc_html( $join_btn_text ) . '</a>';
                } elseif ( ! $in_member_window ) {
                    $window_min = OHA_VE_Settings::get_member_window();
                    $html .= '<h3 class="oha-ve-card-title">Zoom Meeting</h3>';
                    $html .= '<p class="oha-ve-timing-notice">Joining opens <strong>' . esc_html( $window_min ) . ' minutes</strong> before this session.'
                           . ( $event_time_label ? ' This event starts <strong>' . esc_html( $event_time_label ) . '</strong>.' : '' )
                           . '</p>';
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
        if ( ! empty( $yt_live_id ) && in_array( $event_status, [ 'live', 'upcoming' ], true ) ) {
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
    //    Replaces the gd_show_events_on_place_page snippet.
    //    Logic preserved exactly from the original snippet.
    // =========================================================

    public function display_events_on_place_page( $content ) {

        if ( ! is_singular( 'gd_place' ) ) {
            return $content;
        }

        // Skip if old snippet is still running (admin notice already shown).
        if ( function_exists( 'gd_show_events_on_place_page' ) ) {
            return $content;
        }

        global $post;
        $place_id = $post->ID;

        $events = get_posts( [
            'post_type'      => 'gd_event',
            'posts_per_page' => -1,
            'meta_key'       => 'venue',
            'meta_value'     => $place_id,
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
        ] );

        if ( empty( $events ) ) {
            return $content
                . '<h3>Upcoming Events</h3>'
                . '<p>&#127875; <strong>Seasonal Attraction</strong> - Open Select Nights</p>';
        }

        $event_list = '<br><h3>Upcoming Events</h3><ul>';

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

            $event_list .= sprintf(
                '<li><a href="%s">%s</a> &ndash; %s</li>',
                get_permalink( $event->ID ),
                esc_html( $event->post_title ),
                esc_html( $event_datetime )
            );
        }

        $event_list .= '</ul><br>';

        return $content . $event_list;
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
            "SELECT start_date, start_time FROM {$table} WHERE event_id = %d ORDER BY start_date ASC LIMIT 1",
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
        return '<script>
function ohaVeHostLaunch(btn) {
    var joinUrl = btn.getAttribute("data-join-url");
    var hostKey = btn.getAttribute("data-host-key");
    window.open(joinUrl, "_blank", "noopener,noreferrer");
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
    btn.disabled = true;
}
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
