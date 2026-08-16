<?php
if (!defined('ABSPATH')) exit;

/**
 * Vendor Self-Edit Shortcode
 *
 * Shortcode: [oha_vendor_edit]
 *
 * Access flow:
 *   Step 1 — Vendor enters email address + Vendor ID number (from invite email)
 *   Step 2 — Application data loads in an edit form on the same page
 *   Step 3 — Vendor saves; if status was "Invited" it promotes to "Submitted"
 *
 * No magic links or tokens required. Vendor can return to this page any time.
 *
 * Vendor ID = the _vendor_permanent_id meta value (integer).
 * In emails this appears as "VID-{number}" — vendors enter with or without the prefix.
 *
 * oha_vh_send_edit_link() is kept below for backward compatibility — not currently called.
 */

function oha_vh_sandbox_banner() {
    if (!get_option('oha_vhcpt_sandbox_mode')) return '';
    $to = esc_html(get_option('oha_vhcpt_sandbox_email', 'not configured'));
    return '<div style="background:#fef08a;border:2px solid #ca8a04;color:#78350f;padding:.75em 1em;margin-bottom:1.25em;border-radius:6px;font-weight:600;">'
        . 'SANDBOX MODE - This form is in test mode. All emails are being sent to: ' . $to
        . '</div>';
}

/**
 * Find a vendor application matching both email AND Vendor ID.
 * Prioritises the active convention year; falls back to most recent application.
 * Returns post ID or 0.
 */
function oha_vh_find_app_by_email_and_vid($email, $vid) {
    if (!$email || !$vid) return 0;

    $active_year = intval(get_option('oha_vhcpt_active_year', date('Y')));

    // Try active convention year first.
    $apps = get_posts([
        'post_type'      => 'vendor_application',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => 'email',                'value' => $email,       'compare' => '='],
            ['key' => '_vendor_permanent_id', 'value' => $vid,         'compare' => '=', 'type' => 'NUMERIC'],
            ['key' => '_convention_year',     'value' => $active_year, 'compare' => '='],
        ],
    ]);
    if (!empty($apps)) return $apps[0]->ID;

    // Fall back: any year (catches vendors not yet tagged with a year).
    $apps = get_posts([
        'post_type'      => 'vendor_application',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => 'email',                'value' => $email, 'compare' => '='],
            ['key' => '_vendor_permanent_id', 'value' => $vid,   'compare' => '=', 'type' => 'NUMERIC'],
        ],
    ]);
    return !empty($apps) ? $apps[0]->ID : 0;
}

/**
 * Handle logo upload for a vendor application.
 * Returns error string or empty string on success.
 */
function oha_vh_handle_logo_upload($app_id) {
    if (empty($_FILES['vendor_logo']['name'])) return '';

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $upload = wp_handle_upload($_FILES['vendor_logo'], ['test_form' => false]);
    if (!empty($upload['error'])) return $upload['error'];

    $attach_id = wp_insert_attachment([
        'post_mime_type' => $upload['type'],
        'post_title'     => sanitize_file_name($_FILES['vendor_logo']['name']),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ], $upload['file']);

    if (is_wp_error($attach_id)) return $attach_id->get_error_message();

    wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $upload['file']));
    update_post_meta($app_id, '_company_logo_id',  $attach_id);
    update_post_meta($app_id, '_company_logo_url', $upload['url']);

    return '';
}

/**
 * Kept for backward compatibility — not currently called by actions.php.
 * Placeholders: {first_name}, {link}, {vendor_id}
 */
function oha_vh_send_edit_link($email, $token, $edit_page_url, $first_name = '') {
    $default_subject = 'Update Your Vendor Information - Ohio Haunters Convention';
    $default_body    = "Hello {first_name},\n\nHere is a link to update your vendor information for the Ohio Haunters Convention.\n\n{link}\n\nIf you have any questions, contact us at info@ohiohaunters.com\n\n-- The OHA Team";

    $subject = get_option('oha_vhcpt_edit_link_subject', $default_subject);
    $body    = get_option('oha_vhcpt_edit_link_body',    $default_body);

    $subject = str_replace(['{first_name}', '{link}'], [$first_name, $edit_page_url], $subject);
    $body    = str_replace(['{first_name}', '{link}'], [$first_name, $edit_page_url], $body);

    wp_mail($email, $subject, $body);
}

