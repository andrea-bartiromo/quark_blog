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
        if (message.type() !== 'error') {
            return;
        }

        // Stesso criterio "solo prima parte" gia' applicato sopra a
        // requestfailed/response: un browser reale che non riesce a
        // caricare una risorsa di terze parti (font, script analytics,
        // ecc.) logga un console.error nativo del browser attribuito
        // all'URL di QUELLA risorsa, non al documento della pagina —
        // Playwright espone questa origine reale in message.location().url.
        // Un errore genuino dell'applicazione non ha mai un location.url
        // di terze parti: o e' vuoto (chiamata da codice iniettato/eval)
        // o punta alla pagina/allo script di primo livello stesso. Non e'
        // un allowlist di domini: e' lo stesso confine "primo livello vs
        // terze parti" gia' in uso per gli altri due guard, applicato qui
        // per coerenza.
        const locationUrl = message.location()?.url;
        if (locationUrl) {
            try {
                const origin = new URL(locationUrl).hostname;
                if (origin !== '127.0.0.1' && origin !== 'localhost') {
                    return;
                }
            } catch {
                // location.url non e' un URL assoluto valido: non e' il
                // caso delle risorse di terze parti osservate, quindi si
                // tratta il messaggio come primo livello per prudenza.
            }
        }

        severeConsole.push(`console.error: ${message.text()}`);
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

test('ticker autoplays with visibly measurable motion in normal mode', async ({ page }) => {
    test.setTimeout(15_000);
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    const guards = await gotoPublicPage(page, fixture.routes.home);
    const track = page.locator('.ticker-track');
    const viewport = page.locator('.ticker-viewport');
    const firstLink = track.locator('.ticker-sequence').first().getByRole('link').first();

    await expect(track).toBeVisible();
    await expect(track.locator('.ticker-sequence')).toHaveCount(4);

    const initial = await track.evaluate(element => {
        const style = getComputedStyle(element);
        return {
            animationName: style.animationName,
            animationDuration: style.animationDuration,
            playState: style.animationPlayState,
        };
    });
    const startBox = await track.boundingBox();

    expect(initial.animationName).toBe('kairus-ticker-loop');
    expect(initial.animationDuration).not.toBe('0s');
    expect(initial.playState).toBe('running');
    expect(startBox).not.toBeNull();

    await page.waitForTimeout(1000);
    const endBox = await track.boundingBox();
    expect(endBox).not.toBeNull();
    expect(Math.abs(endBox.x - startBox.x)).toBeGreaterThanOrEqual(15);

    const viewportBox = await viewport.boundingBox();
    expect(viewportBox).not.toBeNull();
    await page.mouse.move(
        viewportBox.x + viewportBox.width / 2,
        viewportBox.y + viewportBox.height / 2,
    );
    await expect.poll(() => track.evaluate(element => getComputedStyle(element).animationPlayState)).toBe('running');

    await firstLink.focus();
    await expect(firstLink).toBeFocused();
    await expect.poll(() => track.evaluate(element => getComputedStyle(element).animationPlayState)).toBe('running');

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

for (const width of viewportWidths) {
    test(`article lightbox is keyboard-safe and restores focus at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        const guards = await gotoPublicPage(page, fixture.routes.article);
        const trigger = page.getByRole('link', { name: 'Visualizza immagine completa' });
        const dialog = page.getByRole('dialog', { name: fixture.title });
        const close = dialog.getByRole('button', { name: 'Chiudi' });

        await trigger.focus();
        await page.keyboard.press('Enter');
        await expect(dialog).toBeVisible();
        await expect(close).toBeFocused();
        await expect(dialog).toHaveAttribute('aria-modal', 'true');

        await page.keyboard.press('Shift+Tab');
        await expect(dialog.getByRole('button', { name: 'Ingrandisci' })).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(close).toBeFocused();

        await page.keyboard.press('Escape');
        await expect(dialog).toHaveAttribute('hidden', '');
        await expect(dialog).not.toHaveClass(/is-open/);
        await expect(trigger).toBeFocused();
        guards.assertClean();
    });
}
