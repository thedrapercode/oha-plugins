<?php
/**
 * Plugin Name: OHA Contact Router
 * Description: Routed contact form cards for the OHA website. Each card category sends to independently configured recipients.
 * Version: 1.1.0
 * Author: OHA
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OHA_CR_VERSION', '1.1.0' );
define( 'OHA_CR_URL', plugin_dir_url( __FILE__ ) );

class OHA_Contact_Router {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu',         [ $this, 'add_admin_menu' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_shortcode( 'oha_contact_cards', [ $this, 'render_shortcode' ] );
        add_action( 'wp_ajax_oha_contact_submit',        [ $this, 'handle_ajax' ] );
        add_action( 'wp_ajax_nopriv_oha_contact_submit', [ $this, 'handle_ajax' ] );
    }

    private function categories() {
        // 'icon' is an inline SVG <path> drawn at 24x24 viewBox.
        // To replace an icon: upload an image in WP Media and paste the URL into
        // Settings > OHA Contact Router under the category's "Icon URL" field.
        // The URL overrides the built-in SVG entirely.
        return [
            'membership' => [
                'label' => 'Membership',
                'desc'  => 'Questions about joining, renewals, or member benefits',
                'icon'  => '<circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>',
            ],
            'vendors' => [
                'label' => 'Vendors',
                'desc'  => 'Booth applications, vendor info, or payment questions',
                'icon'  => '<rect x="3" y="10" width="18" height="11" rx="1"/><path d="M3 10l2-7h14l2 7"/><line x1="12" y1="10" x2="12" y2="21"/><line x1="7.5" y1="10" x2="7.5" y2="21"/><line x1="16.5" y1="10" x2="16.5" y2="21"/>',
            ],
            'convention' => [
                'label' => 'Convention',
                'desc'  => 'Event information, schedules, or general convention questions',
                'icon'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
            ],
            'tickets' => [
                'label' => 'Tickets',
                'desc'  => 'Ticket purchases, issues, or questions - handled by our venue',
                'icon'  => '<path d="M15 5H9a2 2 0 0 0-2 2v2a2 2 0 0 1 0 4v2a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-2a2 2 0 0 1 0-4V7a2 2 0 0 0-2-2z"/><line x1="12" y1="5" x2="12" y2="8"/><line x1="12" y1="11" x2="12" y2="13"/><line x1="12" y1="16" x2="12" y2="19"/>',
            ],
            'sponsorship' => [
                'label' => 'Sponsorship',
                'desc'  => 'Partner with OHA or sponsor the convention',
                'icon'  => '<path d="M12 21C12 21 4 15.5 4 9a4 4 0 0 1 8 0 4 4 0 0 1 8 0c0 6.5-8 12-8 12z"/>',
            ],
            'website' => [
                'label' => 'Website and Account',
                'desc'  => 'Login issues, listing updates, or technical help',
                'icon'  => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
            ],
            'media' => [
                'label' => 'Media and Press',
                'desc'  => 'Press inquiries, interviews, or media kits',
                'icon'  => '<rect x="4" y="4" width="16" height="16" rx="2"/><line x1="8" y1="9" x2="16" y2="9"/><line x1="8" y1="13" x2="14" y2="13"/><line x1="8" y1="17" x2="11" y2="17"/>',
            ],
        ];
    }

    private function render_icon( $slug, $cat ) {
        $url = trim( get_option( 'oha_cr_icon_' . $slug, '' ) );
        if ( $url ) {
            return '<img src="' . esc_url( $url ) . '" alt="" width="22" height="22" loading="lazy">';
        }
        return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
             . $cat['icon']
             . '</svg>';
    }

    // -------------------------------------------------------------------------
    // Admin
    // -------------------------------------------------------------------------

    public function add_admin_menu() {
        add_options_page(
            'OHA Contact Router',
            'OHA Contact Router',
            'manage_options',
            'oha-contact-router',
            [ $this, 'render_settings_page' ]
        );
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        if ( isset( $_POST['oha_cr_save'] ) && check_admin_referer( 'oha_cr_settings' ) ) {
            update_option( 'oha_cr_fallback_email', sanitize_text_field( wp_unslash( $_POST['oha_cr_fallback_email'] ?? '' ) ) );
            update_option( 'oha_cr_bcc_email',      sanitize_text_field( wp_unslash( $_POST['oha_cr_bcc_email']      ?? '' ) ) );
            foreach ( array_keys( $this->categories() ) as $slug ) {
                update_option( 'oha_cr_email_' . $slug, sanitize_text_field( wp_unslash( $_POST[ 'oha_cr_email_' . $slug ] ?? '' ) ) );
                update_option( 'oha_cr_icon_'  . $slug, esc_url_raw( wp_unslash( $_POST[ 'oha_cr_icon_'  . $slug ] ?? '' ) ) );
            }
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
        }

        $fallback = get_option( 'oha_cr_fallback_email', 'board@ohiohaunters.com' );
        $bcc      = get_option( 'oha_cr_bcc_email',      'webmasterohiohaunters@gmail.com' );
        $cats     = $this->categories();
        ?>
        <div class="wrap">
            <h1>OHA Contact Router</h1>
            <p>Configure which email addresses receive each type of contact submission. Separate multiple addresses with commas. Leave a category blank to use the fallback address.</p>

            <form method="post">
                <?php wp_nonce_field( 'oha_cr_settings' ); ?>

                <h2 style="margin-top:24px;">General</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="oha_cr_fallback_email">Fallback Email</label></th>
                        <td>
                            <input type="text" id="oha_cr_fallback_email" name="oha_cr_fallback_email"
                                   value="<?php echo esc_attr( $fallback ); ?>" class="regular-text">
                            <p class="description">Used for any category that has no addresses configured.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="oha_cr_bcc_email">BCC All Submissions To</label></th>
                        <td>
                            <input type="text" id="oha_cr_bcc_email" name="oha_cr_bcc_email"
                                   value="<?php echo esc_attr( $bcc ); ?>" class="regular-text">
                            <p class="description">Every submission is BCC'd here regardless of category. Leave blank to disable.</p>
                        </td>
                    </tr>
                </table>

                <h2 style="margin-top:32px;">Category Routing</h2>
                <p>For each category: set the recipient emails and optionally upload a custom icon image via the WP Media Library and paste its URL here. Leave Icon URL blank to use the built-in icon.</p>
                <table class="form-table" role="presentation">
                    <?php foreach ( $cats as $slug => $cat ) :
                        $email_key = 'oha_cr_email_' . $slug;
                        $icon_key  = 'oha_cr_icon_'  . $slug;
                    ?>
                    <tr>
                        <th scope="row" style="padding-top:20px;">
                            <strong><?php echo esc_html( $cat['label'] ); ?></strong>
                        </th>
                        <td>
                            <label for="<?php echo esc_attr( $email_key ); ?>" style="display:block;margin-bottom:4px;font-weight:600;">Recipient Emails</label>
                            <input type="text" id="<?php echo esc_attr( $email_key ); ?>" name="<?php echo esc_attr( $email_key ); ?>"
                                   value="<?php echo esc_attr( get_option( $email_key, '' ) ); ?>"
                                   class="regular-text" placeholder="Leave blank to use fallback">
                            <p class="description" style="margin-bottom:12px;"><?php echo esc_html( $cat['desc'] ); ?></p>

                            <label for="<?php echo esc_attr( $icon_key ); ?>" style="display:block;margin-bottom:4px;font-weight:600;">Icon URL <span style="font-weight:400;">(optional — overrides built-in icon)</span></label>
                            <input type="text" id="<?php echo esc_attr( $icon_key ); ?>" name="<?php echo esc_attr( $icon_key ); ?>"
                                   value="<?php echo esc_attr( get_option( $icon_key, '' ) ); ?>"
                                   class="regular-text" placeholder="https://...">
                            <?php $icon_val = get_option( $icon_key, '' ); if ( $icon_val ) : ?>
                            <img src="<?php echo esc_url( $icon_val ); ?>" style="display:block;margin-top:6px;width:32px;height:32px;object-fit:contain;" alt="">
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>

                <p class="submit">
                    <input type="submit" name="oha_cr_save" class="button-primary" value="Save Settings">
                </p>
            </form>

            <hr style="margin-top:40px;">
            <h2>Usage</h2>
            <p>Add <code>[oha_contact_cards]</code> to any page or post to display the contact card grid.</p>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Frontend
    // -------------------------------------------------------------------------

    public function enqueue_assets() {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'oha_contact_cards' ) ) {
            return;
        }
        wp_enqueue_style(
            'oha-contact-router',
            OHA_CR_URL . 'assets/contact-router.css',
            [],
            OHA_CR_VERSION
        );
        wp_enqueue_script(
            'oha-contact-router',
            OHA_CR_URL . 'assets/contact-router.js',
            [],
            OHA_CR_VERSION,
            true
        );
        wp_localize_script( 'oha-contact-router', 'ohaCR', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'oha_cr_submit' ),
        ] );
    }

    public function render_shortcode() {
        $cats = $this->categories();
        ob_start();
        ?>
        <div class="oha-cr-wrap">

            <div class="oha-cr-cards">
                <?php foreach ( $cats as $slug => $cat ) : ?>
                <button class="oha-cr-card" data-category="<?php echo esc_attr( $slug ); ?>"
                        data-label="<?php echo esc_attr( $cat['label'] ); ?>"
                        type="button" aria-expanded="false">
                    <div class="oha-cr-card-icon" aria-hidden="true">
                        <?php echo $this->render_icon( $slug, $cat ); // Safe: SVG is hardcoded; URL goes through esc_url ?>
                    </div>
                    <div class="oha-cr-card-body">
                        <div class="oha-cr-card-title"><?php echo esc_html( $cat['label'] ); ?></div>
                        <div class="oha-cr-card-desc"><?php echo esc_html( $cat['desc'] ); ?></div>
                    </div>
                    <div class="oha-cr-card-chevron" aria-hidden="true">
                        <svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6 3L11 8L6 13" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </button>
                <?php endforeach; ?>
            </div>

            <div class="oha-cr-panel" id="oha-cr-panel" aria-live="polite">
                <div class="oha-cr-panel-header">
                    <span class="oha-cr-panel-title" id="oha-cr-panel-title"></span>
                    <button class="oha-cr-panel-close" id="oha-cr-panel-close" type="button" aria-label="Close form">
                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2 2L16 16M16 2L2 16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
                <form class="oha-cr-form" id="oha-cr-form" novalidate>
                    <input type="hidden" name="category" id="oha-cr-category">
                    <div class="oha-cr-field">
                        <label for="oha-cr-name">Your Name <span class="oha-cr-required" aria-hidden="true">*</span></label>
                        <input type="text" id="oha-cr-name" name="name" required autocomplete="name">
                    </div>
                    <div class="oha-cr-field">
                        <label for="oha-cr-email">Your Email <span class="oha-cr-required" aria-hidden="true">*</span></label>
                        <input type="email" id="oha-cr-email" name="email" required autocomplete="email">
                    </div>
                    <div class="oha-cr-field">
                        <label for="oha-cr-message">Message <span class="oha-cr-required" aria-hidden="true">*</span></label>
                        <textarea id="oha-cr-message" name="message" rows="5" required></textarea>
                    </div>
                    <div class="oha-cr-form-footer">
                        <button type="submit" class="oha-cr-submit" id="oha-cr-submit">Send Message</button>
                        <span class="oha-cr-sending" id="oha-cr-sending">Sending...</span>
                    </div>
                    <div class="oha-cr-result" id="oha-cr-result"></div>
                </form>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // AJAX
    // -------------------------------------------------------------------------

    public function handle_ajax() {
        check_ajax_referer( 'oha_cr_submit', 'nonce' );

        $name     = sanitize_text_field(     wp_unslash( $_POST['name']     ?? '' ) );
        $email    = sanitize_email(          wp_unslash( $_POST['email']    ?? '' ) );
        $message  = sanitize_textarea_field( wp_unslash( $_POST['message']  ?? '' ) );
        $category = sanitize_key(                        $_POST['category'] ?? '' );

        if ( ! $name || ! $email || ! $message || ! $category ) {
            wp_send_json_error( [ 'message' => 'All fields are required.' ] );
        }

        if ( ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => 'Please enter a valid email address.' ] );
        }

        $cats      = $this->categories();
        $cat_label = $cats[ $category ]['label'] ?? ucfirst( $category );

        $fallback   = get_option( 'oha_cr_fallback_email', 'board@ohiohaunters.com' );
        $raw        = get_option( 'oha_cr_email_' . $category, '' );
        $recipients = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
        if ( empty( $recipients ) ) {
            $recipients = array_filter( array_map( 'trim', explode( ',', $fallback ) ) );
        }

        $bcc = trim( get_option( 'oha_cr_bcc_email', 'webmasterohiohaunters@gmail.com' ) );

        $subject = '[OHA Contact] ' . $cat_label . ' - ' . $name;
        $body    = "Category: {$cat_label}\r\nName: {$name}\r\nEmail: {$email}\r\n\r\n{$message}";

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: ' . $name . ' <' . $email . '>',
        ];
        if ( $bcc ) {
            $headers[] = 'Bcc: ' . $bcc;
        }

        $sent = wp_mail( $recipients, $subject, $body, $headers );

        if ( $sent ) {
            $confirm_subject = 'We received your message - Ohio Haunters Association';
            $confirm_body    = "Hello {$name},\r\n\r\n"
                             . "Thank you for reaching out to the Ohio Haunters Association. This is a confirmation that we received your message under the category \"{$cat_label}\".\r\n\r\n"
                             . "A board member will follow up with you as soon as possible. If your matter is urgent, you may also reach us at board@ohiohaunters.com.\r\n\r\n"
                             . "Ohio Haunters Association\r\n"
                             . "https://ohiohaunters.com";
            $confirm_headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
            wp_mail( $email, $confirm_subject, $confirm_body, $confirm_headers );

            wp_send_json_success( [ 'message' => 'Your message has been sent. A confirmation has been emailed to you.' ] );
        } else {
            wp_send_json_error( [ 'message' => 'There was a problem sending your message. Please try again or contact us directly at board@ohiohaunters.com.' ] );
        }
    }
}

OHA_Contact_Router::get_instance();
