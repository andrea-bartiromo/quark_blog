# Notizie e Categorie — Invarianti (Cantiere F, Prompt 136)

## Route e status HTTP

- `/notizie` sempre 200 (nessuna condizione di 404).
- `/categoria/{slug}` → 404 se lo slug non risolve né a un `Category`
  reale né a una chiave di `Category::options(false)` (fallback
  config) — `abort_unless` in `ArticleController::category()`.

## Canonical

- `/notizie`: pagina 1 → `route('notizie')` (mai `?page=1`); pagina
  N>1 → `route('notizie', ['page' => N])`, mai su pagina 1 (contenuto
  diverso). Costruito da `route()`, mai da `request()->fullUrl()` —
  nessun parametro estraneo (UTM, tracking) nel canonical.
- `/categoria/{slug}`: stessa logica, `route('categoria', ['slug' =>
  $slug, 'page' => N])`.

## Paginazione

- 12 articoli per pagina su entrambe le viste (`Article::published()->paginate(12)`).
- Solo articoli `published()` (mai scheduled/draft/review).
- `$articles->links('components.pagination')` — stesso partial di
  paginazione già in uso su Percorsi index (Cantiere D), invariato.

## Ordinamento e visibilità

- Ordinamento e criteri di inclusione derivano interamente da
  `Article::published()` (scope del modello) — non toccati in questo
  cantiere.
- `/categoria/{slug}`: un articolo compare se la categoria è quella
  **principale** oppure una delle **secondarie** (`whereHas`, `EXISTS`,
  mai righe duplicate).

## Dati categoria

- `categoryLabel` = `$categoryModel?->name ?? $categories[$slug]` (DB
  first, poi fallback a `config('laboratorio.categories')` via
  `Category::options`) — mai un'etichetta inventata.
- `categoryDescription`/`categoryImage` possono essere `null` — la vista
  deve gestire l'assenza senza spazi vuoti (già il caso oggi: hero
  alternativo `public-hero--light` senza immagine, paragrafo
  condizionale per la descrizione).
- Link "Esplora la Turing Experience" mostrato **solo** per
  `$category === 'intelligenza-artificiale'` — dato reale (percorso
  editoriale esistente), non un placeholder generico da estendere ad
  altre categorie.

## SEO / dati strutturati

- `collections/partials/structured-data.blade.php`: JSON-LD
  `CollectionPage`, parametro `collectionName` ("Tutti gli articoli" /
  `$categoryLabel`) — invariato.
- Nessun dato strutturato aggiuntivo (Article/ItemList per-card) da
  introdurre in questo cantiere.

## Nessuna "storia guida"

Vedi FILEMAP — nessuna distinzione dati tra articoli in nessuna delle
due viste; non introdurne una via query o `$articles->first()` in Blade.

## Card articolo (contratto visivo attuale, da preservare nei dati)

Per ciascun articolo: link a `route('articolo', $article->slug)`, cover
(`cover_image` o placeholder), badge categoria, titolo, sommario
troncato a 118 caratteri (`Str::limit`), nome del solo primo nome
dell'autore (`Str::before($article->author->name, ' ')` — invariato,
comportamento esistente, non un dato incompleto da "correggere" in
questo cantiere), tempo di lettura in minuti.

## Stato vuoto categoria

`categoria.blade.php` mostra uno stato vuoto generico (🔬, "Nessun
articolo pubblicato ancora...") quando `$articles` è vuoto — nessun testo
specifico per una singola categoria hardcoded (già corretto: la stessa
vista serve ogni slug). `notizie.blade.php` non ha oggi un ramo `@empty`
esplicito (la home ha sempre almeno il seed reale in produzione) — se
introdotto, deve restare generico allo stesso modo.
