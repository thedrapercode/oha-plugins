# Changelog

All notable changes to OHA Board Hub Suite will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.5.0] - 2025-11-26

### 🔒 Security
- **CRITICAL:** Fixed SQL injection vulnerability in member search functionality
- **CRITICAL:** Added nonce verification to all forms to prevent CSRF attacks
- **HIGH:** Implemented timing-safe comparison for webhook signature verification
- **HIGH:** Added idempotency checking for webhook events to prevent duplicate processing
- **MEDIUM:** Enhanced capability checks throughout the plugin
- **MEDIUM:** Sanitized all user inputs with appropriate WordPress functions
- **MEDIUM:** Improved output escaping to prevent XSS attacks

### ✨ Added
- New `OHA_Logger` class for centralized error logging and admin notices
- New `OHA_Cache` class for performance optimization via WordPress object cache
- New `OHA_Settings` class for centralized settings management with dot notation
- New `OHA_Webhook_Handler` class with improved security and error handling
- New `OHA_Admin_Page` class for better admin interface organization
- Comprehensive PHPDoc blocks throughout codebase
- Translation support (i18n) with 'oha-board-hub' text domain
- Proper CSS and JavaScript asset management with conditional loading
- Pagination for member lists (25 members per page)
- Admin and frontend stylesheets for consistent UI
- JavaScript for improved user experience
- Detailed logging with multiple severity levels (error, warning, info, debug)
- Webhook event idempotency tracking (30-day retention)
- Settings export/import functionality
- Cache helper methods for query optimization

### 🔄 Changed
- **BREAKING:** Refactored from procedural to object-oriented architecture
- **BREAKING:** Reorganized file structure (admin, api, integrations, utilities)
- **BREAKING:** Implemented autoloading for all classes
- Improved member search with secure prepared statements
- Enhanced error messages with better user feedback
- Updated admin interface with cleaner design and better organization
- Optimized database queries with proper indexing recommendations
- Improved webhook processing with better error recovery
- Enhanced downgrade scheduling with comprehensive logging
- Better SWPM table compatibility detection
- Modernized code style following WordPress coding standards

### 🐛 Fixed
- Fixed SQL injection vulnerability in member search
- Fixed missing nonce verification on search forms
- Fixed capability checks being inconsistent
- Fixed webhook signature verification edge cases
- Fixed pagination not working for member lists
- Fixed SWPM table column detection for sites with custom schemas
- Fixed downgrade event not firing in some cases
- Fixed memory leaks in long-running processes
- Fixed race conditions in webhook processing
- Fixed asset loading on pages without shortcodes

### 🚀 Performance
- Added lazy loading for admin and frontend code
- Implemented query result caching where appropriate
- Optimized asset loading (only load on pages that need them)
- Reduced database queries with batch operations
- Added proper WordPress object cache integration
- Improved webhook processing speed

### 📝 Documentation
- Added comprehensive inline code documentation
- Created detailed upgrade guide (UPGRADE-GUIDE.md)
- Expanded readme.txt with troubleshooting section
- Added code examples for developers
- Documented all hooks and filters
- Created security audit checklist

### 🔧 Developer
- Added hooks: `oha_settings_saved`, `oha_logger_log`, `oha_cache_cleared`
- Maintained backward compatibility with 1.4.3
- Added helper functions: `oha_bhs_get_setting()`, `oha_bhs_update_setting()`
- Implemented automatic settings migration from legacy format
- Added debug mode support via WP_DEBUG constant
- Exposed public APIs for extensibility

### 🗃️ Database
- Added indexes to frequently queried columns (not automatically applied)
- Improved table schema for card status and events
- Added created_at timestamp to card status table
- Optimized queries with proper WHERE clauses and LIMIT

### 🎨 UI/UX
- Redesigned admin interface with modern styling
- Added loading states for better user feedback
- Improved error message display
- Added success notifications
- Enhanced form validation with inline errors
- Better mobile responsiveness

## [1.4.3] - 2024-XX-XX

### Added
- Unified plugin combining multiple OHA modules
- Single settings storage system
- Tabbed admin interface
- Basic webhook handling
- SWPM integration
- Stripe payment processing
- Board Hub frontend
- Vendor Convention 2026 module
- Haunt discounts feature

### Changed
- Consolidated multiple plugins into single suite
- Simplified settings management
- Improved admin navigation

## [1.4.2] and Earlier

Previous versions before suite consolidation.

---

## Upgrade Instructions

### From 1.4.3 to 1.5.0

**IMPORTANT:** Please read UPGRADE-GUIDE.md before upgrading.

1. **Backup your database and files**
2. Deactivate version 1.4.3 (do not delete yet)
3. Upload and activate version 1.5.0
4. Verify settings in OHA Board Hub admin
5. Test all functionality
6. Delete old version once confirmed working

All settings from 1.4.3 are automatically migrated to 1.5.0.

### Compatibility

- **WordPress:** 5.8+ (Tested up to 6.4)
- **PHP:** 7.4+ (Recommended: 8.0+)
- **MySQL:** 5.6+ (Recommended: 5.7+)
- **Required Plugins:**
  - Simple WordPress Membership (SWPM)
  - GeoDirectory (for discount features)

---

## Deprecated

The following will be removed in version 2.0.0:

- Direct function calls to `oha_bhs_get()` (use `oha_bhs_get_setting()` instead)
- Direct function calls to `oha_bhs_update()` (use `oha_bhs_update_setting()` instead)
- Legacy option names (`oha_sm_settings`, etc.) - will be read-only

---

## Security Advisories

### [CVE-YYYY-XXXXX] SQL Injection in Member Search (1.4.3 and earlier)

**Severity:** Critical  
**Affected Versions:** ≤ 1.4.3  
**Fixed In:** 1.5.0  
**Description:** Unsanitized user input in member search could allow SQL injection  
**Recommendation:** Upgrade to 1.5.0 immediately

### [CVE-YYYY-XXXXY] CSRF in Admin Forms (1.4.3 and earlier)

**Severity:** High  
**Affected Versions:** ≤ 1.4.3  
**Fixed In:** 1.5.0  
**Description:** Missing nonce verification could allow CSRF attacks  
**Recommendation:** Upgrade to 1.5.0 immediately

---

## Support

For support, please contact: support@ohiohaunt.com

For bug reports and feature requests, please use the appropriate channels.

---

## Contributors

- OHA Development Team
- Ohio Haunters Association Members

---

## License

This plugin is licensed under GPL-2.0+.
See LICENSE file for details.

---

**Note:** This project follows [Semantic Versioning](https://semver.org/).

- **MAJOR** version for incompatible API changes
- **MINOR** version for new functionality in a backward compatible manner  
- **PATCH** version for backward compatible bug fixes
