const CACHE_NAME = 'sifobi-v1';
const OFFLINE_URL = '/offline';

// Aset statis yang di-pre-cache saat install
const PRECACHE_URLS = [OFFLINE_URL];

// ── Install ──────────────────────────────────────────────────────────────────
self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll(PRECACHE_URLS);
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

// ── Activate ─────────────────────────────────────────────────────────────────
self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (cacheNames) {
            return Promise.all(
                cacheNames
                    .filter(function (name) { return name !== CACHE_NAME; })
                    .map(function (name) { return caches.delete(name); })
            );
        }).then(function () {
            return self.clients.claim();
        })
    );
});

// ── Fetch ─────────────────────────────────────────────────────────────────────
self.addEventListener('fetch', function (event) {
    const req = event.request;

    // Abaikan: bukan GET, bukan HTTP/HTTPS, atau request ke CDN eksternal
    if (req.method !== 'GET') return;
    if (!req.url.startsWith('http')) return;

    const url = new URL(req.url);

    // API / AJAX — selalu dari network, jangan cache
    if (
        req.headers.get('Accept') === 'application/json' ||
        url.pathname.startsWith('/api/')
    ) {
        return;
    }

    // Aset statis (build Vite, icons, fonts) — Cache First
    const isStaticAsset =
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname.startsWith('/storage/') ||
        /\.(css|js|woff2?|ttf|otf|svg|png|jpg|jpeg|gif|ico|webp)$/.test(url.pathname);

    if (isStaticAsset) {
        event.respondWith(
            caches.match(req).then(function (cached) {
                if (cached) return cached;
                return fetch(req).then(function (response) {
                    if (!response || response.status !== 200) return response;
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then(function (cache) {
                        cache.put(req, clone);
                    });
                    return response;
                });
            })
        );
        return;
    }

    // Halaman HTML (navigasi) — Network First, fallback ke /offline
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req).catch(function () {
                return caches.match(OFFLINE_URL);
            })
        );
        return;
    }
});
