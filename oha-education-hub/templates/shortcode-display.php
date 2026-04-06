<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
// Variables provided by oha_education_shortcode():
//   $atts, $settings, $classes, $days, $faq_items, $track
?>
<div class="oha-education-wrap">

<?php if ( $atts['show_hero'] !== '0' ) : ?>
<section class="oha-edu-hero">
    <div class="oha-edu-hero-inner">
        <div class="oha-edu-hero-eyebrow">OHA Convention Education</div>
        <h1 class="oha-edu-hero-title"><?php echo esc_html( $settings['hero_heading'] ); ?></h1>
        <p class="oha-edu-hero-desc"><?php echo esc_html( $settings['hero_subheading'] ); ?></p>
        <?php if ( ! empty( $settings['hero_btn_url'] ) && ! empty( $settings['hero_btn_text'] ) ) : ?>
        <a href="<?php echo esc_url( $settings['hero_btn_url'] ); ?>" class="oha-edu-btn oha-edu-btn-primary">
            <?php echo esc_html( $settings['hero_btn_text'] ); ?>
        </a>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ( $atts['show_values'] !== '0' ) : ?>
<section class="oha-edu-values">
    <div class="oha-edu-values-grid">
        <?php for ( $i = 1; $i <= 3; $i++ ) :
            $v_icon  = ! empty( $settings[ "value_{$i}_icon" ] )  ? $settings[ "value_{$i}_icon" ]  : '';
            $v_title = ! empty( $settings[ "value_{$i}_title" ] ) ? $settings[ "value_{$i}_title" ] : '';
            $v_desc  = ! empty( $settings[ "value_{$i}_desc" ] )  ? $settings[ "value_{$i}_desc" ]  : '';
            if ( empty( $v_title ) ) continue;
        ?>
        <div class="oha-edu-value-card">
            <?php if ( $v_icon ) : ?><div class="oha-edu-value-icon"><?php echo $v_icon; ?></div><?php endif; ?>
            <h3 class="oha-edu-value-title"><?php echo esc_html( $v_title ); ?></h3>
            <p class="oha-edu-value-desc"><?php echo esc_html( $v_desc ); ?></p>
        </div>
        <?php endfor; ?>
    </div>
</section>
<?php endif; ?>

