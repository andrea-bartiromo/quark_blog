@extends('layouts.admin')
@section('title','Percorsi')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Percorsi</h1>
  <a class="btn btn--primary" href="{{ route('admin.content-clusters.create') }}">Nuovo percorso</a>
</div>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead><tr><th>Nome</th><th>Slug</th><th>Stato</th><th>Articoli</th><th>Pillar</th><th>Ordine</th><th>Azioni</th></tr></thead>
    <tbody>
      @forelse($clusters as $cluster)
        <tr>
          <td><strong>{{ $cluster->name }}</strong></td>
          <td><code>{{ $cluster->slug }}</code></td>
          <td><span class="status {{ $cluster->is_active ? 'status--published' : 'status--draft' }}">{{ $cluster->is_active ? 'Attivo' : 'Disattivo' }}</span></td>
          <td>{{ $cluster->articles_count }}</td>
          <td>{{ $cluster->pillarArticle?->title ?? '—' }}</td>
          <td>{{ $cluster->sort_order }}</td>
          <td><a class="action-btn" href="{{ route('admin.content-clusters.edit', $cluster) }}">Modifica</a></td>
        </tr>
      @empty
        <tr><td colspan="7" style="text-align:center;padding:2rem;color:#6b7280;">Nessun percorso disponibile.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

{{ $clusters->links() }}
@endsection
