@extends('layouts.app')
@section('title', $categoryLabel.' — '.config('laboratorio.name'))
@section('description', 'Tutti gli articoli di Quark su '.$categoryLabel.': scienza semplice e curiosa.')

@section('content')
<div class="container" style="padding-block:2rem;">
  <div class="page-layout">
    <section>
      <div style="margin-bottom:1.5rem;">
        <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.35rem;">
          <span class="badge badge--{{ $category }}" style="font-size:.72rem;">{{ $categoryLabel }}</span>
        </div>
        <h1 style="font-family:var(--font-display);font-size:1.8rem;font-weight:900;color:var(--ink);letter-spacing:-.02em;">
          {{ $categoryLabel }}
        </h1>
        <p style="font-size:.9rem;color:var(--ink-muted);margin-top:.3rem;">
          {{ $articles->total() }} articoli
        </p>
      </div>

      <div class="card-grid">
        @foreach($articles as $article)
        <a href="{{ route('articolo', $article->slug) }}" class="card" style="text-decoration:none;">
          <div class="card__img">
            <img src="{{ asset('assets/img/'.($article->cover_image ?? 'placeholder-1.svg')) }}"
                 alt="{{ $article->title }}" loading="lazy">
          </div>
          <div class="card__body">
            <div class="card__title">{{ $article->title }}</div>
            <div class="card__excerpt">{{ Str::limit($article->excerpt, 100) }}</div>
            <div class="card__footer">
              <div class="card__author">
                <div class="author-avatar">{{ mb_substr($article->author->name, 0, 2) }}</div>
                {{ Str::before($article->author->name, ' ') }}
              </div>
              <span class="card__read">{{ $article->read_minutes }} min</span>
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
      @include('components.sidebar')
    </aside>
  </div>
</div>
@endsection
