<?php
/**
 * OHA Volunteer Hub - Assignment Management (Phase 3)
 * Handles volunteer assignment to areas, shifts, and area managers
 */

namespace OHA\VH;

if (!defined('ABSPATH')) { exit; }

class Assignments {

    public static function init() {
        // AJAX handlers
        add_action('wp_ajax_oha_vh_assign_volunteer', [__CLASS__, 'ajax_assign_volunteer']);
        add_action('wp_ajax_oha_vh_assign_manual_volunteer', [__CLASS__, 'ajax_assign_manual']);
        // oha_vh_update_area_managers: handled by Board class (real implementation) - do not register stub here
        // oha_vh_create_shift: handled by Board class (real implementation) - do not register stub here
        add_action('wp_ajax_oha_vh_send_volunteer_message', [__CLASS__, 'ajax_send_message']);
        add_action('wp_ajax_oha_vh_check_in_volunteer', [__CLASS__, 'ajax_check_in']);
        add_action('wp_ajax_oha_vh_get_assign_form', [__CLASS__, 'ajax_get_assign_form']);
        
        // Add assignment management page
        add_action('admin_menu', [__CLASS__, 'add_admin_pages'], 20);
    }

    /**
     * Add admin pages for assignment management
     */
    public static function add_admin_pages() {
        add_submenu_page(
            'oha-volunteer-hub',
            'Assign Volunteers',
            'Assign Volunteers',
            'manage_options',
            'oha-volunteer-assign',
            [__CLASS__, 'render_assignment_page']
        );
        
        // Note: Shift management is done via the frontend Board Hub → Shifts tab
        // No backend admin page needed
    }

    /**
     * Render assignment page
     */
    public static function render_assignment_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $areas = Database::get_areas();
        $volunteers_approved = Database::get_volunteers(['status' => 'approved', 'limit' => 500]);
        $volunteers_assigned = Database::get_volunteers(['status' => 'assigned', 'limit' => 500]);
        $all_volunteers = array_merge($volunteers_approved, $volunteers_assigned);
        
        ?>
        <div class="wrap oha-vh-assign-page">
            <h1>🎯 Assign Volunteers to Areas</h1>
            <p>Assign volunteers from the pool to convention areas. You can assign registered users or manually add volunteers.</p>

            <div class="oha-assign-layout">
                <!-- Left: Volunteer Pool -->
                <div class="oha-assign-pool">
                    <h2>Volunteer Pool (<?php echo count($all_volunteers); ?>)</h2>
                    
                    <div class="search-box">
                        <input type="search" id="volunteer-search" placeholder="Search volunteers..." style="width: 100%; padding: 8px;">
                    </div>

