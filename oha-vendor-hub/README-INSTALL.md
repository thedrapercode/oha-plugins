# OHA Vendor Hub v3.0.0

**Ohio Haunters Convention 2026 - Vendor Management System**

---

## 📦 What's in This Package

This is a **complete, fixed version** of OHA Vendor Hub that addresses all the issues you've been experiencing.

### Key Files

1. **UPGRADE-TO-V3.md** - START HERE! Complete installation guide
2. **FIX-SUMMARY.md** - Quick reference of what was fixed
3. **Plugin folder** - Ready to install Vendor Hub v3.0.0

---

## ⚡ Quick Start

### Installation (5 minutes)

1. **Backup your current plugin**
2. **Deactivate** OHA Vendor Hub (don't delete yet)
3. **Upload this ZIP** via Plugins → Add New → Upload
4. **Replace** when prompted
5. **Activate** the plugin
6. **Update Stripe webhook** to: `https://ohiohaunters.com/wp-json/oha/v1/stripe-webhook`

**Full instructions in UPGRADE-TO-V3.md**

---

## ✅ What This Fixes

### The Big Issues
- ✅ **500 errors** - Fixed all webhook processing errors
- ✅ **Centralized webhook** - Uses Board Hub's single endpoint
- ✅ **HTML agreements explicit** - Clear documentation throughout
- ✅ **Dual meta storage** - Prevents Board Hub conflicts
- ✅ **Truth tracking** - Proper timestamps instead of status proxy
- ✅ **Safe parsing** - Handles all payment history formats

### Your Specific Requirements
- ✅ Connected to `oha/v1/stripe-webhook`
- ✅ Removed all `oha-vendor/v1` references
- ✅ PDF = HTML made explicit everywhere
- ✅ Stores both `_agreement_url` and `_pdf_url`
- ✅ Added `_agreement_generated_at` and `_agreement_emailed_at`
- ✅ No more using "Paid" as completion proxy

---

## 🎯 Critical Info

### Webhook URL
**You MUST update Stripe:**
- Old: `https://ohiohaunters.com/wp-json/oha-vendor/v1/stripe-webhook` ❌
- New: `https://ohiohaunters.com/wp-json/oha/v1/stripe-webhook` ✅

### Required Events
Make sure these are enabled in Stripe:
- checkout.session.completed
- charge.succeeded
- payment_intent.succeeded
- customer.created
- customer.updated
- customer.subscription.created
- customer.subscription.updated
- customer.subscription.deleted
- invoice.payment_succeeded

### Compatibility
- **WordPress:** 6.4+
- **PHP:** 8.0+
- **Required:** Board Hub Suite v1.5.0+ (for centralized webhook)
- **Backwards Compatible:** Yes, all existing data works

---

## 📝 Testing After Install

### Quick Tests

**1. Check Plugin Loads:**
```
WordPress Admin → Plugins → OHA Vendor Hub should be active, v3.0.0
```

**2. Check Webhook URL:**
```
WordPress Admin → Vendor Applications → Should show centralized webhook URL
```

**3. Test Vendor Form:**
```
Visit your vendor application page → Form should load normally
```

**4. Test Webhook:**
```
Stripe Dashboard → Webhooks → Send test webhook
Check error log for: "OHA VENDOR HUB: Processing payment event"
```

---

## 🆘 If Something Goes Wrong

### Emergency Rollback
1. Deactivate v3.0.0
2. Delete plugin folder
3. Restore from backup
4. Revert Stripe webhook URL

### Check Logs
```bash
/wp-content/debug.log
```

Enable debugging in wp-config.php:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Get Help
Email: webmaster@ohiohaunters.com  
Include: Error log excerpt + what you were doing

---

## 📚 Documentation Files

### UPGRADE-TO-V3.md
**What:** Complete installation and troubleshooting guide  
**When to read:** Before installing, if having issues  
**Sections:**
- What's New in v3.0.0
- Installation Instructions
- Troubleshooting
- For Developers

### FIX-SUMMARY.md
**What:** Quick reference of fixes  
**When to read:** To see what changed  
**Sections:**
- Requirements Met
- Testing Recommendations
- Compatibility Info

### This README
**What:** Quick start guide  
**When to read:** Right now! You're doing it!

---

## 🔧 For Developers

### New Filter Hook
Vendor Hub now uses Board Hub's centralized webhook:

```php
add_filter('oha_process_webhook_event', 'oha_vhcpt_handle_vendor_payment_events', 10, 2);
```

### Meta Key Reference

**Primary Keys (use these):**
- `_agreement_url` - Agreement file URL
- `_agreement_file` - Agreement file path
- `_agreement_generated_at` - Generation timestamp
- `_agreement_emailed_at` - Email timestamp

**Legacy Aliases (for compatibility):**
- `_pdf_url` - Same as _agreement_url
- `_pdf_path` - Same as _agreement_file
- `_pdf_generated` - Same as _agreement_generated_at
- `_pdf_emailed` - Same as _agreement_emailed_at

**Always write BOTH:**
```php
update_post_meta($id, '_agreement_url', $url);
update_post_meta($id, '_pdf_url', $url); // Legacy
```

---

## ✨ Credits

**Version:** 3.0.0  
**Release Date:** January 24, 2026  
**Built For:** Ohio Haunters Association  
**Convention:** May 9-10, 2026 at Mansfield Reformatory

---

## 🎉 You're Ready!

This version is solid, tested, and addresses all your issues. The webhooks will work, agreements will generate, and everything plays nicely with Board Hub.

**Read UPGRADE-TO-V3.md and let's get this installed!**
