/* OHA Education Hub - Admin JS
   Version: 1.0.0
   Handles FAQ repeater on the settings page. */
(function ($) {
    'use strict';

    var $container = $('#oha-edu-faq-repeater');
    var $hidden    = $('#oha-edu-faq-json');

    if (!$container.length) return;

    // Build a DOM row (safe — uses .val() instead of HTML string injection)
    function buildRow(q, a) {
        var $row = $('<div class="oha-faq-row"></div>');

        var $qWrap = $('<p></p>');
        $('<label>Question</label><br>').appendTo($qWrap);
        var $qInput = $('<input type="text" class="oha-faq-q widefat" placeholder="Question...">');
        $qInput.val(q || '');
        $qInput.appendTo($qWrap);
        $qWrap.appendTo($row);

        var $aWrap = $('<p></p>');
        $('<label>Answer <small style="font-weight:400;color:#888;">(basic HTML ok)</small></label><br>').appendTo($aWrap);
        var $aArea = $('<textarea class="oha-faq-a widefat" rows="2" placeholder="Answer..."></textarea>');
        $aArea.val(a || '');
        $aArea.appendTo($aWrap);
        $aWrap.appendTo($row);

        $('<button type="button" class="button oha-faq-remove">Remove</button>').appendTo($row);
        return $row;
    }

    function renderItems(items) {
        $container.empty();
        if (!items || !items.length) {
            $container.append('<p class="oha-faq-empty">No FAQ items yet. Click "+ Add Question" to add one.</p>');
        } else {
            $.each(items, function (i, item) {
                $container.append(buildRow(item.q, item.a));
            });
        }
        syncJson();
    }

    function getItems() {
        return $container.find('.oha-faq-row').map(function () {
            return {
                q: $(this).find('.oha-faq-q').val(),
                a: $(this).find('.oha-faq-a').val()
            };
        }).get();
    }

    function syncJson() {
        $hidden.val(JSON.stringify(getItems()));
    }

    // Initialise from existing saved JSON
    var existing = [];
    try { existing = JSON.parse($hidden.val() || '[]'); } catch (e) { existing = []; }
    renderItems(existing);

    // Add new row
    $('#oha-edu-faq-add').on('click', function () {
        $container.find('.oha-faq-empty').remove();
        $container.append(buildRow('', ''));
        $container.find('.oha-faq-row').last().find('input').focus();
        syncJson();
    });

    // Remove a row
    $container.on('click', '.oha-faq-remove', function () {
        $(this).closest('.oha-faq-row').remove();
        if (!$container.find('.oha-faq-row').length) {
            $container.append('<p class="oha-faq-empty">No FAQ items yet. Click "+ Add Question" to add one.</p>');
        }
        syncJson();
    });

    // Keep JSON in sync on every keystroke
    $container.on('input change', 'input, textarea', function () {
        syncJson();
    });

}(jQuery));
