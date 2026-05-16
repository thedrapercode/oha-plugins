<?php
/**
 * Survey Processor
 * Handles survey rendering and processing logic
 */

if (!defined('ABSPATH')) {
    exit;
}

class OHA_Survey_Processor {
    
    private $data_handler;
    
    public function __construct() {
        $this->data_handler = new OHA_Survey_Data();
    }
    
    /**
     * Render survey interface
     */
    public function render_survey($survey_type, $atts = array()) {
        $survey = OHA_Survey_Definitions::get_survey($survey_type);
        
        if (!$survey) {
            return '<p>' . __('Survey not found.', 'oha-survey-hub') . '</p>';
        }
        
        // Check if this is a direct link (bypass selector page)
        $is_direct = isset($atts['direct_link']) && $atts['direct_link'];
        
        ob_start();
        ?>
        <div id="oha-survey-container" class="oha-survey-container" data-survey-type="<?php echo esc_attr($survey_type); ?>">
            <div class="oha-survey-header">
                <h2 class="oha-survey-title"><?php echo esc_html($survey['title']); ?></h2>
                <p class="oha-survey-description"><?php echo esc_html($survey['description']); ?></p>
                
                <div class="oha-progress-container">
                    <div class="oha-progress-bar">
                        <div class="oha-progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="oha-progress-text">
                        <span class="oha-current-page">1</span> of <span class="oha-total-pages"><?php echo count($survey['pages']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="oha-survey-content">
                <?php $this->render_survey_pages($survey, $survey_type); ?>
            </div>
            
            <div class="oha-survey-navigation">
                <button type="button" class="oha-btn oha-btn-secondary oha-btn-prev" style="display: none;">
                    <span class="oha-btn-icon">←</span>
                    <?php _e('Previous', 'oha-survey-hub'); ?>
                </button>
                
                <button type="button" class="oha-btn oha-btn-primary oha-btn-save" style="display: none;">
                    <span class="oha-btn-icon">💾</span>
                    <?php _e('Save Progress', 'oha-survey-hub'); ?>
                </button>
                
                <button type="button" class="oha-btn oha-btn-primary oha-btn-next">
                    <?php _e('Next', 'oha-survey-hub'); ?>
                    <span class="oha-btn-icon">→</span>
                </button>
                
                <button type="button" class="oha-btn oha-btn-accent oha-btn-submit" style="display: none;">
                    <span class="oha-btn-icon">✓</span>
                    <?php _e('Submit Survey', 'oha-survey-hub'); ?>
                </button>
            </div>
            
            <div class="oha-survey-messages"></div>
        </div>
        
        <?php if (!$is_direct): ?>
        <div class="oha-survey-trust-info">
            <h4><?php _e('Your Privacy Matters', 'oha-survey-hub'); ?></h4>
            <ul>
                <li><?php _e('This survey is completely anonymous', 'oha-survey-hub'); ?></li>
                <li><?php _e('We only collect your email to prevent duplicate responses and send confirmation', 'oha-survey-hub'); ?></li>
                <li><?php _e('Your individual responses will never be shared or published', 'oha-survey-hub'); ?></li>
                <li><?php _e('You can save your progress and return later', 'oha-survey-hub'); ?></li>
            </ul>
        </div>
        <?php endif; ?>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render survey pages
     */
    private function render_survey_pages($survey, $survey_type) {
        foreach ($survey['pages'] as $page_index => $page) {
            $is_first_page = $page_index === 0;
            $display_style = $is_first_page ? 'block' : 'none';
            
            ?>
            <div class="oha-survey-page" data-page="<?php echo $page_index; ?>" style="display: <?php echo $display_style; ?>;">
                
                <?php if ($is_first_page): ?>
                    <!-- Email collection on first page -->
                    <div class="oha-email-section">
                        <div class="oha-field-group oha-field-required">
                            <label for="oha-survey-email" class="oha-field-label">
                                <?php _e('Email Address', 'oha-survey-hub'); ?>
                                <span class="oha-required-indicator">*</span>
                            </label>
                            <input type="email" id="oha-survey-email" name="survey_email" class="oha-field-input" required>
                            <div class="oha-field-help">
                                <?php _e('Required for confirmation and to prevent duplicate responses. Your email will not be associated with your answers.', 'oha-survey-hub'); ?>
                            </div>
                        </div>
                        
                        <div class="oha-field-group">
                            <label class="oha-checkbox-label">
                                <input type="checkbox" id="oha-save-progress" name="save_progress" value="1">
                                <span class="oha-checkbox-custom"></span>
                                <?php _e('Save my progress so I can return later', 'oha-survey-hub'); ?>
                            </label>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($page['title'])): ?>
                    <h3 class="oha-page-title"><?php echo esc_html($page['title']); ?></h3>
                <?php endif; ?>
                
                <?php if (!empty($page['description'])): ?>
                    <p class="oha-page-description"><?php echo esc_html($page['description']); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($page['questions'])): ?>
                    <div class="oha-questions-container">
                        <?php foreach ($page['questions'] as $question): ?>
                            <?php $this->render_question($question); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }
    }
    
