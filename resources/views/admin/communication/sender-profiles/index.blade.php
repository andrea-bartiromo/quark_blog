@extends('layouts.admin')
@section('title', 'Mittenti — Comunicazione')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Mittenti</h1>
  <a href="{{ route('admin.comunicazione.sender-profiles.create') }}" class="btn btn--primary">+ Nuovo mittente</a>
</div>

<p style="color:#6b7280;font-size:.85rem;margin-top:-.75rem;margin-bottom:1.5rem;">
  Solo l'identità di invio (nome, indirizzo, risposta). L'invio vero e proprio non è ancora disponibile in questo blocco.
</p>

<form method="GET" style="display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1.25rem;">
  <select name="status" class="form-input" style="max-width:220px;" onchange="this.form.submit()">
    <option value="">Tutti gli stati</option>
    @foreach($statusOptions as $value => $label)
      <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
    @endforeach
  </select>
  <input type="search" name="q" value="{{ request('q') }}" placeholder="Cerca per nome…" class="form-input" style="max-width:260px;">
  <button type="submit" class="btn btn--secondary">Cerca</button>
</form>

@if($senderProfiles->isEmpty())
  <div class="admin-card project-empty-state">
    <div class="project-empty-state__icon">📤</div>
    <p class="project-empty-state__text">Nessun mittente trovato. <strong>Crea il primo</strong> per poterlo collegare alle campagne.</p>
    <a href="{{ route('admin.comunicazione.sender-profiles.create') }}" class="btn btn--primary">+ Nuovo mittente</a>
  </div>
@else
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Nome</th>
          <th>Mittente</th>
          <th>Provider</th>
          <th>Stato</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        @foreach($senderProfiles as $senderProfile)
        <tr>
          <td>
            <a href="{{ route('admin.comunicazione.sender-profiles.show', $senderProfile) }}" style="font-weight:700;">{{ $senderProfile->name }}</a>
            @if($senderProfile->is_default)
              <span class="status" style="background:#e0e7ff;color:#3730a3;margin-left:.35rem;">Predefinito</span>
            @endif
          </td>
          <td>{{ $senderProfile->from_name }} &lt;{{ $senderProfile->from_email }}&gt;</td>
          <td><span style="color:#6b7280;font-size:.82rem;">{{ \App\Models\CommunicationSenderProfile::providerOptions()[$senderProfile->provider] ?? $senderProfile->provider }}</span></td>
          <td><span class="status status--sender-profile-{{ $senderProfile->status }}">{{ $statusOptions[$senderProfile->status] ?? $senderProfile->status }}</span></td>
          <td style="display:flex;gap:.4rem;flex-wrap:wrap;">
            <a href="{{ route('admin.comunicazione.sender-profiles.show', $senderProfile) }}" class="action-btn">Apri</a>
            <a href="{{ route('admin.comunicazione.sender-profiles.edit', $senderProfile) }}" class="action-btn">Modifica</a>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{ $senderProfiles->links() }}
@endif

@endsection
