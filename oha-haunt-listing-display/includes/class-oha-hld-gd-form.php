<?php
/**
 * OHA GeoDirectory Listing Form — UI overlay + owner discount editing.
 *
 * Replaces the Code Snippets entry "OHA GeoDirectory Single Form Mode + UI Tidy".
 * Loads only on the GD add/edit listing page (page 991 or geodir_is_page('add')).
 *
 * Responsibilities:
 *   - Enqueue the CSS/JS overlay for the GD form (moved from snippet)
 *   - Output a PHP-rendered discount section into the form for the listing owner
 *   - Save discount fields when the owner submits the GD frontend form
 *   - Append a change history entry (capped at 10) on each save
 *   - Email board recipients when the discount is changed
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class OHA_HLD_GD_Form {

    public static function init() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ], 99 );
        add_action( 'wp_footer',          [ __CLASS__, 'output_discount_template' ], 5 );
        add_action( 'save_post_gd_place', [ __CLASS__, 'save_discount' ], 20, 3 );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function is_gd_form_page() {
        if ( is_admin() ) return false;
        return is_page( 991 ) || ( function_exists( 'geodir_is_page' ) && geodir_is_page( 'add' ) );
    }

    private static function get_editing_post_id() {
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        if ( preg_match( '#/add-listing/places/(\d+)(?:/|$)#', $uri, $m ) ) {
            return absint( $m[1] );
        }
        return 0;
    }

    // ── Enqueue: CSS + JS overlay (moved from Code Snippets) ─────────────────

    public static function enqueue() {
        if ( ! self::is_gd_form_page() ) return;

        wp_register_style( 'oha-gd-single-form-ui', false, [], OHA_HLD_VERSION );
        wp_enqueue_style( 'oha-gd-single-form-ui' );
        wp_add_inline_style( 'oha-gd-single-form-ui', self::get_form_css() );

        wp_register_script( 'oha-gd-single-form-ui', '', [], OHA_HLD_VERSION, true );
        wp_enqueue_script( 'oha-gd-single-form-ui' );
        wp_add_inline_script( 'oha-gd-single-form-ui', self::get_form_js() );
    }

    // ── Discount template: output hidden div that JS moves into the form ──────

    public static function output_discount_template() {
        if ( ! self::is_gd_form_page() ) return;

        $post_id = self::get_editing_post_id();
        if ( ! $post_id ) return; // Only on edit (not add new)

        if ( ! is_user_logged_in() ) return;
        $author_id = (int) get_post_field( 'post_author', $post_id );
        $is_owner  = get_current_user_id() === $author_id;
        $is_admin  = current_user_can( 'manage_options' ) || current_user_can( 'manage_oha_members' );
        if ( ! $is_owner && ! $is_admin ) return;

        $active       = get_post_meta( $post_id, '_oha_disc_active',       true );
        $rate         = get_post_meta( $post_id, '_oha_disc_rate',         true );
        $message      = get_post_meta( $post_id, '_oha_disc_message',      true );
        $link         = get_post_meta( $post_id, '_oha_disc_link',         true );
        $start        = get_post_meta( $post_id, '_oha_disc_start',        true );
        $end          = get_post_meta( $post_id, '_oha_disc_end',          true );
        $promo_code   = get_post_meta( $post_id, '_oha_disc_promo_code',   true );
        $private_link = get_post_meta( $post_id, '_oha_disc_private_link', true );
        $history      = get_post_meta( $post_id, '_oha_disc_history',      true );
        if ( ! is_array( $history ) ) $history = [];

        $nonce = wp_create_nonce( 'oha_disc_frontend_save' );
        ?>
        <!--
            #oha-disc-inject: JS moves these children into the GD form before
            fieldset processing runs, so they get collapsible section behavior.
            Do NOT wrap in oha-section-wrap here — the snippet JS creates it.
        -->
        <div id="oha-disc-inject" style="display:none!important" aria-hidden="true">

            <fieldset data-rule-type="fieldset" data-rule-key="oha_member_discount">
                <h3>Member Discount</h3>
                <small>Add a discount for OHA members. It goes live immediately on your listing and the Member Discounts page.</small>
            </fieldset>

            <!-- Active toggle: full width -->
            <div class="mb-3 row oha-span-2">
                <label>Discount Active</label>
                <div>
                    <label style="display:inline-flex;align-items:center;gap:10px;cursor:pointer;color:var(--oha-text,#f6e8e4);">
                        <input type="checkbox" name="oha_disc_active" value="1" <?php checked( $active, '1' ); ?> style="width:20px;height:20px;accent-color:#ff4a26;">
                        Show on listing and Member Discounts page
                    </label>
                </div>
            </div>

            <!-- Rate + Message side by side -->
            <div class="mb-3 row">
                <label for="oha_disc_rate_f">Discount Rate</label>
                <div class="col-sm-10">
                    <input type="text" id="oha_disc_rate_f" name="oha_disc_rate" class="form-control"
                           value="<?php echo esc_attr( $rate ); ?>" placeholder="e.g. $5 OFF or 10% OFF">
                </div>
            </div>

            <div class="mb-3 row">
                <label for="oha_disc_message_f">Message (optional)</label>
                <div class="col-sm-10">
                    <input type="text" id="oha_disc_message_f" name="oha_disc_message" class="form-control"
                           value="<?php echo esc_attr( $message ); ?>" placeholder="Extra details, conditions, etc.">
                </div>
            </div>

            <!-- Link: full width -->
            <div class="mb-3 row oha-span-2">
                <label for="oha_disc_link_f">Link URL (optional)</label>
                <div class="col-sm-10">
                    <input type="url" id="oha_disc_link_f" name="oha_disc_link" class="form-control"
                           value="<?php echo esc_attr( $link ); ?>" placeholder="https://">
                </div>
            </div>

            <!-- Start + End side by side -->
            <div class="mb-3 row">
                <label for="oha_disc_start_f">Start Date (optional)</label>
                <div class="col-sm-10">
                    <input type="date" id="oha_disc_start_f" name="oha_disc_start" class="form-control"
                           value="<?php echo esc_attr( $start ); ?>">
                </div>
            </div>

            <div class="mb-3 row">
                <label for="oha_disc_end_f">End Date (optional)</label>
                <div class="col-sm-10">
                    <input type="date" id="oha_disc_end_f" name="oha_disc_end" class="form-control"
                           value="<?php echo esc_attr( $end ); ?>">
                </div>
            </div>

            <!-- Members-only fields: full width -->
            <div class="mb-3 row oha-span-2" style="border-top:1px solid rgba(255,255,255,.08);padding-top:14px;margin-top:6px;">
                <label for="oha_disc_promo_f" style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;opacity:.7;">Members-Only Access</label>
                <div style="font-size:12px;opacity:.55;margin-bottom:6px;">Promo codes and private links are visible only to logged-in OHA paid members.</div>
            </div>

            <div class="mb-3 row">
                <label for="oha_disc_promo_f">Promo Code</label>
                <div class="col-sm-10">
                    <input type="text" id="oha_disc_promo_f" name="oha_disc_promo_code" class="form-control"
                           value="<?php echo esc_attr( $promo_code ); ?>" placeholder="e.g. OHASPOOK25">
                </div>
            </div>

            <div class="mb-3 row">
                <label for="oha_disc_private_link_f">Private Link</label>
                <div class="col-sm-10">
                    <input type="url" id="oha_disc_private_link_f" name="oha_disc_private_link" class="form-control"
                           value="<?php echo esc_attr( $private_link ); ?>" placeholder="https://">
                </div>
            </div>

            <?php if ( ! empty( $history ) ) : ?>
            <!-- History: full width, last 5 entries -->
            <div class="mb-3 row oha-span-2">
                <label>Recent Changes</label>
                <div>
                    <ul style="list-style:none;padding:0;margin:0;font-size:13px;">
                        <?php foreach ( array_slice( $history, 0, 5 ) as $entry ) :
                            $entry_active = isset( $entry['active'] ) && $entry['active'] === '1' ? 'Active' : 'Inactive';
                        ?>
                        <li style="padding:7px 0;border-bottom:1px solid rgba(255,255,255,.06);color:rgba(246,232,228,.8);">
                            <strong style="color:#fff;"><?php echo esc_html( $entry['date'] ?? '' ); ?></strong>
                            &mdash; <?php echo esc_html( $entry['name'] ?? '' ); ?> set
                            <em><?php echo esc_html( $entry['rate'] ?: '(no rate)' ); ?></em>
                            <?php if ( ! empty( $entry['message'] ) ) : ?>
                                &mdash; <?php echo esc_html( $entry['message'] ); ?>
                            <?php endif; ?>
                            <span style="opacity:.55;">(<?php echo esc_html( $entry_active ); ?>)</span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <!-- Nonce submitted with the GD form POST -->
            <input type="hidden" name="oha_disc_nonce" value="<?php echo esc_attr( $nonce ); ?>">

        </div><!-- #oha-disc-inject -->
        <?php
    }

    // ── Frontend save: fires when GD saves the listing post ──────────────────

    public static function save_discount( $post_id, $post, $update ) {
        if ( ! isset( $_POST['oha_disc_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['oha_disc_nonce'], 'oha_disc_frontend_save' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // Only the listing owner or an admin/board manager
        $author_id = (int) get_post_field( 'post_author', $post_id );
        $is_owner  = get_current_user_id() === $author_id;
        $is_admin  = current_user_can( 'manage_options' ) || current_user_can( 'manage_oha_members' );
        if ( ! $is_owner && ! $is_admin ) return;

        // Capture old values for change detection
        $old_active  = get_post_meta( $post_id, '_oha_disc_active',  true );
        $old_rate    = get_post_meta( $post_id, '_oha_disc_rate',    true );
        $old_message = get_post_meta( $post_id, '_oha_disc_message', true );

        // Sanitise + save
        $active       = ! empty( $_POST['oha_disc_active'] ) ? '1' : '0';
        $rate         = sanitize_text_field( wp_unslash( $_POST['oha_disc_rate']         ?? '' ) );
        $message      = sanitize_text_field( wp_unslash( $_POST['oha_disc_message']      ?? '' ) );
        $link         = esc_url_raw( wp_unslash( $_POST['oha_disc_link']                 ?? '' ) );
        $start        = sanitize_text_field( wp_unslash( $_POST['oha_disc_start']        ?? '' ) );
        $end          = sanitize_text_field( wp_unslash( $_POST['oha_disc_end']          ?? '' ) );
        $promo_code   = sanitize_text_field( wp_unslash( $_POST['oha_disc_promo_code']   ?? '' ) );
        $private_link = esc_url_raw( wp_unslash( $_POST['oha_disc_private_link']         ?? '' ) );

        update_post_meta( $post_id, '_oha_disc_active',       $active );
        update_post_meta( $post_id, '_oha_disc_rate',         $rate );
        update_post_meta( $post_id, '_oha_disc_message',      $message );
        update_post_meta( $post_id, '_oha_disc_link',         $link );
        update_post_meta( $post_id, '_oha_disc_start',        $start );
        update_post_meta( $post_id, '_oha_disc_end',          $end );
        update_post_meta( $post_id, '_oha_disc_promo_code',   $promo_code );
        update_post_meta( $post_id, '_oha_disc_private_link', $private_link );

        // Fire existing Board Hub hooks so featured-sync etc. stay in sync
        do_action( $active === '1' ? 'oha_discount_activated' : 'oha_discount_deactivated', $post_id );

        // History: only log if something actually changed
        if ( $active !== $old_active || $rate !== $old_rate || $message !== $old_message ) {
            $history = get_post_meta( $post_id, '_oha_disc_history', true );
            if ( ! is_array( $history ) ) $history = [];

            $user = wp_get_current_user();
            array_unshift( $history, [
                'date'    => current_time( 'Y-m-d H:i' ),
                'name'    => $user->display_name ?: $user->user_login,
                'active'  => $active,
                'rate'    => $rate,
                'message' => $message,
                'source'  => $is_admin ? 'admin' : 'owner',
            ] );
            update_post_meta( $post_id, '_oha_disc_history', array_slice( $history, 0, 10 ) );

            self::notify_board( $post_id, $rate, $message, $active );
        }
    }

    // ── Board notification email ──────────────────────────────────────────────

    private static function notify_board( $post_id, $rate, $message, $active ) {
        $settings   = OHA_HLD_Admin::get_all();
        $raw        = $settings['claim_email_recipients'] ?? '';
        $recipients = $raw ? array_filter( array_map( 'trim', explode( ',', $raw ) ), 'is_email' ) : [];
        if ( empty( $recipients ) ) $recipients = [ get_option( 'admin_email' ) ];

        $user   = wp_get_current_user();
        $title  = get_the_title( $post_id );
        $status = $active === '1' ? 'Active' : 'Inactive';

        $subject = "Haunt Discount Updated: {$title}";
        $body    = "A haunt owner updated their OHA member discount.\n\n"
                 . "Haunt: {$title}\n"
                 . "Updated by: " . ( $user->display_name ?: $user->user_login ) . "\n"
                 . "Status: {$status}\n"
                 . "Rate: " . ( $rate ?: '(none)' ) . "\n";
        if ( $message ) $body .= "Message: {$message}\n";
        $body .= "\nAdmin: " . admin_url( "post.php?post={$post_id}&action=edit" ) . "\n";

        wp_mail( $recipients, $subject, $body );
    }

    // ── CSS ───────────────────────────────────────────────────────────────────

    private static function get_form_css() {
        return '
/* =========================================================
   OHA GeoDirectory Single Form Mode + UI Tidy
   Add mode = simplified first-pass form
   Edit mode = expanded form
   ========================================================= */

