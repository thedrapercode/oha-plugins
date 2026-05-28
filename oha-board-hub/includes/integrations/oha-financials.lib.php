<?php
/**
 * OHA Convention Financials
 * Year-over-year budget tracking for board members only.
 * Access: oha_board role (read_oha_board_hub) OR manage_options.
 * No public shortcode -- rendered via Board Hub tab and WP admin page only.
 */

if (!defined('ABSPATH')) exit;

class OHA_Financials {

    const CAP      = 'read_oha_board_hub';
    const NONCE    = 'oha_fin_nonce';
    const OPT_SEED = 'oha_fin_categories_seeded';

    // -------------------------------------------------------------------------
    // Table creation + seed
    // -------------------------------------------------------------------------

    public static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $c = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE {$wpdb->prefix}oha_budget_categories (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            type varchar(20) NOT NULL DEFAULT 'expense',
            sort_order int(11) NOT NULL DEFAULT 0,
            active tinyint(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY type_sort (type, sort_order)
        ) $c;");

        dbDelta("CREATE TABLE {$wpdb->prefix}oha_budget_entries (
            id int(11) NOT NULL AUTO_INCREMENT,
            year int(4) NOT NULL,
            category_id int(11) NOT NULL,
            amount decimal(10,2) NOT NULL DEFAULT 0.00,
            notes text,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY year_category (year, category_id)
        ) $c;");
    }

    public static function seed_categories() {
        if (get_option(self::OPT_SEED)) return;
        global $wpdb;
        $tbl = $wpdb->prefix . 'oha_budget_categories';
        if ((int)$wpdb->get_var("SELECT COUNT(*) FROM $tbl") > 0) {
            update_option(self::OPT_SEED, '1');
            return;
        }
        $cats = [
            ['Ticket Sales (Net)',       'income',       10],
            ['Vendor Booth Fees',        'income',       20],
            ['Other Income',             'income',       30],
            ['Venue / Facility',         'expense',      10],
            ['Catering / Food',          'expense',      20],
            ['A/V Equipment',            'expense',      30],
            ['Entertainment / Talent',   'expense',      40],
            ['Marketing / Advertising',  'expense',      50],
            ['Printing / Signage',       'expense',      60],
            ['Blood Prison',             'expense',      70],
            ['Education Sessions',       'expense',      80],
            ['Costume Ball',             'expense',      90],
            ['Staff / Labor',            'expense',     100],
            ['Equipment Rental',         'expense',     110],
            ['Supplies',                 'expense',     120],
            ['Insurance',                'expense',     130],
            ['Miscellaneous',            'expense',     140],
            ['Cash Sponsorship',         'sponsorship',  10],
            ['In-Kind Sponsorship',      'sponsorship',  20],
            ['Water Station Sponsorship','sponsorship',  30],
            ['Charity Ticket Sales',     'charity',      10],
            ['Charity Donations',        'charity',      20],
        ];
        foreach ($cats as [$name, $type, $order]) {
            $wpdb->insert($tbl, ['name' => $name, 'type' => $type, 'sort_order' => $order, 'active' => 1]);
        }
        update_option(self::OPT_SEED, '1');
    }

    // -------------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------------

    private static function authorized(): bool {
        return current_user_can('manage_options') || current_user_can(self::CAP);
    }

    private static function require_auth() {
        if (!self::authorized()) {
            wp_send_json_error('Unauthorized', 403);
        }
    }

    // -------------------------------------------------------------------------
    // Data helpers
    // -------------------------------------------------------------------------

