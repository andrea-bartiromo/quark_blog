@props([
    /* Array già classificato da ArticlePrimarySourcesParser::parse():
       [['type' => 'link'|'text', 'text' => string, 'url' => ?string], ...].
       Componente puramente di presentazione: non chiama il parser da solo
       e non accetta la stringa grezza — così resta riusabile e testabile
       senza dover ricostruire un Article. */
    'sources' => [],
])

@if(!empty($sources))
<section class="article-premium__panel article-primary-sources" aria-labelledby="article-primary-sources-heading">
  <h3 id="article-primary-sources-heading">Fonti primarie</h3>
  <ul class="article-primary-sources__list">
    @foreach($sources as $source)
      <li>
        @if($source['type'] === 'link')
          <a href="{{ $source['url'] }}" target="_blank" rel="nofollow noopener noreferrer">{{ $source['text'] }}</a>
          <span class="sr-only">(fonte esterna, apre in una nuova scheda)</span>
        @else
          {{ $source['text'] }}
        @endif
      </li>
    @endforeach
  </ul>
</section>
@endif
