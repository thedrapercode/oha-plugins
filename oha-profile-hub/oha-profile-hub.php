<?php
/**
 * Plugin Name: OHA Profile Hub
 * Description: Full-page account dashboard for the OHA haunt-profile experience. Single-page design with in-page tabs, profile hero, account management panels, and membership status. Adapts to PWA, mobile, and desktop.
 * Version: 1.3.2
 * Author: OHA Dev
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OHA_PROFILE_HUB_VERSION', '1.3.2' );
define( 'OHA_PROFILE_HUB_PATH', plugin_dir_path( __FILE__ ) );
define( 'OHA_PROFILE_HUB_URL', plugin_dir_url( __FILE__ ) );

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
        add_filter( 'body_class', array( $this, 'body_classes' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_panel_assets' ) );
    }

    public function body_classes( $classes ) {
        if ( ! empty( $_GET['oha_panel'] ) ) {
            $classes[] = 'oha-panel-view';
        }
        return $classes;
    }

    // Enqueue on the UWP account page when loaded in panel mode (?oha_panel=1)
    public function maybe_enqueue_panel_assets() {
        if ( ! empty( $_GET['oha_panel'] ) ) {
            $this->enqueue_assets();
        }
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
            'resources' => '',
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
                <button class="oha-phub-tab active" data-tab="account" role="tab" aria-selected="true">My Account</button>
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

                            <button type="button" class="oha-phub-action-row"
                                data-panel-url="<?php echo esc_url( $account_base . '?type=account' ); ?>"
                                data-panel-type="account">
                                <span class="oha-phub-action-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                </span>
                                <span class="oha-phub-action-label">Edit Account</span>
                                <span class="oha-phub-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg></span>
                            </button>

                            <button type="button" class="oha-phub-action-row"
                                data-panel-url="<?php echo esc_url( $account_base . '?type=change-password' ); ?>"
                                data-panel-type="change-password">
                                <span class="oha-phub-action-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                </span>
                                <span class="oha-phub-action-label">Change Password</span>
                                <span class="oha-phub-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg></span>
                            </button>

                            <button type="button" class="oha-phub-action-row"
                                data-panel-url="<?php echo esc_url( $account_base . '?type=privacy' ); ?>"
                                data-panel-type="privacy">
                                <span class="oha-phub-action-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                </span>
                                <span class="oha-phub-action-label">Privacy Settings</span>
                                <span class="oha-phub-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg></span>
                            </button>

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
                                <a href="/membership/" class="oha-phub-renew-btn">Renew Membership</a>
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

        <!-- ===================== SLIDE-IN FORM PANEL ===================== -->
        <!-- Right-side drawer on desktop, full-screen on mobile/PWA.         -->
        <!-- Loads UWP account forms (/myaccount/?type=X) with stripped chrome. -->
        <div id="oha-prohub-panel" class="oha-prohub-panel" aria-hidden="true" role="dialog" aria-modal="true">
            <div class="oha-prohub-panel-bar">
                <button type="button" id="oha-prohub-panel-back" class="oha-prohub-panel-back" aria-label="Back to My Account">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
                    <span>My Account</span>
                </button>
                <span class="oha-prohub-panel-bar-end"></span>
            </div>
            <nav class="oha-prohub-panel-nav" aria-label="Account sections">
                <button type="button" class="oha-prohub-panel-nav-btn active"
                        data-type="account"
                        data-url="<?php echo esc_url( $account_base . '?type=account' ); ?>">
                    Edit Account
                </button>
                <button type="button" class="oha-prohub-panel-nav-btn"
                        data-type="change-password"
                        data-url="<?php echo esc_url( $account_base . '?type=change-password' ); ?>">
                    Password
                </button>
                <button type="button" class="oha-prohub-panel-nav-btn"
                        data-type="privacy"
                        data-url="<?php echo esc_url( $account_base . '?type=privacy' ); ?>">
                    Privacy
                </button>
            </nav>
            <iframe id="oha-prohub-panel-iframe"
                    class="oha-prohub-panel-iframe"
                    src=""
                    title="Account settings"
                    scrolling="yes"></iframe>
        </div>
        <?php
        return ob_get_clean();
    }
}

add_action( 'plugins_loaded', function() {
    OHA_Profile_Hub::instance()->init();
} );
