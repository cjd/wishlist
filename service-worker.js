/**
 * WishList Site Service Worker
 * Enables PWA installability and offline list viewing.
 */
const CACHE_NAME = 'wishlist-v1';
const STATIC_ASSETS = [
  './style.css',
  './manifest.json',
  './js/jquery-3.7.1.min.js',
  './js/featherlight.min.js',
  './js/wishlist-filter.js',
  './js/theme.js',
  './js/toast.js',
  './css/featherlight.min.css',
  './images/icons/icon-192x192.png',
  './images/icons/icon-512x512.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS);
    }).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      );
    }).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  if (request.method !== 'GET' || !url.protocol.startsWith('http')) {
    return;
  }

  // Never cache logout or password changes
  if (url.pathname.includes('logout.php') || url.pathname.includes('changePassword.php')) {
    return;
  }

  // For HTML navigation: Network-first, fallback to cache
  if (request.mode === 'navigate' || request.headers.get('accept')?.includes('text/html')) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (response && response.status === 200) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(request, clone);
            });
          }
          return response;
        })
        .catch(() => {
          return caches.match(request).then((cached) => {
            if (cached) return cached;
            return caches.match('./home.php');
          });
        })
    );
    return;
  }

  // For static resources: Cache-first, background network update (stale-while-revalidate)
  event.respondWith(
    caches.match(request).then((cached) => {
      const fetchPromise = fetch(request).then((response) => {
        if (response && response.status === 200) {
          const clone = response.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(request, clone);
          });
        }
        return response;
      }).catch(() => {});

      return cached || fetchPromise;
    })
  );
});
