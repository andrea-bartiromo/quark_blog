{{--
    Kairus Editorial Foundations V1 — Missione 08.
    Card articolo: l'intera card è un solo <a>, unica destinazione
    interattiva principale — lo slot "meta" non deve contenere un altro
    link/bottone (annidare interattivi dentro <a> non è valido HTML). Il
    titolo non è mai troncato via line-clamp; l'excerpt è del tutto
    facoltativo e viene omesso nelle varianti dense (compact, list) invece
    di essere nascosto via CSS.
--}}
@props([
    'href',
    'title',
    'excerpt' => null,
    'categoryLabel' => null,
    'variant' => 'standard',
])

@php
    $variants = ['featured', 'standard', 'compact', 'list'];
    $variantClass = 'kairus-article-card--'.(in_array($variant, $variants, true) ? $variant : 'standard');
    $showExcerpt = filled($excerpt) && ! in_array($variant, ['compact', 'list'], true);
@endphp

<a href="{{ $href }}" {{ $attributes->class(['kairus-article-card', 'kairus-focusable', $variantClass]) }}>
    @isset($image)
        <div class="kairus-article-card__media">{{ $image }}</div>
    @endisset

    <div class="kairus-article-card__body">
        @if($categoryLabel)
            <span class="kairus-article-card__category">{{ $categoryLabel }}</span>
        @endif

        <h3 class="kairus-article-card__title">{{ $title }}</h3>

        @if($showExcerpt)
            <p class="kairus-article-card__excerpt">{{ $excerpt }}</p>
        @endif

        @isset($meta)
            <div class="kairus-article-card__meta">{{ $meta }}</div>
        @endisset
    </div>
</a>
