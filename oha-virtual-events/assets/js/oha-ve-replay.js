/* OHA Virtual Events - Replay card interactions
 *
 * Loaded via wp_enqueue_script so it runs on the initial page load,
 * including when the My Account page is inside an iframe
 * ([oha_uwp_account_inline] shortcode). Event delegation on document
 * catches clicks on cards that UsersWP injects via AJAX tab switching.
 */
(function () {
    'use strict';

    function ohaReplayInit() {
        if (window.ohaReplayEventsInit) { return; }
        window.ohaReplayEventsInit = true;

        document.addEventListener('click', function (e) {
            if (!e.target) { return; }

            /* --------------------------------------------------
               Watch Now button (.oha-replay-toggle + data-target)
               -------------------------------------------------- */
            var tb = (typeof e.target.closest === 'function')
                ? e.target.closest('.oha-replay-toggle')
                : null;
            if (tb) {
                var id = tb.getAttribute('data-target');
                var em = document.getElementById(id);
                if (!em) { return; }
                var vis = (em.style.display !== 'none');
                em.style.display = vis ? 'none' : 'block';
                var wb = document.querySelectorAll('.oha-replay-watch-btn[data-target="' + id + '"]');
                for (var i = 0; i < wb.length; i++) {
                    wb[i].textContent = vis ? 'Watch Now' : 'Close Video';
                }
                return;
            }

            /* --------------------------------------------------
               More Info / Less Info button (.oha-replay-info-btn)
               Toggles an inline panel below the card -- avoids
               position:fixed which breaks inside auto-height iframes.
               -------------------------------------------------- */
            var ib = (typeof e.target.closest === 'function')
                ? e.target.closest('.oha-replay-info-btn')
                : null;
            if (ib) {
                var card = ib.closest('.oha-replay-card');
                if (!card) { return; }
                var panel = card.querySelector('.oha-replay-card__info-panel');
                if (!panel) { return; }
                var isOpen = (panel.style.display !== 'none');
                panel.style.display = isOpen ? 'none' : 'block';
                ib.textContent = isOpen ? 'More Info' : 'Less Info';
                return;
            }
        });
    }

    ohaReplayInit();

}());
