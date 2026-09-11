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

// Prompt 11 (programma 100-prompt Kairus): clearExpiredNewsletterDismiss()
// veniva chiamata dopo la lettura di `dismissed` che decide l'apertura
// automatica, quindi un dismissal scaduto restava efficace per la request
// corrente ed era rimosso solo alla navigazione successiva. page.clock
// porta avanti il timer dei 30s senza un'attesa reale in CI.
test('popup auto-open honors an expired dismissal on the very same page load', async ({ page }) => {
    const expiredDismissal = Date.now() - 1000;
    await page.addInitScript(value => {
        localStorage.setItem('newsletter_dismissed', String(value));
    }, expiredDismissal);
    await page.clock.install({ time: Date.now() });

    const guards = await gotoPublicPage(page, fixture.routes.home);
    const dialog = page.getByRole('dialog', { name: 'Resta aggiornato su Kairus' });

    await expect(dialog).toHaveAttribute('hidden', '');
    await page.clock.fastForward('00:31');
    // .newsletter-popup ha `display:flex` incondizionato in CSS (apre/chiude
    // via opacity/pointer-events sulla classe .visible, non via display) —
    // toBeVisible() da solo risulterebbe vero anche a popup chiuso. L'unico
    // segnale affidabile che il gate JS abbia davvero aperto il popup e' la
    // rimozione dell'attributo hidden, come gia' negli altri test di questo
    // file per lo stato chiuso.
    await expect(dialog).not.toHaveAttribute('hidden', '');
    await expect(dialog).toHaveClass(/visible/);
    guards.assertClean();
});

