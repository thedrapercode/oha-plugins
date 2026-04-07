<?php
/**
 * Plugin Name: OHA Board Hub Suite
 * Description: Unified admin suite for Ohio Haunters Association with centralized Stripe webhook hub. Combines board member management, Stripe integrations, vendor management, and haunt features. All Stripe events (memberships, vendors, products, board payments) flow through a single webhook endpoint.
 * Version: 2.3.1
 * Author: OHA
 * Text Domain: oha-board-hub
 * Domain Path: /languages
 *
 * === CENTRALIZED WEBHOOK ARCHITECTURE ===
 * Board Hub Suite owns THE SINGLE webhook endpoint for all Stripe events:
 * URL: https://ohiohaunters.com/wp-json/oha/v1/stripe-webhook
 *
 * WHAT THIS HANDLES:
 * - Membership subscriptions (customer.subscription.*)
 * - One-time payments (checkout.session.completed, charge.succeeded)
 * - Customer updates (customer.created, customer.updated)
 * - Invoice payments (invoice.payment_succeeded)
 * - ALL other Stripe events
 *
 * COMPANION PLUGIN INTEGRATION:
 * - OHA Vendor Hub v3.0.1+ hooks via filter (does NOT register its own route)
 * - Uses: apply_filters('oha_process_webhook_event', false, $event)
 * - If companion plugin returns true, Board Hub skips processing
 * - If companion plugin returns false, Board Hub processes with internal handlers
 *
 * WEBHOOK FLOW:
 * 1. Stripe → /wp-json/oha/v1/stripe-webhook (Board Hub receives)
 * 2. Signature validation + duplicate check
 * 3. Call filter for external plugins to process first
 * 4. If not handled externally, Board Hub processes
 * 5. Mark as processed to prevent duplicates
 *
 * === CHANGE LOG ===
 * v2.2.11 (Feb 2026) - STRIPE SETTINGS SAVE FIX:
 * - FIXED: Stripe settings now save reliably on every submission
 * - ROOT CAUSE: pre_option_oha_sm_settings filter was intercepting get_option() during
 *   update_option() comparison, causing WordPress to think the value was unchanged and
 *   silently skip the database write (updated_option hook never fired)
 * - FIX: Removed pre_option_oha_sm_settings filter; direct DB reads now allow proper
 *   old-value vs new-value comparison so update_option() saves correctly every time
 * - FIX: Added init_settings() to OHA_Admin_Page per handover document to ensure
 *   settings group registration is explicit on the admin page
 * - KEPT: updated_option mirror to suite settings still works (oha_sa_settings filter kept)
 * - KEPT: All other features, tabs, shortcodes, and webhook processing intact
 *
 * v2.2.8 (Feb 2026) - STRIPE SETTINGS FINAL FIX (v2.2.6 WORKING CODE + VENDOR HUB PROTECTION):
 * - FIXED: Applied WORKING v2.2.6 Stripe settings approach (WordPress Settings API)
 * - FIXED: Form uses action="options.php" with settings_fields('oha_sm') (WordPress standard)
 * - FIXED: Field names use oha_sm_settings[field_name] format (WordPress array handling)
 * - FIXED: Proper settings registration with 'type' => 'array' and sanitize callback
 * - FIXED: Vendor Hub conflict protection - detects and warns about plugin conflicts
 * - FIXED: Settings registration priority ensures Board Hub registers first
 * - WORKING: All Stripe fields save and persist correctly (secret key, webhook secret, price IDs)
 * - WORKING: Success message shows immediately after save via WordPress standard redirect
 * - WORKING: Configuration status indicators show ✅ for configured fields
 * - INCLUDES: All v2.2.7 features (smart button, shortcode reference) remain intact
 * - PRIORITY: Critical payment processing settings now function reliably with Vendor Hub
 *
 * v2.2.7 (Feb 2026) - STABLE SMART BUTTON RELEASE:
 * - FIXED: Critical bug where Stripe settings would not save when clicking "Save Settings"
 * - ADDED: Direct form processing to bypass WordPress settings API capability issues
 * - ENHANCED: Settings now save immediately and show success confirmation
 * - RESOLVED: Webhook secret, API keys, and Price IDs now persist correctly
 * - IMPROVED: Form uses nonce security and prevents resubmission
 * - TESTED: Same fix pattern that resolved identical Vendor Hub plugin issue
 * - PRIORITY: All Stripe settings (secret key, webhook secret, price IDs) save reliably
 *
 * v2.2.6 (Feb 2026) - BOARD ACCESS CONTROL FIX:
 * - FIXED: Board Hub access control updated for post-Simple Membership workflow
 * - ADDED: Users WP "board-member" role support for Board Hub access
 * - ADDED: WordPress administrator role automatic access
 * - ENHANCED: Multi-tier access checking (capability → admin → board-member → oha_board → legacy SM)
 * - MAINTAINED: Backward compatibility with existing Simple Membership records during transition
 * - RESOLVED: Inconsistent board member access when Simple Membership no longer used
 * - PRIORITY: WordPress/Users WP roles now primary, Simple Membership secondary fallback
 *
 * v2.2.5 (Feb 2026) - SHORTCODE REFERENCE RESTORED:
 * - RESTORED: Complete Shortcode Reference tab with searchable documentation
 * - ADDED: Auto-detection of all OHA shortcodes from Board Hub and addon plugins
 * - ADDED: Category filtering (Board Hub Core, Vendor, Membership, Admin Tools, Frontend)
 * - ADDED: Live search functionality by shortcode name or description
 * - ADDED: Click-to-copy functionality with visual feedback
 * - ADDED: Status indicators (Active/Inactive badges) and plugin attribution
 * - INCLUDED: Documentation for new [oha_smart_membership_button] shortcode
 * - ENHANCED: Professional card layout with examples and attribute lists
 *
 * v2.2.4 (Feb 2026) - SMART MEMBERSHIP BUTTON:
 * - ADDED: [oha_smart_membership_button] - Auto-detects user status and shows appropriate action
 * - SMART DETECTION: Shows "Join OHA" for new users, "Upgrade" for listing owners, "Renew" for members
 * - PERFECT FOR: Comparison tables and user-specific calls-to-action  
 * - SAME PRICING: Uses existing checkout system (no pricing changes yet)
 * - ENHANCED UX: Users see relevant action instead of generic "Join" message
 * - COMPATIBLE: Works alongside existing [oha_membership_checkout_button]
 * 
 * v2.1.0 (Jan 2026) - COMPREHENSIVE SPONSOR MANAGEMENT SYSTEM:
 * - ADDED: Complete sponsor functionality to Vendor Hub with existing vendor integration
 * - ADDED: $100 Basic Event Sponsorship with configurable Stripe Product integration
 * - ADDED: In Kind Sponsorship with adjustable amounts and invoice generation
 * - ADDED: Sponsor-specific meta fields (_sponsor_type, _sponsor_amount, _sponsor_status)
 * - ADDED: Enhanced Vendor Hub frontend with sponsor controls and filtering
 * - ADDED: Sponsor management buttons (Send Email, Payment Links, Logo Requests)
 * - ADDED: Real-time AJAX updates for sponsor fields and status tracking
 * - ADDED: Comprehensive status tracking (Sent → Paid → Facebook Ad Up → Banner Done → Assets Received)
 * - ADDED: Activity history logging for all sponsor actions
 * - ADDED: Quick statistics dashboard showing vendors vs sponsors breakdown
 * - ADDED: Filter/sort functionality (All, Vendors Only, Sponsors Only)
 * - ADDED: Stripe webhook integration for automatic sponsor payment detection
 * - ADDED: Payment link generation for both Stripe products and custom invoices
 * - ADDED: Logo request automation via email integration
 * - ADDED: Enhanced settings page for sponsor configuration
 * - ADDED: Sponsor-specific export capabilities
 * - ENHANCED: Board Hub overview cards with sponsor statistics
 * - ENHANCED: Convention tab with complete sponsor workflow management
 * - ENHANCED: Existing vendor applications can be converted to sponsors
 * - ENHANCED: Mobile-responsive design for sponsor management interface
 * v2.0.7 (Jan 2026) - COMPLETE USERSWP INTEGRATION:
 * - ENHANCED: Complete UsersWP field integration for Stripe invoice members
 * - ADDED: uwp_account_type field setting (UsersWP core field)
 * - ADDED: _uwp_register_form_id setting (marks as paid member form)
 * - IMPROVED: Member counting includes uwp_account_type detection
 * - ENSURED: Full compatibility with UsersWP member management
 * - CONFIRMED: Auto-created accounts appear properly in UsersWP interface
 * v2.0.6 (Jan 2026) - UNMATCHED PAYMENT HANDLING & AUTO-ACCOUNT CREATION:
 * - ADDED: Auto-account creation for invoice recipients without WordPress accounts
 * - ADDED: Email-based matching for mismatched Stripe customer IDs
 * - ADDED: Unmatched payment storage and tracking system
 * - ADDED: Auto-matching hooks for user registration and login
 * - ADDED: Stripe Invoice Members section in Members tab showing all invoice-based members
 * - ADDED: Unmatched Payments dashboard for admin review and resolution
 * - ENHANCED: Multi-method member detection (UsersWP + Stripe + auto-created)
 * - ENHANCED: Member counting includes auto-created accounts from invoices
 * - IMPROVED: Invoice processing with fallback matching by customer email
 * - IMPROVED: New user notifications with password reset for auto-created accounts
 * v2.0.5 (Jan 2026) - OVERVIEW CARD FIXES & STRIPE ENHANCEMENTS:
 * - FIXED: Pending Payment card now correctly shows Agreement Sent + Agreement Signed vendors (not paid)
 * - FIXED: Volunteer card now shows all signups including pending (excludes only rejected/deleted)
 * - FIXED: OHA Member card enhanced with Stripe invoice payment support for membership products
 * - ADDED: Stripe membership product invoice processing - auto-marks users as OHA members
 * - ADDED: Membership Product IDs configuration in Stripe Settings for invoice automation
 * - IMPROVED: Development Activity card redesigned with better styling and limited activity items (8 instead of 20+)
 * - ENHANCED: Member counting with multiple detection methods (UsersWP + Stripe invoice payments)
 * - ENHANCED: Better activity display with "View all" links and cleaner formatting
 * v2.0.4 (Jan 2026) - CRITICAL SETTINGS FIXES:
 * - REMOVED: DEV Log Tool section from Board Hub Settings (was causing form save conflicts)
 * - REMOVED: Simple Membership Level IDs mapping section (no longer needed since SM removed)
 * - ENHANCED: Custom Tabs section completely rebuilt
 * - ADDED: Support for multiple custom tab shortcodes (not just one)
 * - ADDED: Built-in tab reordering capability (drag & drop + order fields)
 * - ADDED: Combined tab management for both built-in and custom tabs
 * - FIXED: Board Hub Settings form now saves properly without validation errors
 * - IMPROVED: Better tab management UI with clear instructions
 * v1.6.1 (Jan 2026) - CRITICAL FIXES:
 * v1.6.2 (Jan 2026):
 * v1.6.3 (Jan 2026):
 * v1.7.0 (Jan 2026) - PHASE 2A: DASHBOARD & STATISTICS:
 * v2.0.0 (Jan 2026) - PHASE 2B: FINANCE & ACTIVITY LOG:
 * - ADDED: Finance tab with revenue dashboard
 * - ADDED: Vendor revenue tracking and estimates
 * - ADDED: Membership revenue calculations
 * - ADDED: Financial summary with booth pricing
 * - ADDED: Activity Log tab with filterable timeline
 * - ADDED: Vendor status change tracking
 * - ADDED: Member signup activity tracking
 * - ADDED: Time range filters (7/30/90/365 days)
 * - ADDED: Activity type filtering
 * - ADDED: Clickable stat cards (configurable in settings)
 * - ADDED: Settings for each stat card link destination
 * - IMPROVED: Stat cards now link to relevant hub sections
 * - IMPROVED: Hover effects on clickable cards
 * - Professional finance dashboard with Stripe integration
 * - Comprehensive activity logging system
 * 
 * - ADDED: New Dashboard tab with quick statistics cards
 * - ADDED: Vendor statistics (total, paid, pending, booths, recent applications)
 * - ADDED: Member statistics (OHA members, listing owners, new signups)
 * - ADDED: GeoDirectory haunt listings count
 * - ADDED: Volunteer signup count
 * - ADDED: Recent activity feed showing payments and new members
 * - ADDED: Quick action buttons for common tasks
 * - IMPROVED: Dashboard is now default tab
 * - IMPROVED: Convention 2026 tab shows vendor statistics before admin table
 * - ENHANCED: Member table already includes address display and card tracking
 * - Professional, color-coded stat cards with icons
 * - Mobile-responsive grid layout
 * 
 * - UPDATED: Membership pricing display (removed "Free Trial" language for 2026)
 * - IMPROVED: Clearer checkout button explaining $20 card fee + $10/year structure
 * - IMPROVED: Settings labels clarify "Annual Renewal Start Date" instead of "trial"
 * - Member pays $20 immediately for card, then $10/year starting next Jan 1st
 * 
 * - ADDED: Multi-history board hub admin interface (boardhub-admin.php)
 * - IMPROVED: Vendor history tracking with card-based UI
 * - COMPATIBLE: Works with Vendor Hub v3.0.2
 * 
 * - FIXED: Settings sync recursion crash (now uses post-save mirroring)
 * - FIXED: Webhook secret not persisting (properly saves and clears)
 * - FIXED: Endpoint path snap-back (normalized without leading slash)
 * - FIXED: Multi-signature verification (handles Stripe secret rotation)
 * - IMPROVED: Legacy sync uses updated_option action (prevents recursion)
 * - RESOLVES: 400 invalid_signature errors from settings persistence issues
 * 
 * v1.6.0 (Jan 2026):
 * - ADDED: Filter hook for companion plugins (oha_process_webhook_event)
 * - IMPROVED: Centralized webhook now supports external plugin integration
 * - CLARIFIED: Documentation for companion plugin architecture
 * - COMPATIBLE: Works with OHA Vendor Hub v3.0.1+
 *
 * v1.5.2:
 * - Fixed admin menu display issue
 *
 * v1.5.0:
 * - Centralized webhook handler with security and idempotency
 * - Unified settings system across all components
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Plugin constants
define('OHA_BHS_VERSION', '2.3.1');
define('OHA_BHS_PLUGIN_FILE', __FILE__);
define('OHA_BHS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OHA_BHS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OHA_BHS_OPT', 'oha_bh_suite_settings');
define('OHA_BHS_MENU', 'oha-board-hub');

// Autoloader for classes
spl_autoload_register(function ($class) {
    // Only autoload our classes
    if (strpos($class, 'OHA_') !== 0) {
        return;
    }

    $class_file = str_replace('_', '-', strtolower($class));
    $class_file = 'class-' . $class_file . '.php';

    $directories = [
        OHA_BHS_PLUGIN_DIR . 'includes/',
        OHA_BHS_PLUGIN_DIR . 'includes/admin/',
        OHA_BHS_PLUGIN_DIR . 'includes/api/',
        OHA_BHS_PLUGIN_DIR . 'includes/integrations/',
        OHA_BHS_PLUGIN_DIR . 'includes/frontend/',
        OHA_BHS_PLUGIN_DIR . 'includes/utilities/',
    ];

    foreach ($directories as $directory) {
        if (file_exists($directory . $class_file)) {
            require_once $directory . $class_file;
            return;
        }
    }
});

// Load core utilities first
require_once OHA_BHS_PLUGIN_DIR . 'includes/utilities/class-oha-logger.php';
require_once OHA_BHS_PLUGIN_DIR . 'includes/utilities/class-oha-cache.php';
require_once OHA_BHS_PLUGIN_DIR . 'includes/utilities/class-oha-board-notifications.php';
require_once OHA_BHS_PLUGIN_DIR . 'includes/utilities/class-oha-devlog.php';
require_once OHA_BHS_PLUGIN_DIR . 'includes/utilities/class-oha-stripe-webhook-log.php';
require_once OHA_BHS_PLUGIN_DIR . 'includes/class-oha-settings.php';
require_once OHA_BHS_PLUGIN_DIR . 'includes/admin/class-oha-members-table.php';

// Legacy library files (will be refactored incrementally)
require_once OHA_BHS_PLUGIN_DIR . 'includes/integrations/oha-stripe-minimal.lib.php';
require_once OHA_BHS_PLUGIN_DIR . 'includes/integrations/oha-featured-sync.lib.php';
require_once OHA_BHS_PLUGIN_DIR . 'includes/integrations/oha-boardhub-stripe-admin.lib.php';
require_once OHA_BHS_PLUGIN_DIR . 'includes/integrations/oha-vendor-2026-simple.lib.php';
require_once OHA_BHS_PLUGIN_DIR . 'includes/integrations/oha-board-hub.lib.php';
require_once OHA_BHS_PLUGIN_DIR . 'includes/integrations/oha-haunt-discounts.lib.php';
require_once OHA_BHS_PLUGIN_DIR . 'includes/integrations/oha-sponsor-hub.lib.php';

/**
 * Main plugin class
 */
