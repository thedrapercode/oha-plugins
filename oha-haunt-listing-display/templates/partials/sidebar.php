<?php
/**
 * Sidebar (right column) partial.
 * Variables available from content-single.php scope.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="oha-sidebar">

    <!-- ── Claim section ─────────────────────────────────────────────────── -->
    <?php echo OHA_HLD_Claim::section_html( $listing_id ); ?>

    <!-- ── CTA buttons ───────────────────────────────────────────────────── -->
    <div class="oha-cta-buttons">
        <?php if ( $lat && $lng ) : ?>
            <a class="oha-cta-btn oha-cta-btn--directions"
               href="https://www.google.com/maps/dir/?api=1&destination=<?php echo esc_attr( $lat . ',' . $lng ); ?>"
               target="_blank" rel="noopener noreferrer">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M21.71 11.29l-9-9a1 1 0 00-1.42 0l-9 9a1 1 0 000 1.42l9 9a1 1 0 001.42 0l9-9a1 1 0 000-1.42zM14 14.5V12h-4v3H8v-4a1 1 0 011-1h5V7.5l3.5 3.5-3.5 3.5z"/></svg>
                Get Directions
            </a>
        <?php endif; ?>
        <?php if ( $phone ) : ?>
            <a class="oha-cta-btn oha-cta-btn--call"
               href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1-9.4 0-17-7.6-17-17 0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1L6.6 10.8z"/></svg>
                Call
            </a>
        <?php endif; ?>
    </div>

    <!-- ── Info card ─────────────────────────────────────────────────────── -->
    <div class="oha-info-card">

        <?php if ( $haunt_season ) : ?>
        <div class="oha-info-card__row">
            <span class="oha-info-card__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg>
            </span>
            <span class="oha-info-card__label">Season</span>
            <span class="oha-info-card__value"><?php echo esc_html( $haunt_season ); ?></span>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $holiday_dates ) ) : ?>
        <div class="oha-info-card__row">
            <span class="oha-info-card__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
            </span>
            <span class="oha-info-card__label">Event Dates</span>
            <span class="oha-info-card__value"><?php echo esc_html( $holiday_dates ); ?></span>
        </div>
        <?php endif; ?>

        <?php if ( $typical_hours ) : ?>
        <div class="oha-info-card__row">
            <span class="oha-info-card__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/></svg>
            </span>
            <span class="oha-info-card__label">Hours</span>
            <span class="oha-info-card__value"><?php echo esc_html( $typical_hours ); ?></span>
        </div>
        <?php endif; ?>

        <?php if ( $admission_type ) : ?>
        <div class="oha-info-card__row">
            <span class="oha-info-card__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M20 12c0-1.1.9-2 2-2V6c0-1.1-.9-2-2-2H4c-1.1 0-1.99.9-1.99 2v4c1.1 0 1.99.9 1.99 2s-.89 2-2 2v4c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2v-4c-1.1 0-2-.9-2-2zm-2-1.46c-.83.51-1.4 1.43-1.4 2.46 0 1.03.57 1.95 1.4 2.46V18H6v-2.54c.83-.51 1.4-1.43 1.4-2.46 0-1.03-.57-1.95-1.4-2.46V6h12v4.54z"/></svg>
            </span>
            <span class="oha-info-card__label">Admission</span>
            <span class="oha-info-card__value">
                <?php echo esc_html( $admission_type ); ?>
                <?php if ( $admission_price ) : ?>
                    &mdash; <?php echo esc_html( $admission_price ); ?>
                <?php endif; ?>
            </span>
        </div>
        <?php endif; ?>

        <?php if ( $fear_level ) : ?>
        <div class="oha-info-card__row">
            <span class="oha-info-card__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-4h2v2h-2zm0-10h2v8h-2z"/></svg>
            </span>
            <span class="oha-info-card__label">Fear Level</span>
            <span class="oha-info-card__value oha-fear-inline oha-fear-<?php echo esc_attr( sanitize_title( $fear_level ) ); ?>"><?php echo esc_html( $fear_level ); ?></span>
        </div>
        <?php endif; ?>

        <?php if ( $age_restriction ) : ?>
        <div class="oha-info-card__row">
            <span class="oha-info-card__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            </span>
            <span class="oha-info-card__label">Ages</span>
            <span class="oha-info-card__value"><?php echo esc_html( $age_restriction ); ?></span>
        </div>
        <?php endif; ?>

        <?php if ( $hhh_public_visitors ) : ?>
        <div class="oha-info-card__row">
            <span class="oha-info-card__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            </span>
            <span class="oha-info-card__label">Visitors</span>
            <span class="oha-info-card__value"><?php echo esc_html( $hhh_public_visitors ); ?></span>
        </div>
        <?php endif; ?>

        <?php if ( $hhh_visitor_range_label ) : ?>
        <div class="oha-info-card__row">
            <span class="oha-info-card__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
            </span>
            <span class="oha-info-card__label">Annual Traffic</span>
            <span class="oha-info-card__value"><?php echo esc_html( $hhh_visitor_range_label ); ?></span>
        </div>
        <?php endif; ?>

        <?php if ( $experience_length ) : ?>
        <div class="oha-info-card__row">
            <span class="oha-info-card__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M13 2.05V4.07c3.39.49 6 3.39 6 6.93 0 3.21-1.81 6-4.72 7.28L13 17v5l-1-1-1 1v-5l-1.28 1.28C6.81 16.99 5 14.2 5 11c0-3.54 2.61-6.44 6-6.93V2.05C6.17 2.56 3 6.44 3 11c0 4.09 2.41 7.58 5.92 9.28L8 21h2l1-1 1 1h2l-.92-1.72C16.59 18.58 19 15.09 19 11c0-4.56-3.17-8.44-7-8.95z"/></svg>
            </span>
            <span class="oha-info-card__label">Duration</span>
            <span class="oha-info-card__value"><?php echo esc_html( $experience_length ); ?></span>
        </div>
        <?php endif; ?>

        <?php if ( $wait_time ) : ?>
        <div class="oha-info-card__row">
            <span class="oha-info-card__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/></svg>
            </span>
            <span class="oha-info-card__label">Wait Time</span>
            <span class="oha-info-card__value"><?php echo esc_html( $wait_time ); ?></span>
        </div>
        <?php endif; ?>

        <?php if ( $parking_type ) : ?>
        <div class="oha-info-card__row">
            <span class="oha-info-card__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M13 3H6v18h4v-6h3c3.31 0 6-2.69 6-6s-2.69-6-6-6zm.2 8H10V7h3.2c1.1 0 2 .9 2 2s-.9 2-2 2z"/></svg>
            </span>
            <span class="oha-info-card__label">Parking</span>
            <span class="oha-info-card__value"><?php echo esc_html( $parking_type ); ?></span>
        </div>
        <?php endif; ?>

        <?php if ( $haunt_theme ) : ?>
        <div class="oha-info-card__row">
            <span class="oha-info-card__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg>
            </span>
            <span class="oha-info-card__label">Theme</span>
            <span class="oha-info-card__value"><?php echo esc_html( $haunt_theme ); ?></span>
        </div>
        <?php endif; ?>

        <?php if ( $phone ) : ?>
        <div class="oha-info-card__row">
            <span class="oha-info-card__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1-9.4 0-17-7.6-17-17 0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1L6.6 10.8z"/></svg>
            </span>
            <span class="oha-info-card__label">Phone</span>
            <a class="oha-info-card__value oha-info-card__value--link"
               href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>">
                <?php echo esc_html( $phone ); ?>
            </a>
        </div>
        <?php endif; ?>

        <?php if ( $email && strtolower( $email ) !== 'do-not-reply@ohiohaunters.com' ) :
            // Split email at @ so no full address appears in source HTML — stops basic harvesters.
            $email_parts  = explode( '@', $email, 2 );
            $email_user   = $email_parts[0] ?? '';
            $email_domain = $email_parts[1] ?? '';
        ?>
        <div class="oha-info-card__row">
            <span class="oha-info-card__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
            </span>
            <span class="oha-info-card__label">Email</span>
            <button class="oha-email-reveal oha-info-card__value"
                    type="button"
                    data-u="<?php echo esc_attr( $email_user ); ?>"
                    data-d="<?php echo esc_attr( $email_domain ); ?>"
                    aria-label="Reveal contact email">
                <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true" style="vertical-align:-2px;margin-right:4px;fill:currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                Show Email
            </button>
        </div>
        <?php endif; ?>

        <?php if ( $address ) : ?>
        <div class="oha-info-card__row">
            <span class="oha-info-card__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
            </span>
            <span class="oha-info-card__label">Address</span>
            <span class="oha-info-card__value">
                <?php echo esc_html( $address ); ?>
                <?php if ( $city )  echo ', ' . esc_html( $city ); ?>
                <?php if ( $state ) echo ', ' . esc_html( $state ); ?>
                <?php if ( $zip )   echo ' '  . esc_html( $zip ); ?>
            </span>
        </div>
        <?php elseif ( $city ) : ?>
        <div class="oha-info-card__row">
            <span class="oha-info-card__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
            </span>
            <span class="oha-info-card__label">Location</span>
            <span class="oha-info-card__value">
                <?php echo esc_html( $city ); ?>
                <?php if ( $state ) echo ', ' . esc_html( $state ); ?>
            </span>
        </div>
        <?php endif; ?>

        <?php if ( $website ) : ?>
        <a class="oha-info-card__website-btn"
           href="<?php echo esc_url( $website ); ?>"
           target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zm6.93 11h-2.95a14.09 14.09 0 00-.38-2.75A5.95 5.95 0 0018.92 13zm-8.92 5.93A12.03 12.03 0 019.05 15h5.9a12.03 12.03 0 01-1.95 3.93A6.01 6.01 0 0110 18.93zm-3-3.93H4.08a5.95 5.95 0 003.33 2.25A14.09 14.09 0 017 15zm-2.92-2H7a14.09 14.09 0 00.38 2.75A5.95 5.95 0 015.08 13zM7 11H4.08a5.95 5.95 0 013.33-2.25A14.09 14.09 0 007 11zm1.05-4h.95a12.03 12.03 0 011.95-3.93A6.01 6.01 0 0110 3.07 12.03 12.03 0 018.05 7zM17 11h-2.95a14.09 14.09 0 00-.38-2.75A5.95 5.95 0 0117 11zm-4 2h-2v-2h2v2zm0-4h-2V7h2v2zm0-4h-2V3.07A6.01 6.01 0 0114.95 7H13zm0 14v-2h1.95A6.01 6.01 0 0113 20.93z"/></svg>
            Visit Website
        </a>
        <?php endif; ?>

        <?php if ( ! empty( $related_listing_url ) ) : ?>
        <a class="oha-info-card__related-btn"
           href="<?php echo esc_url( $related_listing_url ); ?>">
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>
            See our <?php echo esc_html( $related_listing_title ); ?>
        </a>
        <?php endif; ?>

    </div><!-- .oha-info-card -->

    <!-- ── Business hours ────────────────────────────────────────────────── -->
    <?php if ( ! empty( $business_hours ) ) : ?>
    <div class="oha-bh-card">
        <h3 class="oha-section-title">Hours</h3>
        <div class="oha-bh-table">
            <?php
            // $business_hours is keyed by full day name: 'Monday', 'Tuesday', ...
            // Each entry: ['today'=>0/1, 'closed'=>0/1, 'day_short'=>'Mon',
            //              'slots'=>[['range'=>'9:00 am - 5:00 pm', 'open'=>0/1, ...]]]
            // GD already formats the range string per the WP time_format option.
            foreach ( $business_hours as $day_name => $day_data ) :
                $is_today  = ! empty( $day_data['today'] );
                $is_closed = ! empty( $day_data['closed'] );
                $slots     = isset( $day_data['slots'] ) ? $day_data['slots'] : [];
                $day_short = isset( $day_data['day_short'] ) ? $day_data['day_short'] : $day_name;
            ?>
            <div class="oha-bh-row<?php echo $is_today ? ' oha-bh-row--today' : ''; ?>">
                <span class="oha-bh-day"><?php echo esc_html( $day_name ); ?></span>
                <span class="oha-bh-hours">
                    <?php if ( $is_closed ) : ?>
                        <span class="oha-bh-closed">Closed</span>
                    <?php else : ?>
                        <?php
                        $ranges = [];
                        foreach ( $slots as $slot ) {
                            if ( ! empty( $slot['range'] ) && $slot['range'] !== __( 'Closed', 'geodirectory' ) ) {
                                $ranges[] = $slot['range'];
                            }
                        }
                        echo esc_html( $ranges ? implode( ', ', $ranges ) : __( 'Closed', 'geodirectory' ) );
                        ?>
                    <?php endif; ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Map embed ─────────────────────────────────────────────────────── -->
    <?php if ( $lat && $lng ) : ?>
    <div class="oha-map">
        <h3 class="oha-section-title">Location</h3>
        <div class="oha-map__frame">
            <iframe
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                src="https://www.google.com/maps?q=<?php echo esc_attr( $lat . ',' . $lng ); ?>&output=embed"
                title="Map location of <?php echo esc_attr( $title ); ?>"
                aria-label="Map showing <?php echo esc_attr( $title ); ?>">
            </iframe>
        </div>
        <a class="oha-map__directions-link"
           href="https://www.google.com/maps/dir/?api=1&destination=<?php echo esc_attr( $lat . ',' . $lng ); ?>"
           target="_blank" rel="noopener noreferrer">
            Open in Google Maps
        </a>
    </div>
    <?php endif; ?>

    <!-- ── Social links ──────────────────────────────────────────────────── -->
    <?php if ( $facebook || $instagram || $tiktok || $youtube_channel ) : ?>
    <div class="oha-social">
        <?php if ( $facebook ) : ?>
        <a class="oha-social__btn oha-social__btn--facebook"
           href="<?php echo esc_url( $facebook ); ?>"
           target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>
            Facebook
        </a>
        <?php endif; ?>
        <?php if ( $instagram ) : ?>
        <a class="oha-social__btn oha-social__btn--instagram"
           href="<?php echo esc_url( $instagram ); ?>"
           target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" ry="5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z" fill="none" stroke="currentColor" stroke-width="1.5"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5" stroke="currentColor" stroke-width="2"/></svg>
            Instagram
        </a>
        <?php endif; ?>
        <?php if ( $tiktok ) : ?>
        <a class="oha-social__btn oha-social__btn--tiktok"
           href="<?php echo esc_url( $tiktok ); ?>"
           target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.79 1.54V6.79a4.85 4.85 0 01-1.02-.1z"/></svg>
            TikTok
        </a>
        <?php endif; ?>
        <?php if ( $youtube_channel ) : ?>
        <a class="oha-social__btn oha-social__btn--youtube"
           href="<?php echo esc_url( $youtube_channel ); ?>"
           target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M22.54 6.42a2.78 2.78 0 00-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46A2.78 2.78 0 001.46 6.42 29 29 0 001 12a29 29 0 00.46 5.58A2.78 2.78 0 003.41 19.6C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 001.95-1.95A29 29 0 0023 12a29 29 0 00-.46-5.58zM9.75 15.02V8.98L15.5 12l-5.75 3.02z"/></svg>
            YouTube
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── GeoDirectory Report Post ──────────────────────────────────────── -->
    <?php if ( $settings['enable_report'] ) : ?>
        <?php
        $report_html = function_exists( 'do_shortcode' )
            ? do_shortcode( '[gd_report_post output="link" text="Report this listing"]' )
            : '';
        if ( ! empty( trim( $report_html ) ) ) :
        ?>
        <div class="oha-report-post">
            <?php echo $report_html; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>

</div><!-- .oha-sidebar -->
