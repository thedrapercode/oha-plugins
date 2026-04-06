<?php
/* OHA Stripe Minimal (embedded lib) */

if (!defined('ABSPATH')) exit;

define('OHA_SM_OPT', 'oha_sm_settings');
define('OHA_SM_MENU', 'oha-stripe-minimal');

/** ======================
 * Admin Menus & Settings
 * ====================== */
/* stripped admin_menu block (moved under Suite) */
// VENDOR HUB CONFLICT PROTECTION: Register early and prevent override
add_action('admin_init', function () {
    // BOARD HUB SUITE OWNS THIS REGISTRATION - v2.2.8 with Vendor Hub Conflict Fix
    // Only register if not already registered by Board Hub (prevents duplicate registration)
    global $wp_settings_fields;
    
    $already_registered = isset($wp_settings_fields['oha_sm'][OHA_SM_OPT]) && 
                         isset($wp_settings_fields['oha_sm'][OHA_SM_OPT]['args']['type']) &&
                         $wp_settings_fields['oha_sm'][OHA_SM_OPT]['args']['type'] === 'array';
    
    if (!$already_registered) {
        // Register as an ARRAY option so WordPress will accept oha_sm_settings[...]
        // CRITICAL: 'type' => 'array' is essential for WordPress Settings API to work
        register_setting('oha_sm', OHA_SM_OPT, [
            'capability'        => 'manage_options', // Broad admin access
            'type'              => 'array',          // CRITICAL: Enables oha_sm_settings[field_name] handling
            'sanitize_callback' => 'oha_sm_sanitize_settings',
            'default'           => oha_sm_defaults(),
        ]);
    }
    
    // VENDOR HUB CONFLICT WARNING: Log if Vendor Hub is overriding our registration
    add_action('admin_notices', function() {
        if (is_plugin_active('oha-vendor-hub/oha-vendor-hub.php')) {
            global $wp_settings_fields;
            $current_registration = $wp_settings_fields['oha_sm'][OHA_SM_OPT] ?? null;
            
            if ($current_registration && 
                (!isset($current_registration['args']['type']) || 
                 $current_registration['args']['type'] !== 'array' ||
                 !isset($current_registration['args']['sanitize_callback']))) {
                
                if (current_user_can('manage_options') && isset($_GET['page']) && $_GET['page'] === 'oha-board-hub') {
                    echo '<div class="notice notice-warning"><p>';
                    echo '<strong>⚠️ VENDOR HUB CONFLICT DETECTED:</strong> ';
                    echo 'Vendor Hub plugin is overriding Board Hub\'s Stripe settings registration. ';
                    echo 'This may prevent settings from saving properly. ';
                    echo 'Please deactivate Vendor Hub temporarily when modifying Stripe settings.';
                    echo '</p></div>';
                }
            }
        }
    });
    
}, 1); // Priority 1 - register before other plugins

function oha_sm_defaults() {
    return array(
        'secret_key' => '',
        'webhook_secret' => '',
        'endpoint_path' => 'oha/v1/stripe-webhook', // namespace/route (e.g. oha/v1/stripe-webhook)
        'timestamp_tolerance' => 300,
        'portal_return_url' => home_url('/'),
        'invoice_limit' => 12,
        'price_filter' => '',
        'log' => 1,
        // Checkout config
        'price_signup_20'     => '',
        'price_yearly_10'     => '',
        'membership_level_id' => 0,
        'trial_end_iso'       => '2027-01-01T00:00:00Z',
        'membership_product_ids' => '',
    );
}

function oha_sm_sanitize_settings($input) {
    // Always preserve existing settings so a bad sanitize run cannot wipe data.
    $existing = get_option(OHA_SM_OPT, array());
    if (!is_array($existing)) { $existing = array(); }

    // Ensure array
    if (!is_array($input)) {
        return array_merge(oha_sm_defaults(), $existing);
    }

    $defaults = oha_sm_defaults();

    // Explicit allow-list (defaults) + expandable prefixes for future fields
    $allowed_keys = array_keys($defaults);
    $allowed_prefixes = array('price_', 'product_', 'coupon_', 'link_');

    // Start with defaults merged with existing, then overwrite with sanitized submitted values.
    $out = array_merge($defaults, $existing);

    foreach ($input as $key => $val) {
        if (!is_string($key)) { continue; }
        $key = trim($key);
        if ($key === '') { continue; }

        $is_allowed = in_array($key, $allowed_keys, true);
        if (!$is_allowed) {
            foreach ($allowed_prefixes as $prefix) {
                if (strpos($key, $prefix) === 0) { $is_allowed = true; break; }
            }
        }
        if (!$is_allowed) { continue; }

        switch ($key) {
            case 'timestamp_tolerance':
            case 'invoice_limit':
            case 'membership_level_id':
                $out[$key] = intval($val);
                break;

            case 'log':
                $out[$key] = empty($val) ? 0 : 1;
                break;

            case 'portal_return_url':
                $out[$key] = esc_url_raw(is_scalar($val) ? (string)$val : '');
                break;

            default:
                if (is_array($val)) {
                    $out[$key] = array_map('sanitize_text_field', $val);
                } else {
                    $out[$key] = is_scalar($val) ? sanitize_text_field((string)$val) : '';
                }
                break;
        }
    }

    return $out;
}


function oha_sm_get() {
    $opt = get_option(OHA_SM_OPT, array());
    if (!is_array($opt)) $opt = array();
    return array_merge(oha_sm_defaults(), $opt);
}

/** Admin notices */
function oha_sm_admin_note($m){
    $a = get_transient('oha_sm_admin_msgs'); if(!is_array($a)) $a = array();
    $a[] = '['.current_time('mysql').'] '.$m;
    set_transient('oha_sm_admin_msgs',$a, 60*30);
}
add_action('admin_notices', function(){
    if (!current_user_can('manage_options')) return;
    $msgs = get_transient('oha_sm_admin_msgs'); if (empty($msgs)) return;
    echo '<div class="notice notice-info"><p><strong>OHA Stripe:</strong></p><ul>';
    foreach ($msgs as $m) echo '<li>'.esc_html($m).'</li>';
    echo '</ul></div>';
});