body.page-id-991 .entry-content-wrap,
body.geodir-page-add .entry-content-wrap {
  padding: 0 !important;
}

#geodirectory-add-post,
form.geodir-add-listing-form,
form#geodir-add-listing {
  --oha-panel: linear-gradient(180deg, rgba(35,0,0,.96) 0%, rgba(88,0,0,.96) 100%);
  --oha-panel-2: rgba(18,0,0,.88);
  --oha-border: rgba(255,95,64,.26);
  --oha-soft: rgba(255,255,255,.08);
  --oha-text: #f6e8e4;
  --oha-muted: rgba(255,232,224,.72);
  --oha-accent: #ff4a26;
  --oha-accent-2: #ff7a1a;
  --oha-radius: 16px;
  --oha-shadow: 0 18px 40px rgba(0,0,0,.35);
  max-width: 1160px;
  margin: 0 auto 40px;
  color: var(--oha-text);
}

#geodirectory-add-post .oha-form-intro,
form.geodir-add-listing-form .oha-form-intro,
form#geodir-add-listing .oha-form-intro {
  background: radial-gradient(circle at top left, rgba(255,90,40,.18), transparent 42%), var(--oha-panel-2);
  border: 1px solid var(--oha-border);
  border-radius: var(--oha-radius);
  box-shadow: var(--oha-shadow);
  padding: 16px 18px;
  margin: 0 0 18px;
  line-height: 1.5;
}

