<?php
/**
 * Plugin Name: OHA Convention Recap
 * Description: Convention recap page with stats, highlights, and video gallery. Shortcode: [oha_convention_recap]
 * Version: 1.0.0
 * Author: OHA
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OHA_RECAP_VERSION', '1.0.0' );
define( 'OHA_RECAP_URL', plugin_dir_url( __FILE__ ) );

class OHA_Convention_Recap {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu',            [ $this, 'add_admin_menu' ] );
        add_action( 'wp_enqueue_scripts',    [ $this, 'enqueue_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_shortcode( 'oha_convention_recap', [ $this, 'render_shortcode' ] );
    }

    // -------------------------------------------------------------------------
    // Admin
    // -------------------------------------------------------------------------

    public function add_admin_menu() {
        add_options_page(
            'OHA Convention Recap',
            'OHA Convention Recap',
            'manage_options',
            'oha-convention-recap',
            [ $this, 'render_settings_page' ]
        );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( $hook !== 'settings_page_oha-convention-recap' ) return;
        wp_enqueue_script(
            'oha-recap-admin',
            OHA_RECAP_URL . 'assets/recap-admin.js',
            [],
            OHA_RECAP_VERSION,
            true
        );
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        if ( isset( $_POST['oha_recap_save'] ) && check_admin_referer( 'oha_recap_settings' ) ) {
            update_option( 'oha_recap_convention_name',     sanitize_text_field( wp_unslash( $_POST['oha_recap_convention_name']     ?? '' ) ) );
            update_option( 'oha_recap_convention_dates',    sanitize_text_field( wp_unslash( $_POST['oha_recap_convention_dates']    ?? '' ) ) );
            update_option( 'oha_recap_convention_location', sanitize_text_field( wp_unslash( $_POST['oha_recap_convention_location'] ?? '' ) ) );
            update_option( 'oha_recap_hero_text',           sanitize_textarea_field( wp_unslash( $_POST['oha_recap_hero_text']      ?? '' ) ) );
            update_option( 'oha_recap_highlights',          wp_kses_post( wp_unslash( $_POST['oha_recap_highlights']                ?? '' ) ) );
            update_option( 'oha_recap_section_stats',       sanitize_text_field( wp_unslash( $_POST['oha_recap_section_stats']     ?? 'By the Numbers' ) ) );
            update_option( 'oha_recap_section_videos',      sanitize_text_field( wp_unslash( $_POST['oha_recap_section_videos']    ?? 'Convention Highlights' ) ) );

            $stat_labels = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $_POST['oha_recap_stat_label'] ?? [] ) );
            $stat_values = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $_POST['oha_recap_stat_value'] ?? [] ) );
            $stats = [];
            foreach ( $stat_labels as $i => $label ) {
                $val = $stat_values[ $i ] ?? '';
                if ( $label || $val ) {
                    $stats[] = [ 'label' => $label, 'value' => $val ];
                }
            }
            update_option( 'oha_recap_stats', wp_json_encode( $stats ) );

            $vid_titles = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $_POST['oha_recap_vid_title'] ?? [] ) );
            $vid_urls   = array_map( 'esc_url_raw',         array_map( 'wp_unslash', $_POST['oha_recap_vid_url']   ?? [] ) );
            $videos = [];
            foreach ( $vid_urls as $i => $url ) {
                if ( $url ) {
                    $videos[] = [ 'title' => $vid_titles[ $i ] ?? '', 'url' => $url ];
                }
            }
            update_option( 'oha_recap_videos', wp_json_encode( $videos ) );

            echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
        }

        $name     = get_option( 'oha_recap_convention_name',     'OHA Convention 2025' );
        $dates    = get_option( 'oha_recap_convention_dates',    '' );
        $location = get_option( 'oha_recap_convention_location', '' );
        $hero     = get_option( 'oha_recap_hero_text',           '' );
        $highs    = get_option( 'oha_recap_highlights',          '' );
        $sec_stat = get_option( 'oha_recap_section_stats',       'By the Numbers' );
        $sec_vid  = get_option( 'oha_recap_section_videos',      'Convention Highlights' );
        $stats    = json_decode( get_option( 'oha_recap_stats',  '[]' ), true ) ?: [];
        $videos   = json_decode( get_option( 'oha_recap_videos', '[]' ), true ) ?: [];
        ?>
        <div class="wrap">
            <h1>OHA Convention Recap</h1>
            <p>Configure the content displayed by the <code>[oha_convention_recap]</code> shortcode.</p>

            <form method="post">
                <?php wp_nonce_field( 'oha_recap_settings' ); ?>

                <h2>Hero Section</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="oha_recap_convention_name">Convention Name</label></th>
                        <td>
                            <input type="text" id="oha_recap_convention_name" name="oha_recap_convention_name"
                                   value="<?php echo esc_attr( $name ); ?>" class="regular-text">
                            <p class="description">e.g. OHA Convention 2025</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="oha_recap_convention_dates">Dates</label></th>
                        <td>
                            <input type="text" id="oha_recap_convention_dates" name="oha_recap_convention_dates"
                                   value="<?php echo esc_attr( $dates ); ?>" class="regular-text">
                            <p class="description">e.g. October 4-5, 2025</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="oha_recap_convention_location">Location</label></th>
                        <td>
                            <input type="text" id="oha_recap_convention_location" name="oha_recap_convention_location"
                                   value="<?php echo esc_attr( $location ); ?>" class="regular-text">
                            <p class="description">e.g. Columbus, Ohio</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="oha_recap_hero_text">Hero Tagline</label></th>
                        <td>
                            <textarea id="oha_recap_hero_text" name="oha_recap_hero_text" rows="2" class="large-text"><?php echo esc_textarea( $hero ); ?></textarea>
                            <p class="description">Short line shown below the convention name. Leave blank to hide.</p>
                        </td>
                    </tr>
                </table>

                <h2>Stats Section</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="oha_recap_section_stats">Section Heading</label></th>
                        <td>
                            <input type="text" id="oha_recap_section_stats" name="oha_recap_section_stats"
                                   value="<?php echo esc_attr( $sec_stat ); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
                <p>Each stat card shows a large value and a label below it. Drag to reorder is not supported - remove and re-add to change order.</p>
                <div id="oha-recap-stats-list">
                    <?php foreach ( $stats as $stat ) : ?>
                    <div class="oha-recap-row" style="display:flex;gap:12px;align-items:center;margin-bottom:10px;">
                        <input type="text" name="oha_recap_stat_value[]" value="<?php echo esc_attr( $stat['value'] ); ?>" placeholder="e.g. 847" style="width:140px;">
                        <input type="text" name="oha_recap_stat_label[]" value="<?php echo esc_attr( $stat['label'] ); ?>" placeholder="e.g. Total Attendees" style="width:260px;">
                        <button type="button" class="button oha-recap-remove-row">Remove</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button" id="oha-recap-add-stat" style="margin-bottom:8px;">+ Add Stat</button>

                <h2 style="margin-top:32px;">Highlights</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="oha_recap_highlights">Highlights Text</label></th>
                        <td>
                            <textarea id="oha_recap_highlights" name="oha_recap_highlights" rows="6" class="large-text"><?php echo esc_textarea( $highs ); ?></textarea>
                            <p class="description">Optional text block shown between stats and videos. Leave blank to hide. Basic HTML (paragraphs, bold, lists) is allowed.</p>
                        </td>
                    </tr>
                </table>

                <h2>Videos</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="oha_recap_section_videos">Section Heading</label></th>
                        <td>
                            <input type="text" id="oha_recap_section_videos" name="oha_recap_section_videos"
                                   value="<?php echo esc_attr( $sec_vid ); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
                <p>For uploaded video files: upload to the WP Media Library, then paste the direct file URL here.<br>
                   For Facebook videos: paste the full Facebook video post URL (facebook.com/... or fb.watch/...).</p>
                <div id="oha-recap-videos-list">
                    <?php foreach ( $videos as $vid ) : ?>
                    <div class="oha-recap-row" style="display:flex;gap:12px;align-items:center;margin-bottom:10px;flex-wrap:wrap;">
                        <input type="text" name="oha_recap_vid_title[]" value="<?php echo esc_attr( $vid['title'] ); ?>" placeholder="Video title (optional)" style="width:200px;">
                        <input type="text" name="oha_recap_vid_url[]"   value="<?php echo esc_attr( $vid['url'] );   ?>" placeholder="Video URL or Facebook URL" style="width:420px;">
                        <button type="button" class="button oha-recap-remove-row">Remove</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button" id="oha-recap-add-video" style="margin-bottom:8px;">+ Add Video</button>

                <p class="submit" style="margin-top:32px;">
                    <input type="submit" name="oha_recap_save" class="button-primary" value="Save Settings">
                </p>
            </form>

            <hr style="margin-top:40px;">
            <h2>Usage</h2>
            <p>Add <code>[oha_convention_recap]</code> to any page to display the convention recap.</p>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Frontend assets
    // -------------------------------------------------------------------------

    public function enqueue_assets() {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'oha_convention_recap' ) ) {
            return;
        }
        wp_enqueue_style(
            'oha-convention-recap',
            OHA_RECAP_URL . 'assets/recap.css',
            [],
            OHA_RECAP_VERSION
        );
    }

    // -------------------------------------------------------------------------
    // Shortcode
    // -------------------------------------------------------------------------

    private function is_facebook_url( $url ) {
        return strpos( $url, 'facebook.com' ) !== false || strpos( $url, 'fb.watch' ) !== false;
    }

    private function render_video( $vid ) {
        $url   = $vid['url']   ?? '';
        $title = $vid['title'] ?? '';
        if ( ! $url ) return '';

        ob_start();
        echo '<div class="oha-recap-vid-item">';
        if ( $title ) {
            echo '<div class="oha-recap-vid-title">' . esc_html( $title ) . '</div>';
        }
        echo '<div class="oha-recap-vid-wrap">';
        if ( $this->is_facebook_url( $url ) ) {
            $encoded = rawurlencode( $url );
            echo '<iframe'
               . ' src="https://www.facebook.com/plugins/video.php?href=' . $encoded . '&width=640&show_text=false&height=360"'
               . ' scrolling="no" frameborder="0" allowfullscreen="true"'
               . ' allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share">'
               . '</iframe>';
        } else {
            echo '<video controls preload="metadata" src="' . esc_url( $url ) . '">'
               . '<p>Your browser does not support HTML5 video.</p>'
               . '</video>';
        }
        echo '</div>';
        echo '</div>';
        return ob_get_clean();
    }

    public function render_shortcode() {
        $name     = get_option( 'oha_recap_convention_name',     'OHA Convention 2025' );
        $dates    = get_option( 'oha_recap_convention_dates',    '' );
        $location = get_option( 'oha_recap_convention_location', '' );
        $hero     = get_option( 'oha_recap_hero_text',           '' );
        $highs    = get_option( 'oha_recap_highlights',          '' );
        $sec_stat = get_option( 'oha_recap_section_stats',       'By the Numbers' );
        $sec_vid  = get_option( 'oha_recap_section_videos',      'Convention Highlights' );
        $stats    = json_decode( get_option( 'oha_recap_stats',  '[]' ), true ) ?: [];
        $videos   = json_decode( get_option( 'oha_recap_videos', '[]' ), true ) ?: [];

        ob_start();
        ?>
        <div class="oha-recap-wrap">

            <div class="oha-recap-hero">
                <h1 class="oha-recap-hero-title"><?php echo esc_html( $name ); ?></h1>
                <?php if ( $dates || $location ) : ?>
                <div class="oha-recap-hero-meta">
                    <?php
                    echo esc_html( $dates );
                    if ( $dates && $location ) echo ' &middot; ';
                    echo esc_html( $location );
                    ?>
                </div>
                <?php endif; ?>
                <?php if ( $hero ) : ?>
                <p class="oha-recap-hero-text"><?php echo esc_html( $hero ); ?></p>
                <?php endif; ?>
            </div>

            <?php if ( $stats ) : ?>
            <div class="oha-recap-section">
                <h2 class="oha-recap-section-heading"><?php echo esc_html( $sec_stat ); ?></h2>
                <div class="oha-recap-stats">
                    <?php foreach ( $stats as $stat ) : ?>
                    <div class="oha-recap-stat-card">
                        <div class="oha-recap-stat-value"><?php echo esc_html( $stat['value'] ); ?></div>
                        <div class="oha-recap-stat-label"><?php echo esc_html( $stat['label'] ); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( $highs ) : ?>
            <div class="oha-recap-section">
                <div class="oha-recap-highlights">
                    <?php echo wp_kses_post( $highs ); ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( $videos ) : ?>
            <div class="oha-recap-section">
                <h2 class="oha-recap-section-heading"><?php echo esc_html( $sec_vid ); ?></h2>
                <div class="oha-recap-videos">
                    <?php foreach ( $videos as $vid ) : ?>
                    <?php echo $this->render_video( $vid ); ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }
}

OHA_Convention_Recap::get_instance();
