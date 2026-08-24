# TROVA V1 — design repository-grounded

## Stato

**DESIGN COMPLETE — IMPLEMENTATION ANCORA DEFERRED.** Vedi "Aggiornamento Missione 26" in fondo al documento per lo stato corrente dei quattro blocchi elencati sotto — due sono stati risolti da allora, uno resta bloccante.

La dipendenza Content Graph (#279) non e su `main`. Questa branch non copia model, migration o service da quella PR e non costruisce una seconda infrastruttura semantica.

## Ricerca pubblica reale su main

La ricerca corrente e gia una Search V1 lessicale non banale:

- controller: `SearchController`;
- motore: `App\Services\Search\ArticleSearchService`;
- tokenizer dedicato: `SearchTokenizer`;
- solo `Article::published()`;
- filtri categoria/autore/data;
- ricerca su `title`, `excerpt`, `category`, `body`;
- ranking deterministico gia definito nel codice;
- normalizzazione di punteggiatura Unicode;
- wildcard LIKE escapati;
- paginazione server-side a 15 risultati.

TROVA non deve riscrivere questo motore da zero.

## Principio architetturale

TROVA V1 deve essere un **orchestratore di fonti di ricerca**, non un secondo `ArticleSearchService`.

Fonti previste, in ordine di maturita:

1. Articoli — disponibile ora.
2. Categorie — disponibile ora.
3. Percorsi (`ContentCluster`) — disponibile ora.
4. Concetti — dipende da #279 su `main`.
5. Alias — dipende da #279 su `main`.
6. Domande — dipende da #279 su `main` e dalla policy della Missione 09.

Non esiste su `main` una tassonomia generica `Speciale` adatta a essere indicizzata come gruppo TROVA; le esperienze Turing sono route/controller dedicati. Nessun adapter viene inventato in V1.

## Normalizzazione query

Riutilizzare `SearchTokenizer` per:

- trim;
- case normalization;
- punteggiatura Unicode;
- stopword/token troppo corti secondo le regole gia esistenti.

Una query non vuota che produce zero token utili deve restituire zero risultati/suggestion, non degradare a catalogo completo.

## Ranking: niente nuovi pesi casuali

### Articoli

Riutilizzare **senza alterarlo implicitamente** il ranking di `ArticleSearchService` finche un benchmark separato non ne giustifica una revisione.

TROVA non somma score di entita eterogenee agli score articolo.

### Gruppi non-Article

Ogni gruppo usa classi di match ordinate, non numeri inventati.

#### Categorie

Ordine:

1. nome normalizzato esatto;
2. slug esatto;
3. tutti i token presenti nel nome;
4. almeno un token nel nome;
5. tie-break `name ASC`, poi `id ASC`.

Solo categorie pubblicamente utilizzabili secondo la policy DB-first effettiva dopo la stabilizzazione di #258.

#### Percorsi

Ordine:

1. nome normalizzato esatto;
2. slug esatto;
3. tutti i token nel nome;
4. almeno un token nel nome;
5. token nella descrizione;
6. tie-break `sort_order ASC`, poi `id ASC`.

Solo Percorsi `is_active=true`. Un Percorso senza tappe pubbliche non deve essere suggerito come destinazione pubblica.

#### Concetti / Alias (dopo #279)

Ordine:

1. nome concetto esatto;
2. slug esatto;
3. alias esatto;
4. tutti i token nel nome/alias;
5. almeno un token nel nome/alias;
6. tie-break nome, poi id.

Solo concetti `active`. Un concetto non deve diventare una pagina pubblica automaticamente: TROVA puo usarlo come metadato di routing verso articoli finche non esiste una topic-page approvata.

#### Domande (dopo #279 + Missione 09)

Ordine:

1. question text normalizzato esatto;
2. slug esatto;
3. tutti i token nel testo;
4. almeno un token nel testo;
5. tie-break editoriale (`sort_order`, id) se previsto dal model reale.

Solo domande approvate con risposta realmente pubblicata. La presenza nel risultato non implica automaticamente una pagina `/domande/{slug}`.

## Result contract

TROVA V1 deve restituire gruppi separati, evitando un ranking globale pseudo-scientifico:

```text
articles
categories
paths
concepts
questions
```

Ogni item non-Article deve esporre almeno:

- `type`;
- `id`;
- `label`;
- `url` se esiste una destinazione pubblica reale;
- `match_class` descrittiva (`exact_name`, `exact_alias`, `all_tokens`, ...);
- eventuale context/excerpt gia presente nel dominio.

Nessun item deve creare una URL verso una pagina thin inesistente.

## Publication/privacy contract

Mai restituire come destinazione pubblica:

- Article draft;
- Article review;
- Article scheduled;
- Concept draft/inactive;
- Question draft/inactive/non-approved;
- Question con target non pubblicato;
- Percorso inattivo;
- esperienza privata/non pubblica.

La fonte di verita per gli articoli resta `Article::published()`.

## Performance

V1 non introduce Elastic, Algolia, embeddings, vector DB o API esterne.

Regole:

- riusare l'attuale query articolo, che esiste gia e ha protezioni LIKE;
- query separate e bounded per gruppi piccoli (categorie/Percorsi/Concetti/Domande);
- limite breve per suggestion non-Article (es. massimo configurato dal consumer, da fissare in implementazione dopo misura del catalogo);
- nessun `LIKE %term%` aggiuntivo sul body fuori dall'engine Article gia esistente;
- nessuna eager-load non necessaria;
- misurare query count e query plan su SQLite + MariaDB prima del merge runtime.

Non viene fissato in questo design un limite numerico arbitrario per gruppo: va scelto dopo aver misurato cardinalita reale e UX.

## UX V1

Prima implementazione consigliata: estendere `/ricerca` server-side con sezioni accessibili.

Ordine della pagina:

1. suggerimenti esatti/strutturati (categorie, Percorsi, poi Concetti/Domande quando disponibili);
2. risultati Articoli paginati gia esistenti.

Nessun autocomplete JavaScript nella prima iterazione. Questo mantiene keyboard-first e riduce la complessita ARIA; autocomplete resta V2 dopo una specifica accessibilita dedicata.

## Collision / ownership

Implementazione runtime oggi non sicura perche:

- `SearchController.php` e toccato da #258;
- route/layout admin/search sono coinvolti da altre PR Growth;
- #279 non e su `main`;
- Concetti/Domande non devono essere duplicati.

Per questo la missione si ferma correttamente al design.

## Test contract per l'implementazione futura

Obbligatori:

- accenti italiani (`perché` / `perche` secondo comportamento tokenizer documentato);
- apostrofi dritti e tipografici;
- case-insensitive;
- trattini Unicode/ASCII;
- query solo punteggiatura/stopword -> zero risultati;
- exact title precede match articolo piu debole secondo engine attuale;
- category exact precede category token match;
- Percorso inattivo escluso;
- alias esatto trova il concetto corretto;
- concept inactive escluso;
- question non approvata esclusa;
- question con target scheduled/draft esclusa;
- nessuna URL verso topic/question page inesistente;
- query count bounded;
- MariaDB reale per qualunque SQL nuovo.

## Dipendenze per sbloccare implementation

1. #258 stabilizzata/mergiata o SearchController ownership liberata;
2. #279 realmente su `main` per Concetti/Alias/Domande;
3. decisione Missione 09 sulle superfici pubbliche Domande;
4. fresh-state audit prima di scrivere runtime code.

## Aggiornamento Missione 26 — TROVA Public Integration Design

Fresh-state audit (dipendenza 4 sopra) eseguito su `main` corrente. Verdetto:
**FOUNDATION_ONLY confermato** — non sufficientemente maturo per Missione 27
(implementazione pubblica), per una sola ragione bloccante reale, non tre.

### Le 4 dipendenze, ricontrollate

1. **#258 — ANCORA BLOCCANTE.** La PR è tuttora aperta, non mergiata, e il suo
   branch (`fix/category-source-debt-db-first`) è basato su un commit di
   `main` di circa 95 merge fa. Tocca esattamente
   `SearchController.php`/`ricerca.blade.php` (filtro/badge categoria) — la
   stessa superficie su cui Missione 27 dovrebbe scrivere. Costruire ora
   significherebbe basarsi su un file che #258 riscriverà, con conflitto
   quasi certo. La sua triage esplicita è già assegnata alla Missione 43
   ("Related Articles / Category Source-Debt Audit", Fase G) — questa
   missione delega lì invece di improvvisare una risoluzione qui.
2. **#279 (Content Graph) — RISOLTA.** Concetti/Alias/Domande sono ora su
   `main` (Missioni 16-25 di questo stesso batch): `Concept`,
   `ConceptAlias`, `ArticleConcept`, `ConceptQuestion`, con
   `ContentGraphService::discoverableConceptsForArticle()`/
   `answerableQuestionsForConcept()` come contratto di lettura pubblica già
   certificato da `ContentGraphPublicSafetyContractTest`. La sezione
   "Concetti / Alias (dopo #279)" sopra non è più ipotetica.
3. **Missione 09 (policy Domande) — RISOLTA.** La Missione 21 di questo
   batch ha chiuso esplicitamente il workflow di stato delle Domande
   (`ConceptQuestionReadinessService`); il contratto "solo domande
   approvate con risposta e target realmente pubblicato" è quello già
   applicato da `answerableQuestionsForConcept()`.
4. **Fresh-state audit — QUESTA missione.**

### TrovaEntitySearchService — stato reale confermato

`app/Services/Search/TrovaEntitySearchService.php` esiste già, è
genuinamente testato (`tests/Feature/TrovaEntitySearchServiceTest.php`, 11
test) ed è sicuro-per-costruzione per ciò che copre oggi: **Categorie**
(via `whereHas` su articoli pubblicati) e **Percorsi** (via
`ContentCluster::publiclyVisible()` + `ContentClusterPublicSequence`,
prefisso continuo pubblico obbligatorio — Percorsi programmati/futuri e
articoli oltre un gap sono già esclusi e testati). Non copre ancora
Articoli (di proprietà di `ArticleSearchService`) né Concetti/Domande
(dipendenza #279 appena risolta sopra, ma il servizio non è stato ancora
esteso per consumarli — resta lavoro della Missione 30, "TROVA Content
Graph Enrichment", deliberatamente non anticipato qui).

Nessuna PR o branch ha mai tentato di collegare `TrovaEntitySearchService`
a `SearchController`/`ricerca.blade.php` — verificato diffando tutti i
branch `*trova*` esistenti contro `main` su quei due file: diff vuoto.

### Cosa resta da fare prima che Missione 27 sia sicura

- #258 triaged/risolta (Missione 43) — unico vero blocco residuo;
- un builder di `url` per risultato non-Article (categorie/Percorsi hanno
  già uno slug pubblico risolvibile, ma nessun codice lo costruisce oggi);
- un limite per-gruppo scelto dopo aver misurato la cardinalità reale del
  catalogo (mai fissato, per design esplicito in questo stesso documento);
- il layout a sezioni accessibili descritto in "UX V1" sopra, non ancora
  implementato in `ricerca.blade.php`.

Nessuna riga di codice applicativo aggiunta da questa missione — solo
questo aggiornamento del design doc, che è l'handoff preciso richiesto
quando un'implementazione non è ancora sicura.
