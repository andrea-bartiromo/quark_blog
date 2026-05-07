@extends('layouts.app')
@section('title', config('laboratorio.name').' — '.config('laboratorio.tagline'))

@section('content')

@php
  $categories = config('laboratorio.categories');
  $fallbackTrending = $trending->count() ? $trending : $latest->take(5);
  $categoryHighlights = collect($byCategory)->map(fn($arts) => $arts->first())->filter();

  $categoryLabel = fn($article) => $categories[$article->category] ?? $article->category;

  $visualFor = function ($article) {
      $category = $article->category ?? 'default';
      return asset('assets/img/qk-'.$category.'.svg');
  };

  $categoryImages = [
      'intelligenza-artificiale' => 'hero-ai-premium.png',
      'spazio'                   => 'hero-spazio.png',
      'energia'                  => 'hero-energia.png',
      'ambiente'                 => 'hero-ambiente.png',
      'salute'                   => 'hero-salute.png',
  ];

  $articleImages = [
      // Immagini specifiche per articolo. Aggiungi qui nuovi slug per evitare ripetizioni.
      'iride-la-costellazione-satellitare-italiana-24-satelliti-in-orbita-finanziata-dal-pnrr' => 'hero-spazio.png',
      'artemis-2-litalia-porta-luomo-intorno-alla-luna-con-il-modulo-di-servizio-esm' => 'hero-ai-premium.png',
      'record-del-fotovoltaico-italiano-nel-2025-443-twh-il-solare-supera-lidroelettrico' => 'hero-energia.png',
  ];

  $rotatingFallbackImages = [
      'hero-ai-premium.png',
      'hero-spazio.png',
      'hero-energia.png',
      'hero-ambiente.png',
      'hero-salute.png',
  ];

  $imageForArticle = function ($article, $position = 0) use ($articleImages, $categoryImages, $rotatingFallbackImages, $visualFor) {
      $slug = $article->slug ?? null;
      $category = $article->category ?? 'default';

      if ($slug && isset($articleImages[$slug])) {
          return asset('assets/img/'.$articleImages[$slug]);
      }

      // Se l'articolo ha una cover nel database, preferiscila.
      if (filled($article->cover_image)) {
          return asset('assets/img/'.$article->cover_image);
      }

      // Per la homepage, evita ripetizioni: usa una rotazione stabile per posizione/id.
      $seed = ($article->id ?? abs(crc32($slug ?? $article->title ?? 'quark'))) + $position;
      $file = $rotatingFallbackImages[$seed % count($rotatingFallbackImages)];

      return asset('assets/img/'.$file);
  };

  $imageForCategory = function ($article, $position = 0) use ($categoryImages, $rotatingFallbackImages, $visualFor) {
      $category = $article->category ?? 'default';

      if (isset($categoryImages[$category])) {
          return asset('assets/img/'.$categoryImages[$category]);
      }

      $file = $rotatingFallbackImages[$position % count($rotatingFallbackImages)];
      return asset('assets/img/'.$file);
  };
@endphp

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
          <img
            src="{{ $imageForArticle($article, $loop->index + 1) }}"
            onerror="this.onerror=null;this.src='{{ $visualFor($article) }}';"
            alt="{{ $article->title }}"
            loading="lazy">
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
</div>

@endsection
