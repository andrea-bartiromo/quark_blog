@extends('layouts.app')
@section('title', 'La redazione — Quark')
@section('description', 'Conosci la redazione di Quark. Giornalisti e divulgatori scientifici appassionati che raccontano la scienza ogni giorno.')

@section('content')
<div class="container" style="padding-block:3rem;max-width:800px;">

  <div style="margin-bottom:2.5rem;">
    <div class="hero-eyebrow" style="margin-bottom:1rem;">Le persone</div>
    <h1 style="font-family:var(--font-display);font-size:2.2rem;font-weight:900;
               color:var(--ink);letter-spacing:-.02em;margin-bottom:.75rem;">
      La redazione
    </h1>
    <p style="font-size:1rem;color:var(--ink-soft);line-height:1.7;max-width:580px;">
      Quark è un progetto indipendente. Ogni articolo è scritto, verificato e pubblicato
      con la stessa cura e passione per la scienza.
    </p>
  </div>

  <hr style="border:none;border-top:1px solid var(--border);margin-bottom:2.5rem;">

  {{-- Fondatore --}}
  <div style="margin-bottom:2rem;">
    <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;
                letter-spacing:.1em;color:var(--ink-muted);margin-bottom:1rem;">
      Direzione
    </div>
    <div style="background:white;border:1px solid var(--border);border-radius:16px;
                overflow:hidden;">
      <div style="background:linear-gradient(135deg,var(--primary),#0f766e);
                  padding:1.5rem;display:flex;align-items:center;gap:1.25rem;">
        <div style="width:72px;height:72px;border-radius:50%;
                    border:3px solid rgba(255,255,255,.3);
                    background:rgba(255,255,255,.15);
                    display:flex;align-items:center;justify-content:center;
                    font-size:1.6rem;font-weight:700;color:white;flex-shrink:0;">AB</div>
        <div>
          <div style="font-size:1.1rem;font-weight:700;color:white;">Andrea Bartiromo</div>
          <div style="font-size:.72rem;color:rgba(255,255,255,.75);text-transform:uppercase;
                      letter-spacing:.06em;margin-top:.2rem;">Fondatore e Direttore</div>
        </div>
      </div>
      <div style="padding:1.25rem 1.5rem;">
        <p style="font-size:.875rem;color:var(--ink-soft);line-height:1.65;margin-bottom:1rem;">
          Fondatore e direttore editoriale di Quark. Si occupa della selezione degli argomenti,
          della verifica delle fonti e della supervisione editoriale di ogni contenuto pubblicato.
          Appassionato di fisica, astronomia e tecnologia.
        </p>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
          <a href="{{ route('autore', \App\Models\User::where('role','editor')->first() ?? 1) }}"
             style="font-size:.75rem;color:var(--primary);padding:.25rem .65rem;
                    border:1px solid var(--primary-light);border-radius:20px;text-decoration:none;">
            Tutti gli articoli
          </a>
          <a href="{{ route('contatti') }}"
             style="font-size:.75rem;color:var(--ink-muted);padding:.25rem .65rem;
                    border:1px solid var(--border);border-radius:20px;text-decoration:none;">
            Contatta
          </a>
        </div>
      </div>
    </div>
  </div>

  {{-- Valori redazionali --}}
  <section style="background:var(--paper-warm);border-radius:16px;padding:2rem;margin-bottom:2rem;">
    <h2 style="font-family:var(--font-display);font-size:1.2rem;font-weight:700;
               color:var(--ink);margin-bottom:1rem;">I nostri impegni</h2>
    @foreach([
      ['✅', 'Verifica delle fonti', 'Ogni articolo è basato su fonti primarie verificabili.'],
      ['🔄', 'Correzioni trasparenti', 'Gli errori vengono corretti pubblicamente con nota visibile nell\'articolo.'],
      ['🚫', 'Nessun conflitto di interessi', 'Quark non riceve compensi per promuovere prodotti o posizioni scientifiche.'],
      ['📬', 'Accessibilità', 'Tutti i contenuti sono gratuiti e aperti a chiunque.'],
    ] as [$icon, $title, $desc])
    <div style="display:flex;gap:.75rem;margin-bottom:.85rem;align-items:flex-start;">
      <span style="font-size:1rem;flex-shrink:0;margin-top:2px;">{{ $icon }}</span>
      <div>
        <div style="font-size:.875rem;font-weight:600;color:var(--ink);">{{ $title }}</div>
        <div style="font-size:.8rem;color:var(--ink-muted);line-height:1.5;">{{ $desc }}</div>
      </div>
    </div>
    @endforeach
  </section>

  {{-- Collabora --}}
  <section style="border:1px solid var(--border);border-radius:16px;padding:1.5rem;text-align:center;">
    <div style="font-size:1.5rem;margin-bottom:.5rem;">✍️</div>
    <h2 style="font-family:var(--font-display);font-size:1.1rem;font-weight:700;
               color:var(--ink);margin-bottom:.5rem;">Vuoi scrivere per Quark?</h2>
    <p style="font-size:.85rem;color:var(--ink-muted);line-height:1.6;margin-bottom:1rem;">
      Siamo sempre alla ricerca di divulgatori appassionati. Se hai una formazione scientifica
      e la voglia di spiegare la scienza al grande pubblico, scrivici.
    </p>
    <a href="{{ route('contatti') }}"
       style="display:inline-block;background:var(--primary);color:white;
              padding:.55rem 1.25rem;border-radius:8px;text-decoration:none;
              font-weight:600;font-size:.85rem;">
      Scrivici
    </a>
  </section>

</div>
@endsection