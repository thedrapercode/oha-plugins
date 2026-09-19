<?php
if (!defined('ABSPATH')) exit;

class OHA_Awards_Stripe_Stub {
    public static function init() {
        add_action('rest_api_init', function(){
            register_rest_route('oha-awards/v1','/print/session', [
                'methods'  => 'POST',
                'permission_callback' => function(){ return is_user_logged_in(); },
                'callback' => [__CLASS__,'create_session']
            ]);
        });
    }

    public static function create_session(WP_REST_Request $r) {
        $entry_id = intval($r->get_param('entry_id'));
        $user_id  = get_current_user_id();
        if (!$entry_id) return new WP_REST_Response(['error'=>'Missing entry_id'], 400);
        if (!OHA_Awards_Helpers::ensure_owner($entry_id, $user_id)) return new WP_REST_Response(['error'=>'Not yours'], 403);
        $status = get_post_meta($entry_id,'_oha_status',true);
        if ($status !== 'completed') return new WP_REST_Response(['error'=>'Not ready'], 400);

        // Replace with real Stripe Checkout Session
        return new WP_REST_Response(['url'=>home_url('/?checkout=todo&entry_id='.$entry_id)], 200);
    }
}
