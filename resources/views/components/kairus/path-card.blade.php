{{--
    Kairus Editorial Foundations V1 — Missione 09.
    Card Percorso: "progress" è un'etichetta puramente descrittiva passata
    dal chiamante (mai una percentuale calcolata qui — nessuna meccanica di
    gioco). L'immagine è facoltativa: se assente, usa lo slot "fallback" del
    chiamante, oppure un placeholder puramente decorativo. La CTA è testo
    esplicito passato dal chiamante, mai generata automaticamente.
--}}
@props([
    'href',
    'title',
    'description' => null,
    'articleCount' => null,
    'variant' => 'standard',
    'progress' => null,
    'cta' => null,
])

@php
    $variants = ['featured', 'standard', 'compact'];
    $variantClass = 'kairus-path-card--'.(in_array($variant, $variants, true) ? $variant : 'standard');
    $showDescription = filled($description) && $variant !== 'compact';
@endphp

<a href="{{ $href }}" {{ $attributes->class(['kairus-path-card', 'kairus-focusable', $variantClass]) }}>
    <div class="kairus-path-card__media">
        @isset($image)
            {{ $image }}
        @elseif(isset($fallback))
            {{ $fallback }}
        @else
            <span class="kairus-path-card__media-placeholder" aria-hidden="true"></span>
        @endisset
    </div>

    <div class="kairus-path-card__body">
        <h3 class="kairus-path-card__title">{{ $title }}</h3>

        @if($showDescription)
            <p class="kairus-path-card__description">{{ $description }}</p>
        @endif

        @if(filled($articleCount) || filled($progress))
            <div class="kairus-path-card__footer">
                @if(filled($articleCount))
                    <span class="kairus-path-card__count">{{ trans_choice(':count articolo|:count articoli', (int) $articleCount, ['count' => $articleCount]) }}</span>
                @endif

                @if(filled($progress))
                    <span class="kairus-path-card__progress">{{ $progress }}</span>
                @endif
            </div>
        @endif

        @if(filled($cta))
            <span class="kairus-path-card__cta">{{ $cta }}</span>
        @endif
    </div>
</a>
