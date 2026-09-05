import { expect, test } from '@playwright/test';

const viewportWidths = [390, 430, 768, 1024, 1440];

function watchPage(page) {
    const errors = [];
    page.on('console', message => {
        if (message.type() === 'error') errors.push(`console: ${message.text()}`);
    });
    page.on('pageerror', error => errors.push(`pageerror: ${error.message}`));
    return errors;
}

test.describe('Percorsi narrative preview progressive enhancement', () => {
    test.use({ javaScriptEnabled: false, viewport: { width: 390, height: 900 } });

    test('keeps the server-rendered preview and article links usable without JavaScript', async ({ page }) => {
        await page.goto('/percorsi');

        await expect(page.locator('.path-card__preview').first()).toBeVisible();
        await expect(page.getByRole('button', { name: 'Anteprima delle tappe' }).first()).toBeHidden();
        await expect(page.getByRole('link', { name: 'Turing e il browser regression harness' }).first()).toBeVisible();
        await expect(page.getByRole('link', { name: 'Esplora il percorso' }).first()).toBeVisible();
    });
});

test('Percorsi narrative preview honors reduced motion', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/percorsi');

    const transition = await page.locator('.path-card__preview').first().evaluate((element) => getComputedStyle(element).transitionDuration);
    expect(transition).toBe('0s');
});

