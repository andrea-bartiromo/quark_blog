@extends('layouts.app')

@section('title', "Termini d'uso — ".config('laboratorio.name'))
@section('description', "Termini e condizioni d'uso del sito Il Laboratorio.")

@section('content')
<div class="container" style="padding-block:2.5rem;max-width:780px;">

  <hr style="border:none;border-top:3px solid var(--color-ink);margin:0 0 .5rem;">
  <h1 style="font-family:var(--font-display);font-size:clamp(1.6rem,3vw,2.2rem);font-weight:900;margin-bottom:.5rem;">
    Termini d'uso
  </h1>
  <p style="font-family:var(--font-ui);font-size:.78rem;color:var(--color-ink-muted);margin-bottom:2rem;">
    Ultimo aggiornamento: {{ now()->translatedFormat('d F Y') }}
  </p>

  @php
  $termini = [
    ['Accettazione dei termini', 'Utilizzando questo sito accetti i presenti Termini d\'uso. Se non li accetti, ti chiediamo di non utilizzare il sito.'],
    ['Proprietà intellettuale', 'Tutti i contenuti pubblicati su Il Laboratorio — articoli, fotografie, grafici, logo — sono di proprietà esclusiva della testata o dei rispettivi autori e sono protetti dalle leggi sul diritto d\'autore. È vietata la riproduzione, anche parziale, senza esplicita autorizzazione scritta.'],
    ['Uso consentito', 'Puoi leggere, condividere sui social network e citare brevi estratti degli articoli, purché citi sempre la fonte con link all\'articolo originale. Non puoi copiare o ripubblicare articoli integrali su altri siti o pubblicazioni senza autorizzazione.'],
    ['Commenti degli utenti', 'I commenti pubblicati dagli utenti sono di esclusiva responsabilità dei loro autori. Ci riserviamo il diritto di rimuovere commenti offensivi, diffamatori, spam o che violino i diritti di terzi. L\'invio di un commento implica l\'accettazione della nostra Privacy Policy.'],
    ['Limitazione di responsabilità', 'I contenuti de Il Laboratorio sono a scopo puramente informativo. Non costituiscono consulenza medica, legale, finanziaria o di altro tipo. Non siamo responsabili per eventuali danni derivanti dall\'uso delle informazioni pubblicate.'],
    ['Link esterni', 'Il sito può contenere link a siti esterni. Non siamo responsabili per i contenuti di tali siti e il link non implica approvazione o endorsement.'],
    ['Modifiche ai termini', 'Ci riserviamo il diritto di modificare questi Termini in qualsiasi momento. Le modifiche entrano in vigore alla pubblicazione. L\'uso continuato del sito implica l\'accettazione dei nuovi termini.'],
    ['Legge applicabile', 'I presenti Termini sono regolati dalla legge italiana. Per qualsiasi controversia è competente il Foro di [Città].'],
  ];
  @endphp

  @foreach($termini as [$titolo, $testo])
  <div style="margin-bottom:1.75rem;">
    <h2 style="font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:var(--color-ink);
               border-top:1px solid var(--color-border);padding-top:1rem;margin-bottom:.5rem;">
      {{ $titolo }}
    </h2>
    <p style="font-size:.92rem;color:var(--color-ink-soft);line-height:1.75;">{{ $testo }}</p>
  </div>
  @endforeach

  <div style="background:var(--color-paper-warm);border-radius:var(--radius);padding:1.25rem;
              font-family:var(--font-ui);font-size:.84rem;color:var(--color-ink-soft);margin-top:2rem;">
    Per domande sui termini d'uso scrivi a
    <a href="mailto:redazione@illaboratorio.it" style="color:var(--color-accent);">redazione@illaboratorio.it</a>.
  </div>

</div>
@endsection
