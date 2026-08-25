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

@php
  $health = $snapshot['salute_operativa'];
@endphp
<div style="display:flex;align-items:center;gap:.85rem;padding:.85rem 1.1rem;border-radius:var(--radius);margin-bottom:1.25rem;background:{{ $health['status'] === 'SANA' ? '#ecfdf5' : '#fffbeb' }};border:1px solid {{ $health['status'] === 'SANA' ? '#a7f3d0' : '#fde68a' }};">
  <span aria-hidden="true" style="font-size:1.4rem;">{{ $health['status'] === 'SANA' ? '✅' : '⚠️' }}</span>
  <div>
    <strong style="color:{{ $health['status'] === 'SANA' ? '#065f46' : '#92400e' }};">
      @if($health['status'] === 'SANA')
        Macchina editoriale sana — nessun problema aperto.
      @else
        {{ $health['open_problems_total'] }} {{ $health['open_problems_total'] === 1 ? 'problema aperto' : 'problemi aperti' }} da rivedere.
      @endif
    </strong>
    <div style="font-size:.78rem;color:#6b7280;margin-top:.15rem;">{{ $health['published_articles_total'] }} {{ $health['published_articles_total'] === 1 ? 'articolo pubblicato' : 'articoli pubblicati' }} · {{ $health['active_percorsi_total'] }} {{ $health['active_percorsi_total'] === 1 ? 'Percorso attivo' : 'Percorsi attivi' }} ora.</div>
  </div>
</div>

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
          @if($row['overdue'])
            <span style="color:#b91c1c;font-weight:600;"> · in ritardo</span>
          @endif
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
          <span style="font-weight:600;color:{{ $row['priority'] === 'HIGH' ? '#b91c1c' : '#92400e' }};">{{ $row['priority'] }}</span>
          <a href="{{ route('admin.articles.edit', $row['article_id']) }}">{{ $row['title'] }}</a>
          <div style="font-size:.72rem;color:#9ca3af;margin-top:.1rem;">{{ implode(' · ', $row['reason_summary']) }}</div>
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
        <li style="font-size:.85rem;">
          <a href="{{ route('admin.articles.edit', $row['id']) }}">{{ $row['title'] }}</a>
          @if($row['published_at'])
            <span style="color:#6b7280;"> — pubblicato il {{ \Illuminate\Support\Carbon::parse($row['published_at'])->timezone('Europe/Rome')->translatedFormat('d M Y') }}</span>
          @endif
        </li>
      @endforeach
    </ul>
  @endif
</section>

<section style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;margin-bottom:1.25rem;">
  <h2 style="font-size:1rem;margin:0 0 .75rem;">Pubblicati senza Concept</h2>
  @if(empty($snapshot['contenuti_senza_concept']))
    <p style="font-size:.82rem;color:#6b7280;margin:0;">Ogni articolo pubblicato è collegato ad almeno un Concept del Content Graph.</p>
  @else
    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.4rem;">
      @foreach($snapshot['contenuti_senza_concept'] as $row)
        <li style="font-size:.85rem;">
          <a href="{{ route('admin.articles.edit', $row['id']) }}">{{ $row['title'] }}</a>
          @if($row['published_at'])
            <span style="color:#6b7280;"> — pubblicato il {{ \Illuminate\Support\Carbon::parse($row['published_at'])->timezone('Europe/Rome')->translatedFormat('d M Y') }}</span>
          @endif
        </li>
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
  @php
    $gapArticleCount = $snapshot['percorsi_order_health']['published_beyond_gap_article_count'];
    $gapClusterCount = $snapshot['percorsi_order_health']['published_beyond_gap_cluster_count'];
  @endphp
  @if($gapArticleCount > 0)
    <p style="font-size:.82rem;color:#b91c1c;margin:0 0 .75rem;font-weight:600;">{{ $gapArticleCount }} {{ $gapArticleCount === 1 ? 'articolo pubblicato resta invisibile' : 'articoli pubblicati restano invisibili' }} in {{ $gapClusterCount }} {{ $gapClusterCount === 1 ? 'Percorso, bloccato' : 'Percorsi, bloccati' }} dietro un gap nel prefisso pubblico.</p>
  @endif
  @if(empty($snapshot['percorsi_order_health']['clusters_with_issues']))
    <p style="font-size:.82rem;color:#6b7280;margin:0;">Nessuna anomalia di sequenza o posizione rilevata.</p>
  @else
    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.4rem;">
      @foreach($snapshot['percorsi_order_health']['clusters_with_issues'] as $row)
        <li style="font-size:.85rem;">
          <a href="{{ route('admin.content-clusters.edit', $row['cluster_id']) }}">{{ $row['name'] }}</a>
          @if(! empty($row['codes']))
            <div style="font-size:.72rem;color:#9ca3af;margin-top:.15rem;">{{ implode(' · ', $row['codes']) }}</div>
          @endif
        </li>
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
          <li style="font-size:.85rem;color:#92400e;">
            <a href="{{ route('admin.content-clusters.edit', $row['cluster_id']) }}">{{ $row['name'] }}</a>
            @if(! empty($row['codes']))
              <div style="font-size:.72rem;margin-top:.15rem;">{{ implode(' · ', $row['codes']) }}</div>
            @endif
          </li>
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
    @if(! empty($snapshot['seo']['violations']))
      <ul style="list-style:none;padding:0;margin:.75rem 0 0;display:flex;flex-direction:column;gap:.4rem;">
        @foreach($snapshot['seo']['violations'] as $row)
          <li style="font-size:.85rem;">
            <span style="font-weight:600;color:{{ $row['priority'] === 'HIGH' ? '#b91c1c' : '#92400e' }};">{{ $row['priority'] }}</span>
            <a href="{{ route('admin.articles.edit', $row['article_id']) }}">{{ $row['title'] }}</a>
            <div style="font-size:.72rem;color:#9ca3af;margin-top:.1rem;">{{ implode(' · ', $row['reasons']) }}</div>
          </li>
        @endforeach
      </ul>
    @endif
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
