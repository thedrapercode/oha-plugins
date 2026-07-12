<?php
/**
 * Plugin Name: OHA Education Hub
 * Plugin URI:  https://ohiohaunters.org
 * Description: Education page shortcode that displays live class data from OHA Schedule Hub.
 * Version:     1.1.0
 * Author:      OHA Web Team
 * Text Domain: oha-education-hub
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OHA_EDUCATION_VERSION', '1.1.0' );
define( 'OHA_EDUCATION_PATH', plugin_dir_path( __FILE__ ) );
define( 'OHA_EDUCATION_URL',  plugin_dir_url( __FILE__ ) );

// ── Enqueue ───────────────────────────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', 'oha_education_enqueue' );
function oha_education_enqueue() {
    global $post;
    if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'oha_education' ) ) return;
    wp_enqueue_style(
        'oha-education-hub',
        OHA_EDUCATION_URL . 'assets/css/frontend.css',
        [],
        OHA_EDUCATION_VERSION
    );
    wp_enqueue_script(
        'oha-education-hub',
        OHA_EDUCATION_URL . 'assets/js/frontend.js',
        [],
        OHA_EDUCATION_VERSION,
        true
    );
}

add_action( 'admin_enqueue_scripts', 'oha_education_admin_enqueue' );
function oha_education_admin_enqueue( $hook ) {
    if ( 'settings_page_oha-education-hub' !== $hook ) return;
    wp_enqueue_script(
        'oha-education-admin',
        OHA_EDUCATION_URL . 'assets/js/admin.js',
        [ 'jquery' ],
        OHA_EDUCATION_VERSION,
        true
    );
}

// ── Admin menu ────────────────────────────────────────────────────────────────

add_action( 'admin_menu', 'oha_education_admin_menu' );
function oha_education_admin_menu() {
    add_options_page(
        'Education Hub Settings',
        'Education Hub',
        'manage_options',
        'oha-education-hub',
        'oha_education_settings_page'
    );
}

function oha_education_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    include OHA_EDUCATION_PATH . 'templates/admin-settings.php';
}

add_action( 'admin_post_oha_education_save', 'oha_education_save_settings' );
function oha_education_save_settings() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Insufficient permissions.' );
    check_admin_referer( 'oha_education_save' );

    $url_fields  = [ 'hero_btn_url', 'cta_btn_url' ];
    $json_fields = [ 'faq_items' ];
    $defaults    = oha_education_defaults();
    $saved       = [];

    foreach ( array_keys( $defaults ) as $key ) {
        if ( ! isset( $_POST[ $key ] ) ) {
            $saved[ $key ] = $defaults[ $key ];
            continue;
        }
        $raw = stripslashes( $_POST[ $key ] );
        if ( in_array( $key, $url_fields, true ) ) {
            $saved[ $key ] = esc_url_raw( $raw );
        } elseif ( in_array( $key, $json_fields, true ) ) {
            $decoded = json_decode( $raw, true );
            if ( ! is_array( $decoded ) ) $decoded = [];
            foreach ( $decoded as &$item ) {
                $item['q'] = sanitize_text_field( $item['q'] ?? '' );
                $item['a'] = sanitize_textarea_field( $item['a'] ?? '' );
            }
            unset( $item );
            $saved[ $key ] = wp_json_encode( $decoded );
        } else {
            $saved[ $key ] = sanitize_textarea_field( $raw );
        }
    }

    update_option( 'oha_education_settings', $saved );
    wp_redirect( admin_url( 'options-general.php?page=oha-education-hub&saved=1' ) );
    exit;
}

// ── Settings helpers ──────────────────────────────────────────────────────────

function oha_education_defaults() {
    return [
        'hero_heading'        => 'Elevate Your Craft',
        'hero_subheading'     => 'Learn from the best in the haunt industry. Expert-led classes covering every aspect of creating unforgettable haunted experiences.',
        'hero_btn_text'       => 'Browse Classes',
        'hero_btn_url'        => '#oha-edu-sessions',
        'value_1_icon'        => '&#127891;',
        'value_1_title'       => 'Expert Instructors',
        'value_1_desc'        => 'Learn directly from veteran haunters, professional makeup artists, actors, and technical designers.',
        'value_2_icon'        => '&#127875;',
        'value_2_title'       => 'Hands-On Workshops',
        'value_2_desc'        => 'Step away from the theory and get your hands dirty with practical, demo-driven sessions.',
        'value_3_icon'        => '&#128202;',
        'value_3_title'       => 'All Skill Levels',
        'value_3_desc'        => 'Classes range from first-time haunter basics to advanced techniques for seasoned pros.',
        'sessions_heading'      => 'Convention Classes',
        'sessions_subheading'   => 'All classes are included with your convention admission. Seating is first-come, first-served.',
        'default_track'         => 'education',
        'active_year_override'  => '',
        'faq_heading'         => 'Frequently Asked Questions',
        'faq_items'           => '[{"q":"Do I need to pre-register for classes?","a":"No pre-registration is required. All classes are first-come, first-served and included with your convention admission."},{"q":"What should I bring to classes?","a":"Each class listing notes any required materials. Beyond that, just bring yourself and a willingness to learn."},{"q":"Are classes included with my convention ticket?","a":"Yes! All education sessions are included free with convention admission."}]',
        'cta_heading'         => 'Ready to Learn?',
        'cta_subheading'      => 'Grab your OHA Convention ticket and get access to all classes.',
        'cta_btn_text'        => 'Register Now',
        'cta_btn_url'         => '/register/',
    ];
}

function oha_education_get_settings() {
    return wp_parse_args( get_option( 'oha_education_settings', [] ), oha_education_defaults() );
}

// ── Format helpers ────────────────────────────────────────────────────────────

function oha_education_format_time( $hhmm ) {
    if ( empty( $hhmm ) ) return '';
    $fmt = get_option( 'oha_schedule_time_format', '12h' );
    $ts  = strtotime( '2000-01-01 ' . $hhmm . ':00' );
    if ( ! $ts ) return esc_html( $hhmm );
    return $fmt === '24h' ? date( 'H:i', $ts ) : date( 'g:i A', $ts );
}

function oha_education_level_label( $level ) {
    $map = [
        'beginner'     => 'Beginner',
        'intermediate' => 'Intermediate',
        'advanced'     => 'Advanced',
        'all'          => 'All Levels',
    ];
    return isset( $map[ strtolower( $level ) ] ) ? $map[ strtolower( $level ) ] : ucfirst( $level );
}

// ── Shortcode ─────────────────────────────────────────────────────────────────

add_shortcode( 'oha_education', 'oha_education_shortcode' );
function oha_education_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'track'       => '',
        'year'        => '',
        'show_hero'   => '1',
        'show_values' => '1',
        'show_faq'    => '1',
        'show_cta'    => '1',
    ], $atts, 'oha_education' );

    if ( ! post_type_exists( 'oha_class' ) ) {
        return '<p class="oha-edu-error">OHA Schedule Hub plugin is required for this shortcode. Please install and activate it.</p>';
    }

    $settings = oha_education_get_settings();
    $track    = ! empty( $atts['track'] ) ? sanitize_key( $atts['track'] ) : sanitize_key( $settings['default_track'] );

    // Priority: shortcode attr > admin override > auto-detect (June 1 cutoff)
    $year = ! empty( $atts['year'] )
        ? sanitize_key( $atts['year'] )
        : sanitize_key( $settings['active_year_override'] ?? '' );

    if ( empty( $year ) ) {
        $now   = current_time( 'timestamp' );
        $month = (int) date( 'n', $now );
        $cal   = (int) date( 'Y', $now );
        $year  = (string) ( $month >= 6 ? $cal + 1 : $cal );
    }

    // Build tax query
    if ( $year ) {
        $tax_query = [
            'relation' => 'AND',
            [ 'taxonomy' => 'class_track',     'field' => 'slug', 'terms' => $track ],
            [ 'taxonomy' => 'convention_year', 'field' => 'slug', 'terms' => $year ],
        ];
    } else {
        $tax_query = [
            [ 'taxonomy' => 'class_track', 'field' => 'slug', 'terms' => $track ],
        ];
    }

    $classes = get_posts( [
        'post_type'      => 'oha_class',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'tax_query'      => $tax_query,
        'meta_key'       => '_oha_start_time',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
    ] );

    // Group by day, respecting oha_schedule_day_order
    $day_order_raw = get_option( 'oha_schedule_day_order', '' );
    $day_order     = $day_order_raw
        ? array_filter( array_map( 'trim', explode( ',', $day_order_raw ) ) )
        : [];

    $days = [];
    foreach ( $classes as $class ) {
        $day_terms = wp_get_post_terms( $class->ID, 'class_day', [ 'fields' => 'names' ] );
        $day = ( ! is_wp_error( $day_terms ) && ! empty( $day_terms ) ) ? $day_terms[0] : 'General';
        $days[ $day ][] = $class;
    }

    if ( ! empty( $day_order ) ) {
        $ordered = [];
        foreach ( $day_order as $d ) {
            if ( isset( $days[ $d ] ) ) $ordered[ $d ] = $days[ $d ];
        }
        foreach ( $days as $d => $cls ) {
            if ( ! isset( $ordered[ $d ] ) ) $ordered[ $d ] = $cls;
        }
        $days = $ordered;
    }

    // Parse FAQ items
    $faq_items = [];
    $decoded   = json_decode( $settings['faq_items'] ?? '[]', true );
    if ( is_array( $decoded ) ) $faq_items = $decoded;

    ob_start();
    include OHA_EDUCATION_PATH . 'templates/shortcode-display.php';
    return ob_get_clean();
}
