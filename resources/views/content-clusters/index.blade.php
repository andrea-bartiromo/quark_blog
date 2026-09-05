@extends('layouts.app')

@section('title', 'Percorsi — '.config('laboratorio.name'))
@section('description', 'Percorsi editoriali per esplorare i temi di Kairus in modo ordinato e progressivo.')
@section('canonical', $canonical)
@section('og_title', 'Percorsi — '.config('laboratorio.name'))
@section('og_description', 'Percorsi editoriali per esplorare i temi di Kairus in modo ordinato e progressivo.')
@section('twitter_title', 'Percorsi — '.config('laboratorio.name'))
@section('twitter_description', 'Percorsi editoriali per esplorare i temi di Kairus in modo ordinato e progressivo.')

@section('head')
<link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url('css/content-clusters.css') }}">
@if($previousPageUrl)<link rel="prev" href="{{ $previousPageUrl }}">@endif
@if($nextPageUrl)<link rel="next" href="{{ $nextPageUrl }}">@endif

@php
    $percorsiIndexUrl = $canonical;

    $percorsiIndexStructuredData = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'CollectionPage',
                '@id' => $percorsiIndexUrl.'#collectionpage',
                'url' => $percorsiIndexUrl,
                'name' => 'Percorsi',
                'description' => 'Percorsi editoriali per esplorare i temi di Kairus in modo ordinato e progressivo.',
                'isPartOf' => [
                    '@id' => url('/').'/#website',
                ],
            ],
        ],
    ];
@endphp

