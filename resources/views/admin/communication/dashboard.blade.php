@extends('layouts.admin')
@section('title', 'Comunicazione')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Comunicazione</h1>
</div>

<p style="color:#6b7280;font-size:.85rem;margin-top:-.75rem;margin-bottom:1.5rem;">
  Fondamenta del nuovo Sistema Comunicazione, in sola lettura. La newsletter continua a funzionare da
  <a href="{{ route('admin.newsletter') }}">Newsletter</a> finché i blocchi successivi non aggiungono creazione, invio e statistiche qui.
</p>

<dl class="admin-grid admin-grid--stats" aria-label="Riepilogo Comunicazione" style="margin-bottom:1.5rem;">
  <div class="stat-card">
    <dt class="stat-card__label">Campagne in bozza</dt>
    <dd class="stat-card__value">{{ $draftCount }}</dd>
  </div>
  <div class="stat-card">
    <dt class="stat-card__label">Programmate nei prossimi 7 giorni</dt>
    <dd class="stat-card__value">{{ $scheduledNext7Count }}</dd>
  </div>
  <div class="stat-card">
    <dt class="stat-card__label">Completate negli ultimi 30 giorni</dt>
    <dd class="stat-card__value">{{ $completedLast30Count }}</dd>
  </div>
  <div class="stat-card">
    <dt class="stat-card__label">Errori aperti</dt>
    <dd class="stat-card__value">{{ $openErrorsCount }}</dd>
  </div>
</dl>

<div class="admin-grid" style="grid-template-columns:repeat(auto-fit,minmax(360px,1fr));">

  <div class="admin-card">
    <h3 style="margin-top:0;">Prossime campagne</h3>
    @if($upcomingCampaigns->isEmpty())
      <p style="color:#9ca3af;">Nessuna campagna programmata.</p>
    @else
      @foreach($upcomingCampaigns as $campaign)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;{{ ! $loop->last ? 'border-bottom:1px solid #f1f5f9;' : '' }}">
          <a href="{{ route('admin.comunicazione.campaigns.show', $campaign) }}" style="font-weight:600;text-decoration:none;color:#111827;">{{ $campaign->title }}</a>
          <span style="font-size:.72rem;color:#6b7280;">{{ $campaign->scheduled_at?->format('d/m/Y H:i') }}</span>
        </div>
      @endforeach
    @endif
  </div>

  <div class="admin-card">
    <h3 style="margin-top:0;">Ultime campagne inviate</h3>
    @if($recentSentCampaigns->isEmpty())
      <p style="color:#9ca3af;">Nessuna campagna completata ancora.</p>
    @else
      @foreach($recentSentCampaigns as $campaign)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;{{ ! $loop->last ? 'border-bottom:1px solid #f1f5f9;' : '' }}">
          <a href="{{ route('admin.comunicazione.campaigns.show', $campaign) }}" style="font-weight:600;text-decoration:none;color:#111827;">{{ $campaign->title }}</a>
          <span style="font-size:.72rem;color:#6b7280;">{{ $campaign->completed_at?->format('d/m/Y H:i') }}</span>
        </div>
      @endforeach
    @endif
  </div>

  <div class="admin-card">
    <h3 style="margin-top:0;">Coda invii in corso</h3>
    @if($sendingCampaign)
      <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;">
        <a href="{{ route('admin.comunicazione.campaigns.show', $sendingCampaign) }}" style="font-weight:600;text-decoration:none;color:#111827;">{{ $sendingCampaign->title }}</a>
        <span class="status status--campaign-sending">Invio in corso</span>
      </div>
    @else
      <p style="color:#9ca3af;">Nessun invio in corso.</p>
    @endif
  </div>

  <div class="admin-card">
    <h3 style="margin-top:0;">Provider e scheduler</h3>
    <p style="color:#9ca3af;">Non verificabile in questo blocco — la configurazione dei provider di invio (<code>comm_sender_profiles</code>) e il motore di invio arrivano in un blocco successivo del Blueprint.</p>
  </div>

</div>

@endsection