class OHA_Board_Hub_Suite {

    /**
     * Single instance of the class
     *
     * @var OHA_Board_Hub_Suite
     */
    private static $instance = null;

    /**
     * Settings manager instance
     *
     * @var OHA_Settings
     */
    private $settings;

    /**
     * Get singleton instance
     *
     * @return OHA_Board_Hub_Suite
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize the plugin
     */
    private function init() {
        // Initialize logger
        OHA_Logger::init();

        // Optional utilities (board visibility + internal logs)
        if (class_exists('OHA_DevLog')) {
            OHA_DevLog::init();
        }
        if (class_exists('OHA_Stripe_Webhook_Log')) {
            OHA_Stripe_Webhook_Log::init();
        }

        // Initialize settings
        $this->settings = OHA_Settings::instance();

        // Load text domain for translations
        add_action('plugins_loaded', [$this, 'load_textdomain']);

        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', [$this, 'register_admin_menu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
            add_action('admin_notices', [$this, 'admin_notices']);

            // Membership table enhancements (formerly MU plugin)
            add_filter('oha_board_hub_membership_columns', [$this, 'mu_membership_add_columns']);
            add_filter('oha_board_hub_membership_query_args', [$this, 'mu_membership_query_args']);
            add_action('wp_ajax_oha_board_hub_mark_card_sent', [$this, 'mu_membership_mark_card_sent']);
            add_action('admin_footer', [$this, 'mu_membership_admin_footer_js']);

        }