#geodirectory-add-post .oha-form-intro strong,
form.geodir-add-listing-form .oha-form-intro strong,
form#geodir-add-listing .oha-form-intro strong {
  color: #fff;
}

.oha-hidden-field {
  display: none !important;
}

#geodirectory-add-post .oha-progress,
form.geodir-add-listing-form .oha-progress,
form#geodir-add-listing .oha-progress {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin: 0 0 18px;
}

#geodirectory-add-post .oha-progress-step,
form.geodir-add-listing-form .oha-progress-step,
form#geodir-add-listing .oha-progress-step {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 999px;
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.08);
  font-size: 13px;
  color: var(--oha-muted);
  cursor: pointer;
}

#geodirectory-add-post .oha-progress-step.is-active,
form.geodir-add-listing-form .oha-progress-step.is-active,
form#geodir-add-listing .oha-progress-step.is-active {
  background: rgba(255,74,38,.16);
  border-color: rgba(255,74,38,.4);
  color: #fff;
}

#geodirectory-add-post fieldset[data-rule-type="fieldset"],
form.geodir-add-listing-form fieldset[data-rule-type="fieldset"],
form#geodir-add-listing fieldset[data-rule-type="fieldset"] {
  position: relative;
  background: var(--oha-panel);
  border: 1px solid var(--oha-border);
  border-radius: var(--oha-radius);
  box-shadow: var(--oha-shadow);
  padding: 18px 22px;
  margin: 20px 0 0 !important;
  cursor: pointer;
}

#geodirectory-add-post fieldset[data-rule-type="fieldset"]::after,
form.geodir-add-listing-form fieldset[data-rule-type="fieldset"]::after,
form#geodir-add-listing fieldset[data-rule-type="fieldset"]::after {
  content: "+";
  position: absolute;
  right: 18px;
  top: 18px;
  width: 34px;
  height: 34px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  background: rgba(255,255,255,.08);
  color: #fff;
  font-size: 22px;
  line-height: 1;
}

