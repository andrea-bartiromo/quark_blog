<section class="public-feature-band">
  <span class="public-hero__kicker">Newsletter Quark</span>
  <h2>Ricevi il meglio della scienza ogni settimana.</h2>
  <p>Una selezione ragionata di articoli, scoperte e analisi dalla redazione. Niente rumore, solo contenuti utili.</p>
  <form method="POST" action="{{ route('newsletter.subscribe') }}" style="display:flex;gap:.7rem;flex-wrap:wrap;margin-top:1rem;">
    @csrf
    <input type="email" name="email" placeholder="La tua email" required style="flex:1;min-width:220px;border:0;border-radius:14px;padding:.9rem 1rem;">
    <button type="submit" style="border:0;border-radius:14px;padding:.9rem 1.1rem;font-weight:900;background:#67e8f9;color:#001018;cursor:pointer;">Iscriviti</button>
  </form>
</section>
