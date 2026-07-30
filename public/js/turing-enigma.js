/**
 * /turing/enigma — evidenzia il capitolo attivo nella navigazione (desktop
 * e mobile) durante lo scroll. Puro miglioramento progressivo: la
 * navigazione e' fatta di <a href="#..."> reali, funzionanti anche se
 * questo script non si carica o IntersectionObserver non e' disponibile.
 */
(function () {
  if (typeof IntersectionObserver === 'undefined') {
    return;
  }

  var links = document.querySelectorAll('[data-enigma-chapter-link]');
  if (!links.length) {
    return;
  }

  var linksById = {};
  links.forEach(function (link) {
    var id = link.getAttribute('data-enigma-chapter-link');
    linksById[id] = linksById[id] || [];
    linksById[id].push(link);
  });

  var setActive = function (id) {
    links.forEach(function (link) {
      link.removeAttribute('aria-current');
    });
    (linksById[id] || []).forEach(function (link) {
      link.setAttribute('aria-current', 'true');
    });
  };

  var sections = Object.keys(linksById)
    .map(function (id) { return document.getElementById(id); })
    .filter(Boolean);

  if (!sections.length) {
    return;
  }

  var observer = new IntersectionObserver(
    function (entries) {
      var visible = entries
        .filter(function (entry) { return entry.isIntersecting; })
        .sort(function (a, b) { return b.intersectionRatio - a.intersectionRatio; });

      if (visible.length) {
        setActive(visible[0].target.id);
      }
    },
    { rootMargin: '-30% 0px -55% 0px', threshold: [0, .25, .5, .75, 1] }
  );

  sections.forEach(function (section) { observer.observe(section); });

  // Oltre l'ultimo capitolo (timeline) la nav fissa non ha piu' nulla da
  // segnalare e, restando sempre verticalmente centrata nel viewport,
  // finirebbe per sovrapporsi ai contenuti di chiusura: la nascondiamo.
  // Un listener di scroll (throttled via requestAnimationFrame), non un
  // secondo IntersectionObserver: un salto istantaneo (tasto Fine, o uno
  // scrollTo programmatico) puo' portare il marcatore dall'essere sotto il
  // viewport a essere sopra senza mai attraversarlo, e un
  // IntersectionObserver a soglia 0 in quel caso non richiama mai la
  // callback — un semplice controllo di posizione a ogni scroll invece
  // funziona sempre, a prescindere da come si e' arrivati a quel punto.
  var endMarker = document.querySelector('[data-enigma-chapter-nav-end]');
  var navs = document.querySelectorAll('[data-enigma-chapter-nav]');

  if (endMarker && navs.length) {
    var ticking = false;

    var updateNavVisibility = function () {
      ticking = false;
      var pastEnd = endMarker.getBoundingClientRect().top < 0;
      navs.forEach(function (nav) {
        nav.classList.toggle('enigma-chapter-nav--hidden', pastEnd);
      });
    };

    var onScroll = function () {
      if (!ticking) {
        ticking = true;
        window.requestAnimationFrame(updateNavVisibility);
      }
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    updateNavVisibility();
  }
})();
