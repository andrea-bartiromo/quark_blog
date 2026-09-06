# Incident runbook (Prompt 081-090, 150-prompt program)

Procedure operative per gli incidenti che questo repository ha gli
strumenti per affrontare oggi. Non e' un runbook generico: ogni sezione
punta a uno script/comando/documento reale gia' esistente in questo
repository, mai a una capacita' presunta. Dove uno strumento non esiste
ancora, questo documento lo dichiara esplicitamente invece di inventare
un passo.

## RPO / RTO — stato reale, non stimato

**RPO (Recovery Point Objective) oggi: non definito / potenzialmente
illimitato.** `php artisan backup:database-v2` (vedi
`docs/BACKUP_V2_OPERATIONS.md`) e' **manuale e opt-in**: nessuno scheduler,
nessun cron, nessun hook di deploy lo esegue automaticamente
(`routes/console.php` schedula solo il backup SQLite locale, guardato per
`config('database.default') === 'sqlite'` — mai il backup MariaDB/MySQL di
produzione). Senza una cadenza schedulata approvata dall'operatore, il RPO
reale coincide con "tempo trascorso dall'ultimo backup manuale eseguito da
un operatore" — un valore che questo repository non puo' conoscere ne'
garantire. **Definire un RPO numerico richiede prima una decisione
operativa** (una cadenza schedulata approvata, fuori dal perimetro di
questa missione, che non autorizza l'attivazione di scheduling automatico
sulla produzione).

**RTO (Recovery Time Objective) oggi: non misurato in produzione.** La
CI (`.github/workflows/backup-restore.yml`) dimostra un ciclo
dump→restore reale contro MariaDB effimero in circa 20 minuti di job CI
(timeout configurato), ma quel numero descrive un runner GitHub Actions
pulito, non l'hardware/rete della produzione reale — vedi
`docs/BACKUP_V2_OPERATIONS.md`, tabella "fatti di produzione richiesti",
ancora `UNKNOWN / TO CONFIRM` su `RPO`/`RTO`/`RESTORE_TEST_DATE`.

**Questa non e' un'inconsistenza da fermare-e-segnalare** (nessuna
promessa scritta altrove in questo repository dichiara un RPO/RTO
numerico che questi fatti contraddicano) — e' una precondizione operativa
gia' nota e gia' documentata da Prompt 031-040
(`docs/PRODUCTION_AUDIT_2026_09.md`, finding 1), qui resa esplicita nel
contesto di un incidente reale invece che di un audit statico.

## Scenario 1 — Un deploy si interrompe a metà o produce un problema rilevato dal gate

1. Se `deploy.sh` e' gia' fallito (gate `deploy:asset-drift`, migrazioni
   pendenti, revisione non corrispondente, ecc.): **REVISION e DEPLOY_INFO
   non sono stati scritti** — la release precedente resta quella
   ufficialmente registrata, per costruzione (`docs/DEPLOYMENT.md` §
   Safety contract). Non serve alcun rollback: il deploy fallito non ha
   mai completato la registrazione.
2. Se il problema emerge DOPO un deploy completato con successo (es. un
   file statico non sincronizzato tra le due document root, o un
   permesso non sicuro sfuggito): usare
   `scripts/selective-deploy-backup.sh rollback` con il backup creato
   PRIMA di quel deploy (vedi `docs/RELEASE_RUNBOOK_V2.md` § 6) —
   ripristina solo i file elencati nel manifest, verificando l'hash
   SHA-256 di ciascuno prima di toccare qualunque destinazione.
3. Verificare la guarigione con `php artisan deploy:asset-drift` (o,
   **[branch `fix/deploy-permissions-contract`, non ancora in main]**,
   `bash scripts/staging-rollback-drill.sh` per una prova completa in un
   ambiente usa-e-getta prima di ripetere il deploy).
4. Database: un rollback Git/file **non implica mai** un rollback del
   database (`docs/DEPLOYMENT.md` § Rollback information). Se il deploy
   includeva migration, serve un piano di restore separato e
   approvato — vedi Scenario 2.

## Scenario 2 — Sospetta corruzione/perdita dati nel database di produzione

1. **Non eseguire alcuna migration o comando di scrittura correttivo
   prima di aver isolato la causa.**
2. Se esiste un dump `backup:database-v2` recente e verificato: il
   restore e' **sempre controllato dall'operatore**
   (`docs/BACKUP_V2_OPERATIONS.md` § Restore is operator-controlled) — mai
   automatico, mai eseguito da un comando/hook di questo repository.
   Serve un runbook di restore approvato, credenziali approvate, un
   target rivisto e una finestra di manutenzione controllata.
3. Se non esiste un dump recente: questo e' esattamente il rischio
   descritto sopra in "RPO — stato reale". Non esiste oggi un modo per
   questo repository di colmare quella lacuna a posteriori.
4. Registrare (fuori da questo repository, mai con credenziali/dati
   personali qui) l'ora dell'incidente, l'ultima operazione nota prima
   del sospetto, e il dump piu' recente disponibile — sono gli input
   minimi che qualunque runbook di restore approvato richiedera'.

## Scenario 3 — Asset pubblico disallineato tra le due document root (classe dell'incidente reale del 24/08)

Vedi `docs/DEPLOYMENT.md` § Public asset deployment: two document roots
per la cronologia completa. Procedura:

1. `php artisan deploy:asset-drift` (richiede `DEPLOY_SERVED_PUBLIC_ROOT`
   configurato) identifica esattamente quali file divergono, e
   **[branch `fix/deploy-permissions-contract`]** anche se un file ha un
   permesso non sicuro o e' vuoto su entrambe le radici.
2. Sincronizzare manualmente le due radici per i soli file segnalati
   (questo repository non lo fa automaticamente — passo esterno,
   documentato come tale).
3. Ri-eseguire il comando per confermare `isClean()`.
4. `App\Support\VersionedAsset` garantisce che il browser non serva mai
   una versione cache di un asset precedente al deploy corrente
   (token `REVISION`), indipendentemente da questo scenario — non
   sostituisce il passo 2, lo rende innocuo per l'utente finale nel
   frattempo.

## Cosa questo runbook non copre (dichiarato, non inventato)

- Un runbook di restore database approvato per la produzione reale — non
  esiste ancora (vedi RPO/RTO sopra); questo documento non lo simula.
- Provisioning di un nuovo host di produzione da zero — fuori perimetro,
  nessuno strumento di questo repository lo automatizza.
- Escalation umana/contatti di reperibilita' — organizzativo, non
  tecnico; non un dato che questo repository possa conoscere o inventare.

## Manutenzione di questo documento

Ogni volta che uno scenario reale si verifica, aggiungere qui l'esito
effettivo (come gia' fatto per l'incidente public-premium.css in
`docs/DEPLOYMENT.md`) — mai sostituire una sezione "non ancora noto" con
un valore inventato solo per completezza estetica.