/**
 * Render the lookup form (Step 1).
 */
function oha_vh_render_lookup_form($page_url, $email_val = '', $vid_val = '') {
    echo oha_vh_sandbox_banner();
    ?>
    <div class="oha-vh-edit-email-wrap">
        <h3>Update Your Vendor Information</h3>
        <p>Enter the email address and Vendor ID number from your invitation email to access your application.</p>
        <form class="oha-vh-form" method="post" action="<?php echo esc_url($page_url); ?>">
            <?php wp_nonce_field('oha_vh_lookup', 'oha_vh_lookup_nonce'); ?>
            <div class="oha-vh-row">
                <label for="lookup_email">Email Address *</label>
                <input type="email" id="lookup_email" name="lookup_email" required
                    placeholder="you@example.com"
                    value="<?php echo esc_attr($email_val); ?>">
            </div>
            <div class="oha-vh-row">
                <label for="lookup_vid">Vendor ID Number *</label>
                <input type="text" id="lookup_vid" name="lookup_vid" required
                    placeholder="e.g. 123 or VID-123"
                    value="<?php echo esc_attr($vid_val); ?>">
                <p style="margin:.25em 0 0;font-size:.85em;color:#bbb;">Your Vendor ID was included in your invitation email (e.g. "Your Vendor ID is: VID-123"). Enter just the number or the full VID-123.</p>
            </div>
            <div style="margin-top:1em;">
                <button type="submit" class="oha-vh-btn">Find My Application</button>
            </div>
        </form>
    </div>
    <?php
}