    private static function get_categories(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}oha_budget_categories WHERE active = 1 ORDER BY type, sort_order, name"
        );
    }

    private static function get_all_categories(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}oha_budget_categories ORDER BY type, sort_order, name"
        );
    }

    private static function get_entries_for_year(int $year): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT category_id, amount, notes FROM {$wpdb->prefix}oha_budget_entries WHERE year = %d",
            $year
        ));
        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r->category_id] = ['amount' => (float)$r->amount, 'notes' => (string)$r->notes];
        }
        return $map;
    }

    public static function get_available_years(): array {
        global $wpdb;
        $years = $wpdb->get_col(
            "SELECT DISTINCT year FROM {$wpdb->prefix}oha_budget_entries ORDER BY year DESC"
        );
        $cur = (int)date('Y');
        if (!in_array((string)$cur, $years, true)) {
            array_unshift($years, (string)$cur);
        }
        return array_map('intval', $years);
    }

    // -------------------------------------------------------------------------
    // AJAX: save entry
    // -------------------------------------------------------------------------

    public static function ajax_save_entry() {
        self::require_auth();
        check_ajax_referer(self::NONCE, 'nonce');

        $year        = intval($_POST['year'] ?? 0);
        $category_id = intval($_POST['category_id'] ?? 0);
        $amount      = round(floatval(str_replace(',', '', $_POST['amount'] ?? '0')), 2);
        $notes       = sanitize_textarea_field($_POST['notes'] ?? '');

        if ($year < 2020 || $year > 2100 || $category_id < 1 || $amount < 0) {
            wp_send_json_error('Invalid input');
        }

        global $wpdb;
        $tbl      = $wpdb->prefix . 'oha_budget_entries';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $tbl WHERE year = %d AND category_id = %d", $year, $category_id
        ));
        $data = ['amount' => $amount, 'notes' => $notes, 'updated_at' => current_time('mysql')];

        if ($existing) {
            $wpdb->update($tbl, $data, ['year' => $year, 'category_id' => $category_id]);
        } else {
            $wpdb->insert($tbl, array_merge($data, ['year' => $year, 'category_id' => $category_id]));
        }

        wp_send_json_success(['amount' => $amount]);
    }

    // -------------------------------------------------------------------------
    // AJAX: add category
    // -------------------------------------------------------------------------

    public static function ajax_add_category() {
        self::require_auth();
        check_ajax_referer(self::NONCE, 'nonce');

        $name = sanitize_text_field($_POST['name'] ?? '');
        $type = sanitize_key($_POST['type'] ?? '');

        if (!$name || !in_array($type, ['income', 'expense', 'sponsorship', 'charity'], true)) {
            wp_send_json_error('Invalid input');
        }

        global $wpdb;
        $tbl       = $wpdb->prefix . 'oha_budget_categories';
        $max_order = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(sort_order), 0) FROM $tbl WHERE type = %s", $type
        ));
        $wpdb->insert($tbl, ['name' => $name, 'type' => $type, 'sort_order' => $max_order + 10, 'active' => 1]);

        wp_send_json_success(['id' => (int)$wpdb->insert_id, 'name' => $name, 'type' => $type]);
    }

    // -------------------------------------------------------------------------
    // AJAX: toggle category active
    // -------------------------------------------------------------------------

    public static function ajax_toggle_category() {
        self::require_auth();
        check_ajax_referer(self::NONCE, 'nonce');

        $id     = intval($_POST['id'] ?? 0);
        $active = intval($_POST['active'] ?? 0);
        if ($id < 1) wp_send_json_error('Invalid input');

        global $wpdb;
        $wpdb->update("{$wpdb->prefix}oha_budget_categories", ['active' => $active ? 1 : 0], ['id' => $id]);
        wp_send_json_success();
    }

    // -------------------------------------------------------------------------
    // Render helpers
    // -------------------------------------------------------------------------

    private static function fmt(float $v): string {
        return '$' . number_format($v, 2);
    }

    private static function change_html(float $a, float $b, string $type): string {
        $delta = $a - $b;
        if ($b == 0 || $delta == 0) return '<span class="oha-fin-change-zero">-</span>';
        $pct   = round(abs($delta / $b) * 100, 1);
        $sign  = $delta > 0 ? '+' : '-';
        $abs   = abs($delta);
        // income/sponsorship: increase = good (green). expense: increase = bad (red)
        $good_up = in_array($type, ['income', 'sponsorship'], true);
        if ($delta > 0) {
            $cls = $good_up ? 'oha-fin-change-good' : 'oha-fin-change-bad';
        } else {
            $cls = $good_up ? 'oha-fin-change-bad' : 'oha-fin-change-good';
        }
        return '<span class="' . $cls . '">' . $sign . '$' . number_format($abs, 2) . ' (' . $pct . '%)</span>';
    }

    private static function net_change_html(float $a, float $b): string {
        $delta = $a - $b;
        if ($b == 0 || $delta == 0) return '<span class="oha-fin-change-zero">-</span>';
        $pct  = round(abs($delta / $b) * 100, 1);
        $sign = $delta > 0 ? '+' : '-';
        $abs  = abs($delta);
        $cls  = $delta > 0 ? 'oha-fin-change-good' : 'oha-fin-change-bad';
        return '<span class="' . $cls . '">' . $sign . '$' . number_format($abs, 2) . ' (' . $pct . '%)</span>';
    }

    // -------------------------------------------------------------------------
    // Render: Board Hub tab
    // -------------------------------------------------------------------------

    public static function render_tab(): string {
        if (!self::authorized()) return '<p>Access restricted.</p>';

        $years     = self::get_available_years();
        $year_a    = intval($_GET['fin_year_a'] ?? ($years[0] ?? (int)date('Y')));
        $year_b    = intval($_GET['fin_year_b'] ?? ($years[1] ?? 0));
        $cats      = self::get_categories();
        $entries_a = self::get_entries_for_year($year_a);
        $entries_b = $year_b ? self::get_entries_for_year($year_b) : [];
        $nonce     = wp_create_nonce(self::NONCE);

        $sections = [
            'income'      => ['label' => 'Income',      'cats' => []],
            'expense'     => ['label' => 'Expenses',    'cats' => []],
            'sponsorship' => ['label' => 'Sponsorship', 'cats' => []],
            'charity'     => ['label' => 'Charity (Pass-Through)', 'cats' => []],
        ];
        foreach ($cats as $cat) {
            if (isset($sections[$cat->type])) $sections[$cat->type]['cats'][] = $cat;
        }

        // Section totals
        $totals_a = ['income' => 0, 'expense' => 0, 'sponsorship' => 0, 'charity' => 0];
        $totals_b = ['income' => 0, 'expense' => 0, 'sponsorship' => 0, 'charity' => 0];
        foreach ($sections as $type => $sec) {
            foreach ($sec['cats'] as $cat) {
                $totals_a[$type] += $entries_a[$cat->id]['amount'] ?? 0;
                $totals_b[$type] += $entries_b[$cat->id]['amount'] ?? 0;
            }
        }
        $net_a = $totals_a['income'] + $totals_a['sponsorship'] - $totals_a['expense'];
        $net_b = $year_b ? ($totals_b['income'] + $totals_b['sponsorship'] - $totals_b['expense']) : 0;

        ob_start();
        ?>
        <style>
        .oha-fin-wrap { font-family: inherit; color: var(--global-palette4, #f7fafc); }
        .oha-fin-controls { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:20px; }
        .oha-fin-controls label { font-size:13px; color:var(--global-palette6,#cbd2d9); margin-right:4px; }
        .oha-fin-controls select { background:#1a1a1a; color:#eee; border:1px solid #444; border-radius:6px; padding:6px 10px; font-size:13px; }
        .oha-fin-section { background:#111; border:1px solid rgba(173,49,25,0.3); border-radius:10px; margin-bottom:16px; overflow:hidden; }
        .oha-fin-section-head { background:#1a0a08; padding:12px 16px; display:flex; align-items:center; justify-content:space-between; }
        .oha-fin-section-title { font-size:14px; font-weight:700; color:var(--global-palette3,#fff); text-transform:uppercase; letter-spacing:.06em; }
        .oha-fin-table { width:100%; border-collapse:collapse; }
        .oha-fin-table th { padding:8px 12px; font-size:12px; font-weight:600; color:var(--global-palette6,#cbd2d9); text-align:left; border-bottom:1px solid #222; background:#0e0e0e; }
        .oha-fin-table th.num { text-align:right; }
        .oha-fin-table td { padding:8px 12px; font-size:14px; border-bottom:1px solid #1a1a1a; vertical-align:middle; }
        .oha-fin-table td.num { text-align:right; font-variant-numeric:tabular-nums; }
        .oha-fin-table tr:hover td { background:rgba(255,255,255,0.03); }
        .oha-fin-table tr.total-row td { border-top:2px solid #333; font-weight:700; font-size:14px; background:#0e0e0e; }
        .oha-fin-table tr.net-row td { border-top:3px solid var(--global-palette1,#ad3119); font-weight:800; font-size:16px; background:#0a0a0a; color:var(--global-palette3,#fff); }
        .oha-fin-amount { cursor:pointer; color:var(--global-palette4,#f7fafc); border-bottom:1px dashed #555; padding-bottom:1px; }
        .oha-fin-amount:hover { color:var(--global-palette7,#e76653); border-bottom-color:var(--global-palette7,#e76653); }
        .oha-fin-edit-wrap { display:none; align-items:center; gap:6px; }
        .oha-fin-edit-wrap input { background:#222; color:#eee; border:1px solid #555; border-radius:4px; padding:4px 8px; font-size:13px; width:110px; text-align:right; }
        .oha-fin-edit-wrap button { padding:4px 10px; font-size:12px; border-radius:4px; cursor:pointer; }
        .oha-fin-save-btn { background:var(--global-palette1,#ad3119); color:#fff; border:none; }
        .oha-fin-save-btn:hover { background:var(--global-palette2,#ff0000); }
        .oha-fin-cancel-btn { background:#333; color:#ccc; border:none; }
        .oha-fin-add-row td { padding:8px 12px; }
        .oha-fin-add-btn { background:none; border:1px dashed #444; color:#888; font-size:12px; padding:4px 12px; border-radius:4px; cursor:pointer; }
        .oha-fin-add-btn:hover { border-color:var(--global-palette7,#e76653); color:var(--global-palette7,#e76653); }
        .oha-fin-change-good { color:#4caf50; font-size:12px; }
        .oha-fin-change-bad  { color:#f44336; font-size:12px; }
        .oha-fin-change-zero { color:#555; font-size:12px; }
        .oha-fin-net-positive { color:#4caf50; }
        .oha-fin-net-negative { color:#f44336; }
        .oha-fin-charity-note { font-size:12px; color:var(--global-palette6,#cbd2d9); padding:8px 16px 12px; font-style:italic; }
        .oha-fin-saving { color:#888; font-size:12px; }
        .oha-fin-add-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:9999; align-items:center; justify-content:center; }
        .oha-fin-add-modal.open { display:flex; }
        .oha-fin-modal-box { background:#1a1a1a; border:1px solid #444; border-radius:10px; padding:24px; min-width:300px; }
        .oha-fin-modal-box h4 { margin:0 0 16px; color:#fff; }
        .oha-fin-modal-box input { width:100%; background:#111; color:#eee; border:1px solid #555; border-radius:4px; padding:8px 10px; font-size:14px; box-sizing:border-box; }
        .oha-fin-modal-actions { display:flex; gap:8px; margin-top:16px; justify-content:flex-end; }
        </style>

        <div class="oha-fin-wrap">

            <div class="oha-fin-controls">
                <div>
                    <label>Year</label>
                    <select id="oha-fin-year-a" onchange="ohaFinSwitchYear()">
                        <?php foreach ($years as $y): ?>
                            <option value="<?php echo $y; ?>" <?php selected($y, $year_a); ?>><?php echo $y; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Compare to</label>
                    <select id="oha-fin-year-b" onchange="ohaFinSwitchYear()">
                        <option value="0">None</option>
                        <?php foreach ($years as $y): ?>
                            <option value="<?php echo $y; ?>" <?php selected($y, $year_b); ?>><?php echo $y; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <span style="font-size:12px; color:#666;">Click any amount to edit</span>
            </div>

            <?php foreach ($sections as $type => $sec):
                $is_charity = $type === 'charity';
            ?>
            <div class="oha-fin-section">
                <div class="oha-fin-section-head">
                    <span class="oha-fin-section-title"><?php echo esc_html($sec['label']); ?></span>
                    <span style="font-size:13px; color:var(--global-palette6,#cbd2d9);">
                        Total: <strong style="color:var(--global-palette3,#fff);"><?php echo self::fmt($totals_a[$type]); ?></strong>
                        <?php if ($year_b): ?>&nbsp;vs&nbsp;<span style="color:#666;"><?php echo self::fmt($totals_b[$type]); ?></span><?php endif; ?>
                    </span>
                </div>
                <table class="oha-fin-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th class="num"><?php echo $year_a; ?></th>
                            <?php if ($year_b): ?>
                                <th class="num"><?php echo $year_b; ?></th>
                                <th class="num">Change</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sec['cats'] as $cat):
                        $amt_a = $entries_a[$cat->id]['amount'] ?? 0;
                        $amt_b = $entries_b[$cat->id]['amount'] ?? 0;
                    ?>
                        <tr>
                            <td><?php echo esc_html($cat->name); ?></td>
                            <td class="num">
                                <span class="oha-fin-amount" data-cat="<?php echo $cat->id; ?>" data-year="<?php echo $year_a; ?>" data-amount="<?php echo $amt_a; ?>"><?php echo self::fmt($amt_a); ?></span>
                                <span class="oha-fin-edit-wrap" id="oha-fin-edit-<?php echo $cat->id; ?>-<?php echo $year_a; ?>">
                                    <input type="text" value="<?php echo number_format($amt_a, 2); ?>" class="oha-fin-input">
                                    <button class="oha-fin-save-btn" data-cat="<?php echo $cat->id; ?>" data-year="<?php echo $year_a; ?>">Save</button>
                                    <button class="oha-fin-cancel-btn">Cancel</button>
                                </span>
                            </td>
                            <?php if ($year_b): ?>
                                <td class="num">
                                    <span class="oha-fin-amount" data-cat="<?php echo $cat->id; ?>" data-year="<?php echo $year_b; ?>" data-amount="<?php echo $amt_b; ?>"><?php echo self::fmt($amt_b); ?></span>
                                    <span class="oha-fin-edit-wrap" id="oha-fin-edit-<?php echo $cat->id; ?>-<?php echo $year_b; ?>">
                                        <input type="text" value="<?php echo number_format($amt_b, 2); ?>" class="oha-fin-input">
                                        <button class="oha-fin-save-btn" data-cat="<?php echo $cat->id; ?>" data-year="<?php echo $year_b; ?>">Save</button>
                                        <button class="oha-fin-cancel-btn">Cancel</button>
                                    </span>
                                </td>
                                <td class="num"><?php echo self::change_html($amt_a, $amt_b, $type); ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td>Total <?php echo esc_html($sec['label']); ?></td>
                            <td class="num"><?php echo self::fmt($totals_a[$type]); ?></td>
                            <?php if ($year_b): ?>
                                <td class="num"><?php echo self::fmt($totals_b[$type]); ?></td>
                                <td class="num"><?php echo self::change_html($totals_a[$type], $totals_b[$type], $type); ?></td>
                            <?php endif; ?>
                        </tr>
                        <tr class="oha-fin-add-row">
                            <td colspan="<?php echo $year_b ? 4 : 2; ?>">
                                <button class="oha-fin-add-btn" data-type="<?php echo esc_attr($type); ?>">+ Add Category</button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
                <?php if ($is_charity): ?>
                    <p class="oha-fin-charity-note">Charity proceeds pass through OHA and are paid out to the designated charity. Not included in net calculation.</p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <!-- Net row -->
            <div class="oha-fin-section">
                <table class="oha-fin-table">
                    <tbody>
                        <tr class="net-row">
                            <td>Net (Income + Sponsorship - Expenses)</td>
                            <td class="num <?php echo $net_a >= 0 ? 'oha-fin-net-positive' : 'oha-fin-net-negative'; ?>">
                                <?php echo self::fmt($net_a); ?>
                            </td>
                            <?php if ($year_b): ?>
                                <td class="num <?php echo $net_b >= 0 ? 'oha-fin-net-positive' : 'oha-fin-net-negative'; ?>">
                                    <?php echo self::fmt($net_b); ?>
                                </td>
                                <td class="num"><?php echo self::net_change_html($net_a, $net_b); ?></td>
                            <?php endif; ?>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div><!-- .oha-fin-wrap -->

        <!-- Add category modal -->
        <div class="oha-fin-add-modal" id="oha-fin-modal">
            <div class="oha-fin-modal-box">
                <h4>Add Category</h4>
                <input type="text" id="oha-fin-modal-name" placeholder="Category name" maxlength="100">
                <input type="hidden" id="oha-fin-modal-type">
                <div class="oha-fin-modal-actions">
                    <button class="oha-fin-cancel-btn" onclick="ohaFinCloseModal()">Cancel</button>
                    <button class="oha-fin-save-btn" onclick="ohaFinSaveCategory()">Add</button>
                </div>
            </div>
        </div>

        <script>
        (function() {
            var AJAX   = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            var NONCE  = <?php echo wp_json_encode($nonce); ?>;
            var YEAR_A = <?php echo $year_a; ?>;
            var YEAR_B = <?php echo $year_b; ?>;

            // Inline edit: click amount
            document.addEventListener('click', function(e) {
                var amtEl = e.target.closest('.oha-fin-amount');
                if (amtEl) {
                    var cat  = amtEl.dataset.cat;
                    var year = amtEl.dataset.year;
                    var wrap = document.getElementById('oha-fin-edit-' + cat + '-' + year);
                    if (!wrap) return;
                    amtEl.style.display = 'none';
                    wrap.style.display  = 'flex';
                    wrap.querySelector('.oha-fin-input').focus();
                    wrap.querySelector('.oha-fin-input').select();
                    return;
                }

                var saveBtn = e.target.closest('.oha-fin-save-btn');
                if (saveBtn && saveBtn.dataset.cat) {
                    var cat  = saveBtn.dataset.cat;
                    var year = saveBtn.dataset.year;
                    var wrap = document.getElementById('oha-fin-edit-' + cat + '-' + year);
                    var val  = wrap.querySelector('.oha-fin-input').value.replace(/,/g, '');
                    ohaFinSaveEntry(cat, year, val, wrap);
                    return;
                }

                var cancelBtn = e.target.closest('.oha-fin-cancel-btn');
                if (cancelBtn && cancelBtn.closest('.oha-fin-edit-wrap')) {
                    var wrap  = cancelBtn.closest('.oha-fin-edit-wrap');
                    var parts = wrap.id.replace('oha-fin-edit-','').split('-');
                    var cat   = parts[0];
                    var year  = parts[1];
                    wrap.style.display = 'none';
                    var amtEl = wrap.previousElementSibling;
                    if (amtEl) amtEl.style.display = '';
                    return;
                }

                var addBtn = e.target.closest('.oha-fin-add-btn');
                if (addBtn) {
                    document.getElementById('oha-fin-modal-type').value = addBtn.dataset.type;
                    document.getElementById('oha-fin-modal-name').value = '';
                    document.getElementById('oha-fin-modal').classList.add('open');
                    setTimeout(function(){ document.getElementById('oha-fin-modal-name').focus(); }, 50);
                    return;
                }
            });

            // Save on Enter key in edit input
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && e.target.classList.contains('oha-fin-input')) {
                    var wrap = e.target.closest('.oha-fin-edit-wrap');
                    var saveBtn = wrap.querySelector('.oha-fin-save-btn');
                    if (saveBtn) saveBtn.click();
                }
                if (e.key === 'Escape' && e.target.classList.contains('oha-fin-input')) {
                    var wrap = e.target.closest('.oha-fin-edit-wrap');
                    var cancelBtn = wrap.querySelector('.oha-fin-cancel-btn');
                    if (cancelBtn) cancelBtn.click();
                }
                if (e.key === 'Enter' && e.target.id === 'oha-fin-modal-name') {
                    ohaFinSaveCategory();
                }
            });

            function ohaFinSaveEntry(cat, year, val, wrap) {
                var amt = parseFloat(val);
                if (isNaN(amt) || amt < 0) { alert('Enter a valid amount.'); return; }

                var saveBtn = wrap.querySelector('.oha-fin-save-btn');
                saveBtn.textContent = 'Saving...';
                saveBtn.disabled = true;

                var fd = new FormData();
                fd.append('action', 'oha_fin_save_entry');
                fd.append('nonce', NONCE);
                fd.append('year', year);
                fd.append('category_id', cat);
                fd.append('amount', amt.toFixed(2));

                fetch(AJAX, { method: 'POST', body: fd })
                    .then(function(r){ return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            var amtEl = wrap.previousElementSibling;
                            var fmt = '$' + amt.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
                            amtEl.textContent = fmt;
                            amtEl.dataset.amount = amt;
                            wrap.querySelector('.oha-fin-input').value = amt.toFixed(2);
                            wrap.style.display = 'none';
                            amtEl.style.display = '';
                            // Reload to update totals and change column
                            window.location.reload();
                        } else {
                            alert('Save failed.');
                        }
                    })
                    .catch(function(){ alert('Network error.'); })
                    .finally(function(){
                        saveBtn.textContent = 'Save';
                        saveBtn.disabled = false;
                    });
            }

            window.ohaFinCloseModal = function() {
                document.getElementById('oha-fin-modal').classList.remove('open');
            };

            window.ohaFinSaveCategory = function() {
                var name = document.getElementById('oha-fin-modal-name').value.trim();
                var type = document.getElementById('oha-fin-modal-type').value;
                if (!name) { alert('Enter a category name.'); return; }

                var fd = new FormData();
                fd.append('action', 'oha_fin_add_category');
                fd.append('nonce', NONCE);
                fd.append('name', name);
                fd.append('type', type);

                fetch(AJAX, { method: 'POST', body: fd })
                    .then(function(r){ return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            ohaFinCloseModal();
                            window.location.reload();
                        } else {
                            alert('Failed to add category.');
                        }
                    });
            };

            window.ohaFinSwitchYear = function() {
                var a = document.getElementById('oha-fin-year-a').value;
                var b = document.getElementById('oha-fin-year-b').value;
                var url = new URL(window.location.href);
                url.searchParams.set('fin_year_a', a);
                url.searchParams.set('fin_year_b', b);
                window.location.href = url.toString();
            };

            // Close modal on background click
            document.getElementById('oha-fin-modal').addEventListener('click', function(e) {
                if (e.target === this) ohaFinCloseModal();
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // Render: WP admin page (CSV export lives here only)
    // -------------------------------------------------------------------------

    public static function render_admin_page() {
        if (!self::authorized()) {
            wp_die('Access denied.');
        }

        $years     = self::get_available_years();
        $year_a    = intval($_GET['fin_year_a'] ?? ($years[0] ?? (int)date('Y')));
        $year_b    = intval($_GET['fin_year_b'] ?? ($years[1] ?? 0));
        $cats      = self::get_categories();
        $entries_a = self::get_entries_for_year($year_a);
        $entries_b = $year_b ? self::get_entries_for_year($year_b) : [];

        // Handle CSV export
        if (isset($_GET['export_csv']) && $_GET['export_csv'] === '1') {
            self::export_csv($year_a, $year_b, $cats, $entries_a, $entries_b);
            exit;
        }

        $sections = [
            'income'      => ['label' => 'Income',      'cats' => []],
            'expense'     => ['label' => 'Expenses',    'cats' => []],
            'sponsorship' => ['label' => 'Sponsorship', 'cats' => []],
            'charity'     => ['label' => 'Charity (Pass-Through)', 'cats' => []],
        ];
        foreach ($cats as $cat) {
            if (isset($sections[$cat->type])) $sections[$cat->type]['cats'][] = $cat;
        }

        $totals_a = ['income' => 0, 'expense' => 0, 'sponsorship' => 0, 'charity' => 0];
        $totals_b = ['income' => 0, 'expense' => 0, 'sponsorship' => 0, 'charity' => 0];
        foreach ($sections as $type => $sec) {
            foreach ($sec['cats'] as $cat) {
                $totals_a[$type] += $entries_a[$cat->id]['amount'] ?? 0;
                $totals_b[$type] += $entries_b[$cat->id]['amount'] ?? 0;
            }
        }
        $net_a = $totals_a['income'] + $totals_a['sponsorship'] - $totals_a['expense'];
        $net_b = $year_b ? ($totals_b['income'] + $totals_b['sponsorship'] - $totals_b['expense']) : 0;

        $nonce   = wp_create_nonce(self::NONCE);
        $base    = admin_url('admin.php?page=oha-fin-admin');
        $csv_url = add_query_arg(['fin_year_a' => $year_a, 'fin_year_b' => $year_b, 'export_csv' => '1'], $base);

        echo '<div class="wrap">';
        echo '<h1>Convention Financials</h1>';
        echo '<p style="color:#666;font-size:13px;">Access restricted to board members and administrators. CSV export available on this page only.</p>';

        // Year selectors
        echo '<div style="display:flex;gap:16px;align-items:center;margin-bottom:20px;flex-wrap:wrap;">';
        echo '<form method="get" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">';
        echo '<input type="hidden" name="page" value="oha-fin-admin">';
        echo '<label>Year: <select name="fin_year_a">';
        foreach ($years as $y) echo '<option value="' . $y . '"' . selected($y, $year_a, false) . '>' . $y . '</option>';
        echo '</select></label>';
        echo '<label>Compare to: <select name="fin_year_b"><option value="0">None</option>';
        foreach ($years as $y) echo '<option value="' . $y . '"' . selected($y, $year_b, false) . '>' . $y . '</option>';
        echo '</select></label>';
        echo '<button type="submit" class="button">View</button>';
        echo '</form>';
        echo '<a href="' . esc_url($csv_url) . '" class="button button-primary">Export CSV</a>';
        echo '</div>';

        // Table output (same structure as tab but in WP admin styling)
        echo '<div style="max-width:900px;">';
        foreach ($sections as $type => $sec) {
            echo '<h2>' . esc_html($sec['label']) . '</h2>';
            echo '<table class="wp-list-table widefat fixed striped" style="margin-bottom:8px;">';
            echo '<thead><tr><th>Category</th><th style="text-align:right;">' . $year_a . '</th>';
            if ($year_b) echo '<th style="text-align:right;">' . $year_b . '</th><th style="text-align:right;">Change</th>';
            echo '</tr></thead><tbody>';
            foreach ($sec['cats'] as $cat) {
                $amt_a = $entries_a[$cat->id]['amount'] ?? 0;
                $amt_b = $entries_b[$cat->id]['amount'] ?? 0;
                echo '<tr><td>' . esc_html($cat->name) . '</td>';
                echo '<td style="text-align:right;">' . self::fmt($amt_a) . '</td>';
                if ($year_b) {
                    echo '<td style="text-align:right;">' . self::fmt($amt_b) . '</td>';
                    echo '<td style="text-align:right;">' . self::change_html($amt_a, $amt_b, $type) . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody><tfoot><tr style="font-weight:bold;">';
            echo '<td>Total</td><td style="text-align:right;">' . self::fmt($totals_a[$type]) . '</td>';
            if ($year_b) {
                echo '<td style="text-align:right;">' . self::fmt($totals_b[$type]) . '</td>';
                echo '<td style="text-align:right;">' . self::change_html($totals_a[$type], $totals_b[$type], $type) . '</td>';
            }
            echo '</tr></tfoot></table>';
            if ($type === 'charity') echo '<p style="font-size:12px;color:#666;font-style:italic;">Charity proceeds pass through OHA and are paid out to the designated charity. Not included in net calculation.</p>';
        }
        echo '<table class="wp-list-table widefat" style="margin-top:12px;"><tbody>';
        echo '<tr style="font-weight:800;font-size:16px;">';
        echo '<td>Net (Income + Sponsorship - Expenses)</td>';
        echo '<td style="text-align:right;color:' . ($net_a >= 0 ? '#0a7a3a' : '#c0392b') . ';">' . self::fmt($net_a) . '</td>';
        if ($year_b) {
            echo '<td style="text-align:right;color:' . ($net_b >= 0 ? '#0a7a3a' : '#c0392b') . ';">' . self::fmt($net_b) . '</td>';
            echo '<td style="text-align:right;">' . self::net_change_html($net_a, $net_b) . '</td>';
        }
        echo '</tr></tbody></table>';
        echo '</div>';
        echo '</div>';
    }

    // -------------------------------------------------------------------------
    // CSV export (admin page only)
    // -------------------------------------------------------------------------

    private static function export_csv(int $year_a, int $year_b, array $cats, array $entries_a, array $entries_b) {
        $filename = 'oha-financials-' . $year_a . ($year_b ? '-vs-' . $year_b : '') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');

        $header = ['Section', 'Category', $year_a];
        if ($year_b) { $header[] = $year_b; $header[] = 'Change ($)'; $header[] = 'Change (%)'; }
        fputcsv($out, $header);

        $sections = [
            'income'      => 'Income',
            'expense'     => 'Expenses',
            'sponsorship' => 'Sponsorship',
            'charity'     => 'Charity',
        ];

        $totals_a = array_fill_keys(array_keys($sections), 0);
        $totals_b = array_fill_keys(array_keys($sections), 0);

        foreach ($sections as $type => $label) {
            $type_cats = array_filter($cats, fn($c) => $c->type === $type);
            foreach ($type_cats as $cat) {
                $amt_a = $entries_a[$cat->id]['amount'] ?? 0;
                $amt_b = $entries_b[$cat->id]['amount'] ?? 0;
                $totals_a[$type] += $amt_a;
                $totals_b[$type] += $amt_b;
                $row = [$label, $cat->name, number_format($amt_a, 2)];
                if ($year_b) {
                    $delta = $amt_a - $amt_b;
                    $pct   = $amt_b != 0 ? round(($delta / $amt_b) * 100, 1) : '';
                    $row[] = number_format($amt_b, 2);
                    $row[] = number_format($delta, 2);
                    $row[] = $pct !== '' ? $pct . '%' : '';
                }
                fputcsv($out, $row);
            }
            $row = [$label, 'TOTAL', number_format($totals_a[$type], 2)];
            if ($year_b) {
                $delta = $totals_a[$type] - $totals_b[$type];
                $pct   = $totals_b[$type] != 0 ? round(($delta / $totals_b[$type]) * 100, 1) : '';
                $row[] = number_format($totals_b[$type], 2);
                $row[] = number_format($delta, 2);
                $row[] = $pct !== '' ? $pct . '%' : '';
            }
            fputcsv($out, $row);
            fputcsv($out, []);
        }

        $net_a = $totals_a['income'] + $totals_a['sponsorship'] - $totals_a['expense'];
        $net_b = $totals_b['income'] + $totals_b['sponsorship'] - $totals_b['expense'];
        $row   = ['', 'NET (Income + Sponsorship - Expenses)', number_format($net_a, 2)];
        if ($year_b) {
            $delta = $net_a - $net_b;
            $pct   = $net_b != 0 ? round(($delta / $net_b) * 100, 1) : '';
            $row[] = number_format($net_b, 2);
            $row[] = number_format($delta, 2);
            $row[] = $pct !== '' ? $pct . '%' : '';
        }
        fputcsv($out, $row);
        fclose($out);
    }
}
