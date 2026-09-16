<?php
/**
 * OHA Haunt Listing Display - Claim Functionality
 *
 * Integrated claim workflow: submit → admin review → approve/reject.
 * Stores claims as a hidden CPT. Writes _gd_claimed / _gd_claimed_by
 * to the listing post meta on approval (compatible with gd-claim-via-reports).
 *
 * NOTE: If the standalone gd-claim-via-reports plugin is also active, disable
 * it to avoid duplicate modals, duplicate AJAX handlers, and double admin menus.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class OHA_HLD_Claim {

    const CPT = 'oha_hld_claim'; // Hidden CPT for claim records

    public static function init() {
        // Register hidden CPT for claim storage
        add_action( 'init', [ __CLASS__, 'register_cpt' ] );

        // AJAX handlers
        add_action( 'wp_ajax_oha_hld_claim_submit',        [ __CLASS__, 'handle_submit' ] );
        add_action( 'wp_ajax_nopriv_oha_hld_claim_submit', [ __CLASS__, 'handle_nopriv' ] );

        // Footer: inject claim modal HTML on gd_place singles (when feature enabled)
        add_action( 'wp_footer', [ __CLASS__, 'render_modal_html' ], 99 );

        // Admin menu bubble for pending count
        add_action( 'admin_menu', [ __CLASS__, 'add_pending_bubble' ], 100 );
    }

    /** Register the hidden CPT */
    public static function register_cpt() {
        register_post_type( self::CPT, [
            'label'           => 'Haunt Claims',
            'public'          => false,
            'show_ui'         => false,
            'show_in_menu'    => false,
            'supports'        => [ 'title', 'custom-fields' ],
            'capability_type' => 'post',
            'capabilities'    => [ 'create_posts' => 'do_not_allow' ],
            'map_meta_cap'    => true,
        ] );
    }

    /** Show pending count badge on admin menu */
    public static function add_pending_bubble() {
        global $submenu;
        $count = self::pending_count();
        if ( ! $count ) return;
        $parent = menu_page_url( 'geodirectory', false ) ? 'geodirectory' : 'options-general.php';
        if ( ! isset( $submenu[ $parent ] ) ) return;
        foreach ( $submenu[ $parent ] as &$item ) {
            if ( isset( $item[2] ) && $item[2] === OHA_HLD_Admin::PAGE_SLUG ) {
                $item[0] = 'OHA Listing Display <span class="awaiting-mod">' . $count . '</span>';
                break;
            }
        }
    }

    /** Count pending claims */
    public static function pending_count() {
        $c = wp_count_posts( self::CPT );
        return $c && isset( $c->pending ) ? (int) $c->pending : 0;
    }

    /**
     * Return the full claim section HTML for the sidebar.
     * All context logic is handled here — no gating needed in templates.
     *
     * Scenarios:
     *   1. Feature disabled → ''
     *   2. Admin (manage_options) → info panel: author + claim status, no claim button
     *   3. Current user IS post_author → "You Own This" + GD Edit button
     *   4. Listing claimed by a real user (non-admin, non-board_member) → ''
     *   5. Unclaimed / held by admin or board_member → claim prompt + button / login link
     */
    public static function section_html( $listing_id ) {
        if ( ! OHA_HLD_Admin::get( 'enable_claim', 1 ) ) return '';

        $author_id   = (int) get_post_field( 'post_author', $listing_id );
        $claimed_val = get_post_meta( $listing_id, '_gd_claimed', true );
        $claimed_by  = (int) get_post_meta( $listing_id, '_gd_claimed_by', true );
        $current_uid = get_current_user_id();

        // ── Scenario 2: Admin info panel ─────────────────────────────────────
        if ( current_user_can( 'manage_options' ) ) {
            $author      = $author_id ? get_userdata( $author_id ) : null;
            $author_name = $author ? ( $author->display_name ?: $author->user_login ) : '(unknown)';
            $wp_edit_url = admin_url( 'post.php?post=' . $listing_id . '&action=edit' );
            $gd_edit_url = add_query_arg( 'gd_action', 'edit_post', get_permalink( $listing_id ) );
            $author_link = '<a href="' . esc_url( $wp_edit_url ) . '">' . esc_html( $author_name ) . '</a>';

            $override_claimed = get_post_meta( $listing_id, '_oha_hld_override_claimed', true ) === '1';

            if ( $claimed_val && $claimed_by ) {
                $claimer      = get_userdata( $claimed_by );
                $claimer_name = $claimer ? ( $claimer->display_name ?: $claimer->user_login ) : '#' . $claimed_by;
                $status_html  = '<span class="oha-claim-admin-value oha-claim-admin-value--claimed">Claimed by ' . esc_html( $claimer_name ) . '</span>';
            } elseif ( $override_claimed ) {
                $status_html = '<span class="oha-claim-admin-value oha-claim-admin-value--claimed">Owner-managed (admin override)</span>';
            } else {
                $status_html = '<span class="oha-claim-admin-value oha-claim-admin-value--unclaimed">Unclaimed</span>';
            }

            return '
<div class="oha-claim-section oha-claim-section--admin">
    <h3 class="oha-claim-section__heading">Listing Info</h3>
    <div class="oha-claim-admin-row">
        <span class="oha-claim-admin-label">Author</span>
        <span class="oha-claim-admin-value">' . $author_link . '</span>
    </div>
    <div class="oha-claim-admin-row">
        <span class="oha-claim-admin-label">Claimed</span>
        ' . $status_html . '
    </div>
    <div class="oha-claim-admin-row" style="margin-top:12px;gap:8px;flex-wrap:wrap;">
        <a class="oha-claim-btn oha-claim-btn--edit" style="font-size:12px;padding:6px 14px;" href="' . esc_url( $gd_edit_url ) . '">Edit (Frontend)</a>
        <a class="oha-claim-btn" style="font-size:12px;padding:6px 14px;color:var(--oha-muted);border-color:rgba(224,213,197,.25);" href="' . esc_url( $wp_edit_url ) . '">Edit (WP Admin)</a>
    </div>
</div>';
        }

        // ── Scenario 3: Current user is the post author → "You Own This" ─────
        if ( is_user_logged_in() && $current_uid === $author_id ) {
            $edit_url = add_query_arg( 'gd_action', 'edit_post', get_permalink( $listing_id ) );
            return '
<div class="oha-claim-section oha-claim-section--owner">
    <h3 class="oha-claim-section__heading">You Own This</h3>
    <p class="oha-claim-section__body">This is your listing. Keep it up to date to attract more visitors.</p>
    <a class="oha-claim-btn oha-claim-btn--edit" href="' . esc_url( $edit_url ) . '">Edit Listing</a>
</div>';
        }

        // ── Scenario 4: Claimed by a different real user → hide ──────────────
        // "Real" = not admin, not board_member
        if ( $claimed_val && $claimed_by ) {
            $claimer = get_userdata( $claimed_by );
            if ( $claimer
                && ! user_can( $claimed_by, 'manage_options' )
                && ! in_array( 'board_member', (array) $claimer->roles, true )
            ) {
                return '';
            }
        }

        // ── Only show claim prompt for OHB account listings (user #3) ────────
        // Admin-created and board_member-created listings are not claimable.
        if ( $author_id !== 3 ) {
            return '';
        }

        // ── Admin override: listing is marked as owner-managed → hide prompt ──
        if ( get_post_meta( $listing_id, '_oha_hld_override_claimed', true ) === '1' ) {
            return '';
        }

        // ── Scenario 5 & 6: OHB listing, unclaimed → claim prompt ────────────
        $heading   = esc_html( OHA_HLD_Admin::get( 'claim_heading',     'Is This Your Haunt?' ) );
        $body_text = esc_html( OHA_HLD_Admin::get( 'claim_body_text',   'Own or manage this location? Claim this listing to update details, respond to reviews, and connect with visitors.' ) );
        $btn_label = esc_html( OHA_HLD_Admin::get( 'claim_button_label','Claim This Haunt' ) );

        ob_start();
        ?>
<div class="oha-claim-section">
    <h3 class="oha-claim-section__heading"><?php echo $heading; ?></h3>
    <p class="oha-claim-section__body"><?php echo $body_text; ?></p>
        <?php if ( ! is_user_logged_in() ) : ?>
    <a class="oha-claim-btn oha-claim-btn--login"
       href="<?php echo esc_url( wp_login_url( get_permalink( $listing_id ) ) ); ?>">
        Log In to Claim
    </a>
        <?php else : ?>
            <?php
            $existing = get_posts( [
                'post_type'      => self::CPT,
                'post_status'    => [ 'pending', 'publish' ],
                'posts_per_page' => 1,
                'meta_query'     => [
                    [ 'key' => '_oha_hld_listing_id', 'value' => $listing_id ],
                    [ 'key' => '_oha_hld_claimant',   'value' => $current_uid ],
                ],
            ] );
            ?>
            <?php if ( $existing ) : ?>
    <p class="oha-claim-already">You have already submitted a claim for this listing.</p>
            <?php else : ?>
    <button class="oha-claim-btn" data-oha-claim-open
            data-listing="<?php echo esc_attr( $listing_id ); ?>"><?php echo $btn_label; ?></button>
            <?php endif; ?>
        <?php endif; ?>
</div>
        <?php
        return ob_get_clean();
    }

    /** Inject modal HTML into wp_footer (only on gd_place singles with claim enabled) */
    public static function render_modal_html() {
        if ( ! is_singular( 'gd_place' ) ) return;
        if ( ! OHA_HLD_Admin::get( 'enable_claim', 1 ) ) return;
        if ( ! is_user_logged_in() ) return;

        global $post;
        if ( ! $post ) return;

        $listing_id   = $post->ID;
        $heading      = esc_html( OHA_HLD_Admin::get( 'claim_heading', 'Is This Your Haunt?' ) );
        $button_label = esc_html( OHA_HLD_Admin::get( 'claim_button_label', 'Claim This Haunt' ) );
        ?>
        <div class="oha-claim-modal" id="oha-claim-modal" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-label="<?php echo $heading; ?>">
            <div class="oha-claim-dialog">
                <button class="oha-claim-modal__close" type="button" data-oha-claim-close aria-label="Close">&times;</button>
                <h3 class="oha-claim-modal__heading"><?php echo $heading; ?></h3>

                <form id="oha-claim-form">
                    <?php wp_nonce_field( 'oha_hld_claim_nonce_action', 'oha_hld_claim_nonce' ); ?>
                    <input type="hidden" name="action"     value="oha_hld_claim_submit">
                    <input type="hidden" name="listing_id" value="<?php echo esc_attr( $listing_id ); ?>">

                    <label class="oha-claim-modal__label" for="oha-claim-message">
                        Describe your connection to this haunt (owner, manager, operator) and include any details we can use to verify your claim.
                    </label>
                    <textarea
                        id="oha-claim-message"
                        name="message"
                        class="oha-claim-modal__textarea"
                        rows="5"
                        required
                        placeholder="Example: I am the owner. Website: ..., email: ..., social links: ..."></textarea>

                    <button type="submit" class="oha-claim-modal__submit"><?php echo $button_label; ?></button>
                </form>

                <div class="oha-claim-modal__success" hidden>
                    <p>Your claim has been submitted and is pending review. We'll notify you by email once it's approved.</p>
                </div>
            </div>
        </div>
        <?php
    }

    /** AJAX: not logged in */
    public static function handle_nopriv() {
        wp_send_json_error( [ 'msg' => 'You must be logged in to claim a listing.' ], 401 );
    }

    /** AJAX: submit claim */
    public static function handle_submit() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'msg' => 'Login required.' ], 401 );
        }

        if ( ! wp_verify_nonce( $_POST['oha_hld_claim_nonce'] ?? '', 'oha_hld_claim_nonce_action' ) ) {
            wp_send_json_error( [ 'msg' => 'Security check failed.' ], 403 );
        }

        $user_id    = get_current_user_id();
        $listing_id = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0;
        $message    = isset( $_POST['message'] ) ? wp_kses_post( $_POST['message'] ) : '';

        if ( ! $listing_id || get_post_type( $listing_id ) !== 'gd_place' ) {
            wp_send_json_error( [ 'msg' => 'Invalid listing.' ] );
        }

        // Prevent duplicate claims
        $existing = get_posts( [
            'post_type'      => self::CPT,
            'post_status'    => [ 'pending', 'publish' ],
            'posts_per_page' => 1,
            'meta_query'     => [
                [ 'key' => '_oha_hld_listing_id', 'value' => $listing_id ],
                [ 'key' => '_oha_hld_claimant',   'value' => $user_id ],
            ],
        ] );
        if ( $existing ) {
            wp_send_json_success( [ 'msg' => 'Already submitted.' ] );
        }

        $claim_id = wp_insert_post( [
            'post_type'   => self::CPT,
            'post_status' => 'pending',
            'post_title'  => 'Claim #' . $listing_id . ' by user #' . $user_id,
            'meta_input'  => [
                '_oha_hld_listing_id' => $listing_id,
                '_oha_hld_claimant'   => $user_id,
                '_oha_hld_message'    => $message,
            ],
        ] );

        if ( ! $claim_id || is_wp_error( $claim_id ) ) {
            wp_send_json_error( [ 'msg' => 'Could not save your request. Please try again.' ] );
        }

        $s            = OHA_HLD_Admin::get_all();
        $listing_title = get_the_title( $listing_id );
        $current_user  = wp_get_current_user();
        $vars = [
            'listing_title' => $listing_title,
            'listing_url'   => get_permalink( $listing_id ),
            'user_name'     => $current_user->display_name ?: $current_user->user_login,
            'site_name'     => get_bloginfo( 'name' ),
        ];

        // Admin notification email
        if ( ! empty( $s['claim_email_admin'] ) ) {
            $to = trim( $s['claim_email_recipients'] );
            $to = empty( $to ) ? get_option( 'admin_email' ) : $to;
            $to_list = array_map( 'trim', array_filter( explode( ',', $to ) ) );
            $headers = self::build_headers( $s['claim_email_from'], $current_user->user_email );
            foreach ( $to_list as $rcpt ) {
                wp_mail(
                    $rcpt,
                    '[OHA] New claim: ' . $listing_title,
                    "New claim submitted.\n\nListing: {$vars['listing_url']}\nUser: {$vars['user_name']}\n",
                    $headers
                );
            }
        }

        // Confirmation email to claimant
        if ( ! empty( $s['claim_email_submit'] ) && $current_user->user_email ) {
            $subj = self::replace_vars( $s['claim_email_submit_subject'], $vars );
            $body = self::replace_vars( $s['claim_email_submit_body'], $vars );
            $headers = self::build_headers( $s['claim_email_from'], $current_user->user_email );
            wp_mail( $current_user->user_email, $subj, $body, $headers );
        }

        wp_send_json_success( [ 'msg' => 'Submitted.' ] );
    }

    /** Approve a claim */
    public static function approve( $claim_id ) {
        if ( ! current_user_can( 'manage_options' ) ) return false;

        $listing_id = (int) get_post_meta( $claim_id, '_oha_hld_listing_id', true );
        $claimant   = (int) get_post_meta( $claim_id, '_oha_hld_claimant',   true );
        if ( ! $listing_id || ! $claimant ) return false;

        $old_author = (int) get_post_field( 'post_author', $listing_id );

        // Transfer authorship
        wp_update_post( [ 'ID' => $listing_id, 'post_author' => $claimant ] );

        // Mark listing claimed (compatible meta keys with gd-claim-via-reports)
        update_post_meta( $listing_id, '_gd_claimed',    1 );
        update_post_meta( $listing_id, '_gd_claimed_by', $claimant );

        // Mark claim as approved
        wp_update_post( [ 'ID' => $claim_id, 'post_status' => 'publish' ] );

        $s = OHA_HLD_Admin::get_all();
        $u = get_userdata( $claimant );

        // Approval email
        if ( ! empty( $s['claim_email_approved'] ) && $u && $u->user_email ) {
            $vars = [
                'listing_title' => get_the_title( $listing_id ),
                'listing_url'   => get_permalink( $listing_id ),
                'user_name'     => $u->display_name ?: $u->user_login,
                'site_name'     => get_bloginfo( 'name' ),
            ];
            $subj    = self::replace_vars( $s['claim_email_approved_subject'], $vars );
            $body    = self::replace_vars( $s['claim_email_approved_body'], $vars );
            $headers = self::build_headers( $s['claim_email_from'], $u->user_email );
            wp_mail( $u->user_email, $subj, $body, $headers );
        }

        // Optional: notify previous owner
        if ( $old_author && $old_author !== $claimant ) {
            $old_user = get_userdata( $old_author );
            if ( $old_user && $old_user->user_email ) {
                wp_mail(
                    $old_user->user_email,
                    '[OHA] Listing ownership transferred',
                    'The listing "' . get_the_title( $listing_id ) . '" has been transferred to a new owner.' . "\n\n" . get_permalink( $listing_id )
                );
            }
        }

        return true;
    }

    /** Reject a claim */
    public static function reject( $claim_id ) {
        if ( ! current_user_can( 'manage_options' ) ) return false;
        wp_update_post( [ 'ID' => $claim_id, 'post_status' => 'draft' ] );
        return true;
    }

    /** Replace {placeholder} vars in email template strings */
    private static function replace_vars( $template, $vars ) {
        foreach ( $vars as $k => $v ) {
            $template = str_replace( '{' . $k . '}', $v, $template );
        }
        return $template;
    }

    /** Build email headers array */
    private static function build_headers( $from = '', $reply_to = '' ) {
        $headers = [];
        if ( $from )      $headers[] = 'From: ' . sanitize_email( $from );
        if ( $reply_to )  $headers[] = 'Reply-To: ' . sanitize_email( $reply_to );
        return $headers;
    }
}
