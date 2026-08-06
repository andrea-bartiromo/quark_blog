<header class="article-premium__hero">
  <img src="{{ $cover }}" alt="{{ $article->cover_alt ?: $article->title }}" loading="eager" decoding="async">
  <div class="article-premium__overlay"></div>

  <x-media.image-viewer
      :src="$cover"
      :alt="$article->cover_alt ?: $article->title"
      :title="$article->title"
      :caption="$article->cover_caption"
      :credit="$article->cover_credit"
      :source="$article->cover_source"
      :sourceUrl="$article->cover_source_url"
      :license="$article->cover_license"
  />

  <div class="article-premium__content">
    <a href="{{ route('categoria', $article->category) }}" class="public-hero__kicker" style="text-decoration:none;">
      {{ $categoryLabel }}
    </a>

    <h1>{{ $article->title }}</h1>

    @if($article->excerpt)
    <p class="article-premium__excerpt">
      {{ $article->excerpt }}
    </p>
    @endif

    <div class="article-premium__meta">
      <span>{{ $article->author->name }}</span>
      <span>·</span>
      <time datetime="{{ $article->published_at->toDateString() }}">
        {{ $article->published_at->locale('it')->isoFormat('D MMMM YYYY') }}
      </time>
      <span>·</span>
      <span>{{ $article->read_minutes }} min di lettura</span>
      <span>·</span>
      <span>{{ number_format($article->views, 0, ',', '.') }} visualizzazioni</span>
    </div>
  </div>
</header>
