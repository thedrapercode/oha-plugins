<?php
/**
 * Plugin Name: OHA Haunt Listing Display
 * Description: Custom single listing page template for GeoDirectory gd_place listings. OHA-branded layout with hero, info card, gallery, nearby haunts, integrated claim workflow, and report post. Configure via GeoDirectory > OHA Listing Display.
 * Version:     1.18.0
 * Author:      OHA
 * Requires Plugins: geodirectory
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OHA_HLD_VERSION', '1.18.0' );

// Auto-update check via GitHub releases
add_filter( 'pre_set_site_transient_update_plugins', 'oha_hld_check_for_update' );
function oha_hld_check_for_update( $transient ) {
    if ( empty( $transient->checked ) ) {
        return $transient;
    }
    $response = wp_remote_get(
        'https://raw.githubusercontent.com/thedrapercode/oha-plugins/main/updates/haunt-listing-display.json',
        [ 'timeout' => 10, 'sslverify' => true ]
    );
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return $transient;
    }
    $data = json_decode( wp_remote_retrieve_body( $response ) );
    if ( ! $data || empty( $data->version ) ) {
        return $transient;
    }
    if ( version_compare( $data->version, OHA_HLD_VERSION, '>' ) ) {
        $transient->response[ plugin_basename( __FILE__ ) ] = (object) [
            'slug'        => 'oha-haunt-listing-display',
            'plugin'      => plugin_basename( __FILE__ ),
            'new_version' => $data->version,
            'package'     => $data->download_url,
            'url'         => '',
        ];
    }
    return $transient;
}
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
 * Returns true on any page where GD renders a card listing grid:
 * - Post type archive URLs (geodir_is_page('archive'))
 * - GD search results (geodir_is_page('search'))
 * - WordPress pages configured as GD archive pages (e.g. /places/, /events/)
 *
 * geodir_is_page('archive') misses the last group because it only matches
 * is_post_type_archive() URLs, not WordPress pages with GD blocks.
 * We cover them by comparing the current page ID against every registered
 * GD post type's configured archive page via geodir_archive_page_id().
 */
function oha_hld_is_gd_listing_context() {
    if ( ! function_exists( 'geodir_is_page' ) ) return false;
    if ( geodir_is_page( 'archive' ) || geodir_is_page( 'search' ) ) return true;
    if ( is_page() && function_exists( 'geodir_get_posttypes' ) && function_exists( 'geodir_archive_page_id' ) ) {
        $current_id = (int) get_the_ID();
        foreach ( geodir_get_posttypes() as $pt ) {
            if ( $current_id && $current_id === (int) geodir_archive_page_id( $pt ) ) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Returns true only when on the WordPress page configured as the gd_place
 * archive in GeoDirectory settings (e.g. /places/). Used to scope the seasonal
 * redirect so it doesn't fire on taxonomy archives or search results.
 */
function oha_hld_is_main_places_archive() {
    if ( ! function_exists( 'geodir_archive_page_id' ) ) return false;
    if ( ! is_page() ) return false;
    return (int) get_the_ID() === (int) geodir_archive_page_id( 'gd_place' );
}

/**
 * Returns the first season window whose MM-DD date range contains today,
 * or null if today falls outside all configured windows.
 * Handles year-wrap ranges (e.g. 11-01 to 01-05).
 */
function oha_hld_get_active_season() {
    $windows = OHA_HLD_Admin::get( 'season_windows', [] );
    if ( empty( $windows ) || ! is_array( $windows ) ) return null;
    $today = date( 'md' ); // zero-padded MMDD, e.g. "1115"
    foreach ( $windows as $w ) {
        if ( empty( $w['category_slug'] ) || empty( $w['start_md'] ) || empty( $w['end_md'] ) ) continue;
        $start = str_replace( '-', '', $w['start_md'] );
        $end   = str_replace( '-', '', $w['end_md'] );
        if ( $start > $end ) {
            // Wraps year boundary (e.g. 1101 → 0105): in range if today >= start OR today <= end
            if ( $today >= $start || $today <= $end ) return $w;
        } else {
            if ( $today >= $start && $today <= $end ) return $w;
        }
    }
    return null;
}

/**
 * When a holiday season is active and the visitor lands on the main haunt
 * archive page with no active filter or search, redirect them to the GD
 * category archive for that season so the right haunts show by default.
 * Uses 302 (temporary) so search engines see the full list at the canonical URL.
 */
add_action( 'template_redirect', function() {
    if ( ! oha_hld_is_main_places_archive() ) return;
    if ( isset( $_GET['geodir_search'] ) || isset( $_GET['s'] ) ) return;
    $season = oha_hld_get_active_season();
    if ( ! $season || empty( $season['category_slug'] ) ) return;
    $term = get_term_by( 'slug', $season['category_slug'], 'gd_placecategory' );
    if ( ! $term || is_wp_error( $term ) ) return;
    $url = get_term_link( $term, 'gd_placecategory' );
    if ( ! is_wp_error( $url ) ) {
        wp_redirect( $url, 302 );
        exit;
    }
} );

/**
 * Enqueue OHA dark-theme CSS on GeoDirectory archive and search pages.
 * Also loads on GD category/listing pages so the same theme applies sitewide
 * wherever GD renders its card grid.
 */
add_action( 'wp_enqueue_scripts', function() {
    if ( oha_hld_is_gd_listing_context() ) {
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
    if ( oha_hld_is_gd_listing_context() ) {
        $classes[] = 'oha-gd-archive';
    }
    return $classes;
} );

/**
 * Add oha-has-discount class to archive card items that have an active OHA discount.
 * archive.css uses this class to show the gold discount badge on the card.
 */
add_filter( 'post_class', function( $classes, $class, $post_id ) {
    if ( ! oha_hld_is_gd_listing_context() ) {
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
    if ( ! oha_hld_is_gd_listing_context() ) return;

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
 * Set 12 posts per page on GD place archive and search pages.
 *
 * Default of 10 leaves one card alone on the bottom row of the 3-column grid.
 * Two hooks cover both paths GD uses: the main WP_Query (search results page)
 * and GD's block-based secondary query (geodir_posts_per_page filter).
 */
add_filter( 'geodir_posts_per_page', function( $per_page, $post_type = '', $defaults = [] ) {
    if ( $post_type === 'gd_place' || $post_type === 'gd_event' ) return 12;
    return $per_page;
}, 10, 3 );

add_action( 'pre_get_posts', function( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) return;
    if ( ! function_exists( 'geodir_is_page' ) ) return;
    if ( ! geodir_is_page( 'archive' ) && ! geodir_is_page( 'search' ) ) return;
    $query->set( 'posts_per_page', 12 );
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
