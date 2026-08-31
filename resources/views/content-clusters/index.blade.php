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
<section class="section paths-index" aria-labelledby="percorsi-title">
  <div class="container">
    <header class="paths-intro">
      <p class="eyebrow">Percorsi Kairus · Archivio editoriale</p>
      <h1 id="percorsi-title">Percorsi</h1>
      <p>Esplora un tema dall'inizio, seguendo un ordine di lettura curato: ogni Percorso raccoglie gli approfondimenti essenziali e indica da dove cominciare.</p>
      <span class="paths-intro__rule" aria-hidden="true"></span>
    </header>

    <aside class="paths-brand-statement" aria-label="Mappa di lettura">
      <p class="eyebrow">Mappa di lettura</p>
      <p>Non una raccolta da scorrere, ma traiettorie da seguire: scegli un tema, trova il punto di partenza e costruisci il quadro un passaggio alla volta.</p>
    </aside>

    <div class="paths-grid" data-percorsi-grid>
      @forelse($clusters as $cluster)
        <article class="path-card {{ \App\Support\PathVisualSignature::cssClass($cluster) }}" data-percorso-id="{{ $cluster->id }}">
          <a class="path-card__media" href="{{ route('percorsi.show', $cluster->slug) }}" tabindex="-1" aria-hidden="true">
            @if($cluster->cover_image)
              <x-responsive-image
                  :diskName="ltrim($cluster->cover_image, '/')"
                  alt=""
                  :sizes="'(max-width: 768px) 100vw, 50vw'"
              />
            @else
              <span class="path-card__fallback" aria-hidden="true"><span>Kairus</span><strong>{{ $cluster->name }}</strong></span>
            @endif
          </a>
          <div class="path-card__body">
            <div class="path-card__meta"><span>Percorso Kairus</span><span>{{ $cluster->published_articles_count }} {{ $cluster->published_articles_count === 1 ? 'articolo pubblicato' : 'articoli pubblicati' }}</span></div>
            <h2><a href="{{ route('percorsi.show', $cluster->slug) }}">{{ $cluster->name }}</a></h2>
            @if($cluster->short_description)<p class="path-card__description">{{ $cluster->short_description }}</p>@endif
            @if($cluster->pillarArticle)<p class="path-card__start"><span>Da qui si parte</span>{{ $cluster->pillarArticle->title }}</p>@endif
            @php($preview = $publicPreviews->get($cluster->id, collect()))
            @if($preview->isNotEmpty())
              @php($previewId = 'path-preview-'.$cluster->id)
              <button class="path-card__preview-toggle" type="button" aria-expanded="false" aria-controls="{{ $previewId }}" hidden>
                Anteprima delle tappe
                <span aria-hidden="true">+</span>
              </button>
              <div class="path-card__preview" id="{{ $previewId }}" role="group" aria-label="Prime tappe pubbliche di {{ $cluster->name }}">
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
            <a class="path-card__cta" href="{{ route('percorsi.show', $cluster->slug) }}">Esplora il percorso <span aria-hidden="true">→</span></a>
          </div>
        </article>
      @empty
        <div class="paths-empty"><p class="eyebrow">In preparazione</p><h2>Nuovi percorsi stanno prendendo forma.</h2><p>Torna presto: qui raccoglieremo sequenze editoriali curate per orientarti nei temi di Kairus.</p></div>
      @endforelse
    </div>

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
