@props([
  /* Periodo del capitolo (es. "1912–1939"), mostrato come eyebrow */
  'period' => null,
  /* Titolo del capitolo */
  'title' => null,
  /* Testo introduttivo del capitolo */
  'intro' => null,
  /* Immagine di apertura (filename in assets/img, path assoluto o URL http).
     Fotografia contenuta, mai sfondo esteso: introduce il capitolo senza
     coprire l'intera sezione. */
  'image' => null,
  'alt' => '',
])

@php
  /* Stesso resolver auto-contenuto usato da <x-special.timeline>: nessuna
     dipendenza dallo scope della pagina che lo include, cosi' il componente
     resta riutilizzabile da qualsiasi Special Project. */
  $resolveMedia = static function ($value) {
      if (blank($value)) {
          return null;
      }

      $value = trim((string) $value);

      if ($value === '') {
          return null;
      }

      if (str_starts_with($value, 'http') || str_starts_with($value, '/')) {
          return $value;
      }

      $value = str_replace('\\', '/', $value);
      $value = preg_replace('#^.*?/public/assets/img/#', '', $value);
      $value = preg_replace('#^/?public/assets/img/#', '', $value);
      $value = preg_replace('#^/?assets/img/#', '', $value);
      $value = ltrim($value, '/');

      return $value === '' ? null : asset('assets/img/' . $value);
  };

  $media = $resolveMedia($image);
@endphp

@if(filled($period) || filled($title) || filled($intro) || $media)
  <section {{ $attributes->merge(['class' => 'sp-chapter']) }}>
    <div class="container container--wide sp-chapter__inner">
      @if($media)
        <figure class="sp-chapter__media">
          <img src="{{ $media }}" alt="{{ $alt }}" loading="lazy" decoding="async">
        </figure>
      @endif

      <div class="sp-chapter__copy">
        @if(filled($period))
          <p class="sp-chapter__period">{{ $period }}</p>
        @endif
        @if(filled($title))
          <h2 class="sp-chapter__title">{{ $title }}</h2>
        @endif
        @if(filled($intro))
          <p class="sp-chapter__intro">{{ $intro }}</p>
        @endif
      </div>
    </div>
  </section>
@endif
