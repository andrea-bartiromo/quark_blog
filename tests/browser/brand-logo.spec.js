import { expect, test } from '@playwright/test';

// Copertura browser reale (non solo HTML server-side) del nuovo simbolo
// Kairus su homepage, pagina articolo, login admin e dashboard admin,
// desktop e mobile — "test obbligatori" #5/#6/#8 della missione logo.
const viewports = { mobile: 390, desktop: 1440 };
const loginPath = '/admin-05dbc57764bf/login';
const editorEmail = 'browser-admin@example.test';
const editorPassword = 'browser-tests';

async function assertSymbolLoaded(page, selector) {
    const symbol = page.locator(selector).first();
    await expect(symbol).toBeVisible();
    await expect(symbol).toHaveAttribute('src', /assets\/icons\/symbol\.svg$/);
    await expect(symbol).toHaveAttribute('alt', '');

    const loaded = await symbol.evaluate((img) => img.complete && img.naturalWidth > 0);
    expect(loaded, `${selector} deve caricarsi correttamente (HTTP 200, non rotto)`).toBeTruthy();
}

for (const [name, width] of Object.entries(viewports)) {
    test(`homepage header and footer show the loaded symbol at ${name} (${width}px)`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));

        await page.goto('/');
        await assertSymbolLoaded(page, '.header-logo__symbol');

        await page.locator('.site-footer').scrollIntoViewIfNeeded();
        await assertSymbolLoaded(page, '.footer-logo__symbol');

        expect(errors).toEqual([]);
    });

    test(`article page keeps header symbol intact at ${name} (${width}px)`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/articolo/browser-turing-article');
        await assertSymbolLoaded(page, '.header-logo__symbol');
    });

    test(`admin login shows the loaded symbol at ${name} (${width}px)`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(loginPath);
        await assertSymbolLoaded(page, '.login-logo__symbol');
    });

    test(`admin dashboard sidebar shows the loaded symbol at ${name} (${width}px)`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(loginPath);
        await page.getByLabel('Email').fill(editorEmail);
        await page.getByLabel('Password').fill(editorPassword);
        await page.getByRole('button', { name: 'Accedi' }).click();
        await expect(page).toHaveURL(/\/admin\/?$/);

        // La sidebar arriva dopo uno script sincrono in cima al <body> che lo
        // spec HTML blocca finche' il <link> Google Fonts che lo precede non
        // ha finito di caricare (successo o fallimento): il tempo di attesa
        // dipende dalla rete, non e' un segnale di malfunzionamento del
        // simbolo. Timeout generoso qui, non sul contenuto del test.
        await page.locator('.admin-sidebar').first().waitFor({ state: 'attached', timeout: 15000 });

        if (width < 901) {
            await page.locator('[data-admin-sidebar-toggle]').click();
        }

        await assertSymbolLoaded(page, '.admin-sidebar__logo-symbol');
    });
}

test('compact desktop sidebar keeps the symbol visible while hiding only the wordmark', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(loginPath);
    await page.getByLabel('Email').fill(editorEmail);
    await page.getByLabel('Password').fill(editorPassword);
    await page.getByRole('button', { name: 'Accedi' }).click();
    await expect(page).toHaveURL(/\/admin\/?$/);

    await page.locator('.admin-sidebar').first().waitFor({ state: 'attached', timeout: 15000 });

    await page.locator('[data-admin-sidebar-compact-toggle]').click();
    await expect(page.locator('body')).toHaveClass(/admin-sidebar-compact/);

    await assertSymbolLoaded(page, '.admin-sidebar__logo-symbol');

    // Tecnica sr-only (width:1px/height:1px/clip): l'elemento resta
    // tecnicamente "visible" per Playwright (non e' display:none, serve
    // alle tecnologie assistive), ma deve essere collassato visivamente a
    // 1x1px — non un display:none/hidden che lo toglierebbe dagli screen
    // reader.
    const wordBox = await page.locator('.admin-sidebar__logo-word').boundingBox();
    expect(wordBox.width).toBeLessThanOrEqual(1);
    expect(wordBox.height).toBeLessThanOrEqual(1);
});
