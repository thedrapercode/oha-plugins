<?php
/**
 * Survey Admin Interface
 * Manages the admin dashboard, results viewing, and analytics
 */

if (!defined('ABSPATH')) {
    exit;
}

class OHA_Survey_Admin {
    
    private $data_handler;
    
    public function __construct() {
        $this->data_handler = new OHA_Survey_Data();
    }
    
    /**
     * Render admin dashboard
     */
    public function render_dashboard() {
        $stats = $this->data_handler->get_survey_stats();
        ?>
        <div class="wrap oha-survey-admin">
            <h1><?php _e('Survey Hub Dashboard', 'oha-survey-hub'); ?></h1>
            
            <div class="oha-admin-grid">
                <div class="oha-admin-card oha-stats-overview">
                    <h2><?php _e('Overview', 'oha-survey-hub'); ?></h2>
                    <div class="oha-stats-grid">
                        <div class="oha-stat-item">
                            <div class="oha-stat-number"><?php echo number_format($stats['total_responses']); ?></div>
                            <div class="oha-stat-label"><?php _e('Total Responses', 'oha-survey-hub'); ?></div>
                        </div>
                        <div class="oha-stat-item">
                            <div class="oha-stat-number"><?php echo number_format($stats['in_progress']); ?></div>
                            <div class="oha-stat-label"><?php _e('In Progress', 'oha-survey-hub'); ?></div>
                        </div>
                        <div class="oha-stat-item">
                            <div class="oha-stat-number"><?php echo count($stats['recent_activity']); ?></div>
                            <div class="oha-stat-label"><?php _e('Active Days (7d)', 'oha-survey-hub'); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="oha-admin-card oha-survey-types">
                    <h2><?php _e('Survey Response Counts', 'oha-survey-hub'); ?></h2>
                    <div class="oha-survey-list">
                        <?php foreach ($stats['by_type'] as $type => $counts): ?>
                            <div class="oha-survey-item">
                                <div class="oha-survey-name">
                                    <?php 
                                    $survey = OHA_Survey_Definitions::get_survey($type);
                                    echo $survey ? esc_html($survey['title']) : esc_html(ucfirst(str_replace('_', ' ', $type)));
                                    ?>
                                </div>
                                <div class="oha-survey-counts">
                                    <span class="oha-completed"><?php echo number_format($counts['completed']); ?> completed</span>
                                    <span class="oha-in-progress"><?php echo number_format($counts['in_progress']); ?> in progress</span>
                                </div>
                                <div class="oha-survey-actions">
                                    <a href="<?php echo admin_url('admin.php?page=oha-survey-results&survey_type=' . $type); ?>" class="button button-small">
                                        <?php _e('View Responses', 'oha-survey-hub'); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="oha-admin-grid">
                <div class="oha-admin-card oha-recent-activity">
                    <h2><?php _e('Recent Activity', 'oha-survey-hub'); ?></h2>
                    <?php if (!empty($stats['recent_activity'])): ?>
                        <canvas id="activityChart" width="400" height="200"></canvas>
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const ctx = document.getElementById('activityChart').getContext('2d');
                            const data = <?php echo wp_json_encode($stats['recent_activity']); ?>;
                            
                            new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: data.map(item => item.date),
                                    datasets: [{
                                        label: 'Responses per day',
                                        data: data.map(item => item.count),
                                        borderColor: '#f39200',
                                        backgroundColor: 'rgba(243, 146, 0, 0.1)',
                                        tension: 0.1
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            ticks: {
                                                stepSize: 1
                                            }
                                        }
                                    }
                                }
                            });
                        });
                        </script>
                    <?php else: ?>
                        <p><?php _e('No recent activity to display.', 'oha-survey-hub'); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="oha-admin-card oha-quick-actions">
                    <h2><?php _e('Quick Actions', 'oha-survey-hub'); ?></h2>
                    <div class="oha-action-list">
                        <a href="<?php echo admin_url('admin.php?page=oha-survey-results'); ?>" class="oha-action-btn">
                            <span class="dashicons dashicons-chart-area"></span>
                            <?php _e('View All Results', 'oha-survey-hub'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=oha-survey-analytics'); ?>" class="oha-action-btn">
                            <span class="dashicons dashicons-chart-bar"></span>
                            <?php _e('Analytics Dashboard', 'oha-survey-hub'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=oha-survey-settings'); ?>" class="oha-action-btn">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <?php _e('Settings', 'oha-survey-hub'); ?>
                        </a>
                        <button type="button" class="oha-action-btn" onclick="ohaCleanupOldResponses()">
                            <span class="dashicons dashicons-trash"></span>
                            <?php _e('Cleanup Old Data', 'oha-survey-hub'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="oha-admin-card oha-shortcode-reference">
                <h2><?php _e('Shortcode Reference', 'oha-survey-hub'); ?></h2>
                <div class="oha-shortcode-grid">
                    <div class="oha-shortcode-item">
                        <code>[oha_survey_selector]</code>
                        <p><?php _e('Survey selection page with context and trust-building information', 'oha-survey-hub'); ?></p>
                    </div>
                    <div class="oha-shortcode-item">
                        <code>[oha_survey_member]</code>
                        <p><?php _e('OHA Member survey', 'oha-survey-hub'); ?></p>
                    </div>
                    <div class="oha-shortcode-item">
                        <code>[oha_survey_scare_actor]</code>
                        <p><?php _e('Scare Actor & Crew survey', 'oha-survey-hub'); ?></p>
                    </div>
                    <div class="oha-shortcode-item">
                        <code>[oha_survey_behind_scenes]</code>
                        <p><?php _e('Behind-the-Scenes & Technical Crew survey', 'oha-survey-hub'); ?></p>
                    </div>
                    <div class="oha-shortcode-item">
                        <code>[oha_survey_home_haunt]</code>
                        <p><?php _e('Home Haunt survey', 'oha-survey-hub'); ?></p>
                    </div>
                    <div class="oha-shortcode-item">
                        <code>[oha_survey_industry_deep_dive]</code>
                        <p><?php _e('Industry Deep-Dive survey for haunt owners', 'oha-survey-hub'); ?></p>
                    </div>
                </div>
                <p><strong><?php _e('Direct Link Usage:', 'oha-survey-hub'); ?></strong> <?php _e('Add', 'oha-survey-hub'); ?> <code>direct_link="true"</code> <?php _e('to bypass the selector page', 'oha-survey-hub'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render results page
     */
    public function render_results() {
        $survey_type = isset($_GET['survey_type']) ? sanitize_text_field($_GET['survey_type']) : '';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 50;
        
        ?>
        <div class="wrap oha-survey-admin">
            <h1><?php _e('Survey Results', 'oha-survey-hub'); ?></h1>
            
            <div class="oha-survey-filter">
                <form method="get" action="">
                    <input type="hidden" name="page" value="oha-survey-results">
                    <select name="survey_type" onchange="this.form.submit()">
                        <option value=""><?php _e('All Surveys', 'oha-survey-hub'); ?></option>
                        <?php
                        $surveys = OHA_Survey_Definitions::get_all_surveys();
                        foreach ($surveys as $type => $survey_data) {
                            $selected = $survey_type === $type ? 'selected' : '';
                            echo '<option value="' . esc_attr($type) . '" ' . $selected . '>' . esc_html($survey_data['title']) . '</option>';
                        }
                        $convention_options = [
                            OHA_Convention_Survey::SURVEY_TYPE_ATTENDEE  => 'OHA Convention 2026 - Attendee Feedback',
                            OHA_Convention_Survey::SURVEY_TYPE_VENDOR    => 'OHA Convention 2026 - Vendor Feedback',
                            OHA_Convention_Survey::SURVEY_TYPE_VOLUNTEER => 'OHA Convention 2026 - Volunteer Feedback',
                        ];
                        foreach ($convention_options as $type => $title) {
                            $selected = $survey_type === $type ? 'selected' : '';
                            echo '<option value="' . esc_attr($type) . '" ' . $selected . '>' . esc_html($title) . '</option>';
                        }
                        ?>
                    </select>
                    <button type="button" class="button" onclick="ohaExportSurveyData('<?php echo esc_js($survey_type); ?>', 'csv')">
                        <?php _e('Export CSV', 'oha-survey-hub'); ?>
                    </button>
                    <button type="button" class="button" onclick="ohaExportSurveyData('<?php echo esc_js($survey_type); ?>', 'json')">
                        <?php _e('Export JSON', 'oha-survey-hub'); ?>
                    </button>
                </form>
            </div>
            
            <?php if ($survey_type): ?>
                <?php $this->render_survey_responses($survey_type, $page, $per_page); ?>
            <?php else: ?>
                <?php $this->render_all_surveys_summary(); ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render survey responses for a specific type
     */
    private function render_survey_responses($survey_type, $page, $per_page) {
        $offset = ($page - 1) * $per_page;
        $responses = $this->data_handler->get_survey_responses($survey_type, $per_page, $offset);
        $total_count = $this->data_handler->get_response_count($survey_type);
        $total_pages = ceil($total_count / $per_page);
        
        $survey = OHA_Survey_Definitions::get_survey($survey_type);
        if (!$survey) {
            $convention_types = [
                OHA_Convention_Survey::SURVEY_TYPE_ATTENDEE,
                OHA_Convention_Survey::SURVEY_TYPE_VENDOR,
                OHA_Convention_Survey::SURVEY_TYPE_VOLUNTEER,
            ];
            if (in_array($survey_type, $convention_types, true)) {
                $survey = OHA_Convention_Survey::get_as_definition($survey_type);
            }
        }

        ?>
        <div class="oha-survey-responses">
            <h2><?php echo $survey ? esc_html($survey['title']) : esc_html(ucfirst(str_replace('_', ' ', $survey_type))); ?></h2>
            <p><?php printf(__('Showing %d-%d of %d responses', 'oha-survey-hub'), 
                ($offset + 1), 
                min($offset + $per_page, $total_count), 
                $total_count); ?></p>
            
            <?php if (!empty($responses)): ?>
                <div class="oha-responses-table">
                    <?php foreach ($responses as $response): ?>
                        <div class="oha-response-card" data-response-id="<?php echo esc_attr($response->id); ?>">
                            <div class="oha-response-header">
                                <div class="oha-response-meta">
                                    <span class="oha-response-id">#<?php echo $response->id; ?></span>
                                    <span class="oha-response-date"><?php echo date('M j, Y g:i A', strtotime($response->completed_at)); ?></span>
                                    <span class="oha-response-email"><?php echo esc_html($this->mask_email($response->email)); ?></span>
                                </div>
                                <button type="button" class="oha-toggle-response" data-response-id="<?php echo esc_attr($response->id); ?>">
                                    <?php _e('View Details', 'oha-survey-hub'); ?>
                                </button>
                            </div>
                            <div class="oha-response-content" id="oha-response-<?php echo $response->id; ?>" style="display: none;">
                                <?php $this->render_response_details($response, $survey); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="oha-pagination">
                        <?php
                        $base_url = admin_url('admin.php?page=oha-survey-results&survey_type=' . $survey_type);
                        for ($i = 1; $i <= $total_pages; $i++) {
                            $class = $i === $page ? 'current' : '';
                            $url = $i === 1 ? $base_url : $base_url . '&paged=' . $i;
                            echo '<a href="' . esc_url($url) . '" class="' . $class . '">' . $i . '</a>';
                        }
                        ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p><?php _e('No responses found for this survey.', 'oha-survey-hub'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render response details
     */
    private function render_response_details($response, $survey) {
        if (!$survey || empty($survey['pages'])) {
            return;
        }
        
        // Create question map
        $question_map = array();
        foreach ($survey['pages'] as $page) {
            if (empty($page['questions'])) continue;
            foreach ($page['questions'] as $question) {
                $question_map[$question['id']] = $question;
            }
        }
        
        ?>
        <div class="oha-response-answers">
            <?php foreach ($response->response_data as $question_id => $answer): ?>
                <?php if (isset($question_map[$question_id])): ?>
                    <div class="oha-answer-item">
                        <div class="oha-answer-question">
                            <?php echo esc_html($question_map[$question_id]['question']); ?>
                            <?php if (isset($question_map[$question_id]['priority']) && $question_map[$question_id]['priority'] === 'high'): ?>
                                <span class="oha-priority-badge">★</span>
                            <?php endif; ?>
                        </div>
                        <div class="oha-answer-value">
                            <?php
                            if (is_array($answer)) {
                                echo esc_html(implode(', ', $answer));
                            } else {
                                echo esc_html($answer);
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Render all surveys summary
     */
    private function render_all_surveys_summary() {
        $stats = $this->data_handler->get_survey_stats();
        ?>
        <div class="oha-all-surveys-summary">
            <h2><?php _e('All Survey Results', 'oha-survey-hub'); ?></h2>
            <div class="oha-survey-grid">
                <?php foreach ($stats['by_type'] as $type => $counts): ?>
                    <?php $survey = OHA_Survey_Definitions::get_survey($type); ?>
                    <div class="oha-survey-summary-card">
                        <h3><?php echo $survey ? esc_html($survey['title']) : esc_html(ucfirst(str_replace('_', ' ', $type))); ?></h3>
                        <div class="oha-summary-stats">
                            <div class="oha-stat">
                                <span class="number"><?php echo number_format($counts['completed']); ?></span>
                                <span class="label"><?php _e('Completed', 'oha-survey-hub'); ?></span>
                            </div>
                            <div class="oha-stat">
                                <span class="number"><?php echo number_format($counts['in_progress']); ?></span>
                                <span class="label"><?php _e('In Progress', 'oha-survey-hub'); ?></span>
                            </div>
                        </div>
                        <div class="oha-summary-actions">
                            <a href="<?php echo admin_url('admin.php?page=oha-survey-results&survey_type=' . $type); ?>" class="button button-primary">
                                <?php _e('View Responses', 'oha-survey-hub'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=oha-survey-analytics&survey_type=' . $type); ?>" class="button">
                                <?php _e('Analytics', 'oha-survey-hub'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render analytics page
     */
    public function render_analytics() {
        $survey_type = isset($_GET['survey_type']) ? sanitize_text_field($_GET['survey_type']) : '';
        
        ?>
        <div class="wrap oha-survey-admin">
            <h1><?php _e('Survey Analytics', 'oha-survey-hub'); ?></h1>
            
            <div class="oha-survey-filter">
                <form method="get" action="">
                    <input type="hidden" name="page" value="oha-survey-analytics">
                    <select name="survey_type" onchange="this.form.submit()">
                        <option value=""><?php _e('Select Survey Type', 'oha-survey-hub'); ?></option>
                        <?php
                        $surveys = OHA_Survey_Definitions::get_all_surveys();
                        foreach ($surveys as $type => $survey_data) {
                            $selected = $survey_type === $type ? 'selected' : '';
                            echo '<option value="' . esc_attr($type) . '" ' . $selected . '>' . esc_html($survey_data['title']) . '</option>';
                        }
                        ?>
                    </select>
                </form>
            </div>
            
            <?php if ($survey_type): ?>
                <?php $this->render_survey_analytics($survey_type); ?>
            <?php else: ?>
                <p><?php _e('Please select a survey type to view analytics.', 'oha-survey-hub'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render analytics for specific survey
     */
    private function render_survey_analytics($survey_type) {
        $analytics = $this->data_handler->get_analytics($survey_type);
        $survey = OHA_Survey_Definitions::get_survey($survey_type);
        
        if (empty($analytics)) {
            echo '<p>' . __('No analytics data available for this survey.', 'oha-survey-hub') . '</p>';
            return;
        }
        
        // Group analytics by question
        $grouped_analytics = array();
        foreach ($analytics as $item) {
            $grouped_analytics[$item->question_id][] = $item;
        }
        
        ?>
        <div class="oha-analytics-content">
            <h2><?php echo $survey ? esc_html($survey['title']) . ' ' : ''; ?><?php _e('Analytics', 'oha-survey-hub'); ?></h2>
            
            <?php foreach ($grouped_analytics as $question_id => $question_data): ?>
                <?php $question = $this->find_question_in_survey($survey, $question_id); ?>
                <div class="oha-analytics-question">
                    <h3><?php echo $question ? esc_html($question['question']) : esc_html($question_id); ?></h3>
                    
                    <div class="oha-analytics-chart">
                        <canvas id="chart-<?php echo esc_attr($question_id); ?>" width="400" height="200"></canvas>
                    </div>
                    
                    <div class="oha-analytics-table">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Answer', 'oha-survey-hub'); ?></th>
                                    <th><?php _e('Count', 'oha-survey-hub'); ?></th>
                                    <th><?php _e('Percentage', 'oha-survey-hub'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_responses = array_sum(array_column($question_data, 'response_count'));
                                foreach ($question_data as $data): 
                                    $percentage = $total_responses > 0 ? round(($data->response_count / $total_responses) * 100, 1) : 0;
                                ?>
                                    <tr>
                                        <td><?php echo esc_html($data->answer_value); ?></td>
                                        <td><?php echo number_format($data->response_count); ?></td>
                                        <td><?php echo $percentage; ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const ctx = document.getElementById('chart-<?php echo esc_js($question_id); ?>').getContext('2d');
                    const data = <?php echo wp_json_encode($question_data); ?>;
                    
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: data.map(item => item.answer_value.length > 30 ? item.answer_value.substring(0, 30) + '...' : item.answer_value),
                            datasets: [{
                                data: data.map(item => item.response_count),
                                backgroundColor: [
                                    '#f39200', '#1e1e1e', '#666666', '#999999', '#cccccc', 
                                    '#ff6b35', '#004e89', '#ffc857', '#8ac4d0', '#bc6c25'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                });
                </script>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings() {
        // Handle form submission
        if (isset($_POST['save_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'oha_survey_settings')) {
            update_option('oha_survey_hub_support_email', sanitize_email($_POST['support_email']));
            update_option('oha_survey_hub_email_notifications', isset($_POST['email_notifications']));
            update_option('oha_survey_hub_admin_notifications', isset($_POST['admin_notifications']));
            update_option('oha_survey_hub_require_email_confirmation', isset($_POST['require_email_confirmation']));
            update_option('oha_survey_hub_max_save_days', intval($_POST['max_save_days']));
            update_option('oha_convention_board_email', sanitize_email($_POST['convention_board_email']));
            echo '<div class="notice notice-success"><p>' . __('Settings saved!', 'oha-survey-hub') . '</p></div>';
        }

        $support_email        = get_option('oha_survey_hub_support_email', 'info@ohiohaunt.com');
        $email_notifications  = get_option('oha_survey_hub_email_notifications', true);
        $admin_notifications  = get_option('oha_survey_hub_admin_notifications', true);
        $require_confirmation = get_option('oha_survey_hub_require_email_confirmation', false);
        $max_save_days        = get_option('oha_survey_hub_max_save_days', 30);
        $convention_board_email = get_option('oha_convention_board_email', '');
        
        ?>
        <div class="wrap oha-survey-admin">
            <h1><?php _e('Survey Hub Settings', 'oha-survey-hub'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('oha_survey_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Support Email Address', 'oha-survey-hub'); ?></th>
                        <td>
                            <input type="email" name="support_email" value="<?php echo esc_attr($support_email); ?>" class="regular-text" required>
                            <p class="description"><?php _e('Email address shown in survey help text and footer information.', 'oha-survey-hub'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Email Notifications', 'oha-survey-hub'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="email_notifications" value="1" <?php checked($email_notifications); ?>>
                                    <?php _e('Send confirmation emails to survey respondents', 'oha-survey-hub'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="admin_notifications" value="1" <?php checked($admin_notifications); ?>>
                                    <?php _e('Send notifications to admin when surveys are completed', 'oha-survey-hub'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Email Confirmation', 'oha-survey-hub'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="require_email_confirmation" value="1" <?php checked($require_confirmation); ?>>
                                <?php _e('Require email confirmation before allowing survey submission', 'oha-survey-hub'); ?>
                            </label>
                            <p class="description"><?php _e('When enabled, users must verify their email before they can complete surveys.', 'oha-survey-hub'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Convention Board Notification Email', 'oha-survey-hub'); ?></th>
                        <td>
                            <input type="email" name="convention_board_email" value="<?php echo esc_attr($convention_board_email); ?>" class="regular-text" placeholder="board@ohiohaunt.com">
                            <p class="description"><?php _e('Email address that receives a notification each time a convention survey is submitted. Leave blank to use the WordPress admin email.', 'oha-survey-hub'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Data Retention', 'oha-survey-hub'); ?></th>
                        <td>
                            <input type="number" name="max_save_days" value="<?php echo esc_attr($max_save_days); ?>" min="1" max="365" class="small-text">
                            <?php _e('days', 'oha-survey-hub'); ?>
                            <p class="description"><?php _e('How long to keep incomplete survey responses before automatic cleanup.', 'oha-survey-hub'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Settings', 'oha-survey-hub'), 'primary', 'save_settings'); ?>
            </form>
            
            <div class="oha-admin-card" style="margin-top: 20px;">
                <h2><?php _e('Data Management', 'oha-survey-hub'); ?></h2>
                <p><?php _e('Use these tools to manage survey data and clean up old records.', 'oha-survey-hub'); ?></p>
                <p>
                    <button type="button" class="button" onclick="ohaCleanupOldResponses()">
                        <?php _e('Clean Up Old Incomplete Responses', 'oha-survey-hub'); ?>
                    </button>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Helper functions
     */
    private function mask_email($email) {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return $email;
        
        $username = $parts[0];
        $domain = $parts[1];
        
        $masked_username = substr($username, 0, 2) . str_repeat('*', max(0, strlen($username) - 2));
        return $masked_username . '@' . $domain;
    }
    
    private function find_question_in_survey($survey, $question_id) {
        if (!$survey || empty($survey['pages'])) return null;
        
        foreach ($survey['pages'] as $page) {
            if (empty($page['questions'])) continue;
            foreach ($page['questions'] as $question) {
                if ($question['id'] === $question_id) {
                    return $question;
                }
            }
        }
        
        return null;
    }
}
