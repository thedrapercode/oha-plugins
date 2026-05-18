/**
 * OHA Job Board Lite — Admin JS
 * Version: 0.4.0
 * Handles: GD place autocomplete in WP admin metabox
 */
(function () {
  'use strict';

  function makeSearch(el) {
    if (!el) return;

    var targetSel = el.getAttribute('data-target');
    var target    = targetSel ? document.querySelector(targetSel) : null;

    var timer = null;
    var box   = document.createElement('div');

    // Minimal inline styles for admin context (no frontend CSS loaded on all pages)
    box.style.cssText = [
      'position:absolute',
      'z-index:100000',
      'background:#fff',
      'border:1px solid #c3c4c7',
      'border-radius:4px',
      'box-shadow:0 4px 12px rgba(0,0,0,0.15)',
      'max-height:240px',
      'overflow-y:auto',
      'display:none',
      'min-width:220px',
      'top:100%',
      'left:0',
    ].join(';');

    var parent = el.parentNode;
    if (parent && getComputedStyle(parent).position === 'static') {
      parent.style.position = 'relative';
    }
    if (parent) parent.appendChild(box);

    function fetchResults(q) {
      var url = OJBConfig.ajax
        + '?action=oha_search_places'
        + '&nonce=' + encodeURIComponent(OJBConfig.nonce)
        + '&q='     + encodeURIComponent(q);

      fetch(url, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (items) {
          box.innerHTML = '';
          if (!items || !items.length) { box.style.display = 'none'; return; }

          items.forEach(function (it) {
            var a = document.createElement('a');
            a.href        = '#';
            a.textContent = it.text;
            a.style.cssText = 'display:block;padding:8px 12px;color:#1d2327;text-decoration:none;font-size:13px;border-bottom:1px solid #f0f0f1;';

            a.addEventListener('mouseenter', function () { this.style.background = '#f0f6fc'; });
            a.addEventListener('mouseleave', function () { this.style.background = ''; });

            a.addEventListener('click', function (ev) {
              ev.preventDefault();
              if (target) target.value = it.id;
              el.value          = it.text;
              box.style.display = 'none';
            });
            box.appendChild(a);
          });

          box.style.width   = el.offsetWidth + 'px';
          box.style.display = 'block';
        })
        .catch(function () { box.style.display = 'none'; });
    }

    el.addEventListener('input', function () {
      var q = el.value.trim();
      clearTimeout(timer);
      if (q.length < 2) { box.style.display = 'none'; return; }
      timer = setTimeout(function () { fetchResults(q); }, 260);
    });

    document.addEventListener('click', function (e) {
      if (e.target !== el && !box.contains(e.target)) {
        box.style.display = 'none';
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.ojb-place-search').forEach(makeSearch);
  });

})();
