<?php
/**
 * Assign Tab - Assign volunteers to areas and shifts
 * Board members see only their areas
 */

if (!defined('ABSPATH')) { exit; }

// Get ALL volunteers that can be assigned (pending, approved, assigned)
$volunteers_pending = \OHA\VH\Database::get_volunteers(['status' => 'pending', 'limit' => 500]);
$volunteers_approved = \OHA\VH\Database::get_volunteers(['status' => 'approved', 'limit' => 500]);
$volunteers_assigned = \OHA\VH\Database::get_volunteers(['status' => 'assigned', 'limit' => 500]);
$all_volunteers = array_merge($volunteers_pending, $volunteers_approved, $volunteers_assigned);

// Pre-select volunteer if coming from link
$preselect_volunteer = isset($_GET['volunteer_id']) ? intval($_GET['volunteer_id']) : 0;
?>

<div class="oha-vh-assign-tab">
    <div class="tab-header">
        <h3>Assign Volunteers</h3>
        <p>Assign volunteers from the pool to your areas and shifts</p>
    </div>

<script>
// Define critical functions FIRST before any HTML uses them
function openAssignModal(volunteerId) {
    document.getElementById('assign-volunteer-id').value = volunteerId;
    
    const modal = document.getElementById('assign-modal');
    modal.style.display = 'flex';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
    
    document.getElementById('assign-area-select').value = '';
    document.getElementById('shift-select-group').style.display = 'none';
    document.getElementById('area-description-box').style.display = 'none';
}

function closeAssignModal() {
    document.getElementById('assign-modal').style.display = 'none';
    document.getElementById('assign-volunteer-form').reset();
}

function submitAssignment() {
    const volunteerId = document.getElementById('assign-volunteer-id').value;
    const areaId = document.getElementById('assign-area-select').value;
    const shiftId = document.getElementById('assign-shift-select').value;
    
    if (!areaId) {
        alert('Please select an area');
        return;
    }
    
    // Use jQuery.ajax for better compatibility
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'oha_vh_assign_volunteer',
            volunteer_id: volunteerId,
            area_id: areaId,
            shift_id: shiftId || '',
            nonce: '<?php echo wp_create_nonce('oha_vh_board'); ?>'
        },
        success: function(response) {
            if (response.success) {
                alert('✅ Volunteer assigned successfully!');
                closeAssignModal();
                location.reload();
            } else {
                alert('Error: ' + (response.data.message || 'Failed to assign volunteer'));
            }
        },
        error: function(xhr, status, error) {
            console.error('Assignment error:', error, xhr.responseText);
            alert('Error assigning volunteer. Please try again.');
        }
    });
}

