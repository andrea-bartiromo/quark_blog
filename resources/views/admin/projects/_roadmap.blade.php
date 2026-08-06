@php
  $roadmapTasks = $project->tasks()
      ->with(['responsible', 'article'])
      ->get()
      ->sortBy(fn ($task) => $task->due_date ?? \Illuminate\Support\Carbon::parse('9999-12-31'))
      ->values();
@endphp

<h3 style="margin-top:0;">Roadmap</h3>

@if($roadmapTasks->isEmpty())
  <div class="admin-card project-empty-state">
    <div class="project-empty-state__icon">🗺️</div>
    <p class="project-empty-state__text">Nessuna attività da mostrare in roadmap. <strong>Aggiungi un'attività</strong> per iniziare a costruire la linea temporale del progetto.</p>
    <a href="{{ route('admin.progettazione.projects.tasks.create', $project) }}" class="btn btn--primary">+ Nuova attività</a>
  </div>
@else
  <div class="admin-card" style="padding:0;">
    @foreach($roadmapTasks as $task)
      <div style="display:flex;align-items:center;gap:1rem;padding:.9rem 1.25rem;{{ ! $loop->last ? 'border-bottom:1px solid #f1f5f9;' : '' }}">
        <div style="min-width:90px;font-size:.78rem;color:#6b7280;">
          {{ $task->due_date?->format('d/m/Y') ?? 'Senza data' }}
        </div>
        <div style="flex:1;">
          <a href="{{ route('admin.progettazione.projects.tasks.edit', [$project, $task]) }}" style="font-weight:700;text-decoration:none;color:#111827;">{{ $task->title }}</a>
          <div style="font-size:.72rem;color:#9ca3af;">{{ \App\Models\ProjectTask::typeOptions()[$task->type] ?? $task->type }} @if($task->responsible) · {{ $task->responsible->name }} @endif</div>
        </div>
        <span class="status status--task-{{ $task->manual_status }}">{{ \App\Models\ProjectTask::statusOptions()[$task->manual_status] ?? $task->manual_status }}</span>
      </div>
    @endforeach
  </div>
@endif
