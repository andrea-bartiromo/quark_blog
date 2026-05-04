{{-- Ticker notizie Quark --}}
@php
  $tickerArticles = \App\Models\Article::published()
    ->orderByDesc('published_at')
    ->limit(8)
    ->get(['title', 'slug']);
@endphp

@if($tickerArticles->count() > 0)
<div class="ticker" aria-label="Ultimi articoli">
  <div class="ticker-label">🔬 Nuovo</div>
  <div style="overflow:hidden;flex:1;">
    <div class="ticker-track">
      @foreach($tickerArticles as $a)
        <span class="ticker-item">
          <a href="{{ route('articolo', $a->slug) }}">{{ $a->title }}</a>
        </span>
        <span class="ticker-sep">·</span>
      @endforeach
      {{-- Duplicate per loop continuo --}}
      @foreach($tickerArticles as $a)
        <span class="ticker-item">
          <a href="{{ route('articolo', $a->slug) }}">{{ $a->title }}</a>
        </span>
        <span class="ticker-sep">·</span>
      @endforeach
    </div>
  </div>
</div>
@endif
