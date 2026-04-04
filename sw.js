// BowlsTracker Service Worker

const CACHE_NAME = 'bowlstracker-v1';
const ASSETS = [
    './',
    './index.php',
    './css/styles.css',
    './js/app.js',
    './assets/logo.svg',
    './manifest.json'
];

// Install
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(ASSETS))
            .then(() => self.skipWaiting())
    );
});

// Activate
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(
                keys.filter(key => key !== CACHE_NAME)
                    .map(key => caches.delete(key))
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch - Network first, fallback to cache (skip POST/API)
self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET' || event.request.url.includes('/api/')) {
        return;
    }
    event.respondWith(
        fetch(event.request)
            .then(response => {
                const clone = response.clone();
                caches.open(CACHE_NAME)
                    .then(cache => cache.put(event.request, clone));
                return response;
            })
            .catch(() => caches.match(event.request))
    );
});