for (const width of viewportWidths) {
    test(`Percorsi public surfaces are safe at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const errors = watchPage(page);

        await page.goto('/percorsi');
        await expect(page.locator('main')).toBeVisible();
        await expect(page.getByRole('heading', { level: 1, name: 'Percorsi' })).toBeVisible();
        await expect(page.getByText('Archivio editoriale')).toBeVisible();
        await expect(page.getByRole('link', { name: 'IA spiegata' }).first()).toBeVisible();
        await expect(page.getByText('2 articoli')).toBeVisible();
        await expect(page.getByText('Articolo programmato da non mostrare')).toHaveCount(0);
        await expect(page.locator('.path-card')).toHaveCount(6);
        await expect(page.getByRole('navigation', { name: 'Paginazione Percorsi' })).toBeVisible();

        const indexLayout = await page.evaluate(() => {
            const index = document.querySelector('.paths-index');
            const grid = document.querySelector('.paths-grid');
            const card = document.querySelector('.path-card');
            const media = document.querySelector('.kairus-path-card__media');
            if (!index || !grid || !card || !media) return null;

            const indexStyle = getComputedStyle(index);
            const gridStyle = getComputedStyle(grid);
            const cardStyle = getComputedStyle(card);
            return {
                background: indexStyle.backgroundColor,
                gridColumns: gridStyle.gridTemplateColumns,
                cardWidth: card.getBoundingClientRect().width,
                cardHeight: card.getBoundingClientRect().height,
                cardRadius: parseFloat(cardStyle.borderRadius),
                cardBackground: cardStyle.backgroundColor,
                mediaWidth: media.getBoundingClientRect().width,
                mediaHeight: media.getBoundingClientRect().height,
                mediaRatio: media.getBoundingClientRect().width / media.getBoundingClientRect().height,
                pageFits: document.documentElement.scrollWidth <= document.documentElement.clientWidth,
            };
        });

        expect(indexLayout).not.toBeNull();
        expect(indexLayout.background).not.toBe('rgb(255, 255, 255)');
        expect(indexLayout.cardBackground).not.toBe('rgb(255, 255, 255)');
        expect(indexLayout.cardRadius).toBeGreaterThanOrEqual(width <= 760 ? 18 : 22);
        expect(indexLayout.pageFits).toBeTruthy();

        expect(indexLayout.mediaRatio).toBeGreaterThan(width <= 430 ? 1.95 : 2.35);
        expect(indexLayout.mediaRatio).toBeLessThan(width <= 430 ? 2.05 : 2.45);

        if (width <= 430) {
            expect(indexLayout.gridColumns.trim().split(' ').length).toBe(1);
            expect(indexLayout.cardWidth).toBeGreaterThan(300);
            expect(indexLayout.mediaWidth).toBeGreaterThan(300);
            expect(indexLayout.mediaHeight).toBeGreaterThan(135);
        } else {
            const minimumCardWidth = width === 768 ? 330 : (width === 1024 ? 430 : 540);
            expect(indexLayout.gridColumns.trim().split(' ').length).toBe(2);
            expect(indexLayout.cardWidth).toBeGreaterThan(minimumCardWidth);
            expect(indexLayout.mediaWidth).toBeGreaterThan(minimumCardWidth);
            expect(indexLayout.cardHeight).toBeLessThan(620);
        }

        await expect(page.getByRole('link', { name: 'Esplora il percorso' }).first()).toBeVisible();

        const iaCard = page.locator('.path-card').filter({ hasText: 'IA spiegata' });
        const preview = iaCard.locator('.path-card__preview');
        if (width <= 430) {
            const toggle = page.getByRole('button', { name: 'Anteprima delle tappe' }).first();
            await expect(toggle).toBeVisible();
            await expect(toggle).toHaveAttribute('aria-expanded', 'false');
            await expect(preview).toBeHidden();
            await toggle.focus();
            await page.keyboard.press('Enter');
            await expect(toggle).toHaveAttribute('aria-expanded', 'true');
            await expect(toggle).toBeFocused();
            await expect(preview).toBeVisible();
            await page.keyboard.press('Space');
            await expect(toggle).toHaveAttribute('aria-expanded', 'false');
            await expect(preview).toBeHidden();
        } else {
            await expect(page.getByRole('button', { name: 'Anteprima delle tappe' }).first()).toBeHidden();
            await expect(preview).toBeVisible();
        }

        await expect(preview.getByText('Articolo programmato da non mostrare')).toHaveCount(0);
        await expect(preview.getByText('Dalle macchine ai modelli moderni')).toHaveCount(0);

        if (width === 1440) {
            await page.getByRole('link', { name: 'Pagina successiva' }).click();
            await expect(page).toHaveURL(/\/percorsi\?page=2$/);
            await expect(page.locator('.path-card')).toHaveCount(1);
            const longTitle = page.getByRole('link', { name: 'Un Percorso dal titolo volutamente molto lungo per verificare la robustezza della card' }).first();
            await expect(longTitle).toBeVisible();
            await longTitle.click();
            await expect(page).toHaveURL(/\/percorsi\/percorso-paginazione-ci-5$/);
            await expect(page.locator('main')).toBeVisible();
            await page.goBack();
            await page.getByRole('link', { name: 'Pagina precedente' }).click();
            await expect(page).toHaveURL(/\/percorsi$/);
        }

        const indexPathLink = page.getByRole('link', { name: 'IA spiegata' }).first();
        await indexPathLink.click();
        await expect(page).toHaveURL(/\/percorsi\/ia-spiegata$/);
        await expect(page.locator('main')).toBeVisible();
        await expect(page.getByRole('heading', { level: 1, name: 'IA spiegata' })).toBeVisible();
        await expect(page.getByRole('heading', { level: 2, name: 'Perché questo percorso' })).toBeVisible();
        await expect(page.getByRole('heading', { level: 2, name: 'Non siamo ancora arrivati alla fine.' })).toBeVisible();
        const continuation = page.locator('[data-path-continues]');
        await expect(continuation).toBeVisible();
        await expect(continuation.getByText('Percorso in aggiornamento', { exact: false })).toBeVisible();
        await expect(continuation.getByText("Stiamo preparando nuovi capitoli per continuare l'esplorazione.", { exact: false })).toBeVisible();
        await expect(continuation.getByText('Torna presto: la prossima tappa arriverà qui.', { exact: false })).toBeVisible();
        await expect(continuation.getByText('Qui riprenderà il viaggio.')).toBeVisible();
        await expect(page.getByText('3 tappe disponibili')).toHaveCount(0);
        await expect(page.getByText('quando il lavoro editoriale sarà pronto')).toHaveCount(0);
        await expect(continuation.getByRole('button', { name: 'Avvisami quando continua' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Turing e il browser regression harness' }).first()).toBeVisible();
        await expect(page.getByRole('link', { name: 'Dalle macchine ai modelli moderni' }).first()).toBeVisible();
        await expect(page.getByText('Articolo programmato da non mostrare')).toHaveCount(0);
        await expect(page.locator('link[href*="content-clusters-detail.css"]')).toHaveCount(1);
        await expect(page.locator('link[href*="media-lightbox.css"]')).toHaveCount(1);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();

        if (width === 1440) {
            const detailLayout = await page.evaluate(() => {
                const detail = document.querySelector('.path-detail');
                const shell = document.querySelector('.path-detail > .container');
                const hero = document.querySelector('.path-hero');
                const heroMedia = document.querySelector('.path-hero img');
                const title = document.querySelector('.path-hero h1');
                const copy = document.querySelector('.path-hero__copy');
                const note = document.querySelector('.path-entrance');
                const noteCopy = document.querySelector('.path-entrance__copy');
                const pillar = document.querySelector('.path-pillar');
                const steps = document.querySelector('.path-steps');
                const firstStep = document.querySelector('.path-step');
                const firstStepNumber = document.querySelector('.path-step__number');
                const firstStepTitle = document.querySelector('.path-step h3');
                const ending = document.querySelector('.path-ending');
                if (!detail || !shell || !hero || !heroMedia || !title || !copy || !note || !noteCopy || !pillar || !steps || !firstStep || !firstStepNumber || !firstStepTitle || !ending) return null;

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
                    heroHeight: hero.getBoundingClientRect().height,
                    copy: copy.getBoundingClientRect().width,
                    pillar: pillar.getBoundingClientRect().width,
                    step: firstStep.getBoundingClientRect().width,
                    titleSize: parseFloat(getComputedStyle(title).fontSize),
                    stepTitleSize: parseFloat(getComputedStyle(firstStepTitle).fontSize),
                    stepNumberSize: parseFloat(getComputedStyle(firstStepNumber).fontSize),
                    heroDisplay: heroStyle.display,
                    heroMediaWidth: heroMedia.getBoundingClientRect().width,
                    heroMediaHeight: heroMedia.getBoundingClientRect().height,
                    heroMediaAlt: heroMedia.getAttribute('alt'),
                    heroRadius: parseFloat(heroStyle.borderRadius),
                    detailBackground: detailStyle.backgroundColor,
                    heroBackground: heroStyle.backgroundColor,
                    noteBackground: noteStyle.backgroundColor,
                    noteBorder: noteStyle.borderTopWidth,
                    noteRadius: parseFloat(noteStyle.borderRadius),
                    pillarBackground: pillarStyle.backgroundColor,
                    pillarAccent: pillarStyle.borderLeftWidth,
                    pillarRadius: parseFloat(pillarStyle.borderRadius),
                    stepsBackground: stepsStyle.backgroundColor,
                    stepsBorder: stepsStyle.borderTopWidth,
                    stepsRadius: parseFloat(stepsStyle.borderRadius),
                    stepBackground: stepStyle.backgroundColor,
                    stepRule: stepStyle.borderBottomWidth,
                    endingBackground: endingStyle.backgroundColor,
                    endingRule: endingStyle.borderTopWidth,
                    endingRadius: parseFloat(endingStyle.borderRadius),
                };
            });

            expect(detailLayout).not.toBeNull();
            expect(detailLayout.shell).toBeGreaterThan(1150);
            expect(detailLayout.shell).toBeLessThanOrEqual(1220);
            expect(detailLayout.hero).toBeGreaterThan(1150);
            expect(detailLayout.heroHeight).toBeGreaterThan(500);
            expect(detailLayout.pillar).toBeGreaterThan(1150);
            expect(detailLayout.step).toBeGreaterThan(1000);
            expect(detailLayout.heroDisplay).toBe('flex');
            expect(detailLayout.copy).toBeGreaterThan(450);
            expect(detailLayout.heroMediaWidth).toBeGreaterThanOrEqual(1150);
            expect(detailLayout.heroMediaHeight).toBeGreaterThan(500);
            expect(detailLayout.heroMediaAlt).toBe('Cover del percorso IA spiegata');
            expect(detailLayout.titleSize).toBeGreaterThanOrEqual(60);
            expect(detailLayout.stepTitleSize).toBeGreaterThanOrEqual(22);
            expect(detailLayout.stepNumberSize).toBeGreaterThanOrEqual(40);
            expect(detailLayout.heroRadius).toBeGreaterThanOrEqual(28);
            expect(detailLayout.detailBackground).not.toBe('rgb(255, 255, 255)');
            expect(detailLayout.heroBackground).not.toBe(detailLayout.pillarBackground);
            expect(detailLayout.noteBackground).not.toBe('rgba(0, 0, 0, 0)');
            expect(detailLayout.noteBorder).not.toBe('0px');
            expect(detailLayout.noteRadius).toBeGreaterThanOrEqual(18);
            expect(detailLayout.pillarAccent).not.toBe('0px');
            expect(detailLayout.pillarRadius).toBeGreaterThanOrEqual(18);
            expect(detailLayout.stepsBackground).not.toBe('rgba(0, 0, 0, 0)');
            expect(detailLayout.stepsBorder).not.toBe('0px');
            expect(detailLayout.stepsRadius).toBeGreaterThanOrEqual(20);
            expect(detailLayout.stepBackground).toBe('rgba(0, 0, 0, 0)');
            expect(detailLayout.stepRule).not.toBe('0px');
            expect(detailLayout.endingBackground).not.toBe('rgba(0, 0, 0, 0)');
            expect(detailLayout.endingRule).not.toBe('0px');
            expect(detailLayout.endingRadius).toBeGreaterThanOrEqual(18);
        }

        await page.goto('/percorsi/percorso-completo-ci');
        await expect(page.getByRole('heading', { level: 2, name: 'Fine del percorso' })).toBeVisible();
        await expect(page.locator('[data-path-continues]')).toHaveCount(0);
        await expect(page.getByText('Percorso in aggiornamento')).toHaveCount(0);
        await expect(page.getByText('Torna presto: la prossima tappa arriverà qui.', { exact: false })).toHaveCount(0);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();

        expect(errors).toEqual([]);
    });

    test(`article continuation stops safely before scheduled content at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const errors = watchPage(page);

        await page.goto('/articolo/browser-turing-article');
        const box = page.locator('.path-continuation');
        await expect(box).toBeVisible();
        await expect(box.getByRole('heading', { name: 'Continua il percorso' })).toBeVisible();
        await expect(box.getByText(/^\d+ di \d+$/)).toBeVisible();
        await expect(box.getByText('Articolo programmato da non mostrare')).toHaveCount(0);
        await expect(box.locator('[data-path-continues]')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();

        await expect(box.locator('a[data-path-event="path_next_click"]')).toHaveCount(0);
        await expect(box.getByText("Hai raggiunto l'ultima tappa disponibile.")).toBeVisible();
        await expect(box.getByText('Il prossimo capitolo arriverà qui.')).toBeVisible();
        await expect(box.getByRole('button', { name: 'Avvisami quando continua' })).toBeVisible();

        await box.getByRole('link', { name: /Vedi tutto il percorso/ }).click();
        await expect(page).toHaveURL(/\/percorsi\/ia-spiegata$/);
        await expect(page.getByRole('heading', { level: 1, name: 'IA spiegata' })).toBeVisible();
        await expect(page.getByText('Articolo programmato da non mostrare')).toHaveCount(0);

        const laterPublishedLink = page.getByRole('link', { name: 'Dalle macchine ai modelli moderni' }).first();
        await expect(laterPublishedLink).toBeVisible();
        await expect(laterPublishedLink).toHaveAttribute('href', /\/articolo\/browser-path-last-article$/);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();

        expect(errors).toEqual([]);
    });
}

