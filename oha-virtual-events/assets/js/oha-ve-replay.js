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
               More Info button (.oha-replay-info-btn)
               -------------------------------------------------- */
            var ib = (typeof e.target.closest === 'function')
                ? e.target.closest('.oha-replay-info-btn')
                : null;
            if (ib) {
                ohaReplayOpenModal(ib);
                return;
            }

            /* --------------------------------------------------
               Modal close button (.oha-replay-modal__close)
               -------------------------------------------------- */
            var cb = (typeof e.target.closest === 'function')
                ? e.target.closest('.oha-replay-modal__close')
                : null;
            if (cb) {
                ohaReplayCloseModal();
                return;
            }

            /* --------------------------------------------------
               Backdrop click closes modal
               -------------------------------------------------- */
            var mo = document.getElementById('oha-replay-modal');
            if (mo && e.target === mo) {
                ohaReplayCloseModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { ohaReplayCloseModal(); }
        });
    }

    window.ohaReplayOpenModal = function (btn) {
        var mo = document.getElementById('oha-replay-modal');
        if (!mo) { return; }

        var info;
        try {
            info = JSON.parse(btn.getAttribute('data-info'));
        } catch (x) {
            return;
        }

        function esc(s) {
            return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        mo.querySelector('.oha-replay-modal__title').textContent = info.title || '';

        var m = '';
        if (info.type)      { m += '<span class="oha-replay-modal__meta-type">'     + esc(info.type)      + '</span>'; }
        if (info.duration)  { m += '<span class="oha-replay-modal__meta-duration">' + esc(info.duration)  + '</span>'; }
        if (info.presenter) { m += '<span>By ' + esc(info.presenter) + '</span>'; }
        if (info.date)      { m += '<span>'    + esc(info.date)      + '</span>'; }
        mo.querySelector('.oha-replay-modal__meta').innerHTML = m;

        mo.querySelector('.oha-replay-modal__description').textContent = info.description || '';

        var f    = '';
        var rurl = info.resources || '';
        if (rurl && /^https?:\/\//i.test(rurl)) {
            f += '<a href="' + esc(rurl) + '" target="_blank" rel="noopener">Download Resources</a>';
        }
        mo.querySelector('.oha-replay-modal__footer').innerHTML = f;

        mo.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    window.ohaReplayCloseModal = function () {
        var mo = document.getElementById('oha-replay-modal');
        if (mo) { mo.style.display = 'none'; }
        document.body.style.overflow = '';
    };

    ohaReplayInit();

}());
