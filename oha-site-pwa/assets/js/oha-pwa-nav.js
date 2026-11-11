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
})();
