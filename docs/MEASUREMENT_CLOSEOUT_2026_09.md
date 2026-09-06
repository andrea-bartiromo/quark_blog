# Measurement Closeout — 2026-09 (Prompt 041-060, 150-prompt program)

Cross-cutting audit of analytics, consent, Core Web Vitals, accessibility
and SEO across the 7 canonical public surfaces already established by
`docs/PUBLIC_SURFACES_QA_MATRIX.md`:

| # | Surface | Route |
|---|---|---|
| 1 | Home | `/` |
| 2 | Percorsi (indice) | `/percorsi` |
| 3 | Percorso (dettaglio) | `/percorsi/{slug}` |
| 4 | Articolo | `/articolo/{slug}` |
| 5 | Notizie | `/notizie` |
| 6 | Categoria | `/categoria/{slug}` |
| 7 | Ricerca | `/ricerca` |

Method: verified current `main` code directly (file:line), not the
documented claims about it — existing docs (`docs/ANALYTICS_HYGIENE.md`,
`docs/SEO_METADATA_QUALITY_AUDIT.md`, the CWV baseline/mission docs) were
read first as the *claimed* state, then checked against the real templates
and controllers.

## 1. Analytics + consent — clean, no findings

All 7 surfaces `@extends('layouts.app')`, which includes
`layouts/partials/head.blade.php` (the consent-gated `gtag` snippet) and
`components/cookie-bar.blade.php` (the GDPR banner) unconditionally, with
no surface-specific override. `AnalyticsExclusionService::shouldLoadAnalytics()`
correctly gates the script on measurement-id presence, `APP_ENV=production`,
and the exclusion cookie — verified from the actual method body, not its
docblock.

No custom analytics event exists anywhere in the 7 surfaces beyond the
shared layout's `gtag`/`dataLayer` calls. Google Consent Mode v2 defaults
`analytics_storage`/`ad_storage`/`ad_user_data`/`ad_personalization` to
`denied` before any `gtag('config', ...)` runs; the cookie bar is the only
code path that ever calls `gtag('consent', 'update', ...)`. Searched for a
consent-bypassing fallback (`google-analytics.com`, `fbq(`, `new Image()`,
`sendBeacon`) — none found. Second Read continuation tracking
(`ContinuationAnalyticsService`) is entirely server-side, stores only
integer article IDs, never touches `gtag`/`dataLayer`, and never sends
anything to Google. `ricerca.blade.php` has no analytics call of any kind —
the search query is never pushed to any tracking call. **No GDPR
consent-bypass or PII-leak risk found.**

## 2. SEO — one real gap found and fixed

Per-surface title/description/canonical are all genuinely dynamic (not
generic stubs) except Home's description, which is an acceptable
homepage-level fallback. JSON-LD is accurate to real content on every
surface that has it (Articolo: `NewsArticle`+`BreadcrumbList`; Percorso:
`CollectionPage`+`ItemList` of the real sequenced articles; Home:
`Organization`+`WebSite`; Notizie/Categoria: shared `CollectionPage`
partial, correctly self-referencing per page). `public/robots.txt` is sane
(explicit `Sitemap:`, blocks only `/admin/`, `/redazione/`, `/storage/`,
`/api/`, deliberately leaves `/ricerca` crawlable so its `noindex,follow`
meta tag — not a `Disallow` — is the actual enforcement, confirmed present
in `ricerca.blade.php`).

**Gap found:** `content-clusters/index.blade.php` (Percorsi indice) emits
`rel="prev"`/`rel="next"` for pagination; **Notizie and Categoria, paginated
identically, did not.** Fixed in this same change — both views now emit the
same pagination link relations, using each view's own already-established
canonical-URL convention (page 1 never carries `?page=1`; `rel=prev` from
page 2 points back to the bare route, not an explicit `page=1`). Covered by
a new test, `CollectionPageStructuredDataTest::test_notizie_and_categoria_expose_rel_prev_next_across_pages`.

## 3. Core Web Vitals — baseline still representative

`docs/PERFORMANCE_BASELINE.md`'s recorded baseline commit is not an
ancestor of current `main` (lives on `feat/kairus-public-performance-cleanup`);
diffing that snapshot against current `main` on the 7 view files +
`editorial-system.css` + `head.blade.php` shows exactly one change since:
the `<x-article.primary-sources>` addition to Articolo (PR #532). That
component is text-only, reuses an existing CSS class, adds no script, and
sits below the article body — not a plausible LCP/CLS regressor. No new
render-blocking resource was added to any surface's `<head>`. Responsive
image usage (confirmed via `x-responsive-image` across home/categoria/
notizie/articolo partials) and hero `fetchpriority="high"` on LCP
candidates both match what `docs/PERFORMANCE_ASSETS_VERIFICATION.md`
already claims. **Verdict: the existing baseline remains valid; no new
Lighthouse run was warranted by this audit.**

## 4. Accessibility — one real regression found and fixed

`docs/PUBLIC_SURFACES_QA_MATRIX.md` (commit `fc1ced1`, 2026-09-05) predates
PR #532 (`eb2cad1`, 2026-09-06), the only commit since that audit to touch
any of the 7 surfaces' own templates. That PR added
`resources/views/components/article/primary-sources.blade.php` to the
Articolo surface, between the article body and `path-continuation`/
`continue-reading`/`newsletter-band`/`related-articles` — all direct
siblings inside `<main>`, all using `<h2>`. The new component used `<h3>`,
the only one of these five sibling sections to do so.

Because the article body's own `<h2>` (in `articles/partials/body.blade.php`)
is conditional on the article's actual content (a bold-first-line
paragraph, or a legacy free-text "Fonti" block — neither guaranteed), an
article with structured `primary_sources` but neither of those two content
shapes would render `<h1>` (hero) → `<h3>` (Fonti primarie) with **no `<h2>`
in between** — a real, content-dependent heading-level skip, not a
hypothetical one.

**Fixed:** `primary-sources.blade.php`'s heading changed from `<h3>` to
`<h2>`, matching its four sibling sections unconditionally regardless of
article content. Locked in by a new test,
`ArticlePublicPrimarySourcesTest::test_primary_sources_heading_is_an_h2_matching_its_sibling_sections`,
which asserts the literal tag, not just the visible text (the previous test
suite only checked for the text "Fonti primarie", which passed identically
whether the tag was `<h2>` or `<h3>` — the actual level was never verified
before this).

## What this closeout did not do

- Did not re-run Lighthouse/CWV measurement (baseline confirmed still
  representative by diffing template changes, not by re-measuring).
- Did not touch `autore`, `turing`, or any surface outside the fixed
  7-surface scope (same isolation boundary as
  `KairusEditorialFoundationsIsolationTest`).
- Did not modify any published or scheduled article's content — both fixes
  are template/component-level, applying identically to all articles
  without editing any article row.
- Did not touch analytics/consent code — no gap was found there.

## Outcome

Two real, concrete findings, both fixed with regression tests: a missing
SEO pagination signal (Notizie/Categoria) and a real accessibility
heading-level regression (Articolo). No P0/P1 inconsistency requiring a
hard stop was found; analytics/consent audited clean with no changes
needed.
