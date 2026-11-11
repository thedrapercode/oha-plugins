<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Login reliability layer for the OHA Site PWA.
 *
 * Fixes three distinct failure modes:
 *
 * 1. WRONG REDIRECT IN PWA STANDALONE MODE
 *    UWP "Last User Page" uses wp_get_referer() (HTTP_REFERER header).
 *    In PWA standalone mode, the browser does not send a Referer header for
 *    AJAX requests, so wp_get_referer() returns empty and UWP falls back to
 *    home_url('/').  Fix: JS injects `redirect_to = current URL` into every
 *    UWP modal form submit before the AJAX fires.  UWP checks $_REQUEST['redirect_to']
 *    first (class-forms.php line 1562), so this takes highest priority.
 *
 * 2. REDIRECT TO WP-ADMIN FOR FRONT-END USERS
 *    Non-admin users who somehow reach wp-login.php directly (or whose UWP
 *    redirect still points at wp-admin) get a PHP-level override via both
 *    login_redirect (WP native) and uwp_login_redirect (UWP's final filter).
 *
 * 3. SHORT SESSION COOKIES LOGGING USERS OUT CONSTANTLY
 *    WP default is 2 days without "Remember me".  PWA users expect native-app
 *    behaviour.  Extended to 30 days (configurable).
 */
class OHA_Site_PWA_Login {

    public static function init() {
        // PHP redirect filters
        add_filter( 'uwp_login_redirect', array( __CLASS__, 'uwp_redirect' ), 20, 4 );
        add_filter( 'login_redirect',     array( __CLASS__, 'wp_redirect' ),  20, 3 );

        // Extended session
        add_filter( 'auth_cookie_expiration', array( __CLASS__, 'extend_session' ), 10, 3 );

        // JS: inject redirect_to into UWP modal forms + clear SW page cache on new login
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_login_js' ) );
    }

    // ------------------------------------------------------------------
    // 1. UWP redirect (fires last in UWP's get_login_redirect_url)
    // ------------------------------------------------------------------

    public static function uwp_redirect( $redirect_to, $redirect_page_id, $data, $user ) {
        if ( is_wp_error( $user ) ) return $redirect_to;

        // Board/admin: keep whatever UWP decided
        if ( user_can( $user, 'manage_options' ) || user_can( $user, 'edit_posts' ) ) {
            return $redirect_to;
        }

        // If the redirect is going somewhere sensible (not wp-admin, not login page), keep it.
        if ( ! self::is_bad_redirect( $redirect_to ) ) {
            return $redirect_to;
        }

        return self::role_redirect( $user );
    }

    // ------------------------------------------------------------------
    // 2. Standard WP login redirect (backup for direct wp-login.php access)
    // ------------------------------------------------------------------

    public static function wp_redirect( $redirect_to, $requested, $user ) {
        if ( is_wp_error( $user ) ) return $redirect_to;

        if ( user_can( $user, 'manage_options' ) || user_can( $user, 'edit_posts' ) ) {
            return $redirect_to;
        }

        if ( ! empty( $requested ) && strpos( $requested, admin_url() ) === false ) {
            return $requested;
        }

        return self::role_redirect( $user );
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private static function is_bad_redirect( $url ) {
        // wp-admin
        if ( strpos( $url, admin_url() ) === 0 ) return true;

        // UWP login page itself
        if ( function_exists( 'uwp_get_page_id' ) ) {
            $login_id  = (int) uwp_get_page_id( 'login_page', 0 );
            $login_url = $login_id ? trailingslashit( get_permalink( $login_id ) ) : '';
            if ( $login_url && trailingslashit( $url ) === $login_url ) return true;
        }

        return false;
    }

    private static function role_redirect( $user ) {
        // OHA member
        $member_url = get_option( 'oha_spwa_member_redirect', '' );
        if ( $member_url && get_user_meta( $user->ID, 'oha_member_id', true ) ) {
            return esc_url_raw( $member_url );
        }

        // Haunt listing owner
        $owner_url = get_option( 'oha_spwa_owner_redirect', '' );
        if ( $owner_url && post_type_exists( 'gd_place' ) ) {
            $listing = get_posts( array(
                'post_type'   => 'gd_place',
                'author'      => $user->ID,
                'numberposts' => 1,
                'fields'      => 'ids',
                'post_status' => array( 'publish', 'pending', 'draft' ),
            ) );
            if ( ! empty( $listing ) ) {
                return esc_url_raw( $owner_url );
            }
        }

        // General fallback: member URL if set, otherwise front page
        return $member_url ? esc_url_raw( $member_url ) : home_url( '/' );
    }

    // ------------------------------------------------------------------
    // 3. Session length
    // ------------------------------------------------------------------

    public static function extend_session( $expiration, $_user_id, $_remember ) {
        $days = max( 1, (int) get_option( 'oha_spwa_session_days', 30 ) );
        return max( $expiration, $days * DAY_IN_SECONDS );
    }

    // ------------------------------------------------------------------
    // 4. Front-end JS
    // ------------------------------------------------------------------

    public static function enqueue_login_js() {
        if ( is_admin() ) return;

        // Inject redirect_to = current page URL into UWP modal login forms just
        // before the AJAX fires.  This overrides UWP's "Last User Page" logic which
        // relies on HTTP_REFERER — a header PWA standalone mode does not send.
        //
        // Also: when the user logs in and returns to a page, tell the SW to drop
        // the page cache so logged-in content (member-specific UI etc.) loads fresh.

        $script = <<<'JS'
(function() {
    // --- Inject redirect_to into UWP modal login form submissions ---
    document.addEventListener('submit', function(e) {
        var form = e.target;
        if (!form || typeof form.classList === 'undefined') return;
        if (!form.classList.contains('uwp-login-form')) return;
        if (form.querySelector('[name="redirect_to"]')) return; // already set

        // Only for modal forms (inside the lightbox) — standalone login pages
        // should use their own configured redirect, not "current page".
        var inModal = form.closest && (
            form.closest('#uwp_login_modal') ||
            form.closest('.uwp-auth-modal')
        );
        if (!inModal) return;

        var inp = document.createElement('input');
        inp.type  = 'hidden';
        inp.name  = 'redirect_to';
        inp.value = window.location.href.replace(/#.*$/, '');
        form.appendChild(inp);
    }, true); // capture phase: fires before jQuery submit handler

    // --- Clear SW page cache when user has just logged in ---
    // We write a session cookie (oha_pwa_s) server-side when logged-in.
    // On the client side we store the last-known user ID in sessionStorage.
    // When they differ, the user just logged in this session.
    if (!('serviceWorker' in navigator) || !navigator.serviceWorker.controller) return;

    var cookieUid = (document.cookie.match(/oha_pwa_s=(\d+)/) || [])[1] || '0';
    var stored    = sessionStorage.getItem('oha_uid') || '0';

    if (cookieUid !== '0' && cookieUid !== stored) {
        sessionStorage.setItem('oha_uid', cookieUid);
        navigator.serviceWorker.controller.postMessage({ action: 'CLEAR_PAGE_CACHE' });
    }
})();
JS;

        wp_add_inline_script(
            'oha-site-pwa-init',
            $script,
            'after'
        );

        // Write a lightweight session marker cookie so the JS above can detect
        // "user just logged in this browser session" without a server round-trip.
        if ( is_user_logged_in() ) {
            $uid = get_current_user_id();
            // SameSite=Lax works for all same-site page loads including PWA.
            // HttpOnly=false intentionally — the JS above needs to read it.
            if ( ! isset( $_COOKIE['oha_pwa_s'] ) || (int) $_COOKIE['oha_pwa_s'] !== $uid ) {
                setcookie( 'oha_pwa_s', (string) $uid, time() + 30 * DAY_IN_SECONDS, '/', '', is_ssl(), false );
            }
        }
    }
}
