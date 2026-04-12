<?php
/**
 * Volunteers Tab - View and manage all volunteers
 */

if (!defined('ABSPATH')) { exit; }

$status_filter = isset($_GET['vh_status']) ? sanitize_text_field($_GET['vh_status']) : 'all';
$search = isset($_GET['vh_search']) ? sanitize_text_field($_GET['vh_search']) : '';

$args = ['limit' => 500];
if ($status_filter !== 'all') {
    $args['status'] = $status_filter;
}
if ($search) {
    $args['search'] = $search;
}

$volunteers = \OHA\VH\Database::get_volunteers($args);
$counts = \OHA\VH\Database::get_volunteer_counts();
$all_areas = \OHA\VH\Database::get_areas();
?>

<div class="oha-vh-volunteers-tab">
    <div class="tab-header">
        <h3>Volunteer Directory</h3>
        <p>View all volunteer signups, approve applications, and assign to areas</p>
        <div style="display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap;">
            <button class="button button-primary" onclick="toggleManualAddPanel()">
                Manually Add Volunteer
            </button>
            <button class="button" onclick="openBroadcastModal()" style="background: #2271b1; border-color: #2271b1; color: white;">
                Broadcast Email to All Assigned
            </button>
        </div>
    </div>

    <!-- Manual Add Volunteer Panel -->
    <div id="manual-add-panel" class="create-panel" style="display: none; background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
        <h4 style="margin-top: 0;">Manually Add Volunteer</h4>
        <p style="margin-bottom: 15px; color: #666;">Add a volunteer who signed up offline or needs assistance with the form.</p>
        
        <form id="manual-add-volunteer-form">
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label><strong>First Name:</strong> <span style="color: red;">*</span></label>
                    <input type="text" name="first_name" required style="width: 100%; padding: 8px;">
                </div>
                <div class="form-group">
                    <label><strong>Last Name:</strong> <span style="color: red;">*</span></label>
                    <input type="text" name="last_name" required style="width: 100%; padding: 8px;">
                </div>
            </div>
            
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label><strong>Email:</strong> <span style="color: red;">*</span></label>
                    <input type="email" name="email" required style="width: 100%; padding: 8px;">
                </div>
                <div class="form-group">
                    <label><strong>Phone:</strong></label>
                    <input type="tel" name="phone" style="width: 100%; padding: 8px;">
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label><strong>Link to WordPress User (Optional):</strong></label>
                <select name="user_id" style="width: 100%; padding: 8px;">
                    <option value="">-- Not linked to any user --</option>
                    <?php
                    // Get all users except subscribers
                    $all_users = get_users(['orderby' => 'display_name', 'order' => 'ASC']);
                    foreach ($all_users as $user):
                        // Skip subscribers unless they're also admins
                        if (in_array('subscriber', $user->roles) && !in_array('administrator', $user->roles)) {
                            continue;
                        }
                    ?>
                        <option value="<?php echo esc_attr($user->ID); ?>">
                            <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>If this volunteer has a WordPress account, link it here</small>
            </div>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label><strong>Notes/Special Skills:</strong></label>
                <textarea name="special_skills" rows="3" style="width: 100%; padding: 8px;" 
                          placeholder="Any special skills, certifications, or notes..."></textarea>
            </div>
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label><strong>Status:</strong></label>
                <select name="status" style="width: 100%; padding: 8px;">
                    <option value="approved">Approved (ready to assign)</option>
                    <option value="pending">Pending (needs review)</option>
                </select>
            </div>
            
            <div class="form-actions" style="display: flex; gap: 10px; margin-top: 15px;">
                <button type="button" class="button" onclick="toggleManualAddPanel()">Cancel</button>
                <button type="submit" class="button button-primary">✅ Add Volunteer</button>
            </div>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="oha-vh-summary-cards">
        <?php foreach ($counts as $count): ?>
            <div class="summary-card status-<?php echo esc_attr($count->status); ?>">
                <div class="card-number"><?php echo esc_html($count->count); ?></div>
                <div class="card-label"><?php echo esc_html(ucfirst($count->status)); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <div class="oha-vh-filters">
        <label>
            <strong>Status:</strong>
            <select name="status" onchange="location.href=buildBoardHubUrl('volunteers', {vh_status: this.value, vh_search: '<?php echo esc_js($search); ?>'})">
                <option value="all" <?php selected($status_filter, 'all'); ?>>All Status</option>
                <option value="pending" <?php selected($status_filter, 'pending'); ?>>Pending</option>
                <option value="approved" <?php selected($status_filter, 'approved'); ?>>Approved</option>
                <option value="assigned" <?php selected($status_filter, 'assigned'); ?>>Assigned</option>
                <option value="rejected" <?php selected($status_filter, 'rejected'); ?>>Rejected</option>
                <option value="declined" <?php selected($status_filter, 'declined'); ?>>Declined</option>
                <option value="trashed" <?php selected($status_filter, 'trashed'); ?>>🗑️ Trash</option>
            </select>
        </label>
        <label>
            <strong>Search:</strong>
            <input type="search" name="search" value="<?php echo esc_attr($search); ?>" 
                   onkeyup="if(event.key==='Enter')location.href=buildBoardHubUrl('volunteers', {vh_status: '<?php echo esc_js($status_filter); ?>', vh_search: this.value})"
                   placeholder="Name or email...">
        </label>
    </div>

    <!-- Volunteers Table -->
    <?php if (empty($volunteers)): ?>
        <div class="empty-state">
            <div class="empty-icon">👥</div>
            <h4>No Volunteers Found</h4>
            <p>No volunteers match your current filters.</p>
        </div>
    <?php else: ?>
        <div class="scroll-hint">
            💡 Click or tap any row to view full volunteer details
        </div>
        <div class="oha-vh-table-wrap">
            <table class="oha-vh-table">
                <thead>
                    <tr>
                        <th style="background: #2271b1; color: #fff !important; padding: 12px; text-align: left; font-weight: 600; white-space: nowrap;">Status</th>
                        <th style="background: #2271b1; color: #fff !important; padding: 12px; text-align: left; font-weight: 600;">Name</th>
                        <th style="background: #2271b1; color: #fff !important; padding: 12px; text-align: left; font-weight: 600;">Contact</th>
                        <th style="background: #2271b1; color: #fff !important; padding: 12px; text-align: left; font-weight: 600;">Experience</th>
                        <th style="background: #2271b1; color: #fff !important; padding: 12px; text-align: left; font-weight: 600;">Preferred Areas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($volunteers as $v): 
                        $preferred = json_decode($v->preferred_areas, true) ?: [];
                        $area_names = [];
                        foreach ($preferred as $area_id) {
                            $area = \OHA\VH\Database::get_area($area_id);
                            if ($area) $area_names[] = $area->icon . ' ' . $area->area_name;
                        }
                        $assignments = \OHA\VH\Database::get_volunteer_assignments($v->id);
                    ?>
                    <tr data-volunteer-id="<?php echo esc_attr($v->id); ?>" onclick="toggleVolunteerDetails(<?php echo esc_js($v->id); ?>)" style="cursor: pointer;" class="volunteer-row">
                        <td data-label="Status" style="white-space: nowrap;">
                            <span class="status-badge status-<?php echo esc_attr($v->status); ?>">
                                <?php echo esc_html(ucfirst($v->status)); ?>
                            </span>
                        </td>
                        <td data-label="Name"><strong><?php echo esc_html($v->first_name . ' ' . $v->last_name); ?></strong></td>
                        <td data-label="Contact">
                            <div><?php echo esc_html($v->email); ?></div>
                            <?php if ($v->phone): ?>
                                <div style="font-size: 12px; color: #666;"><?php echo esc_html($v->phone); ?></div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Experience">
                            <?php
                            $experience_labels = [
                                'newbie' => '⭐ First Timer',
                                'experienced' => '⭐⭐ Experienced',
                                'veteran' => '⭐⭐⭐ Veteran'
                            ];
                            echo esc_html($experience_labels[$v->experience_level] ?? '⭐ First Timer');
                            ?>
                        </td>
                        <td data-label="Preferred Areas">
                            <?php if (empty($area_names)): ?>
                                <span style="color: #999;">None specified</span>
                            <?php else: ?>
                                <?php echo esc_html(implode(', ', array_slice($area_names, 0, 3))); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <!-- Expandable details row -->
                    <tr id="details-<?php echo esc_attr($v->id); ?>" class="volunteer-details-row" style="display: none;">
                        <td colspan="5" style="background: #f9f9f9; padding: 20px;">
                            <div style="max-width: 100%;">
                                <h3 style="margin-top: 0; color: #333;">📋 Full Volunteer Details</h3>
                                
                                <div style="overflow-x: auto;">
                                    <table style="width: 100%; min-width: 500px; background: white; border: 1px solid #ddd; border-collapse: collapse;">
                                        <tr>
                                            <th style="background: #2c3e50; color: #fff; padding: 10px; text-align: left; width: 200px;">Email:</th>
                                            <td style="padding: 10px; border: 1px solid #ddd; word-break: break-word;">
                                                <a href="mailto:<?php echo esc_attr($v->email); ?>"><?php echo esc_html($v->email); ?></a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th style="background: #2c3e50; color: #fff; padding: 10px; text-align: left;">Phone:</th>
                                            <td style="padding: 10px; border: 1px solid #ddd;">
                                                <?php echo esc_html($v->phone ?: 'Not provided'); ?>
                                            </td>
                                        </tr>
                                        <?php if (!empty($v->birthdate)): ?>
                                        <tr>
                                            <th style="background: #2c3e50; color: #fff; padding: 10px; text-align: left;">Birthdate:</th>
                                            <td style="padding: 10px; border: 1px solid #ddd;">
                                                <?php 
                                                $age = floor((time() - strtotime($v->birthdate)) / 31556926);
                                                echo esc_html(date('F j, Y', strtotime($v->birthdate))) . ' (Age: ' . $age . ')';
                                                if ($age < 18) {
                                                    echo ' <span style="color: #dc3545; font-weight: bold;">⚠️ MINOR - Parent must be present</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <th style="background: #2c3e50; color: #fff; padding: 10px; text-align: left;">Experience Level:</th>
                                            <td style="padding: 10px; border: 1px solid #ddd;">
                                                <?php echo esc_html($experience_labels[$v->experience_level] ?? 'Unknown'); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th style="background: #2c3e50; color: #fff; padding: 10px; text-align: left;">Preferred Areas:</th>
                                            <td style="padding: 10px; border: 1px solid #ddd; word-break: break-word;">
                                                <?php echo esc_html(implode(', ', $area_names) ?: 'None specified'); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th style="background: #2c3e50; color: #fff; padding: 10px; text-align: left;">Special Skills:</th>
                                            <td style="padding: 10px; border: 1px solid #ddd; word-break: break-word;">
                                                <?php echo esc_html($v->special_skills ?: 'None specified'); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th style="background: #2c3e50; color: #fff; padding: 10px; text-align: left;">👕 T-Shirt Size:</th>
                                            <td style="padding: 10px; border: 1px solid #ddd;">
                                                <?php echo esc_html(isset($v->tshirt_size) && $v->tshirt_size ? $v->tshirt_size : 'Not specified'); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th style="background: #2c3e50; color: #fff; padding: 10px; text-align: left;">How Did You Hear About Us:</th>
                                            <td style="padding: 10px; border: 1px solid #ddd; word-break: break-word;">
                                                <?php echo esc_html(isset($v->how_did_you_hear) && $v->how_did_you_hear ? $v->how_did_you_hear : 'Not specified'); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th style="background: #2c3e50; color: #fff; padding: 10px; text-align: left;">Availability:</th>
                                            <td style="padding: 10px; border: 1px solid #ddd; word-break: break-word;">
                                                <?php 
                                                if (!empty($v->availability)) {
                                                    echo \OHA\VH\SignupForm::format_availability_for_display($v->availability, true);
                                                } else {
                                                    echo '<em>Not specified</em>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php if (!empty($v->emergency_contact)): ?>
                                        <tr>
                                            <th style="background: #2c3e50; color: #fff; padding: 10px; text-align: left;">Emergency Contact:</th>
                                            <td style="padding: 10px; border: 1px solid #ddd; word-break: break-word;">
                                                <?php echo esc_html($v->emergency_contact); ?>
                                                <?php if (!empty($v->emergency_contact_attending)): ?>
                                                    <br><small style="color: #666;">
                                                        Will be at event: <strong><?php echo esc_html(ucfirst($v->emergency_contact_attending)); ?></strong>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <th style="background: #2c3e50; color: #fff; padding: 10px; text-align: left;">Signup Date:</th>
                                            <td style="padding: 10px; border: 1px solid #ddd;">
                                                <?php echo esc_html(date('F j, Y g:i A', strtotime($v->signup_date))); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th style="background: #2c3e50; color: #fff; padding: 10px; text-align: left;">Current Status:</th>
                                            <td style="padding: 10px; border: 1px solid #ddd;">
                                                <strong><?php echo esc_html(ucfirst($v->status)); ?></strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th style="background: #2c3e50; color: #fff; padding: 10px; text-align: left;">Last Emailed:</th>
                                            <td style="padding: 10px; border: 1px solid #ddd;">
                                                <?php echo !empty($v->last_emailed_at) ? esc_html(date('F j, Y g:i A', strtotime($v->last_emailed_at))) : '<em>Never</em>'; ?>
                                            </td>
                                        </tr>
                                        <?php if ($v->user_id): ?>
                                        <tr>
                                            <th style="background: #2c3e50; color: #fff; padding: 10px; text-align: left;">WordPress Account:</th>
                                            <td style="padding: 10px; border: 1px solid #ddd;">
                                                <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $v->user_id)); ?>" target="_blank">
                                                    User #<?php echo esc_html($v->user_id); ?> (View Profile)
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>

                                <?php if (!empty($assignments)): ?>
                                <div style="margin-top: 20px;">
                                    <h4>Current Assignments (<?php echo count($assignments); ?>)</h4>
                                    <ul style="margin: 0; padding: 0; list-style: none;">
                                        <?php foreach ($assignments as $assignment):
                                            $area = \OHA\VH\Database::get_area($assignment->area_id);
                                        ?>
                                            <li style="margin-bottom: 10px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 8px; background: #f9f9f9; border-radius: 4px;">
                                                <strong><?php echo esc_html($area ? $area->icon . ' ' . $area->area_name : 'Unknown Area'); ?></strong>
                                                <span style="color: #666; font-size: 12px;">
                                                    - Assigned <?php echo esc_html(date('M j, Y', strtotime($assignment->assignment_date))); ?>
                                                </span>
                                                <button class="button" style="padding: 3px 10px; font-size: 12px; margin-left: auto;"
                                                        onclick="event.stopPropagation(); openReassignModal(<?php echo esc_js($assignment->id); ?>, <?php echo esc_js($v->id); ?>)">
                                                    Reassign
                                                </button>
                                                <button class="button" style="padding: 3px 10px; font-size: 12px; background: #dc3545; border-color: #dc3545; color: white;"
                                                        onclick="event.stopPropagation(); removeAssignment(<?php echo esc_js($assignment->id); ?>, <?php echo esc_js($v->id); ?>)">
                                                    Remove
                                                </button>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>

                                <?php if ($v->notes): ?>
                                <div style="margin-top: 20px;">
                                    <h4>Internal Notes</h4>
                                    <div style="background: white; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                                        <?php echo nl2br(esc_html($v->notes)); ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Action Buttons -->
                                <div style="margin-top: 25px; padding: 20px; background: white; border: 2px solid #2271b1; border-radius: 8px;">
                                    <h4 style="margin-top: 0; color: #2271b1;">Quick Actions</h4>
                                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                        <?php if ($v->status === 'pending'): ?>
                                            <button class="button button-primary" onclick="event.stopPropagation(); quickApprove(<?php echo esc_js($v->id); ?>)" style="background: #28a745; border-color: #28a745;">
                                                ✅ Approve
                                            </button>
                                            <button class="button" onclick="event.stopPropagation(); quickReject(<?php echo esc_js($v->id); ?>)" style="background: #dc3545; border-color: #dc3545; color: white;">
                                                ❌ Reject
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($v->status === 'approved' || $v->status === 'assigned'): ?>
                                            <a href="#" onclick="event.stopPropagation(); location.href=buildBoardHubUrl('assign', {volunteer_id: <?php echo esc_js($v->id); ?>}); return false;" class="button button-primary">
                                                Assign to Area
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($assignments)): ?>
                                            <button class="button" onclick="event.stopPropagation(); resendAssignmentEmail(<?php echo esc_js($v->id); ?>)">
                                                Resend Assignment Email
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($is_admin): ?>
                                            <button class="button" onclick="event.stopPropagation(); deleteVolunteer(<?php echo esc_js($v->id); ?>)" style="background: #6c757d; border-color: #6c757d; color: white;">
                                                🗑️ Delete
                                            </button>
                                        <?php endif; ?>
                                        <button class="button" onclick="event.stopPropagation(); toggleVolunteerDetails(<?php echo esc_js($v->id); ?>)">
                                            ⬆ Close Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <p class="total-count">Showing <?php echo count($volunteers); ?> volunteer(s)</p>
