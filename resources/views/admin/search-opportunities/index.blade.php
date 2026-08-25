@extends('layouts.admin')
@section('title','Opportunità di ricerca')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Opportunità di ricerca</h1>
  <a href="{{ route('admin.search-opportunities.import-form') }}" class="btn btn--primary">+ Importa dati Search Console</a>
</div>

@if(session('status'))
<div style="background:#f0fdfa;border:1px solid #99f6e4;border-radius:8px;
            padding:.85rem 1.1rem;margin-bottom:1.25rem;font-size:.82rem;color:#0f766e;">
  {{ session('status') }}
</div>
@endif

@if(is_null($selectedPeriod) && $opportunities->isEmpty())

  <div class="articles-empty-state">
    <p class="articles-empty-state__icon" aria-hidden="true">🔎</p>
    <p>Nessun dato Search Console importato finora.</p>
    <p class="articles-empty-state__hint">
      <a href="{{ route('admin.search-opportunities.import-form') }}">Importa un export CSV</a> per iniziare a vedere opportunità,
      oppure attendi che compaiano ricerche interne senza risultati (nessun import richiesto per queste).
    </p>
  </div>

@else

  <p style="color:var(--admin-muted);font-size:.85rem;margin-bottom:1rem;">
    @if($selectedPeriod)
      Periodo Search Console: <strong>{{ \Illuminate\Support\Carbon::parse($selectedPeriod['period_start'])->format('d/m/Y') }}
      – {{ \Illuminate\Support\Carbon::parse($selectedPeriod['period_end'])->format('d/m/Y') }}</strong>
      (import più recente tra {{ $periods->count() }} disponibili).
      Soglia minima di evidenza: {{ \App\Services\SearchConsole\SearchOpportunityScoringService::MIN_IMPRESSIONS }} impression nel periodo,
      {{ \App\Services\SearchConsole\SearchOpportunityScoringService::MIN_INTERNAL_ZERO_RESULT_HITS }} ricerche per le opportunità interne.
    @else
      Nessun dato Search Console importato: sotto solo le ricerche interne su Kairus senza risultati
      (soglia minima {{ \App\Services\SearchConsole\SearchOpportunityScoringService::MIN_INTERNAL_ZERO_RESULT_HITS }} occorrenze).
    @endif
  </p>

  @if($importHistory !== [])
    <details style="margin-bottom:1.25rem;">
      <summary style="cursor:pointer;font-size:.82rem;color:#374151;">Cronologia import ({{ count($importHistory) }})</summary>
      <div style="overflow-x:auto;margin-top:.6rem;">
        <table class="admin-table">
          <thead>
            <tr>
              <th scope="col">Periodo</th>
              <th scope="col">Importato il</th>
              <th scope="col">Righe</th>
            </tr>
          </thead>
          <tbody>
            @foreach($importHistory as $import)
              <tr>
                <td>{{ \Illuminate\Support\Carbon::parse($import['period_start'])->format('d/m/Y') }}
                  – {{ \Illuminate\Support\Carbon::parse($import['period_end'])->format('d/m/Y') }}</td>
                <td>{{ \Illuminate\Support\Carbon::parse($import['imported_at'])->timezone('Europe/Rome')->format('d/m/Y H:i') }}</td>
                <td>{{ number_format($import['row_count']) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </details>
  @endif

  <form method="GET" action="{{ route('admin.search-opportunities') }}" class="articles-toolbar" style="margin-bottom:1.25rem;">
    <div class="articles-toolbar__field">
      <label class="form-label" for="tipo">Tipo di opportunità</label>
      <select id="tipo" name="tipo" class="form-select" onchange="this.form.submit()">
        <option value="">Tutti i tipi</option>
        @foreach($typeOptions as $value => $label)
          <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
  </form>

  @if($opportunities->isEmpty())
    <div class="articles-empty-state">
      <p class="articles-empty-state__icon" aria-hidden="true">✅</p>
      <p>Nessuna opportunità trovata{{ $selectedType ? ' per questo tipo' : '' }} in questo periodo.</p>
    </div>
  @else
    <div style="overflow-x:auto;">
      <table class="admin-table">
        <thead>
          <tr>
            <th scope="col">Tipo</th>
            <th scope="col">Query</th>
            <th scope="col">Articolo</th>
            <th scope="col">Impression</th>
            <th scope="col">CTR</th>
            <th scope="col">Posizione</th>
            <th scope="col">Spiegazione</th>
            <th scope="col">Stato</th>
          </tr>
        </thead>
        <tbody>
          @foreach($opportunities as $opportunity)
            @php $currentStatus = $opportunityStatuses[$opportunity->key] ?? \App\Models\SearchOpportunityStatus::STATUS_NEW; @endphp
            <tr>
              <td><span class="badge badge--filter">{{ $typeOptions[$opportunity->type] ?? $opportunity->type }}</span></td>
              <td>{{ $opportunity->query }}</td>
              <td>
                @if($opportunity->article)
                  <a href="{{ route('admin.articles.edit', $opportunity->article) }}">{{ Str::limit($opportunity->article->title, 40) }}</a>
                @else
                  <span style="color:var(--admin-faint);">—</span>
                @endif
              </td>
              <td>{{ $opportunity->impressions }}</td>
              <td>{{ $opportunity->ctr !== null ? number_format($opportunity->ctr * 100, 1).'%' : '—' }}</td>
              <td>{{ $opportunity->position !== null ? number_format($opportunity->position, 1) : '—' }}</td>
              <td style="max-width:360px;font-size:.8rem;color:var(--admin-ink-soft);">{{ $opportunity->explanation }}</td>
              <td>
                <form method="POST" action="{{ route('admin.search-opportunities.update-status') }}">
                  @csrf
                  <input type="hidden" name="opportunity_key" value="{{ $opportunity->key }}">
                  <label class="sr-only" for="status-{{ $loop->index }}">Stato per «{{ $opportunity->query }}»</label>
                  <select id="status-{{ $loop->index }}" name="status" class="form-select" onchange="this.form.submit()">
                    @foreach($statusOptions as $value => $label)
                      <option value="{{ $value }}" @selected($currentStatus === $value)>{{ $label }}</option>
                    @endforeach
                  </select>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

@endif

@endsection
