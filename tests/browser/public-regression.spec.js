import { expect, test } from '@playwright/test';

const publicRoutes = [
    '/',
    '/ricerca?q=turing',
    '/articolo/browser-turing-article',
    '/autore/1',
    '/categoria/browser-test',
    '/notizie',
];

for (const width of [390, 768, 1440]) {
    test.describe(`public frontend at ${width}px`, () => {
        test.use({ viewport: { width, height: 900 } });

        for (const route of publicRoutes) {
            test(`${route} has no horizontal overflow`, async ({ page }) => {
                await page.goto(route);
                await expect(page.locator('main')).toBeVisible();

                const dimensions = await page.evaluate(() => ({
                    clientWidth: document.documentElement.clientWidth,
                    scrollWidth: document.documentElement.scrollWidth,
                }));

                expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.clientWidth);
            });
        }
    });
}
