<?php
if (!defined('ABSPATH')) exit;

class OHA_Awards_Settings {
    public static function init() {
        add_action('admin_menu', [__CLASS__,'menu']);
        add_action('admin_init', [__CLASS__,'register']);
    }

    public static function menu() {
        // Top-level Awards menu
        add_menu_page(
            'Awards',
            'Awards',
            'manage_options',
            'oha-awards',
            [__CLASS__,'render'],
            'dashicons-awards',
            58
        );

        // Submissions page
        if (class_exists('OHA_Awards_Admin_Table') && method_exists('OHA_Awards_Admin_Table','render_page')) {
            add_submenu_page('oha-awards','Awards Submissions','Submissions','manage_options','oha-awards-submissions',['OHA_Awards_Admin_Table','render_page']);
        }

        // Settings page
        add_submenu_page('oha-awards','Awards Settings','Settings','manage_options','oha-awards',[__CLASS__,'render']);

        // Debug page
        if (class_exists('OHA_Awards_Debug') && method_exists('OHA_Awards_Debug','render')) {
            add_submenu_page('oha-awards','Awards Debug','Debug','manage_options','oha-awards-debug',['OHA_Awards_Debug','render']);
        }
    }

    /**
     * Returns the active award season year from settings, falling back to the current calendar year.
     * Use this in every WP_Query that touches oha_award_entry so all views are year-scoped.
     */
    public static function get_year() {
        $opts = get_option('oha_awards_settings', []);
        $y = intval($opts['award_year'] ?? 0);
        return $y > 2000 ? $y : (int) date('Y');
    }

    /**
     * Returns true if submissions are currently open.
     * Checks the global closed toggle, then the close date if set.
     * Pass the raw bypass token from the request to skip the check.
     */
    /**
     * @param string $bypass_token  Value of ?oha_open= param from the request.
     * @param string $type          'actor' (default) or 'campsite' — each category has its own settings.
     */
    public static function is_submissions_open($bypass_token = '', $type = 'actor') {
        $opts = get_option('oha_awards_settings', []);

        if ($type === 'campsite') {
            $stored_token = trim($opts['campsite_bypass_token'] ?? '');
            $closed_flag  = !empty($opts['campsite_submissions_closed']);
            $close_date   = trim($opts['campsite_close_date'] ?? '');
        } else {
            $stored_token = trim($opts['bypass_token'] ?? '');
            $closed_flag  = !empty($opts['submissions_closed']);
            $close_date   = trim($opts['close_date'] ?? '');
        }

        // Bypass token check — if provided and matches, always open
        if ($stored_token !== '' && $bypass_token !== '' && hash_equals($stored_token, $bypass_token)) {
            return true;
        }

        // Global closed toggle
        if ($closed_flag) {
            return false;
        }

        // Auto-close by date
        if ($close_date !== '') {
            $close_ts = strtotime($close_date . ' 23:59:59');
            if ($close_ts && time() > $close_ts) {
                return false;
            }
        }

        return true;
    }

