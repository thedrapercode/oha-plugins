/* OHA Virtual Events - Replay card interactions
 *
 * Loaded via wp_enqueue_script so it runs on the initial page load.
 * Event delegation on document catches clicks regardless of how the
 * tab content is inserted (direct render, AJAX, or UWP profile tabs).
 *
 * All DOM lookups are card-relative (closest + querySelector within the
 * card) so they work in any page context, including UWP profile tabs
 * where document.getElementById can fail to locate elements that are
 * inside a tab content container.
 */
(function () {
    'use strict';

    function ohaReplayInit() {
        if (window.ohaReplayEventsInit) { return; }
        window.ohaReplayEventsInit = true;

        document.addEventListener('click', function (e) {
            if (!e.target) { return; }

            /* --------------------------------------------------
               Watch Now / thumbnail toggle (.oha-replay-toggle)
               Finds the embed and button(s) within the same card
               rather than using document.getElementById, which can
               fail inside UWP profile tab containers.
               -------------------------------------------------- */
            var tb = (typeof e.target.closest === 'function')
                ? e.target.closest('.oha-replay-toggle')
                : null;
            if (tb) {
                var card = tb.closest('.oha-replay-card');
                if (!card) { return; }
                var em = card.querySelector('.oha-replay-embed');
                if (!em) { return; }
                var vis = (em.style.display !== 'none');
                em.style.display = vis ? 'none' : 'block';
                var wb = card.querySelectorAll('.oha-replay-watch-btn');
                for (var i = 0; i < wb.length; i++) {
                    wb[i].textContent = vis ? 'Watch Now' : 'Close Video';
                }
                return;
            }

            /* --------------------------------------------------
               More Info / Less Info button (.oha-replay-info-btn)
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