/** Settings page */
function oha_sm_settings_page(){
    if(!current_user_can('manage_options')) return; $s = oha_sm_get();
    $path = trim($s['endpoint_path'],'/'); $p = explode('/',$path);
    if(count($p)>=2){ $ns=$p[0].'/'.$p[1]; $rt='/'.implode('/',array_slice($p,2)); if($rt==='/') $rt='/stripe-webhook'; }
    else { $ns='oha/v1'; $rt='/stripe-webhook'; }
    $wh = esc_url_raw(rest_url($ns.$rt));
    ?>
    <div class="wrap">
        <h1>OHA Stripe (Minimal)</h1>
        <form method="post" action="options.php"><?php settings_fields('oha_sm'); ?>
            <table class="form-table" role="presentation">
                <tr><th>Stripe Secret Key</th><td><input type="password" name="<?php echo esc_attr(OHA_SM_OPT); ?>[secret_key]" value="<?php echo esc_attr($s['secret_key']); ?>" class="regular-text"></td></tr>
                <tr><th>Webhook Signing Secret</th><td><input type="password" name="<?php echo esc_attr(OHA_SM_OPT); ?>[webhook_secret]" value="<?php echo esc_attr($s['webhook_secret']); ?>" class="regular-text"></td></tr>
                <tr><th>Webhook Endpoint Path</th><td><input type="text" name="<?php echo esc_attr(OHA_SM_OPT); ?>[endpoint_path]" value="<?php echo esc_attr($s['endpoint_path']); ?>" class="regular-text"><p class="description">URL: <code><?php echo esc_html($wh); ?></code></p></td></tr>
                <tr><th>Signature Timestamp Tolerance (sec)</th><td><input type="number" name="<?php echo esc_attr(OHA_SM_OPT); ?>[timestamp_tolerance]" value="<?php echo esc_attr($s['timestamp_tolerance']); ?>" class="small-text"></td></tr>
                <tr><th>Billing Portal Return URL</th><td><input type="url" name="<?php echo esc_attr(OHA_SM_OPT); ?>[portal_return_url]" value="<?php echo esc_attr($s['portal_return_url']); ?>" class="regular-text"></td></tr>
                <tr><th>Invoice Limit</th><td><input type="number" name="<?php echo esc_attr(OHA_SM_OPT); ?>[invoice_limit]" value="<?php echo esc_attr($s['invoice_limit']); ?>" class="small-text"></td></tr>
                <tr><th>Enable Error Logging</th><td><label><input type="checkbox" name="<?php echo esc_attr(OHA_SM_OPT); ?>[log]" value="1" <?php checked(!empty($s['log'])); ?>> Show admin notices & error_log</label></td></tr>

                <tr><th colspan="2"><h2>Membership Checkout</h2></th></tr>
                <tr><th>Membership Card Fee (One-Time $20)</th><td><input type="text" name="<?php echo esc_attr(OHA_SM_OPT); ?>[price_signup_20]" value="<?php echo esc_attr($s['price_signup_20']); ?>" class="regular-text"><p class="description">Stripe Price ID for $20 one-time fee (card creation & mailing). Example: price_xxxxxxxxxxxxx</p></td></tr>
                <tr><th>Annual Membership Fee ($10/year)</th><td><input type="text" name="<?php echo esc_attr(OHA_SM_OPT); ?>[price_yearly_10]" value="<?php echo esc_attr($s['price_yearly_10']); ?>" class="regular-text"><p class="description">Stripe Price ID for $10/year recurring charge. Example: price_xxxxxxxxxxxxx</p></td></tr>
                <tr><th>SWPM Membership Level ID</th><td><input type="number" name="<?php echo esc_attr(OHA_SM_OPT); ?>[membership_level_id]" value="<?php echo intval($s['membership_level_id']); ?>" class="small-text"></td></tr>
                <tr><th>Annual Renewal Start Date</th><td><input type="text" name="<?php echo esc_attr(OHA_SM_OPT); ?>[trial_end_iso]" value="<?php echo esc_attr($s['trial_end_iso']); ?>" class="regular-text"><p class="description">When annual $10 charges begin (e.g. 2027-01-01T00:00:00Z for Jan 1, 2027). Members pay $20 immediately, then $10/year starting on this date.</p></td></tr>
                <tr><th>Membership Product IDs</th><td><input type="text" name="<?php echo esc_attr(OHA_SM_OPT); ?>[membership_product_ids]" value="<?php echo esc_attr($s['membership_product_ids'] ?? ''); ?>" class="regular-text"><p class="description">Comma-separated list of Stripe Product IDs that should trigger OHA membership activation when paid via invoice. Example: prod_xxxxx, prod_yyyyy</p></td></tr>
            </table>
            <?php submit_button(); ?>
        </form>

        <h2>Shortcodes</h2>
        <ul>
            <li><code>[oha_membership_checkout]</code> — Membership signup: $20 card fee + $10/year starting on renewal date above</li>
            <li><code>[oha_stripe_billing_portal]</code> — Customer Portal button (manage subscription, payment methods)</li>
            <li><code>[oha_stripe_invoices]</code> — Lists recent invoices for the logged-in user</li>
            <li><code>[oha_stripe_address prefer="shipping|billing"]</code> — Shows saved address.</li>
        </ul>
    </div>
    <?php
}

/** Tools */
function oha_sm_tools_page(){
    if(!current_user_can('manage_options')) return;
    if(isset($_POST['oha_sm_backfill_swpm']) && check_admin_referer('oha_sm_backfill_swpm')){ $n=oha_sm_backfill_all_users_to_swpm(); oha_sm_admin_note('SWPM Backfill complete. Updated rows: '.intval($n)); }
    if(isset($_POST['oha_sm_backfill_bh']) && check_admin_referer('oha_sm_backfill_bh')){ $n=oha_sm_backfill_all_users_to_boardhub(); oha_sm_admin_note('Board Hub Backfill complete. Users updated: '.intval($n)); }
    ?>
    <div class="wrap"><h1>OHA Stripe Tools</h1>
        <form method="post"><?php wp_nonce_field('oha_sm_backfill_swpm'); ?><p><button class="button button-primary" name="oha_sm_backfill_swpm" value="1">Backfill Addresses into SWPM</button></p></form>
        <form method="post"><?php wp_nonce_field('oha_sm_backfill_bh'); ?><p><button class="button" name="oha_sm_backfill_bh" value="1">Backfill Board Hub Fields</button></p></form>
    </div>
    <?php
}
function oha_sm_backfill_all_users_to_swpm(){ $q=new WP_User_Query(array('meta_key'=>'billing_address_line1','meta_compare'=>'EXISTS','number'=>-1,'fields'=>'ID')); $ids=$q->get_results(); if(!$ids) return 0; $c=0; foreach($ids as $id){ if(oha_sm_sync_to_swpm($id)) $c++; } return $c; }
function oha_sm_backfill_all_users_to_boardhub(){ $q=new WP_User_Query(array('meta_key'=>'billing_address_line1','meta_compare'=>'EXISTS','number'=>-1,'fields'=>'ID')); $ids=$q->get_results(); if(!$ids) return 0; $c=0; foreach($ids as $id){ oha_sm_push_to_board_hub_fields($id,'billing_'); $c++; } return $c; }

/** Debug */
function oha_sm_debug_page(){
    if(!current_user_can('manage_options')) return; ?>
    <div class="wrap"><h1>OHA Stripe Debug</h1><p>Use Tools to backfill. Errors surface as admin notices and in PHP error_log.</p></div>
<?php }

/** Logging */
function oha_sm_log($m){ $s=oha_sm_get(); if(empty($s['log'])) return; if(!is_string($m)) $m=print_r($m,true); error_log('[OHA Stripe Minimal] '.$m); }

/** ===============
 * REST Webhook
 * =============== */
