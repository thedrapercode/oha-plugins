<?php
/**
 * Plugin Name: JEmail Bulk Sender
 * Plugin URI: https://ohaunters.com
 * Description: Bulk email campaigns for OHA. Send styled HTML or plain text emails to users by WP role, SWPM membership level, UsersWP user type, Vendor Hub status, Volunteer Hub status, or GeoDirectory listing ownership. Batch processing, link tracking, unsubscribe support, and campaign management. Works with FluentSMTP.
 * Version: 1.5.0
 * Author: Justin / OHA
 * License: GPL v2 or later
 * Text Domain: jemail-bulk-sender
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'JEMAIL_VERSION', '1.5.0' );
define( 'JEMAIL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JEMAIL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JEMAIL_TABLE_CAMPAIGNS', 'jemail_campaigns' );
define( 'JEMAIL_TABLE_LOGS', 'jemail_logs' );
define( 'JEMAIL_TABLE_CLICKS', 'jemail_clicks' );
define( 'JEMAIL_TABLE_UNSUBS', 'jemail_unsubscribes' );
define( 'JEMAIL_DB_VERSION', '1.2.0' );

require_once JEMAIL_PLUGIN_DIR . 'includes/class-jemail-db.php';
require_once JEMAIL_PLUGIN_DIR . 'includes/class-jemail-sender.php';
require_once JEMAIL_PLUGIN_DIR . 'includes/class-jemail-tracker.php';
require_once JEMAIL_PLUGIN_DIR . 'admin/class-jemail-admin.php';
require_once JEMAIL_PLUGIN_DIR . 'public/class-jemail-public.php';

class JEmail_Bulk_Sender {

    private static $instance = null;
    public $db;
    public $sender;
    public $tracker;
    public $admin;
    public $public_handler;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->db             = new JEmail_DB();
        $this->sender         = new JEmail_Sender( $this->db );
        $this->tracker        = new JEmail_Tracker( $this->db );
        $this->admin          = new JEmail_Admin( $this->db, $this->sender );
        $this->public_handler = new JEmail_Public( $this->db, $this->tracker );

        add_action( 'jemail_process_batch', array( $this->sender, 'process_batch' ) );

        // IMPORTANT: Register custom interval BEFORE trying to schedule with it
        add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ) );

        if ( ! wp_next_scheduled( 'jemail_process_batch' ) ) {
            wp_schedule_event( time(), 'jemail_batch_interval', 'jemail_process_batch' );
        }

        // DB upgrade check
        add_action( 'admin_init', array( $this, 'maybe_upgrade_db' ) );

        // Auto-process a batch on every admin page load if there's an active campaign.
        // This ensures emails actually send even if WP Cron isn't firing reliably.
        add_action( 'admin_init', array( $this, 'maybe_process_on_load' ) );
    }

    /**
     * Run DB upgrades when version changes.
     */
    public function maybe_upgrade_db() {
        $installed_version = get_option( 'jemail_db_version', '1.0.0' );
        if ( version_compare( $installed_version, JEMAIL_DB_VERSION, '<' ) ) {
            // Re-run create_tables — dbDelta safely adds missing columns
            $this->db->create_tables();
            update_option( 'jemail_db_version', JEMAIL_DB_VERSION );
        }
    }

    /**
     * Process a batch on admin load if there's an active sending campaign.
     * Throttled to once per 15 seconds via a transient to avoid duplicate processing.
     */
    public function maybe_process_on_load() {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;
        if ( defined( 'DOING_CRON' ) && DOING_CRON ) return;
        if ( get_transient( 'jemail_last_process' ) ) return; // throttle

        global $wpdb;
        $table = $wpdb->prefix . JEMAIL_TABLE_CAMPAIGNS;
        $has_sending = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'sending'" );
        if ( ! $has_sending ) return;

        set_transient( 'jemail_last_process', 1, 15 ); // 15 second cooldown
        $this->sender->process_batch();
    }

    public function add_cron_interval( $schedules ) {
        $delay = absint( get_option( 'jemail_batch_delay', 60 ) );
        if ( $delay < 10 ) $delay = 10;
        $schedules['jemail_batch_interval'] = array(
            'interval' => $delay,
            'display'  => sprintf( __( 'Every %d seconds (JEmail)', 'jemail-bulk-sender' ), $delay ),
        );
        return $schedules;
    }

    public static function activate() {
        $db = new JEmail_DB();
        $db->create_tables();

        $defaults = array(
            'jemail_batch_size'       => 50,
            'jemail_batch_delay'      => 60,
            'jemail_tracking_enabled' => '1',
            'jemail_from_name'        => get_bloginfo( 'name' ),
            'jemail_from_email'       => get_bloginfo( 'admin_email' ),
        );
        foreach ( $defaults as $key => $val ) {
            if ( false === get_option( $key ) ) {
                update_option( $key, $val );
            }
        }

        if ( false === get_option( 'jemail_footer_html' ) ) {
            $footer = '<div style="margin-top:30px;padding-top:15px;border-top:1px solid #ddd;font-size:12px;color:#999;text-align:center;">'
                . '<p>{{site_name}} | {{site_url}}</p>'
                . '<p><a href="{{unsubscribe_url}}" style="color:#999;">Unsubscribe</a></p>'
                . '</div>';
            update_option( 'jemail_footer_html', $footer );
        }

        flush_rewrite_rules();
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'jemail_process_batch' );
    }
}

register_activation_hook( __FILE__, array( 'JEmail_Bulk_Sender', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'JEmail_Bulk_Sender', 'deactivate' ) );

function jemail_init() {
    return JEmail_Bulk_Sender::instance();
}
add_action( 'plugins_loaded', 'jemail_init' );
