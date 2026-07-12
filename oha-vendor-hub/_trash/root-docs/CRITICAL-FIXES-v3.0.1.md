# OHA Vendor Hub v3.0.1 - CRITICAL FIXES

**Date:** January 24, 2026  
**Version:** v3.0.1  
**Status:** Emergency patch for v3.0.0

---

## 🚨 What Was Wrong with v3.0.0

You were absolutely right. v3.0.0 had **three critical architectural conflicts** that would cause exactly the 500 webhook errors you were experiencing:

### Issue #1: Route Registration Conflict ❌
**Problem:** Vendor Hub was STILL registering the `/oha/v1/stripe-webhook` route, even though Board Hub also owns it.

**Location:** `oha-vendor-hub.php` lines 144-219

**Code that was there:**
```php
add_action('rest_api_init', function() {
    register_rest_route('oha/v1', '/stripe-webhook', [
        'methods' => 'POST',
        'callback' => 'oha_vhcpt_shared_webhook_handler',
        'permission_callback' => '__return_true',
    ]);
}, 5);
```

**Why this broke:**
- Both plugins tried to register the same route
- Depending on load order, either plugin could "win"
- Sometimes vendor logic ran, sometimes it didn't
- Appeared random, caused intermittent failures

**Fix:** ✅ **COMPLETELY REMOVED** the route registration and handler function

**Now:** Board Hub owns the route, Vendor Hub ONLY uses the filter

---

### Issue #2: Inconsistent Webhook Strategy ❌
**Problem:** Plugin tried to do BOTH filter-based AND route-based webhook handling simultaneously.

**Evidence:**
- `includes/stripe-webhook.php` said: "Board Hub handles webhook, we use filter"
- `oha-vendor-hub.php` said: "We register the route and forward to handler"
- Handler checked for `oha_vhcpt_handle_stripe_webhook()` function that doesn't exist

**Why this broke:**
- Webhook could hit vendor's route
- Try to call non-existent function
- Return "received" but do nothing
- No agreement generated, no email sent

**Fix:** ✅ **REMOVED** all route-based code, kept ONLY filter-based approach

**Now:** Single, consistent strategy - filter-based integration with Board Hub

---

### Issue #3: Unsafe History Logging ❌
**Problem:** `oha_vhcpt_append_history()` assumed `_oha_history` was always JSON string, but it could be an array.

**Location:** `includes/actions.php` lines 4-15

**Code that was there:**
```php
function oha_vhcpt_append_history($post_id, $entry){
    $hist = get_post_meta($post_id, '_oha_history', true);
    $arr = [];
    if ($hist){
        $arr = json_decode($hist, true);  // ❌ FATAL if $hist is array
        if (!is_array($arr)) $arr = [];
    }
    // ...
}
```

**Why this broke:**
- WordPress can return `_oha_history` as array (not JSON string)
- `json_decode()` on array = fatal TypeError
- This happens during webhook processing
- Causes 500 error, breaks email sending

**Fix:** ✅ **SAFE PARSING** using exact code from your handover document

**Now:**
```php
// Handle serialized data
if (is_string($hist)) {
    $maybe = maybe_unserialize($hist);
    if ($maybe !== false || $hist === 'b:0;') {
        $hist = $maybe;
    }
}

// Handle array or JSON
if (is_array($hist)) {
    $arr = $hist;
} elseif (is_string($hist) && $hist !== '') {
    $decoded = json_decode($hist, true);
    if (is_array($decoded)) {
        $arr = $decoded;
    }
}
```

---

## ✅ What v3.0.1 Changes

### Files Modified

**1. oha-vendor-hub.php**
- ✅ Removed lines 140-219 (entire route registration + handler)
- ✅ Added clear comment explaining webhook architecture
- ✅ Updated version to 3.0.1
- ✅ Updated changelog

**2. includes/actions.php**
- ✅ Replaced unsafe `oha_vhcpt_append_history()` function
- ✅ Now uses safe parsing for array/JSON/serialized formats
- ✅ Prevents all json_decode fatal errors

