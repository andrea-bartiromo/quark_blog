@extends('layouts.app')
@section('title', 'Termini d\'uso — Quark')
@section('description', 'Termini e condizioni d\'uso del sito Quark.')

@section('content')
<div class="container" style="padding-block:3rem;max-width:720px;">

  <div style="margin-bottom:2rem;">
    <div class="hero-eyebrow" style="margin-bottom:1rem;">Legale</div>
    <h1 style="font-family:var(--font-display);font-size:2rem;font-weight:900;
               color:var(--ink);letter-spacing:-.02em;margin-bottom:.5rem;">Termini d'uso</h1>
    <p style="font-size:.82rem;color:var(--ink-muted);">
      Ultimo aggiornamento: {{ date('d/m/Y') }}
    </p>
  </div>

  @php
  $sections = [
    ['Accettazione', 'Utilizzando il sito quark.it accetti i presenti termini d\'uso. Se non li accetti, ti preghiamo di non utilizzare il sito.'],
    ['Contenuti e proprietà intellettuale', 'Tutti i contenuti pubblicati su Quark — testi, immagini, grafica e codice — sono di proprietà di Andrea Bartiromo e sono protetti dal diritto d\'autore. È vietata la riproduzione parziale o totale senza autorizzazione scritta, salvo citazioni brevi con attribuzione e link alla fonte originale.'],
    ['Uso consentito', "Puoi:\n\n• Leggere e condividere gli articoli tramite i pulsanti social presenti sul sito.\n• Citare brevi estratti (max 3 frasi) con link all\'articolo originale.\n• Iscriverti alla newsletter e disiscriverti in qualsiasi momento.\n\nNon puoi:\n\n• Ripubblicare gli articoli integralmente su altri siti.\n• Utilizzare i contenuti per addestrare modelli di intelligenza artificiale.\n• Scraping automatico del sito."],
    ['Accuratezza dei contenuti', 'Quark si impegna a pubblicare informazioni accurate e verificate. Tuttavia, la scienza è in continua evoluzione: alcune informazioni potrebbero risultare superate. Segnala eventuali errori tramite il form di contatto.'],
    ['Link esterni', 'Il sito può contenere link a fonti esterne. Quark non è responsabile del contenuto di siti terzi.'],
    ['Limitazione di responsabilità', 'Quark fornisce i contenuti "così come sono", senza garanzie di alcun tipo. Non siamo responsabili per danni diretti o indiretti derivanti dall\'uso dei contenuti.'],
    ['Modifiche', 'Ci riserviamo il diritto di modificare questi termini in qualsiasi momento. Le modifiche entrano in vigore dalla pubblicazione sul sito.'],
    ['Legge applicabile', 'I presenti termini sono regolati dalla legge italiana. Per qualsiasi controversia è competente il Foro di residenza del titolare.'],
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

</div>
@endsection