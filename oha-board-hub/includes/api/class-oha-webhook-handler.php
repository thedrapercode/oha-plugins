<?php
/**
 * Centralized Webhook Handler for ALL Stripe Events
 *
 * ARCHITECTURE:
 * - This is THE SINGLE webhook endpoint for all Stripe events: /wp-json/oha/v1/stripe-webhook
 * - Handles memberships, vendor payments, board payments, products - everything goes through here
 * - Other plugins (Vendor Hub, etc.) hook in via filter: apply_filters('oha_process_webhook_event', false, $event)
 * - If external plugin returns true, Board Hub skips processing (event already handled)
 * - If external plugin returns false, Board Hub processes with its internal handlers
 * 
 * COMPANION PLUGINS:
 * - OHA Vendor Hub v3.0.1+ hooks into this webhook via filter
 * - Does NOT register its own webhook route
 * - Uses filter-based integration for vendor payment events
 * 
 * WEBHOOK FLOW:
 * 1. Stripe sends event → /wp-json/oha/v1/stripe-webhook
 * 2. Board Hub validates signature and checks for duplicates
 * 3. Board Hub calls filter: apply_filters('oha_process_webhook_event', false, $event)
 * 4. External plugins check if they should handle (e.g., Vendor Hub checks for vendor payments)
 * 5. If external plugin handles: marks processed, returns success
 * 6. If no external handler: Board Hub processes with internal handlers
 * 
 * SECURITY:
 * - Signature verification with configurable tolerance
 * - Idempotency tracking (30-day event history)
 * - Proper error handling and logging
 *
 * @package OHA_Board_Hub
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OHA Webhook Handler class
 */
class OHA_Webhook_Handler {
    
    /**
     * Option name for processed events
     */
    const PROCESSED_EVENTS_OPTION = 'oha_processed_webhook_events';
    
    /**
     * Event object
     *
     * @var object
     */
    private $event;
    
