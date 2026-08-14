import { expect, test } from '@playwright/test';

const fixture = {
    title: 'Turing e il browser regression harness',
    author: 'Browser Test Author',
    category: 'Intelligenza Artificiale',
    routes: {
        home: '/',
        search: '/ricerca?q=turing',
        article: '/articolo/browser-turing-article',
        author: '/autore/1',
        category: '/categoria/intelligenza-artificiale',
        news: '/notizie',
    },
};

const representativeRoutes = Object.values(fixture.routes);
const viewportWidths = [390, 768, 1440];

function installBrowserGuards(page) {
    const failures = [];
    const severeConsole = [];
    let legacyMainJsRequested = false;

    page.on('request', request => {
        const url = new URL(request.url());
        if (url.origin === new URL(page.url() || 'http://127.0.0.1:8000').origin && url.pathname === '/js/main.js') {
            legacyMainJsRequested = true;
        }
    });

    page.on('requestfailed', request => {
        const url = new URL(request.url());
        if (url.hostname === '127.0.0.1' || url.hostname === 'localhost') {
            failures.push(`${request.resourceType()} ${url.pathname}: ${request.failure()?.errorText ?? 'request failed'}`);
        }
    });

    page.on('response', response => {
        const url = new URL(response.url());
        if (url.hostname !== '127.0.0.1' && url.hostname !== 'localhost') {
            return;
        }

        const resourceType = response.request().resourceType();
        const isCriticalAsset = ['stylesheet', 'script', 'image'].includes(resourceType);
        const isAppFailure = resourceType === 'document' || resourceType === 'fetch' || resourceType === 'xhr';

        if ((isCriticalAsset && response.status() >= 400) || (isAppFailure && response.status() >= 500)) {
            failures.push(`${resourceType} ${url.pathname}: HTTP ${response.status()}`);
        }
    });

    page.on('pageerror', error => severeConsole.push(`pageerror: ${error.message}`));
    page.on('console', message => {
        if (message.type() === 'error') {
            severeConsole.push(`console.error: ${message.text()}`);
        }
    });

    return {
        assertClean() {
            expect(legacyMainJsRequested, 'legacy /js/main.js must not be requested').toBe(false);
            expect(failures, 'meaningful first-party request failures').toEqual([]);
            expect(severeConsole, 'severe browser console/runtime errors').toEqual([]);
        },
    };
}

async function gotoPublicPage(page, route) {
    const guards = installBrowserGuards(page);
    const response = await page.goto(route, { waitUntil: 'domcontentloaded' });

    expect(response, `response for ${route}`).not.toBeNull();
    expect(response.status(), `HTTP status for ${route}`).toBeLessThan(400);
    await expect(page.locator('body > main')).toBeVisible();
    await expect(page.getByRole('banner')).toBeVisible();
    await expect(page.locator('header nav').first()).toBeAttached();

    return guards;
}

for (const width of viewportWidths) {
    test.describe(`layout contract at ${width}px`, () => {
        test.use({ viewport: { width, height: 900 } });

        for (const route of representativeRoutes) {
            test(`${route} has stable core layout`, async ({ page }) => {
                const guards = await gotoPublicPage(page, route);
                const dimensions = await page.evaluate(() => ({
                    clientWidth: document.documentElement.clientWidth,
                    scrollWidth: document.documentElement.scrollWidth,
                }));

                expect(dimensions.scrollWidth, `horizontal overflow on ${route}`).toBeLessThanOrEqual(dimensions.clientWidth);
                guards.assertClean();
            });
        }
    });
}

test.describe('semantic public page contracts', () => {
    test.use({ viewport: { width: 1440, height: 1000 } });

    test('search returns the deterministic Turing article', async ({ page }) => {
        const guards = await gotoPublicPage(page, fixture.routes.search);
        await expect(page.getByRole('heading', { level: 1 })).toContainText('turing', { ignoreCase: true });
        await expect(page.getByRole('textbox', { name: 'Cerca nel sito' })).toHaveValue('turing');
        await expect(page.getByRole('link', { name: fixture.title, exact: true })).toBeVisible();
        guards.assertClean();
    });

    test('article exposes title, body, author, cover and TOC', async ({ page }) => {
        const guards = await gotoPublicPage(page, fixture.routes.article);
        await expect(page.getByRole('heading', { level: 1, name: fixture.title })).toBeVisible();
        await expect(page.locator('.article-premium__body')).toContainText('Alan Turing');
        await expect(page.locator('.article-premium__hero img').first()).toHaveAttribute('src', /hero-placeholder\.svg/);
        await expect(page.getByRole('navigation', { name: 'Indice articolo' }).first()).toBeAttached();
        await expect(page.getByText(fixture.author, { exact: true }).first()).toBeVisible();
        guards.assertClean();
    });

    test('author, category and news pages expose fixture content', async ({ page }) => {
        for (const [route, heading] of [
            [fixture.routes.author, fixture.author],
            [fixture.routes.category, fixture.category],
            [fixture.routes.news, 'Tutti gli articoli'],
        ]) {
            const guards = await gotoPublicPage(page, route);
            await expect(page.getByRole('heading', { level: 1, name: heading })).toBeVisible();
            await expect(page.getByText(fixture.title, { exact: true }).first()).toBeVisible();
            guards.assertClean();
        }
    });

    test('representative first-party images use non-empty valid sources', async ({ page }) => {
        for (const route of representativeRoutes) {
            const guards = await gotoPublicPage(page, route);
            const sources = await page.locator('img').evaluateAll(images => images.map(image => image.getAttribute('src')));

            for (const source of sources) {
                expect(source, `empty image src on ${route}`).toBeTruthy();
                expect(() => new URL(source, page.url())).not.toThrow();
            }

            guards.assertClean();
        }
    });
});

