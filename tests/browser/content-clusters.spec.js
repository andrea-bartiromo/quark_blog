import { expect, test } from '@playwright/test';

const viewportWidths = [390, 768, 1440];

function watchPage(page) {
    const errors = [];
    page.on('console', message => {
        if (message.type() === 'error') errors.push(`console: ${message.text()}`);
    });
    page.on('pageerror', error => errors.push(`pageerror: ${error.message}`));
    return errors;
}

for (const width of viewportWidths) {
    test(`Percorsi public surfaces are safe at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const errors = watchPage(page);

        await page.goto('/percorsi');
        await expect(page.locator('main')).toBeVisible();
        await expect(page.getByRole('heading', { level: 1, name: 'Percorsi' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'IA spiegata' }).first()).toBeVisible();
        await expect(page.getByText('2 articoli pubblicati')).toBeVisible();
        await expect(page.getByText('Articolo programmato da non mostrare')).toHaveCount(0);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();

        await page.goto('/percorsi/ia-spiegata');
        await expect(page.locator('main')).toBeVisible();
        await expect(page.getByRole('heading', { level: 1, name: 'IA spiegata' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Turing e il browser regression harness' }).first()).toBeVisible();
        await expect(page.getByRole('link', { name: 'Dalle macchine ai modelli moderni' }).first()).toBeVisible();
        await expect(page.getByText('Articolo programmato da non mostrare')).toHaveCount(0);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();

        if (width === 1440) {
            const detailLayout = await page.evaluate(() => {
                const shell = document.querySelector('.path-detail > .container');
                const hero = document.querySelector('.path-hero');
                const steps = document.querySelector('.path-steps');
                if (!shell || !hero || !steps) return null;

                return {
                    shell: shell.getBoundingClientRect().width,
                    hero: hero.getBoundingClientRect().width,
                    steps: steps.getBoundingClientRect().width,
                    viewport: document.documentElement.clientWidth,
                };
            });

            expect(detailLayout).not.toBeNull();
            expect(detailLayout.shell).toBeGreaterThanOrEqual(detailLayout.viewport - 40);
            expect(detailLayout.hero).toBeGreaterThanOrEqual(detailLayout.viewport - 40);
            expect(detailLayout.steps).toBeGreaterThan(1120);
        }

        expect(errors).toEqual([]);
    });

    test(`article continuation skips scheduled content at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const errors = watchPage(page);

        await page.goto('/articolo/browser-turing-article');
        const box = page.locator('.path-continuation');
        await expect(box).toBeVisible();
        await expect(box.getByRole('heading', { name: 'Continua il percorso' })).toBeVisible();
        await expect(box.getByText('1 di 2')).toBeVisible();
        await expect(box.getByText('Articolo programmato da non mostrare')).toHaveCount(0);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();

        await box.getByRole('link', { name: /Successivo.*Dalle macchine ai modelli moderni/ }).click();
        await expect(page).toHaveURL(/\/articolo\/browser-path-last-article$/);
        await expect(page.locator('.path-continuation').getByText('2 di 2')).toBeVisible();
        await expect(page.locator('.path-continuation').getByText('Articolo programmato da non mostrare')).toHaveCount(0);

        await page.locator('.path-continuation').getByRole('link', { name: /Precedente.*Turing e il browser regression harness/ }).click();
        await expect(page).toHaveURL(/\/articolo\/browser-turing-article$/);

        await page.locator('.path-continuation').getByRole('link', { name: 'Vedi tutto il percorso' }).click();
        await expect(page).toHaveURL(/\/percorsi\/ia-spiegata$/);
        await expect(page.getByRole('heading', { level: 1, name: 'IA spiegata' })).toBeVisible();

        expect(errors).toEqual([]);
    });
}

test('inactive Percorso returns 404', async ({ page }) => {
    const response = await page.goto('/percorsi/percorso-inattivo-ci');
    expect(response?.status()).toBe(404);
});
