/**
 * OHA Convention PWA — Service Worker Registration & Update Handler
 *
 * Update flow:
 *  1. Browser fetches /convention-sw.js on every load (updateViaCache:'none' bypasses HTTP cache)
 *  2. If bytes differ from installed SW → new SW installs, enters "waiting" state
 *  3. updatefound → statechange fires → showUpdateBanner() called
 *  4. User clicks "Refresh" → banner posts {type:'SKIP_WAITING'} to waiting SW
 *  5. SW calls self.skipWaiting() → activates → controllerchange fires
 *  6. Page reloads once to pick up new assets
 */

(function () {
    'use strict';

    if (!('serviceWorker' in navigator)) {
        console.log('[PWA] Service workers not supported');
        return;
    }

    console.log('[PWA] Starting registration script');
    console.log('[PWA] Current path:', window.location.pathname);
    console.log('[PWA] ohaPWA object:', typeof ohaPWA !== 'undefined' ? ohaPWA : 'NOT DEFINED');

    const config            = typeof ohaPWA !== 'undefined' ? ohaPWA : { scope: '/convention/' };
    const conventionScope   = config.scope || '/convention/';
    const currentPath       = window.location.pathname;

    const isConventionPage  = currentPath.includes('convention') ||
                              currentPath.includes('session')    ||
                              currentPath.includes('speaker')    ||
                              currentPath.includes('vendor')     ||
                              currentPath.includes('room')       ||
                              currentPath.includes('actor-passport');

    console.log('[PWA] Is convention page?', isConventionPage);

    if (!isConventionPage) {
        console.log('[PWA] Not a convention page — skipping SW registration');
        return;
    }

    console.log('[PWA] On convention page — registering service worker');

    // Reload-once guard — prevents infinite reload loops
    var reloadQueued = false;

    // -------------------------------------------------------------------------
    // Update banner
    // Injected inline; does not depend on any stylesheet being loaded.
    // -------------------------------------------------------------------------
    function injectBannerStyles() {
        if (document.getElementById('oha-sw-update-style')) { return; }
        var style = document.createElement('style');
        style.id  = 'oha-sw-update-style';
        style.textContent = [
            '#oha-sw-update-banner {',
            '  position: fixed; bottom: 0; left: 0; right: 0; z-index: 99999;',
            '  background: #8B0000; color: #fff;',
            '  display: flex; align-items: center; justify-content: space-between;',
            '  padding: 12px 16px; font-size: 14px; font-family: sans-serif;',
            '  transform: translateY(100%); transition: transform 0.3s ease;',
            '  box-shadow: 0 -2px 12px rgba(0,0,0,0.4);',
            '}',
            '#oha-sw-update-banner.visible { transform: translateY(0); }',
            '#oha-sw-update-btn {',
            '  background: #fff; color: #8B0000; border: none; cursor: pointer;',
            '  font-weight: 700; font-size: 13px; padding: 6px 14px;',
            '  border-radius: 4px; white-space: nowrap; margin-left: 12px;',
            '  flex-shrink: 0;',
            '}',
            '#oha-sw-dismiss-btn {',
            '  background: transparent; color: rgba(255,255,255,0.7); border: none;',
            '  cursor: pointer; font-size: 18px; padding: 0 0 0 12px;',
            '  line-height: 1; flex-shrink: 0;',
            '}',
        ].join('\n');
        document.head.appendChild(style);
    }

    function showUpdateBanner(waitingWorker) {
        injectBannerStyles();
        if (document.getElementById('oha-sw-update-banner')) { return; }

        var banner = document.createElement('div');
        banner.id  = 'oha-sw-update-banner';
        banner.setAttribute('role', 'status');
        banner.innerHTML =
            '<span>Convention app updated — refresh to get the latest version.</span>' +
            '<button id="oha-sw-update-btn">Refresh Now</button>' +
            '<button id="oha-sw-dismiss-btn" aria-label="Dismiss">x</button>';

        document.body.appendChild(banner);
        setTimeout(function () { banner.classList.add('visible'); }, 100);

        document.getElementById('oha-sw-update-btn').addEventListener('click', function () {
            if (!waitingWorker) { window.location.reload(); return; }
            reloadQueued = true;
            waitingWorker.postMessage({ type: 'SKIP_WAITING' });
        });

        document.getElementById('oha-sw-dismiss-btn').addEventListener('click', function () {
            banner.classList.remove('visible');
            setTimeout(function () { banner.remove(); }, 350);
        });
    }

    // controllerchange fires when new SW takes control after skipWaiting
    navigator.serviceWorker.addEventListener('controllerchange', function () {
        if (reloadQueued) {
            console.log('[PWA] Controller changed — reloading for new version');
            reloadQueued = false;
            window.location.reload();
        }
    });

    // -------------------------------------------------------------------------
    // Register on page load
    // Stable URL + updateViaCache:'none' = browser always checks network for
    // SW freshness, bypassing HTTP cache. No timestamp hack needed.
    // -------------------------------------------------------------------------
    window.addEventListener('load', function () {
        var swUrl = '/convention-sw.js';
        console.log('[PWA] Registering:', swUrl, 'scope:', conventionScope);

        // ── Perfecty Push proxy SW ────────────────────────────────────────────
        // GoDaddy Nginx blocks direct PHP execution in plugin directories (403),
        // so Perfecty's service-worker-loader.js.php can't be fetched by the browser.
        // We register our own proxy at /perfecty-push-sw.js — served through WordPress —
        // which importScripts() Perfecty's bundled SW code using its absolute URL.
        // This must run before (or alongside) Perfecty's own registration attempt so
        // the proxy SW is active when pwa-notifications.js tries to subscribe.
        var perfectyScope = (window.PerfectyPushOptions || {}).serviceWorkerScope;
        if (perfectyScope) {
            console.log('[PWA] Perfecty scope from PerfectyPushOptions:', perfectyScope);
            navigator.serviceWorker.register('/perfecty-push-sw.js', {
                scope:          perfectyScope,
                updateViaCache: 'none'
            }).then(function (reg) {
                console.log('[PWA] Perfecty proxy SW registered.',
                    'scope:', reg.scope,
                    '| active:', reg.active     ? reg.active.state     : 'none',
                    '| installing:', reg.installing ? reg.installing.state : 'none',
                    '| waiting:',   reg.waiting    ? reg.waiting.state    : 'none');
                // 1-second follow-up to confirm the registration actually persisted
                setTimeout(function () {
                    navigator.serviceWorker.getRegistration(perfectyScope).then(function (r) {
                        console.log('[PWA] Perfecty SW follow-up check:',
                            r ? ('scope=' + r.scope + ' active=' + (r.active ? r.active.state : 'none'))
                              : 'NOT FOUND — registration did not persist');
                    });
                }, 1000);
            }).catch(function (err) {
                console.warn('[PWA] Perfecty proxy SW registration failed:', err.message);
            });
        } else {
            console.log('[PWA] PerfectyPushOptions.serviceWorkerScope not set — skipping proxy SW registration');
        }

        navigator.serviceWorker.register(swUrl, {
            scope:          conventionScope,
            updateViaCache: 'none'
        })
        .then(function (registration) {
            console.log('[PWA] Service worker registered. Scope:', registration.scope);

            // New SW already waiting (e.g. user opened a cached page after a deploy)
            if (registration.waiting) {
                console.log('[PWA] New SW already waiting — showing update banner');
                showUpdateBanner(registration.waiting);
            }

            // Watch for a new SW installing during this session
            registration.addEventListener('updatefound', function () {
                var newWorker = registration.installing;
                console.log('[PWA] Update found — new SW installing');

                newWorker.addEventListener('statechange', function () {
                    console.log('[PWA] SW state:', newWorker.state);
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        console.log('[PWA] New SW waiting — showing update banner');
                        showUpdateBanner(newWorker);
                    }
                });
            });

            // Periodic update check every hour
            setInterval(function () {
                registration.update();
            }, 60 * 60 * 1000);
        })
        .catch(function (error) {
            console.error('[PWA] SW registration failed:', error.name, error.message);
            fetch(swUrl)
                .then(function (r) {
                    console.log('[PWA] SW HTTP status:', r.status, r.statusText);
                    console.log('[PWA] Content-Type:', r.headers.get('Content-Type'));
                    console.log('[PWA] Cache-Control:', r.headers.get('Cache-Control'));
                    return r.text();
                })
                .then(function (text) {
                    console.log('[PWA] SW file size:', text.length, 'chars');
                    if (text.length < 100) {
                        console.error('[PWA] SW file suspiciously small:', text.substring(0, 500));
                    }
                })
                .catch(function (e) { console.error('[PWA] Cannot fetch SW file:', e); });
        });
    });

    // -------------------------------------------------------------------------
    // Incoming messages from SW
    // -------------------------------------------------------------------------
    navigator.serviceWorker.addEventListener('message', function (event) {
        if (event.data && event.data.action === 'cacheUpdated') {
            console.log('[PWA] Cache updated by SW');
        }
    });

    // -------------------------------------------------------------------------
    // Standalone mode detection
    // -------------------------------------------------------------------------
    function checkStandaloneMode() {
        var isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
                           window.navigator.standalone === true;
        if (isStandalone) {
            document.body.classList.add('pwa-standalone');
            console.log('[PWA] Running in standalone mode');
            trackPWAInstall();
        }
    }

    function trackPWAInstall() {
        if (sessionStorage.getItem('oha_pwa_tracked')) { return; }
        if (typeof ohaPWA === 'undefined' || !ohaPWA.apiUrl || !ohaPWA.nonce) { return; }

        fetch(ohaPWA.apiUrl + 'pwa/install', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': ohaPWA.nonce },
            body:    JSON.stringify({ user_agent: navigator.userAgent, platform: navigator.platform, is_standalone: true })
        })
        .then(function () { sessionStorage.setItem('oha_pwa_tracked', '1'); })
        .catch(function (e) { console.log('[PWA] Could not track install:', e); });
    }

    // -------------------------------------------------------------------------
    // Public helpers (debug shortcode + admin)
    // -------------------------------------------------------------------------
    window.ohaPWAGetCacheInfo = function () {
        return navigator.serviceWorker.ready.then(function (registration) {
            return new Promise(function (resolve) {
                var ch = new MessageChannel();
                ch.port1.onmessage = function (e) { resolve(e.data); };
                registration.active.postMessage({ action: 'getCacheInfo' }, [ch.port2]);
            });
        });
    };

    window.ohaPWAClearCache = function () {
        return navigator.serviceWorker.ready.then(function (registration) {
            registration.active.postMessage({ action: 'clearCache' });
            console.log('[PWA] Cache clear request sent');
        });
    };

    window.addEventListener('load', checkStandaloneMode);

})();
