<?php
/**
 * Schedule Tab - Full time-based volunteer schedule grid
 * Rows = time slots, Columns = areas. Shows all volunteers per slot.
 */

if (!defined('ABSPATH')) { exit; }

global $wpdb;

// ─── Shift dates for date picker ──────────────────────────────────────────────
$shift_dates = $wpdb->get_col(
    "SELECT DISTINCT shift_date FROM {$wpdb->prefix}oha_volunteer_shifts ORDER BY shift_date ASC"
);

$selected_date = isset($_GET['vh_schedule_date']) ? sanitize_text_field($_GET['vh_schedule_date']) : '';
if (empty($selected_date) || !in_array($selected_date, $shift_dates, true)) {
    $selected_date = !empty($shift_dates) ? $shift_dates[0] : '';
}

// ─── Areas (columns) ─────────────────────────────────────────────────────────
$areas = \OHA\VH\Database::get_areas(true);

// ─── Build time slots + assignment matrix ─────────────────────────────────────
$time_slots = [];
$matrix     = [];

if ($selected_date && !empty($areas)) {

    // All shifts for this date
    $shifts_for_date = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}oha_volunteer_shifts
         WHERE shift_date = %s
         ORDER BY shift_start ASC, area_id ASC",
        $selected_date
    ));

    // Build the time_slots lookup (keyed by time_key)
    foreach ($shifts_for_date as $sh) {
        if ($sh->shift_type === 'all_day' || !$sh->shift_start) {
            $tk = 'all_day';
            $time_slots[$tk] = ['label' => 'All Day', 'sort' => ''];
        } else {
            $end_time = $sh->shift_end ?? '';
            $tk       = $sh->shift_start . '-' . $end_time;
            $time_slots[$tk] = [
                'label' => date('g:i A', strtotime($sh->shift_start))
                    . ($sh->shift_end ? ' - ' . date('g:i A', strtotime($sh->shift_end)) : ''),
                'sort' => $sh->shift_start
            ];
        }
    }

    // Sort: all_day first, then chronologically
    uksort($time_slots, function($a, $b) use ($time_slots) {
        if ($a === 'all_day') return -1;
        if ($b === 'all_day') return 1;
        return strcmp($time_slots[$a]['sort'], $time_slots[$b]['sort']);
    });

    // Initialize empty matrix cells
    foreach ($time_slots as $tk => $slot) {
        foreach ($areas as $area) {
            $matrix[$tk][$area->id] = [];
        }
    }

    // All assignments for shifts on this date
    $assignment_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT
            a.id,
            a.volunteer_id,
            a.area_id,
            a.shift_id,
            a.checked_in,
            a.manual_name,
            CASE
                WHEN a.volunteer_id IS NOT NULL
                    THEN CONCAT(v.first_name, ' ', v.last_name)
                ELSE COALESCE(a.manual_name, 'Unknown')
            END AS vol_name,
            v.status AS vol_status,
            s.shift_start,
            s.shift_end,
            s.shift_type
        FROM {$wpdb->prefix}oha_volunteer_assignments a
        LEFT JOIN {$wpdb->prefix}oha_volunteers v ON a.volunteer_id = v.id
        LEFT JOIN {$wpdb->prefix}oha_volunteer_shifts s ON a.shift_id = s.id
        WHERE s.shift_date = %s
        ORDER BY s.shift_start, a.area_id",
        $selected_date
    ));

    foreach ($assignment_rows as $asgn) {
        if ($asgn->shift_type === 'all_day' || !$asgn->shift_start) {
            $tk = 'all_day';
        } else {
            $end_time = $asgn->shift_end ?? '';
            $tk       = $asgn->shift_start . '-' . $end_time;
        }
        if (isset($matrix[$tk][$asgn->area_id])) {
            $matrix[$tk][$asgn->area_id][] = $asgn;
        }
    }
}

// Total assigned count for display
$total_assigned = 0;
foreach ($matrix as $row) {
    foreach ($row as $cell) {
        $total_assigned += count($cell);
    }
}
?>

