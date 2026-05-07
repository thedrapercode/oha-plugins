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

// Network-first timeout: if network doesn't respond within this many ms, serve from cache.
// Keeps the app snappy on slow/spotty connections without waiting for a full network timeout.
const NETWORK_TIMEOUT_MS = 3000;

// Files to cache immediately on install (only essential files that definitely exist)
const STATIC_CACHE_FILES = [
    '<?php echo OHA_PWA_SCOPE; ?>'
];

// Optional files to cache on install (won't fail if they don't exist or return non-ok)
const OPTIONAL_CACHE_FILES = [
    '<?php echo OHA_PWA_SCOPE; ?>schedule/',
    '<?php echo OHA_PWA_SCOPE; ?>vendors/',
    '<?php echo OHA_PWA_SCOPE; ?>map/',
    '<?php echo OHA_PWA_URL; ?>assets/css/pwa-styles.css',
    '<?php echo OHA_PWA_URL; ?>assets/js/pwa-utils.js',
    '<?php echo OHA_PWA_URL; ?>assets/js/fuse.min.js',
    // MapLibre GL JS — loaded from unpkg CDN by the map plugin.
    // Pre-cached here so the convention map page works offline.
    'https://unpkg.com/maplibre-gl@4/dist/maplibre-gl.js',
    'https://unpkg.com/maplibre-gl@4/dist/maplibre-gl.css'
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
 *
 * The SW registration scope (/convention/) means this fetch handler only fires
 * for requests made FROM convention pages. So intercepting assets at any path
 * (wp-content/plugins, wp-content/uploads, wp-admin/admin-ajax.php) is safe —
 * we will never accidentally cache assets for non-convention pages.
 *
 * HTML navigation is still restricted to convention-scoped paths so we never
 * serve a cached convention page for a non-convention URL.
 */
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Only handle GET requests — POST/PUT/etc pass through untouched
    if (event.request.method !== 'GET') return;

    // Pre-cached external CDN resources (e.g. MapLibre from unpkg)
    if (url.hostname !== self.location.hostname && isPreCachedExternal(url)) {
        event.respondWith(handleStaticRequest(event.request));
        return;
    }

    // Skip all other cross-origin requests
    if (url.hostname !== self.location.hostname) return;

    // Static assets (JS/CSS/fonts) from anywhere on this domain.
    // Covers map plugin assets at /wp-content/plugins/oha-modern-map/...
    if (isStaticAsset(url.pathname)) {
        event.respondWith(handleStaticRequest(event.request));
        return;
    }

    // Images from anywhere on this domain.
    // Covers floor plan images at /wp-content/uploads/...
    if (isImage(url.pathname)) {
        event.respondWith(handleImageRequest(event.request));
        return;
    }

    // AJAX requests — covers booth data at /wp-admin/admin-ajax.php
    if (url.pathname.includes('admin-ajax.php')) {
        event.respondWith(handleApiRequest(event.request));
        return;
    }

    // REST API
    if (url.pathname.startsWith(new URL(API_BASE).pathname)) {
        event.respondWith(handleApiRequest(event.request));
        return;
    }

    // For HTML navigation, only handle convention-scoped paths
    if (!isConventionScope(url.pathname)) return;

    if (isSchedulePage(url.pathname)) {
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
    // Same network-first-with-timeout pattern as handleDynamicRequest.
    // Schedule pages store a timestamp header so offline viewers know the data age.
    const networkPromise = fetch(request).then(async response => {
        if (response.ok) {
            const cache = await caches.open(CACHE_DYNAMIC);
            const clonedResponse = response.clone();
            const headers = new Headers(clonedResponse.headers);
            headers.set('X-Cached-At', new Date().toISOString());
            await cache.put(request, new Response(clonedResponse.body, {
                status: clonedResponse.status,
                statusText: clonedResponse.statusText,
                headers: headers
            }));
        }
        return response;
    }).catch(() => null);

    const cachedResponse = await caches.match(request, { ignoreVary: true });

    if (cachedResponse) {
        const timeoutPromise = new Promise(resolve =>
            setTimeout(() => resolve(null), NETWORK_TIMEOUT_MS)
        );
        const winner = await Promise.race([networkPromise, timeoutPromise]);
        if (winner && winner.ok) return winner;
        console.log('[SW] Serving schedule from cache (network slow/offline):', request.url);
        return cachedResponse;
    }

    const response = await networkPromise;
    if (response) return response;

    // No cache and offline — fall back to convention home
    const homeResponse = await caches.match(CONVENTION_SCOPE, { ignoreVary: true });
    if (homeResponse) return homeResponse;

    return new Response(
        '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OHA Convention</title>' +
        '<style>body{background:#1a1a1a;color:#fff;font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;text-align:center}' +
        'h2{margin:0 0 12px}p{opacity:.8;margin:0}</style></head>' +
        '<body><div><h2>Convention App</h2><p>Connect to the internet to load this page.</p>' +
        '<script>window.addEventListener("online",function(){window.location.reload()});<\/script>' +
        '</div></body></html>',
        { status: 503, headers: { 'Content-Type': 'text/html', 'Cache-Control': 'no-store' } }
    );
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
            await cache.put(request, response.clone());
        }
        
        return response;
    } catch (error) {
        console.log('[SW] Network failed, trying cache for API:', request.url);
        const cachedResponse = await caches.match(request, { ignoreVary: true });

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
            await cache.put(request, response.clone());
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
            await cache.put(request, response.clone());
        }
        
        return response;
    } catch (error) {
        console.log('[SW] Static asset failed:', request.url);
        return new Response('Asset unavailable offline', { status: 503 });
    }
}

