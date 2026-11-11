<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OHA_Site_PWA_OneSignal {

    private static $_instance = null;

    const API_URL = 'https://onesignal.com/api/v1/notifications';

    public static function instance() {
        if ( null === self::$_instance ) self::$_instance = new self();
        return self::$_instance;
    }

    private function get_api_key() {
        return get_option( 'oha_spwa_onesignal_api_key', '' );
    }

    private function get_app_id() {
        return get_option( 'oha_spwa_onesignal_app_id', '' );
    }

    /**
     * Send a push notification to all subscribers or a filtered segment.
     *
     * @param string $title   Notification title.
     * @param string $message Notification body.
     * @param string $url     Click-through URL (optional, defaults to home).
     * @param array  $filters OneSignal filter array (optional, targets all by default).
     * @return array|WP_Error Response from OneSignal API.
     */
    public function send( $title, $message, $url = '', $filters = array() ) {
        $api_key = $this->get_api_key();
        $app_id  = $this->get_app_id();

        if ( ! $api_key || ! $app_id ) {
            return new WP_Error( 'oha_spwa_no_credentials', 'OneSignal App ID or API key is not configured.' );
        }

        $payload = array(
            'app_id'   => $app_id,
            'headings' => array( 'en' => sanitize_text_field( $title ) ),
            'contents' => array( 'en' => sanitize_text_field( $message ) ),
            'url'      => $url ? esc_url_raw( $url ) : home_url( '/' ),
        );

        if ( ! empty( $filters ) ) {
            $payload['filters'] = $filters;
        } else {
            $payload['included_segments'] = array( 'All' );
        }

        $response = wp_remote_post( self::API_URL, array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Basic ' . $api_key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ),
            'body' => wp_json_encode( $payload ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            return new WP_Error(
                'oha_spwa_onesignal_error',
                isset( $body['errors'] ) ? implode( ', ', (array) $body['errors'] ) : 'OneSignal returned error ' . $code
            );
        }

        return $body;
    }
}