</div>

<!-- Broadcast Email Modal -->
<div id="broadcast-modal" class="oha-modal" style="display:none; position:fixed !important; z-index:999999 !important; left:0 !important; top:0 !important; width:100% !important; height:100% !important; background-color:rgba(0,0,0,0.7) !important; align-items:center !important; justify-content:center !important;">
    <div class="oha-modal-content" style="background:white; padding:30px; border-radius:8px; max-width:600px; width:90%; max-height:85vh; overflow-y:auto; position:relative; box-shadow:0 10px 40px rgba(0,0,0,0.3);">
        <span class="oha-modal-close" onclick="closeBroadcastModal()" style="position:absolute; right:15px; top:15px; font-size:32px; font-weight:bold; cursor:pointer; color:#999; line-height:1;">&times;</span>
        <h3 style="margin-top:0; color:#333;">Broadcast Email to All Assigned Volunteers</h3>
        <p style="color:#666;">Sends a message to all volunteers with status "Assigned". Use <code>{{first_name}}</code> or <code>{{name}}</code> to personalize.</p>
        <div style="margin-bottom:15px;">
            <label style="display:block; margin-bottom:6px;"><strong>Subject:</strong></label>
            <input type="text" id="broadcast-subject" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box;">
        </div>
        <div style="margin-bottom:15px;">
            <label style="display:block; margin-bottom:6px;"><strong>Message:</strong></label>
            <textarea id="broadcast-message" rows="8" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box;"></textarea>
        </div>
        <div id="broadcast-result" style="display:none; margin-bottom:15px; padding:12px; border-radius:6px;"></div>
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button class="button" onclick="closeBroadcastModal()">Cancel</button>
            <button class="button button-primary" id="broadcast-send-btn" onclick="submitBroadcastEmail()">Send to All Assigned</button>
        </div>
    </div>
