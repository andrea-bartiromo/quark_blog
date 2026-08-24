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

## Current session

No browser executable/working application checkout is available in the agent runtime, therefore no BEFORE values are recorded in this PR and no product optimization is proposed.