<?php
if(defined('OHA_VENDOR_2026_ONCE')) return; define('OHA_VENDOR_2026_ONCE', true);

/* OHA Vendor 2026 (embedded lib) - INTEGRATION WITH VENDOR HUB PLUGIN v3.1.0+
 * 
 * This integration detects and uses the OHA Vendor Hub Plugin v3.1.0+ which includes:
 * - Complete vendor application management
 * - Comprehensive sponsor functionality (Basic Event, In Kind, Custom)
 * - Card-based Board Hub UI with sponsor controls
 * - Real-time AJAX updates and filtering
 * - Stripe integration for automated payments
 * - Status tracking and history logging
 * 
 * If Vendor Hub Plugin is not active, shows fallback interface.
 */
if (!defined('ABSPATH')) exit;

// Ensure vendor_application CPT exists (fallback if Vendor Hub Plugin not active)
add_action('init', function(){
    if (!post_type_exists('vendor_application')){
        register_post_type('vendor_application', [
            'label' => 'Vendor Applications',
            'public' => false,
            'show_ui' => true,
            'supports' => ['title','custom-fields'],
            'menu_icon' => 'dashicons-forms'
        ]);
    }
});

// Basic status functions (compatibility layer)
function oha_vbh_status($post_id){
    $st = get_post_meta($post_id, '_oha_status', true);
    return $st ? $st : 'Submitted';
}
function oha_vbh_set_status($post_id,$st){
    update_post_meta($post_id, '_oha_status', sanitize_text_field($st));
}

// Frontend shortcode - DETECTS AND USES VENDOR HUB PLUGIN
add_shortcode('oha_vendor_board_hub', function($atts){
    if (!is_user_logged_in()) return '<div>Please log in.</div>';
    if (!current_user_can(get_option('oha_vbh_front_cap','edit_posts'))) return '<div>Insufficient permissions.</div>';
    
    // Check if the Vendor Hub Plugin is active and has sponsor functionality
    if (shortcode_exists('oha_vendor_boardhub')) {
        // Use the enhanced Vendor Hub Plugin with sponsor functionality
        return do_shortcode('[oha_vendor_boardhub]');
    }
    
    // Fallback to simple interface if Vendor Hub Plugin not active
    $q = new WP_Query(['post_type'=>'vendor_application','posts_per_page'=>-1,'orderby'=>'date','order'=>'DESC']);
    ob_start(); ?>
    <div class="wrap">
        <h2>Vendor Applications – Convention 2026</h2>
        <div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin-bottom: 20px;">
            <h3 style="margin-top: 0;">🔌 Enhanced Vendor & Sponsor Management Available!</h3>
            <p><strong>Upgrade Required:</strong> The enhanced vendor & sponsor management system requires the <strong>OHA Vendor Hub Plugin v3.1.0+</strong> to be active.</p>
            <p><strong>Enhanced Features Include:</strong></p>
            <ul>
                <li>✅ Complete sponsor management (Basic Event, In Kind, Custom sponsorships)</li>
                <li>✅ Card-based UI with real-time updates</li>
                <li>✅ Integrated Stripe payment processing</li>
                <li>✅ Advanced filtering and statistics</li>
                <li>✅ Automated status tracking and history</li>
            </ul>
            <p><em>Currently showing simplified view. Activate OHA Vendor Hub Plugin for full functionality.</em></p>
        </div>
        <table class="widefat"><thead><tr><th>Vendor</th><th>Status</th><th>Type</th></tr></thead><tbody>
        <?php if($q->have_posts()): while($q->have_posts()): $q->the_post(); 
            $sponsor_type = get_post_meta(get_the_ID(), '_sponsor_type', true);
            $type_display = $sponsor_type ? 'Sponsor (' . ucfirst($sponsor_type) . ')' : 'Vendor';
        ?>
          <tr>
              <td><?php the_title(); ?></td>
              <td><?php echo esc_html(oha_vbh_status(get_the_ID())); ?></td>
              <td><?php echo esc_html($type_display); ?></td>
          </tr>
        <?php endwhile; else: ?>
          <tr><td colspan="3">No applications yet.</td></tr>
        <?php endif; wp_reset_postdata(); ?>
        </tbody></table>
    </div>
    <?php return ob_get_clean();
});

