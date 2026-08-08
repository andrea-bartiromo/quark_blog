@extends('layouts.admin')
@section('title', ($senderProfile->exists ? 'Modifica' : 'Nuovo').' mittente — Comunicazione')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">{{ $senderProfile->exists ? 'Modifica mittente' : 'Nuovo mittente' }}</h1>
</div>

<div class="admin-card" style="max-width:640px;">
  <form method="POST"
        action="{{ $senderProfile->exists ? route('admin.comunicazione.sender-profiles.update', $senderProfile) : route('admin.comunicazione.sender-profiles.store') }}">
    @csrf
    @if($senderProfile->exists) @method('PUT') @endif

    <div class="form-group">
      <label class="form-label" for="name">Nome *</label>
      <input class="form-input" type="text" id="name" name="name" required
             value="{{ old('name', $senderProfile->name) }}">
      <div style="font-size:.76rem;color:#9ca3af;margin-top:.25rem;">Etichetta interna, mai mostrata al destinatario.</div>
      @error('name') <div style="color:#991b1b;font-size:.78rem;margin-top:.25rem;">{{ $message }}</div> @enderror
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
      <div class="form-group">
        <label class="form-label" for="from_name">Nome mittente *</label>
        <input class="form-input" type="text" id="from_name" name="from_name" required
               value="{{ old('from_name', $senderProfile->from_name) }}">
        @error('from_name') <div style="color:#991b1b;font-size:.78rem;margin-top:.25rem;">{{ $message }}</div> @enderror
      </div>
      <div class="form-group">
        <label class="form-label" for="from_email">Indirizzo mittente *</label>
        <input class="form-input" type="email" id="from_email" name="from_email" required
               value="{{ old('from_email', $senderProfile->from_email) }}">
        @error('from_email') <div style="color:#991b1b;font-size:.78rem;margin-top:.25rem;">{{ $message }}</div> @enderror
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="reply_to">Rispondi a</label>
      <input class="form-input" type="email" id="reply_to" name="reply_to"
             value="{{ old('reply_to', $senderProfile->reply_to) }}">
      @error('reply_to') <div style="color:#991b1b;font-size:.78rem;margin-top:.25rem;">{{ $message }}</div> @enderror
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
      <div class="form-group">
        <label class="form-label" for="provider">Provider *</label>
        <select class="form-select" id="provider" name="provider" required>
          @foreach($providerOptions as $value => $label)
            <option value="{{ $value }}" @selected(old('provider', $senderProfile->provider ?: \App\Models\CommunicationSenderProfile::PROVIDER_SMTP) === $value)>{{ $label }}</option>
          @endforeach
        </select>
        <div style="font-size:.76rem;color:#9ca3af;margin-top:.25rem;">Nessuna credenziale qui: usa sempre la configurazione email già attiva sull'applicazione.</div>
      </div>
      @if($senderProfile->exists)
        <div class="form-group">
          <label class="form-label" for="status">Stato *</label>
          <select class="form-select" id="status" name="status" required>
            @foreach(\App\Models\CommunicationSenderProfile::statusOptions() as $value => $label)
              <option value="{{ $value }}" @selected(old('status', $senderProfile->status) === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
      @endif
    </div>

    <div class="form-group">
      <label style="display:flex;align-items:center;gap:.5rem;font-weight:500;">
        <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $senderProfile->is_default))>
        Predefinito
      </label>
      <div style="font-size:.76rem;color:#9ca3af;margin-top:.25rem;">Al più un mittente alla volta può essere il predefinito: selezionandolo qui, l'eventuale predefinito precedente viene disattivato automaticamente.</div>
    </div>

    <div style="display:flex;gap:.6rem;margin-top:1rem;">
      <button class="btn btn--primary" type="submit">{{ $senderProfile->exists ? 'Salva modifiche' : 'Crea mittente' }}</button>
      <a href="{{ $senderProfile->exists ? route('admin.comunicazione.sender-profiles.show', $senderProfile) : route('admin.comunicazione.sender-profiles.index') }}" class="btn btn--secondary">Annulla</a>
    </div>
  </form>
</div>

@endsection
