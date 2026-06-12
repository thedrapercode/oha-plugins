<?php
if (!defined('ABSPATH')) exit;

/**
 * Final Vendor Application Form with E-Signature
 * 
 * Shortcode: [oha_vendor_final_form]
 */
add_shortcode('oha_vendor_final_form', function($atts){
    // CRITICAL: Start output buffering IMMEDIATELY to allow redirects
    if (!ob_get_level()) {
        ob_start();
    }
    
    // FORCE VISIBLE OUTPUT - Can't be suppressed
    if (!wp_doing_ajax()) {
        echo '<!-- OHA VENDOR FORM START -->';
    }
    
    wp_enqueue_style('oha-vhcpt-final');
    
    // Debug: Confirm shortcode is running
    if (current_user_can('manage_options') && isset($_GET['debug'])) {
        echo '<div style="background:yellow;padding:10px;color:black;border:2px solid red;">DEBUG: Shortcode is executing. PHP Version: ' . PHP_VERSION . '</div>';
    }
    
    $atts = shortcode_atts([
        'stripe_mode' => get_option('oha_vhcpt_stripe_mode', 'test'),
    ], $atts);

    $user = wp_get_current_user();
    $msg = '';
    $errors = [];

    // Agreement-only (no payment) mode.
    // Three ways to detect it (in priority order):
    //   1. _is_pre_paid meta flag set on the application when the board sent the no-payment email
    //      — most secure, works regardless of URL parameters
    //   2. URL/POST no_payment_mode parameter (from emailed link)
    //   3. Shortcode sets it via $_GET before calling this shortcode
    $no_payment_mode = false;
    if ($app_id && get_post_type($app_id) === 'vendor_application') {
        $no_payment_mode = (get_post_meta($app_id, '_is_pre_paid', true) === '1');
    }
    if (!$no_payment_mode) {
        $no_payment_mode = (function_exists('oha_vhcpt_is_no_payment_mode') && oha_vhcpt_is_no_payment_mode());
    }
    
    // Get application ID from URL or session
    $app_id = isset($_GET['app_id']) ? intval($_GET['app_id']) : 0;
    
    // DUPLICATE PREVENTION: Check if this email already has a paid application
    // Only check if NOT editing an existing application (app_id is 0)
    if (!$app_id) {
        $check_email = '';
        
        // Check from URL parameter
        if (isset($_GET['email']) && !empty($_GET['email'])) {
            $check_email = sanitize_email($_GET['email']);
        }
        // Check from logged-in user
        elseif (is_user_logged_in()) {
            $check_email = wp_get_current_user()->user_email;
        }
        
        if (!empty($check_email)) {
            $existing = get_posts([
                'post_type' => 'vendor_application',
                'post_status' => 'any',
                'meta_query' => [
                    [
                        'key' => 'email',
                        'value' => $check_email,
                        'compare' => '='
                    ],
                    [
                        'key' => '_oha_status',
                        'value' => 'Paid',
                        'compare' => '='
                    ]
                ],
                'posts_per_page' => 1
            ]);
            
            if (!empty($existing)) {
                $existing_id = $existing[0]->ID;
                $company = get_post_meta($existing_id, 'company_name', true);
                $booths = get_post_meta($existing_id, 'booths', true);
                $pdf_url = get_post_meta($existing_id, '_pdf_url', true);
                
                $duplicate_msg = '<div style="background:#fffbeb;border:2px solid #f59e0b;padding:25px;color:#92400e;border-radius:8px;margin:20px 0;max-width:600px;">
                    <h2 style="margin-top:0;color:#92400e;">⚠️ Application Already Submitted</h2>
                    <p><strong>You already have a paid vendor application on file for this email address.</strong></p>
                    <div style="background:#fff;padding:15px;border-radius:6px;margin:15px 0;">
                        <p style="margin:5px 0;"><strong>Company:</strong> ' . esc_html($company) . '</p>
                        <p style="margin:5px 0;"><strong>Email:</strong> ' . esc_html($check_email) . '</p>
                        <p style="margin:5px 0;"><strong>Booths:</strong> ' . esc_html($booths) . '</p>
                        <p style="margin:5px 0;"><strong>Status:</strong> <span style="background:#22c55e;color:#fff;padding:3px 10px;border-radius:4px;font-size:12px;">PAID ✅</span></p>
                    </div>';
                
                if (!empty($pdf_url)) {
                    $duplicate_msg .= '<p style="margin-top:20px;"><a href="' . esc_url($pdf_url) . '" target="_blank" style="display:inline-block;background:#065f46;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:bold;">📄 Download Your Agreement</a></p>';
                }
                
                $duplicate_msg .= '<hr style="margin:20px 0;border:none;border-top:1px solid #d97706;">
                    <p style="margin-bottom:0;font-size:14px;"><strong>Need to add more booths or make changes?</strong><br>
                    Please contact us at <a href="mailto:webmaster@ohiohaunters.com" style="color:#92400e;font-weight:bold;">webmaster@ohiohaunters.com</a></p>
                </div>';
                
                return $duplicate_msg;
            }
        }
    }
    
    // Initialize form data
    $form_data = [
        'company_name' => '',
        'company_phone' => '',
        'company_email' => '',
        'company_website' => '',
        'primary_contact' => '',
        'primary_contact_phone' => '',
        'company_social' => '',
        'product_type' => '',
        'has_samples' => '',
        'booths' => 1,
        'need_electric' => 0,
        'need_air' => 0,
        'sponsor_interest' => 0,
        'need_table' => 0,
    ];

    // Pre-populate from existing application if app_id provided
    if ($app_id && get_post_type($app_id) === 'vendor_application') {
        $form_data['company_name'] = get_post_meta($app_id, 'company_name', true);
        $form_data['company_phone'] = get_post_meta($app_id, 'phone', true);
        $form_data['company_email'] = get_post_meta($app_id, 'email', true);
        $form_data['company_website'] = get_post_meta($app_id, 'website', true);
        $form_data['product_type'] = get_post_meta($app_id, 'product_type', true);
        $form_data['booths'] = get_post_meta($app_id, 'booths', true) ?: 1;
        $form_data['sponsor_interest'] = get_post_meta($app_id, 'sponsor', true);
        
        $first = get_post_meta($app_id, 'first_name', true);
        $last = get_post_meta($app_id, 'last_name', true);
        $form_data['primary_contact'] = trim($first . ' ' . $last);
        
        $form_data['company_social'] = get_post_meta($app_id, 'company_social', true);
        $form_data['_company_logo_url'] = get_post_meta($app_id, '_company_logo_url', true);
        $form_data['has_samples'] = get_post_meta($app_id, 'has_samples', true);
        $form_data['need_electric'] = get_post_meta($app_id, 'need_electric', true);
        $form_data['need_air'] = get_post_meta($app_id, 'need_air', true);
        $form_data['need_table'] = get_post_meta($app_id, 'need_table', true);
    }
    
    // Override with URL parameters
    if (isset($_GET['email'])) $form_data['company_email'] = sanitize_email($_GET['email']);
    if (isset($_GET['company'])) $form_data['company_name'] = sanitize_text_field($_GET['company']);
    if (isset($_GET['phone'])) $form_data['company_phone'] = sanitize_text_field($_GET['phone']);
    if (isset($_GET['website'])) $form_data['company_website'] = esc_url_raw($_GET['website']);
    if (isset($_GET['contact'])) $form_data['primary_contact'] = sanitize_text_field($_GET['contact']);
    if (isset($_GET['booths'])) $form_data['booths'] = intval($_GET['booths']);

    // Check for successful payment return
    if (isset($_GET['payment']) && $_GET['payment'] === 'success') {
        // SIMPLE SUCCESS MESSAGE - Webhook will handle the rest
        $success_msg = '<div style="background:#ecfdf5;border:2px solid #34d399;padding:20px;color:#065f46;border-radius:8px;margin:20px 0;max-width:600px;margin-left:auto;margin-right:auto;text-align:center;">
            <h2 style="margin-top:0;">✅ Payment Successful!</h2>
            <p style="font-size:18px;"><strong>Thank you for your payment!</strong></p>
            <p style="font-size:16px;">Your vendor registration for OHA Convention 2026 is being processed.</p>
            <div style="background:#fff;border:2px solid #22c55e;padding:20px;margin:20px 0;border-radius:8px;">
                <p style="font-size:16px;margin:0;"><strong>📧 Check your email!</strong></p>
                <p style="margin:10px 0 0 0;">You will receive your signed vendor agreement and confirmation within the next few minutes.</p>
            </div>
            <p style="font-size:14px;color:#065f46;margin-top:20px;">If you don\'t receive the email within 10 minutes, please contact us at <a href="mailto:webmaster@ohiohaunters.com" style="color:#065f46;font-weight:bold;">webmaster@ohiohaunters.com</a></p>
        </div>';
        
        return $success_msg;
    }

    // Start output buffering HERE (after early returns)
    ob_start();
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['oha_final_app_nonce']) && wp_verify_nonce($_POST['oha_final_app_nonce'], 'oha_final_app_submit')) {
        
        // DUPLICATE SUBMISSION CHECK: Prevent multiple paid applications from same email
        $submission_email = sanitize_email($_POST['company_email'] ?? '');
        if (!empty($submission_email)) {
            $check_existing = get_posts([
                'post_type' => 'vendor_application',
                'post_status' => 'any',
                'meta_query' => [
                    [
                        'key' => 'email',
                        'value' => $submission_email,
                        'compare' => '='
                    ],
                    [
                        'key' => '_oha_status',
                        'value' => 'Paid',
                        'compare' => '='
                    ]
                ],
                'posts_per_page' => 1
            ]);
            
            if (!empty($check_existing)) {
                $errors[] = 'This email address already has a paid vendor application. If you need to make changes, please contact webmaster@ohiohaunters.com';
            }
        }
        
        // Collect and sanitize all form data
        $submitted = [
            'company_name' => sanitize_text_field($_POST['company_name'] ?? ''),
            'company_phone' => sanitize_text_field($_POST['company_phone'] ?? ''),
            'company_email' => sanitize_email($_POST['company_email'] ?? ''),
            'company_website' => esc_url_raw($_POST['company_website'] ?? ''),
            'primary_contact' => sanitize_text_field($_POST['primary_contact'] ?? ''),
            'primary_contact_phone' => sanitize_text_field($_POST['primary_contact_phone'] ?? ''),
            'company_social' => sanitize_text_field($_POST['company_social'] ?? ''),
            'product_type' => sanitize_textarea_field($_POST['product_type'] ?? ''),
            'has_samples' => sanitize_text_field($_POST['has_samples'] ?? ''),
            'booths' => intval($_POST['booths'] ?? 1),
            'need_electric' => isset($_POST['need_electric']) ? 1 : 0,
            'need_air' => isset($_POST['need_air']) ? 1 : 0,
            'sponsor_interest' => isset($_POST['sponsor_interest']) ? 1 : 0,
            'need_table' => isset($_POST['need_table']) ? 1 : 0,
            'signature_data' => wp_unslash($_POST['signature_data'] ?? ''),
            'signature_name' => sanitize_text_field($_POST['signature_name'] ?? ''),
            'agree_terms' => isset($_POST['agree_terms']) ? 1 : 0,
        ];

        // Validate required fields
        if (empty($submitted['company_name'])) $errors[] = 'Company Name is required';
        if (empty($submitted['company_email']) || !is_email($submitted['company_email'])) $errors[] = 'Valid Company Email is required';
        if (empty($submitted['company_phone'])) $errors[] = 'Company Phone Number is required';
        if (empty($submitted['primary_contact'])) $errors[] = 'Primary Contact Person is required';
        if (empty($submitted['product_type'])) $errors[] = 'Type of Product Sold is required';
        if ($submitted['booths'] < 1) $errors[] = 'You must request at least 1 booth';
        
        // Validate signature
        if (empty($submitted['agree_terms'])) {
            $errors[] = 'You must agree to the terms and conditions';
        }
        if (empty($submitted['signature_data']) || $submitted['signature_data'] === 'data:,') {
            $errors[] = 'Electronic signature is required';
        }
        // Validate signature data is a raster image data URL (PNG or JPEG).
        // This blocks SVG and other MIME types that can carry executable content
        // when embedded into the HTML agreement file.
        // Canvas.toDataURL() outputs PNG by default; some libraries may output JPEG.
        // The regex allows optional line breaks in base64 (\r\n) for compatibility.
        if (!empty($submitted['signature_data'])
            && $submitted['signature_data'] !== 'data:,'
            && !preg_match('/^data:image\/(png|jpeg|jpg);base64,[A-Za-z0-9+\/=\r\n]+$/', $submitted['signature_data'])
        ) {
            $errors[] = 'Signature format is invalid. Please redraw your signature.';
            $submitted['signature_data'] = ''; // Clear the invalid data
        }
        if (empty($submitted['signature_name'])) {
            $errors[] = 'Printed name for signature is required';
        }

        if (empty($errors)) {
            // Get or create the vendor_application post
            $post_id = $app_id;
            
            if (!$post_id) {
                $post_id = wp_insert_post([
                    'post_type' => 'vendor_application',
                    'post_status' => 'publish',
                    'post_title' => $submitted['company_name'],
                    'post_author' => get_current_user_id(),
                ], true);
                
                if (is_wp_error($post_id)) {
                    $errors[] = 'Error creating application: ' . $post_id->get_error_message();
                }
            }

            if (!is_wp_error($post_id) && $post_id) {
                // Update all meta fields
                update_post_meta($post_id, 'company_name', $submitted['company_name']);
                update_post_meta($post_id, 'phone', $submitted['company_phone']);
                update_post_meta($post_id, 'email', $submitted['company_email']);
                update_post_meta($post_id, 'website', $submitted['company_website']);
                update_post_meta($post_id, 'product_type', $submitted['product_type']);
                update_post_meta($post_id, 'booths', $submitted['booths']);
                update_post_meta($post_id, 'sponsor', $submitted['sponsor_interest']);
                
                // Store primary contact as first/last name
                $name_parts = explode(' ', $submitted['primary_contact'], 2);
                update_post_meta($post_id, 'first_name', $name_parts[0] ?? '');
                update_post_meta($post_id, 'last_name', $name_parts[1] ?? '');
                
                // Store additional fields
                update_post_meta($post_id, 'company_social', $submitted['company_social']);
                update_post_meta($post_id, 'has_samples', $submitted['has_samples']);
                update_post_meta($post_id, 'primary_contact_phone', $submitted['primary_contact_phone']);
                update_post_meta($post_id, 'need_electric', $submitted['need_electric']);
                update_post_meta($post_id, 'need_air', $submitted['need_air']);
                update_post_meta($post_id, 'need_table', $submitted['need_table']);
                
                // Store signature data
                update_post_meta($post_id, '_signature_data', $submitted['signature_data']);
                update_post_meta($post_id, '_signature_name', $submitted['signature_name']);
                update_post_meta($post_id, '_signature_date', current_time('mysql'));
                update_post_meta($post_id, '_signature_ip', $_SERVER['REMOTE_ADDR'] ?? '');
                update_post_meta($post_id, '_agree_terms', $submitted['agree_terms']);
                
                // Handle logo upload
                if (!empty($_FILES['company_logo']['name'])) {
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    require_once(ABSPATH . 'wp-admin/includes/media.php');
                    
                    $upload_overrides = ['test_form' => false];
                    $uploaded_file = wp_handle_upload($_FILES['company_logo'], $upload_overrides);
                    
                    if (!empty($uploaded_file['file']) && empty($uploaded_file['error'])) {
                        $attachment = [
                            'post_mime_type' => $uploaded_file['type'],
                            'post_title' => sanitize_file_name(pathinfo($uploaded_file['file'], PATHINFO_FILENAME)),
                            'post_content' => '',
                            'post_status' => 'inherit'
                        ];
                        
                        $attach_id = wp_insert_attachment($attachment, $uploaded_file['file'], $post_id);
                        
                        if (!is_wp_error($attach_id)) {
                            $attach_data = wp_generate_attachment_metadata($attach_id, $uploaded_file['file']);
                            wp_update_attachment_metadata($attach_id, $attach_data);
                            update_post_meta($post_id, '_company_logo_id', $attach_id);
                            update_post_meta($post_id, '_company_logo_url', $uploaded_file['url']);
                        }
                    }
                }
                
                // Update status
                update_post_meta($post_id, '_oha_status', 'Agreement Signed');
                
                // Add history entry
                if (function_exists('oha_vhcpt_append_history')) {
                    oha_vhcpt_append_history($post_id, [
                        'type' => 'final_application_signed',
                        'booths' => $submitted['booths'],
                        'signed_by' => $submitted['signature_name'],
                        'signed_at' => current_time('mysql'),
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                        'needs' => [
                            'electric' => $submitted['need_electric'],
                            'air' => $submitted['need_air'],
                            'table' => $submitted['need_table'],
                        ],
                    ]);
                }

                // Calculate total
                $booth_price = 250;
                $total_amount = $submitted['booths'] * $booth_price;

                // DEBUG LOG BEFORE REDIRECT
                error_log('======== OHA FORM SUBMISSION DEBUG ========');
                error_log('App ID: ' . $post_id);
                error_log('Email: ' . $submitted['company_email']);
                error_log('Booths: ' . $submitted['booths']);
                error_log('Stripe Mode: ' . ($atts['stripe_mode'] ?? 'NOT SET'));
                
                // CHECK FOR NO-PAYMENT MODE (Agreement-only / pre-paid vendors)
                if ($no_payment_mode) {
                    error_log('OHA: NO-PAYMENT MODE DETECTED - Skipping Stripe');
                    
                    // Generate and email PDF immediately
                    if (function_exists('oha_vhcpt_email_vendor_pdf')) {
                        $pdf_sent = oha_vhcpt_email_vendor_pdf($post_id);
                        
                        if ($pdf_sent) {
                            update_post_meta($post_id, '_oha_status', 'Paid');
                            update_post_meta($post_id, '_pre_paid', true);
                            
                            if (function_exists('oha_vhcpt_append_history')) {
                                oha_vhcpt_append_history($post_id, [
                                    'type' => 'pre_paid_vendor',
                                    'pdf_generated' => true,
                                    'pdf_emailed' => true,
                                ]);
                            }
                            
                            error_log('OHA: Pre-paid vendor PDF sent successfully');
                        }
                    }
                    
                    // Show success message inline (no redirect / no exit) to avoid blank screens in shortcode context
                    $pdf_url = get_post_meta($post_id, '_pdf_url', true);
                    if (empty($pdf_url)) {
                        $pdf_url = get_post_meta($post_id, '_agreement_url', true);
                    }

                    $success  = '<div class="oha-success-message" style="max-width:650px;margin:40px auto;padding:30px;background:#ecfdf5;border:2px solid #22c55e;border-radius:10px;text-align:center;">';
                    $success .= '<h2 style="color:#065f46;margin:0 0 12px 0;">✅ Agreement Submitted</h2>';
                    $success .= '<p style="font-size:16px;color:#065f46;margin:0 0 18px 0;">Your vendor agreement has been signed successfully. A copy has been emailed to <strong>' . esc_html($submitted['company_email']) . '</strong>.</p>';
                    if (!empty($pdf_url)) {
                        $success .= '<p style="margin:18px 0;"><a href="' . esc_url($pdf_url) . '" target="_blank" style="display:inline-block;background:#065f46;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:700;">📄 View/Download Agreement</a></p>';
                    }
                    $success .= '<div style="background:#fff;padding:18px;border-radius:8px;margin:18px 0;text-align:left;">';
                    $success .= '<p style="margin:0 0 10px 0;"><strong>What happens next:</strong></p>';
                    $success .= '<ul style="margin:0;padding-left:18px;line-height:1.7;">';
                    $success .= '<li>📧 Check your email for your signed agreement</li>';
                    $success .= '<li>📅 Load-in: Friday, May 8, 2026 (4 PM – 9 PM)</li>';
                    $success .= '<li>❓ Questions? Email <a href="mailto:webmaster@ohiohaunters.com">webmaster@ohiohaunters.com</a></li>';
                    $success .= '</ul>';
                    $success .= '</div>';
                    $success .= '</div>';

                    return $success;
                }
                
                // STANDARD MODE - Build Stripe payment link
                error_log('OHA: Standard mode - building Stripe URL');
                $stripe_url = oha_vhcpt_build_stripe_url([
                    'app_id' => $post_id,
                    'booths' => $submitted['booths'],
                    'amount' => $total_amount,
                    'email' => $submitted['company_email'],
                    'company' => $submitted['company_name'],
                    'mode' => $atts['stripe_mode'],
                ]);

                // DEBUG: Log what we're about to do
                if (current_user_can('manage_options')) {
                    error_log('OHA VENDOR FORM: About to redirect to Stripe');
                    error_log('App ID: ' . $post_id);
                    error_log('Email: ' . $submitted['company_email']);
                    error_log('Booths: ' . $submitted['booths']);
                    error_log('Stripe URL: ' . ($stripe_url ? 'Generated' : 'EMPTY'));
                }

                // MULTI-METHOD REDIRECT - One of these WILL work
                if ($stripe_url && !empty($stripe_url)) {
                    
                    // Method 1: Try standard wp_redirect
                    if (!headers_sent()) {
                        // Flush any output buffers before redirect
                        while (ob_get_level()) {
                            ob_end_clean();
                        }
                        wp_redirect($stripe_url);
                        exit;
                    }
                    
                    // Method 2: Headers sent - use INSTANT redirect
                    // Minimal HTML, instant JavaScript redirect
                    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
                    echo '<meta http-equiv="refresh" content="0;url=' . esc_url($stripe_url) . '">';
                    echo '<script>window.location.replace("' . esc_js($stripe_url) . '");</script>';
                    echo '<title>Processing...</title>';
                    echo '<style>body{margin:0;padding:0;background:#fff;}</style>';
                    echo '</head><body></body></html>';
                    exit;
                } else {
                    // Stripe not configured
                    if (current_user_can('manage_options')) {
                        error_log('OHA VENDOR FORM: Stripe URL is empty!');
                        error_log('Test link: ' . get_option('oha_vhcpt_stripe_link_test', 'NOT SET'));
                        error_log('Live link: ' . get_option('oha_vhcpt_stripe_link_live', 'NOT SET'));
                    }
                    
                    $redirect_url = add_query_arg([
                        'app_id' => $post_id,
                        'error' => 'stripe_not_configured'
                    ], get_permalink());
                    
                    // Use same instant redirect for error
                    if (!headers_sent()) {
                        while (ob_get_level()) {
                            ob_end_clean();
                        }
                        wp_redirect($redirect_url);
                        exit;
                    }
                    
                    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
                    echo '<meta http-equiv="refresh" content="0;url=' . esc_url($redirect_url) . '">';
                    echo '<script>window.location.replace("' . esc_js($redirect_url) . '");</script>';
                    echo '</head><body></body></html>';
                    exit;
                }
            }
        }

        // If errors, redirect back to prevent resubmission.
        // Preserve no_payment_mode so the flag is not lost on the retry.
        if (!empty($errors)) {
            $error_param = urlencode(implode('|', $errors));
            $redirect_args = [
                'app_id' => $app_id,
                'form_error' => 'yes',
                'error_msg' => $error_param,
            ];
            if ($no_payment_mode) {
                $redirect_args['no_payment_mode'] = '1';
            }
            $redirect_url = add_query_arg($redirect_args, get_permalink());
            wp_redirect($redirect_url);
            exit;
        }
    }

    // Check for error parameters from redirect
    if (isset($_GET['error']) && $_GET['error'] === 'stripe_not_configured') {
        $stripe_test = get_option('oha_vhcpt_stripe_link_test', '');
        $stripe_live = get_option('oha_vhcpt_stripe_link_live', '');
        
        if (empty($stripe_test) && empty($stripe_live)) {
            $msg .= '<div class="oha-vh-error"><strong>⚠️ Configuration Required:</strong> Stripe payment links are not configured yet. ';
            if (current_user_can('manage_options')) {
                $msg .= 'Please <a href="' . admin_url('edit.php?post_type=vendor_application&page=oha-vhcpt-settings') . '">configure Stripe settings</a> to enable payments.';
            } else {
                $msg .= 'Please contact the site administrator to complete setup.';
            }
            $msg .= '</div>';
        }
    }
    
    // Check for pre-paid success
    if (isset($_GET['pre_paid_success']) && $_GET['pre_paid_success'] === '1') {
        $msg .= '<div class="oha-success-message" style="max-width:600px;margin:40px auto;padding:30px;background:#ecfdf5;border:2px solid #22c55e;border-radius:8px;text-align:center;">';
        $msg .= '<h2 style="color:#065f46;margin:0 0 15px 0;">✅ Application Complete!</h2>';
        $msg .= '<p style="font-size:18px;color:#065f46;margin:0 0 20px 0;">Your vendor registration is confirmed.</p>';
        $msg .= '<div style="background:#fff;padding:20px;border-radius:6px;margin:20px 0;text-align:left;">';
        $msg .= '<p><strong>What happens next:</strong></p>';
        $msg .= '<ul style="line-height:1.8;">';
        $msg .= '<li>📧 Check your email for your signed vendor agreement</li>';
        $msg .= '<li>📄 Your agreement includes all event details</li>';
        $msg .= '<li>💰 Payment: <strong>Already received</strong> - Thank you!</li>';
        $msg .= '<li>📅 Load-in: Friday, May 8, 2026 (4 PM - 9 PM)</li>';
        $msg .= '</ul>';
        $msg .= '</div>';
        $msg .= '<p style="color:#065f46;font-size:14px;">If you don\'t receive the email within 10 minutes, please contact us at <a href="mailto:webmaster@ohiohaunters.com">webmaster@ohiohaunters.com</a></p>';
        $msg .= '</div>';
        return $msg;
    }
    
    if (isset($_GET['form_error']) && isset($_GET['error_msg'])) {
        $errors = explode('|', urldecode($_GET['error_msg']));
    }

    // Display errors
    if (!empty($errors)) {
        $msg = '<div class="oha-vh-error">❌ Please correct the following errors:<ul>';
        foreach ($errors as $err) {
            $msg .= '<li>' . esc_html($err) . '</li>';
        }
        $msg .= '</ul></div>';
    }

    ob_start();
    echo $msg;
    
    // Add visible wrapper to override theme CSS
    echo '<div class="oha-final-form-wrapper" style="background:#ffffff;color:#000000;padding:2rem;border-radius:8px;min-height:400px;">';
    
    ?>
    
    <div class="oha-final-app-intro">
        <h2>Ohio Haunters Convention 2026 - Final Vendor Application</h2>
        <p><strong>Venue:</strong> Mansfield Reformatory, Mansfield Ohio<br>
        <strong>Dates:</strong> May 9-10, 2026<br>
        <strong>Load-in:</strong> Friday May 8th, 4pm-9pm<br>
        <strong>Load-out:</strong> Sunday May 10th, after 3pm close (must complete by 6pm)</p>
    </div>

    <form class="oha-vh-form oha-final-form" method="post" id="oha-final-form" enctype="multipart/form-data">
        <?php wp_nonce_field('oha_final_app_submit', 'oha_final_app_nonce'); ?>
        
        <input type="hidden" name="app_id" value="<?php echo intval($app_id); ?>">
        <?php if ($no_payment_mode): ?>
        <input type="hidden" name="no_payment_mode" value="1">
        <?php endif; ?>
        <input type="hidden" name="signature_data" id="signature_data" value="">

        <h3>Company Information</h3>
        
        <div class="oha-vh-row">
            <label for="company_name">Company Name *</label>
            <input type="text" id="company_name" name="company_name" value="<?php echo esc_attr($form_data['company_name']); ?>" required>
        </div>

        <div class="oha-vh-row">
            <label for="company_phone">Company Phone Number *</label>
            <input type="tel" id="company_phone" name="company_phone" value="<?php echo esc_attr($form_data['company_phone']); ?>" required>
        </div>

        <div class="oha-vh-row">
            <label for="company_email">Company Email *</label>
            <input type="email" id="company_email" name="company_email" value="<?php echo esc_attr($form_data['company_email']); ?>" required>
        </div>

        <div class="oha-vh-row">
            <label for="company_website">Company Website</label>
            <input type="text" id="company_website" name="company_website" value="<?php echo esc_attr($form_data['company_website']); ?>" placeholder="https://...">
        </div>

        <div class="oha-vh-row">
            <label for="company_social">Company Social Media</label>
            <input type="text" id="company_social" name="company_social" value="<?php echo esc_attr($form_data['company_social']); ?>" placeholder="@username or profile URL">
            <small>Facebook, Instagram, Twitter, TikTok, etc.</small>
        </div>

        <div class="oha-vh-row">
            <label for="company_logo">Company Logo (Optional)</label>
            <input type="file" id="company_logo" name="company_logo" accept="image/*">
            <small>Upload your company logo (JPG, PNG, or GIF - max 5MB). This will be used in vendor directory.</small>
            <?php
            // Show existing logo if editing
            if (!empty($form_data['_company_logo_url'])) {
                echo '<div style="margin-top:10px;">';
                echo '<img src="' . esc_url($form_data['_company_logo_url']) . '" style="max-width:200px;max-height:100px;border:1px solid #ddd;padding:5px;" alt="Current Logo">';
                echo '<br><small>Current logo (upload new to replace)</small>';
                echo '</div>';
            }
            ?>
        </div>

        <h3>Contact Information</h3>

        <div class="oha-vh-row">
            <label for="primary_contact">Primary Contact Person *</label>
            <input type="text" id="primary_contact" name="primary_contact" value="<?php echo esc_attr($form_data['primary_contact']); ?>" required>
        </div>

        <div class="oha-vh-row">
            <label for="primary_contact_phone">Primary Contact Person Phone Number</label>
            <input type="tel" id="primary_contact_phone" name="primary_contact_phone" value="<?php echo esc_attr($form_data['primary_contact_phone']); ?>" placeholder="If different from company phone">
        </div>

        <h3>Product Information</h3>

        <div class="oha-vh-row">
            <label for="product_type">Type of Product Sold *</label>
            <textarea id="product_type" name="product_type" rows="4" required><?php echo esc_textarea($form_data['product_type']); ?></textarea>
            <small>Please describe the products you will be selling at your booth</small>
        </div>

        <div class="oha-vh-row">
            <label for="has_samples">Are There Photos/Samples of Your Product on Your Website or Social Media?</label>
            <select id="has_samples" name="has_samples">
                <option value="">Select...</option>
                <option value="Yes - Website" <?php selected($form_data['has_samples'], 'Yes - Website'); ?>>Yes - On Website</option>
                <option value="Yes - Social Media" <?php selected($form_data['has_samples'], 'Yes - Social Media'); ?>>Yes - On Social Media</option>
                <option value="Yes - Both" <?php selected($form_data['has_samples'], 'Yes - Both'); ?>>Yes - Both Website and Social Media</option>
                <option value="No" <?php selected($form_data['has_samples'], 'No'); ?>>No</option>
            </select>
        </div>

        <h3>Booth Requirements</h3>

        <div class="oha-vh-row">
            <label for="booths">How Many Booths Do You Need? *</label>
            <input type="number" id="booths" name="booths" value="<?php echo intval($form_data['booths']); ?>" min="1" max="10" required>
            <small>$250 per booth space</small>
        </div>

        <div class="oha-vh-row">
            <label>
                <input type="checkbox" name="need_electric" value="1" <?php checked($form_data['need_electric'], 1); ?>>
                Will You Need Electric?
            </label>
        </div>

        <div class="oha-vh-row">
            <label>
                <input type="checkbox" name="need_air" value="1" <?php checked($form_data['need_air'], 1); ?>>
                Will You Need Compressed Air?
            </label>
        </div>

        <div class="oha-vh-row">
            <label>
                <input type="checkbox" name="need_table" value="1" <?php checked($form_data['need_table'], 1); ?>>
                Do You Need a Table? (6ft tables provided)
            </label>
        </div>

        <h3>Sponsorship</h3>

        <div class="oha-vh-row">
            <label>
                <input type="checkbox" name="sponsor_interest" value="1" <?php checked($form_data['sponsor_interest'], 1); ?>>
                Are You Interested in Being a Sponsor?
            </label>
            <small>We will contact you with sponsorship opportunities</small>
        </div>

        <!-- VENUE TERMS & CONDITIONS -->
        <div class="oha-terms-section">
            <h3>Venue Rules & Requirements</h3>
            <div class="oha-terms-content">
                <h4>Important Venue Information - Mansfield Reformatory</h4>
                
                <h5>Load-in/Load-out Schedule:</h5>
                <ul>
                    <li><strong>Load-in:</strong> Friday, May 8th, 4:00 PM - 9:00 PM</li>
                    <li><strong>Show Hours:</strong> Saturday May 9th and Sunday May 10th</li>
                    <li><strong>Show Close:</strong> Sunday, May 10th at 3:00 PM</li>
                    <li><strong>Load-out:</strong> Sunday, May 10th after 3:00 PM (must complete by 6:00 PM)</li>
                    <li>Vendors are expected to stay until end of day Sunday</li>
                </ul>

                <h5>Venue Conditions:</h5>
                <ul>
                    <li>Cell blocks are <strong>NOT temperature controlled</strong> - may be chilly or warm depending on weather</li>
                    <li>Possibility of animals (mice, bats, raccoons) in building at night - store food products accordingly</li>
                    <li>Building can be damp, especially in spring - protect paper products and moisture-sensitive items</li>
                    <li>Both cell blocks have overhangs that may limit booth height</li>
                </ul>

                <h5>Setup Restrictions (MUST COMPLY):</h5>
                <ul>
                    <li><strong>NO</strong> screws, nails, or tape allowed (use string/zip ties/free-standing displays)</li>
                    <li><strong>NOTHING</strong> may be attached to sprinkler system in any way</li>
                    <li>Setups must not damage floor or walls</li>
                    <li>All displays must be free-standing or use approved attachment methods</li>
                </ul>

                <h5>Food & Beverage Rules:</h5>
                <ul>
                    <li>NO food or drink consumption on vendor floor (except water and coffee for vendors)</li>
                    <li>Vendors may sell packaged food for consumption outside the building</li>
                    <li>Any food products being sold must be stored properly overnight</li>
                </ul>

                <h5>What's Included with Each Booth:</h5>
                <ul>
                    <li>2 chairs per booth</li>
                    <li>Maximum 4 people permitted to work each booth</li>
                    <li>2 Costume Ball passes per booth</li>
                    <li>Small waste basket per booth</li>
                    <li>6ft tables available upon request</li>
                </ul>

                <h5>Pricing & Refund Policy:</h5>
                <ul>
                    <li><strong>$250.00 per booth space</strong></li>
                    <li><strong class="oha-refund-warning">NO REFUNDS</strong> once payment is accepted, except in extenuating circumstances</li>
                    <li>Additional booths can be purchased at same rate</li>
                </ul>

                <p class="oha-terms-acknowledge">By signing below, I acknowledge that I have read, understood, and agree to comply with all venue rules and requirements listed above. I understand that failure to comply may result in removal from the event without refund.</p>
            </div>
        </div>

        <!-- E-SIGNATURE SECTION -->
        <div class="oha-signature-section">
            <h3>Electronic Signature</h3>
            
            <div class="oha-vh-row">
                <label>
                    <input type="checkbox" name="agree_terms" id="agree_terms" value="1" required>
                    <strong>I agree to all terms and conditions listed above *</strong>
                </label>
            </div>

            <div class="oha-vh-row">
                <label for="signature_name">Printed Name (Full Legal Name) *</label>
                <input type="text" id="signature_name" name="signature_name" value="<?php echo esc_attr($form_data['primary_contact']); ?>" required placeholder="Type your full name exactly as you will sign">
            </div>

            <div class="oha-signature-pad-container">
                <label><strong>Sign Below *</strong></label>
                <p class="oha-signature-instructions">Draw your signature using your mouse or touchscreen. This will serve as your legally binding electronic signature.</p>
                <div class="oha-signature-wrapper">
                    <canvas id="signature-pad" width="600" height="200"></canvas>
                </div>
                <div class="oha-signature-buttons">
                    <button type="button" id="clear-signature" class="oha-clear-btn">Clear Signature</button>
                    <span class="oha-signature-status" id="signature-status">Not Signed</span>
                </div>
            </div>

            <p class="oha-signature-legal"><small>By providing my electronic signature above, I agree that this electronic signature has the same legal effect as a handwritten signature. Date: <?php echo date('F j, Y'); ?></small></p>
        </div>

        <?php if (!$no_payment_mode): ?>
        <div class="oha-booth-summary">
            <h3>Payment Summary</h3>
            <p><strong>Booth Spaces:</strong> <span id="booth-count"><?php echo intval($form_data['booths']); ?></span></p>
            <p><strong>Price per Booth:</strong> $250.00</p>
            <p class="oha-total"><strong>Total Amount:</strong> $<span id="total-amount"><?php echo number_format($form_data['booths'] * 250, 2); ?></span></p>
        </div>
        <?php endif; ?>

        <button type="submit" class="oha-vh-btn oha-final-submit" id="oha-final-submit">
            <?php echo $no_payment_mode ? 'Submit Agreement →' : 'Sign &amp; Proceed to Payment →'; ?>
        </button>
    </form>

    <script>
    (function(){
        // Booth calculation
        var boothInput = document.getElementById('booths');
        var boothCount = document.getElementById('booth-count');
        var totalAmount = document.getElementById('total-amount');
        
        if (boothInput && boothCount && totalAmount) {
            boothInput.addEventListener('input', function(){
                var count = parseInt(this.value) || 1;
                if (count < 1) {
                    count = 1;
                    this.value = 1;
                }
                boothCount.textContent = count;
                totalAmount.textContent = (count * 250).toFixed(2);
            });
        }

        // Signature Pad Implementation
        var canvas = document.getElementById('signature-pad');
        var signatureData = document.getElementById('signature_data');
        var clearBtn = document.getElementById('clear-signature');
        var status = document.getElementById('signature-status');
        var form = document.getElementById('oha-final-form');
        var submitBtn = document.getElementById('oha-final-submit');
        
        if (!canvas) return;
        
        var ctx = canvas.getContext('2d');
        var drawing = false;
        var hasSignature = false;

        // Set canvas background to white (so it exports properly)
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        
        // Set pen properties
        ctx.strokeStyle = '#000000';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        function startDrawing(e) {
            drawing = true;
            var rect = canvas.getBoundingClientRect();
            var x = (e.clientX || e.touches[0].clientX) - rect.left;
            var y = (e.clientY || e.touches[0].clientY) - rect.top;
            ctx.beginPath();
            ctx.moveTo(x, y);
        }

        function draw(e) {
            if (!drawing) return;
            e.preventDefault();
            var rect = canvas.getBoundingClientRect();
            var x = (e.clientX || e.touches[0].clientX) - rect.left;
            var y = (e.clientY || e.touches[0].clientY) - rect.top;
            ctx.lineTo(x, y);
            ctx.stroke();
            hasSignature = true;
            updateStatus();
        }

        function stopDrawing() {
            if (drawing && hasSignature) {
                // Save signature as base64
                signatureData.value = canvas.toDataURL('image/png');
            }
            drawing = false;
        }

        function clearSignature() {
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            signatureData.value = '';
            hasSignature = false;
            updateStatus();
        }

        function updateStatus() {
            if (hasSignature) {
                status.textContent = '✓ Signed';
                status.className = 'oha-signature-status signed';
            } else {
                status.textContent = 'Not Signed';
                status.className = 'oha-signature-status';
            }
        }

        // Mouse events
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);

        // Touch events for mobile
        canvas.addEventListener('touchstart', startDrawing);
        canvas.addEventListener('touchmove', draw);
        canvas.addEventListener('touchend', stopDrawing);

        clearBtn.addEventListener('click', function(e) {
            e.preventDefault();
            clearSignature();
        });

        // Form submission
        var submitted = false;
        form.addEventListener('submit', function(e){
            // Validate signature
            if (!hasSignature || !signatureData.value) {
                e.preventDefault();
                alert('Please sign the agreement before submitting.');
                return false;
            }

            // Prevent double submission
            if (submitted) {
                e.preventDefault();
                return false;
            }
            
            submitted = true;
            submitBtn.disabled = true;
            submitBtn.classList.add('submitting');
            submitBtn.innerHTML = 'Processing... <span class="spinner"></span>';
            
            setTimeout(function(){
                submitted = false;
                submitBtn.disabled = false;
                submitBtn.classList.remove('submitting');
                submitBtn.innerHTML = 'Sign & Proceed to Payment →';
            }, 5000);
        });
    })();
    </script>

    </div><!-- .oha-final-form-wrapper -->
    <?php
    
    $output = ob_get_clean();
    
    // CRITICAL: Emergency fallback if output is empty
    if (empty(trim($output))) {
        $output = '<div style="background:#fff;padding:20px;color:#000;border:3px solid red;">
            <h2>OHA Vendor Application Form</h2>
            <p><strong>ERROR:</strong> Form output was empty. Please contact webmaster@ohiohaunters.com</p>
            <p>Debug Info:</p>
            <ul>
                <li>Shortcode: [oha_vendor_final_form]</li>
                <li>Plugin Version: 1.6.0</li>
                <li>App ID: ' . (isset($_GET['app_id']) ? intval($_GET['app_id']) : 'none') . '</li>
                <li>Time: ' . current_time('mysql') . '</li>
            </ul>
        </div>';
    }
    
    // Return output (don't echo - that causes duplication)
    return $output;
});


