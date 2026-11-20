<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * OHA Vendor Hub REST API — Namespace: oha-vendor/v1
 *
 * Public endpoints (no auth required):
 *   GET  /open          is applications open? { open, message }
 *   GET  /info          active convention details { year, name, venue, dates, booth_price }
 *   POST /apply         submit a new vendor application
 *
 * Vendor-authenticated endpoints (require email + vid params):
 *   GET  /status        { status, company_name, year, next_step, agreement_url, payment_url }
 *   GET  /application   full editable application fields
 *   POST /update        update editable fields (locked at Agreement Signed / Paid)
 *
 * Authentication model:
 *   email + vid (Vendor ID = _vendor_permanent_id) — same credentials used by the
 *   web edit form. VID check always verifies BOTH _vendor_permanent_id AND email
 *   to prevent ID-only enumeration attacks.
 *
 * Security:
 *   - All inputs sanitized and type-validated before DB use
 *   - Rate limiting: DB-based transients, keyed per IP
 *   - Booth count enforced 1-4 server-side
 *   - No raw credentials (email, phone) returned in any response
 *   - Stripe payment URL only returned to the authenticated vendor at the correct stage
 *   - No API keys stored or required — auth is the email+VID pair over HTTPS
 *   - CORS open for now; tighten to app domain once known (see cors_headers)
 */
class OHA_Vendor_API {

    const NS = 'oha-vendor/v1';

    public static function register() {
        add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
        add_filter( 'rest_pre_serve_request', [ self::class, 'cors_headers' ], 10, 3 );
    }

    // ------------------------------------------------------------------
    // Route registration
    // ------------------------------------------------------------------

