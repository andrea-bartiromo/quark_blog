@extends('layouts.admin')
@section('title', 'Invio di test — '.$campaign->title)
@section('content')

<div class="admin-topbar">
  <div>
    <a href="{{ route('admin.comunicazione.campaigns.show', $campaign) }}" style="font-size:.8rem;color:#6b7280;text-decoration:none;">← {{ $campaign->title }}</a>
    <h1 class="admin-page-title" style="margin-top:.25rem;">🧪 Invio di test</h1>
  </div>
</div>

<p style="color:#9ca3af;font-size:.85rem;margin-top:-.75rem;margin-bottom:1.25rem;max-width:640px;">
  Invia <strong>una sola email</strong> a <strong>un solo iscritto confermato</strong> esplicitamente scelto qui sotto — mai alla lista destinatari della campagna. Non è un invio della campagna: nessuna riga di <code>comm_sends</code> viene creata o modificata, e la campagna non risulta mai "inviata" a causa di questa azione.
</p>

@if($testSendEnabled)
  <div class="admin-card" style="max-width:640px;margin-bottom:1.25rem;background:#fffbeb;border:1px solid #fde68a;">
    <strong style="color:#92400e;">⚠️ Invio reale abilitato.</strong>
    <span style="color:#92400e;font-size:.85rem;">Premendo "Invia email di test" verrà spedita un'email vera, tramite il transport configurato in <code>.env</code>, all'indirizzo dell'iscritto selezionato.</span>
  </div>
@else
  <div class="admin-card" style="max-width:640px;margin-bottom:1.25rem;background:#f3f4f6;">
    <strong>Invio di test disabilitato.</strong>
    <span style="color:#6b7280;font-size:.85rem;">Impostare <code>COMMUNICATION_REAL_SEND_ENABLED=true</code> nell'ambiente per abilitarlo — una decisione deliberata, non il comportamento di default.</span>
  </div>
@endif

@if(! $canTestSend)
  <div class="admin-card" style="max-width:640px;margin-bottom:1.25rem;">
    <h3 style="margin-top:0;font-size:.9rem;">Non ancora disponibile</h3>
    <ul style="margin:0;padding-left:1.2rem;color:#6b7280;font-size:.85rem;">
      @foreach($testSendBlockingReasons as $reason)
        <li>{{ $reason }}</li>
      @endforeach
    </ul>
  </div>
@endif

<div class="admin-card" style="max-width:640px;margin-bottom:1.25rem;">
  <h3 style="margin-top:0;font-size:.95rem;">Destinatario dell'invio di test</h3>

  <form method="GET" action="{{ route('admin.comunicazione.campaigns.test-send.form', $campaign) }}" role="search" style="display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap;margin-bottom:.85rem;">
    <div>
      <label for="recipient-search" style="display:block;font-size:.78rem;color:#6b7280;margin-bottom:.25rem;">Cerca per email</label>
      <input type="search" name="q" id="recipient-search" value="{{ $recipientQuery }}"
             placeholder="es. mario@" maxlength="190" autocomplete="off"
             style="padding:.45rem .65rem;border:1px solid #e5e7eb;border-radius:.5rem;min-width:220px;">
    </div>
    <button type="submit" class="btn btn--secondary">Cerca</button>
  </form>

  @if($recipientOptions->isEmpty())
    <p style="color:#9ca3af;font-size:.85rem;">
      @if($recipientQuery !== '')
        Nessun iscritto confermato trovato per «{{ $recipientQuery }}».
      @else
        Nessun iscritto confermato disponibile: un invio di test richiede almeno un iscritto reale.
      @endif
    </p>
  @else
    <form method="POST" action="{{ route('admin.comunicazione.campaigns.test-send', $campaign) }}"
          onsubmit="return confirm('Inviare un\'email di test reale a questo iscritto?')">
      @csrf
      <label for="subscriber_id" style="display:block;font-size:.8rem;color:#6b7280;margin-bottom:.35rem;">Invia a</label>
      <select name="subscriber_id" id="subscriber_id" required style="padding:.45rem .65rem;border:1px solid #e5e7eb;border-radius:.5rem;min-width:260px;">
        @foreach($recipientOptions as $option)
          <option value="{{ $option->id }}">{{ $option->email }}</option>
        @endforeach
      </select>
      <div style="margin-top:1rem;">
        <button type="submit" class="btn btn--primary" @disabled(! $canTestSend)>🧪 Invia email di test</button>
      </div>
    </form>

    @if($recipientOptions->hasPages())
      <nav aria-label="Pagine risultati destinatari" style="margin-top:1rem;display:flex;gap:.75rem;align-items:center;font-size:.8rem;">
        @if($recipientOptions->onFirstPage())
          <span style="color:#d1d5db;">← Precedente</span>
        @else
          <a href="{{ $recipientOptions->previousPageUrl() }}" style="color:#0d9488;">← Precedente</a>
        @endif
        <span style="color:#6b7280;">Pagina {{ $recipientOptions->currentPage() }} di {{ $recipientOptions->lastPage() }}</span>
        @if($recipientOptions->hasMorePages())
          <a href="{{ $recipientOptions->nextPageUrl() }}" style="color:#0d9488;">Successiva →</a>
        @else
          <span style="color:#d1d5db;">Successiva →</span>
        @endif
      </nav>
    @endif
  @endif
</div>

@if($errors->any())
  <div class="admin-card" style="max-width:640px;background:#fef2f2;border:1px solid #fecaca;">
    <ul style="margin:0;padding-left:1.2rem;color:#991b1b;font-size:.85rem;">
      @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

@endsection
