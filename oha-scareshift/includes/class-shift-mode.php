<?php
/**
 * Shift Mode - Cooperative Gameplay Component
 * 
 * Players use their characters in shifts (3-wave encounters)
 * Characters work together against NPC guests
 * Stats grow based on performance
 */

if (!defined('ABSPATH')) exit;

class ScareShift_Shift_Mode {
    
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
        
        wp_register_style(
            'scareshift-visual-v2',
            SCARESHIFT_URL . 'assets/css/scareshift-visual-v2.css',
            ['scareshift-style'],
            SCARESHIFT_VERSION
        );
        
        wp_register_style(
            'scareshift-tension',
            SCARESHIFT_URL . 'assets/css/scareshift-tension.css',
            ['scareshift-visual-v2'],
            SCARESHIFT_VERSION
        );
        
        wp_register_script(
            'scareshift-tension-system',
            SCARESHIFT_URL . 'assets/js/tension-system.js',
            [],
            SCARESHIFT_VERSION,
            true
        );
        
        wp_register_script(
            'scareshift-shift',
            SCARESHIFT_URL . 'assets/js/shift-mode.js',
            ['jquery', 'scareshift-tension-system'],
            SCARESHIFT_VERSION,
            true
        );
        
        $user_id = get_current_user_id();
        
        wp_localize_script('scareshift-shift', 'SCARESHIFT_SHIFT', [
            'restUrl' => esc_url_raw(rest_url('scareshift/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'user' => [
                'id' => $user_id,
                'isLoggedIn' => is_user_logged_in()
            ]
        ]);
    }
    
    public function register_shortcodes() {
        add_shortcode('scareshift_shift_mode', [$this, 'render_shift']);
    }
    