/**
 * Handle dynamic HTML pages.
 * Strategy: if we have a cached version, race the network against NETWORK_TIMEOUT_MS.
 * Whichever wins is returned. If network wins it also updates the cache in the background.
 * If timeout wins (slow/no connection), the cached page loads immediately.
 * If no cache exists yet, we wait for the network with no timeout.
 */
async function handleDynamicRequest(request) {
    // Start network fetch immediately — runs whether we use it or not.
    // On success it updates the cache; on failure it resolves to null.
    const networkPromise = fetch(request).then(async response => {
        if (response.ok) {
            const cache = await caches.open(CACHE_DYNAMIC);
            await cache.put(request, response.clone());
        }
        return response;
    }).catch(() => null);

    const cachedResponse = await caches.match(request, { ignoreVary: true });

    if (cachedResponse) {
        // We have cache — race network vs timeout.
        // If network responds fast enough, return fresh. Otherwise return cache.
        const timeoutPromise = new Promise(resolve =>
            setTimeout(() => resolve(null), NETWORK_TIMEOUT_MS)
        );
        const winner = await Promise.race([networkPromise, timeoutPromise]);
        if (winner && winner.ok) {
            return winner;
        }
        console.log('[SW] Serving from cache (network slow/offline):', request.url);
        return cachedResponse;
    }

    // No cache yet — must wait for network.
    const response = await networkPromise;
    if (response) return response;

    // Complete offline with no cache — serve convention home as app shell fallback.
    // Avoids a jarring full-page offline screen; home is always in STATIC_CACHE_FILES.
    console.log('[SW] No cache, falling back to convention home:', request.url);
    const homeResponse = await caches.match(CONVENTION_SCOPE, { ignoreVary: true });
    if (homeResponse) return homeResponse;

    // Last resort: minimal message (no large offline graphic)
    return new Response(
        '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OHA Convention</title>' +
        '<style>body{background:#1a1a1a;color:#fff;font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;text-align:center}' +
        'h2{margin:0 0 12px}p{opacity:.8;margin:0 0 20px}a{color:#e65c00}</style></head>' +
        '<body><div><h2>Convention App</h2><p>Connect to the internet to load this page.</p>' +
        '<script>window.addEventListener("online",function(){window.location.reload()});<\/script>' +
        '</div></body></html>',
        { status: 503, headers: { 'Content-Type': 'text/html', 'Cache-Control': 'no-store' } }
    );
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
 * Helper: Check if request is for a pre-cached external CDN resource.
 * These are cross-origin URLs we cache during SW install (e.g. MapLibre from unpkg).
 * Must be kept in sync with OPTIONAL_CACHE_FILES above.
 */
function isPreCachedExternal(url) {
    return url.href.includes('maplibre-gl');
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
