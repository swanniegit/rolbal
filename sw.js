// BowlsTracker Service Worker

const CACHE_NAME = 'bowlstracker-v2';

// Only static assets — never PHP pages (they contain session-sensitive content)
const STATIC_ASSETS = [
    './css/styles.css',
    './js/app.js',
    './assets/logo.svg',
    './manifest.json',
];

function isStaticAsset(url) {
    return /\.(js|css|svg|png|jpg|jpeg|webp|ico|woff2?)(\?|$)/i.test(url)
        || url.endsWith('/manifest.json');
}

// Install — pre-cache static assets only
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

// Activate — purge old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

// Fetch — cache-first for static assets; network-only for everything else
self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') return;

    const url = event.request.url;

    // Never intercept API calls or PHP pages
    if (url.includes('/api/') || url.includes('.php')) return;

    if (!isStaticAsset(url)) return;

    event.respondWith(
        caches.match(event.request).then(cached => {
            if (cached) return cached;
            return fetch(event.request).then(response => {
                const clone = response.clone();
                caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
                return response;
            });
        })
    );
});
