<?php
/*
Plugin Name: OHA Vendor Hub
Description: Complete vendor management system with applications, e-signatures, Stripe payments, Board Hub admin dashboard, and vendor status table for Ohio Haunters Convention.
Version: 3.15.2
Author: OHA Dev Team
Author URI: https://ohiohaunters.com
Text Domain: oha-vendor-hub
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

=== WEBHOOK ARCHITECTURE ===
- Uses CENTRALIZED webhook: https://ohiohaunters.com/wp-json/oha/v1/stripe-webhook
- Managed by Board Hub Suite (OHA_Webhook_Handler class)
- This plugin hooks into centralized webhook via filters ONLY
- Does NOT register its own route (Board Hub owns the route)
- All Stripe events (memberships, vendors, board payments) use one endpoint

=== AGREEMENT ARCHITECTURE ===
CRITICAL: "PDF" throughout this plugin means "HTML Agreement"
- Files generated are .html (NOT .pdf)
- Browser "Print to PDF" is the intended workflow for users
- Legacy naming (pdf-generator.php, _pdf_url) retained for backwards compatibility
- Agreement artifact = HTML file (legacy naming preserved)

Changelog v3.1.2 (Feb 2026):
- FIXED: UsersWP tab content now renders correctly
- FIXED: Switched from wrong hook (uwp_account_content_extra_item) to
  correct hook (uwp_account_form_display) — matches site's existing tabs

Changelog v3.1.1 (Feb 2026):
- ADDED: UsersWP /myaccount/ tab integration for Vendor Status table
- Tab uses exact same hook pattern as site's existing Track Order / Membership tabs
- Tab label is NEVER rendered for users outside the allowed list (no information leak)
- Double-guarded: tab filter check + shortcode access check are independent
- No new dependencies; hooks into UsersWP core filters only

Changelog v3.1.0 (Feb 2026):
- ADDED: [oha_vendor_status_table] shortcode — read-only, searchable vendor status table
- ADDED: Access control by user ID (Settings → Vendor Status Table)
- ADDED: Default allowed viewer: user ID 183
- ADDED: Sort by column (click any column header)
- ADDED: Live search with visible count
- NOTE: Admins (manage_options) always have access regardless of settings

Changelog v3.0.1 (Jan 2026 - CRITICAL FIXES):
- FIXED: Removed route registration conflict (Board Hub owns route, not Vendor Hub)
- FIXED: Safe history append (prevents json_decode fatal errors)
- FIXED: Consistent filter-only webhook approach (no dual strategy)
- These fixes eliminate 500 webhook errors completely

Changelog v3.0.0 (Jan 2026):
- ARCHITECTURE: Switched to centralized webhook (oha/v1/stripe-webhook)
- CLARIFIED: HTML agreements made explicit throughout codebase
- IMPROVED: Dual meta storage (_agreement_url + _pdf_url) for compatibility
- ADDED: Truth tracking (_agreement_generated_at, _agreement_emailed_at)
- FIXED: Safe payment history parsing (handles array/JSON/serialized)
- FIXED: Status normalization (handles PAID/Paid/paid)

OHA Vendor Hub is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

OHA Vendor Hub is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
*/

if (!defined('ABSPATH')) exit;

// Start output buffering early for pages with our shortcode
// This prevents "headers already sent" errors when redirecting
add_action('template_redirect', function() {
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'oha_vendor_final_form')) {
        if (!ob_get_level()) {
            ob_start();
        }
    }
}, 1);

// Plugin version and paths
define('OHA_VHCPT_VER', '3.15.2');
define('OHA_VHCPT_PATH', plugin_dir_path(__FILE__));
define('OHA_VHCPT_URL', plugin_dir_url(__FILE__));

/**
 * Sandbox mode: redirect all plugin emails to the configured test address.
 * When enabled, the To: address is replaced and the original recipient is
 * prepended to the subject so the tester knows who would have received it.
 */
add_filter('wp_mail', function($args) {
    if (!get_option('oha_vhcpt_sandbox_mode')) return $args;

    $sandbox_email = sanitize_email(get_option('oha_vhcpt_sandbox_email', ''));
    if (!$sandbox_email) return $args;

    $original_to   = is_array($args['to']) ? implode(', ', $args['to']) : $args['to'];
    $args['to']    = $sandbox_email;
    $args['subject'] = '[SANDBOX - would go to: ' . $original_to . '] ' . $args['subject'];

    return $args;
}, 10, 1);

