<section class="home-category-section">
  <div class="home-section-head">
    <div>
      <span>Explore</span>
      <h2>Esplora le categorie</h2>
    </div>
  </div>

  <div class="home-category-grid">
    @foreach($categoryHighlights->take(6) as $art)
      <a href="{{ route('categoria', $art->category) }}" class="home-category-tile">
        <img
          src="{{ $imageForCategory($art, $loop->index) }}"
          onerror="this.onerror=null;this.src='{{ $visualFor($art) }}';"
          alt="{{ $categoryLabel($art) }}"
          loading="lazy">
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