</div>

<!-- Quick Reassign Modal -->
<div id="reassign-modal" class="oha-modal" style="display:none; position:fixed !important; z-index:999999 !important; left:0 !important; top:0 !important; width:100% !important; height:100% !important; background-color:rgba(0,0,0,0.7) !important; align-items:center !important; justify-content:center !important;">
    <div class="oha-modal-content" style="background:white; padding:30px; border-radius:8px; max-width:500px; width:90%; max-height:85vh; overflow-y:auto; position:relative; box-shadow:0 10px 40px rgba(0,0,0,0.3);">
        <span class="oha-modal-close" onclick="closeReassignModal()" style="position:absolute; right:15px; top:15px; font-size:32px; font-weight:bold; cursor:pointer; color:#999; line-height:1;">&times;</span>
        <h3 style="margin-top:0; color:#333;">Quick Reassign</h3>
        <p style="color:#666;">Select a new area and optional shift for this assignment.</p>
        <input type="hidden" id="reassign-assignment-id">
        <input type="hidden" id="reassign-volunteer-id">
        <div style="margin-bottom:15px;">
            <label style="display:block; margin-bottom:6px;"><strong>New Area:</strong></label>
            <select id="reassign-area-select" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                <option value="">-- Select Area --</option>
                <?php foreach ($all_areas as $area):
                    $display = !empty($area->alt_name) ? $area->alt_name . ' (' . $area->area_name . ')' : $area->area_name;
                ?>
                    <option value="<?php echo esc_attr($area->id); ?>">
                        <?php echo esc_html($area->icon . ' ' . $display); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div id="reassign-shift-group" style="display:none; margin-bottom:15px;">
            <label style="display:block; margin-bottom:6px;"><strong>Shift (optional):</strong></label>
            <select id="reassign-shift-select" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                <option value="">-- All Day / No specific shift --</option>
            </select>
        </div>
        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
            <button class="button" onclick="closeReassignModal()">Cancel</button>
            <button class="button button-primary" onclick="submitReassign()">Update Assignment</button>
        </div>
    </div>
