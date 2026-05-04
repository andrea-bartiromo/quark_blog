{{-- components/newsletter-popup.blade.php --}}
<div class="nl-overlay is-hidden" id="nl-overlay" role="dialog"
     aria-modal="true" aria-labelledby="nl-title">
  <div class="nl-popup">

    <button class="nl-popup__close" id="btn-close-nl" aria-label="Chiudi">×</button>

    <div class="ad-slot ad-banner" data-label="468×60 — Sponsor popup"
         role="img" aria-label="Spazio pubblicitario"
         style="margin-bottom:1.25rem;"></div>

    <span class="kicker">Newsletter gratuita</span>
    <h2 id="nl-title">Resta aggiornato sull'innovazione italiana</h2>
    <p>Una email ogni settimana. Solo i fatti che contano. Cancellazione immediata.</p>

    <form id="nl-form" novalidate>
      @csrf
      {{-- Honeypot --}}
      <div style="position:absolute;left:-9999px;" aria-hidden="true">
        <input type="text" name="website" tabindex="-1" autocomplete="off">
      </div>

      <div class="form-group">
        <label for="nl-email">La tua email *</label>
        <input type="email" id="nl-email" name="email"
               placeholder="mario@esempio.it" required autocomplete="email">
      </div>

      <div class="form-group">
        <label class="form-checkbox">
          <input type="checkbox" name="privacy" required>
          Ho letto e accetto la <a href="#">Privacy Policy</a> *
        </label>
      </div>

      <button type="submit" class="btn btn--primary btn--full">Iscriviti — è gratis</button>
      <p class="form-note">Riceverai un'email di conferma (doppia conferma obbligatoria).</p>
    </form>

  </div>
</div>
