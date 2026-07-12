# Vendor Hub Cleanup Trash Log
**Cleanup date**: 2026-06-16
**Plugin version at cleanup**: 3.12.4

All files below were confirmed unreferenced (no require_once, no enqueue) before being moved here.
If you discover a missing feature after 2027 applications open, check here first.

---

## root-docs/ — Stale documentation (10 files)

These were session notes and upgrade guides from v1.x–v3.0 development, all superseded by the handover file.

- `CHANGELOG.md` — manual changelog, replaced by devlog.json
- `COMPATIBILITY-GUIDE.md` — guide for migrating from standalone to Board Hub webhook
- `CRITICAL-FIXES-v1.6.1.md` — notes on fixes from v1.6.1 (pre-2026 season)
- `CRITICAL-FIXES-v3.0.1.md` — notes on the v3.0.1 webhook route conflict fix
- `FIX-SUMMARY.md` — summary of no-payment and URL encoding bugs from early 2026
- `README-INSTALL.md` — original install instructions
- `README.md` — original plugin readme
- `UPGRADE-GUIDE.md` — guide for upgrading from v1 to v2
- `UPGRADE-TO-V3.md` — guide for upgrading to v3 (webhook architecture change)
- `WEBHOOK-INTEGRATION-v1.6.0.md` — original webhook integration notes

---

## root-diagnostics/ — Standalone debug scripts (4 files)

These were run directly via URL (not as WordPress plugin files). All have `require_once('./wp-load.php')` at the top. Safe but should not be in production zip.

- `check-stripe.php` — checked Stripe webhook config
- `diagnostic.php` — full system diagnostic
- `diagnostic-simple.php` — lightweight diagnostic
- `stripe-check-simple.php` — verified Stripe session IDs

---

## includes/integrations/ — Unused integration library files (9 files)

Nothing in this folder was ever loaded via require_once in the active plugin. These appear to be from an earlier architecture where the vendor hub had its own Board Hub integration layer, before switching to the filter-based webhook approach.

- `boardhub-admin.off` — disabled copy of the old boardhub admin (active version is in shortcodes/boardhub-admin.php)
- `boardhub-admin.php` — duplicate/older version of shortcodes/boardhub-admin.php; confirmed NOT loaded
- `oha-board-hub.lib.php` — Board Hub class library (OHA_Board_Hub class with DB table creation, asset enqueue, etc.) — never instantiated in vendor hub
- `oha-boardhub-stripe-admin.lib.php` — Stripe admin integration library for Board Hub — never loaded
- `oha-featured-sync.lib.php` — feature sync library — never loaded
- `oha-haunt-discounts.lib.php` — haunt discounts integration — never loaded
- `oha-stripe-minimal.lib.php` — minimal Stripe API wrapper — never loaded (Stripe used via payment links only, no API calls)
- `oha-vendor-2026.lib.off` — disabled 2026-specific vendor library
- `oha-vendor-2026.lib.php` — 2026-specific vendor library — never loaded

---

## includes/utilities/ — Unused utility classes (5 files)

None of these classes are instantiated or loaded anywhere in the active plugin.

- `class-oha-board-notifications.php` — push notification class
- `class-oha-cache.php` — caching utility
- `class-oha-devlog.php` — devlog class (devlog.json is written directly inline, not via this class)
- `class-oha-logger.php` — logging class
- `class-oha-stripe-webhook-log.php` — Stripe webhook logging class

---

## includes/api/ — Unused API handler (1 file)

- `class-oha-webhook-handler.php` — original class-based webhook handler; replaced by the filter-based approach in includes/stripe-webhook.php after v3.0.0

---

## includes/admin/ — Unused admin classes (3 files)

- `class-oha-admin-page.php` — old class-based admin page; replaced by procedural settings-page.php
- `class-oha-members-table.php` — WP_List_Table subclass for member listing; never registered
- `class-oha-members-table.off` — disabled copy of same

---

## includes/ root — Unused class (1 file)

- `class-oha-settings.php` — old class-based settings handler; replaced by procedural settings-page.php

---

## includes/lib/ — Unused library (1 file)

- `fpdf.php` — FPDF PDF generation library; bypassed since v2.4.0 when plugin switched to HTML agreements. The function `oha_vhcpt_generate_fpdf_pdf()` still exists in pdf-generator.php but is never called — `oha_vhcpt_generate_vendor_pdf()` calls `oha_vhcpt_generate_simple_pdf()` directly.

---

## assets/css/ — Never-enqueued stylesheets (2 files)

The main plugin only registers/enqueues `assets/vendor-hub.css` and `assets/final-application.css`. These subdirectory files were never enqueued.

- `assets/css/admin.css` — admin styles (superseded by inline styles in shortcode files)
- `assets/css/frontend.css` — frontend styles (superseded by vendor-hub.css)

---

## assets/js/ — Never-enqueued scripts (2 files)

No JavaScript files are enqueued as separate assets; all JS is output inline by the shortcode PHP files.

- `assets/js/admin.js` — admin JS (superseded by inline JS in boardhub-admin.php)
- `assets/js/frontend.js` — frontend JS (superseded by inline JS in shortcode files)
