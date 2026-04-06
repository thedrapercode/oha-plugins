<?php
/**
 * Enhanced Members Table - For OHA MU Plugin System
 *
 * Works with:
 * - _uwp_register_form_id: 1 (paid), 4 (free)
 * - Roles: subscriber (paid), listing_owner (free)
 * - oha_paid_member_since: timestamp when became paid
 *
 * @package OHA_Board_Hub
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OHA Enhanced Members Table class
 */
class OHA_Members_Table {
    
    /**
     * Render the enhanced members table
     */
    public static function render() {
        global $wpdb;
        
        // Handle card status updates
        if (isset($_POST['oha_update_card_status']) && 
            isset($_POST['oha_card_status_nonce']) &&
            wp_verify_nonce($_POST['oha_card_status_nonce'], 'oha_update_card_status')) {
            
            $user_id = intval($_POST['user_id']);
            $card_sent = isset($_POST['card_sent']) ? 1 : 0;
            
            update_user_meta($user_id, 'oha_card_sent', $card_sent);
            
            if (class_exists('OHA_Logger')) {
                OHA_Logger::info('Card status updated', [
                    'user_id' => $user_id,
                    'card_sent' => $card_sent
                ]);
            }
            
            echo '<div class="notice notice-success"><p>' . 
                 esc_html__('Card status updated successfully.', 'oha-board-hub') . 
                 '</p></div>';
        }
        
        // Handle CSV export
        if (isset($_POST['oha_export_members_csv']) && 
            isset($_POST['oha_export_nonce']) &&
            wp_verify_nonce($_POST['oha_export_nonce'], 'oha_export_members')) {
            
            $view = sanitize_text_field($_POST['member_view'] ?? 'new_paid');
            $search = sanitize_text_field($_POST['member_search'] ?? '');
            $members = self::get_members($view, $search);
            
            self::export_csv($members);
            exit;
        }
        
        // Get filter parameters
        $view = isset($_GET['member_view']) ? sanitize_text_field($_GET['member_view']) : 'new_paid';
        $search = isset($_GET['member_search']) ? sanitize_text_field($_GET['member_search']) : '';
        
        // Build query based on view
        $members = self::get_members($view, $search);
        
        ?>
        <div class="oha-members-enhanced">
            <div class="oha-members-header">
                <h2><?php esc_html_e('Member Management', 'oha-board-hub'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Manage members and track membership card mailings. Default view shows new paid members from the last 30 days.', 'oha-board-hub'); ?>
                </p>
            </div>
            
            <!-- Filters -->
            <div class="oha-members-filters">
                <form method="get" class="oha-filter-form">
                    <?php
                    // Preserve page parameter
                    if (isset($_GET['page'])) {
                        echo '<input type="hidden" name="page" value="' . esc_attr($_GET['page']) . '">';
                    }
                    if (isset($_GET['tab'])) {
                        echo '<input type="hidden" name="tab" value="' . esc_attr($_GET['tab']) . '">';
                    }
                    ?>
                    
                    <div class="filter-group">
                        <label><?php esc_html_e('View:', 'oha-board-hub'); ?></label>
                        <select name="member_view" onchange="this.form.submit()">
                            <option value="new_paid" <?php selected($view, 'new_paid'); ?>>
                                <?php esc_html_e('New Paid Members (30 days)', 'oha-board-hub'); ?>
                            </option>
                            <option value="new_listing" <?php selected($view, 'new_listing'); ?>>
                                <?php esc_html_e('New Listing Owners (30 days)', 'oha-board-hub'); ?>
                            </option>
                            <option value="all_paid" <?php selected($view, 'all_paid'); ?>>
                                <?php esc_html_e('All Paid Members', 'oha-board-hub'); ?>
                            </option>
                            <option value="all_listing" <?php selected($view, 'all_listing'); ?>>
                                <?php esc_html_e('All Listing Owners', 'oha-board-hub'); ?>
                            </option>
                            <option value="cards_not_sent" <?php selected($view, 'cards_not_sent'); ?>>
                                <?php esc_html_e('Cards Not Sent', 'oha-board-hub'); ?>
                            </option>
                            <option value="all" <?php selected($view, 'all'); ?>>
                                <?php esc_html_e('All Members', 'oha-board-hub'); ?>
                            </option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><?php esc_html_e('Search:', 'oha-board-hub'); ?></label>
                        <input type="search" 
                               name="member_search" 
                               value="<?php echo esc_attr($search); ?>" 
                               placeholder="<?php esc_attr_e('Name, email, username...', 'oha-board-hub'); ?>">
                        <button type="submit" class="button">
                            <?php esc_html_e('Search', 'oha-board-hub'); ?>
                        </button>
                        <?php if (!empty($search)): ?>
                            <a href="?page=<?php echo esc_attr($_GET['page'] ?? ''); ?>&tab=<?php echo esc_attr($_GET['tab'] ?? ''); ?>&member_view=<?php echo esc_attr($view); ?>" 
                               class="button">
                                <?php esc_html_e('Clear', 'oha-board-hub'); ?>
                            </a>
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
                        <th style="width: 50px;"><?php esc_html_e('ID', 'oha-board-hub'); ?></th>
                        <th><?php esc_html_e('Name', 'oha-board-hub'); ?></th>
                        <th><?php esc_html_e('Email', 'oha-board-hub'); ?></th>
                        <th><?php esc_html_e('Member Type', 'oha-board-hub'); ?></th>
                        <th><?php esc_html_e('Address', 'oha-board-hub'); ?></th>
                        <th><?php esc_html_e('Registered', 'oha-board-hub'); ?></th>
                        <th><?php esc_html_e('Became Paid', 'oha-board-hub'); ?></th>
                        <th><?php esc_html_e('Card Sent', 'oha-board-hub'); ?></th>
                        <th><?php esc_html_e('Actions', 'oha-board-hub'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px;">
                                <em><?php esc_html_e('No members found matching your criteria.', 'oha-board-hub'); ?></em>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($members as $member): ?>
                            <?php
                            $user = get_userdata($member['user_id']);
                            if (!$user) continue;
                            
                            $member_type = self::get_member_type_label($member['account_type']);
                            $address = self::get_member_address($member['user_id']);
                            $card_sent = get_user_meta($member['user_id'], 'oha_card_sent', true);
                            $paid_since = $member['paid_since'] ?? null;
                            $is_upgrade = $member['is_upgrade'] ?? false;
                            ?>
                            <tr>
                                <td><?php echo intval($member['user_id']); ?></td>
                                <td>
                                    <strong><?php echo esc_html($member['display_name']); ?></strong>
                                    <br>
                                    <small><?php echo esc_html($user->user_login); ?></small>
                                </td>
                                <td>
                                    <a href="mailto:<?php echo esc_attr($user->user_email); ?>">
                                        <?php echo esc_html($user->user_email); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="member-type-badge member-type-<?php echo esc_attr($member['account_type']); ?>">
                                        <?php echo esc_html($member_type); ?>
                                    </span>
                                </td>
                                <td class="address-column">
                                    <?php if ($address): ?>
                                        <div class="address-display">
                                            <?php echo nl2br(esc_html($address)); ?>
                                        </div>
                                    <?php else: ?>
                                        <em style="color: #999;">
                                            <?php esc_html_e('No address', 'oha-board-hub'); ?>
                                        </em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo esc_html(date('M j, Y', strtotime($user->user_registered))); ?>
                                </td>
                                <td>
                                    <?php if ($paid_since): ?>
                                        <span style="color: #2271b1;">
                                            <?php echo esc_html(date('M j, Y', strtotime($paid_since))); ?>
                                        </span>
                                        <?php if ($is_upgrade): ?>
                                            <br>
                                            <small style="color: #d63638;">
                                                <?php esc_html_e('⬆ Upgraded', 'oha-board-hub'); ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #999;">
                                            <?php echo $member['account_type'] === 'oha_member' ? esc_html__('New member', 'oha-board-hub') : '—'; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('oha_update_card_status', 'oha_card_status_nonce'); ?>
                                        <input type="hidden" name="user_id" value="<?php echo intval($member['user_id']); ?>">
                                        <input type="hidden" name="oha_update_card_status" value="1">
                                        <label class="card-status-toggle">
                                            <input type="checkbox" 
                                                   name="card_sent" 
                                                   value="1"
                                                   <?php checked($card_sent, 1); ?>
                                                   onchange="this.form.submit()">
                                            <span class="toggle-label">
                                                <?php echo $card_sent ? esc_html__('✓ Sent', 'oha-board-hub') : esc_html__('Not Sent', 'oha-board-hub'); ?>
                                            </span>
                                        </label>
                                    </form>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $member['user_id']); ?>" 
                                       class="button button-small">
                                        <?php esc_html_e('Edit', 'oha-board-hub'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Export Options -->
            <div class="oha-members-footer">
                <form method="post" style="display: inline-block;">
                    <?php wp_nonce_field('oha_export_members', 'oha_export_nonce'); ?>
                    <input type="hidden" name="member_view" value="<?php echo esc_attr($view); ?>">
                    <input type="hidden" name="member_search" value="<?php echo esc_attr($search); ?>">
                    <button type="submit" name="oha_export_members_csv" class="button button-secondary">
                        📥 <?php esc_html_e('Export to CSV', 'oha-board-hub'); ?>
                    </button>
                </form>
            </div>
        </div>
        
        <style>
        .oha-members-enhanced {
            margin: 20px 0;
        }
        
        .oha-members-header {
            margin-bottom: 20px;
        }
        
        .oha-members-filters {
            background: #f9f9f9;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .oha-filter-form {
            display: flex;
            gap: 20px;
            align-items: flex-end;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-weight: 600;
            font-size: 12px;
            color: #666;
        }
        
        .filter-group select,
        .filter-group input[type="search"] {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        
        .filter-stats {
            padding: 10px 0;
            color: #666;
            font-size: 14px;
        }
        
        .member-type-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .member-type-oha_member {
            background: #d4edda;
            color: #155724;
        }
        
        .member-type-listing_owner {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .address-column {
            max-width: 250px;
            font-size: 13px;
        }
        
        .address-display {
            line-height: 1.4;
        }
        
        .card-status-toggle {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }
        
        .card-status-toggle input[type="checkbox"] {
            margin: 0;
        }
        
        .toggle-label {
            font-size: 13px;
        }
        
        .oha-members-footer {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        
        @media (max-width: 782px) {
            .oha-filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                width: 100%;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Get members based on view filter
     *
     * @param string $view View type
     * @param string $search Search term
     * @return array Members data
     */
    private static function get_members($view, $search = '') {
        global $wpdb;
        
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        // Base query using uwp_account_type (this is what UsersWP actually uses!)
        $sql = "SELECT DISTINCT u.ID as user_id, u.display_name, u.user_email, u.user_registered,
                m.meta_value as account_type,
                m2.meta_value as paid_since
                FROM {$wpdb->users} u
                INNER JOIN {$wpdb->usermeta} m ON u.ID = m.user_id AND m.meta_key = 'uwp_account_type'
                LEFT JOIN {$wpdb->usermeta} m2 ON u.ID = m2.user_id AND m2.meta_key = 'oha_paid_member_since'";
        
        $where = [];
        $params = [];
        
        // Apply view filter
        switch ($view) {
            case 'new_paid':
                // Paid members (oha_member) - registered OR became paid in last 30 days
                $where[] = "m.meta_value = 'oha_member'";
                $where[] = "(u.user_registered >= %s OR (m2.meta_value IS NOT NULL AND m2.meta_value >= %s))";
                $params[] = $thirty_days_ago;
                $params[] = $thirty_days_ago;
                break;
                
            case 'new_listing':
                // Free members (listing_owner) registered in last 30 days
                $where[] = "m.meta_value = 'listing_owner'";
                $where[] = "u.user_registered >= %s";
                $params[] = $thirty_days_ago;
                break;
                
            case 'all_paid':
                // All paid members
                $where[] = "m.meta_value = 'oha_member'";
                break;
                
            case 'all_listing':
                // All free members
                $where[] = "m.meta_value = 'listing_owner'";
                break;
                
            case 'cards_not_sent':
                // Paid members without card sent flag
                $where[] = "m.meta_value = 'oha_member'";
                $where[] = "NOT EXISTS (
                    SELECT 1 FROM {$wpdb->usermeta} um3
                    WHERE um3.user_id = u.ID
                    AND um3.meta_key = 'oha_card_sent'
                    AND um3.meta_value = '1'
                )";
                break;
                
            case 'all':
            default:
                // No additional filter
                break;
        }
        
        // Apply search
        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = "(u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        
        // Build final query
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " ORDER BY u.user_registered DESC";
        
        // Prepare and execute
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        // Detect upgrades: paid_since is more than 7 days after registration
        foreach ($results as &$member) {
            if ($member['paid_since']) {
                $registered = strtotime($member['user_registered']);
                $paid = strtotime($member['paid_since']);
                
                // If became paid more than 7 days after registration, it's an upgrade
                if (($paid - $registered) > (7 * 24 * 60 * 60)) {
                    $member['is_upgrade'] = true;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Get member type label
     *
     * @param string $account_type Account type (oha_member or listing_owner)
     * @return string Label
     */
    private static function get_member_type_label($account_type) {
        $labels = [
            'oha_member' => __('OHA Member', 'oha-board-hub'),
            'listing_owner' => __('Listing Owner', 'oha-board-hub'),
        ];
        
        return $labels[$account_type] ?? __('Unknown', 'oha-board-hub');
    }
    
    /**
     * Get member address from Stripe data
     *
     * @param int $user_id User ID
     * @return string|null Formatted address
     */
    private static function get_member_address($user_id) {
        // Try pre-formatted address first
        $address = get_user_meta($user_id, 'billing_address_display', true);
        
        if (!empty($address)) {
            return $address;
        }
        
        // Build from individual fields
        $line1 = get_user_meta($user_id, 'billing_address_line1', true);
        $line2 = get_user_meta($user_id, 'billing_address_line2', true);
        $city = get_user_meta($user_id, 'billing_city', true);
        $state = get_user_meta($user_id, 'billing_state', true);
        $zip = get_user_meta($user_id, 'billing_postal_code', true);
        $country = get_user_meta($user_id, 'billing_country', true);
        
        if (empty($line1) || empty($city)) {
            return null;
        }
        
        $address_parts = [$line1];
        
        if (!empty($line2)) {
            $address_parts[] = $line2;
        }
        
        $address_parts[] = $city . ', ' . $state . ' ' . $zip;
        
        if (!empty($country) && $country !== 'US') {
            $address_parts[] = $country;
        }
        
        return implode("\n", $address_parts);
    }
    
    /**
     * Export members to CSV
     *
     * @param array $members Members array
     */
    private static function export_csv($members) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=oha-members-' . date('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        fputcsv($output, [
            'ID',
            'Name',
            'Email',
            'Username',
            'Member Type',
            'Address',
            'Registered',
            'Became Paid',
            'Is Upgrade',
            'Card Sent'
        ]);
        
        foreach ($members as $member) {
            $user = get_userdata($member['user_id']);
            if (!$user) continue;
            
            $address = self::get_member_address($member['user_id']);
            $card_sent = get_user_meta($member['user_id'], 'oha_card_sent', true);
            
            fputcsv($output, [
                $member['user_id'],
                $member['display_name'],
                $user->user_email,
                $user->user_login,
                self::get_member_type_label($member['account_type']),
                $address ? str_replace("\n", ', ', $address) : '',
                date('Y-m-d', strtotime($user->user_registered)),
                $member['paid_since'] ? date('Y-m-d', strtotime($member['paid_since'])) : '',
                ($member['is_upgrade'] ?? false) ? 'Yes' : 'No',
                $card_sent ? 'Yes' : 'No'
            ]);
        }
        
        fclose($output);
    }
}
