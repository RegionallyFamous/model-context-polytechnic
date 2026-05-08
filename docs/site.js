(() => {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const root = document.documentElement;
  const revealSelectors = [
    '.hero-copy',
    '.hero-terminal',
    '.signal-strip div',
    '.campus-reel figure',
    '.section-heading',
    '.admissions-flow div',
    '.quality-ledger article',
    '.terminal-window',
    '.config-terminal > img',
    '.graduation'
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

  const updateScrollProgress = () => {
    const max = document.documentElement.scrollHeight - window.innerHeight;
    const progress = max > 0 ? Math.min(window.scrollY / max, 1) : 0;

    root.style.setProperty('--scroll-progress', progress.toFixed(4));
  };

  updateScrollProgress();
  window.addEventListener('scroll', updateScrollProgress, { passive: true });
  window.addEventListener('resize', updateScrollProgress);
})();
