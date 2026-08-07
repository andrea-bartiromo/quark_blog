@extends('layouts.admin')
@section('title', ($campaign->exists ? 'Modifica' : 'Nuova').' campagna — Comunicazione')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">{{ $campaign->exists ? 'Modifica campagna' : 'Nuova campagna' }}</h1>
</div>

<div class="admin-card" style="max-width:760px;margin-bottom:1rem;">
  <form method="GET" action="{{ $campaign->exists ? route('admin.comunicazione.campaigns.edit', $campaign) : route('admin.comunicazione.campaigns.create') }}">
    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label" for="template_id">Template</label>
      <select class="form-select" id="template_id" name="template_id" onchange="this.form.submit()">
        <option value="">Nessun template</option>
        @foreach($templateOptions as $t)
          <option value="{{ $t->id }}" @selected((string) $selectedTemplateId === (string) $t->id)>{{ $t->name }}</option>
        @endforeach
      </select>
      <div style="font-size:.76rem;color:#9ca3af;margin-top:.35rem;">
        Cambiare il template ricarica la pagina — eventuali modifiche non ancora salvate ai campi sottostanti andrebbero perse. Il template non sovrascrive mai un campo che ha già un contenuto: propone il proprio testo solo dove il campo è ancora vuoto.
      </div>
    </div>
  </form>
</div>

<div class="admin-card" style="max-width:760px;">
  <form method="POST"
        action="{{ $campaign->exists ? route('admin.comunicazione.campaigns.update', $campaign) : route('admin.comunicazione.campaigns.store') }}">
    @csrf
    @if($campaign->exists) @method('PUT') @endif
    <input type="hidden" name="template_id" value="{{ $selectedTemplateId }}">
    <input type="hidden" name="template_version_id" value="{{ $selectedTemplateVersionId }}">

    <h3 style="margin-top:0;">Panoramica</h3>

    <div class="form-group">
      <label class="form-label" for="title">Titolo *</label>
      <input class="form-input" type="text" id="title" name="title" required
             value="{{ old('title', $campaign->title) }}">
      @error('title') <div style="color:#991b1b;font-size:.78rem;margin-top:.25rem;">{{ $message }}</div> @enderror
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
      <div class="form-group">
        <label class="form-label" for="type">Tipo *</label>
        <select class="form-select" id="type" name="type" required>
          @foreach(\App\Models\CommunicationCampaign::typeOptions() as $value => $label)
            <option value="{{ $value }}" @selected(old('type', $campaign->type) === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Stato</label>
        <div>
          <span class="status status--campaign-draft">Bozza</span>
          <span style="display:block;margin-top:.35rem;font-size:.76rem;color:#9ca3af;">Ogni campagna nasce e resta in bozza in questo blocco: l'invio non è ancora disponibile.</span>
        </div>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="project_id">Progetto collegato</label>
      <select class="form-select" id="project_id" name="project_id">
        <option value="">Nessuno</option>
        @foreach($projectOptions as $project)
          <option value="{{ $project->id }}" @selected((string) old('project_id', $campaign->project_id) === (string) $project->id)>{{ $project->title }}</option>
        @endforeach
      </select>
    </div>

    <div class="form-group">
      <label class="form-label" for="description">Descrizione interna</label>
      <textarea class="form-textarea" id="description" name="description" rows="3">{{ old('description', $campaign->description) }}</textarea>
      <div style="font-size:.76rem;color:#9ca3af;margin-top:.25rem;">Mai mostrata al destinatario — solo per la redazione.</div>
    </div>

    <div class="form-group">
      <label class="form-label" for="internal_notes">Note interne</label>
      <textarea class="form-textarea" id="internal_notes" name="internal_notes" rows="3">{{ old('internal_notes', $campaign->internal_notes) }}</textarea>
    </div>

    <h3>Contenuto</h3>

    <div class="form-group">
      <label class="form-label" for="subject">Oggetto email *</label>
      <input class="form-input" type="text" id="subject" name="subject" required
             value="{{ old('subject', $prefill['subject'] ?? $campaign->subject) }}">
      @error('subject') <div style="color:#991b1b;font-size:.78rem;margin-top:.25rem;">{{ $message }}</div> @enderror
      @if(! empty($prefill['subject']))
        <div style="font-size:.76rem;color:#0d9488;margin-top:.25rem;">Proposto dal template selezionato — modificabile liberamente.</div>
      @endif
    </div>

    <div class="form-group">
      <label class="form-label" for="preheader">Preheader</label>
      <input class="form-input" type="text" id="preheader" name="preheader"
             value="{{ old('preheader', $prefill['preheader'] ?? $campaign->preheader) }}">
    </div>

    <div class="form-group">
      <label class="form-label" for="body">Corpo newsletter</label>
      <textarea class="form-textarea" id="body" name="body" rows="10">{{ old('body', $prefill['body'] ?? ($campaign->content['body'] ?? '')) }}</textarea>
      <div style="font-size:.76rem;color:#9ca3af;margin-top:.25rem;">Testo semplice: editor a blocchi arriva in un blocco successivo.</div>
    </div>

    <div style="display:flex;gap:.6rem;margin-top:1rem;">
      <button class="btn btn--primary" type="submit">{{ $campaign->exists ? 'Salva modifiche' : 'Crea campagna' }}</button>
      <a href="{{ $campaign->exists ? route('admin.comunicazione.campaigns.show', $campaign) : route('admin.comunicazione.campaigns.index') }}" class="btn btn--secondary">Annulla</a>
    </div>
  </form>

  @if($campaign->exists)
    <form id="delete-campaign-form" method="POST" action="{{ route('admin.comunicazione.campaigns.destroy', $campaign) }}"
          onsubmit="return confirm('Eliminare definitivamente la campagna «{{ $campaign->title }}»? L\'azione non è reversibile.')"
          style="margin-top:.75rem;">
      @csrf @method('DELETE')
      <button type="submit" class="btn btn--danger">🗑️ Elimina campagna</button>
    </form>
  @endif
</div>

@endsection