#geodirectory-add-post fieldset[data-rule-type="fieldset"].oha-open::after,
form.geodir-add-listing-form fieldset[data-rule-type="fieldset"].oha-open::after,
form#geodir-add-listing fieldset[data-rule-type="fieldset"].oha-open::after {
  content: "\2013";
}

#geodirectory-add-post fieldset[data-rule-type="fieldset"] h3,
form.geodir-add-listing-form fieldset[data-rule-type="fieldset"] h3,
form#geodir-add-listing fieldset[data-rule-type="fieldset"] h3 {
  margin: 0 !important;
  font-size: clamp(22px, 2.2vw, 30px);
  line-height: 1.15;
  color: #fff;
}

#geodirectory-add-post fieldset[data-rule-type="fieldset"] small,
form.geodir-add-listing-form fieldset[data-rule-type="fieldset"] small,
form#geodir-add-listing fieldset[data-rule-type="fieldset"] small {
  display: block;
  margin-top: 8px;
  color: var(--oha-muted) !important;
  font-size: 13px;
  line-height: 1.45;
}

#geodirectory-add-post .oha-section-wrap,
form.geodir-add-listing-form .oha-section-wrap,
form#geodir-add-listing .oha-section-wrap {
  background: rgba(12,0,0,.38);
  border: 1px solid rgba(255,255,255,.06);
  border-top: 0;
  border-radius: 0 0 var(--oha-radius) var(--oha-radius);
  padding: 18px;
  margin: 0 0 22px;
}

#geodirectory-add-post .oha-section-wrap[hidden],
form.geodir-add-listing-form .oha-section-wrap[hidden],
form#geodir-add-listing .oha-section-wrap[hidden] {
  display: none !important;
}

#geodirectory-add-post .mb-3.row,
form.geodir-add-listing-form .mb-3.row,
form#geodir-add-listing .mb-3.row {
  margin: 0 0 14px !important;
  padding: 14px;
  border: 1px solid rgba(255,255,255,.06);
  border-radius: 14px;
  background: rgba(255,255,255,.03);
}

#geodirectory-add-post .mb-3.row:hover,
form.geodir-add-listing-form .mb-3.row:hover,
form#geodir-add-listing .mb-3.row:hover {
  background: rgba(255,255,255,.045);
}

#geodirectory-add-post .mb-3.row > label,
form.geodir-add-listing-form .mb-3.row > label,
form#geodir-add-listing .mb-3.row > label {
  width: 100%;
  max-width: 100%;
  flex: 0 0 100%;
  padding: 0 0 8px !important;
  color: #fff;
  font-weight: 700;
  font-size: 15px;
  line-height: 1.35;
}

#geodirectory-add-post .mb-3.row > .col-sm-10,
#geodirectory-add-post .mb-3.row > .col-sm-12,
#geodirectory-add-post .mb-3.row > [class*="col-"],
form.geodir-add-listing-form .mb-3.row > .col-sm-10,
form.geodir-add-listing-form .mb-3.row > .col-sm-12,
form.geodir-add-listing-form .mb-3.row > [class*="col-"],
form#geodir-add-listing .mb-3.row > .col-sm-10,
form#geodir-add-listing .mb-3.row > .col-sm-12,
form#geodir-add-listing .mb-3.row > [class*="col-"] {
  width: 100%;
  max-width: 100%;
  flex: 0 0 100%;
  padding: 0 !important;
}

#geodirectory-add-post input.form-control,
#geodirectory-add-post textarea.form-control,
#geodirectory-add-post select.form-select,
#geodirectory-add-post .select2-container--bootstrap-5 .select2-selection,
#geodirectory-add-post .input-group-text,
form.geodir-add-listing-form input.form-control,
form.geodir-add-listing-form textarea.form-control,
form.geodir-add-listing-form select.form-select,
form.geodir-add-listing-form .select2-container--bootstrap-5 .select2-selection,
form.geodir-add-listing-form .input-group-text,
form#geodir-add-listing input.form-control,
form#geodir-add-listing textarea.form-control,
form#geodir-add-listing select.form-select,
form#geodir-add-listing .select2-container--bootstrap-5 .select2-selection,
form#geodir-add-listing .input-group-text {
  border-radius: 12px !important;
}

#geodirectory-add-post input.form-control,
#geodirectory-add-post textarea.form-control,
#geodirectory-add-post select.form-select,
#geodirectory-add-post .select2-container--bootstrap-5 .select2-selection,
form.geodir-add-listing-form input.form-control,
form.geodir-add-listing-form textarea.form-control,
form.geodir-add-listing-form select.form-select,
form.geodir-add-listing-form .select2-container--bootstrap-5 .select2-selection,
form#geodir-add-listing input.form-control,
form#geodir-add-listing textarea.form-control,
form#geodir-add-listing select.form-select,
form#geodir-add-listing .select2-container--bootstrap-5 .select2-selection {
  min-height: 48px;
  background: rgba(255,255,255,.94) !important;
  color: #160707 !important;
  border: 1px solid rgba(255,255,255,.16) !important;
  box-shadow: none !important;
}

#geodirectory-add-post textarea.form-control,
form.geodir-add-listing-form textarea.form-control,
form#geodir-add-listing textarea.form-control {
  min-height: 180px;
  padding-top: 12px;
}

#geodirectory-add-post .input-group-text,
form.geodir-add-listing-form .input-group-text,
form#geodir-add-listing .input-group-text {
  border: 1px solid rgba(255,255,255,.16) !important;
  background: rgba(255,255,255,.9) !important;
  color: #320000;
}