        // Frontend hooks
        if (!is_admin()) {
            add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        }

        // REST API
        add_action('rest_api_init', [$this, 'register_rest_routes'], 999);

        
// Membership upgrades/downgrades (UsersWP + SWPM alignment)
add_action('oha_bhs_membership_set_paid', [$this, 'handle_membership_set_paid'], 10, 3);
add_action('oha_bhs_membership_set_free', [$this, 'handle_membership_set_free'], 10, 3);

// Webhook cron
        add_action('oha_bhs_downgrade_event', [$this, 'handle_downgrade_event'], 10, 1);

        // Legacy settings sync (for backward compatibility)
        $this->setup_legacy_sync();
    }

    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'oha-board-hub',
            false,
            dirname(plugin_basename(OHA_BHS_PLUGIN_FILE)) . '/languages'
        );
    }

    /**
     * Register admin menu
     */
    public function register_admin_menu() {
        // Add top-level menu
        add_menu_page(
            __('OHA Board Hub', 'oha-board-hub'),
            __('OHA Board Hub', 'oha-board-hub'),
            'manage_options',
            OHA_BHS_MENU,
            [$this, 'render_admin_page'],
            'dashicons-admin-generic',
            64
        );

        // Add the main settings as the first submenu (this replaces the auto-generated one)
        // and gives it a proper label
        add_submenu_page(
            OHA_BHS_MENU,
            __('OHA Board Hub Settings', 'oha-board-hub'),
            __('Settings', 'oha-board-hub'),
            'manage_options',
            OHA_BHS_MENU,
            [$this, 'render_admin_page']
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'oha-board-hub'));
        }

        require_once OHA_BHS_PLUGIN_DIR . 'includes/admin/class-oha-admin-page.php';
        $admin_page = new OHA_Admin_Page();
        $admin_page->render();
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        if ('toplevel_page_' . OHA_BHS_MENU !== $hook) {
            return;
        }

        wp_enqueue_style(
            'oha-board-hub-admin',
            OHA_BHS_PLUGIN_URL . 'assets/css/admin.css',
            [],
            OHA_BHS_VERSION
        );

        wp_enqueue_script(
            'oha-board-hub-admin',
            OHA_BHS_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-api'],
            OHA_BHS_VERSION,
            true
        );

        wp_localize_script('oha-board-hub-admin', 'ohaAdminData', [
            'nonce' => wp_create_nonce('wp_rest'),
            'restUrl' => rest_url('oha/v1/'),
            'strings' => [
                'loading' => __('Loading...', 'oha-board-hub'),
                'error' => __('An error occurred', 'oha-board-hub'),
                'confirm' => __('Are you sure?', 'oha-board-hub'),
                'searching' => __('Searching...', 'oha-board-hub'),
            ]
        ]);
    }

    /**
     * Enqueue frontend assets
     */

