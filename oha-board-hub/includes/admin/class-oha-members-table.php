<?php
/**
 * Enhanced Members Table — Unified SWPM + UsersWP Member List
 *
 * Data sources (in priority order):
 * 1. uwp_account_type usermeta  — new members registered after Simple Membership removal
 * 2. wp_swpm_members_tbl        — legacy members from Simple Membership era
 *
 * Address priority (per user):
 * 1. billing_address_display usermeta (set by Stripe webhook)
 * 2. billing_address_line1 / billing_city / etc usermeta
 * 3. SWPM table address columns (address_street, address_city, address_state, address_zipcode, country)
 *
 * Card-sent status priority:
 * 1. oha_card_sent usermeta (set by admin form)
 * 2. oha_card_status custom table (set by frontend toggle)
 *
 * @package OHA_Board_Hub
 * @since 1.6.0 / updated 2.2.12
 */

if (!defined('ABSPATH')) {
    exit;
}

class OHA_Members_Table {

    // =========================================================================
    // PUBLIC RENDER ENTRY POINT
    // =========================================================================

    public static function render() {
        global $wpdb;

        // Handle card status update (from admin form)
        if (
            isset($_POST['oha_update_card_status'], $_POST['oha_card_status_nonce'], $_POST['user_id']) &&
            wp_verify_nonce($_POST['oha_card_status_nonce'], 'oha_update_card_status')
        ) {
            $user_id   = intval($_POST['user_id']);
            $card_sent = isset($_POST['card_sent']) ? 1 : 0;

            update_user_meta($user_id, 'oha_card_sent', $card_sent);
            if ($card_sent) {
                update_user_meta($user_id, 'oha_card_sent_date', current_time('mysql'));
            } else {
                delete_user_meta($user_id, 'oha_card_sent_date');
            }

            // Sync to card table so frontend toggle stays consistent
            $card_tbl = $wpdb->prefix . 'oha_card_status';
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $card_tbl)) === $card_tbl) {
                $wpdb->replace($card_tbl, [
                    'member_id' => $user_id,
                    'sent'      => $card_sent,
                    'date'      => $card_sent ? gmdate('Y-m-d') : null,
                ], ['%d', '%d', '%s']);
            }

            if (class_exists('OHA_Logger')) {
                OHA_Logger::info('Admin: card status updated', ['user_id' => $user_id, 'card_sent' => $card_sent]);
            }

            echo '<div class="notice notice-success"><p>' .
                esc_html__('Card status updated successfully.', 'oha-board-hub') .
                '</p></div>';
        }

        // Handle CSV export
        if (
            isset($_POST['oha_export_members_csv'], $_POST['oha_export_nonce']) &&
            wp_verify_nonce($_POST['oha_export_nonce'], 'oha_export_members')
        ) {
            $view    = sanitize_text_field($_POST['member_view'] ?? 'new_paid');
            $search  = sanitize_text_field($_POST['member_search'] ?? '');
            $members = self::get_members($view, $search);
            self::export_csv($members);
            exit;
        }

        // Get filter parameters
        $view   = isset($_GET['member_view'])   ? sanitize_text_field($_GET['member_view'])   : 'new_paid';
        $search = isset($_GET['member_search']) ? sanitize_text_field($_GET['member_search']) : '';

        $members = self::get_members($view, $search);

        ?>
        <div class="oha-members-enhanced">
            <div class="oha-members-header">
                <h2><?php esc_html_e('Member Management', 'oha-board-hub'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Displays members from all sources: UsersWP account type and legacy Simple Membership records.', 'oha-board-hub'); ?>
                </p>
            </div>

            <!-- Filters -->
            <div class="oha-members-filters">
                <form method="get" class="oha-filter-form">
                    <?php
                    if (isset($_GET['page'])) echo '<input type="hidden" name="page" value="' . esc_attr($_GET['page']) . '">';
                    if (isset($_GET['tab']))  echo '<input type="hidden" name="tab"  value="' . esc_attr($_GET['tab'])  . '">';
                    ?>
                    <div class="filter-group">
                        <label><?php esc_html_e('View:', 'oha-board-hub'); ?></label>
                        <select name="member_view" onchange="this.form.submit()">
                            <option value="new_paid"    <?php selected($view, 'new_paid'); ?>><?php esc_html_e('New Paid Members (30 days)',  'oha-board-hub'); ?></option>
                            <option value="new_listing" <?php selected($view, 'new_listing'); ?>><?php esc_html_e('New Listing Owners (30 days)', 'oha-board-hub'); ?></option>
                            <option value="all_paid"    <?php selected($view, 'all_paid'); ?>><?php esc_html_e('All Paid Members',             'oha-board-hub'); ?></option>
                            <option value="all_listing" <?php selected($view, 'all_listing'); ?>><?php esc_html_e('All Listing Owners',            'oha-board-hub'); ?></option>
                            <option value="cards_not_sent" <?php selected($view, 'cards_not_sent'); ?>><?php esc_html_e('Cards Not Sent',           'oha-board-hub'); ?></option>
                            <option value="all"         <?php selected($view, 'all'); ?>><?php esc_html_e('All Members',                  'oha-board-hub'); ?></option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label><?php esc_html_e('Search:', 'oha-board-hub'); ?></label>
                        <input type="search" name="member_search" value="<?php echo esc_attr($search); ?>"
                               placeholder="<?php esc_attr_e('Name, email, username…', 'oha-board-hub'); ?>">
                        <button type="submit" class="button"><?php esc_html_e('Search', 'oha-board-hub'); ?></button>
                        <?php if (!empty($search)) : ?>
                            <a href="?page=<?php echo esc_attr($_GET['page'] ?? ''); ?>&tab=<?php echo esc_attr($_GET['tab'] ?? ''); ?>&member_view=<?php echo esc_attr($view); ?>"
                               class="button"><?php esc_html_e('Clear', 'oha-board-hub'); ?></a>
                        <?php endif; ?>
                    </div>
                </form>
                <div class="filter-stats">
                    <strong><?php echo count($members); ?></strong>
                    <?php esc_html_e('members found', 'oha-board-hub'); ?>
                </div>
            </div>

            <!-- Members Table -->
            <table class="wp-list-table widefat fixed striped oha-members-table">
                <thead>
                    <tr>
                        <th style="width:50px;"><?php esc_html_e('ID',          'oha-board-hub'); ?></th>
                        <th><?php esc_html_e('Name',       'oha-board-hub'); ?></th>
                        <th><?php esc_html_e('Email',      'oha-board-hub'); ?></th>
                        <th><?php esc_html_e('Level',      'oha-board-hub'); ?></th>
                        <th><?php esc_html_e('Address',    'oha-board-hub'); ?></th>
                        <th><?php esc_html_e('Registered', 'oha-board-hub'); ?></th>
                        <th><?php esc_html_e('Start / Paid Since', 'oha-board-hub'); ?></th>
                        <th><?php esc_html_e('Card Sent',  'oha-board-hub'); ?></th>
                        <th><?php esc_html_e('Actions',    'oha-board-hub'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)) : ?>
                        <tr>
                            <td colspan="9" style="text-align:center;padding:40px;">
                                <em><?php esc_html_e('No members found matching your criteria.', 'oha-board-hub'); ?></em>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($members as $member) : ?>
                            <?php
                            $user_id    = intval($member['user_id'] ?? 0);
                            $email      = $member['user_email']   ?? '';
                            $name       = $member['display_name'] ?? '';
                            $registered = $member['user_registered'] ?? '';
                            $paid_since = $member['paid_since']   ?? null;
                            $is_upgrade = $member['is_upgrade']   ?? false;
                            $source     = $member['_source']      ?? 'uwp';
                            $swpm_mid   = intval($member['_swpm_member_id'] ?? 0);
                            $level_label= self::get_member_type_label($member['account_type'] ?? '');

                            // Address: usermeta first, then SWPM columns
                            $address = self::get_member_address($user_id, $member['_swpm_address'] ?? null);

                            // Card status: check usermeta then card table
                            $card_info  = self::get_card_status_for_user($user_id);
                            $card_sent  = $card_info['sent'];
                            ?>
                            <tr>
                                <td><?php echo $user_id ?: esc_html('SM:' . $swpm_mid); ?></td>
                                <td>
                                    <strong><?php echo esc_html($name); ?></strong>
                                    <?php if ($source === 'swpm') : ?>
                                        <br><small style="color:#999;">Legacy</small>
                                    <?php elseif ($source === 'both') : ?>
                                        <br><small style="color:#999;">Migrated</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($email) : ?>
                                        <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                                    <?php else : ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <span class="member-type-badge member-type-<?php echo esc_attr($member['account_type'] ?? ''); ?>">
                                        <?php echo esc_html($level_label); ?>
                                    </span>
                                </td>
                                <td class="address-column">
                                    <?php if ($address) : ?>
                                        <div class="address-display"><?php echo nl2br(esc_html($address)); ?></div>
                                    <?php else : ?>
                                        <em style="color:#999;"><?php esc_html_e('No address', 'oha-board-hub'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $registered ? esc_html(date('M j, Y', strtotime($registered))) : '—'; ?>
                                </td>
                                <td>
                                    <?php if ($paid_since) : ?>
                                        <span style="color:#2271b1;"><?php echo esc_html(date('M j, Y', strtotime($paid_since))); ?></span>
                                        <?php if ($is_upgrade) : ?>
                                            <br><small style="color:#d63638;">⬆ Upgraded</small>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span style="color:#999;">
                                            <?php echo in_array($member['account_type'] ?? '', ['oha_member', 'OHA Member']) ? esc_html__('Member', 'oha-board-hub') : '—'; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user_id > 0) : ?>
                                        <form method="post" style="display:inline;">
                                            <?php wp_nonce_field('oha_update_card_status', 'oha_card_status_nonce'); ?>
                                            <input type="hidden" name="user_id"              value="<?php echo intval($user_id); ?>">
                                            <input type="hidden" name="oha_update_card_status" value="1">
                                            <label class="card-status-toggle">
                                                <input type="checkbox" name="card_sent" value="1"
                                                       <?php checked($card_sent, true); ?>
                                                       onchange="this.form.submit()">
                                                <span class="toggle-label">
                                                    <?php echo $card_sent
                                                        ? esc_html__('✓ Sent', 'oha-board-hub')
                                                        : esc_html__('Not Sent', 'oha-board-hub'); ?>
                                                    <?php if ($card_sent && !empty($card_info['date'])) : ?>
                                                        <br><small><?php echo esc_html(date('M j, Y', strtotime($card_info['date']))); ?></small>
                                                    <?php endif; ?>
                                                </span>
                                            </label>
                                        </form>
                                    <?php else : ?>
                                        <em style="color:#999;">No WP account</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user_id > 0) : ?>
                                        <a href="<?php echo admin_url('user-edit.php?user_id=' . $user_id); ?>"
                                           class="button button-small"><?php esc_html_e('Edit', 'oha-board-hub'); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Export -->
            <div class="oha-members-footer">
                <form method="post" style="display:inline-block;">
                    <?php wp_nonce_field('oha_export_members', 'oha_export_nonce'); ?>
                    <input type="hidden" name="member_view"   value="<?php echo esc_attr($view); ?>">
                    <input type="hidden" name="member_search" value="<?php echo esc_attr($search); ?>">
                    <button type="submit" name="oha_export_members_csv" class="button button-secondary">
                        📥 <?php esc_html_e('Export to CSV', 'oha-board-hub'); ?>
                    </button>
                </form>
            </div>
        </div>

        <style>
        .oha-members-enhanced { margin:20px 0; }
        .oha-members-header   { margin-bottom:20px; }
        .oha-members-filters  { background:#f9f9f9; padding:15px; border:1px solid #ddd; border-radius:4px; margin-bottom:20px; }
        .oha-filter-form      { display:flex; gap:20px; align-items:flex-end; flex-wrap:wrap; margin-bottom:10px; }
        .filter-group         { display:flex; flex-direction:column; gap:5px; }
        .filter-group label   { font-weight:600; font-size:12px; color:#666; }
        .filter-group select, .filter-group input[type="search"] { padding:6px 10px; border:1px solid #ddd; border-radius:3px; }
        .filter-stats         { padding:10px 0; color:#666; font-size:14px; }
        .member-type-badge    { display:inline-block; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600; text-transform:uppercase; }
        .member-type-oha_member    { background:#d4edda; color:#155724; }
        .member-type-listing_owner { background:#d1ecf1; color:#0c5460; }
        .address-column       { max-width:250px; font-size:13px; }
        .address-display      { line-height:1.4; }
        .card-status-toggle   { display:flex; align-items:center; gap:5px; cursor:pointer; }
        .toggle-label         { font-size:13px; }
        .oha-members-footer   { margin-top:20px; padding-top:20px; border-top:1px solid #ddd; }
        @media (max-width:782px) {
            .oha-filter-form { flex-direction:column; align-items:stretch; }
            .filter-group    { width:100%; }
        }
        </style>
        <?php
    }

    // =========================================================================
    // UNIFIED MEMBER QUERY
    // =========================================================================

    /**
     * Get unified member list from both uwp_account_type and SWPM legacy table.
     *
     * Returned row format:
     *   user_id          int    WP user ID (0 if SWPM-only with no WP account)
     *   display_name     string
     *   user_email       string
     *   user_registered  string
     *   account_type     string  'oha_member' | 'listing_owner' | level-name string
     *   paid_since       string|null
     *   is_upgrade       bool
     *   _source          string  'uwp' | 'swpm' | 'both'
     *   _swpm_member_id  int     SWPM primary-key member_id (0 for uwp-only)
     *   _swpm_address    array|null  SWPM address columns as fallback
     */
    private static function get_members($view, $search = '') {
        global $wpdb;

        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));

        // ── Part 1: UsersWP / new members ──────────────────────────────────
        $uwp_where  = [];
        $uwp_params = [];

        switch ($view) {
            case 'new_paid':
                $uwp_where[]  = "m.meta_value = 'oha_member'";
                $uwp_where[]  = "(u.user_registered >= %s OR (m2.meta_value IS NOT NULL AND m2.meta_value >= %s))";
                $uwp_params[] = $thirty_days_ago;
                $uwp_params[] = $thirty_days_ago;
                break;
            case 'new_listing':
                $uwp_where[]  = "m.meta_value = 'listing_owner'";
                $uwp_where[]  = "u.user_registered >= %s";
                $uwp_params[] = $thirty_days_ago;
                break;
            case 'all_paid':
                $uwp_where[] = "m.meta_value = 'oha_member'";
                break;
            case 'all_listing':
                $uwp_where[] = "m.meta_value = 'listing_owner'";
                break;
            case 'cards_not_sent':
                $uwp_where[] = "m.meta_value = 'oha_member'";
                $uwp_where[] = "NOT EXISTS (
                    SELECT 1 FROM {$wpdb->usermeta} um3
                    WHERE um3.user_id = u.ID
                    AND um3.meta_key = 'oha_card_sent'
                    AND um3.meta_value = '1'
                )";
                break;
            case 'all':
            default:
                break;
        }

        if (!empty($search)) {
            $like         = '%' . $wpdb->esc_like($search) . '%';
            $uwp_where[]  = "(u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s)";
            $uwp_params[] = $like;
            $uwp_params[] = $like;
            $uwp_params[] = $like;
        }

        $uwp_sql = "SELECT DISTINCT u.ID as user_id, u.display_name, u.user_email, u.user_registered,
                    m.meta_value as account_type,
                    m2.meta_value as paid_since
                    FROM {$wpdb->users} u
                    INNER JOIN {$wpdb->usermeta} m  ON u.ID = m.user_id  AND m.meta_key  = 'uwp_account_type'
                    LEFT  JOIN {$wpdb->usermeta} m2 ON u.ID = m2.user_id AND m2.meta_key = 'oha_paid_member_since'";

        if (!empty($uwp_where)) {
            $uwp_sql .= ' WHERE ' . implode(' AND ', $uwp_where);
        }
        $uwp_sql .= ' ORDER BY u.user_registered DESC';

        $uwp_raw = empty($uwp_params)
            ? $wpdb->get_results($uwp_sql, ARRAY_A)
            : $wpdb->get_results($wpdb->prepare($uwp_sql, ...$uwp_params), ARRAY_A);

        $uwp_raw = is_array($uwp_raw) ? $uwp_raw : [];

        // Track covered user_ids and emails so SWPM query can exclude duplicates
        $covered_user_ids = [];
        $covered_emails   = [];
        $uwp_members      = [];

        foreach ($uwp_raw as $row) {
            $uid = intval($row['user_id']);
            $covered_user_ids[] = $uid;
            $covered_emails[]   = strtolower(trim($row['user_email']));

            $registered = $row['user_registered'] ?? '';
            $paid_since = $row['paid_since'] ?: null;
            // Also check oha_membership_paid_date as fallback for paid_since
            if (!$paid_since) {
                $paid_since = get_user_meta($uid, 'oha_membership_paid_date', true) ?: null;
            }

            $is_upgrade = false;
            if ($paid_since && $registered) {
                $is_upgrade = (strtotime($paid_since) - strtotime($registered)) > (7 * DAY_IN_SECONDS);
            }

            $uwp_members[] = [
                'user_id'         => $uid,
                'display_name'    => $row['display_name'],
                'user_email'      => $row['user_email'],
                'user_registered' => $registered,
                'account_type'    => $row['account_type'],
                'paid_since'      => $paid_since,
                'is_upgrade'      => $is_upgrade,
                '_source'         => 'uwp',
                '_swpm_member_id' => 0,
                '_swpm_address'   => null,
            ];
        }

        // ── Part 2: SWPM Legacy members ────────────────────────────────────
        $swpm_members = [];
        $swpm_tbl     = $wpdb->prefix . 'swpm_members_tbl';
        $swpm_exists  = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $swpm_tbl)) === $swpm_tbl);

        if ($swpm_exists) {
            $level_map  = self::get_level_map();
            $oha_level  = (string)($level_map['oha_member']    ?? '10');
            $list_level = (string)($level_map['listing_owners'] ?? '2');

            $swpm_where  = [];
            $swpm_params = [];

            // Level / view filter
            switch ($view) {
                case 'new_paid':
                    $swpm_where[]  = "s.membership_level = %d";
                    $swpm_params[] = intval($oha_level);
                    $swpm_where[]  = "(s.subscription_starts >= %s OR (u.user_registered IS NOT NULL AND u.user_registered >= %s))";
                    $swpm_params[] = $thirty_days_ago;
                    $swpm_params[] = $thirty_days_ago;
                    break;
                case 'new_listing':
                    $swpm_where[]  = "s.membership_level = %d";
                    $swpm_params[] = intval($list_level);
                    $swpm_where[]  = "(s.subscription_starts >= %s OR (u.user_registered IS NOT NULL AND u.user_registered >= %s))";
                    $swpm_params[] = $thirty_days_ago;
                    $swpm_params[] = $thirty_days_ago;
                    break;
                case 'all_paid':
                    $swpm_where[]  = "s.membership_level = %d";
                    $swpm_params[] = intval($oha_level);
                    break;
                case 'all_listing':
                    $swpm_where[]  = "s.membership_level = %d";
                    $swpm_params[] = intval($list_level);
                    break;
                case 'cards_not_sent':
                    $swpm_where[]  = "s.membership_level = %d";
                    $swpm_params[] = intval($oha_level);
                    $swpm_where[]  = "NOT EXISTS (
                        SELECT 1 FROM {$wpdb->usermeta} um_cs
                        WHERE um_cs.user_id = s.wp_user_id
                        AND um_cs.meta_key  = 'oha_card_sent'
                        AND um_cs.meta_value = '1'
                    )";
                    break;
                case 'all':
                default:
                    break;
            }

            // Exclude already-covered users (by WP user_id)
            if (!empty($covered_user_ids)) {
                $ids_safe     = implode(',', array_map('intval', $covered_user_ids));
                $swpm_where[] = "(s.wp_user_id IS NULL OR s.wp_user_id NOT IN ($ids_safe))";
            }

            // Search
            if (!empty($search)) {
                $like          = '%' . $wpdb->esc_like($search) . '%';
                $swpm_where[]  = "(s.first_name LIKE %s OR s.last_name LIKE %s OR s.user_name LIKE %s OR s.email LIKE %s)";
                $swpm_params[] = $like;
                $swpm_params[] = $like;
                $swpm_params[] = $like;
                $swpm_params[] = $like;
            }

            // Check which SWPM columns exist
            $cols_check  = $wpdb->get_results("DESCRIBE {$swpm_tbl}", ARRAY_A);
            $col_names   = array_column($cols_check, 'Field');
            $has_wp_uid  = in_array('wp_user_id', $col_names, true);
            $wp_uid_sel  = $has_wp_uid ? 's.wp_user_id' : '0 as wp_user_id';

            $swpm_sql = "SELECT s.member_id, s.user_name, s.first_name, s.last_name, s.email,
                                s.membership_level, s.account_state, s.subscription_starts,
                                s.address_street, s.address_city, s.address_state, s.address_zipcode, s.country,
                                {$wp_uid_sel},
                                u.ID as wp_uid_verified, u.display_name as wp_display_name,
                                u.user_registered as wp_registered
                         FROM {$swpm_tbl} s
                         LEFT JOIN {$wpdb->users} u ON s.email = u.user_email";

            if (!empty($swpm_where)) {
                $swpm_sql .= ' WHERE ' . implode(' AND ', $swpm_where);
            }
            $swpm_sql .= ' ORDER BY s.member_id DESC LIMIT 3000';

            $swpm_raw = empty($swpm_params)
                ? $wpdb->get_results($swpm_sql, ARRAY_A)
                : $wpdb->get_results($wpdb->prepare($swpm_sql, ...$swpm_params), ARRAY_A);

            $swpm_raw = is_array($swpm_raw) ? $swpm_raw : [];

            foreach ($swpm_raw as $s) {
                // Skip if email already covered by uwp query
                if (in_array(strtolower(trim($s['email'])), $covered_emails, true)) {
                    continue;
                }

                $wp_user_id  = intval($s['wp_uid_verified'] ?? $s['wp_user_id'] ?? 0);
                $display     = trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''));
                if (!$display) {
                    $display = $s['wp_display_name'] ?? $s['user_name'] ?? $s['email'];
                }

                $registered  = $s['wp_registered'] ?? '';
                $paid_since  = $s['subscription_starts'] ?: null;
                // Also check usermeta for paid_since if wp_user_id is known
                if (!$paid_since && $wp_user_id) {
                    $paid_since = get_user_meta($wp_user_id, 'oha_paid_member_since', true) ?: null;
                    if (!$paid_since) {
                        $paid_since = get_user_meta($wp_user_id, 'oha_membership_paid_date', true) ?: null;
                    }
                }

                $is_upgrade  = false;
                if ($paid_since && $registered) {
                    $is_upgrade = (strtotime($paid_since) - strtotime($registered)) > (7 * DAY_IN_SECONDS);
                }

                $account_type = self::get_level_name_from_id((string)($s['membership_level'] ?? ''));

                $swpm_members[] = [
                    'user_id'         => $wp_user_id,
                    'display_name'    => $display,
                    'user_email'      => $s['email'] ?? '',
                    'user_registered' => $registered,
                    'account_type'    => $account_type,
                    'paid_since'      => $paid_since,
                    'is_upgrade'      => $is_upgrade,
                    '_source'         => 'swpm',
                    '_swpm_member_id' => intval($s['member_id']),
                    '_swpm_address'   => [
                        'street'  => $s['address_street']  ?? '',
                        'city'    => $s['address_city']    ?? '',
                        'state'   => $s['address_state']   ?? '',
                        'zipcode' => $s['address_zipcode'] ?? '',
                        'country' => $s['country']         ?? '',
                    ],
                ];
            }
        }

        // ── Part 3: Fallback for _uwp_register_form_id=1 users with no uwp_account_type ──
        // These are OHA member form registrants whose uwp_account_type was never set,
        // so they are invisible to Part 1 (uwp INNER JOIN) and may not be in SWPM (Part 2).
        // Include for any view that shows oha_member records.
        $fb_members = [];
        $include_fb = in_array($view, ['all', 'all_paid', 'new_paid', 'cards_not_sent'], true);

        if ($include_fb) {
            // Collect all WP user IDs already covered by Parts 1 & 2
            $fb_excl_ids = array_unique(array_merge(
                $covered_user_ids,
                array_filter(array_map(fn($m) => $m['user_id'], $swpm_members))
            ));
            // Collect covered emails (for dedup when SWPM wp_user_id=0)
            $fb_excl_emails = array_unique(array_merge(
                $covered_emails,
                array_map(fn($m) => strtolower($m['user_email']), $swpm_members)
            ));

            $fb_where  = [];
            $fb_params = [];

            // Only users who registered via the oha_member UWP form (form_id=1)
            $fb_where[] = "um_form.meta_value = '1'";
            // Exclude users who already have uwp_account_type (already in Part 1)
            $fb_where[] = "NOT EXISTS (
                SELECT 1 FROM {$wpdb->usermeta} um_acct
                WHERE um_acct.user_id = u.ID AND um_acct.meta_key = 'uwp_account_type'
            )";

            // Exclude WP user IDs already covered
            if (!empty($fb_excl_ids)) {
                $ids_safe   = implode(',', array_map('intval', $fb_excl_ids));
                $fb_where[] = "u.ID NOT IN ($ids_safe)";
            }

            if ($view === 'new_paid') {
                $fb_where[]  = "(u.user_registered >= %s OR (paid.meta_value IS NOT NULL AND paid.meta_value >= %s))";
                $fb_params[] = $thirty_days_ago;
                $fb_params[] = $thirty_days_ago;
            }
            if ($view === 'cards_not_sent') {
                $fb_where[] = "NOT EXISTS (
                    SELECT 1 FROM {$wpdb->usermeta} um_cs
                    WHERE um_cs.user_id = u.ID
                    AND um_cs.meta_key = 'oha_card_sent'
                    AND um_cs.meta_value = '1'
                )";
            }
            if (!empty($search)) {
                $like        = '%' . $wpdb->esc_like($search) . '%';
                $fb_where[]  = "(u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s)";
                $fb_params[] = $like;
                $fb_params[] = $like;
                $fb_params[] = $like;
            }

            $fb_sql = "SELECT DISTINCT u.ID as user_id, u.display_name, u.user_email, u.user_registered,
                              paid.meta_value as paid_since
                       FROM {$wpdb->users} u
                       INNER JOIN {$wpdb->usermeta} um_form
                               ON u.ID = um_form.user_id AND um_form.meta_key = '_uwp_register_form_id'
                       LEFT  JOIN {$wpdb->usermeta} paid
                               ON u.ID = paid.user_id AND paid.meta_key = 'oha_paid_member_since'";

            if (!empty($fb_where)) {
                $fb_sql .= ' WHERE ' . implode(' AND ', $fb_where);
            }
            $fb_sql .= ' ORDER BY u.user_registered DESC';

            $fb_raw = empty($fb_params)
                ? $wpdb->get_results($fb_sql, ARRAY_A)
                : $wpdb->get_results($wpdb->prepare($fb_sql, ...$fb_params), ARRAY_A);

            foreach (is_array($fb_raw) ? $fb_raw : [] as $row) {
                $uid = intval($row['user_id']);
                // Skip if already covered by email (handles SWPM rows with user_id=0)
                if (in_array(strtolower($row['user_email']), $fb_excl_emails, true)) {
                    continue;
                }

                $registered = $row['user_registered'] ?? '';
                $paid_since = $row['paid_since'] ?: null;
                if (!$paid_since) {
                    $paid_since = get_user_meta($uid, 'oha_membership_paid_date', true) ?: null;
                }
                $is_upgrade = false;
                if ($paid_since && $registered) {
                    $is_upgrade = (strtotime($paid_since) - strtotime($registered)) > (7 * DAY_IN_SECONDS);
                }

                $fb_members[] = [
                    'user_id'         => $uid,
                    'display_name'    => $row['display_name'],
                    'user_email'      => $row['user_email'],
                    'user_registered' => $registered,
                    'account_type'    => 'oha_member',
                    'paid_since'      => $paid_since,
                    'is_upgrade'      => $is_upgrade,
                    '_source'         => 'uwp',
                    '_swpm_member_id' => 0,
                    '_swpm_address'   => null,
                ];
            }
        }

        return array_merge($uwp_members, $swpm_members, $fb_members);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Read level map from oha_board_hub_settings option.
     * Mirrors the get_level_map() in OHA_Board_Hub class.
     */
    private static function get_level_map() {
        $opt = get_option('oha_board_hub_settings', []);
        return [
            'listing_owners' => $opt['level_listing_owners'] ?? '2',
            'board'          => $opt['level_board']          ?? '4',
            'vendors'        => $opt['level_vendors']        ?? '6',
            'sponsors'       => $opt['level_sponsors']       ?? '8',
            'convention2026' => $opt['level_convention2026'] ?? '6',
            'oha_member'     => $opt['level_oha_member']     ?? '10',
            'webmaster'      => $opt['level_webmaster']      ?? '5',
        ];
    }

    /**
     * Convert a SWPM numeric membership_level to a readable account_type string.
     */
    private static function get_level_name_from_id($level_id) {
        $level_id = (string)$level_id;
        if ($level_id === '') return 'unknown';

        $map = self::get_level_map();
        $names = [
            (string)$map['listing_owners'] => 'listing_owner',
            (string)$map['board']          => 'board_member',
            (string)$map['vendors']        => 'vendor',
            (string)$map['sponsors']       => 'sponsor',
            (string)$map['convention2026'] => 'convention2026',
            (string)$map['oha_member']     => 'oha_member',
            (string)$map['webmaster']      => 'webmaster',
        ];

        return $names[$level_id] ?? ('level_' . $level_id);
    }

    /**
     * Human-readable label for an account_type value.
     */
    private static function get_member_type_label($account_type) {
        $labels = [
            'oha_member'     => __('OHA Member',     'oha-board-hub'),
            'listing_owner'  => __('Listing Owner',  'oha-board-hub'),
            'board_member'   => __('Board Member',   'oha-board-hub'),
            'vendor'         => __('Vendor',         'oha-board-hub'),
            'sponsor'        => __('Sponsor',        'oha-board-hub'),
            'convention2026' => __('Convention 2026','oha-board-hub'),
            'webmaster'      => __('Webmaster',      'oha-board-hub'),
        ];

        // Exact match
        if (isset($labels[$account_type])) {
            return $labels[$account_type];
        }

        // level_N fallback — try to resolve via level map
        if (str_starts_with((string)$account_type, 'level_')) {
            $num = substr($account_type, 6);
            $resolved = self::get_level_name_from_id($num);
            if ($resolved !== 'level_' . $num && isset($labels[$resolved])) {
                return $labels[$resolved];
            }
            return __('Level', 'oha-board-hub') . ' ' . esc_html($num);
        }

        return !empty($account_type) ? esc_html($account_type) : __('Unknown', 'oha-board-hub');
    }

    /**
     * Build a formatted address string for display.
     *
     * Priority:
     * 1. boardhub_address_display usermeta  (set by legacy Board Hub)
     * 2. billing_address_display usermeta   (set by Stripe webhook)
     * 3. billing_address_line1 / billing_city / etc usermeta (Stripe individual fields)
     * 4. address_street / address_city / etc usermeta (legacy Board Hub individual fields)
     * 5. SWPM address array (passed as $swpm_address)
     *
     * @param int        $user_id      WordPress user ID (0 = not available)
     * @param array|null $swpm_address Associative array with keys: street, city, state, zipcode, country
     */
    private static function get_member_address($user_id, $swpm_address = null) {
        if ($user_id > 0) {
            // 1. Legacy Board Hub pre-formatted string
            $display = get_user_meta($user_id, 'boardhub_address_display', true);
            if (!empty($display)) {
                return $display;
            }

            // 2. Stripe pre-formatted string
            $display = get_user_meta($user_id, 'billing_address_display', true);
            if (!empty($display)) {
                return $display;
            }

            // 3. Build from Stripe individual usermeta fields
            $line1   = get_user_meta($user_id, 'billing_address_line1', true);
            $line2   = get_user_meta($user_id, 'billing_address_line2', true);
            $city    = get_user_meta($user_id, 'billing_city', true);
            $state   = get_user_meta($user_id, 'billing_state', true);
            $zip     = get_user_meta($user_id, 'billing_postal_code', true);
            $country = get_user_meta($user_id, 'billing_country', true);

            if (!empty($line1) && !empty($city)) {
                $parts = [trim($line1 . ($line2 ? ' ' . $line2 : ''))];
                $parts[] = trim($city . ', ' . $state . ' ' . $zip);
                if (!empty($country) && strtoupper($country) !== 'US') {
                    $parts[] = $country;
                }
                return implode("\n", array_filter($parts));
            }

            // 4. Build from legacy Board Hub individual usermeta fields
            $street  = get_user_meta($user_id, 'address_street',  true);
            $city    = get_user_meta($user_id, 'address_city',    true);
            $state   = get_user_meta($user_id, 'address_state',   true);
            $zip     = get_user_meta($user_id, 'address_zipcode', true);
            $country = get_user_meta($user_id, 'country',         true);

            if (!empty($street) && !empty($city)) {
                $parts = [$street];
                $parts[] = trim($city . ', ' . $state . ' ' . $zip);
                if (!empty($country) && strtoupper($country) !== 'US') {
                    $parts[] = $country;
                }
                return implode("\n", array_filter($parts));
            }
        }

        // 5. SWPM address columns fallback
        if (!empty($swpm_address)) {
            $street  = $swpm_address['street']  ?? '';
            $city    = $swpm_address['city']    ?? '';
            $state   = $swpm_address['state']   ?? '';
            $zipcode = $swpm_address['zipcode'] ?? '';
            $country = $swpm_address['country'] ?? '';

            $parts = array_filter([
                $street,
                trim($city . ($state ? ', ' . $state : '') . ($zipcode ? ' ' . $zipcode : '')),
                (!empty($country) && strtoupper($country) !== 'US') ? $country : '',
            ]);

            if (!empty($parts)) {
                return implode("\n", $parts);
            }
        }

        return null;
    }

    /**
     * Get card-sent status for a WP user, checking both usermeta and the card table.
     *
     * @param int $user_id  WordPress user ID
     * @return array ['sent' => bool, 'date' => string]
     */
    private static function get_card_status_for_user($user_id) {
        if ($user_id <= 0) {
            return ['sent' => false, 'date' => ''];
        }

        // 1. oha_card_sent usermeta (set by admin form)
        $meta_sent = get_user_meta($user_id, 'oha_card_sent', true);
        if ($meta_sent !== '') {
            $sent = ($meta_sent === '1' || $meta_sent === 'yes' || $meta_sent === 1);
            $date = get_user_meta($user_id, 'oha_card_sent_date', true);
            return ['sent' => $sent, 'date' => $date ?: ''];
        }

        // 2. oha_card_status table (set by frontend toggle)
        global $wpdb;
        $card_tbl = $wpdb->prefix . 'oha_card_status';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $card_tbl)) === $card_tbl) {
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT sent, date FROM {$card_tbl} WHERE member_id = %d LIMIT 1", $user_id),
                ARRAY_A
            );
            if ($row) {
                return ['sent' => (bool)intval($row['sent']), 'date' => $row['date'] ?? ''];
            }
        }

        return ['sent' => false, 'date' => ''];
    }

    // =========================================================================
    // CSV EXPORT
    // =========================================================================

    /**
     * Prevent CSV injection by prefixing values that start with formula-trigger
     * characters (=, +, -, @, tab, CR). Spreadsheet apps hide the leading apostrophe
     * and display the text correctly; raw parsers see a plain string.
     */
    private static function sanitize_csv_value($v) {
        if (!is_string($v) || $v === '') return $v;
        if (preg_match('/^[=+\-@\t\r]/', $v)) return "'" . $v;
        return $v;
    }

    private static function export_csv($members) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=oha-members-' . date('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        fputcsv($output, [
            'ID', 'Name', 'Email', 'Level', 'Address',
            'Registered', 'Paid Since', 'Is Upgrade', 'Card Sent', 'Source',
        ]);

        foreach ($members as $member) {
            $user_id    = intval($member['user_id'] ?? 0);
            $address    = self::get_member_address($user_id, $member['_swpm_address'] ?? null);
            $card_info  = self::get_card_status_for_user($user_id);

            fputcsv($output, [
                $user_id ?: ('SM:' . ($member['_swpm_member_id'] ?? '?')),
                self::sanitize_csv_value($member['display_name'] ?? ''),
                self::sanitize_csv_value($member['user_email']   ?? ''),
                self::sanitize_csv_value(self::get_member_type_label($member['account_type'] ?? '')),
                self::sanitize_csv_value($address ? str_replace("\n", ', ', $address) : ''),
                $member['user_registered'] ? date('Y-m-d', strtotime($member['user_registered'])) : '',
                $member['paid_since']      ? date('Y-m-d', strtotime($member['paid_since']))      : '',
                ($member['is_upgrade'] ?? false) ? 'Yes' : 'No',
                $card_info['sent'] ? 'Yes' : 'No',
                $member['_source'] ?? 'uwp',
            ]);
        }

        fclose($output);
    }
}
