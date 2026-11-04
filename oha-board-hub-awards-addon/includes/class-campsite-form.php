<?php
if (!defined('ABSPATH')) exit;

class OHA_Campsite_Awards_Front_Submit {
    public static function init() {
        add_shortcode('oha_campsite_award_submit', [__CLASS__, 'render']);
        add_action('admin_post_oha_campsite_submit',        [__CLASS__, 'handle_submit']);
        add_action('admin_post_nopriv_oha_campsite_submit', [__CLASS__, 'handle_submit']);
    }

    public static function render($atts = []) {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        if (function_exists('nocache_headers')) nocache_headers();

        $bypass = isset($_GET['oha_open']) ? sanitize_text_field($_GET['oha_open']) : '';
        if (!OHA_Awards_Settings::is_submissions_open($bypass, 'campsite')) {
            $year = OHA_Awards_Settings::get_year();
            return '<p>Campsite award submissions for the ' . esc_html($year) . ' season are now closed. Thank you for your interest.</p>';
        }

        $user_id = is_user_logged_in() ? get_current_user_id() : 0;

        $notice = '';
        if ($user_id) {
            $notice = get_transient('oha_campsite_notice_' . $user_id);
            if ($notice) delete_transient('oha_campsite_notice_' . $user_id);
        } elseif (isset($_GET['oha_campsite_success'])) {
            $notice = 'Your campsite award submission has been received.';
        }

        $html = '';
        if ($notice) {
            $html .= '<div class="notice notice-success" style="margin-bottom:12px;padding:8px;border-left:4px solid #46b450;background:#f6fff7;">' . esc_html($notice) . '</div>';
        } elseif (isset($_GET['oha_campsite_error'])) {
            $err_code = sanitize_key($_GET['oha_campsite_error']);
            $err_messages = [
                '1'      => 'There was a problem processing your submission. Please try again.',
                'nonce'  => 'Your session expired. Please try submitting again.',
                'fields' => 'Please fill in all required fields.',
                'save'   => 'Unable to save your submission. Please try again.',
            ];
            $html .= '<div class="notice notice-error" style="margin-bottom:12px;padding:8px;border-left:4px solid #dc3232;background:#fdecea;color:#8c1700;">'
                . esc_html($err_messages[$err_code] ?? 'There was an error processing your submission. Please try again.')
                . '</div>';
        }

        $action      = esc_url(admin_url('admin-post.php'));
        $nonce       = wp_create_nonce('oha_campsite_submit');
        $current_url = esc_attr(get_permalink() ?: home_url('/'));
        wp_enqueue_style('oha-awards-form', OHA_AWARDS_URL . 'assets/css/oha-awards-form.css', [], OHA_AWARDS_VER);

        ob_start();
        ?>
        <form method="post" action="<?php echo $action; ?>" enctype="multipart/form-data" class="oha-awards-form" style="max-width:900px;">
            <input type="hidden" name="action"      value="oha_campsite_submit" />
            <input type="hidden" name="_wpnonce"    value="<?php echo esc_attr($nonce); ?>" />
            <input type="hidden" name="redirect_to" value="<?php echo $current_url; ?>" />

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div>
                    <label>Campground Name*</label>
                    <input type="text" name="campground_name" required class="regular-text" />
                </div>
                <div>
                    <label>Location (City, State)*</label>
                    <input type="text" name="location" required class="regular-text" placeholder="e.g. Columbus, OH" />
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;">
                <div>
                    <label>Contact Person Name*</label>
                    <input type="text" name="contact_name" required class="regular-text" />
                </div>
                <div>
                    <label>Contact Email*</label>
                    <input type="email" name="contact_email" required class="regular-text" />
                </div>
            </div>

            <div style="margin-top:16px;">
                <label>Describe your haunted campsite competition*</label>
                <textarea name="description" rows="5" required class="large-text" placeholder="Tell us about your haunted campsite event - dates, number of campsites competing, what makes it special, etc."></textarea>
            </div>

            <div style="margin-top:16px;">
                <label>Photo (optional)</label>
                <input type="file" name="campsite_photo" accept="image/*" />
                <small style="display:block;margin-top:6px;color:#555;">Maximum file size: 10 MB</small>
            </div>

            <p style="margin-top:18px;">
                <button class="button button-primary" type="submit">Submit Campsite Award Nomination</button>
            </p>
        </form>
        <?php
        $html .= ob_get_clean();
        return $html;
    }

