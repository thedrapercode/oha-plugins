# OHA Vendor Hub v3.0.0 - Upgrade Guide

**Date:** January 24, 2026  
**Plugin:** OHA Vendor Hub  
**New Version:** v3.0.0  
**Previous Version:** v2.4.9 (HTML-ONLY)

---

## 🎯 What's New in v3.0.0

### Critical Architecture Changes

#### 1. Centralized Webhook System
**BEFORE:** Vendor Hub had its own webhook at `oha-vendor/v1/stripe-webhook`  
**NOW:** Uses Board Hub's centralized webhook at `oha/v1/stripe-webhook`

**Benefits:**
- Single Stripe webhook handles ALL payment types (memberships, vendors, board payments)
- No more webhook conflicts or duplicate processing
- Simplified Stripe configuration
- Better error handling and logging

#### 2. HTML Agreements Made Explicit
**The Truth:** "PDF" in this plugin has ALWAYS meant "HTML Agreement"
- Files are `.html` (not `.pdf`)
- Users save to PDF via browser Print function
- Legacy naming (`pdf-generator.php`, `_pdf_url`) retained for backwards compatibility

**What Changed:**
- Added clear comments throughout codebase
- Made file types explicit in all functions
- Updated documentation to prevent confusion

#### 3. Dual Meta Storage for Compatibility
**NEW:** Stores BOTH meta keys to prevent Board Hub/Vendor Hub conflicts

```php
// Primary keys (NEW)
_agreement_url
_agreement_file
_agreement_generated_at
_agreement_emailed_at

// Legacy aliases (for backwards compatibility)
_pdf_url
_pdf_path
_pdf_generated
_pdf_emailed
```

This ensures both plugins can find agreement data regardless of which created it.

#### 4. Truth Tracking Instead of Status Proxying
**BEFORE:** Used `status="Paid"` as proxy for "agreement sent"  
**NOW:** Proper tracking with dedicated timestamps

**Truth Check:**
- `_agreement_generated_at` - When agreement file was created
- `_agreement_emailed_at` - When email was successfully sent
- Status is now just UI state, not truth source

### Bug Fixes

1. **Fixed 500 Errors:**
   - Safe payment history parsing (handles array/JSON/serialized)
   - Proper error handling in webhook processing
   - No more fatal json_decode errors

2. **Fixed Status Handling:**
   - Normalized status checks (handles PAID/Paid/paid)
   - Case-insensitive comparison throughout

3. **Fixed Webhook Retry Logic:**
   - Smart idempotency checks
   - Regenerates agreements if status=Paid but files missing
   - Prevents duplicate emails

---

## 📦 Installation Instructions

### IMPORTANT: Backup First!
```bash
# Via SSH
cd /path/to/wp-content/plugins
cp -r oha-vendor-hub oha-vendor-hub-backup-$(date +%Y%m%d)

# Or use your hosting control panel to download a backup
```

### Installation Steps

#### Step 1: Deactivate Current Version
1. Go to WordPress Admin → Plugins
2. Find "OHA Vendor Hub"
3. Click "Deactivate"

#### Step 2: Replace Plugin Files
Choose ONE method:

**Method A: Upload via WordPress (Recommended)**
1. Go to Plugins → Add New → Upload Plugin
2. Upload `oha-vendor-hub-v3_0_0.zip`
3. When prompted "Destination folder already exists", click "Replace current with uploaded"
4. Activate the plugin

**Method B: FTP/File Manager**
1. Delete old plugin folder: `/wp-content/plugins/oha-vendor-hub/`
2. Upload new `oha-vendor-hub` folder
3. Return to WordPress Admin → Plugins
4. Activate "OHA Vendor Hub"

**Method C: SSH**
```bash
cd /path/to/wp-content/plugins
rm -rf oha-vendor-hub
unzip oha-vendor-hub-v3_0_0.zip
```
Then activate via WordPress admin.

#### Step 3: Configure Stripe Webhook

**CRITICAL:** Update your Stripe webhook configuration

1. Go to https://dashboard.stripe.com/webhooks
2. Find your existing OHA webhook
3. Update the endpoint URL to:
   ```
   https://ohiohaunters.com/wp-json/oha/v1/stripe-webhook
   ```

4. Ensure these events are selected:
   - `checkout.session.completed`
   - `charge.succeeded`
   - `payment_intent.succeeded`
   - `customer.created`
   - `customer.updated`
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `invoice.payment_succeeded`

5. Save the webhook configuration

6. Copy the "Signing secret" (starts with `whsec_`)

7. Go to WordPress Admin → Board Hub Settings → Stripe
8. Paste the signing secret
9. Save settings

#### Step 4: Verify Everything Works

**Test Checklist:**
- [ ] Vendor application form loads
- [ ] Agreement signing works
- [ ] Stripe payment redirects properly
- [ ] Test webhook with Stripe's "Send test webhook" feature
- [ ] Check error logs for any issues
- [ ] Verify existing vendor agreements still download

---

## 🔧 Troubleshooting

### Issue: "Webhook not working"

**Check:**
1. Webhook URL in Stripe matches exactly: `https://ohiohaunters.com/wp-json/oha/v1/stripe-webhook`
2. All required events are selected
3. Signing secret is configured in Board Hub Settings
4. Check error logs: `wp-content/debug.log`

