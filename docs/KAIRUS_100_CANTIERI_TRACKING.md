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
| 2 | Chip categorie → componente Blade accessibile | in_progress | — | — | — | — | 1 |
| 3 | Newsletter categorie → CTA contestuale | pending | — | — | — | — | 1 |
| 4 | Più letti → 3 articoli, esclusi duplicati pagina | pending | — | — | — | — | 1 |
| 5 | Blocco unitario "Continua a esplorare" | pending | — | — | — | — | 1, 4 |
| 6 | Test feature/browser composizione categorie | pending | — | — | — | — | 1-5 |
| 7 | Query budget categorie anti-N+1 | pending | — | — | — | — | 1-5 |
| 8 | Audit canonical/SEO/OG/paginazione categorie | pending | — | — | — | — | 1 |
| 9 | Categorie non pubbliche isolate ovunque | pending | — | — | — | — | — |
| 10 | Test integrazione visibilità temporale categorie | pending | — | — | — | — | 9 |
| 11 | Preview admin categorie bozza/programmate | pending | — | — | — | — | 9 |
| 12 | Checklist admin attivazione categoria | pending | — | — | — | — | 11 |
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
