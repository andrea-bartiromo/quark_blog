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
| 3 | Prontezza organica e scoperta interna | merged | [#589](https://github.com/andrea-bartiromo/quark_blog/pull/589) | `52d5a60` | 31/31 (OrganicDiscoveryReadinessService+Controller, 64 assert.) + 9/9 ArticleRevisionTransparencyService (16 assert.); suite CI completa: 4568/4569 passed, 11 skipped, 1 pre-esistente (`ContentClusterAutoLifecycleCompletionTest.php:231`) | 1 reale (fixato: `lastEditorialUpdates()` caricava l'intera cronologia revisioni invece di filtrare lato DB) | 1, 2 |
| 4 | Dalle opportunità Search Console alle decisioni editoriali | merged | [#590](https://github.com/andrea-bartiromo/quark_blog/pull/590) | `c4ba1ef` | 23/23 (SearchOpportunityDecisionService+Controller+comando misurazione, 76 assert.); suite di regressione mirata (SearchOpportunity+Progettazione): 360/362, 2 pre-esistenti (`ProjectModelTest.php:235`, `ProjectTaskControllerTest.php:193`); CI PR: 6/7 verdi su entrambi i tentativi, unico rosso `ContentClusterAutoLifecycleCompletionTest.php:231` riprodotto identico due volte su commit diversi — confermato pre-esistente, non correlato al diff | 5 reali, vedi nota | 1 |
| 5 | Cannibalizzazione di ricerca | in_progress | [#592](https://github.com/andrea-bartiromo/quark_blog/pull/592) | — | vedi nota | — | 1, 2, 4 |
| 6 | Salute di indicizzazione e sitemap | covered-by-existing | [#587](https://github.com/andrea-bartiromo/quark_blog/pull/587) (implementato direttamente da Andrea Bartiromo, fuori da questa sessione) | `bc34dc0` | vedi nota | 0 | — |
| 7 | Monitoraggio e report operativo | pending | — | — | — | — | 1, 3, 4 |
| 8 | Strategia editoriale per cluster e autorevolezza | pending | — | — | — | — | 2, 3 |

## Note per cantiere

### Cantiere 4 — Dalle opportunità Search Console alle decisioni editoriali (merged)

Nota di riconciliazione: mentre la PR #590 era aperta, un commit diretto su
`main` (`fdee077`, Andrea Bartiromo, fuori da questa sessione) ha aggiunto
`EditorialOpportunityDecisionService`/`Controller`
(`/admin/decisioni-editoriali-seo`, voce di navigazione "Decisioni SEO") —
una coda di priorità editoriale **read-only** (nessuna tabella, nessuna
decisione persistita) che classifica le opportunità già calcolate in
high/medium/monitor/no_action/verify componendo `OrganicDiscoveryReadinessService`
(Cantiere 3) e `ArticleSearchProfile` (Cantiere 2). Verificato che non c'è
sovrapposizione tecnica con questo cantiere: namespace, rotte, viste e
tabelle diverse; nessun conflitto al merge; suite dei due cantieri eseguita
insieme dopo il merge (31/31 verde). Le due funzionalità sono
complementari, non duplicate: quella qui sopra classifica/prioritizza
(sola lettura), questa PR registra e traccia la decisione umana effettiva
nel tempo (baseline, storico append-only, misurazione 28/90gg).

Estende `SearchOpportunityStatus`/`SearchOpportunityStatusService`
(Missione 6, workflow leggero nuova/vista/gestita/ignorata) con una
decisione editoriale tracciabile molto più ricca, MAI sostituendolo: le
due tabelle convivono, la select "Stato" esistente resta invariata.
Nuova tabella `search_opportunity_decisions` (una riga per
`opportunity_key`, la stessa identità stabile `type|query|page_url` già
usata da `SearchOpportunityStatus`) — decisione tra aggiorna
articolo/crea brief/sovrapposizione/ignora, con motivazione, chi/quando,
baseline delle metriche catturato SOLO alla prima decisione, misurazione
a 28/90 giorni. Storico append-only in
`search_opportunity_decision_histories` (stesso schema di
`ProjectActivityLog`, mai un update).

"Crea brief" riusa `ProjectTask` (tipo `publication`, già esistente nel
modulo Progettazione, `article_id` nullable) invece di inventare un nuovo
modello "brief" — nessun `Article` creato automaticamente; fallisce
esplicitamente (fail-closed) se non esiste un progetto editoriale
predefinito attivo (`Project::defaultEditorial()`), mai un progetto creato
al volo. La registrazione di una decisione ricalcola sempre l'opportunità
dal periodo corrente prima di salvare: se non è più presente (dati
cambiati dall'apertura della pagina), fail-closed, nessun baseline
indovinato.

Estratta una nuova funzione pubblica `SearchOpportunityScoringService::currentOpportunities()`
(composizione già esistente identica in `SearchOpportunityController::index()`
e nel nuovo `SearchOpportunityDecisionService` — mai due implementazioni
della stessa regola "opportunità attuali = periodo più recente + ricerche
interne a zero risultati, sempre calcolate anche senza import"). Aggiunto
il comando artisan `search-opportunities:measure-outcomes` (sola lettura,
non schedulato automaticamente in questa v1) per popolare le misurazioni
a 28/90 giorni.

Bug trovato e corretto in fase di test: la prima implementazione di
`currentOpportunitiesByKey()` non calcolava le opportunità da ricerca
interna a zero risultati quando nessun periodo Search Console era
disponibile (a differenza del controller, che le calcola sempre) —
sarebbe stato impossibile misurare l'esito di una decisione su
quell'unica fonte di opportunità "sempre presente" in assenza di CSV
importati. Corretto riusando `currentOpportunities()` per entrambi i
chiamanti.

5 finding reali di Codex (commit `1025ef1`), tutti verificati (revert
della fix → il test di regressione dedicato fallisce → fix ripristinata)
e risolti:

1. **P1** `measureDueOutcomes()` misurava sia +28gg che +90gg dallo stesso
   snapshot corrente, indipendentemente dal fatto che il periodo importato
   più recente coprisse davvero quell'orizzonte dalla baseline — una
   misurazione poteva restare bloccata su un valore sbagliato per sempre
   (il timestamp di misurazione impedisce i tentativi successivi).
   Corretto con `periodCoversHorizon()`: si misura solo se il periodo più
   recente copre davvero l'orizzonte, altrimenti la decisione resta non
   misurata.
2. Race condition nella creazione del brief: due invii concorrenti sulla
   stessa opportunità potevano creare due `ProjectTask` distinti prima che
   una decisione venisse salvata. Corretto spostando la creazione del
   brief dentro la stessa transazione con `lockForUpdate()` di
   `record()`.
3. `decisionsFor()` (il bulk-fetch per la vista elenco) non caricava le
   relazioni `article`/`projectTask` in eager, nonostante la vista le
   legga per ogni riga — una query aggiuntiva per riga, mai realmente
   "bulk". Corretto con `->with(['article', 'projectTask'])`.
4. Lo storico registrava come old/new value solo `decision_type`, perdendo
   quale articolo/brief/motivazione fosse effettivamente cambiato tra due
   decisioni sulla stessa opportunità. Corretto con uno snapshot completo
   (`decision_type;article_id;project_task_id;rationale`) sia su old che
   su new.
5. `opportunity_key` (colonna `string(600)` con indice `unique()`) poteva
   non bastare per chiavi valide: tipo (~28 caratteri) + query (fino a
   255) + page_url (fino a 500) può superare 600 caratteri. Un semplice
   allargamento della colonna non sarebbe bastato: in utf8mb4 un indice
   univoco su una colonna larga avrebbe comunque superato il limite di
   prefisso InnoDB (3072 byte = 768 caratteri in utf8mb4) — scoperto
   tramite analisi diretta, non solo suggerito da Codex. Risolto rendendo
   `opportunity_key` una colonna `TEXT` non indicizzata e spostando
   l'unicità reale su una nuova colonna `opportunity_key_hash` (SHA-256,
   `CHAR(64)` UNIQUE).

Aggiunti 3 test di regressione mirati (copertura orizzonte misurazione,
eager-load, chiave oltre 600 caratteri) — suite Cantiere 4 completa: 23/23
verdi. Suite di regressione mirata (SearchOpportunity + Progettazione):
360/362, i 2 falliti sono i flake pre-esistenti già documentati
(`ProjectModelTest.php:235`, `ProjectTaskControllerTest.php:193`), nessuna
relazione con questo diff.

### Cantiere 5 — Cannibalizzazione di ricerca (in_progress)

Ispezione pre-cantiere (agente Explore in background): il codice esistente
copriva solo un controllo leggero e dichiarato ("due articoli hanno lo
stesso `ArticleSearchProfile::primary_query`" — già in
`ArticleSearchProfileCollisionService` e riusato da
`EditorialOpportunityDecisionService`, quest'ultimo aggiunto direttamente
da Andrea Bartiromo su `main` mentre la PR #590 del Cantiere 4 era ancora
aperta, vedi nota di riconciliazione del Cantiere 4 sopra). Entrambi i
servizi hanno un docblock che rimanda esplicitamente a questo cantiere per
il segnale più forte: "la sovrapposizione più ampia tra query/domande
secondarie e i dati Search Console resta compito del Cantiere 5". Nessuna
duplicazione: il controllo esistente confronta solo testo dichiarato in
redazione, mai osservato nei dati reali di Google.

Aggiunto `SearchOpportunityScoringService::cannibalizationFindings()`
(nuovo metodo, stesso file che già ospita tutta la logica di scoring delle
opportunità — mai un secondo motore parallelo): raggruppa le righe
Search Console del periodo per query normalizzata, tiene solo articoli
**pubblici** (fail-closed: una bozza non entra mai nel conteggio, né come
primario né come concorrente), esclude le query brand
(`search-console.brand_terms`, stessa configurazione già esistente),
richiede almeno 2 articoli distinti e impression totali ≥
`MIN_IMPRESSIONS` (soglia riusata, non una nuova costante arbitraria).
Ogni gruppo produce anche una `SearchOpportunity` di tipo
`search_cannibalization` (articolo = il "probabile primario", quello con
più impression) automaticamente inclusa in `currentOpportunities()` —
compare quindi anche nell'elenco generale `/admin/search-opportunities` e
la sua risoluzione ("Sovrapposizione con articolo esistente") passa dalla
stessa infrastruttura di decisione/baseline/storico/misurazione a 28-90gg
del Cantiere 4, **nessuna nuova tabella o struttura di persistenza**.

Nuovo DTO `SearchCannibalizationFinding` (query, concorrenti ordinati per
impression decrescenti con le proprie metriche individuali, articolo
primario, l'opportunità condivisa) — necessario perché la singola
`SearchOpportunity` esistente espone un solo articolo, mentre la pagina
dedicata deve mostrare le metriche di *ciascun* concorrente, non solo del
vincitore. Nuova pagina read-only `/admin/cannibalizzazione-ricerca`
(`SearchCannibalizationController`), voce di navigazione "Cannibalizzazione
ricerca" nel gruppo Analisi, form che registra la decisione riusando
`admin.search-opportunities.record-decision` — nessuna nuova route di
scrittura.

Test: 5 nuovi in `SearchOpportunityScoringServiceTest` (cannibalizzazione
rilevata con articolo primario corretto, singolo articolo non segnalato,
evidenza combinata insufficiente non segnalata, articolo non pubblico mai
conteggiato, query brand mai segnalata) — suite del file 21/21. 7 nuovi in
`SearchCannibalizationControllerTest` (autorizzazione guest/autore/editor,
stato vuoto, rilevamento reale, esclusione bozza, form di decisione) — tutti
verdi. Suite di regressione mirata (SearchOpportunity+SearchConsole+
SearchCannibalization+EditorialOpportunity+OrganicDiscovery+Progettazione):
463/465, 2 pre-esistenti (`ProjectModelTest.php:235`,
`ProjectTaskControllerTest.php:193`), nessuna relazione con questo diff.
Pint pulito su tutti i file toccati.

Suite completa post-implementazione: 4608/4623 passati, 3 falliti — tutti
e tre i flake pre-esistenti già documentati
(`ProjectModelTest.php:235`, `ProjectTaskControllerTest.php:193`,
`ContentClusterAutoLifecycleCompletionTest.php:192`), nessuna relazione
con questo diff. PR aperta: [#592](https://github.com/andrea-bartiromo/quark_blog/pull/592).

Ancora da fare prima del merge: gestire CI/Codex, mergiare, aggiornare
questa riga con PR/SHA definitivi.

### Cantiere 3 — Prontezza organica e scoperta interna (merged)

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

Codex (PR #589, 1 finding reale P2, corretto): `ArticleRevisionTransparencyService::lastEditorialUpdates()`
eseguiva `ArticleRevision::query()->whereIn('article_id', ...)->get()` senza
alcun filtro su created_at/published_at — caricava quindi l'intera
cronologia di ogni articolo (incluse le revisioni pre-pubblicazione e il
loro `body` longText), un costo che cresceva con il volume storico di
revisioni, non con il numero di articoli. Corretto calcolando i confini
(prima/ultima revisione qualificante) lato DB con MIN/MAX tramite un join
che esclude già le revisioni pre-pubblicazione, caricando il `body` solo
per la singola revisione più vecchia di ciascun articolo. Verificato con
`git stash`: il nuovo test fallisce sull'implementazione precedente
(query `select * from article_revisions where article_id in (...)` senza
alcun filtro) e passa con il fix. Il check CI `PHP 8.4` è fallito su ogni
commit di questa PR solo per il flake pre-esistente canonico
(`ContentClusterAutoLifecycleCompletionTest.php:231`), commentato una
volta sulla PR insieme al fix Codex.

Nota operativa: l'API GraphQL di GitHub (`get_review_comments`) è rimasta
rate-limited per l'intera seconda metà di questa PR — non è stato quindi
possibile rispondere/risolvere esplicitamente il singolo thread di review
di Codex (il finding è comunque stato corretto, verificato, e riferito
esplicitamente in un commento sulla PR prima del merge).

Merge `52d5a60`.

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
