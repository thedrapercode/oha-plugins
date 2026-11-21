<?php
/**
 * Discord Integration
 *
 * Sends notifications to Discord channels via webhooks when content is published.
 * Hooks: standard posts in the Announcements category -> announcement webhook;
 * oha_job posts -> jobs webhook.
 *
 * @package OHA_Board_Hub
 */

if (!defined('ABSPATH')) {
    exit;
}

class OHA_Discord {

    public static function init() {
        add_action('transition_post_status', [__CLASS__, 'on_post_published'], 10, 3);
        add_action('admin_post_oha_save_discord', [__CLASS__, 'handle_save']);
    }

    /**
     * Fires when any post status transitions. Only acts on new publish events.
     */
    public static function on_post_published($new_status, $old_status, $post) {
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }

        $settings = OHA_Settings::instance();

        if ($post->post_type === 'post') {
            if (!has_term('announcements', 'category', $post)) {
                return;
            }
            $webhook = $settings->get('discord.announcement_webhook', '');
            if (empty($webhook)) {
                return;
            }
            self::send($webhook, $post, 'New Announcement');

        } elseif ($post->post_type === 'oha_job') {
            $webhook = $settings->get('discord.jobs_webhook', '');
            if (empty($webhook)) {
                return;
            }
            self::send($webhook, $post, 'New Job Posting');
        }
    }

    /**
     * Send an embed to a Discord webhook URL.
     */
    private static function send($webhook_url, $post, $label) {
        $title   = html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8');
        $url     = get_permalink($post);
        $excerpt = wp_trim_words(wp_strip_all_tags($post->post_content), 30, '...');

        $payload = wp_json_encode([
            'embeds' => [[
                'title'       => $title,
                'url'         => $url,
                'description' => $excerpt,
                'color'       => 11350297, // #ad3119 OHA red
                'footer'      => ['text' => $label . ' - Ohio Haunters Association'],
            ]],
        ]);

        wp_remote_post($webhook_url, [
            'headers'  => ['Content-Type' => 'application/json'],
            'body'     => $payload,
            'timeout'  => 5,
            'blocking' => false,
        ]);
    }

    /**
     * Handle save of Discord settings form (admin-post.php).
     */
    public static function handle_save() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('oha_discord_save', 'oha_discord_nonce');

        $settings = OHA_Settings::instance();
        $settings->set('discord.announcement_webhook', sanitize_url(wp_unslash($_POST['discord_announcement_webhook'] ?? '')));
        $settings->set('discord.jobs_webhook', sanitize_url(wp_unslash($_POST['discord_jobs_webhook'] ?? '')));
        $settings->save();

        $redirect = add_query_arg([
            'page'    => OHA_BHS_MENU,
            'tab'     => 'webhooks',
            'discord' => 'saved',
        ], admin_url('admin.php'));

        wp_safe_redirect($redirect);
        exit;
    }
}