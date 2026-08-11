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
    const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    let lastFocusedElement = null;
    let hideTimer = null;

    const openPopup = () => {
      if (popup.classList.contains('visible')) {
        return;
      }

      lastFocusedElement = document.activeElement;

      if (hideTimer) {
        clearTimeout(hideTimer);
        hideTimer = null;
      }

      popup.hidden = false;
      popup.inert = false;

      requestAnimationFrame(() => {
        popup.classList.add('visible');

        const emailField = document.getElementById('newsletter-popup-email');

        if (emailField) {
          emailField.focus();
        }
      });
    };

    if (!dismissed && !subscribed) {
      setTimeout(openPopup, 30000);
    }

    window.kairusOpenNewsletterPopup = openPopup;

    const closePopup = () => {
      if (popup.hidden) {
        return;
      }

      popup.inert = true;
      popup.classList.remove('visible');

      const expires = Date.now() + 7 * 24 * 60 * 60 * 1000;
      localStorage.setItem('newsletter_dismissed', expires);

      if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
        lastFocusedElement.focus();
      }

      lastFocusedElement = null;
      hideTimer = setTimeout(() => {
        popup.hidden = true;
        hideTimer = null;
      }, 200);
    };

    if (closeButton) {
      closeButton.addEventListener('click', closePopup);
    }

    if (overlay) {
      overlay.addEventListener('click', closePopup);
    }

    document.addEventListener('keydown', event => {
      if (popup.hidden || !popup.classList.contains('visible')) {
        return;
      }

      if (event.key === 'Escape') {
        event.preventDefault();
        closePopup();
        return;
      }

      if (event.key !== 'Tab') {
        return;
      }

      const focusableElements = Array.from(popup.querySelectorAll(focusableSelector));

      if (focusableElements.length === 0) {
        event.preventDefault();
        return;
      }

      const firstFocusable = focusableElements[0];
      const lastFocusable = focusableElements[focusableElements.length - 1];

      if (event.shiftKey && document.activeElement === firstFocusable) {
        event.preventDefault();
        lastFocusable.focus();
      } else if (!event.shiftKey && document.activeElement === lastFocusable) {
        event.preventDefault();
        firstFocusable.focus();
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
