/* OHA Contact Router v1.0.0 */
(function () {
    'use strict';

    var wrap = document.querySelector('.oha-cr-wrap');
    if (!wrap) return;

    var panel     = document.getElementById('oha-cr-panel');
    var panelTitle = document.getElementById('oha-cr-panel-title');
    var closeBtn  = document.getElementById('oha-cr-panel-close');
    var form      = document.getElementById('oha-cr-form');
    var catInput  = document.getElementById('oha-cr-category');
    var submitBtn = document.getElementById('oha-cr-submit');
    var sending   = document.getElementById('oha-cr-sending');
    var result    = document.getElementById('oha-cr-result');
    var cards     = wrap.querySelectorAll('.oha-cr-card');

    function openPanel(slug, label) {
        catInput.value      = slug;
        panelTitle.textContent = label;

        cards.forEach(function (c) {
            var active = c.dataset.category === slug;
            c.classList.toggle('is-active', active);
            c.setAttribute('aria-expanded', active ? 'true' : 'false');
        });

        clearResult();
        panel.classList.add('is-open');
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function closePanel() {
        panel.classList.remove('is-open');
        cards.forEach(function (c) {
            c.classList.remove('is-active');
            c.setAttribute('aria-expanded', 'false');
        });
        form.reset();
        clearResult();
    }

    function clearResult() {
        result.className  = 'oha-cr-result';
        result.textContent = '';
    }

    function showResult(type, msg) {
        result.className  = 'oha-cr-result is-' + type;
        result.textContent = msg;
    }

    function setSubmitting(active) {
        submitBtn.disabled = active;
        if (active) {
            sending.classList.add('is-visible');
        } else {
            sending.classList.remove('is-visible');
        }
    }

    cards.forEach(function (card) {
        card.addEventListener('click', function () {
            if (card.classList.contains('is-active')) {
                closePanel();
            } else {
                openPanel(card.dataset.category, card.dataset.label);
            }
        });
    });

    closeBtn.addEventListener('click', closePanel);

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var name    = document.getElementById('oha-cr-name').value.trim();
        var email   = document.getElementById('oha-cr-email').value.trim();
        var message = document.getElementById('oha-cr-message').value.trim();
        var cat     = catInput.value;

        if (!name || !email || !message) {
            showResult('error', 'All fields are required.');
            return;
        }

        var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRe.test(email)) {
            showResult('error', 'Please enter a valid email address.');
            return;
        }

        clearResult();
        setSubmitting(true);

        var data = new FormData();
        data.append('action',   'oha_contact_submit');
        data.append('nonce',    ohaCR.nonce);
        data.append('name',     name);
        data.append('email',    email);
        data.append('message',  message);
        data.append('category', cat);

        fetch(ohaCR.ajax_url, { method: 'POST', body: data })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                setSubmitting(false);
                if (res.success) {
                    showResult('success', res.data.message);
                    form.reset();
                } else {
                    showResult('error', (res.data && res.data.message) || 'Something went wrong. Please try again.');
                }
            })
            .catch(function () {
                setSubmitting(false);
                showResult('error', 'A network error occurred. Please try again.');
            });
    });
})();
