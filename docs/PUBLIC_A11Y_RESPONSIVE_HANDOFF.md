# Accessibilità e responsive trasversali — Handoff (Cantiere I)

Verifica trasversale, con correzioni mirate, degli invarianti di
accessibilità e responsive su tutte e sette le superfici pubbliche già
adottate dal sistema Kairus Editorial Foundations V1.

## SHA

- Base: `feat/kairus-public-trust-layer` @ `02a2e77da6f26fbd8f57e10ea3dd68c155baa2a7`
- Branch: `feat/kairus-public-a11y-responsive`
- 7 commit, nessuna PR, nessun merge, nessun deploy.

## Commit

1. `4258f9b` — fix: overflow orizzontale a 320px sul layout a due
   colonne (sidebar), root-causato e corretto su articolo/notizie/
   categoria/ricerca.
2. `12d345e` — fix(a11y): salto di heading su Percorsi indice +
   correzione preventiva overflow sulle griglie della home.
3. `894b41c` — docs: matrice QA trasversale delle 7 superfici.
4. `d411e03` — fix(a11y): focus da tastiera invisibile sul form
   newsletter della home.
5. `0b063f2` — fix(a11y): due scarti di contrasto AA nei token Kairus.
6. `de7352a` — test: test di integrazione cross-superficie.
7. `144b7f9` — docs: aggiornamento di chiusura della matrice.

## Metodo

