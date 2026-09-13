@extends('layouts.admin')
@section('title', 'Pubblicazioni programmate')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Pubblicazioni programmate</h1>
</div>

<p style="color:var(--admin-muted);font-size:.85rem;margin-bottom:1rem;max-width:70ch;">
  Articoli programmati nei prossimi {{ $windowDays }} giorni (dal {{ \Illuminate\Support\Carbon::parse($report['from'])->timezone('Europe/Rome')->translatedFormat('d M Y, H:i') }}
  al {{ \Illuminate\Support\Carbon::parse($report['until'])->timezone('Europe/Rome')->translatedFormat('d M Y, H:i') }}, Europe/Rome) —
  stessa identica certificazione di sola lettura del comando <code style="font-size:.78rem;">php artisan editorial:scheduled-certification --days={{ $windowDays }}</code>,
  qui senza dover ricordare il comando o il parametro. Nessuna azione di correzione automatica.
</p>

<div class="admin-card" style="margin-bottom:1.25rem;">
  <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--admin-muted);">Programmati nella finestra</div>
  <div style="font-size:1.9rem;font-weight:700;margin:.35rem 0;">{{ number_format($report['count']) }}</div>
</div>

@if($report['count'] === 0)
  <p style="font-size:.85rem;color:var(--admin-muted);">Nessun articolo programmato nei prossimi {{ $windowDays }} giorni.</p>
@else
  <div style="overflow-x:auto;">
    <table class="admin-table">
      <thead>
        <tr>
          <th scope="col">Data (Europe/Rome)</th>
          <th scope="col">Titolo</th>
          <th scope="col">Qualità</th>
          <th scope="col">Percorsi</th>
          <th scope="col">Concept</th>
          <th scope="col">Fonti</th>
          <th scope="col">Collisione</th>
        </tr>
      </thead>
      <tbody>
        @foreach($report['items'] as $item)
          <tr>
            <td>{{ \Illuminate\Support\Carbon::parse($item['published_at'])->timezone('Europe/Rome')->translatedFormat('d M Y, H:i') }}</td>
            <td><a href="{{ route('admin.articles.edit', $item['id']) }}">{{ $item['title'] }}</a></td>
            <td>
              @if($item['content_health']['warning_count'] > 0)
                <span style="color:#b45309;font-weight:600;">{{ $item['content_health']['warning_count'] }} warning</span>
              @else
                <span style="color:#15803d;">Nessun warning</span>
              @endif
            </td>
            <td>{{ $item['percorsi'] === [] ? '—' : implode(', ', $item['percorsi']) }}</td>
            <td>{{ $item['concepts'] === [] ? '—' : implode(', ', $item['concepts']) }}</td>
            <td>{{ $item['has_sources'] ? 'sì' : 'no' }}</td>
            <td>
              @if($item['collision_count'] > 1)
                <span style="color:#b91c1c;font-weight:600;">{{ $item['collision_count'] }} stesso orario</span>
              @else
                —
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif

@endsection
