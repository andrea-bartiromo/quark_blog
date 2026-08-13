@extends('layouts.app')

@section('title', 'Percorsi — '.config('laboratorio.name'))
@section('description', 'Percorsi editoriali per esplorare i temi di Kairus in modo ordinato e progressivo.')
@section('canonical', route('percorsi.index'))
@section('og_title', 'Percorsi — '.config('laboratorio.name'))
@section('og_description', 'Percorsi editoriali per esplorare i temi di Kairus in modo ordinato e progressivo.')
@section('twitter_title', 'Percorsi — '.config('laboratorio.name'))
@section('twitter_description', 'Percorsi editoriali per esplorare i temi di Kairus in modo ordinato e progressivo.')

@section('content')
<section class="section" aria-labelledby="percorsi-title">
  <div class="container">
    <header class="section-header">
      <p class="eyebrow">Approfondisci</p>
      <h1 id="percorsi-title">Percorsi</h1>
      <p>Segui una sequenza editoriale di articoli pubblicati, dal punto di partenza agli approfondimenti.</p>
    </header>

    <div class="articles-grid" data-percorsi-grid>
      @forelse($clusters as $cluster)
        <article class="article-card">
          @if($cluster->cover_image)
            <a class="article-card__thumb" href="{{ route('percorsi.show', $cluster->slug) }}" tabindex="-1" aria-hidden="true">
              <img src="{{ asset('assets/img/'.ltrim($cluster->cover_image, '/')) }}" alt="" loading="lazy">
            </a>
          @endif
          <div class="article-card__body">
            <h2><a href="{{ route('percorsi.show', $cluster->slug) }}">{{ $cluster->name }}</a></h2>
            @if($cluster->short_description)
              <p>{{ $cluster->short_description }}</p>
            @endif
            <p>{{ $cluster->published_articles_count }} {{ $cluster->published_articles_count === 1 ? 'articolo pubblicato' : 'articoli pubblicati' }}</p>
            @if($cluster->pillarArticle)
              <p>Punto di partenza: <a href="{{ route('articolo', $cluster->pillarArticle->slug) }}">{{ $cluster->pillarArticle->title }}</a></p>
            @endif
          </div>
        </article>
      @empty
        <p>Nessun percorso pubblico disponibile.</p>
      @endforelse
    </div>
  </div>
</section>
@endsection
