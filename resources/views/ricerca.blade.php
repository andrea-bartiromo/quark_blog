@extends('layouts.app')
@section('title', ($query ? 'Ricerca: '.e($query) : 'Ricerca').' — '.config('laboratorio.name'))

@section('content')
<div class="public-page public-page--search">
  <div class="container">
    <section class="public-hero public-hero--light public-hero--compact">
      <span class="public-hero__kicker">Esplora l’archivio</span>
      <h1>
        @if($query)
          Risultati per “{{ $query }}”
        @else
          Ricerca avanzata
        @endif
      </h1>
      <p>Cerca articoli, autori, categorie e finestre temporali nell’archivio editoriale di {{ config('laboratorio.name') }}.</p>

      @if($results instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <div class="public-hero__meta">
          <span>{{ $results->total() }} articoli trovati</span>
        </div>
      @endif
    </section>

    <div class="public-premium-layout">
      <section>
        <form method="GET" action="{{ route('ricerca') }}" class="premium-search-panel">
          <div class="premium-search-panel__main">
            <label class="sr-only" for="premium-search-input">Cerca nel sito</label>
            <input id="premium-search-input" type="text" name="q" value="{{ $query }}" placeholder="Cerca scoperte, tecnologie, spazio…" autocomplete="off">
            <button type="submit">Cerca</button>
          </div>

          <details class="premium-filter-panel" {{ ($category || $authorId || $from || $to) ? 'open' : '' }}>
            <summary>
              <span>Filtri avanzati</span>
              @if($category || $authorId || $from || $to)
                <strong>attivi</strong>
              @endif
            </summary>

            <div class="premium-filter-panel__grid">
              <div class="premium-field">
                <label for="search-category">Categoria</label>
                <select id="search-category" name="categoria">
                  <option value="">Tutte</option>
                  @foreach($categories as $val => $label)
                    <option value="{{ $val }}" {{ $category === $val ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
              </div>

              <div class="premium-field">
                <label for="search-author">Autore</label>
                <select id="search-author" name="autore">
                  <option value="">Tutti</option>
                  @foreach($authors as $au)
                    <option value="{{ $au->id }}" {{ (string)$authorId === (string)$au->id ? 'selected' : '' }}>{{ $au->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="premium-field">
                <label for="search-from">Dal</label>
                <input id="search-from" type="date" name="da" value="{{ $from }}">
              </div>

              <div class="premium-field">
                <label for="search-to">Al</label>
                <input id="search-to" type="date" name="a" value="{{ $to }}">
              </div>

              <div class="premium-filter-panel__actions">
                <button type="submit">Applica</button>
                <a href="{{ route('ricerca') }}">Reset</a>
              </div>
            </div>
          </details>
        </form>

        @if($results instanceof \Illuminate\Contracts\Pagination\Paginator && $results->count() > 0)
          <div class="public-list-stack">
            @foreach($results as $article)
              <a href="{{ route('articolo', $article->slug) }}" class="public-result-card">
                <figure class="public-result-card__media">
                  <img src="{{ asset('assets/img/'.($article->cover_image ?? 'placeholder-1.svg')) }}" alt="{{ $article->title }}" loading="lazy">
                </figure>

                <div class="public-result-card__body">
                  <div class="public-result-card__meta">
                    <span class="badge badge--{{ $article->category }}">{{ config('laboratorio.categories.'.$article->category) }}</span>
                    <span>{{ $article->author->name }}</span>
                  </div>

                  <h3>{{ $article->title }}</h3>

                  @if($article->excerpt)
                    <p>{{ Str::limit($article->excerpt, 140) }}</p>
                  @endif

                  <div class="public-result-card__footer">
                    <time datetime="{{ $article->published_at->toDateString() }}">{{ $article->published_at->locale('it')->isoFormat('D MMM YYYY') }}</time>
                    <span class="dot">·</span>
                    <span>{{ $article->read_minutes }} min</span>
                  </div>
                </div>
              </a>
            @endforeach
          </div>

          @if($results->hasPages())
            <div class="public-pagination-wrap">
              {{ $results->links('components.pagination') }}
            </div>
          @endif
        @elseif(request()->hasAny(['q','categoria','autore','da','a']))
          <div class="public-empty-state">
            <span>🔍</span>
            <h3>Nessun risultato trovato</h3>
            <p>Prova a rimuovere qualche filtro o cerca una parola chiave più ampia.</p>
            <a href="{{ route('ricerca') }}">Rimuovi i filtri</a>
          </div>
        @else
          <div class="public-empty-state public-empty-state--soft">
            <span>⌕</span>
            <h3>Inizia una ricerca</h3>
            <p>Usa la barra in alto per esplorare l’archivio Quark.</p>
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
