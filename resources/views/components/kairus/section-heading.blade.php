{{--
    Kairus Editorial Foundations V1 — Missione 05.
    Titolo di sezione: h2 di default, livello configurabile, nessuna CTA
    automatica (solo lo slot "action", esplicitamente passato dal chiamante).
--}}
@props([
    'eyebrow' => null,
    'title',
    'description' => null,
    'align' => 'start',
    'headingLevel' => 2,
])

@php
    $level = (int) $headingLevel;
    $level = in_array($level, [1, 2, 3, 4, 5, 6], true) ? $level : 2;
    $tag = 'h'.$level;
    $alignClass = $align === 'between' ? 'kairus-section-heading--between' : 'kairus-section-heading--start';
@endphp

<div {{ $attributes->class(['kairus-section-heading', $alignClass]) }}>
    <div class="kairus-section-heading__text">
        @if($eyebrow)
            <p class="kairus-eyebrow">{{ $eyebrow }}</p>
        @endif

        <{{ $tag }} class="kairus-section-heading__title">{{ $title }}</{{ $tag }}>

        @if($description)
            <p class="kairus-section-heading__description">{{ $description }}</p>
        @endif
    </div>

    @isset($action)
        <div class="kairus-section-heading__action">{{ $action }}</div>
    @endisset
</div>
