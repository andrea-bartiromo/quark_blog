@extends('layouts.admin')
@section('title', $campaign->title.' — Comunicazione')
@section('content')

@php
  $tabs = [
    'overview' => 'Panoramica',
    'content' => 'Contenuto',
    'articles' => 'Articoli',
    'template' => 'Template',
    'segments' => 'Segmenti',
    'sends' => 'Invii',
    'stats' => 'Statistiche',
    'history' => 'Cronologia',
  ];
  $placeholderTabs = ['articles', 'segments', 'sends', 'stats'];
@endphp

<div class="admin-topbar">
  <div>
    <a href="{{ route('admin.comunicazione.campaigns.index') }}" style="font-size:.8rem;color:#6b7280;text-decoration:none;">← Campagne</a>
    <h1 class="admin-page-title" style="margin-top:.25rem;">{{ $campaign->title }}</h1>
  </div>
  <div style="display:flex;gap:.5rem;">
    <a href="{{ route('admin.comunicazione.campaigns.preview', $campaign) }}" class="btn btn--secondary">👁️ Anteprima</a>
    <a href="{{ route('admin.comunicazione.campaigns.edit', $campaign) }}" class="btn btn--secondary">✏️ Modifica campagna</a>
    <form id="delete-campaign-form" method="POST" action="{{ route('admin.comunicazione.campaigns.destroy', $campaign) }}"
          onsubmit="return confirm('Eliminare definitivamente la campagna «{{ $campaign->title }}»? L\'azione non è reversibile.')">
      @csrf @method('DELETE')
      <button type="submit" class="btn btn--danger">🗑️ Elimina campagna</button>
    </form>
  </div>
</div>

<div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;margin-bottom:1.25rem;">
  <span class="status status--campaign-{{ $campaign->status }}">{{ $statusOptions[$campaign->status] ?? $campaign->status }}</span>
  <span class="status" style="background:#f3f4f6;color:#4b5563;">{{ $typeOptions[$campaign->type] ?? $campaign->type }}</span>

  <form method="POST" action="{{ route('admin.comunicazione.campaigns.duplicate', $campaign) }}" style="margin-left:auto;">
    @csrf
    <button type="submit" class="btn btn--secondary">📋 Duplica campagna</button>
  </form>
</div>

<nav aria-label="Schede campagna" class="project-tabs">
  @foreach($tabs as $key => $label)
    <a href="{{ route('admin.comunicazione.campaigns.show', [$campaign, 'tab' => $key]) }}"
       @class(['active' => $activeTab === $key])
       aria-current="{{ $activeTab === $key ? 'page' : 'false' }}">
      {{ $label }}
      @if(in_array($key, $placeholderTabs, true))
        <span class="status" style="background:#f3f4f6;color:#9ca3af;margin-left:.35rem;">In arrivo</span>
      @endif
    </a>
  @endforeach
</nav>

@if($activeTab === 'overview')
  <div class="admin-card">
    <h3 style="margin-top:0;">Panoramica</h3>

    @if($campaign->description)
      <p>{{ $campaign->description }}</p>
    @endif

    <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-top:1.25rem;">
      <div>
        <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Progetto collegato</dt>
        <dd style="margin:.2rem 0 0;font-weight:600;">{{ $campaign->project?->title ?? '—' }}</dd>
      </div>
      <div>
        <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Autore</dt>
        <dd style="margin:.2rem 0 0;font-weight:600;">{{ $campaign->createdBy?->name ?? '—' }}</dd>
      </div>
      <div>
        <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Creata il</dt>
        <dd style="margin:.2rem 0 0;font-weight:600;">{{ $campaign->created_at?->format('d/m/Y H:i') }}</dd>
      </div>
      <div>
        <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Ultima modifica</dt>
        <dd style="margin:.2rem 0 0;font-weight:600;">{{ $campaign->updated_at?->format('d/m/Y H:i') }} @if($campaign->updatedBy) · {{ $campaign->updatedBy->name }} @endif</dd>
      </div>
    </dl>

    @if($campaign->internal_notes)
      <div style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid #f1f5f9;">
        <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Note interne</dt>
        <dd style="margin:.4rem 0 0;white-space:pre-line;">{{ $campaign->internal_notes }}</dd>
      </div>
    @endif
  </div>