                    <div class="volunteer-list">
                        <?php if (empty($all_volunteers)): ?>
                            <p style="text-align: center; padding: 40px; color: #999;">
                                No approved volunteers yet.<br>
                                <a href="<?php echo admin_url('admin.php?page=oha-volunteer-signups&status=pending'); ?>">Approve some volunteers</a> to get started.
                            </p>
                        <?php else: ?>
                            <?php foreach ($all_volunteers as $v): 
                                $preferred = json_decode($v->preferred_areas, true) ?: [];
                                $area_names = [];
                                foreach ($preferred as $area_id) {
                                    $area = Database::get_area($area_id);
                                    if ($area) $area_names[] = $area->area_name;
                                }
                                $assignments = Database::get_assignments_by_volunteer($v->id);
                            ?>
                            <div class="volunteer-card" data-volunteer-id="<?php echo esc_attr($v->id); ?>" 
                                 data-name="<?php echo esc_attr($v->first_name . ' ' . $v->last_name); ?>"
                                 data-email="<?php echo esc_attr($v->email); ?>">
                                <div class="volunteer-info">
                                    <strong><?php echo esc_html($v->first_name . ' ' . $v->last_name); ?></strong>
                                    <small><?php echo esc_html($v->email); ?></small>
                                    <?php if (!empty($area_names)): ?>
                                        <small style="color: #666;">Prefers: <?php echo esc_html(implode(', ', array_slice($area_names, 0, 2))); ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($assignments)): ?>
                                        <small style="color: #28a745; font-weight: 600;">
                                            ✅ Assigned to <?php echo count($assignments); ?> area(s)
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <button class="button button-primary assign-btn" onclick="showAssignModal(<?php echo esc_js($v->id); ?>, '<?php echo esc_js($v->first_name . ' ' . $v->last_name); ?>')">
                                    Assign →
                                </button>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #ddd;">
                        <h3>➕ Add Manual Volunteer</h3>
                        <p>For volunteers who haven't registered on the site:</p>
                        <button class="button button-secondary" onclick="showManualEntryModal()">
                            Add Manual Volunteer
                        </button>
                    </div>
                </div>

                <!-- Right: Areas -->
                <div class="oha-assign-areas">
                    <h2>Convention Areas</h2>
                    
                    <?php foreach ($areas as $area): 
                        $managers = json_decode($area->area_managers, true) ?: [];
                        $manager_names = [];
                        foreach ($managers as $manager_id) {
                            $manager = get_user_by('id', $manager_id);
                            if ($manager) $manager_names[] = $manager->display_name;
                        }
                        $assignments = Database::get_assignments_by_area($area->id);
                    ?>
                    <div class="area-card" style="border-left: 4px solid <?php echo esc_attr($area->color); ?>">
                        <h3>
                            <?php echo esc_html($area->icon . ' ' . $area->area_name); ?>
                            <span class="area-count"><?php echo count($assignments); ?> assigned</span>
                        </h3>
                        
                        <?php if (!empty($manager_names)): ?>
                            <p class="area-managers">
                                <strong>Managers:</strong> <?php echo esc_html(implode(', ', $manager_names)); ?>
                            </p>
                        <?php else: ?>
                            <p class="area-managers" style="color: #999;">
                                No managers assigned yet
                            </p>
                        <?php endif; ?>

                        <div class="area-actions">
                            <button class="button" onclick="manageAreaManagers(<?php echo esc_js($area->id); ?>, '<?php echo esc_js($area->area_name); ?>')">
                                👥 Manage Managers
                            </button>
                            <button class="button" onclick="viewAreaAssignments(<?php echo esc_js($area->id); ?>, '<?php echo esc_js($area->area_name); ?>')">
                                👁️ View Assignments (<?php echo count($assignments); ?>)
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Assignment Modal -->
            <div id="assign-modal" style="display:none;">
                <div class="modal-overlay" onclick="closeAssignModal()"></div>
                <div class="modal-content">
                    <h2>Assign Volunteer</h2>
                    <div id="assign-form-content"></div>
                </div>
            </div>

            <!-- Manual Entry Modal -->
            <div id="manual-modal" style="display:none;">
                <div class="modal-overlay" onclick="closeManualModal()"></div>
                <div class="modal-content">
                    <h2>Add Manual Volunteer</h2>
                    <form id="manual-volunteer-form">
                        <table class="form-table">
                            <tr>
                                <th><label>First Name *</label></th>
                                <td><input type="text" name="first_name" required class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label>Last Name *</label></th>
                                <td><input type="text" name="last_name" required class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label>Email *</label></th>
                                <td><input type="email" name="email" required class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label>Phone</label></th>
                                <td><input type="tel" name="phone" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label>Assign to Area *</label></th>
                                <td>
                                    <select name="area_id" required>
                                        <option value="">Select Area</option>
                                        <?php foreach ($areas as $area): ?>
                                            <option value="<?php echo esc_attr($area->id); ?>">
                                                <?php echo esc_html($area->area_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Shift</label></th>
                                <td>
                                    <select name="shift_id">
                                        <option value="">All Day / No specific shift</option>
                                    </select>
                                    <p class="description">Select area first to see shifts</p>
                                </td>
                            </tr>
                        </table>
                        <p>
                            <button type="submit" class="button button-primary">Add & Assign Volunteer</button>
                            <button type="button" class="button" onclick="closeManualModal()">Cancel</button>
                        </p>
                    </form>
                </div>
            </div>

            <style>
                .oha-assign-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px; }
                .oha-assign-pool, .oha-assign-areas { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .volunteer-list { max-height: 600px; overflow-y: auto; margin-top: 15px; }
                .volunteer-card { display: flex; align-items: center; justify-content: space-between; padding: 12px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 6px; transition: all 0.2s; }
                .volunteer-card:hover { border-color: #2271b1; background: #f9f9f9; }
                .volunteer-info { flex: 1; }
                .volunteer-info strong { display: block; color: #000; }
                .volunteer-info small { display: block; color: #666; margin-top: 2px; }
                .assign-btn { flex-shrink: 0; }
                .area-card { background: #fff; padding: 20px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 6px; }
                .area-card h3 { margin-top: 0; display: flex; justify-content: space-between; align-items: center; }
                .area-count { background: #2271b1; color: #fff; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
                .area-managers { color: #666; margin: 10px 0; }
                .area-actions { display: flex; gap: 10px; margin-top: 15px; }
                .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 99999; }
                .modal-content { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 100000; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; }
                @media (max-width: 1200px) {
                    .oha-assign-layout { grid-template-columns: 1fr; }
                }
            </style>

            <script>
            // Search functionality
            document.getElementById('volunteer-search').addEventListener('input', function(e) {
                const search = e.target.value.toLowerCase();
                document.querySelectorAll('.volunteer-card').forEach(card => {
                    const name = card.getAttribute('data-name').toLowerCase();
                    const email = card.getAttribute('data-email').toLowerCase();
                    card.style.display = (name.includes(search) || email.includes(search)) ? '' : 'none';
                });
            });

            function showAssignModal(volunteerId, volunteerName) {
                document.getElementById('assign-modal').style.display = 'block';
                document.getElementById('assign-form-content').innerHTML = '<p>Loading...</p>';
                
                const nonce = '<?php echo wp_create_nonce('oha_vh_admin'); ?>';
                fetch(ajaxurl + '?action=oha_vh_get_assign_form&volunteer_id=' + volunteerId + '&nonce=' + nonce)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('assign-form-content').innerHTML = data.data.html;
                        } else {
                            document.getElementById('assign-form-content').innerHTML = '<p>Error loading form: ' + (data.data?.message || 'Unknown error') + '</p>';
                        }
                    })
                    .catch(err => {
                        document.getElementById('assign-form-content').innerHTML = '<p>Error loading form. Check console.</p>';
                        console.error('Assign form error:', err);
                    });
            }

            function closeAssignModal() {
                document.getElementById('assign-modal').style.display = 'none';
            }

            function showManualEntryModal() {
                document.getElementById('manual-modal').style.display = 'block';
            }

            function closeManualModal() {
                document.getElementById('manual-modal').style.display = 'none';
            }

            function manageAreaManagers(areaId, areaName) {
                // TODO: Open managers modal
                alert('Area manager management coming in next build!');
            }

            function viewAreaAssignments(areaId, areaName) {
                // TODO: Open assignments modal
                window.location.href = '<?php echo admin_url('admin.php?page=oha-volunteer-signups'); ?>&area_filter=' + areaId;
            }

            // Manual volunteer form submission
            document.getElementById('manual-volunteer-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'oha_vh_assign_manual_volunteer');
                formData.append('nonce', '<?php echo wp_create_nonce('oha_vh_admin'); ?>');
                
                fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            alert('Volunteer added and assigned!');
                            location.reload();
                        } else {
                            alert(data.data.message || 'Error');
                        }
                    });
            });
            </script>
        </div>
        <?php
    }

    /**
     * AJAX: Assign volunteer
     */
    public static function ajax_assign_volunteer() {
        // Accept both board hub nonce (oha_vh_board) and WP admin nonce (oha_vh_admin)
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'oha_vh_board') && !wp_verify_nonce($nonce, 'oha_vh_admin')) {
            wp_send_json_error(['message' => 'Security check failed'], 403);
        }

        if (!Core::can_manage()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $volunteer_id = absint($_POST['volunteer_id'] ?? 0);
        $area_id = absint($_POST['area_id'] ?? 0);
        $shift_id = absint($_POST['shift_id'] ?? 0) ?: null;

        if (!$volunteer_id || !$area_id) {
            wp_send_json_error(['message' => 'Missing required fields']);
        }

        // Check volunteer status - auto-approve if pending
        $volunteer = Database::get_volunteer($volunteer_id);
        if ($volunteer && $volunteer->status === 'pending') {
            Database::update_volunteer($volunteer_id, ['status' => 'approved']);
        }

        $result = Database::create_assignment([
            'volunteer_id' => $volunteer_id,
            'area_id' => $area_id,
            'shift_id' => $shift_id
        ]);

        if ($result === false) {
            wp_send_json_error(['message' => 'Failed to create assignment']);
        }

        // Send assignment email
        Emails::send_assignment_email($volunteer_id, $area_id, $shift_id);

        wp_send_json_success(['message' => 'Volunteer assigned successfully!']);
    }

    /**
     * AJAX: Assign manual volunteer
     */
    public static function ajax_assign_manual() {
        check_ajax_referer('oha_vh_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $data = [
            'manual_name' => sanitize_text_field($_POST['first_name'] . ' ' . $_POST['last_name']),
            'manual_email' => sanitize_email($_POST['email']),
            'manual_phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'area_id' => absint($_POST['area_id']),
            'shift_id' => absint($_POST['shift_id']) ?: null
        ];

        if (empty($data['manual_name']) || empty($data['manual_email']) || !$data['area_id']) {
            wp_send_json_error(['message' => 'Missing required fields']);
        }

        $result = Database::create_assignment($data);

        if ($result === false) {
            wp_send_json_error(['message' => 'Failed to create assignment']);
        }

        wp_send_json_success(['message' => 'Manual volunteer added and assigned!']);
    }

    /**
     * AJAX: Update area managers
     */
    public static function ajax_update_managers() {
        check_ajax_referer('oha_vh_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        // TODO: Implement area manager updates

        wp_send_json_success(['message' => 'Managers updated']);
    }

    /**
     * AJAX: Create shift
     */
    public static function ajax_create_shift() {
        // TODO: Implement shift creation
    }

    /**
     * AJAX: Send message to volunteer
     */
    public static function ajax_send_message() {
        // TODO: Implement messaging
    }

    /**
     * AJAX: Check in volunteer
     */
    public static function ajax_check_in() {
        check_ajax_referer('oha_vh_portal', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Must be logged in']);
        }

        $assignment_id = absint($_POST['assignment_id'] ?? 0);
        if (!$assignment_id) {
            wp_send_json_error(['message' => 'Invalid assignment']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteer_assignments';
        
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
            wp_send_json_error(['message' => 'Failed to check in']);
        }

        wp_send_json_success(['message' => 'Checked in successfully!']);
    }

    /**
     * AJAX: Get assignment form for volunteer
     */
    public static function ajax_get_assign_form() {
        check_ajax_referer('oha_vh_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $volunteer_id = absint($_GET['volunteer_id'] ?? 0);
        if (!$volunteer_id) {
            wp_send_json_error(['message' => 'Invalid volunteer']);
        }

        $volunteer = Database::get_volunteer($volunteer_id);
        $areas = Database::get_areas();
        
        ob_start();
        ?>
        <form id="assign-volunteer-form" onsubmit="return submitAssignment(event)">
            <input type="hidden" name="volunteer_id" value="<?php echo esc_attr($volunteer_id); ?>">
            
            <p><strong>Volunteer:</strong> <?php echo esc_html($volunteer->first_name . ' ' . $volunteer->last_name); ?></p>
            
            <p>
                <label><strong>Assign to Area: *</strong></label>
                <select name="area_id" id="assign-area" required onchange="loadAreaShifts(this.value)">
                    <option value="">Select an area...</option>
                    <?php foreach ($areas as $area): ?>
                        <option value="<?php echo esc_attr($area->id); ?>">
                            <?php echo esc_html($area->icon . ' ' . $area->area_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            
            <p>
                <label><strong>Shift:</strong></label>
                <select name="shift_id" id="assign-shift">
                    <option value="">All Day / No specific shift</option>
                </select>
                <small>Select an area first to see available shifts</small>
            </p>
            
            <p>
                <button type="submit" class="button button-primary">Assign Volunteer</button>
                <button type="button" class="button" onclick="closeAssignModal()">Cancel</button>
            </p>
            
            <div id="assign-result"></div>
        </form>

        <script>
        function loadAreaShifts(areaId) {
            if (!areaId) {
                document.getElementById('assign-shift').innerHTML = '<option value="">All Day / No specific shift</option>';
                return;
            }
            
            // TODO: Load shifts via AJAX
            document.getElementById('assign-shift').innerHTML = '<option value="">All Day / No specific shift</option><option disabled>No shifts created yet</option>';
        }

        function submitAssignment(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'oha_vh_assign_volunteer');
            formData.append('nonce', '<?php echo wp_create_nonce('oha_vh_admin'); ?>');
            
            fetch(ajaxurl, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    const result = document.getElementById('assign-result');
                    if (data.success) {
                        result.innerHTML = '<div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 6px; margin-top: 15px;">Volunteer assigned successfully!</div>';
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        result.innerHTML = '<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 6px; margin-top: 15px;">' + (data.data.message || 'Error') + '</div>';
                    }
                });
            
            return false;
        }
        </script>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(['html' => $html]);
    }
}
