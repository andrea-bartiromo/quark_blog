import { expect, test } from '@playwright/test';

for (const width of [390, 768, 1440]) {
    test(`ticker visibly moves left at ${width}px and hover keeps autoplay running`, async ({ page }) => {
        test.setTimeout(15_000);
        await page.setViewportSize({ width, height: 900 });
        await page.emulateMedia({ reducedMotion: 'no-preference' });
        const response = await page.goto('/', { waitUntil: 'domcontentloaded' });
        expect(response?.status()).toBeLessThan(400);

        const track = page.locator('.ticker-track');
        const viewport = page.locator('.ticker-viewport');
        await expect(track.locator('.ticker-sequence')).toHaveCount(4);
        await expect(track).toBeVisible();

        const runtime = await track.evaluate(element => {
            const style = getComputedStyle(element);
            return {
                name: style.animationName,
                duration: style.animationDuration,
                playState: style.animationPlayState,
                width: element.getBoundingClientRect().width,
                viewportWidth: element.parentElement?.getBoundingClientRect().width ?? 0,
            };
        });
        expect(runtime.name).toBe('kairus-ticker-loop');
        expect(runtime.duration).not.toBe('0s');
        expect(runtime.playState).toBe('running');
        expect(runtime.width).toBeGreaterThan(runtime.viewportWidth);

        const start = await track.boundingBox();
        await page.waitForTimeout(1000);
        const end = await track.boundingBox();
        expect(start).not.toBeNull();
        expect(end).not.toBeNull();
        expect(end.x).toBeLessThan(start.x - 15);

        const viewportBox = await viewport.boundingBox();
        expect(viewportBox).not.toBeNull();
        await page.mouse.move(viewportBox.x + viewportBox.width / 2, viewportBox.y + viewportBox.height / 2);
        await expect.poll(() => track.evaluate(element => getComputedStyle(element).animationPlayState)).toBe('running');

        const hoverStart = await track.boundingBox();
        await page.waitForTimeout(500);
        const hoverEnd = await track.boundingBox();
        expect(hoverStart).not.toBeNull();
        expect(hoverEnd).not.toBeNull();
        expect(hoverEnd.x).toBeLessThan(hoverStart.x - 5);

        const dimensions = await page.evaluate(() => ({
            clientWidth: document.documentElement.clientWidth,
            scrollWidth: document.documentElement.scrollWidth,
        }));
        expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.clientWidth);
    });
}

test('ticker reduced motion is static and remains manually scrollable', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 900 });
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    const track = page.locator('.ticker-track');
    const viewport = page.locator('.ticker-viewport');
    const start = await track.boundingBox();
    await page.waitForTimeout(500);
    const end = await track.boundingBox();
    expect(start).not.toBeNull();
    expect(end).not.toBeNull();
    expect(Math.abs(end.x - start.x)).toBeLessThan(1);

    const state = await page.evaluate(() => {
        const trackElement = document.querySelector('.ticker-track');
        const viewportElement = document.querySelector('.ticker-viewport');
        if (!trackElement || !viewportElement) return null;
        return {
            animationName: getComputedStyle(trackElement).animationName,
            overflowX: getComputedStyle(viewportElement).overflowX,
            scrollWidth: viewportElement.scrollWidth,
            clientWidth: viewportElement.clientWidth,
        };
    });
    expect(state).not.toBeNull();
    expect(state.animationName).toBe('none');
    expect(state.overflowX).toBe('auto');
    expect(state.scrollWidth).toBeGreaterThan(state.clientWidth);

    await viewport.evaluate(element => { element.scrollLeft = 80; });
    expect(await viewport.evaluate(element => element.scrollLeft)).toBeGreaterThan(0);
});