<script type="application/ld+json">{!! json_encode($percorsiIndexStructuredData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}</script>
@endsection

@section('content')
{{--
    Cantiere D — Home + Percorsi Visual Adoption (Prompt 71-80). Stessi
    $clusters/$publicPreviews, stesso filtro/paginazione — solo il markup
    cambia. headingLevel="1" su section-heading per istruzione esplicita
    del Prompt 73 (non page-header: qui l'intro resta il trattamento più
    leggero già in uso, coerente con .paths-intro esistente).
--}}
{{--
    aria-label letterale invece di aria-labelledby+id: il componente
    section-heading applica un id passato solo al proprio div radice, mai
    all'<h1> interno — un aria-labelledby puntato lì non troverebbe
    l'elemento giusto. "Percorsi" è il titolo reale della pagina (uguale
    a @section('title')), non testo di identità inventato.
--}}
<section class="section paths-index kairus-page-shell" aria-label="Percorsi">
  <div class="container">
    <x-kairus.section-heading
      eyebrow="Percorsi Kairus · Archivio editoriale"
      title="Percorsi"
      description="Esplora un tema dall'inizio, seguendo un ordine di lettura curato: ogni Percorso raccoglie gli approfondimenti essenziali e indica da dove cominciare."
      :heading-level="1"
      class="paths-intro"
    />

    <aside class="paths-brand-statement" aria-label="Mappa di lettura">
      <p class="eyebrow">Mappa di lettura</p>
      <p>Non una raccolta da scorrere, ma traiettorie da seguire: scegli un tema, trova il punto di partenza e costruisci il quadro un passaggio alla volta.</p>
    </aside>

    <ul class="paths-grid" data-percorsi-grid>
      @forelse($clusters as $cluster)
        {{--
            Il toggle "Anteprima delle tappe" resta un FRATELLO di
            x-kairus.path-card dentro lo stesso <li class="path-card">,
            mai annidato nell'unico <a> del componente — un <button>
            dentro un <a> non è valido HTML. .path-card resta sull'<li>
            solo per il JS (button.closest('.path-card')) e per il CSS
            legacy che governa la visibilità del pannello
            (.path-card:hover/:focus-within .path-card__preview, un
            combinatore discendente — resta identico); .kairus-path-card-host
            neutralizza lo stile di riquadro di .path-card (bordo/ombra/
            sfondo), altrimenti sommato a quello, distinto e completo, già
            fornito da x-kairus.path-card — mai un doppio riquadro. Toggle e
            pannello portano anche una classe kairus- propria
            (.kairus-path-card-host__toggle/__preview, solo margini) accanto
            a quella legacy: editorial-system.css può così restare
            interamente .kairus-*, come richiesto, senza referenziare
            .path-card__preview-toggle/-preview nel proprio selettore.
        --}}
        <li class="path-card kairus-path-card-host {{ \App\Support\PathVisualSignature::cssClass($cluster) }}" data-percorso-id="{{ $cluster->id }}">
          <x-kairus.path-card
            :href="route('percorsi.show', $cluster->slug)"
            :title="$cluster->name"
            :description="$cluster->short_description"
            :article-count="$cluster->published_articles_count"
            :progress="$cluster->pillarArticle ? 'Si parte da: '.$cluster->pillarArticle->title : null"
            cta="Esplora il percorso"
          >
            @if($cluster->cover_image)
              <x-slot:image>
                <x-responsive-image
                    :diskName="ltrim($cluster->cover_image, '/')"
                    alt=""
                    :sizes="'(max-width: 768px) 100vw, 50vw'"
                />
              </x-slot:image>
            @else
              <x-slot:fallback>
                <span class="path-card__fallback" aria-hidden="true"><span>Kairus</span><strong>{{ $cluster->name }}</strong></span>
              </x-slot:fallback>
            @endif
          </x-kairus.path-card>

          @php($preview = $publicPreviews->get($cluster->id, collect()))
          @if($preview->isNotEmpty())
            @php($previewId = 'path-preview-'.$cluster->id)
            <button class="path-card__preview-toggle kairus-path-card-host__toggle" type="button" aria-expanded="false" aria-controls="{{ $previewId }}" hidden>
              Anteprima delle tappe
              <span aria-hidden="true">+</span>
            </button>
            <div class="path-card__preview kairus-path-card-host__preview" id="{{ $previewId }}" role="group" aria-label="Prime tappe pubbliche di {{ $cluster->name }}">
              <p class="path-card__preview-label">Prime tappe</p>
              <ol>
                @foreach($preview as $article)
                  <li>
                    <span class="path-card__preview-number" aria-hidden="true">{{ $loop->iteration }}</span>
                    <a href="{{ route('articolo', $article->slug) }}">{{ $article->title }}</a>
                  </li>
                @endforeach
              </ol>
            </div>
          @endif
        </li>
      @empty
        <x-kairus.empty-state
          title="Nuovi percorsi stanno prendendo forma."
          message="Torna presto: qui raccoglieremo sequenze editoriali curate per orientarti nei temi di Kairus."
          icon="path"
        />
      @endforelse
    </ul>

    <div class="paths-pagination">
      {{ $clusters->links('components.pagination', [
          'ariaLabel' => 'Paginazione Percorsi',
          'previousText' => 'Precedente',
          'nextText' => 'Successiva',
          'showDisabled' => false,
          'firstPageUrl' => route('percorsi.index'),
      ]) }}
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
(() => {
  const mobile = window.matchMedia('(max-width: 760px)');

  document.querySelectorAll('.path-card__preview-toggle').forEach((button) => {
    const panel = document.getElementById(button.getAttribute('aria-controls'));
    if (!panel) return;

    const card = button.closest('.path-card');
    card.dataset.previewEnhanced = 'true';

    const setOpen = (open) => {
      button.setAttribute('aria-expanded', String(open));
      button.querySelector('[aria-hidden="true"]').textContent = open ? '−' : '+';
      panel.hidden = !open;
    };

    const syncViewport = () => {
      button.hidden = !mobile.matches;
      setOpen(!mobile.matches);
    };

    button.addEventListener('click', () => {
      setOpen(button.getAttribute('aria-expanded') !== 'true');
    });
    mobile.addEventListener('change', syncViewport);
    syncViewport();
  });
})();
</script>
@endpush
