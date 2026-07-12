# Board Hub + Vendor Hub - Integration Compatibility Guide

**Date:** January 24, 2026  
**Board Hub:** v1.6.0  
**Vendor Hub:** v3.0.1  
**Status:** Fully Compatible & Tested

---

## 🎯 Quick Reference

### Single Webhook Architecture

```
STRIPE
  │
  └──► https://ohiohaunters.com/wp-json/oha/v1/stripe-webhook
         │
         └──► Board Hub Suite v1.6.0
                │
                ├──► Validates, checks duplicates
                │
                ├──► Calls filter: apply_filters('oha_process_webhook_event', ...)
                │     │
                │     └──► Vendor Hub v3.0.1 checks if vendor event
                │            │
                │            ├──► YES: Generate agreement, send email, return true
                │            └──► NO: Return false, let Board Hub handle
                │
                └──► If not handled: Board Hub processes internally
```

**Key Point:** Board Hub OWNS the route. Vendor Hub HOOKS via filter.

---

## 📦 Installation Order

### Option A: Fresh Installation
1. Install Board Hub Suite v1.6.0
2. Configure Stripe (API keys, webhook secret)
3. Set Stripe webhook URL
4. Test Board Hub webhook
5. Install Vendor Hub v3.0.1
6. Test vendor payment (automatically works)

### Option B: Upgrading Existing System
1. **Upgrade Board Hub** to v1.6.0 first
2. **Then upgrade Vendor Hub** to v3.0.1
3. No configuration changes needed
4. Test both plugins

**Never upgrade in reverse order!** Board Hub must have the filter hook before Vendor Hub needs it.

---

## 🔧 Configuration Requirements

### Board Hub Configuration
```
WordPress Admin → OHA Board Hub → Settings → Stripe

Required:
✅ API Secret Key (live)
✅ Webhook Signing Secret
✅ Endpoint Path: oha/v1/stripe-webhook (default, don't change)

Optional:
- Test mode API key
- Timestamp tolerance (default: 300s)
- Membership level IDs
```

### Vendor Hub Configuration
```
WordPress Admin → Vendor Applications → Settings

Required:
✅ Nothing! Uses Board Hub's webhook

Optional:
- Vendor-specific settings (booth prices, etc.)
- Email templates
- Agreement customization
```

**Critical:** Vendor Hub does NOT have its own webhook settings. It uses Board Hub's webhook exclusively.

---

## 🧪 Testing Both Plugins Together

### Test 1: Membership Payment (Board Hub Handles)
```bash
Stripe Dashboard → Send test webhook
Event type: customer.subscription.created
Customer email: test@example.com (not a vendor)

Expected Logs:
✅ "Webhook event received"
✅ "Processing subscription update"
✅ "Webhook event processed by Board Hub"

NOT Expected:
❌ "OHA VENDOR HUB" messages
```

**Result:** Board Hub handles membership, Vendor Hub ignores it.

---

### Test 2: Vendor Payment (Vendor Hub Handles)
```bash
Stripe Dashboard → Send test webhook
Event type: checkout.session.completed
Customer email: vendor@example.com (matches vendor application)

Expected Logs:
✅ "Webhook event received"
✅ "OHA VENDOR HUB: Processing payment event"
✅ "Found vendor application ID: X"
✅ "Generating HTML agreement"
✅ "Agreement generated and emailed successfully"
✅ "Webhook event handled by external plugin"

NOT Expected:
❌ "Processing subscription update"
❌ "Webhook event processed by Board Hub"
```

**Result:** Vendor Hub handles vendor payment, Board Hub skips processing.

---

### Test 3: Unknown Event (Board Hub Logs Only)
```bash
Stripe Dashboard → Send test webhook
Event type: customer.created
Customer email: random@example.com

Expected Logs:
✅ "Webhook event received"
✅ "Handling customer update"
✅ "Webhook event processed by Board Hub"

NOT Expected:
❌ "OHA VENDOR HUB" messages
```

**Result:** Vendor Hub ignores it, Board Hub handles it.

---

## 🔍 Troubleshooting Integration Issues

### Issue: Both Plugins Processing Same Event

**Symptom:** Logs show both plugins handling same webhook

**Diagnosis:**
```bash
# Check Vendor Hub filter return value
# Should see in logs:
"OHA VENDOR HUB: Processing payment event"

# If you also see:
"Webhook event processed by Board Hub"
# Then Vendor Hub didn't return true properly
```

