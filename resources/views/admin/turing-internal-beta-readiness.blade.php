@extends('layouts.admin')
@section('title', 'Checklist beta interna — Speciale Turing')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Checklist beta interna — Speciale Turing</h1>
  <a class="action-btn" href="{{ route('admin.turing') }}">Torna a Speciale Turing</a>
</div>

<p style="color:var(--admin-muted);font-size:.85rem;max-width:82ch;margin-bottom:1rem">
  Pagina di sola lettura: mostra lo stato delle condizioni per sottoporre lo Speciale Turing a
  revisori/tester interni tramite l'anteprima amministrativa già esistente — non per renderlo
  pubblico (quella resta una decisione separata, fuori da questa pagina). Nessuna condizione qui
  sotto viene impostata da questa pagina: vengono solo lette dallo stato reale del sistema.
</p>

<div class="admin-alert admin-alert--{{ $allConditionsMet ? 'success' : 'danger' }}" role="status">
  @if($allConditionsMet)
    Tutte le condizioni risultano soddisfatte. Questo NON è una decisione di avvio della revisione interna: la decisione resta umana ed esplicita, e resta fuori da questa pagina.
  @else
    La revisione beta interna non è ancora pronta: almeno una condizione non è ancora soddisfatta.
  @endif
</div>

<div class="admin-card" style="overflow-x:auto">
  <table class="admin-table" style="width:100%">
    <thead>
      <tr>
        <th style="text-align:left">Condizione</th>
        <th style="text-align:left">Stato</th>
        <th style="text-align:left">Dettaglio</th>
      </tr>
    </thead>
    <tbody>
      @foreach($conditions as $condition)
        <tr>
          <td>{{ $condition['label'] }}</td>
          <td>
            @if($condition['state'] === \App\Services\Turing\TuringInternalBetaReadinessService::STATE_MET)
              <span class="status status--published">Soddisfatta</span>
            @elseif($condition['state'] === \App\Services\Turing\TuringInternalBetaReadinessService::STATE_NOT_MET)
              <span class="status status--draft">Non soddisfatta</span>
            @else
              <span class="status">Non determinabile automaticamente</span>
            @endif
          </td>
          <td style="max-width:480px;color:var(--admin-muted);font-size:.85rem">{{ $condition['detail'] }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection
