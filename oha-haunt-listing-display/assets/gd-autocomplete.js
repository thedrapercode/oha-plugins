jQuery(document).ready(function ($) {

    // Keyword autocomplete on GeoDirectory search field (input[name="s"])
    var kwInput = $('input[name="s"]').first();
    if (kwInput.length) {
        $.ajax({
            url: gdAutoData.ajaxUrl,
            data: { action: 'gd_autocomplete_keywords' },
            dataType: 'json',
            success: function (data) {
                if (!Array.isArray(data)) return;
                kwInput.autocomplete({
                    source: function (req, res) {
                        res($.ui.autocomplete.filter(data, req.term).slice(0, 10));
                    },
                    minLength: 2
                });
            }
        });
    }

    // Location autocomplete on GeoDirectory near field (input[name="snear"])
    var locInput = $('input[name="snear"]:visible').first();
    if (locInput.length) {
        $.ajax({
            url: gdAutoData.ajaxUrl,
            data: { action: 'gd_autocomplete_locations' },
            dataType: 'json',
            success: function (data) {
                if (!Array.isArray(data)) return;
                locInput.autocomplete({
                    source: function (req, res) {
                        res($.ui.autocomplete.filter(data, req.term).slice(0, 10));
                    },
                    minLength: 2
                });
            }
        });
    }
});
