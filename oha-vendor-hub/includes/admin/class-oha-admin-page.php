<?php
/**
 * Admin Page
 *
 * Main admin interface with tabbed navigation and security
 *
 * @package OHA_Board_Hub
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OHA Admin Page class
 */
class OHA_Admin_Page {
    
    /**
     * Current tab
     *
     * @var string
     */
    private $current_tab;
    
    /**
     * Available tabs
     *
     * @var array
     */
    private $tabs;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
        
        $this->tabs = [
            'dashboard' => __('Dashboard', 'oha-board-hub'),
            'membership' => __('Membership', 'oha-board-hub'),
            'boardhub' => __('Board Hub Settings', 'oha-board-hub'),
            'stripe' => __('Stripe Settings', 'oha-board-hub'),
            'vendor' => __('Convention 2026', 'oha-board-hub'),
            'webhooks' => __('Webhooks', 'oha-board-hub'),
            'notifications' => __('Board Notifications', 'oha-board-hub'),
            'tools' => __('Tools', 'oha-board-hub'),
            'debug' => __('Debug', 'oha-board-hub'),
            'discounts' => __('Haunt Discounts', 'oha-board-hub'),
        ];
    }
    
    /**
     * Render admin page
     */
    public function render() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php $this->render_tabs(); ?>
            
            <div class="oha-admin-content">
                <?php $this->render_tab_content(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render tab navigation
     */
    private function render_tabs() {
        ?>
        <h2 class="nav-tab-wrapper">
            <?php foreach ($this->tabs as $tab_key => $tab_label) : ?>
                <?php
                $active_class = ($tab_key === $this->current_tab) ? ' nav-tab-active' : '';
                $tab_url = add_query_arg('tab', $tab_key, menu_page_url(OHA_BHS_MENU, false));
                ?>
                <a href="<?php echo esc_url($tab_url); ?>" 
                   class="nav-tab<?php echo esc_attr($active_class); ?>">
                    <?php echo esc_html($tab_label); ?>
                </a>
            <?php endforeach; ?>
        </h2>
        <?php
    }
    
    /**
     * Render tab content
     */
    private function render_tab_content() {
        ?>
        <div class="oha-tab-content">
            <?php
            switch ($this->current_tab) {
                case 'dashboard':
                    $this->render_dashboard_tab();
                    break;
                case 'membership':
                    $this->render_membership_tab();
                    break;
                case 'boardhub':
                    $this->render_boardhub_tab();
                    break;
                case 'stripe':
                    $this->render_stripe_tab();
                    break;
                case 'vendor':
                    $this->render_vendor_tab();
                    break;
                case 'webhooks':
                    $this->render_webhooks_tab();
                    break;
                case 'notifications':
                    $this->render_notifications_tab();
                    break;
                case 'tools':
                    $this->render_tools_tab();
                    break;
                case 'debug':
                    $this->render_debug_tab();
                    break;
                case 'discounts':
                    $this->render_discounts_tab();
                    break;
                default:
                    $this->render_dashboard_tab();
            }
            ?>
        </div>
        <?php
    }
    
    /**
     * Render Dashboard tab with statistics
     */
    private function render_dashboard_tab() {
        ?>
        <div class="oha-dashboard">
            <h2><?php esc_html_e('OHA Board Dashboard', 'oha-board-hub'); ?></h2>
            <p><?php esc_html_e('Overview of all OHA operations and statistics', 'oha-board-hub'); ?></p>
            
            <?php $this->render_quick_stats(); ?>
            
            <?php $this->render_recent_activity(); ?>
            
            <?php $this->render_quick_actions(); ?>
        </div>
        
        <style>
            .oha-dashboard { max-width: 1400px; }
            .oha-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin: 30px 0;
            }
            .oha-stat-card {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 20px;
                text-align: center;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                transition: all 0.3s ease;
            }
            .oha-stat-card:hover {
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                transform: translateY(-2px);
            }
            .oha-stat-icon {
                font-size: 36px;
                margin-bottom: 10px;
            }
            .oha-stat-number {
                font-size: 32px;
                font-weight: bold;
                color: #a0232a;
                margin: 10px 0;
            }
            .oha-stat-label {
                font-size: 14px;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .oha-stat-sublabel {
                font-size: 12px;
                color: #999;
                margin-top: 5px;
            }
            .oha-recent-activity {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
            }
            .oha-activity-item {
                padding: 12px;
                border-bottom: 1px solid #eee;
            }
            .oha-activity-item:last-child {
                border-bottom: none;
            }
            .oha-activity-time {
                color: #999;
                font-size: 12px;
            }
            .oha-quick-actions {
                display: flex;
                gap: 15px;
                margin: 20px 0;
                flex-wrap: wrap;
            }
        </style>
        <?php
    }
    
    /**
     * Render quick statistics cards
     */
    private function render_quick_stats() {
        global $wpdb;
        
        // Get vendor statistics
        $total_vendors = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'vendor_application'"
        );
        
        $paid_vendors = $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
             WHERE p.post_type = 'vendor_application'
             AND m.meta_key = '_oha_status'
             AND m.meta_value = 'Paid'"
        );
        
        $total_booths = $wpdb->get_var(
            "SELECT SUM(CAST(m.meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} m
             INNER JOIN {$wpdb->posts} p ON m.post_id = p.ID
             WHERE p.post_type = 'vendor_application'
             AND m.meta_key = 'booths'
             AND EXISTS (
                 SELECT 1 FROM {$wpdb->postmeta} m2
                 WHERE m2.post_id = p.ID
                 AND m2.meta_key = '_oha_status'
                 AND m2.meta_value = 'Paid'
             )"
        );
        
        // Get volunteer count
        $volunteers = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'volunteer'"
        );
        
        // Get GeoDirectory haunt count
        $haunts = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'gd_place' 
             AND post_status = 'publish'"
        );
        
        // Get member counts (last 30 days)
        $new_listing_owners = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} m ON u.ID = m.user_id
             WHERE m.meta_key = 'uwp_member_type'
             AND m.meta_value = 'listing_owner'
             AND u.user_registered >= %s",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));
        
        $new_oha_members = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} m ON u.ID = m.user_id
             WHERE m.meta_key = 'uwp_member_type'
             AND m.meta_value = 'oha_member'
             AND u.user_registered >= %s",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));
        
        // Total OHA members
        $total_oha_members = $wpdb->get_var(
            "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} m ON u.ID = m.user_id
             WHERE m.meta_key = 'uwp_member_type'
             AND m.meta_value = 'oha_member'"
        );
        
        ?>
        <div class="oha-stats-grid">
            <div class="oha-stat-card">
                <div class="oha-stat-icon">🎪</div>
                <div class="oha-stat-number"><?php echo esc_html($total_vendors ?: '0'); ?></div>
                <div class="oha-stat-label">Vendor Applications</div>
                <div class="oha-stat-sublabel">All time</div>
            </div>
            
            <div class="oha-stat-card">
                <div class="oha-stat-icon">💰</div>
                <div class="oha-stat-number"><?php echo esc_html($paid_vendors ?: '0'); ?></div>
                <div class="oha-stat-label">Paid Vendors</div>
                <div class="oha-stat-sublabel">Ready for convention</div>
            </div>
            
            <div class="oha-stat-card">
                <div class="oha-stat-icon">📦</div>
                <div class="oha-stat-number"><?php echo esc_html($total_booths ?: '0'); ?></div>
                <div class="oha-stat-label">Total Booths</div>
                <div class="oha-stat-sublabel">Reserved & paid</div>
            </div>
            
            <div class="oha-stat-card">
                <div class="oha-stat-icon">🙋</div>
                <div class="oha-stat-number"><?php echo esc_html($volunteers ?: '0'); ?></div>
                <div class="oha-stat-label">Volunteers</div>
                <div class="oha-stat-sublabel">Signed up</div>
            </div>
            
            <div class="oha-stat-card">
                <div class="oha-stat-icon">👥</div>
                <div class="oha-stat-number"><?php echo esc_html($total_oha_members ?: '0'); ?></div>
                <div class="oha-stat-label">OHA Members</div>
                <div class="oha-stat-sublabel"><?php echo esc_html($new_oha_members); ?> new (30 days)</div>
            </div>
            
            <div class="oha-stat-card">
                <div class="oha-stat-icon">🏚️</div>
                <div class="oha-stat-number"><?php echo esc_html($haunts ?: '0'); ?></div>
                <div class="oha-stat-label">Haunt Listings</div>
                <div class="oha-stat-sublabel">Published</div>
            </div>
            
            <div class="oha-stat-card">
                <div class="oha-stat-icon">📝</div>
                <div class="oha-stat-number"><?php echo esc_html($new_listing_owners ?: '0'); ?></div>
                <div class="oha-stat-label">New Listing Owners</div>
                <div class="oha-stat-sublabel">Last 30 days</div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render recent activity feed
     */
    private function render_recent_activity() {
        global $wpdb;
        
        // Get recent vendor payments
        $recent_payments = $wpdb->get_results(
            "SELECT p.ID, p.post_date, m.meta_value as company_name
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
             INNER JOIN {$wpdb->postmeta} m2 ON p.ID = m2.post_id
             WHERE p.post_type = 'vendor_application'
             AND m.meta_key = 'company_name'
             AND m2.meta_key = '_oha_status'
             AND m2.meta_value = 'Paid'
             ORDER BY p.post_modified DESC
             LIMIT 5"
        );
        
        // Get recent member signups
        $recent_members = $wpdb->get_results(
            "SELECT u.ID, u.user_registered, u.display_name
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} m ON u.ID = m.user_id
             WHERE m.meta_key = 'uwp_member_type'
             AND m.meta_value = 'oha_member'
             ORDER BY u.user_registered DESC
             LIMIT 5"
        );
        
        ?>
        <div class="oha-recent-activity">
            <h3>Recent Activity</h3>
            
            <?php if ($recent_payments): ?>
                <?php foreach ($recent_payments as $payment): ?>
                    <div class="oha-activity-item">
                        💰 <strong><?php echo esc_html($payment->company_name); ?></strong> completed payment
                        <div class="oha-activity-time"><?php echo human_time_diff(strtotime($payment->post_date), current_time('timestamp')); ?> ago</div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if ($recent_members): ?>
                <?php foreach ($recent_members as $member): ?>
                    <div class="oha-activity-item">
                        👥 <strong><?php echo esc_html($member->display_name); ?></strong> joined as OHA member
                        <div class="oha-activity-time"><?php echo human_time_diff(strtotime($member->user_registered), current_time('timestamp')); ?> ago</div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!$recent_payments && !$recent_members): ?>
                <p style="color: #999;">No recent activity</p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render quick action buttons
     */
    private function render_quick_actions() {
        ?>
        <div class="oha-quick-actions">
            <h3 style="width:100%;">Quick Actions</h3>
            <a href="<?php echo admin_url('admin.php?page=oha-board-hub&tab=vendor'); ?>" class="button button-primary">View Vendors</a>
            <a href="<?php echo admin_url('admin.php?page=oha-board-hub&tab=membership'); ?>" class="button button-primary">Manage Members</a>
            <a href="<?php echo admin_url('admin.php?page=oha-board-hub&tab=stripe'); ?>" class="button">Stripe Settings</a>
            <a href="https://dashboard.stripe.com" target="_blank" class="button">Open Stripe Dashboard →</a>
        </div>
        <?php
    }
    
    /**
     * Render membership tab
     */
    private function render_membership_tab() {
        ?>
        <h2><?php esc_html_e('Members', 'oha-board-hub'); ?></h2>
        <p><?php esc_html_e('Stripe management (Cancel / Resume / Swap / Renew) below. SWPM current members listed for reference.', 'oha-board-hub'); ?></p>
        
        <?php echo do_shortcode('[oha_board_stripe_admin]'); ?>
        
        <?php $this->render_member_list(); ?>
        <?php
    }
    
    /**
     * Render member list with proper security
     */
    private function render_member_list() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'swpm_members_tbl';
        
        // Check if table exists
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        
        if ($exists !== $table) {
            ?>
            <p><em><?php printf(esc_html__('SWPM members table not found (%s).', 'oha-board-hub'), esc_html($table)); ?></em></p>
            <?php
            return;
        }
        
        // Verify nonce for search
        $search_query = '';
        if (isset($_GET['oha_member_search']) && isset($_GET['_wpnonce'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'oha_member_search')) {
                $search_query = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
            }
        }
        
        // Pagination
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 25;
        $offset = ($paged - 1) * $per_page;
        
        // Build query - SECURE VERSION
        $where_clause = '';
        $query_params = [];
        
        if (!empty($search_query)) {
            $like = '%' . $wpdb->esc_like($search_query) . '%';
            $where_clause = " WHERE user_name LIKE %s OR email LIKE %s OR first_name LIKE %s OR last_name LIKE %s";
            $query_params = [$like, $like, $like, $like];
        }
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM {$table}" . $where_clause;
        if (!empty($query_params)) {
            $count_sql = $wpdb->prepare($count_sql, ...$query_params);
        }
        $total_items = $wpdb->get_var($count_sql);
        
        // Get paginated results
        $sql = "SELECT member_id, user_name, first_name, last_name, email, account_state, membership_level 
                FROM {$table}" . $where_clause . " 
                ORDER BY member_id DESC 
                LIMIT %d OFFSET %d";
        
        $final_params = array_merge($query_params, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$final_params), ARRAY_A);
        
        ?>
        <h3><?php esc_html_e('SWPM Members', 'oha-board-hub'); ?></h3>
        
        <form method="get" class="oha-member-search">
            <?php
            // Preserve page and tab
            foreach (['page', 'tab'] as $keep) {
                if (isset($_GET[$keep])) {
                    printf(
                        '<input type="hidden" name="%s" value="%s">',
                        esc_attr($keep),
                        esc_attr($_GET[$keep])
                    );
                }
            }
            
            // Add nonce
            wp_nonce_field('oha_member_search');
            ?>
            
            <input type="hidden" name="oha_member_search" value="1">
            <input type="search" 
                   name="q" 
                   value="<?php echo esc_attr($search_query); ?>" 
                   placeholder="<?php esc_attr_e('Search members (name/email/username)', 'oha-board-hub'); ?>">
            <?php submit_button(__('Search', 'oha-board-hub'), 'secondary', 'submit', false); ?>
        </form>
        
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'oha-board-hub'); ?></th>
                    <th><?php esc_html_e('Username', 'oha-board-hub'); ?></th>
                    <th><?php esc_html_e('Name', 'oha-board-hub'); ?></th>
                    <th><?php esc_html_e('Email', 'oha-board-hub'); ?></th>
                    <th><?php esc_html_e('Level', 'oha-board-hub'); ?></th>
                    <th><?php esc_html_e('Status', 'oha-board-hub'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)) : ?>
                    <tr>
                        <td colspan="6">
                            <em><?php esc_html_e('No members found.', 'oha-board-hub'); ?></em>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($rows as $row) : ?>
                        <?php
                        $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                        ?>
                        <tr>
                            <td><?php echo intval($row['member_id']); ?></td>
                            <td><?php echo esc_html($row['user_name']); ?></td>
                            <td><?php echo esc_html($name); ?></td>
                            <td><?php echo esc_html($row['email']); ?></td>
                            <td><?php echo esc_html($row['membership_level']); ?></td>
                            <td><?php echo esc_html($row['account_state']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php
        // Pagination
        if ($total_items > $per_page) {
            $total_pages = ceil($total_items / $per_page);
            
            $pagination_args = [
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'current' => $paged,
                'total' => $total_pages,
                'prev_text' => __('&laquo; Previous', 'oha-board-hub'),
                'next_text' => __('Next &raquo;', 'oha-board-hub'),
            ];
            
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo paginate_links($pagination_args);
            echo '</div></div>';
        }
    }
    
    /**
     * Render Board Hub settings tab
     */
    private function render_boardhub_tab() {
        ?>
        <h2><?php esc_html_e('Board Hub Settings', 'oha-board-hub'); ?></h2>
        <?php
        
        if (class_exists('OHA_Board_Hub')) {
            $obj = new OHA_Board_Hub();
            if (method_exists($obj, 'settings_page')) {
                $obj->settings_page();
                return;
            }
        }
        
        ?>
        <p><em><?php esc_html_e('Board Hub class not available.', 'oha-board-hub'); ?></em></p>
        <?php
    }
    
    /**
     * Render Stripe settings tab
     */
    private function render_stripe_tab() {
        ?>
        <h2><?php esc_html_e('Stripe Settings', 'oha-board-hub'); ?></h2>
        <?php
        
        if (function_exists('oha_sm_settings_page')) {
            oha_sm_settings_page();
        } else {
            ?>
            <p><?php esc_html_e('Stripe Minimal not loaded.', 'oha-board-hub'); ?></p>
            <?php
        }
    }
    
    /**
     * Render Vendor tab
     */
    private function render_vendor_tab() {
        global $wpdb;
        
        // Get vendor statistics
        $total_vendors = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'vendor_application'"
        );
        
        $paid_vendors = $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
             WHERE p.post_type = 'vendor_application'
             AND m.meta_key = '_oha_status'
             AND m.meta_value = 'Paid'"
        );
        
        $pending_vendors = $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
             WHERE p.post_type = 'vendor_application'
             AND m.meta_key = '_oha_status'
             AND m.meta_value IN ('Submitted', 'In Review', 'Approved')"
        );
        
        $total_booths = $wpdb->get_var(
            "SELECT SUM(CAST(m.meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} m
             INNER JOIN {$wpdb->posts} p ON m.post_id = p.ID
             WHERE p.post_type = 'vendor_application'
             AND m.meta_key = 'booths'
             AND EXISTS (
                 SELECT 1 FROM {$wpdb->postmeta} m2
                 WHERE m2.post_id = p.ID
                 AND m2.meta_key = '_oha_status'
                 AND m2.meta_value = 'Paid'
             )"
        );
        
        $recent_vendors = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'vendor_application'
             AND post_date >= %s",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));
        
        ?>
        <h2><?php esc_html_e('Convention 2026 - Vendor Management', 'oha-board-hub'); ?></h2>
        
        <!-- Vendor Statistics -->
        <div class="oha-vendor-stats-grid">
            <div class="oha-stat-card">
                <div class="oha-stat-icon">🎪</div>
                <div class="oha-stat-number"><?php echo esc_html($total_vendors ?: '0'); ?></div>
                <div class="oha-stat-label">Total Applications</div>
                <div class="oha-stat-sublabel"><?php echo esc_html($recent_vendors); ?> new (30 days)</div>
            </div>
            
            <div class="oha-stat-card">
                <div class="oha-stat-icon">💰</div>
                <div class="oha-stat-number"><?php echo esc_html($paid_vendors ?: '0'); ?></div>
                <div class="oha-stat-label">Paid & Confirmed</div>
                <div class="oha-stat-sublabel">Ready for event</div>
            </div>
            
            <div class="oha-stat-card">
                <div class="oha-stat-icon">⏳</div>
                <div class="oha-stat-number"><?php echo esc_html($pending_vendors ?: '0'); ?></div>
                <div class="oha-stat-label">Pending Payment</div>
                <div class="oha-stat-sublabel">Awaiting completion</div>
            </div>
            
            <div class="oha-stat-card">
                <div class="oha-stat-icon">📦</div>
                <div class="oha-stat-number"><?php echo esc_html($total_booths ?: '0'); ?></div>
                <div class="oha-stat-label">Total Booths</div>
                <div class="oha-stat-sublabel">Reserved & paid</div>
            </div>
        </div>
        
        <style>
            .oha-vendor-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin: 20px 0 30px 0;
            }
        </style>
        
        <?php
        
        if (shortcode_exists('oha_vendor_board_hub')) {
            ?>
            <div class="card" style="padding:12px;margin-bottom:16px;">
                <?php echo do_shortcode('[oha_vendor_board_hub]'); ?>
            </div>
            <?php
        } else {
            ?>
            <p><em><?php esc_html_e('Vendor dashboard not available.', 'oha-board-hub'); ?></em></p>
            <?php
        }
        
        if (function_exists('oha_vbh_2026_settings')) {
            echo '<hr style="margin:20px 0;">';
            oha_vbh_2026_settings();
        }
    }
    
    /**
     * Render Webhooks tab
     */
    private function render_webhooks_tab() {
        $settings = OHA_Settings::instance();
        $path = trim($settings->get('stripe.endpoint_path', 'oha/v1/stripe-webhook'), '/');
        $webhook_url = home_url('/' . $path);
        
        ?>
        <h2><?php esc_html_e('Webhook Endpoint', 'oha-board-hub'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Webhook URL', 'oha-board-hub'); ?></th>
                <td>
                    <code><?php echo esc_html($webhook_url); ?></code>
                    <p class="description">
                        <?php esc_html_e('Add this URL to your Stripe Dashboard webhook settings.', 'oha-board-hub'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Events Handled', 'oha-board-hub'); ?></th>
                <td>
                    <ul>
                        <li><code>checkout.session.completed</code></li>
                        <li><code>customer.created</code></li>
                        <li><code>customer.updated</code></li>
                        <li><code>customer.subscription.created</code></li>
                        <li><code>customer.subscription.updated</code></li>
                        <li><code>customer.subscription.deleted</code></li>
                        <li><code>invoice.payment_succeeded</code></li>
                    </ul>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Downgrade Behavior', 'oha-board-hub'); ?></th>
                <td>
                    <?php
                    printf(
                        esc_html__('When a member cancels at period end, they are automatically downgraded to Free (Level %d).', 'oha-board-hub'),
                        intval($settings->get('swpm.free_level_id', 2))
                    );
                    ?>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render Board Notifications tab
     */
    private function render_notifications_tab() {
        if (class_exists('OHA_Board_Notifications')) {
            OHA_Board_Notifications::render_settings();
        } else {
            ?>
            <p><?php esc_html_e('Board Notifications module not loaded.', 'oha-board-hub'); ?></p>
            <?php
        }
    }
    
    /**
     * Render Tools tab
     */
    private function render_tools_tab() {
        if (function_exists('oha_sm_tools_page')) {
            oha_sm_tools_page();
        } else {
            ?>
            <p><?php esc_html_e('No tools available.', 'oha-board-hub'); ?></p>
            <?php
        }
    }
    
    /**
     * Render Debug tab
     */
    private function render_debug_tab() {
        if (function_exists('oha_sm_debug_page')) {
            oha_sm_debug_page();
        } else {
            ?>
            <p><?php esc_html_e('No debug information available.', 'oha-board-hub'); ?></p>
            <?php
        }
    }
    
    /**
     * Render Discounts tab
     */
    private function render_discounts_tab() {
        if (!current_user_can('manage_options') && 
            !current_user_can('manage_oha_members') && 
            !current_user_can('read_oha_board_hub')) {
            ?>
            <p><em><?php esc_html_e('Access restricted.', 'oha-board-hub'); ?></em></p>
            <?php
            return;
        }
        
        ?>
        <h2><?php esc_html_e('Haunt Discounts', 'oha-board-hub'); ?></h2>
        <?php
        
        if (shortcode_exists('oha_haunt_discounts_admin')) {
            echo do_shortcode('[oha_haunt_discounts_admin]');
        } else {
            ?>
            <p><em><?php esc_html_e('Module not loaded.', 'oha-board-hub'); ?></em></p>
            <?php
        }
    }
}
