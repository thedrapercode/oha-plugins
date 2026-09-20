/**
 * OHA Modern Map v2.0.0
 * MapLibre GL JS frontend — WebGL rendering, region fills, events layer,
 * Google Maps-style bottom sheet, full mobile/PWA support.
 */
/* global maplibregl, ohaMapSettings, jQuery */
(function ($) {
    'use strict';

    const OHAMap = {

        // ---------------------------------------------------------------
        // State
        // ---------------------------------------------------------------
        maps:        {},   // maplibregl.Map instances, keyed by mapId
        allHaunts:   {},   // raw listings from AJAX, keyed by mapId
        allEvents:   {},   // raw events from AJAX, keyed by mapId
        activeFilters: {
            region:        '',
            types:         ['featured', 'professional', 'home_haunt', 'free'],
            fearLevel:     '',
            accessible:    false,
            admissionType: '',
            charityType:   '',
        },
        layerVisible: {
            haunts: true,
            events: true,
        },
        iconsLoaded: {},   // keyed by mapId, bool
        sidebarOpen: {},   // keyed by mapId, bool
        clusterEnabled: {}, // keyed by mapId, bool
        userLocation:    {}, // keyed by mapId, {lat, lng} or null
        nearMeActive:    {}, // keyed by mapId, bool
        favoritesActive: {}, // keyed by mapId, bool
        searchQuery:     {}, // keyed by mapId, string
        userLocationMarker: {}, // keyed by mapId, MapLibre Marker instance

        // ---------------------------------------------------------------
        // Init
        // ---------------------------------------------------------------
        init() {
            if (typeof maplibregl === 'undefined') {
                console.error('OHA Map: MapLibre GL JS not loaded.');
                return;
            }

            const settings = ohaMapSettings;

            // Set initial layer visibility from admin setting
            this.layerVisible.events = !!(settings.showEventsByDefault);

            // Read initial region from URL param (for region selector links)
            const urlParams = new URLSearchParams(window.location.search);
            const urlRegion = urlParams.get('region');
            if (urlRegion) {
                this.activeFilters.region = urlRegion;
            }

            $('.oha-map-wrapper').each((i, el) => {
                const $wrapper = $(el);
                const mapId    = $wrapper.attr('id');

                this.allHaunts[mapId] = [];
                this.allEvents[mapId] = [];
                this.iconsLoaded[mapId] = false;
                this.sidebarOpen[mapId] = false;
                this.clusterEnabled[mapId] = true;
                this.userLocation[mapId]    = null;
                this.nearMeActive[mapId]    = false;
                this.favoritesActive[mapId] = false;
                this.searchQuery[mapId]     = '';
                this.userLocationMarker[mapId] = null;

                // Override region from wrapper data attribute
                const wrapperRegion = $wrapper.data('region');
                if (wrapperRegion) {
                    this.activeFilters.region = wrapperRegion;
                }
                if (urlRegion) {
                    // URL param overrides shortcode attr
                    this.activeFilters.region = urlRegion;
                    // Highlight the corresponding pill
                    $wrapper.find('.oha-pill').removeClass('active');
                    $wrapper.find(`.oha-pill[data-region="${urlRegion}"]`).addClass('active');
                }

                this.initMap($wrapper, mapId);
                this.initControls($wrapper, mapId);
                this.initBottomSheet($wrapper);
            });
        },

        // ---------------------------------------------------------------
        // Map Initialization
        // ---------------------------------------------------------------
        initMap($wrapper, mapId) {
            const settings    = ohaMapSettings;
            const regionSlug  = this.activeFilters.region;
            const regionData  = this.getRegionData(regionSlug);
            const elementId   = `${mapId}-map`;
            const tileStyle   = settings.tileStyle || 'https://tiles.openfreemap.org/styles/dark';

            // Use fitBounds so Ohio always fills the viewport regardless of screen size.
            // If a region is pre-selected via shortcode attr, fit to that region instead.
            const ohioBounds    = [[-84.82, 38.40], [-80.52, 42.40]];
            const initialBounds = (regionSlug && regionData.bounds) ? regionData.bounds : ohioBounds;

            const map = new maplibregl.Map({
                container:          elementId,
                style:              tileStyle,
                bounds:             initialBounds,
                fitBoundsOptions:   { padding: regionSlug ? 40 : 20, duration: 0 },
                attributionControl: false,
                pitchWithRotate:    false,
            });

            // Minimal attribution
            map.addControl(new maplibregl.AttributionControl({ compact: true }), 'bottom-right');

            this.maps[mapId] = map;

            map.on('load', () => {
                this.addRegionLayers(map, mapId);
                this.loadCounties($wrapper, mapId);
                this.loadIcons(map, mapId).then(() => {
                    this.addHauntLayers(map, mapId);
                    this.addEventLayers(map, mapId);
                    this.addMapInteractions(map, mapId, $wrapper);
                    this.loadHaunts($wrapper, mapId);
                    if (settings.eventsEnabled) {
                        this.loadEvents($wrapper, mapId);
                    }
                    // Apply initial layer visibility for events
                    this.setLayerVisibility(map, 'events', this.layerVisible.events);
                    this.updateLayerButtonState($wrapper);
                    // Update sidebar list whenever the map pans or zooms
                    map.on('moveend', () => {
                        this.updateSidebarList(mapId, $wrapper);
                    });
                });
            });

            map.on('error', (e) => {
                console.warn('OHA Map tile error:', e.error);
            });
        },

        // ---------------------------------------------------------------
        // Icon Loading
        // ---------------------------------------------------------------
        loadIcons(map, mapId) {
            const settings    = ohaMapSettings;
            const pluginUrl   = settings.pluginUrl;
            const customIcons = (settings.icons && settings.icons.custom_icons) ? settings.icons.custom_icons : {};

            const iconDefs = [
                { id: 'icon-featured',     url: customIcons.featured     || `${pluginUrl}assets/images/icons/icon-featured.svg` },
                { id: 'icon-professional', url: customIcons.professional  || `${pluginUrl}assets/images/icons/icon-professional.svg` },
                { id: 'icon-home_haunt',   url: customIcons.home_haunt   || `${pluginUrl}assets/images/icons/icon-home-haunt.svg` },
                { id: 'icon-free',         url: customIcons.free         || `${pluginUrl}assets/images/icons/icon-free.svg` },
                { id: 'icon-event',        url: customIcons.event        || `${pluginUrl}assets/images/icons/icon-event.svg` },
            ];

            const promises = iconDefs.map(def => {
                // MapLibre GL JS v4 uses Promise-based loadImage (callback API removed)
                return map.loadImage(def.url)
                    .then(response => {
                        // v4 returns {data, cacheControl, expires}; extract the image
                        const img = (response && response.data) ? response.data : response;
                        if (!map.hasImage(def.id)) {
                            map.addImage(def.id, img, { sdf: false });
                        }
                    })
                    .catch(() => {
                        // SVG decode failure or network error — use colored circle fallback
                        const fallback = this.createFallbackIconImage(def.id);
                        if (fallback && !map.hasImage(def.id)) {
                            map.addImage(def.id, fallback);
                        }
                    });
            });

            return Promise.all(promises).then(() => {
                this.iconsLoaded[mapId] = true;
            });
        },

        createFallbackIconImage(iconId) {
            const colorMap = {
                'icon-featured':     '#ffd700',
                'icon-professional': '#ff6b35',
                'icon-home_haunt':   '#9d4edd',
                'icon-free':         '#888888',
                'icon-event':        '#00b4d8',
            };
            const color = colorMap[iconId] || '#ff6b35';
            const size  = 48;
            const canvas = document.createElement('canvas');
            canvas.width  = size;
            canvas.height = size;
            const ctx = canvas.getContext('2d');

            ctx.beginPath();
            ctx.arc(size / 2, size / 2, size / 2 - 2, 0, Math.PI * 2);
            ctx.fillStyle = color;
            ctx.fill();
            ctx.strokeStyle = 'rgba(0,0,0,0.4)';
            ctx.lineWidth = 2;
            ctx.stroke();

            const imageData = ctx.getImageData(0, 0, size, size);
            return { width: size, height: size, data: imageData.data };
        },

        // ---------------------------------------------------------------
        // Region Layers
        // ---------------------------------------------------------------
        addRegionLayers(map, mapId) {
            const geojson = ohaMapSettings.regionsGeoJSON;
            if (!geojson) return;

            let data;
            try {
                data = typeof geojson === 'string' ? JSON.parse(geojson) : geojson;
            } catch (e) {
                console.error('OHA Map: invalid regionsGeoJSON', e);
                return;
            }

            // Region centroid source — used only for region name labels
            map.addSource('ohio-regions', {
                type: 'geojson',
                data: data,
            });

            // County fill source — populated via AJAX after map loads (see loadCounties)
            map.addSource('ohio-counties', {
                type: 'geojson',
                data: { type: 'FeatureCollection', features: [] },
            });

            // Subtle always-on county fill — fades out when zoomed in
            map.addLayer({
                id:     'county-fills',
                type:   'fill',
                source: 'ohio-counties',
                paint: {
                    'fill-color':   ['get', 'fillColor'],
                    'fill-opacity': ['interpolate', ['linear'], ['zoom'],
                        7, 0.06,
                        9, 0.03,
                        10, 0,
                    ],
                },
            });

            // Highlighted county fill when a region is selected
            map.addLayer({
                id:     'county-fills-selected',
                type:   'fill',
                source: 'ohio-counties',
                filter: ['==', ['get', 'region'], ''],
                paint: {
                    'fill-color':   ['get', 'fillColor'],
                    'fill-opacity': ['interpolate', ['linear'], ['zoom'],
                        8,  0.22,
                        10, 0.10,
                        11, 0,
                    ],
                },
            });

            // Hover highlight — all counties in the hovered region
            map.addLayer({
                id:     'county-fills-hover',
                type:   'fill',
                source: 'ohio-counties',
                filter: ['==', ['get', 'region'], ''],
                paint: {
                    'fill-color':   ['get', 'fillColor'],
                    'fill-opacity': ['interpolate', ['linear'], ['zoom'],
                        7, 0.12,
                        10, 0,
                    ],
                },
            });

            // County divider outlines
            map.addLayer({
                id:     'county-outlines',
                type:   'line',
                source: 'ohio-counties',
                paint: {
                    'line-color':   ['get', 'outlineColor'],
                    'line-width':   0.8,
                    'line-opacity': ['interpolate', ['linear'], ['zoom'],
                        7,  0.45,
                        9,  0.25,
                        10, 0,
                    ],
                },
            });

            // Region name labels — placed at polygon centroids from ohio-regions source
            map.addLayer({
                id:   'region-labels',
                type: 'symbol',
                source: 'ohio-regions',
                layout: {
                    'text-field':            ['get', 'name'],
                    'text-font':             ['Open Sans Bold', 'Arial Unicode MS Bold'],
                    'text-size':             13,
                    'text-letter-spacing':   0.05,
                    'text-transform':        'uppercase',
                    'symbol-placement':      'point',
                    'text-allow-overlap':    false,
                    'text-ignore-placement': false,
                },
                paint: {
                    'text-color':      '#ffffff',
                    'text-opacity':    ['interpolate', ['linear'], ['zoom'],
                        6, 0.0,
                        7, 0.7,
                        9, 0.5,
                        10, 0,
                    ],
                    'text-halo-color': 'rgba(0,0,0,0.6)',
                    'text-halo-width': 2,
                },
                maxzoom: 10,
            });

            // County hover events — highlight all counties in the hovered region
            let hoveredRegion = null;
            map.on('mousemove', 'county-fills', (e) => {
                const slug = e.features[0].properties.region;
                if (hoveredRegion !== slug) {
                    hoveredRegion = slug;
                    if (this.activeFilters.region !== slug) {
                        map.setFilter('county-fills-hover', ['==', ['get', 'region'], slug]);
                    }
                    map.getCanvas().style.cursor = 'pointer';
                }
            });
            map.on('mouseleave', 'county-fills', () => {
                hoveredRegion = null;
                map.setFilter('county-fills-hover', ['==', ['get', 'region'], '']);
                map.getCanvas().style.cursor = '';
            });

            // Click on county fill (not on a haunt marker) → select that region
            map.on('click', 'county-fills', (e) => {
                if (!e.features || !e.features.length) return;
                const slug = e.features[0].properties.region;
                const hauntHits = map.queryRenderedFeatures(e.point, { layers: ['haunts-unclustered'] });
                if (!hauntHits.length) {
                    const $wrapper = $('#' + mapId);
                    this.selectRegion(mapId, slug, $wrapper);
                }
            });

            // Ohio state outline
            const ohioOutlineData = ohaMapSettings.ohioOutline;
            if (ohioOutlineData) {
                try {
                    const parsed = typeof ohioOutlineData === 'string'
                        ? JSON.parse(ohioOutlineData)
                        : ohioOutlineData;
                    map.addSource('ohio-outline', { type: 'geojson', data: parsed });
                    map.addLayer({
                        id:     'ohio-state-outline',
                        type:   'line',
                        source: 'ohio-outline',
                        paint: {
                            'line-color':   '#ad3119',
                            'line-width':   ['interpolate', ['linear'], ['zoom'],
                                5, 1.5,
                                8, 2.5,
                                12, 1.5,
                            ],
                            'line-opacity': ['interpolate', ['linear'], ['zoom'],
                                5,  0.6,
                                8,  0.9,
                                14, 0.4,
                            ],
                        },
                    });
                } catch (e) {
                    console.warn('OHA Map: failed to parse Ohio outline GeoJSON', e);
                }
            }
        },

        selectRegion(mapId, slug, $wrapper) {
            this.activeFilters.region = slug;
            const map = this.maps[mapId];
            if (!map) return;

            // Update county fill highlight
            if (map.getLayer('county-fills-selected')) {
                map.setFilter('county-fills-selected', ['==', ['get', 'region'], slug || '']);
            }
            if (map.getLayer('county-fills-hover')) {
                map.setFilter('county-fills-hover',    ['==', ['get', 'region'], '']);
            }

            // Zoom to region using fitBounds for viewport-aware fitting
            const pad = window.innerWidth < 768 ? 20 : 60;
            if (slug) {
                const regionData = this.getRegionData(slug);
                if (regionData.bounds) {
                    // bounds = [[west_lng, south_lat], [east_lng, north_lat]]
                    map.fitBounds(regionData.bounds, { padding: pad, speed: 1.2 });
                } else {
                    map.flyTo({
                        center: [regionData.centerLng || -82.7, regionData.centerLat || 40.2],
                        zoom:   regionData.zoom || 9,
                        speed:  1.2,
                    });
                }
            } else {
                // Reset to all Ohio
                map.fitBounds([[-84.82, 38.40], [-80.52, 42.40]], { padding: 20, speed: 1.2 });
            }

            // Sync pill UI (both desktop and mobile sheet)
            $wrapper.find('.oha-pill').removeClass('active');
            $wrapper.find(`.oha-pill[data-region="${slug}"]`).addClass('active');

            this.updateMobileFilterBadge($wrapper);

            // Refresh marker + list data
            this.applyFilters(mapId, $wrapper);
        },

        // ---------------------------------------------------------------
        // Haunt Layers
        // ---------------------------------------------------------------
        addHauntLayers(map, mapId) {
            const clusterSettings = ohaMapSettings.clustering || {};
            const clusterEnabled  = clusterSettings.enabled !== false;
            this.clusterEnabled[mapId] = clusterEnabled;
            const clusterRadius   = parseInt(clusterSettings.radius || 50, 10);
            const clusterMaxZoom  = parseInt(clusterSettings.disable_at_zoom || clusterSettings.max_zoom || 14, 10);

            map.addSource('haunts', {
                type:           'geojson',
                data:           { type: 'FeatureCollection', features: [] },
                cluster:        clusterEnabled,
                clusterMaxZoom: clusterMaxZoom,
                clusterRadius:  clusterRadius,
            });

            if (clusterEnabled) {
                // Cluster circles
                map.addLayer({
                    id:     'haunts-clusters',
                    type:   'circle',
                    source: 'haunts',
                    filter: ['has', 'point_count'],
                    paint: {
                        'circle-color': [
                            'step', ['get', 'point_count'],
                            '#ad3119', 10, '#8a2514', 50, '#680400',
                        ],
                        'circle-radius': [
                            'step', ['get', 'point_count'],
                            18, 10, 24, 50, 32,
                        ],
                        'circle-stroke-color': 'rgba(173,49,25,0.4)',
                        'circle-stroke-width': 4,
                    },
                });

                // Cluster count text
                map.addLayer({
                    id:     'haunts-cluster-count',
                    type:   'symbol',
                    source: 'haunts',
                    filter: ['has', 'point_count'],
                    layout: {
                        'text-field':  '{point_count_abbreviated}',
                        'text-font':   ['Open Sans Bold', 'Arial Unicode MS Bold'],
                        'text-size':   13,
                    },
                    paint: { 'text-color': '#ffffff' },
                });
            }

            // Hover ring — white circle stroke shown when a sidebar card is hovered
            map.addLayer({
                id:     'haunts-hover-ring',
                type:   'circle',
                source: 'haunts',
                filter: ['all',
                    clusterEnabled ? ['!', ['has', 'point_count']] : ['has', 'type'],
                    ['==', ['get', 'id'], 0],
                ],
                paint: {
                    'circle-radius':         20,
                    'circle-color':          'rgba(255,255,255,0)',
                    'circle-stroke-color':   '#ffffff',
                    'circle-stroke-width':   2.5,
                    'circle-stroke-opacity': 0.85,
                },
            });

            // Individual haunt markers
            map.addLayer({
                id:     'haunts-unclustered',
                type:   'symbol',
                source: 'haunts',
                filter: clusterEnabled ? ['!', ['has', 'point_count']] : ['has', 'type'],
                layout: {
                    'icon-image':           ['get', 'markerIcon'],
                    'icon-size':            ['get', 'iconSize'],
                    'icon-allow-overlap':   true,
                    'icon-anchor':          'bottom',
                    'symbol-sort-key':      ['get', 'sortKey'],
                    'text-field':           '',
                },
            });
        },

        // ---------------------------------------------------------------
        // Event Layers
        // ---------------------------------------------------------------
        addEventLayers(map, mapId) {
            map.addSource('events', {
                type:           'geojson',
                data:           { type: 'FeatureCollection', features: [] },
                cluster:        true,
                clusterMaxZoom: 14,
                clusterRadius:  40,
            });

            map.addLayer({
                id:     'events-clusters',
                type:   'circle',
                source: 'events',
                filter: ['has', 'point_count'],
                paint: {
                    'circle-color':        '#00b4d8',
                    'circle-radius':       ['step', ['get', 'point_count'], 16, 10, 22, 30, 28],
                    'circle-stroke-color': 'rgba(0,180,216,0.35)',
                    'circle-stroke-width': 3,
                },
            });

            map.addLayer({
                id:     'events-cluster-count',
                type:   'symbol',
                source: 'events',
                filter: ['has', 'point_count'],
                layout: {
                    'text-field': '{point_count_abbreviated}',
                    'text-font':  ['Open Sans Bold', 'Arial Unicode MS Bold'],
                    'text-size':  12,
                },
                paint: { 'text-color': '#ffffff' },
            });

            map.addLayer({
                id:     'events-unclustered',
                type:   'symbol',
                source: 'events',
                filter: ['!', ['has', 'point_count']],
                layout: {
                    'icon-image':         'icon-event',
                    'icon-size':          0.55,
                    'icon-allow-overlap': true,
                    'icon-anchor':        'bottom',
                },
            });
        },

        // ---------------------------------------------------------------
        // Map Interactions
        // ---------------------------------------------------------------
        addMapInteractions(map, mapId, $wrapper) {
            // Click on county fill → toggle region selection (skip if clicking on a haunt/cluster pin)
            map.on('click', 'county-fills', (e) => {
                if (!e.features.length) return;
                const markerHits = map.queryRenderedFeatures(e.point, { layers: ['haunts-unclustered', 'haunts-clusters'] });
                if (markerHits.length) return;
                const slug = e.features[0].properties.region;
                const current = this.activeFilters.region;
                this.selectRegion(mapId, current === slug ? '' : slug, $wrapper);
            });

            // Click on haunt cluster → zoom
            map.on('click', 'haunts-clusters', (e) => {
                const features = map.queryRenderedFeatures(e.point, { layers: ['haunts-clusters'] });
                if (!features.length) return;
                map.easeTo({
                    center: features[0].geometry.coordinates,
                    zoom:   map.getZoom() + 2,
                });
            });

            // Click on individual haunt marker → bottom sheet
            map.on('click', 'haunts-unclustered', (e) => {
                if (!e.features.length) return;
                const props = e.features[0].properties;
                this.showBottomSheet($wrapper, props, 'haunt');
            });

            // Click on event cluster → zoom
            map.on('click', 'events-clusters', (e) => {
                const features = map.queryRenderedFeatures(e.point, { layers: ['events-clusters'] });
                if (!features.length) return;
                map.easeTo({
                    center: features[0].geometry.coordinates,
                    zoom:   map.getZoom() + 2,
                });
            });

            // Click on event marker → bottom sheet
            map.on('click', 'events-unclustered', (e) => {
                if (!e.features.length) return;
                const props = e.features[0].properties;
                this.showBottomSheet($wrapper, props, 'event');
            });

            // Cursor pointer on interactive layers
            const interactiveLayers = [
                'haunts-unclustered', 'haunts-clusters',
                'events-unclustered', 'events-clusters',
            ];
            interactiveLayers.forEach(layer => {
                map.on('mouseenter', layer, () => { map.getCanvas().style.cursor = 'pointer'; });
                map.on('mouseleave', layer, () => { map.getCanvas().style.cursor = ''; });
            });

            // Pin hover → highlight matching sidebar card
            map.on('mousemove', 'haunts-unclustered', (e) => {
                if (!this.sidebarOpen[mapId] || !e.features.length) return;
                const id = e.features[0].properties.id;
                $wrapper.find('.oha-panel-card').removeClass('is-pin-hovered');
                $wrapper.find(`.oha-panel-card[data-id="${id}"]`).addClass('is-pin-hovered');
            });
            map.on('mouseleave', 'haunts-unclustered', () => {
                $wrapper.find('.oha-panel-card').removeClass('is-pin-hovered');
            });

            // Close bottom sheet on map click (outside marker)
            map.on('click', (e) => {
                const features = map.queryRenderedFeatures(e.point, { layers: interactiveLayers });
                if (!features.length) {
                    this.hideBottomSheet($wrapper);
                }
            });
        },

        // ---------------------------------------------------------------
        // Data Loading
        // ---------------------------------------------------------------
        loadHaunts($wrapper, mapId) {
            $wrapper.find('.oha-map-loading').fadeIn(200);

            $.ajax({
                url:    ohaMapSettings.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'oha_get_map_listings',
                    nonce:  ohaMapSettings.nonce,
                    region: '',
                    type:   '',
                },
            }).done((response) => {
                if (response.success && Array.isArray(response.data)) {
                    this.allHaunts[mapId] = response.data;
                    this.applyFilters(mapId, $wrapper);
                }
            }).always(() => {
                $wrapper.find('.oha-map-loading').fadeOut(300);
            });
        },

        loadEvents($wrapper, mapId) {
            $.ajax({
                url:    ohaMapSettings.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'oha_get_map_events',
                    nonce:  ohaMapSettings.nonce,
                    region: '',
                },
            }).done((response) => {
                if (response.success && Array.isArray(response.data)) {
                    this.allEvents[mapId] = response.data;
                    this.updateEventsSource(mapId);
                }
            });
        },

        loadCounties($wrapper, mapId) {
            $.ajax({
                url:    ohaMapSettings.ajaxUrl,
                method: 'GET',
                data: {
                    action: 'oha_get_ohio_counties',
                    nonce:  ohaMapSettings.nonce,
                },
            }).done((response) => {
                if (!response.success || !response.data) {
                    console.warn('OHA Map: county data unavailable', response);
                    return;
                }
                const map = this.maps[mapId];
                if (!map) return;

                // Populate county fills
                const countiesData = response.data.counties;
                if (countiesData && map.getSource('ohio-counties')) {
                    map.getSource('ohio-counties').setData(countiesData);
                }

                // Replace hand-drawn Ohio outline with Census TIGER boundary (if returned)
                const outlineData = response.data.outline;
                if (outlineData && map.getSource('ohio-outline')) {
                    map.getSource('ohio-outline').setData(outlineData);
                }

                // Re-apply selected region highlight now that county data is loaded
                if (this.activeFilters.region && map.getLayer('county-fills-selected')) {
                    map.setFilter('county-fills-selected', ['==', ['get', 'region'], this.activeFilters.region]);
                }
            }).fail((xhr, status, error) => {
                console.warn('OHA Map: county AJAX failed', error);
            });
        },

        // ---------------------------------------------------------------
        // Filter & Render
        // ---------------------------------------------------------------
        applyFilters(mapId, $wrapper) {
            const region     = this.activeFilters.region;
            const types      = this.activeFilters.types;
            const allHaunts  = this.allHaunts[mapId] || [];
            const query      = (this.searchQuery[mapId] || '').toLowerCase().trim();
            const favsOnly   = this.favoritesActive[mapId];
            const favIds     = favsOnly ? this.getFavorites() : null;

            const fearLevel      = this.activeFilters.fearLevel;
            const accessibleOnly = this.activeFilters.accessible;
            const admissionType  = this.activeFilters.admissionType;
            const charityType    = this.activeFilters.charityType;

            const filtered = allHaunts.filter(listing => {
                if (region && listing.region !== region) return false;
                if (!types.includes(listing.type)) return false;
                if (favsOnly && favIds && !favIds.includes(listing.id)) return false;
                if (query) {
                    const titleMatch = listing.title.toLowerCase().includes(query);
                    const cityMatch  = listing.city && listing.city.toLowerCase().includes(query);
                    if (!titleMatch && !cityMatch) return false;
                }
                if (fearLevel && listing.fear_level !== fearLevel) return false;
                if (accessibleOnly) {
                    const wc = (listing.wheelchair_accessible || '').toLowerCase();
                    if (wc !== 'yes' && wc !== 'true' && wc !== '1') return false;
                }
                if (admissionType) {
                    const at = (listing.admission_type || '').toLowerCase();
                    if (admissionType === 'free' && !at.includes('free')) return false;
                    if (admissionType === 'paid' && !at.includes('paid')) return false;
                }
                if (charityType && listing.charity_type !== charityType) return false;
                return true;
            });

            this.updateHauntsSource(mapId, filtered);
            this.renderListView($wrapper, filtered);
            this.updateCounter($wrapper, filtered.length);
            this.updateMobileFilterBadge($wrapper);
            this.updateSidebarList(mapId, $wrapper);
        },

        updateHauntsSource(mapId, listings) {
            const map = this.maps[mapId];
            if (!map || !map.getSource('haunts')) return;

            const icons    = ohaMapSettings.icons || {};
            const features = listings.map(listing => {
                const markerIcon = `icon-${listing.type}`;
                const sortKey    = listing.type === 'featured' ? 100
                                 : listing.type === 'professional' ? 50
                                 : listing.type === 'home_haunt'   ? 30 : 10;
                const iconSize   = listing.type === 'featured'     ? 0.72
                                 : listing.type === 'professional' ? 0.56
                                 : listing.type === 'home_haunt'   ? 0.50 : 0.46;

                return {
                    type: 'Feature',
                    geometry: {
                        type:        'Point',
                        coordinates: [listing.lng, listing.lat],
                    },
                    properties: {
                        id:          listing.id,
                        title:       listing.title,
                        type:        listing.type,
                        markerIcon:  markerIcon,
                        iconSize:    iconSize,
                        sortKey:     sortKey,
                        address:     listing.address     || '',
                        phone:       listing.phone       || '',
                        email:       listing.email       || '',
                        website:     listing.website     || '',
                        permalink:   listing.permalink   || '',
                        thumbnail:   listing.thumbnail   || '',
                        status:         listing.status         || '',
                        featured:       listing.featured       ? 'yes' : 'no',
                        region:         listing.region         || '',
                        gd_icon_url:    listing.gd_icon_url    || '',
                        fear_level:     listing.fear_level     || '',
                        haunt_features: Array.isArray(listing.haunt_features) ? listing.haunt_features.join(',') : '',
                        wheelchair:     listing.wheelchair_accessible || '',
                        indoor_outdoor: listing.indoor_outdoor  || '',
                        admission_type: listing.admission_type  || '',
                        ticket_url:     listing.ticket_url      || '',
                        charity_type:   listing.charity_type    || '',
                        charity_name:   listing.charity_name    || '',
                        donation_url:   listing.donation_url    || '',
                    },
                };
            });

            map.getSource('haunts').setData({
                type:     'FeatureCollection',
                features: features,
            });
        },

        updateEventsSource(mapId) {
            const map    = this.maps[mapId];
            const events = this.allEvents[mapId] || [];
            if (!map || !map.getSource('events')) return;

            const features = events.map(ev => ({
                type: 'Feature',
                geometry: {
                    type:        'Point',
                    coordinates: [ev.lng, ev.lat],
                },
                properties: {
                    id:         ev.id,
                    title:      ev.title,
                    start_date: ev.start_date  || '',
                    end_date:   ev.end_date    || '',
                    start_time: ev.start_time  || '',
                    end_time:   ev.end_time    || '',
                    all_day:    ev.all_day     ? 'yes' : 'no',
                    address:    ev.address     || '',
                    permalink:  ev.permalink   || '',
                    thumbnail:  ev.thumbnail   || '',
                    region:     ev.region      || '',
                },
            }));

            map.getSource('events').setData({
                type:     'FeatureCollection',
                features: features,
            });
        },

        setLayerVisibility(map, layer, visible) {
            const vis = visible ? 'visible' : 'none';
            const layerIds = layer === 'haunts'
                ? ['haunts-clusters', 'haunts-cluster-count', 'haunts-unclustered']
                : ['events-clusters', 'events-cluster-count', 'events-unclustered'];

            layerIds.forEach(id => {
                if (map.getLayer(id)) {
                    map.setLayoutProperty(id, 'visibility', vis);
                }
            });
        },

        // ---------------------------------------------------------------
        // List View
        // ---------------------------------------------------------------
        renderListView($wrapper, listings) {
            const $container = $wrapper.find('.oha-list-container');
            if (!$container.length) return;

            $container.empty();

            if (!listings.length) {
                $container.html('<div class="oha-list-empty"><p>No haunts found for this selection.</p></div>');
                return;
            }

            // Sort: featured first, then by title
            const sorted = [...listings].sort((a, b) => {
                if (a.featured && !b.featured) return -1;
                if (!a.featured && b.featured) return 1;
                return a.title.localeCompare(b.title);
            });

            sorted.forEach(listing => {
                const card = this.buildListingCard(listing);
                $container.append(card);
            });
        },

        buildListingCard(listing) {
            const typeLabel = {
                featured:     'Featured',
                professional: 'Professional',
                home_haunt:   'Home Haunt',
                free:         'Listing',
            }[listing.type] || listing.type;

            const thumb = listing.thumbnail
                ? `<div class="oha-card-thumb" style="background-image:url('${this.escHtml(listing.thumbnail)}')"></div>`
                : '';

            return `
            <div class="oha-listing-card oha-type-${this.escHtml(listing.type)}">
                ${thumb}
                <div class="oha-card-body">
                    <div class="oha-card-badges">
                        <span class="oha-badge oha-badge-${this.escHtml(listing.type)}">${this.escHtml(typeLabel)}</span>
                        ${listing.status ? `<span class="oha-badge oha-badge-status oha-badge-${this.escHtml(listing.status)}">${this.escHtml(listing.status)}</span>` : ''}
                    </div>
                    <h3 class="oha-card-title">${this.escHtml(listing.title)}</h3>
                    ${listing.address ? `<p class="oha-card-address">${this.escHtml(listing.address)}</p>` : ''}
                    <a href="${this.escHtml(listing.permalink)}" class="oha-btn-primary oha-card-btn">View Details</a>
                </div>
            </div>`;
        },

        // ---------------------------------------------------------------
        // Bottom Sheet (Google Maps-style slide-up card)
        // ---------------------------------------------------------------
        showBottomSheet($wrapper, props, cardType) {
            const $sheet  = $wrapper.find('.oha-bottom-sheet');
            const $content = $sheet.find('.oha-bottom-sheet-content');

            $content.html(
                cardType === 'event'
                    ? this.buildEventCardHTML(props)
                    : this.buildHauntCardHTML(props)
            );

            $sheet.addClass('active');
            $('body').addClass('oha-sheet-open');
        },

        hideBottomSheet($wrapper) {
            $wrapper.find('.oha-bottom-sheet').removeClass('active');
            $('body').removeClass('oha-sheet-open');
        },

        buildHauntCardHTML(props) {
            const cardFields = ohaMapSettings.cardFields || {};
            const typeLabel  = {
                featured:     'Featured',
                professional: 'Professional',
                home_haunt:   'Home Haunt',
                free:         'Listing',
            }[props.type] || (props.type || 'Listing');

            // Find upcoming events for this haunt location (by proximity)
            const nearbyEvents = this.findEventsNearCoords(props);

            let html = '<div class="oha-popup-card">';

            // Thumbnail
            if (cardFields.thumbnail !== false && props.thumbnail) {
                html += `<div class="oha-popup-thumb" style="background-image:url('${this.escHtml(props.thumbnail)}')"></div>`;
            }

            html += '<div class="oha-popup-body">';

            // Badges row
            const badgesHtml = [];
            if (cardFields.type_badge !== false && props.type) {
                badgesHtml.push(`<span class="oha-badge oha-badge-${this.escHtml(props.type)}">${this.escHtml(typeLabel)}</span>`);
            }
            if (cardFields.status_badge !== false && props.status) {
                badgesHtml.push(`<span class="oha-badge oha-badge-status oha-badge-${this.escHtml(props.status)}">${this.escHtml(props.status.charAt(0).toUpperCase() + props.status.slice(1))}</span>`);
            }
            if (props.fear_level) {
                const fc = this.getFearLevelClass(props.fear_level);
                badgesHtml.push(`<span class="oha-badge oha-badge-fear oha-fear-${fc}">${this.escHtml(props.fear_level)}</span>`);
            }
            if (props.wheelchair && props.wheelchair.toLowerCase().match(/^(yes|true|1)$/)) {
                badgesHtml.push(`<span class="oha-badge oha-badge-accessible">Accessible</span>`);
            }
            if (props.indoor_outdoor) {
                badgesHtml.push(`<span class="oha-badge oha-badge-venue">${this.escHtml(props.indoor_outdoor)}</span>`);
            }
            if (props.admission_type) {
                const ac = props.admission_type.toLowerCase().includes('free') ? 'free' : 'paid';
                badgesHtml.push(`<span class="oha-badge oha-badge-admission oha-admission-badge-${ac}">${this.escHtml(props.admission_type)}</span>`);
            }
            if (props.charity_type) {
                const cc = props.charity_type === 'Charity Haunt' ? 'charity-haunt' : 'supports-charity';
                badgesHtml.push(`<span class="oha-badge oha-badge-charity oha-badge-${cc}">${this.escHtml(props.charity_type)}</span>`);
            }
            if (badgesHtml.length) {
                html += `<div class="oha-popup-badges">${badgesHtml.join('')}</div>`;
            }
            // Feature tags
            if (props.haunt_features) {
                const feats = props.haunt_features.split(',').map(f => f.trim()).filter(f => f);
                if (feats.length) {
                    html += `<div class="oha-feature-tags">${feats.map(f => `<span class="oha-feature-tag">${this.escHtml(f)}</span>`).join('')}</div>`;
                }
            }

            // Title + fav button row
            html += `<div class="oha-popup-title-row">`;
            html += `<h3 class="oha-popup-title">${this.escHtml(props.title)}</h3>`;
            html += `<button class="oha-fav-btn${this.isFavorite(props.id) ? ' is-fav' : ''}" data-id="${parseInt(props.id, 10)}" title="Save to favorites"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg></button>`;
            html += `</div>`;

            // CTA buttons — placed before info so they're always visible even if card is partially off-screen
            if (cardFields.view_details !== false && props.permalink) {
                html += `<a href="${this.escHtml(props.permalink)}" class="oha-btn-primary oha-popup-cta">View Details</a>`;
            }
            if (props.ticket_url) {
                const ticketHref = props.ticket_url.startsWith('http') ? props.ticket_url : `https://${props.ticket_url}`;
                html += `<a href="${this.escHtml(ticketHref)}" class="oha-btn-tickets oha-popup-cta-tickets" target="_blank" rel="noopener">Buy Tickets</a>`;
            }
            if (props.donation_url) {
                const donateHref = props.donation_url.startsWith('http') ? props.donation_url : `https://${props.donation_url}`;
                html += `<a href="${this.escHtml(donateHref)}" class="oha-btn-donate oha-popup-cta-donate" target="_blank" rel="noopener">Donate</a>`;
            }

            // Info grid
            html += '<div class="oha-popup-info">';
            if (cardFields.address !== false && props.address) {
                html += `<div class="oha-info-row"><svg class="oha-info-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg><span>${this.escHtml(props.address)}</span></div>`;
            }
            if (cardFields.phone !== false && props.phone) {
                html += `<div class="oha-info-row"><svg class="oha-info-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg><a href="tel:${this.escHtml(props.phone)}">${this.escHtml(props.phone)}</a></div>`;
            }
            if (cardFields.website !== false && props.website) {
                const website = props.website.startsWith('http') ? props.website : `https://${props.website}`;
                html += `<div class="oha-info-row"><svg class="oha-info-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg><a href="${this.escHtml(website)}" target="_blank" rel="noopener">${this.escHtml(props.website.replace(/^https?:\/\//, ''))}</a></div>`;
            }
            if (cardFields.email !== false && props.email) {
                html += `<div class="oha-info-row"><svg class="oha-info-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg><a href="mailto:${this.escHtml(props.email)}">${this.escHtml(props.email)}</a></div>`;
            }
            if (props.charity_name) {
                html += `<div class="oha-info-row oha-info-charity"><svg class="oha-info-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg><span>Supporting: ${this.escHtml(props.charity_name)}</span></div>`;
            }
            html += '</div>'; // .oha-popup-info

            // Upcoming events section
            if (cardFields.upcoming_events !== false && nearbyEvents.length) {
                html += '<div class="oha-popup-events"><h4 class="oha-popup-events-title">Upcoming Events</h4><ul class="oha-popup-events-list">';
                nearbyEvents.slice(0, 3).forEach(ev => {
                    const dateStr = this.formatEventDate(ev.start_date, ev.end_date, ev.start_time, ev.all_day);
                    html += `<li><a href="${this.escHtml(ev.permalink)}" target="_blank">${this.escHtml(ev.title)}</a><span class="oha-event-date">${dateStr}</span></li>`;
                });
                html += '</ul></div>';
            }

            html += '</div></div>'; // .oha-popup-body .oha-popup-card
            return html;
        },

        buildEventCardHTML(props) {
            const dateStr = this.formatEventDate(props.start_date, props.end_date, props.start_time, props.all_day);
            let html = '<div class="oha-popup-card oha-popup-event-card">';

            if (props.thumbnail) {
                html += `<div class="oha-popup-thumb" style="background-image:url('${this.escHtml(props.thumbnail)}')"></div>`;
            }

            html += '<div class="oha-popup-body">';
            html += `<div class="oha-popup-badges"><span class="oha-badge oha-badge-event">Event</span></div>`;
            html += `<h3 class="oha-popup-title">${this.escHtml(props.title)}</h3>`;
            html += '<div class="oha-popup-info">';
            if (dateStr) {
                html += `<div class="oha-info-row"><svg class="oha-info-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5C3.89 3 3.01 3.9 3.01 5L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg><span>${dateStr}</span></div>`;
            }
            if (props.address) {
                html += `<div class="oha-info-row"><svg class="oha-info-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg><span>${this.escHtml(props.address)}</span></div>`;
            }
            html += '</div>';
            if (props.permalink) {
                html += `<a href="${this.escHtml(props.permalink)}" class="oha-btn-primary oha-popup-cta">View Event</a>`;
            }
            html += '</div></div>';
            return html;
        },

        // ---------------------------------------------------------------
        // Controls
        // ---------------------------------------------------------------
        initControls($wrapper, mapId) {
            const self = this;

            // Region pills
            $wrapper.on('click', '.oha-pill', function () {
                const slug = $(this).data('region') || '';
                self.selectRegion(mapId, slug, $wrapper);
            });

            // Type checkboxes — unified handler: reads only from the container that changed,
            // then syncs the mirror set (desktop controls ↔ mobile filter sheet)
            $wrapper.on('change', '.oha-type-toggles input[type="checkbox"]', function () {
                const inMobileSheet = $(this).closest('.oha-mob-filter-sheet').length > 0;
                const types = [];
                const readSel = inMobileSheet
                    ? '.oha-mob-filter-sheet .oha-type-toggles input:checked'
                    : '.oha-map-controls .oha-type-toggles input:checked';
                $wrapper.find(readSel).each(function () { types.push($(this).val()); });
                // Sync the other set so state stays consistent
                const syncSel = inMobileSheet
                    ? '.oha-map-controls .oha-type-toggles input'
                    : '.oha-mob-filter-sheet .oha-type-toggles input';
                $wrapper.find(syncSel).each(function () {
                    $(this).prop('checked', types.includes($(this).val()));
                });
                self.activeFilters.types = types;
                self.applyFilters(mapId, $wrapper);
            });

            // Fear level pills — synced between desktop and mobile sheet
            $wrapper.on('click', '.oha-fear-pill', function () {
                const fear = $(this).data('fear') || '';
                $wrapper.find('.oha-fear-pill').removeClass('active');
                $wrapper.find(`.oha-fear-pill[data-fear="${fear}"]`).addClass('active');
                self.activeFilters.fearLevel = fear;
                self.applyFilters(mapId, $wrapper);
            });

            // Wheelchair accessible toggle — synced between desktop and mobile sheet
            $wrapper.on('change', '.oha-accessible-filter', function () {
                const checked = $(this).is(':checked');
                $wrapper.find('.oha-accessible-filter').prop('checked', checked);
                self.activeFilters.accessible = checked;
                self.applyFilters(mapId, $wrapper);
            });

            // Admission type pills — synced between desktop and mobile sheet
            $wrapper.on('click', '.oha-admission-pill', function () {
                const admission = $(this).data('admission') || '';
                $wrapper.find('.oha-admission-pill').removeClass('active');
                $wrapper.find(`.oha-admission-pill[data-admission="${admission}"]`).addClass('active');
                self.activeFilters.admissionType = admission;
                self.applyFilters(mapId, $wrapper);
            });

            // Charity type pills — synced between desktop and mobile sheet
            $wrapper.on('click', '.oha-charity-pill', function () {
                const charity = $(this).data('charity') || '';
                $wrapper.find('.oha-charity-pill').removeClass('active');
                $wrapper.find(`.oha-charity-pill[data-charity="${charity}"]`).addClass('active');
                self.activeFilters.charityType = charity;
                self.applyFilters(mapId, $wrapper);
            });

            // Layer toggle buttons (Haunts / Events)
            $wrapper.on('click', '.oha-layer-btn', function () {
                const layer   = $(this).data('layer');
                const map     = self.maps[mapId];
                if (!map) return;
                const isActive = $(this).hasClass('active');
                $(this).toggleClass('active', !isActive);
                self.layerVisible[layer] = !isActive;
                self.setLayerVisibility(map, layer, !isActive);
            });

            // View toggle (map / list)
            $wrapper.on('click', '.oha-view-btn', function () {
                const view = $(this).data('view');
                $wrapper.find('.oha-view-btn').removeClass('active');
                $(this).addClass('active');

                // Desktop (>=900px): "List" opens sidebar, "Map" closes it
                if (window.innerWidth >= 900) {
                    if (view === 'list' && !self.sidebarOpen[mapId]) {
                        self.toggleSidebar(mapId, $wrapper);
                    } else if (view === 'map' && self.sidebarOpen[mapId]) {
                        self.toggleSidebar(mapId, $wrapper);
                    }
                    return;
                }

                // Mobile: traditional map/list view swap
                $wrapper.find('.oha-map-view, .oha-list-view').removeClass('active');
                $wrapper.find(`.oha-${view}-view`).addClass('active');
                if (view === 'map') {
                    // Force MapLibre to recalculate size after display:none → block
                    setTimeout(() => {
                        const map = self.maps[mapId];
                        if (map) map.resize();
                    }, 50);
                }
            });

            // Custom zoom controls
            $wrapper.on('click', '.oha-zoom-in',  () => { const m = this.maps[mapId]; if (m) m.zoomIn(); });
            $wrapper.on('click', '.oha-zoom-out', () => { const m = this.maps[mapId]; if (m) m.zoomOut(); });
            $wrapper.on('click', '.oha-reset-view', () => {
                this.selectRegion(mapId, '', $wrapper);
            });

            // Mobile filter pill — open/close sheet
            $wrapper.on('click', '.oha-mob-filter-pill', () => this.openMobileFilterSheet($wrapper));
            $wrapper.on('click', '.oha-mob-filter-done', () => this.closeMobileFilterSheet($wrapper));
            $wrapper.on('click', '.oha-mob-filter-overlay', () => this.closeMobileFilterSheet($wrapper));
            $wrapper.on('click', '.oha-mob-reset-pill', () => this.selectRegion(mapId, '', $wrapper));

            // Mobile region tabs — select region and close sheet
            $wrapper.on('click', '.oha-mob-region-tabs .oha-pill', function () {
                const slug = $(this).data('region') || '';
                self.selectRegion(mapId, slug, $wrapper);
                self.closeMobileFilterSheet($wrapper);
            });

            // Region info explore button
            $wrapper.on('click', '.oha-region-explore-btn', function () {
                const slug = $(this).data('region');
                self.hideRegionInfoPanel($wrapper);
                self.selectRegion(mapId, slug, $wrapper);
            });

            // Side panel toggle button
            $wrapper.on('click', '.oha-panel-toggle', function () {
                self.toggleSidebar(mapId, $wrapper);
            });

            // Sidebar card click → fly to pin + open bottom sheet
            $wrapper.on('click', '.oha-panel-card', function () {
                const id      = parseInt($(this).data('id'), 10);
                const listing = (self.allHaunts[mapId] || []).find(h => h.id == id);
                if (!listing) return;
                const map = self.maps[mapId];
                if (map) {
                    const targetZoom = Math.max(map.getZoom(), 10);
                    map.easeTo({ center: [listing.lng, listing.lat], zoom: targetZoom, speed: 1.2 });
                }
                self.showBottomSheet($wrapper, listing, 'haunt');
            });

            // Near Me button
            $wrapper.on('click', '.oha-near-me-btn', function () {
                self.getUserLocation(mapId, $wrapper);
            });

            // Favorites filter toggle
            $wrapper.on('click', '.oha-fav-filter-btn', function () {
                self.favoritesActive[mapId] = !self.favoritesActive[mapId];
                $(this).toggleClass('active', self.favoritesActive[mapId]);
                self.applyFilters(mapId, $wrapper);
            });

            // Heart/fav toggle on popup card or panel card
            $wrapper.on('click', '.oha-fav-btn, .oha-panel-fav-btn', function (e) {
                e.stopPropagation();
                const id = parseInt($(this).data('id'), 10);
                self.toggleFavorite(id);
                const isFav = self.isFavorite(id);
                $wrapper.find(`.oha-fav-btn[data-id="${id}"], .oha-panel-fav-btn[data-id="${id}"]`)
                    .toggleClass('is-fav', isFav);
                if (self.favoritesActive[mapId]) {
                    self.applyFilters(mapId, $wrapper);
                }
            });

            // Initialize search
            self.initSearch($wrapper, mapId);

            // Sidebar card hover → highlight map pin via hover ring
            $wrapper.on('mouseenter', '.oha-panel-card', function () {
                const id  = parseInt($(this).data('id'), 10);
                const map = self.maps[mapId];
                if (!map || !map.getLayer('haunts-hover-ring')) return;
                const ce = self.clusterEnabled[mapId] !== false;
                map.setFilter('haunts-hover-ring', ['all',
                    ce ? ['!', ['has', 'point_count']] : ['has', 'type'],
                    ['==', ['get', 'id'], id],
                ]);
            });
            $wrapper.on('mouseleave', '.oha-panel-card', function () {
                const map = self.maps[mapId];
                if (!map || !map.getLayer('haunts-hover-ring')) return;
                const ce = self.clusterEnabled[mapId] !== false;
                map.setFilter('haunts-hover-ring', ['all',
                    ce ? ['!', ['has', 'point_count']] : ['has', 'type'],
                    ['==', ['get', 'id'], 0],
                ]);
            });
        },

        // ---------------------------------------------------------------
        // Mobile Filter Sheet
        // ---------------------------------------------------------------
        openMobileFilterSheet($wrapper) {
            $wrapper.find('.oha-mob-filter-sheet').addClass('open');
            $wrapper.find('.oha-mob-filter-overlay').addClass('visible');
            $('body').addClass('oha-sheet-open');
        },

        closeMobileFilterSheet($wrapper) {
            $wrapper.find('.oha-mob-filter-sheet').removeClass('open');
            $wrapper.find('.oha-mob-filter-overlay').removeClass('visible');
            $('body').removeClass('oha-sheet-open');
        },

        // ---------------------------------------------------------------
        // Side Panel
        // ---------------------------------------------------------------
        toggleSidebar(mapId, $wrapper) {
            this.sidebarOpen[mapId] = !this.sidebarOpen[mapId];
            const isOpen = this.sidebarOpen[mapId];
            $wrapper.toggleClass('oha-sidebar-open', isOpen);
            $wrapper.find('.oha-panel-toggle').toggleClass('active', isOpen);
            // Keep "List" / "Map" view buttons in sync on desktop
            if (window.innerWidth >= 900) {
                $wrapper.find('.oha-view-btn[data-view="list"]').toggleClass('active', isOpen);
                $wrapper.find('.oha-view-btn[data-view="map"]').toggleClass('active', !isOpen);
            }
            const map = this.maps[mapId];
            if (map) setTimeout(() => map.resize(), 50);
            if (!isOpen && map && map.getLayer('haunts-hover-ring')) {
                const ce = this.clusterEnabled[mapId] !== false;
                map.setFilter('haunts-hover-ring', ['all',
                    ce ? ['!', ['has', 'point_count']] : ['has', 'type'],
                    ['==', ['get', 'id'], 0],
                ]);
            }
            if (isOpen) this.updateSidebarList(mapId, $wrapper);
        },

        updateSidebarList(mapId, $wrapper) {
            if (!this.sidebarOpen[mapId]) return;
            const map    = this.maps[mapId];
            const $panel = $wrapper.find('.oha-panel-list');
            if (!map || !$panel.length) return;

            const bounds    = map.getBounds();
            const region    = this.activeFilters.region;
            const types     = this.activeFilters.types;
            const allHaunts = this.allHaunts[mapId] || [];
            const query     = (this.searchQuery[mapId] || '').toLowerCase().trim();
            const favsOnly  = this.favoritesActive[mapId];
            const favIds    = favsOnly ? this.getFavorites() : null;
            const userLoc   = this.userLocation[mapId];
            const nearMe    = this.nearMeActive[mapId];

            const inView = allHaunts.filter(h => {
                if (region && h.region !== region) return false;
                if (!types.includes(h.type)) return false;
                if (favsOnly && favIds && !favIds.includes(h.id)) return false;
                if (query) {
                    const titleMatch = h.title.toLowerCase().includes(query);
                    const cityMatch  = h.city && h.city.toLowerCase().includes(query);
                    if (!titleMatch && !cityMatch) return false;
                }
                return h.lng >= bounds.getWest() && h.lng <= bounds.getEast()
                    && h.lat >= bounds.getSouth() && h.lat <= bounds.getNorth();
            });

            $wrapper.find('.oha-panel-haunt-count').text(inView.length);

            if (nearMe && userLoc) {
                inView.sort((a, b) => {
                    const da = this.haversineDistance(userLoc.lat, userLoc.lng, a.lat, a.lng);
                    const db = this.haversineDistance(userLoc.lat, userLoc.lng, b.lat, b.lng);
                    return da - db;
                });
            } else {
                inView.sort((a, b) => {
                    if (a.featured && !b.featured) return -1;
                    if (!a.featured && b.featured) return 1;
                    return a.title.localeCompare(b.title);
                });
            }

            $panel.empty();
            if (!inView.length) {
                $panel.html('<div class="oha-panel-empty"><p>No haunts in view. Pan the map to explore.</p></div>');
                return;
            }
            inView.forEach(h => {
                const dist = (nearMe && userLoc)
                    ? this.haversineDistance(userLoc.lat, userLoc.lng, h.lat, h.lng)
                    : null;
                $panel.append(this.buildPanelCard(h, dist));
            });
        },

        buildPanelCard(listing, distMiles) {
            const typeLabel = {
                featured:     'Featured',
                professional: 'Professional',
                home_haunt:   'Home Haunt',
                free:         'Listing',
            }[listing.type] || listing.type;

            const thumb = listing.thumbnail
                ? `<div class="oha-panel-card-thumb" style="background-image:url('${this.escHtml(listing.thumbnail)}')"></div>`
                : '';

            const distHtml = (distMiles != null)
                ? `<span class="oha-distance-badge">${this.formatDistance(distMiles)}</span>`
                : '';

            const favClass = this.isFavorite(listing.id) ? ' is-fav' : '';

            return `<div class="oha-panel-card oha-type-${this.escHtml(listing.type)}" data-id="${parseInt(listing.id, 10)}" data-lat="${parseFloat(listing.lat)}" data-lng="${parseFloat(listing.lng)}">
                ${thumb}
                <div class="oha-panel-card-body">
                    <div class="oha-panel-card-header">
                        <span class="oha-badge oha-badge-${this.escHtml(listing.type)}">${this.escHtml(typeLabel)}</span>
                        ${listing.fear_level ? `<span class="oha-badge oha-badge-fear oha-fear-${this.getFearLevelClass(listing.fear_level)}">${this.escHtml(listing.fear_level)}</span>` : ''}
                        ${distHtml}
                        <button class="oha-panel-fav-btn${favClass}" data-id="${parseInt(listing.id, 10)}" title="Save to favorites"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg></button>
                    </div>
                    <h4 class="oha-panel-card-title">${this.escHtml(listing.title)}</h4>
                    ${listing.address ? `<p class="oha-panel-card-addr">${this.escHtml(listing.address)}</p>` : ''}
                </div>
            </div>`;
        },

        updateMobileFilterBadge($wrapper) {
            const hasRegion     = this.activeFilters.region         ? 1 : 0;
            const hasTypeFilter = this.activeFilters.types.length < 4 ? 1 : 0;
            const hasFear       = this.activeFilters.fearLevel       ? 1 : 0;
            const hasAccess     = this.activeFilters.accessible      ? 1 : 0;
            const hasAdmission  = this.activeFilters.admissionType   ? 1 : 0;
            const hasCharity    = this.activeFilters.charityType     ? 1 : 0;
            const count         = hasRegion + hasTypeFilter + hasFear + hasAccess + hasAdmission + hasCharity;
            const $badge = $wrapper.find('.oha-mob-pill-badge');
            if (count > 0) {
                $badge.text(count).show();
            } else {
                $badge.text('').hide();
            }
        },

        // ---------------------------------------------------------------
        // Region Info Panel
        // ---------------------------------------------------------------
        showRegionInfoPanel($wrapper, slug) {
            const regionInfo = ohaMapSettings.regionInfo || {};
            const info = regionInfo[slug];
            if (!info) return;

            const regions = ohaMapSettings.regions || {};
            const name = (regions[slug] && regions[slug].name) ? regions[slug].name : slug;

            const hlItems = (info.highlights || [])
                .map(h => '<li>' + this.escHtml(h) + '</li>')
                .join('');

            const html = '<div class="oha-region-info-card">'
                + '<h2 class="oha-region-info-title">' + this.escHtml(name) + '</h2>'
                + (info.population ? '<p class="oha-region-info-pop">' + this.escHtml(info.population) + '</p>' : '')
                + (info.description ? '<p class="oha-region-info-desc">' + this.escHtml(info.description) + '</p>' : '')
                + (hlItems ? '<p class="oha-region-info-hl-label">Highlights</p>'
                           + '<ul class="oha-region-info-highlights">' + hlItems + '</ul>' : '')
                + '<button class="oha-cta-btn oha-region-explore-btn" data-region="' + this.escHtml(slug) + '">'
                + 'Explore Haunts</button>'
                + '</div>';

            $wrapper.find('.oha-region-info-content').html(html);
            $wrapper.find('.oha-region-info-sheet').addClass('open');
            $('body').addClass('oha-sheet-open');
        },

        hideRegionInfoPanel($wrapper) {
            $wrapper.find('.oha-region-info-sheet').removeClass('open');
            $('body').removeClass('oha-sheet-open');
        },

        updateLayerButtonState($wrapper) {
            const eventsEnabled = !!(ohaMapSettings && ohaMapSettings.eventsEnabled);
            if (!eventsEnabled) {
                $wrapper.find('.oha-layer-btn[data-layer="events"]').closest('.oha-filter-layers').hide();
            }
        },

        // ---------------------------------------------------------------
        // Bottom Sheet gestures
        // ---------------------------------------------------------------
        initBottomSheet($wrapper) {
            const self = this;

            // Dismiss haunt bottom sheet on overlay click
            $wrapper.on('click', '.oha-bottom-sheet .oha-bottom-sheet-overlay', function () {
                self.hideBottomSheet($wrapper);
            });

            // Dismiss region info sheet on overlay click
            $wrapper.on('click', '.oha-region-info-sheet .oha-bottom-sheet-overlay', function () {
                self.hideRegionInfoPanel($wrapper);
            });

            // Swipe down to close haunt bottom sheet (scoped to haunt sheet only)
            let startY   = 0;
            let isDragging = false;

            $wrapper.find('.oha-bottom-sheet .oha-bottom-sheet-panel').on('touchstart', function (e) {
                startY     = e.originalEvent.touches[0].clientY;
                isDragging = true;
            }, { passive: true });

            $wrapper.find('.oha-bottom-sheet .oha-bottom-sheet-panel').on('touchmove', function (e) {
                if (!isDragging) return;
                const delta = e.originalEvent.touches[0].clientY - startY;
                if (delta > 0) {
                    $(this).css('transform', `translateY(${delta}px)`);
                }
            }, { passive: true });

            $wrapper.find('.oha-bottom-sheet .oha-bottom-sheet-panel').on('touchend', function (e) {
                if (!isDragging) return;
                isDragging = false;
                const endY  = e.originalEvent.changedTouches[0].clientY;
                const delta = endY - startY;
                $(this).css('transform', '');
                if (delta > 80) {
                    self.hideBottomSheet($wrapper);
                }
            });

            // Swipe down to close region info sheet
            let riStartY = 0;
            let riDragging = false;

            $wrapper.find('.oha-region-info-sheet .oha-bottom-sheet-panel').on('touchstart', function (e) {
                riStartY   = e.originalEvent.touches[0].clientY;
                riDragging = true;
            }, { passive: true });

            $wrapper.find('.oha-region-info-sheet .oha-bottom-sheet-panel').on('touchmove', function (e) {
                if (!riDragging) return;
                const delta = e.originalEvent.touches[0].clientY - riStartY;
                if (delta > 0) {
                    $(this).css('transform', `translateY(${delta}px)`);
                }
            }, { passive: true });

            $wrapper.find('.oha-region-info-sheet .oha-bottom-sheet-panel').on('touchend', function (e) {
                if (!riDragging) return;
                riDragging = false;
                const endY  = e.originalEvent.changedTouches[0].clientY;
                const delta = endY - riStartY;
                $(this).css('transform', '');
                if (delta > 80) {
                    self.hideRegionInfoPanel($wrapper);
                }
            });
        },

        // ---------------------------------------------------------------
        // Helpers
        // ---------------------------------------------------------------
        getRegionData(slug) {
            if (!slug) {
                return { centerLng: -82.7, centerLat: 40.2, zoom: 7 };
            }
            const regions = ohaMapSettings.regions || {};
            const region  = regions[slug];
            if (region) {
                // PHP stores bounds as [[south_lat, west_lng], [north_lat, east_lng]]
                // MapLibre fitBounds needs [[west_lng, south_lat], [east_lng, north_lat]]
                let bounds = null;
                if (region.bounds && region.bounds.length === 2) {
                    bounds = [
                        [region.bounds[0][1], region.bounds[0][0]],
                        [region.bounds[1][1], region.bounds[1][0]],
                    ];
                }
                return {
                    centerLng: region.center ? region.center[1] : -82.7,
                    centerLat: region.center ? region.center[0] : 40.2,
                    zoom:      region.zoom   || 8,
                    bounds:    bounds,
                };
            }
            // Try GeoJSON properties
            if (ohaMapSettings.regionsGeoJSON) {
                try {
                    const gj = typeof ohaMapSettings.regionsGeoJSON === 'string'
                        ? JSON.parse(ohaMapSettings.regionsGeoJSON)
                        : ohaMapSettings.regionsGeoJSON;
                    const feat = (gj.features || []).find(f => f.properties.slug === slug);
                    if (feat) {
                        return {
                            centerLng: feat.properties.centerLng,
                            centerLat: feat.properties.centerLat,
                            zoom:      feat.properties.zoom || 8,
                            bounds:    feat.properties.bounds || null,
                        };
                    }
                } catch (e) {}
            }
            return { centerLng: -82.7, centerLat: 40.2, zoom: 7 };
        },

        findEventsNearCoords(props) {
            // Find events stored in any mapId that are within ~15km of the listing
            // (simple bounding box: 0.15 degree ≈ ~12 km in Ohio)
            const allEvents = Object.values(this.allEvents).flat();
            const lat = parseFloat(props.lat || 0);
            const lng = parseFloat(props.lng || 0);
            if (!lat || !lng) return [];
            return allEvents.filter(ev => {
                return Math.abs(ev.lat - lat) < 0.15 && Math.abs(ev.lng - lng) < 0.15;
            });
        },

        formatEventDate(startDate, endDate, startTime, allDay) {
            if (!startDate) return '';
            const fmt = (d) => {
                const parts = d.split('-');
                if (parts.length === 3) {
                    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                    return `${months[parseInt(parts[1], 10) - 1]} ${parseInt(parts[2], 10)}, ${parts[0]}`;
                }
                return d;
            };
            let str = fmt(startDate);
            if (endDate && endDate !== startDate) str += ` – ${fmt(endDate)}`;
            if (allDay !== 'yes' && startTime && startTime !== '00:00:00') {
                const t = startTime.substring(0, 5);
                str += ` at ${t}`;
            }
            return str;
        },

        updateCounter($wrapper, count) {
            $wrapper.find('.oha-count').text(count);
            $wrapper.find('.oha-results-counter').toggleClass('has-results', count > 0);
        },

        // ---------------------------------------------------------------
        // Near Me / Geolocation
        // ---------------------------------------------------------------
        getUserLocation(mapId, $wrapper) {
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by your browser.');
                return;
            }
            const $btn = $wrapper.find('.oha-near-me-btn');
            // Toggle off if already active
            if (this.nearMeActive[mapId]) {
                this.nearMeActive[mapId] = false;
                $btn.removeClass('active');
                const mapOff = this.maps[mapId];
                if (mapOff && mapOff.getLayer('user-location-dot')) {
                    mapOff.setLayoutProperty('user-location-dot', 'visibility', 'none');
                }
                this.updateSidebarList(mapId, $wrapper);
                return;
            }
            $btn.addClass('loading');
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;
                    this.userLocation[mapId]  = { lat, lng };
                    this.nearMeActive[mapId]  = true;
                    $btn.addClass('active').removeClass('loading');
                    const map = this.maps[mapId];
                    if (map) {
                        // Use a GeoJSON circle layer to avoid WebGL instability from DOM markers on Android
                        const locData = { type: 'Feature', geometry: { type: 'Point', coordinates: [lng, lat] } };
                        if (map.getSource('user-location')) {
                            map.getSource('user-location').setData(locData);
                            map.setLayoutProperty('user-location-dot', 'visibility', 'visible');
                        } else {
                            map.addSource('user-location', { type: 'geojson', data: locData });
                            map.addLayer({
                                id:     'user-location-dot',
                                type:   'circle',
                                source: 'user-location',
                                paint: {
                                    'circle-radius':         8,
                                    'circle-color':          '#1159af',
                                    'circle-stroke-width':   2.5,
                                    'circle-stroke-color':   '#ffffff',
                                    'circle-opacity':        0.92,
                                    'circle-stroke-opacity': 1,
                                },
                            });
                        }
                        map.easeTo({ center: [lng, lat], zoom: 10, speed: 1.2 });
                    }
                    // On desktop open sidebar for distance list; on mobile just refresh sidebar state
                    if (window.innerWidth >= 768 && !this.sidebarOpen[mapId]) {
                        this.toggleSidebar(mapId, $wrapper);
                    } else {
                        this.updateSidebarList(mapId, $wrapper);
                    }
                },
                () => {
                    $btn.removeClass('loading');
                    alert('Unable to get your location. Please allow location access and try again.');
                },
                { enableHighAccuracy: false, timeout: 10000 }
            );
        },

        haversineDistance(lat1, lng1, lat2, lng2) {
            const R    = 3958.8;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLng = (lng2 - lng1) * Math.PI / 180;
            const a    = Math.sin(dLat / 2) ** 2
                       + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180)
                       * Math.sin(dLng / 2) ** 2;
            return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        },

        formatDistance(miles) {
            if (miles < 1) return `${(Math.round(miles * 10) / 10)} mi`;
            return `${Math.round(miles)} mi`;
        },

        getFearLevelClass(fearLevel) {
            const lvl = (fearLevel || '').toLowerCase();
            if (lvl === 'mild')     return 'mild';
            if (lvl === 'moderate') return 'moderate';
            if (lvl === 'intense')  return 'intense';
            if (lvl === 'extreme')  return 'extreme';
            return 'mild';
        },

        // ---------------------------------------------------------------
        // Favorites (localStorage)
        // ---------------------------------------------------------------
        getFavorites() {
            try {
                return JSON.parse(localStorage.getItem('oha_map_favorites') || '[]');
            } catch (e) {
                return [];
            }
        },

        toggleFavorite(id) {
            const favs = this.getFavorites();
            const idx  = favs.indexOf(id);
            if (idx === -1) { favs.push(id); } else { favs.splice(idx, 1); }
            localStorage.setItem('oha_map_favorites', JSON.stringify(favs));
        },

        isFavorite(id) {
            return this.getFavorites().includes(parseInt(id, 10));
        },

        // ---------------------------------------------------------------
        // Search + Autocomplete
        // ---------------------------------------------------------------
        initSearch($wrapper, mapId) {
            const self   = this;
            const $input = $wrapper.find('.oha-search-input');
            const $clear = $wrapper.find('.oha-search-clear');
            const $list  = $wrapper.find('.oha-autocomplete-list');

            $input.on('input', function () {
                const q = $(this).val().trim();
                self.searchQuery[mapId] = q;
                $clear.toggle(q.length > 0);
                if (q.length >= 2) {
                    self.showAutocomplete($wrapper, mapId, q);
                } else {
                    $list.empty().hide();
                }
                if (!q) {
                    self.applyFilters(mapId, $wrapper);
                }
            });

            $input.on('keydown', function (e) {
                if (e.key === 'Enter') {
                    $list.empty().hide();
                    self.applyFilters(mapId, $wrapper);
                }
                if (e.key === 'Escape') {
                    $(this).val('');
                    self.searchQuery[mapId] = '';
                    $clear.hide();
                    $list.empty().hide();
                    self.applyFilters(mapId, $wrapper);
                }
            });

            $clear.on('click', function () {
                $input.val('');
                self.searchQuery[mapId] = '';
                $(this).hide();
                $list.empty().hide();
                self.applyFilters(mapId, $wrapper);
            });

            $(document).on('click.oha-search-' + mapId, function (e) {
                if (!$(e.target).closest('.oha-search-wrap').length) {
                    $list.empty().hide();
                }
            });
        },

        showAutocomplete($wrapper, mapId, query) {
            const q      = query.toLowerCase();
            const haunts = this.allHaunts[mapId] || [];
            const $list  = $wrapper.find('.oha-autocomplete-list');

            const hauntMatches = haunts.filter(h => h.title.toLowerCase().includes(q)).slice(0, 5);

            const citySet = new Set();
            haunts.forEach(h => {
                if (h.city && h.city.toLowerCase().includes(q)) citySet.add(h.city);
            });
            const cityMatches = [...citySet].slice(0, 3);

            if (!hauntMatches.length && !cityMatches.length) {
                $list.empty().hide();
                return;
            }

            let html = '';
            hauntMatches.forEach(h => {
                html += `<div class="oha-autocomplete-item oha-autocomplete-haunt" data-id="${parseInt(h.id, 10)}" data-lat="${parseFloat(h.lat)}" data-lng="${parseFloat(h.lng)}">`;
                html += `<svg class="oha-ac-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>`;
                html += `<span>${this.escHtml(h.title)}</span>`;
                html += `</div>`;
            });
            cityMatches.forEach(city => {
                html += `<div class="oha-autocomplete-item oha-autocomplete-city" data-city="${this.escHtml(city)}">`;
                html += `<svg class="oha-ac-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>`;
                html += `<span>${this.escHtml(city)}</span>`;
                html += `</div>`;
            });
            $list.html(html).show();

            $list.find('.oha-autocomplete-haunt').on('click', (e) => {
                const $item = $(e.currentTarget);
                const id    = parseInt($item.data('id'), 10);
                const lat   = parseFloat($item.data('lat'));
                const lng   = parseFloat($item.data('lng'));
                const h     = haunts.find(x => x.id == id);
                $wrapper.find('.oha-search-input').val(h ? h.title : '');
                this.searchQuery[mapId] = h ? h.title : '';
                $list.empty().hide();
                const map = this.maps[mapId];
                if (map) map.easeTo({ center: [lng, lat], zoom: 13, speed: 1.2 });
                if (h) this.showBottomSheet($wrapper, h, 'haunt');
                this.applyFilters(mapId, $wrapper);
            });

            $list.find('.oha-autocomplete-city').on('click', (e) => {
                const city = $(e.currentTarget).data('city');
                $wrapper.find('.oha-search-input').val(city);
                this.searchQuery[mapId] = city;
                $list.empty().hide();
                this.applyFilters(mapId, $wrapper);
                const first = haunts.find(h => h.city && h.city.toLowerCase() === city.toLowerCase());
                if (first) {
                    const map = this.maps[mapId];
                    if (map) map.easeTo({ center: [first.lng, first.lat], zoom: 10, speed: 1.2 });
                }
            });
        },

        escHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        },
    };

    // ---------------------------------------------------------------
    // Boot
    // ---------------------------------------------------------------
    $(document).ready(() => OHAMap.init());

}(jQuery));
