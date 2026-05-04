/**
 * OHA PWA Schedule JavaScript (Preserved from v3.3.0)
 * 
 * Handles schedule display, favorites, and filtering
 * Maintained for backward compatibility with Actor Passport integration
 * 
 * @package OHA_PWA_Core
 * @version 3.4.0
 */

(function($) {
    'use strict';

    // Main Schedule Manager
    window.OHASchedule = {
        
        // Configuration
        config: {
            scheduleData: null,
            activeDay: '2025-05-09',
            activeTrack: 'all',
            favorites: [],
            searchTerm: ''
        },

        // Initialize schedule system
        init: function() {
            console.log('OHA Schedule v3.4.0 (preserved) initializing...');
            
            this.bindEvents();
            this.loadScheduleData();
        },

        // Bind event handlers
        bindEvents: function() {
            
            // Schedule navigation
            $(document).on('click', '.day-filter-btn', this.handleDayFilter.bind(this));
            $(document).on('click', '.track-filter-btn', this.handleTrackFilter.bind(this));
            
            // Favorites
            $(document).on('click', '.favorite-btn', this.handleFavoriteToggle.bind(this));
            
            // Search
            $(document).on('input', '#schedule-search', this.handleSearch.bind(this));
            
            // Export
            $(document).on('click', '#export-schedule', this.exportSchedule.bind(this));
            
            // Session details
            $(document).on('click', '.session-item', this.showSessionDetails.bind(this));
        },

        // Load schedule data
        loadScheduleData: function() {
            
            $('#oha-schedule-container').html('<div class="oha-loading"><div class="oha-spinner"></div><p>Loading schedule...</p></div>');
            
            $.ajax({
                url: ohaActorPassport.ajax_url,
                type: 'GET',
                data: {
                    action: 'oha_get_schedule_data',
                    nonce: ohaActorPassport.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.config.scheduleData = response.data;
                        this.config.favorites = response.data.user_favorites || [];
                        this.displaySchedule();
                    } else {
                        this.showError('Failed to load schedule: ' + response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError('Network error loading schedule');
                }.bind(this)
            });
        },

        // Display schedule
        displaySchedule: function() {
            if (!this.config.scheduleData) return;

            var html = `
                <div class="schedule-controls">
                    <div class="schedule-filters">
                        <div class="day-filters">
                            ${this.renderDayFilters()}
                        </div>
                        <div class="track-filters">
                            ${this.renderTrackFilters()}
                        </div>
                        <div class="search-filter">
                            <input type="text" id="schedule-search" placeholder="Search sessions..." value="${this.config.searchTerm}">
                        </div>
                    </div>
                    <div class="schedule-actions">
                        <button id="export-schedule" class="oha-btn-secondary">📁 Export CSV</button>
                    </div>
                </div>
                <div class="schedule-content">
                    ${this.renderSessions()}
                </div>
            `;
            
            $('#oha-schedule-container').html(html);
        },

        // Render day filters
        renderDayFilters: function() {
            var html = '';
            
            this.config.scheduleData.days.forEach(function(day) {
                var activeClass = (day.date === this.config.activeDay) ? 'active' : '';
                html += `<button class="day-filter-btn ${activeClass}" data-day="${day.date}">${day.name}</button>`;
            }.bind(this));
            
            return html;
        },

        // Render track filters
        renderTrackFilters: function() {
            var html = `<button class="track-filter-btn ${this.config.activeTrack === 'all' ? 'active' : ''}" data-track="all">All Tracks</button>`;
            
            Object.keys(this.config.scheduleData.tracks).forEach(function(trackKey) {
                var trackName = this.config.scheduleData.tracks[trackKey];
                var activeClass = (trackKey === this.config.activeTrack) ? 'active' : '';
                html += `<button class="track-filter-btn ${activeClass}" data-track="${trackKey}">${trackName}</button>`;
            }.bind(this));
            
            return html;
        },

        // Render sessions
        renderSessions: function() {
            var activeDay = this.config.scheduleData.days.find(day => day.date === this.config.activeDay);
            
            if (!activeDay || !activeDay.sessions) {
                return '<div class="no-sessions">No sessions found for this day</div>';
            }

            var filteredSessions = this.filterSessions(activeDay.sessions);
            
            if (filteredSessions.length === 0) {
                return '<div class="no-sessions">No sessions match your current filters</div>';
            }

            var html = '<div class="sessions-grid">';
            
            filteredSessions.forEach(function(session) {
                var isFavorited = this.config.favorites.includes(session.id);
                var favoriteIcon = isFavorited ? '⭐' : '☆';
                
                html += `
                    <div class="session-item" data-session-id="${session.id}">
                        <div class="session-header">
                            <div class="session-time">${session.start_time} - ${session.end_time}</div>
                            <button class="favorite-btn" data-session-id="${session.id}" data-favorited="${isFavorited}">
                                ${favoriteIcon}
                            </button>
                        </div>
                        <h4 class="session-title">${this.escapeHtml(session.title)}</h4>
                        <div class="session-meta">
                            <span class="session-track track-${session.track}">${this.getTrackName(session.track)}</span>
                            ${session.instructor ? `<span class="session-instructor">👨‍🏫 ${this.escapeHtml(session.instructor)}</span>` : ''}
                            ${session.room ? `<span class="session-room">📍 ${this.escapeHtml(session.room)}</span>` : ''}
                        </div>
                        <div class="session-description">
                            ${this.escapeHtml(session.description)}
                        </div>
                        ${session.level ? `<div class="session-level">Level: ${this.escapeHtml(session.level)}</div>` : ''}
                    </div>
                `;
            }.bind(this));
            
            html += '</div>';
            return html;
        },

        // Filter sessions based on current filters
        filterSessions: function(sessions) {
            return sessions.filter(function(session) {
                
                // Track filter
                if (this.config.activeTrack !== 'all' && session.track !== this.config.activeTrack) {
                    return false;
                }
                
                // Search filter
                if (this.config.searchTerm) {
                    var searchLower = this.config.searchTerm.toLowerCase();
                    var searchable = [
                        session.title,
                        session.description,
                        session.instructor,
                        session.room
                    ].join(' ').toLowerCase();
                    
                    if (searchable.indexOf(searchLower) === -1) {
                        return false;
                    }
                }
                
                return true;
            }.bind(this));
        },

        // Handle day filter clicks
        handleDayFilter: function(e) {
            var day = $(e.currentTarget).data('day');
            this.config.activeDay = day;
            
            $('.day-filter-btn').removeClass('active');
            $(e.currentTarget).addClass('active');
            
            this.displaySchedule();
        },

        // Handle track filter clicks
        handleTrackFilter: function(e) {
            var track = $(e.currentTarget).data('track');
            this.config.activeTrack = track;
            
            $('.track-filter-btn').removeClass('active');
            $(e.currentTarget).addClass('active');
            
            this.displaySchedule();
        },

        // Handle search input
        handleSearch: function(e) {
            this.config.searchTerm = $(e.currentTarget).val();
            
            // Debounce search
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(function() {
                this.displaySchedule();
            }.bind(this), 300);
        },

        // Handle favorite toggle
        handleFavoriteToggle: function(e) {
            e.stopPropagation();
            
            if (!ohaActorPassport.is_logged_in) {
                this.showError('Please log in to save favorites');
                return;
            }

            var $btn = $(e.currentTarget);
            var sessionId = parseInt($btn.data('session-id'));
            var isFavorited = $btn.data('favorited');
            
            $.ajax({
                url: ohaActorPassport.ajax_url,
                type: 'POST',
                data: {
                    action: 'oha_toggle_favorite',
                    session_id: sessionId,
                    nonce: ohaActorPassport.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var newState = response.data.is_favorited;
                        var newIcon = newState ? '⭐' : '☆';
                        
                        $btn.html(newIcon).data('favorited', newState);
                        
                        // Update favorites array
                        if (newState) {
                            if (!this.config.favorites.includes(sessionId)) {
                                this.config.favorites.push(sessionId);
                            }
                        } else {
                            this.config.favorites = this.config.favorites.filter(id => id !== sessionId);
                        }
                        
                        this.showSuccess(response.data.action === 'added' ? 'Session added to favorites' : 'Session removed from favorites');
                        
                    } else {
                        this.showError('Failed to update favorite: ' + response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError('Network error updating favorite');
                }.bind(this)
            });
        },

        // Show session details
        showSessionDetails: function(e) {
            if ($(e.target).hasClass('favorite-btn')) return; // Don't trigger on favorite button
            
            var sessionId = parseInt($(e.currentTarget).data('session-id'));
            var session = this.findSession(sessionId);
            
            if (!session) return;
            
            var modalHtml = `
                <div class="session-modal-overlay">
                    <div class="session-modal">
                        <div class="session-modal-header">
                            <h3>${this.escapeHtml(session.title)}</h3>
                            <button class="session-modal-close">×</button>
                        </div>
                        <div class="session-modal-content">
                            <div class="session-detail-meta">
                                <p><strong>Time:</strong> ${session.start_time} - ${session.end_time}</p>
                                <p><strong>Track:</strong> ${this.getTrackName(session.track)}</p>
                                ${session.instructor ? `<p><strong>Instructor:</strong> ${this.escapeHtml(session.instructor)}</p>` : ''}
                                ${session.room ? `<p><strong>Room:</strong> ${this.escapeHtml(session.room)}</p>` : ''}
                                ${session.capacity ? `<p><strong>Capacity:</strong> ${session.capacity} people</p>` : ''}
                                ${session.level ? `<p><strong>Level:</strong> ${this.escapeHtml(session.level)}</p>` : ''}
                            </div>
                            <div class="session-detail-description">
                                <h4>Description</h4>
                                <p>${this.escapeHtml(session.description)}</p>
                            </div>
                            ${session.materials ? `
                                <div class="session-detail-materials">
                                    <h4>Materials Needed</h4>
                                    <p>${this.escapeHtml(session.materials)}</p>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            
            // Bind close events
            $(document).on('click', '.session-modal-close, .session-modal-overlay', function(e) {
                if (e.target === this) {
                    $('.session-modal-overlay').remove();
                }
            });
        },

        // Export schedule
        exportSchedule: function() {
            
            $.ajax({
                url: ohaActorPassport.ajax_url,
                type: 'POST',
                data: {
                    action: 'oha_export_schedule_csv',
                    nonce: ohaActorPassport.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Create download link
                        var blob = new Blob([response.data.csv_data], {type: 'text/csv'});
                        var url = window.URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = url;
                        a.download = response.data.filename;
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        
                        this.showSuccess('Schedule exported successfully!');
                    } else {
                        this.showError('Failed to export schedule: ' + response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError('Network error exporting schedule');
                }.bind(this)
            });
        },

        // Helper functions
        findSession: function(sessionId) {
            for (var day of this.config.scheduleData.days) {
                for (var session of day.sessions) {
                    if (session.id === sessionId) {
                        return session;
                    }
                }
            }
            return null;
        },

        getTrackName: function(trackKey) {
            return this.config.scheduleData.tracks[trackKey] || trackKey;
        },

        escapeHtml: function(text) {
            if (!text) return '';
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        showSuccess: function(message) {
            this.showMessage(message, 'success');
        },

        showError: function(message) {
            this.showMessage(message, 'error');
        },

        showMessage: function(message, type) {
            var $msg = $('<div class="oha-message oha-message-' + type + '">' + this.escapeHtml(message) + '</div>');
            
            $('.oha-message').remove();
            $('body').append($msg);
            
            setTimeout(function() {
                $msg.fadeOut(300, function() {
                    $msg.remove();
                });
            }, 5000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if (typeof ohaActorPassport !== 'undefined') {
            window.OHASchedule.init();
        }
    });

})(jQuery);
