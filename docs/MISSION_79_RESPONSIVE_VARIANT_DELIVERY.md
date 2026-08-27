# Mission 79 — Responsive variant delivery

## Outcome

`VERIFIED_ALREADY_PRESENT`

Current `main` already implements the minimum responsive-variant system. No
rendering or media-pipeline change is justified by this audit.

## Evidence

- `config/media.php` defaults to the measured 480px and 960px targets. The
  documented rendered range is approximately 197–1208px across 390, 768 and
  1440px viewports.
- `ImageService::generateResponsiveVariants()` skips targets greater than or
  equal to the source width, excludes animated GIF, preserves alpha, corrects
  JPEG orientation and writes validated WebP atomically.
- Generation is capability-gated by GD and `imagewebp`; no AVIF URL or file is
  advertised because no AVIF pipeline is implemented.
- `ResponsiveImageVariantService::existingVariantsFor()` checks the real
  filesystem. Missing or partial variants never become fictional candidates.
- `resolveForMarkup()` includes only verified variants plus the readable
  original and derives its intrinsic dimensions from real metadata.
- `components/responsive-image.blade.php` emits `srcset` only when candidates
  exist and emits `sizes` only together with `srcset`.
- Public call sites use layout-specific `sizes`: fixed 180/240/290px thumbnails,
  33/50/62vw grids, and 1200/1240px desktop heroes with real mobile breakpoints.
- Upload paths for Media, Admin/Redazione article covers, category images and
  profile photos call `generateForUpload()`.
- `media:generate-responsive` covers legacy media, is dry-run by default,
  bounded/filterable and requires explicit execution/force flags for writes.

## Regression coverage

- `ResponsiveImageVariantServiceTest`: generation, real candidate resolution,
  missing/partial fallback and secondary-root sync.
- `PublicSurfaceResponsiveImageTest`: `srcset` and coherent `sizes` on public
  news, search and author surfaces.
- `ContentClusters/PathHeroResponsiveImageTest`: Percorso hero contract.
- `Console/GenerateResponsiveImagesCommandTest`: dry-run, execution,
  idempotence, bounds, GIF and missing-source behavior.
- `Uploads/ResponsiveImageLifecycleTest`: upload/replace/delete integration.

## Safety and runtime boundary

No production backfill was run and no variants were generated in this mission.
PHP, Composer dependencies and a served browser are unavailable in this
checkout, so PHPUnit, Pint and browser candidate selection are not claimed.
CI remains an acknowledged external blocker through 2026-09-01.
