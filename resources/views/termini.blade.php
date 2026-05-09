@extends('layouts.app')
@section('title', 'Termini d\'uso — Quark')
@section('description', 'Termini e condizioni d\'uso del sito Quark.')

@section('content')
<div class="public-page public-page--legal">
  <div class="container premium-static">

    <section class="public-hero public-hero--light public-hero--compact">
      <span class="public-hero__kicker">Legale</span>
      <h1>Termini d’uso</h1>
      <p>Le condizioni che regolano l’utilizzo dei contenuti e dei servizi disponibili su Quark.</p>
      <div class="public-hero__meta">
        <span>Ultimo aggiornamento: {{ date('d/m/Y') }}</span>
      </div>
    </section>

    @php
      $sections = [
        ['Accettazione', 'Utilizzando il sito Quark accetti i presenti termini d’uso. Se non li accetti, ti preghiamo di non utilizzare il sito.'],
        ['Contenuti e proprietà intellettuale', 'Tutti i contenuti pubblicati su Quark — testi, immagini, grafica e codice — sono di proprietà di Andrea Bartiromo e sono protetti dal diritto d’autore. È vietata la riproduzione parziale o totale senza autorizzazione scritta, salvo citazioni brevi con attribuzione e link alla fonte originale.'],
        ['Uso consentito', "Puoi:\n\n• Leggere e condividere gli articoli tramite i pulsanti social presenti sul sito.\n• Citare brevi estratti con link all’articolo originale.\n• Iscriverti alla newsletter e disiscriverti in qualsiasi momento.\n\nNon puoi:\n\n• Ripubblicare gli articoli integralmente su altri siti.\n• Utilizzare i contenuti per addestrare modelli di intelligenza artificiale.\n• Effettuare scraping automatico del sito."],
        ['Accuratezza dei contenuti', 'Quark si impegna a pubblicare informazioni accurate e verificate. Tuttavia, la scienza è in continua evoluzione: alcune informazioni potrebbero risultare superate. Segnala eventuali errori tramite il form di contatto.'],
        ['Link esterni', 'Il sito può contenere link a fonti esterne. Quark non è responsabile del contenuto di siti terzi.'],
        ['Limitazione di responsabilità', 'Quark fornisce i contenuti così come sono, senza garanzie di alcun tipo. Non siamo responsabili per danni diretti o indiretti derivanti dall’uso dei contenuti.'],
        ['Modifiche', 'Ci riserviamo il diritto di modificare questi termini in qualsiasi momento. Le modifiche entrano in vigore dalla pubblicazione sul sito.'],
        ['Legge applicabile', 'I presenti termini sono regolati dalla legge italiana. Per qualsiasi controversia è competente il Foro di residenza del titolare.'],
      ];
    @endphp

    <section class="premium-static-section premium-copy-card">
      <h2>Condizioni di utilizzo</h2>

      <div class="premium-legal-list">
        @foreach($sections as [$title, $text])
          <article class="premium-legal-item">
            <h3>{{ $title }}</h3>
            @foreach(explode("\n\n", $text) as $para)
              <p>{!! nl2br(e($para)) !!}</p>
            @endforeach
          </article>
        @endforeach
      </div>
    </section>

    <section class="premium-static-section premium-cta-band">
      <h2>Dubbi sui termini?</h2>
      <p>Per chiarimenti sull’utilizzo dei contenuti o sulle condizioni del sito puoi contattarci.</p>
      <a class="premium-button" href="{{ route('contatti') }}">Contattaci</a>
    </section>

  </div>
</div>
@endsection
