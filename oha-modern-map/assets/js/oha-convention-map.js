/**
 * OHA Convention Map — Indoor Floor Plan Engine
 * Uses MapLibre GL JS with image source for indoor floorplan rendering.
 * Namespace: OHAConvention
 */
(function ($) {
    'use strict';

    /* -----------------------------------------------------------------------
     * Constants
     * --------------------------------------------------------------------- */

    // The floorplan image fills a [0,0]→[1,1] coordinate space.
    // X maps directly to longitude; Y is inverted (top=1, bottom=0).
    var BOUNDS = {
        west:   0,
        east:   1,
        north:  1,
        south:  0,
        center: [0.5, 0.5],
        zoom:   8,          // Initial zoom — MapLibre will re-calculate from bounds
        minZoom: 5,
        maxZoom: 20,
    };

    // Map type key → default marker color
    var TYPE_COLORS = {
        vendor:     '#ff6b35',
        food:       '#ffd700',
        food_truck: '#e67e22',
        restroom:   '#00b4d8',
        class:      '#9d4edd',
        stage:      '#e74c3c',
        info:       '#27ae60',
        exit:       '#95a5a6',
        general:    '#7f8c8d',
    };

    // Icon names used with map.addImage (must match SVG files loaded)
    var TYPE_ICONS = {
        vendor:     'conv-vendor',
        food:       'conv-food',
        food_truck: 'conv-food',
        restroom:   'conv-restroom',
        class:      'conv-class',
        stage:      'conv-stage',
        info:       'conv-info',
        exit:       'conv-exit',
        general:    'conv-general',
    };

    /* -----------------------------------------------------------------------
     * Instance factory — one per [oha_convention_map] shortcode on a page
     * --------------------------------------------------------------------- */

    var OHAConvention = {

        instances: {},

        /**
         * Init all convention map instances defined by ohaConventionConfig.
         */
        initAll: function () {
            if (!window.ohaConventionConfig || !window.ohaConventionConfig.length) return;
            window.ohaConventionConfig.forEach(function (config) {
                OHAConvention.create(config);
            });
        },

        /**
         * Create and boot a single convention map instance.
         * @param {Object} config  Data from wp_json_encode in shortcode
         */
        create: function (config) {
            var inst = new ConventionMap(config);
            OHAConvention.instances[config.wrapperId] = inst;
            inst.boot();
        },
    };

    /* -----------------------------------------------------------------------
     * ConventionMap — single map instance
     * --------------------------------------------------------------------- */

    function ConventionMap(config) {
        this.config      = config;
        this.mapId       = config.mapId;
        this.wrapperId   = config.wrapperId;
        this.$wrapper    = $('#' + config.wrapperId);
        this.map         = null;
        this.allBooths   = [];
        this.filtered    = [];
        this.activeType  = 'all';
        this.activeFloor = config.initialFloor || 1;
        this.floors      = config.floors || [];
        this.searchQuery = '';
        this.iconsLoaded = false;
        // Mobile app mode
        this.isMobile       = !!config.mobile;
        this.listViewActive = false;
    }

    ConventionMap.prototype = {

        /* -------------------------------------------------------------------
         * Bootstrapping
         * ----------------------------------------------------------------- */

        boot: function () {
            var self = this;
            // Wait for MapLibre to fully define its Map constructor before initialising.
            // A partially-executed CDN script can make typeof maplibregl !== 'undefined'
            // while maplibregl.Map is still undefined, causing a silent TypeError.
            if (typeof maplibregl === 'undefined' || typeof maplibregl.Map !== 'function') {
                setTimeout(function () { self.boot(); }, 400);
                return;
            }
            self.initMap();
        },

        initMap: function () {
            var self    = this;
            var floor   = self.getFloor(self.activeFloor);
            var bgColor = (floor && floor.bgColor) ? floor.bgColor : '#1a1a2e';

            // Mobile: ensure MapLibre container has explicit pixel height before init.
            // CSS flex + dvh/vh may not be resolved yet, giving a 0-height container.
            // A 0-height container prevents MapLibre from creating a valid canvas and
            // can prevent the 'load' event from firing.
            if (self.isMobile) {
                var wrapper = self.$wrapper[0];
                if (wrapper) {
                    var h = wrapper.offsetHeight;
                    if (h < 10) {
                        var cssOffset = parseFloat(
                            window.getComputedStyle(wrapper)
                                .getPropertyValue('--oha-mobile-ui-offset')
                        ) || 110;
                        h = Math.max(400, window.innerHeight - cssOffset);
                        wrapper.style.height = h + 'px';
                    }
                    var mapArea = wrapper.querySelector('.oha-conv-map-area');
                    if (mapArea) mapArea.style.height = h + 'px';
                }
                window.addEventListener('resize', function () {
                    if (self.map) self.map.resize();
                });
            }

            self.map = new maplibregl.Map({
                container: self.mapId,
                style: {
                    version: 8,
                    sources: {},
                    layers: [{
                        id:    'background',
                        type:  'background',
                        paint: { 'background-color': bgColor },
                    }],
                    glyphs: 'https://demotiles.maplibre.org/font/{fontstack}/{range}.pbf',
                },
                center:    BOUNDS.center,
                zoom:      BOUNDS.zoom,
                minZoom:   BOUNDS.minZoom,
                maxZoom:   BOUNDS.maxZoom,
                attributionControl: false,
                pitchWithRotate: false,
            });

            // Log MapLibre errors to console for easier debugging.
            self.map.on('error', function (e) {
                console.warn('OHA Convention Map error:', e.error || e);
            });

            // Safety net: if the load callback chain stalls for any reason (e.g. icon
            // network hang, JS error), hide the spinner after 15 s so the map is usable.
            var loadTimeout = setTimeout(function () {
                self.hideLoading();
            }, 15000);

            self.map.on('load', function () {
                clearTimeout(loadTimeout);
                self.loadFloor(self.activeFloor, function () {
                    self.loadIcons(function () {
                        self.addBoothLayers();
                        self.loadBooths(function () {
                            self.hideLoading();
                            // Ensure MapLibre fills the container now that content is visible
                            if (self.isMobile && self.map) self.map.resize();
                            self.checkDeepLink();
                            self.initControls();
                            self.initBottomSheet();
                        });
                    });
                });
            });
        },

        /* -------------------------------------------------------------------
         * Floor management
         * ----------------------------------------------------------------- */

        getFloor: function (id) {
            for (var i = 0; i < this.floors.length; i++) {
                if (parseInt(this.floors[i].id) === parseInt(id)) return this.floors[i];
            }
            return this.floors[0] || null;
        },

        /**
         * Load the floorplan image for a floor and add it as a MapLibre image source.
         * The image fills the entire [0,0]→[1,1] coordinate space.
         * @param {number}   floorId
         * @param {Function} cb  Called when done (or on failure)
         */
        loadFloor: function (floorId, cb) {
            var self  = this;
            var floor = self.getFloor(floorId);

            // Remove previous floorplan layers/sources
            ['floorplan-raster', 'floorplan-border'].forEach(function (id) {
                if (self.map.getLayer(id)) self.map.removeLayer(id);
            });
            if (self.map.getSource('floorplan')) self.map.removeSource('floorplan');

            if (!floor || !floor.imageUrl) {
                // No image configured — show a placeholder grid
                self.addPlaceholderGrid();
                if (cb) cb();
                return;
            }

            // Add image source
            // Corners: top-left, top-right, bottom-right, bottom-left (lng/lat pairs)
            self.map.addSource('floorplan', {
                type: 'image',
                url:  floor.imageUrl,
                coordinates: [
                    [0, 1],  // top-left
                    [1, 1],  // top-right
                    [1, 0],  // bottom-right
                    [0, 0],  // bottom-left
                ],
            });

            // No beforeId — adds on top of 'background' so the image is visible.
            // (Booth layers are added later by addBoothLayers and will sit above this.)
            self.map.addLayer({
                id:     'floorplan-raster',
                type:   'raster',
                source: 'floorplan',
                paint:  { 'raster-opacity': 1.0 },
            });

            if (cb) cb();
        },

        addPlaceholderGrid: function () {
            // When no floorplan image is set, show a simple dot-grid
            if (!this.map.getSource('placeholder-grid')) {
                var features = [];
                for (var x = 0.1; x < 1; x += 0.1) {
                    for (var y = 0.1; y < 1; y += 0.1) {
                        features.push({ type: 'Feature', geometry: { type: 'Point', coordinates: [x, y] }, properties: {} });
                    }
                }
                this.map.addSource('placeholder-grid', {
                    type: 'geojson',
                    data: { type: 'FeatureCollection', features: features },
                });
                this.map.addLayer({
                    id:     'placeholder-dots',
                    type:   'circle',
                    source: 'placeholder-grid',
                    paint:  {
                        'circle-radius': 2,
                        'circle-color':  'rgba(255,255,255,0.1)',
                    },
                });
            }
        },

        /* -------------------------------------------------------------------
         * Icon loading
         * ----------------------------------------------------------------- */

        loadIcons: function (cb) {
            var self     = this;
            var pluginUrl = (this.config.pluginUrl || '').replace(/\/$/, '');
            var iconDefs = [
                { name: 'conv-vendor',   file: 'conv-vendor.svg' },
                { name: 'conv-food',     file: 'conv-food.svg' },
                { name: 'conv-restroom', file: 'conv-restroom.svg' },
                { name: 'conv-class',    file: 'conv-class.svg' },
                { name: 'conv-stage',    file: 'conv-stage.svg' },
                { name: 'conv-info',     file: 'conv-info.svg' },
                { name: 'conv-exit',     file: 'conv-exit.svg' },
                { name: 'conv-general',  file: 'conv-general.svg' },
            ];

            var promises = iconDefs.map(function (def) {
                if (self.map.hasImage(def.name)) { return Promise.resolve(); }
                // MapLibre GL JS v4 uses Promise-based loadImage (callback API removed)
                return self.map.loadImage(pluginUrl + '/assets/images/icons/' + def.file)
                    .then(function (response) {
                        // v4 returns {data, cacheControl, expires}; extract the image
                        var img = (response && response.data) ? response.data : response;
                        if (!self.map.hasImage(def.name)) {
                            self.map.addImage(def.name, img);
                        }
                    })
                    .catch(function () {
                        // Fallback: canvas-drawn circle
                        self.map.addImage(def.name, self.makeCircleImage(TYPE_COLORS[def.name.replace('conv-', '')] || '#7f8c8d'));
                    });
            });

            Promise.all(promises).then(function () {
                self.iconsLoaded = true;
                if (cb) cb();
            });
        },

        makeCircleImage: function (color) {
            var size = 32;
            var canvas = document.createElement('canvas');
            canvas.width = canvas.height = size;
            var ctx = canvas.getContext('2d');
            ctx.beginPath();
            ctx.arc(size / 2, size / 2, size / 2 - 2, 0, Math.PI * 2);
            ctx.fillStyle = color;
            ctx.fill();
            ctx.strokeStyle = 'rgba(255,255,255,0.6)';
            ctx.lineWidth = 2;
            ctx.stroke();
            return { width: size, height: size, data: new Uint8Array(ctx.getImageData(0, 0, size, size).data) };
        },

        /* -------------------------------------------------------------------
         * Booth layers
         * ----------------------------------------------------------------- */

        addBoothLayers: function () {
            var self = this;

            // Pin size: admin-configurable radius at zoom 12 (default 5).
            // All other sizes scale proportionally from this base.
            var s        = self.config.pinSize || 5;
            var hitzone  = Math.round(s * 2.8);
            var r6       = Math.round(s * 0.6 * 10) / 10;
            var r18      = Math.round(s * 1.4 * 10) / 10;
            var iconBase = s / 5;   // 1.0 at default size 5
            var i6       = Math.round(iconBase * 0.20 * 1000) / 1000;
            var i12      = Math.round(iconBase * 0.32 * 1000) / 1000;
            var i18      = Math.round(iconBase * 0.45 * 1000) / 1000;

            // Empty source — data populated after AJAX
            self.map.addSource('booths', {
                type:    'geojson',
                data:    { type: 'FeatureCollection', features: [] },
                cluster: false,
            });

            // Hit zone (transparent circle for easier tapping on mobile)
            self.map.addLayer({
                id:     'booth-hitzone',
                type:   'circle',
                source: 'booths',
                paint:  {
                    'circle-radius':  hitzone,
                    'circle-opacity': 0,
                    'circle-color':   'transparent',
                },
            });

            // Booth background circle
            self.map.addLayer({
                id:     'booth-bg',
                type:   'circle',
                source: 'booths',
                paint:  {
                    'circle-color':        ['get', 'typeColor'],
                    'circle-radius':       [
                        'interpolate', ['linear'], ['zoom'],
                        6,  r6,
                        12, s,
                        18, r18,
                    ],
                    'circle-stroke-color': 'rgba(255,255,255,0.85)',
                    'circle-stroke-width': 1.5,
                    'circle-opacity':      1,
                },
            });

            // Booth icon symbol
            self.map.addLayer({
                id:     'booth-icon',
                type:   'symbol',
                source: 'booths',
                layout: {
                    'icon-image':         ['get', 'icon'],
                    'icon-size':          [
                        'interpolate', ['linear'], ['zoom'],
                        6,  i6,
                        12, i12,
                        18, i18,
                    ],
                    'icon-allow-overlap': true,
                    'icon-anchor':        'center',
                },
            });

            // Booth number/name label
            self.map.addLayer({
                id:     'booth-label',
                type:   'symbol',
                source: 'booths',
                minzoom: 10,
                layout: {
                    'text-field': [
                        'step', ['zoom'],
                        // zoom 10-11: booth number only (keep map uncluttered)
                        ['case', ['!=', ['get', 'number'], ''], ['get', 'number'], ''],
                        // zoom 12+: number + vendor name on two lines when both present
                        12, ['case',
                            ['all', ['!=', ['get', 'number'], ''], ['!=', ['get', 'vendorName'], '']],
                            ['concat', ['get', 'number'], '\n', ['get', 'vendorName']],
                            ['!=', ['get', 'number'], ''], ['get', 'number'],
                            ['!=', ['get', 'vendorName'], ''], ['get', 'vendorName'],
                            ['get', 'title']
                        ]
                    ],
                    'text-font':            ['Open Sans Regular', 'Arial Unicode MS Regular'],
                    'text-size':            [
                        'interpolate', ['linear'], ['zoom'],
                        10, 9,
                        14, 11,
                        18, 13,
                    ],
                    'text-offset':          [0, 1.6],
                    'text-anchor':          'top',
                    'text-allow-overlap':   false,
                    'text-max-width':       12,
                },
                paint: {
                    'text-color':       '#ffffff',
                    'text-halo-color':  'rgba(0,0,0,0.8)',
                    'text-halo-width':  1.5,
                },
            });

            // Interactions
            self.map.on('click', 'booth-hitzone', function (e) {
                if (!e.features.length) return;
                self.showBoothCard(e.features[0].properties);
            });
            self.map.on('click', 'booth-bg', function (e) {
                if (!e.features.length) return;
                self.showBoothCard(e.features[0].properties);
            });
            self.map.on('mouseenter', 'booth-bg', function () {
                self.map.getCanvas().style.cursor = 'pointer';
            });
            self.map.on('mouseleave', 'booth-bg', function () {
                self.map.getCanvas().style.cursor = '';
            });
        },

        /* -------------------------------------------------------------------
         * Data loading
         * ----------------------------------------------------------------- */

        loadBooths: function (cb) {
            var self = this;
            $.ajax({
                url:  self.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'oha_get_convention_booths',
                    nonce:  self.config.nonce,
                    floor:  0, // load all floors, filter client-side
                },
                success: function (response) {
                    if (response.success && response.data) {
                        self.allBooths = response.data;
                        self.applyFilters();
                        self.renderList();
                    }
                    if (cb) cb();
                },
                error: function () {
                    if (cb) cb();
                },
            });
        },

        boothsToGeoJSON: function (booths) {
            var features = booths.map(function (b) {
                // Convert x%/y% to [lng, lat] in [0,1] space
                // x=0% → lng=0, x=100% → lng=1
                // y=0% → lat=1 (top), y=100% → lat=0 (bottom)
                var lng = (parseFloat(b.x) || 0) / 100;
                var lat = 1 - (parseFloat(b.y) || 0) / 100;
                return {
                    type: 'Feature',
                    geometry: { type: 'Point', coordinates: [lng, lat] },
                    properties: {
                        id:           b.id,
                        title:        b.title || '',
                        number:       b.number || '',
                        type:         b.type || 'general',
                        typeLabel:    b.type_label || '',
                        typeColor:    b.type_color || '#7f8c8d',
                        icon:         b.icon || 'conv-general',
                        floor:        b.floor || 1,
                        status:       b.status || 'open',
                        hours:        b.hours || '',
                        contact:      b.contact || '',
                        website:      b.website || '',
                        description:  b.description || '',
                        thumbnail:    b.thumbnail || '',
                        vendorName:   b.vendor_name || '',
                        vendorLogo:   b.vendor_logo || '',
                        vendorDesc:   b.vendor_desc || '',
                        vendorHubId:  b.vendor_hub_id || 0,
                        vendorId:     b.vendor_id || 0,
                        editUrl:      b.edit_url || '',
                        x:            b.x,
                        y:            b.y,
                    },
                };
            });
            return { type: 'FeatureCollection', features: features };
        },

        /* -------------------------------------------------------------------
         * Filtering
         * ----------------------------------------------------------------- */

        applyFilters: function () {
            var self  = this;
            var type  = self.activeType;
            var floor = self.activeFloor;
            var q     = self.searchQuery.toLowerCase();

            self.filtered = self.allBooths.filter(function (b) {
                if (type !== 'all' && b.type !== type) return false;
                if (parseInt(b.floor) !== parseInt(floor)) return false;
                if (q) {
                    var haystack = ((b.title || '') + ' ' + (b.number || '') + ' ' + (b.vendor_name || '')).toLowerCase();
                    if (haystack.indexOf(q) === -1) return false;
                }
                return true;
            });

            if (self.map.getSource('booths')) {
                self.map.getSource('booths').setData(self.boothsToGeoJSON(self.filtered));
            }

            self.updateCounter();
        },

        updateCounter: function () {
            var $c = this.$wrapper.find('.oha-conv-count');
            $c.text(this.filtered.length);
        },

        /* -------------------------------------------------------------------
         * Floor switching
         * ----------------------------------------------------------------- */

        switchFloor: function (floorId) {
            var self  = this;
            var floor = self.getFloor(floorId);
            self.activeFloor = parseInt(floorId);

            // Update background color
            if (floor && floor.bgColor && self.map.getLayer('background')) {
                self.map.setPaintProperty('background', 'background-color', floor.bgColor);
            }

            self.loadFloor(floorId, function () {
                self.applyFilters();
                self.renderList();
            });

            // Update pill UI
            self.$wrapper.find('.oha-floor-btn').each(function () {
                $(this).toggleClass('active', parseInt($(this).data('floor')) === self.activeFloor);
            });
        },

        /* -------------------------------------------------------------------
         * List view
         * ----------------------------------------------------------------- */

        renderList: function () {
            var self = this;
            var $list = self.$wrapper.find('.oha-conv-list');
            if (!$list.length) return;

            $list.empty();

            if (!self.filtered.length) {
                $list.siblings('.oha-conv-list-empty').show();
                return;
            }
            $list.siblings('.oha-conv-list-empty').hide();

            self.filtered.forEach(function (b) {
                var displayName = b.vendor_name || b.title;
                var $item = $('<div class="oha-conv-list-item" role="listitem" tabindex="0"></div>');
                $item.attr('data-id', b.id);
                $item.css('--item-color', b.type_color || '#7f8c8d');

                var thumb = b.vendor_logo || b.thumbnail
                    ? '<img class="oha-conv-list-thumb" src="' + escHtml(b.vendor_logo || b.thumbnail) + '" alt="" loading="lazy">'
                    : '<div class="oha-conv-list-thumb oha-conv-list-thumb-placeholder" style="background:' + escHtml(b.type_color) + '"></div>';

                $item.html(
                    thumb +
                    '<div class="oha-conv-list-info">' +
                        (b.number ? '<span class="oha-conv-list-num">' + escHtml(b.number) + '</span> ' : '') +
                        '<span class="oha-conv-list-name">' + escHtml(displayName) + '</span>' +
                        '<span class="oha-conv-list-type">' + escHtml(b.type_label) + '</span>' +
                    '</div>'
                );

                $item.on('click keypress', function (e) {
                    if (e.type === 'keypress' && e.which !== 13) return;
                    self.flyToBooth(b);
                    self.showBoothCard(b);
                });

                $list.append($item);
            });
        },

        flyToBooth: function (booth) {
            // In mobile mode, switch back to map view first
            if (this.isMobile && this.listViewActive) {
                this.showMapView();
            }
            var lng = (parseFloat(booth.x) || 0) / 100;
            var lat = 1 - (parseFloat(booth.y) || 0) / 100;
            this.map.flyTo({
                center: [lng, lat],
                zoom:   Math.max(this.map.getZoom(), 14),
                duration: 500,
            });
        },

        /* -------------------------------------------------------------------
         * Bottom sheet (booth detail card)
         * ----------------------------------------------------------------- */

        showBoothCard: function (props) {
            var self = this;
            // In mobile mode, switch back to map view so the bottom sheet is visible
            if (self.isMobile && self.listViewActive) {
                self.showMapView();
            }
            var $sheet = self.$wrapper.find('.oha-conv-bottom-sheet');

            // Normalize — might be from GeoJSON properties (serialized) or raw booth object
            var b = typeof props === 'string' ? JSON.parse(props) : props;
            self._currentBoothCard = b;
            // Property names vary (camelCase from GeoJSON vs snake_case from AJAX)
            var displayName  = b.vendorName || b.vendor_name || b.title || '';
            var logo         = b.vendorLogo || b.vendor_logo || b.thumbnail || '';
            var vendorDesc   = b.vendorDesc || b.vendor_desc || '';
            var description  = b.description || vendorDesc || '';
            var typeLabel    = b.typeLabel   || b.type_label  || '';
            var typeColor    = b.typeColor   || b.type_color  || '#7f8c8d';
            var vendorHubId  = b.vendorHubId || b.vendor_hub_id || 0;
            var isVendorType = (b.type || '').toLowerCase() === 'vendor';
            var statusMap    = { open: 'Open', closed: 'Closed', tbd: 'TBD' };
            var status       = statusMap[b.status] || '';
            var statusClass  = b.status === 'open' ? 'open' : (b.status === 'closed' ? 'closed' : 'tbd');

            var html = '';

            if (logo) {
                html += '<div class="oha-conv-card-thumb-wrap"><img class="oha-conv-card-thumb" src="' + escHtml(logo) + '" alt="' + escHtml(displayName) + '"></div>';
            }

            html += '<div class="oha-conv-card-header">';
            if (b.number) html += '<span class="oha-conv-card-number">' + escHtml(b.number) + '</span>';
            html += '<h3 class="oha-conv-card-title">' + escHtml(displayName) + '</h3>';
            html += '<div class="oha-conv-card-badges">';
            html += '<span class="oha-conv-card-type" style="background:' + escHtml(typeColor) + '">' + escHtml(typeLabel) + '</span>';
            if (status) html += '<span class="oha-conv-card-status ' + escHtml(statusClass) + '">' + escHtml(status) + '</span>';
            html += '</div>';
            html += '</div>';

            if (description) {
                html += '<p class="oha-conv-card-desc">' + escHtml(description) + '</p>';
            }

            var iconClock  = '<svg viewBox="0 0 16 16" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="6.5"/><polyline points="8,4.5 8,8 10.5,10"/></svg>';
            var iconPerson = '<svg viewBox="0 0 16 16" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="5.5" r="2.5"/><path d="M3 13.5c0-2.76 2.24-5 5-5s5 2.24 5 5"/></svg>';

            var infoRows = '';
            if (b.hours)   infoRows += '<div class="oha-conv-card-row"><span class="oha-conv-card-icon">' + iconClock  + '</span>' + escHtml(b.hours)   + '</div>';
            if (b.contact) infoRows += '<div class="oha-conv-card-row"><span class="oha-conv-card-icon">' + iconPerson + '</span>' + escHtml(b.contact) + '</div>';
            if (b.website) infoRows += '<div class="oha-conv-card-row"><a href="' + escHtml(b.website) + '" target="_blank" rel="noopener" class="oha-conv-card-link">Visit Website</a></div>';

            if (infoRows) html += '<div class="oha-conv-card-info">' + infoRows + '</div>';

            if (isVendorType && vendorHubId) {
                var isFav = self.isFavoriteVendor(vendorHubId);
                html += '<button class="oha-conv-card-fav-btn' + (isFav ? ' active' : '') + '" data-vendor-hub-id="' + escHtml(String(vendorHubId)) + '" aria-label="' + (isFav ? 'Remove from saved vendors' : 'Save to My Vendors') + '">'
                    + (isFav ? 'Remove from Saved' : 'Save to My Vendors')
                    + '</button>';
            }

            if (b.editUrl) {
                html += '<a href="' + escHtml(b.editUrl) + '" class="oha-conv-card-edit" target="_blank">Edit Booth</a>';
            }

            $sheet.find('.oha-bottom-sheet-content').html(html);
            $sheet.attr('aria-hidden', 'false').addClass('active');
            $('body').addClass('oha-sheet-open');

            // Highlight booth in list
            self.$wrapper.find('.oha-conv-list-item').removeClass('highlighted');
            self.$wrapper.find('.oha-conv-list-item[data-id="' + b.id + '"]').addClass('highlighted');
        },

        hideBoothCard: function () {
            var $sheet = this.$wrapper.find('.oha-conv-bottom-sheet');
            $sheet.attr('aria-hidden', 'true').removeClass('active');
            $('body').removeClass('oha-sheet-open');
            this.$wrapper.find('.oha-conv-list-item').removeClass('highlighted');
        },

        /* -------------------------------------------------------------------
         * Deep link support (?oha_booth=217 or ?oha_vendor=slug)
         * ----------------------------------------------------------------- */

        checkDeepLink: function () {
            var self   = this;
            var params = new URLSearchParams(window.location.search);

            var boothNum = params.get('oha_booth');
            if (boothNum) {
                var match = self.allBooths.find(function (b) {
                    return String(b.number) === String(boothNum);
                });
                if (match) {
                    // Switch to correct floor first
                    if (parseInt(match.floor) !== self.activeFloor) {
                        self.switchFloor(match.floor);
                    }
                    setTimeout(function () {
                        self.flyToBooth(match);
                        self.showBoothCard(match);
                    }, 400);
                }
                return;
            }

            var vendorSlug = params.get('oha_vendor');
            if (vendorSlug) {
                var vMatch = self.allBooths.find(function (b) {
                    return b.vendor_hub_id && String(b.vendor_hub_id) === String(vendorSlug);
                });
                if (vMatch) {
                    if (parseInt(vMatch.floor) !== self.activeFloor) self.switchFloor(vMatch.floor);
                    setTimeout(function () {
                        self.flyToBooth(vMatch);
                        self.showBoothCard(vMatch);
                    }, 400);
                }
            }
        },

        /* -------------------------------------------------------------------
         * UI Controls (filter pills, search, zoom buttons)
         * ----------------------------------------------------------------- */

        initControls: function () {
            // Route to mobile or standard UI
            if (this.isMobile) {
                this.initMobileUI();
                return;
            }

            var self = this;
            var $w   = self.$wrapper;

            // Type filter pills
            $w.on('click', '.oha-conv-type-btn, .oha-conv-type-all', function () {
                var type = $(this).data('type') || 'all';
                self.activeType = type;
                $w.find('.oha-conv-type-btn, .oha-conv-type-all').removeClass('active');
                $(this).addClass('active');
                self.applyFilters();
                self.renderList();
            });

            // Floor pills
            $w.on('click', '.oha-floor-btn', function () {
                self.switchFloor($(this).data('floor'));
            });

            // Search
            $w.on('input', '.oha-conv-search', function () {
                self.searchQuery = $(this).val();
                self.applyFilters();
                self.renderList();
            });

            // Zoom buttons
            $w.on('click', '.oha-conv-zoom-btn', function () {
                var action = $(this).data('action');
                if (action === 'zoom-in')  self.map.zoomIn();
                else if (action === 'zoom-out') self.map.zoomOut();
                else if (action === 'reset') {
                    self.map.flyTo({
                        center: BOUNDS.center,
                        zoom:   BOUNDS.zoom,
                        duration: 400,
                    });
                }
            });
        },

        /* -------------------------------------------------------------------
         * Mobile UI — filter pill, filter sheet, list toggle, search bar
         * ----------------------------------------------------------------- */

        initMobileUI: function () {
            var self = this;
            var $w   = self.$wrapper;

            // Filters pill (map view) + filter button (list view)  → open sheet
            $w.on('click', '[data-mob-action="open-filters"]', function () {
                self.openFilterSheet();
            });

            // Overlay click → close sheet
            $w.on('click', '.oha-mob-overlay', function () {
                self.closeFilterSheet();
            });

            // Done button → close sheet
            $w.on('click', '.oha-mob-sheet-done', function () {
                self.closeFilterSheet();
            });

            // Type filter pills (inside filter sheet)
            $w.on('click', '.oha-conv-type-btn, .oha-conv-type-all', function () {
                var type = $(this).data('type') || 'all';
                self.activeType = type;
                $w.find('.oha-conv-type-btn, .oha-conv-type-all').removeClass('active');
                $(this).addClass('active');
                self.applyFilters();
                self.renderList();
                self.updateFilterBadge();
            });

            // Floor pills (inside filter sheet) → switch floor + close sheet
            $w.on('click', '.oha-floor-btn', function () {
                self.switchFloor($(this).data('floor'));
                self.closeFilterSheet();
            });

            // Search toggle button (top-right icon)
            $w.on('click', '.oha-mob-search-toggle', function () {
                var $bar = $w.find('.oha-mob-search-bar');
                if ($bar.is(':visible')) {
                    $bar.hide();
                    self.searchQuery = '';
                    $bar.find('.oha-conv-search').val('');
                    self.applyFilters();
                    self.renderList();
                } else {
                    $bar.show();
                    $bar.find('.oha-conv-search').focus();
                }
            });

            // Search close button
            $w.on('click', '.oha-mob-search-close', function () {
                var $bar = $w.find('.oha-mob-search-bar');
                $bar.hide();
                self.searchQuery = '';
                $bar.find('.oha-conv-search').val('');
                self.applyFilters();
                self.renderList();
            });

            // Search input — live filter
            $w.on('input', '.oha-conv-search', function () {
                self.searchQuery = $(this).val();
                self.applyFilters();
                self.renderList();
            });

            // List View toggle pill (map view) → show list
            $w.on('click', '.oha-mob-list-toggle-btn', function () {
                self.showListView();
            });

            // Map View button (list view topbar) → show map
            $w.on('click', '.oha-mob-map-toggle-btn', function () {
                self.showMapView();
            });

            // Zoom buttons (floating bottom-right)
            $w.on('click', '.oha-conv-zoom-btn', function () {
                var action = $(this).data('action');
                if (action === 'zoom-in')  self.map.zoomIn();
                else if (action === 'zoom-out') self.map.zoomOut();
                else if (action === 'reset') {
                    self.map.flyTo({ center: BOUNDS.center, zoom: BOUNDS.zoom, duration: 400 });
                }
            });

            // Swipe-down filter sheet to dismiss
            var $sheetPanel = $w.find('.oha-mob-sheet-panel');
            if ($sheetPanel.length) {
                var sheetStartY = 0;
                $sheetPanel[0].addEventListener('touchstart', function (e) {
                    sheetStartY = e.touches[0].clientY;
                }, { passive: true });
                $sheetPanel[0].addEventListener('touchend', function (e) {
                    if (e.changedTouches[0].clientY - sheetStartY > 60) self.closeFilterSheet();
                }, { passive: true });
            }
        },

        openFilterSheet: function () {
            this.$wrapper.find('.oha-mob-filter-sheet')
                .addClass('active').attr('aria-hidden', 'false');
        },

        closeFilterSheet: function () {
            this.$wrapper.find('.oha-mob-filter-sheet')
                .removeClass('active').attr('aria-hidden', 'true');
        },

        showListView: function () {
            var $lv = this.$wrapper.find('.oha-mob-list-view');
            $lv.show().attr('aria-hidden', 'false');
            this.listViewActive = true;
            this.renderList();
        },

        showMapView: function () {
            this.$wrapper.find('.oha-mob-list-view')
                .hide().attr('aria-hidden', 'true');
            this.listViewActive = false;
        },

        updateFilterBadge: function () {
            var count  = this.activeType !== 'all' ? 1 : 0;
            var $badge = this.$wrapper.find('.oha-mob-pill-badge');
            if (count > 0) {
                $badge.text(count).show();
            } else {
                $badge.hide();
            }
        },

        initBottomSheet: function () {
            var self  = this;
            var $sheet = self.$wrapper.find('.oha-conv-bottom-sheet');

            // Close on overlay click
            $sheet.find('.oha-bottom-sheet-overlay').on('click', function () {
                self.hideBoothCard();
            });

            // Favorites button — vendor booths only
            $sheet.on('click', '.oha-conv-card-fav-btn', function () {
                if (self._currentBoothCard) {
                    self.toggleFavoriteVendor(self._currentBoothCard, $(this));
                }
            });

            // Swipe-down to dismiss
            var startY = 0;
            var $panel = $sheet.find('.oha-bottom-sheet-panel');
            $panel[0].addEventListener('touchstart', function (e) {
                startY = e.touches[0].clientY;
            }, { passive: true });
            $panel[0].addEventListener('touchend', function (e) {
                var delta = e.changedTouches[0].clientY - startY;
                if (delta > 80) self.hideBoothCard();
            }, { passive: true });
        },

        /* -------------------------------------------------------------------
         * Vendor Favorites  (localStorage key: 'oha_favorite_vendors')
         * Shared with the PWA's favorite vendors system.
         * ----------------------------------------------------------------- */

        getFavoriteVendors: function () {
            try { return JSON.parse(localStorage.getItem('oha_favorite_vendors') || '{}'); }
            catch (e) { return {}; }
        },

        saveFavoriteVendors: function (favorites) {
            try { localStorage.setItem('oha_favorite_vendors', JSON.stringify(favorites)); }
            catch (e) {}
        },

        isFavoriteVendor: function (vendorHubId) {
            return !!this.getFavoriteVendors()[String(vendorHubId)];
        },

        toggleFavoriteVendor: function (b, $btn) {
            var vendorHubId = String(b.vendorHubId || b.vendor_hub_id || 0);
            if (!vendorHubId || vendorHubId === '0') return;
            var favorites = this.getFavoriteVendors();
            if (favorites[vendorHubId]) {
                delete favorites[vendorHubId];
                $btn.text('Save to My Vendors').removeClass('active')
                    .attr('aria-label', 'Save to My Vendors');
            } else {
                favorites[vendorHubId] = {
                    id:    parseInt(vendorHubId, 10),
                    name:  b.vendorName  || b.vendor_name  || b.title || '',
                    logo:  b.vendorLogo  || b.vendor_logo  || b.thumbnail || '',
                    booth: b.number || '',
                };
                $btn.text('Remove from Saved').addClass('active')
                    .attr('aria-label', 'Remove from saved vendors');
            }
            this.saveFavoriteVendors(favorites);
        },

        /* -------------------------------------------------------------------
         * Helpers
         * ----------------------------------------------------------------- */

        hideLoading: function () {
            this.$wrapper.find('.oha-conv-loading').hide();
        },
    };

    /* -----------------------------------------------------------------------
     * Utility
     * --------------------------------------------------------------------- */

    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /* -----------------------------------------------------------------------
     * Boot on DOM ready
     * --------------------------------------------------------------------- */

    $(document).ready(function () {
        OHAConvention.initAll();
    });

    // Expose for external access (deep linking from other scripts)
    window.OHAConvention = OHAConvention;

}(jQuery));
