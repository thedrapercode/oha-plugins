<?php
if (!defined('ABSPATH')) exit;

/**
 * Returns the correct Stripe payment link based on booth count and mode.
 * Uses the 2-booth link when booths >= 2 and one is configured; falls back to the standard link.
 */
function oha_vh_get_stripe_payment_link( $booths, $stripe_mode ) {
    $booths = max( 1, intval( $booths ) );
    if ( $booths >= 2 ) {
        $link = $stripe_mode === 'test'
            ? get_option( 'oha_vhcpt_stripe_link_test_2booth', '' )
            : get_option( 'oha_vhcpt_stripe_link_live_2booth', '' );
        if ( ! empty( $link ) ) return $link;
    }
    return $stripe_mode === 'test'
        ? get_option( 'oha_vhcpt_stripe_link_test', '' )
        : get_option( 'oha_vhcpt_stripe_link_live', '' );
}

/**
 * Append entry to vendor application history
 * 
 * CRITICAL: Safe parsing - handles array, JSON, or serialized formats
 * Prevents fatal "json_decode(): Argument #1 must be of type string, array given" errors
 * 
 * @param int $post_id Vendor application ID
 * @param array $entry History entry to append
 */
function oha_vhcpt_append_history($post_id, $entry){
    $hist = get_post_meta($post_id, '_oha_history', true);
    
    // Safe parsing: handle array, JSON, or serialized formats
    $arr = [];
    
    if (!empty($hist)) {
        // Handle serialized data
        if (is_string($hist)) {
            $maybe = maybe_unserialize($hist);
            if ($maybe !== false || $hist === 'b:0;') {
                $hist = $maybe;
            }
        }
        
        // Now handle array or JSON
        if (is_array($hist)) {
            $arr = $hist;
        } elseif (is_string($hist) && $hist !== '') {
            $decoded = json_decode($hist, true);
            if (is_array($decoded)) {
                $arr = $decoded;
            }
        }
    }
    
    // Add new entry with metadata
    $entry['by'] = wp_get_current_user() ? wp_get_current_user()->user_login : 'system';
    $entry['ts'] = current_time('mysql');
    $arr[] = $entry;
    
    // Always store as JSON string for consistency
    update_post_meta($post_id, '_oha_history', wp_json_encode($arr));
}


/**
 * Get configured email automation steps.
 */
function oha_vhcpt_get_email_steps(){
    $steps = get_option('oha_vhcpt_email_steps', []);
    if (!is_array($steps)) $steps = [];
    return $steps;
}

/**
 * Normalize status to key used in settings.
 */
function oha_vhcpt_status_to_key($status){
    $status = strtolower(trim((string)$status));
    $status = str_replace([' ', '-'], '_', $status);
    return $status;
}

/**
 * Send an automated email for a given status, if enabled.
 */
function oha_vhcpt_maybe_send_status_email($post_id, $status){
    $steps = oha_vhcpt_get_email_steps();
    $key   = oha_vhcpt_status_to_key($status);

    if (empty($steps[$key]['enabled'])){
        return;
    }

    $to = get_post_meta($post_id, 'email', true);
    if (empty($to) || !is_email($to)){
        return;
    }

    $subject = isset($steps[$key]['subject']) ? $steps[$key]['subject'] : '';
    $body    = isset($steps[$key]['body'])    ? $steps[$key]['body']    : '';

    if ($subject === '' && $body === ''){
        return;
    }

    // Build smart-tag replacements from meta.
    $meta_keys = [
        'first_name',
        'last_name',
        'company_name',
        'email',
        'phone',
        'address_street',
        'address_city',
        'address_state',
        'address_zip',
        'website',
        'product_type',
        'booths',
        'sponsor',
    ];
    $replacements = [];
    foreach ($meta_keys as $mk){
        $replacements['{'.$mk.'}'] = get_post_meta($post_id, $mk, true);
    }

    $replacements['{status}']         = $status;
    $replacements['{agreement_link}'] = get_post_meta($post_id, '_agreement_url', true);
    $replacements['{payment_link}']   = get_post_meta($post_id, '_payment_url', true);
    $replacements['{application_id}'] = $post_id;
    $perm_id = get_post_meta($post_id, '_vendor_permanent_id', true);
    $replacements['{vendor_id}']      = $perm_id ?: $post_id;

    $subject = strtr($subject, $replacements);
    $body    = strtr($body, $replacements);

    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    
    // Track email send success/failure
    $sent = wp_mail($to, $subject, $body, $headers);

    oha_vhcpt_append_history($post_id, [
        'type'    => 'auto_email',
        'status'  => $status,
        'subject' => $subject,
        'to'      => $to,
        'sent'    => $sent ? 'success' : 'FAILED',
    ]);

    // Log failed emails for admin review
    if (!$sent) {
        error_log("OHA Vendor Hub: Failed to send '{$status}' email to {$to} for application #{$post_id}");
        
        // Also store failed email info for admin dashboard visibility
        update_post_meta($post_id, '_last_email_failed', current_time('mysql'));
    } else {
        // Clear any previous failure marker
        delete_post_meta($post_id, '_last_email_failed');
    }
    
    // CRITICAL: If status is "Paid", also generate and email the PDF agreement
    if ($status === 'Paid' && function_exists('oha_vhcpt_email_vendor_pdf')) {
        // Check if PDF was already sent to avoid duplicates
        $pdf_emailed = get_post_meta($post_id, '_pdf_emailed', true);
        
        if (empty($pdf_emailed)) {
            error_log("OHA: Status changed to Paid - generating and emailing PDF for app_id: " . $post_id);
            
            // Generate and email PDF with signature
            $pdf_sent = oha_vhcpt_email_vendor_pdf($post_id);
            
            if ($pdf_sent) {
                error_log("OHA: PDF generated and emailed successfully for app_id: " . $post_id);
                
                // Add history entry
                if (function_exists('oha_vhcpt_append_history')) {
                    oha_vhcpt_append_history($post_id, [
                        'type' => 'pdf_sent_on_status_change',
                        'status' => 'Paid',
                    ]);
                }
            } else {
                error_log("OHA: PDF generation/email FAILED for app_id: " . $post_id);
            }
        } else {
            error_log("OHA: PDF already sent for app_id: " . $post_id . " - skipping duplicate");
        }
    }
}


