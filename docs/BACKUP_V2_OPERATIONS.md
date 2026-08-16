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
| RETENTION_POLICY | UNKNOWN / TO CONFIRM |
| OFF_HOST_STORAGE | UNKNOWN / TO CONFIRM |
| RPO | UNKNOWN / TO CONFIRM |
| RTO | UNKNOWN / TO CONFIRM |
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
