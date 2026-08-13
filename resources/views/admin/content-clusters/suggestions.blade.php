@extends('layouts.admin')
@section('title','Suggerimenti Percorsi')
@section('content')
<div class="admin-topbar">
  <h1 class="admin-page-title">Suggerimenti Percorsi</h1>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
    <form method="POST" action="{{ route('admin.content-cluster-suggestions.regenerate') }}">@csrf<button class="btn btn--primary" type="submit">Rigenera suggerimenti</button></form>
    <a class="action-btn" href="{{ route('admin.content-clusters.index') }}">Torna ai percorsi</a>
  </div>
</div>

@if($errors->any())<div class="admin-alert admin-alert--danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

<section class="admin-card" aria-labelledby="suggestion-filters-title">
  <h2 id="suggestion-filters-title">Filtri</h2>
  <form method="GET" action="{{ route('admin.content-cluster-suggestions.index') }}" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:.75rem;align-items:end;">
    <div class="form-group"><label class="form-label" for="q">Articolo</label><input id="q" class="form-input" name="q" maxlength="120" value="{{ request('q') }}"></div>
    <div class="form-group"><label class="form-label" for="status">Stato</label><select id="status" class="form-input" name="status"><option value="">Tutti</option>@foreach(['pending'=>'Pending','accepted'=>'Accepted','rejected'=>'Rejected','stale'=>'Stale'] as $value=>$label)<option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
    <div class="form-group"><label class="form-label" for="cluster">Percorso</label><select id="cluster" class="form-input" name="cluster"><option value="">Tutti</option>@foreach($clusters as $cluster)<option value="{{ $cluster->id }}" {{ (string) request('cluster') === (string) $cluster->id ? 'selected' : '' }}>{{ $cluster->name }}</option>@endforeach</select></div>
    <button class="btn btn--primary" type="submit">Filtra</button>
  </form>
</section>

<div class="admin-table-wrap" style="margin-top:1rem;">
  <table class="admin-table">
    <thead><tr><th>Articolo</th><th>Percorso</th><th>Forza</th><th>Evidence</th><th>Stato</th><th>Conflict</th><th>Azioni</th></tr></thead>
    <tbody>
    @forelse($suggestions as $suggestion)
      <tr>
        <td><strong>{{ $suggestion->article?->title }}</strong><br><small>{{ $suggestion->article?->status }}</small></td>
        <td>{{ $suggestion->contentCluster?->name }}@if($suggestion->suggested_primary)<br><small>Primary suggerito</small>@endif</td>
        <td>{{ $suggestion->confidence }}/100</td>
        <td><ul style="margin:0;padding-left:1.2rem;">@foreach($suggestion->reasons ?? [] as $reason)<li>{{ $reason }}</li>@endforeach</ul></td>
        <td>{{ strtoupper($suggestion->status) }}</td>
        <td>{{ $suggestion->primary_conflict ? 'Primary esistente: '.$suggestion->primary_conflict : '—' }}</td>
        <td>
          @if($suggestion->status === 'pending')
            <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
              <form method="POST" action="{{ route('admin.content-cluster-suggestions.accept', $suggestion) }}">@csrf<button class="action-btn" type="submit" {{ $suggestion->primary_conflict ? 'disabled' : '' }}>Accetta</button></form>
              <form method="POST" action="{{ route('admin.content-cluster-suggestions.reject', $suggestion) }}">@csrf<button class="action-btn" type="submit">Rifiuta</button></form>
            </div>
          @else
            <span aria-label="Nessuna azione disponibile">—</span>
          @endif
        </td>
      </tr>
    @empty
      <tr><td colspan="7">Nessun suggerimento per i filtri correnti.</td></tr>
    @endforelse
    </tbody>
  </table>
</div>
{{ $suggestions->links() }}
@endsection
