@extends('layouts.app')

@section('title', 'La redazione — '.config('laboratorio.name'))
@section('description', 'Conosci i giornalisti de Il Laboratorio: formazione, specializzazioni e contatti.')

@section('content')
<div class="container" style="padding-block:2.5rem;">

  {{-- Intestazione --}}
  <div style="max-width:640px; margin-bottom:3rem;">
    <hr style="border:none;border-top:3px solid var(--color-ink);margin:0 0 .5rem;">
    <h1 style="font-family:var(--font-display);font-size:clamp(1.8rem,4vw,2.6rem);font-weight:900;margin-bottom:.75rem;">
      La redazione
    </h1>
    <p style="font-size:1.05rem;color:var(--color-ink-soft);line-height:1.7;">
      Il Laboratorio è fatto da giornalisti appassionati di scienza e tecnologia.
      Ognuno porta una competenza specifica: nessun generalista, solo specialisti
      con formazione scientifica o anni di esperienza sul campo.
    </p>
  </div>

  {{-- Direttore --}}
  <section style="margin-bottom:3rem;">
    <h2 class="uppercase font-ui" style="color:var(--color-ink-muted);font-size:.72rem;font-weight:700;letter-spacing:.1em;margin-bottom:1.5rem;">
      Direzione
    </h2>

    <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow-hover);
                overflow:hidden;display:grid;grid-template-columns:260px 1fr;">
      <div style="background:var(--color-ink);min-height:260px;position:relative;">
        <img src="{{ asset('assets/img/author-direttore.jpg') }}"
             alt="Foto del Direttore Responsabile"
             style="width:100%;height:100%;object-fit:cover;opacity:.75;"
             loading="lazy"
             onerror="this.style.display='none'">
        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;">
          <span style="font-family:var(--font-display);font-size:4rem;font-weight:900;color:rgba(255,255,255,.35);">AB</span>
        </div>
      </div>
      <div style="padding:2rem;">
        <span class="kicker">Direttore responsabile</span>
        <h2 style="font-family:var(--font-display);font-size:1.7rem;font-weight:900;margin-bottom:.25rem;">
          Andrea Bartiromo
        </h2>
        <p style="font-family:var(--font-ui);font-size:.78rem;color:var(--color-ink-muted);margin-bottom:1rem;">
          Fondatore e Direttore Responsabile — <em>Il Laboratorio</em>
        </p>
        <p style="font-size:.92rem;color:var(--color-ink-soft);line-height:1.7;margin-bottom:1.25rem;">
          Fondatore e Direttore Responsabile de <em>Il Laboratorio</em>.
          Ha ideato e sviluppato il progetto editoriale con l'obiettivo di portare
          la divulgazione scientifica italiana a uno standard di rigore e accessibilità
          tra i più alti nel panorama digitale nazionale. Supervisiona la linea editoriale
          e il protocollo di verifica delle fonti.
        </p>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
          <a href="mailto:direzione@illaboratorio.it" class="btn btn--outline"
             style="color:var(--color-ink);border-color:var(--color-border);font-size:.75rem;">
            ✉ direzione@illaboratorio.it
          </a>
        </div>
      </div>
    </div>
  </section>

  {{-- Redattori --}}
  <section style="margin-bottom:3rem;">
    <h2 class="uppercase font-ui" style="color:var(--color-ink-muted);font-size:.72rem;font-weight:700;letter-spacing:.1em;margin-bottom:1.5rem;">
      Redattori
    </h2>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:1.25rem;">
      @php
        $redattori = \App\Models\User::where('role','!=','admin')->orderBy('role','desc')->orderBy('name')->get();
      @endphp

      @foreach($redattori as $user)
      <div class="author-box">
        <div style="width:72px;height:72px;border-radius:50%;background:var(--color-paper-warm);
                    flex-shrink:0;border:3px solid var(--color-paper-warm);overflow:hidden;display:flex;
                    align-items:center;justify-content:center;">
          @if($user->photo)
            <img src="{{ asset('assets/img/'.$user->photo) }}"
                 alt="Foto di {{ $user->name }}"
                 style="width:100%;height:100%;object-fit:cover;"
                 onerror="this.style.display='none'">
          @else
            <span style="font-size:1.5rem;">👤</span>
          @endif
        </div>
        <div style="flex:1;">
          <div class="author-box__role">{{ ucfirst($user->role) }}</div>
          <div class="author-box__name">{{ $user->name }}</div>
          @if($user->bio)
            <p class="author-box__bio">{{ $user->bio }}</p>
          @endif
          <div style="font-family:var(--font-ui);font-size:.72rem;color:var(--color-ink-muted);margin-bottom:.4rem;">
            📧 {{ $user->email }}
          </div>
          <div class="author-box__socials">
            @if($user->twitter)
              <a href="https://twitter.com/{{ ltrim($user->twitter,'@') }}" target="_blank" rel="noopener">Twitter</a>
            @endif
            @if($user->linkedin)
              <a href="{{ $user->linkedin }}" target="_blank" rel="noopener">LinkedIn</a>
            @endif
            <span style="font-family:var(--font-ui);font-size:.72rem;color:var(--color-ink-muted);">
              {{ $user->articles()->count() }} articoli pubblicati
            </span>
          </div>
        </div>
      </div>
      @endforeach
    </div>
  </section>

  {{-- Contatti redazione --}}
  <section style="background:var(--color-white);border-radius:var(--radius);
                  box-shadow:var(--shadow);padding:2rem;max-width:600px;">
    <h2 style="font-family:var(--font-display);font-size:1.2rem;font-weight:700;margin-bottom:.75rem;">
      Contatta la redazione
    </h2>
    <p style="font-size:.9rem;color:var(--color-ink-soft);margin-bottom:1rem;">
      Per comunicati stampa, segnalazioni, proposte di collaborazione o rettifiche.
    </p>
    <ul style="list-style:none;display:flex;flex-direction:column;gap:.5rem;font-family:var(--font-ui);font-size:.88rem;">
      <li>✉ <a href="mailto:redazione@illaboratorio.it" style="color:var(--color-accent);">redazione@illaboratorio.it</a> — Notizie e comunicati</li>
      <li>✉ <a href="mailto:pubblicita@illaboratorio.it" style="color:var(--color-accent);">pubblicita@illaboratorio.it</a> — Pubblicità e sponsorship</li>
      <li>✉ <a href="mailto:privacy@illaboratorio.it" style="color:var(--color-accent);">privacy@illaboratorio.it</a> — Richieste GDPR</li>
      <li>✉ <a href="mailto:rettifiche@illaboratorio.it" style="color:var(--color-accent);">rettifiche@illaboratorio.it</a> — Correzioni e rettifiche</li>
    </ul>
  </section>

</div>
@endsection
