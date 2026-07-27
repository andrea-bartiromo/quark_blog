<script>
function copyArticleLink(url) {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(url).then(() => alert('Link copiato negli appunti'));
  }
}

window.addEventListener('scroll', () => {
  const progress = document.getElementById('reading-progress');
  if (!progress) return;
  const scrollTop = window.scrollY || document.documentElement.scrollTop;
  const docHeight = document.documentElement.scrollHeight - window.innerHeight;
  progress.style.width = docHeight > 0 ? ((scrollTop / docHeight) * 100) + '%' : '0%';
});

// Indice articolo: scroll fluido con offset per l'header sticky, e
// evidenziazione del titolo corrente durante lo scroll. Nessuna libreria
// esterna: solo scrollIntoView + IntersectionObserver nativi.
(function () {
  const tocLinks = document.querySelectorAll('.toc-nav a[href^="#"]');
  if (!tocLinks.length) return;

  tocLinks.forEach((link) => {
    link.addEventListener('click', (event) => {
      const id = link.getAttribute('href').slice(1);
      const target = document.getElementById(id);
      if (!target) return;

      event.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });

      if (window.history && window.history.pushState) {
        window.history.pushState(null, '', '#' + id);
      } else {
        window.location.hash = id;
      }
    });
  });

  if (typeof IntersectionObserver === 'undefined') return;

  const seenIds = new Set();
  const headings = [];
  tocLinks.forEach((link) => {
    const id = link.getAttribute('href').slice(1);
    if (seenIds.has(id)) return;
    seenIds.add(id);
    const heading = document.getElementById(id);
    if (heading) headings.push(heading);
  });
  if (!headings.length) return;

  const activeClass = 'toc-link--active';

  function setActive(id) {
    tocLinks.forEach((link) => {
      const isActive = link.getAttribute('href') === '#' + id;
      link.classList.toggle(activeClass, isActive);
      if (isActive) {
        link.setAttribute('aria-current', 'true');
      } else {
        link.removeAttribute('aria-current');
      }
    });
  }

  const header = document.querySelector('.site-header');
  const headerOffset = header ? header.offsetHeight + 24 : 96;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) setActive(entry.target.id);
    });
  }, {
    rootMargin: '-' + headerOffset + 'px 0px -70% 0px',
    threshold: 0,
  });

  headings.forEach((heading) => observer.observe(heading));
})();
</script>
