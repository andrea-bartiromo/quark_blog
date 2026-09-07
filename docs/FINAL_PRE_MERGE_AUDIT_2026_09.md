# Audit finale pre-merge — sola lettura (richiesto dall'utente dopo la chiusura Prompt 001-150)

Nessun merge, deploy, migration o nuova PR eseguiti da questo audit o da
questa sessione (con l'unica eccezione, esplicitamente autorizzata
dall'utente in un secondo momento, dell'apertura — non merge — della PR
per `fix/deploy-permissions-contract`, vedi in fondo a questo documento).
Ogni verifica sotto è stata eseguita o su `git` direttamente (nessuna
scrittura sui branch remoti) o su un checkout `--detach` locale (mai su
un branch tracciato, mai pushato). `origin/main` al momento di questo
audit: `74483e6f2f89a4151a92305b6dd16b70f6e614e3` (invariato dall'inizio
dell'intero programma 150-prompt — confermato via
`git fetch origin --prune --tags` prima di ogni sezione).

**Nota su PR #533**: `origin/main` a `74483e6` **è esattamente il commit
di merge di PR #533** ("Recupero prudente degli iscritti newsletter
pendenti"), non un branch successivo. #533 **non è mai stata "in hold"**
in questo audit — è già parte della baseline `main` su cui ogni branch
elencato qui è stato creato. Non va però considerata "già distribuita in
produzione": introduce una migration (`newsletter_reconfirmations`) e una
pulizia schedulata degli iscritti pendenti (`newsletter:reconfirmation-cleanup`,
cron giornaliero 4:30, `withoutOverlapping()`), quindi il prossimo deploy
di produzione che porti `main` oltre lo stato attuale dovrà essere
trattato come una **release con modifica di database**, con backup e
piano di rollback dedicati — non come un deploy ordinario solo-codice.
Dettagli tecnici in §5.

## 1. Elenco completo dei branch prodotti da questa sessione

Tutti creati da `origin/main` (`merge-base` verificato == `74483e6`
per ciascuno, non un branch divergente da uno stato precedente).

| Branch | SHA | Commit | File modificati | Push |
|---|---|---|---|---|
| `fix/deploy-permissions-contract` | `407ef14` | 4 (`c526212`→`de74dc8`→`fb53854`→`407ef14`) | 15 file, +1194/−8 | ✅ `origin/fix/deploy-permissions-contract` |
| `docs/production-audit-runbook-v2` | `cefc3dc` | 1 | 3 file, +243/−0 | ✅ |
| `docs/measurement-closeout` | `34329f1` | 1 | 6 file, +206/−1 | ✅ |
| `docs/pr507-pr510-search-pagination-audit` | `6d8e11b` | 1 | 1 file, +110/−0 | ✅ |
| `docs/trust-layer-fonti-author-audit` | `c97208e` | 1 | 3 file, +108/−0 | ✅ |
| `chore/ci-hardening-incident-runbook` | `05dbe47` | 1 | 5 file, +132/−0 | ✅ |
| `feat/newsletter-cleanup-dry-run-audit` | `89a9cbd` | 1 | 5 file, +244/−7 | ✅ |
| `docs/pr511-social-522-review` | `b510767` | 1 | 2 file, +142/−0 | ✅ |
| `feat/newsletter-send-kill-switch` | `218d695` | 1 | 7 file, +234/−1 | ✅ |
| `docs/trust-layer-pilots-gate-reassessment` | `f953116` | 1 | 4 file, +115/−25 | ✅ |
| `docs/kairus-next-12-months-readiness` | `bd8b530` | 1 | 1 file, +121/−0 | ✅ |

11 branch, 11 push confermati (`git rev-parse origin/<branch>` risolve
esattamente agli SHA sopra per ciascuno — verificato ora, non solo al
momento del push originale). Nessuno mergeato in `main`.

## 2. Branch con il fix del contratto deploy/permessi/rollback (priorità P0)

**`fix/deploy-permissions-contract`** (SHA `407ef14`, base `origin/main`
diretta). Contiene, nell'ordine dei suoi 4 commit:

- **`c526212`** — `App\Services\Deploy\PublicAssetDriftDetector`: nuovo
  `STATUS_UNSAFE_MODE` (file <644 o directory <755 su una delle due
  document root, anche a contenuto identico); **`scripts/selective-deploy-backup.sh`**:
  il meccanismo concreto del difetto — `cp -a` nel rollback preservava
  qualunque permesso avesse la copia di backup — ora normalizza
  esplicitamente a 644/755 i soli file `public`-scoped (i file
  `app`-scoped restano su `cp -a`, un permesso restrittivo lì può essere
  intenzionale, comportamento verificato da un test dedicato).
