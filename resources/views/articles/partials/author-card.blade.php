<div class="article-premium__panel">
  <h3>Autore</h3>
  <div style="display:flex;gap:.8rem;align-items:center;">
    <div class="author-avatar">
      @if($article->author->photo)
        <img src="{{ asset('storage/'.$article->author->photo) }}" alt="{{ $article->author->name }}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
      @else
        {{ mb_substr($article->author->name, 0, 2) }}
      @endif
    </div>
    <div>
      <strong>{{ $article->author->name }}</strong><br>
      <a href="{{ route('autore', $article->author) }}" style="font-size:.85rem;color:#0f766e;text-decoration:none;font-weight:800;">Profilo autore</a>
    </div>
  </div>
</div>
