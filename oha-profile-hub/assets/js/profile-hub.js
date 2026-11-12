(function () {
    'use strict';

    var panel   = document.getElementById('oha-prohub-panel');
    var iframe  = document.getElementById('oha-prohub-panel-iframe');
    var backBtn = document.getElementById('oha-prohub-panel-back');
    var titleEl = document.getElementById('oha-prohub-panel-title');

    if (!panel || !iframe || !backBtn) return;

    function openPanel(url, title) {
        if (titleEl) titleEl.textContent = title || '';
        // Append oha_panel=1 so the loaded page strips Kadence chrome
        iframe.src = url + (url.indexOf('?') !== -1 ? '&' : '?') + 'oha_panel=1';
        panel.classList.add('oha-panel-open');
        panel.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closePanel() {
        panel.classList.remove('oha-panel-open');
        panel.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        // Clear src after transition so the old page doesn't flash on re-open
        setTimeout(function () {
            if (!panel.classList.contains('oha-panel-open')) {
                iframe.src = '';
                if (titleEl) titleEl.textContent = '';
            }
        }, 320);
    }

    // Wire up action row buttons
    var actionBtns = document.querySelectorAll('[data-panel-url]');
    actionBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            openPanel(btn.getAttribute('data-panel-url'), btn.getAttribute('data-panel-title') || '');
        });
    });

    backBtn.addEventListener('click', closePanel);

    // ESC key closes
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && panel.classList.contains('oha-panel-open')) {
            closePanel();
        }
    });
}());