/* stripped rest_api_init block (moved under Suite) */
function oha_sm_handle_webhook(WP_REST_Request $req){
    $s=oha_sm_get(); $payload=$req->get_body(); $sig=$_SERVER['HTTP_STRIPE_SIGNATURE']??''; $secret=$s['webhook_secret']; $tol=intval($s['timestamp_tolerance']);
    if(!$secret||!$sig||!$payload){ oha_sm_admin_note('Webhook: missing secret/signature/payload'); oha_sm_log('Missing webhook parts'); return new WP_REST_Response(array('error'=>'missing'),400); }
    if(!oha_sm_verify_sig($payload,$sig,$secret,$tol)){ oha_sm_admin_note('Webhook: signature verification failed'); oha_sm_log('Signature verification failed'); return new WP_REST_Response(array('error'=>'sig_failed'),400); }
    $event=json_decode($payload); if(!$event||empty($event->type)) return new WP_REST_Response(array('error'=>'bad_json'),400);

    try{
        switch($event->type){
            case 'checkout.session.completed':
                oha_sm_save_from_checkout_session($event->data->object??null); break;
            case 'customer.created':
            case 'customer.updated':
                oha_sm_save_from_customer($event->data->object??null); break;
            case 'customer.subscription.created':
            case 'customer.subscription.updated':
                $sub=$event->data->object??null;
                if(!empty($sub->customer)){
                    $customer=oha_sm_stripe_get('/v1/customers/'.urlencode($sub->customer));
                    if(!empty($customer)&&empty($customer->error)) oha_sm_save_from_customer($customer);
                }
                break;
            case 'invoice.payment_succeeded':
                $inv=$event->data->object??null; $cfg=oha_sm_get();
                
                // Handle subscription invoices (existing logic)
                if(!empty($inv->subscription)){
                    $sub_id=sanitize_text_field($inv->subscription);
                    $users=get_users(array('meta_key'=>'stripe_subscription_id','meta_value'=>$sub_id,'number'=>1,'fields'=>'ID'));
                    if(!empty($users) && !empty($cfg['membership_level_id'])) oha_sm_set_swpm_membership_active((int)$users[0], (int)$cfg['membership_level_id']);
                }
                
                // Handle one-time membership product invoices (new logic)
                if(!empty($inv->customer) && !empty($inv->lines) && !empty($inv->lines->data)){
                    $customer_id = sanitize_text_field($inv->customer);
                    
                    // Check if invoice contains membership products
                    $is_membership_invoice = false;
                    foreach($inv->lines->data as $line){
                        if(!empty($line->price) && !empty($line->price->product)){
                            $product_id = $line->price->product;
                            // Check if this is a membership product (you can configure specific product IDs)
                            if(oha_sm_is_membership_product($product_id)){
                                $is_membership_invoice = true;
                                break;
                            }
                        }
                    }
                    
                    if($is_membership_invoice){
                        $customer_id = sanitize_text_field($inv->customer);
                        $user_found = false;
                        $user_id = 0;
                        $match_method = '';
                        
                        // Method 1: Find user by Stripe customer ID
                        $users = get_users(array(
                            'meta_key' => 'stripe_customer_id',
                            'meta_value' => $customer_id,
                            'number' => 1,
                            'fields' => 'ID'
                        ));
                        
                        if(!empty($users)){
                            $user_id = (int)$users[0];
                            $user_found = true;
                            $match_method = 'stripe_customer_id';
                        }
                        
                        // Method 2: Try email matching if customer ID failed
                        if(!$user_found && !empty($inv->customer_email)){
                            $customer_email = sanitize_email($inv->customer_email);
                            $user = get_user_by('email', $customer_email);
                            if($user && $user->ID){
                                $user_id = (int)$user->ID;
                                $user_found = true;
                                $match_method = 'email_match';
                                
                                // Update their Stripe customer ID for future matching
                                update_user_meta($user_id, 'stripe_customer_id', $customer_id);
                            }
                        }
                        
                        if($user_found){
                            // Mark user as OHA member via multiple methods for compatibility
                            update_user_meta($user_id, 'uwp_member_type', 'oha_member');
                            update_user_meta($user_id, 'uwp_account_type', 'oha_member'); // UsersWP core field
                            update_user_meta($user_id, '_uwp_register_form_id', '1'); // Paid member form ID
                            update_user_meta($user_id, 'oha_stripe_member_from_invoice', '1');
                            update_user_meta($user_id, 'oha_membership_paid', '1');
                            update_user_meta($user_id, 'oha_membership_paid_date', current_time('mysql'));
                            update_user_meta($user_id, 'oha_stripe_invoice_id', $inv->id);
                            
                            oha_sm_log("Invoice membership payment: User {$user_id} marked as OHA member via invoice {$inv->id} (matched by {$match_method})");
                        } else {
                            // Try creating a WordPress account for unmatched invoice
                            $new_user_id = oha_sm_create_user_from_invoice($inv, $customer_id);
                            if($new_user_id) {
                                $user_found = true;
                                $user_id = $new_user_id;
                                $match_method = 'auto_created';
                                
                                // Mark new user as OHA member
                                update_user_meta($user_id, 'uwp_member_type', 'oha_member');
                                update_user_meta($user_id, 'uwp_account_type', 'oha_member'); // UsersWP core field
                                update_user_meta($user_id, '_uwp_register_form_id', '1'); // Paid member form ID
                                update_user_meta($user_id, 'oha_stripe_member_from_invoice', '1');
                                update_user_meta($user_id, 'oha_membership_paid', '1');
                                update_user_meta($user_id, 'oha_membership_paid_date', current_time('mysql'));
                                update_user_meta($user_id, 'oha_stripe_invoice_id', $inv->id);
                                update_user_meta($user_id, 'stripe_customer_id', $customer_id);
                                
                                oha_sm_log("Created new user {$user_id} from invoice {$inv->id} and activated membership");
                            } else {
                                // Store unmatched membership payment for admin review
                                oha_sm_store_unmatched_membership_payment($inv, $customer_id);
                                oha_sm_log("Failed to create user from invoice {$inv->id} - stored as unmatched payment");
                            }
                        }
                    }
                    
                    // Handle sponsor payments (integrated with vendor management)
                    if (function_exists('oha_vendor_get_sponsor_settings')) {
                        oha_sm_handle_sponsor_payment($inv, $event);
                    }
                }
                break;
            case 'customer.subscription.deleted':
                $sub=$event->data->object??null; if(!empty($sub->id)){
                    $sub_id=sanitize_text_field($sub->id);
                    $users=get_users(array('meta_key'=>'stripe_subscription_id','meta_value'=>$sub_id,'number'=>1,'fields'=>'ID'));
                    if(!empty($users)){
                        $uid=(int)$users[0]; global $wpdb; $table=$wpdb->prefix.'swpm_members_tbl'; $u=get_userdata($uid);
                        $cols=$wpdb->get_results("DESCRIBE {$table}", ARRAY_A); $names=wp_list_pluck($cols,'Field');
                        if(in_array('wp_user_id',$names,true)) $wpdb->query($wpdb->prepare("UPDATE {$table} SET account_state=%s WHERE wp_user_id=%d",'inactive',$uid));
                        else $wpdb->query($wpdb->prepare("UPDATE {$table} SET account_state=%s WHERE email=%s",'inactive',$u->user_email));
                    }
                }
                break;
        }
    }catch(Throwable $e){ oha_sm_admin_note('Webhook exception: '.$e->getMessage()); oha_sm_log('Webhook exception: '.$e->getMessage()); return new WP_REST_Response(array('error'=>'server'),500); }

    return new WP_REST_Response(array('received'=>true),200);
}
/**
 * Verify Stripe webhook signature
 * 
 * CRITICAL: Supports multiple v1 signatures (important during secret rotation)
 * Stripe may send multiple v1= values when rotating secrets
 * Accept if ANY signature matches
 * 
 * @param string $payload Raw webhook payload
 * @param string $sig Stripe-Signature header value
 * @param string $secret Webhook signing secret
 * @param int $tol Timestamp tolerance in seconds (default 300)
 * @return bool True if signature valid, false otherwise
 */
