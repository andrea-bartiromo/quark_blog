@extends('layouts.app')

@section('title', $article->metaTitle().' — '.config('laboratorio.name'))
@section('description', $article->metaDescription())
@section('og_type', 'article')
@section('robots', $article->metaRobots())
@section('canonical', $article->metaCanonicalUrl())
@section('og_title', $article->metaOgTitle())
@section('og_description', $article->metaOgDescription())
@section('og_image', $article->metaOgImage())
@section('twitter_title', $article->metaTwitterTitle())
@section('twitter_description', $article->metaTwitterDescription())
@section('twitter_image', $article->metaTwitterImage())

@section('head')
<meta property="article:published_time" content="{{ $article->published_at->toIso8601String() }}">
<meta property="article:modified_time" content="{{ $article->updated_at->toIso8601String() }}">
<meta property="article:author" content="{{ $article->author->name }}">
<meta property="article:section" content="{{ \App\Models\Category::options(false)[$article->category] ?? $article->category }}">
<style>
.reading-progress{position:fixed;top:0;left:0;height:3px;width:0;background:linear-gradient(90deg,#0d9488,#67e8f9);z-index:9999;transition:width .08s linear}
</style>
@endsection

@section('content')
<div class="reading-progress" id="reading-progress"></div>

@php
  $categoryLabel = \App\Models\Category::options(false)[$article->category] ?? $article->category;
  $cover = asset('assets/img/'.($article->cover_image ?? 'hero-placeholder.svg'));
  $bodyParts = explode('---', (string) $article->body);
  $mainBody = $bodyParts[0] ?? (string) $article->body;
  $sources = isset($bodyParts[1]) ? trim($bodyParts[1]) : null;
  $isHtml = strip_tags($mainBody) !== $mainBody;
  $relatedItems = collect($related ?? []);

  // L'indice si costruisce solo sul body HTML vero e proprio (non sul ramo
  // di fallback per contenuti in solo testo, che non contiene h2/h3 reali):
  // gli id vengono generati qui, solo per il rendering, senza toccare mai
  // $article->body salvato nel database.
  $toc = $isHtml
      ? app(\App\Services\TableOfContentsService::class)->build($mainBody)
      : ['html' => $mainBody, 'items' => []];
  $mainBodyWithTocIds = $toc['html'];
  $tocItems = $toc['items'];
@endphp

<div class="public-shell">
  <article class="article-premium">

    @include('articles.partials.hero')

    <div class="article-premium__layout">
      <main>
        @include('articles.partials.toc', ['tocVariant' => 'toc-panel--mobile'])
        @include('articles.partials.body')
        @include('articles.partials.newsletter-band')
        @include('articles.partials.related-articles')
      </main>

      <aside class="article-premium__aside">
        @include('articles.partials.toc', ['tocVariant' => 'toc-panel--desktop'])
        @include('articles.partials.author-card')
        @include('articles.partials.share-card')

        <div class="article-premium__panel">
          @include('components.sidebar')
        </div>
      </aside>
    </div>
  </article>
</div>
@endsection

@push('scripts')
  @include('articles.partials.scripts')
@endpush
