@extends('layouts.app')
@section('title', 'Tutti gli articoli — '.config('laboratorio.name'))
@section('description', 'Tutti gli articoli di Quark: scienza, tecnologia e innovazione spiegate in modo semplice.')

@section('content')
<div class="container" style="padding-block:2rem;">
  <div class="page-layout">
    <section>
      <div class="section-head" style="margin-top:0;">
        <h2>Tutti gli articoli</h2>
        <span style="font-size:.78rem;color:var(--ink-muted);">{{ $articles->total() }} articoli</span>
      </div>

      {{-- Tag pills --}}
      <div class="tag-pills" style="margin-bottom:1.5rem;">
        <a href="{{ route('notizie') }}"
           class="tag-pill {{ !request('categoria') ? 'active' : '' }}">Tutti</a>
        @foreach(config('laboratorio.categories') as $slug => $label)
          <a href="{{ route('categoria', $slug) }}" class="tag-pill">{{ $label }}</a>
        @endforeach
      </div>

      <div class="card-grid">
        @foreach($articles as $article)
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
