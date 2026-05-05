import { clientsClaim } from 'workbox-core';
import { ExpirationPlugin } from 'workbox-expiration';
import { precacheAndRoute, cleanupOutdatedCaches, createHandlerBoundToURL } from 'workbox-precaching';
import { registerRoute, NavigationRoute } from 'workbox-routing';
import { CacheFirst, NetworkFirst } from 'workbox-strategies';

const PUBLIC_API_CACHE = 'public-api-cache';
const LEGACY_API_CACHE = 'api-cache';
const PUBLIC_API_PATHS = new Set([
  '/api/health',
  '/api/health/public',
  '/api/services',
  '/api/stats/summary',
  '/api/public/init',
  '/api/landing-page',
  '/api/testimonials/feedbacks',
  '/api/testimonials/feedbacks/all',
  '/api/testimonials/completed-appointments',
]);

self.skipWaiting();
clientsClaim();

precacheAndRoute(self.__WB_MANIFEST);
cleanupOutdatedCaches();

const clearApiCaches = async () => {
  await Promise.all([
    caches.delete(LEGACY_API_CACHE),
    caches.delete(PUBLIC_API_CACHE),
  ]);
};

self.addEventListener('activate', (event) => {
  event.waitUntil(caches.delete(LEGACY_API_CACHE));
});

registerRoute(
  ({ url, request }) => request.method === 'GET' && PUBLIC_API_PATHS.has(url.pathname),
  new NetworkFirst({
    cacheName: PUBLIC_API_CACHE,
    networkTimeoutSeconds: 3,
    plugins: [
      new ExpirationPlugin({
        maxEntries: 20,
        maxAgeSeconds: 300,
        purgeOnQuotaError: true,
      }),
    ],
  })
);

registerRoute(
  ({ request }) => request.destination === 'image',
  new CacheFirst({
    cacheName: 'image-cache',
    plugins: [
      new ExpirationPlugin({
        maxEntries: 200,
        maxAgeSeconds: 604800,
        purgeOnQuotaError: true,
      }),
    ],
  })
);

registerRoute(
  ({ request }) => request.destination === 'style' || request.destination === 'script',
  new CacheFirst({
    cacheName: 'static-cache',
    plugins: [
      new ExpirationPlugin({
        maxEntries: 50,
        maxAgeSeconds: 604800,
        purgeOnQuotaError: true,
      }),
    ],
  })
);

const navigationHandler = createHandlerBoundToURL('/index.html');

registerRoute(new NavigationRoute(navigationHandler, {
  denylist: [/^\/api\//, /^\/sanctum\//, /^\/\./, /^\/node_modules/],
}));

self.addEventListener('message', (event) => {
  if (event.data?.type === 'CLEAR_API_CACHES') {
    event.waitUntil(clearApiCaches());
  }
});

self.addEventListener('push', (event) => {
  const payload = (() => {
    if (!event.data) {
      return {};
    }

    try {
      return event.data.json();
    } catch {
      return { body: event.data.text() };
    }
  })();

  const title = payload.title || 'Legal Ease';
  const options = {
    body: payload.body || 'You have a new update.',
    icon: payload.icon || '/logo-192.png',
    badge: payload.badge || '/logo-192.png',
    tag: payload.tag || 'legal-ease-notification',
    renotify: Boolean(payload.renotify),
    data: payload.data || {},
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const targetUrl = new URL(event.notification.data?.url || '/', self.location.origin).href;

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
      for (const client of windowClients) {
        if (client.url.startsWith(self.location.origin)) {
          return client.focus().then(() => client.navigate(targetUrl));
        }
      }

      return self.clients.openWindow(targetUrl);
    })
  );
});