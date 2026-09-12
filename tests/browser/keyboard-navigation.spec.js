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
// Piu' alto del necessario per coprire anche header/ticker/category-bar,
// che precedono <main> nel DOM (vedi layouts/app.blade.php) e che la
// traversata copre fin dal primo Tab (Codex, PR #576, P2).
const MAX_TAB_PRESSES = 35;

// Marca ogni nodo visitato con un attributo dedicato invece di confrontare
// tag/classe/id del giro precedente: card ripetute con la stessa classe e
// nessun id (es. griglia trending della home) sarebbero indistinguibili da
// un vero loop, troncando la traversata dopo la prima (Codex, PR #576, P2).
const VISITED_MARKER = 'data-kairus-tab-visited';

async function describeFocusedElement(page) {
    return page.evaluate(marker => {
        const el = document.activeElement;
        if (!el || el === document.body) {
            return null;
        }

        const alreadyVisited = el.hasAttribute(marker);
        el.setAttribute(marker, 'true');

        // L'indicatore di focus deve essere un cambiamento visibile causato
        // dal focus stesso, non una scia (es. box-shadow permanente di una
        // card) che risulterebbe presente anche senza focus (Codex, PR #576,
        // P2): si confronta lo stile a fuoco con quello subito dopo blur(),
        // poi si ripristina il focus sullo stesso nodo per non alterare la
        // sequenza di tabulazione.
        const focusedStyle = window.getComputedStyle(el);
        const focusedOutline = `${focusedStyle.outlineStyle} ${focusedStyle.outlineWidth} ${focusedStyle.outlineColor}`;
        const focusedShadow = focusedStyle.boxShadow;

        el.blur();
        const blurredStyle = window.getComputedStyle(el);
        const blurredOutline = `${blurredStyle.outlineStyle} ${blurredStyle.outlineWidth} ${blurredStyle.outlineColor}`;
        const blurredShadow = blurredStyle.boxShadow;
        el.focus({ preventScroll: true });

        const hasVisibleFocusIndicator =
            (focusedStyle.outlineStyle !== 'none' && focusedOutline !== blurredOutline) || focusedShadow !== blurredShadow;

        return {
            tag: el.tagName,
            className: el.className,
            id: el.id,
            alreadyVisited,
            hasVisibleFocusIndicator,
            isVisible: el.getClientRects().length > 0,
        };
    }, VISITED_MARKER);
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

            // Traversata dall'inizio pagina (primo Tab incluso): raggiunge
            // anche header/ticker/category-bar, che precedono <main> nel DOM
            // e che una traversata avviata dopo l'attivazione dello
            // skip-link non potrebbe mai coprire (Codex, PR #576, P2).
            const withoutIndicator = [];

            for (let i = 0; i < MAX_TAB_PRESSES; i++) {
                await page.keyboard.press('Tab');
                const focused = await describeFocusedElement(page);

                if (!focused) {
                    // Sequenza di tabulazione esaurita (tornati al body):
                    // nessun elemento restante da controllare su questa pagina.
                    break;
                }

                if (focused.alreadyVisited) {
                    // Tornati su un nodo DOM gia' marcato in questo stesso
                    // giro (identita' reale, non solo tag/classe/id uguali):
                    // la sequenza si e' chiusa in loop.
                    break;
                }

                if (focused.isVisible && !focused.hasVisibleFocusIndicator) {
                    withoutIndicator.push(`${focused.tag}${focused.id ? `#${focused.id}` : ''}${focused.className ? `.${String(focused.className).trim().replace(/\s+/g, '.')}` : ''}`);
                }
            }

            expect(withoutIndicator, `Elementi focalizzabili senza indicatore di focus visibile: ${withoutIndicator.join(', ')}`).toEqual([]);
        });
    });
}
