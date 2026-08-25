# Missione 36 — Metadata completeness

**Batch**: KAIRUS — Operazioni editoriali (Missioni 25–74), Fase E — Editorial Quality & Readiness.
**Esito**: `VERIFIED_ALREADY_PRESENT` (+ una copertura di regressione HTTP mancante, genuinamente utile, aggiunta).

## Requisito

Verificare la completezza dei metadati editoriali (SEO title/description,
canonical, social — Open Graph/Twitter) e renderla operativamente
visibile (outcome, non un'implementazione specifica imposta dalla spec).

## Stato reale trovato

La capacità è già completa e stratificata su più livelli, tutti già su
`main` prima di questa missione:

1. **Fallback-first by design** (`App\Models\Article`): ogni campo SEO/
   social ha una catena di fallback esplicita e mai vuota —
   `metaTitle()`/`metaDescription()` cadono su titolo/estratto,
   `metaOgTitle()`/`metaOgDescription()`/`metaTwitterTitle()`/
   `metaTwitterDescription()` cadono su `metaTitle()`/`metaDescription()`,
   `metaOgImage()`/`metaTwitterImage()` cadono su `cover_image` e infine
   su un raster globale (`config('laboratorio.default_share_image')`,
   mai un SVG placeholder). Un articolo non ha MAI metadati social
   realmente mancanti in output, anche se i campi override sono vuoti.
   Questo comportamento è già coperto da 9 test dedicati in
   `tests/Feature/ArticleSeoMetaTest.php` (fallback title/description/
   image per OG e Twitter, override espliciti, e il caso limite "mai il
   placeholder SVG").
2. **`SeoMetadataQualityAuditService`** (Fase D, missioni precedenti):
   calcola già, per ogni articolo, `canonical` (validità sintattica URL
   assoluto HTTP/S, assenza di query/fragment), `duplicate_effective_title`
   e `duplicate_effective_description` (confronto sull'intero corpus,
   non solo sul sottoinsieme filtrato), oltre a `social_metadata`
   (presenza dei campi override grezzi — diagnostica, non violazione: dato
   il fallback sempre completo sopra, un override assente non è un
   problema editoriale reale, e trattarlo come tale produrrebbe falsi
   positivi contro un sistema già corretto).
3. **Wiring nel dashboard**: `EditorialOperationsDashboardService`
   espone già `seo.summary` (KPI "SEO" con `canonical_warnings`) e
   `seo.violations` (canonical rotto + titolo/description duplicati,
   priorità HIGH per canonical), sulla pagina Operazioni editoriali dalla
   Fase D (missioni precedenti a questo batch).
4. **`EditorialQualityChecker`** (categoria `CATEGORY_SEO`:
   `seoTitleCheck`, `metaDescriptionCheck`, `indexabilityCheck`): già
   esposto sia per singolo articolo (card "Qualità editoriale" nella
   pagina di modifica) sia sitewide (Missione 35, `/admin/qualita-editoriale`).

Nessuna duplicazione necessaria: la combinazione di queste quattro
capacità copre già l'intero outcome richiesto dalla Missione 36.

### Perché `social_metadata` non è stato promosso a violazione

`SeoMetadataQualityAuditService::socialMetadataCheck()` calcola già i
booleani `og_title_present`/`twitter_image_present`/ecc., ma non sono mai
stati usati altrove nell'app (confermato via ricerca sitewide). Non è un
gap da colmare: dato che ogni campo ha già un fallback completo e
testato, un override assente NON rappresenta un metadato realmente
incompleto — promuoverlo a violazione produrrebbe una dashboard
decorativa che segnala problemi inesistenti, in contrasto con il
principio della missione ("ogni indicatore deve produrre un'azione o una
diagnosi utile").

## Gap reale trovato e colmato

Il livello SERVIZIO era testato (`test_seo_violations_are_scoped_to_published_and_scheduled_only`
in `EditorialOperationsDashboardServiceTest`), ma nessun test HTTP aveva
mai provato che una riga di violazione SEO comparisse davvero nella
sezione "SEO" della pagina reale — stesso pattern di gap già trovato e
colmato per Percorsi (Missione 31) e per l'ordinamento cronologico delle
pubblicazioni (Missione 28).

Aggiunto `test_editor_sees_a_seo_violation_row_rendered_in_the_seo_section`
in `tests/Feature/Admin/EditorialOperationsDashboardControllerTest.php`:
crea un articolo pubblicato con un `canonical_url` sintatticamente non
valido e verifica che titolo, link di modifica e badge di priorità HIGH
compaiano nell'HTML reale della pagina.

Nessuna modifica al servizio o alla vista è stata necessaria: si tratta
di sola copertura di regressione su un comportamento già corretto.

## Verifica

- `php artisan test --filter=EditorialOperationsDashboard` — 55/55 passed.
- Nessuna nuova query, nessuna nuova regola di dominio, nessun nuovo file
  di produzione modificato.
