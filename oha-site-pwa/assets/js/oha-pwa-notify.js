(function() {
    'use strict';

    var STORAGE_KEY = 'oha_notify_v1';
    var FIRST_VISIT = 'oha_pv';
    var DELAY_MS    = 30000; // 30 seconds after page load on return visits

    function lsGet(k) {
        try { return JSON.parse(localStorage.getItem(k) || 'null'); } catch (e) { return null; }
    }
    function lsSet(k, v) {
        try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {}
    }

    function canShow() {
        if (typeof Notification === 'undefined') return false;

        // User has blocked notifications at the browser level — nothing we can do
        if (Notification.permission === 'denied') return false;

        // Already opted in via OneSignal
        if (window.ohaPushSubscribed === true) return false;

        // First ever visit: mark it and skip. Show on the next visit.
        if (!lsGet(FIRST_VISIT)) {
            lsSet(FIRST_VISIT, 1);
            return false;
        }

        var state = lsGet(STORAGE_KEY) || {};

        if (state.dismissed) return false;

        if (state.deferred && Date.now() < state.deferred) return false;

        return true;
    }

    function showBanner() {
        var el = document.getElementById('oha-notify-banner');
        if (!el) return;
        el.setAttribute('aria-hidden', 'false');
        el.classList.add('oha-notify-visible');
    }

    function hideBanner() {
        var el = document.getElementById('oha-notify-banner');
        if (!el) return;
        el.setAttribute('aria-hidden', 'true');
        el.classList.remove('oha-notify-visible');
    }

    function onAccept() {
        hideBanner();
        lsSet(STORAGE_KEY, { dismissed: true });
        window.OneSignalDeferred = window.OneSignalDeferred || [];
        OneSignalDeferred.push(function(OneSignal) {
            OneSignal.User.PushSubscription.optIn().catch(function() {});
        });
    }

    function onDefer() {
        hideBanner();
        var state  = lsGet(STORAGE_KEY) || {};
        var count  = (state.deferCount || 0) + 1;
        if (count >= 2) {
            lsSet(STORAGE_KEY, { dismissed: true });
        } else {
            lsSet(STORAGE_KEY, {
                deferred:   Date.now() + 7 * 24 * 60 * 60 * 1000,
                deferCount: count,
            });
        }
    }

    function wireButtons() {
        var accept = document.getElementById('oha-notify-accept');
        var defer  = document.getElementById('oha-notify-defer');
        var close  = document.getElementById('oha-notify-close');
        if (accept) accept.addEventListener('click', onAccept);
        if (defer)  defer.addEventListener('click',  onDefer);
        if (close)  close.addEventListener('click',  onDefer);
    }

    // Called by oha-pwa-init.js after OneSignal has initialized.
    // window.ohaPushSubscribed is set before this fires, so canShow() is reliable.
    // If push is disabled or the app ID is not configured, this callback is never
    // called and the banner never appears.
    window.ohaSpwaPushReady = function() {
        if (!canShow()) return;
        wireButtons();
        setTimeout(function() {
            if (canShow()) showBanner();
        }, DELAY_MS);
    };

    document.addEventListener('DOMContentLoaded', wireButtons);
})();
