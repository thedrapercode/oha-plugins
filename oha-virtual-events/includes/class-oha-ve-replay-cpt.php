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
        add_action( 'init',                        [ $this, 'register_cpt' ] );
        add_action( 'add_meta_boxes',              [ $this, 'add_meta_box' ] );
        add_action( 'save_post_oha_ve_replay',     [ $this, 'save_meta' ], 10, 2 );
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

        $yt_id     = get_post_meta( $post->ID, 'oha_ve_replay_yt_id',     true );
        $presenter = get_post_meta( $post->ID, 'oha_ve_replay_presenter', true );
        $date      = get_post_meta( $post->ID, 'oha_ve_replay_date',      true );
        $access    = get_post_meta( $post->ID, 'oha_ve_replay_access',    true ) ?: 'subscriber_only';
        $type      = get_post_meta( $post->ID, 'oha_ve_replay_type',      true );
        $resources = get_post_meta( $post->ID, 'oha_ve_replay_resources', true );
        $notes     = get_post_meta( $post->ID, 'oha_ve_replay_notes',     true );
        ?>
        <div style="margin-bottom:14px;">
            <label><strong>YouTube Video ID</strong> <span style="color:#999;">(required)</span></label><br>
            <input type="text" name="oha_ve_replay_yt_id" value="<?php echo esc_attr( $yt_id ); ?>"
                   style="width:100%;margin-top:4px;" placeholder="e.g. dQw4w9WgXcQ" />
            <p style="color:#666;font-size:12px;margin:3px 0 0;">The part after <code>?v=</code> in the YouTube URL. Video must be Unlisted or Public.</p>
        </div>

        <div style="display:flex;gap:16px;margin-bottom:14px;">
            <div style="flex:1;">
                <label><strong>Presenter / Instructor</strong></label><br>
                <input type="text" name="oha_ve_replay_presenter" value="<?php echo esc_attr( $presenter ); ?>"
                       style="width:100%;margin-top:4px;" placeholder="e.g. David Jones" />
            </div>
            <div style="flex:0 0 170px;">
                <label><strong>Date Recorded</strong></label><br>
                <input type="date" name="oha_ve_replay_date" value="<?php echo esc_attr( $date ); ?>"
                       style="width:100%;margin-top:4px;" />
            </div>
        </div>

        <div style="display:flex;gap:16px;margin-bottom:14px;">
            <div style="flex:1;">
                <label><strong>Access Level</strong></label><br>
                <select name="oha_ve_replay_access" style="width:100%;margin-top:4px;">
                    <option value="subscriber_only" <?php selected( $access, 'subscriber_only' ); ?>>Members Only (paid subscribers)</option>
                    <option value="all_members"     <?php selected( $access, 'all_members' );     ?>>All Members (including listing owners)</option>
                    <option value="board_only"      <?php selected( $access, 'board_only' );      ?>>Board Only</option>
                    <option value="public"          <?php selected( $access, 'public' );          ?>>Public (anyone)</option>
                </select>
            </div>
            <div style="flex:1;">
                <label><strong>Event Type</strong></label><br>
                <select name="oha_ve_replay_type" style="width:100%;margin-top:4px;">
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
            <input type="url" name="oha_ve_replay_resources" value="<?php echo esc_attr( $resources ); ?>"
                   style="width:100%;margin-top:4px;" placeholder="https://..." />
        </div>

        <div style="margin-bottom:4px;">
            <label><strong>Admin Notes</strong> <span style="color:#999;">(not shown to members)</span></label><br>
            <textarea name="oha_ve_replay_notes" rows="3"
                      style="width:100%;margin-top:4px;"><?php echo esc_textarea( $notes ); ?></textarea>
        </div>
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
}
