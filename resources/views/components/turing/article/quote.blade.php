@props(['cite' => null])

<figure {{ $attributes->merge(['class' => 'turing-final-card']) }}>
  <blockquote>
    {{ $slot }}
  </blockquote>

  @if(filled($cite))
    <figcaption>{{ $cite }}</figcaption>
  @endif
</figure>
