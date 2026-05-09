@extends('layouts.app')
@section('title', 'Chi siamo — Quark')
@section('description', 'Quark è il blog italiano di divulgazione scientifica fondato da Andrea Bartiromo. Scopri la nostra storia, la filosofia editoriale e il protocollo di verifica.')

@section('content')
<div class="public-page public-page--about">
  <div class="container premium-static">

    <section class="public-hero public-hero--light public-hero--compact">
      <span class="public-hero__kicker">Il progetto</span>
      <h1>La scienza spiegata <em>come si deve</em></h1>
      <p>
        Quark è un magazine italiano di divulgazione scientifica che racconta fisica, biologia,
        tecnologia, spazio e ambiente in modo semplice, curioso e verificabile.
      </p>
      <div class="public-hero__meta">
        <span>Niente sensazionalismo</span>
        <span>Fonti verificabili</span>
        <span>Lettura rapida</span>
      </div>
    </section>

    <section class="premium-static-section premium-copy-card">
      <h2>Perché Quark?</h2>
      <p>
        Il quark è tra i mattoni fondamentali della materia. Abbiamo scelto questo nome perché
        crediamo che la scienza parta sempre dalle domande più piccole: perché il cielo è blu?
        Come funziona un vaccino? Cosa c’è dentro un buco nero?
      </p>
      <p>
        Quark nasce dalla convinzione che la divulgazione scientifica di qualità non debba essere
        per pochi. Ogni persona merita di capire la scienza che cambia il mondo, senza dover
        decifrare paper accademici o perdersi in spiegazioni infinite.
      </p>
      <p>
        Ogni articolo è pensato per essere letto in pochi minuti, capito al primo tentativo e
        ricordato il giorno dopo.
      </p>
    </section>

    <section class="premium-static-section">
      <div class="public-section-head">
        <div>
          <span>Filosofia editoriale</span>
          <h2>Il nostro modo di raccontare la scienza</h2>
        </div>
      </div>

      <div class="premium-principles-grid">
        @foreach([
          ['🔬', 'Fonti primarie', 'Ogni informazione viene ricondotta alla fonte originale: paper scientifici, comunicati ufficiali o dati istituzionali.'],
          ['💡', 'Semplicità senza banalità', 'Spieghiamo concetti complessi in modo accessibile, senza sacrificare precisione e contesto.'],
          ['🚫', 'Zero clickbait', 'I titoli descrivono l’articolo. Niente promesse esagerate, niente notizie decontestualizzate.'],
          ['⚡', 'Freschezza editoriale', 'Seguiamo le notizie scientifiche rilevanti con attenzione alla qualità, non alla corsa al primo lancio.'],
          ['🇮🇹', 'Prospettiva italiana', 'Raccontiamo la scienza guardando anche a ricerca italiana, impatto sul territorio e contesto europeo.'],
          ['✅', 'Trasparenza', 'Le fonti sono parte del racconto. Gli errori vengono corretti pubblicamente nella pagina Rettifiche.'],
        ] as [$icon, $title, $desc])
          <article class="premium-principle">
            <div class="premium-principle__icon">{{ $icon }}</div>
            <h3>{{ $title }}</h3>
            <p>{{ $desc }}</p>
          </article>
        @endforeach
      </div>
    </section>

    <section class="premium-static-section premium-copy-card">
      <h2>Il protocollo di verifica</h2>
      <p>
        Prima della pubblicazione, ogni articolo passa attraverso un processo editoriale pensato
        per ridurre errori, interpretazioni deboli e titoli fuorvianti.
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
    </section>

    <section id="fondatore" class="premium-static-section premium-copy-card">
      <div class="premium-founder-card">
        <div class="premium-founder-avatar">AB</div>
        <div>
          <h2>Il fondatore</h2>
          <h3>Andrea Bartiromo</h3>
          <small>Fondatore e Direttore</small>
          <p>
            Appassionato di scienza e comunicazione, Andrea ha fondato Quark con l’obiettivo
            di rendere la divulgazione scientifica più accessibile, più leggibile e più utile.
            L’idea alla base è semplice: capire il mondo attraverso la scienza rende le persone
            più libere.
          </p>
          <a class="premium-button" href="{{ route('contatti') }}">Scrivimi</a>
        </div>
      </div>
    </section>

    <section class="premium-static-section premium-cta-band">
      <h2>Hai una domanda o una segnalazione?</h2>
      <p>
        Scrivici per segnalare errori, proporre argomenti o raccontarci cosa vorresti leggere su Quark.
      </p>
      <a class="premium-button" href="{{ route('contatti') }}">Contattaci</a>
    </section>

  </div>
</div>
@endsection
