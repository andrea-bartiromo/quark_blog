@extends('layouts.admin')
@section('title','Autorevolezza tematica')
@section('content')
<div class="admin-topbar"><h1 class="admin-page-title">Autorevolezza tematica</h1></div>
<p style="color:var(--admin-muted);font-size:.85rem;max-width:82ch">
  Per ogni Percorso e Concetto pubblico, confronta la domanda di ricerca già osservata (Search Console) con la prontezza editoriale già misurata per i suoi articoli (Ricerca organica) — mai un nuovo audit strutturale: l'integrità di Percorsi e Concetti resta nelle pagine dedicate. Quando l'ultimo import Search Console non copre un argomento, lo stato è "domanda non osservata", non "domanda assente": il programma non inventa un segnale che i dati non possono provare.
</p>

@foreach(['clusters' => ['Percorsi', $clusters], 'concepts' => ['Concetti', $concepts]] as $key => [$label, $groups])
  <div class="admin-card" style="margin:1.25rem 0">
    <h2 style="font-size:1rem;margin:0 0 .8rem">{{ $label }}</h2>
    @if($groups->isEmpty())
      <p style="font-size:.82rem;color:var(--admin-muted)">Nessun {{ $key === 'clusters' ? 'Percorso pubblico' : 'Concetto attivo' }} da valutare.</p>
    @else
      <div style="overflow-x:auto">
        <table class="admin-table" style="width:100%;font-size:.82rem">
          <thead>
            <tr>
              <th style="text-align:left">Nome</th>
              <th style="text-align:right">Articoli</th>
              <th style="text-align:left">Stato</th>
              <th style="text-align:right">Impression</th>
              <th style="text-align:right">Click</th>
              <th style="text-align:left">Query secondarie dichiarate</th>
            </tr>
          </thead>
          <tbody>
            @foreach($groups as $row)
              <tr>
                <td>{{ $row['name'] }}</td>
                <td style="text-align:right">{{ $row['article_count'] }}</td>
                <td>{{ $stateLabels[$row['state']] ?? $row['state'] }}</td>
                <td style="text-align:right">{{ $row['impressions'] }}</td>
                <td style="text-align:right">{{ $row['clicks'] }}</td>
                <td>
                  @if(empty($row['secondary_queries']))
                    <span style="color:var(--admin-muted)">—</span>
                  @else
                    {{ implode(', ', array_slice($row['secondary_queries'], 0, 5)) }}
                    @if(count($row['secondary_queries']) > 5)
                      <span style="color:var(--admin-muted)">+{{ count($row['secondary_queries']) - 5 }}</span>
                    @endif
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
@endforeach
@endsection
