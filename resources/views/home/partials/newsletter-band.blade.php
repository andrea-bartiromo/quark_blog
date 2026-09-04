{{--
    Cantiere D — Home + Percorsi Visual Adoption (Prompt 45-50). Il Prompt
    46 chiede esplicitamente page-shell + section-heading (non form-shell,
    che imporrebbe un contenitore a riquadro incoerente con questa banda
    orizzontale sottile). Il <form> sotto è byte-per-byte quello esistente:
    stessa action, method, CSRF, campo nascosto "source", id/label/name.
--}}
<div class="container container--wide kairus-page-shell">
  <section class="home-newsletter-band kairus-newsletter-band">
    <div class="home-newsletter-band__icon" aria-hidden="true">✉</div>
    <x-kairus.section-heading
      eyebrow="Newsletter intelligence"
      title="La settimana scientifica, filtrata dalla redazione."
      description="Analisi, storie e segnali emergenti da spazio, IA, energia, salute e ambiente."
    />
    <form action="{{ route('newsletter.subscribe') }}" method="POST">
      @csrf
      <input type="hidden" name="source" value="homepage">
      <label class="sr-only" for="home-newsletter-email">La tua email</label>
      <input id="home-newsletter-email" type="email" name="email" placeholder="La tua email" required autocomplete="email" class="kairus-focusable">
      <button type="submit" class="kairus-focusable">Iscriviti gratis</button>
    </form>
  </section>
</div>
