<?php
/**
 * OHA Map Shortcode Handler
 * Renders the modern map interface
 */

if (!defined('ABSPATH')) exit;

class OHA_Map_Shortcode {

    /**
     * Initialize shortcodes
     */
    public static function init() {
        add_shortcode('oha_modern_map', [__CLASS__, 'render_map']);
        add_shortcode('oha_region_selector', [__CLASS__, 'render_region_selector']);
    }

    /**
     * Render the main map shortcode
     *
     * Usage: [oha_modern_map region="central" type="all" height="600px"]
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_map($atts) {
        $atts = shortcode_atts([
            'region'       => '',
            'type'         => 'all',
            'height'       => '600px',
            'show_filters' => 'true',
            'show_list'    => 'true',
            'clustering'   => 'true',
            'initial_view' => 'map',
            'class'        => '',
        ], $atts, 'oha_modern_map');

        $map_id        = 'oha-map-' . uniqid();
        $events_config = OHA_Map_Admin::get_events_settings();
        $show_events   = $events_config['enabled'];

        ob_start();
        ?>
        <div class="oha-map-wrapper <?php echo esc_attr($atts['class']); ?>"
             id="<?php echo esc_attr($map_id); ?>"
             data-region="<?php echo esc_attr($atts['region']); ?>"
             data-type="<?php echo esc_attr($atts['type']); ?>"
             data-clustering="<?php echo esc_attr($atts['clustering']); ?>"
             data-initial-view="<?php echo esc_attr($atts['initial_view']); ?>">

            <!-- Mobile floating pill bar (shown below 768px via CSS) -->
            <div class="oha-mob-map-pill-bar">
                <!-- Search row -->
                <div class="oha-mob-search-row">
                    <div class="oha-search-wrap">
                        <svg class="oha-search-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                        <input type="text" class="oha-search-input" placeholder="<?php esc_attr_e('Search haunts or city...', 'oha-modern-map'); ?>" autocomplete="off" />
                        <button class="oha-search-clear" style="display:none" title="<?php esc_attr_e('Clear', 'oha-modern-map'); ?>">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                        </button>
                        <div class="oha-autocomplete-list"></div>
                    </div>
                </div>
                <!-- Quick actions row -->
                <div class="oha-mob-actions-row">
                    <button class="oha-near-me-btn oha-mob-action-pill" type="button">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3c-.46-4.17-3.77-7.48-7.94-7.94V1h-2v2.06C6.83 3.52 3.52 6.83 3.06 11H1v2h2.06c.46 4.17 3.77 7.48 7.94 7.94V23h2v-2.06c4.17-.46 7.48-3.77 7.94-7.94H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/></svg>
                        <?php _e('Near Me', 'oha-modern-map'); ?>
                    </button>
                    <button class="oha-mob-filter-pill" type="button">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/></svg>
                        <?php _e('Filters', 'oha-modern-map'); ?>
                        <span class="oha-mob-pill-badge"></span>
                    </button>
                    <button class="oha-fav-filter-btn oha-mob-action-pill" type="button">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                        <?php _e('Saved', 'oha-modern-map'); ?>
                    </button>
                    <button class="oha-mob-reset-pill" type="button" title="<?php esc_attr_e('Reset', 'oha-modern-map'); ?>">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><path d="M12 5V1L7 6l5 5V7c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6H4c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z"/></svg>
                    </button>
                </div>
            </div>

            <!-- Mobile filter overlay -->
            <div class="oha-mob-filter-overlay"></div>

            <!-- Mobile filter sheet (slide-up) -->
            <div class="oha-mob-filter-sheet">
                <div class="oha-mob-filter-handle"></div>
                <div class="oha-mob-filter-header">
                    <span><?php _e('Filters', 'oha-modern-map'); ?></span>
                    <button type="button" class="oha-mob-filter-done"><?php _e('Done', 'oha-modern-map'); ?></button>
                </div>
                <div class="oha-mob-filter-body">
                    <div class="oha-mob-filter-section">
                        <p class="oha-mob-filter-label"><?php _e('Region', 'oha-modern-map'); ?></p>
                        <div class="oha-mob-region-tabs oha-region-pills">
                            <button class="oha-pill <?php echo empty($atts['region']) ? 'active' : ''; ?>" data-region="" type="button">
                                <?php _e('All Ohio', 'oha-modern-map'); ?>
                            </button>
                            <?php foreach (OHA_Map_Regions::get_region_names() as $slug => $name) : ?>
                                <button class="oha-pill <?php echo $atts['region'] === $slug ? 'active' : ''; ?>"
                                        data-region="<?php echo esc_attr($slug); ?>" type="button">
                                    <?php echo esc_html($name); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="oha-mob-filter-section">
                        <p class="oha-mob-filter-label"><?php _e('Type', 'oha-modern-map'); ?></p>
                        <div class="oha-type-toggles">
                            <label class="oha-toggle">
                                <input type="checkbox" value="featured" checked>
                                <span class="oha-toggle-label">
                                    <span class="oha-icon oha-icon-featured"></span>
                                    <?php _e('Featured', 'oha-modern-map'); ?>
                                </span>
                            </label>
                            <label class="oha-toggle">
                                <input type="checkbox" value="professional" checked>
                                <span class="oha-toggle-label">
                                    <span class="oha-icon oha-icon-professional"></span>
                                    <?php _e('Professional', 'oha-modern-map'); ?>
                                </span>
                            </label>
                            <label class="oha-toggle">
                                <input type="checkbox" value="home_haunt" checked>
                                <span class="oha-toggle-label">
                                    <span class="oha-icon oha-icon-home"></span>
                                    <?php _e('Home Haunts', 'oha-modern-map'); ?>
                                </span>
                            </label>
                            <label class="oha-toggle">
                                <input type="checkbox" value="free" checked>
                                <span class="oha-toggle-label">
                                    <span class="oha-icon oha-icon-free"></span>
                                    <?php _e('Other', 'oha-modern-map'); ?>
                                </span>
                            </label>
                        </div>
                    </div>
                    <p class="oha-mob-filter-count">
                        <span class="oha-count">0</span> <?php _e('haunts', 'oha-modern-map'); ?>
                    </p>
                </div>
            </div>

            <?php if ($atts['show_filters'] === 'true') : ?>
            <div class="oha-map-controls">
                <div class="oha-map-controls-inner">

                    <!-- Region Filter -->
                    <div class="oha-filter-group oha-filter-regions">
                        <label class="oha-filter-label"><?php _e('Region', 'oha-modern-map'); ?></label>
                        <div class="oha-region-pills">
                            <button class="oha-pill <?php echo empty($atts['region']) ? 'active' : ''; ?>" data-region="">
                                <?php _e('All Ohio', 'oha-modern-map'); ?>
                            </button>
                            <?php foreach (OHA_Map_Regions::get_region_names() as $slug => $name) : ?>
                                <button class="oha-pill <?php echo $atts['region'] === $slug ? 'active' : ''; ?>"
                                        data-region="<?php echo esc_attr($slug); ?>">
                                    <?php echo esc_html($name); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Type Filter -->
                    <div class="oha-filter-group oha-filter-types">
                        <label class="oha-filter-label"><?php _e('Type', 'oha-modern-map'); ?></label>
                        <div class="oha-type-toggles">
                            <label class="oha-toggle">
                                <input type="checkbox" value="featured" checked>
                                <span class="oha-toggle-label">
                                    <span class="oha-icon oha-icon-featured"></span>
                                    <?php _e('Featured', 'oha-modern-map'); ?>
                                </span>
                            </label>
                            <label class="oha-toggle">
                                <input type="checkbox" value="professional" checked>
                                <span class="oha-toggle-label">
                                    <span class="oha-icon oha-icon-professional"></span>
                                    <?php _e('Professional', 'oha-modern-map'); ?>
                                </span>
                            </label>
                            <label class="oha-toggle">
                                <input type="checkbox" value="home_haunt" checked>
                                <span class="oha-toggle-label">
                                    <span class="oha-icon oha-icon-home"></span>
                                    <?php _e('Home Haunts', 'oha-modern-map'); ?>
                                </span>
                            </label>
                            <label class="oha-toggle">
                                <input type="checkbox" value="free" checked>
                                <span class="oha-toggle-label">
                                    <span class="oha-icon oha-icon-free"></span>
                                    <?php _e('Other', 'oha-modern-map'); ?>
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Layer Toggles (Haunts / Events) -->
                    <?php if ($show_events) : ?>
                    <div class="oha-filter-group oha-filter-layers">
                        <label class="oha-filter-label"><?php _e('Layers', 'oha-modern-map'); ?></label>
                        <div class="oha-layer-toggles">
                            <button class="oha-layer-btn active" data-layer="haunts">
                                <svg class="oha-layer-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                                <?php _e('Haunts', 'oha-modern-map'); ?>
                            </button>
                            <button class="oha-layer-btn <?php echo $events_config['show_by_default'] ? 'active' : ''; ?>" data-layer="events">
                                <svg class="oha-layer-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg>
                                <?php echo esc_html($events_config['layer_label'] ?: __('Events', 'oha-modern-map')); ?>
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Search with autocomplete -->
                    <div class="oha-filter-group oha-filter-search">
                        <label class="oha-filter-label"><?php _e('Search', 'oha-modern-map'); ?></label>
                        <div class="oha-search-wrap">
                            <svg class="oha-search-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                            <input type="text" class="oha-search-input" placeholder="<?php esc_attr_e('Search haunts or city...', 'oha-modern-map'); ?>" autocomplete="off" />
                            <button class="oha-search-clear" style="display:none" title="<?php esc_attr_e('Clear', 'oha-modern-map'); ?>">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                            </button>
                            <div class="oha-autocomplete-list"></div>
                        </div>
                    </div>

                    <!-- Near Me + Favorites -->
                    <div class="oha-filter-group oha-filter-quick">
                        <label class="oha-filter-label"><?php _e('Quick', 'oha-modern-map'); ?></label>
                        <div class="oha-quick-btns">
                            <button class="oha-near-me-btn oha-quick-btn" title="<?php esc_attr_e('Show haunts near my location', 'oha-modern-map'); ?>">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3c-.46-4.17-3.77-7.48-7.94-7.94V1h-2v2.06C6.83 3.52 3.52 6.83 3.06 11H1v2h2.06c.46 4.17 3.77 7.48 7.94 7.94V23h2v-2.06c4.17-.46 7.48-3.77 7.94-7.94H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/></svg>
                                <?php _e('Near Me', 'oha-modern-map'); ?>
                            </button>
                            <button class="oha-fav-filter-btn oha-quick-btn" title="<?php esc_attr_e('Show saved favorites', 'oha-modern-map'); ?>">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                                <?php _e('Favorites', 'oha-modern-map'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- View Toggle -->
                    <?php if ($atts['show_list'] === 'true') : ?>
                    <div class="oha-filter-group oha-filter-view">
                        <label class="oha-filter-label"><?php _e('View', 'oha-modern-map'); ?></label>
                        <div class="oha-view-toggle">
                            <button class="oha-view-btn <?php echo $atts['initial_view'] === 'map' ? 'active' : ''; ?>" data-view="map">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/></svg>
                                <?php _e('Map', 'oha-modern-map'); ?>
                            </button>
                            <button class="oha-view-btn <?php echo $atts['initial_view'] === 'list' ? 'active' : ''; ?>" data-view="list">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg>
                                <?php _e('List', 'oha-modern-map'); ?>
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
            <?php endif; ?>

            <div class="oha-map-content">
                <!-- Map View -->
                <div class="oha-map-view <?php echo $atts['initial_view'] === 'map' ? 'active' : ''; ?>">
                    <div id="<?php echo esc_attr($map_id); ?>-map"
                         class="oha-map-container"
                         style="height: <?php echo esc_attr($atts['height']); ?>"></div>

                    <!-- Custom Map Controls -->
                    <div class="oha-map-overlay-controls">
                        <button class="oha-map-control oha-zoom-in" title="<?php _e('Zoom In', 'oha-modern-map'); ?>">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                        </button>
                        <button class="oha-map-control oha-zoom-out" title="<?php _e('Zoom Out', 'oha-modern-map'); ?>">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 13H5v-2h14v2z"/></svg>
                        </button>
                        <button class="oha-map-control oha-reset-view" title="<?php _e('Reset View', 'oha-modern-map'); ?>">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 5V1L7 6l5 5V7c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6H4c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z"/></svg>
                        </button>
                        <button class="oha-map-control oha-panel-toggle" title="<?php esc_attr_e('Toggle list panel', 'oha-modern-map'); ?>">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg>
                        </button>
                    </div>

                    <!-- Loading Indicator -->
                    <div class="oha-map-loading">
                        <div class="oha-spinner"></div>
                        <p><?php _e('Loading haunts...', 'oha-modern-map'); ?></p>
                    </div>
                </div>

                <!-- List View -->
                <?php if ($atts['show_list'] === 'true') : ?>
                <div class="oha-list-view <?php echo $atts['initial_view'] === 'list' ? 'active' : ''; ?>">
                    <div class="oha-list-container" style="max-height: <?php echo esc_attr($atts['height']); ?>"></div>
                </div>
                <?php endif; ?>

                <!-- Side Panel — desktop only, toggled by panel-toggle button -->
                <div class="oha-map-side-panel">
                    <div class="oha-panel-header">
                        <span class="oha-panel-header-label"><?php _e('In View', 'oha-modern-map'); ?></span>
                        <span class="oha-panel-count"><span class="oha-panel-haunt-count">0</span> <?php _e('haunts', 'oha-modern-map'); ?></span>
                    </div>
                    <div class="oha-panel-list"></div>
                </div>
            </div>

            <!-- Bottom Sheet — Google Maps-style slide-up card -->
            <div class="oha-bottom-sheet" role="dialog" aria-modal="true" aria-label="<?php _e('Haunt Details', 'oha-modern-map'); ?>">
                <div class="oha-bottom-sheet-overlay"></div>
                <div class="oha-bottom-sheet-panel">
                    <div class="oha-bottom-sheet-handle" aria-hidden="true"></div>
                    <div class="oha-bottom-sheet-content">
                        <!-- Populated by JS when a marker is clicked -->
                    </div>
                </div>
            </div>

            <!-- Region Info Sheet — slide-up panel with region description and highlights -->
            <div class="oha-region-info-sheet" role="dialog" aria-modal="true" aria-label="<?php _e('Region Information', 'oha-modern-map'); ?>">
                <div class="oha-bottom-sheet-overlay"></div>
                <div class="oha-bottom-sheet-panel">
                    <div class="oha-bottom-sheet-handle" aria-hidden="true"></div>
                    <div class="oha-region-info-content">
                        <!-- Populated by JS when a region fill is clicked -->
                    </div>
                </div>
            </div>

            <!-- Results Counter -->
            <div class="oha-results-counter" aria-live="polite">
                <span class="oha-count">0</span>
                <span class="oha-label"><?php _e('haunts', 'oha-modern-map'); ?></span>
            </div>

        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render region selector (for homepage)
     *
     * Usage: [oha_region_selector style="grid" show_counts="true"]
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_region_selector($atts) {
        $atts = shortcode_atts([
            'style'       => 'grid',
            'show_counts' => 'true',
            'title'       => 'Find Haunts Near You',
            'subtitle'    => 'Select your region to explore haunted attractions',
        ], $atts, 'oha_region_selector');

        ob_start();
        ?>
        <div class="oha-region-selector oha-region-selector-<?php echo esc_attr($atts['style']); ?>">
            <?php if ($atts['title']) : ?>
                <div class="oha-region-selector-header">
                    <h2><?php echo esc_html($atts['title']); ?></h2>
                    <?php if ($atts['subtitle']) : ?>
                        <p><?php echo esc_html($atts['subtitle']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($atts['style'] === 'grid') : ?>
                <div class="oha-region-grid">
                    <?php foreach (OHA_Map_Regions::get_regions() as $slug => $region) :
                        $count = $atts['show_counts'] === 'true' ? OHA_Map_Data::get_counts($slug)['total'] : 0;
                        $url   = add_query_arg('region', $slug, get_permalink());
                        ?>
                        <a href="<?php echo esc_url($url); ?>" class="oha-region-card" data-region="<?php echo esc_attr($slug); ?>">
                            <h3><?php echo esc_html($region['name']); ?></h3>
                            <?php if ($atts['show_counts'] === 'true') : ?>
                                <span class="oha-region-count"><?php echo $count; ?> <?php _e('haunts', 'oha-modern-map'); ?></span>
                            <?php endif; ?>
                            <span class="oha-region-arrow">→</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="oha-ohio-map">
                    <svg viewBox="0 0 600 400" class="oha-ohio-svg">
                        <text x="300" y="200" text-anchor="middle" class="oha-map-placeholder">
                            <?php _e('Ohio Regional Map', 'oha-modern-map'); ?>
                        </text>
                    </svg>
                </div>
            <?php endif; ?>
        </div>
        <?php

        return ob_get_clean();
    }
}
