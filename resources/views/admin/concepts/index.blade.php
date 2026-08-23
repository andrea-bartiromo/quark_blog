@extends('layouts.admin')
@section('title','Concetti')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Concetti (Content Graph)</h1>
  <a class="btn btn--primary" href="{{ route('admin.concepts.create') }}">Nuovo concetto</a>
</div>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead><tr><th>Nome</th><th>Stato</th><th>Alias</th><th>Articoli collegati</th><th>Domande</th><th>Azioni</th></tr></thead>
    <tbody>
      @forelse($concepts as $concept)
        <tr>
          <td><strong>{{ $concept->name }}</strong><br><code>{{ $concept->slug }}</code></td>
          <td><span class="status {{ $concept->status === 'active' ? 'status--published' : 'status--draft' }}">{{ ucfirst($concept->status) }}</span></td>
          <td>{{ $concept->aliases_count }}</td>
          <td>{{ $concept->article_links_count }}</td>
          <td>{{ $concept->questions_count }}</td>
          <td><a class="action-btn" href="{{ route('admin.concepts.edit', $concept) }}">Modifica</a></td>
        </tr>
      @empty
        <tr><td colspan="6" style="text-align:center;padding:2rem;color:#6b7280;">Nessun concetto disponibile.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

{{ $concepts->links() }}
@endsection
