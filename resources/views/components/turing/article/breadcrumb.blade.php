@props(['items' => []])

<nav {{ $attributes->merge(['class' => 'turing-article-breadcrumb']) }} aria-label="Percorso">
  <ol>
    <li><a href="{{ route('turing') }}">Speciale Turing</a></li>

    @foreach($items as $item)
      @php
        $label = is_array($item) ? ($item['label'] ?? '') : (string) $item;
        $url = is_array($item) ? ($item['url'] ?? null) : null;
      @endphp

      @if(filled($label))
        <li>
          @if(filled($url))
            <a href="{{ $url }}">{{ $label }}</a>
          @else
            <span aria-current="page">{{ $label }}</span>
          @endif
        </li>
      @endif
    @endforeach
  </ol>
</nav>
