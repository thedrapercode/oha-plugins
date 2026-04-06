<?php
/**
 * No-Payment Final Form Shortcode
 * 
 * For pre-paid vendors - same form but generates PDF immediately without Stripe
 * 
 * Shortcode: [oha_vendor_final_form_no_payment]
 */

if (!defined('ABSPATH')) exit;

add_shortcode('oha_vendor_final_form_no_payment', function($atts) {
    // Set the no-payment URL flag so the inner shortcode detects it.
    // oha_vendor_final_form_shortcode() does not exist (the callback is
    // anonymous); use do_shortcode() which calls the registered handler.
    if (!isset($_GET['no_payment_mode'])) {
        $_GET['no_payment_mode'] = '1';
        $_REQUEST['no_payment_mode'] = '1';
    }

    return do_shortcode('[oha_vendor_final_form]');
});

/**
 * Check if we're in no-payment mode
 */
if (!function_exists('oha_vhcpt_is_no_payment_mode')) {
    function oha_vhcpt_is_no_payment_mode() {
        return !empty($_GET['no_payment_mode']) || !empty($_POST['no_payment_mode']) || !empty($_REQUEST['no_payment_mode']);
    }
}
