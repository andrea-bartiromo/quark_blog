@extends('layouts.admin')
@section('title','Newsletter')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Newsletter</h1>
  <div style="display:flex;gap:.75rem;align-items:center;">
    <span style="font-size:.78rem;color:#6b7280;">
      {{ $total }} iscritti · {{ $confirmed }} confermati
    </span>
    <a href="{{ route('admin.newsletter.export') }}"
       class="btn btn--secondary" style="font-size:.78rem;">
      ⬇ Esporta CSV
    </a>
  </div>
</div>

{{-- Info GDPR --}}
<div style="background:#f0fdfa;border:1px solid #99f6e4;border-radius:8px;
            padding:.85rem 1.1rem;margin-bottom:1.25rem;font-size:.82rem;color:#0f766e;">
  <strong>📋 GDPR:</strong> Ogni email inviata agli iscritti deve contenere un link di disiscrizione.
  Gli iscritti possono richiedere la cancellazione anche via
  <a href="{{ route('contatti') }}" target="_blank" style="color:#0d9488;">form di contatto</a>.
  Tu puoi eliminarli manualmente da questo pannello.
</div>

@if(session('success'))
<div style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:8px;
            padding:.85rem 1.1rem;margin-bottom:1rem;color:#065f46;font-size:.875rem;">
  ✅ {{ session('success') }}
</div>
@endif

<div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.08);overflow:hidden;">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Email</th>
        <th>Stato</th>
        <th>Data iscrizione</th>
        <th>Azioni</th>
      </tr>
    </thead>
    <tbody>
      @forelse($subscribers as $sub)
      <tr>
        <td style="font-weight:500;">{{ $sub->email }}</td>
        <td>
          <span class="status status--{{ $sub->confirmed ? 'published' : 'draft' }}">
            {{ $sub->confirmed ? '✓ Confermato' : '⏳ In attesa' }}
          </span>
        </td>
        <td style="font-size:.82rem;color:#6b7280;">
          {{ $sub->created_at->format('d/m/Y H:i') }}
        </td>
        <td>
          <form method="POST" action="{{ route('admin.newsletter.destroy', $sub) }}"
                onsubmit="return confirm('Eliminare {{ $sub->email }} dalla newsletter?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn--danger btn--sm">
              Elimina
            </button>
          </form>
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="4" style="text-align:center;color:#6b7280;padding:2rem;">
          Nessun iscritto ancora.
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

@if($subscribers->hasPages())
<div style="margin-top:1rem;">
  {{ $subscribers->links('components.pagination') }}
</div>
@endif

@endsection