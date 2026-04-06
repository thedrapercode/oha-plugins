<?php
/* OHA Haunt Discounts (embedded lib) */
if (!defined('ABSPATH')) exit;

if (!class_exists('OHA_Haunt_Discounts')) {
class OHA_Haunt_Discounts {

    public static function init() {
        // Meta box and save
        add_action('add_meta_boxes', array(__CLASS__,'add_meta_box'));
        add_action('save_post_gd_place', array(__CLASS__,'save_meta'), 10, 3);

        // Shortcodes only (no auto inject)
        add_shortcode('oha_haunt_discount', array(__CLASS__,'shortcode'));
        add_shortcode('oha_haunt_discounts_admin', array(__CLASS__,'admin_shortcode'));

        // REST for inline editing and bulk actions
        add_action('rest_api_init', array(__CLASS__,'register_rest'));
    }

    public static function user_can_manage() {
        if (current_user_can('manage_options') || current_user_can('manage_oha_members') || current_user_can('read_oha_board_hub')) return true;
        return false;
    }

    /* ===== Admin Meta ===== */

    public static function add_meta_box() {
        add_meta_box('oha_haunt_discount_box','OHA Discount Announcement', array(__CLASS__,'render_meta_box'),'gd_place','side','high');
    }

    public static function render_meta_box($post) {
        wp_nonce_field('oha_haunt_discount_save','oha_haunt_discount_nonce');
        $active  = get_post_meta($post->ID,'_oha_disc_active',true);
        $rate    = get_post_meta($post->ID,'_oha_disc_rate',true);
        $message = get_post_meta($post->ID,'_oha_disc_message',true);
        $link    = get_post_meta($post->ID,'_oha_disc_link',true);
        $start   = get_post_meta($post->ID,'_oha_disc_start',true);
        $end     = get_post_meta($post->ID,'_oha_disc_end',true);
        $bg      = get_post_meta($post->ID,'_oha_disc_bg',true) ?: '#111111';
        $color   = get_post_meta($post->ID,'_oha_disc_color',true) ?: '#ffffff';
        ?>
        <p><label><input type="checkbox" name="oha_disc_active" value="1" <?php checked($active,'1'); ?>> Enable discount bar</label></p>
        <p><label><strong>Discount Rate</strong><br><input type="text" name="oha_disc_rate" value="<?php echo esc_attr($rate); ?>" placeholder="e.g., 10% OFF" style="width:100%;"></label></p>
        <p><label><strong>Message</strong><br><textarea name="oha_disc_message" rows="3" style="width:100%;" placeholder="Short sentence"><?php echo esc_textarea($message); ?></textarea></label></p>
        <p><label><strong>Call-to-Action Link (optional)</strong><br><input type="url" name="oha_disc_link" value="<?php echo esc_attr($link); ?>" placeholder="/join or https://…" style="width:100%;"></label></p>
        <p><label><strong>Start Date (optional)</strong><br><input type="date" name="oha_disc_start" value="<?php echo esc_attr($start); ?>" style="width:100%;"></label></p>
        <p><label><strong>End Date (optional)</strong><br><input type="date" name="oha_disc_end" value="<?php echo esc_attr($end); ?>" style="width:100%;"></label></p>
        <p><label><strong>Bar Background</strong><br><input type="text" name="oha_disc_bg" value="<?php echo esc_attr($bg); ?>" placeholder="#111111" style="width:100%;"></label></p>
        <p><label><strong>Text Color</strong><br><input type="text" name="oha_disc_color" value="<?php echo esc_attr($color); ?>" placeholder="#ffffff" style="width:100%;"></label></p>
        <p style="font-size:12px;opacity:.8;">Tip: Leave dates empty for "always on". Use the shortcode on the page where you want it to display.</p>
        <?php
    }

    public static function save_meta($post_id,$post,$update) {
        if (!isset($_POST['oha_haunt_discount_nonce']) || !wp_verify_nonce($_POST['oha_haunt_discount_nonce'],'oha_haunt_discount_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post',$post_id)) return;
        $active  = isset($_POST['oha_disc_active']) ? '1':'0';
        $rate    = isset($_POST['oha_disc_rate']) ? sanitize_text_field($_POST['oha_disc_rate']) : '';
        $message = isset($_POST['oha_disc_message']) ? wp_kses_post($_POST['oha_disc_message']) : '';
        $link    = isset($_POST['oha_disc_link']) ? esc_url_raw($_POST['oha_disc_link']) : '';
        $start   = isset($_POST['oha_disc_start']) ? sanitize_text_field($_POST['oha_disc_start']) : '';
        $end     = isset($_POST['oha_disc_end']) ? sanitize_text_field($_POST['oha_disc_end']) : '';
        $bg      = isset($_POST['oha_disc_bg']) ? sanitize_text_field($_POST['oha_disc_bg']) : '';
        $color   = isset($_POST['oha_disc_color']) ? sanitize_text_field($_POST['oha_disc_color']) : '';
        update_post_meta($post_id,'_oha_disc_active',$active);
        // OHA Featured Sync hook
        $__on = ($active==='1' || $active===1 || $active==='yes' || $active==='true' || $active===true);
        do_action($__on ? 'oha_discount_activated' : 'oha_discount_deactivated', $post_id);
        update_post_meta($post_id,'_oha_disc_rate',$rate);
        update_post_meta($post_id,'_oha_disc_message',$message);
        update_post_meta($post_id,'_oha_disc_link',$link);
        update_post_meta($post_id,'_oha_disc_start',$start);
        update_post_meta($post_id,'_oha_disc_end',$end);
        update_post_meta($post_id,'_oha_disc_bg',$bg);
        update_post_meta($post_id,'_oha_disc_color',$color);
    }

    /* ===== Frontend rendering (shortcode-only display) ===== */

    public static function get_discount($post_id) {
        $data = array(
            'active'  => get_post_meta($post_id,'_oha_disc_active',true) === '1',
            'rate'    => get_post_meta($post_id,'_oha_disc_rate',true),
            'message' => get_post_meta($post_id,'_oha_disc_message',true),
            'link'    => get_post_meta($post_id,'_oha_disc_link',true),
            'start'   => get_post_meta($post_id,'_oha_disc_start',true),
            'end'     => get_post_meta($post_id,'_oha_disc_end',true),
            'bg'      => get_post_meta($post_id,'_oha_disc_bg',true) ?: '#111111',
            'color'   => get_post_meta($post_id,'_oha_disc_color',true) ?: '#ffffff',
        );
        $today = current_time('Y-m-d');
        if (!empty($data['start']) && $today < $data['start']) $data['active'] = false;
        if (!empty($data['end']) && $today > $data['end']) $data['active'] = false;
        if (empty($data['rate']) && empty($data['message'])) $data['active'] = false;
        return $data;
    }

    public static function render_bar_html($data,$id_suffix) {
        $id = 'oha-discount-bar-' . $id_suffix;
        $msg = trim($data['message']);
        $rate = trim($data['rate']);
        if ($msg === '' && $rate !== '') $msg = sprintf('Special: %s', esc_html($rate));
        ob_start(); ?>
        <div id="<?php echo esc_attr($id); ?>" class="oha-discount-bar" style="background:<?php echo esc_attr($data['bg']); ?>;color:<?php echo esc_attr($data['color']); ?>;">
            <div class="oha-bar-inner">
                <?php if ($rate !== ''): ?><strong class="oha-bar-rate"><?php echo esc_html($rate); ?></strong><?php endif; ?>
                <?php if ($msg !== ''): ?><span class="oha-bar-text"><?php echo wp_kses_post($msg); ?></span><?php endif; ?>
                <?php if (!empty($data['link'])): ?><a class="oha-bar-btn" href="<?php echo esc_url($data['link']); ?>">Learn more</a><?php endif; ?>
            </div>
        </div>
        <style>
            .oha-discount-bar{position:static;margin:12px 0;z-index:auto}
            .oha-bar-inner{max-width:1200px;margin:0 auto;padding:10px 16px;display:flex;gap:12px;align-items:center;justify-content:center;flex-wrap:wrap}
            .oha-bar-rate{font-weight:700}
            .oha-bar-text{font-weight:600}
            .oha-bar-btn{display:inline-block;padding:8px 12px;border:1px solid currentColor;text-decoration:none;border-radius:6px}
            .oha-discount-bar a{color:inherit; text-decoration:underline;}
            @media (max-width: 600px){ .oha-bar-inner{justify-content:space-between} }
        </style>
        <?php
        return ob_get_clean();
    }

    public static function shortcode($atts) {
        $a = shortcode_atts(array('id'=>''), $atts);
        $post_id = 0;
        if (!empty($a['id'])) $post_id = absint($a['id']);
        elseif (is_singular('gd_place')) $post_id = get_the_ID();
        if (!$post_id) return '';
        $data = self::get_discount($post_id);
        if (!$data['active']) return '';
        return self::render_bar_html($data, $post_id.'-sc');
    }

    /* ===== Board Hub Admin (frontend table) ===== */


    public static function admin_shortcode() {
        if (!self::user_can_manage()) return '<div class="notice notice-error"><p>Board access only.</p></div>';
        $qstr = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $paged = max(1, isset($_GET['pg']) ? intval($_GET['pg']) : 1);
        $per_page = 20;

$active = isset($_GET['active']) ? sanitize_text_field(wp_unslash($_GET['active'])) : '';
$sort   = isset($_GET['sort']) ? sanitize_text_field(wp_unslash($_GET['sort'])) : '';



        $args = array(
            'post_type'      => 'gd_place',
            'post_status'    => array('publish','pending','draft'),
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );
        if ($qstr !== '') {
            $args['s'] = $qstr;
        }

// Filter by active status if requested
if ($active === '1') {
    $args['meta_query'] = array(
        array(
            'key'     => '_oha_disc_active',
            'value'   => '1',
            'compare' => '='
        )
    );
} elseif ($active === '0') {
    $args['meta_query'] = array(
        'relation' => 'OR',
        array(
            'key'     => '_oha_disc_active',
            'value'   => '0',
            'compare' => '='
        ),
        // Treat missing meta as inactive
        array(
            'key'     => '_oha_disc_active',
            'compare' => 'NOT EXISTS'
        )
    );
}

// Optional: sort to show active first
if ($sort === 'active') {
    $args['meta_key'] = '_oha_disc_active';
    $args['meta_type'] = 'NUMERIC';
    $args['orderby'] = array('meta_value' => 'DESC', 'title' => 'ASC');
}



        $q = new WP_Query($args);

        $bulk_url   = esc_url_raw( rest_url('oha/v1/haunt-discounts/bulk-update') );
        $single_url = esc_url_raw( rest_url('oha/v1/haunt-discounts/update') );
        $nonce      = wp_create_nonce('wp_rest');

        ob_start(); ?>
        <div class="oha-disc-admin">
            <h2>Haunt Discounts</h2>
            <form method="get" style="margin:8px 0; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <?php
                    // Explicit whitelist — never echo arbitrary $_GET keys as form field names.
                    // 'q', 'active', 'sort' are visible form fields and submit themselves.
                    // 'page' and 'tab' preserve the WP admin page context; 'pg' preserves pagination.
                    foreach (['page', 'tab', 'pg'] as $k) {
                        if (isset($_GET[$k]) && !is_array($_GET[$k])) {
                            printf('<input type="hidden" name="%s" value="%s">', esc_attr($k), esc_attr(sanitize_text_field(wp_unslash($_GET[$k]))));
                        }
                    }
                ?>
                <input type="text" name="q" value="<?php echo esc_attr($qstr); ?>" placeholder="Search haunts…" />

<select name="active">
    <option value="" <?php selected($active,''); ?>>All</option>
    <option value="1" <?php selected($active,'1'); ?>>Active only</option>
    <option value="0" <?php selected($active,'0'); ?>>Inactive only</option>
</select>
<label style="margin-left:10px;">
    <input type="checkbox" name="sort" value="active" <?php checked($sort,'active'); ?>>
    Active first
</label>

                <button class="button">Search</button>

<?php
    $reset_url = remove_query_arg(array('q','pg','active','sort'));
    $reset_url = add_query_arg(array(
        'page' => isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '',
        'tab'  => isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : ''
    ), $reset_url);
?>
<a class="button" href="<?php echo esc_url($reset_url); ?>">Reset</a>
            </form>

            <div style="display:flex;align-items:center;gap:8px;margin:10px 0 6px;">
                <strong style="font-size:13px;">Bulk:</strong>
                <button class="button" id="oha-bulk-activate">Set Active</button>
                <button class="button" id="oha-bulk-deactivate">Set Inactive</button>
                <span id="oha-bulk-status" style="font-size:13px;color:#3c763d;display:none;"></span>
            </div>

            <table class="widefat striped" id="oha-disc-table">
                <thead><tr>
                    <th style="width:28px;"><input type="checkbox" id="oha-disc-select-all" title="Select all on this page"></th>
                    <th>Haunt</th>
                    <th>Active</th>
                    <th>Rate</th>
                    <th>Message</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Link</th>
                    <th>Colors</th>
                    <th>Actions</th>
                </tr></thead>
                <tbody>
                <?php if ($q->have_posts()): while ($q->have_posts()): $q->the_post(); $pid=get_the_ID();
                    $disc_active = get_post_meta($pid,'_oha_disc_active',true)==='1'?'1':'0';
                    $rate = get_post_meta($pid,'_oha_disc_rate',true);
                    $message = get_post_meta($pid,'_oha_disc_message',true);
                    $link = get_post_meta($pid,'_oha_disc_link',true);
                    $start = get_post_meta($pid,'_oha_disc_start',true);
                    $end = get_post_meta($pid,'_oha_disc_end',true);
                    $bg = get_post_meta($pid,'_oha_disc_bg',true) ?: '#111111';
                    $color = get_post_meta($pid,'_oha_disc_color',true) ?: '#ffffff';
                ?>
                    <tr data-id="<?php echo esc_attr($pid); ?>">
                        <td><input type="checkbox" class="oha-disc-cb" value="<?php echo esc_attr($pid); ?>"></td>
                        <td><a href="<?php echo get_edit_post_link($pid); ?>"><?php echo esc_html(get_the_title()); ?></a></td>
                        <td><input type="checkbox" class="oha-field" data-k="_oha_disc_active" <?php checked($disc_active,'1'); ?>></td>
                        <td><input type="text" class="oha-field" data-k="_oha_disc_rate" value="<?php echo esc_attr($rate); ?>" placeholder="e.g., 10% OFF"></td>
                        <td><input type="text" class="oha-field" data-k="_oha_disc_message" value="<?php echo esc_attr($message); ?>" placeholder="Message"></td>
                        <td><input type="date" class="oha-field" data-k="_oha_disc_start" value="<?php echo esc_attr($start); ?>"></td>
                        <td><input type="date" class="oha-field" data-k="_oha_disc_end" value="<?php echo esc_attr($end); ?>"></td>
                        <td><input type="url" class="oha-field" data-k="_oha_disc_link" value="<?php echo esc_attr($link); ?>" placeholder="https://"></td>
                        <td>
                            <input type="text" class="oha-field" data-k="_oha_disc_bg" value="<?php echo esc_attr($bg); ?>" style="width:90px" title="Background">
                            <input type="text" class="oha-field" data-k="_oha_disc_color" value="<?php echo esc_attr($color); ?>" style="width:90px" title="Text color">
                        </td>
                        <td><a class="button" href="<?php echo esc_url(get_permalink($pid)); ?>" target="_blank">View</a></td>
                    </tr>
                <?php endwhile; wp_reset_postdata(); else: ?>
                    <tr><td colspan="10"><em>No haunts found.</em></td></tr>
                <?php endif; ?>
                </tbody>
            </table>

            <?php
            if ($q->max_num_pages > 1):
                $current = $paged;
                $total = intval($q->max_num_pages);
                echo '<p style="margin-top:10px;">';
                if ($current > 1) {
                    $prev = add_query_arg(array('pg'=>$current-1));
                    echo '<a class="button" href="'.esc_url($prev).'">&laquo; Prev</a> ';
                }
                for ($i=1;$i<=$total;$i++){
                    $url = add_query_arg(array('pg'=>$i));
                    $style = ($i==$current)?'style="font-weight:700"':'';
                    echo '<a class="button button-small" '.$style.' href="'.esc_url($url).'">'.$i.'</a> ';
                }
                if ($current < $total) {
                    $next = add_query_arg(array('pg'=>$current+1));
                    echo '<a class="button" href="'.esc_url($next).'">Next &raquo;</a>';
                }
                echo '</p>';
            endif; ?>

        </div>
        <style>
            .oha-disc-admin input[type=text],
            .oha-disc-admin input[type=url],
            .oha-disc-admin input[type=date]{width:100%; max-width:220px}
            .oha-disc-admin table td{vertical-align:top}
            .oha-disc-admin form input[type=text]{min-width:260px}
        </style>
        <script>
        (function(){
            var singleUrl = <?php echo json_encode($single_url); ?>;
            var bulkUrl   = <?php echo json_encode($bulk_url); ?>;
            var nonce     = <?php echo json_encode($nonce); ?>;

            // ---- Single inline edit ----
            function postSingle(row, key, val){
                var pid = row.getAttribute('data-id');
                return fetch(singleUrl, {
                    method:'POST',
                    headers:{'Content-Type':'application/json','X-WP-Nonce': nonce},
                    body: JSON.stringify({post_id: pid, key: key, value: val})
                }).then(function(r){ return r.json(); });
            }
            document.querySelectorAll('.oha-disc-admin .oha-field').forEach(function(el){
                el.addEventListener(el.type==='checkbox'?'change':'blur', function(){
                    var row = el.closest('tr');
                    var key = el.getAttribute('data-k');
                    var val = (el.type==='checkbox') ? (el.checked?'1':'0') : el.value;
                    el.style.opacity = '.5';
                    postSingle(row, key, val).then(function(){
                        el.style.opacity = '';
                    });
                });
            });

            // ---- Select all ----
            var selectAll = document.getElementById('oha-disc-select-all');
            if (selectAll) {
                selectAll.addEventListener('change', function(){
                    document.querySelectorAll('.oha-disc-cb').forEach(function(cb){ cb.checked = selectAll.checked; });
                });
            }

            // ---- Bulk actions ----
            function getCheckedIds() {
                var ids = [];
                document.querySelectorAll('.oha-disc-cb:checked').forEach(function(cb){ ids.push(parseInt(cb.value, 10)); });
                return ids;
            }

            function bulkAction(value) {
                var ids = getCheckedIds();
                if (!ids.length) { alert('Select at least one haunt first.'); return; }
                var status = document.getElementById('oha-bulk-status');
                status.textContent = 'Saving...';
                status.style.display = 'inline';
                fetch(bulkUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-WP-Nonce': nonce},
                    body: JSON.stringify({ids: ids, value: value})
                })
                .then(function(r){ return r.json(); })
                .then(function(resp){
                    if (resp && resp.ok) {
                        status.textContent = resp.updated + ' haunt(s) updated.';
                        // Update active checkboxes in the table rows that were changed
                        ids.forEach(function(id){
                            var row = document.querySelector('#oha-disc-table tr[data-id="' + id + '"]');
                            if (!row) return;
                            var cb = row.querySelector('.oha-field[data-k="_oha_disc_active"]');
                            if (cb) cb.checked = (value === '1');
                        });
                        // Deselect all row checkboxes
                        document.querySelectorAll('.oha-disc-cb').forEach(function(cb){ cb.checked = false; });
                        if (selectAll) selectAll.checked = false;
                        setTimeout(function(){ status.style.display = 'none'; }, 3000);
                    } else {
                        status.textContent = 'Error saving.';
                        status.style.color = '#a00';
                    }
                });
            }

            var btnActivate   = document.getElementById('oha-bulk-activate');
            var btnDeactivate = document.getElementById('oha-bulk-deactivate');
            if (btnActivate)   btnActivate.addEventListener('click',   function(){ bulkAction('1'); });
            if (btnDeactivate) btnDeactivate.addEventListener('click', function(){ bulkAction('0'); });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

public static function register_rest() {
        // Single field update
        register_rest_route('oha/v1','/haunt-discounts/update', array(
            'methods'  => 'POST',
            'permission_callback' => function(){ return self::user_can_manage(); },
            'callback' => function($req){
                $p = $req->get_json_params();
                $post_id = isset($p['post_id']) ? absint($p['post_id']) : 0;
                $key = isset($p['key']) ? sanitize_key($p['key']) : '';
                $val = isset($p['value']) ? wp_unslash($p['value']) : '';
                if (!$post_id || !$key) return new WP_Error('bad_request','Missing parameters',array('status'=>400));
                if (!current_user_can('edit_post',$post_id) && !current_user_can('manage_options') && !current_user_can('read_oha_board_hub') && !current_user_can('manage_oha_members')) {
                    return new WP_Error('forbidden','Insufficient permissions',array('status'=>403));
                }
                switch($key){
                    case '_oha_disc_active': $val = $val==='1'?'1':'0'; break;
                    case '_oha_disc_rate':
                    case '_oha_disc_bg':
                    case '_oha_disc_color':
                    case '_oha_disc_start':
                    case '_oha_disc_end':
                        $val = sanitize_text_field($val); break;
                    case '_oha_disc_message':
                        $val = wp_kses_post($val); break;
                    case '_oha_disc_link':
                        $val = esc_url_raw($val); break;
                    default: return new WP_Error('bad_key','Invalid key',array('status'=>400));
                }
                update_post_meta($post_id,$key,$val);
                if ($key === '_oha_disc_active') {
                    $__on = ($val==='1' || $val===1 || $val==='yes' || $val==='true' || $val===true);
                    do_action($__on ? 'oha_discount_activated' : 'oha_discount_deactivated', $post_id);
                }
                return array('ok'=>true);
            }
        ));

        // Bulk active/inactive toggle
        register_rest_route('oha/v1', '/haunt-discounts/bulk-update', array(
            'methods'             => 'POST',
            'permission_callback' => function(){ return self::user_can_manage(); },
            'callback'            => function($req){
                $p    = $req->get_json_params();
                $ids  = isset($p['ids']) ? array_map('absint', (array) $p['ids']) : array();
                $val  = (isset($p['value']) && $p['value'] === '1') ? '1' : '0';
                if (empty($ids)) return new WP_Error('bad_request', 'No IDs provided', array('status' => 400));
                $updated = 0;
                foreach ($ids as $id) {
                    if (!$id || get_post_type($id) !== 'gd_place') continue;
                    update_post_meta($id, '_oha_disc_active', $val);
                    $__on = ($val === '1');
                    do_action($__on ? 'oha_discount_activated' : 'oha_discount_deactivated', $id);
                    $updated++;
                }
                return array('ok' => true, 'updated' => $updated);
            }
        ));
    }
}
OHA_Haunt_Discounts::init();
}
