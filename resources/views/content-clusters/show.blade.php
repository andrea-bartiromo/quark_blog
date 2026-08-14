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
<script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection

@section('content')
<section class="section" aria-labelledby="percorso-title" data-path-analytics-view data-path-slug="{{ $cluster->slug }}" data-cluster-id="{{ $cluster->id }}">
  <div class="container">
    <nav aria-label="Breadcrumb">
      <a href="{{ route('home') }}">Home</a> / <a href="{{ route('percorsi.index') }}">Percorsi</a> / <span aria-current="page">{{ $cluster->name }}</span>
    </nav>

    <header class="section-header">
      <p class="eyebrow">Percorso</p>
      <h1 id="percorso-title">{{ $cluster->name }}</h1>
      @if($cluster->description)
        <p>{{ $cluster->description }}</p>
      @elseif($cluster->short_description)
        <p>{{ $cluster->short_description }}</p>
      @endif
    </header>

    @if($pillar)
      <aside aria-labelledby="pillar-title">
        <h2 id="pillar-title">Da dove iniziare</h2>
        <a href="{{ route('articolo', $pillar->slug) }}">{{ $pillar->title }}</a>
      </aside>
    @endif

    <section aria-labelledby="articles-title">
      <h2 id="articles-title">Articoli del percorso</h2>
      <ol>
        @forelse($articles as $article)
          <li>
            <a href="{{ route('articolo', $article->slug) }}">{{ $article->title }}</a>
            @if($article->excerpt)<p>{{ $article->excerpt }}</p>@endif
          </li>
        @empty
          <li>Nessun articolo pubblicato in questo percorso.</li>
        @endforelse
      </ol>
    </section>
  </div>
</section>
@endsection

@push('scripts')
  @include('partials.content-clusters-analytics')
@endpush
