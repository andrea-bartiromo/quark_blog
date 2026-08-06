<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
  <h3 style="margin:0;">Attività</h3>
  <a href="{{ route('admin.progettazione.projects.tasks.create', $project) }}" class="btn btn--primary" style="font-size:.82rem;">+ Nuova attività</a>
</div>

@php($tasks = $project->tasks()->with(['responsible', 'article', 'project'])->orderBy('due_date')->get())

@if($tasks->isEmpty())
  <div class="admin-card" style="text-align:center;padding:2.5rem;color:#9ca3af;">
    Nessuna attività ancora.
  </div>
@else
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Titolo</th>
          <th>Tipo</th>
          <th>Stato</th>
          <th>Responsabile</th>
          <th>Data prevista</th>
          <th>Articolo</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        @include('admin.projects._task-list-rows', ['tasks' => $tasks, 'showProject' => false])
      </tbody>
    </table>
  </div>
@endif
