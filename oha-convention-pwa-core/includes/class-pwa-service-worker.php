<?php
/**
 * PWA Service Worker Generator
 * 
 * Generates the service worker JavaScript for offline functionality
 * Scoped to /convention/* paths only - does NOT affect other site areas
 */

if (!defined('ABSPATH')) exit;

class OHA_PWA_Service_Worker {
    
    /**
     * Generate service worker JavaScript
     */
    public static function generate() {
        $cache_version = get_option('oha_pwa_cache_version', time());
        $api_url = rest_url('oha/v1/');
        
        // Check if OneSignal is configured — only import if enabled
        $onesignal_enabled = get_option('oha_pwa_onesignal_enabled', false);
        $onesignal_app_id  = get_option('oha_pwa_onesignal_app_id', '');
        $use_onesignal     = $onesignal_enabled && !empty($onesignal_app_id);

        ob_start();
        ?>
// OHA Convention Service Worker v<?php echo OHA_PWA_VERSION; ?>

// Cache configuration
// Version tied directly to plugin version — every plugin update busts all caches automatically.
const CACHE_VERSION = 'oha-convention-v<?php echo OHA_PWA_VERSION; ?>';
const CONVENTION_SCOPE = '<?php echo OHA_PWA_SCOPE; ?>';
const API_BASE = '<?php echo $api_url; ?>';

// Cache names
const CACHE_STATIC = `${CACHE_VERSION}-static`;
const CACHE_DYNAMIC = `${CACHE_VERSION}-dynamic`;
const CACHE_IMAGES = `${CACHE_VERSION}-images`;
const CACHE_API = `${CACHE_VERSION}-api`;

// Files to cache immediately on install (only essential files that definitely exist)
const STATIC_CACHE_FILES = [
    '<?php echo OHA_PWA_SCOPE; ?>'
];

// Optional files to cache (won't fail if they don't exist)
const OPTIONAL_CACHE_FILES = [
    '<?php echo OHA_PWA_SCOPE; ?>schedule/',
    '<?php echo OHA_PWA_SCOPE; ?>vendors/',
    '<?php echo OHA_PWA_SCOPE; ?>map/',
    '<?php echo OHA_PWA_URL; ?>assets/css/pwa-styles.css',
    '<?php echo OHA_PWA_URL; ?>assets/js/pwa-utils.js',
    '<?php echo OHA_PWA_URL; ?>assets/js/fuse.min.js'
];

/**
 * Install event - cache static assets
 * Uses forgiving caching strategy - doesn't fail if some files are 404
 */
self.addEventListener('install', event => {
    console.log('[SW] Installing OHA Convention Service Worker...');
    
    event.waitUntil(
        caches.open(CACHE_STATIC)
            .then(async cache => {
                console.log('[SW] Caching essential static assets');
                
                // Cache essential files (will fail if these don't exist)
                await cache.addAll(STATIC_CACHE_FILES);
                
                // Cache optional files individually (won't fail if 404)
                console.log('[SW] Attempting to cache optional files...');
                const promises = OPTIONAL_CACHE_FILES.map(async url => {
                    try {
                        const response = await fetch(url);
                        if (response.ok) {
                            await cache.put(url, response);
                            console.log('[SW] ✅ Cached:', url);
                        } else {
                            console.log('[SW] ⚠️ Skipped (not found):', url);
                        }
                    } catch (error) {
                        console.log('[SW] ⚠️ Skipped (error):', url, error.message);
                    }
                });
                
                await Promise.all(promises);
                console.log('[SW] ✅ Installation complete');
                // NOTE: skipWaiting is intentionally NOT called here.
                // A waiting SW stays in waiting state until the page posts
                // { type: 'SKIP_WAITING' }, which the banner triggers.
                // This gives deterministic rollouts instead of silent mid-session swaps.
            })
            .catch(error => {
                console.error('[SW] ❌ Installation failed:', error);
                throw error;
            })
    );
});

/**
 * Activate event - clean up old caches
 */
self.addEventListener('activate', event => {
    console.log('[SW] Activating OHA Convention Service Worker...');
    
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames
                        .filter(name => name.startsWith('oha-convention-'))
                        .filter(name => name !== CACHE_STATIC && 
                                      name !== CACHE_DYNAMIC && 
                                      name !== CACHE_IMAGES &&
                                      name !== CACHE_API)
                        .map(name => {
                            console.log('[SW] Deleting old cache:', name);
                            return caches.delete(name);
                        })
                );
            })
            .then(() => self.clients.claim())
    );
});