test('continuation destination is unique and keyboard-visible at 390px', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 900 });
    const errors = watchPage(page);

    await page.goto('/articolo/browser-path-last-article');

    const continuationCard = page.locator('.continue-reading__card');
    await expect(continuationCard).toBeVisible();
    await expect(continuationCard).toHaveAttribute('href', /\/articolo\/browser-complete-path-article/);
    await expect(page.locator('.related-premium-grid a[href*="/articolo/browser-complete-path-article"]')).toHaveCount(0);

    await continuationCard.focus();
    await expect(continuationCard).toBeFocused();
    const focusStyle = await continuationCard.evaluate(element => {
        const style = getComputedStyle(element);

        return {
            outlineStyle: style.outlineStyle,
            outlineWidth: parseFloat(style.outlineWidth),
            pageFits: document.documentElement.scrollWidth <= document.documentElement.clientWidth,
        };
    });

    expect(focusStyle.outlineStyle).not.toBe('none');
    expect(focusStyle.outlineWidth).toBeGreaterThanOrEqual(2);
    expect(focusStyle.pageFits).toBeTruthy();
    expect(errors).toEqual([]);
});

test('homepage Percorsi discovery uses editorial-scale cover on desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    const errors = watchPage(page);
    await page.goto('/');

    const card = page.locator('.home-path-link').first();
    const visual = card.locator('.kairus-path-card__media');
    await expect(card).toBeVisible();
    await expect(visual).toBeVisible();

    const dimensions = await visual.evaluate(element => ({
        width: element.getBoundingClientRect().width,
        height: element.getBoundingClientRect().height,
        pageFits: document.documentElement.scrollWidth <= document.documentElement.clientWidth,
    }));

    expect(dimensions.width).toBeGreaterThanOrEqual(300);
    expect(dimensions.height).toBeGreaterThanOrEqual(150);
    expect(dimensions.pageFits).toBeTruthy();
    expect(errors).toEqual([]);
});

