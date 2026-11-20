<?php
if (!defined('ABSPATH')) exit;

/**
 * OHA Survey Hub debug logger.
 *
 * Writes to wp-content/uploads/oha-debug/survey-hub.log (blocked from public
 * HTTP access via .htaccess). Rotates at 500 KB.
 *
 * Usage:
 *   OHA_Survey_Debug::log_deprecated('old_function_name', 'NewClass::method');
 *   OHA_Survey_Debug::log_api('submit_blocked', ['survey_type' => $type]);
 *   OHA_Survey_Debug::write('[CUSTOM] anything you want');
 */
class OHA_Survey_Debug {

    private static $log_path = null;

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Log a call to a deprecated or removed function.
     * Strips email/IP from context automatically.
     */
    public static function log_deprecated($function_name, $suggested = '') {
        $trace = self::short_backtrace(4);
        $msg   = '[DEPRECATED] ' . $function_name . '() called';
        if ($trace) $msg .= ' via ' . $trace;
        if ($suggested) $msg .= ' -- use ' . $suggested . ' instead';
        self::write($msg);

        // Also surface in Query Monitor if present
        if (function_exists('do_action')) {
            do_action('qm/warning', $msg, 'oha-survey-hub');
        }
    }

    /**
     * Log an API-layer event (rate limits, auth failures, bad input).
     * Sensitive keys (email, ip, ip_address, password) are redacted.
     */
    public static function log_api($event, $context = []) {
        $safe = self::redact($context);
        $msg  = '[API] ' . strtoupper($event);
        if ($safe) $msg .= ' ' . wp_json_encode($safe);
        self::write($msg);
    }

    /**
     * Write a raw line to the log. Timestamp is prepended automatically.
     */
    public static function write($message) {
        $path = self::log_path();
        if (!$path) return;

        // Rotate when over 500 KB
        if (file_exists($path) && filesize($path) > 512000) {
            $rotated = dirname($path) . '/survey-hub.log.1';
            if (file_exists($rotated)) unlink($rotated);
            rename($path, $rotated);
        }

        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
        file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    private static function log_path() {
        if (self::$log_path !== null) return self::$log_path;

        $upload = wp_upload_dir();
        if (!empty($upload['error'])) {
            self::$log_path = false;
            return false;
        }

        $dir = $upload['basedir'] . '/oha-debug';
        if (!is_dir($dir)) {
            if (!wp_mkdir_p($dir)) {
                self::$log_path = false;
                return false;
            }
            // Block direct HTTP access
            file_put_contents($dir . '/.htaccess', "Order deny,allow\nDeny from all\n");
            file_put_contents($dir . '/index.php', "<?php // Silence is golden\n");
        }

        self::$log_path = $dir . '/survey-hub.log';
        return self::$log_path;
    }

    private static function short_backtrace($depth = 4) {
        $bt    = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth + 2);
        $parts = [];
        foreach (array_slice($bt, 2, $depth) as $frame) {
            if (isset($frame['file'])) {
                $parts[] = basename($frame['file']) . ':' . ($frame['line'] ?? '?');
            }
        }
        return implode(' > ', $parts);
    }

    private static function redact(array $data) {
        $keys = ['email', 'ip', 'ip_address', 'password', 'token', 'key'];
        foreach ($keys as $k) {
            if (array_key_exists($k, $data)) $data[$k] = '[REDACTED]';
        }
        return $data;
    }
}
