@extends('layouts.app')

@section('title', 'Rettifiche — '.config('laboratorio.name'))
@section('description', 'La politica de Il Laboratorio sulle correzioni e rettifiche degli articoli pubblicati.')

@section('content')
<div class="container" style="padding-block:2.5rem;max-width:780px;">

  <hr style="border:none;border-top:3px solid var(--color-ink);margin:0 0 .5rem;">
  <h1 style="font-family:var(--font-display);font-size:clamp(1.6rem,3vw,2.2rem);font-weight:900;margin-bottom:.75rem;">
    Rettifiche e correzioni
  </h1>
  <p style="font-size:1.05rem;color:var(--color-ink-soft);line-height:1.7;margin-bottom:2rem;">
    Il Laboratorio si impegna all'accuratezza. Quando sbagliamo, correggiamo
    pubblicamente e tempestivamente, senza cancellare la storia.
  </p>

  <div style="font-size:.92rem;color:var(--color-ink-soft);line-height:1.75;">

    <h2 style="font-family:var(--font-display);font-size:1.15rem;font-weight:700;color:var(--color-ink);
               border-top:1px solid var(--color-border);padding-top:1rem;margin:0 0 .6rem;">
      La nostra politica
    </h2>
    <ul style="padding-left:1.5em;list-style:disc;margin-bottom:1.5em;">
      <li style="margin-bottom:.5em;"><strong style="color:var(--color-ink);">Trasparenza totale:</strong> le correzioni vengono segnalate chiaramente nell'articolo con una nota in fondo, indicando cosa è stato modificato e quando.</li>
      <li style="margin-bottom:.5em;"><strong style="color:var(--color-ink);">Nessuna cancellazione silenziosa:</strong> non modifichiamo articoli senza segnalarlo ai lettori.</li>
      <li style="margin-bottom:.5em;"><strong style="color:var(--color-ink);">Tempi rapidi:</strong> ci impegniamo a correggere gli errori entro 24 ore dalla segnalazione verificata.</li>
      <li style="margin-bottom:.5em;"><strong style="color:var(--color-ink);">Rettifica formale:</strong> per errori gravi su fatti o persone, pubblichiamo una rettifica come articolo separato.</li>
    </ul>

    <h2 style="font-family:var(--font-display);font-size:1.15rem;font-weight:700;color:var(--color-ink);
               border-top:1px solid var(--color-border);padding-top:1rem;margin:1.5rem 0 .6rem;">
      Come segnalare un errore
    </h2>
    <p style="margin-bottom:1em;">
      Se hai trovato un errore fattuale, un dato impreciso o un'attribuzione errata in uno dei
      nostri articoli, ti chiediamo di segnalarcelo. Leggiamo ogni segnalazione.
    </p>
    <p style="margin-bottom:1.5em;">
      Scrivi a <a href="mailto:rettifiche@illaboratorio.it" style="color:var(--color-accent);font-weight:600;">rettifiche@illaboratorio.it</a>
      indicando:
    </p>
    <ul style="padding-left:1.5em;list-style:decimal;margin-bottom:2em;">
      <li style="margin-bottom:.4em;">Il link o il titolo dell'articolo</li>
      <li style="margin-bottom:.4em;">L'affermazione che ritieni errata</li>
      <li style="margin-bottom:.4em;">Le fonti che supportano la correzione</li>
    </ul>

    <div style="background:var(--color-paper-warm);border-left:4px solid var(--color-accent);
                padding:1.25rem;border-radius:0 var(--radius) var(--radius) 0;font-size:.9rem;">
      <strong style="color:var(--color-ink);display:block;margin-bottom:.35rem;">Diritto di rettifica (L. 8 febbraio 1948, n. 47)</strong>
      Chiunque si ritenga diffamato da notizie pubblicate su questa testata ha diritto a richiedere
      la pubblicazione di una rettifica ai sensi dell'art. 8 della Legge sulla Stampa.
      Le richieste vanno inviate a
      <a href="mailto:rettifiche@illaboratorio.it" style="color:var(--color-accent);">rettifiche@illaboratorio.it</a>.
    </div>

  </div>
</div>
@endsection
