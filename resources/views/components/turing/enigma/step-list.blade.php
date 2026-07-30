@props([
  /* Collezione di step numerati. Ogni step: title, text. Riusato sia per il
     percorso elettrico del segnale (8 step) sia per la pipeline operativa
     di Bletchley Park (6 step): stessa struttura concettuale — una
     sequenza ordinata di fasi — con contenuto diverso. */
  'steps' => [],
])

@php
  $items = collect($steps)
      ->map(fn ($step) => is_array($step) ? $step : (array) $step)
      ->filter(fn ($step) => filled($step['title'] ?? null) || filled($step['text'] ?? null))
      ->values();
@endphp

@if($items->isNotEmpty())
  <ol {{ $attributes->merge(['class' => 'enigma-step-list']) }}>
    @foreach($items as $index => $step)
      <li class="enigma-step-list__item">
        <span class="enigma-step-list__index" aria-hidden="true">{{ $index + 1 }}</span>
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
