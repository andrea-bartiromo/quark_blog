@extends('layouts.app')
@section('title', 'Cookie Policy — Quark')
@section('description', 'Cookie policy di Quark. Quali cookie utilizziamo e come gestirli.')

@section('content')
<div class="container" style="padding-block:3rem;max-width:720px;">

  <div style="margin-bottom:2rem;">
    <div class="hero-eyebrow" style="margin-bottom:1rem;">Legale</div>
    <h1 style="font-family:var(--font-display);font-size:2rem;font-weight:900;
               color:var(--ink);letter-spacing:-.02em;margin-bottom:.5rem;">Cookie Policy</h1>
    <p style="font-size:.82rem;color:var(--ink-muted);">
      Ultimo aggiornamento: {{ date('d/m/Y') }}
    </p>
  </div>

  <p style="font-size:.875rem;color:var(--ink-soft);line-height:1.75;margin-bottom:2rem;">
    Questa pagina descrive i cookie utilizzati da Quark e come gestirli.
    Un cookie è un piccolo file di testo salvato nel tuo browser quando visiti un sito web.
  </p>

  {{-- Tabella cookie --}}
  <section style="margin-bottom:2rem;">
    <h2 style="font-size:1.1rem;font-weight:700;color:var(--ink);margin-bottom:1rem;">
      Cookie utilizzati
    </h2>

    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
        <thead>
          <tr style="background:var(--paper-warm);">
            <th style="text-align:left;padding:.65rem .85rem;border-bottom:2px solid var(--border);color:var(--ink-muted);font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;">Nome</th>
            <th style="text-align:left;padding:.65rem .85rem;border-bottom:2px solid var(--border);color:var(--ink-muted);font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;">Tipo</th>
            <th style="text-align:left;padding:.65rem .85rem;border-bottom:2px solid var(--border);color:var(--ink-muted);font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;">Scopo</th>
            <th style="text-align:left;padding:.65rem .85rem;border-bottom:2px solid var(--border);color:var(--ink-muted);font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;">Durata</th>
          </tr>
        </thead>
        <tbody>
          @foreach([
            ['quark_session', 'Tecnico', 'Sessione utente — necessario per il funzionamento del sito', '2 ore'],
            ['XSRF-TOKEN', 'Tecnico', 'Protezione sicurezza dei form (CSRF)', '2 ore'],
            ['newsletter_dismissed', 'Funzionale', 'Ricorda se hai chiuso il popup newsletter', '7 giorni'],
            ['newsletter_subscribed', 'Funzionale', 'Ricorda se sei iscritto alla newsletter', 'Permanente'],
            ['cookie_consent', 'Funzionale', 'Salva la tua scelta sul banner cookie', '1 anno'],
            ['_ga', 'Analitico', 'Google Analytics — identificatore utente anonimo', '2 anni'],
            ['_ga_*', 'Analitico', 'Google Analytics — sessione di misurazione', '2 anni'],
          ] as [$nome, $tipo, $scopo, $durata])
          <tr>
            <td style="padding:.65rem .85rem;border-bottom:1px solid var(--border);font-family:monospace;color:var(--primary);">{{ $nome }}</td>
            <td style="padding:.65rem .85rem;border-bottom:1px solid var(--border);">
              <span style="font-size:.68rem;font-weight:700;padding:.15rem .5rem;border-radius:20px;
                background:{{ $tipo === 'Tecnico' ? '#dbeafe' : ($tipo === 'Funzionale' ? '#fef9c3' : '#ede9fe') }};
                color:{{ $tipo === 'Tecnico' ? '#1e40af' : ($tipo === 'Funzionale' ? '#854d0e' : '#5b21b6') }};">
                {{ $tipo }}
              </span>
            </td>
            <td style="padding:.65rem .85rem;border-bottom:1px solid var(--border);color:var(--ink-soft);">{{ $scopo }}</td>
            <td style="padding:.65rem .85rem;border-bottom:1px solid var(--border);color:var(--ink-muted);">{{ $durata }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>

  <section style="margin-bottom:2rem;">
    <h2 style="font-size:1.1rem;font-weight:700;color:var(--ink);margin-bottom:.75rem;">
      Come gestire i cookie
    </h2>
    <p style="font-size:.875rem;color:var(--ink-soft);line-height:1.75;margin-bottom:.75rem;">
      Puoi accettare o rifiutare i cookie analitici tramite il banner che appare alla prima visita.
      Puoi anche gestire i cookie direttamente dal tuo browser:
    </p>
    @foreach([
      ['Chrome', 'Impostazioni → Privacy e sicurezza → Cookie e altri dati dei siti'],
      ['Firefox', 'Impostazioni → Privacy e sicurezza → Cookie e dati dei siti'],
      ['Safari', 'Preferenze → Privacy → Gestisci dati dei siti web'],
      ['Edge', 'Impostazioni → Cookie e autorizzazioni del sito'],
    ] as [$browser, $path])
    <div style="font-size:.82rem;padding:.4rem 0;border-bottom:1px solid var(--border-light);
                display:flex;gap:.5rem;">
      <span style="font-weight:600;color:var(--ink);min-width:70px;">{{ $browser }}</span>
      <span style="color:var(--ink-muted);">{{ $path }}</span>
    </div>
    @endforeach
    <p style="font-size:.8rem;color:var(--ink-muted);margin-top:.75rem;line-height:1.6;">
      Nota: disabilitare i cookie tecnici potrebbe compromettere il funzionamento del sito.
    </p>
  </section>

  <div style="background:var(--primary-light);border-radius:10px;padding:1rem 1.25rem;font-size:.82rem;color:var(--primary-dark);">
    Per domande sui cookie:
    <a href="{{ route('contatti') }}" style="color:var(--primary);font-weight:600;">contattaci →</a>
  </div>

</div>
@endsection