<?php
/**
 * Survey Data Handler
 * Manages all database operations for survey responses and analytics
 */

if (!defined('ABSPATH')) {
    exit;
}

class OHA_Survey_Data {
    
    private $responses_table;
    private $analytics_table;
    
    public function __construct() {
        global $wpdb;
        $this->responses_table = $wpdb->prefix . 'oha_survey_responses';
        $this->analytics_table = $wpdb->prefix . 'oha_survey_analytics';
    }
    
    /**
     * Save survey progress
     */
    public function save_progress($data) {
        global $wpdb;
        
        $survey_type = sanitize_text_field($data['survey_type']);
        $email = sanitize_email($data['email']);
        $session_id = sanitize_text_field($data['session_id']);
        $response_data = wp_json_encode($data['responses']);
        $current_page = intval($data['current_page']);
        $total_pages = intval($data['total_pages']);
        $progress_percentage = round(($current_page / $total_pages) * 100);
        
        // Check if this session already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->responses_table} WHERE session_id = %s AND survey_type = %s",
            $session_id, $survey_type
        ));
        
        $data_array = array(
            'response_data' => $response_data,
            'current_page' => $current_page,
            'total_pages' => $total_pages,
            'progress_percentage' => $progress_percentage,
            'updated_at' => current_time('mysql'),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
        );
        
        if ($existing) {
            // Update existing record
            $result = $wpdb->update(
                $this->responses_table,
                $data_array,
                array('id' => $existing->id),
                array('%s', '%d', '%d', '%d', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // Insert new record (explicit key order so wpdb formats map correctly)
            $insert_data = array(
                'survey_type' => $survey_type,
                'email' => $email,
                'session_id' => $session_id,
                'is_completed' => 0,
                'response_data' => $response_data,
                'current_page' => $current_page,
                'total_pages' => $total_pages,
                'progress_percentage' => $progress_percentage,
                'started_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            );

            $result = $wpdb->insert(
                $this->responses_table,
                $insert_data,
                array('%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s')
            );
        }
        
        return $result !== false;
    }
    
    /**
     * Submit completed survey
     */
    public function submit_survey($data) {
        global $wpdb;
        
        $survey_type = sanitize_text_field($data['survey_type']);
        $email = sanitize_email($data['email']);
        $session_id = sanitize_text_field($data['session_id']);
        $response_data = wp_json_encode($data['responses']);
        $total_pages = intval($data['total_pages']);
        
        // Check if this email already has a completed survey of this type
        $existing_completed = $this->get_completed_survey($email, $survey_type);
        if ($existing_completed) {
            return array('error' => __('This email has already completed this survey', 'oha-survey-hub'));
        }
        
        // Mark as completed
        $data_array = array(
            'survey_type' => $survey_type,
            'email' => $email,
            'session_id' => $session_id,
            'response_data' => $response_data,
            'current_page' => $total_pages,
            'total_pages' => $total_pages,
            'is_completed' => 1,
            'progress_percentage' => 100,
            'completed_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
        );
        
        // Check if session exists, update or insert
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->responses_table} WHERE session_id = %s AND survey_type = %s",
            $session_id, $survey_type
        ));
        
        if ($existing) {
            $result = $wpdb->update(
                $this->responses_table,
                $data_array,
                array('id' => $existing->id),
                array('%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            $data_array['started_at'] = current_time('mysql');
            $result = $wpdb->insert(
                $this->responses_table,
                $data_array,
                array('%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s')
            );
        }
        
        if ($result !== false) {
            // Update analytics
            $this->update_analytics($survey_type, json_decode($response_data, true));
            
            // Send notification email if enabled
            $this->send_completion_notification($email, $survey_type);
            
            return array('success' => true);
        }
        
        return array('error' => __('Failed to submit survey', 'oha-survey-hub'));
    }
    
    /**
     * Get survey progress for resuming
     */
    public function get_survey_progress($email, $survey_type) {
        global $wpdb;
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->responses_table} 
             WHERE email = %s AND survey_type = %s AND is_completed = 0
             ORDER BY updated_at DESC LIMIT 1",
            $email, $survey_type
        ));
        
        if ($result) {
            $result->response_data = json_decode($result->response_data, true);
            return $result;
        }
        
        return null;
    }
    
    /**
     * Check if email has completed survey
     */
    public function get_completed_survey($email, $survey_type) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->responses_table} 
             WHERE email = %s AND survey_type = %s AND is_completed = 1",
            $email, $survey_type
        ));
    }
    
    /**
     * Get all responses for a survey type
     */
    public function get_survey_responses($survey_type, $limit = null, $offset = 0) {
        global $wpdb;
        
        $sql = "SELECT * FROM {$this->responses_table} WHERE survey_type = %s AND is_completed = 1 ORDER BY completed_at DESC";
        
        if ($limit) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        }
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $survey_type));
        
        foreach ($results as &$result) {
            $result->response_data = json_decode($result->response_data, true);
        }
        
        return $results;
    }
    
    /**
     * Get response count for survey type
     */
    public function get_response_count($survey_type) {
        global $wpdb;
        
        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->responses_table} WHERE survey_type = %s AND is_completed = 1",
            $survey_type
        )));
    }
    
    /**
     * Get survey statistics
     */
    public function get_survey_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Overall counts
        $stats['total_responses'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->responses_table} WHERE is_completed = 1"
        ));
        
        $stats['in_progress'] = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->responses_table} WHERE is_completed = 0"
        ));
        
        // By survey type — hardcoded non-convention types plus any convention_YYYY types found in DB
        $convention_types_in_db = $wpdb->get_col(
            "SELECT DISTINCT survey_type FROM {$this->responses_table} WHERE survey_type LIKE 'convention_%'"
        );
        $survey_types = array_merge(
            array('member', 'haunt_owner', 'scare_actor', 'behind_scenes', 'home_haunt', 'industry_deep_dive'),
            $convention_types_in_db
        );
        $stats['by_type'] = array();

        foreach ($survey_types as $type) {
            $completed = $this->get_response_count($type);
            $in_progress = intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->responses_table} WHERE survey_type = %s AND is_completed = 0",
                $type
            )));
            if ($completed === 0 && $in_progress === 0) continue;
            $stats['by_type'][$type] = array(
                'completed'   => $completed,
                'in_progress' => $in_progress,
            );
        }
        
        // Recent activity (last 7 days)
        $stats['recent_activity'] = $wpdb->get_results(
            "SELECT DATE(completed_at) as date, COUNT(*) as count 
             FROM {$this->responses_table} 
             WHERE is_completed = 1 AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(completed_at)
             ORDER BY date ASC"
        );
        
        return $stats;
    }
    
    /**
     * Update analytics data
     */
    private function update_analytics($survey_type, $responses) {
        global $wpdb;
        
        foreach ($responses as $question_id => $answer) {
            if (empty($answer)) continue;
            
            // Handle different answer types
            if (is_array($answer)) {
                // Multiple choice answers
                foreach ($answer as $value) {
                    $this->increment_analytics($survey_type, $question_id, sanitize_text_field($value));
                }
            } else {
                // Single answers
                $this->increment_analytics($survey_type, $question_id, sanitize_text_field($answer));
            }
        }
    }
    
    /**
     * Increment analytics counter
     */
    private function increment_analytics($survey_type, $question_id, $answer_value) {
        global $wpdb;
        
        // Truncate long text responses for analytics
        if (strlen($answer_value) > 100) {
            $answer_value = substr($answer_value, 0, 97) . '...';
        }
        
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->analytics_table} (survey_type, question_id, answer_value, response_count) 
             VALUES (%s, %s, %s, 1) 
             ON DUPLICATE KEY UPDATE response_count = response_count + 1",
            $survey_type, $question_id, $answer_value
        ));
    }
    
    /**
     * Get analytics data for a survey type
     */
    public function get_analytics($survey_type, $question_id = null) {
        global $wpdb;
        
        $sql = "SELECT * FROM {$this->analytics_table} WHERE survey_type = %s";
        $params = array($survey_type);
        
        if ($question_id) {
            $sql .= " AND question_id = %s";
            $params[] = $question_id;
        }
        
        $sql .= " ORDER BY question_id, response_count DESC";
        
        return $wpdb->get_results($wpdb->prepare($sql, ...$params));
    }
    
    /**
     * Export survey data
     */
    public function export_survey_data($survey_type, $format = 'csv') {
        $responses = $this->get_survey_responses($survey_type);
        
        if (empty($responses)) {
            return false;
        }
        
        // Get survey definition to get question labels
        $survey = OHA_Survey_Definitions::get_survey($survey_type);
        $question_map = array();
        
        foreach ($survey['pages'] as $page) {
            foreach ($page['questions'] as $question) {
                $question_map[$question['id']] = $question['question'];
            }
        }
        
        if ($format === 'csv') {
            return $this->export_to_csv($responses, $question_map);
        } elseif ($format === 'json') {
            return $this->export_to_json($responses, $question_map);
        }
        
        return false;
    }
    
    /**
     * Export to CSV
     */
    private function export_to_csv($responses, $question_map) {
        $filename = 'oha-survey-export-' . date('Y-m-d-H-i-s') . '.csv';
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/oha-surveys/' . $filename;
        
        $file = fopen($file_path, 'w');
        
        if (!$file) {
            return false;
        }
        
        // Get all unique question IDs
        $all_questions = array();
        foreach ($responses as $response) {
            $all_questions = array_merge($all_questions, array_keys($response->response_data));
        }
        $all_questions = array_unique($all_questions);
        
        // CSV Headers
        $headers = array('ID', 'Email', 'Completed At', 'IP Address');
        foreach ($all_questions as $q_id) {
            $headers[] = isset($question_map[$q_id]) ? $question_map[$q_id] : $q_id;
        }
        fputcsv($file, $headers);
        
        // Data rows
        foreach ($responses as $response) {
            $row = array(
                $response->id,
                $response->email,
                $response->completed_at,
                $response->ip_address
            );
            
            foreach ($all_questions as $q_id) {
                $value = isset($response->response_data[$q_id]) ? $response->response_data[$q_id] : '';
                if (is_array($value)) {
                    $value = implode('; ', $value);
                }
                $row[] = $value;
            }
            
            fputcsv($file, $row);
        }
        
        fclose($file);
        
        return array(
            'file_path' => $file_path,
            'filename' => $filename,
            'url' => $upload_dir['baseurl'] . '/oha-surveys/' . $filename
        );
    }
    
    /**
     * Export to JSON
     */
    private function export_to_json($responses, $question_map) {
        $filename = 'oha-survey-export-' . date('Y-m-d-H-i-s') . '.json';
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/oha-surveys/' . $filename;
        
        $export_data = array(
            'export_date' => current_time('mysql'),
            'question_map' => $question_map,
            'responses' => $responses
        );
        
        $result = file_put_contents($file_path, wp_json_encode($export_data, JSON_PRETTY_PRINT));
        
        if ($result === false) {
            return false;
        }
        
        return array(
            'file_path' => $file_path,
            'filename' => $filename,
            'url' => $upload_dir['baseurl'] . '/oha-surveys/' . $filename
        );
    }
    
    /**
     * Send completion notification email
     */
    private function send_completion_notification($email, $survey_type) {
        if (!get_option('oha_survey_hub_email_notifications', true)) {
            return;
        }
        
        $survey = OHA_Survey_Definitions::get_survey($survey_type);
        $survey_title = $survey ? $survey['title'] : ucfirst($survey_type) . ' Survey';
        
        $subject = sprintf(__('[OHA] Survey Completed: %s', 'oha-survey-hub'), $survey_title);
        
        $message = sprintf(
            __("Thank you for completing the %s!\n\nYour responses help OHA better serve the Ohio haunt community. All responses are kept anonymous and will only be used in aggregate for industry insights and planning.\n\nBest regards,\nOhio Haunters Association", 'oha-survey-hub'),
            $survey_title
        );
        
        // Send to respondent
        wp_mail($email, $subject, $message);
        
        // Notify admin if enabled
        if (get_option('oha_survey_hub_admin_notifications', true)) {
            $admin_email = get_option('admin_email');
            $admin_subject = sprintf(__('[OHA Survey] New Response: %s', 'oha-survey-hub'), $survey_title);
            $admin_message = sprintf(
                __("A new response has been submitted for the %s.\n\nView responses in the admin dashboard: %s", 'oha-survey-hub'),
                $survey_title,
                admin_url('admin.php?page=oha-survey-results')
            );
            
            wp_mail($admin_email, $admin_subject, $admin_message);
        }
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Clean up old incomplete responses
     */
    public function cleanup_old_responses() {
        global $wpdb;

        $days_to_keep = get_option('oha_survey_hub_max_save_days', 30);

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->responses_table}
             WHERE is_completed = 0 AND updated_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days_to_keep
        ));

        return $deleted;
    }

    // =========================================================================
    // On-demand Analytics Rollup
    // =========================================================================

    /**
     * Per-question answer frequency computed directly from raw response blobs.
     * Values longer than 200 chars (open text) are skipped — use get_text_responses().
     *
     * @return array [ question_id => [ answer_value => int count ] ]
     */
    public function get_rollup( $survey_type, $year = null ) {
        global $wpdb;
        $rows = $year
            ? $wpdb->get_results( $wpdb->prepare(
                "SELECT response_data FROM {$this->responses_table}
                 WHERE survey_type = %s AND is_completed = 1 AND YEAR(completed_at) = %d",
                $survey_type, (int) $year
            ) )
            : $wpdb->get_results( $wpdb->prepare(
                "SELECT response_data FROM {$this->responses_table}
                 WHERE survey_type = %s AND is_completed = 1",
                $survey_type
            ) );

        $rollup = [];
        foreach ( $rows as $row ) {
            $data = json_decode( $row->response_data, true );
            if ( ! is_array( $data ) ) continue;
            foreach ( $data as $qid => $val ) {
                if ( $val === '' || $val === null || $val === [] ) continue;
                if ( ! isset( $rollup[ $qid ] ) ) $rollup[ $qid ] = [];
                if ( is_array( $val ) ) {
                    foreach ( $val as $v ) {
                        $v = (string) $v;
                        if ( strlen( $v ) > 200 ) continue;
                        $rollup[ $qid ][ $v ] = ( $rollup[ $qid ][ $v ] ?? 0 ) + 1;
                    }
                } elseif ( strlen( (string) $val ) <= 200 ) {
                    $v = (string) $val;
                    $rollup[ $qid ][ $v ] = ( $rollup[ $qid ][ $v ] ?? 0 ) + 1;
                }
            }
        }
        return $rollup;
    }

    /**
     * Full open-text entries for one question. Returns newest-first array of
     * [ 'text' => string, 'date' => 'Y-m-d H:i:s' ].
     */
    public function get_text_responses( $survey_type, $question_id, $year = null ) {
        global $wpdb;
        $rows = $year
            ? $wpdb->get_results( $wpdb->prepare(
                "SELECT response_data, completed_at FROM {$this->responses_table}
                 WHERE survey_type = %s AND is_completed = 1 AND YEAR(completed_at) = %d
                 ORDER BY completed_at DESC",
                $survey_type, (int) $year
            ) )
            : $wpdb->get_results( $wpdb->prepare(
                "SELECT response_data, completed_at FROM {$this->responses_table}
                 WHERE survey_type = %s AND is_completed = 1
                 ORDER BY completed_at DESC",
                $survey_type
            ) );

        $texts = [];
        foreach ( $rows as $row ) {
            $data = json_decode( $row->response_data, true );
            $val  = $data[ $question_id ] ?? '';
            if ( is_string( $val ) && strlen( trim( $val ) ) > 0 ) {
                $texts[] = [ 'text' => $val, 'date' => $row->completed_at ];
            }
        }
        return $texts;
    }

    /**
     * Distinct calendar years that have completed responses, newest first.
     */
    public function get_survey_years( $survey_type ) {
        global $wpdb;
        return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT YEAR(completed_at)
             FROM {$this->responses_table}
             WHERE survey_type = %s AND is_completed = 1
             ORDER BY 1 DESC",
            $survey_type
        ) ) );
    }

    /**
     * Completed response count for a specific year.
     */
    public function get_count_for_year( $survey_type, $year ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->responses_table}
             WHERE survey_type = %s AND is_completed = 1 AND YEAR(completed_at) = %d",
            $survey_type, (int) $year
        ) );
    }

    /**
     * Response count per day for one survey type over the last N days.
     */
    public function get_timeline( $survey_type, $days = 30 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(completed_at) AS `date`, COUNT(*) AS count
             FROM {$this->responses_table}
             WHERE survey_type = %s AND is_completed = 1
               AND completed_at >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
             GROUP BY DATE(completed_at)
             ORDER BY `date` ASC",
            $survey_type, (int) $days
        ) );
    }

    /**
     * Response count per day across ALL survey types for the last N days.
     */
    public function get_all_timeline( $days = 30 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(completed_at) AS `date`, COUNT(*) AS count
             FROM {$this->responses_table}
             WHERE is_completed = 1
               AND completed_at >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
             GROUP BY DATE(completed_at)
             ORDER BY `date` ASC",
            (int) $days
        ) );
    }

    /**
     * Responses completed in the current calendar month.
     */
    public function get_this_month_count( $survey_type ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->responses_table}
             WHERE survey_type = %s AND is_completed = 1
               AND YEAR(completed_at) = YEAR(CURDATE())
               AND MONTH(completed_at) = MONTH(CURDATE())",
            $survey_type
        ) );
    }

    /**
     * Completion rate = completed / total started, as integer percent.
     */
    public function get_completion_rate( $survey_type ) {
        global $wpdb;
        $total     = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->responses_table} WHERE survey_type = %s",
            $survey_type
        ) );
        $completed = $this->get_response_count( $survey_type );
        return $total > 0 ? (int) round( $completed / $total * 100 ) : 0;
    }
}
