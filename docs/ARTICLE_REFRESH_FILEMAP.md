# Articolo pubblico — Mappa dei file (Cantiere E, Prompt 104)

## Route e controller (sola lettura — non modificabili in questo cantiere)

- `routes/web.php:67` — `Route::get('/articolo/{slug}', [ArticleController::class, 'show'])->name('articolo')`.
- `app/Http/Controllers/ArticleController.php:58-192` — `show()`. Payload passato alla vista (nessuna query/logica da cambiare):
  `article`, `related`, `categoryOptions`, `pathNavigation`, `continuation`,
  `continuationTargetUrl`, `discoverableConcepts`.
- Servizi di sola lettura usati dal controller: `ArticleViewTrackingService`,
  `ArticlePathNavigation`, `ArticleContinuationService`, `ArticleRelatedService`,
  `ContinuationAnalyticsService`, `ContentGraphService`.

## Vista principale (modificabile)

- `resources/views/articolo.blade.php` — layout, `@php` di preparazione dati
  (categoria, cover fallback, split `body`/`Fonti`, TOC), stile inline
  (reading-progress bar + override statico TOC/aside).

## Partial (modificabili, tutte esclusive dell'articolo salvo dove segnalato)

| Partial | Ruolo | Condivisa con |
|---|---|---|
| `articles/partials/breadcrumb.blade.php` | Breadcrumb visibile | — |
| `articles/partials/hero.blade.php` | H1, cover, kicker categoria, excerpt, meta (autore/data/lettura/views) | — |
| `articles/partials/toc.blade.php` (×2, `mobile`/`desktop`) | Indice h2/h3 | — |
| `articles/partials/body.blade.php` | Info cover (`<details>`), corpo HTML/testo, pannello "Fonti" legacy | — |
| `articles/partials/path-continuation.blade.php` | Navigazione Percorso (prec/succ, progress, subscribe-form) | `content-clusters/partials/subscribe-form.blade.php` (condivisa) |
| `articles/partials/continue-reading.blade.php` | CTA "Continua da qui" (URL firmato) | — |
| `articles/partials/newsletter-band.blade.php` | Form iscrizione inline (`source=article`) | Stesso pattern di `home/partials/newsletter-band.blade.php` (già adottato Cantiere D) |
| `articles/partials/related-articles.blade.php` | Griglia 3 card correlati | — |
| `articles/partials/author-card.blade.php` | Mini-card autore in sidebar (foto/iniziali, nome, link profilo) | — |
| `articles/partials/share-card.blade.php` | Link di condivisione social | — |
| `articles/partials/structured-data.blade.php` | JSON-LD `NewsArticle` + `BreadcrumbList` | — |
| `articles/partials/scripts.blade.php` | JS reading-progress, copy-link, TOC scroll/observer | — |

## Componenti e partial condivise con altre superfici (sola lettura salvo dove serve un ritocco CSS confinato)

- `resources/views/components/responsive-image.blade.php` — usato ovunque (home, Percorsi, articolo). Non toccare la logica, solo eventuale CSS di presentazione via classi aggiuntive.
- `resources/views/components/media/image-viewer.blade.php` — lightbox, condiviso con dettaglio Percorso.
- `resources/views/components/sidebar.blade.php` — condiviso con `notizie.blade.php`, `categoria.blade.php`, `ricerca.blade.php`, `autore.blade.php`. **Fuori perimetro di questo cantiere** (verrà toccato, se necessario, nei cantieri F/G dedicati); qui lo si include senza modificarlo.
- `resources/views/content-clusters/partials/subscribe-form.blade.php` — già marcata read-only nel Cantiere D (form "Avvisami"); stessa regola qui.
- `resources/views/partials/content-clusters-analytics.blade.php` — emettitore JS di analytics Percorso, condiviso con le pagine Percorso. Non toccare.

## CSS

