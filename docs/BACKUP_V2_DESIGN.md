# MariaDB Backup V2 design

## Status

Design only. This document does not enable production backups, migrations or deploys.

The existing `backup:database` command is SQLite-only. It copies `database/database.sqlite` into `storage/backups` and retains the newest files. It is not a MariaDB/MySQL backup implementation and must not be used as the pre-migration safety gate for production.

## Decision

Do not implement MariaDB Backup V2 until the runtime contract below can be verified without inventing production infrastructure details. The repository currently does not establish:

- whether `mariadb-dump` or `mysqldump` is installed on the production host;
- the exact production MariaDB server version;
- the production backup destination/mount and available disk budget;
- the credential-delivery mechanism available to the dump client;
- whether the database account has all privileges needed for a consistent dump;
- an off-host durability/replication target;
- a tested production restore runbook.

Those are deployment facts, not application defaults, so they must not be guessed in code.

## Command contract

Introduce a separate command, for example `backup:database-v2`, rather than silently changing the SQLite command.

The command must support only configured `mysql`/`mariadb` connections. Any other driver fails before creating an artifact.

Suggested explicit modes:

- periodic backup: ordinary scheduled durability copy;
- pre-migration backup: tagged with the expected release SHA and intended to satisfy the deployment migration gate.

A pre-migration artifact must be distinguishable from a periodic artifact in metadata and filename.

## Dump consistency

Use the MariaDB/MySQL dump client appropriate to the verified production server/client combination. For transactional InnoDB data, request a consistent snapshot without table locks where supported. Do not claim global consistency if non-transactional tables are present; detect/document that condition before enabling the gate.

The process must capture exit status and stderr separately. Exit code 0 is necessary but not sufficient for publication.

## Credential handling

Do not put DB passwords directly in process arguments.

Preferred mechanism: create a temporary client option file owned/readable only by the application user (`0600`) containing host, port, user and password; invoke the dump client with the option-file path; delete the option file in a `finally`/trap path. The option file itself must never be logged or committed.

If the deployment platform provides a safer native credential mechanism, prefer it and document the exact integration.

## Artifact lifecycle

1. Validate driver/configuration and destination capacity.
2. Acquire a non-overlap lock scoped to the configured database/instance.
3. Create the backup directory with restrictive permissions.
4. Write the dump to a unique temporary file in the final destination directory.
5. Require dump process success.
6. Require the temporary artifact to exist and be non-empty.
7. Perform structural verification before publication (see below).
8. Set restrictive file permissions (`0600` unless the verified deployment requires a stricter compatible mode).
9. Atomically rename the temporary artifact to its final name.
10. Only after successful publication, apply retention.
11. Always remove temporary credential and dump files on failure.
12. Release the lock.

A `.tmp`/partial file is never a valid backup.

## Naming

Use UTC and collision-resistant naming, for example:

`mariadb-YYYYMMDDTHHMMSSZ-<mode>-<short-release-sha>-<random>.sql`

Do not include database passwords, usernames, private hostnames or other secrets. If instance identity is required, use an explicitly configured non-secret label.

## Verification before publication

At minimum:

- dump process exited successfully;
- artifact exists and size is greater than zero;
- output contains expected SQL dump structure/header markers appropriate to the selected client;
- output is not an HTML/error response or plain diagnostic text;
- no temporary credential content is copied into the artifact.

These checks detect obvious failures but are not restore verification.

## Restore verification — required before migration gate is considered mature

Gold-standard CI/integration flow:

1. Start ephemeral MariaDB A at the supported CI target version.
2. Run current migrations and seed a small deterministic fixture covering foreign keys, unique constraints, JSON/data types used by Kairus and representative article/linking state.
3. Produce a Backup V2 dump using the same command/service used by production.
4. Start or prepare empty MariaDB B.
5. Restore the dump into B using the matching client.
6. Verify migrations/schema presence and deterministic fixture data/relationships.
7. Fail if dump or restore emits an error, if required tables are missing, or fixture assertions differ.

Do not mark Backup V2 restore-tested until this exact path executes successfully in CI with the real dump/restore clients.

## Retention

Retention must be configurable and must run only after a new artifact has passed validation and atomic publication.

Keep pre-migration backups and periodic backups as separate retention classes. Never delete the last known-valid artifact because a new backup attempt failed.

Deletion failures should be logged as warnings and must not retroactively invalidate a newly published backup, but repeated retention failure must be observable to avoid silent disk exhaustion.

## Concurrency and scheduler

Scheduled Backup V2 must use `withoutOverlapping()` and the command/service must also own an explicit lock so manual/deploy/scheduled invocations cannot write concurrently to the same target.

Do not use `onOneServer()` unless the production scheduler/cache topology is verified to support that guarantee.

## Observability

Log only non-secret metadata:

- mode (`periodic` / `pre-migration`);
- UTC start/end time;
- configured non-secret instance label if available;
- release SHA for pre-migration mode;
- final artifact basename;
- artifact size;
- verification result;
- retention result;
- failure stage and sanitized client exit status.

Never log passwords, temporary option-file contents or full process command lines containing secrets.

## Deployment integration

The desired future deployment contract is:

1. verify expected Git SHA;
2. validate production environment and MariaDB/MySQL driver;
3. inspect pending migrations;
4. if none are pending, release without a DB backup requirement;
5. if migrations are pending, execute Backup V2 in `pre-migration` mode;
6. require a newly created, verified backup artifact associated with the expected SHA;
7. run migration safety gate;
8. run `php artisan migrate --force`;
9. run post-migration application/schema verification;
10. only then record `REVISION` / `DEPLOY_INFO`.

Until Backup V2 is implemented and restore-verified, the fail-closed behavior proposed by PR #179 for migration-bearing releases should remain unchanged.

## Migration-specific caution on current main

`2026_08_11_165128_alter_target_article_id_on_article_link_suggestions_to_null_on_delete.php` is not a trivial create-only migration. Its `up()` adds/backfills a column, drops/recreates a foreign key and changes column nullability. That deserves `REVIEW_REQUIRED` treatment for production deployment even though `migrate:fresh` succeeds on ephemeral MariaDB CI. Fresh-schema compatibility does not measure ALTER duration or lock impact on the real production table.

Its `down()` deletes rows whose `target_article_id` became null before restoring the old non-null/cascade contract. That rollback is intentionally data-destructive for those rows and must not be presented as lossless rollback.

## Tests required for implementation PR

Before enabling Backup V2, add behavioral tests for:

- supported mysql/mariadb driver;
- unsupported driver;
- missing dump client/configuration;
- successful non-empty dump;
- dump process failure;
- empty/invalid dump rejection;
- temporary-file cleanup;
- atomic publication;
- same-second/collision-safe naming;
- restrictive permissions where testable;
- retention after success only;
- preservation of existing backups after failure;
- command/service overlap lock;
- sanitized logs and absence of secrets;
- pre-migration metadata/release SHA;
- restore into a second ephemeral MariaDB instance.

## Implementation gate

Implementation is ready to start only when the deployment environment answers these questions:

1. exact supported production MariaDB version;
2. installed/approved dump and restore client versions;
3. secure credential mechanism available to those clients;
4. backup destination and minimum free-space policy;
5. required local/off-host retention policy;
6. database privileges available to the backup user;
7. whether all production tables are transactional/InnoDB;
8. acceptable maximum backup and migration maintenance window.

Until then the correct state is: **design complete, implementation blocked by unverified deployment facts**.