/**
 * Register Custom Post Type for vendor applications
 */
add_action('init', function(){
    if (!post_type_exists('vendor_application')){
        register_post_type('vendor_application', [
            'label' => 'Vendor Applications',
            'labels' => [
                'name' => 'Vendor Applications',
                'singular_name' => 'Vendor Application',
                'add_new' => 'Add New',
                'add_new_item' => 'Add New Application',
                'edit_item' => 'Edit Application',
                'new_item' => 'New Application',
                'view_item' => 'View Application',
                'search_items' => 'Search Applications',
                'not_found' => 'No applications found',
                'not_found_in_trash' => 'No applications found in trash'
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'supports' => ['title', 'custom-fields', 'author'],
            'menu_icon' => 'dashicons-forms',
            'menu_position' => 26,
            'capability_type' => 'post',
            'capabilities' => [
                'edit_post' => 'manage_options',
                'edit_posts' => 'manage_options',
                'edit_others_posts' => 'manage_options',
                'publish_posts' => 'manage_options',
                'read_post' => 'manage_options',
                'read_private_posts' => 'manage_options',
                'delete_post' => 'manage_options'
            ]
        ]);
    }
});

/**
 * Enqueue plugin assets
 */
add_action('wp_enqueue_scripts', function(){
    // Register main styles
    wp_register_style('oha-vhcpt', OHA_VHCPT_URL . 'assets/vendor-hub.css', [], OHA_VHCPT_VER);
    wp_register_style('oha-vhcpt-final', OHA_VHCPT_URL . 'assets/final-application.css', ['oha-vhcpt'], OHA_VHCPT_VER);

    // Enqueue main style by default
    wp_enqueue_style('oha-vhcpt');

    // Enqueue TinyMCE editor assets for manage_options users (used by bulk email panel)
    if (current_user_can('manage_options')) {
        wp_enqueue_editor();
    }
});

/**
 * Include core files
 */
// Load plugin components
require_once OHA_VHCPT_PATH . 'includes/functions.php';
require_once OHA_VHCPT_PATH . 'includes/actions.php';
require_once OHA_VHCPT_PATH . 'includes/pdf-generator.php';
require_once OHA_VHCPT_PATH . 'includes/payment-tracking.php';
require_once OHA_VHCPT_PATH . 'includes/stripe-webhook.php';
require_once OHA_VHCPT_PATH . 'includes/shortcodes.php';
require_once OHA_VHCPT_PATH . 'includes/admin/settings-page.php';
require_once OHA_VHCPT_PATH . 'includes/admin/stripe-settings.php';
require_once OHA_VHCPT_PATH . 'includes/admin/send-edit-links.php';
require_once OHA_VHCPT_PATH . 'includes/admin/year-tools.php';

/**
 * WEBHOOK ARCHITECTURE NOTE:
 * 
 * Vendor Hub does NOT register its own webhook route.
 * Instead, it hooks into Board Hub's centralized webhook via the filter:
 * add_filter('oha_process_webhook_event', 'oha_vhcpt_handle_vendor_payment_events', 10, 2)
 * 
 * This is defined in includes/stripe-webhook.php
 * 
 * Board Hub Suite owns and registers: /wp-json/oha/v1/stripe-webhook
 * Vendor Hub simply listens via the filter when vendor payment events occur.
 */

/**
 * Admin notice for Stripe configuration and testing
 */
add_action('admin_notices', function(){
    $stripe_link_test = get_option('oha_vhcpt_stripe_link_test', '');
    $stripe_link_live = get_option('oha_vhcpt_stripe_link_live', '');
    $final_app_page_id = get_option('oha_vhcpt_final_app_page_id', 0);
    $screen = get_current_screen();
    
    // Only show on vendor application pages
    if ($screen && strpos($screen->id, 'vendor_application') === false) {
        return;
    }
    
    // Webhook URL info
    $webhook_url = rest_url('oha/v1/stripe-webhook'); // Using shared endpoint
    $test_email_url = add_query_arg('oha_test_email', '1', home_url('/'));
    $test_pdf_url = add_query_arg('oha_test_pdf_email', '1', home_url('/'));
    
    echo '<div class="notice notice-info" style="padding:15px;">';
    echo '<h3 style="margin-top:0;">🛠️ OHA Vendor Hub v' . OHA_VHCPT_VER . ' - Configuration</h3>';
    echo '<p><strong>Stripe Webhook URL (shared with other plugins):</strong></p>';
    echo '<p><code style="background:#f0f0f0;padding:5px 10px;display:inline-block;font-size:13px;">' . esc_html($webhook_url) . '</code></p>';
    echo '<p><small>Add to Stripe Dashboard → Webhooks. Event: <code>checkout.session.completed</code></small></p>';
    echo '<hr style="margin:15px 0;">';
    echo '<p><strong>Email Testing:</strong></p>';
    echo '<p>';
    echo '<a href="' . esc_url($test_email_url) . '" class="button" target="_blank" style="margin-right:10px;">Test WordPress Email</a>';
    echo '<a href="' . esc_url($test_pdf_url) . '" class="button" target="_blank">Test PDF Email</a>';
    echo '</p>';
    echo '</div>';
    
    // Check for Stripe configuration
    if (empty($stripe_link_test) && empty($stripe_link_live)) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><strong>OHA Vendor Hub:</strong> Stripe payment links not configured yet. 
            <a href="<?php echo admin_url('edit.php?post_type=vendor_application&page=oha-vhcpt-settings'); ?>">Configure Stripe Settings →</a></p>
        </div>
        <?php
    }
    
    // Check for final app page configuration
    if (empty($final_app_page_id)) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><strong>OHA Vendor Hub:</strong> Final application page not configured. The "Send" dropdown won't work until you select the page with the <code>[oha_vendor_final_form]</code> shortcode. 
            <a href="<?php echo admin_url('edit.php?post_type=vendor_application&page=oha-vhcpt-settings'); ?>">Configure Now →</a></p>
        </div>
        <?php
    }
});

