/* ---- Carousel hero ---- */
(function() {
  const track = document.getElementById('slidesTrack');
  if (!track) return;
  const slides = track.querySelectorAll('.slide');
  const dots   = document.querySelectorAll('.dot');
  let current  = 0, timer;

  function goTo(n) {
    current = (n + slides.length) % slides.length;
    track.style.transform = `translateX(-${current * 100}%)`;
    dots.forEach((d, i) => {
      d.classList.toggle('active', i === current);
      d.setAttribute('aria-selected', i === current);
    });
  }
  function autoplay() { clearInterval(timer); timer = setInterval(() => goTo(current + 1), 4500); }

  const prev = document.getElementById('slidePrev');
  const next = document.getElementById('slideNext');
  if (prev) prev.addEventListener('click', () => { goTo(current - 1); autoplay(); });
  if (next) next.addEventListener('click', () => { goTo(current + 1); autoplay(); });
  dots.forEach(d => d.addEventListener('click', () => { goTo(+d.dataset.index); autoplay(); }));

  let startX = 0;
  track.addEventListener('touchstart', e => { startX = e.changedTouches[0].clientX; }, { passive: true });
  track.addEventListener('touchend', e => {
    const dx = e.changedTouches[0].clientX - startX;
    if (Math.abs(dx) > 40) { goTo(current + (dx < 0 ? 1 : -1)); autoplay(); }
  }, { passive: true });

  if (slides.length > 1) autoplay();
})();

/* ---- Scroll reveal ---- */
(function() {
  const els = document.querySelectorAll('.reveal');
  if (!els.length) return;
  const io = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        io.unobserve(entry.target);
      }
    });
  }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
  els.forEach(el => io.observe(el));
})();
