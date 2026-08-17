const CACHE = 'bacs-station-shell-v1';
const SHELL = ['/attendance-station/login', '/station-icon.svg', '/station.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    if (url.pathname.startsWith('/attendance-station/scan') || url.pathname.startsWith('/attendance-station/heartbeat')) {
        return;
    }

    event.respondWith(
        fetch(request).catch(() => caches.match(request).then((cached) => cached || caches.match('/attendance-station/login')))
    );
});
