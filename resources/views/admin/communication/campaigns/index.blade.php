@extends('layouts.admin')
@section('title', 'Campagne — Comunicazione')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Campagne</h1>
</div>

<form method="GET" style="display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1.25rem;">
  <select name="status" class="form-input" style="max-width:220px;" onchange="this.form.submit()">
    <option value="">Tutti gli stati</option>
    @foreach($statusOptions as $value => $label)
      <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
    @endforeach
  </select>
  <select name="type" class="form-input" style="max-width:220px;" onchange="this.form.submit()">
    <option value="">Tutti i tipi</option>
    @foreach($typeOptions as $value => $label)
      <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
    @endforeach
  </select>
  <select name="sort" class="form-input" style="max-width:220px;" onchange="this.form.submit()">
    <option value="recent" @selected(request('sort', 'recent') === 'recent')>Ordina per: più recenti</option>
    <option value="next-send" @selected(request('sort') === 'next-send')>Ordina per: prossimo invio</option>
  </select>
  <input type="search" name="q" value="{{ request('q') }}" placeholder="Cerca per titolo o oggetto…" class="form-input" style="max-width:260px;">
  <button type="submit" class="btn btn--secondary">Cerca</button>
</form>

@if($campaigns->isEmpty())
  <div class="admin-card project-empty-state">
    <div class="project-empty-state__icon">📨</div>
    <p class="project-empty-state__text">Nessuna campagna trovata. La creazione di nuove campagne arriva in un blocco successivo del Sistema Comunicazione — nel frattempo la newsletter continua da <a href="{{ route('admin.newsletter') }}">Newsletter</a>.</p>
  </div>
@else
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Titolo</th>
          <th>Tipo</th>
          <th>Stato</th>
          <th>Data</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        @foreach($campaigns as $campaign)
        <tr>
          <td><a href="{{ route('admin.comunicazione.campaigns.show', $campaign) }}" style="font-weight:700;">{{ $campaign->title }}</a></td>
          <td><span class="status" style="background:#f3f4f6;color:#4b5563;">{{ $typeOptions[$campaign->type] ?? $campaign->type }}</span></td>
          <td><span class="status status--campaign-{{ $campaign->status }}">{{ $statusOptions[$campaign->status] ?? $campaign->status }}</span></td>
          <td>
            @if($campaign->status === \App\Models\CommunicationCampaign::STATUS_SCHEDULED && $campaign->scheduled_at)
              Programmata per {{ $campaign->scheduled_at->format('d/m/Y H:i') }}
            @elseif($campaign->status === \App\Models\CommunicationCampaign::STATUS_COMPLETED && $campaign->completed_at)
              Inviata il {{ $campaign->completed_at->format('d/m/Y H:i') }}
            @else
              —
            @endif
          </td>
          <td><a href="{{ route('admin.comunicazione.campaigns.show', $campaign) }}" class="action-btn">Apri</a></td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{ $campaigns->links() }}
@endif

@endsection
