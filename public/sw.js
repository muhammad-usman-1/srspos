/* POS service worker: keeps the POS screen and its assets available without internet.
 * - The POS page (/admin/cart) is network-first, falling back to the last copy.
 * - Scripts, styles, fonts and images are cached as they are used.
 * - API calls (axios/fetch JSON) are never cached; the app keeps its own local data. */
const VERSION = 'pos-v1';
const CACHE = 'pos-cache-' + VERSION;
const POS_PAGE = '/admin/cart';

// On install, keep a copy of the POS page so the very first offline start works.
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            .then((cache) =>
                fetch(POS_PAGE, { credentials: 'same-origin' }).then((res) => {
                    if (res.ok && !res.redirected) return cache.put(POS_PAGE, res);
                })
            )
            .catch(() => {})
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

const offlinePage = () =>
    new Response(
        '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' +
        '<title>Offline</title><body style="font-family:sans-serif;text-align:center;padding:40px">' +
        '<h2>No internet connection</h2><p>This page needs internet.</p>' +
        '<p><a href="' + POS_PAGE + '">Go to POS (works offline)</a></p></body>',
        { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
    );

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Page navigations
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req)
                .then((res) => {
                    // remember the POS page only (not redirects to login, not errors)
                    if (url.pathname === POS_PAGE && res.ok && !res.redirected) {
                        const copy = res.clone();
                        caches.open(CACHE).then((c) => c.put(POS_PAGE, copy));
                    }
                    return res;
                })
                .catch(() =>
                    caches.match(POS_PAGE).then((cached) => (url.pathname === POS_PAGE && cached) || offlinePage())
                )
        );
        return;
    }

    // Static files
    const isAsset =
        ['style', 'script', 'font', 'image'].includes(req.destination) ||
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/storage/');
    if (!isAsset) return;

    const hashed = url.pathname.startsWith('/build/');
    event.respondWith(
        caches.open(CACHE).then((cache) =>
            cache.match(req).then((cached) => {
                // hashed build files never change, so a cached copy is final
                if (cached && hashed) return cached;
                const network = fetch(req).then((res) => {
                    if (res.ok) cache.put(req, res.clone());
                    return res;
                });
                // everything else: answer from cache and refresh in the background
                if (cached) {
                    network.catch(() => {});
                    return cached;
                }
                return network;
            })
        )
    );
});
