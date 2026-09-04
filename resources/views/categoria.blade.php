@extends('layouts.app')
@section('title', $categoryLabel.' — '.config('laboratorio.name'))
@section('description', 'Tutti gli articoli di Kairus su '.$categoryLabel.': scienza, tecnologia e innovazione spiegate in modo moderno.')

{{--
    Canonical auto-referenziale per pagina: stessa logica di notizie.blade.php
    (pagina 1 senza query string, pagina N>1 su se stessa, mai su pagina 1;
    costruito da route(), non da request()->fullUrl()).
--}}
@section('canonical', $articles->currentPage() > 1
    ? route('categoria', ['slug' => $slug, 'page' => $articles->currentPage()])
    : route('categoria', $slug))

@section('head')
@include('collections.partials.structured-data', ['collectionName' => $categoryLabel])
@endsection

@section('content')
{{--
    Cantiere F — Archives Visual Adoption (Prompt 144-150). Due varianti
    di hero mutuamente esclusive: con immagine di sfondo
    (.category-premium-hero, stessa struttura overlay+testo sovrapposto
    dell'hero articolo — x-kairus.page-header non ha slot per
    un'immagine di sfondo, stessa motivazione già documentata nel
    Cantiere E) e senza (.public-hero--light, superficie piatta, adottata
    con x-kairus.page-header senza perdita di contenuto come in
    notizie.blade.php). Per coerenza tra le due varianti (lo stesso slug
    categoria può passare dall'una all'altra semplicemente aggiungendo/
    rimuovendo un'immagine in redazione) si mantiene lo stesso trattamento
    "solo token" su entrambe, invece di far dipendere la scelta del
    componente da un dato mutevole.
--}}
<div class="public-shell">
  <div class="container container--wide">

    @if($categoryImage)
    <section class="category-premium-hero kairus-tone-navy">
      <x-responsive-image
          :diskName="'categories/'.$categoryImage"
          :alt="$categoryLabel"
          loading="eager"
          fetchpriority="high"
          :sizes="'(max-width: 900px) 100vw, 1240px'"
          :onerrorSrc="asset('assets/img/hero-placeholder.svg')"
      />

      <div class="category-premium-hero__content">
        <span class="public-hero__kicker">Kairus Category</span>

        <h1 class="kairus-category-hero__title">{{ $categoryLabel }}</h1>

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
    {{--
        $categoryDescription non è usata in questo ramo (invariato:
        anche nel markup legacy questa variante mostrava solo il testo
        generico, mai la descrizione reale — quella compariva SOLO nel
        ramo con immagine, sopra). Non è un'incoerenza introdotta qui:
        per non alterare comportamenti esistenti fuori dal perimetro
        "adozione visiva" di questo cantiere, il ramo resta quello che era.
    --}}
    <x-kairus.page-header
        eyebrow="Kairus Category"
        :title="$categoryLabel"
        :lead="'Approfondimenti, analisi e storie dedicate al mondo '.strtolower($categoryLabel).'.'"
    >
      <x-slot:meta>
        <span>{{ $articles->total() }} articoli</span>
      </x-slot:meta>
    </x-kairus.page-header>
    @endif

    <section class="public-feature-band">
      <span class="public-hero__kicker">Editorial Focus</span>
      <h2>Dentro {{ $categoryLabel }}</h2>
      <p>
        Kairus seleziona notizie, ricerca, scenari e innovazioni per raccontare
        il presente e il futuro di {{ strtolower($categoryLabel) }} con un linguaggio chiaro,
        visivo e accessibile.
      </p>

      @if($category === 'intelligenza-artificiale')
        <a href="{{ route('turing') }}">Esplora la Turing Experience →</a>
      @endif
    </section>

    <div class="public-premium-layout kairus-sidebar-layout">
      <section>

        <div class="public-section-head">
          <div>
            <span>Latest stories</span>
            <h2>Ultimi articoli</h2>
          </div>
        </div>

        <ul class="public-card-grid kairus-archive-grid">
          @forelse($articles as $article)
          <li>
            <x-kairus.article-card
                :href="route('articolo', $article->slug)"
                :title="$article->title"
                :excerpt="Str::limit($article->excerpt, 118)"
                :category-label="$categoryLabel"
            >
              <x-slot:image>
                <x-responsive-image
                    :diskName="$article->cover_image ?: null"
                    :src="asset('assets/img/placeholder-1.svg')"
                    :onerrorSrc="asset('assets/img/placeholder-1.svg')"
                    :alt="$article->title"
                    :sizes="'(max-width: 900px) 100vw, 33vw'"
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
          {{-- Nessuna categoria hardcoded qui: la stessa vista serve ogni
               slug, quindi il messaggio resta generico e onesto (nessun
               contenuto "in arrivo" inventato) per qualunque categoria con
               zero articoli pubblicati — non solo Fisica appena creata. --}}
          <x-kairus.empty-state
              title="Nessun articolo pubblicato ancora"
              :message="'Kairus sta preparando i primi contenuti per '.$categoryLabel.'. Torna presto per scoprirli.'"
              icon="notice"
          />
          @endforelse
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
