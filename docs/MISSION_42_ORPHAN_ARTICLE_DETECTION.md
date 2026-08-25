# Missione 42 — Orphan article detection

**Batch**: KAIRUS — Operazioni editoriali (Missioni 25–74), Fase E — Editorial
Quality & Readiness.
**Esito**: nuova superficie admin per una capacità già completa — nessuna
duplicazione con le Missioni 27/29, nessuna nuova regola di dominio.

## Requisito

Individuare e rendere visibili articoli "orfani" (outcome, non
un'implementazione specifica imposta dalla spec).

## Attenzione esplicita a non duplicare le Missioni 27/29

Due significati di "orfano" sono già completamente coperti sul command
center:

- **Senza Concept** (Missione 27, `ContentGraphOrphanAuditService::
  orphanArticles()`) — un articolo pubblicato non collegato ad alcun
  Concept del Content Graph.
- **Senza Percorso** (`contenuti_isolati`, `PercorsoCoverageAuditService::
  published_without_path`) — un articolo pubblicato non appartenente ad
  alcun Percorso.

Nessuno dei due copre però il significato più letterale e tecnico di
"orfano": un articolo che **nessun altro articolo del sito collega
internamente**. Un articolo può avere un Concept e un Percorso e restare
comunque irraggiungibile dalla normale navigazione da-articolo-ad-articolo
(la strada con cui la maggior parte dei lettori scopre altri contenuti).

## Stato reale trovato

`InternalLinkAuditService` (Internal Linking V2, batch precedente) calcola
già esattamente questo — `InternalLinkAuditRow::isOrphan()`: un articolo
PUBBLICATO con zero collegamenti in entrata da altri articoli. Era
raggiungibile solo via `php artisan content:internal-link-audit` (CLI),
mai da alcuna pagina admin.

## Perché non è stato wired nello snapshot della dashboard V1

`InternalLinkAuditService::audit()` scansiona l'INTERO corpus (tutti gli
stati) e ne analizza il body per costruire il grafo dei collegamenti —
un costo di calcolo per-richiesta non paragonabile alle aggregazioni SQL
leggere già usate da ogni altra sezione della dashboard. Stessa
motivazione, stessa decisione già presa nella Missione 35 per l'Editorial
Quality Gate: tenuto standalone invece di ricalcolarlo a ogni caricamento
del command center.

## Implementazione

Nuovo `InternalLinkAuditController` (`/admin/link-interni`, nome route
`admin.internal-link-audit`) + vista dedicata, riusando integralmente
`InternalLinkAuditService::audit()` — nessuna nuova regola di dominio,
nessun ricalcolo. Nav-link aggiunto al gruppo "Analisi" della sidebar
admin, accanto a "Qualità editoriale".

## Verifica

- `php artisan test --filter=InternalLinkAuditControllerTest` — 7/7
  passed (autorizzazione, stato vuoto, articolo isolato in entrambe le
  sezioni, articolo collegato mai segnalato, bozza mai segnalata, nessuna
  mutazione).
- `php artisan test --filter='InternalLinkAudit|EditorialOperationsDashboard|EditorialQuality'`
  — 211/211 passed.
- `php artisan test --filter='AdminNavigationTest|AdminSidebarCompactToggleTest'`
  — 44/44 passed (nessuna regressione sulla sidebar dall'aggiunta del
  nuovo nav-link).
