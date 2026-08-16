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
  <div style="display:flex;gap:.5rem;">
    <a href="{{ route('admin.progettazione.projects.edit', $project) }}" class="btn btn--secondary">✏️ Modifica progetto</a>
    <form id="delete-project-form" method="POST" action="{{ route('admin.progettazione.projects.destroy', $project) }}"
          onsubmit="return confirm('Eliminare definitivamente il progetto «{{ $project->title }}»? Task, documenti, prompt e decisioni collegati verranno eliminati insieme al progetto. L\'azione non è reversibile.')">
      @csrf @method('DELETE')
      <button type="submit" class="btn btn--danger">🗑️ Elimina progetto</button>
    </form>
  </div>
</div>

<div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;margin-bottom:1.25rem;">
  <span class="status status--project-{{ $project->operational_status }}">{{ $statusOptions[$project->operational_status] ?? $project->operational_status }}</span>
  <span class="status status--priority-{{ $project->priority }}">{{ \App\Models\Project::priorityOptions()[$project->priority] ?? $project->priority }}</span>
  <span class="status" style="background:#f3f4f6;color:#4b5563;">{{ \App\Models\Project::typeOptions()[$project->type] ?? $project->type }}</span>

  {{-- FASE 6, missione Dashboard Automation V2: nessun badge per "OK" —
       niente etichette allarmistiche per una condizione normale, il badge
       compare solo quando c'è davvero qualcosa da notare. --}}
  @if($projectHealth->level !== \App\Services\ProjectAction\ProjectHealth::LEVEL_OK)
    <span class="status project-health-badge" data-health="{{ $projectHealth->level }}" style="background:{{ $projectHealth->level === 'blocked' ? '#fee2e2' : '#fef3c7' }};color:{{ $projectHealth->level === 'blocked' ? '#991b1b' : '#92400e' }};" title="Calcolato dallo stato corrente del progetto">
      {{ $projectHealth->label() }}
    </span>
  @endif

  {{-- Correzione #3 approvata in revisione: cambio rapido dello stato senza
       passare dal form di modifica completo. Widget riconoscibile (bordo,
       sfondo colorato, etichetta maiuscola) invece di una <select> isolata. --}}
  <form method="POST" action="{{ route('admin.progettazione.projects.update-status', $project) }}" class="project-status-switcher" style="margin-left:auto;">
    @csrf
    @method('PATCH')
    <label for="quick-status">Stato progetto</label>
    <select id="quick-status" name="operational_status" onchange="this.form.submit()">
      @foreach($statusOptions as $value => $label)
        <option value="{{ $value }}" @selected($project->operational_status === $value)>{{ $label }}</option>
      @endforeach
    </select>
  </form>
</div>

<nav aria-label="Schede progetto" class="project-tabs">
  @foreach($tabs as $key => $label)
    <a href="{{ route('admin.progettazione.projects.show', [$project, 'tab' => $key]) }}"
       @class(['active' => $activeTab === $key])
       aria-current="{{ $activeTab === $key ? 'page' : 'false' }}">
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
        <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Prossima azione editoriale</dt>
        <dd style="margin:.2rem 0 0;font-weight:600;">{{ $project->next_action ?? '—' }}</dd>
      </div>
      <div>
        <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Avanzamento</dt>
        <dd style="margin:.2rem 0 0;font-weight:600;">
          @if($project->hasCalculableProgress())
            {{ $project->progress }}%
          @else
            <span style="font-weight:400;color:#9ca3af;">Non calcolabile (nessuna attività)</span>
          @endif
        </dd>
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

  {{-- FASE 3-4, missione Dashboard Automation V2: sempre distinto dalla
       "Prossima azione editoriale" sopra — un suggerimento calcolato, mai
       applicato automaticamente, mai scritto in next_action. --}}
  <div class="admin-card" style="margin-top:1rem;">
    <h3 style="margin-top:0;">Suggerimento automatico</h3>
    <div style="display:flex;align-items:flex-start;gap:.6rem;">
      @php
        $severityColors = [
          'urgent' => ['#fee2e2', '#991b1b'],
          'attention' => ['#fef3c7', '#92400e'],
          'info' => ['#f3f4f6', '#6b7280'],
        ][$nextActionSuggestion->severity] ?? ['#f3f4f6', '#6b7280'];
      @endphp
      <span class="status" style="background:{{ $severityColors[0] }};color:{{ $severityColors[1] }};flex:none;margin-top:.1rem;">
        {{ ['urgent' => 'Urgente', 'attention' => 'Attenzione', 'info' => 'Info'][$nextActionSuggestion->severity] ?? $nextActionSuggestion->severity }}
      </span>
      <div>
        <div style="font-weight:600;">
          {{ $nextActionSuggestion->label }}
          @if($nextActionSuggestion->entityType === 'project_task' && $nextActionSuggestion->entityId)
            — <a href="{{ route('admin.progettazione.projects.tasks.edit', [$project, $nextActionSuggestion->entityId]) }}">Apri attività</a>
          @endif
        </div>
        @if($nextActionSuggestion->rationale)
          <div style="font-size:.84rem;color:#6b7280;margin-top:.25rem;">{{ $nextActionSuggestion->rationale }}</div>
        @endif
      </div>
    </div>
    <div style="margin-top:.85rem;font-size:.76rem;color:#9ca3af;">
      Suggerimento calcolato dallo stato corrente del progetto — nessuna modifica automatica.
    </div>
  </div>

  @if($editorialProgress)
    {{-- FASE 8 automazione Piano Editoriale: fotografia compatta, mai
         un'unica percentuale fuorviante — vedi EditorialCalendarProgress. --}}
    <div class="admin-card" style="margin-top:1rem;">
      <h3 style="margin-top:0;">Piano editoriale</h3>
      <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;">
        <div>
          <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Voci pianificate</dt>
          <dd style="margin:.2rem 0 0;font-weight:600;">{{ $editorialProgress->totalPlanned }}</dd>
        </div>
        <div>
          <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Copertura (con articolo)</dt>
          <dd style="margin:.2rem 0 0;font-weight:600;">{{ $editorialProgress->coveragePercent }}%</dd>
        </div>
        <div>
          <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Pubblicato</dt>
          <dd style="margin:.2rem 0 0;font-weight:600;">{{ $editorialProgress->publishedPercent }}%</dd>
        </div>
        <div>
          <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Senza articolo</dt>
          <dd style="margin:.2rem 0 0;font-weight:600;">{{ $editorialProgress->missingArticleCount }}</dd>
        </div>
        <div>
          <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Richiedono revisione</dt>
          <dd style="margin:.2rem 0 0;font-weight:600;">{{ $editorialProgress->needsReviewCount }}</dd>
        </div>
      </dl>
      <div style="margin-top:.85rem;font-size:.76rem;color:#9ca3af;">
        Aggiornato automaticamente dal calendario editoriale collegato. Nessuna modifica applicata qui — usa <code>project:sync-editorial-calendar</code> per collegare i match sicuri.
      </div>
    </div>
  @endif
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
