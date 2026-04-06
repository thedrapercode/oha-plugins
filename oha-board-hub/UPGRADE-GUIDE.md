# OHA Board Hub Suite - Upgrade Guide from 1.4.3 to 1.5.0

## Overview

Version 1.5.0 is a major update that includes critical security fixes, architectural improvements, and enhanced functionality. This guide will help you safely upgrade from version 1.4.3.

---

## ⚠️ IMPORTANT: Pre-Upgrade Checklist

Before upgrading, complete ALL of these steps:

### 1. Backup Your Site
- [ ] **Database backup** - Use your hosting control panel, phpMyAdmin, or a backup plugin
- [ ] **Files backup** - Download a copy of your entire WordPress installation
- [ ] **Export settings** - Document your current plugin settings (take screenshots)

### 2. Test Environment (Recommended)
- [ ] If possible, test the upgrade on a staging site first
- [ ] Verify all functionality works on staging before upgrading production

### 3. Check Requirements
- [ ] WordPress 5.8 or higher
- [ ] PHP 7.4 or higher
- [ ] Simple WordPress Membership plugin active
- [ ] HTTPS enabled (required for Stripe webhooks)

### 4. Notify Users
- [ ] Schedule upgrade during low-traffic period
- [ ] Inform members that the site may be briefly unavailable

---

## 🚀 Upgrade Steps

### Step 1: Backup (Yes, Again!)

Even if you did this above, create a fresh backup right before upgrading:

```bash
# Via WP-CLI (if available)
wp db export backup-before-1.5.0.sql
```

Or use your hosting control panel's backup feature.

### Step 2: Download New Version

1. Download the `oha-board-hub-suite-1.5.0.zip` file
2. Keep a copy in a safe location

### Step 3: Deactivate Current Plugin

1. Go to **Plugins > Installed Plugins**
2. Find "OHA Board Hub – Suite (1.4.3)"
3. Click **Deactivate** (DO NOT DELETE YET)

### Step 4: Upload New Version

**Option A: Via WordPress Admin (Recommended)**
1. Go to **Plugins > Add New**
2. Click **Upload Plugin**
3. Choose `oha-board-hub-suite-1.5.0.zip`
4. Click **Install Now**

**Option B: Via FTP/SFTP**
1. Extract the ZIP file on your computer
2. Connect to your server via FTP/SFTP
3. Navigate to `/wp-content/plugins/`
4. Upload the `oha-board-hub-suite` folder
5. Choose "Overwrite" when prompted

### Step 5: Activate New Version

1. Go to **Plugins > Installed Plugins**
2. Find "OHA Board Hub – Suite (1.5.0)"
3. Click **Activate**

### Step 6: Verify Settings

1. Go to **OHA Board Hub** in admin menu
2. Check each tab:
   - **Membership** - Verify member list loads
   - **Stripe Settings** - Verify API keys are present
   - **Webhooks** - Note the new webhook URL (it should be the same)
   - **Board Hub Settings** - Check all settings preserved
   - **Convention 2026** - Verify vendor settings
   - **Haunt Discounts** - Check discount settings

### Step 7: Test Functionality

Complete these tests:

#### Test Member Search
1. Go to **OHA Board Hub > Membership**
2. Try searching for a member by email
3. Verify results display correctly
4. Test pagination (if you have 25+ members)

