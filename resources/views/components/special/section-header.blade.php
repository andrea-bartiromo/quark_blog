@props([
  /* Etichetta breve sopra il titolo (eyebrow/kicker) */
  'kicker' => null,
  'title' => null,
  /* Testo introduttivo/corpo sotto il titolo */
  'text' => null,
  /* Tag dell'heading: h1-h4, normalizzato a 'h2' se non valido */
  'level' => 'h2',
  /* Allineamento: 'left' o 'center', normalizzato a 'center' se non valido */
  'align' => 'center',
  /* Trattamento tipografico: 'section' (apertura di sezione, centrato,
     scala maggiore), 'panel' (affiancato a un'immagine, allineato a
     sinistra, scala minore) o 'final' (invito conclusivo), normalizzato
     a 'section' se non valido */
  'variant' => 'section',
])

@php
  /* I valori di level/align/variant non vengono mai interpolati cosi'
     come arrivano: solo un valore appartenente a un insieme chiuso puo'
     comporre il tag HTML o le classi CSS, cosi' da evitare che un dato
     CMS arbitrario finisca nel markup. */
  $level = in_array($level, ['h1', 'h2', 'h3', 'h4'], true) ? $level : 'h2';
  $align = in_array($align, ['left', 'center'], true) ? $align : 'center';
  $variant = in_array($variant, ['section', 'panel', 'final'], true) ? $variant : 'section';
@endphp

@if(filled($kicker) || filled($title) || filled($text))
  <div
    {{ $attributes->merge(['class' => "sp-section-header sp-section-header--{$variant} sp-section-header--{$align}"]) }}
  >
    @if(filled($kicker))
      {{-- Classe legacy 'turing-kicker' mantenuta intenzionalmente accanto a
           quella nuova: preserva la cascata colore (incluso il trattamento
           has-bg) gia' definita in public/css/turing.css, cosi' l'aspetto
           su /turing resta identico senza duplicare quella logica qui. --}}
      <p class="turing-kicker sp-section-header__kicker">{{ $kicker }}</p>
    @endif
    @if(filled($title))
      <{{ $level }} class="sp-section-header__title">{{ $title }}</{{ $level }}>
    @endif
    @if(filled($text))
      <p class="sp-section-header__text">{{ $text }}</p>
    @endif
  </div>
@endif
