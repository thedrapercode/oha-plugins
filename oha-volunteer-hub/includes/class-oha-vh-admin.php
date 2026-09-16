<?php
/**
 * OHA Volunteer Hub - Admin Management
 * Clean table interface for managing volunteer signups (like membership tab style)
 */

namespace OHA\VH;

if (!defined('ABSPATH')) { exit; }

class Admin {

    public static function init() {
        // AJAX actions for admin
        add_action('wp_ajax_oha_vh_approve_volunteer', [__CLASS__, 'ajax_approve_volunteer']);
        add_action('wp_ajax_oha_vh_decline_volunteer', [__CLASS__, 'ajax_decline_volunteer']);
        add_action('wp_ajax_oha_vh_get_assignment_form', [__CLASS__, 'ajax_get_assignment_form']);
        add_action('wp_ajax_oha_vh_update_volunteer_notes', [__CLASS__, 'ajax_update_notes']);
        // NOTE: oha_vh_assign_volunteer handled by Assignments class (accepts both admin and board nonces)
        // NOTE: oha_vh_delete_volunteer handled by Ajax class (ajax_delete_volunteer method here does not exist)
        
        // Handle bulk actions
        add_action('admin_init', [__CLASS__, 'handle_bulk_actions']);
    }

    /**
     * Render signups table (called from Settings::render_signups_page)
     */
    public static function render_signups_table() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get filter parameters
        $status     = isset($_GET['status'])     ? sanitize_text_field($_GET['status'])     : '';
        $experience = isset($_GET['experience']) ? sanitize_text_field($_GET['experience']) : '';
        $search     = isset($_GET['s'])          ? sanitize_text_field($_GET['s'])          : '';
        $active_year = Database::get_active_year();
        $year_filter = isset($_GET['year']) ? (int) $_GET['year'] : $active_year;

        // Get volunteers
        $volunteers = self::get_filtered_volunteers($status, $experience, $search, $year_filter);
        $areas = Database::get_areas();
        
        ?>
        <div class="wrap oha-vh-admin-signups">
            <h1 class="wp-heading-inline">Volunteer Signups</h1>
            <a href="<?php echo admin_url('admin.php?page=oha-volunteer-hub'); ?>" class="page-title-action">← Back to Dashboard</a>
            <hr class="wp-header-end">

            <!-- Filters -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="get">
                        <input type="hidden" name="page" value="oha-volunteer-signups">

                        <select name="year">
                            <?php
                            // Show the active year plus a few years back for historical review
                            $earliest_year = 2026;
                            for ($y = $active_year; $y >= $earliest_year; $y--) {
                                $label = ($y === $active_year) ? $y . ' (Active)' : $y;
                                printf('<option value="%d"%s>%s</option>', $y, selected($year_filter, $y, false), esc_html($label));
                            }
                            echo '<option value="0"' . selected($year_filter, 0, false) . '>All Years</option>';
                            ?>
                        </select>

                        <select name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php selected($status, 'pending'); ?>>Pending Approval</option>
                            <option value="approved" <?php selected($status, 'approved'); ?>>Approved</option>
                            <option value="assigned" <?php selected($status, 'assigned'); ?>>Assigned</option>
                            <option value="declined" <?php selected($status, 'declined'); ?>>Declined</option>
                        </select>

                        <select name="experience">
                            <option value="">All Experience Levels</option>
                            <option value="newbie" <?php selected($experience, 'newbie'); ?>>First Timer</option>
                            <option value="experienced" <?php selected($experience, 'experienced'); ?>>Experienced</option>
                            <option value="veteran" <?php selected($experience, 'veteran'); ?>>Veteran</option>
                        </select>

                        <button type="submit" class="button">Filter</button>
                        <a href="<?php echo admin_url('admin.php?page=oha-volunteer-signups'); ?>" class="button">Reset</a>
                    </form>
                </div>
                
                <div class="alignleft actions">
                    <form method="get">
                        <input type="hidden" name="page" value="oha-volunteer-signups">
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search volunteers...">
                        <button type="submit" class="button">Search</button>
                    </form>
                </div>
                
                <div class="alignright actions">
                    <a href="<?php echo admin_url('admin.php?page=oha-volunteer-signups&action=export'); ?>" class="button">
                        📊 Export CSV
                    </a>
                </div>
            </div>

