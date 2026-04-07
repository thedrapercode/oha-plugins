<?php
/**
 * Stripe Webhook Handler for Payment Completion
 * 
 * WEBHOOK ARCHITECTURE:
 * - Uses centralized webhook: https://ohiohaunters.com/wp-json/oha/v1/stripe-webhook
 * - Handled by Board Hub Suite's OHA_Webhook_Handler class
 * - This file provides hooks into that centralized webhook
 * 
 * Processes payment events to automatically:
 * 1. Find vendor application by email
 * 2. Generate HTML agreement (legacy naming: "PDF")
 * 3. Send email to vendor with download link
 * 4. Update application status
 */

if (!defined('ABSPATH')) exit;

/**
 * Hook into centralized webhook to handle vendor payment events
 * Board Hub's webhook handler will call this via apply_filters
 */
add_filter('oha_process_webhook_event', 'oha_vhcpt_handle_vendor_payment_events', 10, 2);

/**
 * Handle vendor payment events from centralized webhook
 * Called via filter by Board Hub's OHA_Webhook_Handler
 * 
 * @param bool $handled Whether event was already handled
 * @param object $event Stripe event object
 * @return bool Whether this handler processed the event
 */
function oha_vhcpt_handle_vendor_payment_events($handled, $event) {
    // If already handled by another system, skip
    if ($handled) {
        return $handled;
    }
    
    // Only handle vendor payment events
    $vendor_events = ['checkout.session.completed', 'charge.succeeded', 'payment_intent.succeeded'];
    if (!in_array($event->type, $vendor_events)) {
        return false; // Not a vendor payment event
    }
    
    error_log('=== OHA VENDOR HUB: Processing payment event ===');
    error_log('Event type: ' . $event->type);
    
    $charge_data = $event->data->object;
    
    // Get customer email from event data
    $customer_email = '';
    $app_id_from_ref = '';
    
    if ($event->type === 'checkout.session.completed') {
        // Checkout Session carries customer_details and client_reference_id
        $session = $charge_data;
        $customer_email = $session->customer_details->email ?? ($session->customer_email ?? '');
        $app_id_from_ref = $session->client_reference_id ?? '';
    } else {
        // charge/payment_intent objects carry billing details
        if (isset($charge_data->billing_details->email)) {
            $customer_email = $charge_data->billing_details->email;
        } elseif (isset($charge_data->receipt_email)) {
            $customer_email = $charge_data->receipt_email;
        }
    }
    
    error_log('Payment for email: ' . $customer_email);
    
    if (empty($customer_email)) {
        error_log('OHA VENDOR HUB: No email found in event data');
        return false;
    }
    
    // Find vendor application
    $app_id = 0;
    if (!empty($app_id_from_ref) && ctype_digit(strval($app_id_from_ref))) {
        $candidate = intval($app_id_from_ref);
        // Validate the referenced post is actually a vendor_application
        if (get_post_type($candidate) === 'vendor_application') {
            // client_reference_id is the authoritative identifier (set server-side by OHA).
            // We use it unconditionally. Email cross-check is a WARNING ONLY — vendors
            // can and do change their email in the Stripe checkout form, which is fine.
            $stored_email = strtolower(trim(get_post_meta($candidate, 'email', true)));
            $stripe_email = strtolower(trim($customer_email));
            if ($stored_email && $stripe_email && $stored_email !== $stripe_email) {
                error_log('OHA VENDOR HUB: Email mismatch warning for client_reference_id=' . $candidate
                    . ' — Stripe email differs from stored vendor email. Payment will still be processed.');
            }
            $app_id = $candidate;
        }
    }

    // Email-only fallback — but only when client_reference_id was completely absent.
    //
    // CROSS-PRODUCT GUARD: If client_reference_id was present (even as a non-vendor post),
    // this payment belongs to another system — e.g. a Board Hub membership uses the WP user
    // ID or a membership record ID as client_reference_id. If we fell through to the email
    // lookup in that case, we would incorrectly mark the vendor's booth application as Paid
    // just because they share an email with the membership buyer. Return false immediately.
    //
    // If client_reference_id was completely absent, use a status-restricted email fallback:
    // only match apps that are already in "Agreement Signed" state (the vendor has signed
    // the contract and legitimately owes payment). This prevents a stray membership webhook
    // with no reference from marking an application that was never in the payment flow.
    if (!$app_id) {
        if (!empty($app_id_from_ref)) {
            error_log('OHA VENDOR HUB: client_reference_id (' . $app_id_from_ref . ') was present but is not a vendor_application — belongs to another system (e.g. membership). Skipping vendor processing.');
            return false;
        }
        // client_reference_id absent — safe to use email fallback, but restrict to Agreement Signed
        $app_id = oha_vhcpt_find_agreement_signed_app_by_email($customer_email);
    }

    if (!$app_id) {
        error_log('OHA VENDOR HUB: No vendor application found for payment event');
        return false; // Not a vendor payment
    }
    
    error_log('Found vendor application ID: ' . $app_id);
    
    // ---- Smart idempotency check ----
    // Check if already processed: status=Paid AND agreement exists AND was emailed
    $status = get_post_meta($app_id, '_oha_status', true);
    
    // Check for agreement URL (try both keys for compatibility)
    $agreement_url = get_post_meta($app_id, '_agreement_url', true);
    if (empty($agreement_url)) {
        $agreement_url = get_post_meta($app_id, '_pdf_url', true);
    }
    
    $emailed_at = get_post_meta($app_id, '_agreement_emailed_at', true);
    
    // Truth check: All three must exist to consider it fully processed
    if ($status === 'Paid' && !empty($agreement_url) && !empty($emailed_at)) {
        error_log('OHA VENDOR HUB: Already fully processed (Paid + Agreement + Email), skipping');
        return true; // Mark as handled to prevent other systems from processing
    }
    
    // If Paid but missing agreement/email - log and retry
    if ($status === 'Paid' && (empty($agreement_url) || empty($emailed_at))) {
        error_log('OHA VENDOR HUB: Status=Paid but incomplete - retrying generation');
        error_log('Agreement URL: ' . ($agreement_url ? 'exists' : 'MISSING'));
        error_log('Email sent: ' . ($emailed_at ? 'yes' : 'NO'));
        // Continue to generation below
    }
    
    // Processing lock to prevent concurrent webhook deliveries
    $lock_key = 'oha_vhcpt_processing_' . $app_id;
    if (get_transient($lock_key)) {
        error_log('OHA VENDOR HUB: Processing lock active, skipping duplicate');
        return true; // Mark as handled
    }
    set_transient($lock_key, 1, 90);
    
    // Generate HTML agreement and send email
    if (function_exists('oha_vhcpt_email_vendor_pdf')) {
        error_log('Generating HTML agreement for app_id: ' . $app_id);
        
        $pdf_sent = oha_vhcpt_email_vendor_pdf($app_id);
        
        if ($pdf_sent) {
            error_log('HTML agreement generated and emailed successfully');
            
            // Update status to Paid
            update_post_meta($app_id, '_oha_status', 'Paid');
            
            // Store tracking timestamps (NEW - proper truth tracking)
            update_post_meta($app_id, '_agreement_emailed_at', current_time('mysql'));
            
            // Store Stripe identifiers
            if ($event->type === 'checkout.session.completed') {
                $session_obj = $event->data->object;
                update_post_meta($app_id, '_stripe_session_id', $session_obj->id ?? '');
                update_post_meta($app_id, '_stripe_payment_intent', $session_obj->payment_intent ?? '');
            } else {
                $obj = $event->data->object;
                update_post_meta($app_id, '_stripe_charge_id', $obj->id ?? '');
                update_post_meta($app_id, '_stripe_payment_intent', $obj->payment_intent ?? '');
            }
            
            // Add history entry
            if (function_exists('oha_vhcpt_append_history')) {
                oha_vhcpt_append_history($app_id, [
                    'type' => 'payment_completed_webhook',
                    'agreement_generated' => true,
                    'agreement_emailed' => true,
                    'stripe_session' => get_post_meta($app_id, '_stripe_session_id', true),
                ]);
            }
            
            // Release lock
            delete_transient($lock_key);
            
            error_log('OHA VENDOR HUB: Processing complete');
            return true; // Mark as handled
            
        } else {
            error_log('OHA VENDOR HUB: Agreement generation/email failed');
            delete_transient($lock_key);
            return false;
        }
    }
    
    // Release lock
    delete_transient($lock_key);
    return false;
}

/**
 * Display centralized webhook information in admin
 * Points to Board Hub's centralized webhook endpoint
 */
add_action('admin_notices', function() {
    $screen = get_current_screen();
    if ($screen && strpos($screen->id, 'vendor-applications') !== false) {
        $webhook_url = rest_url('oha/v1/stripe-webhook');
        
        echo '<div class="notice notice-info">
            <p><strong>Stripe Webhook URL (Centralized):</strong></p>
            <p><code>' . esc_html($webhook_url) . '</code></p>
            <p><small>This is the centralized webhook managed by Board Hub Suite. All Stripe events (memberships, vendors, board payments) use this single endpoint.</small></p>
        </div>';
    }
});
