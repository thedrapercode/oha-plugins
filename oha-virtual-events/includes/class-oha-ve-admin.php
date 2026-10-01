<?php
/**
 * OHA Virtual Events - Admin Meta Box
 *
 * Registers the "OHA Virtual Event Settings" meta box on gd_event posts.
 * Only visible to: administrator, oha_board.
 *
 * META KEYS STORED (wp_postmeta, oha_ve_ prefix):
 *
 *   Core:
 *     oha_ve_enabled              - '1' if this is an OHA virtual event
 *     oha_ve_event_type           - member_education / actor_meetup / member_townhall / other_virtual
 *                                   NOTE: Board meetings are NOT managed here. They use a permanent
 *                                   Zoom room in the Board Hub. See Board Hub settings.
 *     oha_ve_access_level         - public / subscriber_only / all_members / board_only
 *     oha_ve_event_status         - upcoming / live / replay / closed / cancelled
 *     oha_ve_show_in_hub          - '1' to show in My Account Education & Events shortcodes (default on)
 *
 *   Instructor:
 *     oha_ve_instructor_user_id   - WP user ID of assigned instructor
 *     oha_ve_instructor_notes     - admin-only instructor notes
 *
 *   Zoom:
 *     oha_ve_zoom_join_url        - attendee join link (paste from Zoom, does not expire)
 *     oha_ve_zoom_start_url       - Phase 2 only: auto-filled by Zoom API. Leave blank in Phase 1.
 *                                   The Start Session button constructs its URL from the Meeting ID.
 *     oha_ve_zoom_id              - meeting ID (shown to attendees, used to build Start Session URL)
 *     oha_ve_zoom_passcode        - passcode (shown to attendees)
 *     oha_ve_zoom_recording       - '1' if auto cloud recording is on (default on)
 *
 *   YouTube (optional, Phase 3 full automation):
 *     oha_ve_yt_live_id           - YouTube live stream video ID
 *     oha_ve_yt_replay_id         - YouTube replay/recording video ID
 *
 *   Archive & Replay:
 *     oha_ve_archive_enabled      - '1' if recording should be archived for members
 *     oha_ve_replay_available     - '1' once replay is processed and ready to show
 *     oha_ve_resources_url        - downloadable handouts/slides link
 *
 *   Internal:
 *     oha_ve_notes                - admin-only notes (never shown publicly)
 *
 * ROLE NOTES:
 *   oha_board = WordPress role slug stored in wp_capabilities.
 *   "board-member" is the UsersWP account type - not used here for access checks.
 *
 * BOARD MEETINGS:
 *   Board meetings use a permanent Zoom room configured in the Board Hub.
 *   That room uses Zoom's "Only authenticated users can join" + waiting room settings
 *   to restrict access to verified board member Zoom accounts.
 *   Do not create gd_events for board meetings - manage them entirely in the Board Hub.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OHA_VE_Admin {

    public function __construct() {
        add_action( 'add_meta_boxes',     [ $this, 'register_meta_box' ] );
        add_action( 'save_post_gd_event', [ $this, 'save_meta' ], 10, 2 );

        // Instructor role management on the WP user profile screen.
        // Only fires when an admin edits another user - not on self-edit screens.
        add_action( 'edit_user_profile',        [ $this, 'render_instructor_role_section' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save_instructor_role' ] );
    }

    // =========================================================
    // HELPERS
    // =========================================================

    private function current_user_can_manage() {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        $user  = wp_get_current_user();
        $roles = (array) $user->roles;
        return in_array( 'oha_board', $roles, true );
    }

    // =========================================================
    // META BOX REGISTRATION
    // =========================================================

    public function register_meta_box() {
        if ( ! $this->current_user_can_manage() ) {
            return;
        }
        add_meta_box(
            'oha_virtual_event_details',
            __( 'OHA Virtual Event Settings', 'oha-virtual-events' ),
            [ $this, 'render_meta_box' ],
            'gd_event',
            'normal',
            'high'
        );
    }

    // =========================================================
    // META BOX RENDER
    // =========================================================

    public function render_meta_box( $post ) {
        wp_nonce_field( 'oha_ve_save_meta', 'oha_ve_nonce' );

        // Read current values
        $enabled          = get_post_meta( $post->ID, 'oha_ve_enabled',           true );
        $event_type       = get_post_meta( $post->ID, 'oha_ve_event_type',         true );
        $access_level     = get_post_meta( $post->ID, 'oha_ve_access_level',       true );
        $event_status     = get_post_meta( $post->ID, 'oha_ve_event_status',       true );
        $show_in_hub      = get_post_meta( $post->ID, 'oha_ve_show_in_hub',        true );
        $instructor_id    = get_post_meta( $post->ID, 'oha_ve_instructor_user_id', true );
        $instructor_notes = get_post_meta( $post->ID, 'oha_ve_instructor_notes',   true );
        $zoom_join        = get_post_meta( $post->ID, 'oha_ve_zoom_join_url',      true );
        $zoom_id          = get_post_meta( $post->ID, 'oha_ve_zoom_id',            true );
        $zoom_pass        = get_post_meta( $post->ID, 'oha_ve_zoom_passcode',      true );
        $zoom_btn_label   = get_post_meta( $post->ID, 'oha_ve_zoom_btn_label',     true );
        $zoom_uuid        = get_post_meta( $post->ID, 'oha_ve_zoom_meeting_uuid',  true );
        $yt_live_id       = get_post_meta( $post->ID, 'oha_ve_yt_live_id',         true );
        $yt_replay_id     = get_post_meta( $post->ID, 'oha_ve_yt_replay_id',       true );
        $replay_available = get_post_meta( $post->ID, 'oha_ve_replay_available',   true );
        $resources_url    = get_post_meta( $post->ID, 'oha_ve_resources_url',      true );
        $notes            = get_post_meta( $post->ID, 'oha_ve_notes',              true );

        // Defaults for new events
        if ( '' === $show_in_hub ) $show_in_hub = '1';

        $platform         = get_post_meta( $post->ID, 'oha_ve_platform', true );

        // Option lists
        // NOTE: Board meetings are intentionally excluded - they use the Board Hub permanent Zoom room.
        $event_types = [
            ''                 => '- Select Event Type -',
            'member_education' => 'Member Education Session',
            'pre_recorded'     => 'Pre-Recorded Session',
            'actor_meetup'     => 'Actor Meetup',
            'member_townhall'  => 'Member Town Hall',
            'other_virtual'    => 'Other Virtual Event',
        ];

        // Platform controls what fields matter and how the event page displays.
        //   zoom        - Zoom meeting only (standard live session)
        //   youtube     - YouTube Live broadcast (large audience or simulcast)
        //   both        - Zoom streams to YouTube simultaneously
        //   prerecorded - Pre-recorded YouTube unlisted video, no live session
        $platforms = [
            ''             => '- Select Platform -',
            'zoom'         => 'Zoom - Live meeting (upload recording to YouTube after)',
            'youtube'      => 'YouTube Live - Broadcast only, no Zoom',
            'both'         => 'Zoom + YouTube Live simultaneously',
            'prerecorded'  => 'Pre-Recorded - YouTube upload only, no live session',
        ];

        $access_levels = [
            'subscriber_only' => 'Paid Members Only (subscriber role)',
            'all_members'     => 'Paid Members + Free Listing Owners (subscriber + listing_owner)',
            'board_only'      => 'Board Members Only (oha_board role)',
            'public'          => 'Public - no login required',
        ];

        $statuses = [
            'upcoming'  => 'Upcoming',
            'live'      => 'Live Now',
            'replay'    => 'Replay / On Demand',
            'closed'    => 'Closed',
            'cancelled' => 'Cancelled',
        ];

        $instructor_users = get_users( [
            'role__in' => [ 'administrator', 'oha_board', 'oha_instructor' ],
            'orderby'  => 'display_name',
            'order'    => 'ASC',
            'fields'   => [ 'ID', 'display_name' ],
        ] );

        ?>
        <style>
            #oha_virtual_event_details { font-size: 13px; }
            #oha_virtual_event_details .oha-ve-section {
                border-left: 4px solid #0073aa;
                padding: 12px 16px;
                margin-bottom: 18px;
                background: #f9f9f9;
                border-radius: 0 4px 4px 0;
            }
            #oha_virtual_event_details .oha-ve-section.oha-ve-highlight {
                border-left-color: #d63638;
                background: #fdf0f0;
            }
            #oha_virtual_event_details .oha-ve-section h4 {
                margin: 0 0 10px;
                color: #0073aa;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.6px;
            }
            #oha_virtual_event_details .oha-ve-section.oha-ve-highlight h4 { color: #d63638; }
            #oha_virtual_event_details label { font-weight: 600; display: block; margin-bottom: 4px; }
            #oha_virtual_event_details label.inline { display: inline; font-weight: normal; margin-left: 6px; }
            #oha_virtual_event_details input[type="text"],
            #oha_virtual_event_details input[type="url"],
            #oha_virtual_event_details input[type="date"],
            #oha_virtual_event_details select,
            #oha_virtual_event_details textarea { width: 100%; box-sizing: border-box; }
            #oha_virtual_event_details .oha-ve-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 4px; }
            #oha_virtual_event_details .oha-ve-tip {
                background: #e8f4fd; border: 1px solid #bee0f5; border-radius: 4px;
                padding: 7px 10px; font-size: 12px; color: #444; margin-bottom: 10px;
            }
            #oha_virtual_event_details .oha-ve-tip code { background: #d0e8f8; padding: 1px 4px; border-radius: 3px; }
            #oha_virtual_event_details .oha-ve-field { margin-bottom: 12px; }
            #oha_virtual_event_details .oha-ve-field:last-child { margin-bottom: 0; }
            #oha_virtual_event_details .oha-ve-note {
                background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;
                padding: 6px 10px; font-size: 12px; color: #664d03; margin-bottom: 10px;
            }
        </style>

        <!-- Section 1: Enable + Status + Hub visibility -->
        <div class="oha-ve-section oha-ve-highlight">
            <h4>Virtual Event Setup</h4>
            <div class="oha-ve-field">
                <label>
                    <input type="checkbox" name="oha_ve_enabled" value="1" <?php checked( $enabled, '1' ); ?> />
                    <span class="inline">This is an OHA Virtual Event (enables settings below)</span>
                </label>
            </div>
            <div class="oha-ve-field">
                <label>
                    <input type="checkbox" name="oha_ve_show_in_hub" value="1" <?php checked( $show_in_hub, '1' ); ?> />
                    <span class="inline">Show in My Account Education &amp; Events hub (uncheck to hide from shortcodes - event page still accessible)</span>
                </label>
            </div>
            <div class="oha-ve-field">
                <label for="oha_ve_event_status">Event Status</label>
                <select name="oha_ve_event_status" id="oha_ve_event_status">
                    <?php foreach ( $statuses as $val => $label ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $event_status, $val ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p style="font-size:12px;color:#666;margin:4px 0 0;">
                    Change to <strong>Live Now</strong> when session starts. Change to <strong>Replay</strong> after recording is ready.
                </p>
            </div>
        </div>

        <!-- Section 2: Classification -->
        <div class="oha-ve-section">
            <h4>Event Classification</h4>
            <p class="oha-ve-note">
                &#128221; <strong>Board meetings</strong> are not listed here. They use the permanent Zoom room in the Board Hub - no GeoDirectory event needed.
            </p>
            <div class="oha-ve-grid">
                <div class="oha-ve-field">
                    <label for="oha_ve_event_type">OHA Event Type</label>
                    <select name="oha_ve_event_type" id="oha_ve_event_type">
                        <?php foreach ( $event_types as $val => $label ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $event_type, $val ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="oha-ve-field">
                    <label for="oha_ve_platform">Platform</label>
                    <select name="oha_ve_platform" id="oha_ve_platform">
                        <?php foreach ( $platforms as $val => $label ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $platform, $val ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p style="font-size:12px;color:#666;margin:4px 0 0;">
                        <strong>Pre-Recorded:</strong> fill in only the YouTube Replay Video ID below. Set status to Replay and check Replay is Ready when you want it visible.
                    </p>
                </div>
                <div class="oha-ve-field">
                    <label for="oha_ve_access_level">Who Can Watch?</label>
                    <select name="oha_ve_access_level" id="oha_ve_access_level">
                        <?php foreach ( $access_levels as $val => $label ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $access_level, $val ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Section 3: Instructor -->
        <div class="oha-ve-section">
            <h4>Instructor Assignment</h4>
            <p class="oha-ve-tip">
                The assigned instructor sees a <strong>Start Session</strong> button that launches Zoom using their Meeting ID.
                Regular members see a <strong>Join Meeting</strong> button. Board members and admins always see both.
            </p>
            <div class="oha-ve-grid">
                <div class="oha-ve-field">
                    <label for="oha_ve_instructor_user_id">Assigned Instructor</label>
                    <select name="oha_ve_instructor_user_id" id="oha_ve_instructor_user_id">
                        <option value="">- No instructor assigned -</option>
                        <?php foreach ( $instructor_users as $u ) : ?>
                            <option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( (int) $instructor_id, (int) $u->ID ); ?>>
                                <?php echo esc_html( $u->display_name ); ?> (ID: <?php echo (int) $u->ID; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p style="font-size:12px;color:#666;margin:4px 0 0;">
                        Showing users with roles: administrator, oha_board, oha_instructor.
                    </p>
                </div>
                <div class="oha-ve-field">
                    <label for="oha_ve_instructor_notes">Instructor Notes (admin only)</label>
                    <textarea name="oha_ve_instructor_notes" id="oha_ve_instructor_notes" rows="3"
                              placeholder="Speaker bio, session notes, host key, etc."><?php echo esc_textarea( $instructor_notes ); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Section 4: Zoom -->
        <div class="oha-ve-section" id="oha-ve-section-zoom">
            <h4>Zoom Meeting Details</h4>
            <?php
            $p2_configured = OHA_VE_Settings::is_api_configured();
            $p2_auto       = ( '1' === get_option( OHA_VE_Settings::OPT_AUTO_CREATE, '0' ) );
            if ( $p2_configured && $p2_auto && empty( $zoom_id ) ) :
                ?>
                <p class="oha-ve-tip" style="border-color:#1e7e34;background:#f0fbf0;">
                    &#9889; <strong>Phase 2 Active:</strong> Zoom credentials and auto-create are configured.
                    A Zoom meeting will be created automatically when this event is published. Meeting ID and Join URL will be filled in below.
                </p>
            <?php elseif ( $p2_configured && ! $p2_auto ) : ?>
                <p class="oha-ve-tip">
                    &#10003; Zoom API is configured. Auto-create is currently off - you can enable it in
                    <a href="<?php echo esc_url( admin_url( 'options-general.php?page=oha-virtual-events' ) ); ?>">Settings &rarr; OHA Virtual Events</a>,
                    or fill in the fields below manually.
                </p>
            <?php elseif ( ! $p2_configured ) : ?>
                <p class="oha-ve-tip">
                    Each event must use a <strong>unique Zoom meeting</strong> - do not reuse a permanent room.<br>
                    <strong>Meeting ID is required</strong> - it is used to build the Start Session link for the instructor (never expires).<br>
                    <strong>Join URL</strong> is for attendees and does not expire. Paste from Zoom.<br>
                    <a href="<?php echo esc_url( admin_url( 'options-general.php?page=oha-virtual-events' ) ); ?>">Configure Zoom API credentials</a> to enable Phase 2 auto-create.
                </p>
            <?php endif; ?>
            <div class="oha-ve-grid">
                <div class="oha-ve-field">
                    <label for="oha_ve_zoom_join_url">Zoom Join URL (for attendees)</label>
                    <input type="url" name="oha_ve_zoom_join_url" id="oha_ve_zoom_join_url"
                           value="<?php echo esc_url( $zoom_join ); ?>"
                           placeholder="https://us06web.zoom.us/j/..." />
                </div>
                <div class="oha-ve-field">
                    <label for="oha_ve_zoom_id">Meeting ID <span style="font-weight:400;color:#d63638;">&#9679; Required for Start Session</span></label>
                    <input type="text" name="oha_ve_zoom_id" id="oha_ve_zoom_id"
                           value="<?php echo esc_attr( $zoom_id ); ?>"
                           placeholder="123 456 7890" />
                    <p style="font-size:12px;color:#666;margin:4px 0 0;">
                        The Start Session button for the instructor is built as <code>zoom.us/s/{ID}</code> - no token, never expires.
                    </p>
                </div>
                <div class="oha-ve-field">
                    <label for="oha_ve_zoom_passcode">Passcode (shown to attendees, optional)</label>
                    <input type="text" name="oha_ve_zoom_passcode" id="oha_ve_zoom_passcode"
                           value="<?php echo esc_attr( $zoom_pass ); ?>"
                           placeholder="e.g. oha2026" />
                </div>
                <div class="oha-ve-field">
                    <label for="oha_ve_zoom_btn_label">Join Button Label <span style="font-weight:400;color:#888;">(optional - overrides default text)</span></label>
                    <input type="text" name="oha_ve_zoom_btn_label" id="oha_ve_zoom_btn_label"
                           value="<?php echo esc_attr( $zoom_btn_label ); ?>"
                           placeholder="Default: Join Meeting" />
                    <p style="font-size:12px;color:#666;margin:4px 0 0;">
                        Custom text for the attendee join button. Leave blank to use "Join Meeting".
                    </p>
                </div>
            </div>
            <?php if ( $zoom_uuid ) : ?>
            <div class="oha-ve-field" style="margin-top:8px;">
                <label style="font-size:11px;color:#888;font-weight:400;">Meeting UUID <span style="color:#aaa;">(auto-filled by Zoom API - read only)</span></label>
                <code style="font-size:12px;background:#f0f0f0;padding:2px 6px;border-radius:3px;"><?php echo esc_html( $zoom_uuid ); ?></code>
            </div>
            <?php endif; ?>
            <?php
            $synced_at = get_post_meta( $post->ID, 'oha_ve_zoom_synced_at', true );
            if ( ! empty( $zoom_id ) && OHA_VE_Settings::is_api_configured() ) :
            ?>
            <div class="oha-ve-field" style="margin-top:10px;padding-top:10px;border-top:1px solid #e0e0e0;">
                <button type="button"
                        class="button button-small oha-ve-sync-time-btn"
                        data-event-id="<?php echo (int) $post->ID; ?>"
                        data-nonce="<?php echo esc_attr( wp_create_nonce( 'oha_ve_admin_nonce' ) ); ?>">
                    Sync Date/Time to Zoom
                </button>
                <span class="oha-ve-sync-time-result" style="margin-left:8px;font-style:italic;font-size:12px;color:#666;"></span>
                <?php if ( $synced_at ) : ?>
                    <p style="font-size:12px;color:#888;margin:4px 0 0;">
                        Last synced: <strong><?php echo esc_html( date_i18n( 'M j, Y g:i A', strtotime( $synced_at ) ) ); ?></strong>
                    </p>
                <?php else : ?>
                    <p style="font-size:12px;color:#888;margin:4px 0 0;">
                        Not yet synced. Click to push the current event date/time to Zoom.
                    </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Section 5: Archive & Replay -->
        <div class="oha-ve-section" id="oha-ve-section-youtube">
            <h4>Archive &amp; Replay</h4>
            <div class="oha-ve-field" id="oha-ve-yt-live-wrap">
                <label for="oha_ve_yt_live_id">YouTube Live Stream ID <span style="font-weight:400;color:#888;">(only for YouTube Live / simulcast platform)</span></label>
                <input type="text" name="oha_ve_yt_live_id" id="oha_ve_yt_live_id"
                       value="<?php echo esc_attr( $yt_live_id ); ?>"
                       placeholder="Video ID for the active live stream" />
            </div>
            <div class="oha-ve-field">
                <label for="oha_ve_yt_replay_id">YouTube Recording Video ID <span style="font-weight:400;color:#d63638;">&#9679; Paste here after uploading</span></label>
                <input type="text" name="oha_ve_yt_replay_id" id="oha_ve_yt_replay_id"
                       value="<?php echo esc_attr( $yt_replay_id ); ?>"
                       placeholder="The part after ?v= in the YouTube URL" />
                <p style="font-size:12px;color:#666;margin:4px 0 0;">
                    After the class: download from Zoom &rarr; edit if needed &rarr; upload to YouTube as <strong>Unlisted</strong> &rarr; copy the video ID (the part after <code>?v=</code>) &rarr; paste here &rarr; check <em>Replay is ready</em> below.
                </p>
            </div>
            <div class="oha-ve-field" style="border-top:1px solid #e0e0e0;padding-top:10px;margin-top:4px;">
                <label>
                    <input type="checkbox" name="oha_ve_replay_available" id="oha_ve_replay_available" value="1" <?php checked( $replay_available, '1' ); ?> />
                    <span class="inline"><strong>Replay is ready</strong> - publish recording to the member On Demand library</span>
                </label>
                <p style="font-size:12px;color:#666;margin:4px 0 0 22px;">Checking this automatically sets Status to Replay / On Demand.</p>
            </div>
            <?php if ( ! empty( $zoom_id ) && OHA_VE_Settings::is_api_configured() ) : ?>
            <div class="oha-ve-field oha-ve-recording-fetch-wrap" style="background:#f0f5fa;border:1px solid #c3d9ee;border-radius:4px;padding:10px 14px;margin-top:12px;">
                <p style="margin:0 0 6px;font-size:13px;font-weight:600;color:#2271b1;">Fetch Recording from Zoom</p>
                <p style="margin:0 0 8px;font-size:12px;color:#555;">Zoom cloud recordings take 15-60 minutes to process after a session ends.</p>
                <button type="button"
                        class="button button-small oha-ve-fetch-recording-btn"
                        data-event-id="<?php echo (int) $post->ID; ?>"
                        data-nonce="<?php echo esc_attr( wp_create_nonce( 'oha_ve_admin_nonce' ) ); ?>">
                    Fetch Recording from Zoom
                </button>
                <span class="oha-ve-fetch-recording-spinner" style="display:none;margin-left:8px;font-style:italic;font-size:12px;color:#666;">Fetching&hellip;</span>
                <div class="oha-ve-recording-results" style="display:none;margin-top:12px;"></div>
            </div>
            <?php endif; ?>
            <div class="oha-ve-field" style="margin-top:12px;">
                <label for="oha_ve_resources_url">Downloadable Resources URL <span style="font-weight:400;color:#888;">(slides, handouts - optional)</span></label>
                <input type="url" name="oha_ve_resources_url" id="oha_ve_resources_url"
                       value="<?php echo esc_url( $resources_url ); ?>"
                       placeholder="https://... (slides, handouts, PDFs)" />
            </div>
        </div>

        <!-- Section 7: Admin Notes -->
        <div class="oha-ve-section">
            <h4>Admin Notes (never shown publicly)</h4>
            <div class="oha-ve-field">
                <textarea name="oha_ve_notes" id="oha_ve_notes" rows="3"
                          placeholder="Zoom host key, recording checklist, internal reminders, etc."><?php echo esc_textarea( $notes ); ?></textarea>
            </div>
        </div>

        <script>
        (function($) {
            function updatePlatformSections() {
                var p = $('#oha_ve_platform').val();
                var showZoom    = (p === '' || p === 'zoom' || p === 'both');
                var showYT      = (p === '' || p === 'youtube' || p === 'both' || p === 'prerecorded');
                var showLiveId  = (p === 'youtube' || p === 'both');
                $('#oha-ve-section-zoom').toggle(showZoom);
                $('#oha-ve-section-youtube').toggle(showYT);
                $('#oha-ve-yt-live-wrap').toggle(showLiveId);
            }
            function updateInstructorNotes() {
                var hasInstructor = $('#oha_ve_instructor_user_id').val() !== '';
                $('#oha_ve_instructor_notes').closest('.oha-ve-field').toggle(hasInstructor);
            }
            $(document).ready(function() {
                updatePlatformSections();
                updateInstructorNotes();
                $('#oha_ve_platform').on('change', updatePlatformSections);
                $('#oha_ve_instructor_user_id').on('change', updateInstructorNotes);

                // Sync Status <-> Replay is Ready so the admin only needs to do one action.
                $('#oha_ve_event_status').on('change', function() {
                    if ( $(this).val() === 'replay' ) {
                        $('#oha_ve_replay_available').prop('checked', true);
                    }
                });
                $('#oha_ve_replay_available').on('change', function() {
                    if ( $(this).is(':checked') ) {
                        $('#oha_ve_event_status').val('replay');
                    }
                });

                // --- Sync Date/Time to Zoom ---
                $(document).on('click', '.oha-ve-sync-time-btn', function() {
                    var btn     = $(this);
                    var result  = btn.siblings('.oha-ve-sync-time-result');
                    var eventId = btn.data('event-id');
                    var nonce   = btn.data('nonce');
                    btn.prop('disabled', true);
                    result.css('color', '#666').text('Syncing…');
                    $.post(ajaxurl, {
                        action:   'oha_ve_sync_meeting_time',
                        nonce:    nonce,
                        event_id: eventId
                    }, function(resp) {
                        if ( resp.success ) {
                            result.css('color', 'green').text(resp.data.message);
                            if ( resp.data.synced_at ) {
                                btn.closest('.oha-ve-field').find('p').text('Last synced: ' + resp.data.synced_at);
                            }
                        } else {
                            result.css('color', '#d63638').text(resp.data ? resp.data.message : 'Error.');
                        }
                    }).fail(function() {
                        result.css('color', '#d63638').text('Request failed.');
                    }).always(function() {
                        btn.prop('disabled', false);
                    });
                });

                // --- Fetch Recording from Zoom ---
                $(document).on('click', '.oha-ve-fetch-recording-btn', function() {
                    var btn      = $(this);
                    var wrap     = btn.closest('.oha-ve-recording-fetch-wrap');
                    var spinner  = wrap.find('.oha-ve-fetch-recording-spinner');
                    var results  = wrap.find('.oha-ve-recording-results');
                    var eventId  = btn.data('event-id');
                    var nonce    = btn.data('nonce');
                    btn.prop('disabled', true);
                    spinner.show();
                    results.hide().empty();
                    $.post(ajaxurl, {
                        action:   'oha_ve_fetch_recording',
                        nonce:    nonce,
                        event_id: eventId
                    }, function(resp) {
                        spinner.hide();
                        if ( ! resp.success ) {
                            results.html('<p style="color:#d63638;margin:0;">' + (resp.data ? resp.data.message : 'Error fetching recording.') + '</p>').show();
                            return;
                        }
                        var d   = resp.data;
                        var out = '';
                        if ( d.share_url ) {
                            out += '<p style="margin:0 0 8px;"><strong>Share URL:</strong> '
                                 + '<a href="' + d.share_url + '" target="_blank" rel="noopener">' + d.share_url + '</a>';
                            if ( d.password ) {
                                out += ' &nbsp; <strong>Password:</strong> <code>' + d.password + '</code>';
                            }
                            out += '</p>';
                        }
                        if ( d.files && d.files.length ) {
                            out += '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
                                 + '<thead><tr style="background:#e8f0f8;">'
                                 + '<th style="padding:5px 8px;text-align:left;">File</th>'
                                 + '<th style="padding:5px 8px;text-align:left;">Size</th>'
                                 + '<th style="padding:5px 8px;text-align:left;">Links</th>'
                                 + '</tr></thead><tbody>';
                            $.each(d.files, function(i, f) {
                                var size = f.size_mb ? f.size_mb + ' MB' : '-';
                                var links = '';
                                if ( f.play_url )     links += '<a href="' + f.play_url + '" target="_blank" rel="noopener">Play</a>';
                                if ( f.download_url ) links += (links ? ' &nbsp; ' : '') + '<a href="' + f.download_url + '" target="_blank" rel="noopener">Download</a>';
                                out += '<tr style="border-bottom:1px solid #ddd;">'
                                     + '<td style="padding:5px 8px;">' + f.label + '</td>'
                                     + '<td style="padding:5px 8px;">' + size + '</td>'
                                     + '<td style="padding:5px 8px;">' + (links || '-') + '</td>'
                                     + '</tr>';
                            });
                            out += '</tbody></table>';
                        }
                        if ( ! out ) {
                            out = '<p style="margin:0;color:#666;">No completed recording files found yet.</p>';
                        }
                        results.html(out).show();
                    }).fail(function() {
                        spinner.hide();
                        results.html('<p style="color:#d63638;margin:0;">Request failed.</p>').show();
                    }).always(function() {
                        btn.prop('disabled', false);
                    });
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    // =========================================================
    // META SAVE
    // =========================================================

    public function save_meta( $post_id, $post ) {

        // Fix 1: Nonce verification.
        if ( ! isset( $_POST['oha_ve_nonce'] )
            || ! wp_verify_nonce( $_POST['oha_ve_nonce'], 'oha_ve_save_meta' )
        ) {
            return;
        }

        // Fix 2a: Skip autosaves.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Fix 2b: Explicit revision guard (belt-and-suspenders - save_post_gd_event
        // won't fire for revisions since they use post type 'revision', but this
        // makes the intent clear and guards against any future hook refactoring).
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        if ( ! $this->current_user_can_manage() || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Checkboxes
        $checkboxes = [
            'oha_ve_enabled',
            'oha_ve_show_in_hub',
            'oha_ve_replay_available',
        ];
        foreach ( $checkboxes as $field ) {
            update_post_meta( $post_id, $field, isset( $_POST[ $field ] ) ? '1' : '0' );
        }

        // Fix 3: Select/enum fields - validate against allowlist before storing.
        // sanitize_text_field() alone would accept any arbitrary string; these
        // fields have a fixed set of valid values so we enforce them explicitly.
        $enum_fields = [
            'oha_ve_event_type'   => [ '', 'member_education', 'pre_recorded', 'actor_meetup', 'member_townhall', 'other_virtual' ],
            'oha_ve_platform'     => [ '', 'zoom', 'youtube', 'both', 'prerecorded' ],
            'oha_ve_access_level' => [ 'subscriber_only', 'all_members', 'board_only', 'public' ],
            'oha_ve_event_status' => [ 'upcoming', 'live', 'replay', 'closed', 'cancelled' ],
        ];
        foreach ( $enum_fields as $field => $allowed ) {
            if ( isset( $_POST[ $field ] ) ) {
                $val = sanitize_text_field( $_POST[ $field ] );
                update_post_meta( $post_id, $field, in_array( $val, $allowed, true ) ? $val : '' );
            }
        }

        // Free-text fields (no fixed value set).
        $text_fields = [
            'oha_ve_zoom_passcode',
            'oha_ve_zoom_btn_label',
            'oha_ve_yt_live_id',
            'oha_ve_yt_replay_id',
        ];
        foreach ( $text_fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_post_meta( $post_id, $field, sanitize_text_field( $_POST[ $field ] ) );
            }
        }

        // API-managed field: only overwrite if the admin is actively providing a value.
        // Submitting blank preserves whatever the Zoom API wrote so a routine save
        // cannot accidentally erase the meeting ID.
        if ( isset( $_POST['oha_ve_zoom_id'] ) ) {
            $val = sanitize_text_field( $_POST['oha_ve_zoom_id'] );
            if ( '' !== $val ) {
                update_post_meta( $post_id, 'oha_ve_zoom_id', $val );
            }
        }

        // Fix 4: Instructor user ID - absint() + verify the user exists and holds
        // an authorized role. To assign a guest subscriber as instructor, add the
        // oha_instructor secondary role to their account via Users → Edit User.
        // If the submitted ID fails the role check (e.g. tampered form submission),
        // we do not update, preserving the existing valid assignment.
        if ( isset( $_POST['oha_ve_instructor_user_id'] ) ) {
            $uid = absint( $_POST['oha_ve_instructor_user_id'] );
            if ( 0 === $uid ) {
                // "No instructor assigned" - clear it.
                update_post_meta( $post_id, 'oha_ve_instructor_user_id', 0 );
            } else {
                $user          = get_userdata( $uid );
                $allowed_roles = [ 'administrator', 'oha_board', 'oha_instructor' ];
                if ( $user && ! empty( array_intersect( (array) $user->roles, $allowed_roles ) ) ) {
                    update_post_meta( $post_id, 'oha_ve_instructor_user_id', $uid );
                }
                // If the user lacks an authorized role, silently skip the update.
            }
        }

        // URLs
        foreach ( [ 'oha_ve_resources_url' ] as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_post_meta( $post_id, $field, esc_url_raw( $_POST[ $field ] ) );
            }
        }

        // API-managed URL: only overwrite if the admin is actively providing a value.
        if ( isset( $_POST['oha_ve_zoom_join_url'] ) ) {
            $val = esc_url_raw( $_POST['oha_ve_zoom_join_url'] );
            if ( '' !== $val ) {
                update_post_meta( $post_id, 'oha_ve_zoom_join_url', $val );
            }
        }

        // Textareas
        foreach ( [ 'oha_ve_instructor_notes', 'oha_ve_notes' ] as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_post_meta( $post_id, $field, sanitize_textarea_field( $_POST[ $field ] ) );
            }
        }

        // If status is replay, force replay_available on regardless of checkbox state.
        if ( 'replay' === get_post_meta( $post_id, 'oha_ve_event_status', true ) ) {
            update_post_meta( $post_id, 'oha_ve_replay_available', '1' );
        }
    }

    // =========================================================
    // INSTRUCTOR ROLE MANAGEMENT (User Profile Screen)
    // =========================================================

    /**
     * Renders an "OHA Virtual Events" section on the Edit User screen.
     * Only visible to site administrators - not to oha_board members.
     *
     * The oha_instructor role is added as a SECONDARY role alongside
     * whatever primary role the user already has (e.g. subscriber).
     * Their primary role and capabilities are unchanged.
     *
     * Requirement: the oha_instructor role must exist on the site.
     * If you use TutorLMS it is registered automatically. Otherwise
     * add it once via: add_role('oha_instructor','OHA Instructor',[])
     *
     * @param WP_User $user  The user whose profile is being edited.
     */
    public function render_instructor_role_section( $user ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $is_instructor = in_array( 'oha_instructor', (array) $user->roles, true );
        wp_nonce_field( 'oha_ve_instructor_role_' . $user->ID, 'oha_ve_instructor_role_nonce' );
        ?>
        <h2>OHA Virtual Events</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="oha_ve_grant_instructor">Instructor Role</label></th>
                <td>
                    <label>
                        <input type="checkbox"
                               name="oha_ve_grant_instructor"
                               id="oha_ve_grant_instructor"
                               value="1"
                               <?php checked( $is_instructor ); ?> />
                        Grant <strong>OHA Instructor</strong> - allows this user to be assigned as a session host in OHA Virtual Events
                    </label>
                    <p class="description">
                        Adds <code>oha_instructor</code> as a secondary role. The user's primary role
                        (e.g. <em>subscriber</em>) is unchanged. Once granted, this person will appear in
                        the Assigned Instructor dropdown on all virtual event edit screens and will see
                        a <strong>Start Session</strong> button on events they host.
                        Uncheck to revoke at any time.
                    </p>
                    <?php if ( $is_instructor ) : ?>
                        <p class="description" style="color:#2271b1;font-weight:600;margin-top:6px;">
                            &#10003; Currently has OHA Instructor role
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Saves the instructor role grant/revoke when the user profile form is submitted.
     *
     * @param int $user_id  ID of the user being updated.
     */
    public function save_instructor_role( $user_id ) {
        if ( ! isset( $_POST['oha_ve_instructor_role_nonce'] )
            || ! wp_verify_nonce( $_POST['oha_ve_instructor_role_nonce'], 'oha_ve_instructor_role_' . $user_id )
        ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }

        $user = new WP_User( $user_id );

        if ( ! empty( $_POST['oha_ve_grant_instructor'] ) && '1' === $_POST['oha_ve_grant_instructor'] ) {
            $user->add_role( 'oha_instructor' );
        } else {
            $user->remove_role( 'oha_instructor' );
        }
    }
}
