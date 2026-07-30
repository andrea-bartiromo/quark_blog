@props([
  /* Collezione di capitoli: id (ancora, senza #), label. */
  'chapters' => [],
])

@php
  $items = collect($chapters)->filter(fn ($c) => filled($c['id'] ?? null) && filled($c['label'] ?? null))->values();
@endphp

@if($items->isNotEmpty())
  {{-- Versione desktop: barra fissa sul lato sinistro, solo da 1180px in su
       (vedi CSS). Un'unica lista di anchor <a href="#..."> nel markup:
       funziona anche senza JavaScript. Lo script opzionale
       (public/js/turing-enigma.js) si limita ad aggiungere
       aria-current="true" alla voce della sezione visibile — pura
       progressive enhancement, l'accessibilità/keyboard non dipende da
       esso. --}}
  <nav class="enigma-chapter-nav" aria-label="Capitoli della pagina" data-enigma-chapter-nav>
    <ol>
      @foreach($items as $index => $chapter)
        <li>
          <a href="#{{ $chapter['id'] }}" data-enigma-chapter-link="{{ $chapter['id'] }}">
            <span class="enigma-chapter-nav__index">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
            <span>{{ $chapter['label'] }}</span>
          </a>
        </li>
      @endforeach
    </ol>
  </nav>

  <nav class="enigma-chapter-nav--mobile" aria-label="Capitoli della pagina" data-enigma-chapter-nav>
    @foreach($items as $chapter)
      <a href="#{{ $chapter['id'] }}" data-enigma-chapter-link="{{ $chapter['id'] }}">{{ $chapter['label'] }}</a>
    @endforeach
  </nav>
@endif
