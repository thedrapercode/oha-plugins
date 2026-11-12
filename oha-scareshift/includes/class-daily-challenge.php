<?php
/**
 * Daily Challenge Component
 */

if (!defined('ABSPATH')) exit;

class ScareShift_Daily_Challenge {
    
    public function __construct() {
        add_action('init', [$this, 'register_shortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }
    
    public function enqueue_assets() {
        wp_register_script(
            'scareshift-challenge',
            SCARESHIFT_URL . 'assets/js/daily-challenge.js',
            ['jquery'],
            SCARESHIFT_VERSION,
            true
        );
        
        $user_id = get_current_user_id();
        
        wp_localize_script('scareshift-challenge', 'SCARESHIFT_CHALLENGE', [
            'restUrl' => esc_url_raw(rest_url('scareshift/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'user' => [
                'id' => $user_id,
                'isLoggedIn' => is_user_logged_in()
            ],
            'settings' => [
                'xpPerScenario' => ScareShift::get_setting('xp_per_scenario', 10)
            ]
        ]);
    }
    
    public function register_shortcodes() {
        add_shortcode('scareshift_daily_challenge', [$this, 'render_challenge']);
        add_shortcode('scareshift_scenario_history', [$this, 'render_history']);
    }
    
    public function render_challenge($atts = [], $content = '') {
        wp_enqueue_style('scareshift-style');
        wp_enqueue_script('scareshift-challenge');
        
        if (!is_user_logged_in()) {
            return '<div class="scareshift-notice">Please log in to play daily scenarios.</div>';
        }
        
        ob_start();
        ?>
        <div class="scareshift-wrap">
            <div id="scareshift-daily-challenge" class="scareshift-challenge-container">
                <div class="scareshift-loading">
                    <div class="scareshift-spinner"></div>
                    <p>Loading Today's Scenario...</p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function render_history($atts = [], $content = '') {
        if (!is_user_logged_in()) {
            return '<div class="scareshift-notice">Please log in to view your scenario history.</div>';
        }
        
        $user_id = get_current_user_id();
        $history = get_user_meta($user_id, 'scareshift_scenario_history', true);
        
        if (!is_array($history) || empty($history)) {
            return '<div class="scareshift-notice">You haven\'t completed any scenarios yet. Try the daily challenge!</div>';
        }
        
        ob_start();
        ?>
        <div class="scareshift-wrap">
            <div class="scareshift-history">
                <h3>Your Scenario History</h3>
                <div class="scareshift-history-grid">
                    <?php foreach (array_reverse($history) as $entry): ?>
                        <div class="scareshift-history-card">
                            <div class="scareshift-history-date">
                                <?php echo esc_html(date('M j, Y', strtotime($entry['completed_at']))); ?>
                            </div>
                            <div class="scareshift-history-scenario">
                                <?php echo esc_html($entry['scenario_title']); ?>
                            </div>
                            <div class="scareshift-history-choice">
                                Choice: <?php echo esc_html($entry['choice']); ?>
                            </div>
                            <div class="scareshift-history-xp">
                                +<?php echo esc_html($entry['xp_earned']); ?> XP
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function register_rest_routes() {
        // Get today's scenario
        register_rest_route('scareshift/v1', '/challenge/today', [
            'methods' => 'GET',
            'permission_callback' => function() { return is_user_logged_in(); },
            'callback' => [$this, 'rest_get_today_scenario']
        ]);
        
        // Submit scenario choice
        register_rest_route('scareshift/v1', '/challenge/submit', [
            'methods' => 'POST',
            'permission_callback' => function() { return is_user_logged_in(); },
            'callback' => [$this, 'rest_submit_choice']
        ]);
    }
    
    public function rest_get_today_scenario($request) {
        $user_id = get_current_user_id();
        $today_key = date('Y-m-d');
        
        // Check if user already completed today's scenario
        $completed = get_user_meta($user_id, 'scareshift_completed_scenarios', true);
        if (!is_array($completed)) {
            $completed = [];
        }
        
        $already_completed = isset($completed[$today_key]);
        
        // Get today's scenario
        $scenario = $this->get_scenario_for_date($today_key);
        
        if (!$scenario) {
            return new WP_Error('no_scenario', 'No scenario available', ['status' => 404]);
        }
        
        return [
            'success' => true,
            'scenario' => $scenario,
            'already_completed' => $already_completed,
            'completion_data' => $already_completed ? $completed[$today_key] : null
        ];
    }
    
    public function rest_submit_choice($request) {
        $user_id = get_current_user_id();
        $choice = sanitize_text_field($request->get_param('choice'));
        $scenario_id = sanitize_text_field($request->get_param('scenario_id'));
        
        $today_key = date('Y-m-d');
        
        // Check if already completed today
        $completed = get_user_meta($user_id, 'scareshift_completed_scenarios', true);
        if (!is_array($completed)) {
            $completed = [];
        }
        
        if (isset($completed[$today_key])) {
            return new WP_Error('already_completed', 'Already completed today\'s scenario', ['status' => 400]);
        }
        
        // Get scenario to validate choice and calculate outcome
        $scenario = $this->get_scenario_by_id($scenario_id);
        
        if (!$scenario || !isset($scenario['choices'][$choice])) {
            return new WP_Error('invalid_choice', 'Invalid choice', ['status' => 400]);
        }
        
        $choice_data = $scenario['choices'][$choice];
        $xp_earned = ScareShift::get_setting('xp_per_scenario', 10);
        
        // Award XP
        $this->award_xp($user_id, $xp_earned, 'Completed daily scenario');
        
        // Record completion
        $completed[$today_key] = [
            'scenario_id' => $scenario_id,
            'choice' => $choice,
            'completed_at' => current_time('mysql'),
            'xp_earned' => $xp_earned
        ];
        update_user_meta($user_id, 'scareshift_completed_scenarios', $completed);
        
        // Add to history
        $history = get_user_meta($user_id, 'scareshift_scenario_history', true);
        if (!is_array($history)) {
            $history = [];
        }
        
        $history[] = [
            'scenario_id' => $scenario_id,
            'scenario_title' => $scenario['title'],
            'choice' => $choice,
            'choice_text' => $choice_data['text'],
            'outcome' => $choice_data['outcome'],
            'stats' => $choice_data['stats'],
            'xp_earned' => $xp_earned,
            'completed_at' => current_time('mysql')
        ];
        
        // Keep last 100 entries
        $history = array_slice($history, -100);
        update_user_meta($user_id, 'scareshift_scenario_history', $history);
        
        return [
            'success' => true,
            'outcome' => $choice_data['outcome'],
            'stats' => $choice_data['stats'],
            'xp_earned' => $xp_earned,
            'total_xp' => get_user_meta($user_id, 'scareshift_xp', true)
        ];
    }
    
    private function get_scenario_for_date($date) {
        $scenarios = get_option('scareshift_scenarios', []);
        
        if (empty($scenarios)) {
            return null;
        }
        
        // Use date as seed for consistent daily scenario
        $seed = strtotime($date);
        mt_srand($seed);
        $index = mt_rand(0, count($scenarios) - 1);
        mt_srand(); // Reset random seed
        
        $scenario = $scenarios[$index];
        $scenario['id'] = 'scenario_' . $index;
        
        return $scenario;
    }
    
    private function get_scenario_by_id($scenario_id) {
        $scenarios = get_option('scareshift_scenarios', []);
        $index = (int) str_replace('scenario_', '', $scenario_id);
        
        if (!isset($scenarios[$index])) {
            return null;
        }
        
        return $scenarios[$index];
    }
    
    private function award_xp($user_id, $amount, $reason = '') {
        $current_xp = (int) get_user_meta($user_id, 'scareshift_xp', true);
        $new_xp = $current_xp + $amount;
        update_user_meta($user_id, 'scareshift_xp', $new_xp);
        
        // Log XP activity
        $log = get_user_meta($user_id, 'scareshift_xp_log', true);
        if (!is_array($log)) {
            $log = [];
        }
        
        $log[] = [
            'amount' => $amount,
            'reason' => $reason,
            'timestamp' => current_time('mysql'),
            'total_xp' => $new_xp
        ];
        
        $log = array_slice($log, -50);
        update_user_meta($user_id, 'scareshift_xp_log', $log);
        
        return $new_xp;
    }
}
