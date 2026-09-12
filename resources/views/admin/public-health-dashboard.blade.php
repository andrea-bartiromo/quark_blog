@extends('layouts.admin')
@section('title', 'Salute pubblica')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Salute pubblica</h1>
</div>

<p style="color:var(--admin-muted);font-size:.85rem;margin-bottom:1rem;max-width:70ch;">
  Riepilogo read-only degli audit tecnici delle pagine pubbliche (SEO/canonical,
  redirect, 404 reali, collegamenti, media, accessibilità WCAG statica) — nessun
  nuovo controllo, nessuna correzione automatica. Ogni card rimanda al comando
  Artisan corrispondente per il dettaglio completo.
</p>

@php $status = $snapshot['status']; @endphp
<div style="display:flex;align-items:center;gap:.85rem;padding:.85rem 1.1rem;border-radius:var(--radius);margin-bottom:1.25rem;background:{{ $status === 'SANA' ? '#ecfdf5' : '#fffbeb' }};border:1px solid {{ $status === 'SANA' ? '#a7f3d0' : '#fde68a' }};">
  <span aria-hidden="true" style="font-size:1.4rem;">{{ $status === 'SANA' ? '✅' : '⚠️' }}</span>
  <div>
    <strong style="color:{{ $status === 'SANA' ? '#065f46' : '#92400e' }};">
      @if($status === 'SANA')
        Nessun finding aperto sulle pagine pubbliche verificate.
      @else
        {{ $snapshot['open_findings_total'] }} {{ $snapshot['open_findings_total'] === 1 ? 'finding aperto' : 'finding aperti' }} da rivedere.
      @endif
    </strong>
  </div>
</div>

@php
  $available = collect($snapshot['domains'])->filter(fn ($d) => $d['available']);
  $unavailable = collect($snapshot['domains'])->reject(fn ($d) => $d['available']);
@endphp

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-bottom:1.5rem;">
  @foreach($available as $domain)
    <div class="admin-card" style="margin:0;">
      <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--admin-muted);">{{ $domain['label'] }}</div>
      <div style="font-size:1.9rem;font-weight:700;margin:.35rem 0;color:{{ $domain['finding_count'] > 0 ? '#b91c1c' : '#059669' }};">{{ number_format($domain['finding_count']) }}</div>
      <span style="font-size:.78rem;color:var(--admin-muted);">finding · <code style="font-size:.72rem;">{{ $domain['cli_hint'] }}</code></span>
    </div>
  @endforeach
</div>

@if($unavailable->isNotEmpty())
<section class="admin-card" style="margin-bottom:1.5rem;">
  <h2 style="font-size:1rem;margin:0 0 .75rem;">Non aggregabili qui</h2>
  <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.6rem;">
    @foreach($unavailable as $domain)
      <li style="font-size:.85rem;">
        <strong>{{ $domain['label'] }}</strong>
        <p style="margin:.2rem 0 0;color:var(--admin-muted);font-size:.8rem;">{{ $domain['reason'] }}</p>
        <code style="font-size:.72rem;">{{ $domain['cli_hint'] }}</code>
      </li>
    @endforeach
  </ul>
</section>
@endif

@php
  $seo = $snapshot['domains']['seo'];
  $redirectsCanonical = $snapshot['domains']['redirects_canonical'];
  $notFound = $snapshot['domains']['not_found'];
  $links = $snapshot['domains']['links'];
  $media = $snapshot['domains']['media'];
  $wcag = $snapshot['domains']['wcag'];
@endphp

