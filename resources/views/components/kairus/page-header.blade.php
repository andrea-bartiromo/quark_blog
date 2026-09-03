{{--
    Kairus Editorial Foundations V1 — Missione 04.
    Intestazione di pagina: un solo h1, nessun testo hardcoded (ogni stringa
    arriva da prop/slot del chiamante). Non ancora montato su alcuna vista
    pubblica — vedi docs/KAIRUS_EDITORIAL_DESIGN_SYSTEM_V1.md.
--}}
@props([
    'eyebrow' => null,
    'title',
    'lead' => null,
    'tone' => 'light',
    'compact' => false,
])

@php
    $tones = ['light', 'sage', 'navy'];
    $toneClass = 'kairus-tone-'.(in_array($tone, $tones, true) ? $tone : 'light');
@endphp

<section {{ $attributes->class([
    'kairus-page-header',
    $toneClass,
    'kairus-page-header--compact' => $compact,
]) }}>
    <header class="kairus-page-header__inner">
        @if($eyebrow)
            <p class="kairus-eyebrow">{{ $eyebrow }}</p>
        @endif

        <h1 class="kairus-page-header__title">{{ $title }}</h1>

        @if($lead)
            <p class="kairus-page-header__lead">{{ $lead }}</p>
        @endif

        @isset($meta)
            <div class="kairus-page-header__meta">{{ $meta }}</div>
        @endisset
    </header>
</section>
