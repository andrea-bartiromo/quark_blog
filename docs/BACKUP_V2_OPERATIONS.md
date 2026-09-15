# Backup V2 operator checklist

## Status

Backup V2 remains manual-only. This document does not enable production scheduling, deployment migrations, production backups, or automatic restore.

Repository CI may prove a real dump and restore against an ephemeral MariaDB service. That evidence does not replace production operator facts.

## Production facts required before enablement

| Fact | Required value |
| --- | --- |
| PRODUCTION_MARIADB_VERSION | UNKNOWN / TO CONFIRM |
| DUMP_BINARY | UNKNOWN / TO CONFIRM |
| DUMP_BINARY_VERSION | UNKNOWN / TO CONFIRM |
| RESTORE_BINARY | UNKNOWN / TO CONFIRM |
| RESTORE_BINARY_VERSION | UNKNOWN / TO CONFIRM |
| BACKUP_USER | UNKNOWN / TO CONFIRM |
| BACKUP_USER_PRIVILEGES | UNKNOWN / TO CONFIRM |
| CREDENTIAL_DELIVERY_METHOD | UNKNOWN / TO CONFIRM |
| BACKUP_DIRECTORY | UNKNOWN / TO CONFIRM |
| BACKUP_DIRECTORY_OWNER | UNKNOWN / TO CONFIRM |
| BACKUP_DIRECTORY_MODE | UNKNOWN / TO CONFIRM |
| FREE_SPACE | UNKNOWN / TO CONFIRM |
| ESTIMATED_DB_SIZE | UNKNOWN / TO CONFIRM |
| RETENTION_POLICY | UNKNOWN / TO CONFIRM — but when `DB_BACKUP_RETENTION` is set, whether it is actually being enforced on disk is now verifiable (see "Retention/RPO/RTO — what is verifiable today" below) |
| OFF_HOST_STORAGE | Config-available, disabled by default (see "Optional off-host copy" below) — production destination, credentials, and retention/off-host policy remain UNKNOWN / TO CONFIRM |
| RPO | UNKNOWN / TO CONFIRM as a committed production target — the current best-effort observable signal (time since the last valid local backup) is verifiable (see below) |
| RTO | UNKNOWN / TO CONFIRM as a committed production target — CI proves a real restore succeeds and reports how long that CI restore took, not a production number (see below) |
| MAINTENANCE_WINDOW | UNKNOWN / TO CONFIRM |
| NON_INNODB_TABLES | UNKNOWN / TO CONFIRM |
| RESTORE_RUNBOOK | UNKNOWN / TO CONFIRM |
| RESTORE_TEST_DATE | UNKNOWN / TO CONFIRM |

Do not put passwords, DSNs containing passwords, APP_KEY, private credential-file contents, or other secrets in this document.

## Current command contract

`php artisan backup:database-v2`

Optional pre-migration mode:

`php artisan backup:database-v2 --mode=pre-migration --release-sha=<40-character-sha>`

The command accepts only the configured `mysql`/`mariadb` connection, writes credentials to a temporary `0600` client option file, writes the dump to a private temporary artifact, performs basic SQL structure validation, atomically promotes the dump, publishes checksum/size metadata, and only then considers opt-in retention.

Backup identity is a non-secret hash of connection/endpoint/database identity. It scopes artifact names, retention, and overlap locks without publishing a password or full DSN. Retention is isolated by both database identity and backup mode, so periodic cleanup cannot evict pre-migration artifacts or another database's artifacts.

Overlap uses the dedicated `DB_BACKUP_LOCK_STORE`, defaulting to Laravel's cross-process `file` store rather than inheriting the application default. Process-local `array`/`null` stores are rejected before a dump starts. `DB_BACKUP_LOCK_SECONDS` must be a positive integer. Multi-host production must explicitly confirm that the chosen lock store is shared by every host capable of launching Backup V2.

If `DB_BACKUP_BINARY` is empty, compatible client discovery prefers `mariadb-dump` and falls back to `mysqldump`. Production must still explicitly approve the actual binary and version before enablement.