// Render quick statistics
function oha_vbh_render_quick_stats() {
    global $wpdb;
    
    // Get counts
    $total_apps = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'vendor_application' AND post_status != 'trash'");
    $vendors_only = $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = '_sponsor_type' WHERE p.post_type = 'vendor_application' AND p.post_status != 'trash' AND (m.meta_value IS NULL OR m.meta_value = '')");
    $sponsors = $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE p.post_type = 'vendor_application' AND p.post_status != 'trash' AND m.meta_key = '_sponsor_type' AND m.meta_value != ''");
    $paid_vendors = $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE p.post_type = 'vendor_application' AND p.post_status != 'trash' AND m.meta_key = '_oha_status' AND m.meta_value = 'Paid'");
    $paid_sponsors = $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m1 ON p.ID = m1.post_id INNER JOIN {$wpdb->postmeta} m2 ON p.ID = m2.post_id WHERE p.post_type = 'vendor_application' AND p.post_status != 'trash' AND m1.meta_key = '_sponsor_type' AND m1.meta_value != '' AND m2.meta_key = '_sponsor_status' AND m2.meta_value = 'paid'");
    
    ob_start();
    ?>
    <div class="oha-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0; padding: 20px; background: #f9f9f9; border-radius: 8px;">
        <div class="stat-card" style="background: white; padding: 15px; border-radius: 6px; text-align: center; border-left: 4px solid #2271b1;">
            <div style="font-size: 24px; font-weight: bold; color: #2271b1;"><?php echo $total_apps; ?></div>
            <div style="color: #666; font-size: 14px;">Total Applications</div>
        </div>
        <div class="stat-card" style="background: white; padding: 15px; border-radius: 6px; text-align: center; border-left: 4px solid #00a32a;">
            <div style="font-size: 24px; font-weight: bold; color: #00a32a;"><?php echo $vendors_only; ?></div>
            <div style="color: #666; font-size: 14px;">Vendors Only</div>
        </div>
        <div class="stat-card" style="background: white; padding: 15px; border-radius: 6px; text-align: center; border-left: 4px solid #dba617;">
            <div style="font-size: 24px; font-weight: bold; color: #dba617;"><?php echo $sponsors; ?></div>
            <div style="color: #666; font-size: 14px;">Total Sponsors</div>
        </div>
        <div class="stat-card" style="background: white; padding: 15px; border-radius: 6px; text-align: center; border-left: 4px solid #72aee6;">
            <div style="font-size: 24px; font-weight: bold; color: #72aee6;"><?php echo $paid_vendors; ?></div>
            <div style="color: #666; font-size: 14px;">Paid Vendors</div>
        </div>
        <div class="stat-card" style="background: white; padding: 15px; border-radius: 6px; text-align: center; border-left: 4px solid #f0b849;">
            <div style="font-size: 24px; font-weight: bold; color: #f0b849;"><?php echo $paid_sponsors; ?></div>
            <div style="color: #666; font-size: 14px;">Paid Sponsors</div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Render individual application card with sponsor controls
function oha_vbh_render_application_card($post_id, $show_sponsor_controls = true) {
    // Get all meta data
    $company_name = get_post_meta($post_id, 'company_name', true);
    $first_name = get_post_meta($post_id, 'first_name', true);
    $last_name = get_post_meta($post_id, 'last_name', true);
    $email = get_post_meta($post_id, 'email', true);
    $phone = get_post_meta($post_id, 'phone', true);
    $website = get_post_meta($post_id, 'website', true);
    $product_type = get_post_meta($post_id, 'product_type', true);
    $booths = get_post_meta($post_id, 'booths', true);
    $status = oha_vbh_status($post_id);
    
    // Sponsor-specific data
    $is_sponsor = oha_vbh_is_sponsor($post_id);
    $sponsor_type = oha_vbh_get_sponsor_type($post_id);
    $sponsor_amount = oha_vbh_get_sponsor_amount($post_id);
    $sponsor_status = oha_vbh_get_sponsor_status($post_id);
    
    // URLs
    $agreement_url = get_post_meta($post_id, '_agreement_url', true);
    $payment_url = get_post_meta($post_id, '_payment_url', true);
    
    // Logo/attachments
    $attachments = get_attached_media('image', $post_id);
    $has_logo = !empty($attachments);
    
    $display_name = $company_name ?: trim($first_name . ' ' . $last_name);
    $mailto = $email ? 'mailto:' . $email : '';
    
    ob_start();
    ?>
    <div class="oha-application-card <?php echo $is_sponsor ? 'sponsor-card' : 'vendor-card'; ?>" 
         data-post-id="<?php echo $post_id; ?>" 
         style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px; <?php echo $is_sponsor ? 'border-left: 4px solid #dba617;' : 'border-left: 4px solid #2271b1;'; ?>">
        
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <div>
                <h3 style="margin: 0; font-size: 18px;">
                    <?php echo esc_html($display_name); ?>
                    <?php if ($is_sponsor): ?>
                        <span class="sponsor-badge" style="background: #dba617; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; margin-left: 10px;">
                            <?php echo esc_html($sponsor_type ?: 'Sponsor'); ?>
                        </span>
                    <?php endif; ?>
                </h3>
                <div style="color: #666; font-size: 14px;">
                    Status: <strong><?php echo esc_html($status); ?></strong>
                    <?php if ($is_sponsor): ?>
                        | Sponsor Status: <strong><?php echo esc_html(ucfirst($sponsor_status)); ?></strong>
                    <?php endif; ?>
                </div>
            </div>
            <div style="text-align: right;">
                <?php if ($booths): ?>
                    <div style="background: #e7f3ff; color: #0073aa; padding: 4px 8px; border-radius: 4px; font-size: 12px; margin-bottom: 4px;">
                        <?php echo esc_html($booths); ?> Booth<?php echo $booths > 1 ? 's' : ''; ?>
                    </div>
                <?php endif; ?>
                <?php if ($sponsor_amount): ?>
                    <div style="background: #fff4e6; color: #dba617; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                        $<?php echo number_format(floatval($sponsor_amount), 2); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Details Grid -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
            <div>
                <strong>Contact:</strong><br>
                <?php echo esc_html($email); ?><br>
                <?php echo esc_html($phone); ?>
                <?php if ($website): ?>
                    <br><a href="<?php echo esc_url($website); ?>" target="_blank"><?php echo esc_html($website); ?></a>
                <?php endif; ?>
            </div>
            <div>
                <strong>Products/Services:</strong><br>
                <?php echo esc_html($product_type); ?>
            </div>
        </div>
        
        <!-- Sponsor Controls (if enabled) -->
        <?php if ($show_sponsor_controls): ?>
            <div class="sponsor-controls" style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                <h4 style="margin: 0 0 10px 0; font-size: 14px;">Sponsor Management</h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <div>
                        <label style="font-size: 12px; display: block; margin-bottom: 2px;">Sponsor Type:</label>
                        <select name="sponsor_type" onchange="ohaUpdateSponsorField(<?php echo $post_id; ?>, 'sponsor_type', this.value)" style="width: 100%; font-size: 12px;">
                            <option value="">Not a Sponsor</option>
                            <option value="basic" <?php selected($sponsor_type, 'basic'); ?>>Basic Event</option>
                            <option value="inkind" <?php selected($sponsor_type, 'inkind'); ?>>In Kind</option>
                            <option value="custom" <?php selected($sponsor_type, 'custom'); ?>>Custom</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 12px; display: block; margin-bottom: 2px;">Amount ($):</label>
                        <input type="number" name="sponsor_amount" value="<?php echo esc_attr($sponsor_amount); ?>" 
                               onchange="ohaUpdateSponsorField(<?php echo $post_id; ?>, 'sponsor_amount', this.value)" 
                               style="width: 100%; font-size: 12px;" step="0.01" min="0">
                    </div>
                    <div>
                        <label style="font-size: 12px; display: block; margin-bottom: 2px;">Sponsor Status:</label>
                        <select name="sponsor_status" onchange="ohaUpdateSponsorField(<?php echo $post_id; ?>, 'sponsor_status', this.value)" style="width: 100%; font-size: 12px;">
                            <option value="pending" <?php selected($sponsor_status, 'pending'); ?>>Pending</option>
                            <option value="sent" <?php selected($sponsor_status, 'sent'); ?>>Sent</option>
                            <option value="paid" <?php selected($sponsor_status, 'paid'); ?>>Paid</option>
                            <option value="facebook_ad_up" <?php selected($sponsor_status, 'facebook_ad_up'); ?>>Facebook Ad Up</option>
                            <option value="banner_done" <?php selected($sponsor_status, 'banner_done'); ?>>Banner Done</option>
                            <option value="assets_received" <?php selected($sponsor_status, 'assets_received'); ?>>Assets Received</option>
                        </select>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="action-buttons" style="display: flex; gap: 8px; flex-wrap: wrap;">
            <?php if ($email): ?>
                <a href="<?php echo esc_attr($mailto); ?>" class="button button-small">📧 Send Email</a>
            <?php endif; ?>
            
            <?php if ($agreement_url): ?>
                <a href="<?php echo esc_url($agreement_url); ?>" target="_blank" class="button button-small">📄 Agreement</a>
            <?php endif; ?>
            
            <?php if ($payment_url): ?>
                <a href="<?php echo esc_url($payment_url); ?>" target="_blank" class="button button-small">💳 Payment</a>
            <?php endif; ?>
            
            <?php if ($is_sponsor && $show_sponsor_controls): ?>
                <button type="button" class="button button-small" onclick="ohaGenerateSponsorPaymentLink(<?php echo $post_id; ?>)">
                    💰 Generate Payment Link
                </button>
            <?php endif; ?>
            
            <?php if (!$has_logo): ?>
                <button type="button" class="button button-small" onclick="ohaRequestLogo(<?php echo $post_id; ?>)">
                    🎨 Request Logo
                </button>
            <?php else: ?>
                <span style="color: #00a32a; font-size: 12px;">✅ Logo on file</span>
            <?php endif; ?>
            
            <button type="button" class="button button-small button-secondary" onclick="ohaToggleHistory(<?php echo $post_id; ?>)">
                📋 History
            </button>
        </div>
        
        <!-- History Section (initially hidden) -->
        <div id="history-<?php echo $post_id; ?>" class="history-section" style="display: none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
            <?php echo oha_vbh_render_history($post_id); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Frontend shortcode - NOW USES THE FANCY VENDOR HUB CARD UI
add_shortcode('oha_vendor_board_hub', function($atts){
    if (!is_user_logged_in()) return '<div>Please log in.</div>';
    if (!current_user_can(get_option('oha_vbh_front_cap','edit_posts'))) return '<div>Insufficient permissions.</div>';
    
    // Check if the fancy Vendor Hub shortcode exists
    if (shortcode_exists('oha_vendor_boardhub')) {
        // Use the fancy card-based UI from Vendor Hub plugin
        return do_shortcode('[oha_vendor_boardhub]');
    }
    
    // Use enhanced vendor hub with sponsor functionality
    return do_shortcode('[oha_vendor_hub_enhanced]');
});

// Enhanced vendor hub shortcode with sponsor functionality
add_shortcode('oha_vendor_hub_enhanced', function($atts){
    if (!is_user_logged_in()) return '<div>Please log in.</div>';
    if (!current_user_can(get_option('oha_vbh_front_cap','edit_posts'))) return '<div>Insufficient permissions.</div>';
    
    $atts = shortcode_atts([
        'view' => 'all', // all, vendors, sponsors
        'sponsor_controls' => 'true'
    ], $atts);
    
    // Handle AJAX actions for sponsor management
    if (isset($_POST['action']) && $_POST['action'] === 'oha_sponsor_update') {
        oha_vbh_handle_sponsor_ajax();
    }
    
    ob_start();
    
    // Get vendor applications with optional sponsor filtering
    $meta_query = [];
    if ($atts['view'] === 'sponsors') {
        $meta_query[] = [
            'key' => '_sponsor_type',
            'value' => '',
            'compare' => '!='
        ];
    } elseif ($atts['view'] === 'vendors') {
        $meta_query[] = [
            'key' => '_sponsor_type',
            'value' => '',
            'compare' => 'NOT EXISTS'
        ];
    }
    
    $query_args = [
        'post_type' => 'vendor_application',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
        'post_status' => ['publish', 'draft', 'private']
    ];
    
    if (!empty($meta_query)) {
        $query_args['meta_query'] = $meta_query;
    }
    
    $q = new WP_Query($query_args);
    
    ?>
    <div class="wrap oha-vendor-hub-enhanced">
        <div class="oha-vendor-header">
            <h2>Convention 2026 - Vendor & Sponsor Management</h2>
            
            <!-- Quick Stats -->
            <?php echo oha_vbh_render_quick_stats(); ?>
            
            <!-- Filter Controls -->
            <div class="oha-filter-controls" style="margin: 20px 0; display: flex; gap: 10px; align-items: center;">
                <label for="view-filter">View:</label>
                <select id="view-filter" onchange="ohaVendorFilterChange(this.value)">
                    <option value="all" <?php selected($atts['view'], 'all'); ?>>All Applications</option>
                    <option value="vendors" <?php selected($atts['view'], 'vendors'); ?>>Vendors Only</option>
                    <option value="sponsors" <?php selected($atts['view'], 'sponsors'); ?>>Sponsors Only</option>
                </select>
                
                <button type="button" class="button" onclick="location.reload()">Refresh</button>
            </div>
        </div>

        <!-- Applications Table -->
        <div class="oha-applications-grid">
            <?php if($q->have_posts()): ?>
                <?php while($q->have_posts()): $q->the_post(); ?>
                    <?php echo oha_vbh_render_application_card(get_the_ID(), $atts['sponsor_controls'] === 'true'); ?>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="oha-card">
                    <h3>No Applications Found</h3>
                    <p>No <?php echo esc_html($atts['view']); ?> applications found.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Sponsor Settings Panel -->
        <?php if ($atts['sponsor_controls'] === 'true'): ?>
            <div class="oha-sponsor-settings" style="margin-top: 30px;">
                <h3>Sponsor Settings</h3>
                <?php echo oha_vbh_render_sponsor_settings(); ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php echo oha_vbh_render_vendor_scripts(); ?>
    
    <?php
    wp_reset_postdata();
    return ob_get_clean();
});

// Render history for an application
function oha_vbh_render_history($post_id) {
    $history = get_post_meta($post_id, '_oha_history', true);
    if (!is_array($history)) $history = [];
    
    $history = array_reverse($history); // Show newest first
    
    ob_start();
    ?>
    <div class="history-list">
        <h5 style="margin: 0 0 10px 0;">Activity History</h5>
        <?php if (!empty($history)): ?>
            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 10px; background: #fafafa;">
                <?php foreach ($history as $entry): ?>
                    <div style="margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #eee; font-size: 13px;">
                        <strong><?php echo esc_html($entry['action']); ?></strong>
                        <br>
                        <span style="color: #666;">
                            <?php echo esc_html(date('M j, Y g:i A', strtotime($entry['timestamp']))); ?>
                            <?php if (isset($entry['user']) && $entry['user']): ?>
                                by <?php echo esc_html(get_userdata($entry['user'])->display_name ?? 'User #' . $entry['user']); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color: #666; font-style: italic; margin: 0;">No activity recorded.</p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// Render sponsor settings panel
function oha_vbh_render_sponsor_settings() {
    $settings = oha_vendor_get_sponsor_settings();
    
    ob_start();
    ?>
    <div class="sponsor-settings-panel" style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px;">
        <form method="post" action="" onsubmit="return ohaUpdateSponsorSettings(event)">
            <?php wp_nonce_field('oha_sponsor_settings', 'sponsor_nonce'); ?>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h4>Basic Event Sponsorship</h4>
                    <label style="display: block; margin-bottom: 10px;">
                        <span style="display: block; font-weight: 500; margin-bottom: 4px;">Display Name:</span>
                        <input type="text" name="basic_sponsor_name" value="<?php echo esc_attr($settings['basic_sponsor_name']); ?>" 
                               style="width: 100%;" placeholder="Basic Event Sponsorship">
                    </label>
                    <label style="display: block; margin-bottom: 10px;">
                        <span style="display: block; font-weight: 500; margin-bottom: 4px;">Price ($):</span>
                        <input type="number" name="basic_sponsor_price" value="<?php echo esc_attr($settings['basic_sponsor_price']); ?>" 
                               style="width: 100%;" step="0.01" min="0" placeholder="100">
                    </label>
                    <label style="display: block; margin-bottom: 10px;">
                        <span style="display: block; font-weight: 500; margin-bottom: 4px;">Stripe Product ID:</span>
                        <input type="text" name="basic_sponsor_product_id" value="<?php echo esc_attr($settings['basic_sponsor_product_id']); ?>" 
                               style="width: 100%;" placeholder="prod_xxxxxxxxx">
                    </label>
                </div>
                
                <div>
                    <h4>In Kind Sponsorship</h4>
                    <label style="display: block; margin-bottom: 10px;">
                        <span style="display: block; font-weight: 500; margin-bottom: 4px;">Display Name:</span>
                        <input type="text" name="inkind_sponsor_name" value="<?php echo esc_attr($settings['inkind_sponsor_name']); ?>" 
                               style="width: 100%;" placeholder="In Kind Sponsorship">
                    </label>
                    
                    <label style="display: block; margin-bottom: 10px;">
                        <span style="display: block; font-weight: 500; margin-bottom: 4px;">
                            <input type="checkbox" name="sponsor_webhook_enabled" value="1" <?php checked($settings['sponsor_webhook_enabled'], '1'); ?>>
                            Enable Stripe Webhook Processing
                        </span>
                    </label>
                </div>
            </div>
            
            <div style="margin-top: 20px; text-align: right;">
                <button type="submit" class="button button-primary">Save Sponsor Settings</button>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

// Render JavaScript for vendor/sponsor management
function oha_vbh_render_vendor_scripts() {
    ob_start();
    ?>
    <script type="text/javascript">
    // Filter functionality
    function ohaVendorFilterChange(view) {
        const url = new URL(window.location);
        url.searchParams.set('view', view);
        window.location.href = url.toString();
    }
    
    // Update sponsor field via AJAX
    function ohaUpdateSponsorField(postId, field, value) {
        const data = new FormData();
        data.append('action', 'oha_sponsor_field_update');
        data.append('post_id', postId);
        data.append('field', field);
        data.append('value', value);
        data.append('nonce', '<?php echo wp_create_nonce('oha_sponsor_update'); ?>');
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: data
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Field updated successfully');
                // Optionally show success message
                ohaShowMessage('Field updated successfully', 'success');
            } else {
                console.error('Failed to update field:', data.data);
                ohaShowMessage('Failed to update field', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            ohaShowMessage('Network error occurred', 'error');
        });
    }
    
    // Generate payment link for sponsor
    function ohaGenerateSponsorPaymentLink(postId) {
        if (!confirm('Generate Stripe payment link for this sponsor?')) return;
        
        const data = new FormData();
        data.append('action', 'oha_sponsor_payment_link');
        data.append('post_id', postId);
        data.append('nonce', '<?php echo wp_create_nonce('oha_sponsor_update'); ?>');
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: data
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.data.url) {
                    window.open(data.data.url, '_blank');
                    ohaShowMessage('Payment link generated and opened', 'success');
                } else {
                    ohaShowMessage('Payment link generated', 'success');
                }
            } else {
                ohaShowMessage('Failed to generate payment link: ' + data.data, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            ohaShowMessage('Network error occurred', 'error');
        });
    }
    
    // Request logo from vendor
    function ohaRequestLogo(postId) {
        const email = prompt('Enter email address to request logo:');
        if (!email) return;
        
        const data = new FormData();
        data.append('action', 'oha_sponsor_request_logo');
        data.append('post_id', postId);
        data.append('email', email);
        data.append('nonce', '<?php echo wp_create_nonce('oha_sponsor_update'); ?>');
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: data
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                ohaShowMessage('Logo request sent successfully', 'success');
            } else {
                ohaShowMessage('Failed to send logo request: ' + data.data, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            ohaShowMessage('Network error occurred', 'error');
        });
    }
    
    // Toggle history visibility
    function ohaToggleHistory(postId) {
        const historySection = document.getElementById('history-' + postId);
        if (historySection) {
            historySection.style.display = historySection.style.display === 'none' ? 'block' : 'none';
        }
    }
    
    // Update sponsor settings
    function ohaUpdateSponsorSettings(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        formData.append('action', 'oha_sponsor_settings_update');
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                ohaShowMessage('Sponsor settings saved successfully', 'success');
            } else {
                ohaShowMessage('Failed to save settings: ' + data.data, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            ohaShowMessage('Network error occurred', 'error');
        });
        
        return false;
    }
    
    // Show temporary message
    function ohaShowMessage(message, type) {
        const messageDiv = document.createElement('div');
        messageDiv.style.cssText = `
            position: fixed; top: 20px; right: 20px; z-index: 9999;
            padding: 10px 15px; border-radius: 4px; color: white; font-weight: 500;
            background: ${type === 'success' ? '#00a32a' : '#d63638'};
        `;
        messageDiv.textContent = message;
        
        document.body.appendChild(messageDiv);
        
        setTimeout(() => {
            document.body.removeChild(messageDiv);
        }, 3000);
    }
    </script>
    <?php
    return ob_get_clean();
}

// AJAX handler for sponsor field updates
add_action('wp_ajax_oha_sponsor_field_update', 'oha_vbh_handle_sponsor_field_update');
function oha_vbh_handle_sponsor_field_update() {
    if (!wp_verify_nonce($_POST['nonce'], 'oha_sponsor_update')) {
        wp_die(json_encode(['success' => false, 'data' => 'Invalid nonce']));
    }
    
    if (!current_user_can('edit_posts')) {
        wp_die(json_encode(['success' => false, 'data' => 'Insufficient permissions']));
    }
    
    $post_id = intval($_POST['post_id']);
    $field = sanitize_text_field($_POST['field']);
    $value = sanitize_text_field($_POST['value']);
    
    switch ($field) {
        case 'sponsor_type':
            if ($value) {
                oha_vbh_set_sponsor_type($post_id, $value);
            } else {
                delete_post_meta($post_id, '_sponsor_type');
                delete_post_meta($post_id, '_sponsor_amount');
                delete_post_meta($post_id, '_sponsor_status');
            }
            break;
            
        case 'sponsor_amount':
            oha_vbh_set_sponsor_amount($post_id, floatval($value));
            break;
            
        case 'sponsor_status':
            oha_vbh_set_sponsor_status($post_id, $value);
            break;
    }
    
    wp_die(json_encode(['success' => true]));
}

