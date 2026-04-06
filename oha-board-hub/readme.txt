=== OHA Board Hub Suite ===
Contributors: OHA
Tags: membership, stripe, board-hub, payments
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.5.2
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Unified plugin combining membership management, Stripe payment processing, board hub functionality, and vendor management for the Ohio Haunters Association.

== Description ==

OHA Board Hub Suite is a comprehensive membership management system that integrates:

* **Stripe Payment Processing** - Secure payment handling with webhooks
* **Member Management** - Complete member database with search and filtering
* **Board Hub** - Member card tracking and event logging
* **Vendor Management** - Convention 2026 vendor dashboard
* **Discount System** - Haunt-specific discount announcements
* **SWPM Integration** - Seamless integration with Simple WordPress Membership

Version 1.5.0 includes major security improvements, better architecture, and enhanced error handling.

== Installation ==

1. Upload the `oha-board-hub-suite` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'OHA Board Hub' in the admin menu
4. Configure your settings in each tab

== Upgrading from 1.4.3 ==

**IMPORTANT:** Version 1.5.0 includes security fixes and architectural improvements.

### Backup First
Before upgrading, please:
1. **Backup your database**
2. **Backup the plugin files**
3. **Test on a staging site** if possible

### Upgrade Steps
1. Deactivate the old version (DO NOT DELETE YET)
2. Upload the new version
3. Activate the new version
4. Visit 'OHA Board Hub' admin page to verify settings
5. Test all functionality (member search, Stripe webhooks, etc.)
6. Once verified, you can delete the old version

### What's Changed in 1.5.0

**Security Improvements:**
* Fixed SQL injection vulnerabilities
* Added nonce verification to all forms
* Improved webhook signature verification
* Added idempotency checking for webhooks
* Enhanced capability checks throughout

**Architecture:**
* Refactored to object-oriented design
* Improved file organization
* Added autoloading for classes
* Better separation of admin/frontend code
* Centralized settings management

**New Features:**
* Comprehensive error logging system
* Caching support for better performance
* Proper asset management (CSS/JS)
* Translation-ready (i18n support)
* Better error messages and user feedback

**Bug Fixes:**
* Fixed member search security issues
* Improved pagination in member lists
* Better handling of SWPM table variations
* Fixed webhook processing edge cases

### Compatibility Notes

* All previous settings are automatically migrated
* Existing webhooks continue to work
* No database changes required
* Backward compatible with version 1.4.3

== Configuration ==

### Stripe Setup

1. Go to **OHA Board Hub > Stripe Settings**
2. Enter your Stripe Secret Key (starts with `sk_live_` or `sk_test_`)
3. Enter your Webhook Signing Secret (starts with `whsec_`)
4. Copy the webhook endpoint URL shown
5. In your Stripe Dashboard, add this URL as a webhook endpoint
6. Subscribe to these events:
   * `checkout.session.completed`
   * `customer.created`
   * `customer.updated`
   * `customer.subscription.created`
   * `customer.subscription.updated`
   * `customer.subscription.deleted`
   * `invoice.payment_succeeded`

### SWPM Integration

1. Ensure Simple WordPress Membership plugin is installed and active
2. Go to **OHA Board Hub > Membership**
3. Configure your membership level IDs:
   * Free Level ID (usually 2)
   * Paid Level ID (usually 10)

### Testing Webhooks

To test your webhook configuration:

1. Use Stripe's webhook testing tool in the Dashboard
2. Send a test event
3. Check **OHA Board Hub > Debug** for logs
4. Verify the event was processed correctly

== Shortcodes ==

**[oha_membership_checkout]**
Display membership checkout form with $20 signup + $10/year subscription.

**[oha_stripe_billing_portal]**
Display a button to access the Stripe Customer Portal for managing subscriptions.

**[oha_stripe_invoices]**
List recent invoices for the current user.

**[oha_stripe_address prefer="shipping|billing"]**
Display saved shipping or billing address.

**[oha_board_stripe_admin]**
Admin interface for managing member subscriptions (requires `manage_oha_members` capability).

**[oha_haunt_discounts_admin]**
Admin interface for managing haunt-specific discount announcements.

**[oha_haunt_discount]**
Display discount announcement bar on haunt pages.

**[oha_vendor_board_hub]**
Vendor dashboard for Convention 2026.

== Frequently Asked Questions ==

= Does this plugin require other plugins? =

Yes, it requires:
* Simple WordPress Membership (SWPM) plugin
* GeoDirectory plugin (for discount features)

= How do I get a Stripe API key? =

1. Sign up for a Stripe account at stripe.com
2. Go to Developers > API keys
3. Copy your Secret key (for production use the live key, for testing use the test key)

= What happens when a member cancels? =

When a member cancels their subscription with "cancel at period end":
1. They retain access until the end of their billing period
2. A downgrade event is automatically scheduled
3. At the end of the period, they're downgraded to the Free membership level