`DB_BACKUP_RETENTION` is deliberately unset by default. No production retention count is guessed by the repository.

## Optional off-host copy (Cantiere 71, programma "100 cantieri Kairus")

After the local artifact and metadata pair is validated and atomically published, Backup V2 can optionally copy both files to a second Laravel filesystem disk — config-only, disabled by default:

- `DB_BACKUP_OFFHOST_DISK` — the name of a disk already configured in `config/filesystems.php` (e.g. an `s3`-compatible disk). Empty/unset (the default) means no off-host copy is ever attempted; behavior is identical to before this cantiere.
- `DB_BACKUP_OFFHOST_PREFIX` — path prefix on that disk, defaults to `mariadb`.

This is a config-and-test deliverable only: no real off-host destination, credentials, or production policy are established here. The local backup remains the sole guaranteed artifact; a failed off-host copy is a non-fatal warning, never a reason to consider the backup itself failed. Enabling this in production still requires the same operator approval as gate item 5 below (backup destination and retention/off-host policy approved) — this cantiere makes the mechanism available, it does not satisfy that gate.

**Cantiere 74** added the missing other half: `deploy:verify-database-backup` now also checks, when `DB_BACKUP_OFFHOST_DISK` is configured, that the most recent local backup's artifact and metadata actually exist on that off-host disk (same remote path convention the upload itself uses, `{prefix}/{basename}`). A failed off-host upload was already a non-fatal warning for the backup command — but without this check, a *repeated* failure (expired credentials, exhausted quota, an unreachable disk) would leave the sole off-host copy silently missing or stale, exactly in the scenario — loss of the local backup — where it would matter most. Still read-only and still non-blocking for `deploy.sh`: it only reads whether the remote objects exist, never writes or deletes them.

`MariaDbBackupService::createLocked()` publishes the local artifact and metadata *before* copying them off-host, but both steps run under the same cross-process lock (`backup:v2:{identityHash}`) held for the whole `backup:database-v2` execution. Without accounting for that window, an audit running while a backup is still in flight could observe the freshly-published local backup as "latest" while its off-host copy genuinely hasn't happened yet, and report a false "missing" (Codex finding, PR #615). The audit now attempts a non-blocking acquisition of that same lock before judging the off-host mirror: if the lock is currently held by another process, the off-host check is skipped for that run (reported as "not yet checked", never as "missing") — the next run, once the lock is free, checks normally.

## Retention/RPO/RTO — what is verifiable today (Cantiere 72, programma "100 cantieri Kairus")

This cantiere adds observability to three facts the table above still lists as production decisions — it does not commit to any of them, and does not change any retention/backup behavior. It only makes a previously silent risk (or a previously unmeasured number) checkable without reading raw logs.

### Retention enforcement

`MariaDbBackupService::applyRetention()` treats a cleanup failure as a non-fatal warning (see "Failure semantics" below) — the backup itself stays valid, but a *repeated* cleanup failure (disk permissions, an unexpected lock) would otherwise accumulate backups past the configured `DB_BACKUP_RETENTION` limit invisibly. `php artisan deploy:verify-database-backup` (Cantiere 19) now also reports this: when `DB_BACKUP_RETENTION` is configured, it counts the actually-valid backup pairs on disk **per mode** (`periodic`/`pre-migration`, since retention is enforced separately per mode) and warns — still without blocking `deploy.sh` — when either mode's count exceeds the configured limit. Still read-only: it never deletes anything itself.

### RPO — what is observable vs. what remains a decision

Backup V2 is manual/opt-in: nothing schedules `backup:database-v2` automatically, so there is no enforced cadence to derive an RPO from. What *is* observable today is the best-effort proxy `deploy:verify-database-backup` already reported since Cantiere 19: the age of the most recent valid local backup for the current database identity, optionally checked against `DB_BACKUP_MAX_AGE_HOURS`. That age is a floor on the real RPO (a fresher off-host copy or a more frequent operator cadence could do better), never a commitment — the actual RPO target an operator is willing to accept remains a production decision (`RPO` row above), not something this repository can infer from a manual, opt-in mechanism.

