<?php
/**
 * Simple Stripe Settings Check - No WordPress functions
 */

// Don't use WordPress - direct database access
$db_config = [];

// Try to find wp-config.php
$config_path = './wp-config.php';
if (!file_exists($config_path)) {
    die('Cannot find wp-config.php - upload this file to WordPress root directory');
}

// Read wp-config to get database credentials
$config_content = file_get_contents($config_path);

// Extract DB credentials
preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $config_content, $db_name_match);
preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $config_content, $db_user_match);
preg_match("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $config_content, $db_pass_match);
preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $config_content, $db_host_match);
preg_match("/\\\$table_prefix\s*=\s*['\"]([^'\"]+)['\"]/", $config_content, $prefix_match);

$db_name = $db_name_match[1] ?? '';
$db_user = $db_user_match[1] ?? '';
$db_pass = $db_pass_match[1] ?? '';
$db_host = $db_host_match[1] ?? 'localhost';
$prefix = $prefix_match[1] ?? 'wp_';

if (empty($db_name)) {
    die('Could not extract database credentials from wp-config.php');
}

// Connect to database
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

// Get Stripe settings from options table
$stmt = $pdo->prepare("SELECT option_name, option_value FROM {$prefix}options WHERE option_name IN ('oha_vhcpt_stripe_mode', 'oha_vhcpt_stripe_link_test', 'oha_vhcpt_stripe_link_live')");
$stmt->execute();
$options = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stripe_mode = $options['oha_vhcpt_stripe_mode'] ?? 'test';
$stripe_test = $options['oha_vhcpt_stripe_link_test'] ?? '';
$stripe_live = $options['oha_vhcpt_stripe_link_live'] ?? '';

