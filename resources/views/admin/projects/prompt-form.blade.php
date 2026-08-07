@extends('layouts.admin')
@section('title', ($prompt->exists ? 'Modifica' : 'Nuovo').' prompt — '.$project->title)
@section('content')

<div class="admin-topbar">
  <div>
    <a href="{{ route('admin.progettazione.projects.show', [$project, 'tab' => 'prompts']) }}" style="font-size:.8rem;color:#6b7280;text-decoration:none;">← {{ $project->title }}</a>
    <h1 class="admin-page-title" style="margin-top:.25rem;">{{ $prompt->exists ? 'Modifica prompt' : 'Nuovo prompt' }}</h1>
  </div>
</div>

<div class="admin-card" style="max-width:760px;">
  <form method="POST"
        action="{{ $prompt->exists ? route('admin.progettazione.projects.prompts.update', [$project, $prompt]) : route('admin.progettazione.projects.prompts.store', $project) }}">
    @csrf
    @if($prompt->exists) @method('PUT') @endif

    <div class="form-group">
      <label class="form-label" for="title">Titolo *</label>
      <input class="form-input" type="text" id="title" name="title" required
             value="{{ old('title', $prompt->title) }}">
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
      <div class="form-group">
        <label class="form-label" for="agent">Agente</label>
        <input class="form-input" type="text" id="agent" name="agent"
               value="{{ old('agent', $prompt->agent) }}" placeholder="es. Claude Code">
      </div>
      <div class="form-group">
        <label class="form-label" for="status">Stato *</label>
        <select class="form-select" id="status" name="status" required>
          @foreach(\App\Models\ProjectPrompt::statusOptions() as $value => $label)
            <option value="{{ $value }}" @selected(old('status', $prompt->status ?: 'draft') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="task_id">Attività collegata</label>
      <select class="form-select" id="task_id" name="task_id">
        <option value="">Nessuna</option>
        @foreach($taskOptions as $t)
          <option value="{{ $t->id }}" @selected((string) old('task_id', $prompt->task_id) === (string) $t->id)>{{ $t->title }}</option>
        @endforeach
      </select>
      <div style="font-size:.76rem;color:#9ca3af;margin-top:.25rem;">
        Se l'attività è di tipo Sviluppo e la sua PR viene mergiata, questo prompt si completa automaticamente (esito e stato), senza sovrascrivere un esito già scritto a mano.
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="content">Contenuto *</label>
      <textarea class="form-textarea" id="content" name="content" rows="8" required>{{ old('content', $prompt->content) }}</textarea>
    </div>

    <div class="form-group">
      <label class="form-label" for="outcome">Esito</label>
      <textarea class="form-textarea" id="outcome" name="outcome" rows="3">{{ old('outcome', $prompt->outcome) }}</textarea>
    </div>

    <div style="display:flex;gap:.6rem;margin-top:1rem;">
      <button class="btn btn--primary" type="submit">{{ $prompt->exists ? 'Salva modifiche' : 'Crea prompt' }}</button>
      <a href="{{ route('admin.progettazione.projects.show', [$project, 'tab' => 'prompts']) }}" class="btn btn--secondary">Annulla</a>
    </div>
  </form>

  @if($prompt->exists)
    <form id="delete-prompt-form" method="POST" action="{{ route('admin.progettazione.projects.prompts.destroy', [$project, $prompt]) }}"
          onsubmit="return confirm('Eliminare questo prompt?')" style="display:inline;">
      @csrf @method('DELETE')
      <button type="button" class="btn btn--danger" onclick="document.getElementById('delete-prompt-form').submit()" style="margin-left:.5rem;">🗑️ Elimina</button>
    </form>
  @endif
</div>

@endsection