<section class="oha-edu-sessions" id="oha-edu-sessions">
    <div class="oha-edu-container">
        <?php if ( ! empty( $settings['sessions_heading'] ) ) : ?>
        <div class="oha-edu-section-header">
            <h2 class="oha-edu-section-title"><?php echo esc_html( $settings['sessions_heading'] ); ?></h2>
            <?php if ( ! empty( $settings['sessions_subheading'] ) ) : ?>
            <p class="oha-edu-section-sub"><?php echo esc_html( $settings['sessions_subheading'] ); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ( empty( $classes ) ) : ?>
        <p class="oha-edu-no-classes">No classes found. Check back soon!</p>
        <?php else : ?>

        <?php foreach ( $days as $day_name => $day_classes ) : ?>
        <div class="oha-edu-day-group">
            <h3 class="oha-edu-day-label"><?php echo esc_html( $day_name ); ?></h3>
            <div class="oha-edu-sessions-grid">
            <?php foreach ( $day_classes as $class ) :
                $start_raw  = get_post_meta( $class->ID, '_oha_start_time',  true );
                $end_raw    = get_post_meta( $class->ID, '_oha_end_time',    true );
                $room       = get_post_meta( $class->ID, '_oha_room',        true );
                $level      = get_post_meta( $class->ID, '_oha_level',       true );
                $short_desc = get_post_meta( $class->ID, '_oha_short_desc',  true );
                $materials  = get_post_meta( $class->ID, '_oha_materials',   true );
                $promoted   = get_post_meta( $class->ID, '_oha_promoted',    true );
                $capacity   = get_post_meta( $class->ID, '_oha_capacity',    true );

                $start_fmt    = oha_education_format_time( $start_raw );
                $end_fmt      = oha_education_format_time( $end_raw );
                $time_display = $start_fmt . ( $end_fmt ? ' &ndash; ' . $end_fmt : '' );

                // Instructor info
                $instructor_terms = wp_get_post_terms( $class->ID, 'instructor' );
                $instructor_names = [];
                $headshot_url     = '';
                if ( ! is_wp_error( $instructor_terms ) && ! empty( $instructor_terms ) ) {
                    foreach ( $instructor_terms as $inst ) {
                        $instructor_names[] = $inst->name;
                        if ( empty( $headshot_url ) ) {
                            $hs_id = get_term_meta( $inst->term_id, 'instructor_headshot_id', true );
                            if ( $hs_id ) {
                                $img = wp_get_attachment_image_src( $hs_id, [ 200, 200 ] );
                                if ( $img ) $headshot_url = $img[0];
                            }
                        }
                    }
                }
                $instructor_str = implode( ', ', $instructor_names );

                // Full description for modal (short_desc or stripped post content)
                $modal_desc = $short_desc ?: wp_strip_all_tags( $class->post_content );

                // Modal data payload
                $modal_data = wp_json_encode( [
                    'title'      => $class->post_title,
                    'time'       => html_entity_decode( $time_display ),
                    'room'       => $room ?: '',
                    'level'      => $level ? oha_education_level_label( $level ) : '',
                    'capacity'   => $capacity ?: '',
                    'desc'       => $modal_desc,
                    'materials'  => $materials ?: '',
                    'instructor' => $instructor_str,
                    'headshot'   => $headshot_url,
                ] );
            ?>
            <div class="oha-edu-session-card<?php echo $promoted === '1' ? ' oha-edu-promoted' : ''; ?>">
                <?php if ( $promoted === '1' ) : ?>
                <div class="oha-edu-promoted-badge">Featured</div>
                <?php endif; ?>

                <div class="oha-edu-session-top">
                    <?php if ( $headshot_url ) : ?>
                    <div class="oha-edu-session-headshot">
                        <img src="<?php echo esc_url( $headshot_url ); ?>" alt="<?php echo esc_attr( $instructor_str ); ?>">
                    </div>
                    <?php else : ?>
                    <div class="oha-edu-session-headshot oha-edu-headshot-placeholder">
                        <span><?php echo esc_html( substr( $instructor_str ?: 'OHA', 0, 1 ) ); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="oha-edu-session-header-info">
                        <?php if ( $time_display ) : ?>
                        <div class="oha-edu-session-time"><?php echo $time_display; ?></div>
                        <?php endif; ?>
                        <?php if ( $instructor_str ) : ?>
                        <div class="oha-edu-session-instructor"><?php echo esc_html( $instructor_str ); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="oha-edu-session-body">
                    <h4 class="oha-edu-session-title"><?php echo esc_html( $class->post_title ); ?></h4>
                    <?php if ( $short_desc ) : ?>
                    <p class="oha-edu-session-desc"><?php echo esc_html( $short_desc ); ?></p>
                    <?php endif; ?>
                    <div class="oha-edu-session-meta">
                        <?php if ( $level ) : ?>
                        <span class="oha-edu-badge oha-edu-level-<?php echo esc_attr( strtolower( $level ) ); ?>"><?php echo esc_html( oha_education_level_label( $level ) ); ?></span>
                        <?php endif; ?>
                        <?php if ( $room ) : ?>
                        <span class="oha-edu-badge oha-edu-room-badge"><?php echo esc_html( $room ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="oha-edu-session-footer">
                    <button class="oha-edu-details-btn" data-session="<?php echo esc_attr( $modal_data ); ?>">
                        View Details
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            </div><!-- .oha-edu-sessions-grid -->
        </div><!-- .oha-edu-day-group -->
        <?php endforeach; ?>

        <?php endif; // empty classes ?>
    </div><!-- .oha-edu-container -->
</section>

<?php if ( $atts['show_faq'] !== '0' && ! empty( $faq_items ) ) : ?>
<section class="oha-edu-faq">
    <div class="oha-edu-container">
        <div class="oha-edu-section-header">
            <h2 class="oha-edu-section-title"><?php echo esc_html( $settings['faq_heading'] ); ?></h2>
        </div>
        <div class="oha-edu-faq-list">
            <?php foreach ( $faq_items as $idx => $item ) :
                if ( empty( $item['q'] ) ) continue;
            ?>
            <div class="oha-edu-faq-item">
                <button class="oha-edu-faq-q" aria-expanded="false" aria-controls="oha-edu-faq-a-<?php echo (int) $idx; ?>">
                    <span><?php echo esc_html( $item['q'] ); ?></span>
                    <span class="oha-edu-faq-icon" aria-hidden="true">+</span>
                </button>
                <div class="oha-edu-faq-a" id="oha-edu-faq-a-<?php echo (int) $idx; ?>" hidden>
                    <?php echo wp_kses_post( $item['a'] ); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ( $atts['show_cta'] !== '0' ) : ?>
<section class="oha-edu-cta">
    <div class="oha-edu-container">
        <div class="oha-edu-cta-content">
            <?php if ( ! empty( $settings['cta_heading'] ) ) : ?>
            <h2 class="oha-edu-cta-title"><?php echo esc_html( $settings['cta_heading'] ); ?></h2>
            <?php endif; ?>
            <?php if ( ! empty( $settings['cta_subheading'] ) ) : ?>
            <p class="oha-edu-cta-sub"><?php echo esc_html( $settings['cta_subheading'] ); ?></p>
            <?php endif; ?>
            <?php if ( ! empty( $settings['cta_btn_url'] ) && ! empty( $settings['cta_btn_text'] ) ) : ?>
            <a href="<?php echo esc_url( $settings['cta_btn_url'] ); ?>" class="oha-edu-btn oha-edu-btn-primary">
                <?php echo esc_html( $settings['cta_btn_text'] ); ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Session Detail Modal -->
<div id="oha-edu-modal" class="oha-edu-modal-overlay" role="dialog" aria-modal="true" aria-label="Class Details" hidden>
    <div class="oha-edu-modal-box">
        <button class="oha-edu-modal-close" aria-label="Close">&times;</button>
        <div class="oha-edu-modal-body">
            <div class="oha-edu-modal-header">
                <div class="oha-edu-modal-headshot-wrap">
                    <img class="oha-edu-modal-headshot" src="" alt="" hidden>
                    <div class="oha-edu-modal-headshot-placeholder" hidden>
                        <span class="oha-edu-modal-initials"></span>
                    </div>
                </div>
                <div class="oha-edu-modal-header-text">
                    <div class="oha-edu-modal-instructor"></div>
                    <h3 class="oha-edu-modal-title"></h3>
                    <div class="oha-edu-modal-meta">
                        <span class="oha-edu-modal-time"></span>
                        <span class="oha-edu-modal-room"></span>
                        <span class="oha-edu-modal-level"></span>
                    </div>
                </div>
            </div>
            <div class="oha-edu-modal-desc"></div>
            <div class="oha-edu-modal-materials-wrap" hidden>
                <h4 class="oha-edu-modal-materials-label">Materials Needed</h4>
                <div class="oha-edu-modal-materials"></div>
            </div>
        </div>
    </div>
</div>

</div><!-- .oha-education-wrap -->
