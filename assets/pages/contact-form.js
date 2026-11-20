// Évite l'envoi multiple du formulaire de contact : dès le premier envoi, le bouton est désactivé
// et les envois suivants sont ignorés (le serveur applique de toute façon un délai entre deux messages).
var form = document.querySelector('form.form-contact');
if (form) {
  form.addEventListener('submit', function (event) {
    if (form.dataset.sending === '1') {
      event.preventDefault();
      return;
    }
    form.dataset.sending = '1';
    var button = form.querySelector('button[type="submit"]');
    if (button) {
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
    }
  });
  // Retour arrière du navigateur (page restaurée depuis le cache) : on réactive le formulaire.
  window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
      form.dataset.sending = '';
      var button = form.querySelector('button[type="submit"]');
      if (button) {
        button.disabled = false;
        button.removeAttribute('aria-busy');
      }
    }
  });
}
