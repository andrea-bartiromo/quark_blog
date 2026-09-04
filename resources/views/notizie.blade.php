@extends('layouts.app')
@section('title', 'Tutti gli articoli — '.config('laboratorio.name'))
@section('description', 'Tutti gli articoli di Kairus: scienza, tecnologia e innovazione spiegate in modo chiaro, visuale e moderno.')

{{--
    Canonical auto-referenziale per pagina: pagina 1 sempre senza query
    string (mai ?page=1), pagina N>1 canonicalizza su se stessa, mai su
    pagina 1 (articoli diversi, contenuto realmente diverso). Costruito da
    route() + solo il numero di pagina, non da request()->fullUrl(): nessun
    parametro estraneo (UTM, tracking) deve entrare nel canonical.
--}}
@section('canonical', $articles->currentPage() > 1
    ? route('notizie', ['page' => $articles->currentPage()])
    : route('notizie'))

@section('head')
@include('collections.partials.structured-data', ['collectionName' => 'Tutti gli articoli'])
@endsection

@php
    // Calcolato una sola volta fuori dal loop sottostante: prima veniva
    // rieseguito (stessa query "select name, slug from categories") una
    // volta per ciascun articolo della pagina — fino a 12 query identiche
    // per singola richiesta a /notizie, invece di una sola.
    $categoryOptions = \App\Models\Category::options(false);
@endphp

@section('content')
{{--
    Cantiere F — Archives Visual Adoption (Prompt 138-143). L'hero
    "public-hero--light" era già una superficie piatta senza immagine di
    sfondo — adatta, a differenza dell'hero articolo (Cantiere E), a
    x-kairus.page-header senza perdere alcun contenuto. Nessuna "storia
    guida": $articles non distingue mai un articolo principale (vedi
    ARCHIVES_REFRESH_FILEMAP.md) — introdurla via $articles->first() in
    Blade sarebbe una scelta editoriale non deducibile dai dati, vietata
    esplicitamente dal Prompt 139 in assenza di quella distinzione.
--}}
<div class="public-shell">
  <div class="container container--wide">

    <x-kairus.page-header
        eyebrow="Kairus Archive"
        title="Tutti gli articoli"
        lead="Il meglio della divulgazione scientifica di Kairus: IA, spazio, energia, ambiente, salute, tecnologia e società in un unico flusso editoriale."
    >
      <x-slot:meta>
        <span>{{ $articles->total() }} articoli pubblicati</span>
        <span>·</span>
        <span>Archivio aggiornato in tempo reale</span>
      </x-slot:meta>
    </x-kairus.page-header>

    <div class="public-pill-row">
      <a href="{{ route('notizie') }}" class="active">Tutti</a>
      @foreach(\App\Models\Category::options() as $slug => $label)
        <a href="{{ route('categoria', $slug) }}">{{ $label }}</a>
      @endforeach
    </div>

    <div class="public-premium-layout">
      <section>
        <div class="public-section-head">
          <div>
            <span>Latest stories</span>
            <h2>Ultime pubblicazioni</h2>
          </div>
        </div>

        <ul class="public-card-grid kairus-archive-grid">
          @forelse($articles as $article)
          <li>
            <x-kairus.article-card
                :href="route('articolo', $article->slug)"
                :title="$article->title"
                :excerpt="Str::limit($article->excerpt, 118)"
                :category-label="$categoryOptions[$article->category] ?? $article->category"
            >
              <x-slot:image>
                <x-responsive-image
                    disk-name="{{ $article->cover_image ?? 'placeholder-1.svg' }}"
                    alt="{{ $article->title }}"
                    sizes="(max-width: 900px) 100vw, 290px"
                    onerror-src="{{ asset('assets/img/placeholder-1.svg') }}"
                />
              </x-slot:image>
              <x-slot:meta>
                <x-kairus.article-meta
                    :author="Str::before($article->author->name, ' ')"
                    :read-minutes="$article->read_minutes"
                    density="compact"
                />
              </x-slot:meta>
            </x-kairus.article-card>
          </li>
          @empty
            <x-kairus.empty-state
                title="Nessun articolo pubblicato ancora."
                message="Kairus sta preparando i primi contenuti. Torna presto per scoprirli."
                icon="notice"
            />
          @endforelse
        </ul>

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