- **`de74dc8`** — guardia su release incompleta (`artisan`/`composer.json`/`.git`
  assenti), nuovo `scripts/git-release-manifest.sh` (manifest Git
  deterministico A/M/D, `--no-renames` esplicito), nuovo
  `STATUS_EMPTY_FILE` (asset a 0 byte su entrambe le radici).
- **`fb53854`** — `docs/release-checklist.json` machine-readable, ancorato
  a `deploy.sh` da un test che verifica ogni marcatore nell'ordine reale.
- **`407ef14`** — `scripts/staging-rollback-drill.sh`: drill reale (non
  mock) contro il vero gate `deploy:asset-drift`, guasto iniettato
  (permesso 600 su un file di release) e rimediato, eseguito dal vivo con
  evidenza in `docs/DEPLOYMENT.md`.

**Verifica fresca eseguita ora per questo audit** (checkout `--detach`,
mai su un branch tracciato): suite completa **4094 passed, 11 skipped, 0
failed**. Nessun conflitto con nessun altro branch o PR (vedi §3) — è
l'unico branch tra gli 11 a non condividere alcun file con nient'altro
in volo.

## 3. Confronto di ogni branch/PR con `origin/main` attuale e conflitti

Metodo: `git merge-tree` locale (nessuna scrittura remota) su ogni
coppia di branch/PR con almeno un file in comune — l'overlap di file è
stato calcolato per tutte le **136 coppie possibili** tra gli 11 branch
di questa sessione e le 6 PR aperte dall'utente (#507, #510, #511, #512,
#515, #522); solo le coppie con file in comune sono state poi verificate
con `merge-tree` per un conflitto testuale reale.

**Ogni branch/PR preso singolarmente contro `origin/main`**: 0 conflitti
per tutti e 17 (banale, dato che tutti derivano direttamente da
`origin/main` invariato).

**Overlap di file trovati (3 su 136 coppie)**:

| Coppia | File in comune | Conflitto testuale reale? |
|---|---|---|
| PR #507 ↔ `docs/measurement-closeout` | `resources/views/categoria.blade.php` | **Sì** — entrambi inseriscono `rel=prev/next` nello stesso punto (`@section('head')`) con implementazioni diverse; `git merge-tree` produce marcatori `<<<<<<<`/`=======`/`>>>>>>>` espliciti. Risoluzione banale (tenere l'implementazione di #507, più completa: include anche il fix 404 e il tie-breaker `id DESC`) ma **non automatica**. |
| PR #512 ↔ `feat/newsletter-send-kill-switch` | `tests/Feature/Console/SendWeeklyNewsletterTest.php` | No — inseriscono metodi di test in punti diversi della classe; merge 3-way pulito, 0 marcatori. |
| `feat/newsletter-send-kill-switch` ↔ `docs/production-audit-runbook-v2` | `.env.production.example` | No — aggiungono blocchi di commento a variabili diverse in punti diversi del file; merge 3-way pulito, 0 marcatori. |

**Nessun'altra coppia** (tra le 136) condivide alcun file. Il branch P0
(`fix/deploy-permissions-contract`) non compare in nessuna riga sopra:
zero sovrapposizioni con qualunque altro branch o PR.

## 4. Nessun artefatto generato/cache/.env/vendor versionato

Verificato su **tutti e 17** i branch/PR (ogni file toccato, non solo
quelli aggiunti — copre anche modifiche a file esistenti), con pattern
esplicito su `bootstrap/cache/*`, `.env`/`.env.*` (esclusi i template
`.env.*.example`, intenzionalmente versionati per convenzione già
esistente in `main`), `vendor/*`, `node_modules/*`, `*.log`,
`storage/framework/{cache,sessions,views}/*`, `*.sqlite`: **nessuna
corrispondenza in nessun branch**. Il solo file `.env*` toccato da
qualunque branch è `.env.production.example` (un template, mai il file
reale) — confermato con un pattern che esclude esplicitamente i
template e verifica anche la corrispondenza esatta `.env` isolata.

Working tree verificato pulito (`git status --short`) dopo ogni
checkout `--detach` di verifica in questo audit.

## 5. Migration — #522 (PR aperta) e #533 (già in `main`)

Confermato su tutte e 6 le PR elencate in questo audit: **solo #522**
(`feat/social-workspace-admin-v1`) contiene una migration
(`database/migrations/2026_09_02_120000_create_social_drafts_table.php`,
tabella `social_drafts`, nuova e separata da `social_publications` —
nessuna migration tocca tabelle esistenti). #507, #510, #511, #512, #515:
zero migration.

Questo conteggio riguarda solo le PR **non ancora mergeate**. È distinto
dalla baseline `main`, che a sua volta contiene già una migration propria,
introdotta da **PR #533** (mergeata, non oggetto di questo audit come
"branch da valutare" ma rilevante per la pianificazione del prossimo
deploy):

