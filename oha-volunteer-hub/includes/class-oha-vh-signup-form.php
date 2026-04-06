<?php
/**
 * OHA Volunteer Hub - Signup Form Class
 * Handles public volunteer signups (no login required)
 * 
 * Usage: [oha_volunteer_signup]
 */

namespace OHA\VH;

if (!defined('ABSPATH')) { exit; }

class SignupForm {

    public static function init() {
        add_shortcode('oha_volunteer_signup', [__CLASS__, 'render_form']);
        add_action('wp_ajax_oha_vh_signup', [__CLASS__, 'handle_signup']);
        add_action('wp_ajax_nopriv_oha_vh_signup', [__CLASS__, 'handle_signup']);
    }

    /**
     * Render the public signup form
     */
    public static function render_form($atts) {
        $atts = shortcode_atts([
            'title' => 'Join the OHA Convention Crew!',
            'show_areas' => 'yes',
            'show_emergency' => 'no'  // Default to NO for 18+ policy
        ], $atts);

        wp_enqueue_style('oha-vh');
        wp_enqueue_script('oha-vh');

        // Get available areas from settings
        $areas = self::get_convention_areas();
        
        // Get current user data if logged in
        $current_user = wp_get_current_user();
        $user_first_name = '';
        $user_last_name = '';
        $user_email = '';
        
        if ($current_user->ID != 0) {
            // User is logged in - get their data
            $user_first_name = $current_user->first_name ?: $current_user->user_firstname;
            $user_last_name = $current_user->last_name ?: $current_user->user_lastname;
            $user_email = $current_user->user_email;
        }

        ob_start();
        ?>
        <div class="oha-vh oha-vh-signup">
            <div class="oha-vh-signup-header">
                <h2><?php echo esc_html($atts['title']); ?></h2>
                <p class="oha-vh-tagline" style="color: #ffffff !important;">Be a Haunt Hero! Help make OHA Convention 2026 unforgettable!</p>
            </div>

            <form id="oha-volunteer-signup-form" class="oha-vh-signup-form">
                <input type="hidden" name="action" value="oha_vh_signup">
                <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('oha_vh_signup_nonce')); ?>">

                <!-- Personal Information -->
                <div class="oha-vh-section">
                    <h3>Your Info</h3>
                    
                    <div class="oha-vh-form-row">
                        <div class="oha-vh-form-group">
                            <label for="first_name">First Name <span class="required">*</span></label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr($user_first_name); ?>" required>
                        </div>
                        <div class="oha-vh-form-group">
                            <label for="last_name">Last Name <span class="required">*</span></label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr($user_last_name); ?>" required>
                        </div>
                    </div>

                    <div class="oha-vh-form-row">
                        <div class="oha-vh-form-group">
                            <label for="email">Email <span class="required">*</span></label>
                            <input type="email" id="email" name="email" value="<?php echo esc_attr($user_email); ?>" required>
                            <small>We'll send your confirmation and assignment details here</small>
                        </div>
                        <div class="oha-vh-form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone">
                            <small>Optional - for last-minute updates</small>
                        </div>
                    </div>
                </div>

                <!-- Convention Areas -->
                <?php if ($atts['show_areas'] === 'yes' && !empty($areas)): ?>
                <div class="oha-vh-section">
                    <h3>Where Would You Like to Haunt?</h3>
                    <p style="color: #000;">Select areas that interest you - we'll do our best to match your preferences, but final assignments depend on event needs. Check all that appeal to you!</p>
                    
