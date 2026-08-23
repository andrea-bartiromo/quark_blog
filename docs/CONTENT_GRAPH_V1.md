# Content Graph V1 — foundation

## Obiettivo

Content Graph V1 introduce un livello semantico esplicito tra articoli e concetti editoriali, senza sostituire categorie, categorie secondarie, Percorsi o recommendation engine.

La foundation contiene quattro entita:

- `Concept`: nodo semantico canonico, con slug, definizione breve e stato editoriale;
- `ConceptAlias`: sinonimi o varianti linguistiche riferite a un concetto;
- `ArticleConcept`: relazione esplicita articolo-concetto, con `relation_type` (`primary` o `supporting`) e peso editoriale;
- `ConceptQuestion`: domanda/intento editoriale, con slug, stato e risposta-target opzionale.

A queste si aggiunge `ContentGraphService`, entry point applicativo che permette di usare il grafo senza modificare il model `Article` mentre quell'area e coinvolta in lavoro parallelo.

## Decisioni di dominio

La tassonomia resta intenzionalmente separata dai sistemi esistenti:

- `Category` / categorie secondarie = area editoriale ampia e discovery;
- `ContentCluster` / Percorso = sequenza curata e ordinata;
- `Concept` = nodo semantico riusabile;
- `ConceptQuestion` = intento/domanda editoriale;
- Speciale = esperienza editoriale dedicata;
- `ArticleContinuationService` = singolo next read deterministico;
- internal-link suggestions = suggerimenti di collegamento nel corpo articolo.

Il Content Graph non sostituisce nessuno di questi sistemi. Fornisce una relazione semantica esplicita e interrogabile che potra essere riusata da TROVA, Domande di scienza, Radar editoriale, linking e recommendation.

Non vengono introdotti embeddings, LLM, vector database o matching automatico.

## Schema

### concepts

- `name`
- `slug` unique
- `short_definition` nullable
- `status`: `draft`, `active`, `inactive`; default `draft`

Il default `draft` e deliberatamente safe-by-default: creare un concetto non lo rende automaticamente discoverable.

### concept_aliases

- `concept_id`
- `alias`
- unique `(concept_id, alias)`
- indice su `alias`

### article_concepts

- `article_id`
- `concept_id`
- `relation_type` (default `supporting`)
- `weight` 0-255, default 50
- unique `(article_id, concept_id)`
- indice compatto `(concept_id, relation_type, weight)`

### concept_questions

- `concept_id`
- `question`
- `slug` unique
- `answer_summary` nullable
- `target_article_id` nullable, `nullOnDelete`
- `sort_order`
- `status`: `draft`, `approved`, `inactive`; default `draft`
- indice `(concept_id, status, sort_order)`

La domanda puo quindi esistere come metadato editoriale interno anche senza una risposta-target. La cancellazione dell'articolo risposta non cancella la domanda: azzera soltanto `target_article_id`.

Le relazioni figlie del concetto usano cascade delete sul concetto. La cancellazione di un concetto non cancella mai un articolo.

## Contratto del servizio

`App\Services\ContentGraph\ContentGraphService` centralizza le operazioni V1 che devono rispettare invarianti di dominio.

### linkArticle

`linkArticle(Article, Concept, relationType, weight)`:

- accetta soltanto `primary` o `supporting`;
- accetta peso compreso fra 0 e 255;
- e idempotente sulla coppia `(article_id, concept_id)`;
- se la relazione esiste gia, aggiorna tipo/peso invece di creare un duplicato.

### Letture interne

- `conceptsForArticle()` restituisce tutti i link concetto di un articolo e precarica concetto + alias;
- `articlesForConcept()` restituisce tutti i link articolo di un concetto;
- `questionsForConcept()` restituisce tutte le domande del concetto, incluse draft/inactive, per futuri strumenti editoriali.

Queste API sono intenzionalmente interne e non equivalgono a una policy di pubblicazione.

### Letture discovery-safe

`discoverableConceptsForArticle()` restituisce dati solo quando:

- l'articolo soddisfa il gia esistente `Article::published()` (`status=published` e `published_at <= now()`);
- il concetto e `active`.

`answerableQuestionsForConcept()` restituisce dati solo quando:

- il concetto e `active`;
- la domanda e `approved`;
- esiste un `target_article_id`;
- `answer_summary` e non vuoto;
- il target soddisfa `Article::published()`.

Questi boundary impediscono a draft, review, scheduled o contenuti semanticamente non approvati di essere esposti accidentalmente da un futuro consumer pubblico.

## Migration safety

La migration e additiva: crea quattro nuove tabelle e non esegue `ALTER TABLE` su tabelle applicative esistenti. L'unica dipendenza e la tabella `articles`; non esiste backfill, seed automatico o trasformazione del catalogo esistente.

Il rollback elimina soltanto i nuovi metadati Content Graph e non cancella articoli. La compatibilita MariaDB reale resta un gate obbligatorio prima del merge.

## Admin scope

**NONE in questa PR.** Una futura admin UI va introdotta solo dopo il merge della foundation e un nuovo ownership audit.

## Public scope

**NONE.** Nessuna route, controller, vista o pagina pubblica per Concetti/Domande.

## Test contract

La suite dedicata copre slug/unicita/default, alias, relazioni, cascade/nullOnDelete, linking idempotente, relation/weight validation, isolamento publication-state, answer summary requirement e assenza di route pubbliche accidentali.

## Gate richiesti prima del merge

Focused test, full PHP suite, Pint, `git diff --check`, migrate/rollback e MariaDB/MySQL reale con rollback/forward. GitHub Actions indisponibile non equivale a certificazione.
