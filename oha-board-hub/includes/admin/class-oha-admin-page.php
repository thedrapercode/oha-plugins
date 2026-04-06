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
            'shortcodes' => __('Shortcode Reference', 'oha-board-hub'),
            'tools' => __('Tools', 'oha-board-hub'),
            'debug' => __('Debug', 'oha-board-hub'),
            'discounts' => __('Haunt Discounts', 'oha-board-hub'),
        ];

        // Ensure settings group is registered. Hook for future page loads AND call
        // directly because admin_init has already fired by the time this class is
        // instantiated (the menu page callback runs after admin_init).
        add_action('admin_init', [$this, 'init_settings']);
        $this->init_settings();
    }

    /**
     * Register the oha_sm settings group so the Stripe Settings form saves via options.php.
     */
    public function init_settings() {
        // Guard: only register if not already registered.
        $registered = get_registered_settings();
        if (!isset($registered['oha_sm_settings'])) {
            register_setting('oha_sm', 'oha_sm_settings');
        }
    }

    /**
     * Settings are processed by the WordPress Settings API (options.php).
     * Form uses settings_fields('oha_sm') which generates the correct nonce.
     */
    
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
                case 'shortcodes':
                    $this->render_shortcodes_tab();
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
        <p><?php esc_html_e('Stripe management (Cancel / Resume / Swap / Renew) below. All current members (legacy SWPM + UsersWP) listed below.', 'oha-board-hub'); ?></p>

        <?php echo do_shortcode('[oha_board_stripe_admin]'); ?>

        <?php
        if (class_exists('OHA_Members_Table')) {
            OHA_Members_Table::render();
        } else {
            echo '<p><em>OHA_Members_Table class not loaded.</em></p>';
        }
        ?>
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
    /**
     * Render Stripe Settings tab - WORKING v2.2.6 approach (WordPress Settings API)
     */
    private function render_stripe_tab() {
        // Check if settings were just saved
        $settings_updated = false;
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            $settings_updated = true;
        }
        
        ?>
        <h2><?php esc_html_e('Stripe Settings', 'oha-board-hub'); ?></h2>
        
        <?php if ($settings_updated): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('✅ Stripe settings saved successfully!', 'oha-board-hub'); ?></p>
            </div>
        <?php endif; ?>
        
        <form method="post" action="options.php">
            <?php
            // Use the oha_sm settings group for Stripe settings (WordPress Settings API)
            settings_fields('oha_sm');
            
            // Get current stripe settings
            $settings = get_option('oha_sm_settings', array());
            
            // Enhanced debug info with sync status verification
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // Get suite settings for comparison
                $suite_settings = class_exists('OHA_Settings') ? OHA_Settings::instance()->get() : array();
                $suite_stripe = isset($suite_settings['stripe']) ? $suite_settings['stripe'] : array();
                
                echo '<div style="background:#f0f0f0;padding:15px;margin:15px 0;border-left:4px solid #666;">';
                echo '<strong>🔧 STRIPE SETTINGS DEBUG - v2.2.8 with v2.2.6 Working Code</strong><br/><br/>';
                
                echo '<strong>1. Settings Storage (oha_sm_settings) - Working WordPress Settings API:</strong><br/>';
                echo 'Option name: oha_sm_settings<br/>';
                echo 'Status: ' . (!empty($settings) ? '✅ Found (' . count($settings) . ' items)' : '❌ Empty/Not found') . '<br/>';
                if (!empty($settings)) {
                    echo 'Secret Key: ' . (isset($settings['secret_key']) && !empty($settings['secret_key']) ? '✅ Present (' . substr($settings['secret_key'], 0, 7) . '...)' : '❌ Missing/Empty') . '<br/>';
                    echo 'Webhook Secret: ' . (isset($settings['webhook_secret']) && !empty($settings['webhook_secret']) ? '✅ Present (' . substr($settings['webhook_secret'], 0, 7) . '...)' : '❌ Missing/Empty') . '<br/>';
                    echo 'Price IDs: ' . ((!empty($settings['price_signup_20']) && !empty($settings['price_yearly_10'])) ? '✅ Both configured' : '❌ Missing price IDs') . '<br/>';
                }
                
                echo '<br/><strong>2. WordPress Settings API Status:</strong><br/>';
                echo 'Form action: options.php (WordPress standard)<br/>';
                echo 'Settings group: oha_sm (registered with array type)<br/>';
                echo 'Field names: oha_sm_settings[field_name] (WordPress array format)<br/>';
                echo 'Capability required: manage_options<br/>';
                $user_can_manage = current_user_can('manage_options');
                echo 'Current user capability: ' . ($user_can_manage ? '✅ Has permission' : '❌ Missing permission - this may prevent saves') . '<br/>';
                
                echo '<br/><strong>3. Vendor Hub Conflict Protection:</strong><br/>';
                echo 'Board Hub registration: ✅ Full array registration with sanitize callback<br/>';
                echo 'Vendor Hub interference: ' . (is_plugin_active('oha-vendor-hub/oha-vendor-hub.php') ? '⚠️ Vendor Hub active - potential conflict' : '✅ No Vendor Hub conflict') . '<br/>';
                
                echo '</div>';
            }
            ?>
            
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="stripe_secret_key"><?php esc_html_e('Stripe Secret Key', 'oha-board-hub'); ?></label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="stripe_secret_key" 
                                   name="oha_sm_settings[secret_key]" 
                                   value="<?php echo esc_attr($settings['secret_key'] ?? ''); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php esc_html_e('Your Stripe secret key (sk_live_... or sk_test_...)', 'oha-board-hub'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="webhook_secret"><?php esc_html_e('Webhook Signing Secret', 'oha-board-hub'); ?></label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="webhook_secret" 
                                   name="oha_sm_settings[webhook_secret]" 
                                   value="<?php echo esc_attr($settings['webhook_secret'] ?? ''); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php esc_html_e('Webhook signing secret from Stripe (whsec_...)', 'oha-board-hub'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="endpoint_path"><?php esc_html_e('Webhook Endpoint Path', 'oha-board-hub'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="endpoint_path" 
                                   name="oha_sm_settings[endpoint_path]" 
                                   value="<?php echo esc_attr($settings['endpoint_path'] ?? 'oha/v1/stripe-webhook'); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php esc_html_e('REST API path for webhook endpoint', 'oha-board-hub'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="price_signup_20"><?php esc_html_e('Initial Membership Fee ($20)', 'oha-board-hub'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="price_signup_20" 
                                   name="oha_sm_settings[price_signup_20]" 
                                   value="<?php echo esc_attr($settings['price_signup_20'] ?? ''); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php esc_html_e('Stripe Price ID for $20 one-time membership fee (price_xxxxxxxxxxxxx)', 'oha-board-hub'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="price_yearly_10"><?php esc_html_e('Annual Membership Fee ($10/year)', 'oha-board-hub'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="price_yearly_10" 
                                   name="oha_sm_settings[price_yearly_10]" 
                                   value="<?php echo esc_attr($settings['price_yearly_10'] ?? ''); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php esc_html_e('Stripe Price ID for $10/year recurring membership fee (price_xxxxxxxxxxxxx)', 'oha-board-hub'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="membership_product_ids"><?php esc_html_e('Membership Product IDs', 'oha-board-hub'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="membership_product_ids" 
                                   name="oha_sm_settings[membership_product_ids]" 
                                   value="<?php echo esc_attr($settings['membership_product_ids'] ?? ''); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php esc_html_e('Comma-separated Stripe Product IDs for invoice payments (prod_xxxxx, prod_yyyyy)', 'oha-board-hub'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="portal_return_url"><?php esc_html_e('Billing Portal Return URL', 'oha-board-hub'); ?></label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="portal_return_url" 
                                   name="oha_sm_settings[portal_return_url]" 
                                   value="<?php echo esc_attr($settings['portal_return_url'] ?? home_url('/')); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php esc_html_e('URL where users return after managing billing', 'oha-board-hub'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="trial_end_iso"><?php esc_html_e('Annual Renewal Start Date', 'oha-board-hub'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="trial_end_iso" 
                                   name="oha_sm_settings[trial_end_iso]" 
                                   value="<?php echo esc_attr($settings['trial_end_iso'] ?? '2027-01-01T00:00:00Z'); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php esc_html_e('When annual $10 charges begin (e.g. 2027-01-01T00:00:00Z)', 'oha-board-hub'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="timestamp_tolerance"><?php esc_html_e('Webhook Tolerance (seconds)', 'oha-board-hub'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="timestamp_tolerance" 
                                   name="oha_sm_settings[timestamp_tolerance]" 
                                   value="<?php echo intval($settings['timestamp_tolerance'] ?? 300); ?>" 
                                   class="small-text" />
                            <p class="description"><?php esc_html_e('Webhook timestamp tolerance in seconds', 'oha-board-hub'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="invoice_limit"><?php esc_html_e('Invoice Limit', 'oha-board-hub'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="invoice_limit" 
                                   name="oha_sm_settings[invoice_limit]" 
                                   value="<?php echo intval($settings['invoice_limit'] ?? 12); ?>" 
                                   class="small-text" />
                            <p class="description"><?php esc_html_e('Maximum invoices to process per webhook call', 'oha-board-hub'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="membership_level_id"><?php esc_html_e('SWMP Membership Level ID', 'oha-board-hub'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="membership_level_id" 
                                   name="oha_sm_settings[membership_level_id]" 
                                   value="<?php echo intval($settings['membership_level_id'] ?? 0); ?>" 
                                   class="small-text" />
                            <p class="description"><?php esc_html_e('Simple WP Membership level ID for OHA members', 'oha-board-hub'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="enable_logging"><?php esc_html_e('Enable Error Logging', 'oha-board-hub'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="enable_logging" 
                                       name="oha_sm_settings[log]" 
                                       value="1" 
                                       <?php checked(!empty($settings['log'])); ?> />
                                <?php esc_html_e('Show admin notices & error_log', 'oha-board-hub'); ?>
                            </label>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <?php submit_button('Save Stripe Settings'); ?>
        </form>
        
        <hr />
        
        <h3><?php esc_html_e('Configuration Status', 'oha-board-hub'); ?></h3>
        <?php
        // Show current configuration status
        $path = trim($settings['endpoint_path'] ?? '', '/');
        $p = explode('/', $path);
        if (count($p) >= 2) { 
            $ns = $p[0] . '/' . $p[1]; 
            $rt = '/' . implode('/', array_slice($p, 2)); 
            if ($rt === '/') $rt = '/stripe-webhook'; 
        } else { 
            $ns = 'oha/v1'; 
            $rt = '/stripe-webhook'; 
        }
        $webhook_url = esc_url_raw(rest_url($ns . $rt));
        
        echo '<table class="form-table">';
        echo '<tr><th>Webhook URL:</th><td><code>' . esc_html($webhook_url) . '</code></td></tr>';
        echo '<tr><th>Secret Key:</th><td>' . (!empty($settings['secret_key']) ? '<span style="color:green;">✅ Configured</span>' : '<span style="color:red;">❌ Not configured</span>') . '</td></tr>';
        echo '<tr><th>Webhook Secret:</th><td>' . (!empty($settings['webhook_secret']) ? '<span style="color:green;">✅ Configured</span>' : '<span style="color:red;">❌ Not configured</span>') . '</td></tr>';
        echo '<tr><th>Price IDs:</th><td>' . ((!empty($settings['price_signup_20']) && !empty($settings['price_yearly_10'])) ? '<span style="color:green;">✅ Both configured</span>' : '<span style="color:red;">❌ Missing price IDs</span>') . '</td></tr>';
        echo '<tr><th>Portal Return URL:</th><td>' . esc_html($settings['portal_return_url'] ?? 'Not set') . '</td></tr>';
        echo '<tr><th>Invoice Limit:</th><td>' . intval($settings['invoice_limit'] ?? 10) . '</td></tr>';
        echo '<tr><th>Error Logging:</th><td>' . (empty($settings['log']) ? 'Disabled' : 'Enabled') . '</td></tr>';
        echo '</table>';
        ?>
        
        <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:15px;margin:20px 0;">
            <h4 style="margin-top:0;"><?php esc_html_e('Usage', 'oha-board-hub'); ?></h4>
            <ul>
                <li><strong><?php esc_html_e('Membership Checkout:', 'oha-board-hub'); ?></strong> <code>[oha_membership_checkout]</code> <?php esc_html_e('(full form with details)', 'oha-board-hub'); ?></li>
                <li><strong><?php esc_html_e('Button Only:', 'oha-board-hub'); ?></strong> <code>[oha_membership_checkout_button]</code> <?php esc_html_e('(for comparison tables)', 'oha-board-hub'); ?></li>
                <li><strong><?php esc_html_e('Smart Button (v2.2.7+):', 'oha-board-hub'); ?></strong> <code>[oha_smart_membership_button]</code> <?php esc_html_e('(auto-detects Join/Upgrade/Renew)', 'oha-board-hub'); ?></li>
                <li><strong><?php esc_html_e('Member Management:', 'oha-board-hub'); ?></strong> <?php esc_html_e('Available in Board Hub → Members tab', 'oha-board-hub'); ?></li>
                <li><strong><?php esc_html_e('Stripe Dashboard:', 'oha-board-hub'); ?></strong> <a href="https://dashboard.stripe.com" target="_blank"><?php esc_html_e('Open Stripe Dashboard →', 'oha-board-hub'); ?></a></li>
            </ul>
        </div>
        <?php
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
    
    /**
     * Render shortcode reference tab
     */
    private function render_shortcodes_tab() {
        ?>
        <style>
        .oha-shortcode-reference {
            margin-top: 20px;
        }
        .oha-shortcode-search {
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .oha-shortcode-search input[type="text"] {
            min-width: 300px;
            padding: 8px 12px;
        }
        .oha-shortcode-search select {
            padding: 8px 12px;
        }
        .oha-shortcodes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .oha-shortcode-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: box-shadow 0.2s ease;
        }
        .oha-shortcode-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .oha-shortcode-name {
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 4px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            border: 1px solid #e9ecef;
        }
        .oha-shortcode-name:hover {
            background: #e9ecef;
        }
        .oha-shortcode-copy {
            background: #007cba;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        .oha-shortcode-copy:hover {
            opacity: 1;
        }
        .oha-shortcode-copy.copied {
            background: #46b450;
        }
        .oha-shortcode-description {
            color: #555;
            margin-bottom: 15px;
        }
        .oha-shortcode-meta {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .oha-shortcode-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .oha-shortcode-badge.active {
            background: #d4edda;
            color: #155724;
        }
        .oha-shortcode-badge.inactive {
            background: #f8d7da;
            color: #721c24;
        }
        .oha-shortcode-badge.board-hub {
            background: #cce5ff;
            color: #004085;
        }
        .oha-shortcode-badge.vendor {
            background: #fff3cd;
            color: #856404;
        }
        .oha-shortcode-badge.membership {
            background: #d1ecf1;
            color: #0c5460;
        }
        .oha-shortcode-badge.admin {
            background: #f0d0d1;
            color: #721c24;
        }
        .oha-shortcode-badge.frontend {
            background: #d4f6d4;
            color: #155724;
        }
        .oha-shortcode-attributes {
            margin-top: 15px;
        }
        .oha-shortcode-attributes h4 {
            font-size: 12px;
            margin: 0 0 8px 0;
            color: #666;
            text-transform: uppercase;
        }
        .oha-shortcode-example {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            font-size: 13px;
            color: #495057;
            border: 1px solid #e9ecef;
        }
        .oha-shortcode-stats {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        .oha-shortcode-stat {
            text-align: center;
        }
        .oha-shortcode-stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #007cba;
        }
        .oha-shortcode-stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        .oha-shortcode-category-title {
            margin: 30px 0 15px 0;
            font-size: 18px;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #007cba;
            padding-bottom: 5px;
        }
        @media (max-width: 768px) {
            .oha-shortcodes-grid {
                grid-template-columns: 1fr;
            }
            .oha-shortcode-search {
                flex-direction: column;
                align-items: stretch;
            }
        }
        </style>

        <h2><?php esc_html_e('Shortcode Reference', 'oha-board-hub'); ?></h2>
        <p><?php esc_html_e('Complete reference of all OHA shortcodes with examples and documentation.', 'oha-board-hub'); ?></p>

        <?php
        // Get comprehensive shortcode list
        $shortcodes = $this->get_comprehensive_shortcode_list();
        $categories = [];
        $total_shortcodes = count($shortcodes);
        $active_shortcodes = 0;
        
        foreach ($shortcodes as $shortcode_name => $data) {
            if (!isset($categories[$data['category']])) {
                $categories[$data['category']] = [];
            }
            $categories[$data['category']][$shortcode_name] = $data;
            if ($data['status'] === 'active') {
                $active_shortcodes++;
            }
        }
        ?>

        <div class="oha-shortcode-stats">
            <div class="oha-shortcode-stat">
                <div class="oha-shortcode-stat-number"><?php echo esc_html($total_shortcodes); ?></div>
                <div class="oha-shortcode-stat-label">Total Shortcodes</div>
            </div>
            <div class="oha-shortcode-stat">
                <div class="oha-shortcode-stat-number"><?php echo esc_html($active_shortcodes); ?></div>
                <div class="oha-shortcode-stat-label">Active Shortcodes</div>
            </div>
            <div class="oha-shortcode-stat">
                <div class="oha-shortcode-stat-number"><?php echo esc_html(count($categories)); ?></div>
                <div class="oha-shortcode-stat-label">Categories</div>
            </div>
        </div>

        <div class="oha-shortcode-search">
            <input type="text" id="oha-shortcode-search-input" placeholder="Search shortcodes..." />
            <select id="oha-shortcode-category-filter">
                <option value=""><?php esc_html_e('All Categories', 'oha-board-hub'); ?></option>
                <?php foreach (array_keys($categories) as $category): ?>
                    <option value="<?php echo esc_attr($category); ?>">
                        <?php echo esc_html($this->get_category_title($category)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" id="oha-shortcode-clear-search" class="button">
                <?php esc_html_e('Clear', 'oha-board-hub'); ?>
            </button>
        </div>

        <div class="oha-shortcode-reference">
            <?php foreach ($categories as $category => $shortcodes_in_category): ?>
                <div class="oha-shortcode-category" data-category="<?php echo esc_attr($category); ?>">
                    <h3 class="oha-shortcode-category-title" id="category-<?php echo esc_attr($category); ?>">
                        <?php echo esc_html($this->get_category_title($category)); ?>
                        <span style="font-size:14px;font-weight:normal;color:#666;">
                            (<?php echo esc_html(count($shortcodes_in_category)); ?> shortcodes)
                        </span>
                    </h3>
                    <div class="oha-shortcodes-grid">
                        <?php foreach ($shortcodes_in_category as $shortcode_name => $data): ?>
                            <?php $this->render_shortcode_item($shortcode_name, $data); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Search functionality
            $('#oha-shortcode-search-input').on('input', function() {
                filterShortcodes();
            });
            
            $('#oha-shortcode-category-filter').on('change', function() {
                filterShortcodes();
            });
            
            $('#oha-shortcode-clear-search').on('click', function() {
                $('#oha-shortcode-search-input').val('');
                $('#oha-shortcode-category-filter').val('');
                filterShortcodes();
            });
            
            function filterShortcodes() {
                var searchTerm = $('#oha-shortcode-search-input').val().toLowerCase();
                var selectedCategory = $('#oha-shortcode-category-filter').val();
                
                $('.oha-shortcode-category').each(function() {
                    var category = $(this).data('category');
                    var categoryVisible = false;
                    
                    if (selectedCategory && selectedCategory !== category) {
                        $(this).hide();
                        return;
                    }
                    
                    $(this).find('.oha-shortcode-card').each(function() {
                        var shortcodeName = $(this).find('.oha-shortcode-name').text().toLowerCase();
                        var description = $(this).find('.oha-shortcode-description').text().toLowerCase();
                        
                        var matches = !searchTerm || 
                                     shortcodeName.includes(searchTerm) || 
                                     description.includes(searchTerm);
                        
                        if (matches) {
                            $(this).show();
                            categoryVisible = true;
                        } else {
                            $(this).hide();
                        }
                    });
                    
                    if (categoryVisible) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            }
            
            // Copy to clipboard functionality
            $('.oha-shortcode-name').on('click', function() {
                var shortcodeName = $(this).find('.shortcode-text').text();
                copyToClipboard(shortcodeName, $(this).find('.oha-shortcode-copy'));
            });
            
            $('.oha-shortcode-copy').on('click', function(e) {
                e.stopPropagation();
                var shortcodeName = $(this).closest('.oha-shortcode-name').find('.shortcode-text').text();
                copyToClipboard(shortcodeName, $(this));
            });
            
            function copyToClipboard(text, button) {
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(function() {
                        showCopyFeedback(button);
                    });
                } else {
                    // Fallback for older browsers
                    var textArea = document.createElement('textarea');
                    textArea.value = text;
                    document.body.appendChild(textArea);
                    textArea.select();
                    try {
                        document.execCommand('copy');
                        showCopyFeedback(button);
                    } catch (err) {
                        console.error('Could not copy text: ', err);
                    }
                    document.body.removeChild(textArea);
                }
            }
            
            function showCopyFeedback(button) {
                var originalText = button.text();
                button.addClass('copied').text('Copied!');
                setTimeout(function() {
                    button.removeClass('copied').text(originalText);
                }, 2000);
            }
        });
        </script>
        <?php
    }
    
    /**
     * Get comprehensive list of all OHA shortcodes
     */
    private function get_comprehensive_shortcode_list() {
        // Built-in Board Hub shortcodes
        $board_hub_shortcodes = [
            'oha_board_hub' => [
                'description' => 'Complete Board Hub admin interface with all management tools',
                'category' => 'board-hub',
                'status' => 'active',
                'plugin' => 'Board Hub Suite',
                'attributes' => [],
                'example' => '[oha_board_hub]'
            ],
            'oha_member_dashboard' => [
                'description' => 'Member dashboard for logged-in users showing membership status',
                'category' => 'membership', 
                'status' => 'active',
                'plugin' => 'Board Hub Suite',
                'attributes' => [],
                'example' => '[oha_member_dashboard]'
            ],
            'oha_membership_checkout' => [
                'description' => 'Stripe-powered membership checkout and payment form with details',
                'category' => 'membership',
                'status' => 'active',
                'plugin' => 'Board Hub Suite', 
                'attributes' => [],
                'example' => '[oha_membership_checkout]'
            ],
            'oha_membership_checkout_button' => [
                'description' => 'Simple membership checkout button without details (perfect for comparison tables)',
                'category' => 'membership',
                'status' => 'active',
                'plugin' => 'Board Hub Suite',
                'attributes' => ['text', 'class', 'style'],
                'example' => '[oha_membership_checkout_button text="Join Now" class="button button-primary"]'
            ],
            'oha_smart_membership_button' => [
                'description' => 'Smart button that auto-detects user status and shows appropriate text (Join/Upgrade/Renew)',
                'category' => 'membership',
                'status' => 'active',
                'plugin' => 'Board Hub Suite',
                'attributes' => ['text', 'class', 'style'],
                'example' => '[oha_smart_membership_button class="button button-primary"]'
            ],
            'oha_board_stripe_admin' => [
                'description' => 'Stripe admin interface for Board Hub (legacy)',
                'category' => 'admin',
                'status' => 'active',
                'plugin' => 'Board Hub Suite',
                'attributes' => [],
                'example' => '[oha_board_stripe_admin]'
            ]
        ];
        
        // Detect addon shortcodes from other OHA plugins
        $addon_shortcodes = $this->detect_addon_shortcodes();
        
        // Merge all shortcodes
        $all_shortcodes = array_merge($board_hub_shortcodes, $addon_shortcodes);
        
        // Apply filter for extensibility
        return apply_filters('oha_board_hub_shortcode_documentation', $all_shortcodes);
    }
    
    /**
     * Detect shortcodes from addon plugins
     */
    private function detect_addon_shortcodes() {
        global $shortcode_tags;
        
        $addon_shortcodes = [];
        $known_addon_shortcodes = [
            // Vendor Hub shortcodes
            'oha_vendor_application' => [
                'description' => 'Vendor application form for convention booth applications',
                'category' => 'vendor',
                'plugin' => 'Vendor Hub'
            ],
            'oha_vendor_boardhub' => [
                'description' => 'Admin interface for managing vendor applications',
                'category' => 'vendor', 
                'plugin' => 'Vendor Hub'
            ],
            'oha_vendor_dashboard' => [
                'description' => 'Vendor dashboard for managing applications and payments',
                'category' => 'vendor',
                'plugin' => 'Vendor Hub'
            ],
            // Schedule Hub shortcodes
            'oha_convention_schedule' => [
                'description' => 'Display convention schedule with events and timing',
                'category' => 'frontend',
                'plugin' => 'Schedule Hub'
            ],
            'oha_schedule_admin' => [
                'description' => 'Admin interface for managing convention schedule',
                'category' => 'admin',
                'plugin' => 'Schedule Hub'
            ],
            // Volunteer Hub shortcodes
            'oha_volunteer_signup' => [
                'description' => 'Volunteer signup form for convention roles',
                'category' => 'frontend',
                'plugin' => 'Volunteer Hub'
            ],
            'oha_volunteer_dashboard' => [
                'description' => 'Volunteer dashboard for managing assignments',
                'category' => 'frontend',
                'plugin' => 'Volunteer Hub'
            ],
            'oha_volunteer_admin' => [
                'description' => 'Admin interface for managing volunteers',
                'category' => 'admin',
                'plugin' => 'Volunteer Hub'
            ],
            // Survey Hub shortcodes
            'oha_post_event_survey' => [
                'description' => 'Post-event survey form for feedback collection',
                'category' => 'frontend',
                'plugin' => 'Survey Hub'
            ],
            'oha_survey_results' => [
                'description' => 'Display survey results and analytics',
                'category' => 'admin',
                'plugin' => 'Survey Hub'
            ]
        ];
        
        // Check which shortcodes are actually registered
        foreach ($known_addon_shortcodes as $shortcode_name => $data) {
            if (isset($shortcode_tags[$shortcode_name])) {
                $addon_shortcodes[$shortcode_name] = array_merge($data, [
                    'status' => 'active',
                    'attributes' => [], // Could be enhanced to detect attributes
                    'example' => '[' . $shortcode_name . ']'
                ]);
            }
        }
        
        // Auto-detect any other OHA shortcodes
        foreach ($shortcode_tags as $shortcode_name => $callback) {
            if (strpos($shortcode_name, 'oha_') === 0 && 
                !isset($addon_shortcodes[$shortcode_name]) && 
                !in_array($shortcode_name, ['oha_board_hub', 'oha_member_dashboard', 'oha_membership_checkout', 'oha_membership_checkout_button', 'oha_smart_membership_button', 'oha_board_stripe_admin'])) {
                
                $addon_shortcodes[$shortcode_name] = [
                    'description' => 'Auto-detected OHA shortcode (documentation not available)',
                    'category' => 'other',
                    'status' => 'active',
                    'plugin' => 'Unknown Plugin',
                    'attributes' => [],
                    'example' => '[' . $shortcode_name . ']'
                ];
            }
        }
        
        return $addon_shortcodes;
    }
    
    /**
     * Render individual shortcode item
     */
    private function render_shortcode_item($shortcode_name, $data) {
        $status_class = $data['status'] === 'active' ? 'active' : 'inactive';
        $category_class = esc_attr($data['category']);
        ?>
        <div class="oha-shortcode-card" data-shortcode="<?php echo esc_attr($shortcode_name); ?>">
            <div class="oha-shortcode-name" title="Click to copy">
                <span class="shortcode-text">[<?php echo esc_html($shortcode_name); ?>]</span>
                <button type="button" class="oha-shortcode-copy">Copy</button>
            </div>
            
            <div class="oha-shortcode-description">
                <?php echo esc_html($data['description']); ?>
            </div>
            
            <div class="oha-shortcode-meta">
                <span class="oha-shortcode-badge <?php echo esc_attr($status_class); ?>">
                    <?php echo esc_html($data['status']); ?>
                </span>
                <span class="oha-shortcode-badge <?php echo esc_attr($category_class); ?>">
                    <?php echo esc_html($this->get_category_title($data['category'])); ?>
                </span>
                <span class="oha-shortcode-badge" style="background:#f8f9fa;color:#495057;">
                    <?php echo esc_html($data['plugin']); ?>
                </span>
            </div>
            
            <?php if (!empty($data['attributes'])): ?>
                <div class="oha-shortcode-attributes">
                    <h4>Available Attributes:</h4>
                    <div style="font-size:12px;color:#666;">
                        <?php echo esc_html(implode(', ', $data['attributes'])); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="oha-shortcode-attributes">
                <h4>Example:</h4>
                <div class="oha-shortcode-example">
                    <?php echo esc_html($data['example']); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get category display title
     */
    private function get_category_title($category) {
        $titles = [
            'board-hub' => 'Board Hub Core',
            'membership' => 'Membership',
            'vendor' => 'Vendor Management', 
            'admin' => 'Admin Tools',
            'frontend' => 'Frontend Display',
            'other' => 'Other'
        ];
        
        return isset($titles[$category]) ? $titles[$category] : ucfirst(str_replace('-', ' ', $category));
    }
}
