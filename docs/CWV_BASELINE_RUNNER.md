# CWV baseline runner

Executable companion to `PERFORMANCE_CWV_S3_AUDIT_PLAN.md`.

## Why this exists

A runtime change is allowed only after reproducible evidence. This runner standardizes the lab capture without inventing Lighthouse/Core Web Vitals numbers when a browser is unavailable.

## Run

Start Kairus locally with representative public fixtures, install Playwright browsers, then run:

```bash
CWV_BASE_URL=http://127.0.0.1:8000 \
CWV_ARTICLE_PATH=/articolo/<published-slug> \
CWV_CATEGORY_PATH=/categoria/<category-slug> \
CWV_PERCORSO_PATH=/percorsi/<active-path-slug> \
CWV_TROVA_PATH='/ricerca?q=scienza' \
node scripts/cwv-baseline.mjs > storage/logs/cwv-before.json
```

Repeat with the same database fixture, viewport and paths after exactly one coherent optimization and write to `cwv-after.json`.

## Metrics

- LCP: buffered `largest-contentful-paint` entry.
- CLS: accumulated layout shifts excluding recent input.
- INP: the runner reports an Event Timing interaction candidate only when an actual interaction occurred. If no controlled interaction occurs it returns `null`, explicitly rather than fabricating a number. Product decisions about INP should be cross-checked with real field data/CrUX where available.

The runner also records navigation timing, response status and wall-clock elapsed time for diagnostics. These are supporting evidence, not Core Web Vitals substitutes.

## Decision rule

Do not change runtime code unless BEFORE evidence identifies a concrete bottleneck attributable to an owned Kairus resource. Third-party failures, local proxy latency and missing browser binaries must be recorded as environment limitations, not converted into product fixes.

Do not lazy-load the measured LCP element merely to improve another metric. Preserve accessibility, SEO metadata and editorial image quality.

## Current session (Mission 45 recovery)

A working Playwright/browser setup and application checkout were available, so a real BEFORE run was captured against 5 representative surfaces (home, article, category, percorso, `/ricerca`) with seeded fixtures.

Server-side response time was fast and constant across all 5 surfaces (~30ms `responseStart`/`responseEnd`), but `domInteractive`/`load` landed at a near-identical ~12.7-12.9s on every surface regardless of page weight or complexity — home and the lightweight `/ricerca` results page converged to the same value, which page-content differences alone cannot explain. Diagnosed with a targeted script logging failed requests: every page load was blocked on `net::ERR_CONNECTION_RESET` for the render-blocking Google Fonts stylesheet (`fonts.googleapis.com/css2?family=...`), consistent with this sandboxed agent environment's outbound network restrictions (the same pattern seen in every other browser verification this session) — a `networkidle` wait condition then stalls until the browser's own retry/backoff for that reset connection gives up.

Per the decision rule above, this is recorded as an **environment limitation, not a Kairus bottleneck**: the stylesheet request already specifies `display=swap` (so in an environment with normal internet access, text renders immediately with a fallback font and LCP is not blocked waiting on the font file), and the app's own response times were fast and uniform. No runtime, CSS, or font-loading change is proposed from this evidence. The raw captured JSON was not committed (sandbox-specific numbers with no generalizable value) — the reproducible methodology is what this PR delivers; a real BEFORE/AFTER pair against a network-unrestricted environment is the next actionable step whenever a genuine optimization is proposed.