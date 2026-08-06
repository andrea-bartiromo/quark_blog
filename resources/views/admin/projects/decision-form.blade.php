@extends('layouts.admin')
@section('title', ($decision->exists ? 'Modifica' : 'Nuova').' decisione — '.$project->title)
@section('content')

<div class="admin-topbar">
  <div>
    <a href="{{ route('admin.progettazione.projects.show', [$project, 'tab' => 'decisions']) }}" style="font-size:.8rem;color:#6b7280;text-decoration:none;">← {{ $project->title }}</a>
    <h1 class="admin-page-title" style="margin-top:.25rem;">{{ $decision->exists ? 'Modifica decisione' : 'Nuova decisione' }}</h1>
  </div>
</div>

<div class="admin-card" style="max-width:760px;">
  <form method="POST"
        action="{{ $decision->exists ? route('admin.progettazione.projects.decisions.update', [$project, $decision]) : route('admin.progettazione.projects.decisions.store', $project) }}">
    @csrf
    @if($decision->exists) @method('PUT') @endif

    <div class="form-group">
      <label class="form-label" for="title">Titolo *</label>
      <input class="form-input" type="text" id="title" name="title" required
             value="{{ old('title', $decision->title) }}">
    </div>

    <div class="form-group">
      <label class="form-label" for="context">Contesto</label>
      <textarea class="form-textarea" id="context" name="context" rows="3">{{ old('context', $decision->context) }}</textarea>
    </div>

    <div class="form-group">
      <label class="form-label" for="decision">Decisione *</label>
      <textarea class="form-textarea" id="decision" name="decision" rows="3" required>{{ old('decision', $decision->decision) }}</textarea>
    </div>

    <div class="form-group">
      <label class="form-label" for="rationale">Motivazione</label>
      <textarea class="form-textarea" id="rationale" name="rationale" rows="3">{{ old('rationale', $decision->rationale) }}</textarea>
    </div>

    <div class="form-group">
      <label class="form-label" for="status">Stato *</label>
      <select class="form-select" id="status" name="status" required>
        @foreach(\App\Models\ProjectDecision::statusOptions() as $value => $label)
          <option value="{{ $value }}" @selected(old('status', $decision->status ?: 'proposed') === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <div style="display:flex;gap:.6rem;margin-top:1rem;">
      <button class="btn btn--primary" type="submit">{{ $decision->exists ? 'Salva modifiche' : 'Registra decisione' }}</button>
      <a href="{{ route('admin.progettazione.projects.show', [$project, 'tab' => 'decisions']) }}" class="btn btn--secondary">Annulla</a>
    </div>
  </form>

  @if($decision->exists)
    <form id="delete-decision-form" method="POST" action="{{ route('admin.progettazione.projects.decisions.destroy', [$project, $decision]) }}"
          onsubmit="return confirm('Eliminare questa decisione?')" style="display:inline;">
      @csrf @method('DELETE')
      <button type="button" class="btn btn--danger" onclick="document.getElementById('delete-decision-form').submit()" style="margin-left:.5rem;">🗑️ Elimina</button>
    </form>
  @endif
</div>

@endsection
