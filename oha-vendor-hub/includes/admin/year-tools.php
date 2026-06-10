<?php
if (!defined('ABSPATH')) exit;

/**
 * Year Tools — admin submenu page.
 *
 * Two tools on one page:
 *   1. Backfill Year & Vendor IDs — stamps _convention_year and _vendor_permanent_id
 *      on all existing applications that are missing them.
 *   2. Send Return Invites — finds prior-year Paid vendors with no current-year
 *      application, creates pre-populated drafts, and sends invite emails.
 */
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=vendor_application',
        'Year Tools',
        'Year Tools',
        'manage_options',
        'oha-vh-year-tools',
        'oha_vh_year_tools_page'
    );
});

function oha_vh_year_tools_page() {
    if (!current_user_can('manage_options')) return;

    $active_year  = intval(get_option('oha_vhcpt_active_year', date('Y')));
    $settings_url = admin_url('edit.php?post_type=vendor_application&page=oha-vhcpt-settings');
    $nonce_back   = wp_create_nonce('oha_vh_backfill');
    $nonce_invite = wp_create_nonce('oha_vh_return_invites');
    ?>
    <div class="wrap">
        <h1>Vendor Hub Year Tools</h1>
        <p style="color:#555;">Active convention year: <strong><?php echo esc_html($active_year); ?></strong>.
        Change it in <a href="<?php echo esc_url($settings_url); ?>">Vendor Hub Settings</a> &rsaquo; Convention Details.</p>

        <hr>

        <!-- ================================================================
             TOOL 1: Backfill Year & Vendor IDs
             ================================================================ -->
        <h2>Backfill Year &amp; Vendor IDs</h2>
        <p>Run this once after upgrading to v3.10+ to stamp all <em>existing</em> applications
        that pre-date the year-tracking system. Each post will receive:</p>
        <ul style="list-style:disc;padding-left:2em;">
            <li><code>_convention_year</code> — set to the year from the label you enter below (or the post&rsquo;s publish year if left blank)</li>
            <li><code>_vendor_permanent_id</code> — set to the post&rsquo;s own ID (its permanent cross-year identifier)</li>
        </ul>
        <p>Posts that already have these values are skipped.</p>

        <table class="form-table" style="max-width:640px;">
            <tr>
                <th scope="row">Year label for existing posts</th>
                <td>
                    <input type="number" id="backfill_year" class="small-text" min="2020" max="2100"
                        value="2026" placeholder="e.g. 2026">
                    <p class="description">Leave this as-is for all your 2026 data. Posts that already have a year are never overwritten.</p>
                </td>
            </tr>
        </table>

        <p>
            <button type="button" id="oha-backfill-count" class="button">Count Untagged Applications</button>
            <span id="oha-backfill-count-result" style="margin-left:10px;color:#555;"></span>
        </p>
        <p>
            <button type="button" id="oha-backfill-run" class="button button-primary">Run Backfill</button>
        </p>
        <div id="oha-backfill-result" style="margin-top:10px;max-width:640px;"></div>

        <hr>

        <!-- ================================================================
             TOOL 2: Send Return Invites
             ================================================================ -->
        <h2>Send Return Vendor Invites</h2>
        <p>Finds vendors from a prior year who reached <strong>Paid</strong> status and have <em>no</em>
        application for the target year. For each one:</p>
        <ol style="padding-left:2em;">
            <li>A new <code>vendor_application</code> draft is created pre-populated with their contact info.</li>
            <li>Their permanent vendor ID is carried forward.</li>
            <li>An invite email is sent with a magic edit link so they can review and submit.</li>
        </ol>
        <p><strong>This is safe to preview first</strong> — use "Count" before sending.</p>

        <table class="form-table" style="max-width:640px;">
            <tr>
                <th scope="row">Prior year to invite from</th>
                <td>
                    <input type="number" id="invite_from_year" class="small-text" min="2020" max="2100"
                        value="<?php echo esc_attr($active_year); ?>">
                    <p class="description">Find Paid vendors from this year (e.g. 2026).</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Target year for new applications</th>
                <td>
                    <input type="number" id="invite_target_year" class="small-text" min="2020" max="2100"
                        value="<?php echo esc_attr($active_year + 1); ?>">
                    <p class="description">New applications will be stamped with this year (e.g. 2027). Vendors already in this year are skipped.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Invite email subject</th>
                <td>
                    <input type="text" id="invite_subject" class="large-text"
                        value="You are invited to vend at <?php echo esc_attr(get_option('oha_vhcpt_convention_name', 'OHA Convention ' . $active_year)); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">Invite email body</th>
                <td>
                    <textarea id="invite_body" rows="10" class="large-text"><?php
$conv_name  = get_option('oha_vhcpt_convention_name',  'OHA Convention ' . $active_year);
$conv_dates = get_option('oha_vhcpt_convention_dates',  '');
$conv_venue = get_option('oha_vhcpt_convention_venue',  '');
$default_body  = "Hi {first_name},\n\n";
$default_body .= "As one of our valued vendors from last year, we wanted to give you first access to secure your spot at {convention_name}.\n\n";
if ($conv_dates) $default_body .= "Dates: {convention_dates}\n";
if ($conv_venue) $default_body .= "Venue: {convention_venue}\n";
$default_body .= "\nYour application has been pre-filled with your information from last year. Simply review, make any updates, and submit.\n\n";
$default_body .= "Your personalized link:\n{edit_link}\n\n";
$default_body .= "This link is unique to you and expires in 7 days.\n\n";
$default_body .= "Your Vendor ID is: VID-{vendor_id}\n\n";
$default_body .= "We hope to see you again this year!\n\n-- Ohio Haunters Association";
echo esc_textarea($default_body);
                    ?></textarea>
                    <p class="description">
                        Available tags: <code>{first_name}</code> <code>{last_name}</code> <code>{company_name}</code>
                        <code>{vendor_id}</code> <code>{edit_link}</code> <code>{convention_name}</code>
                        <code>{convention_dates}</code> <code>{convention_venue}</code>
                    </p>
                </td>
            </tr>
        </table>

        <p>
            <button type="button" id="oha-invite-count" class="button">Count Eligible Vendors</button>
            <span id="oha-invite-count-result" style="margin-left:10px;color:#555;"></span>
        </p>
        <p>
            <button type="button" id="oha-invite-send" class="button button-primary">Send Return Invites</button>
            <span style="margin-left:8px;color:#666;font-size:13px;">Each vendor receives one invite. Existing target-year applications are not duplicated.</span>
        </p>
        <div id="oha-invite-result" style="margin-top:10px;max-width:640px;"></div>
    </div>

    <script>
    (function($){
        var nonceBack   = <?php echo wp_json_encode($nonce_back); ?>;
        var nonceInvite = <?php echo wp_json_encode($nonce_invite); ?>;
        var ajaxUrl     = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;

        /* ---------- Backfill ---------- */

        $('#oha-backfill-count').on('click', function(){
            $('#oha-backfill-count-result').text('Counting...');
            $.post(ajaxUrl, {
                action: 'oha_vh_backfill_count',
                nonce: nonceBack
            }, function(r){
                if (r.success) {
                    $('#oha-backfill-count-result').text(r.data.count + ' application(s) are missing year or vendor ID tags.');
                } else {
                    $('#oha-backfill-count-result').text('Error: ' + (r.data || 'Unknown'));
                }
            }).fail(function(){ $('#oha-backfill-count-result').text('Request failed.'); });
        });

        $('#oha-backfill-run').on('click', function(){
            var year = parseInt($('#backfill_year').val(), 10);
            if (!year || year < 2020 || year > 2100) {
                alert('Enter a valid year (e.g. 2026).');
                return;
            }
            if (!confirm('Stamp year ' + year + ' and permanent vendor IDs on all untagged applications?\n\nAlready-tagged posts are not changed.')) return;

            var btn = $(this);
            btn.prop('disabled', true).text('Running...');
            $('#oha-backfill-result').html('<p style="color:#555;">Working...</p>');

            $.post(ajaxUrl, {
                action: 'oha_vh_backfill_run',
                nonce: nonceBack,
                year: year
            }, function(r){
                btn.prop('disabled', false).text('Run Backfill');
                if (r.success) {
                    $('#oha-backfill-result').html('<div class="notice notice-success inline"><p>' + r.data.message + '</p></div>');
                } else {
                    $('#oha-backfill-result').html('<div class="notice notice-error inline"><p>Error: ' + (r.data || 'Unknown') + '</p></div>');
                }
            }).fail(function(){
                btn.prop('disabled', false).text('Run Backfill');
                $('#oha-backfill-result').html('<div class="notice notice-error inline"><p>Request failed.</p></div>');
            });
        });

        /* ---------- Return Invites ---------- */

        function getInviteParams() {
            return {
                from_year:   parseInt($('#invite_from_year').val(), 10),
                target_year: parseInt($('#invite_target_year').val(), 10),
                subject:     $('#invite_subject').val(),
                body:        $('#invite_body').val()
            };
        }

        $('#oha-invite-count').on('click', function(){
            var p = getInviteParams();
            if (!p.from_year || p.from_year < 2020) { alert('Enter a valid prior year.'); return; }
            if (!p.target_year || p.target_year < 2020) { alert('Enter a valid target year.'); return; }
            if (p.from_year === p.target_year) { alert('Prior year and target year must be different.'); return; }
            $('#oha-invite-count-result').text('Counting...');
            $.post(ajaxUrl, {
                action:      'oha_vh_return_invite_count',
                nonce:       nonceInvite,
                from_year:   p.from_year,
                target_year: p.target_year
            }, function(r){
                if (r.success) {
                    $('#oha-invite-count-result').text(r.data.count + ' eligible vendor(s) found.');
                } else {
                    $('#oha-invite-count-result').text('Error: ' + (r.data || 'Unknown'));
                }
            }).fail(function(){ $('#oha-invite-count-result').text('Request failed.'); });
        });

        $('#oha-invite-send').on('click', function(){
            var p = getInviteParams();
            if (!p.from_year || p.from_year < 2020) { alert('Enter a valid prior year.'); return; }
            if (!p.target_year || p.target_year < 2020) { alert('Enter a valid target year.'); return; }
            if (p.from_year === p.target_year) { alert('Prior year and target year must be different.'); return; }
            if (!p.subject.trim() || !p.body.trim()) { alert('Subject and body are required.'); return; }
            if (!confirm('Send return invites to all eligible ' + p.from_year + ' Paid vendors?\n\nThis creates ' + p.target_year + ' draft applications and emails each vendor a personalized link.')) return;

            var btn = $(this);
            btn.prop('disabled', true).text('Sending...');
            $('#oha-invite-result').html('<p style="color:#555;">Creating applications and sending emails, please wait...</p>');

            $.post(ajaxUrl, {
                action:      'oha_vh_return_invite_send',
                nonce:       nonceInvite,
                from_year:   p.from_year,
                target_year: p.target_year,
                subject:     p.subject,
                body:        p.body
            }, function(r){
                btn.prop('disabled', false).text('Send Return Invites');
                if (r.success) {
                    $('#oha-invite-result').html('<div class="notice notice-success inline"><p>' + r.data.message + '</p></div>');
                } else {
                    $('#oha-invite-result').html('<div class="notice notice-error inline"><p>Error: ' + (r.data || 'Unknown') + '</p></div>');
                }
            }).fail(function(){
                btn.prop('disabled', false).text('Send Return Invites');
                $('#oha-invite-result').html('<div class="notice notice-error inline"><p>Request failed.</p></div>');
            });
        });
    })(jQuery);
    </script>
    <?php
}

