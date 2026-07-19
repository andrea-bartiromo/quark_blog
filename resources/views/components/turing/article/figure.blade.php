@props([
  'image' => null,
  'alt' => '',
  'caption' => null,
])

@php
  $src = null;

  if (filled($image)) {
      $value = trim((string) $image);
      $src = str_starts_with($value, 'http') || str_starts_with($value, '/')
          ? $value
          : asset('assets/img/' . ltrim($value, '/'));
  }
@endphp

<figure {{ $attributes->merge(['class' => 'turing-article-figure']) }}>
  @if($src)
    <img class="turing-timeline__media" src="{{ $src }}" alt="{{ $alt }}">
  @endif

  @if(filled($caption))
    <figcaption>{{ $caption }}</figcaption>
  @endif
</figure>