// CSV export of vendor_application posts + meta
add_action('admin_post_oha_vhcpt_export_csv', function(){
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    check_admin_referer('oha_vhcpt_export_csv');

    $q = new WP_Query([
        'post_type' => 'vendor_application',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=vendor_applications.csv');
    $out = fopen('php://output', 'w');
    // headers
    $headers = ['ID','Date','Author','Company','First','Last','Email','Phone','Address','City','State','Zip','Website','Product Type','Booths','Sponsor','Electric','Compressed Air','Table','Status','Agreement URL','Payment URL'];
    fputcsv($out, $headers);

    if ($q->have_posts()){
        while($q->have_posts()){ $q->the_post();
            $id = get_the_ID();
            $row = [
                $id,
                get_the_date('Y-m-d H:i:s',$id),
                get_the_author(),
                get_post_meta($id,'company_name',true),
                get_post_meta($id,'first_name',true),
                get_post_meta($id,'last_name',true),
                get_post_meta($id,'email',true),
                get_post_meta($id,'phone',true),
                get_post_meta($id,'address_street',true),
                get_post_meta($id,'address_city',true),
                get_post_meta($id,'address_state',true),
                get_post_meta($id,'address_zip',true),
                get_post_meta($id,'website',true),
                get_post_meta($id,'product_type',true),
                get_post_meta($id,'booths',true),
                get_post_meta($id,'sponsor',true) ? 'Yes' : 'No',
                get_post_meta($id,'need_electric',true) ? 'Yes' : 'No',
                get_post_meta($id,'need_air',true) ? 'Yes' : 'No',
                get_post_meta($id,'need_table',true) ? 'Yes' : 'No',
                get_post_meta($id,'_oha_status',true),
                get_post_meta($id,'_agreement_url',true),
                get_post_meta($id,'_payment_url',true),
            ];
            fputcsv($out, $row);
        }
        wp_reset_postdata();
    }
    fclose($out);
    exit;
});

// Save admin inline update (status/links) from Board Hub table
add_action('admin_post_oha_vhcpt_update_row', function(){
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    check_admin_referer('oha_vhcpt_update_row');

    $id = intval($_POST['id'] ?? 0);
    if (!$id) wp_redirect(wp_get_referer());

    $old_status = get_post_meta($id,'_oha_status',true);
    $old_agree  = get_post_meta($id,'_agreement_url',true);
    $old_pay    = get_post_meta($id,'_payment_url',true);

    $new_status = sanitize_text_field($_POST['status'] ?? 'Submitted');
    $new_agree  = esc_url_raw($_POST['agreement_link'] ?? '');
    $new_pay    = esc_url_raw($_POST['payment_link'] ?? '');

    update_post_meta($id, '_oha_status', $new_status);
    update_post_meta($id, '_agreement_url', $new_agree);
    update_post_meta($id, '_payment_url', $new_pay);

    oha_vhcpt_append_history($id, [
        'type' => 'update',
        'status_from' => $old_status,
        'status_to' => $new_status,
        'agreement_from' => $old_agree,
        'agreement_to' => $new_agree,
        'payment_from' => $old_pay,
        'payment_to' => $new_pay,
    ]);

    // Automated email for this status (if enabled in settings).
    oha_vhcpt_maybe_send_status_email($id, $new_status);

    wp_redirect(wp_get_referer());
    exit;
});

// Send templated email to applicant
add_action('admin_post_oha_vhcpt_send_email', function(){
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    check_admin_referer('oha_vhcpt_send_email');

    $id = intval($_POST['id'] ?? 0);
    $subject = sanitize_text_field($_POST['subject'] ?? 'OHA Vendor Update');
    if (!$id) wp_redirect(wp_get_referer());

    $to = get_post_meta($id,'email',true);
    $vars = [
        '{first_name}' => get_post_meta($id,'first_name',true),
        '{last_name}' => get_post_meta($id,'last_name',true),
        '{company_name}' => get_post_meta($id,'company_name',true),
        '{status}' => get_post_meta($id,'_oha_status',true),
        '{agreement_link}' => get_post_meta($id,'_agreement_url',true),
        '{payment_link}' => get_post_meta($id,'_payment_url',true),
    ];
    $tpl = get_option('oha_vhcpt_email_tpl',"Hello {first_name},\n\nStatus: {status}\nAgreement: {agreement_link}\nPayment: {payment_link}\n\n— OHA");
    $body = strtr($tpl, $vars);

    if ($to){
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        $sent = wp_mail($to, $subject, $body, $headers);
        
        oha_vhcpt_append_history($id, [
            'type' => 'email',
            'subject' => $subject,
            'to' => $to,
            'sent' => $sent ? 'success' : 'FAILED',
        ]);

        if (!$sent) {
            error_log("OHA Vendor Hub: Failed to send manual email to {$to} for application #{$id}");
        }
    }

    wp_redirect(wp_get_referer());
    exit;
});

// Bulk update status
add_action('admin_post_oha_vhcpt_bulk_update', function(){
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    check_admin_referer('oha_vhcpt_bulk_update');

    $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
    $new_status = sanitize_text_field($_POST['bulk_status'] ?? '');

    if ($ids && $new_status){
        foreach($ids as $id){
            $old_status = get_post_meta($id,'_oha_status',true);
            update_post_meta($id,'_oha_status',$new_status);
            oha_vhcpt_append_history($id, [
                'type' => 'bulk_status',
                'status_from' => $old_status,
                'status_to' => $new_status,
            ]);
            
            // Send automated email for each updated application
            oha_vhcpt_maybe_send_status_email($id, $new_status);
        }
    }
    wp_redirect(wp_get_referer());
    exit;
});


/** AJAX status/link update to avoid cache issues */
add_action('wp_ajax_oha_vhcpt_update_row_ajax', function(){
    if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Unauthorized'], 403);
    check_ajax_referer('oha_vhcpt_update_row_ajax', 'nonce');

    $id = intval($_POST['id'] ?? 0);
    if (!$id) wp_send_json_error(['message'=>'Missing ID'], 400);

    $old_status = get_post_meta($id,'_oha_status',true);
    $old_agree  = get_post_meta($id,'_agreement_url',true);
    $old_pay    = get_post_meta($id,'_payment_url',true);

    $new_status = sanitize_text_field($_POST['status'] ?? $old_status);
    $new_agree  = esc_url_raw($_POST['agreement_link'] ?? $old_agree);
    $new_pay    = esc_url_raw($_POST['payment_link'] ?? $old_pay);

    update_post_meta($id, '_oha_status', $new_status);
    update_post_meta($id, '_agreement_url', $new_agree);
    update_post_meta($id, '_payment_url', $new_pay);

    oha_vhcpt_append_history($id, [
        'type' => 'update',
        'status_from' => $old_status,
        'status_to' => $new_status,
        'agreement_from' => $old_agree,
        'agreement_to' => $new_agree,
        'payment_from' => $old_pay,
        'payment_to' => $new_pay,
    ]);

    // Automated email for this status (if enabled in settings).
    oha_vhcpt_maybe_send_status_email($id, $new_status);

    wp_send_json_success([
        'status' => $new_status,
        'agreement' => $new_agree,
        'payment' => $new_pay,
    ]);
});

/**
 * AJAX: Send Final Application Email
 * Generates pre-populated link and sends to vendor
 */
add_action('wp_ajax_oha_send_final_app_email', function(){
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'oha_send_final_app')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Get application ID
    $app_id = isset($_POST['app_id']) ? intval($_POST['app_id']) : 0;
    if (!$app_id || get_post_type($app_id) !== 'vendor_application') {
        wp_send_json_error('Invalid application ID');
        return;
    }
    
    // Get vendor info
    $vendor_email = get_post_meta($app_id, 'email', true);
    $company_name = get_post_meta($app_id, 'company_name', true);
    $first_name = get_post_meta($app_id, 'first_name', true);
    
    if (!is_email($vendor_email)) {
        wp_send_json_error('Invalid email address');
        return;
    }
    
    // Get final application page URL
    // Try to find page with [oha_vendor_final_form] shortcode
    $pages = get_posts([
        'post_type' => 'page',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        's' => '[oha_vendor_final_form]'
    ]);
    
    $final_app_url = '';
    if (!empty($pages)) {
        $final_app_url = get_permalink($pages[0]->ID);
    }
    
    // Fallback: use site URL + /final-vendor-application/
    if (empty($final_app_url)) {
        $final_app_url = home_url('/final-vendor-application/');
    }
    
    // Build pre-populated link
    $final_link = add_query_arg('app_id', $app_id, $final_app_url);
    
    $conv_name    = get_option('oha_vhcpt_convention_name',         'OHA Convention');
    $conv_venue   = get_option('oha_vhcpt_convention_venue',        '');
    $conv_dates   = get_option('oha_vhcpt_convention_dates',        '');
    $conv_load_in = get_option('oha_vhcpt_convention_load_in',      '');
    $conv_price   = get_option('oha_vhcpt_convention_booth_price',  '');

    // Email subject
    $subject = 'Complete Your ' . $conv_name . ' Vendor Application - Action Required';

    // Email body
    $body  = "Hi " . $first_name . ",\n\n";
    $body .= "Good news! Your vendor application for " . $company_name . " has been approved for the " . $conv_name . ".\n\n";
    $body .= "Please complete your final vendor application and payment by clicking the link below:\n\n";
    $body .= $final_link . "\n\n";

    $details = [];
    if ( $conv_venue   ) $details[] = "Venue: " . $conv_venue;
    if ( $conv_dates   ) $details[] = "Dates: " . $conv_dates;
    if ( $conv_load_in ) $details[] = "Load-in: " . $conv_load_in;
    if ( $conv_price   ) $details[] = "Booth Price: " . $conv_price;

    if ( $details ) {
        $body .= "Important Details:\n";
        foreach ( $details as $line ) {
            $body .= "• " . $line . "\n";
        }
        $body .= "\n";
    }

    $body .= "The form will be pre-filled with your information from your initial application. You'll need to:\n";
    $body .= "1. Review and confirm your information\n";
    $body .= "2. Read the venue terms and conditions\n";
    $body .= "3. Sign the electronic agreement\n";
    $body .= "4. Complete payment to secure your booth\n\n";
    $body .= "If you have any questions, please reply to this email.\n\n";
    $body .= "Thanks,\n";
    $body .= "Ohio Haunters Association\n";
    $body .= "https://ohiohaunters.com";
    
    // Headers
    $headers = [
        'From: Ohio Haunters Association <noreply@ohiohaunters.com>',
        'Reply-To: webmaster@ohiohaunters.com',
        'Cc: webmaster@ohiohaunters.com'
    ];
    
    // Send email
    $sent = wp_mail($vendor_email, $subject, $body, $headers);
    
    if ($sent) {
        // Log in history
        oha_vhcpt_append_history($app_id, [
            'type' => 'final_app_email_sent',
            'email' => $vendor_email,
            'link' => $final_link,
            'sent_by' => wp_get_current_user()->user_login,
        ]);
        
        // Update status if not already updated
        $current_status = get_post_meta($app_id, '_oha_status', true);
        if (empty($current_status) || $current_status === 'Submitted' || $current_status === 'Approved') {
            update_post_meta($app_id, '_oha_status', 'Agreement Sent');
        }
        
        wp_send_json_success('Email sent successfully to ' . $vendor_email);
    } else {
        wp_send_json_error('Failed to send email');
    }
});

/**
 * New Send Vendor Email Handler - Handles 3 options
 */
add_action('wp_ajax_oha_send_vendor_email', function() {
    check_ajax_referer('oha_send_vendor_email', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $app_id = intval($_POST['app_id'] ?? 0);
    $option = sanitize_text_field($_POST['option'] ?? '');
    
    if (!$app_id || !$option) {
        wp_send_json_error('Missing app_id or option');
    }
    
    $vendor_email = get_post_meta($app_id, 'email', true);
    if (!is_email($vendor_email)) {
        wp_send_json_error('Invalid email');
    }
    
    $first_name = get_post_meta($app_id, 'first_name', true);
    $last_name = get_post_meta($app_id, 'last_name', true);
    $company = get_post_meta($app_id, 'company_name', true);
    $booths = get_post_meta($app_id, 'booths', true) ?: 1;
    $total = $booths * 250;
    
    // Build links based on option
    $final_app_page = get_option('oha_vhcpt_final_app_page_id', 0);
    if (!$final_app_page) {
        wp_send_json_error('Final app page not configured');
    }
    
    $base_url = get_permalink($final_app_page);
    
    // URL-encode string values — add_query_arg() does NOT encode values, so
    // spaces in company names produce raw spaces that break URLs in plain-text
    // emails (email clients stop URL detection at the first whitespace).
    $encoded_company = rawurlencode($company);
    $encoded_email   = rawurlencode($vendor_email);

    // Standard final app link (with payment)
    $final_app_link = add_query_arg([
        'app_id' => $app_id,
        'email' => $encoded_email,
        'company' => $encoded_company,
        'booths' => $booths,
    ], $base_url);

    // No-payment link - check if separate page configured
    $no_payment_page = get_option('oha_vhcpt_final_app_no_payment_page_id', 0);

    if ($no_payment_page) {
        // Use separate no-payment page
        $no_payment_base_url = get_permalink($no_payment_page);
        $final_app_link_no_payment = add_query_arg([
            'app_id' => $app_id,
            'email' => $encoded_email,
            'company' => $encoded_company,
            'booths' => $booths,
        ], $no_payment_base_url);
    } else {
        // Use same page with ?no_payment_mode=1
        $final_app_link_no_payment = add_query_arg([
            'app_id' => $app_id,
            'email' => $encoded_email,
            'company' => $encoded_company,
            'booths' => $booths,
            'no_payment_mode' => '1',
        ], $base_url);
    }
    
    // Build payment link — select 1-booth or 2-booth Stripe link based on quantity.
    $stripe_mode = get_option('oha_vhcpt_stripe_mode', 'test');
    $payment_link_base = oha_vh_get_stripe_payment_link( $booths, $stripe_mode );

    $payment_link = add_query_arg([
        'prefilled_email'     => $vendor_email,
        'client_reference_id' => $app_id,
    ], $payment_link_base);
    
    // Get email template based on option
    $subject = '';
    $body = '';
    
    if ($option === 'with_payment') {
        $subject = get_option('oha_vhcpt_vendor_send_with_payment_subject', 'Complete Your OHA Convention 2026 Vendor Application');
        $body = get_option('oha_vhcpt_vendor_send_with_payment_body', '');
    } elseif ($option === 'no_payment') {
        $subject = get_option('oha_vhcpt_vendor_send_no_payment_subject', 'Complete Your OHA Convention 2026 Vendor Application (Pre-Paid)');
        $body = get_option('oha_vhcpt_vendor_send_no_payment_body', '');
    } elseif ($option === 'payment_only') {
        $subject = get_option('oha_vhcpt_vendor_send_payment_only_subject', 'Complete Payment for OHA Convention 2026 Vendor Booth');
        $body = get_option('oha_vhcpt_vendor_send_payment_only_body', '');
    }
    
    // Replace placeholders
    $replacements = [
        '{first_name}' => $first_name,
        '{last_name}' => $last_name,
        '{company_name}' => $company,
        '{email}' => $vendor_email,
        '{booths}' => $booths,
        '{total}' => number_format($total, 2),
        '{final_app_link}' => $final_app_link,
        '{final_app_link_no_payment}' => $final_app_link_no_payment,
        '{payment_link}' => $payment_link,
    ];
    
    $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
    $body = str_replace(array_keys($replacements), array_values($replacements), $body);
    
    // If this is a no-payment send, stamp the application record now so the form
    // can bypass Stripe regardless of which URL the vendor uses.
    if ($option === 'no_payment') {
        update_post_meta($app_id, '_is_pre_paid', '1');
    }

    // Send email
    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    $sent = wp_mail($vendor_email, $subject, $body, $headers);

    if ($sent) {
        // Log in history
        if (function_exists('oha_vhcpt_append_history')) {
            oha_vhcpt_append_history($app_id, [
                'type' => 'vendor_email_sent',
                'option' => $option,
                'to' => $vendor_email,
                'subject' => $subject,
            ]);
        }

        // Update status if needed
        $current_status = get_post_meta($app_id, '_oha_status', true);
        if (empty($current_status) || $current_status === 'Submitted') {
            update_post_meta($app_id, '_oha_status', 'Agreement Sent');
        }
        
        wp_send_json_success('Email sent successfully');
    } else {
        wp_send_json_error('Failed to send email');
    }
});


/**
 * Handle CSV Export - Must run BEFORE any output
 */
add_action('init', function() {
    // Only process if export was requested
    if (!isset($_POST['oha_export_csv']) || !isset($_POST['oha_export_nonce'])) {
        return;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['oha_export_nonce'], 'oha_export_vendors')) {
        wp_die('Security check failed');
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    // Get all vendors
    $vendors_query = new WP_Query([
        'post_type' => 'vendor_application',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);

    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set CSV headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="oha-vendors-' . date('Y-m-d-His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Write CSV header row
    fputcsv($output, [
        'ID',
        'Date',
        'Company',
        'Contact',
        'Email',
        'Phone',
        'Website',
        'Product Type',
        'Booths',
        'Electric',
        'Air',
        'Table',
        'Sponsor',
        'Status',
        'Signed Date',
        'PDF Emailed',
        'Paid',
        'Agreement URL',
        'Logo URL',
        'Payment Link',
        'Stripe Session ID'
    ]);
    
    // Write vendor data
    if ($vendors_query->have_posts()) {
        while ($vendors_query->have_posts()) {
            $vendors_query->the_post();
            $id = get_the_ID();
            
            // Get status
            $status = get_post_meta($id, '_oha_status', true) ?: 'Submitted';
            $is_paid = ($status === 'Paid');
            
            // Get agreement URL (check multiple meta keys for backwards compatibility)
            $agreement_url = get_post_meta($id, '_agreement_url', true);
            if (empty($agreement_url)) {
                $agreement_url = get_post_meta($id, '_pdf_url', true);
            }
            if (empty($agreement_url)) {
                $agreement_url = get_post_meta($id, '_agreement_file', true);
            }
            
            // Get logo URL
            $logo_url = get_post_meta($id, '_company_logo_url', true);
            
            // Get payment link
            $booths = get_post_meta($id, 'booths', true) ?: 1;
            $test_mode = get_option('oha_vhcpt_test_mode', 0);
            $payment_link_key = $test_mode ? 'oha_vhcpt_stripe_link_test' : 'oha_vhcpt_stripe_link_live';
            $payment_link = get_option($payment_link_key, '');
            
            // Get Stripe session ID
            $stripe_session = get_post_meta($id, '_stripe_session_id', true);
            
            // Write row
            fputcsv($output, [
                $id,
                get_the_date('Y-m-d H:i', $id),
                get_post_meta($id, 'company_name', true),
                trim(get_post_meta($id, 'first_name', true) . ' ' . get_post_meta($id, 'last_name', true)),
                get_post_meta($id, 'email', true),
                get_post_meta($id, 'phone', true),
                get_post_meta($id, 'website', true),
                get_post_meta($id, 'product_type', true),
                $booths,
                get_post_meta($id, 'need_electric', true) ? 'Yes' : 'No',
                get_post_meta($id, 'need_air', true) ? 'Yes' : 'No',
                get_post_meta($id, 'need_table', true) ? 'Yes' : 'No',
                get_post_meta($id, 'sponsor', true) ? 'Yes' : 'No',
                $status,
                get_post_meta($id, '_signature_date', true),
                get_post_meta($id, '_pdf_emailed', true) ? 'Yes' : 'No',
                $is_paid ? 'Yes' : 'No',
                $agreement_url,
                $logo_url,
                $payment_link,
                $stripe_session
            ]);
        }
        wp_reset_postdata();
    }
    
    // Close and exit
    fclose($output);
    exit;
}, 1); // Priority 1 - run early

/**
 * AJAX: Manual Send Agreement Email
 */
add_action('wp_ajax_oha_manual_send_agreement', function() {
    // Verify nonce
    if (!check_ajax_referer('oha_manual_send_agreement', 'nonce', false)) {
        wp_send_json_error('Security check failed');
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $app_id = intval($_POST['app_id'] ?? 0);
    if (!$app_id) {
        wp_send_json_error('Invalid application ID');
    }
    
    // Send agreement email
    if (function_exists('oha_vhcpt_email_vendor_pdf')) {
        $sent = oha_vhcpt_email_vendor_pdf($app_id);
        
        if ($sent) {
            // Add history entry
            if (function_exists('oha_vhcpt_append_history')) {
                oha_vhcpt_append_history($app_id, [
                    'type' => 'manual_email_sent',
                    'sent_by' => get_current_user_id(),
                    'sent_by_name' => wp_get_current_user()->display_name,
                ]);
            }
            
            wp_send_json_success('Agreement email sent successfully');
        } else {
            wp_send_json_error('Failed to send email');
        }
    } else {
        wp_send_json_error('Email function not available');
    }
});

/**
 * AJAX: Get Vendor Details
 */
add_action('wp_ajax_oha_get_vendor_details', function() {
    // Verify nonce
    if (!check_ajax_referer('oha_get_vendor_details', 'nonce', false)) {
        wp_send_json_error('Security check failed');
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $app_id = intval($_POST['app_id'] ?? 0);
    if (!$app_id) {
        wp_send_json_error('Invalid application ID');
    }

    // Validate the requested post is actually a vendor_application.
    // Without this check an admin could pull post meta from any post ID in the DB.
    if (get_post_type($app_id) !== 'vendor_application') {
        wp_send_json_error('Invalid application ID');
        return;
    }

    // Get all vendor data
    $vendor = [
        'app_id' => $app_id,
        'company_name' => get_post_meta($app_id, 'company_name', true),
        'first_name' => get_post_meta($app_id, 'first_name', true),
        'last_name' => get_post_meta($app_id, 'last_name', true),
        'email' => get_post_meta($app_id, 'email', true),
        'phone' => get_post_meta($app_id, 'phone', true),
        'website' => get_post_meta($app_id, 'website', true),
        'product_type' => get_post_meta($app_id, 'product_type', true),
        'booths'        => get_post_meta($app_id, 'booths', true) ?: 1,
        'tables_needed' => get_post_meta($app_id, 'tables_needed', true) ?: 0,
        'need_electric' => get_post_meta($app_id, 'need_electric', true) ? true : false,
        'need_air'      => get_post_meta($app_id, 'need_air', true) ? true : false,
        'need_table'    => get_post_meta($app_id, 'need_table', true) ? true : false,
        'sponsor' => get_post_meta($app_id, 'sponsor', true) ? true : false,
        'vendor_private_notes' => get_post_meta($app_id, 'vendor_private_notes', true) ?: '',
        'returning_vendor'     => (bool) get_post_meta($app_id, 'returning_vendor', true),
        'booth_preference'     => get_post_meta($app_id, 'booth_preference', true) ?: '',
        'convention_year'      => get_post_meta($app_id, '_convention_year', true) ?: '',
        'vendor_permanent_id'  => get_post_meta($app_id, '_vendor_permanent_id', true) ?: $app_id,
        'status' => get_post_meta($app_id, '_oha_status', true) ?: 'Submitted',
        'status_class' => sanitize_title_with_dashes(get_post_meta($app_id, '_oha_status', true) ?: 'Submitted'),
        'date' => get_the_date('M j, Y g:i A', $app_id),
        'signature_date' => get_post_meta($app_id, '_signature_date', true),
        'payment_date' => get_post_meta($app_id, '_pdf_emailed', true),
    ];
    
    // Get history
    $history_raw = get_post_meta($app_id, '_oha_history', true);
    $history = [];
    if ($history_raw && is_array($history_raw)) {
        foreach (array_reverse($history_raw) as $entry) {
            $description = '';
            switch ($entry['type'] ?? '') {
                case 'application_submitted':
                    $description = '📝 Application submitted';
                    break;
                case 'final_application_signed':
                    $description = '✍️ Agreement signed';
                    break;
                case 'payment_received':
                    $description = '💰 Payment received ($' . ($entry['amount'] ?? '0') . ')';
                    break;
                case 'pdf_generated':
                    $description = '📄 PDF generated';
                    break;
                case 'email_sent':
                    $description = '📧 Email sent to ' . ($entry['to'] ?? 'vendor');
                    break;
                case 'pre_paid_vendor':
                    $description = '✅ Pre-paid vendor confirmed';
                    break;
                case 'manual_email_sent':
                    $description = '📧 Agreement manually resent by ' . ($entry['sent_by_name'] ?? 'admin');
                    break;
                case 'payment_completed_webhook':
                    $description = '💳 Payment processed via webhook';
                    break;
                case 'admin_status_override':
                    $from = $entry['status_from'] ?? '?';
                    $to   = $entry['status_to']   ?? '?';
                    $description = '🔧 Admin overrode status: ' . esc_html($from) . ' → ' . esc_html($to);
                    break;
                default:
                    $description = '• ' . esc_html($entry['type'] ?? 'Unknown');
            }
            
            $history[] = [
                'date' => date('M j, Y g:i A', $entry['timestamp'] ?? time()),
                'description' => $description,
            ];
        }
    }
    $vendor['history'] = $history;
    
    wp_send_json_success($vendor);
});


/* ==========================================================================
   Bulk Email Vendors via JEmail — v3.2.0
   Lets board admins compose and queue a bulk email to vendors directly from
   the [oha_vendor_boardhub] frontend shortcode. Requires JEmail Bulk Sender.
   ========================================================================== */

/**
 * AJAX: Count vendor recipients matching the selected statuses.
 * Used by the "X vendors will receive this" live preview in the UI.
 */
add_action('wp_ajax_oha_vh_bulk_email_count', function() {
    check_ajax_referer('oha_vh_bulk_email', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $statuses = isset($_POST['vendor_statuses']) && is_array($_POST['vendor_statuses'])
        ? array_map('sanitize_text_field', $_POST['vendor_statuses'])
        : array();

    if (empty($statuses)) {
        wp_send_json_success(array('count' => 0));
        return;
    }

    // Gather all vendor emails matching these statuses (includes non-WP-user vendors)
    $emails = oha_vh_get_vendor_emails_by_status($statuses);
    wp_send_json_success(array('count' => count($emails)));
});

/**
 * AJAX: Queue a bulk vendor email campaign via JEmail.
 * Creates a JEmail campaign, queues all matching vendor recipients, and
 * returns immediately WITHOUT processing the first batch synchronously.
 * This prevents the page freeze that occurs when wp_mail() is called in a loop
 * on the same HTTP request. Batches are processed by WP Cron / admin page loads.
 */
add_action('wp_ajax_oha_vh_bulk_email_vendors', function() {
    check_ajax_referer('oha_vh_bulk_email', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    // JEmail must be active
    if (!class_exists('JEmail_Bulk_Sender') || !isset(jemail_init()->db) || !isset(jemail_init()->sender)) {
        wp_send_json_error('JEmail Bulk Sender plugin is not active. Please activate it to use bulk email.');
    }

    $subject      = sanitize_text_field(wp_unslash($_POST['subject'] ?? ''));
    $html_content = wp_kses_post(wp_unslash($_POST['html_content'] ?? ''));
    $statuses     = isset($_POST['vendor_statuses']) && is_array($_POST['vendor_statuses'])
                    ? array_map('sanitize_text_field', $_POST['vendor_statuses'])
                    : array();

    if (empty($subject)) {
        wp_send_json_error('Subject line is required.');
    }
    if (empty($html_content)) {
        wp_send_json_error('Email body cannot be empty.');
    }
    if (empty($statuses)) {
        wp_send_json_error('Select at least one vendor status to target.');
    }

    $jemail = jemail_init();
    $db     = $jemail->db;

    // Build recipient list: stdClass objects with user_email property
    $vendor_emails = oha_vh_get_vendor_emails_by_status($statuses);
    if (empty($vendor_emails)) {
        wp_send_json_error('No vendors found matching the selected statuses.');
    }

    $recipients = array();
    foreach ($vendor_emails as $email_data) {
        // Skip unsubscribed addresses
        if ($db->is_unsubscribed($email_data['email'])) {
            continue;
        }
        $obj               = new stdClass();
        $obj->user_email   = $email_data['email'];
        $obj->ID           = $email_data['user_id']; // 0 for non-WP-user vendors
        $obj->display_name = $email_data['display_name'];
        $recipients[]      = $obj;
    }

    if (empty($recipients)) {
        wp_send_json_error('All matching vendors are unsubscribed.');
    }

    $batch_size = absint(get_option('jemail_batch_size', 50));

    // Insert campaign
    $campaign_data = array(
        'title'                  => 'Vendor Bulk Email - ' . implode(', ', $statuses) . ' - ' . date('Y-m-d H:i'),
        'subject'                => $subject,
        'html_content'           => $html_content,
        'text_content'           => '',
        'content_type'           => 'html',
        'target_vendor_statuses' => maybe_serialize($statuses),
        'status'                 => 'sending',
        'batch_size'             => $batch_size,
        'total_count'            => count($recipients),
    );
    $campaign_id = $db->insert_campaign($campaign_data);

    if (!$campaign_id) {
        wp_send_json_error('Failed to create email campaign. Please try again.');
    }

    // Queue all recipients
    $db->queue_recipients($campaign_id, $recipients);

    // NOTE: We intentionally do NOT call process_batch() here.
    // Processing synchronously on the same HTTP request causes the page freeze
    // the user reported. Batches will be processed by:
    //   1. WP Cron (jemail_process_batch event, fires every jemail_batch_delay seconds)
    //   2. Any admin page load (JEmail's maybe_process_on_load, throttled to 15s)
    // The frontend polls oha_vh_bulk_email_status every 8 seconds for progress.

    wp_send_json_success(array(
        'campaign_id' => $campaign_id,
        'queued'      => count($recipients),
        'batch_size'  => $batch_size,
        'message'     => count($recipients) . ' emails queued. Sending will begin automatically.',
    ));
});

/**
 * AJAX: Poll campaign send progress.
 */
add_action('wp_ajax_oha_vh_bulk_email_status', function() {
    check_ajax_referer('oha_vh_bulk_email', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $campaign_id = absint($_POST['campaign_id'] ?? 0);
    if (!$campaign_id) {
        wp_send_json_error('No campaign ID.');
    }

    if (!class_exists('JEmail_Bulk_Sender')) {
        wp_send_json_error('JEmail not active.');
    }

    $jemail   = jemail_init();
    $campaign = $jemail->db->get_campaign($campaign_id);
    $stats    = $jemail->db->get_campaign_stats($campaign_id);

    if (!$campaign) {
        wp_send_json_error('Campaign not found.');
    }

    wp_send_json_success(array(
        'status'      => $campaign->status,
        'sent_count'  => (int) $campaign->sent_count,
        'fail_count'  => (int) $campaign->fail_count,
        'total_count' => (int) $campaign->total_count,
        'queued'      => $stats ? (int) $stats->queued : 0,
    ));
});

/* ==========================================================================
   Fast-Track Vendor Invite — v3.3.0
   Lets board admins send a professional invitation email to a new vendor with
   a direct link to the final application form — no existing application needed.
   ========================================================================== */

/**
 * AJAX: Send a fast-track invitation email to a new vendor.
 * Does not require an existing vendor_application post.
 */
add_action('wp_ajax_oha_vh_fasttrack_invite', function() {
    check_ajax_referer('oha_vh_fasttrack_invite', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $to_email = sanitize_email(wp_unslash($_POST['invite_email'] ?? ''));
    $to_name  = sanitize_text_field(wp_unslash($_POST['invite_name'] ?? ''));

    if (!is_email($to_email)) {
        wp_send_json_error('Please enter a valid email address.');
        return;
    }

    // Get final application URL from settings, fall back to known page URL
    $final_app_page_id = get_option('oha_vhcpt_final_app_page_id', 0);
    if ($final_app_page_id) {
        $final_app_url = get_permalink($final_app_page_id);
    } else {
        $final_app_url = 'https://ohiohaunters.com/convention-registration/final-vendor-application/';
    }

    $conv_name    = get_option('oha_vhcpt_convention_name',        'OHA Convention');
    $conv_venue   = get_option('oha_vhcpt_convention_venue',       '');
    $conv_dates   = get_option('oha_vhcpt_convention_dates',       '');
    $conv_load_in = get_option('oha_vhcpt_convention_load_in',     '');

    $greeting = $to_name ? 'Hi ' . $to_name . ',' : 'Hello,';

    $subject  = "You're Invited to Vend at the " . $conv_name;

    $body  = $greeting . "\n\n";
    $body .= "We are thrilled to invite you to join us as a vendor at the " . $conv_name . "!\n\n";
    $body .= "This is a fantastic opportunity to showcase your products to hundreds of haunted attraction enthusiasts, ";
    $body .= "professionals, and fans from across the region.\n\n";

    if ( $conv_venue || $conv_dates || $conv_load_in ) {
        $body .= "EVENT DETAILS\n";
        if ( $conv_venue   ) $body .= "Venue:    " . $conv_venue   . "\n";
        if ( $conv_dates   ) $body .= "Dates:    " . $conv_dates   . "\n";
        if ( $conv_load_in ) $body .= "Load-In:  " . $conv_load_in . "\n";
        $body .= "\n";
    }

    $body .= "To secure your booth, please complete your vendor application using the link below:\n\n";
    $body .= $final_app_url . "\n\n";
    $body .= "Booth spaces are limited and fill up quickly, so we encourage you to complete your application as soon as possible.\n\n";
    $body .= "If you have any questions or need assistance, please don't hesitate to reply to this email. ";
    $body .= "We look forward to having you with us this year!\n\n";
    $body .= "Warm regards,\n";
    $body .= "Ohio Haunters Association\n";
    $body .= "https://ohiohaunters.com";

    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'From: Ohio Haunters Association <noreply@ohiohaunters.com>',
        'Reply-To: info@ohiohaunters.com',
    ];

    $sent = wp_mail($to_email, $subject, $body, $headers);

    if ($sent) {
        wp_send_json_success('Invitation sent to ' . $to_email . '.');
    } else {
        wp_send_json_error('Failed to send invitation. Please check server email settings.');
    }
});


/**
 * AJAX: Admin Override — force-set a vendor application to a specific status.
 *
 * Bypasses automated email triggers and Stripe idempotency guards.
 * When reverting FROM Paid, clears payment markers so the vendor can re-enter
 * the normal sign + pay flow without being blocked by duplicate-application guards.
 * The existing agreement file and signature date are preserved as a historical record.
 */
add_action('wp_ajax_oha_vh_admin_override_status', function() {
    check_ajax_referer('oha_vh_admin_override', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 403);
    }

    $app_id     = intval($_POST['app_id'] ?? 0);
    $new_status = sanitize_text_field($_POST['new_status'] ?? '');

    $valid_statuses = ['Submitted', 'Approved', 'Agreement Sent', 'Agreement Signed', 'Paid'];
    if (!$app_id || !in_array($new_status, $valid_statuses, true)) {
        wp_send_json_error('Invalid parameters');
    }
    if (get_post_type($app_id) !== 'vendor_application') {
        wp_send_json_error('Invalid application ID');
    }

    $old_status = get_post_meta($app_id, '_oha_status', true) ?: 'Submitted';

    // Update the canonical status meta
    update_post_meta($app_id, '_oha_status', $new_status);

    // When reverting FROM Paid, clear payment/pre-paid markers so the vendor
    // can re-enter the sign + pay flow without being blocked by idempotency guards
    // or the duplicate-application check in final-application.php.
    if ($old_status === 'Paid' && $new_status !== 'Paid') {
        delete_post_meta($app_id, '_agreement_emailed_at');
        delete_post_meta($app_id, '_stripe_session_id');
        delete_post_meta($app_id, '_stripe_payment_intent');
        delete_post_meta($app_id, '_stripe_charge_id');
        delete_post_meta($app_id, '_is_pre_paid');
        delete_post_meta($app_id, '_pre_paid');
        // _agreement_url, _pdf_url, _signature_date preserved as historical record
    }

    // Record override in history
    oha_vhcpt_append_history($app_id, [
        'type'        => 'admin_status_override',
        'status_from' => $old_status,
        'status_to'   => $new_status,
    ]);

    // Recount all vendors so the UI stat bar can be refreshed without a page reload
    $all_vendor_ids = get_posts([
        'post_type'      => 'vendor_application',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    ]);
    $paid_count    = 0;
    $pending_count = 0;
    foreach ($all_vendor_ids as $vid) {
        $s = get_post_meta($vid, '_oha_status', true);
        if ($s === 'Paid') $paid_count++;
        if ($s === 'Pending' || empty($s)) $pending_count++;
    }

    wp_send_json_success([
        'new_status'    => $new_status,
        'old_status'    => $old_status,
        'app_id'        => $app_id,
        'paid_count'    => $paid_count,
        'pending_count' => $pending_count,
        'total_count'   => count($all_vendor_ids),
    ]);
});


/* ==========================================================================
   Send Edit Links — v3.8.0
   Admin tool: generate personalized magic-link edit emails for vendors
   filtered by status. Submenu page: Vendor Applications > Send Edit Links.
   ========================================================================== */

/**
 * Helper: Get vendors matching given statuses with post ID and first name.
 * Returns array of ['app_id', 'email', 'first_name', 'company'].
 * Deduplicates by email address.
 *
 * @param array $statuses Status strings to match against _oha_status meta.
 * @return array
 */
function oha_vh_get_vendors_for_edit_links(array $statuses) {
    global $wpdb;

    if (empty($statuses)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($statuses), '%s'));

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT p.ID,
                pm_email.meta_value  AS email,
                pm_fn.meta_value     AS first_name,
                pm_cn.meta_value     AS company_name
         FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} pm_status ON pm_status.post_id = p.ID AND pm_status.meta_key = '_oha_status'
         LEFT JOIN {$wpdb->postmeta} pm_email   ON pm_email.post_id  = p.ID AND pm_email.meta_key  = 'email'
         LEFT JOIN {$wpdb->postmeta} pm_fn      ON pm_fn.post_id     = p.ID AND pm_fn.meta_key     = 'first_name'
         LEFT JOIN {$wpdb->postmeta} pm_cn      ON pm_cn.post_id     = p.ID AND pm_cn.meta_key     = 'company_name'
         WHERE p.post_type   = 'vendor_application'
           AND p.post_status != 'trash'
           AND COALESCE(pm_status.meta_value, 'Submitted') IN ({$placeholders})",
        $statuses
    ));

    $result = [];
    $seen   = [];

    foreach ((array) $rows as $row) {
        $email = sanitize_email($row->email ?? '');
        if (empty($email) || !is_email($email)) {
            continue;
        }
        if (isset($seen[$email])) {
            continue;
        }
        $seen[$email] = true;

        $result[] = [
            'app_id'     => (int) $row->ID,
            'email'      => $email,
            'first_name' => sanitize_text_field($row->first_name ?? ''),
            'company'    => sanitize_text_field($row->company_name ?? ''),
        ];
    }

    return $result;
}

/**
 * AJAX: Count vendors matching selected statuses (for the Send Edit Links page).
 */
add_action('wp_ajax_oha_vh_edit_links_count', function() {
    check_ajax_referer('oha_vh_send_edit_links', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $statuses = isset($_POST['vendor_statuses']) && is_array($_POST['vendor_statuses'])
        ? array_map('sanitize_text_field', $_POST['vendor_statuses'])
        : [];

    if (empty($statuses)) {
        wp_send_json_success(['count' => 0]);
        return;
    }

    $vendors = oha_vh_get_vendors_for_edit_links($statuses);
    wp_send_json_success(['count' => count($vendors)]);
});

/**
 * AJAX: Generate tokens and send edit-link emails to vendors by status.
 * Sends individually via wp_mail — no JEmail dependency needed.
 */
add_action('wp_ajax_oha_vh_edit_links_send', function() {
    check_ajax_referer('oha_vh_send_edit_links', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $statuses = isset($_POST['vendor_statuses']) && is_array($_POST['vendor_statuses'])
        ? array_map('sanitize_text_field', $_POST['vendor_statuses'])
        : [];

    if (empty($statuses)) {
        wp_send_json_error('Select at least one vendor status.');
        return;
    }

    $edit_page_id = (int) get_option('oha_vhcpt_vendor_edit_page_id', 0);
    if (!$edit_page_id) {
        wp_send_json_error('Vendor Edit Page is not configured in settings.');
        return;
    }

    $edit_page_url = trailingslashit(get_permalink($edit_page_id));
    if (!$edit_page_url) {
        wp_send_json_error('Could not resolve Vendor Edit Page URL.');
        return;
    }

    $vendors = oha_vh_get_vendors_for_edit_links($statuses);
    if (empty($vendors)) {
        wp_send_json_error('No vendors found matching the selected statuses.');
        return;
    }

    $sent_count  = 0;
    $error_count = 0;

    foreach ($vendors as $v) {
        if (!function_exists('oha_vh_generate_edit_token') || !function_exists('oha_vh_send_edit_link')) {
            $error_count++;
            continue;
        }

        $token = oha_vh_generate_edit_token($v['app_id']);
        $sent  = oha_vh_send_edit_link($v['email'], $token, $edit_page_url, $v['first_name']);

        // wp_mail returns true on success
        if ($sent !== false) {
            $sent_count++;
        } else {
            $error_count++;
        }
    }

    $message = $sent_count . ' edit link' . ($sent_count === 1 ? '' : 's') . ' sent successfully.';
    if ($error_count > 0) {
        $message .= ' ' . $error_count . ' failed — check server email settings.';
    }

    wp_send_json_success(['sent' => $sent_count, 'errors' => $error_count, 'message' => $message]);
});

/**
 * AJAX: Send a test edit-link email to the current admin.
 * Uses the first vendor_application on file to generate a real token so the
 * admin can click through and verify the form works end-to-end.
 * Subject is prefixed with [TEST] so it is easy to identify in the inbox.
 */
add_action('wp_ajax_oha_vh_test_edit_link', function() {
    check_ajax_referer('oha_vh_send_edit_links', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $edit_page_id = (int) get_option('oha_vhcpt_vendor_edit_page_id', 0);
    if (!$edit_page_id) {
        wp_send_json_error('Vendor Edit Page is not configured in settings.');
        return;
    }

    $edit_page_url = trailingslashit(get_permalink($edit_page_id));

    // Find the first vendor application to use as test data
    $apps = get_posts([
        'post_type'      => 'vendor_application',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    if (empty($apps)) {
        wp_send_json_error('No vendor applications exist yet. Create one first so the test link resolves to a real record.');
        return;
    }

    $app_id     = $apps[0]->ID;
    $first_name = get_post_meta($app_id, 'first_name', true) ?: 'Vendor';
    $company    = get_post_meta($app_id, 'company_name', true) ?: 'Test Company';

    $admin_email = wp_get_current_user()->user_email;
    if (!is_email($admin_email)) {
        wp_send_json_error('Your admin user does not have a valid email address.');
        return;
    }

    if (!function_exists('oha_vh_generate_edit_token') || !function_exists('oha_vh_send_edit_link')) {
        wp_send_json_error('Edit link functions not loaded. Make sure vendor-edit.php is included.');
        return;
    }

    $token = oha_vh_generate_edit_token($app_id);
    $link  = add_query_arg('edit_token', $token, $edit_page_url);

    // Build email using the saved template but override subject with [TEST] prefix
    $default_subject = 'Update Your Vendor Information - Ohio Haunters Convention';
    $default_body    = "Hello {first_name},\n\nHere is a link to update your vendor information for the Ohio Haunters Convention.\n\n{link}\n\nThis link is valid for 24 hours. After it expires, you can request a new one by visiting the same page and entering your email address again.\n\nIf you did not request this link, you can safely ignore this email.\n\nQuestions? Contact us at info@ohiohaunters.com\n\n-- The OHA Team";

    $subject = '[TEST] ' . get_option('oha_vhcpt_edit_link_subject', $default_subject);
    $body    = get_option('oha_vhcpt_edit_link_body', $default_body);

    $subject = str_replace(['{first_name}', '{link}'], [$first_name, $link], $subject);
    $body    = str_replace(['{first_name}', '{link}'], [$first_name, $link], $body);

    $body = "[TEST EMAIL — sent to admin, not the vendor]\n"
          . "Vendor record used: #{$app_id} ({$company})\n"
          . "This link is real and will open the edit form for that vendor.\n\n"
          . $body;

    $sent = wp_mail($admin_email, $subject, $body);

    if ($sent) {
        wp_send_json_success(
            'Test email sent to ' . $admin_email . '. '
            . 'It uses vendor record #' . $app_id . ' (' . esc_html($company) . '). '
            . 'Click the link in the email to verify the edit form opens correctly.'
        );
    } else {
        wp_send_json_error('wp_mail() returned false. Check server email settings (FluentSMTP, etc.).');
    }
});

/**
 * Helper: Get all vendor email addresses matching given statuses.
 * Returns array of ['email', 'user_id', 'display_name'].
 * Includes vendors without WP accounts (form submissions with just an email).
 *
 * @param array $statuses Status strings to match against _oha_status meta.
 * @return array
 */
function oha_vh_get_vendor_emails_by_status(array $statuses) {
    global $wpdb;

    if (empty($statuses)) {
        return array();
    }

    $placeholders = implode(',', array_fill(0, count($statuses), '%s'));

    // Fetch all vendor_application posts matching these statuses
    $app_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT p.ID, pm_email.meta_value AS email, pm_fn.meta_value AS first_name,
                pm_ln.meta_value AS last_name, pm_cn.meta_value AS company_name,
                p.post_author
         FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} pm_status ON pm_status.post_id = p.ID AND pm_status.meta_key = '_oha_status'
         LEFT JOIN {$wpdb->postmeta} pm_email   ON pm_email.post_id  = p.ID AND pm_email.meta_key  = 'email'
         LEFT JOIN {$wpdb->postmeta} pm_fn      ON pm_fn.post_id     = p.ID AND pm_fn.meta_key     = 'first_name'
         LEFT JOIN {$wpdb->postmeta} pm_ln      ON pm_ln.post_id     = p.ID AND pm_ln.meta_key     = 'last_name'
         LEFT JOIN {$wpdb->postmeta} pm_cn      ON pm_cn.post_id     = p.ID AND pm_cn.meta_key     = 'company_name'
         WHERE p.post_type   = 'vendor_application'
           AND p.post_status != 'trash'
           AND COALESCE(pm_status.meta_value, 'Submitted') IN ({$placeholders})",
        $statuses
    ));

    if (empty($app_rows)) {
        return array();
    }

    $result    = array();
    $seen      = array();

    foreach ($app_rows as $row) {
        $email = sanitize_email($row->email ?? '');
        if (empty($email) || !is_email($email)) {
            continue;
        }
        if (isset($seen[$email])) {
            continue; // deduplicate
        }
        $seen[$email] = true;

        $display_name = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
        if (empty($display_name)) {
            $display_name = $row->company_name ?: $email;
        }

        // Try to find a matching WP user by email
        $wp_user = get_user_by('email', $email);
        $user_id = $wp_user ? $wp_user->ID : 0;
        if ($wp_user && $wp_user->display_name) {
            $display_name = $wp_user->display_name;
        }

        $result[] = array(
            'email'        => $email,
            'user_id'      => $user_id,
            'display_name' => $display_name,
        );
    }

    return $result;
}