Ambiente locale: SQLite temporaneo (`database/database.sqlite`,
rimosso a fine sessione), seed con articoli/autore/Percorso dai dati
volutamente lunghi (titoli, bio, handle social, nomi Percorso), un
articolo marcato `featured=true` (necessario per il rendering
realistico dell'hero home). Verifica empirica con Playwright +
Chromium locale (nessuna libreria di accessibilità automatica
disponibile nell'ambiente: ogni controllo — heading, landmark,
skip-link, focus-visible, contrasto WCAG, ordine DOM, overflow,
no-CSS, no-JS — è stato scritto a mano contro il DOM/CSS realmente
renderizzato, non stimato). Ogni riscontro reale è stato isolato con
un secondo script mirato prima di essere corretto o scartato come
falso positivo.

## Le sette superfici (invariate rispetto al Cantiere I, Prompt 213-214)

Home, Percorsi (indice e dettaglio), Articolo, Notizie, Categoria,
Ricerca — vedi `docs/PUBLIC_SURFACES_QA_MATRIX.md` per rotte/viste/
cantiere di adozione di ciascuna. Nessuna nuova superficie aggiunta al
perimetro; `autore`/`turing`/`header`/`footer`/`ticker`/`category-bar`
restano esplicitamente fuori, come per l'intera sequenza di cantieri
E-J.

## Difetti reali trovati e corretti

### 1. Overflow orizzontale a 320px — layout a due colonne (sidebar)

Causa radice: `public-premium.css` usa `grid-template-columns: 1fr`
nudo (non `minmax(0, 1fr)`) per `.public-premium-layout`/
`.article-premium__layout` a `max-width: 900px`. Un grid item con
`1fr` nudo eredita come minimo automatico il min-content del proprio
contenuto, non zero — se un widget della sidebar (testo/link non
spezzabile) ha una min-content width maggiore dello spazio
disponibile, la colonna e l'intera pagina si allargano oltre il
viewport. Bug trovato e documentato (non corretto) nei Cantieri E e G,
root-causato e corretto qui: classe marcatore `.kairus-sidebar-layout`
sui 4 contenitori già in perimetro (articolo/notizie/categoria/
ricerca) + una regola in `editorial-system.css` che ripristina
`minmax(0, 1fr)` alla stessa media query. Nessuna riga di
`public-premium.css` toccata.

### 2. Stesso bug, sulle griglie card — home e Percorsi

La stessa causa (`grid-template-columns: 1fr` nudo a breakpoint)
esisteva anche in `editorial-system.css` stesso su
`.public-card-grid`/`.related-premium-grid` (`max-width: 900px`,
già dietro le classi esistenti `.kairus-archive-grid`/
`.kairus-related-articles__grid`, nessuna vista modificata) e su
`.kairus-latest-articles__grid` (home, `max-width: 480px` — overflow
reale, 66px a 320px, unico caso dove la home stessa ne era colpita).
Corretto ovunque; per prevenzione (stessa forma, nessuna differenza
visiva) applicato anche a `.kairus-home-paths__grid`,
`.kairus-special-banner__grid` e `.kairus-form-shell__layout`, che
condividono il pattern pur non avendo ancora riprodotto l'overflow con
i dati di prova attuali.

### 3. Salto di heading — Percorsi indice (h1 → h3)

`content-clusters/index.blade.php` monta `x-kairus.section-heading`
con `headingLevel=1` (scelta esplicita e invariata del Cantiere D)
seguito dalla griglia di `x-kairus.path-card`, il cui titolo è sempre
un `<h3>` per costruzione del componente — nessun `<h2>` intermedio.
Corretto con un singolo `<h2 class="kairus-visually-hidden">Elenco dei
Percorsi</h2>` (utility già esistente) prima della griglia: nessun
cambiamento visivo, nessuna modifica al componente condiviso.

### 4. Focus da tastiera invisibile — form newsletter della home

`home-fix.css` (legacy) imposta `outline: 0` su
`.home-newsletter-band input` senza alcun sostituto (sfondo
trasparente, nessun bordo) — un vero fallimento WCAG 2.4.7 (Focus
Visible). Corretto aggiungendo `kairus-focusable` a input e pulsante
(stesso pattern già usato per il form di ricerca nel Cantiere G),
senza toccare nessun altro attributo — il `<form>` resta byte-per-byte
quello esistente. Verificato: il form newsletter nella sidebar
condivisa (`components/sidebar.blade.php`) non ha lo stesso
`outline: 0` e mantiene già l'anello di focus nativo — nessuna
correzione necessaria lì.

### 5. Due scarti di contrasto AA nei token Kairus

`--kairus-ink-faint` (meta/didascalie/testo terziario) dava 3.54:1 su
bianco, sotto la soglia 4.5:1 richiesta per testo non "large" —
schiarito a `#657469` (4.93:1), ancora più chiaro di `--kairus-ink-soft`
(7.28:1) per non perdere la gerarchia visiva. `.kairus-eyebrow` (colore
`--kairus-teal` di default) dava 4.42:1 sullo sfondo sage della
sezione Percorsi della home (`.kairus-home-paths`) — override scoped a
`--kairus-teal-deep` (già usato altrove per lo stesso scopo), 6.65:1.
Entrambe correzioni di token/regola scoped in `editorial-system.css`,
nessuna vista toccata.

## Falsi positivi (verificati e scartati)

- **Home "0 h1" nella prima passata**: fixture senza alcun articolo
  `featured=true` — l'hero (e il suo unico `<h1>`) è dentro
  `@if($featured)` per scelta esplicita del Cantiere D. Con un
  featured realistico l'h1 è presente e corretto.
- **Contrasto "bianco su bianco" su testata Turing/banda newsletter/
  tile categoria**: lo script di audit calcola lo sfondo effettivo
  risalendo gli antenati per `background-color`, ma questi tre usano
  un'immagine o un gradiente di sfondo (`background-image`) con testo
  chiaro sovrapposto — verificato con un controllo mirato
  (`backgroundImage !== 'none'` sull'antenato). Il banner Turing ha già
  uno scrim scuro esplicitamente introdotto per l'AA nel Cantiere D
  (Prompt 53); gli altri due sono CSS legacy pre-esistenti, mai
  toccati da alcun token Kairus.
- **Label "La tua email" `.sr-only`**: flag "bianco su bianco" del
  contrasto — è testo nascosto visivamente per tecnica standard
  (`position:absolute`, dimensione 1px), non renderizzato, non un
  problema reale.

## Riscontri noti, fuori perimetro, non corretti

Nessuno di questi appartiene alle 7 superfici adottate — sono tutti
componenti condivisi a livello di layout (`layouts/app.blade.php`),
esplicitamente fuori dal perimetro dell'intera sequenza di cantieri
E-J (stessa categoria di header/footer, mai adottati da nessun
cantiere):

- **20 link del ticker** (`components/ticker.blade.php`) sotto i 24px
  di altezza — testo in flusso continuo, rientra nell'eccezione
  "inline" del WCAG 2.5.8 Target Size, non una violazione reale.
- **14 controlli senza anello di focus visibile dedicato** — tutti
  interni a `components/header.blade.php` (nav categorie, pulsante
  ricerca), `components/category-bar.blade.php` (barra categorie
  sotto il ticker) o `components/footer.blade.php` (link categorie) —
  nessuno di questi file è tra le 7 superfici o è mai stato adottato
  da un cantiere Kairus.
- **Colore `#0d9488` (accento teal legacy)** con contrasto 3.74:1 in
  vari punti (skip-link, pulsante "Newsletter" header, popup
  newsletter, iniziali avatar autore, link attivo della
  `.public-pill-row`) — colore condiviso a livello di sito, mai un
  token Kairus, mai introdotto da questa sequenza di cantieri.

## Verifiche eseguite

- **Overflow**: 20/20 combinazioni (4 superfici × 5 viewport) pulite
  dopo il fix 1; 7/7 superfici × 5 viewport pulite (320-1440px) dopo i
  fix 1-2, incluso lo zoom 200% (equivalente 640px), 0px di overflow
  ovunque.
- **Heading/landmark/skip-link**: 7/7 superfici con un solo `<h1>`,
  nessun salto di livello, `<header>`/`<main>`/`<footer>` presenti,
  skip-link presente e puntato a un id realmente esistente.
- **Focus-visible**: ogni controllo interattivo raggiungibile
  focalizzato uno per uno (Playwright, non simulazione) su tutte le 7
  superfici — tutti i controlli nelle 7 superfici hanno un anello di
  focus visibile dopo il fix 4.
- **Contrasto**: calcolo reale del rapporto di luminanza WCAG (non
  stimato) per ogni nodo di testo foglia visibile, sfondo effettivo
  risalito dagli antenati — puliti dopo i fix 5, a meno dei falsi
  positivi e dei riscontri fuori perimetro documentati sopra.
- **Ordine DOM vs ordine visivo**: 0 discrepanze su tutte le 7
  superfici (figli diretti di `<main>` in ordine di posizione verticale
  non decrescente).
- **Nesting link/card**: 0 `<a>` annidati in un altro `<a>` su
  nessuna delle 7 superfici.
- **Alt immagini**: ogni `<img>` porta l'attributo `alt` su tutte le 7
  superfici.
- **`prefers-reduced-motion`**: regola attiva e rilevata su tutte le 7
  superfici (nessuna nuova animazione introdotta da questo cantiere).
- **Touch target**: unico riscontro sotto i 24px è il ticker
  (documentato sopra, fuori perimetro).
- **Stress-test testo lungo**: titoli/bio/handle/nomi Percorso
  volutamente lunghi su tutti i fixture di questo cantiere — nessun
  troncamento non voluto, nessun overflow, nessuna sovrapposizione
  (verificato anche via screenshot).
- **Modalità senza CSS**: tutte e 7 le superfici mantengono un `<h1>`
  corretto, `<main>`/`<nav>` presenti, contenuto testuale sostanziale e
  link funzionanti con i fogli di stile bloccati.
- **Modalità senza JS**: tutte e 7 le superfici mantengono `<h1>` e
  `<main>`, stesso numero di link, e ogni `<form>` ha un attributo
  `action` reale (submit funzionante via HTTP standard senza
  JavaScript) — verificato in particolare sui form newsletter e di
  ricerca.
- **Screenshot regression**: 14 screenshot (7 superfici × 2 viewport,
  375/1280px) ispezionati visivamente — nessuna regressione, nessun
  overlap, nessun taglio di contenuto, corretto a capo del testo
  lungo di stress-test.
- **Suite PHP mirata**: 260 test (design system + home + Percorsi),
  tutti verdi, incluso il nuovo `PublicSurfacesAccessibilityTest` (29
  test, 444 asserzioni).
- **Suite PHP completa**: 4001 test, 3990 passati, 11 skippati
  (pre-esistenti, non correlati), 0 falliti.
- **Pint**: pulito su ogni file toccato, ad ogni commit.
- **`git diff --check`**: pulito, ad ogni commit.
- **Isolamento**: nessun controller/route/model/migration/config/
  admin/redazione toccato — `KairusEditorialFoundationsIsolationTest`
  verde, nessuna nuova voce necessaria (le 7 superfici erano già
  uscite dall'elenco proibito nei Cantieri D-G).

## Rischi residui

- I tre punti "fuori perimetro" sopra (ticker, componenti di layout
  condivisi, colore legacy `#0d9488`) restano riscontri reali ma non
  corretti, per costruzione: appartengono a `header`/`footer`/
  `category-bar`/`ticker`, mai adottati da nessun cantiere di questa
  sequenza. Un cantiere dedicato a quei componenti condivisi (fuori
  dallo scope attuale "sette superfici pubbliche") potrebbe
  riprenderli.
- Le correzioni preventive su `.kairus-home-paths__grid`/
  `.kairus-special-banner__grid`/`.kairus-form-shell__layout`
  (Prompt 215-216, difetto 2) non avevano un overflow riprodotto con
  i dati di prova attuali — la correzione è comunque a costo zero
  (stessa resa visiva) e rimuove lo stesso rischio strutturale.

## Lavoro deliberatamente escluso

- Rimozione di CSS legacy (`public-premium.css`, `home-fix.css`,
  `premium-fixes.css`, ecc.): fuori perimetro fino al cantiere di
  audit CSS dedicato (Cantiere J).
- Qualunque modifica a `header`/`footer`/`ticker`/`category-bar`/
  `autore`/`turing`: esplicitamente fuori dal perimetro dell'intera
  sequenza di cantieri E-J.
- axe-core o altro strumento di accessibilità automatica: non
  disponibile nell'ambiente — ogni controllo di questo cantiere è
  stato scritto a mano e verificato contro il comportamento reale del
  browser (Playwright + Chromium), non stimato o delegato a una
  libreria assente.
