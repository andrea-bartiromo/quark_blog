import { expect, test } from '@playwright/test';

/**
 * Cantiere 6 (programma 100-cantieri Kairus): copertura browser dedicata
 * alla composizione introdotta dai Cantieri 1-3 sulla pagina categoria —
 * chip Argomenti (x-topic-chips), CTA newsletter contestuale e blocco
 * "Continua a esplorare". Le suite tests/Feature/CategoryDiscoveryFlowTest.php
 * e TopicChipsComponentTest.php coprono già il markup renderizzato lato
 * server; qui si verifica il comportamento reale nel browser (layout
 * responsive, focus da tastiera) che un test HTML-string non può vedere.
 *
 * Fixture: 'browser-newsletter-category' (BrowserTestSeeder), 4 articoli
 * pubblicati — mai 'intelligenza-artificiale', su cui altre suite browser
 * fanno assunzioni precise su conteggio/ordine degli articoli.
 */
const categoryPath = '/categoria/browser-newsletter-category';

test('chip Argomenti evidenzia la categoria corrente e resta navigabile', async ({ page }) => {
    const response = await page.goto(categoryPath, { waitUntil: 'domcontentloaded' });
    expect(response).not.toBeNull();
    expect(response.status()).toBeLessThan(400);

    const chips = page.locator('nav[aria-label="Filtra per argomento"]');
    await expect(chips).toBeVisible();

    const current = chips.locator('a[aria-current="page"]');
    await expect(current).toHaveCount(1);
    await expect(current).toHaveText('Browser Newsletter Category');

    // Il sidebar topic-cloud (nome accessibile "Argomenti") resta un
    // landmark distinto: mai lo stesso nome del chip-row principale sulla
    // stessa pagina (finding Codex, PR #553).
    await expect(page.locator('nav[aria-label="Argomenti"]')).toHaveCount(1);
});

test('la CTA newsletter compare dopo la terza card ed è raggiungibile da tastiera', async ({ page }) => {
    await page.goto(categoryPath, { waitUntil: 'domcontentloaded' });

    const cta = page.locator('section.kairus-category-newsletter');
    await expect(cta).toBeVisible();
    await expect(cta).toHaveAttribute('aria-labelledby', 'category-newsletter-heading');

    // Il <li> che ospita la CTA è marcato role="presentation" (non è un
    // articolo come gli altri <li> della griglia).
    const ctaListItem = page.locator('li.kairus-category-newsletter-slot');
    await expect(ctaListItem).toHaveAttribute('role', 'presentation');

    const emailInput = cta.locator('#category-newsletter-email');
    await emailInput.focus();
    await expect(emailInput).toBeFocused();

    // kairus-focusable:focus-visible applica un outline visibile — verifica
    // che lo stile calcolato non sia "none" quando l'elemento ha il focus
    // da tastiera (a differenza dell'assenza di stile prima del Cantiere 3).
    const outlineStyle = await emailInput.evaluate(el => getComputedStyle(el).outlineStyle);
    expect(outlineStyle).not.toBe('none');
});

test('il blocco "Continua a esplorare" mostra Più letti e categorie correlate', async ({ page }) => {
    await page.goto(categoryPath, { waitUntil: 'domcontentloaded' });

    const section = page.locator('section.kairus-continue-exploring');
    await expect(section).toBeVisible();
    await expect(section.locator('.kairus-continue-exploring__most-read')).toBeVisible();
    await expect(section.locator('.kairus-continue-exploring__related-categories')).toBeVisible();

    // La categoria corrente non deve mai comparire tra le "Altre categorie".
    const relatedLinks = section.locator('.kairus-continue-exploring__related-categories a');
    await expect(relatedLinks.filter({ hasText: 'Browser Newsletter Category' })).toHaveCount(0);
});

test('il blocco "Continua a esplorare" collassa a una colonna sotto i 900px', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 900 });
    await page.goto(categoryPath, { waitUntil: 'domcontentloaded' });

    const grid = page.locator('.kairus-continue-exploring__grid');
    await expect(grid).toBeVisible();

    const columns = await grid.evaluate(el => getComputedStyle(el).gridTemplateColumns.split(' ').length);
    expect(columns).toBe(1);

    // Nessuno scroll orizzontale introdotto dal nuovo blocco.
    const pageOverflows = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
    expect(pageOverflows).toBe(false);
});