/* =============================================================================
   AJAX: Backfill count
   ============================================================================= */
add_action('wp_ajax_oha_vh_backfill_count', function() {
    check_ajax_referer('oha_vh_backfill', 'nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }

    global $wpdb;
    // Count vendor_application posts missing _convention_year OR _vendor_permanent_id.
    $post_ids_with_year = $wpdb->get_col(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_convention_year'"
    );
    $post_ids_with_pid = $wpdb->get_col(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_vendor_permanent_id'"
    );

    $q = new WP_Query([
        'post_type'      => 'vendor_application',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    $all_ids    = $q->posts;
    $tagged_ids = array_unique(array_merge(
        array_map('intval', $post_ids_with_year),
        array_map('intval', $post_ids_with_pid)
    ));
    $untagged = array_diff($all_ids, $tagged_ids);

    wp_send_json_success(['count' => count($untagged)]);
});

/* =============================================================================
   AJAX: Backfill run
   ============================================================================= */
add_action('wp_ajax_oha_vh_backfill_run', function() {
    check_ajax_referer('oha_vh_backfill', 'nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }

    $year = intval($_POST['year'] ?? 0);
    if ($year < 2020 || $year > 2100) { wp_send_json_error('Invalid year.'); return; }

    $q = new WP_Query([
        'post_type'      => 'vendor_application',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    $stamped = 0;
    $skipped = 0;

    foreach ($q->posts as $post_id) {
        $has_year = get_post_meta($post_id, '_convention_year', true);
        $has_pid  = get_post_meta($post_id, '_vendor_permanent_id', true);

        $changed = false;
        if (!$has_year) {
            update_post_meta($post_id, '_convention_year', $year);
            $changed = true;
        }
        if (!$has_pid) {
            update_post_meta($post_id, '_vendor_permanent_id', $post_id);
            $changed = true;
        }

        if ($changed) { $stamped++; } else { $skipped++; }
    }

    wp_send_json_success([
        'message' => "Done. {$stamped} application(s) tagged. {$skipped} already had tags and were skipped.",
    ]);
});

/* =============================================================================
   AJAX: Return invite — count
   ============================================================================= */
add_action('wp_ajax_oha_vh_return_invite_count', function() {
    check_ajax_referer('oha_vh_return_invites', 'nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }

    $from_year   = intval($_POST['from_year']   ?? 0);
    $target_year = intval($_POST['target_year'] ?? 0);

    if ($from_year < 2020 || $from_year > 2100) { wp_send_json_error('Invalid prior year.'); return; }
    if ($target_year < 2020 || $target_year > 2100) { wp_send_json_error('Invalid target year.'); return; }
    if ($from_year === $target_year) { wp_send_json_error('Prior year and target year must be different.'); return; }

    $eligible = oha_vh_get_return_invite_candidates($from_year, $target_year);
    wp_send_json_success(['count' => count($eligible)]);
});

/* =============================================================================
   AJAX: Return invite — send
   ============================================================================= */
add_action('wp_ajax_oha_vh_return_invite_send', function() {
    check_ajax_referer('oha_vh_return_invites', 'nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }

    $from_year   = intval($_POST['from_year']   ?? 0);
    $active_year = intval($_POST['target_year'] ?? 0);
    $subject_tpl = sanitize_text_field(wp_unslash($_POST['subject'] ?? ''));
    $body_tpl    = sanitize_textarea_field(wp_unslash($_POST['body'] ?? ''));

    if ($from_year < 2020 || $from_year > 2100) { wp_send_json_error('Invalid prior year.'); return; }
    if ($active_year < 2020 || $active_year > 2100) { wp_send_json_error('Invalid target year.'); return; }
    if ($from_year === $active_year) { wp_send_json_error('Prior year and target year must be different.'); return; }
    if (!$subject_tpl || !$body_tpl) {
        wp_send_json_error('Subject and body are required.');
        return;
    }

    $edit_page_id = intval(get_option('oha_vhcpt_vendor_edit_page_id', 0));
    if (!$edit_page_id) {
        wp_send_json_error('Vendor Edit Page is not configured in Vendor Hub Settings.');
        return;
    }

    $candidates = oha_vh_get_return_invite_candidates($from_year, $active_year);
    if (empty($candidates)) {
        wp_send_json_success(['message' => 'No eligible vendors found. Nothing sent.']);
        return;
    }

    $conv_name  = get_option('oha_vhcpt_convention_name',  'OHA Convention ' . $active_year);
    $conv_dates = get_option('oha_vhcpt_convention_dates',  '');
    $conv_venue = get_option('oha_vhcpt_convention_venue',  '');

    $sent  = 0;
    $skips = 0;
    $fails = 0;

    foreach ($candidates as $old_post_id) {
        $email   = get_post_meta($old_post_id, 'email', true);
        if (!$email || !is_email($email)) { $skips++; continue; }

        // Copy core fields from prior-year application.
        $copy_keys = ['first_name','last_name','company_name','email','phone',
                      'address_street','address_city','address_state','address_zip',
                      'website','product_type','booths','tables_needed','sponsor',
                      'need_electric','need_air','need_table','booth_preference',
                      '_company_logo_id','_company_logo_url'];

        $company   = get_post_meta($old_post_id, 'company_name', true);
        $first     = get_post_meta($old_post_id, 'first_name', true);
        $perm_id   = get_post_meta($old_post_id, '_vendor_permanent_id', true) ?: $old_post_id;
        $title     = $company ?: trim($first . ' ' . get_post_meta($old_post_id, 'last_name', true));
        if (!$title) $title = 'Returning Vendor Application';

        // Create a new draft application pre-populated with prior-year data.
        $new_id = wp_insert_post([
            'post_type'   => 'vendor_application',
            'post_status' => 'publish',
            'post_title'  => $title,
        ], true);

        if (is_wp_error($new_id)) { $skips++; continue; }

        foreach ($copy_keys as $k) {
            $v = get_post_meta($old_post_id, $k, true);
            if ($v !== '' && $v !== false) {
                update_post_meta($new_id, $k, $v);
            }
        }

        // Stamp year and carry forward permanent ID.
        update_post_meta($new_id, '_convention_year',    $active_year);
        update_post_meta($new_id, '_vendor_permanent_id', $perm_id);
        update_post_meta($new_id, 'returning_vendor',    1);
        update_post_meta($new_id, '_oha_status',         'Submitted');

        if (function_exists('oha_vhcpt_append_history')) {
            oha_vhcpt_append_history($new_id, [
                'type'        => 'return_invite_created',
                'description' => 'Application created via Return Invite tool from prior-year post #' . $old_post_id . '.',
            ]);
        }

        // Generate a magic edit token (7-day expiry).
        $token   = wp_generate_password(32, false);
        $expires = time() + (7 * DAY_IN_SECONDS);
        update_post_meta($new_id, '_edit_token',         $token);
        update_post_meta($new_id, '_edit_token_expires', $expires);

        $edit_url = add_query_arg(['vid' => $new_id, 'token' => $token], get_permalink($edit_page_id));

        // Build email from template.
        $replacements = [
            '{first_name}'       => $first,
            '{last_name}'        => get_post_meta($old_post_id, 'last_name', true),
            '{company_name}'     => $company,
            '{vendor_id}'        => $perm_id,
            '{edit_link}'        => $edit_url,
            '{convention_name}'  => $conv_name,
            '{convention_dates}' => $conv_dates,
            '{convention_venue}' => $conv_venue,
        ];

        $subject = strtr($subject_tpl, $replacements);
        $body    = strtr($body_tpl, $replacements);

        $ok = wp_mail($email, $subject, $body, ['Content-Type: text/plain; charset=UTF-8']);
        if ($ok) { $sent++; } else { $fails++; }

        if (function_exists('oha_vhcpt_append_history')) {
            oha_vhcpt_append_history($new_id, [
                'type'    => 'return_invite_email',
                'to'      => $email,
                'subject' => $subject,
                'sent'    => $ok ? 'success' : 'FAILED',
            ]);
        }
    }

    $msg = "Done. {$sent} invite(s) sent. {$fails} email(s) failed. {$skips} vendor(s) skipped (invalid email or post error).";
    wp_send_json_success(['message' => $msg]);
});

/* =============================================================================
   Helper: find prior-year Paid vendors with no current-year application
   ============================================================================= */
function oha_vh_get_return_invite_candidates($from_year, $active_year) {
    // Get all vendor_application post IDs from the prior year with Paid status.
    $prior_year_q = new WP_Query([
        'post_type'      => 'vendor_application',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => '_convention_year', 'value' => $from_year, 'compare' => '='],
            ['key' => '_oha_status', 'value' => ['Paid', 'paid', 'PAID'], 'compare' => 'IN'],
        ],
    ]);

    if (empty($prior_year_q->posts)) return [];

    // Get all permanent vendor IDs that already have a current-year application.
    $current_year_q = new WP_Query([
        'post_type'      => 'vendor_application',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            ['key' => '_convention_year', 'value' => $active_year, 'compare' => '='],
        ],
    ]);

    $current_perm_ids = [];
    foreach ($current_year_q->posts as $cur_id) {
        $pid = get_post_meta($cur_id, '_vendor_permanent_id', true) ?: $cur_id;
        $current_perm_ids[] = intval($pid);
    }
    $current_perm_ids = array_unique($current_perm_ids);

    // Filter: prior-year posts whose permanent ID does not appear in the current year.
    $candidates = [];
    foreach ($prior_year_q->posts as $post_id) {
        $perm_id = intval(get_post_meta($post_id, '_vendor_permanent_id', true) ?: $post_id);
        if (!in_array($perm_id, $current_perm_ids, true)) {
            $candidates[] = $post_id;
        }
    }

    return $candidates;
}
