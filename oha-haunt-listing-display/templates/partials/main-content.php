<?php
/**
 * Main content (left column) partial.
 * Variables available from content-single.php scope.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="oha-listing-main">

    <!-- Rating row -->
    <?php if ( $rating > 0 || $rating_count > 0 ) : ?>
    <div class="oha-rating">
        <div class="oha-rating__stars" aria-label="<?php echo esc_attr( number_format( $rating, 1 ) . ' out of 5 stars' ); ?>">
            <?php for ( $i = 0; $i < $full_stars; $i++ ) : ?>
                <span class="oha-star oha-star--full" aria-hidden="true">&#9733;</span>
            <?php endfor; ?>
            <?php if ( $half_star ) : ?>
                <span class="oha-star oha-star--half" aria-hidden="true">&#9733;</span>
            <?php endif; ?>
            <?php for ( $i = 0; $i < $empty_stars; $i++ ) : ?>
                <span class="oha-star oha-star--empty" aria-hidden="true">&#9733;</span>
            <?php endfor; ?>
        </div>
        <span class="oha-rating__score"><?php echo esc_html( number_format( $rating, 1 ) ); ?></span>
        <?php if ( $rating_count ) : ?>
            <span class="oha-rating__count">(<?php echo esc_html( $rating_count ); ?> review<?php echo $rating_count !== 1 ? 's' : ''; ?>)</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- About section -->
    <?php if ( $content || $haunt_storyline ) : ?>
    <section class="oha-about">
        <h2 class="oha-section-title">About This Haunt</h2>
        <div class="oha-about__body">
            <?php if ( $content ) : ?>
                <?php echo wp_kses_post( $content ); ?>
            <?php endif; ?>
            <?php if ( $haunt_storyline ) : ?>
                <p class="oha-about__storyline"><?php echo wp_kses_post( nl2br( $haunt_storyline ) ); ?></p>
            <?php endif; ?>
        </div>
    </section>
    <?php elseif ( $is_display_unclaimed ) : ?>
    <section class="oha-about">
        <h2 class="oha-section-title">About This Haunt</h2>
        <div class="oha-empty-state">
            <p class="oha-empty-state-label">This listing has not been claimed yet.</p>
            <p class="oha-empty-state-body">The haunt owner hasn&rsquo;t added a description. Claim this listing to add your story.</p>
        </div>
    </section>
    <?php endif; ?>

    <?php do_action( 'oha_place_page_events', $listing_id ); ?>

    <!-- Features row (haunt_features multiselect) -->
    <?php
    $all_feature_pills = array_filter( array_merge(
        $features,
        $indoor_outdoor ? [ $indoor_outdoor ] : [],
        $actor_interaction ? [ $actor_interaction ] : [],
        $recommended_aud,
        $special_effects,
        $hhh_operating_nights  // Home Haunt Hub: when-we're-open nights
    ) );
    ?>
    <?php if ( ! empty( $all_feature_pills ) ) : ?>
    <section class="oha-features">
        <h2 class="oha-section-title">Features</h2>
        <div class="oha-features-row">
            <?php foreach ( $all_feature_pills as $pill ) : ?>
                <?php if ( ! empty( trim( $pill ) ) ) : ?>
                    <span class="oha-pill oha-pill--feature"><?php echo esc_html( trim( $pill ) ); ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Accessibility row (warning-style pills) -->
    <?php if ( ! empty( $accessibility ) ) : ?>
    <section class="oha-accessibility-row">
        <h2 class="oha-section-title oha-section-title--sm">Accessibility & Warnings</h2>
        <div class="oha-features-row">
            <?php foreach ( $accessibility as $item ) : ?>
                <?php if ( ! empty( trim( $item ) ) ) : ?>
                    <span class="oha-pill oha-pill--warning"><?php echo esc_html( trim( $item ) ); ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Gallery grid -->
    <?php if ( ! empty( $gallery_imgs ) ) : ?>
    <section class="oha-gallery">
        <h2 class="oha-section-title">Gallery</h2>
        <div class="oha-gallery__grid">
            <?php foreach ( $gallery_imgs as $index => $img ) : ?>
                <?php
                $img_src   = function_exists( 'geodir_get_image_src' ) ? geodir_get_image_src( $img, 'large' )   : '';
                $img_thumb = $img_src; // Use large src — medium (300px) is blurry when scaled up in grid
                $img_title = isset( $img->title ) ? $img->title : '';
                $is_last   = ( $index === count( $gallery_imgs ) - 1 ) && $gallery_extra > 0;
                ?>
                <?php if ( $img_src ) : ?>
                    <a class="oha-gallery__item <?php echo $is_last ? 'oha-gallery__item--more' : ''; ?>"
                       href="<?php echo esc_url( $img_src ); ?>"
                       data-gallery="oha-listing"
                       style="background-image: url('<?php echo esc_url( $img_thumb ?: $img_src ); ?>')">
                        <?php if ( $is_last && $gallery_extra > 0 ) : ?>
                            <span class="oha-gallery__more-count">+<?php echo esc_html( $gallery_extra ); ?></span>
                        <?php endif; ?>
                        <span class="sr-only"><?php echo esc_html( $img_title ?: 'Gallery image ' . ( $index + 1 ) ); ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php elseif ( $is_display_unclaimed ) : ?>
    <section class="oha-gallery">
        <h2 class="oha-section-title">Gallery</h2>
        <div class="oha-empty-state">
            <svg viewBox="0 0 24 24" width="32" height="32" aria-hidden="true" style="opacity:.3;margin-bottom:8px"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
            <p class="oha-empty-state-label">No photos added yet</p>
        </div>
    </section>
    <?php endif; ?>

    <!-- Video (GeoDirectory 'video' field — YouTube URL or embed code) -->
    <?php if ( ! empty( $video_embed ) ) : ?>
    <section class="oha-video">
        <h2 class="oha-section-title">Video</h2>
        <div class="oha-video__embed">
            <?php echo $video_embed; // already sanitised via wp_embed or wp_kses_post ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Haunt stats block (Track B Priority 3) -->
    <?php if ( $has_stats ) : ?>
    <section class="oha-stats-block">
        <h2 class="oha-section-title">Haunt Stats</h2>
        <div class="oha-stats-block__grid">
            <?php if ( $years_running ) : ?>
            <div class="oha-stats-block__item">
                <span class="oha-stats-block__value"><?php echo esc_html( $years_running ); ?></span>
                <span class="oha-stats-block__label">Years Running</span>
            </div>
            <?php endif; ?>
            <?php if ( $scene_count ) : ?>
            <div class="oha-stats-block__item">
                <span class="oha-stats-block__value"><?php echo esc_html( $scene_count ); ?></span>
                <span class="oha-stats-block__label">Scenes</span>
            </div>
            <?php endif; ?>
            <?php if ( $actor_count ) : ?>
            <div class="oha-stats-block__item">
                <span class="oha-stats-block__value"><?php echo esc_html( $actor_count ); ?></span>
                <span class="oha-stats-block__label">Actors</span>
            </div>
            <?php endif; ?>
            <?php if ( $animatronic_count ) : ?>
            <div class="oha-stats-block__item">
                <span class="oha-stats-block__value"><?php echo esc_html( $animatronic_count ); ?></span>
                <span class="oha-stats-block__label">Animatronics</span>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Visitor tips (Track B Priority 3 - collapsible) -->
    <?php if ( $visitor_tips ) : ?>
    <section class="oha-visitor-tips">
        <button class="oha-visitor-tips__toggle" type="button" aria-expanded="false">
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
            Visitor Tips
            <svg class="oha-visitor-tips__chevron" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M7 10l5 5 5-5z"/></svg>
        </button>
        <div class="oha-visitor-tips__body" hidden>
            <?php echo wp_kses_post( nl2br( $visitor_tips ) ); ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Builder's notes (Home Haunt Hub listings - collapsible like visitor tips) -->
    <?php if ( $hhh_builders_notes ) : ?>
    <section class="oha-visitor-tips">
        <button class="oha-visitor-tips__toggle" type="button" aria-expanded="false">
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 000-1.41l-2.34-2.34a1 1 0 00-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
            Builder's Notes
            <svg class="oha-visitor-tips__chevron" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M7 10l5 5 5-5z"/></svg>
        </button>
        <div class="oha-visitor-tips__body" hidden>
            <?php echo wp_kses_post( nl2br( $hhh_builders_notes ) ); ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Actor recruiting banner (Track B Priority 3) -->
    <?php if ( $recruiting === 'Yes' ) : ?>
    <section class="oha-recruiting-banner">
        <div class="oha-recruiting-banner__inner">
            <div>
                <strong class="oha-recruiting-banner__title">Now Recruiting Actors &amp; Volunteers</strong>
                <p class="oha-recruiting-banner__body">This haunt is looking for scare actors and crew for the upcoming season.</p>
            </div>
            <?php if ( $recruitment_link ) : ?>
                <a class="oha-recruiting-banner__btn" href="<?php echo esc_url( $recruitment_link ); ?>" target="_blank" rel="noopener noreferrer">Apply Now</a>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Tags -->
    <?php if ( $tags && ! is_wp_error( $tags ) ) : ?>
    <div class="oha-tags">
        <?php foreach ( $tags as $tag ) : ?>
            <a class="oha-pill oha-pill--tag"
               href="<?php echo esc_url( get_term_link( $tag ) ); ?>">
                #<?php echo esc_html( $tag->name ); ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div><!-- .oha-listing-main -->
