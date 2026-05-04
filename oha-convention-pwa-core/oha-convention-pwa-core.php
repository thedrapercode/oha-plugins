<?php
/**
 * Plugin Name: OHA Convention PWA Core
 * Description: PWA for OHA Convention 2026 — Schedule, Push Notifications, Offline Support. Actor Passport and Badge Trading require the OHA Passport Hub plugin.
 * Version: 3.20.1
 * Author: OHA Technical Team
 * Requires PHP: 8.0
 * Text Domain: oha-convention-pwa
 *
 * Changelog:
 * 3.15.0 - NEW: [oha_pwa_settings] shortcode — user settings page. Restore dismissed onboarding
 *               and happenings sections; clear saved schedule/vendor data; shows app version,
 *               connection status, and PWA mode. All operations are localStorage-only (no AJAX).
 *         FIX: Schedule Hub offline indicator replaced with small corner icon (top-right) that
 *               no longer covers page content. pointer-events:none so it does not block taps.
 * 3.14.0 - CHANGE: Passport tab removed from bottom nav (now 5 tabs: Schedule/Map/My OHC/Vendors/Info).
 *               Floating scan button (FAB) added above nav bar — links to passport page via
 *               ohaNavConfig.passport_url (default /convention/2026-convention/passport/).
 *               FAB hidden on desktop (min-width:1025px). wp_localize_script added for oha-app-navigation.
 * 3.11.5 - FIX: Perfecty script/style dequeue was using wrong handle ('perfecty-push-notifications')
 *               since v3.7.7. Perfecty's actual registered handle is 'perfecty-push'
 *               (set via $this->plugin_name in class-perfecty-push.php). All dequeue calls
 *               corrected — Perfecty SDK and OHA bell now properly removed on non-convention pages.
 * 3.11.4 - FIX: Bell and notification popup now restricted to /convention/ pages only.
 *               oha-pwa-notifications script was enqueued site-wide; added it to the
 *               restrict_perfecty_to_convention() dequeue block so it is removed on all
 *               non-convention pages at the same time as Perfecty's own scripts.
 * 3.11.3 - FIX: Bell and push notifications now fully suppressed when Perfecty Push plugin is
 *               deactivated. Added class_exists('Perfecty_Push_Public') guard — WP keeps the
 *               perfecty_push option in the DB after deactivation so the previous option-only
 *               check was insufficient. Bell, pwa-notifications.js, and ohaNotifConfig.enabled
 *               are all false when the plugin class is absent.
 * 3.11.2 - FIX: PWA bell (pwa-notifications.js) now respects Perfecty Push widget_enabled
 *               setting. ohaNotifConfig.enabled is false when widget is disabled; the
 *               pwa-notifications.js script is also not enqueued in that case.
 * 3.11.1 - FIX: [oha_favorite_vendors] showed "Vendor"/no logo due to stale localStorage.
 *               favorite_vendors() now queries all paid vendors in PHP and localizes as
 *               window.ohaVendorLookup; renderer always uses fresh server name/logo/website.
 * 3.11.1 - FIX: Heart button invisible — added color:white, visible border, larger font.
 * 3.11.0 - NEW: [oha_convention_vendors] redesigned as full-width 1-column image cards with
 *               gradient overlay (name + website), floating heart button, card shadow.
 *               Booth lookup via oha_booth CPT stored in data-booth. All vendor data
 *               (logo, website, booth) stored in localStorage on favorite.
 * 3.11.0 - NEW: [oha_favorite_vendors] renamed "My Must-See Vendors" — thumbnail, name,
 *               website, booth chip, tap-to-map navigation (?oha_vendor=ID). No CSV export.
 * 3.11.0 - CHANGE: VendorFavorites heart button now uses heart emoji (&#129293;/&#10084;)
 *               instead of star text. Stores logo + website + booth in localStorage.
 * 3.10.0 - NEW: Vendors tab (icon:shopping bags) restored to bottom nav. Nav is now 6 tabs:
 *               Schedule / Map / My OHC / Vendors / Passport / Info. Tab CSS reduced to fit
 *               (icons 20px/22px active, labels 10px, padding 6px 2px, container padding 4px).
 * 3.10.0 - NEW: [oha_convention_vendors] count text changed to
 *               "Currently X Vendors Announced, more coming soon!".
 * 3.10.0 - NEW: Vendor cards have data-vendor-id + data-name attributes — enables the
 *               pwa-phase3a-features.js VendorFavorites system to auto-attach heart buttons.
 * 3.10.0 - NEW: Vendor card optional fields (website/products/social) toggled via
 *               oha_pwa_vendor_card_fields WP option. Admin UI in PWA Settings > General.
 * 3.9.0 - NEW: [oha_convention_passport] shortcode — pre-event Haunt Passport page.
 *              Hero card with countdown, pre-event checklist (profile + demo stamp),
 *              embedded [oha_actor_passport] and [oha_my_stamps], demo stamp claim
 *              button (AJAX -> oha_award_demo_stamp in Passport Hub), "How it works"
 *              explainer. Requires OHA Passport Hub v1.3.0+.
 * 3.9.0 - CHANGE: Bottom nav Vendors tab replaced with Passport tab (id:'passport',
 *              icon:passport, url:/convention/2026-convention/passport/).
 * 3.8.0 - NEW: [oha_pwa_onboarding] now auto-collapses completed steps to a tappable
 *              checkmark summary row. When all steps are done the whole widget collapses
 *              to a green "Setup Complete" banner with a "See all steps" toggle. State
 *              persisted in localStorage (oha_ob_install_done / oha_ob_notif_done) so the
 *              banner shows immediately on every subsequent page load without re-checking.
 * 3.8.0 - NEW: [oha_convention_home] shortcode — welcome/home page content: hero card
 *              with quick-action buttons, info grid (What is this event / First time here),
 *              and a Happenings section. Happenings items editable via PWA Settings >
 *              General > Home Page Happenings (Title|Description per line). Nav hint shown
 *              once per session (sessionStorage), dismissible via "Got it". Happenings
 *              section dismissible per-device via localStorage. Use on the /convention/ page
 *              with [oha_pwa_onboarding] placed above it in the WP editor.
 * 3.7.9 - FIX: Removed '/vendor' from is_convention_page() URI patterns. It was too broad
 *              and matched non-convention URLs like /vendor-hub/, /vendor-applications/, etc.
 *              causing the PWA to load app-navigation.js (which adds oha-app-mode to body)
 *              and all PWA CSS on those pages — breaking third-party vendor plugin styles.
 *              Convention vendor pages are all under /convention/ which already matches.
 * 3.7.8 - SEC: Board dashboard and volunteer area passwords moved server-side.
 *              Previously both passwords were echoed into front-end JavaScript (var PW / ohaVolunteerConfig.password),
 *              visible in view-source. Now: passwords never leave the server.
 *              Board: POST /oha/v1/board/auth verifies with hash_equals(), returns a 4-hour
 *              transient-backed token; GET /oha/v1/board/content requires that token and returns
 *              notes + contacts as JSON (injected into DOM by JS). Volunteer: POST /oha/v1/volunteer/auth
 *              verifies password and returns wp_kses_post HTML content. Both endpoints include
 *              brute-force protection (10 attempts per IP per 15 min via transients, 429 on lock).
 *              Board JS: sessionStorage stores token (not 'granted' flag); loadBoardContent()
 *              re-fetches notes/contacts on restore. Volunteer JS: sessionStorage caches content.
 *              pwa-phase3a-features.js unchanged — VolunteerArea.init() silently no-ops when
 *              ohaVolunteerConfig is not defined (wp_localize_script removed).
 * 3.7.7 - FIX: Perfecty Push scripts, styles, and wp_head output now restricted to
 *              /convention/ pages only. Dequeued at priority 20 (after Perfecty's 10).
 *              wp_head print_head callback removed via $wp_filter iterator on non-convention
 *              pages, stopping the PerfectyPushOptions config output and the unleash_stalled
 *              DB call from running on every page of the site.
 * 3.7.6 - FIX: Perfecty proxy SW (perfecty-push-sw.js) now injects badge and icon defaults
 *              before Perfecty's SDK loads. Perfecty never sends a badge and only sends an
 *              icon if its admin setting is configured — missing both is Chrome's primary
 *              trigger for "Detected Spam" on Android. Monkey-patches showNotification so
 *              badge-72.png and icon-192.png are always present in push notifications.
 * 3.7.5 - NEW: [oha_board_dashboard] shortcode — full password-protected board dashboard
 *              with 5 tabs: Notifications (send push), Live Stats (AJAX from /oha/v1/notifications/stats),
 *              Board Notes (rich text from PWA Settings), Emergency Contacts (tap-to-call cards,
 *              parsed from Name|Role|Phone lines), Quick Links (My OHC, Schedule, Vendors,
 *              WP Admin, Perfecty Push, Clear Cache). Auth: shared password checked client-side
 *              (sessionStorage 'oha_board_auth'). Password + notes + contacts set in new
 *              PWA Settings → Board Hub tab. Version bump forces SW cache bust.
 * 3.7.4 - NEW: [oha_convention_vendors] shortcode — card grid of confirmed vendors
 *              (vendor_application posts with _oha_status = Paid). Logo from attachment
 *              ID (_company_logo_id) or direct URL (_company_logo_url); haunted-house
 *              placeholder if none. Sorted A-Z by company_name. Empty state shows
 *              "Vendor announcements coming soon!". Dark halloween card theme.
 * 3.7.3 - FIX: Service worker push action mismatch — action was 'close' but click handler
 *              checked for 'dismiss', so tapping Dismiss opened the URL instead of closing.
 *         REMOVE: serve_onesignal_worker() dead method (served /OneSignalSDKWorker.js but
 *              was never hooked — pure dead code from the OneSignal era). Removed entirely.
 *         REMOVE: OneSignal settings tab from PWA Settings admin page — replaced with
 *              live Perfecty Push status panel showing plugin active, VAPID, subscriber count.
 *         LOG: pwa-notifications.js — added detailed console logging around PerfectyPushOptions
 *              detection, scope, getRegistration() result, reg.active state, and subscription.
 *         LOG: pwa-register.js — enhanced Perfecty proxy SW logging with reg.active/installing/
 *              waiting states and a 1-second follow-up getRegistration() confirmation check.
 *         DEBUG: shortcode-pwa-debug.php — added PerfectyPushOptions scope display, live
 *              /perfecty-push-sw.js HTTP fetch check, and per-scope subscription endpoint display.
 * 3.7.2 - FIX: [oha_board_notifications] shortcode — replaced dead OneSignal config check
 *              with Perfecty class check; shows live subscriber count; removed dead segment
 *              selector (Perfecty sends to all subscribers).
 *         NEW: POST /oha/v1/notifications/send REST endpoint — queues broadcast via Perfecty
 *              then spawns wp-cron non-blocking so notifications fire within seconds.
 *         FIX: [oha_pwa_debug] shortcode — removed OneSignal section; replaced with
 *              Perfecty plugin status, VAPID config check, subscriber count, and
 *              native Notification.permission + pushManager subscription check.
 *         NEW: [oha_local_notification_test] shortcode — admin-only local notification
 *              tester using navigator.serviceWorker.ready (same path as pwa-reminders.js).
 * 3.7.1 - FIX: notification_subscribe shortcode — self-registers proxy SW on non-convention
 *              pages (e.g. FAQ) so Perfecty subscriptions work site-wide.
 *         FIX: pwa-notifications.js showSettingsGuide() — distinguish SW failure from
 *              permission denial; show "Setup incomplete — Retry" instead of "Blocked".
 *         FIX: pwa_onboarding shortcode — replaced dead OneSignal polling with Perfecty
 *              subscription flow (urlBase64ToUint8Array + pushManager.subscribe + REST).
 *         NEW: [oha_local_notification_test] admin-only shortcode to validate local
 *              notification delivery (same code path as 5-minute schedule reminders).
 * 3.7.0 - ARCH: Switch push provider from OneSignal to Perfecty Push (self-hosted VAPID).
 *              Requires Perfecty Push Notifications plugin to be installed and activated.
 *              Remove PHP OneSignal worker shim — Perfecty handles its own service worker.
 *              Remove OneSignal SDK loading — Perfecty injects PerfectyPushOptions + SDK.
 *              pwa-notifications.js: replace OneSignal subscription with native PushManager
 *              via Perfecty's REST API (/wp-json/perfecty-push/v1/register).
 *              notification_subscribe shortcode: replace OneSignal polling with native
 *              pushManager.getSubscription() checks and Perfecty REST registration.
 *              Admin send page: replaced with link to Perfecty Push admin menu.
 *              class-pwa-notifications.php: removed OneSignal send/stats; kept install
 *              tracking; rest_get_stats() now queries Perfecty's subscribers table.
 * 3.6.4 - FIX: Remove Notification.requestPermission() fallback from triggerOSPrompt().
 *              That fallback could grant browser permission without creating a OneSignal
 *              subscription, leaving the device silently unregistered in OneSignal while
 *              the UI showed the bell as enabled. On failure, show settings guide instead.
 *         FIX: pwa-register.js — broken REST endpoint pwa/track-install → pwa/install.
 * 3.6.3 - FIX: Bump version so service worker cache busts correctly on update.
 *         FIX: pwa-install.js — delay notification onboarding 5 s after appinstalled
 *              so modal doesn't appear while Android is still creating the home-screen icon.
 *         FIX: Notification shortcode — add 15 s timeout + SDK self-load so "Working…"
 *              button can't get permanently stuck when OneSignal is slow to initialise.
 *         FIX: pwa-notifications.js — standalone startup delay increased to 4 s.
 * 3.6.2 - FIX: Add target_channel:"push" + priority:10 + ttl to OneSignal payload.
 * 3.6.1 - FIX: OneSignal send/stats endpoints updated to api.onesignal.com.
 * 3.6.0 - Push notification overhaul.
 *         SECURITY: OneSignal API key moved server-side; removed from client HTML.
 *         NEW: send_via_onesignal() + REST POST /oha/v1/notifications/send.
 *         NEW: REST GET /oha/v1/notifications/stats (subscriber + install counts).
 *         NEW: REST POST /oha/v1/pwa/install (install event tracking).
 *         NEW: Platform-aware notification onboarding state machine in pwa-notifications.js.
 *              Fires after appinstalled event or standalone mode detection.
 *              iOS guide: Settings → OHC → Notifications → Allow Notifications.
 *              Android guide: lock icon → Site settings → Notifications → Allow.
 *         NEW: Notification bell button in .oha-pwa-nav with granted/denied states.
 *         NEW: Stats panel in admin Push Notifications page (installs + subscribers).
 *         FIX: pwa-install.js install-tracking endpoint corrected to /oha/v1/pwa/install.
 *         FIX: 'ohaPWAInstalled' CustomEvent wires install → notification onboarding.
 * 3.5.1 - Hardening pass following plugin split integrity review.
 *         HARDENED: Schedule date localization (conventionStart / conventionEnd) now reads
 *           from the Passport Hub event registry (oha-convention-2026 slug) when Passport
 *           Hub is active, keeping both plugins in sync if convention dates ever change.
 *           Falls back to the oha_pwa_convention_start / _end settings options so the
 *           schedule still works without Passport Hub installed.
 *         Added class_exists('OHA_Passport_Events') guard alongside function_exists check
 *           to prevent any edge-case fatal if plugin load order is non-standard.
 * 3.5.0 - Actor Passport and QR Badge Trading extracted to OHA Passport Hub plugin.
 *         Convention PWA now delegates passport shortcode tabs to Passport Hub when active.
 *         Removed: class-pwa-actor-passport.php, class-pwa-qr-manager.php, class-pwa-event-stamps.php.
 * 3.4.1 - Stability patch: fixed duplicate entry file, missing constants, missing class
 *         instantiation, broken AJAX registrations, table schema mismatches,
 *         JS localization, and QR frame decoding implementation.
 * 3.4.0 - Actor Passport & Badge Trading system added.
 * 3.2.4 - Phase 3A: Push notifications, analytics, vendor favorites, FAQ.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─────────────────────────────────────────────────────────────────────────────
// Constants — all names used by every included class must be defined here
// ─────────────────────────────────────────────────────────────────────────────
define( 'OHA_PWA_VERSION',    '3.20.1' );
define( 'OHA_PWA_DIR',        plugin_dir_path( __FILE__ ) );   // used by stable includes
define( 'OHA_PWA_URL',        plugin_dir_url( __FILE__ ) );    // used by stable includes
define( 'OHA_PWA_SCOPE',      '/convention/' );                // used by service worker & manifest
define( 'OHA_PWA_PLUGIN_DIR', OHA_PWA_DIR );                   // alias — used by 3.4.0 classes
define( 'OHA_PWA_PLUGIN_URL', OHA_PWA_URL );                   // alias — used by 3.4.0 classes

// ─────────────────────────────────────────────────────────────────────────────
// Core includes — v3.2.4 stable (unchanged, always load first)
// ─────────────────────────────────────────────────────────────────────────────
require_once OHA_PWA_DIR . 'includes/class-pwa-manifest.php';
require_once OHA_PWA_DIR . 'includes/class-pwa-service-worker.php';
require_once OHA_PWA_DIR . 'includes/class-pwa-database.php';
require_once OHA_PWA_DIR . 'includes/class-pwa-notifications.php';
require_once OHA_PWA_DIR . 'includes/class-pwa-cache-manager.php';
require_once OHA_PWA_DIR . 'includes/class-pwa-settings.php';
require_once OHA_PWA_DIR . 'includes/class-pwa-notifications-admin.php';
require_once OHA_PWA_DIR . 'includes/shortcode-pwa-debug.php';
require_once OHA_PWA_DIR . 'includes/shortcode-pwa-download.php';

// ─────────────────────────────────────────────────────────────────────────────
// v3.4.0 includes — new classes (loaded AFTER stable so Actor Passport can
// reference stable DB helpers if needed in future)
// ─────────────────────────────────────────────────────────────────────────────
require_once OHA_PWA_DIR . 'includes/class-pwa-schedule.php';



require_once OHA_PWA_DIR . 'includes/class-pwa-shortcodes.php';

// ─────────────────────────────────────────────────────────────────────────────
// Main Plugin Class
// ─────────────────────────────────────────────────────────────────────────────
class OHA_Convention_PWA_Core {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// CRITICAL: Intercept service worker / manifest requests BEFORE WP routing
		add_filter( 'do_parse_request', [ $this, 'maybe_serve_pwa_files' ], 1, 2 );

		// Standard plugin hooks
		add_action( 'init',               [ $this, 'init_pwa' ],          10 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_pwa_scripts' ]    );
		add_action( 'wp_head',            [ $this, 'output_pwa_meta' ]        );

		// GoDaddy CDN bypass — force no-cache on critical PWA files
		add_action( 'send_headers', [ $this, 'force_nocache_headers' ], 999 );

		// Restrict Perfecty Push to convention pages only — runs after Perfecty's priority 10
		add_action( 'wp_enqueue_scripts', [ $this, 'restrict_perfecty_to_convention' ],       20 );
		add_action( 'wp_head',            [ $this, 'suppress_perfecty_head_if_not_convention' ], 1 );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Init — bring up all components
	// ─────────────────────────────────────────────────────────────────────────
	public function init_pwa() {
		// v3.2.4 stable components
		OHA_PWA_Database::register_meta_fields();
		OHA_PWA_Notifications::init();
		OHA_PWA_Cache_Manager::init();

		// v3.4.0 schedule and shortcodes
		OHA_PWA_Schedule::getInstance();
		OHA_PWA_Shortcodes::getInstance();
		// Actor Passport — handled by OHA Passport Hub plugin when active
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Service worker / manifest intercept (v3.2.4 unchanged logic)
	// ─────────────────────────────────────────────────────────────────────────
	public function maybe_serve_pwa_files( $do_parse, $wp ) {
		$request_uri = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ?: '';

		if ( strpos( $request_uri, 'convention-sw.js' ) !== false ) {
			$this->serve_service_worker();
		}
		if ( strpos( $request_uri, 'convention-manifest.json' ) !== false ) {
			$this->serve_manifest();
		}

		// Perfecty Push proxy SW — GoDaddy Nginx returns 403 when the browser tries to
		// fetch service-worker-loader.js.php directly from the plugin directory. We serve
		// an equivalent script through WordPress (always accessible) so the browser can
		// register Perfecty's service worker without hitting the security block.
		if ( strpos( $request_uri, 'perfecty-push-sw.js' ) !== false ) {
			$this->serve_perfecty_proxy_worker();
		}

		return $do_parse;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Script / style enqueue
	// ─────────────────────────────────────────────────────────────────────────
	public function enqueue_pwa_scripts() {
		if ( ! $this->is_convention_page() ) {
			return;
		}

		// ── v3.2.4 core scripts ──────────────────────────────────────────────
		wp_enqueue_script(
			'oha-pwa-register',
			OHA_PWA_URL . 'assets/js/pwa-register.js',
			[],
			OHA_PWA_VERSION,
			true
		);
		wp_localize_script( 'oha-pwa-register', 'ohaPWA', [
			'scope'       => OHA_PWA_SCOPE,
			'apiUrl'      => rest_url( 'oha/v1/' ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'isInstalled' => false,
			'version'     => OHA_PWA_VERSION,
		] );

		// Notification config — always available so pwa-notifications.js can read nonce
		// enabled=true only when Perfecty plugin is active, has a VAPID key, AND widget_enabled is on.
		// class_exists check is required: WP keeps the perfecty_push option in the DB after
		// deactivation, so checking the option alone is not sufficient.
		$_perfecty_opts   = get_option( 'perfecty_push', [] );
		$_perfecty_active = class_exists( 'Perfecty_Push_Public' );
		wp_localize_script( 'oha-pwa-register', 'ohaNotifConfig', [
			'appName' => 'OHC',
			'restUrl' => rest_url( 'oha/v1/' ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'enabled' => $_perfecty_active && ! empty( $_perfecty_opts['vapid_public_key'] ) && ! empty( $_perfecty_opts['widget_enabled'] ),
		] );

		wp_enqueue_script( 'oha-pwa-install',   OHA_PWA_URL . 'assets/js/pwa-install.js',   [ 'oha-pwa-register' ],         OHA_PWA_VERSION, true );
		wp_enqueue_script( 'oha-pwa-utils',     OHA_PWA_URL . 'assets/js/pwa-utils.js',     [ 'jquery', 'oha-pwa-register' ], OHA_PWA_VERSION, true );
		wp_enqueue_script( 'oha-pwa-reminders', OHA_PWA_URL . 'assets/js/pwa-reminders.js', [],                              OHA_PWA_VERSION, true );
		wp_enqueue_script( 'oha-pwa-favorites', OHA_PWA_URL . 'assets/js/pwa-favorites.js', [ 'jquery', 'oha-pwa-reminders' ], OHA_PWA_VERSION, true );
		wp_enqueue_script( 'oha-app-navigation',OHA_PWA_URL . 'assets/js/app-navigation.js',[ 'jquery' ],                    OHA_PWA_VERSION, true );
		wp_localize_script( 'oha-app-navigation', 'ohaNavConfig', [
			'passport_url'   => get_option( 'oha_pwa_passport_url', '/convention/2026-convention/passport/' ),
			'scanner_url'    => get_option( 'oha_pwa_scanner_url',  '/convention/2026-convention/passport/' ),
			'show_scan_fab'  => get_option( 'oha_pwa_show_scan_fab', '1' ),
		] );

		wp_enqueue_style( 'oha-pwa-styles',     OHA_PWA_URL . 'assets/css/pwa-styles.css',     [], OHA_PWA_VERSION );
		wp_enqueue_style( 'oha-app-navigation', OHA_PWA_URL . 'assets/css/app-navigation.css', [], OHA_PWA_VERSION );
		wp_enqueue_style( 'oha-pwa-phase3a',    OHA_PWA_URL . 'assets/css/pwa-phase3a.css',    [], OHA_PWA_VERSION );
		if ( get_option( 'oha_pwa_show_scan_fab', '1' ) === '0' ) {
			wp_add_inline_style( 'oha-app-navigation', '#oha-fab-scan { display: none !important; }' );
		}

		wp_enqueue_script( 'oha-fuse',                 OHA_PWA_URL . 'assets/js/fuse.min.js',             [],                       '7.0.0',          true );
		wp_enqueue_script( 'oha-pwa-phase3a-features', OHA_PWA_URL . 'assets/js/pwa-phase3a-features.js', [ 'jquery', 'oha-fuse' ], OHA_PWA_VERSION, true );

		// ── v3.4.0 schedule ──────────────────────────────────────────────────
		wp_enqueue_script(
			'oha-pwa-schedule',
			OHA_PWA_URL . 'assets/js/pwa-schedule.js',
			[ 'jquery' ],
			OHA_PWA_VERSION,
			true
		);
		// ── Schedule date config — prefer Passport Hub registry over settings options ──
		// If Passport Hub is active and has the convention event registered, use those
		// dates so the schedule display stays in sync with the badge-trading window.
		// Falls back to the dates stored in OHA Convention PWA settings if not available.
		$schedule_start = get_option( 'oha_pwa_convention_start', '2026-05-09' );
		$schedule_end   = get_option( 'oha_pwa_convention_end',   '2026-05-10' );

		if ( function_exists( 'oha_passport_hub_active' ) && class_exists( 'OHA_Passport_Events' ) ) {
			// Passport Hub is active — pull the convention 2026 event dates from its registry.
			// This keeps schedule-side helpers in sync if the event record is ever updated.
			$ph_event = OHA_Passport_Events::get_instance()->get_event_by_slug( 'oha-convention-2026' );
			if ( $ph_event && ! empty( $ph_event['start_date'] ) && ! empty( $ph_event['end_date'] ) ) {
				$schedule_start = $ph_event['start_date'];
				$schedule_end   = $ph_event['end_date'];
			}
		}

		wp_localize_script( 'oha-pwa-schedule', 'ohaScheduleConfig', [
			'ajaxurl'         => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'oha_schedule_nonce' ),
			'conventionStart' => $schedule_start,
			'conventionEnd'   => $schedule_end,
		] );

		wp_enqueue_style( 'oha-pwa-schedule', OHA_PWA_URL . 'assets/css/pwa-schedule.css', [], OHA_PWA_VERSION );

		// ── Actor Passport (v3.5.0+) ────────────────────────────────────────
		// Assets are now owned by OHA Passport Hub plugin.
		// When Passport Hub is active it registers oha-jsqr and oha-passport-hub
		// globally, so the convention schedule shortcode can use the passport tabs
		// without duplicating any code or assets here.
		// Nothing to enqueue — Passport Hub handles it on passport-tagged pages.

		// ── Perfecty Push notifications (conditional) ────────────────────────
		// Reuses $_perfecty_opts already fetched above for ohaNotifConfig.
		// Only load when widget is enabled in Perfecty settings (same gate as ohaNotifConfig.enabled).
		if ( $_perfecty_active && ! empty( $_perfecty_opts['vapid_public_key'] ) && ! empty( $_perfecty_opts['widget_enabled'] ) ) {
			wp_enqueue_script( 'oha-pwa-notifications', OHA_PWA_URL . 'assets/js/pwa-notifications.js', [], OHA_PWA_VERSION, true );
		}

		// ── Google Analytics 4 (conditional) ─────────────────────────────────
		$analytics_enabled = get_option( 'oha_pwa_analytics_enabled', false );
		$ga4_id            = get_option( 'oha_pwa_ga4_id', '' );

		if ( $analytics_enabled && ! empty( $ga4_id ) ) {
			wp_enqueue_script( 'oha-pwa-analytics', OHA_PWA_URL . 'assets/js/pwa-analytics.js', [], OHA_PWA_VERSION, true );
			wp_localize_script( 'oha-pwa-analytics', 'ohaAnalyticsConfig', [
				'ga4Id'   => $ga4_id,
				'enabled' => true,
			] );
		}
	}

	// ─────────────────────────────────────────────────────────────────────────
	// PWA meta tags in <head>
	// ─────────────────────────────────────────────────────────────────────────
	public function output_pwa_meta() {
		if ( ! $this->is_convention_page() ) {
			return;
		}

		$manifest_url = home_url( '/convention-manifest.json' );
		$icon_url     = OHA_PWA_URL . 'assets/icons/icon-192.png';

		echo "\n<!-- OHA Convention PWA v" . OHA_PWA_VERSION . " -->\n";
		echo '<link rel="manifest" href="' . esc_url( $manifest_url ) . '">' . "\n";
		echo '<meta name="theme-color" content="#1a1a1a">' . "\n";
		echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
		echo '<meta name="apple-mobile-web-app-title" content="OHA Convention 2026">' . "\n";
		echo '<link rel="apple-touch-icon" href="' . esc_url( $icon_url ) . '">' . "\n";
		echo '<link rel="icon" type="image/png" sizes="192x192" href="' . esc_url( $icon_url ) . '">' . "\n";
		echo "<!-- End OHA Convention PWA -->\n\n";
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Serve service worker (v3.2.4 logic, unchanged)
	// ─────────────────────────────────────────────────────────────────────────
	public function serve_service_worker() {
		$path = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
		if ( $path !== '/convention-sw.js' ) {
			return;
		}

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'Content-Type: application/javascript; charset=utf-8', true );
		header( 'Service-Worker-Allowed: ' . OHA_PWA_SCOPE, true );
		header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private', true );
		header( 'Pragma: no-cache', true );
		header( 'Expires: Thu, 01 Jan 1970 00:00:00 GMT', true );
		header( 'CDN-Cache-Control: no-cache', true );
		header( 'Cloudflare-CDN-Cache-Control: no-cache', true );
		header( 'Surrogate-Control: no-cache', true );
		header( 'X-Accel-Expires: 0', true );
		header( 'X-SW-Generated: ' . time(), true );

		echo OHA_PWA_Service_Worker::generate();
		exit;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Serve manifest (v3.2.4 logic, unchanged)
	// ─────────────────────────────────────────────────────────────────────────
	public function serve_manifest() {
		$path = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
		if ( $path !== '/convention-manifest.json' ) {
			return;
		}

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'Content-Type: application/json; charset=utf-8', true );
		header( 'Service-Worker-Allowed: ' . OHA_PWA_SCOPE, true );
		header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0', true );
		header( 'Pragma: no-cache', true );
		header( 'Expires: Thu, 01 Jan 1970 00:00:00 GMT', true );
		header( 'CDN-Cache-Control: no-cache', true );
		header( 'X-PWA-Generated: ' . time(), true );

		echo OHA_PWA_Manifest::generate();
		exit;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Perfecty Push proxy service worker
	//
	// GoDaddy Managed WP's Nginx security rules return 403 when the browser
	// fetches service-worker-loader.js.php directly from the plugin directory.
	// We serve an equivalent script at /perfecty-push-sw.js through WordPress's
	// main index (always accessible), which importScripts() Perfecty's real SW
	// bundle using its absolute URL so the browser can register the SW normally.
	// ─────────────────────────────────────────────────────────────────────────
	public function serve_perfecty_proxy_worker() {
		$path = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
		if ( $path !== '/perfecty-push-sw.js' ) {
			return;
		}

		// Resolve the absolute URL to Perfecty's bundled SW min file.
		// Check multiple known locations in case Perfecty reorganised its files across versions.
		$sdk_candidates = [
			'perfecty-push-notifications/public/js/perfecty-push-sdk/dist/perfecty-push-sw.min.js',
			'perfecty-push-notifications/public/js/perfecty-push-sw.min.js',
			'perfecty-push-notifications/public/js/perfecty-push-sw.js',
			'perfecty-push-notifications/assets/js/perfecty-push-sw.min.js',
		];
		$sdk_relative = null;
		foreach ( $sdk_candidates as $candidate ) {
			if ( file_exists( WP_PLUGIN_DIR . '/' . $candidate ) ) {
				$sdk_relative = $candidate;
				break;
			}
		}

		if ( ! $sdk_relative ) {
			// Perfecty not installed or file not found — serve a valid empty SW.
			header( 'Content-Type: application/javascript; charset=utf-8' );
			header( 'Cache-Control: no-cache' );
			echo '/* Perfecty Push SW not found — push notifications unavailable */';
			exit;
		}

		$sdk_url = plugins_url( $sdk_relative );

		while ( ob_get_level() ) { ob_end_clean(); }

		header( 'Content-Type: application/javascript; charset=utf-8', true );
		header( 'Service-Worker-Allowed: /', true );
		header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0', true );
		header( 'Pragma: no-cache', true );
		header( 'Expires: 0', true );
		header( 'CDN-Cache-Control: no-cache', true );
		header( 'Surrogate-Control: no-cache', true );
		header( 'X-Accel-Expires: 0', true );
		header( 'X-Robots-Tag: none', true );

		// Inject badge + icon defaults before Perfecty's SW runs.
		// Perfecty never includes a badge (Android status-bar icon) and only
		// includes an icon if its admin setting is configured. Missing icon/badge
		// is Chrome's primary trigger for "Detected Spam" on Android.
		// We monkey-patch showNotification so every Perfecty notification gets
		// both values filled in, without touching Perfecty's own code.
		$badge_url = OHA_PWA_URL . 'assets/icons/badge-72.png';
		$icon_url  = OHA_PWA_URL . 'assets/icons/icon-192.png';
		echo "(function(){\n";
		echo "  var _show = self.registration.showNotification.bind(self.registration);\n";
		echo "  self.registration.showNotification = function(title, opts){\n";
		echo "    opts = opts || {};\n";
		echo "    if (!opts.badge) opts.badge = " . wp_json_encode( $badge_url ) . ";\n";
		echo "    if (!opts.icon || opts.icon === '') opts.icon = " . wp_json_encode( $icon_url ) . ";\n";
		echo "    return _show(title, opts);\n";
		echo "  };\n";
		echo "})();\n";
		echo "importScripts(" . wp_json_encode( $sdk_url ) . ");\n";
		exit;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Perfecty Push — restrict to convention pages only
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Returns true if the current HTTP request is within the convention scope.
	 * Uses REQUEST_URI directly so it works before WP query is fully set up.
	 */
	private function is_convention_request() {
		$uri = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ?: '';
		return strpos( $uri, '/convention' ) !== false;
	}

	/**
	 * Dequeue Perfecty Push script and style on non-convention pages.
	 * Runs at priority 20, after Perfecty enqueues at priority 10.
	 * The OHA PWA's pwa-notifications.js handles subscriptions independently
	 * via native pushManager + Perfecty REST, so the Perfecty SDK JS is not
	 * needed on non-convention pages.
	 */
	public function restrict_perfecty_to_convention() {
		if ( $this->is_convention_request() ) {
			return;
		}
		wp_dequeue_script( 'perfecty-push' );           // Perfecty's actual handle (plugin_name = 'perfecty-push')
		wp_dequeue_style( 'perfecty-push' );            // same handle for style
		wp_dequeue_script( 'oha-pwa-notifications' );   // our bell + popup — /convention/ only
	}

	/**
	 * Remove Perfecty's wp_head callback (PerfectyPushOptions config output +
	 * unleash_stalled DB call) on non-convention pages.
	 * Runs at priority 1, before Perfecty's default priority 10, so the
	 * callback is removed before it fires.
	 */
	public function suppress_perfecty_head_if_not_convention() {
		if ( $this->is_convention_request() ) {
			return;
		}
		global $wp_filter;
		if ( ! isset( $wp_filter['wp_head'] ) ) {
			return;
		}
		foreach ( $wp_filter['wp_head']->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $key => $callback ) {
				if ( is_array( $callback['function'] )
					&& is_object( $callback['function'][0] )
					&& $callback['function'][0] instanceof Perfecty_Push_Public
					&& $callback['function'][1] === 'print_head' ) {
					unset( $wp_filter['wp_head']->callbacks[ $priority ][ $key ] );
				}
			}
		}
	}

	// ─────────────────────────────────────────────────────────────────────────
	// GoDaddy CDN bypass headers
	// ─────────────────────────────────────────────────────────────────────────
	public function force_nocache_headers() {
		$request_uri = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ?: '';

		if ( strpos( $request_uri, 'convention-sw.js' ) !== false ||
		     strpos( $request_uri, 'perfecty-push-sw.js' ) !== false ) {
			header( 'Content-Type: application/javascript; charset=utf-8' );
			header( 'Service-Worker-Allowed: /' );
			header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0', true );
			header( 'Pragma: no-cache', true );
			header( 'Expires: 0', true );
			header( 'CDN-Cache-Control: no-cache', true );
			header( 'Surrogate-Control: no-cache', true );
			header( 'X-Accel-Expires: 0', true );
		}

		if ( strpos( $request_uri, 'convention-manifest.json' ) !== false ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Cache-Control: no-cache, no-store, must-revalidate', true );
			header( 'Pragma: no-cache', true );
			header( 'Expires: 0', true );
		}

		if ( strpos( $request_uri, 'pwa-register.js' )   !== false ||
			 strpos( $request_uri, 'pwa-install.js' )    !== false ||
			 strpos( $request_uri, 'pwa-utils.js' )      !== false ) {
			header( 'Cache-Control: public, max-age=300, must-revalidate', true );
		}
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Static Perfecty SW file management
	//
	// GoDaddy's Nginx serves .js extensions as static files before PHP runs,
	// so the do_parse_request intercept never fires for /perfecty-push-sw.js.
	// We write a physical file at ABSPATH on activation (and when Perfecty is
	// activated) so Nginx always finds a real file to serve.
	// ─────────────────────────────────────────────────────────────────────────
	public static function write_perfecty_static_sw() {
		$static_sw_path = ABSPATH . 'perfecty-push-sw.js';

		$sdk_candidates = [
			'perfecty-push-notifications/public/js/perfecty-push-sdk/dist/perfecty-push-sw.min.js',
			'perfecty-push-notifications/public/js/perfecty-push-sw.min.js',
			'perfecty-push-notifications/public/js/perfecty-push-sw.js',
			'perfecty-push-notifications/assets/js/perfecty-push-sw.min.js',
		];
		$sdk_relative = null;
		foreach ( $sdk_candidates as $candidate ) {
			if ( file_exists( WP_PLUGIN_DIR . '/' . $candidate ) ) {
				$sdk_relative = $candidate;
				break;
			}
		}

		$badge_url = plugins_url( 'assets/icons/badge-72.png', __FILE__ );
		$icon_url  = plugins_url( 'assets/icons/icon-192.png',  __FILE__ );

		$content  = "(function () {\n";
		$content .= "    var _show = self.registration.showNotification.bind(self.registration);\n";
		$content .= "    self.registration.showNotification = function (title, opts) {\n";
		$content .= "        opts = opts || {};\n";
		$content .= "        if (!opts.badge) opts.badge = " . wp_json_encode( $badge_url ) . ";\n";
		$content .= "        if (!opts.icon || opts.icon === '') opts.icon = " . wp_json_encode( $icon_url ) . ";\n";
		$content .= "        return _show(title, opts);\n";
		$content .= "    };\n";
		$content .= "})();\n";

		if ( $sdk_relative ) {
			$content .= "importScripts(" . wp_json_encode( plugins_url( $sdk_relative ) ) . ");\n";
		} else {
			$content .= "/* Perfecty SDK not found — activate Perfecty Push plugin, then deactivate and reactivate OHA Convention PWA Core */\n";
		}

		file_put_contents( $static_sw_path, $content );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Convention page detection
	// ─────────────────────────────────────────────────────────────────────────
	private function is_convention_page() {
		$request_uri = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ?: '';
		$patterns    = [ '/convention', '/schedule', '/session', '/speaker', '/room', '/actor-passport', '/my-ohc' ];

		foreach ( $patterns as $pattern ) {
			if ( strpos( $request_uri, $pattern ) !== false ) {
				return true;
			}
		}

		if ( is_singular( [ 'session', 'speaker', 'vendor', 'room', 'announcement', 'oha_schedule' ] ) ) {
			return true;
		}

		global $post;
		if ( $post && (
			strpos( $post->post_name, 'convention' ) !== false ||
			strpos( $post->post_name, 'schedule' )   !== false ||
			strpos( $post->post_name, 'passport' )   !== false
		) ) {
			return true;
		}

		return false;
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// Bootstrap
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function() {
	OHA_Convention_PWA_Core::get_instance();
	// Run table upgrade when DB schema version is behind (safe to run on every update).
	if ( get_option( 'oha_pwa_db_version' ) !== '2.0' ) {
		OHA_PWA_Database::create_tables();
	}
} );

// ─────────────────────────────────────────────────────────────────────────────
// Activation — create ALL database tables (fixed schema)
// ─────────────────────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, function() {
	global $wpdb;

	// v3.2.4 tables (PWA installs, push subscriptions)
	OHA_PWA_Database::create_tables();
	OHA_PWA_Database::add_indexes();

	// Write static perfecty-push-sw.js to web root so GoDaddy Nginx can serve it
	// directly without needing to reach PHP (Nginx intercepts .js before WordPress).
	OHA_Convention_PWA_Core::write_perfecty_static_sw();
} );

// Regenerate the static Perfecty SW file when Perfecty itself is activated,
// in case Perfecty was not installed when this plugin was first activated.
add_action( 'activated_plugin', function( $plugin ) {
	if ( strpos( $plugin, 'perfecty-push-notifications' ) !== false ) {
		OHA_Convention_PWA_Core::write_perfecty_static_sw();
	}
} );

// ─────────────────────────────────────────────────────────────────────────────
// Deactivation
// ─────────────────────────────────────────────────────────────────────────────
register_deactivation_hook( __FILE__, function() {
	delete_option( 'oha_pwa_cache_version' );
	flush_rewrite_rules();
} );
