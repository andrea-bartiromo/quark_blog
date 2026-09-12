{{--
    Cantiere 1 (programma 100-cantieri Kairus): CTA newsletter contestuale
    dentro il flusso di scoperta della pagina categoria, inserita dopo la
    terza card della griglia — non il popup globale
    (components/newsletter-popup.blade.php, un dialog separato con la
    propria gestione di dismiss/localStorage) e non il band editoriale
    generico riusato altrove (articles/partials/newsletter-band.blade.php,
    source="article"): source="category" qui identifica correttamente
    l'origine dell'iscrizione. Il Cantiere 3 distingue ulteriormente questo
    blocco a livello visivo/di accessibilità; qui il meccanismo (posizione,
    form funzionante, attribuzione corretta) è già corretto e testato.
--}}
<div class="kairus-category-newsletter">
  <span class="public-hero__kicker">Newsletter Kairus</span>
  <h2>Non perdere i prossimi articoli di {{ $categoryLabel }}</h2>
  <p>Una selezione settimanale degli articoli migliori di Kairus, senza rumore.</p>

  <form method="POST" action="{{ route('newsletter.subscribe') }}" class="kairus-category-newsletter__form">
    @csrf
    <input type="hidden" name="source" value="category">
    <input type="hidden" name="_redirect" value="1">

    <label class="sr-only" for="category-newsletter-email">La tua email</label>
    <input
        id="category-newsletter-email"
        type="email"
        name="email"
        placeholder="La tua email"
        required
        autocomplete="email">

    <button type="submit">Iscriviti gratis</button>
  </form>
</div>
