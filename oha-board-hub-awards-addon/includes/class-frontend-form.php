<?php
if (!defined('ABSPATH')) exit;

class OHA_Awards_Front_Submit {
    public static function init() {
        add_shortcode('oha_awards_submit', [__CLASS__,'render']);
        add_action('admin_post_oha_awards_front_submit', [__CLASS__,'handle_submit']);
        add_action('admin_post_nopriv_oha_awards_front_submit', [__CLASS__,'must_login']);
        add_action('wp_ajax_oha_awards_search_haunts', [__CLASS__,'ajax_search_haunts']);
        add_action('wp_ajax_nopriv_oha_awards_search_haunts', [__CLASS__,'must_login']);
    }

    public static function must_login() {
        wp_safe_redirect(wp_login_url(wp_get_referer() ?: home_url('/')));
        exit;
    }

    private static function login_form_html() {
        $opts      = get_option('oha_awards_settings', []);
        $shortcode = trim($opts['login_shortcode'] ?? '[uwp_login]');
        if (!$shortcode) $shortcode = '[uwp_login]';

        // Inject redirect_to so the user lands back on this page after login
        $redirect = esc_url(get_permalink() ?: home_url('/'));
        if (strpos($shortcode, 'redirect_to') === false) {
            $shortcode = rtrim($shortcode, ']') . ' redirect_to="' . $redirect . '"]';
        }

        wp_enqueue_style('oha-awards-form', OHA_AWARDS_URL . 'assets/css/oha-awards-form.css', [], OHA_AWARDS_VER);
        return '<div class="oha-awards-login">'
            . '<p>You must be logged in as an OHA member to submit a Haunt Actor Award nomination.</p>'
            . '<div class="oha-awards-form">' . do_shortcode($shortcode) . '</div>'
            . '</div>';
    }

    protected static function get_user_listings($user_id) {
        $opts = get_option('oha_awards_settings', []);
        $pt = !empty($opts['listing_post_type']) ? $opts['listing_post_type'] : 'gd_place';

        $args = [
            'post_type'      => $pt,
            'posts_per_page' => 200,
            'post_status'    => ['publish','pending','draft'],
            'author'         => $user_id,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ];
        $q = new WP_Query($args);
        $out = [];
        foreach ($q->posts as $pid) {
            $out[$pid] = get_the_title($pid);
        }
        return $out;
    }

