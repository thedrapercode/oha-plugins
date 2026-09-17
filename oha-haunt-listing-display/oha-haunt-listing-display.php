<?php
/**
 * Plugin Name: OHA Haunt Listing Display
 * Description: Custom single listing page template for GeoDirectory gd_place listings. OHA-branded layout with hero, info card, gallery, nearby haunts, integrated claim workflow, and report post. Configure via GeoDirectory > OHA Listing Display.
 * Version:     1.10.0
 * Author:      OHA
 * Requires Plugins: geodirectory
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OHA_HLD_VERSION', '1.10.0' );
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
