@props([
  /* Collezione di step numerati. Ogni step: title, text, ed
     eventualmente pivot=true per segnalare uno snodo (es. la
     riflessione nel percorso del segnale). Riusato sia per il percorso
     elettrico del segnale (8 step) sia per la pipeline operativa di
     Bletchley Park (6 step): stessa struttura concettuale — una
     sequenza ordinata di fasi — con contenuto diverso. */
  'steps' => [],
  /* 'default' o 'signal': la variante 'signal' aggiunge il connettore
     verticale fra gli step e uno stile a rombo per lo step 'pivot',
     per leggere la sequenza come un diagramma tecnico invece che come
     un semplice elenco. */
  'variant' => 'default',
])

@php
  $items = collect($steps)
      ->map(fn ($step) => is_array($step) ? $step : (array) $step)
      ->filter(fn ($step) => filled($step['title'] ?? null) || filled($step['text'] ?? null))
      ->values();

  $variant = $variant === 'signal' ? 'signal' : 'default';
@endphp

@if($items->isNotEmpty())
  <ol {{ $attributes->merge(['class' => 'enigma-step-list' . ($variant === 'signal' ? ' enigma-step-list--signal' : '')]) }}>
    @foreach($items as $index => $step)
      @php $isPivot = $variant === 'signal' && ($step['pivot'] ?? false); @endphp
      <li class="enigma-step-list__item {{ $isPivot ? 'enigma-step-list__item--pivot' : '' }}">
        <span class="enigma-step-list__index" aria-hidden="true">
          <span>{{ $index + 1 }}</span>
        </span>
        <div class="enigma-step-list__body">
          @if(filled($step['title'] ?? null))
            <strong>{{ $step['title'] }}</strong>
          @endif
          @if(filled($step['text'] ?? null))
            <p>{{ $step['text'] }}</p>
          @endif
        </div>
      </li>
    @endforeach
  </ol>
@endif
