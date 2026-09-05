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
<link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url('css/media-lightbox.css') }}">
<link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url('css/content-clusters.css') }}">
<meta property="article:published_time" content="{{ $article->published_at->toIso8601String() }}">
{{--
    article:modified_time volutamente assente: stessa motivazione già
    documentata per lastmod (sitemap) e dateModified (JSON-LD NewsArticle,
    articles/partials/structured-data.blade.php) — updated_at non è un
    segnale editoriale affidabile, viene toccato anche da
    $article->increment('views') e dal salvataggio del flusso di verifica
    editoriale senza alcuna modifica al contenuto. Prima di questa modifica
    il tag lo pubblicava comunque, incoerente con le altre due superfici.
--}}
<meta property="article:author" content="{{ $article->author->name }}">
<meta property="article:section" content="{{ $categoryOptions[$article->category] ?? $article->category }}">
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
    $categoryLabel = $categoryOptions[$article->category] ?? $article->category;
    $cover = asset('assets/img/'.($article->cover_image ?? 'hero-placeholder.svg'));
    $bodyParts = explode('---', (string) $article->body);
    $mainBody = $bodyParts[0] ?? (string) $article->body;
    $sources = isset($bodyParts[1]) ? trim($bodyParts[1]) : null;
    $isHtml = strip_tags($mainBody) !== $mainBody;
    $relatedItems = collect($related ?? []);
    $toc = $isHtml ? app(\App\Services\TableOfContentsService::class)->build($mainBody) : ['html' => $mainBody, 'items' => []];
    $mainBodyWithTocIds = $isHtml ? app(\App\Services\ArticleBodyImageService::class)->applyLazyLoading($toc['html']) : $toc['html'];
    $tocItems = $toc['items'];

    // Cantiere E (Prompt 119) — Tabelle responsive senza distruggerne la
    // struttura: nessuna tabella <table> più larga della colonna deve
    // rendere l'intera pagina scorrevole in orizzontale, né perdere dati.
    // Un wrapper con overflow-x proprio (.kairus-table-scroll, in
    // editorial-system.css) risolve senza toccare gli attributi/il
    // contenuto della tabella stessa — semplice preg_replace, non una
    // trasformazione DOM come ArticleBodyImageService (qui non serve
    // leggere/scrivere attributi, solo avvolgere il tag) e senza alcun
    // rischio dell'edge-case di parsing HTML già documentato lì.
    if ($isHtml && str_contains($mainBodyWithTocIds, '<table')) {
        $mainBodyWithTocIds = preg_replace(
            '/(<table\b[^>]*>.*?<\/table>)/is',
            '<div class="kairus-table-scroll">$1</div>',
            $mainBodyWithTocIds
        );
    }
@endphp

<div class="public-shell">
    <article class="article-premium">
        @include('articles.partials.breadcrumb')
        @include('articles.partials.hero')
        <div class="article-premium__layout kairus-sidebar-layout">
            <main>
                @include('articles.partials.toc', ['tocVariant' => 'toc-panel--mobile'])
                @include('articles.partials.body')
                {{--
                    Trust Layer — riconciliazione Kairus (#516). Fonti
                    primarie strutturate (Article::primary_sources), un
                    concetto distinto dal blocco "Fonti" legacy dentro
                    body.blade.php (testo libero dopo "---" nel corpo, via
                    x-kairus.trust-panel) — dati diversi, non garantito
                    coincidere per lo stesso articolo, quindi non
                    unificati in un solo pannello.
                --}}
                <x-article.primary-sources :sources="$primarySources" />
                @include('articles.partials.path-continuation')
                @include('articles.partials.continue-reading')
                @include('articles.partials.newsletter-band')
                @include('articles.partials.related-articles')
            </main>
            <aside class="article-premium__aside" style="position:static !important;top:auto !important;right:auto !important;bottom:auto !important;left:auto !important;align-self:start !important;transform:none !important;">
                @include('articles.partials.toc', ['tocVariant' => 'toc-panel--desktop'])
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
    @include('partials.content-clusters-analytics')
@endpush