function oha_sm_verify_sig($payload, $sig, $secret, $tol = 300){
    $parts = array_map('trim', explode(',', (string)$sig));
    $ts = null;
    $v1s = [];
    
    // Parse signature header components
    foreach ($parts as $p) {
        if (strpos($p, 't=') === 0) {
            $ts = substr($p, 2);
        } elseif (strpos($p, 'v1=') === 0) {
            $v1s[] = substr($p, 3);
        }
    }
    
    // Must have timestamp and at least one signature
    if (!$ts || empty($v1s)) return false;
    
    // Verify timestamp tolerance (prevent replay attacks)
    if (abs(time() - (int)$ts) > (int)$tol) return false;
    
    // Compute expected signature
    $signed = $ts . '.' . $payload;
    $calc = hash_hmac('sha256', $signed, $secret);
    
    // Accept if ANY v1 signature matches (important during secret rotation)
    foreach ($v1s as $v1) {
        if (hash_equals($calc, $v1)) return true;
    }
    
    return false;
}

/** ==================
 * Save helpers
 * ================== */
function oha_sm_save_from_checkout_session($session){
    if(!$session) return; $wp_user_id=0;
    if(!empty($session->metadata->wp_user_id)) $wp_user_id=absint($session->metadata->wp_user_id);
    elseif(!empty($session->client_reference_id)) $wp_user_id=absint($session->client_reference_id);
    elseif(!empty($session->customer_details->email)){ $u=get_user_by('email',sanitize_email($session->customer_details->email)); if($u) $wp_user_id=(int)$u->ID; }
    if(!$wp_user_id) return;

    if(!empty($session->customer)) update_user_meta($wp_user_id,'stripe_customer_id',sanitize_text_field($session->customer));
    if(!empty($session->customer_details->address)) oha_sm_save_addr($wp_user_id,$session->customer_details->address,'billing_');
    if(!empty($session->shipping_details->address)) oha_sm_save_addr($wp_user_id,$session->shipping_details->address,'shipping_');
    if(!empty($session->subscription)) update_user_meta($wp_user_id,'stripe_subscription_id',sanitize_text_field($session->subscription));

    $s=oha_sm_get(); $level_id=!empty($s['membership_level_id'])?(int)$s['membership_level_id']:0;
    if($level_id>0) oha_sm_set_swpm_membership_active($wp_user_id,$level_id);
    if(!empty($s['trial_end_iso'])) update_user_meta($wp_user_id,'membership_trial_end',sanitize_text_field($s['trial_end_iso']));
}
function oha_sm_save_from_customer($cust){
    if(!$cust) return; $wp_user_id=0;
    if(!empty($cust->metadata->wp_user_id)) $wp_user_id=absint($cust->metadata->wp_user_id);
    elseif(!empty($cust->email)){ $u=get_user_by('email',sanitize_email($cust->email)); if($u) $wp_user_id=(int)$u->ID; }
    if(!$wp_user_id) return;

    if(!empty($cust->id)) update_user_meta($wp_user_id,'stripe_customer_id',sanitize_text_field($cust->id));
    if(!empty($cust->address)) oha_sm_save_addr($wp_user_id,$cust->address,'billing_');
}
function oha_sm_save_addr($user_id,$addr,$prefix='billing_'){
    $line1=isset($addr->line1)?sanitize_text_field($addr->line1):'';
    $line2=isset($addr->line2)?sanitize_text_field($addr->line2):'';
    $city=isset($addr->city)?sanitize_text_field($addr->city):'';
    $state=isset($addr->state)?sanitize_text_field($addr->state):'';
    $postal=isset($addr->postal_code)?sanitize_text_field($addr->postal_code):'';
    $country=isset($addr->country)?sanitize_text_field($addr->country):'';
    update_user_meta($user_id,$prefix.'address_line1',$line1);
    update_user_meta($user_id,$prefix.'address_line2',$line2);
    update_user_meta($user_id,$prefix.'city',$city);
    update_user_meta($user_id,$prefix.'state',$state);
    update_user_meta($user_id,$prefix.'postal_code',$postal);
    update_user_meta($user_id,$prefix.'country',$country);
    $display=trim(implode(', ',array_filter([trim($line1.' '.$line2),$city,$state,$postal,$country])));
    update_user_meta($user_id,$prefix.'address_display',$display);
    if($prefix==='billing_') oha_sm_push_to_board_hub_fields($user_id,$prefix);
    oha_sm_sync_to_swpm($user_id);
}

/** ==================
 * Stripe REST helpers
 * ================== */
function oha_sm_secret(){ $s=oha_sm_get(); return trim($s['secret_key']); }
function oha_sm_stripe_get($path,$params=array()){
    $key=oha_sm_secret(); if(!$key) return (object)['error'=>'no_key'];
    $url='https://api.stripe.com'.$path; if(!empty($params)) $url.='?'.http_build_query($params);
    $res=wp_remote_get($url,['headers'=>['Authorization'=>'Bearer '.$key],'timeout'=>20]);
    if(is_wp_error($res)) return (object)['error'=>$res->get_error_message()];
    return json_decode(wp_remote_retrieve_body($res));
}
function oha_sm_stripe_post($path,$params=array()){
    $key=oha_sm_secret(); if(!$key) return (object)['error'=>'no_key'];
    $url='https://api.stripe.com'.$path;
    $res=wp_remote_post($url,['headers'=>['Authorization'=>'Bearer '.$key,'Content-Type'=>'application/x-www-form-urlencoded'],'body'=>$params,'timeout'=>20]);
    if(is_wp_error($res)) return (object)['error'=>$res->get_error_message()];
    return json_decode(wp_remote_retrieve_body($res));
}

/** ==================
 * Shortcodes & Routes
 * ================== */
// Customer Portal
add_shortcode('oha_stripe_billing_portal', function($atts){
    $a=shortcode_atts(['label'=>'Manage / Cancel Subscription','class'=>'button button-primary'],$atts,'oha_stripe_billing_portal');
    if(!is_user_logged_in()) return '<p><a class="'.esc_attr($a['class']).'" href="'.esc_url(wp_login_url(get_permalink())).'">Log in to manage billing</a></p>';
    $action=esc_url(add_query_arg('oha_sm_portal','1',home_url('/')));
    return '<p><a class="'.esc_attr($a['class']).'" href="'.esc_url($action).'">'.esc_html($a['label']).'</a></p>';
});
add_action('template_redirect', function(){
    if(!isset($_GET['oha_sm_portal'])) return; if(!is_user_logged_in()){ auth_redirect(); exit; }
    if(!oha_sm_secret()) wp_die('Stripe Secret not configured.');
    $uid=get_current_user_id(); $customer=get_user_meta($uid,'stripe_customer_id',true);
    if(empty($customer)){ $u=get_user_by('id',$uid); if($u&&$u->user_email){ $resp=oha_sm_stripe_get('/v1/customers',['email'=>$u->user_email,'limit'=>5]); if(!empty($resp->data)&&is_array($resp->data)){ $chosen=null; foreach($resp->data as $c){ if(!$chosen||($c->created??0)>($chosen->created??0)) $chosen=$c; } if(!empty($chosen->id)){ $customer=sanitize_text_field($chosen->id); update_user_meta($uid,'stripe_customer_id',$customer); } } } }
    if(empty($customer)) wp_die('No Stripe customer for this user.');
    $s=oha_sm_get(); $return=$s['portal_return_url']?:home_url('/');
    $sess=oha_sm_stripe_post('/v1/billing_portal/sessions',['customer'=>$customer,'return_url'=>$return]);
    if(empty($sess->url)){ oha_sm_log('Portal error: '.json_encode($sess)); wp_die('Could not open Billing Portal.'); }
    wp_redirect(esc_url_raw($sess->url)); exit;
});

