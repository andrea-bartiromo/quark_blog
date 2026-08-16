@extends('layouts.admin')
@section('title', 'Anteprima — '.$campaign->title)
@section('content')

<div class="admin-topbar">
  <div>
    <a href="{{ route('admin.comunicazione.campaigns.show', $campaign) }}" style="font-size:.8rem;color:#6b7280;text-decoration:none;">← {{ $campaign->title }}</a>
    <h1 class="admin-page-title" style="margin-top:.25rem;">Anteprima campagna</h1>
  </div>
</div>

<p style="color:#9ca3af;font-size:.85rem;margin-top:-.75rem;margin-bottom:1.5rem;">
  Solo rendering nel browser: questa pagina non invia email, non mette in coda, non crea consegne e non modifica campagna o iscritti — anche riaperta più volte.
</p>

<div class="admin-card" style="max-width:640px;margin-bottom:1.25rem;">
  <h3 style="margin-top:0;font-size:.95rem;">Destinatario anteprima</h3>

  @if($recipientOptions->isEmpty())
    <p style="color:#9ca3af;font-size:.85rem;">
      Nessun iscritto confermato disponibile. L'anteprima sotto mostra la struttura dell'email con un destinatario segnaposto.
    </p>
  @else
    <form method="GET" action="{{ route('admin.comunicazione.campaigns.preview', $campaign) }}" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
      <label for="subscriber_id" style="font-size:.8rem;color:#6b7280;">Mostra come ricevuto da</label>
      <select name="subscriber_id" id="subscriber_id" onchange="this.form.submit()" style="padding:.4rem .6rem;border:1px solid #e5e7eb;border-radius:.5rem;">
        @foreach($recipientOptions as $option)
          <option value="{{ $option->id }}" @selected($selectedSubscriberId === $option->id)>{{ $option->email }}</option>
        @endforeach
      </select>
      <noscript><button type="submit" class="btn btn--secondary">Aggiorna</button></noscript>
    </form>
    <p style="color:#9ca3af;font-size:.78rem;margin:.5rem 0 0;">
      Elenco limitato agli ultimi {{ $recipientOptions->count() }} iscritti confermati (per campagne con più iscritti, non è ancora una ricerca).
    </p>
  @endif
</div>

<div class="admin-card" style="max-width:640px;margin-bottom:1.25rem;">
  <div style="border-bottom:1px solid #f1f5f9;padding-bottom:1rem;margin-bottom:1rem;">
    <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Oggetto</div>
    <div style="font-weight:700;font-size:1.05rem;margin-top:.25rem;">{{ $rendering->subject ?: '(nessun oggetto)' }}</div>
  </div>
  <div style="border-bottom:1px solid #f1f5f9;padding-bottom:1rem;margin-bottom:1rem;">
    <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Preheader</div>
    <div style="color:#6b7280;margin-top:.25rem;">{{ $rendering->preheader ?: '—' }}</div>
  </div>
  <div style="border-bottom:1px solid #f1f5f9;padding-bottom:1rem;margin-bottom:1rem;">
    <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Mittente</div>
    <div style="color:#6b7280;margin-top:.25rem;">
      @if($rendering->fromName || $rendering->fromEmail)
        {{ $rendering->fromName }} &lt;{{ $rendering->fromEmail }}&gt;
      @else
        Nessun mittente collegato a questa campagna.
      @endif
    </div>
  </div>
  <div>
    <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Destinatario</div>
    <div style="color:#6b7280;margin-top:.25rem;">
      @if($rendering->isPlaceholderRecipient)
        Nessuno (segnaposto strutturale)
      @else
        {{ $rendering->recipientEmail }}
      @endif
    </div>
  </div>
</div>

<div class="admin-card" style="max-width:640px;padding:0;overflow:hidden;">
  <h3 style="margin:0;padding:1rem 1.5rem;border-bottom:1px solid #f1f5f9;font-size:.95rem;">Rendering HTML reale</h3>
  <iframe
    title="Anteprima email"
    srcdoc="{{ $rendering->html }}"
    sandbox=""
    style="width:100%;height:520px;border:0;display:block;"
  ></iframe>
</div>

@if($campaign->template)
  <p style="color:#9ca3af;font-size:.8rem;max-width:640px;margin-top:1rem;">
    Collegata al template <a href="{{ route('admin.comunicazione.templates.show', $campaign->template) }}">{{ $campaign->template->name }}</a> —
    il contenuto sopra è quello proprio della campagna, non viene ricalcolato dal template.
  </p>
@endif

@endsection
