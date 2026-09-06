# Kairus release runbook v2 (Prompt 040, deploy-hardening program)

Consolidated, step-by-step operator procedure for a production release. It
supersedes nothing — `docs/DEPLOYMENT.md` remains the authoritative
reference for *why* each contract exists (incident history, architecture);
this document is the *ordered checklist* for actually running a release,
pointing back to that detail rather than repeating it.

**Status flag — read this first:** the "hardened" steps below (release-
completeness guard, permission/empty-file gates, deterministic manifest
generator, staging drill) exist on branch `fix/deploy-permissions-contract`
and are **not yet on `main`** at the time this runbook was written (see
`docs/PRODUCTION_AUDIT_2026_09.md`, finding 5). Steps are marked
**[hardened branch only]** where this applies. Until that branch merges,
`deploy.sh` on `main` still enforces every gate except the permission/
empty-file/release-completeness ones.

This runbook does not grant authority to deploy, migrate, back up, restore,
send newsletters, or modify subscribers. Those remain separately authorized
actions per operator policy.

## 0. Preconditions (verify once per release, not per gate)

- [ ] You have the exact 40-character target Git SHA.
- [ ] The current production `REVISION` is recorded as the rollback target
      (see `docs/DEPLOYMENT.md` § Rollback information).
- [ ] If the release includes migrations: a verified `backup:database-v2`
      dump exists for this exact pre-release state (see
      `docs/BACKUP_V2_OPERATIONS.md`). `deploy.sh` fails closed on pending
      migrations without one — this is not optional.
- [ ] Production `.env` reflects `.env.production.example` (all variables
      present; secrets filled in only on the server, never in this repo).

## 1. Release directory verification

- [ ] **[hardened branch only]** Confirm `artisan`, `composer.json`, `.git`
      are present in the release directory (`deploy.sh` now checks this
      itself, first, before anything else — see `docs/DEPLOYMENT.md` §
      Safety contract).
- [ ] Confirm the working tree is the exact target SHA and clean (`deploy.sh`
      verifies this itself via `git rev-parse HEAD` and
      `git diff --quiet`; this line item is for the operator's own sanity
      check before running it).

## 2. Manifest and backup (only when using selective rollback)

Skip this section entirely if the release does not warrant a selective
rollback point (e.g. a routine deploy already covered by the standing
backup/rollback policy).

- [ ] **[hardened branch only]** Generate the manifest deterministically:
      `bash scripts/git-release-manifest.sh --from <previous-sha> --to <target-sha> --repo <path>`
      — never hand-write it; a hand-written manifest cannot make the
      "no implicit renames" guarantee this script provides.
- [ ] Run `scripts/selective-deploy-backup.sh backup` with that manifest
      **before** the release lands, against the current (pre-release)
      state of both the app root and the served public root.
- [ ] Confirm the backup directory's `.complete` marker exists before
      proceeding — `selective-deploy-backup.sh rollback` refuses an
      incomplete backup, and so should the operator.

## 3. Run `deploy.sh`

```bash
bash deploy.sh <expected-40-character-git-sha>
```

Gates enforced, in order (see `docs/release-checklist.json` for the
machine-readable version of this list, **[hardened branch only]**):

1. **[hardened branch only]** release directory is complete
2. revision matches expected SHA
3. tracked release files are clean
4. `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` set
5. `DB_CONNECTION` is `mysql`/`mariadb`
6. no pending migrations (without a verified backup)
7. public asset drift gate — content, **[hardened branch only]**
   permissions, and non-emptiness across both document roots

Stop immediately on any failure. Do not re-run with `--force` or any
equivalent — there is none, by design.

## 4. Public asset sync (external step, still manual)

If `DEPLOY_SERVED_PUBLIC_ROOT` is configured, the drift gate in step 3 only
**reports** divergence between the app root and the served root — nothing
in this repository copies files between them automatically (see
`docs/DEPLOYMENT.md` § Public asset deployment: two document roots). If the
gate fails, synchronize the two roots manually, then re-run `deploy.sh`.

## 5. Post-deploy verification

- [ ] `REVISION` and `DEPLOY_INFO` were written with the target SHA
      (`deploy.sh` only writes these after every gate above passes).
- [ ] Spot-check the live site for the specific asset(s) this release
      changed (the drift gate covers CSS/JS/icons/manifest/robots.txt —
      not `public/assets/img` or `public/images`, which have their own
      sync mechanisms; see `docs/DEPLOYMENT.md`).

## 6. Rollback (if needed)

- [ ] `scripts/selective-deploy-backup.sh rollback --backup-dir <dir> ...`
      restores exactly the manifest's files on both roots, verifying each
      backed-up file's SHA-256 against what was recorded at backup time
      before touching anything.
- [ ] **[hardened branch only]** Public-scoped restored files are
      permission-normalized to `644`/`755`; app-scoped files keep whatever
      mode they had at backup time (this is deliberate — see
      `docs/DEPLOYMENT.md` § Public asset permission contract).
- [ ] Database rollback is **never** implied by this. A migration-bearing
      release requires its own reviewed restore plan
      (`docs/BACKUP_V2_OPERATIONS.md` § Restore is operator-controlled).

## 7. Drill this procedure without touching production

**[hardened branch only]** `bash scripts/staging-rollback-drill.sh` exercises
the real asset-drift gate end-to-end (clean → fault injected → fault
detected → remediated → clean) against a disposable temporary directory.
Run it after any change to the deploy contract itself, before trusting it
against a real release.

## What this runbook is not

Not a substitute for an authorized production or staging host verification
(none has been performed by any session working on this repository — see
`docs/PRODUCTION_AUDIT_2026_09.md`). Not an approval to deploy, migrate,
back up, restore, or send anything — each remains a separately authorized
operator action.
