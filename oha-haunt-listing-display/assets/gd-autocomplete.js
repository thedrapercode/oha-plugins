jQuery(document).ready(function ($) {

    // Scope to GD search forms only — identified by presence of the snear field,
    // which is GD-specific. Prevents attaching to the WP native search widget.
    var gdForm   = $('form:has(input[name="snear"])').first();
    var kwInput  = gdForm.find('input[name="s"]');
    var locInput = gdForm.find('input[name="snear"]:visible');

    function attachAC(input, action) {
        $.ajax({
            url: gdAutoData.ajaxUrl,
            data: { action: action },
            dataType: 'json',
            success: function (data) {
                if (!Array.isArray(data)) return;
                input.autocomplete({
                    source: function (req, res) {
                        res($.ui.autocomplete.filter(data, req.term).slice(0, 10));
                    },
                    minLength: 2
                });
                // If user already typed before AJAX returned, fire immediately
                if (input.val().length >= 2) {
                    input.autocomplete('search', input.val());
                }
            }
        });
    }

    if (kwInput.length) {
        kwInput.one('focus', function () {
            attachAC(kwInput, 'gd_autocomplete_keywords');
        });
    }

    if (locInput.length) {
        locInput.one('focus', function () {
            attachAC(locInput, 'gd_autocomplete_locations');
        });
    }
});
