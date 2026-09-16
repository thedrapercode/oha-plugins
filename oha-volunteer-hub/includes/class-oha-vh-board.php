<?php
/**
 * OHA Volunteer Hub - Board Actions (Phase 4)
 * Handles all AJAX actions for board hub tabs
 */

namespace OHA\VH;

if (!defined('ABSPATH')) { exit; }

class Board {

    public static function init() {
        // Volunteer actions
        // oha_vh_get_volunteer_details: handled by Admin::ajax_get_volunteer_details via Ajax::init (no nonce required, works for all)
        add_action('wp_ajax_oha_vh_quick_approve', [__CLASS__, 'quick_approve']);
        add_action('wp_ajax_oha_vh_quick_reject', [__CLASS__, 'quick_reject']);
        
        // Shift actions
        add_action('wp_ajax_oha_vh_get_area_shifts', [__CLASS__, 'get_area_shifts']);
        add_action('wp_ajax_oha_vh_get_shift', [__CLASS__, 'get_shift']);
        add_action('wp_ajax_oha_vh_create_shift', [__CLASS__, 'create_shift']);
        add_action('wp_ajax_oha_vh_update_shift', [__CLASS__, 'update_shift']);
        add_action('wp_ajax_oha_vh_delete_shift', [__CLASS__, 'delete_shift']);
        
        // Area actions
        add_action('wp_ajax_oha_vh_get_area', [__CLASS__, 'get_area']);
        add_action('wp_ajax_oha_vh_create_area', [__CLASS__, 'create_area']);
        add_action('wp_ajax_oha_vh_update_area', [__CLASS__, 'update_area']);
        add_action('wp_ajax_oha_vh_delete_area', [__CLASS__, 'delete_area']);
        add_action('wp_ajax_oha_vh_update_area_managers', [__CLASS__, 'update_area_managers']);
        
        // oha_vh_assign_volunteer: handled by Assignments::ajax_assign_volunteer (accepts both board and admin nonces)
        
        // Messaging actions
        add_action('wp_ajax_oha_vh_send_area_message', [__CLASS__, 'send_area_message']);
        add_action('wp_ajax_oha_vh_get_area_assignments_count', [__CLASS__, 'get_area_assignments_count']);

        // Communication actions
        add_action('wp_ajax_oha_vh_resend_assignment_email', [__CLASS__, 'resend_assignment_email']);
        add_action('wp_ajax_oha_vh_broadcast_email', [__CLASS__, 'broadcast_email']);

        // Assignment management
        add_action('wp_ajax_oha_vh_remove_assignment', [__CLASS__, 'remove_assignment']);
        add_action('wp_ajax_oha_vh_update_assignment_area', [__CLASS__, 'update_assignment_area']);

        // No-show and ban actions
        add_action('wp_ajax_oha_vh_mark_no_show',      [__CLASS__, 'mark_no_show']);
        add_action('wp_ajax_oha_vh_toggle_ban',        [__CLASS__, 'toggle_ban']);
        add_action('wp_ajax_oha_vh_bulk_mark_no_show', [__CLASS__, 'bulk_mark_no_show']);

        // Export action
        add_action('admin_post_oha_vh_export_volunteers', [__CLASS__, 'export_volunteers']);
    }

