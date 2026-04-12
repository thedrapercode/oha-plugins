<?php
/**
 * OHA Volunteer Hub - Volunteer Portal (Phase 3)
 * Frontend portal for volunteers to see their assignments
 */

namespace OHA\VH;

if (!defined('ABSPATH')) { exit; }

class Portal {

    public static function init() {
        add_shortcode('oha_volunteer_portal', [__CLASS__, 'render_portal']);
        add_action('wp_ajax_nopriv_oha_vh_portal_lookup',  [__CLASS__, 'ajax_portal_lookup']);
        add_action('wp_ajax_oha_vh_portal_lookup',         [__CLASS__, 'ajax_portal_lookup']);
        add_action('wp_ajax_nopriv_oha_vh_update_ec',      [__CLASS__, 'ajax_update_emergency_contact']);
        add_action('wp_ajax_oha_vh_update_ec',             [__CLASS__, 'ajax_update_emergency_contact']);
        add_action('wp_ajax_nopriv_oha_vh_update_phone',   [__CLASS__, 'ajax_update_phone']);
        add_action('wp_ajax_oha_vh_update_phone',          [__CLASS__, 'ajax_update_phone']);
    }

    // -------------------------------------------------------------------------
    // Shared helpers
    // -------------------------------------------------------------------------

    /**
     * Build the target datetime string for a countdown from an assignment row.
     * Returns an ISO-8601 string (no timezone) or empty string if no date set.
     */
    private static function get_countdown_target($assignment) {
        if (empty($assignment->shift_date)) return '';
        if ($assignment->shift_type === 'time_slot' && !empty($assignment->shift_start)) {
            return $assignment->shift_date . 'T' . substr($assignment->shift_start, 0, 5);
        }
        return $assignment->shift_date . 'T00:00:00';
    }

