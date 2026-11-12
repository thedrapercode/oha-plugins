<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class JEmail_Admin {

    private $db;
    private $sender;

    public function __construct( JEmail_DB $db, JEmail_Sender $sender ) {
        $this->db     = $db;
        $this->sender = $sender;

        add_action( 'admin_menu', array( $this, 'add_menus' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_jemail_count_recipients', array( $this, 'ajax_count_recipients' ) );
        add_action( 'wp_ajax_jemail_preview_recipients', array( $this, 'ajax_preview_recipients' ) );
        add_action( 'wp_ajax_jemail_send_test', array( $this, 'ajax_send_test' ) );
        add_action( 'wp_ajax_jemail_process_now', array( $this, 'ajax_process_now' ) );
        add_action( 'wp_ajax_jemail_campaign_status', array( $this, 'ajax_campaign_status' ) );
        add_action( 'admin_post_jemail_save_campaign', array( $this, 'save_campaign' ) );
        add_action( 'admin_post_jemail_start_campaign', array( $this, 'start_campaign' ) );
        add_action( 'admin_post_jemail_delete_campaign', array( $this, 'delete_campaign' ) );
        add_action( 'admin_post_jemail_save_settings', array( $this, 'save_settings' ) );
        add_action( 'admin_post_jemail_resubscribe', array( $this, 'resubscribe_user' ) );
    }

    public function add_menus() {
        add_menu_page(
            'JEmail',
            'JEmail',
            'manage_options',
            'jemail',
            array( $this, 'page_campaigns' ),
            'dashicons-email-alt2',
            30
        );
        add_submenu_page( 'jemail', 'All Campaigns', 'All Campaigns', 'manage_options', 'jemail', array( $this, 'page_campaigns' ) );
        add_submenu_page( 'jemail', 'New Campaign', 'New Campaign', 'manage_options', 'jemail-new', array( $this, 'page_editor' ) );
        add_submenu_page( 'jemail', 'Unsubscribes', 'Unsubscribes', 'manage_options', 'jemail-unsubs', array( $this, 'page_unsubs' ) );
        add_submenu_page( 'jemail', 'Settings', 'Settings', 'manage_options', 'jemail-settings', array( $this, 'page_settings' ) );
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'jemail' ) === false ) return;
        wp_enqueue_style( 'jemail-admin', JEMAIL_PLUGIN_URL . 'admin/admin-style.css', array(), JEMAIL_VERSION );
        wp_enqueue_script( 'jemail-admin', JEMAIL_PLUGIN_URL . 'admin/admin-script.js', array( 'jquery', 'wp-editor' ), JEMAIL_VERSION, true );
        wp_localize_script( 'jemail-admin', 'jemailAdmin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'jemail_admin' ),
        ) );
        // Enqueue WP editor for HTML campaigns
        wp_enqueue_editor();
    }

    /* ═══════════════════════════════════════
       PAGE: Campaign List
       ═══════════════════════════════════════ */
    public function page_campaigns() {
        $campaigns = $this->db->get_campaigns( array( 'limit' => 200 ) );
        ?>
        <div class="wrap jemail-wrap">
            <h1>JEmail Campaigns <a href="<?php echo admin_url( 'admin.php?page=jemail-new' ); ?>" class="page-title-action">New Campaign</a></h1>
            <?php $this->show_notices(); ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:30%;">Title</th>
                        <th>Status</th>
                        <th>Recipients</th>
                        <th>Sent</th>
                        <th>Opens</th>
                        <th>Clicks</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $campaigns ) ) : ?>
                        <tr><td colspan="8" style="text-align:center;padding:20px;">No campaigns yet. <a href="<?php echo admin_url( 'admin.php?page=jemail-new' ); ?>">Create one!</a></td></tr>
                    <?php else : foreach ( $campaigns as $c ) :
                        $edit_url   = admin_url( 'admin.php?page=jemail-new&campaign_id=' . $c->id );
                        $delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=jemail_delete_campaign&id=' . $c->id ), 'jemail_delete_' . $c->id );
                        $status_class = 'jemail-status-' . $c->status;
                    ?>
                        <tr>
                            <td><a href="<?php echo esc_url( $edit_url ); ?>"><strong><?php echo esc_html( $c->title ?: '(untitled)' ); ?></strong></a></td>
                            <td>
                                <span class="jemail-status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( ucfirst( $c->status ) ); ?></span>
                                <?php if ( $c->status === 'scheduled' && $c->scheduled_at ) : ?>
                                    <br><small style="color:#666;"><?php echo esc_html( date( 'M j, Y g:ia', strtotime( $c->scheduled_at ) ) ); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo intval( $c->total_count ); ?></td>
                            <td><?php echo intval( $c->sent_count ); ?> / <?php echo intval( $c->fail_count ); ?> fail</td>
                            <td><?php echo intval( $c->open_count ); ?></td>
                            <td><?php echo intval( $c->click_count ); ?></td>
                            <td><?php echo esc_html( date( 'M j, Y g:ia', strtotime( $c->created_at ) ) ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( $edit_url ); ?>">Edit</a>
                                <?php if ( $c->status === 'draft' ) : ?>
                                    | <a href="<?php echo esc_url( $delete_url ); ?>" class="jemail-delete" onclick="return confirm('Delete this campaign?');">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /* ═══════════════════════════════════════
       PAGE: Campaign Editor (New / Edit)
       ═══════════════════════════════════════ */
    public function page_editor() {
        $campaign_id = absint( $_GET['campaign_id'] ?? 0 );
        $campaign    = $campaign_id ? $this->db->get_campaign( $campaign_id ) : null;

        // Defaults
        $title        = $campaign ? $campaign->title : '';
        $subject      = $campaign ? $campaign->subject : '';
        $html_content = $campaign ? $campaign->html_content : '';
        $text_content = $campaign ? $campaign->text_content : '';
        $content_type = $campaign ? $campaign->content_type : 'html';
        $status       = $campaign ? $campaign->status : 'draft';

        // Saved targeting
        $saved_roles    = $campaign ? maybe_unserialize( $campaign->target_roles ) : array();
        $saved_swpm     = $campaign ? maybe_unserialize( $campaign->target_swpm_levels ) : array();
        $saved_uwp      = $campaign ? maybe_unserialize( $campaign->target_uwp_forms ) : array();
        $saved_vendor   = $campaign ? maybe_unserialize( $campaign->target_vendor_statuses ) : array();
        $saved_volunteer = $campaign ? maybe_unserialize( $campaign->target_volunteer_statuses ) : array();
        $saved_gd        = $campaign ? $campaign->target_gd_listing : '';
        $saved_awards_year = $campaign ? ( $campaign->target_awards_year ?? '' ) : '';
        if ( ! is_array( $saved_roles ) )     $saved_roles     = array();
        if ( ! is_array( $saved_swpm ) )      $saved_swpm      = array();
        if ( ! is_array( $saved_uwp ) )       $saved_uwp       = array();
        if ( ! is_array( $saved_vendor ) )    $saved_vendor    = array();
        if ( ! is_array( $saved_volunteer ) ) $saved_volunteer = array();

        $scheduled_at_raw   = ( $campaign && $campaign->scheduled_at ) ? $campaign->scheduled_at : '';
        $scheduled_at_input = $scheduled_at_raw ? date( 'Y-m-d\TH:i', strtotime( $scheduled_at_raw ) ) : '';

        // Available options
        $all_roles          = wp_roles()->roles;
        $swpm_levels        = $this->db->get_swpm_levels();
        $uwp_forms          = $this->db->get_uwp_form_types();
        $vendor_statuses    = $this->db->get_vendor_statuses();
        $volunteer_statuses = $this->db->get_volunteer_statuses();
        $has_geodirectory   = $this->db->has_geodirectory();
        $awards_years       = $this->db->get_awards_years();

        $is_sent = in_array( $status, array( 'sending', 'sent' ), true );
        ?>
        <div class="wrap jemail-wrap">
            <h1><?php echo $campaign_id ? 'Edit Campaign' : 'New Campaign'; ?></h1>
            <?php $this->show_notices(); ?>

            <?php if ( $is_sent ) : ?>
                <?php $this->render_campaign_report( $campaign ); ?>
            <?php else : ?>

            <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" id="jemail-campaign-form">
                <?php wp_nonce_field( 'jemail_save_campaign', 'jemail_nonce' ); ?>
                <input type="hidden" name="action" value="jemail_save_campaign" />
                <input type="hidden" name="campaign_id" value="<?php echo $campaign_id; ?>" />

                <div class="jemail-editor-grid">
                    <!-- LEFT: Content -->
                    <div class="jemail-editor-main">
                        <div class="jemail-field">
                            <label for="jemail-title">Campaign Name</label>
                            <input type="text" id="jemail-title" name="title" value="<?php echo esc_attr( $title ); ?>" placeholder="Internal name for this campaign" class="widefat" />
                        </div>
                        <div class="jemail-field">
                            <label for="jemail-subject">Email Subject <span style="color:#d63638;">*</span></label>
                            <input type="text" id="jemail-subject" name="subject" value="<?php echo esc_attr( $subject ); ?>" placeholder="Subject line recipients will see (required to send)" class="widefat" />
                            <p class="description" style="margin-top:3px;">Note: the <em>Send Test</em> button uses whatever is typed here right now. <strong>Send Now saves this field first</strong>, so the bulk send always uses the subject you see above.</p>
                        </div>
                        <div class="jemail-field">
                            <label>Content Type</label>
                            <label class="jemail-radio"><input type="radio" name="content_type" value="html" <?php checked( $content_type, 'html' ); ?> /> HTML (Rich Editor)</label>
                            <label class="jemail-radio"><input type="radio" name="content_type" value="text" <?php checked( $content_type, 'text' ); ?> /> Plain Text</label>
                        </div>
                        <div class="jemail-field jemail-content-html" <?php if ( $content_type !== 'html' ) echo 'style="display:none;"'; ?>>
                            <label>HTML Content</label>
                            <?php
                            wp_editor( $html_content, 'jemail_html_content', array(
                                'textarea_name' => 'html_content',
                                'textarea_rows' => 20,
                                'media_buttons' => true,
                                'teeny'         => false,
                            ) );
                            ?>
                        </div>
                        <div class="jemail-field jemail-content-text" <?php if ( $content_type !== 'text' ) echo 'style="display:none;"'; ?>>
                            <label for="jemail-text-content">Plain Text Content</label>
                            <textarea id="jemail-text-content" name="text_content" rows="20" class="widefat" style="font-family:monospace;"><?php echo esc_textarea( $text_content ); ?></textarea>
                        </div>

                        <div class="jemail-merge-tags">
                            <strong>Merge Tags:</strong>
                            <code>{{first_name}}</code>
                            <code>{{last_name}}</code>
                            <code>{{display_name}}</code>
                            <code>{{username}}</code>
                            <code>{{email}}</code>
                            <code>{{site_name}}</code>
                            <code>{{site_url}}</code>
                            <code>{{unsubscribe_url}}</code>
                            <?php if ( ! empty( $swpm_levels ) ) : ?>
                                <code>{{membership_level}}</code>
                            <?php endif; ?>
                            <?php if ( ! empty( $uwp_forms ) ) : ?>
                                <code>{{user_type}}</code>
                            <?php endif; ?>
                            <code>{{account_type}}</code>
                        </div>
                    </div>

                    <!-- RIGHT: Targeting Sidebar -->
                    <div class="jemail-editor-sidebar">

                        <!-- Recipient Count -->
                        <div class="jemail-sidebar-box">
                            <h3>Recipients</h3>
                            <div class="jemail-recipient-count">
                                <span id="jemail-count-number">—</span>
                                <span class="jemail-count-label">users will receive this email</span>
                            </div>
                            <button type="button" class="button" id="jemail-count-btn">Count Recipients</button>
                            <button type="button" class="button" id="jemail-preview-btn" style="margin-top:6px;width:100%;">👁 Preview Who Gets This</button>
                            <div id="jemail-preview-results" style="display:none;margin-top:8px;border-top:1px solid #ddd;padding-top:8px;">
                                <p id="jemail-preview-summary" style="margin:0 0 6px;font-size:12px;color:#555;"></p>
                                <ul id="jemail-preview-list" style="margin:0;padding:0;list-style:none;max-height:200px;overflow-y:auto;font-size:12px;"></ul>
                            </div>
                        </div>

                        <!-- WP Roles -->
                        <div class="jemail-sidebar-box">
                            <h3>WordPress Roles</h3>
                            <p class="description">Leave empty = all roles. <strong>Note:</strong> "Subscriber" is the default WordPress role assigned to <em>all</em> regular registered users — it does <strong>not</strong> mean OHA membership subscribers. To target paying OHA members, use the <strong>SWPM Membership Level</strong> filter instead.</p>
                            <div class="jemail-checkbox-list">
                                <?php foreach ( $all_roles as $slug => $role ) :
                                    if ( in_array( $slug, array( 'administrator', 'editor', 'author' ), true ) ) continue;
                                ?>
                                    <label><input type="checkbox" name="target_roles[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $saved_roles, true ) ); ?> /> <?php echo esc_html( $role['name'] ); ?> <small>(<?php echo esc_html( $slug ); ?>)</small></label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description" style="margin-top:6px;"><a href="#" id="jemail-toggle-admin-roles">Show admin roles</a></p>
                            <div class="jemail-checkbox-list jemail-admin-roles" style="display:none;">
                                <?php foreach ( $all_roles as $slug => $role ) :
                                    if ( ! in_array( $slug, array( 'administrator', 'editor', 'author' ), true ) ) continue;
                                ?>
                                    <label><input type="checkbox" name="target_roles[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $saved_roles, true ) ); ?> /> <?php echo esc_html( $role['name'] ); ?></label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- SWPM Membership Levels -->
                        <?php if ( ! empty( $swpm_levels ) ) : ?>
                        <div class="jemail-sidebar-box">
                            <h3>SWPM Membership Level</h3>
                            <p class="description">Leave empty = all levels</p>
                            <div class="jemail-checkbox-list">
                                <?php foreach ( $swpm_levels as $lvl_id => $lvl_name ) : ?>
                                    <label><input type="checkbox" name="target_swpm_levels[]" value="<?php echo esc_attr( $lvl_id ); ?>" <?php checked( in_array( (string) $lvl_id, $saved_swpm, true ) || in_array( (int) $lvl_id, $saved_swpm, true ) ); ?> /> <?php echo esc_html( $lvl_name ); ?> <small>(Level <?php echo esc_html( $lvl_id ); ?>)</small></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- UsersWP Form Types -->
                        <?php if ( ! empty( $uwp_forms ) ) : ?>
                        <div class="jemail-sidebar-box">
                            <h3>UsersWP User Type</h3>
                            <p class="description">Leave empty = all user types</p>
                            <div class="jemail-checkbox-list">
                                <?php foreach ( $uwp_forms as $form_id => $form_name ) : ?>
                                    <label><input type="checkbox" name="target_uwp_forms[]" value="<?php echo esc_attr( $form_id ); ?>" <?php checked( in_array( (string) $form_id, $saved_uwp, true ) || in_array( (int) $form_id, $saved_uwp, true ) ); ?> /> <?php echo esc_html( $form_name ); ?></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Vendor Hub Status -->
                        <?php if ( ! empty( $vendor_statuses ) ) : ?>
                        <div class="jemail-sidebar-box">
                            <h3>🎪 Vendor Hub Status</h3>
                            <p class="description">Filter by vendor application status</p>
                            <div class="jemail-checkbox-list">
                                <?php foreach ( $vendor_statuses as $vs ) : ?>
                                    <label><input type="checkbox" name="target_vendor_statuses[]" value="<?php echo esc_attr( $vs ); ?>" <?php checked( in_array( $vs, $saved_vendor, true ) ); ?> /> <?php echo esc_html( $vs ); ?></label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description" style="margin-top:6px;font-style:italic;color:#888;">📌 Includes vendors without WP accounts (public form). When combined with other filters, only WP users are included.</p>
                        </div>
                        <?php endif; ?>

                        <!-- Volunteer Hub Status -->
                        <?php if ( ! empty( $volunteer_statuses ) ) : ?>
                        <div class="jemail-sidebar-box">
                            <h3>🙋 Volunteer Hub Status</h3>
                            <p class="description">Filter by volunteer status</p>
                            <div class="jemail-checkbox-list">
                                <?php foreach ( $volunteer_statuses as $vol_s ) : ?>
                                    <label><input type="checkbox" name="target_volunteer_statuses[]" value="<?php echo esc_attr( $vol_s ); ?>" <?php checked( in_array( $vol_s, $saved_volunteer, true ) ); ?> /> <?php echo esc_html( ucfirst( $vol_s ) ); ?></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- GeoDirectory Listing -->
                        <?php if ( $has_geodirectory ) : ?>
                        <div class="jemail-sidebar-box">
                            <h3>📍 GeoDirectory Listing</h3>
                            <p class="description">Filter by listing ownership</p>
                            <div class="jemail-checkbox-list">
                                <label><input type="radio" name="target_gd_listing" value="" <?php checked( $saved_gd, '' ); ?> /> No filter (all users)</label>
                                <label><input type="radio" name="target_gd_listing" value="has_listing" <?php checked( $saved_gd, 'has_listing' ); ?> /> Has created/claimed a listing</label>
                                <label><input type="radio" name="target_gd_listing" value="no_listing" <?php checked( $saved_gd, 'no_listing' ); ?> /> Has NOT created any listing</label>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Awards Submissions -->
                        <?php if ( ! empty( $awards_years ) ) : ?>
                        <div class="jemail-sidebar-box">
                            <h3>Awards Submissions</h3>
                            <p class="description">Send to everyone who submitted an award nomination in a given year. Includes both actor and campsite submissions (even those without WP accounts).</p>
                            <select name="target_awards_year" id="jemail-target-awards-year" class="widefat">
                                <option value="">No filter (all users)</option>
                                <?php foreach ( $awards_years as $ay ) : ?>
                                    <option value="<?php echo esc_attr( $ay ); ?>" <?php selected( $saved_awards_year, $ay ); ?>><?php echo esc_html( $ay ); ?> award submissions</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <!-- Actions -->
                        <div class="jemail-sidebar-box">
                            <h3>Actions</h3>
                            <div class="jemail-actions">
                                <button type="submit" name="save_type" value="draft" class="button button-primary widefat">Save Draft</button>
                                <div style="margin-top:10px;">
                                    <label for="jemail-test-email">Test email:</label>
                                    <input type="email" id="jemail-test-email" placeholder="you@email.com" class="widefat" style="margin:4px 0;" />
                                    <button type="button" class="button widefat" id="jemail-send-test">Send Test</button>
                                </div>
                            </div>
                        </div>

                        <!-- Send Campaign -->
                        <?php if ( $campaign_id && $status === 'draft' ) : ?>
                        <div class="jemail-sidebar-box jemail-send-box">
                            <h3>Send Campaign</h3>
                            <p class="description">Clicking <strong>Send Now</strong> saves your current targeting settings and immediately starts sending to all matched recipients. This cannot be undone.</p>
                            <button type="submit" name="start_after_save" value="1" class="button button-primary button-hero widefat" onclick="return confirm('Are you sure you want to save and send this campaign to all matched recipients? This cannot be undone.');" style="text-align:center;">Send Now</button>
                        </div>
                        <?php endif; ?>

                        <!-- Schedule Send -->
                        <?php if ( $campaign_id && $status === 'draft' ) : ?>
                        <div class="jemail-sidebar-box jemail-schedule-box">
                            <h3>Schedule Send</h3>
                            <p class="description">Send automatically at a specific date and time (site local time).</p>
                            <input type="datetime-local" name="scheduled_at" id="jemail-scheduled-at" class="widefat" value="<?php echo esc_attr( $scheduled_at_input ); ?>" min="<?php echo esc_attr( date( 'Y-m-d\TH:i' ) ); ?>" />
                            <button type="submit" name="save_type" value="schedule" class="button widefat" style="margin-top:6px;" id="jemail-schedule-btn">Schedule Send</button>
                        </div>
                        <?php endif; ?>

                        <!-- Scheduled Status Info -->
                        <?php if ( $campaign_id && $status === 'scheduled' ) : ?>
                        <div class="jemail-sidebar-box jemail-scheduled-info-box">
                            <h3>Scheduled</h3>
                            <p>Sending on:<br><strong><?php echo esc_html( date( 'M j, Y g:ia', strtotime( $scheduled_at_raw ) ) ); ?></strong></p>
                            <p class="description">To change the time, update the field below and click Reschedule. To cancel, click Save Draft.</p>
                            <input type="datetime-local" name="scheduled_at" id="jemail-scheduled-at" class="widefat" value="<?php echo esc_attr( $scheduled_at_input ); ?>" min="<?php echo esc_attr( date( 'Y-m-d\TH:i' ) ); ?>" />
                            <button type="submit" name="save_type" value="schedule" class="button widefat" style="margin-top:6px;">Reschedule</button>
                            <button type="submit" name="save_type" value="draft" class="button widefat" style="margin-top:6px;">Cancel Schedule (Save Draft)</button>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render sent campaign report with stats.
     */
    private function render_campaign_report( $campaign ) {
        $stats  = $this->db->get_campaign_stats( $campaign->id );
        $clicks = $this->db->get_campaign_clicks( $campaign->id );
        $open_rate = $campaign->total_count > 0 ? round( ( $campaign->open_count / $campaign->total_count ) * 100, 1 ) : 0;
        $click_rate = $campaign->total_count > 0 ? round( ( $campaign->click_count / $campaign->total_count ) * 100, 1 ) : 0;
        ?>
        <div class="jemail-report">
            <h2><?php echo esc_html( $campaign->title ); ?></h2>
            <p class="jemail-report-subject">Subject: <?php echo esc_html( $campaign->subject ); ?></p>
            <p><span class="jemail-status jemail-status-<?php echo esc_attr( $campaign->status ); ?>"><?php echo esc_html( ucfirst( $campaign->status ) ); ?></span></p>

            <div class="jemail-stats-grid">
                <div class="jemail-stat-card">
                    <div class="jemail-stat-value"><?php echo intval( $campaign->total_count ); ?></div>
                    <div class="jemail-stat-label">Total Recipients</div>
                </div>
                <div class="jemail-stat-card">
                    <div class="jemail-stat-value"><?php echo intval( $stats->sent ); ?></div>
                    <div class="jemail-stat-label">Sent</div>
                </div>
                <div class="jemail-stat-card">
                    <div class="jemail-stat-value"><?php echo intval( $stats->failed ); ?></div>
                    <div class="jemail-stat-label">Failed</div>
                </div>
                <div class="jemail-stat-card">
                    <div class="jemail-stat-value"><?php echo intval( $stats->opened ); ?> <small>(<?php echo $open_rate; ?>%)</small></div>
                    <div class="jemail-stat-label">Opened</div>
                </div>
                <div class="jemail-stat-card">
                    <div class="jemail-stat-value"><?php echo intval( $campaign->click_count ); ?> <small>(<?php echo $click_rate; ?>%)</small></div>
                    <div class="jemail-stat-label">Clicks</div>
                </div>
                <?php if ( $campaign->status === 'sending' ) : ?>
                <div class="jemail-stat-card">
                    <div class="jemail-stat-value" id="jemail-queued-count"><?php echo intval( $stats->queued ); ?></div>
                    <div class="jemail-stat-label">Queued</div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ( $campaign->status === 'sending' ) : ?>
            <div style="margin:15px 0; padding:12px; background:#fff3cd; border-left:4px solid #ffc107; border-radius:3px;">
                <p style="margin:0 0 8px;"><strong>⚡ Campaign is sending...</strong></p>
                <p style="margin:0 0 8px; font-size:12px; color:#666;">
                    WP Cron processes batches automatically on page loads. If emails seem stuck, click below to process a batch immediately.
                </p>
                <button type="button" id="jemail-process-now" class="button button-primary" data-campaign="<?php echo intval( $campaign->id ); ?>">
                    🚀 Process Batch Now
                </button>
                <span id="jemail-process-status" style="margin-left:10px;"></span>
            </div>
            <script>
            (function(){
                var btn = document.getElementById('jemail-process-now');
                var statusEl = document.getElementById('jemail-process-status');
                var nonce = '<?php echo wp_create_nonce( 'jemail_admin' ); ?>';
                var campaignId = <?php echo intval( $campaign->id ); ?>;

                function updateStats(data) {
                    var sentCards = document.querySelectorAll('.jemail-stat-value');
                    // Update queued count
                    var q = document.getElementById('jemail-queued-count');
                    if (q) q.textContent = data.queued;
                    // Refresh page if done
                    if (data.status === 'sent') {
                        statusEl.innerHTML = '<strong style="color:green;">✅ Complete! Refreshing...</strong>';
                        setTimeout(function(){ location.reload(); }, 1500);
                    }
                }

                btn.addEventListener('click', function(){
                    btn.disabled = true;
                    statusEl.textContent = 'Processing...';
                    var fd = new FormData();
                    fd.append('action', 'jemail_process_now');
                    fd.append('nonce', nonce);
                    fd.append('campaign_id', campaignId);
                    fetch(ajaxurl, { method: 'POST', body: fd })
                        .then(function(r){ return r.json(); })
                        .then(function(res){
                            btn.disabled = false;
                            if (res.success && res.data && typeof res.data === 'object') {
                                statusEl.innerHTML = '<strong style="color:green;">✅ Batch sent! ' + res.data.sent_count + '/' + res.data.total_count + ' sent, ' + res.data.queued + ' queued</strong>';
                                updateStats(res.data);
                            } else {
                                statusEl.innerHTML = '<strong style="color:green;">✅ Batch processed</strong>';
                                // Poll for updated stats
                                pollStatus();
                            }
                        })
                        .catch(function(){
                            btn.disabled = false;
                            statusEl.innerHTML = '<span style="color:red;">Error — try again</span>';
                        });
                });

                function pollStatus() {
                    var fd = new FormData();
                    fd.append('action', 'jemail_campaign_status');
                    fd.append('nonce', nonce);
                    fd.append('campaign_id', campaignId);
                    fetch(ajaxurl, { method: 'POST', body: fd })
                        .then(function(r){ return r.json(); })
                        .then(function(res){
                            if (res.success && res.data) updateStats(res.data);
                        });
                }

                // Auto-poll every 10 seconds while sending
                setInterval(pollStatus, 10000);
            })();
            </script>
            <?php endif; ?>

            <?php if ( ! empty( $clicks ) ) : ?>
            <h3>Click Tracking</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>URL</th><th>Total Clicks</th><th>Unique Clicks</th></tr></thead>
                <tbody>
                    <?php foreach ( $clicks as $click ) : ?>
                    <tr>
                        <td><a href="<?php echo esc_url( $click->url ); ?>" target="_blank"><?php echo esc_html( $click->url ); ?></a></td>
                        <td><?php echo intval( $click->clicks ); ?></td>
                        <td><?php echo intval( $click->unique_clicks ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <?php
            // ── Recipient Log with pagination + status filter ──
            $per_page     = 50;
            $current_page = max( 1, absint( $_GET['log_page'] ?? 1 ) );
            $log_status   = in_array( $_GET['log_status'] ?? '', array( '', 'sent', 'opened', 'failed', 'queued' ), true )
                            ? ( $_GET['log_status'] ?? '' )
                            : '';
            $offset       = ( $current_page - 1 ) * $per_page;

            $total_logs  = $this->db->count_campaign_logs( $campaign->id, $log_status );
            $total_pages = $total_logs > 0 ? (int) ceil( $total_logs / $per_page ) : 1;
            $logs        = $this->db->get_campaign_logs( $campaign->id, array(
                'limit'  => $per_page,
                'offset' => $offset,
                'status' => $log_status,
            ) );

            $base_url = admin_url( 'admin.php?page=jemail-new&campaign_id=' . intval( $campaign->id ) );

            // Status filter tabs
            $status_tabs = array(
                ''       => 'All (' . $this->db->count_campaign_logs( $campaign->id ) . ')',
                'opened' => 'Opened (' . $this->db->count_campaign_logs( $campaign->id, 'opened' ) . ')',
                'sent'   => 'Sent (' . $this->db->count_campaign_logs( $campaign->id, 'sent' ) . ')',
                'failed' => 'Failed (' . $this->db->count_campaign_logs( $campaign->id, 'failed' ) . ')',
            );
            ?>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:24px;margin-bottom:6px;">
                <h3 style="margin:0;">Recipient Log</h3>
                <span style="color:#666;font-size:13px;"><?php echo intval( $total_logs ); ?> recipient<?php echo $total_logs !== 1 ? 's' : ''; ?><?php echo $log_status ? ' — filtered by: <strong>' . esc_html( ucfirst( $log_status ) ) . '</strong>' : ''; ?></span>
            </div>

            <!-- Status filter tabs -->
            <ul class="subsubsub" style="margin-bottom:8px;">
                <?php $tab_keys = array_keys( $status_tabs ); ?>
                <?php foreach ( $status_tabs as $slug => $label ) : ?>
                    <li>
                        <a href="<?php echo esc_url( add_query_arg( array( 'log_status' => $slug, 'log_page' => 1 ), $base_url ) ); ?>"
                           <?php if ( $log_status === $slug ) echo 'class="current" style="font-weight:600;"'; ?>>
                           <?php echo esc_html( $label ); ?>
                        </a>
                        <?php if ( $slug !== end( $tab_keys ) ) echo ' | '; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th style="width:90px;">Status</th>
                        <th style="width:130px;">Sent At</th>
                        <th style="width:130px;">Opened At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $logs ) ) : ?>
                        <tr><td colspan="4" style="text-align:center;padding:16px;color:#666;">No recipients match this filter.</td></tr>
                    <?php else : foreach ( $logs as $log ) : ?>
                    <tr <?php if ( $log->opened_at ) echo 'style="background:#f0fff4;"'; ?>>
                        <td><?php echo esc_html( $log->email ); ?></td>
                        <td><span class="jemail-status jemail-status-<?php echo esc_attr( $log->status ); ?>"><?php echo esc_html( ucfirst( $log->status ) ); ?></span></td>
                        <td><?php echo $log->sent_at   ? esc_html( date( 'M j g:ia', strtotime( $log->sent_at ) ) )   : '—'; ?></td>
                        <td><?php echo $log->opened_at ? esc_html( date( 'M j g:ia', strtotime( $log->opened_at ) ) ) : '—'; ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav bottom" style="margin-top:8px;">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo intval( $total_logs ); ?> items</span>
                    <span class="pagination-links">
                        <?php if ( $current_page > 1 ) : ?>
                            <a class="first-page button" href="<?php echo esc_url( add_query_arg( array( 'log_page' => 1, 'log_status' => $log_status ), $base_url ) ); ?>">«</a>
                            <a class="prev-page button" href="<?php echo esc_url( add_query_arg( array( 'log_page' => $current_page - 1, 'log_status' => $log_status ), $base_url ) ); ?>">‹</a>
                        <?php else : ?>
                            <span class="first-page button disabled">«</span>
                            <span class="prev-page button disabled">‹</span>
                        <?php endif; ?>
                        <span class="paging-input">
                            <?php echo intval( $current_page ); ?> of <span class="total-pages"><?php echo intval( $total_pages ); ?></span>
                        </span>
                        <?php if ( $current_page < $total_pages ) : ?>
                            <a class="next-page button" href="<?php echo esc_url( add_query_arg( array( 'log_page' => $current_page + 1, 'log_status' => $log_status ), $base_url ) ); ?>">›</a>
                            <a class="last-page button" href="<?php echo esc_url( add_query_arg( array( 'log_page' => $total_pages, 'log_status' => $log_status ), $base_url ) ); ?>">»</a>
                        <?php else : ?>
                            <span class="next-page button disabled">›</span>
                            <span class="last-page button disabled">»</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ═══════════════════════════════════════
       PAGE: Unsubscribes
       ═══════════════════════════════════════ */
    public function page_unsubs() {
        $unsubs = $this->db->get_unsubscribes( 500 );
        ?>
        <div class="wrap jemail-wrap">
            <h1>Unsubscribed Users</h1>
            <?php $this->show_notices(); ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Email</th><th>User</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                    <?php if ( empty( $unsubs ) ) : ?>
                        <tr><td colspan="4" style="text-align:center;padding:15px;">No unsubscribes yet.</td></tr>
                    <?php else : foreach ( $unsubs as $u ) :
                        $user = $u->user_id ? get_userdata( $u->user_id ) : null;
                        $resub_url = wp_nonce_url( admin_url( 'admin-post.php?action=jemail_resubscribe&email=' . rawurlencode( $u->email ) ), 'jemail_resub_' . $u->email );
                    ?>
                        <tr>
                            <td><?php echo esc_html( $u->email ); ?></td>
                            <td><?php echo $user ? esc_html( $user->display_name ) : '—'; ?></td>
                            <td><?php echo esc_html( date( 'M j, Y g:ia', strtotime( $u->unsubscribed_at ) ) ); ?></td>
                            <td><a href="<?php echo esc_url( $resub_url ); ?>" onclick="return confirm('Re-subscribe this user?');">Re-subscribe</a></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /* ═══════════════════════════════════════
       PAGE: Settings
       ═══════════════════════════════════════ */
    public function page_settings() {
        ?>
        <div class="wrap jemail-wrap">
            <h1>JEmail Settings</h1>
            <?php $this->show_notices(); ?>
            <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
                <?php wp_nonce_field( 'jemail_save_settings', 'jemail_settings_nonce' ); ?>
                <input type="hidden" name="action" value="jemail_save_settings" />

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="jemail_from_name">From Name</label></th>
                        <td><input type="text" id="jemail_from_name" name="jemail_from_name" value="<?php echo esc_attr( get_option( 'jemail_from_name', get_bloginfo( 'name' ) ) ); ?>" class="regular-text" />
                        <p class="description">Sender name shown to recipients. FluentSMTP may override this.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="jemail_from_email">From Email</label></th>
                        <td><input type="email" id="jemail_from_email" name="jemail_from_email" value="<?php echo esc_attr( get_option( 'jemail_from_email', get_bloginfo( 'admin_email' ) ) ); ?>" class="regular-text" />
                        <p class="description">Sender email. FluentSMTP may override this.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="jemail_batch_size">Batch Size</label></th>
                        <td><input type="number" id="jemail_batch_size" name="jemail_batch_size" value="<?php echo esc_attr( get_option( 'jemail_batch_size', 50 ) ); ?>" min="1" max="500" class="small-text" />
                        <p class="description">Emails sent per batch. Keep low (25-50) for shared hosting / FluentSMTP limits.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="jemail_batch_delay">Batch Delay (seconds)</label></th>
                        <td><input type="number" id="jemail_batch_delay" name="jemail_batch_delay" value="<?php echo esc_attr( get_option( 'jemail_batch_delay', 60 ) ); ?>" min="10" max="3600" class="small-text" />
                        <p class="description">Seconds between batches. Minimum 10.</p></td>
                    </tr>
                    <tr>
                        <th scope="row">Open &amp; Click Tracking</th>
                        <td><label><input type="checkbox" name="jemail_tracking_enabled" value="1" <?php checked( get_option( 'jemail_tracking_enabled', '1' ), '1' ); ?> /> Enable open and click tracking</label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="jemail_footer_html">Email Footer HTML</label></th>
                        <td><textarea id="jemail_footer_html" name="jemail_footer_html" rows="6" class="large-text code"><?php echo esc_textarea( get_option( 'jemail_footer_html', '' ) ); ?></textarea>
                        <p class="description">Appended to every email. Use merge tags like <code>{{unsubscribe_url}}</code>, <code>{{site_name}}</code>.</p></td>
                    </tr>
                </table>

                <?php submit_button( 'Save Settings' ); ?>
            </form>

            <hr />
            <h2>System Info</h2>
            <table class="form-table">
                <tr>
                    <th>SWPM Levels Detected</th>
                    <td><?php
                        $levels = $this->db->get_swpm_levels();
                        if ( empty( $levels ) ) { echo '<em>None (Simple Membership not active or no levels)</em>'; }
                        else { foreach ( $levels as $id => $name ) { echo esc_html( $name ) . ' (ID ' . $id . '), '; } }
                    ?></td>
                </tr>
                <tr>
                    <th>UsersWP Forms Detected</th>
                    <td><?php
                        $forms = $this->db->get_uwp_form_types();
                        if ( empty( $forms ) ) { echo '<em>None detected</em>'; }
                        else { foreach ( $forms as $id => $name ) { echo esc_html( $name ) . ' (ID ' . $id . '), '; } }
                    ?></td>
                </tr>
                <tr>
                    <th>Vendor Hub Statuses</th>
                    <td><?php
                        $vs = $this->db->get_vendor_statuses();
                        if ( empty( $vs ) ) { echo '<em>Vendor Hub not active or no applications</em>'; }
                        else { echo esc_html( implode( ', ', $vs ) ); }
                    ?></td>
                </tr>
                <tr>
                    <th>Volunteer Hub</th>
                    <td><?php
                        $vol = $this->db->get_volunteer_statuses();
                        if ( empty( $vol ) ) { echo '<em>Volunteer Hub not active or no volunteers</em>'; }
                        else { echo 'Active — Statuses: ' . esc_html( implode( ', ', array_map( 'ucfirst', $vol ) ) ); }
                    ?></td>
                </tr>
                <tr>
                    <th>GeoDirectory</th>
                    <td><?php
                        if ( $this->db->has_geodirectory() ) {
                            $gd_ids = $this->db->get_user_ids_with_gd_listing();
                            echo '✅ Active — ' . count( $gd_ids ) . ' user(s) with listings';
                        } else { echo '<em>GeoDirectory not active</em>'; }
                    ?></td>
                </tr>
                <tr>
                    <th>Awards Hub</th>
                    <td><?php
                        $ay = $this->db->get_awards_years();
                        if ( empty( $ay ) ) { echo '<em>Awards plugin not active or no submissions found</em>'; }
                        else { echo 'Active - Submission years: ' . esc_html( implode( ', ', $ay ) ); }
                    ?></td>
                </tr>
                <tr>
                    <th>FluentSMTP Active</th>
                    <td><?php echo is_plugin_active( 'fluent-smtp/fluent-smtp.php' ) ? '✅ Yes — emails will be routed through FluentSMTP' : '❌ No — using default wp_mail()'; ?></td>
                </tr>
                <tr>
                    <th>Unsub Secret</th>
                    <td><code><?php echo esc_html( substr( get_option( 'jemail_unsub_secret', 'not set' ), 0, 8 ) ); ?>…</code> <small>(HMAC key for unsubscribe tokens)</small></td>
                </tr>
            </table>
        </div>
        <?php
    }

    /* ═══════════════════════════════════════
       AJAX: Count Recipients
       ═══════════════════════════════════════ */
    public function ajax_count_recipients() {
        check_ajax_referer( 'jemail_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'No permission' );

        $roles              = isset( $_POST['roles'] ) ? array_map( 'sanitize_text_field', (array) $_POST['roles'] ) : array();
        $swpm_levels        = isset( $_POST['swpm_levels'] ) ? array_map( 'absint', (array) $_POST['swpm_levels'] ) : array();
        $uwp_forms          = isset( $_POST['uwp_forms'] ) ? array_map( 'sanitize_text_field', (array) $_POST['uwp_forms'] ) : array();
        $vendor_statuses    = isset( $_POST['vendor_statuses'] ) ? array_map( 'sanitize_text_field', (array) $_POST['vendor_statuses'] ) : array();
        $volunteer_statuses = isset( $_POST['volunteer_statuses'] ) ? array_map( 'sanitize_text_field', (array) $_POST['volunteer_statuses'] ) : array();
        $gd_listing         = sanitize_text_field( $_POST['gd_listing'] ?? '' );
        $awards_year        = sanitize_text_field( $_POST['awards_year'] ?? '' );

        $count = $this->db->count_recipients( $roles, $swpm_levels, $uwp_forms, $vendor_statuses, $volunteer_statuses, $gd_listing, $awards_year );
        wp_send_json_success( array( 'count' => $count ) );
    }

    /* ═══════════════════════════════════════
       AJAX: Preview Recipients
       ═══════════════════════════════════════ */
    public function ajax_preview_recipients() {
        check_ajax_referer( 'jemail_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'No permission' );

        $roles              = isset( $_POST['roles'] ) ? array_map( 'sanitize_text_field', (array) $_POST['roles'] ) : array();
        $swpm_levels        = isset( $_POST['swpm_levels'] ) ? array_map( 'absint', (array) $_POST['swpm_levels'] ) : array();
        $uwp_forms          = isset( $_POST['uwp_forms'] ) ? array_map( 'sanitize_text_field', (array) $_POST['uwp_forms'] ) : array();
        $vendor_statuses    = isset( $_POST['vendor_statuses'] ) ? array_map( 'sanitize_text_field', (array) $_POST['vendor_statuses'] ) : array();
        $volunteer_statuses = isset( $_POST['volunteer_statuses'] ) ? array_map( 'sanitize_text_field', (array) $_POST['volunteer_statuses'] ) : array();
        $gd_listing         = sanitize_text_field( $_POST['gd_listing'] ?? '' );
        $awards_year        = sanitize_text_field( $_POST['awards_year'] ?? '' );

        $all        = $this->db->get_recipients( $roles, $swpm_levels, $uwp_forms, $vendor_statuses, $volunteer_statuses, $gd_listing, $awards_year );
        $total      = count( $all );
        $preview    = array_slice( $all, 0, 25 );

        $list = array();
        foreach ( $preview as $r ) {
            $name  = isset( $r->display_name ) && $r->display_name ? $r->display_name : '';
            $email = isset( $r->user_email )   ? $r->user_email   : '';
            $list[] = array( 'name' => $name, 'email' => $email );
        }

        wp_send_json_success( array( 'total' => $total, 'preview' => $list ) );
    }

    /* ═══════════════════════════════════════
       AJAX: Send Test Email
       ═══════════════════════════════════════ */
    public function ajax_send_test() {
        check_ajax_referer( 'jemail_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'No permission' );

        $test_email   = sanitize_email( $_POST['test_email'] ?? '' );
        $subject      = sanitize_text_field( $_POST['subject'] ?? 'Test Email' );
        $content_type = sanitize_text_field( $_POST['content_type'] ?? 'html' );
        $html_content = wp_kses_post( $_POST['html_content'] ?? '' );
        $text_content = sanitize_textarea_field( $_POST['text_content'] ?? '' );

        if ( ! is_email( $test_email ) ) wp_send_json_error( 'Invalid email address.' );

        // Build a fake campaign object
        $campaign = (object) array(
            'id'           => 0,
            'subject'      => $subject,
            'content_type' => $content_type,
            'html_content' => $html_content,
            'text_content' => $text_content,
        );

        // Use current admin user for merge tags
        $user = wp_get_current_user();
        $log  = (object) array(
            'id'          => 0,
            'campaign_id' => 0,
            'user_id'     => $user->ID,
            'email'       => $test_email,
        );

        $body = $this->sender->render_email( $campaign, $log, $user, false );
        $rendered_subject = $this->sender->replace_merge_tags( $subject, $user, $log );

        $from_name  = get_option( 'jemail_from_name', get_bloginfo( 'name' ) );
        $from_email = get_option( 'jemail_from_email', get_bloginfo( 'admin_email' ) );
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        );

        $sent = wp_mail( $test_email, $rendered_subject, $body, $headers );

        if ( $sent ) {
            wp_send_json_success( 'Test email sent to ' . $test_email );
        } else {
            wp_send_json_error( 'wp_mail failed. Check FluentSMTP logs.' );
        }
    }

    /**
     * AJAX: Manually process a batch of emails right now.
     */
    public function ajax_process_now() {
        check_ajax_referer( 'jemail_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'No permission' );

        $this->sender->process_batch();

        $campaign_id = absint( $_POST['campaign_id'] ?? 0 );
        if ( $campaign_id ) {
            $campaign = $this->db->get_campaign( $campaign_id );
            $stats    = $this->db->get_campaign_stats( $campaign_id );
            wp_send_json_success( array(
                'status'     => $campaign ? $campaign->status : 'unknown',
                'sent_count' => $campaign ? (int) $campaign->sent_count : 0,
                'fail_count' => $campaign ? (int) $campaign->fail_count : 0,
                'total_count' => $campaign ? (int) $campaign->total_count : 0,
                'queued'     => $stats ? (int) $stats->queued : 0,
            ) );
        }
        wp_send_json_success( 'Batch processed.' );
    }

    /**
     * AJAX: Get live campaign status for polling.
     */
    public function ajax_campaign_status() {
        check_ajax_referer( 'jemail_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'No permission' );

        $campaign_id = absint( $_POST['campaign_id'] ?? 0 );
        if ( ! $campaign_id ) wp_send_json_error( 'No campaign ID' );

        $campaign = $this->db->get_campaign( $campaign_id );
        $stats    = $this->db->get_campaign_stats( $campaign_id );
        wp_send_json_success( array(
            'status'     => $campaign ? $campaign->status : 'unknown',
            'sent_count' => $campaign ? (int) $campaign->sent_count : 0,
            'fail_count' => $campaign ? (int) $campaign->fail_count : 0,
            'total_count' => $campaign ? (int) $campaign->total_count : 0,
            'queued'     => $stats ? (int) $stats->queued : 0,
        ) );
    }

    /* ═══════════════════════════════════════
       POST Handlers
       ═══════════════════════════════════════ */
    public function save_campaign() {
        if ( ! wp_verify_nonce( $_POST['jemail_nonce'] ?? '', 'jemail_save_campaign' ) ) wp_die( 'Invalid nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No permission' );

        $campaign_id    = absint( $_POST['campaign_id'] ?? 0 );
        $save_type      = sanitize_text_field( $_POST['save_type'] ?? 'draft' );
        $existing_status = '';
        if ( $campaign_id ) {
            $existing = $this->db->get_campaign( $campaign_id );
            $existing_status = $existing ? $existing->status : '';
        }

        $data = array(
            'title'                    => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
            'subject'                  => sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) ),
            'html_content'             => wp_kses_post( wp_unslash( $_POST['html_content'] ?? '' ) ),
            'text_content'             => sanitize_textarea_field( wp_unslash( $_POST['text_content'] ?? '' ) ),
            'content_type'             => in_array( $_POST['content_type'] ?? '', array( 'html', 'text' ), true ) ? $_POST['content_type'] : 'html',
            'target_roles'             => maybe_serialize( isset( $_POST['target_roles'] ) ? array_map( 'sanitize_text_field', (array) $_POST['target_roles'] ) : array() ),
            'target_swpm_levels'       => maybe_serialize( isset( $_POST['target_swpm_levels'] ) ? array_map( 'sanitize_text_field', (array) $_POST['target_swpm_levels'] ) : array() ),
            'target_uwp_forms'         => maybe_serialize( isset( $_POST['target_uwp_forms'] ) ? array_map( 'sanitize_text_field', (array) $_POST['target_uwp_forms'] ) : array() ),
            'target_vendor_statuses'   => maybe_serialize( isset( $_POST['target_vendor_statuses'] ) ? array_map( 'sanitize_text_field', (array) $_POST['target_vendor_statuses'] ) : array() ),
            'target_volunteer_statuses' => maybe_serialize( isset( $_POST['target_volunteer_statuses'] ) ? array_map( 'sanitize_text_field', (array) $_POST['target_volunteer_statuses'] ) : array() ),
            'target_gd_listing'        => sanitize_text_field( $_POST['target_gd_listing'] ?? '' ),
            'target_awards_year'       => sanitize_text_field( $_POST['target_awards_year'] ?? '' ),
        );

        // When saving draft from a scheduled campaign, cancel the schedule
        if ( $save_type === 'draft' && $existing_status === 'scheduled' ) {
            $data['status']       = 'draft';
            $data['scheduled_at'] = null;
        }

        if ( $campaign_id ) {
            $this->db->update_campaign( $campaign_id, $data );
        } else {
            $data['status'] = 'draft';
            $campaign_id    = $this->db->insert_campaign( $data );
        }

        // Handle "Schedule Send"
        if ( $save_type === 'schedule' && $campaign_id ) {
            if ( empty( $data['subject'] ) ) {
                wp_redirect( admin_url( 'admin.php?page=jemail-new&campaign_id=' . $campaign_id . '&message=empty_subject' ) );
                exit;
            }
            $raw_dt   = sanitize_text_field( $_POST['scheduled_at'] ?? '' );
            $mysql_dt = $raw_dt ? date( 'Y-m-d H:i:s', strtotime( str_replace( 'T', ' ', $raw_dt ) ) ) : '';
            if ( $mysql_dt ) {
                $this->db->update_campaign( $campaign_id, array(
                    'status'       => 'scheduled',
                    'scheduled_at' => $mysql_dt,
                ) );
                wp_redirect( admin_url( 'admin.php?page=jemail-new&campaign_id=' . $campaign_id . '&message=scheduled' ) );
            } else {
                wp_redirect( admin_url( 'admin.php?page=jemail-new&campaign_id=' . $campaign_id . '&message=no_date' ) );
            }
            exit;
        }

        // "Send Now" button submits this form with start_after_save=1 so that the
        // current targeting is always saved before the campaign starts. This prevents
        // the bug where Send Now would use stale saved data if the user had changed
        // the targeting form without clicking Save Draft first.
        if ( ! empty( $_POST['start_after_save'] ) && $campaign_id ) {
            // Block sending if the subject is empty — a missing subject is often caused
            // by the user not saving the form before sending. The save above already
            // wrote the current form state, so if subject is still empty it was never set.
            if ( empty( $data['subject'] ) ) {
                wp_redirect( admin_url( 'admin.php?page=jemail-new&campaign_id=' . $campaign_id . '&message=empty_subject' ) );
                exit;
            }
            $nonce     = wp_create_nonce( 'jemail_start_' . $campaign_id );
            $start_url = admin_url( 'admin-post.php?action=jemail_start_campaign&id=' . $campaign_id . '&_wpnonce=' . $nonce );
            wp_redirect( $start_url );
        } else {
            $msg = ( $save_type === 'draft' && $existing_status === 'scheduled' ) ? 'unscheduled' : 'saved';
            wp_redirect( admin_url( 'admin.php?page=jemail-new&campaign_id=' . $campaign_id . '&message=' . $msg ) );
        }
        exit;
    }

    public function start_campaign() {
        $id = absint( $_GET['id'] ?? 0 );
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'jemail_start_' . $id ) ) wp_die( 'Invalid nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No permission' );

        $result = $this->sender->start_campaign( $id );

        // Immediately process the first batch so emails start sending right away
        if ( $result ) {
            $this->sender->process_batch();
        }

        $msg = $result ? 'started' : 'error';
        wp_redirect( admin_url( 'admin.php?page=jemail-new&campaign_id=' . $id . '&message=' . $msg ) );
        exit;
    }

    public function delete_campaign() {
        $id = absint( $_GET['id'] ?? 0 );
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'jemail_delete_' . $id ) ) wp_die( 'Invalid nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No permission' );

        $this->db->delete_campaign( $id );
        wp_redirect( admin_url( 'admin.php?page=jemail&message=deleted' ) );
        exit;
    }

    public function save_settings() {
        if ( ! wp_verify_nonce( $_POST['jemail_settings_nonce'] ?? '', 'jemail_save_settings' ) ) wp_die( 'Invalid nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No permission' );

        update_option( 'jemail_from_name', sanitize_text_field( $_POST['jemail_from_name'] ?? '' ) );
        update_option( 'jemail_from_email', sanitize_email( $_POST['jemail_from_email'] ?? '' ) );
        update_option( 'jemail_batch_size', absint( $_POST['jemail_batch_size'] ?? 50 ) );
        update_option( 'jemail_batch_delay', max( 10, absint( $_POST['jemail_batch_delay'] ?? 60 ) ) );
        update_option( 'jemail_tracking_enabled', isset( $_POST['jemail_tracking_enabled'] ) ? '1' : '0' );
        update_option( 'jemail_footer_html', wp_kses_post( $_POST['jemail_footer_html'] ?? '' ) );

        wp_redirect( admin_url( 'admin.php?page=jemail-settings&message=settings_saved' ) );
        exit;
    }

    public function resubscribe_user() {
        $email = isset( $_GET['email'] ) ? rawurldecode( $_GET['email'] ) : '';
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'jemail_resub_' . $email ) ) wp_die( 'Invalid nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No permission' );

        $this->db->remove_unsubscribe( $email );
        wp_redirect( admin_url( 'admin.php?page=jemail-unsubs&message=resubscribed' ) );
        exit;
    }

    /* ═══════════════════════════════════════
       Notices
       ═══════════════════════════════════════ */
    private function show_notices() {
        $msg = $_GET['message'] ?? '';
        $notices = array(
            'saved'           => array( 'success', 'Campaign saved.' ),
            'started'         => array( 'success', 'Campaign started! Emails are being sent in batches.' ),
            'deleted'         => array( 'success', 'Campaign deleted.' ),
            'settings_saved'  => array( 'success', 'Settings saved.' ),
            'resubscribed'    => array( 'success', 'User re-subscribed.' ),
            'scheduled'       => array( 'success', 'Campaign scheduled. It will send automatically at the selected time.' ),
            'unscheduled'     => array( 'success', 'Schedule cancelled. Campaign saved as draft.' ),
            'error'           => array( 'error', 'Something went wrong. Campaign may have no matching recipients or is not a draft.' ),
            'empty_subject'   => array( 'error', 'Cannot send: the email subject line is empty. Please fill in the subject and try again.' ),
            'no_date'         => array( 'error', 'Cannot schedule: please select a date and time first.' ),
        );
        if ( isset( $notices[ $msg ] ) ) {
            printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $notices[ $msg ][0] ), esc_html( $notices[ $msg ][1] ) );
        }
    }
}