test('inactive Percorso returns 404', async ({ page }) => {
    const response = await page.goto('/percorsi/percorso-inattivo-ci');
    expect(response?.status()).toBe(404);
});

test('"Avvisami quando continua" form expands, submits, and shows a neutral success state', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 900 });
    const errors = watchPage(page);

    await page.goto('/percorsi/ia-spiegata');

    const cta = page.locator('[data-path-continues] [data-path-subscribe-toggle]');
    const form = page.locator('[data-path-continues] [data-path-subscribe-form]');

    await expect(cta).toBeVisible();
    await expect(form).toBeHidden();
    await expect(cta).toHaveAttribute('aria-expanded', 'false');

    await cta.click();

    await expect(form).toBeVisible();
    await expect(cta).toHaveAttribute('aria-expanded', 'true');

    const emailField = form.locator('input[type="email"]');
    await expect(emailField).toBeFocused();
    await emailField.fill('lettore-playwright@example.com');
    await form.getByRole('button', { name: 'Avvisami' }).click();

    await expect(page).toHaveURL(/\/percorsi\/ia-spiegata$/);
    await expect(page.getByText("Grazie! Se il tuo indirizzo richiede conferma, riceverai un'email a breve.")).toBeVisible();
    await expect(page.locator('[data-path-subscribe-toggle]')).toHaveCount(0);

    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();
    expect(errors).toEqual([]);
});
