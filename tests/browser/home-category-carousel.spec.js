import { expect, test } from '@playwright/test';

const home = '/';

async function carouselState(page) {
    return page.locator('[data-category-track]').evaluate(track => {
        const trackRect = track.getBoundingClientRect();
        const tiles = [...track.querySelectorAll('.home-category-tile')];
        const visibleTiles = tiles.filter(tile => {
            const rect = tile.getBoundingClientRect();
            return rect.right > trackRect.left + 1 && rect.left < trackRect.right - 1;
        }).length;

        return {
            tileCount: tiles.length,
            visibleTiles,
            scrollLeft: track.scrollLeft,
            scrollWidth: track.scrollWidth,
            clientWidth: track.clientWidth,
            scrollHeight: track.scrollHeight,
            clientHeight: track.clientHeight,
            pageScrollWidth: document.documentElement.scrollWidth,
            pageClientWidth: document.documentElement.clientWidth,
        };
    });
}

async function openCarousel(page) {
    const response = await page.goto(home, { waitUntil: 'domcontentloaded' });
    expect(response).not.toBeNull();
    expect(response.status()).toBeLessThan(400);

    const track = page.locator('[data-category-track]');
    const tiles = track.locator('.home-category-tile');

    await expect(track).toBeVisible();
    expect(await tiles.count()).toBeGreaterThanOrEqual(10);
    await expect(tiles.nth(6)).toBeAttached();
    await expect(tiles.nth(9)).toBeAttached();

    return { track, tiles };
}

test('desktop exposes all categories and supports controls and arrow keys', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.emulateMedia({ reducedMotion: 'reduce' });
    const { track } = await openCarousel(page);
    const initial = await carouselState(page);
    expect(initial.tileCount).toBeGreaterThanOrEqual(10);
    expect(initial.visibleTiles).toBeGreaterThanOrEqual(5);
    expect(initial.visibleTiles).toBeLessThanOrEqual(6);
    expect(initial.scrollWidth).toBeGreaterThan(initial.clientWidth);
    expect(initial.scrollHeight).toBeLessThanOrEqual(initial.clientHeight + 2);
    expect(initial.pageScrollWidth).toBeLessThanOrEqual(initial.pageClientWidth);

    await track.focus();
    await expect(track).toBeFocused();
    await page.keyboard.press('ArrowRight');
    await expect.poll(async () => (await carouselState(page)).scrollLeft).toBeGreaterThan(0);

    const rightPosition = (await carouselState(page)).scrollLeft;
    await page.keyboard.press('ArrowLeft');
    await expect.poll(async () => (await carouselState(page)).scrollLeft).toBeLessThan(rightPosition);
});

for (const viewport of [
    { name: 'tablet', width: 768, minVisible: 3, maxVisible: 4 },
    { name: 'mobile', width: 390, minVisible: 1, maxVisible: 2 },
]) {
    test(`${viewport.name} keeps the carousel contained and horizontally scrollable`, async ({ page }) => {
        await page.setViewportSize({ width: viewport.width, height: 900 });
        const { track } = await openCarousel(page);
        const state = await carouselState(page);

        expect(state.visibleTiles).toBeGreaterThanOrEqual(viewport.minVisible);
        expect(state.visibleTiles).toBeLessThanOrEqual(viewport.maxVisible);
        expect(state.scrollWidth).toBeGreaterThan(state.clientWidth);
        expect(state.scrollHeight).toBeLessThanOrEqual(state.clientHeight + 2);
        expect(state.pageScrollWidth).toBeLessThanOrEqual(state.pageClientWidth);

        await expect(page.locator('.home-category-controls')).toHaveCount(0);

        const box = await track.boundingBox();
        expect(box).not.toBeNull();
        expect(box.x).toBeGreaterThanOrEqual(0);
        expect(box.x + box.width).toBeLessThanOrEqual(viewport.width + 1);
    });
}

test('categories beyond the sixth remain reachable with JavaScript disabled', async ({ browser }) => {
    const context = await browser.newContext({
        javaScriptEnabled: false,
        viewport: { width: 1440, height: 1000 },
    });
    const page = await context.newPage();

    try {
        const { track, tiles } = await openCarousel(page);
        await expect(page.locator('.home-category-controls')).toHaveCount(0);

        const initial = await carouselState(page);
        expect(initial.tileCount).toBeGreaterThanOrEqual(10);
        expect(initial.scrollWidth).toBeGreaterThan(initial.clientWidth);
        expect(initial.pageScrollWidth).toBeLessThanOrEqual(initial.pageClientWidth);

        const seventh = tiles.nth(6);
        const tenth = tiles.nth(9);
        await expect(seventh).toBeAttached();
        await expect(tenth).toBeAttached();

        // Senza JavaScript gli handler che animano lo scroll via bottoni/
        // frecce non si attivano mai (sono aggiunti da <script>): la reale
        // garanzia di raggiungibilità con sola tastiera è quella nativa del
        // browser, che porta in vista (scrollIntoView) l'elemento figlio
        // che riceve il focus — esattamente ciò che accade tabulando fino
        // al link di una categoria oltre la sesta. Il carosello stesso non
        // è più raggiunto via ArrowRight (nessun listener senza JS): quel
        // tasto non è un meccanismo di scroll nativo garantito su un
        // contenitore overflow-x, a differenza del focus-scroll.
        await seventh.focus();
        await expect(seventh).toBeFocused();
        await expect.poll(async () => (await carouselState(page)).scrollLeft).toBeGreaterThan(0);

        await tenth.focus();
        await expect(tenth).toBeFocused();
        await expect.poll(async () => (await carouselState(page)).scrollLeft).toBeGreaterThan(0);
    } finally {
        await context.close();
    }
});
