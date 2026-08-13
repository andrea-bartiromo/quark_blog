@extends('layouts.admin')
@section('title','Percorsi')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Percorsi</h1>
  <a class="btn btn--primary" href="{{ route('admin.content-clusters.create') }}">Nuovo percorso</a>
</div>

<section aria-labelledby="cluster-health-summary" class="admin-card" style="margin-bottom:1rem;">
  <h2 id="cluster-health-summary" style="font-size:1rem;">Copertura editoriale</h2>
  <p>
    Senza percorso: <strong>{{ $orphans['without_cluster'] }}</strong> ·
    Senza primary: <strong>{{ $orphans['without_primary'] }}</strong> ·
    Solo percorsi inattivi: <strong>{{ $orphans['inactive_only'] }}</strong> ·
    Pubblicati non coperti: <strong>{{ $orphans['published_uncovered'] }}</strong> ·
    Programmati non coperti: <strong>{{ $orphans['scheduled_uncovered'] }}</strong>
  </p>
  <p><small>Questi sono segnali editoriali, non errori bloccanti.</small></p>
</section>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead><tr><th>Nome</th><th>Health</th><th>Stato</th><th>Pubblicati / totali</th><th>Pillar</th><th>Primary</th><th>Warning</th><th>Azioni</th></tr></thead>
    <tbody>
      @forelse($clusters as $cluster)
        @php($health = $cluster->health)
        <tr>
          <td><strong>{{ $cluster->name }}</strong><br><code>{{ $cluster->slug }}</code></td>
          <td><span class="status {{ $health['status'] === 'HEALTHY' ? 'status--published' : 'status--draft' }}">{{ $health['status'] }}</span></td>
          <td>{{ $cluster->is_active ? 'Attivo' : 'Disattivo' }}</td>
          <td>{{ $health['article_count_published'] }} / {{ $health['article_count_total'] }}</td>
          <td>{{ $cluster->pillarArticle?->title ?? '—' }}{{ $health['pillar_present'] && ! $health['pillar_public'] ? ' (non pubblico)' : '' }}</td>
          <td>{{ $health['primary_coverage'] }}%</td>
          <td>{{ $health['findings'] ? implode(', ', $health['findings']) : 'Nessuno' }}</td>
          <td><a class="action-btn" href="{{ route('admin.content-clusters.edit', $cluster) }}">Modifica</a></td>
        </tr>
      @empty
        <tr><td colspan="8" style="text-align:center;padding:2rem;color:#6b7280;">Nessun percorso disponibile.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

{{ $clusters->links() }}
@endsection
