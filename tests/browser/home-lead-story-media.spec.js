import { expect, test } from '@playwright/test';

const home = '/';

// Regressione: .home-lead-story__media aveva min-height:340px sul
// contenitore ma l'immagine reale (nidificata dentro <x-kairus.image-frame>,
// il cui .kairus-image-frame__media calcola la propria altezza da un
// aspect-ratio INTERNO indipendente, vedi public/css/home-fix.css) restava
// più bassa del contenitore, lasciando una fascia bianca sotto l'immagine
// nella card in evidenza della home. Il fix rende l'immagine (e il livello
// intermedio) position:absolute + inset:0 rispetto al contenitore, che
// resta l'unica fonte di verità per l'altezza.
async function openHome(page) {
    const response = await page.goto(home, { waitUntil: 'networkidle' });
    expect(response).not.toBeNull();
    expect(response.status()).toBeLessThan(400);

    const story = page.locator('.home-lead-story').first();
    await expect(story).toBeVisible();

    return story;
}

async function mediaFillState(story) {
    return story.locator('.home-lead-story__media').evaluate((media) => {
        const img = media.querySelector('img');
        const mediaRect = media.getBoundingClientRect();
        const imgRect = img.getBoundingClientRect();

        return {
            mediaHeight: mediaRect.height,
            imgHeight: imgRect.height,
            bottomGap: (mediaRect.top + mediaRect.height) - (imgRect.top + imgRect.height),
            topGap: imgRect.top - mediaRect.top,
        };
    });
}

test('desktop: hero cover image fills its container with no gap below it', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    const story = await openHome(page);

    const state = await mediaFillState(story);

    expect(state.mediaHeight).toBeGreaterThanOrEqual(340);
    expect(Math.abs(state.bottomGap)).toBeLessThanOrEqual(1);
    expect(Math.abs(state.topGap)).toBeLessThanOrEqual(1);
    expect(Math.abs(state.imgHeight - state.mediaHeight)).toBeLessThanOrEqual(1);
});

test('desktop: two-column hero grid keeps its intended proportions', async ({ page }) => {
    // Regressione secondaria scoperta correggendo la precedente: un
    // primo tentativo di fix aggiungeva aspect-ratio al contenitore
    // stesso, un figlio diretto di .home-lead-story (CSS Grid) — questo
    // gonfiava la larghezza della colonna (min-height * aspect-ratio)
    // rompendo il layout a due colonne. La colonna media deve restare
    // sensibilmente più stretta della metà della card, mai quasi a piena
    // larghezza.
    await page.setViewportSize({ width: 1440, height: 1000 });
    const story = await openHome(page);

    const [storyBox, mediaBox] = await Promise.all([
        story.boundingBox(),
        story.locator('.home-lead-story__media').boundingBox(),
    ]);

    expect(storyBox).not.toBeNull();
    expect(mediaBox).not.toBeNull();
    expect(mediaBox.width).toBeLessThan(storyBox.width * 0.6);
});

test('mobile: hero cover image fills its container with no gap below it', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    const story = await openHome(page);

    const state = await mediaFillState(story);

    expect(state.mediaHeight).toBeGreaterThanOrEqual(230);
    expect(Math.abs(state.bottomGap)).toBeLessThanOrEqual(1);
    expect(Math.abs(state.topGap)).toBeLessThanOrEqual(1);
});

test('hero media preserves the "In evidenza" badge and stays a single link', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    const story = await openHome(page);

    const media = story.locator('a.home-lead-story__media');
    await expect(media).toHaveCount(1);
    await expect(media.locator('span', { hasText: 'In evidenza' })).toBeVisible();

    const href = await media.getAttribute('href');
    expect(href).toContain('/articolo/');

    const img = media.locator('img');
    await expect(img).toHaveAttribute('loading', 'eager');
    await expect(img).toHaveAttribute('fetchpriority', 'high');
});
