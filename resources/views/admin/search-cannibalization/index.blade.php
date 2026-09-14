@extends('layouts.admin')
@section('title','Cannibalizzazione di ricerca')
@section('content')
<div class="admin-topbar"><h1 class="admin-page-title">Cannibalizzazione di ricerca</h1></div>
<p style="color:var(--admin-muted);font-size:.85rem;max-width:82ch">
  Articoli pubblici diversi che ricevono impression Search Console reali per la stessa query nello stesso periodo — un segnale più forte del semplice confronto tra query dichiarate (già visibile in "Decisioni SEO"). Sola lettura: nessuna modifica automatica di contenuto, collegamenti, sitemap o pubblicazione. La risoluzione resta una decisione umana, registrata qui sotto tramite la stessa infrastruttura del Cantiere 4 ("Sovrapposizione con articolo esistente").
</p>
<form method="GET" class="articles-toolbar" style="margin:1rem 0">
  <div class="articles-toolbar__field">
    <label class="form-label" for="period">Periodo</label>
    <select id="period" name="period" class="form-select" onchange="this.form.submit()">
      @foreach($periods as $period)
        <option value="{{ $period['period_start'] }}|{{ $period['period_end'] }}" @selected($selected && $selected['period_start']===$period['period_start'] && $selected['period_end']===$period['period_end'])>
          {{ \Illuminate\Support\Carbon::parse($period['period_start'])->format('d/m/Y') }} – {{ \Illuminate\Support\Carbon::parse($period['period_end'])->format('d/m/Y') }}
        </option>
      @endforeach
    </select>
  </div>
</form>

@if(! $selected)
  <div class="articles-empty-state"><p>Nessun import Search Console disponibile.</p><p><a href="{{ route('admin.search-opportunities.import-form') }}">Importa un CSV</a> per rilevare eventuale cannibalizzazione.</p></div>
@elseif($findings->isEmpty())
  <div class="articles-empty-state"><p>Nessuna cannibalizzazione rilevata per questo periodo: nessuna query con evidenza sufficiente (almeno {{ \App\Services\SearchConsole\SearchOpportunityScoringService::MIN_IMPRESSIONS }} impression) condivisa tra due o più articoli pubblici.</p></div>
@else
  <div style="overflow-x:auto">
    <table class="admin-table">
      <thead><tr><th>Query</th><th>Articoli in competizione</th><th>Decisione</th></tr></thead>
      <tbody>
        @foreach($findings as $finding)
          @php($currentDecision = $decisionsByKey[$finding->opportunity->key] ?? null)
          <tr>
            <td>
              <strong>{{ $finding->query }}</strong><br>
              <small>{{ $finding->opportunity->impressions }} impression totali · {{ $finding->competitors->count() }} articoli</small>
            </td>
            <td style="min-width:280px;">
              <ul style="margin:0;padding-left:1.1rem;">
                @foreach($finding->competitors as $index => $competitor)
                  <li style="margin-bottom:.3rem;">
                    <a href="{{ route('admin.articles.edit', $competitor['article']) }}">{{ \Illuminate\Support\Str::limit($competitor['article']->title, 48) }}</a>
                    @if($index === 0)<span class="status" style="background:#dcfce7;color:#166534;margin-left:.3rem;">probabile primario</span>@endif
                    <br><small>{{ $competitor['impressions'] }} impression · {{ $competitor['clicks'] }} clic · posizione {{ $competitor['position'] }}</small>
                  </li>
                @endforeach
              </ul>
            </td>
            <td style="min-width:260px;">
              @if($currentDecision)
                <div style="font-size:.78rem;margin-bottom:.4rem;">
                  <strong>{{ $decisionTypeOptions[$currentDecision->decision_type] ?? $currentDecision->decision_type }}</strong>
                  @if($currentDecision->article)
                    — <a href="{{ route('admin.articles.edit', $currentDecision->article_id) }}">{{ \Illuminate\Support\Str::limit($currentDecision->article->title, 30) }}</a>
                  @endif
                  @if($currentDecision->rationale)
                    <div style="color:var(--admin-muted);">{{ \Illuminate\Support\Str::limit($currentDecision->rationale, 60) }}</div>
                  @endif
                </div>
              @endif
              <details>
                <summary style="cursor:pointer;font-size:.76rem;color:#374151;">{{ $currentDecision ? 'Modifica decisione' : 'Registra decisione' }}</summary>
                <form method="POST" action="{{ route('admin.search-opportunities.record-decision') }}" style="margin-top:.5rem;display:flex;flex-direction:column;gap:.4rem;">
                  @csrf
                  <input type="hidden" name="opportunity_key" value="{{ $finding->opportunity->key }}">
                  <select name="decision_type" class="form-select" required>
                    <option value="">— Scegli —</option>
                    @foreach($decisionTypeOptions as $value => $label)
                      <option value="{{ $value }}" @selected(($currentDecision?->decision_type ?? \App\Models\SearchOpportunityDecision::DECISION_MERGE) === $value)>{{ $label }}</option>
                    @endforeach
                  </select>
                  <select name="article_id" class="form-select">
                    <option value="">— Nessun articolo target —</option>
                    @foreach($finding->competitors as $competitor)
                      <option value="{{ $competitor['article']->id }}" @selected(($currentDecision?->article_id ?? $finding->primaryArticle->id) === $competitor['article']->id)>{{ \Illuminate\Support\Str::limit($competitor['article']->title, 40) }}</option>
                    @endforeach
                  </select>
                  <textarea name="rationale" class="form-textarea" rows="2" placeholder="Motivazione (obbligatoria per sovrapposizione/ignora)">{{ $currentDecision?->rationale }}</textarea>
                  <button type="submit" class="btn btn--outline btn--sm">Salva</button>
                </form>
              </details>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif
@endsection
