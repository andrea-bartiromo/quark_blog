@extends('layouts.admin')
@section('title', 'Operazioni editoriali')
@section('content')

<div class="admin-topbar">
  <h1 class="admin-page-title">Operazioni editoriali</h1>
</div>

<p style="font-size:.85rem;color:#6b7280;max-width:70ch;margin:0 0 1.25rem;">
  Riepilogo read-only di ciò che richiede attenzione adesso, calcolato dagli stessi
  audit già usati altrove nel pannello (nessun nuovo controllo, nessuna correzione
  automatica). Ogni card rimanda allo strumento giusto per intervenire.
</p>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-bottom:1.5rem;">

  <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;">
    <div style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#6b7280;">Da pubblicare</div>
    <div style="font-size:1.9rem;font-weight:700;margin:.35rem 0;">{{ count($snapshot['da_pubblicare']) }}</div>
    <a href="{{ route('admin.articles.calendar') }}" style="font-size:.78rem;color:#0d9488;">Calendario articoli →</a>
  </div>

  <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;">
    <div style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#6b7280;">Da sistemare</div>
    <div style="font-size:1.9rem;font-weight:700;margin:.35rem 0;">{{ count($snapshot['da_sistemare']) }}</div>
    <span style="font-size:.78rem;color:#6b7280;">Warning contenuto/attribuzione</span>
  </div>

  <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;">
    <div style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#6b7280;">Contenuti isolati</div>
    <div style="font-size:1.9rem;font-weight:700;margin:.35rem 0;">{{ count($snapshot['contenuti_isolati']) }}</div>
    <span style="font-size:.78rem;color:#6b7280;">Pubblicati senza Percorso</span>
  </div>

  <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;">
    <div style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#6b7280;">Percorsi non pronti</div>
    <div style="font-size:1.9rem;font-weight:700;margin:.35rem 0;">{{ count($snapshot['percorsi_readiness']) }}</div>
    <a href="{{ route('admin.content-clusters.index') }}" style="font-size:.78rem;color:#0d9488;">Percorsi →</a>
  </div>

  <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;">
    <div style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#6b7280;">Sequenza Percorsi</div>
    <div style="font-size:1.9rem;font-weight:700;margin:.35rem 0;">{{ $snapshot['percorsi_order_health']['structural_error_count'] + $snapshot['percorsi_order_health']['publication_warning_count'] }}</div>
    <span style="font-size:.78rem;color:#6b7280;">Errori/warning d'ordine</span>
  </div>

  <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;">
    <div style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#6b7280;">SEO</div>
    <div style="font-size:1.9rem;font-weight:700;margin:.35rem 0;">{{ $snapshot['seo']['summary']['canonical_warnings'] }}</div>
    <span style="font-size:.78rem;color:#6b7280;">Canonical non valido</span>
  </div>

  <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;">
    <div style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#6b7280;">Opportunità</div>
    <div style="font-size:1.9rem;font-weight:700;margin:.35rem 0;">{{ $snapshot['opportunita']['total'] }}</div>
    <span style="font-size:.78rem;color:#6b7280;">Segnali Radar esplicabili</span>
  </div>

</div>

@foreach(['distribuzione' => 'Distribuzione'] as $key => $label)
  @if(! $snapshot[$key]['available'])
  <div style="background:#f9fafb;border:1px dashed #d1d5db;border-radius:6px;padding:.75rem 1rem;margin-bottom:.75rem;font-size:.78rem;color:#6b7280;">
    <strong>{{ $label }}:</strong> non disponibile — {{ $snapshot[$key]['reason'] }}
    @if(! empty($snapshot[$key]['tool_url']))
      <a href="{{ $snapshot[$key]['tool_url'] }}" style="color:#0d9488;">Apri lo strumento →</a>
    @endif
  </div>
  @endif
@endforeach

<section style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;margin-bottom:1.25rem;">
  <h2 style="font-size:1rem;margin:0 0 .75rem;">Da pubblicare</h2>
  @if(empty($snapshot['da_pubblicare']))
    <p style="font-size:.82rem;color:#6b7280;margin:0;">Nessun articolo programmato in attesa.</p>
  @else
    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.4rem;">
      @foreach($snapshot['da_pubblicare'] as $row)
        <li style="font-size:.85rem;">
          <a href="{{ route('admin.articles.edit', $row['article_id']) }}">{{ $row['title'] }}</a>
          <span style="color:#6b7280;"> — {{ \Illuminate\Support\Carbon::parse($row['published_at'])->timezone('Europe/Rome')->translatedFormat('d M Y, H:i') }}</span>
        </li>
      @endforeach
    </ul>
  @endif
</section>

<section style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;margin-bottom:1.25rem;">
  <h2 style="font-size:1rem;margin:0 0 .75rem;">Da sistemare</h2>
  @if(empty($snapshot['da_sistemare']))
    <p style="font-size:.82rem;color:#6b7280;margin:0;">Nessun warning di contenuto o attribuzione aperto.</p>
  @else
    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.4rem;">
      @foreach($snapshot['da_sistemare'] as $row)
        <li style="font-size:.85rem;">
          <a href="{{ route('admin.articles.edit', $row['article_id']) }}">{{ $row['title'] }}</a>
          <span style="color:#6b7280;"> — {{ count($row['health_warnings']) }} contenuto, {{ count($row['attribution_warnings']) }} attribuzione</span>
        </li>
      @endforeach
    </ul>
  @endif
</section>

