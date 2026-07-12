<?php
/**
 * PWA Settings Page
 * 
 * Manages all PWA configuration options
 */

if (!defined('ABSPATH')) {
    exit;
}

class OHA_PWA_Settings {
    
    /**
     * Initialize settings
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_settings_page'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts'));
    }

    /**
     * Enqueue WP media picker on our settings page only
     */
    public static function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_oha-pwa-settings') { return; }
        wp_enqueue_media();
    }
    
    /**
     * Add settings page to admin menu
     */
    public static function add_settings_page() {
        add_options_page(
            'OHA Convention PWA Settings',
            'PWA Settings',
            'manage_options',
            'oha-pwa-settings',
            array(__CLASS__, 'render_settings_page')
        );
        add_options_page(
            'OHA Incident Reports',
            'Incident Reports',
            'manage_options',
            'oha-incident-reports',
            array(__CLASS__, 'render_incident_reports_page')
        );
    }

    public static function render_incident_reports_page() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        global $wpdb;
        $table = $wpdb->prefix . 'oha_incident_reports';
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
        $reports = $table_exists
            ? $wpdb->get_results( "SELECT * FROM $table ORDER BY incident_time DESC", ARRAY_A )
            : [];
        ?>
        <div class="wrap">
            <h1>Incident Reports</h1>
            <p>All reports submitted through the board dashboard. Use your browser's Print function to generate a PDF of this page.</p>
            <?php if ( empty( $reports ) ) : ?>
                <p>No incident reports have been filed yet.</p>
            <?php else : ?>
            <style>
                .oha-ir-table { width:100%; border-collapse:collapse; font-size:13px; }
                .oha-ir-table th { background:#1d2327; color:#fff; padding:10px 12px; text-align:left; }
                .oha-ir-table td { padding:10px 12px; vertical-align:top; border-bottom:1px solid #ddd; }
                .oha-ir-table tr:nth-child(even) td { background:#f9f9f9; }
                .oha-ir-badge { display:inline-block; background:#AD3119; color:#fff; font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px; }
                .oha-ir-photos a { display:inline-block; margin:2px; }
                .oha-ir-photos img { width:80px; height:80px; object-fit:cover; border-radius:4px; border:1px solid #ccc; }
                @media print {
                    .oha-ir-table tr { page-break-inside: avoid; }
                    a[href]:after { content: none; }
                }
            </style>
            <table class="oha-ir-table widefat">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date / Time</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Reported By</th>
                        <th>People Involved</th>
                        <th>Description</th>
                        <th>Action Taken</th>
                        <th>Witnesses</th>
                        <th>Photos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $reports as $r ) :
                        $photos = array_filter( explode( ',', $r['photo_urls'] ?? '' ) );
                    ?>
                    <tr>
                        <td><?php echo (int) $r['id']; ?></td>
                        <td><?php echo esc_html( $r['incident_time'] ); ?></td>
                        <td><span class="oha-ir-badge"><?php echo esc_html( $r['incident_type'] ); ?></span></td>
                        <td><?php echo esc_html( $r['location'] ); ?></td>
                        <td><?php echo esc_html( $r['reported_by'] ); ?></td>
                        <td><?php echo esc_html( $r['people_involved'] ); ?></td>
                        <td><?php echo nl2br( esc_html( $r['description'] ) ); ?></td>
                        <td><?php echo nl2br( esc_html( $r['action_taken'] ) ); ?></td>
                        <td><?php echo esc_html( $r['witnesses'] ); ?></td>
                        <td class="oha-ir-photos">
                            <?php foreach ( $photos as $url ) : ?>
                                <a href="<?php echo esc_url( $url ); ?>" target="_blank">
                                    <img src="<?php echo esc_url( $url ); ?>" alt="photo">
                                </a>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top:12px;color:#666;font-size:12px;"><?php echo count( $reports ); ?> report(s) total. Filed to: <code><?php echo esc_html( $table ); ?></code></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Register all settings
     */
    public static function register_settings() {
        // Analytics Settings
        register_setting('oha_pwa_settings', 'oha_pwa_ga4_id');
        register_setting('oha_pwa_settings', 'oha_pwa_analytics_enabled');
        
        // Volunteer Area Settings
        register_setting('oha_pwa_settings', 'oha_pwa_volunteer_password');
        register_setting('oha_pwa_settings', 'oha_pwa_volunteer_content');
        register_setting('oha_pwa_settings', 'oha_pwa_vol_schedule');
        register_setting('oha_pwa_settings', 'oha_pwa_vol_venue_info');
        register_setting('oha_pwa_settings', 'oha_pwa_vol_emergency');
        register_setting('oha_pwa_settings', 'oha_pwa_vol_radio');
        register_setting('oha_pwa_settings', 'oha_pwa_vol_contacts');

        // Board Hub Settings
        register_setting('oha_pwa_settings', 'oha_pwa_board_password');
        register_setting('oha_pwa_settings', 'oha_pwa_board_notes');
        register_setting('oha_pwa_settings', 'oha_pwa_board_contacts');
        
        // FAQ Settings
        register_setting('oha_pwa_settings', 'oha_pwa_faq_enabled');
        register_setting('oha_pwa_settings', 'oha_pwa_faq_questions');
        
        // General Settings
        register_setting('oha_pwa_settings', 'oha_pwa_convention_start');
        register_setting('oha_pwa_settings', 'oha_pwa_convention_end');
        register_setting('oha_pwa_settings', 'oha_pwa_happenings');
        register_setting('oha_pwa_settings', 'oha_pwa_lost_found_items');
        register_setting('oha_pwa_settings', 'oha_pwa_lost_found_map_url');
        register_setting('oha_pwa_settings', 'oha_pwa_vendor_card_fields');
        register_setting('oha_pwa_settings', 'oha_convention_base_url');
        register_setting('oha_pwa_settings', 'oha_pwa_passport_url');
        register_setting('oha_pwa_settings', 'oha_pwa_scanner_url');
        register_setting('oha_pwa_settings', 'oha_pwa_convention_map_url');
        register_setting('oha_pwa_settings', 'oha_pwa_show_scan_fab');

        // App Download Page Settings
        register_setting('oha_pwa_settings', 'oha_pwa_dl_app_name');
        register_setting('oha_pwa_settings', 'oha_pwa_dl_tagline');
        register_setting('oha_pwa_settings', 'oha_pwa_dl_description');
        register_setting('oha_pwa_settings', 'oha_pwa_dl_convention_url');
        register_setting('oha_pwa_settings', 'oha_pwa_dl_schedule_url');
        register_setting('oha_pwa_settings', 'oha_pwa_dl_map_url');
        register_setting('oha_pwa_settings', 'oha_pwa_dl_vendors_url');
        register_setting('oha_pwa_settings', 'oha_pwa_dl_passport_url');
        register_setting('oha_pwa_settings', 'oha_pwa_dl_show_passport');
        // App Download Page — Screenshots
        register_setting('oha_pwa_settings', 'oha_pwa_dl_screenshot_hero');
        register_setting('oha_pwa_settings', 'oha_pwa_dl_screenshot_schedule');
        register_setting('oha_pwa_settings', 'oha_pwa_dl_screenshot_map');
        register_setting('oha_pwa_settings', 'oha_pwa_dl_screenshot_vendors');
        register_setting('oha_pwa_settings', 'oha_pwa_dl_screenshot_passport');
        register_setting('oha_pwa_settings', 'oha_pwa_dl_screenshot_notifications');
        register_setting('oha_pwa_settings', 'oha_pwa_dl_screenshot_offline');
        // App Download Page — Video
        register_setting('oha_pwa_settings', 'oha_pwa_dl_video_url');
        register_setting('oha_pwa_settings', 'oha_pwa_dl_video_title');
        // App Download Page — Version
        register_setting('oha_pwa_settings', 'oha_pwa_dl_show_version');
    }
    
    /**
     * Render settings page
     */
    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle form submission
        if (isset($_POST['oha_pwa_settings_submit'])) {
            check_admin_referer('oha_pwa_settings_save');
            self::save_settings();
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
        
        // Get current settings
        $ga4_id = get_option('oha_pwa_ga4_id', '');
        $analytics_enabled = get_option('oha_pwa_analytics_enabled', false);
        
        $volunteer_password = get_option('oha_pwa_volunteer_password', '');
        $volunteer_content  = get_option('oha_pwa_volunteer_content', '');
        $vol_schedule       = get_option('oha_pwa_vol_schedule', '');
        $vol_venue_info     = get_option('oha_pwa_vol_venue_info', '');
        $vol_emergency      = get_option('oha_pwa_vol_emergency', '');
        $vol_radio          = get_option('oha_pwa_vol_radio', '');
        $vol_contacts       = get_option('oha_pwa_vol_contacts', '');
        
        $faq_enabled = get_option('oha_pwa_faq_enabled', true);
        $faq_questions = get_option('oha_pwa_faq_questions', self::get_default_faqs());
        
        $convention_start = get_option('oha_pwa_convention_start', '2026-05-09 08:00');
        $convention_end = get_option('oha_pwa_convention_end', '2026-05-10 18:00');

        $board_password = get_option('oha_pwa_board_password', '');
        $board_notes    = get_option('oha_pwa_board_notes', '');
        $board_contacts = get_option('oha_pwa_board_contacts', '');
        $happenings     = get_option('oha_pwa_happenings', '');
        $lost_found_items   = get_option('oha_pwa_lost_found_items', '');
        $lost_found_map_url = get_option('oha_pwa_lost_found_map_url', '');
        $convention_base_url = get_option('oha_convention_base_url', '/convention');
        $_base = rtrim( $convention_base_url, '/' );
        $passport_url      = get_option('oha_pwa_passport_url', $_base . '/passport/');
        $scanner_url       = get_option('oha_pwa_scanner_url',  $_base . '/passport/');
        $convention_map_url = get_option('oha_pwa_convention_map_url', $_base . '/map/');
        $show_scan_fab  = get_option('oha_pwa_show_scan_fab', '1');
        $vendor_fields  = get_option('oha_pwa_vendor_card_fields', [
            'website'  => true,
            'products' => false,
            'social'   => false,
        ]);

        // App Download Page options
        $dl_app_name       = get_option('oha_pwa_dl_app_name', 'OHA Convention App');
        $dl_tagline        = get_option('oha_pwa_dl_tagline', 'Your all-in-one guide to the Ohio Haunters Convention');
        $dl_description    = get_option('oha_pwa_dl_description', '');
        $dl_convention_url = get_option('oha_pwa_dl_convention_url', '/convention/');
        $dl_schedule_url   = get_option('oha_pwa_dl_schedule_url', '/convention/schedule/');
        $dl_map_url        = get_option('oha_pwa_dl_map_url', '/convention/map/');
        $dl_vendors_url    = get_option('oha_pwa_dl_vendors_url', '/convention/vendors/');
        $dl_passport_url   = get_option('oha_pwa_dl_passport_url', '/convention/passport/');
        $dl_show_passport  = get_option('oha_pwa_dl_show_passport', true);
        // Screenshots
        $dl_sc_hero    = get_option('oha_pwa_dl_screenshot_hero', '');
        $dl_sc_sched   = get_option('oha_pwa_dl_screenshot_schedule', '');
        $dl_sc_map     = get_option('oha_pwa_dl_screenshot_map', '');
        $dl_sc_vendors = get_option('oha_pwa_dl_screenshot_vendors', '');
        $dl_sc_pass    = get_option('oha_pwa_dl_screenshot_passport', '');
        $dl_sc_notif   = get_option('oha_pwa_dl_screenshot_notifications', '');
        $dl_sc_offline = get_option('oha_pwa_dl_screenshot_offline', '');
        // Video
        $dl_video_url   = get_option('oha_pwa_dl_video_url', '');
        $dl_video_title = get_option('oha_pwa_dl_video_title', 'See It In Action');
        // Version
        $dl_show_version = get_option('oha_pwa_dl_show_version', true);

        ?>
        <div class="wrap">
            <h1>🎪 OHA Convention PWA Settings</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('oha_pwa_settings_save'); ?>
                
                <div class="nav-tab-wrapper" style="margin: 20px 0;">
                    <a href="#notifications" class="nav-tab nav-tab-active" data-tab="notifications">🔔 Push Notifications</a>
                    <a href="#analytics" class="nav-tab" data-tab="analytics">📊 Analytics</a>
                    <a href="#volunteer" class="nav-tab" data-tab="volunteer">👥 Volunteer Area</a>
                    <a href="#board" class="nav-tab" data-tab="board">🏛️ Board Hub</a>
                    <a href="#faq" class="nav-tab" data-tab="faq">❓ FAQ Chatbot</a>
                    <a href="#download" class="nav-tab" data-tab="download">📲 App Download Page</a>
                    <a href="#general" class="nav-tab" data-tab="general">⚙️ General</a>
                </div>
                
                <!-- Notifications Tab -->
                <div id="tab-notifications" class="tab-content">
                    <h2>Perfecty Push Notifications</h2>
                    <p>Push notifications are powered by <strong>Perfecty Push</strong> (self-hosted VAPID — no third-party CDN).</p>

                    <?php
                    global $wpdb;
                    $perfecty_opts   = get_option( 'perfecty_push', [] );
                    $perfecty_active = class_exists( 'Perfecty_Push_Lib_Push_Server' );
                    $vapid_ok        = ! empty( $perfecty_opts['vapid_public_key'] );
                    $pt              = $wpdb->prefix . 'perfecty_push_users';
                    $sub_count       = ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $pt ) ) === $pt )
                                       ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM $pt" ) : 0;
                    $scope           = $perfecty_opts['service_worker_scope'] ?? '(not set)';
                    ?>

                    <table class="form-table">
                        <tr>
                            <th>Perfecty Plugin</th>
                            <td><?php echo $perfecty_active ? '<span style="color:#46b450;">✅ Active</span>' : '<span style="color:#dc3232;">❌ Not active — install and activate Perfecty Push Notifications</span>'; ?></td>
                        </tr>
                        <tr>
                            <th>VAPID Key</th>
                            <td><?php echo $vapid_ok ? '<span style="color:#46b450;">✅ Configured</span>' : '<span style="color:#dc3232;">❌ Not configured — generate keys in Perfecty Push &rarr; Settings</span>'; ?></td>
                        </tr>
                        <tr>
                            <th>SW Scope</th>
                            <td><code><?php echo esc_html( $scope ); ?></code>
                                <p class="description">Expected proxy SW URL: <code>/perfecty-push-sw.js</code></p>
                            </td>
                        </tr>
                        <tr>
                            <th>Subscribers</th>
                            <td><strong><?php echo $sub_count; ?></strong> active device<?php echo $sub_count !== 1 ? 's' : ''; ?></td>
                        </tr>
                    </table>

                    <?php if ( $perfecty_active && $vapid_ok ): ?>
                        <div class="notice notice-success inline">
                            <p>✅ Perfecty Push is ready. Board members can send notifications using the <code>[oha_board_notifications]</code> shortcode, or from <a href="<?php echo admin_url( 'admin.php?page=perfecty-push-notifications' ); ?>">Perfecty Push &rarr; Send Notification</a>.</p>
                        </div>
                    <?php elseif ( ! $perfecty_active ): ?>
                        <div class="notice notice-error inline">
                            <p>⚠️ Perfecty Push plugin is not active. Push notifications will not work until it is installed and activated.</p>
                        </div>
                    <?php elseif ( ! $vapid_ok ): ?>
                        <div class="notice notice-warning inline">
                            <p>⚠️ VAPID keys not generated yet. Go to <a href="<?php echo admin_url( 'admin.php?page=perfecty-push-notifications-options' ); ?>">Perfecty Push &rarr; Settings</a> and generate your VAPID keys.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Analytics Tab -->
                <div id="tab-analytics" class="tab-content" style="display: none;">
                    <h2>Google Analytics 4 Integration</h2>
                    <p>Track PWA installs, usage, and user behavior.</p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="analytics_enabled">Enable Analytics</label>
                            </th>
                            <td>
                                <input type="checkbox" id="analytics_enabled" name="oha_pwa_analytics_enabled" value="1" <?php checked($analytics_enabled, true); ?>>
                                <p class="description">Turn on Google Analytics 4 tracking</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="ga4_id">GA4 Measurement ID *</label>
                            </th>
                            <td>
                                <input type="text" id="ga4_id" name="oha_pwa_ga4_id" value="<?php echo esc_attr($ga4_id); ?>" class="regular-text" placeholder="G-XXXXXXXXXX">
                                <p class="description">
                                    Get your ID from <a href="https://analytics.google.com" target="_blank">Google Analytics</a><br>
                                    Format: G-XXXXXXXXXX
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php if ($analytics_enabled && $ga4_id): ?>
                        <div class="notice notice-info inline">
                            <p>
                                <strong>What we track:</strong><br>
                                • PWA installs<br>
                                • Page views<br>
                                • Events added to favorites<br>
                                • Vendors favorited<br>
                                • Offline usage<br>
                                • Calendar exports<br>
                                • Push notification clicks
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Volunteer Tab -->
                <div id="tab-volunteer" class="tab-content" style="display: none;">
                    <h2>Volunteer Password-Protected Area</h2>
                    <p>Provide volunteers access to special information via Info tab.</p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="volunteer_password">Volunteer Password *</label>
                            </th>
                            <td>
                                <input type="text" id="volunteer_password" name="oha_pwa_volunteer_password" value="" class="regular-text" placeholder="<?php echo $volunteer_password ? '(password set — enter new value to change)' : 'Enter shared password'; ?>">
                                <p class="description">All volunteers will use this password to access volunteer info. Keep it simple! (Example: OHC2026)</p>
                            </td>
                        </tr>
                        
                    </table>

                    <h3 style="margin-top:28px;">Structured Sections</h3>
                    <p>These fields render as formatted cards in the volunteer portal. Leave any section blank to hide it.</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="vol_schedule">Event Times</label></th>
                            <td>
                                <textarea id="vol_schedule" name="oha_pwa_vol_schedule" rows="6" class="large-text code"><?php echo esc_textarea($vol_schedule); ?></textarea>
                                <p class="description">One entry per line. Format: <code>Label | Date/Time | Notes</code><br>
                                Example: <code>Volunteer Check-in | Fri May 9, 7:30 AM | Main entrance</code></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="vol_venue_info">Venue Details</label></th>
                            <td>
                                <textarea id="vol_venue_info" name="oha_pwa_vol_venue_info" rows="5" class="large-text"><?php echo esc_textarea($vol_venue_info); ?></textarea>
                                <p class="description">Address, parking, check-in location, Wi-Fi, etc. Free text — displayed as a text block.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="vol_emergency">Emergency Information</label></th>
                            <td>
                                <textarea id="vol_emergency" name="oha_pwa_vol_emergency" rows="6" class="large-text code"><?php echo esc_textarea($vol_emergency); ?></textarea>
                                <p class="description">One entry per line. Format: <code>Label | Detail</code><br>
                                Example:<br>
                                <code>AED Location | Near main entrance, left of security desk</code><br>
                                <code>First Aid | Room 101, ground floor</code><br>
                                <code>Fire Exits | North stairwell and south emergency door</code><br>
                                <code>Emergency Number | 911</code><br>
                                <code>Venue Security | 555-0199</code></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="vol_radio">Radio Channels</label></th>
                            <td>
                                <textarea id="vol_radio" name="oha_pwa_vol_radio" rows="6" class="large-text code"><?php echo esc_textarea($vol_radio); ?></textarea>
                                <p class="description">One entry per line. Format: <code>Team / Area | Channel</code><br>
                                Example:<br>
                                <code>Board | Channel 1</code><br>
                                <code>Registration | Channel 2</code><br>
                                <code>Venue Contact | Channel 3</code></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="vol_contacts">Key Contacts</label></th>
                            <td>
                                <textarea id="vol_contacts" name="oha_pwa_vol_contacts" rows="6" class="large-text code"><?php echo esc_textarea($vol_contacts); ?></textarea>
                                <p class="description">One entry per line. Format: <code>Name | Role | Phone</code><br>
                                Example: <code>Jane Doe | Volunteer Coordinator | 555-0101</code></p>
                            </td>
                        </tr>
                    </table>

                    <h3 style="margin-top:28px;">Additional Notes</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="volunteer_content">Free-form Content</label></th>
                            <td>
                                <?php
                                wp_editor($volunteer_content, 'volunteer_content', array(
                                    'textarea_name' => 'oha_pwa_volunteer_content',
                                    'textarea_rows' => 10,
                                    'media_buttons' => true,
                                    'teeny'         => false,
                                ));
                                ?>
                                <p class="description">Shown at the bottom of the volunteer portal after the structured sections above. Use for announcements, links to documents, or anything that does not fit the structured format.</p>
                            </td>
                        </tr>
                    </table>

                    <div class="notice notice-info inline" style="margin-top:20px;">
                        <p>📋 <strong>Shortcode:</strong> Add <code>[oha_volunteer_area]</code> to any page to display the password-protected volunteer portal.</p>
                    </div>
                </div>

                <!-- Board Hub Tab -->
                <div id="tab-board" class="tab-content" style="display: none;">
                    <h2>Board Hub</h2>
                    <p>Configure the password-protected board dashboard accessible via the PWA. Add the <code>[oha_board_dashboard]</code> shortcode to a page.</p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="board_password">Board Password *</label>
                            </th>
                            <td>
                                <input type="text" id="board_password" name="oha_pwa_board_password" value="" class="regular-text" placeholder="<?php echo $board_password ? '(password set — enter new value to change)' : 'Enter shared board password'; ?>">
                                <p class="description">All board members will use this password to access the dashboard on-site. Keep it memorable but not guessable. (Example: OHCBoard2026)</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="board_notes">Board Notes</label>
                            </th>
                            <td>
                                <?php
                                wp_editor($board_notes, 'board_notes', array(
                                    'textarea_name' => 'oha_pwa_board_notes',
                                    'textarea_rows' => 12,
                                    'media_buttons' => true,
                                    'teeny'         => false,
                                ));
                                ?>
                                <p class="description">
                                    Shown in the Board Notes tab of the dashboard. Great for: venue rules, parking / radio channels, last-minute info, links to documents.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="board_contacts">Emergency Contacts</label>
                            </th>
                            <td>
                                <textarea id="board_contacts" name="oha_pwa_board_contacts" rows="8" class="large-text code" placeholder="Name | Role | Phone"><?php echo esc_textarea($board_contacts); ?></textarea>
                                <p class="description">
                                    One contact per line. Format: <code>Name | Role | Phone</code><br>
                                    Example:<br>
                                    <code>John Smith | Venue Manager | 555-0100</code><br>
                                    <code>Jane Doe | Safety Officer | 555-0101</code>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <div class="notice notice-info inline" style="margin-top:20px;">
                        <p>📋 <strong>Shortcode:</strong> Add <code>[oha_board_dashboard]</code> to any page to display the full password-protected board dashboard (5 tabs: Notifications, Stats, Notes, Contacts, Quick Links).</p>
                    </div>
                </div>

                <!-- FAQ Tab -->
                <div id="tab-faq" class="tab-content" style="display: none;">
                    <h2>FAQ Chatbot</h2>
                    <p>Answer common convention questions automatically.</p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="faq_enabled">Enable FAQ</label>
                            </th>
                            <td>
                                <input type="checkbox" id="faq_enabled" name="oha_pwa_faq_enabled" value="1" <?php checked($faq_enabled, true); ?>>
                                <p class="description">Show FAQ chatbot in Info tab</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="faq_questions">FAQ Questions & Answers</label>
                            </th>
                            <td>
                                <textarea id="faq_questions" name="oha_pwa_faq_questions" rows="20" class="large-text code"><?php echo esc_textarea($faq_questions); ?></textarea>
                                <p class="description">
                                    <strong>Format (one per line):</strong><br>
                                    <code>Question text|Answer text</code><br><br>
                                    <strong>Example:</strong><br>
                                    <code>What time does registration start?|Registration opens at 8:00 AM on Friday, May 9th at the main entrance.</code><br>
                                    <code>Where is parking?|Free parking is available in the main lot. Follow signs for "OHA Convention Parking".</code>
                                </p>
                                <button type="button" class="button" onclick="document.getElementById('faq_questions').value = '<?php echo esc_js(self::get_default_faqs()); ?>'">Reset to Defaults</button>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- App Download Page Tab -->
                <div id="tab-download" class="tab-content" style="display: none;">
                    <h2>App Download Page</h2>
                    <p>Settings for the <code>[oha_pwa_download]</code> shortcode — the public-facing page that promotes and teaches visitors how to install the convention app.</p>

                    <h3>Branding</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="oha_pwa_dl_app_name">App Name</label>
                            </th>
                            <td>
                                <input type="text" id="oha_pwa_dl_app_name" name="oha_pwa_dl_app_name" value="<?php echo esc_attr($dl_app_name); ?>" class="regular-text" placeholder="OHA Convention App">
                                <p class="description">Displayed in the hero heading and throughout the page.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="oha_pwa_dl_tagline">Tagline</label>
                            </th>
                            <td>
                                <input type="text" id="oha_pwa_dl_tagline" name="oha_pwa_dl_tagline" value="<?php echo esc_attr($dl_tagline); ?>" class="large-text" placeholder="Your all-in-one guide to Ohio Haunters Convention 2026">
                                <p class="description">Short subtitle shown below the app name in the hero.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="oha_pwa_dl_description">Hero Description</label>
                            </th>
                            <td>
                                <textarea id="oha_pwa_dl_description" name="oha_pwa_dl_description" rows="3" class="large-text"><?php echo esc_textarea($dl_description); ?></textarea>
                                <p class="description">Optional paragraph shown beneath the tagline. Leave blank to hide.</p>
                            </td>
                        </tr>
                    </table>

                    <?php
                    // Helper to render one media-picker row
                    $oha_sc_row = function($label, $name, $value, $desc = '') { ?>
                        <tr>
                            <th scope="row"><label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label></th>
                            <td>
                                <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                    <input type="text" id="<?php echo esc_attr($name); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text oha-media-url" placeholder="https://...">
                                    <button type="button" class="button oha-media-btn" data-target="<?php echo esc_attr($name); ?>">Choose Image</button>
                                    <button type="button" class="button oha-media-clear" data-target="<?php echo esc_attr($name); ?>">&#10005; Clear</button>
                                </div>
                                <?php if ($value): ?>
                                <div id="prev_<?php echo esc_attr($name); ?>" class="oha-media-preview" style="margin-top:8px;">
                                    <img src="<?php echo esc_url($value); ?>" style="max-height:80px;max-width:200px;border-radius:4px;border:1px solid #444;">
                                </div>
                                <?php else: ?>
                                <div id="prev_<?php echo esc_attr($name); ?>" class="oha-media-preview"></div>
                                <?php endif; ?>
                                <?php if ($desc): ?><p class="description"><?php echo esc_html($desc); ?></p><?php endif; ?>
                            </td>
                        </tr>
                    <?php }; ?>

                    <h3 style="margin-top:24px;">Screenshots</h3>
                    <p>Upload screenshots of each app feature. When set, clicking a feature card on the page opens the screenshot in a lightbox. <strong>Recommended size: portrait phone screenshot (e.g. 390 x 844px).</strong></p>
                    <table class="form-table">
                        <?php $oha_sc_row('Hero / App Preview', 'oha_pwa_dl_screenshot_hero', $dl_sc_hero, 'Shown in the hero section replacing the CSS phone mockup. Also the first image in the lightbox gallery.'); ?>
                        <?php $oha_sc_row('Full Event Schedule', 'oha_pwa_dl_screenshot_schedule', $dl_sc_sched); ?>
                        <?php $oha_sc_row('Interactive Map', 'oha_pwa_dl_screenshot_map', $dl_sc_map); ?>
                        <?php $oha_sc_row('Vendor Favorites', 'oha_pwa_dl_screenshot_vendors', $dl_sc_vendors); ?>
                        <?php $oha_sc_row('Haunt Passport', 'oha_pwa_dl_screenshot_passport', $dl_sc_pass, 'Only shown if Show Passport Card is checked below.'); ?>
                        <?php $oha_sc_row('Push Notifications', 'oha_pwa_dl_screenshot_notifications', $dl_sc_notif); ?>
                        <?php $oha_sc_row('Works Offline', 'oha_pwa_dl_screenshot_offline', $dl_sc_offline); ?>
                    </table>

                    <h3 style="margin-top:24px;">Promo Video</h3>
                    <p>Optional video shown between the hero and the feature cards. Paste a YouTube or Vimeo URL and it will be embedded automatically.</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="oha_pwa_dl_video_url">Video URL</label></th>
                            <td>
                                <input type="text" id="oha_pwa_dl_video_url" name="oha_pwa_dl_video_url" value="<?php echo esc_attr($dl_video_url); ?>" class="large-text" placeholder="https://www.youtube.com/watch?v=...">
                                <p class="description">YouTube, Vimeo, or direct .mp4 URL. Leave blank to hide the video section.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="oha_pwa_dl_video_title">Video Section Title</label></th>
                            <td>
                                <input type="text" id="oha_pwa_dl_video_title" name="oha_pwa_dl_video_title" value="<?php echo esc_attr($dl_video_title); ?>" class="regular-text" placeholder="See It In Action">
                            </td>
                        </tr>
                    </table>

                    <h3 style="margin-top:24px;">QR Code &amp; Version</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">QR Code</th>
                            <td>
                                <p class="description" style="margin:0;">The QR code on the desktop &#8220;Get It On Your Phone&#8221; section is <strong>auto-generated</strong> from the current page URL. No configuration needed &#8212; just publish the shortcode on your &#8220;Get the App&#8221; page and the QR will point to it automatically.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Show App Version</th>
                            <td>
                                <label><input type="checkbox" name="oha_pwa_dl_show_version" value="1" <?php checked($dl_show_version); ?>> Show the current app version number on the page (e.g. &#8220;App v3.13.0&#8221;)</label>
                                <p class="description">Displays next to the hero badges and in the already-installed footer. Useful for attendees who want to know they have the latest version.</p>
                            </td>
                        </tr>
                    </table>

                    <h3 style="margin-top:24px;">Feature Card — Passport / QR Stamps</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Show Passport Card</th>
                            <td>
                                <label><input type="checkbox" name="oha_pwa_dl_show_passport" value="1" <?php checked($dl_show_passport); ?>> Show the Passport &amp; QR Stamps feature card in the features grid</label>
                                <p class="description">Uncheck if the Passport system is not active for this convention year.</p>
                            </td>
                        </tr>
                    </table>

                    <h3 style="margin-top:24px;">Page URLs</h3>
                    <p>These URLs are used for "Open App" / "Go to" links on the download page. Use site-relative paths (e.g. <code>/convention/schedule/</code>).</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="oha_pwa_dl_convention_url">Convention Home URL</label>
                            </th>
                            <td>
                                <input type="text" id="oha_pwa_dl_convention_url" name="oha_pwa_dl_convention_url" value="<?php echo esc_attr($dl_convention_url); ?>" class="regular-text" placeholder="/convention/">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="oha_pwa_dl_schedule_url">Schedule Page URL</label>
                            </th>
                            <td>
                                <input type="text" id="oha_pwa_dl_schedule_url" name="oha_pwa_dl_schedule_url" value="<?php echo esc_attr($dl_schedule_url); ?>" class="regular-text" placeholder="/convention/schedule/">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="oha_pwa_dl_map_url">Interactive Map URL</label>
                            </th>
                            <td>
                                <input type="text" id="oha_pwa_dl_map_url" name="oha_pwa_dl_map_url" value="<?php echo esc_attr($dl_map_url); ?>" class="regular-text" placeholder="/convention/map/">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="oha_pwa_dl_vendors_url">Vendors Page URL</label>
                            </th>
                            <td>
                                <input type="text" id="oha_pwa_dl_vendors_url" name="oha_pwa_dl_vendors_url" value="<?php echo esc_attr($dl_vendors_url); ?>" class="regular-text" placeholder="/convention/vendors/">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="oha_pwa_dl_passport_url">Passport Page URL</label>
                            </th>
                            <td>
                                <input type="text" id="oha_pwa_dl_passport_url" name="oha_pwa_dl_passport_url" value="<?php echo esc_attr($dl_passport_url); ?>" class="regular-text" placeholder="/convention/passport/">
                                <p class="description">Used in the "Already Installed" footer and the Passport feature card link.</p>
                            </td>
                        </tr>
                    </table>

                    <div class="notice notice-info inline" style="margin-top:20px;">
                        <p>📲 <strong>Shortcode:</strong> Add <code>[oha_pwa_download]</code> to any page to display the full app marketing and installation guide.</p>
                    </div>
                </div>

                <!-- General Tab -->
                <div id="tab-general" class="tab-content" style="display: none;">
                    <h2>General PWA Settings</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="convention_start">Convention Start Date/Time</label>
                            </th>
                            <td>
                                <input type="datetime-local" id="convention_start" name="oha_pwa_convention_start" value="<?php echo esc_attr($convention_start); ?>">
                                <p class="description">Used for "What's Happening Now" feature</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="convention_end">Convention End Date/Time</label>
                            </th>
                            <td>
                                <input type="datetime-local" id="convention_end" name="oha_pwa_convention_end" value="<?php echo esc_attr($convention_end); ?>">
                                <p class="description">Used for "What's Happening Now" feature</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="oha_convention_base_url">Convention Base URL</label>
                            </th>
                            <td>
                                <input type="text" id="oha_convention_base_url" name="oha_convention_base_url" value="<?php echo esc_attr($convention_base_url); ?>" class="regular-text" placeholder="/convention">
                                <p class="description">The base path for all convention pages, without a trailing slash (e.g. <code>/convention</code>). Used by all OHA plugins as the root for subpage links. Update this once each year if you use year-specific URLs.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="oha_pwa_passport_url">Passport Page URL</label>
                            </th>
                            <td>
                                <input type="text" id="oha_pwa_passport_url" name="oha_pwa_passport_url" value="<?php echo esc_attr($passport_url); ?>" class="regular-text" placeholder="/convention/passport/">
                                <p class="description">URL of the page using <code>[oha_convention_passport]</code>. Used as the Passport link from the scan button on the nav. Leave blank to derive from Convention Base URL.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="oha_pwa_scanner_url">Scanner Page URL</label>
                            </th>
                            <td>
                                <input type="text" id="oha_pwa_scanner_url" name="oha_pwa_scanner_url" value="<?php echo esc_attr($scanner_url); ?>" class="regular-text" placeholder="/convention/passport/">
                                <p class="description">URL of the page with the QR scanner shortcode (<code>[oha_smart_scanner]</code>). The Scan button in the app nav links here. Leave blank to derive from Convention Base URL.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="oha_pwa_convention_map_url">Convention Map URL</label>
                            </th>
                            <td>
                                <input type="text" id="oha_pwa_convention_map_url" name="oha_pwa_convention_map_url" value="<?php echo esc_attr($convention_map_url); ?>" class="regular-text" placeholder="/convention/map/">
                                <p class="description">URL of the page using <code>[oha_convention_map]</code>. Used by vendor cards to generate the "Find Booth" locate link. Leave blank to derive from Convention Base URL.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Show Scan Button</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="oha_pwa_show_scan_fab" value="1" <?php checked($show_scan_fab, '1'); ?>>
                                    Show the Scan button in the app navigation bar
                                </label>
                                <p class="description">Uncheck to hide the scan button. Use this if the Passport system is not active for the current event.</p>
                            </td>
                        </tr>
                    </table>

                    <h3 style="margin-top:24px;">&#127968; Home Page Happenings</h3>
                    <p>Items shown in the Happenings section of <code>[oha_convention_home]</code>. One item per line: <code>Title|Description</code>. Leave empty to use the built-in defaults.</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="oha_pwa_happenings">Happenings Items</label>
                            </th>
                            <td>
                                <textarea id="oha_pwa_happenings" name="oha_pwa_happenings" rows="6" class="large-text" style="font-family:monospace;"><?php echo esc_textarea($happenings); ?></textarea>
                                <p class="description">
                                    Format: <code>Title|Description</code> &mdash; one item per line.<br>
                                    Example: <code>Vendor registration is open|Want a booth? Sign up now!</code>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <h3 style="margin-top:24px;">Lost and Found</h3>
                    <p>Items listed here appear on the Lost and Found card in the attendee Info section. One item per line. Leave empty to show only the location link.</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="oha_pwa_lost_found_map_url">Lost and Found Location URL</label>
                            </th>
                            <td>
                                <input type="text" id="oha_pwa_lost_found_map_url" name="oha_pwa_lost_found_map_url" value="<?php echo esc_attr($lost_found_map_url); ?>" class="regular-text" placeholder="/convention/map/?oha_vendor=lost-found">
                                <p class="description">Optional deep link to the map showing the Lost and Found table location. Leave empty to hide the link.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="oha_pwa_lost_found_items">Found Items</label>
                            </th>
                            <td>
                                <textarea id="oha_pwa_lost_found_items" name="oha_pwa_lost_found_items" rows="6" class="large-text" style="font-family:monospace;"><?php echo esc_textarea($lost_found_items); ?></textarea>
                                <p class="description">One item per line. Example: <code>Black backpack - turned in 2:00 PM Saturday</code></p>
                            </td>
                        </tr>
                    </table>

                    <h3 style="margin-top:24px;">&#128717; Vendor Cards</h3>
                    <p>Choose which fields appear on vendor cards (logo and name always shown).</p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Card Fields</th>
                            <td>
                                <label><input type="checkbox" name="oha_pwa_vendor_card_fields[website]" value="1" <?php checked(!empty($vendor_fields['website'])); ?>> Website link</label><br>
                                <label><input type="checkbox" name="oha_pwa_vendor_card_fields[products]" value="1" <?php checked(!empty($vendor_fields['products'])); ?>> Products / Description</label><br>
                                <label><input type="checkbox" name="oha_pwa_vendor_card_fields[social]" value="1" <?php checked(!empty($vendor_fields['social'])); ?>> Social media</label>
                            </td>
                        </tr>
                    </table>

                    <h3>PWA Info</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Current Version</th>
                            <td><code><?php echo OHA_PWA_VERSION; ?></code></td>
                        </tr>
                        <tr>
                            <th scope="row">Service Worker</th>
                            <td><?php echo file_exists(ABSPATH . 'sw.js') ? '✅ Installed' : '❌ Not Found'; ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Manifest</th>
                            <td><?php echo file_exists(ABSPATH . 'manifest.json') ? '✅ Installed' : '❌ Not Found'; ?></td>
                        </tr>
                    </table>
                    <h3 style="margin-top:30px;">📋 Shortcode Reference</h3>
                    <p>All available shortcodes provided by this plugin:</p>
                    <table class="widefat striped" style="max-width:900px;">
                        <thead>
                            <tr>
                                <th style="width:32%;">Shortcode</th>
                                <th>Description</th>
                                <th style="width:22%;">Typical page</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>[oha_convention_schedule]</code></td>
                                <td>Full convention schedule with tabbed view (Schedule, My Classes). Integrates Actor Passport tabs if the OHA Passport Hub plugin is active.</td>
                                <td>Schedule page</td>
                            </tr>
                            <tr>
                                <td><code>[oha_my_schedule]</code></td>
                                <td>Shows the logged-in user's favorited schedule items. Requires login.</td>
                                <td>My OHC / My Schedule page</td>
                            </tr>
                            <tr>
                                <td><code>[oha_pwa_event_card id="" title="" time="" room="" url=""]</code></td>
                                <td>Renders a favoritable event card for standalone events not in the Schedule Hub (e.g. Costume Ball). The <code>id</code> and <code>title</code> attributes are required.</td>
                                <td>Any standalone event page</td>
                            </tr>
                            <tr>
                                <td><code>[oha_happening_now]</code></td>
                                <td>"What's Happening Now" widget — automatically populated by JS from the live schedule data.</td>
                                <td>Convention home / Info page</td>
                            </tr>
                            <tr>
                                <td><code>[oha_notification_subscribe]</code></td>
                                <td>Push notification subscribe / unsubscribe toggle widget (Perfecty). Shows current status and an Enable button.</td>
                                <td>Info / My OHC page</td>
                            </tr>
                            <tr>
                                <td><code>[oha_faq]</code></td>
                                <td>FAQ chatbot — questions &amp; answers are managed in the FAQ Chatbot tab above.</td>
                                <td>Info / FAQ page</td>
                            </tr>
                            <tr>
                                <td><code>[oha_volunteer_area]</code></td>
                                <td>Password-protected volunteer info area. Content and password are set in the Volunteer Area tab above.</td>
                                <td>Info / Volunteer page</td>
                            </tr>
                            <tr>
                                <td><code>[oha_favorite_vendors]</code></td>
                                <td>Displays the user's localStorage-backed favorite vendors list with a CSV export button.</td>
                                <td>Vendors / My OHC page</td>
                            </tr>
                            <tr>
                                <td><code>[oha_convention_home]</code></td>
                                <td>Welcome / home page content: hero card, info grid, and Happenings section. Place <code>[oha_pwa_onboarding]</code> above it on the page. Happenings items editable in General &rarr; Home Page Happenings above.</td>
                                <td>Convention home (/convention/)</td>
                            </tr>
                            <tr>
                                <td><code>[oha_pwa_onboarding]</code></td>
                                <td>Step-by-step PWA install + notification setup wizard. Completed steps auto-collapse; all done shows a "Setup Complete" banner with a "See all steps" toggle. State saved in localStorage. Optional atts: <code>show_install="true|false"</code>, <code>show_notifications="true|false"</code>.</td>
                                <td>Convention home / Get Started page</td>
                            </tr>
                            <tr>
                                <td><code>[oha_board_notifications]</code></td>
                                <td>Frontend push notification send form for board members (requires <em>edit_pages</em> capability). Sends via Perfecty Push to all subscribers.</td>
                                <td>Board Hub page</td>
                            </tr>
                            <tr>
                                <td><code>[oha_board_dashboard]</code></td>
                                <td>Full password-protected board dashboard with 5 tabs: Notifications, Live Stats, Board Notes, Emergency Contacts, Quick Links. Password and content set in the Board Hub tab above.</td>
                                <td>Board Hub page</td>
                            </tr>
                            <tr>
                                <td><code>[oha_local_notification_test]</code></td>
                                <td>Admin-only local notification test tool — fires a notification via the service worker (same path as 5-minute reminders). Invisible to non-admins.</td>
                                <td>Any page (admin only)</td>
                            </tr>
                            <tr>
                                <td><code>[oha_pwa_download]</code></td>
                                <td>App marketing and install guide page — promotes the convention PWA with OS-specific install instructions (Android/iOS/Desktop), features overview (schedule, map, vendors, passport, notifications), and notification setup walkthrough. Branding and URLs are configurable in the App Download Page tab above.</td>
                                <td>Standalone "Get the App" page</td>
                            </tr>
                            <tr>
                                <td><code>[oha_pwa_settings]</code></td>
                                <td>User-facing app settings page. Sections: App Info (version, connection status, PWA mode), Notification permission status, Restore Dismissed Content (onboarding, happenings), Clear Saved Data (schedule + vendor favorites), Reset All App Data, Check for Updates. All localStorage-only, no AJAX.</td>
                                <td>Info page (link from a card)</td>
                            </tr>
                            <tr>
                                <td><code>[oha_convention_vendors]</code></td>
                                <td>Full-width vendor card grid — shows all paid vendors (status=Paid on vendor_application CPT) with logo, name, website, booth number, and heart favorite button.</td>
                                <td>Vendors page</td>
                            </tr>
                            <tr>
                                <td><code>[oha_convention_passport]</code></td>
                                <td>Pre-event Haunt Passport page — hero + countdown, checklist, demo stamp claim, how-it-works explainer, and embedded Passport Hub shortcodes. Requires OHA Passport Hub plugin.</td>
                                <td>Passport page</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="submit">
                    <input type="submit" name="oha_pwa_settings_submit" class="button button-primary" value="Save All Settings">
                </p>
            </form>
        </div>
        
        <style>
            .nav-tab-wrapper { border-bottom: 1px solid #ccc; }
            .nav-tab { cursor: pointer; }
            .tab-content { background: white; padding: 20px; border: 1px solid #ccc; border-top: none; }
            .notice.inline { margin: 20px 0; padding: 10px; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.tab-content').hide();
                $('#tab-' + tab).show();
            });

            // WP Media Picker — Choose Image
            $(document).on('click', '.oha-media-btn', function(e) {
                e.preventDefault();
                var targetId = $(this).data('target');
                var frame = wp.media({
                    title: 'Choose Screenshot',
                    button: { text: 'Use this image' },
                    multiple: false
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#' + targetId).val(attachment.url);
                    $('#prev_' + targetId).html('<img src="' + attachment.url + '" style="max-height:80px;max-width:200px;border-radius:4px;border:1px solid #444;margin-top:4px;">');
                });
                frame.open();
            });

            // WP Media Picker — Clear
            $(document).on('click', '.oha-media-clear', function(e) {
                e.preventDefault();
                var targetId = $(this).data('target');
                $('#' + targetId).val('');
                $('#prev_' + targetId).html('');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save settings
     */
    private static function save_settings() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'oha_pwa_settings_save')) {
            return;
        }
        
        // Save Analytics settings
        update_option('oha_pwa_ga4_id', sanitize_text_field(wp_unslash($_POST['oha_pwa_ga4_id'] ?? '')));
        update_option('oha_pwa_analytics_enabled', isset($_POST['oha_pwa_analytics_enabled']));

        // Save Volunteer settings
        $new_vol_pw = sanitize_text_field( wp_unslash( $_POST['oha_pwa_volunteer_password'] ?? '' ) );
        if ( ! empty( $new_vol_pw ) ) {
            update_option( 'oha_pwa_volunteer_password', wp_hash_password( $new_vol_pw ) );
        }
        update_option('oha_pwa_volunteer_content', wp_kses_post(wp_unslash($_POST['oha_pwa_volunteer_content'] ?? '')));
        update_option('oha_pwa_vol_schedule',  sanitize_textarea_field(wp_unslash($_POST['oha_pwa_vol_schedule']  ?? '')));
        update_option('oha_pwa_vol_venue_info', sanitize_textarea_field(wp_unslash($_POST['oha_pwa_vol_venue_info'] ?? '')));
        update_option('oha_pwa_vol_emergency', sanitize_textarea_field(wp_unslash($_POST['oha_pwa_vol_emergency'] ?? '')));
        update_option('oha_pwa_vol_radio',     sanitize_textarea_field(wp_unslash($_POST['oha_pwa_vol_radio']     ?? '')));
        update_option('oha_pwa_vol_contacts',  sanitize_textarea_field(wp_unslash($_POST['oha_pwa_vol_contacts']  ?? '')));

        // Save Board Hub settings
        $new_board_pw = sanitize_text_field( wp_unslash( $_POST['oha_pwa_board_password'] ?? '' ) );
        if ( ! empty( $new_board_pw ) ) {
            update_option( 'oha_pwa_board_password', wp_hash_password( $new_board_pw ) );
        }
        update_option('oha_pwa_board_notes',    wp_kses_post(wp_unslash($_POST['oha_pwa_board_notes'] ?? '')));
        update_option('oha_pwa_board_contacts', sanitize_textarea_field(wp_unslash($_POST['oha_pwa_board_contacts'] ?? '')));

        // Save FAQ settings
        update_option('oha_pwa_faq_enabled', isset($_POST['oha_pwa_faq_enabled']));
        update_option('oha_pwa_faq_questions', sanitize_textarea_field(wp_unslash($_POST['oha_pwa_faq_questions'] ?? '')));

        // Save General settings
        update_option('oha_pwa_convention_start', sanitize_text_field(wp_unslash($_POST['oha_pwa_convention_start'] ?? '')));
        update_option('oha_pwa_convention_end', sanitize_text_field(wp_unslash($_POST['oha_pwa_convention_end'] ?? '')));
        update_option('oha_pwa_happenings', sanitize_textarea_field(wp_unslash($_POST['oha_pwa_happenings'] ?? '')));
        update_option('oha_pwa_lost_found_items',   sanitize_textarea_field(wp_unslash($_POST['oha_pwa_lost_found_items']   ?? '')));
        update_option('oha_pwa_lost_found_map_url', esc_url_raw($_POST['oha_pwa_lost_found_map_url'] ?? ''));
        update_option('oha_convention_base_url',    sanitize_text_field(wp_unslash($_POST['oha_convention_base_url'] ?? '/convention')));
        update_option('oha_pwa_passport_url',       esc_url_raw($_POST['oha_pwa_passport_url']       ?? ''));
        update_option('oha_pwa_scanner_url',        esc_url_raw($_POST['oha_pwa_scanner_url']        ?? ''));
        update_option('oha_pwa_convention_map_url', esc_url_raw($_POST['oha_pwa_convention_map_url'] ?? ''));
        update_option('oha_pwa_show_scan_fab', ! empty($_POST['oha_pwa_show_scan_fab']) ? '1' : '0');

        // Save Vendor Card Fields
        $vendor_fields = [];
        $vendor_fields['website']  = ! empty( $_POST['oha_pwa_vendor_card_fields']['website'] );
        $vendor_fields['products'] = ! empty( $_POST['oha_pwa_vendor_card_fields']['products'] );
        $vendor_fields['social']   = ! empty( $_POST['oha_pwa_vendor_card_fields']['social'] );
        update_option('oha_pwa_vendor_card_fields', $vendor_fields);

        // Save App Download Page settings
        update_option('oha_pwa_dl_app_name',       sanitize_text_field(wp_unslash($_POST['oha_pwa_dl_app_name']       ?? '')));
        update_option('oha_pwa_dl_tagline',        sanitize_text_field(wp_unslash($_POST['oha_pwa_dl_tagline']        ?? '')));
        update_option('oha_pwa_dl_description',    sanitize_textarea_field(wp_unslash($_POST['oha_pwa_dl_description'] ?? '')));
        update_option('oha_pwa_dl_convention_url', esc_url_raw($_POST['oha_pwa_dl_convention_url']         ?? ''));
        update_option('oha_pwa_dl_schedule_url',   esc_url_raw($_POST['oha_pwa_dl_schedule_url']           ?? ''));
        update_option('oha_pwa_dl_map_url',        esc_url_raw($_POST['oha_pwa_dl_map_url']                ?? ''));
        update_option('oha_pwa_dl_vendors_url',    esc_url_raw($_POST['oha_pwa_dl_vendors_url']            ?? ''));
        update_option('oha_pwa_dl_passport_url',   esc_url_raw($_POST['oha_pwa_dl_passport_url']           ?? ''));
        update_option('oha_pwa_dl_show_passport',  isset($_POST['oha_pwa_dl_show_passport']));

        // Save App Download Page — Screenshots
        update_option('oha_pwa_dl_screenshot_hero',          esc_url_raw($_POST['oha_pwa_dl_screenshot_hero']          ?? ''));
        update_option('oha_pwa_dl_screenshot_schedule',      esc_url_raw($_POST['oha_pwa_dl_screenshot_schedule']      ?? ''));
        update_option('oha_pwa_dl_screenshot_map',           esc_url_raw($_POST['oha_pwa_dl_screenshot_map']           ?? ''));
        update_option('oha_pwa_dl_screenshot_vendors',       esc_url_raw($_POST['oha_pwa_dl_screenshot_vendors']       ?? ''));
        update_option('oha_pwa_dl_screenshot_passport',      esc_url_raw($_POST['oha_pwa_dl_screenshot_passport']      ?? ''));
        update_option('oha_pwa_dl_screenshot_notifications', esc_url_raw($_POST['oha_pwa_dl_screenshot_notifications'] ?? ''));
        update_option('oha_pwa_dl_screenshot_offline',       esc_url_raw($_POST['oha_pwa_dl_screenshot_offline']       ?? ''));

        // Save App Download Page — Video
        update_option('oha_pwa_dl_video_url',   esc_url_raw(sanitize_text_field(wp_unslash($_POST['oha_pwa_dl_video_url']   ?? ''))));
        update_option('oha_pwa_dl_video_title', sanitize_text_field(wp_unslash($_POST['oha_pwa_dl_video_title'] ?? '')));

        // Save App Download Page — Version
        update_option('oha_pwa_dl_show_version', isset($_POST['oha_pwa_dl_show_version']));
    }
    
    /**
     * Get default FAQ questions
     */
    private static function get_default_faqs() {
        return "What time does registration start?|Registration opens at 8:00 AM on Friday, May 9th at the main entrance of Ohio State Reformatory.
Where is parking?|Free parking is available in the main lot. Follow signs for \"OHA Convention Parking\". Overflow parking is across the street.
What time does the convention start?|The convention runs Friday May 9th (8 AM - 6 PM) and Saturday May 10th (9 AM - 6 PM).
Where is the convention located?|Ohio State Reformatory, 100 Reformatory Rd, Mansfield, OH 44905
Can I pay at the door?|Yes! We accept cash, credit cards, and online payment. Pre-registration saves time.
Are there food options?|Food trucks will be available both days. Nearby restaurants are within 5 minutes.
Where are the restrooms?|Restrooms are located on the first floor near registration and on the second floor near the vendor hall.
Is the venue wheelchair accessible?|Yes, the venue is fully ADA accessible. Elevators are available.
What should I bring?|Bring ID for registration, notebook for classes, comfortable shoes, and phone charger.
Where is Room A?|Room A is on the second floor, turn left at the top of the stairs.
Where is Room B?|Room B is on the second floor, turn right at the top of the stairs.
Can I take photos?|Photography is allowed except during vendor demonstrations. Always ask permission first.
Is there WiFi?|Limited WiFi is available. Cell service may be spotty - the PWA works offline!
Where do I get my badge?|Pick up your badge at registration on the first floor.
Can I leave and come back?|Yes! Keep your badge visible for re-entry.
Are kids allowed?|The convention is designed for ages 13+. Parental supervision required for under 18.
What if I have an emergency?|Look for staff with red badges or use the Trouble Button in the PWA's Info tab.
Where is the vendor hall?|The vendor hall is on the second floor in the main ballroom.
When are the classes?|Classes run throughout both days. Check the Schedule tab for times and rooms.
How do I sign up for classes?|Most classes are first-come, first-served. Some require pre-registration.";
    }
}

// Initialize
OHA_PWA_Settings::init();
