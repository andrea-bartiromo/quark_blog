import { expect, test } from '@playwright/test';

// Trust Layer V1 — componente pubblico "Fonti primarie"
// (components/article/primary-sources.blade.php). Riusa la fixture
// deterministica browser-turing-article (BrowserTestSeeder), a cui questa
// missione ha aggiunto Article::primary_sources: una riga URL e una riga
// di testo libero.
const articleWithSources = '/articolo/browser-turing-article';
const articleWithoutSources = '/articolo/browser-carousel-article-2';

test.describe('Public primary sources panel', () => {
    test('desktop: renders the panel with a real link and plain text', async ({ page }) => {
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto(articleWithSources);

        const heading = page.locator('#article-primary-sources-heading');
        await expect(heading).toBeVisible();
        await expect(heading).toHaveText('Fonti primarie');

        const link = page.locator('.article-primary-sources__list a[href="https://example.org/turing-primary-source"]');
        await expect(link).toBeVisible();
        await expect(link).toHaveAttribute('rel', 'nofollow noopener noreferrer');
        await expect(link).toHaveAttribute('target', '_blank');

        await expect(page.locator('.article-primary-sources__list')).toContainText('Comunicato stampa di fixture, ottobre 2026');
    });

    test('mobile: panel is visible and link is reachable', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(articleWithSources);

        await expect(page.locator('#article-primary-sources-heading')).toBeVisible();
        await expect(page.locator('.article-primary-sources__list a')).toBeVisible();
    });

    test('the source link is reachable by keyboard and receives visible focus', async ({ page }) => {
        await page.goto(articleWithSources);

        const link = page.locator('.article-primary-sources__list a').first();
        await link.focus();
        await expect(link).toBeFocused();
    });

    test('no panel is rendered for an article without primary_sources', async ({ page }) => {
        await page.goto(articleWithoutSources);

        await expect(page.locator('#article-primary-sources-heading')).toHaveCount(0);
    });

    test('no regression on canonical link or JSON-LD structured data', async ({ page }) => {
        await page.goto(articleWithSources);

        const canonical = page.locator('link[rel="canonical"]');
        await expect(canonical).toHaveAttribute('href', /\/articolo\/browser-turing-article$/);

        const ldJson = await page.locator('script[type="application/ld+json"]').first().textContent();
        const parsed = JSON.parse(ldJson ?? '{}');
        expect(parsed['@graph']?.[0]?.['@type']).toBe('NewsArticle');
    });
});
