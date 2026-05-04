<?php
if (!defined('ABSPATH')) exit;

/**
 * Vendor Self-Edit Shortcode
 *
 * Shortcode: [oha_vendor_edit]
 *
 * Access flow:
 *  - Logged-in user: finds app by post_author or email meta, shows form directly
 *  - Non-logged-in: shows email form -> sends magic link -> token validates access
 *
 * Editable fields: contact info + marketing fields (not booth count or sponsor)
 * Status lock: product_type is read-only once status is Agreement Signed or Paid
 */

/**
 * Generate and store a 32-char edit token on the vendor application post.
 * Overwrites any existing token.
 */
function oha_vh_generate_edit_token($app_id) {
    $token = wp_generate_password(32, false);
    update_post_meta($app_id, '_edit_token', $token);
    update_post_meta($app_id, '_edit_token_expires', date('Y-m-d H:i:s', strtotime('+24 hours')));
    return $token;
}

/**
 * Find vendor application by _edit_token + expiry check.
 * Returns post ID or 0.
 */
function oha_vh_get_app_by_token($token) {
    if (empty($token) || strlen($token) !== 32) {
        return 0;
    }
    $token = sanitize_text_field($token);
    $apps = get_posts([
        'post_type'      => 'vendor_application',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => '_edit_token',         'value' => $token,                    'compare' => '='],
            ['key' => '_edit_token_expires',  'value' => current_time('mysql'),    'compare' => '>'],
        ],
    ]);
    return !empty($apps) ? $apps[0]->ID : 0;
}

/**
 * Find vendor application for a logged-in user.
 * Prefers post_author match; falls back to email meta match.
 */
