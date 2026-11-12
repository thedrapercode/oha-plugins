(function () {
    'use strict';

    // ----------------------------------------------------------------
    // IN-PAGE TAB SWITCHING
    // ----------------------------------------------------------------
    var tabBtns  = document.querySelectorAll('.oha-phub-tab');
    var panels   = document.querySelectorAll('.oha-phub-panel');

    function activateTab(slug) {
        tabBtns.forEach(function (btn) {
            var active = btn.getAttribute('data-tab') === slug;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach(function (pnl) {
            pnl.classList.toggle('active', pnl.getAttribute('data-panel') === slug);
        });
        // Persist to sessionStorage so a page reload stays on the same tab
        try { sessionStorage.setItem('oha_phub_tab', slug); } catch (e) {}
    }

    tabBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            activateTab(btn.getAttribute('data-tab'));
        });
    });

    // Restore last-used tab on page load
    (function () {
        var saved = '';
        try { saved = sessionStorage.getItem('oha_phub_tab') || ''; } catch (e) {}
        if ( saved && document.querySelector('.oha-phub-tab[data-tab="' + saved + '"]') ) {
            activateTab(saved);
        }
    }());

    // ----------------------------------------------------------------
    // SLIDE-IN FORM PANEL
    // ----------------------------------------------------------------
    var panel   = document.getElementById('oha-prohub-panel');
    var iframe  = document.getElementById('oha-prohub-panel-iframe');
    var backBtn = document.getElementById('oha-prohub-panel-back');
    var navBtns = document.querySelectorAll('.oha-prohub-panel-nav-btn');

    if (!panel || !iframe || !backBtn) return;

    function setPanelNavActive(type) {
        navBtns.forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-type') === type);
        });
    }

    function loadPanelUrl(url) {
        iframe.src = url + (url.indexOf('?') !== -1 ? '&' : '?') + 'oha_panel=1';
    }

    function openPanel(url, type) {
        loadPanelUrl(url);
        setPanelNavActive(type || 'account');
        panel.classList.add('oha-panel-open');
        panel.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closePanel() {
        panel.classList.remove('oha-panel-open');
        panel.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        setTimeout(function () {
            if (!panel.classList.contains('oha-panel-open')) {
                iframe.src = '';
                setPanelNavActive('account');
            }
        }, 320);
    }

    // Dashboard action rows
    document.querySelectorAll('[data-panel-url]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openPanel(
                btn.getAttribute('data-panel-url'),
                btn.getAttribute('data-panel-type') || 'account'
            );
        });
    });

    // Panel internal nav tabs
    navBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url  = btn.getAttribute('data-url');
            var type = btn.getAttribute('data-type');
            if (url) {
                loadPanelUrl(url);
                setPanelNavActive(type);
            }
        });
    });

    backBtn.addEventListener('click', closePanel);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && panel.classList.contains('oha-panel-open')) {
            closePanel();
        }
    });
}());
