/**
 * OHA Convention PWA - Utility Functions
 * 
 * Helper functions for PWA features
 */

(function($) {
    'use strict';
    
    /**
     * PWA Utilities Object
     */
    window.OHAPWA = window.OHAPWA || {};
    
    /**
     * Check if app is installed/running standalone
     */
    OHAPWA.isInstalled = function() {
        return window.matchMedia('(display-mode: standalone)').matches || 
               window.navigator.standalone === true;
    };
    
    /**
     * Check if online
     */
    OHAPWA.isOnline = function() {
        return navigator.onLine;
    };
    
    /**
     * Add event to calendar (.ics download)
     */
    OHAPWA.addToCalendar = function(eventData) {
        const ics = OHAPWA.generateICS(eventData);
        const blob = new Blob([ics], { type: 'text/calendar' });
        const url = URL.createObjectURL(blob);
        
        const link = document.createElement('a');
        link.href = url;
        link.download = `oha-convention-${eventData.title.replace(/\s+/g, '-').toLowerCase()}.ics`;
        link.click();
        
        URL.revokeObjectURL(url);
    };
    
    /**
     * Generate ICS calendar file content
     */
    OHAPWA.generateICS = function(event) {
        const start = new Date(event.start);
        const end = new Date(event.end);
        
        const formatDate = function(date) {
            return date.toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z';
        };
        
        return `BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//OHA Convention//EN
BEGIN:VEVENT
UID:${event.id}@ohiohaunt.com
DTSTAMP:${formatDate(new Date())}
DTSTART:${formatDate(start)}
DTEND:${formatDate(end)}
SUMMARY:${event.title}
DESCRIPTION:${event.description || ''}
LOCATION:${event.location || 'OHA Convention 2026'}
URL:${event.url || window.location.href}
END:VEVENT
END:VCALENDAR`;
    };
    
    /**
     * Save to favorites
     */
    OHAPWA.addFavorite = function(postId) {
        if (typeof ohaPWA === 'undefined') {
            console.error('PWA not initialized');
            return Promise.reject('PWA not initialized');
        }
        
        return $.ajax({
            url: ohaPWA.apiUrl + 'favorite',
            method: 'POST',
            headers: {
                'X-WP-Nonce': ohaPWA.nonce
            },
            data: {
                post_id: postId
            }
        });
    };
    
    /**
     * Remove from favorites
     */
    OHAPWA.removeFavorite = function(postId) {
        if (typeof ohaPWA === 'undefined') {
            console.error('PWA not initialized');
            return Promise.reject('PWA not initialized');
        }
        
        return $.ajax({
            url: ohaPWA.apiUrl + 'favorite/remove',
            method: 'POST',
            headers: {
                'X-WP-Nonce': ohaPWA.nonce
            },
            data: {
                post_id: postId
            }
        });
    };
    
    /**
     * Get favorites
     */
    OHAPWA.getFavorites = function() {
        const favorites = localStorage.getItem('oha_favorites');
        return favorites ? JSON.parse(favorites) : [];
    };
    
    /**
     * Subscribe to push notifications
     */
    OHAPWA.subscribeToPush = async function() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.error('Push notifications not supported');
            return false;
        }
        
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: OHAPWA.urlBase64ToUint8Array(ohaPWA.vapidPublicKey || '')
            });
            
            // Send subscription to server
            await $.ajax({
                url: ohaPWA.apiUrl + 'push/subscribe',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(subscription)
            });
            
            console.log('Subscribed to push notifications');
            return true;
        } catch (error) {
            console.error('Failed to subscribe:', error);
            return false;
        }
    };
    
    /**
     * Unsubscribe from push notifications
     */
    OHAPWA.unsubscribeFromPush = async function() {
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            
            if (subscription) {
                await subscription.unsubscribe();
                
                // Notify server
                await $.ajax({
                    url: ohaPWA.apiUrl + 'push/unsubscribe',
                    method: 'POST',
                    data: {
                        endpoint: subscription.endpoint
                    }
                });
                
                console.log('Unsubscribed from push notifications');
                return true;
            }
        } catch (error) {
            console.error('Failed to unsubscribe:', error);
            return false;
        }
    };
    
    /**
     * Convert VAPID key
     */
    OHAPWA.urlBase64ToUint8Array = function(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');
        
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    };
    
    /**
     * Show notification (if permission granted)
     */
    OHAPWA.showNotification = function(title, options) {
        if (!('Notification' in window)) {
            console.error('Notifications not supported');
            return;
        }
        
        if (Notification.permission === 'granted') {
            navigator.serviceWorker.ready.then(function(registration) {
                registration.showNotification(title, options);
            });
        }
    };
    
    /**
     * Request notification permission
     */
    OHAPWA.requestNotificationPermission = async function() {
        if (!('Notification' in window)) {
            console.error('Notifications not supported');
            return false;
        }
        
        const permission = await Notification.requestPermission();
        return permission === 'granted';
    };
    
    /**
     * Network status handling
     */
    let wasOffline = false;
    let offlineBadge = null;
    
    /**
     * OFFLINE INDICATOR DISABLED
     * User requested no offline banner as it blocks content
     */
    function createOfflineBadge() {
        // Disabled - no offline indicator
        return null;
    }
    
    window.addEventListener('online', function() {
        console.log('[PWA] Back online');
        document.body.classList.remove('offline');
        
        // No badge to update (disabled)
        
        // Show all menu items again
        showAllMenuItems();
        
        // Auto-refresh schedule page when connection restored
        if (isSchedulePage()) {
            showRefreshNotification();
            setTimeout(function() {
                console.log('[PWA] Auto-refreshing schedule page...');
                window.location.reload();
            }, 2000);
        }
        
        // Only show notification if we were actually offline before
        if (wasOffline) {
            document.body.classList.add('online');
            
            // Remove the online class after animation completes
            setTimeout(function() {
                document.body.classList.remove('online');
            }, 3000);
        }
        
        wasOffline = false;
    });
    
    /**
     * Check if current page is a schedule page
     */
    function isSchedulePage() {
        const pathname = window.location.pathname;
        return pathname.includes('schedule') || 
               pathname.includes('events') || 
               pathname.includes('sessions');
    }
    
    /**
     * Show refresh notification for schedule
     */
    function showRefreshNotification() {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #2196F3;
            color: white;
            padding: 16px 24px;
            border-radius: 8px;
            z-index: 999998;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            animation: slideDown 0.3s ease;
        `;
        notification.textContent = '📡 Connection restored - Updating schedule...';
        document.body.appendChild(notification);
        
        setTimeout(function() {
            notification.style.opacity = '0';
            notification.style.transition = 'opacity 0.3s';
            setTimeout(function() {
                notification.remove();
            }, 300);
        }, 1700);
    }
    
    window.addEventListener('offline', function() {
        console.log('[PWA] Gone offline');
        document.body.classList.remove('online');
        document.body.classList.add('offline');
        wasOffline = true;
        
        // Offline indicator disabled (no badge shown)
        // PWA still works offline, just no visual indicator
        
        // Hide non-convention menu items
        hideNonConventionMenuItems();
    });
    
    /**
     * Hide menu items that aren't convention-related when offline
     */
    function hideNonConventionMenuItems() {
        // Find all menu links
        const menuLinks = document.querySelectorAll('nav a, .menu a, .navigation a, header a, .site-header a');
        
        menuLinks.forEach(function(link) {
            const href = link.getAttribute('href') || '';
            
            // Check if link is convention-related
            const isConventionLink = href.includes('convention') ||
                                     href.includes('schedule') ||
                                     href.includes('speaker') ||
                                     href.includes('vendor') ||
                                     href.includes('session') ||
                                     href.includes('room') ||
                                     href === '#' ||  // Keep hash links
                                     href === '' ||   // Keep empty links
                                     !href.startsWith('/') && !href.startsWith('http'); // Keep external/relative links
            
            if (!isConventionLink && (href.startsWith('/') || href.startsWith('http'))) {
                // Mark non-convention internal links
                link.classList.add('oha-pwa-offline-hidden');
                link.style.opacity = '0.4';
                link.style.pointerEvents = 'none';
                link.style.cursor = 'not-allowed';
                
                // Add visual indicator if not already present
                if (!link.querySelector('.oha-offline-indicator')) {
                    const indicator = document.createElement('span');
                    indicator.className = 'oha-offline-indicator';
                    indicator.textContent = ' 🔒';
                    indicator.style.fontSize = '0.8em';
                    indicator.style.marginLeft = '4px';
                    indicator.title = 'Not available offline';
                    link.appendChild(indicator);
                }
            }
        });
    }
    
    /**
     * Show all menu items when back online
     */
    function showAllMenuItems() {
        const hiddenLinks = document.querySelectorAll('.oha-pwa-offline-hidden');
        
        hiddenLinks.forEach(function(link) {
            link.classList.remove('oha-pwa-offline-hidden');
            link.style.opacity = '';
            link.style.pointerEvents = '';
            link.style.cursor = '';
            
            // Remove offline indicator
            const indicator = link.querySelector('.oha-offline-indicator');
            if (indicator) {
                indicator.remove();
            }
        });
    }
    
    /**
     * Initialize online/offline status (without notification)
     */
    $(document).ready(function() {
        // Set initial state without triggering notifications
        if (navigator.onLine) {
            // Don't add 'online' class on page load
            wasOffline = false;
        } else {
            document.body.classList.add('offline');
            wasOffline = true;
            hideNonConventionMenuItems();
        }
    });
    
    /**
     * Share API support
     */
    OHAPWA.canShare = function() {
        return 'share' in navigator;
    };
    
    /**
     * Share content using Web Share API
     */
    OHAPWA.share = async function(data) {
        if (!OHAPWA.canShare()) {
            console.error('Web Share API not supported');
            return false;
        }
        
        try {
            await navigator.share(data);
            return true;
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Share failed:', error);
            }
            return false;
        }
    };
    
})(jQuery);
