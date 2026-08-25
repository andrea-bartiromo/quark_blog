# Missione 45 — Import freshness

**Batch**: KAIRUS — Operazioni editoriali (Missioni 25–74), Fase F — Search
Intelligence.
**Esito**: implementazione mirata — un vero gap di visibilità colmato,
nessuna soglia numerica inventata.

## Requisito

Approfondire la valutazione di "freschezza" dei dati Search Console
(outcome, non un'implementazione specifica imposta dalla spec) — compito
esplicitamente deferito qui dalla docblock di `SearchConsoleFreshnessService`
(Missione 34): "Nessuna soglia di staleness, nessuna cronologia import qui
[...] compito dedicato della Fase F (Missione 45)."

## Attenzione esplicita a non inventare una soglia numerica

Verificato che nessuna soglia di staleness ("quanti giorni sono troppi")
è definita da nessuna parte nel repository — stesso principio già
applicato ripetutamente in questo batch (`ArticleContentHealthService::
freshness()`, `PublicationCadenceService`). Nessun riferimento al delay di
pubblicazione dati dell'API di Search Console (~2-3 giorni, un fatto reale
ma mai documentato nel codebase) è mai comparso nel codice o nei docs.
Nessuna soglia è stata quindi inventata qui.

## Stato reale trovato — il gap era la cronologia, non una soglia

`SearchConsoleCsvImporter` è già idempotente per-periodo: un nuovo import
sostituisce solo le righe con lo stesso `period_start`/`period_end`, mai le
altre. Import di periodi diversi si accumulano quindi davvero nel tempo
(batch distinti, `import_batch` e `imported_at` differenti — confermato da
`SearchOpportunityController::availablePeriods()`, che già interroga
`period_start`/`period_end` distinti). Ma nessuna vista mostrava mai questa
cronologia: la pagina `/admin/opportunita-di-ricerca` mostrava solo
"import più recente tra N disponibili" — un conteggio, mai un elenco.

## Implementazione

`SearchConsoleFreshnessService::importHistory()`: un import per riga
(raggruppato per `import_batch`/periodo, riusando lo stesso schema
`GROUP BY` già implicito nell'idempotenza-per-periodo dell'importer — mai
una nuova regola), più recente per primo. Wired in
`SearchOpportunityController::index()` e mostrato come sezione
`<details>` "Cronologia import" (progressive disclosure, stesso pattern
già usato dalla card "Qualità editoriale") sulla pagina esistente —
nessuna nuova pagina, nessun nuovo nav-link, nessun rischio di collisione
icona (lezione della Missione 42).

## Verifica

- `php artisan test --filter=SearchConsoleFreshnessServiceTest` — 4/4
  (2 nuovi: cronologia vuota, cronologia con più batch in ordine corretto).
- `test_index_shows_the_import_history_across_multiple_periods` — prova
  che la cronologia compaia davvero sulla pagina reale.
- `php artisan test --filter='SearchConsole|SearchOpportunity'` —
  62/62 passed.
- `php artisan test --filter='EditorialOperationsDashboard'` — 66/66
  passed (nessuna regressione: `summary()` non modificato).
