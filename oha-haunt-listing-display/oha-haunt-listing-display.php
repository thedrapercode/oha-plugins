<?php
/**
 * Plugin Name: OHA Haunt Listing Display
 * Description: Custom single listing page template for GeoDirectory gd_place listings. OHA-branded layout with hero, info card, gallery, nearby haunts, integrated claim workflow, and report post. Configure via GeoDirectory > OHA Listing Display.
 * Version:     1.15.1
 * Author:      OHA
 * Requires Plugins: geodirectory
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OHA_HLD_VERSION', '1.15.1' );
define( 'OHA_HLD_PATH', plugin_dir_path( __FILE__ ) );
define( 'OHA_HLD_URL',  plugin_dir_url( __FILE__ ) );

// ── Includes ──────────────────────────────────────────────────────────────────
require_once OHA_HLD_PATH . 'includes/class-oha-hld-admin.php';
require_once OHA_HLD_PATH . 'includes/class-oha-hld-claim.php';
require_once OHA_HLD_PATH . 'includes/class-oha-hld-gd-form.php';

// Boot admin settings page, claim system, and GD form overlay + discount editing
OHA_HLD_Admin::init();
OHA_HLD_Claim::init();
OHA_HLD_GD_Form::init();

/**
 * Enqueue styles + scripts on gd_place singles only.
 */
add_action( 'wp_enqueue_scripts', function() {
    if ( ! is_singular( 'gd_place' ) ) return;

    wp_enqueue_style(
        'oha-haunt-listing-display',
        OHA_HLD_URL . 'assets/listing.css',
        [],
        OHA_HLD_VERSION
    );

    wp_enqueue_script(
        'oha-haunt-listing-display',
        OHA_HLD_URL . 'assets/listing.js',
        [ 'jquery' ],
        OHA_HLD_VERSION,
        true
    );

    wp_localize_script( 'oha-haunt-listing-display', 'OHA_LISTING', [
        'ajax'       => admin_url( 'admin-ajax.php' ),
        'isLoggedIn' => is_user_logged_in(),
        'nonce'      => wp_create_nonce( 'oha_hld_claim_nonce_action' ),
    ] );
} );

/**
 * Enqueue OHA dark-theme CSS on GeoDirectory archive and search pages.
 * Also loads on GD category/listing pages so the same theme applies sitewide
 * wherever GD renders its card grid.
 */
add_action( 'wp_enqueue_scripts', function() {
    if ( ! function_exists( 'geodir_is_page' ) ) return;
    if ( geodir_is_page( 'archive' ) || geodir_is_page( 'search' ) || geodir_is_page( 'listing' ) ) {
        wp_enqueue_style(
            'oha-hld-archive',
            OHA_HLD_URL . 'assets/archive.css',
            [],
            OHA_HLD_VERSION
        );
    }
} );

/**
 * Add oha-gd-archive body class on GeoDirectory archive and search pages.
 * archive.css scopes all rules to this class to prevent bleed into other pages.
 */
add_filter( 'body_class', function( $classes ) {
    if ( ! function_exists( 'geodir_is_page' ) ) return $classes;
    if ( geodir_is_page( 'archive' ) || geodir_is_page( 'search' ) || geodir_is_page( 'listing' ) ) {
        $classes[] = 'oha-gd-archive';
    }
    return $classes;
} );

/**
 * Add oha-has-discount class to archive card items that have an active OHA discount.
 * archive.css uses this class to show the gold discount badge on the card.
 */
add_filter( 'post_class', function( $classes, $class, $post_id ) {
    if ( ! function_exists( 'geodir_is_page' ) ) return $classes;
    if ( ! geodir_is_page( 'archive' ) && ! geodir_is_page( 'search' ) && ! geodir_is_page( 'listing' ) ) {
        return $classes;
    }
    if ( get_post_meta( $post_id, '_oha_disc_active', true ) !== '1' ) return $classes;
    $today = date( 'Y-m-d' );
    $start = get_post_meta( $post_id, '_oha_disc_start', true );
    $end   = get_post_meta( $post_id, '_oha_disc_end',   true );
    if ( $start && $today < $start ) return $classes;
    if ( $end   && $today > $end   ) return $classes;
    $classes[] = 'oha-has-discount';
    $rate = get_post_meta( $post_id, '_oha_disc_rate', true );
    if ( $rate ) {
        $classes[] = 'oha-disc-' . sanitize_html_class( strtolower( preg_replace( '/[^a-zA-Z0-9]/', '', $rate ) ) );
    }
    return $classes;
}, 10, 3 );

/**
 * Inject discount rate badge text onto archive cards.
 *
 * CSS ::after can only show hardcoded strings, so we output an inline script
 * that builds a {post_id: rate} map from the current query, then appends a
 * .oha-arc-disc-badge <span> to each .geodir-post.oha-has-discount .card-body.
 * GD already outputs data-post-id on the outer card div, which is how we match.
 */
add_action( 'wp_footer', function() {
    if ( ! function_exists( 'geodir_is_page' ) ) return;
    if ( ! geodir_is_page( 'archive' ) && ! geodir_is_page( 'search' ) && ! geodir_is_page( 'listing' ) ) return;

    global $wp_query;
    if ( empty( $wp_query->posts ) ) return;

    $rates = [];
    $today = date( 'Y-m-d' );

    foreach ( $wp_query->posts as $p ) {
        if ( get_post_meta( $p->ID, '_oha_disc_active', true ) !== '1' ) continue;
        $start = get_post_meta( $p->ID, '_oha_disc_start', true );
        $end   = get_post_meta( $p->ID, '_oha_disc_end',   true );
        if ( $start && $today < $start ) continue;
        if ( $end   && $today > $end   ) continue;
        $rate = get_post_meta( $p->ID, '_oha_disc_rate', true );
        if ( $rate ) {
            $rates[ (string) $p->ID ] = $rate;
        }
    }

    if ( empty( $rates ) ) return;

    ?>
    <script>
    (function(){
        var d = <?php echo wp_json_encode( $rates ); ?>;
        document.querySelectorAll('.geodir-post.oha-has-discount[data-post-id]').forEach(function(el){
            var id = el.getAttribute('data-post-id');
            if (!d[id]) return;
            var body = el.querySelector('.card-body');
            if (!body) return;
            var badge = document.createElement('span');
            badge.className = 'oha-arc-disc-badge';
            badge.textContent = d[id] + ' Member Discount';
            body.appendChild(badge);
        });
    })();
    </script>
    <?php
}, 20 );

/**
 * Override GeoDirectory single template content for gd_place.
 *
 * AUDIT CORRECTION: The filter name in the handover doc was incorrect.
 * The correct filter is 'geodir_overwrite_single_template_content' (3 args).
 * Found in: geodirectory/includes/class-geodir-template-loader.php:531
 */
add_filter( 'geodir_overwrite_single_template_content', function( $overwrite_content, $content, $page_id ) {
    global $post;

    // Only override for gd_place post type
    if ( empty( $post ) || $post->post_type !== 'gd_place' ) {
        return $overwrite_content;
    }

    $template = OHA_HLD_PATH . 'templates/content-single.php';

    if ( ! file_exists( $template ) ) {
        return $overwrite_content;
    }

    ob_start();
    include $template;
    return ob_get_clean();

}, 10, 3 );
