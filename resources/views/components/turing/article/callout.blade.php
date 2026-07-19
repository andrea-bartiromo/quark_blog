@props(['kicker' => null, 'title' => null])

<aside {{ $attributes->merge(['class' => 'turing-terminal-card']) }}>
  @if(filled($kicker))
    <span>{{ $kicker }}</span>
  @endif

  @if(filled($title))
    <strong>{{ $title }}</strong>
  @endif

  {{ $slot }}
</aside>
