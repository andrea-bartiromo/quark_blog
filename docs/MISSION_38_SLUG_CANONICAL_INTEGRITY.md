# Missione 38 — Slug/canonical integrity

**Batch**: KAIRUS — Operazioni editoriali (Missioni 25–74), Fase E — Editorial
Quality & Readiness.
**Esito**: implementazione mirata — due lacune reali di integrità del
canonical colmate, nessuna duplicazione.

## Requisito

Verificare l'integrità di slug e canonical URL degli articoli e rendere
operativamente visibili eventuali problemi (outcome, non un'implementazione
specifica imposta dalla spec).

## Stato reale trovato

### Slug — già completamente protetto

Lo slug è già protetto a livello di database (colonna `articles.slug`
`UNIQUE`) e a livello applicativo (`Article::uniqueSlug()`, con retry
automatico su collisione in creazione/duplicazione — hardening di un batch
precedente, "S6 FASE 4"). Non esiste alcun percorso, nemmeno in scrittura
concorrente, che possa produrre due articoli con lo stesso slug. Nessuna
azione necessaria: la parte "slug" della missione è `VERIFIED_ALREADY_PRESENT`.

### Canonical — sintassi già validata, due lacune reali

`SeoMetadataQualityAuditService::canonicalCheck()` validava già che il
canonical effettivo (`Article::metaCanonicalUrl()`, override esplicito o
fallback alla route dello slug proprio) fosse un URL HTTP(S) assoluto senza
query string né fragment. Mancavano due controlli, entrambi reali problemi
di integrità editoriale, non ipotetici:

1. **Canonical fuori dominio**: nulla impediva a un override esplicito di
   puntare a un dominio diverso dal sito stesso (es. `https://example.com/...`)
   — un canonical del genere dice ai motori di ricerca "il contenuto vero
   vive altrove", de-indicizzando di fatto la pagina reale su Kairus. Il
   fallback (nessun override) non può mai collidere con questo problema,
   perché `route('articolo', $slug)` risolve sempre sul dominio dell'app.
2. **Canonical duplicato tra articoli**: nulla impediva a due articoli
   diversi di avere lo stesso canonical override — un conflitto di
   canonicalizzazione reale (i motori di ricerca non sanno quale sia la
   pagina "vera"), concettualmente identico a `duplicate_effective_title`/
   `duplicate_effective_description` già esistenti nello stesso servizio.

## Implementazione

Entrambe le lacune sono state colmate **nello stesso servizio esistente**
(`SeoMetadataQualityAuditService`), riusando pattern già presenti nel
codebase — nessun nuovo servizio, nessuna nuova pagina:

- `canonicalCheck()`: aggiunto un confronto host case-insensitive contro
  `config('app.url')`, stesso identico pattern già usato da
  `ArticleLinkInsertionService::isSafeInternalUrl()`. Nuovo motivo WARNING:
  "Canonical effettivo punta a un dominio diverso dal sito."
- `audit()`: aggiunto `duplicate_canonical_url` per riga, calcolato con lo
  stesso schema `countBy()`/`normalize()` già usato per titolo/description
  duplicati, più `duplicate_canonical_urls` nel summary.
- `EditorialOperationsDashboardService::seoViolations()`: il canonical
  fuori dominio eredita automaticamente la stessa priorità HIGH già
  assegnata al canonical sintatticamente rotto (stesso campo `status`). Il
  duplicato è stato aggiunto esplicitamente come terzo motivo, anch'esso
  HIGH (un conflitto di canonicalizzazione è grave quanto un canonical
  rotto, non un semplice duplicato di copy come titolo/description).

Nessuna vista modificata: la sezione "SEO" della dashboard e la sua lista
`reasons` erano già generiche e mostrano automaticamente il nuovo motivo.

## Verifica

- `php artisan test --filter=SeoMetadataQualityAuditServiceTest` — 8/8
  passed (4 nuovi test: dominio diverso, fallback mai segnalato, duplicato
  tra due articoli).
- `test_editor_sees_a_duplicate_canonical_violation_row_rendered_in_the_seo_section`
  in `EditorialOperationsDashboardControllerTest` — prova che il nuovo
  motivo compaia davvero nell'HTML reale della pagina, con priorità HIGH.
- `php artisan test --filter='EditorialOperationsDashboard|SeoMetadataQualityAudit|EditorialQuality|EditorialRadar|ArticleSeoMeta|ArticleSeoFields|HttpsCanonicalization|ArticleBreadcrumbStructuredData|ArticleStructuredData|ContentClusterStructuredData'`
  — 255/255 passed (nessuna regressione sui numerosi test esistenti che
  usano `canonical_url` con domini esterni per scopi non correlati a
  questo servizio, es. escaping HTML, structured data — verificato che
  nessuno di essi invochi `SeoMetadataQualityAuditService`).
