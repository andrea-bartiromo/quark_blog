# Ricerca editoriale — Invarianti (Cantiere G, Prompt 160)

## Parametri di query

- `q` (testo, `trim()`ato), `categoria`, `autore`, `da`, `a` — nomi e
  significato invariati, tutti opzionali.
- `$hasFilter` = OR di tutti e 5 — nessuna ricerca eseguita se tutti
  assenti (stato iniziale, nessuna query DB).
- `autore` normalizzato con `ctype_digit()` (mai un cast diretto —
  vedi commento PR #166: un valore non numerico non deve "sparire"
  silenziosamente come filtro, deve produrre zero risultati).

## Paginazione

- `$results` è un `LengthAwarePaginator` solo quando `$hasFilter` è
  vero; altrimenti una `Collection` vuota. La vista verifica il tipo
  (`instanceof \Illuminate\Contracts\Pagination\Paginator`) prima di
  trattarlo come paginato — non assumere mai che `$results` sia sempre
  un paginatore.
- `$results->links('components.pagination')` — stesso partial già in
  uso su Percorsi/Notizie/Categoria.

## Robots / SEO

- `robots` fisso `noindex,follow` — **mai** cambiato in questo cantiere
  (Prompt 177: "non rendere indicizzabili pagine che prima non lo
  erano").
- Nessun canonical dedicato oggi (eredita quello di default del layout)
  — non introdurne uno nuovo.

## Sicurezza output query

- `$query` interpolato solo via `{{ }}` (mai `{!! !!}`) in: title,
  H1 ("Risultati per "{{ $query }}""), `value` dell'input di ricerca.
  Deve restare così — nessuna nuova interpolazione non escapata.

## Zero-result tracking

- `SearchZeroResultDiagnosticsService::record($query)` chiamato **solo**
  quando `$query !== ''` E `$results->total() === 0` — mai per filtri
  senza testo, mai duplicato, mai spostato in questo cantiere (Prompt
  178: "non modificare tracking o consenso").

## Stati della vista (tutti già esistenti, da vestire non da inventare)

1. **Iniziale** (nessun filtro in `request()`): empty-state "soft"
   ("Inizia una ricerca").
2. **Risultati presenti**: lista + paginazione.
3. **Nessun risultato con almeno un filtro attivo**: empty-state con
   link "Rimuovi i filtri" verso `route('ricerca')` pulita.

## Form

- `method="GET"`, `action="{{ route('ricerca') }}"` — mai POST, mai
  un'altra action (i parametri devono restare nella query string,
  bookmarkabili/condivisibili).
- Nome dei campi (`q`, `categoria`, `autore`, `da`, `a`) invariati.
- `<details class="premium-filter-panel">` aperto automaticamente se
  un filtro avanzato è già attivo — comportamento da preservare.