**Common Fix:**
```bash
# Enable debug logging to see what's happening
# Add to wp-config.php:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Issue: "Agreements not generating"

**Check:**
1. Directory is writable: `/wp-content/uploads/vendor-agreements/`
2. Check meta for vendor: `_agreement_generated_at` should exist
3. Look for errors in `/wp-content/debug.log`

**Test Generation:**
```php
// Test in WordPress admin Tools → Code Snippets
$app_id = 4589; // Replace with actual vendor ID
$result = oha_vhcpt_generate_vendor_pdf($app_id);
if ($result) {
    echo "Success! URL: " . $result['url'];
} else {
    echo "Failed - check error log";
}
```

### Issue: "Old agreements don't show"

**This is normal!** Old agreements used different meta keys.

**Fix:** Run this migration snippet once:
```php
// Migrate old meta keys to new dual-storage format
$vendors = get_posts([
    'post_type' => 'vendor_application',
    'posts_per_page' => -1,
    'post_status' => 'any'
]);

foreach ($vendors as $vendor) {
    // Get existing URL
    $url = get_post_meta($vendor->ID, '_pdf_url', true);
    if (empty($url)) {
        $url = get_post_meta($vendor->ID, '_agreement_url', true);
    }
    
    // Get existing path
    $path = get_post_meta($vendor->ID, '_pdf_path', true);
    if (empty($path)) {
        $path = get_post_meta($vendor->ID, '_agreement_file', true);
    }
    
    // Store in both formats
    if (!empty($url)) {
        update_post_meta($vendor->ID, '_agreement_url', $url);
        update_post_meta($vendor->ID, '_pdf_url', $url);
    }
    
    if (!empty($path)) {
        update_post_meta($vendor->ID, '_agreement_file', $path);
        update_post_meta($vendor->ID, '_pdf_path', $path);
    }
}

echo "Migration complete for " . count($vendors) . " vendors";
```

---

## ⚠️ Breaking Changes

### Webhook Endpoint Changed
If you have any custom code or integrations pointing to the old webhook:
- **OLD:** `https://ohiohaunters.com/wp-json/oha-vendor/v1/stripe-webhook`
- **NEW:** `https://ohiohaunters.com/wp-json/oha/v1/stripe-webhook`

Update all references.

### Removed Functions
None! All public functions remain for backwards compatibility.

### Removed Hooks
- REST route `oha-vendor/v1/stripe-webhook` is no longer registered
- Now uses filter: `oha_process_webhook_event`

---

## 🎓 For Developers

### How the Centralized Webhook Works

**Flow:**
1. Stripe sends event to `oha/v1/stripe-webhook`
2. Board Hub's `OHA_Webhook_Handler` receives it
3. Validates signature and checks for duplicates
4. Calls filter: `apply_filters('oha_process_webhook_event', false, $event)`
5. Vendor Hub hooks into filter and checks if it's a vendor payment
6. If yes, processes vendor application (generates agreement, sends email)
7. Returns true to mark as handled

**Hook Implementation:**
```php
// In Vendor Hub's stripe-webhook.php
add_filter('oha_process_webhook_event', 'oha_vhcpt_handle_vendor_payment_events', 10, 2);

function oha_vhcpt_handle_vendor_payment_events($handled, $event) {
    // Only handle vendor payment events
    if ($handled) return $handled;
    
    $vendor_events = ['checkout.session.completed', 'charge.succeeded', 'payment_intent.succeeded'];
    if (!in_array($event->type, $vendor_events)) {
        return false; // Not our event
    }
    
    // Process vendor payment...
    return true; // Mark as handled
}
```

### Meta Keys Reference

**Primary Keys (use these for new code):**
```php
_agreement_url           // Agreement file URL
_agreement_file          // Agreement file path
_agreement_generated_at  // When generated (MySQL datetime)
_agreement_emailed_at    // When emailed (MySQL datetime)
```

**Legacy Aliases (for compatibility):**
```php
_pdf_url           // Same as _agreement_url
_pdf_path          // Same as _agreement_file
_pdf_generated     // Same as _agreement_generated_at
_pdf_emailed       // Same as _agreement_emailed_at
```

**Always write BOTH to ensure compatibility:**
```php
update_post_meta($app_id, '_agreement_url', $url);
update_post_meta($app_id, '_pdf_url', $url); // Legacy
```

---

## 📞 Support

**Issues?**
1. Check error logs: `/wp-content/debug.log`
2. Review this guide's Troubleshooting section
3. Contact: webmaster@ohiohaunters.com

**Emergency Rollback:**
If something goes terribly wrong:
1. Deactivate v3.0.0
2. Delete plugin folder
3. Restore from backup
4. Activate backup version
5. Revert Stripe webhook URL to old endpoint (if you changed it)

---

## ✅ Post-Installation Checklist

After upgrading, verify:

- [ ] Plugin activated successfully
- [ ] No PHP errors in error log
- [ ] Vendor application form works
- [ ] Stripe webhook updated in dashboard
- [ ] Test payment completes successfully
- [ ] Agreement generates and emails
- [ ] Existing vendor data intact
- [ ] Board Hub admin interface works
- [ ] CSV export functions properly

---

**Version:** v3.0.0  
**Release Date:** January 24, 2026  
**Tested With:** WordPress 6.4+, PHP 8.0+  
**Requires:** Board Hub Suite v1.5.0+ (for centralized webhook)
