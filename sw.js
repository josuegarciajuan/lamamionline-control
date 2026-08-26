/* LaMami Lite — Service Worker (network-first para todo, cache solo como fallback) */
const CACHE = 'lamami-lite-v13';

const PRECACHE = [
  '/control/assets/lite.css?v=1785145047',
  '/control/assets/app.js?v=1787697741',
  '/control/manifest-lite.json?v=1784940661',
  '/control/icon-192.png',
  '/control/icon-512.png',
];

self.addEventListener('install', function(e) {
  e.waitUntil(
    caches.open(CACHE).then(function(c) { return c.addAll(PRECACHE); })
  );
  self.skipWaiting();
});

self.addEventListener('activate', function(e) {
  e.waitUntil(
    caches.keys().then(function(keys) {
      return Promise.all(keys.filter(function(k) { return k !== CACHE; }).map(function(k) { return caches.delete(k); }));
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', function(e) {
  var url = new URL(e.request.url);
  // Solo requests del scope /control/
  if (url.pathname.indexOf('/control/') !== 0) return;
  // No cachear POST ni API calls
  if (e.request.method !== 'GET') return;
  if (url.pathname.indexOf('/control/index.php') === 0 && url.search.indexOf('action=') !== -1) return;

  // Network-first para todo: intenta network primero, fallback a cache
  e.respondWith(
    fetch(e.request, { cache: 'no-cache' }).catch(function() { return caches.match(e.request); })
  );
});

// ── Forzar skipWaiting cuando la página lo solicita ──
self.addEventListener('message', function(e) {
  if (e.data && e.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
