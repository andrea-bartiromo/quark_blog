<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
  <h3 style="margin:0;">Decisioni</h3>
  <a href="{{ route('admin.progettazione.projects.decisions.create', $project) }}" class="btn btn--primary" style="font-size:.82rem;">+ Nuova decisione</a>
</div>

@if($decisions->isEmpty())
  <div class="admin-card project-empty-state">
    <div class="project-empty-state__icon">🗳️</div>
    <p class="project-empty-state__text">Nessuna decisione ancora. <strong>Registra la prima</strong> per tenere traccia delle scelte fatte su questo progetto.</p>
    <a href="{{ route('admin.progettazione.projects.decisions.create', $project) }}" class="btn btn--primary">+ Nuova decisione</a>
  </div>
@else
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Titolo</th>
          <th>Stato</th>
          <th>Decisa il</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        @foreach($decisions as $decision)
        <tr>
          <td style="font-weight:700;">{{ $decision->title }}</td>
          <td><span class="status status--decision-{{ $decision->status }}">{{ \App\Models\ProjectDecision::statusOptions()[$decision->status] ?? $decision->status }}</span></td>
          <td>{{ $decision->decided_at?->format('d/m/Y') ?? '—' }}</td>
          <td><a href="{{ route('admin.progettazione.projects.decisions.edit', [$project, $decision]) }}" class="action-btn">Apri</a></td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{ $decisions->links() }}
@endif
