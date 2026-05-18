<?php
/**
 * OHA Virtual Events - Board Member Event Creation Shortcode
 *
 * Shortcode: [oha_create_virtual_event]
 * Access:    oha_board role and site administrators only.
 *
 * On submit:
 *   1. Creates a gd_event post (published immediately).
 *   2. Inserts a row into wp_geodir_event_schedule for date/time.
 *   3. Sets all oha_ve_ meta fields.
 *   4. Calls Zoom API to create the meeting and auto-fills meeting details.
 *
 * AJAX action: oha_ve_create_event
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OHA_VE_Create {

    public function __construct() {
        add_shortcode( 'oha_create_virtual_event', [ $this, 'render_form' ] );
        add_action( 'wp_ajax_oha_ve_create_event',        [ $this, 'handle_create' ] );
        add_action( 'wp_ajax_nopriv_oha_ve_create_event', [ $this, 'handle_nopriv' ] );
    }

    // =========================================================
    // ACCESS HELPER
    // =========================================================

    private function is_board_or_admin() {
        if ( ! is_user_logged_in() ) {
            return false;
        }
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        $user = wp_get_current_user();
        return in_array( 'oha_board', (array) $user->roles, true );
    }

    // =========================================================
    // SHORTCODE RENDER
    // =========================================================

    public function render_form( $atts ) {
        if ( ! $this->is_board_or_admin() ) {
            return '';
        }

        if ( ! OHA_VE_Settings::is_api_configured() ) {
            return '<div class="oha-create-notice">'
                 . '<strong>Zoom API not configured.</strong> '
                 . 'A site administrator must enter Zoom credentials in <em>Settings &rarr; OHA Virtual Events</em> before virtual events can be created here.'
                 . '</div>';
        }

        $instructors = get_users( [
            'role__in' => [ 'administrator', 'oha_board', 'oha_instructor' ],
            'orderby'  => 'display_name',
            'order'    => 'ASC',
            'fields'   => [ 'ID', 'display_name' ],
        ] );

        $nonce    = wp_create_nonce( 'oha_ve_create_event' );
        $ajax_url = admin_url( 'admin-ajax.php' );

        ob_start();
        ?>
        <div class="oha-create-event-wrap">
            <form id="oha-create-event-form" class="oha-create-event-form" autocomplete="off">
                <h2>Schedule a Virtual Event</h2>

                <div class="oha-create-field">
                    <label for="oha_ce_title">Event Title <span style="color:#d63638;">*</span></label>
                    <input type="text" id="oha_ce_title" name="event_title" required
                           placeholder="e.g. October Actor Meetup" />
                </div>

                <div class="oha-create-row-3">
                    <div class="oha-create-field">
                        <label for="oha_ce_date">Date <span style="color:#d63638;">*</span></label>
                        <input type="date" id="oha_ce_date" name="event_date" required />
                    </div>
                    <div class="oha-create-field">
                        <label for="oha_ce_time">Start Time <span style="color:#d63638;">*</span></label>
                        <input type="time" id="oha_ce_time" name="event_time" required />
                    </div>
                    <div class="oha-create-field">
                        <label for="oha_ce_duration">Duration</label>
                        <select id="oha_ce_duration" name="event_duration">
                            <option value="30">30 minutes</option>
                            <option value="60" selected>60 minutes</option>
                            <option value="90">90 minutes</option>
                            <option value="120">2 hours</option>
                        </select>
                    </div>
                </div>

                <div class="oha-create-row">
                    <div class="oha-create-field">
                        <label for="oha_ce_type">Event Type <span style="color:#d63638;">*</span></label>
                        <select id="oha_ce_type" name="event_type" required>
                            <option value="">- Select -</option>
                            <option value="member_education">Member Education Session</option>
                            <option value="actor_meetup">Actor Meetup</option>
                            <option value="member_townhall">Member Town Hall</option>
                            <option value="other_virtual">Other Virtual Event</option>
                        </select>
                    </div>
                    <div class="oha-create-field">
                        <label for="oha_ce_access">Who Can Attend</label>
                        <select id="oha_ce_access" name="access_level">
                            <option value="subscriber_only" selected>Paid Members Only</option>
                            <option value="all_members">Members + Free Listing Owners</option>
                            <option value="board_only">Board Only</option>
                            <option value="public">Public</option>
                        </select>
                    </div>
                </div>

                <div class="oha-create-field">
                    <label for="oha_ce_instructor">Assigned Instructor / Host</label>
                    <select id="oha_ce_instructor" name="instructor_id">
                        <option value="">- None assigned -</option>
                        <?php foreach ( $instructors as $u ) : ?>
                            <option value="<?php echo esc_attr( $u->ID ); ?>">
                                <?php echo esc_html( $u->display_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="oha-create-hint">The assigned instructor sees a host launch button on the event page.</p>
                </div>

                <div class="oha-create-field">
                    <label for="oha_ce_desc">Description <span style="color:#888;font-weight:400;">(optional)</span></label>
                    <textarea id="oha_ce_desc" name="description"
                              placeholder="Brief description of the event for members."></textarea>
                </div>

                <input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>" />
                <input type="hidden" name="action" value="oha_ve_create_event" />

                <button type="submit" class="oha-create-submit">Create Event + Schedule Zoom Meeting</button>
            </form>

            <div id="oha-create-event-result" class="oha-create-result" style="display:none;"></div>
        </div>

        <script>
        (function() {
            var form   = document.getElementById('oha-create-event-form');
            var result = document.getElementById('oha-create-event-result');
            var btn    = form.querySelector('.oha-create-submit');
            if ( ! form ) return;

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                btn.disabled    = true;
                btn.textContent = 'Creating event...';
                result.style.display = 'none';
                result.className     = 'oha-create-result';

                var fd = new FormData(form);
                fetch('<?php echo esc_url( $ajax_url ); ?>', { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        result.style.display = 'block';
                        if ( data.success ) {
                            result.classList.add('success');
                            result.innerHTML = data.data.message;
                            form.reset();
                        } else {
                            result.classList.add('error');
                            result.textContent = data.data ? data.data.message : 'An error occurred. Please try again.';
                        }
                    })
                    .catch(function() {
                        result.style.display = 'block';
                        result.classList.add('error');
                        result.textContent = 'Request failed. Check your connection and try again.';
                    })
                    .finally(function() {
                        btn.disabled    = false;
                        btn.textContent = 'Create Event + Schedule Zoom Meeting';
                    });
            });
        }());
        </script>
        <?php
        return ob_get_clean();
    }

    // =========================================================
    // AJAX HANDLERS
    // =========================================================

    public function handle_nopriv() {
        wp_send_json_error( [ 'message' => 'You must be logged in to create events.' ] );
    }

    public function handle_create() {

        // Auth and nonce.
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'oha_ve_create_event' ) ) {
            wp_send_json_error( [ 'message' => 'Security check failed. Refresh the page and try again.' ] );
        }

        if ( ! $this->is_board_or_admin() ) {
            wp_send_json_error( [ 'message' => 'You do not have permission to create events.' ] );
        }

        if ( ! OHA_VE_Settings::is_api_configured() ) {
            wp_send_json_error( [ 'message' => 'Zoom API is not configured. Contact an administrator.' ] );
        }

        // Validate required fields.
        $title      = sanitize_text_field( $_POST['event_title'] ?? '' );
        $event_date = sanitize_text_field( $_POST['event_date']  ?? '' );
        $event_time = sanitize_text_field( $_POST['event_time']  ?? '' );
        $event_type = sanitize_text_field( $_POST['event_type']  ?? '' );

        if ( ! $title ) {
            wp_send_json_error( [ 'message' => 'Event title is required.' ] );
        }
        if ( ! $event_date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $event_date ) ) {
            wp_send_json_error( [ 'message' => 'A valid date is required.' ] );
        }
        if ( ! $event_time || ! preg_match( '/^\d{2}:\d{2}$/', $event_time ) ) {
            wp_send_json_error( [ 'message' => 'A valid start time is required.' ] );
        }

        $allowed_types = [ 'member_education', 'pre_recorded', 'actor_meetup', 'member_townhall', 'other_virtual' ];
        if ( ! in_array( $event_type, $allowed_types, true ) ) {
            wp_send_json_error( [ 'message' => 'Please select a valid event type.' ] );
        }

        $allowed_access = [ 'subscriber_only', 'all_members', 'board_only', 'public' ];
        $access_level   = sanitize_text_field( $_POST['access_level'] ?? 'subscriber_only' );
        if ( ! in_array( $access_level, $allowed_access, true ) ) {
            $access_level = 'subscriber_only';
        }

        $duration      = absint( $_POST['event_duration'] ?? 60 );
        $duration      = in_array( $duration, [ 30, 60, 90, 120 ], true ) ? $duration : 60;
        $description   = sanitize_textarea_field( $_POST['description'] ?? '' );
        $instructor_id = absint( $_POST['instructor_id'] ?? 0 );

        // Validate instructor if provided.
        if ( $instructor_id ) {
            $inst_user     = get_userdata( $instructor_id );
            $allowed_roles = [ 'administrator', 'oha_board', 'oha_instructor' ];
            if ( ! $inst_user || empty( array_intersect( (array) $inst_user->roles, $allowed_roles ) ) ) {
                $instructor_id = 0;
            }
        }

        // Create the GD event post.
        $post_id = wp_insert_post( [
            'post_type'    => 'gd_event',
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_content' => $description,
            'post_author'  => get_current_user_id(),
        ], true );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( [ 'message' => 'Failed to create event: ' . $post_id->get_error_message() ] );
        }

        // Insert GD event schedule row.
        $this->insert_event_schedule( $post_id, $event_date, $event_time, $duration );

        // Set OHA Virtual Events meta.
        update_post_meta( $post_id, 'oha_ve_enabled',            '1' );
        update_post_meta( $post_id, 'oha_ve_show_in_hub',        '1' );
        update_post_meta( $post_id, 'oha_ve_platform',           'zoom' );
        update_post_meta( $post_id, 'oha_ve_event_type',         $event_type );
        update_post_meta( $post_id, 'oha_ve_access_level',       $access_level );
        update_post_meta( $post_id, 'oha_ve_event_status',       'upcoming' );
        update_post_meta( $post_id, 'oha_ve_zoom_recording',     '1' );
        if ( $instructor_id ) {
            update_post_meta( $post_id, 'oha_ve_instructor_user_id', $instructor_id );
        }

        // Create Zoom meeting via API.
        $api        = OHA_VE_Zoom_API::get_instance();
        $start_time = $event_date . 'T' . $event_time . ':00';
        $result     = $api->create_meeting( $post_id, [
            'start_time' => $start_time,
            'duration'   => $duration,
            'topic'      => $title,
        ] );

        $event_url = get_permalink( $post_id );

        if ( is_wp_error( $result ) ) {
            // Event was created but Zoom meeting failed — let admin know and provide a link.
            wp_send_json_success( [
                'message' => '<strong>Event created</strong> but Zoom meeting creation failed: '
                           . esc_html( $result->get_error_message() )
                           . ' <a href="' . esc_url( get_edit_post_link( $post_id ) ) . '">Edit the event</a> to add Zoom details manually, '
                           . 'or <a href="' . esc_url( $event_url ) . '">view the event</a>.',
            ] );
        }

        wp_send_json_success( [
            'message' => '<strong>Event created and Zoom meeting scheduled.</strong> '
                       . '<a href="' . esc_url( $event_url ) . '">View the event</a> '
                       . 'or <a href="' . esc_url( get_edit_post_link( $post_id ) ) . '">edit it</a>.',
        ] );
    }

    // =========================================================
    // GD SCHEDULE INSERT
    // =========================================================

    /**
     * Insert a row into the GeoDirectory event schedule table.
     *
     * @param int    $event_id
     * @param string $start_date  YYYY-MM-DD
     * @param string $start_time  HH:MM
     * @param int    $duration    minutes
     */
    private function insert_event_schedule( $event_id, $start_date, $start_time, $duration ) {
        global $wpdb;

        $table = defined( 'GEODIR_EVENT_SCHEDULES_TABLE' )
            ? GEODIR_EVENT_SCHEDULES_TABLE
            : $wpdb->prefix . 'geodir_event_schedule';

        $end_ts   = strtotime( $start_date . ' ' . $start_time ) + ( $duration * 60 );
        $end_time = date( 'H:i:s', $end_ts );

        $wpdb->insert(
            $table,
            [
                'event_id'   => $event_id,
                'start_date' => $start_date,
                'end_date'   => $start_date,
                'start_time' => $start_time . ':00',
                'end_time'   => $end_time,
                'recurring'  => 0,
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%d' ]
        );
    }
}