</div>

<script>
// Toggle volunteer details accordion
function toggleVolunteerDetails(id) {
    const detailsRow = document.getElementById('details-' + id);
    const icon = document.getElementById('expand-icon-' + id);
    
    if (detailsRow.style.display === 'none') {
        // Close any other open details
        document.querySelectorAll('.volunteer-details-row').forEach(row => {
            row.style.display = 'none';
        });
        document.querySelectorAll('[id^="expand-icon-"]').forEach(i => {
            i.textContent = '▶';
        });
        
        // Open this one
        detailsRow.style.display = 'table-row';
        if (icon) icon.textContent = '▼';

        // Scroll to the details smoothly
        setTimeout(() => {
            detailsRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 100);
    } else {
        // Close it
        detailsRow.style.display = 'none';
        if (icon) icon.textContent = '▶';
    }
}

// Manual volunteer add functions
function toggleManualAddPanel() {
    const panel = document.getElementById('manual-add-panel');
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        panel.style.display = 'none';
        document.getElementById('manual-add-volunteer-form').reset();
    }
}

// Handle manual volunteer add form submission
document.getElementById('manual-add-volunteer-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'oha_vh_manual_add_volunteer');
    formData.append('nonce', '<?php echo wp_create_nonce('oha_vh_board'); ?>');
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Adding...';
    
    fetch(ajaxurl, {
        method: 'POST',
        body: new URLSearchParams(formData)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ Volunteer added successfully!');
            toggleManualAddPanel();
            location.reload();
        } else {
            alert(data.data.message || 'Error adding volunteer');
            submitBtn.disabled = false;
            submitBtn.textContent = '✅ Add Volunteer';
        }
    })
    .catch(err => {
        alert('Error adding volunteer');
        submitBtn.disabled = false;
        submitBtn.textContent = '✅ Add Volunteer';
    });
});

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('volunteer-detail-modal');
    if (e.target === modal) {
        closeVolunteerModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('volunteer-detail-modal');
        if (modal && modal.style.display === 'flex') {
            closeVolunteerModal();
        }
    }
});

