@extends('layouts.app')
@section('title', 'Privacy Policy — Quark')
@section('description', 'Informativa sulla privacy di Quark. Come raccogliamo e utilizziamo i tuoi dati.')

@section('content')
<div class="container" style="padding-block:3rem;max-width:720px;">

  <div style="margin-bottom:2rem;">
    <div class="hero-eyebrow" style="margin-bottom:1rem;">Legale</div>
    <h1 style="font-family:var(--font-display);font-size:2rem;font-weight:900;
               color:var(--ink);letter-spacing:-.02em;margin-bottom:.5rem;">Privacy Policy</h1>
    <p style="font-size:.82rem;color:var(--ink-muted);">
      Ultimo aggiornamento: {{ date('d/m/Y') }} — Titolare: Andrea Bartiromo
    </p>
  </div>

  @php
  $sections = [
    ['Titolare del trattamento', 'Il titolare del trattamento dei dati personali è Andrea Bartiromo, raggiungibile tramite il form di contatto presente sul sito.'],
    ['Dati raccolti', "Raccogliamo i seguenti dati:\n\n• Email: quando ti iscrivi alla newsletter o invii un messaggio tramite il form di contatto.\n• Dati di navigazione: attraverso Google Analytics (IP anonimizzato, pagine visitate, tempo di permanenza). Questi dati non permettono di identificare l'utente direttamente.\n• Cookie: utilizziamo cookie tecnici necessari al funzionamento del sito e cookie analitici (Google Analytics) con il tuo consenso."],
    ['Finalità del trattamento', "I dati vengono utilizzati per:\n\n• Invio della newsletter settimanale (solo per gli iscritti che hanno confermato l'email).\n• Rispondere ai messaggi inviati tramite il form di contatto.\n• Analisi statistica anonima del traffico per migliorare il sito."],
    ['Base giuridica', 'Il trattamento si basa sul consenso espresso dell\'utente (art. 6, comma 1, lett. a del GDPR) per la newsletter e i form, e sul legittimo interesse del titolare per i dati analitici anonimi.'],
    ['Conservazione', 'I dati della newsletter vengono conservati fino alla disiscrizione. I dati dei form di contatto vengono eliminati dopo 12 mesi. I dati analitici anonimi vengono conservati per 26 mesi (impostazione predefinita Google Analytics).'],
    ['Diritti dell\'utente', "Hai diritto a:\n\n• Accedere ai tuoi dati personali.\n• Richiedere la rettifica o cancellazione.\n• Opporti al trattamento.\n• Revocare il consenso in qualsiasi momento.\n\nPer esercitare questi diritti, contattaci tramite il form di contatto."],
    ['Trasferimento dati', 'Google Analytics trasferisce dati negli USA. Google LLC aderisce al Data Privacy Framework EU-USA. Non cediamo i tuoi dati a terzi per finalità commerciali.'],
    ['Modifiche', 'Questa policy può essere aggiornata. Le modifiche sostanziali saranno comunicate via newsletter agli iscritti.'],
  ];
  @endphp

  @foreach($sections as $i => [$title, $text])
  <section style="margin-bottom:1.75rem;padding-bottom:1.75rem;
                  {{ !$loop->last ? 'border-bottom:1px solid var(--border);' : '' }}">
    <h2 style="font-size:1rem;font-weight:700;color:var(--ink);margin-bottom:.65rem;">
      {{ $i + 1 }}. {{ $title }}
    </h2>
    @foreach(explode("\n\n", $text) as $para)
    <p style="font-size:.875rem;color:var(--ink-soft);line-height:1.75;margin-bottom:.5rem;">
      {!! nl2br(e($para)) !!}
    </p>
    @endforeach
  </section>
  @endforeach

  <div style="background:var(--primary-light);border-radius:10px;padding:1rem 1.25rem;
              font-size:.82rem;color:var(--primary-dark);">
    Per qualsiasi domanda sulla privacy:
    <a href="{{ route('contatti') }}" style="color:var(--primary);font-weight:600;">
      contattaci tramite il form →
    </a>
  </div>

</div>
@endsection