/**
 * OHA Convention PWA — Push Notification Onboarding (v3.7.0 — Perfecty branch)
 *
 * Platform-aware state machine that:
 *   1. Waits until the PWA is installed (appinstalled event or standalone mode)
 *   2. Checks permission state and platform
 *   3. Shows the right UI: pre-permission modal → OS prompt → or Settings guide
 *
 * Trigger: listens for CustomEvent 'ohaPWAInstalled' (fired by pwa-install.js)
 * Config:  window.ohaNotifConfig  { appName, restUrl, nonce, enabled }
 *          window.PerfectyPushOptions { vapidPublicKey, path, serviceWorkerScope, siteUrl, token }
 *
 * Push provider: Perfecty Push (self-hosted VAPID — no CDN dependency at runtime)
 *   Subscribe:   POST /wp-json/perfecty-push/v1/register
 *   Deactivate:  PUT  /wp-json/perfecty-push/v1/user/active  { user_id, is_active: false }
 */

(function () {
    'use strict';

    // ── Config ──────────────────────────────────────────────────────────────
    var cfg    = window.ohaNotifConfig || {};
    var APP_NAME      = cfg.appName || 'OHC';
    var SITE_HOST     = window.location.hostname;
    var NOTIF_ENABLED = cfg.enabled || false;

    // localStorage keys
    var LS_STATE    = 'oha_notif_state';     // 'granted' | 'snoozed_until_<ts>'
    var LS_PROMPTED = 'oha_notif_prompted';  // timestamp of last pre-prompt show

    // ── Platform detection ──────────────────────────────────────────────────
    function isIOS() {
        return /ipad|iphone|ipod/i.test(navigator.userAgent) && !window.MSStream;
    }

    function isStandalone() {
        return (
            window.navigator.standalone === true ||
            window.matchMedia('(display-mode: standalone)').matches
        );
    }

    // ── LocalStorage helpers ────────────────────────────────────────────────
    function getState() {
        return localStorage.getItem(LS_STATE) || '';
    }

    function setState(val) {
        localStorage.setItem(LS_STATE, val);
    }

    function isSnoozed() {
        var s = getState();
        if (!s.startsWith('snoozed_until_')) { return false; }
        var until = parseInt(s.replace('snoozed_until_', ''), 10);
        return Date.now() < until;
    }

    function snooze() {
        setState('snoozed_until_' + (Date.now() + 24 * 60 * 60 * 1000));
    }

    function markGranted() {
        setState('granted');
        updateBellState('granted');
    }

    function lastPromptedRecently() {
        var t = localStorage.getItem(LS_PROMPTED);
        if (!t) { return false; }
        return Date.now() - parseInt(t, 10) < 24 * 60 * 60 * 1000;
    }

    function markPrompted() {
        localStorage.setItem(LS_PROMPTED, Date.now().toString());
    }

    // ── Perfecty Push helpers ───────────────────────────────────────────────

    /**
     * Convert VAPID base64url key to Uint8Array for pushManager.subscribe()
     */
    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = window.atob(base64);
        var arr     = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; ++i) {
            arr[i] = rawData.charCodeAt(i);
        }
        return arr;
    }

    /**
     * Check whether this browser is already subscribed via Perfecty.
     * Resolves to the PushSubscription object or null.
     */
    function perfectyGetSubscription() {
        var popts = window.PerfectyPushOptions || {};
        var scope = popts.serviceWorkerScope || '/';
        console.log('[OHA Notif] perfectyGetSubscription() — scope:', scope);
        return navigator.serviceWorker.getRegistration(scope).then(function (reg) {
            console.log('[OHA Notif] getRegistration result:', reg
                ? 'found (active=' + !!(reg && reg.active) + ')'
                : 'NOT FOUND — proxy SW not registered at this scope');
            if (!reg || !reg.pushManager) { return null; }
            return reg.pushManager.getSubscription().then(function (sub) {
                console.log('[OHA Notif] getSubscription result:', sub
                    ? 'active (endpoint=...' + sub.endpoint.slice(-20) + ')'
                    : 'NULL — no push subscription exists');
                return sub;
            });
        });
    }

    /**
     * Register this browser subscription with the Perfecty REST API.
     * Resolves to the JSON body or rejects on HTTP error.
     *
     * PerfectyPushOptions.serverUrl is the full namespace base, e.g.
     * "https://site.com/wp-json/perfecty-push" — we append /v1/push/users.
     */
    function perfectyRegister(subscription) {
        var popts    = window.PerfectyPushOptions || {};
        var base     = (popts.serverUrl || '/wp-json/perfecty-push').replace(/\/$/, '');
        var subJson  = subscription.toJSON();

        return fetch(base + '/v1/push/users', {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce':   popts.token || '',
            },
            body: JSON.stringify({
                user:       { endpoint: subJson.endpoint, keys: subJson.keys },
                first_time: true,
            }),
        }).then(function (r) {
            if (!r.ok) { throw new Error('Perfecty register HTTP ' + r.status); }
            return r.json();
        });
    }

    /**
     * Subscribe via Perfecty's service worker (identified by serviceWorkerScope).
     *
     * IMPORTANT: we must NOT use navigator.serviceWorker.ready here — on
     * convention pages that would return the OHA convention SW (scope /convention/),
     * which has no push event handler. We specifically need Perfecty's SW so that
     * push messages are delivered to Perfecty's handler.
     *
     * We poll for up to 10 s to give Perfecty's SDK time to register its SW.
     */
    function perfectySubscribe() {
        var popts    = window.PerfectyPushOptions || {};
        var vapidKey = popts.vapidPublicKey || '';
        var scope    = popts.serviceWorkerScope || '/';

        console.log('[OHA Notif] perfectySubscribe() starting');
        console.log('[OHA Notif] PerfectyPushOptions:', window.PerfectyPushOptions ? 'present' : 'MISSING');
        console.log('[OHA Notif] VAPID key:', vapidKey ? 'present (' + vapidKey.length + ' chars)' : 'MISSING');
        console.log('[OHA Notif] SW scope:', scope);

        if (!vapidKey) {
            return Promise.reject(new Error('Perfecty VAPID key not available'));
        }

        // If no SW is registered at the Perfecty scope, try to register the proxy now.
        // This handles the case where the user is on a non-convention page and pwa-register.js
        // returned early, but notification permission has just been granted.
        return navigator.serviceWorker.getRegistration(scope).then(function (existing) {
            if (!existing) {
                console.log('[OHA Notif] No SW at scope — attempting proxy SW self-registration');
                return navigator.serviceWorker.register('/perfecty-push-sw.js', {
                    scope: scope, updateViaCache: 'none'
                }).then(function (reg) {
                    console.log('[OHA Notif] Proxy SW registered on-demand. Scope:', reg.scope);
                    return; // fall through to poll loop below
                }).catch(function (err) {
                    console.warn('[OHA Notif] Proxy SW self-registration failed:', err.message);
                });
            }
        }).then(function () {
            // Wait for Perfecty's SW to become active (up to 10 s, polling every 500 ms)
            return new Promise(function (resolve, reject) {
                var attempts = 0;
                var check = function () {
                    attempts++;
                    navigator.serviceWorker.getRegistration(scope).then(function (reg) {
                        console.log('[OHA Notif] Poll #' + attempts + ' — reg:', reg ? 'found' : 'missing',
                            '| active:', !!(reg && reg.active),
                            '| installing:', !!(reg && reg.installing),
                            '| waiting:', !!(reg && reg.waiting));
                        if (reg && reg.active) {
                            resolve(reg);
                        } else if (attempts >= 20) {
                            reject(new Error('Perfecty service worker not ready at scope: ' + scope));
                        } else {
                            setTimeout(check, 500);
                        }
                    }).catch(reject);
                };
                check();
            });
        }).then(function (reg) {
            return reg.pushManager.subscribe({
                userVisibleOnly:      true,
                applicationServerKey: urlBase64ToUint8Array(vapidKey),
            });
        }).then(function (sub) {
            return perfectyRegister(sub).then(function (data) {
                // Store Perfecty's uuid so we can unregister later
                if (data && data.uuid) {
                    localStorage.setItem('oha_perfecty_uuid', data.uuid);
                }
                return sub;
            });
        });
    }

    // ── Platform helpers ─────────────────────────────────────────────────────
    function isAndroid() {
        return /android/i.test(navigator.userAgent) && !isIOS();
    }

    // ── Android system Chrome notifications guide ────────────────────────────
    //
    // Android requires TWO separate permission grants:
    //   1. Chrome per-site notification permission  (what Perfecty subscribe does above)
    //   2. Android system Chrome app notification permission  (this guide)
    //
    // Without step 2, push notifications are silently dropped when Chrome is in
    // the background. We show this guide once per device after step 1 succeeds.
    //
    function showAndroidChromeGuide() {
        if (!isAndroid()) return;
        if (isStandalone()) {
            // Already installed as an app — check if guide was shown recently
            if (localStorage.getItem('oha_android_guide_shown')) return;
        } else {
            if (localStorage.getItem('oha_android_guide_shown')) return;
        }
        if (document.getElementById('oha-android-guide')) return;

        localStorage.setItem('oha_android_guide_shown', '1');

        var overlay = document.createElement('div');
        overlay.id        = 'oha-android-guide-overlay';
        overlay.className = 'oha-android-guide-overlay';

        var guide = document.createElement('div');
        guide.id        = 'oha-android-guide';
        guide.className = 'oha-android-guide';
        guide.innerHTML =
            '<div class="oha-android-guide-handle"></div>' +
            '<div class="oha-android-guide-badge">📱 Android tip</div>' +
            '<h3>One more step for background alerts</h3>' +
            '<p class="oha-android-guide-intro">' +
                'Chrome website notifications are ON ✅ — but on Android, ' +
                'background push also needs Chrome\'s system notification permission. ' +
                'Without it, alerts only arrive when Chrome is open.' +
            '</p>' +
            '<ul class="oha-android-guide-steps">' +
                '<li><span class="step-num">1</span><span>Open Android <strong>Settings</strong></span></li>' +
                '<li><span class="step-num">2</span><span>Go to <strong>Apps</strong> &rarr; tap <strong>Chrome</strong></span></li>' +
                '<li><span class="step-num">3</span><span>Tap <strong>Notifications</strong></span></li>' +
                '<li><span class="step-num">4</span><span>Turn <strong>All Chrome notifications</strong> ON ✅</span></li>' +
            '</ul>' +
            '<div class="oha-android-guide-actions">' +
                '<button class="oha-android-guide-btn-settings" id="oha-android-open-settings">⚙️ Open Android Settings</button>' +
                '<button class="oha-android-guide-btn-done"     id="oha-android-guide-done">Done — close</button>' +
            '</div>';

        document.body.appendChild(overlay);
        document.body.appendChild(guide);

        requestAnimationFrame(function () {
            overlay.classList.add('visible');
            guide.classList.add('visible');
        });

        function closeGuide() {
            overlay.classList.remove('visible');
            guide.classList.remove('visible');
            setTimeout(function () {
                overlay.remove();
                guide.remove();
            }, 350);
        }

        document.getElementById('oha-android-open-settings').addEventListener('click', function () {
            // Attempt to open Android Settings via intent URI (works from PWA/Chrome on Android).
            // If the browser can't handle it, it silently fails and the user follows the steps shown.
            window.location.href = 'intent://settings#Intent;scheme=android-app;package=com.android.settings;end';
            setTimeout(closeGuide, 800);
        });

        document.getElementById('oha-android-guide-done').addEventListener('click', closeGuide);
        overlay.addEventListener('click', closeGuide);
    }

    // ── Bell button ─────────────────────────────────────────────────────────
    function injectBellButton() {
        if (document.getElementById('oha-notif-bell')) { return; }

        var nav = document.querySelector('.oha-pwa-nav');
        if (!nav) { return; }

        var btn       = document.createElement('button');
        btn.id        = 'oha-notif-bell';
        btn.className = 'oha-nav-btn oha-notification-bell';
        btn.setAttribute('aria-label', 'Notification settings');
        btn.innerHTML = '<span class="bell-icon">🔕</span> Alerts';
        nav.appendChild(btn);

        btn.addEventListener('click', function () {
            if (!NOTIF_ENABLED) { return; }
            var perm = (typeof Notification !== 'undefined') ? Notification.permission : 'default';
            if (perm === 'granted') {
                // Browser permission is granted, but verify Perfecty actually has
                // our push endpoint. The first install may have failed to register
                // (e.g. the proxy SW wasn't in place yet). Re-subscribe silently if so.
                perfectyGetSubscription().then(function (sub) {
                    if (sub) {
                        showToast('🔔 Notifications are already enabled!', 'success');
                    } else {
                        perfectySubscribe()
                            .then(function () {
                                markGranted();
                                showToast('🔔 Notifications enabled!', 'success');
                                setTimeout(showAndroidChromeGuide, 1500);
                            })
                            .catch(function (err) {
                                console.warn('[OHA PWA] Re-subscribe failed:', err);
                                // Permission is granted but SW/sub failed — don't say "blocked"
                                showSettingsGuide(isIOS() ? 'ios' : 'android', true);
                            });
                    }
                });
            } else if (perm === 'denied') {
                showSettingsGuide(isIOS() ? 'ios' : 'android');
            } else {
                startOnboarding();
            }
        });

        // Set initial state
        var perm = (typeof Notification !== 'undefined') ? Notification.permission : 'default';
        if (perm === 'granted') {
            updateBellState('granted');
        } else if (perm === 'denied') {
            updateBellState('denied');
        }
    }

    function updateBellState(state) {
        var btn = document.getElementById('oha-notif-bell');
        if (!btn) { return; }
        btn.className = btn.className
            .replace(/\b(granted|denied|default)\b/g, '')
            .trim() + ' ' + state;

        var icon = btn.querySelector('.bell-icon');
        if (!icon) { return; }
        if (state === 'granted') {
            icon.textContent = '🔔';
            btn.title = 'Notifications enabled';
        } else if (state === 'denied') {
            icon.textContent = '⚠️';
            btn.title = 'Notifications blocked — tap to fix';
        } else {
            icon.textContent = '🔕';
            btn.title = 'Enable notifications';
        }
    }

    // ── Main state machine ───────────────────────────────────────────────────
    function startOnboarding() {
        if (!NOTIF_ENABLED) { return; }
        if (isSnoozed()) { return; }
        if (getState() === 'granted') {
            var perm = (typeof Notification !== 'undefined') ? Notification.permission : 'default';
            if (perm === 'granted') {
                // Stored state says granted — verify Perfecty still has our endpoint.
                // If missing (e.g. SW was blocked on first install), re-register silently.
                perfectyGetSubscription().then(function (sub) {
                    if (sub) {
                        updateBellState('granted');
                    } else {
                        localStorage.removeItem(LS_STATE);
                        perfectySubscribe()
                            .then(function () { markGranted(); })
                            .catch(function (err) { console.warn('[OHA PWA] Re-subscribe (stale grant):', err); });
                    }
                });
                return;
            }
            // Permission was revoked — clear stored state and re-run
            localStorage.removeItem(LS_STATE);
        }

        var ios        = isIOS();
        var standalone = isStandalone();

        if (ios && !standalone) {
            // iOS browser — must install first before push is possible
            showIOSInstallNudge();
            return;
        }

        var perm = (typeof Notification !== 'undefined') ? Notification.permission : 'default';

        if (perm === 'granted') {
            // Permission already granted — still need to verify Perfecty has the endpoint.
            perfectyGetSubscription().then(function (sub) {
                if (sub) {
                    markGranted();
                } else {
                    perfectySubscribe()
                        .then(function () { markGranted(); })
                        .catch(function (err) { console.warn('[OHA PWA] Subscribe (already permitted):', err); });
                }
            });
            return;
        }

        if (perm === 'denied') {
            showSettingsGuide(ios ? 'ios' : 'android');
            return;
        }

        // perm === 'default' — show pre-permission modal, then trigger OS prompt
        if (lastPromptedRecently()) { return; }
        setTimeout(function () {
            showPrePermissionModal(ios ? 'ios' : 'android');
        }, 2000);
    }

    // ── Pre-permission modal ─────────────────────────────────────────────────
    function showPrePermissionModal(platform) {
        if (document.getElementById('oha-pre-perm-modal')) { return; }
        markPrompted();

        var modal       = document.createElement('div');
        modal.id        = 'oha-pre-perm-modal';
        modal.className = 'oha-notif-modal-overlay';
        modal.innerHTML =
            '<div class="oha-notif-modal">' +
                '<div class="oha-notif-modal-header">' +
                    '<span class="oha-notif-modal-icon">🔔</span>' +
                    '<h2>Stay in the loop</h2>' +
                '</div>' +
                '<div class="oha-notif-modal-body">' +
                    '<p>Get instant alerts about:</p>' +
                    '<ul>' +
                        '<li>📅 Schedule changes &amp; room moves</li>' +
                        '<li>📢 Announcements from the stage</li>' +
                        '<li>⏰ Your sessions starting soon</li>' +
                        '<li>👥 Volunteer shift reminders</li>' +
                    '</ul>' +
                '</div>' +
                '<div class="oha-notif-modal-actions">' +
                    '<button class="oha-notif-btn-primary" id="oha-perm-enable">Enable Notifications</button>' +
                    '<button class="oha-notif-btn-secondary" id="oha-perm-later">Maybe Later</button>' +
                '</div>' +
            '</div>';

        document.body.appendChild(modal);
        requestAnimationFrame(function () { modal.classList.add('visible'); });

        document.getElementById('oha-perm-enable').addEventListener('click', function () {
            closeModal(modal);
            triggerOSPrompt(platform);
        });

        document.getElementById('oha-perm-later').addEventListener('click', function () {
            closeModal(modal);
            snooze();
        });
    }

    // ── Trigger the OS-level notification permission prompt ──────────────────
    function triggerOSPrompt(platform) {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            showSettingsGuide(platform);
            return;
        }

        // Request browser notification permission, then subscribe via Perfecty VAPID.
        // We MUST get 'granted' from the OS prompt before calling pushManager.subscribe().
        Notification.requestPermission().then(function (permission) {
            if (permission !== 'granted') {
                updateBellState('denied');
                showSettingsGuide(platform);
                return;
            }

            // Permission granted — now subscribe via Perfecty's VAPID key
            perfectySubscribe().then(function () {
                markGranted();
                showToast('🔔 Notifications enabled! You\'ll receive convention updates.', 'success');
                // Android: guide user through the second required permission grant
                // (Android system Chrome app notification permission).
                setTimeout(showAndroidChromeGuide, 1500);
            }).catch(function (err) {
                console.warn('[OHA PWA] Perfecty subscribe failed:', err);
                // Permission is granted but subscription failed — don't say "blocked"
                showSettingsGuide(platform, true);
            });
        }).catch(function (err) {
            console.warn('[OHA PWA] requestPermission failed:', err);
            showSettingsGuide(platform);
        });
    }

    // ── Settings guide modal ─────────────────────────────────────────────────
    // subscriptionFailed = true  → permission is granted but SW/subscription failed
    //                              (do NOT say "blocked" — that's wrong and confusing)
    // subscriptionFailed = false → permission is genuinely denied in Chrome
    function showSettingsGuide(platform, subscriptionFailed) {
        // Prevent duplicate modals (not a session-wide rate limit — allow retry)
        if (document.getElementById('oha-settings-guide-modal')) { return; }

        var icon, heading, intro, steps, actionBtn;

        if (subscriptionFailed) {
            // Permission IS granted but push setup failed (SW not ready, network, etc.)
            icon    = '🔔';
            heading = 'Finish enabling notifications';
            intro   = 'Your device already has permission - tap below to complete the ' +
                      'connection so you receive convention updates:';
            steps     = '';
            actionBtn = '<button class="oha-notif-btn-primary" id="oha-guide-retry">Complete Setup</button>';

        } else if (platform === 'ios') {
            icon    = '⚠️';
            heading = 'Notifications are blocked';
            intro   = 'Here\'s how to re-enable them:';
            steps   =
                '<ol class="oha-guide-steps">' +
                    '<li>Open the <strong>Settings</strong> app on your device 📱</li>' +
                    '<li>Scroll down and tap <strong>' + APP_NAME + '</strong>' +
                        '<br><small>(It appears as its own app entry with the OHC icon)</small></li>' +
                    '<li>Tap <strong>Notifications</strong></li>' +
                    '<li>Turn ON <strong>Allow Notifications</strong> ✅</li>' +
                '</ol>';
            actionBtn =
                '<button class="oha-notif-btn-primary" id="oha-guide-open-settings">Open Settings</button>' +
                '<button class="oha-notif-btn-primary" id="oha-guide-retry" style="margin-top:8px;width:100%;">I\'ve fixed it — Try Again</button>';

        } else {
            // Android / Desktop Chrome — Chrome per-site permission is blocked.
            // IMPORTANT: this is Chrome's website permission, not Android system notifications.
            icon    = '⚠️';
            heading = 'Chrome site notifications blocked';
            intro   = '<strong>Note:</strong> This is Chrome\'s website permission, which is ' +
                      '<em>separate</em> from Android\'s system notification setting. ' +
                      'Here\'s how to fix it in Chrome:';
            steps   =
                '<ol class="oha-guide-steps">' +
                    '<li>Open <strong>Chrome</strong> (not the installed app shortcut)</li>' +
                    '<li>Visit <strong>' + SITE_HOST + '</strong></li>' +
                    '<li>Tap the <strong>🔒 lock icon</strong> in the address bar</li>' +
                    '<li>Tap <strong>Site settings</strong></li>' +
                    '<li>Tap <strong>Notifications</strong> → select <strong>Allow</strong> ✅</li>' +
                '</ol>';
            actionBtn =
                '<button class="oha-notif-btn-primary" id="oha-guide-retry">I\'ve fixed it — Try Again</button>';
        }

        var modal       = document.createElement('div');
        modal.id        = 'oha-settings-guide-modal';
        modal.className = 'oha-notif-modal-overlay';
        modal.innerHTML =
            '<div class="oha-notif-modal">' +
                '<div class="oha-notif-modal-header">' +
                    '<span class="oha-notif-modal-icon">' + icon + '</span>' +
                    '<h2>' + heading + '</h2>' +
                '</div>' +
                '<div class="oha-notif-modal-body">' +
                    '<p>' + intro + '</p>' +
                    steps +
                '</div>' +
                '<div class="oha-notif-modal-actions">' +
                    actionBtn +
                    '<button class="oha-notif-btn-secondary" id="oha-guide-dismiss">Maybe Later</button>' +
                '</div>' +
            '</div>';

        document.body.appendChild(modal);
        requestAnimationFrame(function () { modal.classList.add('visible'); });

        document.getElementById('oha-guide-dismiss')?.addEventListener('click', function () {
            closeModal(modal);
        });

        var retryBtn = document.getElementById('oha-guide-retry');
        if (retryBtn) {
            retryBtn.addEventListener('click', function () {
                closeModal(modal);
                // Re-attempt the full subscription flow after settings are fixed
                Notification.requestPermission().then(function (perm) {
                    if (perm === 'granted') {
                        perfectySubscribe()
                            .then(function () { markGranted(); showToast('🔔 Notifications enabled!', 'success'); })
                            .catch(function (err) { showSettingsGuide(platform, true); });
                    } else {
                        showSettingsGuide(platform, false);
                    }
                }).catch(function () { showSettingsGuide(platform, false); });
            });
        }

        var settingsBtn = document.getElementById('oha-guide-open-settings');
        if (settingsBtn) {
            settingsBtn.addEventListener('click', function () {
                window.location.href = 'app-settings:';
                closeModal(modal);
            });
        }
    }

    // ── iOS install nudge (shown when iOS + not standalone) ──────────────────
    function showIOSInstallNudge() {
        if (localStorage.getItem('oha_ios_nudge_dismissed')) { return; }
        if (document.getElementById('oha-ios-install-nudge')) { return; }

        var nudge       = document.createElement('div');
        nudge.id        = 'oha-ios-install-nudge';
        nudge.className = 'oha-ios-nudge';
        nudge.innerHTML =
            '<div class="oha-ios-nudge-content">' +
                '<strong>📱 Install for notifications</strong>' +
                '<p>Tap <strong>Share</strong> (📤) then <strong>"Add to Home Screen"</strong> to enable push notifications for schedule updates.</p>' +
                '<button id="oha-ios-nudge-dismiss">Got it</button>' +
            '</div>';

        document.body.appendChild(nudge);
        requestAnimationFrame(function () { nudge.classList.add('visible'); });

        document.getElementById('oha-ios-nudge-dismiss').addEventListener('click', function () {
            nudge.classList.remove('visible');
            setTimeout(function () { nudge.remove(); }, 300);
            localStorage.setItem('oha_ios_nudge_dismissed', '1');
        });
    }

    // ── Toast notification ───────────────────────────────────────────────────
    function showToast(message, type) {
        var toast       = document.createElement('div');
        toast.className = 'oha-notification-toast ' + (type || 'info');
        toast.textContent = message;
        document.body.appendChild(toast);
        requestAnimationFrame(function () { toast.classList.add('visible'); });
        setTimeout(function () {
            toast.classList.remove('visible');
            setTimeout(function () { toast.remove(); }, 350);
        }, 4000);
    }

    // ── Close any modal ──────────────────────────────────────────────────────
    function closeModal(modal) {
        modal.classList.remove('visible');
        setTimeout(function () { modal.remove(); }, 350);
    }

    // ── Boot ─────────────────────────────────────────────────────────────────
    function boot() {
        if (!NOTIF_ENABLED) { return; }

        // Diagnostic snapshot on every boot — visible in DevTools console
        var popts = window.PerfectyPushOptions || {};
        console.log('[OHA Notif] Boot —',
            'permission:', typeof Notification !== 'undefined' ? Notification.permission : 'unsupported',
            '| localStorage state:', getState() || '(none)',
            '| PerfectyPushOptions:', window.PerfectyPushOptions ? 'present' : 'MISSING',
            '| SW scope:', popts.serviceWorkerScope || '(not set)',
            '| VAPID key:', popts.vapidPublicKey ? 'present' : 'MISSING'
        );

        // Always inject bell so users can manually trigger onboarding
        injectBellButton();

        // Start onboarding when PWA is installed (fired by pwa-install.js)
        window.addEventListener('ohaPWAInstalled', function () {
            startOnboarding();
        });

        // Also run on load if already in standalone mode (returning user).
        // 4 s delay gives the page time to fully paint before showing any modal.
        if (isStandalone()) {
            setTimeout(startOnboarding, 4000);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

})();
