<?php
/**
 * OHA Volunteer Hub - Database Setup
 * Creates and manages volunteer database tables
 */

namespace OHA\VH;

if (!defined('ABSPATH')) { exit; }

class Database {

    const DB_VERSION = '4.8.5'; // v4.8.5: alt_name added to WP admin area edit form
    const DB_VERSION_OPTION = 'oha_vh_db_version';

    /**
     * Install database tables
     */
    public static function install() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $current_version = get_option(self::DB_VERSION_OPTION, '0');

        // Only run if not installed or version changed
        if ($current_version !== self::DB_VERSION) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            // Volunteers table
            $table_volunteers = $wpdb->prefix . 'oha_volunteers';
            $sql_volunteers = "CREATE TABLE $table_volunteers (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                email varchar(100) NOT NULL,
                first_name varchar(50) DEFAULT NULL,
                last_name varchar(50) DEFAULT NULL,
                phone varchar(20) DEFAULT NULL,
                birthdate date DEFAULT NULL,
                preferred_areas longtext DEFAULT NULL COMMENT 'JSON array of area IDs',
                availability longtext DEFAULT NULL,
                experience_level varchar(20) DEFAULT 'newbie',
                special_skills longtext DEFAULT NULL,
                how_did_you_hear varchar(255) DEFAULT NULL,
                tshirt_size varchar(10) DEFAULT NULL,
                emergency_contact varchar(255) DEFAULT NULL,
                emergency_contact_attending varchar(10) DEFAULT NULL,
                user_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Links to WP user if created',
                signup_date datetime NOT NULL,
                status varchar(20) DEFAULT 'pending',
                notes longtext DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY idx_email (email),
                KEY idx_user_id (user_id),
                KEY idx_status (status)
            ) $charset_collate;";

            // Areas table
            $table_areas = $wpdb->prefix . 'oha_volunteer_areas';
            $sql_areas = "CREATE TABLE $table_areas (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                area_name varchar(100) NOT NULL,
                alt_name varchar(100) DEFAULT NULL COMMENT 'Board-only display name shown instead of themed area_name in assign tab',
                area_slug varchar(100) NOT NULL,
                description longtext DEFAULT NULL,
                icon varchar(50) DEFAULT NULL,
                color varchar(7) DEFAULT '#e74c3c',
                min_volunteers int(11) DEFAULT 1,
                max_volunteers int(11) DEFAULT 999,
                display_order int(11) DEFAULT 0,
                is_active tinyint(1) DEFAULT 1,
                area_managers longtext DEFAULT NULL COMMENT 'JSON array of board member user IDs',
                PRIMARY KEY  (id),
                UNIQUE KEY unique_slug (area_slug),
                KEY idx_active (is_active),
                KEY idx_order (display_order)
            ) $charset_collate;";

            // Shifts table
            $table_shifts = $wpdb->prefix . 'oha_volunteer_shifts';
            $sql_shifts = "CREATE TABLE $table_shifts (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                area_id bigint(20) UNSIGNED NOT NULL,
                shift_type varchar(20) DEFAULT 'all_day' COMMENT 'all_day or time_slot',
                shift_date date NOT NULL,
                shift_start time DEFAULT NULL,
                shift_end time DEFAULT NULL,
                slots_available int(11) DEFAULT 1,
                slots_filled int(11) DEFAULT 0,
                shift_notes longtext DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY idx_area_id (area_id),
                KEY idx_shift_date (shift_date),
                KEY idx_shift_type (shift_type)
            ) $charset_collate;";

            // Assignments table
            $table_assignments = $wpdb->prefix . 'oha_volunteer_assignments';
            $sql_assignments = "CREATE TABLE $table_assignments (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                volunteer_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'From volunteers table if registered',
                manual_name varchar(200) DEFAULT NULL COMMENT 'For non-registered volunteers',
                manual_email varchar(100) DEFAULT NULL COMMENT 'For non-registered volunteers',
                manual_phone varchar(20) DEFAULT NULL COMMENT 'For non-registered volunteers',
                area_id bigint(20) UNSIGNED NOT NULL,
                shift_id bigint(20) UNSIGNED DEFAULT NULL,
                assignment_date datetime NOT NULL,
                assigned_by bigint(20) UNSIGNED DEFAULT NULL COMMENT 'User ID who made assignment',
                checked_in tinyint(1) DEFAULT 0,
                checked_in_time datetime DEFAULT NULL,
                hours_worked decimal(4,2) DEFAULT 0.00,
                notes longtext DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY idx_volunteer_id (volunteer_id),
                KEY idx_area_id (area_id),
                KEY idx_shift_id (shift_id),
                KEY idx_manual_email (manual_email)
            ) $charset_collate;";

            // Run table creation
            dbDelta($sql_volunteers);
            dbDelta($sql_areas);
            dbDelta($sql_shifts);
            dbDelta($sql_assignments);

            // Messages table for direct communication
            $table_messages = $wpdb->prefix . 'oha_volunteer_messages';
            $sql_messages = "CREATE TABLE $table_messages (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                from_user_id bigint(20) UNSIGNED NOT NULL COMMENT 'Area manager sending message',
                to_volunteer_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Registered volunteer',
                to_email varchar(100) DEFAULT NULL COMMENT 'Email for manual volunteers',
                area_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Related area',
                subject varchar(200) NOT NULL,
                message longtext NOT NULL,
                sent_date datetime NOT NULL,
                is_read tinyint(1) DEFAULT 0,
                PRIMARY KEY  (id),
                KEY idx_from_user (from_user_id),
                KEY idx_to_volunteer (to_volunteer_id),
                KEY idx_area_id (area_id),
                KEY idx_sent_date (sent_date)
            ) $charset_collate;";
            dbDelta($sql_messages);

            // PWA Notification Preferences table (groundwork for future PWA)
            $table_notifications = $wpdb->prefix . 'oha_notification_prefs';
            $sql_notifications = "CREATE TABLE $table_notifications (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                volunteer_id bigint(20) UNSIGNED NOT NULL,
                push_enabled tinyint(1) DEFAULT 0,
                email_notifications tinyint(1) DEFAULT 1,
                sms_notifications tinyint(1) DEFAULT 0,
                subscription_data longtext DEFAULT NULL COMMENT 'JSON data for push subscription',
                created_date datetime NOT NULL,
                updated_date datetime DEFAULT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY idx_volunteer (volunteer_id),
                KEY idx_push_enabled (push_enabled)
            ) $charset_collate;";
            dbDelta($sql_notifications);

            // Insert default areas if none exist
            if ($wpdb->get_var("SELECT COUNT(*) FROM $table_areas") == 0) {
                self::insert_default_areas();
            }

            // Check if area_managers column exists, add if missing
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_areas} LIKE 'area_managers'");
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE {$table_areas} ADD COLUMN area_managers LONGTEXT DEFAULT NULL COMMENT 'JSON array of board member user IDs'");
            }

            // Add new volunteer fields if they don't exist (v1.3)
            $birthdate_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_volunteers} LIKE 'birthdate'");
            if (empty($birthdate_exists)) {
                $wpdb->query("ALTER TABLE {$table_volunteers} ADD COLUMN birthdate DATE DEFAULT NULL AFTER phone");
            }
            
            $how_did_you_hear_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_volunteers} LIKE 'how_did_you_hear'");
            if (empty($how_did_you_hear_exists)) {
                $wpdb->query("ALTER TABLE {$table_volunteers} ADD COLUMN how_did_you_hear VARCHAR(255) DEFAULT NULL AFTER special_skills");
            }
            
            $emergency_attending_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_volunteers} LIKE 'emergency_contact_attending'");
            if (empty($emergency_attending_exists)) {
                $wpdb->query("ALTER TABLE {$table_volunteers} ADD COLUMN emergency_contact_attending VARCHAR(10) DEFAULT NULL AFTER emergency_contact");
            }

            // Add tshirt_size column (v4.7.1)
            $tshirt_size_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_volunteers} LIKE 'tshirt_size'");
            if (empty($tshirt_size_exists)) {
                $wpdb->query("ALTER TABLE {$table_volunteers} ADD COLUMN tshirt_size VARCHAR(10) DEFAULT NULL AFTER how_did_you_hear");
            }

            // Add alt_name column to areas table (v4.8.4)
            $alt_name_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_areas} LIKE 'alt_name'");
            if (empty($alt_name_exists)) {
                $wpdb->query("ALTER TABLE {$table_areas} ADD COLUMN alt_name VARCHAR(100) DEFAULT NULL COMMENT 'Board-only display name shown in assign tab' AFTER area_name");
            }

            // Update version
            update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
        }
    }

    /**
     * Insert default convention areas
     */
    private static function insert_default_areas() {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteer_areas';

        $default_areas = [
            [
                'area_name' => 'Crowd Flow Wizards',
                'area_slug' => 'crowd-management',
                'description' => 'Keep the scares flowing smoothly and help guests navigate the convention',
                'icon' => '🌊',
                'color' => '#3498db',
                'min_volunteers' => 4,
                'max_volunteers' => 10,
                'display_order' => 1
            ],
            [
                'area_name' => 'Safety Sentinels',
                'area_slug' => 'security',
                'description' => 'Be the guardian of our haunt community and ensure everyone stays safe',
                'icon' => '🛡️',
                'color' => '#e74c3c',
                'min_volunteers' => 3,
                'max_volunteers' => 8,
                'display_order' => 2
            ],
            [
                'area_name' => 'Gateway Guardians',
                'area_slug' => 'door-watchers',
                'description' => 'First face our guests see - set the spooky mood right from the start!',
                'icon' => '🚪',
                'color' => '#9b59b6',
                'min_volunteers' => 2,
                'max_volunteers' => 6,
                'display_order' => 3
            ],
            [
                'area_name' => 'Welcome Wranglers',
                'area_slug' => 'registration',
                'description' => 'Greet and guide our haunt family at the registration desk',
                'icon' => '📋',
                'color' => '#2ecc71',
                'min_volunteers' => 3,
                'max_volunteers' => 8,
                'display_order' => 4
            ],
            [
                'area_name' => 'Build Brigade',
                'area_slug' => 'setup-crew',
                'description' => 'Transform empty spaces into haunted places (setup/teardown)',
                'icon' => '🔨',
                'color' => '#f39c12',
                'min_volunteers' => 6,
                'max_volunteers' => 15,
                'display_order' => 5
            ],
            [
                'area_name' => 'Vendor Helpers',
                'area_slug' => 'vendor-support',
                'description' => 'Assist our amazing vendors with setup, questions, and needs',
                'icon' => '🛒',
                'color' => '#1abc9c',
                'min_volunteers' => 4,
                'max_volunteers' => 10,
                'display_order' => 6
            ],
            [
                'area_name' => 'Memory Makers',
                'area_slug' => 'photo-booth',
                'description' => 'Help guests capture spooky memories at our photo stations',
                'icon' => '📸',
                'color' => '#e91e63',
                'min_volunteers' => 2,
                'max_volunteers' => 4,
                'display_order' => 7
            ],
            [
                'area_name' => 'Flex Squad',
                'area_slug' => 'floater',
                'description' => 'Jump in wherever needed - perfect for experienced volunteers!',
                'icon' => '⚡',
                'color' => '#ff9800',
                'min_volunteers' => 2,
                'max_volunteers' => 8,
                'display_order' => 8
            ]
        ];

        foreach ($default_areas as $area) {
            $wpdb->insert($table, $area);
        }
    }

    /**
     * Uninstall - remove all data (use with caution!)
     */
    public static function uninstall() {
        global $wpdb;

        if (!defined('WP_UNINSTALL_PLUGIN')) {
            return;
        }

        // Drop tables
        $tables = [
            $wpdb->prefix . 'oha_volunteer_assignments',
            $wpdb->prefix . 'oha_volunteer_shifts',
            $wpdb->prefix . 'oha_volunteer_areas',
            $wpdb->prefix . 'oha_volunteers'
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        // Remove options
        delete_option(self::DB_VERSION_OPTION);
        delete_option('oha_vh_settings');
    }

    /**
     * Get all volunteers
     */
    public static function get_volunteers($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteers';

        $defaults = [
            'status' => '',
            'search' => '',
            'orderby' => 'signup_date',
            'order' => 'DESC',
            'limit' => 100,
            'offset' => 0
        ];

        $args = wp_parse_args($args, $defaults);

        $where = [];
        if (!empty($args['status'])) {
            $where[] = $wpdb->prepare("status = %s", $args['status']);
        }

        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = $wpdb->prepare(
                "(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR CONCAT(first_name, ' ', last_name) LIKE %s)",
                $like, $like, $like, $like
            );
        }

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $allowed_orderby = ['signup_date', 'last_name', 'first_name', 'email', 'status', 'id'];
        $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'signup_date';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $order_sql = "ORDER BY $orderby $order";

        $limit_sql = sprintf("LIMIT %d OFFSET %d",
            absint($args['limit']),
            absint($args['offset'])
        );

        $query = "SELECT * FROM $table $where_sql $order_sql $limit_sql";

        return $wpdb->get_results($query);
    }

    /**
     * Get single volunteer
     */
    public static function get_volunteer($volunteer_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteers';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $volunteer_id
        ));
    }

    /**
     * Get volunteer by email
     */
    public static function get_volunteer_by_email($email) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteers';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE email = %s",
            $email
        ));
    }

    /**
     * Get volunteer by user ID
     */
    public static function get_volunteer_by_user_id($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteers';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ));
    }

    /**
     * Update volunteer
     */
    public static function update_volunteer($volunteer_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteers';

        return $wpdb->update(
            $table,
            $data,
            ['id' => $volunteer_id],
            null,
            ['%d']
        );
    }

    /**
     * Get all areas
     */
    public static function get_areas($active_only = true) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteer_areas';

        $where = $active_only ? 'WHERE is_active = 1' : '';
        
        return $wpdb->get_results(
            "SELECT * FROM $table $where ORDER BY display_order ASC, area_name ASC"
        );
    }

    /**
     * Get single area
     */
    public static function get_area($area_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteer_areas';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $area_id
        ));
    }

    /**
     * Get volunteer assignments
     */
    public static function get_volunteer_assignments($volunteer_id) {
        global $wpdb;
        $table_assignments = $wpdb->prefix . 'oha_volunteer_assignments';
        $table_areas = $wpdb->prefix . 'oha_volunteer_areas';
        $table_shifts = $wpdb->prefix . 'oha_volunteer_shifts';

        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                a.*,
                ar.area_name,
                ar.icon,
                ar.color,
                s.shift_date,
                s.shift_start,
                s.shift_end
            FROM $table_assignments a
            LEFT JOIN $table_areas ar ON a.area_id = ar.id
            LEFT JOIN $table_shifts s ON a.shift_id = s.id
            WHERE a.volunteer_id = %d
            ORDER BY s.shift_date ASC, s.shift_start ASC
        ", $volunteer_id));
    }

    /**
     * Get area statistics
     */
    public static function get_area_stats() {
        global $wpdb;
        $table_volunteers = $wpdb->prefix . 'oha_volunteers';
        $table_areas = $wpdb->prefix . 'oha_volunteer_areas';
        $table_assignments = $wpdb->prefix . 'oha_volunteer_assignments';

        return $wpdb->get_results("
            SELECT 
                ar.id,
                ar.area_name,
                ar.min_volunteers,
                ar.max_volunteers,
                COUNT(DISTINCT a.volunteer_id) as assigned_count,
                COUNT(DISTINCT v.id) as interested_count
            FROM $table_areas ar
            LEFT JOIN $table_assignments a ON ar.id = a.area_id
            LEFT JOIN $table_volunteers v ON FIND_IN_SET(ar.id, REPLACE(REPLACE(v.preferred_areas, '[', ''), ']', ''))
            WHERE ar.is_active = 1
            GROUP BY ar.id
            ORDER BY ar.display_order
        ");
    }

    /**
     * Get volunteer count by status
     */
    public static function get_volunteer_counts() {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteers';

        return $wpdb->get_results("
            SELECT status, COUNT(*) as count
            FROM $table
            GROUP BY status
        ", OBJECT_K);
    }

    /**
     * Add/update area managers
     */
    public static function update_area_managers($area_id, $manager_ids) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteer_areas';
        
        return $wpdb->update(
            $table,
            ['area_managers' => wp_json_encode($manager_ids)],
            ['id' => $area_id],
            ['%s'],
            ['%d']
        );
    }

    /**
     * Get areas managed by a user
     */
    public static function get_areas_by_manager($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteer_areas';
        
        $areas = $wpdb->get_results("SELECT * FROM $table WHERE is_active = 1");
        $managed_areas = [];
        
        foreach ($areas as $area) {
            $managers = json_decode($area->area_managers, true) ?: [];
            if (in_array($user_id, $managers)) {
                $managed_areas[] = $area;
            }
        }
        
        return $managed_areas;
    }

    /**
     * Create shift
     */
    public static function create_shift($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteer_shifts';
        
        $defaults = [
            'shift_type' => 'all_day',
            'slots_available' => 1,
            'slots_filled' => 0
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        return $wpdb->insert($table, $data);
    }

    /**
     * Get shifts for an area
     */
    public static function get_shifts_by_area($area_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteer_shifts';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE area_id = %d ORDER BY shift_date, shift_start",
            $area_id
        ));
    }

    /**
     * Create assignment
     */
    public static function create_assignment($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteer_assignments';

        $defaults = [
            'assignment_date' => current_time('mysql'),
            'assigned_by' => get_current_user_id(),
            'checked_in' => 0,
            'hours_worked' => 0.00
        ];

        $data = wp_parse_args($data, $defaults);

        $result = $wpdb->insert($table, $data);
        
        // Update shift slots if shift_id provided
        if ($result && !empty($data['shift_id'])) {
            self::increment_shift_slots($data['shift_id']);
        }
        
        // Update volunteer status to 'assigned' if volunteer_id provided
        if ($result && !empty($data['volunteer_id'])) {
            self::update_volunteer($data['volunteer_id'], ['status' => 'assigned']);
        }
        
        return $result;
    }

    /**
     * Increment shift filled slots
     */
    private static function increment_shift_slots($shift_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteer_shifts';
        
        return $wpdb->query($wpdb->prepare(
            "UPDATE $table SET slots_filled = slots_filled + 1 WHERE id = %d",
            $shift_id
        ));
    }

    /**
     * Get assignments by area
     */
    public static function get_assignments_by_area($area_id) {
        global $wpdb;
        $table_assignments = $wpdb->prefix . 'oha_volunteer_assignments';
        $table_volunteers = $wpdb->prefix . 'oha_volunteers';
        $table_shifts = $wpdb->prefix . 'oha_volunteer_shifts';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                a.*,
                v.first_name,
                v.last_name,
                v.email,
                v.phone,
                s.shift_date,
                s.shift_start,
                s.shift_end,
                s.shift_type
            FROM $table_assignments a
            LEFT JOIN $table_volunteers v ON a.volunteer_id = v.id
            LEFT JOIN $table_shifts s ON a.shift_id = s.id
            WHERE a.area_id = %d
            ORDER BY s.shift_date, s.shift_start, a.assignment_date
        ", $area_id));
    }

    /**
     * Get assignments by volunteer
     */
    public static function get_assignments_by_volunteer($volunteer_id) {
        global $wpdb;
        $table_assignments = $wpdb->prefix . 'oha_volunteer_assignments';
        $table_areas = $wpdb->prefix . 'oha_volunteer_areas';
        $table_shifts = $wpdb->prefix . 'oha_volunteer_shifts';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                a.*,
                ar.area_name,
                ar.icon,
                ar.color,
                ar.area_managers,
                s.shift_date,
                s.shift_start,
                s.shift_end,
                s.shift_type
            FROM $table_assignments a
            LEFT JOIN $table_areas ar ON a.area_id = ar.id
            LEFT JOIN $table_shifts s ON a.shift_id = s.id
            WHERE a.volunteer_id = %d
            ORDER BY s.shift_date, s.shift_start
        ", $volunteer_id));
    }

    /**
     * Send message
     */
    public static function send_message($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteer_messages';
        
        $defaults = [
            'sent_date' => current_time('mysql'),
            'is_read' => 0
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        return $wpdb->insert($table, $data);
    }

    /**
     * Get messages for volunteer
     */
    public static function get_messages_for_volunteer($volunteer_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteer_messages';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE to_volunteer_id = %d ORDER BY sent_date DESC",
            $volunteer_id
        ));
    }

    /**
     * PWA Notification Preferences - Groundwork for future PWA functionality
     */
    
    /**
     * Get notification preferences for volunteer
     */
    public static function get_notification_prefs($volunteer_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_notification_prefs';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE volunteer_id = %d",
            $volunteer_id
        ));
    }

    /**
     * Update notification preferences
     */
    public static function update_notification_prefs($volunteer_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_notification_prefs';
        
        // Check if preferences exist
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE volunteer_id = %d",
            $volunteer_id
        ));
        
        $data['updated_date'] = current_time('mysql');
        
        if ($exists) {
            // Update existing
            return $wpdb->update(
                $table,
                $data,
                ['volunteer_id' => $volunteer_id]
            );
        } else {
            // Insert new
            $data['volunteer_id'] = $volunteer_id;
            $data['created_date'] = current_time('mysql');
            return $wpdb->insert($table, $data);
        }
    }

    /**
     * Get all volunteers with push notifications enabled
     * (For future PWA push notification functionality)
     */
    public static function get_volunteers_with_push_enabled() {
        global $wpdb;
        $table_prefs = $wpdb->prefix . 'oha_notification_prefs';
        $table_volunteers = $wpdb->prefix . 'oha_volunteers';
        
        return $wpdb->get_results(
            "SELECT v.*, n.subscription_data 
             FROM $table_volunteers v
             INNER JOIN $table_prefs n ON v.id = n.volunteer_id
             WHERE n.push_enabled = 1 
             AND v.status IN ('approved', 'assigned')
             ORDER BY v.last_name, v.first_name"
        );
    }
}
