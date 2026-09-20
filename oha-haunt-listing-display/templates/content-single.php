<?php
/**
 * Main single listing template for OHA Haunt Listing Display.
 * Outputs the full .oha-listing-wrap structure.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

global $post, $gd_post;
$listing_id = $post->ID;

// ── Settings from admin ───────────────────────────────────────────────────────
$settings = OHA_HLD_Admin::get_all();

// ── Core GeoDirectory data ────────────────────────────────────────────────────
// Uses geodir_get_post_info() which JOINs wp_posts + wp_geodir_gd_place_detail.
// DO NOT use get_post_meta() directly for GD custom fields - they live in the
// detail table and are NOT in wp_postmeta.
if ( function_exists( 'geodir_get_post_info' ) ) {
    $gd = geodir_get_post_info( $listing_id );
} else {
    $gd = new stdClass();
}

// Ensure $gd_post global is set so GeoDirectory shortcodes (e.g. [gd_report_post]) work.
if ( empty( $gd_post ) || empty( $gd_post->ID ) || $gd_post->ID !== $listing_id ) {
    $gd_post = $gd;
}

$address  = isset( $gd->street )    ? $gd->street    : '';
$city     = isset( $gd->city )      ? $gd->city      : '';
$state    = isset( $gd->region )    ? $gd->region    : '';
$zip      = isset( $gd->zip )       ? $gd->zip       : '';
$phone    = ( isset( $gd->attraction_phone_number ) && $gd->attraction_phone_number )
    ? $gd->attraction_phone_number
    : ( isset( $gd->phone ) ? $gd->phone : '' );
$email    = isset( $gd->email )     ? trim( (string) $gd->email )   : '';
$website  = ( isset( $gd->attraction_website ) && $gd->attraction_website )
    ? $gd->attraction_website
    : ( isset( $gd->website ) ? $gd->website : '' );
$facebook = isset( $gd->facebook )  ? $gd->facebook  : '';
$lat      = isset( $gd->latitude )  ? (float) $gd->latitude  : 0;
$lng      = isset( $gd->longitude ) ? (float) $gd->longitude : 0;
$featured = isset( $gd->featured )  ? $gd->featured  : 0;

// ── Business hours (GD built-in field) ───────────────────────────────────────
// Stored as a JSON schema string in the detail table's 'business_hours' column.
// geodir_get_business_hours() returns:
//   ['days' => ['Monday' => ['today'=>0/1,'closed'=>0/1,'open'=>0/1,'day_short'=>'Mon',
//                            'slots'=>[['range'=>'9:00 am - 5:00 pm','open'=>0/1,...]]]],
//    'extra' => [...]]
// Keys are full day names (Monday…Sunday), slots already formatted per WP time_format.
$business_hours_raw = isset( $gd->business_hours ) ? $gd->business_hours : '';
$business_hours     = [];
if ( $business_hours_raw && function_exists( 'geodir_get_business_hours' ) ) {
    $bh_parsed = geodir_get_business_hours( $business_hours_raw );
    if ( ! empty( $bh_parsed['days'] ) ) {
        $business_hours = $bh_parsed['days']; // keyed by full day name
    }
}

// ── Standard WP data ──────────────────────────────────────────────────────────
$title   = get_the_title();
$content = apply_filters( 'the_content', get_the_content() );

// ── Track A custom fields (already in GeoDirectory Place Fields) ──────────────
$features_raw    = isset( $gd->haunt_features )   ? $gd->haunt_features   : '';
$admission_type  = isset( $gd->admission_type )   ? $gd->admission_type   : '';
$admission_price = isset( $gd->admission_price )  ? $gd->admission_price  : '';
$haunt_season    = isset( $gd->haunt_season )     ? $gd->haunt_season     : '';
$typical_hours   = isset( $gd->typical_hours )    ? $gd->typical_hours    : '';
$age_restriction = isset( $gd->age_restriction )  ? $gd->age_restriction  : '';

// multiselect fields use comma separator (audit confirmed)
$features = $features_raw ? array_map( 'trim', explode( ',', $features_raw ) ) : [];

// ── Track B: New Place Fields (defensive - degrade gracefully when not yet created) ─
// All read from $gd (detail table). Empty string when field doesn't exist.

// Priority 1
$haunt_type       = isset( $gd->haunt_type )       ? (string) $gd->haunt_type       : '';
$fear_level       = isset( $gd->fear_level )        ? (string) $gd->fear_level       : '';
$open_this_season = isset( $gd->open_this_season )  ? (string) $gd->open_this_season : '';
$indoor_outdoor   = isset( $gd->indoor_outdoor )    ? (string) $gd->indoor_outdoor   : '';

// Priority 2
$recommended_raw  = isset( $gd->recommended_audience ) ? (string) $gd->recommended_audience : '';
$recommended_aud  = $recommended_raw ? array_map( 'trim', explode( ',', $recommended_raw ) ) : [];
$accessibility_raw= isset( $gd->accessibility )    ? (string) $gd->accessibility    : '';
$accessibility    = $accessibility_raw ? array_map( 'trim', explode( ',', $accessibility_raw ) ) : [];
$parking_type     = isset( $gd->parking_type )     ? (string) $gd->parking_type     : '';
$wait_time        = isset( $gd->wait_time )        ? (string) $gd->wait_time        : '';
$experience_length= isset( $gd->experience_length )? (string) $gd->experience_length: '';
$actor_interaction= isset( $gd->actor_interaction )? (string) $gd->actor_interaction: '';

// Priority 3
$haunt_theme      = isset( $gd->haunt_theme )      ? (string) $gd->haunt_theme      : '';
$haunt_storyline  = isset( $gd->haunt_storyline )  ? (string) $gd->haunt_storyline  : '';
$years_running    = isset( $gd->years_running )    ? (string) $gd->years_running    : '';
$scene_count      = isset( $gd->scene_count )      ? (string) $gd->scene_count      : '';
$actor_count      = isset( $gd->actor_count )      ? (string) $gd->actor_count      : '';
$animatronic_count= isset( $gd->animatronic_count )? (string) $gd->animatronic_count: '';
$visitor_tips     = isset( $gd->visitor_tips )     ? (string) $gd->visitor_tips     : '';
$recruiting       = isset( $gd->recruiting_actors )? (string) $gd->recruiting_actors: '';
$recruitment_link = isset( $gd->recruitment_link ) ? (string) $gd->recruitment_link : '';
$special_effects_raw = isset( $gd->special_effects ) ? (string) $gd->special_effects : '';
$special_effects  = $special_effects_raw ? array_map( 'trim', explode( ',', $special_effects_raw ) ) : [];
$has_stats        = $years_running || $scene_count || $actor_count || $animatronic_count;

// Social: Track B additions
$instagram        = isset( $gd->instagram )        ? (string) $gd->instagram        : '';
$tiktok           = isset( $gd->tiktok )           ? (string) $gd->tiktok           : '';
$youtube_channel  = isset( $gd->youtube_channel )  ? (string) $gd->youtube_channel  : '';

// ── Home Haunt Hub meta fallbacks ─────────────────────────────────────────────
// Hub-submitted listings (oha-home-haunt-hub plugin) store their fields in
// wp_postmeta (oha_hh_* keys) instead of GeoDirectory's detail table.
// When GD fields are empty, fill from Hub meta.  Hub-exclusive variables
// ($hhh_*) are always initialised so partials never get undefined-variable notices.
$hhh_operating_nights    = [];
$hhh_public_visitors     = '';
$hhh_visitor_range_label = '';
$hhh_builders_notes      = '';

if ( get_post_meta( $listing_id, 'oha_hh_submitted_date', true ) ) {

    // Fear level: Hub stores slugs ('family','moderate','intense')
    // Map to display labels so hero pills + sidebar row work correctly.
    if ( ! $fear_level ) {
        $hhh_raw = get_post_meta( $listing_id, 'oha_hh_scare_level', true );
        $hhh_map = [ 'family' => 'Family Friendly', 'moderate' => 'Moderate', 'intense' => 'Intense' ];
        $fear_level = isset( $hhh_map[ $hhh_raw ] ) ? $hhh_map[ $hhh_raw ] : '';
    }

    // Age restriction
    if ( ! $age_restriction ) {
        $hhh_raw         = (string) get_post_meta( $listing_id, 'oha_hh_age_rec', true );
        $age_restriction = $hhh_raw === 'all' ? 'All Ages' : $hhh_raw;
    }

    // Years running + scene count (stats block)
    if ( ! $years_running ) {
        $years_running = (string) get_post_meta( $listing_id, 'oha_hh_years_running', true );
    }
    if ( ! $scene_count ) {
        $scene_count = (string) get_post_meta( $listing_id, 'oha_hh_scene_count', true );
    }

    // Social: facebook
    if ( ! $facebook ) {
        $facebook = (string) get_post_meta( $listing_id, 'oha_hh_facebook', true );
    }

    // Email (Hub stores contact email in oha_hh_email, never in GD detail table)
    if ( ! $email ) {
        $email = (string) get_post_meta( $listing_id, 'oha_hh_email', true );
    }

    // Attraction styles → feature pills (serialized PHP array → label strings)
    if ( empty( $features ) ) {
        $hhh_raw = get_post_meta( $listing_id, 'oha_hh_style', true );
        if ( is_array( $hhh_raw ) ) {
            $hhh_map  = [
                'yard_display'  => 'Yard Display',  'walkthrough'   => 'Walkthrough',
                'multi_room'    => 'Multi-Room',    'themed_scenes' => 'Themed Scenes',
                'extreme'       => 'Extreme',       'family'        => 'Family',
                'interactive'   => 'Interactive',   'haunted_trail' => 'Haunted Trail',
            ];
            $features = array_values( array_filter( array_map(
                function( $v ) use ( $hhh_map ) { return isset( $hhh_map[ $v ] ) ? $hhh_map[ $v ] : ''; },
                $hhh_raw
            ) ) );
        }
    }

    // Operating nights (Hub-exclusive → pills merged into features section)
    $hhh_raw = get_post_meta( $listing_id, 'oha_hh_operating_nights', true );
    if ( is_array( $hhh_raw ) ) {
        $hhh_map = [
            'early_oct'      => 'Early October',  'mid_oct'        => 'Mid October',
            'late_oct'       => 'Late October',   'full_season'    => 'Full Season',
            'by_appointment' => 'By Appointment',
        ];
        $hhh_operating_nights = array_values( array_filter( array_map(
            function( $v ) use ( $hhh_map ) { return isset( $hhh_map[ $v ] ) ? $hhh_map[ $v ] : ''; },
            $hhh_raw
        ) ) );
    }

    // Public visitors (Hub-exclusive → info card row)
    $hhh_raw             = (string) get_post_meta( $listing_id, 'oha_hh_public_visitors', true );
    $hhh_pv_map          = [ 'yes' => 'Open to Public', 'neighborhood' => 'Neighborhood Only', 'no' => 'Private' ];
    $hhh_public_visitors = isset( $hhh_pv_map[ $hhh_raw ] ) ? $hhh_pv_map[ $hhh_raw ] : '';

    // Estimated visitors per year (Hub-exclusive → info card row)
    $hhh_raw             = (string) get_post_meta( $listing_id, 'oha_hh_visitor_range', true );
    $hhh_vr_map          = [ 'under50' => 'Under 50', '50-200' => '50–200', '200-500' => '200–500', '500-1k' => '500–1,000', '1k+' => '1,000+' ];
    $hhh_visitor_range_label = isset( $hhh_vr_map[ $hhh_raw ] ) ? $hhh_vr_map[ $hhh_raw ] . ' visitors/yr' : '';

    // Builder's notes (Hub-exclusive → collapsible like visitor tips)
    $hhh_builders_notes = (string) get_post_meta( $listing_id, 'oha_hh_builders_notes', true );

    // Recalculate has_stats since years_running / scene_count may have been populated above.
    $has_stats = $years_running || $scene_count || $actor_count || $animatronic_count;
}

// ── GeoDirectory video field (YouTube/Vimeo URL or embed code) ───────────
// GD stores this as plain text in the 'video' column of the detail table.
// The field accepts: YouTube URL, Vimeo URL, any oEmbed provider URL, or
// raw <iframe> embed code. Strategy:
//   1. Plain URL → try wp_oembed_get() (direct oEmbed API, most reliable).
//   2. Plain URL fallback → $wp_embed->autoembed() with post context set.
//   3. Contains HTML tags → treat as raw embed code via wp_kses_post().
$gd_video    = isset( $gd->video ) ? trim( (string) $gd->video ) : '';
$video_embed = '';
if ( $gd_video ) {
    $is_plain_url = preg_match( '|^https?://\S+$|i', $gd_video ) && strpos( $gd_video, '<' ) === false;

    if ( $is_plain_url ) {
        // Primary: direct oEmbed API call — works for YouTube, Vimeo, and all
        // registered oEmbed providers without needing the URL on its own line.
        $oembed = wp_oembed_get( $gd_video, [ 'width' => 1280 ] );
        if ( $oembed ) {
            $video_embed = $oembed;
        }
    }

    if ( empty( $video_embed ) && $is_plain_url ) {
        // Secondary: autoembed with post ID set for cache keying.
        global $wp_embed;
        $wp_embed->post_ID = $listing_id;
        $result = $wp_embed->autoembed( $gd_video );
        if ( $result !== $gd_video ) {
            $video_embed = $result;
        }
    }

    if ( empty( $video_embed ) && strpos( $gd_video, '<' ) !== false ) {
        // Tertiary: raw HTML embed code (e.g. <iframe> pasted by owner).
        $video_embed = wp_kses_post( $gd_video );
    }
}

// ── Ratings ───────────────────────────────────────────────────────────────────
$rating       = function_exists( 'geodir_get_post_rating' )        ? (float) geodir_get_post_rating( $listing_id )        : 0;
$rating_count = function_exists( 'geodir_get_review_count_total' ) ? (int)   geodir_get_review_count_total( $listing_id ) : 0;

$full_stars  = floor( $rating );
$half_star   = ( $rating - $full_stars ) >= 0.5;
$empty_stars = 5 - $full_stars - ( $half_star ? 1 : 0 );

// ── Images ────────────────────────────────────────────────────────────────────
$images = $settings['enable_gallery'] && function_exists( 'geodir_get_images' ) ? geodir_get_images( $listing_id ) : [];
$hero_img = '';

if ( ! empty( $images ) && function_exists( 'geodir_get_image_src' ) ) {
    $hero_img = geodir_get_image_src( $images[0], 'large' );
}

// Gallery: show all images including first (hero bg still uses images[0] separately).
// This ensures single-photo listings also display the gallery section.
$gallery_imgs  = ! empty( $images ) ? array_slice( $images, 0, 5 ) : [];
$gallery_extra = max( 0, count( $images ) - 5 );

// ── Categories & tags ─────────────────────────────────────────────────────────
$categories = get_the_terms( $listing_id, 'gd_placecategory' );
$tags        = get_the_terms( $listing_id, 'gd_place_tags' );

// ── Author / claim status ─────────────────────────────────────────────────────
$author_id   = (int) get_post_field( 'post_author', $listing_id );
$claimed_val = get_post_meta( $listing_id, '_gd_claimed', true );

// Admin can manually mark a listing as claimed/owner-managed via meta box,
// overriding the author-based unclaimed check (e.g. pre-plugin listings).
$override_claimed     = get_post_meta( $listing_id, '_oha_hld_override_claimed', true ) === '1';

// "Display unclaimed" = OHB bulk-created listing not yet claimed by an owner,
// AND not manually overridden by an admin.
$is_display_unclaimed = ( $author_id === 3 && ! $claimed_val && ! $override_claimed );

// ── Discount bar (from oha-discounts-frontend plugin meta) ────────────────────
$disc_active       = get_post_meta( $listing_id, '_oha_disc_active',       true ) === '1';
$disc_rate         = get_post_meta( $listing_id, '_oha_disc_rate',         true );
$disc_message      = get_post_meta( $listing_id, '_oha_disc_message',      true );
$disc_start        = get_post_meta( $listing_id, '_oha_disc_start',        true );
$disc_end          = get_post_meta( $listing_id, '_oha_disc_end',          true );
$disc_promo_code   = get_post_meta( $listing_id, '_oha_disc_promo_code',   true );
$disc_private_link = get_post_meta( $listing_id, '_oha_disc_private_link', true );
$today        = date( 'Y-m-d' );
$disc_date_ok = true;
if ( ! empty( $disc_start ) && $today < $disc_start ) $disc_date_ok = false;
if ( ! empty( $disc_end )   && $today > $disc_end   ) $disc_date_ok = false;
$show_discount    = $disc_active && $disc_date_ok && ( $disc_rate || $disc_message );
$has_members_only = ! empty( $disc_promo_code ) || ! empty( $disc_private_link );

// OHA paid member check — oha_member_id is the canonical field present on all active members
$viewer_id     = get_current_user_id();
$is_oha_member = $viewer_id && ! empty( get_user_meta( $viewer_id, 'oha_member_id', true ) );

// Teaser link for non-members: basic members (listing owners) upgrade at /my-membership/,
// everyone else (not logged in) registers at /register/
$viewer_is_basic = $viewer_id && get_user_meta( $viewer_id, '_uwp_register_form_id', true ) === '4';
$disc_join_url   = $viewer_is_basic
    ? 'https://ohiohaunters.com/my-membership/'
    : 'https://ohiohaunters.com/register/';
$disc_join_label = $viewer_is_basic ? 'Upgrade Membership' : 'Register to Join OHA';

// ── Full address string ───────────────────────────────────────────────────────
$address_parts = array_filter( [ $city, $state, $zip ] );
$location_str  = implode( ', ', $address_parts );

?>
<div class="oha-listing-wrap <?php echo $is_display_unclaimed ? 'oha-unclaimed' : 'oha-claimed'; ?>">

    <?php include OHA_HLD_PATH . 'templates/partials/hero.php'; ?>

    <?php if ( $show_discount || ( $has_members_only && $disc_active && $disc_date_ok ) ) : ?>
    <!-- ── OHA Discount notice ─────────────────────────────────────────────── -->
    <div class="oha-discount-notice" role="complementary" aria-label="OHA Member Discount">
        <div class="oha-discount-notice__card">

            <div class="oha-discount-notice__main">
                <span class="oha-discount-notice__label">OHA Member Discount</span>
                <?php if ( $disc_rate ) : ?>
                    <strong class="oha-discount-notice__rate"><?php echo esc_html( $disc_rate ); ?></strong>
                <?php endif; ?>
                <?php if ( $disc_message ) : ?>
                    <span class="oha-discount-notice__msg"><?php echo esc_html( $disc_message ); ?></span>
                <?php endif; ?>
            </div>

            <?php if ( $has_members_only ) : ?>
            <div class="oha-discount-notice__members">
                <div class="oha-discount-notice__members-header">
                    <svg class="oha-discount-notice__lock" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                    <span class="oha-discount-notice__members-label">Member Access</span>
                </div>
                <?php if ( $is_oha_member ) : ?>
                    <?php if ( $disc_promo_code ) : ?>
                        <span class="oha-discount-notice__promo-wrap">
                            Promo Code:
                            <span class="oha-discount-notice__promo-code" data-code="<?php echo esc_attr( $disc_promo_code ); ?>"><?php echo esc_html( $disc_promo_code ); ?></span>
                            <button type="button" class="oha-discount-notice__copy" aria-label="Copy promo code">Copy</button>
                        </span>
                    <?php endif; ?>
                    <?php if ( $disc_private_link ) : ?>
                        <a class="oha-discount-notice__private-link" href="<?php echo esc_url( $disc_private_link ); ?>" target="_blank" rel="noopener noreferrer">Members Link</a>
                    <?php endif; ?>
                <?php else : ?>
                    <p class="oha-discount-notice__teaser">OHA members unlock an exclusive promo code for this haunt.</p>
                    <a class="oha-discount-notice__join-link" href="<?php echo esc_url( $disc_join_url ); ?>"><?php echo esc_html( $disc_join_label ); ?></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
    <?php endif; ?>

    <div class="oha-listing-body">
        <?php include OHA_HLD_PATH . 'templates/partials/main-content.php'; ?>
        <?php include OHA_HLD_PATH . 'templates/partials/sidebar.php'; ?>
    </div>

    <?php include OHA_HLD_PATH . 'templates/partials/search-bar.php'; ?>

    <?php if ( $settings['enable_nearby'] ) : ?>
        <?php include OHA_HLD_PATH . 'templates/partials/nearby.php'; ?>
    <?php endif; ?>

    <?php if ( $settings['enable_mobile_bar'] ) : ?>
    <!-- Mobile sticky action bar (hidden on desktop via CSS) -->
    <div class="oha-mobile-bar">
        <?php if ( $lat && $lng ) : ?>
            <a class="oha-mobile-bar__btn oha-mobile-bar__btn--directions"
               href="https://www.google.com/maps/dir/?api=1&destination=<?php echo esc_attr( $lat . ',' . $lng ); ?>"
               target="_blank" rel="noopener noreferrer">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M21.71 11.29l-9-9a1 1 0 00-1.42 0l-9 9a1 1 0 000 1.42l9 9a1 1 0 001.42 0l9-9a1 1 0 000-1.42zM14 14.5V12h-4v3H8v-4a1 1 0 011-1h5V7.5l3.5 3.5-3.5 3.5z"/></svg>
                Directions
            </a>
        <?php endif; ?>
        <?php if ( $phone ) : ?>
            <a class="oha-mobile-bar__btn oha-mobile-bar__btn--call"
               href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1-9.4 0-17-7.6-17-17 0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1L6.6 10.8z"/></svg>
                Call
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div><!-- .oha-listing-wrap -->
