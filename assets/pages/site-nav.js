// Navigation du site public : raccourci d'accès à l'administration, menus déroulants et menu mobile.
const loginUrl = document.currentScript?.dataset.loginUrl;

document.addEventListener('keydown', function(e) {
  if (e.ctrlKey && e.shiftKey && e.key === 'A') {
    e.preventDefault();
    if (loginUrl) window.location.href = loginUrl;
  }
});

/* ---- Desktop nav dropdowns ---- */
(function() {
  const bar = document.querySelector('.nav-bar');
  if (!bar) return;
  const items = Array.from(bar.querySelectorAll('.nav-item'));
  // Le déclencheur d'un menu déroulant annonce son état aux lecteurs d'écran (aria-expanded) ; clavier : Entrée / Espace ouvre, Échap ferme.
  const triggerOf = item => item.querySelector('a, button');
  function setOpen(item, open) {
    const d = item.querySelector('.dropdown');
    if (!d) return;
    d.classList.toggle('open', open);
    const trigger = triggerOf(item);
    if (trigger) trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  function closeAll() { items.forEach(item => setOpen(item, false)); }
  items.forEach(item => {
    const trigger = triggerOf(item);
    if (trigger && item.querySelector('.dropdown')) {
      trigger.setAttribute('aria-haspopup', 'true');
      trigger.setAttribute('aria-expanded', 'false');
    }
    item.addEventListener('mouseenter', () => { closeAll(); setOpen(item, true); });
    if (trigger) trigger.addEventListener('click', e => { const d = item.querySelector('.dropdown'); if (d) { e.preventDefault(); const was = d.classList.contains('open'); closeAll(); if (!was) setOpen(item, true); } });
    item.addEventListener('focusin', () => { if (item.querySelector('.dropdown')) setOpen(item, true); });
    item.addEventListener('focusout', e => { if (!item.contains(e.relatedTarget)) setOpen(item, false); });
  });
  bar.addEventListener('mouseleave', closeAll);
  document.addEventListener('click', e => { if (!bar.contains(e.target)) closeAll(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAll(); });
})();

/* ---- Mobile nav ---- */
(function() {
  const toggle = document.getElementById('navToggle');
  const mobileNav = document.getElementById('mobileNav');
  if (!toggle || !mobileNav) return;
  toggle.addEventListener('click', () => { const open = mobileNav.classList.toggle('open'); toggle.classList.toggle('open', open); toggle.setAttribute('aria-expanded', open); });
  document.querySelectorAll('.mob-section-head').forEach(head => {
    head.addEventListener('click', () => { const section = head.parentElement; const wasOpen = section.classList.contains('open'); document.querySelectorAll('.mob-section').forEach(s => s.classList.remove('open')); if (!wasOpen) section.classList.add('open'); });
  });
})();
