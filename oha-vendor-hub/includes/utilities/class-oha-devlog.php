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
            'show_in_menu'  => true,
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
    public static function render_overview_widget($range = '30d') {
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
            // Start of year in site timezone
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

        // Activity feed is always shown as a simple “latest” list.
        // Do NOT filter it by range, since this option only starts logging when enabled.
        $activity_all = get_option(self::OPT_ACTIVITY, []);
        if (!is_array($activity_all)) $activity_all = [];
        $activity = array_slice($activity_all, 0, 20);

        ob_start();
        ?>
        <style>
            .oha-devlog-grid{display:grid;grid-template-columns:1fr;gap:14px}
            @media(min-width:980px){.oha-devlog-grid{grid-template-columns:1.1fr .9fr}}
            .oha-devlog-kpis{display:flex;flex-wrap:wrap;gap:12px}
            /* Board Hub uses a dark card theme. KPI boxes are light, so force readable text. */
            .oha-devlog-kpi{border:1px solid #eee;border-radius:12px;padding:10px 12px;min-width:160px;background:#fff;color:#111}
            .oha-devlog-kpi .n{font-size:22px;font-weight:700;line-height:1}
            .oha-devlog-kpi .l{opacity:.75;margin-top:4px}
            .oha-devlog-badges{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
            .oha-devlog-badge{border:1px solid #eee;border-radius:999px;padding:6px 10px;font-size:13px;opacity:.9;background:#fff;color:#111}
            .oha-devlog-list{margin:0;padding:0;list-style:none}
            .oha-devlog-list li{padding:10px 0;border-top:1px solid #f0f0f0}
            .oha-devlog-list li:first-child{border-top:none}
            .oha-devlog-meta{opacity:.75;font-size:13px;margin-top:4px}
        </style>

        <div class="oha-card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                <h3 style="margin:0;">Development Activity (<?php echo esc_html(self::range_label($range)); ?>)</h3>
            </div>

            <div class="oha-devlog-grid">
                <div>
                    <div class="oha-devlog-kpis">
                        <div class="oha-devlog-kpi">
                            <div class="n"><?php echo esc_html(number_format($total_hours, 2)); ?></div>
                            <div class="l">Estimated hours logged</div>
                        </div>
                        <div class="oha-devlog-kpi">
                            <div class="n"><?php echo esc_html(count($q->posts)); ?></div>
                            <div class="l">Dev log entries</div>
                        </div>
                        <div class="oha-devlog-kpi">
                            <div class="n"><?php echo $since_ts ? esc_html(date_i18n('M j, Y', $since_ts)) : esc_html('—'); ?></div>
                            <div class="l">Summary start</div>
                        </div>
                    </div>

                    <?php if (!empty($cat_hours)) : ?>
                        <div class="oha-devlog-badges">
                            <?php foreach ($cat_hours as $cat => $hrs) : ?>
                                <span class="oha-devlog-badge"><strong><?php echo esc_html($cat); ?></strong> — <?php echo esc_html(number_format($hrs, 2)); ?>h</span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
</div>

                <div>
                    <h4 style="margin:0 0 8px 0;">Site Activity (Auto-Logged)</h4>
                    <div class="oha-devlog-meta" style="margin:0 0 6px 0;">Showing latest <?php echo esc_html(min(20, count($activity_all))); ?> of <?php echo esc_html(count($activity_all)); ?> logged events.</div>
                    <ul class="oha-devlog-list">
                        <?php if (!empty($activity)) : foreach ($activity as $a) :
                            $when = date_i18n('M j, Y g:i a', intval($a['ts']));
                            ?>
                            <li>
                                <div><strong><?php echo esc_html($a['message']); ?></strong></div>
                                <div class="oha-devlog-meta"><?php echo esc_html($when); ?> • <?php echo esc_html($a['user']); ?> • <?php echo esc_html($a['type']); ?></div>
                            </li>
                        <?php endforeach; else : ?>
                            <li>No activity recorded yet.</li>
                        <?php endif; ?>
                    </ul>
                </div>
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