function quickApprove(id) {
    if (!confirm('Approve this volunteer? They will be notified by email.')) return;
    
    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'oha_vh_quick_approve',
            volunteer_id: id,
            nonce: '<?php echo wp_create_nonce('oha_vh_board'); ?>'
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ Volunteer approved and notified!');
            location.reload();
        } else {
            alert(data.data.message || 'Error approving volunteer');
        }
    });
}

// Quick reject volunteer
function quickReject(id) {
    if (!confirm('Reject this volunteer? This will change their status to "rejected".')) return;
    
    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'oha_vh_quick_reject',
            volunteer_id: id,
            nonce: '<?php echo wp_create_nonce('oha_vh_board'); ?>'
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('❌ Volunteer rejected');
            location.reload();
        } else {
            alert(data.data.message || 'Error rejecting volunteer');
        }
    });
}

// --- Broadcast Email ---
function openBroadcastModal() {
    const modal = document.getElementById('broadcast-modal');
    modal.style.display = 'flex';
    document.getElementById('broadcast-result').style.display = 'none';
}

function closeBroadcastModal() {
    document.getElementById('broadcast-modal').style.display = 'none';
}

function submitBroadcastEmail() {
    const subject = document.getElementById('broadcast-subject').value.trim();
    const message = document.getElementById('broadcast-message').value.trim();

    if (!subject || !message) {
        alert('Subject and message are required.');
        return;
    }

    if (!confirm('Send this email to all assigned volunteers?')) return;

    const btn = document.getElementById('broadcast-send-btn');
    btn.disabled = true;
    btn.textContent = 'Sending...';

    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'oha_vh_broadcast_email',
            subject: subject,
            message: message,
            nonce: '<?php echo wp_create_nonce('oha_vh_board'); ?>'
        })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = 'Send to All Assigned';
        const resultDiv = document.getElementById('broadcast-result');
        resultDiv.style.display = 'block';
        if (data.success) {
            resultDiv.style.background = '#d4edda';
            resultDiv.style.color = '#155724';
            resultDiv.textContent = 'Sent to ' + data.data.sent + ' of ' + data.data.total + ' volunteers.' + (data.data.failed > 0 ? ' (' + data.data.failed + ' failed)' : '');
        } else {
            resultDiv.style.background = '#f8d7da';
            resultDiv.style.color = '#721c24';
            resultDiv.textContent = data.data.message || 'Failed to send.';
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = 'Send to All Assigned';
        alert('Connection error. Please try again.');
    });
}