    /**
     * Render the emergency contact display+edit block.
     * Works for both logged-in portal (same page) and guest AJAX result.
     *
     * $volunteer     - DB row with emergency_contact and emergency_contact_attending
     * $ajax_url      - admin-ajax.php URL
     * $ec_nonce      - wp nonce for oha_vh_ec_{id}
     * $fn_prefix     - 'oha' for logged-in, 'ohaGuest' for guest (JS function names)
     */
    private static function render_ec_block($volunteer, $ajax_url, $ec_nonce, $fn_prefix = 'oha') {
        $vid         = (int) $volunteer->id;
        $contact     = $volunteer->emergency_contact ?? '';
        $attending   = $volunteer->emergency_contact_attending ?? '';
        $has_contact = !empty($contact);
        $btn_label   = $has_contact ? 'Edit' : 'Add Emergency Contact';
        ?>
        <div class="portal-ec-card">
            <h3>Emergency Contact on File</h3>
            <div id="ec-display">
                <?php if ($has_contact): ?>
                    <p><?php echo esc_html($contact); ?></p>
                    <?php if ($attending): ?>
                        <p>Will be at event: <strong><?php echo esc_html(ucfirst($attending)); ?></strong></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="ec-empty">No emergency contact on file.</p>
                <?php endif; ?>
                <button class="button" style="margin-top:10px;" onclick="<?php echo esc_attr($fn_prefix); ?>ShowEcEdit()"><?php echo esc_html($btn_label); ?></button>
            </div>
            <div id="ec-edit" style="display:none;">
                <p style="margin-bottom:6px;">Name and phone number of your emergency contact:</p>
                <input type="text" id="ec-name" class="ec-field" autocomplete="off"
                       value="<?php echo esc_attr($contact); ?>"
                       placeholder="Full name and phone number">
                <p style="margin:10px 0 6px 0;">Will this person be at the event?</p>
                <div class="ec-attending-row">
                    <label><input type="radio" name="ec-attending" value="yes" <?php checked($attending, 'yes'); ?>> Yes</label>
                    <label><input type="radio" name="ec-attending" value="no"  <?php checked($attending, 'no');  ?>> No</label>
                    <label><input type="radio" name="ec-attending" value=""    <?php checked($attending, '');    ?>> Not specified</label>
                </div>
                <div class="ec-actions">
                    <button class="button button-primary"
                            onclick="<?php echo esc_attr($fn_prefix); ?>SaveEc(<?php echo esc_js($vid); ?>, '<?php echo esc_js($ec_nonce); ?>', '<?php echo esc_js($ajax_url); ?>')">
                        Save
                    </button>
                    <button class="button" onclick="<?php echo esc_attr($fn_prefix); ?>CancelEcEdit()">Cancel</button>
                </div>
                <div id="ec-message" style="display:none;" class="ec-message"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the phone number display+edit block.
     *
     * $volunteer  - DB row with phone field
     * $ajax_url   - admin-ajax.php URL
     * $phone_nonce - wp nonce for oha_vh_phone_{id}
     * $fn_prefix  - 'oha' for logged-in, 'ohaGuest' for guest
     */
    private static function render_phone_block($volunteer, $ajax_url, $phone_nonce, $fn_prefix = 'oha') {
        $vid       = (int) $volunteer->id;
        $phone     = $volunteer->phone ?? '';
        $has_phone = !empty($phone);
        $btn_label = $has_phone ? 'Edit' : 'Add Phone Number';
        ?>
        <div class="portal-phone-card">
            <h3>Phone Number on File</h3>
            <div id="phone-display">
                <?php if ($has_phone): ?>
                    <p><?php echo esc_html($phone); ?></p>
                <?php else: ?>
                    <p class="phone-empty">No phone number on file.</p>
                <?php endif; ?>
                <button class="button" style="margin-top:10px;" onclick="<?php echo esc_attr($fn_prefix); ?>ShowPhoneEdit()"><?php echo esc_html($btn_label); ?></button>
            </div>
            <div id="phone-edit" style="display:none;">
                <p style="margin-bottom:6px;">Enter your phone number:</p>
                <input type="tel" id="phone-number" class="ec-field" autocomplete="tel"
                       value="<?php echo esc_attr($phone); ?>"
                       placeholder="e.g. 555-555-5555">
                <div class="ec-actions">
                    <button class="button button-primary"
                            onclick="<?php echo esc_attr($fn_prefix); ?>SavePhone(<?php echo esc_js($vid); ?>, '<?php echo esc_js($phone_nonce); ?>', '<?php echo esc_js($ajax_url); ?>')">
                        Save
                    </button>
                    <button class="button" onclick="<?php echo esc_attr($fn_prefix); ?>CancelPhoneEdit()">Cancel</button>
                </div>
                <div id="phone-message" style="display:none;" class="ec-message"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the day-of logistics block (check-in location + parking).
     * Only outputs if at least one field is set.
     */
    private static function render_logistics_block($checkin_location, $parking_info) {
        if (!$checkin_location && !$parking_info) return;
        ?>
        <div class="portal-logistics">
            <h3>Day-of Information</h3>
            <?php if ($checkin_location): ?>
                <div class="logistics-item">
                    <strong>Volunteer Check-In Location</strong>
                    <p><?php echo nl2br(esc_html($checkin_location)); ?></p>
                </div>
            <?php endif; ?>
            <?php if ($parking_info): ?>
                <div class="logistics-item">
                    <strong>Parking</strong>
                    <p><?php echo nl2br(esc_html($parking_info)); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render the contact-your-manager section for an assignment card.
     * Falls back to coordinator email if no managers are configured.
     */
    private static function render_contact_section($manager_info, $coordinator_email, $area_name) {
        $subject = rawurlencode('OHA Convention - ' . $area_name . ' Volunteer Question');
        ?>
        <div class="assignment-contact">
            <strong>Contact</strong>
            <?php if (!empty($manager_info)): ?>
                <?php foreach ($manager_info as $mgr): ?>
                    <a href="mailto:<?php echo esc_attr($mgr['email']); ?>?subject=<?php echo $subject; ?>" class="contact-btn">
                        Email <?php echo esc_html($mgr['name']); ?>
                    </a>
                <?php endforeach; ?>
            <?php elseif ($coordinator_email): ?>
                <a href="mailto:<?php echo esc_attr($coordinator_email); ?>?subject=<?php echo $subject; ?>" class="contact-btn">
                    Email Volunteer Coordinator
                </a>
            <?php else: ?>
                <p style="color:#666;font-size:13px;margin:4px 0 0 0;">No contact information configured. Check with the event organizer.</p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Inline CSS used by all portal output contexts (gate + logged-in).
     * Covers both legacy assignment card classes and all new portal elements.
     */
    private static function portal_css() {
        ?>
        <style>
            /* Gate */
            .oha-vh-portal-gate { max-width: 600px; margin: 0 auto; padding: 20px; }
            .portal-gate-header { text-align: center; margin-bottom: 30px; padding: 30px; background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; border-radius: 12px; }
            .portal-gate-header h2 { margin: 0 0 8px 0; color: white; }
            .portal-gate-header p { margin: 0; opacity: 0.9; }
            .portal-gate-section { background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
            .portal-gate-section h3 { margin: 0 0 12px 0; color: #000; font-size: 16px; }
            .portal-gate-section p { margin: 0 0 15px 0; color: #555; font-size: 14px; }
            .portal-gate-divider { text-align: center; margin: 5px 0; color: #aaa; font-size: 13px; font-style: italic; }
            .portal-email-row { display: flex; gap: 10px; margin-top: 5px; }
            .portal-email-row input[type="email"] { flex: 1; padding: 10px 14px; border: 1px solid #ccc; border-radius: 6px; font-size: 15px; color: inherit; }
            .portal-email-row button { white-space: nowrap; padding: 10px 18px; background: #e74c3c; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; }
            .portal-email-row button:hover { background: #c0392b; }
            .portal-lookup-error { margin-top: 12px; padding: 12px 16px; background: #f8d7da; color: #721c24; border-radius: 6px; font-size: 14px; }
            .portal-lookup-error a { color: #721c24; font-weight: 600; }
            .portal-lookup-result { margin-top: 20px; }
            @media (max-width: 480px) { .portal-email-row { flex-direction: column; } }

            /* Logged-in portal wrapper */
            .oha-vh-portal { max-width: 900px; margin: 0 auto; padding: 20px; }
            .portal-header { text-align: center; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 2px solid #e74c3c; }
            .portal-header h2 { margin: 0 0 10px 0; color: #000; }
            .portal-subtitle { color: #666; font-size: 18px; }
            .portal-no-assignments { text-align: center; padding: 60px 20px; background: #f9f9f9; border-radius: 12px; }
            .portal-no-assignments h3 { color: #000; margin-bottom: 15px; }
            .portal-info { margin-top: 30px; }
            .status-badge { padding: 6px 16px; border-radius: 20px; font-weight: 600; font-size: 14px; }
            .status-badge.approved { background: #d4edda; color: #155724; }

            /* Announcement */
            .portal-announcement { background: #fff3cd; border-left: 4px solid #f0ad4e; padding: 14px 18px; margin-bottom: 22px; border-radius: 0 8px 8px 0; color: #000; font-size: 14px; }
            .portal-announcement strong { color: #000; }

            /* Assignment cards */
            .assignment-card { background: #fff; border: 1px solid #ddd; border-radius: 12px; padding: 25px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
            .assignment-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee; flex-wrap: wrap; gap: 8px; }
            .assignment-header h3 { margin: 0; color: #000; }
            .assignment-status { padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; }
            .assignment-status.checked-in { background: #d4edda; color: #155724; }
            .assignment-status.pending { background: #fff3cd; color: #856404; }
            .assignment-details { margin-bottom: 15px; }
            .detail-row { display: flex; gap: 15px; margin-bottom: 12px; padding: 12px; background: #f9f9f9; border-radius: 6px; color: #000 !important; }
            .detail-row div { color: #000 !important; }
            .detail-row strong { display: block; color: #000 !important; margin-bottom: 4px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
            .manager-info { margin-top: 6px; padding: 7px 10px; background: #e3f2fd; border-radius: 4px; color: #000 !important; }
            .manager-email { display: block; margin-top: 3px; color: #1976d2 !important; text-decoration: none; font-size: 13px; }
            .manager-email:hover { text-decoration: underline; }
            .assignment-actions { text-align: center; padding: 15px 0 5px; border-top: 1px solid #eee; margin-top: 15px; }
            .assignment-actions small { display: block; margin-top: 8px; color: #666 !important; }
            .check-in-btn { font-size: 15px; padding: 10px 28px; background: #28a745 !important; border-color: #28a745 !important; color: white !important; }
            .assignment-checkin-info { padding: 12px; background: #d4edda; border-radius: 6px; text-align: center; margin-top: 15px; }
            .assignment-checkin-info p { margin: 4px 0; color: #155724 !important; }

            /* Countdown */
            .oha-countdown { text-align: center; padding: 8px 14px; background: #e3f2fd; border-radius: 6px; font-size: 14px; font-weight: 600; color: #1565c0; margin-bottom: 15px; }
            .oha-countdown.oha-countdown-past { background: #f5f5f5; color: #888; font-weight: normal; }

            /* Contact section */
            .assignment-contact { padding: 15px 0 5px; border-top: 1px solid #eee; margin-top: 15px; }
            .assignment-contact strong { display: block; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #666; margin-bottom: 8px; }
            .contact-btn { display: inline-block; padding: 8px 16px; background: #2271b1; color: white !important; text-decoration: none !important; border-radius: 6px; font-size: 13px; font-weight: 600; margin-right: 8px; margin-bottom: 6px; }
            .contact-btn:hover { background: #1a5a8a; }

            /* Emergency contact card */
            .portal-ec-card { background: #fff; border: 1px solid #ddd; border-radius: 12px; padding: 25px; margin: 25px 0; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
            .portal-ec-card h3 { margin-top: 0; color: #000; }
            .portal-ec-card p { color: #000 !important; margin: 6px 0; }
            .ec-empty { color: #888 !important; font-style: italic; }
            .ec-field { width: 100%; max-width: 420px; padding: 9px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; color: inherit; display: block; margin-bottom: 10px; box-sizing: border-box; }
            .ec-attending-row { margin-bottom: 15px; }
            .ec-attending-row label { margin-right: 18px; color: #000 !important; font-size: 14px; }
            .ec-actions { display: flex; gap: 10px; }
            .ec-message { padding: 10px 14px; border-radius: 6px; margin-top: 12px; font-size: 14px; display: none; }
            .ec-message.success { background: #d4edda; color: #155724; }
            .ec-message.error { background: #f8d7da; color: #721c24; }

            /* Phone card */
            .portal-phone-card { background: #fff; border: 1px solid #ddd; border-radius: 12px; padding: 25px; margin: 25px 0; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
            .portal-phone-card h3 { margin-top: 0; color: #000; }
            .portal-phone-card p { color: #000 !important; margin: 6px 0; }
            .phone-empty { color: #888 !important; font-style: italic; }

            /* Logistics */
            .portal-logistics { background: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 12px; padding: 25px; margin: 25px 0; }
            .portal-logistics h3 { margin-top: 0; color: #000 !important; }
            .logistics-item { margin-bottom: 16px; }
            .logistics-item:last-child { margin-bottom: 0; }
            .logistics-item strong { display: block; color: #000 !important; margin-bottom: 4px; }
            .logistics-item p { margin: 0; color: #000 !important; }

            /* Info box + contact */
            .portal-contact { background: #e3f2fd; padding: 25px; border-radius: 12px; margin: 25px 0; }
            .portal-contact h3 { margin-top: 0; color: #000 !important; }
            .portal-contact p { margin: 10px 0; color: #000 !important; }
            .portal-contact strong { color: #000 !important; }
            .portal-info-box { background: #fff3cd; padding: 25px; border-radius: 12px; margin-top: 25px; }
            .portal-info-box h3 { margin-top: 0; color: #000 !important; }
            .portal-info-box ul { margin: 15px 0; }
            .portal-info-box li { margin-bottom: 10px; color: #000 !important; }

            @media (max-width: 768px) {
                .assignment-header { flex-direction: column; align-items: flex-start; }
                .detail-row { flex-direction: column; gap: 8px; }
                .ec-actions { flex-direction: column; }
            }
        </style>
        <?php
    }

    /**
     * Shared JavaScript for countdown timer.
     * Safe to output in any context (gate or logged-in portal).
     */
    private static function countdown_js() {
        ?>
        <script>
        function ohaUpdateCountdowns() {
            var now = Date.now();
            document.querySelectorAll('.oha-countdown').forEach(function(el) {
                var target = new Date(el.dataset.target).getTime();
                if (!target || isNaN(target)) { el.style.display = 'none'; return; }
                var diff = target - now;
                if (diff <= 0) {
                    el.className = 'oha-countdown oha-countdown-past';
                    el.textContent = 'This shift has passed.';
                    return;
                }
                var days  = Math.floor(diff / 86400000);
                var hours = Math.floor((diff % 86400000) / 3600000);
                var mins  = Math.floor((diff % 3600000) / 60000);
                var secs  = Math.floor((diff % 60000) / 1000);
                var label;
                if (days > 0) {
                    label = days + (days === 1 ? ' day' : ' days') + ', ' + hours + (hours === 1 ? ' hour' : ' hours') + ' until your shift';
                } else if (hours > 0) {
                    label = hours + 'h ' + mins + 'm ' + secs + 's until your shift';
                } else {
                    label = mins + 'm ' + secs + 's until your shift';
                }
                el.className = 'oha-countdown';
                el.textContent = label;
            });
        }
        if (!window._ohaCountdownTimer) {
            ohaUpdateCountdowns();
            window._ohaCountdownTimer = setInterval(ohaUpdateCountdowns, 1000);
        }
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // Shortcode render
    // -------------------------------------------------------------------------

    public static function render_portal($atts) {

        $show_checkin      = Settings::get('portal_show_checkin', '1') !== '0';
        $announcement      = Settings::get('portal_announcement', '');
        $checkin_location  = Settings::get('portal_checkin_location', '');
        $parking_info      = Settings::get('portal_parking_info', '');
        $coordinator_email = Settings::get('volunteer_coordinator_email');
        $coordinator_phone = Settings::get('volunteer_coordinator_phone');

        // ------------------------------------------------------------------
        // GATE: not logged in
        // ------------------------------------------------------------------
        if (!is_user_logged_in()) {
            $lookup_nonce = wp_create_nonce('oha_vh_portal_lookup');
            $ajax_url     = admin_url('admin-ajax.php');
            $login_url    = wp_login_url(get_permalink());

            ob_start();
            self::portal_css();
            ?>
            <div class="oha-vh-portal-gate">

                <?php if ($announcement): ?>
                <div class="portal-announcement">
                    <strong>Notice:</strong> <?php echo nl2br(esc_html($announcement)); ?>
                </div>
                <?php endif; ?>

                <div class="portal-gate-header">
                    <h2>Volunteer Portal</h2>
                    <p>OHA Convention 2026 - View your assignment and check in</p>
                </div>

                <div class="portal-gate-section">
                    <h3>Have an OHA website account?</h3>
                    <p>Log in with your ohiohaunters.com credentials to access the full portal.</p>
                    <a href="<?php echo esc_url($login_url); ?>" class="button button-primary" style="background:#2271b1;border-color:#2271b1;text-decoration:none;color:white;padding:10px 20px;">Log In to Your Account</a>
                </div>

                <div class="portal-gate-divider">- or -</div>

                <div class="portal-gate-section">
                    <h3>Not a website member?</h3>
                    <p>Enter the email address you used when you signed up as a volunteer.</p>
                    <form id="oha-portal-email-form" onsubmit="return false;">
                        <div class="portal-email-row">
                            <input type="email" id="oha-portal-email-input" placeholder="your@email.com" autocomplete="email">
                            <button type="submit" id="oha-portal-lookup-btn">Find My Assignment</button>
                        </div>
                    </form>
                    <div id="oha-portal-lookup-error" class="portal-lookup-error" style="display:none;"></div>
                    <div id="oha-portal-lookup-result" class="portal-lookup-result"></div>
                </div>
            </div>

            <script>
            // Email lookup
            function ohaPortalLookup() {
                var email    = document.getElementById('oha-portal-email-input').value.trim();
                var errorDiv = document.getElementById('oha-portal-lookup-error');
                var resultDiv = document.getElementById('oha-portal-lookup-result');
                var btn      = document.getElementById('oha-portal-lookup-btn');

                if (!email) {
                    errorDiv.innerHTML = 'Please enter your email address.';
                    errorDiv.style.display = 'block';
                    return;
                }
                btn.textContent = 'Searching...';
                btn.disabled = true;
                errorDiv.style.display = 'none';
                resultDiv.innerHTML = '';

                fetch('<?php echo esc_js($ajax_url); ?>', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'oha_vh_portal_lookup',
                        email: email,
                        nonce: '<?php echo esc_js($lookup_nonce); ?>'
                    })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    btn.textContent = 'Find My Assignment';
                    btn.disabled = false;
                    if (data.success) {
                        resultDiv.innerHTML = data.data.html;
                        document.getElementById('oha-portal-email-form').style.display = 'none';
                        // Start countdowns for injected content
                        clearInterval(window._ohaCountdownTimer);
                        ohaUpdateCountdowns();
                        window._ohaCountdownTimer = setInterval(ohaUpdateCountdowns, 1000);
                    } else {
                        errorDiv.innerHTML = data.data.message || 'Something went wrong. Please try again.';
                        errorDiv.style.display = 'block';
                    }
                })
                .catch(function() {
                    btn.textContent = 'Find My Assignment';
                    btn.disabled = false;
                    errorDiv.innerHTML = 'Connection error. Please try again.';
                    errorDiv.style.display = 'block';
                });
            }
            document.getElementById('oha-portal-email-form').addEventListener('submit', function(e) {
                e.preventDefault();
                ohaPortalLookup();
            });

            // Countdown (shared, also used after AJAX injection)
            function ohaUpdateCountdowns() {
                var now = Date.now();
                document.querySelectorAll('.oha-countdown').forEach(function(el) {
                    var target = new Date(el.dataset.target).getTime();
                    if (!target || isNaN(target)) { el.style.display = 'none'; return; }
                    var diff = target - now;
                    if (diff <= 0) {
                        el.className = 'oha-countdown oha-countdown-past';
                        el.textContent = 'This shift has passed.';
                        return;
                    }
                    var days  = Math.floor(diff / 86400000);
                    var hours = Math.floor((diff % 86400000) / 3600000);
                    var mins  = Math.floor((diff % 3600000) / 60000);
                    var secs  = Math.floor((diff % 60000) / 1000);
                    var label;
                    if (days > 0) {
                        label = days + (days === 1 ? ' day' : ' days') + ', ' + hours + (hours === 1 ? ' hour' : ' hours') + ' until your shift';
                    } else if (hours > 0) {
                        label = hours + 'h ' + mins + 'm ' + secs + 's until your shift';
                    } else {
                        label = mins + 'm ' + secs + 's until your shift';
                    }
                    el.className = 'oha-countdown';
                    el.textContent = label;
                });
            }

            // Guest check-in (called from onclick in injected HTML)
            function ohaGuestCheckIn(assignmentId, nonce, ajaxUrl) {
                if (!confirm('Check in for this assignment?')) return;
                var wrap = document.getElementById('checkin-wrap-' + assignmentId);
                var btn  = wrap ? wrap.querySelector('button') : null;
                if (btn) { btn.disabled = true; btn.textContent = 'Checking in...'; }

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'oha_vh_check_in',
                        assignment_id: assignmentId,
                        nonce: nonce
                    })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        if (wrap) {
                            wrap.innerHTML = '<div class="assignment-checkin-info"><p><strong>Checked in.</strong> Thank you for being here.</p></div>';
                        }
                    } else {
                        alert(data.data && data.data.message ? data.data.message : 'Error checking in. Please try again.');
                        if (btn) { btn.disabled = false; btn.textContent = 'Check In Now'; }
                    }
                })
                .catch(function() {
                    alert('Connection error. Please try again.');
                    if (btn) { btn.disabled = false; btn.textContent = 'Check In Now'; }
                });
            }

            // Guest emergency contact edit
            function ohaGuestShowEcEdit() {
                document.getElementById('ec-display').style.display = 'none';
                document.getElementById('ec-edit').style.display = 'block';
            }
            function ohaGuestCancelEcEdit() {
                document.getElementById('ec-edit').style.display = 'none';
                document.getElementById('ec-display').style.display = 'block';
            }
            function ohaGuestSaveEc(volunteerId, nonce, ajaxUrl) {
                ohaDoSaveEc(volunteerId, nonce, ajaxUrl);
            }

            // Guest phone edit
            function ohaGuestShowPhoneEdit() {
                document.getElementById('phone-display').style.display = 'none';
                document.getElementById('phone-edit').style.display = 'block';
            }
            function ohaGuestCancelPhoneEdit() {
                document.getElementById('phone-edit').style.display = 'none';
                document.getElementById('phone-display').style.display = 'block';
            }
            function ohaGuestSavePhone(volunteerId, nonce, ajaxUrl) {
                var phone = document.getElementById('phone-number').value.trim();
                var msgEl = document.getElementById('phone-message');
                msgEl.style.display = 'none';
                msgEl.className = 'ec-message';

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'oha_vh_update_phone',
                        volunteer_id: volunteerId,
                        nonce: nonce,
                        phone: phone
                    })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    msgEl.style.display = 'block';
                    if (data.success) {
                        msgEl.className = 'ec-message success';
                        msgEl.textContent = 'Phone number saved.';
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        msgEl.className = 'ec-message error';
                        msgEl.textContent = (data.data && data.data.message) ? data.data.message : 'Failed to save. Please try again.';
                    }
                })
                .catch(function() {
                    msgEl.style.display = 'block';
                    msgEl.className = 'ec-message error';
                    msgEl.textContent = 'Connection error. Please try again.';
                });
            }

            // Shared EC save logic
            function ohaDoSaveEc(volunteerId, nonce, ajaxUrl) {
                var contact  = document.getElementById('ec-name').value.trim();
                var attending = '';
                document.querySelectorAll('input[name="ec-attending"]').forEach(function(el) {
                    if (el.checked) attending = el.value;
                });
                var msgEl = document.getElementById('ec-message');
                msgEl.style.display = 'none';
                msgEl.className = 'ec-message';

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'oha_vh_update_ec',
                        volunteer_id: volunteerId,
                        nonce: nonce,
                        emergency_contact: contact,
                        emergency_contact_attending: attending
                    })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    msgEl.style.display = 'block';
                    if (data.success) {
                        msgEl.className = 'ec-message success';
                        msgEl.textContent = 'Emergency contact saved.';
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        msgEl.className = 'ec-message error';
                        msgEl.textContent = (data.data && data.data.message) ? data.data.message : 'Failed to save. Please try again.';
                    }
                })
                .catch(function() {
                    msgEl.style.display = 'block';
                    msgEl.className = 'ec-message error';
                    msgEl.textContent = 'Connection error. Please try again.';
                });
            }
            </script>
            <?php
            return ob_get_clean();
        }

        // ------------------------------------------------------------------
        // LOGGED-IN PORTAL
        // ------------------------------------------------------------------
        wp_enqueue_style('oha-vh');
        wp_enqueue_script('oha-vh');

        // Look up volunteer by user_id OR by WP account email
        $user_id    = get_current_user_id();
        $user_email = wp_get_current_user()->user_email;
        global $wpdb;
        $volunteer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}oha_volunteers WHERE user_id = %d OR email = %s LIMIT 1",
            $user_id, $user_email
        ));

        if (!$volunteer) {
            return '<div class="oha-vh-portal-none">
                <h3>Volunteer Portal</h3>
                <p>You haven\'t signed up as a volunteer yet.</p>
                <p><a href="' . esc_url(get_permalink()) . '?signup" class="button button-primary">Sign Up to Volunteer</a></p>
            </div>';
        }

        $assignments   = Database::get_assignments_by_volunteer($volunteer->id);
        $ec_nonce      = wp_create_nonce('oha_vh_ec_' . $volunteer->id);
        $phone_nonce   = wp_create_nonce('oha_vh_phone_' . $volunteer->id);
        $ajax_url      = admin_url('admin-ajax.php');

        ob_start();
        self::portal_css();
        ?>
        <div class="oha-vh-portal">

            <?php if ($announcement): ?>
            <div class="portal-announcement">
                <strong>Notice:</strong> <?php echo nl2br(esc_html($announcement)); ?>
            </div>
            <?php endif; ?>

            <div class="portal-header">
                <h2 style="color: #ffffff !important;">Welcome, <?php echo esc_html($volunteer->first_name); ?>!</h2>
                <p class="portal-subtitle">Your OHA Convention 2026 Assignments</p>
            </div>

            <?php if (empty($assignments)): ?>
                <div class="portal-no-assignments">
                    <h3>No Assignments Yet</h3>
                    <p style="color: #000 !important;">You are approved. Our team is currently reviewing volunteer preferences and will assign you to an area soon.</p>
                    <p style="color: #000 !important;">You will receive an email when you are assigned. Check back here for updates.</p>
                    <p class="portal-info"><strong style="color: #000 !important;">Your Status:</strong> <span class="status-badge approved">Approved</span></p>
                </div>
            <?php else: ?>
                <div class="portal-assignments">
                    <?php foreach ($assignments as $assignment):
                        $managers     = json_decode($assignment->area_managers, true) ?: [];
                        $manager_info = [];
                        foreach ($managers as $manager_id) {
                            $manager = get_user_by('id', $manager_id);
                            if ($manager) {
                                $manager_info[] = [
                                    'name'  => $manager->display_name,
                                    'email' => $manager->user_email
                                ];
                            }
                        }
                        $countdown_target = self::get_countdown_target($assignment);
                    ?>
                    <div class="assignment-card" style="border-left: 5px solid <?php echo esc_attr($assignment->color ?: '#e74c3c'); ?>">
                        <div class="assignment-header">
                            <h3><?php echo esc_html($assignment->icon . ' ' . $assignment->area_name); ?></h3>
                            <?php if ($assignment->checked_in): ?>
                                <span class="assignment-status checked-in">Checked In</span>
                            <?php else: ?>
                                <span class="assignment-status pending">Not Checked In</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($countdown_target): ?>
                        <div class="oha-countdown" data-target="<?php echo esc_attr($countdown_target); ?>">Calculating...</div>
                        <?php endif; ?>

                        <div class="assignment-details">
                            <?php if ($assignment->shift_type === 'time_slot'): ?>
                                <div class="detail-row">
                                    <div>
                                        <strong>Date</strong>
                                        <?php echo esc_html(date('l, F j, Y', strtotime($assignment->shift_date))); ?>
                                    </div>
                                </div>
                                <div class="detail-row">
                                    <div>
                                        <strong>Time</strong>
                                        <?php echo esc_html(date('g:i A', strtotime($assignment->shift_start)) . ' - ' . date('g:i A', strtotime($assignment->shift_end))); ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="detail-row">
                                    <div>
                                        <strong>Date</strong>
                                        <?php echo esc_html(date('l, F j, Y', strtotime($assignment->shift_date ?: Settings::get('convention_date')))); ?>
                                    </div>
                                </div>
                                <div class="detail-row">
                                    <div><strong>Shift</strong> All Day</div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($assignment->area_description)): ?>
                                <div class="detail-row">
                                    <div>
                                        <strong>Area Description</strong>
                                        <?php echo esc_html($assignment->area_description); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($manager_info)): ?>
                                <div class="detail-row">
                                    <div>
                                        <strong>Area Manager<?php echo count($manager_info) > 1 ? 's' : ''; ?></strong>
                                        <?php foreach ($manager_info as $mgr): ?>
                                            <div class="manager-info">
                                                <?php echo esc_html($mgr['name']); ?>
                                                <a href="mailto:<?php echo esc_attr($mgr['email']); ?>" class="manager-email">
                                                    <?php echo esc_html($mgr['email']); ?>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($assignment->notes): ?>
                                <div class="detail-row">
                                    <div>
                                        <strong>Notes</strong>
                                        <?php echo nl2br(esc_html($assignment->notes)); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php self::render_contact_section($manager_info, $coordinator_email, $assignment->area_name); ?>

                        <?php if ($show_checkin && !$assignment->checked_in): ?>
                            <div class="assignment-actions">
                                <button class="button button-primary check-in-btn"
                                        onclick="checkInVolunteer(<?php echo esc_js($assignment->id); ?>)">
                                    Check In Now
                                </button>
                                <small>Check in when you arrive at the convention.</small>
                            </div>
                        <?php elseif ($assignment->checked_in): ?>
                            <div class="assignment-checkin-info">
                                <p><strong>Checked in:</strong> <?php echo esc_html(date('g:i A', strtotime($assignment->checked_in_time))); ?></p>
                                <?php if ($assignment->hours_worked > 0): ?>
                                    <p><strong>Hours worked:</strong> <?php echo esc_html($assignment->hours_worked); ?> hours</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($coordinator_email): ?>
                <div class="portal-contact">
                    <h3>Need Help?</h3>
                    <p>Questions about your assignment? Contact your area manager using the links above, or reach out to our volunteer coordinator:</p>
                    <p><strong>Email:</strong> <a href="mailto:<?php echo esc_attr($coordinator_email); ?>"><?php echo esc_html($coordinator_email); ?></a></p>
                    <?php if ($coordinator_phone): ?>
                        <p><strong>Phone:</strong> <a href="tel:<?php echo esc_attr($coordinator_phone); ?>"><?php echo esc_html($coordinator_phone); ?></a></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php self::render_ec_block($volunteer, $ajax_url, $ec_nonce, 'oha'); ?>

            <?php self::render_phone_block($volunteer, $ajax_url, $phone_nonce, 'oha'); ?>

            <?php self::render_logistics_block($checkin_location, $parking_info); ?>

            <div class="portal-info-box">
                <h3>What to Bring</h3>
                <ul>
                    <li>Comfortable shoes (you will be on your feet)</li>
                    <li>Water bottle (stay hydrated)</li>
                    <li>Positive attitude and enthusiasm</li>
                    <li>Bookmark this page for check-in day</li>
                </ul>
                <div style="margin-top: 20px; padding: 15px; background: white; border-radius: 6px; text-align: center; border: 2px dashed #e74c3c;">
                    <strong style="display: block; margin-bottom: 8px; color: #000 !important;">Important Resources</strong>
                    <a href="/convention/2026-convention/volunteers/volunteer-handbook/" target="_blank" class="button button-primary" style="background: #e74c3c !important; border-color: #e74c3c !important; text-decoration: none;">
                        View Volunteer Handbook
                    </a>
                    <p style="margin: 10px 0 0 0; font-size: 13px; color: #666 !important;">Review policies, procedures, and important information</p>
                </div>
            </div>
        </div>

        <script>
        function checkInVolunteer(assignmentId) {
            if (!confirm('Check in for this assignment?')) return;
            jQuery.post(OHA_VH_AJAX.ajaxurl, {
                action: 'oha_vh_check_in_volunteer',
                assignment_id: assignmentId,
                nonce: OHA_VH_AJAX.nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error checking in');
                }
            });
        }

        function ohaShowEcEdit() {
            document.getElementById('ec-display').style.display = 'none';
            document.getElementById('ec-edit').style.display = 'block';
        }
        function ohaCancelEcEdit() {
            document.getElementById('ec-edit').style.display = 'none';
            document.getElementById('ec-display').style.display = 'block';
        }

        function ohaShowPhoneEdit() {
            document.getElementById('phone-display').style.display = 'none';
            document.getElementById('phone-edit').style.display = 'block';
        }
        function ohaCancelPhoneEdit() {
            document.getElementById('phone-edit').style.display = 'none';
            document.getElementById('phone-display').style.display = 'block';
        }
        function ohaSavePhone(volunteerId, nonce, ajaxUrl) {
            var phone = document.getElementById('phone-number').value.trim();
            var msgEl = document.getElementById('phone-message');
            msgEl.style.display = 'none';
            msgEl.className = 'ec-message';

            fetch(ajaxUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'oha_vh_update_phone',
                    volunteer_id: volunteerId,
                    nonce: nonce,
                    phone: phone
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                msgEl.style.display = 'block';
                if (data.success) {
                    msgEl.className = 'ec-message success';
                    msgEl.textContent = 'Phone number saved.';
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    msgEl.className = 'ec-message error';
                    msgEl.textContent = (data.data && data.data.message) ? data.data.message : 'Failed to save. Please try again.';
                }
            })
            .catch(function() {
                msgEl.style.display = 'block';
                msgEl.className = 'ec-message error';
                msgEl.textContent = 'Connection error. Please try again.';
            });
        }
        function ohaSaveEc(volunteerId, nonce, ajaxUrl) {
            var contact  = document.getElementById('ec-name').value.trim();
            var attending = '';
            document.querySelectorAll('input[name="ec-attending"]').forEach(function(el) {
                if (el.checked) attending = el.value;
            });
            var msgEl = document.getElementById('ec-message');
            msgEl.style.display = 'none';
            msgEl.className = 'ec-message';

            fetch(ajaxUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'oha_vh_update_ec',
                    volunteer_id: volunteerId,
                    nonce: nonce,
                    emergency_contact: contact,
                    emergency_contact_attending: attending
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                msgEl.style.display = 'block';
                if (data.success) {
                    msgEl.className = 'ec-message success';
                    msgEl.textContent = 'Emergency contact saved.';
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    msgEl.className = 'ec-message error';
                    msgEl.textContent = (data.data && data.data.message) ? data.data.message : 'Failed to save. Please try again.';
                }
            })
            .catch(function() {
                msgEl.style.display = 'block';
                msgEl.className = 'ec-message error';
                msgEl.textContent = 'Connection error. Please try again.';
            });
        }

        // Start countdowns
        (function() {
            function ohaUpdateCountdowns() {
                var now = Date.now();
                document.querySelectorAll('.oha-countdown').forEach(function(el) {
                    var target = new Date(el.dataset.target).getTime();
                    if (!target || isNaN(target)) { el.style.display = 'none'; return; }
                    var diff = target - now;
                    if (diff <= 0) {
                        el.className = 'oha-countdown oha-countdown-past';
                        el.textContent = 'This shift has passed.';
                        return;
                    }
                    var days  = Math.floor(diff / 86400000);
                    var hours = Math.floor((diff % 86400000) / 3600000);
                    var mins  = Math.floor((diff % 3600000) / 60000);
                    var secs  = Math.floor((diff % 60000) / 1000);
                    var label;
                    if (days > 0) {
                        label = days + (days === 1 ? ' day' : ' days') + ', ' + hours + (hours === 1 ? ' hour' : ' hours') + ' until your shift';
                    } else if (hours > 0) {
                        label = hours + 'h ' + mins + 'm ' + secs + 's until your shift';
                    } else {
                        label = mins + 'm ' + secs + 's until your shift';
                    }
                    el.className = 'oha-countdown';
                    el.textContent = label;
                });
            }
            ohaUpdateCountdowns();
            setInterval(ohaUpdateCountdowns, 1000);
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // AJAX: guest email lookup
    // -------------------------------------------------------------------------

    public static function ajax_portal_lookup() {
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'oha_vh_portal_lookup')) {
            wp_send_json_error(['message' => 'Security check failed. Please refresh the page and try again.']);
        }

        $email = sanitize_email($_POST['email'] ?? '');
        if (!is_email($email)) {
            wp_send_json_error(['message' => 'Please enter a valid email address.']);
        }

        global $wpdb;
        $volunteer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}oha_volunteers WHERE email = %s LIMIT 1",
            $email
        ));

        if (!$volunteer) {
            wp_send_json_error(['message' => 'No volunteer record found for that email address. Please double-check your spelling, or <a href="/convention-2026/volunteers/">sign up here</a> if you haven\'t yet.']);
        }

        $assignments       = Database::get_assignments_by_volunteer($volunteer->id);
        $checkin_nonce     = wp_create_nonce('oha_vh_checkin');
        $ajax_url          = admin_url('admin-ajax.php');
        $ec_nonce          = wp_create_nonce('oha_vh_ec_' . $volunteer->id);
        $phone_nonce       = wp_create_nonce('oha_vh_phone_' . $volunteer->id);
        $show_checkin      = Settings::get('portal_show_checkin', '1') !== '0';
        $announcement      = Settings::get('portal_announcement', '');
        $checkin_location  = Settings::get('portal_checkin_location', '');
        $parking_info      = Settings::get('portal_parking_info', '');
        $coordinator_email = Settings::get('volunteer_coordinator_email');

        ob_start();
        ?>
        <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #e74c3c;">

            <?php if ($announcement): ?>
            <div class="portal-announcement">
                <strong>Notice:</strong> <?php echo nl2br(esc_html($announcement)); ?>
            </div>
            <?php endif; ?>

            <h3 style="color: #000; margin: 0 0 15px 0;">Hi <?php echo esc_html($volunteer->first_name); ?>, here are your assignments:</h3>

            <?php if (empty($assignments)): ?>
                <div style="padding: 30px; background: #f9f9f9; border-radius: 8px; text-align: center;">
                    <p style="color: #000; margin: 0;"><strong>No assignments yet.</strong> Our team is reviewing preferences and will assign you soon. You will receive an email when it is ready.</p>
                </div>
            <?php else: ?>
                <?php foreach ($assignments as $assignment):
                    $managers     = json_decode($assignment->area_managers, true) ?: [];
                    $manager_info = [];
                    foreach ($managers as $mid) {
                        $mgr = get_user_by('id', $mid);
                        if ($mgr) {
                            $manager_info[] = ['name' => $mgr->display_name, 'email' => $mgr->user_email];
                        }
                    }

                    if ($assignment->shift_type === 'all_day') {
                        $shift_display = 'All Day';
                    } elseif ($assignment->shift_start) {
                        $shift_display = date('g:i A', strtotime($assignment->shift_start)) . ' - ' . date('g:i A', strtotime($assignment->shift_end));
                    } else {
                        $shift_display = 'TBD';
                    }

                    $countdown_target = self::get_countdown_target($assignment);
                ?>
                <div class="assignment-card" style="border-left: 5px solid <?php echo esc_attr($assignment->color ?: '#e74c3c'); ?>">
                    <div class="assignment-header">
                        <h3><?php echo esc_html($assignment->icon . ' ' . $assignment->area_name); ?></h3>
                        <?php if ($assignment->checked_in): ?>
                            <span class="assignment-status checked-in">Checked In</span>
                        <?php else: ?>
                            <span class="assignment-status pending">Not Checked In</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($countdown_target): ?>
                    <div class="oha-countdown" data-target="<?php echo esc_attr($countdown_target); ?>">Calculating...</div>
                    <?php endif; ?>

                    <div class="assignment-details">
                        <div class="detail-row">
                            <div>
                                <strong>Date</strong>
                                <?php echo esc_html($assignment->shift_date ? date('l, F j, Y', strtotime($assignment->shift_date)) : 'To Be Determined'); ?>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div><strong>Time</strong> <?php echo esc_html($shift_display); ?></div>
                        </div>
                        <?php if (!empty($assignment->area_description)): ?>
                        <div class="detail-row">
                            <div><strong>Area Description</strong> <?php echo esc_html($assignment->area_description); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($assignment->notes): ?>
                        <div class="detail-row">
                            <div><strong>Notes</strong><br><?php echo nl2br(esc_html($assignment->notes)); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($manager_info)): ?>
                        <div class="detail-row">
                            <div>
                                <strong>Area Manager<?php echo count($manager_info) > 1 ? 's' : ''; ?></strong>
                                <?php foreach ($manager_info as $mgr): ?>
                                <div class="manager-info">
                                    <?php echo esc_html($mgr['name']); ?>
                                    <a href="mailto:<?php echo esc_attr($mgr['email']); ?>" class="manager-email">
                                        <?php echo esc_html($mgr['email']); ?>
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php self::render_contact_section($manager_info, $coordinator_email, $assignment->area_name); ?>

                    <?php if ($show_checkin && !$assignment->checked_in): ?>
                    <div class="assignment-actions" id="checkin-wrap-<?php echo (int)$assignment->id; ?>">
                        <button class="button button-primary check-in-btn"
                                onclick="ohaGuestCheckIn(<?php echo (int)$assignment->id; ?>, '<?php echo esc_js($checkin_nonce); ?>', '<?php echo esc_js($ajax_url); ?>')">
                            Check In Now
                        </button>
                        <small>Tap this when you arrive at the convention.</small>
                    </div>
                    <?php elseif ($assignment->checked_in): ?>
                    <div class="assignment-checkin-info">
                        <p><strong>Checked in:</strong> <?php echo esc_html(date('g:i A', strtotime($assignment->checked_in_time))); ?></p>
                        <?php if ($assignment->hours_worked > 0): ?>
                            <p><strong>Hours worked:</strong> <?php echo esc_html($assignment->hours_worked); ?> hours</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php self::render_ec_block($volunteer, $ajax_url, $ec_nonce, 'ohaGuest'); ?>

            <?php self::render_phone_block($volunteer, $ajax_url, $phone_nonce, 'ohaGuest'); ?>

            <?php self::render_logistics_block($checkin_location, $parking_info); ?>

            <p style="margin-top: 20px; font-size: 13px; color: #555; text-align: center;">
                Questions? Contact us at <a href="mailto:<?php echo esc_attr($coordinator_email); ?>"><?php echo esc_html($coordinator_email); ?></a>
            </p>
        </div>
        <?php
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    }

    // -------------------------------------------------------------------------
    // AJAX: update phone number
    // -------------------------------------------------------------------------

    public static function ajax_update_phone() {
        $volunteer_id = absint($_POST['volunteer_id'] ?? 0);
        $nonce        = sanitize_text_field($_POST['nonce'] ?? '');

        if (!$volunteer_id || !wp_verify_nonce($nonce, 'oha_vh_phone_' . $volunteer_id)) {
            wp_send_json_error(['message' => 'Security check failed. Please refresh the page and try again.']);
        }

        $phone = sanitize_text_field($_POST['phone'] ?? '');

        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'oha_volunteers',
            ['phone' => $phone],
            ['id' => $volunteer_id],
            ['%s'],
            ['%d']
        );

        if ($result !== false) {
            wp_send_json_success(['message' => 'Phone number updated.']);
        } else {
            wp_send_json_error(['message' => 'Failed to save. Please try again.']);
        }
    }

    // -------------------------------------------------------------------------
    // AJAX: update emergency contact
    // -------------------------------------------------------------------------

    public static function ajax_update_emergency_contact() {
        $volunteer_id = absint($_POST['volunteer_id'] ?? 0);
        $nonce        = sanitize_text_field($_POST['nonce'] ?? '');

        if (!$volunteer_id || !wp_verify_nonce($nonce, 'oha_vh_ec_' . $volunteer_id)) {
            wp_send_json_error(['message' => 'Security check failed. Please refresh the page and try again.']);
        }

        $contact  = sanitize_text_field($_POST['emergency_contact'] ?? '');
        $attending = sanitize_text_field($_POST['emergency_contact_attending'] ?? '');
        if (!in_array($attending, ['yes', 'no', ''], true)) {
            $attending = '';
        }

        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'oha_volunteers',
            [
                'emergency_contact'           => $contact,
                'emergency_contact_attending' => $attending,
            ],
            ['id' => $volunteer_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($result !== false) {
            wp_send_json_success(['message' => 'Emergency contact updated.']);
        } else {
            wp_send_json_error(['message' => 'Failed to save. Please try again.']);
        }
    }
}