    public static function register() {
        register_setting('oha_awards_group', 'oha_awards_settings', [
            'type' => 'array',
            'sanitize_callback' => function($arr){
                $arr['notification_email'] = sanitize_email($arr['notification_email'] ?? get_option('admin_email'));
                $arr['one_per_haunt']      = !empty($arr['one_per_haunt']) ? 1 : 0;
                $arr['award_year']          = intval($arr['award_year'] ?? date('Y'));
                $arr['submissions_closed']  = !empty($arr['submissions_closed']) ? 1 : 0;
                $arr['close_date']          = sanitize_text_field($arr['close_date'] ?? '');
                $arr['bypass_token']        = sanitize_text_field($arr['bypass_token'] ?? '');
                $arr['email_cert_subject']  = sanitize_text_field($arr['email_cert_subject'] ?? '');
                $arr['email_cert_body']     = wp_kses_post($arr['email_cert_body'] ?? '');
                $arr['field_map']           = OHA_Awards_Helpers::sanitize_field_map($arr['field_map'] ?? []);
                $arr['listing_post_type'] = sanitize_text_field($arr['listing_post_type'] ?? 'gd_place');
                $arr['redirect_after_submit'] = esc_url_raw($arr['redirect_after_submit'] ?? '');
                $arr['email_submitter_subject'] = sanitize_text_field($arr['email_submitter_subject'] ?? '');
                $arr['email_submitter_body']    = wp_kses_post($arr['email_submitter_body'] ?? '');
                $arr['email_admin_subject']     = sanitize_text_field($arr['email_admin_subject'] ?? '');
                $arr['email_admin_body']        = wp_kses_post($arr['email_admin_body'] ?? '');
                // Campsite award settings
                $arr['campsite_submissions_closed']      = !empty($arr['campsite_submissions_closed']) ? 1 : 0;
                $arr['campsite_close_date']              = sanitize_text_field($arr['campsite_close_date'] ?? '');
                $arr['campsite_bypass_token']            = sanitize_text_field($arr['campsite_bypass_token'] ?? '');
                $arr['campsite_notification_email']      = sanitize_email($arr['campsite_notification_email'] ?? '');
                $arr['email_campsite_cert_subject']      = sanitize_text_field($arr['email_campsite_cert_subject'] ?? '');
                $arr['email_campsite_cert_body']         = wp_kses_post($arr['email_campsite_cert_body'] ?? '');
                $arr['email_campsite_admin_subject']     = sanitize_text_field($arr['email_campsite_admin_subject'] ?? '');
                $arr['email_campsite_admin_body']        = wp_kses_post($arr['email_campsite_admin_body'] ?? '');
                $arr['email_campsite_submitter_subject'] = sanitize_text_field($arr['email_campsite_submitter_subject'] ?? '');
                $arr['email_campsite_submitter_body']    = wp_kses_post($arr['email_campsite_submitter_body'] ?? '');
                return $arr;
            }
        ]);
        add_settings_section('oha_awards_main', 'Awards Settings', function(){
            echo '<p>Configure field mapping for Kadence (free supported via bridge) and behavior.</p>';
        }, 'oha_awards');

        add_settings_field('award_year','Award Year',[__CLASS__,'field_year'],'oha_awards','oha_awards_main');
        add_settings_field('submissions_closed','Submissions Closed',[__CLASS__,'field_closed'],'oha_awards','oha_awards_main');
        add_settings_field('close_date','Auto-Close Date',[__CLASS__,'field_close_date'],'oha_awards','oha_awards_main');
        add_settings_field('bypass_token','Bypass Token',[__CLASS__,'field_bypass'],'oha_awards','oha_awards_main');
        add_settings_field('notification_email','Notification Email',[__CLASS__,'field_email'],'oha_awards','oha_awards_main');
        add_settings_field('one_per_haunt','Enforce One Submission per Haunt',[__CLASS__,'field_one_per'],'oha_awards','oha_awards_main');
        add_settings_field('email_cert_ready','Certificate Ready Email',[__CLASS__,'field_cert_email'],'oha_awards','oha_awards_main');
        // Kadence field map kept for backwards compatibility
        add_settings_field('field_map','Legacy Field Map (optional)',[__CLASS__,'field_map'],'oha_awards','oha_awards_main');
        add_settings_field('listing_post_type','GeoDirectory Listing Post Type',[__CLASS__,'field_listing_pt'],'oha_awards','oha_awards_main');

        // Campsite Award — separate section so each category can be managed independently
        add_settings_section('oha_awards_campsite', 'Campsite Award Settings', function(){
            echo '<p>Settings for the Most Outstanding Haunted Campsite award. These controls are independent of the Haunt Actor award above.</p>';
        }, 'oha_awards');
        add_settings_field('campsite_submissions_closed','Campsite Submissions Closed',[__CLASS__,'field_campsite_closed'],'oha_awards','oha_awards_campsite');
        add_settings_field('campsite_close_date','Campsite Auto-Close Date',[__CLASS__,'field_campsite_close_date'],'oha_awards','oha_awards_campsite');
        add_settings_field('campsite_bypass_token','Campsite Bypass Token',[__CLASS__,'field_campsite_bypass'],'oha_awards','oha_awards_campsite');
        add_settings_field('campsite_notification_email','Campsite Notification Email',[__CLASS__,'field_campsite_email'],'oha_awards','oha_awards_campsite');
        add_settings_field('email_campsite_cert_ready','Campsite Certificate Ready Email',[__CLASS__,'field_campsite_cert_email'],'oha_awards','oha_awards_campsite');
    }

