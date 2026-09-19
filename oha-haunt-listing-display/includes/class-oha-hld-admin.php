<?php
/**
 * OHA Haunt Listing Display - Admin Settings Page
 * Handles plugin settings and claims management under GeoDirectory admin.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class OHA_HLD_Admin {

    const OPTION_KEY = 'oha_hld_settings';
    const PAGE_SLUG  = 'oha-haunt-listing-display';
    const CAP        = 'manage_options';

    /** Default settings */
    public static function defaults() {
        return [
            // ── Feature toggles ───────────────────────────────────────────
            'enable_claim'      => 1,  // Show claim section on unclaimed listings
            'enable_report'     => 1,  // Show GD Report Post link
            'enable_gallery'    => 1,  // Show gallery grid
            'enable_nearby'     => 1,  // Show nearby haunts section
            'enable_mobile_bar' => 1,  // Show sticky mobile action bar

            // ── Display ───────────────────────────────────────────────────
            'nearby_count'      => 3,  // How many nearby haunts to show

            // ── Claim section text ────────────────────────────────────────
            'claim_heading'      => 'Is This Your Haunt?',
            'claim_body_text'    => 'Own or manage this location? Claim this listing to update details, respond to reviews, and connect with visitors.',
            'claim_button_label' => 'Claim This Haunt',

            // ── Claim emails ──────────────────────────────────────────────
            'claim_email_from'             => '',
            'claim_email_recipients'       => '',  // blank = site admin_email

            // Admin notification (on submission)
            'claim_email_admin'            => 1,

            // Confirmation email to claimant (on submission)
            'claim_email_submit'           => 1,
            'claim_email_submit_subject'   => 'Claim received: {listing_title}',
            'claim_email_submit_body'      => "Hi {user_name},\n\nWe received your claim request for {listing_title}.\nOur team will review it shortly and notify you once it's approved.\n\nListing: {listing_url}\nSite: {site_name}\n",

            // Approval email to claimant (on approval)
            'claim_email_approved'         => 1,
            'claim_email_approved_subject' => 'Your claim was approved: {listing_title}',
            'claim_email_approved_body'    => "Hi {user_name},\n\nGreat news - your claim for {listing_title} has been approved.\nYou now manage this listing on {site_name}.\n\nListing: {listing_url}\n",

            // ── Utilities ─────────────────────────────────────────────────
            'enable_autocomplete'  => 1,  // GD search autocomplete (keyword + location)
            'enable_place_count'   => 1,  // [gd_place_count] shortcode
            'enable_discount_sort' => 1,  // GD sort option: OHA Discount
        ];
    }

    /** Get a single setting value */
    public static function get( $key, $fallback = null ) {
        $saved = get_option( self::OPTION_KEY, [] );
        $all   = wp_parse_args( is_array( $saved ) ? $saved : [], self::defaults() );
        if ( $fallback !== null ) {
            return isset( $all[ $key ] ) ? $all[ $key ] : $fallback;
        }
        return isset( $all[ $key ] ) ? $all[ $key ] : null;
    }

    /** Get all settings */
    public static function get_all() {
        $saved = get_option( self::OPTION_KEY, [] );
        return wp_parse_args( is_array( $saved ) ? $saved : [], self::defaults() );
    }

    /** Boot hooks */
    public static function init() {
        add_action( 'admin_menu',              [ __CLASS__, 'add_menu' ],      25 );
        add_action( 'add_meta_boxes',          [ __CLASS__, 'register_meta_box' ] );
        add_action( 'save_post_gd_place',      [ __CLASS__, 'save_meta_box' ], 10, 2 );
        register_activation_hook( OHA_HLD_PATH . 'oha-haunt-listing-display.php', [ __CLASS__, 'on_activate' ] );

        // ── Utilities ────────────────────────────────────────────────────
        if ( self::get( 'enable_autocomplete', 1 ) ) {
            add_action( 'wp_ajax_gd_autocomplete_keywords',         [ __CLASS__, 'autocomplete_keywords_cb' ] );
            add_action( 'wp_ajax_nopriv_gd_autocomplete_keywords',  [ __CLASS__, 'autocomplete_keywords_cb' ] );
            add_action( 'wp_ajax_gd_autocomplete_locations',        [ __CLASS__, 'autocomplete_locations_cb' ] );
            add_action( 'wp_ajax_nopriv_gd_autocomplete_locations', [ __CLASS__, 'autocomplete_locations_cb' ] );
            add_action( 'wp_enqueue_scripts',                        [ __CLASS__, 'enqueue_autocomplete' ] );
        }

        if ( self::get( 'enable_place_count', 1 ) ) {
            remove_shortcode( 'gd_place_count' );
            add_shortcode( 'gd_place_count', [ __CLASS__, 'place_count_shortcode' ] );
            add_action( 'save_post',    [ __CLASS__, 'clear_place_count_cache' ] );
            add_action( 'deleted_post', [ __CLASS__, 'clear_place_count_cache' ] );
            add_action( 'trashed_post', [ __CLASS__, 'clear_place_count_cache' ] );
        }

        // Always registered: hides the sort option from the dropdown when the toggle is off,
        // so a previously-dragged-in DB entry doesn't produce silent wrong-sort behavior.
        add_filter( 'geodir_get_sort_options', [ __CLASS__, 'maybe_hide_discount_sort_option' ], 10, 2 );

        if ( self::get( 'enable_discount_sort', 1 ) ) {
            add_filter( 'geodir_add_custom_sort_options', [ __CLASS__, 'register_discount_sort_option' ], 10, 2 );
            add_filter( 'posts_join',                     [ __CLASS__, 'discount_sort_join' ],             10, 2 );
            add_filter( 'geodir_custom_key_orderby',      [ __CLASS__, 'discount_sort_orderby' ],          10, 7 );
        }
    }

    // ── Meta box: listing flags + discount fields ─────────────────────────────

    public static function register_meta_box() {
        add_meta_box(
            'oha-hld-listing-meta',
            'OHA Listing Display',
            [ __CLASS__, 'render_meta_box' ],
            'gd_place',
            'side',
            'default'
        );
    }

    public static function render_meta_box( $post ) {
        wp_nonce_field( 'oha_hld_listing_meta', 'oha_hld_listing_meta_nonce' );

        // ── Owner-managed override ────────────────────────────────────────
        $override = get_post_meta( $post->ID, '_oha_hld_override_claimed', true );
        echo '<label style="display:flex;align-items:flex-start;gap:6px;cursor:pointer;">';
        echo '<input type="checkbox" name="oha_hld_override_claimed" value="1" ' . checked( $override, '1', false ) . ' style="margin-top:3px;">';
        echo '<span><strong>Mark as Owner-Managed</strong><br>';
        echo '<span style="color:#666;font-size:12px;">Suppresses the "Unclaimed" style and claim prompt. Use for listings created before the plugin was installed, or any listing you know is already owned.</span></span>';
        echo '</label>';

        // ── Discount / Deal fields ────────────────────────────────────────
        echo '<hr style="margin:12px 0;">';
        echo '<p style="font-weight:600;margin:0 0 8px;">Discount / Deal</p>';

        $disc_active       = get_post_meta( $post->ID, '_oha_disc_active',       true );
        $disc_rate         = get_post_meta( $post->ID, '_oha_disc_rate',         true );
        $disc_message      = get_post_meta( $post->ID, '_oha_disc_message',      true );
        $disc_link         = get_post_meta( $post->ID, '_oha_disc_link',         true );
        $disc_start        = get_post_meta( $post->ID, '_oha_disc_start',        true );
        $disc_end          = get_post_meta( $post->ID, '_oha_disc_end',          true );
        $disc_bg           = get_post_meta( $post->ID, '_oha_disc_bg',           true ) ?: '#111111';
        $disc_color        = get_post_meta( $post->ID, '_oha_disc_color',        true ) ?: '#ffffff';
        $disc_promo_code   = get_post_meta( $post->ID, '_oha_disc_promo_code',   true );
        $disc_private_link = get_post_meta( $post->ID, '_oha_disc_private_link', true );

        echo '<label style="display:flex;align-items:flex-start;gap:6px;cursor:pointer;margin-bottom:8px;">';
        echo '<input type="checkbox" name="oha_disc_active" value="1" ' . checked( $disc_active, '1', false ) . ' style="margin-top:3px;">';
        echo '<span><strong>Active Discount</strong><br><span style="color:#666;font-size:12px;">Shows in [oha_discounts] shortcode output.</span></span>';
        echo '</label>';

        $iw = 'style="width:100%;margin-bottom:4px;box-sizing:border-box;"';
        $lbl = 'style="display:block;font-size:11px;font-weight:600;margin:4px 0 2px;"';

        echo '<label ' . $lbl . '>Rate (e.g. 15% off)</label>';
        echo '<input type="text" name="oha_disc_rate" value="' . esc_attr( $disc_rate ) . '" ' . $iw . '>';

        echo '<label ' . $lbl . '>Message / Details</label>';
        echo '<input type="text" name="oha_disc_message" value="' . esc_attr( $disc_message ) . '" ' . $iw . '>';

        echo '<label ' . $lbl . '>Discount Link URL</label>';
        echo '<input type="url" name="oha_disc_link" value="' . esc_attr( $disc_link ) . '" placeholder="https://" ' . $iw . '>';

        echo '<label ' . $lbl . '>Start Date</label>';
        echo '<input type="date" name="oha_disc_start" value="' . esc_attr( $disc_start ) . '" ' . $iw . '>';

        echo '<label ' . $lbl . '>End Date</label>';
        echo '<input type="date" name="oha_disc_end" value="' . esc_attr( $disc_end ) . '" ' . $iw . '>';

        echo '<div style="display:flex;gap:8px;margin-top:4px;">';
        echo '<div style="flex:1;"><label ' . $lbl . '>BG Color</label>';
        echo '<input type="color" name="oha_disc_bg" value="' . esc_attr( $disc_bg ) . '" style="width:100%;height:30px;cursor:pointer;border:1px solid #ccc;border-radius:3px;"></div>';

        echo '<div style="flex:1;"><label ' . $lbl . '>Text Color</label>';
        echo '<input type="color" name="oha_disc_color" value="' . esc_attr( $disc_color ) . '" style="width:100%;height:30px;cursor:pointer;border:1px solid #ccc;border-radius:3px;"></div>';
        echo '</div>';

        echo '<hr style="margin:10px 0;">';
        echo '<p style="font-size:11px;color:#666;margin:0 0 6px;">Members-only fields (visible only to logged-in OHA paid members)</p>';

        echo '<label ' . $lbl . '>Promo Code (members only)</label>';
        echo '<input type="text" name="oha_disc_promo_code" value="' . esc_attr( $disc_promo_code ) . '" placeholder="e.g. OHASPOOK25" ' . $iw . '>';

        echo '<label ' . $lbl . '>Private Link (members only)</label>';
        echo '<input type="url" name="oha_disc_private_link" value="' . esc_attr( $disc_private_link ) . '" placeholder="https://" ' . $iw . '>';
    }

    public static function save_meta_box( $post_id, $post ) {
        if ( ! isset( $_POST['oha_hld_listing_meta_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['oha_hld_listing_meta_nonce'], 'oha_hld_listing_meta' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // Owner-managed override
        $value = isset( $_POST['oha_hld_override_claimed'] ) ? '1' : '';
        update_post_meta( $post_id, '_oha_hld_override_claimed', $value );

        // Discount fields
        $disc_fields = [
            '_oha_disc_active'       => ! empty( $_POST['oha_disc_active'] ) ? '1' : '',
            '_oha_disc_rate'         => sanitize_text_field( $_POST['oha_disc_rate']         ?? '' ),
            '_oha_disc_message'      => sanitize_text_field( $_POST['oha_disc_message']      ?? '' ),
            '_oha_disc_link'         => esc_url_raw( $_POST['oha_disc_link']                 ?? '' ),
            '_oha_disc_start'        => sanitize_text_field( $_POST['oha_disc_start']        ?? '' ),
            '_oha_disc_end'          => sanitize_text_field( $_POST['oha_disc_end']          ?? '' ),
            '_oha_disc_bg'           => sanitize_hex_color( $_POST['oha_disc_bg']    ?? '#111111' ) ?: '#111111',
            '_oha_disc_color'        => sanitize_hex_color( $_POST['oha_disc_color'] ?? '#ffffff' ) ?: '#ffffff',
            '_oha_disc_promo_code'   => sanitize_text_field( $_POST['oha_disc_promo_code']   ?? '' ),
            '_oha_disc_private_link' => esc_url_raw( $_POST['oha_disc_private_link']         ?? '' ),
        ];
        foreach ( $disc_fields as $key => $val ) {
            update_post_meta( $post_id, $key, $val );
        }
    }

    public static function on_activate() {
        if ( ! get_option( self::OPTION_KEY ) ) {
            update_option( self::OPTION_KEY, self::defaults() );
        }
    }

    public static function add_menu() {
        // Prefer to appear under GeoDirectory if available; fall back to Settings
        $parent = menu_page_url( 'geodirectory', false ) ? 'geodirectory' : 'options-general.php';

        add_submenu_page(
            $parent,
            'OHA Listing Display',
            'OHA Listing Display',
            self::CAP,
            self::PAGE_SLUG,
            [ __CLASS__, 'render_page' ]
        );
    }

    /** ── Settings save ───────────────────────────────────────────────────── */
    private static function maybe_save( $tab ) {
        if ( ! isset( $_POST['oha_hld_save'] ) ) return;
        if ( ! check_admin_referer( 'oha_hld_nonce_' . $tab, 'oha_hld_nonce' ) ) return;

        $s = self::get_all();

        if ( $tab === 'display' ) {
            $s['enable_claim']      = isset( $_POST['enable_claim'] )      ? 1 : 0;
            $s['enable_report']     = isset( $_POST['enable_report'] )     ? 1 : 0;
            $s['enable_gallery']    = isset( $_POST['enable_gallery'] )    ? 1 : 0;
            $s['enable_nearby']     = isset( $_POST['enable_nearby'] )     ? 1 : 0;
            $s['enable_mobile_bar'] = isset( $_POST['enable_mobile_bar'] ) ? 1 : 0;
            $s['nearby_count']      = max( 1, min( 9, absint( $_POST['nearby_count'] ?? 3 ) ) );
        }

        if ( $tab === 'claim' ) {
            $s['claim_heading']                = sanitize_text_field( $_POST['claim_heading']      ?? '' );
            $s['claim_body_text']              = sanitize_textarea_field( $_POST['claim_body_text'] ?? '' );
            $s['claim_button_label']           = sanitize_text_field( $_POST['claim_button_label'] ?? '' );
            $s['claim_email_from']             = sanitize_email( $_POST['claim_email_from'] ?? '' );
            $s['claim_email_recipients']       = sanitize_text_field( $_POST['claim_email_recipients'] ?? '' );
            $s['claim_email_admin']            = isset( $_POST['claim_email_admin'] )   ? 1 : 0;
            $s['claim_email_submit']           = isset( $_POST['claim_email_submit'] )  ? 1 : 0;
            $s['claim_email_submit_subject']   = sanitize_text_field( $_POST['claim_email_submit_subject'] ?? '' );
            $s['claim_email_submit_body']      = wp_kses_post( str_replace( "\r\n", "\n", $_POST['claim_email_submit_body'] ?? '' ) );
            $s['claim_email_approved']         = isset( $_POST['claim_email_approved'] ) ? 1 : 0;
            $s['claim_email_approved_subject'] = sanitize_text_field( $_POST['claim_email_approved_subject'] ?? '' );
            $s['claim_email_approved_body']    = wp_kses_post( str_replace( "\r\n", "\n", $_POST['claim_email_approved_body'] ?? '' ) );
        }

        if ( $tab === 'utilities' ) {
            $s['enable_autocomplete']  = isset( $_POST['enable_autocomplete'] )  ? 1 : 0;
            $s['enable_place_count']   = isset( $_POST['enable_place_count'] )   ? 1 : 0;
            $s['enable_discount_sort'] = isset( $_POST['enable_discount_sort'] ) ? 1 : 0;
        }

        update_option( self::OPTION_KEY, $s );
        return true;
    }

    /** ── Main page router ────────────────────────────────────────────────── */
    public static function render_page() {
        if ( ! current_user_can( self::CAP ) ) { wp_die( 'Not allowed.' ); }

        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'display';
        $valid_tabs = [ 'display', 'claim', 'claims', 'fields', 'utilities' ];
        if ( ! in_array( $tab, $valid_tabs, true ) ) $tab = 'display';

        $saved = false;
        if ( in_array( $tab, [ 'display', 'claim', 'utilities' ], true ) ) {
            $saved = self::maybe_save( $tab );
        }

        // Handle claim actions (approve / reject)
        if ( $tab === 'claims' && isset( $_GET['oha_hld_action'], $_GET['claim_id'] ) ) {
            $nonce = $_GET['_wpnonce'] ?? '';
            if ( wp_verify_nonce( $nonce, 'oha_hld_claim_action' ) ) {
                $claim_id = absint( $_GET['claim_id'] );
                if ( 'approve' === $_GET['oha_hld_action'] ) {
                    OHA_HLD_Claim::approve( $claim_id );
                    $saved = 'approved';
                } elseif ( 'reject' === $_GET['oha_hld_action'] ) {
                    OHA_HLD_Claim::reject( $claim_id );
                    $saved = 'rejected';
                }
            }
        }

        $tabs = [
            'display'   => 'Display Settings',
            'claim'     => 'Claim Settings',
            'claims'    => 'Pending Claims',
            'fields'    => 'Field Reference',
            'utilities' => 'Utilities',
        ];

        echo '<div class="wrap">';
        echo '<h1>OHA Haunt Listing Display</h1>';

        if ( $saved === true ) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>Settings saved.</strong></p></div>';
        } elseif ( $saved === 'approved' ) {
            echo '<div class="notice notice-success is-dismissible"><p>Claim approved.</p></div>';
        } elseif ( $saved === 'rejected' ) {
            echo '<div class="notice notice-info is-dismissible"><p>Claim rejected.</p></div>';
        }

        // Tab nav
        echo '<h2 class="nav-tab-wrapper">';
        foreach ( $tabs as $key => $label ) {
            $extra = '';
            if ( $key === 'claims' ) {
                $count = OHA_HLD_Claim::pending_count();
                if ( $count ) $extra = ' <span class="awaiting-mod">' . $count . '</span>';
            }
            $url = add_query_arg( [ 'page' => self::PAGE_SLUG, 'tab' => $key ], admin_url( 'admin.php' ) );
            $cls = 'nav-tab' . ( $tab === $key ? ' nav-tab-active' : '' );
            echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $cls ) . '">' . esc_html( $label ) . $extra . '</a>';
        }
        echo '</h2>';

        switch ( $tab ) {
            case 'display':   self::render_display_tab();   break;
            case 'claim':     self::render_claim_tab();     break;
            case 'claims':    self::render_claims_tab();    break;
            case 'fields':    self::render_fields_tab();    break;
            case 'utilities': self::render_utilities_tab(); break;
        }

        echo '</div>'; // .wrap
    }

    /** ── Display tab ─────────────────────────────────────────────────────── */
    private static function render_display_tab() {
        $s = self::get_all();
        ?>
        <form method="post">
            <?php wp_nonce_field( 'oha_hld_nonce_display', 'oha_hld_nonce' ); ?>
            <h2>Feature Toggles</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Claim Listing Section</th>
                    <td>
                        <label><input type="checkbox" name="enable_claim" <?php checked( $s['enable_claim'] ); ?>>
                        Show "Is This Your Haunt?" section on unclaimed listings</label>
                        <p class="description">Only appears when the listing has not been claimed and the viewer is not the listing owner.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Report Post</th>
                    <td>
                        <label><input type="checkbox" name="enable_report" <?php checked( $s['enable_report'] ); ?>>
                        Show GeoDirectory "Report Post" link at bottom of sidebar</label>
                        <p class="description">Uses the built-in GeoDirectory <code>[gd_report_post]</code> shortcode.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Gallery Section</th>
                    <td>
                        <label><input type="checkbox" name="enable_gallery" <?php checked( $s['enable_gallery'] ); ?>>
                        Show photo gallery grid on listing page</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Nearby Haunts Section</th>
                    <td>
                        <label><input type="checkbox" name="enable_nearby" <?php checked( $s['enable_nearby'] ); ?>>
                        Show "More Haunts" section below the listing</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Mobile Action Bar</th>
                    <td>
                        <label><input type="checkbox" name="enable_mobile_bar" <?php checked( $s['enable_mobile_bar'] ); ?>>
                        Show sticky Directions / Call bar on mobile</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="nearby_count">Nearby Haunts Count</label></th>
                    <td>
                        <input type="number" id="nearby_count" name="nearby_count" value="<?php echo esc_attr( $s['nearby_count'] ); ?>" min="1" max="9" style="width:60px;">
                        <p class="description">Number of nearby haunts to display (1–9).</p>
                    </td>
                </tr>
            </table>
            <p><button type="submit" name="oha_hld_save" class="button button-primary">Save Settings</button></p>
        </form>
        <?php
    }

    /** ── Claim settings tab ──────────────────────────────────────────────── */
    private static function render_claim_tab() {
        $s = self::get_all();
        ?>
        <form method="post">
            <?php wp_nonce_field( 'oha_hld_nonce_claim', 'oha_hld_nonce' ); ?>

            <h2>Claim Section Text</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="claim_heading">Heading</label></th>
                    <td><input type="text" id="claim_heading" name="claim_heading" value="<?php echo esc_attr( $s['claim_heading'] ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="claim_body_text">Body Text</label></th>
                    <td><textarea id="claim_body_text" name="claim_body_text" rows="3" class="large-text"><?php echo esc_textarea( $s['claim_body_text'] ); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="claim_button_label">Button Label</label></th>
                    <td><input type="text" id="claim_button_label" name="claim_button_label" value="<?php echo esc_attr( $s['claim_button_label'] ); ?>" class="regular-text"></td>
                </tr>
            </table>

            <h2>Email Configuration</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="claim_email_from">From Address</label></th>
                    <td><input type="email" id="claim_email_from" name="claim_email_from" value="<?php echo esc_attr( $s['claim_email_from'] ); ?>" class="regular-text">
                    <p class="description">Use a verified address on your domain for best deliverability. Leave blank to use WordPress default.</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="claim_email_recipients">Admin Recipients</label></th>
                    <td><input type="text" id="claim_email_recipients" name="claim_email_recipients" value="<?php echo esc_attr( $s['claim_email_recipients'] ); ?>" class="regular-text">
                    <p class="description">Comma-separated. Leave blank to use the site admin email.</p></td>
                </tr>
                <tr>
                    <th scope="row">Admin Notification</th>
                    <td><label><input type="checkbox" name="claim_email_admin" <?php checked( $s['claim_email_admin'] ); ?>>
                    Email admins when a claim is submitted</label></td>
                </tr>
            </table>

            <h2>Email to Claimant — On Submission</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Enable</th>
                    <td><label><input type="checkbox" name="claim_email_submit" <?php checked( $s['claim_email_submit'] ); ?>>
                    Send confirmation email to claimant</label></td>
                </tr>
                <tr>
                    <th scope="row"><label for="claim_email_submit_subject">Subject</label></th>
                    <td><input type="text" id="claim_email_submit_subject" name="claim_email_submit_subject" value="<?php echo esc_attr( $s['claim_email_submit_subject'] ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="claim_email_submit_body">Body</label></th>
                    <td><textarea id="claim_email_submit_body" name="claim_email_submit_body" rows="6" class="large-text code"><?php echo esc_textarea( $s['claim_email_submit_body'] ); ?></textarea>
                    <p class="description">Placeholders: <code>{user_name}</code>, <code>{listing_title}</code>, <code>{listing_url}</code>, <code>{site_name}</code></p></td>
                </tr>
            </table>

            <h2>Email to Claimant — On Approval</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Enable</th>
                    <td><label><input type="checkbox" name="claim_email_approved" <?php checked( $s['claim_email_approved'] ); ?>>
                    Send email to claimant when their claim is approved</label></td>
                </tr>
                <tr>
                    <th scope="row"><label for="claim_email_approved_subject">Subject</label></th>
                    <td><input type="text" id="claim_email_approved_subject" name="claim_email_approved_subject" value="<?php echo esc_attr( $s['claim_email_approved_subject'] ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="claim_email_approved_body">Body</label></th>
                    <td><textarea id="claim_email_approved_body" name="claim_email_approved_body" rows="6" class="large-text code"><?php echo esc_textarea( $s['claim_email_approved_body'] ); ?></textarea>
                    <p class="description">Placeholders: <code>{user_name}</code>, <code>{listing_title}</code>, <code>{listing_url}</code>, <code>{site_name}</code></p></td>
                </tr>
            </table>

            <p><button type="submit" name="oha_hld_save" class="button button-primary">Save Settings</button></p>
        </form>
        <?php
    }

    /** ── Utilities tab ───────────────────────────────────────────────────── */
    private static function render_utilities_tab() {
        $s = self::get_all();
        ?>
        <form method="post">
            <?php wp_nonce_field( 'oha_hld_nonce_utilities', 'oha_hld_nonce' ); ?>
            <h2>GeoDirectory Utilities</h2>
            <p>Utilities absorbed from standalone plugins. Disable any you prefer to run as a separate plugin instead.</p>
            <table class="form-table">
                <tr>
                    <th scope="row">Search Autocomplete</th>
                    <td>
                        <label><input type="checkbox" name="enable_autocomplete" <?php checked( $s['enable_autocomplete'] ); ?>>
                        Keyword and location autocomplete on GeoDirectory search forms</label>
                        <p class="description">
                            Adds dropdown suggestions to <code>input[name="s"]</code> (keyword) and <code>input[name="snear"]</code> (near location).
                            Loads on search pages and standard pages. Uses WordPress's bundled jQuery UI — no CDN required.
                        </p>
                        <p class="description"><em>Replaces the standalone <strong>GD Core Autocomplete Enhancer</strong> plugin — deactivate that plugin if you enable this.</em></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Place Count Shortcode</th>
                    <td>
                        <label><input type="checkbox" name="enable_place_count" <?php checked( $s['enable_place_count'] ); ?>>
                        Register <code>[gd_place_count]</code> shortcode</label>
                        <p class="description">
                            Outputs the total number of published GeoDirectory places.
                            Supports <code>before</code>, <code>after</code>, and <code>class</code> attributes. Count is cached hourly.
                        </p>
                        <p class="description"><em>Replaces the standalone <strong>GeoDirectory Place Count Shortcode</strong> plugin — deactivate that plugin if you enable this.</em></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Discount Sort Option</th>
                    <td>
                        <label><input type="checkbox" name="enable_discount_sort" <?php checked( $s['enable_discount_sort'] ); ?>>
                        Add "OHA Discount" sort option to GeoDirectory place search</label>
                        <p class="description">
                            When enabled, haunts with an active OHA discount sort to the top when "OHA Discount" is selected in the GD sort dropdown.
                            Disable this during off-season when discounts are not in use.
                        </p>
                        <p class="description">
                            After enabling for the first time, go to <strong>GeoDirectory &rarr; Places &rarr; Sorting</strong> and drag the <strong>OHA Discount</strong> tile from the left panel into the active sort list. Set direction to <strong>DESC</strong>.
                        </p>
                    </td>
                </tr>
            </table>
            <p><button type="submit" name="oha_hld_save" class="button button-primary">Save Settings</button></p>
        </form>
        <?php
    }

    /** ── Field Reference tab ────────────────────────────────────────────── */
    private static function render_fields_tab() {
        global $wpdb;

        echo '<h2>GeoDirectory Custom Fields — gd_place</h2>';
        echo '<p>These are all active custom fields registered for <code>gd_place</code> listings. The <strong>Field Key</strong> is what you use in <code>$gd->field_key</code> to read data in the plugin templates.</p>';

        $table = $wpdb->prefix . 'geodir_custom_fields';
        // Check the table exists before querying
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
            echo '<p><em>GeoDirectory custom fields table not found. Make sure GeoDirectory is installed and activated.</em></p>';
            return;
        }

        $fields = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT htmlvar_name, frontend_title, field_type, is_active, is_required, show_in, sort_order
                 FROM {$table}
                 WHERE post_type = %s
                 ORDER BY sort_order ASC",
                'gd_place'
            )
        );

        if ( empty( $fields ) ) {
            echo '<p><em>No custom fields found for gd_place.</em></p>';
            return;
        }

        $preview_id = isset( $_GET['preview_id'] ) ? absint( $_GET['preview_id'] ) : 0;

        echo '<table class="widefat striped" style="margin-top:16px;">';
        echo '<thead><tr>';
        echo '<th>Field Key <small style="font-weight:normal">(htmlvar_name)</small></th>';
        echo '<th>Label</th>';
        echo '<th>Type</th>';
        echo '<th>Active</th>';
        echo '<th>Required</th>';
        if ( $preview_id && function_exists( 'geodir_get_post_info' ) ) {
            echo '<th>Value on listing #' . esc_html( $preview_id ) . '</th>';
        }
        echo '</tr></thead><tbody>';

        $gd_preview = ( $preview_id && function_exists( 'geodir_get_post_info' ) )
            ? geodir_get_post_info( $preview_id )
            : null;

        foreach ( $fields as $f ) {
            echo '<tr>';
            echo '<td><code>' . esc_html( $f->htmlvar_name ) . '</code></td>';
            echo '<td>' . esc_html( $f->frontend_title ) . '</td>';
            echo '<td>' . esc_html( $f->field_type ) . '</td>';
            echo '<td>' . ( $f->is_active ? '✓' : '—' ) . '</td>';
            echo '<td>' . ( $f->is_required ? '✓' : '—' ) . '</td>';
            if ( $gd_preview ) {
                $val = isset( $gd_preview->{$f->htmlvar_name} ) ? $gd_preview->{$f->htmlvar_name} : '';
                echo '<td style="max-width:280px;word-break:break-word;">' . esc_html( is_array( $val ) ? implode( ', ', $val ) : $val ) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';

        // Preview form
        echo '<h3 style="margin-top:24px;">Preview a Listing\'s Field Values</h3>';
        echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" style="display:flex;align-items:center;gap:10px;margin-top:8px;">';
        echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '">';
        echo '<input type="hidden" name="tab" value="fields">';
        echo '<input type="number" name="preview_id" value="' . esc_attr( $preview_id ) . '" placeholder="Listing post ID" style="width:160px;" class="regular-text">';
        echo '<button type="submit" class="button">Show Values</button>';
        echo '</form>';
        echo '<p class="description">Enter a gd_place post ID to see the actual stored values for every field on that listing.</p>';
    }

    /** ── Pending claims tab ──────────────────────────────────────────────── */
    private static function render_claims_tab() {
        $status_map = [ 'pending' => 'pending', 'approved' => 'publish', 'rejected' => 'draft' ];
        $view = isset( $_GET['view'] ) && isset( $status_map[ $_GET['view'] ] ) ? sanitize_key( $_GET['view'] ) : 'pending';
        $status = $status_map[ $view ];

        // Sub-tab nav
        $base_url = add_query_arg( [ 'page' => self::PAGE_SLUG, 'tab' => 'claims' ], admin_url( 'admin.php' ) );
        echo '<p>';
        foreach ( array_keys( $status_map ) as $v ) {
            $active = $v === $view ? 'font-weight:bold;text-decoration:underline;' : '';
            echo '<a href="' . esc_url( add_query_arg( 'view', $v, $base_url ) ) . '" style="margin-right:16px;' . $active . '">' . ucfirst( $v ) . '</a>';
        }
        echo '</p>';

        $claims = get_posts( [
            'post_type'      => OHA_HLD_Claim::CPT,
            'post_status'    => $status,
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        echo '<table class="widefat"><thead><tr><th>#</th><th>Listing</th><th>Claimant</th><th>Message</th><th>Date</th><th>Actions</th></tr></thead><tbody>';

        if ( empty( $claims ) ) {
            echo '<tr><td colspan="6">No claims found.</td></tr>';
        } else {
            foreach ( $claims as $c ) {
                $listing_id = (int) get_post_meta( $c->ID, '_oha_hld_listing_id', true );
                $claimant   = (int) get_post_meta( $c->ID, '_oha_hld_claimant', true );
                $message    = wp_strip_all_tags( get_post_meta( $c->ID, '_oha_hld_message', true ) );
                $nonce      = wp_create_nonce( 'oha_hld_claim_action' );
                $approve_url = add_query_arg( [ 'page' => self::PAGE_SLUG, 'tab' => 'claims', 'view' => $view, 'oha_hld_action' => 'approve', 'claim_id' => $c->ID, '_wpnonce' => $nonce ], admin_url( 'admin.php' ) );
                $reject_url  = add_query_arg( [ 'page' => self::PAGE_SLUG, 'tab' => 'claims', 'view' => $view, 'oha_hld_action' => 'reject',  'claim_id' => $c->ID, '_wpnonce' => $nonce ], admin_url( 'admin.php' ) );

                $user = $claimant ? get_userdata( $claimant ) : null;
                $user_name = $user ? ( $user->display_name ?: $user->user_login ) : '—';

                echo '<tr>';
                echo '<td>#' . esc_html( $c->ID ) . '</td>';
                echo '<td><a href="' . esc_url( get_edit_post_link( $listing_id ) ) . '">' . esc_html( get_the_title( $listing_id ) ) . '</a></td>';
                echo '<td>' . esc_html( $user_name ) . '</td>';
                echo '<td style="max-width:420px;">' . esc_html( $message ) . '</td>';
                echo '<td>' . esc_html( get_the_date( '', $c ) ) . '</td>';
                echo '<td>';
                if ( $status === 'pending' ) {
                    echo '<a class="button button-primary" href="' . esc_url( $approve_url ) . '">Approve</a> ';
                    echo '<a class="button" href="' . esc_url( $reject_url ) . '" onclick="return confirm(\'Reject this claim?\')">Reject</a>';
                } else {
                    echo '<span class="description">' . ucfirst( $view ) . '</span>';
                }
                echo '</td></tr>';
            }
        }

        echo '</tbody></table>';
    }

    // ── Utility: Autocomplete AJAX ────────────────────────────────────────────

    public static function autocomplete_keywords_cb() {
        $results = get_transient( 'oha_hld_ac_keywords' );
        if ( false === $results ) {
            global $wpdb;
            $results = $wpdb->get_col(
                "SELECT post_title FROM {$wpdb->posts}
                 WHERE post_type = 'gd_place' AND post_status = 'publish'
                 ORDER BY post_title
                 LIMIT 500"
            );
            set_transient( 'oha_hld_ac_keywords', $results, HOUR_IN_SECONDS );
        }
        wp_send_json( $results );
    }

    public static function autocomplete_locations_cb() {
        $cities = get_transient( 'oha_hld_ac_locations' );
        if ( false === $cities ) {
            global $wpdb;
            $table = $wpdb->prefix . 'geodir_gd_place_detail';
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
                wp_send_json( [] ); // Don't cache — table may not exist yet
            }
            $cities = array_values( array_filter( $wpdb->get_col(
                "SELECT DISTINCT city FROM {$table}
                 WHERE city != '' AND city IS NOT NULL
                 ORDER BY city
                 LIMIT 500"
            ) ) );
            set_transient( 'oha_hld_ac_locations', $cities, HOUR_IN_SECONDS );
        }
        wp_send_json( $cities );
    }

    public static function enqueue_autocomplete() {
        if ( is_admin() ) return;

        wp_enqueue_script( 'jquery-ui-autocomplete' );

        // Self-hosted minimal jQuery UI autocomplete CSS — no CDN dependency
        wp_register_style( 'oha-hld-autocomplete', false, [], OHA_HLD_VERSION );
        wp_enqueue_style( 'oha-hld-autocomplete' );
        wp_add_inline_style( 'oha-hld-autocomplete',
            '.ui-autocomplete{position:absolute;z-index:9999;background:#fff;border:1px solid #ccc;border-radius:4px;'
            . 'padding:4px 0;list-style:none;margin:0;max-height:300px;overflow-y:auto;box-shadow:0 2px 6px rgba(0,0,0,.15);}'
            . '.ui-menu-item{padding:0;}'
            . '.ui-autocomplete .ui-menu-item-wrapper{padding:6px 12px;cursor:pointer;display:block;white-space:nowrap;color:#111;}'
            . '.ui-autocomplete .ui-menu-item-wrapper.ui-state-active{background:#0073aa;color:#fff;}'
        );

        wp_enqueue_script(
            'oha-hld-autocomplete',
            OHA_HLD_URL . 'assets/gd-autocomplete.js',
            [ 'jquery', 'jquery-ui-autocomplete' ],
            OHA_HLD_VERSION,
            true
        );
        wp_localize_script( 'oha-hld-autocomplete', 'gdAutoData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        ] );
    }

    // ── Utility: Place Count Shortcode ────────────────────────────────────────

    public static function place_count_shortcode( $atts ) {
        $total = get_transient( 'gd_place_count' );
        if ( false === $total ) {
            $counts = wp_count_posts( 'gd_place' );
            $total  = isset( $counts->publish ) ? intval( $counts->publish ) : 0;
            set_transient( 'gd_place_count', $total, HOUR_IN_SECONDS );
        }
        $atts = shortcode_atts( [
            'before' => '',
            'after'  => '',
            'class'  => 'gd-place-count',
        ], $atts, 'gd_place_count' );
        return $atts['before']
            . '<span class="' . esc_attr( $atts['class'] ) . '">' . number_format_i18n( $total ) . '</span>'
            . $atts['after'];
    }

    public static function clear_place_count_cache( $post_id ) {
        if ( get_post_type( $post_id ) === 'gd_place' ) {
            delete_transient( 'gd_place_count' );
        }
    }

    // ── Discount Sort (GeoDirectory) ─────────────────────────────────────────────

    // Removes oha_disc_active from the live sort dropdown when the toggle is off,
    // preventing a stale GD sort-table entry from showing a broken sort option.
    public static function maybe_hide_discount_sort_option( $options, $post_type ) {
        if ( self::get( 'enable_discount_sort', 1 ) ) return $options;
        if ( ! is_array( $options ) ) return $options;
        return array_values( array_filter( $options, function( $opt ) {
            return ! ( isset( $opt->htmlvar_name ) && $opt->htmlvar_name === 'oha_disc_active' );
        } ) );
    }

    public static function register_discount_sort_option( $fields, $post_type ) {
        if ( $post_type !== 'gd_place' ) return $fields;
        $fields['oha_discount'] = [
            'post_type'      => $post_type,
            'data_type'      => 'TINYINT',
            'field_type'     => 'checkbox',
            'frontend_title' => 'OHA Discount',
            'htmlvar_name'   => 'oha_disc_active',
            'field_icon'     => 'fas fa-tag',
            'description'    => 'Haunts with an active OHA member discount sort first (use DESC)',
        ];
        return $fields;
    }

    public static function discount_sort_join( $join, $wp_query ) {
        global $wpdb;
        $sort_by = get_query_var( 'order_by' );
        if ( isset( $_REQUEST['sort_by'] ) && $_REQUEST['sort_by'] !== '' ) {
            $sort_by = sanitize_text_field( wp_unslash( $_REQUEST['sort_by'] ) );
        }
        if ( strpos( $sort_by, 'oha_disc_active' ) === false ) return $join;
        if ( strpos( $join, 'oha_disc_pm' ) !== false ) return $join; // already added
        $join .= " LEFT JOIN {$wpdb->postmeta} AS oha_disc_pm"
               . " ON ({$wpdb->posts}.ID = oha_disc_pm.post_id"
               . " AND oha_disc_pm.meta_key = '_oha_disc_active') ";
        return $join;
    }

    // $sort_by arrives already stripped of _asc/_desc suffix by GeoDirectory
    public static function discount_sort_orderby( $orderby, $sort_by, $order, $old_orderby, $table, $post_type, $wp_query ) {
        if ( $sort_by !== 'oha_disc_active' ) return $orderby;
        global $wpdb;
        return "COALESCE(oha_disc_pm.meta_value + 0, 0) {$order}, {$wpdb->posts}.post_title ASC";
    }

}
