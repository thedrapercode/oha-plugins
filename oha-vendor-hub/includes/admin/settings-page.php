<?php
if (!defined('ABSPATH')) exit;

/**
 * Vendor Hub Settings admin page.
 */
add_action('admin_menu', function(){
    add_submenu_page(
        'edit.php?post_type=vendor_application',
        'Vendor Hub Settings',
        'Settings',
        'manage_options',
        'oha-vhcpt-settings',
        'oha_vhcpt_settings_page'
    );
});

add_action('admin_enqueue_scripts', function( $hook ){
    if ( $hook !== 'vendor_application_page_oha-vhcpt-settings' ) return;
    wp_enqueue_media();
});

/**
 * Normalize a status label to an internal key.
 */
function oha_vhcpt_status_key($label){
    $label = strtolower(trim($label));
    $label = str_replace([' ', '-'], '_', $label);
    return $label;
}

function oha_vhcpt_settings_page(){
    if (!current_user_can('manage_options')) return;

    // Default field config if not set.
    $default_fields = function_exists('oha_vhcpt_fields_default') ? oha_vhcpt_fields_default() : [];

    if (!empty($_POST['oha_vhcpt_nonce']) && wp_verify_nonce($_POST['oha_vhcpt_nonce'],'oha_vhcpt_save')){
        // Fields JSON.
        $fields_json = isset($_POST['fields_json']) ? wp_unslash($_POST['fields_json']) : '';
        $fields = $default_fields;
        if ($fields_json){
            $decoded = json_decode($fields_json, true);
            if (is_array($decoded)){
                $fields = $decoded;
            }
        }
        update_option('oha_vhcpt_fields', $fields);

        // Legacy single email template (kept for compatibility / optional manual use).
        $email_tpl = isset($_POST['email_tpl']) ? wp_unslash($_POST['email_tpl']) : '';
        update_option('oha_vhcpt_email_tpl', wp_kses_post($email_tpl));

        // Capability required to view Board Hub.
        $front_cap = isset($_POST['front_cap']) ? sanitize_text_field(wp_unslash($_POST['front_cap'])) : 'manage_options';
        update_option('oha_vhcpt_front_cap', $front_cap ?: 'manage_options');

        // User IDs allowed to view the Vendor Status Table shortcode.
        $raw_viewer_ids = isset($_POST['status_table_viewer_ids']) ? sanitize_text_field(wp_unslash($_POST['status_table_viewer_ids'])) : '183';
        // Strip anything that's not a digit or comma/space, then normalise.
        $raw_viewer_ids = preg_replace('/[^0-9,\s]/', '', $raw_viewer_ids);
        $raw_viewer_ids = preg_replace('/\s+/', '', $raw_viewer_ids); // remove spaces
        $raw_viewer_ids = preg_replace('/,+/', ',', $raw_viewer_ids); // collapse multiple commas
        $raw_viewer_ids = trim($raw_viewer_ids, ',');
        update_option('oha_vhcpt_status_table_viewer_ids', $raw_viewer_ids ?: '183');

        // Email automation steps.
        $submitted_steps = isset($_POST['email_steps']) && is_array($_POST['email_steps']) ? $_POST['email_steps'] : [];
        $clean_steps = [];

        $status_labels = [
            'submitted'        => 'Submitted',
            'in_review'        => 'In Review',
            'approved'         => 'Approved',
            'denied'           => 'Denied',
            'agreement_sent'   => 'Agreement Sent',
            'agreement_signed' => 'Agreement Signed',
            'paid'             => 'Paid',
        ];

        foreach ($status_labels as $key => $label){
            $row = isset($submitted_steps[$key]) && is_array($submitted_steps[$key]) ? $submitted_steps[$key] : [];
            $clean_steps[$key] = [
                'enabled' => !empty($row['enabled']) ? 1 : 0,
                'subject' => isset($row['subject']) ? sanitize_text_field(wp_unslash($row['subject'])) : '',
                'body'    => isset($row['body']) ? wp_kses_post(wp_unslash($row['body'])) : '',
            ];
        }

        update_option('oha_vhcpt_email_steps', $clean_steps);

        // Save Stripe settings (v1.6)
        if (isset($_POST['stripe_link_test'])) {
            update_option('oha_vhcpt_stripe_link_test', esc_url_raw($_POST['stripe_link_test']));
        }
        if (isset($_POST['stripe_link_live'])) {
            update_option('oha_vhcpt_stripe_link_live', esc_url_raw($_POST['stripe_link_live']));
        }
        if (isset($_POST['stripe_mode'])) {
            update_option('oha_vhcpt_stripe_mode', sanitize_text_field($_POST['stripe_mode']));
        }
        
        // Save Stripe settings (Payment Links only)
        if (isset($_POST['stripe_link_test'])) {
            update_option('oha_vhcpt_stripe_link_test', esc_url_raw($_POST['stripe_link_test']));
        }
        if (isset($_POST['stripe_link_live'])) {
            update_option('oha_vhcpt_stripe_link_live', esc_url_raw($_POST['stripe_link_live']));
        }
        if (isset($_POST['stripe_mode'])) {
            update_option('oha_vhcpt_stripe_mode', sanitize_text_field($_POST['stripe_mode']));
        }
        // Save vendor send options templates (v1.8)
        if (isset($_POST['vendor_send_with_payment_subject'])) {
            update_option('oha_vhcpt_vendor_send_with_payment_subject', sanitize_text_field($_POST['vendor_send_with_payment_subject']));
        }
        if (isset($_POST['vendor_send_with_payment_body'])) {
            update_option('oha_vhcpt_vendor_send_with_payment_body', wp_kses_post($_POST['vendor_send_with_payment_body']));
        }
        if (isset($_POST['vendor_send_no_payment_subject'])) {
            update_option('oha_vhcpt_vendor_send_no_payment_subject', sanitize_text_field($_POST['vendor_send_no_payment_subject']));
        }
        if (isset($_POST['vendor_send_no_payment_body'])) {
            update_option('oha_vhcpt_vendor_send_no_payment_body', wp_kses_post($_POST['vendor_send_no_payment_body']));
        }
        if (isset($_POST['vendor_send_payment_only_subject'])) {
            update_option('oha_vhcpt_vendor_send_payment_only_subject', sanitize_text_field($_POST['vendor_send_payment_only_subject']));
        }
        if (isset($_POST['vendor_send_payment_only_body'])) {
            update_option('oha_vhcpt_vendor_send_payment_only_body', wp_kses_post($_POST['vendor_send_payment_only_body']));
        }
        
        // Save final app page ID
        if (isset($_POST['final_app_page_id'])) {
            update_option('oha_vhcpt_final_app_page_id', intval($_POST['final_app_page_id']));
        }
        
        // Save no-payment page ID
        if (isset($_POST['final_app_no_payment_page_id'])) {
            update_option('oha_vhcpt_final_app_no_payment_page_id', intval($_POST['final_app_no_payment_page_id']));
        }

        // Application close toggle
        update_option('oha_vhcpt_apps_closed', !empty($_POST['apps_closed']) ? 1 : 0);
        $closed_msg = isset($_POST['apps_closed_message']) ? wp_kses_post(wp_unslash($_POST['apps_closed_message'])) : '';
        update_option('oha_vhcpt_apps_closed_message', $closed_msg);

        // Vendor edit page ID
        if (isset($_POST['vendor_edit_page_id'])) {
            update_option('oha_vhcpt_vendor_edit_page_id', intval($_POST['vendor_edit_page_id']));
        }

        // Vendor edit link email template
        if (isset($_POST['edit_link_subject'])) {
            update_option('oha_vhcpt_edit_link_subject', sanitize_text_field(wp_unslash($_POST['edit_link_subject'])));
        }
        if (isset($_POST['edit_link_body'])) {
            update_option('oha_vhcpt_edit_link_body', sanitize_textarea_field(wp_unslash($_POST['edit_link_body'])));
        }

        // Vendor Card Grid settings
        update_option( 'oha_vhcpt_cards_placeholder_id',  absint( isset( $_POST['cards_placeholder_id'] )  ? $_POST['cards_placeholder_id']  : 0 ) );
        update_option( 'oha_vhcpt_cards_placeholder_url', esc_url_raw( isset( $_POST['cards_placeholder_url'] ) ? wp_unslash( $_POST['cards_placeholder_url'] ) : '' ) );
        update_option( 'oha_vhcpt_cards_fields', [
            'website'  => ! empty( $_POST['cards_field_website'] ),
            'products' => ! empty( $_POST['cards_field_products'] ),
        ] );

        // Convention Details — used in automated email bodies.
        update_option( 'oha_vhcpt_convention_name',       sanitize_text_field( wp_unslash( $_POST['convention_name']       ?? '' ) ) );
        update_option( 'oha_vhcpt_convention_venue',      sanitize_text_field( wp_unslash( $_POST['convention_venue']      ?? '' ) ) );
        update_option( 'oha_vhcpt_convention_dates',      sanitize_text_field( wp_unslash( $_POST['convention_dates']      ?? '' ) ) );
        update_option( 'oha_vhcpt_convention_load_in',    sanitize_text_field( wp_unslash( $_POST['convention_load_in']    ?? '' ) ) );
        update_option( 'oha_vhcpt_convention_booth_price',sanitize_text_field( wp_unslash( $_POST['convention_booth_price'] ?? '' ) ) );

        // Active convention year — stamped on all new applications.
        $active_year = intval( $_POST['active_year'] ?? date('Y') );
        if ( $active_year < 2020 || $active_year > 2100 ) $active_year = intval( date('Y') );
        update_option( 'oha_vhcpt_active_year', $active_year );

        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    $fields    = get_option('oha_vhcpt_fields', $default_fields);
    $email_tpl = get_option('oha_vhcpt_email_tpl', "Hello {first_name},\n\nYour vendor application status is now: {status}\nAgreement: {agreement_link}\nPayment: {payment_link}\n\n— OHA");
    $front_cap = get_option('oha_vhcpt_front_cap','manage_options');

    $status_labels = [
        'submitted'        => 'Submitted',
        'in_review'        => 'In Review',
        'approved'         => 'Approved',
        'denied'           => 'Denied',
        'agreement_sent'   => 'Agreement Sent',
        'agreement_signed' => 'Agreement Signed',
        'paid'             => 'Paid',
    ];

    $email_steps = get_option('oha_vhcpt_email_steps', []);
    if (!is_array($email_steps)) $email_steps = [];

    // Build defaults for email steps.
    $defaults = [
        'submitted' => [
            'enabled' => 1,
            'subject' => 'We received your vendor application',
            'body'    => "Hi {first_name},\n\nThank you for submitting your vendor application for {company_name}.\n\nWe will review your information and follow up soon.\n\nThanks,\nOhio Haunters Association",
        ],
        'in_review' => [
            'enabled' => 0,
            'subject' => 'Your vendor application is being reviewed',
            'body'    => "Hi {first_name},\n\nYour vendor application is currently under review.\n\nStatus: {status}\n\nThanks,\nOhio Haunters Association",
        ],
        'approved' => [
            'enabled' => 1,
            'subject' => 'Your vendor application has been approved',
            'body'    => "Hi {first_name},\n\nGood news! Your vendor application has been approved.\n\nAgreement link: {agreement_link}\nPayment link: {payment_link}\n\nThanks,\nOhio Haunters Association",
        ],
        'denied' => [
            'enabled' => 0,
            'subject' => 'Vendor application status update',
            'body'    => "Hi {first_name},\n\nThank you for your interest. At this time, we are unable to approve your vendor application.\n\nThanks,\nOhio Haunters Association",
        ],
        'agreement_sent' => [
            'enabled' => 0,
            'subject' => 'Vendor agreement link',
            'body'    => "Hi {first_name},\n\nHere is your vendor agreement link:\n{agreement_link}\n\nThanks,\nOhio Haunters Association",
        ],
        'agreement_signed' => [
            'enabled' => 0,
            'subject' => 'Vendor agreement received',
            'body'    => "Hi {first_name},\n\nWe have received your signed agreement.\n\nNext step: complete your payment if you have not already done so.\n\nThanks,\nOhio Haunters Association",
        ],
        'paid' => [
            'enabled' => 1,
            'subject' => 'Payment received – your vendor spot is confirmed',
            'body'    => "Hi {first_name},\n\nWe have received your payment. Your vendor spot is now confirmed.\n\nThanks,\nOhio Haunters Association",
        ],
    ];

    // Merge saved settings with defaults.
    foreach ($status_labels as $key => $label){
        if (!isset($email_steps[$key]) || !is_array($email_steps[$key])){
            $email_steps[$key] = $defaults[$key];
        } else {
            $email_steps[$key] = wp_parse_args($email_steps[$key], $defaults[$key]);
        }
    }

    ?>
    <div class="wrap">
        <h1>Vendor Hub Settings</h1>
        <form method="post">
            <?php wp_nonce_field('oha_vhcpt_save','oha_vhcpt_nonce'); ?>

            <h2 class="title">Convention Details</h2>
            <p style="margin-bottom:1em;color:#555;">These values are inserted into automated vendor emails — fast-track invites, approval emails, and agreement notifications. Update them at the start of each convention year so emails always reflect current information.</p>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="convention_name">Convention Name</label></th>
                    <td>
                        <input type="text" id="convention_name" name="convention_name" class="regular-text"
                            value="<?php echo esc_attr( get_option('oha_vhcpt_convention_name', 'OHA Convention') ); ?>">
                        <p class="description">Used in email subjects and body copy. Example: OHA Convention 2027</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="convention_venue">Venue</label></th>
                    <td>
                        <input type="text" id="convention_venue" name="convention_venue" class="regular-text"
                            value="<?php echo esc_attr( get_option('oha_vhcpt_convention_venue', '') ); ?>">
                        <p class="description">Example: Mansfield Reformatory, Mansfield, Ohio</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="convention_dates">Event Dates</label></th>
                    <td>
                        <input type="text" id="convention_dates" name="convention_dates" class="regular-text"
                            value="<?php echo esc_attr( get_option('oha_vhcpt_convention_dates', '') ); ?>">
                        <p class="description">Example: May 8-9, 2027</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="convention_load_in">Load-In Window</label></th>
                    <td>
                        <input type="text" id="convention_load_in" name="convention_load_in" class="regular-text"
                            value="<?php echo esc_attr( get_option('oha_vhcpt_convention_load_in', '') ); ?>">
                        <p class="description">Example: Friday, May 7 from 4:00 PM - 9:00 PM</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="convention_booth_price">Booth Price</label></th>
                    <td>
                        <input type="text" id="convention_booth_price" name="convention_booth_price" class="regular-text"
                            value="<?php echo esc_attr( get_option('oha_vhcpt_convention_booth_price', '') ); ?>">
                        <p class="description">Example: $250.00 per space</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="active_year">Active Convention Year</label></th>
                    <td>
                        <input type="number" id="active_year" name="active_year" class="small-text" min="2020" max="2100"
                            value="<?php echo esc_attr( get_option('oha_vhcpt_active_year', date('Y')) ); ?>">
                        <p class="description">Year stamped on every new vendor application. Update this each season before opening applications. Used by the Return Invite tool to identify prior-year vendors.</p>
                    </td>
                </tr>
            </table>

            <h2 class="title">Application Status</h2>
            <?php
            $apps_closed     = get_option('oha_vhcpt_apps_closed', 0);
            $apps_closed_msg = get_option('oha_vhcpt_apps_closed_message', 'Vendor applications are currently closed. Please check back soon.');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Accept Applications</th>
                    <td>
                        <label>
                            <input type="checkbox" name="apps_closed" value="1" <?php checked($apps_closed, 1); ?>>
                            <strong>Close applications</strong> — the public application form will show the message below instead of the form
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="apps_closed_message">Closed Message</label></th>
                    <td>
                        <textarea id="apps_closed_message" name="apps_closed_message" rows="3" class="large-text"><?php echo esc_textarea($apps_closed_msg); ?></textarea>
                        <p class="description">Shown to visitors when applications are closed. HTML is allowed.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="vendor_edit_page_id">Vendor Edit Page</label></th>
                    <td>
                        <?php
                        $vendor_edit_page_id = (int) get_option('oha_vhcpt_vendor_edit_page_id', 0);
                        $all_pages = get_pages();
                        ?>
                        <select name="vendor_edit_page_id" id="vendor_edit_page_id" class="regular-text">
                            <option value="0">-- Select Page --</option>
                            <?php foreach ($all_pages as $page): ?>
                                <option value="<?php echo intval($page->ID); ?>" <?php selected($vendor_edit_page_id, $page->ID); ?>>
                                    <?php echo esc_html($page->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Select the page that contains the <code>[oha_vendor_edit]</code> shortcode. When set, the magic link email sent to vendors will point to this page.</p>
                        <?php if ($vendor_edit_page_id): ?>
                            <p class="description"><strong>Current page:</strong> <a href="<?php echo esc_url(get_permalink($vendor_edit_page_id)); ?>" target="_blank"><?php echo esc_html(get_the_title($vendor_edit_page_id)); ?></a></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="edit_link_subject">Edit Link Email Subject</label></th>
                    <td>
                        <input type="text" id="edit_link_subject" name="edit_link_subject" class="large-text"
                            value="<?php echo esc_attr(get_option('oha_vhcpt_edit_link_subject', 'Update Your Vendor Information - Ohio Haunters Convention')); ?>">
                        <p class="description">Subject line sent when a vendor receives an edit link. Placeholder: <code>{first_name}</code></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="edit_link_body">Edit Link Email Body</label></th>
                    <td>
                        <?php
                        $edit_link_body_default = "Hello {first_name},\n\nHere is a link to update your vendor information for the Ohio Haunters Convention.\n\n{link}\n\nThis link is valid for 24 hours. After it expires, you can request a new one by visiting the same page and entering your email address again.\n\nIf you did not request this link, you can safely ignore this email.\n\nQuestions? Contact us at info@ohiohaunters.com\n\n-- The OHA Team";
                        ?>
                        <textarea id="edit_link_body" name="edit_link_body" rows="10" class="large-text"><?php echo esc_textarea(get_option('oha_vhcpt_edit_link_body', $edit_link_body_default)); ?></textarea>
                        <p class="description">Body of the vendor edit link email. Placeholders: <code>{first_name}</code> (vendor first name), <code>{link}</code> (the secure edit link — required).</p>
                    </td>
                </tr>
            </table>

            <h2 class="title">Stripe Webhook</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Webhook URL</th>
                    <td>
                        <code><?php echo esc_html(rest_url('oha/v1/stripe-webhook')); ?></code>
                        <p class="description">This is the centralized webhook managed by OHA Board Hub Suite. Configure this URL in your Stripe dashboard. All Stripe events (memberships, vendor payments) use this single endpoint.</p>
                    </td>
                </tr>
            </table>

            <h2 class="title">Fields</h2>
            <p>Edit the JSON to add/remove/reorder fields. (Keys become meta keys on the <code>vendor_application</code> post.)</p>
            <textarea name="fields_json" rows="14" style="width:100%;font-family:monospace;"><?php echo esc_textarea(wp_json_encode($fields, JSON_PRETTY_PRINT)); ?></textarea>

            <h2 class="title">Legacy Manual Email Template</h2>
            <p>This template is used only by the older “Send Email” action (if enabled elsewhere). Automated status emails use the Email Automations section below.</p>
            <textarea name="email_tpl" rows="8" style="width:100%;"><?php echo esc_textarea($email_tpl); ?></textarea>
            <p>Placeholders available here: {first_name}, {last_name}, {company_name}, {status}, {agreement_link}, {payment_link}</p>

            <h2 class="title">Email Automations</h2>
            <p>Configure automatic emails for each status step. These use the applicant&rsquo;s email address and the following smart tags:</p>
            <p><code>{first_name}</code>, <code>{last_name}</code>, <code>{company_name}</code>, <code>{email}</code>, <code>{phone}</code>, <code>{address_street}</code>, <code>{address_city}</code>, <code>{address_state}</code>, <code>{address_zip}</code>, <code>{website}</code>, <code>{product_type}</code>, <code>{booths}</code>, <code>{sponsor}</code>, <code>{status}</code>, <code>{agreement_link}</code>, <code>{payment_link}</code>, <code>{application_id}</code></p>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th style="width:160px;">Status</th>
                        <th>Subject &amp; Body</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($status_labels as $key => $label):
                    $row = $email_steps[$key];
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($label); ?></strong><br>
                            <label>
                                <input type="checkbox" name="email_steps[<?php echo esc_attr($key); ?>][enabled]" value="1" <?php checked($row['enabled'], 1); ?>>
                                Enable
                            </label>
                        </td>
                        <td>
                            <p>
                                <label>Subject<br>
                                    <input type="text" name="email_steps[<?php echo esc_attr($key); ?>][subject]" class="regular-text" value="<?php echo esc_attr($row['subject']); ?>">
                                </label>
                            </p>
                            <p>
                                <label>Body<br>
                                    <textarea name="email_steps[<?php echo esc_attr($key); ?>][body]" rows="6" style="width:100%;"><?php echo esc_textarea($row['body']); ?></textarea>
                                </label>
                            </p>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2 class="title">Vendor Send Options - Configuration</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="final_app_page_id">Final Application Page</label>
                    </th>
                    <td>
                        <?php
                        $final_app_page_id = get_option('oha_vhcpt_final_app_page_id', 0);
                        $pages = get_pages();
                        ?>
                        <select name="final_app_page_id" id="final_app_page_id" class="regular-text">
                            <option value="0">-- Select Page --</option>
                            <?php foreach ($pages as $page): ?>
                                <option value="<?php echo intval($page->ID); ?>" <?php selected($final_app_page_id, $page->ID); ?>>
                                    <?php echo esc_html($page->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            Select the page that has the <code>[oha_vendor_final_form]</code> shortcode.<br>
                            This is used for "Send → With Payment" option.
                        </p>
                        <?php if ($final_app_page_id): ?>
                            <p class="description">
                                <strong>Current page:</strong> <a href="<?php echo esc_url(get_permalink($final_app_page_id)); ?>" target="_blank"><?php echo esc_html(get_the_title($final_app_page_id)); ?></a>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="final_app_no_payment_page_id">No-Payment Application Page</label>
                    </th>
                    <td>
                        <?php
                        $final_app_no_payment_page_id = get_option('oha_vhcpt_final_app_no_payment_page_id', 0);
                        ?>
                        <select name="final_app_no_payment_page_id" id="final_app_no_payment_page_id" class="regular-text">
                            <option value="0">-- Same as above (adds ?no_payment_mode=1) --</option>
                            <?php foreach ($pages as $page): ?>
                                <option value="<?php echo intval($page->ID); ?>" <?php selected($final_app_no_payment_page_id, $page->ID); ?>>
                                    <?php echo esc_html($page->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            Select the page that has the <code>[oha_vendor_final_form_no_payment]</code> shortcode.<br>
                            This is used for "Send → Without Payment" option.<br>
                            <strong>Leave as "Same as above"</strong> to use the same page (recommended - simpler!)<br>
                            <strong>Or select different page</strong> if you created a separate no-payment page.
                        </p>
                        <?php if ($final_app_no_payment_page_id): ?>
                            <p class="description">
                                <strong>Current page:</strong> <a href="<?php echo esc_url(get_permalink($final_app_no_payment_page_id)); ?>" target="_blank"><?php echo esc_html(get_the_title($final_app_no_payment_page_id)); ?></a>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <h2 class="title">Vendor Send Options - Email Templates</h2>
            <p>Configure email templates for the "Send" dropdown in Board Hub. These are used when sending final application links to vendors.</p>
            
            <table class="form-table">
                <tr>
                    <th colspan="2">
                        <h3 style="margin:10px 0;">Option 1: With Payment (Standard)</h3>
                        <p style="font-weight:normal;color:#666;">Vendor needs to complete form AND pay</p>
                    </th>
                </tr>
                <tr>
                    <td style="padding-left:30px;">
                        <label><strong>Subject:</strong><br>
                            <input type="text" name="vendor_send_with_payment_subject" class="large-text" value="<?php echo esc_attr(get_option('oha_vhcpt_vendor_send_with_payment_subject', 'Complete Your OHA Convention 2026 Vendor Application')); ?>">
                        </label>
                    </td>
                </tr>
                <tr>
                    <td style="padding-left:30px;">
                        <label><strong>Body:</strong><br>
                            <textarea name="vendor_send_with_payment_body" rows="8" style="width:100%;"><?php echo esc_textarea(get_option('oha_vhcpt_vendor_send_with_payment_body', "Hi {first_name},\n\nThank you for your interest in being a vendor at the Ohio Haunters Convention 2026!\n\nYour initial application has been approved. Please complete your final application and payment using the link below:\n\n{final_app_link}\n\nImportant Details:\n- Venue: Mansfield Reformatory, Mansfield Ohio\n- Dates: May 9-10, 2026\n- Booth Price: \$250 per space\n- Load-in: Friday May 8th, 4pm-9pm\n\nPlease complete your application and payment as soon as possible to secure your booth space.\n\nIf you have any questions, please don't hesitate to contact us.\n\nThanks,\nOhio Haunters Association")); ?></textarea>
                        </label>
                        <p class="description">Available placeholders: {first_name}, {last_name}, {company_name}, {email}, {booths}, {final_app_link}</p>
                    </td>
                </tr>
                
                <tr>
                    <th colspan="2">
                        <h3 style="margin:10px 0;">Option 2: Without Payment (Pre-Paid)</h3>
                        <p style="font-weight:normal;color:#666;">Vendor needs to complete form ONLY (already paid)</p>
                    </th>
                </tr>
                <tr>
                    <td style="padding-left:30px;">
                        <label><strong>Subject:</strong><br>
                            <input type="text" name="vendor_send_no_payment_subject" class="large-text" value="<?php echo esc_attr(get_option('oha_vhcpt_vendor_send_no_payment_subject', 'Complete Your OHA Convention 2026 Vendor Application (Pre-Paid)')); ?>">
                        </label>
                    </td>
                </tr>
                <tr>
                    <td style="padding-left:30px;">
                        <label><strong>Body:</strong><br>
                            <textarea name="vendor_send_no_payment_body" rows="8" style="width:100%;"><?php echo esc_textarea(get_option('oha_vhcpt_vendor_send_no_payment_body', "Hi {first_name},\n\nThank you for your interest in being a vendor at the Ohio Haunters Convention 2026!\n\nWe have received your payment. Please complete your final application using the link below:\n\n{final_app_link_no_payment}\n\nImportant Details:\n- Venue: Mansfield Reformatory, Mansfield Ohio\n- Dates: May 9-10, 2026\n- Payment Status: Already Received - Thank You!\n- Load-in: Friday May 8th, 4pm-9pm\n\nPlease complete your application to finalize your vendor registration.\n\nIf you have any questions, please don't hesitate to contact us.\n\nThanks,\nOhio Haunters Association")); ?></textarea>
                        </label>
                        <p class="description">Available placeholders: {first_name}, {last_name}, {company_name}, {email}, {booths}, {final_app_link_no_payment}</p>
                    </td>
                </tr>
                
                <tr>
                    <th colspan="2">
                        <h3 style="margin:10px 0;">Option 3: Payment Only</h3>
                        <p style="font-weight:normal;color:#666;">Vendor already completed form, just needs to pay</p>
                    </th>
                </tr>
                <tr>
                    <td style="padding-left:30px;">
                        <label><strong>Subject:</strong><br>
                            <input type="text" name="vendor_send_payment_only_subject" class="large-text" value="<?php echo esc_attr(get_option('oha_vhcpt_vendor_send_payment_only_subject', 'Complete Payment for OHA Convention 2026 Vendor Booth')); ?>">
                        </label>
                    </td>
                </tr>
                <tr>
                    <td style="padding-left:30px;">
                        <label><strong>Body:</strong><br>
                            <textarea name="vendor_send_payment_only_body" rows="8" style="width:100%;"><?php echo esc_textarea(get_option('oha_vhcpt_vendor_send_payment_only_body', "Hi {first_name},\n\nThank you for completing your vendor application for the Ohio Haunters Convention 2026!\n\nTo secure your booth space, please complete payment using the link below:\n\n{payment_link}\n\nBooth Details:\n- Quantity: {booths} booth(s)\n- Price: \$250 per booth\n- Total: \${total}\n\nOnce payment is received, you will receive your signed vendor agreement via email.\n\nIf you have any questions, please don't hesitate to contact us.\n\nThanks,\nOhio Haunters Association")); ?></textarea>
                        </label>
                        <p class="description">Available placeholders: {first_name}, {last_name}, {company_name}, {email}, {booths}, {total}, {payment_link}</p>
                    </td>
                </tr>
            </table>
            
            <h2 class="title">Vendor Status Table</h2>
            <p>
                Use the shortcode <code>[oha_vendor_status_table]</code> to display a read-only, searchable vendor status table on any page.<br>
                Admins (manage_options) always have access. Use the field below to grant access to additional non-admin users.
            </p>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="status_table_viewer_ids">Allowed Viewer User IDs</label></th>
                    <td>
                        <input
                            type="text"
                            id="status_table_viewer_ids"
                            name="status_table_viewer_ids"
                            class="regular-text"
                            value="<?php echo esc_attr( get_option( 'oha_vhcpt_status_table_viewer_ids', '183' ) ); ?>"
                            placeholder="183, 456, 789"
                        >
                        <p class="description">
                            Comma-separated WordPress user IDs that can view the <code>[oha_vendor_status_table]</code> shortcode.
                            All site administrators always have access regardless of this list.<br>
                            <em>Current list: <strong><?php echo esc_html( get_option( 'oha_vhcpt_status_table_viewer_ids', '183' ) ); ?></strong></em>
                        </p>
                    </td>
                </tr>
            </table>

            <h2 class="title">Board Hub Capability</h2>
            <p>
                <label>Required capability to view the Board Hub (shortcode <code>[oha_vendor_board_hub]</code>):<br>
                    <input type="text" name="front_cap" value="<?php echo esc_attr($front_cap); ?>" class="regular-text">
                </label>
                <br><em>Default: <code>manage_options</code></em>
            </p>

            <?php 
            // Render comprehensive Stripe settings (API + Payment Links)
            if (function_exists('oha_vhcpt_render_stripe_settings')) {
                oha_vhcpt_render_stripe_settings();
            }
            ?>

            <h2 class="title">Vendor Card Grid (Public Display)</h2>
            <p>Controls the <code>[oha_vendor_cards]</code> shortcode used on public website pages.</p>

            <?php
            $cards_fields      = get_option( 'oha_vhcpt_cards_fields', [ 'website' => true, 'products' => true ] );
            $cards_ph_id       = (int) get_option( 'oha_vhcpt_cards_placeholder_id', 0 );
            $cards_ph_url      = get_option( 'oha_vhcpt_cards_placeholder_url', '' );
            $cards_ph_preview  = $cards_ph_id ? wp_get_attachment_image_url( $cards_ph_id, [ 120, 90 ] ) : $cards_ph_url;
            ?>

            <table class="form-table">
                <tr>
                    <th scope="row">Fields to display</th>
                    <td>
                        <label style="display:block;margin-bottom:6px;">
                            <input type="checkbox" name="cards_field_website" value="1" <?php checked( ! empty( $cards_fields['website'] ) ); ?>>
                            <strong>Website</strong> — shows a clickable link below the company name
                        </label>
                        <label style="display:block;">
                            <input type="checkbox" name="cards_field_products" value="1" <?php checked( ! empty( $cards_fields['products'] ) ); ?>>
                            <strong>Product Type</strong> — shows the product/product category text on the card
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label>Placeholder image</label></th>
                    <td>
                        <input type="hidden" name="cards_placeholder_id"  id="oha-cards-ph-id"  value="<?php echo esc_attr( $cards_ph_id ); ?>">
                        <input type="hidden" name="cards_placeholder_url" id="oha-cards-ph-url" value="<?php echo esc_url( $cards_ph_url ); ?>">

                        <div id="oha-cards-ph-preview" style="margin-bottom:8px;">
                            <?php if ( $cards_ph_preview ) : ?>
                                <img src="<?php echo esc_url( $cards_ph_preview ); ?>" style="max-width:120px;max-height:90px;border:1px solid #ddd;border-radius:4px;display:block;">
                            <?php else : ?>
                                <span style="color:#888;font-size:13px;">No image set — a shopping bag icon is shown as the default.</span>
                            <?php endif; ?>
                        </div>

                        <button type="button" class="button" id="oha-cards-ph-choose">Choose Image</button>
                        <button type="button" class="button" id="oha-cards-ph-clear" style="<?php echo $cards_ph_id ? '' : 'display:none;'; ?>margin-left:6px;">Remove</button>
                        <p class="description">Shown on vendor cards that have no logo uploaded. Leave blank to use the default shopping bag icon.</p>
                    </td>
                </tr>
            </table>

            <script>
            (function($){
                var frame;
                $('#oha-cards-ph-choose').on('click', function(e){
                    e.preventDefault();
                    if (frame) { frame.open(); return; }
                    frame = wp.media({ title: 'Select Placeholder Image', button: { text: 'Use this image' }, multiple: false });
                    frame.on('select', function(){
                        var att = frame.state().get('selection').first().toJSON();
                        $('#oha-cards-ph-id').val(att.id);
                        $('#oha-cards-ph-url').val(att.url);
                        var thumb = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
                        $('#oha-cards-ph-preview').html('<img src="' + thumb + '" style="max-width:120px;max-height:90px;border:1px solid #ddd;border-radius:4px;display:block;">');
                        $('#oha-cards-ph-clear').show();
                    });
                    frame.open();
                });
                $('#oha-cards-ph-clear').on('click', function(e){
                    e.preventDefault();
                    $('#oha-cards-ph-id').val('0');
                    $('#oha-cards-ph-url').val('');
                    $('#oha-cards-ph-preview').html('<span style="color:#888;font-size:13px;">No image set — a shopping bag icon is shown as the default.</span>');
                    $(this).hide();
                });
            })(jQuery);
            </script>

            <p><button class="button button-primary">Save Settings</button></p>
        </form>
    </div>
    <?php
}
