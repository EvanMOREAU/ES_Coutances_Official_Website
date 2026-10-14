/*
 * Service worker du site (application installable).
 *
 * Il ne met JAMAIS en cache de page ni de donnée personnelle : seules les ressources statiques publiques
 * (/assets, /images, /media/cache) sont gardées pour accélérer les visites suivantes, et une page « hors ligne »
 * s'affiche quand le réseau est coupé. L'administration, l'espace connecté et toute requête autre que GET
 * passent toujours directement par le réseau.
 *
 * Changer VERSION invalide les anciens caches.
 */
const VERSION = 'v1';
const STATIC_CACHE = `es-static-${VERSION}`;
const OFFLINE_URL = '/offline.html';
const STATIC_PREFIXES = ['/assets/', '/images/', '/media/cache/'];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => cache.addAll([OFFLINE_URL, '/images/logo_hd_trans3_gt.png']))
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((key) => key.startsWith('es-static-') && key !== STATIC_CACHE).map((key) => caches.delete(key))))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  if (request.method !== 'GET' || url.origin !== self.location.origin) {
    return;
  }

  // Pages : réseau uniquement ; hors ligne, la page de secours.
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));

    return;
  }

  // Ressources statiques publiques : réponse immédiate depuis le cache, remise à jour en arrière-plan.
  if (STATIC_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))) {
    event.respondWith(
      caches.open(STATIC_CACHE).then(async (cache) => {
        const cached = await cache.match(request);
        const refresh = fetch(request)
          .then((response) => {
            if (response.ok) {
              cache.put(request, response.clone());
            }

            return response;
          })
          .catch(() => cached);

        return cached ?? refresh;
      }),
    );
  }
});
