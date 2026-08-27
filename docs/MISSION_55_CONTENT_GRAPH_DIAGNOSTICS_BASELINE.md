# Missione 55 — Content Graph per-item diagnostics baseline

## Esito

**IMPLEMENTED** — baseline documentale prodotta su `main` al commit
`05db4c0b447a803c0f7ba044af463c8bd7d52492`.

La Missione 55 non introduce codice applicativo. L'audit dimostra che gran parte
della diagnostica richiesta esiste già, ma alcune informazioni restano soltanto
aggregate o non vengono calcolate per singolo Concept/collegamento. Questi gap
sono input per le missioni 56–60 e non vanno colmati duplicando le regole
presenti.

## Confini e fonti canoniche

- `Article::published()` è il gate canonico per la raggiungibilità pubblica
  degli articoli.
- `ContentGraphService::answerableQuestionsForConcept()` è il gate canonico
  per una domanda pubblicamente rispondibile.
- `ContentGraphService::discoverableConceptsForArticle()` è il gate canonico
  per i Concept esponibili da un articolo pubblico.
- `ContentGraphCoverageService` espone conteggi aggregati.
- `ContentGraphOrphanAuditService` espone gli orfani articolo/Concept
  per elemento.
- `ConceptDuplicateAuditService` espone collisioni conservative tra nomi e
  alias di Concept diversi.
- `ConceptQuestionReadinessService` spiega, per domanda, perché il gate
  pubblico non è superato.
- `EditorialOperationsDashboardService` riusa il summary di coverage, ma non
  aggiunge una seconda regola di dominio.

## Matrice diagnostica

| Fact | Source of truth corrente | Visibilità admin corrente | Actionable? | Gap |
|---|---|---|---|---|
| Concept attivo senza articolo collegato | `ContentGraphCoverageService::conceptCoverage()` (conteggio) e `ContentGraphOrphanAuditService::orphanConcepts()` (righe) | Pagina **Concetti**: card aggregata e lista con link all'editor Concept | Sì | Nessun gap funzionale. Riutilizzare, non reimplementare. |
| Concept attivo senza domande | `ContentGraphCoverageService::questionCoverage()` usa `whereDoesntHave('questions')`; la listing carica `questions_count` | Card con totale; tabella paginata permette di osservare `Domande = 0`, ma non esiste una coda diagnostica dedicata | Parzialmente | Manca una struttura per-item esplicita, stabile e machine-readable; la tabella non distingue un conteggio editoriale da una diagnosi. |
| Concept attivo senza domanda pubblicamente rispondibile | Gate canonico: `ContentGraphService::answerableQuestionsForConcept()`; coverage espone solo `publicly_answerable_total` (totale domande, non totale Concept coperti) | Nessuna lista per Concept. Nell'editor di un singolo Concept sono spiegate solo le domande approvate non answerable | No, senza aprire e ricostruire manualmente ogni Concept | Gap reale: manca copertura per Concept che distingua zero domande, sole draft, approved non raggiungibili e almeno una answerable. Missione 57. |
| Alias/nome ambiguo tra Concept diversi | `ConceptDuplicateAuditService::audit()`: match esatto dopo normalizzazione conservativa, inclusi name↔name, name↔alias e alias↔alias | Pagina **Concetti** con gruppi, origine del match e link agli editor; pagina Concept offre il merge esplicito | Sì | Le collisioni cross-Concept richieste sono già presenti. Restano da caratterizzare alias vuoti/Unicode senza ampliare euristiche: Missione 58. |
| Alias duplicato nello stesso Concept | Vincolo DB scoped `concept_id + alias` e `ConceptAliasSyncService` | Gestito nel workflow di modifica, non presentato come incidente operativo | Sì, in scrittura | Nessuna evidenza di un gap cross-record; verificare la normalizzazione Unicode e dati legacy nella Missione 58, senza merge/cancellazioni automatiche. |
| Domanda approvata senza risposta | Gate canonico in `answerableQuestionsForConcept()`; codice `ANSWER_MISSING` in `ConceptQuestionReadinessService` | Editor Concept, sotto la domanda approvata non raggiungibile | Sì | Già presente. |
| Domanda approvata senza target | Gate canonico; codice `TARGET_MISSING` | Editor Concept | Sì | Già presente. |
| Domanda approvata con target non pubblico (draft/review/scheduled/futuro) | `Article::published()`; codice `TARGET_NOT_PUBLISHED` | Editor Concept | Sì | Già presente come categoria unica coerente col gate. Missione 59 deve caratterizzare i singoli stati solo se operativamente utile, senza duplicare il gate. |
| Domanda su Concept non attivo | Gate canonico; codice `CONCEPT_NOT_ACTIVE` | Editor Concept per domande approvate non answerable | Sì | Già presente. |
| Target articolo eliminato | Relazione `targetArticle` + controllo per chiave tramite `Article::published()`; un id non risolvibile ricade oggi in `TARGET_NOT_PUBLISHED` | Visibile, ma non distinto da un articolo esistente non pubblico | Parzialmente | Distinzione “eliminato/mancante” da “non pubblicato” da verificare contro FK e delete policy nella Missione 59. |
| Articolo pubblicato senza Concept | `ContentGraphCoverageService::articleCoverage()` e `ContentGraphOrphanAuditService::orphanArticles()` | Pagina **Concetti** con lista e link all'editor articolo; dashboard Operazioni editoriali riusa l'audit | Sì | Nessun gap funzionale. |
| Articolo pubblico collegato a Concept inattivo | Gate pubblico: `discoverableConceptsForArticle()` esclude Concept non attivi | Nessun audit per-item dedicato rilevato | No | Gap reale per Missione 60: relazione esistente ma inutile pubblicamente. |
| Concept attivo collegato soltanto ad articoli non pubblicabili | `articlesForConcept()` mostra i collegamenti interni; il gate pubblico è `Article::published()` | L'editor Concept mostra gli articoli e i loro stati, ma non classifica il Concept | Parzialmente | Gap reale per Missione 60: manca una diagnosi per-item derivata dal gate canonico. |
| Relazione Article↔Concept pubblicamente utile | Derivabile solo combinando `Article::published()` e stato active del Concept, già applicati in `discoverableConceptsForArticle()` | Nessun verdetto/codice per singola relazione | No | Gap reale; aggiungere solo una rappresentazione read-only nella Missione 60. |
| Relazione primary/supporting incoerente | `ArticleConcept` definisce i due valori; `ContentGraphService::linkArticle()` valida il vocabolario | Tipo visibile/modificabile | Non applicabile | Nessuna policy di coerenza rilevata (es. “un solo primary”). Non inventare un finding. |
| Peso fuori policy | `linkArticle()` accetta 0–255; persistenza integer | Validazione in scrittura | Sì | Nessuna ulteriore policy editoriale rilevata. Non inventare soglie. |

