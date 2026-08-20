<section class="home-editorial-section">
  <div class="home-section-head">
    <div>
      <span>Latest from Kairus</span>
      <h2>Ultimi articoli</h2>
    </div>
    <a href="{{ route('notizie') }}">Vedi tutti →</a>
  </div>

  <div class="home-editorial-grid">
    @foreach($latest->take(6) as $article)
    <a href="{{ route('articolo', $article->slug) }}" class="home-editorial-card {{ $loop->first ? 'home-editorial-card--lead' : '' }}">
      <div class="home-editorial-card__media">
        <x-responsive-image
          :diskName="$article->cover_image ?: null"
          :src="$visualFor($article)"
          :onerrorSrc="$visualFor($article)"
          :alt="$article->title"
          :sizes="'(max-width: 640px) 100vw, (max-width: 820px) 50vw, 33vw'"
        />
      </div>
      <div class="home-editorial-card__body">
        <span>{{ $categoryLabel($article) }}</span>
        <h3>{{ $article->title }}</h3>
        <p>{{ Str::limit($article->excerpt, $loop->first ? 155 : 96) }}</p>
        <div class="magazine-meta">
          <span>{{ Str::before($article->author->name, ' ') }}</span>
          <span class="meta-sep">·</span>
          <span>{{ $article->read_minutes }} min</span>
        </div>
      </div>
    </a>
    @endforeach
  </div>
</section>
