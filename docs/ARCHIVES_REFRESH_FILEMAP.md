# Notizie e Categorie — Mappa dei file (Cantiere F, Prompt 134-135)

## Route e controller (sola lettura)

- `routes/web.php:65` — `GET /notizie` → `ArticleController::index` (name `notizie`).
- `routes/web.php:66` — `GET /categoria/{slug}` → `ArticleController::category` (name `categoria`).
- `app/Http/Controllers/ArticleController.php:20-27` — `index()`: passa
  solo `articles` (`Article::published()->with('author')->paginate(12)`,
  nessuna distinzione "storia guida" nei dati).
- `app/Http/Controllers/ArticleController.php:29-56` — `category()`: passa
  `slug, categoryModel, categoryLabel, categoryDescription, categoryImage,
  category, articles` (paginato 12/pagina, categoria propria o secondaria
  via `whereHas`, stessa assenza di distinzione "storia guida").

## Viste principali (modificabili)

- `resources/views/notizie.blade.php` — hero, pillole categoria, griglia
  card, paginazione, sidebar.
- `resources/views/categoria.blade.php` — hero (con o senza immagine
  categoria), banda "Editorial Focus" (con link Turing solo per la
  categoria `intelligenza-artificiale` — dato reale, non un placeholder),
  griglia card, stato vuoto, paginazione, sidebar.

## Componenti/partial condivise (sola lettura salvo dove serve un ritocco CSS confinato)

- `resources/views/components/sidebar.blade.php` — condivisa con
  articolo (già toccato Cantiere E), ricerca, autore. Qui solo incluso.
- `resources/views/components/responsive-image.blade.php` — invariato.
- `resources/views/components/pagination.blade.php` (`$articles->links('components.pagination')`)
  — condivisa con Percorsi index (Cantiere D). Non toccata: già
  accessibile/coerente.
- `resources/views/collections/partials/structured-data.blade.php` —
  JSON-LD `CollectionPage`, condiviso tra le due viste (parametro
  `collectionName`). Non toccato.

## CSS

- **Base/condiviso (sola lettura):** `style.css`, `frontend-hardening.css`,
  `public-premium.css` (definisce `.public-hero`, `.public-card*`,
  `.public-pill-row`, `.category-premium-hero`, `.public-premium-layout`,
  `.public-section-head`, `.public-feature-band`, `.public-sidebar-card`,
  `.public-empty-state` — condivisi con l'articolo, già noti dal Cantiere E),
  `public-unified.css`, `premium-fixes.css`.
- **Nuove regole:** solo in `public/css/editorial-system.css`, classi
  `.kairus-*`.

## Test esistenti (baseline, 67 test verdi prima di ogni modifica)

`ArchivePaginationCanonicalTest`, `CategoryPageEmptyStateTest`,
`CategoryPaginationV1RegressionTest`, `CategorySourceDbFirstTest`,
`CollectionPageStructuredDataTest`, `InactiveCategoryPublicLabelTest`,
`ArticleSecondaryCategoryDiscoveryTest`, `HttpsCanonicalizationTest`,
`RssFeedDiscoveryTest` — più `PublicPageQueryBudgetTest`,
`PublicSurfaceResponsiveImageTest` (multi-superficie, includono
notizie/categoria).

## "Storia guida" (Prompt 139, 145) — nessuna distinzione nei dati

Né `index()` né `category()` distinguono un "articolo principale": è
la stessa collection `$articles` paginata, ogni elemento con lo stesso
trattamento. Il Prompt 139/145 stesso vieta di introdurla "se non esiste
una distinzione dati" — creare `$articles->first()` come storia guida
via Blade sarebbe una decisione editoriale non dedotta dai dati
(perché il PRIMO elemento di una lista ordinata per data non è
"l'articolo principale" nel senso redazionale, è solo il più recente).
Nessuna storia guida introdotta in questo cantiere; documentato come
lavoro escluso.

## Componenti condivisibili tra le due viste

Entrambe le viste usano la stessa card (`public-card` con badge
categoria, titolo, sommario troncato, footer autore/tempo di lettura) e
lo stesso pattern hero+meta — candidate naturali per lo stesso
`x-kairus.article-card`/`x-kairus.page-header` già adottati per
l'articolo nel Cantiere E.
