<?php
/**
 * OHA Map Data Handler
 * Fetches and processes GeoDirectory listings for the map
 *
 * Icon priority per listing:
 *   1. Custom icon uploaded in OHA Map > Custom Icons (per type)
 *   2. GeoDirectory category map icon (ct_cat_icon term meta on default_category)
 *   3. Colored circle fallback
 */

if (!defined('ABSPATH')) exit;

class OHA_Map_Data {

    public static function init() {
        add_action('wp_ajax_oha_get_map_listings', [__CLASS__, 'ajax_get_listings']);
        add_action('wp_ajax_nopriv_oha_get_map_listings', [__CLASS__, 'ajax_get_listings']);
        add_action('wp_ajax_oha_get_map_events', [__CLASS__, 'ajax_get_events']);
        add_action('wp_ajax_nopriv_oha_get_map_events', [__CLASS__, 'ajax_get_events']);
        add_action('wp_ajax_oha_map_debug', [__CLASS__, 'ajax_debug']);
        add_action('wp_ajax_oha_get_ohio_counties',        [__CLASS__, 'ajax_get_ohio_counties']);
        add_action('wp_ajax_nopriv_oha_get_ohio_counties', [__CLASS__, 'ajax_get_ohio_counties']);
        add_action('wp_ajax_oha_clear_county_cache',       [__CLASS__, 'ajax_clear_county_cache']);
    }

    public static function ajax_get_listings() {
        check_ajax_referer('oha_map_nonce', 'nonce');
        $region = isset($_POST['region']) ? sanitize_text_field($_POST['region']) : '';
        $type   = isset($_POST['type'])   ? sanitize_text_field($_POST['type'])   : '';
        wp_send_json_success(self::get_listings($region, $type));
    }

    /** Debug endpoint — admin-only */
    public static function ajax_debug() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        global $wpdb;
        $fs         = OHA_Map_Admin::get_field_settings();
        $post_type  = $fs['post_type'];
        $lat_field  = $fs['lat_field'];
        $lng_field  = $fs['lng_field'];
        $detail_tbl = self::get_detail_table($post_type);

