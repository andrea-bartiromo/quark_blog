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
        await expect(page.getByText('1 articolo pubblicato')).toBeVisible();
        await expect(page.getByText('Articolo programmato da non mostrare')).toHaveCount(0);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();

        await page.goto('/percorsi/ia-spiegata');
        await expect(page.locator('main')).toBeVisible();
        await expect(page.getByRole('heading', { level: 1, name: 'IA spiegata' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Turing e il browser regression harness' }).first()).toBeVisible();
        await expect(page.getByText('Articolo programmato da non mostrare')).toHaveCount(0);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();

        expect(errors).toEqual([]);
    });
}

test('inactive Percorso returns 404', async ({ page }) => {
    const response = await page.goto('/percorsi/percorso-inattivo-ci');
    expect(response?.status()).toBe(404);
});