- **Migration**: `database/migrations/2026_09_06_090000_create_newsletter_reconfirmations_table.php`
  → tabella `newsletter_reconfirmations` (nuova, non tocca tabelle
  esistenti né il sistema di double opt-in preesistente su `Newsletter`).
- **Componenti applicativi**: modello `NewsletterReconfirmation`,
  `NewsletterReconfirmationService` (`send()`, `confirm()`,
  `deleteExpiredPending()`), `NewsletterReconfirmationMail`, route
  pubblica `newsletter.reconfirm`, azioni admin `sendReconfirmation()` /
  `cleanupExpiredPending()`.
- **Pulizia schedulata**: comando `newsletter:reconfirmation-cleanup`
  (`routes/console.php`), cron giornaliero **4:30 UTC**, `withoutOverlapping()`,
  **senza flag di ambiente/feature a livello di schedule** — verificato
  in questo audit (nessun `env()`/`config()` di gate attorno alla riga
  `Schedule::command('newsletter:reconfirmation-cleanup')`).

**Verificato in questo audit** (checkout `--detach` di
`origin/fix/deploy-permissions-contract`, il cui `merge-base` con
`origin/main` è risultato esattamente uguale a `origin/main`, quindi
include #533 per intero): migration, modello, servizio, mail e comando
schedulato sono tutti presenti e riconoscibili nel checkout.

**Implicazione per il prossimo deploy di produzione**: qualunque deploy
che porti la produzione da uno stato precedente a #533 fino a (o oltre)
`74483e6` deve essere pianificato come **release con modifica di
database**: backup pre-deploy della tabella coinvolta, verifica esplicita
dello stato delle migration (`php artisan migrate:status`) prima di
qualunque `migrate --force`, e un piano di rollback che copra sia il
codice sia lo schema. Questo audit **non verifica** se tale deploy sia
già avvenuto in produzione — nessun accesso a produzione è stato
effettuato o richiesto qui — e quindi non assume né conferma che #533 sia
già distribuita in produzione.

## 6. Verifica CI + esecuzione locale indipendente (non solo fiducia nella CI)

| PR | CI GitHub | `merge-tree` vs `main` | Test rieseguiti localmente ora |
|---|---|---|---|
| #507 | 9/9 verdi | 0 conflitti (isolato) | 12/12 verdi |
| #510 | 8/8 verdi | 0 conflitti | 2/2 verdi |
| #511 | 9/9 verdi | 0 conflitti | 3/3 verdi |
| #512 | 7/7 verdi | 0 conflitti (isolato) | 7/7 verdi |
| #515 | 9/9 verdi | 0 conflitti | 15/15 verdi |
| #522 | 7/7 verdi | 0 conflitti | 127/127 verdi |

Tutte le esecuzioni locali sono avvenute in un checkout `--detach`
separato (mai un branch tracciato, mai push).

## 7. Proposta di ordine PR/merge — nessun merge eseguito

Ordine da valutare, allineato a quello indicato: **deploy hardening →
#512 → #515 → #511 → #510 → #507**, con #522 in hold. Motivazione
tecnica per ciascun passaggio:

1. **Deploy hardening** (`fix/deploy-permissions-contract`) — **prima di
   tutto**: priorità P0 dichiarata, zero conflitti con qualunque altro
   elemento in questa lista, quindi non può mai bloccare né essere
   bloccato da nessuno degli altri passaggi. Nessuna migration, nessun
   rischio di sequenziamento.
2. **#512** (privacy log newsletter) — nessun conflitto con nulla sopra;
   condivide un file (non un conflitto, vedi §3) con #6 sotto — mergiare
   #512 per primo tra i due lo rende un semplice rebase/merge pulito per
   #6 poi.
3. **#515** (hygiene articoli, read-only + editor opt-in) — nessun
   conflitto con nulla sopra o sotto.
4. **#511** (certificazione settimanale) — nessun conflitto con nulla.
5. **#510** (benchmark Trova) — nessun conflitto con nulla; autosufficiente.
6. **#507** (paginazione categoria) — **per ultimo tra questi**: è il solo
   con un conflitto reale noto, contro `docs/measurement-closeout` (non in
   questa lista di PR ma già pushato in questa sessione). Mergiare #507
   qui non blocca nessuno dei passaggi precedenti; il conflitto rimane da
   risolvere separatamente quando/se `docs/measurement-closeout` verrà
   proposto, scartando in quel momento la porzione equivalente già
   coperta da #507.

**#522 (Social Workspace) — HOLD, come indicato**: contiene l'unica
migration tra le PR aperte; corretto tenerlo separato dal resto e
subordinarlo a un rilascio dedicato solo dopo che il deploy corretto
(punto 1) è passato da staging e da una prova di rollback reale — non
per un problema trovato nella PR stessa (verificata internal-only, 0
conflitti, 127/127 test), ma per la disciplina di rilascio dichiarata:
mai una migration nello stesso rilascio del primo deploy realmente
corretto.

Gli altri 9 branch di questa sessione (audit, revisioni, readiness,
riesame pilot, questo stesso audit) sono documentazione/hardening
diagnostico, non hanno un ordine di merge critico tra loro — nessuno
introduce una migration o un conflitto reale con alcun altro elemento
verificato.

## 8. Pilot Trust Layer — NO-GO confermato, nessuna azione

Nessuna route pubblica aperta né contenuto creato per "Cosa sappiamo
davvero" o "Atlante visuale" in questo audit o nella sessione. Entrambi
restano **NO-GO**: owner editoriale e contenuto sorgente approvato
mancanti per entrambi (vedi `docs/TRUST_LAYER_PILOTS_GATE_REASSESSMENT_2026_09.md`)
— condizioni editoriali, non tecniche, invariate da questo audit.

## Riepilogo GO / HOLD / NO-GO

| Elemento | Stato | Note |
|---|---|---|
| `fix/deploy-permissions-contract` (deploy hardening, P0) | **GO** | 0 conflitti, 4094/4094 test verdi verificati ora, nessuna migration |
| PR #533 (recupero iscritti newsletter, riconferma) | **Già in `main`** — non "in hold", non un branch di questo audit | Introduce migration `newsletter_reconfirmations` + pulizia schedulata (`newsletter:reconfirmation-cleanup`, 4:30 UTC); il prossimo deploy production che la porti in produzione va trattato come release con modifica DB, backup e rollback dedicati (vedi §5) |
| PR #512 (privacy log newsletter) | **GO** | 7/7 CI, 7/7 locale, 0 conflitti |
| PR #515 (hygiene articoli) | **GO** | 9/9 CI, 15/15 locale, 0 conflitti, read-only + opt-in editoriale |
| PR #511 (certificazione settimanale) | **GO** | 9/9 CI, 3/3 locale, 0 conflitti, read-only verificato |
| PR #510 (benchmark Trova) | **GO** | 8/8 CI, 2/2 locale, 0 conflitti, autosufficiente |
| PR #507 (paginazione categoria) | **GO con nota** | 9/9 CI, 12/12 locale; conflitto reale ma banale con `docs/measurement-closeout` da risolvere al momento del merge di quest'ultimo |
| PR #522 (Social Workspace) | **HOLD** | Contiene l'unica migration; per disciplina di rilascio, dopo staging + rollback drill del deploy corretto |
| Pilot "Cosa sappiamo davvero" | **NO-GO** | Owner + contenuto editoriale mancanti |
| Pilot "Atlante visuale" | **NO-GO** | Owner + articolo sorgente + metrica mancanti |
| Altri 9 branch di audit/readiness/riesame | **Nessuna azione richiesta** | Documentazione/hardening diagnostico, nessun conflitto, nessuna migration |

## Primo candidato concreto per una PR

**`fix/deploy-permissions-contract`** (SHA `407ef14`). Riconfermato una
terza volta, immediatamente prima dell'apertura della PR, su un nuovo
checkout `--detach` dedicato:

- `merge-base(origin/main, origin/fix/deploy-permissions-contract)` ==
  `origin/main` (`74483e6`) **esattamente** — il branch è avanti di soli
  4 commit rispetto a `main`, senza alcuna divergenza: un fast-forward
  puro, per cui un conflitto è escluso per costruzione, non solo per
  verifica empirica.
- `origin/main` a `74483e6` è il commit di merge di #533 stesso, quindi
  il checkout usato per la riverifica include #533 (migration, modello,
  servizio, mail, comando schedulato) per intero — confermato con
  `grep`/`test -f` mirati sui suoi artefatti, non per deduzione.
- `git status --porcelain --ignored`: solo voci `!!` (cache/sessioni
  ignorate da `.gitignore`), zero file generati tracciati o non tracciati
  fuori da `.gitignore`.
- Suite completa rieseguita da zero in questo stesso passaggio:
  **4094 passed, 11 skipped, 0 failed** — identico al numero già citato
  in §2, ora riconfermato in un secondo checkout indipendente.

Su autorizzazione esplicita dell'utente ("Procedi ad aprire una PR
dedicata verso `main` per il solo deploy hardening... Non fare merge,
deploy, migration o modifiche a produzione"), è stata aperta **una PR
verso `main` per questo solo branch**. Nessun merge, deploy, migration o
modifica a produzione è stato eseguito: la PR resta in attesa di review e
di una successiva autorizzazione esplicita al merge.
