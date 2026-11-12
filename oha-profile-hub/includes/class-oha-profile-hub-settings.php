<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OHA_Profile_Hub_Settings {

    public function init() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function register_menu() {
        add_options_page(
            'OHA Profile Hub',
            'OHA Profile Hub',
            'manage_options',
            'oha-profile-hub',
            array( $this, 'render_page' )
        );
    }

    public function register_settings() {
        // General
        register_setting( 'oha_profile_hub', 'oha_phub_account_tab_label',
            array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'oha_profile_hub', 'oha_phub_renew_url',
            array( 'sanitize_callback' => 'esc_url_raw' ) );

        // Per-tab label + shortcode
        $slugs = array( 'listings', 'events', 'favorites', 'discounts', 'resources' );
        foreach ( $slugs as $slug ) {
            register_setting( 'oha_profile_hub', 'oha_phub_tab_' . $slug . '_label',
                array( 'sanitize_callback' => 'sanitize_text_field' ) );
            register_setting( 'oha_profile_hub', 'oha_phub_tab_' . $slug . '_sc',
                array( 'sanitize_callback' => array( $this, 'sanitize_shortcode' ) ) );
        }
    }

    // Allow shortcode brackets but strip actual HTML tags
    public function sanitize_shortcode( $value ) {
        return wp_kses( trim( $value ), array() );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $tabs = array(
            'listings'  => array( 'label' => 'Listings',         'placeholder_sc' => '[gd_listings post_author="current_user" post_type="gd_place"]' ),
            'events'    => array( 'label' => 'Exclusive Events',  'placeholder_sc' => '[gd_listings post_type="gd_event" event_type="upcoming" posts_per_page="10" map="0" show_paging="1" layout="list"][oha_education_ondemand]' ),
            'favorites' => array( 'label' => 'Favorites',         'placeholder_sc' => '[gd_listings favorites_by_user="current_user" post_type="gd_place"]' ),
            'discounts' => array( 'label' => 'Discounts',         'placeholder_sc' => '[oha_discounts]' ),
            'resources' => array( 'label' => 'Resources',         'placeholder_sc' => '[qcopd-directory mode="all" column="2" style="style-1" orderby="date" order="DESC" per_page="2" enable_embedding="false"]' ),
        );

        $saved = isset( $_GET['settings-updated'] ) && $_GET['settings-updated'];
        ?>
        <div class="wrap">
            <h1>OHA Profile Hub Settings</h1>
            <p>Configure labels and content for the <code>[oha_profile_hub]</code> shortcode. Leave any field blank to use the built-in default shown as placeholder text.</p>

            <?php if ( $saved ) : ?>
            <div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields( 'oha_profile_hub' ); ?>

                <h2 class="title">General</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="oha_phub_account_tab_label">My Account tab label</label></th>
                        <td>
                            <input type="text"
                                   id="oha_phub_account_tab_label"
                                   name="oha_phub_account_tab_label"
                                   value="<?php echo esc_attr( get_option( 'oha_phub_account_tab_label', '' ) ); ?>"
                                   placeholder="My Account"
                                   class="regular-text" />
                            <p class="description">The first tab label. Default: <em>My Account</em></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="oha_phub_renew_url">Renew Membership URL</label></th>
                        <td>
                            <input type="text"
                                   id="oha_phub_renew_url"
                                   name="oha_phub_renew_url"
                                   value="<?php echo esc_attr( get_option( 'oha_phub_renew_url', '' ) ); ?>"
                                   placeholder="/membership/"
                                   class="regular-text" />
                            <p class="description">URL for the Renew Membership button shown to expired members. Default: <em>/membership/</em></p>
                        </td>
                    </tr>
                </table>

                <h2 class="title" style="margin-top:2em;">Extra Tabs</h2>
                <p>Each extra tab appears after My Account in the tab bar. Configure the label and the shortcode(s) to render in that tab's content area.</p>

                <table class="form-table" role="presentation">
                <?php foreach ( $tabs as $slug => $info ) :
                    $label_opt = get_option( 'oha_phub_tab_' . $slug . '_label', '' );
                    $sc_opt    = get_option( 'oha_phub_tab_' . $slug . '_sc', '' );
                    ?>
                    <tr>
                        <th scope="row" style="padding-top:24px;border-top:1px solid #eee;">
                            <strong><?php echo esc_html( $info['label'] ); ?></strong>
                        </th>
                        <td style="padding-top:24px;border-top:1px solid #eee;"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="oha_phub_tab_<?php echo esc_attr( $slug ); ?>_label">Tab label</label></th>
                        <td>
                            <input type="text"
                                   id="oha_phub_tab_<?php echo esc_attr( $slug ); ?>_label"
                                   name="oha_phub_tab_<?php echo esc_attr( $slug ); ?>_label"
                                   value="<?php echo esc_attr( $label_opt ); ?>"
                                   placeholder="<?php echo esc_attr( $info['label'] ); ?>"
                                   class="regular-text" />
                            <p class="description">Default: <em><?php echo esc_html( $info['label'] ); ?></em></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="oha_phub_tab_<?php echo esc_attr( $slug ); ?>_sc">Shortcode content</label></th>
                        <td>
                            <textarea id="oha_phub_tab_<?php echo esc_attr( $slug ); ?>_sc"
                                      name="oha_phub_tab_<?php echo esc_attr( $slug ); ?>_sc"
                                      rows="3"
                                      class="large-text code"
                                      placeholder="<?php echo esc_attr( $info['placeholder_sc'] ); ?>"
                            ><?php echo esc_textarea( $sc_opt ); ?></textarea>
                            <p class="description">Leave blank to use the built-in default shown above.</p>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </table>

                <?php submit_button( 'Save Settings' ); ?>
            </form>
        </div>
        <?php
    }
}
