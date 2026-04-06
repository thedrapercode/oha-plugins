<?php
if (!defined('ABSPATH')) exit;

/**
 * Stripe Payment Links Settings
 * Simple Payment Links only - no API complexity
 */

function oha_vhcpt_save_stripe_settings() {
    if (!current_user_can('manage_options')) return;
    
    if (isset($_POST['stripe_link_test'])) {
        update_option('oha_vhcpt_stripe_link_test', esc_url_raw($_POST['stripe_link_test']));
    }
    if (isset($_POST['stripe_link_live'])) {
        update_option('oha_vhcpt_stripe_link_live', esc_url_raw($_POST['stripe_link_live']));
    }
    if (isset($_POST['stripe_mode'])) {
        update_option('oha_vhcpt_stripe_mode', sanitize_text_field($_POST['stripe_mode']));
    }
}

function oha_vhcpt_render_stripe_settings() {
    $stripe_link_test = get_option('oha_vhcpt_stripe_link_test', '');
    $stripe_link_live = get_option('oha_vhcpt_stripe_link_live', '');
    $stripe_mode = get_option('oha_vhcpt_stripe_mode', 'test');
    ?>
    
    <h2 class="title">Stripe Payment Links</h2>
    <p>Configure Stripe Payment Links for vendor payments. <a href="https://dashboard.stripe.com/payment-links" target="_blank">Create Payment Links in Stripe Dashboard →</a></p>
    
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="stripe_mode">Payment Mode</label>
            </th>
            <td>
                <select name="stripe_mode" id="stripe_mode">
                    <option value="test" <?php selected($stripe_mode, 'test'); ?>>Test Mode</option>
                    <option value="live" <?php selected($stripe_mode, 'live'); ?>>Live Mode</option>
                </select>
                <p class="description">Switch between test and live payment processing.</p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="stripe_link_test">Test Payment Link</label>
            </th>
            <td>
                <input type="url" name="stripe_link_test" id="stripe_link_test" value="<?php echo esc_attr($stripe_link_test); ?>" class="large-text">
                <p class="description">
                    Your Stripe TEST mode Payment Link URL.<br>
                    Example: <code>https://buy.stripe.com/test_...</code>
                </p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="stripe_link_live">Live Payment Link</label>
            </th>
            <td>
                <input type="url" name="stripe_link_live" id="stripe_link_live" value="<?php echo esc_attr($stripe_link_live); ?>" class="large-text">
                <p class="description">
                    Your Stripe LIVE mode Payment Link URL.<br>
                    Example: <code>https://buy.stripe.com/live_...</code>
                </p>
            </td>
        </tr>
    </table>
    
    <div class="notice notice-info inline">
        <h3>How to Set Up Stripe Payment Links</h3>
        <ol>
            <li>Log into your <a href="https://dashboard.stripe.com" target="_blank">Stripe Dashboard</a></li>
            <li>Go to <strong>Payment links</strong> in the left menu</li>
            <li>Click <strong>+ New</strong> to create a payment link</li>
            <li>Set up your product:
                <ul>
                    <li><strong>Name:</strong> OHA Convention 2026 Vendor Booth</li>
                    <li><strong>Price:</strong> $250.00 USD</li>
                    <li><strong>Adjustable quantity:</strong> Enable this! (Vendors can adjust 1-10 booths)</li>
                </ul>
            </li>
            <li>In <strong>Payment options</strong>, enable "Collect customer email"</li>
            <li>Under <strong>After payment</strong>, set redirect to your final application page with <code>?payment=success</code></li>
            <li>Copy the Payment Link URL and paste it above</li>
            <li>Create separate links for test mode and live mode</li>
        </ol>
        <p><strong>Note:</strong> Most vendors will buy 1 booth and won't need to adjust. The 1-2 vendors who need multiple booths can easily adjust the quantity in Stripe's checkout (takes 5 seconds). Keep it simple!</p>
    </div>
    
    <?php
}
