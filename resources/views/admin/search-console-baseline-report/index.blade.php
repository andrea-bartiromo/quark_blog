@extends('layouts.admin')
@section('title','Baseline Search Console')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Baseline Search Console</h1>
  <a href="{{ route('admin.search-opportunities') }}" class="btn btn--outline">Opportunità di ricerca →</a>
</div>

@if($periods->isEmpty())

  <div class="articles-empty-state">
    <p class="articles-empty-state__icon" aria-hidden="true">🔎</p>
    <p>Nessun dato Search Console importato finora.</p>
    <p class="articles-empty-state__hint">
      <a href="{{ route('admin.search-opportunities.import-form') }}">Importa un export CSV</a> per iniziare a vedere questo report.
    </p>
  </div>

@else

  <form method="GET" action="{{ route('admin.search-console-baseline-report') }}" class="articles-toolbar" style="margin-bottom:1.25rem;">
    <div class="articles-toolbar__field">
      <label class="form-label" for="periodo">Periodo</label>
      <select id="periodo" name="periodo" class="form-select" onchange="this.form.submit()">
        @foreach($periods as $index => $period)
          <option value="{{ $index }}" @selected($selectedIndex === $index)>
            {{ \Illuminate\Support\Carbon::parse($period['period_start'])->format('d/m/Y') }}
            – {{ \Illuminate\Support\Carbon::parse($period['period_end'])->format('d/m/Y') }}
          </option>
        @endforeach
      </select>
    </div>
  </form>

  @if(! $report['has_data'])
    <div class="articles-empty-state">
      <p class="articles-empty-state__icon" aria-hidden="true">🕳️</p>
      <p>Nessun dato per questo periodo.</p>
    </div>
  @else

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem;">
      <div class="admin-card" style="padding:1rem;">
        <p style="color:var(--admin-muted);font-size:.78rem;margin:0 0 .25rem;">Clic</p>
        <p style="font-size:1.4rem;font-weight:600;margin:0;">{{ number_format($report['totals']['clicks']) }}</p>
      </div>
      <div class="admin-card" style="padding:1rem;">
        <p style="color:var(--admin-muted);font-size:.78rem;margin:0 0 .25rem;">Impression</p>
        <p style="font-size:1.4rem;font-weight:600;margin:0;">{{ number_format($report['totals']['impressions']) }}</p>
      </div>
      <div class="admin-card" style="padding:1rem;">
        <p style="color:var(--admin-muted);font-size:.78rem;margin:0 0 .25rem;">CTR medio</p>
        <p style="font-size:1.4rem;font-weight:600;margin:0;">{{ $report['totals']['ctr'] !== null ? number_format($report['totals']['ctr'] * 100, 1).'%' : '—' }}</p>
      </div>
      <div class="admin-card" style="padding:1rem;">
        <p style="color:var(--admin-muted);font-size:.78rem;margin:0 0 .25rem;">Posizione media</p>
        <p style="font-size:1.4rem;font-weight:600;margin:0;">{{ $report['totals']['position'] !== null ? number_format($report['totals']['position'], 1) : '—' }}</p>
      </div>
    </div>

    <p style="color:var(--admin-muted);font-size:.8rem;margin-bottom:1.5rem;">
      ℹ️ Dispositivo e Paese non disponibili: gli export CSV supportati (Query, Query + Pagina) non includono queste dimensioni.
    </p>

    <h2 style="font-size:1rem;margin-bottom:.6rem;">Top landing page organiche</h2>
    @if(empty($report['top_landing_pages']))
      <p style="color:var(--admin-faint);font-size:.85rem;margin-bottom:1.5rem;">Nessuna pagina osservata in questo periodo.</p>
    @else
      <div style="overflow-x:auto;margin-bottom:1.5rem;">
        <table class="admin-table">
          <thead>
            <tr>
              <th scope="col">Pagina</th>
              <th scope="col">Clic</th>
              <th scope="col">Impression</th>
              <th scope="col">CTR</th>
              <th scope="col">Posizione</th>
            </tr>
          </thead>
          <tbody>
            @foreach($report['top_landing_pages'] as $page)
              <tr>
                <td>
                  @if(Str::startsWith($page['page_url'], ['http://', 'https://']))
                    <a href="{{ $page['page_url'] }}" target="_blank" rel="noopener noreferrer">{{ Str::limit(parse_url($page['page_url'], PHP_URL_PATH) ?: $page['page_url'], 50) }}</a>
                  @else
                    {{ Str::limit($page['page_url'], 50) }}
                  @endif
                </td>
                <td>{{ number_format($page['clicks']) }}</td>
                <td>{{ number_format($page['impressions']) }}</td>
                <td>{{ $page['ctr'] !== null ? number_format($page['ctr'] * 100, 1).'%' : '—' }}</td>
                <td>{{ $page['position'] !== null ? number_format($page['position'], 1) : '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    <h2 style="font-size:1rem;margin-bottom:.6rem;">Query non-brand</h2>
    @if(empty($report['non_brand_queries']))
      <p style="color:var(--admin-faint);font-size:.85rem;margin-bottom:1.5rem;">Nessuna query non-brand in questo periodo.</p>
    @else
      <div style="overflow-x:auto;margin-bottom:1.5rem;">
        <table class="admin-table">
          <thead>
            <tr>
              <th scope="col">Query</th>
              <th scope="col">Clic</th>
              <th scope="col">Impression</th>
              <th scope="col">CTR</th>
              <th scope="col">Posizione</th>
            </tr>
          </thead>
          <tbody>
            @foreach($report['non_brand_queries'] as $q)
              <tr>
                <td>{{ $q['query'] }}</td>
                <td>{{ number_format($q['clicks']) }}</td>
                <td>{{ number_format($q['impressions']) }}</td>
                <td>{{ $q['ctr'] !== null ? number_format($q['ctr'] * 100, 1).'%' : '—' }}</td>
                <td>{{ $q['position'] !== null ? number_format($q['position'], 1) : '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;">
      <a href="{{ route('admin.search-opportunities', ['tipo' => \App\Services\SearchConsole\SearchOpportunityScoringService::TYPE_HIGH_IMPRESSION_LOW_CTR]) }}" class="admin-card" style="padding:1rem;display:block;">
        <p style="color:var(--admin-muted);font-size:.78rem;margin:0 0 .25rem;">Molte impression, CTR basso</p>
        <p style="font-size:1.4rem;font-weight:600;margin:0;">{{ count($report['high_impression_low_ctr']) }}</p>
      </a>
      <a href="{{ route('admin.search-opportunities', ['tipo' => \App\Services\SearchConsole\SearchOpportunityScoringService::TYPE_NEAR_PAGE_ONE]) }}" class="admin-card" style="padding:1rem;display:block;">
        <p style="color:var(--admin-muted);font-size:.78rem;margin:0 0 .25rem;">Posizione 11–20</p>
        <p style="font-size:1.4rem;font-weight:600;margin:0;">{{ count($report['near_page_one']) }}</p>
      </a>
      <a href="{{ route('admin.search-opportunities', ['tipo' => \App\Services\SearchConsole\SearchOpportunityScoringService::TYPE_NO_STRONG_LANDING_PAGE]) }}" class="admin-card" style="padding:1rem;display:block;">
        <p style="color:var(--admin-muted);font-size:.78rem;margin:0 0 .25rem;">Nessuna landing page forte</p>
        <p style="font-size:1.4rem;font-weight:600;margin:0;">{{ count($report['no_strong_landing_page']) }}</p>
      </a>
    </div>

  @endif

@endif

@endsection