            <!-- Clean Table (Membership Tab Style) -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 30px;">
                            <input type="checkbox" id="select-all">
                        </th>
                        <th style="width: 200px;">Name</th>
                        <th style="width: 200px;">Contact</th>
                        <th>Preferred Areas</th>
                        <th style="width: 100px;">Experience</th>
                        <th style="width: 120px;">Signed Up</th>
                        <th style="width: 80px;">Status</th>
                        <th style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($volunteers)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">
                                <p><strong>No volunteers found</strong></p>
                                <p>Volunteers will appear here once they submit the signup form.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($volunteers as $volunteer): 
                            $preferred_areas = json_decode($volunteer->preferred_areas, true) ?: [];
                            $area_names = [];
                            foreach ($preferred_areas as $area_id) {
                                $area = Database::get_area($area_id);
                                if ($area) {
                                    $area_names[] = $area->icon . ' ' . $area->area_name;
                                }
                            }
                        ?>
                        <tr class="volunteer-row" data-id="<?php echo esc_attr($volunteer->id); ?>">
                            <td>
                                <input type="checkbox" name="volunteer_ids[]" value="<?php echo esc_attr($volunteer->id); ?>">
                            </td>
                            <td>
                                <strong><?php echo esc_html($volunteer->first_name . ' ' . $volunteer->last_name); ?></strong>
                                <?php if ($volunteer->user_id): ?>
                                    <br><small>🔗 Linked to User #<?php echo esc_html($volunteer->user_id); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                📧 <a href="mailto:<?php echo esc_attr($volunteer->email); ?>"><?php echo esc_html($volunteer->email); ?></a>
                                <?php if ($volunteer->phone): ?>
                                    <br>📱 <a href="tel:<?php echo esc_attr($volunteer->phone); ?>"><?php echo esc_html($volunteer->phone); ?></a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($area_names)): ?>
                                    <small><?php echo esc_html(implode(', ', $area_names)); ?></small>
                                <?php else: ?>
                                    <small style="color: #999;">No preferences</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $exp_labels = [
                                    'newbie' => '⭐ First Timer',
                                    'experienced' => '⭐⭐ Experienced',
                                    'veteran' => '⭐⭐⭐ Veteran'
                                ];
                                echo esc_html($exp_labels[$volunteer->experience_level] ?? 'Unknown');
                                ?>
                            </td>
                            <td>
                                <small><?php echo esc_html(date('M j, Y', strtotime($volunteer->signup_date))); ?></small>
                            </td>
                            <td>
                                <?php 
                                $status_badges = [
                                    'pending' => '<span class="oha-status pending">⏳ Pending</span>',
                                    'approved' => '<span class="oha-status approved">✅ Approved</span>',
                                    'assigned' => '<span class="oha-status assigned">📍 Assigned</span>',
                                    'declined' => '<span class="oha-status declined">❌ Declined</span>'
                                ];
                                echo $status_badges[$volunteer->status] ?? '<span class="oha-status">Unknown</span>';
                                ?>
                            </td>
                            <td class="row-actions-wrap">
                                <button class="button button-small" onclick="viewVolunteerDetails(<?php echo esc_js($volunteer->id); ?>)">
                                    👁️ View
                                </button>
                                
                                <?php if ($volunteer->status === 'pending'): ?>
                                    <button class="button button-primary button-small" onclick="approveVolunteer(<?php echo esc_js($volunteer->id); ?>)">
                                        ✅ Approve
                                    </button>
                                    <button class="button button-small" onclick="declineVolunteer(<?php echo esc_js($volunteer->id); ?>)">
                                        ❌ Decline
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($volunteer->status === 'approved'): ?>
                                    <button class="button button-primary button-small" onclick="assignVolunteer(<?php echo esc_js($volunteer->id); ?>)">
                                        📍 Assign
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Volunteer Details Modal -->
            <div id="volunteer-modal" style="display:none;">
                <div class="oha-modal-overlay" onclick="closeVolunteerModal()"></div>
                <div class="oha-modal-content" id="volunteer-details">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>

            <!-- Assignment Modal -->
            <div id="assignment-modal" style="display:none;">
                <div class="oha-modal-overlay" onclick="closeAssignmentModal()"></div>
                <div class="oha-modal-content">
                    <h2>Assign Volunteer</h2>
                    <div id="assignment-form-container">
                        <!-- Form loaded via AJAX -->
                    </div>
                </div>
            </div>

            <style>
                .oha-status { 
                    display: inline-block; 
                    padding: 4px 10px; 
                    border-radius: 12px; 
                    font-size: 11px; 
                    font-weight: bold; 
                    text-transform: uppercase; 
                }
                .oha-status.pending { background: #fff3cd; color: #856404; }
                .oha-status.approved { background: #d4edda; color: #155724; }
                .oha-status.assigned { background: #d1ecf1; color: #0c5460; }
                .oha-status.declined { background: #f8d7da; color: #721c24; }
                .oha-modal-overlay { 
                    position: fixed; 
                    top: 0; 
                    left: 0; 
                    right: 0; 
                    bottom: 0; 
                    background: rgba(0,0,0,0.7); 
                    z-index: 99999; 
                }
                .oha-modal-content { 
                    position: fixed; 
                    top: 50%; 
                    left: 50%; 
                    transform: translate(-50%, -50%); 
                    background: white; 
                    padding: 30px; 
                    border-radius: 8px; 
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3); 
                    z-index: 100000; 
                    max-width: 800px; 
                    width: 90%; 
                    max-height: 90vh; 
                    overflow-y: auto; 
                }
                .row-actions-wrap { white-space: nowrap; }
                .volunteer-detail-table { width: 100%; margin: 20px 0; }
                .volunteer-detail-table th { text-align: left; padding: 10px; background: #f5f5f5; width: 30%; }
                .volunteer-detail-table td { padding: 10px; }
            </style>

            <script>
            // Select all checkbox
            document.getElementById('select-all').addEventListener('change', function() {
                document.querySelectorAll('input[name="volunteer_ids[]"]').forEach(cb => {
                    cb.checked = this.checked;
                });
            });

            // View volunteer details
            function viewVolunteerDetails(volunteerId) {
                document.getElementById('volunteer-modal').style.display = 'block';
                document.getElementById('volunteer-details').innerHTML = '<p>Loading...</p>';
                
                fetch(ajaxurl + '?action=oha_vh_get_volunteer_details&volunteer_id=' + volunteerId)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('volunteer-details').innerHTML = data.data.html;
                        }
                    });
            }

            function closeVolunteerModal() {
                document.getElementById('volunteer-modal').style.display = 'none';
            }

            // Approve volunteer
            function approveVolunteer(volunteerId) {
                if (!confirm('Approve this volunteer?')) return;
                
                const formData = new FormData();
                formData.append('action', 'oha_vh_approve_volunteer');
                formData.append('volunteer_id', volunteerId);
                formData.append('nonce', '<?php echo wp_create_nonce('oha_vh_admin'); ?>');
                
                fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.data.message || 'Error');
                        }
                    });
            }

            // Decline volunteer
            function declineVolunteer(volunteerId) {
                if (!confirm('Decline this volunteer? This action can be reversed.')) return;
                
                const formData = new FormData();
                formData.append('action', 'oha_vh_decline_volunteer');
                formData.append('volunteer_id', volunteerId);
                formData.append('nonce', '<?php echo wp_create_nonce('oha_vh_admin'); ?>');
                
                fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.data.message || 'Error');
                        }
                    });
            }

            // Assign volunteer
            function assignVolunteer(volunteerId) {
                document.getElementById('assignment-modal').style.display = 'block';
                document.getElementById('assignment-form-container').innerHTML = '<p>Loading...</p>';
                
                fetch(ajaxurl + '?action=oha_vh_get_assignment_form&volunteer_id=' + volunteerId)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('assignment-form-container').innerHTML = data.data.html;
                        }
                    });
            }

            function closeAssignmentModal() {
                document.getElementById('assignment-modal').style.display = 'none';
            }
            </script>
        </div>
        <?php
    }

    /**
     * Get filtered volunteers
     */
    private static function get_filtered_volunteers($status = '', $experience = '', $search = '', $year = -1) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteers';

        $where = ['1=1'];

        // Year filter: -1 = active year, 0 = all years, positive int = specific year
        $year_int = (int) $year;
        if ($year_int === -1) {
            $year_int = Database::get_active_year();
        }
        if ($year_int > 0) {
            $where[] = $wpdb->prepare("convention_year = %d", $year_int);
        }

        if ($status) {
            $where[] = $wpdb->prepare("status = %s", $status);
        }

        if ($experience) {
            $where[] = $wpdb->prepare("experience_level = %s", $experience);
        }

        if ($search) {
            $search = '%' . $wpdb->esc_like($search) . '%';
            $where[] = $wpdb->prepare(
                "(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)",
                $search, $search, $search, $search
            );
        }

        $where_sql = implode(' AND ', $where);

        return $wpdb->get_results(
            "SELECT * FROM $table WHERE $where_sql ORDER BY signup_date DESC"
        );
    }

    /**
     * AJAX: Get volunteer details
     */
    public static function ajax_get_volunteer_details() {
        if (!current_user_can('manage_options') && !Core::can_manage()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        // Check both POST and GET for volunteer_id
        $volunteer_id = absint($_POST['volunteer_id'] ?? $_GET['volunteer_id'] ?? 0);
        
        if (!$volunteer_id) {
            wp_send_json_error(['message' => 'No volunteer ID provided']);
        }
        
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

        $assignments = Database::get_volunteer_assignments($volunteer_id);

        ob_start();
        ?>
        <div class="volunteer-details">
            <h2><?php echo esc_html($volunteer->first_name . ' ' . $volunteer->last_name); ?></h2>
            
            <table class="volunteer-detail-table">
                <tr>
                    <th>Email:</th>
                    <td><a href="mailto:<?php echo esc_attr($volunteer->email); ?>"><?php echo esc_html($volunteer->email); ?></a></td>
                </tr>
                <tr>
                    <th>Phone:</th>
                    <td><?php echo esc_html($volunteer->phone ?: 'Not provided'); ?></td>
                </tr>
                <tr>
                    <th>Experience Level:</th>
                    <td><?php echo esc_html(ucfirst($volunteer->experience_level)); ?></td>
                </tr>
                <tr>
                    <th>Preferred Areas:</th>
                    <td><?php echo esc_html(implode(', ', $area_names) ?: 'None specified'); ?></td>
                </tr>
                <tr>
                    <th>Special Skills:</th>
                    <td><?php echo esc_html($volunteer->special_skills ?: 'None specified'); ?></td>
                </tr>
                <tr>
                    <th>Availability:</th>
                    <td><?php echo esc_html($volunteer->availability ?: 'Not specified'); ?></td>
                </tr>
                <tr>
                    <th>Emergency Contact:</th>
                    <td><?php echo esc_html($volunteer->emergency_contact ?: 'Not provided'); ?></td>
                </tr>
                <tr>
                    <th>Signup Date:</th>
                    <td><?php echo esc_html(date('F j, Y g:i A', strtotime($volunteer->signup_date))); ?></td>
                </tr>
                <tr>
                    <th>Current Status:</th>
                    <td><strong><?php echo esc_html(ucfirst($volunteer->status)); ?></strong></td>
                </tr>
                <?php if ($volunteer->user_id): ?>
                <tr>
                    <th>WordPress Account:</th>
                    <td>
                        <a href="<?php echo admin_url('user-edit.php?user_id=' . $volunteer->user_id); ?>" target="_blank">
                            User #<?php echo esc_html($volunteer->user_id); ?> (View Profile)
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
            </table>

            <?php if (!empty($assignments)): ?>
            <h3>Current Assignments</h3>
            <table class="volunteer-detail-table">
                <thead>
                    <tr>
                        <th>Area</th>
                        <th>Shift</th>
                        <th>Assigned</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignments as $assignment): ?>
                    <tr>
                        <td><?php echo esc_html($assignment->icon . ' ' . $assignment->area_name); ?></td>
                        <td>
                            <?php if ($assignment->shift_date): ?>
                                <?php echo esc_html(date('M j', strtotime($assignment->shift_date))); ?> 
                                <?php echo esc_html(date('g:i A', strtotime($assignment->shift_start)) . ' - ' . date('g:i A', strtotime($assignment->shift_end))); ?>
                            <?php else: ?>
                                All day
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(date('M j, Y', strtotime($assignment->assignment_date))); ?></td>
                        <td>
                            <?php if ($assignment->checked_in): ?>
                                ✅ Checked In
                            <?php else: ?>
                                ⏳ Pending
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <h3>Internal Notes</h3>
            <textarea id="volunteer-notes-<?php echo esc_attr($volunteer_id); ?>" rows="4" style="width: 100%;"><?php echo esc_textarea($volunteer->notes); ?></textarea>
            <p>
                <button class="button button-primary" onclick="saveVolunteerNotes(<?php echo esc_js($volunteer_id); ?>)">
                    Save Notes
                </button>
                <button class="button" onclick="closeVolunteerModal()">Close</button>
            </p>
        </div>

        <script>
        function saveVolunteerNotes(volunteerId) {
            const notes = document.getElementById('volunteer-notes-' + volunteerId).value;
            const formData = new FormData();
            formData.append('action', 'oha_vh_update_volunteer_notes');
            formData.append('volunteer_id', volunteerId);
            formData.append('notes', notes);
            formData.append('nonce', '<?php echo wp_create_nonce('oha_vh_admin'); ?>');
            
            fetch(ajaxurl, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('Notes saved!');
                    }
                });
        }
        </script>
        <?php
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /**
     * AJAX: Approve volunteer
     */
    public static function ajax_approve_volunteer() {
        check_ajax_referer('oha_vh_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $volunteer_id = absint($_POST['volunteer_id'] ?? 0);
        
        Database::update_volunteer($volunteer_id, ['status' => 'approved']);
        
        // Send approval email (optional)
        // Emails::send_approval_email($volunteer_id);

        wp_send_json_success(['message' => 'Volunteer approved!']);
    }

    /**
     * AJAX: Decline volunteer
     */
    public static function ajax_decline_volunteer() {
        check_ajax_referer('oha_vh_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $volunteer_id = absint($_POST['volunteer_id'] ?? 0);
        
        Database::update_volunteer($volunteer_id, ['status' => 'declined']);

        wp_send_json_success(['message' => 'Volunteer declined']);
    }

    /**
     * AJAX: Update notes
     */
    public static function ajax_update_notes() {
        check_ajax_referer('oha_vh_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $volunteer_id = absint($_POST['volunteer_id'] ?? 0);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        
        Database::update_volunteer($volunteer_id, ['notes' => $notes]);

        wp_send_json_success(['message' => 'Notes saved!']);
    }

    /**
     * Get assignment form HTML
     */
    public static function ajax_get_assignment_form() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 400);
        }

        $volunteer_id = absint($_GET['volunteer_id'] ?? 0);
        if (!$volunteer_id) {
            wp_send_json_error(['message' => 'Invalid volunteer'], 400);
        }

        $volunteer = Database::get_volunteer($volunteer_id);
        if (!$volunteer) {
            wp_send_json_error(['message' => 'Volunteer not found'], 400);
        }

        $areas = Database::get_areas();
        
        ob_start();
        ?>
        <form id="admin-assignment-form" onsubmit="return submitAdminAssignment(event)">
            <input type="hidden" name="volunteer_id" value="<?php echo esc_attr($volunteer_id); ?>">
            
            <p><strong>Volunteer:</strong> <?php echo esc_html($volunteer->first_name . ' ' . $volunteer->last_name); ?></p>
            <p><strong>Email:</strong> <?php echo esc_html($volunteer->email); ?></p>
            
            <p>
                <label><strong>Assign to Area: *</strong></label>
                <select name="area_id" required style="width: 100%; padding: 8px;">
                    <option value="">Select an area...</option>
                    <?php foreach ($areas as $area): ?>
                        <option value="<?php echo esc_attr($area->id); ?>">
                            <?php echo esc_html($area->area_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            
            <p>
                <button type="submit" class="button button-primary">✅ Assign Volunteer</button>
                <button type="button" class="button" onclick="closeAssignmentModal()">Cancel</button>
            </p>
        </form>

        <script>
        function submitAdminAssignment(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            formData.append('action', 'oha_vh_assign_volunteer');
            formData.append('nonce', '<?php echo wp_create_nonce('oha_vh_admin'); ?>');
            
            fetch(ajaxurl, {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Volunteer assigned successfully!');
                    closeAssignmentModal();
                    location.reload();
                } else {
                    alert('Error: ' + (data.data?.message || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error('Assignment error:', err);
                alert('Error assigning volunteer. Check console.');
            });
            
            return false;
        }
        </script>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(['html' => $html]);
    }

    /**
     * Assign volunteer to area
     */
    public static function ajax_assign_volunteer() {
        check_ajax_referer('oha_vh_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $volunteer_id = absint($_POST['volunteer_id'] ?? 0);
        $area_id = absint($_POST['area_id'] ?? 0);
        
        if (!$volunteer_id || !$area_id) {
            wp_send_json_error(['message' => 'Missing required fields']);
        }

        // Create assignment
        $assignment_data = [
            'volunteer_id' => $volunteer_id,
            'area_id' => $area_id,
            'assignment_date' => current_time('mysql')
        ];
        
        $result = Database::create_assignment($assignment_data);
        
        if ($result) {
            // Update volunteer status to 'assigned'
            Database::update_volunteer($volunteer_id, ['status' => 'assigned']);
            wp_send_json_success(['message' => 'Volunteer assigned successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to create assignment']);
        }
    }

    /**
     * Handle bulk actions
     */
    public static function handle_bulk_actions() {
        // TODO: Implement bulk approve, decline, export, etc.
    }
}