## Diagnosi già calcolate e correttamente esposte

1. Articoli pubblicati senza Concept.
2. Concept attivi senza alcun collegamento articolo.
3. Collisioni conservative tra nomi/alias di Concept diversi.
4. Per una domanda: stato non approvato, Concept non attivo, risposta assente,
   target assente e target non pubblicato.
5. Conteggi di Concept attivi senza domande e domande realmente answerable.

## Gap confermati per le missioni successive

### Missione 56 — Concept health classification

Non esiste una classificazione human-readable per singolo Concept né un set
unificato di codici diagnostici. Deve essere read-only e derivato da fatti già
presenti.

### Missione 57 — Public-answerable question coverage

Il summary corrente conta il numero totale di domande answerable, non quanti
Concept attivi abbiano copertura. Manca il dettaglio per Concept e la distinzione
tra zero domande, sole draft, approved non raggiungibili e almeno una answerable.

### Missione 58 — Concept alias integrity

La collisione conservativa cross-Concept esiste già. Va verificato soltanto ciò
che non è dimostrato: dati vuoti/legacy e comportamento Unicode. Nessuna
euristica linguistica o fusione automatica.

### Missione 59 — Concept question target integrity

La readiness per domanda esiste già. Il solo gap potenziale è distinguere un
target eliminato/non risolvibile da un target esistente ma non pubblico, se il
modello e la FK rendono davvero possibile il caso.

### Missione 60 — Article ↔ Concept relationship diagnostics

Mancano diagnosi per relazioni che non producono utilità pubblica: articolo
pubblico ↔ Concept inattivo e Concept attivo collegato soltanto a contenuti non
pubblici. Non esiste evidenza di policy aggiuntive su primary/supporting o peso.

## Query/performance osservate

- `ConceptDuplicateAuditService` usa eager loading di `aliases`: bounded,
  nessun N+1.
- La listing Concept usa `withCount` per alias, collegamenti e domande:
  bounded.
- `ContentGraphOrphanAuditService` usa due query set-based.
- `ContentGraphCoverageService::questionCoverage()` invoca
  `answerableQuestionsForConcept()` una volta per Concept attivo. Il codice
  documenta la scelta per un catalogo piccolo, ma la Missione 57 richiede
  esplicitamente una forma aggregabile e bounded: non perpetuare questo pattern
  nel dettaglio per-item.

## Cosa non cambia

- Nessuna tabella o migration.
- Nessuno stato di Concept, Article o ConceptQuestion.
- Nessun collegamento o alias.
- Nessuna regola pubblica.
- Nessuna UI.
- Nessun merge automatico.
- Nessuna chiamata a provider esterni o dato production.

## Verifica

Audit statico effettuato sul final baseline SHA
`05db4c0b447a803c0f7ba044af463c8bd7d52492`.

Modifica solo documentale: test applicativi, browser test e Pint non sono
pertinenti. La PR deve comunque essere sottoposta a `git diff --check` e
`scripts/local-release-check.sh` quando eseguita in un checkout locale.

GitHub Actions resta **CI_EXTERNAL_BLOCKER fino al 01/09/2026**: nessun job
viene dichiarato green senza allocazione del runner.
