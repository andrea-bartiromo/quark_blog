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

## B. Current /turing visual baseline (reference, NOT a requirement)
These describe how `/turing` looks today; they are examples, not rules for other Special Projects. Do not force
a dark Legacy, the same palette, or a two-column card layout on a new figure — those are visual choices, not invariants:
- dark full-bleed Legacy → light band → rounded cover;
- spine + node dots, white cards on a light surface, year→title→text hierarchy;
- desktop 2-column cards, mobile stacked (year above title).

## Setup (subordinate to repo docs)
Run the project the way the repo documents it (README / composer scripts / `composer.json` / the environment
blueprint). Use a PHP version compatible with `composer.json`. Do not assume SQLite or a specific local config;
the following are only examples if the repo has no other instructions:
```bash
cp -n .env.example .env && php artisan key:generate
php artisan migrate --seed --force        # DB driver per the repo config
php artisan serve                          # then open TARGET_ROUTE in a browser
```
Write plans, reports, screenshots and recordings to a **persistent workspace path**, not a temp dir.

## What to verify
Judge against section A (invariants) + the current baseline of `TARGET_ROUTE`, not against `/turing` specifics.

- **T1 — chapter opener:** entering the Timeline reads as a new chapter, visually distinct from the previous
  section, with a natural (not jarring) break. Cover is bounded (not edge-to-edge).
- **T2 — component rendering + events area (§10.4):** the page renders the Timeline through the
  `<x-special.timeline>` component (not a bespoke per-page partial); internal structure is present
  (spine/nodes or equivalent), event cards are legible and separated from the surface, year→title→text
  hierarchy is clear, every event renders.
- **T3 — responsive (widths 1440 / 820 / 390):** composition stays coherent at each width and
  **no horizontal overflow** — assert `document.documentElement.scrollWidth <= window.innerWidth`.
- **T4 — semantics & keyboard accessibility:** DOM is `<section aria-labelledby>` → `<h2 id>`, then `<ol>`
  with one `<li>` per event; card-text contrast ≥ WCAG AA; keyboard navigation works with no keyboard trap;
  linkable cards show a visible `:focus-visible` state (see the focus section for how to confirm it).
- **T5 — Decision #001 + regressions:** Decision #001 intact (bounded cover with token-driven height,
  separate from the events area, no full-section image, no `background-attachment: fixed`); every changed file
  is consistent with the PR's declared scope; **Hero, Timeline and Legacy have no unintended changes**
  (`git diff <base>...HEAD -- <hero> <legacy> <timeline partial/component>`); `php artisan test` green (per
  the repo's test setup).
- **T6 — reusability + tokens:** generic API (`events, kicker, title, background, id`); no figure-specific
  literals in the component; images resolved internally; **coherent use of `--sp-*` design tokens** (values
  come from tokens, not duplicated hard-coded literals) and tokens/`.sp-*` reference the global brand tokens,
  not a page namespace. A new figure = new data passed to the same component, no component code change.

## Focus-state testing (in this order)
1. **Fixture / test data** with an event carrying `url`/`link_url`, so a real linkable card exists.
2. **Real keyboard navigation** — Tab to the card link.
3. **DevTools** — force `:focus-visible` or call `element.focus()` on the card link.
4. **Temporary local code change** only as a last resort, then revert (leave the working tree clean).

Confirm focus by checking **`document.activeElement`** and the **computed styles** (lift/border), not the
browser status bar alone.

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
| T2 | Events area (§10.4) | PASS / FAIL / NON CONCLUSIVO |
| T3 | Responsive + no overflow | PASS / FAIL / NON CONCLUSIVO |
| T4 | Semantics & accessibility | PASS / FAIL / NON CONCLUSIVO |
| T5 | Regressions | PASS / FAIL / NON CONCLUSIVO |
| T6 | Reusability | PASS / FAIL / NON CONCLUSIVO |

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
