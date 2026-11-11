(function() {
    'use strict';

    var PROMPT_COOLDOWN_MS = 24 * 60 * 60 * 1000; // 24 hours
    var PROMPT_DELAY_MS    = 5000;
    var deferredPrompt     = null;

    // Don't show if already installed
    if (window.matchMedia('(display-mode: standalone)').matches) return;
    if (window.navigator.standalone === true) return;

    // --- Android install prompt ---

    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        deferredPrompt = e;

        var lastPrompt = parseInt(localStorage.getItem('oha_spwa_install_prompt_ts') || '0', 10);
        if (Date.now() - lastPrompt < PROMPT_COOLDOWN_MS) return;

        setTimeout(showAndroidPrompt, PROMPT_DELAY_MS);
    });

    function showAndroidPrompt() {
        if (!deferredPrompt) return;

        var sheet = document.createElement('div');
        sheet.id = 'oha-spwa-install-sheet';
        sheet.setAttribute('role', 'dialog');
        sheet.setAttribute('aria-modal', 'true');
        sheet.setAttribute('aria-label', 'Install OHA App');
        sheet.innerHTML =
            '<div id="oha-spwa-install-backdrop"></div>' +
            '<div id="oha-spwa-install-card">' +
                '<div id="oha-spwa-install-header">' +
                    '<strong>Add OHA to Your Home Screen</strong>' +
                    '<button id="oha-spwa-install-close" aria-label="Close">' +
                        '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">' +
                            '<line x1="1" y1="1" x2="17" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>' +
                            '<line x1="17" y1="1" x2="1" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>' +
                        '</svg>' +
                    '</button>' +
                '</div>' +
                '<p id="oha-spwa-install-body">Get quick access to haunt listings, events, and member resources — right from your home screen.</p>' +
                '<div id="oha-spwa-install-actions">' +
                    '<button id="oha-spwa-install-btn">Add to Home Screen</button>' +
                    '<button id="oha-spwa-install-later">Not Now</button>' +
                '</div>' +
            '</div>';

        document.body.appendChild(sheet);

        document.getElementById('oha-spwa-install-btn').addEventListener('click', function() {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function(result) {
                deferredPrompt = null;
                localStorage.setItem('oha_spwa_install_prompt_ts', String(Date.now()));
                sheet.remove();
            });
        });

        function dismissSheet() {
            localStorage.setItem('oha_spwa_install_prompt_ts', String(Date.now()));
            sheet.remove();
        }

        document.getElementById('oha-spwa-install-close').addEventListener('click', dismissSheet);
        document.getElementById('oha-spwa-install-later').addEventListener('click', dismissSheet);
        document.getElementById('oha-spwa-install-backdrop').addEventListener('click', dismissSheet);
    }

    window.addEventListener('appinstalled', function() {
        deferredPrompt = null;
        var sheet = document.getElementById('oha-spwa-install-sheet');
        if (sheet) sheet.remove();
    });

    // --- iOS Safari install guide ---

    var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    var isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);

    if (isIOS && isSafari) {
        var lastIOS = parseInt(localStorage.getItem('oha_spwa_ios_prompt_ts') || '0', 10);
        if (Date.now() - lastIOS < PROMPT_COOLDOWN_MS) return;

        setTimeout(showIOSGuide, PROMPT_DELAY_MS);
    }

    function showIOSGuide() {
        var bar = document.createElement('div');
        bar.id = 'oha-spwa-ios-guide';
        bar.setAttribute('role', 'status');
        bar.innerHTML =
            '<svg id="oha-spwa-ios-arrow" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">' +
                '<path d="M8 1v10M4 7l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
            '</svg>' +
            '<span>Tap the Share button, then choose <strong>Add to Home Screen</strong> to install the OHA app.</span>' +
            '<button id="oha-spwa-ios-close" aria-label="Dismiss">&#x2715;</button>';

        document.body.appendChild(bar);

        document.getElementById('oha-spwa-ios-close').addEventListener('click', function() {
            localStorage.setItem('oha_spwa_ios_prompt_ts', String(Date.now()));
            bar.remove();
        });

        setTimeout(function() {
            if (document.body.contains(bar)) {
                localStorage.setItem('oha_spwa_ios_prompt_ts', String(Date.now()));
                bar.remove();
            }
        }, 12000);
    }

})();
