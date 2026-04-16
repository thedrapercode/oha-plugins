<?php
/**
 * OHA Passport Hub — Admin
 *
 * Admin panel: event registry management, live stats, testing mode, CSV export.
 * Replaces the settings buried in OHA PWA Core Settings with a dedicated top-level menu.
 *
 * @package OHA_Passport_Hub
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class OHA_Passport_Admin {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action( 'admin_menu',             [ $this, 'add_admin_menus' ] );
        add_action( 'admin_enqueue_scripts',  [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_ajax_oha_admin_toggle_testing',   [ $this, 'ajax_toggle_testing' ] );
        add_action( 'wp_ajax_oha_admin_save_event',       [ $this, 'ajax_save_event' ] );
        add_action( 'wp_ajax_oha_admin_delete_event',     [ $this, 'ajax_delete_event' ] );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, 'oha-passport-badges' ) !== false ) {
            wp_enqueue_style(
                'font-awesome-6',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
                [],
                '6.5.0'
            );
            wp_enqueue_media();
        }
    }

    public function add_admin_menus() {
        add_menu_page(
            'OHA Passport Hub',
            'Passport Hub',
            'manage_options',
            'oha-passport-hub',
            [ $this, 'page_dashboard' ],
            'dashicons-id-alt',
            58
        );
        add_submenu_page( 'oha-passport-hub', 'Dashboard',      'Dashboard',      'manage_options', 'oha-passport-hub',           [ $this, 'page_dashboard' ] );
        add_submenu_page( 'oha-passport-hub', 'Events',         'Events',         'manage_options', 'oha-passport-events',        [ $this, 'page_events' ] );
        add_submenu_page( 'oha-passport-hub', 'Actors',         'Actors',         'manage_options', 'oha-passport-actors',        [ $this, 'page_actors' ] );
        add_submenu_page( 'oha-passport-hub', 'Vendors',        'Vendors',        'manage_options', 'oha-passport-vendors',       [ $this, 'page_vendors' ] );
        add_submenu_page( 'oha-passport-hub', 'Scavenger Hunt', 'Scavenger Hunt', 'manage_options', 'oha-passport-scavenger',     [ $this, 'page_scavenger' ] );
        add_submenu_page( 'oha-passport-hub', 'Badges',         'Badges',         'manage_options', 'oha-passport-badges',        [ $this, 'page_badges' ] );
        add_submenu_page( 'oha-passport-hub', 'Export Data',    'Export Data',    'manage_options', 'oha-passport-export',        [ $this, 'page_export' ] );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Dashboard page
    // ─────────────────────────────────────────────────────────────────────
    public function page_dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $events       = OHA_Passport_Events::get_instance();
        $qr_manager   = OHA_Passport_QR_Manager::get_instance();
        $is_active    = $events->is_any_event_active();
        $active_event = $events->get_active_event();
        $status_label = $events->get_status_label();
        $testing_mode = get_option( 'oha_passport_testing_mode', false );
        $stats        = $active_event
            ? $qr_manager->get_event_statistics( $active_event['slug'] )
            : $qr_manager->get_event_statistics( 'oha-convention-2026' );
        $nonce        = wp_create_nonce( OHA_PH_NONCE );
        ?>
        <div class="wrap oha-ph-admin">
            <h1>🎭 OHA Passport Hub</h1>

            <div class="notice notice-info"><p><strong>OHA Passport Hub v<?php echo OHA_PH_VERSION; ?></strong> — Multi-event actor passport and badge trading system</p></div>

            <!-- Event Status Card -->
            <div class="oha-ph-admin-card">
                <h2>Event Status</h2>
                <table class="form-table">
                    <tr>
                        <th>Current Status</th>
                        <td>
                            <?php if ( $is_active ) : ?>
                                <span style="color:#27ae60;font-weight:700;">✅ <?php echo esc_html( $status_label ); ?></span>
                            <?php else : ?>
                                <span style="color:#e67e22;font-weight:700;">⏸️ <?php echo esc_html( $status_label ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Testing Mode</th>
                        <td>
                            <button id="oha-toggle-testing" class="button <?php echo $testing_mode ? 'button-secondary' : 'button-primary'; ?>"
                                    data-enabled="<?php echo $testing_mode ? 'true' : 'false'; ?>"
                                    data-nonce="<?php echo $nonce; ?>">
                                <?php echo $testing_mode ? '⏹ Disable Testing Mode' : '▶ Enable Testing Mode'; ?>
                            </button>
                            <p class="description">Testing mode enables badge trading outside of scheduled event dates.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Stats Card -->
            <div class="oha-ph-admin-card">
                <h2>📊 <?php echo $active_event ? esc_html( $active_event['event_name'] ) : 'Convention 2026'; ?> — Live Stats</h2>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:16px 0;">
                    <div style="text-align:center;padding:20px;background:#f0f0f0;border-radius:8px;">
                        <div style="font-size:2em;font-weight:700;color:#8B0000;"><?php echo intval( $stats['total_trades'] ); ?></div>
                        <div>Total Badge Trades</div>
                    </div>
                    <div style="text-align:center;padding:20px;background:#f0f0f0;border-radius:8px;">
                        <div style="font-size:2em;font-weight:700;color:#8B0000;"><?php echo intval( $stats['unique_participants'] ); ?></div>
                        <div>Participating Actors</div>
                    </div>
                    <div style="text-align:center;padding:20px;background:#f0f0f0;border-radius:8px;">
                        <div style="font-size:2em;font-weight:700;color:#8B0000;"><?php echo intval( $stats['haunts_count'] ); ?></div>
                        <div>Haunts Represented</div>
                    </div>
                </div>
                <?php if ( ! empty( $stats['haunts_represented'] ) ) : ?>
                <details><summary style="cursor:pointer;font-weight:600;">Haunts breakdown</summary>
                    <table class="wp-list-table widefat fixed striped" style="margin-top:8px;">
                        <thead><tr><th>Haunt</th><th>Active Actors</th></tr></thead>
                        <tbody>
                        <?php foreach ( $stats['haunts_represented'] as $haunt ) : ?>
                            <tr><td><?php echo esc_html( $haunt->haunt_name ); ?></td><td><?php echo intval( $haunt->actor_count ); ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </details>
                <?php endif; ?>
            </div>

            <script>
            jQuery('#oha-toggle-testing').on('click', function() {
                var $btn = jQuery(this);
                var enabled = $btn.data('enabled') === 'true';
                $btn.prop('disabled', true).text('…');
                jQuery.post(ajaxurl, { action: 'oha_admin_toggle_testing', enable: !enabled, nonce: $btn.data('nonce') }, function(r) {
                    if (r.success) { location.reload(); }
                    else { alert('Error: ' + r.data); $btn.prop('disabled', false); }
                });
            });
            </script>
        </div>
        <?php
    }

    // ─────────────────────────────────────────────────────────────────────
    // Events management page
    // ─────────────────────────────────────────────────────────────────────
    public function page_events() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $events = OHA_Passport_Events::get_instance()->get_all_events();
        $nonce  = wp_create_nonce( OHA_PH_NONCE );
        ?>
        <div class="wrap oha-ph-admin">
            <h1>📅 Event Registry</h1>
            <p>Register events here to enable badge trading on those dates. Profiles persist across events — actors keep their character year to year.</p>

            <!-- Add Event Form -->
            <div class="oha-ph-admin-card">
                <h2>Add New Event</h2>
                <form id="oha-add-event-form">
                    <table class="form-table">
                        <tr><th><label>Event Slug *</label></th><td>
                            <input type="text" name="slug" class="regular-text" placeholder="oha-convention-2027" required>
                            <p class="description">Lowercase letters, numbers, hyphens only. Used in stamp records.</p>
                        </td></tr>
                        <tr><th><label>Event Name *</label></th><td>
                            <input type="text" name="event_name" class="regular-text" placeholder="OHA Convention 2027" required>
                        </td></tr>
                        <tr><th><label>Start Date *</label></th><td>
                            <input type="date" name="start_date" required>
                        </td></tr>
                        <tr><th><label>End Date *</label></th><td>
                            <input type="date" name="end_date" required>
                        </td></tr>
                        <tr><th><label>Location</label></th><td>
                            <input type="text" name="location" class="regular-text" placeholder="Ohio State Reformatory, Mansfield, OH">
                        </td></tr>
                    </table>
                    <p><button type="submit" class="button button-primary" data-nonce="<?php echo $nonce; ?>">Add Event</button></p>
                    <div id="oha-event-form-result" style="display:none;"></div>
                </form>
            </div>

            <!-- Events Table -->
            <div class="oha-ph-admin-card">
                <h2>Registered Events</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr>
                        <th>Event</th><th>Slug</th><th>Dates</th><th>Location</th><th>Status</th><th>Actions</th>
                    </tr></thead>
                    <tbody>
                    <?php if ( empty( $events ) ) : ?>
                        <tr><td colspan="6">No events registered yet.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $events as $event ) :
                            $today     = current_time( 'Y-m-d' );
                            $is_past   = $event['end_date'] < $today;
                            $is_now    = $event['start_date'] <= $today && $event['end_date'] >= $today;
                            $is_future = $event['start_date'] > $today;
                            $dot = $is_now ? '🟢' : ( $is_past ? '⚫' : '🔵' );
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html( $event['event_name'] ); ?></strong></td>
                            <td><code><?php echo esc_html( $event['slug'] ); ?></code></td>
                            <td><?php echo esc_html( $event['start_date'] . ' – ' . $event['end_date'] ); ?></td>
                            <td><?php echo esc_html( $event['location'] ?? '—' ); ?></td>
                            <td><?php echo $dot; ?> <?php echo $is_now ? 'Active' : ( $is_past ? 'Past' : 'Upcoming' ); ?></td>
                            <td>
                                <button class="button oha-delete-event" data-id="<?php echo intval( $event['id'] ); ?>" data-nonce="<?php echo $nonce; ?>">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <script>
            jQuery(function($) {
                $('#oha-add-event-form').on('submit', function(e) {
                    e.preventDefault();
                    var data = $(this).serializeArray().reduce(function(o, f){ o[f.name] = f.value; return o; }, {});
                    data.action = 'oha_admin_save_event';
                    data.nonce  = $(this).find('[data-nonce]').data('nonce');
                    $.post(ajaxurl, data, function(r) {
                        if (r.success) { location.reload(); }
                        else { $('#oha-event-form-result').show().html('<div class="notice notice-error"><p>' + r.data + '</p></div>'); }
                    });
                });
                $(document).on('click', '.oha-delete-event', function() {
                    if (!confirm('Delete this event? Only works if it has no stamp data.')) return;
                    $.post(ajaxurl, { action: 'oha_admin_delete_event', id: $(this).data('id'), nonce: $(this).data('nonce') }, function(r) {
                        if (r.success) { location.reload(); }
                        else { alert('Error: ' + r.data); }
                    });
                });
            });
            </script>
        </div>
        <?php
    }

    // ─────────────────────────────────────────────────────────────────────
    // Actors page
    // ─────────────────────────────────────────────────────────────────────
    public function page_actors() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        global $wpdb;
        $profiles = $wpdb->get_results(
            "SELECT ap.*, u.user_login, u.user_email
             FROM {$wpdb->prefix}oha_actor_profiles ap
             LEFT JOIN {$wpdb->users} u ON ap.user_id = u.ID
             WHERE ap.is_active = 1
             ORDER BY ap.stamps_earned DESC, ap.character_name ASC"
        );
        ?>
        <div class="wrap oha-ph-admin">
            <h1>🎭 Actor Profiles (<?php echo count( $profiles ); ?>)</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Character</th><th>Type</th><th>Haunt</th><th>Stamps</th><th>WP User</th><th>Created</th></tr></thead>
                <tbody>
                <?php if ( empty( $profiles ) ) : ?>
                    <tr><td colspan="6">No actor profiles yet.</td></tr>
                <?php else : ?>
                    <?php foreach ( $profiles as $p ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $p->character_name ); ?></strong></td>
                        <td><?php echo esc_html( $p->character_type ?? '—' ); ?></td>
                        <td><?php echo esc_html( $p->haunt_name ?? '—' ); ?></td>
                        <td><?php echo intval( $p->stamps_earned ); ?></td>
                        <td><?php echo esc_html( $p->user_login ); ?></td>
                        <td><?php echo esc_html( substr( $p->created_at, 0, 10 ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // ─────────────────────────────────────────────────────────────────────
    // Export page
    // ─────────────────────────────────────────────────────────────────────
    public function page_export() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $events = OHA_Passport_Events::get_instance()->get_all_events();
        $nonce  = wp_create_nonce( OHA_PH_NONCE );
        ?>
        <div class="wrap oha-ph-admin">
            <h1>📥 Export Stamp Data</h1>
            <div class="oha-ph-admin-card">
                <h2>Download Stamps CSV</h2>
                <table class="form-table">
                    <tr><th><label for="export-event">Event</label></th><td>
                        <select id="export-event">
                            <option value="">All Events</option>
                            <?php foreach ( $events as $ev ) : ?>
                            <option value="<?php echo esc_attr( $ev['slug'] ); ?>"><?php echo esc_html( $ev['event_name'] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td></tr>
                </table>
                <p><button id="export-csv-btn" class="button button-primary" data-nonce="<?php echo $nonce; ?>">Download CSV</button></p>
            </div>
            <script>
            jQuery('#export-csv-btn').on('click', function() {
                var $btn = jQuery(this);
                $btn.prop('disabled', true).text('Generating…');
                jQuery.post(ajaxurl, {
                    action: 'oha_export_stamps_csv',
                    event_slug: jQuery('#export-event').val(),
                    nonce: $btn.data('nonce')
                }, function(r) {
                    $btn.prop('disabled', false).text('Download CSV');
                    if (r.success) {
                        var blob = new Blob([r.data.csv_data], {type:'text/csv'});
                        var url  = URL.createObjectURL(blob);
                        var a    = document.createElement('a');
                        a.href = url; a.download = r.data.filename;
                        document.body.appendChild(a); a.click();
                        URL.revokeObjectURL(url); a.remove();
                    } else { alert('Error: ' + r.data); }
                });
            });
            </script>
        </div>
        <?php
    }

    // ─────────────────────────────────────────────────────────────────────
    // Badges admin page
    // ─────────────────────────────────────────────────────────────────────
    public function page_badges() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $badges = OHA_Passport_Badges::get_instance()->get_all_badge_defs( false );
        $nonce  = wp_create_nonce( OHA_PH_NONCE );
        $unlock_type_labels = [
            'profile_created'  => 'Profile created (automatic)',
            'stamp_count'      => 'Total stamps collected',
            'stamp_type_count' => 'Stamps of a specific type',
            'event_stamp'      => 'Any stamp from a specific event',
            'manual'           => 'Manual admin award only',
        ];
        ?>
        <div class="wrap oha-ph-admin">
            <h1>Passport Badges</h1>
            <p>Badges are awarded automatically when unlock conditions are met, or manually by admins. Icons use Font Awesome 6 Free class names (e.g., <code>fa-solid fa-star</code>) or a custom uploaded image.</p>

            <!-- Badge list -->
            <div class="oha-ph-admin-card">
                <h2>Active Badges (<?php echo count( $badges ); ?>)</h2>
                <?php if ( empty( $badges ) ) : ?>
                <p>No badges yet. Use the form below to add the first one.</p>
                <?php else : ?>
                <table class="wp-list-table widefat fixed striped" id="oha-badge-table">
                    <thead><tr>
                        <th style="width:52px;">Icon</th>
                        <th>Name</th>
                        <th>Unlock Condition</th>
                        <th style="width:60px;">Order</th>
                        <th style="width:60px;">Active</th>
                        <th style="width:120px;">Actions</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ( $badges as $b ) :
                        $icon_html = '';
                        if ( $b['icon_type'] === 'upload' && $b['icon_value'] ) {
                            $icon_html = '<img src="' . esc_url( $b['icon_value'] ) . '" width="32" height="32" style="border-radius:50%;object-fit:cover;">';
                        } else {
                            $icon_html = '<div style="width:36px;height:36px;border-radius:50%;background:' . esc_attr( $b['icon_color'] ) . ';display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;"><i class="' . esc_attr( $b['icon_value'] ) . '"></i></div>';
                        }
                        $condition = $unlock_type_labels[ $b['unlock_type'] ] ?? $b['unlock_type'];
                        if ( $b['unlock_threshold'] > 0 ) $condition .= ' >= ' . $b['unlock_threshold'];
                        if ( $b['unlock_param'] )         $condition .= ' (' . esc_html( $b['unlock_param'] ) . ')';
                    ?>
                    <tr id="badge-row-<?php echo intval( $b['id'] ); ?>">
                        <td><?php echo $icon_html; ?></td>
                        <td>
                            <strong><?php echo esc_html( $b['badge_name'] ); ?></strong><br>
                            <small style="color:#888;"><?php echo esc_html( $b['badge_slug'] ); ?></small>
                            <?php if ( $b['badge_desc'] ) : ?><br><em style="color:#666;font-size:12px;"><?php echo esc_html( $b['badge_desc'] ); ?></em><?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $condition ); ?></td>
                        <td><?php echo intval( $b['sort_order'] ); ?></td>
                        <td><?php echo $b['is_active'] ? '<span style="color:#27ae60;">Yes</span>' : '<span style="color:#e74c3c;">No</span>'; ?></td>
                        <td>
                            <button class="button button-small oha-edit-badge-btn"
                                    data-badge="<?php echo esc_attr( json_encode( $b ) ); ?>">Edit</button>
                            <button class="button button-small oha-delete-badge-btn" style="margin-top:4px;"
                                    data-id="<?php echo intval( $b['id'] ); ?>"
                                    data-nonce="<?php echo $nonce; ?>">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Add / Edit badge form -->
            <div class="oha-ph-admin-card" id="oha-badge-form-wrap">
                <h2 id="oha-badge-form-title">Add New Badge</h2>
                <form id="oha-badge-form">
                    <input type="hidden" id="badge-id" name="id" value="0">
                    <table class="form-table">
                        <tr><th><label>Badge Name *</label></th><td>
                            <input type="text" id="badge-name" name="badge_name" class="regular-text" required placeholder="e.g., Convention Champion">
                        </td></tr>
                        <tr><th><label>Slug</label></th><td>
                            <input type="text" id="badge-slug" name="badge_slug" class="regular-text" placeholder="Auto-generated from name">
                            <p class="description">Lowercase letters, numbers, hyphens. Unique identifier.</p>
                        </td></tr>
                        <tr><th><label>Description</label></th><td>
                            <input type="text" id="badge-desc" name="badge_desc" class="large-text" maxlength="300" placeholder="Short description shown to users">
                        </td></tr>
                        <tr><th><label>Icon Type</label></th><td>
                            <label><input type="radio" name="icon_type" value="fa" checked> Font Awesome Icon</label>&nbsp;&nbsp;
                            <label><input type="radio" name="icon_type" value="upload"> Custom Image Upload</label>
                        </td></tr>
                        <tr id="fa-icon-row"><th><label>FA Icon Class</label></th><td>
                            <div style="display:flex;gap:12px;align-items:center;">
                                <input type="text" id="badge-icon-value" name="icon_value" class="regular-text" placeholder="fa-solid fa-star" value="fa-solid fa-star">
                                <input type="color" id="badge-icon-color" name="icon_color" value="#8e44ad" style="width:44px;height:36px;padding:2px;border:1px solid #ccc;border-radius:4px;">
                                <div id="badge-icon-preview" style="width:40px;height:40px;border-radius:50%;background:#8e44ad;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;">
                                    <i class="fa-solid fa-star"></i>
                                </div>
                            </div>
                            <p class="description">Enter a Font Awesome 6 Free class string. <a href="https://fontawesome.com/icons?q=&o=r&s=solid&f=free" target="_blank">Browse icons</a></p>
                        </td></tr>
                        <tr id="upload-icon-row" style="display:none;"><th><label>Image Upload</label></th><td>
                            <input type="hidden" id="badge-upload-url" name="icon_value_upload">
                            <img id="badge-upload-preview" src="" style="display:none;width:64px;height:64px;border-radius:50%;object-fit:cover;margin-bottom:8px;">
                            <br><button type="button" id="badge-upload-btn" class="button">Choose Image</button>
                            <button type="button" id="badge-upload-clear" class="button" style="display:none;">Remove</button>
                        </td></tr>
                        <tr><th><label>Unlock Condition</label></th><td>
                            <select id="badge-unlock-type" name="unlock_type">
                                <option value="profile_created">Profile created (automatic on signup)</option>
                                <option value="stamp_count">Total stamps collected</option>
                                <option value="stamp_type_count">Stamps of a specific type</option>
                                <option value="event_stamp">Any stamp from a specific event</option>
                                <option value="manual">Manual admin award only</option>
                            </select>
                        </td></tr>
                        <tr id="threshold-row" style="display:none;"><th><label id="threshold-label">Threshold</label></th><td>
                            <input type="number" id="badge-threshold" name="unlock_threshold" value="1" min="0" class="small-text">
                            <span id="threshold-desc" class="description"></span>
                        </td></tr>
                        <tr id="param-row" style="display:none;"><th><label id="param-label">Param</label></th><td>
                            <input type="text" id="badge-param" name="unlock_param" class="regular-text" placeholder="">
                            <p class="description" id="param-desc"></p>
                        </td></tr>
                        <tr><th><label>Sort Order</label></th><td>
                            <input type="number" id="badge-sort" name="sort_order" value="0" min="0" class="small-text">
                        </td></tr>
                        <tr><th><label>Active</label></th><td>
                            <label><input type="checkbox" id="badge-active" name="is_active" value="1" checked> Show to users</label>
                        </td></tr>
                    </table>
                    <p>
                        <button type="submit" class="button button-primary" data-nonce="<?php echo $nonce; ?>">Save Badge</button>
                        <button type="button" id="oha-badge-form-cancel" class="button" style="margin-left:8px;display:none;">Cancel</button>
                    </p>
                    <div id="oha-badge-form-result" style="display:none;margin-top:8px;"></div>
                </form>
            </div>
        </div>

        <script>
        jQuery(function($) {
            var nonce = '<?php echo esc_js( $nonce ); ?>';

            // ── Icon type radio toggle ───────────────────────────────────
            $('[name="icon_type"]').on('change', function() {
                if ($(this).val() === 'fa') {
                    $('#fa-icon-row').show();
                    $('#upload-icon-row').hide();
                } else {
                    $('#fa-icon-row').hide();
                    $('#upload-icon-row').show();
                }
            });

            // ── FA live preview ──────────────────────────────────────────
            function updateFaPreview() {
                var cls   = $('#badge-icon-value').val().trim();
                var color = $('#badge-icon-color').val();
                $('#badge-icon-preview').css('background', color).html('<i class="' + cls + '"></i>');
            }
            $('#badge-icon-value').on('input', updateFaPreview);
            $('#badge-icon-color').on('input', updateFaPreview);

            // ── Unlock type conditionals ────────────────────────────────
            function updateUnlockFields() {
                var ut = $('#badge-unlock-type').val();
                var needsThreshold = (ut === 'stamp_count' || ut === 'stamp_type_count');
                var needsParam     = (ut === 'stamp_type_count' || ut === 'event_stamp');
                $('#threshold-row').toggle(needsThreshold);
                $('#param-row').toggle(needsParam);
                if (ut === 'stamp_count') {
                    $('#threshold-label').text('Minimum stamps');
                    $('#threshold-desc').text('Award when user has this many total stamps.');
                } else if (ut === 'stamp_type_count') {
                    $('#threshold-label').text('Minimum stamps');
                    $('#threshold-desc').text('Award when user has this many stamps of the specified type.');
                    $('#param-label').text('Stamp type');
                    $('#param-desc').text('e.g., trade, vendor, location, demo');
                } else if (ut === 'event_stamp') {
                    $('#param-label').text('Event slug');
                    $('#param-desc').text('Award when user has any stamp with this event_slug.');
                }
            }
            $('#badge-unlock-type').on('change', updateUnlockFields);
            updateUnlockFields();

            // ── WP media picker ──────────────────────────────────────────
            var mediaFrame;
            $('#badge-upload-btn').on('click', function(e) {
                e.preventDefault();
                if (mediaFrame) { mediaFrame.open(); return; }
                mediaFrame = wp.media({ title: 'Select Badge Image', button: { text: 'Use this image' }, multiple: false });
                mediaFrame.on('select', function() {
                    var attachment = mediaFrame.state().get('selection').first().toJSON();
                    $('#badge-upload-url').val(attachment.url);
                    $('#badge-upload-preview').attr('src', attachment.url).show();
                    $('#badge-upload-clear').show();
                });
                mediaFrame.open();
            });
            $('#badge-upload-clear').on('click', function() {
                $('#badge-upload-url').val('');
                $('#badge-upload-preview').hide();
                $(this).hide();
            });

            // ── Edit badge — pre-fill form ───────────────────────────────
            $(document).on('click', '.oha-edit-badge-btn', function() {
                var b = $(this).data('badge');
                $('#badge-id').val(b.id);
                $('#badge-name').val(b.badge_name);
                $('#badge-slug').val(b.badge_slug);
                $('#badge-desc').val(b.badge_desc);
                $('[name="icon_type"][value="' + b.icon_type + '"]').prop('checked', true).trigger('change');
                $('#badge-icon-value').val(b.icon_value);
                $('#badge-icon-color').val(b.icon_color);
                updateFaPreview();
                if (b.icon_type === 'upload') {
                    $('#badge-upload-url').val(b.icon_value);
                    $('#badge-upload-preview').attr('src', b.icon_value).show();
                    $('#badge-upload-clear').show();
                }
                $('#badge-unlock-type').val(b.unlock_type).trigger('change');
                $('#badge-threshold').val(b.unlock_threshold);
                $('#badge-param').val(b.unlock_param || '');
                $('#badge-sort').val(b.sort_order);
                $('#badge-active').prop('checked', b.is_active == 1);
                $('#oha-badge-form-title').text('Edit Badge');
                $('#oha-badge-form-cancel').show();
                $('html,body').animate({ scrollTop: $('#oha-badge-form-wrap').offset().top - 40 }, 300);
            });

            // ── Cancel edit ──────────────────────────────────────────────
            $('#oha-badge-form-cancel').on('click', function() {
                $('#oha-badge-form')[0].reset();
                $('#badge-id').val(0);
                $('#oha-badge-form-title').text('Add New Badge');
                $(this).hide();
                updateFaPreview();
                updateUnlockFields();
            });

            // ── Delete badge ─────────────────────────────────────────────
            $(document).on('click', '.oha-delete-badge-btn', function() {
                if (!confirm('Deactivate this badge? Users who already earned it will keep it.')) return;
                var id = $(this).data('id');
                $.post(ajaxurl, { action: 'oha_delete_badge_def', id: id, nonce: nonce }, function(r) {
                    if (r.success) { location.reload(); }
                    else { alert('Error: ' + r.data); }
                });
            });

            // ── Save badge form ──────────────────────────────────────────
            $('#oha-badge-form').on('submit', function(e) {
                e.preventDefault();
                var data = {};
                $(this).serializeArray().forEach(function(f) { data[f.name] = f.value; });
                data.action  = 'oha_save_badge_def';
                data.nonce   = nonce;
                data.is_active = $('#badge-active').is(':checked') ? 1 : 0;
                // If upload type, override icon_value from hidden upload field
                if ($('[name="icon_type"]:checked').val() === 'upload') {
                    data.icon_value = $('#badge-upload-url').val();
                }
                $.post(ajaxurl, data, function(r) {
                    if (r.success) {
                        $('#oha-badge-form-result').show()
                            .html('<div class="notice notice-success inline"><p>' + r.data.message + '</p></div>');
                        setTimeout(function() { location.reload(); }, 1200);
                    } else {
                        $('#oha-badge-form-result').show()
                            .html('<div class="notice notice-error inline"><p>' + r.data + '</p></div>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX handlers
    // ─────────────────────────────────────────────────────────────────────

    public function ajax_toggle_testing() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', OHA_PH_NONCE ) ) wp_send_json_error( 'Invalid request' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        $enable = filter_var( $_POST['enable'], FILTER_VALIDATE_BOOLEAN );
        OHA_Passport_QR_Manager::enable_testing_mode( $enable );
        wp_send_json_success( [ 'testing_mode' => $enable ] );
    }

    public function ajax_save_event() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', OHA_PH_NONCE ) ) wp_send_json_error( 'Invalid request' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        $slug       = sanitize_text_field( $_POST['slug'] ?? '' );
        $event_name = sanitize_text_field( $_POST['event_name'] ?? '' );
        $start_date = sanitize_text_field( $_POST['start_date'] ?? '' );
        $end_date   = sanitize_text_field( $_POST['end_date'] ?? '' );
        $location   = sanitize_text_field( $_POST['location'] ?? '' );

        if ( empty( $slug ) || empty( $event_name ) || empty( $start_date ) || empty( $end_date ) ) {
            wp_send_json_error( 'Slug, name, start date, and end date are required' );
        }
        if ( $end_date < $start_date ) {
            wp_send_json_error( 'End date must be on or after start date' );
        }

        $result = OHA_Passport_Events::get_instance()->register_event( $slug, $event_name, $start_date, $end_date, $location );
        if ( ! $result ) {
            wp_send_json_error( 'Failed to save event. Slug may already be in use.' );
        }
        wp_send_json_success( [ 'id' => $result ] );
    }

    public function ajax_delete_event() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', OHA_PH_NONCE ) ) wp_send_json_error( 'Invalid request' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permission denied' );

        $result = OHA_Passport_Events::get_instance()->delete_event( intval( $_POST['id'] ?? 0 ) );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }
        wp_send_json_success();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Vendors page
    // ─────────────────────────────────────────────────────────────────────
    public function page_vendors() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $events  = OHA_Passport_Events::get_instance()->get_all_events();
        $vendors = OHA_Passport_Vendors::get_instance()->get_all_vendors();
        $nonce   = wp_create_nonce( OHA_PH_NONCE );
        ?>
        <div class="wrap oha-ph-admin">
            <h1>🏪 Vendor Booth QR Codes</h1>
            <p>Create a record for each vendor booth, then print and post the QR code at their table.
               Attendees scan the code to check in and earn a stamp.</p>

            <!-- Add Vendor Form -->
            <div class="oha-ph-admin-card">
                <h2>Add New Vendor</h2>
                <form id="oha-add-vendor-form">
                    <table class="form-table">
                        <tr><th><label>Vendor Name *</label></th><td>
                            <input type="text" name="vendor_name" class="regular-text" required placeholder="e.g., Spooky Mask Co.">
                        </td></tr>
                        <tr><th><label>Description</label></th><td>
                            <textarea name="description" rows="3" class="large-text" placeholder="What they sell or do at the event…"></textarea>
                        </td></tr>
                        <tr><th><label>Website / Social URL</label></th><td>
                            <input type="url" name="website_url" class="regular-text" placeholder="https://…">
                        </td></tr>
                        <tr><th><label>Booth / Table Number</label></th><td>
                            <input type="text" name="booth_number" class="small-text" placeholder="e.g., B-12">
                        </td></tr>
                        <tr><th><label>Event *</label></th><td>
                            <select name="event_slug" required>
                                <option value="">Select event…</option>
                                <?php foreach ( $events as $ev ) : ?>
                                <option value="<?php echo esc_attr( $ev['slug'] ); ?>"><?php echo esc_html( $ev['event_name'] ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td></tr>
                    </table>
                    <p><button type="submit" class="button button-primary" data-nonce="<?php echo $nonce; ?>">➕ Add Vendor &amp; Generate QR</button></p>
                    <div id="oha-vendor-form-result" style="display:none;margin-top:12px;"></div>
                </form>
            </div>

            <!-- Vendors Table -->
            <div class="oha-ph-admin-card">
                <h2>Registered Vendors (<?php echo count( $vendors ); ?>)</h2>
                <?php if ( empty( $vendors ) ) : ?>
                <p>No vendors yet. Add your first one above.</p>
                <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr>
                        <th>Vendor</th><th>Booth</th><th>Event</th><th>QR Code</th><th>Actions</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ( $vendors as $v ) :
                        $qr_url = OHA_Passport_Vendors::get_instance()->get_vendor_qr_url( $v['id'] );
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html( $v['vendor_name'] ); ?></strong>
                            <?php if ( $v['website_url'] ) : ?>
                                <br><a href="<?php echo esc_url( $v['website_url'] ); ?>" target="_blank"><?php echo esc_html( $v['website_url'] ); ?></a>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $v['booth_number'] ?: '—' ); ?></td>
                        <td><code><?php echo esc_html( $v['event_slug'] ); ?></code></td>
                        <td>
                            <a href="<?php echo esc_url( $qr_url ); ?>" target="_blank" title="Open QR image">
                                <img src="<?php echo esc_url( $qr_url . '&size=60x60' ); ?>" width="60" height="60" style="display:block;border:1px solid #ddd;border-radius:4px;">
                            </a>
                            <small>QR data: <code>VND:<?php echo intval( $v['id'] ); ?></code></small>
                        </td>
                        <td>
                            <a href="<?php echo esc_url( $qr_url ); ?>" download="vendor-<?php echo intval( $v['id'] ); ?>-qr.png" target="_blank" class="button button-small">⬇ Download QR</a>
                            <button class="button button-small oha-delete-vendor" style="margin-top:4px;"
                                    data-id="<?php echo intval( $v['id'] ); ?>"
                                    data-nonce="<?php echo $nonce; ?>">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <script>
            jQuery(function($) {
                $('#oha-add-vendor-form').on('submit', function(e) {
                    e.preventDefault();
                    var data = $(this).serializeArray().reduce(function(o, f){ o[f.name] = f.value; return o; }, {});
                    data.action = 'oha_admin_save_vendor';
                    data.nonce  = $(this).find('[data-nonce]').data('nonce');
                    $.post(ajaxurl, data, function(r) {
                        if (r.success) {
                            $('#oha-vendor-form-result').show().html(
                                '<div class="notice notice-success"><p>✅ Vendor added! ' +
                                '<a href="' + r.data.qr_url + '" target="_blank">View QR code</a></p></div>'
                            );
                            setTimeout(function(){ location.reload(); }, 1500);
                        } else {
                            $('#oha-vendor-form-result').show().html('<div class="notice notice-error"><p>' + r.data + '</p></div>');
                        }
                    });
                });
                $(document).on('click', '.oha-delete-vendor', function() {
                    if (!confirm('Deactivate this vendor? Check-in history is preserved.')) return;
                    $.post(ajaxurl, { action: 'oha_admin_delete_vendor', id: $(this).data('id'), nonce: $(this).data('nonce') }, function(r) {
                        if (r.success) { location.reload(); }
                        else { alert('Error: ' + r.data); }
                    });
                });
            });
            </script>
        </div>
        <?php
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scavenger Hunt page
    // ─────────────────────────────────────────────────────────────────────
    public function page_scavenger() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $events    = OHA_Passport_Events::get_instance()->get_all_events();
        $locations = OHA_Passport_Scavenger::get_instance()->get_locations_for_event(
            // Default to active event or first event
            ( function() use ( $events ) {
                $active = OHA_Passport_Events::get_instance()->get_active_event();
                return $active ? $active['slug'] : ( $events ? $events[0]['slug'] : '' );
            } )()
        );
        $active_slug = OHA_Passport_Events::get_instance()->get_active_event()
            ? OHA_Passport_Events::get_instance()->get_active_event()['slug']
            : ( $events ? $events[0]['slug'] : '' );
        $nonce = wp_create_nonce( OHA_PH_NONCE );
        ?>
        <div class="wrap oha-ph-admin">
            <h1>🗺️ Scavenger Hunt Locations</h1>
            <p>Create hidden location QRs and print them to hide around the venue.
               Attendees scan them to earn passport stamps. When all are found, they earn a completion bonus stamp.</p>

            <!-- Filter by event -->
            <div class="oha-ph-admin-card">
                <label><strong>View locations for event:</strong>
                    <select id="oha-location-event-filter">
                        <?php foreach ( $events as $ev ) : ?>
                        <option value="<?php echo esc_attr( $ev['slug'] ); ?>"
                            <?php selected( $ev['slug'], $active_slug ); ?>>
                            <?php echo esc_html( $ev['event_name'] ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button id="oha-filter-locations" class="button" style="margin-left:8px;">Filter</button>
            </div>

            <!-- Add Location Form -->
            <div class="oha-ph-admin-card">
                <h2>Add New Location</h2>
                <form id="oha-add-location-form">
                    <table class="form-table">
                        <tr><th><label>Location Name *</label></th><td>
                            <input type="text" name="location_name" class="regular-text" required placeholder="e.g., The Haunted Library">
                            <p class="description">Shown to attendees AFTER they find it.</p>
                        </td></tr>
                        <tr><th><label>Hint (shown before finding)</label></th><td>
                            <input type="text" name="location_hint" class="large-text" placeholder="e.g., Near the bookshelves on the second floor…">
                            <p class="description">A clue shown on the progress screen before the location is found.</p>
                        </td></tr>
                        <tr><th><label>Description</label></th><td>
                            <textarea name="location_description" rows="2" class="large-text" placeholder="Flavour text shown after scanning…"></textarea>
                        </td></tr>
                        <tr><th><label>Event *</label></th><td>
                            <select name="event_slug" required>
                                <option value="">Select event…</option>
                                <?php foreach ( $events as $ev ) : ?>
                                <option value="<?php echo esc_attr( $ev['slug'] ); ?>"
                                    <?php selected( $ev['slug'], $active_slug ); ?>>
                                    <?php echo esc_html( $ev['event_name'] ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td></tr>
                        <tr><th><label>Display Order</label></th><td>
                            <input type="number" name="sort_order" value="0" class="small-text" min="0">
                            <p class="description">Lower numbers appear first in the progress list.</p>
                        </td></tr>
                    </table>
                    <p><button type="submit" class="button button-primary" data-nonce="<?php echo $nonce; ?>">➕ Add Location &amp; Generate QR</button></p>
                    <div id="oha-location-form-result" style="display:none;margin-top:12px;"></div>
                </form>
            </div>

            <!-- Locations Table -->
            <div class="oha-ph-admin-card">
                <h2>Hunt Locations (<?php echo count( $locations ); ?>)</h2>
                <?php if ( empty( $locations ) ) : ?>
                <p>No locations yet. Add the first one above.</p>
                <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr>
                        <th style="width:40px;">#</th>
                        <th>Location Name</th><th>Hint</th><th>QR Code</th><th>Actions</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ( $locations as $i => $loc ) :
                        $qr_url = OHA_Passport_Scavenger::get_instance()->get_location_qr_url( $loc['qr_hash'] );
                    ?>
                    <tr>
                        <td><?php echo intval( $loc['sort_order'] ); ?></td>
                        <td><strong><?php echo esc_html( $loc['location_name'] ); ?></strong></td>
                        <td><em><?php echo esc_html( $loc['location_hint'] ?: '—' ); ?></em></td>
                        <td>
                            <a href="<?php echo esc_url( $qr_url ); ?>" target="_blank" title="Open large QR">
                                <img src="<?php echo esc_url( $qr_url . '&size=60x60' ); ?>" width="60" height="60" style="display:block;border:1px solid #ddd;border-radius:4px;">
                            </a>
                            <small>QR data: <code>LOC:<?php echo esc_html( $loc['qr_hash'] ); ?></code></small>
                        </td>
                        <td>
                            <a href="<?php echo esc_url( $qr_url ); ?>" target="_blank" class="button button-small">⬇ Print QR</a>
                            <button class="button button-small oha-delete-location" style="margin-top:4px;"
                                    data-id="<?php echo intval( $loc['id'] ); ?>"
                                    data-nonce="<?php echo $nonce; ?>">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Completion leaderboard -->
            <?php
            $hunt_lb = OHA_Passport_Scavenger::get_instance()->get_hunt_leaderboard( $active_slug, 10 );
            if ( ! empty( $hunt_lb ) ) : ?>
            <div class="oha-ph-admin-card">
                <h2>🏆 Hunt Leaderboard — <?php echo esc_html( $active_slug ); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>Character</th><th>Haunt</th><th>Locations Found</th></tr></thead>
                    <tbody>
                    <?php foreach ( $hunt_lb as $row ) : ?>
                    <tr>
                        <td><?php echo esc_html( $row->character_name ); ?></td>
                        <td><?php echo esc_html( $row->haunt_name ?: '—' ); ?></td>
                        <td><?php echo intval( $row->locations_found ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <script>
            jQuery(function($) {
                $('#oha-filter-locations').on('click', function() {
                    var slug = $('#oha-location-event-filter').val();
                    window.location.href = window.location.pathname + '?page=oha-passport-scavenger&event=' + encodeURIComponent(slug);
                });
                $('#oha-add-location-form').on('submit', function(e) {
                    e.preventDefault();
                    var data = $(this).serializeArray().reduce(function(o, f){ o[f.name] = f.value; return o; }, {});
                    data.action = 'oha_admin_save_location';
                    data.nonce  = $(this).find('[data-nonce]').data('nonce');
                    $.post(ajaxurl, data, function(r) {
                        if (r.success) {
                            $('#oha-location-form-result').show().html(
                                '<div class="notice notice-success"><p>✅ Location added! ' +
                                '<a href="' + r.data.qr_url + '" target="_blank">View/Print QR</a></p></div>'
                            );
                            setTimeout(function(){ location.reload(); }, 1500);
                        } else {
                            $('#oha-location-form-result').show().html('<div class="notice notice-error"><p>' + r.data + '</p></div>');
                        }
                    });
                });
                $(document).on('click', '.oha-delete-location', function() {
                    if (!confirm('Delete this location? Stamps already earned by attendees are preserved.')) return;
                    $.post(ajaxurl, { action: 'oha_admin_delete_location', id: $(this).data('id'), nonce: $(this).data('nonce') }, function(r) {
                        if (r.success) { location.reload(); }
                        else { alert('Error: ' + r.data); }
                    });
                });
            });
            </script>
        </div>
        <?php
    }
}
