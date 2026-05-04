/**
 * Phase 3A Features
 * Vendor Favorites, What's Happening Now, FAQ, Volunteer Area
 */

(function($) {
    'use strict';
    
    window.OHAPhase3A = window.OHAPhase3A || {};

    // Shared HTML-escape utility used by all sections that build innerHTML strings
    OHAPhase3A.escHtml = function(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
                              .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    };

    /**
     * ============================================
     * VENDOR FAVORITES (Extension of existing system)
     * ============================================
     */
    
    OHAPhase3A.VendorFavorites = {
        STORAGE_KEY: 'oha_favorite_vendors',
        
        init: function() {
            console.log('[Vendor Favorites] Initializing...');
            this.addVendorButtons();
            this.attachEventListeners();
        },
        
        addVendorButtons: function() {
            const vendorItems = document.querySelectorAll('.vendor-item, .oha-vendor-card');
            
            vendorItems.forEach(item => {
                if (item.querySelector('.oha-vendor-favorite-btn')) {
                    return; // Already has button
                }
                
                const vendorId = item.getAttribute('data-vendor-id') || item.getAttribute('id');
                if (!vendorId) return;
                
                const btn = document.createElement('button');
                btn.className = 'oha-vendor-favorite-btn';
                btn.setAttribute('data-vendor-id', vendorId);
                btn.innerHTML = '⭐ Favorite Vendor';
                btn.style.cssText = 'padding: 8px 16px; background: #FF9800; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; margin-top: 12px;';
                
                item.appendChild(btn);
            });
            
            this.updateButtonStates();
        },
        
        attachEventListeners: function() {
            const self = this;
            
            $(document).on('click', '.oha-vendor-favorite-btn', function(e) {
                e.preventDefault();
                const vendorId = $(this).attr('data-vendor-id');
                const vendorItem = $(this).closest('[data-vendor-id]')[0];
                
                if ($(this).hasClass('is-favorite')) {
                    self.removeFavorite(vendorId);
                } else {
                    const vendorData = {
                        id: vendorId,
                        name: vendorItem.getAttribute('data-name') || vendorItem.querySelector('h2, h3, .vendor-name, .oha-vendor-name')?.textContent?.trim() || 'Vendor',
                        booth: vendorItem.getAttribute('data-booth') || '',
                        logo: vendorItem.getAttribute('data-logo') || '',
                        website: vendorItem.getAttribute('data-website') || '',
                        category: vendorItem.getAttribute('data-category') || '',
                        url: window.location.href
                    };
                    self.addFavorite(vendorData);
                }
                
                self.updateButtonStates();
            });
        },
        
        getFavorites: function() {
            try {
                const data = localStorage.getItem(this.STORAGE_KEY);
                return data ? JSON.parse(data) : {};
            } catch (error) {
                return {};
            }
        },
        
        addFavorite: function(vendorData) {
            const favorites = this.getFavorites();
            favorites[vendorData.id] = vendorData;
            localStorage.setItem(this.STORAGE_KEY, JSON.stringify(favorites));
            
            // Track in analytics
            if (window.gtag) {
                gtag('event', 'vendor_favorited', {
                    'vendor_id': vendorData.id,
                    'vendor_name': vendorData.name
                });
            }
            
            this.showNotification('Vendor added to favorites!');
        },
        
        removeFavorite: function(vendorId) {
            const favorites = this.getFavorites();
            delete favorites[vendorId];
            localStorage.setItem(this.STORAGE_KEY, JSON.stringify(favorites));
            this.showNotification('Vendor removed from favorites');
        },
        
        updateButtonStates: function() {
            const favorites = this.getFavorites();

            $('.oha-vendor-favorite-btn').each(function() {
                const vendorId = $(this).attr('data-vendor-id');

                if (favorites[vendorId]) {
                    $(this).addClass('is-favorite').html('&#10084;');
                } else {
                    $(this).removeClass('is-favorite').html('&#129293;');
                }
            });
        },
        
        exportFavorites: function() {
            const favorites = Object.values(this.getFavorites());
            
            if (favorites.length === 0) {
                alert('No favorite vendors to export');
                return;
            }
            
            const csv = ['Vendor Name,Booth,Category'].concat(
                favorites.map(v => `"${v.name}","${v.booth}","${v.category}"`)
            ).join('\n');
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            
            const link = document.createElement('a');
            link.href = url;
            link.download = 'oha-favorite-vendors.csv';
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            
            setTimeout(() => {
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            }, 100);
        },
        
        showNotification: function(message) {
            const notification = document.createElement('div');
            notification.className = 'oha-notification-toast';
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => notification.classList.add('visible'), 100);
            setTimeout(() => {
                notification.classList.remove('visible');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
    };
    
    /**
     * ============================================
     * WHAT'S HAPPENING NOW
     * ============================================
     */
    
    OHAPhase3A.HappeningNow = {
        init: function() {
            console.log('[Happening Now] Initializing...');
            this.render();
            
            // Update every minute
            setInterval(() => this.render(), 60000);
        },
        
        render: function() {
            const container = document.getElementById('oha-happening-now');
            if (!container) return;
            
            const now = new Date();
            const events = this.getCurrentEvents(now);
            
            let html = '<div class="oha-happening-now">';
            html += '<h3>🕐 What\'s Happening Now</h3>';
            
            const esc = OHAPhase3A.escHtml;
            if (events.current.length > 0) {
                html += '<div class="oha-happening-section">';
                events.current.forEach(event => {
                    html += `
                        <div class="oha-happening-event">
                            <div class="oha-happening-event-title">${esc(event.title)}</div>
                            <div class="oha-happening-event-details">
                                ${event.room ? `📍 ${esc(event.room)} · ` : ''}
                                ${esc(event.time)}
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
            }

            if (events.next.length > 0) {
                html += '<h3>🔜 Coming Up Next</h3>';
                html += '<div class="oha-happening-section">';
                events.next.forEach(event => {
                    html += `
                        <div class="oha-happening-event">
                            <div class="oha-happening-event-title">${esc(event.title)}</div>
                            <div class="oha-happening-event-details">
                                ${event.room ? `📍 ${esc(event.room)} · ` : ''}
                                Starts ${esc(event.startsIn)}
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
            }
            
            if (events.current.length === 0 && events.next.length === 0) {
                html += '<div class="oha-happening-no-events">No events scheduled right now. Check the Schedule tab for upcoming sessions!</div>';
            }
            
            html += '</div>';
            
            container.innerHTML = html;
        },
        
        getCurrentEvents: function(now) {
            const current = [];
            const next = [];
            
            // Get all events from schedule
            const eventItems = document.querySelectorAll('[data-event-time]');
            
            eventItems.forEach(item => {
                const title = item.getAttribute('data-event-title');
                const timeStr = item.getAttribute('data-event-time');
                const room = item.getAttribute('data-event-room');
                
                if (!timeStr) return;
                
                const times = this.parseEventTime(timeStr);
                if (!times) return;
                
                const minutesUntilStart = (times.start - now) / 1000 / 60;
                const minutesSinceEnd = (now - times.end) / 1000 / 60;
                
                // Currently happening
                if (minutesUntilStart <= 0 && minutesSinceEnd <= 0) {
                    current.push({
                        title,
                        room,
                        time: timeStr
                    });
                }
                
                // Starting within next 30 minutes
                else if (minutesUntilStart > 0 && minutesUntilStart <= 30) {
                    next.push({
                        title,
                        room,
                        startsIn: this.formatTimeUntil(minutesUntilStart)
                    });
                }
            });
            
            return { current, next };
        },
        
        parseEventTime: function(timeStr) {
            // Example: "Friday 10:00 AM - 11:15 AM" or "Saturday 2:00 PM - 3:00 PM"
            const match = timeStr.match(/(\w+)\s+(\d+):(\d+)\s+(AM|PM)\s+-\s+(\d+):(\d+)\s+(AM|PM)/);
            if (!match) return null;
            
            const day = match[1].toLowerCase();
            const startHour = parseInt(match[2]);
            const startMin = parseInt(match[3]);
            const startPeriod = match[4];
            const endHour = parseInt(match[5]);
            const endMin = parseInt(match[6]);
            const endPeriod = match[7];
            
            // Convention dates
            const year = 2026;
            const month = 4; // May (0-indexed)
            const date = day === 'saturday' ? 10 : 9;
            
            const start = new Date(year, month, date,
                startPeriod === 'PM' && startHour !== 12 ? startHour + 12 : startHour,
                startMin
            );
            
            const end = new Date(year, month, date,
                endPeriod === 'PM' && endHour !== 12 ? endHour + 12 : endHour,
                endMin
            );
            
            return { start, end };
        },
        
        formatTimeUntil: function(minutes) {
            if (minutes < 1) return 'now';
            if (minutes < 60) return `in ${Math.round(minutes)} min`;
            
            const hours = Math.floor(minutes / 60);
            const mins = Math.round(minutes % 60);
            
            if (mins === 0) return `in ${hours} hour${hours > 1 ? 's' : ''}`;
            return `in ${hours}h ${mins}m`;
        }
    };
    
    /**
     * ============================================
     * FAQ CHATBOT — Fuse.js fuzzy search
     * ============================================
     */

    OHAPhase3A.FAQ = {
        questions: [],
        fuse: null,

        init: function(faqData) {
            // Parse FAQ data (format: "Question|Answer" per line)
            this.questions = faqData.split('\n')
                .filter(line => line.trim() && line.includes('|'))
                .map(line => {
                    const parts = line.split('|');
                    return {
                        question: parts[0].trim(),
                        answer:   parts[1].trim()
                    };
                });

            if (typeof Fuse !== 'undefined') {
                this.fuse = new Fuse(this.questions, {
                    keys: [
                        { name: 'question', weight: 0.7 },
                        { name: 'answer',   weight: 0.3 }
                    ],
                    threshold:          0.4,  // 0 = exact, 1 = match anything
                    minMatchCharLength: 2,
                    includeScore:       true,
                    ignoreLocation:     true   // match anywhere in the string
                });
            }

            this.render();
            this.attachEventListeners();
        },

        render: function() {
            const container = document.getElementById('oha-faq-container');
            if (!container) return;

            let html = '<div class="oha-faq-container">';
            html += '<input type="text" class="oha-faq-search" placeholder="Ask a question about the convention..." />';
            html += '<div class="oha-faq-results" id="oha-faq-results"></div>';
            html += '</div>';

            container.innerHTML = html;

            // Show all questions initially
            this.displayResults(this.questions);
        },

        attachEventListeners: function() {
            const self = this;
            const searchInput = document.querySelector('.oha-faq-search');

            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const query = this.value.trim();

                    if (query.length === 0) {
                        self.displayResults(self.questions);
                    } else {
                        self.displayResults(self.search(query));
                    }
                });
            }

            // Toggle FAQ items
            $(document).on('click', '.oha-faq-question', function() {
                $(this).parent().toggleClass('active');
            });
        },

        search: function(query) {
            if (this.fuse) {
                return this.fuse.search(query).map(r => r.item);
            }
            // Fallback: basic substring match if Fuse didn't load
            const q = query.toLowerCase();
            return this.questions.filter(item =>
                (item.question + ' ' + item.answer).toLowerCase().includes(q)
            );
        },
        
        displayResults: function(results) {
            const container = document.getElementById('oha-faq-results');
            if (!container) return;
            
            if (results.length === 0) {
                container.innerHTML = '<div class="oha-faq-no-results">No matching questions found. Try different keywords or contact us at the Info Desk!</div>';
                return;
            }
            
            const html = results.map(item => `
                <div class="oha-faq-item">
                    <div class="oha-faq-question">${item.question}</div>
                    <div class="oha-faq-answer">${item.answer}</div>
                </div>
            `).join('');
            
            container.innerHTML = html;
        }
    };
    
    /**
     * ============================================
     * VOLUNTEER PASSWORD AREA
     * ============================================
     */
    
    OHAPhase3A.VolunteerArea = {
        STORAGE_KEY: 'oha_volunteer_access',
        
        init: function(password, content) {
            console.log('[Volunteer Area] Initializing...');
            this.password = password;
            this.content = content;
            this.render();
        },
        
        render: function() {
            const container = document.getElementById('oha-volunteer-area');
            if (!container) return;
            
            // Check if already logged in
            if (sessionStorage.getItem(this.STORAGE_KEY) === 'granted') {
                container.innerHTML = `
                    <div class="oha-volunteer-content">
                        ${this.content}
                        <hr>
                        <button class="oha-volunteer-logout" style="padding: 10px 20px; background: #666; color: white; border: none; border-radius: 6px; cursor: pointer;">🚪 Logout</button>
                    </div>
                `;
                
                // Attach logout handler
                const self = this;
                container.querySelector('.oha-volunteer-logout').addEventListener('click', function() {
                    sessionStorage.removeItem(self.STORAGE_KEY);
                    self.render();
                });
            } else {
                container.innerHTML = `
                    <div class="oha-volunteer-password-form">
                        <h3>🔒 Volunteer Access</h3>
                        <p>Enter the volunteer password to view shift schedules and important information.</p>
                        <form id="volunteer-password-form">
                            <input type="password" class="oha-volunteer-password-input" placeholder="Enter password" />
                            <br>
                            <button type="submit" class="oha-volunteer-password-submit">🔓 Access Volunteer Info</button>
                        </form>
                        <p style="margin-top: 16px; font-size: 14px; opacity: 0.8;">Don't have the password? Contact your area manager.</p>
                    </div>
                `;
                
                // Attach form handler
                const self = this;
                document.getElementById('volunteer-password-form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    const input = this.querySelector('.oha-volunteer-password-input');
                    
                    if (input.value === self.password) {
                        sessionStorage.setItem(self.STORAGE_KEY, 'granted');
                        self.render();
                        
                        // Track in analytics
                        if (window.gtag) {
                            gtag('event', 'volunteer_area_accessed');
                        }
                    } else {
                        alert('❌ Incorrect password. Please try again or contact your area manager.');
                        input.value = '';
                        input.focus();
                    }
                });
            }
        }
    };
    
    /**
     * ============================================
     * INITIALIZATION
     * ============================================
     */
    
    $(document).ready(function() {
        // Initialize vendor favorites
        if ($('.vendor-item, .oha-vendor-card').length > 0) {
            OHAPhase3A.VendorFavorites.init();
        }
        
        // Initialize What's Happening Now
        if ($('#oha-happening-now').length > 0) {
            OHAPhase3A.HappeningNow.init();
        }
        
        // Initialize FAQ (data passed from PHP)
        if ($('#oha-faq-container').length > 0 && window.ohaFAQData) {
            OHAPhase3A.FAQ.init(window.ohaFAQData);
        }
        
        // Initialize Volunteer Area (data passed from PHP)
        if ($('#oha-volunteer-area').length > 0 && window.ohaVolunteerConfig) {
            OHAPhase3A.VolunteerArea.init(
                window.ohaVolunteerConfig.password,
                window.ohaVolunteerConfig.content
            );
        }
    });
    
})(jQuery);
