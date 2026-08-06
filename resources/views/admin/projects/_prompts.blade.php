<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
  <h3 style="margin:0;">Prompt</h3>
  <a href="{{ route('admin.progettazione.projects.prompts.create', $project) }}" class="btn btn--primary" style="font-size:.82rem;">+ Nuovo prompt</a>
</div>

@if($prompts->isEmpty())
  <div class="admin-card" style="text-align:center;padding:2.5rem;color:#9ca3af;">
    Nessun prompt ancora.
  </div>
@else
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Titolo</th>
          <th>Agente</th>
          <th>Stato</th>
          <th>Utilizzato il</th>
          <th>Articolo</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        @foreach($prompts as $prompt)
        <tr>
          <td style="font-weight:700;">{{ $prompt->title }}</td>
          <td>{{ $prompt->agent ?? '—' }}</td>
          <td><span class="status status--prompt-{{ $prompt->status }}">{{ \App\Models\ProjectPrompt::statusOptions()[$prompt->status] ?? $prompt->status }}</span></td>
          <td>{{ $prompt->used_at?->format('d/m/Y') ?? '—' }}</td>
          <td>{{ $prompt->article?->title ?? '—' }}</td>
          <td style="display:flex;gap:.4rem;">
            <a href="{{ route('admin.progettazione.projects.prompts.edit', [$project, $prompt]) }}" class="action-btn">Apri</a>
            <form method="POST" action="{{ route('admin.progettazione.projects.prompts.duplicate', [$project, $prompt]) }}">
              @csrf
              <button type="submit" class="action-btn">Duplica</button>
            </form>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{ $prompts->links() }}
@endif
