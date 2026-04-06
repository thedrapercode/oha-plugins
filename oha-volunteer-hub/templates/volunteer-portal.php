<?php
/**
 * Volunteer Portal Template
 * Shows volunteer's assignments, managers, shifts, and messages
 */

if (!defined('ABSPATH')) exit;

// Variables available: $volunteer, $assignments, $messages
?>

<div class="oha-vh-portal">
    <style>
        .oha-vh-portal { max-width: 1200px; margin: 0 auto; }
        .oha-portal-header { text-align: center; margin-bottom: 30px; padding: 40px; background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .oha-portal-header h2 { margin: 0 0 10px 0; color: white; font-size: 32px; }
        .oha-portal-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .oha-stat-card { background: white; padding: 25px; border-radius: 8px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: transform 0.2s; }
        .oha-stat-card:hover { transform: translateY(-4px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .oha-stat-number { font-size: 48px; font-weight: 700; color: #e74c3c; margin-bottom: 8px; }
        .oha-stat-label { color: #666; text-transform: uppercase; font-size: 12px; letter-spacing: 1px; font-weight: 600; }
        .oha-assignment-card { background: white; padding: 30px; margin-bottom: 20px; border-radius: 8px; border-left: 5px solid #e74c3c; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .oha-assignment-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .oha-assignment-title { display: flex; align-items: center; gap: 15px; }
        .oha-assignment-icon { font-size: 48px; }
        .oha-assignment-header h3 { margin: 0; color: #000; font-size: 24px; }
        .oha-assignment-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px; }
        .oha-detail-item { padding: 15px; background: #f9f9f9; border-radius: 6px; border-left: 3px solid #e74c3c; }
        .oha-detail-label { font-size: 12px; color: #666; text-transform: uppercase; margin-bottom: 8px; font-weight: 600; }
        .oha-detail-value { font-size: 16px; color: #000; font-weight: 600; }
        .oha-managers-list { margin-top: 20px; padding: 20px; background: #f0f8ff; border-radius: 6px; border-left: 3px solid #2271b1; }
        .oha-manager-item { display: flex; align-items: center; gap: 15px; padding: 12px 0; border-bottom: 1px solid #e0e0e0; }
        .oha-manager-item:last-child { border-bottom: none; }
        .oha-manager-avatar { width: 48px; height: 48px; border-radius: 50%; background: #e74c3c; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 20px; flex-shrink: 0; }
        .oha-no-assignments { text-align: center; padding: 80px 20px; background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .oha-check-in-btn { background: #28a745; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; transition: background 0.2s; }
        .oha-check-in-btn:hover { background: #218838; }
        .oha-checked-in { background: #d4edda; color: #155724; padding: 10px 20px; border-radius: 6px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
        .oha-message-card { background: white; padding: 25px; margin-bottom: 15px; border-radius: 8px; border-left: 5px solid #2271b1; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        @media (max-width: 768px) {
            .oha-assignment-details { grid-template-columns: 1fr; }
            .oha-portal-stats { grid-template-columns: 1fr; }
            .oha-assignment-header { flex-direction: column; align-items: flex-start; }
        }
    </style>

    <div class="oha-portal-header">
        <h2>🎉 Welcome, <?php echo esc_html($volunteer->first_name); ?>!</h2>
        <p style="font-size: 18px; margin: 0;">Your OHA Convention 2026 Volunteer Dashboard</p>
    </div>

    <?php if (empty($assignments)): ?>
        <div class="oha-no-assignments">
            <div style="font-size: 96px; margin-bottom: 20px;">📋</div>
            <h3 style="font-size: 28px; margin-bottom: 15px;">No Assignments Yet</h3>
            <p style="font-size: 16px; color: #666; max-width: 500px; margin: 0 auto;">
                You haven't been assigned to any areas yet. Our team will review your preferences and assign you soon!
            </p>
            <?php 
            $preferred = json_decode($volunteer->preferred_areas, true) ?: [];
            if (!empty($preferred)):
                $area_names = [];
                foreach ($preferred as $area_id) {
                    $area = \OHA\VH\Database::get_area($area_id);
                    if ($area) $area_names[] = $area->icon . ' ' . $area->area_name;
                }
            ?>
            <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 8px; display: inline-block;">
                <strong style="display: block; margin-bottom: 10px;">Your Preferred Areas:</strong>
                <div style="color: #666;"><?php echo esc_html(implode(', ', $area_names)); ?></div>
            </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="oha-portal-stats">
            <div class="oha-stat-card">
                <div class="oha-stat-number"><?php echo count($assignments); ?></div>
                <div class="oha-stat-label">Assignments</div>
            </div>
            <div class="oha-stat-card">
                <div class="oha-stat-number"><?php echo count(array_filter($assignments, fn($a) => $a->checked_in)); ?></div>
                <div class="oha-stat-label">Checked In</div>
            </div>
            <div class="oha-stat-card">
                <div class="oha-stat-number"><?php echo number_format(array_sum(array_column($assignments, 'hours_worked')), 1); ?></div>
                <div class="oha-stat-label">Hours Worked</div>
            </div>
        </div>

        <h2 style="margin-bottom: 20px; font-size: 28px;">📍 Your Assignments</h2>

        <?php foreach ($assignments as $assignment): 
            $managers = json_decode($assignment->area_managers, true) ?: [];
            $shift_display = '';
            
            if ($assignment->shift_type === 'all_day') {
                $shift_display = 'All Day';
            } else if ($assignment->shift_start) {
                $shift_display = date('g:i A', strtotime($assignment->shift_start)) . ' - ' . 
                               date('g:i A', strtotime($assignment->shift_end));
            } else {
                $shift_display = 'TBD';
            }
        ?>
        <div class="oha-assignment-card" style="border-left-color: <?php echo esc_attr($assignment->color); ?>">
            <div class="oha-assignment-header">
                <div class="oha-assignment-title">
                    <span class="oha-assignment-icon"><?php echo esc_html($assignment->icon); ?></span>
                    <h3><?php echo esc_html($assignment->area_name); ?></h3>
                </div>
                <div>
                    <?php if ($assignment->checked_in): ?>
                        <span class="oha-checked-in">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            Checked In
                        </span>
                    <?php else: ?>
                        <button class="oha-check-in-btn" onclick="checkIn(<?php echo esc_js($assignment->id); ?>)">
                            Check In Now
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="oha-assignment-details">
                <div class="oha-detail-item">
                    <div class="oha-detail-label">📅 Date</div>
                    <div class="oha-detail-value">
                        <?php echo $assignment->shift_date ? date('l, F j, Y', strtotime($assignment->shift_date)) : 'To Be Determined'; ?>
                    </div>
                </div>
                <div class="oha-detail-item">
                    <div class="oha-detail-label">🕐 Shift Time</div>
                    <div class="oha-detail-value"><?php echo esc_html($shift_display); ?></div>
                </div>
                <?php if ($assignment->hours_worked > 0): ?>
                <div class="oha-detail-item">
                    <div class="oha-detail-label">⏱️ Hours Worked</div>
                    <div class="oha-detail-value"><?php echo esc_html(number_format($assignment->hours_worked, 1)); ?> hours</div>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($managers)): ?>
            <div class="oha-managers-list">
                <strong style="display: block; margin-bottom: 15px; font-size: 16px;">👥 Your Area Managers:</strong>
                <?php foreach ($managers as $manager_id): 
                    $manager = get_user_by('id', $manager_id);
                    if ($manager):
                        $initials = strtoupper(substr($manager->display_name, 0, 1));
                ?>
                <div class="oha-manager-item">
                    <div class="oha-manager-avatar"><?php echo esc_html($initials); ?></div>
                    <div style="flex: 1;">
                        <strong style="display: block; font-size: 16px;"><?php echo esc_html($manager->display_name); ?></strong>
                        <small style="color: #666;">
                            <a href="mailto:<?php echo esc_attr($manager->user_email); ?>" style="color: #2271b1; text-decoration: none;">
                                <?php echo esc_html($manager->user_email); ?>
                            </a>
                        </small>
                    </div>
                    <a href="mailto:<?php echo esc_attr($manager->user_email); ?>" class="button button-small">
                        ✉️ Email
                    </a>
                </div>
                <?php endif; endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($assignment->notes): ?>
            <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 3px solid #ffc107; border-radius: 6px;">
                <strong style="display: block; margin-bottom: 8px;">📝 Special Instructions:</strong>
                <div style="white-space: pre-wrap;"><?php echo esc_html($assignment->notes); ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($messages)): ?>
        <h2 style="margin-top: 50px; margin-bottom: 20px; font-size: 28px;">💬 Messages from Your Team</h2>
        <?php foreach ($messages as $msg): 
            $from_user = get_user_by('id', $msg->from_user_id);
        ?>
        <div class="oha-message-card">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                <div>
                    <strong style="font-size: 16px;">From: <?php echo esc_html($from_user ? $from_user->display_name : 'OHA Team'); ?></strong><br>
                    <small style="color: #666;"><?php echo date('F j, Y g:i A', strtotime($msg->sent_date)); ?></small>
                </div>
                <?php if (!$msg->is_read): ?>
                    <span style="background: #e74c3c; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">NEW</span>
                <?php endif; ?>
            </div>
            <h4 style="margin: 15px 0 10px 0; font-size: 18px;"><?php echo esc_html($msg->subject); ?></h4>
            <p style="margin: 0; white-space: pre-wrap; line-height: 1.6;"><?php echo esc_html($msg->message); ?></p>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function checkIn(assignmentId) {
    if (!confirm('Check in to this assignment? This will record your arrival time.')) return;
    
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = 'Checking in...';
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'oha_vh_check_in',
            assignment_id: assignmentId,
            nonce: '<?php echo wp_create_nonce('oha_vh_checkin'); ?>'
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ Checked in successfully! Thank you for being here!');
            location.reload();
        } else {
            alert(data.data.message || 'Error checking in. Please try again or contact your area manager.');
            btn.disabled = false;
            btn.textContent = 'Check In Now';
        }
    })
    .catch(err => {
        alert('Error checking in. Please try again.');
        btn.disabled = false;
        btn.textContent = 'Check In Now';
    });
}
</script>
