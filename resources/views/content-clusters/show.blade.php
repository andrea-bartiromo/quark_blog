@extends('layouts.app')

@section('title', ($cluster->seo_title ?: $cluster->name).' — '.config('laboratorio.name'))
@section('description', $description)
@section('canonical', $canonical)
@section('og_title', $cluster->seo_title ?: $cluster->name)
@section('og_description', $description)
@section('twitter_title', $cluster->seo_title ?: $cluster->name)
@section('twitter_description', $description)
@if($cluster->cover_image)
  @section('og_image', asset('assets/img/'.ltrim($cluster->cover_image, '/')))
  @section('twitter_image', asset('assets/img/'.ltrim($cluster->cover_image, '/')))
@endif

@section('head')
<link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url('css/media-lightbox.css') }}">
<link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url('css/content-clusters.css') }}">
<link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url('css/content-clusters-detail.css') }}">
<link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url('css/content-clusters-narrative.css') }}">
<script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection

@section('content')
@php
  $pathCoverUrl = $cluster->cover_image
      ? asset('assets/img/'.ltrim($cluster->cover_image, '/'))
      : null;
  $pathCoverAlt = 'Cover del percorso '.$cluster->name;
  $pathCoverViewerId = 'path-cover-viewer-'.$cluster->id;
  $takeaways = collect($cluster->takeaways ?? [])->filter()->values();
  $guidingQuestions = collect($cluster->guiding_questions ?? [])->filter()->values();
  $showContinuation = $cluster->isUpdating() && $articles->isNotEmpty();
  $publishedStepCount = $articles->count();

  // Tempo di lettura dell'intero percorso: somma automatica dei
  // read_minutes degli articoli pubblicati (calcolati da Article stesso,
  // vedi getReadTimeAttribute) — nessun campo editoriale da gestire a
  // mano, si aggiorna da sé quando cambia la sequenza pubblicata.
  $totalReadMinutes = (int) $articles->sum('read_minutes');

  // Etichetta di categoria per singolo articolo: ha senso solo quando la
  // sequenza pubblicata attraversa davvero più di una categoria (stesso
  // segnale del "cambio di registro" più sotto) — su un percorso
  // omogeneo (il caso comune) sarebbe una ripetizione senza informazione.
  $pathCategories = $articles->pluck('category')->filter()->unique();
  $isMultiCategoryPath = $pathCategories->count() > 1;
  $categoryLabels = config('laboratorio.categories', []);

  // Kairus Visual Language — due asset SEMANTICI, non un conteggio
  // derivato dal numero di articoli (vedi App\Support\PathVisualLibrary):
  // l'atmosfera d'ingresso è sempre presente, il cambio di registro solo
  // quando la sequenza pubblicata attraversa davvero più di una categoria.
  //
  // L'apertura atmosferica di una pagina va nell'HERO stesso, mai altrove
  // (Manuale di Identità Visiva Kairus/Quark, Vol. I — "una pagina può
  // permettersi un'apertura atmosferica" — singolare, e lì dove il
  // lettore la incontra prima ancora di iniziare a leggere): quando il
  // Percorso non ha una cover propria, l'immagine della Visual Library
  // diventa lo sfondo cinematografico dell'hero stesso, non un riquadro
  // separato più in basso.
  $atmosphereImage = \App\Support\PathVisualLibrary::atmosphereImage($cluster);
  $heroImageUrl = $pathCoverUrl ?: \App\Support\PathVisualLibrary::url($atmosphereImage);
  $transitionImage = \App\Support\PathVisualLibrary::transitionImage($cluster);
@endphp

