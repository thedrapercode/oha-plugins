<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OHA_Site_PWA_Shortcodes {

    private static $_instance = null;

    public static function instance() {
        if ( null === self::$_instance ) self::$_instance = new self();
        return self::$_instance;
    }

    public function init() {
        add_shortcode( 'oha_spwa_settings', array( $this, 'render_settings' ) );
    }

    // ------------------------------------------------------------------
    // [oha_spwa_settings]
    // User-facing settings page: app info, restore prompts, clear data,
    // check for updates. All actions operate on localStorage -- no AJAX.
    // ------------------------------------------------------------------
    public function render_settings( $atts ) {
        $version = OHA_SITE_PWA_VERSION;

        ob_start(); ?>
        <div id="oha-spwa-settings" class="oha-spwa-settings-wrap">
        <style>
        .oha-spwa-settings-wrap{max-width:560px;margin:0 auto;padding:8px 0 32px;font-family:inherit;color:#eee}
        .oha-spwa-settings-section{background:#111;border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:20px;margin-bottom:16px}
        .oha-spwa-settings-section h3{margin:0 0 4px;font-size:1rem;font-weight:700;color:#fff;letter-spacing:.3px}
        .oha-spwa-settings-section p.oha-spwa-desc{margin:0 0 16px;font-size:13px;color:rgba(255,255,255,.55);line-height:1.5}
        .oha-spwa-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;border-top:1px solid rgba(255,255,255,.07)}
        .oha-spwa-row:first-of-type{border-top:none;padding-top:0}
        .oha-spwa-label{font-size:14px;font-weight:600;color:#ddd;flex:1}
        .oha-spwa-label small{display:block;font-weight:400;font-size:12px;color:rgba(255,255,255,.45);margin-top:2px}
        .oha-spwa-btn{background:#1e1e2e;border:1px solid rgba(173,49,25,.5);color:#f9a8a8;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer;transition:background .15s,border-color .15s;white-space:nowrap;flex-shrink:0}
        .oha-spwa-btn:hover{background:#2a1010;border-color:#AD3119}
        .oha-spwa-btn:disabled{opacity:.4;cursor:not-allowed}
        .oha-spwa-btn.done{background:#0d1f0d;border-color:#4caf50;color:#81c784}
        .oha-spwa-btn-danger{border-color:rgba(244,67,54,.5);color:#ff8a80}
        .oha-spwa-btn-danger:hover{background:#1a0505;border-color:#f44336}
        .oha-spwa-info-row{display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-top:1px solid rgba(255,255,255,.07);font-size:13px}
        .oha-spwa-info-row:first-child{border-top:none;padding-top:0}
        .oha-spwa-info-key{color:rgba(255,255,255,.5)}
        .oha-spwa-info-val{font-weight:600;color:#eee}
        #oha-spwa-conn-dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:#4caf50;margin-right:6px;flex-shrink:0;vertical-align:middle}
        #oha-spwa-conn-dot.offline{background:#f44336}
        .oha-spwa-notif-hint{margin-top:10px;padding:10px 12px;background:#1a0e0e;border:1px solid rgba(244,67,54,.3);border-radius:8px;font-size:12px;color:rgba(255,255,255,.65);line-height:1.5;display:none}
        </style>

        <div class="oha-spwa-settings-section">
            <h3>App Information</h3>
            <div class="oha-spwa-info-row">
                <span class="oha-spwa-info-key">App Version</span>
                <span class="oha-spwa-info-val"><?php echo esc_html( $version ); ?></span>
            </div>
            <div class="oha-spwa-info-row">
                <span class="oha-spwa-info-key">Connection</span>
                <span class="oha-spwa-info-val">
                    <span id="oha-spwa-conn-dot"></span>
                    <span id="oha-spwa-conn-text">Checking...</span>
                </span>
            </div>
            <div class="oha-spwa-info-row">
                <span class="oha-spwa-info-key">Mode</span>
                <span class="oha-spwa-info-val" id="oha-spwa-mode">Checking...</span>
            </div>
            <div class="oha-spwa-info-row">
                <span class="oha-spwa-info-key">Notifications</span>
                <span class="oha-spwa-info-val" id="oha-spwa-notif-status">Checking...</span>
            </div>
            <div class="oha-spwa-notif-hint" id="oha-spwa-notif-hint">
                Notifications are blocked. To re-enable: open your browser or phone settings, find this website, and change the notification permission to Allow. Then close and reopen the app.
            </div>
        </div>

        <div class="oha-spwa-settings-section">
            <h3>Restore Dismissed Prompts</h3>
            <p class="oha-spwa-desc">Re-show prompts you previously dismissed.</p>
            <div class="oha-spwa-row">
                <div class="oha-spwa-label">
                    Notification Prompt
                    <small id="oha-spwa-notify-status">Checking...</small>
                </div>
                <button class="oha-spwa-btn" id="oha-spwa-restore-notify" type="button">Restore</button>
            </div>
            <div class="oha-spwa-row">
                <div class="oha-spwa-label">
                    Install App Prompt
                    <small id="oha-spwa-install-status">Checking...</small>
                </div>
                <button class="oha-spwa-btn" id="oha-spwa-restore-install" type="button">Restore</button>
            </div>
        </div>

        <div class="oha-spwa-settings-section">
            <h3>App Updates</h3>
            <div class="oha-spwa-row">
                <div class="oha-spwa-label">
                    Check for Updates
                    <small id="oha-spwa-update-status">Tap to check for a newer version of the app</small>
                </div>
                <button class="oha-spwa-btn" id="oha-spwa-check-update" type="button">Check</button>
            </div>
        </div>

        <div class="oha-spwa-settings-section">
            <h3>Clear App Data</h3>
            <p class="oha-spwa-desc">Remove locally saved preferences. This cannot be undone.</p>
            <div class="oha-spwa-row">
                <div class="oha-spwa-label">
                    Reset All App Data
                    <small>Clears all saved preferences and prompt history for this device</small>
                </div>
                <button class="oha-spwa-btn oha-spwa-btn-danger" id="oha-spwa-clear-all" type="button">Reset All</button>
            </div>
        </div>

        </div><!-- .oha-spwa-settings-wrap -->

        <script>
        (function () {
            // ── Connection & mode ─────────────────────────────────────────
            var dot  = document.getElementById('oha-spwa-conn-dot');
            var txt  = document.getElementById('oha-spwa-conn-text');
            var mode = document.getElementById('oha-spwa-mode');

            function updateConnection() {
                var online = navigator.onLine;
                if (dot) dot.className = online ? '' : 'offline';
                if (txt) txt.textContent = online ? 'Online' : 'Offline';
            }
            updateConnection();
            window.addEventListener('online',  updateConnection);
            window.addEventListener('offline', updateConnection);

            if (mode) {
                var isPWA = window.matchMedia('(display-mode: standalone)').matches ||
                            window.navigator.standalone === true;
                mode.textContent = isPWA ? 'Installed App (PWA)' : 'Browser';
            }

            // ── Notification permission ───────────────────────────────────
            var notifEl   = document.getElementById('oha-spwa-notif-status');
            var notifHint = document.getElementById('oha-spwa-notif-hint');
            if (notifEl) {
                if (!('Notification' in window)) {
                    notifEl.textContent = 'Not supported';
                    notifEl.style.color = 'rgba(255,255,255,0.4)';
                } else {
                    var perm = Notification.permission;
                    if (perm === 'granted') {
                        notifEl.textContent = 'Allowed';
                        notifEl.style.color = '#81c784';
                    } else if (perm === 'denied') {
                        notifEl.textContent = 'Blocked';
                        notifEl.style.color = '#f44336';
                        if (notifHint) notifHint.style.display = 'block';
                    } else {
                        notifEl.textContent = 'Not set up';
                        notifEl.style.color = '#ffb74d';
                    }
                }
            }

            // ── Restore indicators ────────────────────────────────────────
            function updateRestoreIndicators() {
                // Notification prompt: oha_notify_v1 is 'defer' or 'dismissed'
                var notifyState  = localStorage.getItem('oha_notify_v1');
                var notifyStatus = document.getElementById('oha-spwa-notify-status');
                var btnNotify    = document.getElementById('oha-spwa-restore-notify');
                var notifyDone   = notifyState === 'dismissed';
                if (notifyStatus) notifyStatus.textContent = notifyDone ? 'Currently dismissed' : notifyState === 'defer' ? 'Deferred' : 'Currently showing';
                if (btnNotify)    btnNotify.disabled = !notifyState;

                // Install prompt: timestamp-based, any value means snoozed
                var installTs    = localStorage.getItem('oha_spwa_install_prompt_ts') || localStorage.getItem('oha_spwa_ios_prompt_ts');
                var installStatus = document.getElementById('oha-spwa-install-status');
                var btnInstall    = document.getElementById('oha-spwa-restore-install');
                if (installStatus) installStatus.textContent = installTs ? 'Currently snoozed (24h)' : 'Not yet shown or already installed';
                if (btnInstall)    btnInstall.disabled = !installTs;
            }
            updateRestoreIndicators();

            // ── Helper: flash done then reset ─────────────────────────────
            function flashDone(btn, label, cb) {
                btn.disabled    = true;
                btn.className   = 'oha-spwa-btn done';
                btn.textContent = 'Done';
                setTimeout(function () {
                    btn.disabled    = false;
                    btn.className   = 'oha-spwa-btn';
                    btn.textContent = label;
                    if (cb) cb();
                }, 2000);
            }

            // ── Restore notification prompt ───────────────────────────────
            var btnNotify = document.getElementById('oha-spwa-restore-notify');
            if (btnNotify) {
                btnNotify.addEventListener('click', function () {
                    localStorage.removeItem('oha_notify_v1');
                    localStorage.removeItem('oha_pv');
                    flashDone(btnNotify, 'Restore', updateRestoreIndicators);
                });
            }

            // ── Restore install prompt ────────────────────────────────────
            var btnInstall = document.getElementById('oha-spwa-restore-install');
            if (btnInstall) {
                btnInstall.addEventListener('click', function () {
                    localStorage.removeItem('oha_spwa_install_prompt_ts');
                    localStorage.removeItem('oha_spwa_ios_prompt_ts');
                    flashDone(btnInstall, 'Restore', updateRestoreIndicators);
                });
            }

            // ── Check for updates ─────────────────────────────────────────
            var btnUpdate    = document.getElementById('oha-spwa-check-update');
            var updateStatus = document.getElementById('oha-spwa-update-status');
            if (btnUpdate) {
                btnUpdate.addEventListener('click', function () {
                    btnUpdate.disabled = true;
                    if (updateStatus) updateStatus.textContent = 'Checking...';
                    if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
                        navigator.serviceWorker.getRegistration().then(function (reg) {
                            if (!reg) {
                                if (updateStatus) updateStatus.textContent = 'No service worker found.';
                                btnUpdate.disabled = false;
                                return;
                            }
                            reg.update().then(function () {
                                if (updateStatus) updateStatus.textContent = 'You are on the latest version.';
                                btnUpdate.className = 'oha-spwa-btn done';
                                setTimeout(function () {
                                    btnUpdate.disabled  = false;
                                    btnUpdate.className = 'oha-spwa-btn';
                                    if (updateStatus) updateStatus.textContent = 'Tap to check for a newer version of the app';
                                }, 3000);
                            });
                        });
                    } else {
                        if (updateStatus) updateStatus.textContent = 'Service worker not active. Try reloading.';
                        btnUpdate.disabled = false;
                    }
                });
            }

            // ── Reset all app data ────────────────────────────────────────
            var btnClearAll = document.getElementById('oha-spwa-clear-all');
            if (btnClearAll) {
                btnClearAll.addEventListener('click', function () {
                    if (!confirm('Clear all saved app data for this device?')) return;
                    localStorage.removeItem('oha_notify_v1');
                    localStorage.removeItem('oha_pv');
                    localStorage.removeItem('oha_spwa_install_prompt_ts');
                    localStorage.removeItem('oha_spwa_ios_prompt_ts');
                    btnClearAll.disabled    = true;
                    btnClearAll.className   = 'oha-spwa-btn done';
                    btnClearAll.textContent = 'Done';
                    updateRestoreIndicators();
                    setTimeout(function () {
                        btnClearAll.disabled    = false;
                        btnClearAll.className   = 'oha-spwa-btn oha-spwa-btn-danger';
                        btnClearAll.textContent = 'Reset All';
                    }, 2500);
                });
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
