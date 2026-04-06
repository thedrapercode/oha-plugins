<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Build query
$args = array(
    'post_type'      => 'oha_class',
    'posts_per_page' => -1,
    'orderby'        => 'meta_value',
    'meta_key'       => '_oha_start_time',
    'order'          => 'ASC',
    'tax_query'      => array(),
);

if (!empty($atts['day'])) {
    $args['tax_query'][] = array('taxonomy' => 'class_day',    'field' => 'slug', 'terms' => sanitize_title($atts['day']));
}
if (!empty($atts['instructor'])) {
    $args['tax_query'][] = array('taxonomy' => 'instructor',   'field' => 'slug', 'terms' => sanitize_title($atts['instructor']));
}
if (!empty($atts['track'])) {
    $args['tax_query'][] = array('taxonomy' => 'class_track',    'field' => 'slug', 'terms' => sanitize_title($atts['track']));
}
if (!empty($atts['year'])) {
    $args['tax_query'][] = array('taxonomy' => 'convention_year', 'field' => 'name', 'terms' => sanitize_text_field($atts['year']));
}
if (empty($args['tax_query'])) {
    unset($args['tax_query']);
}

$classes      = get_posts($args);
$time_format  = get_option('oha_schedule_time_format', '12h');
$map_url      = !empty($atts['map_url']) ? esc_url($atts['map_url']) : '';
$show_filters = !empty($atts['show_filters']);
$show_search  = !empty($atts['show_search']);
$enable_print = !empty($atts['enable_print']);
$force_view   = !empty($atts['force_view']);
$day_order_raw = get_option('oha_schedule_day_order', '');

// Filter terms (only needed when filters are shown)
if ($show_filters) {
    $filter_tracks      = get_terms(array('taxonomy' => 'class_track', 'hide_empty' => true));
    $filter_instructors = get_terms(array('taxonomy' => 'instructor',  'hide_empty' => true));
    $filter_days        = get_terms(array('taxonomy' => 'class_day',   'hide_empty' => true));
    if (is_wp_error($filter_tracks))      $filter_tracks      = array();
    if (is_wp_error($filter_instructors)) $filter_instructors = array();
    if (is_wp_error($filter_days))        $filter_days        = array();

    // Sort the filter day dropdown by custom day order
    if (!empty($day_order_raw) && !empty($filter_days)) {
        $day_order_list = array_map('trim', explode(',', $day_order_raw));
        usort($filter_days, function($a, $b) use ($day_order_list) {
            $pos_a = array_search($a->name, $day_order_list);
            $pos_b = array_search($b->name, $day_order_list);
            if ($pos_a === false) $pos_a = PHP_INT_MAX;
            if ($pos_b === false) $pos_b = PHP_INT_MAX;
            return $pos_a - $pos_b;
        });
    }
}
?>