// Invoices
add_shortcode('oha_stripe_invoices', function($atts){
    if(!is_user_logged_in()) return '<p>Please log in.</p>';
    $s=oha_sm_get(); $uid=get_current_user_id(); $cid=get_user_meta($uid,'stripe_customer_id',true);
    if(!$cid) return '<p>No Stripe payment history found for this account.</p>';
    if(!oha_sm_secret()) return '<p>Stripe not configured.</p>';
    $limit=max(1,intval($s['invoice_limit']));
    $inv=oha_sm_stripe_get('/v1/invoices',['customer'=>$cid,'limit'=>$limit,'expand[]'=>'data.lines']);
    if(!empty($inv->error)) return '<p>Could not load invoices.</p>';
    if(empty($inv->data)) return '<p>No invoices found.</p>';
    $pf=trim($s['price_filter']); ob_start();
    echo '<div class="oha-invoice-history"><table class="widefat striped"><thead><tr><th>Date</th><th>Amount</th><th>Status</th><th>Invoice</th><th>PDF</th></tr></thead><tbody>';
    foreach($inv->data as $i){
        if($pf){
            $match=false;
            if(!empty($i->lines->data)&&is_array($i->lines->data)){
                foreach($i->lines->data as $ln){ if(!empty($ln->price->id)&&$ln->price->id===$pf){ $match=true; break; } }
            }
            if(!$match) continue;
        }
        $date=!empty($i->created)?date_i18n(get_option('date_format'),$i->created):'-';
        $amount=isset($i->amount_paid)?number_format_i18n($i->amount_paid/100,2):'0.00';
        $status=esc_html($i->status??'-');
        $hosted=!empty($i->hosted_invoice_url)?esc_url($i->hosted_invoice_url):'';
        $pdf=!empty($i->invoice_pdf)?esc_url($i->invoice_pdf):'';
        echo '<tr><td>'.esc_html($date).'</td><td>$'.esc_html($amount).'</td><td>'.$status.'</td><td>'.($hosted?'<a href="'.$hosted.'" target="_blank" rel="noopener">View</a>':'-').'</td><td>'.($pdf?'<a href="'.$pdf.'" target="_blank" rel="noopener">Download</a>':'-').'</td></tr>';
    }
    echo '</tbody></table></div>'; return ob_get_clean();
});

// Address display
add_shortcode('oha_stripe_address', function($atts){
    if(!is_user_logged_in()) return ''; $a=shortcode_atts(['prefer'=>'shipping'],$atts,'oha_stripe_address');
    $uid=get_current_user_id(); $pref=strtolower($a['prefer'])==='billing'?'billing_':'shipping_'; $alt=$pref==='shipping_'?'billing_':'shipping_';
    $disp=get_user_meta($uid,$pref.'address_display',true); if(empty($disp)) $disp=get_user_meta($uid,$alt.'address_display',true);
    if(empty($disp)) return '<p><em>No address on file yet.</em></p>'; return '<div class="oha-stripe-address"><code>'.esc_html($disp).'</code></div>';
});

// Membership Checkout button
add_shortcode('oha_membership_checkout', function($atts){
    if(!is_user_logged_in()) return '<p>Please log in.</p>';
    $nonce=wp_create_nonce('oha_create_session');
    $url=add_query_arg(array('action'=>'oha_create_checkout_session','_wpnonce'=>$nonce), admin_url('admin-ajax.php'));
    
    $output = '<div class="oha-membership-signup" style="background:#f5f5f5;padding:20px;border-radius:8px;margin:20px 0;">';
    $output .= '<h3 style="margin-top:0;color:#a0232a;">OHA Membership</h3>';
    $output .= '<p><strong>Initial Fee:</strong> $20 (one-time for membership card creation and mailing)</p>';
    $output .= '<p><strong>Annual Renewal:</strong> $10/year (starting January 1st of following year)</p>';
    $output .= '<p style="font-size:14px;color:#666;">You\'ll receive your physical membership card by mail and be charged $10 annually to maintain your membership.</p>';
    $output .= '<p><a class="button button-primary button-large" href="'.esc_url($url).'">Join / Renew OHA Membership →</a></p>';
    $output .= '</div>';
    
    return $output;
});

// AJAX: create Checkout Session
add_action('wp_ajax_oha_create_checkout_session', function(){
    if(!is_user_logged_in()) wp_die('Login required');
    check_admin_referer('oha_create_session');
    $s=oha_sm_get();
    if(empty($s['secret_key'])||empty($s['price_signup_20'])||empty($s['price_yearly_10'])) wp_die('Stripe not configured (secret or price IDs missing)');
    $uid=get_current_user_id(); $user=get_userdata($uid);
    $trial_ts=strtotime($s['trial_end_iso']); if(!$trial_ts||$trial_ts<=time()) wp_die('First $10 Charge must be a future UTC timestamp (check Settings).');
    $customer=get_user_meta($uid,'stripe_customer_id',true);

    $body=array(
        'mode'=>'subscription',
        'success_url'=>home_url('/membership/success?session_id={CHECKOUT_SESSION_ID}'),
        'cancel_url'=>home_url('/membership/cancelled'),
        'client_reference_id'=>(string)$uid,
        'metadata[wp_user_id]'=>(string)$uid,

        // one-time $20
        'line_items[0][price]'=>$s['price_signup_20'],
        'line_items[0][quantity]'=>1,

        // $10/yr
        'line_items[1][price]'=>$s['price_yearly_10'],
        'line_items[1][quantity]'=>1,

        // first recurring charge
        'subscription_data[trial_end]'=>$trial_ts,
        'subscription_data[metadata][wp_user_id]'=>(string)$uid,

        // collect addresses
        'billing_address_collection'=>'required',
        'shipping_address_collection[allowed_countries][0]'=>'US',
    );

    if(!empty($customer)){
        // Use existing customer; allow Checkout to write updates back
        $body['customer']=$customer;
        $body['customer_update[address]']='auto';
        $body['customer_update[name]']='auto';
    } else {
        // Create new customer from email; DO NOT include customer_update[*]
        $body['customer_email']=$user->user_email;
    }

    $resp=oha_sm_stripe_post('/v1/checkout/sessions',$body);
    if(!empty($resp->error)){
        $msg='Stripe error'; if(!empty($resp->error->message)) $msg.=': '.$resp->error->message; if(!empty($resp->error->code)) $msg.=' (code '.$resp->error->code.')'; if(!empty($resp->error->type)) $msg.=' ['.$resp->error->type.']';
        oha_sm_admin_note('Checkout create error for user '.$uid.': '.json_encode($resp)); oha_sm_log('Checkout create error: '.json_encode($resp)); wp_die(esc_html($msg));
    }
    if(!empty($resp->url)){ wp_redirect($resp->url); exit; }
    oha_sm_admin_note('Checkout create: unexpected response '.json_encode($resp)); wp_die('Could not create session (unexpected response)');
});

