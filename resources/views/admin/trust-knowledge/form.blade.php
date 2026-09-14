@extends('layouts.admin')
@section('title', $statement ? 'Modifica voce' : 'Nuova voce')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">{{ $statement ? 'Modifica voce' : 'Nuova voce' }}</h1>
  <a class="action-btn" href="{{ route('admin.trust-knowledge.index') }}">Torna a "Cosa sappiamo davvero"</a>
</div>

@if($errors->any())
<div class="admin-alert admin-alert--danger" role="alert">
  @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
</div>
@endif

<p style="color:var(--admin-muted);font-size:.85rem;max-width:82ch;margin-bottom:1rem">
  Modello interno, mai pubblico: solo per uso redazionale. Compila onestamente — "Cosa manca" e "Incertezza" non sono campi opzionali da lasciare vuoti per convenienza, sono la parte più importante di questo modello.
</p>

<form method="POST" action="{{ $statement ? route('admin.trust-knowledge.update', $statement) : route('admin.trust-knowledge.store') }}">
  @csrf
  @if($statement) @method('PUT') @endif
  <div class="admin-card" style="max-width:900px;display:grid;gap:1rem;">
    <div class="form-group">
      <label class="form-label" for="domanda">Domanda</label>
      <input id="domanda" class="form-input" name="domanda" required maxlength="300" value="{{ old('domanda', $statement?->domanda) }}">
    </div>
    <div class="form-group">
      <label class="form-label" for="consenso">Consenso (cosa sappiamo con ragionevole confidenza)</label>
      <textarea id="consenso" class="form-textarea" name="consenso" required rows="5">{{ old('consenso', $statement?->consenso) }}</textarea>
    </div>
    <div class="form-group">
      <label class="form-label" for="incertezza">Incertezza (cosa resta discusso o non provato)</label>
      <textarea id="incertezza" class="form-textarea" name="incertezza" required rows="5">{{ old('incertezza', $statement?->incertezza) }}</textarea>
    </div>
    <div class="form-group">
      <label class="form-label" for="cosa_manca">Cosa manca (esplicitamente fuori portata o non ancora coperto)</label>
      <textarea id="cosa_manca" class="form-textarea" name="cosa_manca" rows="4">{{ old('cosa_manca', $statement?->cosa_manca) }}</textarea>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
      <div class="form-group">
        <label class="form-label" for="last_checked_at">Ultimo controllo</label>
        <input id="last_checked_at" type="date" class="form-input" name="last_checked_at" value="{{ old('last_checked_at', $statement?->last_checked_at?->format('Y-m-d')) }}">
        <small style="color:var(--admin-muted)">Data dichiarata manualmente, mai calcolata dall'ultima modifica.</small>
      </div>
      <div class="form-group">
        <label class="form-label" for="last_checked_by">Controllato da</label>
        <input id="last_checked_by" class="form-input" name="last_checked_by" maxlength="150" value="{{ old('last_checked_by', $statement?->last_checked_by) }}">
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
      <div class="form-group">
        <label class="form-label" for="concept_id">Concetto collegato</label>
        <select id="concept_id" class="form-input" name="concept_id">
          <option value="">Nessuno</option>
          @foreach($concepts as $concept)
            <option value="{{ $concept->id }}" {{ (int) old('concept_id', $statement?->concept_id) === $concept->id ? 'selected' : '' }}>{{ $concept->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="content_cluster_id">Percorso collegato</label>
        <select id="content_cluster_id" class="form-input" name="content_cluster_id">
          <option value="">Nessuno</option>
          @foreach($clusters as $cluster)
            <option value="{{ $cluster->id }}" {{ (int) old('content_cluster_id', $statement?->content_cluster_id) === $cluster->id ? 'selected' : '' }}>{{ $cluster->name }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div style="display:flex;gap:.6rem">
      <button type="submit" class="btn btn--primary">{{ $statement ? 'Salva modifiche' : 'Crea voce' }}</button>
      <a class="btn btn--secondary" href="{{ route('admin.trust-knowledge.index') }}">Annulla</a>
    </div>
  </div>
</form>
@endsection
