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
| 12 | Checklist admin attivazione categoria | merged | [#559](https://github.com/andrea-bartiromo/quark_blog/pull/559) | `1068d4b` | 66/66 (212 assert.) | 1 reale (fixato: fixture di test "pronta" falso positivo — matchava il nome categoria, non il badge) | 11 |
| 13 | Comando category:publication-audit | merged | [#560](https://github.com/andrea-bartiromo/quark_blog/pull/560) | `d2dfd6e` | 5/5 (14 assert.); Category*: 245/245 (831 assert.) | 1 reale (fixato: categorie disattivate riportate come Bozza/Programmata invece di Disattivata) | 9 |
| 14 | Test comando audit categorie | merged | [#561](https://github.com/andrea-bartiromo/quark_blog/pull/561) | `678f9b3` | 6/6 (15 assert.); Category*: 251/251 (846 assert.) | 1 reale (fixato: test ordinamento verificava solo lo spareggio per nome, non sort_order) | 13 |
| 15 | Runbook cPanel + front controller pubblico | merged | [#562](https://github.com/andrea-bartiromo/quark_blog/pull/562) | `3a70b5e` | N/A (solo documentazione); Pint pulito | 3 reali (fixati: cadenza cron mancante, probe rewrite con -I inconcludente, esempio front controller senza il path di maintenance.php) | — |
| 16 | Gate deploy integrità front controller | merged | [#563](https://github.com/andrea-bartiromo/quark_blog/pull/563) | `a73aff8` | 10/10 audit (23 assert.); Deploy*: 112/112 (439 assert., 1 skip pre-esistente) | 4 reali (fixati: marker FilesMatch generico, direttive commentate non rilevate, front controller senza condizione !-f, Referrer-Policy mancante) | 15 |
| 17 | Test deploy reale release senza .git (REVISION) | merged | [#565](https://github.com/andrea-bartiromo/quark_blog/pull/565) | `16d5d0e` | 26/26 (155 assert.) | 0 | 16 |
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
