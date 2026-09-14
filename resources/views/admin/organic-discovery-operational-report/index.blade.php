@extends('layouts.admin')
@section('title','Report ricerca organica')
@section('content')
<div class="admin-topbar"><h1 class="admin-page-title">Report ricerca organica</h1></div>
<p style="color:var(--admin-muted);font-size:.85rem;max-width:82ch">
  Un unico punto di lettura sullo stato del programma "Kairus Organic Discovery" — calcolato a ogni apertura della pagina, mai un job schedulato o un'email automatica. Compone solo i dati e gli stati già calcolati dalle pagine dedicate (Ricerca organica, Opportunità di ricerca, Cannibalizzazione ricerca); per agire su un singolo caso, apri la pagina corrispondente.
</p>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;margin:1.25rem 0">

  <div class="admin-card">
    <h2 style="font-size:.95rem;margin:0 0 .6rem">Dati Search Console</h2>
    @if($snapshot['search_console']['freshness']['available'])
      <p style="font-size:.82rem;margin:.2rem 0">Ultimo import: <strong>{{ $snapshot['search_console']['freshness']['days_since_last_import'] }} giorni fa</strong></p>
      <p style="font-size:.82rem;margin:.2rem 0">Periodi coperti: <strong>{{ $snapshot['search_console']['coverage']['periods_covered'] }}</strong></p>
      <p style="font-size:.82rem;margin:.2rem 0">Righe importate totali: <strong>{{ $snapshot['search_console']['coverage']['total_rows_imported'] }}</strong></p>
      <p style="font-size:.82rem;margin:.2rem 0">Query non assegnate a un articolo: <strong>{{ $snapshot['search_console']['coverage']['total_unmatched_queries'] }}</strong></p>
    @else
      <p style="font-size:.82rem;color:var(--admin-muted)">Nessun import Search Console disponibile.</p>
      <p style="font-size:.82rem;"><a href="{{ route('admin.search-opportunities.import-form') }}">Importa un CSV</a></p>
    @endif
  </div>

  <div class="admin-card">
    <h2 style="font-size:.95rem;margin:0 0 .6rem">Prontezza organica</h2>
    <p style="font-size:.82rem;margin:.2rem 0">Articoli pubblici valutati: <strong>{{ $snapshot['readiness']['total'] }}</strong></p>
    @foreach($snapshot['readiness']['by_state'] as $row)
      <p style="font-size:.82rem;margin:.2rem 0">{{ $row['label'] }}: <strong>{{ $row['count'] }}</strong></p>
    @endforeach
    <p style="font-size:.78rem;margin-top:.5rem"><a href="{{ route('admin.organic-discovery-readiness') }}">Vedi il dettaglio →</a></p>
  </div>

  <div class="admin-card">
    <h2 style="font-size:.95rem;margin:0 0 .6rem">Opportunità del periodo corrente</h2>
    <p style="font-size:.82rem;margin:.2rem 0">Totali: <strong>{{ $snapshot['opportunities']['current_total'] }}</strong></p>
    <p style="font-size:.82rem;margin:.2rem 0">Con decisione registrata: <strong>{{ $snapshot['opportunities']['current_with_decision'] }}</strong></p>
    <p style="font-size:.82rem;margin:.2rem 0">Senza decisione: <strong>{{ $snapshot['opportunities']['current_without_decision'] }}</strong></p>
    <p style="font-size:.78rem;margin-top:.5rem"><a href="{{ route('admin.search-opportunities') }}">Vedi l'elenco →</a></p>
  </div>

  <div class="admin-card">
    <h2 style="font-size:.95rem;margin:0 0 .6rem">Cannibalizzazione di ricerca</h2>
    <p style="font-size:.82rem;margin:.2rem 0">Query con più articoli in competizione (periodo corrente): <strong>{{ $snapshot['cannibalization']['current_period_findings'] }}</strong></p>
    <p style="font-size:.78rem;margin-top:.5rem"><a href="{{ route('admin.search-cannibalization') }}">Vedi il dettaglio →</a></p>
  </div>

  <div class="admin-card">
    <h2 style="font-size:.95rem;margin:0 0 .6rem">Decisioni editoriali (tutte, ogni periodo)</h2>
    <p style="font-size:.82rem;margin:.2rem 0">Totali registrate: <strong>{{ $snapshot['decisions']['total'] }}</strong></p>
    @foreach($snapshot['decisions']['by_type'] as $row)
      <p style="font-size:.82rem;margin:.2rem 0">{{ $row['label'] }}: <strong>{{ $row['count'] }}</strong></p>
    @endforeach
    <p style="font-size:.82rem;margin:.4rem 0 .2rem;color:var(--admin-muted)">In attesa di misurazione (dovuta, non ancora eseguita)</p>
    <p style="font-size:.82rem;margin:.2rem 0">A 28 giorni: <strong>{{ $snapshot['decisions']['due_but_unmeasured_28d'] }}</strong></p>
    <p style="font-size:.82rem;margin:.2rem 0">A 90 giorni: <strong>{{ $snapshot['decisions']['due_but_unmeasured_90d'] }}</strong></p>
    @if($snapshot['decisions']['due_but_unmeasured_28d'] > 0 || $snapshot['decisions']['due_but_unmeasured_90d'] > 0)
      <p style="font-size:.76rem;color:var(--admin-muted);margin-top:.3rem">Esegui <code>php artisan search-opportunities:measure-outcomes</code> per aggiornarle (comando manuale, non schedulato in questa v1).</p>
    @endif
  </div>

  <div class="admin-card">
    <h2 style="font-size:.95rem;margin:0 0 .6rem">Esiti misurati</h2>
    <p style="font-size:.78rem;color:var(--admin-muted);margin:0 0 .5rem">Confronto clic osservati vs baseline alla decisione. Nessuna soglia di significatività: anche un solo clic in più conta come "migliorata".</p>
    <p style="font-size:.82rem;margin:.4rem 0 .1rem"><strong>A 28 giorni</strong> ({{ $snapshot['outcomes']['28d']['measured'] }} misurate)</p>
    <p style="font-size:.82rem;margin:.1rem 0">Migliorate: <strong>{{ $snapshot['outcomes']['28d']['improved'] }}</strong> · Invariate: <strong>{{ $snapshot['outcomes']['28d']['flat'] }}</strong> · Peggiorate: <strong>{{ $snapshot['outcomes']['28d']['worse'] }}</strong></p>
    <p style="font-size:.82rem;margin:.4rem 0 .1rem"><strong>A 90 giorni</strong> ({{ $snapshot['outcomes']['90d']['measured'] }} misurate)</p>
    <p style="font-size:.82rem;margin:.1rem 0">Migliorate: <strong>{{ $snapshot['outcomes']['90d']['improved'] }}</strong> · Invariate: <strong>{{ $snapshot['outcomes']['90d']['flat'] }}</strong> · Peggiorate: <strong>{{ $snapshot['outcomes']['90d']['worse'] }}</strong></p>
  </div>

</div>
@endsection
