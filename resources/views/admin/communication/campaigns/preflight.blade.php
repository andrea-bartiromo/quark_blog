@extends('layouts.admin')
@section('title', 'Verifica pre-invio — '.$campaign->title)
@section('content')

<div class="admin-topbar">
  <div>
    <a href="{{ route('admin.comunicazione.campaigns.show', $campaign) }}" style="font-size:.8rem;color:#6b7280;text-decoration:none;">← {{ $campaign->title }}</a>
    <h1 class="admin-page-title" style="margin-top:.25rem;">Verifica pre-invio</h1>
  </div>
</div>

<p style="color:#9ca3af;font-size:.85rem;margin-top:-.75rem;margin-bottom:1.5rem;">
  Solo verifica: questa pagina non invia email, non mette in coda, non crea consegne e non modifica campagna o iscritti — anche riaperta più volte. Qui non esiste, e non esisterà mai, un pulsante "Invia".
</p>

<div class="admin-card" style="max-width:720px;margin-bottom:1.25rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
  @if($report->isReady())
    <span class="status" style="background:#d1fae5;color:#065f46;font-size:.85rem;padding:.4rem .8rem;">✅ {{ $report->readinessLabel() }}</span>
    <p style="margin:0;color:#6b7280;font-size:.85rem;">Nessun blocco rilevato.</p>
    <form method="POST" action="{{ route('admin.comunicazione.campaigns.dry-run', $campaign) }}" style="margin-left:auto;">
      @csrf
      <button type="submit" class="btn btn--secondary">🧪 Esegui dry-run</button>
    </form>
  @else
    <span class="status" style="background:#fee2e2;color:#991b1b;font-size:.85rem;padding:.4rem .8rem;">⛔ {{ $report->readinessLabel() }}</span>
    <p style="margin:0;color:#6b7280;font-size:.85rem;">{{ count($report->blockingErrors) }} blocco/hi da risolvere prima di procedere.</p>
  @endif
</div>

<div class="admin-card" style="max-width:720px;margin-bottom:1.25rem;">
  <h3 style="margin-top:0;font-size:.95rem;">Riepilogo campagna</h3>
  <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin:0;">
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Stato</dt>
      <dd style="margin:.2rem 0 0;font-weight:600;">{{ $statusOptions[$campaign->status] ?? $campaign->status }}</dd>
    </div>
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Oggetto</dt>
      <dd style="margin:.2rem 0 0;font-weight:600;">{{ $campaign->subject ?: '(nessun oggetto)' }}</dd>
    </div>
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Mittente</dt>
      <dd style="margin:.2rem 0 0;font-weight:600;">
        @if($campaign->senderProfile)
          {{ $campaign->senderProfile->name }} ({{ $campaign->senderProfile->from_email }})
        @else
          — nessuno
        @endif
      </dd>
    </div>
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Disiscrizione pubblica</dt>
      <dd style="margin:.2rem 0 0;font-weight:600;">{{ $report->unsubscribeRouteAvailable ? 'Disponibile' : 'Non disponibile' }}</dd>
    </div>
  </dl>
  <a href="{{ route('admin.comunicazione.campaigns.preview', $campaign) }}" class="btn btn--secondary" style="margin-top:1rem;">👁️ Apri anteprima reale</a>
</div>

<div class="admin-card" style="max-width:720px;margin-bottom:1.25rem;">
  <h3 style="margin-top:0;font-size:.95rem;">Destinatari</h3>
  <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin:0;">
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Preparati (in coda)</dt>
      <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.1rem;">{{ number_format($report->preparedCount) }}</dd>
    </div>
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Non più confermati</dt>
      <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.1rem;">{{ number_format($report->staleCount) }}</dd>
    </div>
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Confermati non ancora preparati</dt>
      <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.1rem;">{{ number_format($report->notYetPreparedCount) }}</dd>
    </div>
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Iscritti confermati totali</dt>
      <dd style="margin:.2rem 0 0;font-weight:600;font-size:1.1rem;">{{ number_format($report->eligibleTotal) }}</dd>
    </div>
  </dl>
  <form method="POST" action="{{ route('admin.comunicazione.campaigns.recipients.prepare', $campaign) }}" style="margin-top:1rem;">
    @csrf
    <button type="submit" class="btn btn--secondary">🔄 Prepara/aggiorna destinatari</button>
  </form>
</div>

@if(!empty($report->blockingErrors))
  <div class="admin-card" style="max-width:720px;margin-bottom:1.25rem;border-color:#fecaca;">
    <h3 style="margin-top:0;font-size:.95rem;color:#991b1b;">⛔ Blocchi</h3>
    <ul style="margin:0;padding-left:1.25rem;color:#7f1d1d;">
      @foreach($report->blockingErrors as $error)
        <li style="margin-bottom:.4rem;">{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

@if(!empty($report->warnings))
  <div class="admin-card" style="max-width:720px;margin-bottom:1.25rem;border-color:#fde68a;">
    <h3 style="margin-top:0;font-size:.95rem;color:#92400e;">⚠️ Avvisi (non bloccanti)</h3>
    <ul style="margin:0;padding-left:1.25rem;color:#78350f;">
      @foreach($report->warnings as $warning)
        <li style="margin-bottom:.4rem;">{{ $warning }}</li>
      @endforeach
    </ul>
  </div>
@endif

@endsection
