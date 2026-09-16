<?php
/**
 * Shifts Tab - Complete shift management system
 * Create, edit, and manage volunteer shifts for areas
 */

if (!defined('ABSPATH')) { exit; }

$selected_area = isset($_GET['area_id']) ? intval($_GET['area_id']) : 0;
$convention_date = \OHA\VH\Settings::get('convention_date', '');
?>

<div class="oha-vh-shifts-tab">
    <div class="tab-header">
        <h3>Shift Management</h3>
        <p>Create and manage time slots for convention areas</p>
    </div>

    <!-- Area Selector -->
    <div class="area-selector">
        <label><strong>Select Area:</strong></label>
        <select id="shift-area-select" onchange="location.href=buildBoardHubUrl('shifts', {area_id: this.value})" 
                style="padding: 10px; min-width: 300px;">
            <option value="0">-- Select an Area --</option>
            <?php foreach ($areas_to_show as $area): ?>
                <option value="<?php echo esc_attr($area->id); ?>" <?php selected($selected_area, $area->id); ?>>
                    <?php echo esc_html($area->icon . ' ' . $area->area_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <?php if ($selected_area > 0): ?>
            <button class="button button-primary" onclick="toggleCreateShiftPanel()">
                ➕ Create New Shift
            </button>
        <?php endif; ?>
    </div>

    <!-- Inline Shift Creation Panel (Hidden by default) -->
    <?php if ($selected_area > 0): ?>
    <div id="create-shift-panel" class="create-panel" style="display: none; background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 15px 0;">
        <h4 style="margin-top: 0;" id="shift-panel-title">Create New Shift</h4>
        <form id="shift-form">
            <input type="hidden" name="shift_id" id="shift-id" value="">
            <input type="hidden" name="area_id" value="<?php echo esc_attr($selected_area); ?>">
            
            <div class="form-group">
                <label><strong>Shift Type:</strong></label>
                <div class="radio-group">
                    <label>
                        <input type="radio" name="shift_type" value="all_day" checked onchange="toggleTimeFields()">
                        📅 All Day Shift
                    </label>
                    <label>
                        <input type="radio" name="shift_type" value="time_slot" onchange="toggleTimeFields()">
                        🕐 Specific Time Slot
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label><strong>Date:</strong></label>
                <input type="date" name="shift_date" id="shift-date" value="<?php echo esc_attr($convention_date); ?>" 
                       required style="width: 100%; padding: 10px;">
            </div>
            
            <div id="time-fields" style="display:none;">
                <div class="form-row">
                    <div class="form-group">
                        <label><strong>Start Time:</strong></label>
                        <input type="time" name="shift_start" id="shift-start" style="width: 100%; padding: 10px;">
                    </div>
                    <div class="form-group">
                        <label><strong>End Time:</strong></label>
                        <input type="time" name="shift_end" id="shift-end" style="width: 100%; padding: 10px;">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label><strong>Maximum Volunteers:</strong></label>
                <input type="number" name="slots_available" id="shift-slots" value="10" min="1" max="100" 
                       required style="width: 100%; padding: 10px;">
                <small>How many volunteers can work this shift?</small>
            </div>
            
            <div class="form-actions">
                <button type="button" class="button" onclick="toggleCreateShiftPanel()">Cancel</button>
                <button type="submit" class="button button-primary">
                    <span id="shift-submit-text">✅ Create Shift</span>
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($selected_area > 0): 
        $selected_area_obj = \OHA\VH\Database::get_area($selected_area);
        $shifts = \OHA\VH\Database::get_shifts_by_area($selected_area);
    ?>
        <!-- Selected Area Info -->
        <div class="selected-area-info" style="border-left-color: <?php echo esc_attr($selected_area_obj->color); ?>">
            <div class="area-icon-large"><?php echo esc_html($selected_area_obj->icon); ?></div>
            <div class="area-info-text">
                <h4><?php echo esc_html($selected_area_obj->area_name); ?></h4>
                <p><?php echo esc_html($selected_area_obj->description); ?></p>
                <div class="area-capacity">
                    Capacity: <?php echo esc_html($selected_area_obj->min_volunteers ?? 0); ?>-<?php echo esc_html($selected_area_obj->max_volunteers ?? '∞'); ?> volunteers
                </div>
            </div>
        </div>

        <!-- Shifts List -->
        <?php if (empty($shifts)): ?>
            <div class="empty-state">
                <div class="empty-icon">📅</div>
                <h4>No Shifts Created Yet</h4>
                <p>Create your first shift to start scheduling volunteers.</p>
                <button class="button button-primary button-large" onclick="toggleCreateShiftPanel()">
                    ➕ Create First Shift
                </button>
            </div>
        <?php else:
            global $wpdb;
        ?>
            <h4>Existing Shifts (<?php echo count($shifts); ?>)</h4>
            <div class="shifts-grid">
                <?php foreach ($shifts as $shift):
                    $assignments = $wpdb->get_results($wpdb->prepare(
                        "SELECT a.*, v.first_name, v.last_name, v.email
                         FROM {$wpdb->prefix}oha_volunteer_assignments a
                         LEFT JOIN {$wpdb->prefix}oha_volunteers v ON a.volunteer_id = v.id
                         WHERE a.shift_id = %d",
                        $shift->id
                    ));
                    $filled = count($assignments);
                ?>
                <div class="shift-card">
                    <div class="shift-header">
                        <div class="shift-type-badge shift-type-<?php echo esc_attr($shift->shift_type); ?>">
                            <?php echo $shift->shift_type === 'all_day' ? '📅 All Day' : '🕐 Time Slot'; ?>
                        </div>
                        <div class="shift-capacity">
                            <?php echo $filled; ?> / <?php echo esc_html($shift->slots_available); ?>
                        </div>
                    </div>
                    
                    <div class="shift-info">
                        <div class="shift-date">
                            📅 <?php echo date('l, F j, Y', strtotime($shift->shift_date)); ?>
                        </div>
                        
                        <?php if ($shift->shift_type === 'time_slot'): ?>
                            <div class="shift-time">
                                🕐 <?php echo date('g:i A', strtotime($shift->shift_start)); ?> - 
                                <?php echo date('g:i A', strtotime($shift->shift_end)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="shift-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo ($shift->slots_available > 0) ? ($filled / $shift->slots_available * 100) : 0; ?>%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="shift-actions">
                        <button class="button button-small" onclick="viewShiftAssignments(<?php echo esc_js($shift->id); ?>)">
                            👁️ View (<?php echo $filled; ?>)
                        </button>
                        <button class="button button-small" onclick="editShift(<?php echo esc_js($shift->id); ?>)">
                            ✏️ Edit
                        </button>
                        <button class="button button-small button-delete" onclick="deleteShift(<?php echo esc_js($shift->id); ?>)">
                            🗑️ Delete
                        </button>
                    </div>

                    <?php if (!empty($assignments)): ?>
                    <div class="shift-assignments-list" id="shift-assignments-<?php echo esc_attr($shift->id); ?>" style="display:none; margin-top: 15px; border-top: 1px solid #eee; padding-top: 15px;">
                        <strong>Assigned Volunteers (<?php echo $filled; ?>):</strong>
                        <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                            <?php foreach ($assignments as $asgn):
                                $name = ($asgn->first_name) ? ($asgn->first_name . ' ' . $asgn->last_name) : ($asgn->manual_name ?: 'Unknown');
                                $email = $asgn->email ?: ($asgn->manual_email ?: '');
                            ?>
                            <li>
                                <?php echo esc_html($name); ?>
                                <?php if ($email): ?>
                                    &ndash; <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                                <?php endif; ?>
                                <?php if ($asgn->checked_in): ?>
                                    <span style="color: #28a745; font-weight: 600;">&#10003; Checked In</span>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">🎯</div>
            <h4>Select an Area</h4>
            <p>Choose an area from the dropdown above to manage its shifts.</p>
        </div>
    <?php endif; ?>
</div>


<script>
function toggleTimeFields() {
    const type = document.querySelector('input[name="shift_type"]:checked').value;
    const timeFields = document.getElementById('time-fields');
    const startInput = document.getElementById('shift-start');
    const endInput = document.getElementById('shift-end');
    
    if (type === 'time_slot') {
        timeFields.style.display = 'block';
        startInput.required = true;
        endInput.required = true;
    } else {
        timeFields.style.display = 'none';
        startInput.required = false;
        endInput.required = false;
    }
}

function toggleCreateShiftPanel() {
    const panel = document.getElementById('create-shift-panel');
    if (panel.style.display === 'none') {
        // Reset for create mode
        document.getElementById('shift-panel-title').textContent = 'Create New Shift';
        document.getElementById('shift-submit-text').textContent = '✅ Create Shift';
        document.getElementById('shift-form').reset();
        document.getElementById('shift-id').value = '';
        document.getElementById('shift-date').value = '<?php echo esc_js($convention_date); ?>';
        toggleTimeFields();
        panel.style.display = 'block';
        // Scroll to panel
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        panel.style.display = 'none';
    }
}

function editShift(shiftId) {
    // Load shift data and populate form
    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'oha_vh_get_shift',
            shift_id: shiftId,
            nonce: '<?php echo wp_create_nonce('oha_vh_board'); ?>'
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const shift = data.data;
            document.getElementById('shift-panel-title').textContent = 'Edit Shift';
            document.getElementById('shift-submit-text').textContent = '✅ Update Shift';
            document.getElementById('shift-id').value = shift.id;
            document.getElementById('shift-date').value = shift.shift_date;
            document.getElementById('shift-slots').value = shift.slots_available;
            document.querySelector(`input[name="shift_type"][value="${shift.shift_type}"]`).checked = true;
            
            if (shift.shift_type === 'time_slot') {
                document.getElementById('shift-start').value = shift.shift_start;
                document.getElementById('shift-end').value = shift.shift_end;
            }
            
            toggleTimeFields();
            // Show panel
            const panel = document.getElementById('create-shift-panel');
            panel.style.display = 'block';
            // Scroll to panel
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });
}

