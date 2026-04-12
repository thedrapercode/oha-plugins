<?php
if (!defined('ABSPATH')) exit;

/**
 * Shortcodes Loader
 * 
 * This file loads all shortcode files for the OHA Vendor Hub plugin.
 */

// Initial application form (with duplicate prevention)
require_once OHA_VHCPT_PATH . 'includes/shortcodes/form-frontend.php';

// Final application form with e-signature
require_once OHA_VHCPT_PATH . 'includes/shortcodes/final-application.php';

// No-payment final application form (for pre-paid vendors)
require_once OHA_VHCPT_PATH . 'includes/shortcodes/final-application-no-payment.php';

// Board Hub admin dashboard
require_once OHA_VHCPT_PATH . 'includes/shortcodes/boardhub-admin.php';

// User dashboard
require_once OHA_VHCPT_PATH . 'includes/shortcodes/user-dashboard.php';

// Vendor status table (read-only, restricted by user ID)
require_once OHA_VHCPT_PATH . 'includes/shortcodes/vendor-status-table.php';

// Public vendor card grid for website pages (light theme, searchable, no favorites)
require_once OHA_VHCPT_PATH . 'includes/shortcodes/vendor-cards.php';

// Vendor self-edit form (email magic link, no WP account required)
require_once OHA_VHCPT_PATH . 'includes/shortcodes/vendor-edit.php';

// Shortcode aliases for easier reference
add_shortcode('vendor_dashboard', 'oha_vendor_boardhub'); // Easier: [vendor_dashboard]
add_shortcode('admin_vendor_list', 'oha_vendor_boardhub'); // Alternative: [admin_vendor_list]

