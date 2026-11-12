<?php
/**
 * Plugin Name: OHA Profile Hub
 * Description: Custom account dashboard for the OHA haunt-profile page. Replaces the UWP iframe with a clean, app-ready account management experience for PWA, mobile, and desktop.
 * Version: 1.1.0
 * Author: OHA Dev
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OHA_PROFILE_HUB_VERSION', '1.1.0' );
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
        add_shortcode( 'oha_account_dashboard', array( $this, 'render_dashboard' ) );
        add_filter( 'body_class', array( $this, 'panel_body_class' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_panel_assets' ) );
    }

    // Add body class when UWP account page is loaded inside an oha panel iframe
    public function panel_body_class( $classes ) {
        if ( ! empty( $_GET['oha_panel'] ) ) {
            $classes[] = 'oha-panel-view';
        }
        return $classes;
    }

    // Enqueue CSS/JS on the UWP account page when loaded in panel mode so
    // Kadence chrome and UWP tab sidebar are stripped via oha-panel-view rules
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

    // ------------------------------------------------------------------
    // [oha_account_dashboard]
    // Renders profile card + account action rows + membership status.
    // Replace [oha_uwp_account_inline] with this in UWP Form Builder
    // under Profile Tabs > My Account tab content.
    // UWP account forms open in a slide-in panel (iframe) -- no page navigation.
    // ------------------------------------------------------------------
    public function render_dashboard( $atts ) {
        $this->enqueue_assets();

        if ( ! is_user_logged_in() ) {
            return '<div class="oha-prohub-wrap"><p class="oha-prohub-login-notice">Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to manage your account.</p></div>';
        }

        $user    = wp_get_current_user();
        $user_id = $user->ID;

        // Avatar
        $avatar = get_avatar( $user_id, 80, '', '', array( 'class' => 'oha-prohub-avatar-img' ) );

        // Names
        $display_name = $user->display_name ?: $user->user_login;
        $username     = $user->user_login;

        // Membership status
        $member_id    = get_user_meta( $user_id, 'oha_member_id', true );
        $trial_end    = get_user_meta( $user_id, 'membership_trial_end', true );
        $member_since = get_user_meta( $user_id, 'oha_paid_member_since', true );
        $is_active    = $member_id && $trial_end && strtotime( $trial_end ) > time();
        $is_expired   = $member_id && $trial_end && strtotime( $trial_end ) <= time();

        // WooCommerce orders
        $has_orders = false;
        $orders_url = '';
        if ( function_exists( 'wc_get_orders' ) && function_exists( 'wc_get_account_endpoint_url' ) ) {
            $orders = wc_get_orders( array( 'customer' => $user_id, 'limit' => 1, 'return' => 'ids' ) );
            if ( ! empty( $orders ) ) {
                $has_orders = true;
                $orders_url = wc_get_account_endpoint_url( 'orders' );
            }
        }

        // UWP account base URL
        $account_base = '/myaccount/';
        if ( function_exists( 'uwp_get_page_id' ) ) {
            $acct_id = uwp_get_page_id( 'account_page', false );
            if ( $acct_id ) {
                $account_base = trailingslashit( get_permalink( $acct_id ) );
            }
        }

        ob_start();
        ?>
        <div class="oha-prohub-wrap">

            <div class="oha-prohub-card oha-prohub-profile-card">
                <div class="oha-prohub-avatar-wrap"><?php echo $avatar; ?></div>
                <div class="oha-prohub-identity">
                    <div class="oha-prohub-display-name"><?php echo esc_html( $display_name ); ?></div>
                    <div class="oha-prohub-username">@<?php echo esc_html( $username ); ?></div>
                    <?php if ( $is_active ) : ?>
                        <span class="oha-prohub-badge oha-prohub-badge-active">OHA Member</span>
                    <?php elseif ( $is_expired ) : ?>
                        <span class="oha-prohub-badge oha-prohub-badge-expired">Membership Expired</span>
                    <?php else : ?>
                        <span class="oha-prohub-badge oha-prohub-badge-guest">Registered User</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="oha-prohub-card oha-prohub-actions-card">
                <div class="oha-prohub-card-label">Account</div>

                <button type="button" class="oha-prohub-action-row"
                    data-panel-url="<?php echo esc_url( $account_base . '?type=account' ); ?>"
                    data-panel-title="Edit Account">
                    <span class="oha-prohub-action-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </span>
                    <span class="oha-prohub-action-label">Edit Account</span>
                    <span class="oha-prohub-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg></span>
                </button>

                <button type="button" class="oha-prohub-action-row"
                    data-panel-url="<?php echo esc_url( $account_base . '?type=change-password' ); ?>"
                    data-panel-title="Change Password">
                    <span class="oha-prohub-action-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </span>
                    <span class="oha-prohub-action-label">Change Password</span>
                    <span class="oha-prohub-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg></span>
                </button>

                <button type="button" class="oha-prohub-action-row"
                    data-panel-url="<?php echo esc_url( $account_base . '?type=privacy' ); ?>"
                    data-panel-title="Privacy Settings">
                    <span class="oha-prohub-action-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </span>
                    <span class="oha-prohub-action-label">Privacy Settings</span>
                    <span class="oha-prohub-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg></span>
                </button>

                <?php if ( $has_orders && $orders_url ) : ?>
                <a href="<?php echo esc_url( $orders_url ); ?>" class="oha-prohub-action-row">
                    <span class="oha-prohub-action-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    </span>
                    <span class="oha-prohub-action-label">Track Orders</span>
                    <span class="oha-prohub-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg></span>
                </a>
                <?php endif; ?>

            </div>

            <?php if ( $member_id ) : ?>
            <div class="oha-prohub-card oha-prohub-membership-card">
                <div class="oha-prohub-card-label">Membership</div>
                <div class="oha-prohub-mem-row">
                    <span class="oha-prohub-mem-key">Status</span>
                    <span class="oha-prohub-mem-val <?php echo $is_active ? 'oha-prohub-active' : 'oha-prohub-expired'; ?>">
                        <?php echo $is_active ? 'Active' : 'Expired'; ?>
                    </span>
                </div>
                <?php if ( $member_since ) : ?>
                <div class="oha-prohub-mem-row">
                    <span class="oha-prohub-mem-key">Member since</span>
                    <span class="oha-prohub-mem-val"><?php echo esc_html( date( 'F j, Y', strtotime( $member_since ) ) ); ?></span>
                </div>
                <?php endif; ?>
                <?php if ( $trial_end ) : ?>
                <div class="oha-prohub-mem-row">
                    <span class="oha-prohub-mem-key"><?php echo $is_active ? 'Renews' : 'Expired'; ?></span>
                    <span class="oha-prohub-mem-val"><?php echo esc_html( date( 'F j, Y', strtotime( $trial_end ) ) ); ?></span>
                </div>
                <?php endif; ?>
                <div class="oha-prohub-mem-row">
                    <span class="oha-prohub-mem-key">Member ID</span>
                    <span class="oha-prohub-mem-val oha-prohub-mem-id"><?php echo esc_html( $member_id ); ?></span>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- Slide-in panel for UWP account forms (loads /myaccount/?type=X in iframe) -->
        <div id="oha-prohub-panel" class="oha-prohub-panel" aria-hidden="true" role="dialog" aria-modal="true">
            <div class="oha-prohub-panel-bar">
                <button type="button" id="oha-prohub-panel-back" class="oha-prohub-panel-back" aria-label="Back">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
                    <span>Back</span>
                </button>
                <span id="oha-prohub-panel-title" class="oha-prohub-panel-title"></span>
                <span class="oha-prohub-panel-bar-end"></span>
            </div>
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
