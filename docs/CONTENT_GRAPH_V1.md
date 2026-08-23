# Content Graph V1 — foundation

## Obiettivo

Content Graph V1 introduce un livello semantico esplicito tra articoli e concetti editoriali, senza sostituire categorie, categorie secondarie, Percorsi o recommendation engine.

La foundation contiene quattro entita:

- `Concept`: concetto editoriale canonico, con slug stabile e stato attivo;
- `ConceptAlias`: sinonimi o varianti linguistiche riferite a un concetto;
- `ArticleConcept`: relazione esplicita articolo-concetto, con `relation_type` (`primary` o `supporting`) e peso editoriale;
- `ConceptQuestion`: domande editoriali strutturate associate a un concetto.

## Separazione dai sistemi esistenti

Il Content Graph non duplica:

- `Category` / categorie secondarie: classificano e organizzano la discovery editoriale;
- `ContentCluster` / Percorsi: definiscono sequenze curate e ordinate;
- `ArticleContinuationService`: sceglie un singolo next read deterministico;
- internal-link suggestions: suggeriscono collegamenti editoriali nel testo.

Il grafo aggiunge una relazione semantica esplicita e interrogabile che potra essere riusata in seguito da TROVA, Domande di scienza, Radar editoriale e strumenti di copertura tematica.

## Schema

### concepts

- `name`
- `slug` unique
- `description` nullable
- `is_active`

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
- `answer_summary` nullable
- `sort_order`
- `is_active`
- indice `(concept_id, is_active, sort_order)`

Tutte le relazioni figlie usano cascade delete sul concetto. La cancellazione di un concetto non cancella mai l'articolo collegato.

## Scope intenzionalmente limitato

Questa PR e una foundation isolata. Non modifica `Article.php`, controller, route, admin UI o superfici pubbliche. La scelta e intenzionale per evitare collisioni con PR parallele attive nell'area Article/Admin.

Non sono inclusi:

- editor admin dei concetti;
- relazione Eloquent `Article::concepts()`;
- matching automatico o AI;
- pagine pubbliche `/concetti/*`;
- integrazione con TROVA;
- integrazione con recommendation engine;
- seed di concetti editoriali inventati.

Questi elementi vanno aggiunti solo dopo il merge/rebase delle PR Article/Admin attualmente aperte e dopo una nuova collision analysis.

## Gate richiesti prima del merge

La migration deve essere verificata su SQLite e MariaDB/MySQL reali, inclusi migrate fresh e rollback/forward. La suite completa e Pint devono essere verdi sul branch integrato con il `main` corrente.

GitHub Actions e temporaneamente indisponibile per quota fino al 1 settembre 2026; l'assenza di CI non va interpretata come certificazione o failure del codice.
