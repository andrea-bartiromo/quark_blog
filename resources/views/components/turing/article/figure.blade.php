@props([
  'image' => null,
  'alt' => '',
  'caption' => null,
  'width' => null,
  'height' => null,
])

@php
  $src = null;

  if (filled($image)) {
      $value = trim((string) $image);
      $src = str_starts_with($value, 'http') || str_starts_with($value, '/')
          ? $value
          : asset('assets/img/' . ltrim($value, '/'));
  }

  /* Cantiere 65 (programma "100 cantieri Kairus"): quando il chiamante
     non passa già width/height espliciti (finora sempre il caso per le
     figure editoriali hardcoded di enigma.blade.php, mai per l'unico
     punto CMS-driven — l'anatomia della macchina quando un editor carica
     una propria immagine, $anatomyIsCms), risolve le dimensioni reali dal
     file su disco — stesso meccanismo già usato da
     <x-special.chapter-opener>/<x-special.timeline> (Cantiere Prompt
     237-241, PublicImageDimensions::forUrl(), con protezione da path
     traversal e da URL esterni). Senza dimensioni dichiarate, il browser
     non può riservare lo spazio dell'immagine prima del caricamento —
     layout shift reale, non solo teorico, per qualunque immagine caricata
     via CMS senza passare esplicitamente width/height. */
  $resolvedWidth = $width;
  $resolvedHeight = $height;

  if ($src && (blank($resolvedWidth) || blank($resolvedHeight))) {
      $dimensions = \App\Support\PublicImageDimensions::forUrl($src);

      if ($dimensions) {
          $resolvedWidth = $resolvedWidth ?: $dimensions[0];
          $resolvedHeight = $resolvedHeight ?: $dimensions[1];
      }
  }
@endphp

<figure {{ $attributes->merge(['class' => 'turing-article-figure']) }}>
  @if($src)
    <img
      class="turing-timeline__media"
      src="{{ $src }}"
      alt="{{ $alt }}"
      loading="lazy"
      decoding="async"
      @if($resolvedWidth) width="{{ $resolvedWidth }}" @endif
      @if($resolvedHeight) height="{{ $resolvedHeight }}" @endif
      onerror="this.onerror=null;this.src='{{ asset('assets/img/placeholder-1.svg') }}';"
    >
  @endif

  @if(filled($caption))
    <figcaption>{{ $caption }}</figcaption>
  @endif
</figure>
