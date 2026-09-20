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
     * County-to-region assignment for all 88 Ohio counties.
     * Merges the hardcoded default with any admin overrides stored in oha_county_region_map.
     *
     * @return array county_name_lowercase => region_slug
     */
    public static function get_county_region_map() {
        $default = [
            'adams'      => 'southwest',
            'allen'      => 'northwest',
            'ashland'    => 'northeast',
            'ashtabula'  => 'northeast',
            'athens'     => 'southeast',
            'auglaize'   => 'northwest',
            'belmont'    => 'southeast',
            'brown'      => 'southwest',
            'butler'     => 'southwest',
            'carroll'    => 'northeast',
            'champaign'  => 'central',
            'clark'      => 'southwest',
            'clermont'   => 'southwest',
            'clinton'    => 'southwest',
            'columbiana' => 'northeast',
            'coshocton'  => 'southeast',
            'crawford'   => 'central',
            'cuyahoga'   => 'northeast',
            'darke'      => 'southwest',
            'defiance'   => 'northwest',
            'delaware'   => 'central',
            'erie'       => 'northwest',
            'fairfield'  => 'central',
            'fayette'    => 'southwest',
            'franklin'   => 'central',
            'fulton'     => 'northwest',
            'gallia'     => 'southeast',
            'geauga'     => 'northeast',
            'greene'     => 'southwest',
            'guernsey'   => 'southeast',
            'hamilton'   => 'southwest',
            'hancock'    => 'northwest',
            'hardin'     => 'northwest',
            'harrison'   => 'southeast',
            'henry'      => 'northwest',
            'highland'   => 'southwest',
            'hocking'    => 'southeast',
            'holmes'     => 'northeast',
            'huron'      => 'northwest',
            'jackson'    => 'southwest',
            'jefferson'  => 'southeast',
            'knox'       => 'central',
            'lake'       => 'northeast',
            'lawrence'   => 'southwest',
            'licking'    => 'central',
            'logan'      => 'central',
            'lorain'     => 'northeast',
            'lucas'      => 'northwest',
            'madison'    => 'central',
            'mahoning'   => 'northeast',
            'marion'     => 'central',
            'medina'     => 'northeast',
            'meigs'      => 'southeast',
            'mercer'     => 'northwest',
            'miami'      => 'southwest',
            'monroe'     => 'southeast',
            'montgomery' => 'southwest',
            'morgan'     => 'southeast',
            'morrow'     => 'central',
            'muskingum'  => 'southeast',
            'noble'      => 'southeast',
            'ottawa'     => 'northwest',
            'paulding'   => 'northwest',
            'perry'      => 'southeast',
            'pickaway'   => 'central',
            'pike'       => 'southwest',
            'portage'    => 'northeast',
            'preble'     => 'southwest',
            'putnam'     => 'northwest',
            'richland'   => 'central',
            'ross'       => 'southwest',
            'sandusky'   => 'northwest',
            'scioto'     => 'southwest',
            'seneca'     => 'northwest',
            'shelby'     => 'southwest',
            'stark'      => 'northeast',
            'summit'     => 'northeast',
            'trumbull'   => 'northeast',
            'tuscarawas' => 'southeast',
            'union'      => 'central',
            'van wert'   => 'northwest',
            'vinton'     => 'southeast',
            'warren'     => 'southwest',
            'washington' => 'southeast',
            'wayne'      => 'northeast',
            'williams'   => 'northwest',
            'wood'       => 'northwest',
            'wyandot'    => 'northwest',
        ];
        $saved = get_option('oha_county_region_map', []);
        return array_merge($default, $saved);
    }

    /**
     * Approximate geographic centroids for all 88 Ohio counties.
     * Used by get_region_for_point() so haunt filtering uses the same
     * county-based data as the visual county fills.
     */
    private static function get_county_centroids() {
        return [
            'adams'      => [38.83, -83.47],
            'allen'      => [40.77, -84.11],
            'ashland'    => [40.86, -82.31],
            'ashtabula'  => [41.78, -80.78],
            'athens'     => [39.33, -82.10],
            'auglaize'   => [40.55, -84.23],
            'belmont'    => [40.01, -81.00],
            'brown'      => [38.94, -83.88],
            'butler'     => [39.43, -84.56],
            'carroll'    => [40.57, -81.08],
            'champaign'  => [40.14, -83.76],
            'clark'      => [39.93, -83.79],
            'clermont'   => [39.07, -84.18],
            'clinton'    => [39.41, -83.84],
            'columbiana' => [40.77, -80.79],
            'coshocton'  => [40.27, -81.84],
            'crawford'   => [40.84, -82.97],
            'cuyahoga'   => [41.45, -81.70],
            'darke'      => [40.13, -84.62],
            'defiance'   => [41.30, -84.35],
            'delaware'   => [40.30, -83.00],
            'erie'       => [41.43, -82.72],
            'fairfield'  => [39.75, -82.63],
            'fayette'    => [39.55, -83.43],
            'franklin'   => [39.96, -82.99],
            'fulton'     => [41.60, -84.13],
            'gallia'     => [38.83, -82.30],
            'geauga'     => [41.49, -81.18],
            'greene'     => [39.67, -83.88],
            'guernsey'   => [40.11, -81.49],
            'hamilton'   => [39.20, -84.56],
            'hancock'    => [41.00, -83.67],
            'hardin'     => [40.66, -83.61],
            'harrison'   => [40.29, -80.99],
            'henry'      => [41.33, -84.07],
            'highland'   => [39.18, -83.59],
            'hocking'    => [39.52, -82.42],
            'holmes'     => [40.55, -81.97],
            'huron'      => [41.15, -82.60],
            'jackson'    => [38.97, -82.64],
            'jefferson'  => [40.37, -80.74],
            'knox'       => [40.38, -82.38],
            'lake'       => [41.72, -81.42],
            'lawrence'   => [38.53, -82.55],
            'licking'    => [40.10, -82.50],
            'logan'      => [40.38, -83.72],
            'lorain'     => [41.37, -82.16],
            'lucas'      => [41.65, -83.67],
            'madison'    => [39.89, -83.42],
            'mahoning'   => [41.03, -80.76],
            'marion'     => [40.60, -83.14],
            'medina'     => [41.12, -81.87],
            'meigs'      => [39.08, -82.02],
            'mercer'     => [40.53, -84.63],
            'miami'      => [40.03, -84.23],
            'monroe'     => [39.69, -81.10],
            'montgomery' => [39.76, -84.28],
            'morgan'     => [39.62, -81.86],
            'morrow'     => [40.54, -82.79],
            'muskingum'  => [39.98, -81.98],
            'noble'      => [39.74, -81.46],
            'ottawa'     => [41.53, -83.14],
            'paulding'   => [41.12, -84.56],
            'perry'      => [39.74, -82.24],
            'pickaway'   => [39.68, -82.79],
            'pike'       => [39.07, -83.07],
            'portage'    => [41.22, -81.20],
            'preble'     => [39.73, -84.66],
            'putnam'     => [41.01, -84.13],
            'richland'   => [40.77, -82.52],
            'ross'       => [39.34, -83.05],
            'sandusky'   => [41.35, -83.11],
            'scioto'     => [38.81, -82.96],
            'seneca'     => [41.12, -83.12],
            'shelby'     => [40.34, -84.20],
            'stark'      => [40.80, -81.37],
            'summit'     => [41.12, -81.52],
            'trumbull'   => [41.32, -80.77],
            'tuscarawas' => [40.44, -81.46],
            'union'      => [40.28, -83.34],
            'van wert'   => [40.86, -84.58],
            'vinton'     => [39.25, -82.41],
            'warren'     => [39.43, -84.19],
            'washington' => [39.46, -81.51],
            'wayne'      => [40.83, -81.88],
            'williams'   => [41.55, -84.59],
            'wood'       => [41.36, -83.63],
            'wyandot'    => [40.84, -83.31],
        ];
    }

    /**
     * Determine which region a point belongs to.
     * Uses nearest-county-centroid lookup so haunt filtering always
     * matches the county fill colors shown on the map.
     * Falls back to nearest region centroid for out-of-state haunts.
     */
    public static function get_region_for_point($lat, $lng) {
        $centroids  = self::get_county_centroids();
        $county_map = self::get_county_region_map();

        $min_dist = PHP_INT_MAX;
        $nearest  = null;
        foreach ($centroids as $county => $center) {
            $d = ($lat - $center[0]) ** 2 + ($lng - $center[1]) ** 2;
            if ($d < $min_dist) {
                $min_dist = $d;
                $nearest  = $county;
            }
        }

        if ($nearest !== null && isset($county_map[$nearest])) {
            return $county_map[$nearest];
        }

        // Fallback: nearest region centroid (truly out-of-state haunts)
        $regions  = self::get_regions();
        $min_dist = PHP_INT_MAX;
        $fallback = 'central';
        foreach ($regions as $slug => $region) {
            if (empty($region['center'])) continue;
            $d = ($lat - $region['center'][0]) ** 2 + ($lng - $region['center'][1]) ** 2;
            if ($d < $min_dist) {
                $min_dist = $d;
                $fallback = $slug;
            }
        }
        return $fallback;
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
            // SW – Indiana / Ohio River (near Harrison OH / Lawrenceburg IN)
            [-84.820, 38.770],
            // Indiana border north (nearly straight ~84.82°W)
            [-84.820, 39.100],
            [-84.820, 39.500],
            [-84.820, 40.000],
            [-84.820, 40.369],
            [-84.820, 40.800],
            [-84.820, 41.250],
            [-84.806, 41.696],  // Indiana/Ohio corner (meets Michigan border)
            // Michigan–Ohio border going east (rises toward Lake Erie)
            [-84.437, 41.696],  // jog east at Williams/Fulton county line
            [-83.880, 41.720],  // approaching Toledo
            [-83.454, 41.726],  // Toledo / Maumee Bay area
            // Lake Erie coast heading east-northeast
            [-83.200, 41.700],
            [-82.900, 41.710],
            [-82.710, 41.440],  // Sandusky Bay dip (Sandusky/Cedar Point area)
            [-82.500, 41.440],  // south shore of Sandusky Bay
            [-82.390, 41.550],  // east end of Sandusky Bay
            [-82.200, 41.530],  // near Lorain
            [-81.900, 41.510],  // near Rocky River / Cleveland west
            [-81.700, 41.490],  // Cleveland
            [-81.400, 41.570],  // east of Cleveland
            [-81.100, 41.720],  // Mentor / Painesville area
            [-80.860, 41.860],  // Ashtabula area
            [-80.700, 42.020],
            [-80.565, 42.200],
            [-80.519, 42.327],  // NE – Pennsylvania / Lake Erie
            // Pennsylvania border south (straight line ~80.519°W)
            [-80.519, 41.977],
            [-80.519, 41.500],
            [-80.519, 41.000],
            [-80.519, 40.638],  // PA / WV / OH tripoint at Ohio River
            // Ohio River heading SW – WV border
            [-80.600, 40.420],
            [-80.730, 40.200],
            [-80.870, 40.010],
            [-81.020, 39.760],
            [-81.200, 39.550],
            [-81.430, 39.380],
            [-81.640, 39.240],
            [-81.900, 39.040],  // near Gallipolis OH
            [-82.060, 38.870],
            [-82.190, 38.760],
            [-82.390, 38.560],  // near Point Pleasant WV
            [-82.590, 38.420],  // OH / WV / KY tripoint (Huntington WV / Ironton OH)
            // Ohio River heading west – KY border
            [-82.720, 38.510],
            [-82.960, 38.680],
            [-83.080, 38.730],  // near Portsmouth OH
            [-83.380, 38.710],
            [-83.680, 38.730],
            [-83.990, 38.830],
            [-84.290, 39.000],
            [-84.530, 39.090],  // near Cincinnati
            [-84.700, 38.980],
            [-84.820, 38.770],  // back to SW corner
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
