(function () {
    'use strict';

    var panel      = document.getElementById('oha-prohub-panel');
    var iframe     = document.getElementById('oha-prohub-panel-iframe');
    var backBtn    = document.getElementById('oha-prohub-panel-back');
    var navBtns    = document.querySelectorAll('.oha-prohub-panel-nav-btn');

    if (!panel || !iframe || !backBtn) return;

    function setNavActive(type) {
        navBtns.forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-type') === type);
        });
    }

    function loadUrl(url) {
        iframe.src = url + (url.indexOf('?') !== -1 ? '&' : '?') + 'oha_panel=1';
    }

    function openPanel(url, type) {
        loadUrl(url);
        setNavActive(type || 'account');
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
                setNavActive('account');
            }
        }, 320);
    }

    // Dashboard action row buttons open the panel
    document.querySelectorAll('[data-panel-url]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openPanel(
                btn.getAttribute('data-panel-url'),
                btn.getAttribute('data-panel-type') || 'account'
            );
        });
    });

    // Panel nav tabs switch the loaded section
    navBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url  = btn.getAttribute('data-url');
            var type = btn.getAttribute('data-type');
            if (url) {
                loadUrl(url);
                setNavActive(type);
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
