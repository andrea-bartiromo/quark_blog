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
| 29 | Audit WCAG interno | open | [#577](https://github.com/andrea-bartiromo/quark_blog/pull/577) | — | 14/14 (servizio) + 6/6 (comando, incl. 2 regressioni reali) | — | 21 |
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
