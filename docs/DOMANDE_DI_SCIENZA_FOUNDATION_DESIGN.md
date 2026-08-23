# Domande di scienza — foundation design

## Stato

**DESIGN COMPLETE — IMPLEMENTATION DEFERRED.**

Il modello Question/Concept non e su `main`: #279 e una PR draft separata. Questa missione non duplica migration, model o service del Content Graph.

## Dipendenza reale

Il design assume soltanto il contratto che #279 propone, senza considerarlo disponibile a runtime:

- question text;
- slug;
- concept;
- target article opzionale;
- answer summary;
- status;
- sort order.

Qualunque implementazione deve ripartire da un fresh-state audit dopo il merge effettivo di #279.

## Obiettivo editoriale

Una Domanda di scienza e un **intent editoriale curato**, non una pagina SEO generata da keyword.

Una domanda puo esistere internamente anche senza pagina pubblica, per supportare TROVA/Radar e pianificazione redazionale.

Nessuna domanda viene generata automaticamente da query, LLM, Search Console o Content Graph.

## Stati e workflow

Usare gli stati del model reale quando #279 sara su `main`.

Contratto previsto:

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

1. #279 realmente mergiata su `main`;
2. fresh-state audit del model/migration effettivi;
3. ownership route/controller/admin libera;
4. catalogo editoriale reale sufficiente per decidere se `/domande` abbia senso;
5. implementazione e gate PHP/MariaDB/browser in PR atomica separata.
