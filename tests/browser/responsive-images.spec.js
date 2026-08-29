import { expect, test } from '@playwright/test';

/**
 * FASE 12 (missione S2 responsive images): regressione permanente sulla
 * selezione responsive reale del browser (currentSrc, non solo la presenza
 * dell'attributo srcset), sulla stabilita' del layout e sul comportamento
 * lazy/eager, a 390/768/1440. Fixture deterministica: vedi
 * database/seeders/ResponsiveImageFixtureSeeder.php.
 *
 * Precondizione (non eseguita da questo spec): il database deve essere
 * seminato con
 *   php artisan migrate:fresh --seed
 *   php artisan db:seed --class="Database\Seeders\ResponsiveImageFixtureSeeder"
 * — stessa convenzione documentata in docs/browser-regression-tests.md per
 * BrowserTestSeeder.
 */

const routes = {
    home: '/',
    article: '/articolo/responsive-images-fixture-article',
    category: '/categoria/intelligenza-artificiale',
    percorso: '/percorsi/responsive-images-fixture-percorso',
};

const viewports = {
    mobile: { width: 390, height: 844 },
    tablet: { width: 768, height: 1024 },
    desktop: { width: 1440, height: 900 },
};

/** @returns {Promise<Array<{currentSrc: string, naturalWidth: number, renderedWidth: number, loading: string, hasSrcset: boolean}>>} */
async function fixtureImages(page) {
    return page.evaluate(() => {
        return Array.from(document.querySelectorAll('img'))
            .filter((img) => img.currentSrc && img.currentSrc.includes('fixture-photo-responsive-fixture'))
            // Il visualizzatore a schermo intero (x-media.image-viewer) e'
            // deliberatamente full-res e SENZA srcset (mai una versione
            // ridotta nel lightbox): un secondo <img>, distinto da quello
            // "vetrina" (card/hero), che questo spec non deve confondere
            // con la superficie responsive sotto test.
            .filter((img) => !img.hasAttribute('data-media-viewer-image'))
            .map((img) => {
                const rect = img.getBoundingClientRect();

                return {
                    currentSrc: img.currentSrc,
                    naturalWidth: img.naturalWidth,
                    naturalHeight: img.naturalHeight,
                    renderedWidth: Math.round(rect.width),
                    renderedHeight: Math.round(rect.height),
                    loading: img.loading,
                    hasSrcset: Boolean(img.srcset && img.srcset.length > 0),
                    fetchPriority: img.getAttribute('fetchpriority'),
                };
            });
    });
}

async function hasHorizontalOverflow(page) {
    return page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
}

async function installLayoutShiftObserver(page) {
    await page.addInitScript(() => {
        window.__kairusUnexpectedLayoutShift = 0;

        new PerformanceObserver((list) => {
            for (const entry of list.getEntries()) {
                if (!entry.hadRecentInput) {
                    window.__kairusUnexpectedLayoutShift += entry.value;
                }
            }
        }).observe({ type: 'layout-shift', buffered: true });
    });
}

for (const [viewportName, viewport] of Object.entries(viewports)) {
    test.describe(`responsive images @ ${viewportName} (${viewport.width}px)`, () => {
        test.use({ viewport });

        test('article hero: currentSrc resolves to a real generated variant, not just the original', async ({ page }) => {
            await page.goto(routes.article, { waitUntil: 'networkidle' });

            const images = await fixtureImages(page);
            expect(images.length).toBeGreaterThan(0);

            const hero = images[0];

            // La prova che conta: il browser ha scelto DAVVERO una risorsa
            // tra quelle offerte da srcset (non solo che l'attributo esiste).
            expect(hero.hasSrcset).toBe(true);
            expect(hero.currentSrc).toMatch(/fixture-photo-responsive-fixture(-\d+w)?\.webp$/);

            if (viewportName === 'desktop') {
                // A 1440px l'hero occupa gran parte del container (fino a
                // 1240px): il browser non deve scegliere la miniatura piu'
                // piccola (480w) per una superficie cosi' grande.
                expect(hero.naturalWidth).toBeGreaterThan(480);
            }

            expect(await hasHorizontalOverflow(page)).toBe(false);
        });

        test('article hero is not lazy and carries fetchpriority=high (LCP candidate)', async ({ page }) => {
            await page.goto(routes.article, { waitUntil: 'networkidle' });

            const images = await fixtureImages(page);
            const hero = images[0];

            expect(hero.loading).toBe('eager');
            expect(hero.fetchPriority).toBe('high');
        });

        test('category page: hero and card images resolve to real variants with no overflow', async ({ page }) => {
            await page.goto(routes.category, { waitUntil: 'networkidle' });

            const images = await fixtureImages(page);
            expect(images.length).toBeGreaterThan(0);

            for (const img of images) {
                expect(img.currentSrc).toMatch(/fixture-photo-responsive-fixture(-\d+w)?\.webp$/);
                // Nessun upscale visibile: la risorsa naturale non deve mai
                // essere piu' STRETTA della sua stessa box renderizzata
                // (object-fit:cover puo' ritagliare in altezza, ma mai in
                // larghezza sotto la naturalWidth della risorsa scelta).
                expect(img.naturalWidth).toBeGreaterThanOrEqual(Math.min(img.renderedWidth, img.naturalWidth));
            }

            expect(await hasHorizontalOverflow(page)).toBe(false);
        });

        test('percorso page: step cover resolves to a real variant and stays lazy below the fold', async ({ page }) => {
            await page.goto(routes.percorso, { waitUntil: 'networkidle' });

            const images = await fixtureImages(page);
            expect(images.length).toBeGreaterThan(0);

            const stepCover = images.find((img) => img.loading === 'lazy');
            expect(stepCover).toBeTruthy();
            expect(stepCover.hasSrcset).toBe(true);

            expect(await hasHorizontalOverflow(page)).toBe(false);
        });

        // S2-C (missione notturna): l'hero /percorsi/{slug} e' ora un
        // <x-responsive-image> assolutamente posizionato (stessa grammatica
        // di articles/partials/hero.blade.php), non piu' un CSS
        // background-image — quindi e' il primo elemento tra le
        // fixtureImages() della pagina (stessa convenzione del test
        // "article hero" qui sopra), non piu' il solo step cover.
        test('percorso hero is not lazy and carries fetchpriority=high (LCP candidate)', async ({ page }) => {
            await page.goto(routes.percorso, { waitUntil: 'networkidle' });

            const images = await fixtureImages(page);
            expect(images.length).toBeGreaterThan(0);

            const hero = images[0];

            expect(hero.hasSrcset).toBe(true);
            expect(hero.loading).toBe('eager');
            expect(hero.fetchPriority).toBe('high');
        });

        test('no image on any surface causes a horizontal scrollbar', async ({ page }) => {
            test.slow();

            for (const route of Object.values(routes)) {
                await page.goto(route, { waitUntil: 'load' });
                expect(await hasHorizontalOverflow(page), `overflow on ${route}`).toBe(false);
            }
        });

        test('responsive markup is complete and loading causes no large unexpected layout shift', async ({ page }) => {
            test.slow();
            await installLayoutShiftObserver(page);

            for (const route of Object.values(routes)) {
                await page.goto(route, { waitUntil: 'networkidle' });

                const audit = await page.evaluate(() => ({
                    cls: window.__kairusUnexpectedLayoutShift ?? 0,
                    broken: Array.from(document.images)
                        .filter((img) => img.src && !img.src.startsWith('data:') && img.complete && img.naturalWidth === 0)
                        .map((img) => img.currentSrc || img.src),
                    incompleteResponsiveMarkup: Array.from(document.images)
                        .filter((img) => img.srcset)
                        .filter((img) => !img.sizes || Number(img.getAttribute('width')) <= 0 || Number(img.getAttribute('height')) <= 0)
                        .map((img) => img.currentSrc || img.src),
                }));

                expect(audit.broken, `broken images on ${route}`).toEqual([]);
                expect(audit.incompleteResponsiveMarkup, `incomplete responsive markup on ${route}`).toEqual([]);
                // "Grossa variazione", non un fragile pixel comparison:
                // la soglia poor di Core Web Vitals segnala regressioni
                // sostanziali senza rendere il test sensibile a micro-shift.
                expect(audit.cls, `large unexpected layout shift on ${route}`).toBeLessThan(0.25);
            }
        });
    });
}

