(function ($) {
    'use strict';

    var container = $('#oha-convention-survey');
    if (!container.length) return;

    var pages       = container.find('.oha-conv-page');
    var surveyType  = container.data('survey-type');
    var currentPage = 0;

    // Gate config: { "q_ID": { for: pageGroupInt, value: "Yes" }, ... }
    // Injected by PHP as a global before this script runs.
    var gateConfig = (typeof oha_conv_gate_config !== 'undefined') ? oha_conv_gate_config : {};

    // Track which page groups are skipped. Key = page_group int, value = true/false.
    var skippedGroups = {};

    // Initialize all conditional pages as skipped until gate is answered.
    pages.each(function () {
        var pg = parseInt($(this).data('page-group'), 10);
        if (pg && $(this).data('conditional')) {
            skippedGroups[pg] = true;
        }
    });

    // -------------------------------------------------------------------------
    // Gate logic: listen for changes on any gate question
    // -------------------------------------------------------------------------
    container.on('change', 'input[type="radio"]', function () {
        var qGroup = $(this).closest('[data-gate-for]');
        if (!qGroup.length) return;

        var gateFor  = parseInt(qGroup.data('gate-for'), 10);
        var gateVal  = qGroup.data('gate-value');
        var selected = qGroup.find('input[type="radio"]:checked').val();

        skippedGroups[gateFor] = (selected !== gateVal);
        updateTotalDisplay();
    });

    // -------------------------------------------------------------------------
    // Navigation helpers
    // -------------------------------------------------------------------------

    function pageGroup(idx) {
        return parseInt(pages.eq(idx).data('page-group'), 10) || 0;
    }

    function isSkipped(idx) {
        var pg = pageGroup(idx);
        return pg > 0 && skippedGroups[pg] === true;
    }

    function nextPageIdx(from) {
        for (var i = from + 1; i < pages.length; i++) {
            if (!isSkipped(i)) return i;
        }
        return -1;
    }

    function prevPageIdx(from) {
        for (var i = from - 1; i >= 0; i--) {
            if (!isSkipped(i)) return i;
        }
        return -1;
    }

    function visiblePageCount() {
        var n = 0;
        pages.each(function (i) { if (!isSkipped(i)) n++; });
        return n;
    }

    function visiblePageIndex(absIdx) {
        var n = 0;
        for (var i = 0; i <= absIdx; i++) {
            if (!isSkipped(i)) n++;
        }
        return n;
    }

    function updateTotalDisplay() {
        container.find('.oha-conv-total-pages').text(visiblePageCount());
    }

    function showPage(idx) {
        pages.hide().eq(idx).show();
        currentPage = idx;

        var prevBtn   = container.find('.oha-conv-prev');
        var nextBtn   = container.find('.oha-conv-next');
        var submitBtn = container.find('.oha-conv-submit');
        var isLast    = nextPageIdx(idx) === -1;

        prevBtn.toggle(prevPageIdx(idx) !== -1);
        nextBtn.toggle(!isLast);
        submitBtn.toggle(isLast);

        var pct = Math.round((visiblePageIndex(idx) / visiblePageCount()) * 100);
        container.find('.oha-conv-progress-fill').css('width', pct + '%');
        container.find('.oha-conv-current-page').text(visiblePageIndex(idx));
        updateTotalDisplay();

        container.find('.oha-conv-messages').empty();
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    function validatePage(idx) {
        var page = pages.eq(idx);

        // Email page
        if (idx === 0) {
            var email = $('#oha-conv-email').val().trim();
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                return { valid: false, msg: 'Please enter a valid email address.' };
            }
            return { valid: true };
        }

        // Question pages: check required fields
        var valid = true, msg = '';
        page.find('.oha-field-required').each(function () {
            var group     = $(this);
            var answered  = false;
            var radios    = group.find('input[type="radio"]');
            var checks    = group.find('input[type="checkbox"]');
            var textEl    = group.find('input[type="text"], textarea');

            if (radios.length)  answered = radios.filter(':checked').length > 0;
            else if (checks.length) answered = checks.filter(':checked').length > 0;
            else if (textEl.length) answered = textEl.val().trim() !== '';

            if (!answered) {
                valid = false;
                msg   = 'Please answer all required questions before continuing.';
                return false; // break
            }
        });

        return { valid: valid, msg: msg };
    }

    function showMsg(text, isError) {
        container.find('.oha-conv-messages').html(
            '<div class="oha-survey-message' + (isError ? ' oha-message-error' : ' oha-message-success') + '">' +
            text + '</div>'
        );
    }

    // -------------------------------------------------------------------------
    // Answer collection
    // -------------------------------------------------------------------------

    function collectAnswers() {
        var answers = {};
        container.find('.oha-conv-page').not(':first').each(function () {
            $(this).find('[data-question-id]').each(function () {
                var group = $(this);
                var qid   = group.data('question-id');

                var checked = group.find('input[type="radio"]:checked');
                if (checked.length) { answers[qid] = checked.val(); return; }

                var boxes = group.find('input[type="checkbox"]:checked');
                if (boxes.length) { answers[qid] = boxes.map(function(){ return this.value; }).get(); return; }

                var textEl = group.find('textarea, input[type="text"]');
                if (textEl.length && textEl.val().trim() !== '') {
                    answers[qid] = textEl.val().trim();
                }
            });
        });
        return answers;
    }

    function collectSkippedGroups() {
        var skipped = [];
        $.each(skippedGroups, function (pg, isSkip) {
            if (isSkip) skipped.push(parseInt(pg, 10));
        });
        return skipped;
    }

    // -------------------------------------------------------------------------
    // Navigation events
    // -------------------------------------------------------------------------

    container.on('click', '.oha-conv-next', function () {
        var result = validatePage(currentPage);
        if (!result.valid) { showMsg(result.msg, true); return; }
        var next = nextPageIdx(currentPage);
        if (next === -1) return;
        showPage(next);
        $('html, body').animate({ scrollTop: container.offset().top - 40 }, 200);
    });

    container.on('click', '.oha-conv-prev', function () {
        var prev = prevPageIdx(currentPage);
        if (prev === -1) return;
        showPage(prev);
        $('html, body').animate({ scrollTop: container.offset().top - 40 }, 200);
    });

    container.on('click', '.oha-conv-submit', function () {
        var result = validatePage(currentPage);
        if (!result.valid) { showMsg(result.msg, true); return; }

        var submitBtn = $(this);
        submitBtn.prop('disabled', true).text('Submitting...');

        $.post(oha_conv_ajax.ajax_url, {
            action:         'oha_convention_survey_submit',
            nonce:          oha_conv_ajax.nonce,
            email:          $('#oha-conv-email').val().trim(),
            survey_type:    surveyType,
            responses:      collectAnswers(),
            skipped_groups: collectSkippedGroups(),
        }, function (res) {
            if (res.success) {
                container.find('.oha-survey-header, .oha-survey-content, .oha-survey-navigation, .oha-conv-messages').hide();
                container.find('.oha-survey-success').show();
                container.closest('.oha-survey-trust-info').hide();
            } else {
                var errMsg = (res.data && res.data.message) ? res.data.message : 'An error occurred. Please try again.';
                showMsg(errMsg, true);
                submitBtn.prop('disabled', false).html('<span class="oha-btn-icon">&#10003;</span> Submit Survey');
            }
        }).fail(function () {
            showMsg('A network error occurred. Please check your connection and try again.', true);
            submitBtn.prop('disabled', false).html('<span class="oha-btn-icon">&#10003;</span> Submit Survey');
        });
    });

    // -------------------------------------------------------------------------
    // Init
    // -------------------------------------------------------------------------

    showPage(0);

})(jQuery);
