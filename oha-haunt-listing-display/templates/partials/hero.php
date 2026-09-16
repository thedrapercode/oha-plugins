<?php
/**
 * Hero section partial.
 * Variables available from content-single.php scope.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Fear level → CSS modifier class (e.g. "family-friendly", "extreme")
$fear_slug = $fear_level ? sanitize_title( $fear_level ) : '';
?>
<div class="oha-hero<?php echo $is_display_unclaimed ? ' oha-hero--unclaimed' : ''; ?>"
     <?php if ( $hero_img ) : ?>style="background-image: url('<?php echo esc_url( $hero_img ); ?>')"<?php endif; ?>>
    <div class="oha-hero__overlay"></div>

    <!-- Top nav bar -->
    <div class="oha-hero__topbar">
        <a class="oha-hero__back" href="<?php echo esc_url( get_post_type_archive_link( 'gd_place' ) ); ?>">
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
            All Haunts
        </a>

        <div class="oha-hero__badges">
            <?php if ( $is_display_unclaimed ) : ?>
                <span class="oha-badge oha-badge--unclaimed">Unclaimed</span>
            <?php elseif ( $featured ) : ?>
                <span class="oha-badge oha-badge--featured">Featured</span>
            <?php endif; ?>

            <?php if ( $open_this_season ) : ?>
                <span class="oha-badge oha-badge--open">Open This Season</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bottom content -->
    <div class="oha-hero__content">

        <!-- Type + fear level pills -->
        <?php if ( $haunt_type || $fear_level || ( $categories && ! is_wp_error( $categories ) ) ) : ?>
        <div class="oha-hero__cats">
            <?php if ( $haunt_type ) : ?>
                <span class="oha-pill oha-pill--type"><?php echo esc_html( $haunt_type ); ?></span>
            <?php endif; ?>
            <?php if ( $fear_level ) : ?>
                <span class="oha-pill oha-fear-pill oha-fear-<?php echo esc_attr( $fear_slug ); ?>"><?php echo esc_html( $fear_level ); ?></span>
            <?php endif; ?>
            <?php if ( $categories && ! is_wp_error( $categories ) ) : ?>
                <?php foreach ( $categories as $cat ) : ?>
                    <a class="oha-pill oha-pill--cat"
                       href="<?php echo esc_url( get_term_link( $cat ) ); ?>">
                        <?php echo esc_html( $cat->name ); ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <h1 class="oha-hero__title<?php echo $is_display_unclaimed ? ' oha-title-unclaimed' : ''; ?>"><?php echo esc_html( $title ); ?></h1>

        <?php if ( $location_str ) : ?>
            <p class="oha-hero__location">
                <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                <?php echo esc_html( $location_str ); ?>
            </p>
        <?php endif; ?>

    </div>
</div><!-- .oha-hero -->
