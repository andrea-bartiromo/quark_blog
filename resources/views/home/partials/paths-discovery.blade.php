<link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url('css/content-clusters.css') }}">
<section class="home-paths" aria-labelledby="home-paths-title">
  <div class="container container--wide">
    <div class="home-paths__shell">
      <div class="home-paths__intro">
        <p class="eyebrow">Percorsi</p>
        <h2 id="home-paths-title">Un tema, dall'inizio.</h2>
        <p>Sequenze editoriali curate per orientarti tra gli articoli di Kairus senza perderti i passaggi essenziali.</p>
        <a class="home-paths__all" href="{{ route('percorsi.index') }}">Scopri tutti i percorsi <span aria-hidden="true">→</span></a>
      </div>
      <div class="home-paths__cards">
        @foreach($homePaths as $path)
          <a class="home-path-link" href="{{ route('percorsi.show', $path->slug) }}">
            <span class="home-path-link__visual">
              @if($path->cover_image)<img src="{{ asset('assets/img/'.ltrim($path->cover_image, '/')) }}" alt="" loading="lazy" width="184" height="184">@endif
            </span>
            <span class="home-path-link__copy">
              <span>{{ $path->published_articles_count }} {{ $path->published_articles_count === 1 ? 'articolo' : 'articoli' }}</span>
              <strong>{{ $path->name }}</strong>
              @if($path->short_description)<small>{{ Str::limit($path->short_description, 95) }}</small>@endif
            </span>
            <span class="home-path-link__arrow" aria-hidden="true">→</span>
          </a>
        @endforeach
      </div>
    </div>
  </div>
</section>
