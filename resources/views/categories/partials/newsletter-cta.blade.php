{{--
    Cantiere 1 (programma 100-cantieri Kairus): CTA newsletter contestuale
    dentro il flusso di scoperta della pagina categoria, inserita dopo la
    terza card della griglia — non il popup globale
    (components/newsletter-popup.blade.php, un dialog separato con la
    propria gestione di dismiss/localStorage) e non il band editoriale
    generico riusato altrove (articles/partials/newsletter-band.blade.php,
    source="article"): source="category" qui identifica correttamente
    l'origine dell'iscrizione.

    Cantiere 3: distinzione visiva/di accessibilità del blocco.
    - <section aria-labelledby="..."> invece di un <div> generico: un
      landmark con nome accessibile proprio, scopribile da chi naviga per
      region anche perché il suo genitore diretto (l'<li> in
      categoria.blade.php) è marcato role="presentation" — vedi il
      commento lì per il perché.
    - class="kairus-focusable" su input e bottone: stesso trattamento
      :focus-visible già usato da ogni altro componente interattivo di
      questo design system (kairus-article-card, kairus-path-card,
      kairus-path-step — vedi editorial-system.css, Missione 14), qui
      mancante nella prima versione del Cantiere 1.
--}}
<section class="kairus-category-newsletter" aria-labelledby="category-newsletter-heading">
  <span class="public-hero__kicker">Newsletter Kairus</span>
  <h2 id="category-newsletter-heading">Non perdere i prossimi articoli di {{ $categoryLabel }}</h2>
  <p>Una selezione settimanale degli articoli migliori di Kairus, senza rumore.</p>

  <form method="POST" action="{{ route('newsletter.subscribe') }}" class="kairus-category-newsletter__form">
    @csrf
    <input type="hidden" name="source" value="category">
    <input type="hidden" name="_redirect" value="1">

    <label class="sr-only" for="category-newsletter-email">La tua email</label>
    <input
        id="category-newsletter-email"
        class="kairus-focusable"
        type="email"
        name="email"
        placeholder="La tua email"
        required
        autocomplete="email">

    <button type="submit" class="kairus-focusable">Iscriviti gratis</button>
  </form>
</section>
