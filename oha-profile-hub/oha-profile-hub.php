<?php
/**
 * Plugin Name: OHA Profile Hub
 * Description: Full-page account dashboard for the OHA haunt-profile experience. Single-page design with in-page tabs, profile hero, account management panels, and membership status. Adapts to PWA, mobile, and desktop.
 * Version: 1.4.1
 * Author: OHA Dev
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OHA_PROFILE_HUB_VERSION', '1.4.1' );

// Auto-update check via GitHub releases
add_filter( 'pre_set_site_transient_update_plugins', 'oha_prh_check_for_update' );
function oha_prh_check_for_update( $transient ) {
    if ( empty( $transient->checked ) ) {
        return $transient;
    }
    $response = wp_remote_get(
        'https://raw.githubusercontent.com/thedrapercode/oha-plugins/main/updates/profile-hub.json',
        [ 'timeout' => 10, 'sslverify' => true ]
    );
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return $transient;
    }
    $data = json_decode( wp_remote_retrieve_body( $response ) );
    if ( ! $data || empty( $data->version ) ) {
        return $transient;
    }
    if ( version_compare( $data->version, OHA_PROFILE_HUB_VERSION, '>' ) ) {
        $transient->response[ plugin_basename( __FILE__ ) ] = (object) [
            'slug'        => 'oha-profile-hub',
            'plugin'      => plugin_basename( __FILE__ ),
            'new_version' => $data->version,
            'package'     => $data->download_url,
            'url'         => '',
        ];
    }
    return $transient;
}
define( 'OHA_PROFILE_HUB_PATH', plugin_dir_path( __FILE__ ) );
define( 'OHA_PROFILE_HUB_URL', plugin_dir_url( __FILE__ ) );

require_once OHA_PROFILE_HUB_PATH . 'includes/class-oha-profile-hub-settings.php';

class OHA_Profile_Hub {

    private static $_instance   = null;
    private static $assets_done = false;

    public static function instance() {
        if ( null === self::$_instance ) self::$_instance = new self();
        return self::$_instance;
    }

    public function init() {
        add_shortcode( 'oha_profile_hub',      array( $this, 'render_hub' ) );
        add_shortcode( 'oha_account_dashboard', array( $this, 'render_hub' ) ); // backward compat alias
    }

    private function enqueue_assets() {
        if ( self::$assets_done ) return;
        wp_enqueue_style(
            'oha-profile-hub',
            OHA_PROFILE_HUB_URL . 'assets/css/profile-hub.css',
            array(),
            OHA_PROFILE_HUB_VERSION
        );
        wp_enqueue_script(
            'oha-profile-hub',
            OHA_PROFILE_HUB_URL . 'assets/js/profile-hub.js',
            array(),
            OHA_PROFILE_HUB_VERSION,
            true
        );
        self::$assets_done = true;
    }

    // -----------------------------------------------------------------------
    // [oha_profile_hub]  (also registered as [oha_account_dashboard])
    // Full-page profile hub: hero + in-page tabs + account management.
    // Place on /haunt-profile/ or any My OHA page.
    // -----------------------------------------------------------------------
    public function render_hub( $atts ) {
        $this->enqueue_assets();

        if ( ! is_user_logged_in() ) {
            ob_start();
            ?>
            <div class="oha-phub oha-phub-guest">
                <div class="oha-phub-login-notice">
                    <p>Please <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">log in</a> to manage your account.</p>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        $user    = wp_get_current_user();
        $user_id = $user->ID;

        // ---- Profile data ----
        $display_name = $user->display_name ?: $user->user_login;
        $username     = $user->user_login;
        $avatar       = get_avatar( $user_id, 96, '', '', array( 'class' => 'oha-phub-avatar-img' ) );

        // ---- Membership ----
        $member_id    = get_user_meta( $user_id, 'oha_member_id', true );
        $trial_end    = get_user_meta( $user_id, 'membership_trial_end', true );
        $member_since = get_user_meta( $user_id, 'oha_paid_member_since', true );
        $is_active    = $member_id && $trial_end && strtotime( $trial_end ) > time();
        $is_expired   = $member_id && $trial_end && strtotime( $trial_end ) <= time();

        // ---- WooCommerce orders ----
        $has_orders = false;
        $orders_url = '';
        if ( function_exists( 'wc_get_orders' ) && function_exists( 'wc_get_account_endpoint_url' ) ) {
            $orders = wc_get_orders( array( 'customer' => $user_id, 'limit' => 1, 'return' => 'ids' ) );
            if ( ! empty( $orders ) ) {
                $has_orders = true;
                $orders_url = wc_get_account_endpoint_url( 'orders' );
            }
        }

        // ---- UWP account base URL ----
        $account_base = '/myaccount/';
        if ( function_exists( 'uwp_get_page_id' ) ) {
            $acct_id = uwp_get_page_id( 'account_page', false );
            if ( $acct_id ) {
                $account_base = trailingslashit( get_permalink( $acct_id ) );
            }
        }

        // ---- Extra tabs -- content via WP options (shortcodes) or action hooks ----
        // Set oha_phub_tab_{slug}_label to rename a tab.
        // Set oha_phub_tab_{slug}_sc to override the shortcode for that tab's content.
        // Hook oha_phub_tab_{slug}_content($user_id) to inject content from another plugin.
        // Built-in defaults are used when no option or hook is configured.
        $extra_tabs = array(
            'listings'  => get_option( 'oha_phub_tab_listings_label',  'Listings' ),
            'events'    => get_option( 'oha_phub_tab_events_label',    'Exclusive Events' ),
            'favorites' => get_option( 'oha_phub_tab_favorites_label', 'Favorites' ),
            'discounts' => get_option( 'oha_phub_tab_discounts_label', 'Discounts' ),
            'resources' => get_option( 'oha_phub_tab_resources_label', 'Resources' ),
        );

        // Default shortcodes for known tabs -- used when no WP option override is set.
        // Override any of these by setting oha_phub_tab_{slug}_sc in wp_options.
        $tab_defaults = array(
            'listings'  => '[gd_listings post_author="current_user" post_type="gd_place"]',
            'events'    => '[gd_listings post_type="gd_event" event_type="upcoming" posts_per_page="10" map="0" show_paging="1" layout="list"]' . ( shortcode_exists( 'oha_education_ondemand' ) ? '[oha_education_ondemand]' : '' ),
            'favorites' => '[gd_listings favorites_by_user="current_user" post_type="gd_place"]',
            'discounts' => shortcode_exists( 'oha_discounts' )          ? '[oha_discounts]'          : '',
            'resources' => shortcode_exists( 'qcopd-directory' ) ? '[qcopd-directory mode="all" column="2" style="style-1" orderby="date" order="DESC" per_page="2" enable_embedding="false"]' : '',
        );

        ob_start();
        ?>
        <div class="oha-phub" id="oha-phub">

            <!-- ===================== HERO ===================== -->
            <div class="oha-phub-hero">
                <div class="oha-phub-hero-top">
                    <div class="oha-phub-avatar-ring"><?php echo $avatar; ?></div>
                    <div class="oha-phub-hero-info">
                        <div class="oha-phub-name"><?php echo esc_html( $display_name ); ?></div>
                        <div class="oha-phub-username">@<?php echo esc_html( $username ); ?></div>
                        <?php if ( $is_active ) : ?>
                            <span class="oha-phub-badge oha-phub-badge-active">OHA Member</span>
                        <?php elseif ( $is_expired ) : ?>
                            <span class="oha-phub-badge oha-phub-badge-expired">Membership Expired</span>
                        <?php else : ?>
                            <span class="oha-phub-badge oha-phub-badge-guest">Registered User</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ( $member_id ) : ?>
                <div class="oha-phub-stats">
                    <?php if ( $member_since ) : ?>
                    <div class="oha-phub-stat">
                        <span class="oha-phub-stat-label">Member Since</span>
                        <strong class="oha-phub-stat-val"><?php echo esc_html( date( 'M Y', strtotime( $member_since ) ) ); ?></strong>
                    </div>
                    <span class="oha-phub-stat-sep"></span>
                    <?php endif; ?>
                    <div class="oha-phub-stat">
                        <span class="oha-phub-stat-label">Member ID</span>
                        <strong class="oha-phub-stat-val oha-phub-mono"><?php echo esc_html( $member_id ); ?></strong>
                    </div>
                    <?php if ( $trial_end ) : ?>
                    <span class="oha-phub-stat-sep"></span>
                    <div class="oha-phub-stat">
                        <span class="oha-phub-stat-label"><?php echo $is_active ? 'Renews' : 'Expired'; ?></span>
                        <strong class="oha-phub-stat-val <?php echo $is_active ? '' : 'oha-phub-expired-txt'; ?>"><?php echo esc_html( date( 'M j, Y', strtotime( $trial_end ) ) ); ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- ===================== TAB BAR ===================== -->
            <div class="oha-phub-tabbar" role="tablist" aria-label="Profile sections">
                <button class="oha-phub-tab active" data-tab="account" role="tab" aria-selected="true"><?php echo esc_html( get_option( 'oha_phub_account_tab_label', 'My Account' ) ); ?></button>
                <?php foreach ( $extra_tabs as $slug => $label ) : ?>
                <button class="oha-phub-tab" data-tab="<?php echo esc_attr( $slug ); ?>" role="tab" aria-selected="false">
                    <?php echo esc_html( $label ); ?>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- ===================== TAB PANELS ===================== -->
            <div class="oha-phub-panels">

                <!-- MY ACCOUNT -->
                <div class="oha-phub-panel active" data-panel="account" role="tabpanel">
                    <div class="oha-phub-account-grid">

                        <!-- Account Settings card -->
                        <div class="oha-phub-card">
                            <div class="oha-phub-card-head">Account Settings</div>

                            <a href="<?php echo esc_url( $account_base . '?type=account' ); ?>" class="oha-phub-action-row">
                                <span class="oha-phub-action-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                </span>
                                <span class="oha-phub-action-label">Edit Account</span>
                                <span class="oha-phub-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg></span>
                            </a>

                            <a href="<?php echo esc_url( $account_base . '?type=change-password' ); ?>" class="oha-phub-action-row">
                                <span class="oha-phub-action-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                </span>
                                <span class="oha-phub-action-label">Change Password</span>
                                <span class="oha-phub-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg></span>
                            </a>

                            <a href="<?php echo esc_url( $account_base . '?type=privacy' ); ?>" class="oha-phub-action-row">
                                <span class="oha-phub-action-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                </span>
                                <span class="oha-phub-action-label">Privacy Settings</span>
                                <span class="oha-phub-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg></span>
                            </a>

                            <?php if ( $has_orders && $orders_url ) : ?>
                            <a href="<?php echo esc_url( $orders_url ); ?>" class="oha-phub-action-row">
                                <span class="oha-phub-action-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                                </span>
                                <span class="oha-phub-action-label">Track Orders</span>
                                <span class="oha-phub-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg></span>
                            </a>
                            <?php endif; ?>
                        </div>

                        <!-- Membership card -->
                        <?php if ( $member_id ) : ?>
                        <div class="oha-phub-card">
                            <div class="oha-phub-card-head">Membership</div>
                            <div class="oha-phub-info-row">
                                <span class="oha-phub-info-key">Status</span>
                                <span class="oha-phub-info-val <?php echo $is_active ? 'oha-phub-active-txt' : 'oha-phub-expired-txt'; ?>">
                                    <?php echo $is_active ? 'Active' : 'Expired'; ?>
                                </span>
                            </div>
                            <?php if ( $member_since ) : ?>
                            <div class="oha-phub-info-row">
                                <span class="oha-phub-info-key">Member Since</span>
                                <span class="oha-phub-info-val"><?php echo esc_html( date( 'F j, Y', strtotime( $member_since ) ) ); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ( $trial_end ) : ?>
                            <div class="oha-phub-info-row">
                                <span class="oha-phub-info-key"><?php echo $is_active ? 'Renews' : 'Expired'; ?></span>
                                <span class="oha-phub-info-val"><?php echo esc_html( date( 'F j, Y', strtotime( $trial_end ) ) ); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="oha-phub-info-row">
                                <span class="oha-phub-info-key">Member ID</span>
                                <span class="oha-phub-info-val oha-phub-mono"><?php echo esc_html( $member_id ); ?></span>
                            </div>
                            <?php if ( $is_expired ) : ?>
                            <div class="oha-phub-renew-cta">
                                <a href="<?php echo esc_url( get_option( 'oha_phub_renew_url', '/membership/' ) ); ?>" class="oha-phub-renew-btn">Renew Membership</a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                    </div><!-- .oha-phub-account-grid -->
                </div><!-- [account panel] -->

                <!-- EXTRA TABS: content via WP option shortcode or hook -->
                <?php foreach ( $extra_tabs as $slug => $label ) : ?>
                <div class="oha-phub-panel" data-panel="<?php echo esc_attr( $slug ); ?>" role="tabpanel">
                    <?php
                    $sc = get_option( 'oha_phub_tab_' . $slug . '_sc', '' );
                    if ( ! $sc && ! empty( $tab_defaults[ $slug ] ) ) {
                        $sc = $tab_defaults[ $slug ];
                    }
                    if ( $sc ) {
                        echo do_shortcode( $sc );
                    } else {
                        ob_start();
                        do_action( 'oha_phub_tab_' . $slug . '_content', $user_id );
                        $hook_output = ob_get_clean();
                        if ( $hook_output ) {
                            echo $hook_output;
                        } else {
                            ?>
                            <div class="oha-phub-tab-placeholder">
                                <div class="oha-phub-placeholder-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                </div>
                                <p class="oha-phub-placeholder-text"><?php echo esc_html( $label ); ?> content is configured separately.</p>
                                <p class="oha-phub-placeholder-sub">Set the <code>oha_phub_tab_<?php echo esc_html( $slug ); ?>_sc</code> option or hook <code>oha_phub_tab_<?php echo esc_html( $slug ); ?>_content</code> to populate this tab.</p>
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>
                <?php endforeach; ?>

            </div><!-- .oha-phub-panels -->

        </div><!-- .oha-phub -->

        <?php
        return ob_get_clean();
    }
}

add_action( 'plugins_loaded', function() {
    OHA_Profile_Hub::instance()->init();
    ( new OHA_Profile_Hub_Settings() )->init();
} );
