/* ============================================================
   Special Project — Modal controller
   Componente: <x-special.modal> (Decision #009)
   Apertura: [data-sp-modal-target="<id>"] su un trigger qualsiasi.
   Chiusura: [data-sp-modal-close], overlay, ESC (solo se aperto).
   ============================================================ */

'use strict';

(function () {
  const FOCUSABLE = 'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])';

  let activeModal = null;
  let lastFocusedElement = null;

  function openModal(modal, trigger) {
    if (!modal || modal === activeModal) return;

    lastFocusedElement = trigger || document.activeElement;
    activeModal = modal;

    modal.hidden = false;
    // Forza un reflow prima di aggiungere la classe, cosi' la transizione
    // di opacita' viene eseguita anche alla prima apertura.
    void modal.offsetWidth;
    modal.classList.add('is-open');

    const focusable = modal.querySelectorAll(FOCUSABLE);
    if (focusable.length) {
      focusable[0].focus();
    } else {
      modal.setAttribute('tabindex', '-1');
      modal.focus();
    }

    document.addEventListener('keydown', handleKeydown);
  }

  function closeModal(modal) {
    if (!modal || modal !== activeModal) return;

    modal.classList.remove('is-open');
    modal.hidden = true;
    document.removeEventListener('keydown', handleKeydown);

    activeModal = null;

    if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
      lastFocusedElement.focus();
    }
    lastFocusedElement = null;
  }

  function handleKeydown(event) {
    if (!activeModal) return;

    if (event.key === 'Escape') {
      closeModal(activeModal);
      return;
    }

    if (event.key === 'Tab') {
      trapFocus(event);
    }
  }

  function trapFocus(event) {
    const focusable = [...activeModal.querySelectorAll(FOCUSABLE)];
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
      const trigger = event.target.closest('[data-sp-modal-target]');
      if (trigger) {
        const modal = document.getElementById(trigger.getAttribute('data-sp-modal-target'));
        if (modal) {
          event.preventDefault();
          openModal(modal, trigger);
        }
        return;
      }

      const closer = event.target.closest('[data-sp-modal-close]');
      if (closer) {
        closeModal(closer.closest('.sp-modal'));
        return;
      }

      if (event.target.hasAttribute('data-sp-modal-overlay')) {
        closeModal(event.target.closest('.sp-modal'));
      }
    });
  });
})();
