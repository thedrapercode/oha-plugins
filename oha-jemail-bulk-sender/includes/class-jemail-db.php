<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class JEmail_DB {

    public function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $campaigns_table = $wpdb->prefix . JEMAIL_TABLE_CAMPAIGNS;
        $logs_table      = $wpdb->prefix . JEMAIL_TABLE_LOGS;
        $clicks_table    = $wpdb->prefix . JEMAIL_TABLE_CLICKS;
        $unsubs_table    = $wpdb->prefix . JEMAIL_TABLE_UNSUBS;

        $sql = "CREATE TABLE {$campaigns_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL DEFAULT '',
            subject varchar(255) NOT NULL DEFAULT '',
            html_content longtext,
            text_content longtext,
            content_type varchar(20) NOT NULL DEFAULT 'html',
            target_roles text,
            target_swpm_levels text,
            target_uwp_forms text,
            target_vendor_statuses text,
            target_volunteer_statuses text,
            target_gd_listing varchar(20) NOT NULL DEFAULT '',
            target_awards_year varchar(10) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'draft',
            batch_size int(11) NOT NULL DEFAULT 50,
            sent_count int(11) NOT NULL DEFAULT 0,
            fail_count int(11) NOT NULL DEFAULT 0,
            total_count int(11) NOT NULL DEFAULT 0,
            open_count int(11) NOT NULL DEFAULT 0,
            click_count int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            scheduled_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY status (status)
        ) {$charset};

        CREATE TABLE {$logs_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            email varchar(255) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'queued',
            sent_at datetime DEFAULT NULL,
            opened_at datetime DEFAULT NULL,
            error_message text,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY campaign_status (campaign_id, status)
        ) {$charset};

        CREATE TABLE {$clicks_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) UNSIGNED NOT NULL,
            log_id bigint(20) UNSIGNED DEFAULT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            url text NOT NULL,
            clicked_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY log_id (log_id)
        ) {$charset};

        CREATE TABLE {$unsubs_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            email varchar(255) NOT NULL,
            token varchar(64) NOT NULL,
            unsubscribed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            UNIQUE KEY token (token)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /* ─── Campaigns ─── */
    public function get_campaigns( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . JEMAIL_TABLE_CAMPAIGNS;
        $defaults = array( 'status' => '', 'orderby' => 'created_at', 'order' => 'DESC', 'limit' => 50, 'offset' => 0 );
        $args = wp_parse_args( $args, $defaults );
        $where = '1=1'; $params = array();
        if ( ! empty( $args['status'] ) ) { $where .= ' AND status = %s'; $params[] = $args['status']; }
        $allowed = array( 'id', 'title', 'created_at', 'status', 'sent_count' );
        $orderby = in_array( $args['orderby'], $allowed, true ) ? $args['orderby'] : 'created_at';
        $order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
        $params[] = $args['limit']; $params[] = $args['offset'];
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", $params ) );
    }

    public function get_campaign( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . JEMAIL_TABLE_CAMPAIGNS . " WHERE id = %d", $id ) );
    }

    public function insert_campaign( $data ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . JEMAIL_TABLE_CAMPAIGNS, $data );
        return $wpdb->insert_id;
    }

    public function update_campaign( $id, $data ) {
        global $wpdb;
        return $wpdb->update( $wpdb->prefix . JEMAIL_TABLE_CAMPAIGNS, $data, array( 'id' => $id ) );
    }

    public function delete_campaign( $id ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . JEMAIL_TABLE_CLICKS, array( 'campaign_id' => $id ) );
        $wpdb->delete( $wpdb->prefix . JEMAIL_TABLE_LOGS, array( 'campaign_id' => $id ) );
        return $wpdb->delete( $wpdb->prefix . JEMAIL_TABLE_CAMPAIGNS, array( 'id' => $id ) );
    }

    /* ─── Logs ─── */
    public function queue_recipients( $campaign_id, $users ) {
        global $wpdb;
        $table = $wpdb->prefix . JEMAIL_TABLE_LOGS;
        foreach ( $users as $user ) {
            $user_id = isset( $user->ID ) ? (int) $user->ID : 0;
            $wpdb->insert( $table, array(
                'campaign_id' => $campaign_id,
                'user_id'     => $user_id > 0 ? $user_id : null,
                'email'       => $user->user_email,
                'status'      => 'queued',
            ) );
        }
    }

    public function get_queued_emails( $campaign_id, $limit ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . JEMAIL_TABLE_LOGS . " WHERE campaign_id = %d AND status = 'queued' ORDER BY id ASC LIMIT %d", $campaign_id, $limit ) );
    }

    public function mark_sent( $log_id ) {
        global $wpdb;
        $wpdb->update( $wpdb->prefix . JEMAIL_TABLE_LOGS, array( 'status' => 'sent', 'sent_at' => current_time( 'mysql' ) ), array( 'id' => $log_id ) );
    }

    public function mark_failed( $log_id, $error = '' ) {
        global $wpdb;
        $wpdb->update( $wpdb->prefix . JEMAIL_TABLE_LOGS, array( 'status' => 'failed', 'sent_at' => current_time( 'mysql' ), 'error_message' => $error ), array( 'id' => $log_id ) );
    }

    public function mark_opened( $log_id ) {
        global $wpdb;
        $table = $wpdb->prefix . JEMAIL_TABLE_LOGS;
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT opened_at, campaign_id FROM {$table} WHERE id = %d", $log_id ) );
        if ( $row && empty( $row->opened_at ) ) {
            $wpdb->update( $table, array( 'opened_at' => current_time( 'mysql' ) ), array( 'id' => $log_id ) );
            $wpdb->query( $wpdb->prepare( "UPDATE " . $wpdb->prefix . JEMAIL_TABLE_CAMPAIGNS . " SET open_count = open_count + 1 WHERE id = %d", $row->campaign_id ) );
        }
    }

    public function get_campaign_logs( $campaign_id, $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . JEMAIL_TABLE_LOGS;
        $defaults = array( 'status' => '', 'limit' => 100, 'offset' => 0 );
        $args = wp_parse_args( $args, $defaults );
        $where = 'campaign_id = %d'; $params = array( $campaign_id );
        if ( ! empty( $args['status'] ) ) {
            // 'opened' is tracked via the opened_at timestamp, not the status column
            if ( $args['status'] === 'opened' ) {
                $where .= ' AND opened_at IS NOT NULL';
            } else {
                $where .= ' AND status = %s';
                $params[] = $args['status'];
            }
        }
        $params[] = $args['limit']; $params[] = $args['offset'];
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY id ASC LIMIT %d OFFSET %d", $params ) );
    }

    public function count_campaign_logs( $campaign_id, $status = '' ) {
        global $wpdb;
        $table  = $wpdb->prefix . JEMAIL_TABLE_LOGS;
        $where  = 'campaign_id = %d';
        $params = array( $campaign_id );
        if ( ! empty( $status ) ) {
            // 'opened' is tracked via the opened_at timestamp, not the status column
            if ( $status === 'opened' ) {
                $where .= ' AND opened_at IS NOT NULL';
            } else {
                $where .= ' AND status = %s';
                $params[] = $status;
            }
        }
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $params ) );
    }

    public function get_campaign_stats( $campaign_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) as total, SUM(CASE WHEN status='queued' THEN 1 ELSE 0 END) as queued, SUM(CASE WHEN status='sent' THEN 1 ELSE 0 END) as sent, SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failed, SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened FROM " . $wpdb->prefix . JEMAIL_TABLE_LOGS . " WHERE campaign_id = %d", $campaign_id
        ) );
    }

    /* ─── Clicks ─── */
    public function log_click( $campaign_id, $log_id, $user_id, $url ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . JEMAIL_TABLE_CLICKS, array( 'campaign_id' => $campaign_id, 'log_id' => $log_id, 'user_id' => $user_id, 'url' => $url, 'clicked_at' => current_time( 'mysql' ) ) );
        $wpdb->query( $wpdb->prepare( "UPDATE " . $wpdb->prefix . JEMAIL_TABLE_CAMPAIGNS . " SET click_count = click_count + 1 WHERE id = %d", $campaign_id ) );
    }

    public function get_campaign_clicks( $campaign_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT url, COUNT(*) as clicks, COUNT(DISTINCT user_id) as unique_clicks FROM " . $wpdb->prefix . JEMAIL_TABLE_CLICKS . " WHERE campaign_id = %d GROUP BY url ORDER BY clicks DESC", $campaign_id ) );
    }

    /* ─── Unsubscribes ─── */
    public function is_unsubscribed( $email ) {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . $wpdb->prefix . JEMAIL_TABLE_UNSUBS . " WHERE email = %s", $email ) );
    }

    public function add_unsubscribe( $email, $user_id = null ) {
        global $wpdb;
        if ( $this->is_unsubscribed( $email ) ) return false;
        return $wpdb->insert( $wpdb->prefix . JEMAIL_TABLE_UNSUBS, array( 'user_id' => $user_id, 'email' => $email, 'token' => wp_generate_password( 32, false ) ) );
    }

    public function remove_unsubscribe( $email ) {
        global $wpdb;
        return $wpdb->delete( $wpdb->prefix . JEMAIL_TABLE_UNSUBS, array( 'email' => $email ) );
    }

    public function get_unsubscribes( $limit = 100, $offset = 0 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . JEMAIL_TABLE_UNSUBS . " ORDER BY unsubscribed_at DESC LIMIT %d OFFSET %d", $limit, $offset ) );
    }

    public function process_unsub_token( $token, $email ) {
        $secret = get_option( 'jemail_unsub_secret', '' );
        $expected = hash_hmac( 'sha256', $email, $secret );
        if ( hash_equals( $expected, $token ) ) {
            $user = get_user_by( 'email', $email );
            $this->add_unsubscribe( $email, $user ? $user->ID : null );
            return true;
        }
        return false;
    }

    /* ═══ OHA: SWPM Levels & UsersWP Form Types ═══ */

    public function get_swpm_levels() {
        global $wpdb;
        $table = $wpdb->prefix . 'swpm_membership_tbl';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) return array();
        $rows = $wpdb->get_results( "SELECT id, alias FROM {$table} ORDER BY id ASC" );
        $levels = array();
        foreach ( $rows as $row ) { $levels[ $row->id ] = $row->alias; }
        return $levels;
    }

    public function get_uwp_form_types() {
        global $wpdb;
        $uwp_forms = $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'uwp_register_form' AND post_status = 'publish' ORDER BY ID ASC" );
        if ( ! empty( $uwp_forms ) ) {
            $forms = array();
            foreach ( $uwp_forms as $f ) { $forms[ $f->ID ] = $f->post_title; }
            return $forms;
        }
        $form_ids = $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->usermeta} WHERE meta_key = '_uwp_register_form_id' AND meta_value != '' ORDER BY CAST(meta_value AS UNSIGNED) ASC" );
        $known = array( '1' => 'OHA Member (Form 1)', '4' => 'Listing Owner (Form 4)' );
        $forms = array();
        foreach ( $form_ids as $fid ) { $forms[ $fid ] = isset( $known[ $fid ] ) ? $known[ $fid ] : 'UsersWP Form ' . $fid; }
        return $forms;
    }

    public function get_user_ids_by_swpm_level( $level_ids ) {
        global $wpdb;
        $swpm_table = $wpdb->prefix . 'swpm_members_tbl';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$swpm_table}'" ) !== $swpm_table ) return array();
        if ( empty( $level_ids ) ) return array();
        $placeholders = implode( ',', array_fill( 0, count( $level_ids ), '%d' ) );
        $emails = $wpdb->get_col( $wpdb->prepare( "SELECT email FROM {$swpm_table} WHERE membership_level IN ({$placeholders})", $level_ids ) );
        if ( empty( $emails ) ) return array();
        $user_ids = array();
        foreach ( $emails as $email ) { $user = get_user_by( 'email', $email ); if ( $user ) $user_ids[] = (int) $user->ID; }
        return $user_ids;
    }

    public function get_user_ids_by_uwp_form( $form_ids ) {
        global $wpdb;
        if ( empty( $form_ids ) ) return array();
        $placeholders = implode( ',', array_fill( 0, count( $form_ids ), '%s' ) );
        return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_uwp_register_form_id' AND meta_value IN ({$placeholders})", $form_ids ) ) );
    }

    public function get_swpm_member_by_user( $user_id ) {
        global $wpdb;
        $swpm_table = $wpdb->prefix . 'swpm_members_tbl';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$swpm_table}'" ) !== $swpm_table ) return null;
        $user = get_userdata( $user_id );
        if ( ! $user ) return null;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$swpm_table} WHERE email = %s LIMIT 1", $user->user_email ) );
    }

    /* ═══ OHA: Vendor Hub ═══ */

    /**
     * Get available vendor application statuses.
     * Returns statuses found in the vendor_application CPT, or defaults.
     */
    public function get_vendor_statuses() {
        global $wpdb;
        // Check both current and legacy CPTs
        $post_types = array();
        if ( post_type_exists( 'vendor_application' ) )     $post_types[] = 'vendor_application';
        if ( post_type_exists( 'oha_vendor_application' ) ) $post_types[] = 'oha_vendor_application';
        if ( empty( $post_types ) ) return array();

        $pt_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

        // Get statuses from _oha_status (current meta key)
        $found = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT pm.meta_value
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE p.post_type IN ({$pt_placeholders})
               AND p.post_status != 'trash'
               AND pm.meta_key = '_oha_status'
               AND pm.meta_value != ''",
            $post_types
        ) );
        if ( ! is_array( $found ) ) $found = array();

        // Also get statuses from legacy 'status' meta key (no underscore)
        $legacy = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT pm.meta_value
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE p.post_type IN ({$pt_placeholders})
               AND p.post_status != 'trash'
               AND pm.meta_key = 'status'
               AND pm.meta_value != ''",
            $post_types
        ) );
        if ( is_array( $legacy ) ) {
            $found = array_unique( array_merge( $found, $legacy ) );
        }

        // Always include 'Submitted' — Vendor Hub defaults to it when meta is missing
        if ( ! in_array( 'Submitted', $found, true ) ) {
            $found[] = 'Submitted';
        }

        // Ensure logical order (includes legacy lowercase statuses)
        $ordered = array( 'Submitted', 'Started', 'Pending', 'In Review', 'Agreement Sent', 'Agreement Signed', 'Approved', 'Paid', 'Denied', 'approved', 'payment_requested', 'paid', 'rejected', 'review_ready' );
        $result = array();
        foreach ( $ordered as $s ) {
            if ( in_array( $s, $found, true ) ) $result[] = $s;
        }
        // Append any statuses not in the predefined order
        foreach ( $found as $s ) {
            if ( ! in_array( $s, $result, true ) ) $result[] = $s;
        }
        return $result;
    }

    /**
     * Get user IDs that have a vendor application with any of the given statuses.
     *
     * IMPORTANT: The vendor form is public (no login required), so most applications
     * have post_author = 0. Vendors are linked to WP users by matching the 'email'
     * post meta against wp_users.user_email. Also checks post_author as fallback
     * for vendors who were logged in when they submitted.
     *
     * Checks BOTH current and legacy meta/CPT:
     *   - Current: vendor_application CPT + _oha_status meta
     *   - Legacy:  oha_vendor_application CPT + status meta (no underscore prefix)
     * Vendor Hub defaults to 'Submitted' when _oha_status meta doesn't exist.
     */
    public function get_user_ids_by_vendor_status( $statuses ) {
        global $wpdb;
        if ( empty( $statuses ) ) return array();

        // Build post type list (current + legacy)
        $post_types = array();
        if ( post_type_exists( 'vendor_application' ) )     $post_types[] = 'vendor_application';
        if ( post_type_exists( 'oha_vendor_application' ) ) $post_types[] = 'oha_vendor_application';
        if ( empty( $post_types ) ) return array();

        $pt_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
        $st_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

        // Step 1: Get vendor application IDs matching the target statuses
        $params = array_merge( $post_types, $statuses, $statuses, $statuses );
        $app_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT p.ID
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_oha_status'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'status'
             WHERE p.post_type IN ({$pt_placeholders})
               AND p.post_status != 'trash'
               AND (
                   pm1.meta_value IN ({$st_placeholders})
                   OR pm2.meta_value IN ({$st_placeholders})
                   OR (pm1.meta_value IS NULL AND pm2.meta_value IS NULL AND 'Submitted' IN ({$st_placeholders}))
               )",
            $params
        ) );

        if ( empty( $app_ids ) ) return array();

        // Step 2: Get user IDs by matching vendor email to WP user email
        $user_ids = array();

        // Method A: Match via 'email' post meta → wp_users.user_email
        $id_placeholders = implode( ',', array_fill( 0, count( $app_ids ), '%d' ) );
        $email_matched = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT u.ID
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->postmeta} pm ON pm.meta_value = u.user_email
             WHERE pm.meta_key = 'email'
               AND pm.post_id IN ({$id_placeholders})",
            $app_ids
        ) );
        if ( ! empty( $email_matched ) ) {
            $user_ids = array_merge( $user_ids, $email_matched );
        }

        // Method B: Also include post_author matches (for logged-in submissions)
        $author_matched = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT post_author
             FROM {$wpdb->posts}
             WHERE ID IN ({$id_placeholders})
               AND post_author > 0",
            $app_ids
        ) );
        if ( ! empty( $author_matched ) ) {
            $user_ids = array_merge( $user_ids, $author_matched );
        }

        return array_map( 'intval', array_unique( $user_ids ) );
    }

    /* ═══ OHA: Volunteer Hub ═══ */

    /**
     * Get available volunteer statuses from the oha_volunteers table.
     */
    public function get_volunteer_statuses() {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteers';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) return array();
        $found = $wpdb->get_col( "SELECT DISTINCT status FROM {$table} WHERE status != '' ORDER BY status ASC" );
        if ( ! empty( $found ) ) return $found;
        return array( 'pending', 'approved', 'assigned', 'declined', 'rejected' );
    }

    /**
     * Get user IDs from the oha_volunteers table with any of the given statuses.
     */
    public function get_user_ids_by_volunteer_status( $statuses ) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteers';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) return array();
        if ( empty( $statuses ) ) return array();
        $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$table} WHERE user_id IS NOT NULL AND user_id > 0 AND status IN ({$placeholders})",
            $statuses
        ) ) );
    }

    /**
     * Get volunteer emails that do NOT have a corresponding WP user account.
     * Returns stdClass objects mimicking WP user objects for compatibility with queue_recipients.
     * Volunteers sign up publicly and most do not create a WP account — this ensures
     * all 75 (or however many) pending volunteers are included, not just the 11 with WP accounts.
     */
    public function get_volunteer_only_recipients( $statuses ) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteers';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) return array();
        if ( empty( $statuses ) ) return array();

        $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, email, first_name, last_name FROM {$table}
             WHERE email != '' AND status IN ({$placeholders})",
            $statuses
        ) );
        if ( empty( $rows ) ) return array();

        // Filter out emails that already exist as WP user accounts (handled by get_users query)
        $all_emails = array_unique( array_filter( wp_list_pluck( $rows, 'email' ) ) );
        if ( empty( $all_emails ) ) return array();

        $email_placeholders = implode( ',', array_fill( 0, count( $all_emails ), '%s' ) );
        $wp_emails = $wpdb->get_col( $wpdb->prepare(
            "SELECT LOWER(user_email) FROM {$wpdb->users} WHERE LOWER(user_email) IN ({$email_placeholders})",
            array_map( 'strtolower', $all_emails )
        ) );
        $wp_emails = array_map( 'strtolower', $wp_emails );

        $recipients  = array();
        $seen_emails = array();
        foreach ( $rows as $row ) {
            $email_lower = strtolower( $row->email );
            if ( in_array( $email_lower, $wp_emails, true ) ) continue; // already included via WP user query
            if ( isset( $seen_emails[ $email_lower ] ) ) continue;       // deduplicate
            $seen_emails[ $email_lower ] = true;

            $obj               = new \stdClass();
            $obj->ID           = 0;
            $obj->user_email   = $row->email;
            $obj->user_login   = '';
            $obj->display_name = trim( $row->first_name . ' ' . $row->last_name );
            if ( empty( trim( $obj->display_name ) ) ) {
                $obj->display_name = $row->email;
            }
            $obj->first_name        = $row->first_name ?: '';
            $obj->last_name         = $row->last_name ?: '';
            $obj->_volunteer_row_id = (int) $row->id;
            $recipients[]           = $obj;
        }
        return $recipients;
    }

    /**
     * Find a volunteer record by email address.
     * Returns stdClass with first_name and last_name, or null if not found.
     * Used by replace_merge_tags() to populate name tags for non-WP volunteers.
     */
    public function find_volunteer_by_email( $email ) {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_volunteers';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) return null;
        if ( empty( $email ) ) return null;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT first_name, last_name FROM {$table} WHERE LOWER(email) = LOWER(%s) ORDER BY id DESC LIMIT 1",
            $email
        ) );
    }

    /* ═══ OHA: GeoDirectory Listings ═══ */

    /**
     * Check if GeoDirectory post types exist.
     */
    public function has_geodirectory() {
        return post_type_exists( 'gd_place' ) || post_type_exists( 'gd_event' );
    }

    /**
     * Get user IDs that have created or claimed at least one GeoDirectory listing.
     */
    public function get_user_ids_with_gd_listing() {
        global $wpdb;
        $gd_types = array();
        if ( post_type_exists( 'gd_place' ) ) $gd_types[] = 'gd_place';
        if ( post_type_exists( 'gd_event' ) ) $gd_types[] = 'gd_event';
        if ( empty( $gd_types ) ) return array();
        $placeholders = implode( ',', array_fill( 0, count( $gd_types ), '%s' ) );
        return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT post_author FROM {$wpdb->posts}
             WHERE post_type IN ({$placeholders})
               AND post_status IN ('publish', 'draft', 'pending')
               AND post_author > 0",
            $gd_types
        ) ) );
    }

    /* ═══ Recipient Query (All 7 Filters) ═══ */

    /**
     * Get vendor application emails that do NOT have a corresponding WP user account.
     * Returns stdClass objects mimicking WP user objects for compatibility with queue_recipients.
     * Pulls first_name, last_name, company_name from vendor application meta for merge tags.
     */
    public function get_vendor_only_recipients( $statuses ) {
        global $wpdb;
        if ( empty( $statuses ) ) return array();

        // Build post type list
        $post_types = array();
        if ( post_type_exists( 'vendor_application' ) )     $post_types[] = 'vendor_application';
        if ( post_type_exists( 'oha_vendor_application' ) ) $post_types[] = 'oha_vendor_application';
        if ( empty( $post_types ) ) return array();

        $pt_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
        $st_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

        // Get vendor application IDs + emails matching statuses
        $params = array_merge( $post_types, $statuses, $statuses, $statuses );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT DISTINCT p.ID, em.meta_value AS vendor_email
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_oha_status'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'status'
             INNER JOIN {$wpdb->postmeta} em  ON p.ID = em.post_id  AND em.meta_key = 'email'
             WHERE p.post_type IN ({$pt_placeholders})
               AND p.post_status != 'trash'
               AND em.meta_value != ''
               AND (
                   pm1.meta_value IN ({$st_placeholders})
                   OR pm2.meta_value IN ({$st_placeholders})
                   OR (pm1.meta_value IS NULL AND pm2.meta_value IS NULL AND 'Submitted' IN ({$st_placeholders}))
               )",
            $params
        ) );
        if ( empty( $rows ) ) return array();

        // Filter out any emails that already exist as WP user accounts
        $all_emails = array_unique( array_filter( wp_list_pluck( $rows, 'vendor_email' ) ) );
        if ( empty( $all_emails ) ) return array();

        $email_placeholders = implode( ',', array_fill( 0, count( $all_emails ), '%s' ) );
        $wp_emails = $wpdb->get_col( $wpdb->prepare(
            "SELECT LOWER(user_email) FROM {$wpdb->users} WHERE LOWER(user_email) IN ({$email_placeholders})",
            array_map( 'strtolower', $all_emails )
        ) );
        $wp_emails = array_map( 'strtolower', $wp_emails );

        // Build synthetic user objects for vendor-only emails
        $recipients = array();
        $seen_emails = array();
        foreach ( $rows as $row ) {
            $email_lower = strtolower( $row->vendor_email );
            if ( in_array( $email_lower, $wp_emails, true ) ) continue; // has WP account
            if ( isset( $seen_emails[ $email_lower ] ) ) continue;       // deduplicate
            $seen_emails[ $email_lower ] = true;

            $app_id = (int) $row->ID;
            $obj = new \stdClass();
            $obj->ID           = 0; // no WP user
            $obj->user_email   = $row->vendor_email;
            $obj->user_login   = '';
            $obj->display_name = trim(
                get_post_meta( $app_id, 'first_name', true ) . ' ' .
                get_post_meta( $app_id, 'last_name', true )
            );
            if ( empty( trim( $obj->display_name ) ) ) {
                $obj->display_name = get_post_meta( $app_id, 'company_name', true ) ?: $row->vendor_email;
            }
            // Store extra vendor meta for merge tags
            $obj->first_name   = get_post_meta( $app_id, 'first_name', true ) ?: '';
            $obj->last_name    = get_post_meta( $app_id, 'last_name', true ) ?: '';
            $obj->_vendor_app_id = $app_id;
            $recipients[] = $obj;
        }
        return $recipients;
    }

    /**
     * Find the most recent vendor application by email address.
     * Returns stdClass with first_name, last_name, company_name or null.
     */
    public function find_vendor_app_by_email( $email ) {
        global $wpdb;
        if ( empty( $email ) ) return null;

        $post_types = array();
        if ( post_type_exists( 'vendor_application' ) )     $post_types[] = 'vendor_application';
        if ( post_type_exists( 'oha_vendor_application' ) ) $post_types[] = 'oha_vendor_application';
        if ( empty( $post_types ) ) return null;

        $pt_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
        $params = array_merge( $post_types, array( $email ) );
        $app_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} em ON p.ID = em.post_id AND em.meta_key = 'email'
             WHERE p.post_type IN ({$pt_placeholders})
               AND p.post_status != 'trash'
               AND LOWER(em.meta_value) = LOWER(%s)
             ORDER BY p.post_date DESC
             LIMIT 1",
            $params
        ) );
        if ( ! $app_id ) return null;

        $obj = new \stdClass();
        $obj->first_name   = get_post_meta( $app_id, 'first_name', true ) ?: '';
        $obj->last_name    = get_post_meta( $app_id, 'last_name', true ) ?: '';
        $obj->company_name = get_post_meta( $app_id, 'company_name', true ) ?: '';
        return $obj;
    }

    /* ═══ OHA: Awards Plugin ═══ */

    /**
     * Return distinct award years found in oha_award_entry posts, newest first.
     */
    public function get_awards_years() {
        global $wpdb;
        if ( ! post_type_exists( 'oha_award_entry' ) ) return array();
        $years = $wpdb->get_col(
            "SELECT DISTINCT pm.meta_value
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE p.post_type = 'oha_award_entry'
               AND p.post_status != 'trash'
               AND pm.meta_key = '_oha_award_year'
               AND pm.meta_value != ''
             ORDER BY pm.meta_value DESC"
        );
        return is_array( $years ) ? $years : array();
    }

    /**
     * Get WP user IDs of everyone who submitted an award in the given year.
     *
     * Actor submissions link via _oha_owner_user_id (WP user ID).
     * Campsite submissions link via _oha_contact_email matched to wp_users.
     */
    public function get_user_ids_by_awards_year( $year ) {
        global $wpdb;
        if ( ! post_type_exists( 'oha_award_entry' ) ) return array();
        if ( empty( $year ) ) return array();

        $entry_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} py ON p.ID = py.post_id AND py.meta_key = '_oha_award_year'
             WHERE p.post_type = 'oha_award_entry'
               AND p.post_status != 'trash'
               AND py.meta_value = %s",
            $year
        ) );
        if ( empty( $entry_ids ) ) return array();

        $id_placeholders = implode( ',', array_fill( 0, count( $entry_ids ), '%d' ) );
        $user_ids = array();

        // Actor submissions: _oha_owner_user_id holds the WP user ID directly
        $actor_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT CAST(meta_value AS UNSIGNED)
             FROM {$wpdb->postmeta}
             WHERE meta_key = '_oha_owner_user_id'
               AND CAST(meta_value AS UNSIGNED) > 0
               AND post_id IN ({$id_placeholders})",
            $entry_ids
        ) );
        if ( ! empty( $actor_ids ) ) {
            $user_ids = array_merge( $user_ids, $actor_ids );
        }

        // Campsite submissions: match _oha_contact_email to wp_users.user_email
        $contact_emails = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT meta_value
             FROM {$wpdb->postmeta}
             WHERE meta_key = '_oha_contact_email'
               AND meta_value != ''
               AND post_id IN ({$id_placeholders})",
            $entry_ids
        ) );
        if ( ! empty( $contact_emails ) ) {
            $ep = implode( ',', array_fill( 0, count( $contact_emails ), '%s' ) );
            $matched = $wpdb->get_col( $wpdb->prepare(
                "SELECT ID FROM {$wpdb->users} WHERE LOWER(user_email) IN ({$ep})",
                array_map( 'strtolower', $contact_emails )
            ) );
            if ( ! empty( $matched ) ) {
                $user_ids = array_merge( $user_ids, $matched );
            }
        }

        return array_map( 'intval', array_unique( $user_ids ) );
    }

    /**
     * Get campsite award submitters who do NOT have a WP user account.
     * Returns synthetic stdClass objects for compatibility with queue_recipients().
     */
    public function get_awards_only_recipients( $year ) {
        global $wpdb;
        if ( ! post_type_exists( 'oha_award_entry' ) ) return array();
        if ( empty( $year ) ) return array();

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.ID, em.meta_value AS contact_email, cn.meta_value AS contact_name
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} py ON p.ID = py.post_id AND py.meta_key = '_oha_award_year' AND py.meta_value = %s
             INNER JOIN {$wpdb->postmeta} em ON p.ID = em.post_id AND em.meta_key = '_oha_contact_email' AND em.meta_value != ''
             LEFT  JOIN {$wpdb->postmeta} cn ON p.ID = cn.post_id AND cn.meta_key = '_oha_contact_name'
             WHERE p.post_type = 'oha_award_entry'
               AND p.post_status != 'trash'",
            $year
        ) );
        if ( empty( $rows ) ) return array();

        $all_emails = array_unique( array_filter( wp_list_pluck( $rows, 'contact_email' ) ) );
        if ( empty( $all_emails ) ) return array();

        $ep = implode( ',', array_fill( 0, count( $all_emails ), '%s' ) );
        $wp_emails = $wpdb->get_col( $wpdb->prepare(
            "SELECT LOWER(user_email) FROM {$wpdb->users} WHERE LOWER(user_email) IN ({$ep})",
            array_map( 'strtolower', $all_emails )
        ) );
        $wp_emails = array_map( 'strtolower', $wp_emails );

        $recipients  = array();
        $seen_emails = array();
        foreach ( $rows as $row ) {
            $email_lower = strtolower( $row->contact_email );
            if ( in_array( $email_lower, $wp_emails, true ) ) continue;
            if ( isset( $seen_emails[ $email_lower ] ) ) continue;
            $seen_emails[ $email_lower ] = true;

            $obj               = new \stdClass();
            $obj->ID           = 0;
            $obj->user_email   = $row->contact_email;
            $obj->user_login   = '';
            $obj->display_name = $row->contact_name ?: $row->contact_email;
            $name_parts        = explode( ' ', trim( $obj->display_name ), 2 );
            $obj->first_name   = $name_parts[0] ?: '';
            $obj->last_name    = isset( $name_parts[1] ) ? $name_parts[1] : '';
            $recipients[]      = $obj;
        }
        return $recipients;
    }

    public function count_recipients( $roles = array(), $swpm_levels = array(), $uwp_forms = array(), $vendor_statuses = array(), $volunteer_statuses = array(), $gd_listing = '', $awards_year = '' ) {
        return count( $this->get_recipients( $roles, $swpm_levels, $uwp_forms, $vendor_statuses, $volunteer_statuses, $gd_listing, $awards_year ) );
    }

    public function get_recipients( $roles = array(), $swpm_levels = array(), $uwp_forms = array(), $vendor_statuses = array(), $volunteer_statuses = array(), $gd_listing = '', $awards_year = '' ) {
        // IMPORTANT: Do NOT use 'fields' => array(...) here. When fields is an array,
        // WordPress's WP_User_Query can silently drop the role-based meta JOIN, causing
        // role__in to be ignored and ALL users to be returned. Use 'all' for reliable filtering.
        $args = array( 'fields' => 'all', 'number' => -1 );
        if ( ! empty( $roles ) ) { $args['role__in'] = $roles; }
        $users = get_users( $args );

        // Filter by SWPM level (AND)
        if ( ! empty( $swpm_levels ) ) {
            $swpm_ids = $this->get_user_ids_by_swpm_level( $swpm_levels );
            $users = array_filter( $users, function( $u ) use ( $swpm_ids ) { return in_array( (int) $u->ID, $swpm_ids, true ); } );
        }

        // Filter by UsersWP form type (AND)
        if ( ! empty( $uwp_forms ) ) {
            $uwp_ids = $this->get_user_ids_by_uwp_form( $uwp_forms );
            $users = array_filter( $users, function( $u ) use ( $uwp_ids ) { return in_array( (int) $u->ID, $uwp_ids, true ); } );
        }

        // Filter by Vendor Hub status (AND)
        if ( ! empty( $vendor_statuses ) ) {
            $vendor_ids = $this->get_user_ids_by_vendor_status( $vendor_statuses );
            $users = array_filter( $users, function( $u ) use ( $vendor_ids ) { return in_array( (int) $u->ID, $vendor_ids, true ); } );
        }

        // Filter by Volunteer Hub status (AND)
        if ( ! empty( $volunteer_statuses ) ) {
            $vol_ids = $this->get_user_ids_by_volunteer_status( $volunteer_statuses );
            $users = array_filter( $users, function( $u ) use ( $vol_ids ) { return in_array( (int) $u->ID, $vol_ids, true ); } );
        }

        // Filter by GeoDirectory listing ownership (AND)
        if ( $gd_listing === 'has_listing' || $gd_listing === 'no_listing' ) {
            $gd_ids = $this->get_user_ids_with_gd_listing();
            if ( $gd_listing === 'has_listing' ) {
                $users = array_filter( $users, function( $u ) use ( $gd_ids ) { return in_array( (int) $u->ID, $gd_ids, true ); } );
            } else {
                $users = array_filter( $users, function( $u ) use ( $gd_ids ) { return ! in_array( (int) $u->ID, $gd_ids, true ); } );
            }
        }

        // Filter by Awards submission year (AND)
        if ( ! empty( $awards_year ) ) {
            $awards_ids = $this->get_user_ids_by_awards_year( $awards_year );
            $users = array_filter( $users, function( $u ) use ( $awards_ids ) { return in_array( (int) $u->ID, $awards_ids, true ); } );
        }

        // Exclude unsubscribed
        $recipients = array();
        $seen_emails = array();
        foreach ( $users as $user ) {
            if ( ! $this->is_unsubscribed( $user->user_email ) ) {
                $recipients[] = $user;
                $seen_emails[ strtolower( $user->user_email ) ] = true;
            }
        }

        // Include vendor-only emails (vendors without WP accounts).
        // Only add these when vendor is the ONLY filter active, because non-WP
        // vendors can't match WP-specific filters (roles, SWPM, UsersWP, etc.)
        if ( ! empty( $vendor_statuses ) ) {
            $has_wp_filters = ! empty( $roles ) || ! empty( $swpm_levels ) || ! empty( $uwp_forms )
                              || ! empty( $volunteer_statuses ) || ! empty( $gd_listing );
            if ( ! $has_wp_filters ) {
                $vendor_only = $this->get_vendor_only_recipients( $vendor_statuses );
                foreach ( $vendor_only as $vo ) {
                    $email_lower = strtolower( $vo->user_email );
                    if ( isset( $seen_emails[ $email_lower ] ) ) continue; // already included
                    if ( ! $this->is_unsubscribed( $vo->user_email ) ) {
                        $recipients[] = $vo;
                        $seen_emails[ $email_lower ] = true;
                    }
                }
            }
        }

        // Include volunteer-only emails (volunteers without WP accounts).
        // Most volunteers sign up via the public form without creating a WP account,
        // so user_id is NULL for them. This adds them directly by email from oha_volunteers.
        // Only when volunteer is the ONLY filter active (same logic as vendor-only above).
        if ( ! empty( $volunteer_statuses ) ) {
            $has_wp_filters = ! empty( $roles ) || ! empty( $swpm_levels ) || ! empty( $uwp_forms )
                              || ! empty( $vendor_statuses ) || ! empty( $gd_listing );
            if ( ! $has_wp_filters ) {
                $volunteer_only = $this->get_volunteer_only_recipients( $volunteer_statuses );
                foreach ( $volunteer_only as $vo ) {
                    $email_lower = strtolower( $vo->user_email );
                    if ( isset( $seen_emails[ $email_lower ] ) ) continue; // already included
                    if ( ! $this->is_unsubscribed( $vo->user_email ) ) {
                        $recipients[] = $vo;
                        $seen_emails[ $email_lower ] = true;
                    }
                }
            }
        }

        // Include awards-only emails (campsite submitters without WP accounts).
        // The campsite award form is public — submitters may not have a WP account.
        // Only when awards year is the ONLY filter active.
        if ( ! empty( $awards_year ) ) {
            $has_wp_filters = ! empty( $roles ) || ! empty( $swpm_levels ) || ! empty( $uwp_forms )
                              || ! empty( $vendor_statuses ) || ! empty( $volunteer_statuses ) || ! empty( $gd_listing );
            if ( ! $has_wp_filters ) {
                $awards_only = $this->get_awards_only_recipients( $awards_year );
                foreach ( $awards_only as $ao ) {
                    $email_lower = strtolower( $ao->user_email );
                    if ( isset( $seen_emails[ $email_lower ] ) ) continue;
                    if ( ! $this->is_unsubscribed( $ao->user_email ) ) {
                        $recipients[] = $ao;
                        $seen_emails[ $email_lower ] = true;
                    }
                }
            }
        }

        return array_values( $recipients );
    }
}
