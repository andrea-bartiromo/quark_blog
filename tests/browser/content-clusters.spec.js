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
        await expect(page.getByRole('heading', { level: 2, name: 'Capire un tema significa seguirne i passaggi essenziali.' })).toBeVisible();
        await expect(page.getByRole('heading', { level: 2, name: 'Continua a esplorare' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Turing e il browser regression harness' }).first()).toBeVisible();
        await expect(page.getByRole('link', { name: 'Dalle macchine ai modelli moderni' }).first()).toBeVisible();
        await expect(page.getByText('Articolo programmato da non mostrare')).toHaveCount(0);
        await expect(page.locator('link[href*="content-clusters-detail.css"]')).toHaveCount(1);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();

        if (width === 1440) {
            const detailLayout = await page.evaluate(() => {
                const detail = document.querySelector('.path-detail');
                const shell = document.querySelector('.path-detail > .container');
                const hero = document.querySelector('.path-hero');
                const title = document.querySelector('.path-hero h1');
                const copy = document.querySelector('.path-hero__copy');
                const cover = document.querySelector('.path-hero__cover');
                const note = document.querySelector('.path-editorial-note');
                const pillar = document.querySelector('.path-pillar');
                const steps = document.querySelector('.path-steps');
                const firstStep = document.querySelector('.path-step');
                const firstStepNumber = document.querySelector('.path-step__number');
                const firstStepTitle = document.querySelector('.path-step h3');
                const ending = document.querySelector('.path-ending');
                if (!detail || !shell || !hero || !title || !copy || !cover || !note || !pillar || !steps || !firstStep || !firstStepNumber || !firstStepTitle || !ending) return null;

                const heroStyle = getComputedStyle(hero);
                const detailStyle = getComputedStyle(detail);
                const noteStyle = getComputedStyle(note);
                const pillarStyle = getComputedStyle(pillar);
                const stepsStyle = getComputedStyle(steps);
                const stepStyle = getComputedStyle(firstStep);
                const endingStyle = getComputedStyle(ending);
                return {
                    shell: shell.getBoundingClientRect().width,
                    hero: hero.getBoundingClientRect().width,
                    copy: copy.getBoundingClientRect().width,
                    cover: cover.getBoundingClientRect().width,
                    coverHeight: cover.getBoundingClientRect().height,
                    pillar: pillar.getBoundingClientRect().width,
                    step: firstStep.getBoundingClientRect().width,
                    titleSize: parseFloat(getComputedStyle(title).fontSize),
                    stepTitleSize: parseFloat(getComputedStyle(firstStepTitle).fontSize),
                    stepNumberSize: parseFloat(getComputedStyle(firstStepNumber).fontSize),
                    heroDisplay: heroStyle.display,
                    heroColumns: heroStyle.gridTemplateColumns,
                    heroRadius: parseFloat(heroStyle.borderRadius),
                    detailBackground: detailStyle.backgroundColor,
                    heroBackground: heroStyle.backgroundColor,
                    noteBorderTop: noteStyle.borderTopWidth,
                    pillarBackground: pillarStyle.backgroundColor,
                    pillarAccent: pillarStyle.borderLeftWidth,
                    stepsBackground: stepsStyle.backgroundColor,
                    stepBackground: stepStyle.backgroundColor,
                    stepRule: stepStyle.borderBottomWidth,
                    endingRule: endingStyle.borderTopWidth,
                };
            });

            expect(detailLayout).not.toBeNull();
            expect(detailLayout.shell).toBeGreaterThan(1150);
            expect(detailLayout.shell).toBeLessThanOrEqual(1220);
            expect(detailLayout.hero).toBeGreaterThan(1150);
            expect(detailLayout.pillar).toBeGreaterThan(1150);
            expect(detailLayout.step).toBeGreaterThan(1080);
            expect(detailLayout.heroDisplay).toBe('grid');
            expect(detailLayout.copy).toBeGreaterThan(450);
            expect(detailLayout.cover).toBeGreaterThan(500);
            expect(detailLayout.coverHeight).toBeGreaterThanOrEqual(500);
            expect(detailLayout.titleSize).toBeGreaterThanOrEqual(60);
            expect(detailLayout.stepTitleSize).toBeGreaterThanOrEqual(22);
            expect(detailLayout.stepNumberSize).toBeGreaterThanOrEqual(40);
            expect(detailLayout.heroRadius).toBeGreaterThanOrEqual(28);
            expect(detailLayout.heroColumns).not.toBe('none');
            expect(detailLayout.detailBackground).not.toBe('rgb(255, 255, 255)');
            expect(detailLayout.heroBackground).not.toBe(detailLayout.pillarBackground);
            expect(detailLayout.noteBorderTop).not.toBe('0px');
            expect(detailLayout.pillarAccent).not.toBe('0px');
            expect(detailLayout.stepsBackground).toBe('rgba(0, 0, 0, 0)');
            expect(detailLayout.stepBackground).toBe('rgba(0, 0, 0, 0)');
            expect(detailLayout.stepRule).not.toBe('0px');
            expect(detailLayout.endingRule).not.toBe('0px');
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

test('homepage Percorsi discovery uses editorial-scale cover on desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    const errors = watchPage(page);
    await page.goto('/');

    const card = page.locator('.home-path-link').first();
    const visual = card.locator('.home-path-link__visual');
    await expect(card).toBeVisible();
    await expect(visual).toBeVisible();

    const dimensions = await visual.evaluate(element => ({
        width: element.getBoundingClientRect().width,
        height: element.getBoundingClientRect().height,
        pageFits: document.documentElement.scrollWidth <= document.documentElement.clientWidth,
    }));

    expect(dimensions.width).toBeGreaterThanOrEqual(140);
    expect(dimensions.height).toBeGreaterThanOrEqual(100);
    expect(dimensions.pageFits).toBeTruthy();
    expect(errors).toEqual([]);
});

test('inactive Percorso returns 404', async ({ page }) => {
    const response = await page.goto('/percorsi/percorso-inattivo-ci');
    expect(response?.status()).toBe(404);
});
