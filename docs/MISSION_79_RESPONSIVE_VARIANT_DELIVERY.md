# Mission 79 — Responsive variant delivery

## Outcome

`IMPLEMENTED_HARDENING_AFTER_REVIEW`

The existing minimum system is retained, with focused hardening for three
review-proven gaps: served-root verification, variant-content validation and
post-EXIF sizing.

## Evidence

- `config/media.php` defaults to the measured 480px and 960px targets. The
  documented rendered range is approximately 197–1208px across 390, 768 and
  1440px viewports.
- `ImageService::generateResponsiveVariants()` excludes animated GIF, preserves
  alpha, corrects JPEG orientation, then derives dimensions from the oriented GD
  resource before resampling and writes validated WebP atomically.
- Generation is capability-gated by GD and `imagewebp`; no AVIF URL or file is
  advertised because no AVIF pipeline is implemented.
- `ResponsiveImageVariantService::existingVariantsFor()` checks the configured
  served media root (not merely the application copy) and accepts only readable
  WebP files whose real width matches the advertised descriptor.
- `resolveForMarkup()` includes only verified variants plus the readable
  original and derives its intrinsic dimensions from real metadata.
- `components/responsive-image.blade.php` emits `srcset` only when candidates
  exist and emits `sizes` only together with `srcset`.
- Public call sites use layout-specific `sizes`: fixed 180/240/290px thumbnails,
  33/50/62vw grids, and 1200/1240px desktop heroes with real mobile breakpoints.
- Upload paths for Media, Admin/Redazione article covers, category images and
  profile photos call `generateForUpload()`.
- `media:generate-responsive` covers legacy media, is dry-run by default and
  bounded/filterable. `--execute` enables writes; `--force` only skips the
  interactive confirmation and is not required in non-interactive execution.

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
