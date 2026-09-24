/* Service Worker: noetig, damit die App auf dem Startbildschirm installierbar ist.
   Bewusst ohne Cache: Inhalte kommen immer frisch vom Server. */
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });
self.addEventListener('fetch', function () {});
