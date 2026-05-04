<?php
/**
 * PWA Debug/Status Shortcode
 * 
 * Use [oha_pwa_debug] on any page to see PWA status
 */

if (!defined('ABSPATH')) exit;

add_shortcode('oha_pwa_debug', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return '';
    }
    ob_start();
    ?>
    <div class="oha-pwa-debug" style="background: #1a1a1a; border: 2px solid #AD3119; padding: 20px; margin: 20px 0; font-family: monospace; color: #fff; border-radius: 8px;">
        <h2 style="margin-top: 0; color: #AD3119;">🔍 PWA Debug Information</h2>
        
        <h3 style="color: #fff; border-bottom: 1px solid #333; padding-bottom: 8px;">Plugin Version:</h3>
        <ul style="list-style: none; padding-left: 0;">
            <li><strong>PWA Core Version:</strong> <?php echo defined('OHA_PWA_VERSION') ? OHA_PWA_VERSION : 'Unknown'; ?></li>
            <li><strong>Plugin Active:</strong> <?php echo class_exists('OHA_Convention_PWA_Core') ? '✅ Yes' : '❌ No'; ?></li>
        </ul>
        
        <h3 style="color: #fff; border-bottom: 1px solid #333; padding-bottom: 8px;">Current Page Info:</h3>
        <ul style="list-style: none; padding-left: 0;">
            <li><strong>URL:</strong> <?php echo esc_html($_SERVER['REQUEST_URI']); ?></li>
            <li><strong>Post Type:</strong> <?php echo esc_html(get_post_type()); ?></li>
            <?php if (is_singular()): ?>
            <li><strong>Post Name:</strong> <?php echo esc_html(get_post()->post_name); ?></li>
            <?php endif; ?>
        </ul>
        
        <h3 style="color: #fff; border-bottom: 1px solid #333; padding-bottom: 8px;">PWA Status:</h3>
        <ul style="list-style: none; padding-left: 0;">
            <li><strong>Is Convention Page:</strong> <span id="is-convention-page">Checking...</span></li>
            <li><strong>Service Worker:</strong> <span id="sw-status">Checking...</span></li>
            <li><strong>Install Available:</strong> <span id="install-status">Checking...</span></li>
            <li><strong>Is Installed:</strong> <span id="installed-status">Checking...</span></li>
            <li><strong>App Mode Active:</strong> <span id="app-mode-status">Checking...</span></li>
            <li><strong>Session Storage:</strong> <span id="session-storage">Checking...</span></li>
        </ul>
        
        <h3 style="color: #fff; border-bottom: 1px solid #333; padding-bottom: 8px;">Browser Support:</h3>
        <ul style="list-style: none; padding-left: 0;">
            <li><strong>Service Worker Support:</strong> <span id="sw-support">Checking...</span></li>
            <li><strong>Manifest Support:</strong> <span id="manifest-support">Checking...</span></li>
            <li><strong>Notification Support:</strong> <span id="notification-support">Checking...</span></li>
            <li><strong>Online Status:</strong> <span id="online-status">Checking...</span></li>
            <li><strong>Screen Size:</strong> <span id="screen-size">Checking...</span></li>
        </ul>
        
        <h3 style="color: #fff; border-bottom: 1px solid #333; padding-bottom: 8px;">Push Notifications (Perfecty):</h3>
        <ul style="list-style: none; padding-left: 0;">
            <?php
            $perfecty_opts = get_option('perfecty_push', []);
            global $wpdb;
            $pt    = $wpdb->prefix . 'perfecty_push_users';
            $psubs = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $pt)) === $pt)
                     ? (int) $wpdb->get_var("SELECT COUNT(*) FROM $pt") : 0;
            ?>
            <li><strong>Perfecty Plugin Active:</strong> <?php echo class_exists('Perfecty_Push_Lib_Push_Server') ? '✅ Yes' : '❌ No'; ?></li>
            <li><strong>VAPID Key Configured:</strong> <?php echo !empty($perfecty_opts['vapid_public_key']) ? '✅ Yes' : '❌ No'; ?></li>
            <li><strong>Subscribers:</strong> <?php echo $psubs; ?></li>
            <li><strong>Current Domain:</strong> <span id="current-domain">Checking...</span></li>
            <li><strong>Perfecty SW Scope:</strong> <span id="perfecty-sw-scope">Checking...</span></li>
            <li><strong>Proxy SW (/perfecty-push-sw.js):</strong> <span id="proxy-sw-fetch">Checking...</span></li>
            <li><strong>Permission Status:</strong> <span id="notification-permission">Checking...</span></li>
            <li><strong>SW Subscriptions (per scope):</strong> <span id="subscription-id">Checking...</span></li>
        </ul>
        
        <h3 style="color: #fff; border-bottom: 1px solid #333; padding-bottom: 8px;">Actions:</h3>
        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
            <button id="test-install" style="padding: 8px 16px; background: #AD3119; color: white; border: none; border-radius: 6px; cursor: pointer;">Test Install Prompt</button>
            <button id="check-sw" style="padding: 8px 16px; background: #4CAF50; color: white; border: none; border-radius: 6px; cursor: pointer;">Check Service Worker</button>
            <button id="clear-cache" style="padding: 8px 16px; background: #f44336; color: white; border: none; border-radius: 6px; cursor: pointer;">Clear PWA Cache</button>
            <button id="reset-app-mode" style="padding: 8px 16px; background: #FF9800; color: white; border: none; border-radius: 6px; cursor: pointer;">Reset App Mode</button>
        </div>
        
        <div style="margin-top: 16px; padding: 12px; background: rgba(173, 49, 25, 0.2); border-radius: 6px; font-size: 12px;">
            <strong>💡 Troubleshooting Tips:</strong><br>
            • If tabs not showing: Check "App Mode Active" and "Session Storage"<br>
            • If black screen: Reinstall PWA completely<br>
            • If updates not showing: Clear cache, wait 10 min, reinstall<br>
            • If favorites broken: Check browser console for errors
        </div>
    </div>
    
    <script>
    (function() {
        // Check if this is a convention page (client-side)
        const url = window.location.pathname;
        const isConventionPage = url.includes('convention') || 
                                 url.includes('session') || 
                                 url.includes('speaker') || 
                                 url.includes('vendor');
        document.getElementById('is-convention-page').textContent = isConventionPage ? '✅ Yes' : '❌ No';
        
        // Check app mode status
        const hasAppMode = document.body.classList.contains('oha-app-mode') || 
                          document.documentElement.classList.contains('oha-app-mode');
        document.getElementById('app-mode-status').textContent = hasAppMode ? '✅ Active' : '❌ Inactive';
        
        // Check session storage
        const sessionMode = sessionStorage.getItem('oha-app-mode');
        document.getElementById('session-storage').textContent = 
            sessionMode === 'enabled' ? '✅ Enabled' : '❌ Not Set';
        
        // Check online status
        document.getElementById('online-status').textContent = 
            navigator.onLine ? '✅ Online' : '⚠️ Offline';
        
        // Check screen size
        document.getElementById('screen-size').textContent = 
            window.innerWidth + 'x' + window.innerHeight + 
            (window.innerWidth <= 768 ? ' (Mobile)' : ' (Desktop)');
        
        // Check Service Worker support
        document.getElementById('sw-support').textContent = 
            'serviceWorker' in navigator ? '✅ Supported' : '❌ Not Supported';
        
        // Check Manifest support
        document.getElementById('manifest-support').textContent = 
            'onbeforeinstallprompt' in window ? '✅ Supported' : '❌ Not Supported (might be iOS)';
        
        // Check Notification support
        document.getElementById('notification-support').textContent = 
            'Notification' in window ? '✅ Supported' : '❌ Not Supported';
        
        // Check if installed
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches || 
                           window.navigator.standalone === true;
        document.getElementById('installed-status').textContent = 
            isStandalone ? '✅ Yes (Running as App)' : '❌ No (Running in Browser)';
        
        // Check Service Worker status
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistration().then(function(reg) {
                if (reg) {
                    document.getElementById('sw-status').textContent = 
                        '✅ Registered (Scope: ' + reg.scope + ')';
                } else {
                    document.getElementById('sw-status').textContent = 
                        '❌ Not Registered';
                }
            });
        }
        
        // Check install prompt availability
        let installPromptAvailable = false;
        window.addEventListener('beforeinstallprompt', function(e) {
            installPromptAvailable = true;
            document.getElementById('install-status').textContent = '✅ Available';
        });
        
        setTimeout(function() {
            if (!installPromptAvailable && !isStandalone) {
                document.getElementById('install-status').textContent = 
                    '❌ Not Available (might be iOS or already installed)';
            }
        }, 2000);
        
        // Test install button
        document.getElementById('test-install').addEventListener('click', function() {
            if (typeof window.ohaPWAInstall === 'function') {
                window.ohaPWAInstall();
            } else {
                alert('Install function not available. Make sure PWA scripts are loaded.');
            }
        });
        
        // Check SW button
        document.getElementById('check-sw').addEventListener('click', function() {
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.getRegistrations().then(function(registrations) {
                    alert('Service Workers:\n' + 
                          registrations.length + ' registered\n\n' +
                          registrations.map(r => 'Scope: ' + r.scope).join('\n'));
                });
            }
        });
        
        // Clear cache button
        document.getElementById('clear-cache').addEventListener('click', function() {
            if (typeof window.ohaPWAClearCache === 'function') {
                window.ohaPWAClearCache().then(function() {
                    alert('✅ Cache cleared! Refresh the page.');
                });
            } else {
                alert('Clear cache function not available.');
            }
        });
        
        // Reset app mode button
        document.getElementById('reset-app-mode').addEventListener('click', function() {
            sessionStorage.removeItem('oha-app-mode');
            document.body.classList.remove('oha-app-mode');
            document.documentElement.classList.remove('oha-app-mode');
            alert('✅ App mode reset! Refresh to test again.');
            location.reload();
        });
        
        // Push Notifications (Perfecty) — check permission + SW push subscription
        setTimeout(function() {
            document.getElementById('current-domain').textContent = window.location.hostname;

            // PerfectyPushOptions scope
            if (typeof window.PerfectyPushOptions !== 'undefined' && window.PerfectyPushOptions.serviceWorkerScope) {
                document.getElementById('perfecty-sw-scope').textContent = window.PerfectyPushOptions.serviceWorkerScope;
            } else {
                document.getElementById('perfecty-sw-scope').textContent = '⚠️ PerfectyPushOptions not loaded on this page';
            }

            // HTTP fetch check for proxy SW file
            fetch('/perfecty-push-sw.js', { method: 'HEAD' })
                .then(function(r) {
                    document.getElementById('proxy-sw-fetch').textContent =
                        r.ok ? '✅ HTTP ' + r.status + ' OK' : '❌ HTTP ' + r.status + ' ' + r.statusText;
                })
                .catch(function(err) {
                    document.getElementById('proxy-sw-fetch').textContent = '❌ Fetch failed: ' + err.message;
                });

            // Browser permission
            if ('Notification' in window) {
                var perm = Notification.permission;
                document.getElementById('notification-permission').textContent =
                    perm === 'granted' ? '✅ Granted' :
                    perm === 'denied'  ? '❌ Denied'  :
                                         '⚠️ Default (not asked yet)';
            } else {
                document.getElementById('notification-permission').textContent = '❌ Not supported';
            }

            // SW push subscription — per-scope breakdown
            if ('serviceWorker' in navigator && 'PushManager' in window) {
                navigator.serviceWorker.getRegistrations().then(function(regs) {
                    if (!regs.length) {
                        document.getElementById('subscription-id').textContent = '❌ No SW registered';
                        return;
                    }
                    var checks = regs.map(function(reg) {
                        return reg.pushManager.getSubscription().then(function(sub) {
                            var scopeShort = reg.scope.replace(window.location.origin, '');
                            if (sub) {
                                return scopeShort + ': ✅ …' + sub.endpoint.slice(-24);
                            } else {
                                return scopeShort + ': ⚠️ no sub';
                            }
                        }).catch(function() {
                            return reg.scope.replace(window.location.origin, '') + ': ❌ error';
                        });
                    });
                    Promise.all(checks).then(function(results) {
                        document.getElementById('subscription-id').textContent = results.join(' | ');
                    });
                });
            } else {
                document.getElementById('subscription-id').textContent = '❌ Push API not supported';
            }
        }, 1000);
    })();
    </script>
    <?php
    return ob_get_clean();
});
