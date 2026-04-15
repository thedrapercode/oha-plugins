/**
 * OHA Schedule Hub - Frontend JavaScript
 * View switcher · Live search · Collapsible day sections · Class detail modal
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        var $container = $('.oha-schedule-container').first();
        var mapUrl     = $container.data('map-url') || '';

        // --------------------------------------------------
        // View switcher
        // --------------------------------------------------
        function switchView(view) {
            $('.oha-view-btn').removeClass('active');
            $('.oha-view-btn[data-view="' + view + '"]').addClass('active');
            $('.oha-timeline-view, .oha-grid-view, .oha-list-view, .oha-compact-view').hide();
            $('.oha-' + view + '-view').show();
        }

        // Direct binding on each button (not document delegation)
        $('.oha-view-btn').each(function() {
            $(this).on('click', function() {
                switchView($(this).data('view'));
            });
        });

        var initialView = $container.attr('data-view') || 'timeline';
        switchView(initialView);

        // --------------------------------------------------
        // Collapsible day sections
        // Direct binding — avoids PWA blocking document delegation
        // --------------------------------------------------
        $('.oha-day-title').each(function() {
            var $btn    = $(this);
            var dayKey  = $btn.data('day-key');

            // Restore saved collapse state
            try {
                if (sessionStorage.getItem('oha_day_' + dayKey) === '1') {
                    $btn.closest('.oha-day-section').addClass('is-collapsed');
                    $btn.attr('aria-expanded', 'false');
                }
            } catch(e) {}

            // Direct click binding
            $btn.on('click', function() {
                var $section       = $btn.closest('.oha-day-section');
                var isNowCollapsed = !$section.hasClass('is-collapsed');
                $section.toggleClass('is-collapsed', isNowCollapsed);
                $btn.attr('aria-expanded', isNowCollapsed ? 'false' : 'true');
                try { sessionStorage.setItem('oha_day_' + dayKey, isNowCollapsed ? '1' : '0'); } catch(e) {}
            });
        });

        // --------------------------------------------------
        // Live search + dropdown filters (unified)
        // --------------------------------------------------
        function applyFilters() {
            var term            = ($('#oha-search-input').val()     || '').toLowerCase().trim();
            var filterTrack     = ($('#oha-filter-track').val()     || '').toLowerCase();
            var filterInstructor= ($('#oha-filter-instructor').val()|| '').toLowerCase();
            var filterDay       = ($('#oha-filter-day').val()       || '').toLowerCase();
            var hasFilter       = term || filterTrack || filterInstructor || filterDay;

            // Show/hide individual day sections by day filter first
            $('.oha-day-section').each(function() {
                var daySlug = ($(this).data('day') || '').toLowerCase();
                if (filterDay && daySlug !== filterDay) {
                    $(this).hide();
                } else {
                    $(this).show();
                }
            });

            // Show/hide individual items by search + track + instructor
            $('.oha-class-item, .oha-list-item, .oha-class-card, .oha-compact-item').each(function() {
                var $item = $(this);
                var hide  = false;

                // Text search
                if (term) {
                    var haystack = [
                        $item.find('.oha-class-title, .oha-card-title, .oha-compact-title').text(),
                        $item.data('instructors') || $item.data('event-speaker') || '',
                        $item.find('.oha-class-description, .oha-card-description').text(),
                        $item.find('.oha-class-room, .oha-card-room').text(),
                        $item.data('track-name') || $item.data('track') || '',
                    ].join(' ').toLowerCase();
                    if (haystack.indexOf(term) === -1) hide = true;
                }

                // Track filter
                if (!hide && filterTrack) {
                    var itemTrack = ($item.data('track') || '').toLowerCase();
                    if (itemTrack !== filterTrack) hide = true;
                }

                // Instructor filter
                if (!hide && filterInstructor) {
                    var itemInstructor = ($item.data('instructor') || '').toLowerCase();
                    if (itemInstructor !== filterInstructor) hide = true;
                }

                $item.toggle(!hide);
            });

            // Hide day sections that have no visible items (after item filtering)
            if (term || filterTrack || filterInstructor) {
                $('.oha-day-section:visible').each(function() {
                    var visible = $(this).find(
                        '.oha-class-item:visible, .oha-list-item:visible, .oha-class-card:visible, .oha-compact-item:visible'
                    ).length;
                    if (visible === 0) $(this).hide();
                });
            }
        }

        $('#oha-search-input').on('input', applyFilters);
        $('#oha-search-btn').on('click', applyFilters);
        $('#oha-search-input').on('keypress', function(e) {
            if (e.which === 13) applyFilters();
        });
        $('#oha-filter-track, #oha-filter-instructor, #oha-filter-day').on('change', applyFilters);
        $('#oha-clear-filters').on('click', function() {
            $('#oha-search-input').val('');
            $('#oha-filter-track, #oha-filter-instructor, #oha-filter-day').val('');
            applyFilters();
        });

        // --------------------------------------------------
        // Print button
        // --------------------------------------------------
        $('#oha-print-btn').on('click', function() {
            window.print();
        });

        // --------------------------------------------------
        // Class Detail Modal
        // Appended INSIDE $container so CSS variables inherit.
        // position:fixed means it still covers the full viewport.
        // --------------------------------------------------
        var $overlay = $(
            '<div id="oha-modal-overlay" role="dialog" aria-modal="true" aria-label="Class Details">' +
                '<div id="oha-class-modal">' +
                    '<button id="oha-modal-close" type="button" aria-label="Close">&times;</button>' +
                    '<div id="oha-modal-image-wrap" class="is-empty"><img id="oha-modal-image" src="" alt=""/></div>' +
                    '<div id="oha-modal-content">' +
                        '<div class="oha-modal-header">' +
                            '<span class="oha-modal-time"></span>' +
                            '<span class="oha-modal-track"></span>' +
                            '<span class="oha-modal-promoted" style="display:none">&#9733; Featured</span>' +
                        '</div>' +
                        '<h2 id="oha-modal-title"></h2>' +
                        '<div class="oha-modal-meta"></div>' +
                        '<div class="oha-modal-description"></div>' +
                        '<div class="oha-modal-actions"></div>' +
                    '</div>' +
                '</div>' +
            '</div>'
        );
        // Append inside container → CSS vars work; position:fixed still covers viewport
        $container.append($overlay);

        function openModal($card) {
            var title       = $card.data('event-title')    || $card.find('.oha-class-title').text();
            var timeDisplay = $card.data('time-display')   || $card.data('event-time') || '';
            var trackName   = $card.data('track-name')     || '';
            var instructors = $card.data('instructors')    || $card.data('event-speaker') || '';
            var room        = $card.data('event-room')     || '';
            var level       = $card.data('level')          || '';
            var imageUrl    = $card.data('image')          || '';
            var materials   = $card.data('materials')      || '';
            var headshot1   = $card.data('headshot-1')     || '';
            var headshot2   = $card.data('headshot-2')     || '';
            var promoted    = ($card.data('event-promoted') === '1' || $card.data('event-promoted') === 1);

            // Full description from hidden div (preserves HTML/markup)
            var $fullDesc   = $card.find('.oha-full-description');
            var description = $fullDesc.length ? $fullDesc.html().trim() : '';
            if (!description) {
                description = $card.find('.oha-class-description').html() || '';
            }

            // Populate
            $overlay.find('.oha-modal-time').text(timeDisplay);
            $overlay.find('.oha-modal-track').text(trackName).toggle(!!trackName);
            $overlay.find('.oha-modal-promoted').toggle(promoted);
            $overlay.find('#oha-modal-title').text(title);

            // Description + Materials
            var fullContent = description || '<em style="opacity:.6">No description available.</em>';
            if (materials) {
                fullContent += '<div class="oha-modal-materials">' +
                    '<div class="oha-modal-materials-label">' +
                    '<svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" style="flex-shrink:0">' +
                    '<path d="M1 2.828c.885-.37 2.154-.769 3.388-.893 1.33-.134 2.458.063 3.112.752v9.746c-.935-.53-2.12-.603-3.213-.493-1.18.12-2.37.461-3.287.811V2.828zm7.5-.141c.654-.689 1.782-.886 3.112-.752 1.234.124 2.503.523 3.388.893v9.923c-.918-.35-2.107-.692-3.287-.81-1.094-.111-2.278-.039-3.213.492V2.687zM8 1.783C7.015.936 5.587.81 4.287.94c-1.514.153-3.042.672-3.994 1.105A.5.5 0 0 0 0 2.5v11a.5.5 0 0 0 .707.455c.882-.4 2.303-.881 3.68-1.02 1.409-.142 2.59.087 3.223.877a.5.5 0 0 0 .78 0c.633-.79 1.814-1.019 3.224-.877 1.376.139 2.797.62 3.68 1.02A.5.5 0 0 0 16 13.5v-11a.5.5 0 0 0-.293-.455c-.952-.433-2.48-.952-3.994-1.105C10.413.809 8.985.936 8 1.783z"/>' +
                    '</svg>' +
                    '<strong>Materials Needed</strong>' +
                    '</div>' +
                    '<div class="oha-modal-materials-body">' + escHtml(materials).replace(/\n/g, '<br>') + '</div>' +
                    '</div>';
            }
            $overlay.find('.oha-modal-description').html(fullContent);

            // Meta rows
            var metaHtml = '';

            // Instructor headshots row (if any)
            if (headshot1 || headshot2) {
                metaHtml += '<div class="oha-modal-meta-row oha-modal-headshots-row">';
                if (headshot1) {
                    metaHtml += '<img src="' + escAttr(headshot1) + '" class="oha-modal-headshot-circle" alt="Instructor">';
                }
                if (headshot2) {
                    metaHtml += '<img src="' + escAttr(headshot2) + '" class="oha-modal-headshot-circle" alt="Instructor">';
                }
                if (instructors) {
                    metaHtml += '<span class="oha-modal-headshot-name"><strong>' + escHtml(instructors) + '</strong></span>';
                }
                metaHtml += '</div>';
            } else if (instructors) {
                metaHtml += '<div class="oha-modal-meta-row">' +
                    '<svg viewBox="0 0 16 16"><path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/></svg>' +
                    '<span><strong>' + escHtml(instructors) + '</strong></span></div>';
            }

            if (room) {
                metaHtml += '<div class="oha-modal-meta-row">' +
                    '<svg viewBox="0 0 16 16"><path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/></svg>' +
                    '<span>' + escHtml(room) + '</span></div>';
            }
            if (level) {
                var levelLabel = level.charAt(0).toUpperCase() + level.slice(1);
                metaHtml += '<div class="oha-modal-meta-row">' +
                    '<svg viewBox="0 0 16 16"><path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/></svg>' +
                    '<span class="oha-modal-level-badge level-' + escAttr(level) + '">' + escHtml(levelLabel) + '</span></div>';
            }
            $overlay.find('.oha-modal-meta').html(metaHtml);

            // Image
            var $imgWrap = $overlay.find('#oha-modal-image-wrap');
            if (imageUrl) {
                $overlay.find('#oha-modal-image').attr({ src: imageUrl, alt: title }).show();
                $imgWrap.removeClass('is-empty');
            } else {
                $overlay.find('#oha-modal-image').attr({ src: '', alt: '' }).hide();
                $imgWrap.addClass('is-empty');
            }

            // Map button
            var $actions = $overlay.find('.oha-modal-actions').empty();
            var currentMapUrl = mapUrl || $container.data('map-url') || '';
            if (currentMapUrl) {
                $actions.append(
                    '<a class="oha-modal-map-btn" href="' + currentMapUrl + '">' +
                    '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">' +
                    '<path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/>' +
                    '</svg> Find on Map</a>'
                );
            }

            $overlay.addClass('is-open');
            $overlay.find('#oha-class-modal').scrollTop(0);
        }

        function closeModal() {
            $overlay.removeClass('is-open');
        }

        // --------------------------------------------------
        // Card tap → open modal
        // Uses BOTH click (desktop) and touchend (mobile)
        // with a scroll-movement guard so scrolling doesn't open the modal.
        // Direct binding on each card — not document delegation.
        // --------------------------------------------------
        function isExcludedTarget(e) {
            // Don't open modal if the tap was on a button or link (PWA favorite/calendar buttons)
            return $(e.target).is('button, a') || $(e.target).closest('button, a').length > 0;
        }

        $('.oha-timeline-item').each(function() {
            var $card     = $(this);
            var touchMoved = false;

            $card.on('touchstart', function() {
                touchMoved = false;
            });
            $card.on('touchmove', function() {
                touchMoved = true;
            });
            $card.on('touchend', function(e) {
                if (touchMoved) return;        // user was scrolling
                if (isExcludedTarget(e)) return;
                e.preventDefault();            // prevent ghost click
                openModal($card);
            });
            // Desktop fallback
            $card.on('click', function(e) {
                if (isExcludedTarget(e)) return;
                openModal($card);
            });
            // Keyboard accessibility
            $card.on('keydown', function(e) {
                if (e.which === 13 || e.which === 32) {
                    e.preventDefault();
                    openModal($card);
                }
            });
        });

        // Close controls — direct binding
        $overlay.find('#oha-modal-close').on('click', closeModal);

        $overlay.on('touchend click', function(e) {
            if ($(e.target).is('#oha-modal-overlay')) closeModal();
        });

        $(document).on('keydown', function(e) {
            if (e.which === 27 && $overlay.hasClass('is-open')) closeModal();
        });

        // --------------------------------------------------
        // Helpers
        // --------------------------------------------------
        function escHtml(str) {
            return $('<div>').text(str || '').html();
        }
        function escAttr(str) {
            return (str || '').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        // --------------------------------------------------
        // PWA: cache schedule data in localStorage
        // --------------------------------------------------
        try {
            var scheduleData = { classes: [], lastUpdated: new Date().toISOString() };
            $('.oha-timeline-item').each(function() {
                var $item = $(this);
                scheduleData.classes.push({
                    id:         $item.data('class-id'),
                    title:      $item.data('event-title'),
                    instructor: $item.data('instructors'),
                    track:      $item.data('track'),
                    time:       $item.data('event-time')
                });
            });
            localStorage.setItem('oha_schedule_cache', JSON.stringify(scheduleData));
        } catch(e) {}

        // --------------------------------------------------
        // Offline indicator — small corner icon (does not cover content)
        // --------------------------------------------------
        function showOfflineBanner() {
            if ($('#oha-offline-icon').length === 0) {
                $('body').append(
                    '<div id="oha-offline-icon" title="Offline — viewing cached schedule" style="' +
                    'position:fixed;top:8px;right:8px;z-index:200001;' +
                    'background:rgba(26,15,15,0.92);color:#fbbf24;' +
                    'border:1px solid #e07820;border-radius:16px;' +
                    'padding:4px 10px 4px 7px;font-size:12px;font-weight:600;' +
                    'display:flex;align-items:center;gap:5px;' +
                    'box-shadow:0 2px 8px rgba(0,0,0,0.5);pointer-events:none;">' +
                    '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">' +
                    '<line x1="2" y1="2" x2="22" y2="22"/>' +
                    '<path d="M8.5 16.5a5 5 0 0 1 7 0"/>' +
                    '<path d="M5 12.5a9 9 0 0 1 5.17-2.29"/>' +
                    '<path d="M1.42 9a16 16 0 0 1 3.7-2.4"/>' +
                    '<path d="M22 9a16 16 0 0 0-11-4"/>' +
                    '<circle cx="12" cy="20" r="1" fill="currentColor" stroke="none"/>' +
                    '</svg>' +
                    'Offline</div>'
                );
            }
        }
        function hideOfflineBanner() {
            $('#oha-offline-icon').fadeOut(400, function() { $(this).remove(); });
        }

        window.addEventListener('offline',  showOfflineBanner);
        window.addEventListener('online',   function() { hideOfflineBanner(); location.reload(); });
        if (!navigator.onLine) { showOfflineBanner(); }

        setInterval(function() {
            if (navigator.onLine) { location.reload(); }
        }, 5 * 60 * 1000);

    });

})(jQuery);
