@extends('layouts.admin')
@section('title', 'Progetti — Progettazione')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Progetti</h1>
  <a href="{{ route('admin.progettazione.projects.create') }}" class="btn btn--primary">+ Nuovo progetto</a>
</div>

<form method="GET" style="display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1.25rem;">
  <select name="status" class="form-input" style="max-width:220px;" onchange="this.form.submit()">
    <option value="">Tutti gli stati</option>
    @foreach($statusOptions as $value => $label)
      <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
    @endforeach
  </select>
  <select name="type" class="form-input" style="max-width:220px;" onchange="this.form.submit()">
    <option value="">Tutti i tipi</option>
    @foreach($typeOptions as $value => $label)
      <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
    @endforeach
  </select>
</form>

@if($projects->isEmpty())
  <div class="admin-card" style="text-align:center;padding:3rem;color:#6b7280;">
    <p style="font-size:1.5rem;margin-bottom:.5rem;">📁</p>
    <p>Nessun progetto trovato.</p>
    <a href="{{ route('admin.progettazione.projects.create') }}" class="btn btn--primary" style="margin-top:.75rem;">Crea il primo</a>
  </div>
@else
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Titolo</th>
          <th>Tipo</th>
          <th>Stato</th>
          <th>Priorità</th>
          <th>Responsabile</th>
          <th>Avanzamento</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        @foreach($projects as $project)
        <tr>
          <td><a href="{{ route('admin.progettazione.projects.show', $project) }}" style="font-weight:700;">{{ $project->title }}</a></td>
          <td>{{ $typeOptions[$project->type] ?? $project->type }}</td>
          <td><span class="status status--project-{{ $project->operational_status }}">{{ $statusOptions[$project->operational_status] ?? $project->operational_status }}</span></td>
          <td><span class="status status--priority-{{ $project->priority }}">{{ \App\Models\Project::priorityOptions()[$project->priority] ?? $project->priority }}</span></td>
          <td>{{ $project->responsible?->name ?? '—' }}</td>
          <td>
            @if($project->open_tasks_count > 0)
              {{ $project->progress }}%
            @else
              <span style="color:#9ca3af;">n/d</span>
            @endif
          </td>
          <td><a href="{{ route('admin.progettazione.projects.show', $project) }}" class="action-btn">Apri</a></td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{ $projects->links() }}
@endif

@endsection
