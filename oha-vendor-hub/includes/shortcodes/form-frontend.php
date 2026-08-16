<?php
if (!defined('ABSPATH')) exit;

/**
 * Default fields configuration.
 * These keys must match existing meta keys on vendor_application posts.
 */
function oha_vhcpt_fields_default(){
    return [
        ['key'=>'first_name','label'=>'First Name','type'=>'text','required'=>true],
        ['key'=>'last_name','label'=>'Last Name','type'=>'text','required'=>true],
        ['key'=>'company_name','label'=>'Business / Brand Name','type'=>'text','required'=>true],
        ['key'=>'email','label'=>'Email','type'=>'email','required'=>true],
        ['key'=>'phone','label'=>'Phone','type'=>'text','required'=>true],
        ['key'=>'address_street','label'=>'Address','type'=>'text','required'=>true],
        ['key'=>'address_city','label'=>'City','type'=>'text','required'=>true],
        ['key'=>'address_state','label'=>'State','type'=>'state','required'=>true],
        ['key'=>'address_zip','label'=>'Zip','type'=>'text','required'=>true],
        ['key'=>'website','label'=>'Business Website','type'=>'text','required'=>false],
        ['key'=>'product_type','label'=>'What will you be selling?','type'=>'textarea','required'=>true],
        ['key'=>'booths','label'=>'Number of booth spaces requested','type'=>'number','required'=>true],
        ['key'=>'sponsor','label'=>'I am interested in being a sponsor','type'=>'checkbox','required'=>false],
    ];
}

/**
 * Frontend Vendor Application Form
 *
 * Shortcode: [oha_vendor_form]
 *
 * Unified form for new and returning vendors.
 * Returning vendors use the New/Returning toggle to look up their previous
 * application by email + Vendor ID, which pre-fills the form fields.
 * On submit, returning vendors with an existing Invited application for the
 * active year will have that application updated and promoted to Submitted.
 * Returning vendors without an existing application get a new post created
 * with their permanent VID carried forward.
 */
