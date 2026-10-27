<?php
if (!defined('ABSPATH')) exit;

class OHA_Awards_Shortcodes {
    public static function init() {
        add_shortcode('oha_my_award', [__CLASS__,'my_award']);
        add_shortcode('oha_awards', [__CLASS__,'my_award']);
        add_shortcode('oha_award', [__CLASS__,'my_award']);
    }

    public static function my_award($atts) {
        if (!is_user_logged_in()) {
            $redirect   = get_permalink() ?: home_url('/');
            $login_form = wp_login_form(['echo' => false, 'redirect' => $redirect, 'remember' => true]);
            return '<div class="oha-awards-login">'
                . '<p>Log in to view your award submissions and download your certificate.</p>'
                . '<div class="oha-awards-form">' . $login_form . '</div>'
                . '</div>';
        }
        $user_id = get_current_user_id();
        $atts    = shortcode_atts(['haunt_id' => 0], $atts);
        $year    = OHA_Awards_Settings::get_year();

        $args = [
            'post_type'      => 'oha_award_entry',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                ['key'=>'_oha_award_year','value'=>$year,'compare'=>'=','type'=>'NUMERIC'],
                [
                    'relation' => 'OR',
                    ['key'=>'_oha_owner_user_id','value'=>$user_id,'compare'=>'='],
                    ['key'=>'_oha_owner_user_id','compare'=>'NOT EXISTS'],
                ],
            ],
            'author' => $user_id,  // back-compat if owner meta missing
        ];
        if (!empty($atts['haunt_id'])) {
            $args['meta_query'][] = ['key'=>'_oha_haunt_id','value'=>intval($atts['haunt_id']),'compare'=>'='];
        }
        $q = new WP_Query($args);
        ob_start();

        // Confirmation notice (10 min transient)
        $notice = get_transient('oha_awards_notice_' . $user_id);
        if ($notice) {
            echo '<div class="notice notice-success" style="margin-bottom:12px;padding:8px;border-left:4px solid #46b450;background:#f6fff7;color:#1a5218;">' . esc_html($notice) . '</div>';
            delete_transient('oha_awards_notice_' . $user_id);
        }

        if (!$q->have_posts()) return '<p>No submissions found for the ' . esc_html($year) . ' haunt award season.</p>';

        $status_labels = [
            'in_review' => 'Under Review',
            'verified'  => 'Verified',
            'completed' => 'Certificate Ready',
        ];

        echo '<div class="oha-awards-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;">';
        while($q->have_posts()){ $q->the_post();
            $id          = get_the_ID();
            $award_type  = (string) get_post_meta($id, '_oha_award_type', true);
            $is_campsite = ($award_type === 'campsite');
            $raw_status  = (string) get_post_meta($id, '_oha_status', true);
            $status_label = esc_html($status_labels[$raw_status] ?? ucwords(str_replace('_', ' ', $raw_status)));
            $low  = get_post_meta($id, '_oha_cert_lowres_id', true);
            $lowu = $low ? esc_url(wp_get_attachment_url($low)) : '';
            $print_requested = get_post_meta($id, '_oha_print_requested', true);

            $type_badge = $is_campsite
                ? '<span style="display:inline-block;font-size:11px;padding:2px 8px;border-radius:4px;background:#1159af;color:#fff;margin-bottom:6px;">Campsite Award</span>'
                : '<span style="display:inline-block;font-size:11px;padding:2px 8px;border-radius:4px;background:#ad3119;color:#fff;margin-bottom:6px;">Haunt Actor Award</span>';

            if ($is_campsite) {
                $display_name  = esc_html(get_post_meta($id, '_oha_campground_name', true) ?: get_the_title());
                $detail_line   = '<strong>Location:</strong> ' . esc_html(get_post_meta($id, '_oha_location', true));
            } else {
                $display_name  = esc_html(get_the_title());
                $detail_line   = '<strong>Actor:</strong> ' . esc_html(get_post_meta($id, '_oha_actor_name', true));
            }

            echo '<div class="card" style="border:1px solid #e3e3e3;border-radius:12px;padding:12px;">';
            echo $type_badge;
            echo '<h4 style="margin-top:4px;">' . $display_name . '</h4>';
            echo '<p>' . $detail_line . '<br/><strong>Status:</strong> ' . $status_label . '</p>';
            if ($raw_status === 'completed' && $lowu) {
                echo '<p><a class="button" href="' . $lowu . '" download>Download Certificate (Free)</a></p>';
                echo '<p><button class="button print-btn" data-entry="' . $id . '">Order Printed Copy ($10)</button></p>';
                if ($print_requested) echo '<p><em>Print ordered.</em></p>';
            } else {
                echo '<p><em>Your certificate will appear here when completed.</em></p>';
            }
            echo '</div>';
        }
        echo '</div>';

        ?>
        <script>
        document.addEventListener('click', async (e)=>{
          if (!e.target.matches('.print-btn')) return;
          const id = e.target.getAttribute('data-entry');
          try {
            const resp = await fetch('<?php echo esc_js( rest_url('oha-awards/v1/print/session') ); ?>', {
              method: 'POST',
              headers: {'Content-Type':'application/json','X-WP-Nonce':'<?php echo wp_create_nonce('wp_rest'); ?>'},
              body: JSON.stringify({ entry_id: id })
            });
            const data = await resp.json();
            if (data.url) window.location = data.url;
            else alert(data.error || 'Unable to start checkout.');
          } catch(err){ alert('Network error'); }
        });
        </script>
        <?php

        return ob_get_clean();
    }
}
