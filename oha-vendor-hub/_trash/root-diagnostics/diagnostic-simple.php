<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>OHA Emergency Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; margin: 10px 0; border: 2px solid #333; border-radius: 8px; }
        .success { border-color: #22c55e; background: #f0fdf4; }
        .error { border-color: #ef4444; background: #fef2f2; }
        h2 { margin-top: 0; }
        pre { background: #1f2937; color: #f3f4f6; padding: 15px; border-radius: 6px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 OHA Emergency Diagnostic</h1>
    <p><strong>Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>

    <!-- TEST 1: PHP is working -->
    <div class="box success">
        <h2>✅ Test 1: PHP Is Working</h2>
        <p>If you see this box, PHP is executing on your server.</p>
        <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
    </div>

    <!-- TEST 2: File System Access -->
    <div class="box <?php echo is_readable('wp-config.php') ? 'success' : 'error'; ?>">
        <h2>Test 2: WordPress Files</h2>
        <p><strong>Can read wp-config.php:</strong> <?php echo is_readable('wp-config.php') ? '✅ YES' : '❌ NO'; ?></p>
        <p><strong>Can read wp-load.php:</strong> <?php echo is_readable('wp-load.php') ? '✅ YES' : '❌ NO'; ?></p>
        <p><strong>Current Directory:</strong> <?php echo getcwd(); ?></p>
        <?php if (!is_readable('wp-config.php')): ?>
            <p class="error"><strong>⚠️ Problem:</strong> This diagnostic file is not in the WordPress root directory!</p>
            <p><strong>Action:</strong> Move diagnostic.php to the same folder as wp-config.php</p>
        <?php endif; ?>
    </div>

    <!-- TEST 3: Plugin Folder -->
    <div class="box <?php echo is_dir('wp-content/plugins/oha-vendor-hub-v1.6') ? 'success' : 'error'; ?>">
        <h2>Test 3: Plugin Files</h2>
        <p><strong>Plugin folder exists:</strong> <?php echo is_dir('wp-content/plugins/oha-vendor-hub-v1.6') ? '✅ YES' : '❌ NO'; ?></p>
        <?php if (is_dir('wp-content/plugins')): ?>
            <p><strong>Plugins in folder:</strong></p>
            <ul>
            <?php
            $plugins = scandir('wp-content/plugins');
            foreach ($plugins as $plugin) {
                if ($plugin != '.' && $plugin != '..' && is_dir('wp-content/plugins/' . $plugin)) {
                    $match = strpos($plugin, 'oha') !== false || strpos($plugin, 'vendor') !== false;
                    echo '<li' . ($match ? ' style="font-weight:bold;color:#22c55e;"' : '') . '>' . htmlspecialchars($plugin) . ($match ? ' ⬅️ THIS ONE' : '') . '</li>';
                }
            }
            ?>
            </ul>
        <?php else: ?>
            <p class="error">⚠️ Cannot read plugins folder</p>
        <?php endif; ?>
    </div>

    <!-- TEST 4: Load WordPress (Carefully) -->
    <div class="box">
        <h2>Test 4: Loading WordPress</h2>
        <?php
        if (is_readable('wp-load.php')) {
            echo '<p>Attempting to load WordPress...</p>';
            
            // Capture errors
            ob_start();
            $wp_loaded = false;
            try {
                require_once('wp-load.php');
                $wp_loaded = true;
            } catch (Exception $e) {
                echo '<p class="error">❌ Exception: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            $load_errors = ob_get_clean();
            
            if ($wp_loaded && function_exists('get_bloginfo')) {
                echo '<p class="success">✅ WordPress loaded successfully!</p>';
                echo '<p><strong>Site Name:</strong> ' . get_bloginfo('name') . '</p>';
                echo '<p><strong>Site URL:</strong> ' . get_bloginfo('url') . '</p>';
                echo '<p><strong>WordPress Version:</strong> ' . get_bloginfo('version') . '</p>';
            } else {
                echo '<p class="error">❌ WordPress did not load properly</p>';
                if (!empty($load_errors)) {
                    echo '<pre>' . htmlspecialchars($load_errors) . '</pre>';
                }
            }
        } else {
            echo '<p class="error">❌ Cannot find wp-load.php</p>';
        }
        ?>
    </div>

    <!-- TEST 5: Plugin Active Check (if WP loaded) -->
    <?php if (isset($wp_loaded) && $wp_loaded && function_exists('is_plugin_active')): ?>
    <div class="box <?php echo is_plugin_active('oha-vendor-hub-v1.6/oha-vendor-hub.php') ? 'success' : 'error'; ?>">
        <h2>Test 5: Plugin Status</h2>
        <p><strong>Plugin Active:</strong> 
        <?php 
        if (is_plugin_active('oha-vendor-hub-v1.6/oha-vendor-hub.php')) {
            echo '✅ YES';
        } else {
            echo '❌ NO - Plugin is NOT activated!';
            echo '<p><strong>Action Required:</strong> Go to WordPress Admin → Plugins → Activate "OHA Vendor Hub"</p>';
        }
        ?>
        </p>
    </div>

    <!-- TEST 6: Shortcode Check -->
    <div class="box <?php echo shortcode_exists('oha_vendor_final_form') ? 'success' : 'error'; ?>">
        <h2>Test 6: Shortcode Registration</h2>
        <p><strong>Main shortcode exists:</strong> <?php echo shortcode_exists('oha_vendor_final_form') ? '✅ YES' : '❌ NO'; ?></p>
        <p><strong>Test shortcode exists:</strong> <?php echo shortcode_exists('oha_test') ? '✅ YES' : '❌ NO'; ?></p>
        
        <?php if (!shortcode_exists('oha_vendor_final_form')): ?>
            <p class="error"><strong>⚠️ Problem:</strong> Shortcodes not registered!</p>
            <p><strong>This means either:</strong></p>
            <ul>
                <li>Plugin is not activated (check Test 5 above)</li>
                <li>Plugin files are corrupted</li>
                <li>PHP version too old (need 7.4+)</li>
            </ul>
        <?php endif; ?>
    </div>

    <!-- TEST 7: Test Shortcode Output -->
    <?php if (shortcode_exists('oha_test')): ?>
    <div class="box success">
        <h2>Test 7: Shortcode Output</h2>
        <p>Testing [oha_test] shortcode...</p>
        <div style="border:3px solid #6366f1;padding:10px;margin:10px 0;">
            <?php echo do_shortcode('[oha_test]'); ?>
        </div>
        <p><strong>✅ If you see red box above, shortcodes work!</strong></p>
    </div>
    <?php endif; ?>

    <!-- TEST 8: Theme Info -->
    <div class="box">
        <h2>Test 8: Theme</h2>
        <p><strong>Active Theme:</strong> <?php echo wp_get_theme()->get('Name'); ?></p>
        <p><strong>Theme Version:</strong> <?php echo wp_get_theme()->get('Version'); ?></p>
        <p><em>Kadence theme sometimes filters shortcode output. If other tests pass but page is still blank, this might be the cause.</em></p>
    </div>

    <?php endif; ?>

    <!-- SUMMARY -->
    <div class="box" style="background:#fffbeb;border-color:#f59e0b;">
        <h2>📋 Summary</h2>
        <?php if (isset($wp_loaded) && $wp_loaded): ?>
            <?php if (shortcode_exists('oha_vendor_final_form')): ?>
                <p style="color:#22c55e;font-weight:bold;font-size:18px;">✅ Everything looks good! The plugin should work.</p>
                <p><strong>Next steps:</strong></p>
                <ol>
                    <li>Go to your final vendor application page</li>
                    <li>View page source (right-click → View Page Source)</li>
                    <li>Search for: <code>&lt;!-- OHA VENDOR FORM START --&gt;</code></li>
                    <li>If you find it, the shortcode IS running</li>
                    <li>If you don't find it, the page doesn't have the shortcode</li>
                </ol>
            <?php else: ?>
                <p style="color:#ef4444;font-weight:bold;font-size:18px;">❌ Plugin is installed but NOT ACTIVATED</p>
                <p><strong>FIX:</strong> WordPress Admin → Plugins → Click "Activate" under OHA Vendor Hub</p>
            <?php endif; ?>
        <?php else: ?>
            <p style="color:#ef4444;font-weight:bold;font-size:18px;">❌ WordPress not loading properly</p>
            <p><strong>Possible causes:</strong></p>
            <ul>
                <li>This diagnostic.php file is in wrong location (must be in WordPress root)</li>
                <li>File permissions issue</li>
                <li>WordPress installation is broken</li>
            </ul>
        <?php endif; ?>
    </div>

    <!-- DEBUG INFO -->
    <div class="box">
        <h2>🐛 Copy This If Requesting Support</h2>
        <pre><?php
        echo "OHA Vendor Hub Diagnostic\n";
        echo "========================\n";
        echo "Date: " . date('Y-m-d H:i:s') . "\n";
        echo "PHP Version: " . phpversion() . "\n";
        echo "Current Directory: " . getcwd() . "\n";
        echo "wp-config.php exists: " . (is_readable('wp-config.php') ? 'YES' : 'NO') . "\n";
        echo "Plugin folder exists: " . (is_dir('wp-content/plugins/oha-vendor-hub-v1.6') ? 'YES' : 'NO') . "\n";
        if (isset($wp_loaded) && $wp_loaded) {
            echo "WordPress loaded: YES\n";
            echo "WordPress version: " . get_bloginfo('version') . "\n";
            echo "Site URL: " . get_bloginfo('url') . "\n";
            echo "Theme: " . wp_get_theme()->get('Name') . "\n";
            echo "Plugin active: " . (is_plugin_active('oha-vendor-hub-v1.6/oha-vendor-hub.php') ? 'YES' : 'NO') . "\n";
            echo "Shortcode exists: " . (shortcode_exists('oha_vendor_final_form') ? 'YES' : 'NO') . "\n";
        } else {
            echo "WordPress loaded: NO\n";
        }
        ?></pre>
    </div>

</body>
</html>