### RTO — what is observable vs. what remains a decision

The "MariaDB 11.4 real dump restore" CI job (see "CI restore evidence contract" below) proves, on every push to `main`, that a real Backup V2 artifact restores successfully with the real MariaDB client. It now also reports how long that specific CI restore took (echoed in the job log). This is **not** a production RTO: CI's database is a small deterministic fixture, on ephemeral CI hardware/network, restored non-interactively — none of which represents production data volume, infrastructure, or the human steps of an actual approved restore runbook. It is the one real, reproducible number available today instead of none, not a substitute for measuring an actual production restore rehearsal (`RESTORE_TEST_DATE` row above, still UNKNOWN / TO CONFIRM).

## Failure semantics

Before atomic publication, any critical failure returns non-zero and incomplete temporary artifacts are cleaned. A failed attempt must not trigger retention.

After a validated artifact and metadata pair has been published, retention cleanup failure is a warning/partial-success condition: the new backup remains valid and the command remains successful. Operators must treat repeated retention warnings as a disk-capacity incident.

Basic SQL validation is not restore proof. `validation=basic-sql-validated` means only that the dump passed repository structural checks.

## CI restore evidence contract

`RESTORE_VERIFIED=YES` may be reported only when the dedicated repository CI job has successfully completed all of these steps on the same final HEAD:

1. start ephemeral MariaDB;
2. run current migrations;
3. seed deterministic representative data;
4. execute the real `backup:database-v2` command with a real dump client;
5. validate the published artifact and metadata;
6. create a second disposable database;
7. restore the real artifact with the MariaDB client;
8. assert representative schema, foreign-key presence, relationships, Unicode, nullable values, and timestamps against the restored database;
9. clean the disposable database and backup artifacts.

A unit/feature test with a fake dump runner is not sufficient for `RESTORE_VERIFIED=YES`.

**Cantiere 73** (programma "100 cantieri Kairus") scoped "isolated restore with fixture/non-production dump data" and found this contract already delivers it: the CI job's steps above are exactly that — a real restore into a disposable database (`kairus_restore`, never the source `kairus_test`), seeded with deterministic non-production fixture data, torn down after assertion. No new restore code was written for this cantiere; writing one would have duplicated an already-real, already-CI-proven capability. What this evidence contract does **not** cover — and what remains a genuinely open gap were an operator ever to want it — is an **on-demand, local** restore rehearsal outside CI: no `artisan backup:restore*` command exists today, and none should be added without an explicit, deliberate decision on its guardrails first. A restore-rehearsal command is not like the read-only audits elsewhere in Backup V2: it would need to drop and recreate a real database, so a hard-refuse-in-production check and a rejection of any target matching the app's own configured database identity (reusing `MariaDbBackupService`'s identity-hash pattern) would be non-negotiable from the first line of code, not an afterthought — exactly the kind of decision this program treats as needing explicit sign-off before building, not something to add opportunistically alongside a documentation pass.

## Restore is operator-controlled

Backup V2 does not automatically restore a database. A production restore must use an approved runbook, approved credentials, a reviewed target, and an operator-controlled maintenance window. Never overwrite the live database as an automatic response to a backup or migration failure.

## Deployment integration remains disabled

Backup V2 must not be wired into `deploy.sh`, the scheduler, cron, or migrations by this implementation PR.

Before PR #179 may permit a migration-bearing production release, all of the following remain required:

1. MariaDB compatibility CI accepted/merged;
2. deployment safety contract accepted/merged;
3. Backup V2 real dump-to-restore CI green on the final implementation HEAD;
4. production facts above confirmed;
5. backup destination and retention/off-host policy approved;
6. target migration lock/duration reviewed;
7. production restore runbook approved;
8. pre-migration backup procedure tested in an approved non-production environment.

Until those gates are satisfied, migration-bearing deploys remain fail-closed.
