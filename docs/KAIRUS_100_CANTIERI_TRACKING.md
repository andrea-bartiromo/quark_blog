# Programma Kairus — 100 cantieri: tracking

Fonte di verità per lo stato del programma dei 100 cantieri assegnato in
sessione (istruzione utente verbatim, non riportata qui per brevità — vedi
la cronologia della sessione). Regole permanenti valide per tutto il
programma:

- Un solo cantiere per branch e PR; squash merge.
- Ogni cantiere è preceduto da un'ispezione del repository per non
  duplicare ciò che esiste già.
- Ogni cantiere è implementato, testato (Pint + suite pertinenti), rivisto
  e i finding reali corretti prima del merge automatico (nessun conflitto,
  nessun finding aperto, nessun fallimento nuovo).
- `ContentClusterAutoLifecycleCompletionTest` è pre-esistente e documentato
  (mai modificato) solo quando il fallimento è identico per file, riga e
  messaggio a quello già osservato.
- Nessun deploy, nessuna modifica a dati di produzione, nessun invio
  email/newsletter/social/comunicazioni, nessuna pubblicazione o modifica
  automatica di contenuti, fonti qualificate o date editoriali — il sistema
  prepara/verifica/propone, l'editor umano decide.

Questo file viene aggiornato dopo ogni merge (o dopo ogni tentativo, se un
cantiere risulta bloccato o già coperto da lavoro esistente).

## Legenda stato

`pending` · `in_progress` · `merged` · `covered-by-existing` (già presente
nel repository, nessuna PR necessaria) · `blocked` (dipendenza non
soddisfatta o richiede dato/decisione fuori standing authorization)

## Tabella

