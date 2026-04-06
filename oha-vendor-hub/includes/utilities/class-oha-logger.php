<?php
/**
 * Logger Utility
 *
 * Centralized logging system with multiple severity levels
 *
 * @package OHA_Board_Hub
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OHA Logger class
 */
class OHA_Logger {
    
    const LOG_ERROR = 'error';
    const LOG_WARNING = 'warning';
    const LOG_INFO = 'info';
    const LOG_DEBUG = 'debug';
    
    /**
     * Whether logging is enabled
     *
     * @var bool
     */
    private static $enabled = null;
    
    /**
     * Admin notices to display
     *
     * @var array
     */
    private static $admin_notices = [];
    
    /**
     * Initialize the logger
     */
    public static function init() {
        self::$enabled = defined('WP_DEBUG') && WP_DEBUG;
        
        // Hook for admin notices
        add_action('admin_notices', [__CLASS__, 'display_admin_notices']);
    }
    
    /**
     * Log a message
     *
     * @param string $message Message to log
     * @param string $level Log level (error, warning, info, debug)
     * @param array $context Additional context
     */
    public static function log($message, $level = self::LOG_INFO, $context = []) {
        // Skip debug logs if not in debug mode
        if (!self::$enabled && $level === self::LOG_DEBUG) {
            return;
        }
        
        $timestamp = current_time('mysql');
        $formatted_context = !empty($context) ? ' ' . wp_json_encode($context) : '';
        
        $formatted_message = sprintf(
            '[%s] [OHA Board Hub] [%s] %s%s',
            $timestamp,
            strtoupper($level),
            $message,
            $formatted_context
        );
        
        // Log to error log
        error_log($formatted_message);
        
        // For errors and warnings, also create admin notice
        if (in_array($level, [self::LOG_ERROR, self::LOG_WARNING]) && is_admin()) {
            self::add_admin_notice($message, $level);
        }
        
        // Fire action for other systems to hook into
        do_action('oha_logger_log', $message, $level, $context);
    }
    
    /**
     * Log error message
     *
     * @param string $message Error message
     * @param array $context Additional context
     */
    public static function error($message, $context = []) {
        self::log($message, self::LOG_ERROR, $context);
    }
    
    /**
     * Log warning message
     *
     * @param string $message Warning message
     * @param array $context Additional context
     */
    public static function warning($message, $context = []) {
        self::log($message, self::LOG_WARNING, $context);
    }
    
    /**
     * Log info message
     *
     * @param string $message Info message
     * @param array $context Additional context
     */
    public static function info($message, $context = []) {
        self::log($message, self::LOG_INFO, $context);
    }
    
    /**
     * Log debug message
     *
     * @param string $message Debug message
     * @param array $context Additional context
     */
    public static function debug($message, $context = []) {
        self::log($message, self::LOG_DEBUG, $context);
    }
    
    /**
     * Add admin notice
     *
     * @param string $message Notice message
     * @param string $level Notice level (error, warning, info)
     */
    private static function add_admin_notice($message, $level = 'info') {
        $notice_type = ($level === self::LOG_ERROR) ? 'error' : 'warning';
        
        self::$admin_notices[] = [
            'message' => $message,
            'type' => $notice_type,
            'timestamp' => current_time('mysql')
        ];
        
        // Store in transient for next page load
        $transient_key = 'oha_admin_notices_' . get_current_user_id();
        $existing = get_transient($transient_key);
        
        if (!is_array($existing)) {
            $existing = [];
        }
        
        $existing[] = [
            'message' => $message,
            'type' => $notice_type,
            'timestamp' => current_time('mysql')
        ];
        
        set_transient($transient_key, $existing, 300); // 5 minutes
    }
    
    /**
     * Display admin notices
     */
    public static function display_admin_notices() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $transient_key = 'oha_admin_notices_' . get_current_user_id();
        $notices = get_transient($transient_key);
        
        if (empty($notices) || !is_array($notices)) {
            return;
        }
        
        foreach ($notices as $notice) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p><strong>OHA Board Hub:</strong> %s <em>(%s)</em></p></div>',
                esc_attr($notice['type']),
                esc_html($notice['message']),
                esc_html($notice['timestamp'])
            );
        }
        
        // Clear notices after displaying
        delete_transient($transient_key);
    }
    
    /**
     * Get log entries (if custom log table is implemented)
     *
     * @param int $limit Number of entries to retrieve
     * @param string $level Filter by level
     * @return array Log entries
     */
    public static function get_entries($limit = 100, $level = null) {
        // This could be enhanced to read from a custom log table
        // For now, return empty array
        return [];
    }
    
    /**
     * Clear old log entries
     *
     * @param int $days Delete entries older than this many days
     */
    public static function clear_old_entries($days = 30) {
        // Placeholder for future implementation with custom log table
        do_action('oha_logger_clear_old_entries', $days);
    }
}