                    <div class="oha-vh-areas-grid">
                        <?php foreach ($areas as $area): ?>
                        <label class="oha-vh-area-card">
                            <input type="checkbox" name="areas[]" value="<?php echo esc_attr($area['id']); ?>">
                            <div class="oha-vh-area-content">
                                <?php if (!empty($area['icon'])): ?>
                                <span class="oha-vh-area-icon"><?php echo esc_html($area['icon']); ?></span>
                                <?php endif; ?>
                                <h4><?php echo esc_html($area['name']); ?></h4>
                                <p><?php echo esc_html($area['description']); ?></p>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Experience Level -->
                <div class="oha-vh-section">
                    <h3>Your Experience</h3>
                    <div class="oha-vh-form-group">
                        <label>Have you volunteered at conventions before?</label>
                        <div class="oha-vh-radio-group">
                            <label>
                                <input type="radio" name="experience" value="newbie" checked>
                                <span>First Timer - I'm ready to learn!</span>
                            </label>
                            <label>
                                <input type="radio" name="experience" value="experienced">
                                <span>Been There, Done That - I've got this!</span>
                            </label>
                            <label>
                                <input type="radio" name="experience" value="veteran">
                                <span>Convention Veteran - Put me anywhere!</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Special Skills & Availability -->
                <div class="oha-vh-section">
                    <h3>Special Skills & Notes</h3>
                    <div class="oha-vh-form-group">
                        <label for="special_skills">Do you have any special skills or certifications?</label>
                        <textarea id="special_skills" name="special_skills" rows="3" 
                            placeholder="Example: First Aid certified, forklift license, graphic design, A/V experience..."></textarea>
                    </div>

                    <div class="oha-vh-form-group">
                        <label><strong>When can you help? Check all times you're available:</strong></label>
                        <div class="oha-vh-availability-grid" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin: 15px 0; background: #f9f9f9; padding: 20px; border-radius: 8px;">
                            
                            <!-- Friday May 8th -->
                            <div class="availability-day">
                                <h4 style="margin: 0 0 10px 0; color: #333; font-size: 16px; border-bottom: 2px solid #2271b1; padding-bottom: 5px;">
                                    📅 Friday, May 8
                                </h4>
                                <label style="display: block; margin: 8px 0; cursor: pointer;">
                                    <input type="checkbox" name="availability_days[]" value="friday_setup" style="margin-right: 8px;">
                                    <span style="color: #000;">Setup Crew (4PM-8PM)</span>
                                </label>
                            </div>

                            <!-- Saturday May 9th -->
                            <div class="availability-day">
                                <h4 style="margin: 0 0 10px 0; color: #333; font-size: 16px; border-bottom: 2px solid #2271b1; padding-bottom: 5px;">
                                    📅 Saturday, May 9
                                </h4>
                                <label style="display: block; margin: 8px 0; cursor: pointer;">
                                    <input type="checkbox" name="availability_days[]" value="saturday_am" style="margin-right: 8px;">
                                    <span style="color: #000;">Morning (8AM-12PM)</span>
                                </label>
                                <label style="display: block; margin: 8px 0; cursor: pointer;">
                                    <input type="checkbox" name="availability_days[]" value="saturday_pm" style="margin-right: 8px;">
                                    <span style="color: #000;">Afternoon (12PM-6PM)</span>
                                </label>
                                <label style="display: block; margin: 8px 0; cursor: pointer;">
                                    <input type="checkbox" name="availability_days[]" value="saturday_evening" style="margin-right: 8px;">
                                    <span style="color: #000;">Evening (6PM-10PM)</span>
                                </label>
                            </div>

                            <!-- Sunday May 10th -->
                            <div class="availability-day">
                                <h4 style="margin: 0 0 10px 0; color: #333; font-size: 16px; border-bottom: 2px solid #2271b1; padding-bottom: 5px;">
                                    📅 Sunday, May 10
                                </h4>
                                <label style="display: block; margin: 8px 0; cursor: pointer;">
                                    <input type="checkbox" name="availability_days[]" value="sunday_am" style="margin-right: 8px;">
                                    <span style="color: #000;">Morning (8AM-12PM)</span>
                                </label>
                                <label style="display: block; margin: 8px 0; cursor: pointer;">
                                    <input type="checkbox" name="availability_days[]" value="sunday_pm" style="margin-right: 8px;">
                                    <span style="color: #000;">Afternoon (12PM-6PM)</span>
                                </label>
                            </div>
                        </div>

                        <label for="availability_notes" style="margin-top: 15px; display: block; color: #000;">
                            <strong>Additional notes about your availability:</strong>
                        </label>
                        <textarea id="availability_notes" name="availability_notes" rows="2" 
                            placeholder="Example: Can only do setup Friday, need to leave early Sunday, prefer indoor assignments..." 
                            style="width: 100%; padding: 10px; margin-top: 5px;"></textarea>
                    </div>

