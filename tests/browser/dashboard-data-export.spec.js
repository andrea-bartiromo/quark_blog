import { expect, test } from '@playwright/test';

test('administrator can use the data export action from the only editorial command center', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => {
        if (message.type() === 'error') errors.push(message.text());
    });

    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/admin-05dbc57764bf/login');
    await page.getByLabel('Email').fill('browser-admin@example.test');
    await page.getByLabel('Password').fill('browser-tests');
    await page.getByRole('button', { name: 'Accedi' }).click();
    await page.goto('/admin/operazioni-editoriali');

    const exportPanel = page.getByText('Esporta dati per analisi', { exact: true });
    await expect(exportPanel).toBeVisible();
    await exportPanel.click();
    await expect(page.getByText('Non include email, token, session ID, IP, credenziali o note private.')).toBeVisible();
    await expect(page.getByLabel('Timezone')).toHaveValue('Europe/Rome');
    await expect(page.getByRole('button', { name: 'Esporta dati per analisi' })).toBeVisible();

    const dimensions = await page.evaluate(() => ({
        clientWidth: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
    }));
    expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.clientWidth);
    expect(errors).toEqual([]);
});
