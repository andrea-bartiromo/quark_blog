@extends('layouts.app')
@section('title', 'Privacy Policy — Quark')
@section('description', 'Informativa sulla privacy di Quark. Come raccogliamo e utilizziamo i tuoi dati.')

@section('content')
<div class="public-page public-page--legal">
  <div class="container premium-static">

    <section class="public-hero public-hero--light public-hero--compact">
      <span class="public-hero__kicker">Legale</span>
      <h1>Privacy Policy</h1>
      <p>Come raccogliamo, utilizziamo e proteggiamo i dati personali su Quark.</p>
      <div class="public-hero__meta">
        <span>Ultimo aggiornamento: {{ date('d/m/Y') }}</span>
        <span>Titolare: Andrea Bartiromo</span>
      </div>
    </section>

    @php
      $sections = [
        ['Titolare del trattamento', 'Il titolare del trattamento dei dati personali è Andrea Bartiromo, raggiungibile tramite il form di contatto presente sul sito.'],
        ['Dati raccolti', "Raccogliamo i seguenti dati:\n\n• Email: quando ti iscrivi alla newsletter o invii un messaggio tramite il form di contatto.\n• Dati di navigazione: attraverso Google Analytics (IP anonimizzato, pagine visitate, tempo di permanenza). Questi dati non permettono di identificare l'utente direttamente.\n• Cookie: utilizziamo cookie tecnici necessari al funzionamento del sito e cookie analitici (Google Analytics) con il tuo consenso."],
        ['Finalità del trattamento', "I dati vengono utilizzati per:\n\n• Invio della newsletter settimanale, solo per gli iscritti che hanno confermato l'email.\n• Rispondere ai messaggi inviati tramite il form di contatto.\n• Analisi statistica anonima del traffico per migliorare il sito."],
        ['Base giuridica', 'Il trattamento si basa sul consenso espresso dell\'utente (art. 6, comma 1, lett. a del GDPR) per newsletter e form, e sul legittimo interesse del titolare per i dati analitici anonimi.'],
        ['Conservazione', 'I dati della newsletter vengono conservati fino alla disiscrizione. I dati dei form di contatto vengono eliminati dopo 12 mesi. I dati analitici anonimi vengono conservati per 26 mesi, secondo le impostazioni di Google Analytics.'],
        ['Diritti dell\'utente', "Hai diritto ad accedere ai tuoi dati personali, richiederne rettifica o cancellazione, opporti al trattamento e revocare il consenso in qualsiasi momento. Per esercitare questi diritti, contattaci tramite il form di contatto."],
        ['Trasferimento dati', 'Google Analytics può trasferire dati negli Stati Uniti. Google LLC aderisce al Data Privacy Framework EU-USA. Non cediamo i tuoi dati a terzi per finalità commerciali.'],
        ['Modifiche', 'Questa policy può essere aggiornata. Le modifiche sostanziali saranno comunicate tramite i canali disponibili sul sito.'],
      ];
    @endphp

    <section class="premium-static-section premium-copy-card">
      <h2>Informativa sul trattamento dei dati</h2>
      <div class="premium-steps">
        @foreach($sections as $i => [$title, $text])
          <article class="premium-step">
            <span class="premium-step__num">{{ $i + 1 }}</span>
            <div>
              <strong>{{ $title }}</strong>
              @foreach(explode("\n\n", $text) as $para)
                <span>{!! nl2br(e($para)) !!}</span>
              @endforeach
            </div>
          </article>
        @endforeach
      </div>
    </section>

    <section class="premium-static-section premium-cta-band">
      <h2>Domande sulla privacy?</h2>
      <p>Per richieste sui dati personali o sull’esercizio dei diritti previsti dal GDPR puoi scriverci direttamente.</p>
      <a class="premium-button" href="{{ route('contatti') }}">Contattaci</a>
    </section>

  </div>
</div>
@endsection
