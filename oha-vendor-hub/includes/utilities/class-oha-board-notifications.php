<?php
/**
 * Board Notification System
 *
 * Sends email notifications to board members when new members sign up
 *
 * @package OHA_Board_Hub
 * @since 1.5.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OHA Board Notifications class
 */
class OHA_Board_Notifications {
    
    /**
     * Option name for notification settings
     */
    const OPTION_NAME = 'oha_board_notifications';
    
    /**
     * Initialize the notification system
     */
    public static function init() {
        // Add settings to main admin page
        add_action('admin_init', [__CLASS__, 'register_settings']);
        
        // Hook into SWPM member creation
        add_action('swpm_membership_changed', [__CLASS__, 'check_swpm_membership'], 10, 2);
        
        // Hook into UsersWP form submission (if UsersWP is active)
        add_action('uwp_after_process_register', [__CLASS__, 'check_userswp_registration'], 10, 1);
        
        // Alternative SWPM hook for new members
        add_action('swpm_front_end_registration_complete_user_data', [__CLASS__, 'check_swpm_registration'], 10, 2);
    }
    
    /**
     * Register settings
     */
    public static function register_settings() {
        register_setting('oha_board_notifications', self::OPTION_NAME, [
            'sanitize_callback' => [__CLASS__, 'sanitize_settings']
        ]);
    }
    
    /**
     * Sanitize settings
     */
    public static function sanitize_settings($input) {
        $sanitized = [];
        
        $sanitized['enabled'] = !empty($input['enabled']) ? 1 : 0;
        $sanitized['recipient_emails'] = sanitize_textarea_field($input['recipient_emails'] ?? '');
        $sanitized['subject'] = sanitize_text_field($input['subject'] ?? 'New OHA Member Signup');
        $sanitized['notify_swpm_level'] = absint($input['notify_swpm_level'] ?? 10);
        $sanitized['notify_userswp_form'] = absint($input['notify_userswp_form'] ?? 1);
        
        return $sanitized;
    }
    
    /**
     * Get notification settings
     */
    public static function get_settings() {
        $defaults = [
            'enabled' => 1,
            'recipient_emails' => get_option('admin_email'),
            'subject' => 'New OHA Member Signup',
            'notify_swpm_level' => 10,
            'notify_userswp_form' => 1,
        ];
        
        $settings = get_option(self::OPTION_NAME, []);
        return wp_parse_args($settings, $defaults);
    }
    