test('popup auto-open stays suppressed while a dismissal is still within its 7-day window', async ({ page }) => {
    const activeDismissal = Date.now() + 6 * 24 * 60 * 60 * 1000;
    await page.addInitScript(value => {
        localStorage.setItem('newsletter_dismissed', String(value));
    }, activeDismissal);
    await page.clock.install({ time: Date.now() });

    const guards = await gotoPublicPage(page, fixture.routes.home);
    const dialog = page.getByRole('dialog', { name: 'Resta aggiornato su Kairus' });

    await page.clock.fastForward('00:31');
    await expect(dialog).toHaveAttribute('hidden', '');
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

// Relative luminance / contrast ratio per la formula WCAG 2.x, usata sotto
// per verificare il vincolo AA reale (>=3.0 testo grande / titolo, >=4.5
// testo normale / sommario e meta) sul PIXEL DI SFONDO effettivamente
// composto e renderizzato dal browser (screenshot reale dell'elemento,
// decodificato in canvas — non un valore CSS letto e ricalcolato a mano),
// contro il colore di testo dichiarato via CSS (esatto, non un pixel di
// glifo anti-aliased che introdurrebbe rumore).
function srgbToLinear(c) {
    const v = c / 255;
    return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
}

function relativeLuminance([r, g, b]) {
    return 0.2126 * srgbToLinear(r) + 0.7152 * srgbToLinear(g) + 0.0722 * srgbToLinear(b);
}

function contrastRatio(l1, l2) {
    const lighter = Math.max(l1, l2);
    const darker = Math.min(l1, l2);
    return (lighter + 0.05) / (darker + 0.05);
}

function parseCssColor(value) {
    const match = value.match(/rgba?\(([^)]+)\)/);
    if (!match) {
        throw new Error(`Unparseable CSS color: ${value}`);
    }
    const parts = match[1].split(',').map(part => parseFloat(part.trim()));
    const [r, g, b, a = 1] = parts;
    return { r, g, b, a };
}

function alphaBlend(fg, alpha, bg) {
    return fg.map((c, i) => alpha * c + (1 - alpha) * bg[i]);
}

/**
 * Cantiere fix/article-hero-cover-visibility: prima di questo fix,
 * `.article-premium__hero img` era a opacity .6 e
 * `.article-premium__overlay` applicava un gradiente fino a
 * rgba(2,6,23,.92), rendendo le copertine quasi invisibili su desktop.
 * Verifica sul PIXEL REALMENTE RENDERIZZATO (screenshot dell'elemento,
 * mai un valore CSS ricalcolato a mano) che l'immagine sia visibile nella
 * metà superiore e che il contrasto AA di titolo/sommario resti garantito
 * nella zona di testo, sul fixture deterministico hero-placeholder.svg
 * (sfondo quasi bianco: il caso peggiore realistico per il contrasto,
 * non quello più favorevole).
 */
for (const [label, width, height] of [
    ['320px', 320, 700],
    ['375px', 375, 812],
    ['mobile', 390, 844],
    ['768px', 768, 1024],
    ['1024px', 1024, 800],
    ['desktop', 1440, 900],
]) {
    test(`article hero cover is visible and title/excerpt stay AA-compliant at ${label}`, async ({ page }) => {
        await page.setViewportSize({ width, height });
        const guards = await gotoPublicPage(page, fixture.routes.article);

        const hero = page.locator('.article-premium__hero').first();
        await expect(hero).toBeVisible();
        await expect(hero.locator('img').first()).toHaveAttribute('src', /hero-placeholder\.svg/);

        const box = await hero.boundingBox();
        expect(box, 'hero bounding box must be measurable').not.toBeNull();

        const screenshot = await hero.screenshot();

        const styles = await page.evaluate(() => {
            const heroImg = document.querySelector('.article-premium__hero img');
            const overlay = document.querySelector('.article-premium__overlay');
            const h1 = document.querySelector('.article-premium__content h1');
            const excerpt = document.querySelector('.article-premium__excerpt');
            const meta = document.querySelector('.article-premium__meta');

            return {
                imgOpacity: parseFloat(getComputedStyle(heroImg).opacity),
                overlayBackgroundImage: getComputedStyle(overlay).backgroundImage,
                h1Color: h1 ? getComputedStyle(h1).color : null,
                excerptColor: excerpt ? getComputedStyle(excerpt).color : null,
                metaColor: meta ? getComputedStyle(meta).color : null,
            };
        });

        // Il fix concreto: opacità immagine alzata, non più a .6.
        expect(styles.imgOpacity).toBeGreaterThanOrEqual(0.85);
        // L'overlay non deve più raggiungere il livello di opacità (.92)
        // che rendeva la copertina quasi invisibile.
        expect(styles.overlayBackgroundImage).not.toContain('0.92');

        // Campiona il pixel REALMENTE composto (screenshot decodificato in
        // canvas) in due punti: vicino al bordo superiore (nessun testo lì
        // — deve essere chiaramente più luminoso di prima, prova diretta
        // di leggibilità) e vicino all'angolo inferiore destro (zona dove
        // sta il testo, ma fuori dall'area dei glifi per evitare rumore da
        // anti-aliasing).
        const base64 = screenshot.toString('base64');
        const samples = await page.evaluate(async ({ base64, boxWidth, boxHeight }) => {
            const img = new Image();
            img.src = `data:image/png;base64,${base64}`;
            await img.decode();

            const canvas = document.createElement('canvas');
            canvas.width = img.naturalWidth;
            canvas.height = img.naturalHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0);

            const scaleX = img.naturalWidth / boxWidth;
            const scaleY = img.naturalHeight / boxHeight;

            const pick = (relX, relY) => {
                const x = Math.min(img.naturalWidth - 1, Math.round(relX * boxWidth * scaleX));
                const y = Math.min(img.naturalHeight - 1, Math.round(relY * boxHeight * scaleY));
                const data = ctx.getImageData(x, y, 1, 1).data;
                return [data[0], data[1], data[2]];
            };

            return {
                // Angolo in alto a SINISTRA, non a destra: l'hero ospita
                // sempre (a ogni viewport) il badge di espansione
                // `.media-viewer__trigger--badge`, ancorato in alto a
                // destra con un offset quasi fisso in px (~18px) — a
                // scalare con la larghezza del box, un campione al 95%
                // orizzontale può ricadere proprio sopra quel badge scuro
                // a certe larghezze intermedie (verificato: succede fra
                // ~700 e ~1100px di larghezza hero, mai a mobile/desktop),
                // producendo un falso negativo di luminanza indipendente
                // dal fix opacity/overlay che questo test verifica.
                top: pick(0.05, 0.08),
                bottomText: pick(0.95, 0.92),
            };
        }, { base64, boxWidth: box.width, boxHeight: box.height });

        const topLuminance = relativeLuminance(samples.top);
        const bottomLuminance = relativeLuminance(samples.bottomText);

        // Metà superiore chiaramente leggibile: il fixture ha uno sfondo
        // quasi bianco (hero-placeholder.svg, luminanza ~0.9); con
        // l'immagine ora a opacità alta e overlay leggero in alto, il
        // pixel campionato deve restare marcatamente luminoso, non quasi
        // nero come prima del fix.
        expect(topLuminance, `top-of-hero luminance too low at ${label} — image still reads as nearly invisible`).toBeGreaterThan(0.35);

        // Contrasto AA nella zona di testo, sul caso peggiore realistico
        // (sfondo quasi bianco): titolo (testo grande, soglia 3:1) e
        // sommario/meta (testo normale, soglia 4.5:1), calcolati sul
        // colore CSS dichiarato via alpha-blend sopra il pixel di sfondo
        // realmente renderizzato.
        const h1Color = parseCssColor(styles.h1Color);
        const h1Contrast = contrastRatio(relativeLuminance([h1Color.r, h1Color.g, h1Color.b]), bottomLuminance);
        expect(h1Contrast, `title contrast at ${label}`).toBeGreaterThanOrEqual(3.0);

        if (styles.excerptColor) {
            const excerpt = parseCssColor(styles.excerptColor);
            const blended = alphaBlend([excerpt.r, excerpt.g, excerpt.b], excerpt.a, [samples.bottomText[0], samples.bottomText[1], samples.bottomText[2]]);
            const excerptContrast = contrastRatio(relativeLuminance(blended), bottomLuminance);
            expect(excerptContrast, `excerpt contrast at ${label}`).toBeGreaterThanOrEqual(4.5);
        }

        if (styles.metaColor) {
            const meta = parseCssColor(styles.metaColor);
            const blended = alphaBlend([meta.r, meta.g, meta.b], meta.a, [samples.bottomText[0], samples.bottomText[1], samples.bottomText[2]]);
            const metaContrast = contrastRatio(relativeLuminance(blended), bottomLuminance);
            expect(metaContrast, `meta contrast at ${label}`).toBeGreaterThanOrEqual(4.5);
        }

        // Nessun overflow orizzontale introdotto dal fix (opacity/gradient
        // non toccano il layout, ma verificato esplicitamente comunque).
        const dimensions = await page.evaluate(() => ({
            clientWidth: document.documentElement.clientWidth,
            scrollWidth: document.documentElement.scrollWidth,
        }));
        expect(dimensions.scrollWidth, `horizontal overflow on article page at ${label}`).toBeLessThanOrEqual(dimensions.clientWidth);

        await page.screenshot({
            path: `test-results/article-hero-${label}.png`,
            fullPage: false,
        });

        guards.assertClean();
    });
}