add_shortcode('oha_vendor_edit', function() {
    wp_enqueue_style('oha-vhcpt');

    $current_url = get_permalink() ?: home_url(add_query_arg(null, null));
    $notice      = '';
    $app_id      = 0;

    // ── POST: Lookup form submitted ──────────────────────────────────────────

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['oha_vh_lookup_nonce'])) {

        if (!wp_verify_nonce($_POST['oha_vh_lookup_nonce'], 'oha_vh_lookup')) {
            $notice = '<div class="oha-vh-error">Security check failed. Please try again.</div>';
        } else {
            $lookup_email = sanitize_email(wp_unslash($_POST['lookup_email'] ?? ''));
            $lookup_vid   = intval(preg_replace('/[^0-9]/', '', wp_unslash($_POST['lookup_vid'] ?? '')));

            if (!$lookup_email || !$lookup_vid) {
                $notice = '<div class="oha-vh-error">Please enter both your email address and Vendor ID number.</div>';
            } else {
                $app_id = oha_vh_find_app_by_email_and_vid($lookup_email, $lookup_vid);
                if (!$app_id) {
                    $notice = '<div class="oha-vh-error">No application was found matching that email address and Vendor ID. Please double-check both and try again. Your Vendor ID appears in your invitation email as "VID-" followed by a number.</div>';
                }
            }
        }

        if (!$app_id) {
            $email_prefill = sanitize_email(wp_unslash($_POST['lookup_email'] ?? ''));
            $vid_prefill   = sanitize_text_field(wp_unslash($_POST['lookup_vid'] ?? ''));
            ob_start();
            echo $notice;
            oha_vh_render_lookup_form($current_url, $email_prefill, $vid_prefill);
            return ob_get_clean();
        }

        // Fall through — app_id found, render edit form below
    }

    // ── POST: Edit form submitted ────────────────────────────────────────────

    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['oha_vh_edit_nonce'])) {

        $posted_app_id = intval($_POST['app_id'] ?? 0);
        $re_email      = sanitize_email(wp_unslash($_POST['lookup_email'] ?? ''));
        $re_vid        = intval(preg_replace('/[^0-9]/', '', wp_unslash($_POST['lookup_vid'] ?? '')));

        $nonce_ok    = $posted_app_id && wp_verify_nonce($_POST['oha_vh_edit_nonce'], 'oha_vhcpt_vendor_edit_' . $posted_app_id);
        $verified_id = oha_vh_find_app_by_email_and_vid($re_email, $re_vid);
        $access_ok   = $nonce_ok && ($verified_id === $posted_app_id);

        if (!$access_ok) {
            $notice = '<div class="oha-vh-error">Verification failed or your session expired. Please start over.</div>';
            ob_start();
            echo $notice;
            oha_vh_render_lookup_form($current_url);
            return ob_get_clean();
        }

        $app_id = $posted_app_id;
        $status = get_post_meta($app_id, '_oha_status', true);
        $errors = [];

        $fields_to_save = [
            'first_name'           => sanitize_text_field(wp_unslash($_POST['first_name'] ?? '')),
            'last_name'            => sanitize_text_field(wp_unslash($_POST['last_name'] ?? '')),
            'company_name'         => sanitize_text_field(wp_unslash($_POST['company_name'] ?? '')),
            'email'                => sanitize_email(wp_unslash($_POST['email'] ?? '')),
            'phone'                => sanitize_text_field(wp_unslash($_POST['phone'] ?? '')),
            'address_street'       => sanitize_text_field(wp_unslash($_POST['address_street'] ?? '')),
            'address_city'         => sanitize_text_field(wp_unslash($_POST['address_city'] ?? '')),
            'address_state'        => sanitize_text_field(wp_unslash($_POST['address_state'] ?? '')),
            'address_zip'          => sanitize_text_field(wp_unslash($_POST['address_zip'] ?? '')),
            'website'              => sanitize_text_field(wp_unslash($_POST['website'] ?? '')),
            'product_type'         => sanitize_textarea_field(wp_unslash($_POST['product_type'] ?? '')),
            'booths'               => max(1, intval(wp_unslash($_POST['booths'] ?? 1))),
            'sponsor'              => isset($_POST['sponsor']) ? 1 : 0,
            'booth_preference'     => sanitize_textarea_field(wp_unslash($_POST['booth_preference'] ?? '')),
            'vendor_private_notes' => sanitize_textarea_field(wp_unslash($_POST['vendor_private_notes'] ?? '')),
        ];
        $tables_needed = max(0, intval(wp_unslash($_POST['tables_needed'] ?? 0)));
        $need_electric = sanitize_text_field(wp_unslash($_POST['need_electric'] ?? ''));

        // Normalize website URL — add https:// if protocol is missing.
        if (!empty($fields_to_save['website'])) {
            $url = trim($fields_to_save['website']);
            $url = preg_replace('/[\x{00AD}\x{200B}\x{FEFF}]/u', '', $url);
            $url = trim($url, " \t\n\r\0\x0B\"'\u{201C}\u{201D}\u{2018}\u{2019}");
            if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                $url = 'https://' . $url;
            }
            $fields_to_save['website'] = $url !== '' ? esc_url_raw($url) : '';
        }

        foreach (['first_name', 'last_name', 'company_name', 'email', 'phone', 'product_type'] as $req) {
            if (empty($fields_to_save[$req])) {
                $errors[] = ucwords(str_replace('_', ' ', $req)) . ' is required.';
            }
        }
        if (empty($fields_to_save['booths'])) {
            $errors[] = 'Number of booth spaces is required.';
        }
        if (!isset($_POST['tables_needed'])) {
            $errors[] = 'Number of tables needed is required.';
        }
        if (!in_array($need_electric, ['yes', 'no'], true)) {
            $errors[] = 'Please indicate whether you need electrical access at your booth.';
        }

        if (!empty($errors)) {
            $notice = '<div class="oha-vh-error">Please correct the following:<ul><li>'
                . implode('</li><li>', array_map('esc_html', $errors))
                . '</li></ul></div>';
            // app_id stays set — fall through to re-render edit form with errors
        } else {
            foreach ($fields_to_save as $key => $val) {
                update_post_meta($app_id, $key, $val);
            }
            update_post_meta($app_id, 'tables_needed', $tables_needed);
            update_post_meta($app_id, 'need_electric', $need_electric === 'yes' ? 1 : 0);
            wp_update_post(['ID' => $app_id, 'post_title' => $fields_to_save['company_name']]);

            $logo_error = '';
            if (!empty($_FILES['vendor_logo']['name'])) {
                $logo_error = oha_vh_handle_logo_upload($app_id);
            } elseif (!empty($_POST['remove_logo'])) {
                delete_post_meta($app_id, '_company_logo_id');
                delete_post_meta($app_id, '_company_logo_url');
            }

            // Promote Invited → Submitted on first vendor save.
            if ($status === 'Invited') {
                update_post_meta($app_id, '_oha_status', 'Submitted');
                if (function_exists('oha_vhcpt_append_history')) {
                    oha_vhcpt_append_history($app_id, [
                        'type'        => 'application_submitted',
                        'description' => 'Vendor completed and submitted their return invite application.',
                    ]);
                }
            }

            // Send confirmation email to returning vendor.
            $vendor_email = $fields_to_save['email'];
            if ($vendor_email) {
                $conv_name = get_option('oha_vhcpt_convention_name', 'the Ohio Haunters Convention');
                $default_subject = 'Your Vendor Information Has Been Updated - {convention_name}';
                $default_body    = "Hello {first_name},\n\nThank you for updating your vendor information for {convention_name}. We have received your submission and will be in touch soon with next steps.\n\nIf you need to make any changes, you can return to the vendor update page at any time and log in with your email address and Vendor ID.\n\n-- The OHA Team";

                $conf_subject = get_option('oha_vhcpt_return_confirm_subject', $default_subject);
                $conf_body    = get_option('oha_vhcpt_return_confirm_body',    $default_body);

                $replacements = [
                    '{first_name}'      => $fields_to_save['first_name'],
                    '{company_name}'    => $fields_to_save['company_name'],
                    '{convention_name}' => $conv_name,
                ];
                $conf_subject = str_replace(array_keys($replacements), array_values($replacements), $conf_subject);
                $conf_body    = str_replace(array_keys($replacements), array_values($replacements), $conf_body);

                wp_mail($vendor_email, $conf_subject, $conf_body);
            }

            if ($logo_error) {
                $notice = '<div class="oha-vh-success">Your vendor information has been updated successfully. Logo upload failed: ' . esc_html($logo_error) . '</div>';
            } else {
                $notice = '<div class="oha-vh-success">Your vendor information has been updated successfully. You will receive a confirmation email shortly.</div>';
            }
        }
    }

    // ── No POST → show lookup form ───────────────────────────────────────────

    else {
        ob_start();
        oha_vh_render_lookup_form($current_url);
        return ob_get_clean();
    }

    // ── Render edit form ─────────────────────────────────────────────────────

    $lookup_email = sanitize_email(wp_unslash($_POST['lookup_email'] ?? ''));
    $lookup_vid   = intval(preg_replace('/[^0-9]/', '', wp_unslash($_POST['lookup_vid'] ?? '')));
    $status = get_post_meta($app_id, '_oha_status', true) ?: 'Invited';

    // Lock — applications at Agreement Signed or Paid cannot be edited directly.
    if (in_array($status, ['Agreement Signed', 'Paid'], true)) {
        ob_start();
        echo oha_vh_sandbox_banner();
        ?>
        <style>
        .oha-vh-locked-notice{background:rgba(0,0,0,0.3);border:1px solid rgba(173,49,25,0.4);border-left:4px solid var(--global-palette1,#ad3119);border-radius:6px;padding:1.5em;margin:1em 0}
        .oha-vh-locked-notice h3{margin:0 0 .75em;color:var(--global-palette3,#fff)}
        .oha-vh-locked-notice p{color:var(--global-palette4,#f7fafc);margin:0 0 .5em;line-height:1.5}
        .oha-vh-locked-notice a{color:var(--global-palette7,#e76653)}
        </style>
        <div class="oha-vh-locked-notice">
            <h3>Application Locked</h3>
            <p>Your vendor application has reached the <strong><?php echo esc_html($status); ?></strong> stage. The information in your application has been incorporated into your contract and can no longer be edited directly.</p>
            <p>If you need to make a correction, please email the OHA board: <a href="mailto:info@ohiohaunters.com">info@ohiohaunters.com</a></p>
        </div>
        <p style="margin-top:1.5em;font-size:.9em;">
            <a href="<?php echo esc_url($current_url); ?>" style="color:var(--global-palette7,#e76653);">Back to application lookup</a>
        </p>
        <?php
        return ob_get_clean();
    }

    $using_post = (isset($_POST['oha_vh_edit_nonce']) && $_SERVER['REQUEST_METHOD'] === 'POST');

    // Text/select fields — esc_attr for use in input value attributes.
    $v = [];
    foreach (['first_name','last_name','company_name','email','phone',
              'address_street','address_city','address_state','address_zip',
              'website','booths','sponsor'] as $f) {
        $v[$f] = $using_post
            ? esc_attr(wp_unslash($_POST[$f] ?? ''))
            : esc_attr(get_post_meta($app_id, $f, true));
    }

    // Textarea fields — esc_textarea for use inside <textarea>.
    foreach (['product_type', 'booth_preference', 'vendor_private_notes'] as $f) {
        $v[$f] = $using_post
            ? esc_textarea(wp_unslash($_POST[$f] ?? ''))
            : esc_textarea(get_post_meta($app_id, $f, true));
    }

    // tables_needed and need_electric — stored as int/bool in DB, need conversion for form.
    if ($using_post) {
        $v['tables_needed'] = intval(wp_unslash($_POST['tables_needed'] ?? 0));
        $v['need_electric'] = sanitize_text_field(wp_unslash($_POST['need_electric'] ?? ''));
    } else {
        $v['tables_needed'] = intval(get_post_meta($app_id, 'tables_needed', true));
        $v['need_electric'] = get_post_meta($app_id, 'need_electric', true) ? 'yes' : 'no';
    }

    $logo_id  = get_post_meta($app_id, '_company_logo_id', true);
    $logo_img = $logo_id ? wp_get_attachment_image_src((int)$logo_id, [150, 150]) : null;

    ob_start();
    echo oha_vh_sandbox_banner();
    echo $notice;
    ?>
    <style>
    .oha-vh-section { margin-bottom: 2em; }
    .oha-vh-section-head { margin-bottom: 1em; padding-bottom: 0.5em; border-bottom: 1px solid #333; }
    .oha-vh-section-title { font-size: 1.05em; font-weight: 700; margin: 0 0 0.25em; color: var(--global-palette3, #fff); }
    .oha-vh-section-desc { font-size: 0.875em; margin: 0; color: var(--global-palette6, #cbd2d9); }
    .oha-vh-badge-public { display: inline-block; font-size: 0.7em; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; background: #1a7a3a; color: #fff; padding: 2px 7px; border-radius: 3px; vertical-align: middle; margin-left: 6px; }
    .oha-vh-badge-private { display: inline-block; font-size: 0.7em; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; background: #555; color: #fff; padding: 2px 7px; border-radius: 3px; vertical-align: middle; margin-left: 6px; }
    .oha-vh-hint { display: block; font-size: 0.8em; color: var(--global-palette6, #cbd2d9); margin-top: 4px; line-height: 1.4; }
    .oha-vh-req { color: #ef4444; }
    .oha-vh-checkbox-label { display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer; }
    .oha-vh-logo-wrap { display: flex; flex-direction: column; gap: .25em; }
    .oha-vh-edit-wrap .oha-vh-row label { color: var(--global-palette3, #fff); }
    </style>

    <div class="oha-vh-edit-wrap">
        <form class="oha-vh-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('oha_vhcpt_vendor_edit_' . $app_id, 'oha_vh_edit_nonce'); ?>
            <input type="hidden" name="app_id"       value="<?php echo esc_attr($app_id); ?>">
            <input type="hidden" name="lookup_email" value="<?php echo esc_attr($lookup_email); ?>">
            <input type="hidden" name="lookup_vid"   value="<?php echo esc_attr($lookup_vid); ?>">

            <?php /* ── SECTION 1: PUBLIC BUSINESS INFORMATION ── */ ?>
            <div class="oha-vh-section">
                <div class="oha-vh-section-head">
                    <p class="oha-vh-section-title">Business Information <span class="oha-vh-badge-public">Shown Publicly</span></p>
                    <p class="oha-vh-section-desc">This information appears on the OHA website vendor listing, the convention map, and in the convention app. Make sure it represents your business accurately.</p>
                </div>

                <div class="oha-vh-row">
                    <label for="ve_company_name">Company Name <span class="oha-vh-req">*</span></label>
                    <input type="text" id="ve_company_name" name="company_name" value="<?php echo $v['company_name']; ?>" required>
                    <span class="oha-vh-hint">Your business or brand name as it will appear publicly.</span>
                </div>
                <div class="oha-vh-row">
                    <label for="ve_website">Company Website</label>
                    <input type="text" id="ve_website" name="website" value="<?php echo $v['website']; ?>" placeholder="https://...">
                    <span class="oha-vh-hint">Enter your website address with or without https:// - we will add it automatically. Leave blank if you do not have one.</span>
                </div>
                <div class="oha-vh-row">
                    <label for="ve_product_type">Products / Description <span class="oha-vh-req">*</span></label>
                    <textarea id="ve_product_type" name="product_type" rows="4" required><?php echo $v['product_type']; ?></textarea>
                    <span class="oha-vh-hint">Describe what you will be selling. This appears on your public vendor card - be specific so attendees can find you.</span>
                </div>

                <div class="oha-vh-row">
                    <label>Company Logo / Photo <span class="oha-vh-badge-public">Shown Publicly</span></label>
                    <div class="oha-vh-logo-wrap">
                        <?php if ($logo_img): ?>
                            <img id="oha-ve-logo-preview" src="<?php echo esc_url($logo_img[0]); ?>" alt="Current logo"
                                style="max-width:150px;max-height:150px;display:block;margin-bottom:.5em;border:1px solid #444;border-radius:4px;">
                            <label style="display:flex;align-items:center;gap:.4em;margin-bottom:.5em;font-weight:normal;cursor:pointer;">
                                <input type="checkbox" name="remove_logo" value="1"> Remove current logo
                            </label>
                        <?php else: ?>
                            <img id="oha-ve-logo-preview" src="" alt=""
                                style="max-width:150px;max-height:150px;display:none;margin-bottom:.5em;border:1px solid #444;border-radius:4px;">
                        <?php endif; ?>
                        <input type="file" id="oha-ve-logo-file" name="vendor_logo" accept="image/jpeg,image/png,image/gif">
                        <span class="oha-vh-hint">Your logo appears on your vendor card, the convention map, and in the convention app. JPG or PNG recommended, max 5 MB.</span>
                    </div>
                </div>
            </div>

            <?php /* ── SECTION 2: CONTACT INFORMATION ── */ ?>
            <div class="oha-vh-section">
                <div class="oha-vh-section-head">
                    <p class="oha-vh-section-title">Your Contact Information <span class="oha-vh-badge-private">Board Only</span></p>
                    <p class="oha-vh-section-desc">This information is seen only by the OHA board. It is never displayed publicly and is used for communication and agreement purposes only.</p>
                </div>
                <div class="oha-vh-row">
                    <label for="ve_first_name">First Name <span class="oha-vh-req">*</span></label>
                    <input type="text" id="ve_first_name" name="first_name" value="<?php echo $v['first_name']; ?>" required>
                </div>
                <div class="oha-vh-row">
                    <label for="ve_last_name">Last Name <span class="oha-vh-req">*</span></label>
                    <input type="text" id="ve_last_name" name="last_name" value="<?php echo $v['last_name']; ?>" required>
                </div>
                <div class="oha-vh-row">
                    <label for="ve_email">Email <span class="oha-vh-req">*</span></label>
                    <input type="email" id="ve_email" name="email" value="<?php echo $v['email']; ?>" required>
                    <span class="oha-vh-hint">We will send your confirmation and any agreements to this address.</span>
                </div>
                <div class="oha-vh-row">
                    <label for="ve_phone">Phone <span class="oha-vh-req">*</span></label>
                    <input type="text" id="ve_phone" name="phone" value="<?php echo $v['phone']; ?>" required>
                </div>
                <div class="oha-vh-row">
                    <label for="ve_address_street">Street Address</label>
                    <input type="text" id="ve_address_street" name="address_street" value="<?php echo $v['address_street']; ?>">
                </div>
                <div class="oha-vh-row">
                    <label for="ve_address_city">City</label>
                    <input type="text" id="ve_address_city" name="address_city" value="<?php echo $v['address_city']; ?>">
                </div>
                <div class="oha-vh-row">
                    <label for="ve_address_state">State</label>
                    <select id="ve_address_state" name="address_state">
                        <option value="">Select...</option>
                        <?php
                        $states_html = oha_vhcpt_render_states();
                        if ($v['address_state']) {
                            $states_html = str_replace(
                                'value="' . esc_attr(get_post_meta($app_id, 'address_state', true)) . '"',
                                'value="' . esc_attr(get_post_meta($app_id, 'address_state', true)) . '" selected',
                                $states_html
                            );
                        }
                        echo $states_html;
                        ?>
                    </select>
                </div>
                <div class="oha-vh-row">
                    <label for="ve_address_zip">Zip Code</label>
                    <input type="text" id="ve_address_zip" name="address_zip" value="<?php echo $v['address_zip']; ?>">
                </div>
            </div>

            <?php /* ── SECTION 3: CONVENTION DETAILS ── */ ?>
            <div class="oha-vh-section">
                <div class="oha-vh-section-head">
                    <p class="oha-vh-section-title">Convention Details</p>
                </div>
                <div class="oha-vh-row">
                    <label for="ve_booths">Number of booth spaces requested <span class="oha-vh-req">*</span></label>
                    <input type="number" id="ve_booths" name="booths" value="<?php echo esc_attr($v['booths'] ?: 1); ?>" min="1" max="10" required>
                    <span class="oha-vh-hint">Each booth space is 8x10. Contact us if you need clarification before submitting.</span>
                </div>
                <div class="oha-vh-row">
                    <label for="ve_tables_needed">Number of tables needed <span class="oha-vh-req">*</span></label>
                    <input type="number" id="ve_tables_needed" name="tables_needed" value="<?php echo esc_attr($v['tables_needed']); ?>" min="0" max="10" required>
                    <span class="oha-vh-hint">Enter 0 if you are bringing your own or do not need any.</span>
                </div>
                <div class="oha-vh-row">
                    <label for="ve_need_electric">Do you need electrical access at your booth? <span class="oha-vh-req">*</span></label>
                    <select id="ve_need_electric" name="need_electric" required>
                        <option value="">Select...</option>
                        <option value="yes" <?php selected($v['need_electric'], 'yes'); ?>>Yes</option>
                        <option value="no"  <?php selected($v['need_electric'], 'no'); ?>>No</option>
                    </select>
                </div>
                <div class="oha-vh-row">
                    <label class="oha-vh-checkbox-label">
                        <input type="checkbox" name="sponsor" value="1" <?php checked($v['sponsor'], '1'); ?>>
                        I am interested in being a sponsor
                    </label>
                </div>
            </div>

            <?php /* ── SECTION 4: BOOTH PREFERENCE ── */ ?>
            <div class="oha-vh-section">
                <div class="oha-vh-section-head">
                    <p class="oha-vh-section-title">Booth Preference <span class="oha-vh-badge-private">Board Only</span></p>
                </div>
                <div class="oha-vh-row">
                    <label for="ve_booth_preference">Booth preference or location notes (optional)</label>
                    <textarea id="ve_booth_preference" name="booth_preference" rows="3"
                        placeholder="Example: I would like to keep my booth from last year. Or: I would prefer to be near the main entrance."><?php echo $v['booth_preference']; ?></textarea>
                </div>
            </div>

            <?php /* ── SECTION 5: NOTE TO BOARD ── */ ?>
            <div class="oha-vh-section">
                <div class="oha-vh-section-head">
                    <p class="oha-vh-section-title">Note to the OHA Board <span class="oha-vh-badge-private">Board Only</span></p>
                    <p class="oha-vh-section-desc">Share anything the board should know - accessibility needs, questions, or special requests. This is seen only by the board and is never shared publicly.</p>
                </div>
                <div class="oha-vh-row">
                    <label for="ve_vendor_private_notes">Notes (optional)</label>
                    <textarea id="ve_vendor_private_notes" name="vendor_private_notes" rows="4"><?php echo $v['vendor_private_notes']; ?></textarea>
                </div>
            </div>

            <div style="margin-top:1.5em;">
                <button type="submit" class="oha-vh-btn">Save Changes</button>
            </div>
        </form>

        <p style="margin-top:1.5em;font-size:.9em;">
            <a href="<?php echo esc_url($current_url); ?>">Start over / look up a different application</a>
        </p>
    </div>

    <script>
    (function(){
        var fileInput = document.getElementById('oha-ve-logo-file');
        var preview   = document.getElementById('oha-ve-logo-preview');
        if (fileInput && preview) {
            fileInput.addEventListener('change', function(){
                var file = this.files[0];
                if (!file) return;
                var reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            });
        }
    })();
    </script>
    <?php
    return ob_get_clean();
});
