@extends('layouts.admin')
@section('title', ($project->exists ? 'Modifica' : 'Nuovo').' progetto — Progettazione')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">{{ $project->exists ? 'Modifica progetto' : 'Nuovo progetto' }}</h1>
</div>

<div class="admin-card" style="max-width:760px;">
  <form method="POST"
        action="{{ $project->exists ? route('admin.progettazione.projects.update', $project) : route('admin.progettazione.projects.store') }}">
    @csrf
    @if($project->exists) @method('PUT') @endif

    <div class="form-group">
      <label class="form-label" for="title">Titolo *</label>
      <input class="form-input" type="text" id="title" name="title" required
             value="{{ old('title', $project->title) }}">
      @error('title') <div style="color:#991b1b;font-size:.78rem;margin-top:.25rem;">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
      <label class="form-label" for="description">Descrizione</label>
      <textarea class="form-textarea" id="description" name="description" rows="3">{{ old('description', $project->description) }}</textarea>
    </div>

    <div class="form-group">
      <label class="form-label" for="objective">Obiettivo</label>
      <textarea class="form-textarea" id="objective" name="objective" rows="2">{{ old('objective', $project->objective) }}</textarea>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
      <div class="form-group">
        <label class="form-label" for="type">Tipo *</label>
        <select class="form-select" id="type" name="type" required>
          @foreach(\App\Models\Project::typeOptions() as $value => $label)
            <option value="{{ $value }}" @selected(old('type', $project->type) === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="operational_status">Stato *</label>
        <select class="form-select" id="operational_status" name="operational_status" required>
          @foreach(\App\Models\Project::statusOptions() as $value => $label)
            <option value="{{ $value }}" @selected(old('operational_status', $project->operational_status ?: 'idea') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="priority">Priorità *</label>
        <select class="form-select" id="priority" name="priority" required>
          @foreach(\App\Models\Project::priorityOptions() as $value => $label)
            <option value="{{ $value }}" @selected(old('priority', $project->priority ?: 'medium') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
      <div class="form-group">
        <label class="form-label" for="responsible_id">Responsabile</label>
        <select class="form-select" id="responsible_id" name="responsible_id">
          <option value="">Nessuno</option>
          @foreach($responsibleOptions as $user)
            <option value="{{ $user->id }}" @selected((string) old('responsible_id', $project->responsible_id) === (string) $user->id)>{{ $user->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="next_action">Prossima azione</label>
        <input class="form-input" type="text" id="next_action" name="next_action"
               value="{{ old('next_action', $project->next_action) }}">
        @if($suggestedNextAction)
          <div style="font-size:.78rem;color:#0d9488;margin-top:.4rem;display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
            <span>💡 Suggerimento: {{ $suggestedNextAction }}</span>
            <button type="button" class="action-btn" onclick="document.getElementById('next_action').value = {{ Js::from($suggestedNextAction) }};">Applica</button>
          </div>
        @endif
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
      <div class="form-group">
        <label class="form-label" for="start_date">Data di inizio</label>
        <input class="form-input" type="date" id="start_date" name="start_date"
               value="{{ old('start_date', $project->start_date?->format('Y-m-d')) }}">
      </div>
      <div class="form-group">
        <label class="form-label" for="due_date">Scadenza</label>
        <input class="form-input" type="date" id="due_date" name="due_date"
               value="{{ old('due_date', $project->due_date?->format('Y-m-d')) }}">
      </div>
      <div class="form-group">
        <span class="form-label" style="display:block;">Avanzamento</span>
        <div style="font-weight:700;font-size:1.1rem;padding-top:.35rem;">{{ $project->progress ?? 0 }}%</div>
        <div style="font-size:.76rem;color:#9ca3af;margin-top:.25rem;">Calcolato automaticamente dalle attività completate — non modificabile.</div>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="internal_notes">Note interne</label>
      <textarea class="form-textarea" id="internal_notes" name="internal_notes" rows="3">{{ old('internal_notes', $project->internal_notes) }}</textarea>
    </div>

    <div style="display:flex;gap:.6rem;margin-top:1rem;">
      <button class="btn btn--primary" type="submit">{{ $project->exists ? 'Salva modifiche' : 'Crea progetto' }}</button>
      <a href="{{ $project->exists ? route('admin.progettazione.projects.show', $project) : route('admin.progettazione.projects.index') }}" class="btn btn--secondary">Annulla</a>
    </div>
  </form>

  @if($project->exists)
    <form id="delete-project-form" method="POST" action="{{ route('admin.progettazione.projects.destroy', $project) }}"
          onsubmit="return confirm('Eliminare definitivamente il progetto «{{ $project->title }}»? Task, documenti, prompt e decisioni collegati verranno eliminati insieme al progetto. L\'azione non è reversibile.')"
          style="margin-top:.75rem;">
      @csrf @method('DELETE')
      <button type="submit" class="btn btn--danger">🗑️ Elimina progetto</button>
    </form>
  @endif
</div>

@endsection
