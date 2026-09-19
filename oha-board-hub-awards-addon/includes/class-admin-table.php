<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class OHA_Awards_Admin_Table extends WP_List_Table {
    public static function init() {
        add_action('admin_menu', [__CLASS__,'admin_page']);
        add_action('admin_post_oha_awards_set_status', [__CLASS__,'handle_set_status']);
        add_action('admin_post_oha_awards_export_csv', [__CLASS__,'export_csv']);
        add_action('oha_awards_admin_sections', [__CLASS__,'render_bulk_section']);
    }

    public static function admin_page() {
        add_submenu_page('oha-awards','Awards Submissions','Submissions','manage_options','oha-awards-submissions',[__CLASS__,'render_page']);
    }

    /**
     * Returns the year to use for the current admin view.
     * Reads ?award_year_filter= from the URL so board members can browse
     * historical years without touching the global Award Year setting.
     */
    public static function get_view_year() {
        if (isset($_GET['award_year_filter'])) {
            $y = intval($_GET['award_year_filter']);
            if ($y > 2000) return $y;
        }
        return OHA_Awards_Settings::get_year();
    }

    /**
     * Returns all distinct years that have at least one award entry in the DB.
     * Always includes the global Award Year even if no entries exist for it yet.
     */
    public static function get_all_years() {
        global $wpdb;
        $rows = $wpdb->get_col(
            "SELECT DISTINCT CAST(meta_value AS UNSIGNED) FROM {$wpdb->postmeta}
             WHERE meta_key = '_oha_award_year' AND meta_value != '' AND meta_value != '0'
             ORDER BY 1 DESC"
        );
        $years = array_map('intval', $rows);
        $active = OHA_Awards_Settings::get_year();
        if (!in_array($active, $years)) array_unshift($years, $active);
        return $years;
    }

    public static function handle_set_status() {
        if (!current_user_can('manage_options')) wp_die('Nope');
        check_admin_referer('oha_awards_set_status');
        $post_id = intval($_POST['post_id'] ?? 0);
        $status  = sanitize_text_field($_POST['status'] ?? '');
        $prev    = (string) get_post_meta($post_id, '_oha_status', true);
        OHA_Awards_Helpers::set_status($post_id, $status);
        OHA_Awards_Helpers::log_status($post_id, $status, 'Admin status change');
        // Send cert-ready email when transitioning to completed for the first time
        if ($status === 'completed' && $prev !== 'completed') {
            OHA_Awards_Email::send_cert_ready($post_id);
        }
        wp_redirect(wp_get_referer());
        exit;
    }

    
    public static function export_csv() {
        if (!current_user_can('manage_options')) wp_die('Nope');
        $year = self::get_view_year();
        // Build dynamic header of all _oha_ meta keys across posts for the active year
        $q = new WP_Query(['post_type'=>'oha_award_entry','posts_per_page'=>-1,'orderby'=>'date','order'=>'DESC','meta_query'=>[['key'=>'_oha_award_year','value'=>$year,'compare'=>'=','type'=>'NUMERIC']]]);
        $all_meta_keys = [];
        $posts = [];
        while($q->have_posts()){ $q->the_post();
            $id = get_the_ID();
            $posts[] = $id;
            $meta = get_post_meta($id);
            if (is_array($meta)) {
                foreach ($meta as $k => $v) {
                    if (strpos($k, '_oha_') === 0) {
                        $all_meta_keys[$k] = true;
                    }
                }
            }
        }
        ksort($all_meta_keys);
        $all_meta_keys = array_keys($all_meta_keys);

        // CSV headers: fixed human-friendly columns + all meta keys (raw)
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=oha-awards-'.$year.'.csv');
        $out = fopen('php://output', 'w');

        $headers = array_merge(
            ['ID','Haunt Title (resolved)','Created (UTC)','Status','Photo URL'],
            $all_meta_keys
        );
        fputcsv($out, $headers);

        foreach ($posts as $id) {
            // Compute friendly fields
            $haunt_title = (function() use ($id){
                $hid = (int) get_post_meta($id,'_oha_haunt_id',true);
                if ($hid) {
                    $t = get_the_title($hid);
                    if (!$t) $t = 'ID ' . $hid;
                    return $t;
                }
                $free = (string) get_post_meta($id,'_oha_haunt_name_free',true);
                return $free !== '' ? $free : '';
            })();
            $created = get_post_time('Y-m-d H:i:s', true, $id);
            $status  = (string) get_post_meta($id,'_oha_status',true);
            $photo   = (function() use ($id){
                $url = (string) get_post_meta($id,'_oha_photo_url',true);
                if (!$url) {
                    $aid = (int) get_post_meta($id,'_oha_photo_id',true);
                    if ($aid) $url = wp_get_attachment_url($aid);
                }
                return $url ?: '';
            })();

            $row = [
                $id,
                $haunt_title,
                $created,
                $status,
                $photo,
            ];

            // Append all _oha_ meta values in consistent order
            foreach ($all_meta_keys as $k) {
                $val = get_post_meta($id, $k, true);
                if (is_array($val)) { $val = implode('; ', array_map('strval', $val)); }
                $row[] = $val;
            }

            fputcsv($out, $row);
        }

        exit;
    }


    public static function render_page() {
        $view_year   = self::get_view_year();
        $active_year = OHA_Awards_Settings::get_year();
        $all_years   = self::get_all_years();
        $table = new self();
        $table->prepare_items();

        echo '<div class="wrap"><h1>Awards Submissions</h1>';

        // Year picker — changes the view without touching the global setting
        echo '<form method="get" style="display:inline-block;margin-bottom:12px;">';
        echo '<input type="hidden" name="page" value="oha-awards-submissions" />';
        echo '<label style="font-weight:600;margin-right:6px;">Viewing year:</label>';
        echo '<select name="award_year_filter" onchange="this.form.submit()" style="margin-right:8px;">';
        foreach ($all_years as $y) {
            $sel = selected($y, $view_year, false);
            $label = esc_html($y);
            if ($y === $active_year) $label .= ' (active season)';
            echo '<option value="' . esc_attr($y) . '"' . $sel . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '<noscript><button type="submit" class="button">Go</button></noscript>';
        echo '</form>';

        if ($view_year !== $active_year) {
            echo '<p style="color:#b32d2e;font-weight:600;">You are viewing ' . esc_html($view_year) . ' (historical). The active season is ' . esc_html($active_year) . '. Public views and the submission form are still using ' . esc_html($active_year) . '.</p>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-left:12px;">';
        echo '<input type="hidden" name="action" value="oha_awards_export_csv" />';
        echo '<input type="hidden" name="award_year_filter" value="' . esc_attr($view_year) . '" />';
        submit_button('Export CSV (' . esc_html($view_year) . ')', 'secondary', 'submit', false);
        echo '</form>';

        $table->display();
        echo '</div>';
    }

    public function prepare_items() {
        $per_page = 20;
        $paged  = isset($_GET['paged'])  ? max(1, intval($_GET['paged']))  : 1;
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $year   = self::get_view_year();

        $meta_query = [
            'relation' => 'AND',
            ['key' => '_oha_award_year', 'value' => $year, 'compare' => '=', 'type' => 'NUMERIC'],
        ];
        if ($status) {
            $meta_query[] = ['key' => '_oha_status', 'value' => $status, 'compare' => '='];
        }

        $args = [
            'post_type'      => 'oha_award_entry',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => $meta_query,
        ];

        $q = new WP_Query($args);
        $this->items = $q->posts;
        $this->set_pagination_args([
            'total_items' => $q->found_posts,
            'per_page'    => $per_page,
            'total_pages' => $q->max_num_pages,
        ]);

        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
    }

    public function get_columns() {
        return [
            'cb'             => '<input type="checkbox" />',
            'id'             => 'ID',
            'haunt_name'     => 'Haunt Name',
            'owner'          => 'Owner (Name & Email)',
            'actor_name'     => 'Actor Name',
            'character_name' => 'Character Name',
            'reason'         => 'Reason',
            'download'       => 'Download Photo',
            
        ];
    }


public function column_haunt_name($item) {
    $post_id = $item->ID;
    $haunt_id = (int) get_post_meta($post_id, '_oha_haunt_id', true);
    if ($haunt_id) {
        $title = get_the_title($haunt_id);
        if (!$title) $title = 'ID ' . $haunt_id;
        return esc_html($title);
    }
    $free = get_post_meta($post_id, '_oha_haunt_name_free', true);
    return $free ? esc_html($free) . ' (not listed)' : '—';
}
public function column_owner($item) {
    $uid = (int) get_post_meta($item->ID, '_oha_owner_user_id', true);
    if ($uid) {
        $u = get_userdata($uid);
        if ($u) return esc_html($u->display_name) . ' &lt;' . esc_html($u->user_email) . '&gt;';
    }
    // fallback to submitter
    $name = get_post_meta($item->ID, '_oha_submitter_name', true);
    $email = get_post_meta($item->ID, '_oha_submitter_email', true);
    if ($name || $email) return esc_html($name) . ($email ? ' &lt;' . esc_html($email) . '&gt;' : '');
    return '—';
}
public function column_actor_name($item) { return esc_html(get_post_meta($item->ID, '_oha_actor_name', true)); }
public function column_character_name($item) { return esc_html(get_post_meta($item->ID, '_oha_character_name', true)); }
public function column_reason($item) { return wp_kses_post(get_post_meta($item->ID, '_oha_reason', true)); }
public function column_download($item) {
    $url = get_post_meta($item->ID, '_oha_photo_url', true);
    if (!$url) {
        $aid = (int) get_post_meta($item->ID, '_oha_photo_id', true);
        if ($aid) $url = wp_get_attachment_url($aid);
    }
    if ($url) {
        $fn = basename(parse_url($url, PHP_URL_PATH));
        $safe = esc_url($url);
        return '<a class="button" href="' . $safe . '" download="' . esc_attr($fn) . '">Download Photo</a>';
    }
    return '—';
}
public function column_more($item) {
    $pid = $item->ID;
    $fields = [
        'Submitter' => get_post_meta($pid,'_oha_submitter_name',true) . ' &lt;' . get_post_meta($pid,'_oha_submitter_email',true) . '&gt;',
        'Created'   => esc_html(get_post_time('Y-m-d H:i', true, $pid)),
        'Status'    => esc_html(get_post_meta($pid,'_oha_status',true)),
        'Haunt ID'  => esc_html(get_post_meta($pid,'_oha_haunt_id',true)),
        'Photo URL' => esc_html(get_post_meta($pid,'_oha_photo_url',true)),
    ];
    $rows = '';
    foreach ($fields as $k=>$v) {
        if ($v && $v !== ' &lt;&gt;') {
            $rows .= '<tr><th style="text-align:left;padding:4px 8px;width:140px;">'.esc_html($k).'</th><td style="padding:4px 8px;">'.$v.'</td></tr>';
        }
    }
    $html = '<details><summary>More Details</summary><table class="widefat striped" style="margin-top:8px">'.$rows.'</table></details>';
    return $html;
}


}
