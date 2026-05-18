<?php
/**
 * OHA Virtual Events - Admin Settings Page
 *
 * Adds Settings > OHA Virtual Events to the WordPress admin.
 * Stores Zoom Server-to-Server OAuth credentials, default meeting settings,
 * and automation toggles. Also provides a shortcode reference and role setup guide.
 *
 * OPTION KEYS (stored via get_option / update_option):
 *   oha_zoom_account_id     - Zoom Account ID (from marketplace.zoom.us)
 *   oha_zoom_client_id      - Zoom Client ID
 *   oha_zoom_client_secret  - Zoom Client Secret
 *   oha_zoom_webhook_secret - Webhook Secret Token (for signature verification)
 *   oha_zoom_auto_create    - '1' to auto-create meetings when event is published
 *   oha_zoom_auto_record    - '1' to enable cloud auto-recording by default
 *   oha_zoom_waiting_room   - '1' to enable waiting room by default
 *   oha_zoom_host_video     - '1' to start host video on by default
 *   oha_zoom_default_duration - default meeting duration in minutes (integer)
 *
 * SECURITY: All settings pages require manage_options capability.
 *           Password fields (secret/token) render empty and preserve existing
 *           value on save if left blank.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OHA_VE_Settings {

    const OPT_ACCOUNT_ID        = 'oha_zoom_account_id';
    const OPT_CLIENT_ID         = 'oha_zoom_client_id';
    const OPT_CLIENT_SECRET     = 'oha_zoom_client_secret';
    const OPT_WEBHOOK_SECRET    = 'oha_zoom_webhook_secret';
    const OPT_AUTO_CREATE       = 'oha_zoom_auto_create';
    const OPT_AUTO_RECORD       = 'oha_zoom_auto_record';
    const OPT_WAITING_ROOM      = 'oha_zoom_waiting_room';
    const OPT_JOIN_BEFORE_HOST  = 'oha_zoom_join_before_host';
    const OPT_HOST_VIDEO        = 'oha_zoom_host_video';
    const OPT_DURATION          = 'oha_zoom_default_duration';
    const OPT_HOST_KEY          = 'oha_zoom_host_key';
    const OPT_INSTRUCTOR_WINDOW = 'oha_ve_instructor_window';
    const OPT_MEMBER_WINDOW     = 'oha_ve_member_window';

    public function __construct() {
        add_action( 'admin_menu',    [ $this, 'add_settings_page' ] );
        add_action( 'admin_init',    [ $this, 'register_settings' ] );
        add_action( 'admin_notices', [ $this, 'maybe_show_setup_notice' ] );
        add_action( 'wp_ajax_oha_ve_test_zoom_connection', [ $this, 'ajax_test_connection' ] );
    }

    // =========================================================
    // MENU
    // =========================================================

    public function add_settings_page() {
        add_options_page(
            'OHA Virtual Events Settings',
            'OHA Virtual Events',
            'manage_options',
            'oha-virtual-events',
            [ $this, 'render_settings_page' ]
        );
    }

    // =========================================================
    // SETTINGS REGISTRATION
    // =========================================================

    public function register_settings() {

        // Plain text fields
        foreach ( [ self::OPT_ACCOUNT_ID, self::OPT_CLIENT_ID ] as $opt ) {
            register_setting( 'oha_ve_settings_group', $opt, [
                'sanitize_callback' => 'sanitize_text_field',
            ] );
        }

        // Password fields - preserve existing value if blank on save
        foreach ( [ self::OPT_CLIENT_SECRET, self::OPT_WEBHOOK_SECRET ] as $opt ) {
            register_setting( 'oha_ve_settings_group', $opt, [
                'sanitize_callback' => function ( $new_val ) use ( $opt ) {
                    if ( '' === $new_val || null === $new_val ) {
                        return get_option( $opt, '' );
                    }
                    return sanitize_text_field( $new_val );
                },
            ] );
        }

        // Checkbox fields (value '1' or '0')
        foreach ( [ self::OPT_AUTO_CREATE, self::OPT_AUTO_RECORD, self::OPT_WAITING_ROOM, self::OPT_JOIN_BEFORE_HOST, self::OPT_HOST_VIDEO ] as $opt ) {
            register_setting( 'oha_ve_settings_group', $opt, [
                'sanitize_callback' => function ( $val ) {
                    return ( '1' === $val ) ? '1' : '0';
                },
            ] );
        }

        // Host key: preserve existing if blank (same pattern as secrets)
        register_setting( 'oha_ve_settings_group', self::OPT_HOST_KEY, [
            'sanitize_callback' => function ( $new_val ) {
                if ( '' === $new_val || null === $new_val ) {
                    return get_option( self::OPT_HOST_KEY, '' );
                }
                return sanitize_text_field( $new_val );
            },
        ] );

        // Timing windows: positive integers
        register_setting( 'oha_ve_settings_group', self::OPT_INSTRUCTOR_WINDOW, [
            'sanitize_callback' => function ( $val ) {
                $n = absint( $val );
                return ( $n >= 0 && $n <= 1440 ) ? $n : 30;
            },
        ] );
        register_setting( 'oha_ve_settings_group', self::OPT_MEMBER_WINDOW, [
            'sanitize_callback' => function ( $val ) {
                $n = absint( $val );
                return ( $n >= 0 && $n <= 1440 ) ? $n : 15;
            },
        ] );

        // Duration: positive integer 15-480
        register_setting( 'oha_ve_settings_group', self::OPT_DURATION, [
            'sanitize_callback' => function ( $val ) {
                $n = absint( $val );
                return ( $n >= 15 && $n <= 480 ) ? $n : 60;
            },
        ] );

        // --- Section: Zoom API Credentials ---
        add_settings_section(
            'oha_ve_zoom_credentials',
            'Zoom Server-to-Server OAuth Credentials',
            [ $this, 'render_credentials_section_intro' ],
            'oha-virtual-events'
        );

        add_settings_field(
            self::OPT_ACCOUNT_ID, 'Account ID',
            [ $this, 'render_text_field' ], 'oha-virtual-events', 'oha_ve_zoom_credentials',
            [ 'option' => self::OPT_ACCOUNT_ID, 'desc' => 'Found in your Zoom Server-to-Server OAuth app at marketplace.zoom.us.' ]
        );
        add_settings_field(
            self::OPT_CLIENT_ID, 'Client ID',
            [ $this, 'render_text_field' ], 'oha-virtual-events', 'oha_ve_zoom_credentials',
            [ 'option' => self::OPT_CLIENT_ID, 'desc' => '' ]
        );
        add_settings_field(
            self::OPT_CLIENT_SECRET, 'Client Secret',
            [ $this, 'render_password_field' ], 'oha-virtual-events', 'oha_ve_zoom_credentials',
            [ 'option' => self::OPT_CLIENT_SECRET, 'desc' => 'Stored in wp_options. Leave blank to keep existing value.' ]
        );
        add_settings_field(
            self::OPT_WEBHOOK_SECRET, 'Webhook Secret Token',
            [ $this, 'render_password_field' ], 'oha-virtual-events', 'oha_ve_zoom_credentials',
            [ 'option' => self::OPT_WEBHOOK_SECRET, 'desc' => 'The Secret Token from your Zoom webhook subscription. Used to verify all incoming webhook requests are genuinely from Zoom. Leave blank to keep existing value.' ]
        );

        // --- Section: Default Meeting Settings ---
        add_settings_section(
            'oha_ve_meeting_defaults',
            'Default Meeting Settings',
            [ $this, 'render_meeting_defaults_intro' ],
            'oha-virtual-events'
        );

        add_settings_field(
            self::OPT_AUTO_RECORD, 'Auto-Record to Zoom Cloud',
            [ $this, 'render_checkbox_field' ], 'oha-virtual-events', 'oha_ve_meeting_defaults',
            [ 'option' => self::OPT_AUTO_RECORD, 'label' => 'Automatically record all meetings to Zoom Cloud (recommended)' ]
        );
        add_settings_field(
            self::OPT_JOIN_BEFORE_HOST, 'Allow Join Before Host',
            [ $this, 'render_checkbox_field' ], 'oha-virtual-events', 'oha_ve_meeting_defaults',
            [ 'option' => self::OPT_JOIN_BEFORE_HOST, 'label' => 'Let participants join before the host starts (required for instructor host key workflow - keep ON)' ]
        );
        add_settings_field(
            self::OPT_WAITING_ROOM, 'Waiting Room',
            [ $this, 'render_checkbox_field' ], 'oha-virtual-events', 'oha_ve_meeting_defaults',
            [ 'option' => self::OPT_WAITING_ROOM, 'label' => 'Enable waiting room (leave OFF when using Join Before Host - the two conflict)' ]
        );
        add_settings_field(
            self::OPT_HOST_VIDEO, 'Host Video On',
            [ $this, 'render_checkbox_field' ], 'oha-virtual-events', 'oha_ve_meeting_defaults',
            [ 'option' => self::OPT_HOST_VIDEO, 'label' => 'Start host video on when the meeting begins' ]
        );
        add_settings_field(
            self::OPT_DURATION, 'Default Duration (minutes)',
            [ $this, 'render_number_field' ], 'oha-virtual-events', 'oha_ve_meeting_defaults',
            [ 'option' => self::OPT_DURATION, 'min' => 15, 'max' => 480, 'default' => 60, 'desc' => 'Applied when creating meetings. Minimum 15, maximum 480.' ]
        );

        // --- Section: Host Key and Event Timing ---
        add_settings_section(
            'oha_ve_host_timing',
            'Host Key and Event Timing',
            [ $this, 'render_host_timing_intro' ],
            'oha-virtual-events'
        );

        add_settings_field(
            self::OPT_HOST_KEY, 'Zoom Host Key',
            [ $this, 'render_password_field' ], 'oha-virtual-events', 'oha_ve_host_timing',
            [ 'option' => self::OPT_HOST_KEY, 'desc' => 'Your 6-digit Zoom host key. Found in your Zoom profile at zoom.us/profile. Shown only to the assigned instructor on the event page so they can claim host.' ]
        );
        add_settings_field(
            self::OPT_INSTRUCTOR_WINDOW, 'Instructor Button Opens (minutes before)',
            [ $this, 'render_number_field' ], 'oha-virtual-events', 'oha_ve_host_timing',
            [ 'option' => self::OPT_INSTRUCTOR_WINDOW, 'min' => 0, 'max' => 1440, 'default' => 30, 'desc' => 'How many minutes before the event start time the host launch button appears for the assigned instructor. Default: 30.' ]
        );
        add_settings_field(
            self::OPT_MEMBER_WINDOW, 'Member Join Button Opens (minutes before)',
            [ $this, 'render_number_field' ], 'oha-virtual-events', 'oha_ve_host_timing',
            [ 'option' => self::OPT_MEMBER_WINDOW, 'min' => 0, 'max' => 1440, 'default' => 15, 'desc' => 'How many minutes before the event start time the Join Now button appears for paid members. Default: 15.' ]
        );

        // --- Section: Automation ---
        add_settings_section(
            'oha_ve_automation',
            'Automation',
            '__return_false',
            'oha-virtual-events'
        );

        add_settings_field(
            self::OPT_AUTO_CREATE, 'Auto-Create Zoom Meetings',
            [ $this, 'render_checkbox_field' ], 'oha-virtual-events', 'oha_ve_automation',
            [ 'option' => self::OPT_AUTO_CREATE, 'label' => 'Automatically create a Zoom meeting when a virtual event with platform "Zoom" or "Both" is published (requires credentials above to be filled in)' ]
        );
    }

    // =========================================================
    // SECTION INTROS
    // =========================================================

    public function render_credentials_section_intro() {
        $webhook_url = rest_url( 'oha-ve/v1/zoom-webhook' );
        echo '<p>Enter your Zoom Server-to-Server OAuth app credentials. '
           . 'Create the app at <strong>marketplace.zoom.us &rarr; Develop &rarr; Build App &rarr; Server-to-Server OAuth</strong>.</p>'
           . '<p>Required scopes: <code>meeting:write:admin</code>, <code>meeting:read:admin</code>, <code>recording:read:admin</code>.</p>'
           . '<p><strong>Webhook URL</strong> (paste this into your Zoom app webhook subscription):<br>'
           . '<code style="user-select:all;background:#f0f0f0;padding:4px 10px;border-radius:3px;font-size:13px;">'
           . esc_url( $webhook_url )
           . '</code></p>';
    }

    public function render_meeting_defaults_intro() {
        echo '<p>These defaults are applied when meetings are created via the board event form or auto-created on event publish.</p>';
    }

    public function render_host_timing_intro() {
        echo '<p>The host key is shown only to the assigned instructor on the event page. '
           . 'Timing windows control when buttons appear relative to the event start time. '
           . 'Set both to 0 to always show buttons.</p>';
    }

    // =========================================================
    // FIELD RENDERERS
    // =========================================================

    public function render_text_field( $args ) {
        $option = $args['option'];
        $value  = get_option( $option, '' );
        $desc   = $args['desc'] ?? '';
        echo '<input type="text" name="' . esc_attr( $option ) . '" '
           . 'id="' . esc_attr( $option ) . '" '
           . 'value="' . esc_attr( $value ) . '" '
           . 'class="regular-text" autocomplete="off" />';
        if ( $desc ) {
            echo '<p class="description">' . esc_html( $desc ) . '</p>';
        }
    }

    public function render_password_field( $args ) {
        $option      = $args['option'];
        $existing    = get_option( $option, '' );
        $desc        = $args['desc'] ?? '';
        $placeholder = $existing ? '-- saved (enter new value to change) --' : '';
        echo '<input type="password" name="' . esc_attr( $option ) . '" '
           . 'id="' . esc_attr( $option ) . '" '
           . 'value="" '
           . 'placeholder="' . esc_attr( $placeholder ) . '" '
           . 'class="regular-text" autocomplete="new-password" />';
        if ( $desc ) {
            echo '<p class="description">' . esc_html( $desc ) . '</p>';
        }
    }

    public function render_checkbox_field( $args ) {
        $option = $args['option'];
        $value  = get_option( $option, '1' );
        $label  = $args['label'] ?? '';
        echo '<label>'
           . '<input type="checkbox" name="' . esc_attr( $option ) . '" '
           . 'id="' . esc_attr( $option ) . '" '
           . 'value="1" ' . checked( $value, '1', false ) . ' />'
           . ' ' . esc_html( $label )
           . '</label>';
    }

    public function render_number_field( $args ) {
        $option  = $args['option'];
        $default = $args['default'] ?? 60;
        $value   = get_option( $option, $default );
        $min     = $args['min'] ?? 0;
        $max     = $args['max'] ?? 9999;
        $desc    = $args['desc'] ?? '';
        echo '<input type="number" name="' . esc_attr( $option ) . '" '
           . 'id="' . esc_attr( $option ) . '" '
           . 'value="' . esc_attr( $value ) . '" '
           . 'min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" '
           . 'class="small-text" />';
        if ( $desc ) {
            echo '<p class="description">' . esc_html( $desc ) . '</p>';
        }
    }

    // =========================================================
    // AJAX: TEST CONNECTION
    // =========================================================

    public function ajax_test_connection() {
        check_ajax_referer( 'oha_ve_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
        }

        if ( ! self::is_api_configured() ) {
            wp_send_json_error( [ 'message' => 'Zoom credentials are not filled in. Enter Account ID, Client ID, and Client Secret and save first.' ] );
        }

        $api   = OHA_VE_Zoom_API::get_instance();
        $token = $api->get_access_token( true ); // true = force fresh fetch

        if ( is_wp_error( $token ) ) {
            wp_send_json_error( [ 'message' => 'Connection failed: ' . $token->get_error_message() ] );
        }

        wp_send_json_success( [ 'message' => 'Connected successfully - Zoom access token obtained.' ] );
    }

    // =========================================================
    // SETUP NOTICE (shown on gd_event edit screens and settings page)
    // =========================================================

    public function maybe_show_setup_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }

        $show_on = [ 'gd_event', 'settings_page_oha-virtual-events' ];
        if ( ! in_array( $screen->id, $show_on, true ) ) {
            return;
        }

        if ( ! self::is_api_configured() ) {
            $url = admin_url( 'options-general.php?page=oha-virtual-events' );
            echo '<div class="notice notice-info is-dismissible"><p>';
            echo '<strong>OHA Virtual Events (Phase 2):</strong> Zoom API credentials are not configured. ';
            echo '<a href="' . esc_url( $url ) . '">Configure in Settings &rarr; OHA Virtual Events</a> to enable automatic meeting creation.';
            echo '</p></div>';
        }
    }

    // =========================================================
    // SETTINGS PAGE RENDER
    // =========================================================

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $api_configured = self::is_api_configured();
        $webhook_url    = rest_url( 'oha-ve/v1/zoom-webhook' );
        $nonce          = wp_create_nonce( 'oha_ve_admin_nonce' );
        ?>
        <div class="wrap">
            <h1>OHA Virtual Events Settings</h1>
            <p style="color:#666;">Plugin version: <strong><?php echo esc_html( OHA_VE_VERSION ); ?></strong></p>

            <?php if ( $api_configured ) : ?>
                <div class="notice notice-success inline" style="margin:10px 0 20px;">
                    <p>&#10003; <strong>Zoom API credentials are configured.</strong>
                    Auto-create and webhooks are available.
                    <button type="button" id="oha-ve-test-btn" class="button button-small" style="margin-left:12px;">Test Connection</button>
                    <span id="oha-ve-test-result" style="margin-left:10px;font-style:italic;"></span>
                    </p>
                </div>
            <?php else : ?>
                <div class="notice notice-warning inline" style="margin:10px 0 20px;">
                    <p>&#9888; <strong>Zoom API credentials not yet configured.</strong>
                    Fill in the fields below and save to enable Phase 2 automation.
                    <button type="button" id="oha-ve-test-btn" class="button button-small" style="margin-left:12px;">Test Connection</button>
                    <span id="oha-ve-test-result" style="margin-left:10px;font-style:italic;"></span>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'oha_ve_settings_group' );
                do_settings_sections( 'oha-virtual-events' );
                submit_button( 'Save Settings' );
                ?>
            </form>

            <script>
            (function() {
                var btn    = document.getElementById('oha-ve-test-btn');
                var result = document.getElementById('oha-ve-test-result');
                if ( ! btn ) return;
                btn.addEventListener('click', function() {
                    btn.disabled = true;
                    result.style.color = '#666';
                    result.textContent = 'Testing...';
                    var fd = new FormData();
                    fd.append('action', 'oha_ve_test_zoom_connection');
                    fd.append('nonce',  '<?php echo esc_js( $nonce ); ?>');
                    fetch(ajaxurl, { method: 'POST', body: fd })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            result.style.color = data.success ? 'green' : '#d63638';
                            result.textContent = data.data ? data.data.message : (data.success ? 'OK' : 'Error');
                        })
                        .catch(function() {
                            result.style.color = '#d63638';
                            result.textContent = 'Request failed.';
                        })
                        .finally(function() { btn.disabled = false; });
                });
            }());
            </script>

            <hr style="margin:30px 0;">

            <!-- WEBHOOK SETUP GUIDE -->
            <h2>Webhook Setup Guide</h2>
            <p>In your Zoom app at <strong>marketplace.zoom.us</strong>, add an event notification endpoint:</p>
            <p>
                <code style="font-size:14px;user-select:all;background:#f0f0f0;padding:6px 14px;border-radius:4px;display:inline-block;">
                    <?php echo esc_url( $webhook_url ); ?>
                </code>
            </p>
            <p>Enable these event subscriptions:</p>
            <ul style="list-style:disc;margin-left:24px;line-height:2;">
                <li><code>meeting.started</code> - Auto-sets event status to <strong>Live Now</strong></li>
                <li><code>meeting.ended</code> - Auto-sets event status to <strong>Closed</strong></li>
                <li><code>recording.completed</code> - Sets status to <strong>Replay</strong> and appends the Zoom share URL to the event's Admin Notes for retrieval</li>
            </ul>
            <p style="color:#666;font-size:13px;">After adding the endpoint, Zoom will send a validation challenge. The plugin handles this automatically using your Webhook Secret Token. Copy the Secret Token from Zoom and paste it into the field above.</p>

            <hr style="margin:30px 0;">

            <!-- SHORTCODES REFERENCE -->
            <h2>Shortcode Reference</h2>
            <p>Place these shortcodes in the <strong>My Account &rarr; Education &amp; Events</strong> tab content (UsersWP account tab).</p>

            <table class="widefat striped" style="max-width:960px;margin-bottom:24px;">
                <thead>
                    <tr>
                        <th style="width:230px;">Shortcode</th>
                        <th>What It Shows</th>
                        <th style="width:160px;">Attribute</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>[oha_education_events]</code></td>
                        <td>
                            <strong>Full Education Hub</strong> - all four sections stacked in order:
                            Live Now, Assigned to Teach, Upcoming Events, On Demand library.
                            Best choice for the main Education &amp; Events tab - one shortcode covers everything.
                        </td>
                        <td><code>limit</code><br><small>Applies to each section individually</small></td>
                    </tr>
                    <tr>
                        <td><code>[oha_education_live]</code></td>
                        <td>
                            <strong>Live Now</strong> - events whose status is set to <em>Live Now</em>.
                            Shows a Join Now button for members, or Start Session for instructors and board.
                            Returns empty (no heading) when no events are currently live - safe to include always.
                        </td>
                        <td><code>limit</code><br><small>Default: unlimited</small></td>
                    </tr>
                    <tr>
                        <td><code>[oha_education_upcoming]</code></td>
                        <td>
                            <strong>Upcoming Events</strong> - future scheduled events sorted soonest-first
                            by the Start Date field. Respects the user's access level - members only see events
                            they are allowed to attend. Shows a View Details button linking to the event page.
                        </td>
                        <td><code>limit</code><br><small>Default: 10</small></td>
                    </tr>
                    <tr>
                        <td><code>[oha_education_teaching]</code></td>
                        <td>
                            <strong>Assigned to Teach</strong> - upcoming and live events where the logged-in
                            user is the assigned instructor. Shows the Start Session button. Returns empty output
                            if the user has no assigned sessions. Ignores the "Show in Hub" checkbox - instructors
                            always see their own events.
                        </td>
                        <td><code>limit</code><br><small>Default: unlimited</small></td>
                    </tr>
                    <tr>
                        <td><code>[oha_education_ondemand]</code></td>
                        <td>
                            <strong>On Demand Library</strong> - replay videos available to watch. An event appears
                            here only when ALL of these are true: Status = Replay/On Demand, Archive Recording is
                            checked, Replay is Ready is checked, and a YouTube Replay Video ID is filled in.
                            Shows a Watch Now button.
                        </td>
                        <td><code>limit</code><br><small>Default: unlimited</small></td>
                    </tr>
                </tbody>
            </table>

            <h3>Access Level Reference</h3>
            <table class="widefat striped" style="max-width:680px;margin-bottom:24px;">
                <thead>
                    <tr>
                        <th style="width:200px;">Setting</th>
                        <th>Who Can View the Event</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>public</code></td>
                        <td>Everyone - no login required</td>
                    </tr>
                    <tr>
                        <td><code>all_members</code></td>
                        <td>Paid members (<code>subscriber</code>) and free listing owners (<code>listing_owner</code>)</td>
                    </tr>
                    <tr>
                        <td><code>subscriber_only</code></td>
                        <td>Paid OHA members only (<code>subscriber</code> role)</td>
                    </tr>
                    <tr>
                        <td><code>board_only</code></td>
                        <td>Board members (<code>oha_board</code> role) and site administrators only</td>
                    </tr>
                </tbody>
            </table>

            <h3>One-Time Setup: Register the oha_instructor Role</h3>
            <p>The <code>oha_instructor</code> role must be registered on the site once before instructors can be assigned.
            Add this snippet to <strong>Code Snippets</strong>, run once, then disable:</p>
            <pre style="background:#f0f0f0;padding:14px;border-radius:4px;max-width:580px;overflow:auto;">add_action( 'init', function () {
    if ( ! get_role( 'oha_instructor' ) ) {
        add_role( 'oha_instructor', 'OHA Instructor', [] );
    }
} );</pre>
            <p>Once registered, go to <strong>Users &rarr; Edit User &rarr; OHA Virtual Events section &rarr; Grant OHA Instructor</strong> to assign the role.</p>

            <h3>Replay Workflow (manual - until Phase 3)</h3>
            <ol style="line-height:2;max-width:680px;">
                <li>After the session ends, Zoom cloud recording finishes processing (usually 15-60 min).</li>
                <li>The <code>recording.completed</code> webhook automatically appends the Zoom share URL to the event's <strong>Admin Notes</strong> field.</li>
                <li>Download or retrieve the recording, upload to <strong>YouTube as unlisted</strong>.</li>
                <li>Copy the YouTube video ID (the part after <code>?v=</code>) and paste into the event's <strong>YouTube Replay/Recording Video ID</strong> field.</li>
                <li>Check <strong>Replay is Ready</strong> in the event editor.</li>
                <li>The video now appears in the member On Demand library.</li>
            </ol>

        </div>
        <?php
    }

    // =========================================================
    // STATIC HELPERS (used by other classes)
    // =========================================================

    /**
     * Returns true if all three Zoom OAuth credentials are configured.
     *
     * @return bool
     */
    public static function is_api_configured() {
        return ! empty( get_option( self::OPT_ACCOUNT_ID ) )
            && ! empty( get_option( self::OPT_CLIENT_ID ) )
            && ! empty( get_option( self::OPT_CLIENT_SECRET ) );
    }

    /**
     * Returns default meeting settings as an array for use by OHA_VE_Zoom_API.
     *
     * @return array {
     *   auto_record  string '1' or '0'
     *   waiting_room string '1' or '0'
     *   host_video   string '1' or '0'
     *   duration     int    minutes
     * }
     */
    public static function get_meeting_defaults() {
        return [
            'auto_record'       => get_option( self::OPT_AUTO_RECORD,       '1' ),
            'waiting_room'      => get_option( self::OPT_WAITING_ROOM,      '0' ),
            'join_before_host'  => get_option( self::OPT_JOIN_BEFORE_HOST,  '1' ),
            'host_video'        => get_option( self::OPT_HOST_VIDEO,        '1' ),
            'duration'          => (int) get_option( self::OPT_DURATION,    60  ),
        ];
    }

    public static function get_host_key() {
        return get_option( self::OPT_HOST_KEY, '' );
    }

    public static function get_instructor_window() {
        return (int) get_option( self::OPT_INSTRUCTOR_WINDOW, 30 );
    }

    public static function get_member_window() {
        return (int) get_option( self::OPT_MEMBER_WINDOW, 15 );
    }
}
