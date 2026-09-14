@extends('layouts.admin')
@section('title', 'Gate pubblicazione pilot Trust')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Gate pubblicazione pilot Trust</h1>
  <a class="action-btn" href="{{ route('admin.trust-knowledge.index') }}">Torna a "Cosa sappiamo davvero"</a>
</div>

<p style="color:var(--admin-muted);font-size:.85rem;max-width:82ch;margin-bottom:1rem">
  Pagina di sola lettura: mostra lo stato delle tre condizioni del NO-GO B-45
  (docs/TRUST_LAYER_COSA_SAPPIAMO_DAVVERO_PILOT.md) — non permette di
  assegnare un owner, approvare contenuto o registrare alcuna decisione.
  Nessuna delle condizioni qui sotto viene impostata da questa pagina:
  vengono solo lette dallo stato reale del sistema.
</p>

<div class="admin-alert admin-alert--{{ $allConditionsMet ? 'success' : 'danger' }}" role="status">
  @if($allConditionsMet)
    Tutte e tre le condizioni risultano soddisfatte. Questo NON è una decisione GO: la decisione resta umana ed esplicita, e resta fuori da questa pagina.
  @else
    Il pilot Trust pubblico resta in stato NO-GO: almeno una condizione non è ancora soddisfatta.
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
            @if($condition['state'] === \App\Services\Trust\TrustPilotGateReadinessService::STATE_MET)
              <span class="status status--published">Soddisfatta</span>
            @elseif($condition['state'] === \App\Services\Trust\TrustPilotGateReadinessService::STATE_NOT_MET)
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