// AJAX handler for generating sponsor payment links
add_action('wp_ajax_oha_sponsor_payment_link', 'oha_vbh_handle_sponsor_payment_link');
function oha_vbh_handle_sponsor_payment_link() {
    if (!wp_verify_nonce($_POST['nonce'], 'oha_sponsor_update')) {
        wp_die(json_encode(['success' => false, 'data' => 'Invalid nonce']));
    }
    
    if (!current_user_can('edit_posts')) {
        wp_die(json_encode(['success' => false, 'data' => 'Insufficient permissions']));
    }
    
    $post_id = intval($_POST['post_id']);
    $sponsor_type = oha_vbh_get_sponsor_type($post_id);
    $sponsor_amount = oha_vbh_get_sponsor_amount($post_id);
    $settings = oha_vendor_get_sponsor_settings();
    
    if (!$sponsor_type) {
        wp_die(json_encode(['success' => false, 'data' => 'Not a sponsor']));
    }
    
    // For basic sponsorships, use Stripe product if configured
    if ($sponsor_type === 'basic' && !empty($settings['basic_sponsor_product_id'])) {
        $payment_url = oha_vbh_generate_stripe_payment_link($settings['basic_sponsor_product_id'], $sponsor_amount ?: $settings['basic_sponsor_price']);
    } else {
        // For in-kind or custom, create invoice
        $payment_url = oha_vbh_generate_stripe_invoice($post_id, $sponsor_amount ?: 100);
    }
    
    if ($payment_url) {
        // Save payment URL to application
        update_post_meta($post_id, '_payment_url', $payment_url);
        
        oha_vbh_append_history($post_id, [
            'action' => 'Payment link generated: ' . $payment_url
        ]);
        
        wp_die(json_encode(['success' => true, 'data' => ['url' => $payment_url]]));
    } else {
        wp_die(json_encode(['success' => false, 'data' => 'Failed to generate payment link']));
    }
}

