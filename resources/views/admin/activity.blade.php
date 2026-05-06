@extends('layouts.admin')
@section('title', 'Log attività')

@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Log attività</h1>
  <span style="font-size:.78rem;color:#6b7280;">Storico azioni redazione</span>
</div>

<div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden;">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Quando</th>
        <th>Utente</th>
        <th>Azione</th>
        <th>Oggetto</th>
        <th>IP</th>
      </tr>
    </thead>
    <tbody>
      @forelse($logs as $log)
      <tr>
        <td style="font-size:.75rem;color:#6b7280;white-space:nowrap;">
          {{ $log->created_at->locale('it')->diffForHumans() }}
          <div style="font-size:.65rem;color:#9ca3af;">
            {{ $log->created_at->format('d/m/Y H:i') }}
          </div>
        </td>
        <td style="font-size:.82rem;font-weight:600;">
          {{ $log->user->name ?? 'Sistema' }}
        </td>
        <td>
          @php
            $colors = [
              'creato' => '#065f46', 'create' => '#065f46',
              'modific' => '#1e40af', 'updat' => '#1e40af',
              'elimin' => '#991b1b', 'delet' => '#991b1b',
            ];
            $color = '#6b7280';
            foreach($colors as $k => $v) {
              if(str_contains(strtolower($log->action), $k)) { $color = $v; break; }
            }
          @endphp
          <span style="font-size:.78rem;font-weight:600;color:{{ $color }};">
            {{ $log->action }}
          </span>
        </td>
        <td style="font-size:.78rem;color:#374151;">
          @if($log->subject_title)
            <span title="{{ $log->subject_title }}">
              {{ Str::limit($log->subject_title, 45) }}
            </span>
          @else
            <span style="color:#9ca3af;">—</span>
          @endif
        </td>
        <td style="font-size:.72rem;color:#9ca3af;font-family:monospace;">
          {{ $log->ip ?? '—' }}
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="5" style="text-align:center;color:#6b7280;padding:2rem;">
          Nessuna attività registrata ancora.
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>
</div>

@if($logs->hasPages())
<div style="margin-top:1rem;">
  {{ $logs->links('components.pagination') }}
</div>
@endif

@endsection