= How do I troubleshoot webhook issues? =

1. Check **OHA Board Hub > Debug** for error logs
2. Verify your webhook signing secret is correct
3. Ensure your server time is synchronized (important for signature verification)
4. Check that your site is accessible from Stripe's servers (not behind firewall)
5. Increase timestamp tolerance if needed (default: 300 seconds)

= Where are the error logs? =

Error logs are stored in two places:
1. WordPress admin notices (for admins only)
2. PHP error log (if WP_DEBUG is enabled)

You can also check the Debug tab for recent errors.

== Screenshots ==

1. Main admin dashboard with tabbed interface
2. Member management with search and pagination
3. Stripe settings configuration
4. Webhook endpoint information
5. Haunt discount management

== Changelog ==

= 1.5.2 - 2025-11-26 =
**Bug Fixes:**
* Fixed admin settings page not appearing in menu
* Removed Featured Sync submenu (accessible via Board Hub Settings tab)
* Added explicit "Settings" submenu item so main page is always accessible
* Main settings page now shows when clicking OHA Board Hub menu

**Note:**
* Featured Sync functionality is still available in Board Hub Settings tab
* Award Entries (if present) is from a separate plugin - not part of OHA Board Hub Suite

= 1.5.1 - 2025-11-26 =
**Bug Fixes:**
* Fixed duplicate "Board Hub" menu appearing in admin
* Removed duplicate menu creation from featured-sync library

**New Features:**
* Added Board Notification Email system
* Email alerts when new members sign up with SWPM Level 10
* Email alerts when new members register via UsersWP Form ID 1
* Configurable recipient emails (one per line)
* Test email functionality
* Customizable email subject
* Configurable membership level and form ID monitoring

**Improvements:**
* Cleaner admin menu structure
* Better organization of notification settings
* Comprehensive logging for notification events

= 1.5.0 - 2025-11-26 =
**Security:**
* Fixed SQL injection vulnerabilities in member search
* Added nonce verification to all forms
* Improved webhook signature verification with timing-safe comparison
* Added idempotency checking for webhook events
* Enhanced capability checks throughout plugin

**Architecture:**
* Refactored to object-oriented design with autoloading
* Reorganized file structure (admin, api, integrations, utilities)
* Implemented centralized settings management with dot notation
* Separated admin and frontend code for better performance
* Added proper class-based structure

**Features:**
* Added comprehensive logging system (OHA_Logger)
* Implemented caching system (OHA_Cache)
* Proper asset enqueuing (CSS/JS)
* Translation-ready with text domain support
* Better error messages and user feedback
* Added pagination to member lists (25 per page)

**Bug Fixes:**
* Fixed member search security issues
* Improved SWPM table compatibility
* Better handling of webhook edge cases
* Fixed downgrade scheduling
* Corrected capability checks

**Performance:**
* Lazy loading of admin/frontend code
* Conditional asset loading
* Query optimization with prepared statements
* Better caching strategies

**Developer:**
* Added PHPDoc blocks throughout
* Consistent coding standards
* Better error handling and logging
* Hooks for extensibility
* Backward compatible with 1.4.3

= 1.4.3 =
* Previous stable release
* Combined multiple plugins into suite
* Single settings storage
* Basic webhook handling

== Upgrade Notice ==

= 1.5.0 =
Major security update with architectural improvements. Backup before upgrading. All settings are automatically migrated.

== Support ==

For support, please contact: support@ohiohaunt.com

== Technical Details ==

**Minimum Requirements:**
* WordPress 5.8 or higher
* PHP 7.4 or higher
* MySQL 5.6 or higher
* HTTPS (required for Stripe webhooks)

**Recommended:**
* WordPress 6.0+
* PHP 8.0+
* MySQL 5.7+
* Object caching (Redis/Memcached) for better performance

**Hooks & Filters:**

Actions:
* `oha_settings_saved` - Fired when settings are saved
* `oha_logger_log` - Fired when a log entry is created
* `oha_cache_cleared` - Fired when cache is cleared
* `oha_bhs_downgrade_event` - Scheduled downgrade event

Filters:
* `pre_option_oha_sm_settings` - Legacy settings compatibility
* `pre_option_oha_sa_settings` - Legacy settings compatibility

**Database Tables:**
* `{prefix}_oha_card_status` - Member card status tracking
* `{prefix}_oha_card_events` - Member card event log

**Options:**
* `oha_bh_suite_settings` - Main plugin settings
* `oha_processed_webhook_events` - Webhook idempotency tracking

== Privacy Policy ==

This plugin stores:
* Member information from SWPM
* Stripe customer IDs (for payment processing)
* Shipping and billing addresses
* Webhook event IDs (for idempotency, stored 30 days)
* Member card status and events

No data is sent to third parties except:
* Stripe (for payment processing)

Users can request data deletion by contacting the site administrator.

== Credits ==

Developed by Ohio Haunters Association
Stripe integration powered by Stripe API