// --- Resend Assignment Email ---
function resendAssignmentEmail(volunteerId) {
    if (!confirm('Resend assignment email to this volunteer?')) return;

    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'oha_vh_resend_assignment_email',
            volunteer_id: volunteerId,
            nonce: '<?php echo wp_create_nonce('oha_vh_board'); ?>'
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(data.data.message || 'Email sent.');
            location.reload();
        } else {
            alert(data.data.message || 'Error sending email.');
        }
    });
}

// --- Remove Assignment ---
function removeAssignment(assignmentId, volunteerId) {
    if (!confirm('Remove this assignment? The volunteer will not be notified automatically.')) return;

    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'oha_vh_remove_assignment',
            assignment_id: assignmentId,
            nonce: '<?php echo wp_create_nonce('oha_vh_board'); ?>'
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.data.message || 'Error removing assignment.');
        }
    });
}

// --- Quick Reassign ---
function openReassignModal(assignmentId, volunteerId) {
    document.getElementById('reassign-assignment-id').value = assignmentId;
    document.getElementById('reassign-volunteer-id').value = volunteerId;
    document.getElementById('reassign-area-select').value = '';
    document.getElementById('reassign-shift-group').style.display = 'none';
    document.getElementById('reassign-modal').style.display = 'flex';
}

