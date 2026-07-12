# OHA Board Hub Suite v1.6.0 - Webhook Integration Guide

**Date:** January 24, 2026  
**Version:** v1.6.0  
**Status:** PRODUCTION READY - Companion to Vendor Hub v3.0.1

---

## 🎯 What's New in v1.6.0

### Critical Addition: External Plugin Integration Filter

Board Hub now supports companion plugins through a filter hook system. This allows plugins like OHA Vendor Hub to integrate seamlessly without conflicts.

**New Filter:**
```php
apply_filters('oha_process_webhook_event', false, $event)
```

**How It Works:**
1. Board Hub receives webhook from Stripe
2. Validates signature and checks for duplicates
3. **Calls filter to let external plugins process first**
4. If external plugin returns `true` → marks as handled, skips Board Hub processing
5. If external plugin returns `false` → Board Hub processes with internal handlers

**Companion Plugins Using This:**
- OHA Vendor Hub v3.0.1+ (vendor payment processing)
- Future plugins can hook in the same way

---

## 🏗️ Centralized Webhook Architecture

### The Single Source of Truth

**Webhook URL:** `https://ohiohaunters.com/wp-json/oha/v1/stripe-webhook`

**This ONE endpoint handles:**
- ✅ Membership subscriptions
- ✅ Vendor payments  
- ✅ Board member payments
- ✅ Product purchases
- ✅ Customer updates
- ✅ Invoice payments
- ✅ ALL Stripe events

**No other webhook endpoints exist.** Everything flows through Board Hub's centralized handler.

---

## 🔄 Webhook Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                         STRIPE                               │
│                    (sends event)                             │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
    ┌────────────────────────────────────────────────────┐
    │  Board Hub: /wp-json/oha/v1/stripe-webhook        │
    │  (receives ALL Stripe events)                      │
    └────────────────────┬───────────────────────────────┘
                         │
                         ├─► 1. Verify Signature
                         ├─► 2. Validate Event
                         ├─► 3. Check for Duplicate
                         │
                         ▼
    ┌────────────────────────────────────────────────────┐
    │  apply_filters('oha_process_webhook_event', ...)  │
    │  (let companion plugins try first)                 │
    └────────────────┬─────────────┬─────────────────────┘
                     │             │
                     ▼             ▼
        ┌─────────────────┐   ┌──────────────────┐
        │  Vendor Hub     │   │  Future Plugins  │
        │  (checks for    │   │  (can hook in)   │
        │  vendor events) │   │                  │
        └────┬────────────┘   └──────────────────┘
             │
             │ Vendor payment? → YES → Process & return true
             │ Not vendor? → return false
             │
             ▼
    ┌────────────────────────────────────────────────────┐
    │  If no external plugin handled:                    │
    │  Board Hub processes with internal handlers        │
    │  - Memberships                                     │
    │  - Subscriptions                                   │
    │  - Customer updates                                │
    │  - Etc.                                            │
    └────────────────────────────────────────────────────┘
```

---

## 🔌 Integration with Vendor Hub

### How Vendor Hub Hooks In

**Vendor Hub v3.0.1:**
- Does NOT register its own webhook route
- Uses ONLY the filter hook
- Checks event type in filter callback
- Returns `true` if it handles the event
- Returns `false` if not a vendor event

**Vendor Hub Filter Implementation:**
```php
// In Vendor Hub's includes/stripe-webhook.php
add_filter('oha_process_webhook_event', 'oha_vhcpt_handle_vendor_payment_events', 10, 2);

