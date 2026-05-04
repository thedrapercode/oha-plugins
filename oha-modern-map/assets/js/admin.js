/**
 * OHA Modern Map Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Initialize color pickers
        if ($.fn.wpColorPicker) {
            $('.oha-color-picker').wpColorPicker();
        }
        
        // Show/hide clustering options based on enabled checkbox
        $('#clustering_enabled').on('change', function() {
            const $clusterOptions = $(this).closest('tr').nextAll('tr').slice(0, 2);
            
            if ($(this).is(':checked')) {
                $clusterOptions.show();
            } else {
                $clusterOptions.hide();
            }
        }).trigger('change');
    });
    
})(jQuery);
