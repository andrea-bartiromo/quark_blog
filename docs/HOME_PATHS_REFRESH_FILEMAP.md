# Home + Percorsi Visual Adoption — Mappa del perimetro

Cantiere D, Prompt 02/03/07/08/09. Baseline: `feat/kairus-editorial-foundations`
@ `fb5ff3044a0eeec4345aaa8d0c79dac2cc44843a`.

## Homepage

| File | Ruolo | Stato |
|---|---|---|
| `resources/views/home.blade.php` | Vista principale, orchestratore degli include, `@php` con helper condivisi (`$categoryLabel`, `$imageForArticle`, `$visualFor`, ecc.) | **Modificabile** |
| `resources/views/home/partials/hero-trending.blade.php` | Articolo in evidenza (H1 della home) + pannello "Trending now" (blocco evidenza) — un solo file per entrambi i blocchi | **Modificabile** |
| `resources/views/home/partials/latest-articles.blade.php` | Griglia "Ultimi articoli" | **Modificabile** |
| `resources/views/home/partials/paths-discovery.blade.php` | Card Percorsi in home. Oggi incluso **da dentro** `turing-teaser.blade.php` (`@include('home.partials.paths-discovery')` alla riga 34), non da `home.blade.php` — va spostato per l'ordine richiesto dal Prompt 13 | **Modificabile** |
| `resources/views/home/partials/newsletter-band.blade.php` | Form iscrizione Newsletter in home | **Modificabile (solo shell/testo, mai action/CSRF/campi)** |
| `resources/views/home/partials/turing-teaser.blade.php` | Teaser Speciale Turing, markup interamente a stili inline | **Modificabile (solo shell, mai URL/contenuto/dati)** |
| `resources/views/home/partials/category-grid.blade.php` | Carosello categorie, **JS-dipendente esistente** (`data-category-carousel/-track/-prev/-next`, script in `home.blade.php`) | **Modificabile con cautela — struttura/attributi JS invariati** |
| `resources/views/home/partials/structured-data.blade.php` | JSON-LD della home | **Sola lettura** (SEO, fuori perimetro di stile) |
| `public/css/home-premium.css`, `home-fix.css`, `home-category-carousel.css`, `content-clusters.css` | CSS legacy della home | **Sola lettura — mai rimosso, mai modificato** |
| `app/Http/Controllers/HomeController.php` | Query e dati (`$featured`, `$latest`, `$trending`, `$homePaths`, `$categoryHighlights`, ecc.) | **Fuori perimetro — mai toccato** |

## Indice Percorsi

| File | Ruolo | Stato |
|---|---|---|
| `resources/views/content-clusters/index.blade.php` | Vista monolitica: intro (H1), grid card Percorso inline (nessuna partial separata per la card), paginazione, stato vuoto | **Modificabile** |
| `public/css/content-clusters.css` | CSS legacy condiviso da indice e dettaglio | **Sola lettura** |
| Controller che alimenta la vista (`ContentClusterController` o equivalente), route, model `ContentCluster` | Dati, filtro, paginazione | **Fuori perimetro — mai toccato** |

## Dettaglio Percorso

| File | Ruolo | Stato |
|---|---|---|
| `resources/views/content-clusters/show.blade.php` | Vista monolitica: breadcrumb, hero (H1), nota editoriale, punti chiave, domande guida, pillar, tappe (`path-steps__list`), transizione di registro, footer/continuità | **Modificabile** |
| `resources/views/content-clusters/partials/path-transition.blade.php` | Micro-sezione tipografica "cambio di registro" (Kairus Visual Language) | **Sola lettura** — valore narrativo specifico, nessun componente Kairus generico lo sostituisce senza perdita di significato |
| `resources/views/content-clusters/partials/subscribe-form.blade.php` | Form "Avvisami quando continua": honeypot, toggle JS proprio, stati di successo/errore server-side | **Sola lettura** — il toggle mostra/nasconde il form via JS esistente; `x-kairus.form-shell` non prevede questo stato e introdurlo cambierebbe il comportamento (vietato) |
| `public/css/content-clusters-detail.css`, `content-clusters-narrative.css`, `media-lightbox.css` | CSS legacy | **Sola lettura** |
| `x-media.image-viewer` (lightbox), `App\Support\PathVisualLibrary`, `App\Support\PathVisualSignature` | Servizi/componenti esistenti di cui la vista dipende | **Fuori perimetro — mai toccato** |
| `resources/views/partials/content-clusters-analytics.blade.php` | Tracking (`data-path-analytics-view`, ecc.) | **Fuori perimetro — vietato dal cantiere (tracking)** |

