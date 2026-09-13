{{--
    Checklist certificazione primo piano editoriale (Cantiere 36,
    programma 100-cantieri Kairus) — un ASSISTENTE, mai un blocco: stesso
    principio di partials/editorial-quality-gate.blade.php, non impedisce
    mai di salvare o mantenere un articolo "in evidenza". Richiede
    $featuredCertification (array{findings: list<string>, ready: bool},
    App\Services\EditorialQuality\FeaturedArticleCertificationService,
    calcolato server-side, sola lettura) — mostrato solo quando
    l'articolo è correntemente marcato "in evidenza" (vedi
    ArticleController::edit()).
--}}
<div style="background:var(--color-white, #fff);border-radius:var(--radius, 8px);box-shadow:var(--shadow, 0 1px 3px rgba(0,0,0,.08));padding:1.25rem;">
  <div style="font-family:var(--font-ui, inherit);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.75rem;">
    Certificazione primo piano
  </div>

  @if($featuredCertification['ready'])
  <p style="margin:0;font-size:.82rem;color:#15803d;font-weight:600;">Nessuna criticità — pronto per il primo piano.</p>
  @else
  <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.4rem;">
    @foreach($featuredCertification['findings'] as $finding)
    <li style="font-size:.78rem;display:flex;gap:.4rem;align-items:flex-start;color:#b45309;">
      <span aria-hidden="true" style="flex-shrink:0;">⚠</span>
      <span>{{ \App\Services\EditorialQuality\FeaturedArticleCertificationService::label($finding) }}</span>
    </li>
    @endforeach
  </ul>
  @endif

  <p class="form-hint" style="margin:.6rem 0 0;font-size:.7rem;">
    Segnalazioni per l'articolo marcato "in evidenza" (hero homepage) — mai bloccanti, non impediscono di salvare né di mantenerlo in evidenza.
  </p>
</div>
