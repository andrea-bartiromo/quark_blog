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
- il target soddisfa `Article::published()`.

Questi boundary impediscono a draft, review, scheduled o contenuti semanticamente non approvati di essere esposti accidentalmente da un futuro consumer pubblico.

## Migration safety

### Lock risk

La migration e additiva: crea quattro nuove tabelle e non esegue `ALTER TABLE` su tabelle applicative esistenti. Le foreign key referenziano `articles` e `concepts`; la creazione dei vincoli puo richiedere metadata lock brevi, ma non riscrive righe del catalogo articoli. Prima del merge resta obbligatoria la prova su MariaDB reale.

### Data dependency

L'unica dipendenza dati/schema e l'esistenza della tabella `articles` prima della migration. Non esiste alcun backfill, seed automatico o trasformazione del catalogo esistente.

### Rollback lossiness

Il rollback elimina le quattro tabelle del Content Graph. Prima dell'uso editoriale reale e reversibile senza perdita di dati preesistenti Kairus; dopo l'inserimento di concetti/alias/domande/relazioni, un rollback eliminerebbe quei nuovi metadati semantici. Non elimina articoli.

### MariaDB compatibility

Lo schema usa tipi e foreign key standard Laravel/MySQL-MariaDB e nomi espliciti compatti per gli indici compositi (`article_concepts_lookup_idx`, `concept_questions_order_idx`). La compatibilita reale MariaDB **non e dichiarata verificata in questa sessione**: il gate migrate fresh + rollback/forward su MariaDB/MySQL reale e obbligatorio prima del merge.

## Admin scope

**NONE in questa PR.** Il prompt rende l'admin minimale opzionale (`solo se necessario`). La foundation e gia utilizzabile tramite servizio e modelli, mentre Admin Article/controller/form/routes sono contemporaneamente oggetto di PR parallele. Aggiungere CRUD o relazione articolo-concetto ora aumenterebbe inutilmente il rischio di collisione.

Una futura admin UI potra essere introdotta dopo fresh-state audit, con workflow human-reviewed e senza generazione automatica.

## Public scope

**NONE.** Questa PR non aggiunge route, controller, viste o pagine pubbliche per Concetti/Domande. Un regression test verifica esplicitamente l'assenza di route `concetti*` e `domande*`.

## Test contract

`ContentGraphFoundationTest` copre:

- slug e default draft dei concetti;
- slug univoco e default draft delle domande;
- alias e loro unicita per concetto;
- relazione articolo-concetto e unicita;
- peso di default;
- cascade delete del concetto senza cancellazione dell'articolo;
- `nullOnDelete` della risposta-target;
- linking idempotente;
- rifiuto di relation type e peso invalidi;
- isolamento draft/review/scheduled nella discovery dei concetti;
- isolamento di domande non approvate, concetti inattivi e target non pubblici;
- nessuna pubblicazione accidentale tramite nuove route.

## Scope intenzionalmente rinviato

Non sono inclusi:

- editor admin dei concetti;
- relazione Eloquent `Article::concepts()`;
- matching automatico o AI;
- pagine pubbliche `/concetti/*` o `/domande/*`;
- integrazione con TROVA;
- integrazione con recommendation engine;
- seed di concetti editoriali inventati.

Questi elementi vanno aggiunti solo dopo il merge/rebase delle PR Article/Admin attualmente aperte e dopo una nuova collision analysis.

## Gate richiesti prima del merge

La migration deve essere verificata su SQLite e MariaDB/MySQL reali, inclusi migrate fresh e rollback/forward. Sono inoltre richiesti focused test, full PHP suite, Pint e `git diff --check` sul branch integrato con il `main` corrente.

GitHub Actions e temporaneamente indisponibile per quota fino al 1 settembre 2026; l'assenza di CI non va interpretata come certificazione o failure del codice.