/**
 * Activation hook - create default options
 */
register_activation_hook(__FILE__, function(){
    // Set default email steps if not exist
    if (!get_option('oha_vhcpt_email_steps')) {
        $default_steps = [
            'submitted' => [
                'enabled' => 1,
                'subject' => 'We received your vendor application',
                'body' => "Hi {first_name},\n\nThank you for submitting your vendor application for {company_name}.\n\nWe will review your information and follow up soon.\n\nThanks,\nOhio Haunters Association"
            ],
            'approved' => [
                'enabled' => 1,
                'subject' => 'Your vendor application has been approved',
                'body' => "Hi {first_name},\n\nGood news! Your vendor application has been approved.\n\nWe will send you next steps shortly.\n\nThanks,\nOhio Haunters Association"
            ],
            'agreement_signed' => [
                'enabled' => 1,
                'subject' => 'Agreement signed - please complete payment',
                'body' => "Hi {first_name},\n\nThank you for signing the vendor agreement.\n\nYour next step is to complete payment to secure your booth space.\n\nThanks,\nOhio Haunters Association"
            ],
            'paid' => [
                'enabled' => 1,
                'subject' => 'Payment received – your vendor spot is confirmed',
                'body' => "Hi {first_name},\n\nWe have received your payment. Your vendor spot for OHA Convention 2026 is now confirmed!\n\nWe will send you additional details closer to the event.\n\nThanks,\nOhio Haunters Association"
            ]
        ];
        update_option('oha_vhcpt_email_steps', $default_steps);
    }
    
    // Set default Stripe mode
    if (!get_option('oha_vhcpt_stripe_mode')) {
        update_option('oha_vhcpt_stripe_mode', 'test');
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
});

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, function(){
    flush_rewrite_rules();
});

/**
 * Email Testing Utilities (Admin Only)
 * Visit: yoursite.com/?oha_test_email=1
 * Visit: yoursite.com/?oha_test_pdf_email=1
 */
