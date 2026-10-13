<?php
/**
 * Plugin Name: OHA Board Hub — Awards Addon
 * Description: Haunt Awards capture via Kadence (free bridge), Board Hub review, bulk certificates, and owner downloads/print.
 * Version: 6.8.0
 * Author: OHA
 * Text Domain: oha-awards
 */

if (!defined('ABSPATH')) exit;

define('OHA_AWARDS_VER', '6.8.0');
define('OHA_AWARDS_DIR', plugin_dir_path(__FILE__));
define('OHA_AWARDS_URL', plugin_dir_url(__FILE__));

require_once OHA_AWARDS_DIR . 'includes/helpers.php';
require_once OHA_AWARDS_DIR . 'includes/class-cpt.php';
require_once OHA_AWARDS_DIR . 'includes/class-settings.php';
require_once OHA_AWARDS_DIR . 'includes/class-admin-table.php';
require_once OHA_AWARDS_DIR . 'includes/class-bulk-upload.php';
require_once OHA_AWARDS_DIR . 'includes/class-shortcodes.php';
require_once OHA_AWARDS_DIR . 'includes/class-frontend-admin.php';
require_once OHA_AWARDS_DIR . 'includes/class-bulk-certificates.php';
require_once OHA_AWARDS_DIR . 'includes/class-email.php';
require_once OHA_AWARDS_DIR . 'includes/class-frontend-form.php';
require_once OHA_AWARDS_DIR . 'includes/class-campsite-form.php';
require_once OHA_AWARDS_DIR . 'includes/class-awards-debug.php';
require_once OHA_AWARDS_DIR . 'includes/class-stripe-stub.php';



/**
 * Enqueue frontend assets for forms and admin views when our shortcodes are present.
 */
function oha_awards_enqueue_assets() {
    $load = false;
    if (is_singular() || is_page()) {
        global $post;
        if ($post) {
            $content = $post->post_content;
            if (has_shortcode($content, 'oha_awards_submit') || has_shortcode($content, 'oha_campsite_award_submit') || has_shortcode($content, 'oha_awards_admin') || has_shortcode($content, 'oha_award_admin')) {
                $load = true;
            }
        }
    }
    if ($load) {
        wp_enqueue_style('oha-awards', OHA_AWARDS_URL . 'assets/css/oha-awards.css', [], OHA_AWARDS_VER);
        wp_enqueue_script('oha-awards', OHA_AWARDS_URL . 'assets/js/oha-awards.js', [], OHA_AWARDS_VER, true);
        wp_localize_script('oha-awards', 'ohaAwards', [
            'postUrl' => admin_url('admin-post.php'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('oha_awards_nonce'),
        ]);
    }
}
add_action('wp_enqueue_scripts', 'oha_awards_enqueue_assets');

add_action('plugins_loaded', function() {
    OHA_Awards_CPT::init();
    OHA_Awards_Settings::init();
    OHA_Awards_Admin_Table::init();
    OHA_Awards_Bulk_Upload::init();
    OHA_Awards_Shortcodes::init();
    OHA_Awards_Front_Admin::init();
    OHA_Awards_Front_Submit::init();
    OHA_Campsite_Awards_Front_Submit::init();
    OHA_Awards_Stripe_Stub::init();
});


/**
 * Enqueue prefill helper on GeoDirectory Add Listing page if we have data.
 */
function oha_awards_enqueue_prefill() {
    if (is_admin()) return;
    $req = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($req, '/add-listing/places/') === false) return;
    if (!is_user_logged_in()) return;
    $user_id = get_current_user_id();
    $data = get_user_meta($user_id, '_oha_awards_prefill', true);
    if (!is_array($data) || empty($data)) return;
    wp_enqueue_script('oha-awards-prefill', OHA_AWARDS_URL . 'assets/js/oha-awards-prefill.js', [], OHA_AWARDS_VER, true);
    wp_localize_script('oha-awards-prefill', 'ohaAwardsPrefill', $data);
}
add_action('wp_enqueue_scripts', 'oha_awards_enqueue_prefill', 20);

