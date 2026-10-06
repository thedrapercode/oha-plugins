<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class JEmail_Tracker {

    private $db;

    public function __construct( JEmail_DB $db ) {
        $this->db = $db;
    }

    /**
     * Handle an open tracking pixel hit.
     */
    public function handle_open( $campaign_id, $log_id ) {
        if ( ! $campaign_id || ! $log_id ) return;
        $this->db->mark_opened( $log_id );
        // Return a 1x1 transparent GIF
        header( 'Content-Type: image/gif' );
        header( 'Cache-Control: no-store, no-cache, must-revalidate' );
        // Minimal 1x1 transparent GIF
        echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
        exit;
    }

    /**
     * Handle a click tracking redirect.
     */
    public function handle_click( $campaign_id, $log_id, $url ) {
        if ( ! $campaign_id || ! $url ) return;

        $url = rawurldecode( $url );

        // Validate URL
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            wp_safe_redirect( home_url( '/' ) );
            exit;
        }

        // Also mark as opened if not already
        if ( $log_id ) {
            $this->db->mark_opened( $log_id );
        }

        // Log the click
        $log = null;
        if ( $log_id ) {
            global $wpdb;
            $log = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM " . $wpdb->prefix . JEMAIL_TABLE_LOGS . " WHERE id = %d",
                $log_id
            ) );
        }

        $user_id = $log ? $log->user_id : 0;
        $this->db->log_click( $campaign_id, $log_id, $user_id, $url );

        // Redirect to actual URL
        wp_redirect( $url );
        exit;
    }

    /**
     * Handle unsubscribe request.
     */
    public function handle_unsubscribe( $email, $token ) {
        $result = $this->db->process_unsub_token( $token, $email );
        return $result;
    }
}