#geodirectory-add-post .form-text,
#geodirectory-add-post small.text-muted,
form.geodir-add-listing-form .form-text,
form.geodir-add-listing-form small.text-muted,
form#geodir-add-listing .form-text,
form#geodir-add-listing small.text-muted {
  color: var(--oha-muted) !important;
  margin-top: 8px;
  font-size: 12px;
  line-height: 1.45;
}

#geodirectory-add-post .text-danger,
form.geodir-add-listing-form .text-danger,
form#geodir-add-listing .text-danger {
  color: #ffad98 !important;
}

@media (min-width: 900px) {
  #geodirectory-add-post .oha-section-wrap,
  form.geodir-add-listing-form .oha-section-wrap,
  form#geodir-add-listing .oha-section-wrap {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    align-items: start;
  }

  #geodirectory-add-post .oha-span-2,
  form.geodir-add-listing-form .oha-span-2,
  form#geodir-add-listing .oha-span-2 {
    grid-column: 1 / -1;
  }
}

#geodirectory-add-post [data-rule-type="textarea"],
#geodirectory-add-post [data-rule-key="post_content"],
#geodirectory-add-post [data-rule-key="post_category"],
#geodirectory-add-post [data-rule-key="post_tags"],
#geodirectory-add-post [data-argument="address_street"],
#geodirectory-add-post [data-argument="address_map"],
#geodirectory-add-post [data-argument="latitude"],
#geodirectory-add-post [data-argument="longitude"],
#geodirectory-add-post [data-argument="images"],
#geodirectory-add-post [data-argument="video"],
#geodirectory-add-post [data-argument="special_features_of_your_haunt"],
#geodirectory-add-post [data-argument="driving_directions"],
#geodirectory-add-post [data-argument="business_hours"],
#geodirectory-add-post [data-argument="haunt_features"],
#geodirectory-add-post .geodir-add-files,
#geodirectory-add-post .geodir-taxonomy-field,
#geodirectory-add-post .plupload-upload-ui,
#geodirectory-add-post .geodir-category-select,
#geodirectory-add-post .geodir_message_error,
#geodirectory-add-post .gd-upload-wrapper,
#geodirectory-add-post #gd_placecategory_row,
#geodirectory-add-post .submit,
#geodirectory-add-post .geodir_preview_button {
  grid-column: 1 / -1;
}

#geodirectory-add-post .form-check,
#geodirectory-add-post .form-check-inline,
#geodirectory-add-post .custom-control,
form.geodir-add-listing-form .form-check,
form.geodir-add-listing-form .form-check-inline,
form.geodir-add-listing-form .custom-control,
form#geodir-add-listing .form-check,
form#geodir-add-listing .form-check-inline,
form#geodir-add-listing .custom-control {
  margin-right: 16px;
  margin-bottom: 8px;
}

#geodirectory-add-post input[type="checkbox"],
#geodirectory-add-post input[type="radio"],
form.geodir-add-listing-form input[type="checkbox"],
form.geodir-add-listing-form input[type="radio"],
form#geodir-add-listing input[type="checkbox"],
form#geodir-add-listing input[type="radio"] {
  accent-color: var(--oha-accent);
}

#geodirectory-add-post .plupload-upload-ui,
#geodirectory-add-post .geodir-form-file-upload,
#geodirectory-add-post .geodir-files-dropbox,
form.geodir-add-listing-form .plupload-upload-ui,
form.geodir-add-listing-form .geodir-form-file-upload,
form.geodir-add-listing-form .geodir-files-dropbox,
form#geodir-add-listing .plupload-upload-ui,
form#geodir-add-listing .geodir-form-file-upload,
form#geodir-add-listing .geodir-files-dropbox {
  border: 2px dashed rgba(255,125,90,.5) !important;
  background: rgba(255,255,255,.05) !important;
  border-radius: 16px !important;
  padding: 26px 18px !important;
}

#geodirectory-add-post .plupload-buttons .button,
#geodirectory-add-post .plupload-upload-ui .button,
#geodirectory-add-post .geodir-add-files .button,
#geodirectory-add-post input[type="submit"],
#geodirectory-add-post .btn,
#geodirectory-add-post .button,
form.geodir-add-listing-form .plupload-buttons .button,
form.geodir-add-listing-form .plupload-upload-ui .button,
form.geodir-add-listing-form .geodir-add-files .button,
form.geodir-add-listing-form input[type="submit"],
form.geodir-add-listing-form .btn,
form.geodir-add-listing-form .button,
form#geodir-add-listing .plupload-buttons .button,
form#geodir-add-listing .plupload-upload-ui .button,
form#geodir-add-listing .geodir-add-files .button,
form#geodir-add-listing input[type="submit"],
form#geodir-add-listing .btn,
form#geodir-add-listing .button {
  border-radius: 12px !important;
}

#geodirectory-add-post input[type="submit"],
#geodirectory-add-post .button,
#geodirectory-add-post .btn-primary,
#geodirectory-add-post .btn-outline-primary:hover,
form.geodir-add-listing-form input[type="submit"],
form.geodir-add-listing-form .button,
form.geodir-add-listing-form .btn-primary,
form.geodir-add-listing-form .btn-outline-primary:hover,
form#geodir-add-listing input[type="submit"],
form#geodir-add-listing .button,
form#geodir-add-listing .btn-primary,
form#geodir-add-listing .btn-outline-primary:hover {
  background: linear-gradient(180deg, var(--oha-accent) 0%, var(--oha-accent-2) 100%) !important;
  color: #fff !important;
  border: 0 !important;
  font-weight: 700;
  box-shadow: 0 10px 24px rgba(255,74,38,.25);
}

