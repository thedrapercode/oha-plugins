<?php
/**
 * OHA Volunteer Hub - AJAX Handlers (Phase 3)
 */

namespace OHA\VH;

if (!defined('ABSPATH')) { exit; }

class Ajax {
    public static function init() {
        // Volunteer management handlers
        add_action('wp_ajax_oha_vh_get_volunteer_quick',[__CLASS__,'get_volunteer_quick']);
        // oha_vh_quick_approve: handled by Board class (sends approval email) - do not register here
        add_action('wp_ajax_oha_vh_get_volunteer_details', '\\OHA\\VH\\Admin::ajax_get_volunteer_details');
        add_action('wp_ajax_oha_vh_manual_add_volunteer', [__CLASS__, 'manual_add_volunteer']);
        add_action('wp_ajax_oha_vh_delete_volunteer', [__CLASS__, 'delete_volunteer']);
        
        // Phase 3: Check-in handler (allow logged-in volunteers)
        add_action('wp_ajax_oha_vh_check_in', [__CLASS__, 'check_in_volunteer']);
        add_action('wp_ajax_nopriv_oha_vh_check_in', [__CLASS__, 'check_in_volunteer']); 
        
        // Export functionality
        add_action('init',[__CLASS__,'maybe_export_reports']);
    }

    public static function forbidden() { 
        wp_send_json_error(['message'=>'Unauthorized'], 401); 
    }

    protected static function check_nonce($action = 'oha_vh_nonce') {
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field($_REQUEST['nonce']) : '';
        if (!wp_verify_nonce($nonce, $action)) {
            wp_send_json_error(['message'=>'Invalid nonce'], 403);
        }
    }