    /**
     * Check SWPM membership change
     *
     * @param int $member_id SWPM member ID
     * @param array $old_membership Old membership data
     */
    public static function check_swpm_membership($member_id, $old_membership) {
        $settings = self::get_settings();
        
        if (empty($settings['enabled'])) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'swpm_members_tbl';
        
        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE member_id = %d",
            $member_id
        ), ARRAY_A);
        
        if (!$member) {
            return;
        }
        
        $current_level = (int) $member['membership_level'];
        $target_level = (int) $settings['notify_swpm_level'];
        
        // Check if this is a new member at the target level
        if ($current_level === $target_level) {
            self::send_notification($member, 'swpm');
        }
    }
    
    /**
     * Check SWPM registration (alternative hook)
     *
     * @param array $user_data User data from registration
     * @param int $member_id SWPM member ID
     */
    public static function check_swpm_registration($user_data, $member_id) {
        $settings = self::get_settings();
        
        if (empty($settings['enabled'])) {
            return;
        }
        
        if (empty($user_data['membership_level'])) {
            return;
        }
        
        $current_level = (int) $user_data['membership_level'];
        $target_level = (int) $settings['notify_swpm_level'];
        
        if ($current_level === $target_level) {
            self::send_notification($user_data, 'swpm');
        }
    }
    
    /**
     * Check UsersWP registration
     *
     * @param int $user_id WordPress user ID
     */
    public static function check_userswp_registration($user_id) {
        $settings = self::get_settings();
        
        if (empty($settings['enabled'])) {
            return;
        }
        
        // Check if this registration came from the target form
        $form_id = get_user_meta($user_id, 'uwp_register_form_id', true);
        
        if ((int) $form_id === (int) $settings['notify_userswp_form']) {
            $user = get_userdata($user_id);
            
            if ($user) {
                $user_data = [
                    'user_name' => $user->user_login,
                    'email' => $user->user_email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                ];
                
                self::send_notification($user_data, 'userswp');
            }
        }
    }
    
    /**
     * Send notification email
     *
     * @param array $member_data Member data
     * @param string $source Source of notification (swpm or userswp)
     */
    private static function send_notification($member_data, $source = 'swpm') {
        $settings = self::get_settings();
        
        // Get recipient emails
        $recipients = array_filter(
            array_map('trim', explode("\n", $settings['recipient_emails']))
        );
        
        if (empty($recipients)) {
            OHA_Logger::warning('Board notification: No recipient emails configured');
            return;
        }
        
        // Build member info
        $member_name = trim(
            ($member_data['first_name'] ?? '') . ' ' . ($member_data['last_name'] ?? '')
        );
        if (empty($member_name)) {
            $member_name = $member_data['user_name'] ?? 'Unknown';
        }
        
        $member_email = $member_data['email'] ?? 'Not provided';
        $membership_level = $member_data['membership_level'] ?? $settings['notify_swpm_level'];
        
        // Build email content
        $subject = $settings['subject'];
        
        $message = "Hello Board Members,\n\n";
        $message .= "A new member has signed up for the Ohio Haunters Association.\n\n";
        $message .= "Member Details:\n";
        $message .= "-------------------\n";
        $message .= "Name: " . $member_name . "\n";
        $message .= "Email: " . $member_email . "\n";
        $message .= "Username: " . ($member_data['user_name'] ?? 'N/A') . "\n";
        
        if ($source === 'swpm') {
            $message .= "Membership Level: " . $membership_level . "\n";
            $message .= "Source: Simple WordPress Membership\n";
        } else {
            $message .= "Source: UsersWP Registration Form #" . $settings['notify_userswp_form'] . "\n";
        }
        
        $message .= "Registration Date: " . current_time('F j, Y, g:i a') . "\n";
        $message .= "-------------------\n\n";
        
        $message .= "You can manage this member from the OHA Board Hub admin panel:\n";
        $message .= admin_url('admin.php?page=oha-board-hub') . "\n\n";
        
        $message .= "Best regards,\n";
        $message .= get_bloginfo('name');
        
        // Email headers
        $headers = [
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
            'Content-Type: text/plain; charset=UTF-8'
        ];
        
        // Send to each recipient
        foreach ($recipients as $recipient) {
            if (is_email($recipient)) {
                $sent = wp_mail($recipient, $subject, $message, $headers);
                
                if ($sent) {
                    OHA_Logger::info('Board notification sent', [
                        'recipient' => $recipient,
                        'member' => $member_name,
                        'source' => $source
                    ]);
                } else {
                    OHA_Logger::error('Board notification failed', [
                        'recipient' => $recipient,
                        'member' => $member_name,
                        'source' => $source
                    ]);
                }
            }
        }
    }
    
    /**
     * Render settings section
     */
    public static function render_settings() {
        $settings = self::get_settings();
        ?>
        <div class="oha-board-notifications-settings">
            <h2><?php esc_html_e('Board Notification Settings', 'oha-board-hub'); ?></h2>
            <p><?php esc_html_e('Configure email notifications to board members when new members sign up.', 'oha-board-hub'); ?></p>
            
            <form method="post" action="options.php">
                <?php settings_fields('oha_board_notifications'); ?>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="oha_notifications_enabled">
                                <?php esc_html_e('Enable Notifications', 'oha-board-hub'); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="<?php echo esc_attr(self::OPTION_NAME); ?>[enabled]" 
                                       id="oha_notifications_enabled"
                                       value="1" 
                                       <?php checked($settings['enabled'], 1); ?>>
                                <?php esc_html_e('Send email notifications to board members', 'oha-board-hub'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oha_notifications_emails">
                                <?php esc_html_e('Recipient Email Addresses', 'oha-board-hub'); ?>
                            </label>
                        </th>
                        <td>
                            <textarea name="<?php echo esc_attr(self::OPTION_NAME); ?>[recipient_emails]" 
                                      id="oha_notifications_emails"
                                      rows="5" 
                                      class="large-text"
                                      placeholder="board@example.com&#10;president@example.com&#10;treasurer@example.com"><?php echo esc_textarea($settings['recipient_emails']); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Enter one email address per line. These people will receive notifications about new member signups.', 'oha-board-hub'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oha_notifications_subject">
                                <?php esc_html_e('Email Subject', 'oha-board-hub'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text" 
                                   name="<?php echo esc_attr(self::OPTION_NAME); ?>[subject]" 
                                   id="oha_notifications_subject"
                                   value="<?php echo esc_attr($settings['subject']); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oha_notifications_swpm_level">
                                <?php esc_html_e('SWPM Membership Level to Monitor', 'oha-board-hub'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   name="<?php echo esc_attr(self::OPTION_NAME); ?>[notify_swpm_level]" 
                                   id="oha_notifications_swpm_level"
                                   value="<?php echo esc_attr($settings['notify_swpm_level']); ?>" 
                                   class="small-text"
                                   min="1">
                            <p class="description">
                                <?php esc_html_e('Send notification when a member signs up with this SWPM membership level (default: 10 for paid members).', 'oha-board-hub'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oha_notifications_userswp_form">
                                <?php esc_html_e('UsersWP Form ID to Monitor', 'oha-board-hub'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   name="<?php echo esc_attr(self::OPTION_NAME); ?>[notify_userswp_form]" 
                                   id="oha_notifications_userswp_form"
                                   value="<?php echo esc_attr($settings['notify_userswp_form']); ?>" 
                                   class="small-text"
                                   min="1">
                            <p class="description">
                                <?php esc_html_e('Send notification when a user registers through this UsersWP form ID (default: 1).', 'oha-board-hub'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Notification Settings', 'oha-board-hub')); ?>
            </form>
            
            <hr>
            
            <h3><?php esc_html_e('Test Notification', 'oha-board-hub'); ?></h3>
            <p><?php esc_html_e('Send a test notification to verify your settings.', 'oha-board-hub'); ?></p>
            
            <form method="post" action="">
                <?php wp_nonce_field('oha_test_notification', 'oha_test_notification_nonce'); ?>
                <input type="hidden" name="oha_test_notification" value="1">
                <?php submit_button(__('Send Test Email', 'oha-board-hub'), 'secondary', 'submit', false); ?>
            </form>
            
            <?php
            // Handle test email
            if (isset($_POST['oha_test_notification']) && 
                isset($_POST['oha_test_notification_nonce']) && 
                wp_verify_nonce($_POST['oha_test_notification_nonce'], 'oha_test_notification')) {
                
                $test_data = [
                    'user_name' => 'testuser',
                    'email' => 'test@example.com',
                    'first_name' => 'Test',
                    'last_name' => 'Member',
                    'membership_level' => $settings['notify_swpm_level']
                ];
                
                self::send_notification($test_data, 'swpm');
                
                echo '<div class="notice notice-success"><p>' . 
                     esc_html__('Test notification sent! Check your inbox.', 'oha-board-hub') . 
                     '</p></div>';
            }
            ?>
        </div>
        <?php
    }
}

// Initialize the notification system
OHA_Board_Notifications::init();
