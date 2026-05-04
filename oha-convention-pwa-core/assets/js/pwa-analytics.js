/**
 * Google Analytics 4 Integration
 * Tracks PWA usage, installs, and user behavior
 */

(function() {
    'use strict';
    
    window.OHAAnalytics = window.OHAAnalytics || {};
    
    // Configuration (injected by PHP)
    const config = {
        ga4Id: window.ohaAnalyticsConfig?.ga4Id || '',
        enabled: window.ohaAnalyticsConfig?.enabled || false
    };
    
    /**
     * Initialize Google Analytics
     */
    OHAAnalytics.init = function() {
        if (!config.enabled || !config.ga4Id) {
            console.log('[Analytics] Google Analytics not configured');
            return;
        }
        
        console.log('[Analytics] Initializing GA4:', config.ga4Id);
        
        // Load GA4 script
        const script = document.createElement('script');
        script.src = `https://www.googletagmanager.com/gtag/js?id=${config.ga4Id}`;
        script.async = true;
        document.head.appendChild(script);
        
        // Initialize gtag
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        window.gtag = gtag;
        
        gtag('js', new Date());
        gtag('config', config.ga4Id, {
            'send_page_view': true,
            'app_name': 'OHA Convention PWA',
            'app_version': window.OHA_PWA_VERSION || '3.2.0'
        });
        
        // Track PWA install
        OHAAnalytics.trackPWAInstall();
        
        // Track offline/online transitions
        OHAAnalytics.trackNetworkStatus();
        
        console.log('[Analytics] GA4 initialized successfully');
    };
    
    /**
     * Track PWA installation
     */
    OHAAnalytics.trackPWAInstall = function() {
        // Check if running as PWA
        const isPWA = window.matchMedia('(display-mode: standalone)').matches ||
                      window.navigator.standalone ||
                      document.referrer.includes('android-app://');
        
        if (isPWA) {
            // Check if this is first launch after install
            if (!sessionStorage.getItem('pwa-install-tracked')) {
                sessionStorage.setItem('pwa-install-tracked', 'true');
                
                gtag('event', 'pwa_install', {
                    'event_category': 'PWA',
                    'event_label': 'App Installed',
                    'value': 1
                });
                
                console.log('[Analytics] PWA install tracked');
            }
            
            // Track as PWA session
            gtag('event', 'pwa_session', {
                'event_category': 'PWA',
                'event_label': 'Running as PWA'
            });
        }
    };
    
    /**
     * Track network status changes
     */
    OHAAnalytics.trackNetworkStatus = function() {
        let wasOffline = !navigator.onLine;
        
        window.addEventListener('offline', function() {
            gtag('event', 'went_offline', {
                'event_category': 'Network',
                'event_label': 'User went offline'
            });
            wasOffline = true;
        });
        
        window.addEventListener('online', function() {
            if (wasOffline) {
                gtag('event', 'came_online', {
                    'event_category': 'Network',
                    'event_label': 'User came back online'
                });
            }
        });
    };
    
    /**
     * Track custom events
     */
    OHAAnalytics.trackEvent = function(eventName, params = {}) {
        if (!window.gtag) {
            console.warn('[Analytics] gtag not loaded');
            return;
        }
        
        gtag('event', eventName, params);
        console.log('[Analytics] Event tracked:', eventName, params);
    };
    
    /**
     * Track favorite added
     */
    OHAAnalytics.trackFavoriteAdded = function(type, id, name) {
        this.trackEvent('favorite_added', {
            'event_category': 'Favorites',
            'event_label': type,
            'favorite_type': type,
            'favorite_id': id,
            'favorite_name': name
        });
    };
    
    /**
     * Track calendar export
     */
    OHAAnalytics.trackCalendarExport = function(eventName) {
        this.trackEvent('calendar_export', {
            'event_category': 'Engagement',
            'event_label': 'Event added to calendar',
            'event_name': eventName
        });
    };
    
    /**
     * Track volunteer area access
     */
    OHAAnalytics.trackVolunteerAccess = function() {
        this.trackEvent('volunteer_area_accessed', {
            'event_category': 'Volunteer',
            'event_label': 'Accessed volunteer information'
        });
    };
    
    /**
     * Track FAQ interaction
     */
    OHAAnalytics.trackFAQSearch = function(query, resultCount) {
        this.trackEvent('faq_search', {
            'event_category': 'FAQ',
            'event_label': query,
            'search_query': query,
            'result_count': resultCount
        });
    };
    
    // Initialize on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', OHAAnalytics.init);
    } else {
        OHAAnalytics.init();
    }
    
})();
