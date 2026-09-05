@extends('layouts.app')

@section('title', ($query ? 'Ricerca: '.e($query) : 'Ricerca').' — '.config('laboratorio.name'))
@section('description', 'Cerca articoli, autori e categorie nell’archivio editoriale di Kairus.')
@section('robots', 'noindex,follow')

@section('content')
{{--
    Cantiere G — Search Visual Adoption (Prompt 162-174). Testata:
    "public-hero--compact" era già una superficie piatta senza immagine
    di sfondo — adottata con x-kairus.page-header (prop compact) senza
    perdere alcun contenuto, stesso caso di Notizie/Categoria (Cantiere
    F). Form: x-kairus.form-shell avvolge il <form> reale INVARIATO
    (method/action/nomi campi/validazione/progressive enhancement, il
    componente non renderizza mai un proprio <form>) — solo
    kairus-focusable aggiunto agli elementi interattivi che non avevano
    ancora un :focus-visible dedicato (input/select/pulsanti/reset:
    verificato in premium-search-panel*/premium-field*/
    premium-filter-panel__actions in public-unified.css, nessuna regola
    trovata). Risultati Percorso/Concetto: non applicabile —
    ArticleSearchService restituisce solo articoli (vedi
    SEARCH_REFRESH_FILEMAP.md), backend non esteso per crearne.
--}}
<div class="public-page public-page--search">
  <div class="container">
    <x-kairus.page-header
        eyebrow="Esplora l’archivio"
        :title="$query ? 'Risultati per “'.$query.'”' : 'Ricerca avanzata'"
        lead="Cerca articoli, autori, categorie e finestre temporali nell’archivio editoriale di {{ config('laboratorio.name') }}."
        :compact="true"
    >
      @if($results instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <x-slot:meta>
          <span>{{ $results->total() }} articoli trovati</span>
        </x-slot:meta>
      @endif
    </x-kairus.page-header>

    <div class="public-premium-layout">
      <section>
        <x-kairus.form-shell title="Cerca nel sito">
          <x-slot:form>
            <form method="GET" action="{{ route('ricerca') }}" class="premium-search-panel">
              <div class="premium-search-panel__main">
                <label class="sr-only" for="premium-search-input">Cerca nel sito</label>
                <input id="premium-search-input" type="text" name="q" value="{{ $query }}" placeholder="Cerca scoperte, tecnologie, spazio…" autocomplete="off" class="kairus-focusable">
                <button type="submit" class="kairus-focusable">Cerca</button>
              </div>

              <details class="premium-filter-panel" {{ ($category || $authorId || $from || $to) ? 'open' : '' }}>
                <summary class="kairus-focusable">
                  <span>Filtri avanzati</span>
                  @if($category || $authorId || $from || $to)
                    <strong>attivi</strong>
                  @endif
                </summary>

                <div class="premium-filter-panel__grid">
                  <div class="premium-field">
                    <label for="search-category">Categoria</label>
                    <select id="search-category" name="categoria" class="kairus-focusable">
                      <option value="">Tutte</option>
                      @foreach($categories as $val => $label)
                        <option value="{{ $val }}" {{ $category === $val ? 'selected' : '' }}>{{ $label }}</option>
                      @endforeach
                    </select>
                  </div>

                  <div class="premium-field">
                    <label for="search-author">Autore</label>
                    <select id="search-author" name="autore" class="kairus-focusable">
                      <option value="">Tutti</option>
                      @foreach($authors as $au)
                        <option value="{{ $au->id }}" {{ (string)$authorId === (string)$au->id ? 'selected' : '' }}>{{ $au->name }}</option>
                      @endforeach
                    </select>
                  </div>

                  <div class="premium-field">
                    <label for="search-from">Dal</label>
                    <input id="search-from" type="date" name="da" value="{{ $from }}" class="kairus-focusable">
                  </div>

                  <div class="premium-field">
                    <label for="search-to">Al</label>
                    <input id="search-to" type="date" name="a" value="{{ $to }}" class="kairus-focusable">
                  </div>

                  <div class="premium-filter-panel__actions">
                    <button type="submit" class="kairus-focusable">Applica</button>
                    <a href="{{ route('ricerca') }}" class="kairus-focusable">Reset</a>
                  </div>
                </div>
              </details>
            </form>
          </x-slot:form>
        </x-kairus.form-shell>

        @if($results instanceof \Illuminate\Contracts\Pagination\Paginator && $results->count() > 0)
          <h2 class="sr-only">Risultati di ricerca</h2>
          <ul class="public-list-stack kairus-search-results">
            @foreach($results as $article)
              <li>
                <x-kairus.article-card
                    :href="route('articolo', $article->slug)"
                    :title="$article->title"
                    :excerpt="$article->excerpt ? Str::limit($article->excerpt, 140) : null"
                    :category-label="$categories[$article->category] ?? $article->category"
                >
                  <x-slot:image>
                    <x-responsive-image
                        disk-name="{{ $article->cover_image ?? 'placeholder-1.svg' }}"
                        alt="{{ $article->title }}"
                        sizes="180px"
                        onerror-src="{{ asset('assets/img/placeholder-1.svg') }}"
                    />
                  </x-slot:image>
                  <x-slot:meta>
                    <x-kairus.article-meta
                        :author="$article->author->name"
                        :published-at="$article->published_at"
                        :read-minutes="$article->read_minutes"
                        density="compact"
                    />
                  </x-slot:meta>
                </x-kairus.article-card>
              </li>
            @endforeach
          </ul>

          @if($results->hasPages())
            <div class="public-pagination-wrap">
              {{ $results->links('components.pagination') }}
            </div>
          @endif
        @elseif(request()->hasAny(['q','categoria','autore','da','a']))
          <x-kairus.empty-state
              title="Nessun risultato trovato"
              message="Prova a rimuovere qualche filtro o cerca una parola chiave più ampia."
              icon="search"
          >
            <x-slot:action>
              <a href="{{ route('ricerca') }}" class="kairus-focusable">Rimuovi i filtri</a>
            </x-slot:action>
          </x-kairus.empty-state>
        @else
          <x-kairus.empty-state
              title="Inizia una ricerca"
              message="Usa la barra in alto per esplorare l’archivio Kairus."
              icon="search"
          />
        @endif
      </section>

      <aside>
        @include('components.sidebar')
      </aside>
    </div>
  </div>
</div>
@endsection
