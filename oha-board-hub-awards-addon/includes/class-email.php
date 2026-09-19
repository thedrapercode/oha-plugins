<?php
if (!defined('ABSPATH')) exit;

class OHA_Awards_Email {
    public static function send_admin_notice($post_id) {
        $opts = get_option('oha_awards_settings', []);
        $to   = $opts['notification_email'] ?? get_option('admin_email');
        if (empty($to)) return;

        $tpl_subj = $opts['email_admin_subject'] ?? 'New Haunt Award Submission #{entry_id}';
        $tpl_body = $opts['email_admin_body'] ?? "A new award entry was submitted.\n\nEntry #{entry_id}\nActor: {actor_name}\nHaunt: {haunt_title}\nOwner: {owner_name} ({owner_email})\n\nView in admin: {admin_url}";

        $repl = self::build_replacements($post_id);
        $subject = self::replace($tpl_subj, $repl);
        $body    = self::replace($tpl_body, $repl);

        wp_schedule_single_event(time(), 'oha_awards_send_email', [$to, $subject, $body, []]);
    }

    public static function send_cert_ready($post_id) {
        $opts = get_option('oha_awards_settings', []);

        // Resolve recipient — prefer owner user email, fall back to submitter email on entry
        $owner_id = intval(get_post_meta($post_id, '_oha_owner_user_id', true));
        $to = '';
        if ($owner_id) {
            $u = get_user_by('id', $owner_id);
            if ($u) $to = $u->user_email;
        }
        if (!$to) $to = (string) get_post_meta($post_id, '_oha_submitter_email', true);
        if (!$to) return;

        $tpl_subj = $opts['email_cert_subject'] ?? 'Your Haunt Award certificate is ready';
        $tpl_body = $opts['email_cert_body'] ?? "Hi {owner_name},\n\nGreat news! Your Haunt Award certificate for {actor_name} at {haunt_title} is ready.\n\nLog in to download your certificate: {dashboard_url}\n\nThank you for being part of the OHA haunt community.";

        $repl    = self::build_replacements($post_id);
        $subject = self::replace($tpl_subj, $repl);
        $body    = self::replace($tpl_body, $repl);

        wp_schedule_single_event(time(), 'oha_awards_send_email', [$to, $subject, $body, []]);
    }

    public static function send_submitter_confirm($post_id, $email) {
        if (!$email) return;
        $opts = get_option('oha_awards_settings', []);
        $tpl_subj = $opts['email_submitter_subject'] ?? 'We received your Haunt Award submission';
        $tpl_body = $opts['email_submitter_body'] ?? "Thanks, {owner_name}!\n\nWe received your nomination for {actor_name} at {haunt_title}.\nEntry #{entry_id}\n\nWe’ll review it and notify you when the certificate is ready.\n\nView your awards: {dashboard_url}";

        $repl = self::build_replacements($post_id);
        $subject = self::replace($tpl_subj, $repl);
        $body    = self::replace($tpl_body, $repl);

        wp_schedule_single_event(time(), 'oha_awards_send_email', [$email, $subject, $body, []]);
    }

    protected static function build_replacements($post_id) {
        $owner_id = intval(get_post_meta($post_id,'_oha_owner_user_id',true));
        $haunt_id = intval(get_post_meta($post_id,'_oha_haunt_id',true));
        $actor    = get_post_meta($post_id,'_oha_actor_name',true);
        $character= get_post_meta($post_id,'_oha_character_name',true);
        $reason   = get_post_meta($post_id,'_oha_reason',true);
        $owner    = get_user_by('id',$owner_id);
        $owner_name  = $owner ? $owner->display_name : '';
        $owner_email = $owner ? $owner->user_email : '';
        $haunt_title = $haunt_id ? get_the_title($haunt_id) : '';
        $admin_url = admin_url('admin.php?page=oha-awards-submissions');
        $dashboard_url = home_url('/'); // can be customized on site

        return [
            '{entry_id}' => $post_id,
            '{actor_name}' => $actor,
            '{character_name}' => $character,
            '{reason}' => wp_strip_all_tags($reason),
            '{haunt_id}' => $haunt_id,
            '{haunt_title}' => $haunt_title,
            '{owner_id}' => $owner_id,
            '{owner_name}' => $owner_name,
            '{owner_email}' => $owner_email,
            '{admin_url}' => $admin_url,
            '{dashboard_url}' => $dashboard_url,
        ];
    }

    protected static function replace($tpl, $repl) {
        return strtr($tpl, $repl);
    }
}
