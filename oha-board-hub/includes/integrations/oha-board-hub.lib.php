<?php
/* OHA Board Hub (embedded lib) */

if ( ! defined('ABSPATH') ) exit;

if(!class_exists('OHA_Board_Hub')){
class OHA_Board_Hub {
    private function card_tbl_names(){
        global $wpdb;
        $prefix = $wpdb->prefix.'oha_';
        return [
            'status' => $prefix.'card_status',
            'events' => $prefix.'card_events',
        ];
    }
    private function ensure_card_tables(){
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $t = $this->card_tbl_names();
        $charset_collate = $wpdb->get_charset_collate();
        $sql1 = "CREATE TABLE {$t['status']} (
            member_id BIGINT(20) UNSIGNED NOT NULL,
            sent TINYINT(1) NOT NULL DEFAULT 0,
            date DATE NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (member_id)
        ) $charset_collate;";
        $sql2 = "CREATE TABLE {$t['events']} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id BIGINT(20) UNSIGNED NOT NULL,
            ts DATETIME NOT NULL,
            action VARCHAR(32) NOT NULL,
            by_user BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            note TEXT NULL,
            PRIMARY KEY (id),
            KEY member_id (member_id),
            KEY ts (ts)
        ) $charset_collate;";
        dbDelta($sql1);
        dbDelta($sql2);
    }
    private function migrate_card_options_to_tables(){
        global $wpdb;
        $t = $this->card_tbl_names();
        // If status table already has data, skip migration.
        $has = $wpdb->get_var("SELECT COUNT(*) FROM {$t['status']}");
        if ($has && intval($has) > 0) return;
        $map = get_option(self::CARD_OPT, []);
        if (is_array($map)){
            foreach ($map as $member_id => $row){
                $member_id = intval($member_id);
                if (!$member_id) continue;
                $sent = !empty($row['sent']) ? 1 : 0;
                $date = !empty($row['date']) ? $row['date'] : null;
                $wpdb->replace($t['status'], ['member_id'=>$member_id,'sent'=>$sent,'date'=>$date], ['%d','%d','%s']);
            }
        }
        $events = get_option(self::CARD_EVT_OPT, []);
        if (is_array($events)){
            foreach ($events as $member_id => $arr){
                $member_id = intval($member_id);
                if (!$member_id || !is_array($arr)) continue;
                foreach ($arr as $ev){
                    $wpdb->insert($t['events'], [
                        'member_id'=>$member_id,
                        'ts'=> isset($ev['ts']) ? gmdate('Y-m-d H:i:s', strtotime($ev['ts'])) : gmdate('Y-m-d H:i:s'),
                        'action'=> isset($ev['action']) ? (string)$ev['action'] : 'sent',
                        'by_user'=> isset($ev['by']) ? intval($ev['by']) : 0,
                        'note'=> isset($ev['note']) ? (string)$ev['note'] : ''
                    ], ['%d','%s','%s','%d','%s']);
                }
            }
        }
    }

    const CARD_OPT = 'oha_bh_card_sent_map';
    const CARD_EVT_OPT = 'oha_bh_card_events';

    private function get_card_store(){
        $m = get_option(self::CARD_OPT, []);
        if (!is_array($m)) $m = [];
        return $m;
    }
    private function save_card_store($m){
        if (!is_array($m)) $m = [];
        update_option(self::CARD_OPT, $m, false);
    }
    
    private function get_card_events(){
        global $wpdb;
        $t = $this->card_tbl_names();
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t['events'])) === $t['events']){
            // Return full map keyed by member_id
            $rows = $wpdb->get_results("SELECT member_id, ts, action, by_user, note FROM {$t['events']} ORDER BY ts ASC", ARRAY_A);
            $out = [];
            foreach ($rows as $r){
                $k = (string)intval($r['member_id']);
                if (!isset($out[$k])) $out[$k] = [];
                $out[$k][] = ['ts'=>gmdate('c', strtotime($r['ts'])), 'action'=>$r['action'], 'by'=>intval($r['by_user']), 'note'=>$r['note']];
            }
            return $out;
        }
        // fallback to option
        $e = get_option(self::CARD_EVT_OPT, []);
        if (!is_array($e)) $e = [];
        return $e;
    }
    private function save_card_events($e){
        if (!is_array($e)) $e = [];
        update_option(self::CARD_EVT_OPT, $e, false);
    }
    private function log_card_event($member_id, $action, $note=''){
        global $wpdb;
        $member_id = (string)intval($member_id);
        $user_id = get_current_user_id();
        $t = $this->card_tbl_names();
        $ts = gmdate('Y-m-d H:i:s');
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t['events'])) === $t['events']){
            $wpdb->insert($t['events'], [
                'member_id'=>intval($member_id),
                'ts'=>$ts,
                'action'=>(string)$action,
                'by_user'=> $user_id ? intval($user_id) : 0,
                'note'=>(string)$note
            ], ['%d','%s','%s','%d','%s']);
            return;
        }
        // fallback option log
        $e = $this->get_card_events();
        if (!isset($e[$member_id]) || !is_array($e[$member_id])) $e[$member_id] = [];
        $e[$member_id][] = ['ts'=>gmdate('c'), 'action'=>$action, 'by'=>$user_id ? intval($user_id) : 0, 'note'=>(string)$note];
        $this->save_card_events($e);
    }
private function get_card_status($member_id, $wp_user_id = 0){
        global $wpdb;
        $member_id  = intval($member_id);
        $wp_user_id = intval($wp_user_id);
        if (!$member_id && !$wp_user_id) return ['sent'=>0,'date'=>''];
        // Collect distinct IDs to check (SWPM id first, then WP user id)
        $ids = array_unique(array_filter([$member_id, $wp_user_id]));
        $t = $this->card_tbl_names();
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t['status'])) === $t['status']){
            foreach ($ids as $id) {
                $row = $wpdb->get_row($wpdb->prepare("SELECT sent,date FROM {$t['status']} WHERE member_id=%d LIMIT 1", $id), ARRAY_A);
                if ($row) {
                    return ['sent'=> intval($row['sent']) ? 1 : 0, 'date'=> $row['date'] ?: ''];
                }
            }
        }
        // Fallback to options
        $m = $this->get_card_store();
        foreach ($ids as $id) {
            $key = (string)$id;
            if (isset($m[$key]) && !empty($m[$key]['sent'])) {
                return ['sent'=>1, 'date'=>($m[$key]['date'] ?? '')];
            }
        }
        // Final fallback: oha_card_sent usermeta — try both ids
        foreach ($ids as $id) {
            $meta_sent = get_user_meta($id, 'oha_card_sent', true);
            if (!empty($meta_sent) && $meta_sent !== '0') {
                $meta_date = get_user_meta($id, 'oha_card_sent_date', true);
                return ['sent'=>1, 'date'=>($meta_date ?: '')];
            }
        }
        return ['sent'=>0, 'date'=>''];
    }
    private function set_card_status($member_id, $sent){
        global $wpdb;
        $member_id = (string)intval($member_id);
        $prev = $this->get_card_status($member_id);
        $t = $this->card_tbl_names();
        $d = $sent ? gmdate('Y-m-d') : null;

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t['status'])) === $t['status']){
            $wpdb->replace($t['status'], [
                'member_id'=>intval($member_id),
                'sent'=> $sent ? 1 : 0,
                'date'=> $d
            ], ['%d','%d','%s']);
        } else {
            $m = $this->get_card_store();
            $m[$member_id] = ['sent'=> $sent ? 1 : 0, 'date'=> $d ? $d : ''];
            $this->save_card_store($m);
        }

        if ($sent) {
            if ($prev['sent']) { $this->log_card_event($member_id, 'replaced', ''); }
            else { $this->log_card_event($member_id, 'sent', ''); }
        } else {
            if ($prev['sent']) { $this->log_card_event($member_id, 'cleared', ''); }
        }

        // Also write a JSON backup to uploads for redundancy
        if (method_exists($this,'write_card_backup_file')) { $this->write_card_backup_file(); }
    }
