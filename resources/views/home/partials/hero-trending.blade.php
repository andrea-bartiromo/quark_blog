@if($featured)
<section class="home-premium-hero">
  <div class="container container--wide">
    <div class="home-premium-hero__grid">
      <article class="home-lead-story">
        <a href="{{ route('articolo', $featured->slug) }}" class="home-lead-story__media">
          <img
            src="{{ $imageForArticle($featured, 0) }}"
            onerror="this.onerror=null;this.src='{{ $visualFor($featured) }}';"
            alt="{{ $featured->title }}"
            class="home-lead-story__hero-image"
            loading="eager">

          <span>In evidenza</span>
        </a>

        <div class="home-lead-story__content">
          <span class="magazine-kicker">{{ $categoryLabel($featured) }} · Newsroom selection</span>
          <h1><a href="{{ route('articolo', $featured->slug) }}">{!! nl2br(e($featured->title)) !!}</a></h1>
          <p>{{ Str::limit($featured->excerpt, 230) }}</p>
          <div class="magazine-meta magazine-meta--hero">
            <span>{{ $featured->author->name }}</span>
            <span class="meta-sep">·</span>
            <span>{{ $featured->published_at->locale('it')->isoFormat('D MMM YYYY') }}</span>
            <span class="meta-sep">·</span>
            <span>{{ $featured->read_minutes }} min di lettura</span>
          </div>
        </div>
      </article>

      <aside class="home-trending-panel">
        <div class="home-trending-panel__head">
          <span>Trending now</span>
          <small>24h</small>
        </div>

        @foreach($fallbackTrending->take(5) as $item)
          <a href="{{ route('articolo', $item->slug) }}" class="home-trending-item">
            <span>{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
            <div>
              <small>{{ $categoryLabel($item) }}</small>
              <strong>{{ Str::limit($item->title, 84) }}</strong>
              <em>{{ $item->read_minutes }} min di lettura</em>
            </div>
          </a>
        @endforeach
      </aside>
    </div>
  </div>
</section>
@endif
