<div class="article-premium__panel">
  <h3>Condividi</h3>
  <div class="article-premium__share">
    <a href="https://twitter.com/intent/tweet?text={{ urlencode($article->title) }}&url={{ urlencode(route('articolo', $article->slug)) }}" target="_blank" rel="noopener">X</a>
    <a href="https://api.whatsapp.com/send?text={{ urlencode($article->title.' '.route('articolo', $article->slug)) }}" target="_blank" rel="noopener">WA</a>
    <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(route('articolo', $article->slug)) }}" target="_blank" rel="noopener">in</a>
    <button type="button" onclick="copyArticleLink(this.dataset.url)" data-url="{{ route('articolo', $article->slug) }}">Link</button>
  </div>
</div>
