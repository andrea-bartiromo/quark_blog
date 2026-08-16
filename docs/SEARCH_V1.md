# Search V1

Ricerca pubblica (`/ricerca`, `SearchController`): come una query viene
interpretata, come vengono classificati/ordinati i risultati, cosa NON
fa ancora. Questo documento copre Search V1 (lessicale, locale,
multi-token). Search V2 (concetti canonici, alias/sinonimi editoriali,
intento, eventuale Content Graph) resta futura — vedi §5.

## 1. Filosofia

Prima di Search V1 la ricerca pubblica confrontava la query come UNA
sola stringa letterale (`LIKE '%query%'`) contro title/excerpt/body. Una
ricerca per "test turing" non trovava un articolo che nel titolo avesse
esattamente "Il Test di Turing spiegato davvero" — la sottostringa
letterale "test turing" non compare mai in quel titolo.

Search V1 tokenizza la query in termini significativi e valuta ogni
termine indipendentemente per campo, sommando i contributi in un
punteggio di rilevanza. Resta interamente lessicale (nessun
embedding/AI/servizio esterno) e deterministica (stessa query, stesso
ordinamento, sempre).

## 2. Architettura

```
query utente
    │
    ▼
SearchTokenizer::tokenize()
    │  (termini normalizzati, deduplicati, max 8)
    ▼
ArticleSearchService::search()
    │
    ├─ candidatura: almeno un termine in un campo (WHERE)
    ├─ ranking: peso per (termine, campo) + bonus frase/copertura (ORDER BY)
    └─ filtri esistenti (categoria/autore/date) + paginazione, invariati
    │
    ▼
Article::published() → LengthAwarePaginator
```

| File | Ruolo |
|---|---|
| `app/Services/Search/SearchTokenizer.php` | Tokenizzazione, normalizzazione, variante morfologica leggera |
| `app/Services/Search/ArticleSearchService.php` | Candidatura, ranking, filtri, paginazione |
| `app/Http/Controllers/SearchController.php` | Legge la request, delega al service, passa i dati alla view |
| `resources/views/ricerca.blade.php` | UI pubblica (invariata rispetto a prima di questa missione) |

`SearchTokenizer` è deliberatamente **indipendente** dal tokenizer V3 di
`ArticleLinkSuggestionService` (Internal Linking V2): stesso principio
generale (estrazione termini Unicode-safe, stopword, elisioni), ma
implementazione separata — i due domini (suggerimento di collegamenti
interni tra articoli vs. interpretazione di una query breve digitata da
un lettore) hanno soglie e stopword diverse, e accoppiarli avrebbe reso
una modifica futura a un dominio un rischio silenzioso per l'altro.

## 3. Tokenizzazione

`SearchTokenizer::tokenize()`:

- Unicode-safe (`\p{L}`, `\p{N}`), preserva identificatori alfanumerici
  e con trattino (`GPT-5`, `covid-19`, `Wi-Fi`, `Church-Turing`);
- normalizza trattini/apici tipografici alle forme ASCII prima della
  tokenizzazione (copia-incolla editoriale);
- riduce le elisioni italiane comuni (`l'universo` → `universo`);
- case-insensitive;
- scarta un piccolo elenco di stopword italiane brevi (articoli,
  preposizioni articolate, congiunzioni corte) — elenco dedicato,
  diverso da quello di Internal Linking V2;
- token di **1 carattere sempre esclusi** (politica conservativa
  esplicita); token di **2 caratteri validi** (`IA` funziona);
- token puramente numerico escluso (mai un segnale tematico isolato);
- **limite di 8 termini significativi** per query (`MAX_SIGNIFICANT_TOKENS`):
  oltre questo, i token in eccesso vengono scartati — una query anomala
  non genera una clausola SQL arbitrariamente grande.

Un token puramente di punteggiatura, o composto solo da stopword/token
troppo corti, produce un array vuoto: `ArticleSearchService` lo
riconosce e restituisce **zero risultati**, mai l'intero catalogo (vedi
§6).

## 4. Variante morfologica leggera

`SearchTokenizer::morphologicalVariant()` implementa **esclusivamente**
lo scambio bidirezionale delle vocali finali più comuni per i nomi
italiani regolari di 1ª/2ª classe:

- `a` ↔ `e` (`stella` ↔ `stelle`)
- `o` ↔ `i` (`albero` ↔ `alberi`)

Applicato solo a termini di **5+ caratteri** (sotto questa soglia il
rischio di rumore supera il beneficio). Non è uno stemmer: non tenta di
individuare una radice, non gestisce eccezioni/irregolari. Una variante
generata che non corrisponde a nessuna parola italiana reale (es.
`pianeta` → `pianete`) è innocua: viene solo confrontata col testo, non
validata come parola — se non compare in nessun articolo, non cambia
nulla.

## 5. Ranking

Per ogni termine e per ogni campo (`title`, `excerpt`, `category`,
`body`) il termine — o la sua variante morfologica — contribuisce un
peso fisso se trovato:

| Campo | Peso |
|---|---|
| `title` | 20 |
| `excerpt` | 8 |
| `category` | 5 |
| `body` | 3 |

