<?php
// List view for schedule
$show_descriptions = get_option('oha_schedule_show_descriptions', 1);
?>

<div class="oha-list-view">
    <table class="oha-schedule-table">
        <thead>
            <tr>
                <th>Time</th>
                <th>Class</th>
                <th>Instructor</th>
                <th>Location</th>
                <th>Track</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($day_classes as $class):
                $start_time = get_post_meta($class->ID, '_oha_start_time', true);
                $end_time   = get_post_meta($class->ID, '_oha_end_time',   true);
                $room       = get_post_meta($class->ID, '_oha_room',       true);
                $short_desc = get_post_meta($class->ID, '_oha_short_desc', true);

                $instructors = wp_get_post_terms($class->ID, 'instructor',  array('fields' => 'names'));
                $tracks      = wp_get_post_terms($class->ID, 'class_track', array('fields' => 'names'));
                $track_slug  = !empty($tracks) ? sanitize_title($tracks[0]) : 'default';
                $promoted    = get_post_meta($class->ID, '_oha_promoted', true);
            ?>

            <tr class="oha-list-item track-<?php echo esc_attr($track_slug); ?> event-item"
                data-class-id="<?php echo esc_attr($class->ID); ?>"
                data-track="<?php echo esc_attr($track_slug); ?>"
                data-instructor="<?php echo !empty($instructors) ? esc_attr(sanitize_title($instructors[0])) : ''; ?>"
                data-event-id="class-<?php echo esc_attr($class->ID); ?>"
                data-event-title="<?php echo esc_attr($class->post_title); ?>"
                data-event-time="<?php echo esc_attr($day . ' ' . OHA_Schedule_Hub::format_time($start_time, $time_format) . ' - ' . OHA_Schedule_Hub::format_time($end_time, $time_format)); ?>"
                data-event-room="<?php echo esc_attr($room); ?>"
                data-event-speaker="<?php echo !empty($instructors) ? esc_attr($instructors[0]) : ''; ?>"
                data-event-promoted="<?php echo $promoted ? '1' : '0'; ?>">
                
                <td class="oha-list-time">
                    <?php echo esc_html(OHA_Schedule_Hub::format_time($start_time, $time_format)); ?><br>
                    <small><?php echo esc_html(OHA_Schedule_Hub::format_time($end_time, $time_format)); ?></small>
                </td>
                
                <td class="oha-list-class">
                    <?php if ($promoted): ?>
                        <span class="oha-promoted-badge oha-promoted-badge--inline">&#9733; Featured</span><br>
                    <?php endif; ?>
                    <strong><?php echo esc_html($class->post_title); ?></strong>
                    <?php
                    $card_desc = $short_desc
                        ? $short_desc
                        : ($show_descriptions && $class->post_content ? wp_trim_words($class->post_content, 20) : '');
                    if ($card_desc):
                    ?>
                        <div class="oha-list-description">
                            <?php echo esc_html($card_desc); ?>
                        </div>
                    <?php endif; ?>
                </td>
                
                <td class="oha-list-instructor">
                    <?php echo !empty($instructors) ? esc_html($instructors[0]) : '—'; ?>
                </td>
                
                <td class="oha-list-room">
                    <?php echo $room ? esc_html($room) : '—'; ?>
                </td>
                
                <td class="oha-list-track">
                    <?php if (!empty($tracks)): ?>
                        <span class="track-badge track-<?php echo esc_attr($track_slug); ?>">
                            <?php echo esc_html($tracks[0]); ?>
                        </span>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
            
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
