@extends('layouts.admin')
@section('title', $campaign->title.' — Comunicazione')
@section('content')

<div class="admin-topbar">
  <div>
    <a href="{{ route('admin.comunicazione.campaigns.index') }}" style="font-size:.8rem;color:#6b7280;text-decoration:none;">← Campagne</a>
    <h1 class="admin-page-title" style="margin-top:.25rem;">{{ $campaign->title }}</h1>
  </div>
</div>

<div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;margin-bottom:1.25rem;">
  <span class="status status--campaign-{{ $campaign->status }}">{{ $statusOptions[$campaign->status] ?? $campaign->status }}</span>
  <span class="status" style="background:#f3f4f6;color:#4b5563;">{{ $typeOptions[$campaign->type] ?? $campaign->type }}</span>
</div>

<div class="admin-card">
  <h3 style="margin-top:0;">Panoramica</h3>
  <p style="color:#9ca3af;font-size:.85rem;">Vista di sola lettura — la pagina completa con schede Contenuto, Articoli, Template, Segmenti, Invii e Statistiche arriva in un blocco successivo del Blueprint (UX3).</p>

  <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-top:1.25rem;">
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Oggetto</dt>
      <dd style="margin:.2rem 0 0;font-weight:600;">{{ $campaign->subject }}</dd>
    </div>
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Preheader</dt>
      <dd style="margin:.2rem 0 0;font-weight:600;">{{ $campaign->preheader ?? '—' }}</dd>
    </div>
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Progetto collegato</dt>
      <dd style="margin:.2rem 0 0;font-weight:600;">{{ $campaign->project?->title ?? '—' }}</dd>
    </div>
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Programmata per</dt>
      <dd style="margin:.2rem 0 0;font-weight:600;">{{ $campaign->scheduled_at?->format('d/m/Y H:i') ?? '—' }}</dd>
    </div>
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Inviata il</dt>
      <dd style="margin:.2rem 0 0;font-weight:600;">{{ $campaign->completed_at?->format('d/m/Y H:i') ?? '—' }}</dd>
    </div>
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Destinatari registrati</dt>
      <dd style="margin:.2rem 0 0;font-weight:600;">{{ $campaign->sends()->count() }}</dd>
    </div>
  </dl>
</div>

@endsection
