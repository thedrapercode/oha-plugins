<?php
if (!defined('ABSPATH')) exit;

class OHA_Awards_Front_Admin {

    public static function init() {
        add_shortcode('oha_awards_admin', [__CLASS__, 'render']);
        add_shortcode('oha_award_admin_card', [__CLASS__, 'render_cards']);
        add_action('wp_ajax_oha_awards_admin_refresh', [__CLASS__, 'ajax_refresh']);
        add_action('wp_ajax_nopriv_oha_awards_admin_refresh', [__CLASS__, 'ajax_refresh']);
        add_action('admin_post_oha_awards_download_photo', [__CLASS__, 'download_photo']);
    }

    public static function render($atts = []) {
        if (!current_user_can('read')) return '';
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        if (function_exists('nocache_headers')) { nocache_headers(); }

        $export_url = wp_nonce_url(admin_url('admin-post.php?action=oha_awards_export_csv'),'oha_awards_export_csv');

        ob_start();
        echo '<div class="oha-awards-admin-controls" style="margin-bottom:10px;display:flex;gap:.5rem;align-items:center;">';
        echo '<input id="oha-awards-admin-search" type="search" placeholder="Search haunt, owner, actor, character, email..." style="max-width:340px;width:100%;padding:6px 10px">';
        echo '<a class="button button-primary" href="'.esc_url($export_url).'">Export CSV</a>';
        echo '</div>';
        echo '<div id="oha-awards-admin-container" style="overflow-x:auto">Loading…</div>';

        $ajax = esc_url(admin_url('admin-ajax.php?action=oha_awards_admin_refresh'));
        echo '<script>document.addEventListener("DOMContentLoaded",function(){fetch("'.$ajax.'").then(r=>r.text()).then(html=>{var c=document.getElementById("oha-awards-admin-container"); if(c) c.innerHTML=html;});});</script>';
        echo '<script>document.addEventListener("input",function(e){if(e.target && e.target.id==="oha-awards-admin-search"){var q=e.target.value.toLowerCase();var t=document.querySelector("#oha-awards-admin-container table");if(!t)return;Array.from(t.querySelectorAll("tbody tr")).forEach(function(tr){var text=tr.innerText.toLowerCase();tr.style.display=text.indexOf(q)>-1?"":"none";});}});</script>';

        return ob_get_clean();
    }

    public static function ajax_refresh() {
        if (!current_user_can('read')) wp_die();
        $year = OHA_Awards_Settings::get_year();
        $q = new WP_Query([
            'post_type'      => 'oha_award_entry',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [['key'=>'_oha_award_year','value'=>$year,'compare'=>'=','type'=>'NUMERIC']],
        ]);

        ob_start();
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>Haunt Name</th>';
        echo '<th>Owner (Name &amp; Email)</th>';
        echo '<th>Actor Name</th>';
        echo '<th>Character Name</th>';
        echo '<th>Reason</th>';
        echo '<th>Download Photo</th>';
        echo '</tr></thead><tbody>';

        if ($q->have_posts()) {
            while ($q->have_posts()) { $q->the_post();
                $id = get_the_ID();
                $haunt_id        = (int) get_post_meta($id, '_oha_haunt_id', true);
                $haunt_name_free = (string) get_post_meta($id, '_oha_haunt_name_free', true);
                $haunt_title     = $haunt_id ? (get_the_title($haunt_id) ?: ('ID '.$haunt_id)) : ($haunt_name_free !== '' ? $haunt_name_free.' (not listed)' : '—');
                $owner_id    = (int) get_post_meta($id, '_oha_owner_user_id', true);
                $owner_name  = $owner_email = '';
                if ($owner_id && ($u = get_userdata($owner_id))) { $owner_name = $u->display_name; $owner_email = $u->user_email; }
                if (!$owner_name)  $owner_name  = (string) get_post_meta($id, '_oha_submitter_name', true);
                if (!$owner_email) $owner_email = (string) get_post_meta($id, '_oha_submitter_email', true);
                $actor_name = (string) get_post_meta($id, '_oha_actor_name', true);
                $character  = (string) get_post_meta($id, '_oha_character_name', true);
                $reason     = (string) get_post_meta($id, '_oha_reason', true);
                $photo_url  = (string) get_post_meta($id, '_oha_photo_url', true);
                if (!$photo_url) {
                    $aid = (int) get_post_meta($id,'_oha_photo_id',true);
                    if ($aid) $photo_url = wp_get_attachment_url($aid);
                }
                $download_href = $photo_url ? wp_nonce_url(admin_url('admin-post.php?action=oha_awards_download_photo&id='.$id), 'oha_awards_download_'.$id) : '';

                echo '<tr>';
                echo '<td>'.esc_html($haunt_title).'</td>';
                echo '<td>'.esc_html($owner_name.' / '.$owner_email).'</td>';
                echo '<td>'.esc_html($actor_name).'</td>';
                echo '<td>'.esc_html($character).'</td>';
                echo '<td>'.esc_html(wp_strip_all_tags($reason)).'</td>';
                echo '<td>';
                if ($download_href) echo '<a href="'.esc_url($download_href).'" class="button">Download</a>'; else echo '—';
                echo '</td>';
                echo '</tr>';
            }
            wp_reset_postdata();
        }

        echo '</tbody></table>';
        echo ob_get_clean();
        wp_die();
    }

