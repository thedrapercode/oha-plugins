<?php
// Grid view for schedule
$show_descriptions = get_option('oha_schedule_show_descriptions', 1);
?>

<div class="oha-grid-view">
    <?php foreach ($day_classes as $class):
        $start_time = get_post_meta($class->ID, '_oha_start_time', true);
        $end_time   = get_post_meta($class->ID, '_oha_end_time',   true);
        $room       = get_post_meta($class->ID, '_oha_room',       true);
        $level      = get_post_meta($class->ID, '_oha_level',      true);
        $short_desc = get_post_meta($class->ID, '_oha_short_desc', true);

        $instructors = wp_get_post_terms($class->ID, 'instructor',   array('fields' => 'names'));
        $tracks      = wp_get_post_terms($class->ID, 'class_track',  array('fields' => 'names'));
        $track_slug  = !empty($tracks) ? sanitize_title($tracks[0]) : 'default';
        $promoted    = get_post_meta($class->ID, '_oha_promoted', true);
    ?>

    <div class="oha-class-card track-<?php echo esc_attr($track_slug); ?> event-item"
         data-class-id="<?php echo esc_attr($class->ID); ?>"
         data-track="<?php echo esc_attr($track_slug); ?>"
         data-instructor="<?php echo !empty($instructors) ? esc_attr(sanitize_title($instructors[0])) : ''; ?>"
         data-event-id="class-<?php echo esc_attr($class->ID); ?>"
         data-event-title="<?php echo esc_attr($class->post_title); ?>"
         data-event-time="<?php echo esc_attr($day . ' ' . OHA_Schedule_Hub::format_time($start_time, $time_format) . ' - ' . OHA_Schedule_Hub::format_time($end_time, $time_format)); ?>"
         data-event-room="<?php echo esc_attr($room); ?>"
         data-event-speaker="<?php echo !empty($instructors) ? esc_attr($instructors[0]) : ''; ?>"
         data-event-promoted="<?php echo $promoted ? '1' : '0'; ?>">

        <div class="oha-card-header">
            <?php if ($promoted): ?>
                <div class="oha-promoted-badge">&#9733; Featured</div>
            <?php endif; ?>
            <div class="oha-card-time">
                <?php echo esc_html(OHA_Schedule_Hub::format_time($start_time, $time_format)); ?>
            </div>
            <?php if (!empty($tracks)): ?>
                <div class="oha-card-track"><?php echo esc_html($tracks[0]); ?></div>
            <?php endif; ?>
        </div>
        
        <div class="oha-card-body">
            <h3 class="oha-card-title"><?php echo esc_html($class->post_title); ?></h3>
            
            <?php if (!empty($instructors)): ?>
                <p class="oha-card-instructor">
                    <strong>Instructor:</strong> <?php echo esc_html($instructors[0]); ?>
                </p>
            <?php endif; ?>
            
            <?php if ($room): ?>
                <p class="oha-card-room">
                    <strong>Location:</strong> <?php echo esc_html($room); ?>
                </p>
            <?php endif; ?>
            
            <?php if ($level): ?>
                <p class="oha-card-level">
                    <strong>Level:</strong> <span class="level-badge level-<?php echo esc_attr($level); ?>"><?php echo esc_html(ucfirst($level)); ?></span>
                </p>
            <?php endif; ?>
            
            <?php
            $card_desc = $short_desc
                ? $short_desc
                : ($show_descriptions && $class->post_content ? wp_trim_words($class->post_content, 30) : '');
            if ($card_desc):
            ?>
                <div class="oha-card-description">
                    <?php echo nl2br(esc_html($card_desc)); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="oha-card-footer">
            <span class="oha-card-duration">
                <?php 
                $start = strtotime($start_time);
                $end = strtotime($end_time);
                $duration = ($end - $start) / 60;
                echo esc_html($duration . ' minutes');
                ?>
            </span>
        </div>
    </div>
    
    <?php endforeach; ?>
</div>