    /**
     * Get volunteer details for modal
     */
    public static function get_volunteer_details() {
        check_ajax_referer('oha_vh_board', 'nonce');
        
        if (!Core::can_manage()) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $volunteer_id = isset($_POST['volunteer_id']) ? intval($_POST['volunteer_id']) : 0;
        $volunteer = Database::get_volunteer($volunteer_id);
        
        if (!$volunteer) {
            wp_send_json_error(['message' => 'Volunteer not found']);
        }

        $preferred = json_decode($volunteer->preferred_areas, true) ?: [];
        $area_names = [];
        foreach ($preferred as $area_id) {
            $area = Database::get_area($area_id);
            if ($area) $area_names[] = $area->icon . ' ' . $area->area_name;
        }

        $assignments = Database::get_volunteer_assignments($volunteer_id);

        ob_start();
        ?>
        <div class="volunteer-details">
            <h3><?php echo esc_html($volunteer->first_name . ' ' . $volunteer->last_name); ?></h3>
            
            <div class="detail-section">
                <strong>Contact Information:</strong>
                <p>
                    📧 <?php echo esc_html($volunteer->email); ?><br>
                    <?php if ($volunteer->phone): ?>
                        📱 <?php echo esc_html($volunteer->phone); ?>
                    <?php endif; ?>
                </p>
            </div>

            <div class="detail-section">
                <strong>Experience Level:</strong>
                <p><?php echo esc_html(ucfirst($volunteer->experience_level)); ?></p>
            </div>

            <?php if (!empty($area_names)): ?>
                <div class="detail-section">
                    <strong>Preferred Areas:</strong>
                    <p><?php echo esc_html(implode(', ', $area_names)); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($volunteer->special_skills): ?>
                <div class="detail-section">
                    <strong>Special Skills:</strong>
                    <p><?php echo esc_html($volunteer->special_skills); ?></p>
                </div>
            <?php endif; ?>

            <div class="detail-section">
                <strong>👕 T-Shirt Size:</strong>
                <p><?php echo esc_html((isset($volunteer->tshirt_size) && $volunteer->tshirt_size) ? $volunteer->tshirt_size : 'Not specified'); ?></p>
            </div>

            <div class="detail-section">
                <strong>How Did You Hear About Us:</strong>
                <p><?php echo esc_html((isset($volunteer->how_did_you_hear) && $volunteer->how_did_you_hear) ? $volunteer->how_did_you_hear : 'Not specified'); ?></p>
            </div>

            <?php if ($volunteer->notes): ?>
                <div class="detail-section">
                    <strong>Notes / Availability:</strong>
                    <p><?php echo nl2br(esc_html($volunteer->notes)); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($volunteer->emergency_contact): ?>
                <div class="detail-section">
                    <strong>Emergency Contact:</strong>
                    <p><?php echo esc_html($volunteer->emergency_contact); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($assignments)): ?>
                <div class="detail-section">
                    <strong>Current Assignments:</strong>
                    <ul>
                        <?php foreach ($assignments as $assignment): 
                            $area = Database::get_area($assignment->area_id);
                        ?>
                            <li><?php echo esc_html($area->icon . ' ' . $area->area_name); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="detail-section">
                <strong>Status:</strong>
                <p><span class="status-badge status-<?php echo esc_attr($volunteer->status); ?>">
                    <?php echo esc_html(ucfirst($volunteer->status)); ?>
                </span></p>
            </div>

            <div class="detail-section">
                <strong>Signup Date:</strong>
                <p><?php echo date('F j, Y', strtotime($volunteer->signup_date)); ?></p>
            </div>
        </div>

        <style>
        .volunteer-details .detail-section {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        .volunteer-details .detail-section:last-child {
            border-bottom: none;
        }
        .volunteer-details strong {
            display: block;
            margin-bottom: 8px;
            color: #333;
        }
        .volunteer-details p {
            margin: 0;
            color: #666;
        }
        </style>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(['html' => $html]);
    }

    /**
     * Quick approve volunteer
     */
    public static function quick_approve() {
        check_ajax_referer('oha_vh_board', 'nonce');
        
        if (!Core::can_manage()) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $volunteer_id = isset($_POST['volunteer_id']) ? intval($_POST['volunteer_id']) : 0;
        
        $result = Database::update_volunteer($volunteer_id, ['status' => 'approved']);
        
        if ($result) {
            // Send approval email (only if enabled in settings)
            if (Settings::get('send_approval_email', '1') === '1') {
                Emails::send_approval_email($volunteer_id);
            }
            wp_send_json_success();
        } else {
            wp_send_json_error(['message' => 'Failed to approve volunteer']);
        }
    }