    public static function download_photo() {
        if (!is_user_logged_in()) wp_die('Unauthorized');
        $id = intval($_GET['id'] ?? 0);
        if (!$id) wp_die('Missing id');
        if (isset($_GET['_wpnonce']) && !wp_verify_nonce($_GET['_wpnonce'], 'oha_awards_download_'.$id)) {
            wp_die('Bad nonce');
        }
        $url = (string) get_post_meta($id, '_oha_photo_url', true);
        $aid = (int) get_post_meta($id, '_oha_photo_id', true);
        if (!$url && $aid) $url = wp_get_attachment_url($aid);
        if (!$url) wp_die('No photo.');

        $actor = (string) get_post_meta($id, '_oha_actor_name', true);
        $haunt = (function() use ($id){
            $hid = (int) get_post_meta($id,'_oha_haunt_id',true);
            if ($hid) { $t = get_the_title($hid); if (!$t) $t = 'ID '.$hid; return $t; }
            $free = (string) get_post_meta($id,'_oha_haunt_name_free',true);
            return $free !== '' ? $free : '';
        })();
        $actor = $actor ? $actor : 'Unknown Actor';
        $haunt = $haunt ? $haunt : 'Unknown Haunt';

        $path = wp_parse_url($url, PHP_URL_PATH);
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $base = sanitize_file_name($actor . ' - ' . $haunt);
        if (!$base) { $base = 'Download'; }
        $filename = $base . ($ext ? ('.'.$ext) : '.jpg');

        $file_path = false;
        if ($aid) $file_path = get_attached_file($aid);
        if (!$file_path) {
            $uploads = wp_get_upload_dir();
            $rel = ltrim(str_replace($uploads['baseurl'], '', $url), '/');
            $try = trailingslashit($uploads['basedir']).$rel;
            if (file_exists($try)) $file_path = $try;
        }
        if ($file_path && file_exists($file_path)) {
            $mime = wp_check_filetype($file_path)['type'] ?: 'image/jpeg';
            header('Content-Type: '.$mime);
            header('Content-Disposition: attachment; filename="'.basename($filename).'"');
            header('Content-Length: '.filesize($file_path));
            readfile($file_path);
            exit;
        } else {
            wp_redirect($url);
            exit;
        }
    }

    public static function render_cards($atts = []) {
        if (!current_user_can('read')) return '';
        $year = OHA_Awards_Settings::get_year();
        $q = new WP_Query([
            'post_type'      => 'oha_award_entry',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [['key'=>'_oha_award_year','value'=>$year,'compare'=>'=','type'=>'NUMERIC']],
        ]);
        ob_start();
        echo '<div style="margin:8px 0;"><input id="oha-awards-card-search" type="search" placeholder="Search..." style="max-width:340px;width:100%;padding:6px 10px"></div>';
        echo '<div class="oha-awards-cards" style="color:#111" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;">';
        if ($q->have_posts()) {
            while ($q->have_posts()) { $q->the_post();
                $id   = get_the_ID();
                $actor = esc_html(get_post_meta($id,'_oha_actor_name',true));
                $character = esc_html(get_post_meta($id,'_oha_character_name',true));
                $reason = esc_html( wp_strip_all_tags( get_post_meta($id,'_oha_reason',true) ) );
                $haunt_id = (int) get_post_meta($id,'_oha_haunt_id',true);
                $haunt_free = (string) get_post_meta($id,'_oha_haunt_name_free',true);
                $haunt = $haunt_id ? (get_the_title($haunt_id) ?: ('ID '.$haunt_id)) : ($haunt_free !== '' ? $haunt_free.' (not listed)' : '—');
                $owner = (string) get_post_meta($id,'_oha_submitter_name',true);
                $owner_email = (string) get_post_meta($id,'_oha_submitter_email',true);
                $photo = (string) get_post_meta($id,'_oha_photo_url',true);
                if (!$photo) { $aid = (int) get_post_meta($id,'_oha_photo_id',true); if ($aid) $photo = wp_get_attachment_url($aid); }
                $photo_tag = $photo ? '<img src="'.esc_url($photo).'" alt="" style="width:100%;height:auto;border-radius:12px;object-fit:cover;max-height:200px;">' : '<div style="width:100%;height:200px;background:#f4f4f4;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#666;">No Photo</div>';
                echo '<div class="oha-card" style="border:1px solid #e3e3e3;border-radius:12px;padding:12px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.03)">';
                echo '<div style="display:grid;grid-template-columns:140px 1fr;gap:12px;">';
                echo '<div>'.$photo_tag.'</div>';
                echo '<div>';
                echo '<div style="display:flex;gap:8px;flex-wrap:wrap;"><div><strong>Actor:</strong> '.$actor.'</div><div><strong>Character:</strong> '.$character.'</div></div>';
                echo '<div style="margin-top:6px;"><strong>Haunt:</strong> '.esc_html($haunt).'</div>';
                echo '</div>';
                echo '</div>';
                echo '<div style="margin-top:10px;"><strong>Reason</strong><div style="border:1px solid #eee;border-radius:8px;padding:8px;">'.$reason.'</div></div>';
                echo '<div style="margin-top:10px;"><strong>Owner/Email</strong><div>'.esc_html($owner).' &lt;'.esc_html($owner_email).'&gt;</div></div>'; $dl = wp_nonce_url(admin_url('admin-post.php?action=oha_awards_download_photo&id='.$id), 'oha_awards_download_'.$id); echo '<div style="margin-top:10px;"><a class="button" href="'.esc_url($dl).'">Download Photo</a></div>';
                echo '</div>';
            }
            wp_reset_postdata();
        }
        echo '</div>';
        echo '<script>const s=document.getElementById("oha-awards-card-search");if(s){s.addEventListener("input",function(){var q=this.value.toLowerCase();document.querySelectorAll(".oha-awards-cards .oha-card").forEach(function(card){card.style.display=(card.innerText.toLowerCase().indexOf(q)>-1)?"":"none";});});}</script>';
        return ob_get_clean();
    }
}
