# Mission 25 — Editorial Operations Baseline Audit

**Stato: VERIFIED_ALREADY_PRESENT.** Nessun comportamento modificato. Questa
missione mappa lo stato reale di "Operazioni editoriali" per dare alle
Missioni 26–74 (programma KAIRUS — Operazioni Editoriali) un punto di
partenza verificato, evitando duplicazioni.

## Route

`GET /admin/operazioni-editoriali` → `admin.editorial-operations`
(`routes/web.php`), dentro il gruppo `Route::middleware(['auth',
'editor'])->prefix('admin')`. Nessuna route pubblica equivalente.

## Controller

`App\Http\Controllers\Admin\EditorialOperationsDashboardController::index()`
— un solo metodo, delega interamente a
`EditorialOperationsDashboardService::snapshot()`. Nessuna logica di
dominio nel controller.

## Servizio principale

`App\Services\EditorialOperations\EditorialOperationsDashboardService`
(introdotto "Mission 09" del batch precedente, PR #332). Principio
dichiarato nel suo stesso docblock: **mai ricalcolare qui una regola già
espressa da un servizio esistente — solo aggregare**. `snapshot()` ritorna:

| Chiave | Fonte | Cosa aggrega |
|---|---|---|
| `da_pubblicare` | query diretta su `Article` (scheduled) | Articoli programmati, flag `overdue` |
| `da_sistemare` | `ArticleContentHealthService`, `SourceImageAttributionHealthService` | Warning contenuto/attribuzione, priorità HIGH/MEDIUM |
| `contenuti_isolati` | `PercorsoCoverageAuditService::audit()` | Pubblicati senza Percorso |
| `seo` | `SeoMetadataQualityAuditService::audit()` | Canonical, titoli/description duplicati |
| `percorsi_readiness` | `PercorsoPublicationReadinessService::evaluate()` per cluster | Percorsi NOT READY / READY WITH WARNINGS |
| `percorsi_order_health` | `PercorsoCoverageAuditService::editorialOrderHealth()` | Anomalie di posizione/sequenza, **incluso il conteggio dedicato di publication-gap (Missione 21)** |
| `opportunita` | `EditorialRadarProviderGraphService::opportunities()` | **Già unisce** `EditorialRadarService` (content/attribution/SEO) **e** `SearchConsoleOpportunityProvider` (Search Console) in un solo elenco priorizzato |
| `distribuzione` | statico | Sempre `available: false` — UtmLinkGenerator è deliberatamente stateless, nessun dato aggregabile |

## View

`resources/views/admin/editorial-operations-dashboard.blade.php` —
sette card KPI in testata (Da pubblicare, Da sistemare, Contenuti isolati,
Percorsi non pronti, Sequenza Percorsi con callout gap dedicato, SEO,
Opportunità), poi sezioni dettaglio per ciascuna area, più il banner
"Distribuzione" quando non disponibile.

## Test

- `tests/Feature/EditorialOperationsDashboardServiceTest.php` — 34 test
  (aggregazione, empty state, query budget con ceiling assoluto documentato,
  boundary temporali).
- `tests/Feature/Admin/EditorialOperationsDashboardControllerTest.php` — 33
  test (autorizzazione, rendering, heading hierarchy, ogni link ha una
  destinazione reale, nessuna mutazione da GET, plurali italiani corretti).

Totale: 67 test già dedicati a questa sola pagina.

## Collegamenti verso altre sezioni admin

Ogni card KPI linka già allo strumento reale (Calendario articoli,
Percorsi, tool UTM). La sidebar (`layouts/admin.blade.php:185`) espone la
pagina con icona propria e stato "active" corretto.

## Capability NON ancora presenti in questa dashboard (gap reali per le Missioni 26–34)

Investigate e confermate ASSENTI dallo snapshot attuale, pur esistendo
altrove nel codebase come servizi maturi e riusabili:

- **Content Graph coverage** — `App\Services\ContentGraph\ContentGraphCoverageService::summary()`
  esiste già (Mission 19 del batch precedente, usato da
  `ConceptController::index()`) ma non è mai stato aggregato qui.
- **Second Read / continuazione** — `App\Services\ContinuationAnalyticsService`
  espone `articleBreakdown()`/`statsFor()` per-articolo, ma nessun metodo
  di sintesi sitewide pronto per una card dashboard.
- **Search Console freshness/import history** — `SearchOpportunityController`
  gestisce già l'import, ma nessuno stato di freshness ("ultimo import:
  quando, quante righe") è mai stato aggregato qui.
- **Percorsi operational health nel senso di Missione 31** (attivi ora /
  programmati / completi / in aggiornamento) — `PercorsiActivationCalendarService::summary()`
  (Missione 12, questo batch) già calcola esattamente questi numeri, ma
  non è mai stato wired nella dashboard Operazioni Editoriali (oggi vive
  solo nell'indice `/admin/content-clusters`).

## Duplicazioni verificate: nessuna

Nessuna delle sette sezioni esistenti ricalcola una regola già espressa
altrove — confermato leggendo `EditorialOperationsDashboardService` riga
per riga: ogni sezione chiama un servizio di dominio esistente e aggrega
soltanto conteggi/ordinamento.

## Conclusione

La baseline è solida: architettura di aggregazione pura, già testata a
fondo, già collegata alle altre sezioni admin, già coerente su
autorizzazione. Le Missioni 26–34 (Phase D) hanno un compito chiaro e
circoscritto: **wire dentro `snapshot()` le capability già mature elencate
sopra come gap**, seguendo lo stesso pattern di aggregazione già
stabilito — mai una nuova regola di dominio, solo nuove card che riusano
servizi esistenti.
