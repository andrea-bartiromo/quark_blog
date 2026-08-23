import { expect, test } from '@playwright/test';

// EDITORIAL RESILIENCE — autosave/recovery locale del form articolo.
//
// NOTA: in questo sandbox `npx playwright test` termina con
// "Timed out waiting 30000ms from config.webServer" (verificato anche per
// gli spec browser preesistenti, es. admin-articles.spec.js — non è un
// problema introdotto da questo file, l'harness browser non è eseguibile
// in questo ambiente). Questo spec è scritto e pronto per CI/locale dove
// l'harness funziona, ma non è stato eseguito in questa sessione — vedi
// il report finale della missione per la limitazione documentata.

const loginPath = '/admin-05dbc57764bf/login';
const createPath = '/admin/articoli/nuovo';
const editorEmail = 'browser-admin@example.test';
const editorPassword = 'browser-tests';

async function loginAsEditor(page) {
    await page.goto(loginPath);
    await page.getByLabel('Email').fill(editorEmail);
    await page.getByLabel('Password').fill(editorPassword);
    await page.getByRole('button', { name: 'Accedi' }).click();
    await expect(page).toHaveURL(/\/admin\/?$/);
}

async function draftKeysInStorage(page) {
    return page.evaluate(() => Object.keys(window.localStorage).filter((key) => key.startsWith('kairus:editor:')));
}

