<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get all classes
$classes = get_posts(array(
    'post_type' => 'oha_class',
    'posts_per_page' => -1,
    'orderby' => 'meta_value',
    'meta_key' => '_oha_start_time',
    'order' => 'ASC'
));

// Get taxonomies for dropdowns
$days        = get_terms(array('taxonomy' => 'class_day',       'hide_empty' => false));
$instructors = get_terms(array('taxonomy' => 'instructor',      'hide_empty' => false));
$tracks      = get_terms(array('taxonomy' => 'class_track',     'hide_empty' => false));
$yr_terms    = get_terms(array('taxonomy' => 'convention_year', 'hide_empty' => false));
if (is_wp_error($yr_terms)) $yr_terms = array();
$yr_options  = wp_list_pluck($yr_terms, 'name');
$yr_now      = date('Y');
if (!in_array($yr_now, $yr_options)) $yr_options[] = $yr_now;
sort($yr_options);
?>

<div class="oha-schedule-admin-container">
    
    <div class="oha-admin-header">
        <h2>Quick Schedule Manager</h2>
        <button type="button" class="oha-btn-primary" id="oha-add-class-btn">+ Add New Class</button>
    </div>
    
    <!-- Add/Edit Form Modal -->
    <div id="oha-class-modal" class="oha-modal" style="display: none;">
        <div class="oha-modal-content">
            <span class="oha-modal-close">&times;</span>
            <h3 id="oha-modal-title">Add New Class</h3>
            
            <form id="oha-class-form">
                <input type="hidden" id="oha-class-id" value="">
                
                <div class="oha-form-row">
                    <label for="oha-class-title">Class Title *</label>
                    <input type="text" id="oha-class-title" required>
                </div>
                
                <div class="oha-form-row">
                    <label for="oha-class-short-desc">Short Description <em style="font-weight:400;opacity:.7;">(shown on schedule cards)</em></label>
                    <textarea id="oha-class-short-desc" rows="2" placeholder="1–2 sentences shown on the main schedule card."></textarea>
                </div>

                <div class="oha-form-row">
                    <label for="oha-class-description">Full Description <em style="font-weight:400;opacity:.7;">(shown in detail modal)</em></label>
                    <textarea id="oha-class-description" rows="4" placeholder="Full details shown when the user taps 'more details'."></textarea>
                </div>
                
                <div class="oha-form-row oha-form-row-split">
                    <div>
                        <label for="oha-class-day">Day *</label>
                        <select id="oha-class-day" required>
                            <option value="">Select Day</option>
                            <?php foreach ($days as $day): ?>
                                <option value="<?php echo esc_attr($day->name); ?>"><?php echo esc_html($day->name); ?></option>
                            <?php endforeach; ?>
                            <option value="_new">+ Add New Day</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="oha-class-instructor">Instructor *</label>
                        <select id="oha-class-instructor" required>
                            <option value="">Select Instructor</option>
                            <?php foreach ($instructors as $instructor): ?>
                                <option value="<?php echo esc_attr($instructor->name); ?>"><?php echo esc_html($instructor->name); ?></option>
                            <?php endforeach; ?>
                            <option value="_new">+ Add New Instructor</option>
                        </select>
                    </div>
                </div>
                
                <div class="oha-form-row oha-form-row-split">
                    <div>
                        <label for="oha-class-start-time">Start Time *</label>
                        <input type="time" id="oha-class-start-time" required>
                    </div>
                    
                    <div>
                        <label for="oha-class-end-time">End Time *</label>
                        <input type="time" id="oha-class-end-time" required>
                    </div>
                </div>
                
                <div class="oha-form-row oha-form-row-split">
                    <div>
                        <label for="oha-class-room">Room/Location</label>
                        <input type="text" id="oha-class-room">
                    </div>
                    
                    <div>
                        <label for="oha-class-track">Track</label>
                        <select id="oha-class-track">
                            <option value="">Select Track</option>
                            <?php foreach ($tracks as $track): ?>
                                <option value="<?php echo esc_attr($track->name); ?>"><?php echo esc_html($track->name); ?></option>
                            <?php endforeach; ?>
                            <option value="_new">+ Add New Track</option>
                        </select>
                    </div>
                </div>
                
                <div class="oha-form-row oha-form-row-split">
                    <div>
                        <label for="oha-class-level">Level</label>
                        <select id="oha-class-level">
                            <option value="">Select Level</option>
                            <option value="beginner">Beginner</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                            <option value="all">All Levels</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="oha-class-capacity">Capacity</label>
                        <input type="number" id="oha-class-capacity" min="0">
                    </div>
                </div>
                
                <div class="oha-form-row">
                    <label for="oha-class-year">Convention Year</label>
                    <select id="oha-class-year">
                        <option value="">-- Select Year --</option>
                        <?php foreach ($yr_options as $yr): ?>
                            <option value="<?php echo esc_attr($yr); ?>"><?php echo esc_html($yr); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="oha-form-row" style="border-top:1px solid #2e1a1a;padding-top:12px;margin-top:4px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" id="oha-class-show-end-time" value="1" style="width:16px;height:16px;cursor:pointer;">
                        Show end time as separate Quick List entry
                    </label>
                    <small style="display:block;margin-top:4px;color:#9e7a68;padding-left:24px;">Adds a closing line (e.g. "5:00 PM Show Floor Closed") to the Quick List view.</small>
                </div>

                <div class="oha-form-row" id="oha-end-time-label-row">
                    <label for="oha-class-end-time-label">End Time Label</label>
                    <input type="text" id="oha-class-end-time-label" placeholder="e.g. Show Floor Closed">
                </div>

                <div class="oha-form-actions">
                    <button type="submit" class="oha-btn-primary">Save Class</button>
                    <button type="button" class="oha-btn-secondary oha-modal-close">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Classes List -->
    <div class="oha-admin-classes-list">
        <?php if (empty($classes)): ?>
            <p>No classes scheduled yet. Click "Add New Class" to get started.</p>
        <?php else: ?>
            <table class="oha-admin-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Class</th>
                        <th>Instructor</th>
                        <th>Day</th>
                        <th>Room</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classes as $class): 
                        $start_time = get_post_meta($class->ID, '_oha_start_time', true);
                        $room = get_post_meta($class->ID, '_oha_room', true);
                        $class_days = wp_get_post_terms($class->ID, 'class_day', array('fields' => 'names'));
                        $class_instructors = wp_get_post_terms($class->ID, 'instructor', array('fields' => 'names'));
                    ?>
                        <tr>
                            <td><?php echo esc_html(OHA_Schedule_Hub::format_time($start_time)); ?></td>
                            <td><strong><?php echo esc_html($class->post_title); ?></strong></td>
                            <td><?php echo !empty($class_instructors) ? esc_html($class_instructors[0]) : '—'; ?></td>
                            <td><?php echo !empty($class_days) ? esc_html($class_days[0]) : '—'; ?></td>
                            <td><?php echo $room ? esc_html($room) : '—'; ?></td>
                            <td>
                                <button type="button" class="oha-btn-edit" data-class-id="<?php echo esc_attr($class->ID); ?>">Edit</button>
                                <button type="button" class="oha-btn-delete" data-class-id="<?php echo esc_attr($class->ID); ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
