<?php
/**
 * Areas Tab - Manage convention areas
 * Add, edit, and configure volunteer areas
 */

if (!defined('ABSPATH')) { exit; }

// Get users - try multiple role variations since different sites use different slugs
$role_args = ['role__in' => ['administrator', 'editor', 'oha_board_member', 'oha_board', 'oha-board']];
$all_users = get_users($role_args);

// If we still don't have many users, get ALL users and filter out subscribers
if (count($all_users) < 2) {
    $all_users = get_users(['orderby' => 'display_name', 'order' => 'ASC']);
    // Remove subscribers from the list
    $all_users = array_filter($all_users, function($user) {
        $user_roles = $user->roles;
        return !in_array('subscriber', $user_roles) || in_array('administrator', $user_roles);
    });
}
?>

<div class="oha-vh-areas-tab">
    <div class="tab-header">
        <h3>Area Management</h3>
        <p>Configure convention areas, assign managers, and set capacity limits</p>
        
        <?php if ($is_admin): ?>
            <button class="button button-primary" onclick="toggleCreateAreaPanel()">
                ➕ Create New Area
            </button>
        <?php endif; ?>
    </div>

    <!-- Inline Area Creation Panel (Hidden by default) -->
    <?php if ($is_admin): ?>
    <div id="create-area-panel" class="create-panel" style="display: none; background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
        <h4 style="margin-top: 0;">Create New Area</h4>
        <form id="area-form">
            <input type="hidden" name="area_id" id="area-id" value="">
            
            <div class="form-group">
                <label><strong>Themed Area Name:</strong> <span class="required">*</span></label>
                <input type="text" name="area_name" id="area-name" required
                       placeholder="e.g., Gateway Guardians" style="width: 100%; padding: 10px;">
                <small>Shown to volunteers on the signup form</small>
            </div>

            <div class="form-group">
                <label><strong>Board Alt Name:</strong></label>
                <input type="text" name="alt_name" id="area-alt-name"
                       placeholder="e.g., Front Entrance" style="width: 100%; padding: 10px;">
                <small>Optional. If set, shown <em>instead of</em> the themed name on the Assign tab for easier board-side identification. Themed name shown in parentheses.</small>
            </div>

            <div class="form-group">
                <label><strong>Description:</strong></label>
                <textarea name="description" id="area-description" rows="3" 
                          placeholder="Brief description of this area's responsibilities..." 
                          style="width: 100%; padding: 10px;"></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label><strong>Icon/Emoji:</strong></label>
                    <input type="text" name="icon" id="area-icon" maxlength="10" 
                           placeholder="🎯" style="width: 100%; padding: 10px; font-size: 24px;">
                    <small>Enter an emoji or icon</small>
                </div>
                <div class="form-group">
                    <label><strong>Color:</strong></label>
                    <input type="color" name="area_color" id="area-color" value="#e74c3c" 
                           style="width: 100%; height: 50px; padding: 5px;">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label><strong>Min Volunteers:</strong></label>
                    <input type="number" name="min_volunteers" id="area-min" min="0" max="100" 
                           value="1" style="width: 100%; padding: 10px;">
                </div>
                <div class="form-group">
                    <label><strong>Max Volunteers:</strong></label>
                    <input type="number" name="max_volunteers" id="area-max" min="1" max="100" 
                           value="10" style="width: 100%; padding: 10px;">
                </div>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" id="area-active" value="1" checked>
                    <strong>Active</strong> (unchecked areas won't show in signup form)
                </label>
            </div>
            
            <div class="form-actions">
                <button type="button" class="button" onclick="toggleCreateAreaPanel()">Cancel</button>
                <button type="submit" class="button button-primary">
                    <span id="area-submit-text">✅ Create Area</span>
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php if (empty($areas_to_show)): ?>
        <div class="empty-state">
            <div class="empty-icon">🎯</div>
            <h4>No Areas Yet</h4>
            <p>Create your first convention area to start organizing volunteers.</p>
            <?php if ($is_admin): ?>
                <button class="button button-primary button-large" onclick="openCreateAreaModal()">
                    ➕ Create First Area
                </button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="areas-list">
            <?php foreach ($areas_to_show as $area): 
                $managers = json_decode($area->area_managers, true) ?: [];
                $assignments = \OHA\VH\Database::get_assignments_by_area($area->id);
                $shifts = \OHA\VH\Database::get_shifts_by_area($area->id);
            ?>
            <div class="area-manage-card" style="border-left-color: <?php echo esc_attr($area->color); ?>">
                <div class="area-manage-header">
                    <div class="area-title-section">
                        <span class="area-icon-big"><?php echo esc_html($area->icon); ?></span>
                        <div>
                            <h4><?php echo esc_html($area->area_name); ?></h4>
                            <p class="area-description"><?php echo esc_html($area->description); ?></p>
                        </div>
                    </div>
                    <div class="area-actions-section">
                        <button class="button" onclick="editArea(<?php echo esc_js($area->id); ?>)">
                            ✏️ Edit
                        </button>
                        <?php if ($is_admin): ?>
                            <button class="button button-delete" onclick="deleteArea(<?php echo esc_js($area->id); ?>)">
                                🗑️ Delete
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="area-manage-stats">
                    <div class="stat-box">
                        <div class="stat-number"><?php echo count($assignments); ?></div>
                        <div class="stat-label">Volunteers Assigned</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo count($shifts); ?></div>
                        <div class="stat-label">Shifts Created</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo esc_html($area->min_volunteers ?? 0); ?>-<?php echo esc_html($area->max_volunteers ?? '∞'); ?></div>
                        <div class="stat-label">Capacity Range</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo count($managers); ?></div>
                        <div class="stat-label">Managers</div>
                    </div>
                </div>

                <div class="area-managers-section">
                    <div class="managers-header">
                        <strong>Area Managers:</strong>
                        <?php if (empty($managers)): ?>
                            <span style="color: #999;">No managers assigned</span>
                        <?php else: ?>
                            <div class="managers-list">
                                <?php foreach ($managers as $manager_id): 
                                    $manager = get_userdata($manager_id);
                                    if ($manager):
                                ?>
                                    <span class="manager-badge">
                                        👤 <?php echo esc_html($manager->display_name); ?>
                                    </span>
                                <?php 
                                    endif;
                                endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button class="button button-small" onclick="toggleManagersEdit(<?php echo esc_js($area->id); ?>)">
                        👥 Edit Managers
                    </button>

                    <!-- Inline Manager Editor (Hidden by default) -->
                    <div id="managers-edit-<?php echo esc_attr($area->id); ?>" class="managers-edit-panel" style="display: none; margin-top: 15px; padding: 15px; background: #f9f9f9; border-radius: 5px;">
                        <h4 style="margin-top: 0;">Select Area Managers:</h4>
                        <form class="inline-managers-form" data-area-id="<?php echo esc_attr($area->id); ?>">
                            <div class="managers-checklist-inline">
                                <?php foreach ($all_users as $user):
                                    $is_checked = in_array($user->ID, $managers);
                                ?>
                                    <label class="manager-checkbox-inline">
                                        <input type="checkbox"
                                               name="managers[]"
                                               value="<?php echo esc_attr($user->ID); ?>"
                                               <?php checked($is_checked); ?>>
                                        <span class="manager-info-inline">
                                            <strong><?php echo esc_html($user->display_name); ?></strong>
                                            <span class="manager-email-small">(<?php echo esc_html($user->user_email); ?>)</span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <div style="margin-top: 15px; display: flex; gap: 10px;">
                                <button type="submit" class="button button-primary">✅ Save Managers</button>
                                <button type="button" class="button" onclick="toggleManagersEdit(<?php echo esc_js($area->id); ?>)">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="area-quick-links">
                    <a href="#" onclick="location.href=buildBoardHubUrl('shifts', {area_id: <?php echo esc_js($area->id); ?>}); return false;" class="quick-link">
                        📅 Manage Shifts →
                    </a>
                    <a href="#" onclick="location.href=buildBoardHubUrl('assign', {area_id: <?php echo esc_js($area->id); ?>}); return false;" class="quick-link">
                        📍 Assign Volunteers →
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Toggle create area panel
function toggleCreateAreaPanel() {
    const panel = document.getElementById('create-area-panel');
    if (panel.style.display === 'none') {
        // Reset for create mode
        document.getElementById('area-submit-text').textContent = '✅ Create Area';
        document.getElementById('area-form').reset();
        document.getElementById('area-id').value = '';
        document.getElementById('area-active').checked = true;
        panel.style.display = 'block';
        // Scroll to panel
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        panel.style.display = 'none';
    }
}

function editArea(areaId) {
    // Fetch area data
    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'oha_vh_get_area',
            area_id: areaId,
            nonce: '<?php echo wp_create_nonce('oha_vh_board'); ?>'
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const area = data.data;
            // Fill form with area data
            document.getElementById('area-submit-text').textContent = '✅ Update Area';
            document.getElementById('area-id').value = area.id;
            document.getElementById('area-name').value = area.area_name;
            document.getElementById('area-alt-name').value = area.alt_name || '';
            document.getElementById('area-description').value = area.description || '';
            document.getElementById('area-icon').value = area.icon || '';
            document.getElementById('area-color').value = area.color || '#e74c3c';
            document.getElementById('area-min').value = area.min_volunteers || 1;
            document.getElementById('area-max').value = area.max_volunteers || 10;
            document.getElementById('area-active').checked = area.is_active == 1;
            
            // Show panel
            const panel = document.getElementById('create-area-panel');
            panel.style.display = 'block';
            // Scroll to panel
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });
}