<div class="oha-vh-schedule-tab">
    <div class="tab-header">
        <h3>Full Schedule</h3>
        <p>All volunteers by time slot and area. Use the date selector to switch days. Click Print to get a physical copy.</p>
    </div>

    <?php if (empty($shift_dates)): ?>
        <div class="sched-empty-state">
            <div class="sched-empty-icon">📅</div>
            <h4>No Shifts Created Yet</h4>
            <p>Go to the <a href="<?php echo esc_url(buildBoardHubUrl('shifts')); ?>">Shifts tab</a> to create shifts for each area first.</p>
        </div>
    <?php else: ?>

    <!-- Date picker + summary + print -->
    <div class="sched-toolbar">
        <div class="sched-date-picker">
            <label><strong>Date:</strong></label>
            <select onchange="location.href=buildBoardHubUrl('schedule', {vh_schedule_date: this.value})"
                    style="margin-left: 8px; padding: 8px 12px; font-size: 14px;">
                <?php foreach ($shift_dates as $d): ?>
                    <option value="<?php echo esc_attr($d); ?>" <?php selected($selected_date, $d); ?>>
                        <?php echo esc_html(date('l, F j, Y', strtotime($d))); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($selected_date): ?>
        <div class="sched-count">
            <?php echo esc_html($total_assigned); ?> assignment<?php echo $total_assigned !== 1 ? 's' : ''; ?> on this date
        </div>
        <?php endif; ?>
        <button class="button sched-print-btn" onclick="window.print()">🖨️ Print Schedule</button>
    </div>

    <?php if ($selected_date && empty($time_slots)): ?>
        <div class="sched-empty-state">
            <div class="sched-empty-icon">⏰</div>
            <h4>No Shifts on <?php echo esc_html(date('F j, Y', strtotime($selected_date))); ?></h4>
            <p><a href="<?php echo esc_url(buildBoardHubUrl('shifts')); ?>">Go to Shifts tab to add shifts →</a></p>
        </div>
    <?php elseif (!empty($time_slots)): ?>

    <!-- The schedule grid -->
    <div class="sched-grid-wrap">
        <table class="sched-grid">
            <thead>
                <tr>
                    <th class="sched-time-col">Time Slot</th>
                    <?php foreach ($areas as $area):
                        $col_name = !empty($area->alt_name) ? $area->alt_name : $area->area_name;
                        $sub_name = !empty($area->alt_name) ? $area->area_name : '';
                    ?>
                        <th class="sched-area-col" style="border-top: 4px solid <?php echo esc_attr($area->color); ?>">
                            <span class="sched-area-icon"><?php echo esc_html($area->icon); ?></span>
                            <span class="sched-area-name"><?php echo esc_html($col_name); ?></span>
                            <?php if ($sub_name): ?>
                                <span class="sched-area-subname"><?php echo esc_html($sub_name); ?></span>
                            <?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($time_slots as $tk => $slot): ?>
                <tr class="sched-row <?php echo $tk === 'all_day' ? 'sched-allday-row' : ''; ?>">
                    <td class="sched-time-cell">
                        <?php if ($tk === 'all_day'): ?>
                            <span class="sched-allday-badge">All Day</span>
                        <?php else: ?>
                            <?php echo esc_html($slot['label']); ?>
                        <?php endif; ?>
                    </td>
                    <?php foreach ($areas as $area):
                        $cell_vols = $matrix[$tk][$area->id] ?? [];
                    ?>
                        <td class="sched-cell <?php echo !empty($cell_vols) ? 'sched-has-vols' : 'sched-empty'; ?>">
                            <?php if (!empty($cell_vols)): ?>
                                <ul class="sched-vol-list">
                                    <?php foreach ($cell_vols as $v): ?>
                                        <li class="sched-vol-item <?php echo $v->checked_in ? 'sched-checked-in' : ''; ?>">
                                            <?php echo esc_html($v->vol_name); ?>
                                            <?php if ($v->checked_in): ?>
                                                <span class="sched-check" title="Checked in">&#10003;</span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <span class="sched-dash">&mdash;</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="sched-legend">
        <span>&#10003; = Checked in on the day</span>
        <span style="margin-left: 20px;">&mdash; = No volunteers assigned to this slot</span>
    </div>

    <?php endif; ?>
    <?php endif; ?>
