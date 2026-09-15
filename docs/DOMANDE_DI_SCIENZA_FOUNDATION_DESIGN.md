# Domande di scienza — foundation design

## Stato

**FOUNDATION MERGED — PUBLIC FEATURE STILL DEFERRED.** (aggiornato,
Cantiere 91, programma "100 cantieri Kairus")

Il modello Question/Concept proposto da #279 e ora su `main`, ammesso
solo in admin (`app/Models/Concept.php`, `app/Models/ConceptQuestion.php`,
`app/Http/Controllers/Admin/ConceptController.php`,
`ConceptQuestionController.php`, route `/admin/concetti`). Fa parte della
fondazione "Content Graph V1" (vedi
`docs/ENTITY_CONTRACTS_CONTENT_ENTITIES.md` per il contratto completo
del model reale). Questo NON significa che la missione descritta da
questo documento sia stata implementata: **nessuna route pubblica esiste oggi**
per `Concept`/`ConceptQuestion` (nessun `/domande/{slug}`, nessun hub
`/domande`), e nessuna delle regole di pubblicazione, SEO, structured
data o admin workflow descritte più sotto è stata costruita. Tutto il
resto di questo documento resta un design non ancora implementato,
salvo lo stato della fondazione appena corretto qui sopra.

## Dipendenza reale

Il design assume il contratto che #279 proponeva, **ora disponibile a
runtime** tramite il model reale mergiato:

- question text (`ConceptQuestion::$question`);
- slug (`ConceptQuestion::$slug`, auto-generato dal testo se assente);
- concept (`ConceptQuestion::concept()`, belongsTo `Concept`);
- target article opzionale (`ConceptQuestion::$target_article_id` /
  `targetArticle()`);
- answer summary (`ConceptQuestion::$answer_summary`);
- status (`ConceptQuestion::STATUS_DRAFT`/`STATUS_APPROVED`/
  `STATUS_INACTIVE` — combaciano esattamente con il contratto previsto
  sotto);
- sort order (`ConceptQuestion::$sort_order`).

La fondazione esiste; l'implementazione della missione pubblica
descritta da questo documento (route, hub, SEO, admin workflow
dedicato) resta da costruire come una PR atomica separata, seguendo
comunque un fresh-state audit del model reale prima di iniziare (i
campi sopra sono già verificati, ma nessuna logica di pubblicazione
esiste ancora).

## Obiettivo editoriale

Una Domanda di scienza e un **intent editoriale curato**, non una pagina SEO generata da keyword.

Una domanda puo esistere internamente anche senza pagina pubblica, per supportare TROVA/Radar e pianificazione redazionale.

Nessuna domanda viene generata automaticamente da query, LLM, Search Console o Content Graph.

## Stati e workflow

Gli stati del model reale (`ConceptQuestion::STATUS_*`) sono ora
verificabili direttamente: combaciano con il contratto previsto sotto.

Contratto previsto (confermato contro il model reale):

- `draft`: metadato/editorial work in progress, mai pubblico;
- `approved`: approvato dalla redazione, ma la pubblicabilita dipende anche dalla risposta e dal concetto;
- `inactive`: escluso da discovery/pubblicazione.

Transizioni sempre esplicite e human-reviewed.

Nessun evento automatico puo trasformare una domanda in `approved`.

## Publication rule

Una pagina domanda autonoma puo essere pubblica **solo** quando tutte le condizioni seguenti sono vere:

1. question status = `approved`;
2. concept status = `active`;
3. `target_article_id` valorizzato;
4. target article soddisfa l'attuale `Article::published()`;
5. `answer_summary` non vuoto;
6. esiste una destinazione pubblica reale per l'articolo risposta.

Queste condizioni sono intenzionalmente qualitative ma deterministiche: non viene inventata una soglia arbitraria di caratteri/parole.

`answer_summary` non deve essere una copia del body dell'articolo: e una risposta breve editoriale che orienta al contenuto completo.

Se una sola condizione manca, la domanda resta metadato interno e non deve produrre una pagina pubblica indicizzabile.

## Best answer

V1 usa **un solo target article esplicito** come risposta principale, coerente con il contratto #279.

Non viene introdotto un ranking automatico di piu articoli risposta.

Se in futuro servira una risposta multipla, dovra essere progettata come estensione separata con ordinamento editoriale esplicito.

## Related questions

Il requisito di related questions non giustifica oggi una nuova pivot table prima che #279 sia su `main` e il catalogo reale esista.

V1 puo inizialmente derivare related questions dallo stesso Concept, ordinate con `sort_order`/id e filtrate dalla stessa publication rule.