test.describe('editor autosave e recovery', () => {
    // TEST 1 + 2: create → type → autosave → reload → recovery offered → fields recovered.
    test('un articolo nuovo non salvato viene recuperato dopo un reload', async ({ page }) => {
        await loginAsEditor(page);
        await page.goto(createPath);

        await page.getByLabel('Titolo *').fill('Articolo di prova autosave');
        await page.locator('#excerpt').fill('Sommario di prova per l\'autosave.');
        // Il corpo passa da TinyMCE: digitare direttamente nell'iframe.
        await page.frameLocator('iframe[id$="_ifr"]').locator('body').fill('Corpo lungo di prova per verificare il recupero della bozza locale dopo un refresh accidentale della pagina.');

        // Debounce dell'autosave: 1500ms + margine.
        await page.waitForTimeout(2000);
        await expect(page.locator('[data-editor-autosave-status]')).toContainText('Bozza salvata localmente');

        await page.reload();

        await expect(page.locator('[data-editor-autosave-banner]')).toBeVisible();
        await page.locator('[data-editor-autosave-restore]').click();

        await expect(page.getByLabel('Titolo *')).toHaveValue('Articolo di prova autosave');
        await expect(page.locator('#excerpt')).toHaveValue('Sommario di prova per l\'autosave.');
    });

    // TEST 3: ignore → server/form values retained (in questo caso: valori vuoti del form nuovo restano vuoti).
    test('ignorare la bozza non tocca i valori correnti del form', async ({ page }) => {
        await loginAsEditor(page);
        await page.goto(createPath);

        await page.getByLabel('Titolo *').fill('Titolo che verrà ignorato nel recupero');
        await page.waitForTimeout(2000);
        await page.reload();

        await expect(page.locator('[data-editor-autosave-banner]')).toBeVisible();
        await page.locator('[data-editor-autosave-ignore]').click();

        await expect(page.locator('[data-editor-autosave-banner]')).toBeHidden();
        await expect(page.getByLabel('Titolo *')).toHaveValue('');

        // La bozza non è stata eliminata (solo ignorata): un secondo reload la ripropone.
        await page.reload();
        await expect(page.locator('[data-editor-autosave-banner]')).toBeVisible();
    });

    // TEST 4: successful create → local draft removed.
    test('un salvataggio riuscito elimina la bozza "nuovo articolo"', async ({ page }) => {
        await loginAsEditor(page);
        await page.goto(createPath);

        await page.getByLabel('Titolo *').fill('Articolo completato e salvato');
        await page.frameLocator('iframe[id$="_ifr"]').locator('body').fill('Corpo sufficiente per il salvataggio.');
        await page.locator('select[name="category"]').selectOption({ index: 1 });
        await page.waitForTimeout(2000);

        expect(await draftKeysInStorage(page)).not.toHaveLength(0);

        await page.getByRole('button', { name: 'Crea articolo' }).click();
        await expect(page.locator('#kairus-flash-success')).toBeVisible();

        const newDraftKeys = (await draftKeysInStorage(page)).filter((key) => key.includes(':admin:new:'));
        expect(newDraftKeys).toHaveLength(0);
    });

    // TEST 6: draft article A non contamina article B.
    test('la bozza di un articolo non compare nel form di un articolo diverso', async ({ page }) => {
        await loginAsEditor(page);
        await page.goto(createPath);
        await page.getByLabel('Titolo *').fill('Bozza esclusiva del nuovo articolo');
        await page.waitForTimeout(2000);

        // Un secondo articolo (già esistente in fixture, id presumibilmente diverso da "new")
        // non deve mai vedere il banner di recovery basato sulla chiave "new".
        await page.goto('/admin/articoli');
        const firstEdit = page.locator('.articles-table tbody tr').first().getByRole('link', { name: 'Modifica' });
        await firstEdit.click();

        await expect(page.locator('[data-editor-autosave-banner]')).toBeHidden();
    });

    // TEST 10: submit manuale non genera beforeunload warning.
    test('un submit intenzionale non genera il warning beforeunload', async ({ page }) => {
        await loginAsEditor(page);
        await page.goto(createPath);

        await page.getByLabel('Titolo *').fill('Articolo con submit pulito');
        await page.frameLocator('iframe[id$="_ifr"]').locator('body').fill('Corpo sufficiente.');
        await page.locator('select[name="category"]').selectOption({ index: 1 });

        let dialogAppeared = false;
        page.on('dialog', () => { dialogAppeared = true; });

        await page.getByRole('button', { name: 'Crea articolo' }).click();
        await expect(page.locator('#kairus-flash-success')).toBeVisible();

        expect(dialogAppeared).toBe(false);
    });

    // Regressione P2: se il submit avviene prima della scadenza del debounce,
    // gli ultimi caratteri (compreso TinyMCE) devono essere scritti subito in
    // localStorage prima che beforeunload venga soppresso. Il preventDefault
    // simula un POST che non lascia la pagina (es. errore di rete/419) e ci
    // permette di ispezionare esattamente il payload salvato dal submit.
    test('il submit forza il flush della bozza ancora nel debounce', async ({ page }) => {
        await loginAsEditor(page);
        await page.goto(createPath);

        const title = 'Ultimissima modifica prima del submit';
        const body = 'Corpo digitato immediatamente prima del salvataggio manuale.';

        await page.getByLabel('Titolo *').fill(title);
        await page.frameLocator('iframe[id$="_ifr"]').locator('body').fill(body);

        await page.evaluate(() => {
            const form = document.querySelector('[data-editor-autosave-form]');
            form.addEventListener('submit', (event) => event.preventDefault(), { capture: true, once: true });
            form.requestSubmit();
        });

        const saved = await page.evaluate(() => {
            const key = Object.keys(window.localStorage).find((candidate) => candidate.startsWith('kairus:editor:v1:admin:new:'));
            return key ? JSON.parse(window.localStorage.getItem(key)) : null;
        });

        expect(saved).not.toBeNull();
        expect(saved.fields.title).toBe(title);
        expect(saved.fields.body).toContain(body);
    });

    // Regressione P2: il file non può essere ripristinato dal browser, ma il
    // suo nome è un metadato di recovery. Se è l'unica differenza rispetto al
    // form server-rendered, deve comunque comparire il banner e, al restore,
    // il promemoria di riselezionare il file.
    test('la sola selezione di una cover viene riconosciuta come bozza recuperabile', async ({ page }) => {
        await loginAsEditor(page);
        await page.goto(createPath);

        await page.locator('input[name="cover_image_upload"]').setInputFiles({
            name: 'cover-da-riselezionare.jpg',
            mimeType: 'image/jpeg',
            buffer: Buffer.from('fake-jpeg-for-autosave-metadata'),
        });

        await page.waitForTimeout(2000);
        await page.reload();

        await expect(page.locator('[data-editor-autosave-banner]')).toBeVisible();
        await page.locator('[data-editor-autosave-restore]').click();
        await expect(page.locator('[data-editor-autosave-file-hint]')).toContainText('cover-da-riselezionare.jpg');
    });

    // TEST 11 (parziale): Unicode/HTML long body roundtrip attraverso il recupero locale.
    test('testo con caratteri italiani e apostrofi tipografici sopravvive al recupero', async ({ page }) => {
        await loginAsEditor(page);
        await page.goto(createPath);

        const title = 'L’universo secondo Kairus: perché la fisica ci affascina così tanto?';
        await page.getByLabel('Titolo *').fill(title);
        await page.waitForTimeout(2000);
        await page.reload();

        await page.locator('[data-editor-autosave-restore]').click();
        await expect(page.getByLabel('Titolo *')).toHaveValue(title);
    });
});