test('ticker autoplays continuously in normal motion, including pointer and keyboard focus', async ({ page }) => {
    test.setTimeout(15_000);
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    const guards = await gotoPublicPage(page, fixture.routes.home);
    const track = page.locator('.ticker-track');
    const viewport = page.locator('.ticker-viewport');
    const firstLink = track.locator('.ticker-sequence').first().getByRole('link').first();

    await expect(track).toBeVisible();
    await expect(track.locator('.ticker-sequence')).toHaveCount(2);

    const initial = await track.evaluate(element => {
        const style = getComputedStyle(element);
        return {
            animationName: style.animationName,
            animationDuration: style.animationDuration,
            playState: style.animationPlayState,
            transform: style.transform,
        };
    });

    expect(initial.animationName).toContain('ticker-scroll');
    expect(initial.animationDuration).not.toBe('0s');
    expect(initial.playState).toBe('running');

    await page.waitForTimeout(350);
    const movingTransform = await track.evaluate(element => getComputedStyle(element).transform);
    expect(movingTransform).not.toBe(initial.transform);

    const viewportBox = await viewport.boundingBox();
    expect(viewportBox).not.toBeNull();
    await page.mouse.move(
        viewportBox.x + viewportBox.width / 2,
        viewportBox.y + viewportBox.height / 2,
    );
    await expect.poll(() => track.evaluate(element => getComputedStyle(element).animationPlayState)).toBe('running');
    const hoveredTransform = await track.evaluate(element => getComputedStyle(element).transform);
    await page.waitForTimeout(350);
    expect(await track.evaluate(element => getComputedStyle(element).transform)).not.toBe(hoveredTransform);

    await firstLink.focus();
    await expect(firstLink).toBeFocused();
    await expect.poll(() => track.evaluate(element => getComputedStyle(element).animationPlayState)).toBe('running');
    const focusedTransform = await track.evaluate(element => getComputedStyle(element).transform);
    await page.waitForTimeout(350);
    expect(await track.evaluate(element => getComputedStyle(element).transform)).not.toBe(focusedTransform);

    const overflow = await viewport.evaluate(element => ({
        overflowX: getComputedStyle(element).overflowX,
        pageFits: document.documentElement.scrollWidth <= document.documentElement.clientWidth,
    }));
    expect(overflow.overflowX).toBe('hidden');
    expect(overflow.pageFits).toBeTruthy();
    guards.assertClean();
});

test('ticker reduced motion disables autoplay but keeps manual horizontal access', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 900 });
    await page.emulateMedia({ reducedMotion: 'reduce' });
    const guards = await gotoPublicPage(page, fixture.routes.home);
    const track = page.locator('.ticker-track');
    const viewport = page.locator('.ticker-viewport');

    await expect(track).toBeVisible();
    const state = await page.evaluate(() => {
        const trackElement = document.querySelector('.ticker-track');
        const viewportElement = document.querySelector('.ticker-viewport');
        const duplicate = document.querySelector('.ticker-sequence[aria-hidden="true"]');
        if (!trackElement || !viewportElement || !duplicate) return null;

        const trackStyle = getComputedStyle(trackElement);
        const viewportStyle = getComputedStyle(viewportElement);
        return {
            animationName: trackStyle.animationName,
            transform: trackStyle.transform,
            overflowX: viewportStyle.overflowX,
            duplicateDisplay: getComputedStyle(duplicate).display,
            scrollWidth: viewportElement.scrollWidth,
            clientWidth: viewportElement.clientWidth,
            pageFits: document.documentElement.scrollWidth <= document.documentElement.clientWidth,
        };
    });

    expect(state).not.toBeNull();
    expect(state.animationName).toBe('none');
    expect(state.transform).toBe('none');
    expect(state.overflowX).toBe('auto');
    expect(state.duplicateDisplay).toBe('none');
    expect(state.scrollWidth).toBeGreaterThan(state.clientWidth);
    expect(state.pageFits).toBeTruthy();
    guards.assertClean();
});

test('newsletter modal traps keyboard focus and restores semantic closed state without submitting', async ({ page }) => {
    const guards = await gotoPublicPage(page, fixture.routes.home);
    const trigger = page.getByRole('button', { name: /Newsletter/ });
    const dialog = page.getByRole('dialog', { name: 'Resta aggiornato su Kairus' });
    const email = dialog.getByRole('textbox', { name: 'Indirizzo email' });
    const close = dialog.getByRole('button', { name: 'Chiudi' });
    const submit = dialog.getByRole('button', { name: 'Iscriviti gratis' });

    await trigger.focus();
    await trigger.click();
    await expect(dialog).toBeVisible();
    await expect(email).toBeFocused();

    await page.keyboard.press('Shift+Tab');
    await expect(close).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(email).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(submit).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(close).toBeFocused();

    await page.keyboard.press('Escape');
    await expect(dialog).toHaveAttribute('hidden', '');
    await expect(trigger).toBeFocused();
    await expect(dialog).toHaveAttribute('inert', '');
    await expect(email).not.toBeFocused();
    guards.assertClean();
});
