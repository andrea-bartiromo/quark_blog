# Dashboard e Next Action V2 — Kairus

Guida tecnica all'automazione dell'area Progettazione introdotta dalla
missione "Kairus Dashboard Automation & Next Action V2": come Kairus
calcola la prossima azione utile per ciascun progetto, cosa distingue un
fatto derivato da una decisione umana, le regole di sicurezza sulle
dipendenze tra task, l'algoritmo di avanzamento e lo stato di salute dei
progetti. Costruita sopra l'automazione del Piano Editoriale
(`docs/PROJECT_EDITORIAL_AUTOMATION.md`, PR #156), che resta invariata e
non subisce regressioni.

## Indice

1. [Filosofia](#1-filosofia)
2. [Matrice delle automazioni](#2-matrice-delle-automazioni)
3. [Dipendenze tra task](#3-dipendenze-tra-task)
4. [Next Action V2](#4-next-action-v2)
5. [Project Health](#5-project-health)
6. [Avanzamento progetto](#6-avanzamento-progetto)
7. [Dashboard e pagina progetto](#7-dashboard-e-pagina-progetto)
8. [Cronologia e provenienza](#8-cronologia-e-provenienza)
9. [Scheduler](#9-scheduler)
10. [Performance](#10-performance)
11. [Limiti noti](#11-limiti-noti)
12. [Rollback](#12-rollback)

---

## 1. Filosofia

> Automatizzare il lavoro amministrativo, non la responsabilità
> editoriale. Una derivazione sicura può essere automatica. Una decisione
> editoriale deve restare umana.

Ogni servizio descritto qui è di **sola lettura** sul contenuto
editoriale: nessuno crea, cancella o modifica un articolo, un titolo, una
data pianificata deliberatamente, o scollega automaticamente una
relazione impostata da un umano. L'unica scrittura automatica introdotta
da questa missione è il ricalcolo di `Project::progress` (già esistente
da prima, non toccato nella sua logica) — Next Action V2 e Project Health
non scrivono **mai** nulla.

## 2. Matrice delle automazioni

| Azione | Automatica | Suggerita | Manuale | Motivo |
|---|:---:|:---:|:---:|---|
| Sincronizzare lo stato di una task da un articolo collegato | ✅ | | | Fatto derivabile in modo inequivocabile dallo stato editoriale dell'articolo (`ProjectTaskSyncService`) — mai sovrascrive Bloccato/Sospeso/Annullato o un `manual_override` |
| Sincronizzare lo stato di una task di Sviluppo da GitHub | ✅ | | | Fatto derivabile dallo stato reale di branch/PR (`ProjectTaskGithubSyncService`) — stesse protezioni di cui sopra |
| Collegare una voce di calendario a un articolo (match sicuro) | ✅ | | | Titolo identico o quasi identico, candidato unico — mai un match ambiguo (`EditorialCalendarLinkingService`, PR #156) |
| Scollegare un articolo da un progetto | | | ✅ | Mai automatico, in nessun caso — un'operazione distruttiva su una relazione impostata da un umano |
| Cambiare il titolo di un articolo | | | ✅ | Decisione editoriale, mai dedotta da questa missione |
| Cambiare la data pianificata di un articolo | | | ✅ | Decisione editoriale — le discrepanze di data sono solo segnalate (`EditorialCalendarReconciliationService`), mai corrette |
| Completare un progetto (`operational_status` → completato) | | ✅ | | Next Action segnala "tutte le attività sono completate" quando vero — la conferma resta sempre un click umano |
| Cambiare lo stato operativo di un progetto | | | ✅ | Sempre tramite il selettore di stato rapido esistente — nessuna automazione lo tocca |
| Creare una nuova attività (`ProjectTask`) | | | ✅ | Nessuna automazione in Kairus crea mai una task — vale anche per questa missione |
| Aggiornare l'avanzamento (`Project::progress`) | ✅ | | | Calcolo deterministico dalle task completate (`ProjectProgressService`, preesistente) — mai una stima editoriale |
| Suggerire la prossima azione (Next Action V2) | | ✅ | | Calcolato al bisogno, mai scritto in `next_action` — un click umano lo applica, se lo ritiene valido |
| Impedire una dipendenza tra task non valida (ciclo, cross-project) | ✅ | | | Un'invariante strutturale, non una decisione editoriale — rifiutata sempre con un errore esplicito, mai corretta silenziosamente |

## 3. Dipendenze tra task

`ProjectTask::depends_on_task_id` resta **dormiente**: nessun
form/richiesta HTTP lo espone ancora (`StoreProjectTaskRequest`/
`UpdateProjectTaskRequest` non lo validano). Questa missione lo rende
**sicuro da attivare in futuro**, non lo attiva:

`ProjectTask::guardAgainstInvalidDependency()` (hook `saving()`) rifiuta,
sollevando `InvalidTaskDependencyException`:

- **auto-dipendenza** (una task che dipende da se stessa);
- **ciclo diretto o transitivo** (A→B→A, A→B→C→A, di qualunque
  lunghezza — risale la catena a partire dal candidato);
- **dipendenza cross-project** (V1: sempre interna allo stesso progetto,
  nessuna decisione architetturale la ammette oggi).

Rimuovere una dipendenza (tornare a `null`) non attraversa mai la
guardia. Un'eccezione, non una coercizione silenziosa: a differenza di un
flag booleano "al più uno attivo", non esiste un valore di ripiego
ragionevole per un grafo di dipendenze non valido.

**"Bloccata da dipendenza" ≠ "Bloccata" editorialmente.**
`ProjectTask::isBlockedByDependency()` è un fatto **derivato** (la
dipendenza esiste e il suo `manual_status` non è `completed`) — mai da
confondere con lo stato esplicito `ProjectTask::STATUS_BLOCKED`, deciso
sempre da un umano. Una task può essere l'uno, l'altro, entrambi o
nessuno dei due.

## 4. Next Action V2

`ProjectNextActionResolverV2` compone segnali da più fonti già esistenti
e testate, senza duplicarne la logica:

| Fonte | Servizio riusato | Segnali |
|---|---|---|
| Task | `ProjectNextActionResolver` (v1) | scaduta, in scadenza, eleggibile da avviare, in attesa di dipendenza |
| GitHub | Interrogazione diretta (`derived_status`/`manual_status`) | PR mergiata ma task non completata (bloccata/sospesa/annullata/override) |
| Calendario editoriale | `EditorialCalendarReconciliationService` (PR #156) | richiede revisione, articolo mancante, discrepanza di data, discrepanza di stato |
| Progetto | Attributi diretti | scaduto, in scadenza, completabile |

Ogni segnale (`NextActionSuggestion`) è dato strutturato — `code`,
`label`, `rationale`, `severity` (`urgent`/`attention`/`info`), `source`,
`requiresHumanDecision`, entità collegata — **mai** una stringa già
pronta: la presentazione resta sempre una scelta della view.
`resolve()` restituisce il segnale più severo; se nessuno è rilevante,
restituisce sempre `NextActionSuggestion::aligned()` ("Progetto
allineato — nessuna azione richiesta"), mai un suggerimento inventato.

Priorità tra fonti a parità di severità: task → GitHub → calendario
editoriale → progetto (ordine di inserimento in `collectSignals()`,
`usort()` stabile da PHP 8).

**Non sostituisce** `Project::suggestedNextAction()` né
`EditorialCalendarNextActionResolver` (PR #156, usato per il campo
manuale `next_action` nel form "Modifica progetto") — sono due concetti
distinti e sempre visibili entrambi nella pagina progetto: "Prossima
azione editoriale" (manuale, mai sovrascritta) e "Suggerimento
automatico" (calcolato, mai scritto).

## 5. Project Health

`ProjectHealthResolver` sintetizza in tre livelli — OK / Attenzione /
Bloccato — "devo fare qualcosa adesso?". Deliberatamente **derivato**
dalla severità dei segnali di Next Action V2, non una seconda regola
parallela (due motori di classificazione indipendenti sulla stessa base
di fatti rischierebbero di divergere silenziosamente):

```
stato esplicito "Bloccato"        → sempre Bloccato
almeno un segnale "urgent"        → Bloccato
almeno un segnale "attention"     → Attenzione
altrimenti                        → OK
```

Nessun badge per OK: "non utilizzare etichette allarmistiche per
condizioni normali" — il badge compare solo quando c'è davvero qualcosa
da notare.

## 6. Avanzamento progetto

`Project::progress` / `ProjectProgressService` (Blocco D, preesistente)
**non sono stati modificati**: restano l'unica fonte per l'avanzamento
basato sulle task. Questa missione ha aggiunto solo
`Project::hasCalculableProgress()` — un progetto senza alcuna task non
annullata mostra "Non calcolabile" invece di "0%", che sarebbe
indistinguibile da "0% fatto per davvero". Applicato a form, panoramica
progetto ed elenco progetti (quest'ultimo via `withCount()`, non una
query aggiuntiva per riga).

Per i progetti con un calendario editoriale collegato, la metrica più
significativa esiste già dalla PR #156
(`EditorialCalendarProgress` — copertura e percentuale pubblicata, due
numeri distinti, mai un'unica percentuale che confonderebbe concetti
diversi): nessun nuovo algoritmo necessario lì.

## 7. Dashboard e pagina progetto

**Pagina progetto**: badge di salute in testata (visibile su ogni tab,
non solo la panoramica) e scheda "Suggerimento automatico" nella
panoramica, sempre distinta dalla "Prossima azione editoriale" manuale.
Un solo progetto per richiesta: nessun rischio di crescita con la
dimensione del database.

**Dashboard**: nuova card "Richiedono attenzione" in cima, limitata ai
progetti già mostrati in "Progetti attivi" (limite 10) e "Progetti
bloccati" — **mai** l'intera tabella progetti — filtrati per
`ProjectHealth::level !== OK`, ordinati per severità, massimo 8. Una nota
aggregata (una query) sul numero di attività in attesa di una dipendenza
non ancora completata, in tutti i progetti. Nessuna nuova card se non
c'è nulla da segnalare — la dashboard non deve diventare più affollata.

Deliberatamente **non introdotta**: un'aggregazione "copertura Piano
Editoriale" sulla dashboard — duplicherebbe `project:editorial-audit`
(PR #156) e la scheda già presente nella panoramica di ogni progetto con
calendario, senza un beneficio proporzionato al rischio di affollare la
pagina principale.

## 8. Cronologia e provenienza

Nessun nuovo evento di Cronologia da questa missione: Next Action V2 e
Project Health sono entrambi di sola lettura. Un solo miglioramento alla
provenienza esistente: `ProjectActivityLog::SOURCE_GITHUB`, distinta
dalla generica `SOURCE_SYSTEM`, per il completamento automatico di una
task alla merge di una pull request (`ProjectTaskGithubSyncService::
handleMerge()`) — "il sistema ha dedotto qualcosa dallo stato di un
articolo" e "GitHub ha segnalato un merge" sono provenienze diverse.

Provenienze oggi distinguibili in Cronologia: **Manuale**, **Automatico**
(`SOURCE_SYSTEM` — sync stato da articolo, collegamento al progetto
editoriale predefinito), **GitHub** (`SOURCE_GITHUB`), **Sync
calendario** (`SOURCE_EDITORIAL_SYNC`, PR #156). Nessun source letterale
"Scheduler": ogni meccanismo invocato dallo scheduler ha già una sorgente
più specifica — un'etichetta generica sarebbe meno informativa di quelle
esistenti, non di più.

La paginazione della Cronologia (15/pagina) esisteva già prima di questa
missione (Correzione #5, PR precedenti) — verificata, non reintrodotta.
I marker `PROJECT_ARTICLE_LINKED`/`PROJECT_ARTICLE_UNLINKED` introdotti
dalla PR #156 (protezione dell'unlink manuale contro il ri-collegamento
automatico) sono preservati e non toccati.

## 9. Scheduler

I tre scheduler dell'area Progettazione (`projects:sync-derived-statuses`,
`projects:sync-github-tasks`, `project:sync-editorial-calendar --execute`,
ogni 5 minuti) sono stati auditati, non modificati: tutti e tre hanno già
`withoutOverlapping()`, delegano a servizi idempotenti già testati, e
operano su insiemi di righe disgiunti (task di Pubblicazione, task di
Sviluppo, pivot `project_article` — mai la stessa riga contesa da due
scheduler). Nessuna frequenza aumentata, nessuno scheduler aggiuntivo
introdotto: Next Action V2 e Project Health sono calcolati al bisogno
(on-read), mai materializzati da un comando schedulato.

## 10. Performance

Un dataset realistico (11 progetti, ~50 articoli, decine di task incluse
dipendenze reali, un calendario editoriale, un log corposo —
`ProjectDashboardPerformanceTest`) misura ~64 query per il caricamento
della dashboard e ~13 per la pagina di un singolo progetto (~13 anche per
un progetto con calendario editoriale, su qualunque tab). La sezione
"Richiedono attenzione" opera su un insieme **deliberatamente limitato**:
i progetti già mostrati altrove in dashboard, **e comunque mai più di
`ProjectDashboardController::MAX_ATTENTION_CANDIDATES` (20)** prima di
risolvere — verificato confrontando il costo con 30 e con 150 progetti
bloccati nel database: identico in entrambi i casi. `ProjectHealthResolver`
e la view sulla pagina progetto condividono lo stesso calcolo per
progetto (`ProjectHealth::$signals`) — richiamare
`ProjectNextActionResolverV2::resolve()` separatamente raddoppierebbe le
query senza motivo (bug reale trovato e corretto in review su questa
stessa PR, vedi §"Trovato in review" nel report finale).

## 11. Limiti noti

- `depends_on_task_id` resta non raggiungibile da alcuna UI: attivarlo
  richiederà comunque una decisione di prodotto (come esporlo nel form,
  quali task proporre come candidate) non dedotta da questa missione.
- Nessun blocco pessimistico/ottimistico sulle righe `ProjectTask`
  durante una sincronizzazione schedulata: una modifica manuale
  concorrente nella finestra di pochi millisecondi di una singola riga in
  aggiornamento segue la normale semantica "ultimo salvataggio vince" di
  Eloquent — un limite condiviso con il resto dell'applicazione, non
  introdotto da questa missione.
- La sezione "Richiedono attenzione" della dashboard è limitata ai
  progetti già mostrati in "Progetti attivi"/"Progetti bloccati": un
  progetto attivo a bassa priorità escluso da quell'elenco (limite 10)
  non compare mai in "Richiedono attenzione", anche se avesse un segnale
  urgente — accettato come costo del restare un insieme bounded.
- `ProjectTaskSyncService::syncAll()` non isola gli errori per singola
  task (a differenza della variante GitHub, che lavora su rete esterna
  inaffidabile): un'eccezione imprevista su una task interromperebbe
  l'intero batch. Basso rischio (nessuna I/O esterna nella sincronizzazione
  da articolo), non modificato in questa missione.

## 12. Rollback

- **Disattivare Next Action V2 / Project Health nella UI**: rimuovere i
  blocchi corrispondenti da `show.blade.php` e `dashboard.blade.php` e le
  relative variabili dai controller — nessuna scrittura da annullare,
  sono entrambi puramente derivati.
- **Disattivare la guardia sulle dipendenze**: rimuovere la chiamata a
  `guardAgainstInvalidDependency()` in `ProjectTask::booted()` — il campo
  torna dormiente come prima di questa missione (nessuna migrazione
  coinvolta, nessuno schema cambiato).
- **Tornare a `SOURCE_SYSTEM` per gli eventi GitHub**: rimuovere
  `SOURCE_GITHUB` e il suo uso in `ProjectTaskGithubSyncService::
  handleMerge()` — nessun dato storico da migrare, la colonna `source` è
  testo libero.
- Nessuna migrazione introdotta da questa missione: rollback sempre
  possibile con un semplice revert del codice applicativo.
