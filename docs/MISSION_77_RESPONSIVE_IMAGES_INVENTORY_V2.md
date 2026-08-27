# Mission 77 — Responsive Images inventory V2

## Outcome

`VERIFIED_ALREADY_PRESENT` for the principal article/archive surfaces, with
remaining hardening work ranked below. This is an audit-only mission: no
rendering code was changed.

The shared `x-responsive-image` component resolves the original dimensions and
available variants through `ResponsiveImageVariantService`. Consequently,
"intrinsic: derived" means the referenced original is readable. `srcset` is
emitted for a readable original even when it is the sole candidate; generated
responsive delivery exists only when additional validated width variants are
present. These cases must not be conflated. The component defaults to `loading=lazy` and `decoding=async`.

## Public inventory

| Surface / call site | Markup | Intrinsic size | Responsive variants / srcset | `sizes` | Loading / priority | Layout role |
| --- | --- | --- | --- | --- | --- | --- |
| Home featured hero (`home/partials/hero-trending`) | component | derived | conditional | `100vw` mobile, `62vw` desktop | eager / high | LCP hero |
| Home latest cards (`home/partials/latest-articles`) | component | derived | conditional | `100/50/33vw` | lazy / auto | card grid |
| Home category carousel (`home/partials/category-grid`) | component | derived | conditional | `100/50/33vw` | lazy / auto | carousel tile |
| Home Percorsi discovery (`home/partials/paths-discovery`) | raw IMG | declared 184×184, but committed covers are 1600×900 | none | none | lazy / auto | wide 16:9 visual; full-width below 760px |
| Article cover (`articles/partials/hero`) | component | derived | conditional | `100vw` mobile, 1240px desktop | eager / high | LCP hero |
| Article image viewer (`media/image-viewer`) | raw IMG | derived locally | none | none | lazy / auto | hidden/dialog full image |
| Article body (`ArticleBodyImageService`) | stored IMG | derived for safe local files; external unchanged | none | none | lazy / auto added at render | editorial body media |
| Continue reading (`articles/partials/continue-reading`) | component | derived | conditional | `100vw` mobile, 240px desktop | lazy / auto | continuation card |
| Related articles (`articles/partials/related-articles`) | component | derived | conditional | `100vw` mobile, `33vw` desktop | lazy / auto | related cards |
| Article author card (`articles/partials/author-card`) | raw IMG | none | none | none | lazy / auto | avatar; root disputed by #249 |
| News archive (`notizie`) | component | derived | conditional | `100vw` mobile, 290px desktop | lazy / auto | archive card |
| Search results (`ricerca`) | component | derived | conditional | 180px | lazy / auto | result thumbnail |
| Author hero avatar (`autore`) | component | derived | conditional | 104px mobile, 118px desktop | eager / auto | above-fold avatar |
| Author article list (`autore`) | component | derived | conditional | 180px | lazy / auto | result thumbnail |
| Category hero (`categoria`) | component | derived | conditional | `100vw` mobile, 1240px desktop | eager / high | conditional LCP hero |
| Category cards (`categoria`) | component | derived | conditional | `100vw` mobile, `33vw` desktop | lazy / auto | archive cards |
| Percorsi index covers (`content-clusters/index`) | component | derived | conditional | `100vw` mobile, `50vw` desktop | lazy / auto | path card cover |
| Percorso hero (`content-clusters/show`) | component | derived | conditional | `100vw` mobile, 1200px desktop | eager / high | LCP hero |
| Percorso steps (`content-clusters/show`) | component | derived | conditional | `100vw` mobile, 480px desktop | lazy / auto | step cover |
| Speciale timeline cover (`special/timeline`) | CSS background | none | none | none | CSS discovery / auto | decorative hero cover |
| Speciale timeline card/modal | raw IMG | none | none | none | lazy / auto | event and modal media |
| Speciale feature cards | CSS background | none | none | none | CSS discovery / auto | decorative card image |
| Speciale chapter opener | raw IMG | none | none | none | lazy / auto | chapter editorial media |
| Speciale hotspot | raw IMG | derived for safe local file | none | none | lazy / auto | interactive diagram |
| Homepage Turing teaser CMS background (`home/partials/turing-teaser`) | CSS background | none | none | none | CSS discovery / auto | optional CMS-driven homepage background |\n| Turing portrait (`turing/partials/hero`) | raw IMG | none | none | none | eager / auto | above-fold portrait |
| Turing legacy/Enigma surfaces | raw IMG or CSS background | generally none | none | none | mixed, mostly lazy | special-project editorial media |
| Turing article figure | raw IMG | caller optional | none | none | lazy / auto | editorial figure |

Admin and Redazione preview images are intentionally excluded from this public
inventory. The Redazione profile and admin collaborator avatars remain relevant
to Mission 76, but cannot be normalized before the production fact requested by
Mission 75.

## Priority queue

### P0 — prerequisite, not an implementation task here

- Resolve Mission 75 production evidence for `users.photo`. The article author
  card is a public broken-image risk, but changing its root now would violate the
  explicit data-safety boundary. Mission 76 owns the convergence.

### P1 — Mission 78 intrinsic sizing candidates

- Turing above-fold portrait: eager and visually important, but has no intrinsic
  dimensions. Derive exact dimensions from the committed file metadata.
- Speciale timeline card/modal images and chapter opener: lazy images without
  reserved intrinsic space. Derive metadata only for local, readable assets.
- Turing legacy images and article figures: add dimensions only where the local
  source or explicit CMS metadata proves them.
- Speciale CSS background covers and feature-card backgrounds do not accept IMG
  intrinsic attributes. Preserve aspect-ratio/min-height in CSS or convert only
  if a later mission proves a CWV/discovery benefit; do not guess dimensions.

### P2 — Mission 79 responsive delivery candidates

- Home Percorsi covers are 16:9 assets in a wide responsive slot, not 184px
  thumbnails. Derive `sizes` from `.home-path-link__visual` and its 760px
  breakpoint; never preserve the inaccurate 184px contract.
- Audit the optional homepage Turing CMS background separately: CSS backgrounds
  cannot use the IMG `srcset` contract and need evidence before conversion.
- Speciale and Turing local editorial media currently have no `srcset`. Route
  media-library-backed sources through one resolver/component after P1.
- Article body and image-viewer media intentionally lack `srcset`; variant
  mapping needs a safe source-to-disk-name contract before conversion.

### P3 — retain and recertify

Home, article cover, continuation, related, archives, author, categories and
Percorsi already share the responsive component with surface-specific `sizes`.
Mission 81 should browser-check candidate selection, fallback behavior, no
horizontal overflow, and LCP priority rather than reimplement these call sites.

## Existing regression evidence

- `ResponsiveImageVariantServiceTest` covers variant resolution.
- `PublicSurfaceResponsiveImageTest` covers news, search and author surfaces.
- `ArticleBodyImageServiceTest` covers lazy loading and safe intrinsic sizing.
- `ResponsiveImageLifecycleTest` and upload tests cover generated variants.
- Browser specs already exercise responsive public images, but this checkout
  cannot serve the application for a new runtime certification.

## Validation boundary

Static inventory and `git diff --check` were completed. PHP, Composer
dependencies and a served browser are unavailable in this checkout, so no
PHPUnit, Pint, 390/768/1440 rendering, network candidate selection, or CWV result
is claimed. CI remains an acknowledged external blocker through 2026-09-01.
