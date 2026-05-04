<?php
/**
 * OHA PWA Schedule Class (Preserved from v3.3.0)
 * 
 * Maintains existing schedule functionality while adding Actor Passport features
 * This is a simplified placeholder to ensure v3.3.0 features remain functional
 * 
 * @package OHA_PWA_Core
 * @version 3.4.0
 */

defined('ABSPATH') || exit;

class OHA_PWA_Schedule {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('wp_ajax_oha_get_schedule_data', array($this, 'ajax_get_schedule_data'));
        add_action('wp_ajax_nopriv_oha_get_schedule_data', array($this, 'ajax_get_schedule_data'));
        add_action('wp_ajax_oha_toggle_favorite', array($this, 'ajax_toggle_favorite'));
        add_action('wp_ajax_oha_export_schedule_csv', array($this, 'ajax_export_csv'));
        
        // Initialize schedule post type
        add_action('init', array($this, 'register_schedule_post_type'));
    }

    /**
     * Register schedule post type (preserved from v3.3.0)
     */
    public function register_schedule_post_type() {
        register_post_type('oha_schedule', array(
            'label' => 'Schedule Items',
            'labels' => array(
                'name' => 'Schedule Items',
                'singular_name' => 'Schedule Item',
                'add_new' => 'Add New Schedule Item',
                'edit_item' => 'Edit Schedule Item'
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'supports' => array('title', 'editor', 'custom-fields'),
            'menu_icon' => 'dashicons-calendar-alt',
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => 'manage_options',
            ),
            'map_meta_cap' => true
        ));
    }

    /**
     * Get schedule data (AJAX endpoint)
     */
    public function ajax_get_schedule_data() {
        
        // Basic schedule data structure (v3.3.0 preserved)
        $schedule_data = array(
            'days' => array(
                array(
                    'date' => '2026-05-09',
                    'name' => 'Friday',
                    'sessions' => $this->get_schedule_sessions('2026-05-09')
                ),
                array(
                    'date' => '2026-05-10', 
                    'name' => 'Saturday',
                    'sessions' => $this->get_schedule_sessions('2026-05-10')
                )
            ),
            'tracks' => array(
                'business' => 'Business Operations',
                'makeup' => 'Makeup & Costumes',
                'acting' => 'Scare Acting',
                'tech' => 'Technology & Effects',
                'safety' => 'Safety & Training',
                'networking' => 'Networking'
            ),
            'user_favorites' => $this->get_user_favorites()
        );

        wp_send_json_success($schedule_data);
    }

    /**
     * Get schedule sessions for a specific date
     */
    private function get_schedule_sessions($date) {
        
        // Query schedule items for this date
        $sessions = get_posts(array(
            'post_type' => 'oha_schedule',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'session_date',
                    'value' => $date,
                    'compare' => '='
                )
            ),
            'meta_key' => 'start_time',
            'orderby' => 'meta_value',
            'order' => 'ASC'
        ));

        $formatted_sessions = array();
        
        foreach ($sessions as $session) {
            $formatted_sessions[] = array(
                'id' => $session->ID,
                'title' => $session->post_title,
                'description' => $session->post_content,
                'start_time' => get_post_meta($session->ID, 'start_time', true),
                'end_time' => get_post_meta($session->ID, 'end_time', true),
                'track' => get_post_meta($session->ID, 'track', true),
                'instructor' => get_post_meta($session->ID, 'instructor', true),
                'instructor_photo' => get_post_meta($session->ID, 'instructor_photo', true),
                'room' => get_post_meta($session->ID, 'room', true),
                'capacity' => get_post_meta($session->ID, 'capacity', true),
                'level' => get_post_meta($session->ID, 'level', true),
                'materials' => get_post_meta($session->ID, 'materials', true)
            );
        }

        return $formatted_sessions;
    }

    /**
     * Get user favorites
     */
    private function get_user_favorites() {
        if (!is_user_logged_in()) {
            return array();
        }

        $favorites = get_user_meta(get_current_user_id(), 'oha_schedule_favorites', true);
        return is_array($favorites) ? $favorites : array();
    }

    /**
     * Toggle session favorite (AJAX)
     */
    public function ajax_toggle_favorite() {
        
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to save favorites');
        }

        $session_id = intval($_POST['session_id']);
        $user_id = get_current_user_id();
        
        if (!$session_id) {
            wp_send_json_error('Invalid session ID');
        }

        $favorites = $this->get_user_favorites();
        $is_favorited = in_array($session_id, $favorites);
        
        if ($is_favorited) {
            // Remove from favorites
            $favorites = array_diff($favorites, array($session_id));
            $action = 'removed';
        } else {
            // Add to favorites
            $favorites[] = $session_id;
            $action = 'added';
        }

        update_user_meta($user_id, 'oha_schedule_favorites', array_values($favorites));

        wp_send_json_success(array(
            'action' => $action,
            'session_id' => $session_id,
            'is_favorited' => !$is_favorited
        ));
    }

    /**
     * Export schedule as CSV (AJAX)
     */
    public function ajax_export_csv() {
        
        if (!current_user_can('read')) {
            wp_send_json_error('Permission denied');
        }

        // Generate CSV data
        $csv_data = $this->generate_schedule_csv();
        
        wp_send_json_success(array(
            'csv_data' => $csv_data,
            'filename' => 'oha-convention-2026-schedule-' . date('Y-m-d') . '.csv'
        ));
    }

    /**
     * Generate CSV data for schedule
     */
    private function generate_schedule_csv() {
        
        $csv = "Date,Time,Title,Track,Instructor,Room,Description\n";
        
        $sessions = get_posts(array(
            'post_type' => 'oha_schedule',
            'posts_per_page' => -1,
            'meta_key' => 'session_date',
            'orderby' => array(
                'meta_value' => 'ASC',
                'meta_value_num' => 'ASC'
            )
        ));

        foreach ($sessions as $session) {
            $date = get_post_meta($session->ID, 'session_date', true);
            $start_time = get_post_meta($session->ID, 'start_time', true);
            $track = get_post_meta($session->ID, 'track', true);
            $instructor = get_post_meta($session->ID, 'instructor', true);
            $room = get_post_meta($session->ID, 'room', true);
            
            $csv .= sprintf(
                "%s,%s,\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $date,
                $start_time,
                str_replace('"', '""', $session->post_title),
                str_replace('"', '""', $track),
                str_replace('"', '""', $instructor),
                str_replace('"', '""', $room),
                str_replace('"', '""', wp_strip_all_tags($session->post_content))
            );
        }

        return $csv;
    }

    /**
     * Create sample schedule data (for testing)
     */
    public function create_sample_data() {
        
        if (!current_user_can('manage_options')) {
            return;
        }

        // Sample sessions for OHA Convention 2026
        $sample_sessions = array(
            array(
                'title' => 'Welcome & Opening Keynote',
                'date' => '2026-05-09',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'track' => 'networking',
                'instructor' => 'OHA Board',
                'room' => 'Main Hall',
                'description' => 'Official opening of OHA Convention 2026'
            ),
            array(
                'title' => 'Scare Acting Fundamentals',
                'date' => '2026-05-09',
                'start_time' => '10:30',
                'end_time' => '11:30',
                'track' => 'acting',
                'instructor' => 'Sarah Mitchell',
                'room' => 'Theater 1',
                'description' => 'Learn the basics of effective scare acting techniques'
            ),
            array(
                'title' => 'Makeup Effects Workshop',
                'date' => '2026-05-09',
                'start_time' => '13:00',
                'end_time' => '15:00',
                'track' => 'makeup',
                'instructor' => 'Mike Rodriguez',
                'room' => 'Workshop A',
                'description' => 'Hands-on special effects makeup for haunters'
            ),
            array(
                'title' => 'Actor Passport Demo',
                'date' => '2026-05-09',
                'start_time' => '15:30',
                'end_time' => '16:00',
                'track' => 'tech',
                'instructor' => 'OHA Tech Team',
                'room' => 'Tech Lab',
                'description' => 'Introduction to the new Actor Passport badge trading system'
            ),
            array(
                'title' => 'Haunt Safety Standards',
                'date' => '2026-05-10',
                'start_time' => '09:00',
                'end_time' => '10:30',
                'track' => 'safety',
                'instructor' => 'Dr. Jennifer Adams',
                'room' => 'Conference Room 1',
                'description' => 'Essential safety protocols for haunted attractions'
            ),
            array(
                'title' => 'Marketing Your Haunt',
                'date' => '2026-05-10',
                'start_time' => '11:00',
                'end_time' => '12:00',
                'track' => 'business',
                'instructor' => 'David Kim',
                'room' => 'Conference Room 2',
                'description' => 'Digital marketing strategies for haunted attractions'
            )
        );

        foreach ($sample_sessions as $session) {
            
            // Check if session already exists
            $existing = get_posts(array(
                'post_type' => 'oha_schedule',
                'title' => $session['title'],
                'posts_per_page' => 1
            ));

            if (empty($existing)) {
                
                $post_id = wp_insert_post(array(
                    'post_title' => $session['title'],
                    'post_content' => $session['description'],
                    'post_type' => 'oha_schedule',
                    'post_status' => 'publish'
                ));

                if ($post_id) {
                    update_post_meta($post_id, 'session_date', $session['date']);
                    update_post_meta($post_id, 'start_time', $session['start_time']);
                    update_post_meta($post_id, 'end_time', $session['end_time']);
                    update_post_meta($post_id, 'track', $session['track']);
                    update_post_meta($post_id, 'instructor', $session['instructor']);
                    update_post_meta($post_id, 'room', $session['room']);
                }
            }
        }
    }
}
