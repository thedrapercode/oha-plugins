(function () {
    'use strict';

    var tabBtns = document.querySelectorAll('.oha-phub-tab');
    var panels  = document.querySelectorAll('.oha-phub-panel');

    function activateTab(slug) {
        tabBtns.forEach(function (btn) {
            var active = btn.getAttribute('data-tab') === slug;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach(function (pnl) {
            pnl.classList.toggle('active', pnl.getAttribute('data-panel') === slug);
        });
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

}());
