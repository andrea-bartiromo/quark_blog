import { expect, test } from '@playwright/test';

// Cantiere 66 (programma "100 cantieri Kairus"). Prova in un browser reale
// (non solo PHPUnit, che non esegue mai JavaScript) che l'esperienza
// pubblica di oggi dell'hub /turing — la landing "In arrivo", stato
// reale di default in produzione (config('turing.chapters_public') =
// false) — resta perfettamente utilizzabile con JavaScript disabilitato:
// nessun errore di pagina/console, il contenuto reale (non solo un
// contenitore vuoto in attesa di essere popolato da JS) è visibile.
test.describe('Turing hub landing "In arrivo" senza JavaScript', () => {
    test.use({ javaScriptEnabled: false, viewport: { width: 390, height: 900 } });

    test('renders the real coming-soon content and preview cards without any script running', async ({ page }) => {
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));

        const response = await page.goto('/turing');
        expect(response.status()).toBe(200);

        await expect(page.getByRole('heading', { name: 'Alan Turing', level: 1 })).toBeVisible();
        await expect(page.getByText('In lavorazione', { exact: true })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Enigma e Bletchley Park' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'L’eredità' })).toBeVisible();

        expect(errors).toEqual([]);
    });
});
