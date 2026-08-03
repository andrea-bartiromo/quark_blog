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
@include('articles.partials.structured-data')

<style>
.reading-progress {
    position: fixed;
    top: 0;
    left: 0;
    z-index: 9999;
    width: 0;
    height: 3px;
    background: linear-gradient(90deg, #0d9488, #67e8f9);
    transition: width 0.08s linear;
}

/*
 * La colonna laterale resta nel normale flusso della pagina.
 * L'indice non segue lo scorrimento.
 */
.article-premium__layout > .article-premium__aside {
    position: static !important;
    top: auto !important;
    right: auto !important;
    bottom: auto !important;
    left: auto !important;
    align-self: start !important;
    transform: none !important;
}

.article-premium__aside > .toc-panel--desktop,
.article-premium__aside > .toc-panel {
    position: static !important;
    top: auto !important;
    right: auto !important;
    bottom: auto !important;
    left: auto !important;
    inset: auto !important;

    width: auto !important;
    height: auto !important;
    min-height: 0 !important;
    max-height: none !important;

    overflow: visible !important;
    transform: none !important;
}

.article-premium__aside > .toc-panel--desktop .toc-nav,
.article-premium__aside > .toc-panel .toc-nav {
    position: static !important;
    top: auto !important;
    right: auto !important;
    bottom: auto !important;
    left: auto !important;

    height: auto !important;
    min-height: 0 !important;
    max-height: none !important;

    overflow: visible !important;
    transform: none !important;
}
</style>
@endsection

@section('content')
<div class="reading-progress" id="reading-progress"></div>

@php
    $categoryLabel = \App\Models\Category::options(false)[$article->category]
        ?? $article->category;

    $cover = asset(
        'assets/img/'.($article->cover_image ?? 'hero-placeholder.svg')
    );

    $bodyParts = explode('---', (string) $article->body);

    $mainBody = $bodyParts[0] ?? (string) $article->body;

    $sources = isset($bodyParts[1])
        ? trim($bodyParts[1])
        : null;

    $isHtml = strip_tags($mainBody) !== $mainBody;

    $relatedItems = collect($related ?? []);

    /*
     * L'indice viene generato solo dal contenuto HTML reale.
     * Gli ID dei titoli vengono aggiunti esclusivamente durante
     * il rendering e non modificano il testo salvato nel database.
     */
    $toc = $isHtml
        ? app(\App\Services\TableOfContentsService::class)->build($mainBody)
        : [
            'html' => $mainBody,
            'items' => [],
        ];

    $mainBodyWithTocIds = $toc['html'];
    $tocItems = $toc['items'];
@endphp

<div class="public-shell">
    <article class="article-premium">

        @include('articles.partials.hero')

        <div class="article-premium__layout">

            <main>
                @include('articles.partials.toc', [
                    'tocVariant' => 'toc-panel--mobile',
                ])

                @include('articles.partials.body')

                @include('articles.partials.newsletter-band')

                @include('articles.partials.related-articles')
            </main>

            <aside
                class="article-premium__aside"
                style="
                    position: static !important;
                    top: auto !important;
                    right: auto !important;
                    bottom: auto !important;
                    left: auto !important;
                    align-self: start !important;
                    transform: none !important;
                "
            >
                @include('articles.partials.toc', [
                    'tocVariant' => 'toc-panel--desktop',
                ])

                @include('articles.partials.author-card')

                @include('articles.partials.share-card')

                @include('components.sidebar')
            </aside>

        </div>
    </article>
</div>
@endsection

@push('scripts')
    @include('articles.partials.scripts')
@endpush