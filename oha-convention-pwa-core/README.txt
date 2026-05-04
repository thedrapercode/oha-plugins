=== OHA Convention PWA Core ===
Contributors: Ohio Haunters Association Technical Team
Version: 3.5.1
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.0
License: GPLv2 or later

Progressive Web App shell and convention tools for OHA Convention 2026.
Actor Passport and Badge Trading require the separate OHA Passport Hub plugin.

== Description ==

Full PWA capability scoped to convention pages (/convention/* and related slugs).

Features:
* Installable as a mobile home-screen app (service worker + manifest)
* Offline support via convention-scoped service worker
* Convention schedule (oha_schedule CPT, day/track filters, AJAX load)
* Session favorites (per logged-in user, stored in user meta)
* CSV schedule export
* OneSignal push notification subscribe/unsubscribe endpoints
* Google Analytics 4 conditional integration
* Volunteer password-protected area (content stored in Settings)
* FAQ chatbot (pipe-delimited Q&A stored in Settings)
* GoDaddy CDN bypass headers (no-cache for SW and manifest)
* Settings admin panel (Notifications, Analytics, Volunteer, FAQ, General tabs)
* Delegates Actor Passport tabs to OHA Passport Hub when active

== Shortcodes ==

[oha_convention_schedule]
  - Attributes: view="full", show_filters="true", show_export="true"
  - Shows 2 tabs always: Schedule, My Classes
  - Shows 3 more tabs when Passport Hub is active: My Passport, QR Scanner, My Stamps

[oha_my_schedule]
  - Shows the logged-in user's favorited sessions

== Schedule Post Type ==

Custom post type: oha_schedule
Required custom fields per post:
  session_date     (YYYY-MM-DD)
  start_time       (HH:MM)
  end_time         (HH:MM)
  track            (business|makeup|acting|tech|safety|networking)
  instructor       (string)
  instructor_photo (attachment URL, optional)
  room             (string)
  capacity         (integer, optional)
  level            (beginner|intermediate|advanced, optional)
  materials        (string, optional)

Convention dates: May 9-10, 2026
Venue: Ohio State Reformatory, 100 Reformatory Rd, Mansfield, OH 44905

== Admin Settings ==

Settings > PWA Settings
  Notifications tab  : OneSignal App ID, API Key, enable toggle
  Analytics tab      : GA4 Measurement ID (G-XXXXXXXXXX), enable toggle
  Volunteer tab      : Shared password + rich-text content for Info tab
  FAQ tab            : Pipe-delimited Q|A entries, enable toggle
  General tab        : Convention start/end datetime (used for "What's Happening Now")

== Installation ==

1. Upload oha-convention-pwa-core/ to /wp-content/plugins/
2. Activate plugin
3. Go to Settings > PWA Settings and configure
4. Add sample schedule via Schedule Items > Add New
5. Use [oha_convention_schedule] on your /convention page
6. Add .htaccess rules from htaccess-rules.txt if on GoDaddy hosting

== Plugin Split (v3.5.0) ==

Actor Passport, QR Badge Trading, and event stamps were extracted into the
separate OHA Passport Hub plugin. This plugin no longer contains:
  - class-pwa-actor-passport.php
  - class-pwa-qr-manager.php
  - class-pwa-event-stamps.php

When Passport Hub is active (oha_passport_hub_active() returns true), the
convention schedule shows the full 5-tab view including Passport, Scanner, Stamps.
Without Passport Hub, only Schedule and My Classes tabs are shown.

Convention dates sync automatically from Passport Hub's event registry
(slug: oha-convention-2026) when Passport Hub is active. Falls back to
Settings > General dates if Passport Hub is not installed.

== Known Gaps / Planned Work ==

1. Push notification SENDING is a placeholder.
   send_to_endpoint() in class-pwa-notifications.php returns true without
   actually delivering anything. Real delivery requires either:
   a. Wiring up the OneSignal REST API from PHP (recommended), or
   b. Using OneSignal's own dashboard to send campaigns.

2. "What's Happening Now" feature - convention_start/end settings are stored
   but the schedule UI does not yet highlight current/upcoming sessions.

3. Schedule admin UX - entering sessions requires manually filling WP custom
   fields. A proper meta box UI would improve admin data entry.

== Companion Plugin ==

OHA Passport Hub (oha-passport-hub) - Actor Passport, QR badge trading, stamps.
Install alongside this plugin for the full 5-tab convention experience.

== .htaccess ==

See htaccess-rules.txt for GoDaddy CDN headers. Add to WordPress root .htaccess.

== Changelog ==

= 3.5.1 =
* Hardening: schedule date localization now reads from Passport Hub event registry
  (oha-convention-2026 slug) when active, keeping both plugins in sync.
* Added class_exists('OHA_Passport_Events') guard alongside function_exists check.

= 3.5.0 =
* Actor Passport and QR Badge Trading extracted to OHA Passport Hub plugin.
* Convention PWA delegates passport shortcode tabs to Passport Hub when active.
* Removed: class-pwa-actor-passport.php, class-pwa-qr-manager.php,
  class-pwa-event-stamps.php.

= 3.4.1 =
* Fixed duplicate entry file, missing constants, missing class instantiation,
  broken AJAX registrations, table schema mismatches, JS localization.

= 3.4.0 =
* Actor Passport and Badge Trading system added (later split out in 3.5.0).
* Schedule class added (class-pwa-schedule.php).

= 3.2.4 =
* Phase 3A: Push notifications, GA4 analytics, vendor favorites, FAQ, volunteer area.

= 2.0.0 =
* Phase 1: Core PWA - service worker, manifest, offline support, push subscriptions,
  cache manager, GoDaddy CDN bypass.
