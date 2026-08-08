@extends('layouts.admin')
@section('title', $senderProfile->name.' — Comunicazione')
@section('content')

<div class="admin-topbar">
  <div>
    <a href="{{ route('admin.comunicazione.sender-profiles.index') }}" style="font-size:.8rem;color:#6b7280;text-decoration:none;">← Mittenti</a>
    <h1 class="admin-page-title" style="margin-top:.25rem;">{{ $senderProfile->name }}</h1>
  </div>
  <div style="display:flex;gap:.5rem;">
    <a href="{{ route('admin.comunicazione.sender-profiles.edit', $senderProfile) }}" class="btn btn--secondary">✏️ Modifica</a>
    @if($senderProfile->status === \App\Models\CommunicationSenderProfile::STATUS_ACTIVE)
      <form method="POST" action="{{ route('admin.comunicazione.sender-profiles.archive', $senderProfile) }}">
        @csrf
        <button type="submit" class="btn btn--secondary">🗄️ Archivia</button>
      </form>
    @endif
    <form id="delete-sender-profile-form" method="POST" action="{{ route('admin.comunicazione.sender-profiles.destroy', $senderProfile) }}"
          onsubmit="return confirm('Eliminare definitivamente questo mittente? L\'azione non è reversibile.')">
      @csrf @method('DELETE')
      <button type="submit" class="btn btn--danger">🗑️ Elimina</button>
    </form>
  </div>
</div>

<div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;margin-bottom:1.25rem;">
  <span class="status status--sender-profile-{{ $senderProfile->status }}">{{ \App\Models\CommunicationSenderProfile::statusOptions()[$senderProfile->status] ?? $senderProfile->status }}</span>
  @if($senderProfile->is_default)
    <span class="status" style="background:#e0e7ff;color:#3730a3;">Predefinito</span>
  @endif
</div>

<div class="admin-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-bottom:1.5rem;">
  <div class="stat-card">
    <dt class="stat-card__label">Campagne collegate</dt>
    <dd class="stat-card__value">{{ $campaignsCount }}</dd>
  </div>
  <div class="stat-card">
    <dt class="stat-card__label">Provider</dt>
    <dd class="stat-card__value" style="font-size:1.1rem;">{{ \App\Models\CommunicationSenderProfile::providerOptions()[$senderProfile->provider] ?? $senderProfile->provider }}</dd>
  </div>
  <div class="stat-card">
    <dt class="stat-card__label">Ultima modifica</dt>
    <dd class="stat-card__value" style="font-size:1.1rem;">{{ $senderProfile->updated_at?->format('d/m/Y H:i') }}</dd>
  </div>
</div>

<div class="admin-card">
  <h3 style="margin-top:0;">Identità di invio</h3>
  <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Nome mittente</dt>
      <dd style="margin:.2rem 0 0;font-weight:600;">{{ $senderProfile->from_name }}</dd>
    </div>
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Indirizzo mittente</dt>
      <dd style="margin:.2rem 0 0;font-weight:600;">{{ $senderProfile->from_email }}</dd>
    </div>
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Rispondi a</dt>
      <dd style="margin:.2rem 0 0;font-weight:600;">{{ $senderProfile->reply_to ?? '—' }}</dd>
    </div>
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Autore</dt>
      <dd style="margin:.2rem 0 0;font-weight:600;">{{ $senderProfile->createdBy?->name ?? '—' }}</dd>
    </div>
  </dl>
  <p style="font-size:.8rem;color:#9ca3af;margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid #f1f5f9;">
    Solo identità di invio: nessuna credenziale è memorizzata qui. L'invio effettivo usa la configurazione email già attiva sull'applicazione e non è ancora disponibile in questo blocco.
  </p>
</div>

@endsection