/**
 * Admin notices for duplicate/legacy plugins that conflict with Board Hub Suite
 */
public function admin_notices() {
    // Only show to admins who can manage settings
    if (!current_user_can('manage_options')) {
        return;
    }

    // Detect legacy "OHA Membership System" plugin file that adds Settings → OHA Membership and registers a competing webhook route.
    $legacy_file = 'oha-membership-system-v1.1-CORRECTED.php';
    $legacy_found = false;
    $legacy_active = false;

    if (defined('WP_PLUGIN_DIR') && is_dir(WP_PLUGIN_DIR)) {
        // Quick scan: direct file in plugins root or any subdir
        $direct = trailingslashit(WP_PLUGIN_DIR) . $legacy_file;
        if (file_exists($direct)) {
            $legacy_found = true;
            $legacy_active = $this->is_plugin_file_active($legacy_file);
        } else {
            // Search one level deep to avoid expensive recursion
            $candidates = glob(trailingslashit(WP_PLUGIN_DIR) . '*/' . $legacy_file);
            if (!empty($candidates)) {
                $legacy_found = true;
                foreach ($candidates as $path) {
                    $rel = str_replace(trailingslashit(WP_PLUGIN_DIR), '', $path);
                    if ($this->is_plugin_file_active($rel)) {
                        $legacy_active = true;
                        break;
                    }
                }
            }
        }
    }

    if ($legacy_found && $legacy_active) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $is_boardhub = $screen && !empty($screen->id) && (strpos($screen->id, 'oha-board-hub') !== false || strpos($screen->id, OHA_BHS_MENU) !== false);

        // Show globally, but emphasize on Board Hub pages
        $title = 'Board Hub: legacy membership plugin detected';
        $msg = 'The legacy plugin <strong>OHA Membership System</strong> is active. It creates a duplicate <em>Settings → OHA Membership</em> page and can register a competing Stripe webhook route. '
             . 'Board Hub Suite already includes the free → paid membership upgrade logic via its centralized webhook endpoint. '
             . 'To prevent “saved but didn’t apply” Stripe settings and webhook conflicts, deactivate the legacy plugin and manage Stripe settings only in <strong>OHA Board Hub → Stripe Settings</strong>.';

        echo '<div class="notice notice-warning ' . ($is_boardhub ? 'notice-alt' : '') . '"><p><strong>' . esc_html($title) . '</strong><br>' . wp_kses_post($msg) . '</p></div>';
    }
}