#geodirectory-add-post .btn-outline-primary,
#geodirectory-add-post .button.geodir_preview_button,
#geodirectory-add-post .button.preview,
#geodirectory-add-post input[name="preview"],
form.geodir-add-listing-form .btn-outline-primary,
form.geodir-add-listing-form .button.geodir_preview_button,
form.geodir-add-listing-form .button.preview,
form.geodir-add-listing-form input[name="preview"],
form#geodir-add-listing .btn-outline-primary,
form#geodir-add-listing .button.geodir_preview_button,
form#geodir-add-listing .button.preview,
form#geodir-add-listing input[name="preview"] {
  background: transparent !important;
  color: #fff !important;
  border: 1px solid rgba(255,255,255,.22) !important;
  box-shadow: none !important;
}

#geodirectory-add-post .oha-submit-wrap,
form.geodir-add-listing-form .oha-submit-wrap,
form#geodir-add-listing .oha-submit-wrap {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-top: 22px;
  padding-top: 14px;
  border-top: 1px solid rgba(255,255,255,.08);
}

#geodirectory-add-post .oha-submit-wrap > *,
form.geodir-add-listing-form .oha-submit-wrap > *,
form#geodir-add-listing .oha-submit-wrap > * {
  margin: 0 !important;
}

#geodirectory-add-post #address_map,
#geodirectory-add-post #gd_map_canvas_post,
#geodirectory-add-post .geodir-map,
#geodirectory-add-post .geodir-location-map,
form.geodir-add-listing-form #address_map,
form.geodir-add-listing-form #gd_map_canvas_post,
form.geodir-add-listing-form .geodir-map,
form.geodir-add-listing-form .geodir-location-map,
form#geodir-add-listing #address_map,
form#geodir-add-listing #gd_map_canvas_post,
form#geodir-add-listing .geodir-map,
form#geodir-add-listing .geodir-location-map {
  min-height: 320px;
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid rgba(255,255,255,.08);
}

#geodirectory-add-post .oha-location-cluster,
form.geodir-add-listing-form .oha-location-cluster,
form#geodir-add-listing .oha-location-cluster {
  grid-column: 1 / -1;
}

#geodirectory-add-post #geodir_address_map_row,
form.geodir-add-listing-form #geodir_address_map_row,
form#geodir-add-listing #geodir_address_map_row {
  padding: 14px;
}

#geodirectory-add-post #geodir_address_map_row > .mb-3.row,
form.geodir-add-listing-form #geodir_address_map_row > .mb-3.row,
form#geodir-add-listing #geodir_address_map_row > .mb-3.row {
  padding: 0;
  border: 0;
  background: transparent;
  margin: 0 0 12px !important;
}

#geodirectory-add-post #geodir_address_map_row > .mb-3.d-block,
form.geodir-add-listing-form #geodir_address_map_row > .mb-3.d-block,
form#geodir-add-listing #geodir_address_map_row > .mb-3.d-block {
  margin: 0 !important;
}

#geodirectory-add-post #address_set_address_button,
form.geodir-add-listing-form #address_set_address_button,
form#geodir-add-listing #address_set_address_button {
  width: 100%;
  max-width: 100%;
  white-space: normal;
  line-height: 1.25;
  padding: 14px 16px;
  margin: 0 !important;
  display: block;
}

#geodirectory-add-post #geodir_address_map_row .col-sm-10,
form.geodir-add-listing-form #geodir_address_map_row .col-sm-10,
form#geodir-add-listing #geodir_address_map_row .col-sm-10 {
  width: 100%;
  max-width: 100%;
  flex: 0 0 100%;
}

#geodirectory-add-post #geodir_address_map_row .col-sm-2,
form.geodir-add-listing-form #geodir_address_map_row .col-sm-2,
form#geodir-add-listing #geodir_address_map_row .col-sm-2 {
  display: none;
}

@media (min-width: 900px) {
  #geodirectory-add-post .oha-location-cluster,
  form.geodir-add-listing-form .oha-location-cluster,
  form#geodir-add-listing .oha-location-cluster {
    display: grid;
    grid-template-columns: minmax(0, .92fr) minmax(0, 1.08fr);
    gap: 14px;
    align-items: start;
  }
  #geodirectory-add-post .oha-location-cluster .oha-location-zip,
  form.geodir-add-listing-form .oha-location-cluster .oha-location-zip,
  form#geodir-add-listing .oha-location-cluster .oha-location-zip {
    grid-column: 1; grid-row: 1;
  }
  #geodirectory-add-post .oha-location-cluster .oha-location-lat,
  form.geodir-add-listing-form .oha-location-cluster .oha-location-lat,
  form#geodir-add-listing .oha-location-cluster .oha-location-lat {
    grid-column: 1; grid-row: 2;
  }
  #geodirectory-add-post .oha-location-cluster .oha-location-lng,
  form.geodir-add-listing-form .oha-location-cluster .oha-location-lng,
  form#geodir-add-listing .oha-location-cluster .oha-location-lng {
    grid-column: 1; grid-row: 3;
  }
  #geodirectory-add-post .oha-location-cluster .oha-location-map,
  form.geodir-add-listing-form .oha-location-cluster .oha-location-map,
  form#geodir-add-listing .oha-location-cluster .oha-location-map {
    grid-column: 2; grid-row: 1 / span 3; align-self: start;
  }
}

