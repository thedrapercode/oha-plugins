<?php
// Timeline view — card layout, tappable for detail modal
$show_descriptions = get_option('oha_schedule_show_descriptions', 1);
?>

<div class="oha-timeline-view">
    <?php foreach ($day_classes as $class):
        $start_time  = get_post_meta($class->ID, '_oha_start_time', true);
        $end_time    = get_post_meta($class->ID, '_oha_end_time',   true);
        $room        = get_post_meta($class->ID, '_oha_room',       true);
        $level       = get_post_meta($class->ID, '_oha_level',      true);
        $capacity    = get_post_meta($class->ID, '_oha_capacity',   true);
        $promoted    = get_post_meta($class->ID, '_oha_promoted',   true);
        $short_desc  = get_post_meta($class->ID, '_oha_short_desc', true);
        $materials   = get_post_meta($class->ID, '_oha_materials',  true);

        // All instructor terms (need IDs for headshots)
        $instructor_terms = wp_get_post_terms($class->ID, 'instructor', array('fields' => 'all'));
        $instructors      = wp_list_pluck($instructor_terms, 'name');
        $tracks           = wp_get_post_terms($class->ID, 'class_track', array('fields' => 'names'));
        $track_slug       = !empty($tracks) ? sanitize_title($tracks[0]) : 'default';

        // Featured image URLs
        $has_image  = has_post_thumbnail($class->ID);
        $img_large  = $has_image ? get_the_post_thumbnail_url($class->ID, 'large')  : '';
        $img_medium = $has_image ? get_the_post_thumbnail_url($class->ID, 'medium') : '';

        // Instructor headshots (up to 2, from term meta)
        $headshots = array();
        foreach (array_slice($instructor_terms, 0, 2) as $term) {
            $hs_id  = get_term_meta($term->term_id, 'instructor_headshot_id', true);
            $hs_url = $hs_id ? wp_get_attachment_image_url($hs_id, 'thumbnail') : '';
            if ($hs_url) {
                $headshots[] = array('url' => $hs_url, 'name' => $term->name);
            }
        }

        // Formatted time for data attrs
        $time_display = OHA_Schedule_Hub::format_time($start_time, $time_format)
                        . ' — '
                        . OHA_Schedule_Hub::format_time($end_time, $time_format);

        $headshot_1 = !empty($headshots[0]) ? $headshots[0]['url'] : '';
        $headshot_2 = !empty($headshots[1]) ? $headshots[1]['url'] : '';
        $has_visual = $has_image || !empty($headshots);
    ?>

    <div class="oha-class-item oha-timeline-item track-<?php echo esc_attr($track_slug); ?> event-item<?php echo $has_image ? ' has-image' : ''; ?><?php echo !$has_image && !empty($headshots) ? ' has-headshots-only' : ''; ?>"
         data-class-id="<?php echo esc_attr($class->ID); ?>"
         data-track="<?php echo esc_attr($track_slug); ?>"
         data-instructor="<?php echo !empty($instructors) ? esc_attr(sanitize_title($instructors[0])) : ''; ?>"
         data-event-id="class-<?php echo esc_attr($class->ID); ?>"
         data-event-title="<?php echo esc_attr($class->post_title); ?>"
         data-event-time="<?php echo esc_attr($day . ' ' . $time_display); ?>"
         data-event-room="<?php echo esc_attr($room); ?>"
         data-event-speaker="<?php echo esc_attr(implode(', ', $instructors)); ?>"
         data-event-promoted="<?php echo $promoted ? '1' : '0'; ?>"
         data-image="<?php echo esc_url($img_large); ?>"
         data-image-medium="<?php echo esc_url($img_medium); ?>"
         data-instructors="<?php echo esc_attr(implode(', ', $instructors)); ?>"
         data-level="<?php echo esc_attr($level); ?>"
         data-track-name="<?php echo !empty($tracks) ? esc_attr($tracks[0]) : ''; ?>"
         data-time-display="<?php echo esc_attr($time_display); ?>"
         data-materials="<?php echo esc_attr($materials); ?>"
         data-headshot-1="<?php echo esc_url($headshot_1); ?>"
         data-headshot-2="<?php echo esc_url($headshot_2); ?>"
         tabindex="0"
         role="button"
         aria-label="View details for <?php echo esc_attr($class->post_title); ?>">

        <?php if ($has_visual): ?>
        <!-- Featured image banner + instructor headshot overlay -->
        <div class="oha-card-image<?php echo !$has_image ? ' oha-headshots-only-wrap' : ''; ?>">
            <?php if ($has_image): ?>
            <img src="<?php echo esc_url($img_medium); ?>"
                 alt="<?php echo esc_attr($class->post_title); ?>"
                 class="oha-card-img"
                 loading="lazy">
            <?php if ($promoted): ?>
                <span class="oha-card-image-badge">&#9733; Featured</span>
            <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($headshots)): ?>
            <!-- Instructor headshots — Facebook-style circle(s) at bottom-left -->
            <div class="oha-instructor-headshots<?php echo !$has_image ? ' oha-headshots-standalone' : ''; ?>">
                <?php foreach ($headshots as $hs): ?>
                <img src="<?php echo esc_url($hs['url']); ?>"
                     alt="<?php echo esc_attr($hs['name']); ?>"
                     class="oha-headshot-circle"
                     title="<?php echo esc_attr($hs['name']); ?>">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php elseif ($promoted): ?>
        <!-- Promoted badge when no image and no headshots -->
        <?php endif; ?>

        <!-- Card header: time range + track -->
        <div class="oha-timeline-header">
            <div class="oha-class-time">
                <span class="oha-start-time"><?php echo esc_html(OHA_Schedule_Hub::format_time($start_time, $time_format)); ?></span>
                <span class="oha-time-separator">&#8212;</span>
                <span class="oha-end-time"><?php echo esc_html(OHA_Schedule_Hub::format_time($end_time, $time_format)); ?></span>
            </div>
            <?php if (!empty($tracks)): ?>
                <span class="oha-class-track"><?php echo esc_html($tracks[0]); ?></span>
            <?php endif; ?>
        </div>

        <!-- Card body -->
        <div class="oha-class-details">
            <?php if ($promoted && !$has_visual): ?>
                <div class="oha-promoted-badge">&#9733; Featured</div>
            <?php endif; ?>

            <h3 class="oha-class-title"><?php echo esc_html($class->post_title); ?></h3>

            <div class="oha-class-meta">
                <?php if (!empty($instructors)): ?>
                    <span class="oha-class-instructor">
                        <svg class="oha-icon" viewBox="0 0 16 16"><path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/></svg>
                        <?php echo esc_html(implode(' &amp; ', $instructors)); ?>
                    </span>
                <?php endif; ?>

                <?php if ($room): ?>
                    <span class="oha-class-room">
                        <svg class="oha-icon" viewBox="0 0 16 16"><path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/></svg>
                        <?php echo esc_html($room); ?>
                    </span>
                <?php endif; ?>

                <?php if ($level): ?>
                    <span class="oha-class-level level-<?php echo esc_attr($level); ?>">
                        <?php echo esc_html(ucfirst($level)); ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php
            // Short description on card; fall back to trimmed post_content if none set
            $card_desc = $short_desc
                ? $short_desc
                : ($show_descriptions && $class->post_content ? wp_trim_words($class->post_content, 30) : '');
            if ($card_desc):
            ?>
                <div class="oha-class-description">
                    <?php echo nl2br(esc_html($card_desc)); ?>
                </div>
            <?php endif; ?>

            <!-- Tap hint -->
            <div class="oha-tap-hint" aria-hidden="true">Tap for full details &#8250;</div>
        </div>

        <!-- Full description stored for modal (hidden, not rendered visually) -->
        <div class="oha-full-description" hidden aria-hidden="true">
            <?php echo wp_kses_post(wpautop($class->post_content)); ?>
        </div>

    </div>

    <?php endforeach; ?>
</div>