// AJAX handler for logo requests
add_action('wp_ajax_oha_sponsor_request_logo', 'oha_vbh_handle_logo_request');
function oha_vbh_handle_logo_request() {
    if (!wp_verify_nonce($_POST['nonce'], 'oha_sponsor_update')) {
        wp_die(json_encode(['success' => false, 'data' => 'Invalid nonce']));
    }
    
    if (!current_user_can('edit_posts')) {
        wp_die(json_encode(['success' => false, 'data' => 'Insufficient permissions']));
    }
    
    $post_id = intval($_POST['post_id']);
    $email = sanitize_email($_POST['email']);
    
    if (!$email) {
        wp_die(json_encode(['success' => false, 'data' => 'Invalid email address']));
    }
    
    // Get vendor/sponsor info
    $company_name = get_post_meta($post_id, 'company_name', true);
    $first_name = get_post_meta($post_id, 'first_name', true);
    $last_name = get_post_meta($post_id, 'last_name', true);
    $display_name = $company_name ?: trim($first_name . ' ' . $last_name);
    
    // Send logo request email
    $subject = 'Logo Request - Ohio Haunters Association Convention 2026';
    $message = "Hello {$display_name},\n\n";
    $message .= "We need your company logo for promotional materials for the Ohio Haunters Association Convention 2026.\n\n";
    $message .= "Please reply to this email with your high-resolution logo file (PNG or PDF preferred).\n\n";
    $message .= "Thank you!\n";
    $message .= "Ohio Haunters Association";
    
    $sent = wp_mail($email, $subject, $message, ['Content-Type: text/plain; charset=UTF-8']);
    
    if ($sent) {
        oha_vbh_append_history($post_id, [
            'action' => 'Logo request sent to: ' . $email
        ]);
        
        wp_die(json_encode(['success' => true]));
    } else {
        wp_die(json_encode(['success' => false, 'data' => 'Failed to send email']));
    }
}