**3. No other files changed**
- All the good stuff from v3.0.0 is preserved
- Dual meta storage still there
- Truth tracking still there
- Safe payment history parsing still there
- HTML agreement documentation still there

---

## 🎯 Verification

### No Route Registration
```bash
$ grep -rn "register_rest_route" oha-vendor-hub --include="*.php"
# Result: Nothing found ✅
```

### Only Filter Hook
```bash
$ grep -rn "add_filter.*oha_process_webhook_event" oha-vendor-hub
# Result: includes/stripe-webhook.php:23 ✅
```

### Safe History Function
```bash
$ grep -A5 "function oha_vhcpt_append_history" includes/actions.php
# Result: Safe parsing code present ✅
```

---

## 📋 Testing After Install

### Test 1: Route Ownership
```php
// In WordPress, check registered routes
$routes = rest_get_server()->get_routes();
$webhook_route = $routes['/oha/v1/stripe-webhook'] ?? null;

// Should show Board Hub's handler, NOT Vendor Hub's
print_r($webhook_route);
```

### Test 2: Filter Hook Active
```php
// Check if filter is registered
global $wp_filter;
$hooks = $wp_filter['oha_process_webhook_event'] ?? null;

// Should show Vendor Hub's function
print_r($hooks);
```

### Test 3: Safe History Append
```php
// Test with array data (previously would fatal)
$test_id = 4589; // Any vendor ID
update_post_meta($test_id, '_oha_history', ['test' => 'array']);

// This should NOT fatal
oha_vhcpt_append_history($test_id, ['type' => 'test']);

// Check result
$hist = get_post_meta($test_id, '_oha_history', true);
echo "Success! History: " . $hist;
```

---

## 🚀 Installation

**If you already installed v3.0.0:**
1. Simply upload v3.0.1 over it (replace files)
2. No deactivation needed
3. No settings changes needed
4. Stripe webhook URL stays the same

**If starting fresh:**
1. Follow same steps as v3.0.0 installation
2. Everything in UPGRADE-TO-V3.md still applies

---

## 📊 Comparison

| Issue | v3.0.0 | v3.0.1 |
|-------|--------|--------|
| Route registration conflict | ❌ Present | ✅ Fixed |
| Inconsistent webhook strategy | ❌ Dual approach | ✅ Filter only |
| json_decode fatal errors | ❌ Unsafe | ✅ Safe parsing |
| Centralized webhook | ✅ Attempted | ✅ Properly implemented |
| HTML agreements explicit | ✅ Yes | ✅ Yes |
| Dual meta storage | ✅ Yes | ✅ Yes |
| Truth tracking | ✅ Yes | ✅ Yes |

---

## 💡 Key Takeaways

**What you said:**
> "Only ONE plugin should register `/wp-json/oha/v1/stripe-webhook`"

**What v3.0.0 did:** Both plugins tried to register it ❌

**What v3.0.1 does:** Only Board Hub registers it ✅

---

**What you said:**
> "Vendor Hub should ONLY hook via the filter"

**What v3.0.0 did:** Filter + route + handler (inconsistent) ❌

**What v3.0.1 does:** Filter only (consistent) ✅

---

**What you said:**
> "Fix `oha_vhcpt_append_history()` so it cannot fatal on arrays"

**What v3.0.0 did:** Left unsafe json_decode ❌

**What v3.0.1 does:** Safe parsing from your handover doc ✅

---

## 🎯 Bottom Line

**v3.0.0 was conceptually right but had critical implementation bugs.**

**v3.0.1 fixes all three bugs. This is now production-ready.**

**Installation takes 2 minutes. No webhook URL changes needed.**

---

**Changes:** 3 critical fixes  
**Files changed:** 2 files  
**Lines changed:** ~100 lines  
**Testing needed:** 5 minutes  
**Confidence:** Very high - specific bugs identified and fixed
