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
<link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url('css/content-clusters.css') }}">
<script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection

@section('content')
<section class="section path-detail" aria-labelledby="percorso-title" data-path-analytics-view data-path-slug="{{ $cluster->slug }}" data-cluster-id="{{ $cluster->id }}">
  <div class="container">
    <nav class="path-breadcrumb" aria-label="Breadcrumb">
      <a href="{{ route('home') }}">Home</a><span aria-hidden="true">/</span><a href="{{ route('percorsi.index') }}">Percorsi</a><span aria-hidden="true">/</span><span aria-current="page">{{ $cluster->name }}</span>
    </nav>

    <header class="path-hero">
      <div class="path-hero__copy">
        <p class="eyebrow">Percorso editoriale</p>
        <h1 id="percorso-title">{{ $cluster->name }}</h1>
        @if($cluster->description)
          <p>{{ $cluster->description }}</p>
        @elseif($cluster->short_description)
          <p>{{ $cluster->short_description }}</p>
        @endif
        <div class="path-hero__meta">
          <span>{{ $articles->count() }} {{ $articles->count() === 1 ? 'articolo pubblicato' : 'articoli pubblicati' }}</span>
          <span aria-hidden="true">·</span>
          <span>Ordine di lettura curato</span>
        </div>
      </div>

      <div class="path-hero__cover {{ $cluster->cover_image ? '' : 'path-hero__cover--fallback' }}">
        @if($cluster->cover_image)
          <img src="{{ asset('assets/img/'.ltrim($cluster->cover_image, '/')) }}" alt="" width="720" height="450">
        @else
          <span class="path-card__fallback" aria-hidden="true">
            <span>Kairus · Percorso</span>
            <strong>{{ $cluster->name }}</strong>
          </span>
        @endif
      </div>
    </header>

    @if($pillar)
      <aside class="path-pillar" aria-labelledby="pillar-title">
        <div class="path-pillar__intro">
          <p class="eyebrow">Da dove iniziare</p>
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
          <p class="eyebrow">La sequenza</p>
          <h2 id="articles-title">Articoli del percorso</h2>
        </div>
        <p>Segui l'ordine proposto oppure entra direttamente nell'approfondimento che ti interessa.</p>
      </header>
      <ol class="path-steps__list">
        @forelse($articles as $article)
          <li class="path-step {{ $pillar && $article->is($pillar) ? 'path-step--pillar' : '' }}">
            <div class="path-step__number" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</div>
            <div class="path-step__body">
              @if($pillar && $article->is($pillar))<span class="path-step__label">Punto di partenza</span>@endif
              <h3><a href="{{ route('articolo', $article->slug) }}">{{ $article->title }}</a></h3>
              @if($article->excerpt)<p>{{ $article->excerpt }}</p>@endif
              <a class="path-step__cta" href="{{ route('articolo', $article->slug) }}">Leggi l'articolo <span aria-hidden="true">→</span></a>
            </div>
          </li>
        @empty
          <li class="paths-empty">Nessun articolo pubblicato in questo percorso.</li>
        @endforelse
      </ol>
    </section>
  </div>
</section>
@endsection

@push('scripts')
  @include('partials.content-clusters-analytics')
@endpush
