@extends('layouts.app')
@section('title', $categoryLabel.' — '.config('laboratorio.name'))
@section('description', 'Tutti gli articoli di Quark su '.$categoryLabel.': scienza, tecnologia e innovazione spiegate in modo moderno.')

@section('content')
<div class="public-shell">
  <div class="container container--wide">

    @if($categoryImage)
    <section class="category-premium-hero">
      <img src="{{ asset('assets/img/categories/'.$categoryImage) }}"
           alt="{{ $categoryLabel }}"
           loading="eager">

      <div class="category-premium-hero__content">
        <span class="public-hero__kicker">Quark Category</span>

        <h1>{{ $categoryLabel }}</h1>

        @if($categoryDescription)
        <p>{{ $categoryDescription }}</p>
        @endif

        <div class="public-hero__meta">
          <span>{{ $articles->total() }} articoli</span>
          <span>·</span>
          <span>Aggiornamento continuo</span>
        </div>
      </div>
    </section>
    @else
    <section class="public-hero public-hero--light">
      <span class="public-hero__kicker">Quark Category</span>
      <h1>{{ $categoryLabel }}</h1>
      <p>Approfondimenti, analisi e storie dedicate al mondo {{ strtolower($categoryLabel) }}.</p>
      <div class="public-hero__meta">
        <span>{{ $articles->total() }} articoli</span>
      </div>
    </section>
    @endif

    <section class="public-feature-band">
      <span class="public-hero__kicker">Editorial Focus</span>
      <h2>Dentro {{ $categoryLabel }}</h2>
      <p>
        Quark seleziona notizie, ricerca, scenari e innovazioni per raccontare
        il presente e il futuro di {{ strtolower($categoryLabel) }} con un linguaggio chiaro,
        visivo e accessibile.
      </p>

      @if($category === 'intelligenza-artificiale')
        <a href="{{ route('turing') }}">Esplora la Turing Experience →</a>
      @endif
    </section>

    <div class="public-premium-layout">
      <section>

        <div class="public-section-head">
          <div>
            <span>Latest stories</span>
            <h2>Ultimi articoli</h2>
          </div>
        </div>

        <div class="public-card-grid">
          @foreach($articles as $article)
          <a href="{{ route('articolo', $article->slug) }}" class="public-card">

            <div class="public-card__media">
              <img src="{{ asset('assets/img/'.($article->cover_image ?? 'placeholder-1.svg')) }}"
                   alt="{{ $article->title }}"
                   loading="lazy">

              <span class="public-card__badge">
                {{ $categoryLabel }}
              </span>
            </div>

            <div class="public-card__body">
              <h3>{{ $article->title }}</h3>

              <p>
                {{ Str::limit($article->excerpt, 118) }}
              </p>

              <div class="public-card__footer">
                <span>{{ Str::before($article->author->name, ' ') }}</span>
                <span>{{ $article->read_minutes }} min</span>
              </div>
            </div>

          </a>
          @endforeach
        </div>

        @if($articles->hasPages())
        <div style="margin-top:2rem;">
          {{ $articles->links('components.pagination') }}
        </div>
        @endif

      </section>

      <aside>
        <div class="public-sidebar-card">
          @include('components.sidebar')
        </div>
      </aside>
    </div>

  </div>
</div>
@endsection
