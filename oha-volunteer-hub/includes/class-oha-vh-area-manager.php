<?php
/**
 * OHA Volunteer Hub - Area Manager Dashboard (Phase 3)
 * Dashboard for board members to manage their areas
 */

namespace OHA\VH;

if (!defined('ABSPATH')) { exit; }

class AreaManager {

    public static function init() {
        add_shortcode('oha_area_manager_dashboard', [__CLASS__, 'render_dashboard']);
        add_action('wp_ajax_oha_vh_manager_send_message', [__CLASS__, 'ajax_send_message']);
    }

    /**
     * Render area manager dashboard
     */
    public static function render_dashboard($atts) {
        if (!is_user_logged_in()) {
            return '<div class="oha-vh-login-required">
                <h3>Area Manager Dashboard</h3>
                <p>Please log in to manage your volunteer areas.</p>
                <p><a href="' . wp_login_url(get_permalink()) . '" class="button button-primary">Log In</a></p>
            </div>';
        }

        wp_enqueue_style('oha-vh');
        wp_enqueue_script('oha-vh');

        $user_id = get_current_user_id();
        $managed_areas = Database::get_areas_by_manager($user_id);
        
        if (empty($managed_areas) && !current_user_can('manage_options')) {
            return '<div class="oha-vh-no-areas">
                <h3>Area Manager Dashboard</h3>
                <p>You are not currently assigned as a manager for any convention areas.</p>
                <p>Contact an administrator if you should have area manager access.</p>
            </div>';
        }

        // Admins can see all areas
        if (empty($managed_areas) && current_user_can('manage_options')) {
            $managed_areas = Database::get_areas();
        }

        ob_start();
        ?>
        <div class="oha-vh-manager-dashboard">
            <div class="dashboard-header">
                <h2>👥 Area Manager Dashboard</h2>
                <p class="dashboard-subtitle">Manage your volunteer areas for OHA Convention 2026</p>
            </div>

            <div class="dashboard-stats">
                <?php 
                $total_volunteers = 0;
                foreach ($managed_areas as $area) {
                    $assignments = Database::get_assignments_by_area($area->id);
                    $total_volunteers += count($assignments);
                }
                ?>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($managed_areas); ?></div>
                    <div class="stat-label">Your Areas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_volunteers; ?></div>
                    <div class="stat-label">Total Volunteers</div>
                </div>
            </div>

            <div class="manager-areas">
                <?php foreach ($managed_areas as $area): 
                    $assignments = Database::get_assignments_by_area($area->id);
                    $checked_in_count = 0;
                    foreach ($assignments as $a) {
                        if ($a->checked_in) $checked_in_count++;
                    }
                ?>
                <div class="manager-area-card" style="border-left: 5px solid <?php echo esc_attr($area->color); ?>">
                    <div class="area-card-header">
                        <h3><?php echo esc_html($area->icon . ' ' . $area->area_name); ?></h3>
                        <div class="area-stats-mini">
                            <span class="stat-badge"><?php echo count($assignments); ?> assigned</span>
                            <span class="stat-badge checked-in"><?php echo $checked_in_count; ?> checked in</span>
                        </div>
                    </div>

                    <?php if (!empty($area->description)): ?>
                        <p class="area-description"><?php echo esc_html($area->description); ?></p>
                    <?php endif; ?>

                    <?php if (empty($assignments)): ?>
                        <div class="no-volunteers">
                            <p>No volunteers assigned yet.</p>
                            <?php if (current_user_can('manage_options')): ?>
                                <a href="<?php echo admin_url('admin.php?page=oha-volunteer-assign'); ?>" class="button">
                                    Assign Volunteers
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="volunteers-table-wrap">
                            <table class="volunteers-table">
                                <thead>
                                    <tr>
                                        <th>Volunteer</th>
                                        <th>Contact</th>
                                        <th>Shift</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignments as $a): 
                                        $name = $a->first_name ? ($a->first_name . ' ' . $a->last_name) : $a->manual_name;
                                        $email = $a->email ?: $a->manual_email;
                                        $phone = $a->phone ?: $a->manual_phone;
                                    ?>
                                    <tr>
                                        <td data-label="Volunteer">
                                            <strong><?php echo esc_html($name); ?></strong>
                                        </td>
                                        <td data-label="Contact">
                                            <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                                            <?php if ($phone): ?>
                                                <br><small><?php echo esc_html($phone); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Shift">
                                            <?php if ($a->shift_type === 'time_slot'): ?>
                                                <?php echo esc_html(date('M j, g:i A', strtotime($a->shift_start))); ?>
                                            <?php else: ?>
                                                All Day
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Status">
                                            <?php if ($a->checked_in): ?>
                                                <span class="status-badge checked-in">✅ Checked In</span>
                                                <?php if ($a->hours_worked > 0): ?>
                                                    <br><small><?php echo esc_html($a->hours_worked); ?> hrs</small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="status-badge pending">⏳ Not Checked In</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Actions">
                                            <button class="button-small" 
                                                    onclick="sendVolunteerMessage(<?php echo esc_js($a->volunteer_id); ?>, '<?php echo esc_js($email); ?>', '<?php echo esc_js($name); ?>', <?php echo esc_js($area->id); ?>)">
                                                ✉️ Message
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="area-actions">
                            <button class="button button-secondary" 
                                    onclick="messageAllVolunteers(<?php echo esc_js($area->id); ?>, '<?php echo esc_js($area->area_name); ?>')">
                                📧 Message All Volunteers
                            </button>
                            <button class="button button-secondary" 
                                    onclick="exportAreaReport(<?php echo esc_js($area->id); ?>, '<?php echo esc_js($area->area_name); ?>')">
                                📊 Export Report
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (current_user_can('manage_options')): ?>
                <div class="admin-quick-links">
                    <h3>🔧 Admin Quick Links</h3>
                    <p>
                        <a href="<?php echo admin_url('admin.php?page=oha-volunteer-assign'); ?>" class="button">Assign Volunteers</a>
                        <a href="<?php echo admin_url('admin.php?page=oha-volunteer-shifts'); ?>" class="button">Manage Shifts</a>
                        <a href="<?php echo admin_url('admin.php?page=oha-volunteer-areas'); ?>" class="button">Manage Areas</a>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Message Modal -->
        <div id="message-modal" style="display:none;">
            <div class="modal-overlay" onclick="closeMessageModal()"></div>
            <div class="modal-content">
                <h2>Send Message</h2>
                <form id="message-form">
                    <input type="hidden" name="volunteer_id" id="msg-volunteer-id">
                    <input type="hidden" name="volunteer_email" id="msg-volunteer-email">
                    <input type="hidden" name="area_id" id="msg-area-id">
                    
