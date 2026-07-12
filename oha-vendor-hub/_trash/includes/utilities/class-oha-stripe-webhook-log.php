<?php
/**
 * Stripe Webhook Event Log (Board Hub)
 *
 * Purpose:
 * - Store a permanent record of Stripe webhook events received by the centralized webhook endpoint
 * - Provide lightweight totals + recent activity visibility inside Board Hub (Overview / Finance)
 *
 * Notes:
 * - This is NOT a Stripe API pull. It logs events as they arrive.
 * - If you need historical Stripe events from before this was enabled, use Stripe Dashboard,
 *   or we can add an admin-only “Fetch Events” tool later.
 */

if (!defined('ABSPATH')) exit;

class OHA_Stripe_Webhook_Log {

    const CPT = 'oha_stripe_webhook';

    public static function init() {
        add_action('init', [__CLASS__, 'register_cpt']);
    }

    public static function register_cpt() {
        $labels = [
            'name'          => 'Stripe Webhooks',
            'singular_name' => 'Stripe Webhook',
            'menu_name'     => 'Stripe Webhooks',
        ];

        register_post_type(self::CPT, [
            'labels'        => $labels,
            'public'        => false,
            'show_ui'       => false, // keep clean; we surface this inside Board Hub
            'show_in_menu'  => false,
            'supports'      => ['title', 'editor'],
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ]);
    }

    /**
     * Log an event (idempotent by Stripe event id)
     */
    public static function log_event($event, $status = 'received') {
        if (empty($event) || empty($event->id)) return;

        $event_id = sanitize_text_field($event->id);
        $type = sanitize_text_field($event->type ?? 'unknown');
        $created = isset($event->created) ? intval($event->created) : current_time('timestamp');
        $livemode = !empty($event->livemode) ? 1 : 0;

        // Check existing by meta event id
        $existing = get_posts([
            'post_type'      => self::CPT,
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_oha_stripe_event_id',
                    'value' => $event_id,
                ]
            ]
        ]);

        $title = $type . ' — ' . $event_id;

        // Store a small, safe summary in content (avoid storing full PII heavy payloads)
        $obj_id = '';
        if (!empty($event->data) && !empty($event->data->object) && !empty($event->data->object->id)) {
            $obj_id = sanitize_text_field($event->data->object->id);
        }
        $content = "Type: {$type}\nEvent: {$event_id}\nObject: {$obj_id}\nStatus: {$status}\nLive mode: " . ($livemode ? 'yes' : 'no');

        if (!empty($existing) && !empty($existing[0])) {
            $post_id = intval($existing[0]);
            update_post_meta($post_id, '_oha_status', sanitize_text_field($status));
            update_post_meta($post_id, '_oha_type', $type);
            update_post_meta($post_id, '_oha_livemode', $livemode);
            update_post_meta($post_id, '_oha_created', $created);
            wp_update_post([
                'ID'           => $post_id,
                'post_title'   => $title,
                'post_content' => $content,
            ]);
            return;
        }

        $post_id = wp_insert_post([
            'post_type'    => self::CPT,
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_content' => $content,
            'post_date'    => date('Y-m-d H:i:s', $created),
            'post_date_gmt'=> get_gmt_from_date(date('Y-m-d H:i:s', $created)),
        ], true);

        if (is_wp_error($post_id) || !$post_id) return;

        update_post_meta($post_id, '_oha_stripe_event_id', $event_id);
        update_post_meta($post_id, '_oha_status', sanitize_text_field($status));
        update_post_meta($post_id, '_oha_type', $type);
        update_post_meta($post_id, '_oha_livemode', $livemode);
        update_post_meta($post_id, '_oha_created', $created);
        if ($obj_id) update_post_meta($post_id, '_oha_object_id', $obj_id);
    }

    /**
     * Render an Overview card with totals and latest received events
     */
    public static function render_overview_card() {
        global $wpdb;

        $total = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
            self::CPT
        )));

        $last_ts = intval($wpdb->get_var($wpdb->prepare(
            "SELECT MAX(CAST(pm.meta_value AS UNSIGNED))
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE p.post_type = %s AND p.post_status = 'publish' AND pm.meta_key = '_oha_created'",
            self::CPT
        )));

        $recent = get_posts([
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => 8,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        // Top event types (all time)
        $types = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.meta_value AS type, COUNT(*) AS cnt
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE p.post_type = %s AND p.post_status = 'publish' AND pm.meta_key = '_oha_type'
             GROUP BY pm.meta_value
             ORDER BY cnt DESC
             LIMIT 6",
            self::CPT
        ));

        ob_start();
        ?>
        <div class="oha-card" style="margin-top:16px;">
            <h3 style="margin:0 0 10px 0;">Stripe Webhook Events</h3>
            <p style="margin:0 0 10px 0;opacity:.85;">This is a running log of Stripe webhook events received by your centralized endpoint. It grows as new events arrive.</p>

            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:10px;">
                <div style="border:1px solid #333;border-radius:10px;padding:10px 12px;min-width:180px;">
                    <div style="font-size:22px;font-weight:700;line-height:1;"><?php echo esc_html(number_format_i18n($total)); ?></div>
                    <div style="opacity:.75;margin-top:4px;">Total logged events</div>
                </div>
                <div style="border:1px solid #333;border-radius:10px;padding:10px 12px;min-width:260px;">
                    <div style="font-size:14px;font-weight:700;">Last received</div>
                    <div style="opacity:.8;margin-top:4px;"><?php echo $last_ts ? esc_html(date_i18n('M j, Y g:i a', $last_ts)) : '—'; ?></div>
                </div>
            </div>

            <?php if (!empty($types)) : ?>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin:0 0 10px 0;">
                    <?php foreach ($types as $row) : ?>
                        <span style="border:1px solid #333;border-radius:999px;padding:6px 10px;font-size:12px;opacity:.9;">
                            <strong><?php echo esc_html($row->type); ?></strong> — <?php echo esc_html(number_format_i18n((int)$row->cnt)); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h4 style="margin:12px 0 8px 0;">Latest events</h4>
            <div class="oha-table-wrap" style="max-height:260px;">
                <table class="oha-table">
                    <thead><tr>
                        <th>Received</th>
                        <th>Type</th>
                        <th>Event ID</th>
                        <th>Mode</th>
                        <th>Status</th>
                    </tr></thead>
                    <tbody>
                    <?php if (!empty($recent)) : foreach ($recent as $p) :
                        $created = (int) get_post_meta($p->ID, '_oha_created', true);
                        $type = (string) get_post_meta($p->ID, '_oha_type', true);
                        $eid = (string) get_post_meta($p->ID, '_oha_stripe_event_id', true);
                        $live = (int) get_post_meta($p->ID, '_oha_livemode', true);
                        $status = (string) get_post_meta($p->ID, '_oha_status', true);
                        ?>
                        <tr>
                            <td><?php echo $created ? esc_html(date_i18n('M j, Y g:i a', $created)) : esc_html(get_the_date('', $p)); ?></td>
                            <td><?php echo esc_html($type ?: '—'); ?></td>
                            <td style="font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;white-space:nowrap;">
                                <?php echo esc_html($eid ?: '—'); ?>
                            </td>
                            <td><?php echo $live ? 'Live' : 'Test'; ?></td>
                            <td><?php echo esc_html($status ?: 'received'); ?></td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="5">No webhook events logged yet. Once Stripe starts sending, they’ll appear here.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
