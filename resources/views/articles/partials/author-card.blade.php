{{--
    Cantiere E (Prompt 122). Struttura/dati invariati (nome, foto o
    iniziali, link al profilo) — nessuna bio/credenziali da mostrare:
    non esistono su questa vista (vedi ARTICLE_REFRESH_INVARIANTS.md).
    Il trattamento più approfondito del blocco autore come superficie di
    fiducia (x-kairus.trust-panel) appartiene al Cantiere H dedicato; qui
    solo rifinitura via token degli stili inline esistenti.
--}}
<div class="article-premium__panel kairus-author-card">
  <h3>Autore</h3>
  <div class="kairus-author-card__row">
    <div class="author-avatar kairus-author-card__avatar">
      @if($article->author->photo)
        <img src="{{ asset('storage/'.$article->author->photo) }}" alt="{{ $article->author->name }}" class="kairus-author-card__photo" loading="lazy" decoding="async">
      @else
        {{ mb_substr($article->author->name, 0, 2) }}
      @endif
    </div>
    <div class="kairus-author-card__info">
      <strong>{{ $article->author->name }}</strong><br>
      <a href="{{ route('autore', $article->author) }}" class="kairus-author-card__link kairus-focusable">Profilo autore</a>
    </div>
  </div>
</div>
