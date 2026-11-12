<?php
/**
 * Character Creator Component
 */

if (!defined('ABSPATH')) exit;

class ScareShift_Character_Creator {
    
    public function __construct() {
        add_action('init', [$this, 'register_shortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }
    
    public function enqueue_assets() {
        wp_register_style(
            'scareshift-style',
            SCARESHIFT_URL . 'assets/css/scareshift.css',
            [],
            SCARESHIFT_VERSION
        );
        
        wp_register_script(
            'scareshift-creator',
            SCARESHIFT_URL . 'assets/js/character-creator.js',
            ['jquery'],
            SCARESHIFT_VERSION,
            true
        );
        
        $user_id = get_current_user_id();
        $user_xp = get_user_meta($user_id, 'scareshift_xp', true) ?: 0;
        $user_level = $this->calculate_level($user_xp);
        
        wp_localize_script('scareshift-creator', 'SCARESHIFT', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => esc_url_raw(rest_url('scareshift/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'urls' => [
                'scoring' => SCARESHIFT_URL . 'config/scoring-config.json',
                'samples' => SCARESHIFT_URL . 'config/samples.json',
                'charter' => SCARESHIFT_URL . 'config/charter.html'
            ],
            'user' => [
                'id' => $user_id,
                'isLoggedIn' => is_user_logged_in(),
                'xp' => $user_xp,
                'level' => $user_level
            ],
            'settings' => [
                'enableCharter' => ScareShift::get_setting('enable_charter', 1),
                'enableTutorial' => ScareShift::get_setting('enable_tutorial', 1)
            ]
        ]);
    }
    
    public function register_shortcodes() {
        add_shortcode('scareshift_creator', [$this, 'render_creator']);
        add_shortcode('scareshift_library', [$this, 'render_library']);
    }
    
    public function render_creator($atts = [], $content = '') {
        wp_enqueue_style('scareshift-style');
        wp_enqueue_script('scareshift-creator');
        
        ob_start();
        ?>
        <div class="scareshift-wrap">
            <div id="scareshift-creator" class="scareshift-app" data-mode="creator">
                <div class="scareshift-loading">
                    <div class="scareshift-spinner"></div>
                    <p>Loading Character Studio...</p>
                </div>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function render_library($atts = [], $content = '') {
        wp_enqueue_style('scareshift-style');
        wp_enqueue_script('scareshift-creator');
        
        ob_start();
        ?>
        <div class="scareshift-wrap">
            <div id="scareshift-library" class="scareshift-app" data-mode="library">
                <div class="scareshift-loading">
                    <div class="scareshift-spinner"></div>
                    <p>Loading Your Characters...</p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function register_rest_routes() {
        // Save character
        register_rest_route('scareshift/v1', '/character/save', [
            'methods' => 'POST',
            'permission_callback' => function() { return is_user_logged_in(); },
            'callback' => [$this, 'rest_save_character']
        ]);
        
        // List characters
        register_rest_route('scareshift/v1', '/character/list', [
            'methods' => 'GET',
            'permission_callback' => function() { return is_user_logged_in(); },
            'callback' => [$this, 'rest_list_characters']
        ]);
        
        // Delete character
        register_rest_route('scareshift/v1', '/character/delete', [
            'methods' => 'POST',
            'permission_callback' => function() { return is_user_logged_in(); },
            'callback' => [$this, 'rest_delete_character']
        ]);
    }
    
    public function rest_save_character($request) {
        $user_id = get_current_user_id();
        $payload = $request->get_json_params();
        
        if (!is_array($payload)) {
            return new WP_Error('invalid_data', 'Invalid character data', ['status' => 400]);
        }
        
        $characters = get_user_meta($user_id, 'scareshift_characters', true);
        if (!is_array($characters)) {
            $characters = [];
        }
        
        $payload['_saved_at'] = current_time('mysql');
        $payload['_id'] = uniqid('char_');
        $characters[] = $payload;
        
        // Keep last 20 characters
        $characters = array_slice($characters, -20);
        update_user_meta($user_id, 'scareshift_characters', $characters);
        
        // Award XP for first character creation
        $char_count = count($characters);
        if ($char_count === 1) {
            $this->award_xp($user_id, 50, 'Created first character');
        }
        
        return [
            'success' => true,
            'count' => count($characters),
            'character_id' => $payload['_id']
        ];
    }
    
    public function rest_list_characters($request) {
        $user_id = get_current_user_id();
        $characters = get_user_meta($user_id, 'scareshift_characters', true);
        
        if (!is_array($characters)) {
            $characters = [];
        }
        
        return [
            'success' => true,
            'characters' => $characters
        ];
    }
    
    public function rest_delete_character($request) {
        $user_id = get_current_user_id();
        $char_id = $request->get_param('character_id');
        
        $characters = get_user_meta($user_id, 'scareshift_characters', true);
        if (!is_array($characters)) {
            return new WP_Error('not_found', 'No characters found', ['status' => 404]);
        }
        
        $found = false;
        foreach ($characters as $key => $char) {
            if (isset($char['_id']) && $char['_id'] === $char_id) {
                unset($characters[$key]);
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            return new WP_Error('not_found', 'Character not found', ['status' => 404]);
        }
        
        $characters = array_values($characters);
        update_user_meta($user_id, 'scareshift_characters', $characters);
        
        return [
            'success' => true,
            'count' => count($characters)
        ];
    }
    
    private function calculate_level($xp) {
        // Simple level calculation: Level = floor(sqrt(xp / 100))
        return max(1, floor(sqrt($xp / 100)));
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
        
        // Keep last 50 entries
        $log = array_slice($log, -50);
        update_user_meta($user_id, 'scareshift_xp_log', $log);
        
        return $new_xp;
    }
}
