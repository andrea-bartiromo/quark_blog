@extends('layouts.app')
@section('title', 'Chi siamo — Quark')
@section('description', 'Quark è il blog italiano di divulgazione scientifica fondato da Andrea Bartiromo. Scopri la nostra storia, la filosofia editoriale e il protocollo di verifica.')

@section('content')
<div class="container" style="padding-block:3rem;max-width:800px;">

  {{-- Hero --}}
  <div style="margin-bottom:3rem;">
    <div class="hero-eyebrow" style="margin-bottom:1rem;">Il progetto</div>
    <h1 style="font-family:var(--font-display);font-size:2.4rem;font-weight:900;
               color:var(--ink);letter-spacing:-.02em;line-height:1.15;margin-bottom:1rem;">
      La scienza spiegata<br><em style="color:var(--primary);">come si deve</em>
    </h1>
    <p style="font-size:1.1rem;color:var(--ink-soft);line-height:1.7;max-width:620px;">
      Quark è un blog di divulgazione scientifica italiano che racconta fisica, biologia,
      tecnologia, spazio e ambiente in modo semplice, curioso e senza filtri.
      Niente sensazionalismo, niente fake news — solo scienza verificata.
    </p>
  </div>

  {{-- Separatore --}}
  <hr style="border:none;border-top:1px solid var(--border);margin-bottom:3rem;">

  {{-- Perché Quark --}}
  <section style="margin-bottom:3rem;">
    <h2 style="font-family:var(--font-display);font-size:1.5rem;font-weight:700;
               color:var(--ink);margin-bottom:1rem;">Perché Quark?</h2>
    <p style="color:var(--ink-soft);line-height:1.8;margin-bottom:1rem;">
      Il quark è la particella elementare più piccola conosciuta — il mattone fondamentale
      della materia. Abbiamo scelto questo nome perché crediamo che la scienza parta sempre
      dalle domande più piccole: <em>perché il cielo è blu? Come funziona un vaccino?
      Cosa c'è dentro un buco nero?</em>
    </p>
    <p style="color:var(--ink-soft);line-height:1.8;margin-bottom:1rem;">
      Quark nasce dalla convinzione che la divulgazione scientifica di qualità non debba
      essere per pochi. Ogni italiano merita di capire la scienza che cambia il mondo —
      senza dover leggere paper accademici o guardare video da un'ora.
    </p>
    <p style="color:var(--ink-soft);line-height:1.8;">
      Ogni articolo è scritto per essere letto in 5 minuti, capito al primo tentativo
      e ricordato il giorno dopo.
    </p>
  </section>

  {{-- Filosofia editoriale --}}
  <section style="margin-bottom:3rem;">
    <h2 style="font-family:var(--font-display);font-size:1.5rem;font-weight:700;
               color:var(--ink);margin-bottom:1.5rem;">La nostra filosofia</h2>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
      @foreach([
        ['🔬', 'Fonti primarie', 'Ogni informazione è verificata sulla fonte originale — paper scientifici, comunicati ufficiali, dati istituzionali. Mai telefono senza fili.'],
        ['💡', 'Semplicità senza banalità', 'Spieghiamo concetti complessi in modo semplice, senza sacrificare la precisione scientifica.'],
        ['🚫', 'Zero clickbait', 'I titoli descrivono l\'articolo. Mai promesse esagerate, mai notizie decontestualizzate.'],
        ['⚡', 'Velocità e freschezza', 'Pubblichiamo notizie scientifiche rilevanti entro 48 ore dalla fonte originale.'],
        ['🇮🇹', 'Prospettiva italiana', 'Raccontiamo la scienza con occhio all\'Italia — ricerca italiana, impatto sul territorio, contesto europeo.'],
        ['✅', 'Trasparenza totale', 'Ogni articolo indica le fonti. Gli errori vengono corretti pubblicamente nella pagina Rettifiche.'],
      ] as [$icon, $title, $desc])
      <div style="background:var(--paper-warm);border-radius:12px;padding:1.25rem;">
        <div style="font-size:1.5rem;margin-bottom:.5rem;">{{ $icon }}</div>
        <div style="font-size:.9rem;font-weight:700;color:var(--ink);margin-bottom:.35rem;">{{ $title }}</div>
        <div style="font-size:.82rem;color:var(--ink-muted);line-height:1.55;">{{ $desc }}</div>
      </div>
      @endforeach
    </div>
  </section>

  {{-- Protocollo verifica --}}
  <section style="margin-bottom:3rem;background:var(--primary-light);border-radius:16px;padding:2rem;">
    <h2 style="font-family:var(--font-display);font-size:1.4rem;font-weight:700;
               color:var(--ink);margin-bottom:1rem;">Il protocollo di verifica</h2>
    <p style="color:var(--ink-soft);line-height:1.75;margin-bottom:1.25rem;">
      Prima di pubblicare qualsiasi articolo, ogni informazione passa attraverso
      il nostro protocollo di verifica in 4 fasi:
    </p>
    @foreach([
      ['1', 'Fonte primaria', 'Identifichiamo il paper originale, il comunicato ufficiale o i dati istituzionali alla base della notizia.'],
      ['2', 'Verifica incrociata', 'Confrontiamo con almeno 2 fonti indipendenti per escludere errori o interpretazioni distorte.'],
      ['3', 'Contestualizzazione', 'Inseriamo la notizia nel contesto scientifico appropriato — senza sopravvalutare o minimizzare.'],
      ['4', 'Revisione editoriale', 'Il Direttore rivede ogni articolo prima della pubblicazione.'],
    ] as [$num, $title, $desc])
    <div style="display:flex;gap:1rem;margin-bottom:.85rem;align-items:flex-start;">
      <div style="width:28px;height:28px;border-radius:50%;background:var(--primary);
                  color:white;display:flex;align-items:center;justify-content:center;
                  font-size:.75rem;font-weight:700;flex-shrink:0;margin-top:2px;">
        {{ $num }}
      </div>
      <div>
        <div style="font-size:.875rem;font-weight:700;color:var(--ink);">{{ $title }}</div>
        <div style="font-size:.82rem;color:var(--ink-soft);line-height:1.55;">{{ $desc }}</div>
      </div>
    </div>
    @endforeach
  </section>

  {{-- Fondatore --}}
  <section id="fondatore" style="margin-bottom:3rem;">
    <h2 style="font-family:var(--font-display);font-size:1.5rem;font-weight:700;
               color:var(--ink);margin-bottom:1.5rem;">Il fondatore</h2>
    <div style="display:flex;gap:1.5rem;align-items:flex-start;
                background:white;border:1px solid var(--border);border-radius:16px;padding:1.5rem;">
      <div style="width:80px;height:80px;border-radius:50%;background:var(--primary);
                  display:flex;align-items:center;justify-content:center;
                  font-size:1.8rem;font-weight:700;color:white;flex-shrink:0;">AB</div>
      <div>
        <div style="font-size:1.1rem;font-weight:700;color:var(--ink);margin-bottom:.2rem;">
          Andrea Bartiromo
        </div>
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;
                    letter-spacing:.06em;color:var(--primary);margin-bottom:.65rem;">
          Fondatore e Direttore
        </div>
        <p style="font-size:.875rem;color:var(--ink-soft);line-height:1.65;margin-bottom:.75rem;">
          Appassionato di scienza e comunicazione, Andrea ha fondato Quark con l'obiettivo
          di rendere la divulgazione scientifica accessibile a tutti gli italiani.
          Crede che capire il mondo attraverso la scienza renda le persone più libere.
        </p>
        <a href="{{ route('contatti') }}"
           style="font-size:.78rem;color:var(--primary);text-decoration:none;font-weight:600;">
          Scrivimi →
        </a>
      </div>
    </div>
  </section>

  {{-- Contatti rapidi --}}
  <section style="background:var(--ink);border-radius:16px;padding:2rem;text-align:center;">
    <h2 style="font-family:var(--font-display);font-size:1.3rem;font-weight:700;
               color:white;margin-bottom:.5rem;">Hai una domanda o una segnalazione?</h2>
    <p style="color:rgba(255,255,255,.7);font-size:.875rem;margin-bottom:1.25rem;">
      Scrivici per segnalare errori, proporre argomenti o semplicemente per dirci cosa ne pensi.
    </p>
    <a href="{{ route('contatti') }}"
       style="display:inline-block;background:var(--primary);color:white;
              padding:.65rem 1.5rem;border-radius:8px;text-decoration:none;
              font-weight:700;font-size:.875rem;">
      Contattaci
    </a>
  </section>

</div>
@endsection