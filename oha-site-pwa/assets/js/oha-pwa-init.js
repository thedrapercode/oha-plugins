(function() {
    'use strict';

    // Online/offline class toggling (pwa-mode is set in head inline script)
    var html = document.documentElement;
    window.addEventListener('online',  function() { html.classList.remove('pwa-offline'); });
    window.addEventListener('offline', function() { html.classList.add('pwa-offline'); });

    if (!('serviceWorker' in navigator)) return;
    if (typeof ohaSpwaConfig === 'undefined') return;

    // --- Service Worker Registration ---

    var swRegistration = null;
    var updateBannerShown = false;

    navigator.serviceWorker.register(ohaSpwaConfig.swPath, { scope: ohaSpwaConfig.swScope })
        .then(function(reg) {
            swRegistration = reg;

            reg.addEventListener('updatefound', function() {
                var newWorker = reg.installing;
                if (!newWorker) return;
                newWorker.addEventListener('statechange', function() {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller && !updateBannerShown) {
                        updateBannerShown = true;
                        showUpdateBanner(newWorker);
                    }
                });
            });
        })
        .catch(function() {});

    navigator.serviceWorker.addEventListener('controllerchange', function() {
        if (updateBannerShown) window.location.reload();
    });

    function showUpdateBanner(worker) {
        var banner = document.createElement('div');
        banner.id = 'oha-spwa-update-banner';
        banner.innerHTML =
            '<span>A new version of the OHA app is available.</span>' +
            '<button id="oha-spwa-update-btn">Refresh</button>' +
            '<button id="oha-spwa-update-dismiss" aria-label="Dismiss">&#x2715;</button>';
        document.body.appendChild(banner);

        document.getElementById('oha-spwa-update-btn').addEventListener('click', function() {
            worker.postMessage({ action: 'SKIP_WAITING' });
        });
        document.getElementById('oha-spwa-update-dismiss').addEventListener('click', function() {
            banner.remove();
        });
    }

    // --- OneSignal Initialization ---

    if (ohaSpwaConfig.pushEnabled !== '1' || !ohaSpwaConfig.appId) return;

    window.OneSignalDeferred = window.OneSignalDeferred || [];
    OneSignalDeferred.push(function(OneSignal) {
        OneSignal.init({
            appId: ohaSpwaConfig.appId,
            serviceWorkerPath: '/oha-site-sw.js',
            serviceWorkerParam: { scope: '/' },
            notifyButton: { enable: false },
            promptOptions: {
                slidedown: { prompts: [] },
            },
        }).then(function() {
            OneSignal.User.PushSubscription.addEventListener('change', function(event) {
                window.ohaPushSubscribed = event.current.optedIn;
            });
            window.ohaPushSubscribed = OneSignal.User.PushSubscription.optedIn;

            if (typeof window.ohaSpwaPushReady === 'function') {
                window.ohaSpwaPushReady(OneSignal);
            }
        }).catch(function() {});
    });

})();