const CAP = 'read_oha_board_hub';
    private function resolve_level_name($lvl){
        $lvl = (string)($lvl ?? ''); if ($lvl==='') return '—';
        $map = $this->get_level_map();
        $names = [
            (string)$map['listing_owners']  => 'Basic',
            (string)$map['board']           => 'Board',
            (string)$map['vendors']         => 'Vendors',
            (string)$map['sponsors']        => 'Sponsors',
            (string)$map['convention2026']  => 'Convention 2026',
            (string)$map['oha_member']      => 'OHA Member',
        ];
        return isset($names[(string)$lvl]) ? $names[(string)$lvl] : $lvl;
    }

    const OPT = 'oha_board_hub_settings';
    const CUSTOM_TABS_KEY = 'custom_tabs';

    // Migration function to convert old custom_tabs to new tab_management format
    private function migrate_custom_tabs_to_tab_management() {
        $settings = get_option(self::OPT, []);
        
        // Check if migration has already been done
        if (isset($settings['tab_management']) || !isset($settings[self::CUSTOM_TABS_KEY])) {
            return; // Already migrated or no old tabs to migrate
        }
        
        $old_tabs = $settings[self::CUSTOM_TABS_KEY];
        if (!is_array($old_tabs) || empty($old_tabs)) {
            return; // Nothing to migrate
        }
        
        // Initialize tab_management if it doesn't exist
        if (!isset($settings['tab_management'])) {
            $settings['tab_management'] = [];
        }
        
        // Migrate old custom tabs to new format
        foreach ($old_tabs as $old_tab) {
            if (!is_array($old_tab)) continue;
            
            $title = isset($old_tab['title']) ? sanitize_text_field($old_tab['title']) : '';
            $slug = isset($old_tab['slug']) ? sanitize_key($old_tab['slug']) : '';
            $shortcode = isset($old_tab['shortcode']) ? (string)$old_tab['shortcode'] : '';
            $enabled = isset($old_tab['enabled']) ? (bool)$old_tab['enabled'] : true;
            $order = isset($old_tab['order']) ? intval($old_tab['order']) : 100;
            
            if (!$title || !$slug || !$shortcode) continue;
            
            // Generate unique slug if needed
            $unique_slug = $slug;
            $counter = 1;
            while (isset($settings['tab_management'][$unique_slug])) {
                $unique_slug = $slug . '_' . $counter;
                $counter++;
            }
            
            $settings['tab_management'][$unique_slug] = [
                'title' => $title,
                'slug' => $unique_slug,
                'type' => 'custom',
                'shortcode' => $shortcode,
                'enabled' => $enabled,
                'order' => $order + 100, // Add 100 to put custom tabs after built-ins by default
            ];
        }
        
        // Save migrated settings
        update_option(self::OPT, $settings);
        
        // Optionally remove old custom_tabs data (commented out to preserve data)
        // unset($settings[self::CUSTOM_TABS_KEY]);
        // update_option(self::OPT, $settings);
    }

    private function get_custom_tabs(){
        $s = get_option(self::OPT, []);
        $tab_management = isset($s['tab_management']) && is_array($s['tab_management']) ? $s['tab_management'] : [];
        
        $out = [];
        foreach($tab_management as $slug => $tab_data){
            if(!is_array($tab_data)) continue;
            
            // Only process custom tabs that are enabled
            if (!isset($tab_data['type']) || $tab_data['type'] !== 'custom') continue;
            
            $title = isset($tab_data['title']) ? sanitize_text_field($tab_data['title']) : '';
            $shortcode = isset($tab_data['shortcode']) ? (string)$tab_data['shortcode'] : '';
            $enabled = isset($tab_data['enabled']) ? filter_var($tab_data['enabled'], FILTER_VALIDATE_BOOLEAN) : true;
            $order = isset($tab_data['order']) ? intval($tab_data['order']) : 100;
            
            $out[] = [
                'title' => $title,
                'slug' => $slug,
                'shortcode' => $shortcode,
                'order' => $order,
            ];
        }
        
        usort($out, function($a,$b){ return ($a['order'] <=> $b['order']) ?: strcmp($a['title'],$b['title']); });
        return $out;
    }
    
    private function get_all_tabs_ordered(){
        $s = get_option(self::OPT, []);
        $tab_management = isset($s['tab_management']) && is_array($s['tab_management']) ? $s['tab_management'] : [];
        
        // Define built-in tabs with default configuration
        $builtin_tabs = [
            'overview' => ['title' => 'Overview', 'order' => 10, 'enabled' => true],
            'members'  => ['title' => 'Members', 'order' => 20, 'enabled' => true],
            'gd'       => ['title' => 'Listings & Events', 'order' => 30, 'enabled' => true],
            'con'      => ['title' => 'Convention 2026', 'order' => 40, 'enabled' => true],
            'sponsors' => ['title' => 'Sponsors', 'order' => 45, 'enabled' => true],
            'finance'  => ['title' => 'Finance', 'order' => 50, 'enabled' => true],
            'activity' => ['title' => 'Site Activity', 'order' => 60, 'enabled' => true],
            'help'     => ['title' => 'Training', 'order' => 70, 'enabled' => true],
            'changes'  => ['title' => 'Change Log', 'order' => 80, 'enabled' => true],
            'discounts' => ['title' => 'Haunt Discounts', 'order' => 90, 'enabled' => true],
        ];
        
        $all_tabs = [];
        
        $explicitly_disabled = []; // Track intentionally disabled tabs to prevent fallback loop re-adding them

        // Process all tabs from tab_management (includes both built-in overrides and custom tabs)
        foreach($tab_management as $slug => $tab_data){
            if(!is_array($tab_data)) continue;

            // Fix: (bool)'0' === true in PHP, so use filter_var for reliable string→boolean conversion
            $enabled = isset($tab_data['enabled']) ? filter_var($tab_data['enabled'], FILTER_VALIDATE_BOOLEAN) : true;
            if (!$enabled) {
                $explicitly_disabled[] = $slug; // Remember — don't let fallback loop re-add it
                continue;
            }
            
            if (isset($builtin_tabs[$slug])) {
                // Built-in tab with possible override settings
                $all_tabs[$slug] = [
                    'title' => $builtin_tabs[$slug]['title'], // Always use original title for built-ins
                    'order' => isset($tab_data['order']) ? intval($tab_data['order']) : $builtin_tabs[$slug]['order'],
                    'type' => 'builtin'
                ];
            } elseif (isset($tab_data['type']) && $tab_data['type'] === 'custom') {
                // Custom tab
                $title = isset($tab_data['title']) ? sanitize_text_field($tab_data['title']) : '';
                $shortcode = isset($tab_data['shortcode']) ? (string)$tab_data['shortcode'] : '';
                
                if($title && $shortcode) {
                    $all_tabs[$slug] = [
                        'title' => $title,
                        'order' => isset($tab_data['order']) ? intval($tab_data['order']) : 100,
                        'shortcode' => $shortcode,
                        'type' => 'custom'
                    ];
                }
            }
        }
        
        // Add any built-in tabs that weren't configured in tab_management at all.
        // IMPORTANT: Skip tabs in $explicitly_disabled — they were intentionally unchecked.
        // Without this check, disabled tabs re-appear because they're "missing" from $all_tabs.
        foreach($builtin_tabs as $slug => $default_data) {
            if (!isset($all_tabs[$slug]) && !in_array($slug, $explicitly_disabled, true)) {
                $all_tabs[$slug] = [
                    'title' => $default_data['title'],
                    'order' => $default_data['order'],
                    'type' => 'builtin'
                ];
            }
        }
        
        // Sort by order
        uasort($all_tabs, function($a,$b){ return ($a['order'] <=> $b['order']) ?: strcmp($a['title'],$b['title']); });
        
        return $all_tabs;
    }


    public function __construct() {
        // Ensure card tables exist & migrate old options on admin and front hooks
        add_action('admin_init', function(){ 
            $this->ensure_card_tables(); 
            $this->migrate_card_options_to_tables();
            $this->migrate_custom_tabs_to_tab_management(); // Migrate old custom tabs format
        });
        add_action('init', function(){ $this->ensure_card_tables(); });

        register_activation_hook(__FILE__, [$this,'on_activate']);
        add_shortcode('oha_board_hub', [$this,'shortcode']);
        // stripped: admin menu moved to Suite
        add_action('admin_init', [$this,'register_settings']);
        add_action('wp_enqueue_scripts', [$this,'assets']);
        add_action('admin_post_oha_export_members_csv', [$this,'export_members_csv']);

        add_action('init', function(){
            if ($this->is_convention_enabled()) {
                $this->register_convention_cpts();
            }
            // Card-sent toggle must be available regardless of convention mode
            add_action('wp_ajax_oha_toggle_card_sent', [$this,'action_oha_toggle_card_sent']);
            add_action('wp_ajax_oha_set_member_id',    [$this,'action_oha_set_member_id']);
        });
    }

    public function on_activate() {
        $this->ensure_card_tables();
        $this->migrate_card_options_to_tables();

        add_role('oha_board', 'OHA Board', ['read' => true, self::CAP => true]);
        if ($role = get_role('oha_board')) {
            if (!$role->has_cap(self::CAP)) $role->add_cap(self::CAP);
        }
        $defaults = [
            'level_listing_owners'  => '2',
            'level_board'           => '4',
            'level_vendors'         => '6',
            'level_sponsors'        => '8',
            'level_convention2026'  => '6',
            'level_webmaster'       => '5',
            'notify_email'          => get_option('admin_email'),
            'videos_members'        => '',
            'videos_listings'       => '',
            'videos_convention'     => '',
            'videos_events'         => '',
            'new_window_days'       => '30',
            'enable_convention_tools' => '0',
        ];
        $current = get_option(self::OPT, []);
        update_option(self::OPT, array_merge($defaults, $current));
    }

    public function assets() {
        wp_register_style('oha-board-hub', plugins_url('style.css', __FILE__));
        wp_enqueue_style('oha-board-hub');
    }

    private function get_setting($key, $default = '') {
        $s = get_option(self::OPT, []);
        return isset($s[$key]) ? $s[$key] : $default;
    }

    private function is_convention_enabled() {
        return $this->get_setting('enable_convention_tools','0') === '1';
    }

    private function user_can_view() {
        if (!is_user_logged_in()) return false;
        
        $user = wp_get_current_user();
        
        // Check WordPress capability (from oha_board role)
        if (current_user_can(self::CAP)) return true;
        
        // Check for administrator (full access)
        if (current_user_can('manage_options')) return true;
        
        // Check for Users WP board-member role
        if (in_array('board-member', $user->roles)) return true;
        
        // Check for WordPress OHA Board role (backup)
        if (in_array('oha_board', $user->roles)) return true;
        
        // Legacy: Still allow Simple Membership access for existing users (but don't require it)
        // This provides backward compatibility during transition
        $webmaster_level = intval($this->get_setting('level_webmaster', 5));
        if ($webmaster_level && $this->current_user_sm_level_equals($webmaster_level)) return true;
        
        $board_level = intval($this->get_setting('level_board', 4));
        if ($board_level && $this->current_user_sm_level_equals($board_level)) return true;
        
        return false;
    }

    

    public function action_oha_toggle_card_sent(){
if (function_exists('nocache_headers')) nocache_headers();
while (function_exists('ob_get_level') && ob_get_level() > 0) { @ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');

// Convert PHP notices/warnings into exceptions so we can return JSON
$__oha_prev_err = set_error_handler(function($severity, $message, $file, $line){
    // Respect @-silenced errors
    if (!(error_reporting() & $severity)) { return false; }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    if (!is_user_logged_in() || !$this->user_can_view()) {
        wp_send_json_error(['message' => 'Unauthorized'], 200);
    }

    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    $sent      = isset($_POST['sent']) ? intval($_POST['sent']) : 0;
    $nonce     = isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '';

    if ($member_id <= 0) {
        wp_send_json_error(['message' => 'Invalid member id'], 200);
    }
    if (!wp_verify_nonce($nonce, 'oha_card_sent_'.$member_id)) {
        wp_send_json_error(['message' => 'Bad nonce'], 200);
    }

    $this->set_card_status($member_id, $sent ? 1 : 0);

    // Sync oha_card_sent usermeta so admin panel and frontend stay in agreement.
    // wp_user_id is passed from the table button (data-wp-user-id); falls back to member_id
    // for uwp members where member_id === wp_user_id.
    $wp_user_id = isset($_POST['wp_user_id']) ? intval($_POST['wp_user_id']) : intval($member_id);
    if ($wp_user_id > 0 && get_user_by('ID', $wp_user_id)) {
        update_user_meta($wp_user_id, 'oha_card_sent', $sent ? '1' : '0');
        if ($sent) {
            update_user_meta($wp_user_id, 'oha_card_sent_date', gmdate('Y-m-d'));
        } else {
            delete_user_meta($wp_user_id, 'oha_card_sent_date');
        }
    }

    $st = $this->get_card_status($member_id);
    wp_send_json_success(['member_id' => $member_id, 'sent' => $st['sent'], 'date' => $st['date']], 200);

} catch (Throwable $e) {
    if (function_exists('error_log')) {
        error_log('[OHA Board Hub] Card toggle error: '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
    }
    $payload = ['message' => 'Server error'];
    if (current_user_can('manage_options')) {
        $payload['debug'] = $e->getMessage();
        $payload['file']  = $e->getFile();
        $payload['line']  = $e->getLine();
    }
    wp_send_json_error($payload, 200);
} finally {
    if ($__oha_prev_err !== null) { set_error_handler($__oha_prev_err); } else { restore_error_handler(); }
}
}

    public function action_oha_set_member_id() {
        if (!is_user_logged_in() || !$this->user_can_view()) {
            wp_send_json_error(['message' => 'Unauthorized'], 200);
        }
        $wp_user_id = isset($_POST['wp_user_id']) ? intval($_POST['wp_user_id']) : 0;
        $new_id     = isset($_POST['new_member_id']) ? sanitize_text_field(trim($_POST['new_member_id'])) : '';
        $nonce      = isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '';

        if ($wp_user_id <= 0 || $new_id === '') {
            wp_send_json_error(['message' => 'Invalid data'], 200);
        }
        if (!wp_verify_nonce($nonce, 'oha_set_member_id_' . $wp_user_id)) {
            wp_send_json_error(['message' => 'Bad nonce'], 200);
        }
        if (!get_user_by('ID', $wp_user_id)) {
            wp_send_json_error(['message' => 'User not found'], 200);
        }
        update_user_meta($wp_user_id, 'oha_member_id', $new_id);
        wp_send_json_success(['wp_user_id' => $wp_user_id, 'oha_member_id' => $new_id], 200);
    }

private function current_user_sm_level_equals($target_level) {
        if (!is_user_logged_in()) return false;
        $user = wp_get_current_user();
        if (!$user || !$user->ID) return false;
        global $wpdb;
        $members_tbl  = $wpdb->prefix . 'swpm_members_tbl';
        $level = $wpdb->get_var($wpdb->prepare("SELECT membership_level FROM $members_tbl WHERE wp_user_id = %d LIMIT 1", $user->ID));
        if ($level === null && !empty($user->user_email)) {
            $level = $wpdb->get_var($wpdb->prepare("SELECT membership_level FROM $members_tbl WHERE email = %s LIMIT 1", $user->user_email));
        }
        if ($level === null) return false;
        return intval($level) === intval($target_level);
    }

    public function shortcode($atts) {
        if (!$this->user_can_view()) {
            return '<div class="oha-board-denied">Board access only. Please log in with authorized credentials.</div>';
        }
        $s = get_option(self::OPT, []);
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
        $lvl_key = isset($_GET['lvl']) ? sanitize_key($_GET['lvl']) : 'oha';
        $allowed_lvl_keys = ['listing','board','vendors','sponsors','con2026','oha','all'];
        if (!in_array($lvl_key, $allowed_lvl_keys, true)) $lvl_key = 'oha';
        $range_default = ($tab === 'members') ? 'all' : '30d';
        $range = isset($_GET['range']) ? sanitize_key($_GET['range']) : $range_default;
        $allowed_ranges = ['30d','90d','ytd','all'];
        if (!in_array($range, $allowed_ranges, true)) $range = $range_default;
        
        // Get all tabs (built-in and custom) in their configured order
        $all_tabs_data = $this->get_all_tabs_ordered();
        $tabs = [];
        foreach($all_tabs_data as $slug => $data) {
            $tabs[$slug] = $data['title'];
        }
        
        if (!isset($tabs[$tab])) $tab = 'overview';
        
        // Detect a matching custom tab (render early and return)
        $__ct_match = null;
        if (isset($all_tabs_data[$tab]) && isset($all_tabs_data[$tab]['type']) && $all_tabs_data[$tab]['type'] === 'custom'){
            $__ct_match = [
                'title' => $all_tabs_data[$tab]['title'],
                'slug' => $tab,
                'shortcode' => $all_tabs_data[$tab]['shortcode']
            ];
        }


        ob_start();
        ?>
        <div class="oha-board">
            <nav class="oha-tabs" aria-label="Board Hub Tabs">
                <?php foreach ($tabs as $key=>$label): ?>
                    <a class="oha-tab <?php echo $tab === $key ? 'is-active':''; ?>" href="<?php echo esc_url( add_query_arg(['tab'=>$key]) ); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>
            <?php
            
            
            switch ($tab) {
                case 'overview':
                    echo $this->render_overview_tab($range);
                    break;
                case 'members':
                    echo $this->render_members_tab($lvl_key, $range);
                    break;
                case 'gd':
                    echo $this->render_gd_tab();
                    break;
                case 'con':
                    echo $this->render_convention_tab();
                    break;
                case 'sponsors':
                    echo $this->render_sponsors_tab();
                    break;
                case 'finance':
                    echo $this->render_finance_tab();
                    break;
                case 'activity':
                    echo $this->render_activity_log_tab();
                    break;
                case 'help':
                    echo $this->render_help_tab($s);
                    break;
                case 'changes':
                    echo $this->render_changes_tab();
                    break;
                case 'discounts':
                    echo do_shortcode('[oha_haunt_discounts_admin]');
                    break;
                default:
                    // Check if this is a custom tab
                    if ($__ct_match) {
                        echo '<div class="oha-card"><h3>'.esc_html($__ct_match['title']).'</h3>';
                        echo do_shortcode($__ct_match['shortcode']);
                        echo '</div>';
                        break;
                    }
                    // Fall back to overview tab
                    echo $this->render_overview_tab($range);
                    break;
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    
            
private function render_overview_tab($range = '30d') {
        global $wpdb;
        
        $new_counts = $this->get_new_member_counts_by_level();
        $pending_listings = $this->count_pending_listings();
        $pending_claims = $this->count_pending_claims();
        
        // Phase 2A: Get comprehensive statistics
        // Vendor statistics
        $total_vendors = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'vendor_application' AND post_status <> 'trash'"
        );
        
        $paid_vendors = $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
             WHERE p.post_type = 'vendor_application'
             AND p.post_status <> 'trash'
             AND m.meta_key = '_oha_status'
             AND m.meta_value = 'Paid'"
        );
        
        $pending_vendors = $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
             WHERE p.post_type = 'vendor_application'
             AND p.post_status <> 'trash'
             AND m.meta_key = '_oha_status'
             AND (m.meta_value = 'Agreement Sent' OR m.meta_value = 'Agreement Signed')
             AND NOT EXISTS (
                 SELECT 1 FROM {$wpdb->postmeta} m2
                 WHERE m2.post_id = p.ID
                 AND m2.meta_key = '_oha_status'
                 AND m2.meta_value = 'Paid'
             )"
        );
        
        $total_booths = $wpdb->get_var(
            "SELECT SUM(CAST(m.meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} m
             INNER JOIN {$wpdb->posts} p ON m.post_id = p.ID
             WHERE p.post_type = 'vendor_application'
             AND p.post_status <> 'trash'
             AND m.meta_key = 'booths'
             AND EXISTS (
                 SELECT 1 FROM {$wpdb->postmeta} m2
                 WHERE m2.post_id = p.ID
                 AND m2.meta_key = '_oha_status'
                 AND m2.meta_value = 'Paid'
             )"
        );
        
        $recent_vendors = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'vendor_application' AND post_status <> 'trash'
             AND post_date >= %s",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));
        
        // Volunteer count - include all signups including pending, but exclude rejected/deleted
        $volunteers = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'volunteer'
             AND post_status NOT IN ('trash', 'rejected', 'deleted')"
        );
        
        // OHA Members - enhanced to include Stripe invoice payments
        $total_members = $this->count_oha_members();
        $new_members = $this->count_new_oha_members(30);
        
        // Listing owners
        $new_listing_owners = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} m ON u.ID = m.user_id
             WHERE m.meta_key = 'uwp_member_type'
             AND m.meta_value = 'listing_owner'
             AND u.user_registered >= %s",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));
        
        // GeoDirectory haunts
        $haunts = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'gd_place' 
             AND post_status = 'publish'"
        );
        
        // Recent payments
        $recent_payments = $wpdb->get_results(
            "SELECT p.ID, p.post_modified, m.meta_value as company_name
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
             INNER JOIN {$wpdb->postmeta} m2 ON p.ID = m2.post_id
             WHERE p.post_type = 'vendor_application'
             AND p.post_status <> 'trash'
             AND m.meta_key = 'company_name'
             AND m2.meta_key = '_oha_status'
             AND m2.meta_value = 'Paid'
             ORDER BY p.post_modified DESC
             LIMIT 5"
        );
        
        ob_start(); ?>
        <h2 class="oha-overview-title">Board Dashboard</h2>

        <?php
