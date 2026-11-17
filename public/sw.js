/*
 * Service worker Jurnalin.
 * Privasi dulu: halaman yang butuh login, foto (/media), tautan DUDI (/ttd), dan PDF tidak pernah
 * disimpan di cache, karena HP siswa sering dipinjam atau dipakai bergantian.
 * Yang disimpan hanya aset statis (CSS/JS ber-hash, ikon) dan halaman offline.
 */
const VERSION = 'jurnalin-v1';
const STATIC_CACHE = `${VERSION}-static`;
const OFFLINE_URL = '/offline.html';
const PRECACHE = [OFFLINE_URL, '/manifest.json', '/icons/icon-192.png', '/icons/icon-512.png', '/images/jurnalin-paraf.svg'];
const NEVER_CACHE = ['/media/', '/ttd/', '/reports/', '/admin/export', '/logout', '/sw.js'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) return;
    if (NEVER_CACHE.some((path) => url.pathname.startsWith(path))) return;

    // Halaman: selalu dari jaringan (data PKL harus terbaru). Bila offline, tampilkan halaman offline.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    // Aset build Vite punya hash di nama berkas, jadi aman diambil dari cache dulu.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname.startsWith('/images/')) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
                }
                return response;
            })),
        );
    }
});
