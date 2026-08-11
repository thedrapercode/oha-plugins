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
 * NOTE: This form is intentionally open to the public (no login required).
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

    // Build a key => config map for easy lookup.
    $field_map = [];
    foreach ($fields as $f) {
        $field_map[$f['key']] = $f;
    }

    $msg = '';

    // Success redirect (Post/Redirect/Get pattern).
    if (isset($_GET['vendor_submitted']) && $_GET['vendor_submitted'] === 'success') {
        return '<div class="oha-vh-success">Your vendor application has been submitted successfully. We will review it and contact you soon at the email address you provided.</div>';
    }

    // Handle submission.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['oha_vhcpt_nonce']) && wp_verify_nonce($_POST['oha_vhcpt_nonce'], 'oha_vhcpt_submit')) {

        // Honeypot check.
        if (!empty($_POST['vendor_website_url'])) {
            return '<div class="oha-vh-success">Your vendor application has been submitted successfully. We will review it and contact you soon at the email address you provided.</div>';
        }

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

        // Normalize website URL — add https:// if protocol is missing, strip junk characters.
        if (!empty($meta['website'])) {
            $url = trim($meta['website']);
            // Strip common copy-paste artifacts: smart quotes, zero-width spaces, trailing slashes noise.
            $url = preg_replace('/[\x{00AD}\x{200B}\x{FEFF}]/u', '', $url);
            $url = trim($url, " \t\n\r\0\x0B\"'\u{201C}\u{201D}\u{2018}\u{2019}");
            if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                $url = 'https://' . $url;
            }
            $meta['website'] = $url !== '' ? esc_url_raw($url) : '';
        }

        // Private notes (not in $fields loop — always optional, always textarea).
        $private_notes   = sanitize_textarea_field(wp_unslash($_POST['vendor_private_notes'] ?? ''));
        $returning_vendor = isset($_POST['returning_vendor']) ? 1 : 0;
        $booth_preference = sanitize_textarea_field(wp_unslash($_POST['booth_preference'] ?? ''));

        // Tables needed and electrical access — required, not in $fields loop.
        $tables_needed = max(0, intval(wp_unslash($_POST['tables_needed'] ?? -1)));
        $need_electric = sanitize_text_field(wp_unslash($_POST['need_electric'] ?? ''));

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
            // Duplicate check — same email within last 10 minutes.
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
                $title = !empty($meta['company_name'])
                    ? $meta['company_name']
                    : trim(($meta['first_name'] ?? '') . ' ' . ($meta['last_name'] ?? ''));
                if ($title === '') $title = 'Vendor Application';

                $post_id = wp_insert_post([
                    'post_type'   => 'vendor_application',
                    'post_status' => 'publish',
                    'post_title'  => $title,
                    'post_author' => get_current_user_id(),
                ], true);

                if (is_wp_error($post_id)) {
                    $msg = '<div class="oha-vh-error">Error creating application: ' . esc_html($post_id->get_error_message()) . '</div>';
                } else {
                    foreach ($meta as $k => $v) {
                        update_post_meta($post_id, $k, $v);
                    }

                    if (!empty($private_notes)) {
                        update_post_meta($post_id, 'vendor_private_notes', $private_notes);
                    }
                    update_post_meta($post_id, 'returning_vendor', $returning_vendor);
                    if (!empty($booth_preference)) {
                        update_post_meta($post_id, 'booth_preference', $booth_preference);
                    }
                    update_post_meta($post_id, 'tables_needed', $tables_needed);
                    update_post_meta($post_id, 'need_electric', $need_electric === 'yes' ? 1 : 0);

                    // Handle optional logo upload.
                    if (!empty($_FILES['vendor_logo']['name']) && function_exists('oha_vh_handle_logo_upload')) {
                        oha_vh_handle_logo_upload($post_id);
                    }

                    update_post_meta($post_id, '_oha_status', 'Submitted');

                    // Stamp convention year and assign permanent vendor ID on every new submission.
                    $active_year = intval( get_option('oha_vhcpt_active_year', date('Y')) );
                    update_post_meta($post_id, '_convention_year', $active_year);
                    update_post_meta($post_id, '_vendor_permanent_id', $post_id);

                    // Log the initial submission in history.
                    if (function_exists('oha_vhcpt_append_history')) {
                        oha_vhcpt_append_history($post_id, [
                            'type'        => 'application_submitted',
                            'description' => 'Application submitted via vendor application form.',
                        ]);
                    }

                    if (function_exists('oha_vhcpt_maybe_send_status_email')) {
                        oha_vhcpt_maybe_send_status_email($post_id, 'Submitted');
                    }

                    return '<div class="oha-vh-success">Your vendor application has been submitted successfully. We will review it and contact you soon at the email address you provided.</div>';
                }
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
    .oha-vh-booth-notice { background: rgba(173, 49, 25, 0.08); border: 1px solid rgba(173, 49, 25, 0.30); border-left: 4px solid var(--global-palette1, #ad3119); border-radius: 4px; padding: 1em 1.25em; margin-bottom: 1.25em; }
    .oha-vh-booth-notice p { margin: 0 0 0.5em; font-size: 0.9em; line-height: 1.5; color: var(--global-palette1, #ad3119); }
    .oha-vh-booth-notice p:last-child { margin-bottom: 0; }
    </style>

    <form class="oha-vh-form" method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('oha_vhcpt_submit', 'oha_vhcpt_nonce'); ?>

        <!-- Honeypot -->
        <div style="position:absolute;left:-9999px;" aria-hidden="true">
            <label>Leave this field blank
                <input type="text" name="vendor_website_url" value="" tabindex="-1" autocomplete="off">
            </label>
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
            <?php $render_field('booths', 'Each booth space is 10x10. Contact us if you need clarification before submitting.'); ?>
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

        <?php /* ── SECTION 4: BOOTH PREFERENCE (RETURNING VENDORS) ── */ ?>
        <div class="oha-vh-section">
            <div class="oha-vh-section-head">
                <p class="oha-vh-section-title">Booth Preference <span class="oha-vh-badge-private">Board Only</span></p>
            </div>
            <div class="oha-vh-booth-notice">
                <p><strong>Returning vendors receive first choice on booth location.</strong></p>
                <p>If you vended with us at a previous OHA convention, you may request to keep your current spot or indicate a preference for a new one. New vendors are welcome to note any preferences as well - we will do our best to accommodate requests based on availability.</p>
            </div>
            <div class="oha-vh-row">
                <label class="oha-vh-checkbox-label">
                    <input type="checkbox" name="returning_vendor" value="1">
                    I am a returning vendor from a previous OHA convention
                </label>
            </div>
            <div class="oha-vh-row" style="margin-top:0.75em">
                <label for="booth_preference">Booth preference or location notes (optional)</label>
                <textarea id="booth_preference" name="booth_preference" rows="3" placeholder="Example: I would like to keep booth 14 from last year. Or: I would prefer to be near the main entrance."></textarea>
            </div>
        </div>

        <?php /* ── SECTION 5: PRIVATE NOTES ── */ ?>
        <div class="oha-vh-section">
            <div class="oha-vh-section-head">
                <p class="oha-vh-section-title">Note to the OHA Board <span class="oha-vh-badge-private">Board Only</span></p>
                <p class="oha-vh-section-desc">Share anything the board should know - booth placement preferences, accessibility needs, questions, or special requests. This message is seen only by the board and is never shared publicly.</p>
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
    })();
    (function(){
        var form = document.querySelector('.oha-vh-form');
        var btn  = document.getElementById('oha-submit-btn');
        if (form && btn) {
            var submitted = false;
            form.addEventListener('submit', function(e){
                if (submitted) { e.preventDefault(); return false; }
                submitted = true;
                btn.disabled = true;
                btn.textContent = 'Submitting...';
                setTimeout(function(){
                    submitted = false;
                    btn.disabled = false;
                    btn.textContent = 'Submit Application';
                }, 5000);
            });
        }
    })();
    </script>
    <?php
    return ob_get_clean();
});