    public static function render() {
        ?>
        <div class="wrap">
          <h1>OHA Awards</h1>
          <form method="post" action="options.php">
            <?php
              settings_fields('oha_awards_group');
              do_settings_sections('oha_awards');
              submit_button('Save Settings');
            ?>
          </form>
          <?php if (has_action('oha_awards_admin_sections')) { do_action('oha_awards_admin_sections'); } ?>
        </div>
        <?php
    }

    public static function field_year() {
        $opts = get_option('oha_awards_settings', []);
        $val = intval($opts['award_year'] ?? date('Y'));
        echo '<input type="number" name="oha_awards_settings[award_year]" value="'.esc_attr($val).'" class="small-text" min="2020" max="2099" />';
        echo '<p class="description">All submissions, the admin table, CSV export, and the owner dashboard will be scoped to this year. Change it at the start of each haunt award season.</p>';
    }

    public static function field_closed() {
        $opts = get_option('oha_awards_settings', []);
        $checked = !empty($opts['submissions_closed']) ? 'checked' : '';
        echo '<label><input type="checkbox" name="oha_awards_settings[submissions_closed]" value="1" '.$checked.' /> Close submissions immediately</label>';
        echo '<p class="description">Check this to stop accepting new submissions regardless of the auto-close date. Uncheck to reopen.</p>';
    }

    public static function field_close_date() {
        $opts = get_option('oha_awards_settings', []);
        $val  = esc_attr($opts['close_date'] ?? '');
        echo '<input type="date" name="oha_awards_settings[close_date]" value="'.$val.'" />';
        echo '<p class="description">Optional. Submissions will automatically close at the end of this date. Leave blank to rely only on the checkbox above.</p>';
    }

    public static function field_bypass() {
        $opts  = get_option('oha_awards_settings', []);
        $val   = esc_attr($opts['bypass_token'] ?? '');
        $url   = home_url('/') . '?oha_open=' . ($val ?: 'your-token-here');
        echo '<input type="text" name="oha_awards_settings[bypass_token]" value="'.$val.'" class="regular-text" placeholder="e.g. ohajudge2026" />';
        echo '<p class="description">Set a word or phrase here. Share the submission page URL with <code>?oha_open=YOURTOKEN</code> appended to let specific people submit after closing.<br/>';
        if ($val) {
            echo 'Current bypass URL: <code>'.esc_html($url).'</code>';
        }
        echo '</p>';
    }

    public static function field_cert_email() {
        $opts = get_option('oha_awards_settings', []);
        $subj = esc_attr($opts['email_cert_subject'] ?? 'Your Haunt Award certificate is ready');
        $body = esc_textarea($opts['email_cert_body'] ?? "Hi {owner_name},\n\nGreat news! Your Haunt Award certificate for {actor_name} at {haunt_title} is ready.\n\nLog in to download your certificate: {dashboard_url}\n\nThank you for being part of the OHA haunt community.");
        echo '<p><strong>Placeholders:</strong> {entry_id}, {actor_name}, {character_name}, {haunt_title}, {owner_name}, {owner_email}, {dashboard_url}</p>';
        echo '<p>Subject<br/><input type="text" name="oha_awards_settings[email_cert_subject]" value="'.$subj.'" class="regular-text" style="width:500px" /></p>';
        echo '<p>Body<br/><textarea name="oha_awards_settings[email_cert_body]" rows="6" class="large-text" style="max-width:700px">'.$body.'</textarea></p>';
    }

