@extends('layouts.admin')
@section('title', $project->title.' — Progettazione')
@section('content')

@php
  $tabs = [
    'overview' => 'Panoramica',
    'roadmap' => 'Roadmap',
    'tasks' => 'Attività',
    'articles' => 'Articoli',
    'documents' => 'Documenti',
    'prompts' => 'Prompt',
    'decisions' => 'Decisioni',
    'history' => 'Cronologia',
  ];
@endphp

<div class="admin-topbar">
  <div>
    <a href="{{ route('admin.progettazione.projects.index') }}" style="font-size:.8rem;color:#6b7280;text-decoration:none;">← Progetti</a>
    <h1 class="admin-page-title" style="margin-top:.25rem;">{{ $project->title }}</h1>
  </div>
  <a href="{{ route('admin.progettazione.projects.edit', $project) }}" class="btn btn--secondary">Modifica progetto</a>
</div>

<div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;margin-bottom:1.25rem;">
  <span class="status status--project-{{ $project->operational_status }}">{{ $statusOptions[$project->operational_status] ?? $project->operational_status }}</span>
  <span class="status status--priority-{{ $project->priority }}">{{ \App\Models\Project::priorityOptions()[$project->priority] ?? $project->priority }}</span>
  <span class="status" style="background:#f3f4f6;color:#4b5563;">{{ \App\Models\Project::typeOptions()[$project->type] ?? $project->type }}</span>

  {{-- Correzione #3 approvata in revisione: cambio rapido dello stato senza
       passare dal form di modifica completo. --}}
  <form method="POST" action="{{ route('admin.progettazione.projects.update-status', $project) }}" style="margin-left:auto;display:flex;align-items:center;gap:.4rem;">
    @csrf
    @method('PATCH')
    <label for="quick-status" style="font-size:.72rem;color:#6b7280;">Cambia stato:</label>
    <select id="quick-status" name="operational_status" class="form-select" style="max-width:200px;padding:.35rem .5rem;font-size:.8rem;" onchange="this.form.submit()">
      @foreach($statusOptions as $value => $label)
        <option value="{{ $value }}" @selected($project->operational_status === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </form>
</div>

<nav aria-label="Schede progetto" style="display:flex;gap:.25rem;flex-wrap:wrap;border-bottom:1px solid #e5e7eb;margin-bottom:1.5rem;">
  @foreach($tabs as $key => $label)
    <a href="{{ route('admin.progettazione.projects.show', [$project, 'tab' => $key]) }}"
       aria-current="{{ $activeTab === $key ? 'page' : 'false' }}"
       style="padding:.65rem 1rem;font-size:.85rem;font-weight:600;text-decoration:none;
              color:{{ $activeTab === $key ? '#0d9488' : '#6b7280' }};
              border-bottom:2px solid {{ $activeTab === $key ? '#0d9488' : 'transparent' }};">
      {{ $label }}
    </a>
  @endforeach
</nav>

@if($activeTab === 'overview')
  <div class="admin-card">
    <h3 style="margin-top:0;">Panoramica</h3>

    @if($project->description)
      <p>{{ $project->description }}</p>
    @endif

    @if($project->objective)
      <p><strong>Obiettivo:</strong> {{ $project->objective }}</p>
    @endif

    <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-top:1.25rem;">
      <div>
        <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Responsabile</dt>
        <dd style="margin:.2rem 0 0;font-weight:600;">{{ $project->responsible?->name ?? '—' }}</dd>
      </div>
      <div>
        <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Prossima azione</dt>
        <dd style="margin:.2rem 0 0;font-weight:600;">{{ $project->next_action ?? '—' }}</dd>
      </div>
      <div>
        <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Avanzamento</dt>
        <dd style="margin:.2rem 0 0;font-weight:600;">{{ $project->progress }}%</dd>
      </div>
      <div>
        <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Data di inizio</dt>
        <dd style="margin:.2rem 0 0;font-weight:600;">{{ $project->start_date?->format('d/m/Y') ?? '—' }}</dd>
      </div>
      <div>
        <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Scadenza</dt>
        <dd style="margin:.2rem 0 0;font-weight:600;">{{ $project->due_date?->format('d/m/Y') ?? '—' }}</dd>
      </div>
    </dl>

    @if($project->internal_notes)
      <div style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid #f1f5f9;">
        <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Note interne</dt>
        <dd style="margin:.4rem 0 0;white-space:pre-line;">{{ $project->internal_notes }}</dd>
      </div>
    @endif
  </div>
@elseif($activeTab === 'roadmap')
  @include('admin.projects._roadmap')
@elseif($activeTab === 'tasks')
  @include('admin.projects._tasks')
@elseif($activeTab === 'articles')
  @include('admin.projects._articles')
@elseif($activeTab === 'documents')
  @include('admin.projects._documents')
@elseif($activeTab === 'prompts')
  @include('admin.projects._prompts')
@elseif($activeTab === 'decisions')
  @include('admin.projects._decisions')
@elseif($activeTab === 'history')
  @include('admin.projects._history')
@endif

@endsection
