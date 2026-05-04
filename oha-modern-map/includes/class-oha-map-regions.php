<?php
/**
 * OHA Map Regions Handler
 * Manages region definitions and point-in-polygon calculations
 */

if (!defined('ABSPATH')) exit;

class OHA_Map_Regions {

    public static function get_regions() {
        $regions = get_option('oha_map_regions', null);
        // If no saved option, or option still has old 7-region slugs (east/west),
        // ignore stale DB data and return the new 5-region defaults.
        if (!$regions || isset($regions['east']) || isset($regions['west'])) {
            return apply_filters('oha_map_regions', self::get_default_regions());
        }
        return apply_filters('oha_map_regions', $regions);
    }

    private static function get_default_regions() {
        return [
            'northwest' => [
                'name'    => 'Northwest Ohio',
                'slug'    => 'northwest',
                // County-accurate polygon tracing county boundary grid lines.
                // Counties: Williams, Defiance, Fulton, Henry, Lucas, Ottawa, Sandusky,
                //           Erie, Paulding, Putnam, Hancock, Wood, Wyandot, Seneca,
                //           Huron, Van Wert, Allen, Hardin, Mercer, Auglaize (20)
                'polygon' => [
                    [41.760, -84.820],
                    [41.970, -84.820],
                    [41.970, -82.860],
                    [41.500, -82.520],
                    [41.120, -82.350],
                    [40.990, -82.840],
                    [40.700, -82.840],
                    [40.700, -83.150],
                    [40.370, -83.150],
                    [40.370, -83.660],
                    [40.700, -83.660],
                    [40.700, -84.240],
                    [40.360, -84.240],
                    [40.360, -84.820],
                ],
                'center' => [41.25, -83.60],
                'bounds' => [[40.360, -84.820], [41.970, -82.350]],
                'zoom'   => 8,
            ],
            'northeast' => [
                'name'    => 'Northeast Ohio',
                'slug'    => 'northeast',
                // Counties: Ashtabula, Lake, Geauga, Cuyahoga, Lorain, Medina, Summit,
                //           Portage, Trumbull, Mahoning, Columbiana, Wayne, Stark,
                //           Holmes, Ashland, Carroll (16)
                'polygon' => [
                    [41.970, -82.860],
                    [41.970, -80.520],
                    [39.720, -80.520],
                    [39.720, -81.090],
                    [40.280, -81.090],
                    [40.280, -81.650],
                    [40.550, -81.650],
                    [40.550, -82.200],
                    [40.990, -82.200],
                    [40.990, -82.840],
                    [41.120, -82.840],
                    [41.120, -82.350],
                    [41.500, -82.520],
                ],
                'center' => [41.00, -81.30],
                'bounds' => [[39.720, -82.840], [41.970, -80.520]],
                'zoom'   => 8,
            ],
            'central' => [
                'name'    => 'Central Ohio',
                'slug'    => 'central',
                // Counties: Richland, Crawford, Marion, Morrow, Knox, Delaware, Union,
                //           Logan, Champaign, Franklin, Licking, Madison, Fairfield,
                //           Pickaway (14)
                'polygon' => [
                    [40.990, -83.150],
                    [40.990, -82.840],
                    [40.550, -82.840],
                    [40.550, -82.200],
                    [40.280, -82.200],
                    [40.280, -81.650],
                    [39.720, -81.650],
                    [39.420, -81.650],
                    [39.420, -82.850],
                    [39.720, -82.850],
                    [39.720, -83.500],
                    [40.370, -83.500],
                    [40.370, -83.660],
                    [40.700, -83.660],
                    [40.700, -83.150],
                    [40.990, -83.150],
                ],
                'center' => [40.20, -82.90],
                'bounds' => [[39.420, -83.660], [40.990, -81.650]],
                'zoom'   => 8,
            ],
            'southwest' => [
                'name'    => 'Southwest Ohio',
                'slug'    => 'southwest',
                // Counties: Shelby, Darke, Miami, Montgomery, Clark, Greene, Preble,
                //           Butler, Hamilton, Clermont, Warren, Clinton, Highland, Ross,
                //           Fayette, Brown, Adams, Scioto, Lawrence, Pike, Jackson (21)
                'polygon' => [
                    [40.700, -84.820],
                    [40.700, -84.240],
                    [40.360, -84.240],
                    [40.360, -83.660],
                    [40.370, -83.500],
                    [39.720, -83.500],
                    [39.720, -82.850],
                    [39.420, -82.850],
                    [38.380, -82.850],
                    [38.380, -84.820],
                    [40.700, -84.820],
                ],
                'center' => [39.30, -83.90],
                'bounds' => [[38.380, -84.820], [40.700, -82.850]],
                'zoom'   => 8,
            ],
            'southeast' => [
                'name'    => 'Southeast Ohio',
                'slug'    => 'southeast',
                // Counties: Coshocton, Tuscarawas, Harrison, Jefferson, Belmont, Noble,
                //           Guernsey, Morgan, Muskingum, Perry, Vinton, Hocking, Meigs,
                //           Gallia, Athens, Monroe, Washington (17)
                'polygon' => [
                    [40.280, -82.850],
                    [40.280, -81.650],
                    [39.720, -81.650],
                    [39.420, -81.650],
                    [39.420, -82.850],
                    [38.380, -82.850],
                    [38.380, -80.520],
                    [39.720, -80.520],
                    [40.280, -80.520],
                    [40.280, -81.090],
                    [40.280, -82.850],
                ],
                'center' => [39.50, -81.80],
                'bounds' => [[38.380, -82.850], [40.280, -80.520]],
                'zoom'   => 8,
            ],
        ];
    }

