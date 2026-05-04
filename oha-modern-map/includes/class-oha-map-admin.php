<?php
/**
 * OHA Map Admin Settings
 * Manages plugin settings and admin interface
 */

if (!defined('ABSPATH')) exit;

class OHA_Map_Admin {
    
    /**
     * Register admin menu
     */
    public static function register_menu() {
        add_menu_page(
            __('OHA Modern Map', 'oha-modern-map'),
            __('OHA Map', 'oha-modern-map'),
            'manage_options',
            'oha-modern-map',
            [__CLASS__, 'render_settings_page'],
            'dashicons-location-alt',
            30
        );
        
        add_submenu_page(
            'oha-modern-map',
            __('Map Settings', 'oha-modern-map'),
            __('Settings', 'oha-modern-map'),
            'manage_options',
            'oha-modern-map',
            [__CLASS__, 'render_settings_page']
        );
        
        add_submenu_page(
            'oha-modern-map',
            __('Category Mapping', 'oha-modern-map'),
            __('Category Mapping', 'oha-modern-map'),
            'manage_options',
            'oha-map-categories',
            [__CLASS__, 'render_category_mapping_page']
        );
        
        add_submenu_page(
            'oha-modern-map',
            __('Custom Icons', 'oha-modern-map'),
            __('Custom Icons', 'oha-modern-map'),
            'manage_options',
            'oha-map-icons',
            [__CLASS__, 'render_custom_icons_page']
        );
        
        add_submenu_page(
            'oha-modern-map',
            __('Regions', 'oha-modern-map'),
            __('Regions', 'oha-modern-map'),
            'manage_options',
            'oha-map-regions',
            [__CLASS__, 'render_regions_page']
        );

        add_submenu_page(
            'oha-modern-map',
            __('Region Colors', 'oha-modern-map'),
            __('Region Colors', 'oha-modern-map'),
            'manage_options',
            'oha-map-region-colors',
            [__CLASS__, 'render_region_colors_page']
        );

        add_submenu_page(
            'oha-modern-map',
            __('Region Info', 'oha-modern-map'),
            __('Region Info', 'oha-modern-map'),
            'manage_options',
            'oha-map-region-info',
            [__CLASS__, 'render_region_info_page']
        );

        add_submenu_page(
            'oha-modern-map',
            __('Card Fields', 'oha-modern-map'),
            __('Card Fields', 'oha-modern-map'),
            'manage_options',
            'oha-map-card-fields',
            [__CLASS__, 'render_card_fields_page']
        );

        add_submenu_page(
            'oha-modern-map',
            __('Convention Settings', 'oha-modern-map'),
            __('Convention Mode', 'oha-modern-map'),
            'manage_options',
            'oha-convention-settings',
            [__CLASS__, 'render_convention_settings_page']
        );

        add_submenu_page(
            'oha-modern-map',
            __('Booth Placement', 'oha-modern-map'),
            __('Booth Placement', 'oha-modern-map'),
            'manage_options',
            'oha-booth-placement',
            [__CLASS__, 'render_placement_page']
        );

        add_submenu_page(
            'oha-modern-map',
            __('Diagnostics', 'oha-modern-map'),
            __('Diagnostics', 'oha-modern-map'),
            'manage_options',
            'oha-map-diagnostics',
            [__CLASS__, 'render_diagnostics_page']
        );
    }
    