function closeReassignModal() {
    document.getElementById('reassign-modal').style.display = 'none';
}

function submitReassign() {
    const assignmentId = document.getElementById('reassign-assignment-id').value;
    const areaId       = document.getElementById('reassign-area-select').value;
    const shiftId      = document.getElementById('reassign-shift-select').value;

    if (!areaId) {
        alert('Please select an area.');
        return;
    }

    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'oha_vh_update_assignment_area',
            assignment_id: assignmentId,
            area_id: areaId,
            shift_id: shiftId || '',
            nonce: '<?php echo wp_create_nonce('oha_vh_board'); ?>'
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeReassignModal();
            location.reload();
        } else {
            alert(data.data.message || 'Error reassigning volunteer.');
        }
    });
}

document.getElementById('reassign-area-select')?.addEventListener('change', function() {
    const areaId = this.value;
    if (!areaId) {
        document.getElementById('reassign-shift-group').style.display = 'none';
        return;
    }
    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'oha_vh_get_area_shifts',
            area_id: areaId,
            nonce: '<?php echo wp_create_nonce('oha_vh_board'); ?>'
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.data.shifts.length > 0) {
            const select = document.getElementById('reassign-shift-select');
            select.innerHTML = '<option value="">-- All Day / No specific shift --</option>';
            data.data.shifts.forEach(shift => {
                const opt = document.createElement('option');
                opt.value = shift.id;
                opt.textContent = shift.label;
                select.appendChild(opt);
            });
            document.getElementById('reassign-shift-group').style.display = 'block';
        } else {
            document.getElementById('reassign-shift-group').style.display = 'none';
        }
    });
});

// Close broadcast/reassign modals on Escape or outside click
document.addEventListener('click', function(e) {
    const broadcast = document.getElementById('broadcast-modal');
    if (e.target === broadcast) closeBroadcastModal();
    const reassign = document.getElementById('reassign-modal');
    if (e.target === reassign) closeReassignModal();
});