@elseif($activeTab === 'content')
  <div class="admin-card">
    <h3 style="margin-top:0;">Contenuto</h3>

    <dl>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Oggetto email</dt>
      <dd style="margin:.2rem 0 1rem;font-weight:600;">{{ $campaign->subject }}</dd>

      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Preheader</dt>
      <dd style="margin:.2rem 0 1rem;font-weight:600;">{{ $campaign->preheader ?? '—' }}</dd>

      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Corpo newsletter</dt>
      <dd style="margin:.4rem 0 0;white-space:pre-line;">{{ $campaign->content['body'] ?? '—' }}</dd>
    </dl>
  </div>
@elseif($activeTab === 'template')
  <div class="admin-card">
    <h3 style="margin-top:0;">Template</h3>

    @if($campaign->template)
      <dl>
        <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Template collegato</dt>
        <dd style="margin:.2rem 0 1rem;font-weight:600;">
          <a href="{{ route('admin.comunicazione.templates.show', $campaign->template) }}">{{ $campaign->template->name }}</a>
        </dd>

        <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Versione usata</dt>
        <dd style="margin:.2rem 0 1rem;font-weight:600;">
          v{{ $campaign->templateVersion->version_number ?? '—' }}
        </dd>
      </dl>
      <p style="font-size:.8rem;color:#9ca3af;">
        La campagna resta ancorata a questa versione: se il template avanza a una versione più recente, questa campagna continuerà a mostrare la versione con cui è stata collegata.
      </p>
      <a href="{{ route('admin.comunicazione.templates.preview', ['template' => $campaign->template, 'versione' => $campaign->template_version_id]) }}" class="btn btn--secondary">Anteprima del template usato</a>
    @else
      <div class="project-empty-state">
        <div class="project-empty-state__icon">🧩</div>
        <p class="project-empty-state__text">Nessun template collegato a questa campagna.</p>
        <a href="{{ route('admin.comunicazione.campaigns.edit', $campaign) }}" class="btn btn--secondary">Collega un template</a>
      </div>
    @endif
  </div>
@elseif($activeTab === 'history')
  @if($activityLog->isEmpty())
    <div class="admin-card project-empty-state">
      <div class="project-empty-state__icon">🕐</div>
      <p class="project-empty-state__text">Nessuna attività registrata ancora. Ogni modifica a questa campagna comparirà qui.</p>
    </div>
  @else
    <div class="admin-card">
      @foreach($activityLog as $log)
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;padding:.75rem 0;{{ ! $loop->last ? 'border-bottom:1px solid #f1f5f9;' : '' }}">
          <div>
            <div style="font-weight:600;font-size:.85rem;">{{ $log->action }}</div>
            <div style="font-size:.72rem;color:#9ca3af;">{{ $log->user?->name ?? 'Sistema' }}</div>
          </div>
          <div style="font-size:.72rem;color:#9ca3af;white-space:nowrap;">{{ $log->created_at?->format('d/m/Y H:i') }}</div>
        </div>
      @endforeach
    </div>
  @endif
@elseif(in_array($activeTab, $placeholderTabs, true))
  <div class="admin-card project-empty-state">
    <div class="project-empty-state__icon">🚧</div>
    <p class="project-empty-state__text">
      <strong>{{ $tabs[$activeTab] }}</strong> arriva in un blocco successivo del Sistema Comunicazione.
      <span class="status" style="background:#f3f4f6;color:#9ca3af;">In arrivo</span>
    </p>
  </div>
@endif

@endsection
