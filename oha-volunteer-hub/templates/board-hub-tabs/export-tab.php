<?php
/**
 * Export Tab - CSV export with filters
 * Export volunteer data for backup and management
 */

if (!defined('ABSPATH')) { exit; }

$counts = \OHA\VH\Database::get_volunteer_counts();
$total = array_sum(array_column($counts, 'count'));
?>

<div class="oha-vh-export-tab">
    <div class="tab-header">
        <h3>Export Volunteers</h3>
        <p>Download volunteer data as CSV for backup, analysis, or mail merge</p>
    </div>

    <!-- Export Stats -->
    <div class="export-stats">
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $total; ?></div>
                <div class="stat-label">Total Volunteers</div>
            </div>
        </div>
        <?php foreach ($counts as $count): ?>
            <div class="stat-card status-<?php echo esc_attr($count->status); ?>">
                <div class="stat-info">
                    <div class="stat-number"><?php echo $count->count; ?></div>
                    <div class="stat-label"><?php echo ucfirst($count->status); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Export Options -->
    <div class="export-options">
        <h4>Export Options</h4>
        
        <form id="export-form" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="oha_vh_export_volunteers">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('oha_vh_export'); ?>">
            
            <!-- Filter by Status -->
            <div class="option-group">
                <label><strong>Filter by Status:</strong></label>
                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="statuses[]" value="all" checked onchange="toggleAllStatuses(this)">
                        <strong>All Status</strong>
                    </label>
                    <label>
                        <input type="checkbox" name="statuses[]" value="pending" class="status-checkbox">
                        Pending (<?php echo $counts[0]->count ?? 0; ?>)
                    </label>
                    <label>
                        <input type="checkbox" name="statuses[]" value="approved" class="status-checkbox">
                        Approved (<?php echo $counts[1]->count ?? 0; ?>)
                    </label>
                    <label>
                        <input type="checkbox" name="statuses[]" value="assigned" class="status-checkbox">
                        Assigned (<?php echo $counts[2]->count ?? 0; ?>)
                    </label>
                    <label>
                        <input type="checkbox" name="statuses[]" value="declined" class="status-checkbox">
                        Declined (<?php echo $counts[3]->count ?? 0; ?>)
                    </label>
                </div>
            </div>

            <!-- Filter by Area -->
            <div class="option-group">
                <label><strong>Filter by Preferred Area:</strong></label>
                <select name="area_id" style="width: 100%; padding: 10px;">
                    <option value="">All Areas</option>
                    <?php foreach ($all_areas as $area): ?>
                        <option value="<?php echo esc_attr($area->id); ?>">
                            <?php echo esc_html($area->icon . ' ' . $area->area_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Fields to Include -->
            <div class="option-group">
                <label><strong>Fields to Include:</strong></label>
                <div class="checkbox-group columns-2">
                    <label>
                        <input type="checkbox" name="fields[]" value="basic" checked>
                        <strong>Basic Info</strong> (name, email, phone)
                    </label>
                    <label>
                        <input type="checkbox" name="fields[]" value="experience" checked>
                        Experience Level
                    </label>
                    <label>
                        <input type="checkbox" name="fields[]" value="areas" checked>
                        Preferred Areas
                    </label>
                    <label>
                        <input type="checkbox" name="fields[]" value="availability" checked>
                        Availability Notes
                    </label>
                    <label>
                        <input type="checkbox" name="fields[]" value="skills" checked>
                        Special Skills
                    </label>
                    <label>
                        <input type="checkbox" name="fields[]" value="emergency" checked>
                        Emergency Contact
                    </label>
                    <label>
                        <input type="checkbox" name="fields[]" value="status" checked>
                        Status & Date
                    </label>
                    <label>
                        <input type="checkbox" name="fields[]" value="assignments">
                        Current Assignments
                    </label>
                    <label>
                        <input type="checkbox" name="fields[]" value="checkin">
                        Check-in Status
                    </label>
                    <label>
                        <input type="checkbox" name="fields[]" value="hours">
                        Hours Worked
                    </label>
                </div>
            </div>

            <!-- Export Button -->
            <div class="export-actions">
                <button type="submit" class="button button-primary button-hero">
                    📥 Download CSV Export
                </button>
            </div>
        </form>
    </div>

    <!-- Export Info -->
    <div class="export-info">
        <h4>ℹ️ About CSV Exports</h4>
        <ul>
            <li><strong>File Format:</strong> Standard CSV (Comma-Separated Values) compatible with Excel, Google Sheets, and most database software</li>
            <li><strong>Data Privacy:</strong> Export files contain sensitive personal information. Store them securely and delete when no longer needed</li>
            <li><strong>Best Practices:</strong>
                <ul>
                    <li>Export regularly for backup purposes</li>
                    <li>Use filters to create targeted mailing lists</li>
                    <li>Include assignments for day-of-event coordination</li>
                    <li>Check-in and hours data useful for post-event reporting</li>
                </ul>
            </li>
        </ul>
    </div>

    <!-- Quick Export Presets -->
    <div class="export-presets">
        <h4>🚀 Quick Export Presets</h4>
        <p>One-click exports for common scenarios:</p>
        
        <div class="preset-grid">
            <button class="preset-button" onclick="quickExport('all')">
                <span class="preset-icon">👥</span>
                <strong>All Volunteers</strong>
                <span class="preset-desc">Complete volunteer database</span>
            </button>
            
            <button class="preset-button" onclick="quickExport('approved')">
                <span class="preset-icon">✅</span>
                <strong>Approved Only</strong>
                <span class="preset-desc">Ready to assign</span>
            </button>
            
            <button class="preset-button" onclick="quickExport('assigned')">
                <span class="preset-icon">📍</span>
                <strong>Assigned Volunteers</strong>
                <span class="preset-desc">With area assignments</span>
            </button>
            
            <button class="preset-button" onclick="quickExport('mailing')">
                <span class="preset-icon">✉️</span>
                <strong>Mailing List</strong>
                <span class="preset-desc">Just names & emails</span>
            </button>
            
            <button class="preset-button" onclick="quickExport('dayone')">
                <span class="preset-icon">📅</span>
                <strong>Day-Of Roster</strong>
                <span class="preset-desc">Assigned + check-in fields</span>
            </button>
            
            <button class="preset-button" onclick="quickExport('pending')">
                <span class="preset-icon">⏳</span>
                <strong>Pending Review</strong>
                <span class="preset-desc">Needs approval</span>
            </button>
        </div>
    </div>
</div>

<script>
function toggleAllStatuses(checkbox) {
    const statusBoxes = document.querySelectorAll('.status-checkbox');
    statusBoxes.forEach(box => {
        box.checked = checkbox.checked;
        box.disabled = checkbox.checked;
    });
}

function quickExport(preset) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?php echo admin_url('admin-post.php'); ?>';
    
    const action = document.createElement('input');
    action.type = 'hidden';
    action.name = 'action';
    action.value = 'oha_vh_export_volunteers';
    form.appendChild(action);
    
    const nonce = document.createElement('input');
    nonce.type = 'hidden';
    nonce.name = 'nonce';
    nonce.value = '<?php echo wp_create_nonce('oha_vh_export'); ?>';
    form.appendChild(nonce);
    
    const presetInput = document.createElement('input');
    presetInput.type = 'hidden';
    presetInput.name = 'preset';
    presetInput.value = preset;
    form.appendChild(presetInput);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>

<style>
/* Export Tab Styles */
.export-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 40px;
}