    /**
     * Get volunteer quick view (for board hub shortcode)
     */
    public static function get_volunteer_quick() {
        if (!Core::can_manage()) self::forbidden();
        self::check_nonce();

        $volunteer_id = absint($_POST['volunteer_id'] ?? 0);
        $volunteer = Database::get_volunteer($volunteer_id);

        if (!$volunteer) {
            wp_send_json_error(['message' => 'Volunteer not found']);
        }

        $areas = Database::get_areas();
        $preferred_areas = json_decode($volunteer->preferred_areas, true) ?: [];
        $area_names = [];
        foreach ($preferred_areas as $area_id) {
            $area = Database::get_area($area_id);
            if ($area) {
                $area_names[] = $area->icon . ' ' . $area->area_name;
            }
        }

        ob_start();
        ?>
        <div class="volunteer-quick-view">
            <h2><?php echo esc_html($volunteer->first_name . ' ' . $volunteer->last_name); ?></h2>
            
            <div class="quick-info-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 20px 0;">
                <div>
                    <strong>Email:</strong><br>
                    <a href="mailto:<?php echo esc_attr($volunteer->email); ?>"><?php echo esc_html($volunteer->email); ?></a>
                </div>
                <div>
                    <strong>Phone:</strong><br>
                    <?php echo esc_html($volunteer->phone ?: 'Not provided'); ?>
                </div>
                <div>
                    <strong>Experience:</strong><br>
                    <?php echo esc_html(ucfirst($volunteer->experience_level)); ?>
                </div>
                <div>
                    <strong>Status:</strong><br>
                    <?php echo esc_html(ucfirst($volunteer->status)); ?>
                </div>
            </div>

            <div style="margin: 15px 0;">
                <strong>Preferred Areas:</strong><br>
                <?php echo esc_html(implode(', ', $area_names) ?: 'None specified'); ?>
            </div>

            <?php if ($volunteer->special_skills): ?>
            <div style="margin: 15px 0;">
                <strong>Special Skills:</strong><br>
                <?php echo esc_html($volunteer->special_skills); ?>
            </div>
            <?php endif; ?>

            <?php if ($volunteer->availability): ?>
            <div style="margin: 15px 0;">
                <strong>Availability:</strong><br>
                <?php echo esc_html($volunteer->availability); ?>
            </div>
            <?php endif; ?>

            <div style="margin: 15px 0;">
                <strong>Emergency Contact:</strong><br>
                <?php echo esc_html($volunteer->emergency_contact ?: 'Not provided'); ?>
            </div>

            <div style="margin: 15px 0;">
                <strong>Signed Up:</strong><br>
                <?php echo esc_html(date('F j, Y g:i A', strtotime($volunteer->signup_date))); ?>
            </div>

            <?php if ($volunteer->notes): ?>
            <div style="margin: 15px 0; padding: 15px; background: #f9f9f9; border-radius: 6px;">
                <strong>Internal Notes:</strong><br>
                <?php echo nl2br(esc_html($volunteer->notes)); ?>
            </div>
            <?php endif; ?>

            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                <?php if ($volunteer->status === 'pending'): ?>
                    <button class="button button-primary" onclick="quickApproveInModal(<?php echo esc_js($volunteer_id); ?>)">
                        ✅ Approve Volunteer
                    </button>
                    <button class="button" onclick="closeQuickModal()">Close</button>
                <?php else: ?>
                    <button class="button button-primary" onclick="closeQuickModal()">Close</button>
                    <a href="<?php echo admin_url('admin.php?page=oha-volunteer-signups'); ?>" class="button">
                        View Full Details in Admin
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <script>
        function quickApproveInModal(volunteerId) {
            if (confirm('Approve this volunteer?')) {
                jQuery.post(OHA_VH_AJAX.ajaxurl, {
                    action: 'oha_vh_quick_approve',
                    volunteer_id: volunteerId,
                    nonce: OHA_VH_AJAX.nonce
                }, function(response) {
                    if (response.success) {
                        closeQuickModal();
                        location.reload();
                    } else {
                        alert(response.data.message || 'Error');
                    }
                });
            }
        }
        </script>
        <?php
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Quick approve volunteer (for board hub shortcode)
     */
    public static function quick_approve() {
        if (!Core::can_manage()) self::forbidden();
        self::check_nonce('oha_vh_board');

        $volunteer_id = absint($_POST['volunteer_id'] ?? 0);
        
        $result = Database::update_volunteer($volunteer_id, ['status' => 'approved']);

        if ($result === false) {
            wp_send_json_error(['message' => 'Failed to approve volunteer']);
        }

        // Optionally send approval email
        // Emails::send_approval_email($volunteer_id);

        wp_send_json_success(['message' => 'Volunteer approved successfully!']);
    }

    /**
     * Delete (trash) volunteer
     */
    public static function delete_volunteer() {
        if (!current_user_can('manage_options')) self::forbidden();
        self::check_nonce('oha_vh_board');

        $volunteer_id = absint($_POST['volunteer_id'] ?? 0);
        
        if (!$volunteer_id) {
            wp_send_json_error(['message' => 'Invalid volunteer ID']);
        }
        
        // Move to trash status instead of actually deleting
        $result = Database::update_volunteer($volunteer_id, ['status' => 'trashed']);

        if ($result === false) {
            wp_send_json_error(['message' => 'Failed to delete volunteer']);
        }

        wp_send_json_success(['message' => 'Volunteer moved to trash']);
    }

    /**
     * Check in volunteer to assignment (Phase 3)
     */
    public static function check_in_volunteer() {
        // Check nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'oha_vh_checkin')) {
            wp_send_json_error(['message' => 'Invalid security token']);
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'You must be logged in to check in']);
        }

        $assignment_id = absint($_POST['assignment_id'] ?? 0);
        if (!$assignment_id) {
            wp_send_json_error(['message' => 'Invalid assignment']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteer_assignments';
        
        // Get assignment and verify ownership
        $user_id = get_current_user_id();
        $user_email = wp_get_current_user()->user_email;
        
        $assignment = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, v.user_id, v.email 
             FROM $table a 
             LEFT JOIN {$wpdb->prefix}oha_volunteers v ON a.volunteer_id = v.id
             WHERE a.id = %d",
            $assignment_id
        ));
        
        if (!$assignment) {
            wp_send_json_error(['message' => 'Assignment not found']);
        }
        
        // Verify this assignment belongs to current user
        $is_owner = ($assignment->volunteer_id && $assignment->user_id == $user_id) || 
                    ($assignment->email == $user_email) ||
                    ($assignment->manual_email && $assignment->manual_email == $user_email);
        
        if (!$is_owner && !Core::can_manage()) {
            wp_send_json_error(['message' => 'This assignment does not belong to you']);
        }
        
        // Already checked in?
        if ($assignment->checked_in) {
            wp_send_json_error(['message' => 'Already checked in at ' . date('g:i A', strtotime($assignment->checked_in_time))]);
        }
        
        // Perform check-in
        $result = $wpdb->update(
            $table,
            [
                'checked_in' => 1,
                'checked_in_time' => current_time('mysql')
            ],
            ['id' => $assignment_id],
            ['%d', '%s'],
            ['%d']
        );
        
        if ($result === false) {
            wp_send_json_error(['message' => 'Failed to check in. Please try again.']);
        }
        
        wp_send_json_success([
            'message' => 'Checked in successfully!',
            'time' => current_time('g:i A')
        ]);
    }

    /**
     * Export reports and area data
     */
    public static function maybe_export_reports() {
        if (!isset($_GET['action'])) return;
        
        // Export area volunteer report
        if ($_GET['action'] === 'oha_vh_export_area_report' && isset($_GET['area_id']) && isset($_GET['nonce'])) {
            if (!wp_verify_nonce($_GET['nonce'], 'oha_vh_export')) {
                wp_die('Invalid nonce');
            }
            
            if (!Core::can_manage()) {
                wp_die('Unauthorized');
            }
            
            $area_id = absint($_GET['area_id']);
            $area = Database::get_area($area_id);
            if (!$area) {
                wp_die('Area not found');
            }
            
            $assignments = Database::get_assignments_by_area($area_id);
            
            $filename = sanitize_file_name('area-' . $area->area_slug . '-' . date('Ymd') . '.csv');
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename);
            
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Volunteer Name', 'Email', 'Phone', 'Shift Date', 'Shift Time', 'Checked In', 'Hours Worked']);
            
            foreach ($assignments as $a) {
                $name = $a->first_name ? ($a->first_name . ' ' . $a->last_name) : $a->manual_name;
                $email = $a->email ?: $a->manual_email;
                $phone = $a->phone ?: $a->manual_phone;
                $shift_date = $a->shift_date ? date('M j, Y', strtotime($a->shift_date)) : 'All Day';
                $shift_time = ($a->shift_type === 'time_slot' && $a->shift_start) ? 
                             date('g:i A', strtotime($a->shift_start)) . ' - ' . date('g:i A', strtotime($a->shift_end)) : 
                             'All Day';
                $checked_in = $a->checked_in ? 'Yes' : 'No';
                $hours = $a->hours_worked ?: '0';
                
                fputcsv($out, [$name, $email, $phone, $shift_date, $shift_time, $checked_in, $hours]);
            }
            
            fclose($out);
            exit;
        }
        
        // Export all volunteers
        if ($_GET['action'] === 'oha_vh_export_volunteers' && isset($_GET['nonce'])) {
            if (!wp_verify_nonce($_GET['nonce'], 'oha_vh_export')) {
                wp_die('Invalid nonce');
            }
            
            if (!Core::can_manage()) {
                wp_die('Unauthorized');
            }
            
            $volunteers = Database::get_volunteers(['limit' => 1000]);
            
            $filename = 'oha-volunteers-' . date('Ymd') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename);
            
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'Experience', 'Status', 'Signup Date']);
            
            foreach ($volunteers as $v) {
                fputcsv($out, [
                    $v->id,
                    $v->first_name . ' ' . $v->last_name,
                    $v->email,
                    $v->phone,
                    ucfirst($v->experience_level),
                    ucfirst($v->status),
                    date('Y-m-d H:i', strtotime($v->signup_date))
                ]);
            }
            
            fclose($out);
            exit;
        }
    }
    
    /**
     * Manually add a volunteer (for board members)
     */
    public static function manual_add_volunteer() {
        if (!Core::can_manage()) self::forbidden();
        self::check_nonce('oha_vh_board');
        
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteers';
        
        // Sanitize inputs
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $user_id = absint($_POST['user_id'] ?? 0);
        $special_skills = sanitize_textarea_field($_POST['special_skills'] ?? '');
        $status = sanitize_text_field($_POST['status'] ?? 'approved');
        
        // Validate required fields
        if (empty($first_name) || empty($last_name) || empty($email)) {
            wp_send_json_error(['message' => 'First name, last name, and email are required']);
        }
        
        // Check if email already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE email = %s",
            $email
        ));
        
        if ($existing) {
            wp_send_json_error(['message' => 'A volunteer with this email already exists']);
        }
        
        // Insert volunteer
        $inserted = $wpdb->insert(
            $table,
            [
                'user_id' => $user_id ?: null,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'special_skills' => $special_skills,
                'status' => $status,
                'experience_level' => 'unknown',
                'signup_date' => current_time('mysql'),
                'preferred_areas' => json_encode([]),
                'availability' => 'Manually added by board member',
                'emergency_contact' => '',
                'notes' => ''
            ],
            [
                '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
            ]
        );
        
        if ($inserted === false) {
            error_log('Manual volunteer add error: ' . $wpdb->last_error);
            wp_send_json_error(['message' => 'Database error: ' . $wpdb->last_error]);
        }
        
        $volunteer_id = $wpdb->insert_id;
        
        // Send confirmation email if approved
        if ($status === 'approved') {
            Emails::send_confirmation_email($volunteer_id);
        }
        
        wp_send_json_success([
            'message' => 'Volunteer added successfully',
            'volunteer_id' => $volunteer_id
        ]);
    }
}
