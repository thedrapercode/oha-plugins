<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$time_format = get_option('oha_schedule_time_format', '12h');
$default_duration = get_option('oha_schedule_default_duration', 75);
$setup_time = get_option('oha_schedule_setup_time', 15);
$teardown_time = get_option('oha_schedule_teardown_time', 15);
$enable_search = get_option('oha_schedule_enable_search', 1);
$enable_filters = get_option('oha_schedule_enable_filters', 1);
$default_view = get_option('oha_schedule_default_view', 'timeline');
$show_descriptions = get_option('oha_schedule_show_descriptions', 1);
$enable_print = get_option('oha_schedule_enable_print', 1);
$day_order = get_option('oha_schedule_day_order', '');
?>

<div class="wrap">
    <h1>OHA Schedule Settings</h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('oha_schedule_settings', 'oha_schedule_settings_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label for="time_format">Time Format</label></th>
                <td>
                    <select name="oha_schedule_time_format" id="time_format">
                        <option value="12h" <?php selected($time_format, '12h'); ?>>12 Hour (1:00 PM)</option>
                        <option value="24h" <?php selected($time_format, '24h'); ?>>24 Hour (13:00)</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="default_duration">Default Class Duration (minutes)</label></th>
                <td>
                    <input type="number" name="oha_schedule_default_duration" id="default_duration" 
                           value="<?php echo esc_attr($default_duration); ?>" class="small-text">
                    <p class="description">Default length for classes (75 minutes = 1 hour 15 min)</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="setup_time">Setup Time (minutes)</label></th>
                <td>
                    <input type="number" name="oha_schedule_setup_time" id="setup_time" 
                           value="<?php echo esc_attr($setup_time); ?>" class="small-text">
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="teardown_time">Teardown Time (minutes)</label></th>
                <td>
                    <input type="number" name="oha_schedule_teardown_time" id="teardown_time" 
                           value="<?php echo esc_attr($teardown_time); ?>" class="small-text">
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="default_view">Default View</label></th>
                <td>
                    <select name="oha_schedule_default_view" id="default_view">
                        <option value="timeline" <?php selected($default_view, 'timeline'); ?>>Timeline</option>
                        <option value="grid" <?php selected($default_view, 'grid'); ?>>Grid</option>
                        <option value="list" <?php selected($default_view, 'list'); ?>>List</option>
                        <option value="compact" <?php selected($default_view, 'compact'); ?>>Compact</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">Display Options</th>
                <td>
                    <label>
                        <input type="checkbox" name="oha_schedule_enable_search" value="1" 
                               <?php checked($enable_search, 1); ?>>
                        Enable Search
                    </label><br>
                    
                    <label>
                        <input type="checkbox" name="oha_schedule_enable_filters" value="1" 
                               <?php checked($enable_filters, 1); ?>>
                        Enable Filters
                    </label><br>
                    
                    <label>
                        <input type="checkbox" name="oha_schedule_show_descriptions" value="1" 
                               <?php checked($show_descriptions, 1); ?>>
                        Show Class Descriptions
                    </label><br>
                    
                    <label>
                        <input type="checkbox" name="oha_schedule_enable_print" value="1" 
                               <?php checked($enable_print, 1); ?>>
                        Enable Print View
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="oha_schedule_day_order">Day Order</label></th>
                <td>
                    <input type="text" name="oha_schedule_day_order" id="oha_schedule_day_order"
                           value="<?php echo esc_attr($day_order); ?>" style="width:400px;">
                    <p class="description">Comma-separated day names in the order you want them displayed. Example: <code>Thursday, Friday, Saturday</code>. Days not listed will appear after the ordered ones.</p>
                </td>
            </tr>
        </table>

        <h2>Shortcode Examples</h2>
        <p>Use these shortcodes to display the schedule on any page:</p>
        <ul style="list-style: disc; margin-left: 30px;">
            <li><code>[oha_schedule]</code> - Display full schedule with default settings</li>
            <li><code>[oha_schedule view="timeline"]</code> - Timeline view</li>
            <li><code>[oha_schedule view="grid"]</code> - Grid view</li>
            <li><code>[oha_schedule view="list"]</code> - List view</li>
            <li><code>[oha_schedule day="Saturday"]</code> - Filter by day</li>
            <li><code>[oha_schedule instructor="John Doe"]</code> - Filter by instructor</li>
            <li><code>[oha_schedule_admin]</code> - Quick admin interface (requires edit_posts capability)</li>
        </ul>
        
        <h2>Track Color Coding</h2>
        <p>Configure track colors in the Tracks taxonomy under Classes menu.</p>
        
        <?php submit_button('Save Settings'); ?>
    </form>
</div>

<style>
.form-table th {
    padding: 20px 10px 20px 0;
}
.small-text {
    width: 100px;
}
code {
    background: #f0f0f0;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
}
</style>

<?php
// --- Year Management section (outside the <form> so buttons don't interfere with settings save) ---
$yr_terms   = get_terms(array('taxonomy' => 'convention_year', 'hide_empty' => false));
if (is_wp_error($yr_terms)) $yr_terms = array();
$untagged_ids = get_posts(array(
    'post_type'      => 'oha_class',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'tax_query'      => array(array(
        'taxonomy' => 'convention_year',
        'operator' => 'NOT EXISTS',
    )),
));
$untagged_count = count($untagged_ids);
?>
<hr style="margin:30px 0;">
<h2>Year Management</h2>
<p>Manage classes by convention year. Before entering next year's classes, use <strong>Export JSON</strong> to save a full backup, then <strong>Delete All</strong> to clear them from the active schedule.</p>
<p style="font-size:13px;color:#666;">To show only one year on the public schedule, use <code>[oha_schedule year="2026"]</code> on the schedule page.</p>

<?php if (!empty($yr_terms)): ?>
<table class="widefat striped" style="max-width:560px;margin-bottom:20px;">
    <thead>
        <tr>
            <th>Year</th>
            <th>Classes</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($yr_terms as $yt): ?>
        <tr>
            <td><strong><?php echo esc_html($yt->name); ?></strong></td>
            <td><?php echo intval($yt->count); ?></td>
            <td style="display:flex;gap:8px;flex-wrap:wrap;padding:6px 0;">
                <button type="button" class="button oha-ym-export"
                        data-year="<?php echo esc_attr($yt->name); ?>">
                    Export JSON
                </button>
                <button type="button" class="button oha-ym-delete"
                        style="color:#b32d2e;border-color:#b32d2e;"
                        data-year="<?php echo esc_attr($yt->name); ?>"
                        data-count="<?php echo intval($yt->count); ?>">
                    Delete All
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p style="color:#666;background:#f9f9f9;padding:10px 14px;border-left:4px solid #ccc;max-width:560px;">No tagged years yet. Use the tool below to tag your existing classes with a year.</p>
<?php endif; ?>

<?php if ($untagged_count > 0): ?>
<div style="max-width:560px;padding:14px 16px;background:#fff8e1;border-left:4px solid #f0b429;border-radius:4px;margin-bottom:20px;">
    <strong><?php echo $untagged_count; ?> untagged class<?php echo $untagged_count !== 1 ? 'es have' : ' has'; ?> no convention year set.</strong>
    <div style="margin-top:10px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <label for="oha-ym-tag-input" style="font-size:13px;">Tag all as:</label>
        <input type="text" id="oha-ym-tag-input" value="<?php echo esc_attr(date('Y')); ?>" style="width:70px;">
        <button type="button" class="button button-primary" id="oha-ym-tag-btn">Apply</button>
        <span id="oha-ym-tag-result" style="display:none;color:#1a7f1a;font-weight:600;"></span>
    </div>
</div>
<?php endif; ?>

<script>
jQuery(document).ready(function($) {

    // Export year as JSON
    $(document).on('click', '.oha-ym-export', function() {
        var year = $(this).data('year');
        var $btn = $(this);
        $btn.prop('disabled', true).text('Exporting...');
        $.ajax({
            url: ohaSchedule.ajaxurl,
            type: 'POST',
            data: { action: 'oha_export_year_json', nonce: ohaSchedule.nonce, year: year },
            success: function(response) {
                if (response.success) {
                    var blob = new Blob([JSON.stringify(response.data, null, 2)], { type: 'application/json' });
                    var link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = 'oha-schedule-' + year + '.json';
                    link.click();
                } else {
                    alert('Export failed: ' + (response.data || 'unknown error'));
                }
                $btn.prop('disabled', false).text('Export JSON');
            }
        });
    });

    // Delete all classes for a year
    $(document).on('click', '.oha-ym-delete', function() {
        var year  = $(this).data('year');
        var count = $(this).data('count');
        if (!confirm('Move all ' + count + ' classes tagged "' + year + '" to trash?\n\nMake sure you have exported a JSON backup first.')) {
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true).text('Deleting...');
        $.ajax({
            url: ohaSchedule.ajaxurl,
            type: 'POST',
            data: { action: 'oha_delete_year_classes', nonce: ohaSchedule.nonce, year: year },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Delete failed: ' + (response.data || 'unknown error'));
                    $btn.prop('disabled', false).text('Delete All');
                }
            }
        });
    });

    // Tag untagged classes
    $('#oha-ym-tag-btn').on('click', function() {
        var year = $('#oha-ym-tag-input').val().trim();
        if (!year) { alert('Please enter a year.'); return; }
        if (!confirm('Tag all untagged classes as "' + year + '"?')) return;
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.ajax({
            url: ohaSchedule.ajaxurl,
            type: 'POST',
            data: { action: 'oha_tag_untagged_classes', nonce: ohaSchedule.nonce, year: year },
            success: function(response) {
                if (response.success) {
                    $('#oha-ym-tag-result').text(response.data.message).show();
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    alert('Failed: ' + (response.data || 'unknown error'));
                    $btn.prop('disabled', false);
                }
            }
        });
    });

});
</script>
