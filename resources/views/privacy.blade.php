@extends('layouts.app')

@section('title', 'Privacy Policy — '.config('laboratorio.name'))
@section('description', 'Informativa sul trattamento dei dati personali de Il Laboratorio, ai sensi del GDPR UE 2016/679.')

@section('content')
<div class="container" style="padding-block:2.5rem;max-width:780px;">

  <hr style="border:none;border-top:3px solid var(--color-ink);margin:0 0 .5rem;">
  <h1 style="font-family:var(--font-display);font-size:clamp(1.6rem,3vw,2.2rem);font-weight:900;margin-bottom:.5rem;">
    Privacy Policy
  </h1>
  <p style="font-family:var(--font-ui);font-size:.78rem;color:var(--color-ink-muted);margin-bottom:2rem;">
    Ultimo aggiornamento: {{ now()->translatedFormat('d F Y') }} — ai sensi del Regolamento UE 2016/679 (GDPR)
  </p>

  @php
  $sections = [
    ['Titolare del trattamento', '
      Il titolare del trattamento dei dati è <strong>Il Laboratorio</strong>, con sede in [Indirizzo],
      [CAP] [Città], P.IVA IT00000000000. Contatto email:
      <a href="mailto:privacy@illaboratorio.it" style="color:var(--color-accent);">privacy@illaboratorio.it</a>.
    '],
    ['Dati raccolti e finalità', '
      Raccogliamo i seguenti dati personali:
      <ul style="padding-left:1.5em;list-style:disc;margin-top:.5em;">
        <li style="margin-bottom:.4em;"><strong>Newsletter:</strong> indirizzo email, fornito volontariamente. Utilizzo: invio della newsletter settimanale.</li>
        <li style="margin-bottom:.4em;"><strong>Commenti:</strong> nome, email e testo del commento. Utilizzo: moderazione e pubblicazione dei commenti.</li>
        <li style="margin-bottom:.4em;"><strong>Form contatti:</strong> nome, email e messaggio. Utilizzo: risposta alla richiesta.</li>
        <li style="margin-bottom:.4em;"><strong>Dati di navigazione:</strong> indirizzo IP, browser, pagine visitate (tramite Google Analytics, solo previo consenso). Utilizzo: analisi statistica anonima.</li>
      </ul>
    '],
    ['Base giuridica', '
      Il trattamento è fondato su:
      <ul style="padding-left:1.5em;list-style:disc;margin-top:.5em;">
        <li style="margin-bottom:.4em;"><strong>Consenso</strong> (art. 6.1.a GDPR): per newsletter, commenti e cookie di profilazione.</li>
        <li style="margin-bottom:.4em;"><strong>Interesse legittimo</strong> (art. 6.1.f GDPR): per i dati di navigazione tecnici necessari al funzionamento del sito.</li>
      </ul>
    '],
    ['Conservazione dei dati', '
      I dati sono conservati per il tempo strettamente necessario alla finalità per cui sono stati raccolti:
      <ul style="padding-left:1.5em;list-style:disc;margin-top:.5em;">
        <li style="margin-bottom:.4em;">Email newsletter: fino alla cancellazione dell\'iscrizione.</li>
        <li style="margin-bottom:.4em;">Commenti: fino alla richiesta di rimozione o cancellazione dell\'articolo.</li>
        <li style="margin-bottom:.4em;">Form contatti: 12 mesi dalla ricezione.</li>
        <li style="margin-bottom:.4em;">Log di navigazione tecnici: 30 giorni.</li>
      </ul>
    '],
    ['Diritti dell\'interessato', '
      Ai sensi degli artt. 15-22 GDPR, hai il diritto di:
      accedere ai tuoi dati, rettificarli, cancellarli ("diritto all\'oblio"),
      limitarne il trattamento, riceverli in formato portabile, opporti al trattamento.
      Per esercitare i tuoi diritti scrivi a
      <a href="mailto:privacy@illaboratorio.it" style="color:var(--color-accent);">privacy@illaboratorio.it</a>.
      Hai inoltre diritto di proporre reclamo al Garante per la Protezione dei Dati Personali
      (<a href="https://www.garanteprivacy.it" target="_blank" rel="noopener" style="color:var(--color-accent);">garanteprivacy.it</a>).
    '],
    ['Cookie', '
      Per informazioni dettagliate sull\'utilizzo dei cookie consulta la nostra
      <a href="' . route('cookie') . '" style="color:var(--color-accent);">Cookie Policy</a>.
    '],
    ['Modifiche alla Privacy Policy', '
      Ci riserviamo di aggiornare questa informativa. Le modifiche sostanziali saranno
      comunicate tramite newsletter e con apposito avviso sul sito.
    '],
  ];
  @endphp

  @foreach($sections as [$titolo, $contenuto])
  <div style="margin-bottom:2rem;">
    <h2 style="font-family:var(--font-display);font-size:1.15rem;font-weight:700;color:var(--color-ink);
               border-top:1px solid var(--color-border);padding-top:1rem;margin-bottom:.6rem;">
      {{ $titolo }}
    </h2>
    <div style="font-size:.92rem;color:var(--color-ink-soft);line-height:1.75;">
      {{ $contenuto }}
    </div>
  </div>
  @endforeach

</div>
@endsection
