<?php
/**
 * OHA Dev Log + Lightweight Activity Timeline
 *
 * Purpose:
 * - Let board members see how much development work is going into the site
 *   (estimated hours + readable changelog-style entries)
 * - Provide a lightweight activity feed without requiring extra plugins
 * - Provide an admin CSV importer to backfill historical dev work
 *
 * Notes:
 * - Dev Log entries are stored as CPT: oha_dev_log (non-public)
 * - Hours / category / tags are stored as post meta
 * - Activity feed is stored in a ring buffer option: oha_devlog_activity
 * - This is intentionally lightweight; if you later install a full audit log
 *   plugin, you can keep this and still gain deeper coverage.
 */

if (!defined('ABSPATH')) exit;

class OHA_DevLog {

    const CPT = 'oha_dev_log';
    const OPT_ACTIVITY = 'oha_devlog_activity';
    const ACTIVITY_MAX = 120;

    public static function init() {
        // CPT + meta UI
        add_action('init', [__CLASS__, 'register_cpt']);
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
        add_action('save_post_' . self::CPT, [__CLASS__, 'save_meta']);

        // Admin: CSV import under Tools
        add_action('admin_menu', [__CLASS__, 'register_tools_page']);

        // Admin: manual add helper (rendered inside Settings → OHA Board Hub)
        add_action('admin_post_oha_devlog_manual_add', [__CLASS__, 'handle_manual_add']);

        // Lightweight activity tracking (going forward)
        add_action('transition_post_status', [__CLASS__, 'track_content_changes'], 10, 3);
        add_action('activated_plugin', [__CLASS__, 'track_plugin_activated']);
        add_action('deactivated_plugin', [__CLASS__, 'track_plugin_deactivated']);
        add_action('switch_theme', [__CLASS__, 'track_theme_switched'], 10, 2);
    }

    public static function register_cpt() {
        $labels = [
            'name'               => 'Dev Log',
            'singular_name'      => 'Dev Log Entry',
            'add_new'            => 'Add Entry',
            'add_new_item'       => 'Add Dev Log Entry',
            'edit_item'          => 'Edit Dev Log Entry',
            'new_item'           => 'New Dev Log Entry',
            'view_item'          => 'View Dev Log Entry',
            'search_items'       => 'Search Dev Log',
            'not_found'          => 'No entries found',
            'not_found_in_trash' => 'No entries found in Trash',
            'menu_name'          => 'Dev Log',
        ];

        register_post_type(self::CPT, [
            'labels'        => $labels,
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => false, // Hidden — OHA Dev Log Aggregator plugin manages the admin UI
            'menu_position' => 28,
            'menu_icon'     => 'dashicons-clipboard',
            'supports'      => ['title', 'editor', 'author'],
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ]);
    }

    public static function add_meta_box() {
        add_meta_box(
            'oha_devlog_meta',
            'Dev Log Details',
            [__CLASS__, 'render_meta_box'],
            self::CPT,
            'side',
            'default'
        );
    }

    public static function render_meta_box($post) {
        $hours    = get_post_meta($post->ID, '_oha_hours', true);
        $category = get_post_meta($post->ID, '_oha_category', true);
        $tags     = get_post_meta($post->ID, '_oha_tags', true);
        wp_nonce_field('oha_devlog_meta_save', 'oha_devlog_meta_nonce');

        $opts = ['Feature', 'Bug Fix', 'Maintenance', 'Security', 'Content', 'Design', 'Infrastructure', 'Other'];
        ?>
        <p>
            <label for="oha_hours"><strong>Estimated Hours</strong></label><br/>
            <input type="number" step="0.25" min="0" id="oha_hours" name="oha_hours"
                   value="<?php echo esc_attr($hours); ?>" style="width: 140px;" />
            <span style="opacity:.75;">(e.g. 1, 1.5, 2.25)</span>
        </p>
        <p>
            <label for="oha_category"><strong>Category</strong></label><br/>
            <select id="oha_category" name="oha_category" style="width: 220px;">
                <?php foreach ($opts as $opt) : ?>
                    <option value="<?php echo esc_attr($opt); ?>" <?php selected($category, $opt); ?>><?php echo esc_html($opt); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="oha_tags"><strong>Tags</strong> (comma-separated)</label><br/>
            <input type="text" id="oha_tags" name="oha_tags" value="<?php echo esc_attr($tags); ?>" style="width: 100%;" />
            <span style="opacity:.75;">Examples: Stripe, Vendors, PWA, Board Hub</span>
        </p>
        <?php
    }

