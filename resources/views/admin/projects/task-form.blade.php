@extends('layouts.admin')
@section('title', ($task->exists ? 'Modifica' : 'Nuova').' attività — '.$project->title)
@section('content')

<div class="admin-topbar">
  <div>
    <a href="{{ route('admin.progettazione.projects.show', [$project, 'tab' => 'tasks']) }}" style="font-size:.8rem;color:#6b7280;text-decoration:none;">← {{ $project->title }}</a>
    <h1 class="admin-page-title" style="margin-top:.25rem;">{{ $task->exists ? 'Modifica attività' : 'Nuova attività' }}</h1>
  </div>
</div>

<div class="admin-card" style="max-width:760px;">
  <form method="POST"
        action="{{ $task->exists ? route('admin.progettazione.projects.tasks.update', [$project, $task]) : route('admin.progettazione.projects.tasks.store', $project) }}">
    @csrf
    @if($task->exists) @method('PUT') @endif

    <div class="form-group">
      <label class="form-label" for="title">Titolo *</label>
      <input class="form-input" type="text" id="title" name="title" required
             value="{{ old('title', $task->title) }}">
    </div>

    <div class="form-group">
      <label class="form-label" for="description">Descrizione</label>
      <textarea class="form-textarea" id="description" name="description" rows="3">{{ old('description', $task->description) }}</textarea>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
      <div class="form-group">
        <label class="form-label" for="type">Tipo *</label>
        <select class="form-select" id="type" name="type" required onchange="document.getElementById('article-link-group').style.display = this.value === 'publication' ? '' : 'none';">
          @foreach(\App\Models\ProjectTask::typeOptions() as $value => $label)
            <option value="{{ $value }}" @selected(old('type', $task->type ?: 'task') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="manual_status">Stato *</label>
        <select class="form-select" id="manual_status" name="manual_status" required>
          @foreach(\App\Models\ProjectTask::statusOptions() as $value => $label)
            <option value="{{ $value }}" @selected(old('manual_status', $task->manual_status ?: 'todo') === $value)>{{ $label }}</option>
          @endforeach
        </select>
        @if($task->exists && $task->status_source === \App\Models\ProjectTask::SOURCE_DERIVED)
          <div class="status-override-note">Stato derivato automaticamente dall'articolo collegato ({{ \App\Models\ProjectTask::derivedStatusOptions()[$task->derived_status] ?? $task->derived_status }}). Attiva "Ignora sincronizzazione" per modificarlo manualmente.</div>
        @endif
      </div>
      <div class="form-group">
        <label class="form-label" for="priority">Priorità *</label>
        <select class="form-select" id="priority" name="priority" required>
          @foreach(\App\Models\ProjectTask::priorityOptions() as $value => $label)
            <option value="{{ $value }}" @selected(old('priority', $task->priority ?: 'medium') === $value)>{{ $label }}</option>
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
            <option value="{{ $user->id }}" @selected((string) old('responsible_id', $task->responsible_id) === (string) $user->id)>{{ $user->name }}</option>
          @endforeach
        </select>
      </div>

      {{-- Correzione #4 approvata in revisione: il campo "Articolo collegato"
           ha senso solo per le attività di tipo Pubblicazione (è l'unico tipo
           che attiva la sincronizzazione dello stato derivato) — nascosto per
           gli altri tipi invece di essere sempre visibile e potenzialmente
           fuorviante. --}}
      <div class="form-group" id="article-link-group" style="display: {{ old('type', $task->type) === 'publication' ? '' : 'none' }};">
        <label class="form-label" for="article_id">Articolo collegato</label>
        <select class="form-select" id="article_id" name="article_id">
          <option value="">Nessuno</option>
          @foreach($articleOptions as $article)
            <option value="{{ $article->id }}" @selected((string) old('article_id', $task->article_id) === (string) $article->id)>{{ $article->title }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
      <div class="form-group">
        <label class="form-label" for="due_date">Data prevista</label>
        <input class="form-input" type="date" id="due_date" name="due_date"
               value="{{ old('due_date', $task->due_date?->format('Y-m-d')) }}">
      </div>
      <div class="form-group">
        <label class="form-label" for="due_time">Ora prevista</label>
        <input class="form-input" type="time" id="due_time" name="due_time"
               value="{{ old('due_time', $task->due_time?->format('H:i')) }}">
      </div>
    </div>

    @if($task->exists)
      <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.82rem;font-weight:600;color:#111827;margin-bottom:1rem;">
        <input type="checkbox" name="manual_override" value="1" {{ old('manual_override', $task->manual_override) ? 'checked' : '' }} style="width:16px;height:16px;accent-color:#0d9488;">
        Ignora sincronizzazione automatica (mantieni lo stato impostato manualmente)
      </label>
    @endif

    <input type="hidden" name="sort_order" value="0">

    <div style="display:flex;gap:.6rem;margin-top:1rem;">
      <button class="btn btn--primary" type="submit">{{ $task->exists ? 'Salva modifiche' : 'Crea attività' }}</button>
      <a href="{{ route('admin.progettazione.projects.show', [$project, 'tab' => 'tasks']) }}" class="btn btn--secondary">Annulla</a>
    </div>
  </form>

  @if($task->exists)
    <form id="delete-task-form" method="POST" action="{{ route('admin.progettazione.projects.tasks.destroy', [$project, $task]) }}"
          onsubmit="return confirm('Eliminare questa attività?')" style="display:inline;">
      @csrf @method('DELETE')
      <button type="button" class="btn btn--secondary" onclick="document.getElementById('delete-task-form').submit()" style="margin-left:.5rem;">Elimina</button>
    </form>
  @endif
</div>

@endsection