    /**
     * Determine which region a point belongs to using ray-casting.
     * Falls back to nearest region centroid for out-of-state haunts.
     */
    public static function get_region_for_point($lat, $lng) {
        $regions = self::get_regions();
        foreach ($regions as $slug => $region) {
            $polygon = isset($region['polygon']) ? $region['polygon'] : null;
            if ($polygon && self::point_in_polygon($lat, $lng, $polygon)) {
                return $slug;
            }
        }

        // Fallback: nearest region centroid (handles out-of-state haunts)
        $min_dist = PHP_INT_MAX;
        $nearest  = false;
        foreach ($regions as $slug => $region) {
            if (empty($region['center'])) continue;
            $d = pow($lat - $region['center'][0], 2) + pow($lng - $region['center'][1], 2);
            if ($d < $min_dist) {
                $min_dist = $d;
                $nearest  = $slug;
            }
        }
        return $nearest;
    }

    /**
     * Ray-casting point-in-polygon check.
     * Polygon vertices are [lat, lng] pairs.
     * Casts an eastward ray (increasing longitude) from the test point.
     */
    private static function point_in_polygon($lat, $lng, $polygon) {
        $n      = count($polygon);
        $inside = false;
        $j      = $n - 1;
        for ($i = 0; $i < $n; $i++) {
            $lat_i = $polygon[$i][0];
            $lng_i = $polygon[$i][1];
            $lat_j = $polygon[$j][0];
            $lng_j = $polygon[$j][1];
            if (($lat_i > $lat) !== ($lat_j > $lat)) {
                $cross_lng = $lng_i + ($lat - $lat_i) * ($lng_j - $lng_i) / ($lat_j - $lat_i);
                if ($lng < $cross_lng) {
                    $inside = !$inside;
                }
            }
            $j = $i;
        }
        return $inside;
    }

    public static function get_region($slug) {
        $regions = self::get_regions();
        return isset($regions[$slug]) ? $regions[$slug] : false;
    }

    public static function get_region_slugs() {
        return array_keys(self::get_regions());
    }

    public static function get_region_names() {
        $regions = self::get_regions();
        $names   = [];
        foreach ($regions as $slug => $region) {
            $names[$slug] = $region['name'];
        }
        return $names;
    }

    public static function save_regions($regions) {
        return update_option('oha_map_regions', $regions);
    }

    public static function get_ohio_bounds() {
        return [
            'north'  => 42.0,
            'south'  => 38.4,
            'east'   => -80.5,
            'west'   => -84.9,
            'center' => [40.2, -82.7],
            'zoom'   => 7,
        ];
    }

