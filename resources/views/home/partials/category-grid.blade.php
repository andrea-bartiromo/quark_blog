<section class="home-category-section" data-category-carousel>
  <div class="home-section-head">
    <div>
      <span>Explore</span>
      <h2 id="home-category-heading">Esplora le categorie</h2>
    </div>
  </div>

  <div class="home-category-carousel-shell">
    @if($categoryHighlights->count() > 1)
      <button
        type="button"
        class="home-category-control home-category-control--prev"
        data-category-prev
        aria-label="Categorie precedenti"
        disabled>←</button>
    @endif

    <div
      class="home-category-carousel"
      data-category-track
      role="region"
      aria-labelledby="home-category-heading"
      tabindex="0">
      @foreach($categoryHighlights as $art)
      @php $categoryTileRecord = $categoryRecords[$art->category ?? ''] ?? null; @endphp
        <a href="{{ route('categoria', $art->category) }}" class="home-category-tile">
          <x-responsive-image
            :diskName="filled($categoryTileRecord?->image) ? 'categories/'.$categoryTileRecord->image : null"
            :src="$imageForCategory($art, $loop->index)"
            :onerrorSrc="$visualFor($art)"
            :alt="$categoryLabel($art)"
            :sizes="'(max-width: 640px) 100vw, (max-width: 820px) 50vw, 33vw'"
          />
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

    @if($categoryHighlights->count() > 1)
      <button
        type="button"
        class="home-category-control home-category-control--next"
        data-category-next
        aria-label="Categorie successive">→</button>
    @endif
  </div>
</section>