function oha_vhcpt_handle_vendor_payment_events($handled, $event) {
    if ($handled) return $handled; // Already handled by another plugin
    
    $vendor_events = ['checkout.session.completed', 'charge.succeeded', 'payment_intent.succeeded'];
    if (!in_array($event->type, $vendor_events)) {
        return false; // Not a vendor event
    }
    
    // Process vendor payment...
    // Generate HTML agreement, send email, etc.
    
    return true; // Mark as handled
}
```

**Why This Works:**
- Clear separation of concerns
- No route conflicts
- No load order dependencies
- Each plugin handles what it knows best
- Board Hub provides infrastructure, companion plugins add features

---

## 📝 Files Changed in v1.6.0

### 1. includes/api/class-oha-webhook-handler.php
**Changes:**
- Added filter call in `handle()` method (after validation, before processing)
- Updated class documentation with architecture explanation
- Added logging for external plugin handling

**New Code Block:**
```php
// FILTER HOOK: Allow other plugins to process events first
$handled_by_external = apply_filters('oha_process_webhook_event', false, $this->event);

if ($handled_by_external === true) {
    $this->mark_processed();
    OHA_Logger::info('Webhook event handled by external plugin');
    return new WP_REST_Response(['status' => 'success', 'handled_by' => 'external_plugin'], 200);
}
```

### 2. oha-board-hub-suite.php
**Changes:**
- Updated version to 1.6.0
- Enhanced plugin description
- Added comprehensive webhook architecture documentation
- Added changelog entry

**No Functional Changes:**
- All existing features preserved
- All settings remain the same
- No database changes
- Backward compatible

---

## 🚀 Installation / Upgrade

### For New Installations

1. Install Board Hub Suite v1.6.0
2. Configure Stripe settings (API keys, webhook secret)
3. Set webhook URL in Stripe Dashboard:
   ```
   https://ohiohaunters.com/wp-json/oha/v1/stripe-webhook
   ```
4. Install Vendor Hub v3.0.1 (optional companion plugin)
5. Test webhook with Stripe's "Send test webhook" feature

### For Existing Installations (Upgrading)

**Super Simple - No Configuration Changes:**

1. **Upload v1.6.0** via WordPress Plugins → Upload
2. **Replace** when prompted (or deactivate old, delete, upload new, activate)
3. **Done!** No settings changes needed

**Your existing webhook URL stays the same:**
```
https://ohiohaunters.com/wp-json/oha/v1/stripe-webhook
```

**Your existing settings are preserved:**
- API keys
- Webhook secret
- Membership levels
- All configurations

**Companion plugins work automatically:**
- If you have Vendor Hub v3.0.1 installed, it will immediately start using the filter
- No configuration needed in Vendor Hub
- Everything just works

---

## 🧪 Testing After Installation

### Test 1: Board Hub Webhook Works
```bash
# In Stripe Dashboard:
1. Go to Webhooks
2. Find your OHA webhook
3. Click "Send test webhook"
4. Select: checkout.session.completed
5. Click Send

# Expected in WordPress logs:
"Webhook event processed by Board Hub"
"Event type: checkout.session.completed"
```

### Test 2: Vendor Hub Integration Works
```bash
# Prerequisite: Vendor Hub v3.0.1 installed

# In Stripe Dashboard:
1. Send test webhook: checkout.session.completed
2. Include customer email that matches a vendor application

# Expected in WordPress logs:
"OHA VENDOR HUB: Processing payment event"
"Webhook event handled by external plugin"

# Board Hub should NOT process this event
# Vendor Hub handled it via filter
```

### Test 3: Board Hub Handles Non-Vendor Events
```bash
# In Stripe Dashboard:
1. Send test webhook: customer.subscription.created
2. This is a membership event (not vendor)

# Expected in WordPress logs:
"Webhook event processed by Board Hub"
"Handling subscription update"

