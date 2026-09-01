@if($publicSources->isNotEmpty())
<section class="article-premium__panel article-sources" aria-labelledby="article-sources-title" style="margin-top:2rem;">
  <h2 id="article-sources-title">Fonti e approfondimenti</h2>
  <ol class="article-sources__list">
    @foreach($publicSources as $source)
      <li>
        @if($source['url'])
          <a href="{{ $source['url'] }}" target="_blank" rel="external noopener noreferrer">
            {{ $source['label'] }}
            <span class="sr-only"> (collegamento esterno)</span>
          </a>
        @else
          <span>{{ $source['label'] }}</span>
        @endif
      </li>
    @endforeach
  </ol>
</section>
@endif
