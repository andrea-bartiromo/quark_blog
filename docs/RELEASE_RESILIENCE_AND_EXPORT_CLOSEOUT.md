# Export e resilienza release — closeout

## Dashboard Data Export (Prompt 61–62)

Il perimetro deployed comprende controller/request, serializer, service,
package builder, finestra, sanitizer, config, view e route. I test repository
coprono autorizzazioni, formati, CSV injection, ZIP/manifest/checksum, soglia
privacy, rate limit e cleanup. Il log di successo deve avvenire soltanto dopo la
costruzione completa; i fallimenti non devono contenere email, query, token,
session ID o path temporanei.

La certificazione con fixture/admin controllato non è stata eseguita in questo
ambiente perché PHP/Composer non sono disponibili. Nessun dato production è
stato letto o esportato. Stato: **DEPLOYED, STATICALLY_AUDITED,
RUNTIME_FIXTURE_GATE_PENDING**.

## Backup, RPO/RTO e restore (Prompt 63–64)

Il workflow `backup-restore.yml` usa MariaDB 11.4 effimero, fixture
deterministica, lock, dump, database `kairus_restore`, verifica relazioni/
Unicode e cleanup. Non usa credenziali production. Trigger same-SHA presenti:
PR, push main e workflow_dispatch; concurrency non cancella main.

Obiettivi proposti, da approvare con l'operatore:

- RPO 24 ore per contenuti editoriali, 1 ora durante finestre di pubblicazione;
- RTO 4 ore per DB+app, 1 ora per rollback applicativo senza schema;
- copia off-host cifrata, checksum, retention giornaliera/settimanale/mensile;
- restore drill trimestrale solo su MariaDB effimero, mai dump production in CI.

Il drill deve misurare durata, verificare Unicode/foreign key/conteggi/checksum,
eliminare DB e artifact e provare la non contaminazione production. La CI
esistente implementa già il nucleo; durabilità off-host resta decisione
infrastrutturale non eseguita.

## Dipendenze e deploy (Prompt 65–66)

Manifest dichiarato: PHP `^8.3`, Laravel `^13.7`, Tinker `^3.0`; dev Pail,
Pint, PAO, PHPUnit. Frontend: Playwright 1.62, Vite 8, Tailwind 4. Nessun update
è stato effettuato e gli audit vulnerabilità richiedono registry access.

Rischio dimostrato dal primo deploy: riuso del vendor production con manifest
`bootstrap/cache/packages.php` contenente Pail, poi autoload non rigenerato per
le nuove classi. Il deploy riuscito ha usato worktree detached, perimetro
selettivo, backup/rollback, autoload metadata-only, cache isolate nel preflight,
maintenance, asset drift, route check strutturato, REVISION/DEPLOY_INFO e smoke.

Stop sicuri: SHA non esatto, main avanzato, migration/dipendenze mutate, Social
ON, asset drift, autoload classi assenti, route mancante, failed jobs o HTTP non
200. Mai usare una ricerca testuale fragile nell'output tabellare delle route.

Il repository principale dell'operatore deve restare sul proprio branch; ogni
certificazione release usa worktree detached. Nessun cambiamento a `deploy.sh`
è consigliato senza un finding riproducibile nel suo contratto.
