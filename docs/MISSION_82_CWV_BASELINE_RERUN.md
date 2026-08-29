# Mission 82 — CWV baseline re-run and owned-resource attribution

## Outcome

`BLOCKED_WITH_EVIDENCE`

No new BEFORE metrics are claimed. The existing runner was invoked, but this
checkout cannot run the application or browser workload.

## Attempted command

```bash
CWV_ARTICLE_PATH=/articolo/test \
CWV_CATEGORY_PATH=/categoria/energia \
CWV_PERCORSO_PATH=/percorsi/test \
node scripts/cwv-baseline.mjs
```

Observed blockers:

- the standalone CWV runner does not load `playwright.config.js` and therefore does not start an application server;
- a separate `php artisan serve --host=127.0.0.1 --port=8000` preflight failed because `php` is not installed in this checkout;
- `node_modules/@playwright/test` is absent;
- Node terminated with `ERR_MODULE_NOT_FOUND` for `@playwright/test` before any
  browser or page request was made.

Therefore there is no valid home/article/category/Percorso/search measurement,
no representative response status, and no owned-resource waterfall to
attribute. Inventing LCP, CLS or interaction numbers would violate the runner's
decision rule.

## Attribution boundary

The historical runner documentation records a previous sandbox-specific run in
which every surface stalled similarly on a reset Google Fonts request while
Kairus response timing stayed fast and uniform. That is prior environmental
evidence, not a new Mission 82 baseline. It must not be re-labelled as a current
Kairus bottleneck.

Only same-fixture browser evidence may classify a resource as owned:

- Kairus-owned: application HTML, CSS, JS, images and server response under the
  configured Kairus origin;
- environmental/third-party: sandbox proxy delay, blocked Google Fonts/CDN,
  analytics/provider requests and browser installation/startup overhead.

## Unblock procedure

Install the locked Node dependencies and Playwright Chromium, provide PHP plus
Composer dependencies, seed representative fixtures, then start the application
server separately with `php artisan serve --host=127.0.0.1 --port=8000` and verify
`curl --fail http://127.0.0.1:8000/up` before running the exact standalone command
from `docs/CWV_BASELINE_RUNNER.md`. Retain JSON and a failed/slow-request log,
using identical paths, viewport and network conditions for any AFTER run.

No runtime change is authorized from this blocked capture. Mission 83 has no new
Kairus-owned bottleneck to fix.
