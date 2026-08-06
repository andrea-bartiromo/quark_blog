<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
  <h3 style="margin:0;">Documenti</h3>
  <a href="{{ route('admin.progettazione.projects.documents.create', $project) }}" class="btn btn--primary" style="font-size:.82rem;">+ Nuovo documento</a>
</div>

@if($documents->isEmpty())
  <div class="admin-card" style="text-align:center;padding:2.5rem;color:#9ca3af;">
    Nessun documento ancora.
  </div>
@else
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Titolo</th>
          <th>Tipo</th>
          <th>Stato</th>
          <th>Versione</th>
          <th>Aggiornato</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        @foreach($documents as $document)
        <tr>
          <td style="font-weight:700;">{{ $document->title }}</td>
          <td>{{ \App\Models\ProjectDocument::typeOptions()[$document->type] ?? $document->type }}</td>
          <td><span class="status status--doc-{{ $document->status }}">{{ \App\Models\ProjectDocument::statusOptions()[$document->status] ?? $document->status }}</span></td>
          <td>v{{ $document->version }}</td>
          <td>{{ $document->updated_at->format('d/m/Y H:i') }}</td>
          <td><a href="{{ route('admin.progettazione.projects.documents.edit', [$project, $document]) }}" class="action-btn">Apri</a></td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{ $documents->links() }}
@endif
