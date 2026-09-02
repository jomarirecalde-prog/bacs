/* eslint-disable no-restricted-globals */
const SW_VERSION = 'bacs-app-v1.0.0';
const STATIC_CACHE = `${SW_VERSION}-static`;

const PRECACHE_URLS = [
    '/offline.html',
    '/app.webmanifest',
    '/images/bacs_logo_no_bg.png',
    '/images/icon-192.png',
    '/images/icon-512.png',
    '/images/icon-maskable-512.png',
];

const NO_CACHE_PREFIXES = [
    '/attendance-station/scan',
    '/attendance-station/heartbeat',
    '/broadcasting/',
    '/reverb',
];

const NO_CACHE_PATHS = new Set([
    '/login',
    '/logout',
    '/server-time',
    '/session/heartbeat',
    '/session/extend',
]);

function isSensitiveRequest(url, request) {
    if (NO_CACHE_PATHS.has(url.pathname)) {
        return true;
    }

    if (NO_CACHE_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))) {
        return true;
    }

    if (url.pathname.startsWith('/attendance-station')) {
        return true;
    }

    const accept = request.headers.get('accept') || '';
    if (accept.includes('application/json')) {
        return true;
    }

    if (request.headers.get('X-BACS-Partial') === '1') {
        return true;
    }

    return false;
}

function isStaticAsset(url) {
    if (url.origin !== self.location.origin) {
        return url.hostname === 'fonts.bunny.net';
    }

    return url.pathname.startsWith('/build/')
        || url.pathname.startsWith('/images/')
        || url.pathname.endsWith('.css')
        || url.pathname.endsWith('.js')
        || url.pathname.endsWith('.woff2');
}

function isNavigationRequest(request) {
    return request.mode === 'navigate'
        || ((request.headers.get('accept') || '').includes('text/html'));
}

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => key.startsWith('bacs-app-') && key !== STATIC_CACHE)
                    .map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

async function networkFirstNavigation(request) {
    try {
        const response = await fetch(request);
        return response;
    } catch {
        const cached = await caches.match('/offline.html');
        if (cached) {
            return cached;
        }

        return new Response(
            '<!DOCTYPE html><html><body><h1>Offline</h1><p>You are currently offline. Some features may be unavailable.</p></body></html>',
            { headers: { 'Content-Type': 'text/html; charset=utf-8' } },
        );
    }
}

async function staleWhileRevalidate(request) {
    const cache = await caches.open(STATIC_CACHE);
    const cached = await cache.match(request);

    const networkPromise = fetch(request)
        .then((response) => {
            if (response.ok) {
                cache.put(request, response.clone());
            }
            return response;
        })
        .catch(() => null);

    if (cached) {
        networkPromise.catch(() => {});
        return cached;
    }

    const network = await networkPromise;
    if (network) {
        return network;
    }

    return new Response('', { status: 504, statusText: 'Offline' });
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (isSensitiveRequest(url, request)) {
        return;
    }

    if (isNavigationRequest(request)) {
        event.respondWith(networkFirstNavigation(request));
        return;
    }

    if (isStaticAsset(url)) {
        event.respondWith(staleWhileRevalidate(request));
    }
});
