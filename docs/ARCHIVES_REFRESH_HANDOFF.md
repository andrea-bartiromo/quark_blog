# Notizie e Categorie — Handoff (Cantiere F)

Adozione di Kairus Editorial Foundations V1 su `/notizie` e
`/categoria/{slug}`.

## SHA

- Base: `feat/kairus-article-refresh` @ `fb29dc6528a83eb30de855f20464a26f5269a87a`
- Branch: `feat/kairus-archives-refresh`
- 4 commit (più i commit di chiusura del Prompt 157), nessuna PR, nessun
  merge, nessun deploy.

## Commit

1. `be6458c` — docs: audit filemap + invarianti.
2. `23371d8` — test: congela il contratto prima di ogni modifica.
3. `b949e33` — feat: adozione page-header/article-card/article-meta/empty-state.
4. `cd1cb13` — test+docs: QA viewport + test d'integrazione cross-archivio.

## File toccati

**Documentazione (4):** `docs/ARCHIVES_REFRESH_{FILEMAP,INVARIANTS,VIEWPORTS,HANDOFF}.md`.

**CSS (1 solo file):** `public/css/editorial-system.css` — nessun'altra
riga CSS toccata, nessuna riga rimossa.

**Viste (2):** `resources/views/{notizie,categoria}.blade.php`.

**Test (2):** `ArchivesRefreshTest` (nuovo), `KairusEditorialFoundationsIsolationTest`
(aggiornato: `notizie.blade.php`/`categoria.blade.php` escono
dall'elenco vietato — stessa evoluzione già vista per home/articolo).

Nessun controller, route, model, migration, config, admin, redazione
toccato — verificato con `git diff --name-only` sull'intero cantiere
(elenco esaustivo sopra).

## Componenti Kairus montati

| Blocco | Componente | Note |
|---|---|---|
| Hero Notizie | `x-kairus.page-header` | Era già una superficie piatta, nessuna perdita di contenuto |
| Hero Categoria (senza immagine) | `x-kairus.page-header` | Stesso caso |
| Hero Categoria (con immagine) | Nessuno — solo token | Stesso motivo dell'hero articolo (Cantiere E): nessun componente supporta testo su immagine di sfondo |
| Griglie card (entrambe le pagine) | `x-kairus.article-card` + `x-kairus.article-meta` (slot meta, density compact) | Algoritmo/ordine/paginazione invariati |
| Stato vuoto Categoria | `x-kairus.empty-state` | Rimasto generico per-slug come prima |
| Stato vuoto Notizie | `x-kairus.empty-state` | Nuovo — la vista non aveva un ramo `@empty` prima |

**Esclusi con motivazione:**

- **"Storia guida"** (Prompt 139/145): nessuna distinzione dati in
  nessuna delle due query controller — introdurla via `$articles->first()`
  sarebbe una scelta editoriale non deducibile, esplicitamente vietata
  in questo caso.
- **Banda "Editorial Focus"** (categoria): già uno stile coerente e
  distinto (gradiente scuro), nessun componente Kairus modella una
  banda promozionale con CTA condizionale — stesso trattamento della
  Speciale Turing in home (Cantiere D).
- **`public-pill-row`** (navigazione tra categorie): già accessibile
  (stati hover/active, `flex-wrap`), nessun componente Kairus modella
  una barra di pillole di navigazione — non toccato.

## Verifiche eseguite

- **Suite mirata**: 139 test, 139 passati (design system + archivi +
  SEO/canonical/paginazione/responsive-image/query-budget), 0 fallimenti.
- **Viewport QA**: 20/20 combinazioni (4 pagine × 5 viewport) senza
  overflow, incluse etichetta categoria molto lunga, titolo articolo
  molto lungo, cover mancante, categoria realmente vuota. Nessun bug
  trovato (a differenza del Cantiere E, questa sidebar non condivide la
  struttura `.article-premium__aside` col bug pre-esistente).
- **Test d'integrazione cross-archivio**: Notizie e una Categoria
  verificate insieme sullo stesso articolo reale, stessi componenti
  montati coerentemente, H1 unico su entrambe.
- **Isolamento**: nessun controller/route/model/migration/config/admin/
  redazione toccato.
- **Pint**/**`git diff --check`**: puliti su ogni commit.

## Rischi residui

Nessuno identificato specifico a questo cantiere.

## Lavoro deliberatamente escluso

- Hero categoria con immagine, banda Editorial Focus, pill-row di
  navigazione: motivati sopra.
- "Storia guida" per nessuna delle due pagine: nessuna distinzione dati
  disponibile.
