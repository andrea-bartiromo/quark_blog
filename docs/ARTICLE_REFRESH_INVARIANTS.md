# Articolo pubblico — Invarianti (Cantiere E, Prompt 106)

Contratto che questo cantiere non deve mai violare. Congelato prima di
qualunque modifica di markup/CSS, verificato dai test del Prompt 107.

## Semantica e gerarchia

- Un solo `<h1>`, il titolo dell'articolo, dentro `articles/partials/hero.blade.php`.
- `<h2>`/`<h3>` provengono solo dal corpo HTML dell'editor (via `TableOfContentsService`)
  o da titoli di sezione fissi ("Continua da qui", "Continua a leggere",
  "Continua il percorso", "Fonti" come `<h3>`, "Autore", "Condividi",
  "Indice" come `<p class="toc-nav__title">`, non un heading).
- Landmark esistenti: `<nav aria-label="Percorso di navigazione">` (breadcrumb),
  `<nav aria-label="Indice articolo">` (TOC), `<article class="article-premium">`,
  `<aside class="article-premium__aside">`. Nessun nuovo landmark da
  introdurre in questo cantiere.
- ID delle intestazioni h2/h3 generati da `TableOfContentsService` (usati
  dagli `href="#id"` del TOC e da eventuali ancore esterne): **non
  cambiare l'algoritmo di generazione degli id**.

## SEO / metadati (tutti da `Article::metaX()`, invariati)

- `title` = `$article->metaTitle().' — '.config('laboratorio.name')`.
- `description` = `$article->metaDescription()`.
- `og_type` = `'article'` (fisso).
- `robots` = `$article->metaRobots()`.
- `canonical` = `$article->metaCanonicalUrl()`.
- `og_title/description/image`, `twitter_title/description/image` = dai
  rispettivi metodi `Article::metaOgX()`/`metaTwitterX()`.
- `<meta property="article:published_time">` = `published_at->toIso8601String()`.
- `<meta property="article:modified_time">` **deliberatamente assente**
  (vedi commento in `structured-data.blade.php:13-22`: `updated_at` non è
  un segnale editoriale affidabile, viene toccato anche da
  `$article->increment('views')`).
- `<meta property="article:author">` = `$article->author->name`.
- `<meta property="article:section">` = `$categoryOptions[$article->category] ?? $article->category`.

## JSON-LD (`articles/partials/structured-data.blade.php`)

- `@graph` con due nodi: `NewsArticle` (mai `Article`/`BlogPosting`) e
  `BreadcrumbList`.
- `NewsArticle`: `mainEntityOfPage`, `headline` (titolo editoriale intero,
  mai troncato), `description`, `image` (cover reale o fallback raster
  globale, mai `hero-placeholder.svg` — Google non accetta SVG), reali
  `width`/`height` da `getimagesize()` o assenti (mai inventati),
  `datePublished`, **nessun `dateModified`** (stessa motivazione di
  `article:modified_time` sopra), `articleSection`, `author` (`Person`
  minimale: solo `name`+`url`, mai immagine/sameAs/credenziali),
  `publisher` (`Organization`, stesso `@id` della homepage), `about`
  presente **solo** se `$discoverableConcepts` non è vuoto (mai un array
  vuoto).
- `citation` **mai** presente/derivato da `primary_sources` (colonna non
  strutturata, non renderizzata pubblicamente in questa catena di branch
  — vedi FILEMAP).
- `BreadcrumbList`: Home → Categoria (solo se riconosciuta) → Articolo,
  posizioni rinumerate senza salti quando la Categoria è omessa.

## Breadcrumb visibile

- Deve restare sincronizzato 1:1 con il `BreadcrumbList` JSON-LD (stessa
  fonte `$categoryOptions`, stessa condizione di riconoscimento
  categoria).

## Dati e query (controller — non toccati in questo cantiere)

- `$article` con `author` eager-loaded, nessun `comments` eager-load.
- `$related` da `ArticleRelatedService` (algoritmo, quantità, ordinamento,
  esclusioni invariati).
- `$pathNavigation` da `ArticlePathNavigation` (prev/next/progress).
- `$continuation`/`$continuationTargetUrl` da `ArticleContinuationService`
  + URL firmato (Second Read Analytics) — invariati.
- `$discoverableConcepts` — solo per JSON-LD, nessun blocco UI visibile
  (invariante esplicito già in vigore, da non introdurre in questo
  cantiere).
- Budget di query della pagina (`PublicPageQueryBudgetTest`) — nessuna
  query nuova ammessa: solo Blade/CSS.

## Fonti ("Fonti")

- Meccanismo legacy attivo: `explode('---', $article->body)` in
  `articolo.blade.php:92-94`, secondo blocco renderizzato come pannello
  "Fonti" testuale (`nl2br(e($sources))`) in `body.blade.php:72-77`.
  Questo cantiere adotta lo stile Kairus su **questo** meccanismo, non su
  `primary_sources` (vedi FILEMAP — sovrapposizione con branch non
  mergiati).
- L'heading "Fonti" è deliberatamente escluso dal TOC
  (`ArticleTableOfContentsTest::test_toc_does_not_include_the_sources_section_heading`)
  — non deve rientrarci.

## Cover e responsive image

- `<x-responsive-image>` per la cover hero: `diskName`, `src` (fallback
  `hero-placeholder.svg`), `alt` (`cover_alt` o titolo), `loading="eager"`,
  `fetchpriority="high"`, `sizes` — tutti invariati.
- `<x-media.image-viewer>` lightbox con `caption`/`credit`/`source`/
  `sourceUrl`/`license` — invariato.
- Cover assente → fallback `hero-placeholder.svg` via `asset()`, mai
  un'immagine reale inventata.

## Autore

- `$article->author` è uno `User` standard (non un modello Author
  dedicato). Campi oggi renderizzati sulla pagina articolo: solo `name`,
  `photo` (o iniziali), link a `route('autore', $article->author)`.
  Nessuna bio/credenziali/social renderizzati qui oggi — non introdurli
  in questo cantiere (fuori perimetro "Blade+CSS", richiederebbe dati non
  già disponibili alla vista).

## Newsletter / Percorso / Social

- Form newsletter (`articles/partials/newsletter-band.blade.php`):
  `action`, `method`, `_token`, `name="source" value="article"`,
  `name="email"` — invarianti byte per byte (stesso trattamento già
  applicato in Cantiere D alla home).
- `path-continuation.blade.php`: attributi `data-path-*` (analytics),
  `data-path-continues`, URL di navigazione, subscribe-form — invarianti.
- `continue-reading.blade.php`: `$continuationTargetUrl` firmato,
  attributi `data-continuation-click`/`data-source-article-id`/
  `data-target-article-id` — invarianti (Second Read Analytics).
- `share-card.blade.php`: URL di condivisione (X/WhatsApp/LinkedIn) e
  `copyArticleLink()` — invarianti, nessun nuovo JS.

## Cosa NON esiste oggi (da non inventare)

- Nessuna bio/credenziali autore renderizzate sulla pagina articolo.
- Nessuna metodologia pubblica collegata dall'articolo.
- Nessuna revisione pubblica ("aggiornato il...") — colonna `updated_at`
  esplicitamente giudicata inaffidabile (vedi sopra).
- Nessuna fonte strutturata (solo testo libero legacy).
