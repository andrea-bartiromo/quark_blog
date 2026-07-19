@props([
  'kicker' => null,
  'title' => '',
  'lead' => null,
  'image' => null,
  'meta' => [],
])

@php
  $background = null;

  if (filled($image)) {
      $value = trim((string) $image);
      $background = str_starts_with($value, 'http') || str_starts_with($value, '/')
          ? $value
          : asset('assets/img/' . ltrim($value, '/'));
  }
@endphp

<section {{ $attributes->merge(['class' => 'turing-hero']) }} @if($background) style="background-image:url('{{ $background }}')" @endif>
  <div class="container container--wide">
    <div class="turing-hero__grid">
      <div>
        @if(filled($kicker))
          <p class="turing-kicker">{{ $kicker }}</p>
        @endif

        <h1>{{ $title }}</h1>

        @if(filled($lead))
          <p class="turing-lead">{{ $lead }}</p>
        @endif

        @if(!empty($meta))
          <div class="turing-actions" aria-label="Metadati pagina">
            @foreach($meta as $item)
              @if(filled($item))
                <span>{{ $item }}</span>
              @endif
            @endforeach
          </div>
        @endif
      </div>

      {{ $slot }}
    </div>
  </div>
</section>