/**
 * Safely check if a plugin file is active.
 *
 * @param string $plugin_file Plugin file relative to WP_PLUGIN_DIR, or a basename if unknown.
 * @return bool
 */
private function is_plugin_file_active($plugin_file) {
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    // If they provided just a basename, try to match against active plugins list.
    if (strpos($plugin_file, '/') === false) {
        $active = (array) get_option('active_plugins', []);
        foreach ($active as $af) {
            if (basename($af) === $plugin_file) {
                return true;
            }
        }
        return false;
    }

    return is_plugin_active($plugin_file);
}

    public function enqueue_frontend_assets() {
        // Only load on pages with our shortcodes
        if (!$this->should_load_frontend_assets()) {
            return;
        }

        wp_enqueue_style(
            'oha-board-hub-frontend',
            OHA_BHS_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            OHA_BHS_VERSION
        );

        wp_enqueue_script(
            'oha-board-hub-frontend',
            OHA_BHS_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            OHA_BHS_VERSION,
            true
        );

        wp_localize_script('oha-board-hub-frontend', 'ohaData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oha_frontend_nonce'),
            'strings' => [
                'loading' => __('Loading...', 'oha-board-hub'),
                'error' => __('An error occurred. Please try again.', 'oha-board-hub'),
            ]
        ]);
    }

    /**
     * Check if frontend assets should be loaded
     *
     * @return bool
     */
    private function should_load_frontend_assets() {
        global $post;

        if (!is_a($post, 'WP_Post')) {
            return false;
        }

        $shortcodes = [
            'oha_membership_checkout',
            'oha_stripe_billing_portal',
            'oha_stripe_invoices',
            'oha_board_stripe_admin',
            'oha_haunt_discount',
            'oha_vendor_board_hub'
        ];

        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        require_once OHA_BHS_PLUGIN_DIR . 'includes/api/class-oha-webhook-handler.php';

        $webhook_handler = new OHA_Webhook_Handler();
        $webhook_handler->register_routes();
    }

    /**
     * Handle scheduled downgrade event
     *
     * @param array $payload Event payload containing sub_id and customer_id
     */
    public function handle_downgrade_event($payload) {
        $sub_id = isset($payload['sub_id']) ? sanitize_text_field($payload['sub_id']) : '';

        if (empty($sub_id)) {
            OHA_Logger::warning('Downgrade event triggered without subscription ID', $payload);
            return;
        }

        OHA_Logger::info('Processing scheduled downgrade', ['sub_id' => $sub_id]);

        $this->apply_free_level_from_sub_id($sub_id);

        // Also align UsersWP/account type
        $this->apply_userswp_free_from_sub_id($sub_id);

    }

    /**
     * Apply free membership level based on subscription ID
     *
     * @param string $sub_id Stripe subscription ID
     */
    private function apply_free_level_from_sub_id($sub_id) {
        $sub_id = sanitize_text_field($sub_id);

        $users = get_users([
            'meta_key' => 'stripe_subscription_id',
            'meta_value' => $sub_id,
            'number' => 1,
            'fields' => 'ID'
        ]);

        if (empty($users)) {
            OHA_Logger::warning('No user found for subscription', ['sub_id' => $sub_id]);
            return;
        }

        $user_id = (int) $users[0];
        $free_level = (int) $this->settings->get('swpm.free_level_id', 2);

        $result = $this->set_swpm_level($user_id, $free_level);

        if ($result) {
            OHA_Logger::info('User downgraded to free level', [
                'user_id' => $user_id,
                'level' => $free_level,
                'sub_id' => $sub_id
            ]);
        } else {
            OHA_Logger::error('Failed to downgrade user', [
                'user_id' => $user_id,
                'level' => $free_level,
                'sub_id' => $sub_id
            ]);
        }
    }

