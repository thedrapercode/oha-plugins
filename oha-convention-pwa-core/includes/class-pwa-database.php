<?php
/**
 * PWA Database Manager
 * 
 * Creates all necessary database structure, metadata fields, and indexes
 * for the OHA Convention PWA
 */

if (!defined('ABSPATH')) exit;

class OHA_PWA_Database {
    
    /**
     * Register all meta fields for convention post types
     * This should be called directly during plugin init
     */
    public static function register_meta_fields() {
        
        // Session (Seminar/Event) Meta Fields
        self::register_session_meta();
        
        // Speaker Meta Fields
        self::register_speaker_meta();
        
        // Vendor Meta Fields
        self::register_vendor_meta();
        
        // Room Meta Fields
        self::register_room_meta();
        
        // Venue/Map Meta Fields
        self::register_venue_meta();
    }
    
    /**
     * Create custom tables (if needed in future)
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for tracking PWA installs (analytics)
        $table_name = $wpdb->prefix . 'oha_pwa_installs';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            install_date datetime DEFAULT CURRENT_TIMESTAMP,
            user_agent varchar(255) DEFAULT '',
            platform varchar(50) DEFAULT '',
            browser varchar(50) DEFAULT '',
            is_standalone tinyint(1) DEFAULT 0,
            last_visit datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY install_date (install_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Table for push notification subscriptions
        $table_name = $wpdb->prefix . 'oha_pwa_push_subscriptions';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            endpoint text NOT NULL,
            auth_key varchar(255) DEFAULT '',
            p256dh_key varchar(255) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_used datetime DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        dbDelta($sql);

        // Incident reports table (v2.0)
        $incidents_table = $wpdb->prefix . 'oha_incident_reports';
        $sql = "CREATE TABLE IF NOT EXISTS $incidents_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            reported_by varchar(100) NOT NULL DEFAULT '',
            incident_time datetime NOT NULL,
            location varchar(200) NOT NULL DEFAULT '',
            incident_type varchar(100) NOT NULL DEFAULT '',
            people_involved text NOT NULL DEFAULT '',
            description text NOT NULL DEFAULT '',
            action_taken text NOT NULL DEFAULT '',
            witnesses text NOT NULL DEFAULT '',
            photo_urls text NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY incident_time (incident_time)
        ) $charset_collate;";
        dbDelta($sql);

        update_option('oha_pwa_db_version', '2.0');
    }
    
    /**
     * Add database indexes for performance
     */
    public static function add_indexes() {
        global $wpdb;
        
        // Add index for session times (for fast schedule queries)
        $wpdb->query("
            CREATE INDEX IF NOT EXISTS idx_session_start_time 
            ON {$wpdb->postmeta} (meta_key, meta_value(20))
            WHERE meta_key = 'session_start_time'
        ");
        
        $wpdb->query("
            CREATE INDEX IF NOT EXISTS idx_booth_number 
            ON {$wpdb->postmeta} (meta_key, meta_value(10))
            WHERE meta_key = 'booth_number'
        ");
        
        $wpdb->query("
            CREATE INDEX IF NOT EXISTS idx_map_anchor 
            ON {$wpdb->postmeta} (meta_key, meta_value(50))
            WHERE meta_key = 'map_anchor_id'
        ");
    }
    
    /**
     * Register session meta fields
     */
    private static function register_session_meta() {
        $fields = [
            'session_start_time' => [
                'type' => 'string',
                'description' => 'Session start date/time (Y-m-d H:i:s)',
                'show_in_rest' => true,
                'single' => true
            ],
            'session_end_time' => [
                'type' => 'string',
                'description' => 'Session end date/time (Y-m-d H:i:s)',
                'show_in_rest' => true,
                'single' => true
            ],
            'session_room' => [
                'type' => 'integer',
                'description' => 'Room post ID',
                'show_in_rest' => true,
                'single' => true
            ],
            'session_speaker' => [
                'type' => 'integer',
                'description' => 'Speaker post ID',
                'show_in_rest' => true,
                'single' => true
            ],
            'session_capacity' => [
                'type' => 'integer',
                'description' => 'Maximum attendees',
                'show_in_rest' => true,
                'single' => true,
                'default' => 0
            ],
            'session_level' => [
                'type' => 'string',
                'description' => 'Difficulty level (beginner, intermediate, advanced)',
                'show_in_rest' => true,
                'single' => true,
                'default' => 'beginner'
            ],
            'session_duration' => [
                'type' => 'integer',
                'description' => 'Duration in minutes',
                'show_in_rest' => true,
                'single' => true,
                'default' => 60
            ],
            'session_track' => [
                'type' => 'string',
                'description' => 'Track/category',
                'show_in_rest' => true,
                'single' => true
            ],
            'session_materials_url' => [
                'type' => 'string',
                'description' => 'URL to downloadable materials',
                'show_in_rest' => true,
                'single' => true
            ],
            'session_video_url' => [
                'type' => 'string',
                'description' => 'Video recording URL (post-convention)',
                'show_in_rest' => true,
                'single' => true
            ]
        ];
        
        foreach ($fields as $key => $args) {
            register_post_meta('session', $key, $args);
        }
    }
    
    /**
     * Register speaker meta fields
     */
    private static function register_speaker_meta() {
        $fields = [
            'speaker_bio' => [
                'type' => 'string',
                'description' => 'Speaker biography',
                'show_in_rest' => true,
                'single' => true
            ],
            'speaker_title' => [
                'type' => 'string',
                'description' => 'Speaker job title',
                'show_in_rest' => true,
                'single' => true
            ],
            'speaker_company' => [
                'type' => 'string',
                'description' => 'Speaker company/organization',
                'show_in_rest' => true,
                'single' => true
            ],
            'speaker_website' => [
                'type' => 'string',
                'description' => 'Speaker website URL',
                'show_in_rest' => true,
                'single' => true
            ],
            'speaker_email' => [
                'type' => 'string',
                'description' => 'Speaker contact email',
                'show_in_rest' => false, // Don't expose publicly
                'single' => true
            ],
            'speaker_phone' => [
                'type' => 'string',
                'description' => 'Speaker phone number',
                'show_in_rest' => false,
                'single' => true
            ],
            'speaker_social_twitter' => [
                'type' => 'string',
                'description' => 'Twitter/X handle',
                'show_in_rest' => true,
                'single' => true
            ],
            'speaker_social_instagram' => [
                'type' => 'string',
                'description' => 'Instagram handle',
                'show_in_rest' => true,
                'single' => true
            ],
            'speaker_social_facebook' => [
                'type' => 'string',
                'description' => 'Facebook page',
                'show_in_rest' => true,
                'single' => true
            ],
            'speaker_photo_url' => [
                'type' => 'string',
                'description' => 'Headshot photo URL',
                'show_in_rest' => true,
                'single' => true
            ]
        ];
        
        foreach ($fields as $key => $args) {
            register_post_meta('speaker', $key, $args);
        }
    }
    
    /**
     * Register vendor meta fields
     */
    private static function register_vendor_meta() {
        $fields = [
            'booth_number' => [
                'type' => 'string',
                'description' => 'Vendor booth/table number',
                'show_in_rest' => true,
                'single' => true
            ],
            'booth_size' => [
                'type' => 'string',
                'description' => 'Booth size (e.g., 10x10, 10x20)',
                'show_in_rest' => true,
                'single' => true
            ],
            'map_anchor_id' => [
                'type' => 'string',
                'description' => 'Map anchor ID for clickable map',
                'show_in_rest' => true,
                'single' => true
            ],
            'qr_slug' => [
                'type' => 'string',
                'description' => 'QR code slug for lead capture',
                'show_in_rest' => true,
                'single' => true
            ],
            'vendor_website' => [
                'type' => 'string',
                'description' => 'Vendor website URL',
                'show_in_rest' => true,
                'single' => true
            ],
            'vendor_email' => [
                'type' => 'string',
                'description' => 'Vendor contact email',
                'show_in_rest' => false,
                'single' => true
            ],
            'vendor_phone' => [
                'type' => 'string',
                'description' => 'Vendor phone number',
                'show_in_rest' => false,
                'single' => true
            ],
            'vendor_products' => [
                'type' => 'string',
                'description' => 'Products/services offered',
                'show_in_rest' => true,
                'single' => true
            ],
            'vendor_category' => [
                'type' => 'string',
                'description' => 'Vendor category',
                'show_in_rest' => true,
                'single' => true
            ],
            'vendor_social_facebook' => [
                'type' => 'string',
                'description' => 'Facebook page',
                'show_in_rest' => true,
                'single' => true
            ],
            'vendor_social_instagram' => [
                'type' => 'string',
                'description' => 'Instagram handle',
                'show_in_rest' => true,
                'single' => true
            ],
            'has_electricity' => [
                'type' => 'boolean',
                'description' => 'Requires electrical outlet',
                'show_in_rest' => true,
                'single' => true,
                'default' => false
            ],
            'special_requirements' => [
                'type' => 'string',
                'description' => 'Special setup requirements',
                'show_in_rest' => false,
                'single' => true
            ]
        ];
        
        foreach ($fields as $key => $args) {
            register_post_meta('vendor', $key, $args);
        }
    }
    
    /**
     * Register room meta fields
     */
    private static function register_room_meta() {
        $fields = [
            'room_capacity' => [
                'type' => 'integer',
                'description' => 'Room seating capacity',
                'show_in_rest' => true,
                'single' => true,
                'default' => 0
            ],
            'room_floor' => [
                'type' => 'integer',
                'description' => 'Floor number',
                'show_in_rest' => true,
                'single' => true,
                'default' => 1
            ],
            'room_map_anchor_id' => [
                'type' => 'string',
                'description' => 'Map anchor ID',
                'show_in_rest' => true,
                'single' => true
            ],
            'room_features' => [
                'type' => 'string',
                'description' => 'Room features/amenities',
                'show_in_rest' => true,
                'single' => true
            ],
            'room_setup' => [
                'type' => 'string',
                'description' => 'Seating setup type',
                'show_in_rest' => true,
                'single' => true
            ]
        ];
        
        foreach ($fields as $key => $args) {
            register_post_meta('room', $key, $args);
        }
    }
    
    /**
     * Register venue/map meta fields
     */
    private static function register_venue_meta() {
        // These are stored as options, not post meta
        add_option('oha_venue_map_image', '');
        add_option('oha_venue_map_anchors', '[]');
        add_option('oha_venue_address', '');
        add_option('oha_venue_parking_info', '');
        add_option('oha_venue_accessibility', '');
        add_option('oha_venue_wifi_info', '');
        add_option('oha_venue_emergency_contact', '');
    }
    
    /**
     * Get all registered meta fields for a post type
     */
    public static function get_meta_fields($post_type) {
        global $wp_meta_keys;
        
        if (isset($wp_meta_keys['post'][$post_type])) {
            return array_keys($wp_meta_keys['post'][$post_type]);
        }
        
        return [];
    }
}
