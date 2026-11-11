<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OHA_Site_PWA_Settings {

    private static $_instance = null;

    public static function instance() {
        if ( null === self::$_instance ) self::$_instance = new self();
        return self::$_instance;
    }

    public function init() {
        add_action( 'admin_menu',  array( $this, 'register_menu' ) );
        add_action( 'admin_post_oha_spwa_save_settings', array( $this, 'handle_save' ) );
    }

    public function register_menu() {
        add_options_page(
            'OHA Site PWA',
            'OHA Site PWA',
            'manage_options',
            'oha-site-pwa',
            array( $this, 'render_page' )
        );
    }

    public function handle_save() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied.' );
        check_admin_referer( 'oha_spwa_save' );

        $text_fields = array(
            'oha_spwa_app_name', 'oha_spwa_short_name', 'oha_spwa_start_url',
            'oha_spwa_theme_color', 'oha_spwa_bg_color', 'oha_spwa_offline_url',
            'oha_spwa_onesignal_app_id',
            'oha_spwa_nav_home_url', 'oha_spwa_nav_listings_url',
            'oha_spwa_nav_myoha_url', 'oha_spwa_nav_events_url', 'oha_spwa_nav_map_url',
            'oha_spwa_member_redirect', 'oha_spwa_owner_redirect', 'oha_spwa_session_days',
        );

        foreach ( $text_fields as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                update_option( $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
            }
        }

        // URL fields
        $url_fields = array( 'oha_spwa_icon_url', 'oha_spwa_mask_icon_url' );
        foreach ( $url_fields as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                update_option( $key, esc_url_raw( wp_unslash( $_POST[ $key ] ) ) );
            }
        }

        // API key: only update if a non-empty value is submitted
        if ( ! empty( $_POST['oha_spwa_onesignal_api_key'] ) ) {
            update_option( 'oha_spwa_onesignal_api_key', sanitize_text_field( wp_unslash( $_POST['oha_spwa_onesignal_api_key'] ) ) );
        }

        // Toggle
        update_option( 'oha_spwa_push_enabled', isset( $_POST['oha_spwa_push_enabled'] ) ? '1' : '0' );

        // Regenerate manifest with new settings
        OHA_Site_PWA_Service_Worker::write_manifest();

        wp_safe_redirect( admin_url( 'options-general.php?page=oha-site-pwa&saved=1' ) );
        exit;
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $saved         = isset( $_GET['saved'] );
        $has_api_key   = (bool) get_option( 'oha_spwa_onesignal_api_key', '' );

        $app_name      = get_option( 'oha_spwa_app_name', 'Ohio Haunters Association' );
        $short_name    = get_option( 'oha_spwa_short_name', 'OHA' );
        $start_url     = get_option( 'oha_spwa_start_url', '/' );
        $theme_color   = get_option( 'oha_spwa_theme_color', '#ad3119' );
        $bg_color      = get_option( 'oha_spwa_bg_color', '#680400' );
        $offline_url   = get_option( 'oha_spwa_offline_url', '/offline/' );
        $icon_url      = get_option( 'oha_spwa_icon_url', '' );
        $mask_icon_url = get_option( 'oha_spwa_mask_icon_url', '' );
        $app_id        = get_option( 'oha_spwa_onesignal_app_id', '' );
        $push_enabled  = get_option( 'oha_spwa_push_enabled', '1' );

        $nav_home      = get_option( 'oha_spwa_nav_home_url', '/' );
        $nav_listings  = get_option( 'oha_spwa_nav_listings_url', '' );
        $nav_myoha     = get_option( 'oha_spwa_nav_myoha_url', '' );
        $nav_events    = get_option( 'oha_spwa_nav_events_url', '' );
        $nav_map       = get_option( 'oha_spwa_nav_map_url', '' );

        $member_redirect  = get_option( 'oha_spwa_member_redirect', '' );
        $owner_redirect   = get_option( 'oha_spwa_owner_redirect', '' );
        $session_days     = get_option( 'oha_spwa_session_days', '30' );

        $sw_exists       = file_exists( ABSPATH . 'oha-site-sw.js' );
        $manifest_exists = file_exists( ABSPATH . 'oha-site-manifest.json' );
        ?>
        <div class="wrap">
            <h1>OHA Site PWA Settings</h1>

            <?php if ( $saved ) : ?>
            <div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
            <?php endif; ?>

            <table class="widefat" style="max-width:600px;margin-bottom:1rem;">
                <tbody>
                    <tr>
                        <td><strong>Service Worker</strong></td>
                        <td><?php echo $sw_exists ? '<span style="color:green;">&#10003; Written to web root</span>' : '<span style="color:red;">&#x26A0; Not found — deactivate and reactivate plugin</span>'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Manifest</strong></td>
                        <td><?php echo $manifest_exists ? '<span style="color:green;">&#10003; Written to web root</span>' : '<span style="color:red;">&#x26A0; Not found — save settings to generate</span>'; ?></td>
                    </tr>
                </tbody>
            </table>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'oha_spwa_save' ); ?>
                <input type="hidden" name="action" value="oha_spwa_save_settings">

                <h2>App Identity</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="oha_spwa_app_name">App Name</label></th>
                        <td><input type="text" id="oha_spwa_app_name" name="oha_spwa_app_name" value="<?php echo esc_attr( $app_name ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="oha_spwa_short_name">Short Name</label></th>
                        <td><input type="text" id="oha_spwa_short_name" name="oha_spwa_short_name" value="<?php echo esc_attr( $short_name ); ?>" class="regular-text">
                        <p class="description">Shown on home screen icon — keep under 12 characters.</p></td>
                    </tr>
                    <tr>
                        <th><label for="oha_spwa_start_url">Start URL</label></th>
                        <td><input type="text" id="oha_spwa_start_url" name="oha_spwa_start_url" value="<?php echo esc_attr( $start_url ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="oha_spwa_icon_url">App Icon URL</label></th>
                        <td><input type="url" id="oha_spwa_icon_url" name="oha_spwa_icon_url" value="<?php echo esc_attr( $icon_url ); ?>" class="regular-text">
                        <p class="description">512x512 PNG. Used in manifest and as iOS touch icon.</p></td>
                    </tr>
                    <tr>
                        <th><label for="oha_spwa_mask_icon_url">Maskable Icon URL</label></th>
                        <td><input type="url" id="oha_spwa_mask_icon_url" name="oha_spwa_mask_icon_url" value="<?php echo esc_attr( $mask_icon_url ); ?>" class="regular-text">
                        <p class="description">512x512 PNG with safe zone padding. Required for Android adaptive icon.</p></td>
                    </tr>
                    <tr>
                        <th><label for="oha_spwa_theme_color">Theme Color</label></th>
                        <td><input type="text" id="oha_spwa_theme_color" name="oha_spwa_theme_color" value="<?php echo esc_attr( $theme_color ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="oha_spwa_bg_color">Background Color</label></th>
                        <td><input type="text" id="oha_spwa_bg_color" name="oha_spwa_bg_color" value="<?php echo esc_attr( $bg_color ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="oha_spwa_offline_url">Offline Page URL</label></th>
                        <td><input type="text" id="oha_spwa_offline_url" name="oha_spwa_offline_url" value="<?php echo esc_attr( $offline_url ); ?>" class="regular-text">
                        <p class="description">Create a WordPress page at this path for a branded offline experience.</p></td>
                    </tr>
                </table>

                <h2>Login</h2>
                <p class="description" style="margin-bottom:.75rem;">Where users land after logging in. Leave blank to fall back to the home page. The modal login that works in-app handles the redirect automatically.</p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="oha_spwa_member_redirect">OHA Member Redirect</label></th>
                        <td><input type="text" id="oha_spwa_member_redirect" name="oha_spwa_member_redirect" value="<?php echo esc_attr( $member_redirect ); ?>" class="regular-text" placeholder="/members/">
                        <p class="description">Users with an OHA membership (oha_member_id). Example: <code>/members/</code></p></td>
                    </tr>
                    <tr>
                        <th><label for="oha_spwa_owner_redirect">Haunt Owner Redirect</label></th>
                        <td><input type="text" id="oha_spwa_owner_redirect" name="oha_spwa_owner_redirect" value="<?php echo esc_attr( $owner_redirect ); ?>" class="regular-text" placeholder="/my-listing/">
                        <p class="description">Users with at least one GeoDirectory listing. Example: <code>/my-listing/</code></p></td>
                    </tr>
                    <tr>
                        <th><label for="oha_spwa_session_days">Session Length (days)</label></th>
                        <td><input type="number" id="oha_spwa_session_days" name="oha_spwa_session_days" value="<?php echo esc_attr( $session_days ); ?>" class="small-text" min="1" max="365"> days
                        <p class="description">How long users stay logged in. Default 30. WordPress default without "Remember me" is 2 days.</p></td>
                    </tr>
                </table>

                <h2>Push Notifications (OneSignal)</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="oha_spwa_push_enabled">Enable Push</label></th>
                        <td><label><input type="checkbox" id="oha_spwa_push_enabled" name="oha_spwa_push_enabled" value="1" <?php checked( $push_enabled, '1' ); ?>> Enable OneSignal push notifications</label></td>
                    </tr>
                    <tr>
                        <th><label for="oha_spwa_onesignal_app_id">App ID</label></th>
                        <td><input type="text" id="oha_spwa_onesignal_app_id" name="oha_spwa_onesignal_app_id" value="<?php echo esc_attr( $app_id ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="oha_spwa_onesignal_api_key">Legacy API Key</label></th>
                        <td>
                            <input type="password" id="oha_spwa_onesignal_api_key" name="oha_spwa_onesignal_api_key" value="" class="regular-text" autocomplete="new-password">
                            <?php if ( $has_api_key ) : ?>
                            <p class="description">A key is saved. Leave blank to keep the existing key; enter a new value to replace it.</p>
                            <?php else : ?>
                            <p class="description">Found in OneSignal dashboard under Settings &gt; Keys &amp; IDs. Used for server-side sends only.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <h2>Navigation Tabs</h2>
                <p class="description" style="margin-bottom:.75rem;">Tab order: Haunts | Events | Home (center, always shown) | Map | My OHA. Leave a URL blank to hide that tab.</p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="oha_spwa_nav_listings_url">Haunts</label></th>
                        <td><input type="text" id="oha_spwa_nav_listings_url" name="oha_spwa_nav_listings_url" value="<?php echo esc_attr( $nav_listings ); ?>" class="regular-text" placeholder="/places/"></td>
                    </tr>
                    <tr>
                        <th><label for="oha_spwa_nav_events_url">Events</label></th>
                        <td><input type="text" id="oha_spwa_nav_events_url" name="oha_spwa_nav_events_url" value="<?php echo esc_attr( $nav_events ); ?>" class="regular-text" placeholder="/events/"></td>
                    </tr>
                    <tr>
                        <th><label for="oha_spwa_nav_home_url">Home (center, elevated)</label></th>
                        <td><input type="text" id="oha_spwa_nav_home_url" name="oha_spwa_nav_home_url" value="<?php echo esc_attr( $nav_home ); ?>" class="regular-text">
                        <p class="description">Defaults to site home if blank.</p></td>
                    </tr>
                    <tr>
                        <th><label for="oha_spwa_nav_map_url">Map</label></th>
                        <td><input type="text" id="oha_spwa_nav_map_url" name="oha_spwa_nav_map_url" value="<?php echo esc_attr( $nav_map ); ?>" class="regular-text" placeholder="/haunt-map/"></td>
                    </tr>
                    <tr>
                        <th><label for="oha_spwa_nav_myoha_url">My OHA</label></th>
                        <td><input type="text" id="oha_spwa_nav_myoha_url" name="oha_spwa_nav_myoha_url" value="<?php echo esc_attr( $nav_myoha ); ?>" class="regular-text" placeholder="/my-oha/"></td>
                    </tr>
                </table>

                <?php submit_button( 'Save Settings' ); ?>
            </form>
        </div>
        <?php
    }
}