/**
 * Build Stripe payment URL
 */
function oha_vhcpt_build_stripe_url($args) {
    $defaults = [
        'app_id' => 0,
        'booths' => 1,
        'amount' => 250,
        'email' => '',
        'company' => '',
        'mode' => 'test',
    ];
    
    $args = wp_parse_args($args, $defaults);

    // Select 1-booth or 2-booth Stripe link based on quantity.
    $base_url = function_exists('oha_vh_get_stripe_payment_link')
        ? oha_vh_get_stripe_payment_link( $args['booths'], $args['mode'] )
        : ( $args['mode'] === 'live'
            ? get_option('oha_vhcpt_stripe_link_live', '')
            : get_option('oha_vhcpt_stripe_link_test', '') );
    
    if (empty($base_url)) {
        if (current_user_can('manage_options')) {
            error_log('OHA ERROR: Base URL is empty for mode: ' . $args['mode']);
        }
        return false;
    }
    
    // Build return URL more reliably
    global $post;
    if ($post) {
        $return_url = get_permalink($post->ID);
    } else {
        $return_url = get_permalink();
    }
    
    // Fallback if permalink is empty
    if (empty($return_url)) {
        $return_url = home_url('/final-vendor-application/');
    }
    
    // Add payment=success to return URL (webhook will handle the rest)
    $return_url = add_query_arg('payment', 'success', $return_url);
    
    $params = [
        'prefilled_email' => $args['email'],
        'client_reference_id' => $args['app_id'],
        'quantity' => $args['booths'], // Pre-fill booth quantity
    ];
    
    $final_url = add_query_arg($params, $base_url);
    
    // Debug final URL
    if (current_user_can('manage_options')) {
        error_log('OHA Final Stripe URL: ' . $final_url);
    }
    
    return $final_url;
}
