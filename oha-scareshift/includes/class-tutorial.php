<?php
/**
 * Tutorial Component
 */

if (!defined('ABSPATH')) exit;

class ScareShift_Tutorial {
    
    public function __construct() {
        add_action('init', [$this, 'register_shortcodes']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }
    
    public function register_shortcodes() {
        add_shortcode('scareshift_tutorial', [$this, 'render_tutorial']);
    }
    
    public function render_tutorial($atts = [], $content = '') {
        wp_enqueue_style('scareshift-style');
        
        ob_start();
        ?>
        <div class="scareshift-wrap">
            <div class="scareshift-tutorial">
                <h2>Welcome to ScareShift!</h2>
                
                <div class="scareshift-tutorial-section">
                    <h3>🎭 What is ScareShift?</h3>
                    <p>ScareShift is a cooperative haunt-actor RPG system where you:</p>
                    <ul>
                        <li>Create unique scare characters with stats like Fear, Agility, Endurance, Voice, and Presence</li>
                        <li>Play daily scenarios to earn XP and level up</li>
                        <li>Practice your craft in a fun, safe, off-season environment</li>
                        <li>Learn from choices that mirror real haunt situations</li>
                    </ul>
                </div>
                
                <div class="scareshift-tutorial-section">
                    <h3>⚡ Quick Start Guide</h3>
                    <div class="scareshift-steps">
                        <div class="scareshift-step">
                            <div class="scareshift-step-number">1</div>
                            <div class="scareshift-step-content">
                                <h4>Create Your Character</h4>
                                <p>Answer questions about your scare style to generate a character with unique stats.</p>
                            </div>
                        </div>
                        
                        <div class="scareshift-step">
                            <div class="scareshift-step-number">2</div>
                            <div class="scareshift-step-content">
                                <h4>Play Daily Scenarios</h4>
                                <p>Each day, face a new haunt situation. Choose your response (A, B, or C) and see the outcome.</p>
                            </div>
                        </div>
                        
                        <div class="scareshift-step">
                            <div class="scareshift-step-number">3</div>
                            <div class="scareshift-step-content">
                                <h4>Earn XP & Level Up</h4>
                                <p>Completing scenarios earns you XP. As you level up, you unlock new abilities and skills.</p>
                            </div>
                        </div>
                        
                        <div class="scareshift-step">
                            <div class="scareshift-step-number">4</div>
                            <div class="scareshift-step-content">
                                <h4>Save & Share</h4>
                                <p>Export your character cards as PNG or PDF to share with your haunt crew.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="scareshift-tutorial-section">
                    <h3>📊 Understanding Stats</h3>
                    <div class="scareshift-stat-guide">
                        <div class="scareshift-stat-item">
                            <div class="scareshift-stat-icon">😱</div>
                            <div class="scareshift-stat-info">
                                <strong>Fear Factor</strong>
                                <p>How effectively you intimidate and unsettle guests through presence and unpredictability.</p>
                            </div>
                        </div>
                        
                        <div class="scareshift-stat-item">
                            <div class="scareshift-stat-icon">🏃</div>
                            <div class="scareshift-stat-info">
                                <strong>Agility</strong>
                                <p>Physical movement, flexibility, and reaction time. Affected by costume weight and mobility.</p>
                            </div>
                        </div>
                        
                        <div class="scareshift-stat-item">
                            <div class="scareshift-stat-icon">💪</div>
                            <div class="scareshift-stat-info">
                                <strong>Endurance</strong>
                                <p>Stamina for long performances. Improved by fitness habits and proper breaks.</p>
                            </div>
                        </div>
                        
                        <div class="scareshift-stat-item">
                            <div class="scareshift-stat-icon">🎤</div>
                            <div class="scareshift-stat-info">
                                <strong>Voice</strong>
                                <p>Power, clarity, and creativity of vocalization. Protected by warmups and vocal care.</p>
                            </div>
                        </div>
                        
                        <div class="scareshift-stat-item">
                            <div class="scareshift-stat-icon">✨</div>
                            <div class="scareshift-stat-info">
                                <strong>Presence</strong>
                                <p>Stage awareness, body language, and emotional impact. Grows with experience.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="scareshift-tutorial-section">
                    <h3>🎯 Game Philosophy</h3>
                    <p><strong>ScareShift is cooperative, not competitive.</strong> Your goal is to:</p>
                    <ul>
                        <li>Learn and improve your craft</li>
                        <li>Try new character types safely</li>
                        <li>Build confidence for the haunt season</li>
                        <li>Have fun with the community</li>
                    </ul>
                    <p>There are no "wrong" choices - only different paths with different lessons.</p>
                </div>
                
                <div class="scareshift-tutorial-section">
                    <h3>🔒 Safety First</h3>
                    <p>ScareShift teaches real haunt safety through gameplay:</p>
                    <ul>
                        <li><strong>Voice care:</strong> Screaming without warmups reduces your Voice stat</li>
                        <li><strong>Physical safety:</strong> Heavy costumes with high activity lower Endurance</li>
                        <li><strong>Guest respect:</strong> Unsafe choices (grabbing, blocking exits) reduce scores</li>
                        <li><strong>Self-care:</strong> Taking breaks and staying hydrated improve performance</li>
                    </ul>
                </div>
                
                <div class="scareshift-cta">
                    <a href="#" class="scareshift-btn scareshift-btn-primary" onclick="localStorage.setItem('scareshift_tutorial_seen', 'yes'); return true;">
                        I'm Ready to Start!
                    </a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function register_rest_routes() {
        register_rest_route('scareshift/v1', '/tutorial/complete', [
            'methods' => 'POST',
            'permission_callback' => function() { return is_user_logged_in(); },
            'callback' => function($request) {
                $user_id = get_current_user_id();
                update_user_meta($user_id, 'scareshift_tutorial_completed', current_time('mysql'));
                
                return [
                    'success' => true,
                    'message' => 'Tutorial marked as completed'
                ];
            }
        ]);
    }
}
