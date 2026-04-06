<?php
// Compact view (Quick List) for schedule - minimal, itinerary-style
// Supports end-time markers: events with "Show End Time" checked inject a separate
// closing line at the end time (e.g. "5:00 PM  Show Floor Closed").

// Step 1: Build a unified list of start entries + optional end-time markers
$list_items = array();

foreach ($day_classes as $class) {
    $start_time    = get_post_meta($class->ID, '_oha_start_time', true);
    $end_time      = get_post_meta($class->ID, '_oha_end_time', true);
    $room          = get_post_meta($class->ID, '_oha_room', true);
    $show_end      = get_post_meta($class->ID, '_oha_show_end_time', true);
    $end_label     = get_post_meta($class->ID, '_oha_end_time_label', true);
    $instructors   = wp_get_post_terms($class->ID, 'instructor', array('fields' => 'names'));
    $tracks        = wp_get_post_terms($class->ID, 'class_track', array('fields' => 'names'));
    $track_slug    = !empty($tracks) ? sanitize_title($tracks[0]) : 'default';
    $promoted      = get_post_meta($class->ID, '_oha_promoted', true);

    // Normal class entry (start time)
    $list_items[] = array(
        'type'        => 'class',
        'sort_time'   => $start_time,
        'class'       => $class,
        'start_time'  => $start_time,
        'end_time'    => $end_time,
        'room'        => $room,
        'instructors' => $instructors,
        'track_slug'  => $track_slug,
        'promoted'    => $promoted,
    );

    // Optional end-time marker entry
    if ($show_end === '1' && !empty($end_time)) {
        $list_items[] = array(
            'type'       => 'end_marker',
            'sort_time'  => $end_time,
            'end_time'   => $end_time,
            'label'      => !empty($end_label) ? $end_label : '',
            'track_slug' => $track_slug,
        );
    }
}

// Step 2: Sort all items by time (HH:MM string comparison works correctly)
usort($list_items, function($a, $b) {
    return strcmp($a['sort_time'], $b['sort_time']);
});
?>

<div class="oha-compact-view">
    <?php foreach ($list_items as $item):
        if ($item['type'] === 'end_marker'): ?>

        <div class="oha-compact-item oha-compact-end-marker track-<?php echo esc_attr($item['track_slug']); ?>">
            <span class="oha-compact-time">
                <?php echo esc_html(OHA_Schedule_Hub::format_time($item['end_time'], $time_format)); ?>
            </span>
            <?php if (!empty($item['label'])): ?>
            <span class="oha-compact-title oha-compact-end-label">
                <?php echo esc_html($item['label']); ?>
            </span>
            <?php endif; ?>
        </div>

        <?php else:
            $class = $item['class']; ?>

        <div class="oha-compact-item track-<?php echo esc_attr($item['track_slug']); ?> event-item"
             data-class-id="<?php echo esc_attr($class->ID); ?>"
             data-track="<?php echo esc_attr($item['track_slug']); ?>"
             data-instructor="<?php echo !empty($item['instructors']) ? esc_attr(sanitize_title($item['instructors'][0])) : ''; ?>"
             data-event-id="class-<?php echo esc_attr($class->ID); ?>"
             data-event-title="<?php echo esc_attr($class->post_title); ?>"
             data-event-time="<?php echo esc_attr($day . ' ' . OHA_Schedule_Hub::format_time($item['start_time'], $time_format) . ' - ' . OHA_Schedule_Hub::format_time($item['end_time'], $time_format)); ?>"
             data-event-room="<?php echo esc_attr($item['room']); ?>"
             data-event-speaker="<?php echo !empty($item['instructors']) ? esc_attr($item['instructors'][0]) : ''; ?>"
             data-event-promoted="<?php echo $item['promoted'] ? '1' : '0'; ?>">

            <?php if ($item['promoted']): ?>
                <span class="oha-promoted-badge oha-promoted-badge--compact">&#9733; Featured</span>
            <?php endif; ?>
            <span class="oha-compact-time">
                <?php echo esc_html(OHA_Schedule_Hub::format_time($item['start_time'], $time_format)); ?>
            </span>

            <span class="oha-compact-title">
                <?php echo esc_html($class->post_title); ?>
            </span>

            <?php if (!empty($item['instructors'])): ?>
                <span class="oha-compact-instructor">
                    &mdash; <?php echo esc_html($item['instructors'][0]); ?>
                </span>
            <?php endif; ?>

            <?php if ($item['room']): ?>
                <span class="oha-compact-room">
                    (<?php echo esc_html($item['room']); ?>)
                </span>
            <?php endif; ?>
        </div>

        <?php endif; ?>
    <?php endforeach; ?>
</div>