    /**
     * Settings
     *
     * @var OHA_Settings
     */
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = OHA_Settings::instance();
    }
    
    /**
     * Register REST routes
     */
    public function register_routes() {
        $endpoint_path = $this->settings->get('stripe.endpoint_path', 'oha/v1/stripe-webhook');
        $path_parts = explode('/', trim($endpoint_path, '/'));
        
        if (count($path_parts) >= 2) {
            $namespace = $path_parts[0] . '/' . $path_parts[1];
            $route = '/' . implode('/', array_slice($path_parts, 2));
            if ($route === '/') {
                $route = '/stripe-webhook';
            }
        } else {
            $namespace = 'oha/v1';
            $route = '/stripe-webhook';
        }
        
        register_rest_route($namespace, $route, [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle'],
                'permission_callback' => '__return_true', // Signature verification handles auth
            ],
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'handle_get'],
                'permission_callback' => function() { return current_user_can('manage_options'); },
            ],
        ]);
    }
    
    /**
     * Handle webhook request
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function handle(WP_REST_Request $request) {
        // Verify signature
        if (!$this->verify_signature($request)) {
            OHA_Logger::error('Webhook signature verification failed', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return new WP_REST_Response([
                'error' => 'invalid_signature'
            ], 400);
        }
        
        // Parse event
        $this->event = json_decode($request->get_body());
        
        // Validate event
        if (!$this->validate_event()) {
            OHA_Logger::error('Invalid webhook event received', [
                'body' => substr($request->get_body(), 0, 200)
            ]);
            return new WP_REST_Response([
                'error' => 'invalid_event'
            ], 400);
        }

        // Log webhook event to internal history (for Board Hub visibility)
        if (class_exists('OHA_Stripe_Webhook_Log')) {
            OHA_Stripe_Webhook_Log::log_event($this->event, 'received');
        }
        
        // Check for duplicate
        if ($this->is_duplicate()) {
            if (class_exists('OHA_Stripe_Webhook_Log')) {
                OHA_Stripe_Webhook_Log::log_event($this->event, 'duplicate');
            }
            OHA_Logger::info('Duplicate webhook event received', [
                'event_id' => $this->event->id
            ]);
            return new WP_REST_Response([
                'status' => 'already_processed'
            ], 200);
        }
        
        // FILTER HOOK: Allow other plugins to process events first
        // This is how Vendor Hub and other companion plugins integrate
        // If a plugin returns true, it has handled the event and we skip Board Hub processing
        $handled_by_external = apply_filters('oha_process_webhook_event', false, $this->event);
        
        if ($handled_by_external === true) {
            $this->mark_processed();
            if (class_exists('OHA_Stripe_Webhook_Log')) {
                OHA_Stripe_Webhook_Log::log_event($this->event, 'processed_external');
            }
            OHA_Logger::info('Webhook event handled by external plugin', [
                'event_id' => $this->event->id,
                'type' => $this->event->type
            ]);
            
            return new WP_REST_Response([
                'status' => 'success',
                'handled_by' => 'external_plugin'
            ], 200);
        }
        
        // Process event with Board Hub's internal handlers
        try {
            $this->process_event();
            $this->mark_processed();

            if (class_exists('OHA_Stripe_Webhook_Log')) {
                OHA_Stripe_Webhook_Log::log_event($this->event, 'processed_board_hub');
            }
            
            OHA_Logger::info('Webhook event processed by Board Hub', [
                'event_id' => $this->event->id,
                'type' => $this->event->type
            ]);
            
            return new WP_REST_Response([
                'status' => 'success',
                'handled_by' => 'board_hub'
            ], 200);
            
        } catch (Exception $e) {
            if (class_exists('OHA_Stripe_Webhook_Log')) {
                OHA_Stripe_Webhook_Log::log_event($this->event, 'processing_failed');
            }
            OHA_Logger::error('Webhook processing failed', [
                'event_id' => $this->event->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Store a transient so an admin notice surfaces on the next WP admin page load.
            set_transient('oha_webhook_failure_alert', [
                'event_id' => $this->event->id ?? 'unknown',
                'type'     => $this->event->type ?? 'unknown',
                'error'    => $e->getMessage(),
                'time'     => gmdate('Y-m-d H:i:s') . ' UTC',
            ], 7 * DAY_IN_SECONDS);

            return new WP_REST_Response([
                'error' => 'processing_failed'
            ], 500);
        }
    }
    
    /**
     * Handle GET request — confirms the endpoint is active (for browser/health-check use)
     *
     * @return WP_REST_Response
     */
    public function handle_get() {
        $secret_configured  = !empty($this->settings->get('stripe.webhook_secret', ''));
        $api_key_configured = !empty($this->settings->get('stripe.secret_key', ''));

        return new WP_REST_Response([
            'status'           => 'active',
            'message'          => 'OHA Board Hub Stripe webhook endpoint is active. Stripe should send POST requests to this URL.',
            'secret_key'       => $api_key_configured  ? 'configured' : 'not configured',
            'webhook_secret'   => $secret_configured   ? 'configured' : 'not configured',
        ], 200);
    }

    /**
     * Verify webhook signature
     *
     * @param WP_REST_Request $request Request object
     * @return bool True if signature is valid
     */
    private function verify_signature(WP_REST_Request $request) {
        $payload = $request->get_body();
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $secret = $this->settings->get('stripe.webhook_secret', '');
        $tolerance = (int) $this->settings->get('stripe.timestamp_tolerance', 300);
        
        if (empty($secret) || empty($sig_header) || empty($payload)) {
            return false;
        }
        
        // Use Stripe minimal lib function if available
        if (function_exists('oha_sm_verify_sig')) {
            return oha_sm_verify_sig($payload, $sig_header, $secret, $tolerance);
        }
        
        // Fallback verification
        return $this->verify_signature_manual($payload, $sig_header, $secret, $tolerance);
    }
    
    /**
     * Manual signature verification
     *
     * @param string $payload Request payload
     * @param string $sig_header Signature header
     * @param string $secret Webhook secret
     * @param int $tolerance Timestamp tolerance in seconds
     * @return bool True if valid
     */
    private function verify_signature_manual($payload, $sig_header, $secret, $tolerance) {
        $parts = explode(',', $sig_header);
        $timestamp = null;
        $signature = null;
        
        foreach ($parts as $part) {
            list($key, $value) = explode('=', $part, 2);
            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1') {
                $signature = $value;
            }
        }
        
        if (null === $timestamp || null === $signature) {
            return false;
        }
        
        // Check timestamp tolerance
        $current_time = time();
        if (abs($current_time - $timestamp) > $tolerance) {
            OHA_Logger::warning('Webhook timestamp outside tolerance', [
                'timestamp' => $timestamp,
                'current_time' => $current_time,
                'difference' => abs($current_time - $timestamp)
            ]);
            return false;
        }
        
        // Verify signature
        $signed_payload = $timestamp . '.' . $payload;
        $expected_signature = hash_hmac('sha256', $signed_payload, $secret);
        
        // Use hash_equals for timing-safe comparison
        return hash_equals($expected_signature, $signature);
    }
    
    /**
     * Validate event structure
     *
     * @return bool True if valid
     */
    private function validate_event() {
        if (!is_object($this->event)) {
            return false;
        }
        
        if (empty($this->event->id) || empty($this->event->type)) {
            return false;
        }
        
        if (!isset($this->event->data) || !isset($this->event->data->object)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if event has already been processed
     *
     * @return bool True if duplicate
     */
    private function is_duplicate() {
        $processed = get_option(self::PROCESSED_EVENTS_OPTION, []);
        return isset($processed[$this->event->id]);
    }
    
    /**
     * Mark event as processed
     */
    private function mark_processed() {
        $processed = get_option(self::PROCESSED_EVENTS_OPTION, []);
        $processed[$this->event->id] = time();
        
        // Keep only last 30 days
        $cutoff = time() - (30 * DAY_IN_SECONDS);
        $processed = array_filter($processed, function($timestamp) use ($cutoff) {
            return $timestamp > $cutoff;
        });
        
        update_option(self::PROCESSED_EVENTS_OPTION, $processed, false);
    }
    
    /**
     * Process webhook event
     *
     * @throws Exception If processing fails
     */
    private function process_event() {
        switch ($this->event->type) {
            case 'checkout.session.completed':
                $this->handle_checkout_completed();
                break;
                
            case 'customer.created':
            case 'customer.updated':
                $this->handle_customer_updated();
                break;
                
            case 'customer.subscription.created':
            case 'customer.subscription.updated':
                $this->handle_subscription_updated();
                break;
                
            case 'customer.subscription.deleted':
                $this->handle_subscription_deleted();
                break;
                
            case 'invoice.payment_succeeded':
                $this->handle_invoice_payment_succeeded();
                break;
                
            default:
                OHA_Logger::debug('Unhandled webhook event type', [
                    'type' => $this->event->type
                ]);
        }
    }
    
    /**
     * Handle checkout completed
     */
    private function handle_checkout_completed() {
        if (!function_exists('oha_sm_save_from_checkout_session')) {
            OHA_Logger::error('Function oha_sm_save_from_checkout_session not found');
            return;
        }

        $session = $this->event->data->object;
        oha_sm_save_from_checkout_session($session);

        // Only promote to OHA member for subscription (membership) checkouts.
        // One-time payments — booths, shirts, awards, sponsors — are mode=payment
        // and carry no subscription ID. Promoting them would corrupt the member list.
        if (empty($session->subscription)) {
            return;
        }

        // Promote user to paid member (UsersWP + role) using the same user mapping
        $user_id = 0;
        if (!empty($session->metadata->wp_user_id)) {
            $user_id = absint($session->metadata->wp_user_id);
        } elseif (!empty($session->client_reference_id)) {
            // Only treat client_reference_id as a WP user ID if it resolves to a real user.
            // Vendor/sponsor checkouts use post IDs here — skip those.
            $candidate = absint($session->client_reference_id);
            if ($candidate && get_userdata($candidate)) {
                $user_id = $candidate;
            }
        }
        if (!$user_id && !empty($session->customer_details->email)) {
            $u = get_user_by('email', sanitize_email($session->customer_details->email));
            if ($u) $user_id = (int) $u->ID;
        }
        if ($user_id) {
            do_action('oha_bhs_membership_set_paid', $user_id, $session, $this->event);
        }
    }
    
    /**
     * Handle customer updated
     */
    private function handle_customer_updated() {
        if (!function_exists('oha_sm_save_from_customer')) {
            OHA_Logger::error('Function oha_sm_save_from_customer not found');
            return;
        }
        
        oha_sm_save_from_customer($this->event->data->object);
    }
    
    /**
     * Handle subscription updated
     */
    private function handle_subscription_updated() {
        $subscription = $this->event->data->object;
        
        // Fetch and save customer data
        if (!empty($subscription->customer)) {
            if (function_exists('oha_sm_stripe_get') && function_exists('oha_sm_save_from_customer')) {
                $customer = oha_sm_stripe_get('/v1/customers/' . urlencode($subscription->customer));
                
                if (!empty($customer) && empty($customer->error)) {
                    oha_sm_save_from_customer($customer);
                }
            }
        }
        
        // Handle cancel at period end
        $cancel_at_period_end = !empty($subscription->cancel_at_period_end);
        $status = $subscription->status ?? '';
        
        if ($cancel_at_period_end && $status === 'active') {
            $this->schedule_downgrade($subscription);
        }
    }
    
    /**
     * Schedule downgrade event
     *
     * @param object $subscription Subscription object
     */
    private function schedule_downgrade($subscription) {
        $period_end = (int) ($subscription->current_period_end ?? 0);
        
        if ($period_end <= 0) {
            OHA_Logger::warning('Cannot schedule downgrade: invalid period end', [
                'subscription_id' => $subscription->id ?? 'unknown'
            ]);
            return;
        }
        
        $sub_id = sanitize_text_field($subscription->id ?? '');
        $customer_id = sanitize_text_field($subscription->customer ?? '');
        
        $payload = [
            'sub_id' => $sub_id,
            'customer_id' => $customer_id
        ];
        
        // Schedule 60 seconds after period end
        wp_schedule_single_event($period_end + 60, 'oha_bhs_downgrade_event', [$payload]);
        
        OHA_Logger::info('Downgrade scheduled', [
            'subscription_id' => $sub_id,
            'scheduled_time' => gmdate('Y-m-d H:i:s', $period_end + 60)
        ]);
    }
    
    /**
     * Handle subscription deleted
     */
    private function handle_subscription_deleted() {
        $subscription = $this->event->data->object;
        $sub_id = sanitize_text_field($subscription->id ?? '');
        
        if (empty($sub_id)) {
            OHA_Logger::warning('Subscription deleted event without ID');
            return;
        }
        
        $this->apply_free_level($sub_id);
    }
    
    /**
     * Handle invoice payment succeeded.
     *
     * Path A — subscription invoice (recurring $10/year renewal):
     *   invoice->subscription is set; look up user by stripe_subscription_id and reconfirm paid status.
     *
     * Path B — manual invoice (board-created, no subscription):
     *   invoice->subscription is empty; check line items for a configured membership product ID.
     *   If found, look up user by stripe_customer_id then email, pull billing address, set
     *   oha_membership_verified=1 (so the canonical query finds them via OR path), and upgrade.
     */
    private function handle_invoice_payment_succeeded() {
        $invoice = $this->event->data->object;

        // ── Path A: subscription invoice ────────────────────────────────────────
        if (!empty($invoice->subscription)) {
            $sub_id = sanitize_text_field($invoice->subscription);
            $users = get_users([
                'meta_key'   => 'stripe_subscription_id',
                'meta_value' => $sub_id,
                'number'     => 1,
                'fields'     => 'ID',
            ]);
            if (empty($users)) {
                OHA_Logger::warning('No user found for subscription', ['sub_id' => $sub_id]);
                return;
            }
            $user_id = (int) $users[0];
            do_action('oha_bhs_membership_set_paid', $user_id, $invoice, $this->event);
            return;
        }

        // ── Path B: manual invoice — check for membership product ────────────────
        if (!function_exists('oha_sm_is_membership_product')) {
            return;
        }

        $is_membership = false;
        if (!empty($invoice->lines->data)) {
            foreach ($invoice->lines->data as $line) {
                if (!empty($line->price->product) && oha_sm_is_membership_product($line->price->product)) {
                    $is_membership = true;
                    break;
                }
            }
        }

        if (!$is_membership) {
            return; // Manual invoice but not a membership product — ignore
        }

        // Find WP user: stripe_customer_id first, email fallback
        $user_id = 0;
        if (!empty($invoice->customer)) {
            $customer_id = sanitize_text_field($invoice->customer);
            $users = get_users([
                'meta_key'   => 'stripe_customer_id',
                'meta_value' => $customer_id,
                'number'     => 1,
                'fields'     => 'ID',
            ]);
            if (!empty($users)) {
                $user_id = (int) $users[0];
            }
        }
        if (!$user_id && !empty($invoice->customer_email)) {
            $u = get_user_by('email', sanitize_email($invoice->customer_email));
            if ($u) $user_id = (int) $u->ID;
        }

        if (!$user_id) {
            OHA_Logger::warning('Manual membership invoice: no WP user found', [
                'invoice'  => $invoice->id ?? '',
                'customer' => $invoice->customer ?? '',
                'email'    => $invoice->customer_email ?? '',
            ]);
            return;
        }

        // Save Stripe customer ID and billing address from the invoice
        if (!empty($invoice->customer)) {
            update_user_meta($user_id, 'stripe_customer_id', sanitize_text_field($invoice->customer));
        }
        if (!empty($invoice->customer_address) && function_exists('oha_sm_save_addr')) {
            oha_sm_save_addr($user_id, $invoice->customer_address, 'billing_');
        }

        // Mark as board-verified so the canonical query finds them (no subscription = no sub ID path)
        update_user_meta($user_id, 'oha_membership_verified', '1');

        // Upgrade: sets _uwp_register_form_id=1, WP role=subscriber, stripe_subscription_status=active
        do_action('oha_bhs_membership_set_paid', $user_id, $invoice, $this->event);

        OHA_Logger::info('Manual invoice membership activated', [
            'user_id'  => $user_id,
            'invoice'  => $invoice->id ?? '',
            'customer' => $invoice->customer ?? '',
        ]);
    }
    
    /**
     * Apply free membership level
     *
     * @param string $sub_id Subscription ID
     */
    private function apply_free_level($sub_id) {
        $users = get_users([
            'meta_key' => 'stripe_subscription_id',
            'meta_value' => $sub_id,
            'number' => 1,
            'fields' => 'ID'
        ]);

        if (empty($users)) {
            OHA_Logger::warning('No user found for subscription to downgrade', ['sub_id' => $sub_id]);
            return;
        }

        $user_id = (int) $users[0];

        // Downgrade: set stripe_subscription_status = cancelled and revert form_id/account_type
        do_action('oha_bhs_membership_set_free', $user_id, $sub_id, $this->event);

        OHA_Logger::info('User subscription cancelled — membership status cleared', [
            'user_id' => $user_id,
            'sub_id'  => $sub_id,
        ]);
    }
}