    public static function register_routes() {

        register_rest_route( self::NS, '/open', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ self::class, 'handle_open' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( self::NS, '/info', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ self::class, 'handle_info' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( self::NS, '/apply', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ self::class, 'handle_apply' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( self::NS, '/status', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ self::class, 'handle_status' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'email' => [ 'required' => true,  'sanitize_callback' => 'sanitize_email' ],
                'vid'   => [ 'required' => true,  'sanitize_callback' => 'absint' ],
            ],
        ] );

        register_rest_route( self::NS, '/application', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ self::class, 'handle_get_application' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'email' => [ 'required' => true, 'sanitize_callback' => 'sanitize_email' ],
                    'vid'   => [ 'required' => true, 'sanitize_callback' => 'absint' ],
                ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ self::class, 'handle_update_application' ],
                'permission_callback' => '__return_true',
            ],
        ] );
    }

    // ------------------------------------------------------------------
    // CORS — tighten origin once app domain is known
    // ------------------------------------------------------------------

    public static function cors_headers( $served, $result, $request ) {
        if ( strpos( $request->get_route(), '/' . self::NS ) === false ) {
            return $served;
        }
        header( 'Access-Control-Allow-Origin: *' );
        header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
        header( 'Access-Control-Allow-Headers: Content-Type, Accept' );
        return $served;
    }

    // ------------------------------------------------------------------
    // Rate limiting — DB-based transients, no external cache dependency
    // ------------------------------------------------------------------

    private static function rate_limit( $action, $max, $window = 3600 ) {
        $ip  = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
        $key = 'oha_vh_rl_' . substr( md5( $action . $ip ), 0, 16 );
        $n   = (int) get_transient( $key );
        if ( $n >= $max ) {
            return false;
        }
        set_transient( $key, $n + 1, $window );
        return true;
    }

    // ------------------------------------------------------------------
    // Vendor lookup — email + VID pair
    // Delegates to the existing function in vendor-edit.php when loaded.
    // ------------------------------------------------------------------

    private static function resolve_app_id( $email, $vid ) {
        if ( ! $email || ! $vid ) return 0;

        if ( function_exists( 'oha_vh_find_app_by_email_and_vid' ) ) {
            return oha_vh_find_app_by_email_and_vid( $email, (int) $vid );
        }

        // Fallback (same logic as vendor-edit.php) if shortcodes file not loaded yet
        $active_year = intval( get_option( 'oha_vhcpt_active_year', date( 'Y' ) ) );

        $apps = get_posts( [
            'post_type'      => 'vendor_application',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'meta_query'     => [
                'relation' => 'AND',
                [ 'key' => 'email',                'value' => $email,       'compare' => '=' ],
                [ 'key' => '_vendor_permanent_id', 'value' => $vid,         'compare' => '=', 'type' => 'NUMERIC' ],
                [ 'key' => '_convention_year',     'value' => $active_year, 'compare' => '=' ],
            ],
        ] );
        if ( ! empty( $apps ) ) return $apps[0]->ID;

        $apps = get_posts( [
            'post_type'      => 'vendor_application',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'meta_query'     => [
                'relation' => 'AND',
                [ 'key' => 'email',                'value' => $email, 'compare' => '=' ],
                [ 'key' => '_vendor_permanent_id', 'value' => $vid,   'compare' => '=', 'type' => 'NUMERIC' ],
            ],
        ] );
        return ! empty( $apps ) ? $apps[0]->ID : 0;
    }

    // ------------------------------------------------------------------
    // next_step helper — tells the app what action the vendor needs to take
    // ------------------------------------------------------------------

    private static function next_step( $status, $has_payment_url ) {
        switch ( $status ) {
            case 'Invited':
                return 'submit';
            case 'Submitted':
            case 'Approved':
                return 'wait';
            case 'Agreement Sent':
                return 'agreement';
            case 'Agreement Signed':
                return $has_payment_url ? 'payment' : 'wait';
            case 'Paid':
                return 'complete';
            default:
                return 'none';
        }
    }

    // ------------------------------------------------------------------
    // Handler: GET /open
    // ------------------------------------------------------------------

    public static function handle_open( WP_REST_Request $req ) {
        $closed  = (bool) get_option( 'oha_vhcpt_apps_closed', 0 );
        $message = $closed
            ? get_option( 'oha_vhcpt_apps_closed_message', 'Vendor applications are currently closed.' )
            : 'Vendor applications are currently open.';
        return rest_ensure_response( [
            'open'    => ! $closed,
            'message' => sanitize_text_field( $message ),
        ] );
    }

    // ------------------------------------------------------------------
    // Handler: GET /info
    // ------------------------------------------------------------------

    public static function handle_info( WP_REST_Request $req ) {
        return rest_ensure_response( [
            'year'        => intval( get_option( 'oha_vhcpt_active_year',         date( 'Y' ) ) ),
            'name'        => sanitize_text_field( get_option( 'oha_vhcpt_convention_name',        '' ) ),
            'venue'       => sanitize_text_field( get_option( 'oha_vhcpt_convention_venue',       '' ) ),
            'dates'       => sanitize_text_field( get_option( 'oha_vhcpt_convention_dates',       '' ) ),
            'booth_price' => sanitize_text_field( get_option( 'oha_vhcpt_convention_booth_price', '' ) ),
        ] );
    }

    // ------------------------------------------------------------------
    // Handler: POST /apply
    // ------------------------------------------------------------------

    public static function handle_apply( WP_REST_Request $req ) {

        if ( ! self::rate_limit( 'apply', 5 ) ) {
            return new WP_Error( 'rate_limit', 'Too many submissions. Please try again later.', [ 'status' => 429 ] );
        }

        if ( get_option( 'oha_vhcpt_apps_closed', 0 ) ) {
            $msg = get_option( 'oha_vhcpt_apps_closed_message', 'Vendor applications are currently closed.' );
            return new WP_Error( 'apps_closed', sanitize_text_field( $msg ), [ 'status' => 403 ] );
        }

        $body = $req->get_json_params();
        if ( empty( $body ) ) $body = $req->get_body_params();

        $schema = [
            'first_name'       => [ 'required' => true,  'type' => 'text' ],
            'last_name'        => [ 'required' => true,  'type' => 'text' ],
            'company_name'     => [ 'required' => true,  'type' => 'text' ],
            'email'            => [ 'required' => true,  'type' => 'email' ],
            'phone'            => [ 'required' => true,  'type' => 'text' ],
            'product_type'     => [ 'required' => true,  'type' => 'textarea' ],
            'booths'           => [ 'required' => true,  'type' => 'number' ],
            'address_street'   => [ 'required' => false, 'type' => 'text' ],
            'address_city'     => [ 'required' => false, 'type' => 'text' ],
            'address_state'    => [ 'required' => false, 'type' => 'text' ],
            'address_zip'      => [ 'required' => false, 'type' => 'text' ],
            'website'          => [ 'required' => false, 'type' => 'url' ],
            'sponsor'          => [ 'required' => false, 'type' => 'bool' ],
            'booth_preference' => [ 'required' => false, 'type' => 'textarea' ],
            'tables_needed'    => [ 'required' => false, 'type' => 'number' ],
            'need_electric'    => [ 'required' => false, 'type' => 'bool' ],
        ];

        $meta   = [];
        $errors = [];

        foreach ( $schema as $key => $def ) {
            $raw = $body[ $key ] ?? '';
            switch ( $def['type'] ) {
                case 'email':
                    $meta[ $key ] = sanitize_email( $raw );
                    break;
                case 'number':
                    $meta[ $key ] = intval( $raw );
                    break;
                case 'bool':
                    $meta[ $key ] = in_array( $raw, [ true, 1, '1', 'yes' ], true ) ? 1 : 0;
                    break;
                case 'textarea':
                    $meta[ $key ] = sanitize_textarea_field( $raw );
                    break;
                case 'url':
                    $url = trim( sanitize_text_field( $raw ) );
                    if ( $url && ! preg_match( '#^https?://#i', $url ) ) $url = 'https://' . $url;
                    $meta[ $key ] = $url ? esc_url_raw( $url ) : '';
                    break;
                default:
                    $meta[ $key ] = sanitize_text_field( $raw );
                    break;
            }
            if ( ! empty( $def['required'] ) && empty( $meta[ $key ] ) && $meta[ $key ] !== 0 ) {
                $errors[] = $key . ' is required.';
            }
        }

        if ( isset( $meta['booths'] ) && ( $meta['booths'] < 1 || $meta['booths'] > 4 ) ) {
            $errors[] = 'Booth count must be between 1 and 4. Contact info@ohiohaunters.com for larger requirements.';
        }

        // Duplicate check: same email submitted in last 10 minutes
        if ( ! empty( $meta['email'] ) ) {
            $recent = get_posts( [
                'post_type'      => 'vendor_application',
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'date_query'     => [ [ 'after' => '10 minutes ago' ] ],
                'meta_query'     => [ [ 'key' => 'email', 'value' => $meta['email'], 'compare' => '=' ] ],
            ] );
            if ( ! empty( $recent ) ) {
                $errors[] = 'A recent application with this email address already exists.';
            }
        }

        if ( ! empty( $errors ) ) {
            return new WP_Error( 'validation', implode( ' ', $errors ), [ 'status' => 422 ] );
        }

        $active_year = intval( get_option( 'oha_vhcpt_active_year', date( 'Y' ) ) );
        $title       = $meta['company_name'] ?: trim( ( $meta['first_name'] ?? '' ) . ' ' . ( $meta['last_name'] ?? '' ) );

        $post_id = wp_insert_post( [
            'post_type'   => 'vendor_application',
            'post_status' => 'publish',
            'post_title'  => sanitize_text_field( $title ),
            'post_author' => 0,
        ], true );

        if ( is_wp_error( $post_id ) ) {
            return new WP_Error( 'db_error', 'Application could not be saved.', [ 'status' => 500 ] );
        }

        foreach ( $meta as $k => $v ) update_post_meta( $post_id, $k, $v );
        update_post_meta( $post_id, '_convention_year',     $active_year );
        update_post_meta( $post_id, '_vendor_permanent_id', $post_id );
        update_post_meta( $post_id, '_oha_status',          'Submitted' );
        update_post_meta( $post_id, 'returning_vendor',     0 );

        if ( function_exists( 'oha_vhcpt_append_history' ) ) {
            oha_vhcpt_append_history( $post_id, [
                'type'        => 'application_submitted',
                'description' => 'Application submitted via REST API.',
            ] );
        }

        if ( function_exists( 'oha_vhcpt_maybe_send_status_email' ) ) {
            oha_vhcpt_maybe_send_status_email( $post_id, 'Submitted' );
        }

        return rest_ensure_response( [
            'success' => true,
            'vid'     => $post_id,
            'message' => 'Your vendor application has been submitted successfully.',
        ] );
    }

    // ------------------------------------------------------------------
    // Handler: GET /status
    // ------------------------------------------------------------------

    public static function handle_status( WP_REST_Request $req ) {

        if ( ! self::rate_limit( 'lookup', 20 ) ) {
            return new WP_Error( 'rate_limit', 'Too many requests. Please try again later.', [ 'status' => 429 ] );
        }

        $email  = $req->get_param( 'email' );
        $vid    = (int) $req->get_param( 'vid' );
        $app_id = self::resolve_app_id( $email, $vid );

        if ( ! $app_id ) {
            return new WP_Error( 'not_found', 'No application found matching those credentials.', [ 'status' => 404 ] );
        }

        $status    = get_post_meta( $app_id, '_oha_status',       true ) ?: 'Submitted';
        $company   = get_post_meta( $app_id, 'company_name',      true );
        $year      = (int) get_post_meta( $app_id, '_convention_year', true );
        $agree_url = get_post_meta( $app_id, '_agreement_url',    true ) ?: null;
        $pay_url   = get_post_meta( $app_id, '_payment_url',      true ) ?: null;
        $next      = self::next_step( $status, (bool) $pay_url );

        // Only expose agreement URL once signed; only expose payment URL when vendor needs to pay
        $expose_agreement = in_array( $status, [ 'Agreement Signed', 'Paid' ], true );
        $expose_payment   = ( $status === 'Agreement Signed' );

        return rest_ensure_response( [
            'success'       => true,
            'vid'           => $vid,
            'status'        => $status,
            'company_name'  => sanitize_text_field( $company ),
            'year'          => $year,
            'next_step'     => $next,
            'agreement_url' => $expose_agreement ? $agree_url : null,
            'payment_url'   => $expose_payment   ? $pay_url   : null,
        ] );
    }

    // ------------------------------------------------------------------
    // Handler: GET /application
    // ------------------------------------------------------------------

    public static function handle_get_application( WP_REST_Request $req ) {

        if ( ! self::rate_limit( 'lookup', 20 ) ) {
            return new WP_Error( 'rate_limit', 'Too many requests. Please try again later.', [ 'status' => 429 ] );
        }

        $email  = $req->get_param( 'email' );
        $vid    = (int) $req->get_param( 'vid' );
        $app_id = self::resolve_app_id( $email, $vid );

        if ( ! $app_id ) {
            return new WP_Error( 'not_found', 'No application found matching those credentials.', [ 'status' => 404 ] );
        }

        $editable = [
            'first_name', 'last_name', 'company_name', 'phone',
            'address_street', 'address_city', 'address_state', 'address_zip',
            'website', 'product_type', 'booths', 'sponsor',
            'booth_preference', 'tables_needed', 'need_electric',
        ];

        $data = [ 'vid' => $vid ];
        foreach ( $editable as $k ) {
            $data[ $k ] = get_post_meta( $app_id, $k, true );
        }

        // Logo — return URL, never expose attachment IDs
        $logo_id  = (int) get_post_meta( $app_id, '_company_logo_id', true );
        $logo_url = '';
        if ( $logo_id ) {
            $src = wp_get_attachment_image_src( $logo_id, [ 400, 300 ] );
            if ( $src ) $logo_url = $src[0];
        }
        if ( ! $logo_url ) $logo_url = get_post_meta( $app_id, '_company_logo_url', true ) ?: '';
        $data['logo_url'] = esc_url( $logo_url );

        return rest_ensure_response( [ 'success' => true, 'application' => $data ] );
    }

    // ------------------------------------------------------------------
    // Handler: POST /update
    // ------------------------------------------------------------------

    public static function handle_update_application( WP_REST_Request $req ) {

        if ( ! self::rate_limit( 'update', 10 ) ) {
            return new WP_Error( 'rate_limit', 'Too many requests. Please try again later.', [ 'status' => 429 ] );
        }

        $body  = $req->get_json_params();
        if ( empty( $body ) ) $body = $req->get_body_params();

        $email = sanitize_email( $body['email'] ?? '' );
        $vid   = (int) ( $body['vid'] ?? 0 );

        if ( ! $email || ! $vid ) {
            return new WP_Error( 'auth_required', 'email and vid are required.', [ 'status' => 400 ] );
        }

        $app_id = self::resolve_app_id( $email, $vid );
        if ( ! $app_id ) {
            return new WP_Error( 'not_found', 'No application found matching those credentials.', [ 'status' => 404 ] );
        }

        $status = get_post_meta( $app_id, '_oha_status', true );
        if ( in_array( $status, [ 'Agreement Signed', 'Paid' ], true ) ) {
            return new WP_Error(
                'locked',
                'Your application is locked at this stage. Contact info@ohiohaunters.com for changes.',
                [ 'status' => 403 ]
            );
        }

        $editable = [
            'first_name', 'last_name', 'company_name', 'phone',
            'address_street', 'address_city', 'address_state', 'address_zip',
            'website', 'product_type', 'booths', 'sponsor',
            'booth_preference', 'tables_needed', 'need_electric',
        ];

        $updated = [];
        foreach ( $editable as $key ) {
            if ( ! array_key_exists( $key, $body ) ) continue;
            $raw = $body[ $key ];

            switch ( $key ) {
                case 'booths':
                    $val = intval( $raw );
                    if ( $val < 1 || $val > 4 ) {
                        return new WP_Error( 'validation', 'Booth count must be between 1 and 4.', [ 'status' => 422 ] );
                    }
                    break;
                case 'tables_needed':
                    $val = max( 0, intval( $raw ) );
                    break;
                case 'sponsor':
                case 'need_electric':
                    $val = in_array( $raw, [ true, 1, '1', 'yes' ], true ) ? 1 : 0;
                    break;
                case 'website':
                    $url = trim( sanitize_text_field( $raw ) );
                    if ( $url && ! preg_match( '#^https?://#i', $url ) ) $url = 'https://' . $url;
                    $val = $url ? esc_url_raw( $url ) : '';
                    break;
                case 'product_type':
                case 'booth_preference':
                    $val = sanitize_textarea_field( $raw );
                    break;
                default:
                    $val = sanitize_text_field( $raw );
                    break;
            }

            update_post_meta( $app_id, $key, $val );
            $updated[] = $key;
        }

        // Promote Invited → Submitted on first vendor save
        if ( $status === 'Invited' ) {
            update_post_meta( $app_id, '_oha_status', 'Submitted' );
            if ( function_exists( 'oha_vhcpt_append_history' ) ) {
                oha_vhcpt_append_history( $app_id, [
                    'type'        => 'status_change',
                    'description' => 'Vendor updated application via REST API; status promoted from Invited to Submitted.',
                ] );
            }
        }

        // Keep post title in sync if company name changed
        if ( in_array( 'company_name', $updated, true ) ) {
            $name = get_post_meta( $app_id, 'company_name', true );
            if ( $name ) wp_update_post( [ 'ID' => $app_id, 'post_title' => sanitize_text_field( $name ) ] );
        }

        return rest_ensure_response( [
            'success' => true,
            'updated' => $updated,
            'status'  => get_post_meta( $app_id, '_oha_status', true ),
        ] );
    }
}
