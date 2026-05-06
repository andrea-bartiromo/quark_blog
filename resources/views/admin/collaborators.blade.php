@extends('layouts.admin')
@section('title', 'Collaboratori')

@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Collaboratori</h1>
  <a href="{{ route('admin.collaborators.create') }}" class="btn btn--primary">
    + Aggiungi collaboratore
  </a>
</div>

@if($collaborators->isEmpty())
<div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);
            padding:3rem;text-align:center;color:#6b7280;">
  <p style="font-size:1.5rem;margin-bottom:.5rem;">👥</p>
  <p>Nessun collaboratore ancora.</p>
  <a href="{{ route('admin.collaborators.create') }}" class="btn btn--primary" style="margin-top:.75rem;">
    Aggiungi il primo
  </a>
</div>
@else
<div style="display:grid;gap:1rem;">
  @foreach($collaborators as $user)
  <div style="background:#fff;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.08);
              padding:1.25rem;display:flex;align-items:center;gap:1.25rem;">

    {{-- Avatar --}}
    <div style="width:52px;height:52px;border-radius:50%;background:#0d9488;
                display:flex;align-items:center;justify-content:center;
                font-size:1.1rem;font-weight:700;color:#fff;flex-shrink:0;">
      @if($user->photo)
        <img src="{{ asset('storage/'.$user->photo) }}" alt=""
             style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
      @else
        {{ mb_substr($user->name, 0, 2) }}
      @endif
    </div>

    {{-- Info --}}
    <div style="flex:1;min-width:0;">
      <div style="font-size:.95rem;font-weight:700;color:#111827;">{{ $user->name }}</div>
      <div style="font-size:.78rem;color:#6b7280;">{{ $user->email }}</div>
      @if($user->bio)
      <div style="font-size:.75rem;color:#9ca3af;margin-top:.2rem;">
        {{ Str::limit($user->bio, 80) }}
      </div>
      @endif
    </div>

    {{-- Statistiche --}}
    <div style="display:flex;gap:1.5rem;flex-shrink:0;">
      <div style="text-align:center;">
        <div style="font-size:1.2rem;font-weight:900;color:#111827;">{{ $user->articles_count }}</div>
        <div style="font-size:.62rem;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;">Totali</div>
      </div>
      <div style="text-align:center;">
        <div style="font-size:1.2rem;font-weight:900;color:#065f46;">{{ $user->published_count }}</div>
        <div style="font-size:.62rem;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;">Pubblicati</div>
      </div>
      <div style="text-align:center;">
        <div style="font-size:1.2rem;font-weight:900;color:#854d0e;">{{ $user->review_count }}</div>
        <div style="font-size:.62rem;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;">In revisione</div>
      </div>
    </div>

    {{-- Azioni --}}
    <div style="display:flex;gap:.5rem;flex-shrink:0;">
      <a href="{{ route('admin.collaborators.edit', $user) }}"
         class="btn btn--secondary btn--sm">Modifica</a>

      <form method="POST" action="{{ route('admin.collaborators.reset-password', $user) }}"
            onsubmit="return confirm('Reimpostare la password di {{ $user->name }}? Riceverà una email con la nuova password.')">
        @csrf @method('PATCH')
        <button type="submit" class="btn btn--secondary btn--sm" title="Reimposta password">
          🔑 Reset pw
        </button>
      </form>

      <form method="POST" action="{{ route('admin.collaborators.destroy', $user) }}"
            onsubmit="return confirm('Rimuovere {{ $user->name }}? I suoi articoli verranno riassegnati a te.')">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn--danger btn--sm">Rimuovi</button>
      </form>
    </div>

  </div>
  @endforeach
</div>
@endif

@endsection