function viewAreaAssignments(areaId) {
    // Scroll to the area in the assignments list
    const areaCard = document.querySelector(`[data-area-id="${areaId}"]`);
    if (areaCard) {
        areaCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        // Highlight the area briefly
        areaCard.style.transition = 'all 0.3s';
        areaCard.style.boxShadow = '0 0 20px rgba(231, 76, 60, 0.5)';
        setTimeout(() => {
            areaCard.style.boxShadow = '';
        }, 2000);
    }
}
</script>

    <div class="assign-layout">
        <!-- Left: Volunteer Pool -->
        <div class="assign-pool">
            <h4>Available Volunteers (<?php echo count($all_volunteers); ?>)</h4>
            
            <div class="search-box" style="display: flex; gap: 10px; margin-bottom: 15px;">
                <input type="search" id="volunteer-search" placeholder="Search volunteers..." 
                       style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                <select id="status-filter" style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; min-width: 150px;">
                    <option value="all">All Status</option>
                    <option value="pending">🟡 Pending</option>
                    <option value="approved">🟢 Approved</option>
                    <option value="assigned">🔵 Assigned</option>
                </select>
            </div>

            <div class="volunteer-list">
                <?php if (empty($all_volunteers)): ?>
                    <div class="empty-state-small">
                        <p>No approved volunteers available.</p>
                        <p><a href="#" onclick="location.href=buildBoardHubUrl('volunteers', {vh_status: 'pending'}); return false;">Review pending signups →</a></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($all_volunteers as $v): 
                        $assignments = \OHA\VH\Database::get_volunteer_assignments($v->id);
                        $preferred = json_decode($v->preferred_areas, true) ?: [];
                    ?>
                    <div class="volunteer-card" data-volunteer-id="<?php echo esc_attr($v->id); ?>" 
                         data-name="<?php echo esc_attr(strtolower($v->first_name . ' ' . $v->last_name . ' ' . $v->email)); ?>">
                        <div class="volunteer-info">
                            <strong><?php echo esc_html($v->first_name . ' ' . $v->last_name); ?></strong>
                            <span class="status-badge status-<?php echo esc_attr($v->status); ?>" style="margin-left: 8px; font-size: 11px; padding: 3px 8px;">
                                <?php echo esc_html(ucfirst($v->status)); ?>
                            </span>
                            <div class="volunteer-meta">
                                <?php echo esc_html($v->email); ?>
                                <?php if ($v->phone): ?>
                                    • <?php echo esc_html($v->phone); ?>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($preferred)): ?>
                                <div class="preferred-areas">
                                    Prefers: 
                                    <?php 
                                    $pref_names = [];
                                    foreach (array_slice($preferred, 0, 2) as $area_id) {
                                        $area = \OHA\VH\Database::get_area($area_id);
                                        if ($area) $pref_names[] = $area->icon . ' ' . $area->area_name;
                                    }
                                    echo esc_html(implode(', ', $pref_names));
                                    ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($assignments)): ?>
                                <div class="current-assignments">
                                    📍 Currently assigned to <?php echo count($assignments); ?> area(s)
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="volunteer-actions">
                            <button class="button button-primary" onclick="openAssignModal(<?php echo intval($v->id); ?>)">
                                Assign →
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Your Areas -->
        <div class="assign-areas">
            <h4>Your Areas<?php if (!$is_admin && count($areas_to_show) > 0): ?> (<?php echo count($areas_to_show); ?>)<?php endif; ?></h4>
            
            <?php if (empty($areas_to_show)): ?>
                <div class="empty-state-small">
                    <p>You haven't been assigned as a manager to any areas yet.</p>
                    <?php if (!$is_admin): ?>
                        <p>Contact an administrator to be assigned as an area manager.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="areas-grid">
                    <?php foreach ($areas_to_show as $area): 
                        $assignments = \OHA\VH\Database::get_assignments_by_area($area->id);
                        $assigned_count = count($assignments);
                    ?>
                    <div class="area-summary-card" data-area-id="<?php echo esc_attr($area->id); ?>" style="border-left-color: <?php echo esc_attr($area->color); ?>">
                        <div class="area-header">
                            <span class="area-icon"><?php echo esc_html($area->icon); ?></span>
                            <div>
                                <strong><?php echo esc_html(!empty($area->alt_name) ? $area->alt_name : $area->area_name); ?></strong>
                                <?php if (!empty($area->alt_name)): ?>
                                    <div style="font-size:11px; color:#999; margin-top:1px;"><?php echo esc_html($area->area_name); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="area-stats">
                            <div class="stat">
                                <span class="stat-number"><?php echo $assigned_count; ?></span>
                                <span class="stat-label">Assigned</span>
                            </div>
                            <div class="stat">
                                <span class="stat-number"><?php echo esc_html($area->max_volunteers ?? '∞'); ?></span>
                                <span class="stat-label">Max</span>
                            </div>
                        </div>
                        <div class="area-actions">
                            <a href="#" onclick="location.href=buildBoardHubUrl('shifts', {area_id: <?php echo esc_js($area->id); ?>}); return false;" class="button button-small">
                                📅 Shifts
                            </a>
                            <button class="button button-small" onclick="viewAreaAssignments(<?php echo esc_js($area->id); ?>)">
                                👁️ View All
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Assignment Modal -->
<div id="assign-modal" class="oha-modal" style="display:none; position:fixed !important; z-index:999999 !important; left:0 !important; top:0 !important; width:100% !important; height:100% !important; background-color:rgba(0,0,0,0.7) !important; align-items:center !important; justify-content:center !important;">
    <div class="oha-modal-content" style="background:white; padding:30px; border-radius:8px; max-width:700px; max-height:85vh; overflow-y:auto; position:relative; box-shadow:0 10px 40px rgba(0,0,0,0.3);">
        <span class="oha-modal-close" onclick="closeAssignModal()" style="position:absolute; right:15px; top:15px; font-size:32px; font-weight:bold; cursor:pointer; color:#999; line-height:1;">&times;</span>
        <div id="assign-modal-content">
            <h3>Assign Volunteer</h3>
            <form id="assign-volunteer-form" onsubmit="return false;">
                <input type="hidden" id="assign-volunteer-id" name="volunteer_id">
                
                <div class="form-group">
                    <label><strong>Select Area:</strong></label>
                    <select name="area_id" id="assign-area-select" required style="width: 100%; padding: 10px;">
                        <option value="">-- Choose Area --</option>
                        <?php foreach ($areas_to_show as $area):
                            $display_name = !empty($area->alt_name)
                                ? $area->alt_name . ' (' . $area->area_name . ')'
                                : $area->area_name;
                        ?>
                            <option value="<?php echo esc_attr($area->id); ?>"
                                    data-description="<?php echo esc_attr($area->description); ?>">
                                <?php echo esc_html($area->icon . ' ' . $display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="area-description-box" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-left: 3px solid #2271b1; display: none;">
                        <small><strong>What this area covers:</strong></small>
                        <p id="area-description-text" style="margin: 5px 0 0 0; font-size: 13px;"></p>
                    </div>
                </div>

                <div class="form-group" id="shift-select-group" style="display:none;">
                    <label><strong>Select Shift (optional):</strong></label>
                    <select name="shift_id" id="assign-shift-select" style="width: 100%; padding: 10px;">
                        <option value="">-- All Day (No specific shift) --</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" class="button" onclick="closeAssignModal()">Cancel</button>
                    <button type="button" class="button button-primary" onclick="submitAssignment()">✅ Assign Volunteer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Combined search and filter function
function filterVolunteers() {
    const search = document.getElementById('volunteer-search').value.toLowerCase();
    const statusFilter = document.getElementById('status-filter').value;
    const cards = document.querySelectorAll('.volunteer-card');
    
    cards.forEach(card => {
        const name = card.dataset.name;
        const statusBadge = card.querySelector('.status-badge');
        const status = statusBadge ? statusBadge.textContent.toLowerCase().trim() : '';
        
        const matchesSearch = name.includes(search);
        const matchesStatus = statusFilter === 'all' || status === statusFilter;
        
        if (matchesSearch && matchesStatus) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

// Volunteer search
document.getElementById('volunteer-search')?.addEventListener('keyup', filterVolunteers);

// Status filter
document.getElementById('status-filter')?.addEventListener('change', filterVolunteers);

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('assign-modal');
    if (e.target === modal) {
        closeAssignModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('assign-modal');
        if (modal && modal.style.display === 'flex') {
            closeAssignModal();
        }
    }
});

// Load shifts when area is selected
document.getElementById('assign-area-select')?.addEventListener('change', function() {
    const areaId = this.value;
    const descriptionBox = document.getElementById('area-description-box');
    const descriptionText = document.getElementById('area-description-text');
    
    if (!areaId) {
        descriptionBox.style.display = 'none';
        document.getElementById('shift-select-group').style.display = 'none';
        return;
    }
    
    // Show area description
    const selectedOption = this.options[this.selectedIndex];
    const description = selectedOption.getAttribute('data-description');
    if (description) {
        descriptionText.textContent = description;
        descriptionBox.style.display = 'block';
    } else {
        descriptionBox.style.display = 'none';
    }

    // Load shifts for this area
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
            const select = document.getElementById('assign-shift-select');
            select.innerHTML = '<option value="">-- All Day (No specific shift) --</option>';
            
            data.data.shifts.forEach(shift => {
                const option = document.createElement('option');
                option.value = shift.id;
                option.textContent = shift.label;
                select.appendChild(option);
            });
            
            document.getElementById('shift-select-group').style.display = 'block';
        }
    });
});