/**
 * Fetch event - intercept network requests
 * ONLY intercepts convention-scoped requests
 */
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);
    
    // CRITICAL: Only handle convention paths
    if (!isConventionScope(url.pathname)) {
        // Let browser handle normally - don't interfere
        return;
    }
    
    // Route different request types
    if (url.pathname.startsWith(API_BASE)) {
        event.respondWith(handleApiRequest(event.request));
    } else if (isImage(url.pathname)) {
        event.respondWith(handleImageRequest(event.request));
    } else if (isStaticAsset(url.pathname)) {
        event.respondWith(handleStaticRequest(event.request));
    } else if (isSchedulePage(url.pathname)) {
        // Schedule pages always try network first for freshest content
        event.respondWith(handleScheduleRequest(event.request));
    } else {
        event.respondWith(handleDynamicRequest(event.request));
    }
});

/**
 * Check if this is a schedule page (needs fresh content)
 */
function isSchedulePage(pathname) {
    return pathname.includes('schedule') || 
           pathname.includes('events') ||
           pathname.includes('sessions');
}

/**
 * Handle schedule pages - ALWAYS network first for fresh content
 */
async function handleScheduleRequest(request) {
    try {
        const response = await fetch(request);
        
        if (response.ok) {
            const cache = await caches.open(CACHE_DYNAMIC);
            // Store with timestamp header
            const clonedResponse = response.clone();
            const headers = new Headers(clonedResponse.headers);
            headers.set('X-Cached-At', new Date().toISOString());
            
            cache.put(request, new Response(clonedResponse.body, {
                status: clonedResponse.status,
                statusText: clonedResponse.statusText,
                headers: headers
            }));
        }
        
        return response;
    } catch (error) {
        console.log('[SW] Network failed for schedule, using cache:', request.url);
        const cachedResponse = await caches.match(request);
        
        if (cachedResponse) {
            // Add indicator that this is cached content
            const headers = new Headers(cachedResponse.headers);
            headers.set('X-Served-From-Cache', 'true');
            const cachedAt = headers.get('X-Cached-At') || 'Unknown time';
            headers.set('X-Cache-Age', cachedAt);
            
            return new Response(cachedResponse.body, {
                status: cachedResponse.status,
                statusText: cachedResponse.statusText,
                headers: headers
            });
        }
        
        // No cache available
        return new Response(`
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Schedule Unavailable - OHC</title>
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body {
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                        background: linear-gradient(135deg, #8B0000 0%, #2b0000 100%);
                        color: white;
                        min-height: 100vh;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        padding: 20px;
                    }
                    .container {
                        text-align: center;
                        max-width: 600px;
                    }
                    h1 {
                        font-size: 2.5em;
                        margin-bottom: 20px;
                    }
                    .icon {
                        font-size: 5em;
                        margin-bottom: 20px;
                    }
                    p {
                        font-size: 1.2em;
                        line-height: 1.6;
                        margin-bottom: 15px;
                    }
                    .btn {
                        display: inline-block;
                        background: #FF4500;
                        color: white;
                        text-decoration: none;
                        padding: 12px 24px;
                        border-radius: 8px;
                        margin-top: 20px;
                        font-weight: 600;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="icon">📅</div>
                    <h1>Schedule Unavailable Offline</h1>
                    <p>You need to be online to view the schedule for the first time.</p>
                    <p>Once loaded, it will be available offline.</p>
                    <a href="<?php echo OHA_PWA_SCOPE; ?>" class="btn">Back to Home</a>
                </div>
                <script>
                    // Auto-refresh when connection returns
                    window.addEventListener('online', function() {
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    });
                </script>
            </body>
            </html>
        `, {
            status: 503,
            statusText: 'Service Unavailable',
            headers: {
                'Content-Type': 'text/html',
                'Cache-Control': 'no-store'
            }
        });
    }
}

/**
 * Check if URL is within convention scope
 * Matches /convention/ and ALL child pages
 */
