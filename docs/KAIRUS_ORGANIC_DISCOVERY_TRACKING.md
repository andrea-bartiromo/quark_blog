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
| 2 | Profilo editoriale di ricerca per articolo | merged | [#588](https://github.com/andrea-bartiromo/quark_blog/pull/588) | `f896ee2` | 204/204 (Article*+ArticleSearchProfile+SearchProfile unit, 866 assert. insieme al lavoro del Cantiere 6 sotto); nessun finding Codex (la review non si è mai attivata su questa PR, verificato con get_reviews vuoto) | 0 | 1 |
| 3 | Prontezza organica e scoperta interna | in_progress | [#589](https://github.com/andrea-bartiromo/quark_blog/pull/589) | — | vedi nota | — | 1, 2 |
| 4 | Dalle opportunità Search Console alle decisioni editoriali | pending | — | — | — | — | 1 |
| 5 | Cannibalizzazione di ricerca | pending | — | — | — | — | 1, 2 |
| 6 | Salute di indicizzazione e sitemap | covered-by-existing | [#587](https://github.com/andrea-bartiromo/quark_blog/pull/587) (implementato direttamente da Andrea Bartiromo, fuori da questa sessione) | `bc34dc0` | vedi nota | 0 | — |
| 7 | Monitoraggio e report operativo | pending | — | — | — | — | 1, 3, 4 |
| 8 | Strategia editoriale per cluster e autorevolezza | pending | — | — | — | — | 2, 3 |

## Note per cantiere

### Cantiere 3 — Prontezza organica e scoperta interna (in_progress)

`OrganicDiscoveryReadinessService` compone SOLO audit già esistenti (mai
una regola ricalcolata): `ArticleDiscoveryAuditService` (percorsi/link in
entrata), `SeoMetadataQualityAuditService` (canonical/duplicati),
`EditorialQualityChecker` (qualità complessiva), il profilo di ricerca del
Cantiere 2, i dati Search Console del Cantiere 1. Per ogni articolo
pubblico produce uno stato spiegabile — bloccato/da migliorare/pronto/
misurato — con causa e azione suggerita per ogni finding (mai un
punteggio opaco). "misurato" richiede "pronto" (zero finding) più una
riga Search Console reale osservata per l'articolo nell'ultimo periodo
importato.

Due superfici admin nuove (`/admin/ricerca-organica` aggregata,
`/admin/ricerca-organica/{article}` di dettaglio, entrambe read-only,
eseguite solo aprendo la pagina) più un'anteprima leggera non bloccante
integrata nella pagina di modifica articolo (`previewForArticle()`, MAI
l'intero `auditAll()` del corpus — troppo costoso ad ogni apertura della
pagina di un singolo articolo; copre solo i controlli economici per un
solo articolo, dichiarando esplicitamente cosa non misura: percorsi di
scoperta interna, duplicati di corpus, dati Search Console, presenza di
dati strutturati).

Estensioni minime a servizi esistenti (mai duplicazioni): resa `public`
`SeoMetadataQualityAuditService::canonicalCheck()` (funzione pura sul
singolo articolo, zero dipendenza dal corpus) e aggiunto
`ArticleRevisionTransparencyService::lastEditorialUpdates()` (variante
batch della regola già esistente, una query per l'intero corpus invece di
una per articolo — necessaria perché questo è il primo chiamante che
applica la regola di freschezza a più articoli insieme).

Trovato e corretto in fase di test un vero N+1 auto-introdotto: senza
eager-load esplicito di `author:id`, `EditorialQualityChecker::authorCheck()`
(che legge `$article->author`) generava una query utente per articolo —
verificato con `DB::enableQueryLog()` su un corpus di 20 vs 100 articoli
(37 vs 117 query prima del fix, 37 vs 37 dopo), ora coperto da un test di
query-budget permanente.

Verificato rigorosamente (test dedicati): un articolo bozza o programmato
non compare mai in `auditAll()`, `forArticle()` restituisce `null` per un
articolo non pubblico, e la pagina di dettaglio risponde 404 per un
articolo non pubblico (fail-closed, mai un tentativo di calcolare uno
stato per contenuto non pubblico).

Ancora da fare prima del merge: aprire la PR, eseguire Pint e la suite
completa, gestire CI/Codex, mergiare, aggiornare questa riga con PR/SHA
definitivi.

### Cantiere 6 — Salute di indicizzazione e sitemap (covered-by-existing)

Merge `bc34dc0` (PR #587, "feat: audit read-only della salute d'indicizzazione"),
implementato e mergiato direttamente da Andrea Bartiromo, fuori da questa
sessione — scoperto post-merge durante la sincronizzazione di `main` dopo
il Cantiere 2. Copre esattamente lo scopo del Cantiere 6: import CSV
dell'export Coverage/Indicizzazione reale di Search Console
(`SearchConsoleCoverageCsvImporter`, tabelle `search_console_coverage_imports`/
`_issues`, distinte dalle tabelle Performance del Cantiere 1 — sono due
report Search Console diversi, mai fusi), un audit locale senza rete
dell'eleggibilità pubblica di ogni URL importato
(`SearchConsoleCoverageUrlEligibility::audit()`: pubblico/non pubblico,
HTTP status, canonical dichiarata, robots, presenza in sitemap — nessuna
richiesta a Google, nessuna azione automatica), e una classificazione
read-only per riga (`expected`/`review`/`fix`/`intentional_exclusion`/
`editorial_review`) con motivazione testuale, mai un punteggio opaco.
Pagina admin `/admin/salute-indicizzazione`. Verificato che l'intera
suite combinata Cantiere 1+2+questo lavoro passa insieme (250/250) prima
di proseguire. Nessuna azione richiesta da questa sessione: il cantiere
resta chiuso, non verrà riaperto né duplicato da un cantiere futuro.

### Cantiere 2 — Profilo editoriale di ricerca per articolo

Nuova tabella `article_search_profiles` (1:1 con `articles`, mai letta da
alcuna pagina pubblica): intento primario, query primaria/secondarie,
domande dei lettori, tipo di contenuto, livello del lettore, data e nota
dell'ultima revisione editoriale, ambito/limiti delle evidenze. Endpoint
dedicato (`Admin\ArticleSearchProfileController`), separato dal
costruttore di `ArticleController` per non dover toccare anche
`ArticleDiscoveryController` (sua sottoclasse, bound in
`AppServiceProvider` per tutte le route `admin.articles.*`). Avviso di
collisione non bloccante quando la query primaria normalizzata coincide
con quella di un altro articolo (`ArticleSearchProfileCollisionService`,
stessa normalizzazione già in uso in `ConceptDuplicateAuditService`,
duplicata deliberatamente per lo stesso motivo di bounded context
distinti già documentato lì). Bozze di suggerimento ricavate solo
localmente da titolo/estratto/heading del corpo, mai scritte senza un
clic esplicito "Usa" — stesso principio già seguito da
`article-seo-fallback-script.blade.php`.

Codex non ha mai avviato una review su questa PR (`get_reviews` vuoto,
nessun commento di riepilogo comparso): nessun finding da correggere. Due
check CI (`uneditable/uneditable`, `mariadb-content-clusters`) sono
falliti senza log diagnosticabili e senza alcuna relazione col diff di
questa PR (stesso identico workflow verde sia sul commit base sia
sull'ultimo push a `main`); commentato una volta sulla PR, nessun
rilancio possibile (permessi insufficienti). PHP 8.4 ha fallito solo sul
flake pre-esistente canonico (riga 231), commentato una volta. Durante
l'attesa, l'utente ha mergiato direttamente il Cantiere 6 (vedi sopra) —
verificata l'assenza di conflitti reali, suite combinata 250/250. Merge
`f896ee2`.

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
