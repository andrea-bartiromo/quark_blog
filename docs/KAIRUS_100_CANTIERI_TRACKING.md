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
- **Aggiornamento Cantiere 74**: il fallimento ricorrente su
  `ContentClusterAutoLifecycleCompletionTest.php:231`, archiviato dai
  Cantieri 611-614 come "pre-esistente, non correlato, mai modificato",
  in realtà NON era un flake — riprodotto deterministicamente anche in
  isolamento locale. Causa: `test_9_promotes_exactly_when_the_final_articles_publication_instant_is_reached`
  usava una data hardcoded (`2026-09-10 12:00:00`) come `$target`, ma un
  altro articolo nello stesso test riceve `published_at = now()->subHour()`
  (tempo reale, non congelato) — una volta che il calendario reale ha
  superato quella soglia, il test falliva sempre, non a intermittenza.
  Fissato in PR #615 (`$target` ora relativo a `now()` reale). La regola
  precedente ("mai modificato") è quindi ritirata: un fallimento
  identico per file/riga/messaggio su PIÙ PR consecutive è un segnale da
  investigare a fondo (anche in isolamento locale), non solo da
  ri-archiviare come flake.
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
| 12 | Checklist admin attivazione categoria | merged | [#559](https://github.com/andrea-bartiromo/quark_blog/pull/559) | `1068d4b` | 66/66 (212 assert.) | 1 reale (fixato: fixture di test "pronta" falso positivo — matchava il nome categoria, non il badge) | 11 |
| 13 | Comando category:publication-audit | merged | [#560](https://github.com/andrea-bartiromo/quark_blog/pull/560) | `d2dfd6e` | 5/5 (14 assert.); Category*: 245/245 (831 assert.) | 1 reale (fixato: categorie disattivate riportate come Bozza/Programmata invece di Disattivata) | 9 |
| 14 | Test comando audit categorie | merged | [#561](https://github.com/andrea-bartiromo/quark_blog/pull/561) | `678f9b3` | 6/6 (15 assert.); Category*: 251/251 (846 assert.) | 1 reale (fixato: test ordinamento verificava solo lo spareggio per nome, non sort_order) | 13 |
| 15 | Runbook cPanel + front controller pubblico | merged | [#562](https://github.com/andrea-bartiromo/quark_blog/pull/562) | `3a70b5e` | N/A (solo documentazione); Pint pulito | 3 reali (fixati: cadenza cron mancante, probe rewrite con -I inconcludente, esempio front controller senza il path di maintenance.php) | — |
| 16 | Gate deploy integrità front controller | merged | [#563](https://github.com/andrea-bartiromo/quark_blog/pull/563) | `a73aff8` | 10/10 audit (23 assert.); Deploy*: 112/112 (439 assert., 1 skip pre-esistente) | 4 reali (fixati: marker FilesMatch generico, direttive commentate non rilevate, front controller senza condizione !-f, Referrer-Policy mancante) | 15 |
| 17 | Test deploy reale release senza .git (REVISION) | merged | [#565](https://github.com/andrea-bartiromo/quark_blog/pull/565) | `16d5d0e` | 26/26 (155 assert.) | 0 | 16 |
| 18 | Preflight storage persistente release | merged | [#566](https://github.com/andrea-bartiromo/quark_blog/pull/566) | `c16a496` | 9/9 (20 assert.); Deploy*: 124/124 (473 assert., 1 skip pre-esistente) | 1 reale (fixato: percorso relativo non veniva riconosciuto come "dentro" la release) | — |
| 19 | Verifica automatica backup MariaDB | merged | [#567](https://github.com/andrea-bartiromo/quark_blog/pull/567) | `9673d98` | 12/12 (audit) + 5/5 (comando), 38 assert.; suite CI completa verde (1 fallimento pre-esistente ContentClusterAutoLifecycleCompletionTest:231, identico e non bloccante) | 3 reali (fixati: filtro per identityHash mancante, hash di tutta la storia backup invece del solo candidato più recente, soglia età invalida ignorata silenziosamente) | — |
| 20 | Report read-only deploy readiness | merged | [#568](https://github.com/andrea-bartiromo/quark_blog/pull/568) | `1fd4bac` | 4/4 (8 assert.); Deploy*: 146/146 (526 assert., 1 skip pre-esistente); suite CI completa verde (1 fallimento pre-esistente ContentClusterAutoLifecycleCompletionTest:231, identico e non bloccante) | 0 (nessun finding Codex) | 15-19 |
| 21 | Inventario tecnico pagine pubbliche | merged | [#569](https://github.com/andrea-bartiromo/quark_blog/pull/569) | `09cba8d` | 12/12 (audit) + 3/3 (comando); più ampia (PublicPages\|Category): 261/261 (893 assert.) | 1 reale (fixato: campione categoria ignorava il fallback legacy solo-config di Category::publicOptions()) | — |
| 22 | Audit HTTP/canonical/robots/SEO/JSON-LD | merged | [#570](https://github.com/andrea-bartiromo/quark_blog/pull/570) | `85ecea9` | 10/10 (audit) + 2/2 (comando); più ampia (PublicPages\|Canonical\|StructuredData\|Seo\|ArticleViewTracking\|ContinuationAnalytics): 187/187 (817 assert.) | 1 reale (fixato P1: l'audit incrementava le analytics reali di visualizzazione articolo a ogni esecuzione) | 21 |
| 23 | Audit 404/redirect/canonical incoerenti | merged | [#571](https://github.com/andrea-bartiromo/quark_blog/pull/571) | 38c6654 | 13/13 (audit) + 5/5 (comando); PublicPages+Console: 258/258 (819 assert., 3 skip.); suite completa: 4348 (4345 passed + 3 fallimenti pre-esistenti non correlati, stessi già confermati su main pulito) | 5 (Codex, tutti reali, tutti corretti — 404 accettato senza verificare che l'articolo non sia più pubblicato; 302 accettato al pari di 301; redirect verso l'articolo sbagliato non rilevato; confronto canonical sempre sul self-URL invece di `metaCanonicalUrl()`; `--json` non rifletteva i findings nell'exit code) | 21 |
| 24 | Registro interno aggregato 404 | merged | [#572](https://github.com/andrea-bartiromo/quark_blog/pull/572) | 5ce3263 | 10/10 (tracker) + 5/5 (integrazione end-to-end) + 4/4 (comando); suite completa: 4367 (4364 passed + 3 fallimenti pre-esistenti non correlati, stessi già confermati su main pulito) | 5 (Codex, tutti reali — 4 corretti: chiave path_hash sha256 invece di path troncato/case-insensitive; scrittura mai rilanciata; middleware globale sulla risposta finale invece dell'hook su render() per coprire i 404 espliciti da controller; 1 accettato e documentato: l'esclusione redazionale non copre un path che non corrisponde a nessuna rotta — un fix generale (Route::fallback()) è stato tentato e scartato dopo aver riprodotto una regressione peggiore, rottura della corretta individuazione dei verbi alternativi di Laravel/405) | 23 |
| 25 | Audit link interni rotti / esterni irraggiungibili | merged | [#573](https://github.com/andrea-bartiromo/quark_blog/pull/573) | 9b30160 | 7/7 (estrattore) + 10/10 (audit) + 5/5 (comando); suite completa: 4389 (4386 passed + 3 fallimenti pre-esistenti non correlati, stessi già confermati su main pulito) | 0 (nessun finding Codex) | 21 |
| 26 | Audit media (mancanti/alt/peso/formati/crediti) | merged | [#574](https://github.com/andrea-bartiromo/quark_blog/pull/574) | 9052d4f | 10/10 (audit) + 3/3 (comando); più ampia (Media): 479/479 (1644 assert.) | 1 reale (fixato: measureActual non disattivato verso MediaWebpAuditService, causando conversioni WebP reali e sprecate per ogni candidato) | 21 |
| 27 | Baseline performance lab | merged | [#575](https://github.com/andrea-bartiromo/quark_blog/pull/575) | `4652984` | N/A (nessun file PHP toccato); 4390 passed, 11 skipped, 1 pre-esistente (`ContentClusterAutoLifecycleCompletionTest.php:231`); validazione end-to-end reale: 18/18 combinazioni superficie/viewport misurate con successo (vedi docs/PERFORMANCE_LAB_BASELINE.md) | 3 (tutti reali, tutti corretti: isolamento traffico terze parti, validazione risposta, verifica ownership server) | 21 |
| 28 | Test browser navigazione tastiera | merged | [#576](https://github.com/andrea-bartiromo/quark_blog/pull/576) | `bfa4d83` | 12/12 (nuovo tests/browser/keyboard-navigation.spec.js); suite completa: 4390 passed, 1 pre-esistente (`ContentClusterAutoLifecycleCompletionTest.php:231`) | 3 (tutti reali, tutti corretti: traversata partiva dopo skip-link, loop-detection su tag/classe/id anziche' identita' reale, indicatore di focus non confrontato con lo stato senza focus) | 21 |
| 29 | Audit WCAG interno | merged | [#577](https://github.com/andrea-bartiromo/quark_blog/pull/577) | `81e4301` | 17/17 (servizio) + 6/6 (comando, incl. 2 regressioni reali); suite completa: 4412 passed, 11 skipped, 1 pre-esistente (`ContentClusterAutoLifecycleCompletionTest.php:231`) | 4 reali (round 1: nome accessibile falso positivo su solo-immagine con alt, `aria-labelledby` verso id inesistente accettato senza risoluzione; round 2 dopo il fix: `libxml_use_internal_errors` non ripristinato, perdita a livello di processo PHP) — tutti corretti | 21 |
| 30 | Dashboard admin Salute pubblica | merged | [#578](https://github.com/andrea-bartiromo/quark_blog/pull/578) | `ccd9bdf` | 4/4 (controller) + 4/4 (servizio); suite completa: 4420 passed, 11 skipped, 1 pre-esistente (`ContentClusterAutoLifecycleCompletionTest.php:231`) | 2 reali (fixati: `snapshot()` non ripristinava l'id di sessione condiviso dopo i fetch in-process, rischio di cookie di sessione errato/logout silenzioso lato editor; suggerimento CLI della card Media indicava un comando inesistente) | 22-29 |
| 31 | Severità e presa in carico audit | merged | [#579](https://github.com/andrea-bartiromo/quark_blog/pull/579) | `f42157d` | 4/4 (AuditFindingStatusService) + 13/13 (servizio) + 8/8 (controller); suite completa: 4438 passed, 11 skipped, 1 pre-esistente (`ContentClusterAutoLifecycleCompletionTest.php:231`) | 3 reali (fixati: severità SEO accedeva a `$r['url']` invece di `sample_url`, mai eseguito nei test esistenti per corto-circuito su http_status; conteggi aperti/ignorati del registro 404 calcolati solo sui 50 path mostrati invece che sull'intero registro; finding_key del registro 404 usava il path grezzo invece di path_hash, stesso rischio di collisione case-insensitive già risolto altrove per not_found_hits, PR #572) | 30 |
| 32 | Report articoli con carenze editoriali | covered-by-existing | — | — | vedi nota | 0 | 30 |
| 33 | Audit heading Fonti/Fonti primarie duplicati | merged | [#580](https://github.com/andrea-bartiromo/quark_blog/pull/580) | `530753a` | 111/111 (EditorialQualityCheckerTest, 104 esistenti + 7 nuovi); suite completa: 4445 passed, 11 skipped, 1 pre-esistente (`ContentClusterAutoLifecycleCompletionTest.php:231`) | 1 reale (fixato: DUPLICATE_SOURCES_HEADING_TAGS escludeva h1, formato di blocco reale nell'editor admin, mentre ArticleManualSourcesDetector riconosce già h1-h6) | — |
| 34 | Regressione pannello fonti auto vs manuali | merged | [#581](https://github.com/andrea-bartiromo/quark_blog/pull/581) | `726329b` | 23/23 (nuova suite ArticlePrimarySourcesPanelReconciliationTest + ArticlePublicPrimarySourcesTest + ArticleManualSourcesDetectorTest, 49 assert.); più ampia (Article\*+EditorialQuality): 304/304 (854 assert.); CI: 5/6 check verdi, 1 (PHP 8.4) con 2 fallimenti pre-esistenti non correlati (ContentClusterAutoLifecycleCompletionTest:231, noto; PublicSurfaceResponsiveImageTest riga 189, flake Faker/escaping su nome autore con apostrofo — nessuno dei due nel codice toccato da questa PR, che modifica solo test) | 1 reale (fixato: la heading manuale nel test di coesistenza era messa PRIMA del delimitatore `---`, quindi già dentro $mainBody — non distingueva una regressione di ArticleController::show() che passasse $mainBody invece dell'intero $article->body ad hasManualSourcesSection(); spostata dopo il delimitatore) | 33 |
| 35 | Admin baseline mensile, denominatori separati | merged | [#582](https://github.com/andrea-bartiromo/quark_blog/pull/582) | `bb51bc3` | 33/33 (nuova suite PublicHealthBaselineService + comando + estensione controller, 108 assert.); più ampia (PublicPages+Admin PublicHealthDashboard+Console): 339/339 (3 skip pre-esistenti); suite CI completa: 4462 passed, 11 skipped, 1 pre-esistente (`ContentClusterAutoLifecycleCompletionTest.php:231`) | 1 reale (fixato: `firstOrNew()+save()` per riga non atomico — un'invocazione manuale del comando sovrapposta a quella schedulata poteva violare il vincolo di unicità domain+period; sostituito con `upsert()`, stesso pattern già in uso in ContentClusterSuggestionService) | 30 |
| 36 | Checklist certificazione primo piano editoriale | merged | [#583](https://github.com/andrea-bartiromo/quark_blog/pull/583) | `f31a999` | 22/22 (nuova suite servizio+comando+display, 49 assert.); più ampia (Admin Article\*+EditorialQuality+Console): 529/529 (3 skip pre-esistenti); suite CI completa: 4483 passed, 11 skipped, 1 pre-esistente (`ContentClusterAutoLifecycleCompletionTest.php:231`) | 2 reali (fixati: la certificazione controllava solo `status==='published'`, non lo stesso predicato di `Article::scopePublished()` — `published_at<=now()` incluso — usato davvero da `HomeController`; il comando `articles:featured-certification-audit` sommava query per articolo, corretto precalcolando indice titoli duplicati + eager-load autore + set "in evidenza visibili", stesso pattern di EditorialQualityAuditService) | 30-35 |
| 37 | Report pubblicazioni programmate 30gg | merged | [#584](https://github.com/andrea-bartiromo/quark_blog/pull/584) | `f0e64d0` | 13/13 (nuovo servizio + controller, 36 assert.); più ampia (EditorialOperations+AdminNavigation): 170/170 (705 assert.); suite CI completa: 4501 passed, 12 skipped, 3 pre-esistenti non correlati (`ContentClusterAutoLifecycleCompletionTest.php:192`, `ProjectModelTest`/`ProjectTaskControllerTest` github-sync) | 0 (nessun finding Codex) | 30-35 |
| 38 | Modello interno "Cosa sappiamo davvero" | merged | [#595](https://github.com/andrea-bartiromo/quark_blog/pull/595) | `a26a189` | 22+6 nuovi test dedicati (TrustKnowledgeStatementControllerTest+TrustKnowledgeStatementTest); suite di regressione mirata (Admin, 1128 test): 1126 passati, 2 pre-esistenti (`ProjectModelTest.php:235`, `ProjectTaskControllerTest.php:193`); CI PR: 6/7 verdi, unico rosso `ContentClusterAutoLifecycleCompletionTest.php:231` (confermato pre-esistente) | 2 reali (Codex): fix CI del rollback mirato `mariadb-content-clusters` (la nuova migration con FK verso `content_clusters` mancava dalla lista esplicita del workflow, causava `SQLSTATE[23000] 1451` sul drop — corretto aggiungendo il path mancante); NO-GO B-45 (`docs/TRUST_LAYER_COSA_SAPPIAMO_DAVVERO_PILOT.md`) segnalato come non qualificato "solo pubblico" — sospeso, portato all'utente, che ha deciso esplicitamente di procedere (il gate resta valido per il pilot pubblico reale, nessuna delle sue tre condizioni è toccata da un modello dati interno vuoto; decisione registrata come addendum nel documento originale) | — |
| 39 | Campi/validazioni Trust | merged | [#596](https://github.com/andrea-bartiromo/quark_blog/pull/596) | `403e507` | 7 nuovi test dedicati; suite di regressione mirata (Admin, 1135 test): 1133 passati, 2 pre-esistenti (`ProjectModelTest.php:235`, `ProjectTaskControllerTest.php:193`); CI PR: 6/7 verdi, unico rosso `ContentClusterAutoLifecycleCompletionTest.php:231` (confermato pre-esistente) | 2 reali (Codex): il form offriva Concept/Percorso inattivi come opzioni selezionabili poi rifiutate dalla validazione (Concept nasce "bozza" per default — caso comune, non edge case); `before_or_equal:today` confrontava nel fuso applicativo (UTC) invece che in quello editoriale (`Article::EDITORIAL_TIMEZONE`, Europe/Rome) — nella prima ora/due dopo mezzanotte a Roma una data locale corrente veniva rifiutata come futura | 38 |
| 40 | Preview non indicizzabile pilot Trust | merged | [#598](https://github.com/andrea-bartiromo/quark_blog/pull/598) | `ac7b6aa` | 35/35 (TrustKnowledgeStatementControllerTest + TrustKnowledgeStatementTest, 99 assert.); regressione mirata (Admin + Trust, 1140 test): 1137 passati, 2 pre-esistenti (`ProjectModelTest.php:235`, `ProjectTaskControllerTest.php:193`); CI PR: 6/7 verdi, unico rosso `ContentClusterAutoLifecycleCompletionTest.php:231` (confermato pre-esistente anche su `main`) | 1 reale (Codex): la preview renderizzava `consenso`/`incertezza`/`cosa_manca` (textarea multi-riga) dentro `<p>` semplici — gli a capo inseriti dall'editor collassavano in spazio normale; fixato con `white-space:pre-line`, verificato con revert/re-test | 39 |
| 41 | Componente accessibile consenso/incertezza | merged | [#599](https://github.com/andrea-bartiromo/quark_blog/pull/599) | `eef0a01` | 5 nuovi test dedicati (Blade::render, isolamento senza modello Eloquent, incl. escaping XSS); TrustKnowledgeStatementControllerTest + TrustKnowledgeStatementTest invariati (35/35, nessuna regressione dall'estrazione); regressione mirata (Admin + Trust + TopicChips, 1150 test): 1147 passati, 2 pre-esistenti (`ProjectModelTest.php:235`, `ProjectTaskControllerTest.php:193`); CI PR: 5/6 verdi, unico rosso `ContentClusterAutoLifecycleCompletionTest.php:231` (confermato pre-esistente anche su `main`) | 1 reale (CI, non Codex): il componente era stato messo in `components/kairus/`, ma quella directory è il sistema editoriale isolato "Kairus Editorial Foundations V1" con contratto rigido (ogni classe letterale dev'essere `kairus-`-prefissata) verificato da `KairusEditorialFoundationsIsolationTest` — il componente riusa correttamente le classi `premium-static-section`/`premium-copy-card` preesistenti, quindi non poteva rispettarlo; spostato fuori dal namespace protetto in `resources/views/components/trust-knowledge-summary.blade.php` (flat, come `x-topic-chips`) | 39 |
| 42 | Gate pubblicazione pilot Trust | merged | [#601](https://github.com/andrea-bartiromo/quark_blog/pull/601) | `d98d762` | 6 nuovi test servizio (Unit/Trust) + 6 nuovi test controller (Feature/Admin), inclusa verifica dedicata "nessun form scrivibile"; TrustKnowledgeStatementControllerTest+TrustKnowledgeStatementTest invariati (47/47); regressione mirata (Admin + Trust, 1165 test): 1162 passati, 2 pre-esistenti (`ProjectModelTest.php:235`, `ProjectTaskControllerTest.php:193`); CI PR: 6/7 verdi, unico rosso `ContentClusterAutoLifecycleCompletionTest.php:231` (confermato pre-esistente) | 0 (nessun finding Codex) | 40, 41 |
| 43 | Metriche privacy-first pilot | merged | [#602](https://github.com/andrea-bartiromo/quark_blog/pull/602) | `447dc0d` | 6 nuovi test servizio (Unit/Trust) + 6 nuovi test controller (Feature/Admin, poi saliti a 10+7 dopo i fix review), incl. verifica strutturale schema (nessuna colonna identificativa) e query budget O(1); regressione mirata (Admin + Trust, 1177 test): 1174 passati, 2 pre-esistenti (`ProjectModelTest.php:235`, `ProjectTaskControllerTest.php:193`); CI PR: verde su tutti i check tranne l'unico rosso `ContentClusterAutoLifecycleCompletionTest.php:231` (confermato pre-esistente anche sugli ultimi 2 push su `main`) | 3 reali: 1 auto-catturato prima della review (nome vincolo FK auto-generato da `constrained()` oltre il limite 64 caratteri MariaDB, non rilevabile su SQLite) + 1 CI (la nuova migration con FK verso `trust_knowledge_statements` mancava dalla targeted rollback list di `content-clusters-mariadb.yml`, stesso pattern già visto al Cantiere 38) + 2 Codex P2 (finestra di aggregazione non vincolata ai 30gg da pubblicazione previsti dal contratto B-44; `days_collected` basato sulla sola età dello statement invece che ancorato al rollout della strumentazione — entrambi fixati con test di regressione dedicati verificati via revert) | 40 |
| 44 | Admin decisione GO/NO-GO pilot | pending | — | — | — | — | 42, 43 |
| 45 | Protocollo editoriale Trust documentato | merged | [#603](https://github.com/andrea-bartiromo/quark_blog/pull/603) | `8790965` | 5 nuovi test (`TrustEditorialProtocolDriftTest`, verifica route/classi/file citati dal documento esistano davvero + tripwire su NO-GO/Cantiere 44 non costruito, poi salito a 5 dopo il fix review); regressione mirata (Trust, 95 test): 95/95 passati; CI PR: verde su tutti i check tranne l'unico rosso `ContentClusterAutoLifecycleCompletionTest.php:231` (confermato pre-esistente) | 2 reali (Codex P2): il documento descriveva Cosa manca/Concept-Percorso/Ultimo controllo come obbligatori, ma `StoreTrustKnowledgeStatementRequest::rules()` li rende `nullable` — riformulato come raccomandazioni editoriali; il drift test non copriva il test method citato esplicitamente dal protocollo come prova strutturale (`TrustPilotPreviewMetricsControllerTest::test_the_preview_views_table_has_no_visitor_identifying_column`) — aggiunta copertura dedicata | 38-43 |
| 46 | Pacchetto editoriale non pubblico "Mente e comportamento" | merged | [#604](https://github.com/andrea-bartiromo/quark_blog/pull/604) | `4db4483` | 6 nuovi test (`ProvisionNonPublicContentClusterPackageTest`, incl. verifica end-to-end che il pacchetto resti escluso dalla sitemap pubblica anche con un articolo reale pubblicato collegato); regressione mirata (ContentCluster, 268 test): 267/268 passati, 1 pre-esistente non correlato (`ContentClusterAutoLifecycleCompletionTest`); CI PR: verde su tutti i check tranne l'unico rosso `ContentClusterAutoLifecycleCompletionTest.php:231` (confermato pre-esistente) | 1 reale (Codex P2): `lifecycle_status` ereditava il default di colonna `'complete'` invece di `LIFECYCLE_UPDATING` — avrebbe rotto `acceptsPathSubscriptions()`/`PathContinuationNotifier` una volta attivato il pacchetto mentre l'editore ancora scrive articoli — fixato con test di regressione dedicato | 45 |
| 47 | Audit attivazione "Mente e comportamento" | merged | [#605](https://github.com/andrea-bartiromo/quark_blog/pull/605) | `73c5d6e` | 9 nuovi test (`ContentClusterPublicationReadinessAuditCommandTest`, incl. verifica end-to-end diretta col comando del Cantiere 46); regressione mirata (ContentCluster, 277 test): 276/277 passati, 1 pre-esistente non correlato (`ContentClusterAutoLifecycleCompletionTest`); CI PR: verde su tutti i check tranne l'unico rosso `ContentClusterAutoLifecycleCompletionTest.php:231` (confermato pre-esistente) | 1 reale (Codex P2): un Percorso programmato (`is_active=true`, `publish_at` futuro) veniva valutato "ad adesso" invece che al momento della sua effettiva apertura — `evaluate()` ora riceve `$cluster->publish_at`, evitando errori spuri su pillar/articoli programmati prima di quella data — fixato con test di regressione dedicato | 46 |
| 48 | Preview/audit sitemap/ricerca/canonical M&C | merged | [#606](https://github.com/andrea-bartiromo/quark_blog/pull/606) | `9abd947` | 10 nuovi test (`ContentClusterAdminPreviewTest`, incl. 2 aggiunti dopo review Codex: anteprima admin, audit end-to-end sitemap/ricerca/canonical del pacchetto "Mente e comportamento", suppressione analytics e form iscrizione in anteprima); `ContentClusterPublicTest` (6/6, verificato bit-per-bit invariato dopo l'estrazione di `ContentClusterShowPageData`); 2 nuovi test unitari (`AnalyticsExclusionServiceTest`); regressione mirata (ContentCluster 283 test, TrovaEntitySearch+Seo+Sitemap 127 test): 282/283 + 127/127, 1 pre-esistente non correlato (`ContentClusterAutoLifecycleCompletionTest`); CI PR: 1 flake aggiuntivo confermato non correlato e non deterministico (`PublicSurfaceResponsiveImageTest`, dipendente da nome Faker non seedato con apostrofo, 8/8 pass in locale) | 3 reali (Codex): P1 anteprima admin caricava GA4 in produzione registrando traffico editoriale come pubblico — `AnalyticsExclusionService::shouldLoadAnalytics()` ora accetta `previewMode` (chiude anche un gap identico pre-esistente nell'anteprima Category); P2 anteprima "di sola lettura" poteva raggiungere un Percorso pubblico "in aggiornamento" e renderizzare il vero form di iscrizione live — sostituito da avviso statico in `previewMode`; P2 nessun link di anteprima nell'admin — aggiunto "Vedi anteprima →" nel form Percorso, stesso pattern di Category | 46, 47 |
| 49 | Categorie esistenti → hub editoriali | merged | [#607](https://github.com/andrea-bartiromo/quark_blog/pull/607) | `8c0db85` | 8 nuovi test (`CategoryCuratorNoteTest`: fallback generico invariato senza nota, resa della nota quando presente, creazione/modifica/rimozione da admin, guest bloccato, anteprima admin coerente, rifiuto di un valore array); regressione mirata (filtro `Category`, 264 test): 264/264; sweep SEO/canonical/query-budget (28 test): 28/28; `migrate:fresh` verificato end-to-end; CI PR: verde su tutti i check tranne l'unico rosso `ContentClusterAutoLifecycleCompletionTest.php:231` (confermato pre-esistente); Pint: passed, `git diff --check`: pulito | 1 reale (Codex P2): la validazione di `curator_note` mancava della regola `string` — un `curator_note[]` array avrebbe superato `max:2000` contando gli elementi invece dei caratteri, rischiando di persistere un array nella colonna testo — fixato con la stessa regola già in uso per `ContentCluster::curator_note`, con test di regressione dedicato | 1-8 |
| 50 | Selezione manuale in evidenza per categoria | merged | [#608](https://github.com/andrea-bartiromo/quark_blog/pull/608) | `c0fc1c0` | 12 nuovi test (`CategoryFeaturedArticleTest`: nessuna sezione senza selezione, resa quando idoneo, esclusione se bozza/categoria cambiata, inclusione via categoria secondaria nel rendering pubblico E nel selettore admin, articolo selezionato preservato fuori dalla finestra dei 200 candidati, creazione/rimozione da admin, guest bloccato, id inesistente rifiutato, anteprima admin coerente); regressione mirata (filtro `Category`, 276 test): 276/276; sweep aggiuntivo SEO/query-budget/categoria-secondaria (32 test): 32/32; `migrate:fresh` verificato; CI PR: verde su tutti i check tranne l'unico rosso `ContentClusterAutoLifecycleCompletionTest.php:231` (confermato pre-esistente); Pint: passed, `git diff --check`: pulito | 2 reali (Codex P2): (1) il selettore admin "In evidenza" interrogava solo la categoria principale, omettendo articoli collegati come categoria secondaria pur essendo già accettati in pubblico da `featuredArticleForDisplay()` — corretto includendo `secondaryCategories` nella query; (2) l'articolo già selezionato, se finito fuori dalla finestra dei 200 candidati più recenti, spariva dal `<select>` e un salvataggio del form che non intendeva toccare questo campo lo azzererebbe in silenzio — corretto aggiungendolo sempre esplicitamente ai candidati | 49 |
| 51 | Pacchetto editoriale non pubblico "Scienza e metodo" | covered-by-existing | — | — | Ispezione: `content-clusters:provision-non-public-package` (Cantiere 46) è già completamente generico (slug/nome passati come argomenti, nessun riferimento a "Mente e comportamento" nel codice) e la sua genericità per questo secondo pacchetto è già testata esplicitamente da `test_the_second_package_reuses_the_same_command` (`ProvisionNonPublicContentClusterPackageTest.php:116`). La creazione reale del pacchetto in produzione resta un'azione editoriale umana (`--apply` invocato da un editore), fuori dal perimetro di scrittura automatica di questo programma. Nessuna PR necessaria: ne aprirebbe una che duplica codice e test identici | 0 | 45, 46 |
| 52 | Audit attivazione "Scienza e metodo" | covered-by-existing | — | — | Ispezione: `content-clusters:publication-readiness` (Cantiere 47) itera già su *tutti* i `ContentCluster` non pubblicamente visibili (`ContentCluster::query()->ordered()->get()->reject(fn ($c) => $c->isPubliclyVisible())`), senza alcun riferimento hardcoded a un pacchetto specifico — il giorno in cui "Scienza e metodo" esisterà davvero, comparirà nel report automaticamente. Stesso discorso per l'anteprima admin del Cantiere 48 (route-model-binding semplice, qualunque `ContentCluster`). Nessuna PR necessaria | 0 | 51 |
| 53 | Benchmark CTR/navigazione hub categorie | pending | — | — | — | — | 49, 50 |
| 54 | Command Center vista categorie | merged | [#636](https://github.com/andrea-bartiromo/quark_blog/pull/636) | `0288c35` | 8 nuovi test (`CategoryCommandCenterServiceTest`, poi saliti a 10 dopo i fix review) + 6 nuovi test (`CategoryCommandCenterControllerTest`); suite `--filter="Categor"` completa: 318/318 passed; Pint pulito | Ispezione diretta pre-cantiere: `resources/views/admin/categories.blade.php` mostra già conteggio articoli/stato/visibilità/checklist attivazione per categoria (Cantiere 12) — gap reale identificato: nessun punto mostra, PER CATEGORIA, i segnali di content health che `EditorialOperationsDashboardService` calcola solo in aggregato sull'intero sito. Nuovo `CategoryCommandCenterService` (aggregatore read-only, riusa `ArticleContentHealthService::evaluate()` e `CategoryPublicationReadiness::evaluate()`, non ricalcola nulla), nuovo controller/rotta/vista admin. Bug reale auto-catturato prima della review: la query selezionava solo `['id','category','title']` dagli articoli, facendo risultare "vuoti" cover/excerpt/seo_title/ecc. e producendo falsi positivi di content health — corretto con select completa. 2 finding reali Codex P2 dopo l'apertura della PR: (1) `ArticleContentHealthService::evaluate()` richiede `contentClusters` per il check "percorso", non eager-loaded → N+1 per articolo, stesso fix già in uso in `EditorialOperationsDashboardService`; (2) il raggruppamento usava solo la categoria principale, escludendo gli articoli associati come categoria SECONDARIA via pivot `article_category` — `CategoryDiscoveryPageData`/`CategoryPublicationReadiness` includono già entrambe, stesso criterio applicato qui. Revert-verification: sabotate e ripristinate (via diff) singolarmente — raggruppamento per categoria, filtro `STATUS_WARNING`, readiness hardcoded, merge categorie secondarie, eager load contentClusters — ogni sabotaggio ha fatto fallire il test dedicato come atteso, ogni ripristino verificato identico al backup | 49 |
| 55 | Test isolamento assoluto categorie non pubbliche | merged | [#635](https://github.com/andrea-bartiromo/quark_blog/pull/635) | `aa1d609` | Ispezione diretta: `Category::scopePubliclyVisible()`/`isPubliclyVisible()` distinguono tre stati "mai pubblici" (is_active=false, is_active=true+draft, is_active=true+scheduled futuro). `CategoryScheduledPublicationTest.php` copre già esaustivamente ogni superficie pubblica per "programmata futura" e la pagina categoria diretta per "bozza" — ma nessun test eseguiva una vera richiesta HTTP verso `/categoria/{slug}` o `sitemap.xml` per una categoria disattivata: l'unico test esistente per quello stato verificava solo i metodi del modello, mai il confine HTTP realmente esposto; stesso gap per l'esclusione di "bozza" dalla sitemap. Nessuna modifica al codice di produzione (comportamento già corretto, confermato leggendo `ArticleController::category()` e `SeoController::sitemap()`). Nuovo `tests/Feature/CategoryAbsoluteIsolationTest.php`, 4 test: pagina categoria disattivata → 404 via vera richiesta HTTP; categoria disattivata esclusa dalla sitemap; categoria bozza esclusa dalla sitemap; categoria disattivata poi riattivata torna immediatamente raggiungibile (visibilità calcolata dal vivo, mai una tombstone). Ogni comportamento verificato per revert sabotando `isPubliclyVisible()` e `scopePubliclyVisible()` uno alla volta; sweep `--filter Categor` 304/304 verdi; Pint: passed | 0 (nessun finding Codex/CodeRabbit) | 9, 49 |
| 56 | Modello dati minimo Speciali editoriali | merged | [#628](https://github.com/andrea-bartiromo/quark_blog/pull/628) | `511788d` | Nessuna modifica al codice di produzione: `special_pages`/`SpecialPage` (slug univoco a livello DB, title, description, content JSON nullable, is_active default true) è già un modello dati minimo e genuinamente generico per qualsiasi Speciale editoriale, non accoppiato a Turing — confermato da ispezione diretta (nessuna colonna/vincolo/metodo riferisce "turing"; il livello media itera già su tutti i `SpecialPage`). Il gap reale: nessun test lo dimostrava con uno slug diverso da 'turing'. 7 test nuovi (`SpecialPageDataModelTest`, con un secondo slug sintetico di test): unicità slug a livello DB, round-trip content come array, gestione content nullo, default DB is_active, `bySlug()` risolve un secondo Speciale indipendentemente da 'turing', fallback su pagina disattivata/slug inesistente. Ogni asserzione verificata per revert in due round di sabotaggio (modello, poi migrazione); sweep `--filter "SpecialPage\|Turing\|MediaReference\|MediaUsage\|MediaClassification"` 396/396 verdi; Pint: passed | 0 (nessun finding Codex/CodeRabbit) | — |
| 57 | Bozza non pubblica Speciale Turing | covered-by-existing | — | — | Ispezione (self, dopo aver appena lavorato sull'intero dominio Turing per il Cantiere 68): `config('turing.chapters_public')` (default `false`) fa renderizzare a `TuringPageController::index()` la vista `turing.coming-soon` invece dell'hub, e ogni rotta capitolo (`/turing/enigma\|ai\|legacy\|computation\|intelligence`) risponde con un 302 verso `/turing` — comportamento già completamente coperto da `tests/Feature/TuringReleaseGateTest.php` (11 test: default `false`, vista coming-soon, redirect 302 per ogni capitolo, meta title/description/canonical/robots della landing, card di anteprima non cliccabili con `aria-disabled="true"`, ripristino del rendering normale quando il flag è `true`) e da `tests/Feature/TuringSitemapTest.php` (i capitoli non pubblicati sono esclusi dalla sitemap, `/turing` invece resta indicizzabile). Nessuna PR necessaria: ne aprirebbe una che duplica assert già esistenti e verdi | 0 | 56 |
| 58 | Capitoli Turing ordinabili manualmente | merged | [#624](https://github.com/andrea-bartiromo/quark_blog/pull/624) | `1477c80` | Le card dei capitoli sull'hub `/turing` erano già un array ordinato dentro `SpecialPage::content` (nessuna colonna "position" separata), ma l'admin "lite" le passava solo come campi nascosti, senza modo di riordinarle. Nuovo `TuringController::moveCard()` (scambia due card adiacenti, preservando tutti i campi) + rotta `POST /admin/turing/cards/{index}/sposta` (stesso middleware `auth`+`editor`) + nuova sezione "Ordine dei capitoli in evidenza" con pulsanti ↑/↓ per card. Nessun contenuto editoriale nuovo: sposta solo le tre card di route già esistenti in produzione (Enigma/AI/Legacy). 14 test totali in `TuringChapterCardReorderTest` (11 iniziali + 3 dai fix Codex), ogni comportamento verificato per revert; sweep `--filter Turing` 263/263 verdi; Pint: passed | 2 reali (Codex P2, entrambi verificati per revert e fixati in un secondo push): (1) i pulsanti usavano `formaction`/`formmethod` sullo stesso form principale delle impostazioni — un click su "sposta" inviava (e faceva scartare silenziosamente da `moveCard()`) ogni modifica di testo/immagine non ancora salvata — corretto spostando l'intera sezione fuori dal form principale, un mini `<form>` indipendente per pulsante; (2) quando `content` è ancora vuoto (pagina appena creata), l'editor mostrava le 3 card di route di default con pulsanti attivi, ma `moveCard()` leggeva un array vuoto e rifiutava sempre lo spostamento — corretto promuovendo `TuringPageController::defaultRouteCards()` a `public static` (unica fonte di verità, già usata dal rendering pubblico) e riusandola in un nuovo `resolvedCards()` condiviso da `edit()`/`moveCard()`; ha anche corretto una divergenza pre-esistente (il fallback duplicato nella vista puntava alla rotta `/turing/ia` rimossa) | 57 |
| 59 | Mappa concettuale interna Turing | merged | [#625](https://github.com/andrea-bartiromo/quark_blog/pull/625) | `f5089f2` | `docs/00_Governance/Architettura_Editoriale_v1.0.docx` §4 "Mappa dei contenuti" (versione 1.0, 29 luglio 2026) è un vero blueprint editoriale già redatto da un umano — 26 argomenti con capitolo principale, richiami (con qualificatore: teaser/cenno/richiamo/fondamento) e livello di approfondimento — verificato riga per riga contro l'XML del `.docx` prima della trascrizione, non una tassonomia inventata (caso opposto del Cantiere 79). Nuovo `TuringConceptMapService` (trascrizione letterale + raggruppamento per capitolo) + pagina admin interna sola lettura `GET /admin/turing/mappa-concettuale`, mai pubblica. 15 test totali (13 iniziali + 2 dal fix Codex) in `TuringConceptMapServiceTest`/`TuringConceptMapPageTest`, ogni comportamento verificato per revert; sweep `--filter Turing` 278/278 verdi; Pint: passed | 1 reale (Codex P2, verificato per revert e fixato in un secondo push): la trascrizione riduceva ogni richiamo al solo capitolo di destinazione, scartando il qualificatore che la fonte assegna a ciascuno (es. "Macchina universale" reso come "Ai · Legacy" invece di "AI (cenno) · Legacy (teaser)") — una trascrizione che si dichiara letterale ma scarta un'informazione reale della fonte è ambigua; corretto passando `richiami` da `list<string>` a `list<array{capitolo, qualificatore}>` | 57 |
| 60 | Timeline Turing accessibile/testabile | covered-by-existing | — | — | Ispezione (self): `resources/views/components/special/timeline.blade.php` usa markup semantico (`<ol>/<li>`, `aria-labelledby` sulla sezione, `aria-haspopup="dialog"` + `aria-label` sui trigger, nessun elemento interattivo annidato), `resources/views/components/special/modal.blade.php` espone `role="dialog"`/`aria-modal="true"`/`aria-labelledby`, `public/js/special-modal.js` implementa ESC per chiudere, focus trap con `Tab`, e ripristino del focus sul trigger alla chiusura — tutto già verificato da `tests/Feature/TuringTimelineDetailsModalTest.php` (20 test: corrispondenza id trigger/modale, nessuna collisione su titoli duplicati, verifica DOM via XPath dell'assenza di elementi interattivi annidati, presenza di `role="dialog"`, comportamento di override da CMS, round-trip salvataggio/ricarica admin). Nessuna PR necessaria: capacità già reale e già provata | 0 | 57 |
| 61 | Gestione fonti per capitolo | merged | [#626](https://github.com/andrea-bartiromo/quark_blog/pull/626) | `16fd34a` | `docs/05_Turing_Fonti/Registro_Fonti_v0.1.md` è un puro stub (Stato: DA REDIGERE) — a differenza del Cantiere 59 nessuna bibliografia reale esiste ancora, ma `Architettura_Editoriale_v1.0.docx` §7 contiene un mandato editoriale reale ("ogni pagina dovrebbe includere almeno una citazione, sempre attribuita con fonte e anno") con indicazioni per capitolo (es. "On Computable Numbers" 1936 per Computation, scuse 2009 per Legacy) — nessun testo verbatim, solo quali fonti citare. Nuovo registro `TuringChapterSource` (tabella vuota per costruzione, nessuna riga creata da questo programma, niente FK) + CRUD admin minimale (`Admin\TuringChapterSourceController`) + componente pubblico `<x-turing.chapter-sources>` che mostra una sezione "Fonti" in fondo a ciascun capitolo solo quando l'editor ha registrato almeno una fonte per quel capitolo. 17 test nuovi (`TuringChapterSourceAdminTest` 12, `TuringChapterSourcesPublicRenderingTest` 5), ogni comportamento verificato per revert; sweep `--filter Turing` 295/295 verdi; sweep aggiuntivo `--filter "PublicPages\|Wcag\|Seo\|Accessibility"` 181/181 verdi; Pint: passed | 0 (nessun finding Codex) | 58 |
| 62 | Audit anti-hub-vuoto Speciali | covered-by-existing | — | — | Ispezione (self): l'unico Speciale reale, Turing, ha già una tripla protezione contro un hub vuoto, ciascuna già provata da test esistenti. (1) Hub CMS-driven (`/turing` quando pubblico): `TuringPageFallbacksTest` (13 test) verifica che l'hub mostri sempre il contenuto di default — anche quando la riga `SpecialPage` non esiste affatto (`test_turing_page_uses_fallbacks_when_special_page_record_is_missing`), quando `content` è vuoto, quando editorial_blocks/timeline/card sono strutturalmente vuoti o hanno valori non validi — mai un hub realmente vuoto. (2) Landing "In arrivo" (`turing.coming-soon`, stato reale di default in produzione): ispezione diretta della sorgente conferma zero dipendenza da `SpecialPage`/CMS — contenuto interamente statico, strutturalmente non può essere vuota. (3) I 5 capitoli: contenuto editoriale interamente hardcoded nei rispettivi Blade (solo poche immagini hanno un override CMS opzionale) — stesso motivo, non possono essere vuoti. Il modello dati generico `SpecialPage` (Cantiere 56) è già confermato strutturalmente sano per un futuro secondo Speciale, ma nessuno esiste ancora da auditare concretamente. Nessuna PR necessaria: ne aprirebbe una che duplica coperture già esistenti e verdi, stesso schema già usato per le righe 57/60 | 0 | 56-61 |
| 63 | Prototipo non pubblico navigazione Turing | merged | [#629](https://github.com/andrea-bartiromo/quark_blog/pull/629) | `d1f9088` | Ispezione diretta: con `turing.chapters_public=false` (default produzione), `TuringPageController::index()` mostrava a chiunque — editor autenticati inclusi — solo la landing "In arrivo": nessuno poteva rivedere l'hub reale né la rete di navigazione fra i 5 capitoli prima di renderli pubblici. Nuovo `Admin\TuringController::previewHub()`/`previewChapter($chapter)` (rotte `GET /admin/turing/anteprima[/{chapter}]`, dentro auth+editor esistente) riusano le stesse viste pubbliche reali con `previewMode=true` (banner + `noindex,nofollow`, mai `recordView()`) — stesso pattern già stabilito da `Admin\ContentClusterController::preview()` (Cantiere 48) e `Admin\CategoryController::preview()` (Cantiere 11). Link "Vedi anteprima →" aggiunto in `admin/turing-lite.blade.php`. 12 test nuovi (`TuringNavigationPreviewTest`), ogni comportamento verificato per revert in 3 round di sabotaggio; sweep `--filter Turing` 313/313 verdi; Pint: passed | 1 reale (Codex P2, verificato per revert e fixato in un secondo push): l'anteprima riusava le viste pubbliche ma ogni link interno (hero, card, blocchi editoriali, breadcrumb, CTA "Continua il percorso") puntava ancora alle vere rotte pubbliche `/turing/*` — che con il gate chiuso reindirizzano fuori dall'anteprima verso la landing "In arrivo", vanificando lo scopo del cantiere. Fixato con un nuovo `App\Support\TuringPreviewLink` (unica fonte di verità per ogni link interno Turing, preview-aware) usato ovunque un link fosse scritto direttamente nelle viste, più una riscrittura mirata degli URL dati (card/blocchi editoriali dell'hub) verso il capitolo in anteprima; sweep `--filter Turing` 314/314 verdi dopo il fix | 58-61 |
| 64 | Un visual verificabile per lo Speciale | merged | [#630](https://github.com/andrea-bartiromo/quark_blog/pull/630) | `40fa0a8` | Ispezione diretta: `TuringEditorialAssetsTest` esisteva già ma copriva solo i 13 asset WebP hub/pannello, con un bug reale mai notato — verificava l'assenza di asset legacy nel file morto scoperto nel Cantiere 66 (`turing.blade.php`), mai nel file realmente servito, e non copriva affatto 3 dei 5 capitoli reali. Il capitolo Enigma ha inoltre un proprio apparato iconografico dedicato di 12+ immagini PNG (già enumerato da `TuringEnigmaPageTest::enigmaAssets()`, 20 voci) che non aveva alcuna verifica di decodifica/formato. Corretto il file controllato (ora la vera risposta HTTP di hub+5 capitoli, non i soli sorgenti Blade) ed esteso a tutti i capitoli; aggiunta verifica di decodifica PNG riusando la lista canonica esistente (mai una seconda lista duplicata). Finding reale scoperto e documentato (non corretto in autonomia, decisione editoriale/visiva fuori perimetro): `hero-enigma.png`/`cutaway-enigma.png` sono 287×289px, sotto lo standard 1200px dichiarato in `docs/04_Turing_Visual/Registro_Asset_Turing_v1.0.md`, usate come fallback per un hero quasi a schermo intero (`.enigma-hero`, `background-size:cover`) — stato realmente servito oggi (nessun override CMS in `TuringSeeder`); documentato con `markTestSkipped()` + tripwire. Ogni comportamento verificato per revert; sweep `--filter Turing` 336/337 verdi (1 skip, il gap documentato); Pint: passed | 2 reali (Codex P2, entrambi verificati per revert e fixati in un secondo push): (1) il test "no asset legacy" leggeva solo `turing/index.blade.php`, che delega quasi tutto il proprio markup a 7 `@include('turing.partials.*')` — un asset legacy in una partial sarebbe passato inosservato; corretto renderizzando la vera risposta HTTP di ogni pagina; (2) la nuova lista di asset Enigma duplicava (in modo meno completo, 12 contro 20 voci) `TuringEnigmaPageTest::enigmaAssets()` già esistente — corretto rimuovendo il duplicato e aggiungendo il controllo di decodifica come nuovo test sulla stessa lista canonica | 63 |
| 65 | Performance e immagini responsive Speciale | merged | [#631](https://github.com/andrea-bartiromo/quark_blog/pull/631) | `3119ba8` | Ispezione diretta: `<x-turing.article.figure>`, componente usato per le 12 immagini editoriali di Enigma, non risolveva mai da sé `width`/`height` — le 10 figure hardcoded in `enigma.blade.php` passano sempre dimensioni letterali, ma l'unico punto realmente CMS-driven (l'anatomia della macchina quando un editor carica una propria immagine, `$anatomyIsCms`) non ne passava nessuna: nessuno spazio riservato dal browser prima del caricamento (layout shift reale). Stesso gap trovato nel campo CMS opzionale `why_items` dell'hub (`turing/partials/legacy-section.blade.php`). Corretto riusando lo stesso meccanismo già esistente e testato di `<x-special.chapter-opener>`/`<x-special.timeline>` (`App\Support\PublicImageDimensions::forUrl()`, protezione da path traversal e URL esterni) in entrambi i punti, con le dimensioni esplicite sempre prioritarie quando passate. Non genera srcset/immagini responsive (fuori scope, richiederebbe Media Library `diskName` che le immagini statiche di Turing non hanno). 5 test nuovi in `PublicImageIntrinsicSizingTest` (auto-risoluzione, precedenza dell'esplicito, override CMS reale dell'anatomia via route, why_items dell'hub, non-mescolamento di una singola dimensione esplicita col file reale). Ogni comportamento verificato per revert, inclusa la scoperta di un vero difetto nel test di precedenza (percorso relativo non pre-risolto che non esercitava mai la risoluzione reale) corretto durante la verifica stessa; sweep `--filter Turing` 340/340 verdi (1 skip invariato, tripwire noto dal Cantiere 64); Pint: passed | 1 reale (Codex P2, verificato per revert e fixato in un secondo push): con la condizione OR originale, un chiamante che passasse UNA sola dimensione esplicita (es. solo `width`) avrebbe fatto scattare comunque la risoluzione automatica, mischiando il valore esplicito con la dimensione reale non scalata del file e producendo un aspect ratio scorretto (un file 1672×941 con `width="2400"` sarebbe diventato 2400×941 invece di 2400×1349) — nessun call site reale in produzione lo attivava (passano sempre entrambe esplicite o nessuna), ma la logica del componente condiviso era comunque scorretta per qualunque futuro chiamante; corretto cambiando la condizione da OR ad AND (risoluzione automatica solo quando entrambe le dimensioni sono assenti), con nuovo test di regressione che riproduce esattamente lo scenario | 63, 64 |
| 66 | Indice capitoli senza JavaScript | merged | [#627](https://github.com/andrea-bartiromo/quark_blog/pull/627) | `c8edefe` | Nessuna modifica al codice di produzione: proprietà "nessuna dipendenza da JavaScript" già vera, verificata su sorgente reale (`resources/views/turing/index.blade.php` — non il file inutilizzato e non referenziato `resources/views/turing.blade.php`, scoperto in questo cantiere e ora coperto da un tripwire). 4 test nuovi PHPUnit (`TuringChapterIndexNoJavaScriptTest`: card hub come `<a href>` reali, raggiungibilità di ogni capitolo da un `<a href>` reale in rete, nessun `onclick`, tripwire vista renderizzata) + 1 test browser Playwright (`turing-chapter-index-no-js.spec.js`, `javaScriptEnabled:false`, landing "In arrivo" perfettamente utilizzabile senza JS). Ogni comportamento verificato per revert; sweep `--filter Turing` 299/299 verdi; Pint: passed | 1 reale (Codex P1: il matching href nel test di raggiungibilità confrontava solo il prefisso dopo `href="`, quindi non intercettava mai gli href assoluti generati da `route()` per le CTA verso computation/intelligence — il test passava comunque per una coincidenza di markup, i blocchi editoriali dell'hub con href relativi hardcoded; fixato con match sul suffisso dell'attributo href, che copre sia href relativi che assoluti; fix verificato per revert sabotando temporaneamente i link editoriali dell'hub) | 58 |
| 67 | Report completezza Turing | merged | [#632](https://github.com/andrea-bartiromo/quark_blog/pull/632) | `e6030d4` | Ispezione diretta: gli unici documenti di "completezza" esistenti erano audit statici scritti a mano in `docs/02_Turing_Audit/` e `docs/06_Turing_Release/Checklist_Release_Candidate_v1.0.md`, datati 29 luglio 2026 — e già dimostrabilmente non aggiornati: il bug dei tre link teaser errati documentato in `Audit_Legacy_v1.0.md` risulta oggi già corretto nel codice (verificato leggendo `legacy.blade.php`, probabile effetto collaterale della riscrittura dei link interni del Cantiere 63), ma il documento continua a segnalarlo come "Aperto", e nessun test proteggeva quella correzione da una regressione (lacuna segnalata dallo stesso audit). Nuovo `App\Services\Turing\TuringCompletenessReportService` + pagina admin di sola lettura `GET /admin/turing/report-completezza` (stesso gruppo auth+editor delle altre pagine Turing, linkata da `turing-lite.blade.php`): aggrega dal vero stato attuale del database (mai un'istantanea statica) fonti registrate per capitolo (Cantiere 61), copertura della mappa concettuale già esistente riusata senza duplicarla (Cantiere 59), metriche di navigazione reali (Cantiere 68) e stato di pubblicazione (Cantiere 57/63); nessun punteggio sintetico calcolato, nessuna seconda lista di capitoli (riusa `TuringNavigationMetricsService::CHAPTERS`). Accessibilità/performance restano dichiaratamente fuori scope (richiederebbero ri-misurazioni reali con axe-core/Lighthouse). Aggiunto anche il test di copertura mancante in `TuringLegacyPageTest` per i link teaser verso computation/intelligence, già corretti nel codice ma mai verificati da nessun test. 8 test nuovi in `TuringCompletenessReportTest` + 1 in `TuringLegacyPageTest`, ogni comportamento verificato per revert; sweep `--filter Turing` 349/349 verdi (1 skip invariato, tripwire noto dal Cantiere 64); Pint: passed | 1 reale (Codex P2, verificato per revert e fixato in un secondo push): il testo introduttivo del report dichiarava genericamente "mai un'istantanea statica" senza qualificare che il campo "Livello di approfondimento" di ciascun concetto RESTA invece la stessa fotografia editoriale statica del 29 luglio 2026 già usata (e già segnalata come tale) dalla mappa concettuale — un editor avrebbe potuto fidarsi di un dato editoriale non aggiornato credendolo corrente; corretto distinguendo esplicitamente nel testo cosa è sempre dal vero stato attuale (fonti/metriche/pubblicazione) da cosa non lo è (il livello di approfondimento), con lo stesso avviso ripetuto accanto a ogni tabella per capitolo | 57-66 |
| 68 | Metriche privacy-first navigazione Turing | merged | [#623](https://github.com/andrea-bartiromo/quark_blog/pull/623) | `98e0785` | Stesso schema già stabilito da `TrustPilotPreviewMetricsService` (Cantiere 43): nuovi `TuringNavigationMetricsService`/`TuringChapterView` (tabella append-only senza FK — i capitoli Turing sono rotte hardcoded, non righe DB, confermato dal Cantiere 58), conteggi SOLO aggregati per pagina (hub + 5 capitoli), nessun identificativo di visitatore/sessione/utente mai persistito; `recordView()` collegato solo quando lo Speciale è pubblico (`config('turing.chapters_public')`, Cantieri 57/63) — oggi, gate chiuso in produzione, conteggi onestamente a zero; stato `INSUFFICIENT_DATA` sotto 7gg dal primo evento reale; pannello sola lettura in `admin.turing`. 15 test nuovi (9 in `TuringNavigationMetricsServiceTest`, 7 in `TuringNavigationMetricsTrackingTest`, uno riusato/esteso), ogni comportamento chiave verificato per revert; sweep `--filter Turing` 245/245 verdi; Pint: passed | 2 reali (Codex, entrambi verificati per revert e fixati in un secondo push): (P1) gli audit interni (SEO/WCAG, dashboard salute pubblica) raggiungono ogni pagina Turing con un vero GET in-process (`InProcessPageFetcher`), gonfiando le metriche reali con traffico sintetico — corretto riusando lo stesso marcatore `X-Kairus-Internal-Audit` già usato da `ArticleController::show()`; (P2) l'orologio di raccolta era ancorato a una costante fissa "data di deploy", non al primo evento MAI registrato — con il gate `chapters_public` (default false) potenzialmente chiuso per settimane dopo il deploy, questo avrebbe dichiarato "available" un istante dopo l'apertura del gate nonostante zero vera raccolta — corretto ancorando l'orologio a `min(created_at)` degli eventi reali, con la costante di deploy retrocessa a solo limite di sicurezza fail-closed contro un `created_at` anomalo | 63 |
| 69 | Checklist beta interna Turing | merged | [#633](https://github.com/andrea-bartiromo/quark_blog/pull/633) | `c586971` | Ispezione diretta: nessuno strumento esistente mostrava in un unico punto se lo Speciale Turing fosse pronto per essere sottoposto a revisori/tester interni tramite l'anteprima admin già esistente (Cantiere 63) — a differenza del pilot Trust, che ha già un gate di sola lettura dedicato (`TrustPilotGateReadinessService`, Cantiere 42). Stesso pattern riusato, non reinventato. Nuovo `App\Services\Turing\TuringInternalBetaReadinessService` (sola lettura, nessun metodo scrive alcunché) + pagina admin `GET /admin/turing/checklist-beta` (stesso gruppo auth+editor, linkata da `turing-lite.blade.php`). Cinque condizioni: anteprima amministrativa disponibile (Cantiere 63, MET); nessun hub/capitolo strutturalmente vuoto (Cantiere 57/62, MET); report completezza disponibile (Cantiere 67, MET); fonti registrate per almeno un capitolo reale (Cantiere 61, onestamente NOT_MET — tabella vuota per costruzione in ogni ambiente, nessun seeder la popola mai); owner editoriale che approva la revisione interna (NOT_DETERMINABLE, nessun meccanismo di assegnazione esiste, decisione umana esplicita fuori scope). Nessun GO/NO-GO deciso qui, nessuna pubblicazione, nessuna assegnazione owner. 14 test nuovi (`TuringInternalBetaReadinessServiceTest` 10, `TuringInternalBetaReadinessControllerTest` 5, uno riusato/esteso), ogni comportamento verificato per revert; sweep `--filter Turing` 363/363 verdi (1 skip invariato, tripwire noto dal Cantiere 64); Pint: passed | 2 reali (Codex P2, entrambi verificati per revert e fixati in un secondo push): (1) la condizione "nessun hub/capitolo vuoto" controllava l'esistenza della landing pubblica statica `turing.coming-soon`, che `previewHub()`/`previewChapter()` non renderizzano mai (bypassano il gate, mostrano sempre `turing.index`/`turing.$chapter` reali) — un hub/capitolo realmente rotto nell'anteprima sarebbe passato inosservato; corretto controllando l'esistenza dei template effettivamente usati da quelle due rotte; (2) la condizione "fonti registrate" contava tutte le righe di `TuringChapterSource` senza filtrare sui capitoli reali — la colonna `chapter` non ha vincoli FK/enum a livello DB, solo la validazione `in:` nel controller admin, che non protegge da un valore reso stale da una futura modifica della lista canonica; corretto filtrando sulla stessa lista canonica dei 5 capitoli reali già usata da `TuringChapterSourceController` e dal report di completezza | 62, 67, 68 |
| 70 | Piano rilascio Turing (preflight + rollback) | merged | [#634](https://github.com/andrea-bartiromo/quark_blog/pull/634) | `fe708eb` | Ispezione diretta: lo Speciale Turing è già completo in produzione dietro un singolo interruttore, `config('turing.chapters_public')`/`TURING_CHAPTERS_PUBLIC` — il "rilascio" è tecnicamente il flip di una variabile d'ambiente, non un deploy di nuovo codice — ma nessun documento esistente descriveva la sequenza preflight/rilascio/rollback in un unico punto operativo. Nuovo `docs/06_Turing_Release/Piano_Rilascio_Preflight_Rollback_v1.0.md`, solo operativo (nessun comando/script nuovo, nessun rilascio eseguito, nessuna decisione GO/NO-GO presa qui): preflight che riusa la checklist beta interna (Cantiere 69) e il report di completezza (Cantiere 67), il gap noto degli asset Enigma sottodimensionati (Cantiere 64) e dichiara onestamente che il debito di accessibilità/performance del 29 luglio 2026 non è riverificato qui; procedura di rilascio e rollback che riusano esclusivamente il processo di deploy già esistente. Nuovo `TuringReleasePlanDriftTest` (stesso principio di `RollbackRunbookDriftTest`, Cantiere 75) che verifica ogni riferimento concreto citato dal piano contro il codice reale, con tripwire su comandi Artisan orchestratori mai comparsi. 8 test nuovi, ogni asserzione verificata per revert sabotando il documento/deploy.sh stesso; sweep `--filter Turing` 371/371 verdi (1 skip invariato, tripwire noto dal Cantiere 64); Pint: passed. Con questo merge l'intero arco Turing (Cantieri 56-70) risulta completo | 2 reali (Codex P1, entrambi verificati per revert e fixati in un secondo push): (1) `config:cache` in deploy.sh gira PRIMA di diversi controlli fail-closed che possono ancora interrompere il rilascio — se il `.env` della release contiene già il flag `true`, questo può diventare visibile prima che l'intero script abbia successo, e il piano non prevedeva alcun gestore di fallimento; verificato che questo repository non specifica se la directory su cui gira deploy.sh sia già live o una candidata non ancora collegata dallo switch di symlink — corretto isolando il flip come unica variabile di un rilascio dedicato e trattando qualunque fallimento dopo config:cache come equivalente al rollback; (2) il piano dichiarava erroneamente "non serve alcun passo separato" per OPcache/PHP-FPM, contraddicendo la propria fonte citata (docs/DEPLOYMENT.md) — corretto richiedendo esplicitamente l'invalidazione OPcache/riavvio PHP-FPM in rilascio e rollback, con verifica su ogni worker | 69 |
| 71 | Backup off-host opzionale (config + test, no dati reali) | merged | [#610](https://github.com/andrea-bartiromo/quark_blog/pull/610) | `e7a569c` | 5 nuovi test (`BackupDatabaseV2OffHostTest`: nessuna copia con disco non configurato — verificato fakeando il disco di default stesso —, copia riuscita su disco `Storage::fake()`, prefix personalizzato onorato, disco mal configurato produce un warning ma il backup locale resta valido, fallimento parziale ripulisce l'oggetto già caricato); regressione mirata (filtro `Backup`, 77 test): 76/77 (1 skip pre-esistente, 0 falliti); sweep aggiuntivo Deploy+Console (514 test): 510/514 (4 skip pre-esistenti, 0 falliti); CI PR: verde su tutti i check tranne l'unico rosso `ContentClusterAutoLifecycleCompletionTest.php:231` (confermato pre-esistente); Pint: passed, `git diff --check`: pulito | 1 reale (Codex P2): se il caricamento dell'artefatto .sql riusciva ma quello del metadata .json falliva subito dopo, l'oggetto già caricato restava orfano sul disco off-host — nessuna retention off-host esiste, quindi fallimenti ripetuti avrebbero accumulato dump non verificati e spaiati; corretto con un seam dedicato (`putToOffHostDisk()`) e pulizia best-effort di tutto ciò che è stato caricato nel tentativo corrente prima di restituire il warning — con test di regressione dedicato | — |
| 72 | Retention/RPO/RTO documentati e verificabili | merged | [#613](https://github.com/andrea-bartiromo/quark_blog/pull/613) | `2a0c389` | Estende `MariaDbBackupHealthAudit`/`deploy:verify-database-backup` (Cantiere 19) con conteggio retention PER MODE derivato dal filename (stesso glob di `applyRetention()`); documenta RPO/RTO in `docs/BACKUP_V2_OPERATIONS.md` senza inventare fatti di produzione ancora UNKNOWN/TO CONFIRM; aggiunge durata restore reale nel job CI "MariaDB 11.4 real dump restore" (solo indicativo, mai un impegno di RTO produzione); 28 test (8 nuovi in `MariaDbBackupHealthAuditTest`, 3 in `DeployVerifyDatabaseBackupCommandTest`); sweep aggiuntivo (`--filter "Backup"`, 88 test): 87/88 verdi (1 skip pre-esistente); CI PR: verde su tutti i check tranne l'unico rosso `ContentClusterAutoLifecycleCompletionTest.php:231` (confermato pre-esistente); Pint: passed | 2 reali (Codex, entrambi verificati per revert): (P1) `validPairCountsByMode()` veniva chiamato sempre, anche senza `DB_BACKUP_RETENTION` configurato (il default), hashando l'intera cronologia dei backup in ogni deploy.sh senza alcun beneficio — corretto eseguendo la scansione solo quando la retention è configurata e valida; (P2) il mode veniva derivato dal campo `mode` dei metadata (mutabile/opzionale) invece che dal filename come fa realmente `applyRetention()`, rischiando di nascondere un vero superamento del limite dietro due conteggi separati entrambi sotto soglia — corretto derivando il mode dallo stesso identico glob usato da `applyRetention()` | — |
| 73 | Restore isolato con fixture/dump non produttivi | merged | [#614](https://github.com/andrea-bartiromo/quark_blog/pull/614) | `f8f9dc0` | Scoping fatto da un agente Explore dedicato: già delivered dal job CI esistente "MariaDB 11.4 real dump restore" (restore reale in database usa-e-getta `kairus_restore`, mai la sorgente, con fixture deterministiche non di produzione) — nessun codice di restore nuovo scritto deliberatamente, per non duplicare una capacità già reale e già provata dalla CI; il gap genuino (comando locale/on-demand) resta esplicitamente non costruito perché richiederebbe una guardia contro target di produzione non ancora esistente nella codebase, decisione da sottoporre esplicitamente prima di costruire. Nuovo `BackupRestoreCiEvidenceDriftTest` (4 test) con tripwire su un futuro comando artisan `backup:restore*`; sweep aggiuntivo (`--filter "Backup"`, 92 test): 91/92 verdi (1 skip pre-esistente); CI PR: verde su tutti i check tranne l'unico rosso `ContentClusterAutoLifecycleCompletionTest.php:231` (confermato pre-esistente); Pint: passed | 2 reali (Codex P2, entrambi verificati per revert): il test sui passi CI verificava solo substring generiche, non che il comando di restore reale e l'ambiente di verifica puntassero davvero a `kairus_restore` (mai la sorgente) — corretto ancorando esplicitamente comando e riga `DB_DATABASE`; il tripwire controllava i filename dei comandi invece del `$signature` Artisan pubblico realmente registrato — corretto interrogando `Artisan::all()` | — |
| 74 | Audit dei backup | merged | [#615](https://github.com/andrea-bartiromo/quark_blog/pull/615) | `e8af5b3` | 38/38 nei 2 file modificati (102 assert.) + sweep `--filter "Backup"` 104/104 (1 skip preesistente) | 3 reali (fixati: race condition audit off-host durante upload in corso, Codex P2; assert Faker non HTML-escaped in `PublicSurfaceResponsiveImageTest`, root-causato fuori scope durante il triage CI; `ContentClusterAutoLifecycleCompletionTest.php:231` NON era un flake — data hardcoded scaduta il 2026-09-10, root-causata e fissata, vedi nota sotto la tabella) | 71-73 |
| 75 | Runbook rollback (migration/media/cache/front controller) | merged | [#616](https://github.com/andrea-bartiromo/quark_blog/pull/616) | `a1d6956` | 6/6 test nuovi (`RollbackRunbookDriftTest`, 240 assert.) + sweep `--filter "Deploy"` 169/170 (1 skip preesistente) | 3 reali (Codex P1, tutti fixati): §4 affermava senza verifica che il rollback non tocca mai la Libreria Media — corretto, `public/assets/img` non è tra i percorsi verificati come persistenti da `PersistentStoragePreflight`; "Sequenza consigliata" non specificava di eseguire il rollback di schema PRIMA dello switch di directory — corretto l'ordine; l'esempio di `selective-deploy-backup.sh` in §3 usava `--app-root` sbagliato per le entry public-derived (prefisso `public/` tolto da `git-release-manifest.sh`) — corretto a `~/kairus_app/public` | 16-19, 74 |
| 76 | Audit stagionale articoli evergreen | pending | — | — | — | — | — |
| 77 | Coda manutenzione editoriale (owner/priorità) | pending | — | — | — | — | 76 |
| 78 | Rilevatore concetti sbilanciati | merged | [#617](https://github.com/andrea-bartiromo/quark_blog/pull/617) | `686a0f3` | 12 test nuovi (`ConceptQuestionBalanceAuditServiceTest` + estensioni a `ContentGraphOperationalSummaryServiceTest`) + sweep `--filter "ContentGraph"` 122/122 e `--filter "EditorialOperations"` 102/102 (nessuna regressione) | 1 reale (Codex P2, verificato per revert): il caso speciale "IQR=0 → nessun outlier possibile" nascondeva un outlier evidente quando la maggioranza dei Concept condivide un conteggio e una minoranza no (es. [1,1,1,1,100]) — corretto rimuovendo il caso speciale, il confronto diretto contro i fence gestisce entrambi i casi correttamente | — |
| 79 | Bozza Percorso "Metodo scientifico" | blocked | [#622](https://github.com/andrea-bartiromo/quark_blog/pull/622) | `bc62cdc` | Readiness audit puro (nessun `ContentCluster` creato, nemmeno in bozza): `docs/PERCORSI_METODO_SCIENTIFICO_READINESS.md`, verdetto **NEEDS CONTENT** — stesso schema già stabilito da `docs/PERCORSI_FISICA_FONDAMENTALE_READINESS.md` per un Percorso diverso. Scoping fatto da un agente Explore dedicato: "Percorso" = `ContentCluster`, già generico e riusato da 4 Percorsi reali, nessun ostacolo tecnico — l'unico ostacolo è l'assenza di contenuto editoriale reale sul tema (nessuna categoria in tassonomia, nessun candidato in `content-clusters-initial.php`, nessun articolo nel seeder). `PercorsoMetodoScientificoReadinessDriftTest` (6 test) verifica nel tempo i fatti citati; ogni test verificato per revert; sweep `--filter "Percorso\|ContentCluster"` 372/372 verdi; Pint: passed | 3 reali (Codex P2, tutti verificati per revert e fixati in un secondo push): (1) l'audit affermava "nessuno slug adiacente al tema" senza aver ispezionato gli snapshot SQLite versionati `storage/backups/database-2026-05-02-*.sqlite` (stessa evidenza già usata dall'audit Fisica Fondamentale) — contengono l'articolo pubblicato `ia-ricerca-scientifica-cnr-agenti-2025` (governance IA nella ricerca, tocca ipotesi/esperimenti/riproducibilità/verifica) — aggiunto all'inventario come candidato debole/condizionale, il verdetto NEEDS CONTENT non cambia; (2) il drift test controllava solo la sottostringa letterale "metodo-scientifico" nei membri dei cluster, non gli altri temi candidati elencati dall'audit stesso (ipotesi, esperimento, peer review, bias cognitivi, pensiero critico) — corretto con l'intera lista di parole chiave; (3) il tripwire sul DB usava solo `RefreshDatabase` (nessun seeder), dimostrando solo che le migrazioni non inseriscono un cluster — corretto eseguendo il seeder prima dell'asserzione | — |
| 80 | Gate attivazione Percorso Metodo scientifico | blocked | — | — | — | — | 79 |
| 81 | Continuità contestuale articolo-concetto-Percorso | blocked | — | — | — | — | 79, 80 |
| 82 | Suggerimenti link interni (conferma umana, anti-cicli) | merged | [#618](https://github.com/andrea-bartiromo/quark_blog/pull/618) | `b2764b5` | Scoping fatto da un agente Explore dedicato: "conferma umana" (proposta → conferma → applicazione al salvataggio) era già interamente costruita, il gap genuino era solo l'anti-cicli. `ArticleLinkCycleDetector` (BFS in sola lettura, mai bloccante) wired in entrambi i punti di serializzazione (AJAX `analyze()` e payload iniziale Blade). 10 test nuovi (6 unit + 2 controller + 2 UI), ogni test verificato per revert; sweep `--filter InternalLink` 132/132 verdi; Pint: passed | 1 reale (Codex P2, verificato per revert e fixato in un secondo push): la prima versione derivava il grafo dalle sole righe `ArticleLinkSuggestion::STATUS_ACCEPTED`, che diverge dal contenuto reale in entrambe le direzioni — un link inserito manualmente (mai passato da "Analizza") non genera mai una riga 'accepted' (ciclo perso), e una riga 'accepted' sopravvive alla rimozione manuale del link dal body (falso ciclo). Corretto derivando il grafo dai veri tag `<a href="/articolo/...">` nel body corrente di ogni articolo (`ArticleLinkInsertionService::linkedArticleSlugsInBody()`), stesso pattern già usato da `InternalLinkAuditService` nello stesso dominio — nessuna query aggiuntiva, un parsing DOM in più per articolo. Nota: durante il triage CI, root-causato un fallimento apparentemente ignoto (`PublicPageSeoAuditTest`/`PublicPageSeoAuditReportCommandTest`) come una regressione pre-esistente su `main` (commit `e8049a4`, non correlata a questo cantiere) — risolta in PR dedicata [#621](https://github.com/andrea-bartiromo/quark_blog/pull/621) (squash `2285565`), poi portata in questa PR con un merge di `main` | 78 |
| 83 | Audit accessibilità/UX articolo-concetto-Percorso | blocked | — | — | — | — | 81 |
| 84 | Radar fonti interno | merged | [#609](https://github.com/andrea-bartiromo/quark_blog/pull/609) | `773b7b5` | 17 nuovi test (`ContentSourcesRadarServiceTest` 12 + `ContentSourcesRadarAuditCommandTest` 5: nessun articolo, fonte assente/solo-testo/link/corpo-manuale/blocco-legacy, normalizzazione www., dedup domini per articolo, DOI, ordinamento, esclusione bozze, soppressione pannello pubblico, sola lettura verificata); regressione mirata (EditorialQuality+ArticlePrimarySources+ArticleManualSources+Console, 537 test): 534/537 (3 skip pre-esistenti, 0 falliti); CI PR: verde su tutti i check tranne l'unico rosso `ContentClusterAutoLifecycleCompletionTest.php:231` (confermato pre-esistente); Pint: passed, `git diff --check`: pulito | 1 reale (Codex P1): il radar contava solo `Article::primary_sources`, ignorando i due formati corpo-based già riconosciuti da `EditorialQualityChecker::sourcesCheck()` (heading manuale "Fonti", blocco legacy dopo "---") — sottostimava sistematicamente "senza fonti"; inversamente, quando esiste una heading manuale, `articolo.blade.php` sopprime pubblicamente il pannello `primary_sources`, i cui URL non devono contribuire al conteggio domini — corrette entrambe le metà riusando `ArticleManualSourcesDetector` (già pubblico) e un nuovo passthrough pubblico `EditorialQualityChecker::hasDelimitedSourcesSection()` (zero rischio per il metodo privato esistente, già coperto da 100+ test) | — |
| 85 | Bozze newsletter/social da contenuti approvati (no invio) | pending | — | — | — | — | — |
| 86 | Vista editoriale unica ciclo contenuto | pending | — | — | — | — | 77, 84, 85 |
| 87 | Transizioni di stato con audit trail | pending | — | — | — | — | 86 |
| 88 | Inbox interna issue editoriali/tecniche | pending | — | — | — | — | 86 |
| 89 | Punteggio interno spiegabile salute catalogo | pending | — | — | — | — | 30-32, 76-78 |
| 90 | Coda priorità modificabile dall'editor | pending | — | — | — | — | 86-89 |
| 91 | Contratti interni entità (articoli/concetti/Percorsi/...) | merged | [#611](https://github.com/andrea-bartiromo/quark_blog/pull/611) | `1970eb2` | Nuovo `docs/ENTITY_CONTRACTS_CONTENT_ENTITIES.md` (Article/Category/ContentCluster/Concept/ConceptQuestion) + correzione stato stantio in `docs/DOMANDE_DI_SCIENZA_FOUNDATION_DESIGN.md`; nuovo `EntityContractsDriftTest` (5 test, mirror di `TrustEditorialProtocolDriftTest`) con 2 tripwire dedicati verificati per revert; sweep aggiuntivo `--filter "Concept\|ContentGraph"` (210 test): 210/210 verdi; CI PR: verde su tutti i check tranne l'unico rosso `ContentClusterAutoLifecycleCompletionTest.php:231` (confermato pre-esistente, non riconducibile a questa PR docs-only); Pint: passed | 4 reali (Codex P2, tutti verificati contro il codice reale prima del fix): (1) `Article::STATUS_REVIEW` omesso dal contratto pur attivamente usato; (2) Content Graph descritto come "solo admin" quando `Concept` è già un consumer pubblico indiretto via `discoverableConceptsForArticle()` nel JSON-LD degli articoli; (3) sezione "Admin workflow" di `DOMANDE_DI_SCIENZA` lasciata come "UI futura" quando il workflow editoriale (lista/crea/modifica domande, preview eligibility) è già costruito da Mission 08/21; (4) drift test non rilevava l'hub esatto `/domande` (solo `/domande/{slug}`) — tutti corretti e verificati | — |
| 92 | Test integrità referenziale e cancellazione sicura | merged | [#612](https://github.com/andrea-bartiromo/quark_blog/pull/612) | `f0b843a` | Nuovo `ReferentialIntegrityDeletionTest` (14 test), nessuna migrazione — scoping fatto da un agente Explore dedicato, confermato che ogni FK coinvolta usa già `cascade`/`nullOnDelete` deliberatamente; guard esistente di `Admin\CategoryController::destroy()` verificato per revert; sweep aggiuntivo (`--filter "Category\|ContentCluster\|ArticleLinkSuggestion\|ContinuationEvent\|SlugRedirect\|SearchConsoleQuery\|Project"`, 944 test): 941/944 verdi (3 fallimenti pre-esistenti confermati indipendenti); CI PR: verde su tutti i check tranne l'unico rosso `ContentClusterAutoLifecycleCompletionTest.php:231` (confermato pre-esistente); Pint: passed | 1 reale (Codex P2): il test sulle foreign key di `article_category` usava `PRAGMA foreign_key_list` (solo SQLite), che su MariaDB avrebbe fallito con un errore SQL invece di una normale asserzione — corretto con un branch esplicito sul driver (`information_schema` per MariaDB/MySQL) E aggiungendo il file al pacchetto di regressione MariaDB della CI, così la correzione è stata realmente verificata dal job "MariaDB 11.4 production compatibility" (verde) invece di essere solo assunta corretta | — |
| 93 | Salvataggi locali browser senza account | pending | — | — | — | — | — |
| 94 | Ripresa di lettura locale accessibile/cancellabile | pending | — | — | — | — | 93 |
| 95 | Modalità studio accessibile | pending | — | — | — | — | — |
| 96 | Deduplicazione media interna (hash, no auto-delete) | pending | — | — | — | — | — |
| 97 | Licenze/crediti/varianti responsive/fallback media | pending | — | — | — | — | 96 |
| 98 | Staging/procedura equivalente preview release+migration | pending | — | — | — | — | 16-20, 71-75 |
| 99 | Feature flag interne (audit trail + rollback) | pending | — | — | — | — | — |
| 100 | Vista operativa finale + runbook + roadmap successiva | pending | — | — | — | — | tutti |

## Note per cantiere

### 37 — Report pubblicazioni programmate 30gg

Ispezione preliminare (agente di ricerca dedicato): `editorial:scheduled-certification`
(`CertifyScheduledArticles`) già certificava in sola lettura, con logica
corretta e già testata, gli articoli programmati in una finestra futura
configurabile (`--days`, 1-31, default 14) — ma solo da riga di comando,
mai da una pagina web. La dashboard "Operazioni editoriali" mostra "Da
pubblicare" senza alcun limite temporale a 30 giorni. Nessuna duplicazione:
la certificazione stessa non è stata riscritta, solo estratta.

Estratta la query/certificazione (comportamento invariato) in un nuovo
`ScheduledArticlesCertificationService`; `CertifyScheduledArticles` ora
delega qui — verificato rieseguendo `CertifyScheduledArticlesCommandTest`
senza modificarlo (3/3 passed, prova che l'estrazione non ha cambiato
nulla di osservabile). Nuova pagina `admin.scheduled-publications-report`
("Pubblicazioni programmate", voce di menu in "Analisi" accanto a
"Operazioni editoriali"): stessa identica certificazione, finestra di 30
giorni come default fisso (non configurabile da UI, per restare nel
perimetro di questo cantiere).

Nessun finding Codex (PR #584). Merge `f0e64d0`.

### 36 — Checklist certificazione primo piano editoriale

Ispezione preliminare (agente di ricerca dedicato): "primo piano" è
`Article::featured` ("in evidenza", hero homepage — `HomeController::
index()` legge `Article::published()->featured()->first()`), oggi una
semplice checkbox admin senza alcuna verifica: un articolo può essere
segnato "in evidenza" a prescindere dal suo esito `EditorialQualityChecker`
(FAIL essenziali inclusi), dal suo stato di pubblicazione, o da quanti
altri articoli sono anch'essi marcati "in evidenza" (nessun ordinamento
esplicito nella query). `EditorialQualityChecker` e la pagina
`admin.editorial-quality` non hanno mai gestito questo collegamento — un
gap reale, confermato non duplicare nulla di esistente.

Nuovo `FeaturedArticleCertificationService` (stesso pattern non
bloccante di `CategoryPublicationReadiness`, Cantiere 12): tre
segnalazioni mai bloccanti — `NOT_PUBLISHED`, `QUALITY_INCOMPLETE`/
`QUALITY_ATTENTION` (letti dall'`EditorialQualityReport` già calcolato
per l'articolo, mai ricalcolato), `MULTIPLE_FEATURED`. Mostrato
nell'editor admin solo per un articolo già "in evidenza"
(`partials/featured-certification.blade.php`) + comando di sola lettura
`articles:featured-certification-audit` (stesso pattern di
`category:publication-readiness`). Corretto anche
`ArticleDiscoveryController` (sottoclasse di `ArticleController`, bound
al suo posto in `AppServiceProvider` per **tutte** le route
`admin.articles.*`) per passare la nuova dipendenza al costruttore del
genitore — altrimenti ogni pagina admin articoli sarebbe andata 500.

Codex (PR #583, 2 finding reali, corretti): (1) la certificazione
controllava solo `status==='published'`, non lo stesso identico
predicato di `Article::scopePublished()` (`published_at<=now()`
incluso) usato davvero da `HomeController` — un articolo "pubblicato"
con data futura veniva certificato pronto pur non essendo ancora
visibile, e contava erroneamente come concorrente per
`MULTIPLE_FEATURED`; aggiunto `FeaturedArticleCertificationService::
isPubliclyVisible()`, un solo predicato condiviso. (2) il comando
`articles:featured-certification-audit` sommava query per articolo
(titolo duplicato dentro `EditorialQualityChecker`, autore lazy-loaded,
un altro `exists()` per `MULTIPLE_FEATURED`) proprio nel caso che
esiste per diagnosticare (molte righe "in evidenza"); corretto
precalcolando tutto una volta per l'intero batch, stesso identico
pattern già in uso in `EditorialQualityAuditService`. Entrambi
verificati temporaneamente ripristinando il codice precedente (i nuovi
test falliscono nel modo previsto — incluso un conteggio query 11→38
con 12 articoli in evidenza — e passano con il fix). Merge `f31a999`.

### 35 — Admin baseline mensile, denominatori separati

Ispezione preliminare (agente di ricerca dedicato): `PublicHealthDashboardService`
(Cantiere 30) è puramente istantaneo — ogni richiesta ricalcola i sei
domini da zero, senza alcuna persistenza storica oltre lo stato "presa in
carico"/"ignorato" per singolo finding (Cantiere 31). Nessun "baseline
mensile" esisteva nel codice per questa dashboard: l'unico "baseline" nel
repository è quello, non correlato, della performance lab (Cantiere 27,
docs/PERFORMANCE_LAB_BASELINE.md, uno script Node). Confermato anche che
i denominatori di ciascun dominio (checked_count/total_count) sono già
oggi calcolati separatamente per dominio e mai combinati in
PublicHealthDashboardService — "denominatori separati" nel titolo di
questo cantiere è quindi un vincolo di design da preservare nell'aggiunta
(mai un vincolo da correggere in codice preesistente).

Nuova tabella `public_health_baselines` (una riga per dominio/mese,
unique su domain+period) + `PublicHealthBaselineService`: `recordMonth()`
persiste (mai ricalcola) lo snapshot già prodotto da
PublicHealthDashboardService; `trendFor()` confronta i conteggi correnti
di UN dominio con l'ultima baseline strettamente precedente dello STESSO
dominio, mai un totale tra domini diversi (universi diversi: pagine per
seo/wcag, link per links, media per media...). Comando
`public-health:record-monthly-baseline` schedulato il giorno 1 di ogni
mese; `admin.public-health` mostra ora un confronto "vs mese scorso" per
dominio quando esiste già una baseline precedente.

Codex (PR #582, 1 finding reale, corretto): `firstOrNew()+save()` per
riga non è atomico — `withoutOverlapping()` in routes/console.php
protegge solo le run schedulate tra loro, non un'invocazione manuale
diretta del comando che si sovrapponga a una schedulata; due processi che
trovano entrambi "non esiste ancora" per lo stesso domain/period
tenterebbero entrambi un INSERT, violando il vincolo di unicità.
Sostituito con `upsert()` (stesso pattern già in uso in
ContentClusterSuggestionService::regenerate()), un'unica query atomica
lato database. Merge `bb51bc3`.

### 34 — Regressione pannello fonti auto vs manuali

Ispezione preliminare: `ArticlePublicPrimarySourcesTest` copriva già la
presentazione isolata del pannello Fonti primarie strutturate
(`Article::primary_sources`, via `<x-article.primary-sources>`) e un solo
caso di coesistenza col blocco "Fonti" legacy — ma quel caso usava solo il
delimitatore `---` nel corpo (testo libero, `<x-kairus.trust-panel>` in
`body.blade.php`), mai una heading "Fonti"/"Fonti primarie" riconosciuta
da `ArticleManualSourcesDetector`. La reale logica di soppressione in
`articolo.blade.php` (`@unless($hasManualSourcesSection) <x-article.primary-sources>`)
non aveva quindi mai un test che la eserciti davvero — un gap reale,
non un duplicato di lavoro esistente.

Nuovo `tests/Feature/ArticlePrimarySourcesPanelReconciliationTest.php`,
5 casi: nessuna heading/nessun delimitatore (pannello mostrato, caso di
controllo); heading manuale presente con `primary_sources` valorizzato o
`null` (pannello soppresso in entrambi); solo delimitatore senza heading
(entrambi i pannelli coesistono); heading **e** delimitatore insieme
(pannello Fonti primarie soppresso, pannello legacy `---` mostrato
comunque — perché `ArticleController::show()` passa l'intero
`$article->body`, non `$mainBody`, ad `hasManualSourcesSection()`).
Nessun codice applicativo toccato: solo test.

Codex (PR #581, 1 finding reale, corretto): nel caso "heading e
delimitatore insieme" la heading era messa PRIMA di `---`, quindi già
dentro `$mainBody` — il test sarebbe passato comunque anche se
`ArticleController::show()` fosse regredito a passare `$mainBody` invece
dell'intero `$article->body`, non dimostrando la reale differenza fra i
due input. Corretto spostando la heading DOPO il delimitatore (nella sola
porzione "sources"). Merge `726329b`.

CI su questa PR: 5/6 check verdi; il job "PHP 8.4" ha mostrato 2
fallimenti, nessuno introdotto da questa PR (che modifica solo un file di
test): il consueto `ContentClusterAutoLifecycleCompletionTest:231` e, per
la prima volta in questo programma, `PublicSurfaceResponsiveImageTest`
(riga 189) — verificato in codice: `User::factory()->create()` genera il
nome autore con Faker non seedato, e quando produce un nome con
apostrofo (es. "Issac O'Keefe") l'escaping Blade lo rende `&#039;` nella
risposta reale, mentre il test asserisce l'apostrofo grezzo — un flake
pre-esistente indipendente, in un file estraneo al dominio di questo
cantiere (avatar autore, non Trust Layer/fonti). Tentato un re-run dei
job falliti per confermare, negato con `403 Resource not accessible by
integration` (nessun permesso da qui); documentato con un commento sulla
PR invece di modificare quel test, fuori scope.

### 33 — Audit heading Fonti/Fonti primarie duplicati

Ispezione preliminare (agente di ricerca dedicato): `sourcesCheck()`
esistente (EditorialQualityChecker) risponde solo "c'è almeno una
sezione fonti?", mai "quante ce ne sono?" — nessun controllo esistente
rilevava un corpo con due (o più) heading "Fonti"/"Fonti primarie"/
varianti equivalenti, un errore editoriale reale (template copiato due
volte, vecchia sezione mai rimossa). Nuovo 18° controllo
`duplicate_sources_heading`: conta le heading riconosciute (unione di
`SOURCES_HEADING_LABELS` e delle etichette di
`ArticleManualSourcesDetector` — inclusa "Fonti primarie", nominata
esplicitamente nel titolo di questo cantiere) e segnala WARNING quando
ce ne sono 2+. `sourcesCheck()` resta invariato (elenco di etichette
separato, mai un'estensione silenziosa che alterasse i 104 test
esistenti). Nessuna nuova superficie: il controllo compare
automaticamente nella pagina `admin.editorial-quality` (Cantiere 32).

Codex (PR #580, 1 finding reale, corretto): `SOURCES_HEADING_TAGS`
(h2-h4, riusato inizialmente) esclude h1, ma l'editor TinyMCE
dell'admin espone "Titolo 1=h1" come formato di blocco reale nel
corpo — un h1 "Fonti"/"Fonti primarie" è quindi un caso reale, mentre
`ArticleManualSourcesDetector` riconosce già h1-h6 per la stessa
nozione. Corretto con un nuovo elenco `DUPLICATE_SOURCES_HEADING_TAGS`
(h1-h6), separato da `SOURCES_HEADING_TAGS` per lo stesso motivo.
Merge `530753a`.

### 32 — Report articoli con carenze editoriali

Ispezione preliminare (agente di ricerca dedicato): questa esatta
funzionalità esiste già, costruita in un batch di missioni precedente al
programma Kairus 100-cantieri (Missione 35, "secondo batch autonomo
KAIRUS, Fase E — Editorial Quality & Readiness") —
`App\Http\Controllers\Admin\EditorialQualityAuditController` (route
`admin.editorial-quality`, `/qualita-editoriale`, già in sidebar come
"Qualità editoriale") elenca già TUTTI gli articoli (bozza/revisione/
programmato/pubblicato, nessun filtro di stato di default) con almeno un
livello non-READY secondo `EditorialQualityChecker` — 17 controlli reali
su contenuto/media/SEO/struttura/fonti/discovery/pubblicazione (title,
slug, excerpt, body, placeholder, cover, cover alt, alt immagini nel
corpo, seo title/description, indicizzabilità, struttura heading,
**fonti presenti** — anche rilevate nel corpo articolo, non solo nel
campo dedicato —, link interni, titolo duplicato, autore, categoria,
coerenza di pubblicazione), con drill-down per singolo codice di problema
(`?problema=`) e filtro per stato (`?stato=`). Questo È, testualmente,
il "report articoli con carenze editoriali" richiesto da questo
cantiere — non una funzionalità simile ma distinta (a differenza di
`ArticleContentHealthService`, che è invece scoped a soli pubblicati/
programmati e usato per un'altra dashboard, o di `VerificationController`,
un tracker manuale dello stato di verifica fonti, non un report
calcolato di carenze). 104/104 test esistenti già verdi
(`tests/Unit/EditorialQuality/EditorialQualityCheckerTest.php`,
`tests/Feature/EditorialQualityAuditCommandTest.php`,
`tests/Feature/Admin/EditorialQualityAuditControllerTest.php`,
`tests/Feature/EditorialQualityGateUiTest.php`,
`tests/Feature/EditorialQualityAuditPerformanceTest.php`). Nessuna nuova
PR: duplicare questa pagina sarebbe stata una violazione diretta del
principio "mai ricalcolare una regola già espressa da un audit
esistente".

### 31 — Severità e presa in carico audit

Ispezione preliminare: nessuna severità (HIGH/MEDIUM/LOW) né alcun
workflow di acknowledgment/dismiss esisteva per i finding dei sei domini
di `PublicHealthDashboardService` (Cantiere 30) — l'unico precedente
reale nel repository e' `search_opportunity_statuses`/
`SearchOpportunityStatusService` (Mission 6, nuova/vista/gestita/ignorata
per le Search Console opportunities), specifico a quel dominio e privo di
un concetto di severità.

Ogni riga segnalata riceve ora una `severity` HIGH/MEDIUM (stesso
vocabolario a due livelli di `EditorialOperationsDashboardService`,
calcolata su campi già strutturati — mai una nuova regola di dominio) e
un `finding_key` stabile. Nuova tabella `audit_finding_statuses` +
modello `AuditFindingStatus` + `AuditFindingStatusService` (stesso
pattern di `SearchOpportunityStatusService`: una sola query per l'intero
snapshot) per il workflow "presa in carico"/"ignorato" —
`PublicHealthDashboardController::updateFindingStatus()`. Un finding
"ignorato" resta visibile ma esce dai conteggi "aperti"; uno "preso in
carico" resta conteggiato come aperto.

Codex (PR #579, 3 finding reali, tutti corretti): (1) la severità SEO
accedeva a `$r['url']`, chiave inesistente su una riga di
`PublicPageSeoAudit` (che espone `sample_url`) — mai scoperto nei test
perché `http_status !== 200` va sempre in cortocircuito prima, ma su una
pagina reale con canonical incoerente questo confronto viene eseguito
davvero e avrebbe fatto fallire l'intera dashboard; (2) i conteggi
aperti/ignorati/gravità alta del registro 404 venivano calcolati solo sui
50 path più frequenti mostrati in tabella, sotto-contando i finding
aperti oltre quel limite — `attachStatuses()` ora supporta un insieme di
conteggio più ampio (`_counting_rows`, fino a 5000 righe) distinto dalla
sola porzione mostrata; (3) il `finding_key` del registro 404 usava il
path grezzo invece di `path_hash` — stesso rischio di collisione
case-insensitive (MariaDB `utf8mb4_unicode_ci`) già risolto altrove per
`not_found_hits.path_hash` (Codex, PR #572), reintrodotto qui e ora
corretto. Merge `f42157d`.

### 30 — Dashboard admin Salute pubblica

Ispezione preliminare: ognuno degli audit dei Cantieri 22-29
(`PublicPageSeoAudit`, `RedirectAndCanonicalIntegrityAudit`,
`NotFoundHitTracker`, `LinkReachabilityAuditService`,
`MediaLibraryHealthAudit`, `WcagInternalAudit`) era raggiungibile solo
da riga di comando — nessuna pagina admin li riassumeva in un unico
posto, a differenza di `EditorialOperationsDashboardService` (salute
EDITORIALE dei contenuti, dominio distinto). Nuovo
`App\Services\PublicPages\PublicHealthDashboardService` + pagina
`admin/salute-pubblica`: chiama, raccoglie e riassume i sei audit
esistenti (mai una nuova regola di dominio). I Cantieri 27 (performance)
e 28 (test browser tastiera) non hanno un servizio PHP da aggregare:
sezioni "non disponibili qui" con rimando allo strumento reale.

Codex (PR #578): (1) reale e serio — ogni fetch in-process
(`InProcessPageFetcher`) riattraversa il middleware `web` (incluso
`StartSession`) su una Request sintetica senza cookie, rigenerando
l'id della sessione CONDIVISA (singleton) a ogni singola fetch (decine
per il caricamento completo). Il cookie di sessione viene scritto dopo
che il controller e' gia' tornato: senza ripristino, l'editor che apre
questa pagina riceverebbe indietro l'id dell'ULTIMA sotto-richiesta
interna anziche' il proprio — indistinguibile da un logout silenzioso.
Corretto avvolgendo l'intero calcolo di `snapshot()` con un
salva/ripristina dell'id di sessione; nuovo test di regressione con i
sei servizi REALI (non finti) che riproduce davvero la rigenerazione
attraverso il kernel HTTP (verificato che fallisce senza il fix). (2)
il suggerimento CLI della card Media indicava un comando inesistente
(`media:library-health-audit` invece di `media:health-audit`) — fixato.
Merge `ccd9bdf`.

### 29 — Audit WCAG interno

Ispezione preliminare: `docs/PUBLIC_A11Y_RESPONSIVE_HANDOFF.md` (Cantiere
I, programma precedente e distinto) e' un audit MANUALE una tantum,
limitato a 7 superfici deliberatamente scelte, mai ripetibile
automaticamente — nessun servizio PHP esistente verifica contrasto/
heading/landmark/alt/nome-accessibile in `app/`. `PublicPageInventory`
(Cantiere 21) copre invece 18 pagine, incluse 9 (redazione, chi-siamo,
contatti, privacy, cookie, termini, rettifiche, metodologia, autore)
mai controllate manualmente: gap genuino, non duplicazione.

`App\Services\PublicPages\WcagInternalAudit` (nuovo) itera l'intero
inventario via `InProcessPageFetcher` (stesso pattern di
`RedirectAndCanonicalIntegrityAudit`) e verifica staticamente (senza
browser — `tests/browser/keyboard-navigation.spec.js`, Cantiere 28,
copre gia' il comportamento REALE da tastiera): `<html lang>`,
esattamente un `<h1>`, nessun salto di livello heading, landmark
`header`/`main`/`footer`, skip-link che punta a un id realmente
esistente, `<img>` con attributo `alt` (anche vuoto per contenuto
decorativo), nome accessibile su ogni link/pulsante. Comando
`pages:wcag-audit`.

Eseguito per davvero, ha trovato 2 regressioni genuine (mai finding
Codex, scoperte dall'audit stesso), entrambe corrette nella stessa PR:
(1) pagina Contatti con salto h1→h3 (nessun h2 intermedio) — corretto
h3→h2, CSS `.premium-widget` esteso per non alterare la resa visiva;
(2) la home priva di QUALUNQUE `<h1>` quando non esiste ancora un
articolo pubblicato (`$featured` nullo — stato legittimo, es. sito
appena installato): l'unico h1 viveva dentro
`home/partials/hero-trending.blade.php`, interamente condizionato a
`@if($featured)` — corretto con un `<h1 class="sr-only">` di fallback,
visibile solo in quello stato esatto.

Codex (PR #577, 2 round): nome accessibile falso positivo — un
`aria-labelledby` verso un id inesistente/vuoto contava come nome
presente per la sola presenza dell'attributo, e un link solo-immagine
con `alt` risultava senza nome perche' `textContent` non include gli
`alt` dei discendenti (round 1, fixato con risoluzione reale
dell'id e fallback su `<img alt>`); il fix ha introdotto
`libxml_use_internal_errors(true)` senza ripristinare il valore
precedente — impostazione a livello di intero processo PHP, con
rischio di contaminare il parsing DOM di test successivi nella stessa
suite (round 2, fixato catturando e ripristinando il valore
precedente). Merge `81e4301`.

### 28 — Test browser navigazione tastiera

Ispezione preliminare: Cantiere I (`docs/PUBLIC_A11Y_RESPONSIVE_HANDOFF.md`)
aveva gia' verificato skip-link e anello di focus visibile con
un'ispezione manuale una tantum, poi corretto "14 controlli senza
anello di focus visibile dedicato" via CSS (`.kairus-focusable:focus-visible`
in `public/css/editorial-system.css`). `tests/browser/public-regression.spec.js`
copre gia' il focus-trap da tastiera di due componenti specifici (modale
newsletter, lightbox articolo) — nessuna duplicazione li' . Nessun test
automatico esisteva pero' per l'attraversamento con Tab dell'intera
pagina ne' per lo skip-link stesso: gap genuino.

`tests/browser/keyboard-navigation.spec.js` (nuovo) verifica, sulle
stesse sei superfici/fixture deterministica di `public-regression.spec.js`
(riusa l'inventario del Cantiere 21, `PublicPageInventory`): (1) lo
skip-link e' il primo elemento raggiungibile con Tab e attivandolo
(Enter) sposta il focus su `#main-content`; (2) proseguendo con Tab
(fino a 35 pressioni, campione ampio e deterministico) nessun elemento
focalizzato e visibile e' privo di un indicatore di focus visibile —
l'invariante che avrebbe intercettato il bug dei "14 controlli" gia'
corretto manualmente in Cantiere I, ora sotto regressione automatica.

3 finding Codex, tutti reali e tutti corretti (`bfa4d83`): (1) il test
di attraversamento attivava lo skip-link prima del ciclo Tab, quindi
partiva gia' da `#main-content` senza mai coprire header/ticker/
category-bar (che precedono `<main>` nel DOM) — ora la traversata
riparte dall'inizio pagina; (2) il rilevamento di loop confrontava
tag/classe/id del giro precedente, indistinguibile per card ripetute
con la stessa classe e id vuoto (griglia trending della home) — ora
ogni nodo visitato e' marcato con un attributo dedicato, confrontando
l'identita' reale del nodo; (3) l'indicatore di focus non veniva
confrontato con lo stato senza focus, quindi un box-shadow permanente
di una card avrebbe fatto risultare "visibile" un indicatore
inesistente — ora si confronta lo stile a fuoco con quello subito
dopo `blur()` sullo stesso nodo.

### 27 — Laboratorio prestazioni ripetibile (baseline performance lab)

Ispezione preliminare: `docs/PERFORMANCE_CWV_S3_AUDIT_PLAN.md` aveva
rimandato ogni audit CWV per mancanza di un browser affidabile nella
sessione di allora. `docs/PERFORMANCE_BASELINE.md` (Cantiere J, un
programma precedente e distinto da questo) è una sola misura manuale
ad-hoc, senza script committato — non uno strumento ripetibile. Questo
ambiente ha Chromium/Playwright già funzionanti
(`tests/browser/*.spec.js`): gap genuino.

`scripts/performance-lab.mjs` (`npm run performance:lab`) riusa la
stessa fixture deterministica (`BrowserTestSeeder`) e le stesse
superfici/viewport di `tests/browser/public-regression.spec.js` per
catturare Navigation/Paint Timing reali, con mediana su più run. Mai
un gate di rilascio, mai in CI.

3 finding Codex, tutti reali e tutti corretti (`6084a5c`): (1) P1,
nessun isolamento dal traffico di terze parti — la prima esecuzione
mostrava ~12.6-12.7s uniformi su ogni superficie, causati da Google
Fonts che in questo ambiente sandboxato fallisce con
`ERR_CONNECTION_RESET` (gia' diagnosticato in
`docs/CWV_BASELINE_RUNNER.md` per `scripts/cwv-baseline.mjs`, non
scoperto durante l'ispezione preliminare — gap corretto durante la
revisione), fissato bloccando ogni richiesta cross-origin prima della
navigazione; (2) P1, `page.goto()` non verificava lo stato della
risposta, registrando potenzialmente una pagina di errore come misura
valida; (3) P2, porta fissa (8199) e nessuna verifica che il processo
server appena avviato fosse ancora vivo, fissati con una porta libera
scelta a runtime e il monitoraggio dell'uscita del processo.

Dopo il fix, la stessa esecuzione scende a TTFB 33-55ms e Load sotto i
350ms, con tempi che variano sensatamente per superficie — vedi
`docs/PERFORMANCE_LAB.md` e `docs/PERFORMANCE_LAB_BASELINE.md` (numeri
corretti, non piu' quelli contaminati della primissima esecuzione).

### 26 — Audit editoriale Libreria media (mancanti/alt/peso/formati/crediti)

Ispezione preliminare: `MediaWebpAuditService` (missione WebP) verifica
già `format_breakdown`, `missing_media_files` e i candidati a
conversione WebP per i file immagine sotto `public/assets/img`.
Nessuna duplicazione: questo audit li riusa per "file mancante" e
"formato non ottimale", e aggiunge testo alternativo e credito/fonte
(nessun audit esistente le copriva) più un controllo di peso (soglia
configurabile, `config('media.audit_max_size_bytes')`, nuovo).

`App\Services\MediaLibraryHealthAudit` — stesso criterio di
`ArticleContentHealthService::coverAttribution()` per
credito/fonte (serve il credito E almeno una tra fonte/URL fonte).
Comando `php artisan media:health-audit` (`--max-size=`, `--json`).

### 25 — Audit link interni (oltre articolo) ed esterni irraggiungibili

Ispezione preliminare: `App\Services\InternalLinking\InternalLinkAuditService`
(`content:internal-link-audit`) classifica già ogni collegamento
`/articolo/{slug}` tra articoli — inclusi quelli rotti (`'missing'`),
con risoluzione dei redirect di slug più accurata di una richiesta
HTTP. Resta scoperto ogni ALTRO collegamento: verso
categoria/percorso/pagine statiche (interno, mai verificato per
raggiungibilità reale) e verso siti esterni (mai tracciati). Gap
genuino, nessuna duplicazione dell'audit `/articolo/` esistente.

`App\Services\LinkHealth\ArticleLinkExtractor` estrae ogni `<a href>`
dal corpo e lo classifica interno/esterno per host.
`App\Services\LinkHealth\LinkReachabilityAuditService`: gli interni
(diversi da `/articolo/`) sono verificati con lo stesso GET in-process
di `InProcessPageFetcher` (Cantiere 22/23, nessuna vera chiamata di
rete); gli esterni richiedono una vera richiesta HTTP in uscita
(stesso pattern già in produzione in `ProjectTaskGithubSyncService`),
**mai di default** — solo con `--check-external` esplicito, perché
lenta e non deterministica (un sito di terzi può essere giù o
bloccare l'IP del server); qualunque eccezione di rete è trattata
come "irraggiungibile", mai rilanciata. Comando
`php artisan content:link-reachability-audit` (`--limit=`,
`--check-external`, `--json`).

### 24 — Registro interno aggregato 404

Ispezione preliminare: nessun registro dei 404 reali esisteva prima di
questa PR (verificato — nessuna tabella, nessun modello, nessun hook in
`bootstrap/app.php`). Il Cantiere 23 verifica attivamente un insieme
NOTO di URL attesi; questo registro cattura invece i 404 realmente
incontrati dal traffico pubblico, aggregati per path (un record per
path con contatore, mai una riga per hit), così un editore può scoprire
link rotti che nessun audit conosceva in anticipo. Gap genuino.

Aggiunto `App\Services\PublicPages\NotFoundHitTracker::recordHit()`,
agganciato tramite `App\Http\Middleware\RecordNotFoundHits` (middleware
globale del kernel che controlla lo status della risposta FINALE, non
un hook sull'eccezione — vedi finding Codex sotto). Esclude le stesse
due categorie di traffico già escluse altrove in questa famiglia:
richieste con l'header `X-Kairus-Internal-Audit` (Cantiere 22/23 — un
audit visita deliberatamente vecchi slug che rispondono 404 come esito
corretto) e traffico redazionale autenticato
(`User::canAccessRedazione()`, come `ArticleViewTrackingService`).
Comando `php artisan pages:not-found-registry` (`--limit=`, `--json`).

**5 finding Codex, tutti verificati contro il codice reale (PR #572):**
1. Chiave unica `path` (troncata a 255) → `path_hash` (sha256 del path
   COMPLETO): evita sia la collisione case-insensitive di MariaDB
   (`utf8mb4_unicode_ci`) sia il merge di path lunghi con lo stesso
   prefisso troncato.
2. `recordHit()` avvolge ora la scrittura in try/catch (mai rilanciata,
   solo loggata): un registro osservativo non può trasformare un 404
   genuino in un 500.
3. Hook su `render()` di `HttpException` → middleware globale
   (`RecordNotFoundHits`) che controlla lo status della risposta
   finale: copre anche i 404 restituiti direttamente da un controller
   via `->setStatusCode(404)` (`CommunicationUnsubscribeController`),
   mai visti dall'hook originale.
4. (stesso fix del punto 1) path >255 byte non più troncati prima
   dell'hashing.
5. **Accettato e documentato, non corretto**: l'esclusione redazionale
   non copre un path che non corrisponde a NESSUNA rotta (il routing
   lancia il 404 prima che il gruppo `web`/`StartSession` sia mai
   eseguito, verificato empiricamente con un probe temporaneo). Un fix
   generale (`Route::fallback()` nel gruppo `web`) è stato tentato e
   SCARTATO dopo aver riprodotto concretamente una regressione peggiore:
   un fallback GET rompe la corretta individuazione dei verbi
   alternativi di Laravel, trasformando ogni 405 dell'app in un 404
   (confermato su `AnalyticsExclusionControllerTest`). Impatto
   accettato: un link mal digitato da un redattore autenticato verso un
   path del tutto inesistente può comparire nel registro — rumore
   minimo e autoreferenziale, mai una corruzione di analytics o
   contenuti reali come nel finding P1 del Cantiere 22.

### 23 — Audit 404/redirect/canonical incoerenti

Ispezione preliminare: `PublicPageSeoAudit` (Cantiere 22) verifica UN
solo esempio per ciascun tipo di pagina — sufficiente per accorgersi
che il TIPO di pagina è rotto, mai per scoprire che un singolo
articolo/categoria/percorso reale tra i tanti ha un problema. Il
meccanismo di redirect esistente (`ArticleSlugRedirect`, popolato
automaticamente da `Article::booted()`) copre SOLO gli articoli —
categoria e percorso rinominati restituiscono un 404 diretto, nessun
redirect — e non era mai stato verificato oltre la sua stessa logica
applicativa. Nessuna aggregazione/log dei 404 esiste ancora (quello è
esplicitamente il Cantiere 24, che dipende da questo). Gap genuino, non
"già coperto".

Estratto `App\Services\PublicPages\InProcessPageFetcher` da
`PublicPageSeoAudit` (refactor, nessun cambio di comportamento,
verificato dai test invariati di Cantiere 22): entrambi gli audit
hanno bisogno dello stesso GET in-process con il marcatore
`X-Kairus-Internal-Audit` (PR #570) — mai una duplicazione.

Aggiunto `App\Services\PublicPages\RedirectAndCanonicalIntegrityAudit`
+ il comando `php artisan pages:redirect-canonical-audit`
(`--limit=`, `--json`):
- `auditRedirects()`: per ogni vecchio slug articolo con un redirect
  registrato, verifica che risolva in uno dei due soli esiti previsti
  dal contratto di `ArticleController::show()` — un 301 verso
  l'articolo corrente con canonical di arrivo auto-riferito, oppure un
  404 quando l'articolo non è più pubblicato (comportamento corretto e
  documentato, MAI un finding). Qualunque altro esito è un'incoerenza
  reale.
- `auditCanonicalConsistency()`: estende il controllo di
  auto-riferimento del canonical a OGNI categoria e percorso
  raggiungibili e agli articoli più recenti (limite configurabile,
  default 50 — un sito con centinaia di articoli non deve rischiare
  un'esecuzione di minuti per un controllo senza parametri).

### 22 — Audit HTTP/canonical/robots/SEO/JSON-LD

Ispezione preliminare: ogni test canonical/SEO/JSON-LD esistente
(`ArchivePaginationCanonicalTest`, `HomeStructuredDataTest`,
`ArticleStructuredDataTest`, `CollectionPageStructuredDataTest`,
`ContentClusterStructuredDataTest`, `AuthorStructuredDataTest`,
`RobotsSitemapDiscoveryTest`) copre UN singolo tipo di pagina
hardcoded, mai una generalizzazione su tutti i tipi. `SeoController`
gestisce solo sitemap/feed. `SeoMetadataQualityAuditService` (Mission
15) è un audit content-level per-Articolo (stringhe statiche), mai
HTTP-level, mai su altri tipi di pagina. Gap genuino, ora colmabile
grazie al catalogo del Cantiere 21.

Aggiunto `App\Services\PublicPages\PublicPageSeoAudit` + il comando
`php artisan pages:seo-audit`: per ogni tipo di pagina con un
`sample_url` disponibile, un vero GET in-process (stesso meccanismo di
`MakesHttpRequests::call()`, mai una vera chiamata di rete) verifica
stato HTTP, title/description non vuoti, canonical presente e
auto-riferimento, JSON-LD solo per i tipi che hanno già un partial
dedicato (home, categoria, articolo, percorso, percorsi index, autore —
mai un'aspettativa inventata sulle pagine statiche/di utilità). Il meta
robots è riportato ma mai giudicato (noindex può essere una scelta
editoriale legittima).

Scoperta reale durante i test, documentata ma non corretta in questo
cantiere (resta una decisione SEO/editoriale, non una correzione
automatica — "il sistema prepara/verifica/propone"):
`resources/views/ricerca.blade.php` e
`resources/views/cookie.blade.php` non impostano mai
`@section('canonical', ...)`, a differenza di ogni altra pagina
statica.

Finding Codex (P1, PR #570), reale e fixato: l'audit raggiunge
`ArticleController::show()` con un vero GET in-process per verificare
l'articolo campione — senza un modo per distinguerlo da una visita
reale, ogni esecuzione dell'audit (pensato per essere di sola lettura e
ripetibile a piacere) incrementava silenziosamente il contatore
lifetime, il log per-pageview e l'aggregato giornaliero delle view
reali, oltre a poter registrare un'impression di continuazione.
Corretto con un marcatore esplicito e deterministico (header
`X-Kairus-Internal-Audit`, impostato solo dall'audit — mai
un'euristica sullo User-Agent, che il progetto esclude già
esplicitamente per design in `ArticleViewTrackingService`) controllato
da `ArticleController::show()` prima di registrare la view o
l'impression; aggiunto un test di regressione che esegue l'audit due
volte su un articolo pubblicato e verifica che il contatore resti a
zero.

### 21 — Inventario tecnico pagine pubbliche

Ispezione preliminare: nessun catalogo unico elencava tutti i tipi di
pagina pubblica di Kairus — esistevano solo due elenchi parziali e a
scopo specifico. `docs/PUBLIC_SURFACES_QA_MATRIX.md` (7 superfici,
scope volutamente ristretto a un audit di accessibilità già concluso,
esclude autore/turing/header-footer-ticker "di proposito").
`SeoController::staticSitemapPages()` (elenco statico solo per
sitemap.xml, non pensato per essere iterato, non copre
categoria/articolo/percorso). Nessuno dei due è la fonte di verità che
serve ai Cantieri 22-29 (tutti dipendono da 21): iterare sugli stessi
tipi di pagina con un URL di esempio realmente raggiungibile in questo
ambiente. Gap genuino, non "già coperto".

Aggiunto `App\Services\PublicPages\PublicPageInventory` + il comando
`php artisan pages:inventory`: 14 pagine statiche + 5 capitoli Turing
(solo quando `config('turing.chapters_public')` è abilitato — riflette
cosa è DAVVERO raggiungibile ora) + 4 tipi dinamici (categoria,
articolo, autore, percorso), risolti riusando le stesse query/scope di
visibilità pubblica già in uso altrove (`Article::scopePublished()`,
`Category::scopePubliclyVisible()`, `ContentCluster::scopePubliclyVisible()`)
— mai una condizione duplicata. `sample_url` è `null` quando nessun
record pubblico esiste ancora: stato legittimo, non un errore. Sola
lettura: non crea, modifica o pubblica mai alcun contenuto.

Scoperta in fase di test: la migration `create_categories_table` semina
7 categorie di base (`config('laboratorio.categories')`), tutte
pubbliche di default — `categoria` ha quindi sempre un esempio subito
dopo `migrate:fresh`, a differenza di articolo/autore/percorso (nessun
dato di base). Comportamento intenzionale del repository, non un bug:
testato esplicitamente invece di essere assunto.

Finding Codex (P2, PR #569): il campione categoria interrogava solo la
tabella `categories` (`Category::scopePubliclyVisible()`), ma una
categoria legacy presente SOLO in `config('laboratorio.categories')`
(nessuna riga DB, es. dopo la cancellazione di una categoria di base mai
usata) resta comunque raggiungibile su `/categoria/{slug}` —
`ArticleController::category()` non fa mai `abort(404)` quando la
categoria non esiste in DB. Corretto riusando
`Category::publicOptions()` (già l'unica fonte di verità per "quali
slug categoria sono davvero pubblici oggi", DB-visibili PIÙ il
fallback legacy solo-config) invece di una query DB-only che avrebbe
segnalato erroneamente "nessun esempio" quando una pagina categoria era
invece genuinamente raggiungibile; aggiunto un test di regressione
dedicato.

### 20 — Report read-only deploy readiness

Ispezione preliminare: dopo i Cantieri 15-19 esistono sei verifiche di
rilascio di sola lettura indipendenti (`deploy:verify-cache-paths`,
`deploy:verify-scheduled-commands`, `deploy:verify-front-controller`,
`deploy:asset-drift`, `deploy:verify-persistent-storage`,
`deploy:verify-database-backup`), ma nessuna di esse è mai stata
consultabile in un colpo solo: `deploy.sh` le esegue una alla volta, e
solo DURANTE un rilascio reale contro una release già effettivamente
checked-out. Verificare oggi la prontezza di un rilascio senza eseguire
`deploy.sh` per davvero (con i suoi effetti collaterali: refresh cache,
scrittura di REVISION/DEPLOY_INFO) richiede di lanciare a mano sei
comandi separati, ricordandosi quali sono bloccanti (`|| fail`) e quali
solo informativi (`|| true`) in `deploy.sh`. Nessun comando o servizio
esistente aggregava questo in un'unica vista. Gap genuino, non "già
coperto".

Aggiunto `php artisan deploy:readiness-report` (`--json` per
l'automazione): invoca i sei comandi `deploy:verify-*`/`deploy:asset-
drift` GIÀ esistenti (mai una loro riscrittura — ciascuno resta l'unica
fonte di verità per la propria verifica) tramite `Artisan::call()` con
output catturato in un `BufferedOutput` dedicato per comando, e
stampa una tabella riassuntiva che etichetta esplicitamente ogni
verifica come bloccante o informativa. Exit code: 0 quando tutto passa
o quando fallisce solo una verifica informativa (il report ne stampa
comunque il dettaglio e avverte che va rivista); diverso da zero solo
se fallisce una verifica bloccante — rispecchiando esattamente cosa
farebbe `deploy.sh` stesso. Deliberatamente NON wired in `deploy.sh`:
ogni verifica gira già lì con la propria semantica corretta, ripeterla
dentro il wrapper di rilascio sarebbe ridondante, non più sicuro.
Aggiornato `docs/DEPLOYMENT.md`.

### 19 — Verifica automatica backup MariaDB

Ispezione preliminare: `backup:database-v2` (Backup V2, reale dump
MariaDB/MySQL) resta manuale/opt-in — `routes/console.php` pianifica il
legacy `backup:database` (SQLite-only) SOLO quando `database.default ===
'sqlite'`, quindi in produzione MariaDB nessuno scheduler crea backup
automaticamente, esattamente come già documentato in
`docs/DEPLOYMENT.md` ("Wiring Backup V2 into the deploy pipeline itself…
remains a distinct, deliberately gated engineering decision"). Nessun
comando o servizio esistente verificava però se un backup valido
esistesse già o quanto fosse vecchio — un operatore che dimentica di
eseguirlo manualmente non aveva alcun segnale. Gap genuino, non "già
coperto"; scartata deliberatamente l'alternativa di pianificare
`backup:database-v2` automaticamente, perché sarebbe la stessa
"decisione ingegneristica distinta" che i documenti segnalano come non
implicita — fuori scope per una verifica.

Aggiunto `App\Services\Deploy\MariaDbBackupHealthAudit` + il comando
`php artisan deploy:verify-database-backup`: sola lettura, non crea/
pianifica/elimina mai alcun backup. Scansiona
`config('backup.v2.directory')` per coppie artefatto+metadata la cui
sha256/size combaciano ancora (stesso controllo di
`MariaDbBackupService::isKnownGoodPair()`) e riporta: non applicabile
(connessione non mysql/mariadb), nessun backup valido, stantio (quando
`DB_BACKUP_MAX_AGE_HOURS` è configurato — deliberatamente opt-in come
`DB_BACKUP_RETENTION`, nessuna cadenza presunta per un comando manuale),
oppure ok. **Solo informativo**: wired in `deploy.sh` senza `|| fail`,
subito dopo `deploy:verify-persistent-storage`. Aggiornati
`.env.production.example` e `docs/DEPLOYMENT.md`.

3 finding Codex (PR #567), tutti reali e fixati con test di regressione
dedicati:
- P1: il confronto usava un wildcard su qualunque backup nella directory
  invece di filtrare per l'`identityHash` della connessione CORRENTE
  (stesso calcolo di `MariaDbBackupService::create()`) — un vecchio
  backup di un database diverso avrebbe fatto riportare "ok"
  indefinitamente se produzione cambiasse nome database/host mantenendo
  la stessa directory persistente;
- P2: senza retention configurata (default) la ricerca del backup più
  recente chiamava `hash_file()` su OGNI dump storico ad ogni
  `deploy.sh`, potenzialmente leggendo un'intera storia di backup
  multi-gigabyte — corretto leggendo prima solo i metadata (economici),
  ordinando per data, e convalidando un candidato alla volta dal più
  recente finché non se ne trova uno valido;
- P2: `DB_BACKUP_MAX_AGE_HOURS` configurato ma malformato (es. "-3")
  veniva silenziosamente trattato come "non configurato", disabilitando
  il controllo di staleness invece di segnalare l'errore — corretto con
  un nuovo stato esplicito `max_age_invalid`.

### 18 — Preflight storage persistente release

Ispezione preliminare: con lo schema "directory di release separate +
switch di symlink" già in produzione, `docs/DEPLOYMENT.md` documenta già
il rischio per il registro rilasci (`DEPLOY_RELEASE_REGISTRY_PATH` deve
puntare fuori dalla directory di release, o l'evento è perduto al
deploy successivo) — ma lo stesso identico rischio si applica, senza che
nulla lo verifichi, alla directory dei backup MariaDB
(`DB_BACKUP_DIRECTORY`, `config/backup.php`): a differenza del registro
rilasci (disattivato di default, nessun rischio se non configurato), il
backup ha SEMPRE un valore di default
(`storage_path('backups/mariadb')`), che è per costruzione dentro la
directory di release corrente — un `.env` di produzione che non lo
sovrascrive esplicitamente perderebbe ogni backup al deploy successivo,
vanificando la retention a 7 copie di `docs/STORAGE_AUDIT.md`. Nessun
comando o test verificava questo. Gap genuino, non "già coperto".

Aggiunto `App\Services\Deploy\PersistentStoragePreflight` + il comando
`php artisan deploy:verify-persistent-storage`: verifica che entrambi i
percorsi (backup, registro rilasci) risolvano fuori dalla directory di
release corrente, segnalando solo quelli effettivamente configurati
dentro (mai il registro rilasci quando è semplicemente disattivato —
stato deliberato, non un rischio). **Solo informativo**: wired in
`deploy.sh` senza `|| fail` (stesso principio già in uso per
`release:record-registry`) — un `.env` di produzione già funzionante non
deve iniziare improvvisamente a bloccare i rilasci per una
configurazione mai stata un requisito fin qui. Aggiornati
`.env.production.example` (nuova variabile documentata) e
`docs/DEPLOYMENT.md`.

Finding Codex (P1, PR #566): un valore RELATIVO (es.
`DB_BACKUP_DIRECTORY=storage/backups/mariadb`) non comincia mai per
l'assoluto `$releaseRoot`, quindi il confronto per sottostringa lo
classificava erroneamente come "fuori release" — ma sia i comandi
Artisan sia la shell durante `deploy.sh` risolvono un percorso relativo
rispetto a QUESTA directory di release, non a una futura, quindi il
backup sarebbe comunque perduto al prossimo deploy. Corretto ancorando
esplicitamente il valore a `$releaseRoot` prima del confronto quando non
è già assoluto; aggiunto un test di regressione dedicato.

### 17 — Test deploy reale release senza .git (REVISION)

Ispezione preliminare: `deploy.sh` rifiuta esplicitamente qualunque
release priva di `.git` (`[ -d .git ] || [ -f .git ] || fail ...`, senza
alcun fallback su un eventuale file `REVISION` preesistente — quella
guardia è categorica), e un test reale in sottoprocesso già dimostra il
caso SIMMETRICO (un worktree Git valido dove `.git` è un file, accettato
correttamente). Nessun test reale in sottoprocesso dimostrava però il
caso opposto: una release altrimenti completa (`artisan`,
`composer.json`, `.env` tutti presenti) a cui manca del tutto `.git` —
lo scenario esatto per cui quella guardia esiste (un archivio estratto
senza i metadati Git). Un'analisi solo testuale dello script (già
presente altrove) non prova che l'eseguibile reale si fermi davvero, né
che `REVISION`/`DEPLOY_INFO` (scritti solo a rilascio completato)
restino assenti quando la guardia respinge la directory. Gap genuino,
non "già coperto".

Aggiunto `test_production_deploy_rejects_a_real_checkout_release_with_no_git_metadata_at_all`
in `tests/Feature/DeploymentSafetyTest.php`, stesso pattern del test del
worktree esistente: un vero `git init`+commit, poi `.git` rimosso del
tutto, `deploy.sh` eseguito come vero sottoprocesso contro quella
directory. Verifica il messaggio esatto (`.git not found`), l'exit code
non zero, e l'assenza di `REVISION`/`DEPLOY_INFO` dopo il fallimento.
Nessuna modifica a `deploy.sh` stesso: solo nuova copertura di test.

### 16 — Gate deploy integrità front controller

Ispezione preliminare: nessun comando o test in questo repository
verificava il contenuto di `public/.htaccess` — il runbook del Cantiere
15 lo dichiarava esplicitamente come lavoro futuro proprio di questo
cantiere. Gap genuino, non "già coperto".

Aggiunto `App\Services\Deploy\FrontControllerHtaccessAudit` (sola
lettura, verifica la presenza di ogni direttiva critica documentata nel
runbook: blocco file sensibili, front controller, canonicalizzazione
host/protocollo, header di sicurezza) e il comando
`php artisan deploy:verify-front-controller` che lo espone, seguendo
esattamente la stessa convenzione già in uso per
`deploy:verify-cache-paths`/`deploy:verify-scheduled-commands` (servizio
+ comando sottile + test dedicati). Wired in `deploy.sh` come gate
fail-closed, con lo stesso test strutturale già usato per gli altri gate
(`DeploymentSafetyTest`, verifica che giri dopo il refresh cache e prima
della finalizzazione della release).

Dichiarato esplicitamente, sia nel comando sia nel runbook aggiornato,
cosa questo gate NON copre: verifica solo il file git-tracked di questa
release, mai la copia realmente servita da `~/public_html/.htaccess` —
resta fuori dalla portata di questo repository, come già dichiarato in
`docs/DEPLOYMENT.md`.

Finding Codex (4, PR #563), tutti reali: (1, P1) il marker generico
`<FilesMatch` era soddisfatto anche dai due blocchi di cache statica più
avanti nel file, quindi rimuovere SOLO il blocco delle estensioni
sensibili non veniva rilevato — sostituito con l'espressione delle
estensioni stessa, specifica di quel blocco; (2, P1) una direttiva
disattivata con `#` lasciava comunque il testo del marker nel file
grezzo — aggiunto lo scarto dei commenti prima della ricerca; (3, P2) il
front controller era verificato solo sulla riga finale del rewrite —
aggiunta la condizione `!-f` (l'unica delle due condizioni davvero
distintiva, dato che `!-d` ricorre identica altrove nello stesso file);
(4, P2) `Referrer-Policy`, elencato dal runbook tra gli header critici,
non era tra i marker richiesti. Tutti corretti nello stesso PR con 4
nuovi test di regressione dedicati; `get_review_comments` rate-limited,
risposta via commento generale sulla PR invece che sui singoli thread.

### 15 — Runbook cPanel + front controller pubblico

Ispezione preliminare: `docs/DEPLOYMENT.md` copre già in dettaglio
l'architettura a due document root e dichiara esplicitamente fuori scope
"Web server configuration (Apache vhost, `public_html` alias,
`.htaccess`)... nor validate `.htaccess` rules" nella sua sezione "Known
limits". Nessun documento esistente copre invece quel livello stesso —
cosa deve contenere `~/public_html/index.php` (necessariamente diverso da
`public/index.php` di questo repository, dato che le due directory sono
fisicamente separate), cosa fa ogni blocco di `public/.htaccess` e perché,
e quali impostazioni vivono solo dentro il pannello cPanel (document root,
versione PHP, cron, AutoSSL). Gap genuino e dichiarato dal repository
stesso, non "già coperto".

Aggiunto `docs/CPANEL_FRONT_CONTROLLER_RUNBOOK.md`: architettura (rimando
a `docs/DEPLOYMENT.md`, nessuna duplicazione), front controller e il
rischio silenzioso di un percorso assoluto sbagliato in
`~/public_html/index.php`, spiegazione blocco-per-blocco di `.htaccess`
con verifica via `curl`, configurazione cPanel non versionata altrove,
ed esplicita dichiarazione di cosa questo runbook NON automatizza (compito
del Cantiere 16). Solo documentazione: nessuna modifica applicativa,
nessun comando eseguito contro l'hosting reale.

Finding Codex (3, PR #562), tutti reali: (1, P1) mancava la cadenza esatta
del cron per `schedule:run` — doveva essere ogni minuto
(`routes/console.php` ha eventi a cadenza di 1 e 5 minuti), non generica;
(2, P2) la verifica del rewrite usava `curl -I` (solo header): un 404
Apache e un 404 Laravel possono condividere status e `Content-Type`,
serve il corpo della risposta per distinguerli; (3, P2) l'esempio di front
controller adattato ometteva il terzo percorso relativo di
`public/index.php` (`storage/framework/maintenance.php`), lasciando la
modalità manutenzione silenziosamente inefficace se copiato così com'era.
Tutti e tre corretti nello stesso PR, thread risolti.

Nota a margine non di merito: il merge di questa PR è stato ritardato da
un'interruzione a livello di infrastruttura CI del repository (nessun
runner mai assegnato ai job, riprodotta identicamente anche sui push
diretti su `main`), causata dal limite di minuti Actions su repository
privato senza spending limit configurato — risolta dall'utente rendendo
il repository pubblico e rilanciando manualmente i job dalla UI di
GitHub (l'integrazione usata in questa sessione non ha il permesso
`actions:write` necessario per farlo autonomamente).

### 14 — Test comando audit categorie

Ispezione preliminare: la copertura del comando `category:publication-readiness`
viveva interamente dentro `tests/Feature/CategoryPublicationReadinessTest.php`,
condivisa con i test del servizio, e restava superficiale sul comando in sé
(nessuna verifica della struttura esatta dell'output `--json`, nessun caso
per una categoria PROGRAMMATA ma disattivata, nessuna asserzione sul
messaggio di stato vuoto). La convenzione già stabilita nel repository per
i comandi di audit di sola lettura è un file dedicato in
`tests/Feature/Console/*CommandTest.php` (es. `EditorialCalendarAuditCommandTest`).
Gap genuino: copertura esistente insufficiente e in un percorso non
convenzionale, non "già coperto".

Aggiunto `tests/Feature/Console/CategoryPublicationReadinessAuditCommandTest.php`:
messaggio di stato vuoto, struttura JSON campo per campo per una bozza,
categoria programmata ma disattivata (`visibility_label` = Disattivata, non
Programmata), ordinamento coerente con `Category::scopeOrdered()`, conteggio
del riepilogo. Nessuna modifica al comando stesso.

Finding Codex (P2, PR #561): il test di ordinamento usava lo stesso
`sort_order` per entrambe le fixture, verificando solo lo spareggio per
nome — una regressione a "ordina solo per nome" sarebbe rimasta verde.
Aggiunto un test dedicato con `sort_order` e nome in conflitto deliberato.

### 13 — Comando category:publication-audit

Ispezione preliminare: `category:publication-readiness` (Prompt 4) esisteva
già come comando Artisan di sola lettura, ma era limitato alle categorie
`status=scheduled` — le bozze non comparivano affatto nel report, anche se
la stessa categoria di bozza incompleta era ormai già segnalata sia
nell'editor (Prompt 3) sia nell'elenco admin (Cantiere 12). Interpretato
"category:publication-audit" come lo stesso comando esistente, la cui
copertura andava allineata a quella già raggiunta altrove: gap genuino
(comportamento deliberatamente diverso, non "già coperto").

Cambiato il comando da `where(status, scheduled)` a
`reject(isPubliclyVisible())` (stessa condizione già usata dal Cantiere
12), aggiunto un campo `status` a ogni riga del report (testo e JSON) e
adattata la resa testuale: una bozza non ha una data di programmazione da
mostrare. Nessuna modifica al comando `--json` esistente a parte l'aggiunta
del campo `status` (retrocompatibile, additivo). Aggiornati test esistenti
e `docs/CATEGORY_SCHEDULING_V1_SPEC.md`.

Finding Codex (P2, PR #560): `reject(isPubliclyVisible())` include anche
le categorie DISATTIVATE (`is_active=false`, qualunque status), non solo
bozze e programmate — `isPubliclyVisible()` torna false per is_active=false
a prescindere dallo status. La resa testuale originale distingueva solo
scheduled/bozza, quindi una categoria disattivata con status
published/scheduled veniva presentata come se stesse per aprirsi. Corretto
riusando `Category::effectiveVisibilityLabel()` (stessa etichetta già in
uso nell'elenco admin: Disattivata/Bozza/Programmata/Pubblica) invece di
dedurre lo stato dal solo campo `status`.

Nessun nuovo comando creato: rinominare o duplicare
`category:publication-readiness` avrebbe rotto la copertura di test/doc
già esistente senza alcun beneficio — è la stessa identica responsabilità,
solo con lo scope corretto.

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

Finding Codex (P2): la fixture del test "categoria pronta" si chiamava
"Bozza Pronta Lista" — `assertSee('Pronta')` passava grazie al NOME della
categoria, non al badge reale, e la fixture non era affatto pronta (nessun
Percorso collegato → `NO_RELATED_PERCORSO`). Corretto: fixture rinominata,
Percorso collegato come nel test analogo di
`CategoryPublicationReadinessTest`, asserzione indipendente sul risultato
del servizio prima di verificare la vista, asserzione finale sul markup
esatto del badge.

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
