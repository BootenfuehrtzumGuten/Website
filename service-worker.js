self.addEventListener('install', e => {
  e.waitUntil(
    caches.open('projekt-cache').then(cache => {
      return cache.addAll([
        '/',
        '/zukuenftige-plaene.html',
        '/kanban.html',
        '/manifest.json'
      ]);
    })
  );
});
self.addEventListener('fetch', e => {
  e.respondWith(
    caches.match(e.request).then(response => {
      return response || fetch(e.request);
    })
  );
});