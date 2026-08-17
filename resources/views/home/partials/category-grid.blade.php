<section class="home-category-section" data-category-carousel>
  <div class="home-section-head">
    <div>
      <span>Explore</span>
      <h2 id="home-category-heading">Esplora le categorie</h2>
    </div>

    @if($categoryHighlights->count() > 1)
      <div class="home-category-controls" aria-label="Navigazione categorie">
        <button type="button" class="home-category-control" data-category-prev aria-label="Categorie precedenti" disabled>←</button>
        <button type="button" class="home-category-control" data-category-next aria-label="Categorie successive">→</button>
      </div>
    @endif
  </div>

  <div
    class="home-category-carousel"
    data-category-track
    role="region"
    aria-labelledby="home-category-heading"
    tabindex="0">
    @foreach($categoryHighlights as $art)
      <a href="{{ route('categoria', $art->category) }}" class="home-category-tile">
        <img
          src="{{ $imageForCategory($art, $loop->index) }}"
          onerror="this.onerror=null;this.src='{{ $visualFor($art) }}';"
          alt="{{ $categoryLabel($art) }}"
          loading="lazy"
          decoding="async">
        <div>
          <strong>{{ $categoryLabel($art) }} →</strong>
          <small>
            @switch($art->category)
              @case('intelligenza-artificiale') Scopri il futuro dell'IA @break
              @case('spazio') Esplorazione, satelliti e missioni @break
              @case('energia') Rinnovabili, nucleare e innovazione @break
              @case('ambiente') Clima, natura e sostenibilità @break
              @case('salute') Scienza, medicina e benessere @break
              @default Innovazione e mondo digitale
            @endswitch
          </small>
        </div>
      </a>
    @endforeach
  </div>
</section>
