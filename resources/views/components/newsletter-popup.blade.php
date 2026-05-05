{{-- components/newsletter-popup.blade.php --}}

<div class="newsletter-popup"
     id="newsletter-popup"
     role="dialog"
     aria-modal="true"
     aria-labelledby="newsletter-popup-title">

  {{-- Overlay --}}
  <div class="newsletter-popup__overlay"
       id="newsletter-popup-overlay"></div>

  <div class="newsletter-popup__box">

    {{-- Bottone chiusura --}}
    <button type="button"
            class="newsletter-popup__close"
            id="newsletter-popup-close"
            aria-label="Chiudi">
      ×
    </button>

    {{-- Icona --}}
    <div class="newsletter-popup__icon">
      🧪
    </div>

    {{-- Titolo --}}
    <h2 class="newsletter-popup__title"
        id="newsletter-popup-title">
      Resta aggiornato su Quark
    </h2>

    {{-- Descrizione --}}
    <p class="newsletter-popup__sub">
      Una email ogni settimana con i migliori articoli scientifici. Niente spam.
    </p>

    {{-- Form --}}
    <form class="newsletter-form"
          method="POST"
          action="{{ route('newsletter.subscribe') }}">

      @csrf

      {{-- 🔥 Forza redirect (NO JSON) --}}
      <input type="hidden" name="_redirect" value="1">

      {{-- Honeypot anti-bot --}}
      <input type="text"
             name="website"
             tabindex="-1"
             autocomplete="off"
             style="display:none;">

      {{-- Email --}}
      <input type="email"
             name="email"
             placeholder="La tua email"
             required
             autocomplete="email">

      {{-- Bottone --}}
      <button type="submit">
        Iscriviti gratis
      </button>

    </form>

  </div>
</div>