/**
 * Align UsersWP membership fields to FREE state from a Stripe subscription ID.
 */
private function apply_userswp_free_from_sub_id($sub_id) {
    $sub_id = sanitize_text_field($sub_id);

    $users = get_users([
        'meta_key' => 'stripe_subscription_id',
        'meta_value' => $sub_id,
        'number' => 1,
        'fields' => 'ID'
    ]);

    if (empty($users)) {
        return;
    }

    $this->userswp_set_free_member((int) $users[0]);
}

/**
 * Webhook hook: set PAID state (UsersWP meta + role).
 */
public function handle_membership_set_paid($user_id, $context = null, $event = null) {
    $this->userswp_set_paid_member((int) $user_id);
}

/**
 * Webhook hook: set FREE state (UsersWP meta + role).
 */
public function handle_membership_set_free($user_id, $context = null, $event = null) {
    $this->userswp_set_free_member((int) $user_id);
}

/**
 * Promote a user to PAID OHA member in UsersWP.
 * Uses fixed values to avoid introducing duplicate settings.
 */
private function userswp_set_paid_member($user_id) {
    if ($user_id <= 0) return;

    // UsersWP markers for paid member form/account type
    update_user_meta($user_id, '_uwp_register_form_id', '1');
    update_user_meta($user_id, 'uwp_account_type', 'oha_member');

    // Record when membership was paid — only set once, never overwrite existing date.
    // This powers the "new member" date filter in Board Hub member lists.
    if (!get_user_meta($user_id, 'oha_paid_member_since', true)) {
        update_user_meta($user_id, 'oha_paid_member_since', current_time('mysql'));
    }

    // Role alignment
    $user = new WP_User($user_id);
    if ($user && $user->exists()) {
        // Move to subscriber (paid)
        $user->set_role('subscriber');
    }
}

