# Production deployment

Kairus production uses MariaDB/MySQL. SQLite remains intentional for local development and deterministic automated tests; it is not the production backup model.

## Safety contract

`deploy.sh` is a release verification wrapper, not a provisioning script and not Backup V2.

Run it only after the intended release files and the real production `.env` are already present:

```bash
bash deploy.sh <expected-40-character-git-sha>
```

The wrapper fails unless:

- the checked-out Git revision exactly matches the expected SHA;
- `APP_ENV` resolves to `production`;
- `APP_DEBUG` resolves to `false`;
- `APP_KEY` is already configured (the deploy never generates or rotates it);
- the configured database connection is `mysql` or `mariadb`;
- there are no pending migrations.

After successful checks and cache refresh, the wrapper writes `REVISION` and `DEPLOY_INFO` in the release directory. `DEPLOY_INFO` records only revision, UTC deployment time and database driver; it must never contain credentials.

## Database and backup ordering

The repository's original `backup:database` command is SQLite-only. It must not be used as a MariaDB/MySQL production backup and `deploy.sh` deliberately does not call it.

Backup V2 (`backup:database-v2`) now exists in this repository: a real MariaDB/MySQL dump command with cross-process locking, atomic publication, SHA-256/size metadata and restrictive permissions, with CI evidence of a full dump-and-restore cycle. It is manual/opt-in only — `deploy.sh` does not invoke it, `routes/console.php` does not schedule it, and no automatic deployment path calls it. Running it remains a deliberate, separate operator action.

A deployment with pending migrations therefore stops before any schema change. Before such a release can proceed, run `php artisan backup:database-v2` (or the approved external production procedure) and verify the resulting dump, then run the migrations through a separately reviewed procedure. Wiring Backup V2 into the deploy pipeline itself — so a migration-bearing deploy could back up and migrate in one automated step — remains a distinct, deliberately gated engineering decision, not something this contract does implicitly.

This fail-closed behavior is intentional: a deployment with no pending migrations can refresh caches and record the revision safely; a schema-changing deployment cannot silently migrate first and back up later.

## Rollback information

Before deploying, record the current production `REVISION` as the rollback target. The expected new SHA supplied to `deploy.sh` is the forward target. If the release fails before `REVISION`/`DEPLOY_INFO` are rewritten, those files continue to identify the last successfully completed wrapper run.

Database rollback is not implied by a Git rollback. Any release that includes migrations requires an explicit migration/restore plan reviewed together with the MariaDB/MySQL backup procedure.

## What stays SQLite

SQLite is intentionally retained for local development, PHPUnit and the deterministic Playwright/browser environment. Do not replace those uses merely because production uses MariaDB/MySQL.