?>
        
        <!-- Phase 2A: Enhanced Statistics Dashboard -->
        <div class="oha-stats-dashboard" style="margin-bottom:30px;">
            <div class="oha-stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin:20px 0;">
                
                <?php
                // Get stat card link settings
                $settings = get_option(self::OPT, []);
                $stat_links = [
                    'vendors' => isset($settings['stat_vendors_link']) ? $settings['stat_vendors_link'] : '?tab=con',
                    'paid' => isset($settings['stat_paid_vendors_link']) ? $settings['stat_paid_vendors_link'] : '?tab=con',
                    'pending' => isset($settings['stat_pending_vendors_link']) ? $settings['stat_pending_vendors_link'] : '?tab=con',
                    'booths' => isset($settings['stat_booths_link']) ? $settings['stat_booths_link'] : '?tab=con',
                    'volunteers' => isset($settings['stat_volunteers_link']) ? $settings['stat_volunteers_link'] : admin_url('edit.php?post_type=volunteer'),
                    'members' => isset($settings['stat_members_link']) ? $settings['stat_members_link'] : '?tab=members',
                    'haunts' => isset($settings['stat_haunts_link']) ? $settings['stat_haunts_link'] : '?tab=gd',
                    'listing_owners' => isset($settings['stat_listing_owners_link']) ? $settings['stat_listing_owners_link'] : '?tab=members&lvl=listing',
                ];
                
                // Helper function to wrap card in link if URL exists
                $wrap_card = function($link, $content) {
                    if (!empty($link)) {
                        return '<a href="'.esc_url($link).'" style="text-decoration:none;color:inherit;display:block;transition:transform 0.2s;" onmouseover="this.style.transform=\'translateY(-4px)\'" onmouseout="this.style.transform=\'translateY(0)\'">'.$content.'</a>';
                    }
                    return $content;
                };
                ?>
                
                <!-- Vendor Applications -->
                <?php echo $wrap_card($stat_links['vendors'], '
                <div class="oha-stat-card" style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05);cursor:'.(!empty($stat_links['vendors']) ? 'pointer' : 'default').';">
                    <div style="font-size:36px;margin-bottom:10px;">🎪</div>
                    <div style="font-size:32px;font-weight:bold;color:#a0232a;margin:10px 0;">'.intval($total_vendors).'</div>
                    <div style="font-size:14px;color:#666;text-transform:uppercase;letter-spacing:0.5px;">Vendor Applications</div>
                    <div style="font-size:12px;color:#999;margin-top:5px;">'.intval($recent_vendors).' new (30 days)</div>
                </div>
                '); ?>
                
                <!-- Paid Vendors -->
                <?php echo $wrap_card($stat_links['paid'], '
                <div class="oha-stat-card" style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05);cursor:'.(!empty($stat_links['paid']) ? 'pointer' : 'default').';">
                    <div style="font-size:36px;margin-bottom:10px;">💰</div>
                    <div style="font-size:32px;font-weight:bold;color:#059669;margin:10px 0;">'.intval($paid_vendors).'</div>
                    <div style="font-size:14px;color:#666;text-transform:uppercase;letter-spacing:0.5px;">Paid Vendors</div>
                    <div style="font-size:12px;color:#999;margin-top:5px;">Ready for event</div>
                </div>
                '); ?>
                
                <!-- Pending Vendors -->
                <?php echo $wrap_card($stat_links['pending'], '
                <div class="oha-stat-card" style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05);cursor:'.(!empty($stat_links['pending']) ? 'pointer' : 'default').';">
                    <div style="font-size:36px;margin-bottom:10px;">⏳</div>
                    <div style="font-size:32px;font-weight:bold;color:#dc2626;margin:10px 0;">'.intval($pending_vendors).'</div>
                    <div style="font-size:14px;color:#666;text-transform:uppercase;letter-spacing:0.5px;">Pending Payment</div>
                    <div style="font-size:12px;color:#999;margin-top:5px;">Awaiting completion</div>
                </div>
                '); ?>
                
                <!-- Total Booths -->
                <?php echo $wrap_card($stat_links['booths'], '
                <div class="oha-stat-card" style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05);cursor:'.(!empty($stat_links['booths']) ? 'pointer' : 'default').';">
                    <div style="font-size:36px;margin-bottom:10px;">📦</div>
                    <div style="font-size:32px;font-weight:bold;color:#7c3aed;margin:10px 0;">'.intval($total_booths).'</div>
                    <div style="font-size:14px;color:#666;text-transform:uppercase;letter-spacing:0.5px;">Total Booths</div>
                    <div style="font-size:12px;color:#999;margin-top:5px;">Reserved & paid</div>
                </div>
                '); ?>
                
                <!-- Volunteers -->
                <?php echo $wrap_card($stat_links['volunteers'], '
                <div class="oha-stat-card" style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05);cursor:'.(!empty($stat_links['volunteers']) ? 'pointer' : 'default').';">
                    <div style="font-size:36px;margin-bottom:10px;">🙋</div>
                    <div style="font-size:32px;font-weight:bold;color:#0891b2;margin:10px 0;">'.intval($volunteers).'</div>
                    <div style="font-size:14px;color:#666;text-transform:uppercase;letter-spacing:0.5px;">Volunteers</div>
                    <div style="font-size:12px;color:#999;margin-top:5px;">Signed up</div>
                </div>
                '); ?>
                
                <!-- OHA Members -->
                <?php echo $wrap_card($stat_links['members'], '
                <div class="oha-stat-card" style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05);cursor:'.(!empty($stat_links['members']) ? 'pointer' : 'default').';">
                    <div style="font-size:36px;margin-bottom:10px;">👥</div>
                    <div style="font-size:32px;font-weight:bold;color:#a0232a;margin:10px 0;">'.intval($total_members).'</div>
                    <div style="font-size:14px;color:#666;text-transform:uppercase;letter-spacing:0.5px;">OHA Members</div>
                    <div style="font-size:12px;color:#999;margin-top:5px;">'.intval($new_members).' new (30 days)</div>
                </div>
                '); ?>
                
                <!-- Haunt Listings -->
                <?php echo $wrap_card($stat_links['haunts'], '
                <div class="oha-stat-card" style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05);cursor:'.(!empty($stat_links['haunts']) ? 'pointer' : 'default').';">
                    <div style="font-size:36px;margin-bottom:10px;">🏚️</div>
                    <div style="font-size:32px;font-weight:bold;color:#059669;margin:10px 0;">'.intval($haunts).'</div>
                    <div style="font-size:14px;color:#666;text-transform:uppercase;letter-spacing:0.5px;">Haunt Listings</div>
                    <div style="font-size:12px;color:#999;margin-top:5px;">Published</div>
                </div>
                '); ?>
                
                <!-- New Listing Owners -->
                <?php echo $wrap_card($stat_links['listing_owners'], '
                <div class="oha-stat-card" style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05);cursor:'.(!empty($stat_links['listing_owners']) ? 'pointer' : 'default').';">
                    <div style="font-size:36px;margin-bottom:10px;">📝</div>
                    <div style="font-size:32px;font-weight:bold;color:#6366f1;margin:10px 0;">'.intval($new_listing_owners).'</div>
                    <div style="font-size:14px;color:#666;text-transform:uppercase;letter-spacing:0.5px;">New Listing Owners</div>
                    <div style="font-size:12px;color:#999;margin-top:5px;">Last 30 days</div>
                </div>
                '); ?>
            </div>
            
        </div>

        <?php
        // Development Activity card — powered by OHA Dev Log Aggregator if available
        if (class_exists('OHA_DevLog')) {
            echo OHA_DevLog::render_overview_widget();
            echo '<div style="height:18px;"></div>';
        }
        ?>

        <!-- Original Quick Stats Section (Preserved) -->
        <div class="oha-grid">
            <div class="oha-card">
                <h3>Quick Links</h3>
                <ul class="oha-list">
                    <li>Total New Members (last <?php echo intval($this->get_setting('new_window_days',30)); ?> days): <strong><?php echo intval(array_sum($new_counts)); ?></strong></li>
                    <li>Pending Listings: <strong><?php echo intval($pending_listings); ?></strong> — <a href="<?php echo esc_url( admin_url('edit.php?post_type=gd_place&post_status=pending') ); ?>">review →</a></li>
                    <li>Pending Claims: <strong><?php echo intval($pending_claims); ?></strong> — <a href="<?php echo esc_url( admin_url('admin.php?page=gd-claim-reports') ); ?>">open claims →</a></li>
                    <li><a href="<?php echo esc_url( add_query_arg(['tab'=>'members']) ); ?>">Members dashboard →</a></li>
                </ul>
            </div>

            <div class="oha-card">
                <h3>Listings & Events</h3>
                <p>
                    <a class="button" href="<?php echo admin_url('edit.php?post_type=gd_place'); ?>">Haunts (Admin)</a>
                    <a class="button" href="<?php echo admin_url('edit.php?post_type=gd_event'); ?>">Haunt Events (Admin)</a>
                    <a class="button" href="<?php echo admin_url('edit.php?post_type=gd_place&post_status=pending'); ?>">Pending Places</a>
                    <a class="button" href="<?php echo admin_url('admin.php?page=gd-claim-reports'); ?>">Claim Request</a>
                </p>
            </div>

            <div class="oha-card">
                <h3>Members</h3>
                <p>
                    <a class="button" href="<?php echo esc_url( add_query_arg(['tab'=>'members']) ); ?>">Open Members Dashboard</a>
                    <a class="button" href="<?php echo admin_url('admin.php?page=simple_wp_membership'); ?>">Open Simple Membership</a>
                </p>
            </div>

            <div class="oha-card">
                <h3>Convention 2026</h3>
                <?php if ($this->is_convention_enabled()): ?>
                    <p>Convention tools are enabled. Open the tab to manage vendors, sponsors, and guests.</p>
                    <p><a class="button" href="<?php echo esc_url( add_query_arg(['tab'=>'con']) ); ?>">Open Convention Tools</a></p>
                <?php else: ?>
                    <p><strong>Disabled:</strong> Convention tools are off by default for stability. You can enable them in <em>Settings → OHA Board Hub</em>.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function count_pending_listings() {
        try {
            if (!function_exists('wp_count_posts')) { return 0; }
            $c = wp_count_posts('gd_place');
            if (is_object($c) && isset($c->pending)) return intval($c->pending);
            if (is_array($c) && isset($c['pending'])) return intval($c['pending']);
            return 0;
        } catch (\Throwable $e) { return 0; }
    }

    private function count_pending_claims() {
        try {
            global $wpdb;
            if (!$wpdb) return 0;
            // Robust detection across variant tables/columns/status values
            $candidates = [
                $wpdb->prefix . 'geodir_claim',
                $wpdb->prefix . 'geodir_claims',
                $wpdb->prefix . 'gd_claim',
                $wpdb->prefix . 'gd_claims',
            ];
            $pending_like = ['pending','awaiting','awaiting_approval','submitted','unpaid','Pending','PENDING'];
            foreach ($candidates as $table) {
                $exists = $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $table) );
                if (!$exists) continue;
                $cols = $wpdb->get_results("DESCRIBE {$table}", ARRAY_A);
                if (!is_array($cols)) continue;
                $status_col = null;
                foreach ($cols as $c) {
                    $name = isset($c['Field']) ? $c['Field'] : '';
                    if (in_array($name, ['status','claim_status','state'], true)) { $status_col = $name; break; }
                }
                if (!$status_col) $status_col = 'status';
                $placeholders = implode(',', array_fill(0, count($pending_like), '%s'));
                $sql = "SELECT COUNT(*) FROM {$table} WHERE {$status_col} IN ($placeholders)";
                $prepared = call_user_func_array([$wpdb,'prepare'], array_merge([$sql], $pending_like));
                $count = $wpdb->get_var($prepared);
                if ($count === null) {
                    $count = intval($wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$status_col} LIKE 'pending%' OR {$status_col} LIKE 'Pending%'"));
                }
                return intval($count);
            }
            return 0;
        } catch (\Throwable $e) { return 0; }
    }

    // Enhanced OHA member counting with Stripe invoice support
    private function count_oha_members() {
        global $wpdb;
        try {
            // Count UsersWP members
            $uwp_members = $wpdb->get_var(
                "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
                 INNER JOIN {$wpdb->usermeta} m ON u.ID = m.user_id
                 WHERE m.meta_key = 'uwp_member_type'
                 AND m.meta_value = 'oha_member'"
            ) ?: 0;
            
            // Count members from Stripe invoice payments (including auto-created accounts)
            $stripe_members = $wpdb->get_var(
                "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
                 INNER JOIN {$wpdb->usermeta} m ON u.ID = m.user_id
                 WHERE m.meta_key = 'oha_stripe_member_from_invoice'
                 AND m.meta_value = '1'"
            ) ?: 0;
            
            // Also check for users with successful membership Stripe payments
            $stripe_payment_members = $wpdb->get_var(
                "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
                 INNER JOIN {$wpdb->usermeta} m ON u.ID = m.user_id
                 WHERE (m.meta_key = 'stripe_customer_id' AND m.meta_value != '')
                 AND EXISTS (
                     SELECT 1 FROM {$wpdb->usermeta} m2
                     WHERE m2.user_id = u.ID
                     AND m2.meta_key = 'oha_membership_paid'
                     AND m2.meta_value = '1'
                 )"
            ) ?: 0;
            
            // Get total distinct members (avoiding double-counting)
            $total_distinct = $wpdb->get_var(
                "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
                 WHERE EXISTS (
                     SELECT 1 FROM {$wpdb->usermeta} m1
                     WHERE m1.user_id = u.ID
                     AND (
                         (m1.meta_key = 'uwp_member_type' AND m1.meta_value = 'oha_member')
                         OR (m1.meta_key = 'oha_stripe_member_from_invoice' AND m1.meta_value = '1')
                         OR (m1.meta_key = 'oha_membership_paid' AND m1.meta_value = '1')
                     )
                 )"
            ) ?: 0;
            
            return $total_distinct;
        } catch (\Throwable $e) { 
            return 0; 
        }
    }
    
    private function count_new_oha_members($days = 30) {
        global $wpdb;
        try {
            $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            
            // Get all distinct new members from multiple sources
            $new_members = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
                 WHERE u.user_registered >= %s
                 AND EXISTS (
                     SELECT 1 FROM {$wpdb->usermeta} m
                     WHERE m.user_id = u.ID
                     AND (
                         (m.meta_key = 'uwp_member_type' AND m.meta_value = 'oha_member')
                         OR (m.meta_key = 'oha_stripe_member_from_invoice' AND m.meta_value = '1')
                         OR (m.meta_key = 'oha_membership_paid' AND m.meta_value = '1')
                     )
                 )",
                $since_date
            )) ?: 0;
            
            return $new_members;
        } catch (\Throwable $e) { 
            return 0; 
        }
    }

    private function render_gd_tab() {
        ob_start(); ?>
        <div class="oha-grid">
            <div class="oha-card">
                <h3>Listings</h3>
                <p>
                    <a class="button" href="<?php echo admin_url('edit.php?post_type=gd_place'); ?>">Haunts (Admin)</a>
                    <a class="button" href="<?php echo admin_url('edit.php?post_type=gd_place&post_status=pending'); ?>">Pending Places</a>
                    <a class="button" href="<?php echo admin_url('admin.php?page=gd-claim-reports'); ?>">Claim Request</a>
                </p>
                <p><a class="button" href="<?php echo admin_url('edit.php?post_type=gd_place&orderby=date&order=desc'); ?>">Newest</a></p>
            </div>
            <div class="oha-card">
                <h3>Events</h3>
                <p>
                    <a class="button" href="<?php echo admin_url('edit.php?post_type=gd_event'); ?>">Haunt Events (Admin)</a>
                    <a class="button" href="<?php echo admin_url('post-new.php?post_type=gd_event'); ?>">Create Event</a>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Phase 2B: Finance Tracking Tab
     */
    private function render_finance_tab() {
        global $wpdb;
        
        // Get vendor revenue
        $vendor_revenue = $wpdb->get_results(
            "SELECT p.ID, p.post_date, 
                    m1.meta_value as company_name,
                    m2.meta_value as booths,
                    m3.meta_value as status
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} m1 ON p.ID = m1.post_id AND m1.meta_key = 'company_name'
             LEFT JOIN {$wpdb->postmeta} m2 ON p.ID = m2.post_id AND m2.meta_key = 'booths'
             LEFT JOIN {$wpdb->postmeta} m3 ON p.ID = m3.post_id AND m3.meta_key = '_oha_status'
             WHERE p.post_type = 'vendor_application'
             AND p.post_status <> 'trash'
             AND EXISTS (
                 SELECT 1 FROM {$wpdb->postmeta} m4
                 WHERE m4.post_id = p.ID
                 AND m4.meta_key = '_oha_status'
                 AND m4.meta_value = 'Paid'
             )
             ORDER BY p.post_date DESC"
        );
        
        // Calculate totals
        $total_booths = 0;
        $vendor_count = count($vendor_revenue);
        $booth_price = 250; // Default booth price
        
        foreach ($vendor_revenue as $vendor) {
            $total_booths += intval($vendor->booths ?: 1);
        }
        
        $estimated_vendor_revenue = $total_booths * $booth_price;
        
                // Get membership count (UsersWP member types OR WordPress subscriber role)
        $cap_key = $wpdb->prefix . 'capabilities';
        $membership_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT u.ID)
                 FROM {$wpdb->users} u
                 LEFT JOIN {$wpdb->usermeta} mt ON (u.ID = mt.user_id AND mt.meta_key = 'uwp_member_type')
                 LEFT JOIN {$wpdb->usermeta} cap ON (u.ID = cap.user_id AND cap.meta_key = %s)
                 WHERE (
                     (mt.meta_value IN ('oha_member','oha_members','level 1','level_1','Level 1','subscriber'))
                     OR (cap.meta_value LIKE %s)
                 )
                 AND (cap.meta_value NOT LIKE %s)", 
                $cap_key,
                '%\"subscriber\"%',
                '%\"administrator\"%'
            )
        );

        // Membership annual revenue estimate ($10/year per member)
        $membership_annual_revenue = $membership_count * 10;

        
        ob_start(); ?>
        <h2>Finance Dashboard</h2>
        
        <div class="oha-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
            <!-- Revenue Summary Card -->
            <div class="oha-card">
                <h3>💰 Revenue Summary</h3>
                <table class="widefat" style="margin-top:15px;">
                    <tr>
                        <td><strong>Vendor Booths</strong></td>
                        <td style="text-align:right;color:#059669;font-size:18px;font-weight:bold;">
                            $<?php echo number_format($estimated_vendor_revenue); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-left:20px;">→ <?php echo $vendor_count; ?> vendors</td>
                        <td style="text-align:right;color:#666;font-size:14px;">
                            <?php echo $total_booths; ?> booths × $<?php echo $booth_price; ?>
                        </td>
                    </tr>