    public function render_shift($atts = [], $content = '') {
        wp_enqueue_style('scareshift-style');
        wp_enqueue_style('scareshift-visual-v2');
        wp_enqueue_style('scareshift-tension');
        wp_enqueue_script('scareshift-shift');
        
        if (!is_user_logged_in()) {
            return '<div class="scareshift-notice">Please log in to play Shift Mode.</div>';
        }
        
        ob_start();
        ?>
        <div class="scareshift-wrap">
            <div id="scareshift-shift-mode" class="scareshift-shift-container">
                <div class="scareshift-loading">
                    <div class="scareshift-spinner"></div>
                    <p>Loading Shift Mode...</p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function register_rest_routes() {
        // Start a new shift
        register_rest_route('scareshift/v1', '/shift/start', [
            'methods' => 'POST',
            'permission_callback' => function() { return is_user_logged_in(); },
            'callback' => [$this, 'rest_start_shift']
        ]);
        
        // Make a choice in current wave
        register_rest_route('scareshift/v1', '/shift/choose', [
            'methods' => 'POST',
            'permission_callback' => function() { return is_user_logged_in(); },
            'callback' => [$this, 'rest_make_choice']
        ]);
        
        // Get shift results
        register_rest_route('scareshift/v1', '/shift/results', [
            'methods' => 'GET',
            'permission_callback' => function() { return is_user_logged_in(); },
            'callback' => [$this, 'rest_get_results']
        ]);
    }
    
    public function rest_start_shift($request) {
        $user_id = get_current_user_id();
        $crew_data = $request->get_json_params();
        
        if (!isset($crew_data['characters']) || count($crew_data['characters']) < 1) {
            return new WP_Error('no_crew', 'Need at least 1 character for a shift', ['status' => 400]);
        }
        
        // Get progression parameters
        $run_number = isset($crew_data['run_number']) ? intval($crew_data['run_number']) : 1;
        $total_waves = isset($crew_data['total_waves']) ? intval($crew_data['total_waves']) : 1;
        
        // Limit to 3 waves max
        $total_waves = min($total_waves, 3);
        $run_number = min($run_number, 3);
        
        // Generate shift data
        $shift = [
            'shift_id' => uniqid('shift_'),
            'started_at' => current_time('mysql'),
            'crew' => $crew_data['characters'],
            'current_wave' => 1,
            'total_waves' => $total_waves, // Based on progression
            'run_number' => $run_number, // Track which run this is (1, 2, or 3)
            'encounters_per_wave' => 6, // Fixed at 6 encounters
            'current_wave_encounters' => 0,
            'guests_scared' => 0,
            'incidents_avoided' => 0,
            'incidents_occurred' => 0,
            'momentum' => 0,
            'dread' => 0,           // NEW: Tension that builds over wave
            'dread_max' => 5,       // NEW: Max dread level
            'fatigue' => 0,         // NEW: Actor exhaustion
            'fatigue_max' => 5,     // NEW: Max fatigue before penalties
            'voice_warmed_up' => false,  // NEW: Voice state
            'waves_completed' => [],
            'can_continue' => false // Will be set to true after completing a run
        ];
        
        // Get first wave (this adds encounter to shift cache)
        $wave = $this->generate_wave(1, $shift);
        
        // Save active shift (with cached encounter)
        update_user_meta($user_id, 'scareshift_active_shift', $shift);
        
        return [
            'success' => true,
            'shift' => $shift,
            'wave' => $wave
        ];
    }
    
    public function rest_make_choice($request) {
        $user_id = get_current_user_id();
        $choice = sanitize_text_field($request->get_param('choice'));
        $encounter_id = sanitize_text_field($request->get_param('encounter_id'));
        
        $shift = get_user_meta($user_id, 'scareshift_active_shift', true);
        
        if (!is_array($shift)) {
            return new WP_Error('no_shift', 'No active shift found', ['status' => 400]);
        }
        
        // Get encounter data
        $wave_num = $shift['current_wave'];
        $encounter = $this->get_encounter_by_id($encounter_id);
        
        if (!$encounter) {
            return new WP_Error('invalid_encounter', 'Encounter not found', ['status' => 400]);
        }
        
        // Handle TIMEOUT as special case (failure outcome)
        if ($choice === 'TIMEOUT') {
            $outcome = [
                'success' => false,
                'message' => "⏰ TIME'S UP! You hesitated too long and the moment was lost. The guests walked past unimpressed.",
                'guests_scared' => 0,
                'incidents_occurred' => 1,
                'incidents_avoided' => 0,
                'momentum_change' => -2,
                'dread_change' => 2,
                'fatigue_change' => 1,
                'stat_changes' => [
                    'fear' => -0.05,
                    'presence' => -0.03
                ]
            ];
        } else {
            // Normal choice validation and outcome calculation
            if (!isset($encounter['choices'][$choice])) {
                return new WP_Error('invalid_choice', 'Invalid choice', ['status' => 400]);
            }
            
            $choice_data = $encounter['choices'][$choice];
            
            // Calculate outcome
            $outcome = $this->calculate_outcome($shift, $encounter, $choice_data);
        }
        
        // Update shift data
        $shift['guests_scared'] += $outcome['guests_scared'];
        $shift['incidents_avoided'] += $outcome['incidents_avoided'];
        $shift['incidents_occurred'] += $outcome['incidents_occurred'];
        $shift['momentum'] += $outcome['momentum_change'];
        
        // Record wave completion
        $shift['waves_completed'][] = [
            'wave' => $wave_num,
            'encounter_id' => $encounter_id,
            'choice' => $choice,
            'outcome' => $outcome
        ];
        
        // Increment current wave encounter count
        $shift['current_wave_encounters'] = isset($shift['current_wave_encounters']) 
            ? $shift['current_wave_encounters'] + 1 
            : 1;
        
        // Check if wave complete (met encounter target for this wave)
        $encounters_target = $shift['encounters_per_wave'] ?? 5;
        $wave_complete = $shift['current_wave_encounters'] >= $encounters_target;
        
        if ($wave_complete && $wave_num < $shift['total_waves']) {
            // Move to next wave
            $shift['current_wave']++;
            $shift['current_wave_encounters'] = 0; // Reset counter for new wave
            
            $next_wave = $this->generate_wave($shift['current_wave'], $shift);
            
            // Save after wave generation to include cached encounter
            update_user_meta($user_id, 'scareshift_active_shift', $shift);
            
            return [
                'success' => true,
                'outcome' => $outcome,
                'wave_complete' => true,
                'shift_complete' => false,
                'next_wave' => $next_wave,
                'progress' => [
                    'wave' => $shift['current_wave'],
                    'total_waves' => $shift['total_waves'],
                    'encounters_this_wave' => 0,
                    'encounters_target' => $shift['encounters_per_wave']
                ]
            ];
        } elseif ($wave_complete && $wave_num >= $shift['total_waves']) {
            // Shift complete!
            $results = $this->complete_shift($user_id, $shift);
            delete_user_meta($user_id, 'scareshift_active_shift');
            
            return [
                'success' => true,
                'outcome' => $outcome,
                'wave_complete' => true,
                'shift_complete' => true,
                'results' => $results
            ];
        } else {
            // More encounters in current wave - generate next encounter
            $next_encounter_data = $this->generate_wave($wave_num, $shift);
            
            update_user_meta($user_id, 'scareshift_active_shift', $shift);
            
            return [
                'success' => true,
                'outcome' => $outcome,
                'wave_complete' => false,
                'shift_complete' => false,
                'next_encounter' => $next_encounter_data,
                'progress' => [
                    'wave' => $shift['current_wave'],
                    'total_waves' => $shift['total_waves'],
                    'encounters_this_wave' => $shift['current_wave_encounters'],
                    'encounters_target' => $shift['encounters_per_wave']
                ]
            ];
        }
    }
    
    public function rest_get_results($request) {
        $user_id = get_current_user_id();
        $history = get_user_meta($user_id, 'scareshift_shift_history', true);
        
        if (!is_array($history) || empty($history)) {
            return [
                'success' => true,
                'shifts' => []
            ];
        }
        
        return [
            'success' => true,
            'shifts' => array_slice($history, -10) // Last 10 shifts
        ];
    }
    
    private function generate_wave($wave_num, &$shift) {
        $encounters = $this->get_wave_encounters($wave_num);
        
        // Track used scenarios to prevent repeats
        if (!isset($shift['used_scenarios'])) {
            $shift['used_scenarios'] = [];
        }
        
        // Filter out already-used scenarios
        $available = array_filter($encounters, function($enc) use ($shift) {
            $title = $enc['title'] ?? '';
            return !in_array($title, $shift['used_scenarios']);
        });
        
        // If all scenarios used, reset the pool
        if (empty($available)) {
            $shift['used_scenarios'] = [];
            $available = $encounters;
        }
        
        // Pick random from available (not used) scenarios
        $available_indexed = array_values($available);
        $encounter = $available_indexed[array_rand($available_indexed)];
        $encounter['id'] = uniqid('enc_');
        
        // Mark this scenario as used
        $shift['used_scenarios'][] = $encounter['title'];
        
        // Save encounter in shift data for later lookup
        if (!isset($shift['encounter_cache'])) {
            $shift['encounter_cache'] = [];
        }
        $shift['encounter_cache'][$encounter['id']] = $encounter;
        
        // Get crew stats for display
        $crew_stats = $this->get_crew_stats($shift);
        
        return [
            'wave_number' => $wave_num,
            'encounter' => $encounter,
            'momentum' => $shift['momentum'],
            'dread' => $shift['dread'] ?? 0,
            'fatigue' => $shift['fatigue'] ?? 0,
            'crew_stats' => $crew_stats  // NEW: Send crew stats to frontend
        ];
    }
    
    private function get_wave_encounters($wave_num) {
        // Wave 1: Mostly Green (easy)
        // Wave 2: Green + Yellow (medium)
        // Wave 3: Yellow + Orange (hard)
        
        $all_encounters = $this->get_all_encounters();
        
        switch ($wave_num) {
            case 1:
                return array_filter($all_encounters, function($enc) {
                    return $enc['difficulty'] === 'standard_crowd';
                });
            case 2:
                return array_filter($all_encounters, function($enc) {
                    return in_array($enc['difficulty'], ['standard_crowd', 'tough_crowd']);
                });
            case 3:
                return array_filter($all_encounters, function($enc) {
                    return in_array($enc['difficulty'], ['tough_crowd', 'hot_situation']);
                });
            default:
                return $all_encounters;
        }
    }
    
    private function get_encounter_by_id($id) {
        $user_id = get_current_user_id();
        $shift = get_user_meta($user_id, 'scareshift_active_shift', true);
        
        if (isset($shift['encounter_cache'][$id])) {
            return $shift['encounter_cache'][$id];
        }
        
        return null;
    }
    
    private function get_all_encounters() {
        // Load from config file
        $scenarios = include SCARESHIFT_DIR . 'config/scenario-library.php';
        return is_array($scenarios) ? $scenarios : [];
    }
    
    private function get_crew_stats($shift) {
        $user_id = get_current_user_id();
        $characters = get_user_meta($user_id, 'scareshift_characters', true);
        
        if (!is_array($characters) || !isset($shift['crew']) || empty($shift['crew'])) {
            // Return default stats if no crew
            return [
                'fear' => 5,
                'agility' => 5,
                'endurance' => 5,
                'voice' => 5,
                'presence' => 5,
                'crew_size' => 0
            ];
        }
        
        $crew_stats = [
            'fear' => 0,
            'agility' => 0,
            'endurance' => 0,
            'voice' => 0,
            'presence' => 0
        ];
        
        $crew_count = 0;
        
        foreach ($shift['crew'] as $crew_id) {
            foreach ($characters as $char) {
                if (isset($char['_id']) && $char['_id'] === $crew_id) {
                    $scores = $char['scores'] ?? [
                        'fear' => 5,
                        'agility' => 5,
                        'endurance' => 5,
                        'voice' => 5,
                        'presence' => 5
                    ];
                    
                    foreach ($scores as $stat => $value) {
                        if (isset($crew_stats[$stat])) {
                            $crew_stats[$stat] += $value;
                        }
                    }
                    $crew_count++;
                    break;
                }
            }
        }
        
        // Average the stats if multiple crew members
        if ($crew_count > 0) {
            foreach ($crew_stats as $stat => $total) {
                $crew_stats[$stat] = round($total / $crew_count, 2);
            }
        } else {
            // No valid crew found, use defaults
            $crew_stats = [
                'fear' => 5,
                'agility' => 5,
                'endurance' => 5,
                'voice' => 5,
                'presence' => 5
            ];
        }
        
        $crew_stats['crew_size'] = $crew_count;
        
        return $crew_stats;
    }
    
    private function calculate_outcome($shift, $encounter, $choice_data) {
        // Get crew stats
        $crew_stats = $this->get_crew_stats($shift);
        
        $base_outcome = [
            'guests_scared' => $choice_data['guests_scared'],
            'incidents_avoided' => $choice_data['incidents'] === 0 ? 1 : 0,
            'incidents_occurred' => $choice_data['incidents'],
            'momentum_change' => $choice_data['momentum'],
            'stat_changes' => $choice_data['stat_changes'],
            'message' => $choice_data['outcome'],
            'crew_stats' => $crew_stats  // NEW: Include crew stats in outcome
        ];
        
        // Apply crew stat bonuses
        // Higher relevant stats = better outcomes
        $stat_bonus = 0;
        foreach ($choice_data['stat_changes'] as $stat => $change) {
            if ($change > 0 && isset($crew_stats[$stat])) {
                // If crew is strong in this stat (>= 7), boost outcome
                if ($crew_stats[$stat] >= 7) {
                    $stat_bonus += 1;
                } elseif ($crew_stats[$stat] <= 3) {
                    // Weak in this stat, slight penalty
                    $stat_bonus -= 1;
                }
            }
        }
        
        if ($stat_bonus > 0) {
            $base_outcome['guests_scared'] += $stat_bonus;
            $base_outcome['message'] .= ' (Your crew excels here!)';
        } elseif ($stat_bonus < 0) {
            $base_outcome['guests_scared'] = max(0, $base_outcome['guests_scared'] + $stat_bonus);
            $base_outcome['message'] .= ' (Your crew struggles with this...)';
        }
        
        // Momentum bonus/penalty
        if ($shift['momentum'] > 5) {
            $base_outcome['guests_scared'] += 1;
            $base_outcome['message'] .= ' (Momentum bonus!)';
        } elseif ($shift['momentum'] < -3) {
            $base_outcome['guests_scared'] = max(0, $base_outcome['guests_scared'] - 1);
            $base_outcome['message'] .= ' (Struggling to recover momentum...)';
        }
        
        return $base_outcome;
    }
    
    private function complete_shift($user_id, $shift) {
        // Calculate final score
        $total_scared = $shift['guests_scared'];
        $total_avoided = $shift['incidents_avoided'];
        $total_incidents = $shift['incidents_occurred'];
        
        // Star rating
        $stars = 3; // Base
        if ($total_incidents === 0) $stars++;
        if ($total_scared >= 15) $stars++;
        $stars = min(5, max(1, $stars));
        
        // XP calculation
        $base_xp = 30; // Per shift
        $scared_bonus = $total_scared * 2;
        $safety_bonus = $total_avoided * 5;
        $incident_penalty = $total_incidents * -10;
        $star_bonus = ($stars - 3) * 10;
        
        $total_xp = max(10, $base_xp + $scared_bonus + $safety_bonus + $incident_penalty + $star_bonus);
        
        // Award XP
        $current_xp = (int) get_user_meta($user_id, 'scareshift_xp', true);
        $new_xp = $current_xp + $total_xp;
        update_user_meta($user_id, 'scareshift_xp', $new_xp);
        
        // Update character stats
        $this->update_character_stats($user_id, $shift);
        
        // Save to history
        $history = get_user_meta($user_id, 'scareshift_shift_history', true);
        if (!is_array($history)) $history = [];
        
        $history[] = [
            'shift_id' => $shift['shift_id'],
            'completed_at' => current_time('mysql'),
            'stars' => $stars,
            'guests_scared' => $total_scared,
            'incidents_avoided' => $total_avoided,
            'incidents_occurred' => $total_incidents,
            'xp_earned' => $total_xp
        ];
        
        $history = array_slice($history, -50);
        update_user_meta($user_id, 'scareshift_shift_history', $history);
        
        return [
            'stars' => $stars,
            'guests_scared' => $total_scared,
            'incidents_avoided' => $total_avoided,
            'incidents_occurred' => $total_incidents,
            'xp_earned' => $total_xp,
            'total_xp' => $new_xp,
            'run_number' => $shift['run_number'] ?? 1,
            'can_continue' => ($shift['run_number'] ?? 1) < 3 // Can continue if not on run 3 yet
        ];
    }
    
    private function update_character_stats($user_id, $shift) {
        // Aggregate stat changes from all waves
        $total_changes = [
            'fear' => 0,
            'agility' => 0,
            'endurance' => 0,
            'voice' => 0,
            'presence' => 0
        ];
        
        foreach ($shift['waves_completed'] as $wave) {
            if (isset($wave['outcome']['stat_changes'])) {
                foreach ($wave['outcome']['stat_changes'] as $stat => $change) {
                    if (isset($total_changes[$stat])) {
                        $total_changes[$stat] += $change;
                    }
                }
            }
        }
        
        // Apply to crew characters
        if (isset($shift['crew']) && is_array($shift['crew'])) {
            $characters = get_user_meta($user_id, 'scareshift_characters', true);
            if (!is_array($characters)) return;
            
            foreach ($shift['crew'] as $crew_char_id) {
                foreach ($characters as $idx => &$char) {
                    if (isset($char['_id']) && $char['_id'] === $crew_char_id) {
                        // Update stats
                        if (!isset($char['scores'])) $char['scores'] = [
                            'fear' => 5,
                            'agility' => 5,
                            'endurance' => 5,
                            'voice' => 5,
                            'presence' => 5
                        ];
                        
                        foreach ($total_changes as $stat => $change) {
                            if ($change != 0) {
                                $char['scores'][$stat] = max(1, min(10, $char['scores'][$stat] + $change));
                            }
                        }
                        
                        // Track growth
                        if (!isset($char['_stat_history'])) {
                            $char['_stat_history'] = [];
                        }
                        $char['_stat_history'][] = [
                            'date' => current_time('mysql'),
                            'changes' => $total_changes
                        ];
                        
                        break;
                    }
                }
            }
            
            update_user_meta($user_id, 'scareshift_characters', $characters);
        }
    }
}
