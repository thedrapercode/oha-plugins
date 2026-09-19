<?php
if (!defined('ABSPATH')) exit;

class OHA_Awards_Bulk_Upload {
    public static function init() {
        add_action('oha_awards_admin_sections', [__CLASS__,'render']);
        add_action('admin_post_oha_awards_upload_zip', [__CLASS__,'handle_zip']);
    }

    public static function render() {
        ?>
        <hr/>
        <h2>Bulk Certificate Upload</h2>
        <p>Upload a ZIP containing PDFs named by entry ID, e.g. <code>1234.pdf</code> or <code>award-1234.pdf</code>.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="oha_awards_upload_zip" />
            <?php wp_nonce_field('oha_awards_upload_zip'); ?>
            <input type="file" name="cert_zip" accept=".zip" required />
            <?php submit_button('Upload & Attach Certificates'); ?>
        </form>
        <?php
    }

    public static function handle_zip() {
        if (!current_user_can('manage_options')) wp_die('Nope');
        check_admin_referer('oha_awards_upload_zip');

        if (empty($_FILES['cert_zip']['tmp_name'])) wp_die('No file');

        $tmp = $_FILES['cert_zip']['tmp_name'];
        $zip = new ZipArchive();
        if ($zip->open($tmp) === TRUE) {
            $report = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (!preg_match('/(\d+)\.pdf$/i', $name, $m)) continue;
                $entry_id = intval($m[1]);
                $stream = $zip->getFromIndex($i);
                if (!$stream) { $report[] = "Failed to read $name"; continue; }

                $upload = wp_upload_bits(basename($name), null, $stream);
                if ($upload['error']) { $report[] = "Upload error for $name"; continue; }

                $filetype = wp_check_filetype(basename($upload['file']), null );
                $attachment = [
                    'post_mime_type' => $filetype['type'],
                    'post_title'     => 'Award Certificate '.$entry_id,
                    'post_content'   => '',
                    'post_status'    => 'inherit'
                ];
                $att_id = wp_insert_attachment($attachment, $upload['file'], $entry_id);
                require_once ABSPATH . 'wp-admin/includes/image.php';
                wp_generate_attachment_metadata($att_id, $upload['file']);
                update_post_meta($entry_id, '_oha_cert_pdf_id', $att_id);
                update_post_meta($entry_id, '_oha_cert_pdf_url', wp_get_attachment_url($att_id));

                $low_id = self::maybe_make_lowres($upload['file'], $entry_id);
                if ($low_id) {
                    update_post_meta($entry_id, '_oha_cert_lowres_id', $low_id);
                    update_post_meta($entry_id, '_oha_cert_lowres_url', wp_get_attachment_url($low_id));
                }

                update_post_meta($entry_id, '_oha_status', 'completed');
                OHA_Awards_Helpers::log_status($entry_id, 'completed', 'Bulk certificate attached');
                $report[] = "Attached certificate to entry #$entry_id";
            }
            $zip->close();
            wp_redirect(add_query_arg(['page'=>'oha-awards','report'=>urlencode(implode("\n",$report))], admin_url('admin.php')));
            exit;
        } else {
            wp_die('Could not open ZIP');
        }
    }

    protected static function maybe_make_lowres($file, $entry_id) {
        if (class_exists('Imagick')) {
            try {
                $im = new Imagick();
                $im->setResolution(150, 150);
                $im->readImage($file."[0]");
                $im->setImageFormat('jpeg');
                $im->setImageCompressionQuality(70);
                $jpeg = $file . '-lowres.jpg';
                $im->writeImage($jpeg);
                $im->clear();
                $im->destroy();

                $filetype = wp_check_filetype(basename($jpeg), null );
                $attachment = [
                    'post_mime_type' => $filetype['type'],
                    'post_title'     => 'Award Certificate LowRes '.$entry_id,
                    'post_content'   => '',
                    'post_status'    => 'inherit'
                ];
                $att_id = wp_insert_attachment($attachment, $jpeg, $entry_id);
                require_once ABSPATH . 'wp-admin/includes/image.php';
                wp_generate_attachment_metadata($att_id, $jpeg);
                return $att_id;
            } catch (Exception $e) {
                return 0;
            }
        }
        return 0;
    }
}
