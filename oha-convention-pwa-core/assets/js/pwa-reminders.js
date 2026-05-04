/**
 * OHA Convention PWA - Local Notification Reminders (v1.0.0)
 *
 * Schedules browser/device notifications 5 minutes before favorited events.
 * Uses registration.showNotification() via the service worker — local only,
 * no server push required. If OneSignal has already obtained notification
 * permission, local notifications work automatically without any second prompt.
 *
 * Storage: oha_pending_reminders (localStorage)
 * Schema:  { "class-123": { eventId, title, time, room, url, startMs, reminderMs } }
 *
 * iOS note: Web Push / showNotification only work for INSTALLED PWAs on iOS 16.4+.
 * Android: Works in both browser and installed (standalone) mode.
 *
 * FUTURE SERVER CRON: see class-pwa-notifications.php send_scheduled_reminder()
 * and class-pwa-schedule.php rest_promoted_starting_soon() for the server-side
 * hook points that will be activated when WP-Cron support is confirmed stable.
 */

(function () {
    'use strict';

    window.OHAReminders = window.OHAReminders || {};

    // =========================================================
    // CONSTANTS
    // =========================================================

    var REMINDERS_KEY   = 'oha_pending_reminders';
    var REMINDER_OFFSET = 5 * 60 * 1000; // 5 minutes in ms

    // Convention dates — update each year before repackaging.
    // Saturday = May 9, Sunday = May 10, 2026.
    var CONVENTION_DATES = {
        saturday: '2026-05-09',
        sunday:   '2026-05-10'
    };

    var NOTIFICATION_ICON = '/wp-content/plugins/oha-convention-pwa-core/assets/icons/icon-192.png';

    // In-memory map of eventId -> setTimeout handle (so we can cancel on unfavorite).
    var _timers = {};

    // =========================================================
    // PUBLIC API
    // =========================================================

    /**
     * Initialize — called by OHAFavorites.init() after favorites are loaded.
     * Checks support and reschedules any reminders from a previous session.
     */
    OHAReminders.init = function () {
        console.log('[Reminders] Initializing...');

        if (!OHAReminders.isSupported()) {
            console.log('[Reminders] Not supported in this context (check iOS standalone requirement).');
            return;
        }

        OHAReminders.rescheduleAll();
    };

    /**
     * Schedule a 5-minute reminder for one event.
     * Called by OHAFavorites.addFavorite() immediately after saving.
     *
     * @param {Object} eventData  { id, title, time, room, url }
     */
    OHAReminders.scheduleReminder = function (eventData) {
        if (!OHAReminders.isSupported()) {
            OHAReminders.showInstallPrompt();
            return;
        }

        // Notification permission must already be granted (OneSignal manages the prompt).
        if (typeof Notification === 'undefined' || Notification.permission !== 'granted') {
            console.log('[Reminders] Notification permission not granted — reminder not scheduled for:', eventData.id);
            return;
        }

        var startMs = OHAReminders.parseStartMs(eventData.time);
        if (!startMs) {
            console.warn('[Reminders] Could not parse event time — skipping reminder for:', eventData.id, eventData.time);
            return;
        }

        var reminderMs = startMs - REMINDER_OFFSET;
        var nowMs      = Date.now();

        if (reminderMs <= nowMs) {
            console.log('[Reminders] Event already started or too close — skipping:', eventData.id);
            return;
        }

        // Persist so the reminder survives page reload / app reopen.
        var pending = OHAReminders.getPending();
        pending[eventData.id] = {
            eventId:    eventData.id,
            title:      eventData.title || 'Event',
            time:       eventData.time  || '',
            room:       eventData.room  || '',
            url:        eventData.url   || window.location.href,
            startMs:    startMs,
            reminderMs: reminderMs
        };
        OHAReminders.savePending(pending);

        OHAReminders._setTimer(eventData.id, reminderMs - nowMs, pending[eventData.id]);

        console.log('[Reminders] Scheduled reminder for:', eventData.id,
            '— fires in', Math.round((reminderMs - nowMs) / 60000), 'min(s)');
    };

    /**
     * Cancel a pending reminder.
     * Called by OHAFavorites.removeFavorite() immediately after deleting.
     *
     * @param {string} eventId
     */
    OHAReminders.cancelReminder = function (eventId) {
        if (_timers[eventId]) {
            clearTimeout(_timers[eventId]);
            delete _timers[eventId];
            console.log('[Reminders] Cancelled in-memory timer for:', eventId);
        }

        var pending = OHAReminders.getPending();
        if (pending[eventId]) {
            delete pending[eventId];
            OHAReminders.savePending(pending);
            console.log('[Reminders] Removed from storage:', eventId);
        }
    };

    /**
     * Re-arm timers for any reminders stored from previous sessions.
     * Cleans up stale entries automatically.
     */
    OHAReminders.rescheduleAll = function () {
        var pending = OHAReminders.getPending();
        var nowMs   = Date.now();
        var count   = 0;
        var changed = false;

        Object.keys(pending).forEach(function (eventId) {
            var entry = pending[eventId];
            var delay = entry.reminderMs - nowMs;

            if (delay > 0) {
                OHAReminders._setTimer(eventId, delay, entry);
                count++;
            } else {
                // Stale — past the reminder time; clean up silently.
                delete pending[eventId];
                changed = true;
            }
        });

        if (changed) {
            OHAReminders.savePending(pending);
        }

        console.log('[Reminders] Rescheduled', count, 'pending reminder(s).');
    };

    // =========================================================
    // INTERNAL HELPERS
    // =========================================================

    /**
     * Set a setTimeout that triggers the service worker notification.
     * Uses notification tag to prevent duplicates on reschedule overlap.
     *
     * @param {string} eventId
     * @param {number} delayMs   ms from now until the reminder should fire
     * @param {Object} entry     the stored reminder object
     */
    OHAReminders._setTimer = function (eventId, delayMs, entry) {
        // Clear any existing timer for this event before setting a new one.
        if (_timers[eventId]) {
            clearTimeout(_timers[eventId]);
        }

        _timers[eventId] = setTimeout(function () {
            OHAReminders._fireNotification(entry);

            // Remove from persistent storage — it has fired.
            var pending = OHAReminders.getPending();
            delete pending[eventId];
            OHAReminders.savePending(pending);
            delete _timers[eventId];
        }, delayMs);
    };

    /**
     * Show the local notification via the service worker registration.
     * This is NOT a server push — it fires entirely on the device.
     *
     * @param {Object} entry  the stored reminder object
     */
    OHAReminders._fireNotification = function (entry) {
        if (!('serviceWorker' in navigator)) {
            console.warn('[Reminders] No service worker — cannot fire notification.');
            return;
        }

        var room = entry.room ? ' in ' + entry.room : '';
        var body = entry.title + room + ' \u2014 5 minutes';

        navigator.serviceWorker.ready.then(function (registration) {
            registration.showNotification('Starting Soon', {
                // Tag prevents duplicates if rescheduleAll fires before the old
                // timer has been cleared (e.g. very fast page reload).
                tag:               'reminder-' + entry.eventId,
                body:              body,
                icon:              NOTIFICATION_ICON,
                badge:             NOTIFICATION_ICON,
                // Data read by notificationclick handler in the service worker.
                data:              { url: entry.url, type: 'reminder' },
                // Android vibration (silently ignored on iOS).
                vibrate:           [200, 100, 200],
                // false = auto-dismiss after a few seconds (less intrusive at a convention).
                requireInteraction: false,
                actions: [
                    { action: 'view',    title: 'View Event' },
                    { action: 'dismiss', title: 'Dismiss'    }
                ]
            });

            console.log('[Reminders] Notification fired for:', entry.eventId);
        }).catch(function (err) {
            console.error('[Reminders] Could not access service worker registration:', err);
        });
    };

    // =========================================================
    // TIME PARSING
    // =========================================================

    /**
     * Parse a time string like "Saturday 10:00 AM - 11:15 AM"
     * and return the event start time as a Unix timestamp in milliseconds.
     *
     * Matches the format produced by OHA_Schedule_Hub::format_time() in 12h mode.
     *
     * @param  {string}      timeString
     * @return {number|null} ms since epoch, or null on parse failure
     */
    OHAReminders.parseStartMs = function (timeString) {
        if (!timeString) return null;

        // Match: "Saturday 10:00 AM - 11:15 AM" or "Sunday 2:00 PM - 3:00 PM"
        var match = timeString.match(
            /(\w+)\s+(\d+):(\d+)\s+(AM|PM)\s*-\s*(\d+):(\d+)\s+(AM|PM)/i
        );
        if (!match) return null;

        var dayName     = match[1].toLowerCase();
        var startHour   = parseInt(match[2], 10);
        var startMin    = parseInt(match[3], 10);
        var startPeriod = match[4].toUpperCase();

        var dateStr = CONVENTION_DATES[dayName];
        if (!dateStr) {
            console.warn('[Reminders] Unknown day name:', dayName, '— expected saturday or sunday');
            return null;
        }

        // Convert to 24-hour format.
        if (startPeriod === 'PM' && startHour !== 12) {
            startHour += 12;
        } else if (startPeriod === 'AM' && startHour === 12) {
            startHour = 0; // 12:xx AM = midnight
        }

        // Build an ISO 8601 local-time string, e.g. "2026-05-09T10:00:00".
        // new Date() with this format treats it as local time (not UTC).
        var isoStr = dateStr + 'T'
            + String(startHour).padStart(2, '0') + ':'
            + String(startMin).padStart(2, '0') + ':00';

        var d = new Date(isoStr);
        if (isNaN(d.getTime())) {
            console.warn('[Reminders] Date parse failed for:', isoStr);
            return null;
        }

        return d.getTime();
    };

    // =========================================================
    // PLATFORM DETECTION
    // =========================================================

    /**
     * Determine if local notifications are supported in the current context.
     *
     * Requirements:
     *   - Service Worker API available
     *   - Notification API available
     *   - On iOS: must be running as an installed PWA (standalone)
     *     (iOS only supports Web Push for installed PWAs, not in Safari browser)
     *   - Android/desktop: always supported if the above APIs exist
     *
     * @return {boolean}
     */
    OHAReminders.isSupported = function () {
        if (!('serviceWorker' in navigator) || !('Notification' in window)) {
            return false;
        }

        var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
        if (isIOS) {
            var isStandalone = window.matchMedia('(display-mode: standalone)').matches
                || window.navigator.standalone === true;
            return isStandalone;
        }

        return true; // Android and desktop: always supported
    };

    /**
     * Show a gentle "install the app" toast for iOS users who are in Safari (not installed).
     * Only shown once per session to avoid being annoying.
     */
    OHAReminders.showInstallPrompt = function () {
        var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
        if (!isIOS) return;

        var isStandalone = window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;
        if (isStandalone) return; // Already installed — no prompt needed.

        if (sessionStorage.getItem('oha-reminder-install-shown')) return;
        sessionStorage.setItem('oha-reminder-install-shown', 'true');

        var msg = 'Install the OHA app to get 5-minute reminders for your favorites!';

        // Use the existing OHAFavorites toast helper if available, else fall back to a DIV.
        if (window.OHAFavorites && typeof OHAFavorites.showNotification === 'function') {
            OHAFavorites.showNotification(msg);
        } else {
            var toast = document.createElement('div');
            toast.textContent = msg;
            toast.style.cssText = [
                'position:fixed', 'bottom:20px', 'left:50%',
                'transform:translateX(-50%)',
                'background:#1f2937', 'color:#fff',
                'padding:12px 20px', 'border-radius:8px',
                'z-index:999999', 'font-size:14px',
                'text-align:center', 'max-width:320px',
                'box-shadow:0 4px 12px rgba(0,0,0,0.3)',
                'pointer-events:none'
            ].join(';');
            document.body.appendChild(toast);
            setTimeout(function () {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 5000);
        }
    };

    // =========================================================
    // LOCALSTORAGE HELPERS
    // =========================================================

    OHAReminders.getPending = function () {
        try {
            var raw = localStorage.getItem(REMINDERS_KEY);
            return raw ? JSON.parse(raw) : {};
        } catch (e) {
            console.error('[Reminders] Error reading pending reminders:', e);
            return {};
        }
    };

    OHAReminders.savePending = function (data) {
        try {
            localStorage.setItem(REMINDERS_KEY, JSON.stringify(data));
        } catch (e) {
            console.error('[Reminders] Error saving pending reminders:', e);
        }
    };

    // =========================================================
    // FUTURE SERVER-SIDE CRON NOTE
    // =========================================================
    // When WP-Cron is confirmed stable on iOS + Android, the server-side
    // flow will be:
    //   1. WP-Cron fires every minute via real server cron (cPanel)
    //   2. Calls OHA_Schedule_Hub::rest_promoted_starting_soon() to find
    //      promoted events starting in 5-10 minutes
    //   3. Calls OHA_PWA_Notifications::send_scheduled_reminder($event_data)
    //      which posts to OneSignal REST API for ALL subscribers
    //
    // This client-side module remains active and handles per-user favorites
    // independent of the server-side promoted-event broadcasts.

}());