function deleteArea(areaId) {
    if (!confirm('Delete this area? This will remove all shifts and assignments. This cannot be undone!')) return;
    
    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'oha_vh_delete_area',
            area_id: areaId,
            nonce: '<?php echo wp_create_nonce('oha_vh_board'); ?>'
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ Area deleted successfully!');
            location.reload();
        } else {
            alert(data.data.message || 'Error deleting area');
        }
    });
}

// Toggle inline manager editor
function toggleManagersEdit(areaId) {
    const panel = document.getElementById('managers-edit-' + areaId);
    if (panel.style.display === 'none') {
        // Hide all other open panels first
        document.querySelectorAll('.managers-edit-panel').forEach(p => p.style.display = 'none');
        // Show this one
        panel.style.display = 'block';
    } else {
        panel.style.display = 'none';
    }
}

// Handle area form submission
document.getElementById('area-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const areaId = document.getElementById('area-id').value;
    
    formData.append('action', areaId ? 'oha_vh_update_area' : 'oha_vh_create_area');
    formData.append('nonce', '<?php echo wp_create_nonce('oha_vh_board'); ?>');
    
    fetch(ajaxurl, {
        method: 'POST',
        body: new URLSearchParams(formData)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(areaId ? '✅ Area updated!' : '✅ Area created!');
            toggleCreateAreaPanel();
            location.reload();
        } else {
            alert(data.data.message || 'Error saving area');
        }
    });
});

