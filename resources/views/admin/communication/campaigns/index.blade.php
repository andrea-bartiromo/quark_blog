@extends('layouts.admin')
@section('title', 'Campagne — Comunicazione')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Campagne</h1>
  <a href="{{ route('admin.comunicazione.campaigns.create') }}" class="btn btn--primary">+ Nuova campagna</a>
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
    <p class="project-empty-state__text">Nessuna campagna trovata. <strong>Crea la prima</strong> per iniziare — resta in bozza finché l'invio non sarà disponibile in un blocco successivo. Nel frattempo la newsletter continua da <a href="{{ route('admin.newsletter') }}">Newsletter</a>.</p>
    <a href="{{ route('admin.comunicazione.campaigns.create') }}" class="btn btn--primary">+ Nuova campagna</a>
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
          <td style="display:flex;gap:.4rem;flex-wrap:wrap;">
            <a href="{{ route('admin.comunicazione.campaigns.show', $campaign) }}" class="action-btn">Apri</a>
            <a href="{{ route('admin.comunicazione.campaigns.edit', $campaign) }}" class="action-btn">Modifica</a>
            <form method="POST" action="{{ route('admin.comunicazione.campaigns.duplicate', $campaign) }}">
              @csrf
              <button type="submit" class="action-btn">Duplica</button>
            </form>
            <form method="POST" action="{{ route('admin.comunicazione.campaigns.destroy', $campaign) }}"
                  onsubmit="return confirm('Eliminare definitivamente la campagna «{{ $campaign->title }}»? L\'azione non è reversibile.')">
              @csrf @method('DELETE')
              <button type="submit" class="action-btn action-btn--danger">Elimina</button>
            </form>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{ $campaigns->links() }}
@endif

@endsection
