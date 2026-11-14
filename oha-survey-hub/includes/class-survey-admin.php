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
                        <?php
                        foreach ($stats['by_type'] as $type => $counts):
                            $survey = OHA_Survey_Definitions::get_survey($type);
                            $title  = $survey ? $survey['title'] : (
                                preg_match('/^convention_\d{4}/', $type)
                                    ? OHA_Convention_Survey::get_title($type)
                                    : ucfirst(str_replace('_', ' ', $type))
                            );
                        ?>
                            <div class="oha-survey-item">
                                <div class="oha-survey-name"><?php echo esc_html($title); ?></div>
                                <div class="oha-survey-counts">
                                    <span class="oha-completed"><?php echo number_format($counts['completed']); ?> completed</span>
                                    <span class="oha-in-progress"><?php echo number_format($counts['in_progress']); ?> in progress</span>
                                </div>
                                <div class="oha-survey-actions">
                                    <a href="<?php echo admin_url('admin.php?page=oha-survey-results&survey_type=' . $type); ?>" class="button button-small">View Responses</a>
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
            <script>
            var ohaSurveyAdminNonce = <?php echo wp_json_encode(wp_create_nonce('oha_survey_nonce')); ?>;
            (function($){
                $(document).on('click', '.oha-gen-retake-link', function(){
                    var btn = $(this);
                    var email = btn.data('email');
                    var surveyType = btn.data('survey-type');
                    btn.prop('disabled', true).text('Generating...');
                    $.post(ajaxurl, {
                        action: 'oha_generate_retake_token',
                        nonce: ohaSurveyAdminNonce,
                        email: email,
                        survey_type: surveyType
                    }, function(resp){
                        btn.prop('disabled', false).text('Retake Link');
                        btn.closest('tr').next('.oha-retake-url-row').remove();
                        if (resp.success) {
                            var urlRow = $('<tr class="oha-retake-url-row"><td colspan="5" style="background:#f0f6fc;padding:8px 12px;border-left:3px solid #2271b1;">' +
                                '<strong>Retake Link</strong> <span style="color:#666;font-weight:normal;">(single-use, expires in 48 hours)</span><br>' +
                                '<input type="text" readonly value="' + $('<span>').text(resp.data.url).html() + '" ' +
                                'style="width:100%;margin-top:6px;font-family:monospace;font-size:12px;" onclick="this.select();">' +
                                '</td></tr>');
                            btn.closest('tr').after(urlRow);
                            urlRow.find('input').trigger('click');
                        } else {
                            alert('Error: ' + (resp.data && resp.data.message ? resp.data.message : 'Unknown error'));
                        }
                    });
                });
            }(jQuery));
            </script>
            
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
                        // Build convention options from all years that have responses in DB
                        global $wpdb;
                        $conv_types = $wpdb->get_col(
                            "SELECT DISTINCT survey_type FROM {$wpdb->prefix}oha_survey_responses
                             WHERE survey_type LIKE 'convention_%' ORDER BY survey_type ASC"
                        );
                        // Always include active year types even if no responses yet
                        $active_types = [
                            OHA_Convention_Survey::active_type(),
                            OHA_Convention_Survey::active_type_vendor(),
                            OHA_Convention_Survey::active_type_volunteer(),
                        ];
                        $conv_types = array_unique(array_merge($conv_types, $active_types));
                        sort($conv_types);
                        foreach ($conv_types as $type) {
                            $title    = OHA_Convention_Survey::get_title($type);
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
                <table class="wp-list-table widefat fixed striped" style="margin-top:12px;">
                    <thead>
                        <tr>
                            <th style="width:50px;">#</th>
                            <th style="width:160px;">Date</th>
                            <th>Email</th>
                            <th style="width:90px;">Answers</th>
                            <th style="width:110px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($responses as $response):
                        $answer_count = is_array($response->response_data) ? count(array_filter($response->response_data, function($v){ return $v !== '' && $v !== null && $v !== []; })) : 0;
                    ?>
                        <tr>
                            <td><?php echo (int) $response->id; ?></td>
                            <td><?php echo esc_html(date('M j, Y g:i A', strtotime($response->completed_at))); ?></td>
                            <td><?php echo esc_html($response->email); ?></td>
                            <td><?php echo $answer_count; ?> answered</td>
                            <td>
                                <button type="button" class="button button-small oha-toggle-response"
                                    data-response-id="<?php echo esc_attr($response->id); ?>">View Details</button>
                                <button type="button" class="button button-small oha-gen-retake-link"
                                    data-email="<?php echo esc_attr($response->email); ?>"
                                    data-survey-type="<?php echo esc_attr($survey_type); ?>"
                                    style="margin-left:4px;">Retake Link</button>
                            </td>
                        </tr>
                        <tr class="oha-response-detail-row">
                            <td colspan="5" style="padding:0;">
                                <div id="oha-response-<?php echo $response->id; ?>" style="display:none;padding:0 12px 12px;">
                                    <?php $this->render_response_details($response, $survey); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                
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
     * Render response details grouped by page/section
     */
    private function render_response_details($response, $survey) {
        $answers = is_array($response->response_data) ? $response->response_data : [];

        if (!$survey || empty($survey['pages'])) {
            if (empty($answers)) {
                echo '<p style="padding:8px;color:#666;">No answer data recorded.</p>';
                return;
            }
            echo '<table class="widefat striped" style="margin:8px 0;"><tbody>';
            foreach ($answers as $key => $val) {
                $display = is_array($val) ? implode(', ', $val) : $val;
                echo '<tr><td style="width:50%;padding:6px 10px;font-weight:600;">' . esc_html($key) . '</td>'
                   . '<td style="padding:6px 10px;">' . esc_html($display) . '</td></tr>';
            }
            echo '</tbody></table>';
            return;
        }

        echo '<div style="margin:4px 0 8px;">';
        foreach ($survey['pages'] as $page) {
            if (empty($page['questions'])) continue;

            $page_rows = [];
            foreach ($page['questions'] as $q) {
                if (!isset($answers[$q['id']])) continue;
                $val     = $answers[$q['id']];
                $display = is_array($val) ? implode(', ', $val) : $val;
                if ($display === '') continue;
                $page_rows[] = ['q' => $q['question'], 'a' => $display];
            }
            if (empty($page_rows)) continue;

            echo '<div style="margin-bottom:12px;">';
            echo '<div style="font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.04em;'
               . 'color:#fff;background:#ad3119;padding:4px 10px;margin-bottom:0;">'
               . esc_html($page['title']) . '</div>';
            echo '<table class="widefat" style="border-top:none;"><tbody>';
            foreach ($page_rows as $i => $row) {
                $bg = $i % 2 === 0 ? '#fff' : '#f9f9f9';
                echo '<tr style="background:' . $bg . ';">'
                   . '<td style="width:55%;padding:6px 10px;vertical-align:top;">' . esc_html($row['q']) . '</td>'
                   . '<td style="padding:6px 10px;vertical-align:top;font-weight:600;">' . esc_html($row['a']) . '</td>'
                   . '</tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</div>';
    }
    
    /**
     * Render all surveys summary
     */
    private function render_all_surveys_summary() {
        $stats = $this->data_handler->get_survey_stats();
        ?>
        <div class="oha-all-surveys-summary">
            <h2>All Survey Results</h2>
            <?php if (empty($stats['by_type'])): ?>
                <p>No survey responses yet.</p>
            <?php else: ?>
            <table class="wp-list-table widefat fixed striped" style="margin-top:12px;">
                <thead>
                    <tr>
                        <th>Survey</th>
                        <th style="width:120px;">Completed</th>
                        <th style="width:120px;">In Progress</th>
                        <th style="width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($stats['by_type'] as $type => $counts):
                    $survey = OHA_Survey_Definitions::get_survey($type);
                    $title  = $survey ? $survey['title'] : (
                        preg_match('/^convention_\d{4}/', $type)
                            ? OHA_Convention_Survey::get_title($type)
                            : ucfirst(str_replace('_', ' ', $type))
                    );
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($title); ?></strong></td>
                        <td><?php echo number_format($counts['completed']); ?></td>
                        <td><?php echo number_format($counts['in_progress']); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=oha-survey-results&survey_type=' . $type); ?>" class="button button-small">View Responses</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    // =========================================================================
    // Analytics page
    // =========================================================================

    public function render_analytics() {
        $data        = $this->data_handler;
        $stats       = $data->get_survey_stats();
        $survey_type = sanitize_text_field( $_GET['survey_type'] ?? '' );
        $year        = intval( $_GET['year'] ?? 0 );
        $compare_yr  = intval( $_GET['compare_year'] ?? 0 );
        $base_url    = admin_url( 'admin.php?page=oha-survey-analytics' );
        $all_types   = $this->get_all_analytics_types( $stats );
        ?>
        <div class="wrap oha-survey-admin">
            <h1><?php _e( 'Survey Analytics', 'oha-survey-hub' ); ?></h1>

            <nav class="oha-analytics-nav">
                <a href="<?php echo esc_url( $base_url ); ?>"
                   class="oha-anav-tab <?php echo $survey_type === '' ? 'is-active' : ''; ?>">
                    All Surveys
                    <span class="oha-tab-count"><?php echo number_format( $stats['total_responses'] ); ?></span>
                </a>
                <?php foreach ( $all_types as $type => $info ): ?>
                <a href="<?php echo esc_url( add_query_arg( 'survey_type', $type, $base_url ) ); ?>"
                   class="oha-anav-tab <?php echo $survey_type === $type ? 'is-active' : ''; ?>">
                    <?php echo esc_html( $info['title'] ); ?>
                    <?php if ( $info['count'] > 0 ): ?>
                    <span class="oha-tab-count"><?php echo number_format( $info['count'] ); ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </nav>

            <?php if ( $survey_type ): ?>
                <?php
                $years      = $data->get_survey_years( $survey_type );
                $survey_url = add_query_arg( 'survey_type', $survey_type, $base_url );
                ?>
                <div class="oha-analytics-toolbar">
                    <form class="oha-toolbar-filters" method="get" action="">
                        <input type="hidden" name="page" value="oha-survey-analytics">
                        <input type="hidden" name="survey_type" value="<?php echo esc_attr( $survey_type ); ?>">
                        <label class="oha-filter-label">Year:
                            <select name="year" onchange="this.form.submit()">
                                <option value="0" <?php selected( $year, 0 ); ?>>All years</option>
                                <?php foreach ( $years as $y ): ?>
                                <option value="<?php echo esc_attr( $y ); ?>" <?php selected( $year, $y ); ?>><?php echo esc_html( $y ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <?php if ( count( $years ) > 1 ): ?>
                        <label class="oha-filter-label">Compare to:
                            <select name="compare_year" onchange="this.form.submit()">
                                <option value="0" <?php selected( $compare_yr, 0 ); ?>>Off</option>
                                <?php foreach ( $years as $y ):
                                    if ( $y === $year ) continue; ?>
                                <option value="<?php echo esc_attr( $y ); ?>" <?php selected( $compare_yr, $y ); ?>><?php echo esc_html( $y ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <?php endif; ?>
                    </form>
                    <div class="oha-toolbar-actions">
                        <?php $count = $year ? $data->get_count_for_year( $survey_type, $year ) : $data->get_response_count( $survey_type ); ?>
                        <span class="oha-response-badge"><?php echo number_format( $count ); ?> responses</span>
                        <button type="button" class="button" onclick="ohaExportSurveyData('<?php echo esc_js( $survey_type ); ?>','csv')">Export CSV</button>
                        <button type="button" class="button" onclick="ohaExportSurveyData('<?php echo esc_js( $survey_type ); ?>','json')">Export JSON</button>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=oha-survey-results&survey_type=' . $survey_type ) ); ?>" class="button">Raw Responses</a>
                    </div>
                </div>
                <?php $this->render_analytics_detail( $survey_type, $year ?: null, $compare_yr ?: null ); ?>
            <?php else: ?>
                <?php $this->render_analytics_overview( $stats ); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_analytics_overview( $stats ) {
        $data     = $this->data_handler;
        $raw      = $data->get_all_timeline( 30 );
        $tmap     = [];
        foreach ( $raw as $r ) $tmap[ $r->date ] = (int) $r->count;
        $days = [];
        for ( $i = 29; $i >= 0; $i-- ) {
            $d      = date( 'Y-m-d', strtotime( "-{$i} days" ) );
            $days[] = [ 'label' => date( 'm/d', strtotime( $d ) ), 'count' => $tmap[$d] ?? 0 ];
        }
        $last30 = array_sum( array_column( $days, 'count' ) );
        ?>
        <div class="oha-stat-cards">
            <div class="oha-stat-card">
                <div class="oha-stat-num"><?php echo number_format( $stats['total_responses'] ); ?></div>
                <div class="oha-stat-lbl">Total Responses</div>
            </div>
            <div class="oha-stat-card">
                <div class="oha-stat-num"><?php echo number_format( $stats['in_progress'] ); ?></div>
                <div class="oha-stat-lbl">In Progress</div>
            </div>
            <div class="oha-stat-card">
                <div class="oha-stat-num"><?php echo count( $stats['by_type'] ); ?></div>
                <div class="oha-stat-lbl">Active Survey Types</div>
            </div>
            <div class="oha-stat-card">
                <div class="oha-stat-num"><?php echo number_format( $last30 ); ?></div>
                <div class="oha-stat-lbl">Last 30 Days</div>
            </div>
        </div>

        <div class="oha-chart-card">
            <div class="oha-chart-card-title">Response Activity — All Surveys, Last 30 Days</div>
            <div class="oha-timeline-wrap">
                <canvas id="oha-overview-timeline"></canvas>
            </div>
            <script>
            (function(){
                var el=document.getElementById('oha-overview-timeline');
                if(!el||typeof Chart==='undefined') return;
                var d=<?php echo wp_json_encode( $days ); ?>;
                new Chart(el,{type:'bar',data:{labels:d.map(function(x){return x.label;}),datasets:[{label:'Responses',data:d.map(function(x){return x.count;}),backgroundColor:'rgba(173,49,25,0.75)',borderColor:'rgba(173,49,25,1)',borderWidth:1,borderRadius:3}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1,precision:0}},x:{grid:{display:false},ticks:{maxTicksLimit:15}}}}});
            })();
            </script>
        </div>

        <div class="oha-chart-card">
            <div class="oha-chart-card-title">All Survey Responses</div>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th>Survey</th>
                    <th style="width:120px;">Completed</th>
                    <th style="width:120px;">In Progress</th>
                    <th style="width:200px;">Actions</th>
                </tr></thead>
                <tbody>
                <?php
                $ab = admin_url( 'admin.php' );
                foreach ( $stats['by_type'] as $type => $counts ):
                    $title = $this->resolve_survey_title( $type );
                ?>
                <tr>
                    <td><strong><?php echo esc_html( $title ); ?></strong></td>
                    <td><?php echo number_format( $counts['completed'] ); ?></td>
                    <td><?php echo number_format( $counts['in_progress'] ); ?></td>
                    <td>
                        <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'oha-survey-analytics', 'survey_type' => $type ], $ab ) ); ?>" class="button button-small">Analytics</a>
                        <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'oha-survey-results', 'survey_type' => $type ], $ab ) ); ?>" class="button button-small" style="margin-left:4px;">Raw Responses</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_analytics_detail( $survey_type, $year, $compare_yr ) {
        $data   = $this->data_handler;
        $survey = $this->resolve_survey_definition( $survey_type );
        if ( ! $survey ) {
            echo '<div class="notice notice-warning inline"><p>No survey definition found for this type. Questions may not have been seeded yet.</p></div>';
            return;
        }

        $rollup1 = $data->get_rollup( $survey_type, $year );
        $rollup2 = $compare_yr ? $data->get_rollup( $survey_type, $compare_yr ) : null;
        $total1  = $year ? $data->get_count_for_year( $survey_type, $year ) : $data->get_response_count( $survey_type );
        $total2  = $compare_yr ? $data->get_count_for_year( $survey_type, $compare_yr ) : 0;
        $label1  = $year ? (string) $year : 'All Time';
        $label2  = $compare_yr ? (string) $compare_yr : '';

        // Timeline (last 30 days)
        $raw  = $data->get_timeline( $survey_type, 30 );
        $tmap = [];
        foreach ( $raw as $r ) $tmap[ $r->date ] = (int) $r->count;
        $tdays = [];
        for ( $i = 29; $i >= 0; $i-- ) {
            $d      = date( 'Y-m-d', strtotime( "-{$i} days" ) );
            $tdays[] = [ 'label' => date( 'm/d', strtotime( $d ) ), 'count' => $tmap[$d] ?? 0 ];
        }

        // Question coverage stat
        $total_q = 0;
        $q_with_data = 0;
        foreach ( $survey['pages'] as $pg ) {
            foreach ( ( $pg['questions'] ?? [] ) as $q ) {
                $total_q++;
                if ( ! empty( $rollup1[ $q['id'] ] ) ) $q_with_data++;
            }
        }
        $q_coverage = $total_q > 0 ? round( $q_with_data / $total_q * 100 ) : 0;
        ?>

        <div class="oha-stat-cards">
            <div class="oha-stat-card">
                <div class="oha-stat-num"><?php echo number_format( $total1 ); ?></div>
                <div class="oha-stat-lbl"><?php echo $year ? "Responses ({$year})" : 'Total Responses'; ?></div>
            </div>
            <div class="oha-stat-card">
                <div class="oha-stat-num"><?php echo number_format( $data->get_this_month_count( $survey_type ) ); ?></div>
                <div class="oha-stat-lbl">This Month</div>
            </div>
            <div class="oha-stat-card">
                <div class="oha-stat-num"><?php echo $data->get_completion_rate( $survey_type ); ?>%</div>
                <div class="oha-stat-lbl">Completion Rate</div>
            </div>
            <div class="oha-stat-card">
                <div class="oha-stat-num"><?php echo $q_coverage; ?>%</div>
                <div class="oha-stat-lbl">Question Coverage</div>
            </div>
        </div>

        <?php if ( $compare_yr ): ?>
        <div class="oha-compare-banner">
            Comparing <strong><?php echo esc_html( $label1 ); ?></strong> (<?php echo number_format( $total1 ); ?> responses)
            vs <strong><?php echo esc_html( $label2 ); ?></strong> (<?php echo number_format( $total2 ); ?> responses)
        </div>
        <?php endif; ?>

        <div class="oha-chart-card">
            <div class="oha-chart-card-title">Response Activity — Last 30 Days</div>
            <div class="oha-timeline-wrap">
                <canvas id="oha-survey-timeline"></canvas>
            </div>
            <script>
            (function(){
                var el=document.getElementById('oha-survey-timeline');
                if(!el||typeof Chart==='undefined') return;
                var d=<?php echo wp_json_encode( $tdays ); ?>;
                new Chart(el,{type:'bar',data:{labels:d.map(function(x){return x.label;}),datasets:[{label:'Responses',data:d.map(function(x){return x.count;}),backgroundColor:'rgba(173,49,25,0.75)',borderColor:'rgba(173,49,25,1)',borderWidth:1,borderRadius:3}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1,precision:0}},x:{grid:{display:false},ticks:{maxTicksLimit:15}}}}});
            })();
            </script>
        </div>

        <div class="oha-questions-wrap">
        <?php foreach ( $survey['pages'] as $pg_idx => $page ):
            $page_has_data = false;
            foreach ( ( $page['questions'] ?? [] ) as $q ) {
                $qtype = $q['type'] ?? 'text';
                if ( ! empty( $rollup1[ $q['id'] ] ) ) { $page_has_data = true; break; }
                if ( ( $qtype === 'textarea' || $qtype === 'text' ) ) {
                    $t = $data->get_text_responses( $survey_type, $q['id'], $year );
                    if ( ! empty($t) ) { $page_has_data = true; break; }
                }
            }
            if ( ! $page_has_data ) continue;
        ?>
        <div class="oha-page-section">
            <div class="oha-page-section-title"><?php echo esc_html( $page['title'] ?? ( 'Page ' . ( $pg_idx + 1 ) ) ); ?></div>
            <?php foreach ( ( $page['questions'] ?? [] ) as $q ):
                $qtype = $q['type'] ?? 'text';
                if ( $qtype === 'textarea' || $qtype === 'text' ) {
                    $t1 = $data->get_text_responses( $survey_type, $q['id'], $year );
                    $t2 = $compare_yr ? $data->get_text_responses( $survey_type, $q['id'], $compare_yr ) : [];
                    if ( empty($t1) && empty($t2) ) continue;
                    $this->render_text_question_block( $q, $t1, $t2, $label1, $label2 );
                } else {
                    $a1 = $rollup1[ $q['id'] ] ?? [];
                    $a2 = $rollup2 ? ( $rollup2[ $q['id'] ] ?? [] ) : null;
                    if ( empty($a1) && ( $a2 === null || empty($a2) ) ) continue;
                    $this->render_choice_question_block( $q, $a1, $a2, $total1, $total2, $label1, $label2 );
                }
            endforeach; ?>
        </div>
        <?php endforeach; ?>
        </div>
        <?php
    }

    private function render_choice_question_block( $q, $answers1, $answers2, $total1, $total2, $label1, $label2 ) {
        $q_id   = $q['id'];
        $q_type = $q['type'] ?? 'radio';
        $is_cmp = $answers2 !== null;
        $opts   = $q['options'] ?? [];

        // Build ordered label/key pairs
        if ( ! empty( $opts ) ) {
            $keys   = array_keys( (array) $opts );
            $labels = array_values( (array) $opts );
        } elseif ( $q_type === 'scale' ) {
            $min  = $q['scale_min'] ?? 1;
            $max  = $q['scale_max'] ?? 5;
            $keys = $labels = range( $min, $max );
        } else {
            $all_keys = array_unique( array_merge( array_keys( $answers1 ), array_keys( $answers2 ?? [] ) ) );
            arsort( $answers1 );
            $keys = $labels = array_keys( $answers1 ) ?: $all_keys;
        }

        $data1 = [];
        $data2 = [];
        foreach ( $keys as $k ) {
            $data1[] = (int) ( $answers1[ (string)$k ] ?? 0 );
            if ( $is_cmp ) $data2[] = (int) ( ( $answers2 ?? [] )[ (string)$k ] ?? 0 );
        }

        $resp1 = array_sum( $data1 );
        if ( $resp1 === 0 && ( ! $is_cmp || array_sum( $data2 ) === 0 ) ) return;

        // For checkbox: each respondent can pick multiple, so total > respondent count.
        // We show % of total picks, not % of respondents.
        $pct_base = $resp1 > 0 ? $resp1 : 1;

        $canvas_id = 'oha-qc-' . preg_replace( '/[^a-z0-9]/i', '_', $q_id );
        $chart_h   = max( 90, count( $keys ) * 34 + 20 );

        $scale_avg = null;
        if ( $q_type === 'scale' && $resp1 > 0 ) {
            $weighted = 0;
            foreach ( $keys as $i => $k ) $weighted += (int)$k * $data1[$i];
            $scale_avg = round( $weighted / $resp1, 1 );
        }
        ?>
        <div class="oha-q-block" data-qtype="<?php echo esc_attr( $q_type ); ?>">
            <div class="oha-q-header">
                <div class="oha-q-title"><?php echo esc_html( $q['question'] ); ?></div>
                <div class="oha-q-badges">
                    <span class="oha-q-badge oha-badge-type"><?php echo esc_html( $q_type ); ?></span>
                    <span class="oha-q-badge oha-badge-count"><?php echo number_format( $resp1 ); ?> responses</span>
                    <?php if ( $is_cmp && array_sum($data2) > 0 ): ?>
                    <span class="oha-q-badge oha-badge-compare"><?php echo number_format( array_sum($data2) ); ?> in <?php echo esc_html($label2); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="oha-q-body">
                <?php if ( $scale_avg !== null ): ?>
                <div class="oha-scale-avg">
                    <span class="oha-scale-avg-num"><?php echo $scale_avg; ?></span>
                    <span class="oha-scale-avg-label">avg / <?php echo esc_html( $q['scale_max'] ?? 5 ); ?></span>
                </div>
                <?php endif; ?>
                <div class="oha-q-chart-wrap" style="height:<?php echo $chart_h; ?>px;">
                    <canvas id="<?php echo esc_attr( $canvas_id ); ?>"></canvas>
                </div>
                <div class="oha-q-table-wrap">
                    <table class="oha-q-table">
                        <?php if ( $is_cmp ): ?>
                        <thead><tr>
                            <th class="oha-q-label-col">Answer</th>
                            <th class="oha-q-cnt-col"><?php echo esc_html( $label1 ); ?></th>
                            <th class="oha-q-cnt-col"><?php echo esc_html( $label2 ); ?></th>
                            <th class="oha-q-pct-col">%</th>
                        </tr></thead>
                        <?php endif; ?>
                        <tbody>
                        <?php foreach ( $keys as $i => $k ):
                            $c1  = $data1[$i];
                            $c2  = $is_cmp ? $data2[$i] : 0;
                            $pct = round( $c1 / $pct_base * 100 );
                            if ( $c1 === 0 && ( ! $is_cmp || $c2 === 0 ) ) continue;
                        ?>
                        <tr>
                            <td class="oha-q-label"><?php echo esc_html( (string) $labels[$i] ); ?></td>
                            <td class="oha-q-cnt"><?php echo number_format( $c1 ); ?></td>
                            <?php if ( $is_cmp ): ?>
                            <td class="oha-q-cnt oha-q-cnt-cmp"><?php echo number_format( $c2 ); ?></td>
                            <?php endif; ?>
                            <td class="oha-q-pct">
                                <div class="oha-inline-bar-wrap">
                                    <div class="oha-inline-bar" style="width:<?php echo $pct; ?>%"></div>
                                    <span class="oha-pct-num"><?php echo $pct; ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <script>
            (function(){
                var el=document.getElementById('<?php echo esc_js($canvas_id); ?>');
                if(!el||typeof Chart==='undefined') return;
                var labels=<?php echo wp_json_encode( array_map('strval',$labels) ); ?>;
                var d1=<?php echo wp_json_encode($data1); ?>;
                var total=<?php echo $resp1 ?: 1; ?>;
                <?php if ($is_cmp): ?>
                var d2=<?php echo wp_json_encode($data2); ?>;
                var datasets=[
                    {label:<?php echo wp_json_encode($label1); ?>,data:d1,backgroundColor:'rgba(173,49,25,0.82)',borderColor:'rgba(173,49,25,1)',borderWidth:1,borderRadius:3},
                    {label:<?php echo wp_json_encode($label2); ?>,data:d2,backgroundColor:'rgba(17,89,175,0.75)',borderColor:'rgba(17,89,175,1)',borderWidth:1,borderRadius:3}
                ];
                <?php else: ?>
                var datasets=[{label:'Responses',data:d1,backgroundColor:'rgba(173,49,25,0.82)',borderColor:'rgba(173,49,25,1)',borderWidth:1,borderRadius:3}];
                <?php endif; ?>
                new Chart(el,{
                    type:'bar',
                    data:{labels:labels,datasets:datasets},
                    options:{
                        indexAxis:'y',responsive:true,maintainAspectRatio:false,
                        plugins:{
                            legend:{display:<?php echo $is_cmp ? 'true' : 'false'; ?>},
                            tooltip:{callbacks:{label:function(c){var p=Math.round(c.raw/total*100);return ' '+c.raw+' ('+p+'%)';}}}
                        },
                        scales:{
                            x:{beginAtZero:true,ticks:{stepSize:1,precision:0},grid:{color:'rgba(0,0,0,0.06)'}},
                            y:{grid:{display:false},ticks:{font:{size:12},autoSkip:false}}
                        }
                    }
                });
            })();
            </script>
        </div>
        <?php
    }

    private function render_text_question_block( $q, $texts1, $texts2, $label1, $label2 ) {
        if ( empty($texts1) && empty($texts2) ) return;
        $preview  = 5;
        $has_cmp  = ! empty($texts2);
        $uid      = preg_replace( '/[^a-z0-9]/i', '_', $q['id'] );
        ?>
        <div class="oha-q-block oha-q-text-block" data-qtype="textarea">
            <div class="oha-q-header">
                <div class="oha-q-title"><?php echo esc_html( $q['question'] ); ?></div>
                <div class="oha-q-badges">
                    <span class="oha-q-badge oha-badge-type">open text</span>
                    <span class="oha-q-badge oha-badge-count"><?php echo count($texts1); ?> responses</span>
                </div>
            </div>
            <?php if ( ! empty($texts1) ): ?>
            <?php if ( $has_cmp ): ?><div class="oha-text-section-label"><?php echo esc_html($label1); ?></div><?php endif; ?>
            <div class="oha-text-responses">
                <?php foreach ( $texts1 as $i => $entry ): ?>
                <div class="oha-text-entry<?php echo $i >= $preview ? ' oha-text-hidden' : ''; ?>">
                    <span class="oha-text-date"><?php echo esc_html( date('M j, Y', strtotime($entry['date'])) ); ?></span>
                    <div class="oha-text-content"><?php echo esc_html( $entry['text'] ); ?></div>
                </div>
                <?php endforeach; ?>
                <?php if ( count($texts1) > $preview ): ?>
                <button type="button" class="oha-expand-btn button button-small" data-uid="<?php echo esc_attr($uid); ?>-a">
                    Show all <?php echo count($texts1); ?> responses
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ( $has_cmp ): ?>
            <div class="oha-text-section-label"><?php echo esc_html($label2); ?></div>
            <div class="oha-text-responses">
                <?php foreach ( $texts2 as $i => $entry ): ?>
                <div class="oha-text-entry<?php echo $i >= $preview ? ' oha-text-hidden' : ''; ?>">
                    <span class="oha-text-date"><?php echo esc_html( date('M j, Y', strtotime($entry['date'])) ); ?></span>
                    <div class="oha-text-content"><?php echo esc_html( $entry['text'] ); ?></div>
                </div>
                <?php endforeach; ?>
                <?php if ( count($texts2) > $preview ): ?>
                <button type="button" class="oha-expand-btn button button-small" data-uid="<?php echo esc_attr($uid); ?>-b">
                    Show all <?php echo count($texts2); ?> responses
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function get_all_analytics_types( $stats ) {
        $types = [];

        $ordered = [
            'member'             => 'Member Survey',
            'haunt_owner'        => 'Haunt Owner',
            'industry_deep_dive' => 'Industry Deep-Dive',
            'convention_planning'=> 'Convention Planning',
            'scare_actor'        => 'Scare Actor',
            'behind_scenes'      => 'Behind-the-Scenes',
            'home_haunt'         => 'Home Haunt',
        ];
        foreach ( $ordered as $type => $fallback ) {
            $survey = OHA_Survey_Definitions::get_survey($type);
            $types[$type] = [
                'title' => $survey ? $survey['title'] : $fallback,
                'count' => $stats['by_type'][$type]['completed'] ?? 0,
            ];
        }

        // Convention types from DB responses
        foreach ( $stats['by_type'] as $type => $counts ) {
            if ( strpos($type, 'convention_') === 0 && ! isset($types[$type]) ) {
                $types[$type] = [
                    'title' => OHA_Convention_Survey::get_title($type),
                    'count' => $counts['completed'],
                ];
            }
        }
        // Always include active year types even with no responses yet
        foreach ( [ OHA_Convention_Survey::active_type(), OHA_Convention_Survey::active_type_vendor(), OHA_Convention_Survey::active_type_volunteer() ] as $type ) {
            if ( ! isset($types[$type]) ) {
                $types[$type] = [ 'title' => OHA_Convention_Survey::get_title($type), 'count' => 0 ];
            }
        }

        return $types;
    }

    private function resolve_survey_definition( $survey_type ) {
        $survey = OHA_Survey_Definitions::get_survey($survey_type);
        if ( ! $survey ) {
            $survey = OHA_Convention_Survey::get_as_definition($survey_type);
        }
        return $survey;
    }

    private function resolve_survey_title( $type ) {
        $survey = OHA_Survey_Definitions::get_survey($type);
        if ( $survey ) return $survey['title'];
        if ( preg_match('/^convention_\d{4}/', $type) ) return OHA_Convention_Survey::get_title($type);
        return ucwords(str_replace('_', ' ', $type));
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
            $new_year = (int) $_POST['convention_survey_year'];
            if ($new_year >= 2024 && $new_year <= 2050) {
                update_option('oha_convention_survey_year', $new_year);
            }
            echo '<div class="notice notice-success"><p>' . __('Settings saved!', 'oha-survey-hub') . '</p></div>';
        }

        $support_email          = get_option('oha_survey_hub_support_email', 'info@ohiohaunt.com');
        $email_notifications    = get_option('oha_survey_hub_email_notifications', true);
        $admin_notifications    = get_option('oha_survey_hub_admin_notifications', true);
        $require_confirmation   = get_option('oha_survey_hub_require_email_confirmation', false);
        $max_save_days          = get_option('oha_survey_hub_max_save_days', 30);
        $convention_board_email = get_option('oha_convention_board_email', '');
        $convention_year        = OHA_Convention_Survey::get_year();
        
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
                        <th scope="row"><?php _e('Active Convention Year', 'oha-survey-hub'); ?></th>
                        <td>
                            <input type="number" name="convention_survey_year" value="<?php echo esc_attr($convention_year); ?>" min="2024" max="2050" class="small-text">
                            <p class="description"><?php _e('The convention year shown on surveys, selector cards, and admin pages. Changing this year triggers a fresh question seed for that year. Prior years\' questions and responses are preserved.', 'oha-survey-hub'); ?></p>
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
