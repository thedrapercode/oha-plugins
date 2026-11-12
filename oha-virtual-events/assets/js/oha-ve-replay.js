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
 *
 * Both the video embed and the info panel are position:absolute overlays
 * inside the thumbnail container. Opening one closes the other so they
 * never stack.
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
               Shows video overlay over thumbnail; closes info if open.
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
                // When opening video, close info panel
                if (!vis) {
                    var panel = card.querySelector('.oha-replay-card__info-panel');
                    var ib = card.querySelector('.oha-replay-info-btn');
                    if (panel) { panel.style.display = 'none'; }
                    if (ib) { ib.textContent = 'More Info'; }
                }
                var wb = card.querySelectorAll('.oha-replay-watch-btn');
                for (var i = 0; i < wb.length; i++) {
                    wb[i].textContent = vis ? 'Watch Now' : 'Close Video';
                }
                return;
            }

            /* --------------------------------------------------
               More Info / Less Info button (.oha-replay-info-btn)
               Shows info overlay over thumbnail; closes video if open.
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
                // When opening info, close video embed
                if (!isOpen) {
                    var em = card.querySelector('.oha-replay-embed');
                    var wb = card.querySelectorAll('.oha-replay-watch-btn');
                    if (em) { em.style.display = 'none'; }
                    for (var i = 0; i < wb.length; i++) {
                        wb[i].textContent = 'Watch Now';
                    }
                }
                return;
            }
        });
    }

    ohaReplayInit();

}());
