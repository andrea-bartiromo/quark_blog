@extends('layouts.admin')
@section('title', 'Cosa sappiamo davvero')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Cosa sappiamo davvero</h1>
  <a class="action-btn" href="{{ route('admin.trust-knowledge.create') }}">+ Nuova voce</a>
</div>

<p style="color:var(--admin-muted);font-size:.85rem;max-width:82ch;margin-bottom:1rem">
  Modello interno di scomposizione della conoscenza editoriale (Domanda / Consenso / Incertezza / Cosa manca) — solo per uso redazionale, mai esposto pubblicamente. Non è un sostituto della verifica per-articolo già esistente in <a href="{{ route('admin.verification') }}">Fonti</a>: quella è un flag binario per articolo, questa è una scomposizione di cosa è certo, incerto o esplicitamente non ancora coperto, indipendente da un singolo articolo.
</p>

@if(session('success'))
<div class="admin-alert admin-alert--success" role="status">{{ session('success') }}</div>
@endif

@if($statements->isEmpty())
  <div class="admin-card" style="text-align:center;color:var(--admin-muted)">
    Nessuna voce ancora. Clicca "+ Nuova voce" per iniziare.
  </div>
@else
  <div class="admin-card" style="overflow-x:auto">
    <table class="admin-table" style="width:100%;font-size:.85rem">
      <thead>
        <tr>
          <th style="text-align:left">Domanda</th>
          <th style="text-align:left">Concetto</th>
          <th style="text-align:left">Percorso</th>
          <th style="text-align:left">Ultimo controllo</th>
          <th style="text-align:left">Azioni</th>
        </tr>
      </thead>
      <tbody>
        @foreach($statements as $statement)
          <tr>
            <td>{{ $statement->domanda }}</td>
            <td>{{ $statement->concept?->name ?? '—' }}</td>
            <td>{{ $statement->contentCluster?->name ?? '—' }}</td>
            <td>
              @if($statement->hasBeenChecked())
                {{ $statement->last_checked_at->format('d/m/Y') }}
                @if($statement->last_checked_by)
                  <span style="color:var(--admin-muted)">({{ $statement->last_checked_by }})</span>
                @endif
              @else
                <span style="color:var(--admin-muted)">Mai controllato</span>
              @endif
            </td>
            <td>
              <div class="actions">
                <a class="btn btn--secondary btn--sm" href="{{ route('admin.trust-knowledge.edit', $statement) }}">Modifica</a>
                <form method="POST" action="{{ route('admin.trust-knowledge.destroy', $statement) }}" onsubmit="return confirm('Eliminare questa voce?')" style="display:inline">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn btn--danger btn--sm">Elimina</button>
                </form>
              </div>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <div style="margin-top:1rem">{{ $statements->links() }}</div>
@endif
@endsection