                    <div class="oha-vh-form-group">
                        <label for="how_did_you_hear" style="color: #000;"><strong>How did you learn about us?</strong></label>
                        <input type="text" id="how_did_you_hear" name="how_did_you_hear" 
                            placeholder="Example: Facebook, friend referral, website, haunters forum..." 
                            style="width: 100%; padding: 10px; margin-top: 5px;">
                    </div>
                </div>

                <!-- Emergency Contact (Optional via shortcode) -->
                <?php if ($atts['show_emergency'] === 'yes'): ?>
                <div class="oha-vh-section">
                    <h3>Emergency Contact & Age Verification</h3>
                    
                    <div class="oha-vh-form-group">
                        <label for="birthdate" style="color: #000;"><strong>Birthdate</strong></label>
                        <input type="date" id="birthdate" name="birthdate" 
                            style="width: 100%; padding: 10px; margin-top: 5px;" required>
                    </div>

                    <div style="background: #fff3cd; border: 2px solid #ffc107; padding: 15px; margin: 15px 0; border-radius: 8px;">
                        <p style="color: #856404; font-weight: bold; margin: 0; text-align: center; font-size: 16px;">
                            ⚠️ VOLUNTEERS UNDER THE AGE OF 18 NEED A PARENT PRESENT AT ALL TIMES ⚠️
                        </p>
                    </div>

                    <div class="oha-vh-form-group">
                        <label for="emergency_contact" style="color: #000;"><strong>Emergency Contact Name & Phone</strong></label>
                        <input type="text" id="emergency_contact" name="emergency_contact" 
                            placeholder="Example: Jane Doe: 555-123-4567" 
                            style="width: 100%; padding: 10px; margin-top: 5px;" required>
                    </div>

                    <div class="oha-vh-form-group">
                        <label style="color: #000;">
                            <strong>Will your emergency contact be at this event?</strong>
                        </label>
                        <div style="margin-top: 10px;">
                            <label style="display: inline-block; margin-right: 20px; cursor: pointer;">
                                <input type="radio" name="emergency_contact_attending" value="yes" style="margin-right: 5px;">
                                <span style="color: #000;">Yes</span>
                            </label>
                            <label style="display: inline-block; cursor: pointer;">
                                <input type="radio" name="emergency_contact_attending" value="no" style="margin-right: 5px;">
                                <span style="color: #000;">No</span>
                            </label>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Volunteer Rewards & T-Shirt Size -->
                <div class="oha-vh-section" style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border: 2px solid #ffc107; border-radius: 8px; padding: 25px; margin-bottom: 25px;">
                    <h3 style="margin-top: 0; color: #856404;">🎁 Volunteer Rewards & Benefits!</h3>
                    
                    <div style="background: white; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
                        <h4 style="margin: 0 0 15px 0; color: #000; font-size: 18px;">Thank You for Volunteering! 🙌</h4>
                        
                        <div style="margin-bottom: 15px; padding: 15px; background: #e3f2fd; border-left: 4px solid #2271b1; border-radius: 4px;">
                            <strong style="color: #000; font-size: 16px;">✅ One 4-Hour Shift:</strong>
                            <p style="margin: 5px 0 0 0; color: #000;">Free access to the show floor on your signup day!</p>
                        </div>
                        
                        <div style="padding: 15px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px;">
                            <strong style="color: #000; font-size: 16px;">🎉 Eight Hours (2 Shifts):</strong>
                            <p style="margin: 5px 0 0 0; color: #000;">Show floor access PLUS an exclusive OHA Volunteer T-shirt!</p>
                        </div>
                        
                        <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-radius: 4px; text-align: center;">
                            <small style="color: #856404;">
                                📋 <a href="/convention/2026-convention/volunteers/volunteer-handbook/" target="_blank" style="color: #d63031; font-weight: 600;">Review the Volunteer Handbook</a> for complete details
                            </small>
                        </div>
                    </div>

