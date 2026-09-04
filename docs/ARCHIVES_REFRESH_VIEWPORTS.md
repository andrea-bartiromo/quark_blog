# Notizie e Categorie — QA viewport (Cantiere F, Prompt 148-151)

Verifica empirica (Playwright + Chromium locale) su 4 pagine × 5
viewport (320/375/768/1024/1440px), con dati di prova: 13 articoli
(incluso uno con titolo molto lungo, uno senza cover), una categoria
con etichetta molto lunga ("Tecnologia e Società con un Nome di
Categoria Particolarmente Lungo"), una categoria realmente vuota.

## Overflow orizzontale

Tutte le 20 combinazioni **OK** (`scrollWidth <= clientWidth`):
`/notizie`, `/categoria/fisica`, `/categoria/intelligenza-artificiale`,
`/categoria/vuota-qa` — nessun overflow a nessun viewport. Nessun
problema del tipo trovato nel Cantiere E (sidebar articolo) qui: la
sidebar di Notizie/Categoria non condivide la stessa struttura
`.article-premium__aside` con gli `!important` di
`premium-fixes.css` che causavano quel bug — usa invece
`.public-premium-layout`/`.public-sidebar-card`, verificata pulita.

## Casi limite verificati visivamente (screenshot 1280px)

- **Navigazione tra categorie** (`public-pill-row`, Prompt 148): con
  un'etichetta molto lunga, la pillola va a capo nel proprio flusso
  (`flex-wrap` già presente in CSS legacy), nessun overflow, elenco/
  ordine/URL invariati (non toccato in questo cantiere).
- **Cover mancanti** (Prompt 149): il fallback (`placeholder-1.svg`)
  mantiene l'allineamento della griglia, nessuna card più bassa/più
  alta delle altre, nessun placeholder ingannevole (icona neutra, non
  un'immagine editoriale finta).
- **Titoli lunghi** (Prompt 150): il titolo della card va a capo su più
  righe senza clipping né altezza rigida (coerente con il vincolo del
  componente stesso — `x-kairus.article-card` non usa mai
  `line-clamp`), nessun overflow.
- **Categoria vuota**: `x-kairus.empty-state` renderizzato correttamente
  con il nome della categoria reale nel messaggio, nessuna sezione
  "Ultimi articoli" orfana sopra un'area vuota, nessun banner Editorial
  Focus rotto (resta invariato, con o senza articoli).

## Accessibilità (Prompt 152)

- Griglie card: `<ul>`/`<li>` semantico (era `<div>`), un solo `<a>`
  per card (`x-kairus.article-card`), nessun link annidato.
- Hero senza immagine (`x-kairus.page-header`): un solo H1 per pagina,
  landmark invariati.
- Hero categoria con immagine: nessun cambiamento di landmark/heading,
  solo token (`.kairus-tone-navy`, bilanciamento titolo).
- Focus visibile: `x-kairus.article-card`/`x-kairus.page-header`
  portano già `kairus-focusable` internamente (Foundations V1) — nessun
  intervento aggiuntivo necessario qui.
- Stato vuoto: heading (`<h3>` nel componente) sempre accompagnato da un
  messaggio, mai un titolo orfano.

## SEO (Prompt 153)

- Nessuna modifica a title/canonical/robots/Open Graph.
- JSON-LD `CollectionPage` (`collections/partials/structured-data.blade.php`)
  non toccato — verificato con `CollectionPageStructuredDataTest`
  (verde, invariato).
- Canonical di paginazione (`ArchivePaginationCanonicalTest`) verde,
  invariato.