    public static function save_meta($post_id) {
        if (!isset($_POST['oha_devlog_meta_nonce']) || !wp_verify_nonce($_POST['oha_devlog_meta_nonce'], 'oha_devlog_meta_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $hours = isset($_POST['oha_hours']) ? floatval($_POST['oha_hours']) : 0;
        $cat   = isset($_POST['oha_category']) ? sanitize_text_field($_POST['oha_category']) : 'Other';
        $tags  = isset($_POST['oha_tags']) ? sanitize_text_field($_POST['oha_tags']) : '';

        update_post_meta($post_id, '_oha_hours', $hours);
        update_post_meta($post_id, '_oha_category', $cat ?: 'Other');
        update_post_meta($post_id, '_oha_tags', $tags);
    }

    /**
     * Activity ring buffer
     */
    public static function range_label($range) {
        switch ($range) {
            case '30d': return 'Last 30 days';
            case '90d': return 'Last 90 days';
            case 'ytd': return 'Year to date';
            case 'all': return 'All time';
            default: return 'Last 30 days';
        }
    }

    public static function activity_add($type, $message) {
        $log = get_option(self::OPT_ACTIVITY, []);
        if (!is_array($log)) $log = [];

        $user = is_user_logged_in() ? wp_get_current_user() : null;
        array_unshift($log, [
            'ts'      => current_time('timestamp'),
            'type'    => (string)$type,
            'message' => (string)$message,
            'user'    => $user ? ($user->display_name ?: $user->user_login) : 'System',
        ]);
        if (count($log) > self::ACTIVITY_MAX) {
            $log = array_slice($log, 0, self::ACTIVITY_MAX);
        }
        update_option(self::OPT_ACTIVITY, $log, false);
    }

    public static function track_content_changes($new, $old, $post) {
        if (!$post || empty($post->ID) || wp_is_post_revision($post->ID)) return;
        if (!in_array($post->post_type, ['post', 'page'], true)) return;

        $title = $post->post_title ?: '(no title)';
        if ($new === 'publish' && $old !== 'publish') {
            self::activity_add('content', "Published {$post->post_type}: {$title}");
        } elseif ($new === 'publish' && $old === 'publish') {
            self::activity_add('content', "Updated {$post->post_type}: {$title}");
        }
    }

    public static function track_plugin_activated($plugin) {
        self::activity_add('plugin', "Activated plugin: {$plugin}");
    }

    public static function track_plugin_deactivated($plugin) {
        self::activity_add('plugin', "Deactivated plugin: {$plugin}");
    }

    public static function track_theme_switched($new_name, $new_theme) {
        self::activity_add('theme', "Switched theme to: {$new_name}");
    }

    /**
     * Overview widget renderer (used in Board Hub Overview tab)
     */
    public static function render_overview_widget() {
        // Hours estimate per entry type (used for devlog.json entries that have no logged hours)
        $hours_by_type = [
            'feature' => 3.0,
            'change'  => 1.5,
            'fix'     => 1.0,
            'note'    => 0.25,
        ];

        // Type display config: label, color
        $type_config = [
            'feature' => ['label' => 'Features', 'color' => '#0073aa'],
            'fix'     => ['label' => 'Fixes',    'color' => '#c4161c'],
            'change'  => ['label' => 'Changes',  'color' => '#b46800'],
            'note'    => ['label' => 'Notes',    'color' => '#555555'],
        ];

        $since_ts   = strtotime('-30 days', current_time('timestamp'));
        $since_date = date('Y-m-d', $since_ts);

        $type_counts   = ['feature' => 0, 'fix' => 0, 'change' => 0, 'note' => 0];
        $total_entries = 0;
        $est_hours     = 0.0;
        $actual_hours  = 0.0;
        $recent_entries = [];

        if (class_exists('OHA_Dev_Log_Data')) {
            $all  = OHA_Dev_Log_Data::get_all_logs();
            $logs = array_filter($all, function($e) use ($since_date) {
                return isset($e['date']) && $e['date'] >= $since_date;
            });

            foreach ($logs as $entry) {
                $type = isset($entry['type']) ? $entry['type'] : 'note';
                if (!isset($type_counts[$type])) $type_counts['note']++;
                else $type_counts[$type]++;

                if (($entry['_source'] ?? '') === 'cpt' && !empty($entry['_post_id'])) {
                    $h = floatval(get_post_meta($entry['_post_id'], '_oha_hours', true));
                    $actual_hours += $h;
                    $est_hours    += $h > 0 ? $h : ($hours_by_type[$type] ?? 1.0);
                } else {
                    $est_hours += $hours_by_type[$type] ?? 1.0;
                }
            }

            $total_entries  = count($logs);
            $recent_entries = array_slice(array_values($logs), 0, 5);

        } else {
            // Fallback: read oha_dev_log CPT directly
            $q = new WP_Query([
                'post_type'      => self::CPT,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'date_query'     => [['after' => $since_date, 'inclusive' => true]],
            ]);
            foreach ($q->posts as $post) {
                $h   = floatval(get_post_meta($post->ID, '_oha_hours', true));
                $cat = get_post_meta($post->ID, '_oha_category', true) ?: 'Other';
                $type = 'note';
                foreach (['feature' => 'feature', 'new' => 'feature', 'fix' => 'fix', 'bug' => 'fix', 'change' => 'change', 'update' => 'change'] as $k => $v) {
                    if (stripos($cat, $k) !== false) { $type = $v; break; }
                }
                $type_counts[$type]++;
                $actual_hours += $h;
                $est_hours    += $h > 0 ? $h : ($hours_by_type[$type] ?? 1.0);
                $total_entries++;
                if (count($recent_entries) < 5) {
                    $recent_entries[] = ['date' => get_the_date('Y-m-d', $post), 'type' => $type, 'summary' => $post->post_title, '_plugin' => 'Board Hub'];
                }
            }
        }

        $has_actual = $actual_hours > 0;

        ob_start(); ?>
        <style>
            .oha-devlog-ow{display:grid;grid-template-columns:1fr;gap:16px}
            @media(min-width:900px){.oha-devlog-ow{grid-template-columns:1fr 1fr}}
            .oha-dl-kpis{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px}
            .oha-dl-kpi{border:1px solid #333;border-radius:8px;padding:12px 16px;min-width:110px;flex:1;text-align:center}
            .oha-dl-kpi .n{font-size:26px;font-weight:700;line-height:1;color:#a0232a;margin-bottom:5px}
            .oha-dl-kpi .l{font-size:10px;color:#aaa;text-transform:uppercase;letter-spacing:0.5px}
            .oha-dl-types{display:flex;flex-wrap:wrap;gap:8px;margin-top:4px}
            .oha-dl-type-pill{display:flex;align-items:center;gap:6px;border:1px solid #333;border-radius:20px;padding:5px 12px;font-size:12px}
            .oha-dl-type-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
            .oha-dl-type-count{font-weight:700;color:#eee}
            .oha-dl-type-label{color:#aaa}
            .oha-dl-recent{margin:0;padding:0;list-style:none}
            .oha-dl-recent li{padding:8px 0;border-bottom:1px solid #222;font-size:12px;display:flex;align-items:flex-start;gap:8px}
            .oha-dl-recent li:last-child{border-bottom:none}
            .oha-dl-badge-sm{display:inline-block;color:#fff;padding:1px 7px;border-radius:3px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.4px;white-space:nowrap;flex-shrink:0;margin-top:1px}
            .oha-dl-hours-note{font-size:11px;color:#888;margin-top:10px;font-style:italic}
        </style>

        <div class="oha-card">
            <h3 style="margin:0 0 16px 0;">
                Development Activity
                <span style="font-size:12px;font-weight:400;color:#888;margin-left:8px;">Last 30 days</span>
                <?php if (class_exists('OHA_Dev_Log_Data')): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=oha-dev-log')); ?>" style="font-size:11px;font-weight:400;color:#888;margin-left:10px;text-decoration:none;">Full log &#8594;</a>
                <?php endif; ?>
            </h3>

            <div class="oha-devlog-ow">

                <div>
                    <div class="oha-dl-kpis">
                        <div class="oha-dl-kpi">
                            <div class="n"><?php echo $total_entries; ?></div>
                            <div class="l">Log Entries</div>
                        </div>
                        <div class="oha-dl-kpi">
                            <div class="n"><?php echo number_format($est_hours, 1); ?></div>
                            <div class="l"><?php echo $has_actual ? 'Hours (est.)' : 'Est. Hours'; ?></div>
                        </div>
                    </div>

                    <div class="oha-dl-types">
                        <?php foreach ($type_config as $type => $cfg):
                            if ($type_counts[$type] < 1) continue; ?>
                            <div class="oha-dl-type-pill">
                                <span class="oha-dl-type-dot" style="background:<?php echo esc_attr($cfg['color']); ?>;"></span>
                                <span class="oha-dl-type-count"><?php echo $type_counts[$type]; ?></span>
                                <span class="oha-dl-type-label"><?php echo esc_html($cfg['label']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!$has_actual): ?>
                        <p class="oha-dl-hours-note">Hours are estimated (feature ~3h, change ~1.5h, fix ~1h). Log actual hours via Admin &rarr; Dev Log.</p>
                    <?php endif; ?>
                </div>

                <div>
                    <div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">Recent Entries</div>
                    <?php if (empty($recent_entries)): ?>
                        <p style="color:#666;font-style:italic;font-size:13px;">No entries in the last 30 days.</p>
                    <?php else: ?>
                        <ul class="oha-dl-recent">
                            <?php foreach ($recent_entries as $entry):
                                $type   = $entry['type'] ?? 'note';
                                $color  = $type_config[$type]['color'] ?? '#555';
                                $plugin = $entry['_plugin'] ?? '';
                                $date   = $entry['date'] ?? '';
                            ?>
                            <li>
                                <span class="oha-dl-badge-sm" style="background:<?php echo esc_attr($color); ?>"><?php echo esc_html(ucfirst($type)); ?></span>
                                <div>
                                    <div style="color:#ddd;line-height:1.3;"><?php echo esc_html($entry['summary'] ?? '&mdash;'); ?></div>
                                    <div style="color:#777;font-size:11px;margin-top:2px;"><?php echo esc_html($plugin); ?><?php if ($date): ?> &middot; <?php echo esc_html($date); ?><?php endif; ?></div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Development Activity widget with announcement text (no recent activity)
     * Used on Overview tab - shows dev stats + announcement text only
     */
    public static function render_development_widget($range = '30d') {
        $range = is_string($range) ? sanitize_key($range) : '30d';
        $allowed = ['30d','90d','ytd','all'];
        if (!in_array($range, $allowed, true)) $range = '30d';

        $since_ts = null;
        $since_date = null;

        if ($range === '30d') {
            $since_ts = strtotime('-30 days', current_time('timestamp'));
        } elseif ($range === '90d') {
            $since_ts = strtotime('-90 days', current_time('timestamp'));
        } elseif ($range === 'ytd') {
            $year = (int) date_i18n('Y', current_time('timestamp'));
            $since_ts = strtotime($year . '-01-01 00:00:00');
        } elseif ($range === 'all') {
            $since_ts = null;
        }

        if ($since_ts) {
            $since_date = date('Y-m-d H:i:s', $since_ts);
        }
        
        $args = [
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ];

        if (!empty($since_date)) {
            $args['date_query'] = [[
                'after'     => $since_date,
                'inclusive' => true,
            ]];
        }

        $q = new WP_Query($args);

        $total_hours = 0.0;
        $cat_hours = [];
        foreach ($q->posts as $id) {
            $h = floatval(get_post_meta($id, '_oha_hours', true));
            $c = get_post_meta($id, '_oha_category', true) ?: 'Other';
            $total_hours += $h;
            if (!isset($cat_hours[$c])) $cat_hours[$c] = 0.0;
            $cat_hours[$c] += $h;
        }
        arsort($cat_hours);

        // Get announcement text from Board Hub settings
        $board_settings = get_option('oha_board_hub_settings', []);
        $announcement = isset($board_settings['announcement_text']) ? trim($board_settings['announcement_text']) : '';

        ob_start();
        ?>
        <style>
            /* Enhanced Dev Log styling for better integration with overview cards */
            .oha-devlog-grid{display:grid;grid-template-columns:1fr;gap:18px}
            .oha-devlog-kpis{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:14px}
            .oha-devlog-kpi{border:1px solid #ddd;border-radius:8px;padding:14px 16px;min-width:140px;background:#fff;color:#111;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,0.05)}
            .oha-devlog-kpi .n{font-size:24px;font-weight:700;line-height:1;color:#a0232a;margin-bottom:6px}
            .oha-devlog-kpi .l{font-size:11px;color:#666;text-transform:uppercase;letter-spacing:0.5px}
            .oha-devlog-badges{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
            .oha-devlog-badge{border:1px solid #ddd;border-radius:16px;padding:4px 10px;font-size:11px;background:#f8f9fa;color:#555;white-space:nowrap}
            .oha-announcement{background:#f8f9fa;border-left:4px solid #0073aa;padding:12px 16px;margin-top:16px;border-radius:4px}
            .oha-announcement h4{margin:0 0 8px 0;color:#0073aa;font-size:14px}
            .oha-announcement p{margin:0;color:#333;font-size:13px;line-height:1.4}
        </style>

        <div class="oha-card">
            <h3 style="margin:0 0 16px 0;">Development Activity (Last 30 Days)</h3>

            <div class="oha-devlog-grid">
                <div>
                    <div class="oha-devlog-kpis">
                        <div class="oha-devlog-kpi">
                            <div class="n"><?php echo esc_html(number_format($total_hours, 1)); ?></div>
                            <div class="l">Hours Logged</div>
                        </div>
                        <div class="oha-devlog-kpi">
                            <div class="n"><?php echo esc_html(count($q->posts)); ?></div>
                            <div class="l">Entries</div>
                        </div>
                    </div>

                    <?php if (!empty($cat_hours)) : ?>
                        <div class="oha-devlog-badges">
                            <?php 
                            $shown_cats = 0;
                            foreach ($cat_hours as $cat => $hrs) : 
                                if ($shown_cats >= 4) break;
                                $shown_cats++;
                            ?>
                                <span class="oha-devlog-badge"><?php echo esc_html($cat); ?>: <?php echo esc_html(number_format($hrs, 1)); ?>h</span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($announcement)) : ?>
                        <div class="oha-announcement">
                            <h4>📢 Announcement</h4>
                            <p><?php echo esc_html($announcement); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php 
                // Simple announcement display from Board Hub settings
                $board_settings = get_option('oha_board_hub_settings', []);
                $announcement = isset($board_settings['announcement_text']) ? trim($board_settings['announcement_text']) : '';
                if (!empty($announcement)): ?>
                    <div style="background:#f8f9fa;border-left:4px solid #0073aa;padding:12px 16px;margin-top:16px;border-radius:4px;">
                        <div style="margin:0;color:#333;font-size:13px;line-height:1.4;">
                            <strong>📢 Announcement:</strong> <?php echo esc_html($announcement); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Settings page tools (manual add)
     */
    public static function render_settings_tools() {
        if (!current_user_can('manage_options')) {
            echo '<p>You do not have permission to manage Dev Log tools.</p>';
            return;
        }

        $redirect = admin_url('options-general.php?page=' . urlencode(OHA_BHS_MENU));
        ?>
        <div style="border:1px solid #ddd;background:#fff;border-radius:10px;padding:12px;max-width:920px;">
            <p style="margin-top:0;"><strong>Quick Add Dev Log Entry</strong> (for backfilling or quick updates)</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('oha_devlog_manual_add', 'oha_devlog_manual_nonce'); ?>
                <input type="hidden" name="action" value="oha_devlog_manual_add" />
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect); ?>" />

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="oha_devlog_title">Title</label></th>
                        <td><input type="text" id="oha_devlog_title" name="title" class="regular-text" required placeholder="e.g., Vendor PDF signature capture" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="oha_devlog_hours">Estimated Hours</label></th>
                        <td><input type="number" step="0.25" min="0" id="oha_devlog_hours" name="hours" class="small-text" placeholder="2.5" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="oha_devlog_category">Category</label></th>
                        <td>
                            <select id="oha_devlog_category" name="category">
                                <?php foreach (['Feature','Bug Fix','Maintenance','Security','Content','Design','Infrastructure','Other'] as $opt) : ?>
                                    <option value="<?php echo esc_attr($opt); ?>"><?php echo esc_html($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="oha_devlog_tags">Tags</label></th>
                        <td><input type="text" id="oha_devlog_tags" name="tags" class="regular-text" placeholder="Stripe, Vendors, PWA" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="oha_devlog_date">Date (optional)</label></th>
                        <td>
                            <input type="text" id="oha_devlog_date" name="date" class="regular-text" placeholder="YYYY-MM-DD or YYYY-MM-DD HH:MM" />
                            <p class="description">Leave blank to use current date/time.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="oha_devlog_notes">Notes</label></th>
                        <td><textarea id="oha_devlog_notes" name="notes" class="large-text" rows="3" placeholder="Readable notes for the board."></textarea></td>
                    </tr>
                </table>

                <?php submit_button('Add Dev Log Entry'); ?>
            </form>

            <hr />
            <p style="margin-bottom:0;">CSV import is still available under <strong>Tools → Dev Log CSV Import</strong> if you prefer bulk backfill.</p>
        </div>
        <?php
    }

    public static function handle_manual_add() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        if (empty($_POST['oha_devlog_manual_nonce']) || !wp_verify_nonce($_POST['oha_devlog_manual_nonce'], 'oha_devlog_manual_add')) {
            wp_die('Invalid nonce');
        }

        $title = sanitize_text_field($_POST['title'] ?? '');
        if ($title === '') {
            wp_safe_redirect($_POST['redirect_to'] ?? admin_url());
            exit;
        }

        $notes = wp_kses_post($_POST['notes'] ?? '');
        $hours = floatval($_POST['hours'] ?? 0);
        $cat   = sanitize_text_field($_POST['category'] ?? 'Other');
        $tags  = sanitize_text_field($_POST['tags'] ?? '');

        $postarr = [
            'post_type'    => self::CPT,
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_content' => $notes,
        ];

        $date_raw = trim((string)($_POST['date'] ?? ''));
        if ($date_raw !== '') {
            $ts = strtotime($date_raw);
            if ($ts !== false) {
                $post_date = date('Y-m-d H:i:s', $ts);
                $postarr['post_date'] = $post_date;
                $postarr['post_date_gmt'] = get_gmt_from_date($post_date);
            }
        }

        $post_id = wp_insert_post($postarr, true);
        if (!is_wp_error($post_id) && $post_id) {
            update_post_meta($post_id, '_oha_hours', $hours);
            update_post_meta($post_id, '_oha_category', $cat ?: 'Other');
            update_post_meta($post_id, '_oha_tags', $tags);
            self::activity_add('devlog', "Added Dev Log entry: {$title} (~{$hours} hrs)");
        }

        wp_safe_redirect($_POST['redirect_to'] ?? admin_url());
        exit;
    }

    /**
     * Admin Tools page
     */
    public static function register_tools_page() {
        add_management_page(
            'Dev Log CSV Import',
            'Dev Log CSV Import',
            'manage_options',
            'oha-devlog-csv-import',
            [__CLASS__, 'render_tools_page']
        );
    }

    public static function render_tools_page() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to access this page.');
        }

        $result_html = '';

        if (!empty($_POST['oha_devlog_csv_import']) && check_admin_referer('oha_devlog_csv_import_action', 'oha_devlog_csv_import_nonce')) {
            if (!isset($_FILES['oha_devlog_csv']) || empty($_FILES['oha_devlog_csv']['tmp_name'])) {
                $result_html = '<div class="notice notice-error"><p>No file uploaded.</p></div>';
            } else {
                $file = $_FILES['oha_devlog_csv']['tmp_name'];
                $dry_run = !empty($_POST['oha_devlog_csv_dry_run']);
                $result = self::process_csv($file, $dry_run);

                if (is_wp_error($result)) {
                    $result_html = '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                } else {
                    $result_html = '<div class="notice notice-success"><p>' .
                        esc_html(($dry_run ? 'Dry run: ' : '') . "Imported {$result['imported']} entries. Skipped {$result['skipped']} rows.") .
                        '</p></div>';

                    if (!empty($result['errors'])) {
                        $result_html .= '<div class="notice notice-warning"><p><strong>Row issues:</strong></p><ul style="margin-left:20px;">';
                        foreach ($result['errors'] as $e) {
                            $result_html .= '<li>' . esc_html($e) . '</li>';
                        }
                        $result_html .= '</ul></div>';
                    }
                }
            }
        }

        ?>
        <div class="wrap">
            <h1>Dev Log CSV Import</h1>

            <p>Upload a CSV to create Dev Log entries.</p>

            <h2>Required CSV Format</h2>
            <p><strong>Header row required</strong> with these columns:</p>
            <code>title,notes,hours,category,tags,date</code>

            <p><strong>Notes:</strong></p>
            <ul style="list-style:disc;margin-left:20px;">
                <li><code>title</code> is required.</li>
                <li><code>hours</code> is optional (defaults to 0).</li>
                <li><code>category</code> is optional (defaults to "Other").</li>
                <li><code>tags</code> is optional (comma-separated).</li>
                <li><code>date</code> is optional. Accepts: <code>YYYY-MM-DD</code> or <code>YYYY-MM-DD HH:MM</code> or <code>YYYY-MM-DD HH:MM:SS</code>.</li>
            </ul>

            <?php echo $result_html; ?>

            <form method="post" enctype="multipart/form-data" style="margin-top:12px;">
                <?php wp_nonce_field('oha_devlog_csv_import_action', 'oha_devlog_csv_import_nonce'); ?>
                <input type="file" name="oha_devlog_csv" accept=".csv,text/csv" required />
                <p style="margin-top:10px;">
                    <label>
                        <input type="checkbox" name="oha_devlog_csv_dry_run" value="1" />
                        Dry run (validate only, do not create posts)
                    </label>
                </p>
                <p>
                    <button class="button button-primary" type="submit" name="oha_devlog_csv_import" value="1">Import</button>
                </p>
            </form>

            <h2>Example CSV</h2>
            <pre style="background:#fff;border:1px solid #ddd;padding:10px;border-radius:8px;max-width:920px;white-space:pre-wrap;">title,notes,hours,category,tags,date
Centralized Stripe webhook hub,Unified all Stripe events to one endpoint,6,Feature,"Stripe,Board Hub",2026-01-23
Vendor PDF signature capture,Ensured signature image is saved + embedded in PDF,2.5,Bug Fix,"Vendors,PDF",2026-01-15 14:30</pre>
        </div>
        <?php
    }

    private static function process_csv($filepath, $dry_run = false) {
        if (!file_exists($filepath) || !is_readable($filepath)) {
            return new WP_Error('csv_read', 'CSV file could not be read.');
        }
        $handle = fopen($filepath, 'r');
        if (!$handle) {
            return new WP_Error('csv_open', 'Unable to open CSV.');
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return new WP_Error('csv_header', 'CSV appears empty or missing header row.');
        }

        $header = array_map(function($h){
            $h = strtolower(trim((string)$h));
            $map = [
                'estimated_hours' => 'hours',
                'est_hours'       => 'hours',
                'time'            => 'hours',
                'description'     => 'notes',
                'details'         => 'notes',
                'tag'             => 'tags',
            ];
            return $map[$h] ?? $h;
        }, $header);

        if (!in_array('title', $header, true)) {
            fclose($handle);
            return new WP_Error('csv_missing', 'Missing required column: title');
        }

        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        $row_num  = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $row_num++;

            $all_empty = true;
            foreach ($row as $cell) {
                if (trim((string)$cell) !== '') { $all_empty = false; break; }
            }
            if ($all_empty) { $skipped++; continue; }

            $data = [];
            foreach ($header as $i => $key) {
                $data[$key] = isset($row[$i]) ? trim((string)$row[$i]) : '';
            }

            $title = sanitize_text_field($data['title'] ?? '');
            if ($title === '') {
                $skipped++;
                $errors[] = "Row {$row_num}: missing title (skipped).";
                continue;
            }

            $notes = wp_kses_post($data['notes'] ?? '');
            $hours = isset($data['hours']) && $data['hours'] !== '' ? floatval($data['hours']) : 0.0;
            $cat   = sanitize_text_field($data['category'] ?? 'Other');
            if ($cat === '') $cat = 'Other';
            $tags  = sanitize_text_field($data['tags'] ?? '');

            $date_raw = $data['date'] ?? '';
            $post_date = '';
            if ($date_raw !== '') {
                $ts = strtotime($date_raw);
                if ($ts === false) {
                    $errors[] = "Row {$row_num}: invalid date '{$date_raw}' (used current date).";
                } else {
                    $post_date = date('Y-m-d H:i:s', $ts);
                }
            }

            if ($dry_run) {
                $imported++;
                continue;
            }

            $postarr = [
                'post_type'    => self::CPT,
                'post_status'  => 'publish',
                'post_title'   => $title,
                'post_content' => $notes,
            ];
            if ($post_date) {
                $postarr['post_date'] = $post_date;
                $postarr['post_date_gmt'] = get_gmt_from_date($post_date);
            }

            $post_id = wp_insert_post($postarr, true);
            if (is_wp_error($post_id)) {
                $skipped++;
                $errors[] = "Row {$row_num}: failed to insert '{$title}' ({$post_id->get_error_message()}).";
                continue;
            }

            update_post_meta($post_id, '_oha_hours', $hours);
            update_post_meta($post_id, '_oha_category', $cat);
            update_post_meta($post_id, '_oha_tags', $tags);

            self::activity_add('import', "Imported Dev Log entry: {$title} (~{$hours} hrs)");
            $imported++;
        }

        fclose($handle);

        return [
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ];
    }
}

// Boot
OHA_DevLog::init();
