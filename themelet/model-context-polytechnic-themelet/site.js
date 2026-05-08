(() => {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const root = document.documentElement;
  const revealSelectors = [
    '.hero-copy',
    '.hero-seal',
    '.crest-band div',
    '.campus-tour figure',
    '.section-heading',
    '.step-card',
    '.autopilot-points article',
    '.terminal-card',
    '.course-card',
    '.registrar-panel',
    '.bulletin-board article',
    '.apply'
  ];

  const revealItems = [...new Set(document.querySelectorAll(revealSelectors.join(',')))];

  root.classList.add('motion-ready');

  revealItems.forEach((item, index) => {
    item.dataset.motion = 'reveal';
    item.style.setProperty('--motion-delay', `${Math.min(index % 6, 5) * 55}ms`);
  });

  if (reduceMotion) {
    revealItems.forEach((item) => item.classList.add('is-visible'));
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) {
          return;
        }

        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      });
    },
    {
      rootMargin: '0px 0px -12% 0px',
      threshold: 0.08
    }
  );

  revealItems.forEach((item) => observer.observe(item));

  const hero = document.querySelector('.hero');
  const updateScrollProgress = () => {
    const max = document.documentElement.scrollHeight - window.innerHeight;
    const progress = max > 0 ? Math.min(window.scrollY / max, 1) : 0;

    root.style.setProperty('--scroll-progress', progress.toFixed(4));
  };

  updateScrollProgress();
  window.addEventListener('scroll', updateScrollProgress, { passive: true });
  window.addEventListener('resize', updateScrollProgress);

  if (!hero) {
    return;
  }

  hero.addEventListener('pointermove', (event) => {
    const rect = hero.getBoundingClientRect();
    const x = ((event.clientX - rect.left) / rect.width) * 100;
    const y = ((event.clientY - rect.top) / rect.height) * 100;

    hero.style.setProperty('--hero-x', `${x.toFixed(2)}%`);
    hero.style.setProperty('--hero-y', `${y.toFixed(2)}%`);
  });

  hero.addEventListener('pointerleave', () => {
    hero.style.setProperty('--hero-x', '56%');
    hero.style.setProperty('--hero-y', '42%');
  });
})();
