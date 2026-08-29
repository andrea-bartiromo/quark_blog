<div class="container container--wide">
  <section class="home-newsletter-band">
    <div class="home-newsletter-band__icon" aria-hidden="true">✉</div>
    <div>
      <span>Newsletter intelligence</span>
      <h2>La settimana scientifica, filtrata dalla redazione.</h2>
      <p>Analisi, storie e segnali emergenti da spazio, IA, energia, salute e ambiente.</p>
    </div>
    <form action="{{ route('newsletter.subscribe') }}" method="POST">
      @csrf
      <input type="hidden" name="source" value="homepage">
      <label class="sr-only" for="home-newsletter-email">La tua email</label>
      <input id="home-newsletter-email" type="email" name="email" placeholder="La tua email" required autocomplete="email">
      <button type="submit">Iscriviti gratis</button>
    </form>
  </section>
</div>
