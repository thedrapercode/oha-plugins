<?php
/**
 * OHA Virtual Events - Replay CPT
 *
 * Registers the oha_ve_replay custom post type for standalone on-demand
 * video entries. Replay posts are independent of GeoDirectory events and
 * are not affected by GD's past-event expiry processing.
 *
 * Use this CPT for:
 *   - Recording library entries for classes that have been recorded
 *   - Convention session recordings
 *   - Any on-demand video content not tied to a live GD event
 *
 * Meta:
 *   oha_ve_replay_yt_id      - YouTube video ID (required for Watch Now button)
 *   oha_ve_replay_presenter  - Presenter/instructor display name
 *   oha_ve_replay_date       - Date recorded (YYYY-MM-DD)
 *   oha_ve_replay_access     - Access: subscriber_only, all_members, board_only, public
 *   oha_ve_replay_type       - Category: member_education, actor_meetup, etc.
 *   oha_ve_replay_resources  - URL for handouts or slides
 *   oha_ve_replay_notes      - Admin-only notes (not shown to members)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OHA_VE_Replay_CPT {

    public function __construct() {
        add_action( 'init',                          [ $this, 'register_cpt' ] );
        add_action( 'add_meta_boxes',                [ $this, 'add_meta_box' ] );
        add_action( 'save_post_oha_ve_replay',       [ $this, 'save_meta' ], 10, 2 );
        add_action( 'wp_ajax_oha_ve_get_event_data', [ $this, 'ajax_get_event_data' ] );
    }

    // =========================================================
    // CPT REGISTRATION
    // =========================================================

    public function register_cpt() {
        register_post_type( 'oha_ve_replay', [
            'label'           => 'Replays',
            'labels'          => [
                'name'               => 'Replays',
                'singular_name'      => 'Replay',
                'add_new'            => 'Add Replay',
                'add_new_item'       => 'Add New Replay',
                'edit_item'          => 'Edit Replay',
                'new_item'           => 'New Replay',
                'search_items'       => 'Search Replays',
                'not_found'          => 'No replays found.',
                'not_found_in_trash' => 'No replays found in trash.',
                'menu_name'          => 'Replays',
                'all_items'          => 'All Replays',
            ],
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => true,
            'menu_icon'       => 'dashicons-video-alt3',
            'menu_position'   => 26,
            'supports'        => [ 'title', 'editor', 'thumbnail' ],
            'capability_type' => 'post',
            'map_meta_cap'    => true,
            'rewrite'         => false,
            'query_var'       => false,
        ] );
    }

    // =========================================================
    // META BOX
    // =========================================================

    public function add_meta_box() {
        add_meta_box(
            'oha_ve_replay_details',
            'Replay Details',
            [ $this, 'render_meta_box' ],
            'oha_ve_replay',
            'normal',
            'high'
        );
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'oha_ve_replay_save', 'oha_ve_replay_nonce' );

        $yt_id           = get_post_meta( $post->ID, 'oha_ve_replay_yt_id',      true );
        $presenter       = get_post_meta( $post->ID, 'oha_ve_replay_presenter',  true );
        $date            = get_post_meta( $post->ID, 'oha_ve_replay_date',       true );
        $access          = get_post_meta( $post->ID, 'oha_ve_replay_access',     true ) ?: 'subscriber_only';
        $type            = get_post_meta( $post->ID, 'oha_ve_replay_type',       true );
        $resources       = get_post_meta( $post->ID, 'oha_ve_replay_resources',  true );
        $notes           = get_post_meta( $post->ID, 'oha_ve_replay_notes',      true );
        $linked_event_id = (int) get_post_meta( $post->ID, 'oha_ve_replay_event_id', true );

        // Fetch past virtual events for the import dropdown (most recent 100).
        $past_events = get_posts( [
            'post_type'      => 'gd_event',
            'post_status'    => [ 'publish', 'pending', 'draft' ],
            'posts_per_page' => 100,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'all',
            'meta_query'     => [ [ 'key' => 'oha_ve_enabled', 'value' => '1' ] ],
        ] );
        ?>

        <?php /* ---- Import from GD Event ---- */ ?>
        <div style="background:#f6f7f7;border:1px solid #ddd;border-radius:4px;padding:12px 14px;margin-bottom:18px;">
            <label style="font-weight:600;">Import from Past Virtual Event</label>
            <p style="color:#666;font-size:12px;margin:3px 0 8px;">Select a past virtual event to pre-fill the fields below. You can edit any field after importing.</p>
            <div style="display:flex;gap:8px;align-items:center;">
                <select id="oha-ve-replay-event-picker" style="flex:1;max-width:480px;">
                    <option value="">-- Select an event --</option>
                    <?php foreach ( $past_events as $evt ) : ?>
                        <option value="<?php echo esc_attr( $evt->ID ); ?>"
                            <?php selected( $linked_event_id, $evt->ID ); ?>>
                            <?php echo esc_html( get_the_date( 'Y-m-d', $evt->ID ) . ' - ' . $evt->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="oha-ve-replay-import-btn" class="button button-secondary">Fill from Event</button>
            </div>
            <input type="hidden" name="oha_ve_replay_event_id" id="oha-ve-replay-event-id"
                   value="<?php echo esc_attr( $linked_event_id ?: '' ); ?>" />
            <p id="oha-ve-import-status" style="font-size:12px;color:#555;margin:6px 0 0;min-height:16px;"></p>
        </div>

        <?php /* ---- YouTube Video ID ---- */ ?>
        <div style="margin-bottom:14px;">
            <label><strong>YouTube Video ID</strong> <span style="color:#999;">(required)</span></label><br>
            <input type="text" name="oha_ve_replay_yt_id" id="oha-ve-replay-yt-id"
                   value="<?php echo esc_attr( $yt_id ); ?>"
                   style="width:100%;margin-top:4px;" placeholder="e.g. dQw4w9WgXcQ" />
            <p style="color:#666;font-size:12px;margin:3px 0 0;">The part after <code>?v=</code> in the YouTube URL. Video must be Unlisted or Public.</p>
        </div>

        <div style="display:flex;gap:16px;margin-bottom:14px;">
            <div style="flex:1;">
                <label><strong>Presenter / Instructor</strong></label><br>
                <input type="text" name="oha_ve_replay_presenter" id="oha-ve-replay-presenter"
                       value="<?php echo esc_attr( $presenter ); ?>"
                       style="width:100%;margin-top:4px;" placeholder="e.g. David Jones" />
            </div>
            <div style="flex:0 0 170px;">
                <label><strong>Date Recorded</strong></label><br>
                <input type="date" name="oha_ve_replay_date" id="oha-ve-replay-date"
                       value="<?php echo esc_attr( $date ); ?>"
                       style="width:100%;margin-top:4px;" />
            </div>
        </div>

        <div style="display:flex;gap:16px;margin-bottom:14px;">
            <div style="flex:1;">
                <label><strong>Access Level</strong></label><br>
                <select name="oha_ve_replay_access" id="oha-ve-replay-access" style="width:100%;margin-top:4px;">
                    <option value="subscriber_only" <?php selected( $access, 'subscriber_only' ); ?>>Members Only (paid subscribers)</option>
                    <option value="all_members"     <?php selected( $access, 'all_members' );     ?>>All Members (including listing owners)</option>
                    <option value="board_only"      <?php selected( $access, 'board_only' );      ?>>Board Only</option>
                    <option value="public"          <?php selected( $access, 'public' );          ?>>Public (anyone)</option>
                </select>
            </div>
            <div style="flex:1;">
                <label><strong>Event Type</strong></label><br>
                <select name="oha_ve_replay_type" id="oha-ve-replay-type" style="width:100%;margin-top:4px;">
                    <option value="">-- Select type --</option>
                    <option value="member_education"   <?php selected( $type, 'member_education' );   ?>>Member Education</option>
                    <option value="actor_meetup"       <?php selected( $type, 'actor_meetup' );       ?>>Actor Meetup</option>
                    <option value="member_townhall"    <?php selected( $type, 'member_townhall' );    ?>>Member Town Hall</option>
                    <option value="convention_session" <?php selected( $type, 'convention_session' ); ?>>Convention Session</option>
                    <option value="other_virtual"      <?php selected( $type, 'other_virtual' );      ?>>Other</option>
                </select>
            </div>
        </div>

        <div style="margin-bottom:14px;">
            <label><strong>Resources URL</strong> <span style="color:#999;">(optional - handouts, slides)</span></label><br>
            <input type="url" name="oha_ve_replay_resources" id="oha-ve-replay-resources"
                   value="<?php echo esc_attr( $resources ); ?>"
                   style="width:100%;margin-top:4px;" placeholder="https://..." />
        </div>

        <div style="margin-bottom:4px;">
            <label><strong>Admin Notes</strong> <span style="color:#999;">(not shown to members)</span></label><br>
            <textarea name="oha_ve_replay_notes" rows="3"
                      style="width:100%;margin-top:4px;"><?php echo esc_textarea( $notes ); ?></textarea>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#oha-ve-replay-import-btn').on('click', function() {
                var eventId = $('#oha-ve-replay-event-picker').val();
                if ( ! eventId ) {
                    $('#oha-ve-import-status').text('Select an event first.');
                    return;
                }
                var btn = $(this);
                btn.prop('disabled', true).text('Loading...');
                $('#oha-ve-import-status').text('');

                $.post(ajaxurl, {
                    action:   'oha_ve_get_event_data',
                    event_id: eventId,
                    nonce:    '<?php echo esc_js( wp_create_nonce( 'oha_ve_import_event' ) ); ?>'
                }, function(res) {
                    btn.prop('disabled', false).text('Fill from Event');
                    if ( ! res.success ) {
                        $('#oha-ve-import-status').text('Error: ' + (res.data ? res.data.message : 'Unknown error'));
                        return;
                    }
                    var d = res.data;
                    $('#oha-ve-replay-event-id').val(eventId);
                    if ( d.title )     { $('#title').val(d.title).trigger('keyup'); }
                    if ( d.yt_id )     { $('#oha-ve-replay-yt-id').val(d.yt_id); }
                    if ( d.presenter ) { $('#oha-ve-replay-presenter').val(d.presenter); }
                    if ( d.date )      { $('#oha-ve-replay-date').val(d.date); }
                    if ( d.access )    { $('#oha-ve-replay-access').val(d.access); }
                    if ( d.type )      { $('#oha-ve-replay-type').val(d.type); }
                    if ( d.resources ) { $('#oha-ve-replay-resources').val(d.resources); }
                    $('#oha-ve-import-status').css('color','#1e7e34').text('Fields filled from event. Review and publish when ready.');
                }).fail(function() {
                    btn.prop('disabled', false).text('Fill from Event');
                    $('#oha-ve-import-status').text('Request failed. Try again.');
                });
            });
        });
        </script>
        <?php
    }

    // =========================================================
    // SAVE META
    // =========================================================

    public function save_meta( $post_id, $post ) {
        if ( ! isset( $_POST['oha_ve_replay_nonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce( $_POST['oha_ve_replay_nonce'], 'oha_ve_replay_save' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Linked GD event ID (import source reference)
        if ( isset( $_POST['oha_ve_replay_event_id'] ) ) {
            $eid = absint( $_POST['oha_ve_replay_event_id'] );
            if ( $eid ) {
                update_post_meta( $post_id, 'oha_ve_replay_event_id', $eid );
            } else {
                delete_post_meta( $post_id, 'oha_ve_replay_event_id' );
            }
        }

        $text_fields = [
            'oha_ve_replay_yt_id',
            'oha_ve_replay_presenter',
            'oha_ve_replay_date',
            'oha_ve_replay_access',
            'oha_ve_replay_type',
            'oha_ve_replay_notes',
        ];
        foreach ( $text_fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_post_meta( $post_id, $field, sanitize_text_field( $_POST[ $field ] ) );
            }
        }

        if ( isset( $_POST['oha_ve_replay_resources'] ) ) {
            update_post_meta( $post_id, 'oha_ve_replay_resources', esc_url_raw( $_POST['oha_ve_replay_resources'] ) );
        }
    }

    // =========================================================
    // QUERY REPLAYS
    // =========================================================

    /**
     * Query published oha_ve_replay posts, ordered by recorded date DESC.
     *
     * @param array $args {
     *   limit  int   posts_per_page, default -1 (all)
     *   type   string  filter by oha_ve_replay_type
     * }
     * @return WP_Post[]
     */
    public static function query_replays( $args = [] ) {
        $query_args = [
            'post_type'      => 'oha_ve_replay',
            'post_status'    => 'publish',
            'posts_per_page' => isset( $args['limit'] ) ? intval( $args['limit'] ) : -1,
            'meta_key'       => 'oha_ve_replay_date',
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
        ];

        if ( ! empty( $args['type'] ) ) {
            $query_args['meta_query'] = [ [
                'key'   => 'oha_ve_replay_type',
                'value' => sanitize_key( $args['type'] ),
            ] ];
        }

        return ( new WP_Query( $query_args ) )->posts;
    }

    // =========================================================
    // RENDER REPLAY CARD
    // =========================================================

    /**
     * Render a single on-demand replay card for the member hub shortcode.
     * Includes an expandable YouTube embed toggled by a Watch Now button.
     *
     * @param WP_Post $post  oha_ve_replay post object.
     * @return string HTML
     */
    public static function render_replay_card( $post ) {
        $post_id   = $post->ID;
        $yt_id     = get_post_meta( $post_id, 'oha_ve_replay_yt_id',     true );
        $presenter = get_post_meta( $post_id, 'oha_ve_replay_presenter', true );
        $date      = get_post_meta( $post_id, 'oha_ve_replay_date',      true );
        $type      = get_post_meta( $post_id, 'oha_ve_replay_type',      true );
        $resources = get_post_meta( $post_id, 'oha_ve_replay_resources', true );
        $title     = get_the_title( $post_id );

        $type_labels = [
            'member_education'   => 'Member Education',
            'actor_meetup'       => 'Actor Meetup',
            'member_townhall'    => 'Member Town Hall',
            'convention_session' => 'Convention Session',
            'other_virtual'      => 'Other',
        ];
        $type_label = isset( $type_labels[ $type ] ) ? $type_labels[ $type ] : '';

        $date_display = '';
        if ( $date ) {
            $ts = strtotime( $date );
            if ( $ts ) {
                $date_display = date_i18n( 'F j, Y', $ts );
            }
        }

        $embed_id = 'oha-replay-embed-' . intval( $post_id );

        $out  = '<div class="oha-edu-card oha-edu-card--replay">';
        $out .= '<div class="oha-edu-card-body">';

        if ( $type_label ) {
            $out .= '<span class="oha-edu-type-badge">' . esc_html( $type_label ) . '</span>';
        }

        $out .= '<h4 class="oha-edu-card-title">' . esc_html( $title ) . '</h4>';

        if ( $date_display ) {
            $out .= '<p class="oha-edu-card-date">' . esc_html( $date_display ) . '</p>';
        }

        if ( $presenter ) {
            $out .= '<p class="oha-edu-card-instructor">Presenter: <strong>'
                  . esc_html( $presenter ) . '</strong></p>';
        }

        $out .= '</div>'; // .oha-edu-card-body

        $out .= '<div class="oha-edu-card-actions">';
        if ( $yt_id ) {
            $out .= '<button type="button" class="oha-ve-btn oha-ve-btn-replay oha-replay-toggle"'
                  . ' onclick="ohaReplayToggle(this)"'
                  . ' data-target="' . esc_attr( $embed_id ) . '">Watch Now</button>';
        }
        if ( $resources ) {
            $out .= '<a href="' . esc_url( $resources ) . '" class="oha-ve-btn oha-ve-btn-outline"'
                  . ' target="_blank" rel="noopener noreferrer">Resources</a>';
        }
        $out .= '</div>'; // .oha-edu-card-actions

        if ( $yt_id ) {
            $out .= '<div class="oha-replay-embed" id="' . esc_attr( $embed_id ) . '">';
            $out .= '<div class="oha-replay-video-wrap">';
            $out .= '<iframe'
                  . ' src="https://www.youtube.com/embed/' . esc_attr( $yt_id ) . '?rel=0"'
                  . ' frameborder="0" allowfullscreen loading="lazy"'
                  . ' allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"'
                  . '></iframe>';
            $out .= '</div>'; // .oha-replay-video-wrap
            $out .= '</div>'; // .oha-replay-embed
        }

        $out .= '</div>'; // .oha-edu-card

        return $out;
    }

    // =========================================================
    // AJAX: GET EVENT DATA FOR IMPORT
    // =========================================================

    /**
     * Return key fields from a gd_event post as JSON for the import tool.
     * Pulls: title, instructor name, schedule start date, event type,
     * access level, existing YT replay ID, and resources URL.
     */
    public function ajax_get_event_data() {
        check_ajax_referer( 'oha_ve_import_event', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
        }

        $event_id = absint( $_POST['event_id'] ?? 0 );
        if ( ! $event_id ) {
            wp_send_json_error( [ 'message' => 'No event ID.' ] );
        }

        $event = get_post( $event_id );
        if ( ! $event || 'gd_event' !== $event->post_type ) {
            wp_send_json_error( [ 'message' => 'Event not found.' ] );
        }

        // Instructor display name
        $instructor_id = (int) get_post_meta( $event_id, 'oha_ve_instructor_user_id', true );
        $presenter     = '';
        if ( $instructor_id ) {
            $user = get_userdata( $instructor_id );
            if ( $user ) {
                $presenter = $user->display_name;
            }
        }

        // Schedule start date from GD table
        global $wpdb;
        $table    = defined( 'GEODIR_EVENT_SCHEDULES_TABLE' )
            ? GEODIR_EVENT_SCHEDULES_TABLE
            : $wpdb->prefix . 'geodir_event_schedule';
        $schedule = $wpdb->get_row( $wpdb->prepare(
            "SELECT start_date FROM {$table} WHERE event_id = %d ORDER BY start_date ASC LIMIT 1",
            $event_id
        ) );
        $date = $schedule ? $schedule->start_date : '';

        // Map gd_event type to replay type (convention_session is replay-only, left as manual)
        $event_type = get_post_meta( $event_id, 'oha_ve_event_type', true );
        $type_map   = [
            'member_education' => 'member_education',
            'pre_recorded'     => 'member_education',
            'actor_meetup'     => 'actor_meetup',
            'member_townhall'  => 'member_townhall',
            'other_virtual'    => 'other_virtual',
        ];
        $replay_type = $type_map[ $event_type ] ?? '';

        wp_send_json_success( [
            'title'     => $event->post_title,
            'presenter' => $presenter,
            'date'      => $date,
            'access'    => get_post_meta( $event_id, 'oha_ve_access_level',   true ),
            'type'      => $replay_type,
            'yt_id'     => get_post_meta( $event_id, 'oha_ve_yt_replay_id',  true ),
            'resources' => get_post_meta( $event_id, 'oha_ve_resources_url', true ),
        ] );
    }
}
