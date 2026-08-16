{{-- Ticker notizie Kairus --}}
@php
  $tickerArticles = \App\Models\Article::published()
    ->orderByDesc('published_at')
    ->limit(8)
    ->get(['title', 'slug']);
@endphp

@if($tickerArticles->count() > 0)
<div class="ticker" aria-label="Ultimi articoli">
  <div class="ticker-label">🔬 Nuovo</div>
  <div class="ticker-viewport">
    <div class="ticker-track">
      <div class="ticker-sequence">
        @foreach($tickerArticles as $a)
          <span class="ticker-item">
            <a href="{{ route('articolo', $a->slug) }}">{{ $a->title }}</a>
          </span>
          <span class="ticker-sep">·</span>
        @endforeach
      </div>
      {{-- Copie esclusivamente grafiche: quattro sequenze garantiscono un loop continuo anche su viewport ampie. --}}
      @for($copy = 0; $copy < 3; $copy++)
        <div class="ticker-sequence" aria-hidden="true" inert>
          @foreach($tickerArticles as $a)
            <span class="ticker-item">
              <a href="{{ route('articolo', $a->slug) }}" tabindex="-1">{{ $a->title }}</a>
            </span>
            <span class="ticker-sep">·</span>
          @endforeach
        </div>
      @endfor
    </div>
  </div>
</div>
@endif
