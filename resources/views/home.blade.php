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
<section class="magazine-hero">
  <div class="container container--wide">
    <div class="magazine-hero__grid">
      <article class="magazine-hero__main">
        <a href="{{ route('articolo', $featured->slug) }}" class="magazine-hero__image">
          <img src="{{ $imageFor($featured) }}"
               onerror="this.onerror=null;this.src='{{ $visualFor($featured) }}';"
               alt="{{ $featured->title }}" loading="eager">
          <span class="magazine-hero__badge">In evidenza</span>
        </a>

        <div class="magazine-hero__content">
          <div class="magazine-kicker">
            {{ $categories[$featured->category] ?? $featured->category }} · Newsroom selection
          </div>
          <h1 class="magazine-hero__title">
            <a href="{{ route('articolo', $featured->slug) }}">{!! nl2br(e($featured->title)) !!}</a>
          </h1>
          <p class="magazine-hero__excerpt">{{ Str::limit($featured->excerpt, 220) }}</p>

          <div class="magazine-meta">
            <div class="author-chip">
              <div class="author-avatar">{{ mb_substr($featured->author->name, 0, 2) }}</div>
              <span class="author-name">{{ $featured->author->name }}</span>
            </div>
            <span class="meta-sep">·</span>
            <span>{{ $featured->published_at->locale('it')->isoFormat('D MMM YYYY') }}</span>
            <span class="meta-sep">·</span>
            <span>{{ $featured->read_minutes }} min di lettura</span>
          </div>
        </div>
      </article>

      <aside class="magazine-hero__side">
        <div class="magazine-panel__head">
          <span>Trending now</span>
          <small>24h</small>
        </div>

        @foreach($fallbackTrending->take(5) as $item)
          <a href="{{ route('articolo', $item->slug) }}" class="trending-row">
            <span class="trending-row__rank">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
            <span class="trending-row__body">
              <span class="trending-row__cat">{{ $categories[$item->category] ?? $item->category }}</span>
              <strong>{{ Str::limit($item->title, 82) }}</strong>
              <em>{{ $item->read_minutes }} min di lettura</em>
            </span>
          </a>
        @endforeach
      </aside>
    </div>
  </div>
</section>
@endif

<div class="container container--wide">
  <section class="premium-newsletter-band">
    <div class="premium-newsletter-band__icon" aria-hidden="true">✉</div>
    <div>
      <span class="magazine-kicker">Newsletter intelligence</span>
      <h2>La settimana scientifica, filtrata dalla redazione.</h2>
      <p>Ricevi analisi, storie e insight su spazio, IA, energia, salute e ambiente.</p>
    </div>
    <form class="premium-newsletter-band__form" action="{{ route('newsletter.subscribe') }}" method="POST">
      @csrf
      <input type="email" name="email" placeholder="La tua email" required>
      <button type="submit">Iscriviti gratis</button>
    </form>
  </section>
</div>

<div class="container container--wide">
  <div class="page-layout magazine-layout">

    <section>
      <div class="section-head section-head--premium">
        <div>
          <h2>Ultimi articoli</h2>
        </div>
        <a href="{{ route('notizie') }}">Vedi tutti →</a>
      </div>

      <div class="newsroom-grid">
        @foreach($latest->take(4) as $article)
        <a href="{{ route('articolo', $article->slug) }}" class="newsroom-card">
          <div class="newsroom-card__img">
            <img src="{{ $imageFor($article) }}"
                 onerror="this.onerror=null;this.src='{{ $visualFor($article) }}';"
                 alt="{{ $article->title }}" loading="lazy">
          </div>
          <div class="newsroom-card__body">
            <span class="newsroom-card__cat">{{ $categories[$article->category] ?? $article->category }}</span>
            <h3>{{ $article->title }}</h3>
            <p>{{ Str::limit($article->excerpt, 105) }}</p>
            <div class="magazine-meta">
              <span>{{ Str::before($article->author->name, ' ') }}</span>
              <span class="meta-sep">·</span>
              <span>{{ $article->read_minutes }} min</span>
            </div>
          </div>
        </a>
        @endforeach
      </div>

      <section class="category-strip">
        <div class="section-head section-head--premium">
          <div>
            <h2>Esplora le categorie</h2>
          </div>
        </div>

        <div class="category-strip__grid">
          @foreach($categoryHighlights->take(6) as $art)
            <a href="{{ route('categoria', $art->category) }}" class="category-tile">
              <img src="{{ $visualFor($art) }}"
                   onerror="this.onerror=null;this.src='{{ asset('assets/img/qk-default.svg') }}';"
                   alt="{{ $categories[$art->category] ?? $art->category }}" loading="lazy">
              <span>{{ $categories[$art->category] ?? $art->category }} →</span>
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
            </a>
          @endforeach
        </div>
      </section>
    </section>

    <aside class="magazine-sidebar">
      @include('components.sidebar')
    </aside>

  </div>
</div>

@endsection
