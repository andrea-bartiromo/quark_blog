import { chromium } from '@playwright/test';

const baseUrl = process.env.CWV_BASE_URL ?? 'http://127.0.0.1:8000';
const paths = [
  ['home', '/'],
  ['article', process.env.CWV_ARTICLE_PATH],
  ['category', process.env.CWV_CATEGORY_PATH],
  ['percorso', process.env.CWV_PERCORSO_PATH],
  ['trova', process.env.CWV_TROVA_PATH],
].filter(([, path]) => typeof path === 'string' && path.length > 0);

if (paths.length < 4) {
  console.error('Provide CWV_ARTICLE_PATH, CWV_CATEGORY_PATH and CWV_PERCORSO_PATH. CWV_TROVA_PATH is optional.');
  process.exit(2);
}

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1365, height: 768 } });
const results = [];

for (const [name, path] of paths) {
  const page = await context.newPage();

  await page.addInitScript(() => {
    window.__kairusCwv = { lcp: null, cls: 0, interactions: new Map() };

    new PerformanceObserver((list) => {
      const entries = list.getEntries();
      const last = entries.at(-1);
      if (last) window.__kairusCwv.lcp = last.startTime;
    }).observe({ type: 'largest-contentful-paint', buffered: true });

    new PerformanceObserver((list) => {
      for (const entry of list.getEntries()) {
        if (!entry.hadRecentInput) window.__kairusCwv.cls += entry.value;
      }
    }).observe({ type: 'layout-shift', buffered: true });

    if (PerformanceObserver.supportedEntryTypes.includes('event')) {
      new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
          if (!entry.interactionId) continue;
          const previous = window.__kairusCwv.interactions.get(entry.interactionId) ?? 0;
          window.__kairusCwv.interactions.set(entry.interactionId, Math.max(previous, entry.duration));
        }
      }).observe({ type: 'event', durationThreshold: 16, buffered: true });
    }
  });

  const startedAt = Date.now();
  const response = await page.goto(new URL(path, baseUrl).toString(), { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);

  const metrics = await page.evaluate(() => {
    const interactionDurations = [...window.__kairusCwv.interactions.values()].sort((a, b) => b - a);
    return {
      lcp_ms: window.__kairusCwv.lcp,
      cls: Number(window.__kairusCwv.cls.toFixed(4)),
      inp_ms: interactionDurations.length ? interactionDurations[0] : null,
      inp_note: interactionDurations.length
        ? 'Lab interaction candidate from Event Timing; compare with field INP before product decisions.'
        : 'No controlled interaction occurred; INP intentionally left null rather than invented.',
      navigation: performance.getEntriesByType('navigation')[0]?.toJSON() ?? null,
    };
  });

  results.push({
    surface: name,
    path,
    status: response?.status() ?? null,
    elapsed_ms: Date.now() - startedAt,
    ...metrics,
  });

  await page.close();
}

await browser.close();

console.log(JSON.stringify({
  generated_at: new Date().toISOString(),
  base_url: baseUrl,
  viewport: '1365x768',
  results,
}, null, 2));
