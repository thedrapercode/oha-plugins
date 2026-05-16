<?php
/**
 * Plugin Name: OHA Survey Hub
 * Plugin URI: https://ohiohaunt.com
 * Description: Comprehensive anonymous survey system for the Ohio Haunters Association with multiple survey types, progress tracking, and data analytics.
 * Version: 1.1.0
 * Author: OHA
 * Author URI: https://ohiohaunt.com
 * License: GPL v2 or later
 * Text Domain: oha-survey-hub
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('OHA_SURVEY_HUB_VERSION', '1.1.0');
define('OHA_SURVEY_HUB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OHA_SURVEY_HUB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OHA_SURVEY_HUB_PLUGIN_FILE', __FILE__);

class OHA_Survey_Hub {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Include required files
        $this->include_files();
        
        // Initialize shortcodes
        add_action('init', array($this, 'register_shortcodes'));
        
        // Admin menu
        add_action('admin_menu', array($this, 'admin_menu'));
        
        // AJAX handlers
        add_action('wp_ajax_oha_survey_save_progress', array($this, 'ajax_save_progress'));
        add_action('wp_ajax_nopriv_oha_survey_save_progress', array($this, 'ajax_save_progress'));
        add_action('wp_ajax_oha_survey_load_progress', array($this, 'ajax_load_progress'));
        add_action('wp_ajax_nopriv_oha_survey_load_progress', array($this, 'ajax_load_progress'));
        add_action('wp_ajax_oha_survey_submit', array($this, 'ajax_submit_survey'));
        add_action('wp_ajax_nopriv_oha_survey_submit', array($this, 'ajax_submit_survey'));
        add_action('wp_ajax_oha_survey_validate_email', array($this, 'ajax_validate_email'));
        add_action('wp_ajax_nopriv_oha_survey_validate_email', array($this, 'ajax_validate_email'));
        
        // Admin AJAX handlers
        add_action('wp_ajax_oha_survey_get_response', array($this, 'ajax_get_response'));
        add_action('wp_ajax_oha_survey_cleanup_old_responses', array($this, 'ajax_cleanup_old_responses'));
        add_action('wp_ajax_oha_survey_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_oha_survey_export', array($this, 'ajax_export_survey'));

        // Convention survey AJAX handlers
        $convention = new OHA_Convention_Survey();
        add_action('wp_ajax_nopriv_oha_convention_survey_submit', array($convention, 'ajax_submit'));
        add_action('wp_ajax_oha_convention_survey_submit',        array($convention, 'ajax_submit'));
        add_action('wp_ajax_oha_convention_save_question',        array($convention, 'ajax_save_question'));
        add_action('wp_ajax_oha_convention_toggle_question',      array($convention, 'ajax_toggle_question'));
        add_action('wp_ajax_oha_convention_delete_question',      array($convention, 'ajax_delete_question'));
        
        // Handle export requests
        add_action('init', array($this, 'handle_export_request'));
    }
    
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('oha-survey-hub', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    private function include_files() {
        require_once OHA_SURVEY_HUB_PLUGIN_DIR . 'includes/class-survey-data.php';
        require_once OHA_SURVEY_HUB_PLUGIN_DIR . 'includes/class-survey-processor.php';
        require_once OHA_SURVEY_HUB_PLUGIN_DIR . 'includes/class-survey-admin.php';
        require_once OHA_SURVEY_HUB_PLUGIN_DIR . 'includes/survey-definitions.php';
        require_once OHA_SURVEY_HUB_PLUGIN_DIR . 'includes/class-convention-survey.php';
    }
    
    public function enqueue_scripts() {
        // Only enqueue on pages with survey shortcodes
        global $post;
        if (!is_a($post, 'WP_Post')) return;
        
        $survey_shortcodes = ['oha_survey_selector', 'oha_survey_member', 'oha_survey_haunt_owner', 'oha_survey_scare_actor', 'oha_survey_behind_scenes', 'oha_survey_home_haunt', 'oha_survey_industry_deep_dive', 'oha_convention_survey'];
        $has_survey_shortcode = false;

        foreach ($survey_shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                $has_survey_shortcode = true;
                break;
            }
        }
        
        if ($has_survey_shortcode) {
            wp_enqueue_style(
                'oha-survey-hub-style',
                OHA_SURVEY_HUB_PLUGIN_URL . 'assets/css/survey-style.css',
                array(),
                OHA_SURVEY_HUB_VERSION
            );
            
            wp_enqueue_script(
                'oha-survey-hub-script',
                OHA_SURVEY_HUB_PLUGIN_URL . 'assets/js/survey-script.js',
                array('jquery'),
                OHA_SURVEY_HUB_VERSION,
                true
            );
            
            // Localize script for AJAX
            // Convention survey JS (only loaded on pages with that shortcode)
            if (has_shortcode($post->post_content, 'oha_convention_survey')) {
                wp_enqueue_script(
                    'oha-convention-survey-script',
                    OHA_SURVEY_HUB_PLUGIN_URL . 'assets/js/convention-survey.js',
                    array('jquery'),
                    OHA_SURVEY_HUB_VERSION,
                    true
                );
                wp_localize_script('oha-convention-survey-script', 'oha_conv_ajax', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce'    => wp_create_nonce('oha_survey_nonce'),
                ));
            }

            wp_localize_script('oha-survey-hub-script', 'oha_survey_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('oha_survey_nonce'),
                'strings' => array(
                    'saving' => __('Saving...', 'oha-survey-hub'),
                    'saved' => __('Progress saved!', 'oha-survey-hub'),
                    'error' => __('Error saving progress', 'oha-survey-hub'),
                    'submitting' => __('Submitting...', 'oha-survey-hub'),
                    'submitted' => __('Survey submitted successfully!', 'oha-survey-hub'),
                    'invalid_email' => __('Please enter a valid email address', 'oha-survey-hub'),
                    'required_fields' => __('Please fill in all required fields', 'oha-survey-hub')
                )
            ));
        }
    }
    
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'oha-survey-hub') !== false) {
            // Chart.js must be enqueued/registered BEFORE any script that depends on it.
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.0', true);

            wp_enqueue_style(
                'oha-survey-hub-admin-style',
                OHA_SURVEY_HUB_PLUGIN_URL . 'assets/css/admin-style.css',
                array(),
                OHA_SURVEY_HUB_VERSION
            );
            
            wp_enqueue_script(
                'oha-survey-hub-admin-script',
                OHA_SURVEY_HUB_PLUGIN_URL . 'assets/js/admin-script.js',
                array('jquery', 'chart-js'),
                OHA_SURVEY_HUB_VERSION,
                true
            );
            
            // Localize script for admin AJAX
            wp_localize_script('oha-survey-hub-admin-script', 'oha_survey_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('oha_survey_nonce'),
                'export_nonce' => wp_create_nonce('oha_survey_export')
            ));
        }
    }
    
    public function register_shortcodes() {
        add_shortcode('oha_survey_selector', array($this, 'survey_selector_shortcode'));
        add_shortcode('oha_survey_member', array($this, 'survey_member_shortcode'));
        add_shortcode('oha_survey_haunt_owner', array($this, 'survey_haunt_owner_shortcode'));
        add_shortcode('oha_survey_scare_actor', array($this, 'survey_scare_actor_shortcode'));
        add_shortcode('oha_survey_behind_scenes', array($this, 'survey_behind_scenes_shortcode'));
        add_shortcode('oha_survey_home_haunt', array($this, 'survey_home_haunt_shortcode'));
        add_shortcode('oha_survey_industry_deep_dive', array($this, 'survey_industry_deep_dive_shortcode'));
        $convention = new OHA_Convention_Survey();
        add_shortcode('oha_convention_survey', array($convention, 'shortcode'));
    }
    
    public function admin_menu() {
        add_menu_page(
            __('Survey Hub', 'oha-survey-hub'),
            __('Survey Hub', 'oha-survey-hub'),
            'manage_options',
            'oha-survey-hub',
            array($this, 'admin_dashboard_page'),
            'dashicons-feedback',
            25
        );
        
        add_submenu_page(
            'oha-survey-hub',
            __('Survey Results', 'oha-survey-hub'),
            __('Results', 'oha-survey-hub'),
            'manage_options',
            'oha-survey-results',
            array($this, 'admin_results_page')
        );
        
        add_submenu_page(
            'oha-survey-hub',
            __('Analytics', 'oha-survey-hub'),
            __('Analytics', 'oha-survey-hub'),
            'manage_options',
            'oha-survey-analytics',
            array($this, 'admin_analytics_page')
        );
        
        add_submenu_page(
            'oha-survey-hub',
            __('Settings', 'oha-survey-hub'),
            __('Settings', 'oha-survey-hub'),
            'manage_options',
            'oha-survey-settings',
            array($this, 'admin_settings_page')
        );

        add_submenu_page(
            'oha-survey-hub',
            __('Convention Survey', 'oha-survey-hub'),
            __('Convention Survey', 'oha-survey-hub'),
            'manage_options',
            'oha-convention-survey',
            array($this, 'admin_convention_page')
        );
    }
    
    // Shortcode handlers
    public function survey_selector_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Choose Your Survey', 'oha-survey-hub'),
            'description' => __('Select the survey that best describes your role in the haunt industry.', 'oha-survey-hub')
        ), $atts);
        
        ob_start();
        include OHA_SURVEY_HUB_PLUGIN_DIR . 'templates/survey-selector.php';
        return ob_get_clean();
    }
    
    public function survey_member_shortcode($atts) {
        return $this->render_survey('member', $atts);
    }
    
    public function survey_haunt_owner_shortcode($atts) {
        return $this->render_survey('haunt_owner', $atts);
    }
    
    public function survey_scare_actor_shortcode($atts) {
        return $this->render_survey('scare_actor', $atts);
    }
    
    public function survey_behind_scenes_shortcode($atts) {
        return $this->render_survey('behind_scenes', $atts);
    }
    
    public function survey_home_haunt_shortcode($atts) {
        return $this->render_survey('home_haunt', $atts);
    }
    
    public function survey_industry_deep_dive_shortcode($atts) {
        return $this->render_survey('industry_deep_dive', $atts);
    }
    
    private function render_survey($survey_type, $atts) {
        $atts = shortcode_atts(array(
            'direct_link' => false
        ), $atts);
        
        $survey_processor = new OHA_Survey_Processor();
        return $survey_processor->render_survey($survey_type, $atts);
    }
    
    // AJAX handlers
    public function ajax_save_progress() {
        check_ajax_referer('oha_survey_nonce', 'nonce');
        
        $survey_processor = new OHA_Survey_Processor();
        $result = $survey_processor->save_progress($_POST);
        
        wp_send_json($result);
    }
    
    public function ajax_submit_survey() {
        check_ajax_referer('oha_survey_nonce', 'nonce');
        
        $survey_processor = new OHA_Survey_Processor();
        $result = $survey_processor->submit_survey($_POST);
        
        wp_send_json($result);
    }

    /**
     * AJAX handler for loading in-progress survey data
     */
    public function ajax_load_progress() {
        check_ajax_referer('oha_survey_nonce', 'nonce');

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $survey_type = isset($_POST['survey_type']) ? sanitize_text_field($_POST['survey_type']) : '';

        if (!$email || !is_email($email) || !$survey_type) {
            wp_send_json_error(array('message' => __('Invalid request', 'oha-survey-hub')));
        }

        $data_handler = new OHA_Survey_Data();
        $progress = $data_handler->get_survey_progress($email, $survey_type);

        if (!$progress) {
            wp_send_json_success(null);
        }

        // Return minimal data needed to resume
        wp_send_json_success(array(
            'session_id' => $progress->session_id,
            'current_page' => intval($progress->current_page),
            'total_pages' => intval($progress->total_pages),
            'response_data' => is_array($progress->response_data) ? $progress->response_data : array(),
            'updated_at' => $progress->updated_at,
        ));
    }
    
    public function ajax_validate_email() {
        check_ajax_referer('oha_survey_nonce', 'nonce');
        
        $email = sanitize_email($_POST['email']);
        $survey_type = sanitize_text_field($_POST['survey_type']);
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address', 'oha-survey-hub')));
        }
        
        // Check if email already has a completed survey of this type
        $data_handler = new OHA_Survey_Data();
        $existing = $data_handler->get_completed_survey($email, $survey_type);
        
        if ($existing) {
            wp_send_json_error(array('message' => __('This email has already completed this survey', 'oha-survey-hub')));
        }
        
        wp_send_json_success();
    }
    
    // Admin page handlers
    public function admin_dashboard_page() {
        $admin = new OHA_Survey_Admin();
        $admin->render_dashboard();
    }
    
    public function admin_results_page() {
        $admin = new OHA_Survey_Admin();
        $admin->render_results();
    }
    
    public function admin_analytics_page() {
        $admin = new OHA_Survey_Admin();
        $admin->render_analytics();
    }
    
    public function admin_settings_page() {
        $admin = new OHA_Survey_Admin();
        $admin->render_settings();
    }

    public function admin_convention_page() {
        $convention = new OHA_Convention_Survey();
        $convention->render_admin_page();
    }
    
    public function activate() {
        $this->create_tables();
        OHA_Convention_Survey::create_table();
        OHA_Convention_Survey::seed_questions();
        $this->create_upload_dir();
        
        // Set default options
        add_option('oha_survey_hub_version', OHA_SURVEY_HUB_VERSION);
        add_option('oha_survey_hub_email_notifications', true);
        add_option('oha_survey_hub_require_email_confirmation', false);
        add_option('oha_survey_hub_max_save_days', 30);
        
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    private function create_tables() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Survey responses table
        $table_name = $wpdb->prefix . 'oha_survey_responses';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            survey_type varchar(50) NOT NULL,
            email varchar(100) NOT NULL,
            session_id varchar(100) NOT NULL,
            response_data longtext NOT NULL,
            current_page int(11) DEFAULT 1,
            total_pages int(11) NOT NULL,
            is_completed tinyint(1) DEFAULT 0,
            progress_percentage int(11) DEFAULT 0,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at datetime NULL,
            ip_address varchar(45),
            user_agent text,
            PRIMARY KEY (id),
            KEY email_survey_type (email, survey_type),
            KEY session_id (session_id),
            KEY survey_type (survey_type),
            KEY is_completed (is_completed),
            KEY started_at (started_at)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Survey analytics table
        $analytics_table = $wpdb->prefix . 'oha_survey_analytics';
        $sql_analytics = "CREATE TABLE $analytics_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            survey_type varchar(50) NOT NULL,
            question_id varchar(100) NOT NULL,
            answer_value text NOT NULL,
            response_count int(11) DEFAULT 1,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_response (survey_type, question_id, answer_value(100)),
            KEY survey_type (survey_type),
            KEY question_id (question_id)
        ) $charset_collate;";
        
        dbDelta($sql_analytics);
    }
    
    private function create_upload_dir() {
        $upload_dir = wp_upload_dir();
        $oha_survey_dir = $upload_dir['basedir'] . '/oha-surveys';
        
        if (!file_exists($oha_survey_dir)) {
            wp_mkdir_p($oha_survey_dir);
            
            // Create .htaccess to protect files
            $htaccess_file = $oha_survey_dir . '/.htaccess';
            if (!file_exists($htaccess_file)) {
                file_put_contents($htaccess_file, "deny from all\n");
            }
        }
    }
    
    /**
     * Handle export requests via URL
     */
    public function handle_export_request() {
        if (isset($_GET['action']) && $_GET['action'] === 'oha_survey_export' && 
            isset($_GET['nonce']) && wp_verify_nonce($_GET['nonce'], 'oha_survey_export')) {
            
            if (!current_user_can('manage_options')) {
                wp_die('Insufficient permissions');
            }
            
            $survey_type = isset($_GET['survey_type']) ? sanitize_text_field($_GET['survey_type']) : '';
            $format = isset($_GET['format']) ? sanitize_text_field($_GET['format']) : 'csv';
            
            $this->export_survey_data($survey_type, $format);
        }
    }
    
    /**
     * Export survey data
     */
    private function export_survey_data($survey_type, $format) {
        $data_handler = new OHA_Survey_Data();
        $responses = $data_handler->get_survey_responses($survey_type, 999999, 0);
        $survey = OHA_Survey_Definitions::get_survey($survey_type);
        
        if (empty($responses)) {
            wp_die('No responses found to export');
        }
        
        if ($format === 'json') {
            $this->export_json($responses, $survey, $survey_type);
        } else {
            $this->export_csv($responses, $survey, $survey_type);
        }
    }
    
    /**
     * Export as CSV
     */
    private function export_csv($responses, $survey, $survey_type) {
        $filename = 'oha-survey-' . $survey_type . '-' . date('Y-m-d-H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // Create headers
        $headers = array('ID', 'Email', 'Completed At', 'IP Address');
        
        // Add question headers
        $question_map = array();
        if ($survey && !empty($survey['pages'])) {
            foreach ($survey['pages'] as $page) {
                if (!empty($page['questions'])) {
                    foreach ($page['questions'] as $question) {
                        $question_map[$question['id']] = $question['question'];
                        $headers[] = $question['question'];
                    }
                }
            }
        }
        
        fputcsv($output, $headers);
        
        // Add response data
        foreach ($responses as $response) {
            $row = array(
                $response->id,
                $response->email,
                $response->completed_at,
                $response->ip_address
            );
            
            // Add answers
            foreach ($question_map as $question_id => $question_text) {
                $answer = isset($response->response_data[$question_id]) ? $response->response_data[$question_id] : '';
                if (is_array($answer)) {
                    $answer = implode('; ', $answer);
                }
                $row[] = $answer;
            }
            
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export as JSON
     */
    private function export_json($responses, $survey, $survey_type) {
        $filename = 'oha-survey-' . $survey_type . '-' . date('Y-m-d-H-i-s') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $export_data = array(
            'survey_type' => $survey_type,
            'survey_title' => $survey ? $survey['title'] : ucwords(str_replace('_', ' ', $survey_type)),
            'export_date' => current_time('mysql'),
            'total_responses' => count($responses),
            'responses' => array()
        );
        
        foreach ($responses as $response) {
            $export_data['responses'][] = array(
                'id' => $response->id,
                'email' => $response->email,
                'completed_at' => $response->completed_at,
                'ip_address' => $response->ip_address,
                'responses' => $response->response_data
            );
        }
        
        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * AJAX handler for CSV/JSON export
     */
    public function ajax_export_survey() {
        if (!check_ajax_referer('oha_survey_export', 'nonce', false)) {
            wp_die('Invalid nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $survey_type = isset($_GET['survey_type']) ? sanitize_text_field($_GET['survey_type']) : '';
        $format = isset($_GET['format']) ? sanitize_text_field($_GET['format']) : 'csv';

        $this->export_survey_data($survey_type, $format);
    }

    /**
     * AJAX handler for getting individual response details
     */
    public function ajax_get_response() {
        check_ajax_referer('oha_survey_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $response_id = isset($_POST['response_id']) ? intval($_POST['response_id']) : 0;
        
        if (!$response_id) {
            wp_send_json_error('Invalid response ID');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'oha_survey_responses';
        
        $response = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $response_id
        ));
        
        if (!$response) {
            wp_send_json_error('Response not found');
        }
        
        $response->response_data = json_decode($response->response_data, true);
        $survey = OHA_Survey_Definitions::get_survey($response->survey_type);
        $convention_types = [OHA_Convention_Survey::SURVEY_TYPE_ATTENDEE, OHA_Convention_Survey::SURVEY_TYPE_VENDOR, OHA_Convention_Survey::SURVEY_TYPE_VOLUNTEER];
        if (!$survey && in_array($response->survey_type, $convention_types, true)) {
            $survey = OHA_Convention_Survey::get_as_definition($response->survey_type);
        }
        
        // Format response for display
        $formatted_response = $this->format_response_for_display($response, $survey);
        
        wp_send_json_success($formatted_response);
    }
    
    /**
     * Format response for admin display
     */
    private function format_response_for_display($response, $survey) {
        $formatted = array(
            'id' => $response->id,
            'email' => $this->mask_email($response->email),
            'completed_at' => date('M j, Y g:i A', strtotime($response->completed_at)),
            'ip_address' => $response->ip_address,
            'answers' => array()
        );
        
        if ($survey && !empty($survey['pages'])) {
            foreach ($survey['pages'] as $page) {
                if (!empty($page['questions'])) {
                    foreach ($page['questions'] as $question) {
                        $question_id = $question['id'];
                        if (isset($response->response_data[$question_id])) {
                            $answer = $response->response_data[$question_id];
                            if (is_array($answer)) {
                                $answer = implode(', ', $answer);
                            }
                            
                            $formatted['answers'][] = array(
                                'question' => $question['question'],
                                'answer' => $answer,
                                'priority' => isset($question['priority']) && $question['priority'] === 'high'
                            );
                        }
                    }
                }
            }
        }
        
        return $formatted;
    }
    
    /**
     * Mask email for privacy
     */
    private function mask_email($email) {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return $email;
        
        $username = $parts[0];
        $domain = $parts[1];
        
        $masked_username = substr($username, 0, 2) . str_repeat('*', max(0, strlen($username) - 2));
        return $masked_username . '@' . $domain;
    }
    
    /**
     * AJAX handler for cleanup
     */
    public function ajax_cleanup_old_responses() {
        check_ajax_referer('oha_survey_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $max_days = get_option('oha_survey_hub_max_save_days', 30);
        $data_handler = new OHA_Survey_Data();
        $deleted = $data_handler->cleanup_old_responses($max_days);
        
        wp_send_json_success(array('deleted' => $deleted));
    }
    
    /**
     * AJAX handler for getting stats
     */
    public function ajax_get_stats() {
        check_ajax_referer('oha_survey_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $data_handler = new OHA_Survey_Data();
        $stats = $data_handler->get_survey_stats();
        
        wp_send_json_success($stats);
    }
}

// Initialize the plugin
function oha_survey_hub() {
    return OHA_Survey_Hub::get_instance();
}

// Start the plugin
oha_survey_hub();
