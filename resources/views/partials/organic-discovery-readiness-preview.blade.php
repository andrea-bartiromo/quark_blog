{{--
  Anteprima "Ricerca organica" (Cantiere 3, programma "Kairus Organic
  Discovery") — un ASSISTENTE non bloccante, mai un gate: non impedisce
  mai il salvataggio o la pubblicazione. Richiede $organicDiscoveryPreview
  (array da OrganicDiscoveryReadinessService::previewForArticle(), sola
  lettura) e $article.

  Deliberatamente un'anteprima leggera (vedi il docblock del servizio):
  non replica l'intero OrganicDiscoveryReadinessService::auditAll() —
  quello resta disponibile, con il quadro completo, solo nella pagina
  dedicata "Ricerca organica" per gli articoli già pubblici.
--}}
<div style="background:var(--color-white, #fff);border-radius:var(--radius, 8px);box-shadow:var(--shadow, 0 1px 3px rgba(0,0,0,.08));padding:1.25rem;">
  <div style="font-family:var(--font-ui, inherit);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.75rem;">
    Ricerca organica
  </div>

  @if($organicDiscoveryPreview['findings'] === [])
    <p class="form-hint" style="margin:0;">Nessuna segnalazione tra i controlli disponibili prima della pubblicazione completa.</p>
  @else
    <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.5rem;">
      @foreach($organicDiscoveryPreview['findings_detail'] as $finding)
        <li style="font-size:.78rem;display:flex;gap:.4rem;align-items:flex-start;">
          <span style="color:#b45309;flex-shrink:0;">⚠</span>
          <span>
            <strong>{{ $finding['label'] }}</strong> — {{ $finding['cause'] }}
            <br><span style="color:#6b7280;">{{ $finding['action'] }}</span>
          </span>
        </li>
      @endforeach
    </ul>
  @endif

  @if($article->status === 'published')
    <p class="form-hint" style="margin:.6rem 0 0;font-size:.7rem;">
      Questa è un'anteprima leggera. Per il quadro completo (percorsi di scoperta interna,
      duplicati nel corpus, dati Search Console) vedi
      <a href="{{ route('admin.organic-discovery-readiness.show', $article) }}">Ricerca organica — dettaglio articolo</a>.
    </p>
  @else
    <p class="form-hint" style="margin:.6rem 0 0;font-size:.7rem;">
      Il quadro completo (percorsi di scoperta interna, duplicati nel corpus, dati Search
      Console) sarà disponibile in "Ricerca organica" solo dopo la pubblicazione.
    </p>
  @endif
</div>
