<?php
/**
 * PWA Notifications Admin Page (v3.7.0 — Perfecty branch)
 *
 * Push notifications are now sent via Perfecty Push (self-hosted VAPID).
 * This page shows install + subscriber stats and links to the Perfecty Push
 * admin panel where notification sends are composed.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OHA_PWA_Notifications_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_admin_menu' ] );
    }

    public static function add_admin_menu() {
        add_submenu_page(
            'options-general.php',
            'Push Notifications',
            'Push Notifications',
            'edit_pages',
            'oha-pwa-notifications',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function render_page() {
        if ( ! current_user_can( 'edit_pages' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Access denied. Board members only.' );
        }

        $perfecty_opts  = get_option( 'perfecty_push', [] );
        $perfecty_ready = ! empty( $perfecty_opts['vapid_public_key'] );

        // URL to Perfecty send-notification page (if plugin is active)
        $perfecty_send_url = admin_url( 'admin.php?page=perfecty-push-notifications-send-notification' );

        $rest_nonce = wp_create_nonce( 'wp_rest' );
        $rest_url   = rest_url( 'oha/v1/' );
        ?>
        <div class="wrap">
            <h1>📢 Push Notifications</h1>

            <?php if ( ! $perfecty_ready ) : ?>
                <div class="notice notice-error">
                    <p>
                        <strong>Perfecty Push is not configured.</strong><br>
                        Install and activate the <em>Perfecty Push Notifications</em> plugin, then generate VAPID keys
                        under <strong>Perfecty Push → Settings</strong>. Once configured, return here to see stats.
                    </p>
                </div>
            <?php else : ?>

                <!-- ── Stats panel ──────────────────────────────────────────────── -->
                <div id="oha-notif-stats" style="
                    display:flex;gap:24px;align-items:center;flex-wrap:wrap;
                    background:#fff;border:1px solid #ddd;border-radius:8px;
                    padding:16px 24px;margin:16px 0;max-width:800px;">
                    <div class="oha-stat-box" style="text-align:center;min-width:100px;">
                        <div id="stat-installs" style="font-size:2em;font-weight:700;color:#1d72aa;">—</div>
                        <div style="color:#666;font-size:.85em;">📱 PWA Installs</div>
                    </div>
                    <div style="width:1px;height:48px;background:#ddd;"></div>
                    <div class="oha-stat-box" style="text-align:center;min-width:100px;">
                        <div id="stat-active" style="font-size:2em;font-weight:700;color:#46b450;">—</div>
                        <div style="color:#666;font-size:.85em;">📱 Active (30d)</div>
                    </div>
                    <div style="width:1px;height:48px;background:#ddd;"></div>
                    <div class="oha-stat-box" style="text-align:center;min-width:100px;">
                        <div id="stat-subscribers" style="font-size:2em;font-weight:700;color:#dc3232;">—</div>
                        <div style="color:#666;font-size:.85em;">🔔 Push Subscribers</div>
                    </div>
                    <div style="width:1px;height:48px;background:#ddd;"></div>
                    <div class="oha-stat-box" style="text-align:center;font-size:.8em;color:#888;line-height:1.6;">
                        <div id="stat-platforms">Loading…</div>
                    </div>
                </div>

                <!-- ── Send button ──────────────────────────────────────────────── -->
                <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:24px;margin:16px 0;max-width:800px;">
                    <h2 style="margin-top:0;">Send a Notification</h2>
                    <p>
                        Push notifications are sent through <strong>Perfecty Push</strong>. Use its built-in send form
                        to compose and deliver messages to all subscribed devices.
                    </p>
                    <a href="<?php echo esc_url( $perfecty_send_url ); ?>"
                       class="button button-primary button-large">
                        📤 Go to Perfecty Push → Send Notification
                    </a>
                </div>

                <!-- ── Tips ────────────────────────────────────────────────────── -->
                <div style="margin-top:20px;background:#fff3cd;padding:16px;border-radius:8px;border-left:4px solid #ffc107;max-width:800px;">
                    <strong>⚠️ Reminders before sending:</strong>
                    <ul style="margin:8px 0;">
                        <li>Notifications are sent <strong>immediately</strong> to all subscribed users</li>
                        <li>Double-check your message before sending</li>
                        <li>Avoid sending more than 3–4 notifications per day</li>
                    </ul>
                </div>

                <div style="margin-top:20px;background:#e7f3ff;padding:16px;border-radius:8px;border-left:4px solid #2196F3;max-width:800px;">
                    <strong>💡 Example messages:</strong>
                    <ul style="margin:8px 0;">
                        <li><strong>Schedule change:</strong> "Room A moved to 2nd floor"</li>
                        <li><strong>Upcoming:</strong> "Q&amp;A with Tom Savini starts in 15 min!"</li>
                        <li><strong>Closing:</strong> "Vendor Hall closing in 30 minutes"</li>
                        <li><strong>Emergency:</strong> "Weather delay — event starts at 3 PM"</li>
                    </ul>
                </div>

                <script>
                (function($) {
                    var restUrl   = <?php echo wp_json_encode( $rest_url ); ?>;
                    var restNonce = <?php echo wp_json_encode( $rest_nonce ); ?>;

                    $.ajax({
                        url: restUrl + 'notifications/stats',
                        method: 'GET',
                        beforeSend: function(xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', restNonce);
                        },
                        success: function(data) {
                            $('#stat-installs').text(data.installs || 0);
                            $('#stat-active').text(data.active_installs || 0);
                            $('#stat-subscribers').text(data.subscribers || 0);
                            var p = data.platforms || {};
                            $('#stat-platforms').html(
                                '🍎 iOS: '     + (p.ios     || 0) + '<br>' +
                                '🤖 Android: ' + (p.android || 0) + '<br>' +
                                '💻 Other: '   + (p.other   || 0)
                            );
                        },
                        error: function() {
                            $('#stat-installs, #stat-active, #stat-subscribers').text('—');
                            $('#stat-platforms').text('Could not load stats');
                        }
                    });
                })(jQuery);
                </script>

            <?php endif; ?>
        </div>
        <?php
    }
}

// Initialize
OHA_PWA_Notifications_Admin::init();