<tr>
                        <td><strong>Membership (Annual)</strong></td>
                        <td style="text-align:right;color:#0891b2;font-size:18px;font-weight:bold;">
                            $<?php echo number_format($membership_annual_revenue); ?>/yr
                        </td>
                    </tr>
                    <tr style="border-top:2px solid #ddd;">
                        <td><strong style="font-size:16px;">TOTAL (Vendor + Membership Annual)</strong></td>
                        <td style="text-align:right;color:#a0232a;font-size:22px;font-weight:bold;">
                            $<?php echo number_format($estimated_vendor_revenue + $membership_annual_revenue); ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="text-align:center;padding-top:10px;color:#666;font-size:13px;">
                            <em>Membership estimate assumes $10/year per member.</em>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Quick Stats Card -->
            <div class="oha-card">
                <h3>📊 Quick Stats</h3>
                <ul class="oha-list">
                    <li><strong><?php echo $vendor_count; ?></strong> Paid Vendors</li>
                    <li><strong><?php echo $total_booths; ?></strong> Total Booths</li>
                    <li><strong>$<?php echo number_format($booth_price); ?></strong> Average per Booth</li>
                    <li><strong><?php echo intval($membership_count); ?></strong> OHA Members</li>
                    <li><strong>$30</strong> Lifetime Value per Member (signup + 1 year)</li>
                </ul>
                <p style="margin-top:15px;">
                    <a class="button" href="https://dashboard.stripe.com" target="_blank">Open Stripe Dashboard →</a>
                </p>
            </div>
            
            <!-- External Links Card -->
            <div class="oha-card">
                <h3>🔗 Financial Tools</h3>
                <p><strong>Payment Processing:</strong></p>
                <p>
                    <a class="button" href="https://dashboard.stripe.com/payments" target="_blank">Stripe Payments</a>
                    <a class="button" href="https://dashboard.stripe.com/invoices" target="_blank">Invoices</a>
                </p>
                <p><strong>WordPress Tools:</strong></p>
                <p>
                    <a class="button" href="<?php echo admin_url('admin.php?page=oha-board-hub&tab=con'); ?>">Vendor Management</a>
                    <a class="button" href="<?php echo admin_url('admin.php?page=oha-board-hub&tab=members'); ?>">Member Management</a>
                </p>
                <p style="margin-top:15px;padding:10px;background:#f0f9ff;border-left:3px solid #0891b2;">
                    <strong>💡 Note:</strong> Revenue estimates based on $<?php echo $booth_price; ?> per booth. 
                    Actual amounts in Stripe dashboard.
                </p>
            </div>
        </div>
        
        <!-- Recent Vendor Payments -->
        <div class="oha-card" style="margin-top:20px;">
            <h3>Recent Vendor Payments</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Vendor</th>
                        <th>Booths</th>
                        <th>Estimated Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($vendor_revenue): ?>
                        <?php foreach (array_slice($vendor_revenue, 0, 10) as $vendor): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($vendor->post_date)); ?></td>
                                <td><strong><?php echo esc_html($vendor->company_name); ?></strong></td>
                                <td><?php echo intval($vendor->booths ?: 1); ?></td>
                                <td style="color:#059669;font-weight:bold;">
                                    $<?php echo number_format(intval($vendor->booths ?: 1) * $booth_price); ?>
                                </td>
                                <td><span style="color:#059669;">✓ Paid</span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align:center;padding:40px;">
                                <em>No vendor payments yet</em>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Phase 2B: Site Activity Tab - shows full development activity + system activity
     */
    private function render_activity_log_tab() {
        ob_start(); ?>
        <h2>Site Activity</h2>
        
        <?php
        // Show full development activity (what was previously in Development Activity card)
        if (class_exists('OHA_DevLog')) {
            echo OHA_DevLog::render_overview_widget();
        } else {
            echo '<div class="oha-card"><p>Development activity tracking not available.</p></div>';
        }
        ?>
        
        <?php
        return ob_get_clean();
    }

    private function video_urls($key) {
        $raw = $this->get_setting($key, '');
        $parts = preg_split('/[\r\n,]+/', (string)$raw);
        $urls = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if (empty($p)) continue;
            $urls[] = esc_url($p);
        }
        return $urls;
    }

    // NEW: Safe conversion of watch/share URLs to embed URLs for YouTube + basic Vimeo
    private function oha_convert_video_url($url) {
        $u = trim((string)$url);
        if ($u === '') return $u;
        // YouTube watch
        if (preg_match('#https?://(?:www\.)?youtube\.com/watch\?([^ ]+)#i', $u)) {
            parse_str(parse_url($u, PHP_URL_QUERY), $qs);
            if (!empty($qs['v'])) {
                return 'https://www.youtube.com/embed/' . preg_replace('/[^A-Za-z0-9_\-]/','',$qs['v']);
            }
        }
        // YouTube short
        if (preg_match('#https?://youtu\.be/([A-Za-z0-9_\-]+)#i', $u, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }
        // Already embed
        if (preg_match('#https?://(?:www\.)?youtube\.com/embed/#i', $u)) {
            return $u;
        }
        // Vimeo normal
        if (preg_match('#https?://vimeo\.com/(\d+)#i', $u, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        // Vimeo embed
        if (preg_match('#https?://player\.vimeo\.com/video/\d+#i', $u)) {
            return $u;
        }
        return $u;
    }

    private function render_help_tab($s) {
        $sections = [
            'videos_members'    => 'Members (Simple Membership)',
            'videos_listings'   => 'GeoDirectory (Listings & Events)',
            'videos_convention' => 'Convention Tools',
            'videos_events'     => 'Event Creation',
        ];

        ob_start(); ?>
        <div class="oha-grid">
            <?php foreach ($sections as $opt => $title): $urls = $this->video_urls($opt); ?>
            <div class="oha-card">
                <h3><?php echo esc_html($title); ?></h3>
                <?php if (!empty($urls)): ?>
                    <?php foreach ($urls as $u): $embed = $this->oha_convert_video_url($u); ?>
                        <div class="oha-video-wrap"><iframe src="<?php echo esc_url($embed); ?>" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Add one or more training video URLs (comma or newline separated) in <em>Settings → OHA Board Hub</em>.</p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ================= Members: legacy-safe dataset + PHP filter ================= */

    private function since_for_range($range) {
        $now = time();
        switch ($range) {
            case '30d': return $now - 30*DAY_IN_SECONDS;
            case '90d': return $now - 90*DAY_IN_SECONDS;
            case 'ytd': $y = (int) gmdate('Y'); return strtotime($y . '-01-01 00:00:00');
            case 'all':
            default: return null;
        }
    }

    private function get_level_map() {
        return [
            'listing_owners'  => $this->get_setting('level_listing_owners','2'),
            'board'           => $this->get_setting('level_board','4'),
            'vendors'         => $this->get_setting('level_vendors','6'),
            'sponsors'        => $this->get_setting('level_sponsors','8'),
            'convention2026'  => $this->get_setting('level_convention2026','6'),
            'oha_member'      => $this->get_setting('level_oha_member','10'),
            'webmaster'       => $this->get_setting('level_webmaster','5'),
        ];
    }

    private function get_new_member_counts_by_level() {
        $days = intval($this->get_setting('new_window_days', 30));
        $since_ts = time() - max(1, $days) * DAY_IN_SECONDS;
        $levels = $this->get_level_map();
        $rows_all = $this->fetch_members_legacy_raw(null, '');

        $counts = ['oha_member' => 0, 'listing_owners' => 0, 'board' => 0];
        foreach ($rows_all as $r) {
            $d = isset($r['subscription_starts']) ? trim($r['subscription_starts']) : '';
            if ($d === '') continue;
            $ts = strtotime($d);
            if (!$ts || $ts < $since_ts) continue;
            $lvl = (string)($r['membership_level'] ?? '');
            if ($lvl === (string)$levels['oha_member'])     $counts['oha_member']++;
            if ($lvl === (string)$levels['listing_owners']) $counts['listing_owners']++;
            if ($lvl === (string)$levels['board'])          $counts['board']++;
        }
        return $counts;
    }

    private function fetch_members_legacy_raw($level_id = null, $q = '') {
        global $wpdb;
        $levels  = $this->get_level_map();
        $cap_key = $wpdb->prefix . 'capabilities';

        // Canonical type definitions: form_id + WP role + SWPM level ID for membership_level output
        $type_defs = [
            'oha_member'    => ['form_id' => '1', 'role' => 'subscriber',    'lvl' => $levels['oha_member']],
            'listing_owner' => ['form_id' => '4', 'role' => 'listing_owner', 'lvl' => $levels['listing_owners']],
            'board'         => ['form_id' => '2', 'role' => 'oha_board',     'lvl' => $levels['board']],
        ];

        if ($level_id !== null) {
            $wanted = [];
            foreach ($type_defs as $key => $def) {
                if ((string)$level_id === (string)$def['lvl']) $wanted[$key] = $def;
            }
            if (empty($wanted)) return [];
        } else {
            $wanted = $type_defs;
        }

        $all_rows = [];
        $seen_ids = [];

        foreach ($wanted as $def) {
            $form_id   = $def['form_id'];
            $role_like = '%"' . $def['role'] . '"%';
            $lvl_num   = $def['lvl'];

            $params     = [$form_id, $role_like];
            $search_sql = '';
            if ($q !== '') {
                $like = '%' . $wpdb->esc_like($q) . '%';
                if (ctype_digit($q)) {
                    $search_sql = ' AND (u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s OR u.ID = %d)';
                    array_push($params, $like, $like, $like, intval($q));
                } else {
                    $search_sql = ' AND (u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s)';
                    array_push($params, $like, $like, $like);
                }
            }

            $sql = "SELECT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered,
                           (SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = u.ID AND meta_key = 'first_name' LIMIT 1) AS first_name,
                           (SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = u.ID AND meta_key = 'last_name' LIMIT 1) AS last_name,
                           (SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = u.ID AND meta_key = 'oha_paid_member_since' LIMIT 1) AS oha_paid_since,
                           (SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = u.ID AND meta_key = 'oha_member_id' LIMIT 1) AS oha_member_id
                    FROM {$wpdb->users} u
                    WHERE EXISTS (
                        SELECT 1 FROM {$wpdb->usermeta} um_f
                        WHERE um_f.user_id = u.ID AND um_f.meta_key = '_uwp_register_form_id' AND um_f.meta_value = %s
                    )
                    AND EXISTS (
                        SELECT 1 FROM {$wpdb->usermeta} um_r
                        WHERE um_r.user_id = u.ID AND um_r.meta_key = '{$cap_key}' AND um_r.meta_value LIKE %s
                    ){$search_sql}
                    ORDER BY u.user_registered DESC LIMIT 5000";

            $raw = $wpdb->get_results(
                call_user_func_array([$wpdb, 'prepare'], array_merge([$sql], $params)),
                ARRAY_A
            );

            foreach ((is_array($raw) ? $raw : []) as $ur) {
                $uid = intval($ur['ID']);
                if (isset($seen_ids[$uid])) continue;
                $seen_ids[$uid] = true;

                $fname = $ur['first_name'] ?: '';
                $lname = $ur['last_name']  ?: '';
                if (!$fname && !$lname) {
                    $parts = explode(' ', $ur['display_name'], 2);
                    $fname = $parts[0] ?? '';
                    $lname = $parts[1] ?? '';
                }
                $start = !empty($ur['oha_paid_since']) ? $ur['oha_paid_since'] : $ur['user_registered'];

                $all_rows[] = [
                    'member_id'           => $uid,
                    'wp_user_id'          => $uid,
                    'oha_member_id'       => $ur['oha_member_id'] ?: '',
                    'user_name'           => $ur['user_login'],
                    'first_name'          => $fname,
                    'last_name'           => $lname,
                    'email'               => $ur['user_email'],
                    'membership_level'    => $lvl_num,
                    'account_state'       => 'active',
                    'subscription_starts' => $start,
                    'address_street'      => get_user_meta($uid, 'billing_address_line1', true)
                                          ?: get_user_meta($uid, 'address_street',  true) ?: '',
                    'address_city'        => get_user_meta($uid, 'billing_city',    true)
                                          ?: get_user_meta($uid, 'address_city',    true) ?: '',
                    'address_state'       => get_user_meta($uid, 'billing_state',   true)
                                          ?: get_user_meta($uid, 'address_state',   true) ?: '',
                    'address_zipcode'     => get_user_meta($uid, 'billing_postal_code', true)
                                          ?: get_user_meta($uid, 'address_zipcode', true) ?: '',
                    'country'             => get_user_meta($uid, 'billing_country', true)
                                          ?: get_user_meta($uid, 'country',         true) ?: '',
                    '_source'             => 'canonical',
                ];
            }
        }

        usort($all_rows, function($a, $b) {
            $ta = !empty($a['subscription_starts']) ? strtotime($a['subscription_starts']) : 0;
            $tb = !empty($b['subscription_starts']) ? strtotime($b['subscription_starts']) : 0;
            return $tb - $ta;
        });

        return $all_rows;
    }

    private function php_filter_by_range($rows, $range_key) {
        $since_ts = $this->since_for_range($range_key);
        if ($since_ts === null) return $rows; // 'all'
        $out = [];
        foreach ($rows as $r) {
            $d = isset($r['subscription_starts']) ? trim($r['subscription_starts']) : '';
            if ($d === '') { continue; } // treat blank as unknown -> exclude for ranged views
            $ts = strtotime($d);
            if ($ts && $ts >= $since_ts) $out[] = $r;
        }
        return $out;
    }

    private function render_members_tab($lvl_key, $range_key) {
        $levels = $this->get_level_map();
        $active_level_id = null;
        switch ($lvl_key) {
            case 'listing': $active_level_id = $levels['listing_owners']; break;
            case 'board':   $active_level_id = $levels['board']; break;
            case 'oha':     $active_level_id = $levels['oha_member']; break;
            default:        $active_level_id = null;
        }


        $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';

        
        $card = isset($_GET['card']) ? sanitize_key($_GET['card']) : 'not';
        $allowed_card_filters = ['any','sent','not'];
        if (!in_array($card, $allowed_card_filters, true)) $card = 'any';
// One dataset for current level (or all), search applied; then range filtered in PHP
        $rows_all = $this->fetch_members_legacy_raw($active_level_id, $q);
        $rows = $this->php_filter_by_range($rows_all, $range_key);

        
        if ($card !== 'any') {
            $rows = array_values(array_filter($rows, function($r){
                $st = $this->get_card_status($r['member_id'], $r['wp_user_id'] ?? 0);
                if ($st['sent']) return ($_GET['card']==='sent');
                return ($_GET['card']==='not');
            }));
        }
// Counts by level from a single "all-levels" dataset for consistency
        $rows_for_counts = $this->php_filter_by_range($this->fetch_members_legacy_raw(null, $q), $range_key);
        $counts = ['oha_member' => 0, 'listing_owners' => 0, 'board' => 0];
        foreach ($rows_for_counts as $r) {
            $lvl = (string)($r['membership_level'] ?? '');
            if ($lvl === (string)$levels['oha_member'])     $counts['oha_member']++;
            if ($lvl === (string)$levels['listing_owners']) $counts['listing_owners']++;
            if ($lvl === (string)$levels['board'])          $counts['board']++;
        }

        $export_url = admin_url('admin-post.php?action=oha_export_members_csv');
        $export_url = add_query_arg(['range'=>$range_key], $export_url);
        $export_url = add_query_arg(['card'=>$card], $export_url);if ($lvl_key && $lvl_key !== 'all') $export_url = add_query_arg(['lvl'=>$lvl_key], $export_url);
        if ($q !== '') $export_url = add_query_arg(['q'=>$q], $export_url);

        ob_start(); ?>
        <div class="oha-toolbar">
            <div class="oha-filters">
                <strong>Level:</strong>
                <?php
                    // Build consistent links with active styling
                    $url_all = add_query_arg(['tab'=>'members','lvl'=>'all','range'=>$range_key,'q'=>$q]);
                    $cls_all = ($lvl_key==='all') ? 'button button-primary' : 'button';
                    echo '<a class="'.$cls_all.'" href="'.esc_url($url_all).'">All ('.intval(array_sum($counts)).')</a> ';
                    
                    $url_oha = add_query_arg(['tab'=>'members','lvl'=>'oha','range'=>$range_key,'q'=>$q]);
                    $cls_oha = ($lvl_key==='oha') ? 'button button-primary' : 'button';
                    echo '<a class="'.$cls_oha.'" href="'.esc_url($url_oha).'">OHA Member ('.intval($counts['oha_member']).')</a> ';

                    $url_listing = add_query_arg(['tab'=>'members','lvl'=>'listing','range'=>$range_key,'q'=>$q]);
                    $cls_listing = ($lvl_key==='listing') ? 'button button-primary' : 'button';
                    echo '<a class="'.$cls_listing.'" href="'.esc_url($url_listing).'">Basic ('.intval($counts['listing_owners']).')</a> ';

                    $url_board = add_query_arg(['tab'=>'members','lvl'=>'board','range'=>$range_key,'q'=>$q]);
                    $cls_board = ($lvl_key==='board') ? 'button button-primary' : 'button';
                    echo '<a class="'.$cls_board.'" href="'.esc_url($url_board).'">Board ('.intval($counts['board']).')</a> ';

                ?>
            </div>

            <div class="oha-filters">
                <strong>Range:</strong>
                <?php
                $ranges = ['30d'=>'Last 30 days','90d'=>'Last 90 days','ytd'=>'Year to date','all'=>'All time'];
                foreach ($ranges as $k=>$label) {
                    $url = add_query_arg(['tab'=>'members','lvl'=>$lvl_key,'range'=>$k,'q'=>$q]);
                    $cls = $range_key === $k ? 'button-primary' : '';
                    echo '<a class="button '.$cls.'" href="'.esc_url($url).'">'.esc_html($label).'</a> ';
                }
                ?>
            </div>
        
            <div class="oha-filters">
                <strong>Card:</strong>
                <?php
                    $cards = ['any'=>'Any','sent'=>'Sent'];
                    foreach ($cards as $ck=>$clabel) {
                        $url = add_query_arg(['tab'=>'members','lvl'=>$lvl_key,'range'=>$range_key,'card'=>$ck,'q'=>$q]);
                        $cls = $card === $ck ? 'button-primary' : '';
                        echo '<a class="button '.$cls.'" href="'.esc_url($url).'">'.esc_html($clabel).'</a> ';
                    }
                    // "Create Membership Cards" always resets to the baseline card-making view
                    $cls_cmc = ($card === 'not') ? 'button-primary' : '';
                    $url_cmc = add_query_arg(['tab'=>'members','lvl'=>'oha','range'=>'all','card'=>'not','q'=>'']);
                    echo '<a class="button '.$cls_cmc.'" href="'.esc_url($url_cmc).'">Create Membership Cards</a> ';
                ?>
            </div>
</div>

        <form method="get" class="oha-search-line" role="search" aria-label="Search members">
            <?php foreach (['tab'=>'members','lvl'=>$lvl_key,'range'=>$range_key] as $k=>$v) {
                printf('<input type="hidden" name="%s" value="%s" />', esc_attr(is_int($k)?'':$k), esc_attr($v));
            } ?>
            <input type="search" name="q" value="<?php echo esc_attr($q); ?>" placeholder="Search name or email…" aria-label="Search query" />
            <button class="button">Search</button>
            <?php if ($q !== ''): ?><a class="button" href="<?php echo esc_url( add_query_arg(['q'=>false]) ); ?>">Clear</a><?php endif; ?>
        </form>

        <p class="oha-found"><em><strong>Found <?php echo intval(count($rows)); ?> members</strong> for this filter.</em></p>
        <?php echo $this->render_members_table($rows); ?>
        <script>
        (function(){
            var ajaxUrl = '<?php echo esc_url( admin_url('admin-ajax.php', 'relative') ); ?>';

            // Card sent toggle
            function onCardClick(e){
                var b = e.target.closest('.oha-card-toggle');
                if(!b) return;
                e.preventDefault();
                var id = b.getAttribute('data-id');
                var wpUserId = b.getAttribute('data-wp-user-id') || '0';
                var sent = b.getAttribute('data-sent') === '1' ? 0 : 1;
                var nonce = b.getAttribute('data-nonce');
                b.disabled = true;
                var fd = new FormData();
                fd.append('action','oha_toggle_card_sent');
                fd.append('member_id', id);
                fd.append('wp_user_id', wpUserId);
                fd.append('sent', sent);
                fd.append('_wpnonce', nonce);
                fetch(ajaxUrl, {method:'POST', credentials:'same-origin', body:fd})
                .then(function(r){ return r.json().catch(function(){ return {success:false, data:{message:'Invalid JSON'}}; }); })
                .then(function(resp){
                    if(resp && resp.success){
                        b.setAttribute('data-sent', String(resp.data.sent));
                        b.textContent = resp.data.sent ? ('Card Sent' + (resp.data.date ? (' ('+resp.data.date+')') : '')) : 'Mark Sent';
                        if(resp.data.sent){ b.setAttribute('aria-pressed','true'); } else { b.removeAttribute('aria-pressed'); }
                    } else {
                        alert((resp && resp.data && resp.data.message) ? resp.data.message : 'Failed to update.');
                    }
                })
                .catch(function(){ alert('Network error.'); })
                .finally(function(){ b.disabled = false; });
            }

            // Member ID inline edit
            function onEditClick(e){
                var btn = e.target.closest('.oha-mid-display');
                if(!btn || btn.closest('.oha-mid-input')) return;
                e.preventDefault();
                var cell = btn.closest('.oha-member-id-cell');
                var display = btn;
                var current = display.textContent.trim();
                // Replace display + edit btn with input + save/cancel
                cell.innerHTML =
                    '<input type="text" class="oha-mid-input" value="'+current.replace(/"/g,'&quot;')+'" style="width:60px;" maxlength="10" />' +
                    ' <button type="button" class="oha-mid-save button button-small">Save</button>' +
                    ' <button type="button" class="oha-mid-cancel button button-small">Cancel</button>';
                cell.querySelector('.oha-mid-input').focus();
            }

            function onSaveClick(e){
                var btn = e.target.closest('.oha-mid-save');
                if(!btn) return;
                e.preventDefault();
                var cell = btn.closest('.oha-member-id-cell');
                var input = cell.querySelector('.oha-mid-input');
                var newVal = input.value.trim();
                if(!newVal){ alert('Card number cannot be blank.'); return; }
                var wpUserId = cell.getAttribute('data-wp-user-id');
                var nonce    = cell.getAttribute('data-nonce');
                var oldVal   = input.getAttribute('value');
                if(!confirm('Warning: This will permanently change the card number stored in the database from ' + oldVal + ' to ' + newVal + '.\n\nDouble-check before submitting. Continue?')){
                    return;
                }
                btn.disabled = true;
                var fd = new FormData();
                fd.append('action', 'oha_set_member_id');
                fd.append('wp_user_id', wpUserId);
                fd.append('new_member_id', newVal);
                fd.append('_wpnonce', nonce);
                fetch(ajaxUrl, {method:'POST', credentials:'same-origin', body:fd})
                .then(function(r){ return r.json().catch(function(){ return {success:false, data:{message:'Invalid JSON'}}; }); })
                .then(function(resp){
                    if(resp && resp.success){
                        cell.innerHTML =
                            '<span class="oha-mid-display" title="Click to edit card number" style="cursor:pointer;text-decoration:underline dotted;">'+resp.data.oha_member_id+'</span>';
                    } else {
                        alert((resp && resp.data && resp.data.message) ? resp.data.message : 'Failed to save.');
                        btn.disabled = false;
                    }
                })
                .catch(function(){ alert('Network error.'); btn.disabled = false; });
            }

            function onCancelClick(e){
                var btn = e.target.closest('.oha-mid-cancel');
                if(!btn) return;
                e.preventDefault();
                var cell = btn.closest('.oha-member-id-cell');
                var input = cell.querySelector('.oha-mid-input');
                var oldVal = input.getAttribute('value');
                var nonce  = cell.getAttribute('data-nonce');
                cell.innerHTML =
                    '<span class="oha-mid-display" title="Click to edit card number" style="cursor:pointer;text-decoration:underline dotted;">'+oldVal+'</span>';
            }

            document.addEventListener('click', function(e){
                onCardClick(e);
                onEditClick(e);
                onSaveClick(e);
                onCancelClick(e);
            });
        })();
        </script>

<div class="oha-actions-bottom">
            <a class="button" href="<?php echo esc_url($export_url); ?>">Export CSV</a>
        </div>

        
        <hr class=\"oha-sep\" />
        <h3>💳 Stripe Invoice Members</h3>
        <?php echo $this->render_stripe_members_section(); ?>
        
        <hr class=\"oha-sep\" />
        <h3>Stripe Membership Controls</h3>
        <?php if (function_exists('do_shortcode')) { echo do_shortcode('[oha_board_stripe_admin]'); } ?>
<?php
        return ob_get_clean();
    }

    private function render_members_table($rows) {
        if (empty($rows)) return '<p><em>No members found for this filter.</em></p>';
        $html = '<div class="oha-table-wrap"><table class="oha-table"><thead><tr>';
        $cols = ['ID','Name','Email','Address','Level','Start','Card'];
        foreach($cols as $c) { $html .= '<th>'.esc_html($c).'</th>'; }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $name = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? ''));
            $addr_parts = array_filter([
                $r['address_street'] ?? '',
                $r['address_city'] ?? '',
                $r['address_state'] ?? '',
                $r['address_zipcode'] ?? '',
                $r['country'] ?? ''
            ]);
            $addr = implode(' ', $addr_parts);
            $start = !empty($r['subscription_starts']) ? $r['subscription_starts'] : '—';
            $html .= '<tr>';
            $wp_uid_for_id = isset($r['wp_user_id']) ? intval($r['wp_user_id']) : intval($r['member_id']);
            $display_id    = !empty($r['oha_member_id']) ? esc_html($r['oha_member_id']) : intval($r['member_id']);
            $id_nonce      = wp_create_nonce('oha_set_member_id_' . $wp_uid_for_id);
            $html .= '<td class="oha-member-id-cell" data-wp-user-id="'.intval($wp_uid_for_id).'" data-nonce="'.esc_attr($id_nonce).'">';
            $html .= '<span class="oha-mid-display" title="Click to edit card number" style="cursor:pointer;text-decoration:underline dotted;">'.esc_html($display_id).'</span>';
            $html .= '</td>';
            $html .= '<td>'.esc_html($name ?: ($r['user_name'] ?? '')) .'</td>';
            $email = $r['email'] ?? '';
            $html .= '<td>'.($email ? '<a href="mailto:'.esc_attr($email).'">'.esc_html($email).'</a>' : '—').'</td>';
            $html .= '<td>'.($addr ? esc_html($addr) : '—').'</td>';
            $html .= '<td>'.esc_html($this->resolve_level_name($r['membership_level'] ?? '—')).'</td>';
            $html .= '<td>'.esc_html($start).'</td>';
            $wp_uid = isset($r['wp_user_id']) ? intval($r['wp_user_id']) : intval($r['member_id']);
            $st = $this->get_card_status($r['member_id'], $wp_uid);
            $nonce = wp_create_nonce('oha_card_sent_'.$r['member_id']);
            $label = $st['sent'] ? 'Card Sent'.($st['date']?' ('.$st['date'].')':'') : 'Mark Sent';
            $aria = $st['sent'] ? ' aria-pressed="true"' : '';
            $ev = $this->get_card_events();
            $eid = (string)$r['member_id'];
            $last_title = '';
            if (!empty($ev[$eid])) { $last = end($ev[$eid]); $last_title = esc_attr($last['action'].' @ '.$last['ts']); }
            $btn = '<button type="button" class="button oha-card-toggle" data-id="'.intval($r['member_id']).'" data-wp-user-id="'.$wp_uid.'" data-nonce="'.$nonce.'" data-sent="'.($st['sent']?1:0).'"'.$aria;
            if ($last_title) { $btn .= ' title="'.$last_title.'"'; }
            $btn .= '>'.$label.'</button>';
            $html .= '<td>'.$btn.'</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';
        return $html;
    }

    /**
     * Render Stripe invoice members and unmatched payments
     */
    private function render_stripe_members_section() {
        global $wpdb;
        
        ob_start();
        
        // Get Stripe invoice members
        $stripe_members = $wpdb->get_results(
            "SELECT u.ID, u.user_email, u.user_registered, u.display_name,
                    m1.meta_value as stripe_customer_id,
                    m2.meta_value as invoice_id,
                    m3.meta_value as paid_date,
                    m4.meta_value as created_from_invoice
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} m1 ON u.ID = m1.user_id AND m1.meta_key = 'oha_stripe_member_from_invoice' AND m1.meta_value = '1'
             LEFT JOIN {$wpdb->usermeta} m2 ON u.ID = m2.user_id AND m2.meta_key = 'stripe_customer_id'
             LEFT JOIN {$wpdb->usermeta} m3 ON u.ID = m3.user_id AND m3.meta_key = 'oha_stripe_invoice_id'
             LEFT JOIN {$wpdb->usermeta} m4 ON u.ID = m4.user_id AND m4.meta_key = 'oha_membership_paid_date'
             LEFT JOIN {$wpdb->usermeta} m5 ON u.ID = m5.user_id AND m5.meta_key = 'oha_created_from_invoice'
             ORDER BY u.user_registered DESC"
        );
        
        // Get unmatched payments
        $unmatched_payments = function_exists('oha_sm_get_unmatched_payments') ? oha_sm_get_unmatched_payments() : array();
        $unmatched_count = 0;
        foreach($unmatched_payments as $payment) {
            if($payment['status'] === 'unmatched') $unmatched_count++;
        }
        
        ?>
        
        <div class="oha-grid" style="grid-template-columns: 2fr 1fr; gap: 20px;">
            <!-- Stripe Members Table -->
            <div class="oha-card">
                <h4>Active Stripe Invoice Members (<?php echo count($stripe_members); ?>)</h4>
                <?php if (!empty($stripe_members)): ?>
                    <div class="oha-table-wrap">
                        <table class="oha-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Invoice ID</th>
                                    <th>Paid Date</th>
                                    <th>Source</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stripe_members as $member): ?>
                                    <tr>
                                        <td><?php echo esc_html($member->display_name ?: 'Unknown'); ?></td>
                                        <td>
                                            <a href="mailto:<?php echo esc_attr($member->user_email); ?>">
                                                <?php echo esc_html($member->user_email); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ($member->invoice_id): ?>
                                                <code style="font-size:11px;"><?php echo esc_html($member->invoice_id); ?></code>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($member->paid_date) {
                                                echo esc_html(date('M j, Y', strtotime($member->paid_date)));
                                            } else {
                                                echo esc_html(date('M j, Y', strtotime($member->user_registered)));
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($member->created_from_invoice): ?>
                                                <span class="oha-badge" style="background:#e3f2fd;color:#1976d2;padding:2px 6px;border-radius:3px;font-size:11px;">Auto-Created</span>
                                            <?php else: ?>
                                                <span class="oha-badge" style="background:#e8f5e8;color:#2e7d32;padding:2px 6px;border-radius:3px;font-size:11px;">Matched</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p><em>No Stripe invoice members found.</em></p>
                <?php endif; ?>
            </div>
            
            <!-- Unmatched Payments -->
            <div class="oha-card">
                <h4>⚠️ Unmatched Payments (<?php echo $unmatched_count; ?>)</h4>
                <?php if ($unmatched_count > 0): ?>
                    <p class="description" style="margin-top:0;">Membership payments that couldn't be matched to WordPress accounts.</p>
                    <div style="max-height:300px;overflow-y:auto;">
                        <?php foreach ($unmatched_payments as $invoice_id => $payment): ?>
                            <?php if ($payment['status'] !== 'unmatched') continue; ?>
                            <div class="oha-unmatched-payment" style="border:1px solid #ffc107;border-radius:4px;padding:10px;margin-bottom:8px;background:#fffdf0;">
                                <div style="font-weight:500;">
                                    <?php echo esc_html($payment['customer_name'] ?: 'Unknown Name'); ?>
                                </div>
                                <div style="font-size:13px;color:#666;">
                                    📧 <?php echo esc_html($payment['customer_email'] ?: 'No email'); ?>
                                </div>
                                <div style="font-size:12px;color:#666;margin-top:4px;">
                                    💰 $<?php echo number_format(($payment['amount_paid'] ?? 0) / 100, 2); ?>
                                    • <?php echo esc_html(date('M j, Y', strtotime($payment['paid_at']))); ?>
                                </div>
                                <div style="margin-top:6px;">
                                    <button type="button" class="button button-small oha-resolve-payment" 
                                            data-invoice="<?php echo esc_attr($invoice_id); ?>"
                                            data-email="<?php echo esc_attr($payment['customer_email']); ?>">
                                        🔍 Find Match
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p><em>✅ All membership payments have been matched to WordPress accounts.</em></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($unmatched_count > 0): ?>
        <script>
        jQuery(document).ready(function($) {
            $('.oha-resolve-payment').click(function() {
                var btn = $(this);
                var invoice = btn.data('invoice');
                var email = btn.data('email');
                
                if (!email) {
                    alert('No email address available for matching');
                    return;
                }
                
                // Simple solution: suggest manual user search
                var searchUrl = '<?php echo admin_url('users.php?s='); ?>' + encodeURIComponent(email);
                var message = 'Search for user with email: ' + email + '\n\n';
                message += 'If found, you can manually update their membership status.\n';
                message += 'If not found, consider contacting them to create an account.\n\n';
                message += 'Open user search?';
                
                if (confirm(message)) {
                    window.open(searchUrl, '_blank');
                }
            });
        });
        </script>
        <?php endif; ?>
        
        <?php if (!empty($stripe_members) || $unmatched_count > 0): ?>
        <div class="oha-info" style="background:#f0f8ff;border:1px solid #2196f3;border-radius:4px;padding:12px;margin-top:15px;">
            <h5 style="margin:0 0 8px 0;color:#1976d2;">💡 How Stripe Invoice Memberships Work:</h5>
            <ul style="margin:0;padding-left:20px;font-size:13px;">
                <li><strong>Auto-Matching:</strong> When invoices are paid, system tries to match by Stripe Customer ID, then by email</li>
                <li><strong>Account Creation:</strong> If no WordPress account exists, one is automatically created</li>
                <li><strong>Email Notifications:</strong> New users receive password reset links to set up their accounts</li>
                <li><strong>Manual Resolution:</strong> Unmatched payments can be resolved when users create accounts with matching emails</li>
            </ul>
        </div>
        <?php endif; ?>
        
        <?php
        return ob_get_clean();
    }

    public function export_members_csv() {
        if (!is_user_logged_in() || !$this->user_can_view()) { wp_die('Access denied.'); }
        $lvl_key   = isset($_GET['lvl'])   ? sanitize_key($_GET['lvl'])          : 'oha';
        $range_key = isset($_GET['range']) ? sanitize_key($_GET['range'])         : 'all';
        $q         = isset($_GET['q'])     ? sanitize_text_field($_GET['q'])      : '';
        $card      = isset($_GET['card'])  ? sanitize_key($_GET['card'])          : 'not';
        $allowed_card_filters = ['any', 'sent', 'not'];
        if (!in_array($card, $allowed_card_filters, true)) $card = 'not';

        $levels = $this->get_level_map();
        $level_id = null;
        switch ($lvl_key) {
            case 'listing': $level_id = $levels['listing_owners']; break;
            case 'board':   $level_id = $levels['board']; break;
            case 'oha':     $level_id = $levels['oha_member']; break;
        }

        $rows = $this->fetch_members_legacy_raw($level_id, $q);
        $rows = $this->php_filter_by_range($rows, $range_key);

        if ($card !== 'any') {
            $rows = array_values(array_filter($rows, function($r) use ($card) {
                $st = $this->get_card_status($r['member_id'], $r['wp_user_id'] ?? 0);
                if ($st['sent']) return ($card === 'sent');
                return ($card === 'not');
            }));
        }

        $filename = 'oha_members_' . ($level_id ? ('level'.$level_id.'_') : '') . $range_key . ($q? '_q' : '') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename='.$filename);
        $out = fopen('php://output', 'w');
        fputcsv($out, ['member_id','first_name','last_name','email','membership_level','subscription_starts','address_street','address_city','address_state','address_zipcode','country']);
        foreach($rows as $r) {
            fputcsv($out, [
                $r['member_id'] ?? '',
                $r['first_name'] ?? '',
                $r['last_name'] ?? '',
                $r['email'] ?? '',
                $r['membership_level'] ?? '',
                $r['subscription_starts'] ?? '',
                $r['address_street'] ?? '',
                $r['address_city'] ?? '',
                $r['address_state'] ?? '',
                $r['address_zipcode'] ?? '',
                $r['country'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    /* ========== Convention placeholder/toggle (CPTs only when enabled) ========== */

    public function register_convention_cpts() {
        register_post_type('oha_vendor', [
            'label' => 'Convention Vendors',
            'public' => false,
            'show_ui' => false,
            'supports' => ['title','editor','custom-fields'],
            'capability_type' => 'oha_vendor',
            'map_meta_cap' => true,
        ]);
        register_post_type('oha_sponsor', [
            'label' => 'Convention Sponsors',
            'public' => false,
            'show_ui' => false,
            'supports' => ['title','editor','custom-fields'],
            'capability_type' => 'oha_sponsor',
            'map_meta_cap' => true,
        ]);
        register_post_type('oha_guest', [
            'label' => 'Convention Guests',
            'public' => false,
            'show_ui' => false,
            'supports' => ['title','editor','custom-fields'],
            'capability_type' => 'oha_guest',
            'map_meta_cap' => true,
        ]);
    }

    
private function render_convention_tab() {
    if (function_exists('shortcode_exists') && shortcode_exists('oha_vendor_board_hub')) {
        return do_shortcode('[oha_vendor_board_hub]');
    }
    return '<div class="oha-card"><h3>Convention 2026</h3><p><em>Vendor dashboard shortcode not found.</em> Please ensure the Vendor module is included in the Suite.</p></div>';
}

private function render_sponsors_tab() {
    if (shortcode_exists('oha_sponsor_hub')) {
        return do_shortcode('[oha_sponsor_hub]');
    }
    return '<div class="oha-card"><h3>Sponsors</h3><p><em>Sponsor hub not loaded.</em> Ensure oha-sponsor-hub.lib.php is included.</p></div>';
}


    public function settings_menu() {
        // Disabled - using main OHA Board Hub menu instead to avoid duplicate settings pages
        // add_options_page('OHA Board Hub', 'OHA Board Hub', 'manage_options', 'oha-board-hub', [$this,'settings_page']);
    }

    public function register_settings() {
        register_setting(self::OPT, self::OPT, function($input) {
            if (!is_array($input)) return $input;
            if (isset($input['tab_management']) && is_array($input['tab_management'])) {
                $rekeyed = [];
                foreach ($input['tab_management'] as $old_key => $tab_data) {
                    if (isset($tab_data['type']) && $tab_data['type'] === 'custom' && !empty($tab_data['slug'])) {
                        $new_key = sanitize_key($tab_data['slug']);
                        if ($new_key === '') $new_key = $old_key;
                        // prevent collisions with existing built-in or already-rekeyed entries
                        if (isset($rekeyed[$new_key]) && $new_key !== $old_key) {
                            $new_key = $old_key;
                        }
                        $tab_data['slug'] = $new_key;
                        $rekeyed[$new_key] = $tab_data;
                    } else {
                        $rekeyed[$old_key] = $tab_data;
                    }
                }
                $input['tab_management'] = $rekeyed;
            }
            return $input;
        });

        // Phase 2A: Stat Card Links
        add_settings_section('stat_card_links', 'Dashboard Stat Card Links', function(){
            echo '<p>Configure where each dashboard stat card links to. Leave blank to make cards non-clickable.</p>';
        }, self::OPT);
        
        add_settings_field('stat_vendors_link', 'Vendors Card Link', function(){
            $opt = get_option(self::OPT, []);
            $val = isset($opt['stat_vendors_link']) ? esc_url($opt['stat_vendors_link']) : '?tab=con';
            echo '<input type="text" name="'.self::OPT.'[stat_vendors_link]" value="'.$val.'" class="regular-text" placeholder="?tab=con">';
            echo '<p class="description">Default: ?tab=con (Convention tab)</p>';
        }, self::OPT, 'stat_card_links');
        
        add_settings_field('stat_paid_vendors_link', 'Paid Vendors Card Link', function(){
            $opt = get_option(self::OPT, []);
            $val = isset($opt['stat_paid_vendors_link']) ? esc_url($opt['stat_paid_vendors_link']) : '?tab=con';
            echo '<input type="text" name="'.self::OPT.'[stat_paid_vendors_link]" value="'.$val.'" class="regular-text" placeholder="?tab=con">';
            echo '<p class="description">Default: ?tab=con (Convention tab)</p>';
        }, self::OPT, 'stat_card_links');
        
        add_settings_field('stat_pending_vendors_link', 'Pending Vendors Card Link', function(){
            $opt = get_option(self::OPT, []);
            $val = isset($opt['stat_pending_vendors_link']) ? esc_url($opt['stat_pending_vendors_link']) : '?tab=con';
            echo '<input type="text" name="'.self::OPT.'[stat_pending_vendors_link]" value="'.$val.'" class="regular-text" placeholder="?tab=con">';
            echo '<p class="description">Default: ?tab=con (Convention tab)</p>';
        }, self::OPT, 'stat_card_links');
        
        add_settings_field('stat_booths_link', 'Total Booths Card Link', function(){
            $opt = get_option(self::OPT, []);
            $val = isset($opt['stat_booths_link']) ? esc_url($opt['stat_booths_link']) : '?tab=con';
            echo '<input type="text" name="'.self::OPT.'[stat_booths_link]" value="'.$val.'" class="regular-text" placeholder="?tab=con">';
            echo '<p class="description">Default: ?tab=con (Convention tab)</p>';
        }, self::OPT, 'stat_card_links');
        
        add_settings_field('stat_volunteers_link', 'Volunteers Card Link', function(){
            $opt = get_option(self::OPT, []);
            $val = isset($opt['stat_volunteers_link']) ? esc_url($opt['stat_volunteers_link']) : admin_url('edit.php?post_type=volunteer');
            echo '<input type="text" name="'.self::OPT.'[stat_volunteers_link]" value="'.$val.'" class="regular-text" placeholder="'.admin_url('edit.php?post_type=volunteer').'">';
            echo '<p class="description">Default: WordPress admin volunteers page</p>';
        }, self::OPT, 'stat_card_links');
        
        add_settings_field('stat_members_link', 'OHA Members Card Link', function(){
            $opt = get_option(self::OPT, []);
            $val = isset($opt['stat_members_link']) ? esc_url($opt['stat_members_link']) : '?tab=members';
            echo '<input type="text" name="'.self::OPT.'[stat_members_link]" value="'.$val.'" class="regular-text" placeholder="?tab=members">';
            echo '<p class="description">Default: ?tab=members (Members tab)</p>';
        }, self::OPT, 'stat_card_links');
        
        add_settings_field('stat_haunts_link', 'Haunt Listings Card Link', function(){
            $opt = get_option(self::OPT, []);
            $val = isset($opt['stat_haunts_link']) ? esc_url($opt['stat_haunts_link']) : '?tab=gd';
            echo '<input type="text" name="'.self::OPT.'[stat_haunts_link]" value="'.$val.'" class="regular-text" placeholder="?tab=gd">';
            echo '<p class="description">Default: ?tab=gd (Listings & Events tab)</p>';
        }, self::OPT, 'stat_card_links');
        
        add_settings_field('stat_listing_owners_link', 'New Listing Owners Card Link', function(){
            $opt = get_option(self::OPT, []);
            $val = isset($opt['stat_listing_owners_link']) ? esc_url($opt['stat_listing_owners_link']) : '?tab=members&lvl=listing';
            echo '<input type="text" name="'.self::OPT.'[stat_listing_owners_link]" value="'.$val.'" class="regular-text" placeholder="?tab=members&lvl=listing">';
            echo '<p class="description">Default: ?tab=members&lvl=listing (Members tab filtered)</p>';
        }, self::OPT, 'stat_card_links');

        
        add_settings_section('tab_management', 'Tab Management', function(){
            echo '<p><strong>Enhanced Tab Management:</strong> Configure the order and visibility of both built-in and custom tabs. Add multiple custom tabs with shortcodes. Drag rows to reorder, or use the Order column for precise positioning.</p>';
            echo '<p><em>Built-in tabs:</em> Overview, Members, Listings & Events, Convention 2026, Finance, Site Activity, Training, Change Log, Haunt Discounts</p>';
        }, self::OPT);

        add_settings_field('tab_management_field', 'All Tabs (Built-in + Custom)', function(){
            $opt = get_option(self::OPT, []);
            
            // Define built-in tabs with default order
            $builtin_tabs = [
                'overview' => ['title' => 'Overview', 'type' => 'builtin', 'order' => 10],
                'members' => ['title' => 'Members', 'type' => 'builtin', 'order' => 20],
                'gd' => ['title' => 'Listings & Events', 'type' => 'builtin', 'order' => 30],
                'con' => ['title' => 'Convention 2026', 'type' => 'builtin', 'order' => 40],
                'finance' => ['title' => 'Finance', 'type' => 'builtin', 'order' => 50],
                'activity' => ['title' => 'Site Activity', 'type' => 'builtin', 'order' => 60],
                'help' => ['title' => 'Training', 'type' => 'builtin', 'order' => 70],
                'changes' => ['title' => 'Change Log', 'type' => 'builtin', 'order' => 80],
                'discounts' => ['title' => 'Haunt Discounts', 'type' => 'builtin', 'order' => 90]
            ];
            
            // Get saved tab settings (includes custom tabs and tab overrides)
            $saved_tabs = isset($opt['tab_management']) && is_array($opt['tab_management']) ? $opt['tab_management'] : [];
            
            // Merge built-in with saved settings
            $all_tabs = [];
            
            // First add built-in tabs with any saved overrides
            foreach ($builtin_tabs as $slug => $data) {
                $all_tabs[$slug] = [
                    'title' => $data['title'],
                    'slug' => $slug,
                    'type' => 'builtin',
                    'order' => isset($saved_tabs[$slug]) ? intval($saved_tabs[$slug]['order'] ?? $data['order']) : $data['order'],
                    'enabled' => isset($saved_tabs[$slug]) ? ($saved_tabs[$slug]['enabled'] ?? true) : true,
                    'shortcode' => '' // Built-in tabs don't use shortcodes
                ];
            }
            
            // Then add custom tabs
            foreach ($saved_tabs as $slug => $data) {
                if (!isset($builtin_tabs[$slug]) && isset($data['type']) && $data['type'] === 'custom') {
                    $all_tabs[$slug] = [
                        'title' => sanitize_text_field($data['title'] ?? 'Custom Tab'),
                        'slug' => sanitize_key($slug),
                        'type' => 'custom',
                        'order' => intval($data['order'] ?? 100),
                        'enabled' => $data['enabled'] ?? true,
                        'shortcode' => sanitize_text_field($data['shortcode'] ?? '')
                    ];
                }
            }
            
            // Sort by order
            uasort($all_tabs, function($a, $b) {
                return $a['order'] - $b['order'];
            });
            
            echo '<div id="oha-tab-management-wrap">';
            echo '<table class="widefat striped" id="oha-tab-management-table">';
            echo '<thead><tr>';
            echo '<th style="width:40px">#</th>';
            echo '<th style="width:200px">Tab Title</th>';
            echo '<th style="width:150px">Slug</th>';
            echo '<th style="width:100px">Type</th>';
            echo '<th style="width:80px">Order</th>';
            echo '<th style="width:200px">Shortcode (Custom Only)</th>';
            echo '<th style="width:80px">Enabled</th>';
            echo '<th style="width:60px">Delete</th>';
            echo '</tr></thead><tbody>';
            
            $index = 0;
            foreach ($all_tabs as $slug => $tab) {
                $index++;
                $disabled_fields = $tab['type'] === 'builtin' ? ' readonly' : '';
                $delete_button = $tab['type'] === 'builtin' ? '<span style="color:#999;">Built-in</span>' : '<button class="button oha-tab-del" type="button" data-slug="'.$slug.'">Delete</button>';
                $shortcode_field = $tab['type'] === 'builtin' ? 
                    '<span style="color:#999;">Built-in functionality</span>' : 
                    '<input type="text" name="'.self::OPT.'[tab_management]['.$slug.'][shortcode]" value="'.esc_attr($tab['shortcode']).'" class="regular-text" placeholder="[your_shortcode]">';
                
                echo '<tr data-slug="'.$slug.'" draggable="true">';
                echo '<td class="row-index">'.$index.'</td>';
                echo '<td>';
                if ($tab['type'] === 'builtin') {
                    echo '<strong>'.esc_html($tab['title']).'</strong>';
                    echo '<input type="hidden" name="'.self::OPT.'[tab_management]['.$slug.'][title]" value="'.esc_attr($tab['title']).'">';
                } else {
                    echo '<input type="text" name="'.self::OPT.'[tab_management]['.$slug.'][title]" value="'.esc_attr($tab['title']).'" class="regular-text" placeholder="Custom Tab Title">';
                }
                echo '<input type="hidden" name="'.self::OPT.'[tab_management]['.$slug.'][type]" value="'.$tab['type'].'">';
                echo '</td>';
                echo '<td>';
                if ($tab['type'] === 'builtin') {
                    echo '<code>'.esc_html($slug).'</code>';
                    echo '<input type="hidden" name="'.self::OPT.'[tab_management]['.$slug.'][slug]" value="'.$slug.'">';
                } else {
                    echo '<input type="text" name="'.self::OPT.'[tab_management]['.$slug.'][slug]" value="'.esc_attr($slug).'" class="regular-text" placeholder="custom-slug">';
                }
                echo '</td>';
                echo '<td>';
                echo '<span class="tab-type-badge tab-type-'.$tab['type'].'">'.ucfirst($tab['type']).'</span>';
                echo '</td>';
                echo '<td><input type="number" name="'.self::OPT.'[tab_management]['.$slug.'][order]" value="'.$tab['order'].'" class="small-text" min="0" step="10"></td>';
                echo '<td>'.$shortcode_field.'</td>';
                echo '<td><input type="hidden" name="'.self::OPT.'[tab_management]['.$slug.'][enabled]" value="0"><input type="checkbox" name="'.self::OPT.'[tab_management]['.$slug.'][enabled]" value="1" '.($tab['enabled'] ? 'checked' : '').'></td>';
                echo '<td>'.$delete_button.'</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            echo '<div style="margin-top:12px;">';
            echo '<button type="button" class="button" id="oha-add-custom-tab">Add Custom Tab</button> ';
            echo '<span style="color:#666;">Drag rows to reorder tabs, or use the Order column for precise positioning.</span>';
            echo '</div>';
            echo '</div>';
            
        }, self::OPT, 'tab_management');

        // Enhanced Tab Management JavaScript and CSS
        add_action('admin_print_footer_scripts', function(){
            if (!isset($_GET['page']) || $_GET['page'] !== OHA_BHS_MENU) return;
            ?>
            <script>
            (function(){
                const table = document.getElementById('oha-tab-management-table');
                if(!table) return;
                
                const tbody = table.querySelector('tbody');
                
                // Function to renumber rows and update order values
                function renumberRows(){
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    rows.forEach((tr, idx) => {
                        const indexCell = tr.querySelector('.row-index');
                        if (indexCell) indexCell.textContent = idx + 1;
                        
                        // Update order values to reflect new positions
                        const orderInput = tr.querySelector('input[name*="[order]"]');
                        if (orderInput) {
                            const newOrder = (idx + 1) * 10; // 10, 20, 30, etc.
                            orderInput.value = newOrder;
                        }
                    });
                }
                
                // Drag and drop functionality
                let draggedRow = null;
                
                tbody.addEventListener('dragstart', function(e) {
                    draggedRow = e.target.closest('tr');
                    e.dataTransfer.effectAllowed = 'move';
                    draggedRow.style.opacity = '0.5';
                });
                
                tbody.addEventListener('dragend', function(e) {
                    if (draggedRow) {
                        draggedRow.style.opacity = '';
                        draggedRow = null;
                    }
                });
                
                tbody.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    
                    const targetRow = e.target.closest('tr');
                    if (!targetRow || targetRow === draggedRow) return;
                    
                    const rect = targetRow.getBoundingClientRect();
                    const midpoint = rect.top + rect.height / 2;
                    const insertBefore = e.clientY < midpoint;
                    
                    if (insertBefore) {
                        tbody.insertBefore(draggedRow, targetRow);
                    } else {
                        tbody.insertBefore(draggedRow, targetRow.nextSibling);
                    }
                });
                
                tbody.addEventListener('drop', function(e) {
                    e.preventDefault();
                    renumberRows();
                });
                
                // Delete custom tab functionality
                tbody.addEventListener('click', function(e) {
                    if (e.target.classList.contains('oha-tab-del')) {
                        e.preventDefault();
                        const row = e.target.closest('tr');
                        const slug = row.getAttribute('data-slug');
                        
                        if (confirm('Are you sure you want to delete this custom tab?')) {
                            row.remove();
                            renumberRows();
                        }
                    }
                });
                
                // Add custom tab functionality
                const addButton = document.getElementById('oha-add-custom-tab');
                if (addButton) {
                    addButton.addEventListener('click', function() {
                        const timestamp = Date.now();
                        const newSlug = 'custom_tab_' + timestamp;
                        const rows = tbody.querySelectorAll('tr');
                        const newOrder = (rows.length + 1) * 10;
                        
                        const newRow = document.createElement('tr');
                        newRow.setAttribute('data-slug', newSlug);
                        newRow.setAttribute('draggable', 'true');
                        newRow.innerHTML = 
                            '<td class="row-index">' + (rows.length + 1) + '</td>' +
                            '<td><input type="text" name="<?php echo self::OPT; ?>[tab_management][' + newSlug + '][title]" value="" class="regular-text" placeholder="Custom Tab Title">' +
                            '<input type="hidden" name="<?php echo self::OPT; ?>[tab_management][' + newSlug + '][type]" value="custom"></td>' +
                            '<td><input type="text" name="<?php echo self::OPT; ?>[tab_management][' + newSlug + '][slug]" value="' + newSlug + '" class="regular-text"></td>' +
                            '<td><span class="tab-type-badge tab-type-custom">Custom</span></td>' +
                            '<td><input type="number" name="<?php echo self::OPT; ?>[tab_management][' + newSlug + '][order]" value="' + newOrder + '" class="small-text" min="0" step="10"></td>' +
                            '<td><input type="text" name="<?php echo self::OPT; ?>[tab_management][' + newSlug + '][shortcode]" value="" class="regular-text" placeholder="[your_shortcode]"></td>' +
                            '<td><input type="checkbox" name="<?php echo self::OPT; ?>[tab_management][' + newSlug + '][enabled]" value="1" checked></td>' +
                            '<td><button class="button oha-tab-del" type="button" data-slug="' + newSlug + '">Delete</button></td>';
                        
                        tbody.appendChild(newRow);
                        renumberRows();
                        
                        // Focus on the title field of the new row
                        const titleInput = newRow.querySelector('input[name*="[title]"]');
                        if (titleInput) titleInput.focus();
                    });
                }
                
                // Auto-generate slug from title for new custom tabs
                tbody.addEventListener('input', function(e) {
                    if (e.target.matches('input[name*="[title]"]')) {
                        const row = e.target.closest('tr');
                        const slugInput = row.querySelector('input[name*="[slug]"]');
                        
                        // Only auto-generate for new custom tabs (empty slug)
                        if (slugInput && !slugInput.readOnly && !slugInput.value.includes('custom_tab_')) {
                            const title = e.target.value;
                            const slug = title.toLowerCase()
                                            .replace(/[^a-z0-9\s-]/g, '')
                                            .replace(/\s+/g, '-')
                                            .replace(/-+/g, '-')
                                            .replace(/^-|-$/g, '');
                            
                            if (slug) {
                                const timestamp = Date.now();
                                const uniqueSlug = slug + '_' + timestamp;
                                slugInput.value = uniqueSlug;
                                row.setAttribute('data-slug', uniqueSlug);
                                
                                // Update all name attributes for this row
                                row.querySelectorAll('input').forEach(input => {
                                    const name = input.getAttribute('name');
                                    if (name && name.includes('[tab_management][')) {
                                        const newName = name.replace(/\[tab_management\]\[[^\]]+\]/, '[tab_management][' + uniqueSlug + ']');
                                        input.setAttribute('name', newName);
                                    }
                                });
                                
                                const delButton = row.querySelector('.oha-tab-del');
                                if (delButton) {
                                    delButton.setAttribute('data-slug', uniqueSlug);
                                }
                            }
                        }
                    }
                });
                
                // Initialize numbering
                renumberRows();
            })();
            </script>
            <style>
                #oha-tab-management-table {
                    margin-top: 10px;
                }
                #oha-tab-management-table td, 
                #oha-tab-management-table th { 
                    vertical-align: middle; 
                    padding: 12px 8px;
                }
                #oha-tab-management-table tr[draggable="true"] { 
                    cursor: move; 
                }
                #oha-tab-management-table tr[draggable="true"]:hover {
                    background-color: #f9f9f9;
                }
                #oha-tab-management-table input.small-text { 
                    width: 70px; 
                }
                #oha-tab-management-table input.regular-text { 
                    width: 100%; 
                    max-width: 200px;
                }
                .tab-type-badge {
                    display: inline-block;
                    padding: 4px 8px;
                    border-radius: 12px;
                    font-size: 11px;
                    font-weight: bold;
                    text-transform: uppercase;
                }
                .tab-type-builtin {
                    background-color: #e1f5fe;
                    color: #01579b;
                }
                .tab-type-custom {
                    background-color: #f3e5f5;
                    color: #4a148c;
                }
                .row-index {
                    font-weight: bold;
                    text-align: center;
                    color: #666;
                }
                #oha-add-custom-tab {
                    background-color: #0073aa;
                    border-color: #0073aa;
                    color: white;
                }
                #oha-add-custom-tab:hover {
                    background-color: #005a87;
                    border-color: #005a87;
                }
            </style>
            <?php
        });
        add_settings_section('general', 'General Settings', function(){
            echo '<p>Emails, training videos, and feature toggles. You can paste multiple video URLs for each section (comma or newline separated).</p>';
        }, self::OPT);

        $fields = [
            'notify_email'    => 'Board Notification Email(s) (comma-separated)',
            'new_window_days' => 'Overview “New Members” Window (days)',
            'videos_members'   => 'Members Training Video URL(s)',
            'videos_listings'  => 'Listings/GeoDirectory Training Video URL(s)',
            'videos_convention'=> 'Convention Tools Training Video URL(s)',
            'videos_events'    => 'Event Creation Training Video URL(s)',
            'enable_convention_tools' => 'Enable Convention Tools (leave OFF for stability)',
        ];
        foreach($fields as $key=>$label){
            add_settings_field($key, $label, function() use ($key){
                $opts = get_option(OHA_Board_Hub::OPT, []);
                if ($key === 'new_window_days') {
                    printf('<input type="number" class="small-text" name="%s[%s]" value="%s" />', esc_attr(OHA_Board_Hub::OPT), esc_attr($key), isset($opts[$key]) ? esc_attr($opts[$key]) : '');
                } else if ($key === 'enable_convention_tools') {
                    $checked = isset($opts[$key]) && $opts[$key] === '1' ? 'checked' : '';
                    printf('<label><input type="checkbox" name="%s[%s]" value="1" %s /> Enable</label><p class="description">Leave OFF if your host has strict PHP/MySQL settings or you saw errors previously. Turning it ON registers extra CPTs.</p>', esc_attr(OHA_Board_Hub::OPT), esc_attr($key), $checked);
                } else if (strpos($key, 'videos_') === 0) {
                    $val = isset($opts[$key]) ? $opts[$key] : '';
                    printf('<textarea class="large-text" rows="3" name="%s[%s]">%s</textarea><p class="description">Paste multiple URLs separated by commas or new lines. Unlisted YouTube/Vimeo embed/share URLs recommended.</p>', esc_attr(OHA_Board_Hub::OPT), esc_attr($key), esc_textarea($val));
                } else if ($key === 'notify_email') {
                    $val = isset($opts[$key]) ? $opts[$key] : '';
                    printf('<input type="text" class="regular-text" name="%s[%s]" value="%s" />', esc_attr(OHA_Board_Hub::OPT), esc_attr($key), esc_attr($val));
                }
            }, self::OPT, 'general');
        }
    }

    public function settings_page() {
        ?>
        <div class="wrap"><h1>OHA Board Hub</h1>
            <form method="post" action="options.php">
                <?php settings_fields(self::OPT); do_settings_sections(self::OPT); submit_button(); ?>
            </form>
            <p><strong>Setup:</strong> Create a page called <code>Board</code> and add the shortcode <code>[oha_board_hub]</code>. Assign the <em>OHA Board</em> role to board users for access.</p>
        </div>
        <?php
    }

    private function render_changes_tab() {
        ob_start(); ?>
        <h2 class="oha-overview-title">Change Log</h2>
        <div class="oha-card">
            <?php
            if ( function_exists('do_shortcode') ) {
                echo do_shortcode('[oha_change_log]');
            } else {
                echo '<p>[oha_change_log] shortcode requires WordPress do_shortcode.</p>';
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
new OHA_Board_Hub();
}


add_action('wp_head', function(){
    ?>
    <style>
    /* OHA Board Hub responsive dark theme */
    .oha-board{color:#eee}
    .oha-overview-title{margin:0 0 10px;color:#fff;font-size:20px}
    .oha-tabs{display:flex;gap:8px;margin-bottom:12px;flex-wrap:nowrap;overflow-x:auto;padding-bottom:4px;scrollbar-width:thin}
    .oha-tab{white-space:nowrap;padding:8px 12px;border-radius:10px;background:#1b1b1b;border:1px solid #333;color:#ddd;text-decoration:none;flex:0 0 auto}
    .oha-tab.is-active{background:#2a2a2a;border-color:#555}
    .oha-grid{display:grid;gap:16px;grid-template-columns:repeat(2,minmax(280px,1fr))}
    @media (max-width: 900px){ .oha-grid{grid-template-columns:1fr} }
    .oha-card{background:#111;color:#eee;padding:16px;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.25)}
    .oha-card h3{margin-top:0}
    .oha-card .button{display:inline-block;margin:4px 6px 0 0}
    .oha-list{margin:0;padding-left:18px}

    .oha-table-wrap{overflow:auto;max-height:480px;border:1px solid #333;border-radius:8px;background:#0e0e0e}
    .oha-table{width:100%;border-collapse:collapse}
    .oha-table th,.oha-table td{vertical-align:top}
    @media (max-width: 640px){
        .oha-table th,.oha-table td{padding:6px 8px;font-size:13px}
    }
    .oha-table th,.oha-table td{padding:8px;border-bottom:1px solid #333;font-size:14px}
    .oha-table th{position:sticky;top:0;background:#1b1b1b;color:#eee;z-index:1}
    .oha-table td{color:#ddd}

    /* Video embeds */
    .oha-video-wrap{position:relative;padding-bottom:56.25%;height:0;margin-top:8px}
    .oha-video-wrap iframe{position:absolute;top:0;left:0;width:100%;height:100%;border:0}

    /* Notices */
    .oha-board-denied{padding:24px;background:#221;color:#f6b;border:1px solid #511;border-radius:10px}

    /* Toolbar & filters */
    .oha-toolbar{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:6px;flex-wrap:wrap}
    .oha-filters{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
    .oha-filters .button{margin-right:0}

    /* Search line (full-width) */
    .oha-search-line{display:flex;gap:8px;align-items:center;margin:6px 0 10px;flex-wrap:wrap}
    .oha-search-line input[type="search"]{flex:1 1 240px;padding:8px;border-radius:8px;border:1px solid #444;background:#0f0f0f;color:#eee}

    .oha-sep{margin:24px 0;border:0;border-top:1px solid #2a2a2a}

    /* Bottom-right actions */
    .oha-actions-bottom{display:flex;gap:8px;justify-content:flex-end;margin-top:10px;flex-wrap:wrap}

    /* Link visibility */
    .oha-board a{color:#9fc4ff}
    .oha-board a:hover,.oha-board a:focus{text-decoration:underline}
    .oha-table a{color:#9fc4ff}
    .oha-card a.button,.oha-toolbar a.button,.oha-actions-bottom a.button{color:#eee;text-decoration:none}
    
    .oha-card-toggle{min-width:0;padding:4px 8px;font-size:12px;line-height:1.2}
.oha-found{margin:6px 0}
    </style>
    <?php
});
