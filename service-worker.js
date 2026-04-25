const VERSION = 'alsalam-v1';
const STATIC_CACHE = `static-${VERSION}`;
const RUNTIME_CACHE = `runtime-${VERSION}`;

const OFFLINE_URL = new URL('offline.html', self.location.href).pathname;
const PRECACHE_URLS = [
    OFFLINE_URL,
    new URL('assets/css/app.css', self.location.href).pathname,
    new URL('assets/js/app.js', self.location.href).pathname,
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys
                .filter((key) => ![STATIC_CACHE, RUNTIME_CACHE].includes(key))
                .map((key) => caches.delete(key))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(handleNavigationRequest(request));
        return;
    }

    if (['style', 'script', 'image', 'font'].includes(request.destination)) {
        event.respondWith(handleStaticRequest(request));
    }
});

async function handleNavigationRequest(request) {
    try {
        return await fetch(request);
    } catch (error) {
        const cached = await caches.match(request, { ignoreSearch: true });
        if (cached) {
            return cached;
        }

        const offlineResponse = await caches.match(OFFLINE_URL, { ignoreSearch: true });
        if (offlineResponse) {
            return offlineResponse;
        }

        throw error;
    }
}

async function handleStaticRequest(request) {
    const cached = await caches.match(request, { ignoreSearch: true });

    const fetchPromise = fetch(request)
        .then(async (response) => {
            if (response.ok) {
                const cache = await caches.open(RUNTIME_CACHE);
                cache.put(request, response.clone());
            }

            return response;
        })
        .catch(() => cached);

    return cached || fetchPromise;
}
