<?php
/**
 * "More Haunts" section - featured listings sorted by distance from current listing.
 *
 * GeoDirectory stores latitude/longitude in the detail table (not wp_postmeta),
 * so we cannot use meta_query to filter by coordinates or featured status.
 * Strategy: fetch a batch of published gd_place posts, use geodir_get_post_info()
 * to read featured flag + coordinates, calculate haversine distance, sort, trim.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$nearby_count = max( 1, (int) $settings['nearby_count'] );

// Haversine distance in miles
function oha_hld_haversine( $lat1, $lon1, $lat2, $lon2 ) {
    $R    = 3958.8; // Earth radius in miles
    $dLat = deg2rad( $lat2 - $lat1 );
    $dLon = deg2rad( $lon2 - $lon1 );
    $a    = sin( $dLat / 2 ) * sin( $dLat / 2 )
          + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) )
          * sin( $dLon / 2 ) * sin( $dLon / 2 );
    return $R * 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
}

// Fetch a batch of candidates - pull more than we need to find enough featured ones.
// Exclude current listing.
$candidate_args = [
    'post_type'           => 'gd_place',
    'posts_per_page'      => 60,
    'post__not_in'        => [ $listing_id ],
    'post_status'         => 'publish',
    'orderby'             => 'rand',
    'ignore_sticky_posts' => true,
    'fields'              => 'ids',
];

$candidate_ids = get_posts( $candidate_args );

if ( empty( $candidate_ids ) ) return;

$featured_haunts = [];

foreach ( $candidate_ids as $cid ) {
    $c_gd = function_exists( 'geodir_get_post_info' ) ? geodir_get_post_info( $cid ) : null;
    if ( ! $c_gd ) continue;

    // Only include featured listings
    if ( empty( $c_gd->featured ) || ! $c_gd->featured ) continue;

    $c_lat = isset( $c_gd->latitude )  ? (float) $c_gd->latitude  : 0;
    $c_lng = isset( $c_gd->longitude ) ? (float) $c_gd->longitude : 0;

    // Calculate distance (miles) from current listing; null if coords unavailable
    $distance = null;
    if ( $lat && $lng && $c_lat && $c_lng ) {
        $distance = oha_hld_haversine( $lat, $lng, $c_lat, $c_lng );
    }

    $featured_haunts[] = [
        'id'       => $cid,
        'gd'       => $c_gd,
        'distance' => $distance,
    ];

    // Stop early once we have enough candidates to sort
    if ( count( $featured_haunts ) >= $nearby_count * 4 ) break;
}

if ( empty( $featured_haunts ) ) return;

// Sort by distance ascending (nulls go last)
usort( $featured_haunts, function( $a, $b ) {
    if ( $a['distance'] === null && $b['distance'] === null ) return 0;
    if ( $a['distance'] === null ) return 1;
    if ( $b['distance'] === null ) return -1;
    return $a['distance'] <=> $b['distance'];
} );

// Take only the count we need
$featured_haunts = array_slice( $featured_haunts, 0, $nearby_count );
?>
<section class="oha-nearby">
    <h2 class="oha-section-title">Featured Haunts Nearby</h2>
    <div class="oha-nearby__grid">
        <?php foreach ( $featured_haunts as $item ) :
            $n_id    = $item['id'];
            $n_gd    = $item['gd'];
            $n_dist  = $item['distance'];
            $n_title = get_the_title( $n_id );
            $n_link  = get_permalink( $n_id );
            $n_city  = isset( $n_gd->city )   ? $n_gd->city   : '';
            $n_state = isset( $n_gd->region )  ? $n_gd->region : '';
            $n_loc   = implode( ', ', array_filter( [ $n_city, $n_state ] ) );

            $n_images = function_exists( 'geodir_get_images' ) ? geodir_get_images( $n_id ) : [];
            $n_img    = '';
            if ( ! empty( $n_images ) && function_exists( 'geodir_get_image_src' ) ) {
                $n_img = geodir_get_image_src( $n_images[0], 'medium' );
            }

            $n_cats   = get_the_terms( $n_id, 'gd_placecategory' );
            $n_rating = function_exists( 'geodir_get_post_rating' ) ? (float) geodir_get_post_rating( $n_id ) : 0;
        ?>
        <a class="oha-nearby__card" href="<?php echo esc_url( $n_link ); ?>">
            <div class="oha-nearby__img" <?php if ( $n_img ) : ?>style="background-image: url('<?php echo esc_url( $n_img ); ?>')"<?php endif; ?>>
                <?php if ( $n_dist !== null ) : ?>
                    <span class="oha-nearby__distance">
                        <svg viewBox="0 0 24 24" width="11" height="11" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>
                        <?php echo esc_html( number_format( $n_dist, 1 ) ); ?> mi
                    </span>
                <?php endif; ?>
                <span class="oha-badge oha-badge--featured oha-badge--sm">Featured</span>
            </div>
            <div class="oha-nearby__body">
                <?php if ( $n_cats && ! is_wp_error( $n_cats ) ) : ?>
                    <span class="oha-nearby__cat"><?php echo esc_html( $n_cats[0]->name ); ?></span>
                <?php endif; ?>
                <h3 class="oha-nearby__title"><?php echo esc_html( $n_title ); ?></h3>
                <?php if ( $n_loc ) : ?>
                    <p class="oha-nearby__location"><?php echo esc_html( $n_loc ); ?></p>
                <?php endif; ?>
                <?php if ( $n_rating > 0 ) : ?>
                    <div class="oha-nearby__rating">
                        <span class="oha-nearby__star">&#9733;</span>
                        <?php echo esc_html( number_format( $n_rating, 1 ) ); ?>
                    </div>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section><!-- .oha-nearby -->
