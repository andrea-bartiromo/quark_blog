# Runbook: rollback di un rilascio (migration / media / cache / front controller)

Cantiere 75 del programma "Kairus 100 cantieri" (vedi
`docs/KAIRUS_100_CANTIERI_TRACKING.md`, dipendenze 16-19 e 74). Questo
documento è **solo operativo**: non introduce alcun comando o script nuovo.
Ogni meccanismo citato qui esiste già ed è già stato costruito, testato e
documentato separatamente da un cantiere precedente — questo runbook si
limita a metterli in sequenza per la domanda "il rilascio appena fatto ha
rotto qualcosa, come torno indietro?".

Come per Cantiere 73 (`docs/BACKUP_V2_OPERATIONS.md`, "CI restore evidence
contract"): un comando che orchestrasse automaticamente questi passi (es.
un ipotetico `artisan rollback:release`) rientrerebbe nella categoria di
nuova capacità potenzialmente distruttiva che questo programma tratta come
decisione da sottoporre esplicitamente prima di essere costruita — non
qualcosa da aggiungere opportunisticamente dentro un cantiere di
documentazione. Ogni passo sotto resta un'azione manuale, eseguita da un
operatore autorizzato.

## Decisione iniziale: rollback dell'intero rilascio o solo di una parte?

- **Rollback completo** (torna alla release precedente per intero) è la
  via più semplice quando la directory della release precedente esiste
  ancora sul server: vedi "1. Rollback dell'intero rilascio" sotto.
- **Rollback parziale** (solo i file statici pubblici, es. un CSS/JS
  rotto, senza toccare il resto) usa `scripts/selective-deploy-backup.sh`:
  vedi "3. Rollback dei file statici pubblici" sotto.
- Le migration e la Libreria Media (sezioni 2 e 4) non seguono né l'uno né
  l'altro rollback automaticamente: vanno sempre valutate a parte, anche
  durante un rollback completo.

## 1. Rollback dell'intero rilascio (switch della directory di release)

Produzione usa già lo schema "due directory più switch di symlink"
(`docs/DEPLOYMENT.md`, "Release registry": *"the two-directory-plus-
symlink-switch schema already in production use"*). **Nessun file di
questo repository implementa lo switch stesso** — verificato: nessuno
script in `scripts/` tocca un symlink o una directory "current"/"release".
È un'operazione esterna, eseguita a mano (o da tooling di hosting fuori da
questo repository) dall'operatore che ha effettuato il deploy originale.

Cosa questo repository offre per supportare quella decisione:

- `REVISION` (scritto da `deploy.sh` solo a fine deploy riuscito, dentro la
  directory della release) identifica la release corrente con l'esatto SHA
  Git — confrontarlo con lo storico per sapere a quale directory precedente
  tornare.
