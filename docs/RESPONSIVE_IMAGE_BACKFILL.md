# Responsive image backfill runbook — `media:generate-responsive --execute`

## Status

`media:generate-responsive` is safe, idempotent, and dry-run by default. This document does not authorize running `--execute` against production. It exists so that whoever *does* run it has a checklist, not a blank slate — see [`RESPONSIVE_IMAGES_V2_SPEC.md`](RESPONSIVE_IMAGES_V2_SPEC.md) for the underlying feature design.

This runbook was written entirely against the local repository (command source, config defaults, and a local SQLite dry-run/execute using seeded fixture data). No production database was reached to produce it.

## What the command does (from its own source — `app/Console/Commands/GenerateResponsiveImages.php`)

For every `Media` row where `mime_type LIKE 'image/%'` (in `id` order, chunked), it checks which of `config('media.responsive_widths')` (default `480,960`, env `MEDIA_RESPONSIVE_WIDTHS`) are missing as `{base}-{N}w.webp` files next to the original under `public/assets/img/`. In dry-run (default, no `--execute`) it only reports what's missing. With `--execute` it generates the missing variants via `ResponsiveImageVariantService::generateForUpload()` — the exact same call the real upload paths (article/category/media-library uploads) already make for new files.

- **Never touches the original file.** Only ever writes new, additional `-{N}w.webp` files.
- **Never touches the database.** Variants have no `Media` row of their own (deliberate — see `ResponsiveImageVariantService`'s class doc: a `Media` row per variant would make orphan-cleanup scans treat variants as unreferenced and delete them).
- **GIFs are skipped** (no benefit — GIF isn't re-encoded to WebP variants).
- **A missing/corrupt source file is recorded as `Sorgente mancante/non valida` and skipped, not a failure.** Only an unexpected exception during generation counts as a technical error (`Errori tecnici`), and even then processing continues to the next Media.
- **Idempotent.** A file whose variants already exist and are valid produces zero writes on a repeat run — confirmed locally (see below): a second dry-run after `--execute` reports 0 planned.
- **Chunked** (`--chunk`, default 200): flat memory/query usage regardless of catalog size (see `GenerateResponsiveImagesPerformanceTest`).
- **No secondary-public-root risk beyond what already exists.** If `MEDIA_PUBLIC_ROOT` is configured, each variant sync failure to that secondary root is logged and skipped — it never blocks or fails the primary write (same tolerance `PublicMediaSyncService` already applies everywhere else).

## Local dry-run / execute — real numbers, not estimates

Run against a fresh local SQLite DB seeded only with `ResponsiveImageFixtureSeeder` (2 `Media` rows, deliberately stripped of their variants first to simulate "legacy media uploaded before this feature shipped"):

```
$ php artisan media:generate-responsive
Modalita: sola analisi (dry-run)
 #1 articles/covers/fixture-photo-responsive-fixture.webp -> mancano: 480w, 960w
 #2 categories/fixture-category-photo-responsive-fixture-category.webp -> mancano: 480w, 960w
| Media valutati                              | 2 |
| Con varianti mancanti (pianificati)         | 2 |

$ php artisan media:generate-responsive --execute --force
Modalita: GENERA varianti responsive
 #1 ... -> generate 2 variante/i.
 #2 ... -> generate 2 variante/i.
| Varianti scritte in totale                  | 4 |
| Errori tecnici                              | 0 |

$ php artisan media:generate-responsive   # second dry-run, confirms idempotency
| Con varianti mancanti (pianificati)         | 0 |
```

Original source: a 2400×1350 synthetic JPEG re-encoded to WebP by the upload pipeline, 147,664 bytes. Resulting variants:

| Width | Bytes | % of original |
| --- | --- | --- |
| 480w | 4,554 | ~3.1% |
| 960w | 29,506 | ~20.0% |
| (original, untouched) | 147,664 | 100% |

## Disk-space formula

For a catalog of `N` legacy images averaging `S` bytes each, with configured widths `[w1, w2, ...]` and average original width `W`:

```
extra_bytes ≈ N × S × Σ (wi / W)²
```

The `(width/original_width)²` term approximates variant size because WebP encoding cost scales roughly with pixel area, not linear width — calibrated against the real sample above: `(480/2400)² = 4.0%` vs. the observed 3.1%, `(960/2400)² = 16.0%` vs. the observed 20.0%. Close enough for a planning estimate, not a guarantee — actual compression varies with image content (the fixture is synthetic noise, which compresses worse than a typical photo, so real production numbers are plausibly *lower* than this formula predicts).

With the default two widths (`480,960`) and images whose average original width is well above 960px (typical editorial cover), a rough **+15–25% of current `assets/img/` image storage** is a reasonable planning ceiling. This has **not** been validated against the real production catalog (see "Production facts needed" below).

## Preflight

1. **GD/WebP check** — confirm the target PHP has `gd` with WebP support: `php -r "var_dump(function_exists('imagewebp'));"` must print `bool(true)`. `ImageService` already assumes this everywhere; if it's missing, uploads are already broken, not just this command.
2. **Free disk space** — confirm available space on the `public/assets/img/` filesystem exceeds the estimate above with headroom (2–3× recommended, since the estimate is unvalidated against real content).
3. **Backup** — this command never touches the database and never modifies originals, so there is no in-place data risk to back up against. If cautious, a filesystem-level copy or snapshot of `public/assets/img/` before running is still a reasonable belt-and-suspenders step, purely because it's fast and cheap, not because the command is expected to need it.
4. **Confirm `config('media.responsive_widths')`** resolves to the intended widths in the target environment (`MEDIA_RESPONSIVE_WIDTHS` env var) — the command reports "niente da fare" and exits cleanly if it's empty, which is a config problem, not a bug.

## Dry-run (always do this first, in every environment)

```
php artisan media:generate-responsive
```

Read the `Con varianti mancanti (pianificati)` count and skim the per-media lines. A very large `Sorgente mancante/non valida` count relative to the catalog signals a `disk_name`/root mismatch worth investigating *before* running `--execute` (which would just skip those same rows again, not fail).

## Execute

Start small and scoped, never the whole catalog blind on first run in a new environment:

```
php artisan media:generate-responsive --execute --limit=50
```

Then re-run the dry-run to confirm the expected count dropped by ~50, spot-check a couple of generated `-480w.webp`/`-960w.webp` files open correctly, and only then run the full catalog:

```
php artisan media:generate-responsive --execute
```

(`--force` skips the interactive confirmation — only useful for non-interactive/CI contexts; keep the prompt for a manual production run.)

## Post-verification

- Re-run the dry-run: `Con varianti mancanti (pianificati)` should be `0` (or explainable by `Sorgente mancante/non valida`/`GIF escluse` counts).
- Spot-check a handful of public pages whose cover now has variants (`/notizie`, `/articolo/{slug}`, `/percorsi/{slug}`) and confirm `srcset` is present in the rendered HTML and that images still render correctly.
- Check `Errori tecnici` is `0`; if not, the per-media error lines in the command output name the exact `Media` id/disk_name and the underlying exception message — investigate those specific files, they were skipped, not corrupted.

## Rollback / cleanup

There is no "rollback" in the database sense — nothing in the `media` table changes. To undo:

- **Remove generated variants only**: delete the `-{N}w.webp` files next to each original (the command's own naming convention makes them trivially identifiable: `{base}-(\d+)w\.webp$`). Originals are never touched, so nothing else is affected.
- **Disable the feature going forward** without touching what's already generated: set `MEDIA_RESPONSIVE_WIDTHS=""` — `<x-responsive-image>` and `ResponsiveImageVariantService::resolveForMarkup()` already handle the "some/no variants exist" case gracefully (this is the same legacy-fallback path exercised by `ResponsiveImageVariantServiceTest`), so already-generated variants keep being served, no new ones get planned, and nothing breaks either way.

## Abort conditions

Stop and investigate (don't push through with `--force`/a larger `--limit`) if:

- `Errori tecnici` is non-zero on a `--limit`-scoped test run — find and fix the underlying cause (missing GD extension, permission issue, corrupt source) before scaling up.
- Free disk space during a run drops below the safety margin from preflight — stop, don't let the filesystem fill.
- `Sorgente mancante/non valida` is unexpectedly high — this usually means `disk_name` values in the `media` table don't actually correspond to files under `public/assets/img/`, which is a data-integrity question worth resolving first (see the related audit in issue #249 on `User->photo`'s `assets/img/` vs. `storage/` root mismatch — the same class of problem could exist elsewhere in the `media` table and is worth ruling out before a full-catalog run).

## Production facts needed before a production run

| Fact | Required value |
| --- | --- |
| PRODUCTION_MEDIA_ROW_COUNT (images only) | UNKNOWN / TO CONFIRM |
| PRODUCTION_ASSETS_IMG_DISK_USAGE | UNKNOWN / TO CONFIRM |
| PRODUCTION_FREE_DISK_SPACE | UNKNOWN / TO CONFIRM |
| PRODUCTION_PHP_GD_WEBP_SUPPORT | UNKNOWN / TO CONFIRM |
| PRODUCTION_MEDIA_RESPONSIVE_WIDTHS (env override, if any) | UNKNOWN / TO CONFIRM |
| PRODUCTION_MEDIA_PUBLIC_ROOT (secondary sync root, if configured) | UNKNOWN / TO CONFIRM |
| ESTIMATED_RUNTIME (proportional to PRODUCTION_MEDIA_ROW_COUNT; no production timing exists yet) | UNKNOWN / TO CONFIRM |
| MAINTENANCE_WINDOW (only relevant if runtime is long enough to matter — the command does not lock or block reads while running) | UNKNOWN / TO CONFIRM |

Do not put passwords, DSNs, APP_KEY, or other secrets in this document.

---
_Written as part of an automated audit pass (Frontend Quality / Responsive Images S2 follow-up). No code changes accompany this document; the local dry-run/execute above ran only against a fresh, disposable local SQLite database with seeded fixture data — never against production._
