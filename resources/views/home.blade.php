@extends('layouts.app')
@section('title', config('laboratorio.name').' — '.config('laboratorio.tagline'))
@section('canonical', rtrim(route('home'), '/').'/')

@section('home_css')
<link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url('css/home-premium.css') }}">
<link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url('css/home-fix.css') }}">
<link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url('css/home-category-carousel.css') }}">
<link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url('css/content-clusters.css') }}">
@endsection

@section('head')
@include('home.partials.structured-data')
@endsection

@section('content')

@php
  $categories = $categoryOptions ?? config('laboratorio.categories');
  $categoryLabels = $categoryLabelOptions ?? $categories;
  $categoryRecords = $categoryRecords ?? collect();
  $fallbackTrending = $trending->count() ? $trending : $latest->take(5);
  // La sezione "Esplora le categorie" rappresenta le categorie attive,
  // anche quando non hanno ancora articoli pubblicati. In questo modo una
  // nuova categoria configurata in admin compare subito nel carosello.
  $categoryHighlights = collect($categories)
      ->map(fn($label, $slug) => (object) ['category' => $slug])
      ->values();

  $categoryLabel = fn($article) => $categoryLabels[$article->category] ?? $article->category;

  $fallbackSvg = function ($label = 'Kairus', $tone = '#0f766e') {
      $safeLabel = e(Str::upper(Str::limit($label ?: 'Kairus', 34, '')));
      $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 760" role="img" aria-label="{$safeLabel}">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#0f172a"/>
      <stop offset="0.48" stop-color="{$tone}"/>
      <stop offset="1" stop-color="#14b8a6"/>
    </linearGradient>
    <radialGradient id="r" cx="72%" cy="18%" r="65%">
      <stop offset="0" stop-color="#ffffff" stop-opacity="0.36"/>
      <stop offset="1" stop-color="#ffffff" stop-opacity="0"/>
    </radialGradient>
  </defs>
  <rect width="1200" height="760" fill="url(#g)"/>
  <rect width="1200" height="760" fill="url(#r)"/>
  <circle cx="950" cy="130" r="230" fill="#ffffff" opacity="0.08"/>
  <circle cx="160" cy="610" r="280" fill="#020617" opacity="0.22"/>
  <path d="M150 520 C300 310 455 450 610 260 S900 210 1040 95" fill="none" stroke="#ffffff" stroke-opacity="0.30" stroke-width="7"/>
  <path d="M165 548 C320 365 455 505 655 325 S930 295 1065 170" fill="none" stroke="#99f6e4" stroke-opacity="0.22" stroke-width="4"/>
  <g fill="#ffffff" opacity="0.72">
    <circle cx="305" cy="360" r="7"/>
    <circle cx="610" cy="260" r="9"/>
    <circle cx="875" cy="218" r="6"/>
    <circle cx="1040" cy="95" r="8"/>
  </g>
  <text x="72" y="98" fill="#ffffff" opacity="0.76" font-family="Arial, sans-serif" font-size="25" font-weight="700" letter-spacing="8">KAIRUS</text>
  <text x="72" y="650" fill="#ffffff" font-family="Georgia, serif" font-size="64" font-weight="700">{$safeLabel}</text>
  <text x="76" y="698" fill="#ccfbf1" font-family="Arial, sans-serif" font-size="23" font-weight="600">Science magazine visual</text>
</svg>
SVG;

      return 'data:image/svg+xml;charset=UTF-8,'.rawurlencode($svg);
  };

  $toneForCategory = [
      'intelligenza-artificiale' => '#2563eb',
      'spazio'                   => '#4f46e5',
      'energia'                  => '#ca8a04',
      'ambiente'                 => '#15803d',
      'salute'                   => '#be123c',
      'default'                  => '#0f766e',
  ];

  $visualFor = function ($article) use ($fallbackSvg, $categoryLabel, $toneForCategory) {
      $category = $article->category ?? 'default';
      return $fallbackSvg($categoryLabel($article), $toneForCategory[$category] ?? $toneForCategory['default']);
  };

  $imageForArticle = function ($article, $position = 0) use ($fallbackSvg, $categoryLabel, $toneForCategory) {
      $category = $article->category ?? 'default';

      if (filled($article->cover_image)) {
          return asset('assets/img/'.$article->cover_image);
      }

      return $fallbackSvg($categoryLabel($article), $toneForCategory[$category] ?? $toneForCategory['default']);
  };

  $imageForCategory = function ($article, $position = 0) use ($fallbackSvg, $categoryLabel, $toneForCategory, $categoryRecords) {
      $category = $article->category ?? 'default';
      $record = $categoryRecords[$category] ?? null;

      if ($record && filled($record->image)) {
          return asset('assets/img/categories/'.$record->image);
      }

      return $fallbackSvg($categoryLabel($article), $toneForCategory[$category] ?? $toneForCategory['default']);
  };
@endphp

@include('home.partials.hero-trending')
@include('home.partials.newsletter-band')
@include('home.partials.turing-teaser')

<div class="container container--wide">
  @include('home.partials.latest-articles')
  @include('home.partials.category-grid')
</div>

@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-category-carousel]').forEach((carousel) => {
    const track = carousel.querySelector('[data-category-track]');
    const previous = carousel.querySelector('[data-category-prev]');
    const next = carousel.querySelector('[data-category-next]');

    if (!track || !previous || !next) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const behavior = () => reducedMotion.matches ? 'auto' : 'smooth';
    const step = () => Math.max(track.clientWidth * 0.85, 240);
    const updateControls = () => {
        const maxScroll = Math.max(0, track.scrollWidth - track.clientWidth);
        previous.disabled = track.scrollLeft <= 2;
        next.disabled = track.scrollLeft >= maxScroll - 2;
    };
    const scrollByPage = (direction) => track.scrollBy({ left: direction * step(), behavior: behavior() });

    previous.addEventListener('click', () => scrollByPage(-1));
    next.addEventListener('click', () => scrollByPage(1));
    track.addEventListener('keydown', (event) => {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
        event.preventDefault();
        scrollByPage(event.key === 'ArrowRight' ? 1 : -1);
    });
    track.addEventListener('scroll', updateControls, { passive: true });
    window.addEventListener('resize', updateControls);
    updateControls();
});
</script>
@endpush