/** ==================
 * Board Hub mirror
 * ================== */
function oha_sm_push_to_board_hub_fields($user_id,$src_prefix='billing_'){
    $src=array(
        'address_line1'=>get_user_meta($user_id,$src_prefix.'address_line1',true),
        'address_line2'=>get_user_meta($user_id,$src_prefix.'address_line2',true),
        'city'=>get_user_meta($user_id,$src_prefix.'city',true),
        'state'=>get_user_meta($user_id,$src_prefix.'state',true),
        'postal_code'=>get_user_meta($user_id,$src_prefix.'postal_code',true),
        'country'=>get_user_meta($user_id,$src_prefix.'country',true),
        'address_display'=>get_user_meta($user_id,$src_prefix.'address_display',true),
    );
    $map=apply_filters('oha_sm_boardhub_meta_map', array(
        'address_line1'=>array('address_street'),
        'address_line2'=>array('address_street'),
        'city'=>array('address_city'),
        'state'=>array('address_state'),
        'postal_code'=>array('address_zipcode'),
        'country'=>array('country'),
        'address_display'=>array('boardhub_address_display'),
    ), $user_id, $src_prefix);
    if(!empty($map['address_line2']) && in_array('address_street',$map['address_line2'],true)){
        $street=trim(($src['address_line1']?:'').($src['address_line2']?' '.$src['address_line2']:''));
        $src['address_line1']=$street; $src['address_line2']='';
    }
    foreach($src as $field=>$val){
        if($val===''||$val===null) continue;
        if(empty($map[$field])||!is_array($map[$field])) continue;
        foreach($map[$field] as $target){ update_user_meta($user_id,$target,$val); }
    }
    return true;
}

/** ==================
 * SWPM sync/activate
 * ================== */
function oha_sm_sync_to_swpm($user_id){
    global $wpdb; $table=$wpdb->prefix.'swpm_members_tbl';
    $exists=$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s",$table)); if($exists!=$table){ oha_sm_admin_note('SWPM table not found: '.$table); return false; }
    $cols=$wpdb->get_results("DESCRIBE {$table}", ARRAY_A); $names=wp_list_pluck($cols,'Field');
    $has_uid=in_array('wp_user_id',$names,true); $has_email=in_array('email',$names,true);

    $line1=get_user_meta($user_id,'billing_address_line1',true);
    $line2=get_user_meta($user_id,'billing_address_line2',true);
    $city=get_user_meta($user_id,'billing_city',true);
    $state=get_user_meta($user_id,'billing_state',true);
    $zip=get_user_meta($user_id,'billing_postal_code',true);
    $country=get_user_meta($user_id,'billing_country',true);
    $street=trim($line1.($line2?' '.$line2:''));

    $data=array('address_street'=>$street,'address_city'=>$city,'address_state'=>$state,'address_zipcode'=>$zip,'country'=>$country);
    $fmt=array('%s','%s','%s','%s','%s');

    if($has_uid){
        $m=$wpdb->get_row($wpdb->prepare("SELECT member_id FROM {$table} WHERE wp_user_id=%d",$user_id));
        if($m && !empty($m->member_id)){ $u=$wpdb->update($table,$data,array('member_id'=>(int)$m->member_id),$fmt,array('%d')); return $u!==false; }
    }
    if($has_email){
        $usr=get_userdata($user_id);
        if($usr && $usr->user_email){
            $m=$wpdb->get_row($wpdb->prepare("SELECT member_id FROM {$table} WHERE email=%s",$usr->user_email));
            if($m && !empty($m->member_id)){ $u=$wpdb->update($table,$data,array('member_id'=>(int)$m->member_id),$fmt,array('%d')); return $u!==false; }
        }
    }
    return false;
}
function oha_sm_set_swpm_membership_active($wp_user_id,$level_id){
    global $wpdb; $table=$wpdb->prefix.'swpm_members_tbl';
    $exists=$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s",$table)); if($exists!=$table) return false;
    $user=get_userdata($wp_user_id); if(!$user) return false;
    $cols=$wpdb->get_results("DESCRIBE {$table}", ARRAY_A); $names=wp_list_pluck($cols,'Field'); $has_uid=in_array('wp_user_id',$names,true);

    $member=null;
    if($has_uid) $member=$wpdb->get_row($wpdb->prepare("SELECT member_id FROM {$table} WHERE wp_user_id=%d",$wp_user_id));
    if(!$member) $member=$wpdb->get_row($wpdb->prepare("SELECT member_id FROM {$table} WHERE email=%s",$user->user_email));

    $data=array('membership_level'=>(int)$level_id,'account_state'=>'active','subscription_starts'=>current_time('mysql'));
    $fmt=array('%d','%s','%s');

    if($member && !empty($member->member_id)){
        $wpdb->update($table,$data,array('member_id'=>(int)$member->member_id),$fmt,array('%d'));
        return true;
    } else {
        $ins=array(
            'user_name'=>$user->user_login,
            'first_name'=>get_user_meta($wp_user_id,'first_name',true),
            'last_name'=>get_user_meta($wp_user_id,'last_name',true),
            'email'=>$user->user_email,
            'membership_level'=>(int)$level_id,
            'account_state'=>'active',
            'subscription_starts'=>current_time('mysql'),
        );
        if($has_uid) $ins['wp_user_id']=(int)$wp_user_id;
        $wpdb->insert($table,$ins);
        return (bool)$wpdb->insert_id;
    }
}

/**
 * Check if a Stripe product ID is configured as a membership product
 * 
 * @param string $product_id Stripe product ID
 * @return bool True if it's a membership product
 */