# Vendor Hub should NOT process this
# Board Hub handled it internally
```

---

## 🔍 Troubleshooting

### Issue: "Both plugins processing the same event"

**Problem:** Webhook logs show both Board Hub and Vendor Hub processing

**Cause:** Vendor Hub didn't return `true` properly

**Fix:** Check Vendor Hub filter function returns `true` when handling event

---

### Issue: "Vendor payments not generating agreements"

**Problem:** Vendor payments completing but no agreements sent

**Cause:** Vendor Hub filter not being called

**Check:**
1. Is Vendor Hub v3.0.1+ installed?
2. Is filter hook registered? Check: `has_filter('oha_process_webhook_event')`
3. Check WordPress logs for filter execution

---

### Issue: "500 webhook errors"

**Problem:** Webhooks failing with 500 errors

**Common Causes:**
1. Signature verification failing → Check webhook secret configured
2. Invalid event structure → Check Stripe sending correct events
3. Plugin conflict → Disable other plugins temporarily

**Debug:**
```php
// Enable WordPress debug logging
// Add to wp-config.php:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Check: /wp-content/debug.log
```

---

## 📚 For Developers

### Creating New Companion Plugins

Want to create a plugin that hooks into the centralized webhook? Here's how:

```php
// In your plugin file
add_filter('oha_process_webhook_event', 'my_plugin_webhook_handler', 10, 2);

function my_plugin_webhook_handler($handled, $event) {
    // If already handled by another plugin, skip
    if ($handled) {
        return $handled;
    }
    
    // Check if this is an event you care about
    if ($event->type !== 'your.event.type') {
        return false; // Not your event, let others try
    }
    
    // Process the event
    try {
        // Your processing logic here
        error_log('My plugin handled: ' . $event->type);
        
        // Return true to indicate you handled it
        return true;
        
    } catch (Exception $e) {
        error_log('My plugin error: ' . $e->getMessage());
        return false; // Let Board Hub try if you fail
    }
}
```

**Best Practices:**
1. Always check if `$handled` is already true (respect other plugins)
2. Return `false` if event is not yours
3. Return `true` only if you successfully handled it
4. Use descriptive logging
5. Handle errors gracefully

---

## 🤝 Compatibility Matrix

| Board Hub Version | Vendor Hub Version | Status |
|-------------------|-------------------|---------|
| 1.6.0             | 3.0.1             | ✅ Perfect |
| 1.6.0             | 3.0.0             | ⚠️ Use 3.0.1 |
| 1.5.2             | 3.0.1             | ❌ Upgrade Board Hub |
| 1.5.2             | 3.0.0             | ❌ Upgrade both |

**Recommendation:** Always use latest versions of both plugins together.

---

## 📋 Checklist for Going Live

### Board Hub Setup
- [ ] Plugin version 1.6.0 installed
- [ ] Stripe API keys configured (test & live)
- [ ] Webhook secret configured
- [ ] Webhook URL set in Stripe Dashboard
- [ ] Test webhook sent successfully
- [ ] Logs show successful processing

### Vendor Hub Setup (if using)
- [ ] Plugin version 3.0.1 installed
- [ ] No separate webhook configured
- [ ] Filter hook active (check logs)
- [ ] Test vendor payment processes
- [ ] HTML agreement generates
- [ ] Email sends successfully

### System Health
- [ ] No 500 errors in logs
- [ ] No route registration conflicts
- [ ] Both plugins logging correctly
- [ ] Events processing < 2 seconds
- [ ] No duplicate processing

---

## 🎓 Key Concepts

### Single Webhook Endpoint
- ONE webhook URL handles everything
- No separate endpoints for different features
- Stripe webhook configuration is simpler
- Easier to maintain and debug

### Filter-Based Integration
- Companion plugins hook via filters (not routes)
- No conflicts or competition
- Clear processing order
- Each plugin decides what it handles

### Backward Compatibility
- All existing features preserved
- Settings remain unchanged
- No breaking changes
- Upgrade is seamless

---

## 📞 Support

**Questions about Board Hub v1.6.0?**
- Check logs: `/wp-content/debug.log`
- Review this guide's troubleshooting section
- Contact: webmaster@ohiohaunters.com

**Need help with Vendor Hub integration?**
- Ensure both plugins are latest versions
- Check filter hook is registered
- Review webhook logs for processing order
- See "Integration with Vendor Hub" section above

---

**Version:** v1.6.0  
**Release Date:** January 24, 2026  
**Requires:** WordPress 6.0+, PHP 8.0+  
**Companion:** OHA Vendor Hub v3.0.1+  
**Status:** Production Ready ✅
