<?php
/**
 * OHA Virtual Events - My Account Shortcodes + Live Banner
 *
 * Registers shortcodes for the My Account > Education & Events tab
 * and the homepage Live Banner.
 *
 * SHORTCODES:
 *   [oha_education_events]    - Master hub: all sections stacked in order.
 *   [oha_education_live]      - Live Now: events currently in progress.
 *   [oha_education_upcoming]  - Upcoming: future scheduled events the user can attend.
 *   [oha_education_teaching]  - Teaching: events where the logged-in user is the instructor.
 *   [oha_education_ondemand]  - On Demand: archived replays the user can watch.
 *   [oha_live_banner]         - Homepage banner: shows flashing LIVE button when a live event
 *                               exists. Authorized users get the join link; others get a
 *                               membership/register prompt. Returns empty if no event is live.
 *
 * SHORTCODE ATTRIBUTES:
 *   limit            - max events to show (education shortcodes, default -1 except upcoming = 10)
 *   membership_url   - [oha_live_banner] link for non-members (default /membership/)
 *   register_url     - [oha_live_banner] secondary register link (default /register/)
 *
 * DATES:
 *   Event start date/time is pulled from the wp_geodir_event_schedule table
 *   (provided by Events for GeoDirectory). If no schedule row exists, falls
 *   back to the event's post date.
 *
 * ACCESS:
 *   Uses OHA_VE_Frontend::user_has_access() for consistent role checking.
 *   board_only events are only shown to oha_board and administrators.
 *   subscriber_only events are only shown to subscribers (paid members) and above.
 *   all_members events are shown to subscribers and listing_owners.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OHA_VE_Shortcodes {

    public function __construct() {
        add_shortcode( 'oha_education_events',    [ $this, 'sc_hub' ] );
        add_shortcode( 'oha_education_live',      [ $this, 'sc_live' ] );
        add_shortcode( 'oha_education_upcoming',  [ $this, 'sc_upcoming' ] );
        add_shortcode( 'oha_education_teaching',  [ $this, 'sc_teaching' ] );
        add_shortcode( 'oha_education_ondemand',  [ $this, 'sc_ondemand' ] );
        add_shortcode( 'oha_live_banner',         [ $this, 'sc_live_banner' ] );
        // Ensure CSS loads on pages that use shortcodes.
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_styles' ] );
    }

    // =========================================================
    // SHORTCODE: [oha_education_events] - Master hub
    // =========================================================

    public function sc_hub( $atts ) {
        if ( ! is_user_logged_in() ) {
            return $this->render_login_prompt();
        }

        $out  = '<div class="oha-edu-hub">';
        $out .= $this->sc_live( $atts );
        $out .= $this->sc_teaching( $atts );
        $out .= $this->sc_upcoming( $atts );
        $out .= $this->sc_ondemand( $atts );
        $out .= '</div>';

        return $out;
    }

    // =========================================================
    // SHORTCODE: [oha_education_live]
    // =========================================================

    public function sc_live( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '';
        }

        $atts  = shortcode_atts( [ 'limit' => -1 ], $atts, 'oha_education_live' );
        $limit = intval( $atts['limit'] );

        $events = $this->query_events( [
            'status' => 'live',
            'limit'  => $limit,
        ] );

        $accessible = $this->filter_accessible( $events );

        if ( empty( $accessible ) ) {
            return '';
        }

        $out  = '<div class="oha-edu-section oha-edu-live-section">';
        $out .= '<h3 class="oha-edu-section-title"><span class="oha-ve-live-dot">&#128994;</span> Live Now</h3>';

        foreach ( $accessible as $event ) {
            $out .= $this->render_event_card( $event, 'live' );
        }

        $out .= '</div>';
        return $out;
    }

    // =========================================================
    // SHORTCODE: [oha_education_upcoming]
    // =========================================================

    public function sc_upcoming( $atts ) {
        if ( ! is_user_logged_in() ) {
            return $this->render_login_prompt();
        }

        $atts  = shortcode_atts( [ 'limit' => 10 ], $atts, 'oha_education_upcoming' );
        $limit = intval( $atts['limit'] );

        $events = $this->query_events( [
            'status'             => 'upcoming',
            'limit'              => $limit,
            'order_by_start_date' => true,
        ] );

        $accessible = $this->filter_accessible( $events );

        $out  = '<div class="oha-edu-section">';
        $out .= '<h3 class="oha-edu-section-title">&#128197; Upcoming Events</h3>';

        if ( empty( $accessible ) ) {
            $out .= '<p class="oha-edu-empty">No upcoming virtual events are scheduled at this time.</p>';
        } else {
            foreach ( $accessible as $event ) {
                $out .= $this->render_event_card( $event, 'upcoming' );
            }
        }

        $out .= '</div>';
        return $out;
    }

    // =========================================================
    // SHORTCODE: [oha_education_teaching]
    // =========================================================

    public function sc_teaching( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '';
        }

        $user_id = get_current_user_id();

        $atts  = shortcode_atts( [ 'limit' => -1 ], $atts, 'oha_education_teaching' );
        $limit = intval( $atts['limit'] );

        // Query events where this user is the assigned instructor and the event
        // is upcoming or live (active assignments).
        // skip_hub_filter: instructors always see their own events regardless of hub visibility setting.
        $events = $this->query_events( [
            'instructor_id'      => $user_id,
            'status_in'          => [ 'upcoming', 'live' ],
            'limit'              => $limit,
            'skip_hub_filter'    => true,
            'order_by_start_date' => true,
        ] );

        if ( empty( $events ) ) {
            return '';
        }

        $out  = '<div class="oha-edu-section oha-edu-teaching-section">';
        $out .= '<h3 class="oha-edu-section-title">&#127979; Assigned to Teach</h3>';

        foreach ( $events as $event ) {
            $out .= $this->render_event_card( $event, 'teach' );
        }

        $out .= '</div>';
        return $out;
    }

    // =========================================================
    // SHORTCODE: [oha_education_ondemand]
    // =========================================================

    public function sc_ondemand( $atts ) {
        if ( ! is_user_logged_in() ) {
            return $this->render_login_prompt();
        }

        $atts  = shortcode_atts( [ 'limit' => -1 ], $atts, 'oha_education_ondemand' );
        $limit = intval( $atts['limit'] );

        $replays    = OHA_VE_Replay_CPT::query_replays( [ 'limit' => $limit ] );
        $accessible = [];
        foreach ( $replays as $replay ) {
            $access = get_post_meta( $replay->ID, 'oha_ve_replay_access', true ) ?: 'subscriber_only';
            if ( OHA_VE_Frontend::user_has_access( $access ) ) {
                $accessible[] = $replay;
            }
        }

        $out  = '<div class="oha-edu-section">';
        $out .= '<h3 class="oha-edu-section-title">&#127902; On Demand</h3>';

        if ( empty( $accessible ) ) {
            $out .= '<p class="oha-edu-empty">No recordings are available yet. Check back after the next session.</p>';
        } else {
            $out .= '<div class="oha-replay-grid">';
            foreach ( $accessible as $replay ) {
                $out .= OHA_VE_Replay_CPT::render_replay_card( $replay );
            }
            $out .= '</div>';
            // Toggle script for Watch Now expand/collapse -- emitted once per page
            $out .= '<script>'
                  . 'if(!window.ohaReplayToggleInit){'
                  . 'window.ohaReplayToggleInit=true;'
                  . 'function ohaReplayToggle(b){'
                  . 'var t=document.getElementById(b.getAttribute("data-target"));'
                  . 'if(!t)return;'
                  . 'var o=t.style.display!=="none";'
                  . 't.style.display=o?"none":"block";'
                  . 'b.textContent=o?"Watch Now":"Close Video";'
                  . '}}'
                  . '</script>';
        }

        $out .= '</div>';
        return $out;
    }

    // =========================================================
    // QUERY HELPERS
    // =========================================================

    /**
     * Query gd_event posts that are flagged as OHA virtual events.
     *
     * @param array $args {
     *   status          - single oha_ve_event_status value
     *   status_in       - array of status values (overrides status)
     *   instructor_id   - filter by oha_ve_instructor_user_id
     *   replay_ready    - bool, filter oha_ve_replay_available = 1
     *   limit           - posts_per_page
     * }
     * @return WP_Post[]
     */
    private function query_events( $args = [] ) {

        $meta_query = [
            'relation' => 'AND',
            [
                'key'   => 'oha_ve_enabled',
                'value' => '1',
            ],
        ];

        // Only show events the admin has opted into the hub - UNLESS this is a teaching
        // query (instructors always see their own assigned events regardless of hub flag).
        if ( empty( $args['skip_hub_filter'] ) ) {
            $meta_query[] = [
                'key'   => 'oha_ve_show_in_hub',
                'value' => '1',
            ];
        }

        // Status filter
        if ( ! empty( $args['status_in'] ) ) {
            $meta_query[] = [
                'key'     => 'oha_ve_event_status',
                'value'   => (array) $args['status_in'],
                'compare' => 'IN',
            ];
        } elseif ( ! empty( $args['status'] ) ) {
            $meta_query[] = [
                'key'   => 'oha_ve_event_status',
                'value' => $args['status'],
            ];
        }

        // Instructor filter
        if ( ! empty( $args['instructor_id'] ) ) {
            $meta_query[] = [
                'key'   => 'oha_ve_instructor_user_id',
                'value' => absint( $args['instructor_id'] ),
            ];
        }

        // Replay available
        if ( ! empty( $args['replay_ready'] ) ) {
            $meta_query[] = [
                'key'   => 'oha_ve_replay_available',
                'value' => '1',
            ];
        }

        // For upcoming events, sort by GD event schedule start date ASC (soonest first).
        // For everything else, order by post date DESC (most recently added first).
        $use_date_order = ! empty( $args['order_by_start_date'] );

        $query_args = [
            'post_type'      => 'gd_event',
            'post_status'    => 'publish',
            'posts_per_page' => isset( $args['limit'] ) ? intval( $args['limit'] ) : -1,
            'meta_query'     => $meta_query,
        ];

        if ( ! $use_date_order ) {
            $query_args['orderby'] = 'date';
            $query_args['order']   = 'DESC';
        }

        $query = new WP_Query( $query_args );
        $posts = $query->posts;

        if ( $use_date_order && ! empty( $posts ) ) {
            // Sort using the GD event schedule table — no separate meta field needed.
            // Events with no schedule row sort to the end.
            usort( $posts, function( $a, $b ) {
                $sa = $this->get_event_schedule( $a->ID );
                $sb = $this->get_event_schedule( $b->ID );
                $da = $sa ? $sa->start_date . ' ' . $sa->start_time : '9999-12-31 23:59:59';
                $db = $sb ? $sb->start_date . ' ' . $sb->start_time : '9999-12-31 23:59:59';
                return strcmp( $da, $db );
            } );
        }

        return $posts;
    }

    /**
     * Filter a list of events to only those the current user can access.
     *
     * @param WP_Post[] $events
     * @return WP_Post[]
     */
    private function filter_accessible( $events ) {
        $accessible = [];
        foreach ( $events as $event ) {
            $access_level = get_post_meta( $event->ID, 'oha_ve_access_level', true );
            if ( OHA_VE_Frontend::user_has_access( $access_level ) ) {
                $accessible[] = $event;
            }
        }
        return $accessible;
    }

    // =========================================================
    // EVENT CARD RENDERER
    // =========================================================

    /**
     * Render a single event card for shortcode output.
     *
     * @param WP_Post $event
     * @param string  $view  live | upcoming | replay | teach
     * @return string HTML
     */
    private function render_event_card( $event, $view = 'upcoming' ) {

        $event_id      = $event->ID;
        $event_type    = get_post_meta( $event_id, 'oha_ve_event_type',    true );
        $event_status  = get_post_meta( $event_id, 'oha_ve_event_status',  true );
        $zoom_join     = get_post_meta( $event_id, 'oha_ve_zoom_join_url', true );
        $zoom_id_raw   = get_post_meta( $event_id, 'oha_ve_zoom_id',       true );
        $zoom_btn_raw      = get_post_meta( $event_id, 'oha_ve_zoom_btn_label', true );
        $type_btn_defaults = [
            'member_education' => 'Join Session',
            'pre_recorded'     => 'Watch Recording',
            'actor_meetup'     => 'Join Meetup',
            'member_townhall'  => 'Join Town Hall',
            'other_virtual'    => 'Join Event',
        ];
        $type_default  = isset( $type_btn_defaults[ $event_type ] ) ? $type_btn_defaults[ $event_type ] : 'Join Meeting';
        $join_btn_text = ! empty( $zoom_btn_raw ) ? $zoom_btn_raw : $type_default;
        $instructor_id = (int) get_post_meta( $event_id, 'oha_ve_instructor_user_id', true );

        // Build start URL from meeting ID (never expires).
        // Falls back to stored start URL if Zoom API has set one (Phase 2).
        $zoom_start_stored = get_post_meta( $event_id, 'oha_ve_zoom_start_url', true );
        $zoom_start = $zoom_start_stored;
        if ( empty( $zoom_start ) && ! empty( $zoom_id_raw ) ) {
            $clean_id   = preg_replace( '/\D/', '', $zoom_id_raw );
            $zoom_start = 'https://zoom.us/s/' . rawurlencode( $clean_id );
        }

        $permalink     = get_permalink( $event_id );
        $title         = get_the_title( $event_id );
        $schedule      = $this->get_event_schedule( $event_id );

        $is_teach_view = ( 'teach' === $view );
        $is_live_view  = ( 'live' === $view || 'live' === $event_status );

        $card_class = 'oha-edu-card';
        if ( $is_live_view ) $card_class .= ' oha-edu-card--live';
        if ( $is_teach_view ) $card_class .= ' oha-edu-card--teach';

        $out  = '<div class="' . $card_class . '">';
        $out .= '<div class="oha-edu-card-body">';

        // Type badge
        if ( ! empty( $event_type ) ) {
            $out .= '<span class="oha-edu-type-badge">'
                  . esc_html( OHA_VE_Frontend::event_type_label( $event_type ) )
                  . '</span>';
        }

        // Live badge
        if ( $is_live_view ) {
            $out .= ' <span class="oha-ve-live-badge-sm">LIVE</span>';
        }

        // Title
        $out .= '<h4 class="oha-edu-card-title"><a href="' . esc_url( $permalink ) . '">'
              . esc_html( $title ) . '</a></h4>';

        // Date / time from schedule (GD stores times as UTC; parse accordingly)
        if ( $schedule ) {
            $dt = new DateTime( $schedule->start_date . ' ' . ( $schedule->start_time ?: '00:00:00' ), new DateTimeZone( 'UTC' ) );
            $ts = $dt->getTimestamp();
            if ( $ts ) {
                $out .= '<p class="oha-edu-card-date">'
                      . esc_html( date_i18n( 'F j, Y \a\t g:i A', $ts ) )
                      . '</p>';
            }
        }

        // Instructor name (for teach view or if there is one assigned)
        if ( $instructor_id ) {
            $u = get_userdata( $instructor_id );
            if ( $u ) {
                $out .= '<p class="oha-edu-card-instructor">Instructor: <strong>'
                      . esc_html( $u->display_name ) . '</strong></p>';
            }
        }

        $out .= '</div>'; // .oha-edu-card-body

        // Action button area
        $out .= '<div class="oha-edu-card-actions">';

        switch ( $view ) {
            case 'live':
                // Live: show direct Join Now or Start Session for instructor/board
                $user  = wp_get_current_user();
                $roles = (array) $user->roles;
                $is_instructor  = is_user_logged_in() && (int) get_current_user_id() === $instructor_id;
                $is_board_admin = current_user_can( 'manage_options' ) || in_array( 'oha_board', $roles, true );

                if ( ( $is_instructor || $is_board_admin ) && ! empty( $zoom_start ) ) {
                    $out .= '<a href="' . esc_url( $zoom_start ) . '" target="_blank" rel="noopener noreferrer"'
                          . ' class="oha-ve-btn oha-ve-btn-host">Start Session</a>';
                } elseif ( ! empty( $zoom_join ) ) {
                    $out .= '<a href="' . esc_url( OHA_VE_Frontend::get_protected_join_url( $event_id ) ) . '" target="_blank" rel="noopener noreferrer"'
                          . ' class="oha-ve-btn oha-ve-btn-zoom">' . esc_html( $join_btn_text ) . '</a>';
                } else {
                    $out .= '<a href="' . esc_url( $permalink ) . '" class="oha-ve-btn oha-ve-btn-secondary">View Event</a>';
                }
                break;

            case 'teach':
                // Teaching view: always show Start Session button
                if ( ! empty( $zoom_start ) ) {
                    $out .= '<a href="' . esc_url( $zoom_start ) . '" target="_blank" rel="noopener noreferrer"'
                          . ' class="oha-ve-btn oha-ve-btn-host">Start Session</a>';
                }
                $out .= ' <a href="' . esc_url( $permalink ) . '" class="oha-ve-btn oha-ve-btn-outline">Event Details</a>';
                break;

            case 'replay':
                $out .= '<a href="' . esc_url( $permalink ) . '" class="oha-ve-btn oha-ve-btn-replay">Watch Now</a>';
                break;

            case 'upcoming':
            default:
                $out .= '<a href="' . esc_url( $permalink ) . '" class="oha-ve-btn oha-ve-btn-outline">View Details</a>';
                break;
        }

        $out .= '</div>'; // .oha-edu-card-actions
        $out .= '</div>'; // .oha-edu-card

        return $out;
    }

    // =========================================================
    // SCHEDULE DATE HELPER
    // =========================================================

    /**
     * Get the first schedule row for an event from the GeoDirectory event
     * schedule table (wp_geodir_event_schedule).
     *
     * @param int $event_id
     * @return object|null  stdClass with start_date and start_time, or null.
     */
    private function get_event_schedule( $event_id ) {
        global $wpdb;

        // Use the constant if available, otherwise build the table name.
        $table = defined( 'GEODIR_EVENT_SCHEDULES_TABLE' )
            ? GEODIR_EVENT_SCHEDULES_TABLE
            : $wpdb->prefix . 'geodir_event_schedule';

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT start_date, start_time, end_date, end_time FROM {$table}
             WHERE event_id = %d
             ORDER BY start_date ASC, start_time ASC
             LIMIT 1",
            $event_id
        ) );
    }

    // =========================================================
    // MISC HELPERS
    // =========================================================

    /**
     * Render a login prompt for shortcodes shown to non-logged-in users.
     */
    private function render_login_prompt() {
        return '<div class="oha-edu-login-prompt">'
             . '<p>&#128274; Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to view OHA virtual events.</p>'
             . '</div>';
    }

    /**
     * Enqueue the plugin stylesheet if the current page contains any of
     * our shortcodes. Checks post content for shortcode tag presence.
     */
    // =========================================================
    // SHORTCODE: [oha_live_banner]
    // Compact header bar. Returns empty when no live or cancelled event exists.
    // Shows Spotify-style bar: album art, equalizer, title, start-end time, join button.
    // Cancelled events show within the scheduled time window then auto-hide.
    //
    // Attributes:
    //   membership_url  - URL for non-member "Become a Member" button (default /membership/)
    //   register_url    - URL for non-member "Register" button (default /register/)
    // =========================================================

    public function sc_live_banner( $atts ) {

        $atts = shortcode_atts( [
            'membership_url' => '/membership/',
            'register_url'   => '/register/',
        ], $atts, 'oha_live_banner' );

        // Query both live and cancelled events - cancelled also need banner display.
        $events = $this->query_events( [
            'status_in' => [ 'live', 'cancelled' ],
            'limit'     => 5,
        ] );

        if ( empty( $events ) ) {
            return '';
        }

        // Prioritize live over cancelled.
        $event        = null;
        $is_cancelled = false;
        foreach ( $events as $e ) {
            if ( 'live' === get_post_meta( $e->ID, 'oha_ve_event_status', true ) ) {
                $event = $e;
                break;
            }
        }
        if ( ! $event ) {
            foreach ( $events as $e ) {
                if ( 'cancelled' === get_post_meta( $e->ID, 'oha_ve_event_status', true ) ) {
                    $event        = $e;
                    $is_cancelled = true;
                    break;
                }
            }
        }
        if ( ! $event ) {
            return '';
        }

        $event_id = $event->ID;
        $schedule = $this->get_event_schedule( $event_id );

        // For cancelled events: only show within the scheduled time window.
        // Auto-close in DB once the window has passed.
        if ( $is_cancelled ) {
            $utc_tz = new DateTimeZone( 'UTC' );
            $now_ts = time();

            // Not yet started - don't show yet.
            if ( $schedule && ! empty( $schedule->start_time ) ) {
                $dt_start = new DateTime( $schedule->start_date . ' ' . $schedule->start_time, $utc_tz );
                if ( $now_ts < $dt_start->getTimestamp() ) {
                    return '';
                }
            }

            // Past end time - auto-close and hide.
            if ( $schedule && ! empty( $schedule->end_time ) ) {
                $end_date = ! empty( $schedule->end_date ) ? $schedule->end_date : $schedule->start_date;
                $dt_end   = new DateTime( $end_date . ' ' . $schedule->end_time, $utc_tz );
                if ( $now_ts > $dt_end->getTimestamp() ) {
                    update_post_meta( $event_id, 'oha_ve_event_status', 'closed' );
                    return '';
                }
            }
        }

        $access_level = get_post_meta( $event_id, 'oha_ve_access_level', true );
        $event_type   = get_post_meta( $event_id, 'oha_ve_event_type',   true );
        $title        = get_the_title( $event_id );
        $permalink    = get_permalink( $event_id );
        $zoom_join    = get_post_meta( $event_id, 'oha_ve_zoom_join_url',  true );
        $zoom_btn_raw = get_post_meta( $event_id, 'oha_ve_zoom_btn_label', true );

        // Event-type-based button label defaults.
        $type_btn_defaults = [
            'member_education' => 'Join Session',
            'pre_recorded'     => 'Watch Recording',
            'actor_meetup'     => 'Join Meetup',
            'member_townhall'  => 'Join Town Hall',
            'other_virtual'    => 'Join Event',
        ];
        $type_default = isset( $type_btn_defaults[ $event_type ] ) ? $type_btn_defaults[ $event_type ] : 'Join Meeting';
        $join_label   = ! empty( $zoom_btn_raw ) ? $zoom_btn_raw : $type_default;

        // Featured image for album-art panel.
        $thumb_url = get_the_post_thumbnail_url( $event_id, [ 80, 80 ] );

        // Build start - end time string from schedule (GD stores times as UTC).
        $time_str  = '';
        $end_ts_js = 0;
        if ( $schedule ) {
            $utc_tz = new DateTimeZone( 'UTC' );
            $s_ts   = $schedule->start_time
                ? ( new DateTime( $schedule->start_date . ' ' . $schedule->start_time, $utc_tz ) )->getTimestamp()
                : 0;
            $e_d    = ! empty( $schedule->end_date ) ? $schedule->end_date : $schedule->start_date;
            $e_ts   = $schedule->end_time
                ? ( new DateTime( $e_d . ' ' . $schedule->end_time, $utc_tz ) )->getTimestamp()
                : 0;
            if ( $s_ts ) {
                $time_str = date_i18n( 'g:i A', $s_ts );
                if ( $e_ts && $e_ts > $s_ts ) {
                    $time_str .= ' - ' . date_i18n( 'g:i A', $e_ts );
                    $end_ts_js = $e_ts;
                }
            }
        }

        // Equalizer bars HTML (reused in multiple places).
        $eq = '<div class="oha-live-eq" aria-hidden="true">'
            . '<span class="oha-live-eq-bar"></span>'
            . '<span class="oha-live-eq-bar"></span>'
            . '<span class="oha-live-eq-bar"></span>'
            . '<span class="oha-live-eq-bar"></span>'
            . '<span class="oha-live-eq-bar"></span>'
            . '</div>';

        // Art panel - featured image or dark placeholder with equalizer.
        $art  = '<div class="oha-live-banner-art">';
        if ( $thumb_url ) {
            $art .= '<img src="' . esc_url( $thumb_url ) . '" alt="" />';
        } else {
            $art .= '<div class="oha-live-banner-art-eq">' . $eq . '</div>';
        }
        $art .= '</div>';

        // Time display element.
        $time_el = $time_str
            ? '<div class="oha-live-banner-times">' . esc_html( $time_str ) . '</div>'
            : '';

        // JS for auto-hiding cancelled banner after end time.
        $js = '';
        if ( $is_cancelled && $end_ts_js ) {
            $js = '<script>(function(){'
                . 'var b=document.querySelector(".oha-live-banner--cancelled[data-event-end]");'
                . 'if(!b)return;'
                . 'var e=parseInt(b.getAttribute("data-event-end"),10)*1000;'
                . 'if(Date.now()>=e){b.style.display="none";return;}'
                . 'setTimeout(function(){b.style.display="none";},Math.min(e-Date.now(),2147483647));'
                . '}());</script>';
        }

        // ---- CANCELLED ----
        if ( $is_cancelled ) {
            $end_attr = $end_ts_js ? ' data-event-end="' . intval( $end_ts_js ) . '"' : '';
            $out  = '<div class="oha-live-banner oha-live-banner--cancelled"' . $end_attr . '>';
            $out .= $art;
            $out .= '<div class="oha-live-banner-info">';
            $out .= '<div class="oha-live-banner-meta"><span class="oha-live-banner-dot"></span>'
                  . '<span class="oha-live-banner-label">Cancelled</span></div>';
            $out .= '<div class="oha-live-banner-title">' . esc_html( $title ) . '</div>';
            $out .= $time_el;
            $out .= '</div>';
            $out .= '<div class="oha-live-banner-actions">';
            $out .= '<span class="oha-live-banner-cancelled-msg">This event has been cancelled</span>';
            $out .= '</div>';
            $out .= '</div>';
            $out .= $js;
            return $out;
        }

        $user_can_access = OHA_VE_Frontend::user_has_access( $access_level );

        // ---- LIVE - AUTHORIZED ----
        if ( $user_can_access ) {
            $join_url    = ! empty( $zoom_join ) ? OHA_VE_Frontend::get_protected_join_url( $event_id ) : $permalink;
            $is_external = ! empty( $zoom_join );

            $out  = '<div class="oha-live-banner">';
            $out .= $art;
            $out .= '<div class="oha-live-banner-info">';
            $out .= '<div class="oha-live-banner-meta">';
            $out .= '<span class="oha-live-banner-dot"></span>';
            $out .= '<span class="oha-live-banner-label">Live Now</span>';
            if ( $thumb_url ) $out .= $eq;
            $out .= '</div>';
            $out .= '<div class="oha-live-banner-title">' . esc_html( $title ) . '</div>';
            $out .= $time_el;
            $out .= '</div>';
            $out .= '<div class="oha-live-banner-actions">';
            $out .= '<a href="' . esc_url( $join_url ) . '"'
                  . ( $is_external ? ' target="_blank" rel="noopener noreferrer"' : '' )
                  . ' class="oha-ve-btn oha-ve-btn-live">&#128994; ' . esc_html( $join_label ) . '</a>';
            $out .= '</div>';
            $out .= '</div>';
            return $out;
        }

        // ---- LIVE - LOCKED (not a member) ----
        $out  = '<div class="oha-live-banner oha-live-banner--locked">';
        $out .= $art;
        $out .= '<div class="oha-live-banner-info">';
        $out .= '<div class="oha-live-banner-meta">';
        $out .= '<span class="oha-live-banner-dot"></span>';
        $out .= '<span class="oha-live-banner-label">Members Only</span>';
        $out .= '</div>';
        $out .= '<div class="oha-live-banner-title">A live OHA event is happening now</div>';
        $out .= $time_el;
        $out .= '</div>';
        $out .= '<div class="oha-live-banner-actions">';
        $out .= '<a href="' . esc_url( $atts['membership_url'] ) . '" class="oha-ve-btn oha-ve-btn-secondary">Become a Member</a>';
        $out .= '<a href="' . esc_url( $atts['register_url'] ) . '" class="oha-ve-btn oha-ve-btn-outline" style="border-color:#e0a020;color:#e0a020 !important;">Register</a>';
        $out .= '</div>';
        $out .= '</div>';
        return $out;
    }

    // =========================================================
    // MISC HELPERS
    // =========================================================

    public function maybe_enqueue_styles() {
        global $post;

        if ( ! $post || ! is_a( $post, 'WP_Post' ) ) {
            return;
        }

        $tags = [
            'oha_education_events',
            'oha_education_live',
            'oha_education_upcoming',
            'oha_education_teaching',
            'oha_education_ondemand',
            'oha_live_banner',
        ];

        foreach ( $tags as $tag ) {
            if ( has_shortcode( $post->post_content, $tag ) ) {
                wp_enqueue_style(
                    'oha-virtual-events',
                    OHA_VE_PLUGIN_URL . 'assets/css/oha-virtual-events.css',
                    [],
                    OHA_VE_VERSION
                );
                return;
            }
        }
    }
}
