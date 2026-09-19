<?php
if (!defined('ABSPATH')) exit;

class OHA_Awards_Debug {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_oha_awards_clear_log', [__CLASS__, 'clear_log']);
        add_action('admin_post_oha_awards_toggle_debug', [__CLASS__, 'toggle_debug']);
    }
    public static function menu() {
        add_submenu_page(
            'oha-awards',
            'Awards Debug',
            'Awards Debug',
            'manage_options',
            'oha-awards-debug',
            [__CLASS__, 'render']
        );
    }
    public static function clear_log() {
        if (!current_user_can('manage_options')) wp_die('No permission');
        check_admin_referer('oha_awards_debug');
        delete_option('oha_awards_debug_log');
        wp_safe_redirect(admin_url('admin.php?page=oha-awards-debug'));
        exit;
    }
    public static function toggle_debug() {
        if (!current_user_can('manage_options')) wp_die('No permission');
        check_admin_referer('oha_awards_debug');
        $enabled = isset($_POST['enabled']) ? (int) $_POST['enabled'] : 0;
        update_option('oha_awards_debug_enabled', $enabled ? 1 : 0, false);
        wp_safe_redirect(admin_url('admin.php?page=oha-awards-debug'));
        exit;
    }
    public static function render() {
        if (!current_user_can('manage_options')) wp_die('No permission');
        $nonce = wp_create_nonce('oha_awards_debug');
        $log = get_option('oha_awards_debug_log', []);
        if (!is_array($log)) $log = [];
        $gdcr_defined = defined('GDCR_CPT') ? GDCR_CPT : '(not defined)';
        $gdcr_exists  = post_type_exists('gd_claim') ? 'yes' : 'no';
        $current = wp_get_current_user();
        echo '<div class="wrap"><h1>Awards Debug</h1>';
        echo '<p><strong>GDCR_CPT:</strong> ' . esc_html($gdcr_defined) . ' — <strong>post_type_exists("gd_claim"):</strong> ' . esc_html($gdcr_exists) . '</p>';
        echo '<p><strong>Current user:</strong> ' . esc_html($current ? $current->user_login : 'n/a') . ' (ID ' . get_current_user_id() . ')</p>';
        echo '<form method="post" action="'. esc_url(admin_url('admin-post.php')) .'" style="margin:8px 0;display:inline-block;">';
        echo '<input type="hidden" name="action" value="oha_awards_toggle_debug" />';
        echo '<input type="hidden" name="_wpnonce" value="'. esc_attr($nonce) .'" />';
        $enabled = (bool) get_option('oha_awards_debug_enabled', 1);
        echo '<label><input type="checkbox" name="enabled" value="1" '. ($enabled?'checked':'') .' /> Enable debug logging</label> ';
        echo '<button class="button">Save</button>';
        echo '</form> ';
        echo '<form method="post" action="'. esc_url(admin_url('admin-post.php')) .'" style="margin:8px 0;display:inline-block;">';
        echo '<input type="hidden" name="action" value="oha_awards_clear_log" />';
        echo '<input type="hidden" name="_wpnonce" value="'. esc_attr($nonce) .'" />';
        echo '<button class="button">Clear Log</button>';
        echo '</form>';
        echo '<h2>Recent Events</h2>';
        if (empty($log)) { echo '<p>No log entries yet.</p>'; }
        else {
            echo '<table class="widefat"><thead><tr><th>Time</th><th>Message</th><th>Context</th></tr></thead><tbody>';
            foreach ($log as $row){
                echo '<tr><td>'. esc_html($row['time']) .'</td><td>'. esc_html(is_string($row['msg']) ? $row['msg'] : json_encode($row['msg'])) .'</td><td><code>'. esc_html(json_encode($row['ctx'])) .'</code></td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }
}
OHA_Awards_Debug::init();
