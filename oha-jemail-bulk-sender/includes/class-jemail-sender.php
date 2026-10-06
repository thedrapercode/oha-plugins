<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class JEmail_Sender {

    private $db;

    public function __construct( JEmail_DB $db ) {
        $this->db = $db;
    }

    /**
     * Start a campaign: queue recipients, update status.
     */
    public function start_campaign( $campaign_id ) {
        $campaign = $this->db->get_campaign( $campaign_id );
        if ( ! $campaign || ! in_array( $campaign->status, array( 'draft', 'scheduled' ), true ) ) return false;

        $roles              = maybe_unserialize( $campaign->target_roles );
        $swpm_levels        = maybe_unserialize( $campaign->target_swpm_levels );
        $uwp_forms          = maybe_unserialize( $campaign->target_uwp_forms );
        $vendor_statuses    = maybe_unserialize( $campaign->target_vendor_statuses );
        $volunteer_statuses = maybe_unserialize( $campaign->target_volunteer_statuses );
        $gd_listing         = $campaign->target_gd_listing ?? '';

        if ( ! is_array( $roles ) )              $roles              = array();
        if ( ! is_array( $swpm_levels ) )        $swpm_levels        = array();
        if ( ! is_array( $uwp_forms ) )          $uwp_forms          = array();
        if ( ! is_array( $vendor_statuses ) )    $vendor_statuses    = array();
        if ( ! is_array( $volunteer_statuses ) ) $volunteer_statuses = array();

        $recipients = $this->db->get_recipients( $roles, $swpm_levels, $uwp_forms, $vendor_statuses, $volunteer_statuses, $gd_listing );
        if ( empty( $recipients ) ) return false;

        $this->db->queue_recipients( $campaign_id, $recipients );

        $batch_size = absint( get_option( 'jemail_batch_size', 50 ) );
        $this->db->update_campaign( $campaign_id, array(
            'status'      => 'sending',
            'total_count' => count( $recipients ),
            'batch_size'  => $batch_size,
        ) );

        return true;
    }

    /**
     * Process one batch of emails for any active campaign.
     */
    public function process_batch() {
        global $wpdb;
        $campaigns_table = $wpdb->prefix . JEMAIL_TABLE_CAMPAIGNS;

        // Auto-start any scheduled campaigns whose send time has arrived
        $due_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$campaigns_table} WHERE status = 'scheduled' AND scheduled_at <= %s ORDER BY id ASC",
            current_time( 'mysql' )
        ) );
        foreach ( $due_ids as $due_id ) {
            $this->start_campaign( (int) $due_id );
        }

        $campaign = $wpdb->get_row( "SELECT * FROM {$campaigns_table} WHERE status = 'sending' ORDER BY id ASC LIMIT 1" );
        if ( ! $campaign ) return;

        $batch = $this->db->get_queued_emails( $campaign->id, $campaign->batch_size );
        if ( empty( $batch ) ) {
            $this->db->update_campaign( $campaign->id, array(
                'status'       => 'sent',
                'completed_at' => current_time( 'mysql' ),
            ) );
            return;
        }

        $from_name  = get_option( 'jemail_from_name', get_bloginfo( 'name' ) );
        $from_email = get_option( 'jemail_from_email', get_bloginfo( 'admin_email' ) );
        $tracking   = get_option( 'jemail_tracking_enabled', '1' ) === '1';

        foreach ( $batch as $log ) {
            $user = $log->user_id ? get_userdata( $log->user_id ) : null;
            $body = $this->render_email( $campaign, $log, $user, $tracking );

            $subject = $this->replace_merge_tags( $campaign->subject, $user, $log );

            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $from_name . ' <' . $from_email . '>',
            );

            $sent = wp_mail( $log->email, $subject, $body, $headers );

            if ( $sent ) {
                $this->db->mark_sent( $log->id );
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$campaigns_table} SET sent_count = sent_count + 1 WHERE id = %d",
                    $campaign->id
                ) );
            } else {
                $error = '';
                global $phpmailer;
                if ( isset( $phpmailer ) && is_object( $phpmailer ) && ! empty( $phpmailer->ErrorInfo ) ) {
                    $error = $phpmailer->ErrorInfo;
                }
                $this->db->mark_failed( $log->id, $error ?: 'wp_mail returned false' );
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$campaigns_table} SET fail_count = fail_count + 1 WHERE id = %d",
                    $campaign->id
                ) );
            }
        }

        // Check if done
        $stats = $this->db->get_campaign_stats( $campaign->id );
        if ( (int) $stats->queued === 0 ) {
            $this->db->update_campaign( $campaign->id, array(
                'status'       => 'sent',
                'completed_at' => current_time( 'mysql' ),
            ) );
        }
    }

    /**
     * Render the final email HTML with merge tags, footer, tracking.
     */
    public function render_email( $campaign, $log, $user = null, $tracking = true ) {
        $content_type = isset( $campaign->content_type ) ? $campaign->content_type : 'html';

        if ( $content_type === 'text' ) {
            $body = $this->replace_merge_tags( $campaign->text_content, $user, $log );
            $body = '<pre style="font-family:monospace;white-space:pre-wrap;">' . esc_html( $body ) . '</pre>';
        } else {
            $body = $this->replace_merge_tags( $campaign->html_content, $user, $log );
            // WordPress visual editor stores content with line breaks instead of <p> tags,
            // relying on wpautop() to add them. Apply it here so emails render properly.
            $body = wpautop( $body );
        }

        // Append footer
        $footer = get_option( 'jemail_footer_html', '' );
        if ( ! empty( $footer ) ) {
            $footer = $this->replace_merge_tags( $footer, $user, $log );
            $body  .= "\n" . $footer;
        }

        // Link tracking
        if ( $tracking && $log->id ) {
            $body = $this->add_link_tracking( $body, $campaign->id, $log->id );
        }

        // Open tracking pixel
        if ( $tracking && $log->id ) {
            $pixel_url = add_query_arg( array(
                'jemail_track' => 'open',
                'cid' => $campaign->id,
                'lid' => $log->id,
            ), home_url( '/' ) );
            $body .= '<img src="' . esc_url( $pixel_url ) . '" width="1" height="1" style="display:none;" alt="" />';
        }

        // Wrap in basic HTML doc with inline styles for email client compatibility
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>';
        $html .= '<body style="margin:0;padding:20px;background:#f7f7f7;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#333333;">';
        $html .= '<div style="max-width:640px;margin:0 auto;background:#ffffff;padding:30px;border-radius:6px;">';
        $html .= $body;
        $html .= '</div></body></html>';

        return $html;
    }

    /**
     * Preview email with sample merge tags.
     */
    public function preview_email( $campaign, $user_id = null ) {
        $user = $user_id ? get_userdata( $user_id ) : null;
        $log  = (object) array(
            'id'          => 0,
            'campaign_id' => $campaign->id ?? 0,
            'user_id'     => $user ? $user->ID : 0,
            'email'       => $user ? $user->user_email : 'preview@example.com',
        );

        return $this->render_email( $campaign, $log, $user, false );
    }

    /**
     * Replace merge tags in text. Includes OHA-specific tags:
     *   {{membership_level}} - SWPM level name
     *   {{user_type}}        - UsersWP form type (OHA Member / Listing Owner)
     *   {{account_type}}     - account_type user meta
     */
    public function replace_merge_tags( $text, $user = null, $log = null ) {
        $site_name = get_bloginfo( 'name' );
        $site_url  = home_url( '/' );

        $first_name   = '';
        $last_name    = '';
        $display_name = '';
        $username     = '';
        $email        = $log ? $log->email : '';

        // OHA-specific
        $membership_level = '';
        $user_type        = '';
        $account_type     = '';

        if ( $user ) {
            $first_name   = $user->first_name ?: '';
            $last_name    = $user->last_name ?: '';
            $display_name = $user->display_name ?: '';
            $username     = $user->user_login ?: '';
            $email        = $user->user_email;

            // SWPM membership level name
            $swpm_member = $this->db->get_swpm_member_by_user( $user->ID );
            if ( $swpm_member ) {
                $levels = $this->db->get_swpm_levels();
                $membership_level = isset( $levels[ $swpm_member->membership_level ] )
                    ? $levels[ $swpm_member->membership_level ]
                    : 'Level ' . $swpm_member->membership_level;
            }

            // UsersWP form type
            $uwp_form_id = get_user_meta( $user->ID, '_uwp_register_form_id', true );
            if ( $uwp_form_id ) {
                $uwp_forms = $this->db->get_uwp_form_types();
                $user_type = isset( $uwp_forms[ $uwp_form_id ] )
                    ? $uwp_forms[ $uwp_form_id ]
                    : 'Form ' . $uwp_form_id;
            }

            // account_type user meta
            $account_type = get_user_meta( $user->ID, 'account_type', true ) ?: '';
        } elseif ( ! empty( $email ) ) {
            // No WP user — try vendor application meta first
            $vendor_data = $this->db->find_vendor_app_by_email( $email );
            if ( $vendor_data ) {
                $first_name   = $vendor_data->first_name;
                $last_name    = $vendor_data->last_name;
                $display_name = trim( $first_name . ' ' . $last_name );
                if ( empty( $display_name ) ) {
                    $display_name = $vendor_data->company_name ?: $email;
                }
            }
            // If no vendor data, try volunteer table
            if ( empty( $first_name ) && empty( $last_name ) ) {
                $volunteer_data = $this->db->find_volunteer_by_email( $email );
                if ( $volunteer_data ) {
                    $first_name   = $volunteer_data->first_name ?: '';
                    $last_name    = $volunteer_data->last_name ?: '';
                    $display_name = trim( $first_name . ' ' . $last_name ) ?: $email;
                }
            }
        }

        // Unsubscribe URL with HMAC token
        $unsub_url = '';
        if ( ! empty( $email ) ) {
            $secret = get_option( 'jemail_unsub_secret', '' );
            if ( empty( $secret ) ) {
                $secret = wp_generate_password( 32, false );
                update_option( 'jemail_unsub_secret', $secret );
            }
            $token = hash_hmac( 'sha256', $email, $secret );
            $unsub_url = add_query_arg( array(
                'jemail_unsub' => '1',
                'email'        => rawurlencode( $email ),
                'token'        => $token,
            ), home_url( '/' ) );
        }

        $tags = array(
            '{{first_name}}'       => $first_name,
            '{{last_name}}'        => $last_name,
            '{{display_name}}'     => $display_name,
            '{{username}}'         => $username,
            '{{email}}'            => $email,
            '{{site_name}}'        => $site_name,
            '{{site_url}}'         => $site_url,
            '{{unsubscribe_url}}'  => $unsub_url,
            // OHA merge tags
            '{{membership_level}}' => $membership_level,
            '{{user_type}}'        => $user_type,
            '{{account_type}}'     => $account_type,
        );

        return str_replace( array_keys( $tags ), array_values( $tags ), $text );
    }

    /**
     * Rewrite links in HTML for click tracking.
     */
    private function add_link_tracking( $html, $campaign_id, $log_id ) {
        return preg_replace_callback( '/href=["\']([^"\']+)["\']/i', function( $matches ) use ( $campaign_id, $log_id ) {
            $url = $matches[1];
            // Don't track unsubscribe, mailto, tel, or anchor links
            if ( strpos( $url, 'jemail_unsub' ) !== false || strpos( $url, 'mailto:' ) === 0 || strpos( $url, 'tel:' ) === 0 || strpos( $url, '#' ) === 0 ) {
                return $matches[0];
            }
            $track_url = add_query_arg( array(
                'jemail_track' => 'click',
                'cid'          => $campaign_id,
                'lid'          => $log_id,
                'url'          => rawurlencode( $url ),
            ), home_url( '/' ) );
            return 'href="' . esc_url( $track_url ) . '"';
        }, $html );
    }
}
