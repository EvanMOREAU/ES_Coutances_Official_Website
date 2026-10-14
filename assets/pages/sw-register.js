// Enregistre le service worker du site (application installable, page hors ligne). Sans effet sur les anciens navigateurs.
if ('serviceWorker' in navigator && window.isSecureContext) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
      /* l'application reste utilisable sans service worker */
    });
  });
}