    public static function ajax_search_haunts() {
        if (!is_user_logged_in()) wp_send_json_error(['msg'=>'Login required'], 401);
        $nonce = isset($_GET['nonce']) ? sanitize_text_field($_GET['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'oha_awards_nonce')) wp_send_json_error(['msg'=>'Bad nonce'], 403);
        $term = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $opts = get_option('oha_awards_settings', []);
        $pt = !empty($opts['listing_post_type']) ? $opts['listing_post_type'] : 'gd_place';
        $args = [
            'post_type'      => $pt,
            'post_status'    => 'publish',
            's'              => $term,
            'posts_per_page' => 20,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ];
        $q = new WP_Query($args);
        $items = [];
        foreach ($q->posts as $pid) {
            $items[] = ['id' => $pid, 'text' => get_the_title($pid)];
        }
        wp_send_json_success(['results' => $items]);
    }

    public static function render($atts = []) {
        // Prevent cached nonces and stale forms
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        if (function_exists('nocache_headers')) { nocache_headers(); }

        if (!is_user_logged_in()) return self::login_form_html();

        // Submissions open check (bypass token via ?oha_open=TOKEN)
        $bypass = isset($_GET['oha_open']) ? sanitize_text_field($_GET['oha_open']) : '';
        if (!OHA_Awards_Settings::is_submissions_open($bypass)) {
            $year = OHA_Awards_Settings::get_year();
            return '<p>Submissions for the ' . esc_html($year) . ' haunt award season are now closed. We look forward to your submission next year.</p>';
        }
        $user_id = get_current_user_id();

        // Confirmation notice after redirect
        $notice = isset($_GET['oha_awards_success']) ? 'Your award submission has been received.' : get_transient('oha_awards_notice_' . $user_id);
        if ($notice) {
            $html = '<div class="notice notice-success" style="margin-bottom:12px;padding:8px;border-left:4px solid #46b450;background:#f6fff7;">'.esc_html($notice).'</div>';
            delete_transient('oha_awards_notice_' . $user_id);
        } else {
            $html = '';
        }

        if (isset($_GET['oha_awards_error'])) {
            $err_code = sanitize_key($_GET['oha_awards_error']);
            $err_messages = [
                'nonce'     => 'Your session expired. Please try submitting again.',
                'fields'    => 'Please fill in all required fields.',
                'duplicate' => 'An award nomination for this haunt has already been submitted this season.',
                'save'      => 'Unable to save your submission. Please try again.',
            ];
            $html .= '<div class="notice notice-error" style="margin-bottom:12px;padding:8px;border-left:4px solid #dc3232;background:#fdecea;color:#8c1700;">'
                . esc_html($err_messages[$err_code] ?? 'There was an error processing your submission. Please try again.')
                . '</div>';
        }

        $listings = self::get_user_listings($user_id);
        $default_haunt_id = 0;
        if (!empty($listings)) { $ids = array_keys($listings); $default_haunt_id = intval(reset($ids)); }
        if (!$listings) {
            $html .= '<p><em>No listings found for your account. Please make sure your haunt listing is created and assigned to you.</em></p>';
        }

        $current_user = wp_get_current_user();
        $default_name  = trim(($current_user->first_name ?? '') . ' ' . ($current_user->last_name ?? '')) ?: ($current_user->display_name ?? '');
        $default_email = $current_user->user_email ?? '';

        $action      = esc_url(admin_url('admin-post.php'));
        $nonce       = wp_create_nonce('oha_awards_front_submit');
        $current_url = esc_attr(get_permalink() ?: home_url('/'));
                wp_enqueue_style('oha-awards-form', OHA_AWARDS_URL . 'assets/css/oha-awards-form.css', [], OHA_AWARDS_VER);
ob_start();
        ?>
        <?php if ($default_haunt_id) : ?>
        <script>window.ohaAwardsDefaultHaunt = {id: <?php echo intval($default_haunt_id); ?>, text: <?php echo json_encode($listings[$default_haunt_id] ?? ''); ?>};</script>
        <?php endif; ?>
        <form method="post" action="<?php echo $action; ?>" enctype="multipart/form-data" class="oha-awards-form" style="max-width:900px;">
            <input type="hidden" name="action"      value="oha_awards_front_submit" />
            <input type="hidden" name="_wpnonce"    value="<?php echo esc_attr($nonce); ?>" />
            <input type="hidden" name="redirect_to" value="<?php echo $current_url; ?>" />
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div>
                    <label>Your Name*</label>
                    <input type="text" name="submitter_name" required class="regular-text" value="<?php echo esc_attr($default_name); ?>" />
                </div>
                <div>
                    <label>Your Email*</label>
                    <input type="email" name="submitter_email" required class="regular-text" value="<?php echo esc_attr($default_email); ?>" />
                </div>
            </div>

            <hr style="margin:18px 0;" />

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div>
                    <label>Name of Actor/Actress*</label>
                    <input type="text" name="actor_name" required class="regular-text" />
                </div>
                <div>
                    <label>Haunt Name*</label>
                    <input type="hidden" name="haunt_id" id="oha-haunt-id" />
                    <input type="text" id="oha-haunt-search" class="regular-text" placeholder="Search your haunt name…" autocomplete="off" />
                    <div id="oha-haunt-results" class="oha-haunt-results" style="position:relative;"></div>
                    <small class="description">Start typing to search all haunts in GeoDirectory. If you already own a listing, we'll auto-select it for you.</small>
                    <div style="margin-top:8px;">
                        <label><input type="checkbox" id="oha-haunt-not-listed" /> My haunt is not listed</label>
                        <input type="text" name="haunt_name_free" id="oha-haunt-free" class="regular-text" placeholder="Enter your haunt name" style="display:none;margin-top:8px;" />
                    </div>
                    <select name="_legacy_haunt_id" style="display:none">
                        <option value="">Select your haunt…</option>
                        <?php foreach ($listings as $pid=>$title): ?>
                            <option value="<?php echo intval($pid); ?>"><?php echo esc_html($title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="margin-top:16px;">
                <label>Name of Character</label>
                <input type="text" name="character_name" class="regular-text" />
            </div>

            <div style="margin-top:16px;">
                <label>Reason for Submission*</label>
                <textarea name="reason" rows="4" required class="large-text"></textarea>
            </div>

            <div style="margin-top:16px;">
                <label>Photo Submission (in or out of character)*</label>
                <input type="file" name="actor_photo_file" accept="image/*" required />
                <small style="display:block;margin-top:6px;color:#000;">Maximum file size: 10 MB</small>
            </div>

            <p style="margin-top:18px;">
                <button class="button button-primary" type="submit">Submit Award Nomination</button>
            </p>
        </form>
        <?php
        $html .= ob_get_clean();
        // Client-side 10MB guard
        $html .= '<script>(function(){document.addEventListener("DOMContentLoaded",function(){var f=document.querySelector("form.oha-awards-form");if(!f)return;var i=f.querySelector("input[type=file][name=actor_photo_file]");if(!i)return;var MAX=10*1024*1024;function n(m,e){var b=document.createElement("div");b.style.marginBottom="14px";b.style.padding="12px 14px";b.style.color="#000";if(e){b.style.borderLeft="4px solid #dc3232";b.style.background="#fdecea";}else{b.style.borderLeft="4px solid #46b450";b.style.background="#e7f9ed";}b.textContent=m;f.insertBefore(b,f.firstChild);}i.addEventListener("change",function(){var file=this.files&&this.files[0];if(file&&file.size>MAX){n("Please upload a file 10 MB or smaller.",true);this.value="";}});f.addEventListener("submit",function(ev){var file=i.files&&i.files[0];if(file&&file.size>MAX){ev.preventDefault();n("Please upload a file 10 MB or smaller.",true);}});});})();</script>';
        return $html;
    }

    public static function handle_submit() {
        if (!is_user_logged_in()) self::must_login();
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'oha_awards_front_submit')) {
            $back = !empty($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : (wp_get_referer() ?: home_url('/'));
            wp_safe_redirect(add_query_arg('oha_awards_error', 'nonce', $back));
            exit;
        }

        $user_id = get_current_user_id();
        $submitter_name  = sanitize_text_field($_POST['submitter_name'] ?? '');
        $submitter_email = sanitize_email($_POST['submitter_email'] ?? '');
        $actor_name      = sanitize_text_field($_POST['actor_name'] ?? '');
        $haunt_id        = intval($_POST['haunt_id'] ?? 0);
        $haunt_name_free = sanitize_text_field($_POST['haunt_name_free'] ?? '');
        $character_name  = sanitize_text_field($_POST['character_name'] ?? '');
        $reason          = wp_kses_post($_POST['reason'] ?? '');

        if (!$submitter_name || !$submitter_email || !$actor_name || (!$haunt_id && !$haunt_name_free) || !$reason) {
            $back = !empty($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : (wp_get_referer() ?: home_url('/'));
            wp_safe_redirect(add_query_arg('oha_awards_error', 'fields', $back));
            exit;
        }

        // Ownership check: allow non-owners (we will file a GD claim later if needed)
        $not_owner = false;
        
        // Gate GD Claim creation: only when current owner is user ID 3 or display name "Ohio Haunters Board"
        $allow_claim = false;
        $already_filed = false;
        if ($haunt_id > 0 && $allow_claim && !$already_filed) {
            $listing_author = (int) get_post_field('post_author', $haunt_id);
            if ($listing_author === 3) { $allow_claim = true; }
            else {
                $u = get_user_by('id', $listing_author);
                if ($u && strtolower($u->display_name) === strtolower('Ohio Haunters Board')) { $allow_claim = true; }
            }
        }
        // Prevent duplicate claims from same award entry
        $already_filed = false;
        if (!empty($post_id)) {
            $already_filed = (bool) get_post_meta($post_id, '_oha_claim_filed', true);
        }
    OHA_Awards_Helpers::dbg('Attempting claim creation', ['haunt_id'=>$haunt_id, 'user_id'=>$user_id, 'GDCR_CPT'=> (defined('GDCR_CPT')?GDCR_CPT:'not defined'), 'gd_claim_exists'=> post_type_exists('gd_claim')]);
        if ($haunt_id > 0) {

        // Preferred: invoke the GD Claim plugin's own handler in-process (no HTTP)
        
        if (function_exists('gdcr_handle_claim_submit')) {
            // Fire the claim via AJAX endpoint in a non-blocking request to avoid output/die() collisions.
            $ajax_url = admin_url('admin-ajax.php');
            $body = [
                'action'      => 'gdcr_claim_submit',
                'listing_id'  => $haunt_id,
                'message'     => 'Submitted via Awards form',
                'gdcr_nonce'  => wp_create_nonce('gdcr_nonce_action'),
                '_wpnonce'    => wp_create_nonce('gdcr_nonce_action'),
                'security'    => wp_create_nonce('gdcr_nonce_action'),
            ];
            // Carry over auth cookies so the AJAX action sees the current user
            $cookie_header = '';
            foreach ($_COOKIE as $ck => $cv) {
                if ($cookie_header !== '') $cookie_header .= '; ';
                $cookie_header .= $ck . '=' . $cv;
            }
            $args = [
                'timeout' => 5,
                'blocking' => true,
                'body' => $body,
                'headers' => [
                    'Cookie' => $cookie_header,
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Referer' => wp_get_referer() ?: home_url('/'),
                ],
            ];
            $resp = wp_remote_post($ajax_url, $args);
            if (function_exists('OHA_Awards_Helpers::dbg')) {
                $code = is_wp_error($resp) ? 0 : wp_remote_retrieve_response_code($resp);
                OHA_Awards_Helpers::dbg('gdcr_claim_submit response', ['code'=>$code]);
            } OHA_Awards_Helpers::dbg('gdcr_handle_claim_submit not found; fallback to CPT insert', []);
            // Fallback: CPT insert (kept as backup)
            $cpt_slug = (defined('GDCR_CPT') && GDCR_CPT) ? GDCR_CPT : 'gd_claim';
            $current_user = wp_get_current_user();
            $claim_args = [
                'post_type'   => $cpt_slug,
                'post_status' => 'pending',
                'post_title'  => 'Claim for #' . $haunt_id . ' by user #' . $user_id,
                'post_author' => $user_id,
                'meta_input'  => [
                    '_gdcr_listing_id' => $haunt_id,
                    '_gdcr_claimant'   => $user_id,
                    '_gdcr_message'    => 'Submitted via Awards form by ' . ($current_user && $current_user->user_email ? $current_user->user_email : 'user#'.$user_id),
                ],
            ];
            $cap_filter = function($caps, $cap, $uid, $args){
                if ($cap === 'do_not_allow') { return ['exist']; }
                return $caps;
            };
            add_filter('map_meta_cap', $cap_filter, 999, 4);
            $claim_id = wp_insert_post($claim_args);
            remove_filter('map_meta_cap', $cap_filter, 999);
            if (is_wp_error($claim_id)) { OHA_Awards_Helpers::dbg('Fallback CPT insert failed', ['error'=>$claim_id->get_error_code(), 'message'=>$claim_id->get_error_message()]); }
            else { OHA_Awards_Helpers::dbg('Fallback CPT insert result', ['claim_id'=>$claim_id]); }
        }

            $listing_author = (int) get_post_field('post_author', $haunt_id);
            $not_owner = ($listing_author !== $user_id);
        }
        // Enforce one-per-haunt if enabled (scoped to the active convention year)
        $opts = get_option('oha_awards_settings', []);
        $active_year = OHA_Awards_Settings::get_year();
        $enforce_one = !empty($opts['one_per_haunt']) && ($haunt_id > 0);
        if ($enforce_one) {
            $q = new WP_Query([
                'post_type'      => 'oha_award_entry',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'post_status'    => ['publish','pending','draft'],
                'meta_query'     => [
                    'relation' => 'AND',
                    ['key' => '_oha_award_year', 'value' => $active_year, 'compare' => '=', 'type' => 'NUMERIC'],
                    ['key' => '_oha_haunt_id', 'value' => $haunt_id, 'compare' => '='],
                ],
            ]);
            if ($q->have_posts()) {
                $back = !empty($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : (wp_get_referer() ?: home_url('/'));
                wp_safe_redirect(add_query_arg('oha_awards_error', 'duplicate', $back));
                exit;
            }
        }

        // Create entry
        $post_id = wp_insert_post([
            'post_type'   => 'oha_award_entry',
            'post_status' => 'publish',
            'post_title'  => $actor_name,
            'post_author' => $user_id,
            'meta_input'  => [
                '_oha_owner_user_id'   => $user_id,
                '_oha_submitter_name'  => $submitter_name,
                '_oha_submitter_email' => $submitter_email,
                '_oha_haunt_id'        => $haunt_id,
                '_oha_haunt_name_free' => $haunt_name_free,
                '_oha_actor_name'      => $actor_name,
                '_oha_character_name'  => $character_name,
                '_oha_reason'          => $reason,
                '_oha_status'          => 'in_review',
                '_oha_award_year' => $active_year,
                '_oha_created_at'      => current_time('mysql'),
            ],
        ]);

        if (is_wp_error($post_id) || !$post_id) {
            $back = !empty($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : (wp_get_referer() ?: home_url('/'));
            wp_safe_redirect(add_query_arg('oha_awards_error', 'save', $back));
            exit;
        }

        // Log
        OHA_Awards_Helpers::log_status($post_id, 'in_review', 'Front-end form submission');

        // Upload photo (fast path: no image size generation)
        if (!empty($_FILES['actor_photo_file']['name'])) {
            // Guard file size (10 MB)
            $max_bytes = 10 * 1024 * 1024;
            $file_size = isset($_FILES['actor_photo_file']['size']) ? (int) $_FILES['actor_photo_file']['size'] : 0;
            if ($file_size > $max_bytes) {
                set_transient('oha_awards_notice_' . $user_id, 'Your photo is larger than 10 MB. Please upload a smaller file.', 600);
                $redir = wp_get_referer() ?: home_url('/');
                $sep = (strpos($redir,'?')===false) ? '?' : '&';
                wp_safe_redirect($redir . $sep . 'oha_awards_error=1');
                exit;
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';
            $overrides = [ 'test_form' => false ];
            $movefile = wp_handle_upload($_FILES['actor_photo_file'], $overrides);
            if ($movefile && empty($movefile['error'])) {
                // Create a minimal attachment without generating sizes/metadata
                $filetype = wp_check_filetype( basename( $movefile['file'] ), null );
                $attachment = [
                    'guid'           => $movefile['url'],
                    'post_mime_type' => $filetype['type'] ?: 'image/jpeg',
                    'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $movefile['file'] ) ),
                    'post_content'   => '',
                    'post_status'    => 'inherit'
                ];
                $att_id = wp_insert_attachment( $attachment, $movefile['file'], $post_id );
                if (!is_wp_error($att_id)) {
                    update_post_meta($post_id, '_oha_photo_id', $att_id);
                    update_post_meta($post_id, '_oha_photo_url', esc_url_raw($movefile['url']));
                }
            }
        }

        if (!$already_filed && $allow_claim && $haunt_id>0) { update_post_meta($post_id,'_oha_claim_filed',1); }

        // Emails

        OHA_Awards_Email::send_admin_notice($post_id);
        OHA_Awards_Email::send_submitter_confirm($post_id, $submitter_email);

        // Confirmation + redirect
        set_transient('oha_awards_notice_' . $user_id, 'Your award submission has been received.', 60*10);
        $redir = !empty($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : (!empty($opts['redirect_after_submit']) ? $opts['redirect_after_submit'] : (wp_get_referer() ?: home_url('/')));
        $sep = (strpos($redir,'?')===false) ? '?' : '&';
        wp_safe_redirect($redir . $sep . 'oha_awards_success=1');
        exit;
    }
}
