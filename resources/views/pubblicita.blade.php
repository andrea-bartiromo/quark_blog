@extends('layouts.app')

@section('title', 'Pubblicità — '.config('laboratorio.name'))
@section('description', 'Formati pubblicitari, tariffe e contatti per pubblicizzare il tuo prodotto o servizio su Il Laboratorio.')

@section('content')
<div class="container" style="padding-block:2.5rem;">

  <div style="max-width:700px;margin-bottom:3rem;">
    <hr style="border:none;border-top:3px solid var(--color-ink);margin:0 0 .5rem;">
    <h1 style="font-family:var(--font-display);font-size:clamp(1.8rem,4vw,2.6rem);font-weight:900;margin-bottom:.75rem;">
      Pubblicità
    </h1>
    <p style="font-size:1.05rem;color:var(--color-ink-soft);line-height:1.7;">
      Il Laboratorio raggiunge un pubblico qualificato di professionisti, ricercatori,
      studenti universitari e imprenditori interessati a scienza e tecnologia.
      Un contesto editoriale di qualità per comunicare il tuo brand.
    </p>
  </div>

  {{-- Dati audience --}}
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1.25rem;margin-bottom:3rem;">
    @foreach([
      ['15.000+', 'Lettori unici/mese'],
      ['3,2 min', 'Tempo medio di lettura'],
      ['68%', 'Utenti 25-45 anni'],
      ['54%', 'Laureati o post-laureati'],
    ] as [$val, $label])
    <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);
                padding:1.5rem;text-align:center;">
      <div style="font-family:var(--font-display);font-size:2rem;font-weight:900;color:var(--color-accent);line-height:1;">
        {{ $val }}
      </div>
      <div style="font-family:var(--font-ui);font-size:.72rem;font-weight:600;text-transform:uppercase;
                  letter-spacing:.08em;color:var(--color-ink-muted);margin-top:.35rem;">
        {{ $label }}
      </div>
    </div>
    @endforeach
  </div>

  {{-- Griglia formati --}}
  <h2 style="font-family:var(--font-ui);font-size:.78rem;font-weight:700;text-transform:uppercase;
             letter-spacing:.1em;margin-bottom:1.5rem;border-top:2px solid var(--color-ink);padding-top:.5rem;">
    Formati disponibili
  </h2>

  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.25rem;margin-bottom:3rem;">
    @php
    $formati = [
      ['Leaderboard', '728×90 px', 'Top of page, prima del contenuto. Alta visibilità.', 'Orizzontale', 'ad-leaderboard'],
      ['Billboard', '970×250 px', 'In-feed tra le sezioni. Formato premium ad alto impatto.', 'Orizzontale', 'ad-billboard'],
      ['Half Page', '300×600 px', 'Sidebar destra, sticky durante la lettura.', 'Verticale', 'ad-half-page'],
      ['Medium Rectangle', '300×250 px', 'Sidebar destra. Il formato più performante per il CTR.', 'Verticale', 'ad-medium-rect'],
      ['In-Article', '336×280 px', 'Inserito nel corpo dell\'articolo dopo il terzo paragrafo.', 'Verticale', 'ad-large-rect'],
      ['Popup Sponsor', '468×60 px', 'Visibile nel popup newsletter. Ottimo per la brand awareness.', 'Orizzontale', 'ad-banner'],
    ];
    @endphp

    @foreach($formati as [$nome, $dims, $desc, $tipo, $class])
    <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
      <div style="background:var(--color-paper-warm);padding:.85rem 1.25rem;border-bottom:1px solid var(--color-border);
                  display:flex;align-items:center;justify-content:space-between;">
        <div>
          <div style="font-family:var(--font-ui);font-size:.85rem;font-weight:700;color:var(--color-ink);">{{ $nome }}</div>
          <div style="font-family:var(--font-ui);font-size:.7rem;color:var(--color-ink-muted);">{{ $dims }}</div>
        </div>
        <span style="font-family:var(--font-ui);font-size:.65rem;font-weight:600;text-transform:uppercase;
                     letter-spacing:.08em;padding:.2rem .5rem;border-radius:2px;
                     background:{{ $tipo === 'Verticale' ? '#e8f0fe' : '#e8f8f0' }};
                     color:{{ $tipo === 'Verticale' ? '#1a56db' : '#0d7a3e' }};">
          {{ $tipo }}
        </span>
      </div>
      <div style="padding:1rem 1.25rem;">
        <p style="font-size:.85rem;color:var(--color-ink-soft);margin-bottom:1rem;line-height:1.5;">{{ $desc }}</p>
        {{-- Preview mascherina --}}
        <div style="display:flex;justify-content:center;margin-bottom:.5rem;">
          <div class="ad-slot {{ $class }}"
               data-label="{{ $dims }}"
               style="max-width:100%;max-height:80px;width:100%;"
               role="img" aria-label="Anteprima formato {{ $nome }}"></div>
        </div>
      </div>
    </div>
    @endforeach
  </div>

  {{-- Contatto commerciale --}}
  <div style="background:var(--color-ink);border-radius:var(--radius);padding:2.5rem;
              display:grid;grid-template-columns:1fr auto;gap:2rem;align-items:center;">
    <div>
      <h2 style="font-family:var(--font-display);font-size:1.4rem;font-weight:900;color:#fff;margin-bottom:.5rem;">
        Richiedi un preventivo
      </h2>
      <p style="font-size:.9rem;color:rgba(255,255,255,.7);line-height:1.6;">
        Contattaci per tariffe, pacchetti personalizzati e disponibilità degli spazi.
        Rispondiamo entro 24 ore lavorative.
      </p>
    </div>
    <div style="flex-shrink:0;">
      <a href="mailto:pubblicita@illaboratorio.it"
         class="btn btn--primary"
         style="white-space:nowrap;font-size:.85rem;">
        ✉ pubblicita@illaboratorio.it
      </a>
    </div>
  </div>

</div>
@endsection
