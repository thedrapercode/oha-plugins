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
private function get_card_status($member_id){
        global $wpdb;
        $member_id = (string)intval($member_id);
        if (!$member_id) return ['sent'=>0,'date'=>''];
        $t = $this->card_tbl_names();
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t['status'])) === $t['status']){
            $row = $wpdb->get_row($wpdb->prepare("SELECT sent,date FROM {$t['status']} WHERE member_id=%d LIMIT 1", $member_id), ARRAY_A);
            if ($row){
                return ['sent'=> intval($row['sent']) ? 1 : 0, 'date'=> $row['date'] ?: ''];
            }
        }
        // Fallback to options
        $m = $this->get_card_store();
        if (isset($m[$member_id]) && !empty($m[$member_id]['sent'])) {
            return ['sent'=>1, 'date'=>($m[$member_id]['date'] ?? '')];
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

    private function get_custom_tabs(){
        $s = get_option(self::OPT, []);
        $tabs = isset($s[self::CUSTOM_TABS_KEY]) && is_array($s[self::CUSTOM_TABS_KEY]) ? $s[self::CUSTOM_TABS_KEY] : [];
        // sanitize & normalize
        $out = [];
        foreach($tabs as $t){
            if(!is_array($t)) continue;
            $title = isset($t['title']) ? sanitize_text_field($t['title']) : '';
            $slug  = isset($t['slug']) ? sanitize_title($t['slug']) : sanitize_title($title);
            $shortcode = isset($t['shortcode']) ? (string)$t['shortcode'] : '';
            $enabled = isset($t['enabled']) ? (bool)$t['enabled'] : true;
            $order = isset($t['order']) ? intval($t['order']) : 0;
            if(!$title || !$slug || !$shortcode || !$enabled) continue;
            $out[] = [
                'title'=>$title,
                'slug'=>$slug,
                'shortcode'=>$shortcode,
                'order'=>$order,
            ];
        }
        usort($out, function($a,$b){ return ($a['order'] <=> $b['order']) ?: strcmp($a['title'],$b['title']); });
        return $out;
    }


    public function __construct() {
        // Ensure card tables exist & migrate old options on admin and front hooks
        add_action('admin_init', function(){ $this->ensure_card_tables(); $this->migrate_card_options_to_tables(); });
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
            
        add_action('wp_ajax_oha_toggle_card_sent', [$this,'action_oha_toggle_card_sent']);
}
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
        if (current_user_can(self::CAP)) return true;
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
        $lvl_key = isset($_GET['lvl']) ? sanitize_key($_GET['lvl']) : 'all';
        $allowed_lvl_keys = ['listing','board','vendors','sponsors','con2026','oha','all'];
        if (!in_array($lvl_key, $allowed_lvl_keys, true)) $lvl_key = 'all';
        $range = isset($_GET['range']) ? sanitize_key($_GET['range']) : '30d';
        $allowed_ranges = ['30d','90d','ytd','all'];
        if (!in_array($range, $allowed_ranges, true)) $range = '30d';
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
        $tabs = [
            'overview' => 'Overview',
            'members'  => 'Members',
            'gd'       => 'Listings & Events',
            'con'      => 'Convention 2026',
            'finance'  => 'Finance',
            'activity' => 'Activity Log',
            'help'     => 'Training',
            'changes'  => 'Change Log',
            'discounts'  => 'Haunt Discounts',
];
        // Append custom tabs from settings
        $custom_tabs = $this->get_custom_tabs();
        if (!empty($custom_tabs)){
            foreach($custom_tabs as $ct){
                $key = 'ct__' . sanitize_key($ct['slug']);
                if (!isset($tabs[$key])){
                    $tabs[$key] = $ct['title'];
                }
            }
        }

        if (!isset($tabs[$tab])) $tab = 'overview';
        // Detect a matching custom tab (render early and return)
        $__ct_match = null;
        if (strpos($tab, 'ct__') === 0){
            $slug = substr($tab, 4);
            foreach ($this->get_custom_tabs() as $ct){
                if (sanitize_key($ct['slug']) === $slug){ $__ct_match = $ct; break; }
            }
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
                    if (strpos($tab, 'ct__') === 0){
                        $slug = substr($tab, 4);
                        $found = null;
                        foreach ($this->get_custom_tabs() as $ct){
                            if (sanitize_key($ct['slug']) === $slug){ $found = $ct; break; }
                        }
                        if ($found){
                            echo '<div class="oha-card"><h3>'.esc_html($found['title']).'</h3>';
                            echo do_shortcode($found['shortcode']);
                            echo '</div>';
                            break;
                        }
                    }
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
             AND m.meta_value IN ('Submitted', 'In Review', 'Approved', 'Pending', '')"
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
        
        // Volunteer count
        $volunteers = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'volunteer'"
        );
        
        // OHA Members
        $total_members = $wpdb->get_var(
            "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} m ON u.ID = m.user_id
             WHERE m.meta_key = 'uwp_member_type'
             AND m.meta_value = 'oha_member'"
        );
        
        $new_members = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} m ON u.ID = m.user_id
             WHERE m.meta_key = 'uwp_member_type'
             AND m.meta_value = 'oha_member'
             AND u.user_registered >= %s",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));
        
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
            
            <?php if ($recent_payments): ?>
            <!-- Recent Activity Feed -->
            <div class="oha-card" style="margin-top:20px;">
                <h3>Recent Activity</h3>
                <?php foreach ($recent_payments as $payment): ?>
                    <div style="padding:12px;border-bottom:1px solid #eee;">
                        💰 <strong><?php echo esc_html($payment->company_name); ?></strong> completed payment
                        <div style="font-size:12px;color:#999;margin-top:3px;">
                            <?php echo human_time_diff(strtotime($payment->post_modified), current_time('timestamp')); ?> ago
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        
        <?php
        // Dev Log + lightweight activity feed (Board visibility)
        if (class_exists('OHA_DevLog')) {
            echo OHA_DevLog::render_overview_widget();
            // Visual separation so the Dev Log card doesn't blend into the next grid section.
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
     * Phase 2B: Activity Log Tab
     */
    private function render_activity_log_tab() {
        global $wpdb;
        
        $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
        $type = isset($_GET['type']) ? sanitize_key($_GET['type']) : 'all';
        
        // Get various activities
        $activities = [];
        
        // Vendor status changes (from post_modified)
        $vendor_activities = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_modified as activity_date, 
                    m1.meta_value as company_name,
                    m2.meta_value as status,
                    'vendor_status' as activity_type
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} m1 ON p.ID = m1.post_id AND m1.meta_key = 'company_name'
             LEFT JOIN {$wpdb->postmeta} m2 ON p.ID = m2.post_id AND m2.meta_key = '_oha_status'
             WHERE p.post_type = 'vendor_application'
             AND p.post_status <> 'trash'
             AND p.post_modified >= %s
             ORDER BY p.post_modified DESC
             LIMIT 50",
            date('Y-m-d H:i:s', strtotime("-{$days} days"))
        ));
        
        foreach ($vendor_activities as $activity) {
            $activities[] = [
                'date' => $activity->activity_date,
                'type' => 'vendor_status',
                'icon' => '🎪',
                'description' => '<strong>' . esc_html($activity->company_name) . '</strong> status changed to ' . esc_html($activity->status),
                'user' => 'System',
            ];
        }
        
        // New member signups
        $member_activities = $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID, u.user_registered as activity_date, u.display_name,
                    m.meta_value as member_type
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} m ON u.ID = m.user_id
             WHERE m.meta_key = 'uwp_member_type'
             AND m.meta_value IN ('oha_member', 'listing_owner')
             AND u.user_registered >= %s
             ORDER BY u.user_registered DESC
             LIMIT 50",
            date('Y-m-d H:i:s', strtotime("-{$days} days"))
        ));
        
        foreach ($member_activities as $activity) {
            $activities[] = [
                'date' => $activity->activity_date,
                'type' => 'member_signup',
                'icon' => '👥',
                'description' => '<strong>' . esc_html($activity->display_name) . '</strong> joined as ' . ($activity->member_type === 'oha_member' ? 'OHA Member' : 'Listing Owner'),
                'user' => 'Automatic',
            ];
        }
        
        // Sort by date
        usort($activities, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        ob_start(); ?>
        <h2>Activity Log</h2>
        
        <!-- Filters -->
        <div class="oha-card" style="margin-bottom:20px;">
            <form method="get" style="display:flex;gap:15px;flex-wrap:wrap;align-items:center;">
                <input type="hidden" name="page" value="oha-board-hub">
                <input type="hidden" name="tab" value="activity">
                
                <label>
                    <strong>Time Range:</strong>
                    <select name="days" onchange="this.form.submit()">
                        <option value="7" <?php selected($days, 7); ?>>Last 7 Days</option>
                        <option value="30" <?php selected($days, 30); ?>>Last 30 Days</option>
                        <option value="90" <?php selected($days, 90); ?>>Last 90 Days</option>
                        <option value="365" <?php selected($days, 365); ?>>Last Year</option>
                    </select>
                </label>
                
                <label>
                    <strong>Activity Type:</strong>
                    <select name="type" onchange="this.form.submit()">
                        <option value="all" <?php selected($type, 'all'); ?>>All Activities</option>
                        <option value="vendor_status" <?php selected($type, 'vendor_status'); ?>>Vendor Updates</option>
                        <option value="member_signup" <?php selected($type, 'member_signup'); ?>>Member Signups</option>
                    </select>
                </label>
                
                <button type="submit" class="button">Refresh</button>
            </form>
        </div>
        
        <!-- Activity Feed -->
        <div class="oha-card">
            <h3>Recent Activity (<?php echo count($activities); ?> items)</h3>
            
            <?php if ($activities): ?>
                <div style="margin-top:20px;">
                    <?php foreach ($activities as $activity): 
                        if ($type !== 'all' && $activity['type'] !== $type) continue;
                    ?>
                        <div style="padding:15px;border-bottom:1px solid #eee;display:flex;gap:15px;align-items:start;">
                            <div style="font-size:24px;line-height:1;"><?php echo $activity['icon']; ?></div>
                            <div style="flex:1;">
                                <div style="margin-bottom:5px;">
                                    <?php echo $activity['description']; ?>
                                </div>
                                <div style="font-size:12px;color:#999;">
                                    <?php echo human_time_diff(strtotime($activity['date']), current_time('timestamp')); ?> ago
                                    • <?php echo date('M j, Y g:i a', strtotime($activity['date'])); ?>
                                    <?php if ($activity['user']): ?>
                                        • by <?php echo esc_html($activity['user']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="text-align:center;padding:40px;color:#999;">
                    No activity in the selected time range
                </p>
            <?php endif; ?>
        </div>
        
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

        $counts = ['listing_owners'=>0,'board'=>0,'vendors'=>0,'sponsors'=>0,'convention2026'=>0,'oha_member'=>0];
        foreach ($rows_all as $r) {
            $d = isset($r['subscription_starts']) ? trim($r['subscription_starts']) : '';
            if ($d === '') { continue; }
            $ts = strtotime($d);
            if (!$ts || $ts < $since_ts) { continue; }
            $lvl = (string)($r['membership_level'] ?? '');
            if ($lvl === (string)$levels['listing_owners']) $counts['listing_owners']++;
            if ($lvl === (string)$levels['board'])          $counts['board']++;
            if ($lvl === (string)$levels['vendors'])        $counts['vendors']++;
            if ($lvl === (string)$levels['sponsors'])       $counts['sponsors']++;
            if ($lvl === (string)$levels['convention2026']) $counts['convention2026']++;
                    if ($lvl === (string)$levels['oha_member']) $counts['oha_member']++;
}
        return $counts;
    }

    private function fetch_members_legacy_raw($level_id = null, $q = '') {
        global $wpdb;
        $members_tbl  = $wpdb->prefix . 'swpm_members_tbl';

        $where = [];
        $params = [];
        if ($level_id) { $where[] = "membership_level = %d"; $params[] = intval($level_id); }
        if ($q !== '') {
            $like = '%' . $wpdb->esc_like($q) . '%';
            $where[] = "(first_name LIKE %s OR last_name LIKE %s OR user_name LIKE %s OR email LIKE %s)";
            array_push($params, $like, $like, $like, $like);
        }
        $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "SELECT * FROM $members_tbl $where_sql ORDER BY member_id DESC LIMIT 5000";
        if ($params) {
            $prepared = call_user_func_array([$wpdb,'prepare'], array_merge([$sql], $params));
            $rows = $wpdb->get_results($prepared, ARRAY_A);
        } else {
            $rows = $wpdb->get_results($sql, ARRAY_A);
        }
        return is_array($rows) ? $rows : [];
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
            case 'listing':  $active_level_id = $levels['listing_owners']; break;
            case 'board':    $active_level_id = $levels['board']; break;
            case 'vendors':  $active_level_id = $levels['vendors']; break;
            case 'sponsors': $active_level_id = $levels['sponsors']; break;
            case 'con2026':  $active_level_id = $levels['convention2026']; break;
            case 'oha':     $active_level_id = $levels['oha_member']; break;
            default: $active_level_id = null;
        }
        if ($lvl_key === 'oha') { $active_level_id = $levels['oha_member']; }


        $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';

        
        $card = isset($_GET['card']) ? sanitize_key($_GET['card']) : 'any';
        $allowed_card_filters = ['any','sent','not'];
        if (!in_array($card, $allowed_card_filters, true)) $card = 'any';
// One dataset for current level (or all), search applied; then range filtered in PHP
        $rows_all = $this->fetch_members_legacy_raw($active_level_id, $q);
        $rows = $this->php_filter_by_range($rows_all, $range_key);

        
        if ($card !== 'any') {
            $rows = array_values(array_filter($rows, function($r){
                $st = $this->get_card_status($r['member_id']);
                if ($st['sent']) return ($_GET['card']==='sent');
                return ($_GET['card']==='not');
            }));
        }
// Counts by level from a single "all-levels" dataset for consistency
        $rows_for_counts = $this->php_filter_by_range($this->fetch_members_legacy_raw(null, $q), $range_key);
        $counts = ['listing_owners'=>0,'board'=>0,'vendors'=>0,'sponsors'=>0,'convention2026'=>0,'oha_member'=>0];
        foreach ($rows_for_counts as $r) {
            $lvl = (string)($r['membership_level'] ?? '');
            if ($lvl === (string)$levels['listing_owners']) $counts['listing_owners']++;
            if ($lvl === (string)$levels['board'])          $counts['board']++;
            if ($lvl === (string)$levels['vendors'])        $counts['vendors']++;
            if ($lvl === (string)$levels['sponsors'])       $counts['sponsors']++;
            if ($lvl === (string)$levels['convention2026']) $counts['convention2026']++;
                    if ($lvl === (string)$levels['oha_member']) $counts['oha_member']++;
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

                    $url_vendors = add_query_arg(['tab'=>'members','lvl'=>'vendors','range'=>$range_key,'q'=>$q]);
                    $cls_vendors = ($lvl_key==='vendors') ? 'button button-primary' : 'button';
                    echo '<a class="'.$cls_vendors.'" href="'.esc_url($url_vendors).'">Vendors ('.intval($counts['vendors']).')</a> ';

                    $url_sponsors = add_query_arg(['tab'=>'members','lvl'=>'sponsors','range'=>$range_key,'q'=>$q]);
                    $cls_sponsors = ($lvl_key==='sponsors') ? 'button button-primary' : 'button';
                    echo '<a class="'.$cls_sponsors.'" href="'.esc_url($url_sponsors).'">Sponsors ('.intval($counts['sponsors']).')</a> ';

                    $url_con = add_query_arg(['tab'=>'members','lvl'=>'con2026','range'=>$range_key,'q'=>$q]);
                    $cls_con = ($lvl_key==='con2026') ? 'button button-primary' : 'button';
                    echo '<a class="'.$cls_con.'" href="'.esc_url($url_con).'">Convention 2026 ('.intval($counts['convention2026']).')</a> ';
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
                    $cards = ['any'=>'Any','sent'=>'Sent','not'=>'Not Sent'];
                    foreach ($cards as $ck=>$clabel) {
                        $url = add_query_arg(['tab'=>'members','lvl'=>$lvl_key,'range'=>$range_key,'card'=>$ck,'q'=>$q]);
                        $cls = $card === $ck ? 'button-primary' : '';
                        echo '<a class="button '.$cls.'" href="'.esc_url($url).'">'.esc_html($clabel).'</a> ';
                    }
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
            function onClick(e){
                var b = e.target.closest('.oha-card-toggle');
                if(!b) return;
                e.preventDefault();
                var id = b.getAttribute('data-id');
                var sent = b.getAttribute('data-sent') === '1' ? 0 : 1;
                var nonce = b.getAttribute('data-nonce');
                b.disabled = true;
                var fd = new FormData();
                fd.append('action','oha_toggle_card_sent');
                fd.append('member_id', id);
                fd.append('sent', sent);
                fd.append('_wpnonce', nonce);
                fetch('<?php echo esc_url( admin_url('admin-ajax.php', 'relative') ); ?>', {method:'POST', credentials:'same-origin', body:fd})
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
            document.addEventListener('click', onClick);
        })();
        </script>

<div class="oha-actions-bottom">
            <a class="button" href="<?php echo esc_url($export_url); ?>">Export CSV</a>
            <a class="button" href="<?php echo admin_url('admin.php?page=simple_wp_membership'); ?>">Open Simple Membership</a>
        </div>

        
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
            $html .= '<td>'.intval($r['member_id']).'</td>';
            $html .= '<td>'.esc_html($name ?: ($r['user_name'] ?? '')) .'</td>';
            $email = $r['email'] ?? '';
            $html .= '<td>'.($email ? '<a href="mailto:'.esc_attr($email).'">'.esc_html($email).'</a>' : '—').'</td>';
            $html .= '<td>'.($addr ? esc_html($addr) : '—').'</td>';
            $html .= '<td>'.esc_html($this->resolve_level_name($r['membership_level'] ?? '—')).'</td>';
            $html .= '<td>'.esc_html($start).'</td>';
            $st = $this->get_card_status($r['member_id']);
            $nonce = wp_create_nonce('oha_card_sent_'.$r['member_id']);
            $label = $st['sent'] ? 'Card Sent'.($st['date']?' ('.$st['date'].')':'') : 'Mark Sent';
            $aria = $st['sent'] ? ' aria-pressed="true"' : '';
            $ev = $this->get_card_events();
            $eid = (string)$r['member_id'];
            $last_title = '';
            if (!empty($ev[$eid])) { $last = end($ev[$eid]); $last_title = esc_attr($last['action'].' @ '.$last['ts']); }
            $btn = '<button type="button" class="button oha-card-toggle" data-id="'.intval($r['member_id']).'" data-nonce="'.$nonce.'" data-sent="'.($st['sent']?1:0).'"'.$aria;
            if ($last_title) { $btn .= ' title="'.$last_title.'"'; }
            $btn .= '>'.$label.'</button>';
            $html .= '<td>'.$btn.'</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';
        return $html;
    }

    public function export_members_csv() {
        if (!is_user_logged_in() || !$this->user_can_view()) { wp_die('Access denied.'); }
        $lvl_key = isset($_GET['lvl']) ? sanitize_key($_GET['lvl']) : 'all';
        $range_key = isset($_GET['range']) ? sanitize_key($_GET['range']) : '30d';
        $q     = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';

        $levels = $this->get_level_map();
        $level_id = null;
        switch ($lvl_key) {
            case 'listing':  $level_id = $levels['listing_owners']; break;
            case 'board':    $level_id = $levels['board']; break;
            case 'vendors':  $level_id = $levels['vendors']; break;
            case 'sponsors': $level_id = $levels['sponsors']; break;
            case 'con2026':  $level_id = $levels['convention2026']; break;
            case 'oha':     $level_id = $levels['oha_member']; break;
        }
        // OHA Member level
        if ($lvl_key === 'oha') { $level_id = $levels['oha_member']; }


        $rows = $this->fetch_members_legacy_raw($level_id, $q);
        $rows = $this->php_filter_by_range($rows, $range_key);

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


    public function settings_menu() {
        add_options_page('OHA Board Hub', 'OHA Board Hub', 'manage_options', 'oha-board-hub', [$this,'settings_page']);
    }

    public function register_settings() {
        register_setting(self::OPT, self::OPT);

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

        
        add_settings_section('custom_tabs', 'Custom Tabs', function(){
            echo '<p>Create extra tabs for the Board Hub without editing code. Add a title, slug, order, and the shortcode to render inside the tab.</p>';
        }, self::OPT);

        add_settings_field('custom_tabs_field', 'Tabs', function(){
            $opt = get_option(self::OPT, []);
            $tabs = isset($opt[self::CUSTOM_TABS_KEY]) && is_array($opt[self::CUSTOM_TABS_KEY]) ? $opt[self::CUSTOM_TABS_KEY] : [];
            // Ensure array items are safe
            echo '<div id="oha-ct-wrap">';
            echo '<table class="widefat striped" id="oha-ct-table"><thead><tr><th style="width:36px">#</th><th>Title</th><th>Slug</th><th style="width:80px">Order</th><th>Shortcode</th><th style="width:60px">On?</th><th style="width:60px">Del</th></tr></thead><tbody>';
            $i = 0;
            foreach ($tabs as $t){
                $title = esc_attr($t['title'] ?? '');
                $slug = esc_attr($t['slug'] ?? '');
                $order = intval($t['order'] ?? 0);
                $shortcode = esc_attr($t['shortcode'] ?? '');
                $enabled = !isset($t['enabled']) || $t['enabled'] ? 'checked' : '';
                echo '<tr draggable="true">';
                echo '<td class="index">'.(++$i).'</td>';
                echo '<td><input type="text" name="'.self::OPT.'['.self::CUSTOM_TABS_KEY.'][%i%][title]" value="'.$title.'" class="regular-text title"></td>';
                echo '<td><input type="text" name="'.self::OPT.'['.self::CUSTOM_TABS_KEY.'][%i%][slug]" value="'.$slug.'" class="regular-text slug" placeholder="auto-from-title"></td>';
                echo '<td><input type="number" name="'.self::OPT.'['.self::CUSTOM_TABS_KEY.'][%i%][order]" value="'.$order.'" class="small-text order"></td>';
                echo '<td><input type="text" name="'.self::OPT.'['.self::CUSTOM_TABS_KEY.'][%i%][shortcode]" value="'.$shortcode.'" class="regular-text shortcode" placeholder="[your_shortcode]"></td>';
                echo '<td style="text-align:center"><input type="checkbox" name="'.self::OPT.'['.self::CUSTOM_TABS_KEY.'][%i%][enabled]" value="1" '.$enabled.'></td>';
                echo '<td style="text-align:center"><button class="button oha-ct-del" type="button">×</button></td>';
                echo '</tr>';
            }
            if($i===0){
                echo '<tr draggable="true">';
                echo '<td class="index">1</td>';
                echo '<td><input type="text" name="'.self::OPT.'['.self::CUSTOM_TABS_KEY.'][0][title]" value="" class="regular-text title" placeholder="My Tab"></td>';
                echo '<td><input type="text" name="'.self::OPT.'['.self::CUSTOM_TABS_KEY.'][0][slug]" value="" class="regular-text slug" placeholder="my-tab"></td>';
                echo '<td><input type="number" name="'.self::OPT.'['.self::CUSTOM_TABS_KEY.'][0][order]" value="0" class="small-text order"></td>';
                echo '<td><input type="text" name="'.self::OPT.'['.self::CUSTOM_TABS_KEY.'][0][shortcode]" value="" class="regular-text shortcode" placeholder="[shortcode]"></td>';
                echo '<td style="text-align:center"><input type="checkbox" name="'.self::OPT.'['.self::CUSTOM_TABS_KEY.'][0][enabled]" value="1" checked></td>';
                echo '<td style="text-align:center"><button class="button oha-ct-del" type="button">×</button></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '<p><button type="button" class="button" id="oha-ct-add">Add Tab</button> <em>Drag rows to reorder. Order column controls sorting too.</em></p>';
            echo '</div>';
        }, self::OPT, 'custom_tabs');

        // JS/CSS to support repeater
        add_action('admin_print_footer_scripts', function(){
            if (!isset($_GET['page']) || $_GET['page'] !== OHA_BHS_MENU) return;
            ?>
            <script>
            (function(){
                const table = document.getElementById('oha-ct-table');
                if(!table) return;
                const tbody = table.querySelector('tbody');
                function renumber(){
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    rows.forEach((tr, idx) => {
                        tr.querySelector('.index').textContent = idx+1;
                        tr.querySelectorAll('input').forEach(inp => {
                            inp.name = inp.name.replace(/\\[custom_tabs\\]\\[(\\d+)\\]/, function(_, oldIndex){
                                return "[custom_tabs]["+idx+"]";
                            });
                        });
                    });
                }
                // Drag-and-drop
                let drag;
                tbody.addEventListener('dragstart', e => { drag = e.target.closest('tr'); e.dataTransfer.effectAllowed='move'; });
                tbody.addEventListener('dragover', e => { e.preventDefault(); const tr = e.target.closest('tr'); if(!tr||tr===drag) return; const rect = tr.getBoundingClientRect(); const before = (e.clientY - rect.top) < rect.height/2; tbody.insertBefore(drag, before?tr:tr.nextSibling); });
                tbody.addEventListener('drop', e => { e.preventDefault(); renumber(); });

                // Delete
                tbody.addEventListener('click', e => {
                    if(e.target.classList.contains('oha-ct-del')){
                        e.preventDefault();
                        const tr = e.target.closest('tr');
                        tr.parentNode.removeChild(tr);
                        renumber();
                    }
                });
                // Add
                document.getElementById('oha-ct-add').addEventListener('click', function(){
                    const last = tbody.querySelector('tr:last-child');
                    const clone = last ? last.cloneNode(true) : document.createElement('tr');
                    if(!last){
                        clone.innerHTML = '<td class="index"></td><td><input type="text" name="<?php echo self::OPT; ?>[custom_tabs][0][title]" value="" class="regular-text title"></td><td><input type="text" name="<?php echo self::OPT; ?>[custom_tabs][0][slug]" value="" class="regular-text slug" placeholder="my-tab"></td><td><input type="number" name="<?php echo self::OPT; ?>[custom_tabs][0][order]" value="0" class="small-text order"></td><td><input type="text" name="<?php echo self::OPT; ?>[custom_tabs][0][shortcode]" value="" class="regular-text shortcode" placeholder="[shortcode]"></td><td style="text-align:center"><input type="checkbox" name="<?php echo self::OPT; ?>[custom_tabs][0][enabled]" value="1" checked></td><td style="text-align:center"><button class="button oha-ct-del" type="button">×</button></td>';
                    } else {
                        clone.querySelectorAll('input').forEach(inp => {
                            if(inp.type==='checkbox'){ inp.checked=true; }
                            else { inp.value=''; }
                        });
                    }
                    tbody.appendChild(clone);
                    renumber();
                });
                renumber();
            })();
            </script>
            <style>
                #oha-ct-table td, #oha-ct-table th { vertical-align: middle; }
                #oha-ct-table tr { cursor: move; }
                #oha-ct-table input.small-text { width: 70px; }
                #oha-ct-table input.regular-text.shortcode { width: 100%; }
            </style>
            <?php
        });
add_settings_section('ids', 'Membership Level IDs', function(){
            echo '<p>Map your Simple Membership level IDs here. Users with the <em>OHA Board</em> role or the Webmaster level can access the Board Hub.</p>';
        }, self::OPT);

        $id_fields = [
            'level_listing_owners'  => 'Basic (ID)',
            'level_board'           => 'Board Members (ID)',
            'level_vendors'         => 'Vendors (ID)',
            'level_sponsors'        => 'Sponsors (ID)',
            'level_oha_member'     => 'OHA Member (ID)',
            'level_convention2026'  => 'Convention 2026 (ID)',
            'level_webmaster'       => 'Webmaster (ID) — also grants Board Hub access',
        ];
        foreach($id_fields as $key=>$label){
            add_settings_field($key, $label, function() use ($key){
                $opts = get_option(OHA_Board_Hub::OPT, []);
                printf('<input type="number" class="small-text" name="%s[%s]" value="%s" />', esc_attr(OHA_Board_Hub::OPT), esc_attr($key), isset($opts[$key]) ? esc_attr($opts[$key]) : '');
            }, self::OPT, 'ids');
        }

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

        // Dev Log tools (manual backfill / quick add)
        add_settings_section('devlog_tools', 'Dev Log Tools', function(){
            echo '<p>Quickly add/backfill development entries that appear in the Board Hub Overview. (Board-facing, readable log — not a timesheet.)</p>';
        }, self::OPT);

        add_settings_field('devlog_tools_box', 'Manual Entry', function(){
            if (class_exists('OHA_DevLog')) {
                OHA_DevLog::render_settings_tools();
            } else {
                echo '<p>Dev Log module not loaded.</p>';
            }
        }, self::OPT, 'devlog_tools');
    }

    public function settings_page() {
        ?>
        <div class="wrap"><h1>OHA Board Hub</h1>
            <form method="post" action="options.php">
                <?php settings_fields(self::OPT); do_settings_sections(self::OPT); submit_button(); ?>
            </form>
            <p><strong>Setup:</strong> Create a page called <code>Board</code> and add the shortcode <code>[oha_board_hub]</code>. Assign the <em>OHA Board</em> role to board users, or ensure their Simple Membership level matches your configured <em>Webmaster</em> level ID to grant access.</p>
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
