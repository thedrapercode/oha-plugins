/**
 * OHA Convention PWA - Favorites System
 * 
 * Handles favoriting events and calendar exports
 * Optimized for OHA schedule HTML structure
 */

(function($) {
    'use strict';
    
    window.OHAFavorites = window.OHAFavorites || {};
    
    // Storage keys
    const STORAGE_KEY = 'oha_favorites';
    
    /**
     * Initialize favorites system
     */
    OHAFavorites.init = function() {
        console.log('[Favorites] Initializing...');
        
        // Add favorite buttons to events
        this.addFavoriteButtons();
        
        // Update button states based on current favorites
        this.updateButtonStates();
        
        // Listen for favorite button clicks
        this.attachEventListeners();

        // Initialize reminders module if available
        if (window.OHAReminders) OHAReminders.init();
    };
    
    /**
     * Add favorite buttons to event cards
     * Optimized for OHA schedule HTML structure
     */
    OHAFavorites.addFavoriteButtons = function() {
        // Find all event items - matches OHA HTML structure
        const eventItems = document.querySelectorAll('.event-item');
        
        let buttonsAdded = 0;
        
        eventItems.forEach(function(item) {
            // Skip if button already added
            if (item.querySelector('.oha-favorite-btn')) {
                return;
            }
            
            // Get event ID (OHA HTML already has data-event-id!)
            const eventId = item.getAttribute('data-event-id');
            if (!eventId) {
                return; // Skip items without ID
            }
            
            // Create button container
            const buttonContainer = document.createElement('div');
            buttonContainer.className = 'oha-event-actions';
            buttonContainer.style.marginTop = '12px';
            buttonContainer.style.display = 'flex';
            buttonContainer.style.gap = '8px';
            buttonContainer.style.flexWrap = 'wrap';
            
            // Create favorite button
            const favoriteBtn = document.createElement('button');
            favoriteBtn.className = 'oha-favorite-btn';
            favoriteBtn.setAttribute('data-event-id', eventId);
            favoriteBtn.innerHTML = '⭐ Add to Favorites';
            favoriteBtn.style.cssText = 'padding: 8px 16px; border: 2px solid #FF4500; background: transparent; color: #FF4500; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.2s;';
            
            // Create calendar button
            const calendarBtn = document.createElement('button');
            calendarBtn.className = 'oha-calendar-btn';
            calendarBtn.setAttribute('data-event-id', eventId);
            calendarBtn.innerHTML = '📅 Add to Calendar';
            calendarBtn.style.cssText = 'padding: 8px 16px; border: 2px solid #4CAF50; background: transparent; color: #4CAF50; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.2s;';
            
            // Add buttons to container
            buttonContainer.appendChild(favoriteBtn);
            buttonContainer.appendChild(calendarBtn);
            
            // Find where to insert - OHA HTML has .oha-class-details, .oha-card-body, or .oha-list-class
            const insertTarget = item.querySelector('.oha-class-details, .oha-card-body, .oha-list-class');
            if (insertTarget) {
                insertTarget.appendChild(buttonContainer);
                buttonsAdded++;
            }
        });
        
        console.log('[Favorites] Added buttons to ' + buttonsAdded + ' events');
    };
    
    /**
     * Update button states based on favorites
     */
    OHAFavorites.updateButtonStates = function() {
        const favorites = this.getFavorites();
        const favoriteIds = Object.keys(favorites.events || {});
        
        document.querySelectorAll('.oha-favorite-btn').forEach(function(btn) {
            const eventId = btn.getAttribute('data-event-id');
            
            if (favoriteIds.includes(eventId)) {
                btn.classList.add('is-favorite');
                btn.innerHTML = '⭐ FAVORITED';
                btn.style.background = '#FF4500';
                btn.style.color = 'white';
            } else {
                btn.classList.remove('is-favorite');
                btn.innerHTML = '⭐ Add to Favorites';
                btn.style.background = 'transparent';
                btn.style.color = '#FF4500';
            }
        });
    };
    
    /**
     * Attach event listeners
     */
    OHAFavorites.attachEventListeners = function() {
        const self = this;
        
        // Favorite button clicks
        $(document).on('click', '.oha-favorite-btn', function(e) {
            e.preventDefault();
            const btn = this;
            const eventId = btn.getAttribute('data-event-id');
            
            // Find parent event item (not the button itself!)
            const eventItem = btn.closest('.event-item, .oha-class-item, .oha-class-card, .oha-list-item');
            
            // DEBUG: Log what element we found
            console.log('[Favorites] DEBUG - Found event element:', eventItem);
            console.log('[Favorites] DEBUG - Has data-event-title?', eventItem ? eventItem.hasAttribute('data-event-title') : 'NO ELEMENT');
            console.log('[Favorites] DEBUG - Title value:', eventItem ? eventItem.getAttribute('data-event-title') : 'NO ELEMENT');
            
            if (btn.classList.contains('is-favorite')) {
                self.removeFavorite(eventId);
            } else {
                // Read from OHA HTML data attributes
                const eventData = {
                    id: eventId,
                    title: eventItem.getAttribute('data-event-title') || 'Untitled Event',
                    time: eventItem.getAttribute('data-event-time') || 'Time TBA',
                    room: eventItem.getAttribute('data-event-room') || '',
                    speaker: eventItem.getAttribute('data-event-speaker') || '',
                    url: window.location.href
                };
                
                console.log('[Favorites] Adding:', eventData);
                self.addFavorite(eventData);
            }
            
            self.updateButtonStates();
        });
        
        // Calendar button clicks
        $(document).on('click', '.oha-calendar-btn', function(e) {
            e.preventDefault();
            const btn = this;
            const eventId = btn.getAttribute('data-event-id');
            
            // Find parent event item (not the button itself!)
            const eventItem = btn.closest('.event-item, .oha-class-item, .oha-class-card, .oha-list-item');
            
            const eventData = {
                id: eventId,
                title: eventItem.getAttribute('data-event-title') || 'Event',
                time: eventItem.getAttribute('data-event-time') || '',
                room: eventItem.getAttribute('data-event-room') || '',
                speaker: eventItem.getAttribute('data-event-speaker') || ''
            };
            
            console.log('[Favorites] Calendar export:', eventData);
            self.exportToCalendar(eventData);
        });
    };
    
    /**
     * Get all favorites from localStorage
     */
    OHAFavorites.getFavorites = function() {
        try {
            const data = localStorage.getItem(STORAGE_KEY);
            return data ? JSON.parse(data) : { events: {}, speakers: {}, vendors: {} };
        } catch (error) {
            console.error('[Favorites] Error reading favorites:', error);
            return { events: {}, speakers: {}, vendors: {} };
        }
    };
    
    /**
     * Save favorites to localStorage
     */
    OHAFavorites.saveFavorites = function(favorites) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(favorites));
            console.log('[Favorites] Saved successfully');
            return true;
        } catch (error) {
            console.error('[Favorites] Error saving favorites:', error);
            return false;
        }
    };
    
    /**
     * Add event to favorites
     */
    OHAFavorites.addFavorite = function(eventData) {
        const favorites = this.getFavorites();
        
        favorites.events[eventData.id] = {
            ...eventData,
            addedAt: new Date().toISOString()
        };
        
        this.saveFavorites(favorites);
        console.log('[Favorites] Added:', eventData.title);

        // Schedule 5-minute reminder if reminders module is available
        if (window.OHAReminders) OHAReminders.scheduleReminder(eventData);

        // Show notification
        this.showNotification('Added to favorites!');
    };
    
    /**
     * Remove event from favorites
     */
    OHAFavorites.removeFavorite = function(eventId) {
        const favorites = this.getFavorites();
        
        if (favorites.events[eventId]) {
            delete favorites.events[eventId];
            this.saveFavorites(favorites);
            console.log('[Favorites] Removed:', eventId);

            // Cancel any pending reminder
            if (window.OHAReminders) OHAReminders.cancelReminder(eventId);

            // Show notification
            this.showNotification('Removed from favorites');
        }
    };
    
    /**
     * Get favorite count
     */
    OHAFavorites.getCount = function() {
        const favorites = this.getFavorites();
        return Object.keys(favorites.events || {}).length;
    };
    
    /**
     * Export event to calendar (.ics file)
     */
    OHAFavorites.exportToCalendar = function(eventData) {
        // Parse date/time
        const eventDate = this.parseEventTime(eventData.time);
        
        const ics = this.generateICS({
            title: eventData.title,
            description: eventData.speaker ? 'Speaker: ' + eventData.speaker : '',
            location: eventData.room || 'Ohio State Reformatory',
            start: eventDate.start,
            end: eventDate.end
        });
        
        // Download .ics file (Android-compatible)
        const blob = new Blob([ics], { type: 'text/calendar;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        
        const link = document.createElement('a');
        link.href = url;
        link.download = this.sanitizeFilename(eventData.title) + '.ics';
        link.style.display = 'none';
        
        // Android requires link in document
        document.body.appendChild(link);
        link.click();
        
        // Cleanup
        setTimeout(function() {
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }, 100);
        
        this.showNotification('Calendar event downloaded!');
    };
    
    /**
     * Parse event time string to Date objects
     */
    OHAFavorites.parseEventTime = function(timeString) {
        // Example: "Saturday 10:00 AM - 11:15 AM"
        const parts = timeString.match(/(\w+)\s+(\d+):(\d+)\s+(AM|PM)\s+-\s+(\d+):(\d+)\s+(AM|PM)/);
        
        if (!parts) {
            // Fallback to a default time
            const now = new Date();
            return {
                start: now,
                end: new Date(now.getTime() + 60 * 60 * 1000) // 1 hour later
            };
        }
        
        const day = parts[1];
        const startHour = parseInt(parts[2]);
        const startMin = parseInt(parts[3]);
        const startPeriod = parts[4];
        const endHour = parseInt(parts[5]);
        const endMin = parseInt(parts[6]);
        const endPeriod = parts[7];
        
        // Convention is May 9-10, 2026
        const year = 2026;
        const month = 4; // May (0-indexed)
        const date = day.toLowerCase() === 'saturday' ? 10 : 9;
        
        const start = new Date(year, month, date, 
            startPeriod === 'PM' && startHour !== 12 ? startHour + 12 : startHour,
            startMin);
        
        const end = new Date(year, month, date,
            endPeriod === 'PM' && endHour !== 12 ? endHour + 12 : endHour,
            endMin);
        
        return { start, end };
    };
    
    /**
     * Generate ICS file content
     */
    OHAFavorites.generateICS = function(event) {
        const formatDate = function(date) {
            return date.toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z';
        };
        
        return [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//OHA Convention//NONSGML v1.0//EN',
            'BEGIN:VEVENT',
            'UID:' + Date.now() + '@ohiohaunters.com',
            'DTSTAMP:' + formatDate(new Date()),
            'DTSTART:' + formatDate(event.start),
            'DTEND:' + formatDate(event.end),
            'SUMMARY:' + event.title,
            'DESCRIPTION:' + (event.description || ''),
            'LOCATION:' + (event.location || ''),
            'BEGIN:VALARM',
            'TRIGGER:-PT15M',
            'ACTION:DISPLAY',
            'DESCRIPTION:Reminder',
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR'
        ].join('\r\n');
    };
    
    /**
     * Sanitize filename
     */
    OHAFavorites.sanitizeFilename = function(filename) {
        return filename.replace(/[^a-z0-9]/gi, '-').toLowerCase();
    };
    
    /**
     * Show notification
     */
    OHAFavorites.showNotification = function(message) {
        // Simple notification
        const notification = document.createElement('div');
        notification.textContent = message;
        notification.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #4CAF50; color: white; padding: 16px 24px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 999999; font-weight: 600;';
        document.body.appendChild(notification);
        
        setTimeout(function() {
            notification.style.opacity = '0';
            notification.style.transition = 'opacity 0.3s';
            setTimeout(function() {
                document.body.removeChild(notification);
            }, 300);
        }, 2000);
    };
    
    /**
     * Render My Schedule page
     */
    OHAFavorites.renderMySchedule = function() {
        const container = document.getElementById('oha-my-schedule');
        if (!container) return;
        
        const favorites = this.getFavorites();
        const events = Object.values(favorites.events || {});
        
        if (events.length === 0) {
            var _schedBase = (typeof ohaNavConfig !== 'undefined' && ohaNavConfig.convention_base_url)
                ? ohaNavConfig.convention_base_url
                : '/convention';
            container.innerHTML = `
                <div style="text-align:center;padding:28px 20px 32px;">
                    <div style="font-size:2em;opacity:.4;margin-bottom:10px;">&#9733;</div>
                    <p style="margin:0 0 6px;font-weight:700;font-size:15px;color:#eee;">No saved sessions yet.</p>
                    <p style="margin:0 0 18px;font-size:13px;color:rgba(255,255,255,.55);line-height:1.5;">Browse the schedule and tap the star on any session to save it here for quick access during the convention.</p>
                    <a href="${_schedBase}/schedule/" style="display:inline-block;padding:10px 20px;background:rgba(173,49,25,.85);color:#fff;text-decoration:none;border-radius:8px;font-size:13px;font-weight:600;letter-spacing:.02em;">Browse Schedule</a>
                </div>
            `;
            return;
        }
        
        let html = '<div class="favorites-list">';
        
        events.forEach(function(event) {
            // Safely handle values
            const title = event.title || 'Untitled Event';
            const time = event.time || 'Time TBA';
            const room = event.room || '';
            const speaker = event.speaker || '';
            
            html += `
                <div class="favorite-item" style="background: rgba(255,255,255,0.1); padding: 16px; margin-bottom: 12px; border-radius: 8px;">
                    <h3 style="margin: 0 0 8px 0;">${title}</h3>
                    <p style="margin: 4px 0; opacity: 0.9;">🕐 ${time}</p>
                    ${room ? `<p style="margin: 4px 0; opacity: 0.9;">📍 ${room}</p>` : ''}
                    ${speaker ? `<p style="margin: 4px 0; opacity: 0.9;">🎤 ${speaker}</p>` : ''}
                    <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap;">
                        <button class="oha-calendar-btn" data-event-id="${event.id}" style="padding: 8px 16px; background: #4CAF50; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">📅 Add to Calendar</button>
                        <button class="oha-remove-favorite" data-event-id="${event.id}" style="padding: 8px 16px; background: #f44336; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">🗑️ Remove</button>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        
        container.innerHTML = html;
        
        // Attach remove handlers
        const self = this;
        $(document).on('click', '.oha-remove-favorite', function() {
            const eventId = $(this).attr('data-event-id');
            self.removeFavorite(eventId);
            self.renderMySchedule();
        });
    };
    
    // Initialize on page load
    $(document).ready(function() {
        OHAFavorites.init();
        
        // If on My Schedule page, render it
        if (document.getElementById('oha-my-schedule')) {
            OHAFavorites.renderMySchedule();
        }
    });
    
})(jQuery);
