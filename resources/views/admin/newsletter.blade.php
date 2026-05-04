@extends('layouts.admin')
@section('title','Newsletter')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Newsletter</h1>
  <div style="display:flex;gap:.75rem;align-items:center;">
    <span style="font-family:var(--font-ui);font-size:.78rem;color:var(--color-ink-muted);">
      {{ $total }} iscritti · {{ $confirmed }} confermati
    </span>
    <a href="{{ route('admin.newsletter.export') }}"
       class="btn btn--secondary" style="font-size:.78rem;">
      ⬇ Esporta CSV
    </a>
  </div>
  <span style="font-family:var(--font-ui);font-size:.85rem;color:var(--color-ink-muted);">
    {{ $subscribers->where('confirmed',true)->count() }} iscritti confermati
  </span>
</div>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Email</th>
        <th>Confermato</th>
        <th>Data iscrizione</th>
      </tr>
    </thead>
    <tbody>
      @forelse($subscribers as $sub)
      <tr>
        <td>{{ $sub->email }}</td>
        <td>
          <span class="status status--{{ $sub->confirmed ? 'published' : 'draft' }}">
            {{ $sub->confirmed ? 'Sì' : 'In attesa' }}
          </span>
        </td>
        <td>{{ $sub->created_at->format('d/m/Y H:i') }}</td>
      </tr>
      @empty
      <tr><td colspan="3" style="text-align:center;color:var(--color-ink-muted);padding:2rem;">Nessun iscritto.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

@endsection
