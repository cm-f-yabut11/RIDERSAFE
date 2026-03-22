/**
 * RiderSafe Service Worker
 * Handles:
 *   1. Offline caching of STATIC assets only (css, js, images, audio)
 *   2. Push notifications from the page (via postMessage)
 *   3. Notification click → open/focus the app
 *
 * PHP pages are NEVER intercepted — they always go straight to the network.
 */

const CACHE_NAME = 'ridersafe-v1';

// Only pre-cache genuinely static files — never PHP pages
const STATIC_ASSETS = [
    '/RIDERSAFE_Project/css/styles.css',
    '/RIDERSAFE_Project/js/main.js',
    '/RIDERSAFE_Project/assets/logo.png',
    '/RIDERSAFE_Project/button_sound.mp3',
    '/RIDERSAFE_Project/notif_sound.mp3'
];

// ── Install ──────────────────────────────────────────────────────────────────
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            // Use individual adds so one missing file doesn't block everything
            return Promise.allSettled(
                STATIC_ASSETS.map(url => cache.add(url).catch(e => console.warn('[SW] Could not cache', url, e)))
            );
        })
    );
    self.skipWaiting();
});

// ── Activate ─────────────────────────────────────────────────────────────────
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        )
    );
    self.clients.claim();
});

// ── Fetch: only intercept static assets, pass everything else through ────────
self.addEventListener('fetch', event => {
    const url = event.request.url;

    // Never intercept: non-GET, cross-origin, or PHP/query requests
    if (event.request.method !== 'GET') return;
    if (!url.startsWith(self.location.origin)) return;
    if (url.includes('.php') || url.includes('?')) return;

    // Only intercept known static file types
    const isStatic = /\.(css|js|png|jpg|jpeg|gif|svg|ico|woff2?|mp3|mp4|webp)(\?|$)/.test(url);
    if (!isStatic) return;

    // Cache-first for static assets
    event.respondWith(
        caches.match(event.request).then(cached => {
            if (cached) return cached;
            return fetch(event.request).then(response => {
                // Only cache valid responses
                if (!response || response.status !== 200 || response.type !== 'basic') {
                    return response;
                }
                const clone = response.clone();
                caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
                return response;
            });
            // If fetch also fails, just let the browser handle the error naturally
        })
    );
});

// ── postMessage: show notification triggered by the page ─────────────────────
self.addEventListener('message', event => {
    if (!event.data || event.data.type !== 'SHOW_NOTIFICATION') return;

    const { title, body, icon, tag } = event.data;

    self.registration.showNotification(title || 'RiderSafe', {
        body:               body || 'Your safety check-in is due!',
        icon:               icon || '/RIDERSAFE_Project/assets/logo.png',
        badge:              '/RIDERSAFE_Project/assets/logo.png',
        tag:                tag  || 'ridersafe-checkin',
        requireInteraction: true,
        vibrate:            [200, 100, 200]
    });
});

// ── Notification click: focus or open the app ─────────────────────────────────
self.addEventListener('notificationclick', event => {
    event.notification.close();

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clients => {
            for (const client of clients) {
                if (client.url.includes('RIDERSAFE_Project') && 'focus' in client) {
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow('/RIDERSAFE_Project/button_page.php');
            }
        })
    );
});