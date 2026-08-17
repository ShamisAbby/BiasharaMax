const CACHE_VERSION = 'v1';
const CACHE_NAME = `biashara-os-${CACHE_VERSION}`;

const STATIC_ASSETS = [
    '/',
    '/offline.html',
];

// Cache static assets on install
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

// Remove old caches on activate
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET, cross-origin, and API/auth requests
    if (
        request.method !== 'GET' ||
        url.origin !== self.location.origin ||
        url.pathname.startsWith('/api/') ||
        url.pathname.startsWith('/sanctum/') ||
        url.pathname.startsWith('/broadcasting/')
    ) {
        return;
    }

    // Static assets (JS, CSS, fonts, images) — cache-first
    if (
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname === '/favicon.ico'
    ) {
        event.respondWith(
            caches.match(request).then(
                (cached) => cached ?? fetch(request).then((response) => {
                    if (response.ok) {
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, response.clone()));
                    }
                    return response;
                })
            )
        );
        return;
    }

    // HTML pages — network-first, offline fallback
    event.respondWith(
        fetch(request)
            .catch(() => caches.match('/offline.html'))
    );
});
