@extends('layouts.redazione')
@section('title', 'I miei articoli')

@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">I miei articoli</h1>
  <a href="{{ route('redazione.articles.create') }}" class="btn btn--primary">✍️ Nuovo articolo</a>
</div>

<div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden;">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Titolo</th>
        <th>Categoria</th>
        <th>Stato</th>
        <th>Views</th>
        <th>Ultima modifica</th>
        <th>Azioni</th>
      </tr>
    </thead>
    <tbody>
      @forelse($articles as $article)
      <tr>
        <td>
          <div style="font-weight:600;font-size:.85rem;color:#111827;">
            {{ Str::limit($article->title, 55) }}
          </div>
          @if($article->status === 'draft' && $article->verification_notes)
          <div style="font-size:.72rem;color:#854d0e;background:#fef9c3;
                      border-radius:4px;padding:.2rem .5rem;margin-top:.3rem;display:inline-block;">
            📋 Nota editor: {{ Str::limit($article->verification_notes, 60) }}
          </div>
          @endif
        </td>
        <td>
          <span class="badge badge--{{ $article->category }}">
            {{ config('laboratorio.categories.'.$article->category) }}
          </span>
        </td>
        <td>
          <span class="status status--{{ $article->status }}">
            @if($article->status === 'published') ✅ Pubblicato
            @elseif($article->status === 'review') ⏳ In revisione
            @else 📄 Bozza @endif
          </span>
        </td>
        <td style="font-size:.82rem;color:#6b7280;">
          {{ number_format($article->views, 0, ',', '.') }}
        </td>
        <td style="font-size:.78rem;color:#6b7280;">
          {{ $article->updated_at->diffForHumans() }}
        </td>
        <td>
          <div class="actions">
            @if($article->status === 'published')
              <a href="{{ route('articolo', $article->slug) }}" target="_blank"
                 class="btn btn--secondary btn--sm">Leggi</a>
            @else
              <a href="{{ route('redazione.articles.edit', $article) }}"
                 class="btn btn--secondary btn--sm">Modifica</a>
              <form method="POST" action="{{ route('redazione.articles.destroy', $article) }}"
                    onsubmit="return confirm('Eliminare questo articolo?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn--danger btn--sm">Elimina</button>
              </form>
            @endif
          </div>
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="6" style="text-align:center;padding:2.5rem;color:#6b7280;">
          <p style="font-size:1.5rem;margin-bottom:.5rem;">✍️</p>
          <p>Non hai ancora scritto nessun articolo.</p>
          <a href="{{ route('redazione.articles.create') }}" class="btn btn--primary" style="margin-top:.75rem;">
            Scrivi il primo
          </a>
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

@if($articles->hasPages())
<div style="margin-top:1rem;">{{ $articles->links('components.pagination') }}</div>
@endif

@endsection