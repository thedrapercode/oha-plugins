importScripts("https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.sw.js");

// OHA Site PWA Service Worker v{{VERSION}}
const OHA_CACHE      = 'oha-site-v{{VERSION}}';
const OHA_TILES_CACHE    = 'oha-tiles-v1';
const OHA_MAP_DATA_CACHE = 'oha-map-data-v1';
const OHA_OFFLINE_URL    = '{{OFFLINE_URL}}';

// Ohio overview tiles pre-fetched at SW install (injected by PHP with real MapTiler key).
// Covers Ohio at zoom 6-8 so the map overview works without network after first SW install.
const OHIO_TILES = {{OHIO_TILES_JSON}};

// Requests that must never be intercepted (auth, commerce, sensitive admin).
const NEVER_CACHE = [
    /\/wp-admin/,
    /\/wp-login/,
    /\/wp-json\//,
    /\/cart\b/,
    /\/checkout\b/,
    /\/my-account\//,
    /[?&]wc-ajax=/,
    /preview=true/,
    /\/oha\/v1\/nonce/,
    /\/admin-ajax\.php/,
];

// Routes allowed through even though they match a NEVER_CACHE rule.
// The OHA map listings REST endpoint is public read-only data safe to cache.
const ALLOW_CACHE = [
    /\/oha\/v1\/map\//,
];

function shouldSkip(url) {
    if (ALLOW_CACHE.some(function(p) { return p.test(url); })) return false;
    return NEVER_CACHE.some(function(p) { return p.test(url); });
}

function safeOffline() {
    return caches.match(OHA_OFFLINE_URL).then(function(r) {
        if (r) return r;
        return new Response(
            '<!DOCTYPE html><html><head><title>Offline</title><meta name="viewport" content="width=device-width,initial-scale=1"></head>' +
            '<body style="font-family:sans-serif;background:#680400;color:#f7fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;">' +
            '<div style="text-align:center;padding:2rem;"><h1 style="color:#ad3119;font-size:2rem;margin-bottom:1rem;">No Connection</h1>' +
            '<p>Please check your internet connection and try again.</p></div></body></html>',
            { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        );
    });
}

// Stale-while-revalidate: return cached immediately, fetch fresh copy in background.
function staleWhileRevalidate(req, cacheName) {
    return caches.open(cacheName).then(function(cache) {
        return cache.match(req).then(function(cached) {
            var networkFetch = fetch(req).then(function(res) {
                if (res.ok) cache.put(req, res.clone());
                return res;
            });
            if (cached) {
                networkFetch.catch(function() {});
                return cached;
            }
            return networkFetch.catch(function() { return safeOffline(); });
        });
    });
}

// --- Install: cache offline page + pre-fetch Ohio map tiles ---
self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(OHA_CACHE).then(function(cache) {
            return cache.add(OHA_OFFLINE_URL);
        }).then(function() {
            if (!OHIO_TILES || !OHIO_TILES.length) return Promise.resolve();
            return caches.open(OHA_TILES_CACHE).then(function(cache) {
                return Promise.allSettled(
                    OHIO_TILES.map(function(url) {
                        return fetch(url, { mode: 'cors' }).then(function(r) {
                            if (r.ok) return cache.put(url, r);
                        }).catch(function() {});
                    })
                );
            });
        }).then(function() { return self.skipWaiting(); })
    );
});

// --- Activate: clean up old page-cache versions; keep tiles and map-data caches ---
self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(keys) {
            return Promise.all(
                keys.filter(function(k) {
                    if (k === OHA_CACHE) return false;
                    if (k.startsWith('oha-tiles-') || k.startsWith('oha-map-data-')) return false;
                    return true;
                }).map(function(k) { return caches.delete(k); })
            );
        }).then(function() { return self.clients.claim(); })
    );
});

// --- Fetch ---
self.addEventListener('fetch', function(event) {
    var req = event.request;
    if (req.method !== 'GET') return;
    var url = req.url;

    // Cross-origin: MapTiler tile and style requests — cache-first, store on miss
    if (url.indexOf('api.maptiler.com') !== -1) {
        event.respondWith(
            caches.open(OHA_TILES_CACHE).then(function(cache) {
                return cache.match(req).then(function(cached) {
                    if (cached) return cached;
                    return fetch(req).then(function(res) {
                        if (res.ok) cache.put(req, res.clone());
                        return res;
                    }).catch(function() {
                        return new Response('', { status: 408 });
                    });
                });
            })
        );
        return;
    }

    // Same-origin only beyond this point
    if (url.indexOf(self.location.origin) !== 0) return;

    // OHA map listings REST endpoint — stale-while-revalidate
    if (url.indexOf('/oha/v1/map/') !== -1) {
        event.respondWith(staleWhileRevalidate(req, OHA_MAP_DATA_CACHE));
        return;
    }

    if (shouldSkip(url)) return;

    // Haunt listings and events archive pages — stale-while-revalidate
    if (/\/(places|events)(\/|$|\?)/.test(url)) {
        event.respondWith(staleWhileRevalidate(req, OHA_CACHE));
        return;
    }

    var dest = req.destination;
    var isAsset = dest === 'style' || dest === 'script' || dest === 'image' || dest === 'font';

    if (isAsset) {
        // Cache-first
        event.respondWith(
            caches.match(req).then(function(cached) {
                if (cached) return cached;
                return fetch(req).then(function(res) {
                    if (res.ok) caches.open(OHA_CACHE).then(function(c) { c.put(req, res.clone()); });
                    return res;
                }).catch(function() { return new Response('', { status: 408 }); });
            })
        );
    } else {
        // Network-first with cache fallback
        event.respondWith(
            fetch(req)
                .then(function(res) {
                    if (res.ok) caches.open(OHA_CACHE).then(function(c) { c.put(req, res.clone()); });
                    return res;
                })
                .catch(function() {
                    return caches.match(req).then(function(cached) {
                        return cached || safeOffline();
                    });
                })
        );
    }
});

self.addEventListener('message', function(event) {
    if (event.data && event.data.action === 'SKIP_WAITING') self.skipWaiting();
});
