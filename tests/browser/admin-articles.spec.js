import { expect, test } from '@playwright/test';

const viewportWidths = [390, 768, 1440];
const loginPath = '/admin-05dbc57764bf/login';
const articlesPath = '/admin/articoli';
const editorEmail = 'browser-admin@example.test';
const editorPassword = 'browser-tests';
const longTitle = 'Titolo browser molto lungo per verificare che le azioni restino raggiungibili anche quando il contenuto editoriale supera ampiamente la lunghezza normale della tabella amministrativa';

async function loginAsEditor(page) {
    await page.goto(loginPath);
    await page.getByLabel('Email').fill(editorEmail);
    await page.getByLabel('Password').fill(editorPassword);
    await page.getByRole('button', { name: 'Accedi' }).click();
    await expect(page).toHaveURL(/\/admin\/?$/);
}

async function expectPageFits(page) {
    const dimensions = await page.evaluate(() => ({
        clientWidth: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
    }));

    expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.clientWidth);
}

for (const width of viewportWidths) {
    test(`admin article index remains usable at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await loginAsEditor(page);
        await page.goto(articlesPath);

        const toolbar = page.locator('.articles-toolbar');
        const search = page.getByRole('searchbox', { name: 'Cerca per titolo, sommario o testo dell\'articolo' });
        const status = page.getByRole('combobox', { name: 'Filtra per stato editoriale' });

        await expect(page.getByRole('heading', { level: 1, name: 'Articoli' })).toBeVisible();
        await expect(toolbar).toBeVisible();
        await expect(search).toBeVisible();
        await expect(status).toBeVisible();
        await expectPageFits(page);

        // Tastiera: la toolbar segue un ordine DOM naturale e non richiede JS.
        await search.focus();
        await expect(search).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(status).toBeFocused();

        // Titolo lungo: la riga resta disponibile e le azioni sono raggiungibili.
        const longTitleRow = page.locator('tr').filter({ has: page.locator(`td[title="${longTitle}"]`) });
        await expect(longTitleRow).toHaveCount(1);
        const editAction = longTitleRow.getByRole('link', { name: 'Modifica' });
        const viewAction = longTitleRow.getByRole('link', { name: 'Vedi' });
        const deleteAction = longTitleRow.getByRole('button', { name: 'Elimina' });
        await editAction.scrollIntoViewIfNeeded();
        await expect(editAction).toBeVisible();
        await expect(viewAction).toBeVisible();
        await expect(deleteAction).toBeVisible();
        await expectPageFits(page);

        // Ricerca server-side e reset.
        await search.fill('Titolo browser molto lungo');
        await toolbar.getByRole('button', { name: 'Filtra' }).click();
        await expect(page).toHaveURL(/q=Titolo(?:\+|%20)browser/);
        await expect(page.locator(`td[title="${longTitle}"]`)).toHaveCount(1);
        const resetAfterSearch = toolbar.getByRole('link', { name: 'Azzera filtri' });
        await expect(resetAfterSearch).toBeVisible();
        await resetAfterSearch.click();
        await expect(page).toHaveURL(/\/admin\/articoli$/);

        // Filtro stato e reset.
        await status.selectOption('draft');
        await toolbar.getByRole('button', { name: 'Filtra' }).click();
        await expect(page).toHaveURL(/status=draft/);
        await expect(status).toHaveValue('draft');
        await expect(page.getByText('Bozza browser amministrativa 2', { exact: true })).toBeVisible();
        await expect(page.getByText('Articolo programmato da non mostrare', { exact: true })).toHaveCount(0);
        await toolbar.getByRole('link', { name: 'Azzera filtri' }).click();
        await expect(page).toHaveURL(/\/admin\/articoli$/);

        // La fixture ha 30 articoli totali: la seconda pagina deve esistere.
        const secondPageLink = page.locator('a[href*="page=2"]').last();
        await expect(secondPageLink).toBeVisible();
        await secondPageLink.click();
        await expect(page).toHaveURL(/page=2/);
        await expect(page.locator('.articles-table tbody tr')).not.toHaveCount(0);

        const firstActionOnPageTwo = page.locator('.articles-table tbody tr').first().getByRole('link', { name: 'Modifica' });
        await firstActionOnPageTwo.scrollIntoViewIfNeeded();
        await expect(firstActionOnPageTwo).toBeVisible();
        await expectPageFits(page);
    });
}