// AJAX handler for sponsor settings updates
add_action('wp_ajax_oha_sponsor_settings_update', 'oha_vbh_handle_sponsor_settings_update');
function oha_vbh_handle_sponsor_settings_update() {
    if (!wp_verify_nonce($_POST['sponsor_nonce'], 'oha_sponsor_settings')) {
        wp_die(json_encode(['success' => false, 'data' => 'Invalid nonce']));
    }
    
    if (!current_user_can('manage_options')) {
        wp_die(json_encode(['success' => false, 'data' => 'Insufficient permissions']));
    }
    
    $settings = [
        'basic_sponsor_name' => sanitize_text_field($_POST['basic_sponsor_name'] ?? 'Basic Event Sponsorship'),
        'basic_sponsor_price' => floatval($_POST['basic_sponsor_price'] ?? 100),
        'basic_sponsor_product_id' => sanitize_text_field($_POST['basic_sponsor_product_id'] ?? ''),
        'inkind_sponsor_name' => sanitize_text_field($_POST['inkind_sponsor_name'] ?? 'In Kind Sponsorship'),
        'sponsor_webhook_enabled' => isset($_POST['sponsor_webhook_enabled']) ? '1' : '0'
    ];
    
    oha_vendor_save_sponsor_settings($settings);
    
    wp_die(json_encode(['success' => true]));
}

// Stripe integration functions
function oha_vbh_generate_stripe_payment_link($product_id, $amount) {
    if (!function_exists('oha_sm_get')) {
        return false; // Stripe integration not available
    }
    
    $stripe_config = oha_sm_get();
    if (empty($stripe_config['secret_key'])) {
        return false;
    }
    
    // This would integrate with the existing Stripe system
    // For now, return a placeholder that would be replaced with actual Stripe Payment Link generation
    return "https://buy.stripe.com/placeholder-for-{$product_id}";
}

function oha_vbh_generate_stripe_invoice($post_id, $amount) {
    if (!function_exists('oha_sm_get')) {
        return false; // Stripe integration not available
    }
    
    // Get customer information
    $email = get_post_meta($post_id, 'email', true);
    $company_name = get_post_meta($post_id, 'company_name', true);
    $first_name = get_post_meta($post_id, 'first_name', true);
    $last_name = get_post_meta($post_id, 'last_name', true);
    
    if (!$email) return false;
    
    // This would integrate with the existing Stripe system to create an invoice
    // For now, return a placeholder that would be replaced with actual invoice generation
    return "https://invoice.stripe.com/i/placeholder-invoice-{$post_id}";
}

// Stripe webhook integration for sponsor payments
add_action('oha_stripe_webhook_invoice_payment_succeeded', 'oha_vbh_handle_sponsor_payment_webhook', 10, 2);
function oha_vbh_handle_sponsor_payment_webhook($invoice, $event) {
    $settings = oha_vendor_get_sponsor_settings();
    if ($settings['sponsor_webhook_enabled'] !== '1') {
        return; // Webhook processing disabled
    }
    
    // Check if this is a sponsor-related payment by looking for specific patterns
    $customer_email = $invoice->customer_email ?? '';
    $amount_paid = ($invoice->amount_paid ?? 0) / 100; // Convert from cents
    
    if (!$customer_email) return;
    
    // Find matching vendor application by email
    $applications = get_posts([
        'post_type' => 'vendor_application',
        'meta_query' => [
            [
                'key' => 'email',
                'value' => $customer_email,
                'compare' => '='
            ]
        ],
        'posts_per_page' => -1,
        'post_status' => ['publish', 'draft', 'private']
    ]);
    
    foreach ($applications as $app) {
        $post_id = $app->ID;
        $sponsor_type = oha_vbh_get_sponsor_type($post_id);
        $sponsor_amount = oha_vbh_get_sponsor_amount($post_id);
        
        // Check if this could be a sponsor payment
        $is_sponsor_payment = false;
        
        if ($sponsor_type === 'basic' && abs($amount_paid - floatval($settings['basic_sponsor_price'])) < 0.01) {
            $is_sponsor_payment = true;
        } elseif ($sponsor_type && $sponsor_amount && abs($amount_paid - floatval($sponsor_amount)) < 0.01) {
            $is_sponsor_payment = true;
        }
        
        if ($is_sponsor_payment) {
            // Mark sponsor as paid
            oha_vbh_set_sponsor_status($post_id, 'paid');
            
            // Update main vendor status if not already paid
            $current_status = oha_vbh_status($post_id);
            if ($current_status !== 'Paid') {
                oha_vbh_set_status($post_id, 'Paid');
            }
            
            // Log the payment
            oha_vbh_append_history($post_id, [
                'action' => 'Sponsor payment received: $' . number_format($amount_paid, 2) . ' (Invoice: ' . $invoice->id . ')'
            ]);
            
            break; // Found matching application
        }
    }
}

