<?php
if (!defined('ABSPATH')) exit;

class OHA_Awards_Bulk_Certificates {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_oha_awards_import_zip', [__CLASS__, 'handle_zip']);
        add_action('admin_post_oha_awards_import_folder', [__CLASS__, 'handle_folder']);
    }

    public static function menu() {
        add_submenu_page('oha-awards','Bulk Certificates','Bulk Certificates','manage_options','oha-awards-bulk',[__CLASS__,'render']);
    }

    public static function render() {
        if (!current_user_can('manage_options')) return;
        $uploads = wp_get_upload_dir();
        $folder = trailingslashit($uploads['basedir']).'oha-awards-import';
        echo '<div class="wrap"><h1>Bulk Certificates</h1>';
        echo '<p>Upload a ZIP of certificates or place files into <code>'.$folder.'</code> via SFTP, then run the import.</p>';
        echo '<h2>Option A: Upload ZIP</h2>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" enctype="multipart/form-data">';
        echo '<input type="hidden" name="action" value="oha_awards_import_zip">';
        wp_nonce_field('oha_awards_import');
        echo '<input type="file" name="zipfile" accept=".zip" required> ';
        submit_button('Import ZIP', 'primary', 'submit', false);
        echo '</form>';

        echo '<h2>Option B: Import From Folder</h2>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="oha_awards_import_folder">';
        wp_nonce_field('oha_awards_import');
        submit_button('Scan & Import from /uploads/oha-awards-import', 'secondary', 'submit', false);
        echo '</form>';

        echo '<p><strong>File naming supported:</strong></p>';
        echo '<ul><li><code>{entry_id}.jpg|png|pdf</code> (recommended)</li><li><code>{Actor Name} - {Haunt Name}.jpg|png|pdf</code></li></ul>';
        echo '</div>';
    }

    private static function import_file($filepath) {
        $fname = basename($filepath);
        $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
        $mime = wp_check_filetype($filepath)['type'];
        $entry_id = 0;
        if (preg_match('/^(\d+)\.(jpg|jpeg|png|pdf)$/i', $fname, $m)) {
            $entry_id = intval($m[1]);
        } else {
            // Try "Actor - Haunt" match
            $base = preg_replace('/\.(jpg|jpeg|png|pdf)$/i','',$fname);
            $parts = array_map('trim', explode('-', $base, 2));
            if (count($parts) === 2) {
                $actor = strtolower($parts[0]);
                $haunt = strtolower($parts[1]);
                // find by meta combination scoped to the active convention year
                $q = new WP_Query([
                    'post_type' => 'oha_award_entry',
                    'posts_per_page' => 1,
                    'meta_query' => [
                        'relation' => 'AND',
                        ['key'=>'_oha_award_year','value'=>OHA_Awards_Settings::get_year(),'compare'=>'=','type'=>'NUMERIC'],
                        ['key'=>'_oha_actor_name','value'=>$actor,'compare'=>'LIKE'],
                        ['key'=>'_oha_haunt_name_free','value'=>$haunt,'compare'=>'LIKE'],
                    ],
                    'fields' => 'ids',
                ]);
                if (!empty($q->posts)) $entry_id = (int) $q->posts[0];
                wp_reset_postdata();
            }
        }
        if (!$entry_id) return ['ok'=>false, 'msg'=>"No match for $fname"];

        // Attach file to media library
        $attach_id = self::sideload_file($filepath);
        if (is_wp_error($attach_id)) return ['ok'=>false, 'msg'=>"Attach failed for $fname: ".$attach_id->get_error_message()];

        // Save to entry meta and mark completed
        if ($mime === 'application/pdf') {
            update_post_meta($entry_id, '_oha_cert_pdf_id', $attach_id);
        } else {
            update_post_meta($entry_id, '_oha_cert_lowres_id', $attach_id);
        }
        // Mark completed and notify owner (only fires if not already completed)
        $prev = (string) get_post_meta($entry_id, '_oha_status', true);
        update_post_meta($entry_id, '_oha_status', 'completed');
        if ($prev !== 'completed') {
            OHA_Awards_Email::send_cert_ready($entry_id);
        }

        return ['ok'=>true, 'msg'=>"Imported to entry #$entry_id"];
    }

    private static function sideload_file($filepath) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $file = [
            'name' => basename($filepath),
            'type' => wp_check_filetype($filepath)['type'],
            'tmp_name' => $filepath,
            'error' => 0,
            'size' => filesize($filepath),
        ];
        $overrides = ['test_form' => false];
        $id = media_handle_sideload($file, 0);
        return $id;
    }

    public static function handle_zip() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        check_admin_referer('oha_awards_import');
        if (empty($_FILES['zipfile']['tmp_name'])) wp_die('No zip provided');
        $tmp = $_FILES['zipfile']['tmp_name'];

        // Extract into a temp dir
        $uploads = wp_get_upload_dir();
        $tempdir = trailingslashit($uploads['basedir']).'oha-awards-import-tmp-'.time();
        wp_mkdir_p($tempdir);
        $zip = new ZipArchive();
        if ($zip->open($tmp) === true) {
            $zip->extractTo($tempdir);
            $zip->close();
        } else {
            wp_die('Could not open ZIP');
        }

        $results = [];
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tempdir));
        foreach ($rii as $f) {
            if ($f->isDir()) continue;
            $res = self::import_file($f->getPathname());
            $results[] = $res;
        }
        // cleanup
        foreach (glob($tempdir.'/*') as $g) { /* leave temp */ }
        echo '<div class="wrap"><h1>Import Results (ZIP)</h1><ul>';
        foreach ($results as $r) { echo '<li>'.esc_html(($r['ok']?'✔':'✖').' '.$r['msg']).'</li>'; }
        echo '</ul><p><a class="button" href="'.esc_url(admin_url('admin.php?page=oha-awards-bulk')).'">Back</a></p></div>';
        wp_die();
    }

    public static function handle_folder() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        check_admin_referer('oha_awards_import');
        $uploads = wp_get_upload_dir();
        $folder = trailingslashit($uploads['basedir']).'oha-awards-import';
        if (!is_dir($folder)) wp_mkdir_p($folder);
        $results = [];
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder));
        foreach ($rii as $f) {
            if ($f->isDir()) continue;
            $res = self::import_file($f->getPathname());
            $results[] = $res;
        }
        echo '<div class="wrap"><h1>Import Results (Folder)</h1><ul>';
        foreach ($results as $r) { echo '<li>'.esc_html(($r['ok']?'✔':'✖').' '.$r['msg']).'</li>'; }
        echo '</ul><p><a class="button" href="'.esc_url(admin_url('admin.php?page=oha-awards-bulk')).'">Back</a></p></div>';
        wp_die();
    }
}
