@extends('layouts.admin')
@section('title', 'Progettazione')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Progettazione</h1>
  <a href="{{ route('admin.progettazione.projects.create') }}" class="btn btn--primary">+ Nuovo progetto</a>
</div>

<dl class="admin-grid admin-grid--stats" aria-label="Riepilogo Progettazione" style="margin-bottom:1.5rem;">
  <div class="stat-card">
    <dt class="stat-card__label">Progetti attivi</dt>
    <dd class="stat-card__value">{{ $activeProjects->count() }}</dd>
  </div>
  <div class="stat-card">
    <dt class="stat-card__label">Progetti bloccati</dt>
    <dd class="stat-card__value">{{ $blockedProjects->count() }}</dd>
  </div>
  <div class="stat-card">
    <dt class="stat-card__label">Attività in scadenza</dt>
    <dd class="stat-card__value">{{ $dueSoonTasks->count() }}</dd>
  </div>
  <div class="stat-card">
    <dt class="stat-card__label">Attività scadute</dt>
    <dd class="stat-card__value">{{ $overdueTasks->count() }}</dd>
  </div>
</dl>

<div class="admin-grid" style="grid-template-columns:repeat(auto-fit,minmax(360px,1fr));">

  <div class="admin-card">
    <h3 style="margin-top:0;">Progetti attivi</h3>
    @if($activeProjects->isEmpty())
      <p style="color:#9ca3af;">Nessun progetto attivo.</p>
    @else
      @foreach($activeProjects as $project)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;{{ ! $loop->last ? 'border-bottom:1px solid #f1f5f9;' : '' }}">
          <a href="{{ route('admin.progettazione.projects.show', $project) }}" style="font-weight:600;text-decoration:none;color:#111827;">{{ $project->title }}</a>
          <span class="status status--priority-{{ $project->priority }}">{{ \App\Models\Project::priorityOptions()[$project->priority] ?? $project->priority }}</span>
        </div>
      @endforeach
    @endif
  </div>

  <div class="admin-card">
    <h3 style="margin-top:0;">Progetti bloccati</h3>
    @if($blockedProjects->isEmpty())
      <p style="color:#9ca3af;">Nessun progetto bloccato.</p>
    @else
      @foreach($blockedProjects as $project)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;{{ ! $loop->last ? 'border-bottom:1px solid #f1f5f9;' : '' }}">
          <a href="{{ route('admin.progettazione.projects.show', $project) }}" style="font-weight:600;text-decoration:none;color:#111827;">{{ $project->title }}</a>
          <span style="font-size:.72rem;color:#6b7280;">{{ $project->responsible?->name ?? '—' }}</span>
        </div>
      @endforeach
    @endif
  </div>

  <div class="admin-card">
    <h3 style="margin-top:0;">Attività in scadenza (7 giorni)</h3>
    @if($dueSoonTasks->isEmpty())
      <p style="color:#9ca3af;">Nessuna attività in scadenza.</p>
    @else
      @foreach($dueSoonTasks as $task)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;{{ ! $loop->last ? 'border-bottom:1px solid #f1f5f9;' : '' }}">
          <a href="{{ route('admin.progettazione.projects.tasks.edit', [$task->project, $task]) }}" style="text-decoration:none;color:#111827;">{{ $task->title }}</a>
          <span style="font-size:.72rem;color:#6b7280;">{{ $task->due_date?->format('d/m/Y') }}</span>
        </div>
      @endforeach
    @endif
  </div>

  <div class="admin-card">
    <h3 style="margin-top:0;">Attività scadute</h3>
    @if($overdueTasks->isEmpty())
      <p style="color:#9ca3af;">Nessuna attività scaduta.</p>
    @else
      @foreach($overdueTasks as $task)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;{{ ! $loop->last ? 'border-bottom:1px solid #f1f5f9;' : '' }}">
          <a href="{{ route('admin.progettazione.projects.tasks.edit', [$task->project, $task]) }}" style="text-decoration:none;color:#991b1b;font-weight:600;">{{ $task->title }}</a>
          <span style="font-size:.72rem;color:#991b1b;">{{ $task->due_date?->format('d/m/Y') }}</span>
        </div>
      @endforeach
    @endif
  </div>

  <div class="admin-card">
    <h3 style="margin-top:0;">Prossime pubblicazioni</h3>
    @if($upcomingPublications->isEmpty())
      <p style="color:#9ca3af;">Nessuna pubblicazione programmata.</p>
    @else
      @foreach($upcomingPublications as $task)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;{{ ! $loop->last ? 'border-bottom:1px solid #f1f5f9;' : '' }}">
          <span>{{ $task->article?->title ?? $task->title }}</span>
          <span style="font-size:.72rem;color:#6b7280;">{{ $task->due_date?->format('d/m/Y') }}</span>
        </div>
      @endforeach
    @endif
  </div>

  <div class="admin-card">
    <h3 style="margin-top:0;">Documenti recenti</h3>
    @if($recentDocuments->isEmpty())
      <p style="color:#9ca3af;">Nessun documento ancora.</p>
    @else
      @foreach($recentDocuments as $document)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;{{ ! $loop->last ? 'border-bottom:1px solid #f1f5f9;' : '' }}">
          <a href="{{ route('admin.progettazione.projects.documents.edit', [$document->project, $document]) }}" style="text-decoration:none;color:#111827;">{{ $document->title }}</a>
          <span style="font-size:.72rem;color:#6b7280;">{{ $document->project->title }}</span>
        </div>
      @endforeach
    @endif
  </div>

  <div class="admin-card">
    <h3 style="margin-top:0;">Prossime azioni prioritarie</h3>
    @if($priorityNextActions->isEmpty())
      <p style="color:#9ca3af;">Nessuna azione prioritaria in evidenza.</p>
    @else
      @foreach($priorityNextActions as $project)
        <div style="padding:.5rem 0;{{ ! $loop->last ? 'border-bottom:1px solid #f1f5f9;' : '' }}">
          <a href="{{ route('admin.progettazione.projects.show', $project) }}" style="font-weight:600;text-decoration:none;color:#111827;">{{ $project->title }}</a>
          <div style="font-size:.78rem;color:#6b7280;">{{ $project->next_action }}</div>
        </div>
      @endforeach
    @endif
  </div>

  <div class="admin-card">
    <h3 style="margin-top:0;">Attività recente</h3>
    @if($lastActivity->isEmpty())
      <p style="color:#9ca3af;">Nessuna attività registrata ancora.</p>
    @else
      @foreach($lastActivity as $log)
        <div style="padding:.5rem 0;{{ ! $loop->last ? 'border-bottom:1px solid #f1f5f9;' : '' }}">
          <div style="font-weight:600;font-size:.85rem;">{{ $log->action }}</div>
          <div style="font-size:.72rem;color:#9ca3af;">{{ $log->project->title }} · {{ $log->user?->name ?? 'Sistema' }} · {{ $log->created_at->format('d/m/Y H:i') }}</div>
        </div>
      @endforeach
    @endif
  </div>

</div>

@endsection
