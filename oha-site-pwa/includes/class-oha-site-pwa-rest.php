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

        register_rest_route( 'oha/v1', '/push/send', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'send_push' ),
            'permission_callback' => array( $this, 'can_send_push' ),
            'args'                => array(
                'title'   => array( 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ),
                'message' => array( 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ),
                'url'     => array( 'required' => false, 'sanitize_callback' => 'esc_url_raw' ),
            ),
        ) );
    }

    public function can_send_push() {
        return current_user_can( 'edit_posts' );
    }

    public function send_push( WP_REST_Request $request ) {
        $title   = $request->get_param( 'title' );
        $message = $request->get_param( 'message' );
        $url     = $request->get_param( 'url' ) ?: '';

        $result = OHA_Site_PWA_OneSignal::instance()->send( $title, $message, $url );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response(
                array( 'success' => false, 'error' => $result->get_error_message() ),
                400
            );
        }

        return new WP_REST_Response( array(
            'success'    => true,
            'recipients' => isset( $result['recipients'] ) ? (int) $result['recipients'] : 0,
        ), 200 );
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
