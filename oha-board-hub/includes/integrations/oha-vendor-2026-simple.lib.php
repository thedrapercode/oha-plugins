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

// Hardcoded tab hooks removed -- vendor hub is now added as a custom tab
// via Board Hub Settings > Tab Management with shortcode [oha_vendor_boardhub].

// Settings (minimal for Page ID + capability)
function oha_vbh_2026_settings(){
    if (isset($_POST['oha_vbh_nonce']) && wp_verify_nonce($_POST['oha_vbh_nonce'],'oha_vbh_save')){
        update_option('oha_vbh_front_cap', sanitize_text_field($_POST['front_cap'] ?? 'edit_posts'));
        update_option('oha_vbh_convention_2026_page_id', (int) ($_POST['page_id'] ?? 0));
        echo '<div class="updated"><p>Saved.</p></div>';
    }
    $front_cap = get_option('oha_vbh_front_cap','edit_posts');
    $page_id = (int) get_option('oha_vbh_convention_2026_page_id', 0);
    echo '<div class="wrap"><h1>Vendor & Sponsor Integration – Convention 2026</h1><form method="post">';
    wp_nonce_field('oha_vbh_save','oha_vbh_nonce');
    echo '<table class="form-table">';
    echo '<tr><th>Frontend Capability</th><td><input type="text" name="front_cap" value="'.esc_attr($front_cap).'"/><p class="description">WordPress capability required to access vendor/sponsor management.</p></td></tr>';
    echo '<tr><th>Convention 2026 Page ID</th><td><input type="number" name="page_id" value="'.esc_attr($page_id).'"/><p class="description">Auto-inject vendor hub on this page ID.</p></td></tr>';
    echo '</table>';
    submit_button();
    echo '</form>';
    echo '<div class="card">';
    echo '<h2>Integration Status</h2>';
    if (shortcode_exists('oha_vendor_boardhub')) {
        echo '<p style="color: #00a32a;"><strong>✅ Enhanced Integration Active</strong></p>';
        echo '<p>OHA Vendor Hub Plugin is active with vendor functionality.</p>';
        echo '<ul>';
        echo '<li>✅ Complete vendor application management</li>';
        echo '<li>✅ Comprehensive sponsor system (Basic Event, In Kind, Custom)</li>';
        echo '<li>✅ Card-based UI with real-time updates</li>';
        echo '<li>✅ Integrated Stripe payment processing</li>';
        echo '<li>✅ Advanced filtering and statistics</li>';
        echo '</ul>';
    } elseif (shortcode_exists('oha_vendor_boardhub')) {
        echo '<p style="color: #dba617;"><strong>⚠️ Basic Integration Active</strong></p>';
        echo '<p>OHA Vendor Hub Plugin is active but sponsor features may not be enabled.</p>';
        echo '<p>Check <em>Vendor Applications → Settings → Sponsor Management</em> to enable sponsor functionality.</p>';
    } else {
        echo '<p style="color: #d63638;"><strong>❌ Plugin Required</strong></p>';
        echo '<p>OHA Vendor Hub Plugin v3.1.0+ is required for full vendor and sponsor management.</p>';
        echo '<p>Currently showing fallback interface only.</p>';
    }
    echo '</div>';
    echo '<div class="card">';
    echo '<h2>Usage</h2>';
    echo '<p><strong>Shortcode:</strong> <code>[oha_vendor_board_hub]</code></p>';
    echo '<p>This shortcode automatically detects the OHA Vendor Hub Plugin and uses its enhanced features when available.</p>';
    echo '</div>';
}

// Add admin menu for vendor settings under Board Hub (if needed)
// Vendor integration settings page disabled - using main OHA Board Hub instead
/*
add_action('admin_menu', function() {
    // Only add if Board Hub Suite is active but Vendor Hub Plugin is not
    if (!shortcode_exists('oha_vendor_boardhub')) {
        add_submenu_page(
            'options-general.php',
            'Vendor & Sponsor Integration',
            'Vendor Integration',
            'manage_options',
            'oha-vendor-integration-settings',
            'oha_vbh_2026_settings'
        );
    }
}, 20);
*/
