<?php
/**
 * Canonical Members Table — OHA Member List
 *
 * Member query (canonical, v2.6.0+):
 *   _uwp_register_form_id = '1'
 *   Written by userswp_set_paid_member() on every confirmed payment.
 *   Cleared to '4' by userswp_set_free_member() on cancellation.
 *
 * Address priority (per user):
 * 1. billing_address_display usermeta (set by Stripe webhook)
 * 2. billing_address_line1 / billing_city / etc usermeta (Stripe individual fields)
 * 3. address_street / address_city / etc usermeta (legacy Board Hub individual fields)
 *
 * Card-sent status priority:
 * 1. oha_card_sent usermeta (set by admin form)
 * 2. oha_card_status custom table (set by frontend toggle)
 *
 * @package OHA_Board_Hub
 * @since 1.6.0 / updated 2.4.0
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
            $view    = sanitize_text_field($_POST['member_view'] ?? 'cards_not_sent');
            $search  = sanitize_text_field($_POST['member_search'] ?? '');
            $members = self::get_members($view, $search);
            self::export_csv($members);
            exit;
        }

        // Get filter parameters
        $view   = isset($_GET['member_view'])   ? sanitize_text_field($_GET['member_view'])   : 'cards_not_sent';
        $search = isset($_GET['member_search']) ? sanitize_text_field($_GET['member_search']) : '';

        $members = self::get_members($view, $search);

        ?>
        <div class="oha-members-enhanced">
            <div class="oha-members-header">
                <h2><?php esc_html_e('Member Management', 'oha-board-hub'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Displays OHA members using the canonical Stripe-based query. Use the Stripe Sync Migration Tool below to populate the required meta before first use.', 'oha-board-hub'); ?>
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
                            <option value="all_paid"       <?php selected($view, 'all_paid'); ?>><?php esc_html_e('OHA Members',                  'oha-board-hub'); ?></option>
                            <option value="new_paid"       <?php selected($view, 'new_paid'); ?>><?php esc_html_e('New OHA Members (30 days)',    'oha-board-hub'); ?></option>
                            <option value="all_listing"    <?php selected($view, 'all_listing'); ?>><?php esc_html_e('Basic Members',                'oha-board-hub'); ?></option>
                            <option value="new_listing"    <?php selected($view, 'new_listing'); ?>><?php esc_html_e('New Basic Members (30 days)',  'oha-board-hub'); ?></option>
                            <option value="board"          <?php selected($view, 'board'); ?>><?php esc_html_e('Board Members',                  'oha-board-hub'); ?></option>
                            <option value="cards_not_sent" <?php selected($view, 'cards_not_sent'); ?>><?php esc_html_e('Create Membership Cards',     'oha-board-hub'); ?></option>
                            <option value="all"            <?php selected($view, 'all'); ?>><?php esc_html_e('All Users',                      'oha-board-hub'); ?></option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label><?php esc_html_e('Search:', 'oha-board-hub'); ?></label>
                        <input type="search" name="member_search" value="<?php echo esc_attr($search); ?>"
                               placeholder="<?php esc_attr_e('Name, email, username, or Member ID', 'oha-board-hub'); ?>">
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
                            $user_id       = intval($member['user_id'] ?? 0);
                            $email         = $member['user_email']     ?? '';
                            $name          = $member['display_name']   ?? '';
                            $registered    = $member['user_registered'] ?? '';
                            $paid_since    = $member['paid_since']     ?? null;
                            $is_upgrade    = $member['is_upgrade']     ?? false;
                            $source        = $member['_source']        ?? 'uwp';
                            $swpm_mid      = intval($member['_swpm_member_id'] ?? 0);
                            $oha_member_id = $member['oha_member_id']  ?? '';
                            $level_label   = self::get_member_type_label($member['account_type'] ?? '');

                            // Address: usermeta first, then SWPM columns
                            $address = self::get_member_address($user_id, $member['_swpm_address'] ?? null);

                            // Card status: check usermeta then card table
                            $card_info  = self::get_card_status_for_user($user_id);
                            $card_sent  = $card_info['sent'];
                            ?>
                            <tr>
                                <td><?php echo $oha_member_id ? esc_html($oha_member_id) : esc_html($user_id ?: ('SM:' . $swpm_mid)); ?></td>
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
                        <?php esc_html_e('Export to CSV', 'oha-board-hub'); ?>
                    </button>
                </form>
            </div>

            <!-- Stripe Sync Migration Tool -->
            <div class="oha-stripe-sync-section" style="margin-top:30px;padding-top:20px;border-top:2px solid #e0e0e0;">
                <h3 style="margin-bottom:8px;">Stripe Sync Migration Tool</h3>
                <p class="description" style="margin-bottom:15px;">
                    Syncs missing members from Stripe. Run Preview first to review, then Run Sync to apply. The sync matches each active Stripe subscription to a WP user by customer ID or email, then sets <code>_uwp_register_form_id = 1</code> so they appear in the member list.
                </p>
                <div id="oha-sync-controls">
                    <button type="button" id="oha-sync-preview-btn" class="button button-primary">Preview Migration</button>
                    <button type="button" id="oha-sync-commit-btn" class="button button-secondary" style="display:none;margin-left:10px;">Run Sync</button>
                    <span id="oha-sync-spinner" style="display:none;margin-left:10px;color:#555;"><em>Working, please wait...</em></span>
                </div>
                <div id="oha-sync-results" style="display:none;margin-top:20px;">
                    <div id="oha-sync-summary" style="padding:12px;background:#f0f6fc;border:1px solid #c0d9f0;border-radius:4px;margin-bottom:15px;"></div>
                    <div id="oha-sync-green" style="margin-bottom:15px;"></div>
                    <div id="oha-sync-yellow" style="margin-bottom:15px;"></div>
                    <div id="oha-sync-orange" style="margin-bottom:15px;"></div>
                    <div id="oha-sync-commit-result" style="display:none;padding:12px;background:#edfaed;border:1px solid #acdcac;border-radius:4px;"></div>
                </div>
            </div>

            <script>
            (function(){
                var nonce   = '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>';
                var apiBase = '<?php echo esc_js(rest_url('oha/v1/membership/')); ?>';

                function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

                function buildTable(rows, extraHeader) {
                    if (!rows || !rows.length) return '<em style="color:#999;">None</em>';
                    var h = '<table class="wp-list-table widefat fixed striped" style="font-size:13px;"><thead><tr><th style="width:50px;">ID</th><th>Name</th><th>Email</th><th>Source</th>' + (extraHeader||'') + '</tr></thead><tbody>';
                    rows.forEach(function(r){
                        h += '<tr><td>'+r.user_id+'</td><td>'+esc(r.name)+'</td><td>'+esc(r.email)+'</td><td>'+esc(r.source)+'</td>';
                        if (extraHeader && r.stripe_sub) {
                            h += '<td><code>'+esc(r.stripe_sub)+'</code></td>';
                        } else if (extraHeader && r.user_id) {
                            h += '<td><button class="button button-small oha-verify-btn" data-user-id="'+r.user_id+'" data-name="'+esc(r.name)+'">Verify</button></td>';
                        }
                        h += '</tr>';
                    });
                    return h+'</tbody></table>';
                }

                function doFetch(endpoint, body, callback) {
                    document.getElementById('oha-sync-spinner').style.display = 'inline';
                    fetch(apiBase + endpoint, {
                        method: 'POST',
                        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
                        body: body ? JSON.stringify(body) : null,
                        credentials: 'same-origin'
                    }).then(function(r){ return r.json(); }).then(function(data){
                        document.getElementById('oha-sync-spinner').style.display = 'none';
                        callback(data);
                    }).catch(function(e){
                        document.getElementById('oha-sync-spinner').style.display = 'none';
                        alert('Request failed: '+e);
                    });
                }

                document.getElementById('oha-sync-preview-btn').addEventListener('click', function(){
                    doFetch('stripe-sync-preview', null, function(data){
                        var c = data.counts || {};
                        var stripeErrHtml = c.stripe_error
                            ? '<br><strong style="color:#721c24;">Stripe API error: ' + c.stripe_error + '</strong>'
                            : '';
                        document.getElementById('oha-sync-summary').innerHTML =
                            '<strong>Preview complete.</strong>&nbsp; ' +
                            'Old query total: <strong>'+( c.old_total||0)+'</strong> | ' +
                            'New canonical total: <strong>'+(c.new_total||0)+'</strong> | ' +
                            'Stripe pages fetched: <strong>'+(c.stripe_pages||0)+'</strong>' +
                            stripeErrHtml + '<br>' +
                            'Already canonical (green): <strong style="color:#155724;">'+(c.stays_member||0)+'</strong> | ' +
                            'Stripe match found (yellow): <strong style="color:#856404;">'+(c.will_sync||0)+'</strong> | ' +
                            'No Stripe match (orange): <strong style="color:#721c24;">'+(c.needs_review||0)+'</strong>';

                        document.getElementById('oha-sync-green').innerHTML =
                            '<h4 style="color:#155724;margin:0 0 6px;">Already Canonical (no action needed) - '+(data.green||[]).length+'</h4>'+
                            buildTable(data.green, '');
                        document.getElementById('oha-sync-yellow').innerHTML =
                            '<h4 style="color:#856404;margin:10px 0 6px;">Stripe Match Found - Sync Will Fix - '+(data.yellow||[]).length+'</h4>'+
                            buildTable(data.yellow, '<th>Stripe Subscription</th>');
                        document.getElementById('oha-sync-orange').innerHTML =
                            '<h4 style="color:#721c24;margin:10px 0 6px;">No Stripe Match - Needs Manual Review - '+(data.orange||[]).length+'</h4>'+
                            buildTable(data.orange, '<th>Action</th>');

                        document.getElementById('oha-sync-results').style.display = 'block';
                        document.getElementById('oha-sync-commit-btn').style.display = 'inline-block';

                        // Wire verify buttons
                        document.querySelectorAll('.oha-verify-btn').forEach(function(btn){
                            btn.addEventListener('click', function(){
                                var uid  = parseInt(this.getAttribute('data-user-id'), 10);
                                var name = this.getAttribute('data-name');
                                if (!confirm('Mark '+name+' as a board-verified OHA member? This sets oha_membership_verified = 1.')) return;
                                var self = this;
                                doFetch('verify-member', {user_id: uid}, function(resp){
                                    if (resp.success) {
                                        self.textContent = 'Verified';
                                        self.disabled = true;
                                        self.classList.add('button-primary');
                                    } else {
                                        alert('Error: could not verify user.');
                                    }
                                });
                            });
                        });
                    });
                });

                document.getElementById('oha-sync-commit-btn').addEventListener('click', function(){
                    if (!confirm('Run the Stripe sync now?\n\nThis writes stripe_subscription_status and _uwp_register_form_id for all matched users.\n\nReview the preview above before proceeding.')) return;
                    doFetch('stripe-sync-commit', null, function(data){
                        var el = document.getElementById('oha-sync-commit-result');
                        el.innerHTML = '<strong>Sync complete.</strong>&nbsp; ' +
                            'Matched: <strong>'+(data.matched||0)+'</strong> | ' +
                            'Updated: <strong>'+(data.updated||0)+'</strong> | ' +
                            'Skipped (no WP match): <strong>'+(data.skipped||0)+'</strong>';
                        el.style.display = 'block';
                        document.getElementById('oha-sync-commit-btn').style.display = 'none';
                    });
                });
            })();
            </script>
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
        .member-type-board_member  { background:#e8d5f5; color:#5b2c6f; }
        .member-type-other         { background:#f5f5f5; color:#666; }
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
     * Get the member list for a given view.
     *
     * Views:
     *   all_paid / new_paid / cards_not_sent  -- _uwp_register_form_id='1' AND role=subscriber
     *   all_listing / new_listing             -- _uwp_register_form_id='4' AND role=listing_owner
     *   board                                 -- _uwp_register_form_id='2' AND role=oha_board
     *   all                                   -- every user in the WP database
     */
    private static function get_members($view, $search = '') {
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));

        if (in_array($view, ['all_listing', 'new_listing'], true)) {
            return self::get_listing_owner_members($view, $search, $thirty_days_ago);
        }

        if ($view === 'board') {
            return self::get_board_members($search);
        }

        if ($view === 'all') {
            return self::get_all_users($search);
        }

        return self::get_canonical_paid_members($view, $search, $thirty_days_ago);
    }

    /**
     * OHA member query.
     * Requires BOTH: _uwp_register_form_id = '1' AND WP role = subscriber.
     * View filters applied: new_paid (30-day window), cards_not_sent (no card sent).
     */
    private static function get_canonical_paid_members($view, $search, $thirty_days_ago) {
        global $wpdb;
        $cap_key = $wpdb->prefix . 'capabilities';

        // OHA member: oha_member_id assigned AND form_id = 1 AND WP role = subscriber
        $canonical = "EXISTS (
            SELECT 1 FROM {$wpdb->usermeta} om
            WHERE om.user_id = u.ID
            AND om.meta_key = 'oha_member_id'
        )
        AND EXISTS (
            SELECT 1 FROM {$wpdb->usermeta} mf
            WHERE mf.user_id = u.ID
            AND mf.meta_key = '_uwp_register_form_id'
            AND mf.meta_value = '1'
        )
        AND EXISTS (
            SELECT 1 FROM {$wpdb->usermeta} mr
            WHERE mr.user_id = u.ID
            AND mr.meta_key = '{$cap_key}'
            AND mr.meta_value LIKE '%\"subscriber\"%'
        )";

        $where  = [$canonical];
        $params = [];

        // For cards_not_sent: check both usermeta and card status table
        $card_tbl        = $wpdb->prefix . 'oha_card_status';
        $card_tbl_exists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $card_tbl)) === $card_tbl);

        switch ($view) {
            case 'new_paid':
                $where[]  = "(u.user_registered >= %s OR EXISTS (
                    SELECT 1 FROM {$wpdb->usermeta} um_ps
                    WHERE um_ps.user_id = u.ID
                    AND um_ps.meta_key = 'oha_paid_member_since'
                    AND um_ps.meta_value >= %s
                ))";
                $params[] = $thirty_days_ago;
                $params[] = $thirty_days_ago;
                break;
            case 'cards_not_sent':
                $where[] = "NOT EXISTS (
                    SELECT 1 FROM {$wpdb->usermeta} um_cs
                    WHERE um_cs.user_id = u.ID AND um_cs.meta_key = 'oha_card_sent' AND um_cs.meta_value = '1'
                )";
                if ($card_tbl_exists) {
                    $where[] = "NOT EXISTS (
                        SELECT 1 FROM {$card_tbl} cs_tbl
                        WHERE cs_tbl.member_id = u.ID AND cs_tbl.sent = 1
                    )";
                }
                break;
            case 'all_paid':
            case 'all':
            default:
                break;
        }

        if (!empty($search) && ctype_digit($search)) {
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $where[]  = "(u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s OR u.ID = %d OR EXISTS (SELECT 1 FROM {$wpdb->usermeta} si WHERE si.user_id = u.ID AND si.meta_key = 'oha_member_id' AND si.meta_value = %s))";
            $params[] = $like; $params[] = $like; $params[] = $like;
            $params[] = intval($search);
            $params[] = $search;
        } elseif (!empty($search)) {
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $where[]  = "(u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s)";
            $params[] = $like; $params[] = $like; $params[] = $like;
        }

        $sql = "SELECT u.ID as user_id, u.display_name, u.user_email, u.user_registered,
                    (SELECT meta_value FROM {$wpdb->usermeta}
                     WHERE user_id = u.ID AND meta_key = 'oha_paid_member_since'
                     LIMIT 1) as paid_since,
                    (SELECT meta_value FROM {$wpdb->usermeta}
                     WHERE user_id = u.ID AND meta_key = 'oha_member_id'
                     LIMIT 1) as oha_member_id
                FROM {$wpdb->users} u";

        $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY u.user_registered DESC';

        $raw = empty($params)
            ? $wpdb->get_results($sql, ARRAY_A)
            : $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        $members = [];
        foreach ((array) $raw as $row) {
            $uid        = intval($row['user_id']);
            $registered = $row['user_registered'] ?? '';
            $paid_since = $row['paid_since'] ?: null;
            if (!$paid_since) {
                $paid_since = get_user_meta($uid, 'oha_membership_paid_date', true) ?: null;
            }
            $is_upgrade = false;
            if ($paid_since && $registered) {
                $is_upgrade = (strtotime($paid_since) - strtotime($registered)) > (7 * DAY_IN_SECONDS);
            }
            $members[] = [
                'user_id'         => $uid,
                'display_name'    => $row['display_name'],
                'user_email'      => $row['user_email'],
                'user_registered' => $registered,
                'account_type'    => 'oha_member',
                'paid_since'      => $paid_since,
                'is_upgrade'      => $is_upgrade,
                'oha_member_id'   => $row['oha_member_id'] ?: '',
                '_source'         => 'canonical',
                '_swpm_member_id' => 0,
                '_swpm_address'   => null,
            ];
        }

        return $members;
    }

    /**
     * Basic member query.
     * Requires BOTH: _uwp_register_form_id = '4' AND WP role = listing_owner.
     */
    private static function get_listing_owner_members($view, $search, $thirty_days_ago) {
        global $wpdb;
        $cap_key = $wpdb->prefix . 'capabilities';
        $where   = [
            "EXISTS (
                SELECT 1 FROM {$wpdb->usermeta} om
                WHERE om.user_id = u.ID
                AND om.meta_key = 'oha_member_id'
            )",
            "EXISTS (
                SELECT 1 FROM {$wpdb->usermeta} mf
                WHERE mf.user_id = u.ID
                AND mf.meta_key = '_uwp_register_form_id'
                AND mf.meta_value = '4'
            )",
            "EXISTS (
                SELECT 1 FROM {$wpdb->usermeta} mr
                WHERE mr.user_id = u.ID
                AND mr.meta_key = '{$cap_key}'
                AND mr.meta_value LIKE '%\"listing_owner\"%'
            )",
        ];
        $params = [];

        if ($view === 'new_listing') {
            $where[]  = "u.user_registered >= %s";
            $params[] = $thirty_days_ago;
        }

        if (!empty($search) && ctype_digit($search)) {
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $where[]  = "(u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s OR u.ID = %d OR EXISTS (SELECT 1 FROM {$wpdb->usermeta} si WHERE si.user_id = u.ID AND si.meta_key = 'oha_member_id' AND si.meta_value = %s))";
            $params[] = $like; $params[] = $like; $params[] = $like;
            $params[] = intval($search);
            $params[] = $search;
        } elseif (!empty($search)) {
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $where[]  = "(u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s)";
            $params[] = $like; $params[] = $like; $params[] = $like;
        }

        $sql = "SELECT u.ID as user_id, u.display_name, u.user_email, u.user_registered,
                    (SELECT meta_value FROM {$wpdb->usermeta}
                     WHERE user_id = u.ID AND meta_key = 'oha_paid_member_since'
                     LIMIT 1) as paid_since,
                    (SELECT meta_value FROM {$wpdb->usermeta}
                     WHERE user_id = u.ID AND meta_key = 'oha_member_id'
                     LIMIT 1) as oha_member_id
                FROM {$wpdb->users} u
                WHERE " . implode(' AND ', $where) . "
                ORDER BY u.user_registered DESC";

        $raw = empty($params)
            ? $wpdb->get_results($sql, ARRAY_A)
            : $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        $members = [];
        foreach ((array) $raw as $row) {
            $uid        = intval($row['user_id']);
            $registered = $row['user_registered'] ?? '';
            $paid_since = $row['paid_since'] ?: null;
            $is_upgrade = false;
            if ($paid_since && $registered) {
                $is_upgrade = (strtotime($paid_since) - strtotime($registered)) > (7 * DAY_IN_SECONDS);
            }
            $members[] = [
                'user_id'         => $uid,
                'display_name'    => $row['display_name'],
                'user_email'      => $row['user_email'],
                'user_registered' => $registered,
                'account_type'    => 'listing_owner',
                'paid_since'      => $paid_since,
                'is_upgrade'      => $is_upgrade,
                'oha_member_id'   => $row['oha_member_id'] ?: '',
                '_source'         => 'uwp',
                '_swpm_member_id' => 0,
                '_swpm_address'   => null,
            ];
        }
        return $members;
    }

    /**
     * Board member query.
     * Requires BOTH: _uwp_register_form_id = '2' AND WP role = oha_board.
     */
    private static function get_board_members($search = '') {
        global $wpdb;
        $cap_key = $wpdb->prefix . 'capabilities';

        $where  = [
            "EXISTS (
                SELECT 1 FROM {$wpdb->usermeta} mf
                WHERE mf.user_id = u.ID
                AND mf.meta_key = '_uwp_register_form_id'
                AND mf.meta_value = '2'
            )",
            "EXISTS (
                SELECT 1 FROM {$wpdb->usermeta} mr
                WHERE mr.user_id = u.ID
                AND mr.meta_key = '{$cap_key}'
                AND mr.meta_value LIKE '%\"oha_board\"%'
            )",
        ];
        $params = [];

        if (!empty($search) && ctype_digit($search)) {
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $where[]  = "(u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s OR u.ID = %d OR EXISTS (SELECT 1 FROM {$wpdb->usermeta} si WHERE si.user_id = u.ID AND si.meta_key = 'oha_member_id' AND si.meta_value = %s))";
            $params[] = $like; $params[] = $like; $params[] = $like;
            $params[] = intval($search);
            $params[] = $search;
        } elseif (!empty($search)) {
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $where[]  = "(u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s)";
            $params[] = $like; $params[] = $like; $params[] = $like;
        }

        $sql = "SELECT u.ID as user_id, u.display_name, u.user_email, u.user_registered,
                    (SELECT meta_value FROM {$wpdb->usermeta}
                     WHERE user_id = u.ID AND meta_key = 'oha_paid_member_since'
                     LIMIT 1) as paid_since,
                    (SELECT meta_value FROM {$wpdb->usermeta}
                     WHERE user_id = u.ID AND meta_key = 'oha_member_id'
                     LIMIT 1) as oha_member_id
                FROM {$wpdb->users} u
                WHERE " . implode(' AND ', $where) . "
                ORDER BY u.display_name ASC";

        $raw = empty($params)
            ? $wpdb->get_results($sql, ARRAY_A)
            : $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        $members = [];
        foreach ((array) $raw as $row) {
            $uid = intval($row['user_id']);
            $members[] = [
                'user_id'         => $uid,
                'display_name'    => $row['display_name'],
                'user_email'      => $row['user_email'],
                'user_registered' => $row['user_registered'] ?? '',
                'account_type'    => 'board_member',
                'paid_since'      => $row['paid_since'] ?: null,
                'is_upgrade'      => false,
                'oha_member_id'   => $row['oha_member_id'] ?: '',
                '_source'         => 'canonical',
                '_swpm_member_id' => 0,
                '_swpm_address'   => null,
            ];
        }

        return $members;
    }

    /**
     * All users query — every user in the WP database.
     * account_type is inferred from form_id and WP capabilities.
     */
    private static function get_all_users($search = '') {
        global $wpdb;
        $cap_key = $wpdb->prefix . 'capabilities';

        $where  = [];
        $params = [];

        if (!empty($search) && ctype_digit($search)) {
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $where[]  = "(u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s OR u.ID = %d OR EXISTS (SELECT 1 FROM {$wpdb->usermeta} si WHERE si.user_id = u.ID AND si.meta_key = 'oha_member_id' AND si.meta_value = %s))";
            $params[] = $like; $params[] = $like; $params[] = $like;
            $params[] = intval($search);
            $params[] = $search;
        } elseif (!empty($search)) {
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $where[]  = "(u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s)";
            $params[] = $like; $params[] = $like; $params[] = $like;
        }

        $sql = "SELECT u.ID as user_id, u.display_name, u.user_email, u.user_registered,
                    (SELECT meta_value FROM {$wpdb->usermeta}
                     WHERE user_id = u.ID AND meta_key = '_uwp_register_form_id'
                     LIMIT 1) as form_id,
                    (SELECT meta_value FROM {$wpdb->usermeta}
                     WHERE user_id = u.ID AND meta_key = '{$cap_key}'
                     LIMIT 1) as capabilities,
                    (SELECT meta_value FROM {$wpdb->usermeta}
                     WHERE user_id = u.ID AND meta_key = 'oha_paid_member_since'
                     LIMIT 1) as paid_since,
                    (SELECT meta_value FROM {$wpdb->usermeta}
                     WHERE user_id = u.ID AND meta_key = 'oha_member_id'
                     LIMIT 1) as oha_member_id
                FROM {$wpdb->users} u";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY u.user_registered DESC';

        $raw = empty($params)
            ? $wpdb->get_results($sql, ARRAY_A)
            : $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        $members = [];
        foreach ((array) $raw as $row) {
            $uid        = intval($row['user_id']);
            $registered = $row['user_registered'] ?? '';
            $paid_since = $row['paid_since'] ?: null;
            $form_id    = $row['form_id'] ?? '';
            $caps       = $row['capabilities'] ?? '';
            $is_upgrade = false;
            if ($paid_since && $registered) {
                $is_upgrade = (strtotime($paid_since) - strtotime($registered)) > (7 * DAY_IN_SECONDS);
            }

            if ($form_id === '1' && strpos($caps, '"subscriber"') !== false) {
                $account_type = 'oha_member';
            } elseif ($form_id === '4' && strpos($caps, '"listing_owner"') !== false) {
                $account_type = 'listing_owner';
            } elseif ($form_id === '2' && strpos($caps, '"oha_board"') !== false) {
                $account_type = 'board_member';
            } else {
                $account_type = 'other';
            }

            $members[] = [
                'user_id'         => $uid,
                'display_name'    => $row['display_name'],
                'user_email'      => $row['user_email'],
                'user_registered' => $registered,
                'account_type'    => $account_type,
                'paid_since'      => $paid_since,
                'is_upgrade'      => $is_upgrade,
                'oha_member_id'   => $row['oha_member_id'] ?: '',
                '_source'         => 'canonical',
                '_swpm_member_id' => 0,
                '_swpm_address'   => null,
            ];
        }

        return $members;
    }

    // wp_swpm_members_tbl is no longer read by any query in this class.
    // It is accessed once during the migration preview via the REST endpoint
    // rest_stripe_sync_preview() in oha-board-hub-suite.php.


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
            'listing_owner'  => __('Basic Member',   'oha-board-hub'),
            'board_member'   => __('Board Member',   'oha-board-hub'),
            'vendor'         => __('Vendor',         'oha-board-hub'),
            'sponsor'        => __('Sponsor',        'oha-board-hub'),
            'convention2026' => __('Convention 2026','oha-board-hub'),
            'webmaster'      => __('Webmaster',      'oha-board-hub'),
            'other'          => __('Other',          'oha-board-hub'),
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
                $member['oha_member_id'] ?: ($user_id ?: ('SM:' . ($member['_swpm_member_id'] ?? '?'))),
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