$active_link = ($stripe_mode === 'live') ? $stripe_live : $stripe_test;
$is_configured = !empty($active_link);

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
        h1 { color: #111; }
        h2 { margin-top: 0; color: #111; }
        code { background: #e5e7eb; padding: 2px 6px; border-radius: 3px; font-size: 13px; word-break: break-all; }
        pre { background: #1f2937; color: #f3f4f6; padding: 15px; border-radius: 6px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; }
        .big-status { font-size: 24px; font-weight: bold; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0; }
        .big-success { background: #d1fae5; color: #065f46; border: 3px solid #22c55e; }
        .big-error { background: #fee2e2; color: #991b1b; border: 3px solid #ef4444; }
        ul { line-height: 1.8; }
        li { margin: 5px 0; }
    </style>
</head>
<body>
    <h1>🔧 Stripe Configuration Check</h1>
    <p><strong>Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
    
    <?php if ($is_configured): ?>
        <div class="big-status big-success">
            ✅ STRIPE IS CONFIGURED
        </div>
    <?php else: ?>
        <div class="big-status big-error">
            ❌ STRIPE IS NOT CONFIGURED
        </div>
    <?php endif; ?>

    <div class="box <?php echo $stripe_mode ? 'success' : 'error'; ?>">
        <h2>Current Mode</h2>
        <p><strong>Active Mode:</strong> <code><?php echo htmlspecialchars($stripe_mode); ?></code></p>
        <p>The plugin will use <strong><?php echo strtoupper($stripe_mode); ?> MODE</strong> payment links.</p>
    </div>

    <div class="box <?php echo !empty($stripe_test) ? 'success' : 'warning'; ?>">
        <h2>Test Mode Payment Link</h2>
        <?php if (!empty($stripe_test)): ?>
            <p style="color:#22c55e;font-weight:bold;">✅ Test link is configured</p>
            <p><strong>URL:</strong><br><code><?php echo htmlspecialchars($stripe_test); ?></code></p>
            
            <?php if (strpos($stripe_test, 'buy.stripe.com') !== false): ?>
                <p style="color:#22c55e;">✅ URL format looks correct (contains buy.stripe.com)</p>
            <?php else: ?>
                <p style="color:#ef4444;">⚠️ Warning: URL doesn't contain 'buy.stripe.com'</p>
                <p>It should look like: <code>https://buy.stripe.com/test_xxxxx</code></p>
            <?php endif; ?>
            
            <p><a href="<?php echo htmlspecialchars($stripe_test); ?>" target="_blank" style="display:inline-block;background:#3b82f6;color:white;padding:10px 20px;text-decoration:none;border-radius:6px;">Test This Link in Browser</a></p>
        <?php else: ?>
            <p style="color:#f59e0b;">⚠️ Test link is NOT configured</p>
            <p>If you want to use test mode, you need to add a test payment link.</p>
        <?php endif; ?>
    </div>

    <div class="box <?php echo !empty($stripe_live) ? 'success' : 'warning'; ?>">
        <h2>Live Mode Payment Link</h2>
        <?php if (!empty($stripe_live)): ?>
            <p style="color:#22c55e;font-weight:bold;">✅ Live link is configured</p>
            <p><strong>URL:</strong><br><code><?php echo htmlspecialchars($stripe_live); ?></code></p>
            
            <?php if (strpos($stripe_live, 'buy.stripe.com') !== false): ?>
                <p style="color:#22c55e;">✅ URL format looks correct (contains buy.stripe.com)</p>
            <?php else: ?>
                <p style="color:#ef4444;">⚠️ Warning: URL doesn't contain 'buy.stripe.com'</p>
                <p>It should look like: <code>https://buy.stripe.com/xxxxx</code></p>
            <?php endif; ?>
            
            <p><a href="<?php echo htmlspecialchars($stripe_live); ?>" target="_blank" style="display:inline-block;background:#3b82f6;color:white;padding:10px 20px;text-decoration:none;border-radius:6px;">Test This Link in Browser</a></p>
        <?php else: ?>
            <p style="color:#f59e0b;">⚠️ Live link is NOT configured</p>
            <p>If you want to use live mode, you need to add a live payment link.</p>
        <?php endif; ?>
    </div>

    <div class="box <?php echo $is_configured ? 'success' : 'error'; ?>">
        <h2>⚡ What Will Happen After Form Submission</h2>
        
        <?php if ($is_configured): ?>
            <p style="font-size:18px;color:#22c55e;"><strong>✅ Form will redirect to Stripe</strong></p>
            <p>When a vendor submits the final application form:</p>
            <ol>
                <li>Data and signature are saved ✅</li>
                <li>Plugin builds this Stripe URL: <code><?php echo htmlspecialchars($active_link); ?></code></li>
                <li>Browser redirects to Stripe ✅</li>
                <li>Vendor completes payment ✅</li>
                <li>Stripe redirects back to your site ✅</li>
            </ol>
            
            <p><strong>Expected Stripe URL will be:</strong></p>
            <pre><?php 
            $example_url = $active_link . '?prefilled_email=vendor@example.com&client_reference_id=app_123&success_url=' . urlencode('https://yoursite.com/final-vendor-application/?payment=success');
            echo htmlspecialchars($example_url); 
            ?></pre>
            
        <?php else: ?>
            <p style="font-size:18px;color:#ef4444;"><strong>❌ Form will show error or black screen</strong></p>
            <p>The plugin is in <strong><?php echo strtoupper($stripe_mode); ?> MODE</strong> but no payment link is configured for this mode.</p>
            
            <p><strong>What happens:</strong></p>
            <ol>
                <li>Data and signature are saved ✅</li>
                <li>Plugin tries to build Stripe URL ❌</li>
                <li>URL is empty (nothing configured) ❌</li>
                <li>Redirect fails ❌</li>
                <li><strong>Result: Black screen or error</strong></li>
            </ol>
            
            <div style="background:#fee2e2;border:2px solid #ef4444;padding:15px;border-radius:6px;margin-top:15px;">
                <h3 style="margin-top:0;color:#991b1b;">🚨 THIS IS YOUR PROBLEM!</h3>
                <p style="color:#991b1b;"><strong>Fix: Add a <?php echo $stripe_mode; ?> mode payment link in WordPress plugin settings</strong></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="box <?php echo $is_configured ? 'success' : 'error'; ?>">
        <h2>🎯 How to Fix</h2>
        
        <?php if (!$is_configured): ?>
            <h3>You need to add a Stripe Payment Link!</h3>
            
            <p><strong>Step 1: Create Payment Link in Stripe</strong></p>
            <ol>
                <li>Go to <a href="https://dashboard.stripe.com" target="_blank">Stripe Dashboard</a></li>
                <li>Switch to <strong><?php echo strtoupper($stripe_mode); ?> MODE</strong> (toggle in top right)</li>
                <li>Click <strong>Payment Links</strong> in left menu</li>
                <li>Click <strong>+ New</strong></li>
                <li>Product: "OHA Convention Vendor Booth"</li>
                <li>Price: $250.00</li>
                <li>Enable: <strong>"Customer can adjust quantity"</strong></li>
                <li>Click <strong>Create Link</strong></li>
                <li>Copy the Payment Link URL</li>
            </ol>
            
            <p><strong>Step 2: Add to WordPress</strong></p>
            <ol>
                <li>WordPress Admin → Vendor Applications → Settings</li>
                <li>Scroll to "Stripe Payment Settings"</li>
                <li>Paste URL into: <strong>"<?php echo ucfirst($stripe_mode); ?> Mode Payment Link URL"</strong></li>
                <li>Click <strong>Save Settings</strong></li>
            </ol>
            
            <p><strong>Step 3: Test</strong></p>
            <ol>
                <li>Refresh this page - should show ✅ configured</li>
                <li>Test the vendor form - should redirect to Stripe</li>
            </ol>
            
        <?php else: ?>
            <p style="color:#22c55e;"><strong>✅ Everything looks good!</strong></p>
            <p>If you're still getting a black screen after form submission, the issue is something else:</p>
            <ul>
                <li>Headers already sent (output buffering issue)</li>
                <li>PHP error during redirect</li>
                <li>Theme/plugin conflict</li>
            </ul>
            
            <p><strong>Enable debug logging to see what's happening:</strong></p>
            <p>Edit <code>wp-config.php</code> and add:</p>
            <pre>define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);</pre>
            <p>Then check <code>/wp-content/debug.log</code> after submitting form.</p>
        <?php endif; ?>
    </div>

    <div class="box">
        <h2>📋 Summary</h2>
        <table style="width:100%;border-collapse:collapse;">
            <tr style="border-bottom:1px solid #e5e7eb;">
                <td style="padding:10px;font-weight:bold;">Current Mode:</td>
                <td style="padding:10px;"><code><?php echo htmlspecialchars($stripe_mode); ?></code></td>
            </tr>
            <tr style="border-bottom:1px solid #e5e7eb;">
                <td style="padding:10px;font-weight:bold;">Test Link Configured:</td>
                <td style="padding:10px;"><?php echo !empty($stripe_test) ? '✅ YES' : '❌ NO'; ?></td>
            </tr>
            <tr style="border-bottom:1px solid #e5e7eb;">
                <td style="padding:10px;font-weight:bold;">Live Link Configured:</td>
                <td style="padding:10px;"><?php echo !empty($stripe_live) ? '✅ YES' : '❌ NO'; ?></td>
            </tr>
            <tr style="border-bottom:1px solid #e5e7eb;">
                <td style="padding:10px;font-weight:bold;">Active Link for Current Mode:</td>
                <td style="padding:10px;"><?php echo $is_configured ? '✅ CONFIGURED' : '❌ NOT CONFIGURED'; ?></td>
            </tr>
            <tr>
                <td style="padding:10px;font-weight:bold;">Form Will Redirect to Stripe:</td>
                <td style="padding:10px;font-size:18px;"><?php echo $is_configured ? '<span style="color:#22c55e;">✅ YES</span>' : '<span style="color:#ef4444;">❌ NO</span>'; ?></td>
            </tr>
        </table>
    </div>

    <div class="box">
        <h2>🐛 Debug Information</h2>
        <pre><?php
echo "Database: " . htmlspecialchars($db_name) . "\n";
echo "Table Prefix: " . htmlspecialchars($prefix) . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "\n";
echo "Stripe Settings\n";
echo "===============\n";
echo "Mode: " . htmlspecialchars($stripe_mode) . "\n";
echo "Test Link: " . (!empty($stripe_test) ? 'CONFIGURED (' . strlen($stripe_test) . ' chars)' : 'EMPTY') . "\n";
echo "Live Link: " . (!empty($stripe_live) ? 'CONFIGURED (' . strlen($stripe_live) . ' chars)' : 'EMPTY') . "\n";
echo "Active Link: " . ($is_configured ? 'CONFIGURED' : 'NOT CONFIGURED') . "\n";
echo "\n";
echo "Expected Behavior: " . ($is_configured ? 'Redirect to Stripe after form submission' : 'Error/black screen after form submission') . "\n";
        ?></pre>
    </div>

    <p style="text-align:center;margin-top:40px;">
        <a href="javascript:location.reload();" style="display:inline-block;background:#6b7280;color:white;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:bold;">Refresh This Page</a>
    </p>
</body>
</html>