**Fix:**
1. Check Vendor Hub v3.0.1 is active
2. Check filter function exists: `oha_vhcpt_handle_vendor_payment_events`
3. Verify function returns `true` when handling events

---

### Issue: Neither Plugin Processing Event

**Symptom:** Webhook receives event but no processing logs

**Diagnosis:**
```bash
# Check for signature verification failure:
"Webhook signature verification failed"

# Or invalid event:
"Invalid webhook event received"
```

**Fix:**
1. Verify webhook secret is configured in Board Hub
2. Check Stripe webhook uses correct URL
3. Ensure webhook secret matches Stripe dashboard

---

### Issue: Vendor Hub Not Generating Agreements

**Symptom:** Vendor payments complete but no agreements

**Diagnosis:**
```bash
# Check if filter is being called:
# Should see in logs:
"Webhook event received"

# But NOT:
"OHA VENDOR HUB: Processing payment event"
```

**Fix:**
1. Verify Vendor Hub v3.0.1 is installed and active
2. Check filter is registered:
   ```php
   // In WordPress console or snippet:
   var_dump(has_filter('oha_process_webhook_event'));
   // Should output: int(10) or true
   ```
3. Verify vendor application exists with matching email
4. Check event type is one Vendor Hub handles

---

## 📊 Integration Health Check

### Quick Verification Script

```php
// Run in WordPress Code Snippets or console

echo "=== Board Hub + Vendor Hub Integration Check ===\n\n";

// 1. Check Board Hub version
if (defined('OHA_BHS_VERSION')) {
    echo "✅ Board Hub installed: v" . OHA_BHS_VERSION . "\n";
    if (version_compare(OHA_BHS_VERSION, '1.6.0', '>=')) {
        echo "   ✅ Version is 1.6.0+ (filter hook available)\n";
    } else {
        echo "   ❌ Version is < 1.6.0 (upgrade required)\n";
    }
} else {
    echo "❌ Board Hub not installed\n";
}

// 2. Check Vendor Hub version
if (defined('OHA_VHCPT_VER')) {
    echo "✅ Vendor Hub installed: v" . OHA_VHCPT_VER . "\n";
    if (version_compare(OHA_VHCPT_VER, '3.0.1', '>=')) {
        echo "   ✅ Version is 3.0.1+ (filter-based integration)\n";
    } else {
        echo "   ❌ Version is < 3.0.1 (upgrade required)\n";
    }
} else {
    echo "⚠️  Vendor Hub not installed (optional)\n";
}

// 3. Check filter hook
if (has_filter('oha_process_webhook_event')) {
    echo "✅ Webhook filter hook is active\n";
    echo "   Hooked functions:\n";
    global $wp_filter;
    if (isset($wp_filter['oha_process_webhook_event'])) {
        foreach ($wp_filter['oha_process_webhook_event']->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $func_name = is_array($callback['function']) 
                    ? get_class($callback['function'][0]) . '::' . $callback['function'][1]
                    : $callback['function'];
                echo "   - Priority $priority: $func_name\n";
            }
        }
    }
} else {
    echo "⚠️  Webhook filter hook not found (Vendor Hub not hooked in)\n";
}

// 4. Check webhook route
$routes = rest_get_server()->get_routes();
if (isset($routes['/oha/v1/stripe-webhook'])) {
    echo "✅ Webhook route registered: /oha/v1/stripe-webhook\n";
} else {
    echo "❌ Webhook route NOT registered (Board Hub issue)\n";
}

// 5. Check webhook secret configured
$settings = get_option('oha_bh_suite_settings', []);
$webhook_secret = $settings['stripe']['webhook_secret'] ?? '';
if (!empty($webhook_secret)) {
    echo "✅ Webhook secret configured\n";
} else {
    echo "❌ Webhook secret NOT configured\n";
}

echo "\n=== Integration Check Complete ===\n";
```

**Expected Output (Healthy System):**
```
=== Board Hub + Vendor Hub Integration Check ===

✅ Board Hub installed: v1.6.0
   ✅ Version is 1.6.0+ (filter hook available)
✅ Vendor Hub installed: v3.0.1
   ✅ Version is 3.0.1+ (filter-based integration)
✅ Webhook filter hook is active
   Hooked functions:
   - Priority 10: oha_vhcpt_handle_vendor_payment_events
✅ Webhook route registered: /oha/v1/stripe-webhook
✅ Webhook secret configured

=== Integration Check Complete ===
```

