/**
 * OHA Convention Map - Visual Booth Placement Tool
 * Admin JS for the Booth Placement page (OHA Map > Booth Placement)
 *
 * Requires: ohaPlacementConfig (localized by render_placement_page())
 */
(function($) {
    'use strict';

    // Type-default colors for booth pins
    var TYPE_COLORS = {
        vendor:   '#ff6b35',
        food:     '#e67e22',
        restroom: '#3498db',
        'class':  '#9b59b6',
        stage:    '#e74c3c',
        info:     '#27ae60',
        exit:     '#2d3436',
        general:  '#7f8c8d'
    };

    var OHAPlacement = {

        config:          null,
        activeFloorId:   null,
        booths:          [],    // booths for the active floor
        selectedBoothId: null,
        isDragging:      false,
        dragPin:         null,
        dragStartX:      0,
        dragStartY:      0,
        vendorDebounce:  null,

        // -------------------------------------------------------
        // Init
        // -------------------------------------------------------

        init: function(config) {
            this.config        = config;
            this.activeFloorId = config.activeFloorId;
            this.booths        = config.booths || [];

            this.bindFloorTabs();
            this.bindCanvasClick();
            this.bindPanel();
            this.bindVendorSearch();
            this.renderPins();
        },

        // -------------------------------------------------------
        // Floor tabs
        // -------------------------------------------------------

        bindFloorTabs: function() {
            var self = this;
            $(document).on('click', '.oha-floor-tab', function() {
                var floorId = parseInt($(this).data('floor-id'), 10);
                if (floorId === self.activeFloorId) return;

                $('.oha-floor-tab').removeClass('active');
                $(this).addClass('active');

                self.activeFloorId = floorId;
                self.selectedBoothId = null;
                self.clearPanel();

                // Switch floor plan image
                var floor = self.getFloor(floorId);
                if (floor) {
                    var $img = $('#oha-placement-img');
                    if ($img.length) {
                        $img.attr('src', floor.image_url || '');
                    }
                }

                // Load booths for this floor
                self.loadBooths(floorId);
            });
        },

        getFloor: function(floorId) {
            for (var i = 0; i < this.config.floors.length; i++) {
                if (this.config.floors[i].id == floorId) return this.config.floors[i];
            }
            return null;
        },

        // -------------------------------------------------------
        // Load booths via AJAX
        // -------------------------------------------------------

        loadBooths: function(floorId) {
            var self = this;
            $.post(this.config.ajaxUrl, {
                action:   'oha_get_convention_booths',
                nonce:    this.config.nonce,
                floor_id: floorId,
                year:     this.config.activeYear
            }, function(resp) {
                if (resp.success) {
                    self.booths = resp.data || [];
                    self.renderPins();
                }
            });
        },

        // -------------------------------------------------------
        // Render all pins for the active floor
        // -------------------------------------------------------

        renderPins: function() {
            // Remove existing pins
            $('#oha-placement-canvas .oha-pin').remove();

            var self = this;
            $.each(this.booths, function(i, booth) {
                if (booth.floor == self.activeFloorId) {
                    self.createPin(booth);
                }
            });
        },

        createPin: function(booth) {
            var color = booth.color || TYPE_COLORS[booth.type] || TYPE_COLORS.general;
            var label = booth.vendor_name || booth.title || ('Booth ' + (booth.number || booth.id));
            var number = booth.number || booth.id;

            var $pin = $('<div class="oha-pin" draggable="false">')
                .attr('data-id', booth.id)
                .css({
                    left: booth.x + '%',
                    top:  booth.y + '%',
                    '--pin-color': color
                });

            var $dot   = $('<div class="oha-pin-dot">').text(number);
            var $lbl   = $('<div class="oha-pin-label">').text(label);
            $pin.append($dot).append($lbl);

            // Click to select
            var self = this;
            $pin.on('mousedown touchstart', function(e) {
                if (e.type === 'mousedown' && e.button !== 0) return;
                e.stopPropagation();
                self.startDrag(e, $pin[0], booth);
            });

            $('#oha-placement-canvas').append($pin);

            // Highlight if already selected
            if (this.selectedBoothId === booth.id) {
                $pin.addClass('selected');
            }
        },

        updatePin: function(booth) {
            var $pin = $('#oha-placement-canvas .oha-pin[data-id="' + booth.id + '"]');
            if (!$pin.length) {
                this.createPin(booth);
                return;
            }
            var color  = booth.color || TYPE_COLORS[booth.type] || TYPE_COLORS.general;
            var label  = booth.vendor_name || booth.title || ('Booth ' + (booth.number || booth.id));
            var number = booth.number || booth.id;
            $pin.css({ left: booth.x + '%', top: booth.y + '%', '--pin-color': color });
            $pin.find('.oha-pin-dot').text(number);
            $pin.find('.oha-pin-label').text(label);
        },

        // -------------------------------------------------------
        // Canvas click — place new booth
        // -------------------------------------------------------

        bindCanvasClick: function() {
            var self = this;
            $('#oha-placement-canvas').on('click', function(e) {
                // Ignore if we just finished dragging
                if (self.isDragging) return;
                // Ignore clicks on pins
                if ($(e.target).closest('.oha-pin').length) return;

                var coords = self.coordsFromEvent(e);
                self.selectedBoothId = null;
                $('.oha-pin').removeClass('selected');
                self.openNewPanel(coords.x, coords.y);
            });
        },

        coordsFromEvent: function(e) {
            var $canvas = $('#oha-placement-canvas');
            var offset  = $canvas.offset();
            var w       = $canvas.width();
            var h       = $canvas.height();

            // Use pageX/pageY (document-relative) so scroll position doesn't corrupt coords.
            // On touchend, touches[] is empty — fall back to changedTouches.
            var pageX, pageY;
            var orig = e.originalEvent || e;
            if (orig.touches && orig.touches.length) {
                pageX = orig.touches[0].pageX;
                pageY = orig.touches[0].pageY;
            } else if (orig.changedTouches && orig.changedTouches.length) {
                pageX = orig.changedTouches[0].pageX;
                pageY = orig.changedTouches[0].pageY;
            } else {
                pageX = e.pageX != null ? e.pageX : (orig.pageX || 0);
                pageY = e.pageY != null ? e.pageY : (orig.pageY || 0);
            }

            var x = ((pageX - offset.left) / w * 100).toFixed(2);
            var y = ((pageY - offset.top)  / h * 100).toFixed(2);

            x = Math.max(0, Math.min(100, parseFloat(x)));
            y = Math.max(0, Math.min(100, parseFloat(y)));

            return { x: x, y: y };
        },

        // -------------------------------------------------------
        // Drag to reposition
        // -------------------------------------------------------

        startDrag: function(e, pinEl, booth) {
            var self = this;

            // If just a short tap, treat as select
            var moved = false;
            var startTime = Date.now();

            var $pin = $(pinEl);

            var onMove = function(ev) {
                ev.preventDefault();
                moved = true;
                self.isDragging = true;
                $pin.addClass('is-dragging');

                var coords = self.coordsFromEvent(ev);
                $pin.css({ left: coords.x + '%', top: coords.y + '%' });
            };

            var onUp = function(ev) {
                $(document).off('mousemove touchmove', onMove);
                $(document).off('mouseup touchend', onUp);

                var elapsed = Date.now() - startTime;

                if (!moved || elapsed < 150) {
                    // Treat as a click/tap — select the booth
                    self.isDragging = false;
                    $pin.removeClass('is-dragging');
                    self.selectBoothById(booth.id);
                } else {
                    // Drag ended — save new position
                    $pin.removeClass('is-dragging');
                    var coords = self.coordsFromEvent(ev);

                    // Update in-memory
                    var b = self.getBoothById(booth.id);
                    if (b) { b.x = coords.x; b.y = coords.y; }

                    // Auto-save
                    self.savePosition(booth.id, coords.x, coords.y, function() {
                        // Done — reset drag flag after a tick
                        setTimeout(function() { self.isDragging = false; }, 50);
                    });
                }
            };

            $(document).on('mousemove touchmove', onMove);
            $(document).on('mouseup touchend', onUp);
        },

        savePosition: function(boothId, x, y, callback) {
            var booth = this.getBoothById(boothId);
            if (!booth) { if (callback) callback(); return; }

            $.post(this.config.ajaxUrl, {
                action:   'oha_save_booth_placement',
                nonce:    this.config.nonce,
                booth_id: boothId,
                title:    booth.title || '',
                number:   booth.number || '',
                type:     booth.type || 'vendor',
                floor:    booth.floor || this.activeFloorId,
                x:        x,
                y:        y,
                width:    booth.width  || '',
                height:   booth.height || '',
                vendor_hub_id: booth.vendor_hub_id || '',
                hours:    booth.hours  || '',
                status:   booth.status || 'open',
                color:    booth.color  || '',
                year:     this.config.activeYear
            }, function(resp) {
                if (callback) callback(resp);
            });
        },

        getBoothById: function(id) {
            for (var i = 0; i < this.booths.length; i++) {
                if (this.booths[i].id == id) return this.booths[i];
            }
            return null;
        },

        // -------------------------------------------------------
        // Panel: select existing booth
        // -------------------------------------------------------

        selectBoothById: function(id) {
            this.selectedBoothId = id;
            var booth = this.getBoothById(id);

            $('.oha-pin').removeClass('selected');
            $('#oha-placement-canvas .oha-pin[data-id="' + id + '"]').addClass('selected');

            if (booth) this.openEditPanel(booth);
        },

        // -------------------------------------------------------
        // Panel open/close
        // -------------------------------------------------------

        clearPanel: function() {
            $('#oha-panel-form').hide();
            $('#oha-panel-empty').show();
            this.selectedBoothId = null;
            $('.oha-pin').removeClass('selected');
        },

        openNewPanel: function(x, y) {
            $('#oha-panel-title').text('New Booth');
            $('#oha-form-booth-id').val(0);
            $('#oha-form-x').val(x);
            $('#oha-form-y').val(y);
            $('#oha-form-number').val('');
            $('#oha-form-type').val('vendor');
            $('#oha-form-status').val('open');
            $('#oha-form-hours').val('');
            $('#oha-form-color').val('');
            this.clearVendorSelection();
            $('#oha-panel-delete').hide();
            $('#oha-panel-saving').hide();
            $('#oha-panel-empty').hide();
            $('#oha-panel-form').show();
            $('#oha-form-number').focus();
            this.updateColorPickerDisplay('');
        },

        openEditPanel: function(booth) {
            $('#oha-panel-title').text('Edit Booth ' + (booth.number || booth.id));
            $('#oha-form-booth-id').val(booth.id);
            $('#oha-form-x').val(booth.x);
            $('#oha-form-y').val(booth.y);
            $('#oha-form-number').val(booth.number || '');
            $('#oha-form-type').val(booth.type || 'vendor');
            $('#oha-form-status').val(booth.status || 'open');
            $('#oha-form-hours').val(booth.hours || '');
            $('#oha-form-color').val(booth.color || '');
            this.updateColorPickerDisplay(booth.color || '');

            // Vendor
            if (booth.vendor_hub_id && booth.vendor_name) {
                this.setVendorSelection(booth.vendor_hub_id, booth.vendor_name, booth.vendor_status || '');
            } else {
                this.clearVendorSelection();
                $('#oha-form-vendor-id').val('');
            }

            $('#oha-panel-delete').show();
            $('#oha-panel-saving').hide();
            $('#oha-panel-empty').hide();
            $('#oha-panel-form').show();
        },

        updateColorPickerDisplay: function(color) {
            // Update wp-color-picker widget if present
            var $input = $('#oha-form-color');
            if ($input.hasClass('wp-color-picker') || $input.next('.wp-picker-container').length) {
                try { $input.wpColorPicker('color', color || ''); } catch(e) {}
            } else {
                $input.val(color || '');
            }
        },

        // -------------------------------------------------------
        // Panel save + delete
        // -------------------------------------------------------

        bindPanel: function() {
            var self = this;

            $(document).on('click', '#oha-panel-save', function() {
                self.savePanel();
            });

            $(document).on('click', '#oha-panel-delete', function() {
                if (!confirm('Delete this booth? This cannot be undone.')) return;
                self.deleteSelected();
            });
        },

        savePanel: function() {
            var self = this;

            var boothId  = parseInt($('#oha-form-booth-id').val(), 10) || 0;
            var number   = $('#oha-form-number').val().trim();
            var vendorId = $('#oha-form-vendor-id').val().trim();
            var type     = $('#oha-form-type').val();
            var status   = $('#oha-form-status').val();
            var hours    = $('#oha-form-hours').val().trim();
            var color    = this.getColorPickerValue();
            var x        = parseFloat($('#oha-form-x').val()) || 50;
            var y        = parseFloat($('#oha-form-y').val()) || 50;

            // Build title from vendor name or booth number
            var vendorName = $('#oha-vendor-name').text().trim();
            var title = vendorName || ('Booth ' + (number || boothId));

            $('#oha-panel-saving').show();
            $('#oha-panel-save').prop('disabled', true);

            $.post(this.config.ajaxUrl, {
                action:    'oha_save_booth_placement',
                nonce:     this.config.nonce,
                booth_id:  boothId,
                title:     title,
                number:    number,
                type:      type,
                floor:     this.activeFloorId,
                x:         x,
                y:         y,
                width:     '',
                height:    '',
                vendor_hub_id: vendorId,
                hours:     hours,
                status:    status,
                color:     color,
                year:      this.config.activeYear
            }, function(resp) {
                $('#oha-panel-saving').hide();
                $('#oha-panel-save').prop('disabled', false);

                if (!resp.success) {
                    alert('Save failed: ' + (resp.data || 'Unknown error'));
                    return;
                }

                var saved = resp.data;

                // Update in-memory booth list
                var existing = self.getBoothById(saved.id);
                if (existing) {
                    $.extend(existing, saved);
                } else {
                    self.booths.push(saved);
                }

                // Update form state to "editing" mode
                $('#oha-form-booth-id').val(saved.id);
                self.selectedBoothId = saved.id;
                $('#oha-panel-title').text('Edit Booth ' + (saved.number || saved.id));
                $('#oha-panel-delete').show();

                // Refresh pin
                self.updatePin(saved);
                $('.oha-pin').removeClass('selected');
                $('#oha-placement-canvas .oha-pin[data-id="' + saved.id + '"]').addClass('selected');
            });
        },

        getColorPickerValue: function() {
            var $input = $('#oha-form-color');
            // wp-color-picker stores the color in the input value
            return $input.val() || '';
        },

        deleteSelected: function() {
            var boothId = parseInt($('#oha-form-booth-id').val(), 10);
            if (!boothId) return;

            var self = this;
            $('#oha-panel-saving').text('Deleting...').show();
            $('#oha-panel-delete').prop('disabled', true);

            $.post(this.config.ajaxUrl, {
                action:   'oha_delete_booth_placement',
                nonce:    this.config.nonce,
                booth_id: boothId
            }, function(resp) {
                $('#oha-panel-saving').hide().text('Saving...');
                $('#oha-panel-delete').prop('disabled', false);

                if (!resp.success) {
                    alert('Delete failed: ' + (resp.data || 'Unknown error'));
                    return;
                }

                // Remove pin from DOM
                $('#oha-placement-canvas .oha-pin[data-id="' + boothId + '"]').remove();

                // Remove from in-memory list
                self.booths = self.booths.filter(function(b) { return b.id != boothId; });

                self.clearPanel();
            });
        },

        // -------------------------------------------------------
        // Vendor search
        // -------------------------------------------------------

        bindVendorSearch: function() {
            var self = this;

            $(document).on('input', '#oha-vendor-search', function() {
                clearTimeout(self.vendorDebounce);
                var query = $(this).val().trim();

                if (!query) {
                    $('#oha-vendor-results').hide().empty();
                    return;
                }

                self.vendorDebounce = setTimeout(function() {
                    self.searchVendors(query);
                }, 300);
            });

            // Hide results when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#oha-vendor-search, #oha-vendor-results').length) {
                    $('#oha-vendor-results').hide();
                }
            });

            // Clear vendor selection
            $(document).on('click', '#oha-vendor-clear', function(e) {
                e.preventDefault();
                self.clearVendorSelection();
                $('#oha-form-vendor-id').val('');
            });
        },

        searchVendors: function(query) {
            var self = this;
            $.post(this.config.ajaxUrl, {
                action: 'oha_get_vendor_hub_list',
                nonce:  this.config.nonce,
                search: query
            }, function(resp) {
                if (!resp.success) return;

                var $results = $('#oha-vendor-results').empty().show();

                if (!resp.data || !resp.data.length) {
                    $results.append('<div class="oha-vendor-item oha-vendor-none">No matching vendors found.</div>');
                    return;
                }

                $.each(resp.data, function(i, vendor) {
                    var statusColor = vendor.status === 'Paid' ? '#27ae60' : '#e67e22';
                    var $item = $('<div class="oha-vendor-item">')
                        .attr('data-id', vendor.id)
                        .attr('data-name', vendor.name)
                        .attr('data-status', vendor.status);

                    $item.html(
                        '<strong>' + self.escHtml(vendor.name) + '</strong>' +
                        '<span style="margin-left:8px;padding:1px 6px;background:' + statusColor + ';color:#fff;border-radius:3px;font-size:11px;">' + self.escHtml(vendor.status) + '</span>'
                    );

                    $item.on('click', function() {
                        self.setVendorSelection(vendor.id, vendor.name, vendor.status);
                        $('#oha-vendor-search').val('');
                        $('#oha-vendor-results').hide().empty();
                    });

                    $results.append($item);
                });
            });
        },

        setVendorSelection: function(id, name, status) {
            $('#oha-form-vendor-id').val(id);
            $('#oha-vendor-name').text(name);

            var statusColor = status === 'Paid' ? '#27ae60' : '#e67e22';
            $('#oha-vendor-status-badge')
                .text(status)
                .css({ background: statusColor, color: '#fff' });

            $('#oha-vendor-selected').show();
            $('#oha-vendor-search').hide();
        },

        clearVendorSelection: function() {
            $('#oha-form-vendor-id').val('');
            $('#oha-vendor-selected').hide();
            $('#oha-vendor-name').text('');
            $('#oha-vendor-status-badge').text('').css({ background: '', color: '' });
            $('#oha-vendor-search').show().val('');
            $('#oha-vendor-results').hide().empty();
        },

        // -------------------------------------------------------
        // Utility
        // -------------------------------------------------------

        escHtml: function(str) {
            return $('<div>').text(str).html();
        }
    };

    // Boot on DOM ready
    $(document).ready(function() {
        if (typeof ohaPlacementConfig === 'undefined') return;
        OHAPlacement.init(ohaPlacementConfig);
    });

})(jQuery);
