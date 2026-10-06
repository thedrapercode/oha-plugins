<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class JEmail_Public {

    private $db;
    private $tracker;

    public function __construct( JEmail_DB $db, JEmail_Tracker $tracker ) {
        $this->db      = $db;
        $this->tracker = $tracker;

        add_action( 'init', array( $this, 'handle_requests' ), 1 );
    }

    /**
     * Check for tracking / unsubscribe query params on every page load.
     */
    public function handle_requests() {
        // Open tracking pixel
        if ( isset( $_GET['jemail_track'] ) && $_GET['jemail_track'] === 'open' ) {
            $cid = absint( $_GET['cid'] ?? 0 );
            $lid = absint( $_GET['lid'] ?? 0 );
            $this->tracker->handle_open( $cid, $lid );
            // handle_open exits
        }

        // Click tracking redirect
        if ( isset( $_GET['jemail_track'] ) && $_GET['jemail_track'] === 'click' ) {
            $cid = absint( $_GET['cid'] ?? 0 );
            $lid = absint( $_GET['lid'] ?? 0 );
            $url = isset( $_GET['url'] ) ? $_GET['url'] : '';
            $this->tracker->handle_click( $cid, $lid, $url );
            // handle_click exits
        }

        // Unsubscribe
        if ( isset( $_GET['jemail_unsub'] ) && $_GET['jemail_unsub'] === '1' ) {
            $email = isset( $_GET['email'] ) ? rawurldecode( $_GET['email'] ) : '';
            $token = isset( $_GET['token'] ) ? sanitize_text_field( $_GET['token'] ) : '';

            if ( $email && $token ) {
                $result = $this->tracker->handle_unsubscribe( $email, $token );
                if ( $result ) {
                    wp_die(
                        '<div style="max-width:500px;margin:80px auto;text-align:center;font-family:sans-serif;">'
                        . '<h2>You have been unsubscribed</h2>'
                        . '<p style="color:#666;">You will no longer receive bulk emails from ' . esc_html( get_bloginfo( 'name' ) ) . '.</p>'
                        . '<p><a href="' . esc_url( home_url( '/' ) ) . '">Return to site</a></p>'
                        . '</div>',
                        'Unsubscribed',
                        array( 'response' => 200 )
                    );
                } else {
                    wp_die(
                        '<div style="max-width:500px;margin:80px auto;text-align:center;font-family:sans-serif;">'
                        . '<h2>Invalid unsubscribe link</h2>'
                        . '<p style="color:#666;">This link may have expired or is invalid. Please contact us if you need help.</p>'
                        . '<p><a href="' . esc_url( home_url( '/' ) ) . '">Return to site</a></p>'
                        . '</div>',
                        'Error',
                        array( 'response' => 403 )
                    );
                }
            }
        }
    }
}
