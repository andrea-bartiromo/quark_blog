# Programma "Kairus Organic Discovery": tracking

Fonte di verità per lo stato del programma "Kairus Organic Discovery"
assegnato in sessione (istruzione utente verbatim, non riportata qui per
brevità — vedi la cronologia della sessione). Obiettivo: rendere gli
articoli Kairus scopribili per domande generiche e non-brand attraverso
qualità editoriale, architettura interna, dati Search Console e
misurazione — mai tramite meccanismi artificiali di posizionamento.

Regole permanenti valide per tutto il programma:

- Un solo cantiere per branch e PR; squash merge automatico se non
  emergono finding aperti, conflitti o fallimenti nuovi.
- Ogni cantiere è preceduto da un'ispezione del repository (agenti Explore
  in background) per non duplicare modelli, fonti di verità o audit già
  esistenti — si estende, non si duplica.
- Nessun deploy, nessuna email reale, nessuna chiamata OAuth/API Search
  Console reale finché non esiste una configurazione esplicita.
- Nessuna pubblicazione, modifica automatica di corpo/titolo/SEO
  title/slug/canonical/redirect: il sistema prepara/verifica/propone,
  l'editor umano decide e resta tracciabile.
- Fail-closed: dati ambigui, errore, o contenuto non pubblico non vengono
  mai associati o suggeriti come pubblici; il motivo viene registrato.
- Draft, scheduled, categorie e Percorsi non pubblici non compaiono mai in
  audit, sitemap, suggerimenti o dati pubblici come contenuti raggiungibili.
- Tutti gli audit sono spiegabili, deterministici e testabili — mai un
  punteggio opaco senza causa e azione suggerita.
- `ContentClusterAutoLifecycleCompletionTest` (file/riga/messaggio
  identici a quelli già osservati nel programma "100 cantieri Kairus",
  canonicamente riga 231) e le due flake GitHub-sync di
  `ProjectModelTest`/`ProjectTaskControllerTest` restano pre-esistenti e
  non bloccanti, mai modificate.

Questo file viene aggiornato dopo ogni merge (o dopo ogni tentativo, se un
cantiere risulta bloccato o già coperto da lavoro esistente).

## Legenda stato

`pending` · `in_progress` · `merged` · `covered-by-existing` (già presente
nel repository, nessuna PR necessaria) · `blocked` (dipendenza non
soddisfatta o richiede dato/decisione fuori standing authorization)

## Tabella

| # | Cantiere | Stato | PR | SHA merge | Test | Finding | Dipendenze |
|---|---|---|---|---|---|---|---|
| 1 | Baseline e affidabilità dei dati Search Console | merged | [#586](https://github.com/andrea-bartiromo/quark_blog/pull/586) | `bfc3893` | 124/124 (SearchConsole+SearchConsoleBaselineReportController+SearchOpportunityController+AdminNavigation, 416 assert.); suite CI completa: 4518 passed, 11 skipped, 1 pre-esistente (`ContentClusterAutoLifecycleCompletionTest.php:231`) | 2 reali (fixati: righe di copertura per property/tipo di report ormai sostituiti non rimosse su reimport dello stesso periodo; card di drill-down verso le opportunità del periodo sbagliato quando selezionato un periodo storico) | — |
| 2 | Profilo editoriale di ricerca per articolo | pending | — | — | — | — | 1 |
| 3 | Prontezza organica e scoperta interna | pending | — | — | — | — | 1, 2 |
| 4 | Dalle opportunità Search Console alle decisioni editoriali | pending | — | — | — | — | 1 |
| 5 | Cannibalizzazione di ricerca | pending | — | — | — | — | 1, 2 |
| 6 | Salute di indicizzazione e sitemap | pending | — | — | — | — | 3 |
| 7 | Monitoraggio e report operativo | pending | — | — | — | — | 1, 3, 4 |
| 8 | Strategia editoriale per cluster e autorevolezza | pending | — | — | — | — | 2, 3 |

## Note per cantiere

### Cantiere 1 — Baseline e affidabilità dei dati Search Console

Ispezione preliminare (agente Explore in background) confermata: nessuna
duplicazione. `SearchConsoleCsvImporter`, `SearchOpportunityScoringService`,
`SearchConsoleFreshnessService`, `SearchZeroResultQuery` già coprivano
idempotenza per periodo, matching query→articolo senza invenzioni,
posizione 11-20, CTR basso, nessuna landing page forte. Il gap reale era la
copertura effettiva (property/tipo di report/righe/origine) e un report
aggregato per periodo.

Aggiunto:
- `search_console_import_coverage` (upsert per property/periodo/tipo di
  report, stesso pattern atomico di `PublicHealthBaseline`), popolata
  dalla stessa transazione dell'importer.
- Sezione "Copertura dati" in `/admin/search-opportunities` (distinta dalla
  cronologia grezza per singolo import, non toccata).
- `/admin/search-console-baseline`: totali clic/impression/CTR/posizione,
  top landing page, query non-brand (config `search-console.brand_terms`),
  conteggi delle opportunità già scorate (mai ricalcolate).
- Dispositivo/Paese dichiarati esplicitamente non disponibili (nessun
  export CSV supportato li contiene).

Codex (PR #586, 2 finding reali, corretti): (1) `SearchConsoleImportCoverageService::record()`
aggiornava solo la riga di copertura per la property/tipo di report appena
importati, ma `SearchConsoleCsvImporter::import()` sostituisce
`search_console_queries` per l'intero periodo indipendentemente da questi
due campi — una reimportazione con property o tipo di report diversi
lasciava quindi in admin una riga di copertura "corrente" che descriveva
dati ormai cancellati; `record()` ora rimuove prima ogni riga di copertura
dello stesso periodo che non corrisponde alla nuova property/tipo di
report. (2) le card di conteggio opportunità in
`admin.search-console-baseline` linkavano sempre ad
`admin.search-opportunities`, che mostra solo l'ultimo periodo disponibile:
selezionando un periodo storico il link portava a dati di un periodo
diverso da quello mostrato; ora sono link solo per l'ultimo periodo, un
conteggio non cliccabile con avviso per un periodo storico. Entrambi
verificati con `git stash` (i nuovi test falliscono senza il fix, passano
con il fix).

Durante l'attesa della review, il Cantiere 37 (programma "100 cantieri
Kairus") è stato mergiato su `main`, toccando `routes/web.php` e
`resources/views/layouts/admin.blade.php` nelle stesse zone (entrambi
aggiungono una voce di navigazione in "Analisi"): conflitto reale
risolto con un merge commit (mai un rebase/force-push), verificato
riepilogando entrambe le variabili booleane di apertura del gruppo nav e
rieseguendo la suite combinata (137/137). Merge `bfc3893`.
