import { expect, test } from '@playwright/test';

// Cantiere 28 (programma 100-cantieri Kairus), dipende dal Cantiere 21
// (inventario tecnico pagine pubbliche, PublicPageInventory): stesse sei
// superfici pubbliche gia' usate da tests/browser/public-regression.spec.js,
// sulla stessa fixture deterministica (Database\Seeders\BrowserTestSeeder).
//
// Cantiere I (audit accessibilita' precedente, PUBLIC_A11Y_RESPONSIVE_HANDOFF.md)
// aveva verificato skip-link e anello di focus visibile con un'ispezione
// manuale una tantum, poi corretto "14 controlli senza anello di focus
// visibile dedicato" via CSS (.kairus-focusable:focus-visible). Nessun test
// automatico esisteva pero' per la navigazione da tastiera vera e propria:
// i soli test da tastiera gia' presenti (public-regression.spec.js) coprono
// il focus-trap di due componenti specifici (modale newsletter, lightbox
// articolo), non l'attraversamento con Tab dell'intera pagina ne' lo
// skip-link. Questo file colma quel gap, senza duplicare i test esistenti.
const routes = {
    home: '/',
    ricerca: '/ricerca?q=turing',
    articolo: '/articolo/browser-turing-article',
    autore: '/autore/1',
    categoria: '/categoria/intelligenza-artificiale',
    notizie: '/notizie',
};

// Limite superiore di pressioni Tab: le pagine con molte card/link (griglia
// categoria, elenco articoli) hanno decine di elementi via via raggiungibili
// — non serve visitarli tutti per verificare l'invariante (skip-link
// funzionante, nessun elemento senza indicatore di focus visibile), un
// campione ampio e deterministico basta ed evita un test troppo lento.
const MAX_TAB_PRESSES = 25;

async function describeFocusedElement(page) {
    return page.evaluate(() => {
        const el = document.activeElement;
        if (!el || el === document.body) {
            return null;
        }

        const style = window.getComputedStyle(el);
        return {
            tag: el.tagName,
            className: el.className,
            id: el.id,
            hasVisibleFocusIndicator: style.outlineStyle !== 'none' || style.boxShadow !== 'none',
            isVisible: el.getClientRects().length > 0,
        };
    });
}

for (const [surface, path] of Object.entries(routes)) {
    test.describe(`${surface} — navigazione da tastiera`, () => {
        test(`${surface}: lo skip-link e' il primo elemento raggiungibile e sposta il focus sul contenuto principale`, async ({ page }) => {
            await page.goto(path);

            await page.keyboard.press('Tab');
            const skipLink = await describeFocusedElement(page);

            expect(skipLink).not.toBeNull();
            expect(skipLink.className).toContain('skip-link');

            await page.keyboard.press('Enter');

            const afterActivation = await page.evaluate(() => document.activeElement?.id ?? null);
            expect(afterActivation).toBe('main-content');
        });

        test(`${surface}: attraversando la pagina con Tab nessun elemento focalizzato e' privo di un indicatore di focus visibile`, async ({ page }) => {
            await page.goto(path);

            // Il primo Tab raggiunge lo skip-link (coperto dal test sopra):
            // qui si riparte dal contenuto principale, cosi' da verificare i
            // controlli reali della pagina (header, corpo, footer), non solo
            // lo skip-link stesso.
            await page.keyboard.press('Tab');
            await page.keyboard.press('Enter');

            const withoutIndicator = [];
            let previous = null;

            for (let i = 0; i < MAX_TAB_PRESSES; i++) {
                await page.keyboard.press('Tab');
                const focused = await describeFocusedElement(page);

                if (!focused) {
                    // Sequenza di tabulazione esaurita (tornati al body):
                    // nessun elemento restante da controllare su questa pagina.
                    break;
                }

                if (previous && previous.tag === focused.tag && previous.className === focused.className && previous.id === focused.id) {
                    // Stesso elemento del giro precedente: la sequenza si e'
                    // chiusa in loop, non serve continuare a premere Tab.
                    break;
                }

                if (focused.isVisible && !focused.hasVisibleFocusIndicator) {
                    withoutIndicator.push(`${focused.tag}${focused.id ? `#${focused.id}` : ''}${focused.className ? `.${String(focused.className).trim().replace(/\s+/g, '.')}` : ''}`);
                }

                previous = focused;
            }

            expect(withoutIndicator, `Elementi focalizzabili senza indicatore di focus visibile: ${withoutIndicator.join(', ')}`).toEqual([]);
        });
    });
}
