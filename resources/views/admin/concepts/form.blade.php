@extends('layouts.admin')
@section('title', $concept ? 'Modifica concetto' : 'Nuovo concetto')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">{{ $concept ? 'Modifica concetto' : 'Nuovo concetto' }}</h1>
  <a class="action-btn" href="{{ route('admin.concepts.index') }}">Torna ai concetti</a>
</div>

@if($errors->any())
<div class="admin-alert admin-alert--danger" role="alert">
  @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
</div>
@endif

<form method="POST" action="{{ $concept ? route('admin.concepts.update', $concept) : route('admin.concepts.store') }}">
  @csrf
  @if($concept) @method('PUT') @endif
  <div class="admin-card" style="max-width:900px;display:grid;gap:1rem;">
    <div class="form-group"><label class="form-label" for="name">Nome</label><input id="name" class="form-input" name="name" required maxlength="160" value="{{ old('name', $concept?->name) }}"></div>
    <div class="form-group"><label class="form-label" for="slug">Slug</label><input id="slug" class="form-input" name="slug" maxlength="180" value="{{ old('slug', $concept?->slug) }}" placeholder="auto-generato dal nome se vuoto"></div>
    <div class="form-group"><label class="form-label" for="short_definition">Definizione breve</label><textarea id="short_definition" class="form-textarea" name="short_definition" maxlength="2000">{{ old('short_definition', $concept?->short_definition) }}</textarea></div>
    <div class="form-group">
      <label class="form-label" for="status">Stato</label>
      <select id="status" class="form-input" name="status">
        @foreach(['draft' => 'Bozza', 'active' => 'Attivo', 'inactive' => 'Inattivo'] as $value => $label)
          <option value="{{ $value }}" {{ old('status', $concept?->status ?? 'draft') === $value ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
      </select>
      <small style="color:#6b7280;">Solo i concetti "Attivo" sono discoverable dai consumer pubblici (vedi ContentGraphService::discoverableConceptsForArticle()).</small>
    </div>

    @if($concept)
      <div class="form-group">
        <label class="form-label" for="aliases">Alias (uno per riga)</label>
        <textarea id="aliases" class="form-textarea" name="aliases_raw" style="min-height:100px;">{{ old('aliases_raw', $concept->aliases->pluck('alias')->implode("\n")) }}</textarea>
        <small style="color:#6b7280;">Un alias per riga. Righe vuote e duplicati (senza distinguere maiuscole/minuscole) vengono ignorati al salvataggio.</small>
      </div>
    @endif

    <button class="btn btn--primary" type="submit">{{ $concept ? 'Salva concetto' : 'Crea concetto' }}</button>
    @if(!$concept)<small>Dopo la creazione potrai aggiungere alias, articoli collegati e domande.</small>@endif
  </div>
</form>

@if($concept)
  <script>
    // Il textarea "aliases" e' testo libero (un alias per riga); il
    // controller si aspetta aliases[] come array. Convertiamo qui, subito
    // prima del submit, con un input hidden per riga non vuota — nessun
    // parsing lato server di un blob di testo libero.
    (() => {
      const form = document.querySelector('form[action="{{ route('admin.concepts.update', $concept) }}"]');
      const textarea = document.getElementById('aliases');
      if (!form || !textarea) return;
      form.addEventListener('submit', () => {
        form.querySelectorAll('.concept-alias-hidden').forEach((el) => el.remove());
        textarea.value.split('\n')
          .map((line) => line.trim())
          .filter((line) => line.length > 0)
          .forEach((line) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'aliases[]';
            input.value = line;
            input.className = 'concept-alias-hidden';
            form.appendChild(input);
          });
      });
    })();
  </script>

  <section class="admin-card" style="max-width:1100px;margin-top:1.25rem;" aria-labelledby="links-title">
    <h2 id="links-title">Articoli collegati</h2>
    @if($links->isEmpty())
      <p style="color:#6b7280;">Nessun articolo collegato a questo concetto.</p>
    @else
      <div style="overflow-x:auto;">
        <table class="admin-table">
          <thead><tr><th>Articolo</th><th>Relazione</th><th>Peso</th><th>Azione</th></tr></thead>
          <tbody>
          @foreach($links as $link)
            <tr>
              <td>{{ $link->article->title ?? '—' }}</td>
              <td>{{ $link->relation_type === 'primary' ? 'Primaria' : 'Di supporto' }}</td>
              <td>{{ $link->weight }}</td>
              <td>
                <form method="POST" action="{{ route('admin.concepts.articles.unlink', [$concept, $link->article_id]) }}" onsubmit="return confirm('Rimuovere questo collegamento?');">
                  @csrf @method('DELETE')
                  <button class="action-btn" type="submit" style="color:#b91c1c;">Rimuovi</button>
                </form>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    @endif

    <h3 style="margin-top:1.5rem;">Catalogo articoli</h3>
    <form method="GET" action="{{ route('admin.concepts.edit', $concept) }}" style="display:flex;gap:.75rem;align-items:end;">
      <div class="form-group"><label class="form-label" for="q">Cerca titolo</label><input id="q" class="form-input" name="q" maxlength="120" value="{{ request('q') }}"></div>
      <button class="btn btn--primary" type="submit">Filtra</button>
    </form>
    <div style="overflow-x:auto;margin-top:1rem;">
      <table class="admin-table">
        <thead><tr><th>Articolo</th><th>Stato</th><th>Categoria</th><th>Azione</th></tr></thead>
        <tbody>
        @forelse($catalog as $article)
          <tr>
            <td>{{ $article->title }}</td>
            <td>{{ $article->status }}</td>
            <td>{{ $article->category }}</td>
            <td>
              <form method="POST" action="{{ route('admin.concepts.articles.link', [$concept, $article]) }}" style="display:flex;gap:.4rem;align-items:center;">
                @csrf
                <select name="relation_type" class="form-input" style="width:auto;">
                  <option value="supporting">Di supporto</option>
                  <option value="primary">Primaria</option>
                </select>
                <button class="action-btn" type="submit">Collega</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="4">Nessun articolo corrisponde ai filtri correnti.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    {{ $catalog->links() }}
  </section>

  <section class="admin-card" style="max-width:1100px;margin-top:1.25rem;" aria-labelledby="questions-title">
    <h2 id="questions-title">Domande di scienza</h2>
    @if($concept->questions->isEmpty())
      <p style="color:#6b7280;">Nessuna domanda per questo concetto.</p>
    @else
      <div style="overflow-x:auto;">
        <table class="admin-table">
          <thead><tr><th>Domanda</th><th>Stato</th><th>Articolo target</th><th>Ordine</th><th>Azione</th></tr></thead>
          <tbody>
          @foreach($concept->questions as $question)
            <tr>
              <td>{{ $question->question }}</td>
              <td>{{ ucfirst($question->status) }}</td>
              <td>{{ $question->targetArticle?->title ?? '—' }}</td>
              <td>{{ $question->sort_order }}</td>
              <td><button class="action-btn" type="button" data-question-edit-toggle="{{ $question->id }}">Modifica</button></td>
            </tr>
            <tr id="question-edit-{{ $question->id }}" style="display:none;">
              <td colspan="5">
                <form method="POST" action="{{ route('admin.concepts.questions.update', [$concept, $question]) }}" style="display:grid;gap:.6rem;max-width:640px;">
                  @csrf @method('PUT')
                  <div class="form-group"><label class="form-label">Domanda</label><textarea class="form-textarea" name="question" required maxlength="500">{{ $question->question }}</textarea></div>
                  <div class="form-group"><label class="form-label">Risposta (sintesi)</label><textarea class="form-textarea" name="answer_summary" maxlength="2000">{{ $question->answer_summary }}</textarea></div>
                  <div class="form-group"><label class="form-label">Articolo target (ID)</label><input class="form-input" type="number" name="target_article_id" value="{{ $question->target_article_id }}"></div>
                  <div class="form-group"><label class="form-label">Ordine</label><input class="form-input" type="number" min="0" name="sort_order" value="{{ $question->sort_order }}"></div>
                  <div class="form-group">
                    <label class="form-label">Stato</label>
                    <select class="form-input" name="status">
                      @foreach(['draft' => 'Bozza', 'approved' => 'Approvata', 'inactive' => 'Inattiva'] as $value => $label)
                        <option value="{{ $value }}" {{ $question->status === $value ? 'selected' : '' }}>{{ $label }}</option>
                      @endforeach
                    </select>
                    <small style="color:#6b7280;">"Approvata" da sola non pubblica nulla: serve anche risposta non vuota, articolo target pubblicato e concetto attivo (vedi ContentGraphService::answerableQuestionsForConcept()).</small>
                  </div>
                  <button class="btn btn--primary" type="submit">Salva domanda</button>
                </form>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    @endif

    <h3 style="margin-top:1.5rem;">Nuova domanda</h3>
    <form method="POST" action="{{ route('admin.concepts.questions.store', $concept) }}" style="display:grid;gap:.6rem;max-width:640px;">
      @csrf
      <div class="form-group"><label class="form-label" for="new_question">Domanda</label><textarea id="new_question" class="form-textarea" name="question" required maxlength="500"></textarea></div>
      <div class="form-group"><label class="form-label" for="new_answer_summary">Risposta (sintesi)</label><textarea id="new_answer_summary" class="form-textarea" name="answer_summary" maxlength="2000"></textarea></div>
      <div class="form-group"><label class="form-label" for="new_target_article_id">Articolo target (ID)</label><input id="new_target_article_id" class="form-input" type="number" name="target_article_id"></div>
      <div class="form-group"><label class="form-label" for="new_sort_order">Ordine</label><input id="new_sort_order" class="form-input" type="number" min="0" name="sort_order" value="0"></div>
      <div class="form-group">
        <label class="form-label" for="new_status">Stato</label>
        <select id="new_status" class="form-input" name="status">
          <option value="draft" selected>Bozza</option>
          <option value="approved">Approvata</option>
          <option value="inactive">Inattiva</option>
        </select>
      </div>
      <button class="btn btn--primary" type="submit">Aggiungi domanda</button>
    </form>
  </section>

  <script>
    document.querySelectorAll('[data-question-edit-toggle]').forEach((button) => {
      button.addEventListener('click', () => {
        const row = document.getElementById('question-edit-' + button.dataset.questionEditToggle);
        if (row) row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
      });
    });
  </script>
@endif

@endsection
