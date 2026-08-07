@extends('layouts.admin')
@section('title', $template->name.' — Comunicazione')
@section('content')

<div class="admin-topbar">
  <div>
    <a href="{{ route('admin.comunicazione.templates.index') }}" style="font-size:.8rem;color:#6b7280;text-decoration:none;">← Template</a>
    <h1 class="admin-page-title" style="margin-top:.25rem;">{{ $template->name }}</h1>
  </div>
  <div style="display:flex;gap:.5rem;">
    <a href="{{ route('admin.comunicazione.templates.preview', $template) }}" class="btn btn--secondary">👁️ Anteprima</a>
    <a href="{{ route('admin.comunicazione.templates.edit', $template) }}" class="btn btn--secondary">✏️ Modifica</a>
    @if($template->status === \App\Models\CommunicationTemplate::STATUS_ACTIVE)
      <form method="POST" action="{{ route('admin.comunicazione.templates.archive', $template) }}">
        @csrf
        <button type="submit" class="btn btn--secondary">🗄️ Archivia</button>
      </form>
    @endif
    <form id="delete-template-form" method="POST" action="{{ route('admin.comunicazione.templates.destroy', $template) }}"
          onsubmit="return confirm('Eliminare definitivamente questo template? L\'azione non è reversibile.')">
      @csrf @method('DELETE')
      <button type="submit" class="btn btn--danger">🗑️ Elimina</button>
    </form>
  </div>
</div>

<div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;margin-bottom:1.25rem;">
  <span class="status status--template-{{ $template->status }}">{{ \App\Models\CommunicationTemplate::statusOptions()[$template->status] ?? $template->status }}</span>
  @if($template->type)
    <span class="status" style="background:#f3f4f6;color:#4b5563;">{{ \App\Models\CommunicationCampaign::typeOptions()[$template->type] ?? $template->type }}</span>
  @else
    <span style="color:#9ca3af;font-size:.82rem;">Tutti i tipi</span>
  @endif

  <form method="POST" action="{{ route('admin.comunicazione.templates.duplicate', $template) }}" style="margin-left:auto;">
    @csrf
    <button type="submit" class="btn btn--secondary">📋 Duplica template</button>
  </form>
</div>

<div class="admin-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-bottom:1.5rem;">
  <div class="stat-card">
    <dt class="stat-card__label">Versione corrente</dt>
    <dd class="stat-card__value">v{{ $template->activeVersion->version_number ?? '—' }}</dd>
  </div>
  <div class="stat-card">
    <dt class="stat-card__label">Numero versioni</dt>
    <dd class="stat-card__value">{{ $template->versions->count() }}</dd>
  </div>
  <div class="stat-card">
    <dt class="stat-card__label">Ultima modifica</dt>
    <dd class="stat-card__value" style="font-size:1.1rem;">{{ $template->updated_at?->format('d/m/Y H:i') }}</dd>
  </div>
  <div class="stat-card">
    <dt class="stat-card__label">Campagne collegate</dt>
    <dd class="stat-card__value">{{ $campaignsCount }}</dd>
  </div>
</div>

<div class="admin-card" style="margin-bottom:1.5rem;">
  <h3 style="margin-top:0;">Panoramica</h3>
  @if($template->description)
    <p>{{ $template->description }}</p>
  @endif
  <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-top:1rem;">
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Autore</dt>
      <dd style="margin:.2rem 0 0;font-weight:600;">{{ $template->createdBy?->name ?? '—' }}</dd>
    </div>
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Oggetto attuale</dt>
      <dd style="margin:.2rem 0 0;font-weight:600;">{{ $template->activeVersion->subject ?? '—' }}</dd>
    </div>
  </dl>
</div>

<div class="admin-card">
  <h3 style="margin-top:0;">Cronologia versioni</h3>
  @if($template->versions->isEmpty())
    <p style="color:#9ca3af;">Nessuna versione ancora.</p>
  @else
    @foreach($template->versions as $v)
      <div style="display:flex;justify-content:space-between;align-items:center;padding:.75rem 0;{{ ! $loop->last ? 'border-bottom:1px solid #f1f5f9;' : '' }}">
        <div>
          <div style="font-weight:600;font-size:.85rem;">
            v{{ $v->version_number }}
            @if($template->active_version_id === $v->id)
              <span class="status status--template-active" style="margin-left:.4rem;">Attiva</span>
            @endif
          </div>
          <div style="font-size:.78rem;color:#6b7280;">{{ $v->subject }}</div>
          <div style="font-size:.72rem;color:#9ca3af;">{{ $v->createdBy?->name ?? 'Sistema' }} · {{ $v->created_at?->format('d/m/Y H:i') }}</div>
        </div>
        <div style="display:flex;gap:.4rem;">
          <a href="{{ route('admin.comunicazione.templates.preview', ['template' => $template, 'versione' => $v->id]) }}" class="action-btn">Anteprima</a>
          @if($template->active_version_id !== $v->id)
            <form method="POST" action="{{ route('admin.comunicazione.templates.versions.duplicate', [$template, $v]) }}">
              @csrf
              <button type="submit" class="action-btn">Duplica da questa versione</button>
            </form>
          @endif
        </div>
      </div>
    @endforeach
  @endif
</div>

@endsection