    public static function field_email() {
        $opts = get_option('oha_awards_settings', []);
        $val = esc_attr($opts['notification_email'] ?? get_option('admin_email'));
        echo '<input type="email" name="oha_awards_settings[notification_email]" value="'.$val.'" class="regular-text" />';
    }

    public static function field_one_per() {
        $opts = get_option('oha_awards_settings', []);
        $checked = !empty($opts['one_per_haunt']) ? 'checked' : '';
        echo '<label><input type="checkbox" name="oha_awards_settings[one_per_haunt]" value="1" '.$checked.' /> Allow only one submission per haunt (per owner)</label>';
    }

    public static function field_listing_pt() {
        $opts = get_option('oha_awards_settings', []);
        $val = esc_attr($opts['listing_post_type'] ?? 'gd_place');
        echo '<input type="text" name="oha_awards_settings[listing_post_type]" value="'.$val.'" class="regular-text" />';
        echo '<p class="description">Default is <code>gd_place</code>. Change if your GeoDirectory listing type uses another post type.</p>';
    }

    public static function field_redirect() {
        $opts = get_option('oha_awards_settings', []);
        $val = esc_url($opts['redirect_after_submit'] ?? '');
        echo '<input type="url" name="oha_awards_settings[redirect_after_submit]" value="'.$val.'" class="regular-text" placeholder="https://example.com/awards-success" />';
        echo '<p class="description">If set, submitters are redirected to this URL after a successful submission.</p>';
    }

    public static function field_emails() {
        $opts = get_option('oha_awards_settings', []);
        $esub = esc_attr($opts['email_submitter_subject'] ?? 'We received your Haunt Award submission');
        $ebod = esc_textarea($opts['email_submitter_body'] ?? "{owner_name},\n\nThank you for submitting a Haunt Award nomination for {actor_name} at {haunt_title}.\nEntry #{entry_id}\n\nWe will review it and notify you when your certificate is ready.\n\nView your awards: {dashboard_url}");
        $asub = esc_attr($opts['email_admin_subject'] ?? 'New Haunt Award Submission #{entry_id}');
        $abod = esc_textarea($opts['email_admin_body'] ?? 'A new award entry was submitted.\n\nEntry #{entry_id}\nActor: {actor_name}\nHaunt: {haunt_title}\nOwner: {owner_name} ({owner_email})\n\nView in admin: {admin_url}');
        echo '<p><strong>Placeholders:</strong> {entry_id}, {actor_name}, {character_name}, {reason}, {haunt_title}, {owner_name}, {owner_email}, {admin_url}, {dashboard_url}</p>';
        echo '<h4>Submitter Email</h4>';
        echo '<p>Subject<br/><input type="text" name="oha_awards_settings[email_submitter_subject]" value="'.$esub.'" class="regular-text" style="width:600px" /></p>';
        echo '<p>Body<br/><textarea name="oha_awards_settings[email_submitter_body]" rows="6" class="large-text" style="max-width:800px">'.$ebod.'</textarea></p>';
        echo '<h4>Admin Email</h4>';
        echo '<p>Subject<br/><input type="text" name="oha_awards_settings[email_admin_subject]" value="'.$asub.'" class="regular-text" style="width:600px" /></p>';
        echo '<p>Body<br/><textarea name="oha_awards_settings[email_admin_body]" rows="6" class="large-text" style="max-width:800px">'.$abod.'</textarea></p>';
    }