- **Base (condiviso con tutto il sito, sola lettura in questo cantiere):**
  `public/css/style.css`, `frontend-hardening.css`, `public-premium.css`
  (definisce `.article-premium*`, `.public-shell`, `.cover-info*`, `.public-card`,
  `.related-premium-grid`, condivisi con notizie/categoria), `public-unified.css`,
  `premium-fixes.css`.
- **Specifiche di pagina (sola lettura):** `media-lightbox.css`,
  `content-clusters.css` (condivisa con Percorsi).
- **Nuove regole di questo cantiere:** esclusivamente in
  `public/css/editorial-system.css`, classi `.kairus-*`/token `--kairus-*`,
  come per i cantieri precedenti.
- **File orfano trovato, escluso:** `public/css/article-extras.css` — non
  referenziato da nessuna vista (`grep -rl "article-extras" resources/views`
  vuoto). Non toccato, non rimosso (fuori perimetro dell'audit CSS dedicato).

## Test esistenti sulla superficie (da eseguire dopo ogni gruppo di modifiche)

`ArticleSeoMetaTest`, `ArticleStructuredDataTest`, `ArticleBreadcrumbStructuredDataTest`,
`ArticleBreadcrumbVisibleTest`, `ArticleCoverMetadataTest`, `ArticleCoverImageViewerTest`,
`ArticleTableOfContentsTest` (+ Unit), `ArticleContinueReadingUiTest`,
`ArticlePathNavigationTest`, `RelatedArticlesExcludePathNavigationTest`,
`PublicSurfaceResponsiveImageTest`, `Unit/ArticleReadMinutesTest`,
`GrowthS2E2ECertificationTest`, `SecondReadAnalyticsTest`, `SecondReadAnalyticsV2Test`,
`ContentGraph/ContentGraphPublicStructuredDataTest`, `PublicPageQueryBudgetTest`,
`OrganicGrowthSeoRegressionTest`, `ScheduledArticlePublicSurfaceLeakAuditTest`,
`ArticleViewTrackingTest`.

## Sovrapposizione rilevata — Trust Layer pubblico (NON in questa catena di branch)

L'audit ha trovato un intero filone "Trust Layer" con codice reale, ma
**su branch non mergiati**, assenti da `main` e da questa catena
`feat/kairus-*`:

- `feat/public-article-sources-v1` (PR #516) — `ArticlePrimarySourcesParser` +
  `<x-article.primary-sources>`, renderizza `Article::primary_sources` come
  pannello strutturato subito dopo `body.blade.php`.
- `feat/public-article-revision-transparency-v1` (PR #517) —
  `ArticleRevisionTransparencyService`, aggiunge "Aggiornato il ..." in
  hero e `dateModified` condizionale nel JSON-LD.
- `feat/public-author-pages-v1` (PR #518) — eligibilità pagina autore
  pubblica, non tocca `articolo.blade.php`.
- `feat/trust-policy-pages-v1` (PR #519) — pagine statiche `/metodologia`
  ecc., non tocca l'articolo.

**Decisione per questo cantiere**: non fondere, non fare cherry-pick, non
anticipare questo lavoro (vietato dalle regole globali: niente merge/rebase/
cherry-pick). Il Cantiere E adotta i componenti Kairus sulla presentazione
delle fonti **realmente attiva oggi in questo branch**: il pannello "Fonti"
legacy, ricavato da `explode('---', $article->body)` in
`articolo.blade.php:92-94` e renderizzato in `body.blade.php:72-77`. La
colonna `primary_sources` esiste nel DB ma non è renderizzata da nessuna
vista pubblica in questa catena di branch (confermato da
`ArticleStructuredDataTest::test_citation_is_not_derived_from_unstructured_primary_sources`).
Il Cantiere H (Trust Layer pubblico, più avanti in questa sequenza) opererà
sullo stesso perimetro reale — non sul codice non mergiato — e documenterà
di nuovo questa sovrapposizione.