<section style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;margin-bottom:1.25rem;">
  <h2 style="font-size:1rem;margin:0 0 .75rem;">Contenuti isolati</h2>
  @if(empty($snapshot['contenuti_isolati']))
    <p style="font-size:.82rem;color:#6b7280;margin:0;">Ogni articolo pubblicato appartiene ad almeno un Percorso.</p>
  @else
    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.4rem;">
      @foreach($snapshot['contenuti_isolati'] as $row)
        <li style="font-size:.85rem;"><a href="{{ route('admin.articles.edit', $row['id']) }}">{{ $row['title'] }}</a></li>
      @endforeach
    </ul>
  @endif
</section>

<section style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;margin-bottom:1.25rem;">
  <h2 style="font-size:1rem;margin:0 0 .75rem;">Percorsi non pronti</h2>
  @if(empty($snapshot['percorsi_readiness']))
    <p style="font-size:.82rem;color:#6b7280;margin:0;">Ogni Percorso valutato risulta READY.</p>
  @else
    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.4rem;">
      @foreach($snapshot['percorsi_readiness'] as $row)
        <li style="font-size:.85rem;">
          <a href="{{ route('admin.content-clusters.edit', $row['cluster_id']) }}">{{ $row['name'] }}</a>
          <span style="color:#6b7280;"> — {{ $row['status'] }} ({{ $row['error_count'] }} errori, {{ $row['warning_count'] }} warning)</span>
          @if(! empty($row['codes']))
            <div style="font-size:.72rem;color:#9ca3af;margin-top:.15rem;">{{ implode(' · ', $row['codes']) }}</div>
          @endif
          @if($row['also_in_order_health'])
            <div style="font-size:.72rem;color:#b45309;margin-top:.15rem;">Segnalato anche in Sequenza Percorsi qui sotto — probabilmente la stessa causa.</div>
          @endif
        </li>
      @endforeach
    </ul>
  @endif
</section>

<section style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;margin-bottom:1.25rem;">
  <h2 style="font-size:1rem;margin:0 0 .75rem;">Sequenza Percorsi</h2>
  @if(empty($snapshot['percorsi_order_health']['clusters_with_issues']))
    <p style="font-size:.82rem;color:#6b7280;margin:0;">Nessuna anomalia di sequenza o posizione rilevata.</p>
  @else
    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.4rem;">
      @foreach($snapshot['percorsi_order_health']['clusters_with_issues'] as $row)
        <li style="font-size:.85rem;"><a href="{{ route('admin.content-clusters.edit', $row['cluster_id']) }}">{{ $row['name'] }}</a></li>
      @endforeach
    </ul>
  @endif

  @if(! empty($snapshot['percorsi_order_health']['clusters_with_advisories_only']))
    <div style="margin-top:1rem;padding-top:.85rem;border-top:1px dashed #e5e7eb;">
      <div style="font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#92400e;margin-bottom:.4rem;">
        Segnali editoriali ({{ $snapshot['percorsi_order_health']['editorial_advisory_count'] }}) — informativi, non bloccanti
      </div>
      <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.4rem;">
        @foreach($snapshot['percorsi_order_health']['clusters_with_advisories_only'] as $row)
          <li style="font-size:.85rem;color:#92400e;"><a href="{{ route('admin.content-clusters.edit', $row['cluster_id']) }}">{{ $row['name'] }}</a></li>
        @endforeach
      </ul>
    </div>
  @endif
</section>

<section style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;">
  <h2 style="font-size:1rem;margin:0 0 .75rem;">SEO</h2>
  @if(empty($snapshot['seo']['articles']))
    <p style="font-size:.82rem;color:#6b7280;margin:0;">Nessun articolo in archivio.</p>
  @else
    <p style="font-size:.82rem;color:#6b7280;">
      {{ $snapshot['seo']['summary']['analyzed'] }} articoli analizzati —
      {{ $snapshot['seo']['summary']['canonical_warnings'] }} con canonical non valido,
      {{ $snapshot['seo']['summary']['duplicate_effective_titles'] }} con titolo effettivo duplicato,
      {{ $snapshot['seo']['summary']['duplicate_effective_descriptions'] }} con description effettiva duplicata.
      Dettaglio completo nella pagina di modifica di ciascun articolo.
    </p>
  @endif
</section>

<section style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;margin-top:1.25rem;">
  <h2 style="font-size:1rem;margin:0 0 .75rem;">Opportunità</h2>
  @if(empty($snapshot['opportunita']['items']))
    <p style="font-size:.82rem;color:#6b7280;margin:0;">Nessuna opportunità editoriale rilevata al momento.</p>
  @else
    <p style="font-size:.78rem;color:#6b7280;margin:0 0 .6rem;">
      {{ $snapshot['opportunita']['total'] }} opportunità rilevate — le prime {{ count($snapshot['opportunita']['items']) }} in ordine di priorità.
    </p>
    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.55rem;">
      @foreach($snapshot['opportunita']['items'] as $row)
        <li style="font-size:.85rem;">
          @if($row['article_id'])
            <a href="{{ route('admin.articles.edit', $row['article_id']) }}">{{ $row['title'] }}</a>
          @else
            <span>{{ $row['title'] }}</span>
          @endif
          <span style="color:#6b7280;"> — {{ $row['priority'] }} · {{ $row['detected'] }}</span>
          <div style="font-size:.72rem;color:#9ca3af;margin-top:.1rem;">{{ $row['why'] }}</div>
        </li>
      @endforeach
    </ul>
  @endif
</section>

@endsection
