import { expect, test } from '@playwright/test';

const viewports = [390, 768, 1440];

for (const width of viewports) {
    test(`footer social profiles are accessible and contained at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));

        await page.goto('/');

        const socialNav = page.getByRole('navigation', { name: 'La curiosità continua' });
        const linkedin = socialNav.getByRole('link', { name: 'Kairus su LinkedIn', exact: true });
        const facebook = socialNav.getByRole('link', { name: 'Kairus su Facebook', exact: true });

        await expect(socialNav).toBeVisible();
        await expect(linkedin).toBeVisible();
        await expect(facebook).toBeVisible();
        await expect(page.getByText('Nuove storie, idee e domande da esplorare insieme.')).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Seguici', exact: true })).toHaveCount(0);
        await expect(page.getByText('Seguici', { exact: true })).toHaveCount(0);

        await expect(linkedin).toHaveAttribute('href', 'https://www.linkedin.com/company/kairus-it/');
        await expect(facebook).toHaveAttribute('href', 'https://www.facebook.com/profile.php?id=61593323495927');

        for (const link of [linkedin, facebook]) {
            await expect(link).toHaveAttribute('target', '_blank');
            await expect(link).toHaveAttribute('rel', /\bnoopener\b/);
            await expect(link).toHaveAttribute('rel', /\bnoreferrer\b/);

            const box = await link.boundingBox();
            expect(box).not.toBeNull();
            expect(box.width).toBeGreaterThanOrEqual(44);
            expect(box.height).toBeGreaterThanOrEqual(44);
        }

        await linkedin.focus();
        await expect(linkedin).toBeFocused();
        expect(await linkedin.evaluate(element => getComputedStyle(element).outlineStyle)).not.toBe('none');

        await page.keyboard.press('Tab');
        await expect(facebook).toBeFocused();

        expect(await page.evaluate(() => (
            document.documentElement.scrollWidth <= document.documentElement.clientWidth
        ))).toBeTruthy();
        expect(errors).toEqual([]);
    });
}
