@extends('layouts.admin')
@section('title', 'Nuova attività — scegli un progetto')
@section('content')

<div class="admin-topbar">
  <div>
    <a href="{{ route('admin.progettazione.tasks.index-all') }}" style="font-size:.8rem;color:#6b7280;text-decoration:none;">← Attività progetti</a>
    <h1 class="admin-page-title" style="margin-top:.25rem;">A quale progetto appartiene la nuova attività?</h1>
  </div>
</div>

@if($projects->isEmpty())
  <div class="admin-card project-empty-state">
    <div class="project-empty-state__icon">📁</div>
    <p class="project-empty-state__text">Non esiste ancora nessun progetto. <strong>Crea un progetto</strong> prima di poter aggiungere un'attività.</p>
    <a href="{{ route('admin.progettazione.projects.create') }}" class="btn btn--primary">+ Nuovo progetto</a>
  </div>
@else
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Progetto</th>
          <th>Stato</th>
          <th>Priorità</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @foreach($projects as $project)
        <tr>
          <td style="font-weight:700;">{{ $project->title }}</td>
          <td><span class="status status--project-{{ $project->operational_status }}">{{ \App\Models\Project::statusOptions()[$project->operational_status] ?? $project->operational_status }}</span></td>
          <td><span class="status status--priority-{{ $project->priority }}">{{ \App\Models\Project::priorityOptions()[$project->priority] ?? $project->priority }}</span></td>
          <td><a href="{{ route('admin.progettazione.projects.tasks.create', $project) }}" class="btn btn--primary btn--sm">Scegli →</a></td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif

@endsection
