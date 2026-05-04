<?php
/**
 * OHA Convention Shortcode
 * Renders the indoor convention map interface.
 *
 * Shortcodes:
 *   [oha_convention_map]
 *   [oha_convention_directory]
 */

if (!defined('ABSPATH')) exit;

class OHA_Convention_Shortcode {

    public static function init() {
        add_shortcode('oha_convention_map', [__CLASS__, 'render_map']);
        add_shortcode('oha_convention_directory', [__CLASS__, 'render_directory']);
    }

    // -----------------------------------------------------------------------
    // [oha_convention_map]
    // -----------------------------------------------------------------------

    /**
     * Render the indoor convention map.
     *
     * Attributes:
     *   floor        — Initial floor ID (default: 1)
     *   height       — Map height CSS value (default: 600px) [non-mobile only]
     *   show_filters — Show type filter buttons (default: true) [non-mobile only]
     *   show_list    — Show booth list panel (default: true) [non-mobile only]
     *   mobile       — Full-screen app mode with floating UI (default: false)
     *   class        — Extra CSS classes on the wrapper
     */
    public static function render_map($atts) {
        $settings = OHA_Convention_Data::get_settings();

        // If convention mode is not enabled, output nothing (admin note only for logged-in users)
        if (empty($settings['enabled'])) {
            if (current_user_can('manage_options')) {
                return '<p style="border:2px dashed #ff6b35;padding:16px;color:#ff6b35;">'
                    . __('<strong>OHA Convention Map:</strong> Convention Mode is not enabled. Go to OHA Map &rsaquo; Convention Settings to enable it.', 'oha-modern-map')
                    . '</p>';
            }
            return '';
        }

        $atts = shortcode_atts([
            'floor'        => 1,
            'height'       => '600px',
            'show_filters' => 'true',
            'show_list'    => 'true',
            'mobile'       => 'false',
            'class'        => '',
        ], $atts, 'oha_convention_map');

        $map_id    = 'oha-conv-' . uniqid();
        $floors    = $settings['floors'] ?? [];
        $floor_id  = intval($atts['floor']) ?: 1;
        $floor     = OHA_Convention_Data::get_floor($floor_id) ?? ($floors[0] ?? null);
        $types     = OHA_Convention_Data::get_booth_types();
        $is_mobile = ($atts['mobile'] === 'true');

        // Build floor JS data (id, label, image_url, bg_color)
        $floors_js = array_map(function($f) {
            return [
                'id'       => (int) $f['id'],
                'label'    => $f['label'],
                'imageUrl' => $f['image_url'] ?? '',
                'bgColor'  => $f['bg_color'] ?? '#1a1a2e',
            ];
        }, $floors);

        ob_start();

        if ($is_mobile) :
        // ===================================================================
        // MOBILE APP MODE — full-height map with floating overlay UI
        // ===================================================================
        ?>
        <div class="oha-convention-wrapper oha-conv-mobile <?php echo esc_attr($atts['class']); ?>"
             id="<?php echo esc_attr($map_id); ?>"
             data-floor="<?php echo esc_attr($floor_id); ?>"
             data-event="<?php echo esc_attr($settings['event_name'] ?? ''); ?>">

            <!-- Map fills full height of wrapper -->
            <div class="oha-conv-map-area">
                <div class="oha-conv-map" id="<?php echo esc_attr($map_id); ?>-map"
                     aria-label="<?php esc_attr_e('Convention floor map', 'oha-modern-map'); ?>">
                </div>

                <!-- Loading overlay -->
                <div class="oha-conv-loading">
                    <span class="oha-conv-spinner"></span>
                    <span><?php _e('Loading floor plan...', 'oha-modern-map'); ?></span>
                </div>

                <!-- Floating top-left: Filters pill -->
                <button class="oha-mob-filters-pill" data-mob-action="open-filters"
                        aria-label="<?php esc_attr_e('Open filters', 'oha-modern-map'); ?>">
                    <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" width="14" height="14">
                        <path d="M3 5h14M6 10h8M9 15h2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    <span class="oha-mob-pill-text"><?php _e('Filters', 'oha-modern-map'); ?></span>
                    <span class="oha-mob-pill-badge" style="display:none">0</span>
                </button>

                <!-- Floating top-right: Search -->
                <div class="oha-mob-top-right">
                    <button class="oha-mob-action-btn oha-mob-search-toggle"
                            aria-label="<?php esc_attr_e('Search booths', 'oha-modern-map'); ?>">
                        <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" width="18" height="18">
                            <circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M15 15l3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <!-- Search bar (slides down from top, hidden by default) -->
                <div class="oha-mob-search-bar" style="display:none">
                    <div class="oha-mob-search-inner">
                        <svg viewBox="0 0 20 20" fill="none" width="16" height="16" style="color:rgba(232,232,240,0.45);flex-shrink:0">
                            <circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M15 15l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                        <input type="search" class="oha-conv-search"
                               placeholder="<?php esc_attr_e('Search booths...', 'oha-modern-map'); ?>">
                        <button class="oha-mob-search-close"
                                aria-label="<?php esc_attr_e('Close search', 'oha-modern-map'); ?>">
                            <svg viewBox="0 0 20 20" fill="none" width="16" height="16">
                                <path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Floating bottom-right: Zoom controls -->
                <div class="oha-conv-map-overlay-controls">
                    <button class="oha-conv-zoom-btn" data-action="zoom-in" aria-label="<?php esc_attr_e('Zoom in', 'oha-modern-map'); ?>">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                    </button>
                    <button class="oha-conv-zoom-btn" data-action="zoom-out" aria-label="<?php esc_attr_e('Zoom out', 'oha-modern-map'); ?>">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                    </button>
                    <button class="oha-conv-zoom-btn" data-action="reset" aria-label="<?php esc_attr_e('Reset view', 'oha-modern-map'); ?>">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3.05 11a9 9 0 1 1 .5 4M3 15v-4h4"/>
                        </svg>
                    </button>
                </div>

                <!-- Floating bottom-center: List View toggle pill -->
                <button class="oha-mob-list-toggle-btn">
                    <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" width="14" height="14">
                        <path d="M4 6h12M4 10h12M4 14h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    <span><?php _e('List View', 'oha-modern-map'); ?></span>
                </button>

            </div><!-- .oha-conv-map-area -->

            <!-- ---------------------------------------------------------------
                 Filter sheet (slides up from bottom over the map)
            --------------------------------------------------------------- -->
            <div class="oha-mob-filter-sheet" aria-hidden="true">
                <div class="oha-mob-overlay"></div>
                <div class="oha-mob-sheet-panel">
                    <div class="oha-mob-sheet-handle"></div>
                    <div class="oha-mob-sheet-body">

                        <?php if (count($floors) > 1) : ?>
                        <div class="oha-mob-filter-section">
                            <span class="oha-mob-section-label"><?php _e('Floor', 'oha-modern-map'); ?></span>
                            <div class="oha-floor-pills">
                                <?php foreach ($floors as $f) : ?>
                                <button class="oha-pill oha-floor-btn <?php echo ((int) $f['id'] === $floor_id) ? 'active' : ''; ?>"
                                        data-floor="<?php echo esc_attr($f['id']); ?>">
                                    <?php echo esc_html($f['label']); ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="oha-mob-filter-section">
                            <span class="oha-mob-section-label"><?php _e('Type', 'oha-modern-map'); ?></span>
                            <div class="oha-conv-type-filters">
                                <button class="oha-pill oha-conv-type-all active" data-type="all">
                                    <?php _e('All', 'oha-modern-map'); ?>
                                </button>
                                <?php foreach ($types as $key => $label) : ?>
                                <button class="oha-pill oha-conv-type-btn" data-type="<?php echo esc_attr($key); ?>"
                                        style="--type-color:<?php echo esc_attr(OHA_Convention_Data::get_color_for_type($key)); ?>">
                                    <span class="oha-conv-type-dot"></span>
                                    <?php echo esc_html($label); ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="oha-mob-filter-footer">
                            <span class="oha-mob-filter-count">
                                <span class="oha-conv-count">0</span> <?php _e('booths', 'oha-modern-map'); ?>
                            </span>
                            <button class="oha-mob-sheet-done">
                                <?php _e('Done', 'oha-modern-map'); ?>
                            </button>
                        </div>

                    </div>
                </div>
            </div><!-- .oha-mob-filter-sheet -->

            <!-- ---------------------------------------------------------------
                 List view (full overlay — toggled by pill, replaces map view)
            --------------------------------------------------------------- -->
            <div class="oha-mob-list-view" style="display:none" aria-hidden="true">
                <div class="oha-mob-list-topbar">
                    <button class="oha-mob-map-toggle-btn">
                        <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" width="14" height="14">
                            <path d="M1 4l6 2 6-2 6 2v12l-6-2-6 2-6-2V4z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                            <path d="M7 6v12M13 4v12" stroke="currentColor" stroke-width="1.4"/>
                        </svg>
                        <span><?php _e('Map', 'oha-modern-map'); ?></span>
                    </button>
                    <span class="oha-mob-list-title">
                        <?php echo esc_html($settings['event_name'] ?? __('Booths', 'oha-modern-map')); ?>
                    </span>
                    <button class="oha-mob-list-filter-btn" data-mob-action="open-filters"
                            aria-label="<?php esc_attr_e('Filter booths', 'oha-modern-map'); ?>">
                        <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" width="16" height="16">
                            <path d="M3 5h14M6 10h8M9 15h2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                        <span class="oha-mob-pill-badge" style="display:none">0</span>
                    </button>
                </div>
                <div class="oha-conv-list" role="list"
                     aria-label="<?php esc_attr_e('Booth list', 'oha-modern-map'); ?>">
                    <div class="oha-conv-list-empty" style="display:none">
                        <?php _e('No booths match your filter.', 'oha-modern-map'); ?>
                    </div>
                </div>
            </div><!-- .oha-mob-list-view -->

            <!-- Bottom sheet (booth detail card) -->
            <div class="oha-bottom-sheet oha-conv-bottom-sheet" role="dialog" aria-modal="true"
                 aria-label="<?php esc_attr_e('Booth details', 'oha-modern-map'); ?>" aria-hidden="true">
                <div class="oha-bottom-sheet-overlay"></div>
                <div class="oha-bottom-sheet-panel">
                    <div class="oha-bottom-sheet-handle" aria-hidden="true"></div>
                    <div class="oha-bottom-sheet-content">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>

        </div><!-- .oha-convention-wrapper.oha-conv-mobile -->

        <script>
        (function() {
            var convData = <?php echo wp_json_encode([
                'mapId'        => $map_id . '-map',
                'wrapperId'    => $map_id,
                'ajaxUrl'      => admin_url('admin-ajax.php'),
                'nonce'        => wp_create_nonce('oha_map_nonce'),
                'pluginUrl'    => OHA_MODERN_MAP_URL,
                'floors'       => $floors_js,
                'initialFloor' => $floor_id,
                'showList'     => true,
                'mobile'       => true,
                'eventName'    => $settings['event_name'] ?? '',
                'venueName'    => $settings['venue_name'] ?? '',
            ]); ?>;
            window.ohaConventionConfig = window.ohaConventionConfig || [];
            window.ohaConventionConfig.push(convData);
        })();
        </script>
        <?php

        else :
        // ===================================================================
        // STANDARD MODE — existing layout (controls bar + map + list panel)
        // ===================================================================
        ?>
        <div class="oha-convention-wrapper <?php echo esc_attr($atts['class']); ?>"
             id="<?php echo esc_attr($map_id); ?>"
             data-floor="<?php echo esc_attr($floor_id); ?>"
             data-event="<?php echo esc_attr($settings['event_name'] ?? ''); ?>">

            <?php if ($atts['show_filters'] === 'true') : ?>
            <div class="oha-conv-controls">
                <div class="oha-conv-controls-inner">

                    <!-- Event Header -->
                    <?php if (!empty($settings['event_name'])) : ?>
                    <div class="oha-conv-event-header">
                        <span class="oha-conv-event-name"><?php echo esc_html($settings['event_name']); ?></span>
                        <?php if (!empty($settings['event_dates'])) : ?>
                            <span class="oha-conv-event-dates"><?php echo esc_html($settings['event_dates']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($settings['venue_name'])) : ?>
                            <span class="oha-conv-venue"><?php echo esc_html($settings['venue_name']); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Floor Selector (only shown if > 1 floor) -->
                    <?php if (count($floors) > 1) : ?>
                    <div class="oha-filter-group oha-conv-floor-group">
                        <label class="oha-filter-label"><?php _e('Floor', 'oha-modern-map'); ?></label>
                        <div class="oha-floor-pills">
                            <?php foreach ($floors as $f) : ?>
                                <button class="oha-pill oha-floor-btn <?php echo ((int) $f['id'] === $floor_id) ? 'active' : ''; ?>"
                                        data-floor="<?php echo esc_attr($f['id']); ?>">
                                    <?php echo esc_html($f['label']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Type Filter -->
                    <div class="oha-filter-group oha-conv-type-group">
                        <label class="oha-filter-label"><?php _e('Show', 'oha-modern-map'); ?></label>
                        <div class="oha-conv-type-filters">
                            <button class="oha-pill oha-conv-type-all active" data-type="all">
                                <?php _e('All', 'oha-modern-map'); ?>
                            </button>
                            <?php foreach ($types as $key => $label) : ?>
                                <button class="oha-pill oha-conv-type-btn" data-type="<?php echo esc_attr($key); ?>"
                                        style="--type-color:<?php echo esc_attr(OHA_Convention_Data::get_color_for_type($key)); ?>">
                                    <span class="oha-conv-type-dot"></span>
                                    <?php echo esc_html($label); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Search -->
                    <div class="oha-filter-group oha-conv-search-group">
                        <div class="oha-conv-search-wrap">
                            <svg class="oha-conv-search-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" width="16" height="16">
                                <circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.5"/>
                                <path d="M15 15l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <input type="search" class="oha-conv-search" placeholder="<?php esc_attr_e('Search booths...', 'oha-modern-map'); ?>">
                        </div>
                    </div>

                    <!-- Counter -->
                    <div class="oha-conv-counter">
                        <span class="oha-conv-count">0</span> <?php _e('booths', 'oha-modern-map'); ?>
                    </div>

                </div><!-- .oha-conv-controls-inner -->
            </div><!-- .oha-conv-controls -->
            <?php endif; ?>

            <div class="oha-conv-body">

                <!-- Map Canvas -->
                <div class="oha-conv-map-area">
                    <div class="oha-conv-map" id="<?php echo esc_attr($map_id); ?>-map"
                         style="height:<?php echo esc_attr($atts['height']); ?>;"
                         aria-label="<?php esc_attr_e('Convention floor map', 'oha-modern-map'); ?>">
                    </div>

                    <!-- Map controls overlay -->
                    <div class="oha-conv-map-overlay-controls">
                        <button class="oha-conv-zoom-btn" data-action="zoom-in" aria-label="<?php esc_attr_e('Zoom in', 'oha-modern-map'); ?>">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                        </button>
                        <button class="oha-conv-zoom-btn" data-action="zoom-out" aria-label="<?php esc_attr_e('Zoom out', 'oha-modern-map'); ?>">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                        </button>
                        <button class="oha-conv-zoom-btn" data-action="reset" aria-label="<?php esc_attr_e('Reset view', 'oha-modern-map'); ?>">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3.05 11a9 9 0 1 1 .5 4M3 15v-4h4"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Loading state -->
                    <div class="oha-conv-loading">
                        <span class="oha-conv-spinner"></span>
                        <span><?php _e('Loading floor plan...', 'oha-modern-map'); ?></span>
                    </div>
                </div><!-- .oha-conv-map-area -->

                <?php if ($atts['show_list'] === 'true') : ?>
                <!-- Booth List Panel -->
                <div class="oha-conv-list-panel">
                    <div class="oha-conv-list-header">
                        <span class="oha-conv-list-title"><?php _e('Booths', 'oha-modern-map'); ?></span>
                    </div>
                    <div class="oha-conv-list" role="list" aria-label="<?php esc_attr_e('Booth list', 'oha-modern-map'); ?>">
                        <div class="oha-conv-list-empty" style="display:none;">
                            <?php _e('No booths match your filter.', 'oha-modern-map'); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- .oha-conv-body -->

            <!-- Bottom Sheet (booth detail card) -->
            <div class="oha-bottom-sheet oha-conv-bottom-sheet" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Booth details', 'oha-modern-map'); ?>" aria-hidden="true">
                <div class="oha-bottom-sheet-overlay"></div>
                <div class="oha-bottom-sheet-panel">
                    <div class="oha-bottom-sheet-handle" aria-hidden="true"></div>
                    <div class="oha-bottom-sheet-content">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>

        </div><!-- .oha-convention-wrapper -->

        <script>
        (function() {
            var convData = <?php echo wp_json_encode([
                'mapId'       => $map_id . '-map',
                'wrapperId'   => $map_id,
                'ajaxUrl'     => admin_url('admin-ajax.php'),
                'nonce'       => wp_create_nonce('oha_map_nonce'),
                'pluginUrl'   => OHA_MODERN_MAP_URL,
                'floors'      => $floors_js,
                'initialFloor'=> $floor_id,
                'showList'    => $atts['show_list'] === 'true',
                'eventName'   => $settings['event_name'] ?? '',
                'venueName'   => $settings['venue_name'] ?? '',
            ]); ?>;
            window.ohaConventionConfig = window.ohaConventionConfig || [];
            window.ohaConventionConfig.push(convData);
        })();
        </script>
        <?php
        endif; // end mobile / standard branch

        return ob_get_clean();
    }

    // -----------------------------------------------------------------------
    // [oha_convention_directory]
    // -----------------------------------------------------------------------

    /**
     * Render a searchable text-based directory of all booths.
     *
     * Attributes:
     *   floor   — Filter to floor (0 = all, default: 0)
     *   type    — Filter to type ('' = all, default: '')
     *   columns — Grid columns (default: 3)
     *   class   — Extra CSS classes
     */
    public static function render_directory($atts) {
        $settings = OHA_Convention_Data::get_settings();
        if (empty($settings['enabled'])) return '';

        $atts = shortcode_atts([
            'floor'   => 0,
            'type'    => '',
            'columns' => 3,
            'class'   => '',
        ], $atts, 'oha_convention_directory');

        $booths  = OHA_Convention_Data::get_booths(intval($atts['floor']));
        $columns = max(1, min(6, intval($atts['columns'])));

        if (!empty($atts['type'])) {
            $booths = array_filter($booths, fn($b) => $b['type'] === $atts['type']);
            $booths = array_values($booths);
        }

        ob_start();
        ?>
        <div class="oha-conv-directory <?php echo esc_attr($atts['class']); ?>" style="--dir-cols:<?php echo $columns; ?>">
            <?php if (empty($booths)) : ?>
                <p class="oha-conv-dir-empty"><?php _e('No booths found.', 'oha-modern-map'); ?></p>
            <?php else : ?>
                <div class="oha-conv-dir-search-row">
                    <input type="search" class="oha-conv-dir-search" placeholder="<?php esc_attr_e('Search directory...', 'oha-modern-map'); ?>">
                </div>
                <div class="oha-conv-dir-grid">
                    <?php foreach ($booths as $booth) : ?>
                        <div class="oha-conv-dir-card"
                             data-type="<?php echo esc_attr($booth['type']); ?>"
                             data-number="<?php echo esc_attr($booth['number']); ?>"
                             data-title="<?php echo esc_attr(strtolower($booth['title'])); ?>"
                             style="--booth-color:<?php echo esc_attr($booth['type_color']); ?>">

                            <?php if (!empty($booth['thumbnail'])) : ?>
                                <img class="oha-conv-dir-thumb" src="<?php echo esc_url($booth['thumbnail']); ?>" alt="<?php echo esc_attr($booth['title']); ?>" loading="lazy">
                            <?php elseif (!empty($booth['vendor_logo'])) : ?>
                                <img class="oha-conv-dir-thumb" src="<?php echo esc_url($booth['vendor_logo']); ?>" alt="<?php echo esc_attr($booth['vendor_name'] ?: $booth['title']); ?>" loading="lazy">
                            <?php endif; ?>

                            <div class="oha-conv-dir-info">
                                <?php if (!empty($booth['number'])) : ?>
                                    <span class="oha-conv-dir-number"><?php echo esc_html($booth['number']); ?></span>
                                <?php endif; ?>
                                <h4 class="oha-conv-dir-name"><?php echo esc_html($booth['vendor_name'] ?: $booth['title']); ?></h4>
                                <span class="oha-conv-dir-type-badge"><?php echo esc_html($booth['type_label']); ?></span>
                                <?php if (!empty($booth['hours'])) : ?>
                                    <p class="oha-conv-dir-hours"><?php echo esc_html($booth['hours']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($booth['website'])) : ?>
                                    <a href="<?php echo esc_url($booth['website']); ?>" class="oha-conv-dir-link" target="_blank" rel="noopener">
                                        <?php _e('Visit Website', 'oha-modern-map'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var dirSearch = document.querySelector('.oha-conv-dir-search');
            if (!dirSearch) return;
            dirSearch.addEventListener('input', function() {
                var q = this.value.toLowerCase();
                document.querySelectorAll('.oha-conv-dir-card').forEach(function(card) {
                    var match = !q
                        || card.dataset.title.includes(q)
                        || (card.dataset.number || '').toLowerCase().includes(q);
                    card.style.display = match ? '' : 'none';
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}
