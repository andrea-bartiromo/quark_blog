@extends('layouts.admin')
@section('title','Concetti')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Concetti (Content Graph)</h1>
  <a class="btn btn--primary" href="{{ route('admin.concepts.create') }}">Nuovo concetto</a>
</div>

@if(! empty($duplicates))
  <section style="background:#fffbeb;border:1px solid #fde68a;border-radius:var(--radius);padding:1rem 1.25rem;margin-bottom:1.25rem;">
    <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#92400e;margin-bottom:.5rem;">
      Possibili concetti duplicati ({{ count($duplicates) }}) — informativo, nessuna fusione automatica
    </div>
    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.6rem;">
      @foreach($duplicates as $group)
        <li style="font-size:.85rem;color:#78350f;">
          <span style="color:#92400e;">"{{ $group['normalized_text'] }}"</span> —
          @foreach($group['concepts'] as $i => $c)
            <a href="{{ route('admin.concepts.edit', $c['id']) }}">{{ $c['name'] }}</a>
            <span style="color:#b45309;font-size:.75rem;">({{ $c['matched_via'] }}, {{ $c['status'] }})</span>{{ $i < count($group['concepts']) - 1 ? ', ' : '' }}
          @endforeach
        </li>
      @endforeach
    </ul>
  </section>
@endif

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
