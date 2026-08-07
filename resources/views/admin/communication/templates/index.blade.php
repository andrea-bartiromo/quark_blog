@extends('layouts.admin')
@section('title', 'Template — Comunicazione')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Template</h1>
  <a href="{{ route('admin.comunicazione.templates.create') }}" class="btn btn--primary">+ Nuovo template</a>
</div>

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

@if($templates->isEmpty())
  <div class="admin-card project-empty-state">
    <div class="project-empty-state__icon">🧩</div>
    <p class="project-empty-state__text">Nessun template trovato. <strong>Crea il primo</strong> per riutilizzare oggetto, preheader e corpo tra più campagne.</p>
    <a href="{{ route('admin.comunicazione.templates.create') }}" class="btn btn--primary">+ Nuovo template</a>
  </div>
@else
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Nome</th>
          <th>Tipo</th>
          <th>Stato</th>
          <th>Versione</th>
          <th>Ultima modifica</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        @foreach($templates as $template)
        <tr>
          <td><a href="{{ route('admin.comunicazione.templates.show', $template) }}" style="font-weight:700;">{{ $template->name }}</a></td>
          <td>
            @if($template->type)
              <span class="status" style="background:#f3f4f6;color:#4b5563;">{{ \App\Models\CommunicationCampaign::typeOptions()[$template->type] ?? $template->type }}</span>
            @else
              <span style="color:#9ca3af;font-size:.82rem;">Tutti i tipi</span>
            @endif
          </td>
          <td><span class="status status--template-{{ $template->status }}">{{ $statusOptions[$template->status] ?? $template->status }}</span></td>
          <td>v{{ $template->activeVersion->version_number ?? '—' }}</td>
          <td>{{ $template->updated_at?->format('d/m/Y H:i') }}</td>
          <td style="display:flex;gap:.4rem;flex-wrap:wrap;">
            <a href="{{ route('admin.comunicazione.templates.show', $template) }}" class="action-btn">Apri</a>
            <a href="{{ route('admin.comunicazione.templates.edit', $template) }}" class="action-btn">Modifica</a>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{ $templates->links() }}
@endif

@endsection