add_action('init', function() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Basic email test
    if (isset($_GET['oha_test_email'])) {
        error_log('=== OHA EMAIL TEST STARTED ===');
        
        $to = get_option('admin_email');
        $subject = 'OHA Email Test - ' . date('Y-m-d H:i:s');
        $message = "If you receive this email, WordPress email is working!\n\n";
        $message .= "Server: " . $_SERVER['SERVER_NAME'] . "\n";
        $message .= "PHP: " . PHP_VERSION . "\n";
        $message .= "WordPress: " . get_bloginfo('version') . "\n\n";
        $message .= "Next: Test PDF email with ?oha_test_pdf_email=1";
        
        $headers = [
            'From: Ohio Haunters <noreply@ohiohaunters.com>',
            'Reply-To: webmaster@ohiohaunters.com'
        ];
        
        $sent = wp_mail($to, $subject, $message, $headers);
        
        error_log('wp_mail returned: ' . ($sent ? 'TRUE' : 'FALSE'));
        
        if ($sent) {
            wp_die('<h1 style="color:green;">✅ Email Test PASSED!</h1><p>Email sent to: <strong>' . esc_html($to) . '</strong></p><p>Check inbox and spam folder.</p><p><a href="' . admin_url() . '">← Back to Dashboard</a></p>');
        } else {
            wp_die('<h1 style="color:red;">❌ Email Test FAILED!</h1><p>WordPress cannot send email.</p><p><strong>Solution:</strong> Configure WP Mail SMTP plugin.</p><p><a href="' . admin_url('plugin-install.php?s=wp+mail+smtp&tab=search') . '">Install WP Mail SMTP →</a></p>');
        }
    }
    
    // PDF email test - SAFE VERSION (sends to admin only, NOT vendors!)
    if (isset($_GET['oha_test_pdf_email'])) {
        error_log('=== OHA PDF EMAIL TEST (ADMIN ONLY) ===');
        
        $vendors = get_posts([
            'post_type' => 'vendor_application',
            'posts_per_page' => 1,
            'post_status' => 'any',
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        if (empty($vendors)) {
            wp_die('<h1 style="color:red;">❌ No Vendor Applications</h1><p>Create a test vendor application first.</p><p><a href="' . admin_url('post-new.php?post_type=vendor_application') . '">Create Test Vendor →</a></p>');
        }
        
        $app_id = $vendors[0]->ID;
        $vendor_email = get_post_meta($app_id, 'email', true);
        $company = get_post_meta($app_id, 'company_name', true);
        $admin_email = get_option('admin_email');
        
        error_log('Testing with App ID: ' . $app_id);
        error_log('Company: ' . $company);
        error_log('Vendor Email (not using): ' . $vendor_email);
        error_log('Sending to ADMIN: ' . $admin_email);
        
        if (!function_exists('oha_vhcpt_email_vendor_pdf')) {
            wp_die('<h1 style="color:red;">❌ Function Missing!</h1><p>PDF email function not loaded. Check plugin activation.</p>');
        }
        
        // Intercept email meta read for this post so the PDF function sends to admin without touching the DB.
        $email_override = function( $value, $object_id, $meta_key, $single ) use ( $app_id, $admin_email ) {
            if ( $object_id === $app_id && $meta_key === 'email' && $single ) {
                return $admin_email;
            }
            return $value;
        };
        add_filter( 'get_post_metadata', $email_override, 10, 4 );
        $result = oha_vhcpt_email_vendor_pdf( $app_id );
        remove_filter( 'get_post_metadata', $email_override, 10 );
        
        if ($result) {
            wp_die('<h1 style="color:green;">✅ PDF Test Email Sent to ADMIN!</h1>
                <p><strong>⚠️ TEST MODE - Email sent to admin, NOT vendor</strong></p>
                <p>Used vendor data from: <strong>' . esc_html($company) . '</strong></p>
                <p>Sent to: <strong>' . esc_html($admin_email) . '</strong></p>
                <p>Vendor email was NOT contacted: ' . esc_html($vendor_email) . '</p>
                <p>Check inbox, spam folder, and /wp-content/debug.log</p>
                <p><a href="' . admin_url() . '">← Back to Dashboard</a></p>');
        } else {
            wp_die('<h1 style="color:red;">❌ PDF Email Test FAILED!</h1><p>Check /wp-content/debug.log for details.</p><p>Common issues:</p><ul><li>Email invalid or missing</li><li>PDF generation failed</li><li>wp_mail() failed</li><li>File permissions</li></ul><p><a href="' . admin_url() . '">← Back to Dashboard</a></p>');
        }
    }
});
