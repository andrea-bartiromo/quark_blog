# Timeline test report — /turing
- Context: Decision #003 — evolve the Timeline from a flat chronological sequence into temporal chapters
  (`Cover → (Chapter Opener → Events)` repeated), via a new reusable `<x-special.chapter-opener>` component.
  Events keep rendering through the existing `<x-special.timeline>` component, unchanged in markup.
- Route: /turing
- Branch / PR: claude/refactor-image-service-py7qtz / #37 (opened against `main`)
- Commit: `96487ae` — follow-up on top of `7caac8c` (`feat(timeline): introduce narrative chapters and reusable
  chapter opener`), addressing CodeRabbit review feedback on PR #37 (predicate fix, chapter-opener ids, Project
  Book wording/roadmap, embedded evidence)
- Viewports: 1440 / 820 / 390

## Results
| # | Check | Result |
|---|-------|--------|
| T1 | Chapter opener | PASS |
| T2 | Component rendering + events area (§10.4) | PASS |
| T3 | Responsive + no overflow | PASS |
| T4 | Semantics & keyboard accessibility | PASS |
| T5 | Decision #001 + regressions | PASS |
| T6 | Reusability + design tokens | PASS |
| T7 | Chapter → events order (Decision #003) | PASS |

## Evidence

### T1/T7 — DOM order (`.sp-timeline`, `.sp-chapter` in source order)
```text
1. section.sp-timeline#timeline                  cover=yes  list=no   (Cover — "Una vita che attraversa il Novecento")
2. section.sp-chapter#timeline-chapter-opener-1  media=yes  "1912–1939 — La formazione di un pensiero computazionale"
3. section.sp-timeline#timeline-chapter-1        cover=no   list=yes  3 events (1912, 1936, 1938–1939)
4. section.sp-chapter#timeline-chapter-opener-2  media=yes  "1939–1946 — La guerra e il calcolo applicato"
5. section.sp-timeline#timeline-chapter-2        cover=no   list=yes  2 events (1939–1945, 1945–1946)
6. section.sp-chapter#timeline-chapter-opener-3  media=yes  "1950–2013 — Il pensiero delle macchine e l'eredità"
7. section.sp-timeline#timeline-chapter-3        cover=no   list=yes  4 events (1950, 1952, 1954, 2009–2013)
```
Confirms `Cover → (Chapter Opener → Events) × 3`, exactly as required. Total event count across chapters = 9,
matching the page's full event set (no event dropped/duplicated by the grouping). All 7 sections now carry an
explicit, unique `id` (the Chapter Opener sections previously had none — fixed by passing `:id` from the partial,
forwarded automatically to `<x-special.chapter-opener>`'s root element via its `$attributes` bag) — verified via
`document.querySelectorAll('[id]')`, no DOM id collisions anywhere on the page.

### T1 — Chapter Opener image is bounded, not full-bleed
Measured via `getBoundingClientRect()` at 1440px: chapter image width = 300px vs. section width = 1440px
(~21%), confirmed `object-fit: cover` on a fixed-height (`220px` desktop / `200px` ≤720px) contained `<img>`,
never a CSS section background. `alt` text present and descriptive for every chapter image.

Regression found and fixed during verification: the first render of `.sp-chapter` had no background of its own,
so it inherited the dark Legacy section's background immediately above it, making the dark-ink title/intro text
invisible (contrast failure). Fixed by giving `.sp-chapter` its own `background: var(--sp-surface)` — screenshots
below confirm the fix (title/period/intro fully legible on all three chapters).

Screenshots (desktop 1440px, `docs/evidence/timeline-chapters/`):

**Cover** (`#timeline`, Decision #001, unchanged):
![Cover](docs/evidence/timeline-chapters/cover.png)

**Chapter 1** (`#timeline-chapter-opener-1`, "La formazione di un pensiero computazionale", 1912–1939):
![Chapter 1](docs/evidence/timeline-chapters/chapter-1.png)

**Chapter 2** (`#timeline-chapter-opener-2`, "La guerra e il calcolo applicato", 1939–1946):
![Chapter 2](docs/evidence/timeline-chapters/chapter-2.png)

**Chapter 3** (`#timeline-chapter-opener-3`, "Il pensiero delle macchine e l'eredità", 1950–2013):
![Chapter 3](docs/evidence/timeline-chapters/chapter-3.png)

### T3 — Responsive / no overflow
`document.documentElement.scrollWidth <= window.innerWidth` asserted true at all three widths:
- 1440: scrollWidth=1440, innerWidth=1440 → no overflow.
- 820: scrollWidth=820, innerWidth=820 → no overflow.
- 390: scrollWidth=390, innerWidth=390 → no overflow.
Mobile (390px) screenshot confirms the Chapter Opener stacks (image above text) via the `@media (max-width:
720px)` rule; desktop/tablet keep the side-by-side layout.

### T4 — Semantics & accessibility
- Cover section (`#timeline`) exposes `aria-labelledby` → `<h2 id>` (has a `title`); the three per-chapter
  events sections correctly omit both (no `title` passed to those calls) — matches the component's documented,
  pre-existing behavior.
- Contrast (computed styles, chapter background `rgb(238,242,247)`):
  - title `rgb(15,23,42)` → ratio 15.88:1 (≥4.5 required — PASS)
  - intro `rgb(71,85,105)` → ratio 6.74:1 (≥4.5 required — PASS)
  - period (bold, uppercase, small) `rgb(15,118,110)` → ratio 4.87:1 (≥3 required for this text size — PASS)
- Keyboard traversal: tagged all focusable elements with a unique per-element marker and tabbed 60 times;
  0 instances of the same focusable element receiving focus twice in a row → no keyboard trap introduced.
  `:focus-visible` styling on linkable event cards (`.sp-timeline__card--link`) is unchanged/inherited from the
  existing `<x-special.timeline>` component (not touched by this PR).

### T5 — Decision #001 + regressions
- Cover screenshot (desktop) confirms the bounded photographic header from Decision #001 is unchanged in
  appearance (same title, same background image, same rounded/bounded treatment).
- `git diff --name-status origin/main HEAD` (full PR diff, both commits `7caac8c` and `96487ae`) — only the
  expected files changed:
  ```text
  M	.agents/skills/testing-special-project-timeline/SKILL.md
  M	app/Http/Controllers/TuringPageController.php
  A	docs/PROJECT_BOOK.md
  A	docs/evidence/timeline-chapters/chapter-1.png
  A	docs/evidence/timeline-chapters/chapter-2.png
  A	docs/evidence/timeline-chapters/chapter-3.png
  A	docs/evidence/timeline-chapters/cover.png
  M	public/css/special-project.css
  A	resources/views/components/special/chapter-opener.blade.php
  M	resources/views/components/special/timeline.blade.php
  M	resources/views/turing/partials/timeline.blade.php
  A	test-report.md
  ```
  Hero, Legacy, editorial blocks, Intro, final card partials: **no changes**.
- Review-round fix: `TuringPageController::isRenderableTimelineEvent()` previously accepted `image`/`url`-only
  items as a "valid" CMS timeline override (it mirrored `isRenderableEditorialBlock`'s broader predicate).
  `<x-special.timeline>` itself only renders an event with `year`/`title`/`text`, so an image/url-only CMS
  override could have disabled the default chapters while rendering zero events. Narrowed the predicate to
  `year`/`title`/`text` to match the component's own filter exactly — re-verified with `php artisan test`
  (159/159 still green; no existing fixture relies on an image/url-only event).
- `resources/views/components/special/timeline.blade.php` change is the minimal, strictly-necessary tweak:
  splitting the single `@if($items->isNotEmpty())` guard into two independent conditionals (cover-block,
  events-block) so the same component can be invoked cover-only (the page's overall Cover) or events-only (each
  chapter), without duplicating its markup anywhere. No existing prop, default, or markup for the
  already-supported "cover + events together" case changed.
- `git diff --check` — clean (no whitespace errors).

### T6 — Reusability + design tokens
- `<x-special.chapter-opener>` API: `period, title, intro, image, alt` — no Turing-specific literal anywhere in
  the component or in `special-project.css`; all Turing content (period/title/intro/image per chapter) lives in
  `TuringPageController::defaultTimelineChapters()`, a page-specific controller method.
  A future Special Project defines its own chapters array and reuses the same two components unchanged.
- All new CSS (`.sp-chapter*`) is built on existing `--sp-*` tokens (`--sp-surface`, `--sp-accent`,
  `--sp-ink-strong`, `--sp-ink-soft`, `--sp-radius-lg`, `--sp-card-shadow`, `--font-display`) — no new
  hard-coded color/spacing literals besides the two new fixed image heights (`220px`/`200px`, matching the
  existing Cover's `--sp-cover-height` pattern of a controlled, bounded photo height).
- CMS-driven timeline overrides (flat `timeline` array, no explicit chapters) intentionally keep the legacy
  single-`<x-special.timeline>` rendering (Cover + all events together) — this only affects the *default*
  hardcoded Turing dataset, so no CMS-authored content or existing admin flow changes behavior.

## Automated tests
```text
php artisan test
{"tool":"phpunit","result":"passed","tests":159,"passed":159,"assertions":665,"duration_ms":4695}
```
`php -l app/Http/Controllers/TuringPageController.php` — no syntax errors. `git diff --check` — clean.

## Issues
- Blocking: none.
- Non-blocking: none. (The contrast/background regression described under T1 was found and fixed during this
  same verification pass, before opening the PR — not left as an open issue.)

## Final judgment
Merge Ready
