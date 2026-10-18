const CACHE_PREFIX = 'standard-public-';
const CACHE_NAME = `${CACHE_PREFIX}v4`;
const PUBLIC_ASSETS = [
    '/offline.html',
    '/css/app.css',
    '/css/theme.css',
    '/css/mobile-motion.css',
    '/js/app.js',
    '/js/navigation.js',
    '/js/mobile-menu.js',
    '/js/theme.js',
    '/js/pwa.js',
    '/js/branch-clock.js',
    '/images/pwa-192.png',
    '/images/pwa-512.png',
    '/images/pwa-maskable-512.png',
    '/images/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(PUBLIC_ASSETS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        await Promise.all([
            caches.keys().then((keys) => Promise.all(keys.filter((key) => key.startsWith(CACHE_PREFIX) && key !== CACHE_NAME).map((key) => caches.delete(key)))),
            self.registration.navigationPreload?.enable().catch(() => {}),
        ]);
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    // Authenticated pages, photos and financial data never enter the offline cache.
    if (request.mode === 'navigate') {
        event.respondWith((async () => (await event.preloadResponse) || fetch(request))().catch(async () => {
            const cache = await caches.open(CACHE_NAME);
            return (await cache.match('/offline.html')) || Response.error();
        }));
        return;
    }
    if (!PUBLIC_ASSETS.includes(url.pathname)) return;

    // Versioned assets must match exactly: never serve an older script for a new URL.
    const versioned = url.searchParams.has('v');
    const key = versioned ? url.pathname + url.search : url.pathname;
    const cachePromise = caches.open(CACHE_NAME).catch(() => null);
    const cachedPromise = cachePromise.then((cache) => cache?.match(key)).catch(() => undefined);
    const refresh = async () => {
        const response = await fetch(request);
        if (response.ok && !response.redirected) {
            const copy = response.clone();
            event.waitUntil(cachePromise.then((cache) => cache?.put(key, copy)).catch(() => {}));
        }
        return response;
    };
    // Keep writes/revalidation alive without putting them on the rendering path.
    const responsePromise = versioned ? (async () => {
        const cached = await cachedPromise;
        if (cached) return cached;
        return refresh();
    })() : null;
    if (versioned) {
        event.waitUntil(responsePromise.then(() => undefined).catch(() => {}));
    } else {
        const fresh = refresh();
        event.waitUntil(fresh.then(() => undefined).catch(() => {}));
        event.respondWith(cachedPromise.then((cached) => cached || fresh));
        return;
    }
    event.respondWith(responsePromise);
});