    /**
     * Render main settings page
     */
    public static function render_settings_page() {
        if (isset($_POST['oha_map_settings_nonce']) && wp_verify_nonce($_POST['oha_map_settings_nonce'], 'oha_map_settings')) {
            self::save_settings();
            echo '<div class="notice notice-success"><p>' . __('Settings saved!', 'oha-modern-map') . '</p></div>';
        }
        
        $theme = self::get_theme_settings();
        $icons = self::get_icon_settings();
        $clustering = self::get_clustering_settings();
        $fields = self::get_field_settings();
        
        ?>
        <div class="wrap">
            <h1><?php _e('OHA Modern Map Settings', 'oha-modern-map'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('oha_map_settings', 'oha_map_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th colspan="2">
                            <h2><?php _e('Map Provider', 'oha-modern-map'); ?></h2>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="map_provider"><?php _e('Map Service', 'oha-modern-map'); ?></label>
                        </th>
                        <td>
                            <select name="theme[map_provider]" id="map_provider">
                                <option value="leaflet" <?php selected($theme['map_provider'] ?? 'leaflet', 'leaflet'); ?>><?php _e('Leaflet (Free)', 'oha-modern-map'); ?></option>
                                <option value="google" <?php selected($theme['map_provider'] ?? 'leaflet', 'google'); ?>><?php _e('Google Maps (Requires API Key)', 'oha-modern-map'); ?></option>
                            </select>
                            <p class="description">
                                <?php _e('Leaflet is free and works without API key. Google Maps requires API key but may perform better.', 'oha-modern-map'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr id="google_api_key_row" style="display: none;">
                        <th scope="row">
                            <label for="google_api_key"><?php _e('Google Maps API Key', 'oha-modern-map'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="theme[google_api_key]" id="google_api_key"
                                   value="<?php echo esc_attr($theme['google_api_key'] ?? ''); ?>"
                                   class="regular-text" placeholder="AIzaSyC...">
                            <p class="description">
                                <?php _e('(Legacy field — v2.0 uses MapLibre + MapTiler below)', 'oha-modern-map'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- MapLibre / MapTiler Settings (v2.0) -->
                    <tr>
                        <th colspan="2">
                            <h2><?php _e('MapTiler Tile Provider', 'oha-modern-map'); ?></h2>
                            <p class="description">
                                <?php _e('OHA Modern Map v2.0 uses MapLibre GL JS (WebGL) with MapTiler vector tiles.', 'oha-modern-map'); ?>
                                <a href="https://cloud.maptiler.com/account/keys" target="_blank"><?php _e('Get a free MapTiler API key', 'oha-modern-map'); ?></a>
                                <?php _e('(free tier: 100,000 tile loads/month).', 'oha-modern-map'); ?>
                            </p>
                        </th>
                    </tr>

                    <?php $maptiler = self::get_maptiler_settings(); ?>
                    <tr>
                        <th scope="row">
                            <label for="maptiler_api_key"><?php _e('MapTiler API Key', 'oha-modern-map'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="maptiler[api_key]" id="maptiler_api_key"
                                   value="<?php echo esc_attr($maptiler['api_key']); ?>"
                                   class="regular-text" placeholder="aBcDeFgH1234...">
                            <p class="description">
                                <?php _e('Without a key the map falls back to OpenFreeMap tiles (no account needed, basic style only).', 'oha-modern-map'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="maptiler_style"><?php _e('Map Style', 'oha-modern-map'); ?></label>
                        </th>
                        <td>
                            <select name="maptiler[style]" id="maptiler_style">
                                <option value="basic-v2-dark" <?php selected($maptiler['style'], 'basic-v2-dark'); ?>><?php _e('Basic Dark (Recommended)', 'oha-modern-map'); ?></option>
                                <option value="dataviz-dark"  <?php selected($maptiler['style'], 'dataviz-dark'); ?>><?php _e('DataViz Dark', 'oha-modern-map'); ?></option>
                                <option value="backdrop"      <?php selected($maptiler['style'], 'backdrop'); ?>><?php _e('Backdrop (Subtle)', 'oha-modern-map'); ?></option>
                                <option value="custom"        <?php selected($maptiler['style'], 'custom'); ?>><?php _e('Custom Style URL', 'oha-modern-map'); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr id="maptiler_custom_url_row" style="<?php echo $maptiler['style'] === 'custom' ? '' : 'display:none'; ?>">
                        <th scope="row">
                            <label for="maptiler_custom_url"><?php _e('Custom Style URL', 'oha-modern-map'); ?></label>
                        </th>
                        <td>
                            <input type="url" name="maptiler[custom_url]" id="maptiler_custom_url"
                                   value="<?php echo esc_attr($maptiler['custom_url']); ?>"
                                   class="large-text" placeholder="https://...">
                            <p class="description"><?php _e('Full MapLibre-compatible style.json URL.', 'oha-modern-map'); ?></p>
                        </td>
                    </tr>

                    <!-- Events Layer Settings -->
                    <tr>
                        <th colspan="2"><h2><?php _e('Events Layer', 'oha-modern-map'); ?></h2></th>
                    </tr>
                    <?php $ev = self::get_events_settings(); ?>
                    <tr>
                        <th scope="row"><?php _e('Show Events Layer', 'oha-modern-map'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="events[enabled]" value="1" <?php checked($ev['enabled']); ?>>
                                <?php _e('Display events (from Events for GeoDirectory) as a separate layer on the map', 'oha-modern-map'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Events Visible by Default', 'oha-modern-map'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="events[show_by_default]" value="1" <?php checked($ev['show_by_default']); ?>>
                                <?php _e('Events layer is on when the map first loads', 'oha-modern-map'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="events_layer_label"><?php _e('Events Button Label', 'oha-modern-map'); ?></label></th>
                        <td>
                            <input type="text" name="events[layer_label]" id="events_layer_label"
                                   value="<?php echo esc_attr($ev['layer_label']); ?>"
                                   class="regular-text" placeholder="Events">
                        </td>
                    </tr>

                    <script>
                    jQuery(document).ready(function($) {
                        function toggleGoogleApiKey() {
                            $('#google_api_key_row').toggle($('#map_provider').val() === 'google');
                        }
                        function toggleCustomUrl() {
                            $('#maptiler_custom_url_row').toggle($('#maptiler_style').val() === 'custom');
                        }
                        $('#map_provider').on('change', toggleGoogleApiKey);
                        $('#maptiler_style').on('change', toggleCustomUrl);
                        toggleGoogleApiKey();
                        toggleCustomUrl();
                    });
                    </script>
                    
                    <tr>
                        <th colspan="2">
                            <h2><?php _e('Map Theme', 'oha-modern-map'); ?></h2>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="map_style"><?php _e('Map Style', 'oha-modern-map'); ?></label>
                        </th>
                        <td>
                            <select name="theme[style]" id="map_style">
                                <option value="dark" <?php selected($theme['style'], 'dark'); ?>><?php _e('Dark (Recommended)', 'oha-modern-map'); ?></option>
                                <option value="light" <?php selected($theme['style'], 'light'); ?>><?php _e('Light', 'oha-modern-map'); ?></option>
                                <option value="haunted" <?php selected($theme['style'], 'haunted'); ?>><?php _e('Haunted (Custom)', 'oha-modern-map'); ?></option>
                            </select>
                            <p class="description"><?php _e('Dark mode works on both Leaflet and Google Maps', 'oha-modern-map'); ?></p>
                        </td>
                    </tr>
                    
                    <script>
                    jQuery(document).ready(function($) {
                        function toggleGoogleApiKey() {
                            if ($('#map_provider').val() === 'google') {
                                $('#google_api_key_row').show();
                            } else {
                                $('#google_api_key_row').hide();
                            }
                        }
                        
                        $('#map_provider').on('change', toggleGoogleApiKey);
                        toggleGoogleApiKey();
                    });
                    </script>
                    
                    <tr>
                        <th colspan="2">
                            <h2><?php _e('Colors', 'oha-modern-map'); ?></h2>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="accent_color"><?php _e('Accent Color', 'oha-modern-map'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="theme[accent_color]" id="accent_color" value="<?php echo esc_attr($theme['accent_color']); ?>" class="oha-color-picker">
                            <p class="description"><?php _e('Primary color for UI elements', 'oha-modern-map'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="featured_color"><?php _e('Featured Haunts Color', 'oha-modern-map'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="theme[featured_color]" id="featured_color" value="<?php echo esc_attr($theme['featured_color']); ?>" class="oha-color-picker">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="home_haunt_color"><?php _e('Home Haunts Color', 'oha-modern-map'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="theme[home_haunt_color]" id="home_haunt_color" value="<?php echo esc_attr($theme['home_haunt_color']); ?>" class="oha-color-picker">
                        </td>
                    </tr>
                    
                    <tr>
                        <th colspan="2">
                            <h2><?php _e('Icon Hierarchy', 'oha-modern-map'); ?></h2>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="featured_size"><?php _e('Featured Icon Size', 'oha-modern-map'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="icons[featured_size]" id="featured_size" value="<?php echo esc_attr($icons['featured_size']); ?>" min="20" max="50" step="2">
                            <span>px</span>
                            <p class="description"><?php _e('Recommended: 32px', 'oha-modern-map'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="standard_size"><?php _e('Professional Icon Size', 'oha-modern-map'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="icons[standard_size]" id="standard_size" value="<?php echo esc_attr($icons['standard_size']); ?>" min="16" max="40" step="2">
                            <span>px</span>
                            <p class="description"><?php _e('Recommended: 24px', 'oha-modern-map'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="home_haunt_size"><?php _e('Home Haunt Icon Size', 'oha-modern-map'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="icons[home_haunt_size]" id="home_haunt_size" value="<?php echo esc_attr($icons['home_haunt_size']); ?>" min="14" max="32" step="2">
                            <span>px</span>
                            <p class="description"><?php _e('Recommended: 20px', 'oha-modern-map'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="featured_glow"><?php _e('Featured Glow Effect', 'oha-modern-map'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="icons[featured_glow]" id="featured_glow" value="1" <?php checked($icons['featured_glow'], true); ?>>
                                <?php _e('Add subtle glow to featured haunts', 'oha-modern-map'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th colspan="2">
                            <h2><?php _e('Clustering Settings', 'oha-modern-map'); ?></h2>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="clustering_enabled"><?php _e('Enable Clustering', 'oha-modern-map'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="clustering[enabled]" id="clustering_enabled" value="1" <?php checked($clustering['enabled'], true); ?>>
                                <?php _e('Group nearby markers (strongly recommended)', 'oha-modern-map'); ?>
                            </label>
                            <p class="description"><?php _e('Essential for mobile performance and readability', 'oha-modern-map'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="cluster_radius"><?php _e('Cluster Radius', 'oha-modern-map'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="clustering[radius]" id="cluster_radius" value="<?php echo esc_attr($clustering['radius']); ?>" min="30" max="100" step="5">
                            <span>px</span>
                            <p class="description"><?php _e('How close markers need to be to cluster (50 is ideal)', 'oha-modern-map'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="disable_at_zoom"><?php _e('Disable Clustering at Zoom Level', 'oha-modern-map'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="clustering[disable_at_zoom]" id="disable_at_zoom" value="<?php echo esc_attr($clustering['disable_at_zoom']); ?>" min="12" max="18" step="1">
                            <p class="description"><?php _e('Stop clustering when zoomed in this far (16 = individual haunts visible)', 'oha-modern-map'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th colspan="2">
                            <h2><?php _e('GeoDirectory Field Mapping', 'oha-modern-map'); ?></h2>
                            <p class="description"><?php _e('Configure which column names GeoDirectory uses to store coordinates. Default values work for standard installs. Go to OHA Map &rsaquo; Diagnostics to see your actual column names.', 'oha-modern-map'); ?></p>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fields_post_type"><?php _e('GD Post Type', 'oha-modern-map'); ?></label></th>
                        <td>
                            <input type="text" name="fields[post_type]" id="fields_post_type" value="<?php echo esc_attr($fields['post_type']); ?>" class="regular-text" placeholder="gd_place">
                            <p class="description"><?php _e('GeoDirectory custom post type for listings (usually gd_place).', 'oha-modern-map'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fields_lat_field"><?php _e('Latitude Column', 'oha-modern-map'); ?></label></th>
                        <td>
                            <input type="text" name="fields[lat_field]" id="fields_lat_field" value="<?php echo esc_attr($fields['lat_field']); ?>" class="regular-text" placeholder="latitude">
                            <p class="description"><?php _e('Column name in the GeoDirectory detail table. Check Diagnostics if unsure.', 'oha-modern-map'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fields_lng_field"><?php _e('Longitude Column', 'oha-modern-map'); ?></label></th>
                        <td>
                            <input type="text" name="fields[lng_field]" id="fields_lng_field" value="<?php echo esc_attr($fields['lng_field']); ?>" class="regular-text" placeholder="longitude">
                            <p class="description"><?php _e('Column name in the GeoDirectory detail table. Check Diagnostics if unsure.', 'oha-modern-map'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fields_phone_field"><?php _e('Phone Column', 'oha-modern-map'); ?></label></th>
                        <td><input type="text" name="fields[phone_field]" id="fields_phone_field" value="<?php echo esc_attr($fields['phone_field']); ?>" class="regular-text" placeholder="phone"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fields_email_field"><?php _e('Email Column', 'oha-modern-map'); ?></label></th>
                        <td><input type="text" name="fields[email_field]" id="fields_email_field" value="<?php echo esc_attr($fields['email_field']); ?>" class="regular-text" placeholder="email"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fields_website_field"><?php _e('Website Column', 'oha-modern-map'); ?></label></th>
                        <td><input type="text" name="fields[website_field]" id="fields_website_field" value="<?php echo esc_attr($fields['website_field']); ?>" class="regular-text" placeholder="website"></td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Settings', 'oha-modern-map')); ?>
            </form>
            
            <hr>
            
            <h2><?php _e('Shortcode Usage', 'oha-modern-map'); ?></h2>
            <div class="oha-shortcode-examples">
                <h3><?php _e('Main Map', 'oha-modern-map'); ?></h3>
                <code>[oha_modern_map]</code>
                <p><?php _e('Basic map with all default settings', 'oha-modern-map'); ?></p>
                
                <code>[oha_modern_map region="central" height="500px"]</code>
                <p><?php _e('Map focused on Central Ohio, 500px tall', 'oha-modern-map'); ?></p>
                
                <code>[oha_modern_map type="featured" show_filters="false"]</code>
                <p><?php _e('Only featured haunts, no filters shown', 'oha-modern-map'); ?></p>
                
                <h3><?php _e('Region Selector', 'oha-modern-map'); ?></h3>
                <code>[oha_region_selector]</code>
                <p><?php _e('Grid of clickable regions (perfect for homepage)', 'oha-modern-map'); ?></p>
                
                <code>[oha_region_selector show_counts="true"]</code>
                <p><?php _e('Show haunt counts per region', 'oha-modern-map'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render category mapping page
     */
    public static function render_category_mapping_page() {
        if (isset($_POST['oha_category_mapping_nonce']) && wp_verify_nonce($_POST['oha_category_mapping_nonce'], 'oha_category_mapping')) {
            self::save_category_mapping();
            echo '<div class="notice notice-success"><p>' . __('Category mappings saved!', 'oha-modern-map') . '</p></div>';
        }
        
        $mappings = self::get_category_mappings();
        $categories = self::get_geodirectory_categories();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Category to Listing Type Mapping', 'oha-modern-map'); ?></h1>
            
            <p class="description">
                <?php _e('Map your existing GeoDirectory category slugs to listing types. This lets you keep your current categories without changing them.', 'oha-modern-map'); ?>
            </p>
            
            <form method="post" action="">
                <?php wp_nonce_field('oha_category_mapping', 'oha_category_mapping_nonce'); ?>
                
                <h2><?php _e('Default Listing Types', 'oha-modern-map'); ?></h2>
                
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Listing Type', 'oha-modern-map'); ?></th>
                            <th><?php _e('Your Category Slug(s)', 'oha-modern-map'); ?></th>
                            <th><?php _e('Current Categories Available', 'oha-modern-map'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <strong><?php _e('Featured', 'oha-modern-map'); ?></strong>
                                <p class="description"><?php _e('Uses GeoDirectory featured checkbox - no category needed', 'oha-modern-map'); ?></p>
                            </td>
                            <td colspan="2">
                                <em><?php _e('Automatic - based on featured checkbox in GeoDirectory', 'oha-modern-map'); ?></em>
                            </td>
                        </tr>
                        
                        <tr>
                            <td>
                                <strong><?php _e('Professional Haunts', 'oha-modern-map'); ?></strong>
                                <p class="description"><?php _e('Professional attractions, OHA members', 'oha-modern-map'); ?></p>
                            </td>
                            <td>
                                <input type="text" name="mappings[professional]" 
                                       value="<?php echo esc_attr($mappings['professional'] ?? 'professional-haunt'); ?>" 
                                       class="regular-text"
                                       placeholder="haunted-house, professional-haunt">
                                <p class="description"><?php _e('Enter category slug(s), comma-separated', 'oha-modern-map'); ?></p>
                            </td>
                            <td>
                                <code><?php echo implode('</code>, <code>', array_keys($categories)); ?></code>
                            </td>
                        </tr>
                        
                        <tr>
                            <td>
                                <strong><?php _e('Home Haunts', 'oha-modern-map'); ?></strong>
                                <p class="description"><?php _e('Home haunts, yard displays', 'oha-modern-map'); ?></p>
                            </td>
                            <td>
                                <input type="text" name="mappings[home_haunt]" 
                                       value="<?php echo esc_attr($mappings['home_haunt'] ?? 'home-haunt'); ?>" 
                                       class="regular-text"
                                       placeholder="homehaunt, home-haunt, yard-display">
                                <p class="description"><?php _e('Enter category slug(s), comma-separated', 'oha-modern-map'); ?></p>
                            </td>
                            <td>
                                <code><?php echo implode('</code>, <code>', array_keys($categories)); ?></code>
                            </td>
                        </tr>
                        
                        <tr>
                            <td>
                                <strong><?php _e('Free Listings', 'oha-modern-map'); ?></strong>
                                <p class="description"><?php _e('Default for uncategorized listings', 'oha-modern-map'); ?></p>
                            </td>
                            <td colspan="2">
                                <em><?php _e('Automatic - any listing not matching above categories', 'oha-modern-map'); ?></em>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <h2 style="margin-top: 30px;"><?php _e('Add Custom Listing Types', 'oha-modern-map'); ?></h2>
                <p class="description">
                    <?php _e('Create additional listing types beyond the defaults. These will get their own filter toggles and icon settings.', 'oha-modern-map'); ?>
                </p>
                
                <table class="widefat" id="oha-custom-types">
                    <thead>
                        <tr>
                            <th><?php _e('Type Name', 'oha-modern-map'); ?></th>
                            <th><?php _e('Type Slug', 'oha-modern-map'); ?></th>
                            <th><?php _e('Category Slug(s)', 'oha-modern-map'); ?></th>
                            <th><?php _e('Action', 'oha-modern-map'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $custom_types = isset($mappings['custom_types']) ? $mappings['custom_types'] : [];
                        if (!empty($custom_types)) :
                            foreach ($custom_types as $index => $type) :
                        ?>
                            <tr>
                                <td>
                                    <input type="text" name="mappings[custom_types][<?php echo $index; ?>][name]" 
                                           value="<?php echo esc_attr($type['name']); ?>" 
                                           placeholder="Escape Rooms">
                                </td>
                                <td>
                                    <input type="text" name="mappings[custom_types][<?php echo $index; ?>][slug]" 
                                           value="<?php echo esc_attr($type['slug']); ?>" 
                                           placeholder="escape-room">
                                </td>
                                <td>
                                    <input type="text" name="mappings[custom_types][<?php echo $index; ?>][categories]" 
                                           value="<?php echo esc_attr($type['categories']); ?>" 
                                           placeholder="escape-room, puzzle-room">
                                </td>
                                <td>
                                    <button type="button" class="button oha-remove-type"><?php _e('Remove', 'oha-modern-map'); ?></button>
                                </td>
                            </tr>
                        <?php 
                            endforeach;
                        endif;
                        ?>
                        <tr id="oha-type-template" style="display: none;">
                            <td><input type="text" name="mappings[custom_types][__INDEX__][name]" placeholder="Escape Rooms"></td>
                            <td><input type="text" name="mappings[custom_types][__INDEX__][slug]" placeholder="escape-room"></td>
                            <td><input type="text" name="mappings[custom_types][__INDEX__][categories]" placeholder="escape-room, puzzle-room"></td>
                            <td><button type="button" class="button oha-remove-type"><?php _e('Remove', 'oha-modern-map'); ?></button></td>
                        </tr>
                    </tbody>
                </table>
                
                <p>
                    <button type="button" id="oha-add-type" class="button button-secondary">
                        <?php _e('+ Add Custom Type', 'oha-modern-map'); ?>
                    </button>
                </p>
                
                <script>
                jQuery(document).ready(function($) {
                    let typeIndex = <?php echo count($custom_types); ?>;
                    
                    $('#oha-add-type').on('click', function() {
                        const $template = $('#oha-type-template').clone();
                        $template.attr('id', '');
                        $template.find('input').each(function() {
                            $(this).attr('name', $(this).attr('name').replace('__INDEX__', typeIndex));
                        });
                        $template.show();
                        $('#oha-custom-types tbody').append($template);
                        typeIndex++;
                    });
                    
                    $(document).on('click', '.oha-remove-type', function() {
                        $(this).closest('tr').remove();
                    });
                });
                </script>
                
                <?php submit_button(__('Save Category Mappings', 'oha-modern-map')); ?>
            </form>
            
            <hr>
            
            <h2><?php _e('Example: Your Current Setup', 'oha-modern-map'); ?></h2>
            <p><?php _e('Based on your categories, here\'s what you should enter:', 'oha-modern-map'); ?></p>
            
            <table class="widefat">
                <tr>
                    <td><strong><?php _e('Professional Haunts field:', 'oha-modern-map'); ?></strong></td>
                    <td><code>haunted-house, featured</code></td>
                </tr>
                <tr>
                    <td><strong><?php _e('Home Haunts field:', 'oha-modern-map'); ?></strong></td>
                    <td><code>homehaunt</code></td>
                </tr>
            </table>
            
            <p class="description">
                <?php _e('This maps your existing slugs (haunted-house, homehaunt, featured) to the plugin\'s listing types without changing anything in GeoDirectory.', 'oha-modern-map'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Render custom icons page
     */
    public static function render_custom_icons_page() {
        if (isset($_POST['oha_custom_icons_nonce']) && wp_verify_nonce($_POST['oha_custom_icons_nonce'], 'oha_custom_icons')) {
            self::save_custom_icons();
            echo '<div class="notice notice-success"><p>' . __('Custom icons saved!', 'oha-modern-map') . '</p></div>';
        }
        
        $custom_icons = self::get_custom_icons();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Custom Map Icons', 'oha-modern-map'); ?></h1>
            
            <p class="description">
                <?php _e('Upload custom icon images for each listing type. Leave empty to use default colored circles. Recommended size: 32x32px PNG with transparency.', 'oha-modern-map'); ?>
            </p>
            
            <form method="post" action="">
                <?php wp_nonce_field('oha_custom_icons', 'oha_custom_icons_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php _e('Featured Icon', 'oha-modern-map'); ?></label>
                        </th>
                        <td>
                            <?php self::render_image_upload_field('featured', $custom_icons); ?>
                            <p class="description"><?php _e('Icon for featured haunts (default: gold circle with glow)', 'oha-modern-map'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php _e('Professional Icon', 'oha-modern-map'); ?></label>
                        </th>
                        <td>
                            <?php self::render_image_upload_field('professional', $custom_icons); ?>
                            <p class="description"><?php _e('Icon for professional haunts (default: orange circle)', 'oha-modern-map'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php _e('Home Haunt Icon', 'oha-modern-map'); ?></label>
                        </th>
                        <td>
                            <?php self::render_image_upload_field('home_haunt', $custom_icons); ?>
                            <p class="description"><?php _e('Icon for home haunts (default: purple circle)', 'oha-modern-map'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php _e('Free Listing Icon', 'oha-modern-map'); ?></label>
                        </th>
                        <td>
                            <?php self::render_image_upload_field('free', $custom_icons); ?>
                            <p class="description"><?php _e('Icon for free listings (default: gray circle)', 'oha-modern-map'); ?></p>
                        </td>
                    </tr>
                    
                    <?php 
                    // Show custom type icons if they exist
                    $mappings = self::get_category_mappings();
                    if (!empty($mappings['custom_types'])) :
                        foreach ($mappings['custom_types'] as $type) :
                            if (!empty($type['slug'])) :
                    ?>
                    <tr>
                        <th scope="row">
                            <label><?php echo esc_html($type['name']); ?> <?php _e('Icon', 'oha-modern-map'); ?></label>
                        </th>
                        <td>
                            <?php self::render_image_upload_field($type['slug'], $custom_icons); ?>
                            <p class="description"><?php printf(__('Icon for %s listings', 'oha-modern-map'), esc_html($type['name'])); ?></p>
                        </td>
                    </tr>
                    <?php 
                            endif;
                        endforeach;
                    endif;
                    ?>
                </table>
                
                <?php submit_button(__('Save Custom Icons', 'oha-modern-map')); ?>
            </form>
            
            <hr>
            
            <h2><?php _e('Icon Design Tips', 'oha-modern-map'); ?></h2>
            <ul>
                <li><?php _e('Recommended size: 32x32px (will be scaled automatically)', 'oha-modern-map'); ?></li>
                <li><?php _e('Format: PNG with transparency', 'oha-modern-map'); ?></li>
                <li><?php _e('Simple designs work best on small screens', 'oha-modern-map'); ?></li>
                <li><?php _e('High contrast colors for visibility', 'oha-modern-map'); ?></li>
                <li><?php _e('Avoid fine details (hard to see when small)', 'oha-modern-map'); ?></li>
            </ul>
            
            <h2><?php _e('Free Icon Resources', 'oha-modern-map'); ?></h2>
            <ul>
                <li><a href="https://www.flaticon.com" target="_blank">Flaticon</a> - Free icons (check license)</li>
                <li><a href="https://thenounproject.com" target="_blank">The Noun Project</a> - Simple icon designs</li>
                <li><a href="https://fontawesome.com" target="_blank">Font Awesome</a> - Download PNG versions</li>
            </ul>
        </div>
        <?php
    }
    
    /**
     * Render image upload field
     */
    private static function render_image_upload_field($type, $custom_icons) {
        $image_url = isset($custom_icons[$type]) ? $custom_icons[$type] : '';
        $image_id = isset($custom_icons[$type . '_id']) ? $custom_icons[$type . '_id'] : '';
        
        ?>
        <div class="oha-image-upload">
            <input type="hidden" name="custom_icons[<?php echo esc_attr($type); ?>]" value="<?php echo esc_url($image_url); ?>" class="oha-image-url">
            <input type="hidden" name="custom_icons[<?php echo esc_attr($type); ?>_id]" value="<?php echo esc_attr($image_id); ?>" class="oha-image-id">
            
            <div class="oha-image-preview" style="margin-bottom: 10px;">
                <?php if ($image_url) : ?>
                    <img src="<?php echo esc_url($image_url); ?>" style="max-width: 100px; max-height: 100px; border: 1px solid #ddd; padding: 5px; background: #f5f5f5;">
                <?php else : ?>
                    <div style="width: 100px; height: 100px; border: 1px dashed #ddd; display: flex; align-items: center; justify-content: center; color: #999;">
                        <?php _e('No icon', 'oha-modern-map'); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <button type="button" class="button oha-upload-image" data-type="<?php echo esc_attr($type); ?>">
                <?php $image_url ? _e('Change Icon', 'oha-modern-map') : _e('Upload Icon', 'oha-modern-map'); ?>
            </button>
            
            <?php if ($image_url) : ?>
                <button type="button" class="button oha-remove-image" data-type="<?php echo esc_attr($type); ?>">
                    <?php _e('Remove Icon', 'oha-modern-map'); ?>
                </button>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.oha-upload-image').on('click', function(e) {
                e.preventDefault();
                
                const button = $(this);
                const container = button.closest('.oha-image-upload');
                const type = button.data('type');
                
                const frame = wp.media({
                    title: '<?php _e('Select Icon Image', 'oha-modern-map'); ?>',
                    button: {
                        text: '<?php _e('Use this icon', 'oha-modern-map'); ?>'
                    },
                    multiple: false
                });
                
                frame.on('select', function() {
                    const attachment = frame.state().get('selection').first().toJSON();
                    
                    container.find('.oha-image-url').val(attachment.url);
                    container.find('.oha-image-id').val(attachment.id);
                    container.find('.oha-image-preview').html('<img src="' + attachment.url + '" style="max-width: 100px; max-height: 100px; border: 1px solid #ddd; padding: 5px; background: #f5f5f5;">');
                    
                    button.text('<?php _e('Change Icon', 'oha-modern-map'); ?>');
                    
                    if (container.find('.oha-remove-image').length === 0) {
                        button.after('<button type="button" class="button oha-remove-image" data-type="' + type + '"><?php _e('Remove Icon', 'oha-modern-map'); ?></button>');
                    }
                });
                
                frame.open();
            });
            
            $(document).on('click', '.oha-remove-image', function(e) {
                e.preventDefault();
                
                const button = $(this);
                const container = button.closest('.oha-image-upload');
                
                container.find('.oha-image-url').val('');
                container.find('.oha-image-id').val('');
                container.find('.oha-image-preview').html('<div style="width: 100px; height: 100px; border: 1px dashed #ddd; display: flex; align-items: center; justify-content: center; color: #999;"><?php _e('No icon', 'oha-modern-map'); ?></div>');
                container.find('.oha-upload-image').text('<?php _e('Upload Icon', 'oha-modern-map'); ?>');
                
                button.remove();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get GeoDirectory categories
     */
    private static function get_geodirectory_categories() {
        $categories = [];
        
        if (!class_exists('GeoDirectory')) {
            return $categories;
        }
        
        $terms = get_terms([
            'taxonomy' => 'gd_placecategory',
            'hide_empty' => false
        ]);
        
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $categories[$term->slug] = $term->name;
            }
        }
        
        return $categories;
    }
    
    /**
     * Render regions management page
     */
    public static function render_regions_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Manage Regions', 'oha-modern-map'); ?></h1>
            
            <p><?php _e('Regions are defined by latitude/longitude boundaries. Listings are automatically assigned to regions based on their coordinates.', 'oha-modern-map'); ?></p>
            
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Region', 'oha-modern-map'); ?></th>
                        <th><?php _e('Boundaries', 'oha-modern-map'); ?></th>
                        <th><?php _e('Haunts', 'oha-modern-map'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (OHA_Map_Regions::get_regions() as $slug => $region) : 
                        $counts = OHA_Map_Data::get_counts($slug);
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($region['name']); ?></strong></td>
                            <td>
                                <small>
                                    N: <?php echo $region['bounds']['north']; ?>, 
                                    S: <?php echo $region['bounds']['south']; ?>, 
                                    E: <?php echo $region['bounds']['east']; ?>, 
                                    W: <?php echo $region['bounds']['west']; ?>
                                </small>
                            </td>
                            <td><?php echo $counts['total']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <p class="description">
                <?php _e('To customize regions, use the filter:', 'oha-modern-map'); ?>
                <code>add_filter('oha_map_regions', 'your_custom_regions');</code>
            </p>
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    private static function save_settings() {
        if (isset($_POST['theme'])) {
            $theme = array_map('sanitize_text_field', $_POST['theme']);
            update_option('oha_map_theme', $theme);
        }
        
        if (isset($_POST['icons'])) {
            $icons = [
                'featured_size' => intval($_POST['icons']['featured_size']),
                'standard_size' => intval($_POST['icons']['standard_size']),
                'home_haunt_size' => intval($_POST['icons']['home_haunt_size']),
                'featured_glow' => isset($_POST['icons']['featured_glow'])
            ];
            update_option('oha_map_icons', $icons);
        }
        
        if (isset($_POST['clustering'])) {
            $clustering = [
                'enabled'         => isset($_POST['clustering']['enabled']),
                'radius'          => intval($_POST['clustering']['radius']),
                'disable_at_zoom' => intval($_POST['clustering']['disable_at_zoom']),
            ];
            update_option('oha_map_clustering', $clustering);
        }

        if (isset($_POST['fields'])) {
            $raw = $_POST['fields'];
            update_option('oha_map_fields', [
                'post_type'     => sanitize_key($raw['post_type']     ?? 'gd_place'),
                'lat_field'     => sanitize_key($raw['lat_field']     ?? 'latitude'),
                'lng_field'     => sanitize_key($raw['lng_field']     ?? 'longitude'),
                'phone_field'   => sanitize_key($raw['phone_field']   ?? 'phone'),
                'email_field'   => sanitize_key($raw['email_field']   ?? 'email'),
                'website_field' => sanitize_key($raw['website_field'] ?? 'website'),
            ]);
        }

        if (isset($_POST['maptiler'])) {
            $mt = $_POST['maptiler'];
            update_option('oha_map_maptiler', [
                'api_key'    => sanitize_text_field($mt['api_key']    ?? ''),
                'style'      => sanitize_key($mt['style']             ?? 'basic-v2-dark'),
                'custom_url' => esc_url_raw($mt['custom_url']         ?? ''),
            ]);
        }

        if (isset($_POST['events'])) {
            update_option('oha_map_events', [
                'enabled'         => !empty($_POST['events']['enabled']),
                'show_by_default' => !empty($_POST['events']['show_by_default']),
                'layer_label'     => sanitize_text_field($_POST['events']['layer_label'] ?? 'Events'),
            ]);
        }
    }
    
    /**
     * Save category mapping
     */
    private static function save_category_mapping() {
        $mappings = [];
        
        if (isset($_POST['mappings'])) {
            // Save default type mappings
            if (isset($_POST['mappings']['professional'])) {
                $mappings['professional'] = sanitize_text_field($_POST['mappings']['professional']);
            }
            
            if (isset($_POST['mappings']['home_haunt'])) {
                $mappings['home_haunt'] = sanitize_text_field($_POST['mappings']['home_haunt']);
            }
            
            // Save custom types
            if (isset($_POST['mappings']['custom_types']) && is_array($_POST['mappings']['custom_types'])) {
                $custom_types = [];
                foreach ($_POST['mappings']['custom_types'] as $type) {
                    if (!empty($type['name']) && !empty($type['slug']) && !empty($type['categories'])) {
                        $custom_types[] = [
                            'name' => sanitize_text_field($type['name']),
                            'slug' => sanitize_text_field($type['slug']),
                            'categories' => sanitize_text_field($type['categories'])
                        ];
                    }
                }
                $mappings['custom_types'] = $custom_types;
            }
        }
        
        update_option('oha_map_category_mappings', $mappings);
    }
    
    /**
     * Save custom icons
     */
    private static function save_custom_icons() {
        $icons = [];
        
        if (isset($_POST['custom_icons']) && is_array($_POST['custom_icons'])) {
            foreach ($_POST['custom_icons'] as $key => $value) {
                if (strpos($key, '_id') !== false) {
                    $icons[$key] = intval($value);
                } else {
                    $icons[$key] = esc_url_raw($value);
                }
            }
        }
        
        update_option('oha_map_custom_icons', $icons);
    }
    
    /**
     * Get theme settings
     */
    public static function get_theme_settings() {
        return wp_parse_args(get_option('oha_map_theme', []), [
            'map_provider' => 'leaflet',
            'google_api_key' => '',
            'style' => 'dark',
            'accent_color' => '#ff6b35',
            'featured_color' => '#ffd700',
            'home_haunt_color' => '#9d4edd',
            'background_color' => '#1a1a1a'
        ]);
    }
    
    /**
     * Get icon settings
     */
    public static function get_icon_settings() {
        $settings = wp_parse_args(get_option('oha_map_icons', []), [
            'featured_size' => 32,
            'standard_size' => 24,
            'home_haunt_size' => 20,
            'featured_glow' => true
        ]);
        
        // Add custom icons to settings
        $custom_icons = self::get_custom_icons();
        if (!empty($custom_icons)) {
            $settings['custom_icons'] = $custom_icons;
        }
        
        return $settings;
    }
    
    /**
     * Get clustering settings
     */
    public static function get_clustering_settings() {
        return wp_parse_args(get_option('oha_map_clustering', []), [
            'enabled' => true,
            'radius' => 50,
            'disable_at_zoom' => 16
        ]);
    }
    
    /**
     * Get category mappings
     */
    public static function get_category_mappings() {
        return wp_parse_args(get_option('oha_map_category_mappings', []), [
            'professional' => 'professional-haunt',
            'home_haunt' => 'home-haunt',
            'custom_types' => []
        ]);
    }
    
    /**
     * Get custom icons
     */
    public static function get_custom_icons() {
        return get_option('oha_map_custom_icons', []);
    }

    /**
     * Get GeoDirectory field name settings (configurable column names)
     */
    public static function get_field_settings() {
        return wp_parse_args(get_option('oha_map_fields', []), [
            'post_type'     => 'gd_place',
            'lat_field'     => 'latitude',
            'lng_field'     => 'longitude',
            'phone_field'   => 'phone',
            'email_field'   => 'email',
            'website_field' => 'website',
        ]);
    }

    // -----------------------------------------------------------------------
    // New v2.0 Getters
    // -----------------------------------------------------------------------

    /**
     * Get MapTiler settings.
     */
    public static function get_maptiler_settings() {
        return wp_parse_args(get_option('oha_map_maptiler', []), [
            'api_key'    => '',
            'style'      => 'basic-v2-dark',
            'custom_url' => '',
        ]);
    }

    /**
     * Return just the MapTiler API key (convenience wrapper).
     */
    public static function get_maptiler_key() {
        return self::get_maptiler_settings()['api_key'];
    }

    /**
     * Build the full MapTiler (or fallback) style URL.
     */
    public static function get_tile_style_url() {
        $mt  = self::get_maptiler_settings();
        $key = $mt['api_key'];

        if ($mt['style'] === 'custom' && !empty($mt['custom_url'])) {
            return $mt['custom_url'];
        }

        if (!empty($key)) {
            $style = $mt['style'] ?: 'basic-v2-dark';
            return "https://api.maptiler.com/maps/{$style}/style.json?key={$key}";
        }

        // Fallback: OpenFreeMap — free, no API key, basic dark style
        return 'https://tiles.openfreemap.org/styles/dark';
    }

    /**
     * Get card fields configuration.
     */
    public static function get_card_fields() {
        return wp_parse_args(get_option('oha_map_card_fields', []), [
            'thumbnail'       => true,
            'type_badge'      => true,
            'status_badge'    => true,
            'address'         => true,
            'phone'           => true,
            'website'         => true,
            'email'           => false,
            'view_details'    => true,
            'upcoming_events' => true,
            'description'     => false,
        ]);
    }

    /**
     * Get events layer settings.
     */
    public static function get_events_settings() {
        return wp_parse_args(get_option('oha_map_events', []), [
            'enabled'         => true,
            'show_by_default' => true,
            'layer_label'     => 'Events',
        ]);
    }

    /**
     * Get region info settings (description, population, highlights per region).
     */
    public static function get_region_info() {
        $saved    = get_option('oha_map_region_info', []);
        $defaults = self::get_default_region_info();
        $merged   = [];
        foreach ($defaults as $slug => $info) {
            $saved_region = isset($saved[$slug]) && is_array($saved[$slug]) ? $saved[$slug] : [];
            $merged[$slug] = wp_parse_args($saved_region, $info);
            // Highlights: saved as array or pipe-separated string
            if (!empty($saved_region['highlights']) && is_array($saved_region['highlights'])) {
                $merged[$slug]['highlights'] = array_values(array_filter(array_map('sanitize_text_field', $saved_region['highlights'])));
            }
        }
        return $merged;
    }

    private static function get_default_region_info() {
        return [
            'northwest' => [
                'description' => 'Home to Toledo and the Lake Erie shoreline, Northwest Ohio blends industrial heritage with outdoor adventures, great fishing, and a rich agricultural landscape.',
                'population'  => 'Approx. 1.1 million',
                'highlights'  => ['Toledo Museum of Art', 'Lake Erie Islands & Perry Monument', 'Maumee Bay State Park', 'Sauder Village Living History'],
            ],
            'northeast' => [
                'description' => 'Northeast Ohio is the cultural and economic hub of the state, featuring Cleveland, Akron, Youngstown, and the stunning Cuyahoga Valley National Park.',
                'population'  => 'Approx. 2.9 million',
                'highlights'  => ['Rock & Roll Hall of Fame (Cleveland)', 'Cuyahoga Valley National Park', 'Hale Farm & Village', 'Stan Hywet Hall & Gardens'],
            ],
            'central' => [
                'description' => 'Central Ohio surrounds Columbus, the state capital, offering a vibrant urban scene, world-class zoos, and easy access to the scenic Hocking Hills.',
                'population'  => 'Approx. 2.4 million',
                'highlights'  => ['Columbus Zoo & Aquarium', 'Hocking Hills State Park (nearby)', 'Short North Arts District', 'Ohio State University Campus'],
            ],
            'southwest' => [
                'description' => 'Southwest Ohio encompasses Cincinnati, Dayton, and the Ohio River valley, rich in history, museums, top-tier sports, and natural beauty.',
                'population'  => 'Approx. 2.5 million',
                'highlights'  => ['Cincinnati Museum Center at Union Terminal', 'National Museum of the US Air Force (Dayton)', 'Great American Ball Park', 'Caesar Creek State Park'],
            ],
            'southeast' => [
                'description' => 'Southeast Ohio is the heart of Appalachian Ohio, featuring rugged hills, ancient hollows, Ohio River towns, and some of the state\'s most dramatic natural scenery.',
                'population'  => 'Approx. 600 thousand',
                'highlights'  => ['Hocking Hills State Park', 'Burr Oak State Park', 'Ohio River Scenic Byway', 'Serpent Mound Historic Site'],
            ],
        ];
    }

    /**
     * Render the Region Info admin page.
     */
    public static function render_region_info_page() {
        if (isset($_POST['oha_region_info_nonce']) && wp_verify_nonce($_POST['oha_region_info_nonce'], 'oha_region_info')) {
            $raw  = isset($_POST['region_info']) && is_array($_POST['region_info']) ? $_POST['region_info'] : [];
            $save = [];
            foreach (OHA_Map_Regions::get_region_slugs() as $slug) {
                $r = isset($raw[$slug]) && is_array($raw[$slug]) ? $raw[$slug] : [];
                $highlights = [];
                for ($i = 0; $i < 4; $i++) {
                    $h = sanitize_text_field($r['highlight_' . $i] ?? '');
                    if ($h !== '') $highlights[] = $h;
                }
                $save[$slug] = [
                    'description' => sanitize_textarea_field($r['description'] ?? ''),
                    'population'  => sanitize_text_field($r['population'] ?? ''),
                    'highlights'  => $highlights,
                ];
            }
            update_option('oha_map_region_info', $save);
            echo '<div class="notice notice-success"><p>' . __('Region info saved!', 'oha-modern-map') . '</p></div>';
        }

        $info_data    = self::get_region_info();
        $region_names = OHA_Map_Regions::get_region_names();
        ?>
        <div class="wrap">
            <h1><?php _e('Region Info', 'oha-modern-map'); ?></h1>
            <p class="description">
                <?php _e('Edit the description, estimated population, and highlights shown in the region info panel when a user clicks on a region.', 'oha-modern-map'); ?>
            </p>
            <form method="post" action="">
                <?php wp_nonce_field('oha_region_info', 'oha_region_info_nonce'); ?>
                <?php foreach ($region_names as $slug => $name) :
                    $info = $info_data[$slug] ?? [];
                    $desc = $info['description'] ?? '';
                    $pop  = $info['population']  ?? '';
                    $hls  = array_pad((array)($info['highlights'] ?? []), 4, '');
                ?>
                <h2 style="margin-top:24px;"><?php echo esc_html($name); ?></h2>
                <table class="form-table" style="margin-top:0;">
                    <tr>
                        <th scope="row"><label><?php _e('Description', 'oha-modern-map'); ?></label></th>
                        <td>
                            <textarea name="region_info[<?php echo esc_attr($slug); ?>][description]"
                                      rows="3" class="large-text"><?php echo esc_textarea($desc); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label><?php _e('Estimated Population', 'oha-modern-map'); ?></label></th>
                        <td>
                            <input type="text" name="region_info[<?php echo esc_attr($slug); ?>][population]"
                                   value="<?php echo esc_attr($pop); ?>" class="regular-text"
                                   placeholder="Approx. 1.2 million">
                        </td>
                    </tr>
                    <?php for ($i = 0; $i < 4; $i++) : ?>
                    <tr>
                        <th scope="row"><label><?php echo sprintf(__('Highlight %d', 'oha-modern-map'), $i + 1); ?></label></th>
                        <td>
                            <input type="text" name="region_info[<?php echo esc_attr($slug); ?>][highlight_<?php echo $i; ?>]"
                                   value="<?php echo esc_attr($hls[$i]); ?>" class="regular-text"
                                   placeholder="<?php _e('e.g. Rock Hall of Fame', 'oha-modern-map'); ?>">
                        </td>
                    </tr>
                    <?php endfor; ?>
                </table>
                <?php endforeach; ?>
                <?php submit_button(__('Save Region Info', 'oha-modern-map')); ?>
            </form>
        </div>
        <?php
    }

    // -----------------------------------------------------------------------
    // New v2.0 Admin Pages
    // -----------------------------------------------------------------------

    /**
     * Render the Region Colors admin page.
     */
    public static function render_region_colors_page() {
        if (isset($_POST['oha_region_colors_nonce']) && wp_verify_nonce($_POST['oha_region_colors_nonce'], 'oha_region_colors')) {
            $saved = [];
            foreach (OHA_Map_Regions::get_region_slugs() as $slug) {
                $saved[$slug] = [
                    'fill'    => sanitize_hex_color($_POST['region_colors'][$slug]['fill']    ?? '#ff6b35'),
                    'outline' => sanitize_hex_color($_POST['region_colors'][$slug]['outline'] ?? '#ff8c5a'),
                ];
            }
            update_option('oha_map_region_colors', $saved);
            echo '<div class="notice notice-success"><p>' . __('Region colors saved!', 'oha-modern-map') . '</p></div>';
        }

        $saved_colors    = get_option('oha_map_region_colors', []);
        $default_colors  = OHA_Map_Regions::get_default_region_colors();
        $region_names    = OHA_Map_Regions::get_region_names();
        ?>
        <div class="wrap">
            <h1><?php _e('Region Colors', 'oha-modern-map'); ?></h1>
            <p class="description">
                <?php _e('Set fill and outline colors for each Ohio region. Fill is the semi-transparent overlay shown when a region is selected. Outline is the border drawn at all times.', 'oha-modern-map'); ?>
            </p>

            <form method="post" action="">
                <?php wp_nonce_field('oha_region_colors', 'oha_region_colors_nonce'); ?>

                <table class="form-table">
                    <?php foreach ($region_names as $slug => $name) :
                        $colors  = isset($saved_colors[$slug]) ? $saved_colors[$slug] : ($default_colors[$slug] ?? []);
                        $fill    = $colors['fill']    ?? '#ff6b35';
                        $outline = $colors['outline'] ?? '#ff8c5a';
                    ?>
                    <tr>
                        <th scope="row"><strong><?php echo esc_html($name); ?></strong></th>
                        <td>
                            <label style="margin-right:20px;">
                                <?php _e('Fill color:', 'oha-modern-map'); ?><br>
                                <input type="text" name="region_colors[<?php echo esc_attr($slug); ?>][fill]"
                                       value="<?php echo esc_attr($fill); ?>"
                                       class="oha-color-picker">
                            </label>
                            <label>
                                <?php _e('Outline color:', 'oha-modern-map'); ?><br>
                                <input type="text" name="region_colors[<?php echo esc_attr($slug); ?>][outline]"
                                       value="<?php echo esc_attr($outline); ?>"
                                       class="oha-color-picker">
                            </label>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>

                <?php submit_button(__('Save Region Colors', 'oha-modern-map')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the Card Fields admin page.
     */
    public static function render_card_fields_page() {
        if (isset($_POST['oha_card_fields_nonce']) && wp_verify_nonce($_POST['oha_card_fields_nonce'], 'oha_card_fields')) {
            $raw    = isset($_POST['card_fields']) && is_array($_POST['card_fields']) ? $_POST['card_fields'] : [];
            $fields = [
                'thumbnail'       => !empty($raw['thumbnail']),
                'type_badge'      => !empty($raw['type_badge']),
                'status_badge'    => !empty($raw['status_badge']),
                'address'         => !empty($raw['address']),
                'phone'           => !empty($raw['phone']),
                'website'         => !empty($raw['website']),
                'email'           => !empty($raw['email']),
                'view_details'    => !empty($raw['view_details']),
                'upcoming_events' => !empty($raw['upcoming_events']),
                'description'     => !empty($raw['description']),
            ];
            update_option('oha_map_card_fields', $fields);
            echo '<div class="notice notice-success"><p>' . __('Card fields saved!', 'oha-modern-map') . '</p></div>';
        }

        $fields = self::get_card_fields();
        $labels = [
            'thumbnail'       => __('Thumbnail image', 'oha-modern-map'),
            'type_badge'      => __('Listing type badge (Featured / Professional / etc.)', 'oha-modern-map'),
            'status_badge'    => __('Haunt status badge (Open / Seasonal)', 'oha-modern-map'),
            'address'         => __('Address', 'oha-modern-map'),
            'phone'           => __('Phone number', 'oha-modern-map'),
            'website'         => __('Website link', 'oha-modern-map'),
            'email'           => __('Email address', 'oha-modern-map'),
            'view_details'    => __('View Details button (links to listing page)', 'oha-modern-map'),
            'upcoming_events' => __('Upcoming Events section', 'oha-modern-map'),
            'description'     => __('Short description / excerpt', 'oha-modern-map'),
        ];
        ?>
        <div class="wrap">
            <h1><?php _e('Map Card Fields', 'oha-modern-map'); ?></h1>
            <p class="description">
                <?php _e('Choose which information to display in the popup card when a haunt marker is clicked.', 'oha-modern-map'); ?>
            </p>

            <form method="post" action="">
                <?php wp_nonce_field('oha_card_fields', 'oha_card_fields_nonce'); ?>

                <table class="form-table">
                    <?php foreach ($labels as $key => $label) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($label); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="card_fields[<?php echo esc_attr($key); ?>]" value="1"
                                       <?php checked(!empty($fields[$key])); ?>>
                                <?php _e('Show this field', 'oha-modern-map'); ?>
                            </label>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>

                <?php submit_button(__('Save Card Fields', 'oha-modern-map')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render Diagnostics page — shows raw GeoDirectory data to help debug map issues
     */
    public static function render_diagnostics_page() {
        global $wpdb;

        if (!current_user_can('manage_options')) return;

        $fs         = self::get_field_settings();
        $post_type  = $fs['post_type'];
        $lat_field  = $fs['lat_field'];
        $lng_field  = $fs['lng_field'];

        // Build detail table name
        if (function_exists('geodir_db_cpt_table')) {
            $detail_tbl = geodir_db_cpt_table($post_type);
        } else {
            global $plugin_prefix;
            $prefix     = !empty($plugin_prefix) ? $plugin_prefix : $wpdb->prefix . 'geodir_';
            $detail_tbl = $prefix . $post_type . '_detail';
        }

        $gd_active    = class_exists('GeoDirectory');
        $table_exists = (bool) $wpdb->get_var("SHOW TABLES LIKE '{$detail_tbl}'");
        $total_posts  = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
            $post_type
        ));

        $with_both   = 0;
        $columns     = [];
        $sample_rows = [];
        $column_notice = '';

        if ($table_exists) {
            $columns = $wpdb->get_col("SHOW COLUMNS FROM {$detail_tbl}", 0);

            $lat_exists = in_array($lat_field, $columns);
            $lng_exists = in_array($lng_field, $columns);

            if (!$lat_exists || !$lng_exists) {
                $column_notice = sprintf(
                    __('WARNING: Column "%s" or "%s" not found in detail table. Check your field mapping below or in Settings.', 'oha-modern-map'),
                    $lat_field, $lng_field
                );
            }

            if ($lat_exists && $lng_exists) {
                $with_both = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$detail_tbl}
                     WHERE `{$lat_field}` IS NOT NULL AND `{$lat_field}` != ''
                       AND `{$lng_field}` IS NOT NULL AND `{$lng_field}` != ''"
                );
            }

            $sample_rows = $wpdb->get_results(
                "SELECT p.ID, p.post_title,
                        pd.`{$lat_field}` AS lat_val,
                        pd.`{$lng_field}` AS lng_val
                 FROM {$wpdb->posts} p
                 INNER JOIN {$detail_tbl} pd ON pd.post_id = p.ID
                 WHERE p.post_status = 'publish'
                 ORDER BY p.post_title ASC
                 LIMIT 15"
            );
        }

        ?>
        <div class="wrap">
            <h1><?php _e('OHA Modern Map — Diagnostics', 'oha-modern-map'); ?></h1>
            <p><?php _e('This page shows raw GeoDirectory data to help diagnose why haunts may not appear on the map.', 'oha-modern-map'); ?></p>

            <h2><?php _e('GeoDirectory Status', 'oha-modern-map'); ?></h2>
            <table class="widefat striped" style="max-width:700px">
                <tr><td><strong>GeoDirectory Plugin Active</strong></td><td><?php echo $gd_active ? '<span style="color:green">✓ YES</span>' : '<span style="color:red">✗ NO — install/activate GeoDirectory</span>'; ?></td></tr>
                <tr><td><strong>Configured Post Type</strong></td><td><code><?php echo esc_html($post_type); ?></code></td></tr>
                <tr><td><strong>Published Listings Found</strong></td><td><?php echo $total_posts; ?></td></tr>
                <tr><td><strong>Detail Table</strong></td><td><code><?php echo esc_html($detail_tbl); ?></code></td></tr>
                <tr><td><strong>Table Exists</strong></td><td><?php echo $table_exists ? '<span style="color:green">✓ YES</span>' : '<span style="color:red">✗ NO</span>'; ?></td></tr>
                <tr><td><strong>Configured Latitude Column</strong></td><td><code><?php echo esc_html($lat_field); ?></code></td></tr>
                <tr><td><strong>Configured Longitude Column</strong></td><td><code><?php echo esc_html($lng_field); ?></code></td></tr>
                <tr><td><strong>Listings with Both Coordinates</strong></td><td><?php echo $with_both; ?></td></tr>
            </table>

            <?php if ($column_notice): ?>
            <div class="notice notice-error"><p><?php echo esc_html($column_notice); ?></p></div>
            <?php endif; ?>

            <?php if ($with_both === 0 && $table_exists): ?>
            <div class="notice notice-warning">
                <p><strong><?php _e('No listings have coordinates yet.', 'oha-modern-map'); ?></strong>
                <?php _e('In GeoDirectory, edit each listing and save an address so the map geocodes lat/lng. Or check that the latitude/longitude column names match the settings below.', 'oha-modern-map'); ?></p>
            </div>
            <?php endif; ?>

            <?php if (!empty($columns)): ?>
            <h2><?php _e('Columns in Detail Table', 'oha-modern-map'); ?></h2>
            <p><?php _e('These are ALL available column names. Your latitude/longitude columns must match exactly.', 'oha-modern-map'); ?></p>
            <p><?php
                $lat_marker = in_array($lat_field, $columns) ? ' ← <strong>lat column found ✓</strong>' : '';
                $lng_marker = in_array($lng_field, $columns) ? ' ← <strong>lng column found ✓</strong>' : '';
                echo '<code>' . implode('</code>, <code>', array_map(function($c) use ($lat_field, $lng_field, $lat_marker, $lng_marker) {
                    $mark = ($c === $lat_field) ? ' <span style="color:green">✓ lat</span>' :
                            (($c === $lng_field) ? ' <span style="color:green">✓ lng</span>' : '');
                    return esc_html($c) . $mark;
                }, $columns)) . '</code>';
            ?></p>
            <?php endif; ?>

            <?php if (!empty($sample_rows)): ?>
            <h2><?php _e('Sample Listing Data', 'oha-modern-map'); ?></h2>
            <table class="widefat striped" style="max-width:800px">
                <thead><tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th><?php echo esc_html($lat_field); ?> (lat)</th>
                    <th><?php echo esc_html($lng_field); ?> (lng)</th>
                    <th>Status</th>
                </tr></thead>
                <tbody>
                <?php foreach ($sample_rows as $r): ?>
                <tr>
                    <td><?php echo $r->ID; ?></td>
                    <td><?php echo esc_html($r->post_title); ?></td>
                    <td><?php echo esc_html($r->lat_val ?? '— empty'); ?></td>
                    <td><?php echo esc_html($r->lng_val ?? '— empty'); ?></td>
                    <td><?php echo (!empty($r->lat_val) && !empty($r->lng_val)) ? '<span style="color:green">✓ Will show on map</span>' : '<span style="color:red">✗ Missing coords</span>'; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <h2><?php _e('Quick Fix: Update Field Mapping', 'oha-modern-map'); ?></h2>
            <p><?php _e('If your column names don\'t match the defaults, update them here and in OHA Map &rsaquo; Settings.', 'oha-modern-map'); ?></p>
            <p><a href="<?php echo admin_url('admin.php?page=oha-modern-map'); ?>" class="button button-primary"><?php _e('Go to Settings to Update Field Names', 'oha-modern-map'); ?></a></p>

            <h2><?php _e('AJAX Endpoint Test', 'oha-modern-map'); ?></h2>
            <p><?php _e('Click to test the AJAX endpoint that the map uses to load listings:', 'oha-modern-map'); ?></p>
            <button id="oha-ajax-test" class="button"><?php _e('Test AJAX (load all listings)', 'oha-modern-map'); ?></button>
            <pre id="oha-ajax-result" style="background:#f0f0f0;padding:10px;margin-top:10px;max-height:300px;overflow:auto;display:none"></pre>
            <script>
            jQuery('#oha-ajax-test').on('click', function() {
                var $result = jQuery('#oha-ajax-result');
                $result.text('Loading...').show();
                jQuery.post(ajaxurl, {
                    action: 'oha_get_map_listings',
                    nonce: '<?php echo wp_create_nonce('oha_map_nonce'); ?>',
                    region: '',
                    type: ''
                }, function(response) {
                    if (response.success) {
                        $result.text('Found ' + response.data.length + ' listings.\n\n' + JSON.stringify(response.data.slice(0, 3), null, 2));
                    } else {
                        $result.text('Error: ' + JSON.stringify(response));
                    }
                }).fail(function(xhr) {
                    $result.text('AJAX failed: ' + xhr.status + ' ' + xhr.responseText.substring(0, 200));
                });
            });
            </script>
        </div>
        <?php
    }

    // -----------------------------------------------------------------------
    // Convention Mode Settings Page
    // -----------------------------------------------------------------------

    /**
     * Render and save the Convention Mode settings page.
     */
    public static function render_convention_settings_page() {
        if (isset($_POST['oha_conv_nonce']) && wp_verify_nonce($_POST['oha_conv_nonce'], 'oha_conv_settings')) {
            self::save_convention_settings();
            echo '<div class="notice notice-success"><p>' . __('Convention settings saved!', 'oha-modern-map') . '</p></div>';
        }

        $settings = OHA_Convention_Data::get_settings();
        $floors   = $settings['floors'];
        ?>
        <div class="wrap">
            <h1><?php _e('Convention Mode Settings', 'oha-modern-map'); ?></h1>
            <p><?php _e('Configure the indoor floor plan map for your OHA convention. Use <code>[oha_convention_map]</code> and <code>[oha_convention_directory]</code> shortcodes on any page.', 'oha-modern-map'); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field('oha_conv_settings', 'oha_conv_nonce'); ?>

                <!-- Enable / Disable -->
                <h2><?php _e('General', 'oha-modern-map'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="conv_enabled"><?php _e('Enable Convention Mode', 'oha-modern-map'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="conv[enabled]" id="conv_enabled" value="1" <?php checked(!empty($settings['enabled'])); ?>>
                                <?php _e('Show convention map shortcodes on the front end', 'oha-modern-map'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="conv_active_year"><?php _e('Convention Year', 'oha-modern-map'); ?></label></th>
                        <td>
                            <input type="text" name="conv[active_year]" id="conv_active_year"
                                   value="<?php echo esc_attr($settings['active_year'] ?? date('Y')); ?>"
                                   style="width:100px" placeholder="2026">
                            <p class="description"><?php _e('Year tag applied to all booths. Change this when starting a new convention year.', 'oha-modern-map'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="conv_event_name"><?php _e('Event Name', 'oha-modern-map'); ?></label></th>
                        <td>
                            <input type="text" name="conv[event_name]" id="conv_event_name" value="<?php echo esc_attr($settings['event_name']); ?>" class="regular-text" placeholder="OHA HauntCon 2026">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="conv_event_dates"><?php _e('Event Dates', 'oha-modern-map'); ?></label></th>
                        <td>
                            <input type="text" name="conv[event_dates]" id="conv_event_dates" value="<?php echo esc_attr($settings['event_dates']); ?>" class="regular-text" placeholder="October 3–5, 2026">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="conv_venue_name"><?php _e('Venue Name', 'oha-modern-map'); ?></label></th>
                        <td>
                            <input type="text" name="conv[venue_name]" id="conv_venue_name" value="<?php echo esc_attr($settings['venue_name']); ?>" class="regular-text" placeholder="Greater Columbus Convention Center">
                        </td>
                    </tr>
                </table>

                <!-- Floors -->
                <h2><?php _e('Floor Plans', 'oha-modern-map'); ?></h2>
                <p class="description"><?php _e('Upload a floor plan image for each level of your venue. Booths are positioned using X/Y percentages relative to each floor image.', 'oha-modern-map'); ?></p>

                <div id="oha-conv-floors-list">
                    <?php foreach ($floors as $idx => $floor) : ?>
                    <div class="oha-conv-floor-row" style="background:#f9f9f9;border:1px solid #ddd;padding:14px;margin-bottom:10px;border-radius:4px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                            <span class="oha-floor-drag-handle" title="Drag to reorder" style="cursor:move;color:#999;font-size:18px;line-height:1;user-select:none;">&#9776;</span>
                            <button type="button" class="button oha-conv-remove-floor" style="color:#a00;">Remove Floor</button>
                        </div>
                        <input type="hidden" name="conv_floors[<?php echo $idx; ?>][id]" value="<?php echo esc_attr($floor['id']); ?>">

                        <table class="form-table" style="margin:0;">
                            <tr>
                                <th style="width:200px"><label><?php _e('Floor Label', 'oha-modern-map'); ?></label></th>
                                <td>
                                    <input type="text" name="conv_floors[<?php echo $idx; ?>][label]"
                                           value="<?php echo esc_attr($floor['label']); ?>" class="regular-text"
                                           placeholder="Main Hall">
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Floor Plan Image', 'oha-modern-map'); ?></label></th>
                                <td>
                                    <input type="hidden" name="conv_floors[<?php echo $idx; ?>][image_id]"
                                           id="conv_floor_img_id_<?php echo $idx; ?>"
                                           value="<?php echo esc_attr($floor['image_id'] ?? 0); ?>">
                                    <input type="text" name="conv_floors[<?php echo $idx; ?>][image_url]"
                                           id="conv_floor_img_url_<?php echo $idx; ?>"
                                           value="<?php echo esc_attr($floor['image_url'] ?? ''); ?>"
                                           class="large-text" placeholder="https://">
                                    <button type="button" class="button oha-conv-upload-btn"
                                            data-idx="<?php echo $idx; ?>" style="margin-left:6px;">
                                        <?php _e('Upload / Select Image', 'oha-modern-map'); ?>
                                    </button>
                                    <?php if (!empty($floor['image_url'])) : ?>
                                        <br><img src="<?php echo esc_url($floor['image_url']); ?>"
                                                 style="max-height:80px;margin-top:6px;border-radius:4px;">
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Map Background Color', 'oha-modern-map'); ?></label></th>
                                <td>
                                    <input type="text" name="conv_floors[<?php echo $idx; ?>][bg_color]"
                                           value="<?php echo esc_attr($floor['bg_color'] ?? '#1a1a2e'); ?>"
                                           class="oha-color-picker">
                                </td>
                            </tr>
                        </table>
                    </div>
                    <?php endforeach; ?>
                </div>

                <p>
                    <button type="button" class="button" id="oha-conv-add-floor">
                        + <?php _e('Add Another Floor', 'oha-modern-map'); ?>
                    </button>
                </p>

                <!-- New Convention Year -->
                <h2><?php _e('Start a New Convention Year', 'oha-modern-map'); ?></h2>
                <p><?php _e('Copy all booth positions and numbers from the current active year to a new year. Vendor links are cleared so you can reassign vendors for the new year.', 'oha-modern-map'); ?></p>
                <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:8px;">
                    <input type="text" id="oha-new-year-input" placeholder="2027" style="width:90px">
                    <button type="button" class="button button-primary" id="oha-clone-year-btn">
                        <?php _e('Clone Booth Positions to New Year', 'oha-modern-map'); ?>
                    </button>
                    <span id="oha-clone-result" style="font-weight:600;"></span>
                </div>
                <p class="description"><?php _e('After cloning: update the Convention Year field above and save settings. Then go to Booth Placement to reassign vendors for the new year.', 'oha-modern-map'); ?></p>
                <script>
                jQuery(document).ready(function($) {
                    $('#oha-clone-year-btn').on('click', function() {
                        var toYear = $('#oha-new-year-input').val().trim();
                        if (!toYear) { alert('Please enter a target year.'); return; }
                        var fromYear = $('#conv_active_year').val().trim() || '<?php echo esc_js($settings['active_year'] ?? date('Y')); ?>';
                        var $btn = $(this);
                        var $result = $('#oha-clone-result');
                        $btn.prop('disabled', true).text('Cloning...');
                        $result.text('').css('color', '#46b450');
                        $.post(ajaxurl, {
                            action: 'oha_clone_year_layout',
                            nonce: '<?php echo wp_create_nonce('oha_map_nonce'); ?>',
                            from_year: fromYear,
                            to_year: toYear
                        }, function(resp) {
                            $btn.prop('disabled', false).text('Clone Booth Positions to New Year');
                            if (resp.success) {
                                $result.css('color', '#46b450').text('Done! Cloned ' + resp.data.cloned + ' booths to ' + toYear + '.');
                            } else {
                                $result.css('color', '#dc3232').text('Error: ' + (resp.data || 'Unknown error'));
                            }
                        }).fail(function() {
                            $btn.prop('disabled', false).text('Clone Booth Positions to New Year');
                            $result.css('color', '#dc3232').text('AJAX request failed.');
                        });
                    });
                });
                </script>

                <!-- Shortcode Reference -->
                <h2><?php _e('Shortcode Reference', 'oha-modern-map'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Convention Map', 'oha-modern-map'); ?></th>
                        <td>
                            <code>[oha_convention_map]</code><br>
                            <code>[oha_convention_map floor="1" height="700px" show_list="true"]</code>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Booth Directory', 'oha-modern-map'); ?></th>
                        <td>
                            <code>[oha_convention_directory]</code><br>
                            <code>[oha_convention_directory type="vendor" columns="3"]</code>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Deep Link Examples', 'oha-modern-map'); ?></th>
                        <td>
                            <code>/convention/?oha_booth=217</code> — open map and highlight booth 217<br>
                            <code>/convention/?oha_vendor=fright-barn</code> — open booth linked to a GD vendor
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Manage Booths', 'oha-modern-map'); ?></th>
                        <td>
                            <a href="<?php echo admin_url('edit.php?post_type=oha_booth'); ?>" class="button">
                                <?php _e('View / Edit Booths', 'oha-modern-map'); ?>
                            </a>
                            &nbsp;
                            <a href="<?php echo admin_url('post-new.php?post_type=oha_booth'); ?>" class="button button-primary">
                                <?php _e('Add New Booth', 'oha-modern-map'); ?>
                            </a>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php esc_attr_e('Save Convention Settings', 'oha-modern-map'); ?>">
                </p>

            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Re-index all floor rows so name/id attributes stay sequential
            function reindexFloors() {
                $('#oha-conv-floors-list .oha-conv-floor-row').each(function(i) {
                    $(this).find('input[name^="conv_floors["]').each(function() {
                        this.name = this.name.replace(/conv_floors\[\d+\]/, 'conv_floors[' + i + ']');
                    });
                    // Keep id attrs in sync for media uploader
                    var $imgId  = $(this).find('input[id^="conv_floor_img_id_"]');
                    var $imgUrl = $(this).find('input[id^="conv_floor_img_url_"]');
                    var $btn    = $(this).find('.oha-conv-upload-btn');
                    $imgId.attr('id',  'conv_floor_img_id_'  + i);
                    $imgUrl.attr('id', 'conv_floor_img_url_' + i);
                    $btn.data('idx', i);
                });
            }

            // Drag-to-reorder
            $('#oha-conv-floors-list').sortable({
                handle: '.oha-floor-drag-handle',
                axis: 'y',
                stop: function() { reindexFloors(); }
            });

            // Remove floor row
            $(document).on('click', '.oha-conv-remove-floor', function() {
                var $list = $('#oha-conv-floors-list');
                if ($list.find('.oha-conv-floor-row').length <= 1) {
                    alert('At least one floor is required.');
                    return;
                }
                if (!confirm('Remove this floor? Booth placements on this floor will not be deleted automatically.')) return;
                $(this).closest('.oha-conv-floor-row').remove();
                reindexFloors();
            });

            // Media upload for floor images
            $(document).on('click', '.oha-conv-upload-btn', function(e) {
                e.preventDefault();
                var idx = $(this).data('idx');
                var frame = wp.media({
                    title: 'Select Floor Plan Image',
                    button: { text: 'Use this image' },
                    multiple: false
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#conv_floor_img_id_'  + idx).val(attachment.id);
                    $('#conv_floor_img_url_' + idx).val(attachment.url);
                });
                frame.open();
            });

            // Add floor row
            var floorCount = <?php echo count($floors); ?>;
            $('#oha-conv-add-floor').on('click', function() {
                var idx = floorCount++;
                var html = '<div class="oha-conv-floor-row" style="background:#f9f9f9;border:1px solid #ddd;padding:14px;margin-bottom:10px;border-radius:4px;">' +
                    '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">' +
                    '<span class="oha-floor-drag-handle" title="Drag to reorder" style="cursor:move;color:#999;font-size:18px;line-height:1;user-select:none;">&#9776;</span>' +
                    '<button type="button" class="button oha-conv-remove-floor" style="color:#a00;">Remove Floor</button>' +
                    '</div>' +
                    '<input type="hidden" name="conv_floors[' + idx + '][id]" value="' + (idx + 1) + '">' +
                    '<table class="form-table" style="margin:0;">' +
                    '<tr><th style="width:200px"><label>Floor Label</label></th>' +
                    '<td><input type="text" name="conv_floors[' + idx + '][label]" value="" class="regular-text" placeholder="Upper Level"></td></tr>' +
                    '<tr><th><label>Floor Plan Image</label></th>' +
                    '<td><input type="hidden" name="conv_floors[' + idx + '][image_id]" id="conv_floor_img_id_' + idx + '" value="0">' +
                    '<input type="text" name="conv_floors[' + idx + '][image_url]" id="conv_floor_img_url_' + idx + '" value="" class="large-text" placeholder="https://">' +
                    '<button type="button" class="button oha-conv-upload-btn" data-idx="' + idx + '" style="margin-left:6px;">Upload / Select Image</button></td></tr>' +
                    '<tr><th><label>Map Background Color</label></th>' +
                    '<td><input type="text" name="conv_floors[' + idx + '][bg_color]" value="#1a1a2e" class="oha-color-picker"></td></tr>' +
                    '</table></div>';
                $('#oha-conv-floors-list').append(html);
                $('#oha-conv-floors-list .oha-color-picker').not('.wp-color-picker').wpColorPicker();
            });
        });
        </script>
        <?php
    }

    /**
     * Save convention settings from POST.
     */
    private static function save_convention_settings() {
        $data = $_POST['conv'] ?? [];
        $settings = [
            'enabled'     => !empty($data['enabled']),
            'active_year' => sanitize_text_field($data['active_year'] ?? date('Y')),
            'event_name'  => sanitize_text_field($data['event_name'] ?? ''),
            'event_dates' => sanitize_text_field($data['event_dates'] ?? ''),
            'venue_name'  => sanitize_text_field($data['venue_name'] ?? ''),
            'floors'      => [],
        ];

        $raw_floors = $_POST['conv_floors'] ?? [];
        foreach ($raw_floors as $f) {
            $settings['floors'][] = [
                'id'        => intval($f['id'] ?? 1),
                'label'     => sanitize_text_field($f['label'] ?? ''),
                'image_id'  => intval($f['image_id'] ?? 0),
                'image_url' => esc_url_raw($f['image_url'] ?? ''),
                'bg_color'  => sanitize_hex_color($f['bg_color'] ?? '#1a1a2e') ?: '#1a1a2e',
            ];
        }

        // Ensure at least one floor
        if (empty($settings['floors'])) {
            $settings['floors'][] = ['id' => 1, 'label' => 'Main Hall', 'image_id' => 0, 'image_url' => '', 'bg_color' => '#1a1a2e'];
        }

        update_option('oha_convention_settings', $settings);
    }

    // -----------------------------------------------------------------------
    // Booth Placement Page
    // -----------------------------------------------------------------------

    /**
     * Render the visual Booth Placement admin page.
     */
    public static function render_placement_page() {
        if (!current_user_can('manage_options')) return;

        $settings    = OHA_Convention_Data::get_settings();
        $floors      = $settings['floors'];
        $active_year = $settings['active_year'] ?? date('Y');
        $nonce       = wp_create_nonce('oha_map_nonce');

        // Build floors data for JS
        $floors_js = [];
        foreach ($floors as $floor) {
            $floors_js[] = [
                'id'        => $floor['id'],
                'label'     => $floor['label'],
                'image_url' => $floor['image_url'] ?? '',
                'bg_color'  => $floor['bg_color']  ?? '#1a1a2e',
            ];
        }

        $first_floor_id = !empty($floors) ? $floors[0]['id'] : 1;
        $initial_booths = OHA_Convention_Data::get_booths($first_floor_id, $active_year);
        ?>
        <div class="wrap">
            <h1><?php printf(__('Booth Placement &mdash; %s', 'oha-modern-map'), esc_html($active_year)); ?></h1>

            <?php if (empty($floors) || empty($floors[0]['image_url'])) : ?>
            <div class="notice notice-warning">
                <p><?php printf(
                    __('No floor plan image is set. <a href="%s">Go to Convention Settings</a> to upload a floor plan image first.', 'oha-modern-map'),
                    admin_url('admin.php?page=oha-convention-settings')
                ); ?></p>
            </div>
            <?php endif; ?>

            <!-- Floor tabs -->
            <div class="oha-placement-floor-tabs">
                <?php foreach ($floors as $i => $floor) : ?>
                <button type="button"
                        class="button oha-floor-tab<?php echo $i === 0 ? ' active' : ''; ?>"
                        data-floor-id="<?php echo esc_attr($floor['id']); ?>">
                    <?php echo esc_html($floor['label'] ?: 'Floor ' . $floor['id']); ?>
                </button>
                <?php endforeach; ?>
            </div>

            <div class="oha-placement-layout">

                <!-- Canvas area -->
                <div class="oha-placement-canvas-wrap">
                    <div id="oha-placement-canvas">
                        <?php if (!empty($floors[0]['image_url'])) : ?>
                        <img id="oha-placement-img"
                             src="<?php echo esc_url($floors[0]['image_url']); ?>"
                             alt="<?php esc_attr_e('Floor plan', 'oha-modern-map'); ?>">
                        <?php else : ?>
                        <div id="oha-placement-img-placeholder">
                            <p><?php _e('No floor plan image uploaded.', 'oha-modern-map'); ?></p>
                        </div>
                        <?php endif; ?>
                        <!-- Booth pins injected by JS -->
                    </div>
                    <p class="description" style="margin-top:8px;">
                        <?php _e('Click anywhere on the floor plan to add a new booth. Drag existing pins to reposition.', 'oha-modern-map'); ?>
                    </p>
                </div>

                <!-- Detail panel -->
                <div class="oha-placement-panel" id="oha-placement-panel">

                    <div id="oha-panel-empty">
                        <p style="color:#999;text-align:center;margin-top:24px;line-height:1.6;">
                            <?php _e('Click on the floor plan to place a new booth, or click an existing pin to edit it.', 'oha-modern-map'); ?>
                        </p>
                    </div>

                    <div id="oha-panel-form" style="display:none;">
                        <h3 id="oha-panel-title"><?php _e('New Booth', 'oha-modern-map'); ?></h3>
                        <input type="hidden" id="oha-form-booth-id" value="0">
                        <input type="hidden" id="oha-form-x" value="">
                        <input type="hidden" id="oha-form-y" value="">
                        <input type="hidden" id="oha-form-vendor-id" value="">

                        <table style="width:100%;border-collapse:collapse;">
                            <tr>
                                <th style="width:90px;padding:7px 8px 7px 0;text-align:left;font-weight:600;vertical-align:top;">
                                    <label for="oha-form-number"><?php _e('Booth #', 'oha-modern-map'); ?></label>
                                </th>
                                <td style="padding:5px 0;">
                                    <input type="text" id="oha-form-number" style="width:100%;" placeholder="217">
                                </td>
                            </tr>
                            <tr>
                                <th style="padding:7px 8px 7px 0;text-align:left;font-weight:600;vertical-align:top;">
                                    <label><?php _e('Vendor', 'oha-modern-map'); ?></label>
                                </th>
                                <td style="padding:5px 0;position:relative;">
                                    <div id="oha-vendor-selected" style="display:none;margin-bottom:6px;">
                                        <span id="oha-vendor-name" style="font-weight:600;font-size:13px;"></span>
                                        <span id="oha-vendor-status-badge" style="margin-left:6px;padding:2px 6px;border-radius:3px;font-size:11px;font-weight:600;"></span>
                                        <a href="#" id="oha-vendor-clear" style="margin-left:8px;font-size:12px;color:#a00;"><?php _e('Clear', 'oha-modern-map'); ?></a>
                                    </div>
                                    <input type="text" id="oha-vendor-search" style="width:100%;"
                                           placeholder="<?php esc_attr_e('Search vendors...', 'oha-modern-map'); ?>"
                                           autocomplete="off">
                                    <div id="oha-vendor-results" class="oha-vendor-search-results" style="display:none;"></div>
                                </td>
                            </tr>
                            <tr>
                                <th style="padding:7px 8px 7px 0;text-align:left;font-weight:600;vertical-align:top;">
                                    <label for="oha-form-type"><?php _e('Type', 'oha-modern-map'); ?></label>
                                </th>
                                <td style="padding:5px 0;">
                                    <select id="oha-form-type" style="width:100%;">
                                        <option value="vendor"><?php _e('Vendor', 'oha-modern-map'); ?></option>
                                        <option value="food"><?php _e('Food', 'oha-modern-map'); ?></option>
                                        <option value="restroom"><?php _e('Restroom', 'oha-modern-map'); ?></option>
                                        <option value="class"><?php _e('Class / Seminar', 'oha-modern-map'); ?></option>
                                        <option value="stage"><?php _e('Stage', 'oha-modern-map'); ?></option>
                                        <option value="info"><?php _e('Info / Registration', 'oha-modern-map'); ?></option>
                                        <option value="exit"><?php _e('Exit / Entrance', 'oha-modern-map'); ?></option>
                                        <option value="general"><?php _e('General / Other', 'oha-modern-map'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th style="padding:7px 8px 7px 0;text-align:left;font-weight:600;vertical-align:top;">
                                    <label for="oha-form-status"><?php _e('Status', 'oha-modern-map'); ?></label>
                                </th>
                                <td style="padding:5px 0;">
                                    <select id="oha-form-status" style="width:100%;">
                                        <option value="open"><?php _e('Open', 'oha-modern-map'); ?></option>
                                        <option value="tbd"><?php _e('TBD', 'oha-modern-map'); ?></option>
                                        <option value="closed"><?php _e('Closed', 'oha-modern-map'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th style="padding:7px 8px 7px 0;text-align:left;font-weight:600;vertical-align:top;">
                                    <label for="oha-form-hours"><?php _e('Hours', 'oha-modern-map'); ?></label>
                                </th>
                                <td style="padding:5px 0;">
                                    <input type="text" id="oha-form-hours" style="width:100%;" placeholder="Fri 6-10pm, Sat 12-10pm">
                                </td>
                            </tr>
                            <tr>
                                <th style="padding:7px 8px 7px 0;text-align:left;font-weight:600;vertical-align:top;">
                                    <label for="oha-form-color"><?php _e('Color', 'oha-modern-map'); ?></label>
                                </th>
                                <td style="padding:5px 0;">
                                    <input type="text" id="oha-form-color" value=""
                                           class="oha-color-picker oha-placement-color-picker"
                                           placeholder="<?php esc_attr_e('Auto (by type)', 'oha-modern-map'); ?>">
                                    <p class="description" style="margin:3px 0 0;font-size:11px;"><?php _e('Leave blank for type default.', 'oha-modern-map'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th style="padding:7px 8px 7px 0;text-align:left;font-weight:600;"><?php _e('Year', 'oha-modern-map'); ?></th>
                                <td style="padding:5px 0;"><strong><?php echo esc_html($active_year); ?></strong></td>
                            </tr>
                        </table>

                        <p style="margin-top:14px;">
                            <button type="button" class="button button-primary" id="oha-panel-save">
                                <?php _e('Save Booth', 'oha-modern-map'); ?>
                            </button>
                            <button type="button" class="button" id="oha-panel-delete"
                                    style="margin-left:6px;color:#a00;border-color:#a00;display:none;">
                                <?php _e('Delete', 'oha-modern-map'); ?>
                            </button>
                            <span id="oha-panel-saving" style="margin-left:10px;display:none;color:#666;font-size:13px;"><?php _e('Saving...', 'oha-modern-map'); ?></span>
                        </p>
                    </div>

                </div><!-- #oha-placement-panel -->
            </div><!-- .oha-placement-layout -->
        </div><!-- .wrap -->

        <script>
        var ohaPlacementConfig = <?php echo wp_json_encode([
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'nonce'         => $nonce,
            'pluginUrl'     => OHA_MODERN_MAP_URL,
            'activeYear'    => $active_year,
            'floors'        => $floors_js,
            'booths'        => $initial_booths,
            'activeFloorId' => $first_floor_id,
        ]); ?>;
        jQuery(document).ready(function($) {
            // Init color picker for the placement panel
            $('#oha-form-color').wpColorPicker({
                defaultColor: '',
                change: function(event, ui) {},
                clear: function() {}
            });
        });
        </script>
        <?php
    }
}
