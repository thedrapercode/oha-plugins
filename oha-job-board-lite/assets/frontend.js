/**
 * OHA Job Board Lite — Frontend JS
 * Version: 0.4.0
 * Handles: GD place autocomplete, client-side filtering, apply toggle
 */
(function () {
  'use strict';

  /* =========================================================
   * Haunt / GD Place Autocomplete
   * ======================================================= */
  function makeSearch(el) {
    if (!el) return;

    // Target hidden input: prefer data-target attr, then sibling hidden input
    var targetSel = el.getAttribute('data-target');
    var target = targetSel ? document.querySelector(targetSel) : null;
    if (!target) {
      var siblings = el.parentNode ? el.parentNode.querySelectorAll('input[type="hidden"]') : [];
      if (siblings.length) target = siblings[0];
    }

    var timer = null;
    var box   = document.createElement('div');
    box.className = 'ojb-autocomplete';

    // Ensure parent is positioned so dropdown aligns correctly
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
            var a       = document.createElement('a');
            a.href      = '#';
            a.textContent = it.text;
            a.addEventListener('click', function (ev) {
              ev.preventDefault();
              if (target) target.value = it.id;
              el.value         = it.text;
              box.style.display = 'none';
            });
            box.appendChild(a);
          });

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

  /* =========================================================
   * Apply toggle — show/hide inline email form on card
   * ======================================================= */
  function initApplyToggles() {
    document.querySelectorAll('.ojb-apply-toggle').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var jobId = this.getAttribute('data-job');
        var wrap  = document.getElementById('ojb-apply-' + jobId);
        if (!wrap) return;

        var isOpen = wrap.style.display !== 'none';
        wrap.style.display = isOpen ? 'none' : 'block';

        // Toggle button label — keep original text or set Cancel
        if (isOpen) {
          this.textContent = this.getAttribute('data-original-text') || 'Apply';
          this.setAttribute('aria-expanded', 'false');
        } else {
          if (!this.getAttribute('data-original-text')) {
            this.setAttribute('data-original-text', this.textContent);
          }
          this.textContent = 'Cancel';
          this.setAttribute('aria-expanded', 'true');
          // Scroll to form if off-screen
          wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      });
      btn.setAttribute('aria-expanded', 'false');
      btn.setAttribute('type', 'button'); // prevent accidental form submit
    });
  }

  /* =========================================================
   * Client-side live filtering (search + role + employment)
   * Fast because it runs against already-rendered cards.
   * ======================================================= */
  function initFilters() {
    var grid  = document.getElementById('ojb-grid');
    var srch  = document.getElementById('ojb-search');
    var fRole = document.getElementById('ojb-filter-role');
    var fEmp  = document.getElementById('ojb-filter-employment');
    if (!grid) return;

    var filterTimer  = null;
    var emptyMarker  = null; // dynamically injected "no results" node

    function applyFilters() {
      var q   = srch  ? srch.value.trim().toLowerCase()  : '';
      var rol = fRole ? fRole.value.toLowerCase()         : '';
      var emp = fEmp  ? fEmp.value.toLowerCase()          : '';

      var cards   = grid.querySelectorAll('.ojb-card');
      var visible = 0;

      cards.forEach(function (card) {
        var cardRole = (card.getAttribute('data-role')       || '').toLowerCase();
        var cardEmp  = (card.getAttribute('data-employment') || '').toLowerCase();
        var text     = card.textContent.toLowerCase();

        var match = true;
        if (rol && cardRole !== rol)       match = false;
        if (emp && cardEmp  !== emp)       match = false;
        if (q   && text.indexOf(q) === -1) match = false;

        card.style.display = match ? '' : 'none';
        if (match) visible++;
      });

      // Manage dynamic empty state
      if (visible === 0) {
        if (!emptyMarker) {
          emptyMarker = document.createElement('div');
          emptyMarker.className = 'ojb-empty ojb-empty--dynamic';
          emptyMarker.innerHTML = '<p>No jobs match your filters.</p><p>Try clearing the search or selecting a different role type.</p>';
          grid.appendChild(emptyMarker);
        }
      } else {
        if (emptyMarker) {
          emptyMarker.parentNode.removeChild(emptyMarker);
          emptyMarker = null;
        }
      }
    }

    if (srch) {
      srch.addEventListener('input', function () {
        clearTimeout(filterTimer);
        filterTimer = setTimeout(applyFilters, 200);
      });
    }
    if (fRole) fRole.addEventListener('change', applyFilters);
    if (fEmp)  fEmp.addEventListener('change', applyFilters);
  }

  /* =========================================================
   * Applicant panel toggle (My Jobs — click app count to expand)
   * ======================================================= */
  function initApplicantPanels() {
    document.querySelectorAll('.ojb-apps-toggle').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var jobId  = this.getAttribute('data-job');
        var panel  = document.getElementById('ojb-applicants-' + jobId);
        var label  = this.getAttribute('data-label');
        if (!panel) return;

        var isOpen = panel.style.display !== 'none';
        panel.style.display = isOpen ? 'none' : 'block';
        this.textContent    = isOpen ? label : 'Hide Applicants';

        // Mark item so CSS can remove bottom margin when panel is open
        var item = panel.previousElementSibling;
        if (item && item.classList.contains('ojb-my-job-item')) {
          item.style.marginBottom = isOpen ? '' : '0';
          item.style.borderRadius = isOpen ? '' : 'var(--ojb-radius) var(--ojb-radius) 0 0';
          item.style.borderBottom = isOpen ? '' : 'none';
        }
      });
    });
  }

  /* =========================================================
   * Applicant status AJAX (Shortlist / Decline toggle)
   * ======================================================= */
  function initAppStatus() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.ojb-app-status-btn');
      if (!btn || !btn.getAttribute('data-app')) return;
      e.preventDefault();

      var appId   = btn.getAttribute('data-app');
      var card    = btn.closest('.ojb-applicant-card');
      var current = card ? card.getAttribute('data-status') : 'new';

      var newStatus;
      if (btn.classList.contains('ojb-app-status-btn--shortlist')) {
        newStatus = (current === 'shortlisted') ? '' : 'shortlisted';
      } else {
        newStatus = (current === 'declined') ? '' : 'declined';
      }

      var form = new FormData();
      form.append('action',   'oha_app_status');
      form.append('nonce',    OJBConfig.app_nonce);
      form.append('app_id',   appId);
      form.append('status',   newStatus);

      fetch(OJBConfig.ajax, { method: 'POST', credentials: 'same-origin', body: form })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (!res.success || !card) return;

          var resolved = res.data.status; // 'new' | 'shortlisted' | 'declined'
          card.setAttribute('data-status', resolved === 'new' ? '' : resolved);

          // Update status badge
          var badge = card.querySelector('.ojb-app-status-badge');
          if (badge) {
            var labelMap = { shortlisted: 'Shortlisted', declined: 'Declined', new: 'New' };
            var classMap = {
              shortlisted: 'ojb-badge--active',
              declined:    'ojb-badge--denied',
              new:         'ojb-badge--draft',
            };
            badge.textContent = labelMap[resolved] || 'New';
            badge.className   = 'ojb-app-status-badge ojb-badge ' + (classMap[resolved] || 'ojb-badge--draft');
          }

          // Update active state on both buttons
          card.querySelectorAll('.ojb-app-status-btn').forEach(function (b) {
            var isShortlist = b.classList.contains('ojb-app-status-btn--shortlist');
            var active = isShortlist ? resolved === 'shortlisted' : resolved === 'declined';
            b.classList.toggle('ojb-app-status-btn--active', active);
            if (isShortlist) b.textContent = (resolved === 'shortlisted') ? 'Shortlisted' : 'Shortlist';
            else             b.textContent = (resolved === 'declined')    ? 'Restore'     : 'Decline';
          });
        })
        .catch(function () {});
    });
  }

  /* =========================================================
   * Init on DOMContentLoaded
   * ======================================================= */
  document.addEventListener('DOMContentLoaded', function () {
    // Autocomplete on all haunt-search fields
    document.querySelectorAll('.ojb-place-search').forEach(makeSearch);

    // Apply toggles
    initApplyToggles();

    // Filter bar
    initFilters();

    // Applicant panel toggles
    initApplicantPanels();

    // Applicant status buttons
    initAppStatus();
  });

})();
