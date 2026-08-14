@extends('layouts.app')

@section('title', 'Percorsi — '.config('laboratorio.name'))
@section('description', 'Percorsi editoriali per esplorare i temi di Kairus in modo ordinato e progressivo.')
@section('canonical', route('percorsi.index'))
@section('og_title', 'Percorsi — '.config('laboratorio.name'))
@section('og_description', 'Percorsi editoriali per esplorare i temi di Kairus in modo ordinato e progressivo.')
@section('twitter_title', 'Percorsi — '.config('laboratorio.name'))
@section('twitter_description', 'Percorsi editoriali per esplorare i temi di Kairus in modo ordinato e progressivo.')

@section('head')
<link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url('css/content-clusters.css') }}">
@endsection

@section('content')
<section class="section paths-index" aria-labelledby="percorsi-title">
  <div class="container">
    <header class="paths-intro">
      <p class="eyebrow">Approfondisci</p>
      <h1 id="percorsi-title">Percorsi</h1>
      <p>Guide editoriali per capire un tema un passo alla volta: parti dall'articolo chiave e prosegui con gli approfondimenti.</p>
    </header>
    <div class="paths-grid" data-percorsi-grid>
      @forelse($clusters as $cluster)
        <article class="path-card">
          <a class="path-card__media article-card__thumb" href="{{ route('percorsi.show', $cluster->slug) }}" tabindex="-1" aria-hidden="true">
            @if($cluster->cover_image)
              <img src="{{ asset('assets/img/'.ltrim($cluster->cover_image, '/')) }}" alt="" loading="lazy" width="720" height="450">
            @else
              <span class="path-card__fallback" aria-hidden="true"><span>Kairus</span><strong>{{ $cluster->name }}</strong></span>
            @endif
          </a>
          <div class="path-card__body">
            <div class="path-card__meta"><span>Percorso</span><span>{{ $cluster->published_articles_count }} {{ $cluster->published_articles_count === 1 ? 'articolo pubblicato' : 'articoli pubblicati' }}</span></div>
            <h2><a href="{{ route('percorsi.show', $cluster->slug) }}">{{ $cluster->name }}</a></h2>
            @if($cluster->short_description)<p class="path-card__description">{{ $cluster->short_description }}</p>@endif
            @if($cluster->pillarArticle)<p class="path-card__start"><span>Da dove iniziare</span>{{ $cluster->pillarArticle->title }}</p>@endif
            <a class="path-card__cta" href="{{ route('percorsi.show', $cluster->slug) }}">Esplora il percorso <span aria-hidden="true">→</span></a>
          </div>
        </article>
      @empty
        <div class="paths-empty"><p class="eyebrow">In preparazione</p><h2>Nuovi percorsi stanno prendendo forma.</h2><p>Torna presto: qui raccoglieremo sequenze editoriali curate per orientarti nei temi di Kairus.</p></div>
      @endforelse
    </div>
  </div>
</section>
@endsection