    public static function handle_submit() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'oha_campsite_submit')) {
            $back = !empty($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : (wp_get_referer() ?: home_url('/'));
            wp_safe_redirect(add_query_arg('oha_campsite_error', 'nonce', $back));
            exit;
        }

        $campground_name = sanitize_text_field($_POST['campground_name'] ?? '');
        $location        = sanitize_text_field($_POST['location'] ?? '');
        $contact_name    = sanitize_text_field($_POST['contact_name'] ?? '');
        $contact_email   = sanitize_email($_POST['contact_email'] ?? '');
        $description     = wp_kses_post($_POST['description'] ?? '');

        if (!$campground_name || !$contact_name || !$contact_email || !$description) {
            $back = !empty($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : (wp_get_referer() ?: home_url('/'));
            wp_safe_redirect(add_query_arg('oha_campsite_error', 'fields', $back));
            exit;
        }

        $user_id     = is_user_logged_in() ? get_current_user_id() : 0;
        $active_year = OHA_Awards_Settings::get_year();

        $post_id = wp_insert_post([
            'post_type'   => 'oha_award_entry',
            'post_status' => 'publish',
            'post_title'  => $campground_name,
            'post_author' => $user_id ?: 0,
            'meta_input'  => [
                '_oha_award_type'      => 'campsite',
                '_oha_award_year'      => $active_year,
                '_oha_campground_name' => $campground_name,
                '_oha_location'        => $location,
                '_oha_contact_name'    => $contact_name,
                '_oha_contact_email'   => $contact_email,
                '_oha_description'     => $description,
                '_oha_status'          => 'in_review',
                '_oha_created_at'      => current_time('mysql'),
            ],
        ]);

        if ($user_id) {
            update_post_meta($post_id, '_oha_owner_user_id', $user_id);
        }

        if (is_wp_error($post_id) || !$post_id) {
            $back = !empty($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : (wp_get_referer() ?: home_url('/'));
            wp_safe_redirect(add_query_arg('oha_campsite_error', 'save', $back));
            exit;
        }

        OHA_Awards_Helpers::log_status($post_id, 'in_review', 'Campsite front-end form submission');

        // Photo upload (optional)
        if (!empty($_FILES['campsite_photo']['name'])) {
            $max_bytes = 10 * 1024 * 1024;
            $file_size = (int) ($_FILES['campsite_photo']['size'] ?? 0);
            if ($file_size > 0 && $file_size <= $max_bytes) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                $movefile = wp_handle_upload($_FILES['campsite_photo'], ['test_form' => false]);
                if ($movefile && empty($movefile['error'])) {
                    $filetype = wp_check_filetype(basename($movefile['file']), null);
                    $att_id   = wp_insert_attachment([
                        'guid'           => $movefile['url'],
                        'post_mime_type' => $filetype['type'] ?: 'image/jpeg',
                        'post_title'     => preg_replace('/\.[^.]+$/', '', basename($movefile['file'])),
                        'post_content'   => '',
                        'post_status'    => 'inherit',
                    ], $movefile['file'], $post_id);
                    if (!is_wp_error($att_id)) {
                        update_post_meta($post_id, '_oha_photo_id', $att_id);
                        update_post_meta($post_id, '_oha_photo_url', esc_url_raw($movefile['url']));
                    }
                }
            }
        }

        OHA_Awards_Email::send_admin_notice($post_id);
        OHA_Awards_Email::send_submitter_confirm($post_id, $contact_email);

        $redir = !empty($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : (wp_get_referer() ?: home_url('/'));
        if ($user_id) {
            set_transient('oha_campsite_notice_' . $user_id, 'Your campsite award submission has been received.', 600);
        }
        wp_safe_redirect(add_query_arg('oha_campsite_success', '1', $redir));
        exit;
    }
}