<section class="admin-card" style="margin-bottom:1.5rem;">
  <h2 style="font-size:1rem;margin:0 0 .75rem;">SEO/canonical/JSON-LD</h2>
  @if($seo['flagged'] === [])
    <p style="font-size:.82rem;color:var(--admin-muted);margin:0;">Nessun finding — {{ $seo['checked_count'] }}/{{ $seo['total_count'] }} pagine verificate.</p>
  @else
    <div style="overflow-x:auto;">
      <table class="admin-table">
        <thead><tr><th scope="col">Pagina</th><th scope="col">URL</th><th scope="col">Finding</th></tr></thead>
        <tbody>
          @foreach($seo['flagged'] as $row)
            <tr>
              <td>{{ $row['label'] }}</td>
              <td>{{ $row['sample_url'] ?? '—' }}</td>
              <td>{{ implode(' | ', $row['findings']) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>

<section class="admin-card" style="margin-bottom:1.5rem;">
  <h2 style="font-size:1rem;margin:0 0 .75rem;">Redirect vecchi slug e coerenza canonical</h2>
  @if($redirectsCanonical['flagged_redirects'] === [] && $redirectsCanonical['flagged_canonicals'] === [])
    <p style="font-size:.82rem;color:var(--admin-muted);margin:0;">Nessun finding — {{ $redirectsCanonical['checked_count'] }} elementi verificati.</p>
  @else
    <div style="overflow-x:auto;">
      <table class="admin-table">
        <thead><tr><th scope="col">Tipo</th><th scope="col">Riferimento</th><th scope="col">Stato HTTP</th><th scope="col">Finding</th></tr></thead>
        <tbody>
          @foreach($redirectsCanonical['flagged_redirects'] as $row)
            <tr>
              <td>Redirect</td>
              <td>{{ $row['old_slug'] }} (articolo #{{ $row['article_id'] }})</td>
              <td>{{ $row['http_status'] }}</td>
              <td>{{ implode(' | ', $row['findings']) }}</td>
            </tr>
          @endforeach
          @foreach($redirectsCanonical['flagged_canonicals'] as $row)
            <tr>
              <td>Canonical ({{ $row['type'] }})</td>
              <td>{{ $row['url'] }}</td>
              <td>{{ $row['http_status'] }}</td>
              <td>{{ implode(' | ', $row['findings']) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>

<section class="admin-card" style="margin-bottom:1.5rem;">
  <h2 style="font-size:1rem;margin:0 0 .75rem;">Registro 404 dal traffico reale</h2>
  @if($notFound['flagged'] === [])
    <p style="font-size:.82rem;color:var(--admin-muted);margin:0;">Nessun 404 registrato dal traffico reale.</p>
  @else
    <div style="overflow-x:auto;">
      <table class="admin-table">
        <thead><tr><th scope="col">Path</th><th scope="col">Occorrenze</th><th scope="col">Ultima vista</th></tr></thead>
        <tbody>
          @foreach($notFound['flagged'] as $hit)
            <tr>
              <td>{{ $hit->path }}</td>
              <td>{{ $hit->hits }}</td>
              <td>{{ $hit->last_seen_at->toDateTimeString() }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>

<section class="admin-card" style="margin-bottom:1.5rem;">
  <h2 style="font-size:1rem;margin:0 0 .75rem;">Collegamenti interni (oltre /articolo/)</h2>
  <p style="font-size:.78rem;color:var(--admin-muted);margin:0 0 .5rem;">
    {{ $links['external_links_found'] }} {{ $links['external_links_found'] === 1 ? 'collegamento esterno trovato' : 'collegamenti esterni trovati' }},
    non verificati di default (vedi <code style="font-size:.72rem;">--check-external</code> del comando).
  </p>
  @if($links['flagged'] === [])
    <p style="font-size:.82rem;color:var(--admin-muted);margin:0;">Nessun finding — {{ $links['checked_count'] }} collegamenti interni verificati.</p>
  @else
    <div style="overflow-x:auto;">
      <table class="admin-table">
        <thead><tr><th scope="col">URL</th><th scope="col">Articoli</th><th scope="col">Finding</th></tr></thead>
        <tbody>
          @foreach($links['flagged'] as $row)
            <tr>
              <td>{{ $row['url'] }}</td>
              <td>{{ implode(', ', $row['articles']) }}</td>
              <td>{{ implode(' | ', $row['findings']) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>

<section class="admin-card" style="margin-bottom:1.5rem;">
  <h2 style="font-size:1rem;margin:0 0 .75rem;">Media (alt/crediti/peso/formato/file mancanti)</h2>
  @if($media['flagged'] === [])
    <p style="font-size:.82rem;color:var(--admin-muted);margin:0;">Nessun finding — {{ $media['checked_count'] }} media analizzati.</p>
  @else
    <p style="font-size:.78rem;color:var(--admin-muted);margin:0 0 .5rem;">
      Alt mancante: {{ $media['breakdown']['missing_alt'] }} · Credito incompleto: {{ $media['breakdown']['missing_credit'] }} ·
      File mancante: {{ $media['breakdown']['missing_file'] }} · Peso elevato: {{ $media['breakdown']['oversized'] }} ·
      Formato non ottimale: {{ $media['breakdown']['non_optimal_format'] }}
    </p>
    <div style="overflow-x:auto;">
      <table class="admin-table">
        <thead><tr><th scope="col">File</th><th scope="col">Finding</th></tr></thead>
        <tbody>
          @foreach($media['flagged'] as $row)
            <tr>
              <td><a href="{{ route('admin.media') }}">{{ $row['filename'] }}</a></td>
              <td>{{ implode(' | ', $row['findings']) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>

<section class="admin-card" style="margin-bottom:1.5rem;">
  <h2 style="font-size:1rem;margin:0 0 .75rem;">Accessibilità WCAG statica</h2>
  @if($wcag['flagged'] === [])
    <p style="font-size:.82rem;color:var(--admin-muted);margin:0;">Nessun finding — {{ $wcag['checked_count'] }}/{{ $wcag['total_count'] }} pagine verificate.</p>
  @else
    <div style="overflow-x:auto;">
      <table class="admin-table">
        <thead><tr><th scope="col">Pagina</th><th scope="col">URL</th><th scope="col">Finding</th></tr></thead>
        <tbody>
          @foreach($wcag['flagged'] as $row)
            <tr>
              <td>{{ $row['label'] }}</td>
              <td>{{ $row['url'] ?? '—' }}</td>
              <td>{{ implode(' | ', $row['findings']) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>

@endsection