/**
 * Demote a user to FREE Listing Owner in UsersWP.
 */
private function userswp_set_free_member($user_id) {
    if ($user_id <= 0) return;

    // UsersWP markers for free listing owner form/account type
    update_user_meta($user_id, '_uwp_register_form_id', '4');
    update_user_meta($user_id, 'uwp_account_type', 'listing_owner');

    // Role alignment: prefer listing_owner if it exists, otherwise subscriber
    $target_role = 'subscriber';
    if (function_exists('get_role') && get_role('listing_owner')) {
        $target_role = 'listing_owner';
    }

    $user = new WP_User($user_id);
    if ($user && $user->exists()) {
        $user->set_role($target_role);
    }
}

/**
 * ===== Membership Table Enhancements (MU plugin migration) =====
 */
public function mu_membership_add_columns($columns) {
    $columns['card_sent'] = 'Card Sent';
    $columns['card_sent_date'] = 'Date Sent';
    return $columns;
}

public function mu_membership_query_args($args) {
    // Filter by card sent status
    if (!empty($_GET['card_sent_filter'])) {
        if ($_GET['card_sent_filter'] === 'sent') {
            $args['meta_query'][] = [
                'key' => 'card_sent',
                'value' => '1',
                'compare' => '='
            ];
        } elseif ($_GET['card_sent_filter'] === 'not_sent') {
            $args['meta_query'][] = [
                'key' => 'card_sent',
                'compare' => 'NOT EXISTS'
            ];
        }
    }
    // Search by membership ID
    if (!empty($_GET['membership_search'])) {
        $args['search'] = sanitize_text_field($_GET['membership_search']);
    }
    return $args;
}

public function mu_membership_mark_card_sent() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    check_ajax_referer('oha_mark_card_sent');
    $membership_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
    if (!$membership_id) {
        wp_send_json_error('Missing membership ID');
    }
    $today = gmdate('Y-m-d');
    // Usermeta (admin panel primary store)
    update_user_meta($membership_id, 'oha_card_sent', '1');
    update_user_meta($membership_id, 'oha_card_sent_date', $today);
    // Also sync to oha_card_status table so frontend Board Hub matches
    global $wpdb;
    $card_tbl = $wpdb->prefix . 'oha_card_status';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $card_tbl)) === $card_tbl) {
        $wpdb->replace($card_tbl, [
            'member_id' => $membership_id,
            'sent'      => 1,
            'date'      => $today,
        ], ['%d', '%d', '%s']);
    }
    wp_send_json_success(['membership_id' => $membership_id]);
}

