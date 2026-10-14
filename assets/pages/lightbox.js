(function () {
  var overlay = document.createElement('div');
  overlay.className = 'content-lightbox';
  overlay.innerHTML = '<button type="button" class="content-lightbox-close" aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button><img alt="">';
  document.body.appendChild(overlay);
  var img = overlay.querySelector('img');

  function open(src, alt) {
    img.src = src;
    img.alt = alt || '';
    overlay.classList.add('is-open');
  }
  function close() {
    overlay.classList.remove('is-open');
    img.src = '';
  }

  document.querySelectorAll('.prose figure > a').forEach(function (link) {
    var target = link.querySelector('img');
    if (!target) return;
    link.addEventListener('click', function (event) {
      event.preventDefault();
      open(link.getAttribute('href'), target.alt);
    });
  });

  overlay.addEventListener('click', function (event) {
    if (event.target === overlay || event.target.closest('.content-lightbox-close')) close();
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') close();
  });
})();
