# Production deployment

Kairus production uses MariaDB/MySQL. SQLite remains intentional for local development and deterministic automated tests; it is not the production backup model.

## Safety contract

`deploy.sh` is a release verification wrapper, not a provisioning script and not Backup V2.

Run it only after the intended release files and the real production `.env` are already present:

```bash
bash deploy.sh <expected-40-character-git-sha>
```

The wrapper fails unless:

- the current directory looks like a complete Laravel release (`artisan`, `composer.json` and `.git` are all present — checked before anything else runs, so an incomplete copy or the wrong working directory fails with a clear reason instead of a confusing PHP or git error later). `.git` may be either a directory (an ordinary clone) or a regular file (a `git worktree add` or submodule checkout, where `.git` holds a `gitdir:` pointer) — both are accepted, since `git rev-parse HEAD` works identically either way (found by an external review of PR #535, which noted the directory-only check rejected an otherwise valid worktree release before verification even began);
- the checked-out Git revision exactly matches the expected SHA;
- `APP_ENV` resolves to `production`;
- `APP_DEBUG` resolves to `false`;
- `APP_KEY` is already configured (the deploy never generates or rotates it);
- the configured database connection is `mysql` or `mariadb`;
- there are no pending migrations.

`deploy.sh` assumes it runs from an already-checked-out release directory (it only ever runs `git rev-parse HEAD` to verify the revision) — it never extracts an archive or changes its own working directory, and a regression test locks this in.

After successful checks and cache refresh, the wrapper writes `REVISION` and `DEPLOY_INFO` in the release directory. `DEPLOY_INFO` records only revision, UTC deployment time and database driver; it must never contain credentials.

## Database and backup ordering

The repository's original `backup:database` command is SQLite-only. It must not be used as a MariaDB/MySQL production backup and `deploy.sh` deliberately does not call it.

Backup V2 (`backup:database-v2`) now exists in this repository: a real MariaDB/MySQL dump command with cross-process locking, atomic publication, SHA-256/size metadata and restrictive permissions, with CI evidence of a full dump-and-restore cycle. It is manual/opt-in only — `deploy.sh` does not invoke it, `routes/console.php` does not schedule it, and no automatic deployment path calls it. Running it remains a deliberate, separate operator action.

A deployment with pending migrations therefore stops before any schema change. Before such a release can proceed, run `php artisan backup:database-v2` (or the approved external production procedure) and verify the resulting dump, then run the migrations through a separately reviewed procedure. Wiring Backup V2 into the deploy pipeline itself — so a migration-bearing deploy could back up and migrate in one automated step — remains a distinct, deliberately gated engineering decision, not something this contract does implicitly.

This fail-closed behavior is intentional: a deployment with no pending migrations can refresh caches and record the revision safely; a schema-changing deployment cannot silently migrate first and back up later.

## Rollback information

Before deploying, record the current production `REVISION` as the rollback target. The expected new SHA supplied to `deploy.sh` is the forward target. If the release fails before `REVISION`/`DEPLOY_INFO` are rewritten, those files continue to identify the last successfully completed wrapper run.

Database rollback is not implied by a Git rollback. Any release that includes migrations requires an explicit migration/restore plan reviewed together with the MariaDB/MySQL backup procedure.

## Release registry

`REVISION` and `DEPLOY_INFO` above live inside the release directory itself and are overwritten/discarded on the next release — with the two-directory-plus-symlink-switch schema already in production use, no history survives across deploys. The operational roadmap's own stated principle (`costruito ≠ CI green ≠ merged ≠ deployed ≠ verified ≠ measured` — see `docs/KAIRUS_TECHNICAL_ROADMAP_V14.md`) is tracked today as a hand-maintained Markdown table, with no automatic source.

`App\Services\Deploy\ReleaseRegistry` is an append-only, JSON Lines log at `config('deploy.release_registry_path')` (`DEPLOY_RELEASE_REGISTRY_PATH`), one event per line: `{"revision", "stage", "recorded_at_utc", "note"}`. **Disabled by default** — same opt-in pattern as `DEPLOY_SERVED_PUBLIC_ROOT`/`MEDIA_PUBLIC_ROOT` — nothing changes until the path is set, and it must point **outside** the release directory (e.g. a file sibling to `~/kairus_app`, never inside it) or the entry is lost on the next release just like `DEPLOY_INFO`.

`deploy.sh` calls `php artisan release:record-registry "$ACTUAL_SHA" --stage=deployed` as the very last step, after `REVISION`/`DEPLOY_INFO` are written. **Never a gate**: a no-op when unconfigured, and any other failure (unwritable path, missing directory) only produces a warning — `deploy.sh` always continues. Later stages (`verified`, `measured`) are meant to be recorded by separate, manual or scheduled invocations of the same command once that evidence actually exists — this repository does not (and should not) infer "verified" or "measured" from the mere fact that a deploy succeeded.

Read the history at any time:

```bash
php artisan release:registry
```

Exits `0` always; prints a table of every registered revision with its earliest `deployed`/`verified`/`measured` timestamp, or an explanatory message when the registry is disabled or empty. `--json` returns the same data as JSON for tooling.

## Persistent storage preflight (Cantiere 18, programma Kairus 100 cantieri)

The release registry above is one instance of a broader class of risk: **any** path meant to survive across releases, but left pointing inside the current release directory, is silently lost the moment the next deploy's symlink switch retires this directory. Database backups (`config('backup.v2.directory')`, `DB_BACKUP_DIRECTORY`) are the other checked instance — unlike the release registry, the backup directory always has a default (`storage_path('backups/mariadb')`), which is itself inside the release directory, so a production `.env` that never overrides it would silently lose every MariaDB backup — and the 7-copy retention documented in `docs/STORAGE_AUDIT.md` — on the very next deploy.

`App\Services\Deploy\PersistentStoragePreflight` (`php artisan deploy:verify-persistent-storage`) checks both paths and reports any that resolve inside this release's own directory. **Informational only** — `deploy.sh` calls it without `|| fail` (same principle as `release:record-registry`): an existing, working production `.env` must never suddenly start blocking deploys over a configuration that was never a hard requirement until now. Review its warning and set `DB_BACKUP_DIRECTORY` (and `DEPLOY_RELEASE_REGISTRY_PATH`, if using the release registry) to a path outside `~/kairus_app` — see `.env.production.example`.

## Database backup health check (Cantiere 19, programma Kairus 100 cantieri)

The preflight above only checks *where* a configured backup directory resolves — it says nothing about whether a valid backup actually exists there, or how old it is. Since `backup:database-v2` remains manual/opt-in (see "Database and backup ordering" above: no scheduler runs it, `deploy.sh` does not call it), an operator who forgets to run it has had no automated signal at all.

`App\Services\Deploy\MariaDbBackupHealthAudit` (`php artisan deploy:verify-database-backup`) is a **read-only** check: it never creates, schedules, or deletes any backup. It scans `config('backup.v2.directory')` for artifact/metadata pairs whose recorded SHA-256 and size still match the file on disk (the same integrity check `MariaDbBackupService::isKnownGoodPair()` uses when applying retention), and reports:

- **not applicable** — the current `database.default` connection is not `mysql`/`mariadb` (Backup V2 only supports those; nothing to check on SQLite dev/test environments);
- **no valid backup found** — the directory has no artifact/metadata pair that passes the integrity check;
- **stale** — a valid backup exists, but is older than `DB_BACKUP_MAX_AGE_HOURS`, when that variable is set;
- **ok** — a valid, non-stale backup exists.

`DB_BACKUP_MAX_AGE_HOURS` is deliberately opt-in, like `DB_BACKUP_RETENTION`: because Backup V2 has no fixed schedule, this repository cannot guess what cadence counts as "too old" for a given deployment, so the staleness check is skipped entirely (only existence is reported) until an operator sets that threshold explicitly — see `.env.production.example`.

**Informational only** — `deploy.sh` calls it without `|| fail`, right after `deploy:verify-persistent-storage`: it must never start blocking an existing, working deploy over a backup policy that was never a hard requirement until now. Wiring Backup V2 *creation* into the deploy pipeline remains, as stated above, a distinct and deliberately gated engineering decision — this check only ever reads what already exists.

## What stays SQLite

SQLite is intentionally retained for local development, PHPUnit and the deterministic Playwright/browser environment. Do not replace those uses merely because production uses MariaDB/MySQL.

## Public asset deployment: two document roots

Production Kairus has two physically separate public trees:

- `~/kairus_app/public` — the Laravel application root's own `public/` directory. This is what `public_path()`, `asset()`, and every piece of PHP code in this repository actually read from.
- `~/public_html` — the real Apache document root. This is what a browser actually receives over HTTP.

`scripts/selective-deploy-backup.sh` already models these as two separate roots (`--app-root` / `--public-root`, with `app`/`public`-scoped manifest entries), which confirms this is the intended architecture: a release's public-scoped files are meant to land in **both** roots identically. But the actual "copy new release files into both roots" step is not implemented anywhere in this repository — `deploy.sh` never touches `public_html`, and `selective-deploy-backup.sh` only backs up/rolls back what's already there, it never applies new content. That copy step is an external, undocumented operation, and nothing in this repository verifies afterward that the two roots ever converged.

**Incident (2026-08-24):** `public-premium.css` diverged between the two roots — `public_html` had the correct, current 14825-byte file (matching `origin/main`), while `kairus_app/public` had a stale 12504-byte file. `App\Support\VersionedAsset` computed its cache-busting `?v=` from `filemtime(public_path(...))`, i.e. from the stale `kairus_app/public` copy. Its mtime never changed, so browsers kept the old file cached indefinitely even though the correct bytes were already being served — a live regression in the "Continua da qui" article card that was invisible to any check reading from the app root.

**Who else is exposed to this class of bug:** any static file under `public/` (CSS, JS, `favicon.ico`, `site.webmanifest`, icons) that is neither part of the git-tracked release payload's own freshness check nor covered by an existing sync mechanism. The one existing exception is the Media Library (`public/assets/img`), which already has a tested, PHP-runtime-driven secondary-root sync (`PublicMediaSyncService`, driven by `MEDIA_PUBLIC_ROOT`) — that mechanism does **not** cover build-time CSS/JS, which is why the incident happened to a stylesheet and not an uploaded image.

**Fix shipped (`App\Support\VersionedAsset`):** when a `REVISION` file exists at the application root — written by `deploy.sh` only after a fully verified, successful deploy, containing the exact Git SHA of the release — that SHA is used as the cache-busting version instead of any file's `mtime`. A release SHA is identical no matter which tree reads it and changes on every single deploy, so a browser can never keep serving a previous release's cached asset after a new deploy, regardless of mtime skew between the two roots. Local development, tests, and CI are unaffected: without a `REVISION` file, behavior falls back unchanged to the original `filemtime(public_path(...))` logic.

**What this fix does *not* solve:** it only guarantees the *browser* always re-requests assets on every new release. It does not guarantee `public_html` actually *has* the new file — that content-synchronization gap is closed separately by the drift detector and deploy gate below.

## Public asset drift detection and release gate

`App\Services\Deploy\PublicAssetDriftDetector` compares the release-managed static files listed in `config('deploy.asset_drift_scan_paths')` (CSS, JS, `assets/icons`, and the top-level static files — deliberately *not* `assets/img`, which already has its own sync via `PublicMediaSyncService`, and *not* `images/`, a large hand-curated tree out of scope by default) between the application root (`public_path()`) and a configured served document root.

It is **disabled by default**. Set `DEPLOY_SERVED_PUBLIC_ROOT` in the production `.env` to the real served webroot (e.g. `~/public_html`) to activate it — same opt-in pattern as `MEDIA_PUBLIC_ROOT` in `config/media.php`. It also self-disables when the two roots resolve, via `realpath()`, to the same physical directory. It never writes to either root; it only reads and reports.

Run it directly at any time:

```bash
php artisan deploy:asset-drift
```

Exits `0` when disabled or clean, non-zero the moment any file differs, is missing on the served root, is missing on the application root, has an unsafe permission, or is empty on both roots — with a table listing every problem path and its SHA-256 on each side.

`deploy.sh` calls this command automatically, right after the cache-refresh step and **before** `REVISION`/`DEPLOY_INFO` are ever written — the same fail-closed placement as the pending-migrations check. When `DEPLOY_SERVED_PUBLIC_ROOT` is configured and a problem exists, the release stops there: no revision gets recorded for a release whose static assets never actually, safely reached the served root. When unset, the check is a no-op and never blocks a deploy — matching every environment (local, CI, staging) that has not configured a second root.

## Public asset permission contract (Prompt 011-019, deploy-hardening program)

The drift detector above originally compared only content (SHA-256) and presence. Content-only comparison has a gap: a release-managed file can be byte-identical on both roots yet unreadable by Apache if its permission mode is too restrictive (e.g. `600`), or empty on both sides if a copy step was interrupted mid-write. Two more statuses close that gap, both wired into the same `isClean()` / `deploy:asset-drift` exit-code contract as `mismatch`/`missing_on_*`:

- **`unsafe_mode`** — a file mode more restrictive than `644`, or a directory named directly in `asset_drift_scan_paths` more restrictive than `755`, on either root, while content still matches. A directory is only reported once, as its own synthetic entry (`app_hash`/`served_hash` both `null`) — not once per file inside it.
- **`empty_file`** — a file that is `0` bytes on both roots (content still "matches" in the SHA-256 sense — both hashes are the empty-string hash — but no static asset managed by this release is legitimately empty). A file that is empty on only one side is still `mismatch`, not `empty_file`.

**Where a restrictive permission could reach the served root:** `scripts/selective-deploy-backup.sh`'s rollback used `cp -a` unconditionally, which preserves the exact mode of the backed-up copy. For `public`-scoped manifest entries specifically — the files that land on the real served root — rollback now copies content only and normalizes the mode explicitly to `644` (or `755` if the source was executable) instead. `app`-scoped entries are untouched (still `cp -a`): a restrictive mode there can be intentional (e.g. a config-like file not meant to be world-readable), and an existing regression test locks in that this distinction is deliberate, not a gap. The same normalization now also applies to any **directory** rollback has to recreate for a `public`-scoped entry (e.g. an intermediate directory removed by a failed release): `mkdir -p` alone inherits the process umask, which under a restrictive umask (e.g. `077`) would silently leave a `0700` directory Apache cannot traverse even though the file inside it is correctly `0644` — found by an external review of PR #535 and locked in by a regression test that recreates a removed directory under `umask 077`.

**Where a restrictive permission on a nested directory could escape the drift gate:** `App\Services\Deploy\PublicAssetDriftDetector`'s `STATUS_UNSAFE_MODE` check originally inspected only the directory named directly in `config('deploy.asset_drift_scan_paths')` (e.g. `css`), on the assumption that an unreadable *nested* directory (e.g. `css/theme`) would simply make its files show up as missing. That assumption holds only when the scanning process itself lacks access — but the deploy/scanning process typically owns the files and can traverse a `0700` directory it owns even though Apache (a different user) cannot, so a nested directory like that could hash and match its files as `ok` while still being unreachable to the actual web server. The detector now walks and validates every directory in the scanned subtree, not just the one named directly in config — found by the same PR #535 review and locked in by a regression test with a safe top-level directory and an unsafe nested one.

## Staging rollback drill

`scripts/staging-rollback-drill.sh` exercises the real `php artisan deploy:asset-drift` gate against a temporary, throwaway "served root" — never production, never this checkout's own `public/` content, no database required. It mirrors every path in `config('deploy.asset_drift_scan_paths')` into a temp directory, confirms the gate passes clean, injects a real fault (a restrictive `600` permission on one release-managed file — the exact class of risk Prompt 011-014 closed), confirms the same gate now fails closed with that specific file named as `unsafe_mode`, then remediates and confirms the gate passes again. Run it locally at any time:

```bash
bash scripts/staging-rollback-drill.sh
```

**What this drill does *not* claim:** it verifies the filesystem-level gate logic in isolation. It does not exercise `deploy.sh`'s database-dependent gates (migration status, `DB_CONNECTION`) against a real MariaDB/MySQL server — that verification already exists and runs for real against an ephemeral MariaDB service in `.github/workflows/deploy-safety.yml` (wrong SHA, pending migration, diverged served root, valid state, idempotent second run). Neither this drill nor that CI workflow constitutes a real production or staging **host** verification — no code in this repository has ever connected to the actual production server. **This repository's tooling is not, by itself, evidence that a release is production-ready**; an authorized human verification against the real host remains a separate, required step before any production deploy.

## Deterministic release manifests

`scripts/git-release-manifest.sh --from <sha> --to <sha> --repo <dir>` generates the exact TSV manifest format `scripts/selective-deploy-backup.sh` consumes (`app`/`public`-scoped relative paths), from a real `git diff --no-renames --name-status -z` between two known commits. `--no-renames` is deliberate: without it, a rename can collapse an old-path-removed + new-path-added pair into a single `R` line, silently dropping the old path from the generated manifest (and from backup coverage — a rollback could never restore what used to be there). `-z` is equally deliberate: plain `--name-status` C-quotes a path containing a tab, newline or non-ASCII byte (e.g. a literal `"odd\tname.txt"` spelling), and that quoted spelling does not exist on disk — `-z` emits raw, NUL-delimited records instead, with no quoting to get wrong (found by an external review of PR #535). Any change class other than `A`/`M`/`D` (copies, type changes, unmerged paths) is refused rather than guessed at.

Every changed path under `public/` produces **both** an `app` and a `public` manifest entry (with the `public/` prefix stripped from the relative path in each), not just one: after a real deploy, that file exists as two physically separate copies — the application root's own copy (`app`-scoped) and the served webroot's copy (`public`-scoped) — and a manifest that only records one leaves the other root out of backup/rollback entirely, reproducing the exact two-root divergence this whole mechanism exists to prevent (found by the same PR #535 review; the reference scenario is `SelectiveDeployBackupScriptTest::test_rollback_restores_public_premium_css_on_both_roots_and_the_revision_token_together`). Paths outside `public/` still produce a single `app`-scoped entry, unchanged.

## Known limits — what this tooling deliberately does not cover

Documented explicitly so a future reviewer does not assume coverage that does not exist, rather than leaving it to be rediscovered as a surprise gap:

- **No maintenance-mode window.** `deploy.sh` never calls `artisan down`/`artisan up`. There is no "post-asset-copy, pre-artisan-up" phase in this script — it has no multi-step copy phase of its own; it verifies a release directory that is already fully in place (per the safety contract at the top of this document) and refreshes caches. A request already in flight during cache refresh is not held or queued by anything in this repository.
- **Filesystem ownership across users is assumed, not verified.** Every permission gate in this repository (`STATUS_UNSAFE_MODE`, the rollback normalization, this script's own checks) operates on Unix *mode* bits (0644/0755). None of it checks or changes file *ownership* (user:group). If the deploy process and Apache/PHP-FPM run as different users without a shared group and correct default ACLs, a file can be mode-0644 and still unreadable to the server — mode alone does not guarantee reachability. Confirming the production user/group topology is an operator responsibility outside this repository's tooling.
- **PHP-FPM / OPcache are not restarted or invalidated by anything here.** `php artisan optimize:clear`/`config:cache`/`route:cache`/`view:cache` run in the deploying process; if production PHP-FPM workers have OPcache enabled with a long `revalidate_freq` (or `validate_timestamps=0`), stale bytecode can persist after a successful `deploy.sh` run until PHP-FPM itself is reloaded — a step this script does not perform and does not know how to perform generically across hosting setups.
- **Cron/scheduler registration is external.** `routes/console.php` defines what should run (including `newsletter:reconfirmation-cleanup` from PR #533), but nothing in this repository registers the actual system cron entry that invokes `artisan schedule:run`. A correct deploy of new scheduled work still depends on that cPanel/crontab entry already existing and pointing at the right PHP binary and working directory — verifying that entry is an operator step, not something `deploy.sh` can check from inside the release directory.
- **Web server configuration (Apache vhost, `public_html` alias, `.htaccess`) is out of scope.** This tooling detects *content and permission* divergence between the two document roots (`docs/DEPLOYMENT.md`'s "two document roots" section above); it cannot detect a vhost misconfiguration that points Apache somewhere unexpected. `docs/CPANEL_FRONT_CONTROLLER_RUNBOOK.md` (Cantiere 15, programma Kairus 100 cantieri) documents this layer for operators — what `public_html/index.php` and `public/.htaccess` must contain and why, and the cPanel-only settings (document root, PHP version, cron, AutoSSL) that no file in this repository can verify. `php artisan deploy:verify-front-controller` (Cantiere 16, wired into `deploy.sh`) closes part of that gap: it validates that the git-tracked `public/.htaccess` of the current release still contains every critical directive — the one part of this layer that *is* verifiable in CI, since it never leaves this repository. It still cannot validate the copy actually served from `public_html`, which stays fully out of scope.
- **No real host has ever been touched by this repository's tooling or tests.** Every simulation described above (`staging-rollback-drill.sh`, the CI `deploy.sh real execution` job, this repository's PHPUnit suite) runs against temporary directories, an ephemeral CI-only MariaDB container, or SQLite. None of it is a substitute for a human running the approved procedure against the real production or a real staging host before any production deploy.
