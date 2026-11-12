<?php
/**
 * Admin Component
 */

if (!defined('ABSPATH')) exit;

class ScareShift_Admin {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_post_scareshift_save_settings', [$this, 'save_settings']);
        add_action('admin_post_scareshift_save_scenarios', [$this, 'save_scenarios']);
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'ScareShift Settings',
            'ScareShift',
            'manage_options',
            'scareshift',
            [$this, 'render_settings_page'],
            'dashicons-tickets-alt',
            30
        );
        
        add_submenu_page(
            'scareshift',
            'ScareShift Settings',
            'Settings',
            'manage_options',
            'scareshift',
            [$this, 'render_settings_page']
        );
        
        add_submenu_page(
            'scareshift',
            'Manage Scenarios',
            'Scenarios',
            'manage_options',
            'scareshift-scenarios',
            [$this, 'render_scenarios_page']
        );
        
        add_submenu_page(
            'scareshift',
            'Player Stats',
            'Player Stats',
            'manage_options',
            'scareshift-stats',
            [$this, 'render_stats_page']
        );
    }
    
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $settings = get_option('scareshift_settings', []);
        $saved = isset($_GET['saved']) && $_GET['saved'] === '1';
        
        ?>
        <div class="wrap">
            <h1>ScareShift Settings</h1>
            
            <div class="notice notice-info" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-left: 4px solid #764ba2; color: white; margin-bottom: 20px;">
                <p style="font-size: 14px; margin: 10px 0;">
                    <strong>🎭 ScareShift Version: <?php echo SCARESHIFT_VERSION; ?></strong><br>
                    <?php 
                    $scenario_file = SCARESHIFT_DIR . 'config/scenario-library.php';
                    $scenario_count = 0;
                    if (file_exists($scenario_file)) {
                        $scenarios = include $scenario_file;
                        $scenario_count = is_array($scenarios) ? count($scenarios) : 0;
                    }
                    ?>
                    📚 Loaded Scenarios: <strong><?php echo $scenario_count; ?></strong><br>
                    📅 Last Updated: December 6, 2024
                </p>
            </div>
            
            <?php if ($saved): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Settings saved successfully!</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('scareshift_settings'); ?>
                <input type="hidden" name="action" value="scareshift_save_settings" />
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Charter Gate</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_charter" value="1" 
                                    <?php checked(!empty($settings['enable_charter'])); ?> />
                                Show safety charter before first use
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Enable Tutorial</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_tutorial" value="1" 
                                    <?php checked(!empty($settings['enable_tutorial'])); ?> />
                                Show tutorial for new users
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Enable Daily Challenge</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_daily_challenge" value="1" 
                                    <?php checked(!empty($settings['enable_daily_challenge'])); ?> />
                                Allow users to play daily scenarios
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">XP Per Scenario</th>
                        <td>
                            <input type="number" name="xp_per_scenario" 
                                value="<?php echo esc_attr($settings['xp_per_scenario'] ?? 10); ?>" 
                                min="1" max="100" />
                            <p class="description">XP awarded for completing each daily scenario</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Scenarios Per Day</th>
                        <td>
                            <input type="number" name="scenarios_per_day" 
                                value="<?php echo esc_attr($settings['scenarios_per_day'] ?? 1); ?>" 
                                min="1" max="5" />
                            <p class="description">Number of scenarios users can complete daily</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">Save Settings</button>
                </p>
            </form>
        </div>
        <?php
    }
    
    public function render_scenarios_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Load scenarios from config file
        $scenario_file = SCARESHIFT_DIR . 'config/scenario-library.php';
        $scenarios = file_exists($scenario_file) ? include $scenario_file : [];
        
        $saved = isset($_GET['saved']) && $_GET['saved'] === '1';
        
        // Count by difficulty
        $counts = [
            'standard_crowd' => 0,
            'tough_crowd' => 0,
            'hot_situation' => 0,
            'all_hands' => 0
        ];
        
        foreach ($scenarios as $scenario) {
            if (isset($scenario['difficulty']) && isset($counts[$scenario['difficulty']])) {
                $counts[$scenario['difficulty']]++;
            }
        }
        
        ?>
        <div class="wrap">
            <h1>Manage Scenarios</h1>
            
            <div class="notice notice-info" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-left: 4px solid #764ba2; color: white; margin-bottom: 20px;">
                <p style="font-size: 14px; margin: 10px 0;">
                    <strong>📚 Scenario Library Status</strong><br>
                    Total Scenarios: <strong><?php echo count($scenarios); ?></strong><br>
                    🟢 Standard Crowd: <strong><?php echo $counts['standard_crowd']; ?></strong><br>
                    🟡 Tough Crowd: <strong><?php echo $counts['tough_crowd']; ?></strong><br>
                    🟠 Hot Situation: <strong><?php echo $counts['hot_situation']; ?></strong><br>
                    🔴 All Hands: <strong><?php echo $counts['all_hands']; ?></strong>
                </p>
            </div>
            
            <?php if ($saved): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Scenarios saved successfully!</p>
                </div>
            <?php endif; ?>
            
            <p>Scenarios are loaded from <code>/config/scenario-library.php</code>. To add more scenarios, edit that file or use the table below to see what's loaded.</p>
            
            <h2>Loaded Scenarios</h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th style="width: 150px;">Difficulty</th>
                        <th>Title</th>
                        <th style="width: 100px;">Choices</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($scenarios)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 20px;">
                                <strong>⚠️ No scenarios loaded!</strong><br>
                                Check that <code>/config/scenario-library.php</code> exists and is properly formatted.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($scenarios as $index => $scenario): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <?php
                                    $labels = [
                                        'standard_crowd' => '🟢 Standard Crowd',
                                        'tough_crowd' => '🟡 Tough Crowd',
                                        'hot_situation' => '🟠 Hot Situation',
                                        'all_hands' => '🔴 All Hands'
                                    ];
                                    echo $labels[$scenario['difficulty']] ?? $scenario['difficulty'];
                                    ?>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($scenario['title']); ?></strong><br>
                                    <small style="color: #666;"><?php echo esc_html(substr($scenario['description'], 0, 100)); ?>...</small>
                                </td>
                                <td><?php echo count($scenario['choices']); ?> options</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 20px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                <h3>How to Add More Scenarios</h3>
                <p>Edit <code><?php echo SCARESHIFT_DIR; ?>config/scenario-library.php</code></p>
                <p>Each scenario needs:</p>
                <ul>
                    <li><code>'difficulty'</code> - standard_crowd, tough_crowd, hot_situation, or all_hands</li>
                    <li><code>'title'</code> - Short name for the scenario</li>
                    <li><code>'description'</code> - What's happening (no hints!)</li>
                    <li><code>'choices'</code> - Array with A, B, C, D, E options (3-7 choices)</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    public function render_stats_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        global $wpdb;
        
        // Get top players by XP
        $top_players = $wpdb->get_results("
            SELECT u.ID, u.display_name, um.meta_value as xp
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = 'scareshift_xp'
            ORDER BY CAST(um.meta_value AS UNSIGNED) DESC
            LIMIT 20
        ");
        
        ?>
        <div class="wrap">
            <h1>Player Statistics</h1>
            
            <div class="card" style="max-width:800px;">
                <h2>Top Players by XP</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Player</th>
                            <th>Total XP</th>
                            <th>Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($top_players)): ?>
                            <tr>
                                <td colspan="4">No player data yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php $rank = 1; ?>
                            <?php foreach ($top_players as $player): ?>
                                <?php 
                                $xp = (int) $player->xp;
                                $level = max(1, floor(sqrt($xp / 100)));
                                ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $player->ID)); ?>">
                                            <?php echo esc_html($player->display_name); ?>
                                        </a>
                                    </td>
                                    <td><?php echo number_format($xp); ?></td>
                                    <td>Level <?php echo $level; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    public function save_settings() {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        
        check_admin_referer('scareshift_settings');
        
        $settings = [
            'enable_charter' => isset($_POST['enable_charter']) ? 1 : 0,
            'enable_tutorial' => isset($_POST['enable_tutorial']) ? 1 : 0,
            'enable_daily_challenge' => isset($_POST['enable_daily_challenge']) ? 1 : 0,
            'xp_per_scenario' => (int) $_POST['xp_per_scenario'],
            'scenarios_per_day' => (int) $_POST['scenarios_per_day']
        ];
        
        update_option('scareshift_settings', $settings);
        
        wp_redirect(add_query_arg(['page' => 'scareshift', 'saved' => '1'], admin_url('admin.php')));
        exit;
    }
    
    public function save_scenarios() {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        
        check_admin_referer('scareshift_scenarios');
        
        if (isset($_GET['action']) && $_GET['action'] === 'reset') {
            $default_scenarios = include SCARESHIFT_DIR . 'config/default-scenarios.php';
            update_option('scareshift_scenarios', $default_scenarios);
            
            wp_redirect(add_query_arg(['page' => 'scareshift-scenarios', 'saved' => '1'], admin_url('admin.php')));
            exit;
        }
        
        $json = wp_unslash($_POST['scenarios_json']);
        $scenarios = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_redirect(add_query_arg([
                'page' => 'scareshift-scenarios',
                'error' => 'json'
            ], admin_url('admin.php')));
            exit;
        }
        
        update_option('scareshift_scenarios', $scenarios);
        
        wp_redirect(add_query_arg(['page' => 'scareshift-scenarios', 'saved' => '1'], admin_url('admin.php')));
        exit;
    }
}