// Auto-open modal if volunteer pre-selected
<?php if ($preselect_volunteer > 0): ?>
    setTimeout(() => openAssignModal(<?php echo $preselect_volunteer; ?>), 500);
<?php endif; ?>
</script>

<style>
/* Assign Tab Styles */
.assign-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.assign-pool,
.assign-areas {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 8px;
    min-height: 400px;
}

.assign-pool h4,
.assign-areas h4 {
    margin-top: 0;
    color: #333;
}

.search-box {
    margin-bottom: 20px;
}

.volunteer-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
    max-height: 600px;
    overflow-y: auto;
}

.volunteer-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.volunteer-info {
    flex: 1;
}

.volunteer-meta {
    font-size: 13px;
    color: #666;
    margin-top: 5px;
}

.preferred-areas,
.current-assignments {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}

.current-assignments {
    color: #2271b1;
    font-weight: 600;
}

.areas-grid {
    display: grid;
    gap: 15px;
}

.area-summary-card {
    background: white;
    border: 1px solid #ddd;
    border-left: 4px solid #e74c3c;
    border-radius: 6px;
    padding: 15px;
}

.area-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.area-icon {
    font-size: 24px;
}

.area-stats {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
}

.stat {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #333;
}

.stat-label {
    font-size: 12px;
    color: #666;
}

.area-actions {
    display: flex;
    gap: 10px;
}

.empty-state-small {
    text-align: center;
    padding: 40px 20px;
    color: #999;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 30px;
}

@media (max-width: 968px) {
    .assign-layout {
        grid-template-columns: 1fr;
    }
}
</style>
