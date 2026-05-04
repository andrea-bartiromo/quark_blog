@extends('layouts.app')
@section('title', config('laboratorio.name').' — '.config('laboratorio.tagline'))

@section('content')

{{-- Hero: articolo in evidenza --}}
@if($featured)
<section class="hero">
  <div class="container">
    <div class="hero-inner">
      <div>
        <div class="hero-eyebrow">
          ⭐ In evidenza
        </div>
        <h1 class="hero-title">
          {!! nl2br(e($featured->title)) !!}
        </h1>
        <p class="hero-excerpt">{{ Str::limit($featured->excerpt, 180) }}</p>
        <div class="hero-meta">
          <div class="author-chip">
            <div class="author-avatar">
              {{ mb_substr($featured->author->name, 0, 2) }}
            </div>
            <span class="author-name">{{ $featured->author->name }}</span>
          </div>
          <span class="meta-sep">·</span>
          <span class="meta-date">
            {{ $featured->published_at->locale('it')->isoFormat('D MMM YYYY') }}
          </span>
          <span class="meta-sep">·</span>
          <span class="meta-read">{{ $featured->read_minutes }} min</span>
          <span class="meta-tag">
            {{ config('laboratorio.categories.'.$featured->category) }}
          </span>
        </div>
        <div style="margin-top:1.25rem;">
          <a href="{{ route('articolo', $featured->slug) }}"
             class="btn btn--primary" style="font-size:.875rem;">
            Leggi l'articolo →
          </a>
        </div>
      </div>
      <div class="hero-img">
        <img src="{{ asset('assets/img/'.($featured->cover_image ?? 'hero-placeholder.svg')) }}"
             alt="{{ $featured->title }}" loading="eager">
      </div>
    </div>
  </div>
</section>
@endif

{{-- Contenuto principale --}}
<div class="container">
  <div class="page-layout">

    <section>

      {{-- Ultimi articoli --}}
      <div class="section-head" style="margin-top:0;">
        <h2>Ultimi articoli</h2>
        <a href="{{ route('notizie') }}">Vedi tutti →</a>
      </div>

      <div class="card-grid" style="margin-bottom:2.5rem;">
        @foreach($latest as $article)
        <a href="{{ route('articolo', $article->slug) }}" class="card" style="text-decoration:none;">
          <div class="card__img">
            <img src="{{ asset('assets/img/'.($article->cover_image ?? 'placeholder-1.svg')) }}"
                 alt="{{ $article->title }}" loading="lazy">
            <span class="card__cat-badge">
              {{ config('laboratorio.categories.'.$article->category) }}
            </span>
          </div>
          <div class="card__body">
            <div class="card__title">{{ $article->title }}</div>
            <div class="card__excerpt">{{ Str::limit($article->excerpt, 110) }}</div>
            <div class="card__footer">
              <div class="card__author">
                <div class="author-avatar">{{ mb_substr($article->author->name, 0, 2) }}</div>
                {{ Str::before($article->author->name, ' ') }}
              </div>
              <div class="card__stats">
                <span class="card__read">{{ $article->read_minutes }} min</span>
              </div>
            </div>
          </div>
        </a>
        @endforeach
      </div>

      {{-- Per categoria --}}
      @foreach($byCategory as $slug => $arts)
        @if($arts->count() > 0)
        <div style="margin-bottom:2.5rem;">
          <div class="section-head">
            <h2>{{ config('laboratorio.categories.'.$slug) }}</h2>
            <a href="{{ route('categoria', $slug) }}">Vedi tutti →</a>
          </div>
          @foreach($arts as $art)
          <a href="{{ route('articolo', $art->slug) }}" class="article-card" style="text-decoration:none;display:flex;">
            <div class="article-card__thumb">
              <img src="{{ asset('assets/img/'.($art->cover_image ?? 'placeholder-1.svg')) }}"
                   alt="{{ $art->title }}" loading="lazy">
            </div>
            <div class="article-card__body">
              <span class="article-card__cat">
                {{ config('laboratorio.categories.'.$art->category) }}
              </span>
              <div class="article-card__title">{{ $art->title }}</div>
              <div class="article-card__meta">
                <span>{{ $art->author->name }}</span>
                <span>·</span>
                <span>{{ $art->published_at->locale('it')->isoFormat('D MMM') }}</span>
                <span>·</span>
                <span>{{ $art->read_minutes }} min</span>
              </div>
            </div>
          </a>
          @endforeach
        </div>
        @endif
      @endforeach

    </section>

    {{-- Sidebar --}}
    <aside>
      @include('components.sidebar')
    </aside>

  </div>
</div>

@endsection