    public static function field_campsite_closed() {
        $opts    = get_option('oha_awards_settings', []);
        $checked = !empty($opts['campsite_submissions_closed']) ? 'checked' : '';
        echo '<label><input type="checkbox" name="oha_awards_settings[campsite_submissions_closed]" value="1" ' . $checked . ' /> Close campsite submissions immediately</label>';
        echo '<p class="description">Check to stop accepting new campsite submissions. Uncheck to reopen.</p>';
    }

    public static function field_campsite_close_date() {
        $opts = get_option('oha_awards_settings', []);
        $val  = esc_attr($opts['campsite_close_date'] ?? '');
        echo '<input type="date" name="oha_awards_settings[campsite_close_date]" value="' . $val . '" />';
        echo '<p class="description">Optional. Campsite submissions auto-close at the end of this date.</p>';
    }

    public static function field_campsite_bypass() {
        $opts = get_option('oha_awards_settings', []);
        $val  = esc_attr($opts['campsite_bypass_token'] ?? '');
        $url  = home_url('/') . '?oha_open=' . ($val ?: 'your-token-here');
        echo '<input type="text" name="oha_awards_settings[campsite_bypass_token]" value="' . $val . '" class="regular-text" placeholder="e.g. ohacamp2026" />';
        echo '<p class="description">Append <code>?oha_open=YOURTOKEN</code> to the campsite submission URL to allow submissions after closing.</p>';
        if ($val) {
            echo '<p class="description">Current bypass URL: <code>' . esc_html($url) . '</code></p>';
        }
    }

    public static function field_campsite_email() {
        $opts = get_option('oha_awards_settings', []);
        $val  = esc_attr($opts['campsite_notification_email'] ?? get_option('admin_email'));
        echo '<input type="email" name="oha_awards_settings[campsite_notification_email]" value="' . $val . '" class="regular-text" />';
        echo '<p class="description">Board notification email for new campsite submissions. Defaults to site admin email if blank.</p>';
    }

    public static function field_campsite_cert_email() {
        $opts = get_option('oha_awards_settings', []);
        $subj = esc_attr($opts['email_campsite_cert_subject'] ?? 'Your Haunted Campsite Award certificate is ready');
        $body = esc_textarea($opts['email_campsite_cert_body'] ?? "Hi {contact_name},\n\nGreat news! Your Haunted Campsite Award certificate for {campground_name} is ready.\n\nYou can download it here: {dashboard_url}\n\nThank you for participating in the OHA Haunted Campsite Awards.");
        echo '<p><strong>Placeholders:</strong> {entry_id}, {campground_name}, {contact_name}, {contact_email}, {location}, {dashboard_url}</p>';
        echo '<p>Subject<br/><input type="text" name="oha_awards_settings[email_campsite_cert_subject]" value="' . $subj . '" class="regular-text" style="width:500px" /></p>';
        echo '<p>Body<br/><textarea name="oha_awards_settings[email_campsite_cert_body]" rows="6" class="large-text" style="max-width:700px">' . $body . '</textarea></p>';
    }

    public static function field_map() {
        $opts = get_option('oha_awards_settings', []);
        $map  = $opts['field_map'] ?? [];
        $fields = [
            'haunt_id'   => 'Haunt ID (GeoDirectory place post_id)',
            'actor_name' => 'Actor Name',
            'actor_email'=> 'Actor Email',
            'actor_photo'=> 'Actor Photo (URL or Kadence file field)'
        ];
        echo '<table class="form-table"><tbody>';
        foreach ($fields as $key=>$label){
            $v = esc_attr($map[$key] ?? '');
            echo '<tr><th><label>'.$label.'</label></th><td><input type="text" name="oha_awards_settings[field_map]['.$key.']" value="'.$v.'" class="regular-text" /></td></tr>';
        }
        echo '</tbody></table>';
        echo '<p class="description">These are no longer required if you use the built-in submission form, but are kept for backwards compatibility.</p>';
    }
}
