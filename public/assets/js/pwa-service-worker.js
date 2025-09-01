// File: public/assets/js/pwa-service-worker.js
// Basic service worker skeleton for caching static assets.
const CACHE_NAME = 'invopt-static-v1';
const STATIC_ASSETS = [
  '/',
  '/assets/css/bootstrap.min.css',
  '/assets/css/style.css',
  '/assets/js/bootstrap.bundle.min.js',
  '/assets/js/darkmode-toggle.js',
  '/assets/js/barcode-scanner.js'
];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS)));
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(clients.claim());
});

self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  // Network-first for API requests, cache-first for static
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(fetch(event.request).catch(()=>caches.match(event.request)));
    return;
  }
  event.respondWith(caches.match(event.request).then(resp => resp || fetch(event.request)));
});
