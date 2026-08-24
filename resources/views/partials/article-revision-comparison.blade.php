{{--
  EDITORIAL SAFETY — confronto campo-per-campo tra una versione salvata e
  lo stato attuale dell'articolo. Deliberatamente NON un diff carattere
  per carattere (sproporzionato per HTML editoriale, vedi
  docs/article-revision-history.md): un confronto testuale semplice, con
  indicazione visiva di quali campi differiscono.

  Variabili attese: $article, $revision, $restoreUrl, $canRestore (bool),
  $cannotRestoreReason (string, usata solo se $canRestore è false).
--}}
@php
  $fields = [
    'title' => 'Titolo',
    'excerpt' => 'Sommario',
    'category' => 'Categoria',
    'status' => 'Stato',
    'body' => 'Corpo',
  ];
@endphp

<div class="admin-topbar">
  <h1 class="admin-page-title">
    Versione del {{ $revision->created_at->timezone('Europe/Rome')->format('d/m/Y H:i') }}
  </h1>
  <a href="{{ route($indexUrlName, $article) }}" class="btn btn--secondary">← Tutte le versioni</a>
</div>

<p style="color:#6b7280;font-size:.875rem;">
  Autore del salvataggio successivo a questa versione:
  {{ $revision->user?->name ?? 'Utente non più disponibile' }}.
</p>

<div style="overflow-x:auto;">
  <table class="admin-table" aria-label="Confronto tra la versione salvata e lo stato attuale">
    <caption class="sr-only">Confronto campo per campo tra la versione salvata e l'articolo attuale</caption>
    <thead>
      <tr>
        <th scope="col">Campo</th>
        <th scope="col">In questa versione</th>
        <th scope="col">Attuale</th>
      </tr>
    </thead>
    <tbody>
      @foreach($fields as $field => $label)
        @php $differs = (string) $revision->{$field} !== (string) $article->{$field}; @endphp
        <tr @if($differs) style="background:#fffbeb;" @endif>
          <th scope="row">
            {{ $label }}
            @if($differs)
              <span aria-label="campo modificato" title="Questo campo è cambiato">●</span>
            @endif
          </th>
          <td style="white-space:pre-wrap;max-width:40ch;">{{ $revision->{$field} }}</td>
          <td style="white-space:pre-wrap;max-width:40ch;">{{ $article->{$field} }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

<div style="margin-top:1.5rem;">
  @if($canRestore)
    <form method="POST" action="{{ $restoreUrl }}"
          onsubmit="return confirm('Ripristinare questa versione? Lo stato attuale verrà comunque salvato come nuova versione, nulla andrà perso.')">
      @csrf
      <button type="submit" class="btn btn--primary">Ripristina questa versione</button>
    </form>
  @else
    <p style="color:#6b7280;font-size:.875rem;">{{ $cannotRestoreReason }}</p>
  @endif
</div>