                    <p><strong>To:</strong> <span id="msg-volunteer-name"></span></p>
                    
                    <p>
                        <label><strong>Subject:</strong></label>
                        <input type="text" name="subject" required style="width: 100%; padding: 8px;">
                    </p>
                    
                    <p>
                        <label><strong>Message:</strong></label>
                        <textarea name="message" required rows="8" style="width: 100%; padding: 8px;"></textarea>
                    </p>
                    
                    <p>
                        <button type="submit" class="button button-primary">Send Message</button>
                        <button type="button" class="button" onclick="closeMessageModal()">Cancel</button>
                    </p>
                </form>
                <div id="message-result"></div>
            </div>
        </div>

        <style>
            .oha-vh-manager-dashboard { max-width: 1200px; margin: 0 auto; padding: 20px; }
            .dashboard-header { text-align: center; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 2px solid #e74c3c; }
            .dashboard-header h2 { margin: 0 0 10px 0; color: #000; }
            .dashboard-subtitle { color: #666; font-size: 18px; }
            .dashboard-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
            .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
            .stat-number { font-size: 48px; font-weight: 700; margin-bottom: 10px; }
            .stat-label { font-size: 16px; opacity: 0.9; }
            .manager-area-card { background: #fff; border: 1px solid #ddd; border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
            .area-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
            .area-card-header h3 { margin: 0; color: #000; }
            .area-stats-mini { display: flex; gap: 10px; }
            .stat-badge { padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; background: #e3f2fd; color: #1976d2; }
            .stat-badge.checked-in { background: #d4edda; color: #155724; }
            .area-description { color: #666; margin-bottom: 20px; }
            .no-volunteers { text-align: center; padding: 30px; color: #999; }
            .volunteers-table-wrap { overflow-x: auto; margin-bottom: 20px; }
            .volunteers-table { width: 100%; border-collapse: collapse; }
            .volunteers-table th { background: #2c3e50; color: #fff; padding: 12px; text-align: left; font-weight: 600; }
            .volunteers-table td { padding: 12px; border-bottom: 1px solid #eee; color: #000; }
            .volunteers-table tr:hover { background: #f9f9f9; }
            .status-badge { padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; white-space: nowrap; }
            .status-badge.checked-in { background: #d4edda; color: #155724; }
            .status-badge.pending { background: #fff3cd; color: #856404; }
            .button-small { padding: 6px 12px; font-size: 13px; cursor: pointer; }
            .area-actions { display: flex; gap: 10px; flex-wrap: wrap; padding-top: 15px; border-top: 1px solid #eee; }
            .admin-quick-links { background: #e3f2fd; padding: 25px; border-radius: 12px; margin-top: 30px; }
            .admin-quick-links h3 { margin-top: 0; color: #000; }
            .admin-quick-links .button { margin-right: 10px; margin-bottom: 10px; }
            .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 99999; }
            .modal-content { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 100000; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; }
            .modal-content h2 { margin-top: 0; }
            #message-result { margin-top: 15px; padding: 15px; border-radius: 6px; }
            #message-result.success { background: #d4edda; color: #155724; }
            #message-result.error { background: #f8d7da; color: #721c24; }
            @media (max-width: 768px) {
                .area-card-header { flex-direction: column; align-items: flex-start; gap: 10px; }
                .volunteers-table thead { display: none; }
                .volunteers-table tr { display: block; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 8px; padding: 15px; }
                .volunteers-table td { display: block; padding: 8px 0; border: none; }
                .volunteers-table td:before { content: attr(data-label); font-weight: 600; display: block; margin-bottom: 5px; color: #000; }
                .area-actions { flex-direction: column; }
                .area-actions button { width: 100%; }
            }
        </style>

        <script>
        function sendVolunteerMessage(volunteerId, email, name, areaId) {
            document.getElementById('message-modal').style.display = 'block';
            document.getElementById('msg-volunteer-id').value = volunteerId;
            document.getElementById('msg-volunteer-email').value = email;
            document.getElementById('msg-volunteer-name').textContent = name;
            document.getElementById('msg-area-id').value = areaId;
            document.getElementById('message-form').reset();
            document.getElementById('message-result').style.display = 'none';
        }

        function closeMessageModal() {
            document.getElementById('message-modal').style.display = 'none';
        }

        function messageAllVolunteers(areaId, areaName) {
            alert('Bulk messaging coming in next update! For now, message volunteers individually.');
        }

        function exportAreaReport(areaId, areaName) {
            window.location.href = '<?php echo admin_url('admin-ajax.php'); ?>?action=oha_vh_export_area_report&area_id=' + areaId + '&nonce=<?php echo wp_create_nonce('oha_vh_export'); ?>';
        }

        document.getElementById('message-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'oha_vh_manager_send_message');
            formData.append('nonce', '<?php echo wp_create_nonce('oha_vh_message'); ?>');
            
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                const result = document.getElementById('message-result');
                result.style.display = 'block';
                if (data.success) {
                    result.className = 'success';
                    result.textContent = 'Message sent successfully!';
                    setTimeout(() => closeMessageModal(), 2000);
                } else {
                    result.className = 'error';
                    result.textContent = data.data.message || 'Error sending message';
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX: Send message to volunteer
     */
    public static function ajax_send_message() {
        check_ajax_referer('oha_vh_message', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Must be logged in']);
        }

        $data = [
            'from_user_id' => get_current_user_id(),
            'to_volunteer_id' => absint($_POST['volunteer_id'] ?? 0) ?: null,
            'to_email' => sanitize_email($_POST['volunteer_email'] ?? ''),
            'area_id' => absint($_POST['area_id'] ?? 0) ?: null,
            'subject' => sanitize_text_field($_POST['subject'] ?? ''),
            'message' => sanitize_textarea_field($_POST['message'] ?? '')
        ];

        if (empty($data['subject']) || empty($data['message'])) {
            wp_send_json_error(['message' => 'Subject and message required']);
        }

        // Save to database
        Database::send_message($data);

        // Send actual email
        $to = $data['to_email'];
        $subject = $data['subject'];
        $message = $data['message'];
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        $from_user = wp_get_current_user();
        $message .= "\n\n---\nFrom: " . $from_user->display_name . "\nEmail: " . $from_user->user_email;

        wp_mail($to, $subject, nl2br($message), $headers);

        wp_send_json_success(['message' => 'Message sent!']);
    }
}
