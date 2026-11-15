/**
 * WishList Site Service Worker
 * Caches static UI assets only; never caches authenticated HTML or private uploads.
 */
const CACHE_NAME = 'wishlist-v2';
const STATIC_ASSETS = [
  './style.css',
  './print.css',
  './manifest.json',
  './js/jquery-3.7.1.min.js',
  './js/featherlight.min.js',
  './js/wishlist-filter.js',
  './js/theme.js',
  './js/toast.js',
  './js/back-to-top.js',
  './js/store-detector.js',
  './js/duplicate-detector.js',
  './css/featherlight.min.css',
  './images/icons/icon-192x192.png',
  './images/icons/icon-512x512.png'
];

function isCacheableStaticAsset(url, request) {
  if (url.origin !== self.location.origin) {
    return false;
  }
  if (request.mode === 'navigate') {
    return false;
  }
  const accept = request.headers.get('accept') || '';
  if (accept.includes('text/html')) {
    return false;
  }
  const path = url.pathname;
  if (path.endsWith('.php') || path.includes('/uploads/')) {
    return false;
  }
  return (
    /\.(?:css|js|ico|json)$/i.test(path) ||
    path.includes('/images/')
  );
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(STATIC_ASSETS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) =>
        Promise.all(
          keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
        )
      )
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);
  if (!url.protocol.startsWith('http') || !isCacheableStaticAsset(url, request)) {
    return;
  }

  // Stale-while-revalidate strictly for static UI assets
  event.respondWith(
    caches.match(request).then((cached) => {
      const fetchPromise = fetch(request)
        .then((response) => {
          if (
            response &&
            response.status === 200 &&
            !response.redirected &&
            response.type === 'basic'
          ) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(request, clone);
            });
          }
          return response;
        })
        .catch(() => cached);

      return cached || fetchPromise;
    })
  );
});
