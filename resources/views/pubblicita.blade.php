@extends('layouts.app')
@section('title', 'Pubblicità e collaborazioni — Quark')
@section('description', 'Collabora con Quark. Opportunità pubblicitarie e partnership per raggiungere un pubblico scientifico qualificato.')

@section('content')
<div class="container" style="padding-block:3rem;max-width:720px;">

  <div style="margin-bottom:2.5rem;">
    <div class="hero-eyebrow" style="margin-bottom:1rem;">Collabora</div>
    <h1 style="font-family:var(--font-display);font-size:2.2rem;font-weight:900;
               color:var(--ink);letter-spacing:-.02em;margin-bottom:.75rem;">
      Pubblicità e collaborazioni
    </h1>
    <p style="font-size:1rem;color:var(--ink-soft);line-height:1.7;">
      Quark raggiunge un pubblico giovane, istruito e appassionato di scienza e tecnologia.
      Se vuoi raggiungere questo pubblico in modo autentico, parliamone.
    </p>
  </div>

  {{-- Numeri --}}
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:2.5rem;">
    @foreach([
      ['30+', 'Articoli pubblicati'],
      ['6', 'Categorie scientifiche'],
      ['100%', 'Contenuti verificati'],
    ] as [$num, $label])
    <div style="background:var(--primary-light);border-radius:12px;padding:1.25rem;text-align:center;">
      <div style="font-family:var(--font-display);font-size:2rem;font-weight:900;
                  color:var(--primary);margin-bottom:.25rem;">{{ $num }}</div>
      <div style="font-size:.75rem;color:var(--primary-dark);font-weight:600;">{{ $label }}</div>
    </div>
    @endforeach
  </div>

  {{-- Opzioni --}}
  <section style="margin-bottom:2rem;">
    <h2 style="font-size:1.1rem;font-weight:700;color:var(--ink);margin-bottom:1rem;">
      Opportunità disponibili
    </h2>
    @foreach([
      ['📢', 'Banner display', 'Posizioni nella sidebar, nell\'articolo e in homepage. Formati standard IAB.'],
      ['📝', 'Contenuto sponsorizzato', 'Articoli di approfondimento su temi scientifici in linea con la nostra editorial policy. Sempre etichettati come "Sponsorizzato".'],
      ['✉️', 'Newsletter sponsorizzata', 'Presenza dedicata nella newsletter settimanale inviata agli iscritti confermati.'],
      ['🤝', 'Partnership editoriale', 'Collaborazioni con istituti di ricerca, università e aziende tech per contenuti di divulgazione.'],
    ] as [$icon, $title, $desc])
    <div style="display:flex;gap:1rem;padding:1rem;border:1px solid var(--border);
                border-radius:10px;margin-bottom:.75rem;">
      <span style="font-size:1.5rem;flex-shrink:0;">{{ $icon }}</span>
      <div>
        <div style="font-size:.9rem;font-weight:700;color:var(--ink);margin-bottom:.25rem;">{{ $title }}</div>
        <div style="font-size:.82rem;color:var(--ink-muted);line-height:1.55;">{{ $desc }}</div>
      </div>
    </div>
    @endforeach
  </section>

  {{-- Policy --}}
  <section style="background:var(--paper-warm);border-radius:12px;padding:1.5rem;margin-bottom:2rem;">
    <h2 style="font-size:1rem;font-weight:700;color:var(--ink);margin-bottom:.75rem;">
      La nostra policy pubblicitaria
    </h2>
    @foreach([
      'Accettiamo solo pubblicità coerente con i valori scientifici di Quark',
      'Ogni contenuto sponsorizzato è sempre chiaramente etichettato',
      'Non accettiamo pubblicità che promuova pseudoscienza o disinformazione',
      'I contenuti editoriali rimangono completamente indipendenti dagli inserzionisti',
    ] as $item)
    <div style="display:flex;gap:.5rem;font-size:.82rem;color:var(--ink-soft);
                margin-bottom:.4rem;align-items:flex-start;">
      <span style="color:var(--primary);margin-top:1px;">✓</span> {{ $item }}
    </div>
    @endforeach
  </section>

  {{-- CTA --}}
  <div style="background:var(--ink);border-radius:16px;padding:2rem;text-align:center;">
    <h2 style="font-family:var(--font-display);font-size:1.2rem;font-weight:700;
               color:white;margin-bottom:.5rem;">Interessato a collaborare?</h2>
    <p style="color:rgba(255,255,255,.7);font-size:.875rem;margin-bottom:1.25rem;">
      Scrivici con una descrizione del tuo progetto e il tipo di collaborazione che hai in mente.
    </p>
    <a href="{{ route('contatti') }}"
       style="display:inline-block;background:var(--primary);color:white;
              padding:.65rem 1.5rem;border-radius:8px;text-decoration:none;
              font-weight:700;font-size:.875rem;">
      Contattaci
    </a>
  </div>

</div>
@endsection