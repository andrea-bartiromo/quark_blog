# Production deployment

Kairus production uses MariaDB/MySQL. SQLite remains intentional for local development and deterministic automated tests; it is not the production backup model.

## Safety contract

`deploy.sh` is a release verification wrapper, not a provisioning script and not Backup V2.

Run it only after the intended release files and the real production `.env` are already present:

```bash
bash deploy.sh <expected-40-character-git-sha>
```

The wrapper fails unless:

- the current directory looks like a complete Laravel release (`artisan`, `composer.json` and `.git` are all present — checked before anything else runs, so an incomplete copy or the wrong working directory fails with a clear reason instead of a confusing PHP or git error later);
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

**Where a restrictive permission could reach the served root:** `scripts/selective-deploy-backup.sh`'s rollback used `cp -a` unconditionally, which preserves the exact mode of the backed-up copy. For `public`-scoped manifest entries specifically — the files that land on the real served root — rollback now copies content only and normalizes the mode explicitly to `644` (or `755` if the source was executable) instead. `app`-scoped entries are untouched (still `cp -a`): a restrictive mode there can be intentional (e.g. a config-like file not meant to be world-readable), and an existing regression test locks in that this distinction is deliberate, not a gap.

## Deterministic release manifests

`scripts/git-release-manifest.sh --from <sha> --to <sha> --repo <dir>` generates the exact TSV manifest format `scripts/selective-deploy-backup.sh` consumes (`app`/`public`-scoped relative paths), from a real `git diff --no-renames --name-status` between two known commits. `--no-renames` is deliberate: without it, a rename can collapse an old-path-removed + new-path-added pair into a single `R` line, silently dropping the old path from the generated manifest (and from backup coverage — a rollback could never restore what used to be there). Any change class other than `A`/`M`/`D` (copies, type changes, unmerged paths) is refused rather than guessed at.
