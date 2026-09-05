# Ricerca editoriale — Mappa dei file (Cantiere G, Prompt 159-160)

## Route e controller (sola lettura)

- `routes/web.php:68` — `GET /ricerca` → `SearchController::index` (name `ricerca`).
- `app/Http/Controllers/SearchController.php` — parametri di query:
  `q` (testo), `categoria`, `autore` (id, normalizzato in modo
  sicuro — vedi commento PR #166 sul cast), `da`/`a` (date). `$hasFilter`
  = vero se almeno uno è valorizzato; altrimenti `$results` resta una
  `collect()` vuota (nessuna query eseguita). Ricerca zero-risultati
  tracciata (`SearchZeroResultDiagnosticsService::record()`) **solo**
  per query testuali reali (`$query !== ''`) con 0 risultati — mai per
  un filtro senza testo.
- `app/Services/Search/ArticleSearchService.php` — **solo articoli**:
  nessun Percorso, nessun Concetto restituito. I Prompt 169
  ("risultati Percorso") e 170 ("risultati Concetto") non si applicano:
  "se non li restituisce, non estendere il backend" — confermato, non
  toccato in questo cantiere.

## Vista principale (modificabile)

- `resources/views/ricerca.blade.php` — hero, form (barra + filtri
  avanzati in `<details>`), tre stati (risultati / nessun risultato /
  stato iniziale), paginazione, sidebar.

## Componenti/partial condivise (sola lettura)

- `resources/views/components/sidebar.blade.php` — condivisa con
  articolo/notizie/categoria (già in perimetro nei cantieri precedenti).
- `resources/views/components/responsive-image.blade.php` — invariato.
- `resources/views/components/pagination.blade.php` — invariato.

## CSS

- **Base/condiviso (sola lettura):** `style.css`, `frontend-hardening.css`,
  `public-premium.css` (`.public-hero--compact`, `.public-result-card*`,
  `.premium-search-panel*`, `.premium-filter-panel*`, `.premium-field`,
  `.public-empty-state*`), `public-unified.css`, `premium-fixes.css`.
- **Nuove regole:** solo `public/css/editorial-system.css`, classi `.kairus-*`.

## Test esistenti (baseline, 68 verdi)

`SearchControllerTest`, `Unit/Search/SearchTokenizerTest`,
`Feature/Search/SearchZeroResultDiagnosticsServiceTest`.

## Sicurezza

- `robots` già `noindex,follow` (fisso, `@section('robots', 'noindex,follow')`)
  — non indicizzata oggi, deve restarlo.
- La query (`$query`) è già interpolata solo via `{{ }}` Blade (escaping
  automatico) in title/H1/attributo `value` — nessuna interpolazione
  `{!! !!}` non escapata trovata. Verificato, da congelare con un test
  di regressione (Prompt 172), non da correggere.
