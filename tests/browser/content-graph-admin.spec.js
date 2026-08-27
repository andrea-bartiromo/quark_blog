import { expect, test } from '@playwright/test';

const viewportWidths = [390, 768, 1440];
const loginPath = '/admin-05dbc57764bf/login';
const conceptsPath = '/admin/concetti';
const articlesPath = '/admin/articoli';
const editorEmail = 'browser-admin@example.test';
const editorPassword = 'browser-tests';
const targetArticleTitle = 'Turing e il browser regression harness';

async function loginAsEditor(page) {
    await page.goto(loginPath);
    await page.getByLabel('Email').fill(editorEmail);
    await page.getByLabel('Password').fill(editorPassword);
    await page.getByRole('button', { name: 'Accedi' }).click();
    await expect(page).toHaveURL(/\/admin\/?$/);
}

async function expectPageFits(page) {
    const dimensions = await page.evaluate(() => ({
        clientWidth: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
    }));

    expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.clientWidth);
}

// Resolves the numeric article id for a known fixture title by reading the
// "Modifica" link href on the admin article index — never hardcoded, since
// ids are not stable across fixture seeds/runs.
async function findArticleId(page, title) {
    await page.goto(`${articlesPath}?q=${encodeURIComponent(title)}`);
    const row = page.locator('tr').filter({ hasText: title }).first();
    const href = await row.getByRole('link', { name: 'Modifica' }).getAttribute('href');
    const match = href?.match(/\/admin\/articoli\/(\d+)\/modifica/);
    if (!match) {
        throw new Error(`Could not resolve article id for "${title}" from href "${href}"`);
    }

    return match[1];
}

