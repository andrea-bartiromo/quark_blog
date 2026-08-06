<h3 style="margin-top:0;">Articoli collegati</h3>

<div class="admin-card" style="margin-bottom:1.25rem;">
  <form method="POST" action="{{ route('admin.progettazione.projects.articles.link', $project) }}" style="display:flex;gap:.6rem;align-items:flex-end;">
    @csrf
    <div class="form-group" style="flex:1;margin-bottom:0;">
      <label class="form-label" for="article_id">Collega un articolo esistente</label>
      <select class="form-select" id="article_id" name="article_id" required>
        <option value="">Seleziona un articolo…</option>
        @foreach($articleOptions as $article)
          <option value="{{ $article->id }}">{{ $article->title }}</option>
        @endforeach
      </select>
    </div>
    <button type="submit" class="btn btn--primary">Collega</button>
  </form>
</div>

@if($linkedArticles->isEmpty())
  <div class="admin-card" style="text-align:center;padding:2.5rem;color:#9ca3af;">
    Nessun articolo collegato ancora.
  </div>
@else
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Titolo</th>
          <th>Stato editoriale</th>
          <th>Categoria</th>
          <th>Autore</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        @foreach($linkedArticles as $article)
        <tr>
          <td style="font-weight:700;">{{ $article->title }}</td>
          <td><span class="status status--{{ $article->status }}">{{ \App\Models\Article::statusOptions()[$article->status] ?? $article->status }}</span></td>
          <td>{{ $article->category }}</td>
          <td>{{ $article->author?->name ?? '—' }}</td>
          <td style="display:flex;gap:.4rem;">
            <a href="{{ route('admin.articles.edit', $article) }}" class="action-btn">Modifica</a>
            <form method="POST" action="{{ route('admin.progettazione.projects.articles.unlink', [$project, $article]) }}"
                  onsubmit="return confirm('Scollegare questo articolo dal progetto?')">
              @csrf @method('DELETE')
              <button type="submit" class="action-btn action-btn--danger">Scollega</button>
            </form>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif
