(function($) {
    'use strict';

    $(document).ready(function() {

        // ─── Content Type Toggle ───
        $('input[name="content_type"]').on('change', function() {
            var val = $(this).val();
            if (val === 'html') {
                $('.jemail-content-html').show();
                $('.jemail-content-text').hide();
            } else {
                $('.jemail-content-html').hide();
                $('.jemail-content-text').show();
            }
        });

        // ─── Toggle Admin Roles ───
        $('#jemail-toggle-admin-roles').on('click', function(e) {
            e.preventDefault();
            var $box = $('.jemail-admin-roles');
            $box.toggle();
            $(this).text($box.is(':visible') ? 'Hide admin roles' : 'Show admin roles');
        });

        // ─── Count Recipients ───
        function getFilterData() {
            var roles = [];
            $('input[name="target_roles[]"]:checked').each(function() {
                roles.push($(this).val());
            });
            var swpmLevels = [];
            $('input[name="target_swpm_levels[]"]:checked').each(function() {
                swpmLevels.push($(this).val());
            });
            var uwpForms = [];
            $('input[name="target_uwp_forms[]"]:checked').each(function() {
                uwpForms.push($(this).val());
            });
            var vendorStatuses = [];
            $('input[name="target_vendor_statuses[]"]:checked').each(function() {
                vendorStatuses.push($(this).val());
            });
            var volunteerStatuses = [];
            $('input[name="target_volunteer_statuses[]"]:checked').each(function() {
                volunteerStatuses.push($(this).val());
            });
            var gdListing = $('input[name="target_gd_listing"]:checked').val() || '';
            var awardsYear = $('select[name="target_awards_year"]').val() || '';
            return {
                roles: roles,
                swpm_levels: swpmLevels,
                uwp_forms: uwpForms,
                vendor_statuses: vendorStatuses,
                volunteer_statuses: volunteerStatuses,
                gd_listing: gdListing,
                awards_year: awardsYear
            };
        }

        $('#jemail-count-btn').on('click', function() {
            var $btn = $(this);
            var $num = $('#jemail-count-number');
            $btn.prop('disabled', true).text('Counting…');
            $num.text('…');

            var filters = getFilterData();
            $.post(jemailAdmin.ajax_url, {
                action: 'jemail_count_recipients',
                nonce: jemailAdmin.nonce,
                roles: filters.roles,
                swpm_levels: filters.swpm_levels,
                uwp_forms: filters.uwp_forms,
                vendor_statuses: filters.vendor_statuses,
                volunteer_statuses: filters.volunteer_statuses,
                gd_listing: filters.gd_listing,
                awards_year: filters.awards_year
            }, function(res) {
                $btn.prop('disabled', false).text('Count Recipients');
                if (res.success) {
                    $num.text(res.data.count);
                } else {
                    $num.text('Error');
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('Count Recipients');
                $num.text('Error');
            });
        });

        // Auto-count on checkbox/radio/select change (debounced)
        var countTimer;
        $('.jemail-checkbox-list input[type="checkbox"], .jemail-checkbox-list input[type="radio"]').on('change', function() {
            clearTimeout(countTimer);
            countTimer = setTimeout(function() {
                $('#jemail-count-btn').trigger('click');
            }, 500);
        });
        $('select[name="target_awards_year"]').on('change', function() {
            clearTimeout(countTimer);
            countTimer = setTimeout(function() {
                $('#jemail-count-btn').trigger('click');
            }, 500);
        });

        // ─── Preview Recipients ───
        $('#jemail-preview-btn').on('click', function() {
            var $btn     = $(this);
            var $results = $('#jemail-preview-results');
            var $summary = $('#jemail-preview-summary');
            var $list    = $('#jemail-preview-list');

            $btn.prop('disabled', true).text('Loading…');
            $results.hide();
            $list.empty();

            var filters = getFilterData();
            $.post(jemailAdmin.ajax_url, {
                action: 'jemail_preview_recipients',
                nonce: jemailAdmin.nonce,
                roles: filters.roles,
                swpm_levels: filters.swpm_levels,
                uwp_forms: filters.uwp_forms,
                vendor_statuses: filters.vendor_statuses,
                volunteer_statuses: filters.volunteer_statuses,
                gd_listing: filters.gd_listing,
                awards_year: filters.awards_year
            }, function(res) {
                $btn.prop('disabled', false).text('👁 Preview Who Gets This');
                if (res.success && res.data) {
                    var total   = res.data.total;
                    var preview = res.data.preview;
                    var showing = preview.length;
                    $summary.html(
                        '<strong>' + total + ' total recipient' + (total !== 1 ? 's' : '') + '</strong>' +
                        (total > showing ? ' — showing first ' + showing : '')
                    );
                    if (total === 0) {
                        $list.append('<li style="color:#d63638;padding:4px 0;">⚠ No recipients match the current filters.</li>');
                    } else {
                        $.each(preview, function(i, r) {
                            var label = r.name ? r.name + ' &lt;' + r.email + '&gt;' : r.email;
                            $list.append('<li style="padding:2px 0;border-bottom:1px solid #f0f0f0;">' + label + '</li>');
                        });
                        if (total > showing) {
                            $list.append('<li style="padding:4px 0;color:#666;font-style:italic;">… and ' + (total - showing) + ' more</li>');
                        }
                    }
                    $results.show();
                } else {
                    $btn.after('<span style="color:red;font-size:12px;display:block;margin-top:4px;">Error loading preview.</span>');
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('👁 Preview Who Gets This');
            });
        });

        // ─── Send Test Email ───
        $('#jemail-send-test').on('click', function() {
            var $btn = $(this);
            var email = $('#jemail-test-email').val();
            if (!email || email.indexOf('@') === -1) {
                alert('Enter a valid email address for the test.');
                return;
            }

            $btn.prop('disabled', true).text('Sending…');
            $('#jemail-test-feedback').remove();

            // Get content from WP editor or textarea
            var contentType = $('input[name="content_type"]:checked').val() || 'html';
            var htmlContent = '';
            var textContent = '';

            if (contentType === 'html') {
                // Try TinyMCE first, then fallback to textarea
                if (typeof tinymce !== 'undefined' && tinymce.get('jemail_html_content')) {
                    htmlContent = tinymce.get('jemail_html_content').getContent();
                } else {
                    htmlContent = $('#jemail_html_content').val() || '';
                }
            } else {
                textContent = $('#jemail-text-content').val() || '';
            }

            $.post(jemailAdmin.ajax_url, {
                action: 'jemail_send_test',
                nonce: jemailAdmin.nonce,
                test_email: email,
                subject: $('input[name="subject"]').val() || 'Test Email',
                content_type: contentType,
                html_content: htmlContent,
                text_content: textContent
            }, function(res) {
                $btn.prop('disabled', false).text('Send Test');
                var cls = res.success ? 'success' : 'error';
                var msg = res.success ? res.data : (res.data || 'Failed to send.');
                $btn.after('<div id="jemail-test-feedback" class="' + cls + '">' + msg + '</div>');
                setTimeout(function() { $('#jemail-test-feedback').fadeOut(400, function(){ $(this).remove(); }); }, 5000);
            }).fail(function() {
                $btn.prop('disabled', false).text('Send Test');
                $btn.after('<div id="jemail-test-feedback" class="error">AJAX request failed.</div>');
            });
        });

        // ─── Merge Tag Click-to-Copy ───
        $('.jemail-merge-tags code').on('click', function() {
            var tag = $(this).text();
            // Try to insert into active editor
            var contentType = $('input[name="content_type"]:checked').val() || 'html';
            if (contentType === 'html' && typeof tinymce !== 'undefined' && tinymce.get('jemail_html_content')) {
                tinymce.get('jemail_html_content').execCommand('mceInsertContent', false, tag);
            } else if (contentType === 'text') {
                var $ta = $('#jemail-text-content');
                var pos = $ta[0].selectionStart || $ta.val().length;
                var val = $ta.val();
                $ta.val(val.substring(0, pos) + tag + val.substring(pos));
            } else {
                // Fallback: copy to clipboard
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(tag);
                }
            }

            // Visual feedback
            var $el = $(this);
            $el.css('background', '#d4edda');
            setTimeout(function() { $el.css('background', ''); }, 500);
        });

        // ─── Initial Count on Page Load ───
        if ($('#jemail-count-btn').length) {
            setTimeout(function() {
                $('#jemail-count-btn').trigger('click');
            }, 800);
        }
    });
})(jQuery);