                    <div class="oha-vh-form-group">
                        <label for="tshirt_size" style="color: #000;">
                            <strong>👕 T-Shirt Size</strong>
                            <span style="color: #856404; font-size: 14px; display: block; margin-top: 5px;">
                                (T-shirts awarded for 8+ hours of volunteer time - please select your size!)
                            </span>
                        </label>
                        <select id="tshirt_size" name="tshirt_size" required
                                style="width: 100%; padding: 10px; margin-top: 5px; font-size: 14px;">
                            <option value="">-- Select Your Size --</option>
                            <option value="YS">Youth Small</option>
                            <option value="YM">Youth Medium</option>
                            <option value="YL">Youth Large</option>
                            <option value="AS">Adult Small</option>
                            <option value="AM">Adult Medium</option>
                            <option value="AL">Adult Large</option>
                            <option value="AXL">Adult XL</option>
                            <option value="A2XL">Adult 2XL</option>
                            <option value="A3XL">Adult 3XL</option>
                            <option value="A4XL">Adult 4XL</option>
                        </select>
                        <small style="display: block; margin-top: 5px; color: #666;">
                            We order shirts after volunteer assignments are finalized. Select your size now even if you're only doing one shift - you might add more hours later!
                        </small>
                    </div>
                </div>

                <!-- Account Linking (if user is logged in) -->
                <?php if (is_user_logged_in()): ?>
                <div class="oha-vh-section oha-vh-section-highlight">
                    <label>
                        <input type="checkbox" name="link_account" value="yes" checked>
                        Link this volunteer signup to my ohiohaunters.com account
                    </label>
                </div>
                <?php else: ?>
                <div class="oha-vh-section oha-vh-section-highlight">
                    <p style="color: #000;"><strong>Already have an ohiohaunters.com account?</strong></p>
                    <p>
                        <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="button">
                            Log in first to link your account
                        </a>
                    </p>
                    <small style="color: #000;">Don't worry! You can link it later from your account dashboard.</small>
                </div>
                <?php endif; ?>

                <!-- Submit -->
                <div class="oha-vh-submit-section">
                    <button type="submit" class="button button-primary button-large">
                        Sign Me Up!
                    </button>
                    <div id="oha-vh-signup-message" class="oha-vh-message" style="display:none;"></div>
                </div>
            </form>

            <!-- Success Message (hidden by default) -->
            <div id="oha-vh-signup-success" class="oha-vh-success-message" style="display:none;">
                <div class="oha-vh-success-icon">✅</div>
                <h2 style="color: #000;">Welcome to the Haunt Crew!</h2>
                <p style="color: #000;">Your application has been received and is awaiting review. Check your email for confirmation!</p>
                <p style="color: #000;"><strong>What happens next?</strong></p>
                <ul style="color: #000;">
                    <li>You'll receive a confirmation email within 24 hours</li>
                    <li>Our board will review your application and preferences</li>
                    <li>Once approved, you'll be assigned to an area based on event needs</li>
                    <li>You'll receive your specific assignment and schedule 2 weeks before the convention</li>
                    <li>Questions? Email us at volunteers@ohiohaunters.com</li>
                </ul>
                <button class="button" onclick="location.reload()">Sign Up Another Volunteer</button>
            </div>
        </div>