    /**
     * Convert regions to a GeoJSON FeatureCollection for MapLibre.
     *
     * PHP regions store polygon vertices as [lat, lng].
     * GeoJSON requires [lng, lat] -- we swap here.
     * Region fill/outline colors come from oha_map_region_colors option.
     * Bounds are converted from [[south, west], [north, east]] to [[west, south], [east, north]].
     *
     * @return string JSON-encoded GeoJSON FeatureCollection
     */
    public static function regions_to_geojson() {
        $regions      = self::get_regions();
        $color_option = get_option('oha_map_region_colors', []);
        $defaults     = self::get_default_region_colors();
        $features     = [];

        foreach ($regions as $slug => $region) {
            if (empty($region['polygon'])) continue;

            // Swap [lat, lng] -> [lng, lat] for GeoJSON
            $coords = array_map(function($point) {
                return [(float) $point[1], (float) $point[0]];
            }, $region['polygon']);

            // Close the ring (GeoJSON requires first === last)
            $coords[] = $coords[0];

            $colors        = isset($color_option[$slug]) ? $color_option[$slug] : [];
            $fill_color    = !empty($colors['fill'])    ? $colors['fill']    : ($defaults[$slug]['fill']    ?? '#ff6b35');
            $outline_color = !empty($colors['outline']) ? $colors['outline'] : ($defaults[$slug]['outline'] ?? '#ff8c5a');

            // Centroid approximation (average of vertices, excluding the closing duplicate)
            $ring = array_slice($coords, 0, -1);
            $cx   = array_sum(array_column($ring, 0)) / count($ring);
            $cy   = array_sum(array_column($ring, 1)) / count($ring);

            // Convert bounds from [[south_lat, west_lng], [north_lat, east_lng]]
            // to [[west_lng, south_lat], [east_lng, north_lat]] for MapLibre fitBounds
            $bounds = null;
            if (!empty($region['bounds']) && count($region['bounds']) === 2) {
                $bounds = [
                    [(float) $region['bounds'][0][1], (float) $region['bounds'][0][0]],
                    [(float) $region['bounds'][1][1], (float) $region['bounds'][1][0]],
                ];
            }

            $features[] = [
                'type'       => 'Feature',
                'properties' => [
                    'slug'         => $slug,
                    'name'         => $region['name'],
                    'fillColor'    => $fill_color,
                    'outlineColor' => $outline_color,
                    'centerLng'    => isset($region['center'][1]) ? (float) $region['center'][1] : $cx,
                    'centerLat'    => isset($region['center'][0]) ? (float) $region['center'][0] : $cy,
                    'zoom'         => isset($region['zoom']) ? (int) $region['zoom'] : 8,
                    'bounds'       => $bounds,
                ],
                'geometry' => [
                    'type'        => 'Polygon',
                    'coordinates' => [$coords],
                ],
            ];
        }

        return wp_json_encode([
            'type'     => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * Default spooky Halloween color palette per region (5 regions).
     *
     * @return array slug => ['fill' => hex, 'outline' => hex]
     */
    public static function get_default_region_colors() {
        return [
            'northwest' => ['fill' => '#ff6b35', 'outline' => '#ff8c5a'],
            'northeast' => ['fill' => '#9d4edd', 'outline' => '#b46ef0'],
            'central'   => ['fill' => '#c0392b', 'outline' => '#e74c3c'],
            'southwest' => ['fill' => '#8e44ad', 'outline' => '#9b59b6'],
            'southeast' => ['fill' => '#27ae60', 'outline' => '#2ecc71'],
        ];
    }

    /**
     * Return a GeoJSON Feature for the Ohio state boundary outline.
     * Used to draw a distinct highlight around Ohio on the map background.
     * Coordinates are [lng, lat] for GeoJSON.
     * Ohio River waypoints are approximate; border is decorative (~0.1 deg accuracy).
     *
     * @return string JSON-encoded GeoJSON Feature
     */
    public static function get_ohio_outline_geojson() {
        $coords = [
            // Start at SW corner (Indiana/Ohio/Kentucky area)
            [-84.820, 38.403],
            // Indiana border -- north
            [-84.820, 40.360],
            [-84.820, 41.760],
            // Michigan border (jogs at Williams/Fulton)
            [-84.437, 41.760],
            [-84.437, 41.970],
            // Lake Erie coastline east (simplified straight line to PA border area)
            [-83.120, 41.970],
            [-82.520, 41.500],
            [-82.180, 41.650],
            [-81.480, 41.970],
            [-80.520, 41.970],
            // Pennsylvania border south
            [-80.520, 40.640],
            [-80.520, 39.720],
            // West Virginia / Ohio River (simplified waypoints)
            [-80.520, 39.400],
            [-80.860, 39.090],
            [-81.090, 38.720],
            [-81.350, 38.590],
            [-81.650, 38.610],
            [-82.000, 38.700],
            [-82.200, 38.620],
            [-82.580, 38.420],
            [-82.850, 38.380],
            // Continue Ohio River west
            [-83.270, 38.620],
            [-83.500, 38.690],
            [-83.860, 38.760],
            [-84.240, 38.800],
            [-84.550, 38.990],
            [-84.820, 38.790],
            // Indiana border south back to start
            [-84.820, 38.403],
        ];
        $coords[] = $coords[0]; // close ring
        return wp_json_encode([
            'type'       => 'Feature',
            'properties' => [],
            'geometry'   => [
                'type'        => 'Polygon',
                'coordinates' => [$coords],
            ],
        ]);
    }
}
