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
        add_action('wp_ajax_nopriv_oha_vh_portal_lookup', [__CLASS__, 'ajax_portal_lookup']);
        add_action('wp_ajax_oha_vh_portal_lookup', [__CLASS__, 'ajax_portal_lookup']);
    }

    /**
     * Render volunteer portal
     */
    public static function render_portal($atts) {
        if (!is_user_logged_in()) {
            $lookup_nonce = wp_create_nonce('oha_vh_portal_lookup');
            $ajax_url    = admin_url('admin-ajax.php');
            $login_url   = wp_login_url(get_permalink());

            ob_start();
            ?>
            <div class="oha-vh-portal-gate">
                <style>
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
                </style>

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
                            <button type="submit" id="oha-portal-lookup-btn" onclick="ohaPortalLookup()">Find My Assignment</button>
                        </div>
                    </form>
                    <div id="oha-portal-lookup-error" class="portal-lookup-error" style="display:none;"></div>
                    <div id="oha-portal-lookup-result" class="portal-lookup-result"></div>
                </div>
            </div>

            <script>
            function ohaPortalLookup() {
                var email = document.getElementById('oha-portal-email-input').value.trim();
                var errorDiv = document.getElementById('oha-portal-lookup-error');
                var resultDiv = document.getElementById('oha-portal-lookup-result');
                var btn = document.getElementById('oha-portal-lookup-btn');

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
            </script>
            <?php
            return ob_get_clean();
        }

        wp_enqueue_style('oha-vh');
        wp_enqueue_script('oha-vh');

        // Look up volunteer by user_id OR by WP account email so both linked and unlinked records match
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

        $assignments = Database::get_assignments_by_volunteer($volunteer->id);

        ob_start();
        ?>
        <div class="oha-vh-portal">
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
                        $managers = json_decode($assignment->area_managers, true) ?: [];
                        $manager_info = [];
                        foreach ($managers as $manager_id) {
                            $manager = get_user_by('id', $manager_id);
                            if ($manager) {
                                $manager_info[] = [
                                    'name' => $manager->display_name,
                                    'email' => $manager->user_email
                                ];
                            }
                        }
                    ?>
                    <div class="assignment-card" style="border-left: 5px solid <?php echo esc_attr($assignment->color); ?>">
                        <div class="assignment-header">
                            <h3><?php echo esc_html($assignment->icon . ' ' . $assignment->area_name); ?></h3>
                            <?php if ($assignment->checked_in): ?>
                                <span class="assignment-status checked-in">Checked In</span>
                            <?php else: ?>
                                <span class="assignment-status pending">Not Checked In</span>
                            <?php endif; ?>
                        </div>

                        <div class="assignment-details">
                            <?php if ($assignment->shift_type === 'time_slot'): ?>
                                <div class="detail-row">
                                    <div>
                                        <strong>Date:</strong>
                                        <?php echo esc_html(date('l, F j, Y', strtotime($assignment->shift_date))); ?>
                                    </div>
                                </div>
                                <div class="detail-row">
                                    <div>
                                        <strong>Time:</strong>
                                        <?php echo esc_html(date('g:i A', strtotime($assignment->shift_start)) . ' - ' . date('g:i A', strtotime($assignment->shift_end))); ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="detail-row">
                                    <div>
                                        <strong>Date:</strong>
                                        <?php echo esc_html(date('l, F j, Y', strtotime($assignment->shift_date ?: Settings::get('convention_date')))); ?>
                                    </div>
                                </div>
                                <div class="detail-row">
                                    <div><strong>Shift:</strong> All Day</div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($manager_info)): ?>
                                <div class="detail-row">
                                    <div>
                                        <strong>Your Area Manager<?php echo count($manager_info) > 1 ? 's' : ''; ?>:</strong><br>
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
                                        <strong>Notes:</strong><br>
                                        <?php echo nl2br(esc_html($assignment->notes)); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!$assignment->checked_in): ?>
                            <div class="assignment-actions">
                                <button class="button button-primary check-in-btn"
                                        onclick="checkInVolunteer(<?php echo esc_js($assignment->id); ?>)">
                                    Check In Now
                                </button>
                                <small>Check in when you arrive at the convention.</small>
                            </div>
                        <?php else: ?>
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

                <div class="portal-contact">
                    <h3>Need Help?</h3>
                    <p>Questions about your assignment? Contact your area manager using the email above, or reach out to our volunteer coordinator:</p>
                    <p><strong>Email:</strong> <a href="mailto:<?php echo esc_attr(Settings::get('volunteer_coordinator_email')); ?>"><?php echo esc_html(Settings::get('volunteer_coordinator_email')); ?></a></p>
                    <?php if (Settings::get('volunteer_coordinator_phone')): ?>
                        <p><strong>Phone:</strong> <a href="tel:<?php echo esc_attr(Settings::get('volunteer_coordinator_phone')); ?>"><?php echo esc_html(Settings::get('volunteer_coordinator_phone')); ?></a></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

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

        <style>
            .oha-vh-portal { max-width: 900px; margin: 0 auto; padding: 20px; }
            .portal-header { text-align: center; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 2px solid #e74c3c; }
            .portal-header h2 { margin: 0 0 10px 0; color: #000; }
            .portal-subtitle { color: #666; font-size: 18px; }
            .portal-no-assignments { text-align: center; padding: 60px 20px; background: #f9f9f9; border-radius: 12px; }
            .portal-no-assignments h3 { color: #000; margin-bottom: 15px; }
            .portal-info { margin-top: 30px; }
            .status-badge { padding: 6px 16px; border-radius: 20px; font-weight: 600; font-size: 14px; }
            .status-badge.approved { background: #d4edda; color: #155724; }
            .assignment-card { background: #fff; border: 1px solid #ddd; border-radius: 12px; padding: 25px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
            .assignment-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
            .assignment-header h3 { margin: 0; color: #000; }
            .assignment-status { padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; }
            .assignment-status.checked-in { background: #d4edda; color: #155724; }
            .assignment-status.pending { background: #fff3cd; color: #856404; }
            .assignment-details { margin-bottom: 20px; }
            .detail-row { display: flex; gap: 15px; margin-bottom: 15px; padding: 12px; background: #f9f9f9; border-radius: 6px; color: #000 !important; }
            .detail-row div { color: #000 !important; }
            .detail-row strong { display: block; color: #000 !important; margin-bottom: 5px; }
            .manager-info { margin-top: 8px; padding: 8px 12px; background: #e3f2fd; border-radius: 4px; color: #000 !important; }
            .manager-email { display: block; margin-top: 4px; color: #1976d2 !important; text-decoration: none; font-size: 14px; }
            .manager-email:hover { text-decoration: underline; }
            .assignment-actions { text-align: center; padding-top: 20px; border-top: 1px solid #eee; }
            .assignment-actions small { display: block; margin-top: 10px; color: #666 !important; }
            .check-in-btn { font-size: 16px; padding: 12px 30px; }
            .assignment-checkin-info { padding: 15px; background: #d4edda; border-radius: 6px; text-align: center; }
            .assignment-checkin-info p { margin: 5px 0; color: #155724 !important; }
            .portal-contact { background: #e3f2fd; padding: 25px; border-radius: 12px; margin: 30px 0; }
            .portal-contact h3 { margin-top: 0; color: #000 !important; }
            .portal-contact p { margin: 10px 0; color: #000 !important; }
            .portal-contact strong { color: #000 !important; }
            .portal-info-box { background: #fff3cd; padding: 25px; border-radius: 12px; margin-top: 30px; }
            .portal-info-box h3 { margin-top: 0; color: #000 !important; }
            .portal-info-box ul { margin: 15px 0; }
            .portal-info-box li { margin-bottom: 10px; color: #000 !important; }
            @media (max-width: 768px) {
                .assignment-header { flex-direction: column; align-items: flex-start; gap: 10px; }
                .detail-row { flex-direction: column; gap: 10px; }
            }
        </style>

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
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX: Email-based portal lookup for non-WP users
     * Nopriv -- authenticated only by email match against submitted volunteer record
     */
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

        $assignments   = Database::get_assignments_by_volunteer($volunteer->id);
        $checkin_nonce = wp_create_nonce('oha_vh_checkin');
        $ajax_url      = admin_url('admin-ajax.php');

        ob_start();
        ?>
        <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #e74c3c;">
            <h3 style="color: #000; margin: 0 0 15px 0;">Hi <?php echo esc_html($volunteer->first_name); ?>, here are your assignments:</h3>

            <?php if (empty($assignments)): ?>
                <div style="padding: 30px; background: #f9f9f9; border-radius: 8px; text-align: center;">
                    <p style="color: #000; margin: 0;"><strong>No assignments yet.</strong> Our team is reviewing preferences and will assign you soon. You will receive an email when it is ready.</p>
                </div>
            <?php else: ?>
                <?php foreach ($assignments as $assignment):
                    $managers = json_decode($assignment->area_managers, true) ?: [];

                    if ($assignment->shift_type === 'all_day') {
                        $shift_display = 'All Day';
                    } elseif ($assignment->shift_start) {
                        $shift_display = date('g:i A', strtotime($assignment->shift_start)) . ' - ' . date('g:i A', strtotime($assignment->shift_end));
                    } else {
                        $shift_display = 'TBD';
                    }
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

                    <div class="assignment-details">
                        <div class="detail-row">
                            <div>
                                <strong>Date:</strong>
                                <?php echo esc_html($assignment->shift_date ? date('l, F j, Y', strtotime($assignment->shift_date)) : 'To Be Determined'); ?>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div><strong>Time:</strong> <?php echo esc_html($shift_display); ?></div>
                        </div>
                        <?php if ($assignment->notes): ?>
                        <div class="detail-row">
                            <div><strong>Notes:</strong><br><?php echo nl2br(esc_html($assignment->notes)); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($managers)): ?>
                        <div class="detail-row">
                            <div>
                                <strong>Area Manager<?php echo count($managers) > 1 ? 's' : ''; ?>:</strong><br>
                                <?php foreach ($managers as $mid):
                                    $mgr = get_user_by('id', $mid);
                                    if ($mgr):
                                ?>
                                <div class="manager-info">
                                    <?php echo esc_html($mgr->display_name); ?>
                                    <a href="mailto:<?php echo esc_attr($mgr->user_email); ?>" class="manager-email">
                                        <?php echo esc_html($mgr->user_email); ?>
                                    </a>
                                </div>
                                <?php endif; endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!$assignment->checked_in): ?>
                    <div class="assignment-actions" id="checkin-wrap-<?php echo (int)$assignment->id; ?>">
                        <button class="button button-primary check-in-btn"
                                onclick="ohaGuestCheckIn(<?php echo (int)$assignment->id; ?>, '<?php echo esc_js($checkin_nonce); ?>', '<?php echo esc_js($ajax_url); ?>')">
                            Check In Now
                        </button>
                        <small>Tap this when you arrive at the convention.</small>
                    </div>
                    <?php else: ?>
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

            <p style="margin-top: 20px; font-size: 13px; color: #555; text-align: center;">
                Questions? Contact us at <a href="mailto:<?php echo esc_attr(Settings::get('volunteer_coordinator_email')); ?>"><?php echo esc_html(Settings::get('volunteer_coordinator_email')); ?></a>
            </p>
        </div>

        <style>
            .assignment-card { background: #fff; border: 1px solid #ddd; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
            .assignment-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 12px; border-bottom: 1px solid #eee; flex-wrap: wrap; gap: 8px; }
            .assignment-header h3 { margin: 0; color: #000; }
            .assignment-status { padding: 5px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; }
            .assignment-status.checked-in { background: #d4edda; color: #155724; }
            .assignment-status.pending { background: #fff3cd; color: #856404; }
            .assignment-details { margin-bottom: 15px; }
            .detail-row { display: flex; gap: 12px; margin-bottom: 10px; padding: 10px; background: #f9f9f9; border-radius: 6px; }
            .detail-row div { color: #000 !important; }
            .detail-row strong { display: block; color: #000 !important; margin-bottom: 3px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
            .manager-info { margin-top: 6px; padding: 7px 10px; background: #e3f2fd; border-radius: 4px; color: #000 !important; }
            .manager-email { display: block; margin-top: 3px; color: #1976d2 !important; text-decoration: none; font-size: 13px; }
            .assignment-actions { text-align: center; padding-top: 15px; border-top: 1px solid #eee; }
            .assignment-actions small { display: block; margin-top: 8px; color: #666; }
            .check-in-btn { font-size: 15px; padding: 10px 24px; background: #28a745 !important; border-color: #28a745 !important; color: white !important; }
            .assignment-checkin-info { padding: 12px; background: #d4edda; border-radius: 6px; text-align: center; }
            .assignment-checkin-info p { margin: 4px 0; color: #155724 !important; }
        </style>

        <script>
        function ohaGuestCheckIn(assignmentId, nonce, ajaxUrl) {
            if (!confirm('Check in for this assignment?')) return;
            var wrap = document.getElementById('checkin-wrap-' + assignmentId);
            var btn = wrap ? wrap.querySelector('button') : null;
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
                        wrap.innerHTML = '<div class="assignment-checkin-info"><p><strong>Checked in!</strong> Thank you for being here.</p></div>';
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
        </script>
        <?php
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    }
}
