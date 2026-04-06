/* OHA Education Hub - Frontend JS
   Version: 1.0.0 */
(function () {
    'use strict';

    var modal = document.getElementById('oha-edu-modal');
    if (!modal) return;

    var modalClose       = modal.querySelector('.oha-edu-modal-close');
    var modalHeadshot    = modal.querySelector('.oha-edu-modal-headshot');
    var modalPlaceholder = modal.querySelector('.oha-edu-modal-headshot-placeholder');
    var modalInitials    = modal.querySelector('.oha-edu-modal-initials');
    var modalInstructor  = modal.querySelector('.oha-edu-modal-instructor');
    var modalTitle       = modal.querySelector('.oha-edu-modal-title');
    var modalTime        = modal.querySelector('.oha-edu-modal-time');
    var modalRoom        = modal.querySelector('.oha-edu-modal-room');
    var modalLevel       = modal.querySelector('.oha-edu-modal-level');
    var modalDesc        = modal.querySelector('.oha-edu-modal-desc');
    var modalMatWrap     = modal.querySelector('.oha-edu-modal-materials-wrap');
    var modalMaterials   = modal.querySelector('.oha-edu-modal-materials');

    // ── Modal open/close ──────────────────────────────────────────────────────

    function openModal(data) {
        // Headshot or placeholder
        if (data.headshot) {
            modalHeadshot.src = data.headshot;
            modalHeadshot.alt = data.instructor || '';
            modalHeadshot.hidden = false;
            modalPlaceholder.hidden = true;
        } else {
            modalHeadshot.hidden = true;
            modalPlaceholder.hidden = false;
            modalInitials.textContent = (data.instructor || 'OHA').charAt(0).toUpperCase();
        }

        // Instructor + title
        modalInstructor.textContent = data.instructor || '';
        modalTitle.textContent = data.title || '';

        // Meta chips — hide when empty
        modalTime.textContent  = data.time  || '';
        modalRoom.textContent  = data.room  || '';
        modalLevel.textContent = data.level || '';
        modalTime.hidden  = !data.time;
        modalRoom.hidden  = !data.room;
        modalLevel.hidden = !data.level;

        // Description
        modalDesc.textContent = data.desc || '';

        // Materials (optional section)
        if (data.materials) {
            modalMaterials.textContent = data.materials;
            modalMatWrap.hidden = false;
        } else {
            modalMatWrap.hidden = true;
        }

        modal.hidden = false;
        document.body.style.overflow = 'hidden';

        if (modalClose) modalClose.focus();
    }

    function closeModal() {
        modal.hidden = true;
        document.body.style.overflow = '';
    }

    // Open via delegated click on .oha-edu-details-btn
    document.addEventListener('click', function (e) {
        // View Details button
        var btn = e.target.closest('.oha-edu-details-btn');
        if (btn) {
            e.preventDefault();
            var raw = btn.getAttribute('data-session');
            if (!raw) return;
            try {
                openModal(JSON.parse(raw));
            } catch (err) {
                console.warn('OHA Education Hub: failed to parse session data', err);
            }
            return;
        }

        // Close button
        if (e.target.closest('.oha-edu-modal-close')) {
            closeModal();
            return;
        }

        // Click on backdrop (overlay itself, not modal box)
        if (e.target === modal) {
            closeModal();
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });

    // ── FAQ Accordion ─────────────────────────────────────────────────────────

    document.querySelectorAll('.oha-edu-faq-q').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var expanded = btn.getAttribute('aria-expanded') === 'true';
            var answerId = btn.getAttribute('aria-controls');
            var answer   = answerId ? document.getElementById(answerId) : null;

            btn.setAttribute('aria-expanded', String(!expanded));
            if (answer) answer.hidden = expanded;
        });
    });

}());
