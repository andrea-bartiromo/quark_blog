@extends('layouts.app')
@section('title', 'Cookie Policy — Quark')
@section('description', 'Cookie policy di Quark. Quali cookie utilizziamo e come gestirli.')

@section('content')
<div class="public-page public-page--legal">
  <div class="container premium-static">

    <section class="public-hero public-hero--light public-hero--compact">
      <span class="public-hero__kicker">Legale</span>
      <h1>Cookie Policy</h1>
      <p>Informazioni sui cookie utilizzati da Quark e sulle modalità di gestione del consenso.</p>
      <div class="public-hero__meta">
        <span>Ultimo aggiornamento: {{ date('d/m/Y') }}</span>
        <span>Cookie tecnici e analitici</span>
      </div>
    </section>

    <section class="premium-static-section premium-copy-card">
      <p>
        Un cookie è un piccolo file di testo salvato nel browser quando visiti un sito web.
        Alcuni cookie sono necessari al funzionamento del sito, altri servono per statistiche,
        preferenze e miglioramento dell’esperienza utente.
      </p>
    </section>

    <section class="premium-static-section premium-copy-card">
      <h2>Cookie utilizzati</h2>

      <div class="premium-cookie-table">
        <div class="premium-cookie-table__head">
          <span>Nome</span>
          <span>Tipo</span>
          <span>Scopo</span>
          <span>Durata</span>
        </div>

        @foreach([
          ['quark_session', 'Tecnico', 'Sessione utente necessaria al funzionamento del sito', '2 ore'],
          ['XSRF-TOKEN', 'Tecnico', 'Protezione sicurezza dei form (CSRF)', '2 ore'],
          ['newsletter_dismissed', 'Funzionale', 'Ricorda la chiusura del popup newsletter', '7 giorni'],
          ['newsletter_subscribed', 'Funzionale', 'Ricorda lo stato di iscrizione newsletter', 'Persistente'],
          ['cookie_consent', 'Funzionale', 'Salva la scelta del banner cookie', '1 anno'],
          ['_ga', 'Analitico', 'Google Analytics con identificatore anonimo', '2 anni'],
          ['_ga_*', 'Analitico', 'Sessioni di misurazione Analytics', '2 anni'],
        ] as [$name, $type, $purpose, $duration])
          <div class="premium-cookie-row">
            <div class="premium-cookie-name">{{ $name }}</div>
            <div>
              <span class="premium-cookie-badge premium-cookie-badge--{{ strtolower($type) }}">
                {{ $type }}
              </span>
            </div>
            <div>{{ $purpose }}</div>
            <div>{{ $duration }}</div>
          </div>
        @endforeach
      </div>
    </section>

    <section class="premium-static-section premium-copy-card">
      <h2>Come gestire i cookie</h2>
      <p>
        Puoi accettare o rifiutare i cookie analitici tramite il banner mostrato alla prima visita.
        In alternativa puoi modificare le impostazioni direttamente dal browser.
      </p>

      <div class="premium-browser-list">
        @foreach([
          ['Chrome', 'Impostazioni → Privacy e sicurezza → Cookie e altri dati dei siti'],
          ['Firefox', 'Impostazioni → Privacy e sicurezza → Cookie e dati dei siti'],
          ['Safari', 'Preferenze → Privacy → Gestisci dati siti web'],
          ['Edge', 'Impostazioni → Cookie e autorizzazioni del sito'],
        ] as [$browser, $path])
          <div class="premium-browser-item">
            <strong>{{ $browser }}</strong>
            <span>{{ $path }}</span>
          </div>
        @endforeach
      </div>

      <p class="premium-note">
        Disabilitare i cookie tecnici potrebbe compromettere il corretto funzionamento del sito.
      </p>
    </section>

    <section class="premium-static-section premium-cta-band">
      <h2>Domande sui cookie?</h2>
      <p>Per chiarimenti sulle tecnologie utilizzate da Quark puoi contattarci direttamente.</p>
      <a class="premium-button" href="{{ route('contatti') }}">Contattaci</a>
    </section>

  </div>
</div>
@endsection
