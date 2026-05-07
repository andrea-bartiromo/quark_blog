@extends('layouts.app')
@section('title', config('laboratorio.name').' — '.config('laboratorio.tagline'))

@section('content')

@php
  $categories = config('laboratorio.categories');
  $fallbackTrending = $trending->count() ? $trending : $latest->take(5);
  $categoryHighlights = collect($byCategory)->map(fn($arts) => $arts->first())->filter();

  $visualFor = function ($article) {
      $category = $article->category ?? 'default';
      return asset('assets/img/qk-'.$category.'.svg');
  };

  $imageFor = function ($article) use ($visualFor) {
      return $article->cover_image
          ? asset('assets/img/'.$article->cover_image)
          : $visualFor($article);
  };
@endphp

@if($featured)
<section class="home-premium-hero">
  <div class="container container--wide">
    <div class="home-premium-hero__grid">
      <article class="home-lead-story">
        <a href="{{ route('articolo', $featured->slug) }}" class="home-lead-story__media">
          <img src="{{ $imageFor($featured) }}"
               onerror="this.onerror=null;this.src='{{ $visualFor($featured) }}';"
               alt="{{ $featured->title }}" loading="eager">
          <span>In evidenza</span>
        </a>

        <div class="home-lead-story__content">
          <span class="magazine-kicker">{{ $categories[$featured->category] ?? $featured->category }} · Newsroom selection</span>
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
              <small>{{ $categories[$item->category] ?? $item->category }}</small>
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

<div class="container container--wide">
  <section class="home-newsletter-band">
    <div class="home-newsletter-band__icon" aria-hidden="true">✉</div>
    <div>
      <span>Newsletter intelligence</span>
      <h2>La settimana scientifica, filtrata dalla redazione.</h2>
      <p>Analisi, storie e segnali emergenti da spazio, IA, energia, salute e ambiente.</p>
    </div>
    <form action="{{ route('newsletter.subscribe') }}" method="POST">
      @csrf
      <input type="email" name="email" placeholder="La tua email" required>
      <button type="submit">Iscriviti gratis</button>
    </form>
  </section>
</div>

<div class="container container--wide">
  <section class="home-editorial-section">
    <div class="home-section-head">
      <div>
        <span>Latest from Quark</span>
        <h2>Ultimi articoli</h2>
      </div>
      <a href="{{ route('notizie') }}">Vedi tutti →</a>
    </div>

    <div class="home-editorial-grid">
      @foreach($latest->take(6) as $article)
      <a href="{{ route('articolo', $article->slug) }}" class="home-editorial-card {{ $loop->first ? 'home-editorial-card--lead' : '' }}">
        <div class="home-editorial-card__media">
          <img src="{{ $imageFor($article) }}"
               onerror="this.onerror=null;this.src='{{ $visualFor($article) }}';"
               alt="{{ $article->title }}" loading="lazy">
        </div>
        <div class="home-editorial-card__body">
          <span>{{ $categories[$article->category] ?? $article->category }}</span>
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
          <img src="{{ $visualFor($art) }}"
               onerror="this.onerror=null;this.src='{{ asset('assets/img/qk-default.svg') }}';"
               alt="{{ $categories[$art->category] ?? $art->category }}" loading="lazy">
          <div>
            <strong>{{ $categories[$art->category] ?? $art->category }} →</strong>
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
</div>

@endsection
