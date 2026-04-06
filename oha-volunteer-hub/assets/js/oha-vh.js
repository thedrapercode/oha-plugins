(function($){
    function notify($form, msg, ok){
        var $n = $('<div class="oha-vh-note"></div>').text(msg);
        if (ok) $n.addClass('ok'); else $n.addClass('err');
        $form.append($n);
        setTimeout(function(){ $n.fadeOut(300, function(){ $(this).remove(); }); }, 2500);
    }
    $(document).on('submit', '.oha-vh-update-form', function(e){
        e.preventDefault();
        var $f = $(this);
        $.post(OHA_VH_AJAX.ajaxurl, $f.serialize(), function(res){
            if (res && res.success){
                notify($f, 'Saved', true);
                // Update row values
                var $tr = $f.closest('tr');
                $tr.find('td:nth-child(4) .oha-vh-bar span').css('width', $f.find('[name="progress"]').val() + '%');
                $tr.find('td:nth-child(4) small').text($f.find('[name="progress"]').val() + '%');
                $tr.find('td:nth-child(5)').text($f.find('[name="status"]').val());
            } else {
                notify($f, (res && res.data && res.data.message) ? res.data.message : 'Error', false);
            }
        });
    });

    $(document).on('submit', '.oha-vh-admin-update', function(e){
        e.preventDefault();
        var $f = $(this);
        $.post(OHA_VH_AJAX.ajaxurl, $f.serialize(), function(res){
            if (res && res.success){
                notify($f, 'Saved', true);
                var $tr = $f.closest('tr');
                $tr.find('td:nth-child(4) .oha-vh-bar span').css('width', $f.find('[name="progress"]').val() + '%');
                $tr.find('td:nth-child(4) small').text($f.find('[name="progress"]').val() + '%');
                $tr.find('td:nth-child(7)').text($f.find('[name="status"]').val());
            } else {
                notify($f, (res && res.data && res.data.message) ? res.data.message : 'Error', false);
            }
        });
    });

    $(document).on('submit', '.oha-vh-create-form', function(e){
        e.preventDefault();
        var $f = $(this);
        $.post(OHA_VH_AJAX.ajaxurl, $f.serialize(), function(res){
            if (res && res.success){
                notify($f, 'Task created', true);
                $f[0].reset();
            } else {
                notify($f, (res && res.data && res.data.message) ? res.data.message : 'Error', false);
            }
        });
    });
})(jQuery);
