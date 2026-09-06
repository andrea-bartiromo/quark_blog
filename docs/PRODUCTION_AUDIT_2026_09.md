# Production audit — 2026-09 (Prompt 031-039, deploy-hardening program)

Read-only audit of the production deployment/backup contract as it exists in
this repository. **No production host was accessed or could be accessed from
this session** — every finding below is derived from the repository's own
code, config, docs and CI, cross-checked for internal consistency. Where a
fact can only be confirmed by an operator with real production access, it is
listed as such rather than assumed.

## Scope and method

Reviewed: `deploy.sh`, `config/deploy.php`, `config/backup.php`,
`.env.production.example`, `docs/DEPLOYMENT.md`,
`docs/BACKUP_V2_OPERATIONS.md`, `routes/console.php`,
`app/Console/Commands/BackupDatabaseV2.php`,
`.github/workflows/deploy-safety.yml`, `.github/workflows/backup-restore.yml`.
Each claim below was checked against the actual file content on `main`
(`74483e6f2f89a4151a92305b6dd16b70f6e614e3` at audit time), not against a
description of it.

## Findings

### 1. Backup V2 enablement gates remain fully open (expected, not a regression)

`docs/BACKUP_V2_OPERATIONS.md`'s "Production facts required before
enablement" table has **19 of 19 fields still `UNKNOWN / TO CONFIRM`**. This
session has no channel to a real production host, so none of these can be
closed here — recorded as a standing precondition, not treated as resolved.

### 2. `backup:database-v2` is not wired into any automatic path — confirmed

`routes/console.php` schedules only the SQLite-only `backup:database`
(itself guarded by `config('database.default') === 'sqlite'`, per existing
test coverage). `backup:database-v2` appears nowhere in `routes/console.php`
or `deploy.sh`. This matches the documented invariant exactly.

### 3. `.env.production.example` was missing two variable families entirely — fixed

`config/deploy.php`'s `DEPLOY_SERVED_PUBLIC_ROOT` and all five
`config/backup.php` `DB_BACKUP_*` variables had **no entry at all** in
`.env.production.example` — an operator provisioning production from that
template alone would never learn these variables exist, including
`DEPLOY_SERVED_PUBLIC_ROOT`, the single opt-in switch for the entire public
asset drift gate this program built in Prompt 011-019. Fixed in this same
change: both families are now documented there (commented out, matching the
existing `MEDIA_PUBLIC_ROOT` convention), with a pointer to
`docs/DEPLOYMENT.md` / `docs/BACKUP_V2_OPERATIONS.md`.

### 4. Single-host assumption is consistent but nowhere enforced

`.env.production.example` sets `CACHE_STORE=file` and `SESSION_DRIVER=file`
(Laravel's own unset defaults are `database`). `config/backup.php`'s
`DB_BACKUP_LOCK_STORE` also defaults to `file`, and
`docs/BACKUP_V2_OPERATIONS.md` already states multi-host production "must
explicitly confirm the chosen lock store is shared by every host." This is
architecturally consistent (all three assume one filesystem, one host), but
nothing in the codebase would detect a future move to multiple app servers —
unlike the asset-drift gate, there is no automated check here. Documented as
a residual risk in the runbook (see `docs/RELEASE_RUNBOOK_V2.md`), not fixed
here: closing it would mean picking a shared cache/session/lock backend,
which is an infrastructure decision outside a docs-audit branch's scope.

### 5. `deploy.sh` on `main` does not yet include the Prompt 011-030 hardening

The permission gate, empty-file gate, release-completeness guard, and
deterministic manifest generator built in Prompt 011-030 exist only on
`fix/deploy-permissions-contract`, not yet merged (no PR-opening
authorization was given for that branch). This runbook (below) is written
against the **hardened** contract and says so explicitly, so it does not
silently describe unmerged behavior as already live.

### 6. CI restore-evidence contract matches its own documentation

`.github/workflows/backup-restore.yml`'s real steps (fresh MariaDB → seed →
verify lock → real dump → validate → restore into a disposable DB → assert
schema/FK/Unicode/timestamps → cleanup) match the 9-step contract
`docs/BACKUP_V2_OPERATIONS.md` requires for `RESTORE_VERIFIED=YES` line by
line. No drift found.

## What this audit did not do

- No production or staging host was contacted, queried, or modified.
- No credential, DSN, or secret was read, requested, or generated.
- No migration, backup, restore, newsletter send, or subscriber change was
  performed or triggered.

## Outcome

One concrete gap found and fixed (`.env.production.example` completeness,
finding 3). No P0/P1 inconsistency between repository and any verifiable
production fact was found — the standing "UNKNOWN" backup facts (finding 1)
are a known, already-documented precondition, not a new discovery, so this
does not trigger the hard-stop clause for an undocumented repo/production
inconsistency.
