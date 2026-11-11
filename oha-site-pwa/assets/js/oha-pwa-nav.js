(function() {
    'use strict';

    var tabs = document.querySelectorAll('#oha-pwa-nav .oha-nav-tab');
    if (!tabs.length) return;

    var current = window.location.pathname.replace(/\/+$/, '') || '/';

    for (var i = 0; i < tabs.length; i++) {
        var tab  = tabs[i];
        var link = document.createElement('a');
        link.href = tab.getAttribute('href') || '';
        var tabPath = link.pathname.replace(/\/+$/, '') || '/';

        var active = false;
        if (tabPath === '/') {
            active = current === '/';
        } else {
            active = current === tabPath || current.indexOf(tabPath + '/') === 0;
        }

        if (active) tab.classList.add('oha-nav-active');
    }

    // Nav tab tap animation
    for (var j = 0; j < tabs.length; j++) {
        (function(t) {
            t.addEventListener('click', function() {
                t.classList.remove('oha-nav-tapped');
                void t.offsetWidth;
                t.classList.add('oha-nav-tapped');
                setTimeout(function() { t.classList.remove('oha-nav-tapped'); }, 400);
            });
        })(tabs[j]);
    }
})();

// PWA App Header drawer toggle
(function() {
    var btn     = document.getElementById('oha-pwa-menu-btn');
    var overlay = document.getElementById('oha-pwa-drawer-overlay');
    var drawer  = document.getElementById('oha-pwa-drawer');
    var close   = document.getElementById('oha-pwa-drawer-close');

    if (!btn || !drawer) return;

    function openDrawer() {
        drawer.classList.add('oha-drawer-open');
        drawer.setAttribute('aria-hidden', 'false');
        if (overlay) {
            overlay.classList.add('oha-drawer-open');
            overlay.setAttribute('aria-hidden', 'false');
        }
        btn.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeDrawer() {
        drawer.classList.remove('oha-drawer-open');
        drawer.setAttribute('aria-hidden', 'true');
        if (overlay) {
            overlay.classList.remove('oha-drawer-open');
            overlay.setAttribute('aria-hidden', 'true');
        }
        btn.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    btn.addEventListener('click', openDrawer);
    if (close)   close.addEventListener('click', closeDrawer);
    if (overlay) overlay.addEventListener('click', closeDrawer);
})();