.stat-card {
    background: white;
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}

.stat-card.status-pending { border-color: #ffc107; }
.stat-card.status-approved { border-color: #28a745; }
.stat-card.status-assigned { border-color: #2271b1; }
.stat-card.status-declined { border-color: #dc3545; }

.stat-icon {
    font-size: 48px;
}

.stat-info {
    display: flex;
    flex-direction: column;
}

.export-options {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 30px;
    margin-bottom: 30px;
}

.export-options h4 {
    margin-top: 0;
}

.option-group {
    margin-bottom: 30px;
}

.option-group label {
    display: block;
    margin-bottom: 10px;
}

.checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding-left: 20px;
}

.checkbox-group.columns-2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
}

.checkbox-group label {
    display: flex;
    align-items: center;
    gap: 10px;
}

.export-actions {
    text-align: center;
    margin-top: 30px;
}

.button-hero {
    font-size: 18px !important;
    padding: 15px 40px !important;
    height: auto !important;
}

.export-info {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.export-info h4 {
    margin-top: 0;
    color: #1976d2;
}

.export-info ul {
    margin-bottom: 0;
}

.export-presets {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 30px;
}

.export-presets h4 {
    margin-top: 0;
}

.preset-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.preset-button {
    background: white;
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    transition: all 0.3s;
}

.preset-button:hover {
    border-color: #e74c3c;
    background: #fff3f3;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.preset-icon {
    font-size: 36px;
}

.preset-button strong {
    font-size: 14px;
    color: #333;
}

.preset-desc {
    font-size: 12px;
    color: #666;
}

@media (max-width: 768px) {
    .export-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .checkbox-group.columns-2 {
        grid-template-columns: 1fr;
    }
    
    .preset-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