## Conflitti con branch/PR aperte (Prompt 03)

Verificate tutte le **13 pull request aperte** al momento dell'audit (#507,
#510–#513, #515–#522): nessuna tocca `home.blade.php`, `resources/views/home/`
o `resources/views/content-clusters/`. **Nessun conflitto reale.** (Le PR
Trust Layer #516–#521 toccano `articolo.blade.php`, `autore.blade.php`,
`chi-siamo.blade.php` e nuove pagine statiche — fuori dal perimetro di questo
cantiere in ogni caso.)

Il repository contiene inoltre decine di branch remoti non associati ad
alcuna PR aperta (serie `mission-*`, vari `feat/percorsi-*`, ecc.): non sono
lavoro in corso da rispettare, sono cronologia non collegata a una revisione
attiva — ignorati.

## Audit classi CSS legacy (Prompt 07)

Classi oggi necessarie al comportamento visivo attuale — **nessuna verrà
rimossa in questo cantiere**, anche quando il markup che le usa viene
sostituito da un componente Kairus (la regola CSS resta nel file, solo
inutilizzata):

- Home: `.home-premium-hero*`, `.home-lead-story*`, `.home-trending-panel*`,
  `.home-editorial-*`, `.home-paths*`, `.home-path-link*`,
  `.home-newsletter-band*`, `.home-category-*` (queste ultime **restano
  attive**: il carosello è JS-dipendente e non viene sostituito).
- Percorsi indice: `.paths-index`, `.paths-intro*`, `.paths-brand-statement`,
  `.paths-grid`, `.path-card*` (nome coincidente ma **distinto** da
  `.kairus-path-card` — nessuna collisione di selettore), `.paths-pagination`,
  `.paths-empty`.
- Percorso dettaglio: `.path-hero*`, `.path-entrance*`, `.path-curator-note*`,
  `.path-narrative*`, `.path-pillar*`, `.path-steps*`, `.path-step*`,
  `.path-transition*`, `.path-ending*`, `.path-subscribe*`.

Candidate a diventare obsolete in un **futuro** cantiere di pulizia (non in
questo), una volta che l'adozione Kairus copre tutte le superfici che le
usano: `.home-editorial-card*` (sostituita da `.kairus-article-card` su
tutte le sue istanze), `.home-path-link*` (sostituita da
`.kairus-path-card` sulle card Percorso in home). Restano invece
**definitivamente necessarie**, non solo per ora: `.home-category-*`
(carosello JS), `.path-card__preview-*` (toggle JS sull'indice Percorsi),
`.path-subscribe*` (toggle JS sul dettaglio).

## Audit immagini (Prompt 08)

Tutte le immagini di home e Percorsi passano già da `<x-responsive-image>`
(hero, ultimi articoli, categorie, cover Percorso, cover tappe) o sono SVG
generati inline come fallback deterministico (`$fallbackSvg`, nessun file su
disco). Dimensioni note in tutti i casi (`width`/`height` risolti dal
componente o dal file reale). **Nessuna nuova immagine richiesta da questo
restyling**: l'adozione riusa sempre l'immagine/lo slot esistente passato ai
nuovi componenti Kairus (`image-frame` non renderizza mai un proprio `<img>`).

## Audit semantico (Prompt 09)

| Pagina | H1 attuale | H2 attuali (ordine) |
|---|---|---|
| Home | 1 — dentro `hero-trending.blade.php`, titolo dell'articolo in evidenza | "Ultimi articoli" (latest-articles) → "Un tema, dall'inizio." (paths-discovery, nidificato oggi dentro turing-teaser) → "La settimana scientifica..." (newsletter-band, tag `<h2>` senza id) → "Esplora le categorie" (category-grid). Turing-teaser stesso usa `<h2>` per il proprio titolo. |
| Indice Percorsi | 1 — `#percorsi-title` | Nessuno strutturale (card usano `<h2>` per il titolo di ciascun Percorso, ripetuto per ogni card — corretto: sono voci di un elenco, non sezioni di pagina) |
| Dettaglio Percorso | 1 — `#percorso-title` | "Perché questo percorso" → ["I punti da portare con te"] → ["Una mappa fatta di domande"] → ["Il punto di partenza" — pillar] → "Articoli del percorso" → footer (nessun H2 nel footer attuale) |

Nessuna correzione semantica necessaria: la gerarchia è già valida (un solo
H1 per pagina, H2 in ordine di apparizione, nessun salto di livello). Le
uniche modifiche pianificate sono di **ordine di posizione** dei blocchi in
home (Prompt 13), non di livello di heading.