// Inject into Convention 2026 tab via hooks if available
add_action('oha_board_hub_render_tab_convention_2026', function(){
    echo do_shortcode('[oha_vendor_board_hub]');
});
add_action('oha_board_hub_render_tab', function($slug){
    if ($slug === 'convention_2026') echo do_shortcode('[oha_vendor_board_hub]');
}, 10, 1);

// Optional: content-injection fallback by Page ID
add_filter('the_content', function($content){
    if (!is_user_logged_in()) return $content;
    if (!current_user_can(get_option('oha_vbh_front_cap','edit_posts'))) return $content;
    $page_id = (int) get_option('oha_vbh_convention_2026_page_id', 0);
    if ($page_id && is_page($page_id)){
        $dashboard = do_shortcode('[oha_vendor_board_hub]');
        $placeholder = 'Convention tools enabled (placeholder panel). We can reintroduce full UI once the Members tab is confirmed stable.';
        if (strpos($content, $placeholder) !== false){
            return str_replace($placeholder, $dashboard, $content);
        }
        return $content . $dashboard;
    }
    return $content;
}, 20);

// Enhanced settings with sponsor configuration
function oha_vbh_2026_settings(){
    if (isset($_POST['oha_vbh_nonce']) && wp_verify_nonce($_POST['oha_vbh_nonce'],'oha_vbh_save')){
        update_option('oha_vbh_front_cap', sanitize_text_field($_POST['front_cap'] ?? 'edit_posts'));
        update_option('oha_vbh_convention_2026_page_id', (int) ($_POST['page_id'] ?? 0));
        
        // Save sponsor settings
        if (isset($_POST['sponsor_settings'])) {
            $sponsor_settings = [
                'basic_sponsor_name' => sanitize_text_field($_POST['sponsor_settings']['basic_sponsor_name'] ?? 'Basic Event Sponsorship'),
                'basic_sponsor_price' => floatval($_POST['sponsor_settings']['basic_sponsor_price'] ?? 100),
                'basic_sponsor_product_id' => sanitize_text_field($_POST['sponsor_settings']['basic_sponsor_product_id'] ?? ''),
                'inkind_sponsor_name' => sanitize_text_field($_POST['sponsor_settings']['inkind_sponsor_name'] ?? 'In Kind Sponsorship'),
                'sponsor_webhook_enabled' => isset($_POST['sponsor_settings']['sponsor_webhook_enabled']) ? '1' : '0'
            ];
            oha_vendor_save_sponsor_settings($sponsor_settings);
        }
        
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }
    
    $front_cap = get_option('oha_vbh_front_cap','edit_posts');
    $page_id = (int) get_option('oha_vbh_convention_2026_page_id', 0);
    $sponsor_settings = oha_vendor_get_sponsor_settings();
    
    echo '<div class="wrap">';
    echo '<h1>Vendor & Sponsor Management – Convention 2026</h1>';
    echo '<form method="post">';
    wp_nonce_field('oha_vbh_save','oha_vbh_nonce');
    
    echo '<h2>General Settings</h2>';
    echo '<table class="form-table">';
    echo '<tr><th>Frontend Capability</th><td><input type="text" name="front_cap" value="'.esc_attr($front_cap).'"/><p class="description">WordPress capability required to access vendor/sponsor management.</p></td></tr>';
    echo '<tr><th>Convention 2026 Page ID</th><td><input type="number" name="page_id" value="'.esc_attr($page_id).'"/><p class="description">Auto-inject vendor hub on this page ID.</p></td></tr>';
    echo '</table>';
    
    echo '<h2>Sponsor Configuration</h2>';
    echo '<table class="form-table">';
    echo '<tr><th>Basic Sponsorship Name</th><td><input type="text" name="sponsor_settings[basic_sponsor_name]" value="'.esc_attr($sponsor_settings['basic_sponsor_name']).'" style="width: 300px;"/><p class="description">Display name for basic event sponsorship.</p></td></tr>';
    echo '<tr><th>Basic Sponsorship Price ($)</th><td><input type="number" name="sponsor_settings[basic_sponsor_price]" value="'.esc_attr($sponsor_settings['basic_sponsor_price']).'" step="0.01" min="0" style="width: 100px;"/><p class="description">Default price for basic event sponsorship.</p></td></tr>';
    echo '<tr><th>Basic Sponsorship Stripe Product ID</th><td><input type="text" name="sponsor_settings[basic_sponsor_product_id]" value="'.esc_attr($sponsor_settings['basic_sponsor_product_id']).'" style="width: 300px;" placeholder="prod_xxxxxxxxx"/><p class="description">Stripe Product ID for automated payment links. Leave empty to use manual invoicing.</p></td></tr>';
    echo '<tr><th>In Kind Sponsorship Name</th><td><input type="text" name="sponsor_settings[inkind_sponsor_name]" value="'.esc_attr($sponsor_settings['inkind_sponsor_name']).'" style="width: 300px;"/><p class="description">Display name for in-kind sponsorship.</p></td></tr>';
    echo '<tr><th>Webhook Processing</th><td><label><input type="checkbox" name="sponsor_settings[sponsor_webhook_enabled]" value="1" '.checked($sponsor_settings['sponsor_webhook_enabled'], '1', false).'/> Enable automatic sponsor payment detection via Stripe webhooks</label><p class="description">When enabled, sponsor payments will be automatically marked as paid when Stripe webhooks fire.</p></td></tr>';
    echo '</table>';
    
    submit_button('Save All Settings');
    echo '</form>';
    
    echo '<div class="card">';
    echo '<h2>Usage Instructions</h2>';
    echo '<h3>Shortcodes:</h3>';
    echo '<p><code>[oha_vendor_board_hub]</code> - Standard vendor management (automatically uses enhanced version with sponsor controls)</p>';
    echo '<p><code>[oha_vendor_hub_enhanced view="all"]</code> - Enhanced vendor/sponsor management</p>';
    echo '<p><code>[oha_vendor_hub_enhanced view="vendors"]</code> - Show vendors only</p>';
    echo '<p><code>[oha_vendor_hub_enhanced view="sponsors"]</code> - Show sponsors only</p>';
    
    echo '<h3>Sponsor Workflow:</h3>';
    echo '<ol>';
    echo '<li><strong>Convert Vendor to Sponsor:</strong> Use the sponsor controls to set sponsor type (Basic/In Kind/Custom)</li>';
    echo '<li><strong>Set Amount:</strong> Enter the sponsorship amount (auto-filled for Basic sponsorships)</li>';
    echo '<li><strong>Generate Payment Link:</strong> Click "Generate Payment Link" to create Stripe payment or invoice</li>';
    echo '<li><strong>Track Status:</strong> Use sponsor status field to track progress (Sent → Paid → Facebook Ad Up → Banner Done → Assets Received)</li>';
    echo '<li><strong>Request Assets:</strong> Use "Request Logo" button to email sponsors for logo files</li>';
    echo '</ol>';
    
    echo '<h3>Stripe Integration:</h3>';
    echo '<p><strong>Basic Sponsorship:</strong> Uses Stripe Product (if configured) for automated payment links</p>';
    echo '<p><strong>In Kind/Custom:</strong> Creates Stripe invoices with custom amounts</p>';
    echo '<p><strong>Webhook Processing:</strong> Automatically marks sponsors as paid when payments are received</p>';
    echo '</div>';
    
    echo '</div>';
}

