---
name: testing-special-project-timeline
description: End-to-end test a Quark Blog "Special Project" page and its reusable <x-special.timeline> component. Use before merging any Special Project PR that touches the Timeline to verify UI/UX vs the Project Book, responsive layout, accessibility, reusability, and regressions.
---

# Testing the Special Project Timeline (Quark Blog)

Quark Blog is a Laravel + Blade editorial platform. A **Special Project** (first prototype: `/turing`) is a
narrative page built from partials (Hero, Intro, editorial blocks, Legacy, Timeline, final CTA). The Timeline
is the reusable component `resources/views/components/special/timeline.blade.php`
(`<x-special.timeline :events kicker title :background id>`), styled by `public/css/special-project.css`
with `--sp-*` design tokens layered on the global brand tokens in `style.css`.

Since Decision #003, the Timeline area is a sequence of **temporal chapters**: `Cover → (Chapter Opener →
Events) → (Chapter Opener → Events) → ...`. The Cover is a `<x-special.timeline>` call with no events (header
only). Each chapter pairs the reusable `resources/views/components/special/chapter-opener.blade.php`
(`<x-special.chapter-opener :period :title :intro :image :alt>`) with its own `<x-special.timeline>` call
carrying only that chapter's events (no `title`/`background`, so its built-in cover stays hidden). Both
components are generic/data-driven — a future Special Project supplies its own chapters, no component change.

`TARGET_ROUTE` = the Special Project route under test. **Default: `/turing`.** Any future Special Project sets
its own `TARGET_ROUTE`; the skill must not depend on `/turing` specifically.

## A. Invariant Project Book constraints (apply to EVERY Special Project)
- §10.1 the structure narrates, images introduce the chapter.
- §10.2 every chapter must be immediately recognizable (a real **chapter opener** into the Timeline).
- §10.3 avoid long runs of sections sharing the same visual language.
- §10.4 long sections need internal structure: chronological spine + event hierarchy.
- §10.5 photography opens a section with controlled proportions.
- §10.6 coherence over spectacle.
- **Decision #001 (never reverse):** the Timeline uses a **bounded photographic cover + a separate events
  area** — never one image stretched over the whole section, and never `background-attachment: fixed`.
- **Decision #003 (never reverse):** the Timeline is a sequence of **temporal chapters** — `Cover → (Chapter
  Opener → Events)` repeated. Each Chapter Opener's photo is a **bounded, contained image** (never a full-bleed
  section background) and sits next to `period`/`title`/`intro` text. Events keep rendering through
  `<x-special.timeline>` — a chapter never re-implements the events markup itself.

## B. Visual baseline (reference, NOT an invariant)
A **visual baseline** is the approved look of a specific `TARGET_ROUTE`, used only to detect unintended visual
change — never as a cross-project rule. For a `TARGET_ROUTE` other than `/turing`, **capture the baseline and get
it approved before testing**; **until an approved baseline exists, only the Project Book invariants (section A)
are normative** for that route. The `/turing` baseline below is a concrete example, not a requirement for other
Special Projects (do not force a dark Legacy, the same palette, or a two-column card layout — those are visual
choices, not invariants):
- dark full-bleed Legacy → light band → rounded cover;
- spine + node dots, white cards on a light surface, year→title→text hierarchy;
- desktop 2-column cards, mobile stacked (year above title).

## Setup (subordinate to repo docs)
Run the project the way the repo documents it (README / composer scripts / `composer.json` / the environment
blueprint). Use a PHP version compatible with `composer.json`. Do not assume SQLite or a specific local config;
the following are only examples if the repo has no other instructions:
```bash
cp -n .env.example .env && php artisan key:generate
php artisan migrate --seed              # run ONLY against an isolated, disposable test DB — never a real/prod DB
php artisan serve                       # then open TARGET_ROUTE in a browser
```
Write plans, reports, screenshots and recordings to a **persistent workspace path**, not a temp dir.

## What to verify
Judge against section A (invariants) **always**, plus the **approved** baseline of `TARGET_ROUTE` when one
exists (for `/turing`, section B). With no approved baseline, only section A is normative. Never judge a route
against `/turing` specifics.

- **T1 — chapter opener:** entering the Timeline reads as a new chapter, visually distinct from the previous
  section, with a natural (not jarring) break. Cover is bounded (not edge-to-edge). Every temporal chapter is
  introduced by a real `<x-special.chapter-opener>` instance rendering `period`/`title`/`intro`, and its image
  is a **contained `<img>`**, not a section-wide background — check `getBoundingClientRect()` of the image is
  meaningfully narrower than its section (not full-bleed), and that its text has a background behind it that
  guarantees contrast regardless of the section immediately before it (a Chapter Opener must not inherit a dark
  section's background and silently lose contrast on dark-on-dark or light-on-light text).
- **T2 — component rendering + events area (§10.4):** the page renders the Timeline through the
  `<x-special.timeline>` component (not a bespoke per-page partial); internal structure is present
  (spine/nodes or equivalent), event cards are legible and separated from the surface, year→title→text
  hierarchy is clear. Expected card count = **renderable events only**: the component keeps an event when it
  has at least one of `year`/`title`/`text` and drops events lacking all three, so assert on that count, not
  on the raw input length.
- **T3 — responsive (widths 1440 / 820 / 390):** composition stays coherent at each width and
  **no horizontal overflow** — assert `document.documentElement.scrollWidth <= window.innerWidth`.
- **T4 — semantics & keyboard accessibility:** `<ol>` with one `<li>` per renderable event; card-text
  contrast ≥ WCAG AA; keyboard navigation works with no keyboard trap; linkable cards show a visible
  `:focus-visible` state (see the focus section). **Only when a `title` is passed** does the component expose
  `<section aria-labelledby>` → `<h2 id>`; with no title it omits both, so their absence is not a failure.
- **T5 — Decision #001 + regressions:** Decision #001 intact (bounded cover with token-driven height,
  separate from the events area, no full-section image, no `background-attachment: fixed`); every changed file
  is consistent with the PR's declared scope; **Hero, Timeline and Legacy have no unintended changes**;
  `php artisan test` green (per the repo's test setup). Runnable check — set `BASE` to the PR base branch and
  swap the paths for the route under test (paths shown are `/turing`):
  ```bash
  BASE=origin/main
  # 1. Full scope: every changed file must be expected — review anything unexpected.
  git diff --name-only --merge-base "$BASE" HEAD
  # 2. Targeted regression: the sensitive partials/component below must show no unintended changes
  #    (swap the paths for the route under test; paths shown are /turing).
  git diff --merge-base "$BASE" HEAD -- \
    resources/views/turing/partials/hero.blade.php \
    resources/views/turing/partials/legacy-section.blade.php \
    resources/views/turing/partials/timeline.blade.php \
    resources/views/components/special/timeline.blade.php \
    resources/views/components/special/chapter-opener.blade.php
  ```
- **T6 — reusability + design tokens:** generic API (`events, kicker, title, background, id` for the Timeline;
  `period, title, intro, image, alt` for the Chapter Opener); no figure-specific literals in either component;
  images resolved internally; **coherent use of `--sp-*` design tokens** (values come from tokens, not
  duplicated hard-coded literals) and tokens/`.sp-*` reference the global brand tokens, not a page namespace.
  A new figure = new data (and, for the Timeline, its own chapter grouping) passed to the same components, no
  component code change.
- **T7 — chapter → events order (Decision #003):** walking the DOM in source order, the Timeline area is
  `Cover, then (Chapter Opener, Events) repeated` — a Chapter Opener is never immediately followed by another
  Chapter Opener, and events never appear before their own chapter's opener. Every `<x-special.timeline>`/
  chapter section has a unique `id` (no duplicate DOM ids across chapters); the total renderable event count
  across all chapters equals the page's total event count (no event dropped or duplicated by the grouping).

## Focus-state testing
Provide a linkable card first: **fixture / test data** with an event carrying `url`/`link_url` (use a
**temporary local code change** only as a last resort, reverted afterwards — leave the working tree clean).
Then:
1. **Keyboard path (primary):** real **Tab** navigation to the card link; confirm `document.activeElement` is
   the card link and that there is no keyboard trap.
2. **Styling assertion:** use **DevTools to force `:focus-visible`** and check the computed styles (lift/border).

`element.focus()` proves the element is active/focusable but does **not** by itself prove `:focus-visible` —
use it only to reach the element, never as evidence of the focus-visible style. Always verify
`document.activeElement` and computed styles, not the browser status bar alone.

## Evidence
- Annotated screen recording of the desktop scroll-through (chapter opener → events → focus state): annotate
  setup, each test start, and pass/fail assertions.
- Full-Timeline screenshots at 1440 / 820 / 390. For exact widths, drive the running browser via its
  DevTools/CDP endpoint (discover the endpoint from the environment — do not hardcode a port) with device
  metrics + capture-beyond-viewport, or use the browser's responsive/device mode; reset any override after.
- A `test-report.md` following the template below.

## test-report.md template (required)
```markdown
# Timeline test report — <TARGET_ROUTE>
- Context: <what/why>
- Route: <TARGET_ROUTE>
- Branch / PR: <branch> / #<pr>
- Commit: <sha>
- Viewports: 1440 / 820 / 390

## Results
| # | Check | Result |
|---|-------|--------|
| T1 | Chapter opener | PASS / FAIL / NON CONCLUSIVO |
| T2 | Component rendering + events area (§10.4) | PASS / FAIL / NON CONCLUSIVO |
| T3 | Responsive + no overflow | PASS / FAIL / NON CONCLUSIVO |
| T4 | Semantics & keyboard accessibility | PASS / FAIL / NON CONCLUSIVO |
| T5 | Decision #001 + regressions | PASS / FAIL / NON CONCLUSIVO |
| T6 | Reusability + design tokens | PASS / FAIL / NON CONCLUSIVO |
| T7 | Chapter → events order (Decision #003) | PASS / FAIL / NON CONCLUSIVO |

## Evidence
<inline screenshots + recording links>

## Automated tests
<`php artisan test` result>

## Issues
- Blocking: <...>
- Non-blocking: <...>

## Final judgment
Merge Ready / Merge con piccoli fix / Da rivedere
```

## Notes
- Generated files (e.g. `bootstrap/cache/*.php`) may show as modified after artisan runs — not a regression.
- Do not claim success if any assertion is untested or failing.

## Devin Secrets Needed
None. Runs against a local Laravel server; no external credentials or API keys required.
