@extends('layouts.app')
@section('title', config('laboratorio.name').' — '.config('laboratorio.tagline'))

@section('content')

@php
  $categories = config('laboratorio.categories');
  $fallbackTrending = $trending->count() ? $trending : $latest->take(5);
@endphp

{{-- Homepage magazine premium --}}
@if($featured)
<section class="magazine-hero">
  <div class="container">
    <div class="magazine-hero__grid">
      <article class="magazine-hero__main">
        <a href="{{ route('articolo', $featured->slug) }}" class="magazine-hero__image">
          <img src="{{ asset('assets/img/'.($featured->cover_image ?? 'hero-placeholder.svg')) }}"
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
          <p class="magazine-hero__excerpt">{{ Str::limit($featured->excerpt, 210) }}</p>

          <div class="magazine-meta">
            <div class="author-chip">
              <div class="author-avatar">{{ mb_substr($featured->author->name, 0, 2) }}</div>
              <span class="author-name">{{ $featured->author->name }}</span>
            </div>
            <span class="meta-sep">·</span>
            <span>{{ $featured->published_at->locale('it')->isoFormat('D MMM YYYY') }}</span>
            <span class="meta-sep">·</span>
            <span>{{ $featured->read_minutes }} min</span>
          </div>
        </div>
      </article>

      <aside class="magazine-hero__side">
        <div class="magazine-panel__head">
          <span>Trending now</span>
          <small>24h</small>
        </div>

        @foreach($fallbackTrending->take(4) as $item)
          <a href="{{ route('articolo', $item->slug) }}" class="trending-row">
            <span class="trending-row__rank">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
            <span class="trending-row__body">
              <span class="trending-row__cat">{{ $categories[$item->category] ?? $item->category }}</span>
              <strong>{{ Str::limit($item->title, 72) }}</strong>
              <em>{{ $item->read_minutes }} min di lettura</em>
            </span>
          </a>
        @endforeach
      </aside>
    </div>
  </div>
</section>
@endif

<div class="container">
  <section class="premium-newsletter-band">
    <div>
      <span class="magazine-kicker">Newsletter intelligence</span>
      <h2>La settimana scientifica, filtrata dalla redazione.</h2>
      <p>Ricevi analisi, storie e segnali emergenti da spazio, IA, energia, salute e ambiente.</p>
    </div>
    <form class="premium-newsletter-band__form" action="{{ route('newsletter.subscribe') }}" method="POST">
      @csrf
      <input type="email" name="email" placeholder="La tua email" required>
      <button type="submit">Iscriviti gratis</button>
    </form>
  </section>
</div>

<div class="container">
  <div class="page-layout magazine-layout">

    <section>
      <div class="section-head section-head--premium">
        <div>
          <span class="magazine-kicker">Latest from the newsroom</span>
          <h2>Ultimi articoli</h2>
        </div>
        <a href="{{ route('notizie') }}">Vedi tutti →</a>
      </div>

      <div class="newsroom-grid">
        @foreach($latest as $article)
        <a href="{{ route('articolo', $article->slug) }}" class="newsroom-card">
          <div class="newsroom-card__img">
            <img src="{{ asset('assets/img/'.($article->cover_image ?? 'placeholder-1.svg')) }}"
                 alt="{{ $article->title }}" loading="lazy">
            <span>{{ $categories[$article->category] ?? $article->category }}</span>
          </div>
          <div class="newsroom-card__body">
            <h3>{{ $article->title }}</h3>
            <p>{{ Str::limit($article->excerpt, 118) }}</p>
            <div class="magazine-meta">
              <span>{{ Str::before($article->author->name, ' ') }}</span>
              <span class="meta-sep">·</span>
              <span>{{ $article->published_at->locale('it')->isoFormat('D MMM') }}</span>
              <span class="meta-sep">·</span>
              <span>{{ $article->read_minutes }} min</span>
            </div>
          </div>
        </a>
        @endforeach
      </div>

      @foreach($byCategory as $slug => $arts)
        @if($arts->count() > 0)
        <section class="category-showcase">
          <div class="section-head section-head--premium">
            <div>
              <span class="magazine-kicker">Dossier</span>
              <h2>{{ $categories[$slug] ?? $slug }}</h2>
            </div>
            <a href="{{ route('categoria', $slug) }}">Vedi tutti →</a>
          </div>

          <div class="category-showcase__grid">
            @foreach($arts as $art)
            <a href="{{ route('articolo', $art->slug) }}" class="category-story {{ $loop->first ? 'category-story--lead' : '' }}">
              <div class="category-story__thumb">
                <img src="{{ asset('assets/img/'.($art->cover_image ?? 'placeholder-1.svg')) }}"
                     alt="{{ $art->title }}" loading="lazy">
              </div>
              <div class="category-story__body">
                <span>{{ $categories[$art->category] ?? $art->category }}</span>
                <h3>{{ $art->title }}</h3>
                @if($loop->first)
                  <p>{{ Str::limit($art->excerpt, 135) }}</p>
                @endif
                <div class="magazine-meta">
                  <span>{{ $art->published_at->locale('it')->isoFormat('D MMM') }}</span>
                  <span class="meta-sep">·</span>
                  <span>{{ $art->read_minutes }} min</span>
                </div>
              </div>
            </a>
            @endforeach
          </div>
        </section>
        @endif
      @endforeach
    </section>

    <aside class="magazine-sidebar">
      @include('components.sidebar')
    </aside>

  </div>
</div>

@endsection