// Vendor settings page disabled - using main OHA Board Hub instead
// This prevents duplicate "OHA Membership" page in Settings menu
/*
add_action('admin_menu', function() {
    add_submenu_page(
        'options-general.php',
        'Vendor & Sponsor Management',
        'Vendor & Sponsors',
        'manage_options',
        'oha-vendor-sponsor-settings',
        'oha_vbh_2026_settings'
    );
}, 20);
*/

// Add vendor/sponsor statistics to Board Hub overview cards
add_filter('oha_board_hub_vendor_stats', function($stats) {
    global $wpdb;
    
    // Enhanced vendor statistics with sponsor breakdown
    $total_vendors = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'vendor_application' AND post_status != 'trash'");
    $vendors_only = $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = '_sponsor_type' WHERE p.post_type = 'vendor_application' AND p.post_status != 'trash' AND (m.meta_value IS NULL OR m.meta_value = '')");
    $sponsors = $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE p.post_type = 'vendor_application' AND p.post_status != 'trash' AND m.meta_key = '_sponsor_type' AND m.meta_value != ''");
    $paid_vendors = $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id WHERE p.post_type = 'vendor_application' AND p.post_status != 'trash' AND m.meta_key = '_oha_status' AND m.meta_value = 'Paid'");
    $paid_sponsors = $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m1 ON p.ID = m1.post_id INNER JOIN {$wpdb->postmeta} m2 ON p.ID = m2.post_id WHERE p.post_type = 'vendor_application' AND p.post_status != 'trash' AND m1.meta_key = '_sponsor_type' AND m1.meta_value != '' AND m2.meta_key = '_sponsor_status' AND m2.meta_value = 'paid'");
    
    return [
        'total_applications' => $total_vendors,
        'vendors_only' => $vendors_only,
        'sponsors' => $sponsors,
        'paid_vendors' => $paid_vendors,
        'paid_sponsors' => $paid_sponsors
    ];
});

// Export enhancement for sponsors
add_action('oha_board_hub_vendor_export', function($post_id, $meta) {
    // Add sponsor-specific fields to export
    if (oha_vbh_is_sponsor($post_id)) {
        $meta['sponsor_type'] = oha_vbh_get_sponsor_type($post_id);
        $meta['sponsor_amount'] = oha_vbh_get_sponsor_amount($post_id);
        $meta['sponsor_status'] = oha_vbh_get_sponsor_status($post_id);
    }
    return $meta;
});

// CSS for enhanced vendor hub
add_action('wp_head', function() {
    if (!is_admin() && (current_user_can('edit_posts') || current_user_can('manage_options'))) {
        ?>
        <style>
        .oha-vendor-hub-enhanced .sponsor-card {
            box-shadow: 0 2px 8px rgba(219, 166, 23, 0.1);
        }
        .oha-vendor-hub-enhanced .vendor-card {
            box-shadow: 0 2px 8px rgba(34, 113, 177, 0.1);
        }
        .oha-applications-grid {
            display: grid;
            gap: 20px;
        }
        .sponsor-controls {
            border-left: 3px solid #dba617;
        }
        .oha-filter-controls select {
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .action-buttons .button {
            font-size: 12px;
            padding: 4px 8px;
            height: auto;
            line-height: 1.2;
        }
        .history-section {
            background: #fafafa;
            border-radius: 4px;
            padding: 10px;
        }
        @media (max-width: 768px) {
            .oha-applications-grid {
                grid-template-columns: 1fr;
            }
            .sponsor-controls {
                grid-template-columns: 1fr !important;
            }
        }
        </style>
        <?php
    }
});

?>
