@extends('layouts.app')
@section('title', 'Metodologia — Kairus')
@section('description', 'Come Kairus sceglie cosa raccontare, verifica le fonti e distingue consenso scientifico e incertezza.')
@section('canonical', route('metodologia'))

@section('content')
<div class="public-page public-page--about">
  <div class="container premium-static">

    <section class="public-hero public-hero--light public-hero--compact">
      <span class="public-hero__kicker">Metodo</span>
      <h1>Come lavoriamo</h1>
      <p>
        Kairus racconta la scienza partendo sempre da una fonte identificabile. Questa pagina spiega,
        in modo verificabile, come scegliamo cosa raccontare, come trattiamo le fonti primarie e dove
        passa il confine tra automazione e responsabilità editoriale umana.
      </p>
      <div class="public-hero__meta">
        <span>Fonti verificabili</span>
        <span>Nessun ghostwriting invisibile</span>
        <span>Responsabilità editoriale umana</span>
      </div>
    </section>

    <section class="premium-static-section premium-copy-card">
      <h2>Come scegliamo cosa raccontare</h2>
      <p>
        Diamo priorità a sviluppi scientifici recenti supportati da una fonte primaria identificabile —
        un paper, un comunicato ufficiale, un dato istituzionale. Non rincorriamo il primo lancio:
        preferiamo raccontare una notizia un'ora dopo se questo significa raccontarla bene.
      </p>
    </section>

    <section class="premium-static-section">
      <div class="public-section-head">
        <div>
          <span>Verifica</span>
          <h2>Fonti primarie e protocollo di verifica</h2>
        </div>
      </div>
      <p>
        Il processo in sintesi è già descritto nella pagina <a href="{{ route('chi-siamo') }}">Chi siamo</a>:
        qui lo dettagliamo.
      </p>

      <div class="premium-steps">
        @foreach([
          ['1', 'Fonte primaria', 'Identifichiamo il paper originale, il comunicato ufficiale o i dati istituzionali alla base della notizia.'],
          ['2', 'Verifica incrociata', 'Confrontiamo le informazioni con fonti indipendenti e contesto scientifico affidabile.'],
          ['3', 'Contestualizzazione', 'Inseriamo la notizia nel quadro corretto, senza sopravvalutarla né minimizzarla.'],
          ['4', 'Revisione editoriale', 'Il contenuto viene riletto prima della pubblicazione per chiarezza, accuratezza e tono.'],
        ] as [$num, $title, $desc])
          <div class="premium-step">
            <span class="premium-step__num">{{ $num }}</span>
            <div>
              <strong>{{ $title }}</strong>
              <span>{{ $desc }}</span>
            </div>
          </div>
        @endforeach
      </div>

      <p style="margin-top:1.5rem;">
        Quando disponibile, la fonte primaria citata è visibile pubblicamente nel riquadro
        "Fonti primarie" in fondo al testo dell'articolo.
      </p>
    </section>

    <section class="premium-static-section premium-copy-card">
      <h2>Consenso scientifico e incertezza</h2>
      <p>
        Distinguiamo esplicitamente, quando rilevante, tra ciò su cui la comunità scientifica converge
        e ciò che resta oggetto di studio o dibattito aperto. Non presentiamo un'ipotesi preliminare
        come un fatto stabilito.
      </p>
    </section>

    <section class="premium-static-section premium-copy-card">
      <h2>Il ruolo dell'automazione</h2>
      <p>
        Alcuni strumenti automatizzati assistono la redazione — organizzazione dei contenuti,
        suggerimenti, controlli di qualità editoriale interni. La decisione su cosa pubblicare, come
        verificarlo e come correggerlo resta sempre umana. Nessun articolo viene pubblicato senza
        revisione editoriale.
      </p>
    </section>

    <section class="premium-static-section premium-copy-card">
      <h2>Indipendenza editoriale</h2>
      <p>
        Kairus è un progetto indipendente. Non esistono oggi sponsorizzazioni o contenuti commerciali
        che influenzano le scelte editoriali. Se in futuro dovessero essere introdotte forme di
        sostegno o collaborazione, verranno sempre segnalate in modo chiaro e distinte dal contenuto
        editoriale.
      </p>
    </section>

    <section class="premium-static-section premium-cta-band">
      <h2>Trovi un errore?</h2>
      <p>
        Scopri come gestiamo correzioni e aggiornamenti, oppure segnalacene uno.
      </p>
      <a class="premium-button" href="{{ route('rettifiche') }}">Come correggiamo gli errori</a>
    </section>

  </div>
</div>
@endsection
