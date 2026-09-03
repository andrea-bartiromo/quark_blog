{{--
    Kairus Editorial Foundations V1 — Missione 07.
    Cornice immagine: riceve l'immagine reale nello slot di default (tipicamente
    <x-responsive-image>, mai duplicata qui) e si limita a inquadrarla in un
    rapporto fisso via aspect-ratio, protetta da overflow/layout shift. Non
    aggiunge alcun attributo loading/fetchpriority — quelli restano decisi
    dal chiamante sull'immagine reale.
--}}
@props([
    'ratio' => 'card',
    'caption' => null,
    'credit' => null,
])

@php
    $ratios = ['hero', 'landscape', 'card', 'square'];
    $ratioClass = 'kairus-image-frame--'.(in_array($ratio, $ratios, true) ? $ratio : 'card');
@endphp

<figure {{ $attributes->class(['kairus-image-frame', $ratioClass]) }}>
    <div class="kairus-image-frame__media">
        {{ $slot }}
    </div>

    @if($caption || $credit)
        <figcaption class="kairus-image-frame__caption">
            @if($caption)
                <span class="kairus-image-frame__caption-text">{{ $caption }}</span>
            @endif

            @if($credit)
                <span class="kairus-image-frame__credit">{{ $credit }}</span>
            @endif
        </figcaption>
    @endif
</figure>
