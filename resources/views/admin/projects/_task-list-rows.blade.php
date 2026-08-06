@foreach($tasks as $task)
<tr>
  <td>
    <a href="{{ route('admin.progettazione.projects.tasks.edit', [$task->project, $task]) }}" style="font-weight:700;">{{ $task->title }}</a>
    @if($showProject ?? false)
      <div style="font-size:.72rem;color:#6b7280;">{{ $task->project->title }}</div>
    @endif
  </td>
  <td>{{ \App\Models\ProjectTask::typeOptions()[$task->type] ?? $task->type }}</td>
  <td>
    <span class="status status--task-{{ $task->manual_status }}">{{ \App\Models\ProjectTask::statusOptions()[$task->manual_status] ?? $task->manual_status }}</span>
    @if($task->derived_status)
      <div style="margin-top:.25rem;"><span class="status status--derived-{{ $task->derived_status }}">{{ \App\Models\ProjectTask::derivedStatusOptions()[$task->derived_status] ?? $task->derived_status }}</span></div>
    @endif
  </td>
  <td>{{ $task->responsible?->name ?? '—' }}</td>
  <td>{{ $task->due_date?->format('d/m/Y') ?? '—' }}</td>
  <td>{{ $task->article?->title ?? '—' }}</td>
  <td><a href="{{ route('admin.progettazione.projects.tasks.edit', [$task->project, $task]) }}" class="action-btn">Modifica</a></td>
</tr>
@endforeach
