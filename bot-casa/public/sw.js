/**
 * Service Worker — CasaWasap Panel
 * Estrategia: Network-First con Cache Fallback para assets estáticos.
 * SIEMPRE carga de red. Solo usa caché si no hay conexión.
 * NO cachea páginas dinámicas (HTML) — solo CSS, JS, imágenes, manifiesto.
 */
const CACHE_NAME = 'casawasap-panel-v5';
const STATIC_ASSETS = [
  '/assets/style.css',
  '/assets/chat.css',
  '/assets/chat.js',
  '/assets/chat-operator.css',
  '/assets/chat-operator.js',
  '/assets/wa-icon.svg',
  '/assets/pwa.js',
  '/manifest.json',
  '/chat-manifest.json'
];

// Dynamic pages — NEVER cache
const DYNAMIC_PATHS = ['/cliente', '/panel', '/login', '/pago', '/logout', '/', '/chat'];

// ── Install: precache static assets (for offline fallback) ──
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS).catch(() => {});
    })
  );
  self.skipWaiting();
});

// ── Activate: clean old caches ──
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.filter((name) => name !== CACHE_NAME).map((name) => caches.delete(name))
      );
    })
  );
  self.clients.claim();
});

// ── Fetch: network-first for static assets, network-only for dynamic pages ──
self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;
  const url = new URL(event.request.url);
  const pathname = url.pathname;

  // Skip API calls
  if (pathname.startsWith('/api/') || pathname === '/webhook') return;

  // Dynamic pages / navigation: NEVER cache, always network
  if (DYNAMIC_PATHS.includes(pathname) || event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request).catch(() => {
        return new Response(
          '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CasaWasap</title><style>body{background:#0a0a12;color:#f0f3fa;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center} h1{color:#ff3b8d} p{color:#9d9dad}</style></head><body><div><h1>📡 Sin conexión</h1><p>Conéctate a Internet para usar CasaWasap.</p></div></body></html>',
          { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        );
      })
    );
    return;
  }

  // Static assets: network-first, fallback to cache only if offline
  event.respondWith(
    fetch(event.request).then((networkResponse) => {
      if (networkResponse && networkResponse.ok) {
        const clone = networkResponse.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
      }
      return networkResponse;
    }).catch(() => {
      return caches.match(event.request).then((cached) => {
        return cached || new Response('Recurso no disponible sin conexión', { status: 503 });
      });
    })
  );
});