| # | Cantiere | Stato | PR | SHA merge | Test | Finding | Dipendenze |
|---|---|---|---|---|---|---|---|
| 1 | Nuovo flusso UX pagine categoria | merged | [#552](https://github.com/andrea-bartiromo/quark_blog/pull/552) | `a5af463` | 172/172 (1049 assert.) | 1 reale (fixato: allowlist newsletter `source=category`) | — |
| 2 | Chip categorie → componente Blade accessibile | merged | [#553](https://github.com/andrea-bartiromo/quark_blog/pull/553) | `83446de` | 35/35 (1267 assert.) | 2 reali (fixati: landmark aria duplicato; classe non-kairus in components/kairus/) | 1 |
| 3 | Newsletter categorie → CTA contestuale | merged | [#554](https://github.com/andrea-bartiromo/quark_blog/pull/554) | `b1e6553` | 75/75 (1768 assert.) | 0 | 1 |
| 4 | Più letti → 3 articoli, esclusi duplicati pagina | covered-by-existing | — | — | vedi nota | 0 | 1 |
| 5 | Blocco unitario "Continua a esplorare" | covered-by-existing | — | — | vedi nota | 0 | 1, 4 |
| 6 | Test feature/browser composizione categorie | merged | [#555](https://github.com/andrea-bartiromo/quark_blog/pull/555) | `b68695e` | 16/16 PHPUnit (56 assert.) + 6 browser (verdi in CI reale, "Chromium public regression") | 2 reali (fixati: focus programmatico non tastiera reale; breakpoint 900px non testato al confine) | 1-5 |
| 7 | Query budget categorie anti-N+1 | covered-by-existing | — | — | vedi nota | 0 | 1-5 |
| 8 | Audit canonical/SEO/OG/paginazione categorie | covered-by-existing | — | — | 36/36 (176 assert.) | 0 | 1 |
| 9 | Categorie non pubbliche isolate ovunque | merged | [#556](https://github.com/andrea-bartiromo/quark_blog/pull/556) | `1ab7b6c` | 34/34 (112 assert.) | 0 gap reali (audit completo) | — |
| 10 | Test integrazione visibilità temporale categorie | merged | [#557](https://github.com/andrea-bartiromo/quark_blog/pull/557) | `d005ad1` | 37/37 (106 assert.) | 0 | 9 |
| 11 | Preview admin categorie bozza/programmate | merged | [#558](https://github.com/andrea-bartiromo/quark_blog/pull/558) | `6ddfd56` | 241/241 (817 assert.) | 1 reale (fixato: route key `category` non rimossa dalla query string in preview()) | 9 |
| 12 | Checklist admin attivazione categoria | in_progress | [#559](https://github.com/andrea-bartiromo/quark_blog/pull/559) | — | 66/66 (212 assert.) | 0 | 11 |
| 13 | Comando category:publication-audit | pending | — | — | — | — | 9 |
| 14 | Test comando audit categorie | pending | — | — | — | — | 13 |
| 15 | Runbook cPanel + front controller pubblico | pending | — | — | — | — | — |
| 16 | Gate deploy integrità front controller | pending | — | — | — | — | 15 |
| 17 | Test deploy reale release senza .git (REVISION) | pending | — | — | — | — | 16 |
| 18 | Preflight storage persistente release | pending | — | — | — | — | — |
| 19 | Verifica automatica backup MariaDB | pending | — | — | — | — | — |
| 20 | Report read-only deploy readiness | pending | — | — | — | — | 15-19 |
| 21 | Inventario tecnico pagine pubbliche | pending | — | — | — | — | — |
| 22 | Audit HTTP/canonical/robots/SEO/JSON-LD | pending | — | — | — | — | 21 |
| 23 | Audit 404/redirect/canonical incoerenti | pending | — | — | — | — | 21 |
| 24 | Registro interno aggregato 404 | pending | — | — | — | — | 23 |
| 25 | Audit link interni rotti / esterni irraggiungibili | pending | — | — | — | — | 21 |
| 26 | Audit media (mancanti/alt/peso/formati/crediti) | pending | — | — | — | — | 21 |
| 27 | Baseline performance lab | pending | — | — | — | — | 21 |
| 28 | Test browser navigazione tastiera | pending | — | — | — | — | 21 |
| 29 | Audit WCAG interno | pending | — | — | — | — | 21 |
| 30 | Dashboard admin Salute pubblica | pending | — | — | — | — | 22-29 |
| 31 | Severità e presa in carico audit | pending | — | — | — | — | 30 |
| 32 | Report articoli con carenze editoriali | pending | — | — | — | — | 30 |
| 33 | Audit heading Fonti/Fonti primarie duplicati | pending | — | — | — | — | — |
| 34 | Regressione pannello fonti auto vs manuali | pending | — | — | — | — | 33 |
| 35 | Admin baseline mensile, denominatori separati | pending | — | — | — | — | 30 |
| 36 | Checklist certificazione primo piano editoriale | pending | — | — | — | — | 30-35 |
| 37 | Report pubblicazioni programmate 30gg | pending | — | — | — | — | — |
| 38 | Modello interno "Cosa sappiamo davvero" | pending | — | — | — | — | — |
| 39 | Campi/validazioni Trust | pending | — | — | — | — | 38 |
| 40 | Preview non indicizzabile pilot Trust | pending | — | — | — | — | 39 |
| 41 | Componente accessibile consenso/incertezza | pending | — | — | — | — | 39 |
| 42 | Gate pubblicazione pilot Trust | pending | — | — | — | — | 40, 41 |
| 43 | Metriche privacy-first pilot | pending | — | — | — | — | 40 |
| 44 | Admin decisione GO/NO-GO pilot | pending | — | — | — | — | 42, 43 |
| 45 | Protocollo editoriale Trust documentato | pending | — | — | — | — | 38-44 |
| 46 | Pacchetto editoriale non pubblico "Mente e comportamento" | pending | — | — | — | — | 45 |
| 47 | Audit attivazione "Mente e comportamento" | pending | — | — | — | — | 46 |
| 48 | Preview/audit sitemap/ricerca/canonical M&C | pending | — | — | — | — | 46, 47 |
| 49 | Categorie esistenti → hub editoriali | pending | — | — | — | — | 1-8 |
| 50 | Selezione manuale in evidenza per categoria | pending | — | — | — | — | 49 |
| 51 | Pacchetto editoriale non pubblico "Scienza e metodo" | pending | — | — | — | — | 45 |
| 52 | Audit attivazione "Scienza e metodo" | pending | — | — | — | — | 51 |
| 53 | Benchmark CTR/navigazione hub categorie | pending | — | — | — | — | 49, 50 |
| 54 | Command Center vista categorie | pending | — | — | — | — | 49 |
| 55 | Test isolamento assoluto categorie non pubbliche | pending | — | — | — | — | 9, 49 |
| 56 | Modello dati minimo Speciali editoriali | pending | — | — | — | — | — |
| 57 | Bozza non pubblica Speciale Turing | pending | — | — | — | — | 56 |
| 58 | Capitoli Turing ordinabili manualmente | pending | — | — | — | — | 57 |
| 59 | Mappa concettuale interna Turing | pending | — | — | — | — | 57 |
| 60 | Timeline Turing accessibile/testabile | pending | — | — | — | — | 57 |
| 61 | Gestione fonti per capitolo | pending | — | — | — | — | 58 |
| 62 | Audit anti-hub-vuoto Speciali | pending | — | — | — | — | 56-61 |
| 63 | Prototipo non pubblico navigazione Turing | pending | — | — | — | — | 58-61 |
| 64 | Un visual verificabile per lo Speciale | pending | — | — | — | — | 63 |
| 65 | Performance e immagini responsive Speciale | pending | — | — | — | — | 63, 64 |
| 66 | Indice capitoli senza JavaScript | pending | — | — | — | — | 58 |
| 67 | Report completezza Turing | pending | — | — | — | — | 57-66 |
| 68 | Metriche privacy-first navigazione Turing | pending | — | — | — | — | 63 |
| 69 | Checklist beta interna Turing | pending | — | — | — | — | 62, 67, 68 |
| 70 | Piano rilascio Turing (preflight + rollback) | pending | — | — | — | — | 69 |
| 71 | Backup off-host opzionale (config + test, no dati reali) | pending | — | — | — | — | — |
| 72 | Retention/RPO/RTO documentati e verificabili | pending | — | — | — | — | 71 |
| 73 | Restore isolato con fixture/dump non produttivi | pending | — | — | — | — | 71 |
| 74 | Audit dei backup | pending | — | — | — | — | 71-73 |
| 75 | Runbook rollback (migration/media/cache/front controller) | pending | — | — | — | — | 16-19, 74 |
| 76 | Audit stagionale articoli evergreen | pending | — | — | — | — | — |
| 77 | Coda manutenzione editoriale (owner/priorità) | pending | — | — | — | — | 76 |
| 78 | Rilevatore concetti sbilanciati | pending | — | — | — | — | — |
| 79 | Bozza Percorso "Metodo scientifico" | pending | — | — | — | — | — |
| 80 | Gate attivazione Percorso Metodo scientifico | pending | — | — | — | — | 79 |
| 81 | Continuità contestuale articolo-concetto-Percorso | pending | — | — | — | — | 79, 80 |
| 82 | Suggerimenti link interni (conferma umana, anti-cicli) | pending | — | — | — | — | 78 |
| 83 | Audit accessibilità/UX articolo-concetto-Percorso | pending | — | — | — | — | 81 |
| 84 | Radar fonti interno | pending | — | — | — | — | — |
| 85 | Bozze newsletter/social da contenuti approvati (no invio) | pending | — | — | — | — | — |
| 86 | Vista editoriale unica ciclo contenuto | pending | — | — | — | — | 77, 84, 85 |
| 87 | Transizioni di stato con audit trail | pending | — | — | — | — | 86 |
| 88 | Inbox interna issue editoriali/tecniche | pending | — | — | — | — | 86 |
| 89 | Punteggio interno spiegabile salute catalogo | pending | — | — | — | — | 30-32, 76-78 |
| 90 | Coda priorità modificabile dall'editor | pending | — | — | — | — | 86-89 |
| 91 | Contratti interni entità (articoli/concetti/Percorsi/...) | pending | — | — | — | — | — |
| 92 | Test integrità referenziale e cancellazione sicura | pending | — | — | — | — | 91 |
| 93 | Salvataggi locali browser senza account | pending | — | — | — | — | — |
| 94 | Ripresa di lettura locale accessibile/cancellabile | pending | — | — | — | — | 93 |
| 95 | Modalità studio accessibile | pending | — | — | — | — | — |
| 96 | Deduplicazione media interna (hash, no auto-delete) | pending | — | — | — | — | — |
| 97 | Licenze/crediti/varianti responsive/fallback media | pending | — | — | — | — | 96 |
| 98 | Staging/procedura equivalente preview release+migration | pending | — | — | — | — | 16-20, 71-75 |
| 99 | Feature flag interne (audit trail + rollback) | pending | — | — | — | — | — |
| 100 | Vista operativa finale + runbook + roadmap successiva | pending | — | — | — | — | tutti |

## Note per cantiere

### 12 — Checklist admin attivazione categoria

Ispezione preliminare: `CategoryPublicationReadiness::evaluate()` esisteva
già, ma la sua checklist era visibile SOLO dopo aver aperto "Modifica" su
una singola categoria (`categories-edit.blade.php`) — un editor che
scorre l'elenco non aveva modo di individuare a colpo d'occhio quali
categorie non ancora pubbliche fossero davvero pronte prima di attivarle.
Gap genuino, non coperto da nulla di esistente.

Aggiunta una colonna "Checklist" nell'elenco admin delle categorie
(`admin.categories`), calcolata riusando lo stesso servizio — SOLO per le
categorie non ancora pubblicamente visibili (`reject(isPubliclyVisible())`,
nessuno spreco di query per quelle già pubbliche). "Pronta" (badge verde)
se nessuna criticità, altrimenti un conteggio con tooltip nativo
(`title="..."`) che elenca le etichette esatte — mai bloccante, stessa
filosofia già stabilita per il pannello di anteprima nell'editor.

### 11 — Preview admin categorie bozza/programmate

Ispezione preliminare: `categories-edit.blade.php` aveva già un pannello
"Anteprima pubblicazione" (`CategoryPublicationReadiness`), ma solo
testuale — stato effettivo, checklist di readiness, URL pubblico mostrato
come `<code>` non cliccabile finché non pubblico. Nessun modo di vedere
davvero come apparirebbe la pagina categoria prima dell'attivazione: gap
genuino, non coperto da nulla di esistente.

Estratta la logica di `ArticleController::category()` (griglia paginata,
"Più letti", chip Argomenti) in un nuovo servizio condiviso
`CategoryDiscoveryPageData` (mai una query duplicata: la categoria già
risolta dal chiamante viene passata, non ri-recuperata). Nuova rotta
staff-only `admin.categories.preview` (dentro il gruppo `auth`+`editor`
già esistente) che riusa la STESSA vista pubblica `categoria.blade.php`
con gli STESSI dati, saltando deliberatamente `isPubliclyVisible()` —
mai una vista duplicata che rischierebbe di divergere da quella reale.
Banner "Anteprima amministrativa" + `noindex,nofollow` (difesa in
profondità, oltre al gate di autenticazione) visibili solo quando
`previewMode=true`, mai sulla pagina pubblica reale. Link "Vedi
anteprima →" aggiunto al pannello esistente in `categories-edit.blade.php`.

### 10 — Test integrazione visibilità temporale categorie

Ispezione preliminare: `CategoryScheduledPublicationTest.php` (già
esistente, 19 test) copre già in modo esaustivo ogni superficie pubblica
a istanti di tempo fissi e isolati (bozza, programmata futura,
programmata all'istante esatto, disattivata, pubblicata — una fixture
diversa per ciascun caso). Mancava un test che facesse attraversare
realmente il tempo a UNA sola categoria, verificata prima e dopo
l'istante di apertura sulle stesse superfici — a differenza degli
articoli (`ScheduledArticleVisibilityTest::test_full_lifecycle_...`, che
deve eseguire `articles:publish-scheduled`), `Category::scopePubliclyVisible()`
calcola la visibilità dal vivo (`published_at <= now()`) senza mai
modificare la colonna `status`: nessun comando batch esiste o serve per
le categorie.

Nuovo `tests/Feature/CategoryTemporalVisibilityIntegrationTest.php`: una
categoria, un solo test, orologio virtuale (`Carbon::setTestNow`) fatto
avanzare oltre l'istante di apertura senza eseguire alcun comando —
verificata invisibile (pagina 404, assente da home/notizie/sitemap) prima
e visibile ovunque dopo, con `status` rimasto `scheduled` in entrambi i
momenti (prova esplicita che nessuna transizione di stato è necessaria).

### 9 — Categorie non pubbliche isolate ovunque

Audit completo (read-only, prima di scrivere codice) di ogni superficie
pubblica che referenzia `Category`: sitemap, sitemap-news, RSS feed,
ricerca, JSON-LD/breadcrumb, pagina categoria, notizie/home, pagina
articolo, header/footer/sidebar/category-bar, blocco "Continua a
esplorare", newsletter. **Nessun gap funzionale reale trovato**: ogni
superficie che rende un link cliccabile verso una pagina categoria usa
già `publicOptions()`/`isPubliclyVisible()`/`scopePubliclyVisible()`; ogni
uso del più permissivo `Category::options(false)` è confinato a
un'etichetta testuale sulla categoria di un articolo già pubblicato (mai
un link), seguendo la convenzione già esplicitamente documentata in
`structured-data.blade.php` ("articleSection ... resta un'etichetta
testuale, non un link, e quindi non è filtrato qui").

Unico neo trovato: due usi di `Category::options(false)` in
`SeoController.php` (`feed()` per `<category>`, `newsSitemap()` per
`<news:genres>`) non avevano il commento esplicativo che accompagna ogni
altro uso analogo altrove. Aggiunto per coerenza, insieme a 2 test di
regressione che verificano che l'etichetta sopravviva alla disattivazione
della categoria dopo la pubblicazione dell'articolo (comportamento
corretto, non un leak: l'articolo resta pubblico, solo l'etichetta di
testo non deve sparire né mostrare lo slug grezzo).

### 4 — Più letti → 3 articoli, esclusi duplicati pagina

Ispezione: già interamente implementato dal Cantiere 1 in
`ArticleController::category()` — `Article::published()->whereNotIn('id',
$articles->pluck('id'))->orderByDesc('views')->limit(3)`, testato da
`CategoryDiscoveryFlowTest::test_continue_exploring_most_read_excludes_articles_already_shown_on_the_page`.
Nessuna PR: aprirne una avrebbe duplicato codice e test identici.

### 5 — Blocco unitario "Continua a esplorare"

Ispezione: già implementato dal Cantiere 1 come sezione unica
(`categories/partials/continue-exploring.blade.php`) che combina Più letti
e categorie correlate in un solo blocco a fine pagina, testato da 2 dei 6
test di `CategoryDiscoveryFlowTest.php`. Nessuna PR necessaria.

### 6 — Test feature/browser composizione categorie

Nuovo `tests/browser/category-discovery.spec.js` (4 test — 6 con il
breakpoint diviso in due casi) + fixture isolata `browser-newsletter-category`
in `BrowserTestSeeder.php` (mai `intelligenza-artificiale`, su cui altre
suite browser fanno assunzioni su conteggio/ordine articoli).

Due finding reali di Codex, entrambi sulla qualità del test stesso (non
sul codice applicativo): (1) il test del focus usava `.focus()`
programmatico e verificava solo "outline diverso da none" — un default
del browser avrebbe fatto passare il test anche senza il fix reale;
corretto con navigazione da tastiera reale (Tab ripetuto) e verifica
della firma specifica (outline 3px solid, offset 2px) della regola
`.kairus-focusable:focus-visible`. (2) il test del breakpoint 900px
usava solo 390px, lontano dalla soglia reale; aggiunti due test al
confine esatto (899px/901px). Entrambi verificati con un browser reale
prima del push; "Chromium public regression" verde in CI sul commit
finale.

### 7 — Query budget categorie anti-N+1

Ispezione: `PublicPageQueryBudgetTest.php` copre già la pagina categoria
(budget ≤10, aggiornato dal Cantiere 1 con giustificazione documentata per
ogni query aggiunta). Nessuna crescita con il numero di articoli o
categorie verificata dai test esistenti. Nessuna PR necessaria.

### 8 — Audit canonical/SEO/OG/paginazione categorie

Ispezione: copertura già esistente e verificata verde (36 test, 176
assertion) in `ArchivePaginationCanonicalTest.php`,
`ArticleBreadcrumbStructuredDataTest.php`,
`CollectionPageStructuredDataTest.php`, `HttpsCanonicalizationTest.php` —
canonical, paginazione, structured data e coerenza OG/HTTPS per la pagina
categoria, non toccati né regrediti dai Cantieri 1-3. Nessuna PR
necessaria.

### 3 — Newsletter categorie → CTA contestuale

Ispezione preliminare: il Cantiere 1 aveva già introdotto il meccanismo di
base (CTA dopo la terza card, `source="category"`, form funzionante e
testato) — la nota lasciata in quel commit rimandava esplicitamente al
Cantiere 3 la "distinzione visiva/di accessibilità". Nessuna duplicazione:
questo cantiere completa esattamente ciò che era stato deliberatamente
rimandato.

Due correzioni reali, non solo estetiche: (1) il `<li>` che ospita la CTA
dentro il `<ul>` della griglia articoli non è un articolo — senza
`role="presentation"` chi naviga con screen reader sentirebbe annunciare
un elenco di N articoli che in realtà ne contiene N-1 più un modulo di
iscrizione; il contenuto resta comunque raggiungibile perché la CTA è
diventata un `<section aria-labelledby="...">` con nome accessibile
proprio (landmark "region"), non un `<div>` generico. (2) input e bottone
del form non avevano il trattamento `:focus-visible` (classe condivisa
`kairus-focusable`) già usato da ogni altro elemento interattivo del
design system Kairus (article-card, path-card, path-step) — aggiunto per
coerenza.

### 2 — Chip categorie → componente Blade accessibile

Ispezione preliminare: il markup del chip-row "Argomenti" era duplicato tra
`notizie.blade.php` (categoria corrente sempre "Tutti") e `categoria.blade.php`
(Cantiere 1). Estratto in `x-kairus.topic-chips` (`options`, `current`).

Finding tecnico scoperto durante l'estrazione (non un bug applicativo, un
comportamento di compilazione Blade): passare un'espressione dinamica
(una chiamata di funzione, es. `Category::publicOptions()`) direttamente
come attributo `:options="..."` di un componente anonimo la fa valutare
DUE volte dal template compilato (una per costruire i dati del
componente, una per `$component->withAttributes()`), raddoppiando query
o altri side-effect. Confermato con `DB::getQueryLog()` +
`(new Exception())->getTraceAsString()` temporanei in
`Category::publicOptions()`, che hanno mostrato due chiamate dallo stesso
file compilato a righe diverse. Corretto calcolando il valore in una
variabile locale (`$topicChipOptions`) nel blocco `@php` esistente di
`notizie.blade.php` prima di passarlo al componente — zero query in più
rispetto a prima dell'estrazione. `categoria.blade.php` non era a
rischio: passava già una variabile, non una chiamata di funzione.

Due finding reali emersi in review/CI (nessuno bloccante per più di un
giro): (1) Codex (P2) — il nuovo `<nav aria-label="Argomenti">` del
chip-row collideva con l'omonimo landmark del topic-cloud in
`components/sidebar.blade.php`, incluso dalla stessa pagina — due
landmark "nav" indistinguibili per screen reader. Rinominato in
"Filtra per argomento". (2) CI —
`KairusEditorialFoundationsIsolationTest` impone che ogni componente in
`resources/views/components/kairus/` usi solo classi prefissate
`kairus-` (isolamento deliberato dal tema "public"); il componente
riusa `.public-pill-row`/`.active` di `public-premium.css`, quindi
appartiene ai componenti condivisi non-Kairus — spostato in
`resources/views/components/topic-chips.blade.php` (`x-topic-chips`).

### 1 — Nuovo flusso UX pagine categoria

Ispezione preliminare: `categoria.blade.php` aveva già hero, feature band,
griglia paginata a 6/pagina (merge umano `7228fac`, non duplicato) e
sidebar con "Più letti"/"Argomenti". Mancavano: chip Argomenti nel flusso
principale (esiste già come pattern `.public-pill-row` in `notizie.blade.php`
e come CSS in `public-premium.css` — riusati, non duplicati), newsletter
CTA a metà griglia, blocco "Continua a esplorare" a fine pagina.

Finding reale in review (Codex, P1): `Newsletter::SOURCES` non includeva
`category`, quindi ogni submit dalla nuova CTA veniva respinto dalla
validazione prima di raggiungere `Newsletter::subscribe()` — la CTA non
avrebbe mai registrato un'email. Corretto nello stesso PR (commit
`29f9f9a`): aggiunto `category` all'allowlist + test di regressione
dedicato in `NewsletterSourcePersistenceTest.php`. `PHP 8.4` ha continuato
a mostrare solo il fallimento pre-esistente
`ContentClusterAutoLifecycleCompletionTest.php:231` ("Failed asserting
that false is true."), identico per file/riga/messaggio sia prima che
dopo il fix — documentato, non toccato.
