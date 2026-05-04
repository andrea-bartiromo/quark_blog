@extends('layouts.app')

@section('title', 'Cookie Policy — '.config('laboratorio.name'))
@section('description', 'Informativa sull\'utilizzo dei cookie su Il Laboratorio, ai sensi del Provvedimento del Garante Privacy.')

@section('content')
<div class="container" style="padding-block:2.5rem;max-width:780px;">

  <hr style="border:none;border-top:3px solid var(--color-ink);margin:0 0 .5rem;">
  <h1 style="font-family:var(--font-display);font-size:clamp(1.6rem,3vw,2.2rem);font-weight:900;margin-bottom:.5rem;">
    Cookie Policy
  </h1>
  <p style="font-family:var(--font-ui);font-size:.78rem;color:var(--color-ink-muted);margin-bottom:2rem;">
    Ultimo aggiornamento: {{ now()->translatedFormat('d F Y') }}
  </p>

  <div style="font-size:.92rem;color:var(--color-ink-soft);line-height:1.75;">

    <p style="margin-bottom:1.25em;">
      Questo sito utilizza cookie e tecnologie simili per garantire il corretto funzionamento,
      analizzare il traffico e mostrare pubblicità pertinente. Puoi gestire le tue preferenze
      in qualsiasi momento tramite il banner cookie o questa pagina.
    </p>

    {{-- Tabella cookie tecnici --}}
    <h2 style="font-family:var(--font-display);font-size:1.15rem;font-weight:700;
               border-top:1px solid var(--color-border);padding-top:1rem;margin:1.5rem 0 .75rem;color:var(--color-ink);">
      Cookie tecnici (sempre attivi)
    </h2>
    <p style="margin-bottom:.75em;">Non richiedono consenso. Necessari per il funzionamento del sito.</p>

    <div style="overflow-x:auto;margin-bottom:1.5rem;">
      <table style="width:100%;border-collapse:collapse;font-size:.84rem;">
        <thead>
          <tr style="background:var(--color-paper-warm);">
            <th style="text-align:left;padding:.6rem .85rem;font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--color-ink-muted);">Nome</th>
            <th style="text-align:left;padding:.6rem .85rem;font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--color-ink-muted);">Scopo</th>
            <th style="text-align:left;padding:.6rem .85rem;font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--color-ink-muted);">Durata</th>
          </tr>
        </thead>
        <tbody>
          @foreach([
            ['il_laboratorio_session', 'Sessione utente autenticato', 'Sessione'],
            ['XSRF-TOKEN', 'Protezione CSRF', 'Sessione'],
            ['cookie-choice', 'Memorizza la scelta sui cookie', '12 mesi'],
            ['nl-seen', 'Nasconde il popup newsletter dopo la prima visualizzazione', '30 giorni'],
          ] as [$nome, $scopo, $durata])
          <tr style="border-bottom:1px solid var(--color-border);">
            <td style="padding:.6rem .85rem;font-family:monospace;font-size:.82rem;color:var(--color-ink);">{{ $nome }}</td>
            <td style="padding:.6rem .85rem;">{{ $scopo }}</td>
            <td style="padding:.6rem .85rem;white-space:nowrap;">{{ $durata }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    {{-- Cookie analytics --}}
    <h2 style="font-family:var(--font-display);font-size:1.15rem;font-weight:700;
               border-top:1px solid var(--color-border);padding-top:1rem;margin:1.5rem 0 .75rem;color:var(--color-ink);">
      Cookie analytics (previo consenso)
    </h2>
    <p style="margin-bottom:.75em;">Attivati solo se accetti i cookie analytics nel banner.</p>

    <div style="overflow-x:auto;margin-bottom:1.5rem;">
      <table style="width:100%;border-collapse:collapse;font-size:.84rem;">
        <thead>
          <tr style="background:var(--color-paper-warm);">
            <th style="text-align:left;padding:.6rem .85rem;font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--color-ink-muted);">Fornitore</th>
            <th style="text-align:left;padding:.6rem .85rem;font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--color-ink-muted);">Scopo</th>
            <th style="text-align:left;padding:.6rem .85rem;font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--color-ink-muted);">Info</th>
          </tr>
        </thead>
        <tbody>
          <tr style="border-bottom:1px solid var(--color-border);">
            <td style="padding:.6rem .85rem;font-weight:600;">Google Analytics (GA4)</td>
            <td style="padding:.6rem .85rem;">Analisi statistica del traffico in forma aggregata e anonima</td>
            <td style="padding:.6rem .85rem;"><a href="https://policies.google.com/privacy" target="_blank" rel="noopener" style="color:var(--color-accent);">Privacy Google</a></td>
          </tr>
          <tr>
            <td style="padding:.6rem .85rem;font-weight:600;">Google Tag Manager</td>
            <td style="padding:.6rem .85rem;">Gestione centralizzata dei tag di tracciamento</td>
            <td style="padding:.6rem .85rem;"><a href="https://marketingplatform.google.com/about/analytics/tag-manager/use-policy/" target="_blank" rel="noopener" style="color:var(--color-accent);">Policy GTM</a></td>
          </tr>
        </tbody>
      </table>
    </div>

    {{-- Come gestire i cookie --}}
    <h2 style="font-family:var(--font-display);font-size:1.15rem;font-weight:700;
               border-top:1px solid var(--color-border);padding-top:1rem;margin:1.5rem 0 .75rem;color:var(--color-ink);">
      Come gestire i cookie
    </h2>
    <p style="margin-bottom:.75em;">
      Puoi modificare le tue preferenze in qualsiasi momento:
    </p>
    <ul style="padding-left:1.5em;list-style:disc;margin-bottom:1.25em;">
      <li style="margin-bottom:.4em;"><strong>Dal banner cookie</strong> — appare al primo accesso al sito e dopo aver cancellato i cookie del browser.</li>
      <li style="margin-bottom:.4em;"><strong>Dal browser</strong> — le impostazioni del tuo browser ti permettono di bloccare o eliminare tutti i cookie.</li>
      <li style="margin-bottom:.4em;"><strong>Da Google Analytics</strong> — puoi disattivare GA4 installando il <a href="https://tools.google.com/dlpage/gaoptout" target="_blank" rel="noopener" style="color:var(--color-accent);">componente aggiuntivo del browser</a>.</li>
    </ul>

    <p>
      Per qualsiasi informazione scrivi a
      <a href="mailto:privacy@illaboratorio.it" style="color:var(--color-accent);">privacy@illaboratorio.it</a>.
    </p>

  </div>
</div>
@endsection