// Delete volunteer (move to trash)
function deleteVolunteer(id) {
    if (!confirm('Move this volunteer to trash? You can restore them from the Trash filter.')) return;
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'oha_vh_delete_volunteer',
            volunteer_id: id,
            nonce: '<?php echo wp_create_nonce('oha_vh_board'); ?>'
        },
        success: function(response) {
            if (response.success) {
                alert('✅ Volunteer moved to trash');
                location.reload();
            } else {
                alert('Error: ' + (response.data.message || 'Failed to delete volunteer'));
            }
        },
        error: function() {
            alert('Error deleting volunteer');
        }
    });
}
</script>

<style>
/* Volunteers Tab Styles */
.oha-vh-volunteers-tab .tab-header {
    margin-bottom: 30px;
}

.oha-vh-volunteers-tab .tab-header h3 {
    color: #333 !important;
}

.oha-vh-volunteers-tab .tab-header p {
    color: #666 !important;
}

.oha-vh-summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.summary-card {
    background: white;
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}

.summary-card.status-pending { border-color: #ffc107; }
.summary-card.status-approved { border-color: #28a745; }
.summary-card.status-assigned { border-color: #2271b1; }
.summary-card.status-declined { border-color: #dc3545; }

.summary-card .card-number {
    font-size: 36px;
    font-weight: bold;
    color: #333 !important;
}

.summary-card .card-label {
    font-size: 14px;
    color: #666 !important;
    text-transform: uppercase;
    margin-top: 5px;
}

.oha-vh-filters {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.oha-vh-filters label {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #333 !important;
}

.oha-vh-filters strong {
    color: #333 !important;
}

.oha-vh-filters select,
.oha-vh-filters input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    color: #333 !important;
}

.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    color: white !important;
}

.status-badge.status-pending { background: #ffc107; }
.status-badge.status-approved { background: #28a745; }
.status-badge.status-assigned { background: #2271b1; }
.status-badge.status-rejected { background: #dc3545; }
.status-badge.status-declined { background: #dc3545; }

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #f9f9f9;
    border-radius: 8px;
}

.empty-state h4 {
    color: #333 !important;
}

.empty-state p {
    color: #666 !important;
}

.empty-icon {
    font-size: 72px;
    margin-bottom: 20px;
}

.total-count {
    text-align: center;
    color: #666 !important;
    margin-top: 20px;
    font-size: 14px;
}

/* Modal - Proper Lightbox Overlay */
.oha-modal {
    display: none; /* Hidden by default */
    position: fixed;
    z-index: 999999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: hidden; /* Changed from 'auto' to fix flexbox centering */
    background-color: rgba(0,0,0,0.7);
    backdrop-filter: blur(2px);
    -webkit-backdrop-filter: blur(2px);
}

.oha-modal.show {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.oha-modal-content {
    background-color: white;
    padding: 30px;
    border-radius: 8px;
    width: 90%;
    max-width: 700px;
    max-height: 85vh;
    overflow-y: auto;
    position: relative;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.oha-modal-close {
    position: absolute;
    right: 15px;
    top: 15px;
    font-size: 32px;
    font-weight: bold;
    cursor: pointer;
    color: #999;
    line-height: 1;
    transition: color 0.2s;
}

.oha-modal-close:hover {
    color: #333;
}

/* Clickable row styles */
.volunteer-row {
    transition: background-color 0.2s ease;
}

.volunteer-row:hover {
    background-color: #f0f7ff !important;
}

.volunteer-details-row td {
    padding: 20px !important;
}

@media (max-width: 768px) {
    .oha-vh-summary-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .oha-vh-filters {
        flex-direction: column;
    }
    
    .oha-vh-filters label {
        width: 100%;
    }
    
    .oha-vh-filters select,
    .oha-vh-filters input {
        width: 100%;
    }
    
    /* Make details table responsive */
    .volunteer-details-row table {
        display: block;
        overflow-x: auto;
    }
    
    .volunteer-details-row th,
    .volunteer-details-row td {
        display: block;
        width: 100% !important;
        box-sizing: border-box;
    }
    
    .volunteer-details-row th {
        border-bottom: none !important;
    }
    
    .volunteer-details-row td {
        border-top: none !important;
        margin-bottom: 15px;
    }
    
    /* Make action buttons stack on mobile */
    .volunteer-details-row .button {
        width: 100%;
        margin-bottom: 8px;
    }
}
</style>

