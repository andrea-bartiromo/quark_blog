@extends('layouts.app')

@section('title', $author->name.' — Redattore — '.config('laboratorio.name'))
@section('description', 'Articoli di '.$author->name.' su '.config('laboratorio.name').', rivista italiana di divulgazione scientifica.')
@section('canonical', $articles->currentPage() > 1
    ? route('autore', ['user' => $author, 'page' => $articles->currentPage()])
    : route('autore', $author))

@if($isThinAuthorProfile)
  @section('robots', 'noindex,follow')
@endif

@section('head')
@php
    $authorRoleLabel = $author->role === 'author' ? 'Collaboratore Kairus' : 'Redazione Kairus';
    $personSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Person',
        'name' => $author->name,
        'url' => route('autore', $author),
        'jobTitle' => $authorRoleLabel,
    ];

    if ($author->bio) {
        $personSchema['description'] = $author->bio;
    }

    $authorSameAs = array_values(array_filter([
        $author->twitter ? 'https://twitter.com/'.ltrim($author->twitter, '@') : null,
        $author->linkedin && filter_var($author->linkedin, FILTER_VALIDATE_URL)
            && in_array(parse_url($author->linkedin, PHP_URL_SCHEME), ['http', 'https'], true)
                ? $author->linkedin
                : null,
    ]));

    if ($authorSameAs !== []) {
        $personSchema['sameAs'] = $authorSameAs;
    }
@endphp
<script type="application/ld+json">{!! json_encode($personSchema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection

@section('content')
<div class="public-page public-page--author">
  <div class="container">

    <section class="author-premium-hero">
      <div class="author-premium-hero__avatar" aria-hidden="{{ $author->photo ? 'false' : 'true' }}">
        @if($author->photo)
          <x-responsive-image
              disk-name="{{ $author->photo }}"
              alt="{{ $author->name }}"
              sizes="(max-width: 980px) 104px, 118px"
              loading="eager"
          />
        @else
          <span>{{ mb_substr($author->name, 0, 1) }}</span>
        @endif
      </div>

      <div class="author-premium-hero__content">
        <span class="public-hero__kicker">{{ $authorRoleLabel }}</span>
        <h1>{{ $author->name }}</h1>

        @if($author->bio)
          <p>{{ $author->bio }}</p>
        @else
          <p>Una raccolta editoriale degli articoli pubblicati su {{ config('laboratorio.name') }}.</p>
        @endif

        <div class="author-premium-hero__links">
          @if($author->twitter)
            <a href="https://twitter.com/{{ ltrim($author->twitter,'@') }}" target="_blank" rel="external noopener noreferrer">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
              {{ $author->twitter }}
            </a>
          @endif

          @if($author->linkedin && filter_var($author->linkedin, FILTER_VALIDATE_URL) && in_array(parse_url($author->linkedin, PHP_URL_SCHEME), ['http', 'https'], true))
            <a href="{{ $author->linkedin }}" target="_blank" rel="external noopener noreferrer">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.446-2.136 2.94v5.666H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 1 1 0-4.124 2.062 2.062 0 0 1 0 4.124zM7.114 20.452H3.56V9h3.554v11.452z"/></svg>
              LinkedIn
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
                <x-responsive-image
                    disk-name="{{ $article->cover_image ?? 'placeholder-1.svg' }}"
                    alt="{{ $article->title }}"
                    sizes="180px"
                    onerror-src="{{ asset('assets/img/placeholder-1.svg') }}"
                />
              </figure>

              <div class="public-result-card__body">
                <div class="public-result-card__meta">
                  <span class="badge badge--{{ $article->category }}">{{ $categoryOptions[$article->category] ?? $article->category }}</span>
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