---

## 🎓 Architecture Comparison

### OLD Architecture (Before v1.6.0 + v3.0.1) ❌
```
Stripe
  ├──► /oha/v1/stripe-webhook (Board Hub membership endpoint)
  └──► /oha-vendor/v1/stripe-webhook (Vendor Hub vendor endpoint)

Problems:
- Two separate webhooks to configure
- Route conflicts possible
- Load order matters
- Duplicate processing possible
- Maintenance nightmare
```

### NEW Architecture (v1.6.0 + v3.0.1) ✅
```
Stripe
  └──► /oha/v1/stripe-webhook (ONE endpoint, Board Hub owns)
         │
         └──► Filter: apply_filters('oha_process_webhook_event', ...)
               │
               ├──► Vendor Hub (checks, handles vendors)
               ├──► Future plugins (can hook in)
               └──► Board Hub (handles everything else)

Benefits:
- Single webhook configuration
- No conflicts
- Load order doesn't matter
- Clear processing order
- Easy to maintain
- Easy to add new features
```

---

## 📝 Shared Terminology & Standards

Both plugins now use consistent terminology:

### Agreement Files
- **Type:** HTML files (not PDF)
- **Extension:** `.html`
- **User workflow:** Browser Print → Save as PDF
- **Storage:** `/wp-content/uploads/vendor-agreements/`

### Meta Keys (Vendor Applications)
Both plugins recognize these keys:

**Primary Keys:**
- `_agreement_url` - Agreement file URL
- `_agreement_file` - Agreement file path
- `_agreement_generated_at` - Generation timestamp
- `_agreement_emailed_at` - Email sent timestamp

**Legacy Aliases (for compatibility):**
- `_pdf_url` - Same as `_agreement_url`
- `_pdf_path` - Same as `_agreement_file`
- `_pdf_generated` - Same as `_agreement_generated_at`
- `_pdf_emailed` - Same as `_agreement_emailed_at`

**Why both?** Ensures either plugin can read data created by the other.

### Status Values
- `Submitted` - Application submitted, not signed
- `Agreement Signed` - Signed but not paid
- `Paid` - Payment complete (final status)

**Case handling:** Both plugins normalize status checks (handles PAID/Paid/paid)

### History Storage
- **Format:** JSON array stored as string
- **Key:** `_oha_history`
- **Parsing:** Both plugins use safe parsing (handles array/JSON/serialized)
- **Entry Structure:**
  ```php
  [
      'type' => 'event_type',
      'by' => 'username or system',
      'ts' => 'MySQL datetime',
      // ... event-specific data
  ]
  ```

---

## 🚀 Deployment Checklist

### Pre-Deployment
- [ ] Backup database
- [ ] Backup current plugins
- [ ] Test in staging environment (if available)
- [ ] Note current webhook URL from Stripe

### Deployment
- [ ] Upload Board Hub v1.6.0
- [ ] Activate Board Hub
- [ ] Verify webhook route exists
- [ ] Upload Vendor Hub v3.0.1
- [ ] Activate Vendor Hub
- [ ] Run integration health check script

### Post-Deployment
- [ ] Send test membership webhook (Board Hub handles)
- [ ] Send test vendor webhook (Vendor Hub handles)
- [ ] Check logs for proper processing
- [ ] Verify no 500 errors
- [ ] Test live payment (small amount)
- [ ] Confirm agreement generates (if vendor payment)
- [ ] Confirm email sends

### Rollback Plan (if needed)
- [ ] Deactivate v1.6.0 / v3.0.1
- [ ] Restore from backup
- [ ] Reactivate old versions
- [ ] No webhook URL changes needed (same URL)

---

## 📞 Support & Resources

**Documentation:**
- Board Hub: See `WEBHOOK-INTEGRATION-v1.6.0.md`
- Vendor Hub: See `CRITICAL-FIXES-v3.0.1.md` and `UPGRADE-TO-V3.md`

**Quick Help:**
- Integration issues: This document
- Webhook errors: Check logs first
- Contact: webmaster@ohiohaunters.com

**Resources:**
- Integration health check script (above)
- Test scenarios (above)
- Troubleshooting guide (above)

---

**Compatibility:** ✅ Fully Compatible  
**Testing:** ✅ All Scenarios Pass  
**Production Ready:** ✅ Yes  
**Recommended:** ✅ Use Together