        <style>
            /* Signup Form Styles - Fixed for visibility and mobile */
            .oha-vh-signup-header { text-align: center; margin-bottom: 30px; }
            .oha-vh-tagline { font-size: 18px; color: #666; }
            .oha-vh-section { 
                background: #f9f9f9; 
                padding: 25px; 
                margin-bottom: 20px; 
                border-radius: 8px;
                border-left: 4px solid #e74c3c;
            }
            .oha-vh-section h3 { margin-top: 0; color: #e74c3c; }
            .oha-vh-section-highlight { background: #fff3cd; border-left-color: #ffc107; }
            
            /* Checkbox label in highlight section - black text */
            .oha-vh-section-highlight label {
                color: #000 !important;
                font-weight: 600;
                font-size: 16px;
            }
            .oha-vh-section-highlight input[type="checkbox"] {
                margin-right: 8px;
            }
            
            .oha-vh-form-row { display: flex; gap: 20px; }
            .oha-vh-form-row .oha-vh-form-group { flex: 1; }
            .oha-vh-form-group { margin-bottom: 20px; }
            
            /* FIXED: Label text color - now black for visibility */
            .oha-vh-form-group label { 
                display: block; 
                font-weight: 600; 
                margin-bottom: 5px; 
                color: #000 !important; /* Black text for labels */
            }
            
            .oha-vh-form-group input[type="text"],
            .oha-vh-form-group input[type="email"],
            .oha-vh-form-group input[type="tel"],
            .oha-vh-form-group textarea { 
                width: 100%; 
                padding: 10px; 
                border: 1px solid #ddd; 
                border-radius: 4px;
                background: #fff;
                color: #000;
            }
            .oha-vh-form-group small { color: #666; font-size: 12px; display: block; margin-top: 5px; }
            .required { color: #e74c3c; }
            .oha-vh-areas-grid { 
                display: grid; 
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
                gap: 15px; 
                margin-top: 15px;
            }
            .oha-vh-area-card { 
                border: 2px solid #ddd; 
                border-radius: 8px; 
                padding: 15px; 
                cursor: pointer;
                transition: all 0.3s;
                background: white;
                display: flex;
                align-items: flex-start;
            }
            .oha-vh-area-card:hover { border-color: #e74c3c; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
            .oha-vh-area-card input[type="checkbox"] { margin-right: 10px; margin-top: 5px; flex-shrink: 0; }
            .oha-vh-area-card input[type="checkbox"]:checked ~ .oha-vh-area-content { opacity: 1; }
            .oha-vh-area-content { opacity: 0.7; transition: opacity 0.3s; flex: 1; }
            .oha-vh-area-icon { font-size: 32px; display: block; margin-bottom: 10px; }
            .oha-vh-area-card h4 { margin: 0 0 8px 0; color: #000; font-size: 16px; }
            .oha-vh-area-card p { margin: 0; font-size: 14px; color: #333; }
            
            /* Radio group styles */
            .oha-vh-radio-group label { 
                display: block; 
                padding: 12px; 
                margin-bottom: 8px; 
                background: white; 
                border: 2px solid #ddd; 
                border-radius: 4px; 
                cursor: pointer;
                color: #000;
            }
            .oha-vh-radio-group label:hover { border-color: #e74c3c; }
            .oha-vh-radio-group label span { color: #000; }
            .oha-vh-radio-group input[type="radio"] { margin-right: 10px; }
            
            .oha-vh-submit-section { text-align: center; margin-top: 30px; }
            .oha-vh-message { margin-top: 15px; padding: 15px; border-radius: 4px; }
            .oha-vh-message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
            .oha-vh-message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
            .oha-vh-success-message { 
                background: #d4edda; 
                border: 2px solid #28a745; 
                border-radius: 8px; 
                padding: 40px; 
                text-align: center;
            }
            .oha-vh-success-message .oha-vh-success-icon { font-size: 72px; margin-bottom: 20px; }
            .oha-vh-success-message h2 { color: #000 !important; }
            .oha-vh-success-message p { color: #000 !important; }
            .oha-vh-success-message ul { text-align: left; max-width: 500px; margin: 20px auto; color: #000 !important; }
            .oha-vh-success-message li { color: #000 !important; }
            
            /* Mobile responsive */
            @media (max-width: 768px) {
                .oha-vh-form-row { flex-direction: column; gap: 0; }
                .oha-vh-areas-grid { grid-template-columns: 1fr; }
                .oha-vh-section { padding: 15px; }
                .oha-vh-area-card { padding: 12px; }
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('#oha-volunteer-signup-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $message = $('#oha-vh-signup-message');
                var $submitBtn = $form.find('button[type="submit"]');
                
                // Disable submit button
                $submitBtn.prop('disabled', true).text('Submitting...');
                $message.hide();

                $.ajax({
                    url: OHA_VH_AJAX.ajaxurl,
                    type: 'POST',
                    data: $form.serialize(),
                    success: function(response) {
                        if (response.success) {
                            // Hide form and show success message
                            $form.fadeOut(300, function() {
                                $('#oha-vh-signup-success').fadeIn(300);
                            });
                        } else {
                            $message
                                .removeClass('success')
                                .addClass('error')
                                .html('<strong>Oops!</strong> ' + (response.data.message || 'Something went wrong. Please try again.'))
                                .fadeIn();
                            $submitBtn.prop('disabled', false).text('Sign Me Up!');
                        }
                    },
                    error: function() {
                        $message
                            .removeClass('success')
                            .addClass('error')
                            .html('<strong>Error!</strong> Unable to submit. Please check your connection and try again.')
                            .fadeIn();
                        $submitBtn.prop('disabled', false).text('Sign Me Up!');
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle the AJAX signup submission
     */
    public static function handle_signup() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'oha_vh_signup_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        // Validate required fields
        $required = ['first_name', 'last_name', 'email'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error(['message' => 'Please fill in all required fields']);
            }
        }

        // Validate email
        $email = sanitize_email($_POST['email']);
        if (!is_email($email)) {
            wp_send_json_error(['message' => 'Please enter a valid email address']);
        }

        // Check for duplicate email
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteers';
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE email = %s",
            $email
        ));

        if ($exists) {
            wp_send_json_error(['message' => 'This email is already registered. Check your inbox for confirmation!']);
        }

        // Prepare data
        
        // Process structured availability (checkboxes + notes)
        $availability_days = isset($_POST['availability_days']) ? (array)$_POST['availability_days'] : [];
        $availability_notes = sanitize_textarea_field($_POST['availability_notes'] ?? '');
        
        $availability_data = [
            'days' => array_map('sanitize_text_field', $availability_days),
            'notes' => $availability_notes
        ];
        
        $data = [
            'email' => $email,
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'birthdate' => !empty($_POST['birthdate']) ? sanitize_text_field($_POST['birthdate']) : null,
            'preferred_areas' => wp_json_encode($_POST['areas'] ?? []),
            'availability' => wp_json_encode($availability_data),
            'experience_level' => sanitize_text_field($_POST['experience'] ?? 'newbie'),
            'special_skills' => sanitize_textarea_field($_POST['special_skills'] ?? ''),
            'how_did_you_hear' => sanitize_text_field($_POST['how_did_you_hear'] ?? ''),
            'tshirt_size' => sanitize_text_field($_POST['tshirt_size'] ?? ''),
            'emergency_contact' => sanitize_text_field($_POST['emergency_contact'] ?? ''),
            'emergency_contact_attending' => sanitize_text_field($_POST['emergency_contact_attending'] ?? ''),
            'signup_date' => current_time('mysql'),
            'status' => 'pending',
            'user_id' => (is_user_logged_in() && !empty($_POST['link_account'])) ? get_current_user_id() : null
        ];

        // Insert into database
        $inserted = $wpdb->insert($table, $data);

        if ($inserted === false) {
            // Log the actual error for debugging
            error_log('OHA Volunteer Signup Error: ' . $wpdb->last_error);
            error_log('OHA Volunteer Data: ' . print_r($data, true));
            
            // Return detailed error in debug mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                wp_send_json_error([
                    'message' => 'Database error: ' . $wpdb->last_error,
                    'debug_data' => array_keys($data)
                ]);
            } else {
                wp_send_json_error(['message' => 'Database error. Please try again or contact support.']);
            }
        }

        $volunteer_id = $wpdb->insert_id;

        // Send confirmation email
        self::send_confirmation_email($volunteer_id, $data);

        // Notify admin
        self::notify_admin_new_signup($volunteer_id, $data);

        wp_send_json_success([
            'message' => 'Signup successful!',
            'volunteer_id' => $volunteer_id
        ]);
    }

    /**
     * Send confirmation email to volunteer
     * Uses the editable template from Volunteer Hub > Settings > Email Templates
     */
    private static function send_confirmation_email($volunteer_id, $data) {
        $convention_name      = Settings::get('convention_name', 'OHA Convention 2026');
        $convention_date_raw  = Settings::get('convention_date', '2026-05-09');
        $convention_date      = date('F j, Y', strtotime($convention_date_raw));
        $coordinator_email    = Settings::get('volunteer_coordinator_email', get_option('admin_email'));

        $subject = Settings::get('confirmation_subject', 'Volunteer Application Received - ' . $convention_name);
        $body    = Settings::get('confirmation_body', Settings::get_default_confirmation_email());

        $placeholders = [
            '{first_name}'                  => $data['first_name'],
            '{last_name}'                   => $data['last_name'],
            '{email}'                       => $data['email'],
            '{convention_name}'             => $convention_name,
            '{convention_date}'             => $convention_date,
            '{volunteer_coordinator_email}' => $coordinator_email,
        ];

        $subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject);
        $body    = str_replace(array_keys($placeholders), array_values($placeholders), $body);

        // Strip any HTML that may have been saved into the template editor
        $body = preg_replace('/<br\s*\/?>/i', "\n", $body);
        $body = preg_replace('/<\/p>/i', "\n\n", $body);
        $body = wp_strip_all_tags($body);
        $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = preg_replace('/\n{3,}/', "\n\n", trim($body));

        wp_mail($data['email'], $subject, $body);
    }

    /**
     * Notify admin of new signup
     */
    private static function notify_admin_new_signup($volunteer_id, $data) {
        $admin_email = get_option('admin_email');
        $subject = '🎉 New OHA Convention Volunteer Signup';
        
        // Format availability for email
        $availability_formatted = self::format_availability_for_display($data['availability'], false);
        
        $message = "New volunteer signup received!\n\n";
        $message .= "Name: {$data['first_name']} {$data['last_name']}\n";
        $message .= "Email: {$data['email']}\n";
        $message .= "Phone: {$data['phone']}\n";
        $message .= "Experience: " . ucfirst($data['experience_level']) . "\n";
        $message .= "Special Skills: {$data['special_skills']}\n";
        $message .= "Availability:\n{$availability_formatted}\n\n";
        $message .= "View/Manage: [Add your Board Hub URL here]\n";

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Format availability data for display
     */
    public static function format_availability_for_display($availability_json, $html = true) {
        // Handle empty/null
        if (empty($availability_json)) {
            return $html ? '<em>Not specified</em>' : 'Not specified';
        }
        
        // Try to decode JSON
        $availability = json_decode($availability_json, true);
        
        // If decode failed or it's not the expected structure, show as plain text
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($availability)) {
            // It's old plain text format or invalid
            return $html ? esc_html($availability_json) : $availability_json;
        }
        
        // Check if it's our new structured format
        if (!isset($availability['days']) || !is_array($availability['days'])) {
            // JSON but not our structure - show as is
            return $html ? esc_html($availability_json) : $availability_json;
        }
        
        $days = $availability['days'];
        $notes = $availability['notes'] ?? '';
        
        // Friendly day/time labels
        $labels = [
            'friday_setup' => 'Friday Setup Crew (4PM-8PM)',
            'saturday_am' => 'Saturday Morning (8AM-12PM)',
            'saturday_pm' => 'Saturday Afternoon (12PM-6PM)',
            'saturday_evening' => 'Saturday Evening (6PM-10PM)',
            'sunday_am' => 'Sunday Morning (8AM-12PM)',
            'sunday_pm' => 'Sunday Afternoon (12PM-6PM)',
        ];
        
        if ($html) {
            $output = '<div class="availability-display">';
            if (!empty($days)) {
                $output .= '<strong>Available times:</strong><br>';
                foreach ($days as $day) {
                    $label = $labels[$day] ?? ucfirst(str_replace('_', ' ', $day));
                    $output .= '✓ ' . esc_html($label) . '<br>';
                }
            } else {
                $output .= '<em>No specific times selected</em><br>';
            }
            if (!empty($notes)) {
                $output .= '<strong>Notes:</strong> ' . esc_html($notes);
            }
            $output .= '</div>';
            return $output;
        } else {
            // Plain text for emails and CSV
            $output = '';
            if (!empty($days)) {
                $time_list = [];
                foreach ($days as $day) {
                    $label = $labels[$day] ?? ucfirst(str_replace('_', ' ', $day));
                    $time_list[] = $label;
                }
                $output .= implode('; ', $time_list);
            } else {
                $output .= 'No specific times selected';
            }
            if (!empty($notes)) {
                $output .= ' | Notes: ' . $notes;
            }
            return $output;
        }
    }

    /**
     * Get convention areas (from settings or default)
     */
    private static function get_convention_areas() {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteer_areas';
        
        // Get active areas from database
        $areas = $wpdb->get_results("
            SELECT id, area_name, description, icon, color
            FROM {$table}
            WHERE is_active = 1
            ORDER BY display_order ASC, area_name ASC
        ");
        
        // Format for the form
        $formatted = [];
        foreach ($areas as $area) {
            $formatted[] = [
                'id' => $area->id,
                'name' => $area->area_name,
                'description' => $area->description ?: '',
                'icon' => $area->icon ?: ''
            ];
        }
        
        return $formatted;
    }
}