public function mu_membership_admin_footer_js() {
    if (!is_admin()) return;
    $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    if ($page !== 'oha-board-hub-membership') return;
    $nonce = wp_create_nonce('oha_mark_card_sent');
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
      document.querySelectorAll('.oha-mark-card-sent').forEach(function(btn){
        btn.addEventListener('click', function(e){
          e.preventDefault();
          var id = this.getAttribute('data-membership-id');
          var fd = new FormData();
          fd.append('action','oha_board_hub_mark_card_sent');
          fd.append('user_id', id);
          fd.append('_ajax_nonce', '<?php echo esc_js($nonce); ?>');
          fetch(ajaxurl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(r=>r.json())
            .then(function(resp){
              if(resp && resp.success){
                location.reload();
              } else {
                alert((resp && resp.data) ? resp.data : 'Error');
              }
            });
        });
      });
    });
    </script>
    <?php
}



    /**
     * Set SWPM membership level for a user
     *
     * @param int $wp_user_id WordPress user ID
     * @param int $level_id SWPM membership level ID
     * @return bool True on success, false on failure
     */
    private function set_swpm_level($wp_user_id, $level_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'swpm_members_tbl';

        // Verify table exists
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if ($exists !== $table) {
            OHA_Logger::error('SWPM members table does not exist', ['table' => $table]);
            return false;
        }

        $user = get_userdata($wp_user_id);
        if (!$user) {
            OHA_Logger::error('User not found', ['user_id' => $wp_user_id]);
            return false;
        }

        // Check if table has wp_user_id column
        $columns = $wpdb->get_results("DESCRIBE {$table}", ARRAY_A);
        $column_names = wp_list_pluck($columns, 'Field');

        $data = [
            'membership_level' => (int) $level_id,
            'account_state' => 'active'
        ];

        if (in_array('wp_user_id', $column_names, true)) {
            $where = ['wp_user_id' => $wp_user_id];
        } else {
            $where = ['email' => $user->user_email];
        }

        $result = $wpdb->update($table, $data, $where);

        return false !== $result;
    }

    /**
     * Setup legacy option syncing for backward compatibility
     *
     * IMPORTANT:
     * - DO NOT write/update other options inside pre_update_option_* filters (recursion crash).
     * - We only intercept READS with pre_option_*.
     * - We mirror SAVES using updated_option (runs AFTER option saved).
     */
    private function setup_legacy_sync() {

        // READ from legacy options → return suite settings
        // NOTE: pre_option_oha_sm_settings intentionally NOT intercepted here.
        // Intercepting it caused update_option() to compare the new form data against
        // the suite-settings value instead of the actual DB value, making WordPress
        // think nothing changed and silently skip every subsequent save.
        // Direct DB reads for oha_sm_settings are correct; updated_option below mirrors
        // any successful write back into the suite settings.

        add_filter('pre_option_oha_sa_settings', function($pre) {
            return $this->settings->get('stripe');
        });

        add_filter('pre_option_oha_vbh_front_cap', function($pre) {
            return $this->settings->get('vendor.front_cap');
        });

        add_filter('pre_option_oha_vbh_convention_2026_page_id', function($pre) {
            return $this->settings->get('vendor.convention_2026_page_id');
        });

        /**
         * WRITE mirror:
         * When legacy options are updated, mirror into suite settings AFTER the save.
         * This avoids recursion and site crashes.
         */
        add_action('updated_option', function($option, $old_value, $value) {
            if ($option !== 'oha_sm_settings' && $option !== 'oha_sa_settings') {
                return;
            }

            $value = (array) $value;

            // Normalize endpoint path (prevents leading "/" from coming back)
            if (isset($value['endpoint_path'])) {
                $value['endpoint_path'] = trim((string)$value['endpoint_path'], "/ \t\n\r\0\x0B");
            }

            // Allow overwriting/clearing secrets (store blank if cleared)
            if (!array_key_exists('webhook_secret', $value)) {
                $value['webhook_secret'] = '';
            }
            if (!array_key_exists('secret_key', $value)) {
                $value['secret_key'] = '';
            }

            // Persist into Suite settings
            // NOTE: This must NOT write back into oha_sm_settings / oha_sa_settings.
            $this->settings->update('stripe', $value);
        }, 10, 3);

        /**
         * Legacy vendor settings writes: keep simple and non-recursive.
         * These write directly to suite settings keys (not legacy options).
         */
        add_filter('pre_update_option_oha_vbh_front_cap', function($value) {
            $this->settings->update('vendor.front_cap', $value);
            return $value;
        }, 10, 1);

        add_filter('pre_update_option_oha_vbh_convention_2026_page_id', function($value) {
            $this->settings->update('vendor.convention_2026_page_id', (int) $value);
            return (int) $value;
        }, 10, 1);
    }
}

/**
 * Initialize the plugin
 *
 * @return OHA_Board_Hub_Suite
 */
function oha_bhs_init() {
    return OHA_Board_Hub_Suite::instance();
}

// Start the plugin
add_action('plugins_loaded', 'oha_bhs_init', 10);

/**
 * Helper function to get settings
 *
 * @param string|null $key Settings key using dot notation (e.g., 'stripe.secret_key')
 * @param mixed $default Default value if key doesn't exist
 * @return mixed
 */
function oha_bhs_get_setting($key = null, $default = null) {
    return OHA_Settings::instance()->get($key, $default);
}

/**
 * Helper function to update settings
 *
 * @param string $key Settings key using dot notation
 * @param mixed $value Value to set
 * @return bool
 */
function oha_bhs_update_setting($key, $value) {
    return OHA_Settings::instance()->update($key, $value);
}

/**
 * Legacy compatibility functions
 */
function oha_bhs_defaults() {
    return OHA_Settings::instance()->get_defaults();
}

function oha_bhs_get() {
    return OHA_Settings::instance()->get();
}

function oha_bhs_update($new) {
    $settings = OHA_Settings::instance();
    foreach ($new as $section => $values) {
        if (is_array($values)) {
            foreach ($values as $key => $value) {
                $settings->update($section . '.' . $key, $value);
            }
        } else {
            $settings->update($section, $values);
        }
    }
    return $settings->get();
}