    /**
     * Quick reject volunteer
     */
    public static function quick_reject() {
        check_ajax_referer('oha_vh_board', 'nonce');
        
        if (!Core::can_manage()) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $volunteer_id = isset($_POST['volunteer_id']) ? intval($_POST['volunteer_id']) : 0;
        
        $result = Database::update_volunteer($volunteer_id, ['status' => 'rejected']);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(['message' => 'Failed to reject volunteer']);
        }
    }

    /**
     * Mark a single volunteer as no-show (status → no_show)
     */
    public static function mark_no_show() {
        check_ajax_referer('oha_vh_board', 'nonce');
        if (!Core::can_manage()) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $volunteer_id = absint($_POST['volunteer_id'] ?? 0);
        if (!$volunteer_id) {
            wp_send_json_error(['message' => 'Invalid volunteer ID']);
        }

        $result = Database::update_volunteer($volunteer_id, ['status' => 'no_show']);
        if ($result === false) {
            wp_send_json_error(['message' => 'Failed to update status']);
        }

        wp_send_json_success(['message' => 'Marked as no-show']);
    }

    /**
     * Toggle the banned flag on a volunteer (cross-year block).
     * Returns the new banned state so the UI can update without a reload.
     */
    public static function toggle_ban() {
        check_ajax_referer('oha_vh_board', 'nonce');
        if (!Core::can_manage()) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $volunteer_id = absint($_POST['volunteer_id'] ?? 0);
        if (!$volunteer_id) {
            wp_send_json_error(['message' => 'Invalid volunteer ID']);
        }

        $volunteer = Database::get_volunteer($volunteer_id);
        if (!$volunteer) {
            wp_send_json_error(['message' => 'Volunteer not found']);
        }

        $new_banned = $volunteer->banned ? 0 : 1;
        $result = Database::update_volunteer($volunteer_id, ['banned' => $new_banned]);
        if ($result === false) {
            wp_send_json_error(['message' => 'Failed to update ban status']);
        }

        wp_send_json_success([
            'banned'  => $new_banned,
            'message' => $new_banned ? 'Volunteer banned from future signups' : 'Ban removed',
        ]);
    }

    /**
     * Bulk mark no-shows: sets status = no_show on every volunteer who is
     * assigned for the active year but has not checked in to any assignment.
     * Returns the count of records updated.
     */
    public static function bulk_mark_no_show() {
        check_ajax_referer('oha_vh_board', 'nonce');
        if (!Core::can_manage()) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        global $wpdb;
        $tv = $wpdb->prefix . 'oha_volunteers';
        $ta = $wpdb->prefix . 'oha_volunteer_assignments';
        $active_year = Database::get_active_year();

        // Find all assigned volunteers for the active year who never checked in
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT v.id
             FROM {$tv} v
             WHERE v.status = 'assigned'
               AND v.convention_year = %d
               AND NOT EXISTS (
                   SELECT 1 FROM {$ta} a
                   WHERE a.volunteer_id = v.id AND a.checked_in = 1
               )",
            $active_year
        ));

        if (empty($ids)) {
            wp_send_json_success(['count' => 0, 'message' => 'No unchecked-in assigned volunteers found.']);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$tv} SET status = 'no_show' WHERE id IN ($placeholders)",
                ...$ids
            )
        );

        wp_send_json_success([
            'count'   => $updated,
            'message' => $updated . ' volunteer(s) marked as no-show.',
        ]);
    }

    /**
     * Get shifts for an area
     */
    public static function get_area_shifts() {
        check_ajax_referer('oha_vh_board', 'nonce');
        
        if (!Core::can_manage()) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;
        $shifts = Database::get_shifts_by_area($area_id);

        global $wpdb;
        $formatted_shifts = [];
        foreach ($shifts as $shift) {
            $label = date('M j, Y', strtotime($shift->shift_date));
            if ($shift->shift_type === 'time_slot') {
                $label .= ' ' . date('g:i A', strtotime($shift->shift_start)) . ' - ' . date('g:i A', strtotime($shift->shift_end));
            } else {
                $label .= ' (All Day)';
            }
            $filled = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}oha_volunteer_assignments WHERE shift_id = %d",
                $shift->id
            ));
            $label .= ' [' . $filled . '/' . $shift->slots_available . ']';
            
            $formatted_shifts[] = [
                'id' => $shift->id,
                'label' => $label
            ];
        }
        
        wp_send_json_success(['shifts' => $formatted_shifts]);
    }

    /**
     * Get shift details
     */
    public static function get_shift() {
        check_ajax_referer('oha_vh_board', 'nonce');
        
        if (!Core::can_manage()) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $shift_id = isset($_POST['shift_id']) ? intval($_POST['shift_id']) : 0;
        
        global $wpdb;
        $shift = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}oha_volunteer_shifts WHERE id = %d",
            $shift_id
        ));
        
        if (!$shift) {
            wp_send_json_error(['message' => 'Shift not found']);
        }
        
        wp_send_json_success((array)$shift);
    }

    /**
     * Create new shift
     */
    public static function create_shift() {
        check_ajax_referer('oha_vh_board', 'nonce');
        
        if (!Core::can_manage()) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $data = [
            'area_id' => isset($_POST['area_id']) ? intval($_POST['area_id']) : 0,
            'shift_type' => sanitize_text_field($_POST['shift_type'] ?? 'all_day'),
            'shift_date' => sanitize_text_field($_POST['shift_date'] ?? ''),
            'slots_available' => intval($_POST['slots_available'] ?? 10)
        ];

        if ($data['shift_type'] === 'time_slot') {
            $data['shift_start'] = sanitize_text_field($_POST['shift_start'] ?? '');
            $data['shift_end'] = sanitize_text_field($_POST['shift_end'] ?? '');
        }

        $result = Database::create_shift($data);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(['message' => 'Failed to create shift']);
        }
    }

    /**
     * Update shift
     */
    public static function update_shift() {
        check_ajax_referer('oha_vh_board', 'nonce');
        
        if (!Core::can_manage()) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $shift_id = isset($_POST['shift_id']) ? intval($_POST['shift_id']) : 0;
        
        $data = [
            'shift_type' => sanitize_text_field($_POST['shift_type'] ?? 'all_day'),
            'shift_date' => sanitize_text_field($_POST['shift_date'] ?? ''),
            'slots_available' => intval($_POST['slots_available'] ?? 10)
        ];

        if ($data['shift_type'] === 'time_slot') {
            $data['shift_start'] = sanitize_text_field($_POST['shift_start'] ?? '');
            $data['shift_end'] = sanitize_text_field($_POST['shift_end'] ?? '');
        } else {
            $data['shift_start'] = null;
            $data['shift_end'] = null;
        }

        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'oha_volunteer_shifts',
            $data,
            ['id' => $shift_id]
        );
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(['message' => 'Failed to update shift']);
        }
    }

    /**
     * Delete shift
     */
    public static function delete_shift() {
        check_ajax_referer('oha_vh_board', 'nonce');
        
        if (!Core::can_manage()) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $shift_id = isset($_POST['shift_id']) ? intval($_POST['shift_id']) : 0;
        
        global $wpdb;
        
        // Delete assignments for this shift
        $wpdb->delete(
            $wpdb->prefix . 'oha_volunteer_assignments',
            ['shift_id' => $shift_id]
        );
        
        // Delete shift
        $result = $wpdb->delete(
            $wpdb->prefix . 'oha_volunteer_shifts',
            ['id' => $shift_id]
        );
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(['message' => 'Failed to delete shift']);
        }
    }

    /**
     * Get area details
     */
    public static function get_area() {
        check_ajax_referer('oha_vh_board', 'nonce');
        
        if (!Core::can_manage()) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;
        $area = Database::get_area($area_id);
        
        if (!$area) {
            wp_send_json_error(['message' => 'Area not found']);
        }
        
        // Decode area_managers
        $area->area_managers = json_decode($area->area_managers, true) ?: [];
        
        wp_send_json_success((array)$area);
    }

    /**
     * Create new area
     */
    public static function create_area() {
        check_ajax_referer('oha_vh_board', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Only administrators can create areas']);
        }

        global $wpdb;
        
        $area_name = sanitize_text_field($_POST['area_name'] ?? '');
        $area_slug = sanitize_title($area_name);
        
        // Make slug unique if needed
        $original_slug = $area_slug;
        $counter = 1;
        while ($wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}oha_volunteer_areas WHERE area_slug = %s",
            $area_slug
        ))) {
            $area_slug = $original_slug . '-' . $counter;
            $counter++;
        }
        
        $data = [
            'area_name' => $area_name,
            'alt_name' => sanitize_text_field($_POST['alt_name'] ?? ''),
            'area_slug' => $area_slug,
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'icon' => sanitize_text_field($_POST['icon'] ?? '🎯'),
            'color' => sanitize_hex_color($_POST['area_color'] ?? '#e74c3c'),
            'min_volunteers' => intval($_POST['min_volunteers'] ?? 1),
            'max_volunteers' => intval($_POST['max_volunteers'] ?? 10),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        $result = $wpdb->insert($wpdb->prefix . 'oha_volunteer_areas', $data);
        
        if ($result) {
            wp_send_json_success(['area_id' => $wpdb->insert_id]);
        } else {
            error_log('Area creation error: ' . $wpdb->last_error);
            wp_send_json_error(['message' => 'Failed to create area: ' . $wpdb->last_error]);
        }
    }

    /**
     * Update area
     */
    public static function update_area() {
        check_ajax_referer('oha_vh_board', 'nonce');
        
        if (!Core::can_manage()) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;
        
        global $wpdb;
        
        $data = [
            'area_name' => sanitize_text_field($_POST['area_name'] ?? ''),
            'alt_name' => sanitize_text_field($_POST['alt_name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'icon' => sanitize_text_field($_POST['icon'] ?? '🎯'),
            'color' => sanitize_hex_color($_POST['area_color'] ?? '#e74c3c'),
            'min_volunteers' => intval($_POST['min_volunteers'] ?? 1),
            'max_volunteers' => intval($_POST['max_volunteers'] ?? 10),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        $result = $wpdb->update(
            $wpdb->prefix . 'oha_volunteer_areas',
            $data,
            ['id' => $area_id]
        );
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            error_log('Area update error: ' . $wpdb->last_error);
            wp_send_json_error(['message' => 'Failed to update area: ' . $wpdb->last_error]);
        }
    }

    /**
     * Delete area
     */
    public static function delete_area() {
        check_ajax_referer('oha_vh_board', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Only administrators can delete areas']);
        }

        $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;
        
        global $wpdb;
        
        // Delete assignments
        $wpdb->delete($wpdb->prefix . 'oha_volunteer_assignments', ['area_id' => $area_id]);
        
        // Delete shifts
        $wpdb->delete($wpdb->prefix . 'oha_volunteer_shifts', ['area_id' => $area_id]);
        
        // Delete area
        $result = $wpdb->delete($wpdb->prefix . 'oha_volunteer_areas', ['id' => $area_id]);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(['message' => 'Failed to delete area']);
        }
    }

    /**
     * Update area managers
     */
    public static function update_area_managers() {
        // Simpler permission check - just verify user can manage
        if (!current_user_can('manage_options') && !Core::can_manage()) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        // Verify nonce the simple way
        $nonce = isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '';
        if (!wp_verify_nonce($nonce, 'oha_vh_board')) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;
        $managers = isset($_POST['managers']) ? array_map('intval', $_POST['managers']) : [];
        
        if (!$area_id) {
            wp_send_json_error(['message' => 'Invalid area ID']);
        }
        
        $result = Database::update_area_managers($area_id, $managers);
        
        if ($result !== false) {
            wp_send_json_success(['message' => 'Managers updated successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to update managers']);
        }
    }

    /**
     * Assign volunteer (redirect to Assignments class)
     */
    public static function assign_volunteer() {
        Assignments::ajax_assign_volunteer();
    }

    /**
     * Export volunteers to CSV
     */
    public static function export_volunteers() {
        check_admin_referer('oha_vh_export', 'nonce');
        
        if (!Core::can_manage()) {
            wp_die('Access denied');
        }

        // Handle preset exports
        $preset = isset($_POST['preset']) ? sanitize_text_field($_POST['preset']) : '';
        
        if ($preset) {
            self::handle_preset_export($preset);
            return;
        }

        // Custom export
        $statuses = isset($_POST['statuses']) ? array_map('sanitize_text_field', $_POST['statuses']) : ['all'];
        $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;
        $fields = isset($_POST['fields']) ? array_map('sanitize_text_field', $_POST['fields']) : ['basic'];

        // Build query — always scoped to the active convention year
        $active_year = Database::get_active_year();
        $args = ['limit' => 10000]; // year defaults to -1 (active year) in get_volunteers()

        if (!in_array('all', $statuses) && !empty($statuses)) {
            global $wpdb;
            $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
            $volunteers = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}oha_volunteers WHERE status IN ($placeholders) AND convention_year = %d ORDER BY signup_date DESC",
                ...[...$statuses, $active_year]
            ));
        } else {
            $volunteers = Database::get_volunteers($args);
        }

        // Filter by area if specified
        if ($area_id > 0) {
            $volunteers = array_filter($volunteers, function($v) use ($area_id) {
                $preferred = json_decode($v->preferred_areas, true) ?: [];
                return in_array($area_id, $preferred);
            });
        }

        // Generate CSV
        self::generate_csv($volunteers, $fields);
    }

    /**
     * Handle preset exports
     */
    private static function handle_preset_export($preset) {
        switch ($preset) {
            case 'all':
                $volunteers = Database::get_volunteers(['limit' => 10000]);
                $fields = ['basic', 'experience', 'areas', 'status'];
                break;
            case 'approved':
                $volunteers = Database::get_volunteers(['status' => 'approved', 'limit' => 10000]);
                $fields = ['basic', 'experience', 'areas'];
                break;
            case 'assigned':
                $volunteers = Database::get_volunteers(['status' => 'assigned', 'limit' => 10000]);
                $fields = ['basic', 'assignments', 'checkin'];
                break;
            case 'mailing':
                $volunteers = Database::get_volunteers(['limit' => 10000]);
                $fields = ['basic'];
                break;
            case 'dayone':
                $volunteers = Database::get_volunteers(['status' => 'assigned', 'limit' => 10000]);
                $fields = ['basic', 'assignments', 'checkin', 'emergency'];
                break;
            case 'pending':
                $volunteers = Database::get_volunteers(['status' => 'pending', 'limit' => 10000]);
                $fields = ['basic', 'experience', 'areas', 'status'];
                break;
            default:
                wp_die('Invalid preset');
        }
        
        self::generate_csv($volunteers, $fields);
    }

    /**
     * Generate CSV file
     */
    private static function generate_csv($volunteers, $fields) {
        $filename = 'oha-volunteers-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // Build headers based on selected fields
        $headers = [];
        if (in_array('basic', $fields)) {
            $headers = array_merge($headers, ['First Name', 'Last Name', 'Email', 'Phone', 'Birthdate', 'Age', 'T-Shirt Size']);
        }
        if (in_array('experience', $fields)) {
            $headers[] = 'Experience Level';
        }
        if (in_array('areas', $fields)) {
            $headers[] = 'Preferred Areas';
        }
        if (in_array('availability', $fields)) {
            $headers[] = 'Availability';
        }
        if (in_array('skills', $fields)) {
            $headers = array_merge($headers, ['Special Skills', 'How Did You Hear About Us']);
        }
        if (in_array('emergency', $fields)) {
            $headers = array_merge($headers, ['Emergency Contact', 'Emergency Contact Attending']);
        }
        if (in_array('status', $fields)) {
            $headers = array_merge($headers, ['Status', 'Signup Date']);
        }
        if (in_array('assignments', $fields)) {
            $headers[] = 'Assigned Areas';
        }
        if (in_array('checkin', $fields)) {
            $headers[] = 'Checked In';
        }
        if (in_array('hours', $fields)) {
            $headers[] = 'Hours Worked';
        }
        
        fputcsv($output, $headers);
        
        // Output data rows
        foreach ($volunteers as $v) {
            $row = [];
            
            if (in_array('basic', $fields)) {
                $row[] = $v->first_name;
                $row[] = $v->last_name;
                $row[] = $v->email;
                $row[] = $v->phone ?? '';
                
                // Birthdate and age
                if (!empty($v->birthdate)) {
                    $row[] = date('m/d/Y', strtotime($v->birthdate));
                    $age = floor((time() - strtotime($v->birthdate)) / 31556926);
                    $row[] = $age;
                } else {
                    $row[] = '';
                    $row[] = '';
                }
                
                // T-Shirt Size
                $row[] = $v->tshirt_size ?? '';
            }
            if (in_array('experience', $fields)) {
                $row[] = ucfirst($v->experience_level);
            }
            if (in_array('areas', $fields)) {
                $preferred = json_decode($v->preferred_areas, true) ?: [];
                $area_names = [];
                foreach ($preferred as $area_id) {
                    $area = Database::get_area($area_id);
                    if ($area) $area_names[] = $area->area_name;
                }
                $row[] = implode(', ', $area_names);
            }
            if (in_array('availability', $fields)) {
                // Use the formatting function for clean CSV output
                $row[] = \OHA\VH\SignupForm::format_availability_for_display($v->availability ?? '', false);
            }
            if (in_array('skills', $fields)) {
                $row[] = $v->special_skills ?? '';
                $row[] = $v->how_did_you_hear ?? '';
            }
            if (in_array('emergency', $fields)) {
                $row[] = $v->emergency_contact ?? '';
                $row[] = !empty($v->emergency_contact_attending) ? ucfirst($v->emergency_contact_attending) : '';
            }
            if (in_array('status', $fields)) {
                $row[] = ucfirst($v->status);
                $row[] = date('Y-m-d', strtotime($v->signup_date));
            }
            if (in_array('assignments', $fields)) {
                $assignments = Database::get_volunteer_assignments($v->id);
                $assigned_areas = [];
                foreach ($assignments as $assignment) {
                    $area = Database::get_area($assignment->area_id);
                    if ($area) $assigned_areas[] = $area->area_name;
                }
                $row[] = implode(', ', $assigned_areas);
            }
            if (in_array('checkin', $fields)) {
                $assignments = Database::get_volunteer_assignments($v->id);
                $checked_in = 0;
                foreach ($assignments as $assignment) {
                    if ($assignment->checked_in) $checked_in++;
                }
                $row[] = $checked_in . '/' . count($assignments);
            }
            if (in_array('hours', $fields)) {
                $assignments = Database::get_volunteer_assignments($v->id);
                $total_hours = 0;
                foreach ($assignments as $assignment) {
                    $total_hours += $assignment->hours_worked ?? 0;
                }
                $row[] = $total_hours;
            }
            
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }

    /**
     * Send email message to area volunteers
     */
    public static function send_area_message() {
        check_ajax_referer('oha_vh_board', 'nonce');
        
        if (!Core::can_manage()) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;
        $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
        $message = isset($_POST['message']) ? wp_kses_post($_POST['message']) : '';

        if (!$area_id || !$subject || !$message) {
            wp_send_json_error(['message' => 'Area, subject, and message are required']);
        }

        // Get area info
        $area = Database::get_area($area_id);
        if (!$area) {
            wp_send_json_error(['message' => 'Area not found']);
        }

        // Get all volunteers assigned to this area
        $assignments = Database::get_assignments_by_area($area_id);
        
        if (empty($assignments)) {
            wp_send_json_error(['message' => 'No volunteers assigned to this area']);
        }

        $sent_count = 0;
        $failed_count = 0;

        foreach ($assignments as $assignment) {
            $email = $assignment->email ?: $assignment->manual_email;
            $name = $assignment->first_name 
                ? $assignment->first_name . ' ' . $assignment->last_name 
                : $assignment->manual_name;

            if (!$email) {
                $failed_count++;
                continue;
            }

            // Personalize message
            $personalized_message = str_replace(
                ['{{name}}', '{{area}}'],
                [$name, $area->area_name],
                $message
            );

            // Send email
            $result = Emails::send_area_message($email, $name, $subject, $personalized_message, $area);
            
            if ($result) {
                $sent_count++;
            } else {
                $failed_count++;
            }
        }

        wp_send_json_success([
            'sent' => $sent_count,
            'failed' => $failed_count,
            'total' => count($assignments)
        ]);
    }

    /**
     * Resend assignment email to a volunteer (all their current assignments)
     */
    public static function resend_assignment_email() {
        check_ajax_referer('oha_vh_board', 'nonce');

        if (!Core::can_manage()) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $volunteer_id = isset($_POST['volunteer_id']) ? intval($_POST['volunteer_id']) : 0;

        if (!$volunteer_id) {
            wp_send_json_error(['message' => 'Invalid volunteer']);
        }

        $assignments = Database::get_volunteer_assignments($volunteer_id);

        if (empty($assignments)) {
            wp_send_json_error(['message' => 'No assignments found for this volunteer']);
        }

        $sent = 0;
        foreach ($assignments as $assignment) {
            $result = Emails::send_assignment_email($volunteer_id, $assignment->area_id, $assignment->shift_id ?: null);
            if ($result) $sent++;
        }

        if ($sent > 0) {
            wp_send_json_success(['message' => 'Assignment email sent (' . $sent . ' sent)']);
        } else {
            wp_send_json_error(['message' => 'Failed to send assignment email']);
        }
    }

    /**
     * Broadcast email to all volunteers with status "assigned"
     */
    public static function broadcast_email() {
        check_ajax_referer('oha_vh_board', 'nonce');

        if (!Core::can_manage()) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';

        if (!$subject || !$message) {
            wp_send_json_error(['message' => 'Subject and message are required']);
        }

        $volunteers = Database::get_volunteers(['status' => 'assigned', 'limit' => 10000]);

        if (empty($volunteers)) {
            wp_send_json_error(['message' => 'No assigned volunteers to email']);
        }

        global $wpdb;
        $from_name  = Settings::get('from_name', 'OHA Volunteer Coordinator');
        $from_email = Settings::get('from_email', get_option('admin_email'));
        $headers    = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        ];

        $sent   = 0;
        $failed = 0;

        foreach ($volunteers as $v) {
            $personalized = str_replace(
                ['{{name}}', '{{first_name}}'],
                [$v->first_name . ' ' . $v->last_name, $v->first_name],
                $message
            );

            $result = wp_mail($v->email, $subject, $personalized, $headers);

            if ($result) {
                $sent++;
                $wpdb->update(
                    $wpdb->prefix . 'oha_volunteers',
                    ['last_emailed_at' => current_time('mysql')],
                    ['id' => $v->id]
                );
            } else {
                $failed++;
            }
        }

        wp_send_json_success([
            'sent'   => $sent,
            'failed' => $failed,
            'total'  => count($volunteers)
        ]);
    }

    /**
     * Remove a single volunteer assignment
     */
    public static function remove_assignment() {
        check_ajax_referer('oha_vh_board', 'nonce');

        if (!Core::can_manage()) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;

        if (!$assignment_id) {
            wp_send_json_error(['message' => 'Invalid assignment']);
        }

        global $wpdb;
        $result = $wpdb->delete(
            $wpdb->prefix . 'oha_volunteer_assignments',
            ['id' => $assignment_id],
            ['%d']
        );

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(['message' => 'Failed to remove assignment']);
        }
    }

    /**
     * Update a volunteer assignment to a new area/shift (quick-reassign)
     */
    public static function update_assignment_area() {
        check_ajax_referer('oha_vh_board', 'nonce');

        if (!Core::can_manage()) {
            wp_send_json_error(['message' => 'Access denied']);
        }

        $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
        $area_id       = isset($_POST['area_id'])       ? intval($_POST['area_id'])       : 0;
        $shift_id      = isset($_POST['shift_id'])      ? intval($_POST['shift_id'])      : 0;

        if (!$assignment_id || !$area_id) {
            wp_send_json_error(['message' => 'Missing required fields']);
        }

        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'oha_volunteer_assignments',
            ['area_id' => $area_id, 'shift_id' => $shift_id ?: null],
            ['id' => $assignment_id]
        );

        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(['message' => 'Failed to update assignment']);
        }
    }

    /**
     * Get count of volunteers assigned to an area
     */
    public static function get_area_assignments_count() {
        check_ajax_referer('oha_vh_board', 'nonce');
        
        $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;
        
        if (!$area_id) {
            wp_send_json_error(['message' => 'Area ID required']);
        }
        
        $assignments = Database::get_assignments_by_area($area_id);
        
        wp_send_json_success(['count' => count($assignments)]);
    }
}
