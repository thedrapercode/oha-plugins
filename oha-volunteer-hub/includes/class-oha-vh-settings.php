<?php
/**
 * OHA Volunteer Hub - Settings Page
 * Comprehensive admin settings for volunteer management
 */

namespace OHA\VH;

if (!defined('ABSPATH')) { exit; }

class Settings {

    const OPTION_NAME = 'oha_vh_settings';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    /**
     * Add settings page to WordPress admin
     */
    public static function add_menu() {
        add_menu_page(
            'Volunteer Hub Settings',
            'Volunteer Hub',
            'manage_options',
            'oha-volunteer-hub',
            [__CLASS__, 'render_admin_page'],
            'dashicons-groups',
            30
        );

        add_submenu_page(
            'oha-volunteer-hub',
            'Volunteer Signups',
            'Signups',
            'manage_options',
            'oha-volunteer-signups',
            [__CLASS__, 'render_signups_page']
        );

        add_submenu_page(
            'oha-volunteer-hub',
            'Manage Areas',
            'Areas & Shifts',
            'manage_options',
            'oha-volunteer-areas',
            [__CLASS__, 'render_areas_page']
        );

        add_submenu_page(
            'oha-volunteer-hub',
            'Settings',
            'Settings',
            'manage_options',
            'oha-volunteer-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        register_setting('oha_vh_settings_group', self::OPTION_NAME, [
            'sanitize_callback' => [__CLASS__, 'sanitize_settings']
        ]);
    }

    /**
     * Get a setting value
     */
    public static function get($key, $default = '') {
        $settings = get_option(self::OPTION_NAME, []);
        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * Update a setting value
     */
    public static function update($key, $value) {
        $settings = get_option(self::OPTION_NAME, []);
        $settings[$key] = $value;
        update_option(self::OPTION_NAME, $settings);
    }

    /**
     * Get default settings
     */
    public static function get_defaults() {
        return [
            // General
            'enable_signups' => '1',
            'require_approval' => '1',
            'thank_you_message' => 'Thank you for signing up! We\'ll be in touch soon with your assignment.',
            'admin_notification_emails' => get_option('admin_email'),
            
            // Email Templates
            'confirmation_subject' => 'Volunteer Application Received - {convention_name}',
            'confirmation_body' => self::get_default_confirmation_email(),
            'assignment_subject' => 'Your OHA Convention Assignment! 🎯',
            'assignment_body' => self::get_default_assignment_email(),
            'reminder_subject' => 'Reminder: OHA Convention Tomorrow! ⏰',
            'reminder_body' => self::get_default_reminder_email(),
            'approval_subject' => 'Volunteer Application Approved - {convention_name}',
            'approval_body' => self::get_default_approval_email(),

            // Convention Info
            'convention_date' => '2026-05-09',
            'convention_name' => 'OHA Convention 2026',
            'convention_location' => 'To Be Announced',
            'volunteer_coordinator_email' => get_option('admin_email'),
            'volunteer_coordinator_phone' => '',
            
            // Features
            'enable_shifts' => '1',
            'enable_check_in' => '1',
            'enable_hours_tracking' => '1',
            'send_reminder_24h' => '1',
            'send_thank_you_after' => '1',
            'send_approval_email' => '1',

            // Portal Display
            'portal_show_checkin'    => '1',
            'portal_announcement'    => '',
            'portal_checkin_location' => '',
            'portal_parking_info'    => '',

            // Applications
            'signup_closed'          => '0',
            'signup_closed_message'  => 'Volunteer applications for OHA Convention 2026 are now closed. Thank you for your interest - please keep us in mind for 2027!',
        ];
    }

    /**
     * Sanitize settings
     */
    public static function sanitize_settings($input) {
        $sanitized = [];
        
        // Checkboxes
        $checkboxes = ['enable_signups', 'require_approval', 'enable_shifts', 'enable_check_in',
                       'enable_hours_tracking', 'send_reminder_24h', 'send_thank_you_after', 'send_approval_email',
                       'portal_show_checkin', 'signup_closed'];
        foreach ($checkboxes as $checkbox) {
            $sanitized[$checkbox] = isset($input[$checkbox]) ? '1' : '0';
        }
        
        // Text fields
        $text_fields = ['thank_you_message', 'admin_notification_emails', 'convention_name', 
                        'convention_location', 'volunteer_coordinator_email', 'volunteer_coordinator_phone'];
        foreach ($text_fields as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = sanitize_text_field($input[$field]);
            }
        }
        
        // Email templates (allow HTML)
        $email_fields = ['confirmation_subject', 'confirmation_body', 'assignment_subject',
                         'assignment_body', 'reminder_subject', 'reminder_body',
                         'approval_subject', 'approval_body'];
        foreach ($email_fields as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = wp_kses_post($input[$field]);
            }
        }
        
        // Date
        if (isset($input['convention_date'])) {
            $sanitized['convention_date'] = sanitize_text_field($input['convention_date']);
        }

        // Board guide content (allow HTML)
        if (isset($input['board_guide_content'])) {
            $sanitized['board_guide_content'] = wp_kses_post($input['board_guide_content']);
        }

        // Portal display fields (plain text)
        foreach (['portal_announcement', 'portal_checkin_location', 'portal_parking_info'] as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = sanitize_textarea_field($input[$field]);
            }
        }

        // Applications closed message (plain text)
        if (isset($input['signup_closed_message'])) {
            $sanitized['signup_closed_message'] = sanitize_textarea_field($input['signup_closed_message']);
        }

        return $sanitized;
    }

    /**
     * Render main admin page (dashboard)
     */
    public static function render_admin_page() {
        $stats = Database::get_volunteer_counts();
        $area_stats = Database::get_area_stats();
        
        ?>
        <div class="wrap oha-vh-admin">
            <h1>🎉 Volunteer Hub Dashboard</h1>
            
            <div class="oha-vh-dashboard-cards">
                <div class="oha-vh-card">
                    <h3>Total Signups</h3>
                    <div class="oha-vh-stat">
                        <?php 
                        $total = 0;
                        foreach ($stats as $stat) $total += $stat->count;
                        echo esc_html($total);
                        ?>
                    </div>
                </div>
                
                <div class="oha-vh-card">
                    <h3>Pending Approval</h3>
                    <div class="oha-vh-stat pending">
                        <?php echo isset($stats['pending']) ? esc_html($stats['pending']->count) : '0'; ?>
                    </div>
                    <a href="<?php echo admin_url('admin.php?page=oha-volunteer-signups&status=pending'); ?>" class="button button-primary">
                        Review Now
                    </a>
                </div>
                
                <div class="oha-vh-card">
                    <h3>Approved</h3>
                    <div class="oha-vh-stat approved">
                        <?php echo isset($stats['approved']) ? esc_html($stats['approved']->count) : '0'; ?>
                    </div>
                </div>
                
                <div class="oha-vh-card">
                    <h3>Assigned</h3>
                    <div class="oha-vh-stat assigned">
                        <?php echo isset($stats['assigned']) ? esc_html($stats['assigned']->count) : '0'; ?>
                    </div>
                </div>
            </div>

            <h2>Area Coverage</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Area</th>
                        <th>Assigned</th>
                        <th>Interested</th>
                        <th>Need (Min)</th>
                        <th>Capacity (Max)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($area_stats as $stat): 
                        $percentage = $stat->min_volunteers > 0 ? ($stat->assigned_count / $stat->min_volunteers * 100) : 0;
                        $status_class = $percentage >= 100 ? 'full' : ($percentage >= 50 ? 'half' : 'low');
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($stat->area_name); ?></strong></td>
                        <td><?php echo esc_html($stat->assigned_count); ?></td>
                        <td><?php echo esc_html($stat->interested_count); ?></td>
                        <td><?php echo esc_html($stat->min_volunteers); ?></td>
                        <td><?php echo esc_html($stat->max_volunteers); ?></td>
                        <td>
                            <span class="oha-vh-status <?php echo esc_attr($status_class); ?>">
                                <?php echo esc_html(round($percentage)); ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <style>
                .oha-vh-dashboard-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
                .oha-vh-card { background: white; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
                .oha-vh-card h3 { margin-top: 0; font-size: 14px; color: #666; text-transform: uppercase; }
                .oha-vh-stat { font-size: 48px; font-weight: bold; color: #2271b1; margin: 10px 0; }
                .oha-vh-stat.pending { color: #f0ad4e; }
                .oha-vh-stat.approved { color: #5cb85c; }
                .oha-vh-stat.assigned { color: #2271b1; }
                .oha-vh-status { padding: 4px 12px; border-radius: 12px; font-weight: bold; font-size: 12px; }
                .oha-vh-status.full { background: #d4edda; color: #155724; }
                .oha-vh-status.half { background: #fff3cd; color: #856404; }
                .oha-vh-status.low { background: #f8d7da; color: #721c24; }
            </style>
        </div>
        <?php
    }

    /**
     * Render signups management page
     */
    public static function render_signups_page() {
        // This will be handled by the Admin class
        Admin::render_signups_table();
    }

    /**
     * Render areas management page
     */
    public static function render_areas_page() {
        // Handle area actions
        if (isset($_POST['oha_vh_area_action'])) {
            check_admin_referer('oha_vh_area_action');
            self::handle_area_action();
        }

        $areas = Database::get_areas(false); // Get all, including inactive
        ?>
        <div class="wrap">
            <h1>Manage Convention Areas & Shifts</h1>
            
            <div class="oha-vh-areas-section">
                <h2>Convention Areas</h2>
                <p>These are the volunteer positions available for the convention. Click "Edit" to change names, colors, or requirements.</p>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 40px;">Order</th>
                            <th style="width: 50px;">Icon</th>
                            <th>Area Name</th>
                            <th>Description</th>
                            <th>Min/Max</th>
                            <th style="width: 80px;">Active</th>
                            <th style="width: 100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($areas as $area): ?>
                        <tr>
                            <td><?php echo esc_html($area->display_order); ?></td>
                            <td style="font-size: 24px;"><?php echo esc_html($area->icon); ?></td>
                            <td><strong><?php echo esc_html($area->area_name); ?></strong></td>
                            <td><?php echo esc_html($area->description); ?></td>
                            <td><?php echo esc_html($area->min_volunteers . ' - ' . $area->max_volunteers); ?></td>
                            <td><?php echo $area->is_active ? '✅ Yes' : '❌ No'; ?></td>
                            <td>
                                <button class="button button-small" onclick="editArea(<?php echo esc_js(json_encode($area)); ?>)">
                                    Edit
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p>
                    <button class="button button-primary" onclick="addNewArea()">➕ Add New Area</button>
                </p>
            </div>

            <!-- Area Edit Modal -->
            <div id="oha-vh-area-modal" style="display:none;">
                <div class="oha-vh-modal-overlay" onclick="closeModal()"></div>
                <div class="oha-vh-modal-content">
                    <h2 id="modal-title">Edit Area</h2>
                    <form method="post" action="">
                        <?php wp_nonce_field('oha_vh_area_action'); ?>
                        <input type="hidden" name="oha_vh_area_action" value="save">
                        <input type="hidden" name="area_id" id="area_id" value="">
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="area_name">Area Name *</label></th>
                                <td>
                                    <input type="text" id="area_name" name="area_name" class="regular-text" required>
                                    <p class="description">Themed name shown to volunteers on the signup form.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="alt_name">Board Alt Name</label></th>
                                <td>
                                    <input type="text" id="alt_name" name="alt_name" class="regular-text" placeholder="e.g., Front Entrance">
                                    <p class="description">Optional. If set, shown instead of the themed name on the Assign tab for board members. Themed name shown in parentheses.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="area_slug">Slug (URL-friendly) *</label></th>
                                <td><input type="text" id="area_slug" name="area_slug" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="icon">Icon (Emoji)</label></th>
                                <td><input type="text" id="icon" name="icon" maxlength="10" placeholder="🎯"></td>
                            </tr>
                            <tr>
                                <th><label for="color">Color</label></th>
                                <td><input type="color" id="color" name="color" value="#e74c3c"></td>
                            </tr>
                            <tr>
                                <th><label for="description">Description</label></th>
                                <td><textarea id="description" name="description" rows="3" class="large-text"></textarea></td>
                            </tr>
                            <tr>
                                <th><label for="min_volunteers">Minimum Volunteers</label></th>
                                <td><input type="number" id="min_volunteers" name="min_volunteers" value="1" min="1"></td>
                            </tr>
                            <tr>
                                <th><label for="max_volunteers">Maximum Volunteers</label></th>
                                <td><input type="number" id="max_volunteers" name="max_volunteers" value="999" min="1"></td>
                            </tr>
                            <tr>
                                <th><label for="display_order">Display Order</label></th>
                                <td><input type="number" id="display_order" name="display_order" value="0" min="0"></td>
                            </tr>
                            <tr>
                                <th><label for="is_active">Active</label></th>
                                <td><input type="checkbox" id="is_active" name="is_active" value="1" checked></td>
                            </tr>
                        </table>
                        
                        <p>
                            <button type="submit" class="button button-primary">Save Area</button>
                            <button type="button" class="button" onclick="closeModal()">Cancel</button>
                        </p>
                    </form>
                </div>
            </div>

            <style>
                .oha-vh-modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; }
                .oha-vh-modal-content { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 100000; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; }
            </style>

            <script>
            function editArea(area) {
                document.getElementById('modal-title').textContent = 'Edit Area';
                document.getElementById('area_id').value = area.id;
                document.getElementById('area_name').value = area.area_name;
                document.getElementById('alt_name').value = area.alt_name || '';
                document.getElementById('area_slug').value = area.area_slug;
                document.getElementById('icon').value = area.icon || '';
                document.getElementById('color').value = area.color || '#e74c3c';
                document.getElementById('description').value = area.description || '';
                document.getElementById('min_volunteers').value = area.min_volunteers;
                document.getElementById('max_volunteers').value = area.max_volunteers;
                document.getElementById('display_order').value = area.display_order;
                document.getElementById('is_active').checked = area.is_active == 1;
                document.getElementById('oha-vh-area-modal').style.display = 'block';
            }

            function addNewArea() {
                document.getElementById('modal-title').textContent = 'Add New Area';
                document.getElementById('area_id').value = '';
                document.getElementById('area_name').value = '';
                document.getElementById('alt_name').value = '';
                document.getElementById('area_slug').value = '';
                document.getElementById('icon').value = '🎯';
                document.getElementById('color').value = '#e74c3c';
                document.getElementById('description').value = '';
                document.getElementById('min_volunteers').value = '1';
                document.getElementById('max_volunteers').value = '999';
                document.getElementById('display_order').value = '0';
                document.getElementById('is_active').checked = true;
                document.getElementById('oha-vh-area-modal').style.display = 'block';
            }

            function closeModal() {
                document.getElementById('oha-vh-area-modal').style.display = 'none';
            }
            </script>
        </div>
        <?php
    }

    /**
     * Handle area save/delete actions
     */
    private static function handle_area_action() {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteer_areas';

        if ($_POST['oha_vh_area_action'] === 'save') {
            $data = [
                'area_name' => sanitize_text_field($_POST['area_name']),
                'alt_name' => sanitize_text_field($_POST['alt_name'] ?? ''),
                'area_slug' => sanitize_title($_POST['area_slug']),
                'icon' => sanitize_text_field($_POST['icon'] ?? ''),
                'color' => sanitize_hex_color($_POST['color'] ?? '#e74c3c'),
                'description' => sanitize_textarea_field($_POST['description'] ?? ''),
                'min_volunteers' => absint($_POST['min_volunteers'] ?? 1),
                'max_volunteers' => absint($_POST['max_volunteers'] ?? 999),
                'display_order' => absint($_POST['display_order'] ?? 0),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];

            $area_id = absint($_POST['area_id'] ?? 0);

            if ($area_id) {
                // Update existing
                $wpdb->update($table, $data, ['id' => $area_id]);
                add_settings_error('oha_vh_messages', 'area_updated', 'Area updated successfully!', 'success');
            } else {
                // Insert new
                $wpdb->insert($table, $data);
                add_settings_error('oha_vh_messages', 'area_added', 'Area added successfully!', 'success');
            }
        }
    }

    /**
     * Render settings page
     */
    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Save settings
        if (isset($_POST['oha_vh_save_settings'])) {
            check_admin_referer('oha_vh_settings');
            update_option(self::OPTION_NAME, self::sanitize_settings($_POST['oha_vh_settings']));
            add_settings_error('oha_vh_messages', 'settings_updated', 'Settings saved successfully!', 'success');
        }

        $settings = wp_parse_args(get_option(self::OPTION_NAME, []), self::get_defaults());
        settings_errors('oha_vh_messages');
        
        ?>
        <div class="wrap">
            <h1>Volunteer Hub Settings</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('oha_vh_settings'); ?>
                <input type="hidden" name="oha_vh_save_settings" value="1">
                
                <h2 class="nav-tab-wrapper">
                    <a href="#general" class="nav-tab nav-tab-active" onclick="switchTab(event, 'general')">General</a>
                    <a href="#emails" class="nav-tab" onclick="switchTab(event, 'emails')">Email Templates</a>
                    <a href="#convention" class="nav-tab" onclick="switchTab(event, 'convention')">Convention Info</a>
                    <a href="#features" class="nav-tab" onclick="switchTab(event, 'features')">Features</a>
                    <a href="#guide" class="nav-tab" onclick="switchTab(event, 'guide')">Guide</a>
                    <a href="#portal" class="nav-tab" onclick="switchTab(event, 'portal')">Portal</a>
                </h2>

                <!-- General Tab -->
                <div id="general" class="tab-content">
                    <table class="form-table">
                        <tr>
                            <th><label for="enable_signups">Enable Public Signups</label></th>
                            <td>
                                <input type="checkbox" id="enable_signups" name="oha_vh_settings[enable_signups]" value="1" <?php checked($settings['enable_signups'], '1'); ?>>
                                <p class="description">Allow public to submit volunteer signup form</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="signup_closed">Close Applications</label></th>
                            <td>
                                <input type="checkbox" id="signup_closed" name="oha_vh_settings[signup_closed]" value="1" <?php checked($settings['signup_closed'] ?? '0', '1'); ?>>
                                <p class="description">When checked, the signup form is hidden and the message below is displayed in its place.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="signup_closed_message">Applications Closed Message</label></th>
                            <td>
                                <textarea id="signup_closed_message" name="oha_vh_settings[signup_closed_message]" rows="3" class="large-text"><?php echo esc_textarea($settings['signup_closed_message'] ?? ''); ?></textarea>
                                <p class="description">Displayed on the signup page when applications are closed. Shown only when the toggle above is checked.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="require_approval">Require Manual Approval</label></th>
                            <td>
                                <input type="checkbox" id="require_approval" name="oha_vh_settings[require_approval]" value="1" <?php checked($settings['require_approval'], '1'); ?>>
                                <p class="description">Board must approve volunteers before they can be assigned</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="thank_you_message">Thank You Message</label></th>
                            <td>
                                <textarea id="thank_you_message" name="oha_vh_settings[thank_you_message]" rows="3" class="large-text"><?php echo esc_textarea($settings['thank_you_message']); ?></textarea>
                                <p class="description">Shown after successful signup submission</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="admin_notification_emails">Admin Notification Emails</label></th>
                            <td>
                                <input type="text" id="admin_notification_emails" name="oha_vh_settings[admin_notification_emails]" value="<?php echo esc_attr($settings['admin_notification_emails']); ?>" class="regular-text">
                                <p class="description">Comma-separated emails to notify of new signups</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Email Templates Tab -->
                <div id="emails" class="tab-content" style="display:none;">
                    <p style="background:#f0f6fc;border-left:4px solid #2271b1;padding:12px 16px;margin-bottom:20px;font-size:14px;">
                        These are the three emails the system sends automatically. Edit the subject and body for each one. Changes take effect on the next send — no re-activation needed.
                        <br><strong>Not shown here:</strong> Area manager messages (written fresh each time) and the admin new-signup alert (internal only).
                    </p>

                    <h3>1. Signup Confirmation <span style="font-size:13px;font-weight:400;color:#666;">— sent immediately when a volunteer submits the signup form</span></h3>
                    <table class="form-table">
                        <tr>
                            <th><label for="confirmation_subject">Subject Line</label></th>
                            <td><input type="text" id="confirmation_subject" name="oha_vh_settings[confirmation_subject]" value="<?php echo esc_attr($settings['confirmation_subject']); ?>" class="large-text"></td>
                        </tr>
                        <tr>
                            <th><label for="confirmation_body">Email Body</label></th>
                            <td>
                                <?php
                                wp_editor($settings['confirmation_body'], 'confirmation_body', [
                                    'textarea_name' => 'oha_vh_settings[confirmation_body]',
                                    'textarea_rows' => 15,
                                    'media_buttons' => false
                                ]);
                                ?>
                                <p class="description">Placeholders: {first_name}, {last_name}, {email}, {convention_name}, {convention_date}, {volunteer_coordinator_email}</p>
                            </td>
                        </tr>
                    </table>

                    <hr style="margin:30px 0;">

                    <h3>2. Approval Notification <span style="font-size:13px;font-weight:400;color:#666;">— sent when a board member approves a volunteer (requires "Send Approval Emails" toggle in Features tab)</span></h3>
                    <table class="form-table">
                        <tr>
                            <th><label for="approval_subject">Subject Line</label></th>
                            <td><input type="text" id="approval_subject" name="oha_vh_settings[approval_subject]" value="<?php echo esc_attr($settings['approval_subject']); ?>" class="large-text"></td>
                        </tr>
                        <tr>
                            <th><label for="approval_body">Email Body</label></th>
                            <td>
                                <?php
                                wp_editor($settings['approval_body'], 'approval_body', [
                                    'textarea_name' => 'oha_vh_settings[approval_body]',
                                    'textarea_rows' => 15,
                                    'media_buttons' => false
                                ]);
                                ?>
                                <p class="description">Placeholders: {first_name}, {last_name}, {convention_name}, {volunteer_coordinator_email}</p>
                            </td>
                        </tr>
                    </table>

                    <hr style="margin:30px 0;">

                    <h3>3. Assignment Email <span style="font-size:13px;font-weight:400;color:#666;">— sent the moment a board member assigns a volunteer to an area</span></h3>
                    <table class="form-table">
                        <tr>
                            <th><label for="assignment_subject">Subject Line</label></th>
                            <td><input type="text" id="assignment_subject" name="oha_vh_settings[assignment_subject]" value="<?php echo esc_attr($settings['assignment_subject']); ?>" class="large-text"></td>
                        </tr>
                        <tr>
                            <th><label for="assignment_body">Email Body</label></th>
                            <td>
                                <?php
                                wp_editor($settings['assignment_body'], 'assignment_body', [
                                    'textarea_name' => 'oha_vh_settings[assignment_body]',
                                    'textarea_rows' => 15,
                                    'media_buttons' => false
                                ]);
                                ?>
                                <p class="description">Placeholders: {first_name}, {last_name}, {area_name}, {area_description}, {shift_time}, {convention_name}, {convention_date}, {portal_url}, {manager_info}</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Convention Info Tab -->
                <div id="convention" class="tab-content" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <th><label for="convention_name">Convention Name</label></th>
                            <td><input type="text" id="convention_name" name="oha_vh_settings[convention_name]" value="<?php echo esc_attr($settings['convention_name']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="convention_date">Convention Date</label></th>
                            <td><input type="date" id="convention_date" name="oha_vh_settings[convention_date]" value="<?php echo esc_attr($settings['convention_date']); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="convention_location">Location</label></th>
                            <td><input type="text" id="convention_location" name="oha_vh_settings[convention_location]" value="<?php echo esc_attr($settings['convention_location']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="volunteer_coordinator_email">Volunteer Coordinator Email</label></th>
                            <td><input type="email" id="volunteer_coordinator_email" name="oha_vh_settings[volunteer_coordinator_email]" value="<?php echo esc_attr($settings['volunteer_coordinator_email']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="volunteer_coordinator_phone">Volunteer Coordinator Phone</label></th>
                            <td><input type="tel" id="volunteer_coordinator_phone" name="oha_vh_settings[volunteer_coordinator_phone]" value="<?php echo esc_attr($settings['volunteer_coordinator_phone']); ?>" class="regular-text"></td>
                        </tr>
                    </table>
                </div>

                <!-- Features Tab -->
                <div id="features" class="tab-content" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <th><label for="enable_shifts">Enable Shift Management</label></th>
                            <td>
                                <input type="checkbox" id="enable_shifts" name="oha_vh_settings[enable_shifts]" value="1" <?php checked($settings['enable_shifts'], '1'); ?>>
                                <p class="description">Allow volunteers to select specific time slots</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="enable_check_in">Enable Check-In System</label></th>
                            <td>
                                <input type="checkbox" id="enable_check_in" name="oha_vh_settings[enable_check_in]" value="1" <?php checked($settings['enable_check_in'], '1'); ?>>
                                <p class="description">Track when volunteers check in/out</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="enable_hours_tracking">Enable Hours Tracking</label></th>
                            <td>
                                <input type="checkbox" id="enable_hours_tracking" name="oha_vh_settings[enable_hours_tracking]" value="1" <?php checked($settings['enable_hours_tracking'], '1'); ?>>
                                <p class="description">Track hours worked by each volunteer</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="send_approval_email">Send Approval Emails</label></th>
                            <td>
                                <input type="checkbox" id="send_approval_email" name="oha_vh_settings[send_approval_email]" value="1" <?php checked($settings['send_approval_email'], '1'); ?>>
                                <p class="description">Send an email to the volunteer when their application is approved</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="send_reminder_24h">Send 24-Hour Reminders</label></th>
                            <td>
                                <input type="checkbox" id="send_reminder_24h" name="oha_vh_settings[send_reminder_24h]" value="1" <?php checked($settings['send_reminder_24h'], '1'); ?>>
                                <p class="description">Automatically send reminder emails 24 hours before shift</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="send_thank_you_after">Send Thank You Emails</label></th>
                            <td>
                                <input type="checkbox" id="send_thank_you_after" name="oha_vh_settings[send_thank_you_after]" value="1" <?php checked($settings['send_thank_you_after'], '1'); ?>>
                                <p class="description">Send thank you email after convention</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Guide Tab -->
                <div id="guide" class="tab-content" style="display:none;">
                    <p>This is the step-by-step guide shown to board members in the Board Hub. You can customize the HTML below. Leave it blank to use the built-in default guide.</p>
                    <p>Supported styled divs: <code>&lt;div class="step-card"&gt;</code> (red-bordered card), <code>&lt;div class="email-note"&gt;</code> (blue info box), <code>&lt;div class="tip-card"&gt;</code> (yellow tip box).</p>
                    <table class="form-table">
                        <tr>
                            <th><label for="board_guide_content">Guide Content (HTML)</label></th>
                            <td>
                                <textarea id="board_guide_content" name="oha_vh_settings[board_guide_content]" rows="35" class="large-text" style="font-family: monospace; font-size: 12px;"><?php echo esc_textarea($settings['board_guide_content'] ?? ''); ?></textarea>
                                <p class="description">Leave blank to restore the built-in default guide.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Portal Tab -->
                <div id="portal" class="tab-content" style="display:none;">
                    <p>These settings control optional information and features shown on the Volunteer Portal page (<code>[oha_volunteer_portal]</code>). All fields are optional. Leave blank to hide the corresponding section.</p>
                    <table class="form-table">
                        <tr>
                            <th><label for="portal_show_checkin">Show Check-In Button on Portal</label></th>
                            <td>
                                <input type="checkbox" id="portal_show_checkin" name="oha_vh_settings[portal_show_checkin]" value="1" <?php checked($settings['portal_show_checkin'] ?? '1', '1'); ?>>
                                <p class="description">When checked, volunteers see a "Check In Now" button on their portal page. Uncheck this if the board will not be using portal-based check-in for the convention.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="portal_announcement">Volunteer Announcement</label></th>
                            <td>
                                <textarea id="portal_announcement" name="oha_vh_settings[portal_announcement]" rows="4" class="large-text"><?php echo esc_textarea($settings['portal_announcement'] ?? ''); ?></textarea>
                                <p class="description">If filled in, this notice appears at the top of the portal for all volunteers. Use for last-minute updates or important day-of information. Leave blank to hide.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="portal_checkin_location">Volunteer Check-In Location</label></th>
                            <td>
                                <textarea id="portal_checkin_location" name="oha_vh_settings[portal_checkin_location]" rows="3" class="large-text"><?php echo esc_textarea($settings['portal_checkin_location'] ?? ''); ?></textarea>
                                <p class="description">Where volunteers should go when they first arrive (e.g., "Check in at the registration table in the main lobby before reporting to your area."). Leave blank to hide.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="portal_parking_info">Parking Information</label></th>
                            <td>
                                <textarea id="portal_parking_info" name="oha_vh_settings[portal_parking_info]" rows="3" class="large-text"><?php echo esc_textarea($settings['portal_parking_info'] ?? ''); ?></textarea>
                                <p class="description">Parking guidance specific to volunteers. Leave blank to hide.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary">Save All Settings</button>
                    <button type="button" class="button" onclick="if(confirm('Reset all settings to defaults?')) location.href='<?php echo admin_url('admin.php?page=oha-volunteer-settings&reset=1'); ?>'">
                        Reset to Defaults
                    </button>
                </p>
            </form>

            <script>
            function switchTab(e, tabName) {
                e.preventDefault();
                document.querySelectorAll('.tab-content').forEach(tab => tab.style.display = 'none');
                document.querySelectorAll('.nav-tab').forEach(tab => tab.classList.remove('nav-tab-active'));
                document.getElementById(tabName).style.display = 'block';
                e.target.classList.add('nav-tab-active');
            }
            </script>
        </div>
        <?php
    }

    /**
     * Default email templates
     */
    public static function get_default_confirmation_email() {
        return "Hi {first_name}!\n\nThank you for your interest in volunteering with the {convention_name} on {convention_date}. We've received your volunteer application and appreciate you raising your hand to help.\n\nYour selected areas have been recorded as preferences, and we'll do our best to match them. Please note that final assignments depend on event needs and board approval, and volunteer placement is not automatic.\n\nAt this time, your application is pending review by the OHA Board.\n\nWhat is Next?\n1. Your application will be reviewed by the OHA Board\n2. If approved, a volunteer coordinator will contact you to confirm availability and placement\n3. Final assignments and schedules will be sent approximately 2 weeks before the convention\n4. Show up ready to have an amazing time!\n\nQuestions? Reply to this email or contact us at {volunteer_coordinator_email}\n\nThank you for being a Haunt Hero!\n— The OHA Convention Team";
    }

    public static function get_default_assignment_subject() {
        return 'Your OHA Convention Assignment is Ready!';
    }

    public static function get_default_approval_subject() {
        return 'Volunteer Application Approved - {convention_name}';
    }

    public static function get_default_reminder_subject() {
        return 'Reminder: OHA Convention Tomorrow!';
    }

    public static function get_default_assignment_email() {
        return "Hi {first_name}!\n\nYour convention assignment is ready!\n\nYou've been assigned to: {area_name}\nShift Time: {shift_time}\n\nWhat to bring:\n- Comfortable shoes (you'll be on your feet!)\n- Your enthusiasm and positive energy\n- Any special equipment listed in your area details\n\nWe'll see you on {convention_date} at {convention_location}!\n\nQuestions? Contact {volunteer_coordinator_email}\n\n— The OHA Convention Team";
    }

    public static function get_default_reminder_email() {
        return "Hi {first_name}!\n\nThis is a friendly reminder that {convention_name} is TOMORROW! ⏰\n\nYour Assignment:\n- Area: {area_name}\n- Time: {shift_time}\n- Location: {convention_location}\n\nReminder checklist:\n✅ Comfortable shoes\n✅ Arrive 15 minutes early\n✅ Bring your positive energy\n✅ Check in at the volunteer desk\n\nWe're excited to see you!\n\nQuestions? Contact {volunteer_coordinator_email} or {volunteer_coordinator_phone}\n\n— The OHA Convention Team";
    }

    public static function get_default_approval_email() {
        return "Hi {first_name}!\n\nGreat news! Your volunteer application for {convention_name} has been approved!\n\nYou are now an official member of the volunteer crew.\n\nWHAT'S NEXT:\nWe are currently reviewing area assignments and will send you your specific assignment and schedule soon. Keep an eye on your inbox!\n\nQuestions? Reply to this email or contact us at {volunteer_coordinator_email}\n\nThanks for joining us!\n\n-- The OHA Convention Team";
    }

    /**
     * Default board member how-to guide HTML (shown in Guide tab when no custom content is set)
     */
    public static function get_default_guide_html() {
        return '
<h2>Welcome to Volunteer Management!</h2>
<p>This guide walks you through the full volunteer process from start to finish. Follow these steps in order and your volunteers will be organized and ready for convention day.</p>

<h2>Step 1: A Volunteer Signs Up</h2>
<div class="step-card">
<p>When someone submits the volunteer signup form on the website, they automatically appear in the <strong>Volunteers tab</strong> with a <span style="background:#f0ad4e;color:white;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:bold;">Pending</span> status badge in the first column of the table.</p>
<p>You will also receive a notification email at the admin address letting you know someone signed up.</p>
</div>
<div class="email-note"><strong>Email sent to volunteer automatically:</strong> A confirmation email goes out the moment they submit the form, thanking them and explaining that their application is under review. You do not need to contact them manually.</div>

<h2>Step 2: Review and Approve a Volunteer</h2>
<div class="step-card">
<p><span class="step-number">1</span> Go to the <strong>Volunteers tab</strong> at the top of this board hub.</p>
<p><span class="step-number">2</span> Use the Status filter dropdown to show only <strong>Pending</strong> volunteers so you can work through them efficiently.</p>
<p><span class="step-number">3</span> Click <strong>View</strong> on a volunteer to see their details, experience level, and the areas they are interested in.</p>
<p><span class="step-number">4</span> Click <strong>Approve</strong> to mark them ready for assignment. Their status changes to <span style="background:#2271b1;color:white;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:bold;">Approved</span>.</p>
<p>If a volunteer is not a good fit, you can set their status to <strong>Waitlisted</strong> or <strong>Declined</strong> using the status dropdown in their profile.</p>
</div>
<div class="tip-card"><strong>Tip:</strong> Only Approved volunteers can be assigned to shifts. Make sure to approve them before trying to assign.</div>

<h2>Step 3: Confirm Your Shifts Are Set Up</h2>
<div class="step-card">
<p>Before you can assign anyone, shifts need to exist. Click the <strong>Shifts tab</strong> to check.</p>
<p>Each shift has a date, a start time, an end time, and an area. For example: "Saturday Setup - 8:00 AM to 11:00 AM - Front Entrance".</p>
<p>To add a shift, click <strong>Add Shift</strong> at the top of the Shifts tab and fill in the details.</p>
<p>If your shifts are already set up, skip ahead to Step 4.</p>
</div>
<div class="tip-card"><strong>Tip:</strong> Create all your shifts at the start of the planning season so you can assign volunteers as soon as they are approved.</div>

<h2>Step 4: Assign a Volunteer to an Area and Shift</h2>
<div class="step-card">
<p><span class="step-number">1</span> Go to the <strong>Assign tab</strong>.</p>
<p><span class="step-number">2</span> Use the volunteer search box to find the approved volunteer by name.</p>
<p><span class="step-number">3</span> Select the <strong>Area</strong> from the dropdown (e.g., Front Entrance, Main Stage, Parking).</p>
<p><span class="step-number">4</span> Select the <strong>Shift</strong> — the date and time slot — from the shift dropdown.</p>
<p><span class="step-number">5</span> Click <strong>Assign Volunteer</strong>. They will now appear in that area card on the right side of the screen.</p>
</div>
<div class="email-note"><strong>Email sent to volunteer automatically:</strong> The moment you assign a volunteer, they receive an assignment email with their area name, shift time, and location. No need to contact them separately.</div>
<div class="tip-card"><strong>Tip:</strong> The area cards on the right side of the Assign tab show a count of how many volunteers are assigned vs. needed. Areas highlighted in red need more volunteers.</div>

<h2>Step 5: View the Full Schedule</h2>
<div class="step-card">
<p>Click the <strong>Schedule tab</strong> to see a complete grid showing all volunteers organized by time slot (rows) and area (columns).</p>
<p>Use the <strong>Date</strong> dropdown at the top to switch between convention days if you have multiple dates.</p>
<p>Click <strong>Print Schedule</strong> to get a physical copy to bring to the convention. The print view is clean and formatted for paper.</p>
<p>On convention day, volunteers who have been checked in show a green checkmark next to their name in the grid.</p>
</div>

<h2>Convention Day: Checking Volunteers In</h2>
<div class="step-card">
<p><span class="step-number">1</span> On convention day, open the <strong>Assign tab</strong> on your phone or laptop.</p>
<p><span class="step-number">2</span> Find the volunteer in their area card on the right side of the screen.</p>
<p><span class="step-number">3</span> Click <strong>Check In</strong> next to their name when they arrive at their post.</p>
<p>You can also see who is checked in at a glance from the <strong>Schedule tab</strong> — checked-in volunteers are shown in green.</p>
</div>

<h2>Quick Reference: What Each Status Means</h2>
<ul>
<li><strong>Pending</strong> — Just signed up, waiting for board review</li>
<li><strong>Approved</strong> — Board approved, ready to be assigned to a shift</li>
<li><strong>Assigned</strong> — Has been given an area and time slot (assignment email sent)</li>
<li><strong>Waitlisted</strong> — On hold, may be called if a spot opens up</li>
<li><strong>Declined</strong> — Application not accepted this year</li>
</ul>

<h2>Email Summary: When Are Emails Sent Automatically?</h2>
<div class="email-note">
<strong>The system sends these emails for you — no manual action needed:</strong>
<ul>
<li><strong>Signup submitted</strong> — Confirmation email sent immediately to the volunteer</li>
<li><strong>Volunteer assigned to a shift</strong> — Assignment email sent immediately with their area and time</li>
<li><strong>24 hours before convention</strong> (if enabled in Settings) — Reminder email sent automatically to all assigned volunteers</li>
</ul>
</div>

<h2>Editing This Guide</h2>
<p>To customize or update this guide, go to <strong>WP Admin &rsaquo; Volunteer Hub &rsaquo; Settings &rsaquo; Guide</strong> and paste your updated HTML in the text area.</p>
';
    }
}
