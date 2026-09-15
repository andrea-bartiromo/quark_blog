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
il manifest da un intervallo di commit reale.

**Prerequisito**: un backup deve essere stato preso *prima* del rilascio
che si vuole annullare:

```bash
scripts/git-release-manifest.sh --from <sha-precedente> --to <sha-nuovo> --repo <checkout> > manifest.tsv
scripts/selective-deploy-backup.sh backup \
  --manifest manifest.tsv \
  --app-root ~/kairus_app --public-root ~/public_html \
  --backup-root <directory-di-backup> \
  --previous-sha <sha-precedente> --target-sha <sha-nuovo>
```

Per annullare, dato quel backup:

```bash
scripts/selective-deploy-backup.sh rollback \
  --backup-dir <directory-di-backup-dal-passo-precedente> \
  --app-root ~/kairus_app --public-root ~/public_html
```

Cosa copre: ogni file `app`-scoped (dentro `~/kairus_app`) e `public`-
scoped (dentro `~/public_html`) presente nel manifest generato dal diff
Git tra le due revision — inclusi CSS/JS e `public/.htaccess` se
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

La Libreria Media **non fa parte del payload di rilascio**: i file caricati
tramite l'applicazione vivono in `public/assets/img`, fuori da Git, e
`App\Services\PublicMediaSyncService` li replica in tempo reale (ad ogni
creazione, spostamento o eliminazione operata dall'applicazione stessa,
non ad ogni deploy) verso una seconda document root opzionale
(`MEDIA_PUBLIC_ROOT`, se configurata). Il rollback di una release — intero
o parziale che sia — **non tocca né deve toccare** il contenuto della
Libreria Media: quei file esistono indipendentemente da quale directory di
release sia attiva.

Il rischio reale non è "il rollback perde i media", ma il contrario: se il
rilascio da annullare conteneva un bug applicativo che ha cancellato o
spostato erroneamente dei file media, quell'operazione è già stata
replicata dal vivo su entrambe le root (proprio perché
`PublicMediaSyncService` è sincrona con l'azione, non con il deploy) —
tornare a una release precedente non ripristina quei file, perché non
esiste alcun meccanismo di versioning o backup della Libreria Media in
questo repository. Questa lacuna non ha oggi alcuna mitigazione
verificabile qui: va trattata allo stesso modo del limite descritto per
il database al punto 2 — nessun ripristino automatico esiste.

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
2. Se sono coinvolte migration, **fermarsi e valutare §2 prima di
   qualunque altro passo** — un rollback dei soli file statici con uno
   schema di database già avanzato lascia l'applicazione in uno stato
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
   passo precedente la ripristina automaticamente.

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
