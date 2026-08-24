{{--
  EDITORIAL SAFETY — elenco delle versioni salvate di un articolo.
  Condiviso da admin/article-revisions/index.blade.php e
  redazione/article-revisions/index.blade.php. Variabili attese:
  $article, $revisions (paginator di ArticleRevision), $editUrl,
  $showUrlName (nome rotta per il link "Visualizza", riceve [$article, $rev]).
--}}
<div class="admin-topbar">
  <h1 class="admin-page-title">Versioni salvate — {{ $article->title }}</h1>
  <a href="{{ $editUrl }}" class="btn btn--secondary">← Torna all'articolo</a>
</div>

<p style="color:#6b7280;font-size:.875rem;max-width:60ch;">
  Ogni riga rappresenta lo stato dell'articolo subito PRIMA di un
  salvataggio esplicito che lo ha modificato. Non include le bozze
  salvate solo nel browser (autosave locale): quelle proteggono solo il
  lavoro non ancora salvato, non un salvataggio già confermato.
</p>

@if($revisions->isEmpty())
  <div class="public-empty-state" role="status">
    <p>Nessuna versione salvata per questo articolo. Una versione viene
      creata automaticamente al primo salvataggio che modifica il
      contenuto dopo la creazione.</p>
  </div>
@else
  <div style="overflow-x:auto;">
    <table class="admin-table" aria-label="Versioni salvate dell'articolo">
      <caption class="sr-only">Elenco delle versioni salvate, dalla più recente</caption>
      <thead>
        <tr>
          <th scope="col">Data e ora</th>
          <th scope="col">Autore</th>
          <th scope="col">Titolo in quel momento</th>
          <th scope="col">Azioni</th>
        </tr>
      </thead>
      <tbody>
        @foreach($revisions as $revision)
        <tr>
          <td>
            <time datetime="{{ $revision->created_at->toIso8601String() }}">
              {{ $revision->created_at->timezone('Europe/Rome')->format('d/m/Y H:i') }}
            </time>
          </td>
          <td>{{ $revision->user?->name ?? 'Utente non più disponibile' }}</td>
          <td>{{ $revision->title }}</td>
          <td>
            <a href="{{ route($showUrlName, [$article, $revision]) }}" class="btn btn--outline btn--sm">Visualizza</a>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div style="margin-top:1rem;">
    {{ $revisions->links() }}
  </div>
@endif