#### Test Stripe Webhook
1. Go to [Stripe Dashboard](https://dashboard.stripe.com/)
2. Navigate to **Developers > Webhooks**
3. Click on your webhook endpoint
4. Click **Send test webhook**
5. Choose "customer.created"
6. Verify in **OHA Board Hub > Debug** that it was received

#### Test Frontend Shortcodes
1. Visit pages with these shortcodes:
   - `[oha_membership_checkout]`
   - `[oha_stripe_billing_portal]`
   - Any other shortcodes you use
2. Verify they display correctly
3. Check browser console for any JavaScript errors

### Step 8: Monitor Error Logs

For the first 24 hours after upgrade:

1. Check **OHA Board Hub > Debug** periodically
2. Look for any PHP errors in your error log
3. Monitor admin notices
4. Watch for any user-reported issues

### Step 9: Delete Old Version (After Testing)

Once you've confirmed everything works:

1. Go to **Plugins > Installed Plugins**
2. Find any old OHA Board Hub plugins
3. **Delete** them

---

## 🔧 What's New in 1.5.0

### Security Improvements

#### 1. SQL Injection Fixes
**What changed:** Member search now uses properly prepared SQL statements
**Impact:** Your site is now protected against SQL injection attacks
**Action required:** None - automatic

#### 2. Nonce Verification
**What changed:** All forms now verify nonces before processing
**Impact:** Protection against CSRF attacks
**Action required:** None - automatic

#### 3. Webhook Idempotency
**What changed:** Duplicate webhook events are now detected and ignored
**Impact:** Prevents double-processing of Stripe events
**Action required:** None - automatic

#### 4. Better Signature Verification
**What changed:** Webhooks use timing-safe comparison
**Impact:** More secure webhook verification
**Action required:** None - automatic

### Architecture Changes

#### 1. Class-Based Structure
**What changed:** Code refactored to use classes instead of functions
**Impact:** Better code organization and maintainability
**Action required:** If you have custom code that calls plugin functions, check compatibility

#### 2. Autoloading
**What changed:** Classes are now autoloaded
**Impact:** Better performance
**Action required:** None - automatic

#### 3. New File Structure
**Old structure:**
```
oha-board-hub-suite/
  includes/
    oha-stripe-minimal.lib.php
    oha-board-hub.lib.php
    ...
```

**New structure:**
```
oha-board-hub-suite/
  includes/
    admin/
    api/
    integrations/
    frontend/
    utilities/
  assets/
    css/
    js/
```

**Impact:** Better organization
**Action required:** If you reference files directly, update paths

### New Features

#### 1. Logging System
**What's new:** Comprehensive error logging with OHA_Logger class
**How to use:**
```php
OHA_Logger::error('Something went wrong', ['context' => 'data']);
OHA_Logger::info('Process completed');
```

**View logs:** OHA Board Hub > Debug

#### 2. Caching System
**What's new:** Built-in caching for performance
**How to use:** Automatic - queries are cached where appropriate
**Clear cache:** WordPress > Settings > clear cache

#### 3. Better Asset Management
**What's new:** CSS and JS properly enqueued
**Impact:** Better performance, no conflicts
**Action required:** None - automatic

#### 4. Translation Support
**What's new:** Plugin is now translation-ready
**How to use:** Add .po/.mo files to `/languages/` folder
**Text domain:** `oha-board-hub`

---

## 🐛 Troubleshooting

### Issue: "Class not found" errors

**Solution:**
1. Deactivate the plugin
2. Delete the old plugin folder via FTP
3. Upload the new version fresh
4. Activate

### Issue: Settings not preserved

**Solution:**
1. Check `wp_options` table for `oha_bh_suite_settings`
2. If missing, restore from backup
3. Alternatively, re-enter settings manually

### Issue: Webhooks not working

**Checklist:**
- [ ] Webhook signing secret is correct in settings
- [ ] Webhook URL in Stripe matches what's shown in plugin
- [ ] Your site is accessible via HTTPS
- [ ] Server time is synchronized (check with Stripe)
- [ ] PHP error logs show no errors

**Test webhook:**
```bash
# Send test webhook from command line
curl -X POST https://yoursite.com/oha/v1/stripe-webhook \
  -H "Stripe-Signature: test" \
  -d '{"type":"test.event"}'
```

### Issue: Member search returns no results

**Solution:**
1. Check that SWPM plugin is active
2. Verify SWPM members table exists in database
3. Check **OHA Board Hub > Debug** for errors
4. Try searching for a specific email you know exists

### Issue: Admin page doesn't load

**Solution:**
1. Check browser console for JavaScript errors
2. Disable other plugins temporarily to check for conflicts
3. Switch to a default WordPress theme to rule out theme issues
4. Check PHP error log for clues

### Issue: "Permission denied" errors

**Solution:**
1. Verify file permissions:
   - Folders: 755
   - Files: 644
2. Check that web server can write to uploads directory
3. Verify user has `manage_options` capability

---

## 🔄 Rolling Back (If Needed)

If you need to roll back to version 1.4.3:

### Step 1: Restore Database Backup
```bash
wp db import backup-before-1.5.0.sql
```

### Step 2: Deactivate & Delete 1.5.0
1. Deactivate OHA Board Hub Suite
2. Delete the plugin

### Step 3: Restore Old Plugin Files
1. Extract your backup of version 1.4.3
2. Upload to `/wp-content/plugins/`
3. Activate

### Step 4: Verify
- Check settings
- Test functionality
- Review error logs

---

## 📊 Performance Optimization

After upgrading, consider these optimizations:

### 1. Enable Object Caching
If you have Redis or Memcached available:
```php
// wp-config.php
define('WP_CACHE', true);
```

### 2. Optimize Database Tables
```sql
OPTIMIZE TABLE wp_oha_card_status;
OPTIMIZE TABLE wp_oha_card_events;
```

### 3. Clear Old Data
The plugin stores webhook events for 30 days. Old ones are automatically removed.

---

## 📞 Getting Help

If you encounter issues:

1. **Check Debug Tab** - OHA Board Hub > Debug
2. **Review Error Logs** - Check PHP error_log
3. **Check Documentation** - readme.txt file
4. **Contact Support** - support@ohiohaunt.com

When contacting support, include:
- WordPress version
- PHP version
- Error messages (exact text)
- Steps to reproduce issue
- Screenshots if applicable

---

## ✅ Post-Upgrade Checklist

After upgrade is complete and tested:

- [ ] All settings verified
- [ ] Member search working
- [ ] Stripe webhooks tested
- [ ] Frontend shortcodes working
- [ ] No errors in debug log
- [ ] Old plugin version deleted
- [ ] Backup confirmed working
- [ ] Users notified of any changes
- [ ] Documentation updated
- [ ] Monitoring in place

---

## 🎯 Best Practices Going Forward

### Regular Backups
Set up automated daily backups of:
- Database
- Plugin files
- Uploads directory

### Monitoring
- Check Debug tab weekly
- Review Stripe webhooks monthly
- Monitor member activity

### Updates
- Keep WordPress updated
- Keep SWPM plugin updated
- Keep theme updated
- Monitor for plugin updates

### Security
- Use strong API keys
- Rotate keys annually
- Monitor for suspicious activity
- Keep HTTPS certificate current

---

## 📝 Version History

- **1.5.0** (2025-11-26) - Security & architecture update
- **1.4.3** (Previous) - Suite consolidation
- **1.4.2** and earlier - Legacy versions

---

## 🙏 Thank You

Thank you for upgrading to OHA Board Hub Suite 1.5.0. This update represents significant improvements in security, performance, and maintainability.

If you have any questions or feedback, please don't hesitate to contact us.

---

**Document Version:** 1.0  
**Last Updated:** November 26, 2025  
**Plugin Version:** 1.5.0
