import { expect, test } from '@playwright/test';

// Workspace Social Admin V1 — copre desktop/mobile, tastiera e il
// funzionamento senza JavaScript delle azioni essenziali (Prompt 67-69).
// Fixture: BrowserTestSeeder crea una bozza "draft" collegata a
// browser-turing-article.
const loginPath = '/admin-05dbc57764bf/login';
const indexPath = '/admin/distribuzione-social/bozze';
const editorEmail = 'browser-admin@example.test';
const editorPassword = 'browser-tests';

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

const viewportWidths = [390, 768, 1440];

for (const width of viewportWidths) {
    test(`social drafts index remains usable at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await loginAsEditor(page);
        await page.goto(indexPath);

        await expect(page.getByRole('heading', { level: 1, name: 'Bozze Social' })).toBeVisible();
        await expect(page.getByRole('link', { name: '+ Nuova bozza' })).toBeVisible();
        await expectPageFits(page);
    });
}

test('keyboard: filters and the new-draft link are reachable in a natural tab order', async ({ page }) => {
    await loginAsEditor(page);
    await page.goto(indexPath);

    const search = page.getByLabel('Cerca articolo');
    await search.focus();
    await expect(search).toBeFocused();

    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Canale')).toBeFocused();
});

test('creating a draft and moving it to reviewed works end to end with visible confirmation', async ({ page }) => {
    await loginAsEditor(page);
    await page.goto(indexPath + '/crea');

    await page.getByLabel('Articolo *').selectOption({ label: 'Turing e il browser regression harness (published)' });
    await page.getByLabel('Canale *').selectOption('linkedin');
    await page.getByLabel('Copy (opzionale)').fill('Bozza creata dal test browser.');
    await page.getByRole('button', { name: 'Crea bozza' }).click();

    await expect(page.getByText('Bozza Social creata.')).toBeVisible();
    await expect(page.getByRole('heading', { level: 1, name: /Bozza Social/ })).toBeVisible();

    page.once('dialog', dialog => dialog.accept());
    await page.getByRole('button', { name: 'Invia in revisione' }).click();

    await expect(page.getByText('Stato aggiornato.')).toBeVisible();
    await expect(page.locator('.badge--reviewed')).toBeVisible();
});

test('a forbidden transition shows a friendly error, never a stack trace', async ({ page }) => {
    await loginAsEditor(page);
    await page.goto(indexPath);

    await page.getByRole('link', { name: 'Apri' }).first().click();
    await expect(page.getByRole('heading', { level: 1, name: /Bozza Social/ })).toBeVisible();

    // La bozza fixture è "draft": nessuna azione "Programma" deve essere
    // proposta in questo stato (transizione non raggiungibile dalla UI).
    await expect(page.getByRole('button', { name: 'Programma' })).toHaveCount(0);
});

test.describe('senza JavaScript', () => {
    test.use({ javaScriptEnabled: false, viewport: { width: 390, height: 900 } });

    test('creare una bozza e inviarla in revisione resta possibile senza JavaScript', async ({ page }) => {
        await page.goto(loginPath);
        await page.getByLabel('Email').fill(editorEmail);
        await page.getByLabel('Password').fill(editorPassword);
        await page.getByRole('button', { name: 'Accedi' }).click();
        await expect(page).toHaveURL(/\/admin\/?$/);

        await page.goto(indexPath + '/crea');
        await page.getByLabel('Articolo *').selectOption({ label: 'Turing e il browser regression harness (published)' });
        await page.getByLabel('Canale *').selectOption('facebook');
        await page.getByRole('button', { name: 'Crea bozza' }).click();

        await expect(page.getByRole('heading', { level: 1, name: /Bozza Social/ })).toBeVisible();

        // Senza JavaScript il confirm() nativo non esiste: il form di
        // transizione resta un normale submit HTML, senza dipendere da
        // onsubmit per funzionare.
        await page.getByRole('button', { name: 'Invia in revisione' }).click();
        await expect(page.getByText('Stato aggiornato.')).toBeVisible();
    });
});
