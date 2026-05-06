@extends('layouts.app')
@section('title', 'Rettifiche — Quark')
@section('description', 'La politica di rettifica di Quark. Come correggere gli errori in modo trasparente.')

@section('content')
<div class="container" style="padding-block:3rem;max-width:720px;">

  <div style="margin-bottom:2rem;">
    <div class="hero-eyebrow" style="margin-bottom:1rem;">Trasparenza</div>
    <h1 style="font-family:var(--font-display);font-size:2rem;font-weight:900;
               color:var(--ink);letter-spacing:-.02em;margin-bottom:.75rem;">Rettifiche</h1>
    <p style="font-size:1rem;color:var(--ink-soft);line-height:1.7;">
      Quark si impegna a correggere gli errori in modo rapido e trasparente.
      La precisione scientifica è il fondamento del nostro lavoro.
    </p>
  </div>

  <hr style="border:none;border-top:1px solid var(--border);margin-bottom:2rem;">

  {{-- Politica --}}
  <section style="margin-bottom:2rem;">
    <h2 style="font-size:1.1rem;font-weight:700;color:var(--ink);margin-bottom:1rem;">
      La nostra politica
    </h2>
    @foreach([
      ['⚡', 'Rapidità', 'Gli errori fattuali vengono corretti entro 24 ore dalla segnalazione.'],
      ['👁', 'Trasparenza', 'Ogni correzione viene annotata nell\'articolo con data e natura dell\'errore. Non cancelliamo silenziosamente.'],
      ['📧', 'Comunicazione', 'Se l\'errore è significativo, gli iscritti alla newsletter vengono informati nella prossima edizione.'],
      ['🏆', 'Riconoscimento', 'Chi segnala un errore viene ringraziato nell\'articolo corretto, se lo desidera.'],
    ] as [$icon, $title, $desc])
    <div style="display:flex;gap:.85rem;margin-bottom:1rem;padding:.85rem;
                background:var(--paper-warm);border-radius:10px;">
      <span style="font-size:1.2rem;flex-shrink:0;">{{ $icon }}</span>
      <div>
        <div style="font-size:.875rem;font-weight:700;color:var(--ink);margin-bottom:.2rem;">{{ $title }}</div>
        <div style="font-size:.82rem;color:var(--ink-soft);line-height:1.55;">{{ $desc }}</div>
      </div>
    </div>
    @endforeach
  </section>

  {{-- Come segnalare --}}
  <section style="margin-bottom:2rem;background:white;border:1px solid var(--border);
                  border-radius:16px;padding:1.5rem;">
    <h2 style="font-size:1.1rem;font-weight:700;color:var(--ink);margin-bottom:.75rem;">
      Come segnalare un errore
    </h2>
    <p style="font-size:.875rem;color:var(--ink-soft);line-height:1.7;margin-bottom:1rem;">
      Utilizza il form di contatto specificando:
    </p>
    @foreach([
      'Il titolo o l\'URL dell\'articolo',
      'L\'informazione che ritieni errata',
      'La fonte che ritieni corretta (con link se possibile)',
    ] as $item)
    <div style="display:flex;gap:.5rem;font-size:.82rem;color:var(--ink-soft);margin-bottom:.35rem;">
      <span style="color:var(--primary);">→</span> {{ $item }}
    </div>
    @endforeach
    <div style="margin-top:1.25rem;">
      <a href="{{ route('contatti') }}"
         class="btn btn--primary" style="font-size:.82rem;">
        Segnala un errore
      </a>
    </div>
  </section>

  {{-- Storico rettifiche --}}
  <section>
    <h2 style="font-size:1.1rem;font-weight:700;color:var(--ink);margin-bottom:.75rem;">
      Storico rettifiche
    </h2>
    <div style="background:var(--paper-warm);border-radius:10px;padding:1.25rem;
                text-align:center;color:var(--ink-muted);font-size:.875rem;">
      Nessuna rettifica registrata al momento.
    </div>
  </section>

</div>
@endsection