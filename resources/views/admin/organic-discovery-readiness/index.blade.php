@extends('layouts.admin')
@section('title','Ricerca organica')
@section('content')

@php
  $stateColors = [
    \App\Services\OrganicDiscovery\OrganicDiscoveryReadinessService::STATE_BLOCKED => '#b91c1c',
    \App\Services\OrganicDiscovery\OrganicDiscoveryReadinessService::STATE_NEEDS_WORK => '#b45309',
    \App\Services\OrganicDiscovery\OrganicDiscoveryReadinessService::STATE_READY => '#15803d',
    \App\Services\OrganicDiscovery\OrganicDiscoveryReadinessService::STATE_MEASURED => '#1d4ed8',
  ];
@endphp

<div class="admin-topbar">
  <h1 class="admin-page-title">Ricerca organica</h1>
</div>

<p style="color:var(--admin-muted);font-size:.85rem;margin-bottom:1rem;max-width:78ch;">
  Stato spiegabile per ciascun articolo pubblico rispetto alla scopribilità organica:
  qualità editoriale, SEO/canonical, profilo di ricerca (Cantiere 2), scoperta interna e —
  quando disponibili — dati Search Console osservati (Cantiere 1). Mai un punteggio opaco:
  ogni segnalazione mostra causa e azione suggerita. L'audit viene eseguito soltanto
  aprendo questa pagina.
</p>

<dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem;">
  @foreach([
    \App\Services\OrganicDiscovery\OrganicDiscoveryReadinessService::STATE_BLOCKED => 'Bloccato',
    \App\Services\OrganicDiscovery\OrganicDiscoveryReadinessService::STATE_NEEDS_WORK => 'Da migliorare',
    \App\Services\OrganicDiscovery\OrganicDiscoveryReadinessService::STATE_READY => 'Pronto',
    \App\Services\OrganicDiscovery\OrganicDiscoveryReadinessService::STATE_MEASURED => 'Misurato',
  ] as $state => $label)
    <div class="admin-card" style="margin:0;">
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--admin-muted);">{{ $label }}</dt>
      <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.4rem;color:{{ $stateColors[$state] }};">{{ number_format($counts[$state] ?? 0) }}</dd>
    </div>
  @endforeach
</dl>

@if($rows->isEmpty())
  <div class="articles-empty-state">
    <p class="articles-empty-state__icon" aria-hidden="true">🌱</p>
    <p>Nessun articolo pubblico da analizzare.</p>
  </div>
@else
  <div style="overflow-x:auto;">
    <table class="admin-table">
      <thead>
        <tr>
          <th scope="col">Articolo</th>
          <th scope="col">Stato</th>
          <th scope="col">Segnalazioni</th>
          <th scope="col">Dati Search Console</th>
        </tr>
      </thead>
      <tbody>
        @foreach($rows as $row)
          <tr>
            <td><a href="{{ route('admin.organic-discovery-readiness.show', $row['article_id']) }}">{{ Str::limit($row['title'], 55) }}</a></td>
            <td><strong style="color:{{ $stateColors[$row['state']] ?? '#6b7280' }};">{{ $row['state_label'] }}</strong></td>
            <td>{{ $row['findings'] === [] ? '—' : implode(', ', $row['findings']) }}</td>
            <td>{{ $row['has_search_console_data'] ? 'Sì' : 'No' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @if($truncated)
    <p style="font-size:.78rem;color:var(--admin-muted);margin-top:.75rem;">
      Mostrate le prime {{ $rows->count() }} righe più deboli su {{ number_format($total) }} articoli analizzati.
    </p>
  @endif
@endif

@endsection
