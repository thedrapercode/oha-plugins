/**
 * OHA Convention PWA — Install Prompt Handler (v3.7.3)
 *
 * Manages the "Add to Home Screen" install experience.
 * Shows an engaging bottom-sheet install prompt instead of a plain floating button.
 * After install (or on standalone load), fires CustomEvent 'ohaPWAInstalled'
 * which triggers the notification onboarding flow in pwa-notifications.js.
 */

(function () {
    'use strict';

    var deferredPrompt = null;

    // ── Detect platform ──────────────────────────────────────────────────────
    function getPlatform() {
        var ua = navigator.userAgent;
        if (/ipad|iphone|ipod/i.test(ua)) return 'ios';
        if (/android/i.test(ua))           return 'android';
        return 'other';
    }

    function isStandalone() {
        return (
            window.navigator.standalone === true ||
            window.matchMedia('(display-mode: standalone)').matches
        );
    }

    // ── Signal the notification module that we are installed ─────────────────
    function signalInstalled() {
        window.dispatchEvent(new CustomEvent('ohaPWAInstalled'));
    }

    // ── Record install in our WP REST endpoint ───────────────────────────────
    function recordInstall() {
        if (typeof ohaPWA === 'undefined' && typeof ohaNotifConfig === 'undefined') return;

        var restUrl = (ohaNotifConfig && ohaNotifConfig.restUrl) || (ohaPWA && ohaPWA.apiUrl) || '';
        var nonce   = (ohaNotifConfig && ohaNotifConfig.nonce)   || (ohaPWA && ohaPWA.nonce)   || '';
        if (!restUrl) return;

        fetch(restUrl + 'pwa/install', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce':   nonce,
            },
            body: JSON.stringify({
                platform:   getPlatform(),
                standalone: isStandalone(),
                user_agent: navigator.userAgent,
            }),
        }).catch(function () { /* non-critical */ });
    }

    // ── Android: capture beforeinstallprompt ────────────────────────────────
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        window.dispatchEvent(new CustomEvent('pwaInstallAvailable'));

        // Check for an explicit install button already in the page first.
        var existingBtn = document.getElementById('oha-pwa-install-btn');
        if (existingBtn) {
            existingBtn.style.display = 'inline-block';
            existingBtn.classList.add('pwa-install-ready');
            return;
        }

        // Otherwise, show the engaging bottom sheet after a short engagement delay.
        // 5 s lets the user see the page before we ask.
        var sheetDelay = 5000;
        // If the user has dismissed before, respect a 24-hour cooldown.
        var dismissed = localStorage.getItem('oha_install_sheet_dismissed');
        if (dismissed) {
            var elapsed = Date.now() - parseInt(dismissed, 10);
            if (elapsed < 24 * 60 * 60 * 1000) return; // within cooldown
        }

        setTimeout(function () {
            if (deferredPrompt && !isStandalone()) {
                showInstallBottomSheet();
            }
        }, sheetDelay);
    });

    // ── Install bottom sheet ─────────────────────────────────────────────────
    function showInstallBottomSheet() {
        if (document.getElementById('oha-install-sheet')) return;

        var overlay = document.createElement('div');
        overlay.id        = 'oha-install-sheet-overlay';
        overlay.className = 'oha-install-sheet-overlay';

        var sheet = document.createElement('div');
        sheet.id        = 'oha-install-sheet';
        sheet.className = 'oha-install-sheet';
        sheet.innerHTML =
            '<div class="oha-install-sheet-handle"></div>' +
            '<div class="oha-install-sheet-header">' +
                '<div class="oha-install-sheet-icon">' +
                    '<img src="https://ohiohaunters.com/wp-content/uploads/2025/12/OHC.jpg" ' +
                         'alt="OHC" onerror="this.parentNode.textContent=\'🎃\'">' +
                '</div>' +
                '<div>' +
                    '<p class="oha-install-sheet-title">OHC Convention App</p>' +
                    '<p class="oha-install-sheet-subtitle">Ohio Haunters Convention 2026</p>' +
                '</div>' +
            '</div>' +
            '<ul class="oha-install-sheet-benefits">' +
                '<li><span class="benefit-icon">📶</span><span>Works offline at the venue — no signal needed</span></li>' +
                '<li><span class="benefit-icon">🔔</span><span>Push notifications for schedule changes &amp; announcements</span></li>' +
                '<li><span class="benefit-icon">⭐</span><span>Save favorites &amp; get 5-minute session reminders</span></li>' +
                '<li><span class="benefit-icon">⚡</span><span>Instant launch from your home screen</span></li>' +
            '</ul>' +
            '<div class="oha-install-sheet-actions">' +
                '<button class="oha-install-sheet-btn-install" id="oha-sheet-install-btn">📱 Add to Home Screen</button>' +
                '<button class="oha-install-sheet-btn-later"  id="oha-sheet-later-btn">Maybe later</button>' +
            '</div>';

        document.body.appendChild(overlay);
        document.body.appendChild(sheet);

        requestAnimationFrame(function () {
            overlay.classList.add('visible');
            sheet.classList.add('visible');
        });

        document.getElementById('oha-sheet-install-btn').addEventListener('click', function () {
            hideInstallBottomSheet();
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function (choice) {
                if (choice.outcome !== 'accepted') {
                    localStorage.setItem('oha_install_sheet_dismissed', Date.now().toString());
                }
                deferredPrompt = null;
            });
        });

        document.getElementById('oha-sheet-later-btn').addEventListener('click', function () {
            hideInstallBottomSheet();
            localStorage.setItem('oha_install_sheet_dismissed', Date.now().toString());
        });

        // Tap the backdrop to dismiss
        overlay.addEventListener('click', function () {
            hideInstallBottomSheet();
            localStorage.setItem('oha_install_sheet_dismissed', Date.now().toString());
        });
    }

    function hideInstallBottomSheet() {
        var overlay = document.getElementById('oha-install-sheet-overlay');
        var sheet   = document.getElementById('oha-install-sheet');
        if (overlay) {
            overlay.classList.remove('visible');
            setTimeout(function () { overlay.remove(); }, 350);
        }
        if (sheet) {
            sheet.classList.remove('visible');
            setTimeout(function () { sheet.remove(); }, 350);
        }
    }

    // ── Legacy explicit install button support (pages with #oha-pwa-install-btn) ──
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#oha-pwa-install-btn');
        if (!btn || !deferredPrompt) return;
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function () {
            btn.style.display = 'none';
            deferredPrompt = null;
        });
    });

    // ── appinstalled: fires on Android after user accepts prompt ─────────────
    window.addEventListener('appinstalled', function () {
        hideInstallBottomSheet();
        recordInstall();
        // Delay 5 s so Android finishes creating the home-screen icon before
        // we fire ohaPWAInstalled and trigger the notification onboarding modal.
        setTimeout(signalInstalled, 5000);
        window.dispatchEvent(new CustomEvent('pwaInstalled'));
    });

    // ── iOS install instructions (share → Add to Home Screen) ────────────────
    function maybeShowIOSInstructions() {
        var ios        = /ipad|iphone|ipod/i.test(navigator.userAgent);
        var standalone = isStandalone();
        if (!ios || standalone) return;
        if (localStorage.getItem('oha_pwa_ios_hint_dismissed')) return;

        var existing = document.getElementById('oha-pwa-ios-hint');
        if (existing) return;

        var hint = document.createElement('div');
        hint.id        = 'oha-pwa-ios-hint';
        hint.className = 'oha-pwa-ios-hint';
        hint.innerHTML =
            '<div class="oha-pwa-ios-content">' +
                '<strong>📱 Install the OHC App</strong>' +
                '<p>Tap <strong>Share</strong> (📤) then <strong>"Add to Home Screen"</strong> for the best experience and push notifications.</p>' +
                '<button id="oha-pwa-ios-dismiss">Got it</button>' +
            '</div>';

        document.body.appendChild(hint);
        setTimeout(function () { hint.classList.add('visible'); }, 500);

        document.getElementById('oha-pwa-ios-dismiss').addEventListener('click', function () {
            hint.classList.remove('visible');
            setTimeout(function () { hint.remove(); }, 300);
            localStorage.setItem('oha_pwa_ios_hint_dismissed', '1');
        });
    }

    // ── On load ──────────────────────────────────────────────────────────────
    window.addEventListener('load', function () {
        // Hide install buttons if already installed
        if (isStandalone()) {
            document.querySelectorAll('.oha-pwa-install-btn, #oha-pwa-install-btn')
                .forEach(function (btn) { btn.style.display = 'none'; });

            // Returning standalone user — signal notification module
            recordInstall();
            signalInstalled();
            return;
        }

        // Check for iOS install hint after a delay
        setTimeout(maybeShowIOSInstructions, 3000);
    });

    // ── Expose for manual triggering ────────────────────────────────────────
    window.ohaPWAInstall = function () {
        if (deferredPrompt) {
            showInstallBottomSheet();
        }
    };

})();
