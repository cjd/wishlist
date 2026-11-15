/**
 * WishList Site Service Worker
 * Caches static UI assets only; never caches authenticated HTML or private uploads.
 */
const CACHE_NAME = 'wishlist-v10';
const STATIC_ASSETS = [
  './style.css',
  './print.css',
  './manifest.json',
  './css/select2.min.css',
  './js/jquery-3.7.1.min.js',
  './js/select2.min.js',
  './js/Sortable.min.js',
  './js/lightbox.js',
  './js/wishlist-filter.js',
  './js/theme.js',
  './js/toast.js',
  './js/back-to-top.js',
  './js/store-detector.js',
  './js/duplicate-detector.js',
  './js/url-autofill.js',
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
      .then((cache) =>
        Promise.all(
          STATIC_ASSETS.map((asset) => cache.add(new Request(asset, { cache: 'reload' })))
        )
      )
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

  const cleanUrl = url.origin + url.pathname;
  const isCodeAsset = /\.(?:css|js)$/i.test(url.pathname);

  if (isCodeAsset) {
    // Network-first for CSS and JS so deployments appear immediately, falling back to cache offline
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (
            response &&
            response.status === 200 &&
            !response.redirected &&
            response.type === 'basic'
          ) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(cleanUrl, clone);
            });
          }
          return response;
        })
        .catch(() => caches.match(cleanUrl))
    );
    return;
  }

  // Stale-while-revalidate strictly for static icons/images/manifest
  event.respondWith(
    caches.match(cleanUrl).then((cached) => {
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
              cache.put(cleanUrl, clone);
            });
          }
          return response;
        })
        .catch(() => cached);

      return cached || fetchPromise;
    })
  );
});
