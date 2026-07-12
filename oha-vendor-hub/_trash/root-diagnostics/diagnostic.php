<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>OHA Vendor Hub Diagnostics</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .diagnostic-box {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border: 2px solid #333;
            border-radius: 8px;
        }
        .success { border-color: #22c55e; background: #f0fdf4; }
        .error { border-color: #ef4444; background: #fef2f2; }
        .warning { border-color: #f59e0b; background: #fffbeb; }
        h2 { margin-top: 0; }
        code { background: #e5e7eb; padding: 2px 6px; border-radius: 3px; }
        pre { background: #1f2937; color: #f3f4f6; padding: 15px; border-radius: 6px; overflow-x: auto; }
        .button { display: inline-block; padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px; margin: 5px; }
        .button:hover { background: #2563eb; }
    </style>
</head>
<body>
    <h1>🔍 OHA Vendor Hub - Diagnostic Tool</h1>
    <p><strong>Created:</strong> <?php echo current_time('mysql'); ?></p>

    <?php
    // Check if plugin is active
    $plugin_active = is_plugin_active('oha-vendor-hub-v1.6/oha-vendor-hub.php') || 
                     function_exists('oha_vhcpt_render_states') ||
                     shortcode_exists('oha_vendor_final_form');
    ?>

    <div class="diagnostic-box <?php echo $plugin_active ? 'success' : 'error'; ?>">
        <h2>1. Plugin Status</h2>
        <p><strong>Plugin Active:</strong> <?php echo $plugin_active ? '✅ YES' : '❌ NO'; ?></p>
        <?php if (!$plugin_active): ?>
            <p><strong>Action Required:</strong> Activate the plugin in WordPress Admin → Plugins</p>
        <?php endif; ?>
    </div>

    <div class="diagnostic-box <?php echo shortcode_exists('oha_vendor_final_form') ? 'success' : 'error'; ?>">
        <h2>2. Shortcode Registration</h2>
        <p><strong>Shortcode Exists:</strong> <?php echo shortcode_exists('oha_vendor_final_form') ? '✅ YES' : '❌ NO'; ?></p>
        <p><strong>Shortcode to use:</strong> <code>[oha_vendor_final_form]</code></p>
        <?php if (shortcode_exists('oha_test')): ?>
            <p><strong>Test shortcode available:</strong> <code>[oha_test]</code></p>
        <?php endif; ?>
    </div>

    <div class="diagnostic-box">
        <h2>3. Environment</h2>
        <ul>
            <li><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?> <?php echo version_compare(PHP_VERSION, '7.4.0', '>=') ? '✅' : '❌ Need 7.4+'; ?></li>
            <li><strong>WordPress Version:</strong> <?php echo get_bloginfo('version'); ?></li>
            <li><strong>Theme:</strong> <?php echo wp_get_theme()->get('Name'); ?> v<?php echo wp_get_theme()->get('Version'); ?></li>
            <li><strong>Site URL:</strong> <?php echo home_url(); ?></li>
            <li><strong>Admin URL:</strong> <?php echo admin_url(); ?></li>
        </ul>
    </div>

    <div class="diagnostic-box">
        <h2>4. Page Check</h2>
        <?php
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            's' => '[oha_vendor_final_form]',
            'posts_per_page' => -1
        ]);
        
        if (!empty($pages)):
        ?>
            <p><strong>Found <?php echo count($pages); ?> page(s) with the shortcode:</strong></p>
            <ul>
            <?php foreach ($pages as $page): ?>
                <li>
                    <strong><?php echo esc_html($page->post_title); ?></strong><br>
                    <a href="<?php echo get_permalink($page->ID); ?>" class="button" target="_blank">View Page</a>
                    <a href="<?php echo get_edit_post_link($page->ID); ?>" class="button">Edit Page</a>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="error">⚠️ No pages found with <code>[oha_vendor_final_form]</code> shortcode</p>
            <p><strong>Action Required:</strong> Create a page and add the shortcode</p>
        <?php endif; ?>
    </div>

    <div class="diagnostic-box">
        <h2>5. Stripe Configuration</h2>
        <?php
        $stripe_mode = get_option('oha_vhcpt_stripe_mode', 'test');
        $stripe_test = get_option('oha_vhcpt_stripe_link_test', '');
        $stripe_live = get_option('oha_vhcpt_stripe_link_live', '');
        ?>
        <ul>
            <li><strong>Mode:</strong> <?php echo esc_html($stripe_mode); ?></li>
            <li><strong>Test Link:</strong> <?php echo !empty($stripe_test) ? '✅ Configured' : '❌ Not set'; ?></li>
            <li><strong>Live Link:</strong> <?php echo !empty($stripe_live) ? '✅ Configured' : '❌ Not set'; ?></li>
        </ul>
        <?php if (empty($stripe_test) && empty($stripe_live)): ?>
            <p class="warning">⚠️ Stripe not configured. Form will show error after submission.</p>
            <a href="<?php echo admin_url('edit.php?post_type=vendor_application&page=oha-vhcpt-settings'); ?>" class="button">Configure Stripe</a>
        <?php endif; ?>
    </div>

    <div class="diagnostic-box">
        <h2>6. Quick Tests</h2>
        
        <h3>Test 1: Simple Output</h3>
        <div style="background:#e0e7ff;padding:10px;border:2px solid #6366f1;margin:10px 0;">
            <?php echo "If you see this, PHP output is working! ✅"; ?>
        </div>

        <h3>Test 2: Shortcode Test (if available)</h3>
        <?php if (shortcode_exists('oha_test')): ?>
            <?php echo do_shortcode('[oha_test]'); ?>
        <?php else: ?>
            <p>❌ Test shortcode not registered</p>
        <?php endif; ?>

        <h3>Test 3: Full Form Shortcode</h3>
        <?php if (shortcode_exists('oha_vendor_final_form')): ?>
            <p><a href="<?php echo home_url('/final-vendor-application/?debug=1'); ?>" class="button" target="_blank">Test Form Page with Debug</a></p>
            <p><em>Add <code>?debug=1</code> to URL for debug info (admins only)</em></p>
        <?php else: ?>
            <p>❌ Form shortcode not registered</p>
        <?php endif; ?>
    </div>

    <div class="diagnostic-box">
        <h2>7. Debug Information</h2>
        <p><strong>Copy this info if requesting support:</strong></p>
        <pre><?php
        echo "Plugin: OHA Vendor Hub v1.6.0\n";
        echo "WordPress: " . get_bloginfo('version') . "\n";
        echo "PHP: " . PHP_VERSION . "\n";
        echo "Theme: " . wp_get_theme()->get('Name') . " v" . wp_get_theme()->get('Version') . "\n";
        echo "Site URL: " . home_url() . "\n";
        echo "Shortcode Exists: " . (shortcode_exists('oha_vendor_final_form') ? 'YES' : 'NO') . "\n";
        echo "Stripe Mode: " . $stripe_mode . "\n";
        echo "Stripe Configured: " . (!empty($stripe_test) || !empty($stripe_live) ? 'YES' : 'NO') . "\n";
        echo "Time: " . current_time('mysql') . "\n";
        ?></pre>
    </div>

    <div class="diagnostic-box">
        <h2>8. Next Steps</h2>
        <ol>
            <li>Verify all checks above are ✅ green</li>
            <li>If plugin not active, activate it</li>
            <li>If shortcode not registered, deactivate and reactivate plugin</li>
            <li>If page not found, create page with shortcode</li>
            <li>Clear ALL caches (WordPress, server, browser)</li>
            <li>Test the form page</li>
            <li>If still issues, share the debug info above with support</li>
        </ol>
    </div>

    <p style="text-align:center;margin-top:40px;">
        <a href="<?php echo admin_url(); ?>" class="button">Back to WordPress Admin</a>
    </p>
</body>
</html>
