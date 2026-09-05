{{--
    Kairus Editorial Foundations V1 — Missione 10.
    Passo di Percorso: "state" è un valore puramente visivo/testuale passato
    dal chiamante (mai dedotto qui da dati personali di un utente) e viene
    sempre accompagnato da un'etichetta testuale, mai comunicato dal solo
    colore. Il numero è decorativo (aria-hidden): l'ordine reale del Percorso
    resta quello del markup del chiamante, mai deciso da questo componente.
--}}
@props([
    'number',
    'label',
    'categoryLabel' => null,
    'title',
    'description' => null,
    'href',
    'state' => 'available',
])

@php
    $states = ['available', 'current', 'upcoming'];
    $resolvedState = in_array($state, $states, true) ? $state : 'available';
    $stateLabels = [
        'available' => 'Disponibile',
        'current' => 'Tappa attuale',
        'upcoming' => 'Prossimamente',
    ];
    $stateClass = 'kairus-path-step--'.$resolvedState;
@endphp

<a href="{{ $href }}" {{ $attributes->class(['kairus-path-step', 'kairus-focusable', $stateClass]) }}>
    <span class="kairus-path-step__number" aria-hidden="true">{{ $number }}</span>

    <div class="kairus-path-step__content">
        <div class="kairus-path-step__meta">
            <span class="kairus-path-step__label">{{ $label }}</span>

            @if($categoryLabel)
                <span class="kairus-path-step__category">{{ $categoryLabel }}</span>
            @endif

            <span class="kairus-path-step__state">{{ $stateLabels[$resolvedState] }}</span>
        </div>

        <h3 class="kairus-path-step__title">{{ $title }}</h3>

        @if($description)
            <p class="kairus-path-step__description">{{ $description }}</p>
        @endif
    </div>

    @isset($image)
        <div class="kairus-path-step__media">{{ $image }}</div>
    @endisset
</a>
