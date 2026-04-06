<?php
/**
 * OHA Sponsor Hub
 *
 * Manages convention sponsors inside the OHA Board Hub.
 * - Shortcode [oha_sponsor_hub]: full CRUD admin UI
 * - REST endpoints: oha/v1/sponsors/save|delete|update-status|settings
 * - Stripe webhook: hooks oha_process_webhook_event to auto-mark oha_sponsor posts Paid
 *
 * Data stored on oha_sponsor CPT (registered in oha-board-hub.lib.php):
 *   sponsor_type, sponsor_tier, sponsor_amount, sponsor_year, sponsor_contribution,
 *   sponsor_notes, contact_name, contact_email, contact_phone,
 *   _oha_status, _payment_method, _paid_date, _stripe_session_id, _logo_url, _logo_id
 *
 * Settings: oha_sponsor_hub_settings { payment_link_100, active_year }
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( defined( 'OHA_SPONSOR_HUB_ONCE' ) ) return;
define( 'OHA_SPONSOR_HUB_ONCE', true );

define( 'OHA_SH_OPT', 'oha_sponsor_hub_settings' );

// ============================================================
// Settings helpers
// ============================================================

function oha_sh_defaults() {
    return array(
        'payment_link_100' => '',
        'active_year'      => (string) date( 'Y' ),
    );
}

function oha_sh_get() {
    $opt = get_option( OHA_SH_OPT, array() );
    if ( ! is_array( $opt ) ) $opt = array();
    return array_merge( oha_sh_defaults(), $opt );
}

function oha_sh_user_can() {
    return current_user_can( 'manage_options' ) || current_user_can( 'read_oha_board_hub' );
}

// ============================================================
// REST Endpoints
// ============================================================

add_action( 'rest_api_init', function () {

    register_rest_route( 'oha/v1', '/sponsors/save', array(
        'methods'             => 'POST',
        'permission_callback' => 'oha_sh_user_can',
        'callback'            => 'oha_sh_rest_save',
    ) );

    register_rest_route( 'oha/v1', '/sponsors/delete', array(
        'methods'             => 'POST',
        'permission_callback' => 'oha_sh_user_can',
        'callback'            => 'oha_sh_rest_delete',
    ) );

    register_rest_route( 'oha/v1', '/sponsors/update-status', array(
        'methods'             => 'POST',
        'permission_callback' => 'oha_sh_user_can',
        'callback'            => 'oha_sh_rest_update_status',
    ) );

    register_rest_route( 'oha/v1', '/sponsors/settings', array(
        'methods'             => 'POST',
        'permission_callback' => 'oha_sh_user_can',
        'callback'            => 'oha_sh_rest_save_settings',
    ) );

} );

function oha_sh_rest_save( WP_REST_Request $req ) {
    $p = $req->get_json_params();
    if ( ! is_array( $p ) ) {
        return new WP_Error( 'bad_request', 'Invalid data', array( 'status' => 400 ) );
    }

    $post_id = ! empty( $p['id'] ) ? absint( $p['id'] ) : 0;

    if ( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'oha_sponsor' ) {
            return new WP_Error( 'not_found', 'Sponsor not found', array( 'status' => 404 ) );
        }
    }

    $name = sanitize_text_field( $p['name'] ?? '' );
    if ( empty( $name ) ) {
        return new WP_Error( 'missing_name', 'Display name is required', array( 'status' => 400 ) );
    }

    $allowed_types    = array( 'company', 'individual' );
    $allowed_tiers    = array( 'convention', 'water_station' );
    $allowed_statuses = array( 'Invited', 'Paid', 'In-Kind', 'Confirmed' );
    $allowed_methods  = array( 'stripe', 'cash', 'check', 'in-kind', 'waived' );

    $sponsor_type = in_array( $p['sponsor_type'] ?? '', $allowed_types, true )    ? $p['sponsor_type']    : 'company';
    $tier         = in_array( $p['sponsor_tier'] ?? '', $allowed_tiers, true )     ? $p['sponsor_tier']    : 'convention';
    $status       = in_array( $p['_oha_status'] ?? '', $allowed_statuses, true )   ? $p['_oha_status']     : 'Invited';
    $method       = in_array( $p['_payment_method'] ?? '', $allowed_methods, true ) ? $p['_payment_method'] : 'stripe';
    $amount       = is_numeric( $p['sponsor_amount'] ?? '' ) ? round( (float) $p['sponsor_amount'], 2 ) : 0.00;
    $year         = ( isset( $p['sponsor_year'] ) && preg_match( '/^\d{4}$/', $p['sponsor_year'] ) ) ? $p['sponsor_year'] : (string) date( 'Y' );
    $paid_date    = ! empty( $p['_paid_date'] ) ? sanitize_text_field( $p['_paid_date'] ) : '';

    $contact_name  = sanitize_text_field( $p['contact_name'] ?? '' );
    $contact_email = sanitize_email( $p['contact_email'] ?? '' );
    $contact_phone = sanitize_text_field( $p['contact_phone'] ?? '' );
    $contribution  = sanitize_textarea_field( $p['sponsor_contribution'] ?? '' );
    $notes         = sanitize_textarea_field( $p['sponsor_notes'] ?? '' );
    $logo_url      = esc_url_raw( $p['_logo_url'] ?? '' );
    $logo_id       = absint( $p['_logo_id'] ?? 0 );

    $post_args = array(
        'post_type'   => 'oha_sponsor',
        'post_status' => 'publish',
        'post_title'  => $name,
    );

    if ( $post_id ) {
        $post_args['ID'] = $post_id;
        $result = wp_update_post( $post_args, true );
    } else {
        $result = wp_insert_post( $post_args, true );
    }

    if ( is_wp_error( $result ) ) {
        return new WP_Error( 'save_failed', $result->get_error_message(), array( 'status' => 500 ) );
    }

    $post_id = (int) $result;

    update_post_meta( $post_id, 'sponsor_type',         $sponsor_type );
    update_post_meta( $post_id, 'sponsor_tier',         $tier );
    update_post_meta( $post_id, 'sponsor_amount',       $amount );
    update_post_meta( $post_id, 'sponsor_year',         $year );
    update_post_meta( $post_id, '_oha_status',          $status );
    update_post_meta( $post_id, '_payment_method',      $method );
    update_post_meta( $post_id, 'contact_name',         $contact_name );
    update_post_meta( $post_id, 'contact_email',        $contact_email );
    update_post_meta( $post_id, 'contact_phone',        $contact_phone );
    update_post_meta( $post_id, 'sponsor_contribution', $contribution );
    update_post_meta( $post_id, 'sponsor_notes',        $notes );
    update_post_meta( $post_id, '_logo_url',            $logo_url );
    update_post_meta( $post_id, '_logo_id',             $logo_id );
    if ( $paid_date ) {
        update_post_meta( $post_id, '_paid_date', $paid_date );
    }

    return array( 'ok' => true, 'id' => $post_id, 'name' => $name );
}

function oha_sh_rest_delete( WP_REST_Request $req ) {
    $p       = $req->get_json_params();
    $post_id = absint( $p['id'] ?? 0 );
    if ( ! $post_id ) {
        return new WP_Error( 'bad_request', 'ID required', array( 'status' => 400 ) );
    }
    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'oha_sponsor' ) {
        return new WP_Error( 'not_found', 'Sponsor not found', array( 'status' => 404 ) );
    }
    wp_delete_post( $post_id, true );
    return array( 'ok' => true );
}

function oha_sh_rest_update_status( WP_REST_Request $req ) {
    $p       = $req->get_json_params();
    $post_id = absint( $p['id'] ?? 0 );
    if ( ! $post_id ) {
        return new WP_Error( 'bad_request', 'ID required', array( 'status' => 400 ) );
    }
    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'oha_sponsor' ) {
        return new WP_Error( 'not_found', 'Sponsor not found', array( 'status' => 404 ) );
    }

    $allowed_statuses = array( 'Invited', 'Paid', 'In-Kind', 'Confirmed' );
    $allowed_methods  = array( 'stripe', 'cash', 'check', 'in-kind', 'waived', '' );

    $status    = in_array( $p['status'] ?? '', $allowed_statuses, true ) ? $p['status'] : null;
    $method    = in_array( $p['method'] ?? '', $allowed_methods, true )  ? $p['method'] : null;
    $paid_date = ! empty( $p['paid_date'] ) ? sanitize_text_field( $p['paid_date'] ) : null;

    if ( $status )              update_post_meta( $post_id, '_oha_status',     $status );
    if ( $method !== null )     update_post_meta( $post_id, '_payment_method', $method );
    if ( $paid_date !== null )  update_post_meta( $post_id, '_paid_date',      $paid_date );

    return array( 'ok' => true );
}

function oha_sh_rest_save_settings( WP_REST_Request $req ) {
    $p = $req->get_json_params();
    if ( ! is_array( $p ) ) {
        return new WP_Error( 'bad_request', 'Invalid data', array( 'status' => 400 ) );
    }
    $s = oha_sh_get();
    $s['payment_link_100'] = esc_url_raw( $p['payment_link_100'] ?? '' );
    if ( isset( $p['active_year'] ) && preg_match( '/^\d{4}$/', $p['active_year'] ) ) {
        $s['active_year'] = $p['active_year'];
    }
    update_option( OHA_SH_OPT, $s );
    return array( 'ok' => true );
}

// ============================================================
// Stripe Webhook Handler
// Hooks into oha_process_webhook_event (owned by Board Hub).
// Returns true only when client_reference_id resolves to an
// oha_sponsor post, so membership payments pass through safely.
// ============================================================

add_filter( 'oha_process_webhook_event', function ( $handled, $event ) {
    if ( $handled ) return true;
    if ( $event->type !== 'checkout.session.completed' ) return $handled;

    $session = $event->data->object ?? null;
    if ( ! $session ) return $handled;

    $ref_id = $session->client_reference_id ?? '';
    if ( empty( $ref_id ) || ! is_numeric( $ref_id ) ) return $handled;

    $post = get_post( absint( $ref_id ) );
    if ( ! $post || $post->post_type !== 'oha_sponsor' ) return $handled;

    update_post_meta( $post->ID, '_oha_status',        'Paid' );
    update_post_meta( $post->ID, '_payment_method',    'stripe' );
    update_post_meta( $post->ID, '_stripe_session_id', sanitize_text_field( $session->id ?? '' ) );
    update_post_meta( $post->ID, '_paid_date',         current_time( 'mysql' ) );

    if ( ! empty( $session->amount_total ) ) {
        update_post_meta( $post->ID, 'sponsor_amount', round( $session->amount_total / 100, 2 ) );
    }

    return true;
}, 10, 2 );

// ============================================================
// CSV Export helper (called from shortcode POST handler)
// ============================================================

function oha_sh_export_csv() {
    $year   = isset( $_POST['sh_year'] )   ? sanitize_text_field( $_POST['sh_year'] )   : (string) date( 'Y' );
    $status = isset( $_POST['sh_status'] ) ? sanitize_text_field( $_POST['sh_status'] ) : '';
    $tier   = isset( $_POST['sh_tier'] )   ? sanitize_text_field( $_POST['sh_tier'] )   : '';

    $meta_query = array( array( 'key' => 'sponsor_year', 'value' => $year, 'compare' => '=' ) );
    if ( $status ) $meta_query[] = array( 'key' => '_oha_status',  'value' => $status, 'compare' => '=' );
    if ( $tier )   $meta_query[] = array( 'key' => 'sponsor_tier', 'value' => $tier,   'compare' => '=' );

    $query = new WP_Query( array(
        'post_type'      => 'oha_sponsor',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_query'     => $meta_query,
    ) );

    $scsv = function ( $v ) {
        if ( ! is_string( $v ) || $v === '' ) return $v;
        if ( preg_match( '/^[=+\-@\t\r]/', $v ) ) return "'" . $v;
        return $v;
    };

    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="sponsors-' . sanitize_file_name( $year ) . '.csv"' );
    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, array( 'Year', 'Name', 'Type', 'Tier', 'Amount', 'Status', 'Payment Method', 'Paid Date', 'Contact Name', 'Contact Email', 'Contact Phone', 'Contribution', 'Notes', 'Logo URL' ) );

    while ( $query->have_posts() ) {
        $query->the_post();
        $id         = get_the_ID();
        $tier_meta  = get_post_meta( $id, 'sponsor_tier', true );
        $tier_label = $tier_meta === 'water_station' ? 'Water Station' : 'Convention Sponsor';
        $type_raw   = get_post_meta( $id, 'sponsor_type', true );
        fputcsv( $out, array(
            $scsv( get_post_meta( $id, 'sponsor_year',        true ) ),
            $scsv( get_the_title() ),
            $scsv( ucfirst( $type_raw ?: 'company' ) ),
            $scsv( $tier_label ),
            get_post_meta( $id, 'sponsor_amount',      true ),
            $scsv( get_post_meta( $id, '_oha_status',        true ) ),
            $scsv( get_post_meta( $id, '_payment_method',    true ) ),
            $scsv( get_post_meta( $id, '_paid_date',         true ) ),
            $scsv( get_post_meta( $id, 'contact_name',       true ) ),
            $scsv( get_post_meta( $id, 'contact_email',      true ) ),
            $scsv( get_post_meta( $id, 'contact_phone',      true ) ),
            $scsv( get_post_meta( $id, 'sponsor_contribution', true ) ),
            $scsv( get_post_meta( $id, 'sponsor_notes',      true ) ),
            get_post_meta( $id, '_logo_url', true ),
        ) );
    }
    wp_reset_postdata();
    fclose( $out );
}

// ============================================================
// Shortcode [oha_sponsor_hub]
// ============================================================

add_shortcode( 'oha_sponsor_hub', function () {

    if ( ! oha_sh_user_can() ) {
        return '<p>Access denied.</p>';
    }

    // WP media uploader assets (safe to call here)
    wp_enqueue_media();

    // CSV export (POST handler)
    if ( isset( $_POST['oha_sh_export'] ) && wp_verify_nonce( $_POST['oha_sh_nonce'] ?? '', 'oha_sh_export' ) ) {
        oha_sh_export_csv();
        exit;
    }

    $s            = oha_sh_get();
    $active_year  = $s['active_year'];
    $payment_link = $s['payment_link_100'];

    // Collect all years that have at least one sponsor
    global $wpdb;
    $years = $wpdb->get_col(
        "SELECT DISTINCT pm.meta_value
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = 'sponsor_year'
           AND p.post_type = 'oha_sponsor'
           AND p.post_status = 'publish'
         ORDER BY pm.meta_value DESC"
    );
    if ( ! in_array( $active_year, $years, true ) ) {
        $years[] = $active_year;
    }
    rsort( $years );

    // Filters from URL
    $allowed_years    = array_map( 'sanitize_text_field', $years );
    $allowed_statuses = array( '', 'Invited', 'Paid', 'In-Kind', 'Confirmed' );
    $allowed_tiers    = array( '', 'convention', 'water_station' );

    $filter_year   = ( isset( $_GET['sh_year'] ) && in_array( sanitize_text_field( $_GET['sh_year'] ), $allowed_years, true ) )
                     ? sanitize_text_field( $_GET['sh_year'] ) : $active_year;
    $filter_status = ( isset( $_GET['sh_status'] ) && in_array( sanitize_text_field( $_GET['sh_status'] ), $allowed_statuses, true ) )
                     ? sanitize_text_field( $_GET['sh_status'] ) : '';
    $filter_tier   = ( isset( $_GET['sh_tier'] ) && in_array( sanitize_text_field( $_GET['sh_tier'] ), $allowed_tiers, true ) )
                     ? sanitize_text_field( $_GET['sh_tier'] ) : '';

    $meta_query = array( array( 'key' => 'sponsor_year', 'value' => $filter_year, 'compare' => '=' ) );
    if ( $filter_status ) $meta_query[] = array( 'key' => '_oha_status',  'value' => $filter_status, 'compare' => '=' );
    if ( $filter_tier )   $meta_query[] = array( 'key' => 'sponsor_tier', 'value' => $filter_tier,   'compare' => '=' );

    $query = new WP_Query( array(
        'post_type'      => 'oha_sponsor',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_query'     => $meta_query,
    ) );

    $sponsors = array();
    while ( $query->have_posts() ) {
        $query->the_post();
        $id      = get_the_ID();
        $logo_id = (int) get_post_meta( $id, '_logo_id', true );
        $logo_thumb = $logo_id ? wp_get_attachment_image_url( $logo_id, array( 48, 48 ) ) : '';
        if ( ! $logo_thumb ) $logo_thumb = get_post_meta( $id, '_logo_url', true );

        $sponsors[] = array(
            'id'                  => $id,
            'name'                => get_the_title(),
            'sponsor_type'        => get_post_meta( $id, 'sponsor_type',         true ) ?: 'company',
            'sponsor_tier'        => get_post_meta( $id, 'sponsor_tier',         true ) ?: 'convention',
            'sponsor_amount'      => get_post_meta( $id, 'sponsor_amount',       true ),
            'sponsor_year'        => get_post_meta( $id, 'sponsor_year',         true ),
            'sponsor_contribution'=> get_post_meta( $id, 'sponsor_contribution', true ),
            'sponsor_notes'       => get_post_meta( $id, 'sponsor_notes',        true ),
            '_oha_status'         => get_post_meta( $id, '_oha_status',          true ) ?: 'Invited',
            '_payment_method'     => get_post_meta( $id, '_payment_method',      true ),
            '_paid_date'          => get_post_meta( $id, '_paid_date',           true ),
            '_stripe_session_id'  => get_post_meta( $id, '_stripe_session_id',   true ),
            'contact_name'        => get_post_meta( $id, 'contact_name',         true ),
            'contact_email'       => get_post_meta( $id, 'contact_email',        true ),
            'contact_phone'       => get_post_meta( $id, 'contact_phone',        true ),
            '_logo_url'           => $logo_thumb,
            '_logo_id'            => $logo_id,
        );
    }
    wp_reset_postdata();

    // Summary counts
    $total_paid     = 0;
    $count_paid     = 0;
    $count_invited  = 0;
    $count_inkind   = 0;
    $count_confirmed = 0;
    foreach ( $sponsors as $sp ) {
        $st = $sp['_oha_status'];
        if ( $st === 'Paid' )      { $count_paid++;      $total_paid += (float) $sp['sponsor_amount']; }
        if ( $st === 'Invited' )   { $count_invited++; }
        if ( $st === 'In-Kind' )   { $count_inkind++; }
        if ( $st === 'Confirmed' ) { $count_confirmed++; $total_paid += (float) $sp['sponsor_amount']; }
    }

    $base_url = remove_query_arg( array( 'sh_year', 'sh_status', 'sh_tier' ) );

    $status_badge = array(
        'Paid'      => 'oha-sh-badge-paid',
        'Invited'   => 'oha-sh-badge-invited',
        'In-Kind'   => 'oha-sh-badge-inkind',
        'Confirmed' => 'oha-sh-badge-confirmed',
    );

    ob_start();
    ?>
<div class="oha-sh-wrap" id="oha-sponsor-hub">
<style>
.oha-sh-wrap{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;max-width:1100px;}
.oha-sh-settings-panel{background:#f9f9f9;border:1px solid #ddd;border-radius:6px;padding:16px;margin-bottom:16px;}
.oha-sh-settings-panel h4{margin:0 0 12px;font-size:14px;color:#555;cursor:pointer;user-select:none;}
.oha-sh-settings-panel h4::after{content:' \25B2';font-size:10px;}
.oha-sh-settings-panel.collapsed h4::after{content:' \25BC';}
.oha-sh-settings-body{display:grid;grid-template-columns:1fr 140px auto;gap:12px;align-items:end;}
.oha-sh-settings-body.hidden{display:none;}
.oha-sh-fl label{display:block;font-size:12px;color:#555;margin-bottom:4px;font-weight:600;}
.oha-sh-fl input,.oha-sh-fl select{width:100%;box-sizing:border-box;padding:7px 9px;border:1px solid #ccc;border-radius:4px;font-size:13px;}
.oha-sh-toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:12px;}
.oha-sh-toolbar select{font-size:13px;padding:6px 10px;border-radius:4px;border:1px solid #ccc;background:#fff;cursor:pointer;}
.oha-sh-btn{font-size:13px;padding:7px 14px;border-radius:4px;border:1px solid #ccc;background:#fff;cursor:pointer;white-space:nowrap;}
.oha-sh-btn:hover{background:#f5f5f5;}
.oha-sh-btn-primary{background:#0073aa;color:#fff;border-color:#0073aa;}
.oha-sh-btn-primary:hover{background:#005a87;}
.oha-sh-btn-success{background:#27ae60;color:#fff;border-color:#27ae60;}
.oha-sh-btn-success:hover{background:#1e8449;}
.oha-sh-btn-danger{background:#e74c3c;color:#fff;border-color:#c0392b;font-size:11px;padding:3px 8px;}
.oha-sh-btn-danger:hover{background:#c0392b;}
.oha-sh-btn-small{font-size:11px;padding:3px 8px;}
.oha-sh-summary{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:14px;}
.oha-sh-stat{background:#fff;border:1px solid #ddd;border-radius:6px;padding:10px 18px;text-align:center;min-width:80px;}
.oha-sh-stat .num{font-size:22px;font-weight:700;color:#0073aa;}
.oha-sh-stat .lbl{font-size:11px;color:#888;margin-top:2px;}
.oha-sh-stat.green .num{color:#27ae60;}
.oha-sh-stat.orange .num{color:#e67e22;}
.oha-sh-stat.grey .num{color:#7f8c8d;}
.oha-sh-table-wrap{overflow-x:auto;}
.oha-sh-table{width:100%;border-collapse:collapse;font-size:13px;}
.oha-sh-table th{background:#f5f5f5;padding:8px 10px;text-align:left;border-bottom:2px solid #ddd;white-space:nowrap;}
.oha-sh-table td{padding:8px 10px;border-bottom:1px solid #eee;vertical-align:middle;}
.oha-sh-table tr:hover td{background:#fafafa;}
.oha-sh-logo-thumb{width:36px;height:36px;object-fit:contain;border:1px solid #eee;border-radius:4px;display:block;}
.oha-sh-logo-none{width:36px;height:36px;background:#f0f0f0;border-radius:4px;border:1px solid #ddd;display:flex;align-items:center;justify-content:center;font-size:9px;color:#aaa;text-align:center;line-height:1.2;}
.oha-sh-badge{display:inline-block;padding:2px 9px;border-radius:12px;font-size:11px;font-weight:600;white-space:nowrap;}
.oha-sh-badge-paid{background:#d4edda;color:#155724;}
.oha-sh-badge-invited{background:#cce5ff;color:#004085;}
.oha-sh-badge-inkind{background:#e2e3e5;color:#383d41;}
.oha-sh-badge-confirmed{background:#d1ecf1;color:#0c5460;}
.oha-sh-actions{display:flex;gap:4px;flex-wrap:wrap;align-items:flex-start;}
/* Modal */
.oha-sh-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:100000;overflow-y:auto;}
.oha-sh-overlay.active{display:flex;align-items:flex-start;justify-content:center;padding:40px 20px;}
.oha-sh-modal{background:#fff;border-radius:8px;padding:28px;width:100%;max-width:580px;position:relative;box-shadow:0 4px 24px rgba(0,0,0,.2);}
.oha-sh-modal h3{margin:0 0 20px;font-size:17px;}
.oha-sh-modal .oha-sh-close{position:absolute;top:14px;right:18px;background:none;border:none;font-size:22px;cursor:pointer;color:#777;line-height:1;}
.oha-sh-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.oha-sh-grid .full{grid-column:1/-1;}
.oha-sh-frow label{display:block;font-size:12px;color:#555;margin-bottom:4px;font-weight:600;}
.oha-sh-frow input,.oha-sh-frow select,.oha-sh-frow textarea{width:100%;box-sizing:border-box;padding:7px 9px;border:1px solid #ccc;border-radius:4px;font-size:13px;}
.oha-sh-frow textarea{resize:vertical;min-height:58px;}
.oha-sh-modal-footer{margin-top:20px;display:flex;justify-content:flex-end;gap:8px;}
.oha-sh-logo-row{display:flex;align-items:center;gap:10px;}
.oha-sh-logo-prev{width:48px;height:48px;object-fit:contain;border:1px solid #ddd;border-radius:4px;display:none;}
/* Mark Paid panel */
.oha-sh-mp-panel{display:none;background:#f9f9f9;border:1px solid #ddd;border-radius:5px;padding:10px 12px;margin-top:5px;font-size:12px;}
.oha-sh-mp-panel select,.oha-sh-mp-panel input[type=date]{font-size:12px;padding:4px 6px;border:1px solid #ccc;border-radius:3px;margin-right:4px;}
/* Notice */
.oha-sh-notice{padding:8px 14px;border-radius:4px;font-size:13px;margin-bottom:10px;display:none;}
.oha-sh-notice.success{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
.oha-sh-notice.error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
</style>

<div class="oha-sh-notice" id="oha-sh-notice"></div>

<!-- Settings Panel -->
<div class="oha-sh-settings-panel" id="oha-sh-spanel">
    <h4 id="oha-sh-stoggle">Sponsor Hub Settings</h4>
    <div class="oha-sh-settings-body" id="oha-sh-sbody">
        <div class="oha-sh-fl">
            <label>$100 Convention Stripe Payment Link URL</label>
            <input type="url" id="oha-sh-pl" value="<?php echo esc_attr( $payment_link ); ?>" placeholder="https://buy.stripe.com/..." />
        </div>
        <div class="oha-sh-fl">
            <label>Active Convention Year</label>
            <input type="text" id="oha-sh-yr" value="<?php echo esc_attr( $active_year ); ?>" maxlength="4" />
        </div>
        <div>
            <button class="oha-sh-btn oha-sh-btn-primary" onclick="ohaSHSaveSettings()">Save Settings</button>
        </div>
    </div>
</div>

<!-- Summary Stats -->
<div class="oha-sh-summary">
    <div class="oha-sh-stat green">
        <div class="num"><?php echo $count_paid + $count_confirmed; ?></div>
        <div class="lbl">Paid / Confirmed</div>
    </div>
    <div class="oha-sh-stat orange">
        <div class="num"><?php echo $count_invited; ?></div>
        <div class="lbl">Invited</div>
    </div>
    <div class="oha-sh-stat grey">
        <div class="num"><?php echo $count_inkind; ?></div>
        <div class="lbl">In-Kind</div>
    </div>
    <div class="oha-sh-stat">
        <div class="num">$<?php echo number_format( $total_paid, 0 ); ?></div>
        <div class="lbl">Total Received</div>
    </div>
    <div class="oha-sh-stat">
        <div class="num"><?php echo count( $sponsors ); ?></div>
        <div class="lbl">Showing</div>
    </div>
</div>

<!-- Toolbar -->
<div class="oha-sh-toolbar">
    <button class="oha-sh-btn oha-sh-btn-primary" onclick="ohaSHOpenModal(null)">+ Add Sponsor</button>

    <form method="post" style="display:contents;">
        <?php wp_nonce_field( 'oha_sh_export', 'oha_sh_nonce' ); ?>
        <input type="hidden" name="sh_year"   value="<?php echo esc_attr( $filter_year ); ?>" />
        <input type="hidden" name="sh_status" value="<?php echo esc_attr( $filter_status ); ?>" />
        <input type="hidden" name="sh_tier"   value="<?php echo esc_attr( $filter_tier ); ?>" />
        <button type="submit" name="oha_sh_export" value="1" class="oha-sh-btn">&#8659; Export CSV</button>
    </form>

    <select onchange="location.href='<?php echo esc_js( esc_url( add_query_arg( array( 'sh_status' => $filter_status, 'sh_tier' => $filter_tier ), $base_url ) ) ); ?>&sh_year='+this.value">
        <?php foreach ( $years as $y ) : ?>
            <option value="<?php echo esc_attr( $y ); ?>" <?php selected( $filter_year, $y ); ?>><?php echo esc_html( $y ); ?></option>
        <?php endforeach; ?>
    </select>

    <select onchange="location.href='<?php echo esc_js( esc_url( add_query_arg( array( 'sh_year' => $filter_year, 'sh_tier' => $filter_tier ), $base_url ) ) ); ?>&sh_status='+this.value">
        <option value="">All Statuses</option>
        <?php foreach ( array( 'Invited', 'Paid', 'In-Kind', 'Confirmed' ) as $st ) : ?>
            <option value="<?php echo esc_attr( $st ); ?>" <?php selected( $filter_status, $st ); ?>><?php echo esc_html( $st ); ?></option>
        <?php endforeach; ?>
    </select>

    <select onchange="location.href='<?php echo esc_js( esc_url( add_query_arg( array( 'sh_year' => $filter_year, 'sh_status' => $filter_status ), $base_url ) ) ); ?>&sh_tier='+this.value">
        <option value="">All Tiers</option>
        <option value="convention"   <?php selected( $filter_tier, 'convention' ); ?>>Convention ($100)</option>
        <option value="water_station"<?php selected( $filter_tier, 'water_station' ); ?>>Water Station</option>
    </select>
</div>

<!-- Table -->
<div class="oha-sh-table-wrap">
<?php if ( empty( $sponsors ) ) : ?>
    <p style="color:#888;font-style:italic;">No sponsors found for <?php echo esc_html( $filter_year ); ?>. Use &ldquo;+ Add Sponsor&rdquo; to add one.</p>
<?php else : ?>
<table class="oha-sh-table">
    <thead>
        <tr>
            <th>Logo</th>
            <th>Name</th>
            <th>Type</th>
            <th>Tier</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Paid Date</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ( $sponsors as $sp ) :
        $badge   = isset( $status_badge[ $sp['_oha_status'] ] ) ? $status_badge[ $sp['_oha_status'] ] : 'oha-sh-badge-invited';
        $tier_lbl = $sp['sponsor_tier'] === 'water_station' ? 'Water Station' : 'Convention';
        $type_lbl = ucfirst( $sp['sponsor_type'] );
        $amt      = (float) $sp['sponsor_amount'];
        if ( $amt > 0 ) {
            $amt_disp = '$' . number_format( $amt, $amt == floor( $amt ) ? 0 : 2 );
        } elseif ( $sp['_oha_status'] === 'In-Kind' ) {
            $amt_disp = 'In-Kind';
        } else {
            $amt_disp = '&mdash;';
        }
        $pd_disp   = $sp['_paid_date'] ? date( 'M j, Y', strtotime( $sp['_paid_date'] ) ) : '&mdash;';
        $sp_json   = esc_attr( wp_json_encode( $sp ) );
        $show_link = ( $sp['sponsor_tier'] === 'convention' && $payment_link );
        $show_mp   = ! in_array( $sp['_oha_status'], array( 'Paid', 'Confirmed', 'In-Kind' ), true );
    ?>
    <tr id="oha-sh-row-<?php echo $sp['id']; ?>">
        <td>
            <?php if ( $sp['_logo_url'] ) : ?>
                <img src="<?php echo esc_url( $sp['_logo_url'] ); ?>" alt="" class="oha-sh-logo-thumb" />
            <?php else : ?>
                <div class="oha-sh-logo-none">no<br>logo</div>
            <?php endif; ?>
        </td>
        <td>
            <strong><?php echo esc_html( $sp['name'] ); ?></strong>
            <?php if ( $sp['contact_name'] ) : ?>
                <br><small style="color:#888;"><?php echo esc_html( $sp['contact_name'] ); ?></small>
            <?php endif; ?>
        </td>
        <td><?php echo esc_html( $type_lbl ); ?></td>
        <td><?php echo esc_html( $tier_lbl ); ?></td>
        <td><?php echo $amt_disp; ?></td>
        <td><span class="oha-sh-badge <?php echo $badge; ?>"><?php echo esc_html( $sp['_oha_status'] ); ?></span></td>
        <td><?php echo $pd_disp; ?></td>
        <td>
            <div class="oha-sh-actions">
                <button class="oha-sh-btn oha-sh-btn-small" onclick="ohaSHOpenModal(<?php echo $sp_json; ?>)">Edit</button>
                <?php if ( $show_link ) : ?>
                <button class="oha-sh-btn oha-sh-btn-small oha-sh-btn-success" onclick="ohaSHCopyLink(<?php echo $sp['id']; ?>,this)" title="Copy Stripe payment link with this sponsor's ID">Copy Link</button>
                <?php endif; ?>
                <?php if ( $show_mp ) : ?>
                <button class="oha-sh-btn oha-sh-btn-small" onclick="ohaSHToggleMP(<?php echo $sp['id']; ?>)">Mark Paid</button>
                <?php endif; ?>
                <button class="oha-sh-btn oha-sh-btn-danger" onclick="ohaSHDelete(<?php echo $sp['id']; ?>,'<?php echo esc_js( $sp['name'] ); ?>')">Del</button>
            </div>
            <div class="oha-sh-mp-panel" id="oha-sh-mp-<?php echo $sp['id']; ?>">
                Method:
                <select id="oha-sh-mpm-<?php echo $sp['id']; ?>">
                    <option value="cash">Cash</option>
                    <option value="check">Check</option>
                    <option value="stripe">Stripe</option>
                    <option value="waived">Waived</option>
                </select>
                Date: <input type="date" id="oha-sh-mpd-<?php echo $sp['id']; ?>" value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>" />
                <button class="oha-sh-btn oha-sh-btn-small oha-sh-btn-primary" onclick="ohaSHConfirmPaid(<?php echo $sp['id']; ?>)">Confirm</button>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
</div><!-- .oha-sh-table-wrap -->

<!-- Add / Edit Modal -->
<div class="oha-sh-overlay" id="oha-sh-overlay">
<div class="oha-sh-modal">
    <button class="oha-sh-close" onclick="ohaSHCloseModal()">&times;</button>
    <h3 id="oha-sh-mtitle">Add Sponsor</h3>
    <input type="hidden" id="oha-sh-eid" value="" />
    <div class="oha-sh-grid">
        <div class="oha-sh-frow full">
            <label>Display Name *</label>
            <input type="text" id="oha-sh-f-name" placeholder="Name shown on website and sponsor graphics" />
        </div>
        <div class="oha-sh-frow">
            <label>Sponsor Type</label>
            <select id="oha-sh-f-type">
                <option value="company">Company</option>
                <option value="individual">Individual</option>
            </select>
        </div>
        <div class="oha-sh-frow">
            <label>Tier</label>
            <select id="oha-sh-f-tier" onchange="ohaSHTierChange(this)">
                <option value="convention">Convention Sponsor ($100)</option>
                <option value="water_station">Water Station (variable)</option>
            </select>
        </div>
        <div class="oha-sh-frow">
            <label>Amount ($)</label>
            <input type="number" id="oha-sh-f-amount" value="100" min="0" step="0.01" />
        </div>
        <div class="oha-sh-frow">
            <label>Year</label>
            <input type="text" id="oha-sh-f-year" value="<?php echo esc_attr( $active_year ); ?>" maxlength="4" />
        </div>
        <div class="oha-sh-frow">
            <label>Status</label>
            <select id="oha-sh-f-status">
                <option value="Invited">Invited</option>
                <option value="Paid">Paid</option>
                <option value="In-Kind">In-Kind</option>
                <option value="Confirmed">Confirmed</option>
            </select>
        </div>
        <div class="oha-sh-frow">
            <label>Payment Method</label>
            <select id="oha-sh-f-method">
                <option value="stripe">Stripe</option>
                <option value="cash">Cash</option>
                <option value="check">Check</option>
                <option value="in-kind">In-Kind</option>
                <option value="waived">Waived</option>
            </select>
        </div>
        <div class="oha-sh-frow">
            <label>Paid / Confirmed Date</label>
            <input type="date" id="oha-sh-f-pdate" />
        </div>
        <div class="oha-sh-frow">
            <label>Contact Name</label>
            <input type="text" id="oha-sh-f-cname" />
        </div>
        <div class="oha-sh-frow">
            <label>Contact Email</label>
            <input type="email" id="oha-sh-f-cemail" />
        </div>
        <div class="oha-sh-frow full">
            <label>Contact Phone</label>
            <input type="text" id="oha-sh-f-cphone" />
        </div>
        <div class="oha-sh-frow full">
            <label>Contribution / Description</label>
            <textarea id="oha-sh-f-contrib" placeholder="What they are providing (for in-kind, water station, or any extra details)"></textarea>
        </div>
        <div class="oha-sh-frow full">
            <label>Logo (for graphics team)</label>
            <div class="oha-sh-logo-row">
                <img src="" alt="" class="oha-sh-logo-prev" id="oha-sh-lprev" />
                <button type="button" class="oha-sh-btn" onclick="ohaSHMedia()">Upload / Select Logo</button>
                <button type="button" class="oha-sh-btn" onclick="ohaSHRmLogo()" id="oha-sh-rml" style="display:none;">Remove</button>
            </div>
            <input type="hidden" id="oha-sh-f-lurl" />
            <input type="hidden" id="oha-sh-f-lid" />
        </div>
        <div class="oha-sh-frow full">
            <label>Internal Notes</label>
            <textarea id="oha-sh-f-notes" placeholder="Board-only notes, not shown publicly"></textarea>
        </div>
    </div>
    <div class="oha-sh-modal-footer">
        <button class="oha-sh-btn" onclick="ohaSHCloseModal()">Cancel</button>
        <button class="oha-sh-btn oha-sh-btn-primary" onclick="ohaSHSave()">Save Sponsor</button>
    </div>
</div>
</div><!-- .oha-sh-overlay -->

<script>
(function(){
    var REST  = (typeof ohaAdminData !== 'undefined' ? ohaAdminData.restUrl : '<?php echo esc_js( rest_url( 'oha/v1/' ) ); ?>');
    var NONCE = (typeof ohaAdminData !== 'undefined' ? ohaAdminData.nonce  : '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>');
    var PL100 = '<?php echo esc_js( $payment_link ); ?>';
    var mediaFrame = null;

    function api(ep, data, cb) {
        fetch(REST + ep, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-WP-Nonce': NONCE},
            body: JSON.stringify(data)
        }).then(function(r){ return r.json(); }).then(cb).catch(function(e){
            notice('Request failed: ' + e.message, 'error');
        });
    }

    function notice(msg, type) {
        var el = document.getElementById('oha-sh-notice');
        el.textContent = msg;
        el.className = 'oha-sh-notice ' + (type || 'success');
        el.style.display = 'block';
        setTimeout(function(){ el.style.display = 'none'; }, 4500);
    }

    function fv(id) { return document.getElementById(id) ? document.getElementById(id).value : ''; }
    function fs(id, v) { if (document.getElementById(id)) document.getElementById(id).value = v; }

    window.ohaSHOpenModal = function(sp) {
        document.getElementById('oha-sh-mtitle').textContent = sp ? 'Edit Sponsor' : 'Add Sponsor';
        fs('oha-sh-eid',      sp ? sp.id       : '');
        fs('oha-sh-f-name',   sp ? sp.name     : '');
        fs('oha-sh-f-type',   sp ? (sp.sponsor_type   || 'company')    : 'company');
        fs('oha-sh-f-tier',   sp ? (sp.sponsor_tier   || 'convention') : 'convention');
        fs('oha-sh-f-amount', sp ? (sp.sponsor_amount || (sp && sp.sponsor_tier === 'convention' ? '100' : '')) : '100');
        fs('oha-sh-f-year',   sp ? (sp.sponsor_year   || '<?php echo esc_js( $active_year ); ?>') : '<?php echo esc_js( $active_year ); ?>');
        fs('oha-sh-f-status', sp ? (sp._oha_status    || 'Invited')    : 'Invited');
        fs('oha-sh-f-method', sp ? (sp._payment_method || 'stripe')    : 'stripe');
        fs('oha-sh-f-pdate',  sp && sp._paid_date ? sp._paid_date.substring(0,10) : '');
        fs('oha-sh-f-cname',  sp ? (sp.contact_name  || '') : '');
        fs('oha-sh-f-cemail', sp ? (sp.contact_email || '') : '');
        fs('oha-sh-f-cphone', sp ? (sp.contact_phone || '') : '');
        fs('oha-sh-f-contrib',sp ? (sp.sponsor_contribution || '') : '');
        fs('oha-sh-f-notes',  sp ? (sp.sponsor_notes || '') : '');
        fs('oha-sh-f-lurl',   sp ? (sp._logo_url || '') : '');
        fs('oha-sh-f-lid',    sp ? (sp._logo_id  || '') : '');
        var prev = document.getElementById('oha-sh-lprev');
        var rml  = document.getElementById('oha-sh-rml');
        if (sp && sp._logo_url) {
            prev.src = sp._logo_url; prev.style.display = 'block'; rml.style.display = '';
        } else {
            prev.src = ''; prev.style.display = 'none'; rml.style.display = 'none';
        }
        document.getElementById('oha-sh-overlay').classList.add('active');
    };

    window.ohaSHCloseModal = function() {
        document.getElementById('oha-sh-overlay').classList.remove('active');
    };

    window.ohaSHTierChange = function(sel) {
        if (sel.value === 'convention') {
            fs('oha-sh-f-amount', '100');
        } else {
            fs('oha-sh-f-amount', '');
        }
    };

    window.ohaSHSave = function() {
        var name = fv('oha-sh-f-name').trim();
        if (!name) { notice('Display name is required.', 'error'); return; }
        api('sponsors/save', {
            id:                   fv('oha-sh-eid') || null,
            name:                 name,
            sponsor_type:         fv('oha-sh-f-type'),
            sponsor_tier:         fv('oha-sh-f-tier'),
            sponsor_amount:       fv('oha-sh-f-amount'),
            sponsor_year:         fv('oha-sh-f-year'),
            _oha_status:          fv('oha-sh-f-status'),
            _payment_method:      fv('oha-sh-f-method'),
            _paid_date:           fv('oha-sh-f-pdate'),
            contact_name:         fv('oha-sh-f-cname'),
            contact_email:        fv('oha-sh-f-cemail'),
            contact_phone:        fv('oha-sh-f-cphone'),
            sponsor_contribution: fv('oha-sh-f-contrib'),
            sponsor_notes:        fv('oha-sh-f-notes'),
            _logo_url:            fv('oha-sh-f-lurl'),
            _logo_id:             fv('oha-sh-f-lid'),
        }, function(resp) {
            if (resp.ok) {
                ohaSHCloseModal();
                notice('Sponsor saved.');
                setTimeout(function(){ location.reload(); }, 900);
            } else {
                notice(resp.message || 'Save failed.', 'error');
            }
        });
    };

    window.ohaSHDelete = function(id, name) {
        if (!confirm('Delete sponsor "' + name + '"? This cannot be undone.')) return;
        api('sponsors/delete', {id: id}, function(resp) {
            if (resp.ok) {
                var row = document.getElementById('oha-sh-row-' + id);
                if (row) row.remove();
                notice('Sponsor deleted.');
            } else {
                notice('Delete failed.', 'error');
            }
        });
    };

    window.ohaSHCopyLink = function(id, btn) {
        if (!PL100) { notice('Payment link not configured. Add it in Settings above.', 'error'); return; }
        var sep  = PL100.indexOf('?') >= 0 ? '&' : '?';
        var full = PL100 + sep + 'client_reference_id=' + id;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(full).then(function() {
                var orig = btn.textContent;
                btn.textContent = 'Copied!';
                setTimeout(function(){ btn.textContent = orig; }, 1800);
            });
        } else {
            prompt('Copy this payment link:', full);
        }
    };

    window.ohaSHToggleMP = function(id) {
        var p = document.getElementById('oha-sh-mp-' + id);
        if (p) p.style.display = p.style.display === 'block' ? 'none' : 'block';
    };

    window.ohaSHConfirmPaid = function(id) {
        var method = document.getElementById('oha-sh-mpm-' + id) ? document.getElementById('oha-sh-mpm-' + id).value : 'cash';
        var pdate  = document.getElementById('oha-sh-mpd-' + id) ? document.getElementById('oha-sh-mpd-' + id).value : '';
        api('sponsors/update-status', {id: id, status: 'Paid', method: method, paid_date: pdate}, function(resp) {
            if (resp.ok) {
                notice('Status updated to Paid.');
                setTimeout(function(){ location.reload(); }, 900);
            } else {
                notice('Update failed.', 'error');
            }
        });
    };

    window.ohaSHSaveSettings = function() {
        var pl = document.getElementById('oha-sh-pl') ? document.getElementById('oha-sh-pl').value.trim() : '';
        var yr = document.getElementById('oha-sh-yr') ? document.getElementById('oha-sh-yr').value.trim() : '';
        api('sponsors/settings', {payment_link_100: pl, active_year: yr}, function(resp) {
            if (resp.ok) { PL100 = pl; notice('Settings saved.'); }
            else { notice('Settings save failed.', 'error'); }
        });
    };

    // WP Media Uploader
    window.ohaSHMedia = function() {
        if (typeof wp === 'undefined' || !wp.media) {
            notice('Media uploader not available.', 'error'); return;
        }
        if (!mediaFrame) {
            mediaFrame = wp.media({title:'Select Sponsor Logo', button:{text:'Use this logo'}, multiple:false, library:{type:'image'}});
            mediaFrame.on('select', function() {
                var att = mediaFrame.state().get('selection').first().toJSON();
                fs('oha-sh-f-lid',  att.id);
                fs('oha-sh-f-lurl', att.url);
                var prev = document.getElementById('oha-sh-lprev');
                prev.src = att.url; prev.style.display = 'block';
                document.getElementById('oha-sh-rml').style.display = '';
            });
        }
        mediaFrame.open();
    };

    window.ohaSHRmLogo = function() {
        fs('oha-sh-f-lid',  '');
        fs('oha-sh-f-lurl', '');
        var prev = document.getElementById('oha-sh-lprev');
        prev.src = ''; prev.style.display = 'none';
        document.getElementById('oha-sh-rml').style.display = 'none';
    };

    // Settings panel toggle
    document.getElementById('oha-sh-stoggle').addEventListener('click', function() {
        document.getElementById('oha-sh-spanel').classList.toggle('collapsed');
        document.getElementById('oha-sh-sbody').classList.toggle('hidden');
    });

    // Close modal on overlay click
    document.getElementById('oha-sh-overlay').addEventListener('click', function(e) {
        if (e.target === this) ohaSHCloseModal();
    });

})();
</script>
</div><!-- .oha-sh-wrap -->
    <?php
    return ob_get_clean();
} );