</div>

<style>
/* ─── Schedule Tab ─────────────────────────────────────────────────── */
.sched-toolbar {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.sched-count {
    font-size: 13px;
    color: #666 !important;
}

.sched-print-btn {
    margin-left: auto;
}

.sched-empty-state {
    text-align: center;
    padding: 50px 20px;
    background: #f9f9f9;
    border: 2px dashed #ddd;
    border-radius: 8px;
}

.sched-empty-icon { font-size: 48px; margin-bottom: 10px; }

.sched-grid-wrap {
    overflow-x: auto;
    border: 1px solid #ddd;
    border-radius: 6px;
    margin-bottom: 15px;
}

.sched-grid {
    width: 100%;
    border-collapse: collapse;
    min-width: 500px;
}

.sched-grid th,
.sched-grid td {
    border: 1px solid #e0e0e0 !important;
    padding: 10px 12px;
    vertical-align: top;
    text-align: left;
    color: #333 !important;
}

.sched-grid thead th {
    background: #f5f5f5 !important;
    font-weight: 600;
    font-size: 13px;
}

.sched-time-col {
    width: 130px;
    min-width: 110px;
    white-space: nowrap;
}

.sched-area-col {
    min-width: 130px;
}

.sched-area-icon {
    display: block;
    font-size: 20px;
    margin-bottom: 3px;
    line-height: 1;
}

.sched-area-name {
    display: block;
    font-weight: 600;
    font-size: 13px;
}

.sched-area-subname {
    display: block;
    font-size: 11px;
    color: #999 !important;
    font-weight: normal;
}

.sched-time-cell {
    background: #f9f9f9 !important;
    font-weight: 600;
    font-size: 13px;
    white-space: nowrap;
    color: #444 !important;
}

.sched-allday-row .sched-time-cell {
    background: #fff8e1 !important;
}

.sched-allday-row td {
    background: #fffdf5 !important;
}

.sched-allday-badge {
    display: inline-block;
    background: #f39c12;
    color: white !important;
    padding: 2px 9px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
}

.sched-cell { font-size: 12px; }

.sched-has-vols { background: #f0fff4 !important; }

.sched-empty { background: white !important; }

.sched-dash {
    color: #ddd !important;
    font-size: 18px;
}

.sched-vol-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.sched-vol-item {
    padding: 3px 0;
    border-bottom: 1px solid #e8f5e9;
    display: flex;
    align-items: center;
    gap: 4px;
    color: #333 !important;
}

.sched-vol-item:last-child { border-bottom: none; }

.sched-checked-in { color: #2e7d32 !important; }

.sched-check {
    color: #2e7d32 !important;
    font-weight: bold;
    font-size: 11px;
}

.sched-legend {
    font-size: 12px;
    color: #888 !important;
    padding: 8px 0;
}

/* ─── Print styles ──────────────────────────────────────────────────── */
@media print {
    .oha-vh-tabs,
    .sched-print-btn,
    .sched-count,
    .sched-legend,
    .tab-header p,
    .oha-vh-board-hub > h2,
    .oha-vh-board-hub > p {
        display: none !important;
    }
    .oha-vh-board-hub { box-shadow: none !important; padding: 0 !important; }
    .sched-grid-wrap { overflow: visible !important; border: none !important; }
    .sched-grid { font-size: 9pt; }
    .sched-grid th, .sched-grid td { padding: 5px 7px !important; }
    .sched-area-subname { display: none !important; }
    .tab-header h3 { font-size: 16pt; margin-bottom: 8pt !important; }
}
</style>