@media (max-width: 899px) {
  #geodirectory-add-post,
  form.geodir-add-listing-form,
  form#geodir-add-listing {
    margin-bottom: 24px;
  }
  #geodirectory-add-post fieldset[data-rule-type="fieldset"],
  form.geodir-add-listing-form fieldset[data-rule-type="fieldset"],
  form#geodir-add-listing fieldset[data-rule-type="fieldset"] {
    padding: 16px 18px;
  }
  #geodirectory-add-post .oha-section-wrap,
  form.geodir-add-listing-form .oha-section-wrap,
  form#geodir-add-listing .oha-section-wrap {
    padding: 14px;
  }
  #geodirectory-add-post .mb-3.row,
  form.geodir-add-listing-form .mb-3.row,
  form#geodir-add-listing .mb-3.row {
    padding: 12px;
  }
  #geodirectory-add-post input.form-control,
  #geodirectory-add-post textarea.form-control,
  #geodirectory-add-post select.form-select,
  #geodirectory-add-post .select2-container--bootstrap-5 .select2-selection,
  form.geodir-add-listing-form input.form-control,
  form.geodir-add-listing-form textarea.form-control,
  form.geodir-add-listing-form select.form-select,
  form.geodir-add-listing-form .select2-container--bootstrap-5 .select2-selection,
  form#geodir-add-listing input.form-control,
  form#geodir-add-listing textarea.form-control,
  form#geodir-add-listing select.form-select,
  form#geodir-add-listing .select2-container--bootstrap-5 .select2-selection {
    min-height: 46px;
    font-size: 16px;
  }
  #geodirectory-add-post #address_map,
  #geodirectory-add-post #gd_map_canvas_post,
  form.geodir-add-listing-form #address_map,
  form.geodir-add-listing-form #gd_map_canvas_post,
  form#geodir-add-listing #address_map,
  form#geodir-add-listing #gd_map_canvas_post {
    min-height: 240px;
  }
}
';
    }

    // ── JS ────────────────────────────────────────────────────────────────────

    private static function get_form_js() {
        return '
(function () {
  function ready(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn);
    } else {
      fn();
    }
  }

  ready(function () {
    var form = document.querySelector("#geodirectory-add-post, form.geodir-add-listing-form, form#geodir-add-listing");
    if (!form) return;

    // ---- Inject discount section (PHP-rendered, must run before fieldset processing) ----
    var discInject = document.getElementById("oha-disc-inject");
    if (discInject) {
      var nodes = Array.prototype.slice.call(discInject.childNodes);
      nodes.forEach(function (node) { form.appendChild(node); });
      if (discInject.parentNode) discInject.parentNode.removeChild(discInject);
    }

    var path   = window.location.pathname.replace(/\/+$/, "");
    var isEdit = /\/add-listing\/places\/\d+$/.test(path);
    var isAdd  = /\/add-listing(\/places)?$/.test(path) && !isEdit;

    function findFieldWrap(key) {
      var selectors = [
        "[data-argument=\"" + key + "\"]",
        "[data-field-key=\"" + key + "\"]",
        ".geodir-field-" + key,
        "#" + key,
        "[name=\"" + key + "\"]",
        "[name=\"" + key + "[]\"]"
      ];
      for (var i = 0; i < selectors.length; i++) {
        var el = form.querySelector(selectors[i]);
        if (el) {
          return el.closest(".mb-3.row, .geodir_form_row, .gd-fieldset-details, .form-group, li, p, div") || el;
        }
      }
      return null;
    }

    function hideField(key) {
      var wrap = findFieldWrap(key);
      if (wrap) wrap.classList.add("oha-hidden-field");
    }

    function showField(key) {
      var wrap = findFieldWrap(key);
      if (wrap) wrap.classList.remove("oha-hidden-field");
    }

    if (!form.querySelector(".oha-form-intro")) {
      var intro = document.createElement("div");
      intro.className = "oha-form-intro";
      intro.innerHTML = isEdit
        ? "<strong>Expand your haunt profile.</strong> You can now add advanced details, additional media, and optional operational information to make your listing more complete."
        : "Submit the basic public details first. After your listing is approved, you can return and add more advanced profile information.";
      form.insertBefore(intro, form.firstChild);
    }

    var addHiddenFields = [
      "video", "featured", "interested_featured",
      "search_keywords", "alternate_haunt_names", "map_card_summary",
      "map_card_image", "show_on_map", "open_this_weekend", "open_tonight",
      "listing_claimable", "claim_request_name", "claim_request_email",
      "claim_request_message", "claim_request_date", "claim_status",
      "number_of_scenes", "square_footage", "actor_count", "staff_count",
      "queue_capacity", "throughput_per_hour", "build_type",
      "operating_nights_per_season", "average_nightly_attendance",
      "peak_attendance", "staffing_model", "volunteer_vs_paid_actors",
      "build_months_per_year", "expansion_plans", "vendor_partnerships",
      "available_for_oha_meetings"
    ];

    if (isAdd)  addHiddenFields.forEach(hideField);
    if (isEdit) addHiddenFields.forEach(showField);

    if (isAdd)  document.body.classList.add("oha-gd-add-mode");
    if (isEdit) document.body.classList.add("oha-gd-edit-mode");

    // ---- Build section wrappers after each fieldset ----
    var fieldsets = Array.prototype.slice.call(form.querySelectorAll("fieldset[data-rule-type=\"fieldset\"]"));
    if (fieldsets.length) {
      fieldsets.forEach(function (fieldset, index) {
        if (fieldset.dataset.ohaProcessed === "1") return;
        fieldset.dataset.ohaProcessed = "1";

        var wrap = document.createElement("div");
        wrap.className = "oha-section-wrap";

        var node = fieldset.nextSibling;
        while (node && !(node.nodeType === 1 && node.matches("fieldset[data-rule-type=\"fieldset\"]"))) {
          var next = node.nextSibling;
          wrap.appendChild(node);
          node = next;
        }
        fieldset.parentNode.insertBefore(wrap, node);

        var isOpen = isEdit ? true : (index === 0);
        if (!isOpen) { wrap.hidden = true; } else { fieldset.classList.add("oha-open"); }

        fieldset.setAttribute("tabindex", "0");
        fieldset.setAttribute("role", "button");
        fieldset.setAttribute("aria-expanded", isOpen ? "true" : "false");
        fieldset.setAttribute("aria-controls", "oha-section-" + index);
        wrap.id = "oha-section-" + index;

        var toggle = function () {
          var willOpen = wrap.hidden;
          wrap.hidden = !willOpen;
          fieldset.classList.toggle("oha-open", willOpen);
          fieldset.setAttribute("aria-expanded", willOpen ? "true" : "false");
        };

        fieldset.addEventListener("click", function (e) {
          if (e.target.closest("input, textarea, select, button, a, .select2-container")) return;
          toggle();
        });
        fieldset.addEventListener("keydown", function (e) {
          if (e.key === "Enter" || e.key === " ") { e.preventDefault(); toggle(); }
        });
      });
    }

    var detailsFieldset = form.querySelector("#geodir_fieldset_details");
    if (detailsFieldset) {
      var nextWrap = detailsFieldset.nextElementSibling;
      if (nextWrap && nextWrap.classList.contains("oha-section-wrap") && !nextWrap.children.length) nextWrap.remove();
      detailsFieldset.style.display = "none";
    }

    var adminFieldset = form.querySelector("[data-rule-key=\"submitter_information\"]");
    if (adminFieldset) {
      var adminWrap = adminFieldset.nextElementSibling;
      if (adminWrap && adminWrap.classList.contains("oha-section-wrap")) {
        form.appendChild(adminFieldset);
        form.appendChild(adminWrap);
      }
    }

    var fullWidthSelectors = [
      "[data-rule-key=\"post_content\"]", "[data-rule-key=\"post_category\"]",
      "[data-rule-key=\"post_tags\"]", "[data-argument=\"address_street\"]",
      "[data-argument=\"address_map\"]", "[data-argument=\"latitude\"]",
      "[data-argument=\"longitude\"]", "[data-argument=\"driving_directions\"]",
      "[data-argument=\"business_hours\"]", "[data-argument=\"haunt_features\"]",
      "[data-argument=\"images\"]", "[data-argument=\"video\"]",
      "#gd_placecategory_row"
    ];
    fullWidthSelectors.forEach(function (selector) {
      form.querySelectorAll(selector).forEach(function (el) { el.classList.add("oha-span-2"); });
    });

    if (!form.querySelector(".oha-progress") && fieldsets.length) {
      var progress = document.createElement("div");
      progress.className = "oha-progress";
      fieldsets
        .filter(function (fs) { return fs.style.display !== "none"; })
        .forEach(function (fs, idx) {
          var title = fs.querySelector("h3");
          var pill = document.createElement("div");
          pill.className = "oha-progress-step" + ((isAdd && idx === 0) ? " is-active" : "");
          pill.textContent = title ? title.textContent.trim() : ("Section " + (idx + 1));
          pill.dataset.sectionFor = fs.id || ("oha-fs-" + idx);
          if (!fs.id) fs.id = pill.dataset.sectionFor;
          pill.addEventListener("click", function () { fs.scrollIntoView({ behavior: "smooth", block: "start" }); });
          progress.appendChild(pill);
        });
      var introEl = form.querySelector(".oha-form-intro");
      if (introEl) { introEl.insertAdjacentElement("afterend", progress); } else { form.insertBefore(progress, form.firstChild); }
    }

    var progressPills    = Array.prototype.slice.call(form.querySelectorAll(".oha-progress-step"));
    var visibleFieldsets = fieldsets.filter(function (fs) { return fs.style.display !== "none"; });

    if ("IntersectionObserver" in window && progressPills.length && visibleFieldsets.length) {
      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            progressPills.forEach(function (pill) {
              pill.classList.toggle("is-active", pill.dataset.sectionFor === entry.target.id);
            });
          }
        });
      }, { rootMargin: "-30% 0px -55% 0px", threshold: 0.1 });
      visibleFieldsets.forEach(function (fs) { observer.observe(fs); });
    }

    var zipRow = form.querySelector("[data-argument=\"address_zip\"]");
    var latRow = form.querySelector("[data-argument=\"address_latitude\"]");
    var lngRow = form.querySelector("[data-argument=\"address_longitude\"]");
    var mapRow = form.querySelector("#geodir_address_map_row");
    if (zipRow && latRow && lngRow && mapRow && !form.querySelector(".oha-location-cluster")) {
      var locationCluster = document.createElement("div");
      locationCluster.className = "oha-location-cluster oha-span-2";
      zipRow.classList.add("oha-location-zip");
      latRow.classList.add("oha-location-lat");
      lngRow.classList.add("oha-location-lng");
      mapRow.classList.add("oha-location-map");
      zipRow.parentNode.insertBefore(locationCluster, zipRow);
      locationCluster.appendChild(zipRow);
      locationCluster.appendChild(latRow);
      locationCluster.appendChild(lngRow);
      locationCluster.appendChild(mapRow);
    }

    var submitButtons = Array.prototype.slice.call(form.querySelectorAll("input[type=\"submit\"], .geodir_preview_button, .button.preview"));
    if (submitButtons.length && !form.querySelector(".oha-submit-wrap")) {
      var submitWrap = document.createElement("div");
      submitWrap.className = "oha-submit-wrap oha-span-2";
      var anchor = submitButtons[0].parentNode;
      if (anchor && anchor.parentNode) {
        anchor.parentNode.insertBefore(submitWrap, anchor);
        submitButtons.forEach(function (btn) { submitWrap.appendChild(btn); });
      }
    }
  });
})();
';
    }
}
