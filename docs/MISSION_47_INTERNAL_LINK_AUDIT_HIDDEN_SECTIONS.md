# Missione 47 — Sezioni nascoste della pagina Link interni

**Fase F — Search Intelligence** (secondo batch autonomo KAIRUS).

## Gap trovato

`InternalLinkAuditService::audit()` calcola già, per ogni esecuzione,
due elenchi pronti all'azione e li porta fino a `InternalLinkAuditReport`:

- `scheduledWithoutInternalLinks` — articoli programmati che usciranno
  senza alcun link interno in uscita (gap pre-pubblicazione, distinto
  dal caso "isolato" già mostrato, che riguarda solo articoli
  pubblicati).
- `highConfidenceUnusedSuggestions` — suggerimenti di collegamento
  interno ancora `proposed`, con punteggio di confidenza ≥ 70 e già
  filtrati per sicurezza temporale (`InternalLinkTemporalEligibility`),
  quindi davvero inseribili subito.

Entrambi erano già coperti da test a livello di servizio
(`InternalLinkAuditCommandTest`), ma
`resources/views/admin/internal-link-audit/index.blade.php` renderizzava
solo `publishedWithoutIncomingLinks` — gli altri due restavano dati
morti, mai visti da un redattore sulla pagina reale
`/admin/link-interni`.

## Soluzione

Aggiunte due nuove sezioni `<section class="admin-card">` alla vista,
subito dopo "Pubblicati senza incoming links", visibili solo quando la
rispettiva lista non è vuota (stesso pattern condizionale già in uso):

- "Programmati senza link interni" — elenco semplice con link a
  `admin.articles.edit`, stesso stile della sezione già esistente.
- "Opportunità di collegamento ad alta confidenza" — tabella
  Da/A/Anchor/Confidenza, con link sia sulla sorgente sia sulla
  destinazione verso la pagina di modifica dell'articolo (dove
  l'inserimento/ignora reale avviene già tramite
  `ArticleLinkSuggestionController`, mai duplicato qui: questa pagina
  resta sola lettura).

Nessuna nuova icona di navigazione è stata aggiunta (nessun rischio di
collisione emoji nella sidebar, vedi la lezione della Missione 42).

## File toccati

- `resources/views/admin/internal-link-audit/index.blade.php`
- `tests/Feature/Admin/InternalLinkAuditControllerTest.php`

## Test

- `test_editor_sees_a_scheduled_article_without_internal_links`
- `test_editor_sees_a_high_confidence_unused_suggestion`
- `test_neither_new_section_appears_when_nothing_qualifies`

Suite `InternalLinkAudit*` completa: 48 test, 113 assertion, verde.
Repeatability gate: 5/5 run consecutivi verdi.
