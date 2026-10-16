const CACHE_PREFIX = 'standard-public-';
const CACHE_NAME = `${CACHE_PREFIX}v1`;
const PUBLIC_ASSETS = [
    '/offline.html',
    '/css/app.css',
    '/css/theme.css',
    '/js/app.js',
    '/js/theme.js',
    '/js/pwa.js',
    '/js/branch-clock.js',
    '/images/standard-mark.png',
    '/images/pwa-192.png',
    '/images/pwa-512.png',
    '/images/pwa-maskable-512.png',
    '/images/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(PUBLIC_ASSETS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((key) => key.startsWith(CACHE_PREFIX) && key !== CACHE_NAME).map((key) => caches.delete(key)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    // Authenticated pages, photos and financial data never enter the offline cache.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(async () => {
            const cache = await caches.open(CACHE_NAME);
            return (await cache.match('/offline.html')) || Response.error();
        }));
        return;
    }
    if (!PUBLIC_ASSETS.includes(url.pathname)) return;

    event.respondWith((async () => {
        const cache = await caches.open(CACHE_NAME);
        try {
            const response = await fetch(request);
            if (response.ok && !response.redirected) {
                await cache.put(url.pathname, response.clone()).catch(() => {});
            }
            return response;
        } catch {
            return (await cache.match(url.pathname)) || Response.error();
        }
    })());
});
