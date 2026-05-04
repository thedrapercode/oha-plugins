<?php
/**
 * PWA Push Notifications Handler (v3.7.0 — Perfecty branch)
 *
 * Push subscriptions are now managed entirely by the Perfecty Push plugin.
 * This class retains:
 *   - /pwa/install   — records PWA install events for the stats panel
 *   - /notifications/stats — aggregates install + subscriber counts for admin
 *
 * Subscription subscribe/unsubscribe are handled by the Perfecty Push REST API
 * (/wp-json/perfecty-push/v1/push/users) directly from the browser.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class OHA_PWA_Notifications {

    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_endpoints' ] );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REST endpoint registration
    // ─────────────────────────────────────────────────────────────────────────
    public static function register_endpoints() {

        // Public — record a PWA install event (called from pwa-install.js)
        register_rest_route( 'oha/v1', '/pwa/install', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'record_install' ],
            'permission_callback' => '__return_true',
        ] );

        // Admin-visible stats (aggregate counts only, no sensitive data)
        register_rest_route( 'oha/v1', '/notifications/stats', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'rest_get_stats' ],
            'permission_callback' => '__return_true',
        ] );

        // Board / admin — send a broadcast via Perfecty Push, then spawn cron immediately
        register_rest_route( 'oha/v1', '/notifications/send', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'send_notification' ],
            'permission_callback' => [ __CLASS__, 'can_send_notifications' ],
        ] );

        // Board auth — verifies password server-side, returns short-lived token
        register_rest_route( 'oha/v1', '/board/auth', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'board_auth' ],
            'permission_callback' => '__return_true',
        ] );

        // Board content — returns notes + contacts (requires valid token)
        // POST so token travels in request body, not URL query string or cache.
        register_rest_route( 'oha/v1', '/board/content', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'board_content' ],
            'permission_callback' => '__return_true',
        ] );

        // Volunteer auth — verifies password server-side, returns HTML content
        register_rest_route( 'oha/v1', '/volunteer/auth', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'volunteer_auth' ],
            'permission_callback' => '__return_true',
        ] );

        // Board incident report — save (requires valid board token)
        register_rest_route( 'oha/v1', '/board/incident/save', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'incident_save' ],
            'permission_callback' => '__return_true',
        ] );

        // Board incident report — list all reports (requires valid board token)
        register_rest_route( 'oha/v1', '/board/incident/list', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'incident_list' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public static function can_send_notifications() {
        return current_user_can( 'edit_pages' ) || current_user_can( 'manage_options' );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Brute-force helpers — 10 attempts per IP per 15 min per scope
    // ─────────────────────────────────────────────────────────────────────────
    private static function check_brute_force( $scope ) {
        $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'oha_auth_fail_' . md5( $scope . $ip );
        return ( (int) get_transient( $key ) ) < 10;
    }

    private static function record_auth_fail( $scope ) {
        $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'oha_auth_fail_' . md5( $scope . $ip );
        $n   = (int) get_transient( $key );
        set_transient( $key, $n + 1, 15 * MINUTE_IN_SECONDS );
    }

    private static function clear_auth_fail( $scope ) {
        $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'oha_auth_fail_' . md5( $scope . $ip );
        delete_transient( $key );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Board token helpers — 72-hour transient-backed tokens (covers full convention weekend)
    // ─────────────────────────────────────────────────────────────────────────
    private static function generate_board_token() {
        $token = bin2hex( random_bytes( 16 ) );
        set_transient( 'oha_board_token_' . hash( 'sha256', $token ), 1, 72 * HOUR_IN_SECONDS );
        return $token;
    }

    private static function verify_board_token( $token ) {
        if ( empty( $token ) ) { return false; }
        return (bool) get_transient( 'oha_board_token_' . hash( 'sha256', $token ) );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REST: Board password auth — returns a short-lived token
    // ─────────────────────────────────────────────────────────────────────────
    public static function board_auth( $request ) {
        if ( ! self::check_brute_force( 'board' ) ) {
            return new WP_Error( 'too_many_attempts', 'Too many attempts. Try again later.', [ 'status' => 429 ] );
        }

        $params  = $request->get_json_params();
        $attempt = $params['password'] ?? '';
        $stored  = get_option( 'oha_pwa_board_password', '' );

        $pass_ok = false;
        if ( ! empty( $stored ) ) {
            if ( wp_check_password( $attempt, $stored ) ) {
                // Modern hashed password
                $pass_ok = true;
            } elseif ( hash_equals( $stored, $attempt ) ) {
                // Legacy plaintext — migrate to hash transparently
                update_option( 'oha_pwa_board_password', wp_hash_password( $attempt ) );
                $pass_ok = true;
            }
        }

        if ( ! $pass_ok ) {
            self::record_auth_fail( 'board' );
            return new WP_Error( 'invalid_password', 'Incorrect password.', [ 'status' => 401 ] );
        }

        self::clear_auth_fail( 'board' );
        return rest_ensure_response( [ 'token' => self::generate_board_token() ] );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REST: Board content — notes + contacts (requires valid token)
    // ─────────────────────────────────────────────────────────────────────────
    public static function board_content( $request ) {
        $params = $request->get_json_params();
        $token  = $params['token'] ?? '';
        if ( ! self::verify_board_token( $token ) ) {
            return new WP_Error( 'invalid_token', 'Session expired. Please log in again.', [ 'status' => 401 ] );
        }

        $notes        = wp_kses_post( get_option( 'oha_pwa_board_notes', '' ) );
        $contacts_raw = get_option( 'oha_pwa_board_contacts', '' );
        $contacts     = [];
        foreach ( explode( "\n", $contacts_raw ) as $line ) {
            $line = trim( $line );
            if ( ! $line ) { continue; }
            $parts      = array_map( 'trim', explode( '|', $line ) );
            $contacts[] = [
                'name'  => $parts[0] ?? '',
                'role'  => $parts[1] ?? '',
                'phone' => $parts[2] ?? '',
            ];
        }

        $response = rest_ensure_response( [
            'notes'    => $notes,
            'contacts' => $contacts,
        ] );
        $response->header( 'Cache-Control', 'no-store' );
        return $response;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REST: Volunteer auth — verifies password, returns HTML content
    // ─────────────────────────────────────────────────────────────────────────
    public static function volunteer_auth( $request ) {
        if ( ! self::check_brute_force( 'volunteer' ) ) {
            return new WP_Error( 'too_many_attempts', 'Too many attempts. Try again later.', [ 'status' => 429 ] );
        }

        $params  = $request->get_json_params();
        $attempt = $params['password'] ?? '';
        $stored  = get_option( 'oha_pwa_volunteer_password', '' );

        $pass_ok = false;
        if ( ! empty( $stored ) ) {
            if ( wp_check_password( $attempt, $stored ) ) {
                // Modern hashed password
                $pass_ok = true;
            } elseif ( hash_equals( $stored, $attempt ) ) {
                // Legacy plaintext — migrate to hash transparently
                update_option( 'oha_pwa_volunteer_password', wp_hash_password( $attempt ) );
                $pass_ok = true;
            }
        }

        if ( ! $pass_ok ) {
            self::record_auth_fail( 'volunteer' );
            return new WP_Error( 'invalid_password', 'Incorrect password.', [ 'status' => 401 ] );
        }

        self::clear_auth_fail( 'volunteer' );
        $content = self::build_volunteer_html();
        $response = rest_ensure_response( [ 'content' => $content ] );
        $response->header( 'Cache-Control', 'no-store' );
        return $response;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Builds the full volunteer portal HTML from structured options.
    // Returned as the 'content' field in the volunteer/auth REST response —
    // the client caches this in localStorage and injects it into the page.
    // ─────────────────────────────────────────────────────────────────────────
    private static function build_volunteer_html() {
        $schedule   = get_option( 'oha_pwa_vol_schedule',   '' );
        $venue_info = get_option( 'oha_pwa_vol_venue_info', '' );
        $emergency  = get_option( 'oha_pwa_vol_emergency',  '' );
        $radio      = get_option( 'oha_pwa_vol_radio',      '' );
        $contacts   = get_option( 'oha_pwa_vol_contacts',   '' );
        $extra      = wp_kses_post( get_option( 'oha_pwa_volunteer_content', '' ) );

        $html = '<div class="oha-vol-portal">';

        // ── Event Times ──────────────────────────────────────────────────────
        if ( ! empty( $schedule ) ) {
            $html .= '<div class="oha-vol-section">';
            $html .= '<h3 class="oha-vol-section-title">Event Times</h3>';
            $html .= '<div class="oha-vol-time-list">';
            foreach ( explode( "\n", $schedule ) as $line ) {
                $line  = trim( $line );
                if ( ! $line ) continue;
                $parts = array_map( 'trim', explode( '|', $line ) );
                $label = esc_html( $parts[0] ?? '' );
                $time  = esc_html( $parts[1] ?? '' );
                $notes = esc_html( $parts[2] ?? '' );
                $html .= '<div class="oha-vol-time-item">';
                $html .= '<span class="oha-vol-time-label">' . $label . '</span>';
                if ( $time )  { $html .= '<span class="oha-vol-time-value">' . $time . '</span>'; }
                if ( $notes ) { $html .= '<span class="oha-vol-time-notes">' . $notes . '</span>'; }
                $html .= '</div>';
            }
            $html .= '</div></div>';
        }

        // ── Emergency Information (rendered prominently) ───────────────────
        if ( ! empty( $emergency ) ) {
            $html .= '<div class="oha-vol-section oha-vol-section-emergency">';
            $html .= '<h3 class="oha-vol-section-title oha-vol-title-emergency">Emergency Information</h3>';
            $html .= '<div class="oha-vol-emerg-list">';
            foreach ( explode( "\n", $emergency ) as $line ) {
                $line  = trim( $line );
                if ( ! $line ) continue;
                $parts  = array_map( 'trim', explode( '|', $line ) );
                $label  = esc_html( $parts[0] ?? '' );
                $detail = esc_html( $parts[1] ?? '' );
                $html  .= '<div class="oha-vol-emerg-item">';
                $html  .= '<span class="oha-vol-emerg-label">' . $label . '</span>';
                $html  .= '<span class="oha-vol-emerg-detail">' . $detail . '</span>';
                $html  .= '</div>';
            }
            $html .= '</div></div>';
        }

        // ── Radio Channels ────────────────────────────────────────────────────
        if ( ! empty( $radio ) ) {
            $html .= '<div class="oha-vol-section">';
            $html .= '<h3 class="oha-vol-section-title">Radio Channels</h3>';
            $html .= '<div class="oha-vol-radio-list">';
            foreach ( explode( "\n", $radio ) as $line ) {
                $line  = trim( $line );
                if ( ! $line ) continue;
                $parts   = array_map( 'trim', explode( '|', $line ) );
                $team    = esc_html( $parts[0] ?? '' );
                $channel = esc_html( $parts[1] ?? '' );
                $html   .= '<div class="oha-vol-radio-row">';
                $html   .= '<span class="oha-vol-radio-team">' . $team . '</span>';
                $html   .= '<span class="oha-vol-radio-ch">' . $channel . '</span>';
                $html   .= '</div>';
            }
            $html .= '</div></div>';
        }

        // ── Venue Details ─────────────────────────────────────────────────────
        if ( ! empty( $venue_info ) ) {
            $html .= '<div class="oha-vol-section">';
            $html .= '<h3 class="oha-vol-section-title">Venue Details</h3>';
            $html .= '<div class="oha-vol-venue-text">' . nl2br( esc_html( $venue_info ) ) . '</div>';
            $html .= '</div>';
        }

        // ── Key Contacts ──────────────────────────────────────────────────────
        if ( ! empty( $contacts ) ) {
            $html .= '<div class="oha-vol-section">';
            $html .= '<h3 class="oha-vol-section-title">Key Contacts</h3>';
            $html .= '<div class="oha-vol-contacts-grid">';
            foreach ( explode( "\n", $contacts ) as $line ) {
                $line  = trim( $line );
                if ( ! $line ) continue;
                $parts = array_map( 'trim', explode( '|', $line ) );
                $name  = esc_html( $parts[0] ?? '' );
                $role  = esc_html( $parts[1] ?? '' );
                $phone = esc_html( $parts[2] ?? '' );
                $tel   = preg_replace( '/[^0-9+]/', '', $phone );
                $html .= '<div class="oha-vol-contact-card">';
                $html .= '<p class="oha-vol-contact-name">' . $name . '</p>';
                if ( $role )  { $html .= '<span class="oha-vol-contact-role">' . $role . '</span>'; }
                if ( $tel )   { $html .= '<a class="oha-vol-contact-call" href="tel:' . $tel . '">' . $phone . '</a>'; }
                $html .= '</div>';
            }
            $html .= '</div></div>';
        }

        // ── Additional Notes (free-form HTML) ─────────────────────────────────
        if ( ! empty( trim( $extra ) ) ) {
            $html .= '<div class="oha-vol-section">';
            $html .= '<h3 class="oha-vol-section-title">Additional Notes</h3>';
            $html .= '<div class="oha-vol-extra">' . $extra . '</div>';
            $html .= '</div>';
        }

        // ── Volunteer Schedule (from Volunteer Hub DB if active) ───────────────
        $sched_html = self::build_volunteer_schedule_html();
        if ( $sched_html ) {
            $html .= $sched_html;
        }

        // ── Volunteer Handbook (collapsible, from Volunteer Hub shortcode) ─────
        $handbook_html = self::build_volunteer_handbook_html();
        if ( $handbook_html ) {
            $html .= $handbook_html;
        }

        $html .= '<button class="oha-volunteer-logout">Log Out</button>';
        $html .= '</div>';

        return $html;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Builds the volunteer schedule section from Volunteer Hub DB tables.
    // Shows all dates, areas, and assigned volunteers (first name + last initial).
    // Returns '' if Volunteer Hub tables are not present or no shifts exist.
    // ─────────────────────────────────────────────────────────────────────────
    private static function build_volunteer_schedule_html() {
        global $wpdb;

        $shifts_table = $wpdb->prefix . 'oha_volunteer_shifts';
        if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $shifts_table ) ) ) {
            return '';
        }

        $shift_dates = $wpdb->get_col(
            "SELECT DISTINCT shift_date FROM {$wpdb->prefix}oha_volunteer_shifts ORDER BY shift_date ASC"
        );
        if ( empty( $shift_dates ) ) {
            return '';
        }

        $dates_data = [];

        foreach ( $shift_dates as $date ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT
                    COALESCE(a.alt_name, a.area_name) AS area_name,
                    a.color,
                    a.icon,
                    s.shift_type,
                    s.shift_start,
                    s.shift_end,
                    CASE
                        WHEN asgn.volunteer_id IS NOT NULL
                            THEN CONCAT(v.first_name, ' ', LEFT(v.last_name, 1), '.')
                        ELSE COALESCE(asgn.manual_name, '')
                    END AS vol_name
                FROM {$wpdb->prefix}oha_volunteer_assignments asgn
                LEFT JOIN {$wpdb->prefix}oha_volunteer_shifts s ON asgn.shift_id = s.id
                LEFT JOIN {$wpdb->prefix}oha_volunteer_areas a ON asgn.area_id = a.id
                LEFT JOIN {$wpdb->prefix}oha_volunteers v ON asgn.volunteer_id = v.id
                WHERE s.shift_date = %s
                ORDER BY s.shift_start ASC, a.area_name ASC",
                $date
            ) );

            if ( empty( $rows ) ) continue;

            $slots = [];
            foreach ( $rows as $row ) {
                if ( $row->shift_type === 'all_day' || ! $row->shift_start ) {
                    $tk    = 'all_day';
                    $label = 'All Day';
                } else {
                    $tk    = $row->shift_start . '-' . ( $row->shift_end ?? '' );
                    $label = date( 'g:i A', strtotime( $row->shift_start ) );
                    if ( $row->shift_end ) {
                        $label .= ' - ' . date( 'g:i A', strtotime( $row->shift_end ) );
                    }
                }

                if ( ! isset( $slots[ $tk ] ) ) {
                    $slots[ $tk ] = [ 'label' => $label, 'sort' => $row->shift_start ?? '', 'areas' => [] ];
                }

                $ak = $row->area_name;
                if ( ! isset( $slots[ $tk ]['areas'][ $ak ] ) ) {
                    $slots[ $tk ]['areas'][ $ak ] = [ 'color' => $row->color, 'icon' => $row->icon, 'vols' => [] ];
                }
                if ( $row->vol_name ) {
                    $slots[ $tk ]['areas'][ $ak ]['vols'][] = $row->vol_name;
                }
            }

            // Sort: all_day first, then chronological
            uksort( $slots, function( $a, $b ) use ( $slots ) {
                if ( $a === 'all_day' ) return -1;
                if ( $b === 'all_day' ) return 1;
                return strcmp( $slots[ $a ]['sort'], $slots[ $b ]['sort'] );
            } );

            $dates_data[ $date ] = $slots;
        }

        if ( empty( $dates_data ) ) {
            return '';
        }

        $html  = '<div class="oha-vol-section">';
        $html .= '<h3 class="oha-vol-section-title">Volunteer Schedule</h3>';
        $html .= '<p class="oha-vol-sched-sub">All assigned volunteers by area and shift.</p>';
        $html .= '<div class="oha-vol-sched-wrap">';

        if ( count( $dates_data ) > 1 ) {
            $html .= '<div class="oha-vol-sched-date-tabs">';
            $first = true;
            foreach ( $dates_data as $date => $slots ) {
                $dlabel = date( 'D M j', strtotime( $date ) );
                $active = $first ? ' active' : '';
                $html  .= '<button class="oha-vol-sched-date-btn' . $active . '"'
                        . ' data-date="' . esc_attr( $date ) . '"'
                        . ' onclick="ohaVolSchedSwitch(this,\'' . esc_attr( $date ) . '\')">'
                        . esc_html( $dlabel ) . '</button>';
                $first  = false;
            }
            $html .= '</div>';
        }

        $first = true;
        foreach ( $dates_data as $date => $slots ) {
            $style = $first ? '' : ' style="display:none;"';
            $html .= '<div class="oha-vol-sched-panel" data-date="' . esc_attr( $date ) . '"' . $style . '>';
            foreach ( $slots as $tk => $slot ) {
                $html .= '<div class="oha-vol-sched-slot">';
                $html .= '<div class="oha-vol-sched-time">' . esc_html( $slot['label'] ) . '</div>';
                $html .= '<div class="oha-vol-sched-areas">';
                foreach ( $slot['areas'] as $area_name => $area ) {
                    $color  = esc_attr( $area['color'] ?: '#555555' );
                    $icon   = $area['icon'] ? '<span class="oha-vol-sched-area-icon">' . esc_html( $area['icon'] ) . '</span> ' : '';
                    $html  .= '<div class="oha-vol-sched-area-block" style="border-left-color:' . $color . '">';
                    $html  .= '<div class="oha-vol-sched-area-name">' . $icon . esc_html( $area_name ) . '</div>';
                    if ( ! empty( $area['vols'] ) ) {
                        $html .= '<div class="oha-vol-sched-vols">' . implode( ', ', array_map( 'esc_html', $area['vols'] ) ) . '</div>';
                    }
                    $html .= '</div>';
                }
                $html .= '</div></div>';
            }
            $html .= '</div>';
            $first = false;
        }

        $html .= '</div>'; // .oha-vol-sched-wrap
        $html .= '</div>'; // .oha-vol-section

        return $html;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Renders the Volunteer Handbook as a collapsible section.
    // Uses the Volunteer Hub [oha_volunteer_handbook] shortcode if active.
    // Returns '' if Volunteer Hub is not installed.
    // ─────────────────────────────────────────────────────────────────────────
    private static function build_volunteer_handbook_html() {
        if ( ! shortcode_exists( 'oha_volunteer_handbook' ) ) {
            return '';
        }

        $content = do_shortcode( '[oha_volunteer_handbook]' );
        if ( empty( trim( $content ) ) ) {
            return '';
        }

        $html  = '<div class="oha-vol-section oha-vol-handbook-outer">';
        $html .= '<button class="oha-vol-handbook-toggle" onclick="ohaVolHandbookToggle(this)">';
        $html .= '<h3 class="oha-vol-section-title">Volunteer Handbook</h3>';
        $html .= '<span class="oha-vol-hb-arrow">+</span>';
        $html .= '</button>';
        $html .= '<div class="oha-vol-handbook-body" style="display:none;">';
        $html .= $content;
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REST: Send broadcast notification via Perfecty Push
    // After queuing the notification, spawns wp-cron non-blocking so it goes
    // out in seconds rather than waiting for the next scheduled cron run.
    // ─────────────────────────────────────────────────────────────────────────
    public static function send_notification( $request ) {
        $params  = $request->get_json_params();
        $title   = sanitize_text_field( $params['title']   ?? '' );
        $message = sanitize_textarea_field( $params['message'] ?? '' );
        $url     = esc_url_raw( $params['url']   ?? '' );
        $image   = esc_url_raw( $params['image'] ?? '' );

        if ( empty( $title ) || empty( $message ) ) {
            return new WP_Error( 'missing_fields', 'Title and message are required.', [ 'status' => 400 ] );
        }

        if ( ! class_exists( 'Perfecty_Push_Lib_Payload' ) || ! class_exists( 'Perfecty_Push_Lib_Push_Server' ) ) {
            return new WP_Error( 'perfecty_missing', 'Perfecty Push plugin is not active.', [ 'status' => 503 ] );
        }

        $payload = Perfecty_Push_Lib_Payload::build( $message, $title, $image, $url );
        $id      = Perfecty_Push_Lib_Push_Server::schedule_broadcast_async( $payload );

        if ( $id === false ) {
            return new WP_Error( 'send_failed', 'Could not schedule notification. Check Perfecty Push logs.', [ 'status' => 500 ] );
        }

        // Spawn cron immediately in a non-blocking background request.
        // The user's response returns right away; cron wakes up and Perfecty
        // processes the queue within a few seconds — no 5-minute wait.
        wp_remote_get(
            add_query_arg( 'doing_wp_cron', '', site_url( 'wp-cron.php' ) ),
            [ 'timeout' => 0.01, 'blocking' => false, 'sslverify' => false ]
        );

        global $wpdb;
        $recipients = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}perfecty_push_users"
        );

        return rest_ensure_response( [ 'id' => $id, 'recipients' => $recipients ] );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REST: Incident report — save (board token required)
    // Accepts text fields + up to 4 base64-encoded photos.
    // Photos are decoded and written to uploads/oha-incidents/YYYY-MM/.
    // ─────────────────────────────────────────────────────────────────────────
    public static function incident_save( $request ) {
        $params = $request->get_json_params();
        $token  = $params['token'] ?? '';
        if ( ! self::verify_board_token( $token ) ) {
            return new WP_Error( 'invalid_token', 'Session expired. Please log in again.', [ 'status' => 401 ] );
        }

        global $wpdb;

        // Save photos
        $photo_urls = [];
        $photos     = is_array( $params['photos'] ?? null ) ? $params['photos'] : [];
        if ( ! empty( $photos ) ) {
            $upload      = wp_upload_dir();
            $subdir      = 'oha-incidents/' . current_time( 'Y-m' );
            $dir_path    = $upload['basedir'] . '/' . $subdir;
            $dir_url     = $upload['baseurl'] . '/' . $subdir;
            wp_mkdir_p( $dir_path );

            foreach ( array_slice( $photos, 0, 4 ) as $i => $photo ) {
                $raw  = $photo['data'] ?? '';
                $mime = $photo['mime'] ?? 'image/jpeg';
                if ( strpos( $raw, ',' ) !== false ) {
                    $raw = explode( ',', $raw )[1];
                }
                $decoded = base64_decode( $raw, true );
                if ( ! $decoded ) { continue; }
                $ext      = ( $mime === 'image/png' ) ? 'png' : 'jpg';
                $filename = 'incident-' . current_time( 'Ymd-His' ) . '-' . $i . '.' . $ext;
                if ( file_put_contents( $dir_path . '/' . $filename, $decoded ) !== false ) {
                    $photo_urls[] = $dir_url . '/' . $filename;
                }
            }
        }

        // Parse datetime-local (YYYY-MM-DDTHH:MM) to MySQL datetime
        $raw_time = sanitize_text_field( $params['incident_time'] ?? '' );
        try {
            $incident_time = ( new DateTime( $raw_time ) )->format( 'Y-m-d H:i:s' );
        } catch ( Exception $e ) {
            $incident_time = current_time( 'mysql' );
        }

        $wpdb->insert(
            $wpdb->prefix . 'oha_incident_reports',
            [
                'reported_by'     => sanitize_text_field( $params['reported_by']     ?? '' ),
                'incident_time'   => $incident_time,
                'location'        => sanitize_text_field( $params['location']        ?? '' ),
                'incident_type'   => sanitize_text_field( $params['incident_type']   ?? '' ),
                'people_involved' => sanitize_textarea_field( $params['people_involved'] ?? '' ),
                'description'     => sanitize_textarea_field( $params['description'] ?? '' ),
                'action_taken'    => sanitize_textarea_field( $params['action_taken'] ?? '' ),
                'witnesses'       => sanitize_textarea_field( $params['witnesses']   ?? '' ),
                'photo_urls'      => implode( ',', $photo_urls ),
                'created_at'      => current_time( 'mysql' ),
            ]
        );

        if ( $wpdb->last_error ) {
            return new WP_Error( 'db_error', 'Could not save report.', [ 'status' => 500 ] );
        }

        $response = rest_ensure_response( [ 'id' => $wpdb->insert_id, 'photo_urls' => $photo_urls ] );
        $response->header( 'Cache-Control', 'no-store' );
        return $response;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REST: Incident report — list (board token required)
    // Returns up to 100 most recent reports, newest first.
    // ─────────────────────────────────────────────────────────────────────────
    public static function incident_list( $request ) {
        $params = $request->get_json_params();
        $token  = $params['token'] ?? '';
        if ( ! self::verify_board_token( $token ) ) {
            return new WP_Error( 'invalid_token', 'Session expired. Please log in again.', [ 'status' => 401 ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oha_incident_reports';
        $rows  = $wpdb->get_results(
            "SELECT id, reported_by, incident_time, location, incident_type,
                    people_involved, description, action_taken, witnesses, photo_urls, created_at
             FROM $table ORDER BY incident_time DESC LIMIT 100",
            ARRAY_A
        );

        $reports = array_map( function( $row ) {
            $row['photo_urls'] = $row['photo_urls'] ? array_filter( explode( ',', $row['photo_urls'] ) ) : [];
            return $row;
        }, (array) $rows );

        $response = rest_ensure_response( [ 'reports' => array_values( $reports ) ] );
        $response->header( 'Cache-Control', 'no-store' );
        return $response;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Record PWA install event (called from pwa-install.js after appinstalled)
    // ─────────────────────────────────────────────────────────────────────────
    public static function record_install( $request ) {
        global $wpdb;

        $params     = $request->get_json_params();
        $platform   = sanitize_text_field( $params['platform']   ?? 'other' );
        $standalone = ! empty( $params['standalone'] ) ? 1 : 0;
        $user_agent = sanitize_text_field( substr( $params['user_agent'] ?? '', 0, 255 ) );
        $user_id    = get_current_user_id() ?: null;

        $table = $wpdb->prefix . 'oha_pwa_installs';

        // Avoid duplicate rows: one install per user_agent per day
        $today    = current_time( 'Y-m-d' );
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table WHERE user_agent = %s AND DATE(install_date) = %s",
            $user_agent,
            $today
        ) );

        if ( ! $existing ) {
            $wpdb->insert( $table, [
                'user_id'       => $user_id,
                'user_agent'    => $user_agent,
                'platform'      => $platform,
                'is_standalone' => $standalone,
                'install_date'  => current_time( 'mysql' ),
                'last_visit'    => current_time( 'mysql' ),
            ] );
        } else {
            $wpdb->update(
                $table,
                [ 'last_visit' => current_time( 'mysql' ) ],
                [ 'id' => $existing ]
            );
        }

        return [ 'success' => true ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REST: Stats
    // Subscriber count is pulled from Perfecty Push's users table.
    // Platform breakdown comes from our install tracking table.
    // ─────────────────────────────────────────────────────────────────────────
    public static function rest_get_stats( $request ) {
        global $wpdb;

        // Perfecty Push subscriber count (all rows = active subscriptions;
        // Perfecty deletes rows on unsubscribe rather than setting is_active=0).
        $perfecty_table = $wpdb->prefix . 'perfecty_push_users';
        $subscribers    = 0;
        if ( $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $perfecty_table
        ) ) === $perfecty_table ) {
            $subscribers = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $perfecty_table" );
        }

        // OHA install tracking
        $install_table  = $wpdb->prefix . 'oha_pwa_installs';
        $total_installs = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $install_table" );

        $platform_counts = $wpdb->get_results(
            "SELECT platform, COUNT(*) as cnt FROM $install_table GROUP BY platform",
            ARRAY_A
        );
        $platforms = [ 'ios' => 0, 'android' => 0, 'other' => 0 ];
        foreach ( (array) $platform_counts as $row ) {
            $p = $row['platform'] ?? 'other';
            if ( isset( $platforms[ $p ] ) ) {
                $platforms[ $p ] += (int) $row['cnt'];
            } else {
                $platforms['other'] += (int) $row['cnt'];
            }
        }

        // Active installs (last_visit within past 30 days)
        $active_installs = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $install_table WHERE last_visit >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        return rest_ensure_response( [
            'subscribers'     => $subscribers,
            'installs'        => $total_installs,
            'active_installs' => $active_installs,
            'platforms'       => $platforms,
        ] );
    }
}