function deleteShift(shiftId) {
    if (!confirm('Delete this shift? All volunteer assignments for this shift will be removed.')) return;
    
    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'oha_vh_delete_shift',
            shift_id: shiftId,
            nonce: '<?php echo wp_create_nonce('oha_vh_board'); ?>'
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ Shift deleted successfully!');
            location.reload();
        } else {
            alert(data.data.message || 'Error deleting shift');
        }
    });
}

function viewShiftAssignments(shiftId) {
    const panel = document.getElementById('shift-assignments-' + shiftId);
    if (!panel) {
        alert('No volunteers assigned to this shift yet.');
        return;
    }
    panel.style.display = (panel.style.display === 'none') ? 'block' : 'none';
}

// Handle shift form submission
document.getElementById('shift-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const shiftId = document.getElementById('shift-id').value;
    
    formData.append('action', shiftId ? 'oha_vh_update_shift' : 'oha_vh_create_shift');
    formData.append('nonce', '<?php echo wp_create_nonce('oha_vh_board'); ?>');
    
    fetch(ajaxurl, {
        method: 'POST',
        body: new URLSearchParams(formData)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(shiftId ? '✅ Shift updated!' : '✅ Shift created!');
            toggleCreateShiftPanel();
            location.reload();
        } else {
            alert(data.data.message || 'Error saving shift');
        }
    });
});
</script>

