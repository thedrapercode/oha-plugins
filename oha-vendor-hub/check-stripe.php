<?php
/**
 * OHA Stripe Settings Checker
 * Upload to WordPress root and visit to check Stripe configuration
 */

require_once('./wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied. Must be logged in as administrator.');
}

$stripe_mode = get_option('oha_vhcpt_stripe_mode', 'test');
$stripe_test = get_option('oha_vhcpt_stripe_link_test', '');
$stripe_live = get_option('oha_vhcpt_stripe_link_live', '');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Stripe Settings Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; margin: 10px 0; border: 2px solid #333; border-radius: 8px; }
        .success { border-color: #22c55e; background: #f0fdf4; }
        .error { border-color: #ef4444; background: #fef2f2; }
        .warning { border-color: #f59e0b; background: #fffbeb; }
        h2 { margin-top: 0; }
        code { background: #e5e7eb; padding: 2px 6px; border-radius: 3px; font-size: 13px; }
        pre { background: #1f2937; color: #f3f4f6; padding: 15px; border-radius: 6px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; }
        .test-btn { background: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 16px; }
        .test-btn:hover { background: #2563eb; }
    </style>
</head>
<body>
    <h1>🔧 OHA Stripe Configuration Check</h1>
    <p><strong>Time:</strong> <?php echo current_time('mysql'); ?></p>

    <div class="box <?php echo !empty($stripe_mode) ? 'success' : 'error'; ?>">
        <h2>Current Mode</h2>
        <p><strong>Mode:</strong> <code><?php echo esc_html($stripe_mode); ?></code></p>
        <p>The plugin is currently set to use <strong><?php echo $stripe_mode === 'live' ? 'LIVE' : 'TEST'; ?></strong> mode.</p>
    </div>

    <div class="box <?php echo !empty($stripe_test) ? 'success' : 'error'; ?>">
        <h2>Test Mode Payment Link</h2>
        <?php if (!empty($stripe_test)): ?>
            <p>✅ <strong>Test payment link is configured</strong></p>
            <p><strong>URL:</strong> <code><?php echo esc_html($stripe_test); ?></code></p>
            <p><a href="<?php echo esc_url($stripe_test); ?>" target="_blank" class="test-btn">Test This Link</a></p>
            
            <?php if (strpos($stripe_test, 'buy.stripe.com') !== false): ?>
                <p style="color:#22c55e;">✅ URL looks valid (contains buy.stripe.com)</p>
            <?php else: ?>
                <p style="color:#ef4444;">⚠️ URL doesn't look like a Stripe Payment Link</p>
            <?php endif; ?>
        <?php else: ?>
            <p>❌ <strong>Test payment link is NOT configured</strong></p>
            <p>Go to: <a href="<?php echo admin_url('edit.php?post_type=vendor_application&page=oha-vhcpt-settings'); ?>">Plugin Settings</a></p>
        <?php endif; ?>
    </div>

    <div class="box <?php echo !empty($stripe_live) ? 'success' : 'error'; ?>">
        <h2>Live Mode Payment Link</h2>
        <?php if (!empty($stripe_live)): ?>
            <p>✅ <strong>Live payment link is configured</strong></p>
            <p><strong>URL:</strong> <code><?php echo esc_html($stripe_live); ?></code></p>
            <p><a href="<?php echo esc_url($stripe_live); ?>" target="_blank" class="test-btn">Test This Link</a></p>
            
            <?php if (strpos($stripe_live, 'buy.stripe.com') !== false): ?>
                <p style="color:#22c55e;">✅ URL looks valid (contains buy.stripe.com)</p>
            <?php else: ?>
                <p style="color:#ef4444;">⚠️ URL doesn't look like a Stripe Payment Link</p>
            <?php endif; ?>
        <?php else: ?>
            <p>❌ <strong>Live payment link is NOT configured</strong></p>
            <p>Go to: <a href="<?php echo admin_url('edit.php?post_type=vendor_application&page=oha-vhcpt-settings'); ?>">Plugin Settings</a></p>
        <?php endif; ?>
    </div>

    <div class="box <?php echo ($stripe_mode === 'test' && !empty($stripe_test)) || ($stripe_mode === 'live' && !empty($stripe_live)) ? 'success' : 'error'; ?>">
        <h2>Current Configuration Status</h2>
        <?php
        $active_link = ($stripe_mode === 'live') ? $stripe_live : $stripe_test;
        
        if (!empty($active_link)):
        ?>
            <p style="font-size:18px;color:#22c55e;font-weight:bold;">✅ CONFIGURED CORRECTLY</p>
            <p>The plugin will redirect to <strong><?php echo $stripe_mode; ?> mode</strong> payment link after form submission.</p>
            <p><strong>Active link:</strong></p>
            <pre><?php echo esc_html($active_link); ?></pre>
        <?php else: ?>
            <p style="font-size:18px;color:#ef4444;font-weight:bold;">❌ NOT CONFIGURED</p>
            <p>The plugin is set to <strong><?php echo $stripe_mode; ?> mode</strong> but no payment link is configured for this mode!</p>
            <p><strong>Required action:</strong> Add a <?php echo $stripe_mode; ?> mode payment link in <a href="<?php echo admin_url('edit.php?post_type=vendor_application&page=oha-vhcpt-settings'); ?>">plugin settings</a>.</p>
        <?php endif; ?>
    </div>

    <div class="box">
        <h2>Test URL Builder</h2>
        <p>This simulates what happens when a vendor submits the form:</p>
        
        <?php
        // Simulate URL building
        $test_args = [
            'app_id' => 123,
            'booths' => 2,
            'amount' => 500,
            'email' => 'test@example.com',
            'company' => 'Test Company',
            'mode' => $stripe_mode,
        ];
        
        if (function_exists('oha_vhcpt_build_stripe_url')) {
            $test_url = oha_vhcpt_build_stripe_url($test_args);
            
            if ($test_url):
            ?>
                <p style="color:#22c55e;">✅ <strong>URL built successfully!</strong></p>
                <p><strong>Generated URL:</strong></p>
                <pre><?php echo esc_html($test_url); ?></pre>
                <p><a href="<?php echo esc_url($test_url); ?>" target="_blank" class="test-btn">Test This URL</a></p>
                
                <p><strong>URL Parameters:</strong></p>
                <ul>
                    <li>prefilled_email: test@example.com</li>
                    <li>client_reference_id: app_123</li>
                    <li>success_url: (return URL after payment)</li>
                </ul>
            <?php else: ?>
                <p style="color:#ef4444;">❌ <strong>URL building FAILED</strong></p>
                <p>The function returned FALSE, which means the <?php echo $stripe_mode; ?> payment link is not configured.</p>
            <?php endif; ?>
        <?php else: ?>
            <p style="color:#ef4444;">❌ Function oha_vhcpt_build_stripe_url not found</p>
        <?php endif; ?>
    </div>

    <div class="box warning">
        <h2>⚠️ What Happens After Form Submission</h2>
        <ol>
            <li>Vendor fills out form and signs</li>
            <li>Form submits to WordPress</li>
            <li>Plugin saves data and signature</li>
            <li>Plugin builds Stripe redirect URL using settings above</li>
            <li><strong>Plugin redirects browser to Stripe</strong></li>
            <li>Vendor completes payment on Stripe</li>
            <li>Stripe redirects back to your site with success message</li>
        </ol>
        
        <p><strong>If you're getting a black screen after submission:</strong></p>
        <ul>
            <li>The redirect in step 5 is failing</li>
            <li>Usually because the Stripe URL is empty/invalid</li>
            <li>Or headers are already sent (output buffering issue)</li>
        </ul>
    </div>

    <div class="box">
        <h2>Debug Information</h2>
        <pre><?php
        echo "Stripe Settings\n";
        echo "===============\n";
        echo "Mode: " . $stripe_mode . "\n";
        echo "Test Link Configured: " . (!empty($stripe_test) ? 'YES' : 'NO') . "\n";
        echo "Live Link Configured: " . (!empty($stripe_live) ? 'YES' : 'NO') . "\n";
        echo "Active Link Configured: " . (!empty($active_link) ? 'YES' : 'NO') . "\n";
        echo "\n";
        echo "If active link is configured: " . (!empty($active_link) ? 'Form should redirect to Stripe' : 'Form will show error') . "\n";
        ?></pre>
    </div>

    <p style="text-align:center;margin-top:40px;">
        <a href="<?php echo admin_url('edit.php?post_type=vendor_application&page=oha-vhcpt-settings'); ?>" class="test-btn">Go to Plugin Settings</a>
        <a href="<?php echo admin_url(); ?>" class="test-btn" style="background:#6b7280;">Back to WordPress Admin</a>
    </p>
</body>
</html>
