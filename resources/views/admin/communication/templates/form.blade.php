@extends('layouts.admin')
@section('title', ($template->exists ? 'Modifica' : 'Nuovo').' template — Comunicazione')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">{{ $template->exists ? 'Modifica template' : 'Nuovo template' }}</h1>
</div>

@if($template->exists)
  <div class="callout" style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;padding:.75rem 1rem;border-radius:8px;font-size:.85rem;margin-bottom:1.25rem;">
    Salvando verrà creata una <strong>nuova versione</strong> (v{{ ($template->versions()->max('version_number') ?? 0) + 1 }}). La versione attuale (v{{ $version->version_number ?? '—' }}) resta intatta e continua a essere usata dalle campagne che la referenziano già.
  </div>
@endif

<div class="admin-card" style="max-width:760px;">
  <form method="POST"
        action="{{ $template->exists ? route('admin.comunicazione.templates.update', $template) : route('admin.comunicazione.templates.store') }}">
    @csrf
    @if($template->exists) @method('PUT') @endif

    <div class="form-group">
      <label class="form-label" for="name">Nome *</label>
      <input class="form-input" type="text" id="name" name="name" required
             value="{{ old('name', $template->name) }}">
      @error('name') <div style="color:#991b1b;font-size:.78rem;margin-top:.25rem;">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
      <label class="form-label" for="description">Descrizione</label>
      <textarea class="form-textarea" id="description" name="description" rows="2">{{ old('description', $template->description) }}</textarea>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
      <div class="form-group">
        <label class="form-label" for="type">Tipo di campagna</label>
        <select class="form-select" id="type" name="type">
          <option value="">Tutti i tipi</option>
          @foreach($typeOptions as $value => $label)
            <option value="{{ $value }}" @selected(old('type', $template->type) === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      @if($template->exists)
        <div class="form-group">
          <label class="form-label" for="status">Stato *</label>
          <select class="form-select" id="status" name="status" required>
            @foreach(\App\Models\CommunicationTemplate::statusOptions() as $value => $label)
              <option value="{{ $value }}" @selected(old('status', $template->status) === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
      @endif
    </div>

    <h3>Contenuto {{ $template->exists ? '(nuova versione)' : '' }}</h3>

    <div class="form-group">
      <label class="form-label" for="subject">Oggetto email *</label>
      <input class="form-input" type="text" id="subject" name="subject" required
             value="{{ old('subject', $version->subject) }}">
      @error('subject') <div style="color:#991b1b;font-size:.78rem;margin-top:.25rem;">{{ $message }}</div> @enderror
    </div>

    <div class="form-group">
      <label class="form-label" for="preheader">Preheader</label>
      <input class="form-input" type="text" id="preheader" name="preheader"
             value="{{ old('preheader', $version->preheader) }}">
    </div>

    <div class="form-group">
      <label class="form-label" for="body">Corpo</label>
      <textarea class="form-textarea" id="body" name="body" rows="10">{{ old('body', $version->content['body'] ?? '') }}</textarea>
      <div style="font-size:.76rem;color:#9ca3af;margin-top:.25rem;">Testo semplice: l'editor a blocchi arriva in un blocco successivo.</div>
    </div>

    <div style="display:flex;gap:.6rem;margin-top:1rem;">
      <button class="btn btn--primary" type="submit">{{ $template->exists ? 'Salva come nuova versione' : 'Crea template' }}</button>
      <a href="{{ $template->exists ? route('admin.comunicazione.templates.show', $template) : route('admin.comunicazione.templates.index') }}" class="btn btn--secondary">Annulla</a>
    </div>
  </form>
</div>

@endsection