- `php artisan release:registry` (`docs/DEPLOYMENT.md`, "Release
  registry") — se `DEPLOY_RELEASE_REGISTRY_PATH` è configurato — mostra lo
  storico delle revision effettivamente deployate, con i relativi
  timestamp UTC. **Opt-in, disabilitato di default**: senza quel path
  configurato fuori dalla directory di release, non esiste alcuno storico
  interrogabile da questo repository.

Dopo lo switch (qualunque meccanismo esterno lo esegua):

1. Rieseguire il refresh cache nella directory ora attiva — stessa
   sequenza di `deploy.sh` (`docs/DEPLOYMENT.md`, "Safety contract"):

   ```bash
   php artisan optimize:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

2. Riverificare il front controller e (se configurato) il drift degli
   asset pubblici sulla directory ora attiva:

   ```bash
   php artisan deploy:verify-front-controller
   php artisan deploy:asset-drift
   ```

3. Se PHP-FPM ha OPcache abilitato con `validate_timestamps=0` o un
   `revalidate_freq` alto, il bytecode della release precedente può
   restare in cache finché PHP-FPM non viene ricaricato — caveat già
   dichiarato in `docs/DEPLOYMENT.md`, "Known limits" ("PHP-FPM / OPcache
   are not restarted or invalidated by anything here"). Nessun comando di
   questo repository verifica o esegue quel reload: è un passo
   dell'operatore, specifico dell'hosting.

**Attenzione all'ordine se sono coinvolte migration** (finding Codex P1,
PR #616): il rollback di schema (§2) va eseguito **prima** di questo
switch, non dopo — la directory precedente a cui si torna tipicamente non
contiene ancora il file della migration introdotta dal rilascio che si
sta annullando, quindi `migrate:rollback` non potrebbe più trovarla per
eseguirne il `down()` una volta effettuato lo switch. Vedi §2 per la
procedura completa.

**Attenzione alla Libreria Media**: la directory `public/assets/img` non
è tra i percorsi verificati come persistenti tra le release (§4) — un
upload avvenuto durante il rilascio che si sta annullando può non essere
presente nella directory di release a cui si torna. Verificare §4 prima
di considerare concluso un rollback completo.

**UNKNOWN / TO CONFIRM** (nessun file di questo repository lo stabilisce):
quante directory di release precedenti restano effettivamente sul server
prima di essere ripulite, e chi/cosa esegue materialmente lo switch di
symlink. Senza queste due informazioni, questa sezione descrive solo la
sequenza *dopo* lo switch, non se lo switch stesso sia oggi possibile per
una release specifica.

## 2. Rollback del database / delle migration

**Nessun comando di restore automatico esiste in questo repository.**
Cantiere 73 (`docs/BACKUP_V2_OPERATIONS.md`, "CI restore evidence
contract") ha deliberatamente deciso di non costruirne uno: richiederebbe
una guardia esplicita contro un target di produzione, non ancora presente
in nessun punto della codebase, e questo programma tratta quella
decisione come da sottoporre esplicitamente prima di scrivere codice, non
da aggiungere opportunisticamente qui.

Quello che esiste ed è già lo strumento corretto per la maggior parte dei
casi:

1. **Se la migration ha un `down()` corretto e non sono già stati persi
   dati non recuperabili** (caso comune per una migration additiva —
   nuova tabella, nuova colonna nullable):

   ```bash
   php artisan migrate:status        # identifica la/le migration da annullare
   php artisan migrate:rollback --step=N   # N = numero di migration da annullare, dalla più recente
   ```

   Esempio reale di questa via, già documentato per una funzionalità
   specifica: `docs/NEWSLETTER_PENDING_RECOVERY.md`, sezione "Rollback
   dello schema" — `migrate:rollback` sulla singola migration in
   questione, con verifica esplicita che nessun'altra tabella/colonna
   preesistente venga toccata.

2. **Prima di eseguire qualunque migration in avanti che possa richiedere
   questo passo**, un dump Backup V2 deve già esistere — non è opzionale
   in retrospettiva: `docs/DEPLOYMENT.md`, "Database and backup ordering",
   stabilisce che un dump va preso *prima* della migration, non dopo che
   qualcosa è andato storto:

   ```bash
   php artisan backup:database-v2 --mode=pre-migration --release-sha=<sha-della-release>
   ```

3. **Se il `down()` è assente, sbagliato, o sono già stati persi dati che
   `down()` non può ricostruire** (es. una migration distruttiva già
   eseguita, o dati scritti dall'applicazione dopo la migration che
   `down()` non conosce): l'unica via resta il dump Backup V2 preso al
   passo 2, ripristinato tramite la procedura esterna approvata —
   **nessun comando `artisan` di questo repository esegue quel
   ripristino** (punto 1 sopra). Questo è il limite più severo dell'intero
   runbook: se il passo 2 non è stato eseguito prima della migration
   incriminata, non esiste alcun percorso verificabile da questo
   repository per tornare allo stato precedente.

**UNKNOWN / TO CONFIRM**: la procedura di ripristino esterna approvata
citata al punto 3 (`docs/BACKUP_V2_OPERATIONS.md` la elenca come
`RESTORE_RUNBOOK: UNKNOWN / TO CONFIRM` nella tabella dei fatti di
produzione) e la data dell'ultima prova di ripristino reale
(`RESTORE_TEST_DATE`, stessa tabella).

## 3. Rollback dei file statici pubblici (incluso il front controller)

Questa è l'unica parte di questo runbook con un meccanismo realmente
automatizzato e testato in questo repository: `scripts/selective-deploy-
backup.sh`, in coppia con `scripts/git-release-manifest.sh` per generare
il manifest da un intervallo di commit reale. **Copre solo i file sotto
`public/`** — un rilascio che cambia anche file applicativi fuori da
`public/` (es. `app/`) non è nello scope di questa sezione, che riguarda
solo il rollback dei file statici pubblici; per quello serve il rollback
completo (§1).

`scripts/git-release-manifest.sh` genera, per ogni percorso sotto
`public/`, **due** entry con lo stesso percorso relativo (prefisso
`public/` tolto da entrambe): una `app`-scoped e una `public`-scoped
(`docs/DEPLOYMENT.md`, "Deterministic release manifests"). Perché
`--app-root` risolva quella entry `app`-scoped nel punto corretto (finding
Codex, PR #616), va quindi puntato alla directory `public/`
dell'applicazione, **non** alla radice del repository — un file Git
`public/css/site.css` diventa l'entry `css/site.css`, che deve risolvere
in `~/kairus_app/public/css/site.css`, non in `~/kairus_app/css/site.css`.
Un manifest che includesse anche percorsi fuori da `public/` (possibile se
`--from`/`--to` copre un intervallo con altri cambi applicativi) avrebbe
entry `app`-scoped con il percorso relativo COMPLETO invece — incompatibili
con questa stessa radice; generare qui il manifest solo per l'intervallo
di commit che tocca `public/`, o filtrare manualmente il TSV alle sole
righe rilevanti, prima di usarlo con questo comando.

**Prerequisito**: un backup deve essere stato preso *prima* del rilascio
che si vuole annullare:

```bash
scripts/git-release-manifest.sh --from <sha-precedente> --to <sha-nuovo> --repo <checkout> > manifest.tsv
scripts/selective-deploy-backup.sh backup \
  --manifest manifest.tsv \
  --app-root ~/kairus_app/public --public-root ~/public_html \
  --backup-root <directory-di-backup> \
  --previous-sha <sha-precedente> --target-sha <sha-nuovo>
```

Per annullare, dato quel backup:

```bash
scripts/selective-deploy-backup.sh rollback \
  --backup-dir <directory-di-backup-dal-passo-precedente> \
  --app-root ~/kairus_app/public --public-root ~/public_html
```

Cosa copre: ogni file `app`-scoped (dentro `~/kairus_app/public`) e
`public`-scoped (dentro `~/public_html`) presente nel manifest generato
dal diff Git tra le due revision — inclusi CSS/JS e `public/.htaccess` se
modificati dal rilascio. Le entry `public`-scoped vengono ripristinate con
permessi normalizzati (`644`/`755`) indipendentemente dal modo del file
di backup, proprio per restare sicure sul webroot reale
(`docs/DEPLOYMENT.md`, "Public asset permission contract"). Rifiuta di
procedere se il backup è incompleto o già stato usato per un rollback
(protegge da un doppio rollback accidentale).

**Cosa NON copre**: `~/public_html/index.php` — come documentato in
`docs/CPANEL_FRONT_CONTROLLER_RUNBOOK.md`, quel file non è (e non può
essere) una copia byte-per-byte di `public/index.php` di questo
repository, quindi non è mai parte di un manifest generato da un diff Git
del repository stesso. Un front controller rotto in `public_html` va
verificato e corretto manualmente seguendo quel runbook, non tramite
questo script. Dopo qualunque rollback che tocchi `public_html`,
rieseguire le verifiche `curl` documentate lì:

```bash
curl -sI https://kairus.it/ | head -1                       # atteso: HTTP/2 200
curl -s https://kairus.it/articolo/uno-slug-qualunque | head -5   # atteso: markup Laravel, non un 404 Apache "nudo"
```

Dopo il rollback dei file statici, riverificare anche il gate applicativo
corrispondente:

```bash
php artisan deploy:verify-front-controller
php artisan deploy:asset-drift   # solo se DEPLOY_SERVED_PUBLIC_ROOT è configurato
```

## 4. Media (Libreria Media, `public/assets/img`)

**Correzione (finding Codex P1, PR #616)**: una prima versione di questa
sezione affermava che il rollback "non tocca né deve toccare" la Libreria
Media perché i suoi file "esistono indipendentemente da quale directory di
release sia attiva" — non è verificato da nessun file di questo
repository, ed è probabilmente falso per il rollback completo (§1).

`public/assets/img` è letto/scritto da ogni servizio reale della Libreria
Media tramite `public_path('assets/img')` (`docs/STORAGE_AUDIT.md`, "§4bis
storage/ vs public/assets/img") — cioè **dentro** la directory `public/`
dell'applicazione corrente, non in un percorso condiviso esterno. Contiene
sia asset curati git-tracked sia upload scritti a runtime, **non
git-tracked**. `App\Services\Deploy\PersistentStoragePreflight` (Cantiere
18, `deploy:verify-persistent-storage`) verifica solo due percorsi contro
il rischio "silenziosamente perso al prossimo switch di release" — il
registro release e la directory dei backup Backup V2
(`docs/DEPLOYMENT.md`, "Persistent storage preflight") — **mai**
`public/assets/img`. Se quella directory non è deliberatamente condivisa
tra le directory di release a livello di sistema operativo (es. un mount o
un symlink impostato dall'operatore, mai da questo repository), un
rollback completo (§1) che cambia directory di release attiva farebbe
leggere all'applicazione la copia di `public/assets/img` di QUELLA
directory — che può mancare di ogni upload avvenuto dopo che quella
release ha smesso di essere corrente.

**UNKNOWN / TO CONFIRM**: se `public/assets/img` è condiviso tra le
directory di release in produzione (mount/symlink a livello di sistema
operativo) o è una copia indipendente per ciascuna — nessun file di questo
repository lo stabilisce. Finché non è confermato, un rollback completo
va considerato a rischio di "perdita" (lato lettura applicativa, non
necessariamente lato disco: `MEDIA_PUBLIC_ROOT`, se configurato, avrebbe
comunque la copia più recente, ora scollegata da quale directory
l'applicazione legge) di ogni upload media avvenuto durante la release che
si sta annullando — verificare manualmente lo stato dei file più recenti
in `public/assets/img` sulla directory ora attiva dopo ogni rollback
completo, prima di considerarlo concluso.

Un rischio distinto, non legato allo switch di directory: se il rilascio
da annullare conteneva un bug applicativo che ha cancellato o spostato
erroneamente dei file media, quell'operazione — se `MEDIA_PUBLIC_ROOT` è
configurato — è già stata replicata dal vivo su entrambe le root da
`App\Services\PublicMediaSyncService` (sincrona con l'azione applicativa,
non con il deploy). Nessun rollback di release la annulla: non esiste
alcun meccanismo di versioning o backup della Libreria Media in questo
repository, stesso limite già descritto per il database al punto 2.

## 5. Cache

Il passo più semplice e sempre sicuro, idempotente in qualunque momento:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Stessa sequenza già eseguita da `deploy.sh` ad ogni rilascio riuscito.
Non richiede alcuna decisione: eseguirla dopo un rollback (completo o
parziale) è sempre corretto, mai dannoso.

Vale però lo stesso limite di OPcache/PHP-FPM già citato al punto 1: senza
un reload di PHP-FPM sull'hosting reale, `optimize:clear` da solo non
garantisce che il bytecode servito sia effettivamente aggiornato.

## Sequenza consigliata

1. Decidere rollback completo (§1) o solo dei file statici (§3), in base a
   cosa risulta rotto e a cosa esiste ancora sul server.
2. Se sono coinvolte migration, **valutare ed eseguire §2 PRIMA di
   qualunque altro passo, incluso lo switch di directory** — un rollback
   di release che precede quello di schema può rendere `migrate:rollback`
   incapace di trovare la migration da annullare (§1, "Attenzione
   all'ordine"), e un rollback dei soli file statici con uno schema di
   database già avanzato lascia comunque l'applicazione in uno stato
   incoerente.
3. Eseguire il rollback scelto (§1 o §3).
4. Rieseguire il refresh cache (§5).
5. Riverificare front controller e drift asset (`deploy:verify-front-
   controller`, `deploy:asset-drift` se configurato).
6. Verificare manualmente `public_html/index.php` e `.htaccess` con i
   comandi `curl` di `docs/CPANEL_FRONT_CONTROLLER_RUNBOOK.md` se il
   rollback ha toccato `public_html`.
7. Confermare con l'operatore hosting se un reload di PHP-FPM è
   necessario (OPcache).
8. Verificare a parte lo stato della Libreria Media (§4) — nessun
   passo precedente ne garantisce la persistenza attraverso lo switch di
   directory, tanto meno un ripristino automatico.

## Cosa questo runbook NON fa

Come ogni altro documento operativo di questo programma
(`docs/BACKUP_V2_OPERATIONS.md`, `docs/CPANEL_FRONT_CONTROLLER_RUNBOOK.md`):
non automatizza nulla di nuovo, non garantisce che un rollback sia sempre
possibile (dipende da backup/manifest presi *prima* dell'incidente, non
creabili retroattivamente), e non sostituisce una finestra di manutenzione
o un piano di rollback specifico rivisto da un operatore per un rilascio
con migration — `docs/DEPLOYMENT.md`, "Rollback information", lo dichiara
già esplicitamente: *"Database rollback is not implied by a Git
rollback."*