function isConventionScope(pathname) {
    // Match ANY path that contains 'convention' or specific convention-related paths
    const conventionPaths = [
        '/convention', // Matches /convention, /convention/, /convention/anything/
        '/schedule',
        '/sessions',
        '/session',
        '/speakers',
        '/speaker',
        // NOTE: /vendors and /vendor removed — these match /vendor-hub/ and /vendor-applications/
        // which are NOT convention pages and should not be cached by this SW.
        '/rooms',
        '/room',
        '2026-convention' // Specific to your site
    ];
    
    return conventionPaths.some(path => pathname.includes(path));
}

/**
 * Handle API requests - Network first, cache fallback
 */
async function handleApiRequest(request) {
    try {
        const response = await fetch(request);
        
        if (response.ok) {
            const cache = await caches.open(CACHE_API);
            cache.put(request, response.clone());
        }
        
        return response;
    } catch (error) {
        console.log('[SW] Network failed, trying cache for API:', request.url);
        const cachedResponse = await caches.match(request);
        
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline response
        return new Response(JSON.stringify({
            error: 'Offline',
            message: 'This content will load when you\'re back online'
        }), {
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

/**
 * Handle image requests - Cache first, network fallback
 */
async function handleImageRequest(request) {
    const cachedResponse = await caches.match(request);
    
    if (cachedResponse) {
        return cachedResponse;
    }
    
    try {
        const response = await fetch(request);
        
        if (response.ok) {
            const cache = await caches.open(CACHE_IMAGES);
            cache.put(request, response.clone());
        }
        
        return response;
    } catch (error) {
        console.log('[SW] Image failed to load:', request.url);
        
        // Return placeholder image
        return new Response(
            '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300"><rect width="400" height="300" fill="#333"/><text x="50%" y="50%" fill="#fff" text-anchor="middle" dominant-baseline="middle">Image unavailable offline</text></svg>',
            { headers: { 'Content-Type': 'image/svg+xml' } }
        );
    }
}

/**
 * Handle static assets - Cache first
 */
async function handleStaticRequest(request) {
    const cachedResponse = await caches.match(request);
    
    if (cachedResponse) {
        return cachedResponse;
    }
    
    try {
        const response = await fetch(request);
        
        if (response.ok) {
            const cache = await caches.open(CACHE_STATIC);
            cache.put(request, response.clone());
        }
        
        return response;
    } catch (error) {
        console.log('[SW] Static asset failed:', request.url);
        return new Response('Asset unavailable offline', { status: 503 });
    }
}

/**
 * Handle dynamic HTML pages - Network first, cache fallback
 */
async function handleDynamicRequest(request) {
    try {
        const response = await fetch(request);
        
        if (response.ok) {
            const cache = await caches.open(CACHE_DYNAMIC);
            await cache.put(request, response.clone());
        }

        return response;
    } catch (error) {
        console.log('[SW] Network failed, trying cache:', request.url);
        const cachedResponse = await caches.match(request, { ignoreVary: true });
        
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return custom styled offline page
        return new Response(`
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Offline - OHA Convention</title>
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body {
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                        background: linear-gradient(135deg, #8B0000 0%, #2b0000 100%);
                        color: white;
                        min-height: 100vh;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        padding: 20px;
                    }
                    .container {
                        text-align: center;
                        max-width: 600px;
                    }
                    h1 {
                        font-size: 3em;
                        margin-bottom: 20px;
                        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
                    }
                    .icon {
                        font-size: 5em;
                        margin-bottom: 20px;
                        opacity: 0.8;
                    }
                    p {
                        font-size: 1.2em;
                        line-height: 1.6;
                        margin-bottom: 15px;
                        opacity: 0.9;
                    }
                    .available-pages {
                        background: rgba(255,255,255,0.1);
                        border-radius: 10px;
                        padding: 20px;
                        margin-top: 30px;
                    }
                    .available-pages h2 {
                        margin-bottom: 15px;
                        font-size: 1.5em;
                    }
                    .page-link {
                        display: inline-block;
                        background: #FF4500;
                        color: white;
                        text-decoration: none;
                        padding: 12px 24px;
                        border-radius: 8px;
                        margin: 8px;
                        font-weight: 600;
                        transition: all 0.2s;
                    }
                    .page-link:hover {
                        background: #FF6347;
                        transform: translateY(-2px);
                        box-shadow: 0 4px 8px rgba(0,0,0,0.3);
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="icon">🔌</div>
                    <h1>You're Offline</h1>
                    <p>This page isn't available offline yet.</p>
                    <p>Check your internet connection and try again.</p>
                    
                    <div class="available-pages">
                        <h2>📱 Available Offline:</h2>
                        <a href="<?php echo OHA_PWA_SCOPE; ?>" class="page-link">Convention Home</a>
                    </div>
                </div>
                
                <script>
                    // Auto-refresh when back online
                    window.addEventListener('online', function() {
                        window.location.reload();
                    });
                </script>
            </body>
            </html>
        `, {
            status: 503,
            headers: {
                'Content-Type': 'text/html',
                'Cache-Control': 'no-store'
            }
        });
    }
}

/**
 * Helper: Check if request is for an image
 */
function isImage(pathname) {
    return /\.(jpg|jpeg|png|gif|webp|svg|ico)$/i.test(pathname);
}

/**
 * Helper: Check if request is for a static asset
 */
function isStaticAsset(pathname) {
    return /\.(css|js|woff|woff2|ttf|eot)$/i.test(pathname);
}

/**
 * Message event - Handle commands from pages
 */
self.addEventListener('message', event => {
    // Accept both { action: 'skipWaiting' } (legacy) and { type: 'SKIP_WAITING' } (standard)
    if (event.data.action === 'skipWaiting' || event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data.action === 'clearCache') {
        event.waitUntil(
            caches.keys().then(cacheNames => {
                return Promise.all(
                    cacheNames
                        .filter(name => name.startsWith('oha-convention-'))
                        .map(name => caches.delete(name))
                );
            })
        );
    }
    
    if (event.data.action === 'getCacheInfo') {
        event.waitUntil(
            caches.keys().then(cacheNames => {
                return Promise.all(
                    cacheNames
                        .filter(name => name.startsWith('oha-convention-'))
                        .map(async name => {
                            const cache = await caches.open(name);
                            const keys = await cache.keys();
                            return { name, count: keys.length };
                        })
                );
            }).then(info => {
                event.ports[0].postMessage({ caches: info });
            })
        );
    }
});

/**
 * Native push notification handler — handles OHA VAPID pushes and local reminder notifications.
 */
self.addEventListener('push', event => {
    if (!event.data) return;
    
    const data = event.data.json();
    
    const options = {
        body: data.body || 'New update from OHA Convention',
        icon: '<?php echo OHA_PWA_URL; ?>assets/icons/icon-192.png',
        badge: '<?php echo OHA_PWA_URL; ?>assets/icons/badge-72.png',
        data: data.url || '<?php echo OHA_PWA_SCOPE; ?>',
        actions: [
            { action: 'open',    title: 'View' },
            { action: 'dismiss', title: 'Dismiss' }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification(data.title || 'OHA Convention', options)
    );
});

/**
 * Notification click handler — handles local reminder notifications (data = { url, type: 'reminder' })
 * and legacy push notifications (data = plain string URL).
 */
self.addEventListener('notificationclick', event => {
    event.notification.close();

    // Dismiss action — just close, no navigation
    if (event.action === 'dismiss') return;

    const notifData = event.notification.data;
    let targetUrl;
    if (notifData && typeof notifData === 'object' && notifData.url) {
        targetUrl = notifData.url;           // local reminder: { url, type }
    } else if (typeof notifData === 'string' && notifData) {
        targetUrl = notifData;               // legacy push: plain string
    } else {
        targetUrl = '<?php echo OHA_PWA_SCOPE; ?>';
    }

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(clientList => {
                for (let client of clientList) {
                    if (client.url === targetUrl && 'focus' in client) {
                        return client.focus();
                    }
                }
                for (let client of clientList) {
                    if (client.url.includes('/convention/') && 'focus' in client) {
                        client.focus();
                        return client.navigate(targetUrl);
                    }
                }
                if (clients.openWindow) return clients.openWindow(targetUrl);
            })
    );
});

console.log('[SW] OHA Convention Service Worker loaded');
        <?php
        return ob_get_clean();
    }
}