add_action('wp_ajax_oha_awards_clear_prefill', function(){
    if (!is_user_logged_in()) wp_send_json_error();
    delete_user_meta(get_current_user_id(), '_oha_awards_prefill');
    wp_send_json_success();
});


/**
 * Background email sender to avoid front-end timeouts.
 */
add_action('oha_awards_send_email', function($to, $subject, $body, $headers = []){
    // Basic validation
    if (empty($to) || empty($subject) || empty($body)) return;
    // Allow string or array of recipients
    if (is_array($to)) { foreach ($to as $rcpt){ wp_mail($rcpt, $subject, $body, $headers); } }
    else { wp_mail($to, $subject, $body, $headers); }
}, 10, 4);


/**
 * Background fallback to force-create a GD claim (runs via wp_cron).
 * Args: listing_id (int), user_id (int), message (string)
 */
add_action('oha_awards_force_claim', function($listing_id, $user_id, $message){
    try {
        if (!$listing_id || !$user_id) return;
        if (!post_type_exists('gd_claim') && !defined('GDCR_CPT')) return;
        $cpt_slug = (defined('GDCR_CPT') && GDCR_CPT) ? GDCR_CPT : 'gd_claim';
        // Scoped cap override
        $cap_filter = function($caps, $cap, $uid, $args){
            if ($cap === 'do_not_allow') { return ['exist']; }
            return $caps;
        };
        add_filter('map_meta_cap', $cap_filter, 999, 4);
        $claim_id = wp_insert_post([
            'post_type'   => $cpt_slug,
            'post_status' => 'pending',
            'post_title'  => 'Claim for #'. intval($listing_id) .' by user #'. intval($user_id),
            'meta_input'  => [
                '_gdcr_listing_id' => intval($listing_id),
                '_gdcr_claimant'   => intval($user_id),
                '_gdcr_message'    => sanitize_text_field($message),
            ],
        ]);
        remove_filter('map_meta_cap', $cap_filter, 999);
        if (function_exists('OHA_Awards_Helpers::dbg')) {
            if (is_wp_error($claim_id)) {
                OHA_AWARDS_Helpers::dbg('Force-claim failed', ['error'=>$claim_id->get_error_code(), 'message'=>$claim_id->get_error_message()]);
            } else {
                OHA_AWARDS_Helpers::dbg('Force-claim result', ['claim_id'=>$claim_id, 'exists'=> (bool) get_post($claim_id)]);
            }
        }
    } catch (Throwable $e) {
        if (function_exists('OHA_Awards_Helpers::dbg')) {
            OHA_AWARDS_Helpers::dbg('Force-claim exception', ['msg'=>$e->getMessage()]);
        }
    }
}, 10, 3);


add_action('admin_post_oha_awards_start_print', function(){
    if (!is_user_logged_in()) wp_die('Please log in');
    $entry_id = intval($_GET['entry_id'] ?? 0);
    if (!$entry_id) wp_die('Missing entry');
    if (isset($_GET['_wpnonce']) && !wp_verify_nonce($_GET['_wpnonce'], 'oha_awards_start_print_'.$entry_id)) wp_die('Bad nonce');
    // Record intent for Board visibility
    update_post_meta($entry_id, '_oha_print_requested', current_time('mysql'));
    $opts = get_option('oha_awards_settings', []);
    $pay = isset($opts['stripe_payment_link']) ? esc_url($opts['stripe_payment_link']) : '';
    if (!$pay) { wp_die('Payment link not configured.'); }
    $url = add_query_arg(['entry_id'=>$entry_id], $pay);
    wp_safe_redirect($url);
    exit;
});

add_action('plugins_loaded', function(){ if (class_exists('OHA_Awards_Bulk_Certificates')) OHA_Awards_Bulk_Certificates::init(); });
