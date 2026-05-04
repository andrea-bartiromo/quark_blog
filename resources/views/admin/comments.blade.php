@extends('layouts.admin')
@section('title','Commenti')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Commenti</h1>
</div>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Autore</th>
        <th>Articolo</th>
        <th>Commento</th>
        <th>Stato</th>
        <th>Data</th>
        <th>Azioni</th>
      </tr>
    </thead>
    <tbody>
      @forelse($comments as $comment)
      <tr>
        <td>
          <strong>{{ $comment->name }}</strong><br>
          <small style="color:var(--color-ink-muted);">{{ $comment->email }}</small>
        </td>
        <td>
          <a href="{{ route('articolo', $comment->article->slug) }}" target="_blank"
             style="font-size:.85rem;">
            {{ Str::limit($comment->article->title, 40) }}
          </a>
        </td>
        <td style="max-width:280px;font-size:.85rem;">{{ Str::limit($comment->body,90) }}</td>
        <td>
          <span class="status status--{{ $comment->status === 'approved' ? 'published' : 'draft' }}">
            {{ $comment->status }}
          </span>
        </td>
        <td>{{ $comment->created_at->format('d/m/Y H:i') }}</td>
        <td>
          <div class="actions">
            @if($comment->status !== 'approved')
            <form method="POST" action="{{ route('admin.comments.approve', $comment) }}" style="display:inline;">
              @csrf @method('PATCH')
              <button type="submit" class="action-btn">Approva</button>
            </form>
            @endif
            <form method="POST" action="{{ route('admin.comments.destroy', $comment) }}"
                  onsubmit="return confirm('Eliminare questo commento?')" style="display:inline;">
              @csrf @method('DELETE')
              <button type="submit" class="action-btn action-btn--danger">Elimina</button>
            </form>
          </div>
        </td>
      </tr>
      @empty
      <tr><td colspan="6" style="text-align:center;color:var(--color-ink-muted);padding:2rem;">Nessun commento.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

@endsection
