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

        // Save Stripe settings (Payment Links)
        if (isset($_POST['stripe_link_test'])) {
            update_option('oha_vhcpt_stripe_link_test', esc_url_raw($_POST['stripe_link_test']));
        }
        if (isset($_POST['stripe_link_live'])) {
            update_option('oha_vhcpt_stripe_link_live', esc_url_raw($_POST['stripe_link_live']));
        }
        if (isset($_POST['stripe_link_test_2booth'])) {
            update_option('oha_vhcpt_stripe_link_test_2booth', esc_url_raw($_POST['stripe_link_test_2booth']));
        }
        if (isset($_POST['stripe_link_live_2booth'])) {
            update_option('oha_vhcpt_stripe_link_live_2booth', esc_url_raw($_POST['stripe_link_live_2booth']));
        }
        if (isset($_POST['stripe_mode'])) {
            update_option('oha_vhcpt_stripe_mode', sanitize_text_field($_POST['stripe_mode']));
        }
        // Save vendor send options templates (v1.8)
        if (isset($_POST['vendor_send_with_payment_subject'])) {
            update_option('oha_vhcpt_vendor_send_with_payment_subject', sanitize_text_field(wp_unslash($_POST['vendor_send_with_payment_subject'])));
        }
        if (isset($_POST['vendor_send_with_payment_body'])) {
            update_option('oha_vhcpt_vendor_send_with_payment_body', wp_kses_post(wp_unslash($_POST['vendor_send_with_payment_body'])));
        }
        if (isset($_POST['vendor_send_no_payment_subject'])) {
            update_option('oha_vhcpt_vendor_send_no_payment_subject', sanitize_text_field(wp_unslash($_POST['vendor_send_no_payment_subject'])));
        }
        if (isset($_POST['vendor_send_no_payment_body'])) {
            update_option('oha_vhcpt_vendor_send_no_payment_body', wp_kses_post(wp_unslash($_POST['vendor_send_no_payment_body'])));
        }
        if (isset($_POST['vendor_send_payment_only_subject'])) {
            update_option('oha_vhcpt_vendor_send_payment_only_subject', sanitize_text_field(wp_unslash($_POST['vendor_send_payment_only_subject'])));
        }
        if (isset($_POST['vendor_send_payment_only_body'])) {
            update_option('oha_vhcpt_vendor_send_payment_only_body', wp_kses_post(wp_unslash($_POST['vendor_send_payment_only_body'])));
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
            update_option('oha_vhcpt_edit_link_body', wp_kses_post(wp_unslash($_POST['edit_link_body'])));
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

        update_option( 'oha_vhcpt_convention_contact_email', sanitize_email( wp_unslash( $_POST['convention_contact_email'] ?? '' ) ) );

        // Active convention year — stamped on all new applications.
        $active_year = intval( $_POST['active_year'] ?? date('Y') );
        if ( $active_year < 2020 || $active_year > 2100 ) $active_year = intval( date('Y') );
        update_option( 'oha_vhcpt_active_year', $active_year );

        // Sandbox / test mode.
        update_option('oha_vhcpt_sandbox_mode', !empty($_POST['sandbox_mode']) ? 1 : 0);
        if (isset($_POST['sandbox_email'])) {
            update_option('oha_vhcpt_sandbox_email', sanitize_email(wp_unslash($_POST['sandbox_email'])));
        }

        // Returning vendor confirmation email.
        if (isset($_POST['return_confirm_subject'])) {
            update_option('oha_vhcpt_return_confirm_subject', sanitize_text_field(wp_unslash($_POST['return_confirm_subject'])));
        }
        if (isset($_POST['return_confirm_body'])) {
            update_option('oha_vhcpt_return_confirm_body', wp_kses_post(wp_unslash($_POST['return_confirm_body'])));
        }

        // Fast-Track Invite email template.
        if (isset($_POST['fasttrack_subject'])) {
            update_option('oha_vhcpt_fasttrack_subject', sanitize_text_field(wp_unslash($_POST['fasttrack_subject'])));
        }
        if (isset($_POST['fasttrack_body'])) {
            update_option('oha_vhcpt_fasttrack_body', wp_kses_post(wp_unslash($_POST['fasttrack_body'])));
        }

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
                    <th scope="row"><label for="convention_contact_email">Vendor Contact Email</label></th>
                    <td>
                        <input type="email" id="convention_contact_email" name="convention_contact_email" class="regular-text"
                            value="<?php echo esc_attr( get_option('oha_vhcpt_convention_contact_email', 'webmaster@ohiohaunters.com') ); ?>">
                        <p class="description">Email shown to vendors for questions and 3+ booth requests. Defaults to webmaster@ohiohaunters.com.</p>
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

            <h2 class="title">Sandbox / Test Mode</h2>
            <?php
            $sandbox_on    = get_option('oha_vhcpt_sandbox_mode', 0);
            $sandbox_email = get_option('oha_vhcpt_sandbox_email', '');
            if ($sandbox_on): ?>
            <div class="notice notice-warning inline" style="margin:0 0 12px;padding:8px 12px;">
                <strong>Sandbox mode is ON.</strong> All plugin emails are being redirected to <strong><?php echo esc_html($sandbox_email ?: '(not set)'); ?></strong>. A yellow banner is shown on the vendor edit page. Turn this off before going live.
            </div>
            <?php endif; ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Enable Sandbox Mode</th>
                    <td>
                        <label>
                            <input type="checkbox" name="sandbox_mode" value="1" <?php checked($sandbox_on, 1); ?>>
                            <strong>Enable sandbox / test mode</strong>
                        </label>
                        <p class="description">When enabled: all plugin emails are redirected to the address below (original recipient is shown in the subject line), and a yellow "SANDBOX MODE" banner appears on the vendor edit page so you can verify the flow without contacting real vendors.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sandbox_email">Test Email Address</label></th>
                    <td>
                        <input type="email" id="sandbox_email" name="sandbox_email" class="regular-text"
                            value="<?php echo esc_attr($sandbox_email); ?>"
                            placeholder="you@example.com">
                        <p class="description">All vendor hub emails will be sent here instead of the real recipient. Use your own email so you can verify content and links. The subject line will show who the email would normally have gone to.</p>
                    </td>
                </tr>
            </table>

            <h2 class="title">Application Status</h2>
            <?php
            $apps_closed     = get_option('oha_vhcpt_apps_closed', 0);
            $apps_closed_msg = get_option('oha_vhcpt_apps_closed_message', 'Vendor applications are currently closed. Please check back soon.');
            $vendor_edit_page_id = (int) get_option('oha_vhcpt_vendor_edit_page_id', 0);
            $all_pages = get_pages();
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
                        <select name="vendor_edit_page_id" id="vendor_edit_page_id" class="regular-text">
                            <option value="0">-- Select Page --</option>
                            <?php foreach ($all_pages as $page): ?>
                                <option value="<?php echo intval($page->ID); ?>" <?php selected($vendor_edit_page_id, $page->ID); ?>>
                                    <?php echo esc_html($page->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Page containing the <code>[oha_vendor_edit]</code> shortcode. Used by the Edit Link email and return invite emails.</p>
                        <?php if ($vendor_edit_page_id): ?>
                            <p class="description"><strong>Current page:</strong> <a href="<?php echo esc_url(get_permalink($vendor_edit_page_id)); ?>" target="_blank"><?php echo esc_html(get_the_title($vendor_edit_page_id)); ?></a></p>
                        <?php endif; ?>
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

            <h2 class="title">Email Templates</h2>
            <p style="color:#555;">All automated and manual emails sent by this plugin. Use Sandbox Mode (below) to redirect any of these to a test address before going live.</p>

            <?php /* ── 1. Status-Change Automations ── */ ?>
            <h3 style="font-size:14px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#3c434a;border-top:1px solid #c3c4c7;padding-top:20px;margin-top:4px;">Status-Change Automations</h3>
            <p class="description" style="margin-bottom:8px;">Trigger: fires automatically when you change a vendor&rsquo;s status in the board hub. Each row can be enabled or disabled independently.</p>
            <p class="description" style="margin-bottom:12px;">Placeholders: <code>{first_name}</code> <code>{last_name}</code> <code>{company_name}</code> <code>{email}</code> <code>{phone}</code> <code>{booths}</code> <code>{status}</code> <code>{agreement_link}</code> <code>{payment_link}</code> <code>{application_id}</code></p>

            <table class="widefat striped" style="margin-bottom:0;">
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
                        <td style="vertical-align:top;padding-top:14px;">
                            <strong><?php echo esc_html($label); ?></strong><br>
                            <label style="font-weight:normal;">
                                <input type="checkbox" name="email_steps[<?php echo esc_attr($key); ?>][enabled]" value="1" <?php checked($row['enabled'], 1); ?>>
                                Enable
                            </label>
                        </td>
                        <td>
                            <p style="margin-bottom:6px;">
                                <label>Subject<br>
                                    <input type="text" name="email_steps[<?php echo esc_attr($key); ?>][subject]" class="large-text" value="<?php echo esc_attr($row['subject']); ?>">
                                </label>
                            </p>
                            <p style="margin-bottom:0;">
                                <label>Body<br>
                                    <textarea name="email_steps[<?php echo esc_attr($key); ?>][body]" rows="5" class="large-text"><?php echo esc_textarea($row['body']); ?></textarea>
                                </label>
                            </p>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php /* ── 2. Send Options ── */ ?>
            <?php
            $final_app_page_id            = (int) get_option('oha_vhcpt_final_app_page_id', 0);
            $final_app_no_payment_page_id = (int) get_option('oha_vhcpt_final_app_no_payment_page_id', 0);
            $pages = get_pages();
            ?>
            <h3 style="font-size:14px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#3c434a;border-top:1px solid #c3c4c7;padding-top:20px;margin-top:20px;">Send Options</h3>
            <p class="description" style="margin-bottom:12px;">Trigger: fires when you click the &ldquo;Send&rdquo; dropdown on a vendor card in the board hub. Choose the right option per vendor.</p>

            <table class="form-table" style="margin-top:0;">
                <tr>
                    <th scope="row"><label for="final_app_page_id">Final Application Page</label></th>
                    <td>
                        <select name="final_app_page_id" id="final_app_page_id" class="regular-text">
                            <option value="0">-- Select Page --</option>
                            <?php foreach ($pages as $page): ?>
                                <option value="<?php echo intval($page->ID); ?>" <?php selected($final_app_page_id, $page->ID); ?>>
                                    <?php echo esc_html($page->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Page containing <code>[oha_vendor_final_form]</code>. Used for the &ldquo;With Payment&rdquo; and &ldquo;Payment Only&rdquo; send options.<?php if ($final_app_page_id): ?> <strong>Current:</strong> <a href="<?php echo esc_url(get_permalink($final_app_page_id)); ?>" target="_blank"><?php echo esc_html(get_the_title($final_app_page_id)); ?></a><?php endif; ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="final_app_no_payment_page_id">No-Payment Page</label></th>
                    <td>
                        <select name="final_app_no_payment_page_id" id="final_app_no_payment_page_id" class="regular-text">
                            <option value="0">-- Same as Final Application Page (recommended) --</option>
                            <?php foreach ($pages as $page): ?>
                                <option value="<?php echo intval($page->ID); ?>" <?php selected($final_app_no_payment_page_id, $page->ID); ?>>
                                    <?php echo esc_html($page->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Only needed if you have a separate page for <code>[oha_vendor_final_form_no_payment]</code>. Leave as &ldquo;Same as above&rdquo; in most cases.<?php if ($final_app_no_payment_page_id): ?> <strong>Current:</strong> <a href="<?php echo esc_url(get_permalink($final_app_no_payment_page_id)); ?>" target="_blank"><?php echo esc_html(get_the_title($final_app_no_payment_page_id)); ?></a><?php endif; ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="padding-top:20px;"><strong>Option 1: With Payment</strong><br><span style="font-weight:normal;color:#666;font-size:12px;">Vendor completes form + pays</span></th>
                    <td style="padding-top:20px;">
                        <label>Subject<br>
                            <input type="text" name="vendor_send_with_payment_subject" class="large-text"
                                value="<?php echo esc_attr(get_option('oha_vhcpt_vendor_send_with_payment_subject', 'Complete Your Vendor Application - {convention_name}')); ?>">
                        </label>
                        <br><br>
                        <label>Body<br>
                            <textarea name="vendor_send_with_payment_body" rows="8" class="large-text"><?php echo esc_textarea(get_option('oha_vhcpt_vendor_send_with_payment_body', "Hi {first_name},\n\nYour vendor application has been approved for {convention_name}. Please complete your final application and payment using the link below:\n\n{final_app_link}\n\nPlease complete your application and payment as soon as possible to secure your booth space.\n\nIf you have any questions, please don't hesitate to contact us.\n\nThanks,\nOhio Haunters Association")); ?></textarea>
                        </label>
                        <p class="description">Placeholders: <code>{first_name}</code> <code>{last_name}</code> <code>{company_name}</code> <code>{email}</code> <code>{booths}</code> <code>{final_app_link}</code></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="padding-top:20px;"><strong>Option 2: Without Payment</strong><br><span style="font-weight:normal;color:#666;font-size:12px;">Vendor completes form only (pre-paid)</span></th>
                    <td style="padding-top:20px;">
                        <label>Subject<br>
                            <input type="text" name="vendor_send_no_payment_subject" class="large-text"
                                value="<?php echo esc_attr(get_option('oha_vhcpt_vendor_send_no_payment_subject', 'Complete Your Vendor Application - {convention_name} (Pre-Paid)')); ?>">
                        </label>
                        <br><br>
                        <label>Body<br>
                            <textarea name="vendor_send_no_payment_body" rows="8" class="large-text"><?php echo esc_textarea(get_option('oha_vhcpt_vendor_send_no_payment_body', "Hi {first_name},\n\nWe have received your payment for {convention_name}. Please complete your final application using the link below:\n\n{final_app_link_no_payment}\n\nPayment Status: Already Received - Thank you!\n\nPlease complete your application to finalize your vendor registration.\n\nIf you have any questions, please don't hesitate to contact us.\n\nThanks,\nOhio Haunters Association")); ?></textarea>
                        </label>
                        <p class="description">Placeholders: <code>{first_name}</code> <code>{last_name}</code> <code>{company_name}</code> <code>{email}</code> <code>{booths}</code> <code>{final_app_link_no_payment}</code></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row" style="padding-top:20px;"><strong>Option 3: Payment Only</strong><br><span style="font-weight:normal;color:#666;font-size:12px;">Vendor already completed form, just needs to pay</span></th>
                    <td style="padding-top:20px;">
                        <label>Subject<br>
                            <input type="text" name="vendor_send_payment_only_subject" class="large-text"
                                value="<?php echo esc_attr(get_option('oha_vhcpt_vendor_send_payment_only_subject', 'Complete Payment for Your Vendor Booth - {convention_name}')); ?>">
                        </label>
                        <br><br>
                        <label>Body<br>
                            <textarea name="vendor_send_payment_only_body" rows="8" class="large-text"><?php echo esc_textarea(get_option('oha_vhcpt_vendor_send_payment_only_body', "Hi {first_name},\n\nThank you for completing your vendor application for {convention_name}!\n\nTo secure your booth space, please complete payment using the link below:\n\n{payment_link}\n\nBooth Details:\n- Quantity: {booths} booth(s)\n- Total: \${total}\n\nOnce payment is received, you will receive your signed vendor agreement via email.\n\nIf you have any questions, please don't hesitate to contact us.\n\nThanks,\nOhio Haunters Association")); ?></textarea>
                        </label>
                        <p class="description">Placeholders: <code>{first_name}</code> <code>{last_name}</code> <code>{company_name}</code> <code>{email}</code> <code>{booths}</code> <code>{total}</code> <code>{payment_link}</code></p>
                    </td>
                </tr>
            </table>

            <?php /* ── 3. Fast-Track Invite ── */ ?>
            <h3 style="font-size:14px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#3c434a;border-top:1px solid #c3c4c7;padding-top:20px;margin-top:20px;">Fast-Track Invite</h3>
            <p class="description" style="margin-bottom:12px;">Trigger: fires when you send a Fast-Track Invite from the board hub panel. Sent to new vendors with no existing application.</p>

            <table class="form-table" style="margin-top:0;">
                <tr>
                    <th scope="row"><label for="fasttrack_subject">Subject</label></th>
                    <td>
                        <?php $ft_subject_default = "You're Invited to Vend at {convention_name}"; ?>
                        <input type="text" id="fasttrack_subject" name="fasttrack_subject" class="large-text"
                            value="<?php echo esc_attr(get_option('oha_vhcpt_fasttrack_subject', $ft_subject_default)); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="fasttrack_body">Body</label></th>
                    <td>
                        <?php $ft_body_default = "Hello,\n\nWe are thrilled to invite you to join us as a vendor at {convention_name}!\n\nThis is a fantastic opportunity to showcase your products to hundreds of haunted attraction enthusiasts, professionals, and fans from across the region.\n\nEVENT DETAILS\nVenue:    {convention_venue}\nDates:    {convention_dates}\nLoad-In:  {convention_load_in}\n\nTo secure your booth, please complete your vendor application using the link below:\n\n{final_app_url}\n\nBooth spaces are limited and fill up quickly, so we encourage you to complete your application as soon as possible.\n\nIf you have any questions or need assistance, please don't hesitate to reply to this email. We look forward to having you with us this year!\n\nWarm regards,\nOhio Haunters Association\nhttps://ohiohaunters.com"; ?>
                        <textarea id="fasttrack_body" name="fasttrack_body" rows="12" class="large-text"><?php echo esc_textarea(get_option('oha_vhcpt_fasttrack_body', $ft_body_default)); ?></textarea>
                        <p class="description">Placeholders: <code>{convention_name}</code> <code>{convention_venue}</code> <code>{convention_dates}</code> <code>{convention_load_in}</code> <code>{final_app_url}</code>. Event detail lines are removed automatically if the value is blank in Convention Details above.</p>
                    </td>
                </tr>
            </table>

            <?php /* ── 4. Edit Link Email ── */ ?>
            <h3 style="font-size:14px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#3c434a;border-top:1px solid #c3c4c7;padding-top:20px;margin-top:20px;">Edit Link Email</h3>
            <p class="description" style="margin-bottom:12px;">Trigger: fires from the &ldquo;Send Edit Links&rdquo; admin page. Sends the vendor the update page URL and their Vendor ID number.</p>

            <table class="form-table" style="margin-top:0;">
                <tr>
                    <th scope="row"><label for="edit_link_subject">Subject</label></th>
                    <td>
                        <input type="text" id="edit_link_subject" name="edit_link_subject" class="large-text"
                            value="<?php echo esc_attr(get_option('oha_vhcpt_edit_link_subject', 'Update Your Vendor Information - Ohio Haunters Convention')); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="edit_link_body">Body</label></th>
                    <td>
                        <?php $edit_link_body_default = "Hello {first_name},\n\nHere is a link to update your vendor information for the Ohio Haunters Convention.\n\n{link}\n\nThis link is valid for 24 hours. After it expires, you can request a new one by visiting the same page and entering your email address again.\n\nIf you did not request this link, you can safely ignore this email.\n\nQuestions? Contact us at info@ohiohaunters.com\n\n-- The OHA Team"; ?>
                        <textarea id="edit_link_body" name="edit_link_body" rows="10" class="large-text"><?php echo esc_textarea(get_option('oha_vhcpt_edit_link_body', $edit_link_body_default)); ?></textarea>
                        <p class="description">Placeholders: <code>{first_name}</code> <code>{link}</code> (vendor edit page URL) <code>{vendor_id}</code> (vendors need this to log in to the edit form)</p>
                    </td>
                </tr>
            </table>

            <?php /* ── 5. Returning Vendor Confirmation ── */ ?>
            <h3 style="font-size:14px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#3c434a;border-top:1px solid #c3c4c7;padding-top:20px;margin-top:20px;">Returning Vendor Confirmation</h3>
            <p class="description" style="margin-bottom:12px;">Trigger: fires automatically when a returning vendor saves the edit form and their status promotes from Invited to Submitted.</p>

            <table class="form-table" style="margin-top:0;">
                <tr>
                    <th scope="row"><label for="return_confirm_subject">Subject</label></th>
                    <td>
                        <?php $rc_subject_default = 'Your Vendor Information Has Been Updated - {convention_name}'; ?>
                        <input type="text" id="return_confirm_subject" name="return_confirm_subject" class="large-text"
                            value="<?php echo esc_attr(get_option('oha_vhcpt_return_confirm_subject', $rc_subject_default)); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="return_confirm_body">Body</label></th>
                    <td>
                        <?php $rc_body_default = "Hello {first_name},\n\nThank you for updating your vendor information for {convention_name}. We have received your submission and will be in touch soon with next steps.\n\nIf you need to make any changes, you can return to the vendor update page at any time and log in with your email address and Vendor ID.\n\n-- The OHA Team"; ?>
                        <textarea id="return_confirm_body" name="return_confirm_body" rows="8" class="large-text"><?php echo esc_textarea(get_option('oha_vhcpt_return_confirm_body', $rc_body_default)); ?></textarea>
                        <p class="description">Placeholders: <code>{first_name}</code> <code>{company_name}</code> <code>{convention_name}</code></p>
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

        <hr style="margin:40px 0 30px;">
        <h2>Shortcode Reference</h2>
        <p style="color:#666;margin-bottom:20px;">All shortcodes provided by this plugin. None require a logged-in user unless noted.</p>
        <style>
        .oha-sc-table { border-collapse:collapse; width:100%; margin-bottom:30px; }
        .oha-sc-table th { background:#f1f1f1; padding:10px 12px; text-align:left; border:1px solid #ddd; font-size:13px; }
        .oha-sc-table td { padding:9px 12px; border:1px solid #ddd; font-size:13px; vertical-align:top; }
        .oha-sc-table tr:nth-child(even) td { background:#fafafa; }
        .oha-sc-tag { font-family:monospace; background:#e8f0fe; color:#1a56db; padding:2px 6px; border-radius:3px; font-size:12px; white-space:nowrap; }
        .oha-sc-meta { font-family:monospace; font-size:11px; color:#555; }
        .oha-sc-badge { display:inline-block; padding:1px 7px; border-radius:10px; font-size:11px; font-weight:600; }
        .oha-sc-badge-pub { background:#d1fae5; color:#065f46; }
        .oha-sc-badge-admin { background:#fee2e2; color:#991b1b; }
        .oha-sc-badge-restricted { background:#fef3c7; color:#92400e; }
        </style>

        <table class="oha-sc-table">
            <thead>
                <tr>
                    <th>Shortcode</th>
                    <th>Access</th>
                    <th>Description</th>
                    <th>Key Meta Used</th>
                    <th>Attributes</th>
                    <th>Dependencies</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="oha-sc-tag">[oha_vendor_form]</span></td>
                    <td><span class="oha-sc-badge oha-sc-badge-pub">Public</span></td>
                    <td>Initial vendor application intake form. Creates a new <code>vendor_application</code> post on submission. Respects the "Close Applications" toggle. Includes logo upload.</td>
                    <td class="oha-sc-meta">first_name, last_name, company_name, email, phone, address_*, website, product_type, booths, tables_needed, need_electric, booth_preference, sponsor, _company_logo_id, _company_logo_url, _convention_year, _oha_status</td>
                    <td>None</td>
                    <td>None</td>
                </tr>
                <tr>
                    <td><span class="oha-sc-tag">[oha_vendor_edit]</span></td>
                    <td><span class="oha-sc-badge oha-sc-badge-pub">Public</span></td>
                    <td>Vendor self-service edit form. Step 1: vendor enters their email address and Vendor ID number (from their invite email, e.g. VID-123). Step 2: their application loads pre-filled for editing. On save, status promotes from <em>Invited</em> to <em>Submitted</em> automatically. No login or magic link required — works on any device. Place this shortcode on the page configured in "Vendor Edit Page" above. Enable Sandbox Mode to test without affecting real vendors.</td>
                    <td class="oha-sc-meta">All intake fields + _oha_status, _vendor_permanent_id, _company_logo_id, _company_logo_url</td>
                    <td>None</td>
                    <td>Vendor Edit Page must be set in Settings. Active Convention Year must be set for correct year matching.</td>
                </tr>
                <tr>
                    <td><span class="oha-sc-tag">[oha_vendor_final_form]</span></td>
                    <td><span class="oha-sc-badge oha-sc-badge-pub">Public<br>(token required)</span></td>
                    <td>Final application step: displays agreement, captures e-signature (canvas), then redirects to Stripe for payment. Requires a valid <code>?app_id=&amp;token=</code> in the URL (sent by admin via "Send Final App" button).</td>
                    <td class="oha-sc-meta">_signature_name, _signature_date, _signature_ip, _signature_data, _agree_terms, _oha_status, _payment_url, _stripe_session_id</td>
                    <td>None</td>
                    <td>Stripe Payment Links (configured in Settings)</td>
                </tr>
                <tr>
                    <td><span class="oha-sc-tag">[oha_vendor_final_form_no_payment]</span></td>
                    <td><span class="oha-sc-badge oha-sc-badge-pub">Public<br>(token required)</span></td>
                    <td>Same as final form but skips the Stripe payment step. Used for vendors who are pre-paid or comped. Sets <code>_is_pre_paid = 1</code> on the application.</td>
                    <td class="oha-sc-meta">Same as final form; adds _is_pre_paid</td>
                    <td>None</td>
                    <td>None (no Stripe)</td>
                </tr>
                <tr>
                    <td><span class="oha-sc-tag">[oha_vendor_cards]</span></td>
                    <td><span class="oha-sc-badge oha-sc-badge-pub">Public</span></td>
                    <td>Public vendor card grid showing all Paid vendors for the active convention year. Searchable. Displays logo, company name, product type, and optionally website. Year filter uses the "Active Convention Year" setting.</td>
                    <td class="oha-sc-meta">_oha_status (Paid), _convention_year, company_name, product_type, website, _company_logo_id, _company_logo_url</td>
                    <td><code>title=""</code> - grid heading text</td>
                    <td>None</td>
                </tr>
                <tr>
                    <td><span class="oha-sc-tag">[oha_vendor_status_table year="2028"]</span></td>
                    <td><span class="oha-sc-badge oha-sc-badge-restricted">Restricted<br>(specific user IDs + admins)</span></td>
                    <td>Read-only paginated status table showing all vendor applications. Allowed user IDs are configured in Settings (default: 183). The <code>year</code> attribute filters by convention year; omit to show all years.</td>
                    <td class="oha-sc-meta">_oha_status, _convention_year, company_name, first_name, last_name, email, booths</td>
                    <td><code>year=""</code> - filter by year (e.g. 2027)<br><code>per_page=""</code> - rows per page (default 20)</td>
                    <td>None (optionally integrates with UsersWP account tab)</td>
                </tr>
                <tr>
                    <td><span class="oha-sc-tag">[oha_vendor_boardhub]</span></td>
                    <td><span class="oha-sc-badge oha-sc-badge-admin">Admin only<br>(manage_options)</span></td>
                    <td>Full vendor management dashboard for board members. Card-based UI with search, status filters, bulk actions, email sending, agreement generation, Stripe payment links, and vendor detail modal. Requires admin login.</td>
                    <td class="oha-sc-meta">All meta keys (reads and writes full application data)</td>
                    <td>None</td>
                    <td>None (Stripe links are pre-configured URLs)</td>
                </tr>
                <tr>
                    <td><span class="oha-sc-tag">[oha_vendor_dashboard]</span></td>
                    <td><span class="oha-sc-badge oha-sc-badge-restricted">Logged-in users</span></td>
                    <td>Shows the logged-in user's own vendor application submissions. Displays status and a link to edit. Requires the vendor to have a WordPress account that matches their application.</td>
                    <td class="oha-sc-meta">_oha_status, company_name, _convention_year (read only)</td>
                    <td>None</td>
                    <td>Requires vendor WP account</td>
                </tr>
            </tbody>
        </table>

        <h3 style="margin-top:0;">Legacy / Alias Shortcodes</h3>
        <p style="color:#666;font-size:13px;">These are registered for backward compatibility. Do not place them on new pages - use <code>[oha_vendor_boardhub]</code> instead.</p>
        <table class="oha-sc-table" style="max-width:500px;">
            <thead><tr><th>Shortcode</th><th>Resolves To</th></tr></thead>
            <tbody>
                <tr><td><span class="oha-sc-tag">vendor_dashboard</span></td><td><code>[oha_vendor_boardhub]</code></td></tr>
                <tr><td><span class="oha-sc-tag">admin_vendor_list</span></td><td><code>[oha_vendor_boardhub]</code></td></tr>
            </tbody>
        </table>

    </div>
    <?php
}
