<script>
  document.addEventListener('DOMContentLoaded', () => {
    const popup = document.getElementById('newsletter-popup');
    const closeButton = document.getElementById('newsletter-popup-close');
    const overlay = document.getElementById('newsletter-popup-overlay');
    const alert = document.getElementById('newsletter-alert');

    fadeNewsletterAlert(alert);

    if (!popup) {
      return;
    }

    const dismissed = localStorage.getItem('newsletter_dismissed');
    const subscribed = localStorage.getItem('newsletter_subscribed');

    // Elemento che aveva il focus prima dell'apertura (es. il pulsante
    // "Newsletter" dell'header): il focus vi torna alla chiusura, cosi'
    // che un utente da tastiera non lo perda "dentro" un dialog ormai
    // nascosto — stesso principio gia' applicato in media-viewer.js per
    // il lightbox.
    let lastFocusedElement = null;

    const openPopup = () => {
      lastFocusedElement = document.activeElement;
      popup.classList.add('visible');

      const emailField = document.getElementById('newsletter-popup-email');

      if (emailField) {
        emailField.focus();
      }
    };

    if (!dismissed && !subscribed) {
      setTimeout(openPopup, 30000);
    }

    window.kairusOpenNewsletterPopup = openPopup;

    const closePopup = () => {
      popup.classList.remove('visible');

      const expires = Date.now() + 7 * 24 * 60 * 60 * 1000;

      localStorage.setItem('newsletter_dismissed', expires);

      if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
        lastFocusedElement.focus();
      }

      lastFocusedElement = null;
    };

    if (closeButton) {
      closeButton.addEventListener('click', closePopup);
    }

    if (overlay) {
      overlay.addEventListener('click', closePopup);
    }

    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') {
        closePopup();
      }
    });

    @if(request('newsletter') === 'ok')
      localStorage.setItem('newsletter_subscribed', '1');
    @endif

    clearExpiredNewsletterDismiss();
  });

  function fadeNewsletterAlert(alert) {
    if (!alert) {
      return;
    }

    setTimeout(() => {
      alert.style.transition = 'opacity .5s';
      alert.style.opacity = '0';

      setTimeout(() => alert.remove(), 500);
    }, 5000);
  }

  function clearExpiredNewsletterDismiss() {
    const dismissedUntil = localStorage.getItem('newsletter_dismissed');

    if (dismissedUntil && Date.now() > parseInt(dismissedUntil)) {
      localStorage.removeItem('newsletter_dismissed');
    }
  }
</script>