A questi si sommano bonus aggiuntivi (solo con **2+ token** per i bonus
di frase, dove "frase" ha senso):

| Bonus | Punti | Condizione |
|---|---|---|
| Titolo esatto | 100 | `LOWER(title) = LOWER(query)` |
| Frase intera nel titolo | 60 | i token uniti da spazio compaiono letteralmente nel titolo |
| Titolo copre tutti i token | 40 | ogni token (o variante) trovato nel titolo |
| Frase intera in excerpt/body | 15 | i token uniti da spazio compaiono in excerpt o body |

Tutto sommato in un'unica espressione (`ORDER BY (…) DESC`), con
`published_at DESC` come tie-breaker finale deterministico. Un match nel
titolo batte sempre un match solo nel body con margine ampio (20 vs 3
per singolo termine, prima ancora dei bonus); più termini coperti
produce sempre un punteggio maggiore, a parità d'altro.

## 6. Query vuota, insignificante, wildcard

| Input | Comportamento |
|---|---|
| Query assente/vuota | Nessun filtro testuale — comportamento invariato rispetto a prima (mostra tutto se altri filtri sono attivi, altrimenti stato "inizia una ricerca") |
| Query non vuota ma senza token utilizzabili (`"!!!"`, solo stopword) | **Zero risultati espliciti** — mai l'intero catalogo |
| `%` o `_` isolati o incorporati | Mai trattati come wildcard SQL: il tokenizer li tratta come separatori (non sopravvivono in alcun token), e ogni pattern LIKE è comunque generato da `escapeLikeValue()` con `ESCAPE '\'` esplicito, difesa aggiuntiva indipendente dalla tokenizzazione |

Ogni valore utente entra in una query SQL **solo tramite parametro
bindato** — mai concatenato nella stringa raw.

## 7. Filtri e paginazione (invariati)

Categoria, autore, data-da, data-a: stessi filtri di prima, applicabili
da soli o insieme al testo. La paginazione (15 per pagina) preserva i
parametri via `withQueryString()`, come già accadeva.

## 8. Performance

Candidatura + ranking restano un'unica `SELECT` (nessuna query per
token, nessuna query per candidato), più la `COUNT` di paginazione e
l'eager load dell'autore — numero di query costante indipendentemente
dal numero di token nella query o dalla dimensione del catalogo (vedi
`test_search_query_count_does_not_grow_with_corpus_size_or_token_count`).

## 9. Limiti noti e accettati di Search V1

- **Matching per sottostringa, non per confine di parola.** Un token
  breve può comparire dentro una parola più lunga non correlata — es.
  la query `IA` (2 caratteri, valida per design) trova anche articoli il
  cui unico punto di contatto è la sottostringa "ia" dentro parole come
  "energia". Il ranking comunque privilegia correttamente i match reali
  (titolo/excerpt pertinenti restano in cima), ma il recall totale può
  essere più ampio del previsto per token molto corti. Un matching
  word-boundary-aware richiederebbe funzioni SQL non ugualmente
  portabili tra SQLite (test/CI) e MySQL (produzione), o un indice
  full-text dedicato — rimandato a Search V2.
- Nessuna similarità semantica: "sole" non trova "stella" per
  conoscenza concettuale, "macchina che pensa" non trova il Test di
  Turing se quelle parole non compaiono nei campi cercati.
- Nessuna correzione di errori di battitura.
- Il body viene cercato come storage corrente (può contenere markup
  HTML) — nessuna normalizzazione/indicizzazione separata introdotta.
- Ranking euristico, non probabilistico; nessun machine learning,
  nessuna personalizzazione.
- **Case-folding non Unicode-aware su SQLite.** `LIKE` in SQLite
  ripiega maiuscole/minuscole solo per l'ASCII: una query `è` non trova
  un titolo che inizia con la maiuscola accentata "È" (comune in
  italiano a inizio frase). Su MySQL con collation `utf8mb4_*_ci`
  (tipica configurazione di produzione) questo non è un problema — il
  confronto è già case/accent-insensitive nativamente. Un fix
  realmente portabile richiederebbe o l'estensione ICU di SQLite (non
  garantita disponibile) o una funzione SQLite custom registrata via
  PDO (nuova infrastruttura di connessione, non solo query) — rimandato
  a Search V2. Non influisce sulla stragrande maggioranza delle query
  (minuscole, ASCII, o termine non a inizio titolo).

Questi limiti sono scelte deliberate di scope per una V1 lessicale
locale, non bug.

## 10. Follow-up Search V2 (non implementati qui)

- Matching word-boundary-aware o full-text nativo per ridurre il
  falso-recall dei token molto corti (§9).
- Sinonimi/alias editoriali gestiti da dashboard.
- Concetti canonici condivisi con Internal Linking V2 (es. "buco nero"
  come unità semantica esplicita, non solo bonus di frase).
- Intento di ricerca, autocomplete, typo tolerance.
- Content Graph, topic page, query analytics.

Nessuno di questi elementi è implementato in Search V1: la tokenizzazione
centralizzata in `SearchTokenizer` è pensata per restare un punto di
estensione naturale (una funzione che produce termini normalizzati),
non per essere già l'architettura finale.