for (const width of viewportWidths) {
    test(`content graph admin flow works end-to-end at ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await loginAsEditor(page);

        const runId = `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
        const conceptName = `Concetto Browser ${width} ${runId}`;
        const questionText = `Cosa è un test end-to-end? ${width} ${runId}`;

        // Index page: heading, create action, no horizontal overflow.
        await page.goto(conceptsPath);
        await expect(page.getByRole('heading', { level: 1, name: 'Concetti (Content Graph)' })).toBeVisible();
        const newConceptLink = page.getByRole('link', { name: 'Nuovo concetto' });
        await expect(newConceptLink).toBeVisible();
        await expectPageFits(page);

        // Create a concept through the real form.
        await newConceptLink.click();
        await expect(page.getByRole('heading', { level: 1, name: 'Nuovo concetto' })).toBeVisible();
        await page.getByLabel('Nome').fill(conceptName);
        await page.getByLabel('Definizione breve').fill('Fixture Playwright per lo smoke test del Content Graph.');
        await page.getByRole('button', { name: 'Crea concetto' }).click();

        await expect(page).toHaveURL(/\/admin\/concetti\/\d+\/modifica$/);
        await expect(page.getByText('Concetto creato. Ora puoi aggiungere alias, articoli e domande.')).toBeVisible();
        await expectPageFits(page);
        const conceptEditUrl = page.url();

        // Alias round-trip.
        const aliasValue = `alias-${width}-${runId}`;
        await page.getByLabel('Alias (uno per riga)').fill(aliasValue);
        await page.getByRole('button', { name: 'Salva concetto' }).click();
        await expect(page.getByText('Concetto aggiornato.')).toBeVisible();
        await expect(page.getByLabel('Alias (uno per riga)')).toHaveValue(aliasValue);

        // Link an existing published article from the concept-side catalog.
        await page.getByLabel('Cerca titolo').fill(targetArticleTitle);
        await page.getByRole('button', { name: 'Filtra' }).click();
        await expect(page).toHaveURL(/q=/);
        const catalogRow = page.locator('tr').filter({ hasText: targetArticleTitle });
        await expect(catalogRow).toHaveCount(1);
        await catalogRow.getByRole('button', { name: 'Collega' }).click();

        await expect(page.getByText('Articolo collegato al concetto.')).toBeVisible();
        const linkedTable = page.locator('table').filter({ has: page.getByRole('columnheader', { name: 'Relazione' }) });
        await expect(linkedTable.getByText(targetArticleTitle)).toBeVisible();
        await expectPageFits(page);

        // Once linked, the article must disappear from the concept-side catalog.
        await page.getByLabel('Cerca titolo').fill(targetArticleTitle);
        await page.getByRole('button', { name: 'Filtra' }).click();
        await expect(page.getByText('Nessun articolo corrisponde ai filtri correnti.')).toBeVisible();

        // Unlink it back out before exercising the article-side direction.
        const unlinkRow = linkedTable.locator('tr').filter({ hasText: targetArticleTitle });
        page.once('dialog', (dialog) => dialog.accept());
        await unlinkRow.getByRole('button', { name: 'Rimuovi' }).click();
        await expect(page.getByText('Collegamento rimosso.')).toBeVisible();
        await expect(page.getByText('Nessun articolo collegato a questo concetto.')).toBeVisible();

        // New draft question: not publicly reachable yet.
        await page.getByLabel('Domanda', { exact: true }).fill(questionText);
        await page.getByRole('button', { name: 'Aggiungi domanda' }).click();
        await expect(page.getByText('— Non pubblica')).toBeVisible();
        await expectPageFits(page);

        // Approve it with a real answer + a real published target article +
        // an already-active concept: the ONLY combination
        // ContentGraphService::answerableQuestionsForConcept() treats as
        // publicly reachable — the dynamic indicator flip is exactly the
        // behavior worth checking in a real browser rather than PHPUnit.
        await page.locator('#status').selectOption('active');
        await page.getByRole('button', { name: 'Salva concetto' }).click();
        await expect(page.getByText('Concetto aggiornato.')).toBeVisible();

        const targetArticleId = await findArticleId(page, targetArticleTitle);
        await page.goto(conceptEditUrl);
        const questionRow = page.locator('tr').filter({ hasText: questionText }).first();
        await questionRow.getByRole('button', { name: 'Modifica' }).click();
        const editRow = page.locator('tr[id^="question-edit-"]:visible');
        await expect(editRow).toBeVisible();
        await editRow.locator('textarea[name="answer_summary"]').fill('Una verifica end-to-end in un browser reale.');
        await editRow.locator('input[name="target_article_id"]').fill(targetArticleId);
        await editRow.locator('select[name="status"]').selectOption('approved');
        await editRow.getByRole('button', { name: 'Salva domanda' }).click();

        await expect(page.getByText('✓ Pubblica')).toBeVisible();
        await expectPageFits(page);

        // Reverse direction: create the relation from the article editor and
        // verify the complete Article -> Concept round-trip.
        await page.goto(`${articlesPath}?q=${encodeURIComponent(targetArticleTitle)}`);
        await page.locator('tr').filter({ hasText: targetArticleTitle }).first().getByRole('link', { name: 'Modifica' }).click();
        await expect(page.getByText('Concetti collegati (Content Graph)')).toBeVisible();
        await expect(page.getByText('Nessun concetto collegato a questo articolo.')).toBeVisible();

        const catalogDetails = page.locator('details').filter({
            has: page.getByText('Collega un nuovo concetto…'),
        });
        await catalogDetails.locator('summary').click();
        await catalogDetails.getByLabel('Cerca concetto').fill(conceptName);
        await catalogDetails.getByRole('button', { name: 'Filtra' }).click();

        // Filtering reloads the edit page, so the catalog returns closed.
        const filteredDetails = page.locator('details').filter({
            has: page.getByText('Collega un nuovo concetto…'),
        });
        await filteredDetails.locator('summary').click();

        const conceptCatalogEntry = filteredDetails.locator('li').filter({ hasText: conceptName });
        await expect(conceptCatalogEntry).toHaveCount(1);
        const conceptLinkForm = conceptCatalogEntry.locator('form[method="POST"]');
        await expect(conceptLinkForm).toHaveCount(1);
        const conceptLinkButton = conceptLinkForm.locator('button[type="submit"]');
        await expect(conceptLinkButton).toBeVisible();
        await expect(conceptLinkButton).toHaveText('Collega');
        await conceptLinkButton.click();

        const linkedConceptsList = page.locator('ul').filter({
            has: page.getByRole('link', { name: conceptName }),
        });
        await expect(linkedConceptsList.getByRole('link', { name: conceptName })).toBeVisible();
        await expectPageFits(page);

        // The linked concept must no longer be offered by the available catalog.
        const postLinkDetails = page.locator('details').filter({
            has: page.getByText('Collega un nuovo concetto…'),
        });
        await postLinkDetails.locator('summary').click();
        await postLinkDetails.getByLabel('Cerca concetto').fill(conceptName);
        await postLinkDetails.getByRole('button', { name: 'Filtra' }).click();

        const postFilterDetails = page.locator('details').filter({
            has: page.getByText('Collega un nuovo concetto…'),
        });
        await postFilterDetails.locator('summary').click();
        await expect(postFilterDetails.locator('li').filter({ hasText: conceptName })).toHaveCount(0);

        // Remove the reverse relation and verify the empty state again.
        page.once('dialog', (dialog) => dialog.accept());
        await linkedConceptsList.getByRole('button', { name: 'Rimuovi' }).click();
        await expect(page.getByText('Nessun concetto collegato a questo articolo.')).toBeVisible();
    });
}