<style>
/* Shifts Tab Styles */
.area-selector {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.selected-area-info {
    background: white;
    border: 1px solid #ddd;
    border-left: 4px solid #e74c3c;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
}

.area-icon-large {
    font-size: 64px;
}

.area-info-text h4 {
    margin: 0 0 10px 0;
}

.area-capacity {
    color: #666;
    font-size: 14px;
    margin-top: 10px;
}

.shifts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.shift-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
}

.shift-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.shift-type-badge {
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    color: white;
}

.shift-type-all_day { background: #2271b1; }
.shift-type-time_slot { background: #28a745; }

.shift-capacity {
    font-size: 20px;
    font-weight: bold;
    color: #333;
}

.shift-info {
    margin-bottom: 15px;
}

.shift-date,
.shift-time {
    margin-bottom: 8px;
    color: #666;
}

.shift-progress {
    margin-top: 15px;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: #f0f0f0;
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
    transition: width 0.3s;
}

.shift-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.button-delete:hover {
    background: #dc3545;
    border-color: #dc3545;
    color: white;
}

.radio-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.radio-group label {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border: 2px solid #ddd;
    border-radius: 6px;
    cursor: pointer;
}

.radio-group input[type="radio"]:checked + * {
    border-color: #e74c3c;
    background: #fff3f3;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

@media (max-width: 768px) {
    .selected-area-info {
        flex-direction: column;
        text-align: center;
    }
    
    .shifts-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>
