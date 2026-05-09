@extends('layouts.app')

@section('title', $author->name.' — Redattore — '.config('laboratorio.name'))
@section('description', 'Articoli di '.$author->name.' su '.config('laboratorio.name').', rivista italiana di divulgazione scientifica.')

@section('content')
<div class="public-page public-page--author">
  <div class="container">

    <section class="author-premium-hero">
      <div class="author-premium-hero__avatar" aria-hidden="{{ $author->avatar ? 'false' : 'true' }}">
        @if($author->avatar)
          <img src="{{ asset('storage/'.$author->avatar) }}" alt="{{ $author->name }}">
        @else
          <span>{{ mb_substr($author->name, 0, 1) }}</span>
        @endif
      </div>

      <div class="author-premium-hero__content">
        <span class="public-hero__kicker">Redattore Quark</span>
        <h1>{{ $author->name }}</h1>

        @if($author->bio)
          <p>{{ $author->bio }}</p>
        @else
          <p>Una raccolta editoriale degli articoli pubblicati su {{ config('laboratorio.name') }}.</p>
        @endif

        <div class="author-premium-hero__links">
          @if($author->twitter)
            <a href="https://twitter.com/{{ ltrim($author->twitter,'@') }}" target="_blank" rel="noopener">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
              {{ $author->twitter }}
            </a>
          @endif

          @if($author->email && $author->role === 'editor')
            <a href="mailto:{{ $author->email }}">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,12 2,6"/></svg>
              Contatta
            </a>
          @endif
        </div>
      </div>

      <dl class="author-premium-stats">
        <div>
          <dt>Articoli</dt>
          <dd>{{ $articles->total() }}</dd>
        </div>
        <div>
          <dt>Letture</dt>
          <dd>{{ number_format($articles->sum('views'), 0, ',', '.') }}</dd>
        </div>
      </dl>
    </section>

    <div class="public-premium-layout public-premium-layout--content-first">
      <section>
        <div class="public-section-head">
          <div>
            <span>Archivio autore</span>
            <h2>Articoli pubblicati</h2>
          </div>
        </div>

        <div class="public-list-stack">
          @forelse($articles as $article)
            <a href="{{ route('articolo', $article->slug) }}" class="public-result-card">
              <figure class="public-result-card__media">
                <img src="{{ asset('assets/img/'.($article->cover_image ?? 'placeholder-1.svg')) }}" alt="{{ $article->title }}" loading="lazy">
              </figure>

              <div class="public-result-card__body">
                <div class="public-result-card__meta">
                  <span class="badge badge--{{ $article->category }}">{{ config('laboratorio.categories.'.$article->category) }}</span>
                  <time datetime="{{ $article->published_at->toDateString() }}">{{ $article->published_at->locale('it')->isoFormat('D MMM YYYY') }}</time>
                  <span class="dot">·</span>
                  <span>{{ $article->read_minutes }} min</span>
                </div>

                <h3>{{ $article->title }}</h3>

                @if($article->excerpt)
                  <p>{{ Str::limit($article->excerpt, 140) }}</p>
                @endif
              </div>
            </a>
          @empty
            <div class="public-empty-state">
              <span>✍️</span>
              <h3>Nessun articolo pubblicato ancora</h3>
              <p>Quando {{ $author->name }} pubblicherà nuovi contenuti, li troverai raccolti qui.</p>
            </div>
          @endforelse
        </div>

        @if($articles->hasPages())
          <div class="public-pagination-wrap">
            {{ $articles->links('components.pagination') }}
          </div>
        @endif
      </section>

      <aside>
        @include('components.sidebar')
      </aside>
    </div>
  </div>
</div>
@endsection
