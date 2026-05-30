<?php
/**
 * Plugin Name: OHA Convention Schedule Hub
 * Plugin URI: https://ohiohaunt.com
 * Description: Comprehensive class schedule management for OHA Convention with multiple views, filtering, and PWA support
 * Version: 1.6.1
 * Author: Justin @ OHA
 * Author URI: https://ohiohaunt.com
 * License: GPL v2 or later
 * Text Domain: oha-schedule-hub
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('OHA_SCHEDULE_VERSION', '1.6.1');
define('OHA_SCHEDULE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OHA_SCHEDULE_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main OHA Schedule Hub Class
 */
class OHA_Schedule_Hub {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize plugin
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomies'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_oha_class', array($this, 'save_class_meta'));
        
        // Register shortcodes
        add_shortcode('oha_schedule', array($this, 'schedule_shortcode'));
        add_shortcode('oha_schedule_admin', array($this, 'schedule_admin_shortcode'));
        
        // AJAX handlers
        add_action('wp_ajax_oha_save_class', array($this, 'ajax_save_class'));
        add_action('wp_ajax_oha_delete_class', array($this, 'ajax_delete_class'));
        add_action('wp_ajax_oha_get_class', array($this, 'ajax_get_class'));
        add_action('wp_ajax_oha_bulk_import', array($this, 'ajax_bulk_import'));
        add_action('wp_ajax_oha_export_schedule',      array($this, 'ajax_export_schedule'));
        add_action('wp_ajax_oha_export_year_json',     array($this, 'ajax_export_year_json'));
        add_action('wp_ajax_oha_delete_year_classes',  array($this, 'ajax_delete_year_classes'));
        add_action('wp_ajax_oha_tag_untagged_classes', array($this, 'ajax_tag_untagged_classes'));

        // Activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));

        // Instructor headshot term meta
        add_action('instructor_add_form_fields',  array($this, 'instructor_add_headshot_field'));
        add_action('instructor_edit_form_fields', array($this, 'instructor_edit_headshot_field'));
        add_action('created_instructor',          array($this, 'save_instructor_headshot'));
        add_action('edited_instructor',           array($this, 'save_instructor_headshot'));

        // REST API endpoints (future cron integration stub)
        add_action('rest_api_init', array($this, 'register_rest_endpoints'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        $this->register_post_type();
        $this->register_taxonomies();
        flush_rewrite_rules();
        
        // Set default options
        $defaults = array(
            'time_format' => '12h',
            'default_duration' => 75,
            'setup_time' => 15,
            'teardown_time' => 15,
            'enable_search' => 1,
            'enable_filters' => 1,
            'default_view' => 'timeline',
            'show_descriptions' => 1,
            'enable_print' => 1,
            'day_order' => ''
        );
        
        foreach ($defaults as $key => $value) {
            if (false === get_option('oha_schedule_' . $key)) {
                add_option('oha_schedule_' . $key, $value);
            }
        }
    }
    
    /**
     * Register custom post type
     */
    public function register_post_type() {
        $labels = array(
            'name' => 'Classes',
            'singular_name' => 'Class',
            'menu_name' => 'Schedule',
            'add_new' => 'Add Class',
            'add_new_item' => 'Add New Class',
            'edit_item' => 'Edit Class',
            'new_item' => 'New Class',
            'view_item' => 'View Class',
            'search_items' => 'Search Classes',
            'not_found' => 'No classes found',
            'not_found_in_trash' => 'No classes found in trash'
        );
        
        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-calendar-alt',
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => array('title', 'editor', 'thumbnail'),
            'has_archive' => false,
            'rewrite' => false,
            'show_in_rest' => true
        );
        
        register_post_type('oha_class', $args);
    }
    
    /**
     * Register taxonomies
     */
    public function register_taxonomies() {
        // Track/Category taxonomy
        register_taxonomy('class_track', 'oha_class', array(
            'label' => 'Track',
            'hierarchical' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'rewrite' => false
        ));
        
        // Instructor taxonomy
        register_taxonomy('instructor', 'oha_class', array(
            'label' => 'Instructor',
            'hierarchical' => false,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'rewrite' => false
        ));
        
        // Day taxonomy
        register_taxonomy('class_day', 'oha_class', array(
            'label' => 'Day',
            'hierarchical' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'rewrite' => false
        ));

        // Convention Year taxonomy (for multi-year archiving)
        register_taxonomy('convention_year', 'oha_class', array(
            'label' => 'Convention Year',
            'hierarchical' => false,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'rewrite' => false,
        ));
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'oha_class_details',
            'Class Details',
            array($this, 'render_class_details_meta_box'),
            'oha_class',
            'normal',
            'high'
        );
    }
    
    /**
     * Render class details meta box
     */
    public function render_class_details_meta_box($post) {
        wp_nonce_field('oha_class_meta_nonce', 'oha_class_meta_nonce');
        
        $start_time      = get_post_meta($post->ID, '_oha_start_time', true);
        $end_time        = get_post_meta($post->ID, '_oha_end_time', true);
        $room            = get_post_meta($post->ID, '_oha_room', true);
        $capacity        = get_post_meta($post->ID, '_oha_capacity', true);
        $level           = get_post_meta($post->ID, '_oha_level', true);
        $short_desc      = get_post_meta($post->ID, '_oha_short_desc', true);
        $materials       = get_post_meta($post->ID, '_oha_materials', true);
        $notes           = get_post_meta($post->ID, '_oha_notes', true);
        $promoted        = get_post_meta($post->ID, '_oha_promoted', true);
        $show_end_time   = get_post_meta($post->ID, '_oha_show_end_time', true);
        $end_time_label  = get_post_meta($post->ID, '_oha_end_time_label', true);
        ?>
        <div class="oha-meta-box">
            <p style="background:#1a0808;padding:10px 12px;border-radius:6px;border-left:4px solid #e07820;margin-bottom:16px;">
                <strong style="color:#f0ddd0;">Note:</strong>
                <span style="color:#c9a090;font-size:13px;">
                    The <strong>Short Description</strong> below appears on the main schedule cards.
                    The <strong>WordPress editor above</strong> (Description / post content) is the full detail that appears when a user taps "more details".
                </span>
            </p>
            <p>
                <label><strong>Short Description</strong> <em style="font-weight:400;color:#9e7a68;">(shown on schedule cards)</em></label><br>
                <textarea name="oha_short_desc" rows="3" style="width: 100%;"><?php echo esc_textarea($short_desc); ?></textarea>
                <small style="color:#9e7a68;">Keep this brief — 1–2 sentences. The full description (WordPress editor above) shows in the detail modal.</small>
            </p>
            <p>
                <label><strong>Start Time:</strong></label><br>
                <input type="time" name="oha_start_time" value="<?php echo esc_attr($start_time); ?>" style="width: 200px;">
            </p>
            <p>
                <label><strong>End Time:</strong></label><br>
                <input type="time" name="oha_end_time" value="<?php echo esc_attr($end_time); ?>" style="width: 200px;">
            </p>
            <p>
                <label><strong>Room/Location:</strong></label><br>
                <input type="text" name="oha_room" value="<?php echo esc_attr($room); ?>" style="width: 100%;">
            </p>
            <p>
                <label><strong>Capacity:</strong></label><br>
                <input type="number" name="oha_capacity" value="<?php echo esc_attr($capacity); ?>" style="width: 100px;">
            </p>
            <p>
                <label><strong>Level:</strong></label><br>
                <select name="oha_level" style="width: 200px;">
                    <option value="">Select Level</option>
                    <option value="beginner" <?php selected($level, 'beginner'); ?>>Beginner</option>
                    <option value="intermediate" <?php selected($level, 'intermediate'); ?>>Intermediate</option>
                    <option value="advanced" <?php selected($level, 'advanced'); ?>>Advanced</option>
                    <option value="all" <?php selected($level, 'all'); ?>>All Levels</option>
                </select>
            </p>
            <p>
                <label><strong>Materials Needed:</strong></label><br>
                <textarea name="oha_materials" rows="3" style="width: 100%;"><?php echo esc_textarea($materials); ?></textarea>
            </p>
            <p>
                <label><strong>Internal Notes:</strong></label><br>
                <textarea name="oha_notes" rows="3" style="width: 100%;"><?php echo esc_textarea($notes); ?></textarea>
                <small>These notes are for admin use only and won't be displayed publicly.</small>
            </p>
            <p style="margin-top:16px;padding-top:16px;border-top:2px solid #f0b429;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:15px;">
                    <input type="checkbox" name="oha_promoted" value="1" <?php checked($promoted, '1'); ?>
                           style="width:18px;height:18px;cursor:pointer;">
                    <strong>Featured / Promoted Event</strong>
                </label>
                <small style="display:block;margin-top:4px;color:#666;padding-left:26px;">
                    High-priority events (costume ball, keynotes, special announcements). Adds a gold "Featured" badge on the public schedule.
                </small>
            </p>
            <p style="margin-top:16px;padding-top:16px;border-top:1px solid #2e1a1a;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:15px;">
                    <input type="checkbox" name="oha_show_end_time" value="1" <?php checked($show_end_time, '1'); ?>
                           style="width:18px;height:18px;cursor:pointer;">
                    <strong>Show End Time as Separate Quick List Entry</strong>
                </label>
                <small style="display:block;margin-top:4px;color:#666;padding-left:26px;">
                    When checked, the end time appears as its own line in the Quick List view (e.g. "5:00 PM Show Floor Closed").
                </small>
            </p>
            <p>
                <label><strong>End Time Label:</strong></label><br>
                <input type="text" name="oha_end_time_label" value="<?php echo esc_attr($end_time_label); ?>" style="width:100%;" placeholder="e.g. Show Floor Closed">
                <small style="color:#666;">Text shown next to the end time in the Quick List. Leave blank to show the time with no label.</small>
            </p>
            <p style="margin-top:16px;padding-top:16px;border-top:1px solid #2e1a1a;">
                <label><strong>Convention Year:</strong></label><br>
                <?php
                $yr_all     = get_terms(array('taxonomy' => 'convention_year', 'hide_empty' => false));
                if (is_wp_error($yr_all)) $yr_all = array();
                $yr_current = wp_get_post_terms($post->ID, 'convention_year', array('fields' => 'names'));
                $yr_current = !empty($yr_current) ? $yr_current[0] : '';
                $yr_options = wp_list_pluck($yr_all, 'name');
                $yr_now     = date('Y');
                if (!in_array($yr_now, $yr_options)) $yr_options[] = $yr_now;
                sort($yr_options);
                ?>
                <select name="oha_convention_year" style="width:200px;">
                    <option value="">-- Select Year --</option>
                    <?php foreach ($yr_options as $yr): ?>
                        <option value="<?php echo esc_attr($yr); ?>" <?php selected($yr_current, $yr); ?>><?php echo esc_html($yr); ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="display:block;margin-top:4px;color:#666;">Used for multi-year archiving. Set to current convention year.</small>
            </p>
        </div>
        <?php
    }
    
    /**
     * Save class meta
     */
    public function save_class_meta($post_id) {
        // Security checks
        if (!isset($_POST['oha_class_meta_nonce']) || 
            !wp_verify_nonce($_POST['oha_class_meta_nonce'], 'oha_class_meta_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save meta fields
        $fields = array(
            '_oha_start_time' => 'sanitize_text_field',
            '_oha_end_time'   => 'sanitize_text_field',
            '_oha_room'       => 'sanitize_text_field',
            '_oha_capacity'   => 'absint',
            '_oha_level'      => 'sanitize_text_field',
            '_oha_short_desc' => 'sanitize_textarea_field',
            '_oha_materials'  => 'sanitize_textarea_field',
            '_oha_notes'      => 'sanitize_textarea_field'
        );
        
        foreach ($fields as $field => $sanitize) {
            if (isset($_POST[str_replace('_oha_', 'oha_', $field)])) {
                update_post_meta(
                    $post_id,
                    $field,
                    $sanitize($_POST[str_replace('_oha_', 'oha_', $field)])
                );
            }
        }

        // Checkbox special case — unchecked sends no POST key
        if (!empty($_POST['oha_promoted']) && $_POST['oha_promoted'] === '1') {
            update_post_meta($post_id, '_oha_promoted', '1');
        } else {
            delete_post_meta($post_id, '_oha_promoted');
        }

        // Show end time checkbox
        if (!empty($_POST['oha_show_end_time']) && $_POST['oha_show_end_time'] === '1') {
            update_post_meta($post_id, '_oha_show_end_time', '1');
        } else {
            delete_post_meta($post_id, '_oha_show_end_time');
        }

        // End time label
        if (isset($_POST['oha_end_time_label'])) {
            update_post_meta($post_id, '_oha_end_time_label', sanitize_text_field($_POST['oha_end_time_label']));
        }

        // Convention year taxonomy
        if (isset($_POST['oha_convention_year'])) {
            $yr = sanitize_text_field($_POST['oha_convention_year']);
            if ($yr !== '') {
                wp_set_object_terms($post_id, $yr, 'convention_year');
            } else {
                wp_set_object_terms($post_id, array(), 'convention_year');
            }
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=oha_class',
            'Schedule Settings',
            'Settings',
            'manage_options',
            'oha-schedule-settings',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'edit.php?post_type=oha_class',
            'Bulk Import',
            'Bulk Import',
            'manage_options',
            'oha-schedule-import',
            array($this, 'render_import_page')
        );
        
        add_submenu_page(
            'edit.php?post_type=oha_class',
            'Schedule View',
            'View Schedule',
            'read',
            'oha-schedule-view',
            array($this, 'render_schedule_view_page')
        );
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (isset($_POST['oha_schedule_settings_nonce']) && 
            wp_verify_nonce($_POST['oha_schedule_settings_nonce'], 'oha_schedule_settings')) {
            
            $settings = array(
                'time_format', 'default_duration', 'setup_time', 'teardown_time',
                'enable_search', 'enable_filters', 'default_view', 'show_descriptions', 'enable_print',
                'day_order'
            );
            
            foreach ($settings as $setting) {
                if (isset($_POST['oha_schedule_' . $setting])) {
                    update_option('oha_schedule_' . $setting, sanitize_text_field($_POST['oha_schedule_' . $setting]));
                } else {
                    // Use '' not 0 — get_option always returns strings,
                    // and '0' is truthy in PHP so checkboxes would never turn off.
                    update_option('oha_schedule_' . $setting, '');
                }
            }
            
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        
        include OHA_SCHEDULE_PLUGIN_DIR . 'templates/admin-settings.php';
    }
    
    /**
     * Render import page
     */
    public function render_import_page() {
        include OHA_SCHEDULE_PLUGIN_DIR . 'templates/admin-import.php';
    }
    
    /**
     * Render schedule view page
     */
    public function render_schedule_view_page() {
        echo '<div class="wrap">';
        echo '<h1>Convention Schedule</h1>';
        echo do_shortcode('[oha_schedule view="timeline"]');
        echo '</div>';
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'oha') !== false || $hook === 'post.php' || $hook === 'post-new.php') {
            wp_enqueue_style('oha-schedule-admin', OHA_SCHEDULE_PLUGIN_URL . 'assets/css/admin.css', array(), OHA_SCHEDULE_VERSION);
            wp_enqueue_script('oha-schedule-admin', OHA_SCHEDULE_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), OHA_SCHEDULE_VERSION, true);

            wp_localize_script('oha-schedule-admin', 'ohaSchedule', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('oha_schedule_nonce')
            ));
        }

        // Media uploader for instructor headshots on taxonomy pages
        if ($hook === 'edit-tags.php' || $hook === 'term.php') {
            $taxonomy = isset($_GET['taxonomy']) ? sanitize_key($_GET['taxonomy']) : '';
            if ($taxonomy === 'instructor') {
                wp_enqueue_media();
                wp_add_inline_script('jquery', $this->get_instructor_headshot_admin_js());
            }
        }
    }

    /**
     * Inline JS for instructor headshot media uploader
     */
    private function get_instructor_headshot_admin_js() {
        return "
jQuery(document).ready(function(\$) {
    var frame;
    \$(document).on('click', '#instructor_upload_headshot', function(e) {
        e.preventDefault();
        if (frame) { frame.open(); return; }
        frame = wp.media({
            title: 'Select Instructor Headshot',
            button: { text: 'Use this image' },
            multiple: false,
            library: { type: 'image' }
        });
        frame.on('select', function() {
            var att = frame.state().get('selection').first().toJSON();
            \$('#instructor_headshot_id').val(att.id);
            var url = (att.sizes && att.sizes.thumbnail) ? att.sizes.thumbnail.url : att.url;
            \$('#instructor_headshot_preview').attr('src', url).show();
            \$('#instructor_remove_headshot').show();
        });
        frame.open();
    });
    \$(document).on('click', '#instructor_remove_headshot', function(e) {
        e.preventDefault();
        \$('#instructor_headshot_id').val('');
        \$('#instructor_headshot_preview').hide().attr('src', '');
        \$(this).hide();
    });
});
";
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_style('oha-schedule-frontend', OHA_SCHEDULE_PLUGIN_URL . 'assets/css/frontend.css', array(), OHA_SCHEDULE_VERSION);
        wp_enqueue_script('oha-schedule-frontend', OHA_SCHEDULE_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), OHA_SCHEDULE_VERSION, true);
        
        wp_localize_script('oha-schedule-frontend', 'ohaSchedule', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oha_schedule_nonce')
        ));
    }
    
    /**
     * Helper function to format time
     */
    public static function format_time($time, $format = '12h') {
        if (empty($time)) return '';
        
        $timestamp = strtotime($time);
        if ($format === '24h') {
            return date('H:i', $timestamp);
        } else {
            return date('g:i A', $timestamp);
        }
    }
    
    /**
     * Schedule shortcode
     */
    public function schedule_shortcode($atts) {
        $atts = shortcode_atts(array(
            'view'         => get_option('oha_schedule_default_view', 'timeline'),
            'day'          => '',
            'track'        => '',
            'instructor'   => '',
            'year'         => '',  // Filter to a specific convention year, e.g. year="2026"
            'show_filters' => get_option('oha_schedule_enable_filters', '1'),
            'show_search'  => get_option('oha_schedule_enable_search',  '1'),
            'enable_print' => get_option('oha_schedule_enable_print',   '1'),
            'map_url'      => '',  // URL for the map tab link in class detail modal
            'force_view'   => '',  // If "1", hide view switcher buttons so users can't change views
        ), $atts);
        
        ob_start();
        include OHA_SCHEDULE_PLUGIN_DIR . 'templates/schedule-display.php';
        return ob_get_clean();
    }
    
    /**
     * Schedule admin shortcode
     */
    public function schedule_admin_shortcode($atts) {
        if (!current_user_can('edit_posts')) {
            return '<p>You do not have permission to access this area.</p>';
        }
        
        // Enqueue admin scripts for frontend use
        wp_enqueue_style('oha-schedule-admin', OHA_SCHEDULE_PLUGIN_URL . 'assets/css/admin.css', array(), OHA_SCHEDULE_VERSION);
        wp_enqueue_script('oha-schedule-admin', OHA_SCHEDULE_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), OHA_SCHEDULE_VERSION, true);
        
        wp_localize_script('oha-schedule-admin', 'ohaSchedule', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oha_schedule_nonce')
        ));
        
        ob_start();
        include OHA_SCHEDULE_PLUGIN_DIR . 'templates/schedule-admin.php';
        return ob_get_clean();
    }
    
    /**
     * AJAX: Save class
     */
    public function ajax_save_class() {
        check_ajax_referer('oha_schedule_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $class_data = array(
            'post_type' => 'oha_class',
            'post_status' => 'publish',
            'post_title' => sanitize_text_field($_POST['title']),
            'post_content' => wp_kses_post($_POST['description'])
        );
        
        if (!empty($_POST['class_id'])) {
            $class_data['ID'] = absint($_POST['class_id']);
        }
        
        $class_id = wp_insert_post($class_data);
        
        if ($class_id) {
            // Save meta
            update_post_meta($class_id, '_oha_start_time', sanitize_text_field($_POST['start_time']));
            update_post_meta($class_id, '_oha_end_time',   sanitize_text_field($_POST['end_time']));
            update_post_meta($class_id, '_oha_room',       sanitize_text_field($_POST['room']));
            update_post_meta($class_id, '_oha_capacity',   absint($_POST['capacity']));
            update_post_meta($class_id, '_oha_level',      sanitize_text_field($_POST['level']));
            update_post_meta($class_id, '_oha_short_desc', sanitize_textarea_field(isset($_POST['short_desc']) ? $_POST['short_desc'] : ''));
            update_post_meta($class_id, '_oha_materials',  sanitize_textarea_field(isset($_POST['materials'])  ? $_POST['materials']  : ''));

            // Promoted flag (checkbox — absent when unchecked)
            if (!empty($_POST['promoted']) && $_POST['promoted'] === '1') {
                update_post_meta($class_id, '_oha_promoted', '1');
            } else {
                delete_post_meta($class_id, '_oha_promoted');
            }

            // Show end time checkbox
            if (!empty($_POST['show_end_time']) && $_POST['show_end_time'] === '1') {
                update_post_meta($class_id, '_oha_show_end_time', '1');
            } else {
                delete_post_meta($class_id, '_oha_show_end_time');
            }
            update_post_meta($class_id, '_oha_end_time_label', sanitize_text_field(isset($_POST['end_time_label']) ? $_POST['end_time_label'] : ''));

            // Set taxonomies
            if (!empty($_POST['day'])) {
                wp_set_object_terms($class_id, sanitize_text_field($_POST['day']), 'class_day');
            }
            if (!empty($_POST['instructor'])) {
                wp_set_object_terms($class_id, sanitize_text_field($_POST['instructor']), 'instructor');
            }
            if (!empty($_POST['track'])) {
                wp_set_object_terms($class_id, sanitize_text_field($_POST['track']), 'class_track');
            }
            if (isset($_POST['year'])) {
                if (!empty($_POST['year'])) {
                    wp_set_object_terms($class_id, sanitize_text_field($_POST['year']), 'convention_year');
                } else {
                    wp_set_object_terms($class_id, array(), 'convention_year');
                }
            }

            wp_send_json_success(array('class_id' => $class_id, 'message' => 'Class saved successfully'));
        } else {
            wp_send_json_error('Failed to save class');
        }
    }
    
    /**
     * AJAX: Delete class
     */
    public function ajax_delete_class() {
        check_ajax_referer('oha_schedule_nonce', 'nonce');
        
        if (!current_user_can('delete_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $class_id = absint($_POST['class_id']);
        
        if (wp_delete_post($class_id, true)) {
            wp_send_json_success('Class deleted successfully');
        } else {
            wp_send_json_error('Failed to delete class');
        }
    }
    
    /**
     * AJAX: Get class data
     */
    public function ajax_get_class() {
        check_ajax_referer('oha_schedule_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $class_id = absint($_POST['class_id']);
        $class = get_post($class_id);
        
        if (!$class) {
            wp_send_json_error('Class not found');
        }
        
        $days        = wp_get_post_terms($class_id, 'class_day',       array('fields' => 'names'));
        $instructors = wp_get_post_terms($class_id, 'instructor',      array('fields' => 'names'));
        $tracks      = wp_get_post_terms($class_id, 'class_track',     array('fields' => 'names'));
        $years       = wp_get_post_terms($class_id, 'convention_year', array('fields' => 'names'));

        $data = array(
            'title'          => $class->post_title,
            'description'    => $class->post_content,
            'short_desc'     => get_post_meta($class_id, '_oha_short_desc',     true),
            'start_time'     => get_post_meta($class_id, '_oha_start_time',     true),
            'end_time'       => get_post_meta($class_id, '_oha_end_time',       true),
            'room'           => get_post_meta($class_id, '_oha_room',           true),
            'capacity'       => get_post_meta($class_id, '_oha_capacity',       true),
            'level'          => get_post_meta($class_id, '_oha_level',          true),
            'materials'      => get_post_meta($class_id, '_oha_materials',      true),
            'show_end_time'  => get_post_meta($class_id, '_oha_show_end_time',  true),
            'end_time_label' => get_post_meta($class_id, '_oha_end_time_label', true),
            'day'            => !empty($days)        ? $days[0]        : '',
            'instructor'     => !empty($instructors) ? $instructors[0] : '',
            'track'          => !empty($tracks)      ? $tracks[0]      : '',
            'year'           => !empty($years)        ? $years[0]       : '',
        );
        
        wp_send_json_success($data);
    }
    
    /**
     * AJAX: Bulk import
     */
    public function ajax_bulk_import() {
        check_ajax_referer('oha_schedule_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $classes = json_decode(stripslashes($_POST['classes']), true);
        $imported = 0;
        
        foreach ($classes as $class) {
            $class_data = array(
                'post_type' => 'oha_class',
                'post_status' => 'publish',
                'post_title' => sanitize_text_field($class['title']),
                'post_content' => wp_kses_post($class['description'])
            );
            
            $class_id = wp_insert_post($class_data);
            
            if ($class_id) {
                update_post_meta($class_id, '_oha_start_time', sanitize_text_field(isset($class['start_time']) ? $class['start_time'] : ''));
                update_post_meta($class_id, '_oha_end_time',   sanitize_text_field(isset($class['end_time'])   ? $class['end_time']   : ''));
                update_post_meta($class_id, '_oha_room',       sanitize_text_field(isset($class['room'])       ? $class['room']       : ''));

                if (!empty($class['capacity']))   update_post_meta($class_id, '_oha_capacity',   absint($class['capacity']));
                if (!empty($class['level']))      update_post_meta($class_id, '_oha_level',      sanitize_text_field($class['level']));
                if (!empty($class['short_desc'])) update_post_meta($class_id, '_oha_short_desc', sanitize_textarea_field($class['short_desc']));
                if (!empty($class['materials']))  update_post_meta($class_id, '_oha_materials',  sanitize_textarea_field($class['materials']));
                if (!empty($class['notes']))      update_post_meta($class_id, '_oha_notes',      sanitize_textarea_field($class['notes']));

                if (!empty($class['promoted'])) {
                    update_post_meta($class_id, '_oha_promoted', '1');
                }

                if (!empty($class['day'])) {
                    wp_set_object_terms($class_id, sanitize_text_field($class['day']), 'class_day');
                }
                if (!empty($class['instructor'])) {
                    wp_set_object_terms($class_id, sanitize_text_field($class['instructor']), 'instructor');
                }
                if (!empty($class['track'])) {
                    wp_set_object_terms($class_id, sanitize_text_field($class['track']), 'class_track');
                }
                if (!empty($class['year'])) {
                    wp_set_object_terms($class_id, sanitize_text_field($class['year']), 'convention_year');
                }

                $imported++;
            }
        }
        
        wp_send_json_success(array('imported' => $imported, 'message' => "$imported classes imported successfully"));
    }
    
    /**
     * AJAX: Export schedule
     */
    public function ajax_export_schedule() {
        check_ajax_referer('oha_schedule_nonce', 'nonce');
        
        $args = array(
            'post_type' => 'oha_class',
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'meta_key' => '_oha_start_time',
            'order' => 'ASC'
        );
        
        $classes = get_posts($args);
        $export_data = array();
        
        foreach ($classes as $class) {
            $days        = wp_get_post_terms($class->ID, 'class_day',       array('fields' => 'names'));
            $instructors = wp_get_post_terms($class->ID, 'instructor',      array('fields' => 'names'));
            $tracks      = wp_get_post_terms($class->ID, 'class_track',     array('fields' => 'names'));
            $years       = wp_get_post_terms($class->ID, 'convention_year', array('fields' => 'names'));

            $export_data[] = array(
                'title'       => $class->post_title,
                'description' => $class->post_content,
                'short_desc'  => get_post_meta($class->ID, '_oha_short_desc', true),
                'start_time'  => get_post_meta($class->ID, '_oha_start_time', true),
                'end_time'    => get_post_meta($class->ID, '_oha_end_time',   true),
                'room'        => get_post_meta($class->ID, '_oha_room',       true),
                'capacity'    => get_post_meta($class->ID, '_oha_capacity',   true),
                'level'       => get_post_meta($class->ID, '_oha_level',      true),
                'materials'   => get_post_meta($class->ID, '_oha_materials',  true),
                'notes'       => get_post_meta($class->ID, '_oha_notes',      true),
                'promoted'    => get_post_meta($class->ID, '_oha_promoted',   true),
                'day'         => !empty($days)        ? $days[0]        : '',
                'instructor'  => !empty($instructors) ? $instructors[0] : '',
                'track'       => !empty($tracks)      ? $tracks[0]      : '',
                'year'        => !empty($years)        ? $years[0]       : '',
            );
        }
        
        wp_send_json_success($export_data);
    }

    /**
     * Instructor headshot — add form (new term)
     */
    public function instructor_add_headshot_field($taxonomy) {
        ?>
        <div class="form-field">
            <label for="instructor_headshot_id">Instructor Headshot</label>
            <div>
                <img id="instructor_headshot_preview" src=""
                     style="width:80px;height:80px;border-radius:50%;object-fit:cover;display:none;margin-bottom:8px;border:3px solid #e07820;" />
                <br>
                <input type="hidden" name="instructor_headshot_id" id="instructor_headshot_id" value="">
                <button type="button" class="button" id="instructor_upload_headshot">Upload Headshot</button>
                <button type="button" class="button" id="instructor_remove_headshot" style="display:none;margin-left:6px;">Remove</button>
            </div>
            <p>Circular headshot photo for this instructor. Displays on class cards and detail modals.</p>
        </div>
        <?php
    }

    /**
     * Instructor headshot — edit form (existing term)
     */
    public function instructor_edit_headshot_field($term) {
        $headshot_id  = get_term_meta($term->term_id, 'instructor_headshot_id', true);
        $headshot_url = $headshot_id ? wp_get_attachment_image_url($headshot_id, 'thumbnail') : '';
        ?>
        <tr class="form-field">
            <th scope="row"><label for="instructor_headshot_id">Instructor Headshot</label></th>
            <td>
                <img id="instructor_headshot_preview" src="<?php echo esc_url($headshot_url); ?>"
                     style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin-bottom:8px;border:3px solid #e07820;<?php echo $headshot_url ? '' : 'display:none;'; ?>" />
                <br>
                <input type="hidden" name="instructor_headshot_id" id="instructor_headshot_id"
                       value="<?php echo esc_attr($headshot_id); ?>">
                <button type="button" class="button" id="instructor_upload_headshot">Upload Headshot</button>
                <button type="button" class="button" id="instructor_remove_headshot"
                        style="margin-left:6px;<?php echo $headshot_url ? '' : 'display:none;'; ?>">Remove</button>
                <p class="description">Circular headshot photo for this instructor. Displays on class cards and detail modals.</p>
            </td>
        </tr>
        <?php
    }

    /**
     * Instructor headshot — save term meta
     */
    public function save_instructor_headshot($term_id) {
        if (!isset($_POST['instructor_headshot_id'])) {
            return;
        }
        $headshot_id = absint($_POST['instructor_headshot_id']);
        if ($headshot_id) {
            update_term_meta($term_id, 'instructor_headshot_id', $headshot_id);
        } else {
            delete_term_meta($term_id, 'instructor_headshot_id');
        }
    }

    /**
     * Register REST API endpoints (future cron integration hooks)
     */
    public function register_rest_endpoints() {
        register_rest_route('oha/v1', '/schedule/promoted-starting-soon', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'rest_promoted_starting_soon'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route('oha/v1', '/schedule/events', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'rest_get_events'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * REST: Return all published classes for a given convention year.
     * Used by [oha_happening_now] when [oha_convention_schedule] is not on the same page.
     * Query param: year (e.g. "2026") — filters by convention_year taxonomy.
     */
    public function rest_get_events( $request ) {
        $year = $request->get_param( 'year' );

        $args = array(
            'post_type'      => 'oha_class',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value',
            'meta_key'       => '_oha_start_time',
            'order'          => 'ASC',
        );

        if ( $year ) {
            $args['tax_query'] = array( array(
                'taxonomy' => 'convention_year',
                'field'    => 'name',
                'terms'    => sanitize_text_field( $year ),
            ) );
        }

        $classes = get_posts( $args );
        $events  = array();

        foreach ( $classes as $class ) {
            $days    = wp_get_post_terms( $class->ID, 'class_day', array( 'fields' => 'names' ) );
            $events[] = array(
                'title'      => $class->post_title,
                'start_time' => get_post_meta( $class->ID, '_oha_start_time', true ),
                'end_time'   => get_post_meta( $class->ID, '_oha_end_time',   true ),
                'room'       => get_post_meta( $class->ID, '_oha_room',       true ),
                'day'        => ! empty( $days ) ? $days[0] : '',
            );
        }

        return rest_ensure_response( $events );
    }

    /**
     * REST: Return promoted events starting in 5-10 minutes.
     *
     * STUB — currently returns empty array. Future WP-Cron use:
     *   1. Query oha_class posts where _oha_promoted = '1'
     *   2. Check _oha_start_time is within NOW+5min to NOW+10min (server time)
     *   3. Pass event data to OHA_PWA_Notifications::send_scheduled_reminder()
     *
     * @return WP_REST_Response
     */
    public function rest_promoted_starting_soon() {
        // TODO (cron): implement promoted event query + server-side push trigger
        return rest_ensure_response( array() );
    }

    /**
     * AJAX: Export all classes for a specific convention year as full JSON.
     * Used by Year Management section in Settings for archiving.
     */
    public function ajax_export_year_json() {
        check_ajax_referer('oha_schedule_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        $year = isset($_POST['year']) ? sanitize_text_field($_POST['year']) : '';
        if (empty($year)) {
            wp_send_json_error('No year specified');
        }
        $classes = get_posts(array(
            'post_type'      => 'oha_class',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value',
            'meta_key'       => '_oha_start_time',
            'order'          => 'ASC',
            'tax_query'      => array(array(
                'taxonomy' => 'convention_year',
                'field'    => 'name',
                'terms'    => $year,
            )),
        ));
        $export = array();
        foreach ($classes as $class) {
            $days        = wp_get_post_terms($class->ID, 'class_day',       array('fields' => 'names'));
            $instructors = wp_get_post_terms($class->ID, 'instructor',      array('fields' => 'names'));
            $tracks      = wp_get_post_terms($class->ID, 'class_track',     array('fields' => 'names'));
            $export[] = array(
                'title'       => $class->post_title,
                'description' => $class->post_content,
                'short_desc'  => get_post_meta($class->ID, '_oha_short_desc', true),
                'start_time'  => get_post_meta($class->ID, '_oha_start_time', true),
                'end_time'    => get_post_meta($class->ID, '_oha_end_time',   true),
                'room'        => get_post_meta($class->ID, '_oha_room',       true),
                'capacity'    => get_post_meta($class->ID, '_oha_capacity',   true),
                'level'       => get_post_meta($class->ID, '_oha_level',      true),
                'materials'   => get_post_meta($class->ID, '_oha_materials',  true),
                'notes'       => get_post_meta($class->ID, '_oha_notes',      true),
                'promoted'    => get_post_meta($class->ID, '_oha_promoted',   true),
                'day'         => !empty($days)        ? $days[0]        : '',
                'instructor'  => !empty($instructors) ? $instructors[0] : '',
                'track'       => !empty($tracks)      ? $tracks[0]      : '',
                'year'        => $year,
            );
        }
        wp_send_json_success($export);
    }

    /**
     * AJAX: Move all classes for a specific convention year to trash.
     * Used by Year Management section. Always exports first via separate call.
     */
    public function ajax_delete_year_classes() {
        check_ajax_referer('oha_schedule_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        $year = isset($_POST['year']) ? sanitize_text_field($_POST['year']) : '';
        if (empty($year)) {
            wp_send_json_error('No year specified');
        }
        $class_ids = get_posts(array(
            'post_type'      => 'oha_class',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => array(array(
                'taxonomy' => 'convention_year',
                'field'    => 'name',
                'terms'    => $year,
            )),
        ));
        $trashed = 0;
        foreach ($class_ids as $id) {
            if (wp_trash_post($id)) {
                $trashed++;
            }
        }
        wp_send_json_success(array(
            'trashed' => $trashed,
            'message' => $trashed . ' classes moved to trash.',
        ));
    }

    /**
     * AJAX: Tag all classes that have no convention_year term with a given year.
     * Used by Year Management section to retroactively tag existing classes.
     */
    public function ajax_tag_untagged_classes() {
        check_ajax_referer('oha_schedule_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        $year = isset($_POST['year']) ? sanitize_text_field($_POST['year']) : '';
        if (empty($year)) {
            wp_send_json_error('No year specified');
        }
        $class_ids = get_posts(array(
            'post_type'      => 'oha_class',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => array(array(
                'taxonomy' => 'convention_year',
                'operator' => 'NOT EXISTS',
            )),
        ));
        $tagged = 0;
        foreach ($class_ids as $id) {
            wp_set_object_terms($id, $year, 'convention_year', false);
            $tagged++;
        }
        wp_send_json_success(array(
            'tagged'  => $tagged,
            'message' => 'Tagged ' . $tagged . ' classes as ' . $year . '.',
        ));
    }
}

// Initialize the plugin
OHA_Schedule_Hub::get_instance();
