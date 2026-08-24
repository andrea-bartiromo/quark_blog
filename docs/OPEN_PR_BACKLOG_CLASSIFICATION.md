# Open PR Backlog Classification

Mission 41 of the autonomous batch (Phase G). Snapshot taken after re-fetching
every open PR against `main` at sha `72bb2b7` (2026-08-24). Classifies each
open PR into: still useful; superseded; duplicate; stale/conflicting;
requires product decision. Closures below were made only with strong,
citable evidence (the superseding PR's own text, or a verbatim-recovered
diff now merged) — no PR was closed on inference alone.

## Closed this mission (superseded, strong evidence)

| PR | Title | Why closed |
|---|---|---|
| #292 | feat(radar): add first explainable editorial opportunities | Verbatim-recovered and merged via #362 (Mission 35). All 8 of its own tests passed unmodified. |
| #317 | fix(discovery): recover public-prefix convergence | Verbatim-recovered and merged via #367 (Mission 40). One real gap found during recovery (missing `Article::secondaryCategories()` relation) and fixed; all 8 tests then passed. |
| #308 | fix(discovery): count only publicly reachable Percorsi | Its own PR body names #317 as its replacement ("Batch 06 Mission 05 replacement for #308"); #317 is now merged via #367. |
| #293 | feat(discovery): add real article reachability audit | Its discovery-path logic (any `is_active` Percorso membership = a path) is exactly the bug #308/#317 fixed; #317's corrected version is now merged via #367. |
| #261 | feat: minimal revision history for articles (post-save safety net) | Verbatim-recovered and merged via #369 (Mission 42). Two missed fix-up commits found and cherry-picked during recovery (a mass-assignment bug: `published_at` missing from `ArticleRevision::$fillable`), then verified end-to-end with a real browser smoke test. |
| #260 | feat: local autosave and draft recovery for the article editor | Verbatim-recovered and merged via #370 (Mission 42). Cherry-picked cleanly onto current `main`, including the just-merged #369 — zero conflicts. Verified end-to-end with a real browser smoke test (draft write, recovery banner, restore, post-save cleanup). |
| #254 | Internal linking: related-articles no longer repeats Percorso prev/next | Verbatim-recovered and merged via #371 (Mission 43). Confirmed the underlying duplication bug was still present on current `main` before recovering; verified with a real seeded Percorso in a browser. |
| #258 | fix: make public category navigation DB-first, not config-snapshot-first | Verbatim-recovered and merged via #372 (Mission 43). One real conflict resolved in a shared query-budget test (both `main` and the stale PR had independently bumped the same ceiling for unrelated reasons); two more query-budget tests elsewhere in the suite needed the identical adjustment, found via the full suite and fixed. Verified end-to-end with a real browser check. |

## Still open — classification

### Still useful, requires product decision

| PR | Title | Base | Notes |
|---|---|---|---|
| #345 | test(content-graph): add real-browser admin smoke test | `main` | Content Graph itself is fully merged and live. This PR only adds a Playwright spec for it, left draft because the authoring session couldn't get a definitive pass signal. Genuinely still useful — worth a follow-up mission to actually execute it with this environment's working Playwright setup and merge if green. |
| #321 | feat(percorsi): surface scheduled Percorsi on the admin Article Calendar | `main` | Verified: `ArticleController::calendar()` on current `main` has zero `ContentCluster` references — this feature is **not** yet landed anywhere else. `ContentCluster.publish_at` scheduling itself is already on `main` (from an earlier, separate mission), so this PR's premise is still valid. Left draft in its own text pending "the public Percorso page enforcement" it explicitly says is out of scope. Requires a product decision on whether to land the admin-only calendar view now or wait for full public scheduling enforcement. |
| #261 | feat: minimal revision history for articles (post-save safety net) | `main` | Not a draft. In scope for Mission 42 (Revision History / Autosave Foundations Audit) — deliberately not touched here to avoid pre-empting that mission's own compatibility audit. |
| #260 | feat: local autosave and draft recovery for the article editor | `main` | Not a draft. Same — in scope for Mission 42. |
| #258 | fix: make public category navigation DB-first, not config-snapshot-first | `main` | Not a draft. In scope for Mission 43 (Related Articles / Category Source-Debt Audit) — already identified in this batch's Mission 26 as the real blocker deferred to Mission 43. |
| #254 | Internal linking: related-articles no longer repeats Percorso prev/next | `main` | Not a draft. Also in scope for Mission 43. |
| #299 | chore(performance): add reproducible CWV baseline runner | `main` | Draft. Explicitly measurement-only, no BEFORE evidence recorded (author's session had no working browser). In scope for Mission 45 (Performance/CWV Foundation Audit). |

## Closed this mission — deliberately not shipped (Mission 44)

| PR | Title | Why closed without merging |
|---|---|---|
| #297 | feat(newsletter): add privacy-safe conversion instrumentation boundary | Investigated in full. Its own diff against its real base is genuinely tiny and clean (2 files, 125 lines: `NewsletterConversionInstrumentation` + its unit test) — a pure, side-effect-free event-shape validator (closed enums for event/placement/error_class, no PII, no persistence). The PR's own body says wiring was deferred pending two things: (1) Communication ownership settling, and (2) "an appropriate sink" being confirmed. #278 and #298 (both named as the ownership blockers) are now merged — but they resolved *admin campaign* freeze/test-send, a different subsystem from the *public* newsletter signup CTA (`article_band`/`popup` placements) this class targets. No sink for site-side, non-email conversion events exists anywhere on `main` today — `NewsletterTrackingController` is pixel/click tracking for *sent emails* (stores email + IP hash, a different privacy model entirely), not a fit. The class has **zero call sites** on `main` and would still have zero call sites if merged as-is: no controller, no view, nothing invokes it. Merging an unwired, uncalled class adds dead code without adding regression coverage of anything real. A working precedent for this exact pattern (closed-enum event + DB sink, no PII) already exists in `ContinuationAnalyticsService`/`ArticleContinuationEvent` — a future mission that actually wants this feature should build the sink and wire the real CTAs in the same PR, using that as the template, rather than merging a contract with nothing behind it. Per the batch's own priority rule ("a mission that discovers a design is unsafe [or premature] and deliberately does not ship it is successful"), this PR is closed without merging. |

None of the remaining 7 open PRs were found to be simple duplicates of each
other or of anything already on `main` — each targets a genuinely distinct
concern. All predate this session's own recent merges into `main` and will
need a fresh compatibility check (same pattern used for #292/#317: verify
every dependency's current shape before trusting the diff) before any of
them can safely land — that re-verification is each remaining mission's own
job, not repeated here.

## Method

For each closed PR, the evidence was: (a) the exact file diff was already
read in full during this session's own recovery work (#292 → Mission 35,
#317 → Mission 40), and its tests were actually executed against current
`main`, not merely reviewed; or (b) the PR's own author-written body
explicitly names another PR as its replacement. No PR was closed based on
title similarity or staleness alone.
