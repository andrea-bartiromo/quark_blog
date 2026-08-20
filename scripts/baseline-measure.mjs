// One-off measurement script for FASE 3 / FASE 13 (prima/dopo). Not part
// of the permanent test suite. Navigates real pages in real Chromium at
// 390/768/1440 and records image bytes, natural vs rendered size, and CLS
// for the deterministic ResponsiveImageFixtureSeeder fixture.
import { chromium } from '@playwright/test';

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';
const viewports = [
  { name: '390 mobile', width: 390, height: 844 },
  { name: '768 tablet', width: 768, height: 1024 },
  { name: '1440 desktop', width: 1440, height: 900 },
];

const pages = [
  { name: 'home', path: '/' },
  { name: 'article', path: '/articolo/responsive-images-fixture-article' },
  { name: 'category', path: '/categoria/intelligenza-artificiale' },
  { name: 'percorso', path: '/percorsi/responsive-images-fixture-percorso' },
];

const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });

const results = [];

for (const pg of pages) {
  for (const vp of viewports) {
    const context = await browser.newContext({ viewport: { width: vp.width, height: vp.height } });
    const page = await context.newPage();

    const imageBytes = new Map();
    page.on('response', async (res) => {
      const ct = res.headers()['content-type'] || '';
      if (ct.startsWith('image/')) {
        try {
          const buf = await res.body();
          imageBytes.set(res.url(), buf.length);
        } catch {}
      }
    });

    let cls = 0;
    await page.addInitScript(() => {
      window.__cls = 0;
      try {
        new PerformanceObserver((list) => {
          for (const entry of list.getEntries()) {
            if (!entry.hadRecentInput) window.__cls += entry.value;
          }
        }).observe({ type: 'layout-shift', buffered: true });
      } catch {}
    });

    const resp = await page.goto(baseURL + pg.path, { waitUntil: 'networkidle', timeout: 20000 }).catch(() => null);
    await page.waitForTimeout(300);
    cls = await page.evaluate(() => window.__cls || 0);

    const status = resp ? resp.status() : 'ERR';

    // Measure the fixture cover image specifically wherever it appears.
    const fixtureImg = await page.evaluate(() => {
      const imgs = Array.from(document.querySelectorAll('img'));
      const hit = imgs.find((i) => i.currentSrc && i.currentSrc.includes('fixture-photo'));
      if (!hit) return null;
      const rect = hit.getBoundingClientRect();
      return {
        currentSrc: hit.currentSrc,
        naturalWidth: hit.naturalWidth,
        naturalHeight: hit.naturalHeight,
        renderedWidth: Math.round(rect.width),
        renderedHeight: Math.round(rect.height),
        loading: hit.loading,
        hasSrcset: hit.srcset && hit.srcset.length > 0,
        hasWidthAttr: hit.hasAttribute('width'),
        hasHeightAttr: hit.hasAttribute('height'),
      };
    });

    let totalImageBytes = 0;
    for (const v of imageBytes.values()) totalImageBytes += v;
    const largest = Math.max(0, ...imageBytes.values());

    results.push({
      page: pg.name,
      viewport: vp.name,
      status,
      imageRequests: imageBytes.size,
      totalImageBytes,
      largestImageBytes: largest,
      cls: Number(cls.toFixed(4)),
      fixtureImg,
    });

    await context.close();
  }
}

await browser.close();

console.log(JSON.stringify(results, null, 2));