    /**
     * Render individual question
     */
    private function render_question($question) {
        $required = isset($question['required']) && $question['required'];
        $priority = isset($question['priority']) ? $question['priority'] : '';
        $depends_on = isset($question['depends_on']) ? $question['depends_on'] : null;
        
        $field_classes = array('oha-field-group');
        if ($required) {
            $field_classes[] = 'oha-field-required';
        }
        if ($priority === 'high') {
            $field_classes[] = 'oha-field-priority';
        }
        if ($depends_on) {
            $field_classes[] = 'oha-field-conditional';
        }
        
        ?>
        <div class="<?php echo implode(' ', $field_classes); ?>" 
             data-question-id="<?php echo esc_attr($question['id']); ?>"
             <?php if ($depends_on): ?>
                data-depends-on="<?php echo esc_attr($depends_on['field']); ?>"
                data-depends-value="<?php echo esc_attr(is_array($depends_on['value']) ? implode(',', $depends_on['value']) : $depends_on['value']); ?>"
                style="display: none;"
             <?php endif; ?>>
            
            <label class="oha-field-label">
                <?php echo esc_html($question['question']); ?>
                <?php if ($required): ?>
                    <span class="oha-required-indicator">*</span>
                <?php endif; ?>
                <?php if ($priority === 'high'): ?>
                    <span class="oha-priority-indicator" title="<?php _e('High Priority Question', 'oha-survey-hub'); ?>">★</span>
                <?php endif; ?>
            </label>
            
            <?php $this->render_question_input($question); ?>
            
            <?php if (!empty($question['help'])): ?>
                <div class="oha-field-help"><?php echo esc_html($question['help']); ?></div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render question input based on type
     */
    private function render_question_input($question) {
        $type = $question['type'];
        $id = $question['id'];
        $required = isset($question['required']) && $question['required'];
        
        switch ($type) {
            case 'text':
                ?>
                <input type="text" 
                       id="<?php echo esc_attr($id); ?>" 
                       name="<?php echo esc_attr($id); ?>" 
                       class="oha-field-input"
                       <?php echo $required ? 'required' : ''; ?>>
                <?php
                break;
                
            case 'email':
                ?>
                <input type="email" 
                       id="<?php echo esc_attr($id); ?>" 
                       name="<?php echo esc_attr($id); ?>" 
                       class="oha-field-input"
                       <?php echo $required ? 'required' : ''; ?>>
                <?php
                break;
                
            case 'textarea':
                ?>
                <textarea id="<?php echo esc_attr($id); ?>" 
                          name="<?php echo esc_attr($id); ?>" 
                          class="oha-field-textarea"
                          rows="4"
                          <?php echo $required ? 'required' : ''; ?>></textarea>
                <?php
                break;
                
            case 'select':
                ?>
                <select id="<?php echo esc_attr($id); ?>" 
                        name="<?php echo esc_attr($id); ?>" 
                        class="oha-field-select"
                        <?php echo $required ? 'required' : ''; ?>>
                    <option value=""><?php _e('Select an option...', 'oha-survey-hub'); ?></option>
                    <?php foreach ($question['options'] as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php
                break;
                
            case 'radio':
                ?>
                <div class="oha-radio-group">
                    <?php foreach ($question['options'] as $value => $label): ?>
                        <label class="oha-radio-label">
                            <input type="radio" 
                                   name="<?php echo esc_attr($id); ?>" 
                                   value="<?php echo esc_attr($value); ?>"
                                   class="oha-radio-input"
                                   <?php echo $required ? 'required' : ''; ?>>
                            <span class="oha-radio-custom"></span>
                            <span class="oha-radio-text"><?php echo esc_html($label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php
                break;
                
            case 'checkbox':
                $max_selections = isset($question['max_selections']) ? intval($question['max_selections']) : 0;
                $checkbox_index = 0;
                ?>
                <div class="oha-checkbox-group" <?php echo $max_selections ? 'data-max-selections="' . $max_selections . '"' : ''; ?>>
                    <?php foreach ($question['options'] as $value => $label): $checkbox_index++; ?>
                        <label class="oha-checkbox-label">
                            <input type="checkbox"
                                   name="<?php echo esc_attr($id); ?>[]"
                                   value="<?php echo esc_attr($value); ?>"
                                   class="oha-checkbox-input"
                                   <?php echo ($required && $checkbox_index === 1) ? 'required' : ''; ?>>
                            <span class="oha-checkbox-custom"></span>
                            <span class="oha-checkbox-text"><?php echo esc_html($label); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php
                break;
                
            case 'scale':
                $min = isset($question['scale_min']) ? intval($question['scale_min']) : 1;
                $max = isset($question['scale_max']) ? intval($question['scale_max']) : 5;
                $labels = isset($question['scale_labels']) ? $question['scale_labels'] : array();
                ?>
                <div class="oha-scale-group">
                    <div class="oha-scale-labels">
                        <?php if (isset($labels[$min])): ?>
                            <span class="oha-scale-label-start"><?php echo esc_html($labels[$min]); ?></span>
                        <?php endif; ?>
                        <?php if (isset($labels[$max])): ?>
                            <span class="oha-scale-label-end"><?php echo esc_html($labels[$max]); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="oha-scale-options">
                        <?php for ($i = $min; $i <= $max; $i++): ?>
                            <label class="oha-scale-option">
                                <input type="radio" 
                                       name="<?php echo esc_attr($id); ?>" 
                                       value="<?php echo $i; ?>"
                                       class="oha-scale-input"
                                       <?php echo $required ? 'required' : ''; ?>>
                                <span class="oha-scale-number"><?php echo $i; ?></span>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php
                break;
        }
    }
    
    /**
     * Save survey progress via AJAX
     */
    public function save_progress($post_data) {
        try {
            // Validate nonce
            if (!wp_verify_nonce($post_data['nonce'], 'oha_survey_nonce')) {
                throw new Exception(__('Security check failed', 'oha-survey-hub'));
            }
            
            // Validate required fields
            $required_fields = array('survey_type', 'email', 'session_id', 'current_page', 'total_pages');
            foreach ($required_fields as $field) {
                if (empty($post_data[$field])) {
                    throw new Exception(sprintf(__('Missing required field: %s', 'oha-survey-hub'), $field));
                }
            }
            
            // Validate email
            if (!is_email($post_data['email'])) {
                throw new Exception(__('Invalid email address', 'oha-survey-hub'));
            }
            
            // Prepare responses data
            $responses = isset($post_data['responses']) ? $post_data['responses'] : array();
            
            // Save to database
            $result = $this->data_handler->save_progress(array(
                'survey_type' => sanitize_text_field($post_data['survey_type']),
                'email' => sanitize_email($post_data['email']),
                'session_id' => sanitize_text_field($post_data['session_id']),
                'responses' => $responses,
                'current_page' => intval($post_data['current_page']),
                'total_pages' => intval($post_data['total_pages'])
            ));
            
            if (!$result) {
                throw new Exception(__('Failed to save progress', 'oha-survey-hub'));
            }
            
            return array(
                'success' => true,
                'message' => __('Progress saved successfully!', 'oha-survey-hub')
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Submit completed survey via AJAX
     */
    public function submit_survey($post_data) {
        try {
            // Validate nonce
            if (!wp_verify_nonce($post_data['nonce'], 'oha_survey_nonce')) {
                throw new Exception(__('Security check failed', 'oha-survey-hub'));
            }
            
            // Validate required fields
            $required_fields = array('survey_type', 'email', 'session_id', 'total_pages');
            foreach ($required_fields as $field) {
                if (empty($post_data[$field])) {
                    throw new Exception(sprintf(__('Missing required field: %s', 'oha-survey-hub'), $field));
                }
            }
            
            // Validate email
            if (!is_email($post_data['email'])) {
                throw new Exception(__('Invalid email address', 'oha-survey-hub'));
            }
            
            // Validate survey completion
            $responses = isset($post_data['responses']) ? $post_data['responses'] : array();

            // jQuery may POST objects or a JSON string depending on implementation.
            if (is_string($responses)) {
                $decoded = json_decode($responses, true);
                if (is_array($decoded)) {
                    $responses = $decoded;
                }
            }

            if (!is_array($responses)) {
                $responses = array();
            }
            
            // Normalize checkbox field names for jQuery form serialization.
            // JS now strips [] from checkbox keys before sending, so PHP receives
            // "field" with an array value rather than "field[]" with scalar values.
            // We still handle the legacy bracket-key format for safety.
            $normalized_responses = array();
            foreach ($responses as $key => $value) {
                if (preg_match('/^(.+)\[.*\]$/', $key, $matches)) {
                    // Legacy format: key contains [] (e.g. "field[]" or "field[0]")
                    $clean_key = $matches[1];
                    if (!isset($normalized_responses[$clean_key])) {
                        $normalized_responses[$clean_key] = array();
                    }
                    if (is_array($value)) {
                        // PHP already parsed the indexed values into an array
                        foreach ($value as $v) {
                            if ($v !== '' && $v !== null) {
                                $normalized_responses[$clean_key][] = $v;
                            }
                        }
                    } elseif ($value !== '' && $value !== null) {
                        $normalized_responses[$clean_key][] = $value;
                    }
                } elseif (is_array($value)) {
                    // Clean key with array value — checkbox data sent without [] suffix
                    $normalized_responses[$key] = array_values(
                        array_filter($value, function($v) { return $v !== '' && $v !== null; })
                    );
                } else {
                    // Regular scalar field
                    $normalized_responses[$key] = $value;
                }
            }
            $responses = $normalized_responses;
            
            $survey = OHA_Survey_Definitions::get_survey($post_data['survey_type']);
            
            if (!$survey) {
                throw new Exception(__('Invalid survey type', 'oha-survey-hub'));
            }
            
            // Check required questions are answered
            $this->validate_required_questions($survey, $responses);
            
            // Submit to database
            $result = $this->data_handler->submit_survey(array(
                'survey_type' => sanitize_text_field($post_data['survey_type']),
                'email' => sanitize_email($post_data['email']),
                'session_id' => sanitize_text_field($post_data['session_id']),
                'responses' => $responses,
                'total_pages' => intval($post_data['total_pages'])
            ));
            
            if (isset($result['error'])) {
                throw new Exception($result['error']);
            }
            
            return array(
                'success' => true,
                'message' => __('Survey submitted successfully! Thank you for your participation.', 'oha-survey-hub')
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Validate required questions are answered
     */
    private function validate_required_questions($survey, $responses) {
        $missing_required = array();
        
        foreach ($survey['pages'] as $page) {
            if (empty($page['questions'])) continue;
            
            foreach ($page['questions'] as $question) {
                if (!isset($question['required']) || !$question['required']) continue;
                
                // Check if question should be visible based on conditional logic
                if (!$this->is_question_applicable($question, $responses)) {
                    continue; // Skip validation for conditionally hidden questions
                }
                
                $question_id = $question['id'];
                // Treat 0 / "0" as valid answers (empty() would incorrectly flag them).
                if (!array_key_exists($question_id, $responses)) {
                    $missing_required[] = $question['question'];
                    continue;
                }

                $answer = $responses[$question_id];

                // Empty arrays (checkbox groups) are missing.
                if (is_array($answer)) {
                    if (count($answer) === 0) {
                        $missing_required[] = $question['question'];
                    }
                    continue;
                }

                // Strings are missing only when truly blank ("0" is valid).
                if (is_string($answer)) {
                    if (trim($answer) === '') {
                        $missing_required[] = $question['question'];
                    }
                    continue;
                }

                // For other scalar types (int/float/bool), consider them present as long as set.
            }
        }
        
        if (!empty($missing_required)) {
            throw new Exception(
                sprintf(
                    __('Please answer all required questions. Missing: %s', 'oha-survey-hub'),
                    implode(', ', array_slice($missing_required, 0, 3)) . (count($missing_required) > 3 ? '...' : '')
                )
            );
        }
    }
    
    /**
     * Check if a question should be applicable based on conditional dependencies
     */
    private function is_question_applicable($question, $responses) {
        // If no dependencies, question is always applicable
        if (!isset($question['depends_on'])) {
            return true;
        }
        
        $depends_on = $question['depends_on'];
        $parent_field = $depends_on['field'];
        $required_value = $depends_on['value'];
        
        // Check if parent field has the required value
        if (!isset($responses[$parent_field])) {
            return false; // Parent field not answered, so this question doesn't apply
        }
        
        $parent_answer = $responses[$parent_field];
        
        // If the required value is an array of acceptable values, match any.
        if (is_array($required_value)) {
            if (is_array($parent_answer)) {
                return count(array_intersect($required_value, $parent_answer)) > 0;
            }
            return in_array($parent_answer, $required_value, true);
        }

        // Handle array parent answers (checkbox groups)
        if (is_array($parent_answer)) {
            return in_array($required_value, $parent_answer, true);
        }

        // Handle single values
        return $parent_answer === $required_value;
    }
    
    /**
     * Get survey progress for resuming
     */
    public function get_survey_progress($email, $survey_type) {
        return $this->data_handler->get_survey_progress($email, $survey_type);
    }
}
