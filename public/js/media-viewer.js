/* ============================================================
   <x-media.image-viewer> — controller generico del visualizzatore
   immagini (lightbox). Nessuna dipendenza da Article, dallo Speciale
   Turing o da altro modello/pagina: apre/chiude via data-attribute,
   esattamente come <x-special.modal> (stessa logica di base — click
   delegation, ESC, focus trap, ripristino del focus — qui estesa con
   blocco dello scroll di pagina e zoom).

   Apertura:  [data-media-viewer-target="<id>"] su un trigger qualsiasi.
   Chiusura:  [data-media-viewer-close], overlay, ESC (solo se aperto).
   Zoom:      [data-media-viewer-zoom-in|-out|-fit] dentro il dialog attivo.
   ============================================================ */

'use strict';

(function () {
  const FOCUSABLE = 'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])';

  const ZOOM_STEPS = [1, 1.5, 2, 2.5];

  let activeViewer = null;
  let lastFocusedElement = null;
  let zoomIndex = 0;
  let scrollLockScrollY = 0;

  function lockScroll() {
    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
    scrollLockScrollY = window.scrollY || document.documentElement.scrollTop;

    document.body.style.position = 'fixed';
    document.body.style.top = '-' + scrollLockScrollY + 'px';
    document.body.style.left = '0';
    document.body.style.right = '0';
    document.body.style.width = '100%';
    if (scrollbarWidth > 0) {
      document.body.style.paddingRight = scrollbarWidth + 'px';
    }
  }

  function unlockScroll() {
    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.left = '';
    document.body.style.right = '';
    document.body.style.width = '';
    document.body.style.paddingRight = '';
    window.scrollTo(0, scrollLockScrollY);
  }

  function applyZoom(viewer) {
    const image = viewer.querySelector('[data-media-viewer-image]');
    const frame = viewer.querySelector('[data-media-viewer-frame]');
    if (!image || !frame) return;

    const scale = ZOOM_STEPS[zoomIndex];
    image.style.transform = scale === 1 ? '' : 'scale(' + scale + ')';
    frame.classList.toggle('is-zoomed', scale > 1);

    const zoomInBtn = viewer.querySelector('[data-media-viewer-zoom-in]');
    const zoomOutBtn = viewer.querySelector('[data-media-viewer-zoom-out]');
    if (zoomInBtn) zoomInBtn.disabled = zoomIndex >= ZOOM_STEPS.length - 1;
    if (zoomOutBtn) zoomOutBtn.disabled = zoomIndex <= 0;
  }

  function resetZoom(viewer) {
    zoomIndex = 0;
    const frame = viewer.querySelector('[data-media-viewer-frame]');
    if (frame) {
      frame.scrollTop = 0;
      frame.scrollLeft = 0;
    }
    applyZoom(viewer);
  }

  function openViewer(viewer, trigger) {
    if (!viewer || viewer === activeViewer) return;

    lastFocusedElement = trigger || document.activeElement;
    activeViewer = viewer;

    lockScroll();

    viewer.hidden = false;
    // Forza un reflow prima della classe, cosi' la transizione di opacita'
    // viene eseguita anche alla prima apertura (stesso motivo in
    // special-modal.js).
    void viewer.offsetWidth;
    viewer.classList.add('is-open');

    resetZoom(viewer);

    const closeButton = viewer.querySelector('[data-media-viewer-close]');
    if (closeButton) {
      closeButton.focus();
    } else {
      const focusable = viewer.querySelectorAll(FOCUSABLE);
      if (focusable.length) focusable[0].focus();
    }

    document.addEventListener('keydown', handleKeydown);
  }

  function closeViewer(viewer) {
    if (!viewer || viewer !== activeViewer) return;

    viewer.classList.remove('is-open');
    viewer.hidden = true;
    document.removeEventListener('keydown', handleKeydown);

    resetZoom(viewer);
    unlockScroll();

    activeViewer = null;

    if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
      lastFocusedElement.focus();
    }
    lastFocusedElement = null;
  }

  function handleKeydown(event) {
    if (!activeViewer) return;

    if (event.key === 'Escape') {
      closeViewer(activeViewer);
      return;
    }

    if (event.key === 'Tab') {
      trapFocus(event);
    }
  }

  function trapFocus(event) {
    const focusable = [...activeViewer.querySelectorAll(FOCUSABLE)];
    if (!focusable.length) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('click', (event) => {
      const trigger = event.target.closest('[data-media-viewer-target]');
      if (trigger) {
        const viewer = document.getElementById(trigger.getAttribute('data-media-viewer-target'));
        if (viewer) {
          // Il trigger e' un <a href> reale (fallback senza JS: apre il
          // file). Con JS attivo, intercettiamo la navigazione e apriamo
          // il dialog al suo posto.
          event.preventDefault();
          openViewer(viewer, trigger);
        }
        return;
      }

      const closer = event.target.closest('[data-media-viewer-close]');
      if (closer) {
        closeViewer(closer.closest('.media-viewer'));
        return;
      }

      // Solo un click esattamente sull'overlay (non un click interno che
      // ha fatto bubbling) chiude il dialog: evita chiusure accidentali
      // cliccando dentro l'immagine o i metadati.
      if (event.target.hasAttribute('data-media-viewer-overlay')) {
        closeViewer(event.target.closest('.media-viewer'));
        return;
      }

      if (activeViewer) {
        const zoomIn = event.target.closest('[data-media-viewer-zoom-in]');
        const zoomOut = event.target.closest('[data-media-viewer-zoom-out]');
        const zoomFit = event.target.closest('[data-media-viewer-zoom-fit]');

        if (zoomIn && zoomIndex < ZOOM_STEPS.length - 1) {
          zoomIndex += 1;
          applyZoom(activeViewer);
        } else if (zoomOut && zoomIndex > 0) {
          zoomIndex -= 1;
          applyZoom(activeViewer);
        } else if (zoomFit) {
          resetZoom(activeViewer);
        }
      }
    });

    // Un <a href> nativo non risponde alla barra spaziatrice (solo Invio,
    // gia' coperto dal 'click' sopra: il browser genera un click nativo
    // su Invio per i link). La barra spaziatrice va quindi gestita qui,
    // esplicitamente, solo per il trigger — senza alterarne il ruolo
    // semantico di link.
    document.addEventListener('keydown', (event) => {
      if (event.key !== ' ') return;

      const trigger = event.target.closest('[data-media-viewer-target]');
      if (!trigger) return;

      const viewer = document.getElementById(trigger.getAttribute('data-media-viewer-target'));
      if (!viewer) return;

      event.preventDefault();
      openViewer(viewer, trigger);
    });
  });
})();
