{{--
    Trust Layer — riconciliazione Kairus (#516). Array già classificato da
    ArticlePrimarySourcesParser::parse(): [['type' => 'link'|'text', 'text'
    => string, 'url' => ?string], ...]. Componente puramente di
    presentazione: non chiama il parser da solo e non accetta la stringa
    grezza — così resta riusabile e testabile senza dover ricostruire un
    Article.

    article-premium__panel resta la stessa base visiva (box/bordo/ombra)
    già riusata da .kairus-cover-info (Cantiere H) per un pannello
    aggiuntivo nella colonna dei contenuti — kairus-primary-sources è solo
    un marcatore di contesto, nessuna nuova regola CSS necessaria: nessuno
    stile inline da tokenizzare qui, a differenza del caso cover-info.

    Concettualmente distinto dal pannello Fonti legacy
    (x-kairus.trust-panel, testo libero da explode('---', $body)): dato
    diverso (Article::primary_sources, non garantito coincidere con lo
    stesso articolo), quindi un pannello separato invece di forzarli nello
    stesso slot "Fonti" del trust-panel condiviso.
--}}
@props([
    'sources' => [],
])

@if(!empty($sources))
<section class="article-premium__panel kairus-primary-sources article-primary-sources" aria-labelledby="article-primary-sources-heading">
  <h3 id="article-primary-sources-heading">Fonti primarie</h3>
  <ul class="article-primary-sources__list">
    @foreach($sources as $source)
      <li>
        @if($source['type'] === 'link')
          <a href="{{ $source['url'] }}" target="_blank" rel="nofollow noopener noreferrer" class="kairus-focusable">{{ $source['text'] }}</a>
          <span class="sr-only">(fonte esterna, apre in una nuova scheda)</span>
        @else
          {{ $source['text'] }}
        @endif
      </li>
    @endforeach
  </ul>
</section>
@endif