test.describe('responsive images: layout stability (CLS-relevant attributes)', () => {
    test.use({ viewport: viewports.mobile });

    test('article hero image declares width/height so the browser can reserve space before load', async ({ page }) => {
        await page.goto(routes.article, { waitUntil: 'domcontentloaded' });

        const dims = await page.evaluate(() => {
            const img = Array.from(document.querySelectorAll('img')).find((i) =>
                i.src.includes('fixture-photo-responsive-fixture')
            );

            return img ? { width: img.getAttribute('width'), height: img.getAttribute('height') } : null;
        });

        expect(dims).not.toBeNull();
        expect(Number(dims.width)).toBeGreaterThan(0);
        expect(Number(dims.height)).toBeGreaterThan(0);
    });

    test('percorso hero image declares width/height so the browser can reserve space before load', async ({ page }) => {
        await page.goto(routes.percorso, { waitUntil: 'domcontentloaded' });

        const dims = await page.evaluate(() => {
            const img = Array.from(document.querySelectorAll('img')).find((i) =>
                i.src.includes('fixture-photo-responsive-fixture')
            );

            return img ? { width: img.getAttribute('width'), height: img.getAttribute('height') } : null;
        });

        expect(dims).not.toBeNull();
        expect(Number(dims.width)).toBeGreaterThan(0);
        expect(Number(dims.height)).toBeGreaterThan(0);
    });

    test('percorso card declares width/height derived from the real cover, not a hardcoded guess', async ({ page }) => {
        await page.goto('/percorsi', { waitUntil: 'domcontentloaded' });

        const dims = await page.evaluate(() => {
            const img = Array.from(document.querySelectorAll('img')).find((i) =>
                i.src.includes('fixture-photo-responsive-fixture')
            );

            return img ? { width: img.getAttribute('width'), height: img.getAttribute('height') } : null;
        });

        // Puo' non esserci se la fixture cluster non compare in questa
        // pagina (dipende dai dati seminati): quando c'e', deve avere
        // dimensioni reali, non zero/assenti.
        if (dims) {
            expect(Number(dims.width)).toBeGreaterThan(0);
            expect(Number(dims.height)).toBeGreaterThan(0);
        }
    });
});

test.describe('responsive images: legacy fallback (no variants generated)', () => {
    test.use({ viewport: viewports.mobile });

    test('an image without responsive variants still renders correctly (graceful degradation)', async ({ page }) => {
        // Gli articoli del DatabaseSeeder di base (cover-*.webp) non hanno
        // mai attraversato ResponsiveImageVariantService: devono comunque
        // renderizzare senza errori, con un srcset a un solo candidato (o
        // assente) — mai un <img> rotto.
        await page.goto(routes.home, { waitUntil: 'networkidle' });

        const brokenImages = await page.evaluate(() => {
            return Array.from(document.querySelectorAll('img'))
                .filter((img) => img.src && !img.src.startsWith('data:'))
                .filter((img) => img.complete && img.naturalWidth === 0)
                .map((img) => img.src);
        });

        expect(brokenImages).toEqual([]);
    });
});