function oha_vh_get_app_for_user() {
    $user = wp_get_current_user();
    if (!$user || !$user->ID) {
        return 0;
    }

    // Try post_author first
    $apps = get_posts([
        'post_type'      => 'vendor_application',
        'post_status'    => 'any',
        'author'         => $user->ID,
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);
    if (!empty($apps)) {
        return $apps[0]->ID;
    }

    // Fall back to email meta match
    $apps = get_posts([
        'post_type'      => 'vendor_application',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [
            ['key' => 'email', 'value' => $user->user_email, 'compare' => '='],
        ],
    ]);
    return !empty($apps) ? $apps[0]->ID : 0;
}

/**
 * Send magic link email to vendor.
 *
 * Subject and body are read from WP options (editable in Vendor Hub Settings).
 * Placeholders: {first_name}, {link}
 *
 * @param string $email         Recipient email address.
 * @param string $token         32-char edit token.
 * @param string $edit_page_url URL of the page containing [oha_vendor_edit].
 * @param string $first_name    Optional vendor first name for {first_name} placeholder.
 */
function oha_vh_send_edit_link($email, $token, $edit_page_url, $first_name = '') {
    $link = add_query_arg('edit_token', $token, $edit_page_url);

    $default_subject = 'Update Your Vendor Information - Ohio Haunters Convention';
    $default_body    = "Hello {first_name},\n\nHere is a link to update your vendor information for the Ohio Haunters Convention.\n\n{link}\n\nThis link is valid for 24 hours. After it expires, you can request a new one by visiting the same page and entering your email address again.\n\nIf you did not request this link, you can safely ignore this email.\n\nQuestions? Contact us at info@ohiohaunters.com\n\n-- The OHA Team";

    $subject = get_option('oha_vhcpt_edit_link_subject', $default_subject);
    $body    = get_option('oha_vhcpt_edit_link_body',    $default_body);

    $subject = str_replace(['{first_name}', '{link}'], [$first_name, $link], $subject);
    $body    = str_replace(['{first_name}', '{link}'], [$first_name, $link], $body);

    wp_mail($email, $subject, $body);
}

/**
 * Handle logo upload for a vendor application.
 * Returns error string or empty string on success.
 */
function oha_vh_handle_logo_upload($app_id) {
    if (empty($_FILES['vendor_logo']['name'])) {
        return '';
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $upload = wp_handle_upload($_FILES['vendor_logo'], ['test_form' => false]);

    if (!empty($upload['error'])) {
        return $upload['error'];
    }

    $attach_id = wp_insert_attachment([
        'post_mime_type' => $upload['type'],
        'post_title'     => sanitize_file_name($_FILES['vendor_logo']['name']),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ], $upload['file']);

    if (is_wp_error($attach_id)) {
        return $attach_id->get_error_message();
    }

    $metadata = wp_generate_attachment_metadata($attach_id, $upload['file']);
    wp_update_attachment_metadata($attach_id, $metadata);

    update_post_meta($app_id, '_company_logo_id', $attach_id);
    update_post_meta($app_id, '_company_logo_url', $upload['url']);

    return '';
}

add_shortcode('oha_vendor_edit', function() {
    wp_enqueue_style('oha-vhcpt');

    $current_url = get_permalink() ?: home_url(add_query_arg(null, null));

    // ── Determine access path ────────────────────────────────────────────────

    $app_id    = 0;
    $auth_mode = ''; // 'token' | 'user'
    $token_val = isset($_GET['edit_token']) ? sanitize_text_field($_GET['edit_token']) : '';

    if ($token_val) {
        $app_id    = oha_vh_get_app_by_token($token_val);
        $auth_mode = $app_id ? 'token' : '';
    } elseif (is_user_logged_in()) {
        $app_id    = oha_vh_get_app_for_user();
        $auth_mode = $app_id ? 'user' : '';
    }

    // ── POST: handle email request (magic link send) ─────────────────────────

    $notice = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['oha_vh_edit_email_nonce'])) {
        if (!wp_verify_nonce($_POST['oha_vh_edit_email_nonce'], 'oha_vh_edit_email_request')) {
            $notice = '<div class="oha-vh-error">Security check failed. Please try again.</div>';
        } else {
            $submitted_email = sanitize_email(wp_unslash($_POST['vendor_lookup_email'] ?? ''));
            $rate_key        = 'oha_vh_edit_req_' . md5($submitted_email);

            if (get_transient($rate_key)) {
                $notice = '<div class="oha-vh-success">An email was recently sent to that address. Please check your inbox (and spam folder). You can request a new link in a few minutes.</div>';
            } else {
                // Always show the same message regardless of whether the email was found
                $notice = '<div class="oha-vh-success">If an application was found for that email address, we\'ve sent a link to update your vendor information. Please check your inbox (and spam folder).</div>';

                $apps = get_posts([
                    'post_type'      => 'vendor_application',
                    'post_status'    => 'any',
                    'posts_per_page' => 1,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                    'meta_query'     => [
                        ['key' => 'email', 'value' => $submitted_email, 'compare' => '='],
                    ],
                ]);

                if (!empty($apps)) {
                    $found_id   = $apps[0]->ID;
                    $new_token  = oha_vh_generate_edit_token($found_id);
                    $edit_url   = trailingslashit(get_permalink() ?: home_url('/'));
                    $first_name = get_post_meta($found_id, 'first_name', true);
                    oha_vh_send_edit_link($submitted_email, $new_token, $edit_url, $first_name);
                }

                set_transient($rate_key, 1, 5 * MINUTE_IN_SECONDS);
            }
        }

        // After email request POST, always show the email form (no redirect to avoid re-send on refresh)
        ob_start();
        echo $notice;
        oha_vh_render_email_form($current_url);
        return ob_get_clean();
    }

    // ── POST: handle edit form save ──────────────────────────────────────────

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['oha_vh_edit_nonce']) && $app_id) {
        $posted_nonce = $_POST['oha_vh_edit_nonce'];
        $posted_token = sanitize_text_field($_POST['edit_token'] ?? '');

        // Re-validate access
        $access_ok = false;
        if (wp_verify_nonce($posted_nonce, 'oha_vhcpt_vendor_edit_' . $app_id)) {
            if ($auth_mode === 'user') {
                $access_ok = true;
            } elseif ($auth_mode === 'token' && $posted_token) {
                // Re-check token is still valid
                $access_ok = (oha_vh_get_app_by_token($posted_token) === $app_id);
            }
        }

        if (!$access_ok) {
            $notice = '<div class="oha-vh-error">Session expired or invalid. Please use your email link again.</div>';
            $app_id = 0;
        } else {
            $status        = get_post_meta($app_id, '_oha_status', true);
            $locked        = in_array($status, ['Agreement Signed', 'Paid'], true);
            $errors        = [];

            // Sanitize and validate fields
            $fields_to_save = [
                'first_name'     => sanitize_text_field(wp_unslash($_POST['first_name'] ?? '')),
                'last_name'      => sanitize_text_field(wp_unslash($_POST['last_name'] ?? '')),
                'company_name'   => sanitize_text_field(wp_unslash($_POST['company_name'] ?? '')),
                'email'          => sanitize_email(wp_unslash($_POST['email'] ?? '')),
                'phone'          => sanitize_text_field(wp_unslash($_POST['phone'] ?? '')),
                'address_street' => sanitize_text_field(wp_unslash($_POST['address_street'] ?? '')),
                'address_city'   => sanitize_text_field(wp_unslash($_POST['address_city'] ?? '')),
                'address_state'  => sanitize_text_field(wp_unslash($_POST['address_state'] ?? '')),
                'address_zip'    => sanitize_text_field(wp_unslash($_POST['address_zip'] ?? '')),
                'website'        => sanitize_text_field(wp_unslash($_POST['website'] ?? '')),
            ];

            if (!$locked) {
                $fields_to_save['product_type'] = sanitize_textarea_field(wp_unslash($_POST['product_type'] ?? ''));
            }

            // Required field check
            foreach (['first_name', 'last_name', 'company_name', 'email', 'phone'] as $req) {
                if (empty($fields_to_save[$req])) {
                    $errors[] = ucwords(str_replace('_', ' ', $req)) . ' is required.';
                }
            }

            if (!empty($errors)) {
                $notice = '<div class="oha-vh-error">Please correct the following:<ul><li>' . implode('</li><li>', array_map('esc_html', $errors)) . '</li></ul></div>';
            } else {
                // Save text fields
                foreach ($fields_to_save as $key => $val) {
                    update_post_meta($app_id, $key, $val);
                }

                // Update post title if company name changed
                wp_update_post(['ID' => $app_id, 'post_title' => $fields_to_save['company_name']]);

                // Handle logo
                $logo_error = '';
                if (!empty($_FILES['vendor_logo']['name'])) {
                    $logo_error = oha_vh_handle_logo_upload($app_id);
                } elseif (!empty($_POST['remove_logo'])) {
                    delete_post_meta($app_id, '_company_logo_id');
                    delete_post_meta($app_id, '_company_logo_url');
                }

                if ($logo_error) {
                    $notice = '<div class="oha-vh-error">Info saved, but logo upload failed: ' . esc_html($logo_error) . '</div>';
                } else {
                    $notice = '<div class="oha-vh-success">Your vendor information has been updated successfully.</div>';
                }
            }
        }
    }

    // ── Render ───────────────────────────────────────────────────────────────

    ob_start();
    echo $notice;

    if (!$app_id) {
        // Token was in URL but invalid/expired
        if ($token_val && !$app_id) {
            echo '<div class="oha-vh-error"><strong>This link has expired or is invalid.</strong> Please request a new link below.</div>';
        }
        oha_vh_render_email_form($current_url);
        return ob_get_clean();
    }

    // Show edit form
    $status  = get_post_meta($app_id, '_oha_status', true);
    $locked  = in_array($status, ['Agreement Signed', 'Paid'], true);

    // Read current values (use POST values if there were validation errors)
    $v = [];
    $text_fields = ['first_name','last_name','company_name','email','phone',
                    'address_street','address_city','address_state','address_zip','website','product_type'];
    foreach ($text_fields as $f) {
        $v[$f] = (isset($_POST[$f]) && $_SERVER['REQUEST_METHOD'] === 'POST')
            ? esc_attr(wp_unslash($_POST[$f]))
            : esc_attr(get_post_meta($app_id, $f, true));
    }

    $logo_id  = get_post_meta($app_id, '_company_logo_id', true);
    $logo_url = get_post_meta($app_id, '_company_logo_url', true);
    $logo_img = $logo_id ? wp_get_attachment_image_src((int)$logo_id, [150, 150]) : null;
    ?>
    <div class="oha-vh-edit-wrap">
        <h3>Update Your Vendor Information</h3>
        <?php if ($locked): ?>
            <p class="oha-vh-status-note">Your application is currently <strong><?php echo esc_html($status); ?></strong>. Contact info and logo can still be updated, but your product description is locked.</p>
        <?php endif; ?>

        <form class="oha-vh-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('oha_vhcpt_vendor_edit_' . $app_id, 'oha_vh_edit_nonce'); ?>
            <input type="hidden" name="edit_token" value="<?php echo esc_attr($token_val); ?>">

            <h4 style="margin:1.5em 0 0.5em;">Contact Information</h4>

            <div class="oha-vh-row">
                <label for="ve_first_name">First Name *</label>
                <input type="text" id="ve_first_name" name="first_name" value="<?php echo $v['first_name']; ?>" required>
            </div>
            <div class="oha-vh-row">
                <label for="ve_last_name">Last Name *</label>
                <input type="text" id="ve_last_name" name="last_name" value="<?php echo $v['last_name']; ?>" required>
            </div>
            <div class="oha-vh-row">
                <label for="ve_company_name">Company Name *</label>
                <input type="text" id="ve_company_name" name="company_name" value="<?php echo $v['company_name']; ?>" required>
            </div>
            <div class="oha-vh-row">
                <label for="ve_email">Email *</label>
                <input type="email" id="ve_email" name="email" value="<?php echo $v['email']; ?>" required>
            </div>
            <div class="oha-vh-row">
                <label for="ve_phone">Phone *</label>
                <input type="text" id="ve_phone" name="phone" value="<?php echo $v['phone']; ?>">
            </div>

            <h4 style="margin:1.5em 0 0.5em;">Address</h4>

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
                    <option value="">Select&hellip;</option>
                    <?php
                    $states_html = oha_vhcpt_render_states();
                    if ($v['address_state']) {
                        $states_html = str_replace(
                            'value="' . esc_attr($v['address_state']) . '"',
                            'value="' . esc_attr($v['address_state']) . '" selected',
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

            <h4 style="margin:1.5em 0 0.5em;">Vendor Profile</h4>

            <div class="oha-vh-row">
                <label for="ve_website">Company Website</label>
                <input type="text" id="ve_website" name="website" value="<?php echo $v['website']; ?>" placeholder="https://...">
            </div>

            <div class="oha-vh-row">
                <label>Products / Description<?php echo $locked ? ' <span style="font-weight:normal;font-size:.85em;">(locked — agreement signed)</span>' : ' *'; ?></label>
                <?php if ($locked): ?>
                    <p style="margin:0;padding:0.5em 0;color:#555;"><?php echo nl2br(esc_html(get_post_meta($app_id, 'product_type', true))); ?></p>
                <?php else: ?>
                    <textarea id="ve_product_type" name="product_type" rows="4" required><?php echo esc_textarea(get_post_meta($app_id, 'product_type', true)); ?></textarea>
                <?php endif; ?>
            </div>

            <div class="oha-vh-row">
                <label>Company Logo / Photo</label>
                <div class="oha-vh-logo-wrap">
                    <?php if ($logo_img): ?>
                        <div class="oha-vh-logo-current">
                            <img id="oha-ve-logo-preview" src="<?php echo esc_url($logo_img[0]); ?>" alt="Current logo" style="max-width:150px;max-height:150px;display:block;margin-bottom:0.5em;border:1px solid #ddd;border-radius:4px;">
                        </div>
                        <label style="display:flex;align-items:center;gap:0.4em;margin-bottom:0.5em;font-weight:normal;cursor:pointer;">
                            <input type="checkbox" name="remove_logo" value="1"> Remove current logo
                        </label>
                    <?php else: ?>
                        <img id="oha-ve-logo-preview" src="" alt="" style="max-width:150px;max-height:150px;display:none;margin-bottom:0.5em;border:1px solid #ddd;border-radius:4px;">
                    <?php endif; ?>
                    <input type="file" id="oha-ve-logo-file" name="vendor_logo" accept="image/jpeg,image/png,image/gif">
                    <p style="margin:0.25em 0 0;font-size:.85em;color:#666;">JPG, PNG, or GIF. Max 5 MB.</p>
                </div>
            </div>

            <div style="margin-top:1.5em;">
                <button type="submit" class="oha-vh-btn">Save Changes</button>
            </div>
        </form>
    </div>

    <style>
    .oha-vh-edit-wrap h4 { border-bottom: 1px solid #eee; padding-bottom: 0.3em; }
    .oha-vh-logo-wrap { display:flex; flex-direction:column; gap:0.25em; }
    .oha-vh-status-note { background:#fff8e1; border-left:3px solid #f59e0b; padding:0.6em 0.9em; margin-bottom:1em; border-radius:0 4px 4px 0; color:#000; }
    .oha-vh-edit-wrap .oha-vh-row label { color:#fff; }
    </style>

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

/**
 * Render the email request form (State 1 / expired token state).
 */
function oha_vh_render_email_form($page_url) {
    ?>
    <div class="oha-vh-edit-email-wrap">
        <h3>Update Your Vendor Information</h3>
        <p>Enter the email address you used when you applied to be a vendor. We will send you a secure link to update your information.</p>
        <form class="oha-vh-form" method="post" action="<?php echo esc_url($page_url); ?>">
            <?php wp_nonce_field('oha_vh_edit_email_request', 'oha_vh_edit_email_nonce'); ?>
            <div class="oha-vh-row">
                <label for="vendor_lookup_email">Email Address *</label>
                <input type="email" id="vendor_lookup_email" name="vendor_lookup_email" required placeholder="you@example.com">
            </div>
            <div style="margin-top:1em;">
                <button type="submit" class="oha-vh-btn">Send Me a Link</button>
            </div>
        </form>
    </div>
    <?php
}
