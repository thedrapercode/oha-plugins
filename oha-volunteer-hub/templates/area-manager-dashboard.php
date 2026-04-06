<?php
/**
 * Area Manager Dashboard Template
 * For board members to manage their areas and volunteers
 */

if (!defined('ABSPATH')) exit;

// Variables available: $managed_areas
?>

<div class="oha-manager-dashboard">
    <style>
        .oha-manager-dashboard { max-width: 1400px; margin: 0 auto; }
        .oha-manager-header { text-align: center; margin-bottom: 30px; padding: 40px; background: linear-gradient(135deg, #2271b1 0%, #135e96 100%); color: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .oha-manager-header h2 { margin: 0 0 10px 0; color: white; font-size: 32px; }
        .oha-manager-areas { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 25px; }
        .oha-manager-area-card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: transform 0.2s; }
        .oha-manager-area-card:hover { transform: translateY(-4px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .oha-area-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin: 20px 0; padding: 20px; background: #f9f9f9; border-radius: 6px; }
        .oha-area-stat { text-align: center; }
        .oha-area-stat-number { font-size: 32px; font-weight: 700; margin-bottom: 5px; }
        .oha-area-stat-label { font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        .oha-volunteers-list { margin-top: 20px; }
        .oha-volunteer-row { display: flex; justify-content: space-between; align-items: center; padding: 15px; margin-bottom: 10px; background: #f9f9f9; border-radius: 6px; border-left: 3px solid #e74c3c; transition: background 0.2s; }
        .oha-volunteer-row:hover { background: #e9ecef; }
        .oha-volunteer-info strong { display: block; font-size: 15px; color: #000; margin-bottom: 4px; }
        .oha-volunteer-info small { display: block; color: #666; }
        .oha-quick-actions { display: flex; gap: 8px; flex-shrink: 0; }
        .oha-area-actions { margin-top: 25px; padding-top: 20px; border-top: 2px solid #e0e0e0; display: flex; gap: 10px; flex-wrap: wrap; }
        @media (max-width: 768px) {
            .oha-manager-areas { grid-template-columns: 1fr; }
            .oha-area-stats { grid-template-columns: 1fr; }
            .oha-volunteer-row { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
    </style>

    <div class="oha-manager-header">
        <h2 style="color: #ffffff !important;">👥 Area Manager Dashboard</h2>
        <p style="font-size: 18px; margin: 0;">Manage your convention areas and volunteers</p>
    </div>

    <?php if (empty($managed_areas)): ?>
        <div style="text-align: center; padding: 80px 20px; background: white; border-radius: 8px;">
            <div style="font-size: 96px; margin-bottom: 20px;">📋</div>
            <h3 style="font-size: 28px; margin-bottom: 15px;">No Areas Assigned</h3>
            <p style="font-size: 16px; color: #666;">You haven't been assigned as a manager to any areas yet.</p>
        </div>
    <?php else: ?>
        <div class="oha-manager-areas">
            <?php foreach ($managed_areas as $area): 
                $assignments = \OHA\VH\Database::get_assignments_by_area($area->id);
                $checked_in_count = count(array_filter($assignments, fn($a) => $a->checked_in));
                $total_hours = array_sum(array_column($assignments, 'hours_worked'));
            ?>
            <div class="oha-manager-area-card" style="border-left: 5px solid <?php echo esc_attr($area->color); ?>">
                <h3 style="margin: 0 0 10px 0; font-size: 24px; display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 36px;"><?php echo esc_html($area->icon); ?></span>
                    <?php echo esc_html($area->area_name); ?>
                </h3>
                <p style="color: #666; margin: 0 0 20px 0;"><?php echo esc_html($area->description); ?></p>
                
                <div class="oha-area-stats">
                    <div class="oha-area-stat">
                        <div class="oha-area-stat-number" style="color: #e74c3c;"><?php echo count($assignments); ?></div>
                        <div class="oha-area-stat-label">Assigned</div>
                    </div>
                    <div class="oha-area-stat">
                        <div class="oha-area-stat-number" style="color: #28a745;"><?php echo $checked_in_count; ?></div>
                        <div class="oha-area-stat-label">Checked In</div>
                    </div>
                    <div class="oha-area-stat">
                        <div class="oha-area-stat-number" style="color: #2271b1;"><?php echo $area->max_volunteers; ?></div>
                        <div class="oha-area-stat-label">Capacity</div>
                    </div>
                </div>

                <?php if (!empty($assignments)): ?>
                <div class="oha-volunteers-list">
                    <strong style="display: block; margin-bottom: 12px; font-size: 16px;">Your Volunteers:</strong>
                    <?php 
                    $display_count = min(5, count($assignments));
                    $display_assignments = array_slice($assignments, 0, $display_count);
                    foreach ($display_assignments as $a): 
                        $v_name = $a->first_name ? $a->first_name . ' ' . $a->last_name : $a->manual_name;
                        $v_email = $a->email ?: $a->manual_email;
                        $v_phone = $a->phone ?: $a->manual_phone;
                        
                        $shift_display = '';
                        if ($a->shift_type === 'all_day') {
                            $shift_display = 'All Day';
                        } else if ($a->shift_start) {
                            $shift_display = date('g:i A', strtotime($a->shift_start)) . ' - ' . date('g:i A', strtotime($a->shift_end));
                        }
                    ?>
                    <div class="oha-volunteer-row">
                        <div class="oha-volunteer-info">
                            <strong>
                                <?php if ($a->checked_in): ?>
                                    <span style="color: #28a745;">✅</span>
                                <?php endif; ?>
                                <?php echo esc_html($v_name); ?>
                            </strong>
                            <small>
                                📧 <?php echo esc_html($v_email); ?>
                                <?php if ($v_phone): ?>
                                    | 📱 <?php echo esc_html($v_phone); ?>
                                <?php endif; ?>
                            </small>
                            <?php if ($shift_display): ?>
                                <small>🕐 <?php echo esc_html($shift_display); ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="oha-quick-actions">
                            <a href="mailto:<?php echo esc_attr($v_email); ?>" class="button button-small" title="Send Email">
                                ✉️
                            </a>
                            <?php if ($v_phone): ?>
                                <a href="tel:<?php echo esc_attr($v_phone); ?>" class="button button-small" title="Call">
                                    📞
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (count($assignments) > $display_count): ?>
                        <div style="text-align: center; margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0;">
                            <small style="color: #666; font-weight: 600;">
                                + <?php echo count($assignments) - $display_count; ?> more volunteer(s)
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 30px; background: #f9f9f9; border-radius: 6px; margin-top: 20px;">
                        <p style="color: #999; margin: 0;">No volunteers assigned yet</p>
                    </div>
                <?php endif; ?>

                <div class="oha-area-actions">
                    <a href="<?php echo home_url('/board-hub/?vh_tab=assign&tab=ct__volunteers'); ?>" class="button button-primary">
                        ➕ Assign Volunteers
                    </a>
                    <button class="button" onclick="messageAreaVolunteers(<?php echo esc_js($area->id); ?>, '<?php echo esc_js($area->area_name); ?>')">
                        📧 Message All
                    </button>
                    <a href="<?php echo home_url('/board-hub/?vh_tab=shifts&tab=ct__volunteers'); ?>" class="button">
                        🕐 Manage Shifts
                    </a>
                    <a href="<?php echo home_url('/board-hub/?vh_tab=areas&tab=ct__volunteers'); ?>" class="button">
                        ⚙️ Manage Area
                    </a>
                </div>

                <?php if ($total_hours > 0): ?>
                <div style="margin-top: 20px; padding: 15px; background: #e7f3ff; border-radius: 6px; text-align: center;">
                    <strong>Total Volunteer Hours:</strong> 
                    <span style="font-size: 20px; color: #2271b1; font-weight: 700;">
                        <?php echo number_format($total_hours, 1); ?> hours
                    </span>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Message Modal -->
<div id="message-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 999999; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 12px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <h2 style="margin-top: 0; color: #000 !important;">📧 Send Message</h2>
        
        <form id="message-form">
            <input type="hidden" id="message-area-id" name="area_id">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #000 !important;">
                    To: <span id="message-area-name" style="color: #e74c3c;"></span>
                </label>
                <small style="color: #666 !important; display: block;">
                    This message will be sent to <strong id="message-volunteer-count" style="color: #000 !important;">0</strong> volunteer(s) in this area
                </small>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label for="message-subject" style="display: block; margin-bottom: 8px; font-weight: 600; color: #000 !important;">
                    Subject: <span style="color: red;">*</span>
                </label>
                <input type="text" 
                       id="message-subject" 
                       name="subject" 
                       required
                       placeholder="e.g., Important Update About Your Assignment"
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; color: #000 !important;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label for="message-content" style="display: block; margin-bottom: 8px; font-weight: 600; color: #000 !important;">
                    Message: <span style="color: red;">*</span>
                </label>
                <textarea id="message-content" 
                          name="message" 
                          required
                          rows="8"
                          placeholder="Write your message here...

You can use {{name}} to insert the volunteer's name and {{area}} for the area name."
                          style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; font-family: inherit; resize: vertical; color: #000 !important;"></textarea>
                <small style="color: #666 !important; display: block; margin-top: 5px;">
                    💡 Tip: Use {{name}} and {{area}} for personalization
                </small>
            </div>
            
            <div style="background: #fff3cd; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
                <strong style="color: #856404 !important; display: block; margin-bottom: 5px;">📬 Email Preview</strong>
                <small style="color: #856404 !important;">
                    Emails will be sent to each volunteer individually from your site email address. 
                    Volunteers can reply directly to their area managers.
                </small>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeMessageModal()" class="button">
                    Cancel
                </button>
                <button type="submit" class="button button-primary" style="background: #e74c3c !important; border-color: #e74c3c !important;">
                    📧 Send Message
                </button>
            </div>
        </form>
        
        <div id="message-sending" style="display: none; text-align: center; padding: 40px 20px;">
            <div style="font-size: 48px; margin-bottom: 15px;">📧</div>
            <h3 style="color: #000 !important; margin-bottom: 10px;">Sending Messages...</h3>
            <p style="color: #666 !important;">Please wait while we send emails to your volunteers.</p>
            <div style="margin-top: 20px;">
                <div style="width: 100%; height: 4px; background: #f0f0f0; border-radius: 2px; overflow: hidden;">
                    <div id="message-progress" style="width: 0%; height: 100%; background: #e74c3c; transition: width 0.3s;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var currentAreaVolunteerCount = 0;

function messageAreaVolunteers(areaId, areaName) {
    // Get volunteer count for this area
    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'oha_vh_get_area_assignments_count',
            area_id: areaId,
            nonce: '<?php echo wp_create_nonce('oha_vh_board'); ?>'
        })
    })
    .then(r => r.json())
    .then(data => {
        currentAreaVolunteerCount = data.data?.count || 0;
        
        if (currentAreaVolunteerCount === 0) {
            alert('No volunteers are assigned to this area yet.');
            return;
        }
        
        document.getElementById('message-area-id').value = areaId;
        document.getElementById('message-area-name').textContent = areaName;
        document.getElementById('message-volunteer-count').textContent = currentAreaVolunteerCount;
        document.getElementById('message-subject').value = '';
        document.getElementById('message-content').value = '';
        
        const modal = document.getElementById('message-modal');
        modal.style.display = 'flex';
        document.getElementById('message-subject').focus();
    });
}

function closeMessageModal() {
    document.getElementById('message-modal').style.display = 'none';
    document.getElementById('message-form').reset();
}

// Close modal on background click
document.getElementById('message-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeMessageModal();
    }
});

// Handle form submission
document.getElementById('message-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'oha_vh_send_area_message');
    formData.append('nonce', '<?php echo wp_create_nonce('oha_vh_board'); ?>');
    
    // Show sending state
    document.querySelector('#message-modal form').style.display = 'none';
    document.getElementById('message-sending').style.display = 'block';
    
    // Simulate progress
    let progress = 0;
    const progressInterval = setInterval(() => {
        progress += 10;
        if (progress <= 90) {
            document.getElementById('message-progress').style.width = progress + '%';
        }
    }, 200);
    
    fetch(ajaxurl, {
        method: 'POST',
        body: new URLSearchParams(formData)
    })
    .then(r => r.json())
    .then(data => {
        clearInterval(progressInterval);
        document.getElementById('message-progress').style.width = '100%';
        
        setTimeout(() => {
            document.getElementById('message-sending').style.display = 'none';
            document.querySelector('#message-modal form').style.display = 'block';
            
            if (data.success) {
                alert('✅ Messages sent successfully!\n\n' + 
                      'Sent: ' + data.data.sent + '\n' +
                      'Failed: ' + data.data.failed + '\n' +
                      'Total: ' + data.data.total);
                closeMessageModal();
            } else {
                alert('❌ Error: ' + (data.data?.message || 'Failed to send messages'));
            }
        }, 500);
    })
    .catch(error => {
        clearInterval(progressInterval);
        document.getElementById('message-sending').style.display = 'none';
        document.querySelector('#message-modal form').style.display = 'block';
        
        console.error('Message error:', error);
        alert('❌ Error sending messages. Please try again.');
    });
});
}
</script>
