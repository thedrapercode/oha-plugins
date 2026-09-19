<?php
if (!defined('ABSPATH')) exit;

class OHA_Awards_Kadence_Hook {
    public static function init() {
        self::bridge_init();
        add_action('kadence_form_submission', [__CLASS__,'capture'], 10, 2);
    }

    // Bridge free Kadence Blocks -> kadence_form_submission
    public static function bridge_init(){
        add_action('kadence_blocks_form_submission', function($fields,$form_id){
            do_action('kadence_form_submission', $fields, $form_id);
        }, 10, 2);
    }

    protected static function submission_exists($user_id, $haunt_id) {
        $year = OHA_Awards_Settings::get_year();
        $q = new WP_Query([
            'post_type'      => 'oha_award_entry',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                ['key'=>'_oha_award_year','value'=>$year,'compare'=>'=','type'=>'NUMERIC'],
                ['key'=>'_oha_owner_user_id','value'=>intval($user_id),'compare'=>'='],
                ['key'=>'_oha_haunt_id','value'=>intval($haunt_id),'compare'=>'='],
            ],
        ]);
        return $q->have_posts();
    }

    public static function capture($form_data, $form_args) {
        if (!is_user_logged_in()) return;

        $opts = get_option('oha_awards_settings', []);
        $map  = $opts['field_map'] ?? [];
        $notify = $opts['notification_email'] ?? get_option('admin_email');
        $enforce_one = !empty($opts['one_per_haunt']);

        $user_id  = get_current_user_id();
        $haunt_key = $map['haunt_id'] ?? '';
        $actor_name_key = $map['actor_name'] ?? '';
        $actor_email_key = $map['actor_email'] ?? '';
        $actor_photo_key = $map['actor_photo'] ?? '';

        $haunt_id   = isset($form_data[$haunt_key]) ? intval($form_data[$haunt_key]) : 0;
        $actor_name = isset($form_data[$actor_name_key]) ? sanitize_text_field($form_data[$actor_name_key]) : '';
        $actor_email= isset($form_data[$actor_email_key]) ? sanitize_email($form_data[$actor_email_key]) : '';
        $actor_photo= $form_data[$actor_photo_key] ?? '';

        if ($enforce_one && $haunt_id && self::submission_exists($user_id, $haunt_id)) {
            // Already exists; set a notice and quit
            set_transient('oha_awards_notice_' . $user_id, 'You have already submitted an award entry for this haunt.', 60*10);
            return;
        }

        $post_id = wp_insert_post([
            'post_type'   => 'oha_award_entry',
            'post_status' => 'publish',
            'post_title'  => $actor_name ? $actor_name : 'Award Entry',
            'post_author' => $user_id,
            'meta_input'  => [
                '_oha_owner_user_id'   => $user_id,
                '_oha_haunt_id'        => $haunt_id,
                '_oha_actor_name'      => $actor_name,
                '_oha_actor_email'     => $actor_email,
                '_oha_status'          => 'in_review',
                '_oha_award_year' => OHA_Awards_Settings::get_year(),
                '_oha_created_at'      => current_time('mysql'),
            ],
        ]);

        if ($post_id && !is_wp_error($post_id)) {
            // Log initial status
            OHA_Awards_Helpers::log_status($post_id, 'in_review', 'Initial submission');

            // Attach photo if URL or Kadence file field
            if (!empty($actor_photo)) {
                if (is_string($actor_photo)) {
                    $attach_id = media_sideload_image($actor_photo, $post_id, null, 'id');
                } elseif (is_array($actor_photo) && !empty($actor_photo['url'])) {
                    $attach_id = media_sideload_image($actor_photo['url'], $post_id, null, 'id');
                } else {
                    $attach_id = 0;
                }
                if (!is_wp_error($attach_id) && $attach_id) {
                    update_post_meta($post_id, '_oha_photo_id', $attach_id);
                    update_post_meta($post_id, '_oha_photo_url', wp_get_attachment_url($attach_id));
                }
            }

            // Confirmation for owner
            set_transient('oha_awards_notice_' . $user_id, 'Your award submission has been received.', 60*10);

            // Notify staff
            if (!empty($notify)) {
                wp_mail($notify, 'New Haunt Award Submission', 'Entry ID: '.$post_id.' — Actor: '.$actor_name);
            }
        }
    }
}
