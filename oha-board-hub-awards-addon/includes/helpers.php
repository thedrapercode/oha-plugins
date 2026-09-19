<?php
if (!defined('ABSPATH')) exit;

class OHA_Awards_Helpers {
    public static function option($key, $default = '') {
        $opts = get_option('oha_awards_settings', []);
        return isset($opts[$key]) ? $opts[$key] : $default;
    }

    public static function sanitize_field_map($arr) {
        $out = [];
        $keys = ['haunt_id','actor_name','actor_email','actor_photo'];
        foreach ($keys as $k) {
            $out[$k] = isset($arr[$k]) ? sanitize_text_field($arr[$k]) : '';
        }
        return $out;
    }

    public static function attachment_url($att_id) {
        $u = wp_get_attachment_url($att_id);
        return $u ? esc_url($u) : '';
    }

    public static function set_status($post_id, $status) {
        $allowed = ['in_review','verified','completed'];
        if (!in_array($status, $allowed, true)) return false;
        return update_post_meta($post_id, '_oha_status', $status);
    }

    public static function ensure_owner($post_id, $user_id) {
        return intval(get_post_meta($post_id, '_oha_owner_user_id', true)) === intval($user_id);
    }

    public static function log_status($post_id, $status, $note = '') {
        $log = get_post_meta($post_id, '_oha_status_log', true);
        if (!is_array($log)) $log = [];
        $log[] = [
            'time'   => current_time('mysql'),
            'status' => sanitize_text_field($status),
            'user'   => get_current_user_id(),
            'note'   => $note ? wp_kses_post($note) : ''
        ];
        update_post_meta($post_id, '_oha_status_log', $log);
    }

    public static function dbg($msg, $context = []) {
        $enabled = (bool) get_option('oha_awards_debug_enabled', 1);
        if (!$enabled) return;
        $line = '['. current_time('mysql') .'] ' . (is_string($msg) ? $msg : json_encode($msg));
        if (!empty($context)) { $line .= ' | ' . json_encode($context); }
        error_log('OHA_AWARDS: ' . $line);
        $log = get_option('oha_awards_debug_log', []);
        if (!is_array($log)) $log = [];
        $log[] = [ 'time' => current_time('mysql'), 'msg' => $msg, 'ctx' => $context ];
        if (count($log) > 200) { $log = array_slice($log, -200); }
        update_option('oha_awards_debug_log', $log, false);
    }
}
