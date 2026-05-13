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

    if (!dismissed && !subscribed) {
      setTimeout(() => {
        popup.classList.add('visible');
      }, 30000);
    }

    const closePopup = () => {
      popup.classList.remove('visible');

      const expires = Date.now() + 7 * 24 * 60 * 60 * 1000;

      localStorage.setItem('newsletter_dismissed', expires);
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