        $total_posts = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
            $post_type
        ));

        $table_exists = (bool) $wpdb->get_var("SHOW TABLES LIKE '{$detail_tbl}'");

        $with_coords = 0;
        $sample      = [];
        $columns     = [];

        if ($table_exists) {
            $with_coords = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$detail_tbl}
                 WHERE `{$lat_field}` IS NOT NULL AND `{$lat_field}` != ''
                 AND `{$lng_field}` IS NOT NULL AND `{$lng_field}` != ''"
            );
            $columns = $wpdb->get_col("SHOW COLUMNS FROM {$detail_tbl}", 0);
            $sample  = $wpdb->get_results(
                "SELECT p.ID, p.post_title,
                        pd.`{$lat_field}` AS lat, pd.`{$lng_field}` AS lng
                 FROM {$wpdb->posts} p
                 INNER JOIN {$detail_tbl} pd ON pd.post_id = p.ID
                 WHERE p.post_status = 'publish'
                 ORDER BY p.post_title ASC
                 LIMIT 10"
            );
        }

        wp_send_json_success([
            'gd_active'    => class_exists('GeoDirectory'),
            'post_type'    => $post_type,
            'detail_table' => $detail_tbl,
            'table_exists' => $table_exists,
            'total_posts'  => $total_posts,
            'with_coords'  => $with_coords,
            'lat_field'    => $lat_field,
            'lng_field'    => $lng_field,
            'columns'      => $columns,
            'sample'       => $sample,
        ]);
    }

    /** Resolve the GeoDirectory detail table name. */
    private static function get_detail_table($post_type) {
        if (function_exists('geodir_db_cpt_table')) {
            return geodir_db_cpt_table($post_type);
        }
        global $wpdb, $plugin_prefix;
        $prefix = !empty($plugin_prefix) ? $plugin_prefix : $wpdb->prefix . 'geodir_';
        return $prefix . $post_type . '_detail';
    }

    /**
     * Pre-load GeoDirectory category icons for a list of category IDs.
     * GD stores the map icon as term meta 'ct_cat_icon' = ['id' => attachment_id, 'src' => path].
     *
     * @param  int[]  $cat_ids
     * @return array  Map of category_id => icon_url
     */
    private static function get_gd_cat_icons(array $cat_ids) {
        $map = [];
        foreach (array_unique(array_filter($cat_ids)) as $cat_id) {
            $meta = get_term_meta((int) $cat_id, 'ct_cat_icon', true);
            if (!empty($meta['id'])) {
                $url = wp_get_attachment_url((int) $meta['id']);
                if ($url) {
                    $map[(int) $cat_id] = $url;
                }
            } elseif (!empty($meta['src'])) {
                // Fallback: src may be a relative file path
                $uploads = wp_upload_dir();
                $map[(int) $cat_id] = trailingslashit($uploads['baseurl']) . $meta['src'];
            }
        }
        return $map;
    }

    /**
     * Get listings from GeoDirectory via a single JOIN query.
     *
     * Uses SELECT pd.* so the query never fails due to non-standard optional column
     * names (e.g. this site uses attraction_phone_number instead of phone).
     *
     * Icon priority per listing (returned as gd_icon_url):
     *   - If the listing's default GeoDirectory category has a map icon set, that URL
     *     is returned. The JS layer will use it only if no Modern Map custom icon is set.
     *
     * @param string $region Region slug
     * @param string $type   Listing type filter
     * @return array
     */
    public static function get_listings($region = '', $type = '') {
        global $wpdb;

        if (!class_exists('GeoDirectory')) {
            return [];
        }

        $fs            = OHA_Map_Admin::get_field_settings();
        $post_type     = $fs['post_type'];
        $lat_field     = $fs['lat_field'];
        $lng_field     = $fs['lng_field'];
        $phone_field   = $fs['phone_field'];
        $email_field   = $fs['email_field'];
        $website_field = $fs['website_field'];
        $detail_tbl    = self::get_detail_table($post_type);

        // pd.* avoids failure when optional columns (phone/email/website) have
        // non-standard names. Only lat/lng must exist for the WHERE clause to succeed.
        $sql = "SELECT p.ID, p.post_title, pd.*
                FROM {$wpdb->posts} p
                INNER JOIN {$detail_tbl} pd ON pd.post_id = p.ID
                WHERE p.post_type   = %s
                  AND p.post_status = 'publish'
                  AND pd.`{$lat_field}`  IS NOT NULL AND pd.`{$lat_field}`  != ''
                  AND pd.`{$lng_field}` IS NOT NULL AND pd.`{$lng_field}` != ''
                ORDER BY p.post_title ASC";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $post_type));

        if (empty($rows)) {
            return [];
        }

        // Pre-load GeoDirectory category icons in bulk (one get_term_meta per unique cat)
        $cat_ids      = array_column($rows, 'default_category');
        $cat_icon_map = self::get_gd_cat_icons($cat_ids);

        $region_data = $region ? OHA_Map_Regions::get_region($region) : null;
        $listings    = [];

        foreach ($rows as $row) {
            $lat = floatval($row->{$lat_field});
            $lng = floatval($row->{$lng_field});

            // Region bounds filter (PHP-side; GD fields not in wp_postmeta)
            if ($region_data) {
                $b = $region_data['bounds'];
                if ($lat < $b['south'] || $lat > $b['north'] ||
                    $lng < $b['west']  || $lng > $b['east']) {
                    continue;
                }
            }

            $listing_type = self::determine_listing_type($row->ID, $row);

            if ($type && $type !== 'all' && $listing_type !== $type) {
                continue;
            }

            $phone   = !empty($row->{$phone_field})   ? $row->{$phone_field}   : '';
            $email   = !empty($row->{$email_field})   ? $row->{$email_field}   : '';
            $website = !empty($row->{$website_field}) ? $row->{$website_field} : '';

            // GD category icon — used by JS as fallback when no Modern Map custom icon is set
            $gd_icon_url = !empty($row->default_category) && isset($cat_icon_map[(int) $row->default_category])
                ? $cat_icon_map[(int) $row->default_category]
                : '';

            $listings[] = [
                'id'          => $row->ID,
                'title'       => $row->post_title,
                'lat'         => $lat,
                'lng'         => $lng,
                'type'        => $listing_type,
                'permalink'   => get_permalink($row->ID),
                'address'     => self::get_address($row),
                'city'        => !empty($row->city) ? $row->city : '',
                'phone'       => $phone,
                'email'       => $email,
                'website'     => $website,
                'status'      => self::get_haunt_status($row->ID),
                'featured'    => !empty($row->featured) && $row->featured == '1',
                'region'      => OHA_Map_Regions::get_region_for_point($lat, $lng),
                'gd_icon_url' => $gd_icon_url,
                'thumbnail'   => get_the_post_thumbnail_url((int) $row->ID, 'thumbnail') ?: '',
            ];
        }

        return apply_filters('oha_map_listings', $listings, $region, $type);
    }

    /**
     * Determine listing type hierarchy.
     *
     * @param int    $post_id Post ID
     * @param object $row     Row from the SQL JOIN query (pd.*)
     * @return string Listing type slug
     */
    private static function determine_listing_type($post_id, $row = null) {
        $is_featured = $row
            ? (!empty($row->featured) && $row->featured == '1')
            : (get_post_meta($post_id, 'featured', true) === '1');

        if ($is_featured) {
            return 'featured';
        }

        $mappings = OHA_Map_Admin::get_category_mappings();
        $terms    = wp_get_post_terms($post_id, 'gd_placecategory', ['fields' => 'slugs']);

        if (is_wp_error($terms) || empty($terms)) {
            return 'free';
        }

        if (!empty($mappings['custom_types'])) {
            foreach ($mappings['custom_types'] as $custom_type) {
                if (!empty($custom_type['categories'])) {
                    $slugs = array_map('trim', explode(',', $custom_type['categories']));
                    foreach ($slugs as $slug) {
                        if (in_array($slug, $terms)) {
                            return $custom_type['slug'];
                        }
                    }
                }
            }
        }

        if (!empty($mappings['home_haunt'])) {
            foreach (array_map('trim', explode(',', $mappings['home_haunt'])) as $slug) {
                if (in_array($slug, $terms)) {
                    return 'home_haunt';
                }
            }
        }

        if (!empty($mappings['professional'])) {
            foreach (array_map('trim', explode(',', $mappings['professional'])) as $slug) {
                if (in_array($slug, $terms)) {
                    return 'professional';
                }
            }
        }

        $oha_member = get_post_meta($post_id, 'oha_member', true);
        $pro_haunt  = get_post_meta($post_id, 'professional_haunt', true);
        if ($oha_member === '1' || $pro_haunt === '1') {
            return 'professional';
        }

        $home_haunt = get_post_meta($post_id, 'home_haunt', true);
        if ($home_haunt === '1') {
            return 'home_haunt';
        }

        return 'free';
    }

    private static function get_address($row) {
        $parts = [];
        if (!empty($row->street)) $parts[] = $row->street;
        if (!empty($row->city))   $parts[] = $row->city;
        if (!empty($row->region)) $parts[] = $row->region;
        if (!empty($row->zip))    $parts[] = $row->zip;
        return implode(', ', $parts);
    }

    private static function get_haunt_status($post_id) {
        $status = get_post_meta($post_id, 'haunt_status', true);
        if (!$status) {
            $month  = date('n');
            $status = ($month >= 9 && $month <= 11) ? 'open' : 'seasonal';
        }
        return $status;
    }

    public static function get_counts($region = '') {
        $listings = self::get_listings($region);
        $counts = [
            'total'        => count($listings),
            'featured'     => 0,
            'professional' => 0,
            'home_haunt'   => 0,
            'free'         => 0,
        ];
        foreach ($listings as $listing) {
            if (isset($counts[$listing['type']])) {
                $counts[$listing['type']]++;
            }
        }
        return $counts;
    }

    /**
     * AJAX handler for events layer.
     */
    public static function ajax_get_events() {
        check_ajax_referer('oha_map_nonce', 'nonce');
        $region = isset($_POST['region']) ? sanitize_text_field($_POST['region']) : '';
        wp_send_json_success(self::get_events($region));
    }

    /**
     * Fetch upcoming events from Events for GeoDirectory.
     *
     * Joins: wp_posts + wp_geodir_gd_event_detail + wp_geodir_event_schedule
     * Returns only events whose end_date is today or future.
     *
     * @param string $region Optional region slug filter
     * @return array
     */
    public static function get_events($region = '') {
        global $wpdb;

        // Check events plugin table exists
        $event_detail_tbl = $wpdb->prefix . 'geodir_gd_event_detail';
        $schedule_tbl     = $wpdb->prefix . 'geodir_event_schedule';

        $table_check = $wpdb->get_var("SHOW TABLES LIKE '{$event_detail_tbl}'");
        if (!$table_check) {
            return [];
        }

        $today = date('Y-m-d');

        $sql = "SELECT DISTINCT
                    p.ID,
                    p.post_title,
                    ed.latitude,
                    ed.longitude,
                    ed.street,
                    ed.city,
                    ed.region   AS state_region,
                    ed.zip,
                    s.start_date,
                    s.end_date,
                    s.start_time,
                    s.end_time,
                    s.all_day
                FROM {$wpdb->posts} p
                INNER JOIN {$event_detail_tbl} ed ON ed.post_id = p.ID
                INNER JOIN {$schedule_tbl} s ON s.event_id = p.ID
                WHERE p.post_type   = 'gd_event'
                  AND p.post_status = 'publish'
                  AND ed.latitude   IS NOT NULL AND ed.latitude  != ''
                  AND ed.longitude  IS NOT NULL AND ed.longitude != ''
                  AND s.end_date   >= %s
                ORDER BY s.start_date ASC
                LIMIT 500";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $today));

        if (empty($rows)) {
            return [];
        }

        $region_data = $region ? OHA_Map_Regions::get_region($region) : null;
        $events      = [];

        foreach ($rows as $row) {
            $lat = floatval($row->latitude);
            $lng = floatval($row->longitude);

            // Region bounds filter
            if ($region_data && isset($region_data['bounds'])) {
                $b = $region_data['bounds'];
                if ($lat < $b['south'] || $lat > $b['north'] ||
                    $lng < $b['west']  || $lng > $b['east']) {
                    continue;
                }
            }

            $address_parts = array_filter([
                $row->street,
                $row->city,
                $row->state_region,
                $row->zip,
            ]);

            $events[] = [
                'id'         => $row->ID,
                'title'      => $row->post_title,
                'lat'        => $lat,
                'lng'        => $lng,
                'start_date' => $row->start_date,
                'end_date'   => $row->end_date,
                'start_time' => $row->start_time,
                'end_time'   => $row->end_time,
                'all_day'    => (bool) $row->all_day,
                'address'    => implode(', ', $address_parts),
                'permalink'  => get_permalink($row->ID),
                'thumbnail'  => get_the_post_thumbnail_url((int) $row->ID, 'thumbnail') ?: '',
                'region'     => OHA_Map_Regions::get_region_for_point($lat, $lng),
            ];
        }

        return apply_filters('oha_map_events', $events, $region);
    }

    // ---------------------------------------------------------------
    // Ohio County GeoJSON (ArcGIS-backed, 7-day cache)
    // ---------------------------------------------------------------

    /**
     * AJAX: return all 88 Ohio county polygons with region + color properties injected.
     * Raw geometry is cached for 7 days; region/color assignments are applied fresh each call.
     */
    public static function ajax_get_ohio_counties() {
        check_ajax_referer('oha_map_nonce', 'nonce');

        $raw = self::get_ohio_county_geojson();
        if (is_wp_error($raw)) {
            wp_send_json_error($raw->get_error_message());
            return;
        }

        $county_map   = OHA_Map_Regions::get_county_region_map();
        $color_option = get_option('oha_map_region_colors', []);
        $defaults     = OHA_Map_Regions::get_default_region_colors();

        $features = $raw['features'];
        foreach ($features as &$feature) {
            $name   = strtolower(trim($feature['properties']['name'] ?? ''));
            $region = $county_map[$name] ?? 'central';

            $colors        = $color_option[$region] ?? [];
            $fill_color    = !empty($colors['fill'])    ? $colors['fill']    : ($defaults[$region]['fill']    ?? '#c0392b');
            $outline_color = !empty($colors['outline']) ? $colors['outline'] : ($defaults[$region]['outline'] ?? '#e74c3c');

            $feature['properties']['region']       = $region;
            $feature['properties']['fillColor']    = $fill_color;
            $feature['properties']['outlineColor'] = $outline_color;
        }
        unset($feature);

        wp_send_json_success([
            'counties' => [
                'type'     => 'FeatureCollection',
                'features' => $features,
            ],
            'outline' => self::get_ohio_state_geojson(),
        ]);
    }

    /** AJAX: clear county GeoJSON cache (admin only). */
    public static function ajax_clear_county_cache() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        check_ajax_referer('oha_map_nonce', 'nonce');
        delete_transient('oha_ohio_counties_v1');
        delete_transient('oha_ohio_state_v1');
        wp_send_json_success('County and state outline cache cleared.');
    }

    /**
     * Fetch Ohio state boundary from Census TIGER, cached for 7 days.
     * Returns a GeoJSON Feature on success, null on failure (JS keeps the hand-drawn fallback).
     */
    private static function get_ohio_state_geojson() {
        $cache_key = 'oha_ohio_state_v1';
        $cached    = get_transient($cache_key);
        if ($cached !== false) {
            return $cached ?: null;
        }

        $url = 'https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/State_County/MapServer/0/query'
             . '?where=NAME%3D%27Ohio%27&outFields=NAME&f=geojson&outSR=4326&maxAllowableOffset=0.005&geometryPrecision=4';

        $response = wp_remote_get($url, ['timeout' => 30, 'sslverify' => true]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            set_transient($cache_key, '', HOUR_IN_SECONDS);
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['features'][0]['geometry'])) {
            set_transient($cache_key, '', HOUR_IN_SECONDS);
            return null;
        }

        $feature = [
            'type'       => 'Feature',
            'properties' => [],
            'geometry'   => $data['features'][0]['geometry'],
        ];
        set_transient($cache_key, $feature, 7 * DAY_IN_SECONDS);
        return $feature;
    }

    /**
     * Fetch raw county GeoJSON from Ohio state ArcGIS portal, caching for 7 days.
     * Returns an associative array (FeatureCollection) on success, WP_Error on failure.
     */
    private static function get_ohio_county_geojson() {
        $cache_key = 'oha_ohio_counties_v1';
        $cached    = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $url = 'https://maps.ohio.gov/arcgis/rest/services/Hosted/Ohio_County_Boundaries_Land_Only/FeatureServer/0/query'
             . '?where=1%3D1&outFields=name&f=geojson&outSR=4326&maxAllowableOffset=0.005&geometryPrecision=4';

        $response = wp_remote_get($url, [
            'timeout'   => 30,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('arcgis_error', "ArcGIS returned HTTP {$code}");
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!$data || empty($data['features'])) {
            return new WP_Error('arcgis_empty', 'County GeoJSON returned no features');
        }

        // Store only geometry + name to keep the cached blob small
        $clean = [];
        foreach ($data['features'] as $f) {
            $clean[] = [
                'type'       => 'Feature',
                'geometry'   => $f['geometry'],
                'properties' => ['name' => $f['properties']['name'] ?? ''],
            ];
        }

        $geojson = ['type' => 'FeatureCollection', 'features' => $clean];
        set_transient($cache_key, $geojson, 7 * DAY_IN_SECONDS);
        return $geojson;
    }
}

add_action('init', ['OHA_Map_Data', 'init']);