// Handle inline managers form submissions
document.querySelectorAll('.inline-managers-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const areaId = this.getAttribute('data-area-id');
        const checkboxes = this.querySelectorAll('input[name="managers[]"]:checked');
        const managers = Array.from(checkboxes).map(cb => cb.value);
        
        // Use jQuery AJAX for better compatibility
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'oha_vh_update_area_managers',
                area_id: areaId,
                managers: managers,
                _wpnonce: '<?php echo wp_create_nonce('oha_vh_board'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ Area managers updated!');
                    location.reload();
                } else {
                    alert('Error: ' + (response.data?.message || 'Failed to update'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error, xhr.responseText);
                alert('Error updating managers. Status: ' + status);
            }
        });
    });
});
</script>

<style>
/* Areas Tab Styles */
.create-panel {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.create-panel h4 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #333;
    font-size: 16px;
}

.areas-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.area-manage-card {
    background: white;
    border: 1px solid #ddd;
    border-left: 4px solid #e74c3c;
    border-radius: 8px;
    padding: 25px;
}

.area-manage-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
    gap: 20px;
}

.area-title-section {
    display: flex;
    gap: 15px;
    flex: 1;
}

.area-icon-big {
    font-size: 48px;
}

.area-title-section h4 {
    margin: 0 0 8px 0;
}

.area-description {
    color: #666;
    margin: 0;
}

.area-actions-section {
    display: flex;
    gap: 10px;
}

.area-manage-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-box {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 6px;
    text-align: center;
}

.stat-number {
    font-size: 28px;
    font-weight: bold;
    color: #333;
}

.stat-label {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
    margin-top: 5px;
}

.area-managers-section {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.managers-header {
    margin-bottom: 10px;
}

.managers-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 10px 0;
}

.manager-badge {
    display: inline-block;
    background: white;
    border: 1px solid #ddd;
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 13px;
}

/* Inline Manager Editor Panel */
.managers-edit-panel {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 15px;
    margin-top: 15px;
}

.managers-edit-panel h4 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #333;
}

.managers-checklist-inline {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 12px;
    background: white;
}

.manager-checkbox-inline {
    display: flex;
    align-items: center;
    padding: 10px;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
}

.manager-checkbox-inline:last-child {
    border-bottom: none;
}

.manager-checkbox-inline:hover {
    background: #f5f5f5;
}

.manager-checkbox-inline input[type="checkbox"] {
    margin-right: 12px;
    width: 18px;
    height: 18px;
}

.manager-info-inline {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.manager-info-inline strong {
    color: #333;
    font-size: 14px;
}

.manager-email-small {
    color: #666;
    font-size: 12px;
}

.area-quick-links {
    display: flex;
    gap: 15px;
}

.quick-link {
    color: #2271b1;
    text-decoration: none;
    font-weight: 600;
}

.quick-link:hover {
    text-decoration: underline;
}

@media (max-width: 768px) {
    .area-manage-header {
        flex-direction: column;
    }
    
    .area-title-section {
        flex-direction: column;
        text-align: center;
    }
    
    .area-actions-section {
        width: 100%;
        justify-content: center;
    }
    
    .area-manage-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