function oha_sm_is_membership_product($product_id) {
    if(empty($product_id)) return false;
    
    $cfg = oha_sm_get();
    $membership_products = array();
    
    // Check if membership product IDs are configured in settings
    if(!empty($cfg['membership_product_ids'])) {
        $membership_products = array_map('trim', explode(',', $cfg['membership_product_ids']));
    }
    
    // Default membership product patterns (can be customized)
    $default_patterns = array(
        'membership',
        'member',
        'oha_member', 
        'annual_membership',
        'yearly_membership'
    );
    
    // Check exact product ID matches
    if(in_array($product_id, $membership_products, true)) {
        return true;
    }
    
    // Check if product ID contains membership-related terms (fallback)
    foreach($default_patterns as $pattern) {
        if(stripos($product_id, $pattern) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Store unmatched membership payment for admin review
 * 
 * @param object $invoice Stripe invoice object
 * @param string $customer_id Stripe customer ID
 */
function oha_sm_store_unmatched_membership_payment($invoice, $customer_id) {
    if(empty($invoice) || empty($customer_id)) return;
    
    $unmatched_payments = get_option('oha_unmatched_membership_payments', array());
    if(!is_array($unmatched_payments)) $unmatched_payments = array();
    
    // Extract useful info from the invoice
    $payment_data = array(
        'invoice_id' => $invoice->id,
        'customer_id' => $customer_id,
        'customer_email' => $invoice->customer_email ?? '',
        'customer_name' => $invoice->customer_name ?? '',
        'amount_paid' => $invoice->amount_paid ?? 0,
        'currency' => $invoice->currency ?? 'usd',
        'paid_at' => current_time('mysql'),
        'stripe_created' => date('Y-m-d H:i:s', $invoice->created),
        'status' => 'unmatched',
        'products' => array()
    );
    
    // Extract product info
    if(!empty($invoice->lines) && !empty($invoice->lines->data)){
        foreach($invoice->lines->data as $line){
            if(!empty($line->price) && !empty($line->price->product)){
                $payment_data['products'][] = array(
                    'product_id' => $line->price->product,
                    'price_id' => $line->price->id ?? '',
                    'quantity' => $line->quantity ?? 1,
                    'amount' => $line->amount ?? 0
                );
            }
        }
    }
    
    // Store with invoice ID as key
    $unmatched_payments[$invoice->id] = $payment_data;
    
    update_option('oha_unmatched_membership_payments', $unmatched_payments, false);
}

/**
 * Get all unmatched membership payments
 * 
 * @return array Array of unmatched payments
 */
function oha_sm_get_unmatched_payments() {
    $payments = get_option('oha_unmatched_membership_payments', array());
    return is_array($payments) ? $payments : array();
}

/**
 * Try to match unmatched payments when a user registers or logs in
 * 
 * @param int $user_id WordPress user ID
 */
function oha_sm_auto_match_membership_payments($user_id) {
    if(!$user_id) return;
    
    $user = get_userdata($user_id);
    if(!$user || !$user->user_email) return;
    
    $unmatched = oha_sm_get_unmatched_payments();
    if(empty($unmatched)) return;
    
    $matched_any = false;
    
    foreach($unmatched as $invoice_id => $payment_data) {
        if($payment_data['status'] !== 'unmatched') continue;
        
        // Try email matching
        if(!empty($payment_data['customer_email']) && 
           strtolower($payment_data['customer_email']) === strtolower($user->user_email)) {
            
            // Match found! Activate membership
            update_user_meta($user_id, 'uwp_member_type', 'oha_member');
            update_user_meta($user_id, 'uwp_account_type', 'oha_member'); // UsersWP core field
            update_user_meta($user_id, '_uwp_register_form_id', '1'); // Paid member form ID
            update_user_meta($user_id, 'oha_stripe_member_from_invoice', '1');
            update_user_meta($user_id, 'oha_membership_paid', '1');
            update_user_meta($user_id, 'oha_membership_paid_date', $payment_data['paid_at']);
            update_user_meta($user_id, 'oha_stripe_invoice_id', $invoice_id);
            update_user_meta($user_id, 'stripe_customer_id', $payment_data['customer_id']);
            
            // Mark payment as matched
            $unmatched[$invoice_id]['status'] = 'matched';
            $unmatched[$invoice_id]['matched_user_id'] = $user_id;
            $unmatched[$invoice_id]['matched_at'] = current_time('mysql');
            
            $matched_any = true;
            
            oha_sm_log("Auto-matched membership payment: Invoice {$invoice_id} matched to user {$user_id} via email {$user->user_email}");
        }
    }
    
    if($matched_any) {
        update_option('oha_unmatched_membership_payments', $unmatched, false);
    }
}

/**
 * Manually match an unmatched payment to a user (admin function)
 * 
 * @param string $invoice_id Stripe invoice ID
 * @param int $user_id WordPress user ID
 * @return bool Success
 */
function oha_sm_manual_match_payment($invoice_id, $user_id) {
    if(!$invoice_id || !$user_id) return false;
    
    $unmatched = oha_sm_get_unmatched_payments();
    if(!isset($unmatched[$invoice_id])) return false;
    
    $payment_data = $unmatched[$invoice_id];
    if($payment_data['status'] !== 'unmatched') return false;
    
    $user = get_userdata($user_id);
    if(!$user) return false;
    
    // Activate membership
    update_user_meta($user_id, 'uwp_member_type', 'oha_member');
    update_user_meta($user_id, 'uwp_account_type', 'oha_member'); // UsersWP core field
    update_user_meta($user_id, '_uwp_register_form_id', '1'); // Paid member form ID
    update_user_meta($user_id, 'oha_stripe_member_from_invoice', '1');
    update_user_meta($user_id, 'oha_membership_paid', '1');
    update_user_meta($user_id, 'oha_membership_paid_date', $payment_data['paid_at']);
    update_user_meta($user_id, 'oha_stripe_invoice_id', $invoice_id);
    update_user_meta($user_id, 'stripe_customer_id', $payment_data['customer_id']);
    
    // Mark payment as matched
    $unmatched[$invoice_id]['status'] = 'matched';
    $unmatched[$invoice_id]['matched_user_id'] = $user_id;
    $unmatched[$invoice_id]['matched_at'] = current_time('mysql');
    
    update_option('oha_unmatched_membership_payments', $unmatched, false);
    
    oha_sm_log("Manual match: Invoice {$invoice_id} matched to user {$user_id} by admin");
    
    return true;
}

/**
 * Create a WordPress user from Stripe invoice data
 * 
 * @param object $invoice Stripe invoice object
 * @param string $customer_id Stripe customer ID
 * @return int|false User ID on success, false on failure
 */
function oha_sm_create_user_from_invoice($invoice, $customer_id) {
    if(empty($invoice) || empty($customer_id)) return false;
    
    $customer_email = $invoice->customer_email ?? '';
    $customer_name = $invoice->customer_name ?? '';
    
    // Must have email to create account
    if(empty($customer_email) || !is_email($customer_email)) {
        oha_sm_log("Cannot create user from invoice {$invoice->id} - invalid email: {$customer_email}");
        return false;
    }
    
    // Check if user already exists by email (should not happen but safety check)
    if(get_user_by('email', $customer_email)) {
        oha_sm_log("User already exists with email {$customer_email} - should have been matched earlier");
        return false;
    }
    
    // Parse name
    $first_name = '';
    $last_name = '';
    if($customer_name) {
        $name_parts = explode(' ', trim($customer_name), 2);
        $first_name = $name_parts[0] ?? '';
        $last_name = $name_parts[1] ?? '';
    }
    
    // Generate username from email
    $username = sanitize_user(strtok($customer_email, '@'), true);
    $original_username = $username;
    $counter = 1;
    
    // Ensure username is unique
    while(username_exists($username)) {
        $username = $original_username . $counter;
        $counter++;
        if($counter > 100) break; // Prevent infinite loop
    }
    
    // Generate random password
    $password = wp_generate_password(16, false);
    
    // Create user
    $user_data = array(
        'user_login' => $username,
        'user_email' => $customer_email,
        'user_pass' => $password,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'display_name' => $customer_name ?: $customer_email,
        'role' => 'subscriber'
    );
    
    $user_id = wp_insert_user($user_data);
    
    if(is_wp_error($user_id)) {
        oha_sm_log("Failed to create user from invoice {$invoice->id}: " . $user_id->get_error_message());
        return false;
    }
    
    // Store Stripe metadata
    update_user_meta($user_id, 'stripe_customer_id', $customer_id);
    update_user_meta($user_id, 'oha_created_from_invoice', '1');
    update_user_meta($user_id, 'oha_created_from_invoice_id', $invoice->id);
    update_user_meta($user_id, 'oha_account_needs_setup', '1');
    
    // Send password reset email instead of password
    wp_new_user_notification($user_id, null, 'user');
    
    oha_sm_log("Created new WordPress user {$user_id} ({$username}) from Stripe invoice {$invoice->id}");
    
    return $user_id;
}

/** Auto-heal: sync to SWPM on login if we have an address */
add_action('wp_login', function($user_login,$user){
    if($user && !empty($user->ID)){
        if(get_user_meta($user->ID,'billing_address_line1',true)) oha_sm_sync_to_swpm($user->ID);
        
        // Try to auto-match any unmatched membership payments
        oha_sm_auto_match_membership_payments($user->ID);
    }
},10,2);

// Auto-match payments when new users register
add_action('user_register', function($user_id){
    if($user_id) {
        oha_sm_auto_match_membership_payments($user_id);
    }
});

// Auto-match payments when user email is updated
add_action('profile_update', function($user_id, $old_user_data){
    $new_user = get_userdata($user_id);
    if($new_user && $old_user_data && $new_user->user_email !== $old_user_data->user_email) {
        oha_sm_auto_match_membership_payments($user_id);
        oha_sm_log("Email updated for user {$user_id}, attempting payment matching");
    }
}, 10, 2);

/**
 * Handle sponsor payments from Stripe webhooks
 * 
 * @param object $invoice Stripe invoice object
 * @param object $event Stripe event object
 */
function oha_sm_handle_sponsor_payment($invoice, $event) {
    if (!function_exists('oha_vendor_get_sponsor_settings')) {
        return; // Vendor/sponsor system not available
    }
    
    $settings = oha_vendor_get_sponsor_settings();
    if ($settings['sponsor_webhook_enabled'] !== '1') {
        return; // Sponsor webhook processing disabled
    }
    
    // Get invoice details
    $customer_email = $invoice->customer_email ?? '';
    $amount_paid = ($invoice->amount_paid ?? 0) / 100; // Convert from cents
    $invoice_id = $invoice->id;
    
    if (!$customer_email) return;
    
    // Check if this is a sponsor product by looking at line items
    $is_sponsor_product = false;
    $sponsor_product_type = 'unknown';
    
    if (!empty($invoice->lines) && !empty($invoice->lines->data)) {
        foreach ($invoice->lines->data as $line) {
            if (!empty($line->price) && !empty($line->price->product)) {
                $product_id = $line->price->product;
                
                // Check if it's the basic sponsor product
                if ($product_id === $settings['basic_sponsor_product_id']) {
                    $is_sponsor_product = true;
                    $sponsor_product_type = 'basic';
                    break;
                }
                
                // Check for sponsor-related product patterns
                $sponsor_patterns = ['sponsor', 'sponsorship', 'event_sponsor', 'convention_sponsor'];
                foreach ($sponsor_patterns as $pattern) {
                    if (stripos($product_id, $pattern) !== false) {
                        $is_sponsor_product = true;
                        $sponsor_product_type = 'detected';
                        break 2;
                    }
                }
            }
        }
    }
    
    // Find matching vendor application by email
    $applications = get_posts([
        'post_type' => 'vendor_application',
        'meta_query' => [
            [
                'key' => 'email',
                'value' => $customer_email,
                'compare' => '='
            ]
        ],
        'posts_per_page' => -1,
        'post_status' => ['publish', 'draft', 'private']
    ]);
    
    foreach ($applications as $app) {
        $post_id = $app->ID;
        
        if (function_exists('oha_vbh_get_sponsor_type')) {
            $sponsor_type = oha_vbh_get_sponsor_type($post_id);
            $sponsor_amount = oha_vbh_get_sponsor_amount($post_id);
            
            // Determine if this payment matches a sponsor
            $is_sponsor_payment = false;
            
            if ($is_sponsor_product && $sponsor_type) {
                $is_sponsor_payment = true;
            } elseif ($sponsor_type === 'basic' && abs($amount_paid - floatval($settings['basic_sponsor_price'])) < 0.01) {
                $is_sponsor_payment = true;
            } elseif ($sponsor_type && $sponsor_amount && abs($amount_paid - floatval($sponsor_amount)) < 0.01) {
                $is_sponsor_payment = true;
            }
            
            if ($is_sponsor_payment && function_exists('oha_vbh_set_sponsor_status') && function_exists('oha_vbh_append_history')) {
                // Mark sponsor as paid
                oha_vbh_set_sponsor_status($post_id, 'paid');
                
                // Update main vendor status if not already paid
                if (function_exists('oha_vbh_status') && function_exists('oha_vbh_set_status')) {
                    $current_status = oha_vbh_status($post_id);
                    if ($current_status !== 'Paid') {
                        oha_vbh_set_status($post_id, 'Paid');
                    }
                }
                
                // Store payment URL if available
                if (!empty($invoice->hosted_invoice_url)) {
                    update_post_meta($post_id, '_payment_url', $invoice->hosted_invoice_url);
                }
                
                // Log the payment
                oha_vbh_append_history($post_id, [
                    'action' => 'Sponsor payment received via webhook: $' . number_format($amount_paid, 2) . ' (Invoice: ' . $invoice_id . ')',
                    'timestamp' => current_time('mysql'),
                    'user' => 0 // System/webhook
                ]);
                
                oha_sm_log("Sponsor payment processed: Post {$post_id}, Amount: ${amount_paid}, Invoice: {$invoice_id}, Type: {$sponsor_product_type}");
                
                break; // Found matching application
            }
        }
    }
}

/** ==================
 * SMART MEMBERSHIP BUTTON
 * Auto-detects user role and shows appropriate button text
 * ================== */

// Smart membership button - detects user role and adjusts button text
add_shortcode('oha_smart_membership_button', function($atts) {
    if (!is_user_logged_in()) {
        $atts = shortcode_atts([
            'text' => 'Login to Join OHA',
            'class' => 'button button-primary',
            'style' => ''
        ], $atts, 'oha_smart_membership_button');
        
        return '<a href="' . esc_url(wp_login_url(get_permalink())) . '" class="' . esc_attr($atts['class']) . '" style="' . esc_attr($atts['style']) . '">' . esc_html($atts['text']) . '</a>';
    }
    
    $atts = shortcode_atts([
        'text' => '',
        'class' => 'button button-primary', 
        'style' => ''
    ], $atts, 'oha_smart_membership_button');
    
    $user = wp_get_current_user();
    $roles = $user->roles;
    
    // Auto-detect appropriate button text based on user status
    $button_text = $atts['text'];
    if (empty($button_text)) {
        if (in_array('subscriber', $roles)) {
            // Current OHA member - show renewal message
            $button_text = 'Renew Membership';
        } elseif (in_array('listing_owner', $roles)) {
            // Free listing owner - show upgrade message
            $button_text = 'Upgrade to OHA Member';
        } else {
            // New/other user - show join message
            $button_text = 'Join OHA';
        }
    }
    
    // Use existing checkout system (same as regular button)
    $nonce = wp_create_nonce('oha_create_session');
    $url = add_query_arg([
        'action' => 'oha_create_checkout_session',
        '_wpnonce' => $nonce
    ], admin_url('admin-ajax.php'));
    
    $style_attr = !empty($atts['style']) ? ' style="' . esc_attr($atts['style']) . '"' : '';
    
    return '<a href="' . esc_url($url) . '" class="' . esc_attr($atts['class']) . '"' . $style_attr . '>' . esc_html($button_text) . '</a>';
});
