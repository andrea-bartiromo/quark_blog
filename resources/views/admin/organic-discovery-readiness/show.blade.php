@extends('layouts.admin')
@section('title','Ricerca organica — '.$article->title)
@section('content')

@php
  $stateColors = [
    \App\Services\OrganicDiscovery\OrganicDiscoveryReadinessService::STATE_BLOCKED => '#b91c1c',
    \App\Services\OrganicDiscovery\OrganicDiscoveryReadinessService::STATE_NEEDS_WORK => '#b45309',
    \App\Services\OrganicDiscovery\OrganicDiscoveryReadinessService::STATE_READY => '#15803d',
    \App\Services\OrganicDiscovery\OrganicDiscoveryReadinessService::STATE_MEASURED => '#1d4ed8',
  ];
  $stateColor = $stateColors[$report['state']] ?? '#6b7280';
@endphp

<div class="admin-topbar">
  <h1 class="admin-page-title">Ricerca organica</h1>
  <a href="{{ route('admin.organic-discovery-readiness') }}" class="btn btn--outline">← Torna all'elenco</a>
  <a href="{{ route('admin.articles.edit', $article) }}" class="btn btn--outline">Modifica articolo</a>
</div>

<div class="admin-card" style="margin-bottom:1.5rem;">
  <p style="margin:0 0 .3rem;font-size:.9rem;font-weight:600;">{{ $article->title }}</p>
  <p style="margin:0;">
    Stato: <strong style="color:{{ $stateColor }};">{{ $report['state_label'] }}</strong>
    · Qualità editoriale: {{ $report['quality_level'] }}
    · Dati Search Console: {{ $report['has_search_console_data'] ? 'disponibili nell\'ultimo periodo importato' : 'non disponibili' }}
  </p>
</div>

@if($report['findings_detail'] === [])
  <div class="articles-empty-state">
    <p class="articles-empty-state__icon" aria-hidden="true">🌱</p>
    <p>Nessuna segnalazione: tutti i controlli disponibili sono superati.</p>
  </div>
@else
  <div style="display:flex;flex-direction:column;gap:.75rem;margin-bottom:1.5rem;">
    @foreach($report['findings_detail'] as $finding)
      <div class="admin-card" style="margin:0;">
        <p style="margin:0 0 .25rem;font-weight:600;"><code>{{ $finding['code'] }}</code> — {{ $finding['label'] }}</p>
        <p style="margin:0 0 .25rem;color:var(--admin-muted);">Causa: {{ $finding['cause'] }}</p>
        <p style="margin:0;color:var(--admin-muted);">Azione suggerita: {{ $finding['action'] }}</p>
      </div>
    @endforeach
  </div>
@endif

@if($report['not_measured'] !== [])
  <p style="font-size:.78rem;color:var(--admin-muted);margin-bottom:1.5rem;">
    Non misurato in questa vista: {{ implode(', ', $report['not_measured']) }}.
  </p>
@endif

<div class="admin-card">
  <p style="margin:0 0 .5rem;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;">
    Verifica approfondita canonical/robots/sitemap
  </p>
  @if($eligibilityAudit['visibility'] !== 'public')
    <p style="margin:0;color:var(--admin-muted);">
      Verifica non disponibile (visibilità rilevata: <code>{{ $eligibilityAudit['visibility'] }}</code>).
    </p>
  @else
    <p style="margin:0;">
      HTTP: <code>{{ $eligibilityAudit['http_status'] ?? '—' }}</code>
      · Canonical dichiarata: <code>{{ $eligibilityAudit['canonical'] ?? '—' }}</code>
      · Robots: <code>{{ $eligibilityAudit['robots'] ?? '—' }}</code>
      · In sitemap: {{ $eligibilityAudit['in_sitemap'] ? 'Sì' : 'No' }}
    </p>
  @endif
  <p style="margin:.5rem 0 0;font-size:.7rem;color:var(--admin-muted);">
    Stessa verifica read-only di
    <a href="{{ route('admin.search-console-coverage') }}">Salute indicizzazione</a> (Cantiere 6),
    eseguita qui solo per questo articolo — nessuna richiesta a Google, nessuna azione automatica.
  </p>
</div>

@endsection