Solo se la redazione avra bisogno di relazioni cross-concept curate sara opportuno introdurre una relazione esplicita `question_related_question` in una missione successiva.

## Public page design

Route proposta **solo dopo dependency gate**:

`GET /domande/{slug}`

Contenuto minimo:

- H1 = question text;
- risposta breve = `answer_summary`;
- link/card evidente al best answer article;
- concetto come contesto editoriale solo se esiste una superficie pubblica reale oppure come testo non-linkato;
- eventuali related questions realmente pubblicabili;
- breadcrumb coerente con le convenzioni Kairus.

La pagina non deve ripubblicare il body dell'articolo target.

## Hub `/domande`

**Deferred finche il catalogo non e sufficiente.**

Non viene fissato un numero arbitrario di domande necessario per aprire l'hub. Prima dell'implementazione occorre misurare il catalogo reale dopo attivazione editoriale.

Regola: se l'hub sarebbe sostanzialmente vuoto o composto da poche pagine non curate, non va pubblicato.

TROVA puo usare le domande come metadato anche in assenza dell'hub.

## SEO policy

Principi:

- nessun keyword stuffing;
- nessuna pagina generata automaticamente da query Search Console;
- nessuna duplicazione del testo dell'articolo;
- canonical self-referencing solo per pagine realmente pubblicabili;
- draft/inactive/non-answerable non devono essere routabili come pagine pubbliche;
- sitemap solo dopo esistenza di superfici pubbliche reali e usando la stessa publication rule.

### Structured data

Non introdurre automaticamente `QAPage`/`FAQPage` solo perche la pagina contiene una domanda.

Kairus usa structured data specifico quando semanticamente giustificato. Una pagina editoriale con una singola domanda e una risposta curata non deve fingere un formato community Q&A.

Prima iterazione: riusare soltanto primitive gia affidabili nel progetto (es. breadcrumb/WebPage dove appropriato), dopo audit delle convenzioni correnti. Una nuova tipologia schema.org richiede review SEO separata.

## Admin workflow

UI futura minimale, dopo ownership liberata:

- lista domande per concept/status;
- crea/modifica question text, slug, concept, answer summary, target article, status, sort order;
- preview della publication eligibility;
- nessun pulsante "genera domande";
- nessuna approvazione automatica.

Le superfici Admin Article sono oggi occupate da PR parallele e non vengono modificate da questa missione.

## TROVA integration

TROVA puo restituire una domanda soltanto se soddisfa almeno il contratto di discovery definito nella Missione 08.

Se una domanda e approvata ma non ha pagina autonoma pubblicabile, TROVA puo usare il suo testo/alias come segnale per portare direttamente al target article pubblico, senza inventare `/domande/{slug}`.

## Test contract per implementation futura

### Domain

- slug generato/univoco secondo model reale;
- duplicate slug respinto;
- status draft non pubblico;
- inactive non pubblico;
- concept inactive blocca publication.

### Answer publication

- approved + published target + nonblank summary -> eligible;
- target draft -> non eligible;
- target review -> non eligible;
- target scheduled -> non eligible;
- target published_at futuro -> non eligible;
- target deleted/null -> non eligible;
- summary vuoto -> non eligible.

### Public routing

- non-eligible slug -> 404, non soft page;
- eligible slug -> 200;
- nessun leak di titolo/data scheduled;
- canonical corretto;
- nessuna duplicazione completa del body target.

### Related

- stessa domanda esclusa;
- solo related eligible;
- ordine deterministico.

### Hub

- nessun hub se catalogo reale non supera il quality gate editoriale definito prima dell'implementazione;
- nessuna pagina thin creata solo per riempire il catalogo.

### SEO regression

- nessuna question draft/inactive in sitemap;
- nessun `QAPage`/`FAQPage` introdotto senza decisione esplicita;
- canonical e breadcrumb coerenti con le convenzioni Kairus.

## Sblocco implementation

1. ~~#279 realmente mergiata su `main`~~ — **fatto**: `Concept`/
   `ConceptQuestion` sono su `main`, ammessi solo in admin (vedi
   "Stato" sopra);
2. fresh-state audit del model/migration effettivi — parzialmente
   fatto qui (campi/stati confermati), ma nessun audit della logica di
   pubblicazione perché non esiste ancora;
3. ownership route/controller/admin libera — da verificare al momento
   dell'implementazione;
4. catalogo editoriale reale sufficiente per decidere se `/domande` abbia senso;
5. implementazione e gate PHP/MariaDB/browser in PR atomica separata.
