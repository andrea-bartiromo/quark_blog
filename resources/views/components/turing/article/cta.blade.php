@props([
  'kicker' => null,
  'title' => null,
  'text' => null,
  'actions' => [],
])

<section {{ $attributes->merge(['class' => 'turing-section turing-section--final']) }}>
  <div class="container container--wide">
    <div class="turing-final-card">
      @if(filled($kicker))
        <p class="turing-kicker">{{ $kicker }}</p>
      @endif

      @if(filled($title))
        <h2>{{ $title }}</h2>
      @endif

      @if(filled($text))
        <p>{{ $text }}</p>
      @endif

      @if(!empty($actions))
        <div class="turing-actions turing-actions--center">
          @foreach($actions as $action)
            @php
              $label = $action['label'] ?? null;
              $url = $action['url'] ?? null;
            @endphp

            @if(filled($label) && filled($url))
              <a href="{{ $url }}">{{ $label }}</a>
            @endif
          @endforeach
        </div>
      @endif
    </div>
  </div>
</section>
