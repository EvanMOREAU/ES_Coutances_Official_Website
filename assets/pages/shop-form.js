(function () {
  var form = document.querySelector('[data-shop-form]');
  if (!form) return;
  var qty = form.querySelector('[data-qty] input');
  form.querySelectorAll('[data-step]').forEach(function (b) {
    b.addEventListener('click', function () {
      var max = parseInt(qty.max, 10) || 10;
      var chosen = form.querySelector('input[name="variante"]:checked');
      if (chosen && chosen.dataset.stock) max = Math.min(max, parseInt(chosen.dataset.stock, 10));
      qty.value = Math.max(1, Math.min(max, (parseInt(qty.value, 10) || 1) + parseInt(b.dataset.step, 10)));
    });
  });
  // La quantité ne dépasse jamais le stock de la taille choisie.
  form.querySelectorAll('input[name="variante"][data-stock]').forEach(function (r) {
    r.addEventListener('change', function () {
      qty.value = Math.min(parseInt(qty.value, 10) || 1, parseInt(r.dataset.stock, 10));
    });
  });
})();