add_shortcode('oha_vendor_form', function(){
    wp_enqueue_style('oha-vhcpt');

    // Applications closed check.
    if (get_option('oha_vhcpt_apps_closed', 0)) {
        $closed_msg = get_option('oha_vhcpt_apps_closed_message', 'Vendor applications are currently closed. Please check back soon.');
        return '<div class="oha-vh-notice" style="padding:1.2em 1.5em;border-left:4px solid #b32020;background:#fff5f5;border-radius:0 4px 4px 0;margin:1em 0;">' . wp_kses_post($closed_msg) . '</div>';
    }

    $user   = wp_get_current_user();
    $fields = get_option('oha_vhcpt_fields', oha_vhcpt_fields_default());

    $field_map = [];
    foreach ($fields as $f) {
        $field_map[$f['key']] = $f;
    }

    $msg = '';

    // Success message (shown after redirect or direct return).
    if (isset($_GET['vendor_submitted']) && $_GET['vendor_submitted'] === 'success') {
        return '<div class="oha-vh-success">Your vendor application has been submitted successfully. We will review it and contact you soon at the email address you provided.</div>';
    }

    // Handle submission.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['oha_vhcpt_nonce']) && wp_verify_nonce($_POST['oha_vhcpt_nonce'], 'oha_vhcpt_submit')) {

        // Honeypot check.
        if (!empty($_POST['vendor_website_url'])) {
            return '<div class="oha-vh-success">Your vendor application has been submitted successfully. We will review it and contact you soon at the email address you provided.</div>';
        }

        // Returning vendor detection — set by JS after a successful AJAX lookup.
        $is_returning = isset($_POST['is_returning_verified']) && $_POST['is_returning_verified'] === '1';
        $source_vid   = $is_returning ? intval(preg_replace('/[^0-9]/', '', wp_unslash($_POST['source_vid'] ?? ''))) : 0;

        $meta   = [];
        $errors = [];

        foreach ($fields as $f) {
            $key = $f['key'];
            $raw = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';

            switch ($f['type']) {
                case 'email':
                    $meta[$key] = sanitize_email($raw);
                    break;
                case 'number':
                    $meta[$key] = intval($raw);
                    break;
                case 'checkbox':
                    $meta[$key] = isset($_POST[$key]) ? 1 : 0;
                    break;
                case 'textarea':
                    $meta[$key] = sanitize_textarea_field($raw);
                    break;
                default:
                    $meta[$key] = sanitize_text_field($raw);
                    break;
            }

            if (!empty($f['required'])) {
                if (empty($meta[$key]) && $meta[$key] !== '0' && $meta[$key] !== 0) {
                    $errors[] = sprintf('The field "%s" is required.', $f['label']);
                }
            }
        }

        // Normalize website URL.
        if (!empty($meta['website'])) {
            $url = trim($meta['website']);
            $url = preg_replace('/[\x{00AD}\x{200B}\x{FEFF}]/u', '', $url);
            $url = trim($url, " \t\n\r\0\x0B\"'\u{201C}\u{201D}\u{2018}\u{2019}");
            if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                $url = 'https://' . $url;
            }
            $meta['website'] = $url !== '' ? esc_url_raw($url) : '';
        }

        $private_notes    = sanitize_textarea_field(wp_unslash($_POST['vendor_private_notes'] ?? ''));
        $booth_preference = sanitize_textarea_field(wp_unslash($_POST['booth_preference'] ?? ''));
        $tables_needed    = max(0, intval(wp_unslash($_POST['tables_needed'] ?? -1)));
        $need_electric    = sanitize_text_field(wp_unslash($_POST['need_electric'] ?? ''));

        if (!isset($_POST['tables_needed'])) {
            $errors[] = 'The field "Number of tables needed" is required.';
        }
        if (!in_array($need_electric, ['yes', 'no'], true)) {
            $errors[] = 'Please indicate whether you need electrical access at your booth.';
        }

        if (!empty($errors)) {
            $msg = '<div class="oha-vh-error">Please correct the following errors:<ul>';
            foreach ($errors as $err) {
                $msg .= '<li>' . esc_html($err) . '</li>';
            }
            $msg .= '</ul></div>';
        } else {

            $active_year = intval(get_option('oha_vhcpt_active_year', date('Y')));
            $post_id     = 0;
            $is_upgrade  = false; // true when promoting an existing Invited app to Submitted

            // Verify source_vid is real before trusting it.
            if ($is_returning && $source_vid > 0) {
                $vid_exists = get_posts([
                    'post_type'      => 'vendor_application',
                    'post_status'    => 'publish',
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                    'meta_query'     => [
                        'relation' => 'AND',
                        ['key' => '_vendor_permanent_id', 'value' => $source_vid,   'compare' => '=', 'type' => 'NUMERIC'],
                        ['key' => 'email',                'value' => $meta['email'], 'compare' => '='],
                    ],
                ]);
                if (empty($vid_exists)) {
                    $is_returning = false;
                    $source_vid   = 0;
                }
            }

            if ($is_returning && $source_vid > 0) {
                // Check for an existing current-year application with this VID.
                $existing = get_posts([
                    'post_type'      => 'vendor_application',
                    'post_status'    => 'publish',
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                    'meta_query'     => [
                        'relation' => 'AND',
                        ['key' => '_vendor_permanent_id', 'value' => $source_vid, 'compare' => '=', 'type' => 'NUMERIC'],
                        ['key' => '_convention_year',     'value' => $active_year, 'compare' => '='],
                    ],
                ]);

                if (!empty($existing)) {
                    $existing_id     = $existing[0];
                    $existing_status = get_post_meta($existing_id, '_oha_status', true);
                    $locked_statuses = ['Submitted', 'Approved', 'Agreement Sent', 'Agreement Signed', 'Paid'];

                    if (in_array($existing_status, $locked_statuses, true)) {
                        $edit_url = get_permalink((int) get_option('oha_vhcpt_vendor_edit_page_id'));
                        $msg = '<div class="oha-vh-error">You already have an active application for this convention year (status: <strong>'
                             . esc_html($existing_status) . '</strong>). '
                             . ($edit_url ? 'To update your details, use the <a href="' . esc_url($edit_url) . '">vendor update page</a>. ' : '')
                             . 'For assistance, contact <a href="mailto:info@ohiohaunters.com">info@ohiohaunters.com</a>.</div>';
                    } else {
                        // Existing Invited application — update in place and promote to Submitted.
                        $post_id    = $existing_id;
                        $is_upgrade = true;
                    }
                } else {
                    // No current-year app — create a new post, carrying the permanent VID forward.
                    $title   = !empty($meta['company_name']) ? $meta['company_name'] : trim(($meta['first_name'] ?? '') . ' ' . ($meta['last_name'] ?? ''));
                    $post_id = wp_insert_post([
                        'post_type'   => 'vendor_application',
                        'post_status' => 'publish',
                        'post_title'  => $title ?: 'Vendor Application',
                        'post_author' => get_current_user_id(),
                    ], true);
                    if (!is_wp_error($post_id)) {
                        update_post_meta($post_id, '_convention_year',     $active_year);
                        update_post_meta($post_id, '_vendor_permanent_id', $source_vid);
                    }
                }

            } else {
                // New vendor path — duplicate email check.
                $recent_duplicate = new WP_Query([
                    'post_type'      => 'vendor_application',
                    'post_status'    => 'publish',
                    'meta_query'     => [['key' => 'email', 'value' => $meta['email'], 'compare' => '=']],
                    'date_query'     => [['after' => '10 minutes ago']],
                    'posts_per_page' => 1,
                ]);

                if ($recent_duplicate->have_posts()) {
                    wp_reset_postdata();
                    $msg = '<div class="oha-vh-error">We recently received an application from this email address. If you need to update your application, please contact us directly at <a href="mailto:info@ohiohaunters.com">info@ohiohaunters.com</a>.</div>';
                } else {
                    wp_reset_postdata();
                    $title   = !empty($meta['company_name']) ? $meta['company_name'] : trim(($meta['first_name'] ?? '') . ' ' . ($meta['last_name'] ?? ''));
                    $post_id = wp_insert_post([
                        'post_type'   => 'vendor_application',
                        'post_status' => 'publish',
                        'post_title'  => $title ?: 'Vendor Application',
                        'post_author' => get_current_user_id(),
                    ], true);
                    if (!is_wp_error($post_id)) {
                        update_post_meta($post_id, '_convention_year',     $active_year);
                        update_post_meta($post_id, '_vendor_permanent_id', $post_id); // self-referential for new vendors
                    }
                }
            }

            // Common save path — runs when we have a valid post ID.
            if (empty($msg) && $post_id && !is_wp_error($post_id)) {

                foreach ($meta as $k => $v) {
                    update_post_meta($post_id, $k, $v);
                }

                $new_title = !empty($meta['company_name']) ? $meta['company_name'] : trim(($meta['first_name'] ?? '') . ' ' . ($meta['last_name'] ?? ''));
                if ($new_title) {
                    wp_update_post(['ID' => $post_id, 'post_title' => $new_title]);
                }

                update_post_meta($post_id, 'returning_vendor',     ($is_returning && $source_vid > 0) ? 1 : 0);
                update_post_meta($post_id, 'vendor_private_notes', $private_notes);
                update_post_meta($post_id, 'booth_preference',     $booth_preference);
                update_post_meta($post_id, 'tables_needed',        $tables_needed);
                update_post_meta($post_id, 'need_electric',        $need_electric === 'yes' ? 1 : 0);

                if (!empty($_FILES['vendor_logo']['name']) && function_exists('oha_vh_handle_logo_upload')) {
                    oha_vh_handle_logo_upload($post_id);
                }

                update_post_meta($post_id, '_oha_status', 'Submitted');

                if (function_exists('oha_vhcpt_append_history')) {
                    if ($is_upgrade) {
                        oha_vhcpt_append_history($post_id, [
                            'type'        => 'status_change',
                            'description' => 'Returning vendor completed application via intake form. Status promoted from Invited to Submitted.',
                        ]);
                    } else {
                        oha_vhcpt_append_history($post_id, [
                            'type'        => 'application_submitted',
                            'description' => ($is_returning && $source_vid > 0)
                                ? 'Returning vendor submitted via intake form. Previous application data pre-filled.'
                                : 'Application submitted via vendor application form.',
                        ]);
                    }
                }

                if (function_exists('oha_vhcpt_maybe_send_status_email')) {
                    oha_vhcpt_maybe_send_status_email($post_id, 'Submitted');
                }

                return '<div class="oha-vh-success">Your vendor application has been submitted successfully. We will review it and contact you soon at the email address you provided.</div>';

            } elseif (empty($msg) && is_wp_error($post_id)) {
                $msg = '<div class="oha-vh-error">Error creating application: ' . esc_html($post_id->get_error_message()) . '</div>';
            }
        }
    }

    // Helper: render a single form field row.
    $render_field = function($key, $hint = '') use ($field_map, $user) {
        if (!isset($field_map[$key])) return;
        $f       = $field_map[$key];
        $id      = esc_attr($key);
        $label   = esc_html($f['label']);
        $req     = !empty($f['required']);
        $prefill = '';

        if ($key === 'first_name' && $user && $user->ID) $prefill = esc_attr($user->user_firstname);
        elseif ($key === 'last_name' && $user && $user->ID) $prefill = esc_attr($user->user_lastname);
        elseif ($key === 'email' && $user && $user->ID)     $prefill = esc_attr($user->user_email);

        echo '<div class="oha-vh-row">';
        echo '<label for="' . $id . '">' . $label . ($req ? ' <span class="oha-vh-req">*</span>' : '') . '</label>';

        if ($f['type'] === 'state') {
            echo '<select id="' . $id . '" name="' . $id . '"' . ($req ? ' required' : '') . '>';
            echo '<option value="">Select a state...</option>';
            echo oha_vhcpt_render_states();
            echo '</select>';
        } elseif ($f['type'] === 'checkbox') {
            echo '<label class="oha-vh-checkbox-label"><input type="checkbox" id="' . $id . '" name="' . $id . '" value="1"> Yes</label>';
        } elseif ($f['type'] === 'textarea') {
            echo '<textarea id="' . $id . '" name="' . $id . '" rows="4"' . ($req ? ' required' : '') . '>' . esc_textarea($prefill) . '</textarea>';
        } else {
            $type = $f['type'] === 'number' ? 'number' : 'text';
            echo '<input type="' . $type . '" id="' . $id . '" name="' . $id . '" value="' . $prefill . '"' . ($req ? ' required' : '') . '>';
        }

        if ($hint) {
            echo '<span class="oha-vh-hint">' . esc_html($hint) . '</span>';
        }

        echo '</div>';
    };

    $prefill_nonce   = wp_create_nonce('oha_vh_prefill');
    $ajax_url        = admin_url('admin-ajax.php');
    $vendor_edit_url = get_permalink((int) get_option('oha_vhcpt_vendor_edit_page_id'));

    ob_start();
    echo $msg;
    ?>
    <style>
    .oha-vh-section { margin-bottom: 2em; }
    .oha-vh-section-head { margin-bottom: 1em; padding-bottom: 0.5em; border-bottom: 2px solid #e0e0e0; }
    .oha-vh-section-title { font-size: 1.05em; font-weight: 700; margin: 0 0 0.25em; }
    .oha-vh-section-desc { font-size: 0.875em; margin: 0; }
    .oha-vh-badge-public { display: inline-block; font-size: 0.7em; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; background: #1a7a3a; color: #fff; padding: 2px 7px; border-radius: 3px; vertical-align: middle; margin-left: 6px; }
    .oha-vh-badge-private { display: inline-block; font-size: 0.7em; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; background: #555; color: #fff; padding: 2px 7px; border-radius: 3px; vertical-align: middle; margin-left: 6px; }
    .oha-vh-hint { display: block; font-size: 0.8em; color: #ffffff; margin-top: 4px; line-height: 1.4; }
    .oha-vh-req { color: #ffd700; }
    .oha-vh-checkbox-label { display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer; }
    .oha-vh-type-toggle { margin-bottom: 2em; padding: 1.75em 1.5em; background: rgba(173,49,25,0.08); border: 1px solid rgba(173,49,25,0.30); border-radius: 6px; text-align: center; }
    .oha-vh-toggle-label { font-size: 1.15em; font-weight: 700; margin: 0 0 1.1em; }
    .oha-vh-toggle-btns { display: flex; gap: 1em; flex-wrap: wrap; justify-content: center; }
    .oha-vh-toggle-btn { padding: .9em 2.75em; border: 2px solid rgba(173,49,25,0.5); border-radius: 6px; background: transparent; color: var(--global-palette4,#f7fafc); font-size: 1.1em; font-weight: 600; cursor: pointer; min-width: 190px; letter-spacing: 0.01em; }
    .oha-vh-toggle-btn.oha-active { background: var(--global-palette1,#ad3119); border-color: var(--global-palette1,#ad3119); color: #fff; font-weight: 700; box-shadow: 0 4px 14px rgba(173,49,25,0.45); }
    .oha-vh-returning-panel { margin-bottom: 2em; padding: 1.25em 1.5em; background: rgba(0,0,0,0.25); border: 1px solid rgba(173,49,25,0.30); border-radius: 4px; }
    .oha-vh-prefill-success { background: rgba(19,97,46,0.15); border: 1px solid rgba(19,97,46,0.45); border-left: 4px solid var(--global-palette11,#13612e); border-radius: 4px; padding: .75em 1em; margin: .75em 0; font-size: .9em; }
    .oha-vh-btn-secondary { display: inline-block; padding: .6em 1.4em; background: transparent; border: 2px solid var(--global-palette1,#ad3119); color: var(--global-palette1,#ad3119); border-radius: 4px; font-size: .95em; cursor: pointer; margin-top: .75em; }
    .oha-vh-forgot-vid { font-size: .85em; color: var(--global-palette7,#e76653); cursor: pointer; background: none; border: none; padding: 0; text-decoration: underline; }
    </style>

    <!-- New / Returning toggle (outside the form) -->
    <div class="oha-vh-type-toggle">
        <p class="oha-vh-toggle-label">Are you a new or returning vendor?</p>
        <div class="oha-vh-toggle-btns" role="group">
            <button type="button" class="oha-vh-toggle-btn oha-active" data-type="new">New Vendor</button>
            <button type="button" class="oha-vh-toggle-btn" data-type="returning">Returning Vendor</button>
        </div>
    </div>

    <form class="oha-vh-form" method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('oha_vhcpt_submit', 'oha_vhcpt_nonce'); ?>

        <!-- Honeypot -->
        <div style="position:absolute;left:-9999px;" aria-hidden="true">
            <label>Leave this field blank
                <input type="text" name="vendor_website_url" value="" tabindex="-1" autocomplete="off">
            </label>
        </div>

        <!-- Hidden fields populated by JS on successful returning vendor lookup -->
        <input type="hidden" name="source_vid" id="oha-source-vid" value="">
        <input type="hidden" name="is_returning_verified" id="oha-is-returning-verified" value="0">

        <!-- Returning vendor lookup panel (hidden until toggle is clicked) -->
        <div id="oha-returning-panel" class="oha-vh-returning-panel" style="display:none;">
            <div class="oha-vh-section-head">
                <p class="oha-vh-section-title">Find Your Previous Application</p>
                <p class="oha-vh-section-desc">Enter your email address and Vendor ID to pre-fill this form with your information from your previous application. Your Vendor ID was included in your original confirmation email as "VID-" followed by a number.</p>
            </div>
            <div class="oha-vh-row">
                <label for="oha-ret-email">Email address from your previous application <span class="oha-vh-req">*</span></label>
                <input type="email" id="oha-ret-email" autocomplete="email" placeholder="you@example.com">
            </div>
            <div class="oha-vh-row">
                <label for="oha-ret-vid">Your Vendor ID <span class="oha-vh-req">*</span></label>
                <input type="text" id="oha-ret-vid" placeholder="e.g. VID-123 or just 123">
                <span class="oha-vh-hint">Don't know your Vendor ID?
                    <button type="button" class="oha-vh-forgot-vid" id="oha-forgot-vid-btn">Send it to my email</button>
                </span>
            </div>
            <div id="oha-prefill-notice"></div>
            <button type="button" class="oha-vh-btn-secondary" id="oha-prefill-btn">Find My Application</button>
        </div>

        <?php /* ── SECTION 1: PUBLIC BUSINESS INFORMATION ── */ ?>
        <div class="oha-vh-section">
            <div class="oha-vh-section-head">
                <p class="oha-vh-section-title">Business Information <span class="oha-vh-badge-public">Shown Publicly</span></p>
                <p class="oha-vh-section-desc">This information appears on the OHA website vendor listing, the convention map, and in the convention app. Make sure it represents your business accurately.</p>
            </div>
            <?php $render_field('company_name', 'Your business or brand name as it will appear publicly. Use your vendor name here - not your personal name.'); ?>
            <?php $render_field('website', 'Your business website or Facebook page. Enter the full address including https:// -- for example: https://www.yourbusiness.com or https://www.facebook.com/yourpage. Leave blank if you do not have one.'); ?>
            <?php $render_field('product_type', 'Describe what you will be selling at the convention. This description appears on your public vendor card - be specific so attendees can find you.'); ?>
            <div class="oha-vh-row">
                <label for="vendor_logo">Company Logo <span class="oha-vh-badge-public">Shown Publicly</span></label>
                <input type="file" id="vendor_logo" name="vendor_logo" accept="image/jpeg,image/png,image/gif">
                <span class="oha-vh-hint">Your logo appears on your vendor card, the convention map, and in the convention app. JPG or PNG recommended, max 5MB. Optional now - you can update it at any time via the edit link sent after your application is reviewed.</span>
                <img id="oha-intake-logo-preview" src="" alt="" style="max-width:160px;max-height:160px;display:none;margin-top:0.75em;border:1px solid #ddd;border-radius:4px;object-fit:contain;">
            </div>
        </div>

        <?php /* ── SECTION 2: CONTACT INFORMATION ── */ ?>
        <div class="oha-vh-section">
            <div class="oha-vh-section-head">
                <p class="oha-vh-section-title">Your Contact Information <span class="oha-vh-badge-private">Board Only</span></p>
                <p class="oha-vh-section-desc">This information is seen only by the OHA board. It is never displayed publicly and is used for communication and agreement purposes only.</p>
            </div>
            <?php $render_field('first_name'); ?>
            <?php $render_field('last_name'); ?>
            <?php $render_field('email', 'We will send your application updates and agreement to this address.'); ?>
            <?php $render_field('phone'); ?>
            <?php $render_field('address_street'); ?>
            <?php $render_field('address_city'); ?>
            <?php $render_field('address_state'); ?>
            <?php $render_field('address_zip'); ?>
        </div>

        <?php /* ── SECTION 3: CONVENTION DETAILS ── */ ?>
        <div class="oha-vh-section">
            <div class="oha-vh-section-head">
                <p class="oha-vh-section-title">Convention Details</p>
            </div>
            <?php $render_field('booths', 'Each booth space is 8x10. Contact us if you need clarification before submitting.'); ?>
            <div class="oha-vh-row">
                <label for="tables_needed">Number of tables needed <span class="oha-vh-req">*</span></label>
                <input type="number" id="tables_needed" name="tables_needed" value="0" min="0" max="10" required>
                <span class="oha-vh-hint">Enter the number of tables you need at your booth. Enter 0 if you are bringing your own or do not need any.</span>
            </div>
            <div class="oha-vh-row">
                <label for="need_electric">Do you need electrical access at your booth? <span class="oha-vh-req">*</span></label>
                <select id="need_electric" name="need_electric" required>
                    <option value="">Select...</option>
                    <option value="yes">Yes</option>
                    <option value="no">No</option>
                </select>
            </div>
            <?php $render_field('sponsor'); ?>
        </div>

        <?php /* ── SECTION 4: BOOTH PREFERENCE ── */ ?>
        <div class="oha-vh-section">
            <div class="oha-vh-section-head">
                <p class="oha-vh-section-title">Booth Preference <span class="oha-vh-badge-private">Board Only</span></p>
                <p class="oha-vh-section-desc">If you have a preferred location or would like to be near a specific area, let us know. We will do our best to accommodate requests based on availability.</p>
            </div>
            <div class="oha-vh-row">
                <label for="booth_preference">Booth preference or location notes (optional)</label>
                <textarea id="booth_preference" name="booth_preference" rows="3" placeholder="Example: I would like to keep my booth from last year. Or: I would prefer to be near the main entrance."></textarea>
            </div>
        </div>

        <?php /* ── SECTION 5: PRIVATE NOTES ── */ ?>
        <div class="oha-vh-section">
            <div class="oha-vh-section-head">
                <p class="oha-vh-section-title">Note to the OHA Board <span class="oha-vh-badge-private">Board Only</span></p>
                <p class="oha-vh-section-desc">Share anything the board should know - accessibility needs, questions, or special requests. This message is seen only by the board and is never shared publicly.</p>
            </div>
            <div class="oha-vh-row">
                <label for="vendor_private_notes">Notes (optional)</label>
                <textarea id="vendor_private_notes" name="vendor_private_notes" rows="4" placeholder="Example: I would prefer to be near my business partner who is also applying. Their business name is..."></textarea>
            </div>
        </div>

        <button type="submit" class="oha-vh-btn" id="oha-submit-btn">Submit Application</button>
    </form>

    <script>
    (function(){
        var ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
        var nonce   = <?php echo wp_json_encode($prefill_nonce); ?>;
        var editUrl = <?php echo wp_json_encode($vendor_edit_url ?: ''); ?>;

        // ── New / Returning toggle ─────────────────────────────────────────────
        var toggleBtns     = document.querySelectorAll('.oha-vh-toggle-btn');
        var returningPanel = document.getElementById('oha-returning-panel');

        toggleBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                toggleBtns.forEach(function(b) { b.classList.remove('oha-active'); });
                this.classList.add('oha-active');
                if (this.getAttribute('data-type') === 'returning') {
                    returningPanel.style.display = 'block';
                } else {
                    returningPanel.style.display = 'none';
                    document.getElementById('oha-source-vid').value          = '';
                    document.getElementById('oha-is-returning-verified').value = '0';
                    document.getElementById('oha-prefill-notice').innerHTML  = '';
                }
            });
        });

        // ── Returning vendor lookup ────────────────────────────────────────────
        var prefillBtn    = document.getElementById('oha-prefill-btn');
        var prefillNotice = document.getElementById('oha-prefill-notice');

        function showMsg(html) { if (prefillNotice) prefillNotice.innerHTML = html; }

        if (prefillBtn) {
            prefillBtn.addEventListener('click', function() {
                var email = document.getElementById('oha-ret-email').value.trim();
                var vid   = document.getElementById('oha-ret-vid').value.trim();

                if (!email || !vid) {
                    showMsg('<div class="oha-vh-error">Please enter both your email address and Vendor ID.</div>');
                    return;
                }

                prefillBtn.disabled    = true;
                prefillBtn.textContent = 'Looking up...';
                showMsg('');

                var fd = new FormData();
                fd.append('action', 'oha_vh_prefill_lookup');
                fd.append('nonce',  nonce);
                fd.append('email',  email);
                fd.append('vid',    vid);

                fetch(ajaxUrl, { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(resp) {
                        prefillBtn.disabled    = false;
                        prefillBtn.textContent = 'Find My Application';

                        if (!resp.success) {
                            showMsg('<div class="oha-vh-error">' + resp.data.message + '</div>');
                            return;
                        }

                        var d = resp.data;
                        var locked = ['Submitted','Approved','Agreement Sent','Agreement Signed','Paid'];

                        // Warn if already has an active application this year.
                        if (d.existing_year_app && locked.indexOf(d.existing_year_status) !== -1) {
                            var editLink = editUrl ? ' To update your details, use the <a href="' + editUrl + '">vendor update page</a>.' : '';
                            showMsg('<div class="oha-vh-error">You already have an active application for this convention year (status: <strong>' + d.existing_year_status + '</strong>).' + editLink + '</div>');
                            return;
                        }

                        // Set hidden verification fields.
                        document.getElementById('oha-source-vid').value           = d.vid;
                        document.getElementById('oha-is-returning-verified').value = '1';

                        // Pre-fill text / number / textarea fields by name.
                        ['company_name','website','product_type','first_name','last_name',
                         'email','phone','address_street','address_city','address_zip',
                         'booths','booth_preference','vendor_private_notes'
                        ].forEach(function(name) {
                            var el = document.querySelector('[name="' + name + '"]');
                            if (el && d[name] !== undefined && d[name] !== null) el.value = d[name];
                        });

                        // tables_needed: default to 0 if source record predates this field.
                        var tnEl = document.querySelector('[name="tables_needed"]');
                        if (tnEl) tnEl.value = (d.tables_needed !== undefined && d.tables_needed !== null && d.tables_needed !== '') ? d.tables_needed : '0';

                        // State dropdown.
                        if (d.address_state) {
                            var stEl = document.querySelector('[name="address_state"]');
                            if (stEl) stEl.value = d.address_state;
                        }

                        // Electrical select (stored as 1/0, form uses yes/no).
                        var elEl = document.getElementById('need_electric');
                        if (elEl && d.need_electric !== undefined) {
                            elEl.value = (d.need_electric === '1' || d.need_electric === 1) ? 'yes' : 'no';
                        }

                        // Sponsor checkbox.
                        var spEl = document.querySelector('[name="sponsor"]');
                        if (spEl) spEl.checked = (d.sponsor === '1' || d.sponsor === 1);

                        // Logo preview.
                        if (d.logo_url) {
                            var prev = document.getElementById('oha-intake-logo-preview');
                            if (prev) { prev.src = d.logo_url; prev.style.display = 'block'; }
                        }

                        var note = d.existing_year_app
                            ? 'Your information has been pre-filled. This will update your existing application for this convention year.'
                            : 'Your information has been pre-filled from your previous application. Review and update as needed, then submit.';
                        showMsg('<div class="oha-vh-prefill-success">' + note + '</div>');

                        var firstSection = document.querySelector('.oha-vh-section');
                        if (firstSection) firstSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    })
                    .catch(function() {
                        prefillBtn.disabled    = false;
                        prefillBtn.textContent = 'Find My Application';
                        showMsg('<div class="oha-vh-error">An error occurred. Please try again.</div>');
                    });
            });
        }

        // ── Forgot VID ────────────────────────────────────────────────────────
        var forgotBtn = document.getElementById('oha-forgot-vid-btn');
        if (forgotBtn) {
            forgotBtn.addEventListener('click', function() {
                var email = document.getElementById('oha-ret-email').value.trim();
                if (!email) {
                    showMsg('<div class="oha-vh-error">Please enter your email address first, then click the link to send your Vendor ID.</div>');
                    return;
                }

                forgotBtn.disabled    = true;
                forgotBtn.textContent = 'Sending...';

                var fd = new FormData();
                fd.append('action', 'oha_vh_send_vid_reminder');
                fd.append('nonce',  nonce);
                fd.append('email',  email);

                fetch(ajaxUrl, { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(resp) {
                        forgotBtn.disabled    = false;
                        forgotBtn.textContent = 'Send it to my email';
                        if (resp.success) {
                            showMsg('<div class="oha-vh-prefill-success">' + resp.data.message + '</div>');
                        } else {
                            showMsg('<div class="oha-vh-error">' + resp.data.message + '</div>');
                        }
                    })
                    .catch(function() {
                        forgotBtn.disabled    = false;
                        forgotBtn.textContent = 'Send it to my email';
                    });
            });
        }

        // ── Logo file preview ─────────────────────────────────────────────────
        var logoInput   = document.getElementById('vendor_logo');
        var logoPreview = document.getElementById('oha-intake-logo-preview');
        if (logoInput && logoPreview) {
            logoInput.addEventListener('change', function(){
                if (this.files && this.files[0]) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        logoPreview.src = e.target.result;
                        logoPreview.style.display = 'block';
                    };
                    reader.readAsDataURL(this.files[0]);
                } else {
                    logoPreview.src = '';
                    logoPreview.style.display = 'none';
                }
            });
        }

        // ── Submit guard (prevent double-click) ───────────────────────────────
        var form = document.querySelector('.oha-vh-form');
        var btn  = document.getElementById('oha-submit-btn');
        if (form && btn) {
            var submitted = false;
            form.addEventListener('submit', function(e){
                if (submitted) { e.preventDefault(); return false; }
                submitted = true;
                btn.disabled    = true;
                btn.textContent = 'Submitting...';
                setTimeout(function(){
                    submitted       = false;
                    btn.disabled    = false;
                    btn.textContent = 'Submit Application';
                }, 5000);
            });
        }
    })();
    </script>
    <?php
    return ob_get_clean();
});
