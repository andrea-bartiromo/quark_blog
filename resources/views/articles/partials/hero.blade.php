<header class="article-premium__hero">
  <img src="{{ $cover }}" alt="{{ $article->cover_alt ?: $article->title }}" loading="eager">
  <div class="article-premium__overlay"></div>

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
