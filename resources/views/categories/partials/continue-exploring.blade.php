{{--
    Cantiere 1 (programma 100-cantieri Kairus): blocco unitario di
    ricircolo a fine pagina categoria — tre articoli tra i più letti (mai
    un duplicato di quelli già mostrati sulla griglia, vedi
    ArticleController::category()) e le categorie pubbliche correlate
    (tutte tranne quella corrente). Il Cantiere 5 formalizza ulteriormente
    questo blocco; qui la composizione e i dati sono già corretti e
    testati.
--}}
<section class="kairus-continue-exploring">
  <div class="public-section-head">
    <div>
      <span>Continua</span>
      <h2>Continua a esplorare</h2>
    </div>
  </div>

  <div class="kairus-continue-exploring__grid">

    @if($mostRead->isNotEmpty())
    <div class="kairus-continue-exploring__most-read">
      <h3>Più letti</h3>
      <div class="premium-most-read">
        @foreach($mostRead as $i => $art)
          <a href="{{ route('articolo', $art->slug) }}" class="premium-most-read__item">
            <span class="premium-most-read__rank">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
            <span class="premium-most-read__content">
              <span class="badge badge--{{ $art->category }}">{{ $categoryLabelOptions[$art->category] ?? $art->category }}</span>
              <strong>{{ Str::limit($art->title, 68) }}</strong>
              <small>{{ $art->read_minutes }} min di lettura</small>
            </span>
          </a>
        @endforeach
      </div>
    </div>
    @endif

    @php
      $relatedCategories = collect($categoryOptions)->except($slug);
    @endphp

    @if($relatedCategories->isNotEmpty())
    <div class="kairus-continue-exploring__related-categories">
      <h3>Altre categorie</h3>
      <nav class="public-pill-row" aria-label="Altre categorie">
        @foreach($relatedCategories as $relatedSlug => $relatedLabel)
          <a href="{{ route('categoria', $relatedSlug) }}">{{ $relatedLabel }}</a>
        @endforeach
      </nav>
    </div>
    @endif

  </div>
</section>
