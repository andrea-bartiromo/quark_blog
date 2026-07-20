@props([
  /* Identificatore univoco: radice dell'id del contenitore e di
     aria-labelledby, oltre che hook per il controller JS */
  'id' => null,
  'title' => null,
  /* Dimensione del box: 'sm', 'md' o 'lg', normalizzata a 'md' se non valida */
  'size' => 'md',
  'closeLabel' => 'Chiudi',
])

@php
  /* Come per level/align/variant in <x-special.section-header>, size non
     viene mai interpolata cosi' come arriva: solo un valore appartenente a
     un insieme chiuso puo' comporre la classe CSS. */
  $size = in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md';
  $titleId = filled($title) && filled($id) ? "{$id}-title" : null;
@endphp

<div
  {{ $attributes->merge(['class' => "sp-modal sp-modal--{$size}"]) }}
  @if($id) id="{{ $id }}" @endif
  role="dialog"
  aria-modal="true"
  @if($titleId) aria-labelledby="{{ $titleId }}" @endif
  hidden
>
  <div class="sp-modal__overlay" data-sp-modal-overlay></div>

  <div class="sp-modal__box">
    <button
      type="button"
      class="sp-modal__close"
      aria-label="{{ $closeLabel }}"
      data-sp-modal-close
    >&times;</button>

    @if(filled($title))
      <h2 @if($titleId) id="{{ $titleId }}" @endif class="sp-modal__title">{{ $title }}</h2>
    @endif

    <div class="sp-modal__body">
      {{ $slot }}
    </div>
  </div>
</div>
