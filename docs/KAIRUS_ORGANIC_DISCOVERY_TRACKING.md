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
| 1 | Baseline e affidabilità dei dati Search Console | in_progress | — | — | in corso | — | — |
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