<div class="oha-schedule-container"
     data-view="<?php echo esc_attr($atts['view']); ?>"
     data-map-url="<?php echo $map_url; ?>">

    <!-- Controls: search · filters · view switcher · print (hidden when force_view is on) -->
    <?php if (!$force_view): ?>
    <div class="oha-schedule-controls">

        <?php if ($show_search): ?>
        <div class="oha-schedule-search">
            <input type="text" id="oha-search-input" placeholder="&#128269; Search classes&hellip;" autocomplete="off" />
            <button type="button" id="oha-search-btn" class="oha-btn-primary">Search</button>
        </div>
        <?php endif; ?>

        <?php if ($show_filters && (!empty($filter_tracks) || !empty($filter_instructors) || !empty($filter_days))): ?>
        <div class="oha-schedule-filters">
            <?php if (!empty($filter_days) && empty($atts['day'])): ?>
            <select id="oha-filter-day" class="oha-filter" aria-label="Filter by day">
                <option value="">All Days</option>
                <?php foreach ($filter_days as $term): ?>
                    <option value="<?php echo esc_attr($term->slug); ?>"><?php echo esc_html($term->name); ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <?php if (!empty($filter_tracks) && empty($atts['track'])): ?>
            <select id="oha-filter-track" class="oha-filter" aria-label="Filter by track">
                <option value="">All Tracks</option>
                <?php foreach ($filter_tracks as $term): ?>
                    <option value="<?php echo esc_attr($term->slug); ?>"><?php echo esc_html($term->name); ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <?php if (!empty($filter_instructors) && empty($atts['instructor'])): ?>
            <select id="oha-filter-instructor" class="oha-filter" aria-label="Filter by instructor">
                <option value="">All Instructors</option>
                <?php foreach ($filter_instructors as $term): ?>
                    <option value="<?php echo esc_attr($term->slug); ?>"><?php echo esc_html($term->name); ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <button type="button" id="oha-clear-filters" class="oha-btn-secondary">Clear</button>
        </div>
        <?php endif; ?>

        <div class="oha-controls-bottom">
            <?php if (!$force_view): ?>
            <div class="oha-schedule-view-switcher">
                <button type="button" class="oha-view-btn" data-view="timeline">Cards</button>
                <button type="button" class="oha-view-btn" data-view="compact">Quick List</button>
                <button type="button" class="oha-view-btn" data-view="grid">Grid</button>
                <button type="button" class="oha-view-btn" data-view="list">Table</button>
            </div>
            <?php endif; ?>

            <?php if ($enable_print): ?>
            <button type="button" id="oha-print-btn" class="oha-btn-secondary oha-print-btn" title="Print schedule">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" style="vertical-align:middle;margin-right:4px;">
                    <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                    <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
                </svg>Print
            </button>
            <?php endif; ?>
        </div>

    </div><!-- .oha-schedule-controls -->
    <?php endif; // force_view ?>

    <div class="oha-schedule-content">
        <?php if (empty($classes)): ?>
            <p class="oha-no-classes">No classes scheduled yet. Check back soon!</p>
        <?php else:
            // Group by day
            $classes_by_day = array();
            foreach ($classes as $class) {
                $class_days = wp_get_post_terms($class->ID, 'class_day', array('fields' => 'names'));
                $day_name   = !empty($class_days) ? $class_days[0] : 'Unscheduled';
                $classes_by_day[$day_name][] = $class;
            }

            // Sort days by custom order (Schedule Settings > Day Order)
            if (!empty($day_order_raw)) {
                $day_order_list = array_map('trim', explode(',', $day_order_raw));
                $sorted_by_day  = array();
                foreach ($day_order_list as $ordered_day) {
                    if (isset($classes_by_day[$ordered_day])) {
                        $sorted_by_day[$ordered_day] = $classes_by_day[$ordered_day];
                    }
                }
                foreach ($classes_by_day as $day_name => $day_classes) {
                    if (!isset($sorted_by_day[$day_name])) {
                        $sorted_by_day[$day_name] = $day_classes;
                    }
                }
                $classes_by_day = $sorted_by_day;
            }

            foreach ($classes_by_day as $day => $day_classes):
                $day_slug = sanitize_title($day);
        ?>
        <div class="oha-day-section" data-day="<?php echo esc_attr($day_slug); ?>">

            <!-- Collapsible day header -->
            <button class="oha-day-title" type="button" aria-expanded="true" data-day-key="<?php echo esc_attr($day_slug); ?>">
                <span><?php echo esc_html($day); ?></span>
                <svg class="oha-day-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="6,9 12,15 18,9"/>
                </svg>
            </button>

            <div class="oha-day-body">
                <?php
                    include OHA_SCHEDULE_PLUGIN_DIR . 'templates/views/timeline-view.php';
                    include OHA_SCHEDULE_PLUGIN_DIR . 'templates/views/grid-view.php';
                    include OHA_SCHEDULE_PLUGIN_DIR . 'templates/views/list-view.php';
                    include OHA_SCHEDULE_PLUGIN_DIR . 'templates/views/compact-view.php';
                ?>
            </div>

        </div><!-- .oha-day-section -->
        <?php endforeach; endif; ?>
    </div><!-- .oha-schedule-content -->

</div><!-- .oha-schedule-container -->
