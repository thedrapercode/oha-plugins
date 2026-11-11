<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OHA_Site_PWA_REST {

    private static $_instance = null;

    public static function instance() {
        if ( null === self::$_instance ) self::$_instance = new self();
        return self::$_instance;
    }

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( 'oha/v1', '/nonce', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_nonce' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function get_nonce() {
        $response = new WP_REST_Response( array(
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'expires' => time() + ( 11 * HOUR_IN_SECONDS ),
        ) );
        $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate' );
        $response->header( 'CDN-Cache-Control', 'no-store' );
        $response->header( 'X-Accel-Expires', '0' );
        return $response;
    }
}
