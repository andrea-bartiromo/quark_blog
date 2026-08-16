{{--
  Qualità editoriale (Editorial Quality Gate V1) — un ASSISTENTE, mai un
  blocco: non impedisce mai il salvataggio o la pubblicazione, si limita a
  segnalare cosa Kairus può verificare con certezza prima che l'articolo
  vada online. Richiede $qualityReport (App\Services\EditorialQuality\
  EditorialQualityReport, calcolato server-side, sola lettura).

  Progressive disclosure: solo il riepilogo (livello + N/M controlli) è
  sempre visibile; l'elenco dei singoli controlli è dietro <details>, non
  occupa spazio finché non viene aperto.
--}}
@php
  $levelColors = [
    \App\Services\EditorialQuality\EditorialQualityReport::LEVEL_READY => '#15803d',
    \App\Services\EditorialQuality\EditorialQualityReport::LEVEL_ATTENTION => '#b45309',
    \App\Services\EditorialQuality\EditorialQualityReport::LEVEL_INCOMPLETE => '#b91c1c',
  ];
  $levelColor = $levelColors[$qualityReport->level()] ?? '#6b7280';
  $statusIcons = [
    \App\Services\EditorialQuality\EditorialQualityCheckResult::STATUS_PASS => '✓',
    \App\Services\EditorialQuality\EditorialQualityCheckResult::STATUS_WARNING => '⚠',
    \App\Services\EditorialQuality\EditorialQualityCheckResult::STATUS_FAIL => '⚠',
  ];
@endphp
<div style="background:var(--color-white, #fff);border-radius:var(--radius, 8px);box-shadow:var(--shadow, 0 1px 3px rgba(0,0,0,.08));padding:1.25rem;">
  <div style="font-family:var(--font-ui, inherit);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.75rem;">
    Qualità editoriale
  </div>

  <div style="display:flex;align-items:baseline;gap:.5rem;margin-bottom:.25rem;">
    <span style="font-weight:700;color:{{ $levelColor }};">{{ $qualityReport->levelLabel() }}</span>
    <span style="font-size:.8rem;color:#6b7280;">{{ $qualityReport->passedCount() }}/{{ $qualityReport->applicableCount() }} controlli superati</span>
  </div>

  @if($qualityReport->issues() !== [])
  <details style="margin-top:.6rem;">
    <summary style="cursor:pointer;font-size:.78rem;color:#374151;">Dettagli</summary>
    <ul style="list-style:none;margin:.6rem 0 0;padding:0;display:flex;flex-direction:column;gap:.4rem;">
      @foreach($qualityReport->results as $result)
        @if($result->isApplicable())
        <li style="font-size:.78rem;display:flex;gap:.4rem;align-items:flex-start;">
          <span style="color:{{ $result->status === 'pass' ? '#15803d' : ($result->status === 'fail' ? '#b91c1c' : '#b45309') }};flex-shrink:0;">{{ $statusIcons[$result->status] ?? '·' }}</span>
          <span>
            <strong>{{ $result->label }}</strong>
            @if($result->status !== 'pass')
              — {{ $result->message }}
            @endif
          </span>
        </li>
        @endif
      @endforeach
    </ul>
  </details>
  @else
  <p class="form-hint" style="margin:.4rem 0 0;">Nessuna segnalazione — tutti i controlli applicabili sono superati.</p>
  @endif

  <p class="form-hint" style="margin:.6rem 0 0;font-size:.7rem;">
    Verifica la completezza editoriale (titolo, media, SEO, struttura, fonti), mai l'accuratezza scientifica del contenuto — non blocca mai il salvataggio.
  </p>
</div>