<section class="section path-detail {{ \App\Support\PathVisualSignature::cssClass($cluster) }}" aria-labelledby="percorso-title" data-path-analytics-view data-path-slug="{{ $cluster->slug }}" data-cluster-id="{{ $cluster->id }}">
  <div class="container">
    <nav class="path-breadcrumb" aria-label="Breadcrumb">
      <a href="{{ route('home') }}">Home</a><span aria-hidden="true">/</span><a href="{{ route('percorsi.index') }}">Percorsi</a><span aria-hidden="true">/</span><span aria-current="page">{{ $cluster->name }}</span>
    </nav>

    <header class="path-hero" style="background-image: url('{{ $heroImageUrl }}')">
      <div class="path-hero__scrim" aria-hidden="true"></div>
      <div class="path-hero__copy">
        <p class="eyebrow">Percorso Kairus</p>
        <h1 id="percorso-title">{{ $cluster->name }}</h1>
        @if($cluster->description)
          <p>{{ $cluster->description }}</p>
        @elseif($cluster->short_description)
          <p>{{ $cluster->short_description }}</p>
        @endif
        <div class="path-hero__meta">
          <span>{{ $articles->count() }} {{ $articles->count() === 1 ? 'articolo pubblicato' : 'articoli pubblicati' }}</span>
          @if($totalReadMinutes > 0)
            <span aria-hidden="true">·</span>
            <span>{{ $totalReadMinutes }} min di lettura</span>
          @endif
          <span aria-hidden="true">·</span>
          <span>Mappa di lettura curata</span>
        </div>
      </div>

      @if($pathCoverUrl)
        <button
          type="button"
          class="path-hero__view-trigger"
          data-media-viewer-target="{{ $pathCoverViewerId }}"
          aria-haspopup="dialog"
          aria-label="Visualizza l'immagine completa del percorso {{ $cluster->name }}"
        >Visualizza immagine</button>
      @endif
    </header>

    @if($pathCoverUrl)
      <x-media.image-viewer
        :src="$pathCoverUrl"
        :alt="$pathCoverAlt"
        :title="$cluster->name"
        :id="$pathCoverViewerId"
        variant="embedded"
      />
    @endif

    <section class="path-entrance" aria-labelledby="path-note-title">
      <div class="path-entrance__copy">
        <p class="eyebrow">Mappa di lettura</p>
        <h2 id="path-note-title">Perché questo percorso</h2>
        <p>{{ $cluster->description ?: ($cluster->short_description ?: 'Una sequenza curata di approfondimenti per costruire il quadro un passaggio alla volta.') }}</p>
      </div>
    </section>

    @if($cluster->curator_note)
      <figure class="path-curator-note" aria-labelledby="path-curator-note-title">
        <figcaption id="path-curator-note-title" class="eyebrow">Nota del curatore</figcaption>
        <blockquote>{{ $cluster->curator_note }}</blockquote>
      </figure>
    @endif

    @if($takeaways->isNotEmpty())
      <section class="path-narrative path-narrative--takeaways" aria-labelledby="path-takeaways-title">
        <div><p class="eyebrow">Cosa capirai</p><h2 id="path-takeaways-title">I punti da portare con te</h2></div>
        <ol>
          @foreach($takeaways as $takeaway)<li>{{ $takeaway }}</li>@endforeach
        </ol>
      </section>
    @endif

    @if($guidingQuestions->isNotEmpty())
      <section class="path-narrative path-narrative--questions" aria-labelledby="path-questions-title">
        <div><p class="eyebrow">Le domande che ci guideranno</p><h2 id="path-questions-title">Una mappa fatta di domande</h2></div>
        <ul>
          @foreach($guidingQuestions as $question)<li>{{ $question }}</li>@endforeach
        </ul>
      </section>
    @endif

    @if($pillar)
      <aside class="path-pillar" aria-labelledby="pillar-title">
        <div class="path-pillar__intro">
          <span class="path-pillar__number" aria-hidden="true">00</span>
          <p class="eyebrow">Da qui si parte</p>
          <h2 id="pillar-title">Il punto di partenza</h2>
          <p>Se è la prima volta che esplori questo tema, comincia dall'articolo chiave e poi prosegui nella sequenza.</p>
        </div>
        <a class="path-pillar__link" href="{{ route('articolo', $pillar->slug) }}">
          <span>Articolo chiave</span>
          <strong>{{ $pillar->title }}</strong>
          <span class="path-pillar__cta">Inizia a leggere <span aria-hidden="true">→</span></span>
        </a>
      </aside>
    @endif

    <section class="path-steps" aria-labelledby="articles-title">
      <header class="path-steps__header">
        <div>
          <p class="eyebrow">Sequenza Kairus</p>
          <h2 id="articles-title">Articoli del percorso</h2>
        </div>
        <p>Segui l'ordine proposto oppure entra direttamente nell'approfondimento che ti interessa.</p>
      </header>
      <ol class="path-steps__list {{ $showContinuation ? 'path-steps__list--continues' : '' }}">
        @forelse($articles as $article)
          @php
            $stepCoverUrl = filled($article->cover_image) ? asset('assets/img/'.$article->cover_image) : null;
          @endphp
          <li class="path-step {{ $pillar && $article->is($pillar) ? 'path-step--pillar' : '' }} {{ $stepCoverUrl ? '' : 'path-step--no-cover' }}">
            <div class="path-step__number" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</div>
            @if($stepCoverUrl)
              <a class="path-step__cover" href="{{ route('articolo', $article->slug) }}" tabindex="-1" aria-hidden="true">
                <img src="{{ $stepCoverUrl }}" alt="" loading="lazy" decoding="async" width="320" height="240">
              </a>
            @endif
            <div class="path-step__body">
              @if($pillar && $article->is($pillar))<span class="path-step__label">Punto di partenza</span>@endif
              @if($isMultiCategoryPath && $article->category && isset($categoryLabels[$article->category]))
                <span class="path-step__category">{{ $categoryLabels[$article->category] }}</span>
              @endif
              <h3><a href="{{ route('articolo', $article->slug) }}">{{ $article->title }}</a></h3>
              @if($article->excerpt)<p>{{ $article->excerpt }}</p>@endif
              <a class="path-step__cta" href="{{ route('articolo', $article->slug) }}">Leggi l'articolo <span aria-hidden="true">→</span></a>
              @if($article->pivot?->transition_text)
                <p class="path-step__transition"><span aria-hidden="true">↳</span> {{ $article->pivot->transition_text }}</p>
              @endif
            </div>
          </li>
        @empty
          <li class="paths-empty">Nessun articolo pubblicato in questo percorso.</li>
        @endforelse
        @if($showContinuation)
          <li class="path-step path-step--next" aria-hidden="true">
            <div class="path-step__number path-step__number--next">···</div>
            <div class="path-step__body">
              <span class="path-step__label path-step__label--next">In arrivo</span>
              <p class="path-step__next-copy">La prossima tappa non è ancora disponibile.</p>
            </div>
          </li>
        @elseif($articles->isNotEmpty())
          <li class="path-step path-step--close" aria-hidden="true">
            <div class="path-step__number path-step__number--close">●</div>
            <div class="path-step__body">
              <span class="path-step__label path-step__label--close">Percorso concluso</span>
            </div>
          </li>
        @endif
      </ol>
    </section>

    @isset($transitionImage)
      @include('content-clusters.partials.path-transition')
    @endisset

    <footer class="path-ending {{ $showContinuation ? 'path-ending--continues' : '' }}" @if($showContinuation) data-path-continues @endif>
      <div>
        @if($showContinuation)
          <p class="eyebrow">Il percorso continua</p>
          <h2>Non siamo ancora arrivati alla fine.</h2>
          <p>{{ $publishedStepCount }} {{ $publishedStepCount === 1 ? 'tappa disponibile' : 'tappe disponibili' }} · Percorso in aggiornamento</p>
          <p>Questo Percorso Kairus è ancora in evoluzione. Stiamo preparando nuovi capitoli per continuare l'esplorazione. Torna presto: la prossima tappa arriverà qui.</p>
          <p><em>Qui riprenderà il viaggio.</em></p>
          @include('content-clusters.partials.subscribe-form', ['cluster' => $cluster])
        @else
          <p class="eyebrow">Continua l'esplorazione</p>
          <h2>{{ $cluster->closing_title ?: 'Fine del percorso' }}</h2>
          <p>{{ $cluster->closing_text ?: 'Hai attraversato la mappa essenziale di questo tema. Puoi tornare ai Percorsi e scegliere la prossima direzione.' }}</p>
        @endif
      </div>
      <a href="{{ route('percorsi.index') }}">Scopri tutti i Percorsi <span aria-hidden="true">→</span></a>
    </footer>
  </div>
</section>
@endsection

@push('scripts')
  @include('partials.content-clusters-analytics')
@endpush
